const { test, expect } = require('@playwright/test');
const { emptyCart } = require('./utils');

test.describe('Snel bestellen', () => {
	test.beforeEach(async ({ page }) => {
		await emptyCart(page);
	});

	test('een bekend artikelnummer plakken voegt het toe aan de winkelmand', async ({ page }) => {
		await page.goto('/my-account/snelbestellen/');
		await page.locator('#hdp_snelbestellen_lijst').fill('11668');
		await page.getByRole('button', { name: 'Toevoegen aan winkelmand' }).click();

		await expect(page.getByText(/artikel(en)? toegevoegd aan je winkelmand/)).toBeVisible();

		// Geen mandje-icoon op deze accountpagina zelf (zie hdp-mandje-knop:
		// alleen op is_woocommerce()/is_cart()/is_checkout()) — bevestig het
		// toegevoegde artikel daarom op de winkelwagenpagina zelf.
		await page.getByRole('link', { name: 'Naar de winkelmand' }).click();
		await expect(page).toHaveURL(/cart/);
		await expect(page.locator('.hdp-mandje-aantal')).toHaveText('1');
	});

	test('een onbekend artikelnummer wordt duidelijk gemeld', async ({ page }) => {
		await page.goto('/my-account/snelbestellen/');
		await page.locator('#hdp_snelbestellen_lijst').fill('dit-bestaat-niet-999');
		await page.getByRole('button', { name: 'Toevoegen aan winkelmand' }).click();

		await expect(page.getByText('Niet gevonden')).toBeVisible();
	});
});
