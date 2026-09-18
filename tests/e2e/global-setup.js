require('dotenv').config({ path: require('path').join(__dirname, '.env') });
const { request } = require('@playwright/test');
const { execFileSync } = require('child_process');
const fs = require('fs');
const os = require('os');
const path = require('path');

/**
 * Logt één keer in als de testdealer en bewaart de sessie (cookies) op
 * schijf, zodat elke test die de "dealer"-project gebruikt meteen
 * ingelogd start i.p.v. zelf steeds het loginformulier in te vullen.
 *
 * Leegt daarnaast de winkelwagen van dat account via wp-cli, vóór er ook
 * maar iets met een browser gebeurt. WooCommerce bewaart een ingelogde
 * winkelwagen op twee plekken tegelijk (de sessietabel
 * wp_woocommerce_sessions ÉN een "persistent cart" in user-meta, die een
 * nieuwe sessie weer aanvult) — puur via de UI op /cart/ verwijderen raakt
 * maar één van de twee, dus na een volgende testrun staan de oude regels
 * er via de andere weer in. Rechtstreeks in de database is de enige
 * betrouwbare manier om echt met een lege winkelwagen te beginnen.
 */
module.exports = async function globalSetup(config) {
	const baseURL = config.projects[0].use.baseURL;
	const username = process.env.HDP_TEST_DEALER_USER;
	const password = process.env.HDP_TEST_DEALER_PASS;

	if (!username || !password) {
		throw new Error(
			'HDP_TEST_DEALER_USER / HDP_TEST_DEALER_PASS ontbreken. Kopieer .env.example naar .env en vul het testdealer-account in (zie het project-geheugen "test-dealer-account").'
		);
	}

	leegWinkelwagenViaWpCli(username);

	const context = await request.newContext({ baseURL });
	await context.post('/wp-login.php', {
		form: {
			log: username,
			pwd: password,
			'wp-submit': 'Log In',
			redirect_to: baseURL + '/dealerportaal/',
			testcookie: '1',
		},
	});
	await context.storageState({ path: './.auth/dealer.json' });
	await context.dispose();
};

function leegWinkelwagenViaWpCli(username) {
	const repoRoot = path.resolve(__dirname, '..', '..');
	const php = `<?php
$gebruiker = get_user_by( 'login', '${username}' );
if ( $gebruiker ) {
	delete_user_meta( $gebruiker->ID, '_woocommerce_persistent_cart_' . get_current_blog_id() );
	global $wpdb;
	$wpdb->delete( $wpdb->prefix . 'woocommerce_sessions', array( 'session_key' => $gebruiker->ID ) );
	echo "Winkelwagen van {$gebruiker->user_login} (ID {$gebruiker->ID}) geleegd.\\n";
} else {
	fwrite( STDERR, "Gebruiker '${username}' niet gevonden.\\n" );
	exit( 1 );
}
`;
	const tmpFile = path.join(os.tmpdir(), `hdp-e2e-reset-cart-${Date.now()}.php`);
	fs.writeFileSync(tmpFile, php);
	try {
		const output = execFileSync('powershell.exe', ['-NoProfile', '-Command', `& '${repoRoot}\\wp.ps1' eval-file '${tmpFile}'`], {
			encoding: 'utf-8',
		});
		process.stdout.write(output);
	} finally {
		fs.unlinkSync(tmpFile);
	}
}
