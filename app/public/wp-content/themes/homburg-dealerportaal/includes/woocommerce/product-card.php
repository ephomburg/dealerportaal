<?php
/**
 * Aanvullingen op de productkaart (shop, categorie, zoekresultaten) en de
 * knoppteksten: SKU, voorraadstatus en een expliciete "Bekijk product"-
 * link naast de (voorwaardelijke) bestelknop. Werkt via WooCommerce-hooks
 * op de klassieke productlus — geen sjabloonbestanden gekopieerd.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

add_action( 'woocommerce_after_shop_loop_item_title', 'homburg_wc_sku_en_voorraad_op_kaart', 8 );
/**
 * SKU en voorraadstatus onder de titel op de productkaart. Producten
 * zonder SKU tonen dat onderdeel simpelweg niet.
 */
function homburg_wc_sku_en_voorraad_op_kaart() {
	global $product;

	if ( ! $product ) {
		return;
	}

	echo '<span class="hdp-wc-kaart-meta">';

	$sku = $product->get_sku();
	if ( $sku ) {
		echo '<span class="hdp-wc-sku">Art.nr. ' . esc_html( $sku ) . '</span>';
	}

	echo wp_kses_post( wc_get_stock_html( $product ) );

	echo '</span>';
}

add_action( 'woocommerce_after_shop_loop_item', 'homburg_wc_bekijk_product_knop', 5 );
/**
 * Expliciete "Bekijk product"-link vóór de (eventuele) bestelknop, zodat
 * een dealer altijd naar de productpagina kan — ook wanneer een product
 * (nog) niet direct besteld kan worden.
 */
function homburg_wc_bekijk_product_knop() {
	global $product;

	if ( ! $product ) {
		return;
	}

	printf(
		'<a href="%1$s" class="hdp-btn hdp-btn-secundair hdp-wc-bekijk-knop">Bekijk product</a>',
		esc_url( get_permalink( $product->get_id() ) )
	);
}

add_filter( 'woocommerce_product_add_to_cart_text', 'homburg_wc_knoptekst_kaart', 10, 2 );
add_filter( 'woocommerce_product_single_add_to_cart_text', 'homburg_wc_knoptekst_kaart', 10, 2 );
/**
 * "Toevoegen aan bestelling" wanneer het product direct besteld kan
 * worden; anders "Bekijk product" (bijv. bij een variabel product zonder
 * standaardkeuze, of wanneer een product niet bestelbaar is).
 */
function homburg_wc_knoptekst_kaart( $tekst, $product ) {
	if ( $product && $product->is_purchasable() && $product->is_in_stock() ) {
		return 'Toevoegen aan bestelling';
	}
	return 'Bekijk product';
}
