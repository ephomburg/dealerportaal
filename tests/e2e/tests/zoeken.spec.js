const { test, expect } = require('@playwright/test');

/**
 * De zoekbalk in de header: "/" springt naar het veld, treffers verschijnen
 * al tijdens het typen, en pijltje + Enter opent de gekozen treffer.
 */
test.describe('Zoekbalk in de header', () => {
	test('vindt een artikel en een menuonderdeel, met toetsenbord te bedienen', async ({ page }) => {
		await page.goto('/');
		await page.keyboard.press('/');
		await expect(page.locator('.hdp-zoekbalk-veld')).toBeFocused();

		await page.keyboard.type('hardi');
		const paneel = page.locator('.hdp-zoekbalk-paneel');
		await expect(paneel.locator('.hdp-zoekbalk-groep-kop', { hasText: 'Artikelen' })).toBeVisible({ timeout: 10000 });
		await expect(paneel).not.toContainText('&euro;');
		await expect(paneel.locator('.hdp-zoekbalk-meer')).toHaveAttribute('href', /zoek=hardi/);

		await page.locator('.hdp-zoekbalk-veld').fill('bestel');
		await expect(paneel.locator('.hdp-zoekbalk-item', { hasText: 'Bestellingen' })).toBeVisible({ timeout: 10000 });
		await page.keyboard.press('ArrowDown');
		await page.keyboard.press('Enter');
		await page.waitForURL(/\/orders\//);
	});

	test('op een telefoon eerst alleen een vergrootglas', async ({ page }) => {
		await page.setViewportSize({ width: 412, height: 850 });
		await page.goto('/');
		await expect(page.locator('.hdp-zoekbalk-veld')).toBeHidden();
		await page.click('.hdp-zoekbalk-open');
		await expect(page.locator('.hdp-zoekbalk-veld')).toBeVisible();
	});
});
