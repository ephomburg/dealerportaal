<?php
/**
 * Productafbeelding: eigen placeholder voor producten zonder foto (bijv.
 * geïmporteerd uit PowerAll zonder afbeelding), en het uitzetten van de
 * voorraadweergave — Homburg toont beschikbaarheid (nog) niet in de shop.
 *
 * Werkt via WooCommerce-filters; geen sjabloonbestanden gekopieerd.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

/**
 * SVG-placeholder (afgeronde lijst, berg + zon in grijs) — de generieke
 * val-terug voor WooCommerce-plekken die dit thema niet zelf overschrijft
 * (winkelmand, mini-cart, e-mails). Op de productkaart en de losse
 * productpagina wordt in plaats daarvan de "blauwdruk-plaat" getoond
 * (zie homburg_wc_blauwdruk_plaat()) — die toont per product het echte
 * artikelnummer en de categorie i.p.v. een generiek plaatje.
 */
function homburg_wc_placeholder_src() {
	return get_theme_file_uri( 'assets/img/product-placeholder.svg' );
}

add_filter( 'woocommerce_placeholder_img_src', 'homburg_wc_placeholder_src' );

/**
 * Bouwt de "blauwdruk-plaat" voor een product zonder foto: een
 * millimeterpapier-vlak met de categorie linksboven en het artikelnummer
 * groot in het midden. Geen los plaatje — puur HTML/CSS, dus altijd scherp
 * en per product anders.
 *
 * @param WC_Product $product     Het product.
 * @param string     $extra_class Extra CSS-klasse voor de maatvoering
 *                                (bv. "hdp-card__img" of "hdp-pdp-media__img").
 */
function homburg_wc_blauwdruk_plaat( $product, $extra_class = '' ) {
	$categorieen = get_the_terms( $product->get_id(), 'product_cat' );
	$categorie   = ( $categorieen && ! is_wp_error( $categorieen ) ) ? $categorieen[0]->name : __( 'Onderdeel', 'homburg-dealerportaal-theme' );
	$label       = $product->get_sku() ? $product->get_sku() : $product->get_name();

	printf(
		'<div class="hdp-plate %1$s" role="img" aria-label="%2$s"><span class="hdp-plate__cat">%3$s</span><span class="hdp-plate__sku">%4$s</span></div>',
		esc_attr( $extra_class ),
		esc_attr(
			/* translators: %s: artikelnummer of productnaam. */
			sprintf( __( 'Geen afbeelding beschikbaar voor %s', 'homburg-dealerportaal-theme' ), $label )
		),
		esc_html( $categorie ),
		esc_html( $label )
	);
}

add_filter(
	'woocommerce_placeholder_img',
	function ( $image_html, $size, $dimensions ) {
		$w = is_array( $dimensions ) && ! empty( $dimensions['width'] ) ? (int) $dimensions['width'] : 600;
		$h = is_array( $dimensions ) && ! empty( $dimensions['height'] ) ? (int) $dimensions['height'] : 600;

		return sprintf(
			'<img src="%1$s" alt="%2$s" width="%3$d" height="%4$d" class="woocommerce-placeholder wp-post-image hdp-wc-placeholder" loading="lazy" decoding="async" />',
			esc_url( homburg_wc_placeholder_src() ),
			esc_attr__( 'Geen afbeelding beschikbaar', 'homburg-dealerportaal-theme' ),
			$w,
			$h
		);
	},
	10,
	3
);

// Voorraad-/beschikbaarheidsweergave overal uitzetten.
add_filter( 'woocommerce_get_stock_html', '__return_empty_string' );
add_filter( 'woocommerce_get_availability_text', '__return_empty_string' );
add_filter( 'woocommerce_product_get_stock_status', 'homburg_wc_forceer_instock', 20 );

/**
 * Zonder voorraadbeheer beschouwt WooCommerce een product als leverbaar;
 * dit houdt dat gedrag stabiel, ook als een import per ongeluk een
 * afwijkende status meegeeft.
 */
function homburg_wc_forceer_instock( $status ) {
	return 'instock';
}
