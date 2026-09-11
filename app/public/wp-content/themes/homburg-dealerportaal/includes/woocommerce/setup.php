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

add_filter( 'woocommerce_get_price_suffix', 'homburg_wc_prijs_suffix', 20 );
/**
 * "excl. btw" achter elke prijs, via WooCommerce's eigen suffix-mechanisme
 * zodat het overal precies één keer verschijnt (kaart, productpagina,
 * winkelmand). Vervangt eventuele instelling in WooCommerce > Instellingen.
 */
function homburg_wc_prijs_suffix() {
	return ' <span class="hdp-wc-btw">' . esc_html__( 'excl. btw', 'homburg-dealerportaal-theme' ) . '</span>';
}

add_filter( 'woocommerce_coupons_enabled', '__return_false' );
/**
 * Homburg werkt niet met kortingsbonnen — haalt het coupon-veld (en de
 * bijbehorende instellingen/functionaliteit) overal weg i.p.v. het per
 * pagina te verbergen.
 */

add_action( 'woocommerce_before_single_product', 'homburg_wc_terug_naar_winkel_knop' );
/**
 * "Terug naar de winkel"-knop op de losse productpagina — die draait nog
 * op de klassieke templates, dus deze hook volstaat daar.
 */
function homburg_wc_terug_naar_winkel_knop() {
	echo homburg_wc_terug_naar_winkel_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- escapet intern.
}

function homburg_wc_terug_naar_winkel_html() {
	return sprintf(
		'<a class="hdp-terug-naar-winkel" href="%1$s"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>%2$s</a>',
		esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ),
		esc_html__( 'Terug naar de winkel', 'homburg-dealerportaal-theme' )
	);
}

add_filter( 'the_content', 'homburg_wc_terug_naar_winkel_bij_blokken' );
/**
 * Winkelmand en afrekenen draaien op WooCommerce-blokken (geen klassieke
 * templates), dus de hooks hierboven vuren daar niet — de knop hier in
 * plaats daarvan vóór de bloktekst plakken.
 */
function homburg_wc_terug_naar_winkel_bij_blokken( $content ) {
	if ( ( is_cart() || is_checkout() ) && is_main_query() && in_the_loop() ) {
		return homburg_wc_terug_naar_winkel_html() . $content;
	}
	return $content;
}

add_filter( 'woocommerce_output_related_products_args', 'homburg_wc_gerelateerd_args' );
/**
 * Gerelateerde producten: drie stuks in drie kolommen, zodat de rij
 * onderaan de productpagina netjes vol staat.
 */
function homburg_wc_gerelateerd_args( $args ) {
	$args['posts_per_page'] = 3;
	$args['columns']        = 3;
	return $args;
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
