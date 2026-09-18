const { test, expect } = require('@playwright/test');

/**
 * Regressietest voor een concrete bug: het merkenfilter in de winkel stond
 * op mobiel altijd volledig open, bovenop zoekbalk/sortering/producten.
 * Draait in het "dealer-mobile"-project (telefoonformaat).
 */
test('winkel: merkenfilter start dichtgeklapt op mobiel en is open te klikken', async ({ page }) => {
	await page.goto('/shop/');
	const filter = page.locator('.hdp-shop-merken-details');

	await expect.poll(() => filter.evaluate((el) => el.open)).toBe(false);

	await filter.locator('summary').click();
	await expect.poll(() => filter.evaluate((el) => el.open)).toBe(true);
	await expect(page.getByRole('checkbox').first()).toBeVisible();
});
