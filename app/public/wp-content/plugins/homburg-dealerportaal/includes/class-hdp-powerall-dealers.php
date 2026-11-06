<?php
/**
 * Dealers uit PowerAll: elke klant die Homburg aanwijst, krijgt een
 * dealeraccount in het portaal, gekoppeld aan zijn klantnummer. De dealer
 * krijgt een uitnodiging, kiest zelf een wachtwoord en kan daarna inloggen
 * met zijn e-mailadres.
 *
 * Voorzichtig opgezet, omdat PowerAll de echte administratie is:
 * - Alleen de klantnummers die in Gebruikers > Dealers uit PowerAll staan,
 *   worden opgehaald. Is die lijst leeg, dan gebeurt er niets.
 * - Uitnodigingen gaan alleen automatisch als dat daar is aangezet. Anders
 *   stuurt Homburg ze per dealer met de hand.
 * - Een e-mailadres dat al bij een beheerder of ander niet-dealeraccount
 *   hoort, wordt nooit overgenomen.
 * - Uit PowerAll wordt alleen gelezen.
 *
 * @package Homburg_Dealerportaal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HDP_PowerAll_Dealers {

	const META_RELATIE     = 'hdp_powerall_relatie';
	const META_STATUS      = 'hdp_powerall_status';
	const META_BIJGEWERKT  = 'hdp_powerall_bijgewerkt';
	const META_UITNODIGING = 'hdp_uitnodiging';
	const META_WACHTWOORD  = 'hdp_wachtwoord_ingesteld';

	const OPTION       = 'hdp_powerall';
	const HOOK         = 'hdp_powerall_dealers_ophalen';
	const PAGINA       = 'hdp-powerall';
	const GELDIG_DAGEN = 14;
	const PER_VRAAG    = 200;
	const MIN_TEKENS   = 8;

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'ophalen' ) );
		add_action( 'admin_init', array( __CLASS__, 'plan' ) );
		add_action( 'init', array( __CLASS__, 'verwerk_wachtwoord' ) );
		add_action( 'admin_menu', array( __CLASS__, 'registreer_menu' ) );
		add_action( 'admin_post_hdp_powerall_opslaan', array( __CLASS__, 'verwerk_opslaan' ) );
		add_action( 'admin_post_hdp_powerall_ophalen', array( __CLASS__, 'verwerk_ophalen' ) );
		add_action( 'admin_post_hdp_powerall_uitnodigen', array( __CLASS__, 'verwerk_uitnodigen' ) );
	}

	/** Eén keer per dag ophalen, maar alleen als er iets op te halen valt. */
	public static function plan() {
		$nodig = HDP_PowerAll::beschikbaar() && self::klantnummers();
		$gepland = wp_next_scheduled( self::HOOK );
		if ( $nodig && ! $gepland ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK );
		} elseif ( ! $nodig && $gepland ) {
			wp_clear_scheduled_hook( self::HOOK );
		}
	}

	/* ---------------------------------------------------------------------
	 * Instellingen
	 * ------------------------------------------------------------------- */

	private static function instellingen() {
		$waarde = get_option( self::OPTION, array() );
		return is_array( $waarde ) ? $waarde : array();
	}

	private static function zet_instelling( $sleutel, $waarde ) {
		$alles             = self::instellingen();
		$alles[ $sleutel ] = $waarde;
		update_option( self::OPTION, $alles, false );
	}

	/** @return int[] De klantnummers die opgehaald mogen worden. */
	public static function klantnummers() {
		$tekst = (string) ( self::instellingen()['klantnummers'] ?? '' );
		preg_match_all( '/\d+/', $tekst, $treffers );
		return array_values( array_unique( array_map( 'intval', $treffers[0] ) ) );
	}

	public static function auto_uitnodigen() {
		return '1' === (string) ( self::instellingen()['auto_uitnodigen'] ?? '' );
	}

	/* ---------------------------------------------------------------------
	 * Ophalen
	 * ------------------------------------------------------------------- */

	/**
	 * Haalt de ingestelde klanten op en maakt of werkt hun accounts bij.
	 *
	 * @return array Verslag: 'tijd', 'fout' en per klant 'code', 'naam', 'uitkomst'.
	 */
	public static function ophalen() {
		$verslag = array(
			'tijd'   => time(),
			'fout'   => '',
			'regels' => array(),
		);
		$codes   = self::klantnummers();

		if ( ! HDP_PowerAll::beschikbaar() ) {
			$verslag['fout'] = 'De toegang tot PowerAll is op deze server niet ingesteld.';
		} elseif ( ! $codes ) {
			$verslag['fout'] = 'Er zijn nog geen klantnummers ingesteld.';
		} else {
			$gevonden = array();
			foreach ( array_chunk( $codes, self::PER_VRAAG ) as $groep ) {
				$relaties = HDP_PowerAll::get(
					'relations',
					array(
						'filters' => 'RelationCode$[' . implode( ',', array_map( static function ( $c ) { return '"' . $c . '"'; }, $groep ) ) . ']',
						'limit'   => self::PER_VRAAG,
					)
				);
				if ( is_wp_error( $relaties ) ) {
					$verslag['fout'] = $relaties->get_error_message();
					break;
				}
				foreach ( (array) $relaties as $relatie ) {
					if ( ! is_array( $relatie ) || ! isset( $relatie['RelationCode'] ) || ! in_array( (int) $relatie['RelationCode'], $groep, true ) ) {
						continue;
					}
					$gevonden[]          = (int) $relatie['RelationCode'];
					$verslag['regels'][] = self::verwerk_relatie( $relatie );
				}
			}
			if ( '' === $verslag['fout'] ) {
				foreach ( array_diff( $codes, $gevonden ) as $code ) {
					$verslag['regels'][] = array(
						'code'     => $code,
						'naam'     => '',
						'uitkomst' => 'Niet gevonden in PowerAll.',
					);
				}
			}
		}

		self::zet_instelling( 'verslag', $verslag );
		return $verslag;
	}

	/**
	 * Maakt of werkt het account van één klant bij.
	 *
	 * @param array $relatie Een relatie zoals PowerAll die teruggeeft.
	 * @return array 'code', 'naam', 'uitkomst'.
	 */
	public static function verwerk_relatie( array $relatie ) {
		$code   = (int) $relatie['RelationCode'];
		$naam   = self::schoon( $relatie['Name1'] ?? '' );
		$naam   = '' !== $naam ? $naam : 'Klant ' . $code;
		$email  = sanitize_email( self::schoon( $relatie['EmailAddress'] ?? '' ) );
		$status = self::status_uit( $relatie );
		$regel  = array(
			'code' => $code,
			'naam' => $naam,
		);

		$gebruiker = self::gebruiker_bij_klantnummer( $code );
		$nieuw     = false;

		if ( ! $gebruiker ) {
			if ( ! is_email( $email ) ) {
				return $regel + array( 'uitkomst' => 'Overgeslagen: in PowerAll staat geen geldig e-mailadres.' );
			}

			$bestaand = get_user_by( 'email', $email );
			if ( $bestaand ) {
				if ( ! self::is_gewone_dealer( $bestaand ) ) {
					return $regel + array( 'uitkomst' => 'Overgeslagen: ' . $email . ' hoort al bij een account dat geen dealer is, zoals een beheerder.' );
				}
				$ander = (int) get_user_meta( $bestaand->ID, self::META_RELATIE, true );
				if ( $ander && $ander !== $code ) {
					return $regel + array( 'uitkomst' => 'Overgeslagen: het account van ' . $email . ' is al gekoppeld aan klantnummer ' . $ander . '.' );
				}
				update_user_meta( $bestaand->ID, self::META_RELATIE, $code );
				$gebruiker = $bestaand;
				$uitkomst  = 'Gekoppeld aan het bestaande account van ' . $email . '.';
			} else {
				$id = wp_insert_user(
					array(
						'user_login'   => self::vrije_login( $code ),
						'user_pass'    => wp_generate_password( 32, true, true ),
						'user_email'   => $email,
						'display_name' => $naam,
						'nickname'     => $naam,
						'role'         => HDP_Roles::ROLE,
					)
				);
				if ( is_wp_error( $id ) ) {
					return $regel + array( 'uitkomst' => 'Account niet aangemaakt: ' . $id->get_error_message() );
				}
				update_user_meta( $id, 'hdp_goedgekeurd', '1' );
				update_user_meta( $id, self::META_RELATIE, $code );
				$gebruiker = get_userdata( $id );
				$nieuw     = true;
				$uitkomst  = 'Account aangemaakt voor ' . $email . '.';
			}
		} else {
			$uitkomst = 'Bijgewerkt.';
			if ( is_email( $email ) && strtolower( $email ) !== strtolower( $gebruiker->user_email ) ) {
				$uitkomst .= ' Let op: in PowerAll staat ' . $email . ', in het portaal ' . $gebruiker->user_email . '.';
			}
		}

		self::werk_gegevens_bij( $gebruiker->ID, $relatie, $naam, $status );

		if ( 'actief' !== $status ) {
			$uitkomst .= ' In PowerAll ' . ( 'geblokkeerd' === $status ? 'geblokkeerd voor orders' : 'niet actief' ) . '.';
		}

		if ( $nieuw && 'actief' === $status && self::auto_uitnodigen() ) {
			$uitkomst .= self::nodig_uit( $gebruiker ) ? ' Uitnodiging verstuurd.' : ' Uitnodiging NIET verstuurd.';
		}

		return $regel + array( 'uitkomst' => $uitkomst );
	}

	/** actief, geblokkeerd (geen orders) of inactief. */
	private static function status_uit( array $relatie ) {
		if ( empty( $relatie['Active'] ) ) {
			return 'inactief';
		}
		return 'Block' === ( $relatie['AcceptOrdersForDebtor'] ?? '' ) ? 'geblokkeerd' : 'actief';
	}

	public static function status( $user_id ) {
		return (string) get_user_meta( $user_id, self::META_STATUS, true );
	}

	private static function werk_gegevens_bij( $user_id, array $relatie, $naam, $status ) {
		update_user_meta( $user_id, self::META_STATUS, $status );
		update_user_meta( $user_id, self::META_BIJGEWERKT, time() );

		$velden = array(
			'billing_company'   => $naam,
			'billing_address_1' => self::schoon( $relatie['AddressLine'] ?? '' ),
			'billing_postcode'  => self::schoon( $relatie['ZipCode'] ?? '' ),
			'billing_city'      => self::schoon( $relatie['Town'] ?? '' ),
			'billing_phone'     => self::schoon( $relatie['Phone'] ?? '' ),
		);
		foreach ( $velden as $sleutel => $waarde ) {
			if ( '' !== $waarde ) {
				update_user_meta( $user_id, $sleutel, $waarde );
			}
		}
	}

	/** Leeg voor opvullers als "." of "..", die in PowerAll voor "niet bekend" staan. */
	private static function schoon( $waarde ) {
		$waarde = trim( sanitize_text_field( (string) $waarde ) );
		return '' === trim( $waarde, '.- ' ) ? '' : $waarde;
	}

	public static function gebruiker_bij_klantnummer( $code ) {
		$gevonden = get_users(
			array(
				'meta_key'   => self::META_RELATIE, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => (string) (int) $code, // phpcs:ignore WordPress.DB.SlowDBQuery
				'number'     => 1,
			)
		);
		return $gevonden ? $gevonden[0] : null;
	}

	/** Alleen een account dat puur dealer is, mag aan een klant gekoppeld worden. */
	private static function is_gewone_dealer( WP_User $gebruiker ) {
		return in_array( HDP_Roles::ROLE, (array) $gebruiker->roles, true )
			&& ! user_can( $gebruiker, 'manage_options' )
			&& ! user_can( $gebruiker, 'edit_posts' );
	}

	private static function vrije_login( $code ) {
		$basis = 'pa-' . (int) $code;
		$login = $basis;
		for ( $i = 2; username_exists( $login ); $i++ ) {
			$login = $basis . '-' . $i;
		}
		return $login;
	}

	/* ---------------------------------------------------------------------
	 * Uitnodiging en wachtwoord kiezen
	 * ------------------------------------------------------------------- */

	/** Stuurt de uitnodiging met een nieuwe link; een oudere link vervalt daarmee. */
	public static function nodig_uit( WP_User $gebruiker ) {
		$sleutel = wp_generate_password( 32, false );
		update_user_meta(
			$gebruiker->ID,
			self::META_UITNODIGING,
			array(
				'hash'      => self::hash( $sleutel ),
				'tot'       => time() + self::GELDIG_DAGEN * DAY_IN_SECONDS,
				'verstuurd' => time(),
			)
		);

		$link    = add_query_arg(
			array(
				'hdp_welkom' => $gebruiker->ID,
				'sleutel'    => $sleutel,
			),
			home_url( '/dealerportaal/' )
		);
		$portaal = home_url( '/dealerportaal/' );

		$onderwerp = 'Je account voor het Homburg-dealerportaal / Votre compte pour le portail concessionnaire Homburg';
		$tekst     = sprintf(
			"Beste %1\$s,\n\nEr staat een account voor je klaar in het dealerportaal van Homburg. Daar bestel je onderdelen, volg je je garantieclaims en vind je documentatie en trainingen.\n\nKies via deze link je wachtwoord:\n%2\$s\n\nDe link werkt %3\$d dagen en één keer. Daarna log je in op %4\$s met je e-mailadres (%5\$s) en je wachtwoord.\n\nMet vriendelijke groet,\nHomburg Machinehandel\n\n---\n\nCher/Chère %1\$s,\n\nUn compte vous attend sur le portail concessionnaire Homburg. Vous pourrez y commander des pièces, suivre vos demandes de garantie et consulter la documentation et les formations.\n\nChoisissez votre mot de passe via ce lien :\n%2\$s\n\nLe lien est valable %3\$d jours et une seule fois. Ensuite, connectez-vous sur %4\$s avec votre adresse e-mail (%5\$s) et votre mot de passe.\n\nCordialement,\nHomburg Machinehandel",
			$gebruiker->display_name,
			$link,
			self::GELDIG_DAGEN,
			$portaal,
			$gebruiker->user_email
		);

		return (bool) wp_mail( $gebruiker->user_email, $onderwerp, $tekst );
	}

	private static function hash( $sleutel ) {
		return hash_hmac( 'sha256', (string) $sleutel, wp_salt( 'auth' ) );
	}

	/** Het account bij een geldige, ongebruikte en niet verlopen link; anders null. */
	public static function gebruiker_bij_link( $user_id, $sleutel ) {
		$gebruiker = get_userdata( (int) $user_id );
		$gegevens  = $gebruiker ? get_user_meta( $gebruiker->ID, self::META_UITNODIGING, true ) : null;
		if ( ! is_array( $gegevens ) || empty( $gegevens['hash'] ) || '' === (string) $sleutel ) {
			return null;
		}
		if ( (int) $gegevens['tot'] < time() || ! hash_equals( (string) $gegevens['hash'], self::hash( $sleutel ) ) ) {
			return null;
		}
		return $gebruiker;
	}

	/**
	 * Zet het wachtwoord via de link.
	 *
	 * @return true|string True, of de code van de fout: 'link', 'kort' of 'verschil'.
	 */
	public static function zet_wachtwoord( $user_id, $sleutel, $wachtwoord, $herhaling ) {
		$gebruiker = self::gebruiker_bij_link( $user_id, $sleutel );
		if ( ! $gebruiker ) {
			return 'link';
		}
		if ( strlen( $wachtwoord ) < self::MIN_TEKENS ) {
			return 'kort';
		}
		if ( $wachtwoord !== $herhaling ) {
			return 'verschil';
		}
		wp_set_password( $wachtwoord, $gebruiker->ID );
		delete_user_meta( $gebruiker->ID, self::META_UITNODIGING );
		update_user_meta( $gebruiker->ID, self::META_WACHTWOORD, time() );
		return true;
	}

	public static function verwerk_wachtwoord() {
		if ( ! isset( $_POST['hdp_welkom_nonce'], $_POST['hdp_welkom'] ) ) {
			return;
		}
		$user_id = absint( $_POST['hdp_welkom'] );
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hdp_welkom_nonce'] ) ), 'hdp_welkom_' . $user_id ) ) {
			return;
		}
		$sleutel = isset( $_POST['sleutel'] ) ? sanitize_text_field( wp_unslash( $_POST['sleutel'] ) ) : '';
		// Wachtwoorden niet opschonen: dat zou geldige tekens kunnen veranderen (zie HDP_Login).
		$wachtwoord = isset( $_POST['wachtwoord'] ) ? (string) wp_unslash( $_POST['wachtwoord'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$herhaling  = isset( $_POST['wachtwoord_herhaal'] ) ? (string) wp_unslash( $_POST['wachtwoord_herhaal'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$uitkomst = self::zet_wachtwoord( $user_id, $sleutel, $wachtwoord, $herhaling );
		if ( true === $uitkomst ) {
			wp_set_auth_cookie( $user_id, true, is_ssl() );
			wp_safe_redirect( home_url( '/dealerportaal/' ) );
			exit;
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'hdp_welkom'      => $user_id,
					'sleutel'         => rawurlencode( $sleutel ),
					'hdp_welkom_fout' => $uitkomst,
				),
				home_url( '/dealerportaal/' )
			)
		);
		exit;
	}

	/** Is dit een bezoek via de uitnodigingslink? */
	public static function is_welkom_verzoek() {
		return isset( $_GET['hdp_welkom'] ) && ! is_user_logged_in(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/** Het scherm waar de dealer zijn wachtwoord kiest, in de stijl van het inlogscherm. */
	public static function render_welkom( $a ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- alleen tonen; de sleutel zelf is de controle.
		$user_id = absint( $_GET['hdp_welkom'] ?? 0 );
		$sleutel = sanitize_text_field( wp_unslash( $_GET['sleutel'] ?? '' ) );
		$fout    = sanitize_key( wp_unslash( $_GET['hdp_welkom_fout'] ?? '' ) );
		// phpcs:enable

		$gebruiker = self::gebruiker_bij_link( $user_id, $sleutel );
		$meldingen = array(
			'kort'     => HDP_I18N::t( 'instellingen_fout_kort' ),
			'verschil' => HDP_I18N::t( 'instellingen_fout_mismatch' ),
		);

		add_filter(
			'body_class',
			static function ( $classes ) {
				$classes[] = 'hdp-login-actief';
				return $classes;
			}
		);
		?>
		<div class="hdp-login-sectie hdp-login-fullscreen alignfull" style="background-image:url('<?php echo esc_url( $a['heroAfbeelding'] ?? '' ); ?>')">
			<div class="hdp-login-kaart">
				<img class="hdp-login-logo" src="<?php echo esc_url( HDP_PLUGIN_URL . 'assets/img/Logo-HOMBURG_RGB-300x40.png' ); ?>" alt="Homburg">
				<?php if ( ! $gebruiker ) : ?>
					<h1><?php echo esc_html( HDP_I18N::t( 'welkom_titel' ) ); ?></h1>
					<div class="hdp-login-fout hdp-zichtbaar" role="alert"><?php echo esc_html( HDP_I18N::t( 'welkom_verlopen' ) ); ?></div>
					<p class="hdp-wachtwoord-vergeten"><a href="<?php echo esc_url( wp_lostpassword_url( home_url( '/dealerportaal/' ) ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'wachtwoord_vergeten' ) ); ?></a></p>
				<?php else : ?>
					<h1><?php echo esc_html( HDP_I18N::t( 'welkom_titel' ) ); ?></h1>
					<p class="hdp-intro"><?php echo esc_html( sprintf( HDP_I18N::t( 'welkom_intro' ), $gebruiker->user_email ) ); ?></p>
					<?php if ( isset( $meldingen[ $fout ] ) ) : ?>
						<div class="hdp-login-fout hdp-zichtbaar" role="alert"><?php echo esc_html( $meldingen[ $fout ] ); ?></div>
					<?php endif; ?>
					<form method="post">
						<?php wp_nonce_field( 'hdp_welkom_' . $gebruiker->ID, 'hdp_welkom_nonce' ); ?>
						<input type="hidden" name="hdp_welkom" value="<?php echo esc_attr( $gebruiker->ID ); ?>">
						<input type="hidden" name="sleutel" value="<?php echo esc_attr( $sleutel ); ?>">
						<input type="text" name="gebruikersnaam" value="<?php echo esc_attr( $gebruiker->user_email ); ?>" autocomplete="username" hidden>
						<div class="hdp-veld">
							<label for="hdp-welkom-wachtwoord"><?php echo esc_html( HDP_I18N::t( 'label_nieuw_wachtwoord' ) ); ?></label>
							<div class="hdp-veld-invoer">
								<?php echo HDP_Icons::svg_icoon( 'login' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?>
								<input type="password" id="hdp-welkom-wachtwoord" name="wachtwoord" autocomplete="new-password" minlength="<?php echo esc_attr( self::MIN_TEKENS ); ?>" required>
							</div>
						</div>
						<div class="hdp-veld">
							<label for="hdp-welkom-herhaal"><?php echo esc_html( HDP_I18N::t( 'label_nieuw_wachtwoord_herhaal' ) ); ?></label>
							<div class="hdp-veld-invoer">
								<?php echo HDP_Icons::svg_icoon( 'login' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?>
								<input type="password" id="hdp-welkom-herhaal" name="wachtwoord_herhaal" autocomplete="new-password" minlength="<?php echo esc_attr( self::MIN_TEKENS ); ?>" required>
							</div>
						</div>
						<button type="submit" class="hdp-btn"><?php echo esc_html( HDP_I18N::t( 'welkom_btn' ) ); ?></button>
					</form>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Beheer: Gebruikers > Dealers uit PowerAll
	 * ------------------------------------------------------------------- */

	public static function registreer_menu() {
		add_users_page( 'Dealers uit PowerAll', 'Dealers uit PowerAll', 'manage_options', self::PAGINA, array( __CLASS__, 'render_beheer' ) );
	}

	public static function beheer_url( $melding = '' ) {
		$url = admin_url( 'users.php?page=' . self::PAGINA );
		return $melding ? add_query_arg( 'hdp_melding', $melding, $url ) : $url;
	}

	private static function controleer_beheer( $actie ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Geen toegang.' );
		}
		check_admin_referer( $actie );
	}

	public static function verwerk_opslaan() {
		self::controleer_beheer( 'hdp_powerall_opslaan' );
		$klantnummers = isset( $_POST['klantnummers'] ) ? sanitize_textarea_field( wp_unslash( $_POST['klantnummers'] ) ) : '';
		self::zet_instelling( 'klantnummers', $klantnummers );
		self::zet_instelling( 'auto_uitnodigen', empty( $_POST['auto_uitnodigen'] ) ? '' : '1' );
		self::plan();
		wp_safe_redirect( self::beheer_url( 'opgeslagen' ) );
		exit;
	}

	public static function verwerk_ophalen() {
		self::controleer_beheer( 'hdp_powerall_ophalen' );
		$verslag = self::ophalen();
		wp_safe_redirect( self::beheer_url( '' === $verslag['fout'] ? 'opgehaald' : 'fout' ) );
		exit;
	}

	public static function verwerk_uitnodigen() {
		$user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		self::controleer_beheer( 'hdp_powerall_uitnodigen_' . $user_id );
		$gebruiker = get_userdata( $user_id );
		$gelukt    = $gebruiker && get_user_meta( $user_id, self::META_RELATIE, true ) && self::nodig_uit( $gebruiker );
		wp_safe_redirect( self::beheer_url( $gelukt ? 'uitgenodigd' : 'niet_uitgenodigd' ) );
		exit;
	}

	private static function datum( $tijd ) {
		return $tijd ? wp_date( 'j-n-Y H:i', (int) $tijd ) : '';
	}

	public static function render_beheer() {
		$instellingen = self::instellingen();
		$verslag      = isset( $instellingen['verslag'] ) && is_array( $instellingen['verslag'] ) ? $instellingen['verslag'] : null;
		$dealers      = get_users(
			array(
				'meta_key' => self::META_RELATIE, // phpcs:ignore WordPress.DB.SlowDBQuery
				'orderby'  => 'display_name',
			)
		);
		$meldingen    = array(
			'opgeslagen'       => array( 'success', 'Opgeslagen.' ),
			'opgehaald'        => array( 'success', 'Opgehaald uit PowerAll. Zie het verslag hieronder.' ),
			'fout'             => array( 'error', 'Ophalen is niet gelukt. Zie het verslag hieronder.' ),
			'uitgenodigd'      => array( 'success', 'De uitnodiging is verstuurd.' ),
			'niet_uitgenodigd' => array( 'error', 'De uitnodiging kon niet worden verstuurd.' ),
		);
		$melding      = isset( $_GET['hdp_melding'] ) ? sanitize_key( wp_unslash( $_GET['hdp_melding'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap">
			<h1>Dealers uit PowerAll</h1>
			<?php if ( isset( $meldingen[ $melding ] ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $meldingen[ $melding ][0] ); ?> is-dismissible"><p><?php echo esc_html( $meldingen[ $melding ][1] ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! HDP_PowerAll::beschikbaar() ) : ?>
				<div class="notice notice-warning"><p>De toegang tot PowerAll is op deze server nog niet ingesteld. Tot die tijd wordt er niets opgehaald.</p></div>
			<?php endif; ?>

			<p>Voor elke klant hieronder maakt het portaal een dealeraccount aan, gekoppeld aan zijn klantnummer in PowerAll. Er wordt één keer per dag opgehaald, en meteen als je op "Nu ophalen" klikt. Uit PowerAll wordt alleen gelezen.</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'hdp_powerall_opslaan' ); ?>
				<input type="hidden" name="action" value="hdp_powerall_opslaan">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="hdp-pa-klantnummers">Klantnummers</label></th>
						<td>
							<textarea id="hdp-pa-klantnummers" name="klantnummers" rows="3" class="large-text code"><?php echo esc_textarea( (string) ( $instellingen['klantnummers'] ?? '' ) ); ?></textarea>
							<p class="description">De klantnummers uit PowerAll die een account krijgen, gescheiden door komma's of spaties. Leeg = er wordt niets opgehaald.</p>
						</td>
					</tr>
					<tr>
						<th scope="row">Uitnodiging</th>
						<td>
							<label><input type="checkbox" name="auto_uitnodigen" value="1" <?php checked( self::auto_uitnodigen() ); ?>> Een nieuwe dealer meteen een uitnodiging mailen</label>
							<p class="description">Staat dit uit, dan stuur je de uitnodiging per dealer met de knop in de lijst hieronder.</p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Opslaan' ); ?>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'hdp_powerall_ophalen' ); ?>
				<input type="hidden" name="action" value="hdp_powerall_ophalen">
				<?php submit_button( 'Nu ophalen uit PowerAll', 'primary', 'submit', false, HDP_PowerAll::beschikbaar() && self::klantnummers() ? array() : array( 'disabled' => 'disabled' ) ); ?>
			</form>

			<?php if ( $verslag ) : ?>
				<h2>Laatste keer opgehaald: <?php echo esc_html( self::datum( $verslag['tijd'] ) ); ?></h2>
				<?php if ( '' !== $verslag['fout'] ) : ?>
					<div class="notice notice-error inline"><p><?php echo esc_html( $verslag['fout'] ); ?></p></div>
				<?php endif; ?>
				<?php if ( $verslag['regels'] ) : ?>
					<table class="widefat striped" style="max-width:960px">
						<thead><tr><th>Klantnummer</th><th>Naam</th><th>Uitkomst</th></tr></thead>
						<tbody>
						<?php foreach ( $verslag['regels'] as $regel ) : ?>
							<tr><td><?php echo esc_html( $regel['code'] ); ?></td><td><?php echo esc_html( $regel['naam'] ); ?></td><td><?php echo esc_html( $regel['uitkomst'] ); ?></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			<?php endif; ?>

			<h2>Gekoppelde dealers (<?php echo esc_html( count( $dealers ) ); ?>)</h2>
			<?php if ( ! $dealers ) : ?>
				<p>Nog geen dealers gekoppeld.</p>
			<?php else : ?>
				<table class="widefat striped" style="max-width:1200px">
					<thead><tr><th>Klantnummer</th><th>Naam</th><th>E-mailadres</th><th>In PowerAll</th><th>Account</th><th></th></tr></thead>
					<tbody>
					<?php
					foreach ( $dealers as $dealer ) :
						$uitnodiging = get_user_meta( $dealer->ID, self::META_UITNODIGING, true );
						$gezet       = get_user_meta( $dealer->ID, self::META_WACHTWOORD, true );
						if ( $gezet ) {
							$account = 'Wachtwoord gekozen op ' . self::datum( $gezet );
						} elseif ( is_array( $uitnodiging ) ) {
							$account = (int) $uitnodiging['tot'] < time()
								? 'Uitnodiging verlopen (verstuurd ' . self::datum( $uitnodiging['verstuurd'] ) . ')'
								: 'Uitgenodigd op ' . self::datum( $uitnodiging['verstuurd'] ) . ', link werkt tot ' . self::datum( $uitnodiging['tot'] );
						} else {
							$account = 'Nog niet uitgenodigd';
						}
						$statussen = array(
							'actief'      => 'Actief',
							'geblokkeerd' => 'Geblokkeerd voor orders',
							'inactief'    => 'Niet actief',
						);
						?>
						<tr>
							<td><?php echo esc_html( get_user_meta( $dealer->ID, self::META_RELATIE, true ) ); ?></td>
							<td><a href="<?php echo esc_url( get_edit_user_link( $dealer->ID ) ); ?>"><?php echo esc_html( $dealer->display_name ); ?></a></td>
							<td><?php echo esc_html( $dealer->user_email ); ?></td>
							<td><?php echo esc_html( $statussen[ self::status( $dealer->ID ) ] ?? '' ); ?></td>
							<td><?php echo esc_html( $account ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('Een uitnodiging mailen naar <?php echo esc_js( $dealer->user_email ); ?>?');">
									<?php wp_nonce_field( 'hdp_powerall_uitnodigen_' . $dealer->ID ); ?>
									<input type="hidden" name="action" value="hdp_powerall_uitnodigen">
									<input type="hidden" name="user_id" value="<?php echo esc_attr( $dealer->ID ); ?>">
									<button type="submit" class="button"><?php echo esc_html( is_array( $uitnodiging ) || $gezet ? 'Opnieuw uitnodigen' : 'Uitnodiging sturen' ); ?></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}
}
