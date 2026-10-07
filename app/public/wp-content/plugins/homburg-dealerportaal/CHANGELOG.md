# Changelog

Noemenswaardige wijzigingen aan deze plugin, per versie (nieuwste eerst).
Versienummers volgen geen strikte SemVer-"breaking changes"-betekenis — in
dit project krijgt vrijwel elke afgeronde wijziging een eigen versiebump
(het derde cijfer voor kleine fixes/opschoning, de andere voor nieuwe
functionaliteit), zodat elke commit die de plugin-header raakt precies één
duidelijk afgebakende wijziging vertegenwoordigt. Zie ook `git log --
homburg-dealerportaal.php` voor de onderliggende commits.

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
