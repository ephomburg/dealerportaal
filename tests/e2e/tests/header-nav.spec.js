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
		// De begroeting gaat met het tijdstip mee (Goedemorgen/Goedemiddag/
		// Goedenavond), dus toetsen op de drie samen in plaats van op één
		// vaste tekst — anders valt deze test 's avonds om.
		await expect(page.getByRole('heading', { name: /Goede(morgen|middag)|Goedenavond/ })).toBeVisible();
		await page.locator('.hdp-taalswitch a', { hasText: 'FR' }).click();
		await expect(page.getByRole('heading', { name: /Bonjour|Bonsoir/ })).toBeVisible();
	});

	/**
	 * De header blijft bij het scrollen bovenaan staan. Dat hangt aan het
	 * omhulsel dat het sjabloon om de header zet; zou iemand het sticky-maken
	 * per ongeluk naar .hdp-site-header zelf verplaatsen, dan scrollt hij
	 * stilletjes gewoon weer mee — in de code is daar niets aan te zien.
	 */
	test('de header blijft bij het scrollen bovenaan staan', async ({ page }) => {
		await page.evaluate(() => window.scrollTo(0, 1200));
		await page.waitForTimeout(200);

		const header = page.locator('.hdp-site-header');
		await expect(header).toBeVisible();

		const top = await header.evaluate((el) => el.getBoundingClientRect().top);
		expect(Math.round(top)).toBe(0);
	});

	/**
	 * Kolommen die zélf sticky zijn (de filterkolom bij garantie, de
	 * zijkolom bij een ticket) moeten onder de header blijven in plaats van
	 * eronder te verdwijnen. Ze rekenen met --hdp-header-hoogte, die het
	 * gedeelde script opmeet.
	 */
	test('de hoogte van de header staat als variabele klaar', async ({ page }) => {
		const hoogte = await page.evaluate(() =>
			getComputedStyle(document.documentElement).getPropertyValue('--hdp-header-hoogte').trim()
		);
		const gemeten = await page
			.locator('.hdp-site-header')
			.evaluate((el) => el.getBoundingClientRect().height);

		expect(parseFloat(hoogte)).toBeGreaterThan(0);
		expect(parseFloat(hoogte)).toBeCloseTo(gemeten, 0);
	});
});
