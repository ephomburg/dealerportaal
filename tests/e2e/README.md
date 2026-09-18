# E2E-tests (Playwright)

Browser-tests die de site zelf doorklikken — inloggen, winkelen, afrekenen
starten, snel bestellen, en het mobiele merkenfilter. Vullen de bestaande
PHPUnit-tests (`../` in de repo-root) aan: die testen backend-logica
(rollen, toegang, downloads), maar kunnen geen CSS/lay-out of klikgedrag in
een echte browser controleren — dat is precies waar deze suite voor is.

## Eenmalig instellen

1. Zorg dat de Local-site draait.
2. `cd tests/e2e && npm install` (installeert Playwright + de Chromium-browser).
3. `npx playwright install chromium` (eerste keer, downloadt de browser).
4. Kopieer `.env.example` naar `.env` en vul het wachtwoord van het
   testdealer-account in (`hdp-test-agent` — zie het project-geheugen
   "test-dealer-account" voor de inloggegevens).

## Draaien

```
npm test              # alle tests, headless
npm run test:headed   # met zichtbaar browserscherm (handig bij debuggen)
npm run report        # laatste HTML-testrapport openen
```

## Opzet

- `playwright.config.js` — drie "projects": `guest` (inlogscherm, geen
  sessie), `dealer` (desktop, al ingelogd via een opgeslagen sessie) en
  `dealer-mobile` (telefoonformaat, voor `mobile-*.spec.js`-bestanden).
- `global-setup.js` — logt één keer in als de testdealer en bewaart die
  sessie, zodat niet elke test opnieuw hoeft in te loggen.
- `tests/utils.js` — gedeelde hulpfuncties (bv. de winkelwagen leegmaken
  vóór een test, zodat tests elkaar niet raken via gedeelde winkelwagenstaat).

Nieuwe test toevoegen? Zet 'm in `tests/`, gebruik `emptyCart()` uit
`utils.js` als de test iets aan de winkelwagen toevoegt, en volg de
patronen van een bestaand bestand voor de projectkeuze (mobiel: bestandsnaam
moet met `mobile-` beginnen, zie `playwright.config.js`).
