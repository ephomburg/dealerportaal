<?php
/**
 * Laadt de WordPress-testsuite (via het wp-phpunit/wp-phpunit-pakket) en
 * daarna onze eigen plugin, zodat elke testklasse tegen een echte,
 * geïnstalleerde WordPress-omgeving draait (met echte rollen, capabilities,
 * hooks, etc.) i.p.v. tegen mocks.
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

define( 'WP_TESTS_CONFIG_FILE_PATH', __DIR__ . '/wp-tests-config.php' );

$wp_phpunit_dir = dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';

require $wp_phpunit_dir . '/includes/functions.php';

/**
 * Laadt WooCommerce en daarna onze eigen plugin vóórdat de testsuite
 * WordPress initialiseert, zoals WordPress' eigen testsuite dat ook voor
 * plugins-onder-test verwacht. WooCommerce moet als eerste, want onze
 * plugin en de HDP_Herbestellen-tests leunen op wc_get_orders() e.d.
 */
function hdp_laad_plugin_voor_tests() {
	$woocommerce = dirname( __DIR__ ) . '/app/public/wp-content/plugins/woocommerce/woocommerce.php';
	if ( file_exists( $woocommerce ) ) {
		require $woocommerce;
		// We laden WooCommerce hier los in (niet via echte plugin-activatie),
		// dus moet de eigen database-installatie handmatig getriggerd worden —
		// anders ontbreken tabellen als wc_webhooks/attribute_taxonomies. Pas op
		// 'init' (i.p.v. hier direct): WC_Install::install() heeft
		// wp_get_current_user() nodig, dat op muplugins_loaded nog niet bestaat.
		if ( class_exists( 'WC_Install' ) ) {
			tests_add_filter( 'init', array( 'WC_Install', 'install' ) );
		}
	}
	require dirname( __DIR__ ) . '/app/public/wp-content/plugins/homburg-dealerportaal/homburg-dealerportaal.php';
}
tests_add_filter( 'muplugins_loaded', 'hdp_laad_plugin_voor_tests' );

require $wp_phpunit_dir . '/includes/bootstrap.php';
