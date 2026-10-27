# Changelog

Noemenswaardige wijzigingen aan deze plugin, per versie (nieuwste eerst).
Versienummers volgen geen strikte SemVer-"breaking changes"-betekenis — in
dit project krijgt vrijwel elke afgeronde wijziging een eigen versiebump
(het derde cijfer voor kleine fixes/opschoning, de andere voor nieuwe
functionaliteit), zodat elke commit die de plugin-header raakt precies één
duidelijk afgebakende wijziging vertegenwoordigt. Zie ook `git log --
homburg-dealerportaal.php` voor de onderliggende commits.

## 1.84.0

- Portaalgebruik: testaccounts tellen niet meer mee (vinkje "Testaccount" in het gebruikersprofiel; accounts waarvan de naam begint met `hdp-test-` vanzelf). "Niet meer geweest" gebruikt ook de laatste activiteit die WooCommerce al bijhield, zodat de lijst niet de eerste maand bij iedereen "nog nooit" zegt.
- Webshop (thema 1.35.0): nieuw vinkje "Bestellen in de webshop" in Instellingen > Dealerportaal. Aan: "Op rekening", "Levering volgens afspraak" in Nederland en België en het afleveradres bij afrekenen worden vanzelf ingesteld en goed gehouden. Uit: "Op rekening" gaat uit, afrekenen kan dan niet. Op live staat het vinkje nog uit.
- Opmaak webshop opgeruimd: de losse correcties onderaan woocommerce.css zitten nu in de regels waar ze bij horen. Op 17 van de 18 vergeleken schermen (computer en telefoon) pixel voor pixel gelijk; op de productpagina verschillen alleen de willekeurig gekozen gerelateerde producten.

## 1.83.0

- Nieuw overzicht **Portaalgebruik** in wp-admin (Dashboard > Portaalgebruik, `HDP_Gebruik`), per periode van 30 dagen, 3 of 12 maanden: hoeveel dealers actief waren (ook per week), welke dealers 30 dagen of langer niet zijn geweest (met contactgegevens, om te bellen), zoektermen zonder resultaat en meest gezocht, meest geopende documenten en documenten die niemand opende, en bestellingen en omzet per dealer.
- Houdt daarvoor in een eigen tabel (`wp_hdp_gebruik`, maakt zichzelf aan) per goedgekeurde dealer bij: één bezoek per dag, zoekopdrachten uit de zoekbalk (alleen het eindwoord, niet elke toetsaanslag) en downloads met datum. Beheerders tellen niet mee. Niets gaat naar een externe dienst; na 400 dagen ruimt het zichzelf op.

## 1.82.0

- Eén zoekbalk in de header (`HDP_Zoeken`): zoekt tegelijk in artikelen (titel en artikelnummer), eigen bestellingen (ordernummer, eigen referentie, artikel erin), eigen garantieclaims en machines (claim-, serie- en factuurnummer, machine, eindklant), documenten (downloads, content, huisstijl) en de onderdelen van het menu. Per soort maximaal vijf treffers, plus een link naar alle artikelen in de webshop. Elke bron houdt dezelfde zichtbaarheid aan als zijn eigen pagina (merk en taal bij documenten, alleen eigen claims en bestellingen).
- Bediening: "/" springt vanaf elke pagina naar het veld, pijltjes kiezen, Enter opent, Escape sluit. Op een telefoon eerst een vergrootglas dat het veld onder de header uitklapt. Zonder script zoekt het veld in de webshop.

## 1.81.1

- Webshop (thema 1.34.1): scrolde je met de muis boven het merkenfilter, dan schoof het hele vak mee en verdween de kop "Merk" boven uit beeld. Nu scrolt alleen de lijst met merken; de kop en "Filter wissen" blijven staan.
- Ingelogd als beheerder (met de zwarte WordPress-balk bovenin) zat het merkenfilter, en bij garantie de filter- en detailkolom en de zijkolom bij een ticket, 32px te hoog en verdween de bovenkant achter de header. Die houden nu rekening met die balk, net als het menu links al deed.

## 1.81.0

