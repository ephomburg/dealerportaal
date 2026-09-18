const { test, expect } = require('@playwright/test');

/**
 * De eigen, gebrande inlogpagina (HDP_Login) op /dealerportaal/ — niet het
 * kale wp-login.php. Draait in het "guest"-project (geen opgeslagen sessie).
 */
test.describe('Inloggen dealerportaal', () => {
	test('verkeerd wachtwoord toont de Nederlandse foutmelding', async ({ page }) => {
		await page.goto('/dealerportaal/');
		await page.getByLabel('Gebruikersnaam').fill(process.env.HDP_TEST_DEALER_USER || 'hdp-test-agent');
		await page.getByLabel('Wachtwoord').fill('dit-is-zeker-fout');
		await page.getByRole('button', { name: 'Inloggen' }).click();

		await expect(page.getByText('Onjuiste gebruikersnaam of wachtwoord')).toBeVisible();
	});

	test('juiste gegevens loggen in en tonen het portaal', async ({ page }) => {
		await page.goto('/dealerportaal/');
		await page.getByLabel('Gebruikersnaam').fill(process.env.HDP_TEST_DEALER_USER || 'hdp-test-agent');
		await page.getByLabel('Wachtwoord').fill(process.env.HDP_TEST_DEALER_PASS || '');
		await page.getByRole('button', { name: 'Inloggen' }).click();

		await expect(page.getByRole('heading', { name: /Welkom,/ })).toBeVisible();
	});
});
