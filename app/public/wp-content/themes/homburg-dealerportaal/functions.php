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
		wp_enqueue_style( 'homburg-google-fonts', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap', array(), null );
		wp_enqueue_style( 'homburg-dealerportaal-theme', get_stylesheet_uri(), array(), wp_get_theme()->get( 'Version' ) );
	}
);
