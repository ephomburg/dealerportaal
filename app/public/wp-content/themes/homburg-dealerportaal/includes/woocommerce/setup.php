<?php
/**
 * Basis-integratie van WooCommerce in het Homburg Dealerportaal-thema.
 *
 * Raakt geen WordPress- of WooCommerce-kernbestanden aan; voegt alleen
 * thema-ondersteuning toe en laadt de eigen stylesheet, uitsluitend
 * wanneer WooCommerce actief is.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

add_action( 'after_setup_theme', 'homburg_wc_theme_support' );
/**
 * Meldt het thema aan bij WooCommerce. Zonder deze declaratie neemt
 * WooCommerce zijn eigen (afwijkende) standaardbreedtes en galerij-opties
 * aan in plaats van die van dit thema.
 */
function homburg_wc_theme_support() {
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
}

add_action( 'wp_enqueue_scripts', 'homburg_wc_enqueue_assets', 20 );
/**
 * Eigen WooCommerce-stylesheet, losgekoppeld van de rest van het thema en
 * alleen geladen op WooCommerce-pagina's, zodat portaalpagina's die niets
 * met de webshop te maken hebben hier geen last van kunnen krijgen.
 */
function homburg_wc_enqueue_assets() {
	if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() && ! is_account_page() ) {
		return;
	}

	wp_enqueue_style(
		'homburg-woocommerce',
		get_theme_file_uri( 'assets/css/woocommerce.css' ),
		array( 'homburg-dealerportaal-theme' ),
		wp_get_theme()->get( 'Version' )
	);
}
