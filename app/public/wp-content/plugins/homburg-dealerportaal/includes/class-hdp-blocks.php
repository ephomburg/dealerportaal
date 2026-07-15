<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registreert de dealerportaal-blokken en rendert ze.
 *
 * De inlogverwerking gebeurt bewust op het 'init'-hook (vóór er ooit HTML
 * is uitgestuurd) en niet in het blok zelf: wp_signon() zet een
 * auth-cookie via setcookie(), wat mislukt zodra de thema-header al is
 * ge-echood. Na verwerking volgt een redirect, zodat het blok alleen nog
 * hoeft te renderen op basis van de huidige inlogstatus.
 *
 * Bewerkbare teksten (kaarten, intro's) hebben een Nederlands en een
 * Frans blokattribuut; HDP_I18N::kies() kiest de juiste op basis van de
 * taalswitch en valt terug op het Nederlands als het Frans nog leeg is.
 * Vaste teksten (labels, knoppen, meldingen) komen uit HDP_I18N::t().
 */
class HDP_Blocks {

	const MAX_LOGIN_POGINGEN      = 5;
	const LOGIN_BLOKKADE_SECONDEN = 15 * MINUTE_IN_SECONDS;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'registreer_blokken' ) );
		add_action( 'init', array( __CLASS__, 'verwerk_login' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function registreer_blokken() {
		register_block_type( HDP_PLUGIN_DIR . 'blocks/dealerportaal' );
		register_block_type( HDP_PLUGIN_DIR . 'blocks/downloads' );
		register_block_type( HDP_PLUGIN_DIR . 'blocks/content' );
	}

	public static function enqueue_assets() {
		$post = get_post();
		if ( ! $post
			|| ( ! has_block( 'homburg/dealerportaal', $post )
				&& ! has_block( 'homburg/downloads-pagina', $post )
				&& ! has_block( 'homburg/content-pagina', $post ) )
		) {
			return;
		}

		wp_enqueue_style( 'hdp-dealerportaal', HDP_PLUGIN_URL . 'assets/css/dealerportaal.css', array(), HDP_VERSION );
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
	private static function login_foutmelding() {
		if ( ! isset( $_GET['hdp_fout'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return '';
		}
		$code = sanitize_key( wp_unslash( $_GET['hdp_fout'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return 'geblokkeerd' === $code ? HDP_I18N::t( 'login_geblokkeerd' ) : HDP_I18N::t( 'login_fout' );
	}

	public static function render_dealerportaal( $a ) {
		ob_start();

		if ( is_user_logged_in() ) {
			self::render_portal_content( $a );
		} else {
			self::render_login( self::login_foutmelding(), $a );
		}

		return ob_get_clean();
	}

	public static function render_downloads_pagina( $a ) {
		$mag_zien = is_user_logged_in() && HDP_Roles::mag_portaal_zien( get_current_user_id() );

		if ( ! $mag_zien ) {
			ob_start();
			self::render_login( self::login_foutmelding(), $a );
			return ob_get_clean();
		}

		$titel        = HDP_I18N::kies( $a['titel'], $a['titelFr'] );
		$omschrijving = HDP_I18N::kies( $a['omschrijving'], $a['omschrijvingFr'] );

		ob_start();
		?>
		<div class="hdp-portaal alignfull">
			<section class="hdp-welkom alignfull hdp-welkom-zonder-hero">
				<div class="hdp-welkom-inner">
					<a class="hdp-terug-boven" href="<?php echo esc_url( home_url( '/dealerportaal/' ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'terug_naar_portaal' ) ); ?></a>
					<div class="hdp-welkom-top">
						<h1><?php echo esc_html( $titel ); ?></h1>
						<a class="hdp-btn-logout" href="<?php echo esc_url( wp_logout_url( home_url( '/dealerportaal/' ) ) ); ?>"><?php echo self::svg_icoon( 'uitloggen' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?><?php echo esc_html( HDP_I18N::t( 'btn_uitloggen' ) ); ?></a>
					</div>
					<p><?php echo esc_html( $omschrijving ); ?></p>
				</div>
			</section>
			<section class="hdp-downloads">
				<?php echo self::render_downloads_lijst( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput -- reeds ge-escaped in render_downloads_lijst(). ?>
				<p class="hdp-terug"><a class="hdp-btn hdp-btn-secundair" href="<?php echo esc_url( home_url( '/dealerportaal/' ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'terug_naar_portaal' ) ); ?></a></p>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Bibliotheek met content voor social media/advertenties — zelfde
	 * opzet als de downloadspagina (zoeken, merk-/regiofilter, beveiligd
	 * endpoint), maar dan gefilterd op categorie "content" i.p.v.
	 * "download". Zie render_downloads_lijst().
	 */
	public static function render_content_pagina( $a ) {
		$mag_zien = is_user_logged_in() && HDP_Roles::mag_portaal_zien( get_current_user_id() );

		if ( ! $mag_zien ) {
			ob_start();
			self::render_login( self::login_foutmelding(), $a );
			return ob_get_clean();
		}

		$titel        = HDP_I18N::kies( $a['titel'], $a['titelFr'] );
		$omschrijving = HDP_I18N::kies( $a['omschrijving'], $a['omschrijvingFr'] );

		ob_start();
		?>
		<div class="hdp-portaal alignfull">
			<section class="hdp-welkom alignfull hdp-welkom-zonder-hero">
				<div class="hdp-welkom-inner">
					<a class="hdp-terug-boven" href="<?php echo esc_url( home_url( '/dealerportaal/' ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'terug_naar_portaal' ) ); ?></a>
					<div class="hdp-welkom-top">
						<h1><?php echo esc_html( $titel ); ?></h1>
						<a class="hdp-btn-logout" href="<?php echo esc_url( wp_logout_url( home_url( '/dealerportaal/' ) ) ); ?>"><?php echo self::svg_icoon( 'uitloggen' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?><?php echo esc_html( HDP_I18N::t( 'btn_uitloggen' ) ); ?></a>
					</div>
					<p><?php echo esc_html( $omschrijving ); ?></p>
				</div>
			</section>
			<section class="hdp-downloads">
				<?php echo self::render_downloads_lijst( 'content' ); // phpcs:ignore WordPress.Security.EscapeOutput -- reeds ge-escaped in render_downloads_lijst(). ?>
				<p class="hdp-terug"><a class="hdp-btn hdp-btn-secundair" href="<?php echo esc_url( home_url( '/dealerportaal/' ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'terug_naar_portaal' ) ); ?></a></p>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_login( $fout, $a ) {
		$intro = HDP_I18N::kies( $a['loginIntro'], $a['loginIntroFr'] );
		?>
		<div class="hdp-hero alignfull" style="background-image:url('<?php echo esc_url( $a['heroAfbeelding'] ); ?>')" aria-hidden="true"></div>
		<div class="hdp-login-sectie">
			<div class="hdp-login-kaart">
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
							<?php echo self::svg_icoon( 'gebruiker' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?>
							<input type="text" id="gebruikersnaam" name="gebruikersnaam" autocomplete="username" required>
						</div>
					</div>
					<div class="hdp-veld">
						<label for="wachtwoord"><?php echo esc_html( HDP_I18N::t( 'label_wachtwoord' ) ); ?></label>
						<div class="hdp-veld-invoer">
							<?php echo self::svg_icoon( 'login' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?>
							<input type="password" id="wachtwoord" name="wachtwoord" autocomplete="current-password" required>
						</div>
					</div>
					<button type="submit" class="hdp-btn"><?php echo esc_html( HDP_I18N::t( 'btn_inloggen' ) ); ?></button>
				</form>
				<p class="hdp-wachtwoord-vergeten"><a href="<?php echo esc_url( wp_lostpassword_url( home_url( '/dealerportaal/' ) ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'wachtwoord_vergeten' ) ); ?></a></p>
			</div>
		</div>
		<?php
	}

	private static function render_portal_content( $a ) {
		$user = wp_get_current_user();

		if ( ! HDP_Roles::mag_portaal_zien( $user->ID ) ) {
			?>
			<div class="hdp-login-sectie">
				<div class="hdp-login-kaart">
					<?php self::render_icoon( 'wachten' ); ?>
					<h1><?php echo esc_html( HDP_I18N::t( 'account_titel' ) ); ?></h1>
					<p class="hdp-intro"><?php echo esc_html( HDP_I18N::t( 'account_tekst' ) ); ?></p>
					<a class="hdp-btn-logout" href="<?php echo esc_url( wp_logout_url( home_url( '/dealerportaal/' ) ) ); ?>"><?php echo self::svg_icoon( 'uitloggen' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?><?php echo esc_html( HDP_I18N::t( 'btn_uitloggen' ) ); ?></a>
				</div>
			</div>
			<?php
			return;
		}

		$merken           = get_user_meta( $user->ID, 'hdp_merken', true );
		$webshop_url      = HDP_Settings::get( 'webshop_url' );
		$configurator_url = HDP_Settings::get( 'configurator_url' );

		$portaal_intro       = HDP_I18N::kies( $a['portaalIntro'], $a['portaalIntroFr'] );
		$kaart1_titel        = HDP_I18N::kies( $a['kaart1Titel'], $a['kaart1TitelFr'] );
		$kaart1_omschrijving = HDP_I18N::kies( $a['kaart1Omschrijving'], $a['kaart1OmschrijvingFr'] );
		$kaart1_knoptekst    = HDP_I18N::kies( $a['kaart1Knoptekst'], $a['kaart1KnoptekstFr'] );
		$kaart2_titel        = HDP_I18N::kies( $a['kaart2Titel'], $a['kaart2TitelFr'] );
		$kaart2_omschrijving = HDP_I18N::kies( $a['kaart2Omschrijving'], $a['kaart2OmschrijvingFr'] );
		$kaart2_knoptekst    = HDP_I18N::kies( $a['kaart2Knoptekst'], $a['kaart2KnoptekstFr'] );
		$kaart3_titel        = HDP_I18N::kies( $a['kaart3Titel'], $a['kaart3TitelFr'] );
		$kaart3_omschrijving = HDP_I18N::kies( $a['kaart3Omschrijving'], $a['kaart3OmschrijvingFr'] );
		$kaart3_knoptekst    = HDP_I18N::kies( $a['kaart3Knoptekst'], $a['kaart3KnoptekstFr'] );
		$kaart4_titel        = HDP_I18N::kies( $a['kaart4Titel'], $a['kaart4TitelFr'] );
		$kaart4_omschrijving = HDP_I18N::kies( $a['kaart4Omschrijving'], $a['kaart4OmschrijvingFr'] );
		$kaart4_knoptekst    = HDP_I18N::kies( $a['kaart4Knoptekst'], $a['kaart4KnoptekstFr'] );
		?>
		<div class="hdp-hero alignfull" style="background-image:url('<?php echo esc_url( $a['heroAfbeelding'] ); ?>')" aria-hidden="true"></div>
		<div class="hdp-portaal alignfull">
			<section class="hdp-welkom alignfull">
				<div class="hdp-welkom-inner">
					<div class="hdp-welkom-top">
						<h1><?php echo esc_html( HDP_I18N::t( 'welkom_prefix' ) ); ?> <?php echo esc_html( $user->display_name ); ?></h1>
						<a class="hdp-btn-logout" href="<?php echo esc_url( wp_logout_url( home_url( '/dealerportaal/' ) ) ); ?>"><?php echo self::svg_icoon( 'uitloggen' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?><?php echo esc_html( HDP_I18N::t( 'btn_uitloggen' ) ); ?></a>
					</div>
					<p><?php echo esc_html( $portaal_intro ); ?></p>
					<?php if ( $merken ) : ?>
						<p class="hdp-merken"><?php echo esc_html( HDP_I18N::t( 'geautoriseerd_voor' ) ); ?> <?php echo esc_html( $merken ); ?></p>
					<?php endif; ?>
				</div>
			</section>

			<section class="hdp-kaarten-sectie" aria-label="Portaalopties">
				<article class="hdp-kaart">
					<?php self::render_icoon( 'webshop' ); ?>
					<h2><?php echo esc_html( $kaart1_titel ); ?></h2>
					<p><?php echo esc_html( $kaart1_omschrijving ); ?></p>
					<?php if ( $webshop_url ) : ?>
						<a class="hdp-btn" href="<?php echo esc_url( $webshop_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $kaart1_knoptekst ); ?></a>
					<?php else : ?>
						<a class="hdp-btn" href="#"><?php echo esc_html( $kaart1_knoptekst ); ?></a>
					<?php endif; ?>
				</article>

				<article class="hdp-kaart">
					<?php self::render_icoon( 'configurator' ); ?>
					<h2><?php echo esc_html( $kaart2_titel ); ?></h2>
					<p><?php echo esc_html( $kaart2_omschrijving ); ?></p>
					<?php if ( $configurator_url ) : ?>
						<a class="hdp-btn" href="<?php echo esc_url( $configurator_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $kaart2_knoptekst ); ?></a>
					<?php else : ?>
						<p class="hdp-nog-niet"><?php echo esc_html( HDP_I18N::t( 'nog_niet_geconfigureerd' ) ); ?></p>
					<?php endif; ?>
				</article>

				<article class="hdp-kaart">
					<?php self::render_icoon( 'downloads' ); ?>
					<h2><?php echo esc_html( $kaart3_titel ); ?></h2>
					<p><?php echo esc_html( $kaart3_omschrijving ); ?></p>
					<a class="hdp-btn" href="<?php echo esc_url( home_url( '/downloads/' ) ); ?>"><?php echo esc_html( $kaart3_knoptekst ); ?></a>
				</article>

				<article class="hdp-kaart">
					<?php self::render_icoon( 'content' ); ?>
					<h2><?php echo esc_html( $kaart4_titel ); ?></h2>
					<p><?php echo esc_html( $kaart4_omschrijving ); ?></p>
					<a class="hdp-btn" href="<?php echo esc_url( home_url( '/content/' ) ); ?>"><?php echo esc_html( $kaart4_knoptekst ); ?></a>
				</article>
			</section>

			<?php self::render_info_sectie(); ?>
		</div>
		<?php
	}

	/**
	 * Aanvullende informatie (bestellen, contact magazijn, technische
	 * documentatie per merk) — vaste, zelden wijzigende inhoud, daarom
	 * hier als statische opmaak (via HDP_I18N::t()) in plaats van losse
	 * blokattributen.
	 */
	private static function render_info_sectie() {
		?>
		<section class="hdp-info-sectie" aria-label="Aanvullende informatie">
			<h2 class="hdp-info-titel"><?php echo esc_html( HDP_I18N::t( 'info_label' ) ); ?></h2>
			<div class="hdp-info-grid">

				<article class="hdp-info-kaart">
					<?php self::render_icoon( 'bestellen', 'hdp-info-icoon' ); ?>
					<div class="hdp-info-body">
						<h3><?php echo esc_html( HDP_I18N::t( 'info_bestellen_titel' ) ); ?></h3>
						<p><?php echo esc_html( HDP_I18N::t( 'info_bestellen_tekst' ) ); ?></p>
					</div>
				</article>

				<article class="hdp-info-kaart">
					<?php self::render_icoon( 'mail', 'hdp-info-icoon' ); ?>
					<div class="hdp-info-body">
						<h3><?php echo esc_html( HDP_I18N::t( 'info_contact_titel' ) ); ?></h3>
						<p><?php echo esc_html( HDP_I18N::t( 'info_contact_tekst' ) ); ?></p>
						<div class="hdp-info-links">
							<a class="hdp-info-link" href="mailto:mag@homburg-holland.com"><?php echo self::svg_icoon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?> mag@homburg-holland.com</a>
							<a class="hdp-info-link" href="tel:+31582045232"><?php echo self::svg_icoon( 'tel' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> +31 58 204 5232</a>
						</div>
					</div>
				</article>

				<article class="hdp-info-kaart">
					<?php self::render_icoon( 'technisch', 'hdp-info-icoon' ); ?>
					<div class="hdp-info-body">
						<h3><?php echo esc_html( HDP_I18N::t( 'info_technisch_titel' ) ); ?></h3>
						<p><?php echo esc_html( HDP_I18N::t( 'info_technisch_tekst' ) ); ?></p>
						<div class="hdp-info-links">
							<a class="hdp-info-link" href="https://www.agroparts.com" target="_blank" rel="noopener noreferrer"><?php echo self::svg_icoon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> agroparts.com</a>
							<a class="hdp-info-link" href="https://rabe-ersatzteile.de/" target="_blank" rel="noopener noreferrer"><?php echo self::svg_icoon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> Rabe Ersatzteilportal</a>
						</div>
					</div>
				</article>

				<article class="hdp-info-kaart">
					<?php self::render_icoon( 'bogballe', 'hdp-info-icoon' ); ?>
					<div class="hdp-info-body">
						<h3><?php echo esc_html( HDP_I18N::t( 'info_bogballe_titel' ) ); ?></h3>
						<p><?php echo esc_html( HDP_I18N::t( 'info_bogballe_tekst' ) ); ?></p>
						<div class="hdp-info-links">
							<a class="hdp-info-link" href="https://media.bogballe.com" target="_blank" rel="noopener noreferrer"><?php echo self::svg_icoon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> media.bogballe.com</a>
						</div>
					</div>
				</article>

				<article class="hdp-info-kaart">
					<?php self::render_icoon( 'vaderstad', 'hdp-info-icoon' ); ?>
					<div class="hdp-info-body">
						<h3><?php echo esc_html( HDP_I18N::t( 'info_vaderstad_titel' ) ); ?></h3>
						<p><?php echo esc_html( HDP_I18N::t( 'info_vaderstad_tekst' ) ); ?></p>
						<div class="hdp-info-links">
							<a class="hdp-info-link" href="https://www.vaderstad.com/en/support/parts-catalogue-online" target="_blank" rel="noopener noreferrer"><?php echo self::svg_icoon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> Väderstad parts catalogue</a>
						</div>
					</div>
				</article>

				<article class="hdp-info-kaart">
					<?php self::render_icoon( 'link', 'hdp-info-icoon' ); ?>
					<div class="hdp-info-body">
						<h3><?php echo esc_html( HDP_I18N::t( 'info_draincleaner_titel' ) ); ?></h3>
						<p>
							<?php echo esc_html( HDP_I18N::t( 'info_draincleaner_tekst' ) ); ?>
							<a class="hdp-info-link" href="https://www.homburg-holland.com/nl/downloads" target="_blank" rel="noopener noreferrer"><?php echo self::svg_icoon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( HDP_I18N::t( 'info_onze_website' ) ); ?></a>.
						</p>
					</div>
				</article>

			</div>
		</section>
		<?php
	}

	/**
	 * Eén centrale bron voor alle vaste SVG-iconen in het portaal (kaarten,
	 * infoboxen en de inline iconen bij contactlinks), zodat een icoon
	 * maar op één plek staat gedefinieerd.
	 */
	private static function icoon_paden() {
		return array(
			'webshop'      => '<circle cx="9" cy="21" r="1.5"/><circle cx="19" cy="21" r="1.5"/><path d="M2 3h3l2.6 12.5a1 1 0 0 0 1 .8h9.7a1 1 0 0 0 1-.8L21 7H6"/>',
			'configurator' => '<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1.03 1.56V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1.12-1.56 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.56-1.03H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.65 8.85a1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.7 1.7 0 0 0 1.87.34h.01A1.7 1.7 0 0 0 10.05 3V3a2 2 0 1 1 4 0v.09c0 .68.4 1.29 1.03 1.56a1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87v.01c.27.62.88 1.03 1.56 1.03H21a2 2 0 1 1 0 4h-.09c-.68 0-1.29.4-1.51 1z"/>',
			'downloads'    => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>',
			'content'      => '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
			'login'        => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
			'wachten'      => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
			'bestellen'    => '<path d="M20.59 13.41 11 3.83A2 2 0 0 0 9.59 3.24L4 3a1 1 0 0 0-1 1l.24 5.59a2 2 0 0 0 .59 1.41l9.58 9.58a2 2 0 0 0 2.83 0l4.35-4.34a2 2 0 0 0 0-2.83Z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
			'mail'         => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
			'tel'          => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
			'technisch'    => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94Z"/>',
			'bogballe'     => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/>',
			'vaderstad'    => '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2Z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7Z"/>',
			'link'         => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>',
			'gebruiker'    => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
			'uitloggen'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
		);
	}

	/** Los, ongewikkeld SVG-icoon voor inline gebruik (bijv. naast een link). */
	private static function svg_icoon( $type ) {
		$paden = self::icoon_paden();
		if ( ! isset( $paden[ $type ] ) ) {
			return '';
		}
		return '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $paden[ $type ] . '</svg>';
	}

	/**
	 * Icoon in zijn gebruikelijke badge-omhulsel. De portaalkaarten
	 * gebruiken een vierkante badge (.hdp-kaart-icoon), de infoboxen een
	 * ronde (.hdp-info-icoon) — bewust verschillend zodat de infoboxen er
	 * duidelijk anders uitzien dan de drie hoofdkaarten.
	 */
	private static function render_icoon( $type, $badge_class = 'hdp-kaart-icoon' ) {
		$svg = self::svg_icoon( $type );
		if ( '' === $svg ) {
			return;
		}
		?>
		<div class="<?php echo esc_attr( $badge_class ); ?>" aria-hidden="true"><?php echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG-paden (geen gebruikersinvoer). ?></div>
		<?php
	}

	/**
	 * $categorie bepaalt of dit de technische downloads ('download': prijs-
	 * lijsten, handleidingen, e.d.) of de socialmedia-/advertentiecontent
	 * ('content') toont. Bestaande downloads van vóór dit onderscheid
	 * hebben geen _hdp_categorie-meta; die tellen mee als 'download'.
	 */
	private static function render_downloads_lijst( $categorie = 'download' ) {
		$meta_query = 'content' === $categorie
			? array( array( 'key' => '_hdp_categorie', 'value' => 'content' ) )
			: array(
				'relation' => 'OR',
				array( 'key' => '_hdp_categorie', 'value' => 'download' ),
				array( 'key' => '_hdp_categorie', 'compare' => 'NOT EXISTS' ),
			);

		$downloads = get_posts(
			array(
				'post_type'      => HDP_Downloads_CPT::POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- kleine, beheerste dataset (dealerdownloads), geen gebruikersinvoer.
			)
		);

		if ( ! $downloads ) {
			$leeg_sleutel = 'content' === $categorie ? 'nog_geen_content' : 'nog_geen_downloads';
			return '<p class="hdp-nog-niet">' . esc_html( HDP_I18N::t( $leeg_sleutel ) ) . '</p>';
		}

		// Merken voor de filterchips worden automatisch afgeleid uit de
		// downloads zelf — als Homburg later een nieuw merk invult bij een
		// download, verschijnt de chip vanzelf, zonder codewijziging.
		$merken = array();
		foreach ( $downloads as $download ) {
			$merk = HDP_Downloads_CPT::get_merk( $download->ID );
			if ( $merk && ! in_array( $merk, $merken, true ) ) {
				$merken[] = $merk;
			}
		}
		sort( $merken );

		ob_start();
		?>
		<div class="hdp-dl-werkbalk">
			<div class="hdp-zoekveld">
				<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
				<input type="text" id="hdp-zoeken" placeholder="<?php echo esc_attr( HDP_I18N::t( 'zoek_placeholder' ) ); ?>" autocomplete="off">
			</div>
			<div class="hdp-filterrij">
				<div class="hdp-filtergroep">
					<span class="hdp-filtergroep-label"><?php echo esc_html( HDP_I18N::t( 'filter_regio' ) ); ?></span>
					<div class="hdp-chips" id="hdp-regio-chips">
						<button type="button" class="hdp-chip hdp-chip-actief" data-regio="alle"><?php echo esc_html( HDP_I18N::t( 'filter_alles' ) ); ?></button>
						<button type="button" class="hdp-chip" data-regio="nl"><?php echo esc_html( HDP_I18N::t( 'filter_nederland' ) ); ?></button>
						<button type="button" class="hdp-chip" data-regio="be"><?php echo esc_html( HDP_I18N::t( 'filter_belgie' ) ); ?></button>
					</div>
				</div>
				<?php if ( $merken ) : ?>
				<div class="hdp-filtergroep">
					<span class="hdp-filtergroep-label"><?php echo esc_html( HDP_I18N::t( 'filter_merk' ) ); ?></span>
					<div class="hdp-chips" id="hdp-merk-chips">
						<?php foreach ( $merken as $merk ) : ?>
							<button type="button" class="hdp-chip" data-merk="<?php echo esc_attr( $merk ); ?>"><?php echo esc_html( $merk ); ?></button>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>
				<div class="hdp-filtergroep">
					<span class="hdp-filtergroep-label"><?php echo esc_html( HDP_I18N::t( 'sorteren_label' ) ); ?></span>
					<select id="hdp-sorteer" class="hdp-sorteer-select">
						<option value="datum-nieuw"><?php echo esc_html( HDP_I18N::t( 'sorteer_nieuw' ) ); ?></option>
						<option value="datum-oud"><?php echo esc_html( HDP_I18N::t( 'sorteer_oud' ) ); ?></option>
						<option value="naam-az"><?php echo esc_html( HDP_I18N::t( 'sorteer_naam' ) ); ?></option>
					</select>
				</div>
			</div>
			<div class="hdp-filterrij-onder">
				<p class="hdp-telling" aria-live="polite" aria-atomic="true"><strong id="hdp-telling-zichtbaar"><?php echo count( $downloads ); ?></strong> <?php echo esc_html( HDP_I18N::t( 'telling_van' ) ); ?> <?php echo count( $downloads ); ?> <?php echo esc_html( HDP_I18N::t( 'telling_zichtbaar' ) ); ?></p>
				<button type="button" class="hdp-wis-filters" id="hdp-wis-filters"><?php echo esc_html( HDP_I18N::t( 'wis_filters' ) ); ?></button>
			</div>
		</div>

		<div class="hdp-download-lijst" id="hdp-download-lijst">
			<?php foreach ( $downloads as $download ) : ?>
				<?php
				$merk   = HDP_Downloads_CPT::get_merk( $download->ID );
				$regios = HDP_Downloads_CPT::get_regios( $download->ID );
				?>
				<div class="hdp-download-item"
					data-titel="<?php echo esc_attr( strtolower( $download->post_title ) ); ?>"
					data-merk="<?php echo esc_attr( $merk ); ?>"
					data-regios="<?php echo esc_attr( implode( ' ', $regios ) ); ?>"
					data-datum="<?php echo esc_attr( get_post_time( 'U', true, $download ) ); ?>">
					<span class="hdp-download-type"><?php echo esc_html( HDP_Downloads_CPT::type_label( $download->ID ) ); ?></span>
					<span class="hdp-download-info">
						<strong><?php echo esc_html( $download->post_title ); ?></strong>
						<?php if ( $download->post_content ) : ?>
							<span><?php echo esc_html( wp_strip_all_tags( $download->post_content ) ); ?></span>
						<?php endif; ?>
					</span>
					<a class="hdp-btn" href="<?php echo esc_url( HDP_Downloads_CPT::download_url( $download->ID ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'btn_downloaden' ) ); ?></a>
				</div>
			<?php endforeach; ?>
		</div>

		<p class="hdp-leeg-resultaat" id="hdp-leeg-resultaat"><?php echo esc_html( HDP_I18N::t( 'leeg_resultaat' ) ); ?></p>

		<script>
		(function () {
			var zoekveld = document.getElementById('hdp-zoeken');
			var lijst = document.getElementById('hdp-download-lijst');
			var items = lijst ? lijst.querySelectorAll('.hdp-download-item') : [];
			var tellingEl = document.getElementById('hdp-telling-zichtbaar');
			var leegEl = document.getElementById('hdp-leeg-resultaat');
			var regioChips = document.getElementById('hdp-regio-chips');
			var merkChips = document.getElementById('hdp-merk-chips');
			var wisKnop = document.getElementById('hdp-wis-filters');
			var sorteerSelect = document.getElementById('hdp-sorteer');
			var actieveRegio = 'alle';
			var actieveMerken = [];

			function sorteerToepassen() {
				if (!sorteerSelect || !lijst) { return; }
				var volgorde = sorteerSelect.value;
				Array.prototype.slice.call(items).sort(function (a, b) {
					if (volgorde === 'naam-az') { return a.dataset.titel.localeCompare(b.dataset.titel); }
					var datumA = parseInt(a.dataset.datum, 10);
					var datumB = parseInt(b.dataset.datum, 10);
					return volgorde === 'datum-oud' ? datumA - datumB : datumB - datumA;
				}).forEach(function (item) { lijst.appendChild(item); });
			}

			if (sorteerSelect) { sorteerSelect.addEventListener('change', sorteerToepassen); }

			function filterToepassen() {
				var zoekterm = zoekveld ? zoekveld.value.trim().toLowerCase() : '';
				var zichtbaar = 0;
				items.forEach(function (item) {
					var voldoetZoek = !zoekterm || item.dataset.titel.indexOf(zoekterm) !== -1;
					var voldoetRegio = actieveRegio === 'alle' || item.dataset.regios.split(' ').indexOf(actieveRegio) !== -1;
					var voldoetMerk = actieveMerken.length === 0 || actieveMerken.indexOf(item.dataset.merk) !== -1;
					var zicht = voldoetZoek && voldoetRegio && voldoetMerk;
					item.style.display = zicht ? '' : 'none';
					if (zicht) { zichtbaar++; }
				});
				if (tellingEl) { tellingEl.textContent = zichtbaar; }
				if (leegEl) { leegEl.classList.toggle('hdp-zichtbaar', zichtbaar === 0); }
			}

			if (zoekveld) { zoekveld.addEventListener('input', filterToepassen); }

			if (regioChips) {
				regioChips.addEventListener('click', function (e) {
					var chip = e.target.closest('.hdp-chip');
					if (!chip) { return; }
					actieveRegio = chip.dataset.regio;
					regioChips.querySelectorAll('.hdp-chip').forEach(function (c) { c.classList.remove('hdp-chip-actief'); });
					chip.classList.add('hdp-chip-actief');
					filterToepassen();
				});
			}

			if (merkChips) {
				merkChips.addEventListener('click', function (e) {
					var chip = e.target.closest('.hdp-chip');
					if (!chip) { return; }
					var merk = chip.dataset.merk;
					var idx = actieveMerken.indexOf(merk);
					if (idx === -1) { actieveMerken.push(merk); chip.classList.add('hdp-chip-actief'); }
					else { actieveMerken.splice(idx, 1); chip.classList.remove('hdp-chip-actief'); }
					filterToepassen();
				});
			}

			if (wisKnop) {
				wisKnop.addEventListener('click', function () {
					if (zoekveld) { zoekveld.value = ''; }
					actieveRegio = 'alle';
					actieveMerken = [];
					document.querySelectorAll('.hdp-chip').forEach(function (c) { c.classList.remove('hdp-chip-actief'); });
					var alles = regioChips ? regioChips.querySelector('[data-regio="alle"]') : null;
					if (alles) { alles.classList.add('hdp-chip-actief'); }
					filterToepassen();
				});
			}
		})();
		</script>
		<?php
		return ob_get_clean();
	}
}
