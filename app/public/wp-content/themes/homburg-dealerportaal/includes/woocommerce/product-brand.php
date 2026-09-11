<?php
/**
 * Merken (product_brand) — dit is een kernfunctie van WooCommerce zelf
 * (geen eigen taxonomie, geen plugin); hier wordt alleen de standaardlijst
 * gezet en het merk zichtbaar gemaakt op de productkaart en de losse
 * productpagina. De taxonomie kan later automatisch gevuld worden vanuit
 * een PowerAll-koppeling; deze bestandsnaam en functies wijzigen daarvoor
 * niet mee.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

// LET OP: "product_brand" wordt door WooCommerce zelf pas op de
// 'init'-hook geregistreerd. Dit bestand wordt al bij het laden van
// functions.php ge-required (dus vóór 'init'), dus taxonomy_exists()
// controleren zou hier altijd false teruggeven. De losse functies
// hieronder draaien pas ná 'init' (via hun eigen hooks), dus daar is
// de taxonomie wél al beschikbaar.

add_action( 'init', 'homburg_wc_seed_default_brands', 20 );
/**
 * Zet een startlijst met merken klaar zodat er meteen mee getest en
 * gekoppeld kan worden. Slaat merken over die al bestaan (bijv. na een
 * PowerAll-import), dus dit is veilig om op elke request te laten lopen.
 */
function homburg_wc_seed_default_brands() {
	if ( ! taxonomy_exists( 'product_brand' ) ) {
		return;
	}

	$merken = array( 'HARDI', 'Väderstad', 'Ekobot', 'PerPlant' );

	foreach ( $merken as $merk ) {
		if ( ! term_exists( $merk, 'product_brand' ) ) {
			wp_insert_term( $merk, 'product_brand' );
		}
	}
}

add_action( 'woocommerce_after_shop_loop_item_title', 'homburg_wc_merk_op_productkaart', 5 );
/**
 * Toont het merk als klein label boven de titel op de productkaart
 * (shop, categorie, zoekresultaten) — enkel wanneer het product een merk
 * heeft, zodat producten zonder merk niets extra tonen.
 */
function homburg_wc_merk_op_productkaart() {
	global $product;

	if ( ! $product ) {
		return;
	}

	$merken = get_the_terms( $product->get_id(), 'product_brand' );
	if ( empty( $merken ) || is_wp_error( $merken ) ) {
		return;
	}

	echo '<span class="hdp-wc-merk">' . esc_html( $merken[0]->name ) . '</span>';
}

add_action( 'woocommerce_single_product_summary', 'homburg_wc_merk_op_productpagina', 4 );
/**
 * Toont het merk als klein label boven de producttitel op de losse
 * productpagina (prioriteit 4, vóór de titel op 5).
 */
function homburg_wc_merk_op_productpagina() {
	global $product;

	if ( ! $product ) {
		return;
	}

	$merken = get_the_terms( $product->get_id(), 'product_brand' );
	if ( empty( $merken ) || is_wp_error( $merken ) ) {
		return;
	}

	echo '<p class="hdp-wc-merk hdp-wc-merk--groot">' . esc_html( $merken[0]->name ) . '</p>';
}
