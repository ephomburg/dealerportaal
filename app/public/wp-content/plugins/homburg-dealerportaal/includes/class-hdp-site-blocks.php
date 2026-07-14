<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registreert de sitebrede header/footer als dynamische blokken, zodat ze
 * de taalswitch en vertaalde teksten (HDP_I18N) kunnen tonen. Voorheen
 * stonden header/footer als statische HTML in het thema; dat kan geen
 * taal wisselen.
 */
class HDP_Site_Blocks {

	public static function init() {
		add_action( 'init', array( __CLASS__, 'registreer_blokken' ) );
	}

	public static function registreer_blokken() {
		register_block_type( HDP_PLUGIN_DIR . 'blocks/site-header' );
		register_block_type( HDP_PLUGIN_DIR . 'blocks/site-footer' );
	}
}
