<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inlogverwerking (met eenvoudige brute-force-bescherming) en het
 * inlogscherm zelf. Gedeeld door alle plekken waar het inlogformulier kan
 * verschijnen: dealerportaal, downloads- en content-pagina.
 *
 * De verwerking gebeurt bewust op het 'init'-hook (vóór er ooit HTML is
 * uitgestuurd) en niet in het blok zelf: wp_signon() zet een auth-cookie
 * via setcookie(), wat mislukt zodra de thema-header al is ge-echood. Na
 * verwerking volgt een redirect, zodat de renderfuncties alleen nog
 * hoeven te tekenen op basis van de huidige inlogstatus.
 */
class HDP_Login {

	const MAX_LOGIN_POGINGEN      = 5;
	const LOGIN_BLOKKADE_SECONDEN = 15 * MINUTE_IN_SECONDS;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'verwerk_login' ) );
	}

	/**
	 * IP-adres van de bezoeker, voor de eenvoudige pogingenteller hieronder.
	 * Bewust alleen REMOTE_ADDR (geen X-Forwarded-For e.d.): die headers zijn
	 * door de bezoeker zelf te vervalsen tenzij een reverse proxy ze expliciet
	 * overschrijft, en zouden de teller dus juist omzeilbaar maken.
	 */
	private static function login_ip() {
		return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	}

	private static function login_pogingen_sleutel() {
		return 'hdp_login_pogingen_' . md5( self::login_ip() );
	}

	public static function verwerk_login() {
		if ( ! isset( $_POST['hdp_login_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hdp_login_nonce'] ) ), 'hdp_login' ) ) {
			return;
		}

		$terug_naar = wp_get_referer();
		if ( ! $terug_naar ) {
			$terug_naar = home_url( '/dealerportaal/' );
		}

		$gebruikersnaam = isset( $_POST['gebruikersnaam'] ) ? sanitize_text_field( wp_unslash( $_POST['gebruikersnaam'] ) ) : '';
		// Wachtwoord bewust NIET door sanitize_text_field() halen: dat zou geldige
		// speciale tekens in een wachtwoord kunnen wijzigen, waardoor een dealer
		// met zo'n wachtwoord niet meer zou kunnen inloggen. wp-login.php van
		// WordPress-kern zelf doet dit om dezelfde reden ook niet.
		$wachtwoord = isset( $_POST['wachtwoord'] ) ? (string) wp_unslash( $_POST['wachtwoord'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		wp_safe_redirect( self::verwerk_inloggegevens( $gebruikersnaam, $wachtwoord, $terug_naar ) );
		exit;
	}

	/**
	 * Bevat de eigenlijke inlog-/blokkadebeslissing, los van de
	 * redirect/exit hierboven — 'exit' is niet aan te roepen binnen een
	 * testomgeving, dus staat de testbare logica hier apart.
	 */
	public static function verwerk_inloggegevens( $gebruikersnaam, $wachtwoord, $terug_naar ) {
		$pogingen_sleutel = self::login_pogingen_sleutel();
		$pogingen         = (int) get_transient( $pogingen_sleutel );

		if ( $pogingen >= self::MAX_LOGIN_POGINGEN ) {
			return add_query_arg( 'hdp_fout', 'geblokkeerd', remove_query_arg( 'hdp_fout', $terug_naar ) );
		}

		$resultaat = wp_signon(
			array(
				'user_login'    => $gebruikersnaam,
				'user_password' => $wachtwoord,
				'remember'      => true,
			),
			is_ssl()
		);

		if ( is_wp_error( $resultaat ) ) {
			set_transient( $pogingen_sleutel, $pogingen + 1, self::LOGIN_BLOKKADE_SECONDEN );
			return add_query_arg( 'hdp_fout', '1', remove_query_arg( 'hdp_fout', $terug_naar ) );
		}

		delete_transient( $pogingen_sleutel );
		return remove_query_arg( 'hdp_fout', $terug_naar );
	}

	/**
	 * Vertaalt de ?hdp_fout=-vlag (Post/Redirect/Get, geen state-wijziging)
	 * naar de juiste, vertaalde melding. Gedeeld door alle schermen waar het
	 * inlogformulier kan verschijnen (dealerportaal, downloads, content).
	 */
	public static function login_foutmelding() {
		if ( ! isset( $_GET['hdp_fout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '';
		}
		$code = sanitize_key( wp_unslash( $_GET['hdp_fout'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return 'geblokkeerd' === $code ? HDP_I18N::t( 'login_geblokkeerd' ) : HDP_I18N::t( 'login_fout' );
	}

	public static function render_login( $fout, $a ) {
		$intro = HDP_I18N::kies( $a['loginIntro'], $a['loginIntroFr'] );

		// Verbergt de site-header/-footer achter het fullscreen inlogscherm
		// hieronder — die staan al vast in het themasjabloon (parts/header.html
		// resp. footer.html) en worden dus niet via deze functie getekend.
		add_filter(
			'body_class',
			static function ( $classes ) {
				$classes[] = 'hdp-login-actief';
				return $classes;
			}
		);
		?>
		<div class="hdp-login-sectie hdp-login-fullscreen alignfull" style="background-image:url('<?php echo esc_url( $a['heroAfbeelding'] ); ?>')">
			<div class="hdp-login-kaart">
				<img class="hdp-login-logo" src="/wp-content/uploads/2026/07/Logo-HOMBURG_RGB-300x40.png" alt="Homburg">
				<h1><?php echo esc_html( HDP_I18N::t( 'login_titel' ) ); ?></h1>
				<p class="hdp-intro"><?php echo esc_html( $intro ); ?></p>

				<?php if ( $fout ) : ?>
					<div class="hdp-login-fout hdp-zichtbaar" role="alert"><?php echo esc_html( $fout ); ?></div>
				<?php endif; ?>

				<form method="post">
					<?php wp_nonce_field( 'hdp_login', 'hdp_login_nonce' ); ?>
					<div class="hdp-veld">
						<label for="gebruikersnaam"><?php echo esc_html( HDP_I18N::t( 'label_gebruiker' ) ); ?></label>
						<div class="hdp-veld-invoer">
							<?php echo HDP_Icons::svg_icoon( 'gebruiker' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?>
							<input type="text" id="gebruikersnaam" name="gebruikersnaam" autocomplete="username" required>
						</div>
					</div>
					<div class="hdp-veld">
						<label for="wachtwoord"><?php echo esc_html( HDP_I18N::t( 'label_wachtwoord' ) ); ?></label>
						<div class="hdp-veld-invoer">
							<?php echo HDP_Icons::svg_icoon( 'login' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?>
							<input type="password" id="wachtwoord" name="wachtwoord" autocomplete="current-password" required>
						</div>
					</div>
					<div class="hdp-veld-onthoud">
						<label>
							<input type="checkbox" id="onthoud_mij" name="onthoud_mij" checked>
							<?php echo esc_html( HDP_I18N::t( 'onthoud_mij' ) ); ?>
						</label>
					</div>
					<button type="submit" class="hdp-btn"><?php echo esc_html( HDP_I18N::t( 'btn_inloggen' ) ); ?></button>
				</form>
				<p class="hdp-wachtwoord-vergeten"><a href="<?php echo esc_url( wp_lostpassword_url( home_url( '/dealerportaal/' ) ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'wachtwoord_vergeten' ) ); ?></a></p>
			</div>
		</div>
		<?php
	}
}
