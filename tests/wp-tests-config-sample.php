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
 *
 * ── Specifiek voor Local by Flywheel (Windows) ──────────────────────────
 * Local start per site een eigen, geïsoleerde MySQL op een WILLEKEURIGE
 * poort (niet standaard 3306) — DB_HOST hieronder ('localhost') werkt dan
 * niet. Zo krijg je een werkende lokale testomgeving:
 *
 * 1. Poort opzoeken terwijl de site in Local draait:
 *      Get-NetTCPConnection -State Listen |
 *        Where-Object { (Get-Process -Id $_.OwningProcess).ProcessName -eq 'mysqld' } |
 *        Select-Object LocalPort
 *    (of: Local-app > site > tab "Database" toont host/poort direct.)
 *
 * 2. Testdatabase aanmaken op die poort, met Local's eigen mysql.exe
 *    (staat onder %APPDATA%\Local\lightning-services\mysql-*\bin\win64\bin\):
 *      mysql -h127.0.0.1 -P<poort> -uroot -proot -e "CREATE DATABASE IF NOT EXISTS wp_tests;"
 *
 * 3. DB_HOST hieronder zetten op '127.0.0.1:<poort>'.
 *
 * 4. PHP CLI: Local's eigen php.exe (naast mysqld onder lightning-services,
 *    map "php-*") heeft standaard géén php.ini en dus geen mbstring/mysqli/
 *    pdo_mysql geladen, die phpunit en de WP-testsuite wel nodig hebben.
 *    Zet een eigen php.ini (extension_dir + die 3 extensions) klaar, wijs
 *    er met de omgevingsvariabele PHPRC naar, en zet de map van Local's
 *    php.exe op het PATH — WP_PHP_BINARY hieronder roept "php" aan als los
 *    proces om de testsite te installeren, dus dat commando moet ook
 *    zonder volledig pad werken.
 *
 * Daarna: `vendor/bin/phpunit` (of `composer test`) vanuit de repo-root.
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
