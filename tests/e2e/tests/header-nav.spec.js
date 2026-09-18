const { test, expect } = require('@playwright/test');

test.describe('Header — Links/Mijn account-menu en taalswitch', () => {
	test.beforeEach(async ({ page }) => {
		await page.goto('/dealerportaal/');
	});

	test('"Links" klapt Homburg Holland/Belgium open', async ({ page }) => {
		await page.locator('.hdp-links-menu-knop').click();
		await expect(page.getByRole('link', { name: 'Homburg Holland' })).toBeVisible();
		await expect(page.getByRole('link', { name: 'Homburg Belgium' })).toBeVisible();
	});

	test('"Mijn account" klapt Instellingen/Uitloggen open', async ({ page }) => {
		await page.locator('.hdp-account-split-toggle').click();
		await expect(page.locator('.hdp-account-menu-uitloggen')).toBeVisible();
	});

	test('taalswitch naar FR vertaalt de welkomsttekst', async ({ page }) => {
		await expect(page.getByRole('heading', { name: /Welkom,/ })).toBeVisible();
		await page.locator('.hdp-taalswitch a', { hasText: 'FR' }).click();
		await expect(page.getByRole('heading', { name: /Bienvenue,/ })).toBeVisible();
	});
});
