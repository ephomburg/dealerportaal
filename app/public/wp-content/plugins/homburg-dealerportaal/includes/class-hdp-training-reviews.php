<?php
/**
 * Beoordelingen van trainingen: na afloop geeft een deelnemer een cijfer, en een
 * vervolgtraining laat zien wat deelnemers van de vorige vonden.
 *
 * De deelnemer krijgt een mail met een persoonlijke link. Inloggen is niet
 * nodig: wie door een collega is aangemeld, heeft vaak geen eigen account. De
 * sleutel in de link is af te leiden uit het nummer van de aanmelding, dus een
 * herinnering werkt met dezelfde link en er hoeft niets extra's bewaard te worden.
 *
 * Elk cijfer telt mee in het gemiddelde. Een reactie wordt pas zichtbaar als
 * de deelnemer dat goed vindt en Homburg hem heeft goedgekeurd.
 *
 * @package Homburg_Dealerportaal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * De cijfers, de uitnodigingen en het tonen ervan.
 */
class HDP_Training_Reviews {

	const TABEL = 'hdp_training_reviews';
	const HOOK  = 'hdp_training_reviews_mailen';

	/** Onder dit aantal zegt een gemiddelde te weinig. */
	const MIN_CIJFERS = 3;

	/** Hoeveel reacties er hoogstens bij een vervolgtraining staan. */
	const MAX_REACTIES = 6;

	/** Tot hoe lang na de training de eerste uitnodiging nog uitgaat. Oudere trainingen krijgen geen mail meer. */
	const VENSTER_DAGEN = 14;

	/** Na hoeveel dagen de ene herinnering volgt. */
	const HERINNERING_DAGEN = 7;

	/** Niet meer mails per ronde: de rest volgt de dag erna. */
	const MAX_PER_RONDE = 50;

	const MAX_TEKENS = 1000;

