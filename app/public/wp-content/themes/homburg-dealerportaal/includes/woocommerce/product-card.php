<?php
/**
 * Kleine aanvullingen rond de productlus. De opmaak van de kaart zelf zit
 * in woocommerce/content-product.php (eigen sjabloon); hier alleen de
 * knoptekst. Voorraad wordt bewust niet getoond (zie product-image.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

add_filter( 'woocommerce_product_add_to_cart_text', 'homburg_wc_knoptekst_kaart', 10, 2 );
add_filter( 'woocommerce_product_single_add_to_cart_text', 'homburg_wc_knoptekst_kaart', 10, 2 );
/**
 * "Toevoegen aan bestelling" wanneer het product direct besteld kan
 * worden; anders "Bekijk product" (bijv. een variabel product zonder
 * standaardkeuze).
 */
function homburg_wc_knoptekst_kaart( $tekst, $product ) {
	if ( $product && $product->is_purchasable() ) {
		return 'Toevoegen aan bestelling';
	}
	return 'Bekijk product';
}
