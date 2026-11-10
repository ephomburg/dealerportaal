<?php
/**
 * Kleinordertoeslag: een bestelling onder € 75 netto (de artikelen, excl.
 * btw, na korting) krijgt automatisch een toeslag van € 12,50. Die staat als
 * aparte regel in de winkelwagen, bij afrekenen, in de mails en in de order,
 * en gaat als het PowerAll-artikel voor de toeslag mee naar PowerAll (zie
 * HDP_PowerAll_Orders::bouw()).
 *
 * Grens, bedrag en artikel staan onder WooCommerce > PowerAll.
 *
 * @package Homburg_Dealerportaal
 */

defined( 'ABSPATH' ) || exit;

class HDP_Kleinordertoeslag {

	const FEE_ID      = 'hdp-kleinordertoeslag';
	const META        = '_hdp_kleinordertoeslag';
	const GRENS       = 75.0;
	const BEDRAG      = 12.5;
	const ARTIKEL     = 'KLEIN ORDER TOESLAG';

	public static function init() {
		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'bereken' ) );
		add_action( 'woocommerce_checkout_create_order_fee_item', array( __CLASS__, 'markeer' ), 10, 3 );
	}

	private static function instellingen() {
		$waarde = get_option( HDP_PowerAll_Orders::OPTION, array() );
		return is_array( $waarde ) ? $waarde : array();
	}

	/** Staat de toeslag aan? Standaard wel. */
	public static function aan() {
		return 'uit' !== ( self::instellingen()['toeslag'] ?? 'aan' );
	}

	/** Onder dit bedrag (netto, excl. btw) komt de toeslag erbij. */
	public static function grens() {
		$grens = self::instellingen()['toeslag_grens'] ?? '';
		return '' !== $grens && is_numeric( $grens ) ? max( 0, (float) $grens ) : self::GRENS;
	}

	public static function bedrag() {
		$bedrag = self::instellingen()['toeslag_bedrag'] ?? '';
		return '' !== $bedrag && is_numeric( $bedrag ) ? max( 0, round( (float) $bedrag, 2 ) ) : self::BEDRAG;
	}

	/** Het PowerAll-artikel voor de toeslag. */
	public static function artikel() {
		$artikel = trim( (string) ( self::instellingen()['toeslag_artikel'] ?? '' ) );
		return '' !== $artikel ? $artikel : self::ARTIKEL;
	}

	/** "Kleinordertoeslag (bestelling onder € 75)". */
	public static function naam() {
		return sprintf( HDP_I18N::t( 'kleinordertoeslag' ), '€ ' . number_format( self::grens(), ( floor( self::grens() ) === self::grens() ? 0 : 2 ), ',', '.' ) );
	}

	/** Het nettobedrag waar de grens over gaat: de artikelen excl. btw, na korting. */
	public static function netto( WC_Cart $cart ) {
		return round( (float) $cart->get_subtotal() - (float) $cart->get_discount_total(), 2 );
	}

	/** Valt deze winkelwagen onder de grens? Een lege winkelwagen niet. */
	public static function geldt_voor( WC_Cart $cart ) {
		$netto = self::netto( $cart );
		return self::aan() && self::bedrag() > 0 && ! $cart->is_empty() && $netto > 0 && $netto < self::grens();
	}

	public static function bereken( $cart ) {
		if ( ! $cart instanceof WC_Cart || ( is_admin() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) ) {
			return;
		}
		if ( ! self::geldt_voor( $cart ) ) {
			return;
		}
		$cart->fees_api()->add_fee(
			array(
				'id'      => self::FEE_ID,
				'name'    => self::naam(),
				'amount'  => self::bedrag(),
				'taxable' => true,
			)
		);
	}

	/** In de order herkenbaar, ook als de naam (taal, grens) anders is. */
	public static function markeer( $item, $fee_key, $fee ) {
		if ( is_object( $fee ) && isset( $fee->id ) && self::FEE_ID === $fee->id ) {
			$item->add_meta_data( self::META, '1', true );
		}
	}

	/** Is deze orderregel de kleinordertoeslag? */
	public static function is_toeslag( $item ) {
		return $item instanceof WC_Order_Item_Fee && '1' === (string) $item->get_meta( self::META );
	}
}
