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
 */
class HDP_Blocks {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'registreer_blokken' ) );
		add_action( 'init', array( __CLASS__, 'verwerk_login' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function registreer_blokken() {
		register_block_type( HDP_PLUGIN_DIR . 'blocks/dealerportaal' );
		register_block_type( HDP_PLUGIN_DIR . 'blocks/downloads' );
	}

	public static function enqueue_assets() {
		$post = get_post();
		if ( ! $post || ( ! has_block( 'homburg/dealerportaal', $post ) && ! has_block( 'homburg/downloads-pagina', $post ) ) ) {
			return;
		}

		wp_enqueue_style( 'hdp-dealerportaal', HDP_PLUGIN_URL . 'assets/css/dealerportaal.css', array(), HDP_VERSION );
	}

	public static function verwerk_login() {
		if ( ! isset( $_POST['hdp_login_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['hdp_login_nonce'] ), 'hdp_login' ) ) {
			return;
		}

		$gebruikersnaam = isset( $_POST['gebruikersnaam'] ) ? trim( wp_unslash( $_POST['gebruikersnaam'] ) ) : '';
		$wachtwoord     = isset( $_POST['wachtwoord'] ) ? (string) $_POST['wachtwoord'] : '';

		$resultaat = wp_signon(
			array(
				'user_login'    => $gebruikersnaam,
				'user_password' => $wachtwoord,
				'remember'      => true,
			),
			is_ssl()
		);

		$terug_naar = wp_get_referer();
		if ( ! $terug_naar ) {
			$terug_naar = home_url( '/dealerportaal/' );
		}

		if ( is_wp_error( $resultaat ) ) {
			wp_safe_redirect( add_query_arg( 'hdp_fout', '1', remove_query_arg( 'hdp_fout', $terug_naar ) ) );
		} else {
			wp_safe_redirect( remove_query_arg( 'hdp_fout', $terug_naar ) );
		}
		exit;
	}

	public static function render_dealerportaal( $a ) {
		ob_start();

		if ( is_user_logged_in() ) {
			self::render_portal_content( $a );
		} else {
			$fout = isset( $_GET['hdp_fout'] ) ? 'Onjuiste gebruikersnaam of wachtwoord. Probeer het opnieuw.' : '';
			self::render_login( $fout, $a );
		}

		return ob_get_clean();
	}

	public static function render_downloads_pagina( $a ) {
		$mag_zien = is_user_logged_in() && HDP_Roles::mag_portaal_zien( get_current_user_id() );

		if ( ! $mag_zien ) {
			ob_start();
			self::render_login( '', $a );
			return ob_get_clean();
		}

		ob_start();
		?>
		<div class="hdp-portaal alignfull">
			<section class="hdp-welkom alignfull hdp-welkom-zonder-hero">
				<div class="hdp-welkom-inner">
					<div class="hdp-welkom-top">
						<h1><?php echo esc_html( $a['titel'] ); ?></h1>
						<a class="hdp-btn-logout" href="<?php echo esc_url( wp_logout_url( home_url( '/dealerportaal/' ) ) ); ?>">Uitloggen</a>
					</div>
					<p><?php echo esc_html( $a['omschrijving'] ); ?></p>
				</div>
			</section>
			<section class="hdp-downloads">
				<?php echo self::render_downloads_lijst(); // phpcs:ignore WordPress.Security.EscapeOutput -- reeds ge-escaped in render_downloads_lijst(). ?>
				<p class="hdp-terug"><a class="hdp-btn hdp-btn-secundair" href="<?php echo esc_url( home_url( '/dealerportaal/' ) ); ?>">&larr; Terug naar het portaal</a></p>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_login( $fout, $a ) {
		?>
		<div class="hdp-hero alignfull" style="background-image:url('<?php echo esc_url( $a['heroAfbeelding'] ); ?>')" aria-hidden="true"></div>
		<div class="hdp-login-sectie">
			<div class="hdp-login-kaart">
				<h1>Inloggen dealerportaal</h1>
				<p class="hdp-intro"><?php echo esc_html( $a['loginIntro'] ); ?></p>

				<?php if ( $fout ) : ?>
					<div class="hdp-login-fout hdp-zichtbaar" role="alert"><?php echo esc_html( $fout ); ?></div>
				<?php endif; ?>

				<form method="post">
					<?php wp_nonce_field( 'hdp_login', 'hdp_login_nonce' ); ?>
					<div class="hdp-veld">
						<label for="gebruikersnaam">Gebruikersnaam</label>
						<input type="text" id="gebruikersnaam" name="gebruikersnaam" autocomplete="username" required>
					</div>
					<div class="hdp-veld">
						<label for="wachtwoord">Wachtwoord</label>
						<input type="password" id="wachtwoord" name="wachtwoord" autocomplete="current-password" required>
					</div>
					<button type="submit" class="hdp-btn">Inloggen</button>
				</form>
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
					<h1>Account in behandeling</h1>
					<p class="hdp-intro">Uw account is nog niet goedgekeurd voor het dealerportaal. Neem contact op met Homburg Machinehandel.</p>
					<a class="hdp-btn hdp-btn-secundair" href="<?php echo esc_url( wp_logout_url( home_url( '/dealerportaal/' ) ) ); ?>">Uitloggen</a>
				</div>
			</div>
			<?php
			return;
		}

		$merken           = get_user_meta( $user->ID, 'hdp_merken', true );
		$webshop_url      = HDP_Settings::get( 'webshop_url' );
		$configurator_url = HDP_Settings::get( 'configurator_url' );
		?>
		<div class="hdp-hero alignfull" style="background-image:url('<?php echo esc_url( $a['heroAfbeelding'] ); ?>')" aria-hidden="true"></div>
		<div class="hdp-portaal alignfull">
			<section class="hdp-welkom alignfull">
				<div class="hdp-welkom-inner">
					<div class="hdp-welkom-top">
						<h1>Welkom, <?php echo esc_html( $user->display_name ); ?></h1>
						<a class="hdp-btn-logout" href="<?php echo esc_url( wp_logout_url( home_url( '/dealerportaal/' ) ) ); ?>">Uitloggen</a>
					</div>
					<p><?php echo esc_html( $a['portaalIntro'] ); ?></p>
					<?php if ( $merken ) : ?>
						<p class="hdp-merken">Geautoriseerd voor: <?php echo esc_html( $merken ); ?></p>
					<?php endif; ?>
				</div>
			</section>

			<section class="hdp-kaarten-sectie" aria-label="Portaalopties">
				<article class="hdp-kaart">
					<?php self::render_icoon( 'webshop' ); ?>
					<h2><?php echo esc_html( $a['kaart1Titel'] ); ?></h2>
					<p><?php echo esc_html( $a['kaart1Omschrijving'] ); ?></p>
					<?php if ( $webshop_url ) : ?>
						<a class="hdp-btn" href="<?php echo esc_url( $webshop_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $a['kaart1Knoptekst'] ); ?></a>
					<?php else : ?>
						<a class="hdp-btn" href="#"><?php echo esc_html( $a['kaart1Knoptekst'] ); ?></a>
					<?php endif; ?>
				</article>

				<article class="hdp-kaart">
					<?php self::render_icoon( 'configurator' ); ?>
					<h2><?php echo esc_html( $a['kaart2Titel'] ); ?></h2>
					<p><?php echo esc_html( $a['kaart2Omschrijving'] ); ?></p>
					<?php if ( $configurator_url ) : ?>
						<a class="hdp-btn" href="<?php echo esc_url( $configurator_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $a['kaart2Knoptekst'] ); ?></a>
					<?php else : ?>
						<p class="hdp-nog-niet">Nog niet geconfigureerd</p>
					<?php endif; ?>
				</article>

				<article class="hdp-kaart">
					<?php self::render_icoon( 'downloads' ); ?>
					<h2><?php echo esc_html( $a['kaart3Titel'] ); ?></h2>
					<p><?php echo esc_html( $a['kaart3Omschrijving'] ); ?></p>
					<a class="hdp-btn" href="<?php echo esc_url( home_url( '/downloads/' ) ); ?>"><?php echo esc_html( $a['kaart3Knoptekst'] ); ?></a>
				</article>
			</section>

			<?php self::render_info_sectie(); ?>
		</div>
		<?php
	}

	/**
	 * Aanvullende informatie (bestellen, contact magazijn, technische
	 * documentatie per merk) — vaste, zelden wijzigende inhoud, daarom
	 * hier als statische opmaak in plaats van losse blokattributen.
	 */
	private static function render_info_sectie() {
		$mail_icoon = '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>';
		$tel_icoon  = '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></svg>';
		$link_icoon = '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';
		?>
		<section class="hdp-info-sectie" aria-label="Aanvullende informatie">
			<h2 class="hdp-info-titel">Overige informatie</h2>
			<div class="hdp-info-grid">

				<article class="hdp-info-kaart">
					<div class="hdp-info-icoon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41 11 3.83A2 2 0 0 0 9.59 3.24L4 3a1 1 0 0 0-1 1l.24 5.59a2 2 0 0 0 .59 1.41l9.58 9.58a2 2 0 0 0 2.83 0l4.35-4.34a2 2 0 0 0 0-2.83Z"/><circle cx="7.5" cy="7.5" r="1.5"/></svg></div>
					<div class="hdp-info-body">
						<h3>Bestellen en levertijden</h3>
						<p>Prijzen die niet zichtbaar zijn via deze dealerlogin kunnen opgevraagd worden via ons magazijn. Prijslijsten per merk zijn beschikbaar onder het onderdeel "verkoopdocumenten".</p>
					</div>
				</article>

				<article class="hdp-info-kaart">
					<div class="hdp-info-icoon" aria-hidden="true"><?php echo $mail_icoon; // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?></div>
					<div class="hdp-info-body">
						<h3>Contact magazijn</h3>
						<p>Bereikbaar via e-mail of telefonisch.</p>
						<div class="hdp-info-links">
							<a class="hdp-info-link" href="mailto:mag@homburg-holland.com"><?php echo $mail_icoon; // phpcs:ignore WordPress.Security.EscapeOutput ?> mag@homburg-holland.com</a>
							<a class="hdp-info-link" href="tel:+31582045232"><?php echo $tel_icoon; // phpcs:ignore WordPress.Security.EscapeOutput ?> +31 58 204 5232</a>
						</div>
					</div>
				</article>

				<article class="hdp-info-kaart">
					<div class="hdp-info-icoon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94Z"/></svg></div>
					<div class="hdp-info-body">
						<h3>(Technische) informatie</h3>
						<p>Voor HARDI en oudere machines van Rabe.</p>
						<div class="hdp-info-links">
							<a class="hdp-info-link" href="https://www.agroparts.com" target="_blank" rel="noopener noreferrer"><?php echo $link_icoon; // phpcs:ignore WordPress.Security.EscapeOutput ?> agroparts.com</a>
							<a class="hdp-info-link" href="https://rabe-ersatzteile.de/" target="_blank" rel="noopener noreferrer"><?php echo $link_icoon; // phpcs:ignore WordPress.Security.EscapeOutput ?> Rabe Ersatzteilportal</a>
						</div>
					</div>
				</article>

				<article class="hdp-info-kaart">
					<div class="hdp-info-icoon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/></svg></div>
					<div class="hdp-info-body">
						<h3>Bogballe onderdelen</h3>
						<p>Documentatie en onderdelen voor Bogballe-strooiers.</p>
						<div class="hdp-info-links">
							<a class="hdp-info-link" href="https://media.bogballe.com" target="_blank" rel="noopener noreferrer"><?php echo $link_icoon; // phpcs:ignore WordPress.Security.EscapeOutput ?> media.bogballe.com</a>
						</div>
					</div>
				</article>

				<article class="hdp-info-kaart">
					<div class="hdp-info-icoon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2Z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7Z"/></svg></div>
					<div class="hdp-info-body">
						<h3>Väderstad onderdelen</h3>
						<p>Gedetailleerde informatie en onderdelen voor Väderstad-machines.</p>
						<div class="hdp-info-links">
							<a class="hdp-info-link" href="https://www.vaderstad.com/en/support/parts-catalogue-online" target="_blank" rel="noopener noreferrer"><?php echo $link_icoon; // phpcs:ignore WordPress.Security.EscapeOutput ?> Väderstad parts catalogue</a>
						</div>
					</div>
				</article>

				<article class="hdp-info-kaart">
					<div class="hdp-info-icoon" aria-hidden="true"><?php echo $link_icoon; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<div class="hdp-info-body">
						<h3>Draincleaner onderdelen</h3>
						<p>Onderdelenboeken van de Homburg Draincleaners, te vinden bij het onderdeel "Support".</p>
						<div class="hdp-info-links">
							<a class="hdp-info-link" href="https://www.homburg-holland.com/nl/downloads" target="_blank" rel="noopener noreferrer"><?php echo $link_icoon; // phpcs:ignore WordPress.Security.EscapeOutput ?> Naar onze website</a>
						</div>
					</div>
				</article>

			</div>
		</section>
		<?php
	}

	/**
	 * De kaart-iconen uit het oorspronkelijke HTML-ontwerp (dealeromgeving
	 * opzet.html), 1-op-1 overgenomen zodat de portaalkaarten er precies
	 * zo uitzien als het goedgekeurde ontwerp.
	 */
	private static function render_icoon( $type ) {
		$paden = array(
			'webshop'      => '<circle cx="9" cy="21" r="1.5"/><circle cx="19" cy="21" r="1.5"/><path d="M2 3h3l2.6 12.5a1 1 0 0 0 1 .8h9.7a1 1 0 0 0 1-.8L21 7H6"/>',
			'configurator' => '<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1.03 1.56V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1.12-1.56 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.56-1.03H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.65 8.85a1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.7 1.7 0 0 0 1.87.34h.01A1.7 1.7 0 0 0 10.05 3V3a2 2 0 1 1 4 0v.09c0 .68.4 1.29 1.03 1.56a1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87v.01c.27.62.88 1.03 1.56 1.03H21a2 2 0 1 1 0 4h-.09c-.68 0-1.29.4-1.51 1z"/>',
			'downloads'    => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>',
		);

		if ( ! isset( $paden[ $type ] ) ) {
			return;
		}
		?>
		<div class="hdp-kaart-icoon" aria-hidden="true">
			<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo $paden[ $type ]; // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG-paden (geen gebruikersinvoer). ?></svg>
		</div>
		<?php
	}

	private static function render_downloads_lijst() {
		$downloads = get_posts(
			array(
				'post_type'      => HDP_Downloads_CPT::POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
			)
		);

		if ( ! $downloads ) {
			return '<p class="hdp-nog-niet">Nog geen downloads beschikbaar</p>';
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
				<input type="text" id="hdp-zoeken" placeholder="Zoek op bestandsnaam…" autocomplete="off">
			</div>
			<div class="hdp-filterrij">
				<div class="hdp-filtergroep">
					<span class="hdp-filtergroep-label">Regio</span>
					<div class="hdp-chips" id="hdp-regio-chips">
						<button type="button" class="hdp-chip hdp-chip-actief" data-regio="alle">Alles</button>
						<button type="button" class="hdp-chip" data-regio="nl">Nederland</button>
						<button type="button" class="hdp-chip" data-regio="be">België</button>
					</div>
				</div>
				<?php if ( $merken ) : ?>
				<div class="hdp-filtergroep">
					<span class="hdp-filtergroep-label">Merk</span>
					<div class="hdp-chips" id="hdp-merk-chips">
						<?php foreach ( $merken as $merk ) : ?>
							<button type="button" class="hdp-chip" data-merk="<?php echo esc_attr( $merk ); ?>"><?php echo esc_html( $merk ); ?></button>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>
			</div>
			<div class="hdp-filterrij-onder">
				<p class="hdp-telling"><strong id="hdp-telling-zichtbaar"><?php echo count( $downloads ); ?></strong> van <?php echo count( $downloads ); ?> downloads zichtbaar</p>
				<button type="button" class="hdp-wis-filters" id="hdp-wis-filters">Filters wissen</button>
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
					data-regios="<?php echo esc_attr( implode( ' ', $regios ) ); ?>">
					<span class="hdp-download-type"><?php echo esc_html( HDP_Downloads_CPT::type_label( $download->ID ) ); ?></span>
					<span class="hdp-download-info">
						<strong><?php echo esc_html( $download->post_title ); ?></strong>
						<?php if ( $download->post_content ) : ?>
							<span><?php echo esc_html( wp_strip_all_tags( $download->post_content ) ); ?></span>
						<?php endif; ?>
					</span>
					<a class="hdp-btn" href="<?php echo esc_url( HDP_Downloads_CPT::download_url( $download->ID ) ); ?>">Downloaden</a>
				</div>
			<?php endforeach; ?>
		</div>

		<p class="hdp-leeg-resultaat" id="hdp-leeg-resultaat">Geen downloads gevonden voor deze combinatie van filters.</p>

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
			var actieveRegio = 'alle';
			var actieveMerken = [];

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
