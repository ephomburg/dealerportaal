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

	/** Tot wanneer de dealer de melding over nieuwe documenten heeft weggeklikt. */
	const META_NIEUW_WEG = 'hdp_nieuw_weggeklikt_tot';

	const NIEUW_WEG_VELD  = 'hdp_nieuw_weg';
	const NIEUW_WEG_NONCE = 'hdp_nieuw_wegklikken';

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'verwerk_wegklikken' ) );
	}

	/**
	 * De melding over nieuwe documenten wegklikken.
	 *
	 * Bewaart niet "weggeklikt ja/nee" maar tot welk moment: komt er daarna
	 * een document bij, dan is de melding weer terecht en verschijnt hij
	 * vanzelf. Met een enkele vlag zou de dealer hem één keer wegklikken en
	 * er daarna nooit meer iets van horen.
	 */
	public static function verwerk_wegklikken() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- de nonce wordt hieronder gecontroleerd zodra we weten dát dit onze knop is.
		if ( ! isset( $_POST[ self::NIEUW_WEG_VELD ] ) || ! is_user_logged_in() ) {
			return;
		}

		check_admin_referer( self::NIEUW_WEG_NONCE );

		update_user_meta( get_current_user_id(), self::META_NIEUW_WEG, time() );

		// Terug naar dezelfde pagina, zodat een verversing niet opnieuw
		// verstuurt.
		wp_safe_redirect( remove_query_arg( array() ) );
		exit;
	}

	public static function render_dealerportaal( $a ) {
		ob_start();

		if ( is_user_logged_in() ) {
			self::render_portal_content();
		} else {
			HDP_Login::render_login( HDP_Login::login_foutmelding(), $a );
		}

		return ob_get_clean();
	}

	private static function render_portal_content() {
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

		?>
		<div class="hdp-welkomscherm">
			<?php self::render_welkomkop( $user ); ?>
			<?php // Berichten van Homburg staan bovenaan: een mededeling die je pas tegenkomt nadat je ergens heen geklikt hebt, is te laat. ?>
			<?php HDP_Berichten::render(); ?>
			<?php self::render_nieuw_melding(); ?>
			<?php HDP_Herbestellen::render(); ?>
		</div>
		<?php
	}

	/**
	 * De kop van het welkomscherm: groet, datum en een zoekveld.
	 *
	 * Verving de paginabrede foto. Die foto nam driehonderd pixels van het
	 * eerste scherm in beslag en vertelde niets; een dealer die inlogt wil
	 * weten waar hij is, welke dag het is en waar hij iets kan zoeken.
	 *
	 * @param WP_User $user De ingelogde dealer.
	 */
	private static function render_welkomkop( $user ) {
		// Via de lokale tijd als tekst en niet via current_time('timestamp'):
		// dat laatste is afgeraden, en dit levert hetzelfde soort tijdstempel
		// op als overal elders in deze code (strtotime op een datumtekst),
		// waar HDP_I18N::datum() op rekent.
		$nu = strtotime( current_time( 'mysql' ) );
		?>
		<div class="hdp-welkomkop">
			<div class="hdp-welkomkop-tekst">
				<h1><?php echo esc_html( self::groet( $nu ) ); ?>, <?php echo esc_html( $user->display_name ); ?></h1>
				<p class="hdp-welkomkop-datum"><?php echo esc_html( HDP_I18N::datum( 'l j F Y', $nu ) ); ?></p>
			</div>
			<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
				<form class="hdp-welkomkop-zoek" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<label for="hdp-home-zoek" class="hdp-vh"><?php echo esc_html( HDP_I18N::t( 'home_zoek_label' ) ); ?></label>
					<input id="hdp-home-zoek" type="search" name="s" placeholder="<?php echo esc_attr( HDP_I18N::t( 'home_zoek_label' ) ); ?>">
					<?php // Zonder dit zoekt WordPress door berichten en pagina's heen; hier gaat het altijd om artikelen. ?>
					<input type="hidden" name="post_type" value="product">
					<button type="submit"><?php echo esc_html( HDP_I18N::t( 'home_zoek_knop' ) ); ?></button>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Goedemorgen / goedemiddag / goedenavond.
	 *
	 * Met eigen woorden en niet via de taalbestanden van WordPress: die
	 * bestaan in dit project niet in het Frans, dus alle vertaling loopt
	 * via HDP_I18N.
	 *
	 * @param int $tijdstip Tijdstempel in de tijdzone van de site.
	 * @return string
	 */
	private static function groet( $tijdstip ) {
		$uur = (int) wp_date( 'G', $tijdstip );

		if ( $uur < 12 ) {
			return HDP_I18N::t( 'groet_ochtend' );
		}

		return $uur < 18 ? HDP_I18N::t( 'groet_middag' ) : HDP_I18N::t( 'groet_avond' );
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
		// Alles van vóór het wegklikken telt niet meer mee; wat daarna is
		// toegevoegd wél.
		$sinds  = (int) get_user_meta( get_current_user_id(), self::META_NIEUW_WEG, true );
		$aantal = 0;

		foreach ( HDP_Downloads_Render::zichtbare_downloads( 'download' )['downloads'] as $download ) {
			if ( ! HDP_Downloads_CPT::is_nieuw( $download->ID ) ) {
				continue;
			}
			if ( $sinds && (int) get_post_time( 'U', true, $download->ID ) <= $sinds ) {
				continue;
			}
			++$aantal;
		}

		if ( ! $aantal ) {
			return;
		}

		$tekst = 1 === $aantal ? HDP_I18N::t( 'nieuw_melding_een' ) : sprintf( HDP_I18N::t( 'nieuw_melding_meer' ), $aantal );
		?>
		<?php // Een <div> om de link heen en geen link om alles: een knop binnen een link is geen geldige opbouw, en de ene klik hoort niet de andere te worden. ?>
		<div class="hdp-nieuw-melding">
			<a class="hdp-nieuw-link" href="<?php echo esc_url( home_url( '/downloads/' ) ); ?>">
				<span class="hdp-nieuw-badge"><?php echo esc_html( HDP_I18N::t( 'badge_nieuw' ) ); ?></span>
				<span class="hdp-nieuw-tekst"><?php echo esc_html( $tekst ); ?></span>
				<span class="hdp-nieuw-pijl" aria-hidden="true">&rarr;</span>
			</a>
			<form class="hdp-nieuw-weg" method="post">
				<?php wp_nonce_field( self::NIEUW_WEG_NONCE ); ?>
				<input type="hidden" name="<?php echo esc_attr( self::NIEUW_WEG_VELD ); ?>" value="1">
				<button type="submit" title="<?php echo esc_attr( HDP_I18N::t( 'nieuw_melding_weg' ) ); ?>">
					<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><line x1="6" y1="6" x2="18" y2="18"></line><line x1="18" y1="6" x2="6" y2="18"></line></svg>
					<span><?php echo esc_html( HDP_I18N::t( 'nieuw_melding_weg' ) ); ?></span>
				</button>
			</form>
		</div>
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
