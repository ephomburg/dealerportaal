<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rendert het dealerportaal zelf: het inlogscherm (via HDP_Login) voor
 * uitgelogde/niet-goedgekeurde bezoekers, en na inloggen de welkomstkop,
 * "snel herbestellen", de 4 hoofdkaarten en de infosectie.
 *
 * Bewerkbare teksten (kaarten, intro's) hebben een Nederlands en een
 * Frans blokattribuut; HDP_I18N::kies() kiest de juiste op basis van de
 * taalswitch en valt terug op het Nederlands als het Frans nog leeg is.
 * Vaste teksten (labels, knoppen, meldingen) komen uit HDP_I18N::t().
 */
class HDP_Portal_Render {

	public static function render_dealerportaal( $a ) {
		ob_start();

		if ( is_user_logged_in() ) {
			self::render_portal_content( $a );
		} else {
			HDP_Login::render_login( HDP_Login::login_foutmelding(), $a );
		}

		return ob_get_clean();
	}

	private static function render_portal_content( $a ) {
		$user = wp_get_current_user();

		if ( ! HDP_Roles::mag_portaal_zien( $user->ID ) ) {
			?>
			<div class="hdp-login-sectie">
				<div class="hdp-login-kaart hdp-wacht-kaart">
					<?php HDP_Icons::render_icoon( 'wachten' ); ?>
					<h1><?php echo esc_html( HDP_I18N::t( 'account_titel' ) ); ?></h1>
					<p class="hdp-intro"><?php echo esc_html( HDP_I18N::t( 'account_tekst' ) ); ?></p>
					<?php
					// Een dealer die hier belandt kan nergens heen. Vertel dus
					// wat er nu gebeurt, hoe lang dat duurt en waar ze terecht
					// kunnen als het te lang duurt — anders is dit scherm het
					// eindstation van de allereerste kennismaking met het
					// portaal.
					?>
					<p class="hdp-wacht-stappen"><?php echo esc_html( HDP_I18N::t( 'account_stappen' ) ); ?></p>
					<?php self::render_contactregel(); ?>
				</div>
			</div>
			<?php
			return;
		}

		$portaal_intro = HDP_I18N::kies( $a['portaalIntro'], $a['portaalIntroFr'] );
		?>
		<div class="hdp-hero-nieuw alignfull" style="background-image:url('<?php echo esc_url( $a['heroAfbeelding'] ); ?>')">
			<div class="hdp-hero-plaat">
				<h1><?php echo esc_html( HDP_I18N::t( 'welkom_prefix' ) ); ?> <?php echo esc_html( $user->display_name ); ?></h1>
				<p><?php echo esc_html( $portaal_intro ); ?></p>
			</div>
		</div>
		<div class="hdp-portaal alignfull">
			<?php self::render_nieuw_melding(); ?>
			<?php HDP_Herbestellen::render(); ?>
		</div>
		<?php
	}

	/**
	 * "Er staat iets nieuws voor u klaar"-regel op de startpagina.
	 *
	 * Zonder dit is de automatische FileBird-koppeling eenrichtingsverkeer:
	 * bestanden verschijnen vanzelf in het portaal, maar een dealer moet
	 * gáán kijken om dat te ontdekken. Telt bewust via
	 * HDP_Downloads_Render::zichtbare_downloads(), dezelfde bron als de
	 * downloadspagina zelf — anders kan hier "3 nieuw" staan terwijl de
	 * dealer er door zijn merkrechten nul van te zien krijgt.
	 */
	private static function render_nieuw_melding() {
		$aantal = 0;
		foreach ( HDP_Downloads_Render::zichtbare_downloads( 'download' )['downloads'] as $download ) {
			if ( HDP_Downloads_CPT::is_nieuw( $download->ID ) ) {
				++$aantal;
			}
		}

		if ( ! $aantal ) {
			return;
		}

		$tekst = 1 === $aantal ? HDP_I18N::t( 'nieuw_melding_een' ) : sprintf( HDP_I18N::t( 'nieuw_melding_meer' ), $aantal );
		?>
		<a class="hdp-nieuw-melding" href="<?php echo esc_url( home_url( '/downloads/' ) ); ?>">
			<span class="hdp-nieuw-badge"><?php echo esc_html( HDP_I18N::t( 'badge_nieuw' ) ); ?></span>
			<span class="hdp-nieuw-tekst"><?php echo esc_html( $tekst ); ?></span>
			<span class="hdp-nieuw-pijl" aria-hidden="true">&rarr;</span>
		</a>
		<?php
	}

	/** Contactgegevens uit de plugin-instellingen; stil als die niet ingevuld zijn. */
	private static function render_contactregel() {
		$email    = HDP_Settings::get( 'contact_email' );
		$telefoon = HDP_Settings::get( 'contact_telefoon' );

		if ( ! $email && ! $telefoon ) {
			return;
		}
		?>
		<p class="hdp-wacht-contact">
			<?php echo esc_html( HDP_I18N::t( 'account_duurt_lang' ) ); ?>
		</p>
		<?php // Een <div> (het icoon-omhulsel van HDP_Icons) mag niet binnen een <p>: browsers sluiten die alinea dan voortijdig en de opmaak valt uiteen. ?>
		<div class="hdp-wacht-gegevens">
			<?php if ( $telefoon ) : ?>
				<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $telefoon ) ); ?>">
					<?php HDP_Icons::render_icoon( 'tel', 'hdp-inline-icoon' ); ?><?php echo esc_html( $telefoon ); ?>
				</a>
			<?php endif; ?>
			<?php if ( $email ) : ?>
				<a href="mailto:<?php echo esc_attr( $email ); ?>">
					<?php HDP_Icons::render_icoon( 'mail', 'hdp-inline-icoon' ); ?><?php echo esc_html( $email ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}
}
