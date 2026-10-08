<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Zorgt dat het bestand áchter een download niet buiten het portaal om op
 * te halen is.
 *
 * Het probleem: een download is een gewone WordPress-bijlage, en die staat
 * in de openbare uploadsmap. De inlogcontrole van HDP_Downloads_CPT geldt
 * alleen voor de eigen URL (?hdp_download=123) — wie de directe bestands-URL
 * kent (of hem opzoekt via /wp-json/wp/v2/media, dat standaard voor iedereen
 * open staat) haalt een dealerprijslijst zonder in te loggen op.
 *
 * Drie lagen, want op één maatregel wil je dit niet laten rusten:
 *
 *  1. Het bestand verhuist naar een aparte submap met een .htaccess die
 *     directe toegang weigert. Dat is de echte afscherming, maar werkt
 *     alleen op Apache/LiteSpeed (de live server is cPanel, dus goed);
 *     nginx negeert .htaccess.
 *  2. wp_get_attachment_url() geeft voor zo'n bestand niet meer het pad in
 *     de uploadsmap terug maar de eigen, gecontroleerde download-URL. Alles
 *     wat WordPress ergens laat zien (mediabibliotheek, REST, een per
 *     ongeluk gedeelde link) wijst daarmee naar de versie mét controle.
 *     Deze laag werkt op élke server.
 *  3. De bijlagen verdwijnen uit de openbare REST-lijst, zodat ze niet meer
 *     simpelweg op te sommen zijn.
 *
 * Ook de door WordPress gegenereerde voorbeeldformaten worden verwijderd:
 * van een PDF maakt WordPress een JPG van de eerste pagina, en dat is bij
 * een prijslijst precies de pagina die je niet openbaar wilt hebben.
 */
class HDP_Bestandsbeveiliging {

	/** Submap binnen wp-content/uploads waar beveiligde bestanden staan. */
	const MAP = 'hdp-beveiligd';

	/** Bijlage-meta die aangeeft dat dit bestand verplaatst en afgeschermd is. */
	const META = '_hdp_beveiligd';

	/** Optie waarin bijgehouden wordt of de eenmalige verhuizing al gedraaid heeft. */
	const MIGRATIE_OPTIE = 'hdp_bestanden_beveiligd';

	/** Uitkomst van de laatste zelfcontrole: 'afgeschermd' | 'openbaar' | 'onbekend'. */
	const CONTROLE_OPTIE = 'hdp_beveiliging_controle';

	/** Bestandsnaam van het testbestand waarmee de afscherming gecontroleerd wordt. */
	const CONTROLE_BESTAND = 'hdp-controle.txt';

	/** Uitkomst van de controle op de uploadsmap: 'dicht' | 'open' | 'stuk' | 'onbekend'. */
	const UPLOADS_CONTROLE_OPTIE = 'hdp_uploads_controle';

	/**
	 * Bestanden in de uploadsmap die ook zonder inloggen bereikbaar moeten
	 * blijven, want anders breekt het inlogscherm zelf: het logo, de favicon
	 * en het merklogo op de winkelpagina. (De sfeerfoto op de achtergrond
	 * hoort hier niet bij: die komt van homburg-belgium.com en staat dus niet
	 * in deze map.)
	 *
	 * Deze lijst is gemeten aan de uitgelogde pagina's, niet gegokt. Komt er
	 * een afbeelding bij op het inlogscherm, dan hoort die hier ook bij —
	 * controleer_uploads_afscherming() merkt het als dat vergeten wordt.
	 */
	const OPENBARE_BESTANDEN = array(
		'Logo-HOMBURG',
		'Godin-druppel-klein',
		'dc',
	);

	/** Bijlage-ID => download-ID, zodat de URL-filter niet per aanroep de database bevraagt. */
	private static $download_cache = array();

	public static function init() {
		// Een bestand wordt "beveiligd" op het moment dat het aan een
		// download gekoppeld raakt — of dat nu met de hand in wp-admin
		// gebeurt of automatisch via de FileBird-koppeling. Meehaken op de
		// meta zelf vangt beide, zonder dat die twee klassen hier iets van
		// hoeven te weten.
		add_action( 'added_post_meta', array( __CLASS__, 'on_meta_gewijzigd' ), 10, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'on_meta_gewijzigd' ), 10, 4 );

