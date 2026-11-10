<?php
/**
 * Portaalgebruik: wat dealers in het portaal doen.
 *
 * Houdt drie dingen bij, alleen van dealers (beheerders tellen niet mee):
 * een bezoek (hooguit één per dealer per dag), een zoekopdracht in de
 * zoekbalk (met het aantal treffers) en een download. Bestellingen hoeven
 * niet apart bijgehouden te worden: die staan al in WooCommerce.
 *
 * Alles blijft in de eigen database; er gaat niets naar een externe
 * dienst en er worden geen cookies gezet. Na STERMIJN_DAGEN ruimt het
 * zichzelf op.
 *
 * Het overzicht staat in wp-admin onder Dashboard > Portaalgebruik.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HDP_Gebruik {

	const TABEL       = 'hdp_gebruik';
	const DB_VERSIE   = '1';
	const DB_OPTIE    = 'hdp_gebruik_db_versie';
	const LAATST_META = 'hdp_laatst_actief';

	/** Ruim een jaar, zodat je een maand kunt vergelijken met dezelfde maand vorig jaar. */
	const STERMIJN_DAGEN = 400;

	/** Zonder bezoek in zoveel dagen staat een dealer op de bellijst. */
	const STIL_DAGEN = 30;

	/** Dezelfde zoekterm in de webshop telt binnen deze tijd maar één keer (filters aanklikken, terugbladeren). */
	const WEBSHOPZOEK_PAUZE = 30 * MINUTE_IN_SECONDS;

	const EXPORT_ACTIE = 'hdp_gebruik_export';

	public static function init() {
		// Zelfherstellend: een deploy (rsync) activeert de plugin niet
		// opnieuw, dus de tabel hier aanmaken zodra de versie niet klopt.
		add_action( 'init', array( __CLASS__, 'controleer_tabel' ) );
		add_action( 'template_redirect', array( __CLASS__, 'noteer_bezoek' ) );
		add_action( 'template_redirect', array( __CLASS__, 'noteer_webshopzoek' ), 20 );
		add_action( 'admin_menu', array( __CLASS__, 'registreer_menu' ) );
		add_action( 'admin_post_' . self::EXPORT_ACTIE, array( __CLASS__, 'exporteer' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_profiel' ), 20 );
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
	 * Wanneer deze dealer voor het laatst iets deed.
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

	/**
	 * Legt één gebeurtenis vast.
	 *
	 * @param string $soort   'bezoek', 'zoek' of 'download'.
	 * @param string $sleutel De zoekterm of het download-ID.
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
			self::noteer( 'bezoek', '', 0, $user_id );
		}
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
	 * Cijfers
	 * ------------------------------------------------------------------- */

	private static function vanaf( $dagen ) {
		return gmdate( 'Y-m-d H:i:s', time() - (int) $dagen * DAY_IN_SECONDS );
	}

	/** Alle goedgekeurde dealers (geen beheerders). */
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

	/** Kerncijfers over een periode. */
	public static function kerncijfers( $dagen ) {
		global $wpdb;
		$tabel = self::tabel();
		$vanaf = self::vanaf( $dagen );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- eigen tabel, beheerscherm.
		$cijfers = array(
			'actieve_dealers' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(DISTINCT user_id) FROM {$tabel} WHERE soort = 'bezoek' AND tijd >= %s", $vanaf ) ),
			'bezoeken'        => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabel} WHERE soort = 'bezoek' AND tijd >= %s", $vanaf ) ),
			'zoekopdrachten'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabel} WHERE soort = 'zoek' AND tijd >= %s", $vanaf ) ),
			'zonder_treffer'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabel} WHERE soort = 'zoek' AND aantal = 0 AND tijd >= %s", $vanaf ) ),
			'downloads'       => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabel} WHERE soort = 'download' AND tijd >= %s", $vanaf ) ),
			'webshopzoek'     => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabel} WHERE soort = 'webshopzoek' AND tijd >= %s", $vanaf ) ),
			'webshop_niets'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabel} WHERE soort = 'webshopzoek' AND aantal = 0 AND tijd >= %s", $vanaf ) ),
		);
		// phpcs:enable

		$cijfers['dealers'] = count( self::dealers() );

		return $cijfers;
	}

	/**
	 * Actieve dealers per week, de laatste $weken weken, oudste eerst.
	 *
	 * @return array<int, array{week: string, aantal: int}>
	 */
	public static function actief_per_week( $weken = 12 ) {
		global $wpdb;
		$tabel = self::tabel();
		$rijen = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT YEARWEEK(tijd, 3) AS week, COUNT(DISTINCT user_id) AS aantal FROM {$tabel} WHERE soort = 'bezoek' AND tijd >= %s GROUP BY week", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				self::vanaf( $weken * 7 )
			),
			OBJECT_K
		);

		$lijst = array();
		for ( $i = $weken - 1; $i >= 0; $i-- ) {
			$moment  = time() - $i * WEEK_IN_SECONDS;
			$sleutel = gmdate( 'o', $moment ) . gmdate( 'W', $moment );
			$lijst[] = array(
				'week'   => 'Week ' . (int) gmdate( 'W', $moment ),
				'aantal' => isset( $rijen[ $sleutel ] ) ? (int) $rijen[ $sleutel ]->aantal : 0,
			);
		}
		return $lijst;
	}

	/**
	 * Dealers die al STIL_DAGEN niet zijn geweest (of nog nooit), langst
	 * weg eerst — een lijstje voor de buitendienst.
	 */
	public static function stille_dealers() {
		$grens = time() - self::STIL_DAGEN * DAY_IN_SECONDS;
		$lijst = array();

		foreach ( self::dealers() as $dealer ) {
			$laatst = self::laatst_actief( $dealer->ID );
			if ( $laatst && $laatst >= $grens ) {
				continue;
			}
			$lijst[] = array(
				'id'      => $dealer->ID,
				'bedrijf' => (string) get_user_meta( $dealer->ID, 'billing_company', true ),
				'naam'    => $dealer->display_name,
				'email'   => $dealer->user_email,
				'telefoon' => (string) get_user_meta( $dealer->ID, 'billing_phone', true ),
				'laatst'  => $laatst,
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
			$lijst[ (int) $a->training_id ]['bedrijven'][ (int) $a->user_id ] = true;
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

	/**
	 * Alles van één dealer bij elkaar, voor zijn profiel en de export.
	 *
	 * @return array{laatst: int, bezoeken: int, downloads: int, zoeken: int, webshopzoek: int, termen: array, bestellingen: int, omzet: float, trainingen: int}
	 */
	public static function per_dealer( $user_id, $dagen ) {
		global $wpdb;
		$tabel = self::tabel();
		$vanaf = self::vanaf( $dagen );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- eigen tabel.
		$tellingen = $wpdb->get_results( $wpdb->prepare( "SELECT soort, COUNT(*) AS aantal FROM {$tabel} WHERE user_id = %d AND tijd >= %s GROUP BY soort", $user_id, $vanaf ), OBJECT_K );
		$termen    = $wpdb->get_col( $wpdb->prepare( "SELECT sleutel FROM {$tabel} WHERE user_id = %d AND soort IN ('zoek','webshopzoek') AND tijd >= %s GROUP BY sleutel ORDER BY COUNT(*) DESC, MAX(tijd) DESC LIMIT 8", $user_id, $vanaf ) );
		// phpcs:enable

		$aantal = static function ( $soort ) use ( $tellingen ) {
			return isset( $tellingen[ $soort ] ) ? (int) $tellingen[ $soort ]->aantal : 0;
		};

		$bestellingen = 0;
		$omzet        = 0.0;
		if ( function_exists( 'wc_get_orders' ) ) {
			foreach ( wc_get_orders( array( 'customer_id' => (int) $user_id, 'limit' => -1, 'status' => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ), 'date_created' => '>=' . ( time() - (int) $dagen * DAY_IN_SECONDS ) ) ) as $order ) {
				++$bestellingen;
				$omzet += (float) $order->get_total() - (float) $order->get_total_tax();
			}
		}

		$trainingen = 0;
		if ( class_exists( 'HDP_Trainingen' ) ) {
			$ttabel     = HDP_Trainingen::tabel();
			$trainingen = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$ttabel} WHERE user_id = %d AND status = %s AND aangemaakt >= %s", $user_id, HDP_Trainingen::STATUS_AANGEMELD, $vanaf ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		return array(
			'laatst'       => self::laatst_actief( $user_id ),
			'bezoeken'     => $aantal( 'bezoek' ),
			'downloads'    => $aantal( 'download' ),
			'zoeken'       => $aantal( 'zoek' ),
			'webshopzoek'  => $aantal( 'webshopzoek' ),
			'termen'       => $termen,
			'bestellingen' => $bestellingen,
			'omzet'        => $omzet,
			'trainingen'   => $trainingen,
		);
	}

	/* ---------------------------------------------------------------------
	 * Export naar Excel
	 * ------------------------------------------------------------------- */

	public static function export_url( $dagen ) {
		return wp_nonce_url( add_query_arg( array( 'action' => self::EXPORT_ACTIE, 'periode' => (int) $dagen ), admin_url( 'admin-post.php' ) ), self::EXPORT_ACTIE );
	}

	/** De rijen van de export: één per dealer. */
	public static function export_rijen( $dagen ) {
		$rijen = array(
			array( 'Bedrijf', 'Naam', 'E-mailadres', 'Telefoon', 'Klantnummer PowerAll', 'Merken', 'Account', 'Laatst geweest', 'Bezoekdagen', 'Documenten geopend', 'Zoekopdrachten', 'Bestellingen', 'Omzet excl. btw', 'Trainingaanmeldingen' ),
		);
		foreach ( self::dealers() as $dealer ) {
			$cijfers = self::per_dealer( $dealer->ID, $dagen );
			$relatie = class_exists( 'HDP_PowerAll_Dealers' ) ? (string) get_user_meta( $dealer->ID, HDP_PowerAll_Dealers::META_RELATIE, true ) : '';
			$rijen[] = array(
				(string) get_user_meta( $dealer->ID, 'billing_company', true ),
				$dealer->display_name,
				$dealer->user_email,
				(string) get_user_meta( $dealer->ID, 'billing_phone', true ),
				$relatie,
				(string) get_user_meta( $dealer->ID, 'hdp_merken', true ),
				$relatie ? HDP_PowerAll_Dealers::accountstatus( $dealer->ID )['tekst'] : 'Zelf aangemeld',
				$cijfers['laatst'] ? wp_date( 'd-m-Y', $cijfers['laatst'] ) : 'Nog nooit',
				$cijfers['bezoeken'],
				$cijfers['downloads'],
				$cijfers['zoeken'] + $cijfers['webshopzoek'],
				$cijfers['bestellingen'],
				number_format( $cijfers['omzet'], 2, ',', '' ),
				$cijfers['trainingen'],
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

	/* ---------------------------------------------------------------------
	 * Op het profiel van een dealer
	 * ------------------------------------------------------------------- */

	public static function render_profiel( $user ) {
		if ( ! current_user_can( 'manage_options' ) || ! HDP_Roles::mag_portaal_zien( $user->ID ) || user_can( $user->ID, 'manage_options' ) ) {
			return;
		}
		$c = self::per_dealer( $user->ID, 90 );
		$j = self::per_dealer( $user->ID, 365 );
		?>
		<h2 id="hdp-portaalgebruik">Portaalgebruik</h2>
		<table class="form-table" role="presentation">
			<tr><th>Laatst geweest</th><td><?php echo esc_html( $c['laatst'] ? wp_date( 'j F Y', $c['laatst'] ) : 'Nog nooit (sinds de meting)' ); ?><?php echo self::is_testaccount( $user->ID ) ? ' <em>(testaccount: telt niet mee in de cijfers)</em>' : ''; ?></td></tr>
			<tr><th>Laatste 3 maanden</th><td><?php echo esc_html( sprintf( '%d bezoekdagen · %d documenten geopend · %d zoekopdrachten · %d trainingaanmeldingen', $c['bezoeken'], $c['downloads'], $c['zoeken'] + $c['webshopzoek'], $c['trainingen'] ) ); ?></td></tr>
			<tr><th>Webshop, laatste 12 maanden</th><td><?php echo esc_html( sprintf( '%d bestellingen', $j['bestellingen'] ) ); ?><?php echo function_exists( 'wc_price' ) ? ', ' . wp_kses_post( wc_price( $j['omzet'] ) ) . ' excl. btw' : ''; ?></td></tr>
			<?php if ( $c['termen'] ) : ?>
				<tr><th>Zocht naar</th><td><?php echo esc_html( implode( ', ', $c['termen'] ) ); ?></td></tr>
			<?php endif; ?>
		</table>
		<?php
	}

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

	/**
	 * Bestellingen en omzet (excl. btw) per dealer in de periode, hoogste
	 * omzet eerst. Komt rechtstreeks uit WooCommerce.
	 */
	public static function webshop_per_dealer( $dagen ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$orders = wc_get_orders(
			array(
				'limit'        => -1,
				'status'       => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ),
				'date_created' => '>=' . ( time() - (int) $dagen * DAY_IN_SECONDS ),
			)
		);

		$per_dealer = array();
		foreach ( $orders as $order ) {
			$id = (int) $order->get_customer_id();
			if ( $id && self::is_testaccount( $id ) ) {
				continue;
			}
			if ( ! isset( $per_dealer[ $id ] ) ) {
				$per_dealer[ $id ] = array(
					'id'      => $id,
					'bedrijf' => $order->get_billing_company() ? $order->get_billing_company() : trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
					'aantal'  => 0,
					'omzet'   => 0.0,
					'laatst'  => 0,
				);
			}
			++$per_dealer[ $id ]['aantal'];
			$per_dealer[ $id ]['omzet'] += (float) $order->get_total() - (float) $order->get_total_tax();
			$moment                      = $order->get_date_created() ? $order->get_date_created()->getTimestamp() : 0;
			$per_dealer[ $id ]['laatst'] = max( $per_dealer[ $id ]['laatst'], $moment );
		}

		usort(
			$per_dealer,
			static function ( $a, $b ) {
				return $b['omzet'] <=> $a['omzet'];
			}
		);

		return $per_dealer;
	}

	/* ---------------------------------------------------------------------
	 * Beheerscherm
	 * ------------------------------------------------------------------- */

	const PERIODES = array(
		30  => 'Laatste 30 dagen',
		90  => 'Laatste 3 maanden',
		365 => 'Laatste 12 maanden',
	);

	public static function registreer_menu() {
		add_dashboard_page( 'Portaalgebruik', 'Portaalgebruik', 'manage_options', 'hdp-gebruik', array( __CLASS__, 'render_scherm' ) );
	}

	private static function datum( $tijdstempel ) {
		return $tijdstempel ? wp_date( 'j M Y', $tijdstempel ) : '—';
	}

	public static function render_scherm() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$dagen = isset( $_GET['periode'] ) ? (int) $_GET['periode'] : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- alleen een weergavekeuze.
		if ( ! isset( self::PERIODES[ $dagen ] ) ) {
			$dagen = 30;
		}

		$cijfers    = self::kerncijfers( $dagen );
		$per_week   = self::actief_per_week( 12 );
		$hoogste    = max( 1, max( wp_list_pluck( $per_week, 'aantal' ) ) );
		$stil       = self::stille_dealers();
		$niets      = self::zoektermen( $dagen, true );
		$termen     = self::zoektermen( $dagen );
		$downloads  = self::top_downloads( $dagen );
		$ongebruikt = self::ongebruikte_downloads( $dagen );
		$webshop    = self::webshop_per_dealer( $dagen );
		$shop_niets = self::zoektermen( $dagen, true, 20, 'webshopzoek' );
		$uitrol     = self::uitrol();
		$trainingen = self::trainingen( $dagen );
		$pa_fouten  = self::powerall_fouten();
		?>
		<div class="wrap hdp-gebruik">
			<style>
				.hdp-gebruik .hdp-g-periodes a { margin-left: 0.4rem; }
				.hdp-gebruik .hdp-g-periodes a.is-actief { font-weight: 700; text-decoration: none; color: #1d2327; }
				.hdp-gebruik .hdp-g-tegels { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 12px; margin: 16px 0 24px; }
				.hdp-gebruik .hdp-g-tegel { background: #fff; border: 1px solid #dcdcde; border-radius: 6px; padding: 14px 16px; }
				.hdp-gebruik .hdp-g-tegel strong { display: block; font-size: 28px; line-height: 1.1; color: #1d2327; }
				.hdp-gebruik .hdp-g-tegel span { color: #50575e; font-size: 13px; }
				.hdp-gebruik .hdp-g-raster { display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 20px; align-items: start; }
				.hdp-gebruik .hdp-g-blok { background: #fff; border: 1px solid #dcdcde; border-radius: 6px; padding: 4px 16px 16px; }
				.hdp-gebruik .hdp-g-blok h2 { font-size: 15px; margin: 14px 0 4px; }
				.hdp-gebruik .hdp-g-uitleg { margin: 0 0 10px; color: #50575e; }
				.hdp-gebruik .hdp-g-blok table { border: 0; box-shadow: none; }
				.hdp-gebruik .hdp-g-blok td.num, .hdp-gebruik .hdp-g-blok th.num { text-align: right; white-space: nowrap; }
				.hdp-gebruik .hdp-g-week { display: grid; grid-template-columns: 70px 1fr 32px; align-items: center; gap: 8px; margin: 4px 0; font-size: 12px; }
				.hdp-gebruik .hdp-g-week i { display: block; height: 12px; border-radius: 0 3px 3px 0; background: #c41214; }
				.hdp-gebruik .hdp-g-week b { text-align: right; font-weight: 600; }
				.hdp-gebruik .hdp-g-leeg { color: #50575e; font-style: italic; }
				.hdp-gebruik .hdp-g-trechter { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 8px; margin: 8px 0 12px; }
				.hdp-gebruik .hdp-g-trechter div { background: #f6f7f7; border-radius: 4px; padding: 8px 10px; }
				.hdp-gebruik .hdp-g-trechter strong { display: block; font-size: 20px; }
				.hdp-gebruik .hdp-g-trechter span { font-size: 12px; color: #50575e; }
			</style>

			<h1>Portaalgebruik</h1>
			<p class="hdp-g-periodes">
				<strong>Periode:</strong>
					<?php foreach ( self::PERIODES as $waarde => $label ) : ?>
						<a href="<?php echo esc_url( add_query_arg( 'periode', $waarde ) ); ?>" class="<?php echo $waarde === $dagen ? 'is-actief' : ''; ?>"><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
			</p>
			<p class="description">Alleen goedgekeurde dealers tellen mee; beheerders en testaccounts (aan te vinken in het gebruikersprofiel) niet. Alles staat in de eigen database; gegevens ouder dan <?php echo (int) self::STERMIJN_DAGEN; ?> dagen worden automatisch verwijderd.</p>
			<p><a class="button" href="<?php echo esc_url( self::export_url( $dagen ) ); ?>">Alle dealers downloaden voor Excel</a> <span class="description">Eén regel per dealer, met de cijfers over de gekozen periode.</span></p>

			<?php if ( $pa_fouten ) : ?>
				<div class="notice notice-error inline">
					<?php if ( 'aan' === HDP_PowerAll_Orders::modus() ) : ?>
						<p><strong><?php echo esc_html( count( $pa_fouten ) . ( 1 === count( $pa_fouten ) ? ' bestelling is' : ' bestellingen zijn' ) ); ?> niet in PowerAll aangekomen.</strong> Voer ze met de hand in PowerAll in, of open de bestelling en kies bij Bestelacties "Opnieuw naar PowerAll versturen".</p>
					<?php else : ?>
						<p><strong>Bij <?php echo esc_html( count( $pa_fouten ) . ( 1 === count( $pa_fouten ) ? ' bestelling' : ' bestellingen' ) ); ?> ging de proef met PowerAll mis.</strong> Er gaat nu nog niets echt naar PowerAll, maar zo zou het straks ook misgaan.</p>
					<?php endif; ?>
					<ul style="margin-top:0">
						<?php foreach ( array_slice( $pa_fouten, 0, 10 ) as $order ) : ?>
							<li><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">Bestelling <?php echo esc_html( $order->get_order_number() ); ?></a> — <?php echo esc_html( $order->get_billing_company() ? $order->get_billing_company() : $order->get_formatted_billing_full_name() ); ?>: <?php echo esc_html( (string) $order->get_meta( HDP_PowerAll_Orders::META_FOUT ) ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<div class="hdp-g-tegels">
				<div class="hdp-g-tegel"><strong><?php echo (int) $cijfers['actieve_dealers']; ?> <small>/ <?php echo (int) $cijfers['dealers']; ?></small></strong><span>dealers actief geweest</span></div>
				<div class="hdp-g-tegel"><strong><?php echo (int) $cijfers['bezoeken']; ?></strong><span>bezoekdagen</span></div>
				<div class="hdp-g-tegel"><strong><?php echo (int) $cijfers['zoekopdrachten']; ?></strong><span>zoekopdrachten, waarvan <?php echo (int) $cijfers['zonder_treffer']; ?> zonder resultaat</span></div>
				<div class="hdp-g-tegel"><strong><?php echo (int) $cijfers['downloads']; ?></strong><span>documenten geopend of gedownload</span></div>
				<div class="hdp-g-tegel"><strong><?php echo (int) $cijfers['webshopzoek']; ?></strong><span>keer gezocht in de webshop, waarvan <?php echo (int) $cijfers['webshop_niets']; ?> zonder resultaat</span></div>
				<div class="hdp-g-tegel"><strong><?php echo (int) array_sum( wp_list_pluck( $trainingen, 'aanmeldingen' ) ); ?></strong><span>aanmeldingen voor trainingen</span></div>
				<div class="hdp-g-tegel"><strong><?php echo (int) array_sum( wp_list_pluck( $webshop, 'aantal' ) ); ?></strong><span>bestellingen, <?php echo function_exists( 'wc_price' ) ? wp_kses_post( wc_price( array_sum( wp_list_pluck( $webshop, 'omzet' ) ) ) ) : ''; ?> excl. btw</span></div>
			</div>

			<div class="hdp-g-raster">
				<?php if ( $uitrol['totaal'] ) : ?>
					<section class="hdp-g-blok">
						<h2>Uitnodigingen (dealers uit PowerAll)</h2>
						<p class="hdp-g-uitleg">Hoe ver de dealers uit PowerAll zijn met hun account. Dit telt altijd alles, los van de gekozen periode.</p>
						<div class="hdp-g-trechter">
							<div><strong><?php echo (int) $uitrol['totaal']; ?></strong><span>gekoppeld</span></div>
							<div><strong><?php echo (int) ( $uitrol['totaal'] - $uitrol['niet'] ); ?></strong><span>uitgenodigd</span></div>
							<div><strong><?php echo (int) $uitrol['gekozen']; ?></strong><span>wachtwoord gekozen</span></div>
							<div><strong><?php echo (int) $uitrol['geweest']; ?></strong><span>al eens in het portaal</span></div>
						</div>
						<?php if ( $uitrol['nalopen'] ) : ?>
							<p class="hdp-g-uitleg">Uitgenodigd, maar nog geen wachtwoord gekozen. Een verlopen uitnodiging stuur je opnieuw via Gebruikers &gt; Dealers uit PowerAll.</p>
							<table class="widefat striped">
								<thead><tr><th>Dealer</th><th>Klantnummer</th><th>Stand</th></tr></thead>
								<tbody>
								<?php foreach ( array_slice( $uitrol['nalopen'], 0, 25 ) as $dealer ) : ?>
									<tr>
										<td><a href="<?php echo esc_url( get_edit_user_link( $dealer['id'] ) ); ?>"><?php echo esc_html( $dealer['naam'] ); ?></a><br><small><?php echo esc_html( $dealer['email'] ); ?></small></td>
										<td><?php echo esc_html( $dealer['relatie'] ); ?></td>
										<td><?php echo esc_html( $dealer['status']['tekst'] ); ?></td>
									</tr>
								<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</section>
				<?php endif; ?>

				<section class="hdp-g-blok">
					<h2>Niet meer geweest</h2>
					<p class="hdp-g-uitleg">Dealers die <?php echo (int) self::STIL_DAGEN; ?> dagen of langer niet in het portaal zijn geweest, langst weg bovenaan. Een lijstje om eens te bellen.</p>
					<?php if ( $stil ) : ?>
						<table class="widefat striped">
							<thead><tr><th>Dealer</th><th>Contact</th><th class="num">Laatst geweest</th></tr></thead>
							<tbody>
							<?php foreach ( array_slice( $stil, 0, 25 ) as $dealer ) : ?>
								<tr>
									<td><a href="<?php echo esc_url( get_edit_user_link( $dealer['id'] ) ); ?>"><?php echo esc_html( $dealer['bedrijf'] ? $dealer['bedrijf'] : $dealer['naam'] ); ?></a>
									<?php
									if ( $dealer['bedrijf'] ) :
										?>
										<br><small><?php echo esc_html( $dealer['naam'] ); ?></small><?php endif; ?></td>
									<td><a href="mailto:<?php echo esc_attr( $dealer['email'] ); ?>"><?php echo esc_html( $dealer['email'] ); ?></a>
									<?php
									if ( $dealer['telefoon'] ) :
										?>
										<br><small><?php echo esc_html( $dealer['telefoon'] ); ?></small><?php endif; ?></td>
									<td class="num"><?php echo esc_html( $dealer['laatst'] ? self::datum( $dealer['laatst'] ) : 'Nog nooit (sinds de meting)' ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
						<?php if ( count( $stil ) > 25 ) : ?>
							<p class="hdp-g-uitleg">En nog <?php echo (int) ( count( $stil ) - 25 ); ?> andere.</p>
						<?php endif; ?>
					<?php else : ?>
						<p class="hdp-g-leeg">Alle dealers zijn de afgelopen <?php echo (int) self::STIL_DAGEN; ?> dagen geweest.</p>
					<?php endif; ?>
				</section>

				<section class="hdp-g-blok">
					<h2>Actieve dealers per week</h2>
					<p class="hdp-g-uitleg">Hoeveel verschillende dealers per week minstens één keer in het portaal waren.</p>
					<?php foreach ( $per_week as $week ) : ?>
						<div class="hdp-g-week">
							<span><?php echo esc_html( $week['week'] ); ?></span>
							<i style="width: <?php echo esc_attr( (string) round( $week['aantal'] / $hoogste * 100 ) ); ?>%"></i>
							<b><?php echo (int) $week['aantal']; ?></b>
						</div>
					<?php endforeach; ?>
				</section>

				<section class="hdp-g-blok">
					<h2>Gezocht, niets gevonden</h2>
					<p class="hdp-g-uitleg">Waar dealers naar zochten zonder resultaat. Vaak een artikel of document dat ontbreekt, of een andere schrijfwijze.</p>
					<?php self::render_termen( $niets ); ?>
				</section>

				<section class="hdp-g-blok">
					<h2>Webshop: gezocht, niets gevonden</h2>
					<p class="hdp-g-uitleg">Zoekopdrachten in de webshop zonder één artikel als resultaat. Het artikel ontbreekt, heet anders, of hoort bij een merk dat deze dealers niet voeren.</p>
					<?php self::render_termen( $shop_niets ); ?>
				</section>

				<section class="hdp-g-blok">
					<h2>Trainingen</h2>
					<p class="hdp-g-uitleg">Aanmeldingen en beoordelingen in deze periode (cijfer van 1 tot 10).</p>
					<?php if ( $trainingen ) : ?>
						<table class="widefat striped">
							<thead><tr><th>Training</th><th class="num">Aanmeldingen</th><th class="num">Bedrijven</th><th class="num">Gemiddeld cijfer</th></tr></thead>
							<tbody>
							<?php foreach ( $trainingen as $training ) : ?>
								<tr>
									<td><?php echo get_post( $training['id'] ) ? '<a href="' . esc_url( (string) get_edit_post_link( $training['id'] ) ) . '">' . esc_html( $training['titel'] ) . '</a>' : esc_html( $training['titel'] ); ?></td>
									<td class="num"><?php echo (int) $training['aanmeldingen']; ?></td>
									<td class="num"><?php echo (int) $training['bedrijven']; ?></td>
									<td class="num"><?php echo $training['cijfers'] ? esc_html( number_format_i18n( $training['gemiddeld'], 1 ) . ' (' . $training['cijfers'] . 'x)' ) : '—'; ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="hdp-g-leeg">In deze periode geen aanmeldingen of beoordelingen.</p>
					<?php endif; ?>
				</section>

				<section class="hdp-g-blok">
					<h2>Meest gezocht</h2>
					<p class="hdp-g-uitleg">Alle zoekopdrachten in de zoekbalk bovenin, vaakst eerst.</p>
					<?php self::render_termen( $termen ); ?>
				</section>

				<section class="hdp-g-blok">
					<h2>Meest geopende documenten</h2>
					<?php if ( $downloads ) : ?>
						<table class="widefat striped">
							<thead><tr><th>Document</th><th class="num">Keer</th><th class="num">Dealers</th></tr></thead>
							<tbody>
							<?php foreach ( $downloads as $rij ) : ?>
								<?php $titel = get_the_title( (int) $rij->post_id ); ?>
								<tr>
									<td><?php echo $titel ? '<a href="' . esc_url( (string) get_edit_post_link( (int) $rij->post_id ) ) . '">' . esc_html( $titel ) . '</a>' : '<em>Verwijderd document</em>'; ?></td>
									<td class="num"><?php echo (int) $rij->keer; ?></td>
									<td class="num"><?php echo (int) $rij->dealers; ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="hdp-g-leeg">In deze periode nog niets geopend.</p>
					<?php endif; ?>
				</section>

				<section class="hdp-g-blok">
					<h2>Door niemand geopend</h2>
					<p class="hdp-g-uitleg">Documenten die in deze periode door geen enkele dealer zijn geopend. Verouderd, verkeerd genoemd, of niet te vinden?</p>
					<?php if ( $ongebruikt ) : ?>
						<table class="widefat striped">
							<thead><tr><th>Document</th><th class="num">Online sinds</th></tr></thead>
							<tbody>
							<?php foreach ( array_slice( $ongebruikt, 0, 25 ) as $post ) : ?>
								<tr>
									<td><a href="<?php echo esc_url( (string) get_edit_post_link( $post->ID ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></td>
									<td class="num"><?php echo esc_html( self::datum( (int) get_post_time( 'U', true, $post ) ) ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
						<?php if ( count( $ongebruikt ) > 25 ) : ?>
							<p class="hdp-g-uitleg">En nog <?php echo (int) ( count( $ongebruikt ) - 25 ); ?> andere.</p>
						<?php endif; ?>
					<?php else : ?>
						<p class="hdp-g-leeg">Elk document is in deze periode minstens één keer geopend.</p>
					<?php endif; ?>
				</section>

				<section class="hdp-g-blok">
					<h2>Webshop per dealer</h2>
					<p class="hdp-g-uitleg">Bestellingen die in behandeling, afgerond of in de wacht staan; omzet exclusief btw.</p>
					<?php if ( $webshop ) : ?>
						<table class="widefat striped">
							<thead><tr><th>Dealer</th><th class="num">Bestellingen</th><th class="num">Omzet</th><th class="num">Laatste</th></tr></thead>
							<tbody>
							<?php foreach ( array_slice( $webshop, 0, 25 ) as $rij ) : ?>
								<tr>
									<td><?php echo $rij['id'] ? '<a href="' . esc_url( get_edit_user_link( $rij['id'] ) ) . '">' . esc_html( $rij['bedrijf'] ) . '</a>' : esc_html( $rij['bedrijf'] ); ?></td>
									<td class="num"><?php echo (int) $rij['aantal']; ?></td>
									<td class="num"><?php echo wp_kses_post( wc_price( $rij['omzet'] ) ); ?></td>
									<td class="num"><?php echo esc_html( self::datum( $rij['laatst'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					<?php else : ?>
						<p class="hdp-g-leeg">In deze periode geen bestellingen.</p>
					<?php endif; ?>
				</section>
			</div>
		</div>
		<?php
	}

	private static function render_termen( $termen ) {
		if ( ! $termen ) {
			echo '<p class="hdp-g-leeg">In deze periode nog niets.</p>';
			return;
		}
		?>
		<table class="widefat striped">
			<thead><tr><th>Zoekterm</th><th class="num">Keer</th><th class="num">Dealers</th><th class="num">Laatst</th></tr></thead>
			<tbody>
			<?php foreach ( $termen as $rij ) : ?>
				<tr>
					<td><?php echo esc_html( $rij->term ); ?></td>
					<td class="num"><?php echo (int) $rij->keer; ?></td>
					<td class="num"><?php echo (int) $rij->dealers; ?></td>
					<td class="num"><?php echo esc_html( self::datum( strtotime( $rij->laatst . ' UTC' ) ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
}
