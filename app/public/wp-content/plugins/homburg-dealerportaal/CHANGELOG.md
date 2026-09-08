# Changelog

Noemenswaardige wijzigingen aan deze plugin, per versie (nieuwste eerst).
Versienummers volgen geen strikte SemVer-"breaking changes"-betekenis — in
dit project krijgt vrijwel elke afgeronde wijziging een eigen versiebump
(het derde cijfer voor kleine fixes/opschoning, de andere voor nieuwe
functionaliteit), zodat elke commit die de plugin-header raakt precies één
duidelijk afgebakende wijziging vertegenwoordigt. Zie ook `git log --
homburg-dealerportaal.php` voor de onderliggende commits.

## 1.38.0
- `HDP_Editor` uitgebreid: de patronen-kiezer toont nu alleen nog de categorieën "Homburg dealerportaal" en "WooCommerce" — kern-, thema- en externe patronen (met hun tientallen categorieën) worden uitgeschreven via `remove_theme_support('core-block-patterns')` + `should_load_remote_block_patterns` + gerichte `unregister_block_pattern(_category)`.
- Insluitingen (`core/embed`) verwijderd uit de toegestane blokken; "Klassiek" (`core/freeform`, rich text mét HTML-tab) toegevoegd naast "Aangepaste HTML".

## 1.37.0
- Blokkenkiezer opgeschoond (`HDP_Editor`): op gewone pagina's/berichten toont de inserter nog maar een beheersbare set — alle `homburg/*`-blokken plus basis- en lay-outblokken (paragraaf, koptekst, lijst, afbeelding, groep, kolommen, knoppen, tabel, ...). Blokken die deze site nooit gebruikt (poëzie, RSS, wiskunde, accordeon, tag cloud, ...) verdwijnen uit de kiezer. WooCommerce winkelwagen/afrekenen en pagina's die al `woocommerce/*`-blokken bevatten houden de volledige set; front-end en bestaande inhoud blijven ongemoeid.

## 1.36.0
- Editor-canvas toont de plugin-blokken (portaalkaarten, infokaarten, hero) nu weer opgemaakt. Sinds WordPress 6.3 zit het canvas in een `iframe`; de CSS werd via `enqueue_block_editor_assets` geladen en kwam dat `iframe` niet meer in, waardoor kaarten als kale tekst verschenen. Nu via `enqueue_block_assets` (met `is_admin()`-check, dus front-end ongemoeid).

## 1.35.0
- Block-patterns via auto-discovery (`HDP_Patterns`): elk `.php`-bestand in `patterns/` met een headerdocblock (Title/Slug/Categories/Description) wordt automatisch geregistreerd, zonder code- of versiewijziging. WordPress scant alleen de `patterns/`-map van het actieve thema; deze loader doet hetzelfde voor de plugin. Nieuwe pattern-categorie "Homburg dealerportaal".
- Eerste pattern `homburg/portaalkaarten-sectie`: de vier hoofdkaarten (webshop/configurator/downloads/content) in één `hdp-kaarten-sectie`-groep, met webshop- en configurator-URL uit Instellingen > Dealerportaal.

