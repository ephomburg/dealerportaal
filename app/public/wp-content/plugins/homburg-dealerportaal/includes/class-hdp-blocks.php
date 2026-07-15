<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registreert de dealerportaal-blokken en laadt hun stylesheet. Het
 * daadwerkelijke renderen gebeurt in HDP_Portal_Render / HDP_Downloads_Render
 * (aangeroepen vanuit de render.php van elk blok); inloggen + rate-limiting
 * in HDP_Login; iconen in HDP_Icons.
 */
class HDP_Blocks {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'registreer_blokken' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
	}

	public static function registreer_blokken() {
		register_block_type( HDP_PLUGIN_DIR . 'blocks/dealerportaal' );
		register_block_type( HDP_PLUGIN_DIR . 'blocks/downloads' );
		register_block_type( HDP_PLUGIN_DIR . 'blocks/content' );
		register_block_type( HDP_PLUGIN_DIR . 'blocks/info-kaart' );
	}

	public static function enqueue_assets() {
		$post = get_post();
		if ( ! $post
			|| ( ! has_block( 'homburg/dealerportaal', $post )
				&& ! has_block( 'homburg/downloads-pagina', $post )
				&& ! has_block( 'homburg/content-pagina', $post )
				&& ! has_block( 'homburg/info-kaart', $post ) )
		) {
			return;
		}

		wp_enqueue_style( 'hdp-dealerportaal', HDP_PLUGIN_URL . 'assets/css/dealerportaal.css', array(), HDP_VERSION );
	}

	/**
	 * Zonder dit blijven de dynamische portaalblokken (met hun ServerSideRender-
	 * of PHP-preview) en homburg/info-kaart in de editor onopgemaakt: de hero,
	 * kaarten en iconen hebben geen enkele stijl en vallen daardoor (bijna)
	 * onzichtbaar samen tot een lege pagina — precies het "ik zie niks"-effect.
	 * Wordt altijd geladen in de editor (dit hook draait toch alleen daar),
	 * dus geen has_block()-check nodig zoals bij de front-end variant.
	 */
	public static function enqueue_editor_assets() {
		wp_enqueue_style( 'hdp-dealerportaal-editor', HDP_PLUGIN_URL . 'assets/css/dealerportaal.css', array(), HDP_VERSION );
	}
}
