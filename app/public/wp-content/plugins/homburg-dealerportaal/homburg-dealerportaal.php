<?php
/**
 * Plugin Name: Homburg Dealerportaal
 * Description: Dealerportaal met echte inlogbeveiliging, dealerrechten (merken/korting) en beveiligde downloads voor Homburg-dealers.
 * Version:     1.25.0
 * Author:      Homburg Machinehandel BV
 * Text Domain: homburg-dealerportaal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Versienummer komt uit de plugin-header hierboven (Version:), zodat het
// maar op één plek hoeft te worden bijgewerkt bij een release.
define( 'HDP_VERSION', get_file_data( __FILE__, array( 'version' => 'Version' ) )['version'] );
define( 'HDP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HDP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once HDP_PLUGIN_DIR . 'includes/class-hdp-i18n.php';
require_once HDP_PLUGIN_DIR . 'includes/class-hdp-roles.php';
require_once HDP_PLUGIN_DIR . 'includes/class-hdp-user-fields.php';
require_once HDP_PLUGIN_DIR . 'includes/class-hdp-settings.php';
require_once HDP_PLUGIN_DIR . 'includes/class-hdp-downloads-cpt.php';
require_once HDP_PLUGIN_DIR . 'includes/class-hdp-herbestellen.php';
require_once HDP_PLUGIN_DIR . 'includes/class-hdp-icons.php';
require_once HDP_PLUGIN_DIR . 'includes/class-hdp-login.php';
require_once HDP_PLUGIN_DIR . 'includes/class-hdp-portal-render.php';
require_once HDP_PLUGIN_DIR . 'includes/class-hdp-downloads-render.php';
require_once HDP_PLUGIN_DIR . 'includes/class-hdp-site-blocks.php';
require_once HDP_PLUGIN_DIR . 'includes/class-hdp-blocks.php';
require_once HDP_PLUGIN_DIR . 'includes/class-hdp-admin-upload.php';

register_activation_hook( __FILE__, array( 'HDP_Roles', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'HDP_Roles', 'deactivate' ) );

add_action(
	'plugins_loaded',
	function () {
		HDP_I18N::init();
		HDP_Roles::init();
		HDP_User_Fields::init();
		HDP_Settings::init();
		HDP_Downloads_CPT::init();
		HDP_Login::init();
		HDP_Site_Blocks::init();
		HDP_Blocks::init();
		HDP_Admin_Upload::init();
	}
);
