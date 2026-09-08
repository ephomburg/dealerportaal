# Homburg Dealerportaal

Dealerportaal met echte inlogbeveiliging, dealerrechten (merken/korting) en
beveiligde downloads voor Homburg-dealers. Native Gutenberg-blocks, geen ACF,
geen build-tooling — handgeschreven PHP + vanilla JS tegen de globale `wp`-API.

Versienummer staat op één plek: de `Version:`-regel in de plugin-header van
`homburg-dealerportaal.php` (uitgelezen via `get_file_data()`). Noemenswaardige
wijzigingen per versie: zie `CHANGELOG.md`.

## Structuur

| Map | Inhoud |
|-----|--------|
| `blocks/<naam>/` | `block.json` + `index.js` + `index.asset.php` + `render.php` per block |
| `includes/` | class-per-verantwoordelijkheid (`HDP_*`), geladen vanuit de hoofdfile |
| `assets/css/`, `assets/js/` | één gedeelde stylesheet en één gedeeld script |

Blocks worden handmatig geregistreerd in `HDP_Blocks::registreer_blokken()`.

## Deploy

`main` is de live-staat. Elke push naar `main` die deze map raakt, synct via
GitHub Actions (`.github/workflows/deploy.yml`) met rsync-over-SSH naar
`wp-content/plugins/homburg-dealerportaal/` op de productieserver. De sync
draait met `--delete`: de servermap volgt exact de repo, dus bewerk niets
rechtstreeks op de server.

Workflow schrijven → committen → pushen → automatisch live.
