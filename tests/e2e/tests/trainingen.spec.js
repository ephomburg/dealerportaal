const { test, expect } = require('@playwright/test');

/**
 * Trainingen: via het menu naar het overzicht en een training. Niet aanmelden:
 * dat zou elke testronde een plaats kosten en mail versturen. Dat stuk staat in
 * tests/trainingen-test.php. Vereist minstens één training op Local.
 */
test.describe('Trainingen', () => {
	test('menu, overzicht en aanmeldformulier', async ({ page }) => {
		await page.goto('/');
		await page.locator('.hdp-zijbalk a', { hasText: 'Trainingen' }).first().click();
		await expect(page).toHaveURL(/\/trainingen\//);
		await page.locator('.hdp-training-kaart a').first().click();
		await expect(page).toHaveURL(/training=\d+/);
		await expect(page.locator('.hdp-training-formulier #hdp-training-naam')).toBeVisible();
		await expect(page.locator('.hdp-training-formulier #hdp-training-email')).toBeVisible();
	});
});
