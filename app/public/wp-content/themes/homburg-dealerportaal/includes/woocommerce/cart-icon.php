<?php
/**
 * Houdt het aantal-bolletje bij het winkelmandje-icoon in de header
 * (blocks/site-header/render.php in de plugin) live bij, via WooCommerce's
 * eigen "cart fragments"-mechanisme — na een AJAX-toevoeging op de
 * winkel-/productpagina ververst dit bolletje vanzelf, zonder dat de
 * pagina opnieuw hoeft te laden.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

add_filter( 'woocommerce_add_to_cart_fragments', 'homburg_wc_mandje_fragment' );
function homburg_wc_mandje_fragment( $fragments ) {
	$aantal                          = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
	$fragments['.hdp-mandje-aantal'] = '<span class="hdp-mandje-aantal"' . ( $aantal ? '' : ' hidden' ) . '>' . (int) $aantal . '</span>';
	return $fragments;
}
