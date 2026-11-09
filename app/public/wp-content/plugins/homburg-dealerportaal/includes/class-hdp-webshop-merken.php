<?php
/**
 * De webshop per dealer: alleen de merken waarvoor het bedrijf dealer is.
 *
 * Elk merk is een hoofdcategorie in WooCommerce (zie HDP_PowerAll_Artikelen).
 * Een dealer ziet alleen de categorieën van zijn toegestane merken (hdp_merken,
 * door Homburg aangevinkt), plus Diversen en de outlet. Andere merken zijn
 * overal weg: in de lijst, bij zoeken, als losse pagina, in de winkelwagen en
 * bij snel bestellen.
 *
 * Let op het verschil met downloads: daar ziet een dealer zonder merken alles.
 * In de webshop ziet hij dan alleen Diversen en de outlet.
 *
 * Collega's van hetzelfde bedrijf (hetzelfde klantnummer in PowerAll) delen de
 * merken: wie ze bij één van hen wijzigt, wijzigt ze voor allemaal.
 *
 * Medewerkers van Homburg (wie producten mag beheren) zien alles.
 *
 * @package Homburg_Dealerportaal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HDP_Webshop_Merken {

	/** Categorieën die elke dealer ziet. */
	const VOOR_IEDEREEN = array( 'diversen', 'outlet', 'uncategorized' );

	private static $bezig_met_delen = false;

	/** @var array<int, array<int, string>> Per gebruiker: de verboden categorieën. */
	private static $cache = array();

	public static function init() {
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_query' ), 50 );
		add_filter( 'woocommerce_product_is_visible', array( __CLASS__, 'is_zichtbaar' ), 20, 2 );
		add_filter( 'woocommerce_is_purchasable', array( __CLASS__, 'is_te_koop' ), 20, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'mag_in_wagen' ), 20, 2 );
		add_filter( 'get_terms', array( __CLASS__, 'filter_termen' ), 20, 3 );
		add_action( 'template_redirect', array( __CLASS__, 'blokkeer_pagina' ), 5 );
		add_action( 'updated_user_meta', array( __CLASS__, 'deel_met_collegas' ), 10, 4 );
		add_action( 'added_user_meta', array( __CLASS__, 'deel_met_collegas' ), 10, 4 );
	}

	/** Geldt de beperking voor deze gebruiker? Niet voor Homburg-medewerkers en niet in het beheer. */
	public static function geldt( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( ! $user_id || user_can( $user_id, 'edit_products' ) ) {
			return false;
		}
		return ! ( is_admin() && ! wp_doing_ajax() );
	}

	/** De categorieslugs die deze dealer mag zien. */
	public static function toegestaan( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$slugs   = array_map( 'sanitize_title', HDP_Merken::naar_array( get_user_meta( $user_id, 'hdp_merken', true ) ) );
		return array_values( array_unique( array_merge( $slugs, self::VOOR_IEDEREEN ) ) );
	}

	/** De hoofdcategorieën die deze dealer niet mag zien. */
	public static function verboden( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( isset( self::$cache[ $user_id ] ) ) {
			return self::$cache[ $user_id ];
		}
		self::$bezig_met_delen = true; // get_terms hieronder niet zelf filteren.
		$alle = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => 0,
				'hide_empty' => false,
				'fields'     => 'id=>slug',
			)
		);
		self::$bezig_met_delen = false;
		self::$cache[ $user_id ] = is_wp_error( $alle ) ? array() : array_diff( (array) $alle, self::toegestaan( $user_id ) );
		return self::$cache[ $user_id ];
	}

	public static function mag_product( $product_id, $user_id = 0 ) {
		if ( ! self::geldt( $user_id ) ) {
			return true;
		}
		$product_id = (int) $product_id;
		$product    = wc_get_product( $product_id );
		if ( $product && $product->get_parent_id() ) {
			$product_id = $product->get_parent_id();
		}
		$verboden = self::verboden( $user_id );
		if ( ! $verboden ) {
			return true;
		}
		$termen = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $termen ) ) {
			return true;
		}
		foreach ( $termen as $term_id ) {
			// Ook subcategorieën van een verboden merk.
			$voorouders = array_merge( array( (int) $term_id ), get_ancestors( (int) $term_id, 'product_cat', 'taxonomy' ) );
			if ( array_intersect( $voorouders, array_keys( $verboden ) ) ) {
				return false;
			}
		}
		return true;
	}

	/** Elke productlijst op de site: webshop, zoeken, favorieten, gerelateerd. */
	public static function filter_query( $query ) {
		if ( ! self::geldt() ) {
			return;
		}
		$soort = (array) $query->get( 'post_type' );
		$is_productlijst = in_array( 'product', $soort, true ) || $query->is_tax( get_object_taxonomies( 'product' ) ) || ( function_exists( 'is_shop' ) && $query->is_main_query() && is_shop() );
		if ( ! $is_productlijst ) {
			return;
		}
		$verboden = self::verboden();
		if ( ! $verboden ) {
			return;
		}
		$tax_query   = (array) $query->get( 'tax_query' );
		$tax_query[] = array(
			'taxonomy'         => 'product_cat',
			'field'            => 'term_id',
			'terms'            => array_keys( $verboden ),
			'operator'         => 'NOT IN',
			'include_children' => true,
		);
		$query->set( 'tax_query', $tax_query );
	}

	public static function is_zichtbaar( $zichtbaar, $product_id ) {
		return $zichtbaar && self::mag_product( $product_id );
	}

	public static function is_te_koop( $te_koop, $product ) {
		return $te_koop && ( ! $product instanceof WC_Product || self::mag_product( $product->get_id() ) );
	}

	public static function mag_in_wagen( $geldig, $product_id ) {
		return $geldig && self::mag_product( $product_id );
	}

	/** Geen verboden merken in het merkfilter van de webshop en bij zoeken. */
	public static function filter_termen( $termen, $taxonomieen, $args ) {
		if ( self::$bezig_met_delen || ! in_array( 'product_cat', (array) $taxonomieen, true ) || ! self::geldt() || ! is_array( $termen ) ) {
			return $termen;
		}
		$verboden = self::verboden();
		if ( ! $verboden ) {
			return $termen;
		}
		return array_values(
			array_filter(
				$termen,
				static function ( $term ) use ( $verboden ) {
					if ( ! $term instanceof WP_Term ) {
						return true;
					}
					$voorouders = array_merge( array( (int) $term->term_id ), get_ancestors( (int) $term->term_id, 'product_cat', 'taxonomy' ) );
					return ! array_intersect( $voorouders, array_keys( $verboden ) );
				}
			)
		);
	}

	/** Een losse productpagina of merkpagina van een ander merk: niet gevonden. */
	public static function blokkeer_pagina() {
		if ( ! self::geldt() ) {
			return;
		}
		$weg = false;
		if ( function_exists( 'is_product' ) && is_product() ) {
			$weg = ! self::mag_product( get_queried_object_id() );
		} elseif ( function_exists( 'is_product_category' ) && is_product_category() ) {
			$term       = get_queried_object();
			$voorouders = $term instanceof WP_Term ? array_merge( array( (int) $term->term_id ), get_ancestors( (int) $term->term_id, 'product_cat', 'taxonomy' ) ) : array();
			$weg        = (bool) array_intersect( $voorouders, array_keys( self::verboden() ) );
		}
		if ( $weg ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}

	/** Merken gewijzigd bij één dealer: ook bij zijn collega's (hetzelfde klantnummer). */
	public static function deel_met_collegas( $meta_id, $user_id, $sleutel, $waarde ) {
		if ( 'hdp_merken' !== $sleutel ) {
			return;
		}
		self::$cache = array();
		if ( self::$bezig_met_delen ) {
			return;
		}
		$relatie = (int) get_user_meta( $user_id, HDP_PowerAll_Dealers::META_RELATIE, true );
		if ( ! $relatie ) {
			return;
		}
		self::$bezig_met_delen = true;
		foreach ( get_users( array( 'meta_key' => HDP_PowerAll_Dealers::META_RELATIE, 'meta_value' => (string) $relatie, 'exclude' => array( (int) $user_id ), 'fields' => 'ID' ) ) as $collega ) { // phpcs:ignore WordPress.DB.SlowDBQuery
			update_user_meta( (int) $collega, 'hdp_merken', $waarde );
		}
		self::$bezig_met_delen = false;
	}
}
