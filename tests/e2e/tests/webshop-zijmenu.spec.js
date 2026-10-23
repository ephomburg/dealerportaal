const { test, expect } = require('@playwright/test');

/**
 * De webshop hoort dezelfde opzet te hebben als de rest van het portaal:
 * het donkere vlak met het zijmenu links en het werkblad als paneel.
 *
 * Twee dingen gaan hier stil kapot als iemand aan de sjablonen komt: het
 * menu verdwijnt (de winkel heeft eigen sjablonen, los van de
 * portaalpagina's), en "Webshop" dooft zodra je op een product klikt —
 * want een productpagina heeft een ander adres dan de winkel.
 */
test.describe('Webshop — zelfde opzet als de rest van het portaal', () => {
	for (const [naam, pad] of [
		['winkel', '/shop/'],
		['winkelwagen', '/cart/'],
		['afrekenen', '/checkout/'],
	]) {
		test(`${naam} heeft het zijmenu en het werkblad`, async ({ page }) => {
			await page.goto(pad);

			await expect(page.locator('.hdp-zijbalk')).toBeVisible();
			await expect(page.locator('.hdp-app-inhoud')).toBeVisible();
			await expect(page.locator('body')).toHaveClass(/hdp-app-layout/);
		});
	}

	test('"Webshop" blijft oplichten op een productpagina', async ({ page }) => {
		await page.goto('/shop/');
		await expect(page.locator('.hdp-zijbalk-huidig .hdp-zijbalk-tekst')).toHaveText('Webshop');

		await page.locator('.hdp-card__title a').first().click();
		await page.waitForLoadState('networkidle');

		await expect(page).toHaveURL(/\/product\//);
		await expect(page.locator('.hdp-zijbalk-huidig .hdp-zijbalk-tekst')).toHaveText('Webshop');
	});

	/**
	 * De kolomkoppen stonden boven de verkeerde kolom doordat er voor de
	 * omschrijving maar 51 pixels overbleef: de kop "Omschrijving" liep over
	 * die van "Merk" heen en productnamen werden afgekapt tot "12-ad…".
	 */
	test('de kolommen van de lijstweergave lopen gelijk', async ({ page }) => {
		await page.setViewportSize({ width: 1440, height: 900 });
		await page.goto('/shop/');

		// De weergave wordt onthouden, dus hier expliciet op lijst zetten.
		await page.locator('[data-hdp-weergave="lijst"]').click();
		await expect(page.locator('body')).toHaveClass(/hdp-shop-lijst/);
		await expect(page.locator('.hdp-lijst-kop')).toBeVisible();

		const kolommen = await page.evaluate(() => {
			const k = getComputedStyle(document.querySelector('.hdp-lijst-kop')).gridTemplateColumns;
			const r = getComputedStyle(document.querySelector('.hdp-lijst-weergave .hdp-card__body')).gridTemplateColumns;
			return { k, r };
		});

		expect(kolommen.k).toBe(kolommen.r);

		// En de omschrijving moet echt iets kunnen tonen.
		const breedte = await page
			.locator('.hdp-lijst-weergave .hdp-card__title')
			.first()
			.evaluate((el) => el.getBoundingClientRect().width);

		expect(breedte).toBeGreaterThan(120);
	});
});