		add_filter( 'wp_get_attachment_url', array( __CLASS__, 'filter_attachment_url' ), 10, 2 );
		add_filter( 'rest_attachment_query', array( __CLASS__, 'filter_rest_query' ), 10, 2 );
		add_filter( 'rest_prepare_attachment', array( __CLASS__, 'filter_rest_item' ), 10, 3 );

		if ( is_admin() ) {
			add_action( 'admin_init', array( __CLASS__, 'migreer_bestaande_downloads' ) );
			add_action( 'admin_init', array( __CLASS__, 'schrijf_uploads_afscherming' ) );
			add_action( 'admin_init', array( __CLASS__, 'controleer_afscherming' ) );
			add_action( 'admin_init', array( __CLASS__, 'controleer_uploads_afscherming' ) );
			add_action( 'admin_notices', array( __CLASS__, 'toon_waarschuwing' ) );
		}
	}

	// --- Laag 2: de hele uploadsmap dicht voor wie niet is ingelogd ------
	//
	// Laag 1 (hierboven) haalt prijslijsten en handleidingen uit de openbare
	// map en laat ze alleen via PHP uit, mét controle op wie je bent. Dat is
	// waterdicht maar kost een PHP-aanroep per bestand — voor een winkel vol
	// productfoto's is dat te traag.
	//
	// Deze laag doet het andersom: de webserver kijkt zelf of er een
	// inlogkoekje meekomt. Geen koekje, dan weigert hij meteen, zonder PHP en
	// dus zonder snelheidsverlies. Dat stopt waar het om gaat — iemand met een
	// link, een zoekmachine, een crawler.
	//
	// Wat het níét doet: controleren of dat koekje echt is. Wie er bewust een
	// verzint komt erlangs. Daarom blijft laag 1 bestaan voor de bestanden
	// waar dat wél moet kloppen.
	//
	//

	/**
	 * Zet (of actualiseert) de .htaccess in de uploadsmap.
	 *
	 * Wordt bij elk bezoek aan wp-admin nagelopen in plaats van één keer
	 * weggeschreven: zo herstelt hij zichzelf als er iets mee gebeurt, en
	 * komt een gewijzigde witte lijst vanzelf mee met een deploy.
	 */
	public static function schrijf_uploads_afscherming() {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return;
		}

		$pad     = trailingslashit( $uploads['basedir'] ) . '.htaccess';
		$gewenst = self::uploads_regels();

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- WP_Filesystem is hier niet geladen; zelfde aanpak als HDP_Log.
		$huidig = file_exists( $pad ) ? (string) @file_get_contents( $pad ) : '';
		if ( $huidig === $gewenst ) {
			return;
		}

		@file_put_contents( $pad, $gewenst ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- zie hierboven.
	}

	/** De inhoud van die .htaccess. */
	private static function uploads_regels() {
		$openbaar = implode( '|', array_map( 'preg_quote', self::OPENBARE_BESTANDEN ) );

		return "# Geschreven door de dealerportaal-plugin (HDP_Bestandsbeveiliging).\n"
			. "# Handmatige wijzigingen worden overschreven; pas OPENBARE_BESTANDEN aan.\n"
			. "#\n"
			. "# Alles in deze map is alleen voor ingelogde dealers, op een paar\n"
			. "# bestanden na die het inlogscherm zelf nodig heeft.\n"
			. "<IfModule mod_rewrite.c>\n"
			. "RewriteEngine On\n"
			. "\n"
			. "# 1. Wat het inlogscherm nodig heeft blijft openbaar.\n"
			// (^|/) ankert op het begin van de bestandsnaam. Zonder dat zou een
			// korte term als "dc" ook elk ander bestand doorlaten dat die
			// letters ergens in de naam heeft staan.
			. 'RewriteRule (^|/)(' . $openbaar . ')[^/]*$ - [L]' . "\n"
			. "\n"
			. "# 2. Komt er een inlogkoekje mee, dan gewoon uitleveren.\n"
			. "RewriteCond %{HTTP_COOKIE} wordpress_logged_in_ [NC]\n"
			. "RewriteRule ^ - [L]\n"
			. "\n"
			. "# 3. Al het andere: geen toegang.\n"
			. "RewriteRule ^ - [F,L]\n"
			. "</IfModule>\n";
	}

	/**
	 * Controleert of die afscherming doet wat hij moet.
	 *
	 * Drie dingen: komt een gewoon bestand er niet door zonder inloggen,
	 * komen de bestanden van het inlogscherm er wél door, en — het
	 * belangrijkste — staat er op dat inlogscherm niets dat nu stuk is. Dat
	 * laatste vangt de fout die je anders pas van een dealer hoort: een
	 * afbeelding toegevoegd en de witte lijst vergeten.
	 */
	public static function controleer_uploads_afscherming() {
		if ( get_transient( 'hdp_uploads_controle_gedaan' ) ) {
			return;
		}
		set_transient( 'hdp_uploads_controle_gedaan', 1, DAY_IN_SECONDS );

		$gebroken = self::gebroken_op_inlogscherm();

		if ( null === $gebroken ) {
			update_option( self::UPLOADS_CONTROLE_OPTIE, 'onbekend' );
			return;
		}

		if ( $gebroken ) {
			update_option( self::UPLOADS_CONTROLE_OPTIE, 'stuk' );
			HDP_Log::schrijf(
				'Uploadsafscherming: deze bestanden zijn op het inlogscherm niet meer op te halen: '
					. implode( ', ', $gebroken ) . '. Vul ze aan in OPENBARE_BESTANDEN.',
				'WAARSCHUWING'
			);
			return;
		}

		update_option( self::UPLOADS_CONTROLE_OPTIE, 'dicht' );
	}

	/**
	 * Haalt het inlogscherm op zoals een uitgelogde bezoeker dat ziet, en
	 * kijkt welke afbeeldingen daaruit niet meer bereikbaar zijn.
	 *
	 * @return array|null Lijst gebroken bestanden, of null als de controle
	 *                    zelf niet gelukt is.
	 */
	private static function gebroken_op_inlogscherm() {
		// De startpagina plus de winkel: dat zijn de pagina's die een uitgelogde
		// bezoeker te zien krijgt, en ze gebruiken niet dezelfde afbeeldingen
		// (het merklogo staat alleen op de winkel).
		$paginas = array( home_url( '/' ) );
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$winkel = wc_get_page_permalink( 'shop' );
			if ( $winkel ) {
				$paginas[] = $winkel;
			}
		}

		$urls = array();
		foreach ( $paginas as $pagina ) {
			$gevonden = self::uploads_urls_op( $pagina );
			if ( null === $gevonden ) {
				return null;
			}
			$urls = array_merge( $urls, $gevonden );
		}

		$gebroken = array();
		foreach ( array_unique( $urls ) as $url ) {
			$antwoord = wp_remote_head(
				$url,
				array(
					'timeout'   => 10,
					'sslverify' => false,
					'cookies'   => array(),
				)
			);
			if ( is_wp_error( $antwoord ) ) {
				return null;
			}

			// Alleen 401/403 zegt iets over déze afscherming. Een 404 betekent
			// dat het bestand niet bestaat — ook een probleem, maar een ander,
			// en het hoort geen melding op te leveren die zegt dat de witte
			// lijst aangevuld moet worden.
			$code = (int) wp_remote_retrieve_response_code( $antwoord );
			if ( 401 === $code || 403 === $code ) {
				$gebroken[] = basename( wp_parse_url( $url, PHP_URL_PATH ) );
			}
		}

		return $gebroken;
	}

	/**
	 * Haalt één pagina cookieloos op en geeft elke uploads-URL erin terug.
	 *
	 * Zoekt op het pad (/wp-content/uploads/...) en niet op de volledige
	 * URL-met-domein: de header zet zijn logo zonder domeinnaam in de pagina,
	 * en dat zijn juist de afbeeldingen die op élke pagina staan. Op alleen de
	 * volledige URL zoeken miste ze allemaal, waardoor de controle "in orde"
	 * meldde op grond van twee favicons.
	 *
	 * @param string $pagina Volledige URL van de pagina.
	 * @return array|null Lijst volledige URL's, of null als de pagina niet op
	 *                    te halen was.
	 */
	private static function uploads_urls_op( $pagina ) {
		$antwoord = wp_remote_get(
			$pagina,
			array(
				'timeout'   => 15,
				'sslverify' => false,
				// Zonder cookies, dus precies zoals een bezoeker het krijgt.
				'cookies'   => array(),
			)
		);

		if ( is_wp_error( $antwoord ) ) {
			return null;
		}

		$uploads = wp_upload_dir();
		$pad     = wp_parse_url( $uploads['baseurl'], PHP_URL_PATH );
		if ( ! $pad ) {
			return array();
		}

		$html       = wp_remote_retrieve_body( $antwoord );
		$eigen_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$urls       = array();

		// 1. Volledige URL's: alleen die van onze eigen site tellen mee. De
		// sfeerfoto op het inlogscherm komt bijvoorbeeld van
		// homburg-belgium.com; die staat niet in ónze uploadsmap en zou
		// omgerekend naar ons eigen domein een valse melding geven.
		if ( preg_match_all( '#https?://[^"\'\s),>]+#', $html, $treffers ) ) {
			foreach ( $treffers[0] as $url ) {
				$delen = wp_parse_url( $url );
				if ( empty( $delen['host'] ) || $delen['host'] !== $eigen_host ) {
					continue;
				}
				if ( empty( $delen['path'] ) || 0 !== strpos( $delen['path'], $pad . '/' ) ) {
					continue;
				}
				$urls[] = $url;
			}
		}

		// 2. Paden zonder domeinnaam; die zijn per definitie van onszelf. De
		// header schrijft zijn logo zo, en dat staat op elke pagina.
		if ( preg_match_all( '#(?<=["\'\s(])' . preg_quote( $pad, '#' ) . '/[^"\'\s),>]+#', $html, $treffers ) ) {
			foreach ( $treffers[0] as $relatief ) {
				$urls[] = home_url( $relatief );
			}
		}

		return array_values( array_filter( array_unique( $urls ), array( __CLASS__, 'lijkt_op_een_bestand' ) ) );
	}

	/**
	 * Of deze URL een bestand aanwijst en niet iets anders.
	 *
	 * Een stylesheet kan "/wp-content/uploads/*" bevatten en die sterretjes
	 * zijn geen bestand. Binnen een afgeschermde map geeft álles wat er niet
	 * is een 403 in plaats van een 404, dus zonder deze zeef meldt de
	 * controle dat er iets stuk is wat nooit heeft bestaan.
	 *
	 * @param string $url Volledige URL.
	 * @return bool
	 */
	private static function lijkt_op_een_bestand( $url ) {
		$pad = (string) wp_parse_url( $url, PHP_URL_PATH );

		return (bool) preg_match( '#\.[a-z0-9]{2,5}$#i', $pad );
	}

	/**
	 * Controleert of de webserver de afgeschermde map daadwerkelijk dichthoudt.
	 *
	 * Nodig omdat .htaccess alleen door Apache/LiteSpeed gelezen wordt; een
	 * nginx-server negeert hem zonder enige melding, en dan zou je denken dat
	 * de bestanden beschermd zijn terwijl ze gewoon op te halen blijven. Deze
	 * controle haalt daarom via een echte HTTP-request een testbestand uit die
	 * map op: komt dat binnen, dan klopt de aanname niet en volgt een
	 * waarschuwing in wp-admin.
	 *
	 * Dagelijks, via een transient — het is een loopback-request, geen last.
	 */
	public static function controleer_afscherming() {
		if ( get_transient( 'hdp_beveiliging_controle_gedaan' ) ) {
			return;
		}
		set_transient( 'hdp_beveiliging_controle_gedaan', 1, DAY_IN_SECONDS );

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return;
		}

		$map = trailingslashit( $uploads['basedir'] ) . self::MAP;
		if ( ! self::zorg_voor_map( $map ) ) {
			return;
		}

		$pad = trailingslashit( $map ) . self::CONTROLE_BESTAND;
		@file_put_contents( $pad, "controlebestand beveiliging\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- WP_Filesystem is hier niet geladen; zelfde aanpak als HDP_Log.

		$antwoord = wp_remote_get(
			trailingslashit( $uploads['baseurl'] ) . self::MAP . '/' . self::CONTROLE_BESTAND,
			array(
				'timeout'   => 10,
				'sslverify' => false,
			)
		);

		if ( is_wp_error( $antwoord ) ) {
			update_option( self::CONTROLE_OPTIE, 'onbekend' );
			return;
		}

		$openbaar = 200 === (int) wp_remote_retrieve_response_code( $antwoord );
		update_option( self::CONTROLE_OPTIE, $openbaar ? 'openbaar' : 'afgeschermd' );

		if ( $openbaar ) {
			HDP_Log::schrijf(
				'Bestandsbeveiliging: de map ' . self::MAP . ' is via de browser gewoon op te halen — '
					. 'de webserver leest blijkbaar geen .htaccess. Dealerbestanden zijn dan zonder inloggen bereikbaar.',
				'WAARSCHUWING'
			);
		}
	}

	/** Zichtbare melding in wp-admin als de afscherming niet blijkt te werken. */
	public static function toon_waarschuwing() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( 'stuk' === get_option( self::UPLOADS_CONTROLE_OPTIE ) ) {
			?>
			<div class="notice notice-warning">
				<p>
					<strong>Dealerportaal: een afbeelding op het inlogscherm is niet meer zichtbaar.</strong>
					De uploadsmap is afgeschermd voor bezoekers die niet zijn ingelogd, en er staat
					nu iets op het inlogscherm dat daar niet bij hoort. Zie het logboek voor welke
					bestanden het betreft.
				</p>
			</div>
			<?php
		}

		if ( 'openbaar' !== get_option( self::CONTROLE_OPTIE ) ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p>
				<strong>Dealerportaal: downloadbestanden zijn niet afgeschermd.</strong>
				De map <code>wp-content/uploads/<?php echo esc_html( self::MAP ); ?></code> blijkt
				via de browser gewoon op te halen. Deze server leest kennelijk geen
				<code>.htaccess</code>-bestanden (dat doet bijvoorbeeld nginx niet).
				Prijslijsten zijn daardoor voor iedereen bereikbaar die het bestandspad kent.
				Vraag de hostingpartij om deze map dicht te zetten.
			</p>
		</div>
		<?php
	}

	public static function on_meta_gewijzigd( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( '_hdp_attachment_id' !== $meta_key || ! $meta_value ) {
			return;
		}
		self::beveilig( (int) $meta_value );
	}

	public static function is_beveiligd( $attachment_id ) {
		return (bool) get_post_meta( (int) $attachment_id, self::META, true );
	}

	/**
	 * Verplaatst het bestand van een bijlage naar de afgeschermde map en
	 * ruimt de openbare voorbeeldformaten op.
	 *
	 * @return bool Of de bijlage (nu) beveiligd is.
	 */
	public static function beveilig( $attachment_id ) {
		$attachment_id = (int) $attachment_id;

		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			return false;
		}

		if ( self::is_beveiligd( $attachment_id ) ) {
			return true;
		}

		$huidig_pad = get_attached_file( $attachment_id );
		if ( ! $huidig_pad || ! file_exists( $huidig_pad ) ) {
			return false;
		}

		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return false;
		}

		$doelmap = trailingslashit( $uploads['basedir'] ) . self::MAP;
		if ( ! self::zorg_voor_map( $doelmap ) ) {
			return false;
		}

		$doelnaam = wp_unique_filename( $doelmap, wp_basename( $huidig_pad ) );
		$doel_pad = trailingslashit( $doelmap ) . $doelnaam;

		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename -- WP_Filesystem is hier niet geladen (draait ook buiten wp-admin) en een verplaatsing binnen dezelfde uploadsmap heeft geen filesystem-abstractie nodig.
		if ( ! @rename( $huidig_pad, $doel_pad ) ) {
			return false;
		}

		self::verwijder_openbare_formaten( $attachment_id, dirname( $huidig_pad ) );

		update_post_meta( $attachment_id, '_wp_attached_file', self::MAP . '/' . $doelnaam );
		update_post_meta( $attachment_id, self::META, 1 );

		return true;
	}

	/**
	 * WordPress maakt naast het origineel kleinere kopieën (en van een PDF
	 * een JPG-voorbeeld van pagina 1). Die blijven bij een verhuizing achter
	 * in de openbare map, dus die gaan weg — een beveiligd bestand wordt
	 * toch altijd in zijn geheel via het eigen endpoint uitgeleverd.
	 */
	private static function verwijder_openbare_formaten( $attachment_id, $oude_map ) {
		$meta = wp_get_attachment_metadata( $attachment_id );

		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $formaat ) {
				if ( empty( $formaat['file'] ) ) {
					continue;
				}
				$pad = trailingslashit( $oude_map ) . wp_basename( $formaat['file'] );
				if ( file_exists( $pad ) ) {
					wp_delete_file( $pad );
				}
			}
			$meta['sizes'] = array();
		}

		if ( ! empty( $meta['file'] ) ) {
			$meta['file'] = self::MAP . '/' . wp_basename( $meta['file'] );
		}

		if ( $meta ) {
			wp_update_attachment_metadata( $attachment_id, $meta );
		}
	}

	/**
	 * Maakt de afgeschermde map aan, met daarin de bestanden die de
	 * webserver vertellen dat hier niets rechtstreeks op te halen valt.
	 * Zelfde techniek als bij de logmap (zie HDP_Log).
	 */
	private static function zorg_voor_map( $map ) {
		if ( ! wp_mkdir_p( $map ) ) {
			return false;
		}

		$bewakers = array(
			// Apache 2.4 gebruikt Require, 2.2 gebruikt Order/Deny — beide
			// meenemen zodat dit ook op een oudere cPanel-server werkt.
			'.htaccess'  => "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n\t<system.webServer>\n\t\t<authorization>\n\t\t\t<deny users=\"*\" />\n\t\t</authorization>\n\t</system.webServer>\n</configuration>\n",
			'index.php'  => "<?php\n// Niets te zien hier.\n",
		);

		foreach ( $bewakers as $naam => $inhoud ) {
			$pad = trailingslashit( $map ) . $naam;
			if ( ! file_exists( $pad ) ) {
				@file_put_contents( $pad, $inhoud ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- WP_Filesystem is hier niet geladen; zelfde aanpak als HDP_Log.
			}
		}

		return true;
	}

	/**
	 * Geeft voor een beveiligd bestand de eigen download-URL terug in plaats
	 * van het directe pad. Daarmee wijst álles wat WordPress toont (de
	 * mediabibliotheek, de REST-API, een gekopieerde link) naar de versie
	 * mét toegangscontrole.
	 */
	public static function filter_attachment_url( $url, $attachment_id ) {
		if ( ! self::is_beveiligd( $attachment_id ) ) {
			return $url;
		}

		$download_id = self::vind_download( $attachment_id );

		return $download_id ? HDP_Downloads_CPT::download_url( $download_id ) : $url;
	}

	/** Haalt beveiligde bestanden uit de openbare /wp-json/wp/v2/media-lijst. */
	public static function filter_rest_query( $args, $request ) {
		if ( current_user_can( 'upload_files' ) ) {
			return $args;
		}

		$args['meta_query']   = isset( $args['meta_query'] ) ? $args['meta_query'] : array();
		$args['meta_query'][] = array(
			'key'     => self::META,
			'compare' => 'NOT EXISTS',
		);

		return $args;
	}

	/**
	 * Een rechtstreekse opvraag van één bijlage (/wp-json/wp/v2/media/123)
	 * gaat niet door de queryfilter hierboven — daar dus de gegevens
	 * weghalen waarmee je alsnog bij het bestand zou komen.
	 */
	public static function filter_rest_item( $response, $post, $request ) {
		if ( current_user_can( 'upload_files' ) || ! self::is_beveiligd( $post->ID ) ) {
			return $response;
		}

		$data = $response->get_data();

		// 'filename' hoort er ook uit: samen met de (vaste) mapnaam is de
		// directe URL daarmee weer samen te stellen.
		foreach ( array( 'source_url', 'guid', 'media_details', 'description', 'caption', 'filename', 'filesize' ) as $veld ) {
			unset( $data[ $veld ] );
		}

		$response->set_data( $data );

		return $response;
	}

	/** Bij welke download hoort deze bijlage? */
	private static function vind_download( $attachment_id ) {
		$attachment_id = (int) $attachment_id;

		if ( isset( self::$download_cache[ $attachment_id ] ) ) {
			return self::$download_cache[ $attachment_id ];
		}

		$posts = get_posts(
			array(
				'post_type'      => HDP_Downloads_CPT::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'meta_key'       => '_hdp_attachment_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- kleine, beheerste dataset (dealerdownloads).
				'meta_value'     => $attachment_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		);

		self::$download_cache[ $attachment_id ] = $posts ? (int) $posts[0] : 0;

		return self::$download_cache[ $attachment_id ];
	}

	/**
	 * Eenmalig: downloads die er al stonden vóór deze beveiliging bestond
	 * alsnog verhuizen. Draait na een deploy vanzelf één keer mee bij het
	 * eerstvolgende bezoek aan wp-admin.
	 */
	public static function migreer_bestaande_downloads() {
		if ( get_option( self::MIGRATIE_OPTIE ) ) {
			return;
		}

		$downloads = get_posts(
			array(
				'post_type'      => HDP_Downloads_CPT::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		$verplaatst = 0;
		foreach ( $downloads as $download_id ) {
			$attachment_id = (int) get_post_meta( $download_id, '_hdp_attachment_id', true );
			if ( $attachment_id && self::beveilig( $attachment_id ) ) {
				++$verplaatst;
			}
		}

		update_option( self::MIGRATIE_OPTIE, 1 );

		HDP_Log::schrijf(
			sprintf( 'Bestandsbeveiliging: %d van %d downloads verhuisd naar de afgeschermde map.', $verplaatst, count( $downloads ) ),
			'INFO'
		);
	}
}
