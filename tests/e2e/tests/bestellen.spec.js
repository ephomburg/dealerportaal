const { test, expect } = require('@playwright/test');
const { emptyCart } = require('./utils');

/**
 * Wat een dealer bij afrekenen hoort te zien: het artikelnummer bij elk
 * artikel, "Op rekening" als betaalmethode, levering volgens afspraak, een
 * veld voor de eigen referentie, en de leveringsvoorwaarden als echte link.
 * Plaatst zelf geen bestelling, zodat de testdealer niet volloopt.
 *
 * Gaat uit van de webshopinstellingen in Local (op rekening aan, verzend-
 * gebieden Nederland en België); zie de 1.81.0-regel in de changelog.
 */
test.describe('Afrekenen voor dealers', () => {
	test.beforeEach(async ({ page }) => {
		await emptyCart(page);
	});

	test('artikelnummer, op rekening, referentie en voorwaarden', async ({ page }) => {
		await page.goto('/shop/');
		const eersteKaart = page.locator('li.product.hdp-card').first();
		const nummer = (await eersteKaart.locator('.hdp-card__sku').innerText()).trim();
		await eersteKaart.locator('.hdp-card__btn').click();
		await expect(page.locator('.hdp-mandje-aantal')).toHaveText('1', { timeout: 10000 });

		await page.goto('/cart/');
		await expect(page.getByText('Artikelnummer: ' + nummer).first()).toBeVisible({ timeout: 15000 });

		await page.goto('/checkout/');
		await expect(page.getByText('Op rekening').first()).toBeVisible({ timeout: 20000 });
		await expect(page.getByText('Levering volgens afspraak').first()).toBeVisible();
		await expect(page.getByLabel(/Jouw referentie/)).toBeVisible();

		const voorwaarden = page.locator('.wc-block-checkout__terms a', { hasText: 'Algemene voorwaarden' });
		await expect(voorwaarden).toHaveAttribute('href', /Metaalunievoorwaarden-2025-Nederlands\.pdf$/);
	});

	test('de winkelwagen staat ook buiten de webshop in de kop', async ({ page }) => {
		await page.goto('/my-account/snelbestellen/');
		await expect(page.locator('.hdp-mandje-knop')).toBeVisible();
	});
});
