<?php
/**
 * Portaalgebruik: wat dealers in het portaal doen.
 *
 * Houdt bij, alleen van dealers (beheerders en testaccounts tellen niet mee):
 * - een bezoek (hooguit één per account per dag), met het apparaat
 *   (telefoon/tablet of computer) en de taal;
 * - welke onderdelen een dealer die dag opende (webshop, downloads, ...),
 *   ook hooguit één keer per onderdeel per dag;
 * - een zoekopdracht in de zoekbalk en in de webshop (met het aantal treffers);
 * - een download.
 * Bestellingen hoeven niet apart bijgehouden te worden: die staan al in
 * WooCommerce.
 *
 * Alles telt per bedrijf: een collega (HDP_Collegas) telt mee bij het
 * bedrijf van zijn hoofdaccount.
 *
 * Alles blijft in de eigen database; er gaat niets naar een externe
 * dienst en er worden geen cookies gezet. Na STERMIJN_DAGEN ruimt het
 * zichzelf op.
 *
 * Het overzicht staat in wp-admin onder Dashboard > Portaalgebruik
 * (HDP_Gebruik_Scherm).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HDP_Gebruik {

	const TABEL       = 'hdp_gebruik';
	const DB_VERSIE   = '1';
	const DB_OPTIE    = 'hdp_gebruik_db_versie';
	const LAATST_META = 'hdp_laatst_actief';

	/** Gebruikersmeta: welke onderdelen vandaag al geteld zijn ("2026-11-11:webshop,downloads"). */
	const ONDERDEEL_META = 'hdp_onderdelen_vandaag';

	/** Ruim een jaar, zodat je een maand kunt vergelijken met dezelfde maand vorig jaar. */
	const STERMIJN_DAGEN = 400;

	/** Zonder bezoek in zoveel dagen staat een bedrijf op de bellijst. */
	const STIL_DAGEN = 30;

	/** Zoveel bezoekdagen in de laatste 30 dagen = "actief" (ongeveer wekelijks). */
	const ACTIEF_DAGEN = 4;

	/** Een gevulde winkelwagen van iemand die zo lang niet is geweest, is "vergeten". */
	const VERGETEN_DAGEN = 3;

	/** Dezelfde zoekterm in de webshop telt binnen deze tijd maar één keer (filters aanklikken, terugbladeren). */
	const WEBSHOPZOEK_PAUZE = 30 * MINUTE_IN_SECONDS;

	const EXPORT_ACTIE = 'hdp_gebruik_export';

	const PERIODES = array(
		30  => 'Laatste 30 dagen',
		90  => 'Laatste 3 maanden',
		365 => 'Laatste 12 maanden',
	);

	/** De onderdelen van het portaal, in de volgorde van het menu. */
	const ONDERDELEN = array(
		'start'        => 'Startpagina',
		'webshop'      => 'Webshop',
		'bestellingen' => 'Bestelgeschiedenis',
		'downloads'    => 'Downloads',
		'content'      => 'Content',
		'huisstijl'    => 'Huisstijl',
		'garantie'     => 'Garantie',
		'trainingen'   => 'Trainingen',
		'account'      => 'Mijn account',
	);

	/** Het blok op een pagina bepaalt het onderdeel. */
	const BLOK_ONDERDEEL = array(
		'homburg/downloads-pagina'        => 'downloads',
		'homburg/content-pagina'          => 'content',
		'homburg/huisstijl-pagina'        => 'huisstijl',
		'homburg/garantie-pagina'         => 'garantie',
		'homburg/trainingen-pagina'       => 'trainingen',
		'homburg/bestelgeschiedenis-pagina' => 'bestellingen',
	);

	const ORDERSTATUSSEN = array( 'wc-processing', 'wc-completed', 'wc-on-hold' );

	public static function init() {
		// Zelfherstellend: een deploy (rsync) activeert de plugin niet
		// opnieuw, dus de tabel hier aanmaken zodra de versie niet klopt.
		add_action( 'init', array( __CLASS__, 'controleer_tabel' ) );
		add_action( 'template_redirect', array( __CLASS__, 'noteer_bezoek' ) );
		add_action( 'template_redirect', array( __CLASS__, 'noteer_webshopzoek' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'noteer_onderdeel' ), 30 );
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'noteer_winkelwagen' ) );
		add_action( 'admin_menu', array( 'HDP_Gebruik_Scherm', 'registreer_menu' ) );
		add_action( 'admin_post_' . self::EXPORT_ACTIE, array( __CLASS__, 'exporteer' ) );
		add_action( 'edit_user_profile', array( 'HDP_Gebruik_Scherm', 'render_profiel' ), 20 );
		add_action( 'hdp_gebruik_opruimen', array( __CLASS__, 'ruim_op' ) );
		if ( ! wp_next_scheduled( 'hdp_gebruik_opruimen' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'hdp_gebruik_opruimen' );
		}
	}

	public static function tabel() {
		global $wpdb;
		return $wpdb->prefix . self::TABEL;
	}

	public static function controleer_tabel() {
		if ( get_option( self::DB_OPTIE ) === self::DB_VERSIE ) {
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$tabel   = self::tabel();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$tabel} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				tijd datetime NOT NULL,
				user_id bigint(20) unsigned NOT NULL,
				soort varchar(20) NOT NULL,
				sleutel varchar(191) NOT NULL DEFAULT '',
				aantal int(11) NOT NULL DEFAULT 0,
				PRIMARY KEY  (id),
				KEY soort_tijd (soort,tijd),
				KEY user_tijd (user_id,tijd)
			) {$charset};"
		);

		update_option( self::DB_OPTIE, self::DB_VERSIE, false );
	}

	/** Telt deze gebruiker mee? Alleen goedgekeurde dealers, geen beheerders en geen testaccounts. */
	public static function telt_mee( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		return $user_id
			&& ! user_can( $user_id, 'manage_options' )
			&& ! self::is_testaccount( $user_id )
			&& HDP_Roles::mag_portaal_zien( $user_id );
	}

	/** Gebruikersmeta: aangevinkt in het profiel ("Testaccount"). */
	const TEST_META = 'hdp_testaccount';

	/**
	 * Een account om het portaal mee te testen. Anders staat de testdealer
	 * straks bovenaan "meest gezocht" en in de bestellingen per dealer.
	 * Aangevinkt in het gebruikersprofiel, of herkenbaar aan de naam: de
	 * testaccounts die bij het bouwen zijn gebruikt beginnen met "hdp-test-".
	 */
	public static function is_testaccount( $user_id ) {
		if ( get_user_meta( $user_id, self::TEST_META, true ) ) {
			return true;
		}
		$gebruiker = get_userdata( $user_id );
		return $gebruiker && 0 === strpos( $gebruiker->user_login, 'hdp-test-' );
	}

	/**
	 * Wanneer deze gebruiker voor het laatst iets deed.
	 *
	 * Onze eigen meting begint pas bij de livegang. WooCommerce houdt al
	 * langer per klant de laatste activiteit bij (per dag); die vult het
	 * gat, zodat "niet meer geweest" niet de eerste maand bij iedereen "nog
	 * nooit" zegt.
	 */
	public static function laatst_actief( $user_id ) {
		return max(
			(int) get_user_meta( $user_id, self::LAATST_META, true ),
			(int) get_user_meta( $user_id, 'wc_last_active', true )
		);
	}

	/** Het bedrijf (hoofdaccount) waar deze gebruiker bij hoort; zonder collega-koppeling hijzelf. */
	public static function bedrijf_van( $user_id ) {
		$user_id = (int) $user_id;
		if ( ! $user_id ) {
			return 0; // Geen account (hoofd_id(0) zou de ingelogde gebruiker geven).
		}
		return class_exists( 'HDP_Collegas' ) ? HDP_Collegas::hoofd_id( $user_id ) : $user_id;
	}

	/* ---------------------------------------------------------------------
	 * Meten
	 * ------------------------------------------------------------------- */

	/**
	 * Legt één gebeurtenis vast.
	 *
	 * @param string $soort   'bezoek', 'onderdeel', 'zoek', 'webshopzoek' of 'download'.
	 * @param string $sleutel De zoekterm, het download-ID, het onderdeel, of bij een bezoek "apparaat|taal".
	 * @param int    $aantal  Bij een zoekopdracht: het aantal treffers.
	 */
	public static function noteer( $soort, $sleutel = '', $aantal = 0, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( ! self::telt_mee( $user_id ) ) {
			return;
		}

		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- eigen tabel.
			self::tabel(),
			array(
				'tijd'    => current_time( 'mysql', true ),
				'user_id' => $user_id,
				'soort'   => $soort,
				'sleutel' => mb_substr( (string) $sleutel, 0, 191 ),
				'aantal'  => (int) $aantal,
			),
			array( '%s', '%d', '%s', '%s', '%d' )
		);
	}

	/** "mobiel|fr": het apparaat en de taal van dit bezoek. */
	public static function apparaat_en_taal() {
		$apparaat = wp_is_mobile() ? 'mobiel' : 'computer';
		$taal     = class_exists( 'HDP_I18N' ) ? HDP_I18N::huidige_taal() : 'nl';
		return $apparaat . '|' . $taal;
	}

	/**
	 * Eén bezoek per dealer per dag, en het moment dat hij het laatst iets
	 * deed (voor "wie is al een maand niet geweest").
	 */
	public static function noteer_bezoek() {
		if ( ! is_user_logged_in() || wp_doing_ajax() || ! self::telt_mee() ) {
			return;
		}

		$user_id = get_current_user_id();
		$laatst  = (int) get_user_meta( $user_id, self::LAATST_META, true );
		$nu      = time();

		if ( $nu - $laatst < 15 * MINUTE_IN_SECONDS ) {
			return;
		}

		update_user_meta( $user_id, self::LAATST_META, $nu );

		if ( ! $laatst || wp_date( 'Y-m-d', $laatst ) !== wp_date( 'Y-m-d', $nu ) ) {
			self::noteer( 'bezoek', self::apparaat_en_taal(), 0, $user_id );
		}
	}

	/** Welk onderdeel van het portaal is deze pagina? Leeg als het geen onderdeel is. */
	public static function onderdeel_van_pagina() {
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			if ( is_wc_endpoint_url( 'orders' ) || is_wc_endpoint_url( 'view-order' ) ) {
				return 'bestellingen';
			}
			if ( is_wc_endpoint_url( 'snelbestellen' ) || is_wc_endpoint_url( 'favorieten' ) ) {
				return 'webshop';
			}
			if ( is_wc_endpoint_url( 'mijn-trainingen' ) ) {
				return 'trainingen';
			}
			return 'account';
		}
		if ( function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() ) ) {
			return 'webshop';
		}
		if ( is_front_page() ) {
			return 'start';
		}
		if ( is_singular() ) {
			$post = get_queried_object();
			if ( $post instanceof WP_Post ) {
				foreach ( self::BLOK_ONDERDEEL as $blok => $onderdeel ) {
					if ( has_block( $blok, $post ) ) {
						return $onderdeel;
					}
				}
			}
		}
		return '';
	}

	/** Eén keer per onderdeel per dag: welke onderdelen deze dealer gebruikt. */
	public static function noteer_onderdeel() {
		if ( ! is_user_logged_in() || wp_doing_ajax() || ! self::telt_mee() ) {
			return;
		}
		$onderdeel = self::onderdeel_van_pagina();
		if ( '' === $onderdeel ) {
			return;
		}
		self::noteer_onderdeel_voor( get_current_user_id(), $onderdeel );
	}

	/** Los van de pagina, zodat het te testen is. */
	public static function noteer_onderdeel_voor( $user_id, $onderdeel ) {
		if ( isset( self::ONDERDELEN[ $onderdeel ] ) ) {
			self::noteer_vandaag( $user_id, 'onderdeel', $onderdeel );
		}
	}

	/**
	 * Iets in de winkelwagen gedaan: één keer per dag. Voor de stap
	 * "iets in de winkelwagen" tussen webshop en bestelling.
	 */
	public static function noteer_winkelwagen() {
		$user_id = get_current_user_id();
		if ( $user_id && self::telt_mee( $user_id ) ) {
			self::noteer_vandaag( $user_id, 'winkelwagen', '' );
		}
	}

	/**
	 * Legt soort + sleutel hooguit één keer per dag per gebruiker vast. Wat
	 * vandaag al geteld is staat in één gebruikersmeta, zodat dit geen
	 * opvraag per pagina kost.
	 */
	public static function noteer_vandaag( $user_id, $soort, $sleutel ) {
		if ( ! self::telt_mee( $user_id ) ) {
			return;
		}
		$vandaag = wp_date( 'Y-m-d' );
		$teken   = $soort . '/' . $sleutel;
		$stand   = (string) get_user_meta( $user_id, self::ONDERDEEL_META, true );
		$al      = array();
		if ( 0 === strpos( $stand, $vandaag . ':' ) ) {
			$al = array_filter( explode( ',', substr( $stand, strlen( $vandaag ) + 1 ) ) );
		}
		if ( in_array( $teken, $al, true ) ) {
			return;
		}
		$al[] = $teken;
		update_user_meta( $user_id, self::ONDERDEEL_META, $vandaag . ':' . implode( ',', $al ) );
		self::noteer( $soort, $sleutel, 0, $user_id );
	}

	/**
	 * Een zoekopdracht uit de zoekbalk.
	 *
	 * De zoekbalk zoekt al tijdens het typen, dus "har", "hard" en "hardi"
	 * komen alle drie binnen. Alleen het eindwoord telt: een eerdere term
	 * van dezelfde dealer van vlak daarvoor, die het begin van deze is,
	 * wordt vervangen. Anders staat de lijst vol halve woorden.
	 */
	public static function noteer_zoekopdracht( $term, $treffers ) {
		$user_id = get_current_user_id();
		if ( ! self::telt_mee( $user_id ) ) {
			return;
		}

		$term = mb_strtolower( trim( (string) $term ) );
		if ( '' === $term ) {
			return;
		}

		global $wpdb;
		$tabel  = self::tabel();
		$recent = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- eigen tabel, net geschreven.
			$wpdb->prepare(
				"SELECT id, sleutel FROM {$tabel} WHERE user_id = %d AND soort = 'zoek' AND tijd >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- tabelnaam uit eigen prefix.
				$user_id,
				gmdate( 'Y-m-d H:i:s', time() - MINUTE_IN_SECONDS )
			)
		);

		foreach ( $recent as $rij ) {
			$vorige = (string) $rij->sleutel;
			if ( $vorige === $term ) {
				return; // Zelfde term nog eens (bijvoorbeeld opnieuw in het veld geklikt).
			}
			if ( 0 === mb_strpos( $term, $vorige ) || 0 === mb_strpos( $vorige, $term ) ) {
				$wpdb->delete( $tabel, array( 'id' => (int) $rij->id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			}
		}

		self::noteer( 'zoek', $term, (int) $treffers, $user_id );
	}

	/**
	 * Een zoekopdracht in de webshop zelf (het zoekveld boven de artikelen),
	 * met het aantal artikelen dat de dealer te zien kreeg. Nul betekent:
	 * het artikel ontbreekt, heet anders, of hoort bij een merk dat deze
	 * dealer niet voert.
	 */
	public static function noteer_webshopzoek() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- alleen lezen.
		if ( empty( $_GET['zoek'] ) || ! is_user_logged_in() || wp_doing_ajax() || ! function_exists( 'is_shop' ) ) {
			return;
		}
		if ( ! ( is_shop() || is_product_taxonomy() ) || get_query_var( 'paged' ) > 1 ) {
			return;
		}
		$term = mb_strtolower( trim( sanitize_text_field( wp_unslash( $_GET['zoek'] ) ) ) );
		// phpcs:enable
		$user_id = get_current_user_id();
		if ( '' === $term || ! self::telt_mee( $user_id ) ) {
			return;
		}

		global $wpdb, $wp_query;
		$tabel = self::tabel();
		$al    = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- eigen tabel.
			$wpdb->prepare(
				"SELECT id FROM {$tabel} WHERE user_id = %d AND soort = 'webshopzoek' AND sleutel = %s AND tijd >= %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$user_id,
				mb_substr( $term, 0, 191 ),
				gmdate( 'Y-m-d H:i:s', time() - self::WEBSHOPZOEK_PAUZE )
			)
		);
		if ( $al ) {
			return;
		}
		self::noteer( 'webshopzoek', $term, (int) $wp_query->found_posts, $user_id );
	}

	public static function ruim_op() {
		global $wpdb;
		$tabel = self::tabel();
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( "DELETE FROM {$tabel} WHERE tijd < %s", gmdate( 'Y-m-d H:i:s', time() - self::STERMIJN_DAGEN * DAY_IN_SECONDS ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/* ---------------------------------------------------------------------
	 * Wie telt mee
	 * ------------------------------------------------------------------- */

	private static function sql_tijd( $tijdstempel ) {
		return gmdate( 'Y-m-d H:i:s', (int) $tijdstempel );
	}

	private static function vanaf( $dagen ) {
		return self::sql_tijd( time() - (int) $dagen * DAY_IN_SECONDS );
	}

	/**
	 * Begin en eind van een periode als tijdstempels: de laatste $dagen
	 * dagen, of (met $vorige) de $dagen dagen daarvoor.
	 *
	 * @return int[] array( van, tot )
	 */
	public static function bereik( $dagen, $vorige = false ) {
		$lengte = (int) $dagen * DAY_IN_SECONDS;
		$tot    = $vorige ? time() - $lengte : time();
		return array( $tot - $lengte, $tot );
	}

	/** Alle goedgekeurde dealeraccounts die meetellen, collega's inbegrepen. */
	public static function dealers() {
		$gebruikers = get_users(
			array(
				'role'       => HDP_Roles::ROLE,
				'meta_key'   => 'hdp_goedgekeurd', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- beheerscherm, kleine set.
				'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		return array_values(
			array_filter(
				$gebruikers,
				static function ( $gebruiker ) {
					return self::telt_mee( $gebruiker->ID );
				}
			)
		);
	}

	/** De bedrijven: dealers zonder de collega's (die tellen bij hun hoofdaccount). */
	public static function bedrijven() {
		return array_values(
			array_filter(
				self::dealers(),
				static function ( $gebruiker ) {
					return self::bedrijf_van( $gebruiker->ID ) === (int) $gebruiker->ID;
				}
			)
		);
	}

	/**
	 * Per bedrijf de accounts die meetellen (hoofdaccount en collega's).
	 *
	 * @return array<int, int[]>
	 */
	public static function accounts_per_bedrijf() {
		$lijst = array();
		foreach ( self::dealers() as $gebruiker ) {
			$lijst[ self::bedrijf_van( $gebruiker->ID ) ][] = (int) $gebruiker->ID;
		}
		return $lijst;
	}

	/**
	 * De bedrijven die nu meetellen, als sleutels. Oude regels van een account
	 * dat inmiddels verwijderd, beheerder of testaccount is, tellen zo niet
	 * meer mee ("2 van 1 bedrijven actief").
	 *
	 * @return array<int, true>
	 */
	public static function bekende_bedrijven() {
		return array_fill_keys( wp_list_pluck( self::bedrijven(), 'ID' ), true );
	}

	/** De naam waaronder een bedrijf in de overzichten staat. */
	public static function bedrijfsnaam( $user_id ) {
		$bedrijf = (string) get_user_meta( $user_id, 'billing_company', true );
		if ( '' !== $bedrijf ) {
			return $bedrijf;
		}
		$gebruiker = get_userdata( $user_id );
		return $gebruiker ? $gebruiker->display_name : '';
	}

	/** Wanneer iemand van dit bedrijf er het laatst was. */
	public static function laatst_actief_bedrijf( $hoofd_id, array $accounts = array() ) {
		$laatst = self::laatst_actief( $hoofd_id );
		foreach ( $accounts as $id ) {
			$laatst = max( $laatst, self::laatst_actief( $id ) );
		}
		return $laatst;
	}

	/** Wanneer de eigen meting begon (de eerste regel in de tabel); 0 als er nog niets is. */
	public static function meting_sinds() {
		global $wpdb;
		$tabel = self::tabel();
		$eerste = $wpdb->get_var( "SELECT MIN(tijd) FROM {$tabel}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $eerste ? (int) strtotime( $eerste . ' UTC' ) : 0;
	}

	/** Liep de meting al tijdens de vorige periode? Anders valt er niets te vergelijken. */
	public static function vergelijkbaar( $dagen ) {
		$sinds = self::meting_sinds();
		return $sinds && $sinds <= self::bereik( $dagen, true )[0];
	}

	/* ---------------------------------------------------------------------
	 * Cijfers
	 * ------------------------------------------------------------------- */

	/** Kerncijfers over de periode, of (met $vorige) de periode daarvoor. */
	public static function kerncijfers( $dagen, $vorige = false ) {
		global $wpdb;
		$tabel = self::tabel();
		list( $van, $tot ) = self::bereik( $dagen, $vorige );
		$van = self::sql_tijd( $van );
		$tot = self::sql_tijd( $tot );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- eigen tabel, beheerscherm.
		$tel = static function ( $soort, $extra = '' ) use ( $wpdb, $tabel, $van, $tot ) {
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabel} WHERE soort = %s{$extra} AND tijd >= %s AND tijd <= %s", $soort, $van, $tot ) );
		};
		$bezoekers = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT user_id FROM {$tabel} WHERE soort = 'bezoek' AND tijd >= %s AND tijd <= %s", $van, $tot ) );
		// phpcs:enable

		$bekend = self::bekende_bedrijven();
		$actief = count( array_intersect_key( array_flip( array_map( array( __CLASS__, 'bedrijf_van' ), array_map( 'intval', $bezoekers ) ) ), $bekend ) );

		$bestellingen = 0;
		$omzet        = 0.0;
		foreach ( self::orders( $dagen, $vorige ) as $order ) {
			++$bestellingen;
			$omzet += self::netto( $order );
		}

		$bedrijven = count( self::bedrijven() );

		return array(
			'actieve_bedrijven' => $actief,
			'actieve_dealers'   => $actief,
			'bedrijven'         => $bedrijven,
			'dealers'           => $bedrijven,
			'bezoeken'          => $tel( 'bezoek' ),
			'zoekopdrachten'    => $tel( 'zoek' ),
			'zonder_treffer'    => $tel( 'zoek', ' AND aantal = 0' ),
			'downloads'         => $tel( 'download' ),
			'webshopzoek'       => $tel( 'webshopzoek' ),
			'webshop_niets'     => $tel( 'webshopzoek', ' AND aantal = 0' ),
			'bestellingen'      => $bestellingen,
			'omzet'             => $omzet,
		);
	}

	/**
	 * Actieve bedrijven per week, de laatste $weken weken, oudste eerst.
	 *
	 * @return array<int, array{week: string, aantal: int}>
	 */
	public static function actief_per_week( $weken = 12 ) {
		global $wpdb;
		$tabel = self::tabel();
		$rijen = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT DISTINCT YEARWEEK(tijd, 3) AS week, user_id FROM {$tabel} WHERE soort = 'bezoek' AND tijd >= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::vanaf( $weken * 7 )
			)
		);

		$bekend   = self::bekende_bedrijven();
		$per_week = array();
		foreach ( $rijen as $rij ) {
			$bedrijf = self::bedrijf_van( (int) $rij->user_id );
			if ( isset( $bekend[ $bedrijf ] ) ) {
				$per_week[ (string) $rij->week ][ $bedrijf ] = true;
			}
		}

		$lijst = array();
		for ( $i = $weken - 1; $i >= 0; $i-- ) {
			$moment  = time() - $i * WEEK_IN_SECONDS;
			$sleutel = gmdate( 'o', $moment ) . gmdate( 'W', $moment );
			$lijst[] = array(
				'week'   => 'Week ' . (int) gmdate( 'W', $moment ),
				'aantal' => isset( $per_week[ $sleutel ] ) ? count( $per_week[ $sleutel ] ) : 0,
			);
		}
		return $lijst;
	}

	/**
	 * Per onderdeel: hoeveel bedrijven het in de periode gebruikten, en op
	 * hoeveel dagen (opgeteld over alle accounts). In de volgorde van het menu.
	 *
	 * @return array<string, array{naam: string, bedrijven: int, dagen: int}>
	 */
	public static function onderdelen( $dagen ) {
		global $wpdb;
		$tabel = self::tabel();
		$rijen = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT sleutel, user_id, COUNT(*) AS aantal FROM {$tabel} WHERE soort = 'onderdeel' AND tijd >= %s GROUP BY sleutel, user_id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::vanaf( $dagen )
			)
		);

		$lijst = array();
		foreach ( self::ONDERDELEN as $sleutel => $naam ) {
			$lijst[ $sleutel ] = array( 'naam' => $naam, 'bedrijven' => array(), 'dagen' => 0 );
		}
		$bekend = self::bekende_bedrijven();
		foreach ( $rijen as $rij ) {
			if ( ! isset( $lijst[ $rij->sleutel ] ) || ! isset( $bekend[ self::bedrijf_van( (int) $rij->user_id ) ] ) ) {
				continue;
			}
			$lijst[ $rij->sleutel ]['bedrijven'][ self::bedrijf_van( (int) $rij->user_id ) ] = true;
			$lijst[ $rij->sleutel ]['dagen'] += (int) $rij->aantal;
		}
		foreach ( $lijst as &$onderdeel ) {
			$onderdeel['bedrijven'] = count( $onderdeel['bedrijven'] );
		}
		unset( $onderdeel );
		return $lijst;
	}

	/**
	 * Bezoekdagen naar apparaat en taal. Bezoeken van voor deze meting
	 * (zonder apparaat) tellen niet mee.
	 *
	 * @return array{apparaat: array<string, int>, taal: array<string, int>}
	 */
	public static function apparaten( $dagen ) {
		global $wpdb;
		$tabel = self::tabel();
		$rijen = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT sleutel, COUNT(*) AS aantal FROM {$tabel} WHERE soort = 'bezoek' AND sleutel <> '' AND tijd >= %s GROUP BY sleutel", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::vanaf( $dagen )
			)
		);
		$uit = array(
			'apparaat' => array( 'computer' => 0, 'mobiel' => 0 ),
			'taal'     => array( 'nl' => 0, 'fr' => 0 ),
		);
		foreach ( $rijen as $rij ) {
			$delen = explode( '|', (string) $rij->sleutel );
			if ( isset( $uit['apparaat'][ $delen[0] ] ) ) {
				$uit['apparaat'][ $delen[0] ] += (int) $rij->aantal;
			}
			if ( isset( $delen[1], $uit['taal'][ $delen[1] ] ) ) {
				$uit['taal'][ $delen[1] ] += (int) $rij->aantal;
			}
		}
		return $uit;
	}

	/**
	 * Bedrijven waar al STIL_DAGEN niemand is geweest (of nog nooit),
	 * langst weg eerst — een lijstje voor de buitendienst.
	 */
	public static function stille_dealers() {
		$grens = time() - self::STIL_DAGEN * DAY_IN_SECONDS;
		$lijst = array();

		foreach ( self::accounts_per_bedrijf() as $hoofd_id => $accounts ) {
			if ( ! in_array( (int) $hoofd_id, $accounts, true ) ) {
				continue; // Het hoofdaccount telt zelf niet mee (testaccount, niet goedgekeurd).
			}
			$laatst = self::laatst_actief_bedrijf( $hoofd_id, $accounts );
			if ( $laatst && $laatst >= $grens ) {
				continue;
			}
			$dealer  = get_userdata( $hoofd_id );
			$lijst[] = array(
				'id'       => (int) $hoofd_id,
				'bedrijf'  => (string) get_user_meta( $hoofd_id, 'billing_company', true ),
				'naam'     => $dealer->display_name,
				'email'    => $dealer->user_email,
				'telefoon' => (string) get_user_meta( $hoofd_id, 'billing_phone', true ),
				'laatst'   => $laatst,
			);
		}

		usort(
			$lijst,
			static function ( $a, $b ) {
				return $a['laatst'] <=> $b['laatst'];
			}
		);

		return $lijst;
	}

	/**
	 * Zoektermen, vaakst eerst.
	 *
	 * @param bool   $zonder_treffer Alleen de termen die niets opleverden.
	 * @param string $soort          'zoek' (de zoekbalk) of 'webshopzoek' (het zoekveld in de webshop).
	 */
	public static function zoektermen( $dagen, $zonder_treffer = false, $limiet = 20, $soort = 'zoek' ) {
		global $wpdb;
		$tabel = self::tabel();
		$extra = $zonder_treffer ? ' AND aantal = 0' : '';

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT sleutel AS term, COUNT(*) AS keer, COUNT(DISTINCT user_id) AS dealers, MAX(tijd) AS laatst FROM {$tabel} WHERE soort = %s{$extra} AND tijd >= %s GROUP BY sleutel ORDER BY keer DESC, laatst DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $extra is een vaste string.
				$soort,
				self::vanaf( $dagen ),
				$limiet
			)
		);
	}

	/**
	 * Hoe ver de dealers uit PowerAll zijn: uitgenodigd, wachtwoord gekozen,
	 * al eens geweest. Plus wie er nog achteraan moet.
	 *
	 * @return array{totaal: int, niet: int, uitgenodigd: int, verlopen: int, gekozen: int, geweest: int, nalopen: array}
	 */
	public static function uitrol() {
		$stand = array( 'totaal' => 0, 'niet' => 0, 'uitgenodigd' => 0, 'verlopen' => 0, 'gekozen' => 0, 'geweest' => 0, 'nalopen' => array() );
		if ( ! class_exists( 'HDP_PowerAll_Dealers' ) ) {
			return $stand;
		}
		$gebruikers = get_users(
			array(
				'meta_key'     => HDP_PowerAll_Dealers::META_RELATIE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- beheerscherm.
				'meta_compare' => 'EXISTS',
			)
		);
		foreach ( $gebruikers as $gebruiker ) {
			if ( self::is_testaccount( $gebruiker->ID ) ) {
				continue;
			}
			$status = HDP_PowerAll_Dealers::accountstatus( $gebruiker->ID );
			++$stand['totaal'];
			++$stand[ $status['code'] ];
			if ( self::laatst_actief( $gebruiker->ID ) ) {
				++$stand['geweest'];
			}
			if ( in_array( $status['code'], array( 'uitgenodigd', 'verlopen' ), true ) ) {
				$stand['nalopen'][] = array(
					'id'      => $gebruiker->ID,
					'naam'    => $gebruiker->display_name,
					'email'   => $gebruiker->user_email,
					'relatie' => (string) get_user_meta( $gebruiker->ID, HDP_PowerAll_Dealers::META_RELATIE, true ),
					'status'  => $status,
				);
			}
		}
		return $stand;
	}

	/**
	 * Aanmeldingen per training in de periode, met het gemiddelde cijfer.
	 *
	 * @return array<int, array{id: int, titel: string, aanmeldingen: int, bedrijven: int, cijfers: int, gemiddeld: float}>
	 */
	public static function trainingen( $dagen ) {
		if ( ! class_exists( 'HDP_Trainingen' ) ) {
			return array();
		}
		global $wpdb;
		$tabel   = HDP_Trainingen::tabel();
		$reviews = HDP_Training_Reviews::tabel();
		$vanaf   = self::vanaf( $dagen );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- eigen tabellen, beheerscherm.
		$aanmeldingen = $wpdb->get_results( $wpdb->prepare( "SELECT training_id, training_titel, user_id FROM {$tabel} WHERE status = %s AND aangemaakt >= %s", HDP_Trainingen::STATUS_AANGEMELD, $vanaf ) );
		$cijfers      = $wpdb->get_results( $wpdb->prepare( "SELECT r.training_id, r.cijfer, a.user_id FROM {$reviews} r LEFT JOIN {$tabel} a ON a.id = r.aanmelding_id WHERE r.aangemaakt >= %s", $vanaf ) );
		// phpcs:enable

		$lijst = array();
		$rij   = static function ( $id, $titel ) use ( &$lijst ) {
			if ( ! isset( $lijst[ $id ] ) ) {
				$huidig       = get_the_title( $id );
				$lijst[ $id ] = array( 'id' => $id, 'titel' => $huidig ? $huidig : $titel, 'aanmeldingen' => 0, 'bedrijven' => array(), 'cijfers' => array() );
			}
		};
		foreach ( $aanmeldingen as $a ) {
			if ( $a->user_id && self::is_testaccount( (int) $a->user_id ) ) {
				continue;
			}
			$rij( (int) $a->training_id, (string) $a->training_titel );
			++$lijst[ (int) $a->training_id ]['aanmeldingen'];
			$lijst[ (int) $a->training_id ]['bedrijven'][ self::bedrijf_van( (int) $a->user_id ) ] = true;
		}
		foreach ( $cijfers as $c ) {
			if ( $c->user_id && self::is_testaccount( (int) $c->user_id ) ) {
				continue;
			}
			$rij( (int) $c->training_id, '' );
			$lijst[ (int) $c->training_id ]['cijfers'][] = (int) $c->cijfer;
		}
		foreach ( $lijst as &$training ) {
			$training['bedrijven'] = count( $training['bedrijven'] );
			$training['gemiddeld'] = $training['cijfers'] ? array_sum( $training['cijfers'] ) / count( $training['cijfers'] ) : 0.0;
			$training['cijfers']   = count( $training['cijfers'] );
		}
		unset( $training );

		usort(
			$lijst,
			static function ( $a, $b ) {
				return $b['aanmeldingen'] <=> $a['aanmeldingen'];
			}
		);
		return $lijst;
	}

	/** Bestellingen die niet in PowerAll zijn aangekomen. Die moeten met de hand worden ingevoerd of opnieuw verstuurd. */
	public static function powerall_fouten() {
		if ( ! class_exists( 'HDP_PowerAll_Orders' ) || ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		return HDP_PowerAll_Orders::met_status( 'fout', 50 );
	}

	/** Trainingaanmeldingen waarvan de order niet in PowerAll kwam. */
	public static function training_fouten() {
		if ( ! class_exists( 'HDP_Training_PowerAll' ) || ! class_exists( 'HDP_Trainingen' ) ) {
			return 0;
		}
		global $wpdb;
		$tabel = HDP_Trainingen::tabel();
		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( "SELECT COUNT(*) FROM {$tabel} WHERE status = %s AND pa_status = 'fout'", HDP_Trainingen::STATUS_AANGEMELD ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
	}

	/* ---------------------------------------------------------------------
	 * Webshop
	 * ------------------------------------------------------------------- */

	/** @var array<string, WC_Order[]> */
	private static $orders = array();

	/** Bedrijven die niet meetellen: beheerders en testaccounts. */
	private static function telt_niet( $klant_id ) {
		return $klant_id && ( self::is_testaccount( $klant_id ) || user_can( $klant_id, 'manage_options' ) );
	}

	/**
	 * De echte bestellingen in de periode (in behandeling, afgerond of in de
	 * wacht), zonder die van beheerders en testaccounts.
	 *
	 * @return WC_Order[]
	 */
	public static function orders( $dagen, $vorige = false ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}
		list( $van, $tot ) = self::bereik( $dagen, $vorige );
		$sleutel = (int) $dagen . ( $vorige ? 'v' : '' );
		if ( isset( self::$orders[ $sleutel ] ) ) {
			return self::$orders[ $sleutel ];
		}
		$orders = wc_get_orders(
			array(
				'limit'        => -1,
				'type'         => 'shop_order',
				'status'       => self::ORDERSTATUSSEN,
				'date_created' => $van . '...' . $tot,
			)
		);
		self::$orders[ $sleutel ] = array_values(
			array_filter(
				$orders,
				static function ( $order ) {
					return ! self::telt_niet( (int) $order->get_customer_id() );
				}
			)
		);
		return self::$orders[ $sleutel ];
	}

	/** Leeg de bewaarde bestellingen (voor tests, die binnen één verzoek bestellingen aanmaken). */
	public static function vergeet_orders() {
		self::$orders = array();
	}

	/** Omzet van een bestelling: het totaal zonder btw (met verzending en toeslag). */
	public static function netto( $order ) {
		return (float) $order->get_total() - (float) $order->get_total_tax();
	}

	/** Het merk van een artikel: de bovenste categorie. */
	public static function merk_van_product( $product_id ) {
		static $cache = array();
		$product_id = (int) $product_id;
		if ( isset( $cache[ $product_id ] ) ) {
			return $cache[ $product_id ];
		}
		$merk   = 'Overig';
		$termen = wp_get_post_terms( $product_id, 'product_cat' );
		if ( ! is_wp_error( $termen ) ) {
			foreach ( $termen as $term ) {
				$voorouders = get_ancestors( $term->term_id, 'product_cat', 'taxonomy' );
				$boven      = $voorouders ? get_term( (int) end( $voorouders ), 'product_cat' ) : $term;
				if ( $boven instanceof WP_Term && 'uncategorized' !== $boven->slug ) {
					$merk = html_entity_decode( $boven->name );
					break;
				}
			}
		}
		$cache[ $product_id ] = $merk;
		return $merk;
	}

	/**
	 * Wat er besteld is: per artikel, per merk, en hoe vaak de
	 * kleinordertoeslag werd berekend.
	 *
	 * @return array{artikelen: array, merken: array<string, float>, toeslag: array{keer: int, bedrag: float}}
	 */
	public static function webshopcijfers( $dagen ) {
		$artikelen = array();
		$merken    = array();
		$toeslag   = array( 'keer' => 0, 'bedrag' => 0.0 );

		foreach ( self::orders( $dagen ) as $order ) {
			$bedrijf = self::bedrijf_van( (int) $order->get_customer_id() );
			foreach ( $order->get_items( 'line_item' ) as $item ) {
				$product_id = (int) $item->get_product_id();
				$bedrag     = (float) $item->get_total();
				if ( ! isset( $artikelen[ $product_id ] ) ) {
					$product                  = $item->get_product();
					$artikelen[ $product_id ] = array(
						'id'        => $product_id,
						'naam'      => $item->get_name(),
						'sku'       => $product ? (string) $product->get_sku() : '',
						'stuks'     => 0,
						'omzet'     => 0.0,
						'bedrijven' => array(),
					);
				}
				$artikelen[ $product_id ]['stuks']                += (int) $item->get_quantity();
				$artikelen[ $product_id ]['omzet']                += $bedrag;
				$artikelen[ $product_id ]['bedrijven'][ $bedrijf ] = true;

				$merk            = self::merk_van_product( $product_id );
				$merken[ $merk ] = ( isset( $merken[ $merk ] ) ? $merken[ $merk ] : 0.0 ) + $bedrag;
			}
			if ( class_exists( 'HDP_Kleinordertoeslag' ) ) {
				foreach ( $order->get_items( 'fee' ) as $fee ) {
					if ( HDP_Kleinordertoeslag::is_toeslag( $fee ) ) {
						++$toeslag['keer'];
						$toeslag['bedrag'] += (float) $fee->get_total();
					}
				}
			}
		}

		foreach ( $artikelen as &$artikel ) {
			$artikel['bedrijven'] = count( $artikel['bedrijven'] );
		}
		unset( $artikel );
		usort(
			$artikelen,
			static function ( $a, $b ) {
				return array( $b['stuks'], $b['omzet'] ) <=> array( $a['stuks'], $a['omzet'] );
			}
		);
		arsort( $merken );

		return array(
			'artikelen' => $artikelen,
			'merken'    => $merken,
			'toeslag'   => $toeslag,
		);
	}

	/**
	 * Winkelwagens met iets erin dat (nog) niet besteld is. WooCommerce
	 * bewaart de wagen van een ingelogde klant, en leegt hem na bestellen.
	 *
	 * @return array<int, array{id: int, bedrijf_id: int, bedrijf: string, naam: string, regels: int, stuks: int, waarde: float, laatst: int, vergeten: bool}>
	 */
	public static function open_winkelwagens() {
		$sleutel = '_woocommerce_persistent_cart_' . get_current_blog_id();
		$grens   = time() - self::VERGETEN_DAGEN * DAY_IN_SECONDS;
		$lijst   = array();

		foreach ( self::dealers() as $dealer ) {
			$wagen = get_user_meta( $dealer->ID, $sleutel, true );
			if ( ! is_array( $wagen ) || empty( $wagen['cart'] ) || ! is_array( $wagen['cart'] ) ) {
				continue;
			}
			$stuks  = 0;
			$waarde = 0.0;
			foreach ( $wagen['cart'] as $regel ) {
				$aantal = isset( $regel['quantity'] ) ? (int) $regel['quantity'] : 0;
				$stuks += $aantal;
				if ( isset( $regel['line_total'] ) && '' !== $regel['line_total'] ) {
					$waarde += (float) $regel['line_total'];
				} else {
					$id      = ! empty( $regel['variation_id'] ) ? (int) $regel['variation_id'] : ( isset( $regel['product_id'] ) ? (int) $regel['product_id'] : 0 );
					$product = $id && function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
					$waarde += $product ? (float) $product->get_price() * $aantal : 0.0;
				}
			}
			$bedrijf_id = self::bedrijf_van( $dealer->ID );
			$laatst     = self::laatst_actief( $dealer->ID );
			$lijst[]    = array(
				'id'         => (int) $dealer->ID,
				'bedrijf_id' => $bedrijf_id,
				'bedrijf'    => self::bedrijfsnaam( $bedrijf_id ),
				'naam'       => $dealer->display_name,
				'regels'     => count( $wagen['cart'] ),
				'stuks'      => $stuks,
				'waarde'     => $waarde,
				'laatst'     => $laatst,
				'vergeten'   => $laatst < $grens,
			);
		}

		usort(
			$lijst,
			static function ( $a, $b ) {
				return $b['waarde'] <=> $a['waarde'];
			}
		);
		return $lijst;
	}

	/* ---------------------------------------------------------------------
	 * Per bedrijf
	 * ------------------------------------------------------------------- */

	/** Hoe actief een bedrijf is, op basis van de bezoekdagen in de laatste 30 dagen. */
	public static function activiteit( $laatst, $dagen30 ) {
		if ( ! $laatst ) {
			return array( 'code' => 'nooit', 'tekst' => 'Nog nooit' );
		}
		if ( $dagen30 >= self::ACTIEF_DAGEN ) {
			return array( 'code' => 'actief', 'tekst' => 'Actief' );
		}
		if ( $dagen30 > 0 || $laatst >= time() - 30 * DAY_IN_SECONDS ) {
			return array( 'code' => 'af-en-toe', 'tekst' => 'Af en toe' );
		}
		return array( 'code' => 'stil', 'tekst' => 'Stil' );
	}

	/**
	 * Eén regel per bedrijf met alles bij elkaar, voor de dealerlijst, de
	 * export en het profiel. Collega's tellen mee bij hun bedrijf.
	 *
	 * @param int $alleen Alleen dit bedrijf (hoofdaccount-ID).
	 * @return array<int, array>
	 */
	public static function dealerlijst( $dagen, $alleen = 0 ) {
		global $wpdb;
		$tabel  = self::tabel();
		$vanaf  = self::vanaf( $dagen );
		$grens  = gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS );
		$alleen = (int) $alleen;

		$accounts = self::accounts_per_bedrijf();
		if ( $alleen ) {
			$accounts = array( $alleen => isset( $accounts[ $alleen ] ) ? $accounts[ $alleen ] : array( $alleen ) );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- eigen tabel, beheerscherm.
		$tellingen = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, soort, COUNT(*) AS aantal FROM {$tabel} WHERE tijd >= %s AND soort IN ('download','zoek','webshopzoek') GROUP BY user_id, soort", $vanaf ) );
		$dagen_rij = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT user_id, DATE(tijd) AS dag FROM {$tabel} WHERE soort = 'bezoek' AND tijd >= %s", self::vanaf( max( (int) $dagen, 30 ) ) ) );
		$zoekrijen = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, sleutel, COUNT(*) AS aantal FROM {$tabel} WHERE soort IN ('zoek','webshopzoek') AND tijd >= %s GROUP BY user_id, sleutel", $vanaf ) );
		$deelrijen = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, sleutel, COUNT(*) AS aantal FROM {$tabel} WHERE soort = 'onderdeel' AND tijd >= %s GROUP BY user_id, sleutel", $vanaf ) );
		$weekrijen = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT user_id, DATE(tijd) AS dag FROM {$tabel} WHERE soort = 'bezoek' AND tijd >= %s", self::vanaf( 12 * 7 ) ) );
		// phpcs:enable

		$leeg = array( 'download' => 0, 'zoek' => 0, 'bezoekdagen' => array(), 'dagen30' => array(), 'bestellingen' => 0, 'omzet' => 0.0, 'laatste_bestelling' => 0, 'trainingen' => 0, 'termen' => array(), 'onderdelen' => array(), 'weken' => array() );
		$per  = array();

		// Bezoekdagen per week, de laatste 12 weken (0 = deze week), per bedrijf.
		$vandaag = strtotime( gmdate( 'Y-m-d' ) . ' 00:00:00 UTC' );
		foreach ( $weekrijen as $rij ) {
			$b = self::bedrijf_van( (int) $rij->user_id );
			if ( ! isset( $per[ $b ] ) ) {
				$per[ $b ] = $leeg;
			}
			$terug = (int) floor( ( $vandaag - strtotime( $rij->dag . ' 00:00:00 UTC' ) ) / WEEK_IN_SECONDS );
			if ( $terug >= 0 && $terug < 12 ) {
				$per[ $b ]['weken'][ $terug ][ $rij->dag ] = true;
			}
		}
		foreach ( $zoekrijen as $rij ) {
			$b = self::bedrijf_van( (int) $rij->user_id );
			if ( ! isset( $per[ $b ] ) ) {
				$per[ $b ] = $leeg;
			}
			$term                         = (string) $rij->sleutel;
			$per[ $b ]['termen'][ $term ] = ( isset( $per[ $b ]['termen'][ $term ] ) ? $per[ $b ]['termen'][ $term ] : 0 ) + (int) $rij->aantal;
		}
		foreach ( $deelrijen as $rij ) {
			$b = self::bedrijf_van( (int) $rij->user_id );
			if ( ! isset( $per[ $b ] ) ) {
				$per[ $b ] = $leeg;
			}
			$deel                             = (string) $rij->sleutel;
			$per[ $b ]['onderdelen'][ $deel ] = ( isset( $per[ $b ]['onderdelen'][ $deel ] ) ? $per[ $b ]['onderdelen'][ $deel ] : 0 ) + (int) $rij->aantal;
		}

		$cutoff = gmdate( 'Y-m-d', time() - (int) $dagen * DAY_IN_SECONDS );
		foreach ( $dagen_rij as $rij ) {
			$b = self::bedrijf_van( (int) $rij->user_id );
			if ( ! isset( $per[ $b ] ) ) {
				$per[ $b ] = $leeg;
			}
			if ( $rij->dag >= $cutoff ) {
				$per[ $b ]['bezoekdagen'][ $rij->dag ] = true;
			}
			if ( $rij->dag >= $grens ) {
				$per[ $b ]['dagen30'][ $rij->dag ] = true;
			}
		}
		foreach ( $tellingen as $rij ) {
			$b = self::bedrijf_van( (int) $rij->user_id );
			if ( ! isset( $per[ $b ] ) ) {
				$per[ $b ] = $leeg;
			}
			$soort               = 'download' === $rij->soort ? 'download' : 'zoek';
			$per[ $b ][ $soort ] += (int) $rij->aantal;
		}
		foreach ( self::orders( $dagen ) as $order ) {
			$b = self::bedrijf_van( (int) $order->get_customer_id() );
			if ( ! isset( $per[ $b ] ) ) {
				$per[ $b ] = $leeg;
			}
			++$per[ $b ]['bestellingen'];
			$per[ $b ]['omzet'] += self::netto( $order );
			$moment               = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0;
			$per[ $b ]['laatste_bestelling'] = max( $per[ $b ]['laatste_bestelling'], $moment );
		}
		if ( class_exists( 'HDP_Trainingen' ) ) {
			$ttabel = HDP_Trainingen::tabel();
			$rijen  = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, COUNT(*) AS aantal FROM {$ttabel} WHERE status = %s AND aangemaakt >= %s GROUP BY user_id", HDP_Trainingen::STATUS_AANGEMELD, $vanaf ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $rijen as $rij ) {
				$b = self::bedrijf_van( (int) $rij->user_id );
				if ( ! isset( $per[ $b ] ) ) {
					$per[ $b ] = $leeg;
				}
				$per[ $b ]['trainingen'] += (int) $rij->aantal;
			}
		}

		$lijst = array();
		foreach ( $accounts as $hoofd_id => $leden ) {
			$hoofd = get_userdata( $hoofd_id );
			if ( ! $hoofd ) {
				continue;
			}
			$c       = isset( $per[ $hoofd_id ] ) ? $per[ $hoofd_id ] : $leeg;
			$laatst  = self::laatst_actief_bedrijf( $hoofd_id, $leden );
			arsort( $c['termen'] );
			arsort( $c['onderdelen'] );
			$weken = array();
			for ( $i = 11; $i >= 0; $i-- ) {
				$weken[] = isset( $c['weken'][ $i ] ) ? count( $c['weken'][ $i ] ) : 0;
			}
			$personen = array();
			foreach ( array_unique( array_merge( array( (int) $hoofd_id ), $leden ) ) as $id ) {
				$persoon = get_userdata( $id );
				if ( $persoon ) {
					$personen[] = array(
						'naam'   => $persoon->display_name,
						'rol'    => (int) $id === (int) $hoofd_id ? 'hoofdaccount' : 'collega',
						'laatst' => self::laatst_actief( $id ),
					);
				}
			}
			$relatie = class_exists( 'HDP_PowerAll_Dealers' ) ? (string) get_user_meta( $hoofd_id, HDP_PowerAll_Dealers::META_RELATIE, true ) : '';
			$lijst[] = array(
				'id'                 => (int) $hoofd_id,
				'bedrijf'            => self::bedrijfsnaam( $hoofd_id ),
				'naam'               => $hoofd->display_name,
				'email'              => $hoofd->user_email,
				'telefoon'           => (string) get_user_meta( $hoofd_id, 'billing_phone', true ),
				'klantnummer'        => $relatie,
				'merken'             => (string) get_user_meta( $hoofd_id, 'hdp_merken', true ),
				'collegas'           => count( array_diff( $leden, array( (int) $hoofd_id ) ) ),
				'laatst'             => $laatst,
				'bezoekdagen'        => count( $c['bezoekdagen'] ),
				'downloads'          => $c['download'],
				'zoeken'             => $c['zoek'],
				'bestellingen'       => $c['bestellingen'],
				'omzet'              => $c['omzet'],
				'laatste_bestelling' => $c['laatste_bestelling'],
				'trainingen'         => $c['trainingen'],
				'activiteit'         => self::activiteit( $laatst, count( $c['dagen30'] ) ),
				'termen'             => array_slice( array_map( 'strval', array_keys( $c['termen'] ) ), 0, 8 ),
				'onderdelen'         => $c['onderdelen'],
				'weken'              => $weken,
				'plaats'             => (string) get_user_meta( $hoofd_id, 'billing_city', true ),
				'personen'           => $personen,
			);
		}

		usort(
			$lijst,
			static function ( $a, $b ) {
				return strcasecmp( $a['bedrijf'], $b['bedrijf'] );
			}
		);
		return $lijst;
	}

	/* ---------------------------------------------------------------------
	 * Voor het overzicht: per week, de stappen naar een bestelling, en wat
	 * er net gebeurde
	 * ------------------------------------------------------------------- */

	/**
	 * Bestellingen en omzet per week, de laatste $weken weken, oudste eerst.
	 *
	 * @return array<int, array{week: string, nr: int, bestellingen: int, omzet: float}>
	 */
	public static function webshop_per_week( $weken = 12 ) {
		$per_week = array();
		foreach ( self::orders( $weken * 7 ) as $order ) {
			$moment = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0;
			if ( ! $moment ) {
				continue;
			}
			$sleutel = gmdate( 'o', $moment ) . gmdate( 'W', $moment );
			if ( ! isset( $per_week[ $sleutel ] ) ) {
				$per_week[ $sleutel ] = array( 0, 0.0 );
			}
			++$per_week[ $sleutel ][0];
			$per_week[ $sleutel ][1] += self::netto( $order );
		}
		$lijst = array();
		for ( $i = $weken - 1; $i >= 0; $i-- ) {
			$moment  = time() - $i * WEEK_IN_SECONDS;
			$sleutel = gmdate( 'o', $moment ) . gmdate( 'W', $moment );
			$lijst[] = array(
				'week'         => 'Week ' . (int) gmdate( 'W', $moment ),
				'nr'           => (int) gmdate( 'W', $moment ),
				'bestellingen' => isset( $per_week[ $sleutel ] ) ? $per_week[ $sleutel ][0] : 0,
				'omzet'        => isset( $per_week[ $sleutel ] ) ? $per_week[ $sleutel ][1] : 0.0,
			);
		}
		return $lijst;
	}

	/**
	 * Van bezoek naar bestelling: hoeveel bedrijven elke stap haalden. Wie
	 * bestelde, had ook iets in de winkelwagen en was in de webshop (ook als
	 * dat van voor de meting was), dus de stappen lopen nooit omhoog.
	 *
	 * @return array{portaal: int, webshop: int, winkelwagen: int, besteld: int}
	 */
	public static function trechter( $dagen ) {
		global $wpdb;
		$tabel  = self::tabel();
		$bekend = self::bekende_bedrijven();
		$rijen  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT DISTINCT user_id, soort FROM {$tabel} WHERE tijd >= %s AND ( soort IN ('bezoek','winkelwagen') OR ( soort = 'onderdeel' AND sleutel = 'webshop' ) )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::vanaf( $dagen )
			)
		);
		$stap = array( 'portaal' => array(), 'webshop' => array(), 'winkelwagen' => array(), 'besteld' => array() );
		foreach ( $rijen as $rij ) {
			$b = self::bedrijf_van( (int) $rij->user_id );
			if ( ! isset( $bekend[ $b ] ) ) {
				continue;
			}
			$naam                = 'bezoek' === $rij->soort ? 'portaal' : ( 'winkelwagen' === $rij->soort ? 'winkelwagen' : 'webshop' );
			$stap[ $naam ][ $b ] = true;
		}
		foreach ( self::orders( $dagen ) as $order ) {
			$b = self::bedrijf_van( (int) $order->get_customer_id() );
			if ( isset( $bekend[ $b ] ) ) {
				$stap['besteld'][ $b ] = true;
			}
		}
		$stap['winkelwagen'] += $stap['besteld'];
		$stap['webshop']     += $stap['winkelwagen'];
		$stap['portaal']     += $stap['webshop'];
		return array_map( 'count', $stap );
	}

	/**
	 * Wie iets deed, zoals in "Net gebeurd": de naam, en bij een collega het
	 * bedrijf erachter.
	 *
	 * @return string[] array( naam, bedrijf of leeg )
	 */
	private static function wie( $user_id ) {
		$bedrijf = self::bedrijf_van( $user_id );
		$naam    = self::bedrijfsnaam( $bedrijf );
		if ( $bedrijf !== (int) $user_id ) {
			$persoon = get_userdata( $user_id );
			return array( $persoon ? $persoon->display_name : $naam, $naam );
		}
		return array( $naam, '' );
	}

	/**
	 * Wat er de laatste $dagen dagen gebeurde, nieuwste eerst: bestellingen,
	 * bestellingen die niet in PowerAll kwamen, geopende documenten,
	 * zoekopdrachten zonder resultaat, trainingaanmeldingen en garantieclaims.
	 *
	 * @return array<int, array{tijd: int, soort: string, wie: string, bij: string, tekst: string}>
	 */
	public static function gebeurtenissen( $limiet = 8, $dagen = 7 ) {
		global $wpdb;
		$tabel  = self::tabel();
		$bekend = self::bekende_bedrijven();
		$lijst  = array();
		$voeg   = static function ( $tijd, $soort, $user_id, $tekst ) use ( &$lijst, $bekend ) {
			$user_id = (int) $user_id;
			if ( ! $tijd || ! $user_id || ! isset( $bekend[ self::bedrijf_van( $user_id ) ] ) ) {
				return;
			}
			list( $wie, $bij ) = self::wie( $user_id );
			$lijst[]           = array( 'tijd' => (int) $tijd, 'soort' => $soort, 'wie' => $wie, 'bij' => $bij, 'tekst' => $tekst );
		};

		foreach ( self::orders( $dagen ) as $order ) {
			$voeg( $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0, 'bestelling', $order->get_customer_id(), sprintf( 'bestelde %s · € %s', $order->get_order_number(), number_format( self::netto( $order ), 2, ',', '.' ) ) );
		}
		if ( class_exists( 'HDP_PowerAll_Orders' ) && 'aan' === HDP_PowerAll_Orders::modus() ) {
			foreach ( self::powerall_fouten() as $order ) {
				$moment = $order->get_date_modified() ? $order->get_date_modified()->getTimestamp() : 0;
				if ( $moment >= time() - $dagen * DAY_IN_SECONDS ) {
					$voeg( $moment, 'fout', $order->get_customer_id(), sprintf( 'bestelling %s kwam niet in PowerAll aan', $order->get_order_number() ) );
				}
			}
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- eigen tabellen, beheerscherm.
		$rijen = $wpdb->get_results( $wpdb->prepare( "SELECT tijd, user_id, soort, sleutel FROM {$tabel} WHERE tijd >= %s AND ( soort = 'download' OR ( soort IN ('zoek','webshopzoek') AND aantal = 0 ) ) ORDER BY tijd DESC LIMIT 40", self::vanaf( $dagen ) ) );
		foreach ( $rijen as $rij ) {
			$moment = strtotime( $rij->tijd . ' UTC' );
			if ( 'download' === $rij->soort ) {
				$titel = get_the_title( (int) $rij->sleutel );
				$voeg( $moment, 'download', $rij->user_id, $titel ? sprintf( 'opende "%s"', html_entity_decode( $titel ) ) : 'opende een document' );
			} else {
				$voeg( $moment, 'zoek', $rij->user_id, sprintf( 'zocht "%s": niets gevonden', $rij->sleutel ) );
			}
		}
		if ( class_exists( 'HDP_Trainingen' ) ) {
			$ttabel = HDP_Trainingen::tabel();
			foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT user_id, training_titel, aangemaakt FROM {$ttabel} WHERE status = %s AND aangemaakt >= %s ORDER BY aangemaakt DESC LIMIT 20", HDP_Trainingen::STATUS_AANGEMELD, self::vanaf( $dagen ) ) ) as $rij ) {
				$voeg( strtotime( $rij->aangemaakt . ' UTC' ), 'training', $rij->user_id, sprintf( 'meldde zich aan voor "%s"', $rij->training_titel ) );
			}
		}
		// phpcs:enable
		foreach ( self::recente_claims( $dagen ) as $claim ) {
			$voeg( $claim['tijd'], 'garantie', $claim['dealer_id'], sprintf( 'diende garantieclaim %s in', $claim['nummer'] ) );
		}

		usort(
			$lijst,
			static function ( $a, $b ) {
				return $b['tijd'] <=> $a['tijd'];
			}
		);
		return array_slice( $lijst, 0, $limiet );
	}

	/**
	 * Garantieclaims van de laatste dagen, van alle dealers. Ze staan in de
	 * garantiedatabase (Supabase); een kwartier bewaard, zodat het overzicht
	 * niet bij elke klik daarop wacht. Een storing geeft een lege lijst.
	 *
	 * @return array<int, array{tijd: int, dealer_id: int, nummer: string}>
	 */
	public static function recente_claims( $dagen = 7 ) {
		if ( ! class_exists( 'HDP_Supabase' ) || ! HDP_Supabase::beschikbaar( HDP_Supabase::APP ) ) {
			return array();
		}
		$sleutel = 'hdp_gebruik_claims_' . (int) $dagen;
		$lijst   = get_transient( $sleutel );
		if ( is_array( $lijst ) ) {
			return $lijst;
		}
		$rijen = HDP_Supabase::selecteer(
			HDP_Supabase::APP,
			'claims',
			array(
				'select'       => 'nummer,dealer_id,ingediend_op',
				'ingediend_op' => 'gte.' . gmdate( 'Y-m-d\TH:i:s\Z', time() - (int) $dagen * DAY_IN_SECONDS ),
				'order'        => 'ingediend_op.desc',
				'limit'        => '20',
			)
		);
		$lijst = array();
		if ( ! is_wp_error( $rijen ) && is_array( $rijen ) ) {
			foreach ( $rijen as $rij ) {
				$lijst[] = array(
					'tijd'      => (int) strtotime( (string) $rij['ingediend_op'] ),
					'dealer_id' => (int) $rij['dealer_id'],
					'nummer'    => (string) $rij['nummer'],
				);
			}
		}
		set_transient( $sleutel, $lijst, is_wp_error( $rijen ) ? 2 * MINUTE_IN_SECONDS : 15 * MINUTE_IN_SECONDS );
		return $lijst;
	}

	/* ---------------------------------------------------------------------
	 * Downloads
	 * ------------------------------------------------------------------- */

	/** Downloads in de periode, vaakst eerst. */
	public static function top_downloads( $dagen, $limiet = 15 ) {
		global $wpdb;
		$tabel = self::tabel();

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT sleutel AS post_id, COUNT(*) AS keer, COUNT(DISTINCT user_id) AS dealers FROM {$tabel} WHERE soort = 'download' AND tijd >= %s GROUP BY sleutel ORDER BY keer DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::vanaf( $dagen ),
				$limiet
			)
		);
	}

	/** Gepubliceerde documenten die in de periode door niemand zijn geopend. */
	public static function ongebruikte_downloads( $dagen ) {
		global $wpdb;
		$tabel    = self::tabel();
		$gebruikt = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare( "SELECT DISTINCT sleutel FROM {$tabel} WHERE soort = 'download' AND tijd >= %s", self::vanaf( $dagen ) ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return get_posts(
			array(
				'post_type'      => HDP_Downloads_CPT::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'post__not_in'   => array_map( 'intval', $gebruikt ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- beheerscherm, kleine set.
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Export naar Excel
	 * ------------------------------------------------------------------- */

	public static function export_url( $dagen ) {
		return wp_nonce_url( add_query_arg( array( 'action' => self::EXPORT_ACTIE, 'periode' => (int) $dagen ), admin_url( 'admin-post.php' ) ), self::EXPORT_ACTIE );
	}

	/** De rijen van de export: één per bedrijf. */
	public static function export_rijen( $dagen ) {
		$rijen = array(
			array( 'Bedrijf', 'Naam', 'E-mailadres', 'Telefoon', 'Klantnummer PowerAll', 'Merken', 'Collega\'s', 'Account', 'Activiteit', 'Laatst geweest', 'Bezoekdagen', 'Documenten geopend', 'Zoekopdrachten', 'Bestellingen', 'Omzet excl. btw', 'Trainingaanmeldingen' ),
		);
		foreach ( self::dealerlijst( $dagen ) as $rij ) {
			$rijen[] = array(
				$rij['bedrijf'],
				$rij['naam'],
				$rij['email'],
				$rij['telefoon'],
				$rij['klantnummer'],
				$rij['merken'],
				$rij['collegas'],
				$rij['klantnummer'] && class_exists( 'HDP_PowerAll_Dealers' ) ? HDP_PowerAll_Dealers::accountstatus( $rij['id'] )['tekst'] : 'Zelf aangemeld',
				$rij['activiteit']['tekst'],
				$rij['laatst'] ? wp_date( 'd-m-Y', $rij['laatst'] ) : 'Nog nooit',
				$rij['bezoekdagen'],
				$rij['downloads'],
				$rij['zoeken'],
				$rij['bestellingen'],
				number_format( $rij['omzet'], 2, ',', '' ),
				$rij['trainingen'],
			);
		}
		return $rijen;
	}

	/** Een CSV die Excel meteen goed opent: puntkomma's en een BOM voor de accenten. */
	public static function exporteer() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Geen toegang.' );
		}
		check_admin_referer( self::EXPORT_ACTIE );
		$dagen = isset( $_GET['periode'] ) ? (int) $_GET['periode'] : 30;
		if ( ! isset( self::PERIODES[ $dagen ] ) ) {
			$dagen = 30;
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="portaalgebruik-' . wp_date( 'Y-m-d' ) . '-' . $dagen . '-dagen.csv"' );
		$uit = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fwrite( $uit, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		foreach ( self::export_rijen( $dagen ) as $rij ) {
			fputcsv( $uit, array_map( array( __CLASS__, 'veilige_cel' ), $rij ), ';' );
		}
		fclose( $uit ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/** Een cel die met =, +, - of @ begint zou Excel als formule uitvoeren. */
	public static function veilige_cel( $waarde ) {
		if ( is_string( $waarde ) && '' !== $waarde && in_array( $waarde[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $waarde;
		}
		return $waarde;
	}
}
