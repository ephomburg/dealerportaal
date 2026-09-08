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
		add_action( 'enqueue_block_assets', array( __CLASS__, 'enqueue_editor_assets' ) );
		add_filter( 'render_block', array( __CLASS__, 'verberg_portaal_secties' ), 10, 2 );
	}

	public static function registreer_blokken() {
		register_block_type( HDP_PLUGIN_DIR . 'blocks/dealerportaal' );
		register_block_type( HDP_PLUGIN_DIR . 'blocks/downloads' );
		register_block_type( HDP_PLUGIN_DIR . 'blocks/content' );
		register_block_type( HDP_PLUGIN_DIR . 'blocks/info-kaart' );
		register_block_type( HDP_PLUGIN_DIR . 'blocks/portaal-kaart' );
		register_block_type( HDP_PLUGIN_DIR . 'blocks/bestelgeschiedenis' );
	}

	public static function enqueue_assets() {
		$post = get_post();
		if ( ! $post
			|| ( ! has_block( 'homburg/dealerportaal', $post )
				&& ! has_block( 'homburg/downloads-pagina', $post )
				&& ! has_block( 'homburg/content-pagina', $post )
				&& ! has_block( 'homburg/info-kaart', $post )
				&& ! has_block( 'homburg/portaal-kaart', $post )
				&& ! has_block( 'homburg/bestelgeschiedenis-pagina', $post ) )
		) {
			return;
		}

		wp_enqueue_style( 'hdp-dealerportaal', HDP_PLUGIN_URL . 'assets/css/dealerportaal.css', array(), HDP_VERSION );
		wp_enqueue_script( 'hdp-dealerportaal', HDP_PLUGIN_URL . 'assets/js/dealerportaal.js', array(), HDP_VERSION, true );
	}

	/**
	 * Zonder dit blijven de dynamische portaalblokken (met hun ServerSideRender-
	 * of PHP-preview) en homburg/info-kaart in de editor onopgemaakt: de hero,
	 * kaarten en iconen hebben geen enkele stijl en vallen daardoor (bijna)
	 * onzichtbaar samen tot een lege pagina — precies het "ik zie niks"-effect.
	 *
	 * Draait op enqueue_block_assets (i.p.v. enqueue_block_editor_assets): sinds
	 * WordPress 6.3 zit het editor-canvas in een iframe, en alleen stijlen die
	 * via enqueue_block_assets binnenkomen worden dat iframe in getrokken. De
	 * is_admin()-check houdt de front-end ongemoeid — die laadt de CSS via
	 * enqueue_assets() met has_block()-gate.
	 */
	public static function enqueue_editor_assets() {
		if ( ! is_admin() ) {
			return;
		}
		wp_enqueue_style( 'hdp-dealerportaal-editor', HDP_PLUGIN_URL . 'assets/css/dealerportaal.css', array(), HDP_VERSION );
	}

	/**
	 * De 4 hoofdkaarten en de infosectie staan als losse groepsblokken in de
	 * paginainhoud (zodat ze rechtstreeks bewerkbaar zijn), maar horen alleen
	 * zichtbaar te zijn voor ingelogde, goedgekeurde dealers — net als vroeger
	 * toen ze nog binnen HDP_Portal_Render::render_portal_content() zaten.
	 * WordPress rendert losse blokken anders altijd, ongeacht inlogstatus, dus
	 * deze filter verbergt ze alsnog voor uitgelogde/niet-goedgekeurde bezoekers.
	 */
	public static function verberg_portaal_secties( $block_content, $block ) {
		if ( 'core/group' !== $block['blockName'] ) {
			return $block_content;
		}

		$classname = isset( $block['attrs']['className'] ) ? $block['attrs']['className'] : '';
		if ( false === strpos( $classname, 'hdp-kaarten-sectie' ) && false === strpos( $classname, 'hdp-info-sectie' ) ) {
			return $block_content;
		}

		if ( is_user_logged_in() && HDP_Roles::mag_portaal_zien( get_current_user_id() ) ) {
			return $block_content;
		}

		return '';
	}
}
