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
			add_action( 'admin_init', array( __CLASS__, 'controleer_afscherming' ) );
			add_action( 'admin_notices', array( __CLASS__, 'toon_waarschuwing' ) );
		}
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
		if ( 'openbaar' !== get_option( self::CONTROLE_OPTIE ) || ! current_user_can( 'manage_options' ) ) {
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
