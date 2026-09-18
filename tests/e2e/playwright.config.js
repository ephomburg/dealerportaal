require('dotenv').config({ path: require('path').join(__dirname, '.env') });
const { defineConfig, devices } = require('@playwright/test');

const BASE_URL = process.env.HDP_BASE_URL || 'http://homburg-dealerportaal.local';

module.exports = defineConfig({
	testDir: './tests',
	fullyParallel: false,
	// Eén echte, gedeelde WooCommerce-sessie/winkelwagen voor alle tests (geen
	// losse databases per test) — meerdere workers zouden gelijktijdig aan
	// dezelfde winkelwagen zitten en elkaars aantallen verstoren.
	workers: 1,
	retries: 0,
	reporter: [['list'], ['html', { open: 'never' }]],
	globalSetup: require.resolve('./global-setup.js'),
	use: {
		baseURL: BASE_URL,
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		{
			name: 'dealer',
			use: { ...devices['Desktop Chrome'], storageState: './.auth/dealer.json' },
			testIgnore: [/login\.spec\.js/, /mobile-.*\.spec\.js/],
		},
		{
			name: 'dealer-mobile',
			use: { ...devices['Pixel 7'], storageState: './.auth/dealer.json' },
			testMatch: /mobile-.*\.spec\.js/,
		},
		{
			name: 'guest',
			use: { ...devices['Desktop Chrome'] },
			testMatch: /login\.spec\.js/,
		},
	],
});
