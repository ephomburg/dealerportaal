<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Schoont de editor op:
 *
 * 1. De blokkenkiezer (inserter) op gewone pagina's/berichten wordt beperkt
 *    tot alle homburg/*-blokken plus een handvol basis- en lay-outblokken.
 *    Al het overige (poëzie, RSS, wiskunde, accordeon, tag cloud, insluitingen,
 *    ...) verdwijnt.
 * 2. De patronen-kiezer toont alleen de categorie "Homburg dealerportaal" en
 *    de WooCommerce-patronen. Alle andere (kern-, thema- en externe patronen)
 *    worden uitgeschreven.
 *
 * Puur een editor-opschoning — de front-end en bestaande pagina-inhoud
 * blijven volledig ongemoeid.
 *
 * Uitzonderingen die de VOLLEDIGE blokkenset houden:
 * - WooCommerce winkelwagen/afrekenen (tientallen woocommerce/*-blokken)
 * - elke pagina die al woocommerce/*-blokken bevat
 * - contexten zonder post (site-editor, navigatie, widgets)
 */
class HDP_Editor {

	/**
	 * Basisblokken die naast de homburg/*-blokken beschikbaar blijven.
	 * Bewust ruim genoeg voor normale pagina-opbouw; bewust zonder de
	 * blokken die op deze site nooit nodig zijn.
	 *
	 * "Aangepaste HTML" (core/html) en "Klassiek" (core/freeform) staan er
	 * expliciet in: dat zijn de blokken voor vrije HTML / rich text.
	 */
	const KERN_TOEGESTAAN = array(
		'core/paragraph',
		'core/heading',
		'core/list',
		'core/list-item',
		'core/image',
		'core/gallery',
		'core/cover',
		'core/media-text',
		'core/group',
		'core/columns',
		'core/column',
		'core/buttons',
		'core/button',
		'core/separator',
		'core/spacer',
		'core/quote',
		'core/table',
		'core/html',
		'core/freeform',
		'core/shortcode',
		'core/video',
		'core/file',
		'core/page-list',
	);

	/**
	 * Homburg-rood voor de editor-accentkleur. Overschrijft de admin-thema-
	 * CSS-variabelen die WordPress overal in de blok-editor gebruikt: de
	 * "+"-inservoegknop, de blok-selectierand, primaire knoppen, focus-
	 * ringen, de gemarkeerde regel in de lijstweergave, links in panelen.
	 */
	const ACCENT_CSS = 'html body,html body.wp-admin,html .interface-interface-skeleton,html .block-editor__container,html .editor-styles-wrapper{'
		. '--wp-admin-theme-color:#c00d0d!important;'
		. '--wp-admin-theme-color--rgb:192,13,13!important;'
		. '--wp-admin-theme-color-darker-10:#a90b0b!important;'
		. '--wp-admin-theme-color-darker-20:#920a0a!important;'
		. '--wp-block-synced-color:#c00d0d!important;'
		. '}'
		// Directe val-terug voor de meest zichtbare elementen, mocht een
		// build de kleur hardgecodeerd hebben i.p.v. via de variabele.
		. 'body .components-button.is-primary{background-color:#c00d0d!important;border-color:#c00d0d!important;}'
		. 'body .components-button.is-primary:hover{background-color:#a90b0b!important;border-color:#a90b0b!important;}'
		. 'body .components-button.is-primary:active,body .components-button.is-primary.is-pressed{background-color:#920a0a!important;}';

	public static function init() {
		add_filter( 'allowed_block_types_all', array( __CLASS__, 'beperk_blokken' ), 10, 2 );

		// Patronen inperken.
		add_filter( 'should_load_remote_block_patterns', '__return_false' );
		add_action( 'after_setup_theme', array( __CLASS__, 'schrap_kern_patronen' ), 20 );
		add_action( 'init', array( __CLASS__, 'beperk_patronen' ), 20 );

		// Editor-huisstijl: accentkleur naar Homburg-rood.
		// - admin_head (prio 999): als laatste <style> in de editor-chrome,
		//   zodat het WordPress-kleurenschema er niet overheen komt.
		// - enqueue_block_assets: hetzelfde in het iframe-canvas (selectierand).
		add_action( 'admin_head', array( __CLASS__, 'editor_accent_head' ), 999 );
		add_action( 'enqueue_block_assets', array( __CLASS__, 'editor_accent_canvas' ) );
	}

