const { test, expect } = require('@playwright/test');
const { emptyCart } = require('./utils');

/**
 * De kernflow van de webshop: een product vinden, toevoegen, terugzien in
 * de winkelwagen, en de afrekenpagina laten laden met dat product erin.
 * Rondt de bestelling zelf niet af — er staat geen betaalmethode op Local.
 */
test.describe('Winkel → winkelwagen → afrekenen', () => {
	test.beforeEach(async ({ page }) => {
		await emptyCart(page);
	});

	test('een product toevoegen laat het mandje-aantal oplopen', async ({ page }) => {
		await page.goto('/shop/');
		const eersteKaart = page.locator('li.product.hdp-card').first();
		const titel = (await eersteKaart.locator('.hdp-card__title').innerText()).trim();

		await eersteKaart.locator('.hdp-card__btn').click();
		await expect(page.locator('.hdp-mandje-aantal')).toHaveText('1', { timeout: 10000 });

		// Het Winkelwagen-/Afreken-blok toont eerst een skeleton-placeholder
		// vóórdat de Store-API-data (productnaam, prijs) binnen is — de
		// naam zelf is hier bewust een link (".wc-block-components-
		// product-name"), geen kop, dus op tekst zoeken i.p.v. op rol.
		await page.goto('/cart/');
		await expect(page.locator('.wc-block-components-product-name', { hasText: titel })).toBeVisible({ timeout: 15000 });

		await page.getByRole('link', { name: 'Doorgaan naar afrekenen' }).click();
		await expect(page).toHaveURL(/checkout/);
		await expect(page.getByText(titel).first()).toBeVisible({ timeout: 20000 });
	});
});