	/** Een mislukte poging, om hetzelfde formulier met de fout terug te tonen. */
	private static $fout = '';

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'verstuur_ronde' ) );
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		}
		add_action( 'template_redirect', array( __CLASS__, 'verwerk' ) );
		add_action( 'admin_menu', array( __CLASS__, 'registreer_menu' ) );
		add_action( 'admin_post_hdp_training_review_zichtbaar', array( __CLASS__, 'verwerk_zichtbaar' ) );
		add_action( 'admin_post_hdp_training_aanwezig', array( __CLASS__, 'verwerk_aanwezig' ) );
	}

	public static function tabel() {
		global $wpdb;
		return $wpdb->prefix . self::TABEL;
	}

	/* === De link in de mail ============================================= */

	public static function sleutel( $aanmelding_id ) {
		return substr( hash_hmac( 'sha256', 'hdp-review-' . (int) $aanmelding_id, wp_salt( 'auth' ) ), 0, 40 );
	}

	public static function link( $aanmelding_id ) {
		return add_query_arg(
			array(
				'beoordeel' => (int) $aanmelding_id,
				'sleutel'   => self::sleutel( $aanmelding_id ),
			),
			HDP_Trainingen::url()
		);
	}

	/**
	 * De aanmelding bij deze link, als die klopt en iemand hiermee mag beoordelen.
	 *
	 * @return array|null
	 */
	public static function aanmelding_bij_link( $aanmelding_id, $sleutel ) {
		if ( ! hash_equals( self::sleutel( $aanmelding_id ), (string) $sleutel ) ) {
			return null;
		}
		$rij = self::aanmelding( $aanmelding_id );
		if ( ! $rij || HDP_Trainingen::STATUS_AANGEMELD !== $rij['status'] || empty( $rij['aanwezig'] ) ) {
			return null;
		}
		return HDP_Trainingen::is_voorbij( $rij['training_id'] ) ? $rij : null;
	}

	private static function aanmelding( $aanmelding_id ) {
		global $wpdb;
		$tabel = HDP_Trainingen::tabel();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- eigen tabel.
		$rij = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabel} WHERE id = %d", $aanmelding_id ), ARRAY_A );
		return $rij ? HDP_Trainingen::pak_uit( $rij ) : null;
	}

	/* === Opslaan ======================================================== */

	/** "Tom de Jong" wordt "Tom de J."; alleen een voornaam blijft een voornaam. */
	public static function korte_naam( $naam ) {
		$delen = preg_split( '/\s+/', trim( (string) $naam ) );
		if ( count( $delen ) < 2 ) {
			return $delen[0];
		}
		$laatste = array_pop( $delen );
		return implode( ' ', $delen ) . ' ' . mb_strtoupper( mb_substr( $laatste, 0, 1 ) ) . '.';
	}

	/** Wat er bij een reactie staat: de korte naam, met de functie als die is gevraagd. */
	public static function publieke_naam( $rij ) {
		$naam = self::korte_naam( $rij['deelnemer_naam'] );
		foreach ( (array) $rij['antwoorden'] as $antwoord ) {
			if ( ( 'functie' === $antwoord['id'] || false !== stripos( $antwoord['label'], 'functie' ) ) && '' !== trim( (string) $antwoord['waarde'] ) ) {
				return $naam . ', ' . mb_strtolower( trim( $antwoord['waarde'] ) );
			}
		}
		return $naam;
	}

	public static function heeft_beoordeeld( $aanmelding_id ) {
		global $wpdb;
		$tabel = self::tabel();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- eigen tabel.
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$tabel} WHERE aanmelding_id = %d", $aanmelding_id ) );
	}

	/**
	 * @param array $rij    De aanmelding.
	 * @param array $invoer Ongeschoond: 'cijfer', 'reactie', 'mag_tonen'.
	 * @return true|string True, of de tekstsleutel van de fout.
	 */
	public static function bewaar( $rij, $invoer ) {
		$cijfer = isset( $invoer['cijfer'] ) && is_scalar( $invoer['cijfer'] ) ? (int) $invoer['cijfer'] : 0;
		if ( $cijfer < 1 || $cijfer > 10 ) {
			return 'training_review_fout_cijfer';
		}
		// Dubbel versturen is geen fout: het cijfer staat er al.
		if ( self::heeft_beoordeeld( $rij['id'] ) ) {
			return true;
		}

		$reactie = isset( $invoer['reactie'] ) && is_scalar( $invoer['reactie'] ) ? trim( sanitize_textarea_field( (string) $invoer['reactie'] ) ) : '';
		$reactie = mb_substr( $reactie, 0, self::MAX_TEKENS );

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- eigen tabel.
		$gelukt = $wpdb->insert(
			self::tabel(),
			array(
				'aanmelding_id' => (int) $rij['id'],
				'training_id'   => (int) $rij['training_id'],
				'cijfer'        => $cijfer,
				'reactie'       => $reactie,
				'mag_tonen'     => empty( $invoer['mag_tonen'] ) ? 0 : 1,
				'zichtbaar'     => 0,
				'publieke_naam' => self::publieke_naam( $rij ),
				'taal'          => $rij['taal'],
				'aangemaakt'    => current_time( 'mysql', true ),
			)
		);
		if ( ! $gelukt ) {
			HDP_Log::schrijf( 'Beoordeling niet opgeslagen: ' . $wpdb->last_error, 'WAARSCHUWING' );
			return 'training_fout_opslaan';
		}
		if ( '' !== $reactie ) {
			self::mail_team( $rij, $cijfer, $reactie, ! empty( $invoer['mag_tonen'] ) );
		}
		return true;
	}

	/* === Cijfers en reacties ============================================ */

	/**
	 * @return array{aantal: int, gemiddelde: float, verdeling: array<string, int>}
	 */
	public static function stand( $training_id ) {
		global $wpdb;
		$tabel = self::tabel();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- eigen tabel.
		$cijfers   = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT cijfer FROM {$tabel} WHERE training_id = %d", $training_id ) ) );
		$verdeling = array(
			'10'    => 0,
			'9'     => 0,
			'8'     => 0,
			'7'     => 0,
			'lager' => 0,
		);
		foreach ( $cijfers as $cijfer ) {
			$verdeling[ $cijfer >= 7 ? (string) $cijfer : 'lager' ]++;
		}
		return array(
			'aantal'     => count( $cijfers ),
			'gemiddelde' => $cijfers ? array_sum( $cijfers ) / count( $cijfers ) : 0.0,
			'verdeling'  => $verdeling,
		);
	}

	public static function cijfer_tekst( $gemiddelde ) {
		return number_format( (float) $gemiddelde, 1, ',', '' );
	}

	/** De reacties die de deelnemer én Homburg goed vinden, nieuwste eerst. */
	public static function zichtbare_reacties( $training_id ) {
		global $wpdb;
		$tabel = self::tabel();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- eigen tabel.
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tabel} WHERE training_id = %d AND zichtbaar = 1 AND mag_tonen = 1 AND reactie <> '' ORDER BY aangemaakt DESC, id DESC LIMIT %d", $training_id, self::MAX_REACTIES ), ARRAY_A );
	}

	/** De eerdere training waarvan deze het vervolg is; 0 als die er niet (meer) is. */
	public static function vorige_training( $training_id ) {
		$vorige = (int) get_post_meta( $training_id, HDP_Trainingen::META_VERVOLG_OP, true );
		if ( ! $vorige || HDP_Trainingen::POST_TYPE !== get_post_type( $vorige ) || 'trash' === get_post_status( $vorige ) ) {
			return 0;
		}
		return $vorige;
	}

	/* === Mail =========================================================== */

	/**
	 * De uitnodigingen en herinneringen van vandaag.
	 *
	 * @return int Hoeveel mails er zijn verstuurd.
	 */
	public static function verstuur_ronde() {
		global $wpdb;
		$aanmeldingen = HDP_Trainingen::tabel();
		$reviews      = self::tabel();
		$vandaag      = strtotime( current_time( 'Y-m-d' ) . ' UTC' );
		$teller       = 0;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- eigen tabellen.
		$rijen = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.* FROM {$aanmeldingen} a LEFT JOIN {$reviews} r ON r.aanmelding_id = a.id
				WHERE a.status = %s AND a.aanwezig = 1 AND a.review_herinnering IS NULL AND r.id IS NULL
				ORDER BY a.id ASC",
				HDP_Trainingen::STATUS_AANGEMELD
			),
			ARRAY_A
		);
		// phpcs:enable

		foreach ( $rijen as $rij ) {
			if ( $teller >= self::MAX_PER_RONDE ) {
				break;
			}
			$soort = self::wat_nu( $rij, $vandaag );
			if ( $soort && self::verstuur( $rij, $soort ) ) {
				++$teller;
			}
		}
		return $teller;
	}

	/**
	 * @return string 'uitnodiging', 'herinnering' of leeg.
	 */
	private static function wat_nu( $rij, $vandaag ) {
		$id = (int) $rij['training_id'];
		if ( 'publish' !== get_post_status( $id ) ) {
			return '';
		}
		if ( ! $rij['review_uitnodiging'] ) {
			$eind = (string) get_post_meta( $id, HDP_Trainingen::META_EINDDATUM, true );
			$eind = $eind ? $eind : (string) get_post_meta( $id, HDP_Trainingen::META_DATUM, true );
			if ( ! $eind ) {
				return '';
			}
			$dagen = (int) floor( ( $vandaag - strtotime( $eind . ' UTC' ) ) / DAY_IN_SECONDS );
			return $dagen >= 1 && $dagen <= self::VENSTER_DAGEN ? 'uitnodiging' : '';
		}
		$verstuurd = strtotime( $rij['review_uitnodiging'] . ' UTC' );
		return time() - $verstuurd >= self::HERINNERING_DAGEN * DAY_IN_SECONDS ? 'herinnering' : '';
	}

	/** Eén mail, in de taal waarin de deelnemer zich aanmeldde. */
	public static function verstuur( $rij, $soort ) {
		$taal  = 'fr' === $rij['taal'] ? 'fr' : 'nl';
		$id    = (int) $rij['training_id'];
		$titel = self::titel_in( $id, $taal );

		$regels = array(
			sprintf( HDP_I18N::t_in( 'training_mail_aanhef', $taal ), $rij['deelnemer_naam'] ),
			'',
			sprintf( HDP_I18N::t_in( 'herinnering' === $soort ? 'training_review_mail_herinnering' : 'training_review_mail_vraag', $taal ), $titel ),
			'',
			self::link( $rij['id'] ),
			'',
			HDP_I18N::t_in( 'training_review_mail_uitleg', $taal ),
			'',
			HDP_I18N::t_in( 'training_mail_groet', $taal ),
		);
		$gelukt = wp_mail(
			$rij['deelnemer_email'],
			sprintf( HDP_I18N::t_in( 'training_review_mail_onderwerp', $taal ), $titel ),
			implode( "\n", $regels )
		);
		if ( ! $gelukt ) {
			HDP_Log::schrijf( 'Uitnodiging beoordeling niet verstuurd (aanmelding ' . $rij['id'] . ').', 'WAARSCHUWING' );
			return false;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- eigen tabel.
		$wpdb->update(
			HDP_Trainingen::tabel(),
			array( 'herinnering' === $soort ? 'review_herinnering' : 'review_uitnodiging' => current_time( 'mysql', true ) ),
			array( 'id' => (int) $rij['id'] )
		);
		return true;
	}

	private static function titel_in( $training_id, $taal ) {
		$fr = 'fr' === $taal ? trim( (string) get_post_meta( $training_id, HDP_Trainingen::META_TITEL_FR, true ) ) : '';
		return '' !== $fr ? $fr : html_entity_decode( (string) get_post_field( 'post_title', $training_id ), ENT_QUOTES, 'UTF-8' );
	}

	/** Een reactie wacht op goedkeuring: altijd in het Nederlands, zoals alle teammail. */
	private static function mail_team( $rij, $cijfer, $reactie, $mag_tonen ) {
		$aan = HDP_Trainingen::team_email();
		if ( ! $aan ) {
			return;
		}
		$regels = array(
			'Nieuwe reactie op de training "' . self::titel_in( $rij['training_id'], 'nl' ) . '".',
			'',
			'Cijfer: ' . $cijfer,
			'Van: ' . $rij['deelnemer_naam'],
			'',
			$reactie,
			'',
			$mag_tonen ? 'Deze reactie mag bij vervolgtrainingen staan, maar is pas zichtbaar als jullie die goedkeuren:' : 'De deelnemer wil niet dat deze reactie bij vervolgtrainingen staat. Bekijken:',
			self::beheer_url( $rij['training_id'] ),
		);
		wp_mail(
			$aan,
			'Nieuwe reactie op een training: ' . self::titel_in( $rij['training_id'], 'nl' ),
			implode( "\n", $regels ),
			array( 'Reply-To: ' . $rij['deelnemer_email'] )
		);
	}

	/* === De pagina met de link ========================================== */

	public static function is_beoordeel_verzoek() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- de sleutel in de link is de controle.
		return ! empty( $_GET['beoordeel'] );
	}

	/** Vóór er iets is uitgestuurd. De sleutel uit de mail is hier de beveiliging, er is immers geen login. */
	public static function verwerk() {
		// phpcs:disable WordPress.Security.NonceVerification -- zie hierboven.
		if ( self::is_beoordeel_verzoek() ) {
			nocache_headers();
		}
		if ( empty( $_POST['hdp_review_aanmelding'] ) ) {
			return;
		}
		$id      = absint( $_POST['hdp_review_aanmelding'] );
		$sleutel = isset( $_POST['sleutel'] ) ? sanitize_text_field( wp_unslash( $_POST['sleutel'] ) ) : '';
		$rij     = self::aanmelding_bij_link( $id, $sleutel );
		if ( ! $rij ) {
			return;
		}
		$uitkomst = self::bewaar( $rij, wp_unslash( $_POST ) );
		// phpcs:enable
		if ( true !== $uitkomst ) {
			self::$fout = $uitkomst;
			return;
		}
		wp_safe_redirect( add_query_arg( 'bedankt', 1, self::link( $id ) ) );
		exit;
	}

	public static function render_beoordeel() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- alleen lezen; de sleutel is de controle.
		$id      = isset( $_GET['beoordeel'] ) ? absint( $_GET['beoordeel'] ) : 0;
		$sleutel = isset( $_GET['sleutel'] ) ? sanitize_text_field( wp_unslash( $_GET['sleutel'] ) ) : '';
		// phpcs:enable
		$rij  = self::aanmelding_bij_link( $id, $sleutel );
		$taal = $rij && 'fr' === $rij['taal'] ? 'fr' : HDP_I18N::huidige_taal();

		ob_start();
		echo '<div class="hdp-portaal alignfull"><section class="hdp-trainingen hdp-training-beoordeel">';
		if ( ! $rij ) {
			echo '<p class="hdp-training-melding hdp-training-melding-fout">' . esc_html( HDP_I18N::t_in( 'training_review_ongeldig', $taal ) ) . '</p>';
		} elseif ( self::heeft_beoordeeld( $rij['id'] ) ) {
			echo '<p class="hdp-training-melding hdp-training-melding-goed" role="status"><strong>' . esc_html( HDP_I18N::t_in( 'training_review_bedankt', $taal ) ) . '</strong> ' . esc_html( HDP_I18N::t_in( 'training_review_bedankt_uitleg', $taal ) ) . '</p>';
		} else {
			self::render_formulier( $rij, $sleutel, $taal );
		}
		echo '</section></div>';
		return ob_get_clean();
	}

	private static function render_formulier( $rij, $sleutel, $taal ) {
		$id = (int) $rij['training_id'];
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- alleen om het formulier ingevuld terug te tonen.
		$cijfer    = isset( $_POST['cijfer'] ) ? (int) $_POST['cijfer'] : 0;
		$reactie   = isset( $_POST['reactie'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reactie'] ) ) : '';
		$mag_tonen = empty( $_POST['hdp_review_aanmelding'] ) || ! empty( $_POST['mag_tonen'] );
		// phpcs:enable
		?>
		<form class="hdp-garantie-formulier hdp-training-review-formulier" method="post" action="<?php echo esc_url( self::link( $rij['id'] ) ); ?>">
			<input type="hidden" name="hdp_review_aanmelding" value="<?php echo esc_attr( $rij['id'] ); ?>">
			<input type="hidden" name="sleutel" value="<?php echo esc_attr( $sleutel ); ?>">
			<p class="hdp-training-soort"><?php echo esc_html( HDP_I18N::t_in( 'training_review_kop', $taal ) ); ?></p>
			<h1><?php echo esc_html( self::titel_in( $id, $taal ) ); ?></h1>
			<?php if ( self::$fout ) : ?>
				<p class="hdp-training-melding hdp-training-melding-fout" role="alert"><?php echo esc_html( HDP_I18N::t_in( self::$fout, $taal ) ); ?></p>
			<?php endif; ?>
			<fieldset class="hdp-training-cijfers">
				<legend><?php echo esc_html( HDP_I18N::t_in( 'training_review_vraag', $taal ) ); ?></legend>
				<div>
					<?php for ( $i = 1; $i <= 10; $i++ ) : ?>
						<label>
							<input type="radio" name="cijfer" value="<?php echo esc_attr( $i ); ?>" <?php checked( $cijfer, $i ); ?> required>
							<span><?php echo esc_html( $i ); ?></span>
						</label>
					<?php endfor; ?>
				</div>
				<p class="hdp-training-schaal" aria-hidden="true"><span><?php echo esc_html( HDP_I18N::t_in( 'training_review_slecht', $taal ) ); ?></span><span><?php echo esc_html( HDP_I18N::t_in( 'training_review_uitstekend', $taal ) ); ?></span></p>
			</fieldset>
			<div class="hdp-veld hdp-veld-breed">
				<label for="hdp-review-reactie"><?php echo esc_html( HDP_I18N::t_in( 'training_review_reactie', $taal ) ); ?></label>
				<textarea id="hdp-review-reactie" name="reactie" rows="4" maxlength="<?php echo esc_attr( self::MAX_TEKENS ); ?>"><?php echo esc_textarea( $reactie ); ?></textarea>
			</div>
			<label class="hdp-training-vinkje">
				<input type="checkbox" name="mag_tonen" value="1" <?php checked( $mag_tonen ); ?>>
				<span><?php echo esc_html( HDP_I18N::t_in( 'training_review_mag_tonen', $taal ) ); ?></span>
			</label>
			<p class="hdp-garantie-verzenden">
				<button type="submit" class="hdp-btn"><?php echo esc_html( HDP_I18N::t_in( 'training_review_knop', $taal ) ); ?></button>
			</p>
		</form>
		<?php
	}

	/* === Bij de vervolgtraining ========================================= */

	/** Het gemiddelde in het donkere paneel. Niets zolang er te weinig cijfers zijn. */
	public static function render_oordeel_paneel( $training_id ) {
		$vorige = self::vorige_training( $training_id );
		$stand  = $vorige ? self::stand( $vorige ) : null;
		if ( ! $stand || $stand['aantal'] < self::MIN_CIJFERS ) {
			return;
		}
		?>
		<div class="hdp-training-oordeel">
			<b><?php echo esc_html( self::cijfer_tekst( $stand['gemiddelde'] ) ); ?></b>
			<span><?php echo esc_html( sprintf( HDP_I18N::t( 'training_review_vorige' ), $stand['aantal'] ) ); ?></span>
		</div>
		<?php
	}

	public static function render_vervolg_label( $training_id ) {
		$vorige = self::vorige_training( $training_id );
		if ( $vorige ) {
			echo '<p class="hdp-training-vervolg">' . esc_html( sprintf( HDP_I18N::t( 'training_review_vervolg_op' ), HDP_Trainingen::titel( $vorige ) ) ) . '</p>';
		}
	}

	/** Het blok onder de uitleg: het gemiddelde, de verdeling en de reacties. */
	public static function render_sectie( $training_id ) {
		$vorige = self::vorige_training( $training_id );
		$stand  = $vorige ? self::stand( $vorige ) : null;
		if ( ! $stand || $stand['aantal'] < self::MIN_CIJFERS ) {
			return;
		}
		$grootste = max( 1, max( $stand['verdeling'] ) );
		$reacties = self::zichtbare_reacties( $vorige );
		?>
		<section class="hdp-training-reviews">
			<h2><?php echo esc_html( sprintf( HDP_I18N::t( 'training_review_sectie' ), HDP_Trainingen::titel( $vorige ) ) ); ?></h2>
			<p class="hdp-training-reviews-uitleg"><?php echo esc_html( HDP_I18N::t( 'training_review_sectie_uitleg' ) ); ?></p>
			<div class="hdp-training-reviews-raster<?php echo $reacties ? '' : ' is-zonder-reacties'; ?>">
				<div>
					<p class="hdp-training-reviews-cijfer"><b><?php echo esc_html( self::cijfer_tekst( $stand['gemiddelde'] ) ); ?></b> <span><?php echo esc_html( sprintf( HDP_I18N::t( 'training_review_aantal' ), $stand['aantal'] ) ); ?></span></p>
					<ul class="hdp-training-verdeling">
						<?php foreach ( $stand['verdeling'] as $cijfer => $aantal ) : ?>
							<li>
								<span><?php echo esc_html( 'lager' === $cijfer ? HDP_I18N::t( 'training_review_lager' ) : $cijfer ); ?></span>
								<i><u style="width:<?php echo (int) round( $aantal / $grootste * 100 ); ?>%"></u></i>
								<em><?php echo (int) $aantal; ?></em>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
				<?php if ( $reacties ) : ?>
					<ul class="hdp-training-reacties">
						<?php foreach ( $reacties as $reactie ) : ?>
							<li>
								<b><?php echo (int) $reactie['cijfer']; ?></b>
								<div>
									<p><?php echo esc_html( $reactie['reactie'] ); ?></p>
									<small><?php echo esc_html( $reactie['publieke_naam'] ); ?></small>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}

	/* === Beheer ========================================================= */

	public static function beheer_url( $training_id = 0 ) {
		$url = admin_url( 'edit.php?post_type=' . HDP_Trainingen::POST_TYPE . '&page=hdp-training-beoordelingen' );
		return $training_id ? add_query_arg( 'training', (int) $training_id, $url ) : $url;
	}

	public static function registreer_menu() {
		add_submenu_page(
			'edit.php?post_type=' . HDP_Trainingen::POST_TYPE,
			'Beoordelingen',
			'Beoordelingen',
			HDP_Trainingen::CAP_BEHEER,
			'hdp-training-beoordelingen',
			array( __CLASS__, 'render_beheer' )
		);
	}

	public static function render_beheer() {
		if ( ! current_user_can( HDP_Trainingen::CAP_BEHEER ) ) {
			return;
		}
		global $wpdb;
		$tabel = self::tabel();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- alleen een keuze uit de URL.
		$training_id = isset( $_GET['training'] ) ? absint( $_GET['training'] ) : 0;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- eigen tabel.
		$met_cijfers = array_map( 'intval', $wpdb->get_col( "SELECT DISTINCT training_id FROM {$tabel}" ) );
		$trainingen  = get_posts(
			array(
				'post_type'      => HDP_Trainingen::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'private', 'trash' ),
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		if ( ! $training_id ) {
			foreach ( $trainingen as $training ) {
				if ( in_array( (int) $training->ID, $met_cijfers, true ) ) {
					$training_id = (int) $training->ID;
					break;
				}
			}
		}
		?>
		<div class="wrap">
			<h1>Beoordelingen van trainingen</h1>
			<?php if ( ! $trainingen ) : ?>
				<p>Er zijn nog geen trainingen geplaatst.</p>
			</div>
				<?php
				return;
			endif;
			?>
			<form method="get" style="margin:1em 0">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( HDP_Trainingen::POST_TYPE ); ?>">
				<input type="hidden" name="page" value="hdp-training-beoordelingen">
				<label for="hdp-review-keuze"><strong>Training:</strong></label>
				<select name="training" id="hdp-review-keuze" onchange="this.form.submit()">
					<option value="0">Kies een training</option>
					<?php foreach ( $trainingen as $training ) : ?>
						<option value="<?php echo esc_attr( $training->ID ); ?>" <?php selected( $training_id, $training->ID ); ?>><?php echo esc_html( html_entity_decode( $training->post_title, ENT_QUOTES, 'UTF-8' ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</form>
			<?php
			if ( ! $training_id ) {
				echo '<p>Nog geen beoordelingen ontvangen.</p></div>';
				return;
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- eigen tabel.
			$rijen = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$tabel} WHERE training_id = %d ORDER BY aangemaakt DESC, id DESC", $training_id ), ARRAY_A );
			if ( ! $rijen ) {
				echo '<p>Nog geen beoordelingen ontvangen voor deze training.</p></div>';
				return;
			}
			$stand = self::stand( $training_id );
			$van   = count(
				array_filter(
					HDP_Trainingen::aanmeldingen( $training_id, true ),
					static function ( $rij ) {
						return ! empty( $rij['aanwezig'] );
					}
				)
			);
			?>
			<p><strong>Gemiddeld <?php echo esc_html( self::cijfer_tekst( $stand['gemiddelde'] ) ); ?></strong>, <?php echo esc_html( $stand['aantal'] . ' van ' . max( $van, $stand['aantal'] ) ); ?> deelnemers gaven een cijfer. Een cijfer telt altijd mee; hieronder bepaal je welke reacties bij een vervolgtraining staan.</p>
			<table class="widefat striped">
				<thead><tr><th style="width:60px">Cijfer</th><th>Reactie</th><th style="width:190px">Staat erbij als</th><th style="width:110px">Ontvangen</th><th style="width:150px">Zichtbaar</th></tr></thead>
				<tbody>
					<?php foreach ( $rijen as $rij ) : ?>
						<tr>
							<td><strong><?php echo (int) $rij['cijfer']; ?></strong></td>
							<td><?php echo '' !== $rij['reactie'] ? nl2br( esc_html( $rij['reactie'] ) ) : '<span class="description">Alleen een cijfer</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- geëscapet. ?></td>
							<td><?php echo esc_html( $rij['publieke_naam'] ); ?></td>
							<td><?php echo esc_html( get_date_from_gmt( $rij['aangemaakt'], 'd-m-Y' ) ); ?></td>
							<td>
								<?php if ( '' === $rij['reactie'] ) : ?>
									<span class="description">Niets te tonen</span>
								<?php elseif ( ! $rij['mag_tonen'] ) : ?>
									<span class="description">Deelnemer wil dit niet</span>
								<?php else : ?>
									<?php
									$aan = (int) $rij['zichtbaar'];
									printf(
										'<a class="button %1$s" href="%2$s">%3$s</a>',
										$aan ? '' : 'button-primary',
										esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hdp_training_review_zichtbaar&review=' . (int) $rij['id'] . '&training=' . $training_id . '&aan=' . ( $aan ? 0 : 1 ) ), 'hdp_training_review_' . (int) $rij['id'] ) ),
										$aan ? 'Verbergen' : 'Goedkeuren'
									);
									?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function verwerk_zichtbaar() {
		$id = isset( $_GET['review'] ) ? absint( $_GET['review'] ) : 0;
		check_admin_referer( 'hdp_training_review_' . $id );
		if ( ! current_user_can( HDP_Trainingen::CAP_BEHEER ) ) {
			wp_die( 'Geen toegang.' );
		}
		self::zet_zichtbaar( $id, ! empty( $_GET['aan'] ) );
		wp_safe_redirect( self::beheer_url( isset( $_GET['training'] ) ? absint( $_GET['training'] ) : 0 ) );
		exit;
	}

	public static function zet_zichtbaar( $review_id, $zichtbaar ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- eigen tabel.
		return (bool) $wpdb->update( self::tabel(), array( 'zichtbaar' => $zichtbaar ? 1 : 0 ), array( 'id' => (int) $review_id ) );
	}

	public static function verwerk_aanwezig() {
		$id = isset( $_GET['aanmelding'] ) ? absint( $_GET['aanmelding'] ) : 0;
		check_admin_referer( 'hdp_training_aanwezig_' . $id );
		if ( ! current_user_can( HDP_Trainingen::CAP_BEHEER ) ) {
			wp_die( 'Geen toegang.' );
		}
		self::zet_aanwezig( $id, ! empty( $_GET['aan'] ) );
		wp_safe_redirect( HDP_Trainingen::beheer_url( isset( $_GET['training'] ) ? absint( $_GET['training'] ) : 0 ) );
		exit;
	}

	/** Wie niet was, krijgt geen vraag om een cijfer en zijn link werkt niet. */
	public static function zet_aanwezig( $aanmelding_id, $aanwezig ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- eigen tabel.
		return (bool) $wpdb->update( HDP_Trainingen::tabel(), array( 'aanwezig' => $aanwezig ? 1 : 0 ), array( 'id' => (int) $aanmelding_id ) );
	}
}
