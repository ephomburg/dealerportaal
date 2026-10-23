/**
 * Eén klein, gedeeld scriptje voor alle formulieren in het dealerportaal
 * (login, instellingen, downloads-/bestelgeschiedenis-paginering-links via
 * hun eigen inline script, adminportaal-upload en -gebruikersoverzicht):
 * zet de verzendknop op "bezig" zodra een formulier wordt ingediend, zodat
 * een dealer/beheerder ziet dat er iets gebeurt tijdens de server-round-trip
 * (dit portaal doet bewust geen AJAX — elke actie is een gewone
 * paginaherlaad) en niet twee keer op "Opslaan" klikt.
 *
 * Eén gedelegeerde listener i.p.v. per formulier een losse handler, zodat
 * dit ook werkt voor formulieren die pas na page-load in de DOM komen
 * (bijv. het instellingenpaneel, dat door CSS in- en uitgeklapt wordt maar
 * altijd al in de HTML staat — dus dat is hier niet eens strikt nodig, maar
 * scheelt wel een aparte listener per formulier).
 */
( function () {
	document.addEventListener( 'submit', function ( e ) {
		var form = e.target;
		if ( ! ( form instanceof HTMLFormElement ) ) {
			return;
		}
		if ( ! form.closest( '.hdp-portaal, .hdp-login-sectie, .hdp-admin-sectie, .hdp-instellingen-kaart' ) ) {
			return;
		}

		var knop = form.querySelector( 'button[type="submit"], input[type="submit"]' );
		if ( ! knop || knop.disabled ) {
			return;
		}

		// Pas ná dit event uitschakelen. Een verzendknop die tijdens het
		// submit-event op disabled gezet wordt, telt niet meer mee als
		// verzender en valt daarmee uit de verzonden gegevens weg — een
		// knop met een eigen name/value stuurt die dan niet mee. Alle
		// formulieren hier droegen hun gegevens in verborgen velden, dus
		// dat viel lang niet op.
		window.setTimeout( function () {
			knop.disabled = true;
			knop.classList.add( 'hdp-btn-bezig' );
		}, 0 );
	} );
} )();

/**
 * De mededeling bij een onderdelenclaim ("stuur het onderdeel naar
 * Stiens") hoort te verdwijnen zodra de dealer "niet ontvangen" kiest: dan
 * is er niets om terug te sturen.
 *
 * Staat hier en niet als inline-script bij het blok zelf: het blok staat
 * boven het formulier, dus op dat moment bestaat de keuzelijst nog niet en
 * vond het script er niets.
 */
( function () {
	var blok = document.getElementById( 'hdp-retour-attentie' );
	if ( ! blok ) {
		return;
	}

	var keuze = document.getElementById( 'hdp-claim-onderdeel_probleem' );
	if ( ! keuze ) {
		// Op het ticketscherm staat hetzelfde blok zonder keuzelijst; daar
		// is er niets te verbergen.
		return;
	}

	function bijwerken() {
		blok.hidden = keuze.value === blok.dataset.zonderOnderdeel;
	}

	keuze.addEventListener( 'change', bijwerken );
	bijwerken();
} )();

/**
 * Geeft de hoogte van de header door als --hdp-header-hoogte.
 *
 * De header blijft bij het scrollen bovenaan staan. Kolommen die zélf
 * sticky zijn (de filterkolom bij garantie, de zijkolom bij een ticket)
 * zouden daar zonder dit getal onder verdwijnen. De hoogte verschilt per
 * schermbreedte — op een telefoon wrappen de knoppen naar een tweede
 * regel — dus hem hier opmeten in plaats van ergens een vast getal in de
 * CSS te zetten dat stilletjes niet meer klopt.
 */
( function () {
	var header = document.querySelector( '.hdp-site-header' );
	if ( ! header ) {
		return;
	}

	function meten() {
		document.documentElement.style.setProperty(
			'--hdp-header-hoogte',
			header.getBoundingClientRect().height + 'px'
		);
	}

	meten();

	if ( window.ResizeObserver ) {
		new window.ResizeObserver( meten ).observe( header );
	} else {
		window.addEventListener( 'resize', meten );
	}
} )();
