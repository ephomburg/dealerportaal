<?php
/**
 * Artikelen uit PowerAll in de webshop.
 *
 * Alle actieve artikelen waarbij in PowerAll "verbergen op webshop" niet aan
 * staat, komen als product in WooCommerce: artikelnummer, omschrijving,
 * verkoopprijs (excl. btw) en het merk als categorie. De inkoop- en
 * verrekenprijs worden nooit overgenomen.
 *
 * Elk uur loopt het portaal alles na, in stappen van 200 artikelen (Action
 * Scheduler van WooCommerce), zodat geen enkele stap lang duurt. De webshop
 * volgt PowerAll precies: een artikel dat niet meer meekomt (op "Verberg op
 * webshop", niet meer actief of weg) gaat op concept, ook als dat er heel veel
 * tegelijk zijn (bijvoorbeeld als in PowerAll alles op verbergen wordt gezet om
 * opnieuw te kiezen). Het verdwijnt uit de webshop; oude bestellingen blijven
 * kloppen. Alleen bij een foutmelding van PowerAll verandert er niets.
 *
 * Producten die met de hand zijn aangemaakt, zoals de outlet, raakt dit nooit:
 * alleen producten met het kenmerk META_BRON worden bijgewerkt of verborgen.
 *
 * @package Homburg_Dealerportaal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HDP_PowerAll_Artikelen {

	const META_BRON  = '_hdp_powerall';
	const META_HASH  = '_hdp_pa_hash';
	const META_RONDE = '_hdp_pa_ronde';

	const OPTION     = 'hdp_powerall_artikelen';
	const HOOK_START = 'hdp_powerall_artikelen_start';
	const HOOK_STAP  = 'hdp_powerall_artikelen_stap';
	const PER_STAP   = 200;
	const FILTER     = 'HideOnWebshop:"False" AND Status:"Active" AND Type:"Regular"';

	/**
	 * Hoofdgroep in PowerAll => categorie (merk) in de webshop. De slugs van de
	 * bestaande merkcategorieën blijven gelijk.
	 */
	const MERKEN = array(
		'010' => array( 'hardi', 'HARDI' ),
		'020' => array( 'homburg', 'Homburg' ),
		'030' => array( 'bogballe', 'Bogballe' ),
		'045' => array( 'ag-leader', 'Ag Leader' ),
		'055' => array( 'zurn', 'Zürn' ),
		'070' => array( 'vaderstad', 'Väderstad' ),
		'073' => array( 'rabe', 'Rabe' ),
		'075' => array( 'tefen', 'Tefen' ),
		'080' => array( 'diversen', 'Diversen' ),
		'085' => array( 'ekobot', 'Ekobot' ),
	);

	public static function init() {
		add_action( self::HOOK_START, array( __CLASS__, 'start' ) );
		add_action( self::HOOK_STAP, array( __CLASS__, 'stap' ) );
		add_action( 'admin_init', array( __CLASS__, 'plan' ) );
	}

	public static function plan() {
		$gepland = wp_next_scheduled( self::HOOK_START );
		if ( $gepland && 'hourly' !== wp_get_schedule( self::HOOK_START ) ) {
			// Was eerder één keer per nacht.
			wp_clear_scheduled_hook( self::HOOK_START );
			$gepland = false;
		}
		if ( HDP_PowerAll::beschikbaar() && ! $gepland ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'hourly', self::HOOK_START );
		} elseif ( ! HDP_PowerAll::beschikbaar() && $gepland ) {
			wp_clear_scheduled_hook( self::HOOK_START );
		}
	}

	public static function stand() {
		$stand = get_option( self::OPTION, array() );
		return is_array( $stand ) ? $stand : array();
	}

	private static function zet_stand( array $stand ) {
		update_option( self::OPTION, $stand, false );
	}

	/** Een nieuwe ronde: begint bij het eerste artikel. Loopt er al een, dan gebeurt er niets. */
	public static function start() {
		$stand = self::stand();
		if ( ! empty( $stand['bezig'] ) && (int) $stand['bijgewerkt'] > time() - HOUR_IN_SECONDS ) {
			return false;
		}
		self::zet_stand(
			array(
				'bezig'      => true,
				// Altijd hoger dan de vorige, ook bij twee rondes in dezelfde seconde.
				'ronde'      => max( time(), (int) ( $stand['ronde'] ?? 0 ) + 1 ),
				'offset'     => 0,
				'gezien'     => 0,
				'nieuw'      => 0,
				'gewijzigd'  => 0,
				'fout'       => '',
				'begonnen'   => time(),
				'bijgewerkt' => time(),
				'vorige'     => $stand['vorige'] ?? null,
			)
		);
		self::volgende();
		return true;
	}

	/** Bij een ronde via ronde_nu() niets in de wachtrij zetten. */
	private static $direct = false;

	private static function volgende() {
		if ( self::$direct ) {
			return;
		}
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK_STAP, array(), 'hdp-powerall' );
		} else {
			wp_schedule_single_event( time(), self::HOOK_STAP );
		}
	}

	/**
	 * Eén stap van 200 artikelen. Plant zelf de volgende, tot alles binnen is.
	 *
	 * @return bool Of de ronde nu klaar is.
	 */
	public static function stap() {
		$stand = self::stand();
		if ( empty( $stand['bezig'] ) ) {
			return true;
		}

		$artikelen = HDP_PowerAll::get(
			'products',
			array(
				'filters' => self::FILTER,
				'limit'   => self::PER_STAP,
				'offset'  => (int) $stand['offset'],
				'orderBy' => 'ProductCode:ASC',
			)
		);

		if ( is_wp_error( $artikelen ) ) {
			// Niets verbergen: een storing mag de webshop niet leeghalen.
			$stand['bezig']  = false;
			$stand['fout']   = $artikelen->get_error_message();
			$stand['vorige'] = self::samenvatting( $stand );
			self::zet_stand( $stand );
			return true;
		}

		wp_defer_term_counting( true );
		foreach ( (array) $artikelen as $artikel ) {
			$uitkomst = is_array( $artikel ) ? self::verwerk( $artikel, (int) $stand['ronde'] ) : '';
			if ( '' !== $uitkomst ) {
				++$stand['gezien'];
				if ( 'nieuw' === $uitkomst || 'gewijzigd' === $uitkomst ) {
					++$stand[ $uitkomst ];
				}
			}
		}
		wp_defer_term_counting( false );

		$stand['offset']    += self::PER_STAP;
		$stand['bijgewerkt'] = time();

		if ( count( (array) $artikelen ) < self::PER_STAP ) {
			$stand['verborgen'] = self::verberg_ongeziene( (int) $stand['ronde'] );
			$stand['bezig']     = false;
			$stand['vorige']    = self::samenvatting( $stand );
			self::zet_stand( $stand );
			wc_delete_product_transients();
			return true;
		}

		self::zet_stand( $stand );
		self::volgende();
		return false;
	}

	private static function samenvatting( array $stand ) {
		return array(
			'klaar'     => time(),
			'gezien'    => (int) $stand['gezien'],
			'nieuw'     => (int) $stand['nieuw'],
			'gewijzigd' => (int) $stand['gewijzigd'],
			'verborgen' => isset( $stand['verborgen'] ) ? (int) $stand['verborgen'] : 0,
			'fout'      => (string) $stand['fout'],
		);
	}

	/**
	 * Maakt of werkt één product bij.
	 *
	 * @return string 'nieuw', 'gewijzigd', 'gelijk', of '' als het overgeslagen is.
	 */
	public static function verwerk( array $artikel, $ronde ) {
		$code = trim( (string) ( $artikel['ProductCode'] ?? '' ) );
		if ( '' === $code ) {
			return '';
		}

		$naam  = trim( (string) ( $artikel['Description1'] ?? '' ) );
		$extra = trim( (string) ( $artikel['Description2'] ?? '' ) );
		$naam  = '' !== $naam ? $naam : $code;
		if ( '' !== $extra ) {
			$naam .= ' – ' . $extra;
		}
		$prijs     = (float) ( $artikel['SalesPrice'] ?? 0 );
		$prijs     = $prijs > 0 ? wc_format_decimal( $prijs, 2 ) : '';
		$categorie = self::categorie( (string) ( $artikel['MainGroup'] ?? '' ) );
		$eenheid   = trim( (string) ( $artikel['SalesUnit'] ?? '' ) );
		$hash      = md5( wp_json_encode( array( $naam, $prijs, $categorie, $eenheid ) ) );

		$id = wc_get_product_id_by_sku( $code );
		if ( $id && ! get_post_meta( $id, self::META_BRON, true ) ) {
			return ''; // Met de hand aangemaakt (bijv. outlet): niet aankomen.
		}

		if ( $id && get_post_meta( $id, self::META_HASH, true ) === $hash && 'publish' === get_post_status( $id ) ) {
			update_post_meta( $id, self::META_RONDE, $ronde );
			return 'gelijk';
		}

		$product = $id ? wc_get_product( $id ) : new WC_Product_Simple();
		if ( ! $product ) {
			return '';
		}
		$product->set_name( $naam );
		$product->set_sku( $code );
		$product->set_regular_price( $prijs );
		$product->set_sale_price( '' );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'visible' );
		$product->set_manage_stock( false );
		$product->set_stock_status( 'instock' );
		$product->set_category_ids( $categorie ? array( $categorie ) : array() );
		$product->update_meta_data( self::META_BRON, 1 );
		$product->update_meta_data( self::META_HASH, $hash );
		$product->update_meta_data( self::META_RONDE, $ronde );
		$product->update_meta_data( '_hdp_pa_eenheid', 'Stuk' === $eenheid ? '' : $eenheid );
		$product->save();

		return $id ? 'gewijzigd' : 'nieuw';
	}

	/** De categorie (het merk) bij een hoofdgroep; maakt hem aan als hij nog niet bestaat. */
	private static function categorie( $hoofdgroep ) {
		static $cache = array();
		if ( ! isset( self::MERKEN[ $hoofdgroep ] ) ) {
			return 0;
		}
		if ( isset( $cache[ $hoofdgroep ] ) ) {
			return $cache[ $hoofdgroep ];
		}
		list( $slug, $naam ) = self::MERKEN[ $hoofdgroep ];
		$term                = get_term_by( 'slug', $slug, 'product_cat' );
		if ( ! $term ) {
			$nieuw = wp_insert_term( $naam, 'product_cat', array( 'slug' => $slug ) );
			$id    = is_wp_error( $nieuw ) ? 0 : (int) $nieuw['term_id'];
		} else {
			$id = (int) $term->term_id;
		}
		$cache[ $hoofdgroep ] = $id;
		return $id;
	}

	/**
	 * Zet PowerAll-producten die deze ronde niet meer meekwamen op concept.
	 *
	 * @return int Hoeveel er verborgen zijn.
	 */
	private static function verberg_ongeziene( $ronde ) {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$weg = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} bron ON bron.post_id = p.ID AND bron.meta_key = %s
				LEFT JOIN {$wpdb->postmeta} r ON r.post_id = p.ID AND r.meta_key = %s
				WHERE p.post_type = 'product' AND p.post_status = 'publish' AND ( r.meta_value IS NULL OR r.meta_value <> %s )",
				self::META_BRON,
				self::META_RONDE,
				(string) $ronde
			)
		);
		// phpcs:enable
		$weg = array_map( 'intval', $weg );
		if ( ! $weg ) {
			return 0;
		}
		if ( count( $weg ) > 25 ) {
			HDP_Log::schrijf( 'PowerAll-artikelen: ' . count( $weg ) . ' producten staan in PowerAll niet meer voor de webshop en zijn uit de webshop gehaald.', 'INFO' );
		}
		// In blokken in één keer op concept, niet product voor product: als in
		// PowerAll alles op verbergen gaat, zijn het er duizenden, en dat moet
		// binnen één achtergrondstap passen.
		foreach ( array_chunk( $weg, 500 ) as $blok ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- alleen gehele getallen.
			$wpdb->query( "UPDATE {$wpdb->posts} SET post_status = 'draft' WHERE ID IN (" . implode( ',', $blok ) . ')' );
			foreach ( $blok as $id ) {
				clean_post_cache( $id );
			}
		}
		// De aantallen per merk in het filter tellen alleen gepubliceerde producten.
		$termen = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'fields' => 'ids' ) );
		if ( ! is_wp_error( $termen ) && $termen ) {
			wp_update_term_count_now( $termen, 'product_cat' );
		}
		return count( $weg );
	}

	/** Voor WP-CLI en het beheer: een hele ronde direct, zonder wachtrij. */
	public static function ronde_nu() {
		self::$direct = true;
		self::start();
		$stappen = 0;
		while ( ! self::stap() && $stappen < 1000 ) {
			++$stappen;
		}
		self::$direct = false;
		return self::stand();
	}
}