**Webshop: bestellen zoals een dealer bestelt** (thema 1.34.0).
- **Op rekening, levering volgens afspraak.** Er was geen betaal- en geen verzendmethode, dus afrekenen liep vast. Nu: "Op rekening" (WooCommerce' ingebouwde methode onder eigen naam, in NL/FR; de bestelling gaat meteen "in behandeling") en "Levering volgens afspraak" (kosteloos, Nederland en België). Dat zijn instellingen; in Local staan ze aan, op live nog niet.
- **Afleveradres.** Bij afrekenen staat eerst het afleveradres, met "zelfde adres voor facturering". Heeft een dealer nog geen afleveradres, dan staat zijn factuuradres er al in; leveren bij een eindklant kan door het aan te passen.
- **Jouw referentie.** Een veld voor een eigen inkoopnummer of de naam van de eindklant. Komt terug in de bevestiging, de e-mail, bij de bestelling, in het beheer en in het bestellingenoverzicht ("Ref. …").
- **Artikelnummers** in de winkelwagen, bij afrekenen, in de bevestiging, bij een bestelling en in de e-mails. Er stond alleen de omschrijving.
- **De leveringsvoorwaarden zijn een link** naar de Metaalunievoorwaarden 2025, in het Nederlands of Frans naar de taal van de dealer. Er stond "Algemene voorwaarden" zonder link.
- **De bevestigingspagina na het bestellen was kapot**: een donker scherm met twee losse productlinks. WooCommerce gebruikte een eigen sjabloon zonder het portaal eromheen; het thema heeft er nu een.
- **"Opnieuw bestellen" stond twee keer** bij een bestelling, en een bestelling zonder adres toonde "N/B" (nu het factuuradres van het account).
- **De winkelwagen staat altijd in de kop**, ook op Snel bestellen, Favorieten en Bestellingen. Hij verdween buiten de winkel.

**Webshop: rustiger en leesbaarder.**
- Productnamen in de lijst lopen over twee regels in plaats van "1e boom deel 17 HAL…".
- Productpagina zonder foto: geen groot grijs vlak meer, één compacte kolom. Eén tabblad "Specificaties" is geen tabbalk meer maar een kop. "Bewaar als favoriet" staat links onder het nummer in plaats van midden op de pagina. Het gat van 160 pixels boven "Gerelateerde producten" is weg; die kaarten tonen geen grijs vlak en hebben dezelfde omlijnde knop als de lijst.
- Het merkfilter begint op dezelfde hoogte als de zoekbalk (het hing op 28% van de schermhoogte). Op een telefoon staat het eerste product weer op het eerste scherm.
- Het accountmenu had gaten van ~75 pixels per regel (WooCommerce' eigen lijstruimte).
- Het aantal-veldje: de "1" staat in het midden en het veld is even hoog als de knop.
- Tests: 403 PHPUnit, 18 browsertests (was 16).

## 1.80.2

**Zijmenu: Bestellingen direct onder de webshop, de productconfigurator daaronder.** De configurator is een externe site en hoort achteraan in de groep; Bestellingen werd er tot nu toe achter geplakt.

**Een externe link in het zijmenu krijgt altijd het pijltje** en opent in een nieuw tabblad, ook als het vinkje "nieuw tabblad" op de kaart uit staat. Op live stond dat vinkje uit bij de productconfigurator, waardoor niet te zien was dat je het portaal verliet.
- Tests: 403 (was 401).

## 1.80.1

**Header en footer.**
- De header is vertaald: een Franstalige dealer kreeg "Links" en "Mijn account" in het Nederlands, net als de labels voor schermlezers (favorieten, winkelwagen, accountmenu). Nu "Liens" en "Mon compte".
- De link "Adminportaal (test)" in de footer staat er alleen nog voor wie het adminportaal mag openen. Hij stond er voor iedereen, ook voor dealers en voor bezoekers op het inlogscherm.
- Het jaartal in de footer komt van de server. Het werd door een script ingevuld; zonder JavaScript stond er "© ·".
- Tests: 401 (was 396).

## 1.80.0

**Nieuwe startpagina: wat ligt er vandaag voor je klaar.**
- Bovenaan de groet met een label of het magazijn nu bereikbaar is ("tot 17:00"), en anders wanneer weer ("morgen vanaf 08:00", "maandag vanaf 08:00").
- Onder het zoekveld snelle wegen: Artikelnummers plakken, Mijn favorieten, Hele webshop bekijken.
- **"Vandaag voor jou"**: vier tegels met een getal. Garantieclaims die op jou wachten (oranje als dat zo is), bestellingen in behandeling met de laatste erbij, nieuwe documenten, en wat er nog in de winkelwagen ligt. Elke tegel gaat direct naar de juiste plek.
- Daaronder links **"Nieuw voor jou"** (nu een eigen blok, met een rustige regel als er niets nieuws is) en de **magazijnkaart** met grote bel- en mailknoppen; rechts de **onderdelen per merk** als compacte lijst met een rood icoontje in plaats van vier grote tegels. "Oudere Rabe-machines" (Rabe Ersatzteilportal) staat in die lijst; dat stond onder "(Technische) informatie".
- "Snel herbestellen" staat niet meer op de startpagina.
- Alle links van de oude startpagina zijn er nog. Het blokpatroon "Overige informatie" volgt de nieuwe indeling.
- Het losse puntje achter het e-mailadres van het magazijn is weg: dat was het scheidingsteken tussen links, dat binnen het knopje terechtkwam.

**Tijd.** Groet en magazijnstatus rekenden de tijdzone er twee keer bij; op een site in Nederlandse tijd liep dat een of twee uur scheef. Daarnaast staat de live site op UTC: die hoort op Europe/Amsterdam (een instelling, geen code).

**Telefoon: de pagina schuift niet meer opzij.** Het menublok werd zo breed als de hele rij menu-items, waardoor de hele pagina zijwaarts schoof in plaats van alleen de menustrook.
- Tests: 396 (was 386).

## 1.79.0

**Downloads: meerdere formaten onder één titel.** Hetzelfde document als PDF én Excel stond als twee losse regels. Nu is het één regel met een knop per formaat. In het adminportaal kies je de formaten samen bij het uploaden, of voeg je er later een toe ("Formaat toevoegen"); in wp-admin staat het bij "Extra formaten". Een extra formaat wordt net zo afgeschermd als het hoofdbestand, en is alleen op te halen via de download waar het bij hoort.

**Een nieuwe versie vervangt de oude.** Bij een nieuwe prijslijst bleef de oude staan. Nu: "Nieuwe versie" in het adminportaal (of een ander bestand kiezen in wp-admin). Het blijft één regel, met de datum van vandaag, bovenaan, en weer met het label "Nieuw". Het oude bestand blijft afgeschermd in de mediabibliotheek staan, zodat een vergissing terug te draaien is. De lijst in het adminportaal toont nu alle bestanden in plaats van de laatste tien, anders kon een oudere prijslijst nooit vervangen worden.

**Nette titels.** De titel mag bij uploaden leeg blijven; dan wordt het een nette titel uit de bestandsnaam ("Gebruikershandleiding_HOMBURG_JUNIOR_-_NL.pdf" wordt "Gebruikershandleiding Homburg Junior NL"). Merknamen houden hun eigen schrijfwijze (HARDI), en een versienummer als "Rev-2" blijft staan.

**Bij elk bestand: formaat, grootte en datum**, bijvoorbeeld "PDF 14 MB · 6 okt 2026". Er stond alleen een titel, waardoor niet te zien was welke prijslijst de nieuwste was.

**Bekijken in de browser.** Een PDF of afbeelding heeft naast "Downloaden" een knop "Bekijken", die het bestand in een nieuw tabblad opent in plaats van het op te slaan. Niet voor SVG: die kan scripts bevatten.

**Zoeken in titel, omschrijving en merk**, in plaats van alleen de titel.

**Content is een galerij.** Kaarten met een voorbeeld van het beeld in plaats van een lijst met bestandsnamen, met de bijbehorende posttekst, een knop "Kopieer tekst" en bovenaan "Alles downloaden (zip)". De zip bevat precies wat de dealer ook los mag zien (zelfde taal- en merkfilter). De voorbeeldplaatjes worden pas gemaakt als ze voor het eerst nodig zijn, en staan in de afgeschermde map; een voorbeeld telt niet als download.
**Footer: de icoontjes bij telefoon en e-mail zijn wit** (thema 1.33.1). Rood op donkergrijs was nauwelijks te zien.
- Tests: 386 (was 374).

## 1.78.0

**Garantie: een claim op onderdelen ziet er niet meer kapot uit.**
- Zo'n claim heeft geen machine en kreeg daardoor een lege titel: in de lijst stond alleen een nummer, en boven het ticket stond niets. Nu staat daar het probleem met het factuurnummer, bijvoorbeeld "Beschadigd aangekomen · F-2026-0412".
- Op het ticket stond een lege machinekaart met "Eerdere claims: 1". Dat getal was fout: alle onderdelenclaims hebben geen serienummer en telden zo als elkaars eerdere claims. De kaart is weg bij een onderdelenclaim.
- Wat er mis was met de onderdelen (defect, beschadigd, verkeerd geleverd…) koos de dealer wel, maar het stond nergens op het ticket. Nu wel, en het veld heet weer "Toelichting", net als op het formulier.

**Enters blijven enters.** Een klacht of bericht over meerdere regels werd één lap tekst, ook wat Homburg vanuit de app schreef.

**Bijlagen zijn te openen.** Er stond alleen de bestandsnaam. Nu is elke bijlage een link, met een voorbeeldje bij foto's en het label "Van Homburg" bij wat Homburg toevoegde. De links worden alleen gemaakt nadat vaststaat dat het ticket van deze dealer is, en ze vervallen na een uur.

**Machine aanmelden: factuur of afleverbon meesturen.** De verwerking bestond al, het veld stond er niet. Niet verplicht. Een aankoopdatum in de toekomst wordt geweigerd: een tikfout als 2062 gaf een garantie die decennia doorliep.

**Verlopen en afgewezen machines.**
- Op een machine met verlopen garantie kan geen claim meer worden ingediend. Dat wordt ook op de server tegengehouden, niet alleen in de keuzelijst.
- Een afgewezen machine weigerde de database pas nádat het hele formulier was ingevuld. Nu staat die in de keuzelijst wel, maar is hij niet te kiezen, met de reden erbij.
- In de keuzelijst staat per machine tot wanneer de garantie loopt.

**Vanaf de machine direct een claim.** Elke machinekaart waarop geclaimd kan worden heeft een knop "Claim indienen", met de machine al gekozen. Eerder: naar "Claim indienen", "machine" kiezen, en dezelfde machine in de lijst opnieuw zoeken. De kaarten breken in de smalle middenkolom nu netjes af; de garantiebalk viel daar buiten de kaart.

**Het besluit staat bovenaan een afgeronde claim**, in groen of rood, met de laatste toelichting van Homburg. Er stond alleen "dit ticket is afgehandeld".

**Controle vóór het versturen.**
- Te veel of te grote bestanden, een onderdeelregel zonder uren, of een onderdeel van een andere leverancier zonder factuur: dat zegt het formulier nu meteen, vóór er iets wordt verstuurd. Eerder kwam die melding pas na een retour, en dan waren de gekozen foto's weg.
- Komt er tóch een melding van de server, dan staat erbij dat de bestanden opnieuw gekozen moeten worden.
- Was een verzending groter dan de server aankan, dan kwam de dealer zonder enige melding op een leeg formulier terug. Nu krijgt hij een melding. Bestanden boven het maximum van tien vielen stilletjes weg; die worden nu genoemd.

**Nette foutmeldingen.** Een technische fout van de database (Engelse Postgres-tekst, soms een hele foutpagina) kwam letterlijk op het scherm. Alleen bewuste weigeringen van de database gaan nog letterlijk door; de rest wordt een gewone melding en staat in het logboek.

**Startpagina: de rechterkolom is breder.** De merkentegels mochten alle ruimte pakken; de kolom met bestellen, contact en technische informatie bleef een strook van zo'n 260px, met e-mailadressen die halverwege braken. Die kolom is nu zo'n 400px.
- Tests: 374 (was 364).

## 1.77.1

**De rode bocht linksboven blijft staan bij het scrollen.**
- De rode rand met de ronde hoek, waar het menu in de balk overgaat, hoort bij de bovenkant van het werkblad. Bij het scrollen schoof hij daardoor mee onder de balk door en was de bocht weg.
- Nu plakt er een smal randje van het werkblad onder de balk, met dezelfde rand en ronding. De inhoud schuift eronderdoor. Bovenaan ziet de pagina er precies hetzelfde uit als eerst.
- Op een smal scherm heeft het werkblad geen ronde hoek, dus daar verandert niets.

## 1.77.0

**Het portaal zegt overal "je"** (thema 1.33.0).
- Het was een mengsel: de startpagina zei "jouw socials", de rest "u heeft" en "uw account". Ruim zestig Nederlandse teksten zijn omgezet: garantie, account, meldingen, foutschermen, de goedkeuringsmail en de mail bij een e-mailwijziging.
- Het Frans blijft "vous"; "tu" is in zakelijk Frans ongebruikelijk.
- Een nieuwe test slaat alarm zodra er weer een tekst met "u" of "uw" bijkomt.

**De webshop gebruikt één set woorden.**
- De titel boven de winkel is "Webshop", net als op de startpagina en in het zijmenu; er stond "Winkel".
- "Terug naar de winkel" en "Ga naar de winkel" zijn "... de webshop" geworden.
- Het mandje heet overal "winkelwagen", zoals WooCommerce het zelf ook noemt in de winkelwagen en bij afrekenen. Op andere plekken stond "winkelmand" of "bestelling".
- De bestelknop zegt "In winkelwagen" in plaats van "Toevoegen aan bestelling".

**Sorteren in de webshop.**
- "Populariteit", "gemiddelde waardering" en "nieuwste" zijn weg: hier worden geen beoordelingen gegeven en dealers zoeken daar niet op.
- Nieuw: sorteren op artikelnummer.
- De standaardsortering heet nu wat hij doet: "Omschrijving (A–Z)". Hij sorteert ook echt alleen op omschrijving, ook als iemand ooit een handmatige volgorde instelt.

**Bestellingen.**
- Datums staan als "17 september 2026"; er stond "september 17, 2026". Dat geldt ook voor de losse bestelling, en een Franstalige dealer krijgt de Franse maandnaam.
- Het statusfilter toont alleen nog In behandeling, Afgerond en Geannuleerd. "Wachtend op betaling", "Terugbetaald", "Mislukt" en "Concept" horen bij een webwinkel met online betalen. "Alle statussen" toont nog steeds alles, dus er raakt geen bestelling uit beeld.

**Garantie: geen tegenstrijdige melding meer bij een nieuwe dealer.**
- Midden stond "nog geen claims" met een knop "Claim indienen", rechts "meld eerst een machine aan, daarna kun je claims indienen". Dat laatste klopte niet: een claim op bestelde onderdelen heeft geen machine nodig.
- Rechts staan nu de twee routes, met alleen de knop om een machine aan te melden. Heeft de dealer al machines, dan staat daar geen aanmeldoproep.

**Patroon "Overige informatie" bijgewerkt** naar de nieuwe startpagina: de link naar Downloads (er stond "verkoopdocumenten", een naam die nergens voorkomt), de magazijntijden (werkdagen 08:00–17:00), het blok "Onderdelen bestellen" en overal "Naar onderdelen" op de merktegels. Een bestaande pagina verandert hier niet door; dit patroon gebruik je alleen bij opnieuw invoegen.
- Tests: 364 (was 358).

## 1.76.0

**Een dealer ziet zijn eigen ingediende onderdelen terug op zijn ticket** (thema 1.32.1).
- Het portaal schreef de claimregels wel weg maar las ze nooit terug. Een dealer vulde onderdeelnummers, aantallen, bedragen en sinds kort uren in, en zag daar op zijn eigen ticket niets van — hij kon dus ook niet nagaan of hij het goed had ingevuld.
- Ze staan er nu als tabel, met het totaalbedrag en, als ze zijn opgegeven, het totaal aantal uren. Bedragen staan in centen opgeslagen en worden als euro's getoond; het aantal telt mee in het totaal.
- Een oudere claim zonder uren krijgt die kolom gewoon niet, in plaats van een rij streepjes.
- Ook factuurnummer, klantreferentie en opmerkingen kwamen niet terug; die staan er nu bij.

**De machinelijst wordt ingekort bij veel machines.**
- Boven de twaalf staat de rest ingeklapt, met een knop "Toon de overige N machines". Ze staan wél allemaal in de pagina, want de zoekbalk filtert in de browser en moet ze kunnen vinden — gaat iemand zoeken, dan klapt de lijst vanzelf open.

**De accountpagina's zijn opgeschoond.**
- Er zat ruim honderd pixels lege ruimte boven de titel: de ruimte die WooCommerce voor een eigen paginakop aanhoudt, kwam bovenop die van het werkblad.
- De regels in het accountmenu stonden veertig pixels uit elkaar door een standaardmarge van WooCommerce onder elk lijstitem.
- Tests: 358 (was 345).

## 1.75.0

**De startpagina opnieuw ingericht.**
- Het zoekveld staat over de volle breedte onder de groet, in plaats van weggedrukt in de rechterbovenhoek. Het is de snelste weg de webshop in.
- "Nieuw voor u" toont de nieuwste documenten zélf — titel, merk en datum, hooguit drie regels met een telling voor de rest. Er stond een balk die alleen meldde dát er iets klaarstond; dan moet je nog klikken om te zien wat het is.
- De merken staan nu links en breed, de praktische informatie rechts als rustige kolom. Dat was andersom, met de merken onder drie stukjes vaste tekst — terwijl dat juist links zijn waar men naartoe gaat.
- Het kopje "Overige informatie" is weg. Dat was geen kopje maar een restbak; de informatie eronder is naslag en hoort niet als eigen hoofdstuk op de startpagina.
- De kolommen zijn ook in de pagina zelf omgedraaid en niet alleen met een CSS-truc, zodat de volgorde op het scherm gelijk loopt met die van het toetsenbord.
- Het wegklikken werkt zoals het werkte: weg blijft weg tot er een nieuwer document bij komt.
- Tests: 345 (was 342).

## 1.74.0

**Garantie werkt nu ook bij een dealer met honderd machines.**
- Opgelost, en dit was ernstig: de machinelijst telde per machine de lopende claims en haalde daarvoor elke keer opnieuw de hele claimlijst op. Bij honderd machines waren dat ruim honderd aanvragen naar de claimadministratie voor één pagina — die zou simpelweg niet meer laden. De gegevens worden nu binnen één paginalaad onthouden: gemeten van 101 aanvragen naar 2.
- De machinekeuze op het claimformulier krijgt vanaf acht machines een zoekveld erboven, dat de lijst al tijdens het typen uitdunt op machinenaam, serienummer én klantnaam. Een gewone keuzelijst van honderd regels was niet te doorzoeken: de typehulp van de browser kijkt alleen naar het begin van de regel, dus op serienummer werkte die niet.
- In die lijst staat nu ook de klantnaam: wie honderd machines heeft zoekt meestal "die van Jansen" en niet een serienummer uit zijn hoofd.

**Opgelost: losse backslashes in vier teksten.** Er stond "klant\…" en "num\éro" in plaats van "klant…" en "numéro". Zelfde soort fout als eerder met de \u-reeksen; er staat nu ook een test op dat patroon.
- Tests: 342 (was 336).

## 1.73.0

**De webshop heeft nu dezelfde opzet als de rest van het portaal** (thema 1.32.0).
- Winkel, productpagina, winkelwagen en afrekenen staan in hetzelfde donkere vlak met het zijmenu links en het werkblad als afgerond paneel. De merkenfilter blijft gewoon staan, als tweede kolom naast het menu.
- "Webshop" blijft oplichten in het menu zolang u in de winkel bent — ook op een productpagina, in de winkelwagen en bij het afrekenen. Op padvergelijking alleen doofde dat zodra u op een product klikte.
- De winkelwagen en afrekenen hebben hun eigen sjabloon op paginanaam gekregen, dus hiervoor hoeft er op geen enkele site iets aan de pagina's te veranderen.

**Opgelost: in de lijstweergave liepen de kolomkoppen over elkaar heen.**
- Voor de omschrijving bleef maar 51 pixels over: productnamen werden afgekapt tot "12-ad…" en de kop "Omschrijving" liep over die van "Merk" heen. De vaste kolommen (nummer, merk, prijs, aantal, knop) waren samen te breed.
- Die zijn krapper gezet, waardoor de omschrijving de ruimte krijgt die overblijft. Op een scherm van 1440 is dat ruim 160 pixels in plaats van 51.
- Het omslagpunt waarop de rij onder elkaar klapt, kijkt nu naar de breedte van de productkolom zelf in plaats van naar die van het scherm. Naast een zijmenu én een filterkolom blijft van 1440 pixels nog 860 over, en een omslagpunt op schermbreedte had dat niet door.
- Browsertests: 16 (was 11).

## 1.72.1

**De melding "er staan nieuwe documenten voor u klaar" is weg te klikken, en het logo lijnt uit met het zijmenu.**
- De melding heeft een knop "Niet meer tonen" gekregen, met tekst erbij: een los kruisje is makkelijk over het hoofd te zien en dan blijft die balk er wekenlang staan.
- Wegklikken onthoudt niet "weggeklikt ja/nee" maar tot welk moment. Komt er daarna een document bij, dan verschijnt de melding vanzelf weer — en telt alleen het nieuwe mee, niet wat al weggeklikt was. Met een enkele vlag zou een dealer hem één keer wegklikken en daarna nooit meer horen dat er iets klaarstaat.
- Het logo in de balk bovenaan hield de 70%-inhoudsbreedte aan en begon daardoor 15% naar binnen, terwijl het zijmenu eronder tegen de rand staat. Op de portaalpagina's lijnt het nu uit met de menu-items, en staan de knoppen rechts in lijn met de rand van het werkblad.
- Tests: 336 (was 332).

## 1.72.0

**Bij een machineclaim staan de uren nu per onderdeel, en dat is verplicht.**
- Er was één veld "te claimen tijd" voor de hele claim. Daarmee kwam alle tijd op één hoop en viel achteraf niet meer te zien waar hij aan op was gegaan — niet bij ons en niet bij de fabrikant.
- De regels "te claimen onderdelen" hebben er een kolom Uren bij gekregen, tussen het bedrag en de herkomst. Elke ingevulde regel moet een urenopgave hebben; is er aan een onderdeel geen tijd besteed, dan vult de dealer 0 in. Niets invullen levert een duidelijke melding op en houdt de claim tegen.
- Het losse veld voor de hele claim is bij een machineclaim vervallen — twee plekken waar tijd staat is er één te veel. Het totaal gaat nog steeds mee naar de claimadministratie, maar wordt opgeteld uit de regels, zodat het nooit iets anders kan zeggen dan wat eronder staat.
- Een komma als decimaalteken werkt (0,25 uur); negatieve uren worden 0.
- Bij een onderdelenclaim verandert er niets: die gaat over geleverde onderdelen en niet over werkuren, en houdt het losse veld.
- Tests: 332 (was 319).

## 1.71.2

**Kortere paginatitels: "Downloads" en "Content" in plaats van "Downloads voor dealers" en "Content voor dealers".**
- Dat het voor dealers is, is in een afgeschermd dealerportaal met een menu waar "Downloads" in staat niet meer het vermelden waard. In het Frans net zo: "Téléchargements" en "Contenu".
- De titels komen uit de standaardwaarden van de blokken en niet uit de pagina's zelf, dus dit werkt meteen — er hoeft op geen enkele site iets aan de pagina's te veranderen. Wie een eigen titel invult in de editor, houdt die.

## 1.71.1

**Home bovenaan het zijmenu, en de "terug naar het portaal"-knoppen vervallen.**
- Home staat los boven de kopjes, met een streep eronder: het is de weg terug, geen onderdeel. Daarmee staat die weg terug op elke pagina op dezelfde plek.
- De knop "← Terug naar het portaal" boven de pagina's is daardoor overbodig en verdwijnt zodra het zijmenu er staat. Een terugknop naar iets anders — zoals "terug naar garantie" op een ticket — blijft wél staan; die wijst naar een pagina die niet in het menu voorkomt.
- Het menu begint wat lager; tegen de balk aan leek het eraan vastgeplakt.
- Tests: 319 (was 316).

## 1.71.0

**Het zijmenu staat nu op alle portaalpagina's, niet alleen op het welkomscherm** (thema 1.31.0).
- Downloads, Content, Huisstijl & media, Garantie, Bestelgeschiedenis en de accountpagina's (bestellingen, snel bestellen, favorieten, instellingen) hebben dezelfde opzet als het welkomscherm: het donkere vlak met het werkblad als afgerond paneel erin, en links het menu. Het onderdeel waar u bent licht op.
- Er is een sjabloon "Portaalpagina (met zijmenu)" bijgekomen. Pagina's die daarop staan krijgen het menu; publieke pagina's zoals de privacyverklaring blijven ongemoeid. Een nieuwe portaalpagina zet u er zelf op via Pagina-instellingen → Sjabloon.
- De paginatitel staat nu direct op het werkblad, zoals de begroeting op het welkomscherm. Dat was een witte band over de volle breedte, wat binnen het werkblad als een losse kaart oogde.
- De accountpagina's hielden hun eigen smalle breedte aan en sprongen daardoor binnen het werkblad nog eens in; die vullen nu de ruimte.
- Een bezoeker zonder toegang ziet gewoon het inlogscherm: geen lege donkere kolom.
- De webshop (winkel, product, winkelwagen, afrekenen) is bewust ongemoeid gelaten — die heeft een eigen filterkolom.
- Tests: 316 (was 312).

## 1.70.2

**De voettekst is op de hele site een stuk compacter** (thema 1.30.1).
- Van ongeveer 300 naar 182 pixels op een groot scherm, en van 602 naar 491 op een telefoon. De inhoud is dezelfde gebleven.
- De hoogte zat in de adressen: die stonden over drie regels met telefoon en e-mail eronder — zes regels per kolom. Nu staat het adres op één regel en staan telefoon en e-mail naast elkaar. De alinea met het adres hield bovendien zijn standaardmarges aan, goed voor 25 onzichtbare pixels.
- De footer gebruikt nu de brede maat (90%) in plaats van de 70% van de leespagina's; op 70% waren de kolommen zo smal dat alles alsnog over twee regels brak.
- De lichte waas over de voettekst is weg: die maakte hem zichtbaar lichter dan de balk bovenaan, wat op het welkomscherm meteen opviel doordat ze aan elkaar grenzen.
- De kopjes in het zijmenu zijn wit in plaats van grijs. Het verschil met de regels eronder zit in de vorm — klein, kapitalen, wijd uit elkaar, met een rood streepje — en niet in een flauwere kleur.

## 1.70.1

**Opgelost: onder de voettekst van het welkomscherm bleef een lichte strook staan.**
- Op een hoog scherm is de pagina korter dan het venster. De voettekst eindigde dan halverwege en daaronder kwam de achtergrond van de site tevoorschijn — een witte strook die dwars door het donkere vlak heen brak.
- De pagina is nu minstens zo hoog als het scherm, met het werkblad als het deel dat meegroeit, zodat de voettekst altijd onderaan staat. De achtergrond van de pagina is bovendien donkergrijs, voor wat er bij doorscrollen onder vandaan komt.

## 1.70.0

**Nieuw welkomscherm: een vast menu aan de zijkant, in plaats van een scherm vol tegels.** (thema 1.30.0)
- De startpagina heeft een eigen sjabloon gekregen (`templates/front-page.html`). De balk bovenaan blijft precies zoals hij was; daaronder staat links een vast menu met de onderdelen van het portaal, en rechts het werkblad.
- Het menu haalt zijn onderdelen uit de portaalkaarten die al op de startpagina stonden. Titel, Franse titel en link blijven dus gewoon in de pagina-editor staan; een kaart die daar bijkomt, verschijnt vanzelf in het menu. De kaarten worden op het welkomscherm zelf niet meer als tegels getoond — die navigatie zit nu in het menu.
- De onderdelen staan onder drie kopjes: Bestellen, Service en Materiaal. Een kaart met een onbekend icoon komt onder Materiaal, zodat er nooit eentje uit het menu valt.
- Bij Garantie staat een rood cijfer zodra er een claim op de dealer wacht.
- De paginabrede foto is weg. Daarvoor in de plaats: een groet die met het tijdstip meegaat, de datum van vandaag en een zoekveld naar de artikelen. Daaronder staan meteen de berichten van Homburg.
- De balk bovenaan, het menu en de voettekst vormen samen één donker vlak; het werkblad ligt daar als een licht paneel in, met afgeronde hoeken aan de kant van het menu en een rode rand eromheen. De lichte waas die over de voettekst lag is weg — daardoor was die zichtbaar lichter dan de rest.
- Onder elk kopje in het menu staat een kort rood streepje.
- Achter de herbestelde artikelen staat een kaart "Artikelnummers plakken" — die vult het gat dat overbleef en wijst naar de snelste manier van bestellen.
- Huisstijl & media had hetzelfde icoon als Content; die heeft nu een eigen palet-icoon.
- De startpagina is van 3298 naar ongeveer 2000 pixels hoog gegaan.
- Op een telefoon wordt het menu één strook boven de inhoud die opzij schuift.

**Opgelost: Franse teksten toonden `é` in plaats van é.**
- Op 76 plekken in de vertalingen stond zo'n reeks letterlijk in de code. In een PHP-tekst tussen enkele aanhalingstekens betekent die niets, dus kwam hij onvertaald op het scherm ("pièces" in plaats van "pièces"). Ze waren via bewerkscripts ingeslopen en vielen niet op omdat ze alleen het Frans raakten.
- Een test bewaakt dit nu, en controleert meteen dat elke tekst zowel Nederlands als Frans heeft.
- Tests: 312 (was 298), browsertests 11.

## 1.69.2

**Op een telefoon worden "Links" en "Mijn account" alleen hun icoontje** (thema 1.29.1).
- Daarmee passen het hartje, beide knoppen en de taalkeuze op één regel en is de header daar 100px in plaats van 139px. Nu hij blijft staan, scheelt dat een hele regel scherm op elke pagina.
- Het opschrift is weggeklemd en niet verwijderd: een schermlezer leest nog steeds "Mijn account" voor. Op laptop en desktop verandert er niets.

## 1.69.1

**De header met het logo blijft bij het scrollen bovenaan staan** (thema 1.29.0).
- Geldt op elke pagina: portaal, webshop, winkelwagen, afrekenen en Mijn account. De uitklapmenu's "Links" en "Mijn account" liften mee en blijven over de pagina-inhoud heen vallen.
- Staat voor ingelogde beheerders netjes onder de balk van WordPress in plaats van erachter te verdwijnen.
- De filterkolom bij garantie en de zijkolom bij een ticket waren zelf al sticky; die houden nu afstand tot de header in plaats van eronder te schuiven. Het gedeelde script meet de hoogte op (`--hdp-header-hoogte`), zodat daar geen vast getal in de CSS staat dat stilletijds niet meer klopt.
- Op een telefoon was de header 176px hoog — een vijfde van het scherm, permanent bezet. Die is nu 139px: kleiner logo, minder lucht, en aan de zijkanten niet langer de 70%-inhoudsbreedte van de rest van de site (daardoor bleef er maar 288px over voor de knoppen).
- Browsertests: 11 (was 9).

## 1.69.0

**Een onderdelenclaim gaat pas in behandeling als het onderdeel in Stiens is — en dat staat nu op elk scherm waar de dealer het nodig heeft.**
- Dit was een vinkje "ik stuur onderdelen retour" met een regeltje eronder. Daarmee was het iets dat een dealer kon overslaan, terwijl het bepaalt of zijn claim überhaupt in behandeling komt.
- Bij een onderdelenclaim is het vinkje weg: terugsturen is daar de regel, geen keuze. Boven het formulier staat nu een kader dat uitlegt waarom (wij beoordelen het onderdeel zelf) en dat het ticketnummer op de zending moet.
- Eén uitzondering: kiest de dealer "niet ontvangen", dan is er niets om terug te sturen en verdwijnt het kader. Zonder die uitzondering zou zo'n claim voorgoed blijven hangen.
- Na indienen krijgt de dealer een eigen bevestiging: geen vraag ("stuurt u onderdelen retour?") maar een opdracht, met het ticketnummer erin.
- Op het ticket zelf blijft het staan zolang wij op het pakket wachten, inclusief ticketnummer. In de route vertelt de stap "Behandeling" nu ook waarom er nog niets gebeurt: "Start zodra het onderdeel binnen is".
- Bij een machineclaim blijft het een vinkje; die reparatie is vaak ter plekke gedaan en dan komt er niets retour.
- Tests: 298 (was 282).

## 1.68.1

**Content en huisstijl worden nu ook via FileBird gevuld, en de mappen verschijnen ook op een site waar ze al eens zijn aangemaakt.**
- De contentbibliotheek had nog geen eigen FileBird-hoofdmap: die bestanden moesten met de hand als "content" worden aangemerkt. Nu geldt voor alle drie de bibliotheken dezelfde werkwijze — bestand in de juiste map zetten in de mediabibliotheek en het staat in het portaal.
- Mappen onder "Content dealerportaal" houden de taalcode (`Merk (NL)`), want bij advertentiemateriaal maakt de taal uit. Onder "Huisstijl dealerportaal" blijft die weg: een logo heeft geen taal en hoort bij iedere dealer te staan.
- Verplaatst iemand een bestand van de ene hoofdmap naar de andere, dan verhuist het nu ook in het portaal naar de bijbehorende pagina.
- Opgelost: de mappen werden eenmalig aangemaakt met een vlag "gedaan" in de database. Op een site waar die vlag al stond — zoals live, van de downloadsmappen — zouden de nieuwe hoofdmappen er nooit bijkomen en deed de koppeling daar stilletjes niets. Die vlag is vervangen door een versienummer, dat omhoog kan zodra er een map bijkomt.
- Tests: 282 (was 279).

## 1.68.0

**Huisstijl & media: logo's, kleuren en lettertypen om te downloaden.**
- Dealers zetten het Homburg-logo op hun eigen site, briefpapier en advertenties. Tot nu toe moesten ze daarvoor mailen of een bestand uit een oude nieuwsbrief vissen.
- Derde categorie naast Downloads en Content, met een eigen pagina (`/huisstijl/`) en een tegel op de startpagina. Zelfde zoeken, filteren en beveiligde uitlevering als de andere twee.
- **Vullen gaat via FileBird**, net als bij downloads: een nieuwe hoofdmap **"Huisstijl dealerportaal"** met een submap per merk. Een bestand dat daarin belandt, verschijnt vanzelf op de huisstijlpagina. Verplaatst u het later naar Downloads, dan verhuist het mee.
- De submappen onder huisstijl hebben géén taalcode, anders dan bij downloads. Een logo is niet in één taal; zonder taalcode blijft de regio leeg en ziet iedere dealer het bestand, of hij het portaal nu op Nederlands of Frans heeft staan.
- Huisstijl staat in een eigen hoofdmap en niet als submap van Downloads: logo's zijn iets anders dan prijslijsten, en zo is in de mediabibliotheek meteen te zien waar iets terechtkomt.

**Opgelost bij het bouwen hiervan: een nieuwe categorie zou de oude bestanden opslokken.**
- De query kende alleen "content" en "al het andere". Dat "al het andere" vangt ook de bestanden van vóór dit onderscheid op — die hebben geen categorie-meta en zijn altijd downloads geweest. Huisstijl viel daar doorheen en de pagina bleef leeg.
- Nu vangt alleen "download" die erfenis op; elke andere categorie kijkt strikt naar zijn eigen meta. Vier tests bewaken dat de drie categorieën elkaars bestanden niet oppikken.

**Drie keer dezelfde pagina is één pagina geworden.**
- Downloads, content en huisstijl stonden als drie identieke blokken code naast elkaar, op één woord na. Bij de derde was dat niet meer vol te houden.

## 1.67.0

**Een stap "onderdeel onderweg" in de route van een ticket.**
- Stuurt een dealer een onderdeel retour naar Stiens, dan ligt de claim stil tot dat pakket binnen is. Hij zag alleen "ingediend" staan en kon denken dat er niets gebeurde, terwijl de bal bij de post lag.
- De stap staat tussen **Ingediend** en **Behandeling**, en verschijnt alleen als er ook echt iets onderweg is — bij elke claim een grijze "onderdeel onderweg" zou ruis zijn.
- Twee toestanden: **Onderdeel onderweg** (oranje, met het ticketnummer erbij dat op de zending moet) en **Onderdeel ontvangen** (groen, met de datum van binnenkomst).
- Geen nieuwe status maar een feit uit de administratie: het vinkje "ik stuur onderdelen retour" bij het indienen, en `onderdeel_ontvangen_op` dat de app zet zodra het pakket op de balie ligt. Daar was in 1.63.0 al ruimte voor gemaakt.

**Opgelost: een datum verdween als er geen naam bij stond.**
- De route toonde het tijdstip van een stap alleen samen met de naam van wie hem zette. Bij "onderdeel ontvangen" is er geen naam — er kwam een pakket binnen — en dan viel juist de datum weg die het punt is.

## 1.66.0

**Datums volgen nu de taal van de dealer.**
- Een Waalse dealer kreeg "22 oktober 2026" te lezen tussen verder Franse tekst, op elf plekken in het portaal — ingediend op, garantie tot, tijdstippen in het ticketgesprek.
- Bewust niet via WordPress' eigen taalomschakeling: er staan op deze site geen Franse taalbestanden, dus die zou terugvallen op het Engels ("22 October 2026"). Dat is erger dan het Nederlands laten staan. Het portaal draagt al zijn eigen teksten; de maand- en dagnamen horen daarbij.
- Acht tests, waaronder alle twaalf maanden apart — een verschoven tabel valt dan meteen op.

**Dealers kunnen een bericht wegklikken.**
- Zonder dat blijft een mededeling van vorige maand boven de tegels staan, ook als de dealer hem allang gelezen heeft.
- Per dealer onthouden: wie het wegklikt is het kwijt, zijn collega ziet het nog gewoon. Een nieuw bericht komt er altijd weer boven.
- Een formulier met nonce en geen kale link: wegklikken verandert iets, en dat hoort niet te gebeuren doordat een browser of mailprogramma even een adres ophaalt.

**Opgelost: een verzendknop verloor zijn eigen waarde.**
- Het gedeelde scriptje zette de verzendknop op "bezig" tijdens het versturen. Een knop die op dat moment wordt uitgeschakeld telt niet meer als verzender, waardoor zijn `name`/`value` nooit aankwam. Alle bestaande formulieren dragen hun gegevens in verborgen velden, dus dit heeft nooit iets gebroken — maar het zou de volgende die zo'n knop gebruikt stilzwijgend opbreken.
- De knop wordt nu pas ná het versturen uitgeschakeld. Het bericht-id staat daarnaast in een verborgen veld, zodat het wegklikken niet van die subtiliteit afhangt.

## 1.65.1
- **Berichten kunnen nu in het Nederlands én het Frans.** Onder de gewone editor staat een blok "Franse vertaling" met een eigen titel en tekst. De dealer krijgt de taal die hij in het portaal heeft gekozen.
- Het Franse veld is niet verplicht. Blijft het leeg, dan ziet een Franstalige dealer de Nederlandse tekst — een bericht dat alleen in het Nederlands bestaat is beter dan geen bericht.
- In de berichtenlijst in wp-admin staat een kolom "Talen" (NL + FR of alleen NL), zodat in één oogopslag te zien is wat nog een vertaling mist.

## 1.65.0

**Homburg kan nu een bericht op de startpagina zetten, gericht op merken.**
- De informatiestroom liep één kant op: bestanden en claims verschenen vanzelf, maar er was geen manier om iets te zéggen. Een prijswijziging of een leveringsprobleem bij één fabrikant ging per mail, en kwam daarmee bij iedereen terecht of bij niemand.
- Te plaatsen in wp-admin onder **Berichten**. Per bericht: voor welke merken (niets aanvinken = alle dealers), het soort (Melding of Belangrijk) en een optionele datum tot wanneer het blijft staan.
- Dezelfde merkregels als bij downloads, zodat "merk" overal hetzelfde betekent: geen merk = voor iedereen, en een dealer bij wie nog geen merken zijn ingesteld ziet alles — die mag niet per ongeluk niets meer te zien krijgen.
- Een gewone melding is blauw, "Belangrijk" oranje: in het hele portaal de kleur van "hier moet u iets mee". Gebruik dat spaarzaam, anders valt het niet meer op.
- Hoogstens drie berichten tegelijk. Daarboven wordt het een nieuwsbrief en vallen de tegels eronder weg; staat er een vierde klaar, dan verdwijnt het oudste vanzelf. Dat dwingt af dat oude berichten een einddatum krijgen in plaats van eeuwig te blijven staan.
- Elf tests leggen de afscherming vast — dat is het stuk dat stil misgaat: er komt geen foutmelding van, je ziet alleen te veel of te weinig.

## 1.64.0 / thema 1.28.0

**Eén statuspalet in plaats van twee.**
- Bestellingen en garantieclaims hadden allebei hun eigen set statuskleuren, op verschillende momenten gekozen: "afgerond" was groen `#1a7d3a` en "goedgekeurd" groen `#1d6b2d`. Hetzelfde idee, net een andere kleur — niemand ziet het verschil, en het kost onderhoud zonder iets op te leveren.
- Nu vijf betekenissen: neutraal, loopt, aan zet, klaar, gestopt. Elke status wijst naar een betekenis; nul losse statuskleuren over in de hele stylesheet.
- **Twee zichtbare gevolgen.** "Wordt verwerkt" en "machine in beoordeling" zijn van oranje naar blauw gegaan: oranje is nu voorbehouden aan "u bent aan zet", en bij een lopende bestelling hoeft de dealer juist niets. En "bij fabrikant" deelt de kleur van "in behandeling" — in beide gevallen loopt het; wélke het is staat in het label.
- Een test bewaakt dat elke status een regel hééft en dat die uit het palet komt. Dat is hier twee keer misgegaan: een nieuwe status belandde als kale tekst op het scherm.

**Eén paginakop, en overal dezelfde terugweg.**
- Acht keer stond dezelfde kop gekopieerd in de code; de Winkel had een klein kruimelpad en Mijn account alleen een titel met een streep. Nu één component.
- Garantie en Downloads hadden twéé terugknoppen (boven én onder), de Winkel en Bestellingen geen enkele. Zeven dubbele knoppen verwijderd; de Winkel en Mijn account hebben nu ook een terugweg.
- Mijn account zei altijd "Mijn account", ook op Bestellingen of Adressen. Nu staat het onderdeel in de titel, met "Mijn account" erboven — en die titel komt uit dezelfde lijst als het menu ernaast, dus hij klopt altijd met het tabblad dat oplicht.

**Garantie is een werkblad geworden: filters links, claims in het midden, de claim rechts.**
- Dit was een stapel secties onder elkaar: twee kaarten, een zoekveld, de claims, de machines. Om één claim te lezen moest je weg van het overzicht, en na de terugweg stond je weer bovenaan.
- Links filtert u op Alles, Wacht op u, In behandeling, Afgehandeld of Mijn machines, met achter elke regel hoeveel het er zijn. Staat er iets op u te wachten, dan kleurt die telling. Zonder die cijfers moet u elke groep openklikken om te zien of er iets in zit.
- Rechts opent de gekozen claim meteen: status, route, bij wie het ligt, het gesprek en het antwoordvak — zonder de lijst kwijt te raken. Het volledige ticketscherm (met route, machinekaart en bijlagen, die breedte nodig hebben) blijft bestaan en is één klik verder.
- Dezelfde indeling als Contactpersonen in de Homburg App, zodat de dealer en de binnendienst naar hetzelfde kijken.

**Lege schermen vertellen nu wat u kunt doen.**
- "U heeft op dit moment geen lopende claims." was een grijze regel tekst. Garantie is juist waar een nieuwe dealer begint, met nul machines en nul claims; dat scherm had de schraalste lege toestand van de hele site.
- Nu een kaart met icoon, uitleg en een knop: nog geen claims → meld eerst een machine aan; lege groep → toon alle claims; geen claim gekozen → kies er links een.

## 1.63.1
- **De zelfcontrole sloeg vals alarm.** Direct na het live zetten meldde hij dat er twee bestanden op het inlogscherm niet meer op te halen waren. Allebei onterecht: de sfeerfoto komt van homburg-belgium.com en staat helemaal niet in onze uploadsmap, en het tweede "bestand" was een sterretje uit een stylesheet.
- Oorzaak: de controle rekende elk pad om naar ons eigen domein. En binnen een afgeschermde map geeft alles wat er niet is een 403 in plaats van een 404 — waardoor de regel "alleen een 403 telt" die bestanden toch aanwees.
- Nu kijkt de controle alleen naar bestanden van de eigen site, en alleen naar wat op een bestandsnaam lijkt. Drie tests leggen beide gevallen vast. Een melding die onterecht afgaat, leer je namelijk negeren.

## 1.63.0

**Garantie: twee soorten claims, en de velden die de serviceafdeling vroeg.**
- Een claim begint nu met één vraag: gaat het over een machine of over bestelde onderdelen? Daarna volgt een formulier dat alleen vraagt wat bij die soort hoort. Dat scheelt een scherm vol velden die aan- en uitspringen.
- **Onderdelenclaim**: geen machine nodig (een doos die verkeerd aankomt hoort niet bij een serienummer), wel verplicht een Homburg-factuurnummer en de keuze wat er mis is — defect, beschadigd aangekomen, onjuist geleverd, onjuist besteld of niet ontvangen.
- **Te claimen onderdelen** zijn echte regels geworden: onderdeelnummer, aantal, bedrag en of het van een Homburg-factuur komt. Drie regels staan klaar, meer kan met één knop. Staat er een regel van een andere leverancier bij, dan wordt de claim geweigerd tot die factuur als bijlage meekomt.
- Nieuw op beide formulieren: **te claimen tijd** (met de uitleg dat reistijd niet meetelt), **referentie van de klant**, **opmerkingen** en een vinkje **"ik stuur onderdelen retour naar Stiens"** — zodat de werkplaats weet dat er iets onderweg is.
- Bedragen worden in hele centen opgeslagen. Als kommagetal is 6,11 niet precies op te slaan (het wordt 6.1100000000000003) en dan loopt het optellen van tien claimregels zichtbaar mis.
- De herkomst per regel is een keuzemenu en geen vinkje: een vinkje dat uit staat stuurt niets mee, waardoor de rijtjes uit het formulier niet meer gelijk lopen en het bedrag van regel 2 bij onderdeel 3 belandt. Een test bewaakt precies dat.
- Vraagt om de kolommen die op 7 oktober 2026 aan de claimtabel van de Homburg App zijn toegevoegd.

**Twee kleine dingen uit de doorlichting.**
- Het instellingenvenster in de header gebruikte een `h1`. Omdat dat venster op elke pagina staat, heette elke pagina voor een schermlezer en voor Google "Accountinstellingen" — ook de winkel en garantie. Nu een `h2`; het venster houdt zijn naam via `aria-labelledby`.
- De sitemap gaf `/author/erik/` prijs, en daarmee een inlognaam. Gebruikers staan er niet meer in.

## 1.62.0

**De webshop was voor iedereen te bekijken — inclusief de dealerprijzen.**
- Zonder in te loggen kon je de hele catalogus openen: productnamen, prijzen en bestelknoppen. Ook losse productpagina's gaven de prijs vrij. Op de live site stonden er twee producten, waarvan één met prijs; met de volledige catalogus erin zouden dat er 250 zijn.
- Het waren vier deuren naar dezelfde kamer, en ze zijn alle vier dicht: de pagina's zelf (winkel, product, merk, categorie, winkelwagen, afrekenen en productzoeken) sturen een bezoeker zonder goedgekeurd dealeraccount naar het inlogscherm; de Store API en `/wp/v2/product` geven 401; en producten en productcategorieën staan niet meer in de sitemap, zodat ze ook niet via Google alsnog naar buiten komen.
- De beheer-API `/wc/v3` blijft met rust gelaten: die vraagt al om een sleutel en daar kan een koppeling aan hangen. Een te brede afscherming legt zoiets stilletjes plat.
- Dezelfde controle als de rest van het portaal gebruikt, dus een dealer die nog op goedkeuring wacht komt er net zomin in als op de downloadpagina. Elf tests leggen vast wie erbij mag en via welke routes.

**Filters zijn nu ook bruikbaar met een schermlezer.**
- Het statusfilter bij Bestellingen en het sorteerfilter bij Downloads hadden het woord ernaast staan maar niet eraan gekoppeld: je hoorde alleen "keuzemenu". Nu een echt label.
- De regio- en merkknoppen bij Downloads zijn knoppengroepen, geen invoervelden; die hebben een groepsnaam gekregen in plaats van een label.

**Garantie: drie punten uit de lijst van de serviceafdeling.**
- Het aantal hectares is weg bij het aanmelden van een machine. Dat is daar een momentopname die meteen veroudert; wat telt is de stand op het moment van de klacht, en die vraagt het claimformulier al.
- "Wat is er aan de hand?" heet nu **Te claimen defect**, met de uitleg erbij dat het om het hele verhaal gaat: wat er gebeurde, welke diagnose en wat er vervangen is. Een claim komt immers ná de reparatie.
- "Benodigde onderdelen" heet nu **Te claimen onderdelen**, met de vraag om onderdeelnummer, aantal en bedrag per regel en om de factuur mee te sturen als het onderdeel niet van Homburg komt. Dit is een tussenstap: echte invoervelden per onderdeel vragen om een uitbreiding van de claimtabel in de app.
- Bij foto's staat nu wat er minimaal bij hoort: het typeplaatje met het serienummer en het defect zelf.
- De bevestiging na het indienen vermeldt dat onderdelen retour naar Stiens moeten onder vermelding van het ticketnummer, en dat de claim pas behandeld kan worden zodra dat onderdeel binnen is.

## 1.61.0 / thema 1.27.0

**De webshop opent voortaan als lijst.**
- Onderdelen hebben vrijwel nooit een foto, dus elke tegel reserveerde een groot vlak voor een grijze placeholder met het artikelnummer erin — een nummer dat er twee regels lager nog een keer stond. Dat vlak is weg; het nummer is een eigen kolom geworden.
- Vaste kolommen: nummer, omschrijving, merk, prijs (rechts uitgelijnd, cijfers even breed), aantal en de bestelknop. Daarboven staan kolomkoppen, zodat duidelijk is waar een getal voor staat.
- **48 onderdelen per pagina in plaats van 16.** Voor 250 onderdelen waren zestien pagina's nodig; nu zes. Een rij is 62 px hoog tegen ruim 310 px voor een tegel.
- **Een aantalveld per rij.** Een dealer bestelt zelden één bout; het aantal gaat mee in de bestellink, dus zeven stuks is één handeling in plaats van zeven.
- De bestelknop is omlijnd in plaats van rood: op één scherm staan er achtenveertig van, en dan betekent rood niets meer. Rood blijft voor de winkelwagen in de kop.
- De merkkolom valt terug op de categorie, omdat de losse merk-taxonomie bij vrijwel geen onderdeel is ingevuld — een kolom die bij negen van de tien rijen leeg blijft is erger dan geen kolom. In de tegelweergave blijft die terugval verborgen; daar staat de categorie al op het blauwdrukvlak.
- De tegelweergave blijft gewoon bestaan en is onveranderd; wie hem kiest, houdt hem (de keuze wordt onthouden). Een link met `?weergave=raster` werkt ook.

**De startpagina op de telefoon is bijna een derde korter.**
- De tegels stonden onder elkaar, elk ruim 350 px hoog met icoon, titel, drie regels uitleg en een knop over de volle breedte. De pagina werd daardoor 5006 px lang: ruim zes schermen scrollen om bij Garantie te komen.
- Nu twee tegels naast elkaar met icoon en naam; de hele tegel is aanklikbaar. De uitleg blijft staan op de brede weergave, waar er ruimte voor is. De pagina is nog 3653 px.

## 1.60.1
- **De zelfcontrole van 1.59.0 keek bijna nergens naar.** Op live meldde hij "in orde" op grond van twee bestanden, terwijl een uitgelogde bezoeker er dertien te zien krijgt. Oorzaak: de header zet zijn logo zonder domeinnaam in de pagina (`/wp-content/uploads/...`) en de controle zocht alleen op de volledige URL mét domein — precies de afbeeldingen die op elke pagina staan, werden dus overgeslagen.
- De controle kijkt nu ook op de winkelpagina, want het merklogo staat alleen daar.
- En hij meldt alleen nog een bestand dat echt wordt afgeschermd (403). Een bestand dat niet bestaat (404) is een ander probleem en leverde anders een melding op die zei dat de witte lijst aangevuld moest worden — wat daar niets aan verhelpt.
- **Witte lijst van 4 naar 3 termen.** De sfeerfoto op de achtergrond van het inlogscherm stond erop, maar die komt van homburg-belgium.com en staat helemaal niet in deze map. Die regel beschermde dus niets en zette alleen onnodig bestanden met die naam open.
- Vier tests leggen dit vast, inclusief het geval dat de fout veroorzaakte.

## 1.60.0 / thema 1.26.0
- **Een voordeur in plaats van twee.** "Mijn account" toonde een eigen tegeloverzicht in precies dezelfde kaartstijl als de startpagina — zes tegels, met "Downloads" zelfs op beide pagina's. Wie op "Mijn account" klikte, leek weer op de startpagina te belanden en moest zelf uitvogelen welke van de twee overzichten hij nu voor zich had.
- De startpagina is nu de enige plek die zich als startpagina gedraagt. "Mijn account" opent meteen op de bestellingen — waar een dealer feitelijk voor komt — en is verder gewoon een accountgedeelte: adressen, accountdetails, favorieten en snel bestellen staan in de navigatiekolom ernaast. Er is dus niets onbereikbaar geworden.
- "Dashboard" is uit het accountmenu gehaald, want dat item stuurde je door naar het item eronder. Een oude bladwijzer naar /my-account/ komt nog steeds goed uit: die wordt doorgestuurd. Uitgelogd blijft daar het inlogformulier staan.

## 1.59.0
- **De hele uploadsmap is dicht voor wie niet is ingelogd.** Foto's, documenten en al het andere in de mediabibliotheek zijn niet langer op te halen met alleen een link. Tot nu toe gold dat alleen voor bestanden die aan een download gekoppeld waren; alles daarbuiten — productfoto's, losse prijslijsten, onderdelenboeken — stond gewoon open.
- Dat gebeurt op de webserver zelf, door te kijken of er een inlogkoekje meekomt. Geen koekje, dan weigert hij meteen, zonder PHP en dus zonder snelheidsverlies. Een winkelpagina vol productfoto's zou anders evenzoveel keer WordPress moeten opstarten.
- **Wat deze laag niet doet:** controleren of dat koekje echt is. Wie er bewust een verzint komt erlangs. Voor prijslijsten en handleidingen blijft daarom de bestaande, zwaardere afscherming bestaan: die staan buiten de openbare map en gaan altijd langs een echte toegangscontrole.
- Een korte witte lijst houdt openbaar wat het inlogscherm zelf nodig heeft: het Homburg-logo, de favicon, de sfeerfoto en het merklogo op de winkelpagina. Die lijst is gemeten aan de uitgelogde pagina's, niet gegokt, en matcht alleen op het begin van een bestandsnaam — anders zou een korte term als "dc" elk bestand met die letters doorlaten.
- De plugin schrijft dat bestand zelf en loopt het bij elk bezoek aan wp-admin na, zodat het zichzelf herstelt en een gewijzigde witte lijst vanzelf meekomt met een deploy.
- Dagelijkse zelfcontrole erbij: die haalt het inlogscherm op zoals een bezoeker dat ziet en kijkt of alle afbeeldingen daarop nog bereikbaar zijn. Vergeet iemand een nieuwe afbeelding op de witte lijst te zetten, dan staat dat in het logboek en bovenaan wp-admin — in plaats van dat je het van een dealer moet horen.

## 1.58.3
- Het NIEUW-label was nog steeds onleesbaar: de letters waren grijs, niet wit. Oorzaak was een regel die *alle* spans binnen een downloadregel grijs kleurde — bedoeld voor de omschrijving onder de titel, maar hij raakte ook het label binnen de titel, en die regel was specifieker dan het label zelf. De regel is nu beperkt tot directe kinderen, zodat hij alleen de omschrijving pakt.

## 1.58.2
- Het label **NIEUW** bij recente downloads was slecht leesbaar: wit op het felle huisrood haalt op 11 pixels te weinig contrast. Het label staat nu op donkerrood (8,7:1 in plaats van 6,4:1), is iets groter en heeft meer lucht om de letters. De twee kleuren staan hier als vaste waarde in plaats van via een themavariabele, zodat het label leesbaar blijft ook als die variabelen ergens niet doorkomen.

## 1.58.1
- De route op het ticketscherm stond nog niet goed: label en naam kwamen naast elkaar in plaats van onder elkaar. Oorzaak was een tweede, oudere definitie van diezelfde route verderop in de stylesheet — die zette een stap op `display: flex` en won daarmee van de nieuwe horizontale opmaak. De oude versie is weg; de route staat nu nog op één plek beschreven.
- Twee statussen hadden nooit een kleur gekregen: **"Bij de fabrikant"** en **"Afgewezen door fabrikant"** stonden als kale tekst op het scherm. Die zijn in 1.54.0 aan de statuslijst toegevoegd maar niet aan de opmaak. Alle zeven hebben nu hun eigen kleur.
- De paperclip bij "Bestand meesturen" verscheen als `ǴCE`: de CSS-code voor dat teken was te lang geschreven, waardoor de browser er een letter in las en de rest als tekst liet staan. Vervangen door een echt icoon uit de iconenset van het portaal, zoals overal elders.
- "Dit ticket ligt bij Homburg" stond als losse tekst tussen de witte kaarten in de zijkolom; dat blok heeft nu dezelfde kaartvorm als de rest.
- De route krijgt een maximumbreedte. Op een breed scherm dreven de vier stappen anders zo ver uit elkaar dat het geen route meer las.

## 1.58.0
- **Het ticketscherm is een werkblad geworden in plaats van een document.** Links het gesprek, dat leeft en groeit; rechts een kolom met de feiten die blijft staan terwijl je leest. Eerder stond alles onder elkaar: je moest bij elk nieuw bericht verder scrollen om te zien wélke machine het ook alweer was, en de claimgegevens stonden ónder het gesprek. Het scherm was 2511 pixels hoog voor een claim met drie stappen en drie berichten; nu past het in één beeld op een laptop.
- **"Dit ticket wacht op u" staat bovenaan** in plaats van onderaan de pagina, mét de vraag van Homburg erbij en een knop naar het antwoordvak. Dat is het belangrijkste feit van het scherm en stond onder de bijlagen.
- De route is een **horizontale balk over de volle breedte** geworden. Als verticale lijst gebruikte die een smalle kolom links en bleef de rest van de band leeg.
- **Bijlagen kunnen nu ook ná het indienen mee.** Dat was een gat in de gang van zaken, geen opmaakkwestie: Homburg vraagt in de praktijk vaak om een foto van het typeplaatje, maar een dealer kon er geen meer toevoegen — daar liep het proces dood. Stuur je alleen een bestand zonder tekst, dan zet het portaal er zelf een regel bij met de bestandsnamen, zodat het gesprek leesbaar blijft.
- Drie dingen die al in de administratie stonden maar nergens getoond werden, staan er nu bij: **hoelang een ticket al op dezelfde status staat**, de **garantiebalk van de machine** (loopt de dekking bijna af?), en hoeveel **eerdere claims** er op diezelfde machine liepen.
- Berichten hebben initialen in een rondje gekregen en een leesbare regellengte. Op een smal scherm schuift de rechterkolom boven het gesprek, zodat "u bent aan zet" als eerste in beeld komt.

## 1.57.0
- **De dealer kan nu zelf indienen.** De drie formulieren staan open en schrijven rechtstreeks naar de claimadministratie: een machine aanmelden, een claim indienen, en reageren op een lopend ticket. Daarmee is de keten rond — wat een dealer hier instuurt, staat direct in de Homburg App bij Gerard, Erik en Benne, en wat zij daar schrijven komt hier terug.
- Foto's en facturen gaan mee naar de afgeschermde opslag in Supabase: JPG, PNG, WEBP, HEIC of PDF, maximaal tien bestanden van 10 MB. Op de meegestuurde bestandsnaam wordt niet vertrouwd — WordPress bepaalt zelf wat voor bestand het werkelijk is. Elk bestand krijgt een eigen pad, zodat twee dealers met dezelfde bestandsnaam elkaar niet overschrijven.
- Een claim gaat over een machine uit je **eigen** lijst: het portaal zoekt die op serienummer op binnen de machines van de ingelogde dealer, in plaats van een meegestuurd id te vertrouwen. Zonder dat zou je met een geraden id op andermans machine kunnen claimen. Hetzelfde geldt voor reageren op een ticket.
- Na het opslaan wordt doorverwezen naar een nette URL. Daardoor dient een dealer bij het verversen van de pagina zijn claim niet nog een keer in.
- Gaat er iets mis, dan blijft staan wat er al was ingevuld. Bij een lang klachtverhaal is dat het verschil tussen "even opnieuw" en "laat maar". De melding van de database gaat mee terug waar die bruikbaar is ("Dit serienummer is al aangemeld"), en wordt vervangen door iets begrijpelijks waar die dat niet is.
- Claimen op een machine die nog beoordeeld wordt, mag — zoals afgesproken. In de keuzelijst staat er dan bij dat de aanmelding nog wordt beoordeeld.
- Een bericht van de dealer zet bewust geen status: de dealer beantwoordt een vraag, Homburg bepaalt wat dat voor de status betekent. En een dealer kan nooit een interne notitie schrijven.

## 1.56.0
- **Het garantieportaal is gekoppeld aan de echte claimadministratie.** De voorbeelddata is weg; machines, claims en het gesprek komen nu uit de Supabase van de Homburg App, waar Homburg-medewerkers ze afhandelen. Claimnummers (T100001 enzovoort) worden daar uitgedeeld.
- Nieuw: **HDP_Supabase**, een koppellaag met koppelingen *per naam*. Homburg heeft twee Supabase-projecten — één voor de Homburg App en één voor het portaal zelf — en elke module vraagt om degene die hij nodig heeft. Komt er later een derde bij, dan is dat één regel erbij.
- De sleutel staat in `wp-config.php` (buiten git, niet in een deploy, niet in een database-export) met het instellingenscherm als terugval voor testomgevingen. Alle aanroepen gebeuren server-side in PHP; er gaat niets van de sleutel naar de browser.
- **Elke opvraag is gefilterd op het accountnummer van de ingelogde dealer**, in de opvraag zelf en niet in een controle achteraf. Claimnummers lopen op, dus zonder dat filter zou een dealer andermans claims kunnen openen door het nummer in de URL te veranderen — dezelfde fout die bij de downloads gemaakt bleek (1.51.0). Een test controleert nu dat élke opvraag dat filter draagt.
- Het gesprek wordt gelezen via de afgeschermde view `verloop_voor_dealer`, die de interne notities van Homburg weglaat. Rechtstreeks uit de logboektabel lezen zou ook werken, maar hangt dan aan een filter dat je kunt vergeten — en dan lekt er een interne notitie naar een dealer.
- **Een storing bij de claimadministratie sleept de rest van het portaal niet mee.** De garantiepagina zegt dan eerlijk dat ze de gegevens even niet kan ophalen (in plaats van lege lijsten te tonen, waardoor een dealer denkt dat zijn claims verdwenen zijn); webshop en downloads werken gewoon door. De twee ingangen blijven staan, zodat aanmelden en indienen mogelijk blijven.
- Machines die nog beoordeeld moeten worden of zijn afgewezen, zijn als zodanig herkenbaar in het overzicht — inclusief de reden van afwijzing. Dat bepaalt of een claim erop verder kan.
- De testset bootst de claimadministratie na via WordPress’ eigen http-filter. De tests draaien daardoor zonder internet en controleren meteen *wát* er aan de administratie gevraagd wordt; juist daar zit de afscherming.
- De indienformulieren zijn nog ontwerp (velden staan uit). Lezen gebeurt al wel echt.

## 1.55.1
- Zoekveld op /garantie, dat claims én machines tegelijk filtert. Een dealer zoekt op wat hij in zijn hoofd heeft — een serienummer, een machinenaam, een claimnummer, een eindklant — en hoeft niet eerst te bedenken of dat bij claims of bij machines hoort. Zoeken gebeurt in de browser: de lijsten staan al op de pagina, dus een serverronde per toetsaanslag zou alleen maar trager voelen. Een sectiekop verdwijnt mee als er in die lijst niets overblijft.
- De lopende claims zaten als één blok met scheidingslijntjes aan elkaar vast; het zijn nu losse kaarten met ruimte ertussen, net als de machines eronder. Elke claim is een eigen ding waar je op klikt, en dat hoort er ook zo uit te zien.
- De sectiekoppen "Lopende claims" en "Mijn machines" hadden de rode streep eronder niet gekregen die het ticketscherm wel heeft. Nu overal dezelfde sectiekop, en meer lucht boven een volgende sectie.

## 1.55.0
- **/garantie is opnieuw ingedeeld (optie 1).** Bovenaan de twee dingen die een dealer hier kan doen — machine aanmelden en claim indienen — daaronder wat er speelt: eerst wat op u wacht, dan de lopende claims, dan de aangemelde machines. De vraag waarmee iemand deze pagina opent is bijna altijd "moet ik iets doen?", en dat antwoord staat nu vóór alle lijsten in plaats van ertussen.
- **Beide formulieren staan op hun eigen scherm.** Het indienformulier hing open onder de claimlijst; daar las het als een naschrift in plaats van als een taak, en met twee formulieren was dat helemaal niet vol te houden.
- **Nieuw onderdeel: machines aanmelden voor garantie.** Een machine heeft een merk, serienummer, aankoopdatum, eindklant en aantal hectares, en krijgt een eigen garantietermijn. In het overzicht staat per machine een balk met hoelang de dekking nog loopt; een garantie die binnen een half jaar afloopt springt eruit. Zonder die balk ziet niemand aankomen dat dekking verloopt, en dat is nu juist het moment om nog te claimen.
- **Een claim gaat over een aangemelde machine.** Op het claimformulier kiest de dealer de machine uit zijn eigen lijst; merk, serienummer en aankoopdatum komen daaruit mee. Dat scheelt overtypen én tikfouten in precies de velden waarop de fabrikant controleert. Het aantal hectares wordt wél opnieuw gevraagd: dat is de stand op het moment van de klacht.
- **Serienummer staat nu in de titel**, op het overzicht, bij de machines en op het ticketscherm. Een dealer heeft vaak meerdere machines van hetzelfde type staan; zonder serienummer weet die niet welke bedoeld wordt.
- Het veld "Urenstand" heet voortaan **"Aantal hectares"** — dat is wat er bij deze machines toe doet.
- Het overzicht toont alleen nog lopende claims, met een link naar alle claims. Afgehandelde claims vragen niets meer en zouden de lijst alleen maar langer maken naarmate een dealer er meer indient.

## 1.54.0
- **Garantieclaims werken als tickets.** Elke claim heeft een eigen scherm met een ticketnummer, de route, het gesprek, de claimgegevens en de bijlagen — dezelfde opbouw als het declaratiescherm dat Homburg al gebruikt, zodat het meteen vertrouwd leest. De rijen in het claimoverzicht zijn nu links naar dat scherm.
- **De route laat zien waar een claim ligt en wat er nog komt.** Gezette stappen krijgen een vinkje, de huidige stap springt eruit, en de fases die nog moeten volgen staan er grijs onder. Onderaan staat in één regel wie er aan zet is: u, Homburg, of niemand meer omdat het ticket is afgehandeld.
- **Statussen uitgebreid met de twee vertakkingen uit het echte proces:** tijdens de behandeling kan een claim bij Homburg liggen óf *bij de fabrikant*, en de afloop is goedgekeurd, afgewezen, óf *afgewezen door fabrikant*. Omdat dat geen rechte lijn is, werkt de route met fases (indienen → behandeling → besluit): binnen een fase bepaalt de status wat er precies aan de hand is. Zo blijven beide vertakkingen op één tijdlijn staan.
- **Opmerkingen van Homburg komen bij de dealer terecht.** Het verloop van een ticket is één logboek waarin statuswijzigingen én losse berichten door elkaar staan. Elke statuswijziging kan een toelichting dragen, en daarnaast kan er los een bericht bij — van beide kanten. De dealer leest dat hele gesprek terug op het ticketscherm, inclusief bij welke status een opmerking hoorde. Dat is waar de transparantie zit: wat Gerard in de app schrijft, ziet de dealer hier.
- Het antwoordvak voor de dealer staat er alvast in (nog niet actief, net als het indienformulier) — zonder terugpraten is het geen ticket maar een mededeling.
- De controle "hoort dit ticket bij déze dealer" zit er nu al in, vóór er echte data is. Ticketnummers lopen op, dus zonder die controle zou een dealer andermans claims kunnen openen door het nummer in de URL te veranderen — precies de fout die bij de downloads gemaakt bleek (zie 1.51.0). Een onbekend nummer en een nummer van een andere dealer geven hetzelfde antwoord, zodat je aan het verschil niet kunt aflezen welke nummers bestaan.

## 1.53.1
- De vijfde tegel paste niet in het raster: dat stond hard op vier kolommen, dus Garantie bleef links onderaan hangen met driekwart lege rij ernaast. Garantie staat nu als brede kaart onder de vier, met icoon, tekst en knop naast elkaar.
- Dat is geregeld met een nieuwe schakelaar **"Over de volle breedte"** op het portaalkaart-blok, niet met een CSS-regel die "de vijfde kaart" aanwijst. Je kunt dus in de editor zelf bepalen welke kaart breed staat, en het blijft kloppen als er later een kaart bij komt of verdwijnt. De kaart beslaat altijd een hele rij, of het raster op dat moment nu vier, twee of één kolom breed is; op telefoonbreedte valt hij vanzelf terug op alles onder elkaar.

## 1.53.0
- **Garantieportaal — eerste opzet (alleen het ontwerp).** Nieuwe tegel "Garantie" op de portaalstartpagina, naast Webshop/Configurator/Downloads/Content, en een bijbehorende pagina `/garantie/` met een overzicht van claims per status en het indienformulier. Net als de andere portaalpagina's een eigen blok, dus titel, omschrijving, sfeerbeeld en inlogtekst zijn aanpasbaar via "Bewerk pagina"; de tegel zelf is een gewone portaalkaart, dus die kan ook ter plekke aangepast of verplaatst worden.
- De claims komen nu nog uit voorbeelddata en er wordt niets opgeslagen: boven de lijst staat daarom een duidelijke melding dat het een voorbeeldweergave is, en alle formuliervelden staan uitgeschakeld. Bedoeld om het ontwerp te beoordelen voordat er een koppeling komt. De afhandeling gaat straks via de Homburg App op Supabase; de website wordt de voordeur die weet wie er inlogt en de status toont.
- Twee dingen zijn alvast op één plek vastgelegd, nog vóór die koppeling bestaat, zodat website en claimadministratie niet uit elkaar kunnen lopen: de **statuslijst** (`ingediend`, `in_behandeling`, `info_nodig`, `goedgekeurd`, `afgewezen`) en de **veldenlijst** van een claim. Een status die de app straks niet uit deze lijst kiest, valt terug op "in behandeling" i.p.v. een leeg vakje bij de dealer. De statussen en velden hebben allemaal een Nederlands én Frans label, met een test die dat bewaakt.
- Een claim met status "informatie nodig" krijgt bewust nadruk (eigen kleur, accentrand en een regel tekst): dat is de enige status waarbij de dealer zélf aan zet is, en zonder nadruk blijft zo'n claim liggen omdat niemand merkt dat er iets gevraagd is.
- De garantiepagina gebruikt de bredere paginakolom (90%) in plaats van de 70%-tekstkolom, zoals afgesproken voor werkpagina's. Het formulier staat daarbinnen juist in twee kolommen, omdat invoervelden over de volle breedte slechter te lezen zijn.
- **Zijvondst, meteen gefixt:** het inlogscherm las blind een introtekst-attribuut dat de downloads- en contentpagina helemaal niet definiëren. Een uitgelogde bezoeker die rechtstreeks naar `/downloads/` of `/content/` ging, leverde daardoor een PHP-waarschuwing op in het logboek. Het scherm verschijnt nu gewoon zonder intro als er geen is ingevuld, met een regressietest eronder.

## 1.52.0
- **Foutschermen staan niet langer buiten het portaal.** Liep een dealer tegen "geen toegang" of een verdwenen bestand aan, dan belandde die op het kale witte WordPress-foutscherm: geen logo, geen menu, geen taalkeuze en geen enkele link terug — je denkt dan dat het portaal stuk is in plaats van dat het bestand niet voor jouw account bedoeld is. Sinds de merkcontrole in het endpoint zit (1.51.0) was dat scherm ook nog eens vaker bereikbaar. Nu een scherm in de eigen huisstijl, met per geval een eigen uitleg (sessie verlopen / account nog in behandeling / bestand hoort bij een ander merk / bestand bestaat niet meer), een weg terug naar het portaal én naar de downloadspagina, en waar het helpt de contactgegevens. De HTTP-statuscodes (403/404) blijven ongewijzigd. Werkt via WordPress' eigen wp_die_handler-filter, maar uitsluitend voor onze eigen aanroepen — WordPress' en andere plugins' foutmeldingen houden hun normale afhandeling.
- **"Nieuw"-markering bij recent toegevoegde bestanden.** De FileBird-koppeling uit 1.50.0 laat bestanden vanzelf in het portaal verschijnen, maar aan de kant van de dealer veranderde er niets zichtbaars: je moest gáán kijken om te ontdekken dat er iets was. Bestanden van de afgelopen drie weken krijgen nu een label op de downloadspagina, en op de portaalstartpagina staat een aanklikbare regel "Er staan X nieuwe documenten voor u klaar". Die teller loopt via exact dezelfde zichtbaarheidscontrole als de downloadspagina zelf, zodat er nooit "3 nieuw" kan staan terwijl de dealer er door zijn merkrechten nul van te zien krijgt. Bewust een vast venster van drie weken en geen "sinds uw vorige bezoek"-teller: dat laatste klinkt aardiger maar telt bij elke tweede paginalading al naar nul.
- **Het wachten-op-goedkeuringsscherm is geen doodlopend eind meer.** Dat is letterlijk de eerste ervaring van een nieuwe dealer en zei alleen "nog niet goedgekeurd, neem contact op". Nu staat er wat er gebeurt, dat er automatisch bericht volgt, dat het doorgaans binnen één werkdag rond is, en waar je terechtkunt als het langer duurt.
- Nieuw onder Instellingen > Dealerportaal: een e-mailadres en telefoonnummer voor contact. Die worden getoond op de twee schermen hierboven waar een dealer niet verder kan. Blijven ze leeg, dan werken die schermen gewoon, alleen zonder contactregel.

## 1.51.0
- **Beveiliging: dealerbestanden waren zonder inloggen op te halen.** Een download is een gewone WordPress-bijlage en stond daarmee in de openbare uploadsmap; de inlogcontrole gold alleen voor de eigen URL (`?hdp_download=123`), niet voor het bestand zelf. Erger nog: `/wp-json/wp/v2/media` staat in WordPress standaard voor iedereen open en gaf de complete lijst mét directe bestands-URL's — een prijslijst was daarmee zonder account op te halen (op de testomgeving bevestigd). Opgelost in drie lagen: (1) downloadbestanden verhuizen naar `uploads/hdp-beveiligd/` met een `.htaccess` die directe toegang weigert, inclusief het opruimen van de JPG-voorbeeldpagina die WordPress van een PDF maakt; (2) `wp_get_attachment_url()` geeft voor zo'n bestand voortaan de eigen, gecontroleerde download-URL terug, zodat een gedeelde of uitgelekte link alsnog langs de inlog- en merkcontrole gaat — dit werkt op élke server; (3) de bijlagen zijn uit de openbare REST-lijst gehaald en een losse opvraag geeft geen bestands-URL, bestandsnaam of grootte meer prijs. Bestanden die er al stonden verhuizen eenmalig vanzelf bij het eerstvolgende bezoek aan wp-admin.
- Omdat laag (1) alleen werkt op servers die `.htaccess` lezen (Apache/LiteSpeed wel, nginx niet — en dat gebeurt stilzwijgend), controleert de plugin nu dagelijks zélf of die map werkelijk dichtstaat, door een testbestand via de browser op te vragen. Blijkt hij toch openbaar, dan volgt een duidelijke waarschuwing bovenaan wp-admin plus een regel in het logboek, in plaats van dat je ten onrechte denkt dat het goed zit.
- **Beveiliging: het merkfilter op de downloadspagina was puur cosmetisch.** Een bestand van een merk dat een dealer niet voert werd wel verborgen in de lijst, maar bleef gewoon op te halen door het nummer in de URL op te hogen (de ID's lopen netjes op). Een Väderstad-dealer kon zo bij de HARDI-prijslijst. De merkcontrole zit nu in het downloadendpoint zelf (403), in één gedeelde functie die de pagina én het endpoint gebruiken, zodat die twee niet meer uit elkaar kunnen lopen.
- Nieuw merk-optie "Algemeen" voor bestanden die voor iedereen met toegang tot het dealerportaal bedoeld zijn (algemene voorwaarden, huisstijl, formulieren). Die vallen bewust buiten de merkrechten-controle; voorheen was er geen manier om zoiets te taggen zonder dat het juist voor dealers mét merkrechten onzichtbaar werd. In FileBird verschijnen daarvoor automatisch de mappen "Algemeen (NL/FR/EN)".
- De downloadteller telt beheerders niet meer mee — die gaat over dealergebruik, en sinds beveiligde bestanden via het eigen endpoint getoond worden zou elk voorbeeld in de mediabibliotheek anders als download meetellen.
- Testset bewaakt nu ook dat de twee merkenlijsten niet uit elkaar lopen: een merk waarop je downloads kunt taggen (`HDP_Downloads_CPT::merk_opties()`, ook de bron van de FileBird-mapnamen) maar dat je niet aan een dealer kunt toewijzen (`HDP_Merken::lijst()`) levert bestanden op die voor élke dealer met merkrechten onzichtbaar zijn — zonder enige melding.

## 1.50.0
- Nieuw: bestanden die in FileBird binnen de hoofdmap "Downloads dealerportaal" in een submap met de naam "Merk (Taalcode)" worden gezet — bijv. "HARDI (NL)", "Väderstad (FR)", "Bogballe (EN)" — verschijnen voortaan automatisch als download op de downloadspagina onder dat merk/die regio, zonder dat daar met de hand een Download-bericht voor aangemaakt hoeft te worden. Een gelijknamige map die niet in "Downloads dealerportaal" staat (bijv. foto's voor elders op de site) wordt bewust genegeerd, zodat alleen bewust in die hoofdmap geplaatste bestanden ooit voor dealers zichtbaar worden. Werkt via FileBird's eigen "fbv_after_set_folder"/"fbv_after_assign_folder"-hooks (ook in de gratis versie aanwezig, geen periodieke controle nodig). Haalt het bestand automatisch weer weg (prullenbak) als het uit de merk-map wordt gehaald — een met de hand aangemaakte download blijft daarbij altijd met rust. Herkent "HARDI"/"Vaderstad" ook zonder hoofdletter- of accentgevoeligheid.
- Regio "Engels" toegevoegd als nieuwe, derde taaloptie naast Nederland/België/België (Frans) — voor downloads/content, los van de NL/FR-schermtaal van het portaal zelf (Engelstalige bestanden blijven zichtbaar voor iedereen, ongeacht schermtaal).
- (Zijvondst, nog niet gefixt op de live site: het testdealer-account bleek nog de oude merknaam-spelling "Vaderstad" i.p.v. "Väderstad" te hebben staan sinds de correctie in 1.43.0, waardoor Väderstad-downloads voor die dealer onterecht onzichtbaar bleven. Op de live site is niet gecontroleerd of andere dealers hetzelfde probleem hebben — zie CHANGELOG bij 1.43.0.)

## 1.49.4
- Drie CSS-plekken waar WooCommerce's eigen kernstijl (hogere specificiteit) stiekem van onze eigen thema-CSS won, gecorrigeerd: de padding en breedte van bestel-tabellen (o.a. de "Bekijk bestelling"-pagina), de kleur van het actieve tabblad op de productpagina (bleef grijs i.p.v. rood), en een ongewenste onderstreping op het actieve item in het "Mijn account"-menu.
- (Twee andere vermoede gevallen — de prijs op een productkaart, en de breedte van de bestellingenlijst zelf — bleken bij narekenen loos alarm: deze pagina's gebruiken hun eigen aangepaste opmaak zonder de HTML-structuur waar WooCommerce's regel op aangrijpt, dus daar was niets stuk.)
- Testset (`tests/`) uitgebreid met browserniveau-tests (Playwright, in `tests/e2e/`) naast de bestaande PHPUnit-tests: inloggen, winkelen → winkelwagen → afrekenen, snel bestellen, en een regressietest voor het mobiele merkenfilter van hierboven. Zie `tests/e2e/README.md`. Ook 3 verouderde PHPUnit-tests hersteld die nog de oude spelling "Vaderstad" i.p.v. "Väderstad" gebruikten (sinds 1.43.0) en daardoor onterecht faalden.

## 1.49.3
- Winkel op mobiel: het merkenfilter (14 aanvinkopties) stond standaard volledig opengeklapt bóven de zoekbalk, sortering en producten — een dealer op zijn telefoon moest daar eerst voorbij scrollen. De bijbehorende in-/uitklapstijl (pijltje, aanklikbare kop) bestond al in de CSS voor mobiel, maar werd nooit gebruikt omdat het filter hard "open" stond; dat staat nu standaard dicht op mobiel (desktop ongewijzigd).

## 1.49.2
- Downloads, Content, Bestellingen en het "wachten op goedkeuring"-scherm hadden nog een eigen "Uitloggen"-knop, dubbelop met het uitloggen dat al onder "Mijn account" in de header zit (ook op dat laatste scherm, bleek na naslag — de aanname dat het daar de enige uitlogweg was klopte niet meer sinds de headerredesign). Overal verwijderd.

## 1.49.1
- Winkelwagen: "Your cart is currently empty!"/"New in store" op een lege winkelwagen stonden nooit vertaald — bleek bevroren standaardtekst in de paginainhoud zelf, opgelost met dezelfde NL/FR-schakeltruc als "Overige informatie".
- Mijn account → Bestellingen: de WooCommerce-melding "Confirm your email address..." (bedoeld om gastbestellingen aan een account te koppelen) is verborgen i.p.v. vertaald — dit portaal kent geen gasten, alleen ingelogde dealers.
- Winkel: bij producten met een lange titel liep de tekst soms half over het artikelnummer eronder heen. Oorzaak: WooCommerce's eigen basisstijl voor productitels (grotere letter, wat opvulling) was specifieker dan onze eigen opmaak en won stiekem, waardoor de knip-hoogte voor "titel op 2 regels" niet meer klopte met de werkelijk getoonde tekstgrootte. Onze opmaak wint nu gegarandeerd.

## 1.49.0
- "Overige informatie" / merken-hub: de vier merktegels (Väderstad, Bogballe, Draincleaners, HARDI & Rabe) kunnen nu een eigen achtergrondfoto krijgen — via een "Foto kiezen"-knop in de bloktoolbar (gewone WordPress-mediabibliotheek), zonder dat daar code voor nodig is. Zolang er geen foto is gekozen tonen ze een neutraal streeppatroon als plaatshouder i.p.v. de oude vlakke kleur. De kop erboven is uitgebreid van één regel ("Onderdelen per merk") naar een label + titel ("Direct naar de onderdelen") + korte introtekst, en elke tegel heeft nu een ronde pijl-knop i.p.v. de tekstuele "→".

## 1.48.2
- Header: de "Links"-knop en de "Mijn account"-knop oogden niet bij elkaar horend (Links was één vlak rood vlak, Mijn account een tweekleurige splitknop). "Links" heeft nu dezelfde tweekleurige opmaak (lichter rood hoofddeel, donkerder rood pijltje-vak met scheidingslijn) als Mijn account.

## 1.48.1
- Header: favorietenknop staat nu helemaal links van de knoppenrij (vóór Links/Mijn account). Homburg Holland en Homburg Belgium zijn samengevoegd tot één rode "Links"-knop met een uitklapmenu, i.p.v. twee losse rode knoppen naast elkaar.
- "Overige informatie": de lopende-tekstkolom oogde los/zwevend zonder enig visueel houvast — elk punt kreeg een klein icoontje terug, een dunne scheidingslijn tussen de punten, en de linkjes eronder zijn nu lichte label-chips i.p.v. kale onderstreepte tekst met bullet-puntjes ertussen.

## 1.48.0
- Header: "Mijn account" is nu een splitknop — het rode knopdeel blijft een gewone link naar Mijn account, het pijltje ernaast klapt een menu open met Instellingen en Uitloggen (variant C uit de eerdere schetsen, met Homburg Holland/Belgium bewust rood gehouden i.p.v. omlijnd). Werkt zonder JS-framework (checkbox-hack, zelfde patroon als het instellingenpaneel), met een klein scriptje voor sluiten via Escape/klik-ernaast. Taal (NL/FR) blijft bewust los zichtbaar — die moet ook werken voor uitgelogde bezoekers.

## 1.47.2
- "Overige informatie": de eerste tekstkaart links (Bestellen en levertijden) trok een enorme, lege ruimte onder zich, waardoor Contact magazijn/(Technische) informatie er ver onder kwamen te staan. Oorzaak: de kaartversie van `.hdp-info-kaart` zet `height:100%` (bedoeld voor het oude raster met gelijke rijhoogtes) — dat bleef ook gelden voor de nieuwe "platte tekst"-stijl, en de linkerkolom werd bovendien door wp:columns even hoog gerekt als de merken-hub ernaast. Beide gecorrigeerd.

## 1.47.1
- Header: het icoontje op de nieuwe "Mijn account"-knop was onzichtbaar (HDP_Icons::svg_icoon() geeft zelf geen stroke-kleur mee, en de knop-CSS zette die ook niet) — nu zichtbaar.
- Hero: de hoekmarkeringen op de foto zijn eruit gehaald; het naamplaatje sluit nu links aan op dezelfde marge als de rest van de pagina (header/footer) i.p.v. een los vast pixelgetal.

## 1.47.0
Voorpagina (/dealerportaal/) grondig herzien, in twee onderdelen:
- **Hero:** de foto met losse witte "Welkom"-balk eronder vervangen door een "blauwdruk-plaat" — hoekmarkeringen op de foto en de welkomsttekst op een donker naamplaatje eroverheen, leunend op hetzelfde technisch-tekening-motief als de rasterplaceholder bij productfoto's. Geen aparte "Geautoriseerd voor: merken"-regel meer op deze plek (zie CHANGELOG-item hieronder als dat gemist wordt).
- **"Overige informatie":** het raster van zes gelijke kaarten vervangen door een tweeluik — links lopende tekst voor bestellen/levertijden, contact en technische informatie (nieuwe "Platte tekst"-stijlvariant op de bestaande info-kaart/contact-kaart-blokken), rechts een "merken-hub" met vier stevige tegels (Väderstad, Bogballe, Draincleaners, HARDI & Rabe) naar de onderdelenportalen. Nieuw blok `homburg/merken-tegel` hiervoor; de bijbehorende blokpatroon (voor eventuele toekomstige pagina's) is meeveranderd.

## 1.46.3
- Downloads: merk "Homburg Draincleaners" gecorrigeerd naar "Draincleaners" (keuzelijst én de bestaande download die al onder dat merk stond).

## 1.46.2
- Downloads: merk "RABE" gecorrigeerd naar "Rabe" (keuzelijst én de bestaande download die al onder dat merk stond).

## 1.46.1
- Duidelijke "Mijn account"-knop toegevoegd in de header (naast Homburg Holland/Belgium) — er was daarvoor geen zichtbare weg naar Mijn account, alleen het kleine "Instellingen"-tandwiel (dat een apart paneel opent, niet de accountpagina zelf).

## 1.46.0
Grote, samenhangende update naar aanleiding van een uiterlijk/UX/code-audit; hieronder gebundeld per onderwerp i.p.v. per tussenstap (zie ook de werkafspraak in CHANGELOG hierboven over versiebump-per-afgeronde-wijziging).

**Uiterlijk**
- Eén dashboardkaart ("Snel bestellen") kreeg meer visueel gewicht (donkere kaart, omgekeerde knop) i.p.v. zes identieke witte kaarten op een rij.
- Merken waarvoor een dealer geautoriseerd is tonen nu een logo i.p.v. platte tekst, zodra dat merk een logo heeft — nieuw instellingenscherm-onderdeel "Instellingen > Dealerportaal > Merklogo's" om per merk een logo te uploaden (valt terug op de tekstbadge zolang er geen logo is ingesteld).

**Gebruiksvriendelijkheid**
- De twee losse "bekijk je bestellingen"-schermen (het kale WooCommerce "Bestellingen"-tabblad en de uitgebreidere Bestelgeschiedenis-pagina) zijn samengevoegd: "Mijn account" > "Bestellingen" toont voortaan de doorzoekbare, filterbare lijst. De losse pagina /bestelgeschiedenis/ verwijst nu (301) door naar de nieuwe locatie.
- WooCommerce's eigen "Downloads"-tabblad in Mijn account (dat over digitale productdownloads gaat, iets wat Homburg niet verkoopt, en dus altijd leeg was) is uit het menu gehaald; de "Downloads"-kaart op het dashboard linkt nu naar de échte downloadpagina.
- "Snel bestellen" kreeg een zoekveld met live suggesties (hergebruikt dezelfde zoekfunctie als de winkelpagina) om een artikel op te zoeken en toe te voegen zonder het nummer te hoeven weten — de textarea voor het plakken van een lijst blijft gewoon werken zoals voorheen.
- Consistente "hier staat nog niets"-status (icoon + tekst + eventueel een knop) op downloads, favorieten, bestellingen en het dashboard, i.p.v. overal losse cursieve tekstregels.

**Techniek/onderhoud**
- Eén globale `box-sizing: border-box`-reset i.p.v. een reset die alleen een paar losse wrapperklassen dekte (de oorzaak van de over-de-kaart-heen-stekende knoppen eerder deze week).
- De utility-klasse `.hdp-btn-klein` deed er per ongeluk `width:100%` bij (bedoeld voor de herbestelkaartjes) — nu een eerlijk gescheiden `.hdp-btn-vol` voor waar dat écht gewenst is.
- Herhaalde `70%`/`15%`-inhoudsbreedtes op zes plekken vervangen door dezelfde bron (`--wp--style--global--content-size` uit theme.json) i.p.v. los van elkaar te kunnen gaan afwijken.
- De thema-eigen `--hdp-wc-radius`/`--hdp-wc-schaduw`-tokens waren een losse kopie van de plugin's `--hdp-radius`/`--hdp-schaduw` met dezelfde waarde; nu een echte alias (en de plugin-stylesheet is een gegarandeerde afhankelijkheid geworden van de thema-WooCommerce-stylesheet, i.p.v. toevallig al geladen te zijn).
- `wp-content/duplicator-backups/` (volledige sitebackups, tot 50+ MB) stond niet in `.gitignore` — nu wel.

## 1.45.2
- Dashboard-kaarten: de knoppen ("Naar bestellingen" e.d.) staken over de rand van hun kaart uit. Oorzaak: `.hdp-btn` had geen `box-sizing: border-box`, dus de eigen binnenruimte (padding) van de knop kwam er bovenop de breedte van 100% bij — daarmee brak de knop net buiten de kaart. Nu overal gecorrigeerd, ook voor eventuele toekomstige volle-breedte-knoppen.
- "Mijn account" navigatie/inhoud liep in float-hoogte uit elkaar zodra de inhoud langer werd dan de navigatie (goed zichtbaar op Dashboard/Bestellingen, toevallig niet op de korte Adressen-pagina) — omgezet naar een flex-indeling zodat beide kolommen altijd even hoog blijven, met een vaste breedte voor de navigatie i.p.v. een percentage.
- "Mijn account" kreeg een paginatitel ("Mijn account") met scheidingslijn boven de navigatie/inhoud, i.p.v. daar direct mee te beginnen.

## 1.45.1
- De vorige "Mijn account"-breedtefix (1.45.0) werkte in de praktijk niet: `wp:post-content` kreeg zelf een "constrained"-layout mee, waardoor WordPress' eigen generieke regel de kale `<div class="woocommerce">`-inhoud daarbinnen alsnog naar de smalle 70%-tekstbreedte terugbracht — het "align: wide"-attribuut kwam daardoor nooit tot zijn recht. Nu krijgt `.woocommerce` de breedte rechtstreeks via een eigen regel (gelijk aan de 70%-inhoudsbreedte van header/hero/kaarten elders op de site, dus nu wél gelijk uitgelijnd), en is de tekst-layout van post-content ongemoeid gelaten (zelfde aanpak als page-checkout.html).
- Knoppen als "Bewerken"/"Toevoegen" (adresrijen) en "Bekijken" (recente bestellingen, ook op de bestaande bestelgeschiedenispagina) rekten zich uit over de volle rijbreedte i.p.v. netjes rechts uit te lijnen — kwam door de utility-class `.hdp-btn-klein`, die elders bewust `width:100%` gebruikt (de herbestelkaartjes) maar hier per ongeluk meeliftte. Expliciet gecorrigeerd voor beide plekken.

## 1.45.0
- "Mijn account" > Adressen: WooCommerce's kale, ongestylede twee-koloms-indeling vervangen door een rustige lijst met rijen (icoon, adres, Bewerken/Toevoegen-knop) — zelfde opzet als "Recente bestellingen" op het dashboard.
- De navigatie links in "Mijn account" (Dashboard/Bestellingen/.../Uitloggen) stond los op de grijze pagina-achtergrond; staat nu in een witte kaart, net als de rest van de lijsten op deze pagina's.
- "Mijn account" gebruikte overal maar zo'n 30% van de paginabreedte (de standaard, voor tekstpagina's bedoelde 70%-kolom, mét de klassieke 30/70-navigatie-indeling daarbinnen) — nu net als de afrekenpagina op de bredere 90%-kolom gezet.
- Plugin kreeg er twee iconen bij (bewerken, toevoegen) t.b.v. de nieuwe adresrijen.

## 1.44.1
- Footer: telefoonnummer Homburg Belgium gecorrigeerd naar +32 (0)15 55 98 35.

## 1.44.0
- "Mijn account"-dashboard (het startscherm na inloggen op /my-account/) vervangen: i.p.v. de kale standaard-WooCommerce-tekst nu een welkomstregel, kaarten met snelkoppelingen naar Bestellingen/Snel bestellen/Mijn favorieten/Downloads/Adressen/Accountdetails, en een voorproefje van de 3 meest recente bestellingen — in dezelfde stijl als de rest van het dealerportaal. Thema-wijziging (`woocommerce/myaccount/dashboard.php` + `assets/css/woocommerce.css`), plugin kreeg er twee iconen (hart, adres) bij t.b.v. deze kaarten.

## 1.43.0
- Downloads voor dealers: het vrije tekstveld "Merk" vervangen door een vaste keuzelijst (Algemeen, Homburg Draincleaners, HARDI, Väderstad, Bogballe, RABE, Tefen) — op de bewerkpagina, in het front-end uploadformulier en bij bulk-bewerken.
- Spelling "Väderstad" gelijkgetrokken (met puntjes) in de dealer-merkenlijst, zodat die straks 1-op-1 matcht met de merknaam uit PowerAll.
- Winkelpagina: de werkbalk boven de productgrid kreeg een lichte, rustige opmaak (witte balk i.p.v. donker, zoekveld met icoon, gevulde rode actieve weergave-knop) i.p.v. het donkere contrastvlak.
- Winkelpagina: overbodige "Filter toepassen"-knop bij het merkfilter verwijderd (vinkjes filteren al automatisch).

## 1.42.0
Grote update aan de webshop-kant van het dealerportaal (WooCommerce-integratie in het thema) plus wat plugin-fixes; hieronder de belangrijkste, gebundeld per onderwerp.

**Winkelpagina**
- Zoeken op naam/artikelnummer met live suggesties terwijl je typt, filteren op merk met verwijderbare "chips" boven de resultaten, en een raster/lijst-weergavekeuze (onthouden per bezoeker).
- Alles gebundeld in één donkere Homburg-balk (zoeken, weergave, resultaattelling, sortering) i.p.v. losse regels.
- Paginering, merkfilter en de productkaart zelf visueel verbeterd (en een aantal WooCommerce-eigenaardigheden rechtgezet die de Homburg-huisstijl overschreven, zoals de standaard-paarse paginering en dubbele/ongelijke knopbreedtes in de lijstweergave).

**Favorieten en snel bestellen**
- Persoonlijke favorietenlijst: een hartje op elke productkaart en de productpagina, een hartje-icoon met aantal in de header, en een eigen opgeschoonde "Mijn favorieten"-pagina (zonder het volledige account-menu ernaast).
- "Snel bestellen" (nieuw tabblad onder Mijn account): een lijst artikelnummers in één keer plakken/intypen (optioneel met aantal, bijv. "10714 x3") om in één keer aan de winkelmand toe te voegen.
- "Opnieuw bestellen" nu ook in het bestellingenoverzicht zelf (niet alleen op de losse orderpagina), en bruikbaar bij "in behandeling"/"on hold" i.p.v. alleen "voltooid".

**Header en overige pagina's**
- Winkelmandje-icoon met live aantal in de header.
- Instellingen + Uitloggen verplaatst van de homepage naar de header (gestapeld, naast winkelmandje/favorieten) — overal op de site bereikbaar.
- "Terug naar de winkel"-knop op winkelmand, afrekenen en de productpagina.
- Coupons volledig uitgezet (niet van toepassing bij Homburg).
- Sticky footer: blijft altijd onderaan het scherm i.p.v. omhoog te kruipen op een korte pagina.
- Homepage: hero loopt vloeiend over in de welkomstsectie i.p.v. een brede lege overgangsstrook.
- "Naar de webshop"-kaart opent niet langer geforceerd in een nieuw tabblad (de configurator, die wél extern is, blijft dat gewoon doen).

## 1.41.0
- Opmaakknoppen per kaart: `portaal-kaart`, `info-kaart` en `contact-kaart` ondersteunen nu de standaard WordPress-instellingen voor **achtergrond-/tekstkleur, binnenmarge (padding), buitenmarge en rand/hoekafronding** — per losse kaart in te stellen via het zijpaneel, zonder CSS. De `render.php` van deze blokken zet nu `get_block_wrapper_attributes()` op de wrapper zodat die instellingen ook op de front-end doorkomen. Zonder instellingen ziet de kaart er exact hetzelfde uit als voorheen.
- Editor-accentkleur naar Homburg-rood (`HDP_Editor`): de "+"-knop, blok-selectierand, primaire knoppen, focusringen en de gemarkeerde regel in de lijstweergave zijn nu rood i.p.v. WordPress-blauw. Alleen admin-CSS (editor-chrome + iframe-canvas), raakt de front-end niet.

## 1.40.0
- `homburg/portaal-kaart` en `homburg/info-kaart` gebruiken nu `useBlockProps()` op hun wrapper in de editor. Daardoor is de hele kaart één klikbare blokgrens: klik = blok geselecteerd, Backspace/Delete verwijdert 'm, de blok-toolbar hangt aan de kaart. Voorheen landde een klik in het tekstveld en was het blok alleen via de lijstweergave te pakken.
- Nieuw blok `homburg/contact-kaart`: als de infokaart, maar met losse velden **E-mail** en **Telefoon** die automatisch `mailto:` / `tel:`-knoppen worden — geen handmatige links meer typen.
- Nieuw pattern `homburg/overige-informatie`: de volledige "Overige informatie"-sectie (kop + raster met alle infokaarten en de contactkaart), ineens te plaatsen.

## 1.39.0
- Foutmonitoring (`HDP_Log`): fatale PHP-fouten en niet-afgevangen excepties die in de plugincode ontstaan worden weggeschreven naar `wp-content/uploads/hdp-logs/hdp-JJJJ-MM.log` (afgeschermde map, per maand). Bij een fatale fout gaat er ook een gethrottelde e-mail naar `marketing@homburg-holland.com`. Plugincode kan zelf loggen via `HDP_Log::schrijf()`. Bekijken/wissen via Instellingen > Dealerportaal foutenlog. Fouten van WordPress-core of andere plugins worden genegeerd (geen ruis).

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
