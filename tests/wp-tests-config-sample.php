<?php
/**
 * Sjabloon voor tests/wp-tests-config.php (die zelf niet in git staat,
 * zoals wp-config.php). Kopieer dit bestand naar wp-tests-config.php en
 * pas de DB-gegevens aan indien nodig.
 *
 * Draait tegen een losse testdatabase ("wp_tests"), volledig gescheiden
 * van de echte site-database ("local"), zodat de testsuite die veilig
 * kan leegmaken/opnieuw opbouwen zonder ooit aan echte content,
 * gebruikers of bestellingen te komen. Maak de database eenmalig aan met:
 * mysql -u root -proot -e "CREATE DATABASE IF NOT EXISTS wp_tests;"
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/app/public/' );

define( 'DB_NAME', 'wp_tests' );
define( 'DB_USER', 'root' );
define( 'DB_PASSWORD', 'root' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'homburg-dealerportaal.test' );
define( 'WP_TESTS_EMAIL', 'admin@homburg-dealerportaal.test' );
define( 'WP_TESTS_TITLE', 'Homburg Dealerportaal (tests)' );

define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );

define( 'WP_DEBUG', true );
