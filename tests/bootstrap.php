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
 * Laadt onze plugin vóórdat de testsuite WordPress initialiseert, zoals
 * WordPress' eigen testsuite dat ook voor plugins-onder-test verwacht.
 */
function hdp_laad_plugin_voor_tests() {
	require dirname( __DIR__ ) . '/app/public/wp-content/plugins/homburg-dealerportaal/homburg-dealerportaal.php';
}
tests_add_filter( 'muplugins_loaded', 'hdp_laad_plugin_voor_tests' );

require $wp_phpunit_dir . '/includes/bootstrap.php';
