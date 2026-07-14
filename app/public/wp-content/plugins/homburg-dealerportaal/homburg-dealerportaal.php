<?php
/**
 * Plugin Name: Homburg Dealerportaal
 * Description: Dealerportaal met echte inlogbeveiliging, dealerrechten (merken/korting) en beveiligde downloads voor Homburg-dealers.
 * Version:     1.16.1
 * Author:      Homburg Machinehandel BV
 * Text Domain: homburg-dealerportaal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HDP_VERSION', '1.16.1' );
define( 'HDP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'HDP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once HDP_PLUGIN_DIR . 'includes/class-hdp-i18n.php';
require_once HDP_PLUGIN_DIR . 'includes/class-hdp-roles.php';
require_once HDP_PLUGIN_DIR . 'includes/class-hdp-user-fields.php';
require_once HDP_PLUGIN_DIR . 'includes/class-hdp-settings.php';
require_once HDP_PLUGIN_DIR . 'includes/class-hdp-downloads-cpt.php';
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
		HDP_Site_Blocks::init();
		HDP_Blocks::init();
		HDP_Admin_Upload::init();
	}
);
