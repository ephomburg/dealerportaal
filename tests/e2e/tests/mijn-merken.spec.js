const { test, expect } = require('@playwright/test');

/** De testdealer heeft als merken Väderstad en Bogballe. */
test.describe('Mijn merken', () => {
	test('toont de eigen merken, niet de rest', async ({ page }) => {
		await page.goto('/my-account/');
		await page.locator('.woocommerce-MyAccount-navigation a', { hasText: 'Mijn merken' }).click();
		await expect(page).toHaveURL(/mijn-merken/);
		const merken = page.locator('.hdp-mijn-merken li');
		await expect(merken).toHaveCount(2);
		await expect(merken.filter({ hasText: 'Väderstad' })).toBeVisible();
		await expect(merken.filter({ hasText: 'Bogballe' })).toBeVisible();
		await expect(page.locator('.hdp-mijn-merken')).not.toContainText('HARDI');
	});
});