	public static function editor_accent_head() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! method_exists( $screen, 'is_block_editor' ) || ! $screen->is_block_editor() ) {
			return;
		}
		echo '<style id="hdp-editor-accent">' . self::ACCENT_CSS . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische CSS zonder variabele invoer.
	}

	public static function editor_accent_canvas() {
		if ( ! is_admin() ) {
			return; // Alleen het editor-canvas, niet de front-end.
		}
		wp_add_inline_style( 'wp-block-library', self::ACCENT_CSS );
	}

	public static function beperk_blokken( $toegestaan, $context ) {
		// Geen post in context (site-editor, widgets, losse navigatie):
		// niets beperken.
		if ( empty( $context->post ) ) {
			return $toegestaan;
		}

		$post = $context->post;

		// WooCommerce winkelwagen/afrekenen, of elke pagina die al
		// woocommerce/*-blokken bevat: volledige set laten staan, anders
		// breekt de checkout-editor.
		$wc_paginas = array();
		if ( function_exists( 'wc_get_page_id' ) ) {
			$wc_paginas = array( (int) wc_get_page_id( 'cart' ), (int) wc_get_page_id( 'checkout' ) );
		}
		if ( in_array( (int) $post->ID, $wc_paginas, true )
			|| false !== strpos( (string) $post->post_content, '<!-- wp:woocommerce/' ) ) {
			return $toegestaan;
		}

		// Alle geregistreerde homburg/*-blokken automatisch meenemen, zodat
		// een nieuw blok niet ook nog hier toegevoegd hoeft te worden.
		$homburg = array();
		if ( class_exists( 'WP_Block_Type_Registry' ) ) {
			foreach ( array_keys( WP_Block_Type_Registry::get_instance()->get_all_registered() ) as $naam ) {
				if ( 0 === strpos( $naam, 'homburg/' ) ) {
					$homburg[] = $naam;
				}
			}
		}

		return array_values( array_unique( array_merge( self::KERN_TOEGESTAAN, $homburg ) ) );
	}

	/**
	 * Zet de door WordPress meegeleverde kernpatronen uit; die vullen anders
	 * de patronen-kiezer met tientallen categorieën ("Tekst", "Banner", ...).
	 */
	public static function schrap_kern_patronen() {
		remove_theme_support( 'core-block-patterns' );
	}

	/**
	 * Laat in de patronen-kiezer alleen "Homburg dealerportaal" en de
	 * WooCommerce-patronen staan. Draait op init/20, ná de registratie van
	 * kern-, thema-, plugin- en WooCommerce-patronen (die zitten op init/9-12).
	 */
	public static function beperk_patronen() {
		if ( ! class_exists( 'WP_Block_Patterns_Registry' )
			|| ! class_exists( 'WP_Block_Pattern_Categories_Registry' ) ) {
			return;
		}

		$toegestane_cats = self::toegestane_pattern_categorieen();

		// Patronen zonder toegestane categorie uitschrijven.
		foreach ( WP_Block_Patterns_Registry::get_instance()->get_all_registered() as $pattern ) {
			$cats = isset( $pattern['categories'] ) ? (array) $pattern['categories'] : array();
			if ( ! array_intersect( $cats, $toegestane_cats ) ) {
				unregister_block_pattern( $pattern['name'] );
			}
		}

		// Categorieën zelf: alles behalve de toegestane uitschrijven.
		// get_all_registered() geeft een lijst terug, niet slug-gesleuteld,
		// dus de slug uit het 'name'-veld halen.
		foreach ( WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered() as $cat ) {
			$slug = isset( $cat['name'] ) ? $cat['name'] : '';
			if ( '' !== $slug && ! in_array( $slug, $toegestane_cats, true ) ) {
				unregister_block_pattern_category( $slug );
			}
		}
	}

	/**
	 * Toegestane pattern-categorieën: "homburg" plus elke geregistreerde
	 * categorie waarvan de slug of het label naar WooCommerce verwijst.
	 */
	private static function toegestane_pattern_categorieen() {
		$toegestaan = array( HDP_Patterns::CATEGORIE );

		foreach ( WP_Block_Pattern_Categories_Registry::get_instance()->get_all_registered() as $cat ) {
			$slug  = isset( $cat['name'] ) ? $cat['name'] : '';
			$label = isset( $cat['label'] ) ? $cat['label'] : '';
			if ( false !== stripos( $slug, 'woo' ) || false !== stripos( $label, 'woo' ) ) {
				$toegestaan[] = $slug;
			}
		}

		return array_values( array_unique( $toegestaan ) );
	}
}