## 1.34.0
- Merken-autorisatie wordt nu echt afgedwongen: een dealer met een ingestelde merkenlijst ziet alleen downloads/content van die merken (of zonder merk-tag); een dealer zonder ingestelde merken blijft alles zien. Voorheen was "Geautoriseerd voor:" puur informatief.
- Dealers krijgen automatisch een (tweetalige) e-mail zodra hun account wordt goedgekeurd — vanuit zowel het front-end gebruikersoverzicht als het wp-admin-gebruikersprofiel, en alleen bij de overgang naar goedgekeurd (niet bij elke opslag).
- Bulk-goedkeuren van meerdere dealers tegelijk in het gebruikersoverzicht.
- Bulk merk/regio instellen voor meerdere downloads tegelijk op het adminportaal.
- Bugfix: zoeken op ordernummer in de bestelgeschiedenis werkte niet betrouwbaar (WooCommerce's zoekparameter doorzoekt facturatiegegevens, niet het post-ID waar het ordernummer standaard uit bestaat). Zoekt nu bij een numerieke zoekterm op exact ID.

## 1.33.0
- Regio bepaalt nu automatisch de taalgebonden zichtbaarheid van downloads/content: bestanden getagd met "Nederland" of "België" verschijnen bij de NL-taalversie, bestanden getagd met "België (Franstalig)" alleen bij de FR-taalversie. Bestanden zonder regio-tag (bestaande uploads van vóór dit onderscheid) blijven in beide talen zichtbaar. De regiofilterchips tonen nu alleen de opties die voor de actieve taal relevant zijn.

## 1.32.1
- Nieuwe regio-optie "België (Franstalig)" (`be-fr`) toegevoegd naast Nederland en België — bij het uploaden (adminportaal én wp-admin-metabox) en als filterchip op de downloads-/contentpagina.

## 1.32.0
- Merkenlijst geconsolideerd (`HDP_Merken`): de "Merk"-keuze bij uploaden, "Toegestane merken" in het gebruikersoverzicht én hetzelfde veld op het wp-admin-gebruikersprofiel gebruiken nu allemaal dezelfde vaste lijst van 17 merken via checkboxes, i.p.v. dat twee van de drie plekken nog een vrij tekstveld waren dat qua spelling/hoofdletters uit de pas kon lopen met de dropdown.
- Rate limiting op het e-mailwijziging-verzoek: max. 3 bevestigingsmails per dealer per uur, om misbruik als spam-relay naar willekeurige adressen te voorkomen. Teller wordt gewist zodra een wijziging daadwerkelijk bevestigd wordt.
- Zoeken op ordernummer/naam + filteren op status toegevoegd aan de bestelgeschiedenispagina (via WooCommerce's eigen zoekparameter, zodat paginering kloppend blijft).
- Zoeken op naam/e-mail toegevoegd aan het gebruikersoverzicht in het adminportaal.

## 1.31.0
- Laadstatus op formulierknoppen na verzenden (spinner + uitgeschakelde knop), zodat duidelijk is dat de server-round-trip bezig is en er niet twee keer geklikt wordt. Eén gedeeld script (`assets/js/dealerportaal.js`), geen framework.
- Lokale testomgeving-opzet voor Local by Flywheel gedocumenteerd in `tests/wp-tests-config-sample.php` (MySQL-poort vinden, PHP-extensies, PHPRC/PATH).
- Dit CHANGELOG toegevoegd.

## 1.30.0
- Adminportaal (`/adminportaal/`) is nu alleen toegankelijk voor WordPress-beheerders en de e-mailadressen in `HDP_Admin_Upload::TOEGESTANE_ADMIN_EMAILS` (voorheen bewust tijdelijk voor iedereen open).
- Nieuw gebruikersoverzicht op het adminportaal: goedkeuring en toegestane merken per dealer direct instelbaar, zonder dat daarvoor nog wp-admin nodig is.
- Instellingenpaneel is nu toetsenbord-/screenreadertoegankelijk: Escape sluit het paneel, focus verplaatst automatisch bij openen/sluiten, `role="dialog"`/`aria-modal`.
- Bugfix: het automatisch aanmaken van ontbrekende pagina's (bijv. na een pluginupdate) crashte de site, omdat het te vroeg draaide — vóór WordPress' rewrite-systeem (`$wp_rewrite`) klaarstond. Verplaatst naar het `init`-hook.
- PHPUnit-testdekking flink uitgebreid: accountinstellingen, e-mailbevestiging (incl. verlopen/foute tokens), adminportaal-toegang en bestelgeschiedenis-afscherming (34 nieuwe tests, 57 totaal).

## 1.29.1
- Radioknoppen bij "Categorie" op het adminportaal zagen er kapot uit (de CSS-reset voor volle-breedte tekstvelden ontbrak voor `type="radio"`, stond er alleen voor checkboxes).
- "Merk" bij het uploaden is een vaste keuzelijst geworden i.p.v. een vrij tekstveld (de 17 merken die Homburg voert) — voorkomt dubbele filterchips op de downloadspagina door tikfouten of afwijkend hoofdlettergebruik.

## 1.29.0
- Nieuwe pagina `/bestelgeschiedenis/`: alle WooCommerce-bestellingen van de ingelogde dealer (elke status, niet alleen afgeronde), gepagineerd, met een link naar WooCommerce's eigen orderdetailpagina.
- E-mailadres wijzigen toegevoegd aan het instellingenpaneel, met verplichte bevestiging via een link naar het nieuwe adres — het account wijzigt pas na die bevestiging, wat voorkomt dat een gekaapte sessie het adres zomaar kan overnemen.

## 1.28.0
- Nieuw instellingenpaneel naast de uitlogknop op de homepage: weergavenaam en wachtwoord wijzigen.

## 1.27.x (niet apart gedocumenteerd)
Niet-gecommitte tussenstappen vóór dit changelog bestond, met onder meer het
fullscreen inlogscherm (logo, "onthoud mij") en het verbergen van de
portaalkaarten voor uitgelogde/niet-goedgekeurde bezoekers. Niet item voor
item opgenomen omdat de precieze inhoud niet via commitberichten te
herleiden is.

## 1.26.0 — Nieuw blok homburg/portaal-kaart: de 4 hoofdkaarten nu ook echt bewerkbaar
## 1.25.1 — Editor-canvas laadde nooit onze eigen CSS — daarom leek de pagina leeg
## 1.25.0 — Nieuw blok homburg/info-kaart: "Overige informatie" nu echt bewerkbaar
## 1.24.0 — class-hdp-blocks.php opgesplitst per verantwoordelijkheid + test voor herbestellen
## 1.23.0 — Snel herbestellen: eerder bestelde producten met één klik terug in de winkelwagen
## 1.22.1 — Slotje-icoon van het inlogscherm verwijderd, sitenaam naar Dealerportaal
## 1.22.0 — PHPUnit-testsuite: login, dealerrechten en download-/contenttoegang
## 1.21.0 — Sortering, downloadteller (admin-only) en brute-force-bescherming login
## 1.20.0 — "Terug naar het portaal"-link ook bovenaan downloads-/contentpagina
## 1.19.0 — Beveiligingsscherpte n.a.v. phpcs: nonce-/wachtwoordvelden en taalcookie
## 1.18.0 — Versienummer nu uit één bron (plugin-header) i.p.v. twee losse plekken
## 1.17.2 — Resterende losse iconen (login-velden, uitlogknop) ook door icoon_paden()
## 1.17.1 — Iconen samengevoegd tot één gedeelde bron i.p.v. twee parallelle systemen
## 1.17.0 — Login- en wachtscherm visueel bijgetrokken naar de rest van het portaal
## 1.16.1 — Dode CSS opgeruimd: .hdp-hero-klein werd nergens meer toegepast
## 1.16.0 — Vierde portaalkaart "Content" toegevoegd naast webshop/configurator/downloads
## 1.15.0 — Uitloggen-knop: subtiele vulkleur + icoon i.p.v. kale outline-knop
## 1.14.0 — Footer professioneler: grid-uitlijning + social-iconen i.p.v. platte tekstlijst
## 1.13.0 — NL/FR-taalswitch voor het dealerportaal
## 1.12.0 — Adminportaal: bestand uploaden → automatisch als download met tags
## 1.11.0 — Baseline vóór WooCommerce-integratie
