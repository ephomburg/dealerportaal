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

		knop.disabled = true;
		knop.classList.add( 'hdp-btn-bezig' );
	} );
} )();
