/**
 * Leegt de winkelwagen van de ingelogde testdealer via de UI, zodat elke
 * test met een schone winkelwagen begint ongeacht wat een vorige test in
 * dezelfde run erin heeft laten staan (de harde reset in global-setup.js
 * draait maar één keer, vóór de hele testrun).
 */
async function emptyCart(page) {
	await page.goto('/cart/', { waitUntil: 'networkidle' });
	await wachtTotWinkelwagenGeladenIs(page);

	const verwijderKnoppen = page.locator('.wc-block-cart-item__remove-link');
	let aantal = await verwijderKnoppen.count();

	while (aantal > 0) {
		await verwijderKnoppen.first().click();
		// Wachten tot de klik écht serverside is verwerkt (niet alleen de
		// optimistische UI-update) i.p.v. een vaste pauze — anders kan de
		// volgende /cart/-load in een latere test dit artikel weer tonen.
		await expect_(async () => (await verwijderKnoppen.count()) < aantal);
		aantal = await verwijderKnoppen.count();
	}

	// Verifiëren op een verse paginalaad: de optimistische UI-telling
	// hierboven kan 0 tonen terwijl de laatste verwijdering server-side nog
	// niet is weggeschreven. Dit is de asserted waarheid.
	await page.goto('/cart/', { waitUntil: 'networkidle' });
	await wachtTotWinkelwagenGeladenIs(page);
	const restAantal = await page.locator('.wc-block-cart-item__remove-link').count();
	if (restAantal > 0) {
		throw new Error(`emptyCart(): winkelwagen bevat na leegmaken nog ${restAantal} regel(s) — mogelijk een echt probleem, geen testflakiness.`);
	}
}

/**
 * Het Winkelwagen-blok (React) rendert eerst skeleton-placeholders totdat
 * de Store-API-data binnen is — zowel voor "aantal regels tellen" als voor
 * "is dit artikel echt zichtbaar" moet daarop gewacht worden, anders lijkt
 * een net geladen winkelwagen leeg terwijl de data nog onderweg is.
 */
async function wachtTotWinkelwagenGeladenIs(page) {
	await page
		.waitForFunction(() => !document.querySelector('.wc-block-components-skeleton__element'), null, { timeout: 15000 })
		.catch(() => {}); // lege winkelwagen toont ook geen skeleton — dan is er niets om op te wachten.
}

/** Kleine polling-helper zonder van Playwright's eigen expect() af te hangen (die hoort bij een test, niet bij deze losse helper). */
async function expect_(voorwaardeFn, { timeout = 5000, interval = 200 } = {}) {
	const start = Date.now();
	while (Date.now() - start < timeout) {
		if (await voorwaardeFn()) {
			return;
		}
		await new Promise((r) => setTimeout(r, interval));
	}
	throw new Error('emptyCart(): timeout tijdens wachten op verwijdering.');
}

module.exports = { emptyCart };
