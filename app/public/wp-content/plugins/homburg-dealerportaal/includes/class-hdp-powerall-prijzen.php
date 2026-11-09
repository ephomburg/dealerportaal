<?php
/**
 * De prijs per dealer: een dealer die aan een klantnummer in PowerAll gekoppeld
 * is, ziet bij PowerAll-artikelen zijn eigen nettoprijs (met zijn kortingen en
 * prijsafspraken). Wie geen klantnummer heeft, zoals een beheerder, ziet de
 * gewone verkoopprijs.
 *
 * PowerAll rekent de prijzen uit; het portaal vraagt ze in groepjes op voor de
 * artikelen die op de pagina of in de winkelwagen staan en onthoudt ze 6 uur.
 * Lukt dat niet, dan staat de gewone verkoopprijs er. De factuur komt altijd
 * uit PowerAll, dus daar telt de prijs die PowerAll dan berekent.
 *
 * @package Homburg_Dealerportaal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HDP_PowerAll_Prijzen {

	const ORDERTYPE  = 1;
	const DUUR       = 6 * HOUR_IN_SECONDS;
	const MISLUKT    = 15 * MINUTE_IN_SECONDS;
	const PER_VRAAG  = 100;
	const TRANSIENT  = 'hdp_pa_prijzen_';

	/** @var array<int, array<string, array{0: float|null, 1: int}>> Per klantnummer: code => [prijs, tot]. */
	private static $geheugen = array();

	public static function init() {
		add_filter( 'woocommerce_product_get_price', array( __CLASS__, 'filter_prijs' ), 20, 2 );
		add_filter( 'woocommerce_product_get_regular_price', array( __CLASS__, 'filter_prijs' ), 20, 2 );
		add_filter( 'the_posts', array( __CLASS__, 'vooraf_voor_lijst' ), 20, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'vooraf_voor_wagen' ), 5 );
	}

	/** Het klantnummer van de ingelogde dealer, of 0. */
	public static function relatie( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		return $user_id ? (int) get_user_meta( $user_id, HDP_PowerAll_Dealers::META_RELATIE, true ) : 0;
	}

	public static function filter_prijs( $prijs, $product ) {
		if ( ! $product instanceof WC_Product || ! $product->get_meta( HDP_PowerAll_Artikelen::META_BRON ) ) {
			return $prijs;
		}
		$relatie = self::relatie();
		if ( ! $relatie || '' === (string) $prijs ) {
			return $prijs;
		}
		$eigen = self::prijs( $relatie, $product->get_sku() );
		return null === $eigen ? $prijs : wc_format_decimal( $eigen, 2 );
	}

	/** De nettoprijs voor deze dealer, of null als die niet bekend is. */
	public static function prijs( $relatie, $code ) {
		if ( '' === (string) $code ) {
			return null;
		}
		self::laad( $relatie, array( $code ) );
		$rij = self::$geheugen[ $relatie ][ $code ] ?? null;
		return $rij ? $rij[0] : null;
	}

	/** Haalt de prijzen op die nog niet (of niet meer) bekend zijn. */
	public static function laad( $relatie, array $codes ) {
		$relatie = (int) $relatie;
		if ( ! $relatie || ! HDP_PowerAll::beschikbaar() ) {
			return;
		}
		if ( ! isset( self::$geheugen[ $relatie ] ) ) {
			$opgeslagen                 = get_transient( self::TRANSIENT . $relatie );
			self::$geheugen[ $relatie ] = is_array( $opgeslagen ) ? $opgeslagen : array();
		}
		$nu    = time();
		$nodig = array();
		foreach ( array_unique( array_filter( array_map( 'strval', $codes ) ) ) as $code ) {
			$rij = self::$geheugen[ $relatie ][ $code ] ?? null;
			if ( ! $rij || $rij[1] < $nu ) {
				$nodig[] = $code;
			}
		}
		if ( ! $nodig ) {
			return;
		}

		foreach ( array_chunk( $nodig, self::PER_VRAAG ) as $groep ) {
			$antwoord = HDP_PowerAll::post(
				'products/prices',
				array(
					'RelationCode' => $relatie,
					'OrderType'    => self::ORDERTYPE,
					'Lines'        => array_map(
						static function ( $code ) {
							return array(
								'ProductCode' => $code,
								'Quantity'    => 1,
							);
						},
						$groep
					),
				)
			);
			$gevonden = array();
			if ( ! is_wp_error( $antwoord ) ) {
				foreach ( (array) $antwoord as $regel ) {
					if ( isset( $regel['ProductCode'], $regel['NetPriceExVat'] ) ) {
						$gevonden[ (string) $regel['ProductCode'] ] = (float) $regel['NetPriceExVat'];
					}
				}
			}
			foreach ( $groep as $code ) {
				self::$geheugen[ $relatie ][ $code ] = isset( $gevonden[ $code ] )
					? array( $gevonden[ $code ], $nu + self::DUUR )
					: array( null, $nu + self::MISLUKT );
			}
		}
		set_transient( self::TRANSIENT . $relatie, self::$geheugen[ $relatie ], self::DUUR );
	}

	/** Alle prijzen van een lijst producten (webshop, zoeken, favorieten) in één keer. */
	public static function vooraf_voor_lijst( $posts, $query = null ) {
		$relatie = self::relatie();
		if ( ! $relatie || ! $posts || is_admin() ) {
			return $posts;
		}
		$codes = array();
		foreach ( $posts as $post ) {
			if ( $post instanceof WP_Post && 'product' === $post->post_type && get_post_meta( $post->ID, HDP_PowerAll_Artikelen::META_BRON, true ) ) {
				$codes[] = (string) get_post_meta( $post->ID, '_sku', true );
			}
		}
		if ( $codes ) {
			self::laad( $relatie, $codes );
		}
		return $posts;
	}

	public static function vooraf_voor_wagen( $wagen ) {
		$relatie = self::relatie();
		if ( ! $relatie || ! $wagen instanceof WC_Cart ) {
			return;
		}
		$codes = array();
		foreach ( $wagen->get_cart() as $regel ) {
			if ( isset( $regel['data'] ) && $regel['data'] instanceof WC_Product && $regel['data']->get_meta( HDP_PowerAll_Artikelen::META_BRON ) ) {
				$codes[] = $regel['data']->get_sku();
			}
		}
		if ( $codes ) {
			self::laad( $relatie, $codes );
		}
	}

	/** Na een nieuwe artikelronde: alles opnieuw vragen. */
	public static function vergeet( $relatie ) {
		unset( self::$geheugen[ (int) $relatie ] );
		delete_transient( self::TRANSIENT . (int) $relatie );
	}
}
