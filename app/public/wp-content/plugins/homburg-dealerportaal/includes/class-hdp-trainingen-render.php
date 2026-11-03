<?php
/**
 * De trainingen zoals de dealer ze ziet: het overzicht, de pagina van één
 * training en het aanmeldformulier. De regels staan in HDP_Trainingen.
 *
 * @package Homburg_Dealerportaal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Toont de trainingen en verwerkt het aanmeldformulier.
 */
class HDP_Trainingen_Render {

	const NONCE      = 'hdp_training_aanmelden';
	const ACTIE_VELD = 'hdp_training_aanmelden';

	/** Meer streepjes passen niet netjes in het paneel. */
	const MAX_STREEPJES = 24;

	/** Fouten en invoer van een mislukte poging, om hetzelfde formulier ingevuld terug te tonen. */
	private static $fouten = array();
	private static $invoer = array();

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'verwerk' ) );
	}

	/**
	 * Vóór er iets is uitgestuurd. Na een geslaagde aanmelding een redirect,
	 * zodat verversen niet opnieuw aanmeldt.
	 */
	public static function verwerk() {
		if ( empty( $_POST[ self::ACTIE_VELD ] ) ) {
			return;
		}
		if ( ! is_user_logged_in() ) {
			return;
		}

		$training_id = absint( $_POST[ self::ACTIE_VELD ] );
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_wpnonce'] ) ), self::NONCE . '_' . $training_id ) ) {
			self::$fouten = array( 'algemeen' => 'training_fout_verlopen' );
			return;
		}

		self::$invoer = wp_unslash( $_POST );
		$uitkomst     = HDP_Trainingen::meld_aan( $training_id, self::$invoer );
		if ( is_array( $uitkomst ) ) {
			self::$fouten = $uitkomst;
			return;
		}

		wp_safe_redirect( add_query_arg( 'aangemeld', 1, HDP_Trainingen::url( $training_id ) ) . '#aanmelden' );
		exit;
	}

	public static function render_pagina( $a ) {
		if ( ! is_user_logged_in() || ! HDP_Roles::mag_portaal_zien( get_current_user_id() ) ) {
			ob_start();
			HDP_Login::render_login( HDP_Login::login_foutmelding(), $a );
			return ob_get_clean();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- alleen een keuze uit de URL; mag_zien() controleert.
		$training_id = isset( $_GET['training'] ) ? absint( $_GET['training'] ) : 0;
		$tonen       = $training_id && HDP_Trainingen::mag_zien( $training_id );

		ob_start();
		echo '<div class="hdp-portaal alignfull">';
		if ( $tonen ) {
			self::render_training( $training_id );
		} else {
			self::render_overzicht();
		}
		echo '</div>';
		return ob_get_clean();
	}

	private static function render_overzicht() {
		HDP_Paginakop::render(
			array_merge(
				array(
					'titel' => HDP_I18N::t( 'training_titel' ),
					'intro' => HDP_I18N::t( 'training_intro' ),
				),
				HDP_Paginakop::terug_naar_portaal()
			)
		);
		$trainingen = HDP_Trainingen::komende();
		?>
		<section class="hdp-trainingen">
			<?php if ( ! $trainingen ) : ?>
				<p class="hdp-training-leeg"><?php echo esc_html( HDP_I18N::t( 'training_leeg' ) ); ?></p>
			<?php else : ?>
				<ul class="hdp-training-lijst">
					<?php foreach ( $trainingen as $id ) : ?>
						<?php self::render_kaart( $id ); ?>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
		<?php
	}

	private static function render_kaart( $id ) {
		$feiten  = HDP_Trainingen::feiten( $id );
		$eigen   = count( HDP_Trainingen::van_dealer( $id ) );
		$is_nieuw = (int) get_post_time( 'U', true, $id ) > time() - HDP_Downloads_CPT::NIEUW_DAGEN * DAY_IN_SECONDS;
		$tekst   = wp_trim_words( wp_strip_all_tags( HDP_Trainingen::tekst( $id ) ), 26 );
		$url     = HDP_Trainingen::url( $id );
		?>
		<li class="hdp-training-kaart">
			<a class="hdp-training-kaart-link" href="<?php echo esc_url( $url ); ?>">
				<?php if ( has_post_thumbnail( $id ) ) : ?>
					<?php echo get_the_post_thumbnail( $id, 'medium_large', array( 'class' => 'hdp-training-beeld', 'alt' => '' ) ); ?>
				<?php endif; ?>
				<span class="hdp-training-kaart-body">
					<span class="hdp-training-labels">
						<?php if ( $is_nieuw ) : ?>
							<span class="hdp-training-label hdp-training-label-nieuw"><?php echo esc_html( HDP_I18N::t( 'training_nieuw' ) ); ?></span>
						<?php endif; ?>
						<?php self::render_standlabel( $id, $eigen ); ?>
					</span>
					<strong class="hdp-training-kaart-titel"><?php echo esc_html( HDP_Trainingen::titel( $id ) ); ?></strong>
					<?php if ( $feiten ) : ?>
						<span class="hdp-training-kaart-feiten"><?php echo esc_html( implode( ' · ', array_slice( array_values( $feiten ), 0, 3 ) ) ); ?></span>
					<?php endif; ?>
					<span class="hdp-training-kaart-tekst"><?php echo esc_html( $tekst ); ?></span>
					<span class="hdp-training-kaart-meer"><?php echo esc_html( HDP_I18N::t( 'training_bekijk' ) ); ?> &rarr;</span>
				</span>
			</a>
		</li>
		<?php
	}

	private static function render_standlabel( $id, $eigen ) {
		if ( $eigen ) {
			echo '<span class="hdp-training-label hdp-training-label-aangemeld">' . esc_html( HDP_I18N::t( 'training_label_aangemeld' ) ) . '</span>';
			return;
		}
		$stand = HDP_Trainingen::stand( $id );
		if ( 'vol' === $stand || 'gesloten' === $stand ) {
			echo '<span class="hdp-training-label">' . esc_html( HDP_I18N::t( 'training_stand_' . $stand ) ) . '</span>';
		}
	}

	private static function render_training( $id ) {
		$eigen = HDP_Trainingen::van_dealer( $id );
		$stand = HDP_Trainingen::stand( $id );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- alleen om de bevestiging te tonen.
		$net_aangemeld = ! empty( $_GET['aangemeld'] ) && $eigen;
		?>
		<section class="hdp-trainingen hdp-training-detail">
			<a class="hdp-terug-boven" href="<?php echo esc_url( HDP_Trainingen::url() ); ?>">&larr; <?php echo esc_html( HDP_I18N::t( 'training_terug' ) ); ?></a>

			<div class="hdp-training-kop">
				<div class="hdp-training-uitleg">
					<?php if ( has_post_thumbnail( $id ) ) : ?>
						<?php echo get_the_post_thumbnail( $id, 'large', array( 'class' => 'hdp-training-beeld', 'alt' => '' ) ); ?>
					<?php endif; ?>
					<p class="hdp-training-soort"><?php echo esc_html( HDP_I18N::t( 'training_label' ) ); ?></p>
					<h1><?php echo esc_html( HDP_Trainingen::titel( $id ) ); ?></h1>
					<div class="hdp-training-tekst"><?php echo wp_kses_post( HDP_Trainingen::tekst( $id ) ); ?></div>
				</div>
				<?php self::render_feiten( $id, $stand, $eigen ); ?>
			</div>

			<div class="hdp-training-aanmelden" id="aanmelden">
				<?php
				if ( 'open' === $stand ) {
					self::render_formulier( $id, $eigen, $net_aangemeld );
				} elseif ( ! $eigen ) {
					echo '<p class="hdp-training-melding">' . esc_html( HDP_I18N::t( 'training_stand_' . $stand . '_uitleg' ) ) . '</p>';
				}
				?>
			</div>
		</section>
		<?php
	}

	/** Het donkere paneel: datum, locatie, kosten, plaatsen en de weg naar het formulier. */
	private static function render_feiten( $id, $stand, $eigen ) {
		$datum   = (string) get_post_meta( $id, HDP_Trainingen::META_DATUM, true );
		$tijd    = (string) get_post_meta( $id, HDP_Trainingen::META_TIJD, true );
		$locatie = (string) get_post_meta( $id, HDP_Trainingen::META_LOCATIE, true );
		$kosten  = (string) get_post_meta( $id, HDP_Trainingen::META_KOSTEN, true );
		?>
		<aside class="hdp-training-feiten" aria-label="<?php echo esc_attr( HDP_I18N::t( 'training_feiten_label' ) ); ?>">
			<div class="hdp-training-datum">
				<?php if ( $datum ) : ?>
					<div class="hdp-training-blad" aria-hidden="true">
						<span><?php echo esc_html( rtrim( mb_strtoupper( HDP_I18N::datum( 'M', strtotime( $datum ) ) ), '.' ) ); ?></span>
						<strong><?php echo esc_html( HDP_I18N::datum( 'j', strtotime( $datum ) ) ); ?></strong>
					</div>
				<?php endif; ?>
				<div class="hdp-training-datumtekst">
					<strong><?php echo esc_html( $datum ? HDP_Trainingen::datumtekst( $id ) : HDP_I18N::t( 'training_datum_volgt' ) ); ?></strong>
					<?php if ( $tijd ) : ?>
						<small><?php echo esc_html( $tijd ); ?></small>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( $locatie || $kosten ) : ?>
				<dl class="hdp-training-regels">
					<?php if ( $locatie ) : ?>
						<div>
							<?php echo HDP_Icons::svg_icoon( 'adres' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- vaste opmaak. ?>
							<dt><?php echo esc_html( HDP_I18N::t( 'training_feit_locatie' ) ); ?></dt>
							<dd><?php echo esc_html( $locatie ); ?></dd>
						</div>
					<?php endif; ?>
					<?php if ( $kosten ) : ?>
						<div>
							<?php echo HDP_Icons::svg_icoon( 'euro' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- vaste opmaak. ?>
							<dt><?php echo esc_html( HDP_I18N::t( 'training_feit_kosten' ) ); ?></dt>
							<dd><?php echo esc_html( $kosten ); ?></dd>
						</div>
					<?php endif; ?>
				</dl>
			<?php endif; ?>

			<?php self::render_plaatsen( $id, $stand ); ?>

			<?php if ( 'open' === $stand ) : ?>
				<a class="hdp-btn hdp-training-naar-formulier" href="#aanmelden"><?php echo esc_html( HDP_I18N::t( $eigen ? 'training_nog_iemand' : 'training_aanmelden' ) ); ?></a>
			<?php endif; ?>

			<?php if ( $eigen ) : ?>
				<div class="hdp-training-eigen">
					<h2><?php echo esc_html( HDP_I18N::t( 'training_jouw_aanmeldingen' ) ); ?></h2>
					<ul>
						<?php foreach ( $eigen as $rij ) : ?>
							<li><?php echo esc_html( $rij['deelnemer_naam'] ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</aside>
		<?php
	}

	/** Hoeveel plaatsen er nog zijn, met een balkje. Alleen als er een maximum is en de training nog komt. */
	private static function render_plaatsen( $id, $stand ) {
		$plaatsen = (int) get_post_meta( $id, HDP_Trainingen::META_PLAATSEN, true );
		if ( $plaatsen <= 0 || 'voorbij' === $stand ) {
			return;
		}
		$over  = (int) HDP_Trainingen::plaatsen_over( $id );
		$bezet = $plaatsen - $over;
		if ( 'vol' === $stand ) {
			$kop = HDP_I18N::t( 'training_stand_vol' );
		} else {
			$kop = 1 === $over ? HDP_I18N::t( 'training_plaats_over' ) : sprintf( HDP_I18N::t( 'training_plaatsen_over' ), $over );
		}
		// Bij een grote groep past niet elke plaats als eigen streepje: dan op schaal.
		$streepjes = min( $plaatsen, self::MAX_STREEPJES );
		$gevuld    = (int) ceil( $bezet / $plaatsen * $streepjes );
		?>
		<div class="hdp-training-plaatsen">
			<p>
				<strong><?php echo esc_html( $kop ); ?></strong>
				<span><?php echo esc_html( sprintf( HDP_I18N::t( 'training_plaatsen_bezet' ), $bezet, $plaatsen ) ); ?></span>
			</p>
			<div class="hdp-training-stoelen" style="--hdp-stoelen: <?php echo (int) $streepjes; ?>" aria-hidden="true">
				<?php for ( $i = 0; $i < $streepjes; $i++ ) : ?>
					<i<?php echo $i < $gevuld ? ' class="is-bezet"' : ''; ?>></i>
				<?php endfor; ?>
			</div>
		</div>
		<?php
	}

	private static function render_formulier( $id, $eigen, $net_aangemeld ) {
		$user   = wp_get_current_user();
		$fouten = self::$fouten;
		// Na een aanmelding geen ingevuld formulier meer: de volgende is meestal een collega.
		$eerder_naam  = isset( self::$invoer['naam'] ) ? (string) self::$invoer['naam'] : ( $eigen ? '' : $user->display_name );
		$eerder_email = isset( self::$invoer['email'] ) ? (string) self::$invoer['email'] : ( $eigen ? '' : $user->user_email );
		?>
		<?php if ( $net_aangemeld ) : ?>
			<p class="hdp-training-melding hdp-training-melding-goed" role="status"><strong><?php echo esc_html( HDP_I18N::t( 'training_gelukt' ) ); ?></strong> <?php echo esc_html( HDP_I18N::t( 'training_gelukt_uitleg' ) ); ?></p>
		<?php endif; ?>
		<?php if ( isset( $fouten['algemeen'] ) ) : ?>
			<p class="hdp-training-melding hdp-training-melding-fout" role="alert"><?php echo esc_html( HDP_I18N::t( $fouten['algemeen'] ) ); ?></p>
		<?php endif; ?>
		<form class="hdp-garantie-formulier hdp-training-formulier" method="post" action="<?php echo esc_url( HDP_Trainingen::url( $id ) . '#aanmelden' ); ?>">
			<?php wp_nonce_field( self::NONCE . '_' . $id ); ?>
			<input type="hidden" name="<?php echo esc_attr( self::ACTIE_VELD ); ?>" value="<?php echo esc_attr( $id ); ?>">
			<h2><?php echo esc_html( HDP_I18N::t( $eigen ? 'training_nog_iemand' : 'training_aanmelden' ) ); ?></h2>
			<div class="hdp-garantie-velden">
				<?php
				self::render_vast_veld( 'naam', HDP_I18N::t( 'training_veld_naam' ), 'text', $eerder_naam );
				self::render_vast_veld( 'email', HDP_I18N::t( 'training_veld_email' ), 'email', $eerder_email );
				foreach ( HDP_Trainingen::velden( $id ) as $veld ) {
					self::render_veld( $veld );
				}
				?>
			</div>
			<p class="hdp-garantie-verzenden">
				<button type="submit" class="hdp-btn"><?php echo esc_html( HDP_I18N::t( 'training_aanmelden_knop' ) ); ?></button>
			</p>
		</form>
		<?php
	}

	private static function render_vast_veld( $naam, $label, $type, $waarde ) {
		$id = 'hdp-training-' . $naam;
		?>
		<div class="hdp-veld">
			<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?> <span class="hdp-veld-verplicht" aria-hidden="true">*</span></label>
			<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $naam ); ?>" value="<?php echo esc_attr( $waarde ); ?>" required>
			<?php self::render_fout( $naam ); ?>
		</div>
		<?php
	}

	private static function render_veld( $veld ) {
		$naam      = 'veld_' . $veld['id'];
		$id        = 'hdp-training-' . $naam;
		$waarde    = isset( self::$invoer[ $naam ] ) && is_scalar( self::$invoer[ $naam ] ) ? (string) self::$invoer[ $naam ] : '';
		$verplicht = ! empty( $veld['verplicht'] );
		$label     = HDP_I18N::kies( $veld['label'], $veld['label_fr'] );
		$breed     = in_array( $veld['soort'], array( 'lang', 'vinkje' ), true );
		$ster      = $verplicht ? ' <span class="hdp-veld-verplicht" aria-hidden="true">*</span>' : '';
		?>
		<div class="hdp-veld<?php echo $breed ? ' hdp-veld-breed' : ''; ?>">
			<?php if ( 'vinkje' === $veld['soort'] ) : ?>
				<label class="hdp-training-vinkje">
					<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $naam ); ?>" value="1" <?php checked( '' !== $waarde ); ?> <?php echo $verplicht ? 'required' : ''; ?>>
					<span><?php echo esc_html( $label ) . $ster; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $ster is vaste opmaak. ?></span>
				</label>
			<?php else : ?>
				<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ) . $ster; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $ster is vaste opmaak. ?></label>
				<?php if ( 'lang' === $veld['soort'] ) : ?>
					<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $naam ); ?>" rows="3" <?php echo $verplicht ? 'required' : ''; ?>><?php echo esc_textarea( $waarde ); ?></textarea>
				<?php elseif ( 'keuze' === $veld['soort'] ) : ?>
					<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $naam ); ?>" <?php echo $verplicht ? 'required' : ''; ?>>
						<option value=""><?php echo esc_html( HDP_I18N::t( 'garantie_kies' ) ); ?></option>
						<?php foreach ( HDP_Trainingen::opties( $veld ) as $optie => $tekst ) : ?>
							<option value="<?php echo esc_attr( $optie ); ?>" <?php selected( $waarde, $optie ); ?>><?php echo esc_html( $tekst ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<?php
					$types = array(
						'telefoon' => 'tel',
						'email'    => 'email',
						'getal'    => 'number',
						'datum'    => 'date',
					);
					$type  = isset( $types[ $veld['soort'] ] ) ? $types[ $veld['soort'] ] : 'text';
					?>
					<input type="<?php echo esc_attr( $type ); ?>" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $naam ); ?>" value="<?php echo esc_attr( $waarde ); ?>" <?php echo $verplicht ? 'required' : ''; ?>>
				<?php endif; ?>
			<?php endif; ?>
			<?php self::render_fout( $naam ); ?>
		</div>
		<?php
	}

	private static function render_fout( $naam ) {
		if ( isset( self::$fouten[ $naam ] ) ) {
			echo '<span class="hdp-training-fout" role="alert">' . esc_html( HDP_I18N::t( self::$fouten[ $naam ] ) ) . '</span>';
		}
	}
}
