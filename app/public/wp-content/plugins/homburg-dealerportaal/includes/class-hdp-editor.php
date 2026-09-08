<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Beperkt de blokkenkiezer (inserter) op gewone pagina's/berichten tot een
 * beheersbare set: alle homburg/*-blokken plus een handvol basis- en
 * lay-outblokken. Al het overige (Poëzie, RSS, Wiskunde, accordeon, tag
 * cloud, ...) verdwijnt uit de inserter. Puur een editor-opschoning — de
 * front-end en bestaande pagina-inhoud blijven volledig ongemoeid.
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
		'core/shortcode',
		'core/embed',
		'core/video',
		'core/file',
		'core/page-list',
	);

	public static function init() {
		add_filter( 'allowed_block_types_all', array( __CLASS__, 'beperk_blokken' ), 10, 2 );
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
}
