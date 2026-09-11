<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'editor-styles' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'align-wide' );
		add_theme_support( 'title-tag' );
		add_editor_style( 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap' );
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'homburg-google-fonts', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap', array(), wp_get_theme()->get( 'Version' ) );
		wp_enqueue_style( 'homburg-dealerportaal-theme', get_stylesheet_uri(), array(), wp_get_theme()->get( 'Version' ) );
	}
);

// Blokstijl-varianten die met één klik in de editor te kiezen zijn.
// inline_style wordt alleen geladen als de variant daadwerkelijk gebruikt is.
add_action(
	'init',
	function () {
		register_block_style(
			'core/button',
			array(
				'name'         => 'omlijnd',
				'label'        => 'Omlijnd',
				'inline_style' => '.wp-block-button.is-style-omlijnd .wp-block-button__link{background-color:transparent;border:2px solid var(--wp--preset--color--homburg-rood);color:var(--wp--preset--color--homburg-rood);}.wp-block-button.is-style-omlijnd .wp-block-button__link:hover{background-color:var(--wp--preset--color--homburg-rood);border-color:var(--wp--preset--color--homburg-rood);color:var(--wp--preset--color--wit);}',
			)
		);
	}
);

// WooCommerce-integratie: alleen geladen wanneer WooCommerce actief is,
// logisch gescheiden zodat productzichtbaarheid/toegangscontrole hier
// later bij kan zonder de rest van het thema te raken.
require_once get_theme_file_path( 'includes/woocommerce/setup.php' );
require_once get_theme_file_path( 'includes/woocommerce/product-brand.php' );
require_once get_theme_file_path( 'includes/woocommerce/product-card.php' );
require_once get_theme_file_path( 'includes/woocommerce/product-image.php' );
require_once get_theme_file_path( 'includes/woocommerce/product-single.php' );
require_once get_theme_file_path( 'includes/woocommerce/shop-filters.php' );
require_once get_theme_file_path( 'includes/woocommerce/cart-icon.php' );
require_once get_theme_file_path( 'includes/woocommerce/reorder.php' );
require_once get_theme_file_path( 'includes/woocommerce/search-autosuggest.php' );
require_once get_theme_file_path( 'includes/woocommerce/bulk-order.php' );
require_once get_theme_file_path( 'includes/woocommerce/favorites.php' );
