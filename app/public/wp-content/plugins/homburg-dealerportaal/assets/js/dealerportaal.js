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

		// Tegengehouden door de controle hieronder: dan gaat er niets weg en
		// hoort de knop gewoon bruikbaar te blijven.
		if ( e.defaultPrevented ) {
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
 * Controle vóór het versturen bij de garantieformulieren.
 *
 * Wat de server afkeurde kwam pas na een volledige retour terug, en dan
 * waren de gekozen foto's weg — een browser laat een bestandsveld niet door
 * een website vullen. Bij een te grote verzending kwam de dealer zelfs
 * zonder melding terug op een leeg formulier. Dus dezelfde regels hier,
 * vóór er iets weggaat. De server controleert daarna gewoon nog een keer.
 *
 * De grenzen en de teksten staan op het formulier zelf (data-attributen,
 * zie HDP_Garantie_Render::controle_attributen()), zodat ze in de juiste
 * taal zijn en niet twee keer bijgehouden worden.
 *
 * In de capture-fase, zodat dit vóór het uitschakelen van de knop
 * hierboven draait.
 */
( function () {
	document.addEventListener( 'submit', function ( e ) {
		var form = e.target;
		if ( ! ( form instanceof HTMLFormElement ) || ! form.hasAttribute( 'data-hdp-controle' ) ) {
			return;
		}

		var melding = fout( form );
		var vak = form.querySelector( '.hdp-js-melding' );

		if ( ! melding ) {
			if ( vak ) {
				vak.remove();
			}
			return;
		}

		e.preventDefault();

		if ( ! vak ) {
			vak = document.createElement( 'p' );
			vak.className = 'hdp-garantie-storing hdp-js-melding';
			vak.setAttribute( 'role', 'alert' );
			form.insertBefore( vak, form.firstChild );
		}
		vak.textContent = melding;
		vak.scrollIntoView( { behavior: 'smooth', block: 'center' } );
	}, true );

	function fout( form ) {
		var d = form.dataset;
		var bestanden = [];
		form.querySelectorAll( 'input[type="file"]' ).forEach( function ( veld ) {
			Array.prototype.forEach.call( veld.files || [], function ( b ) { bestanden.push( b ); } );
		} );

		if ( bestanden.length > Number( d.maxAantal ) ) {
			return d.meldingAantal;
		}

		var totaal = 0;
		for ( var i = 0; i < bestanden.length; i++ ) {
			if ( bestanden[ i ].size > Number( d.maxBestand ) ) {
				return d.meldingBestand.replace( '%s', bestanden[ i ].name );
			}
			totaal += bestanden[ i ].size;
		}
		if ( totaal > Number( d.maxTotaal ) ) {
			return d.meldingTotaal;
		}

		// De onderdelenregels: alleen de regels waar een nummer in staat
		// tellen, net als op de server.
		var regels = form.querySelectorAll( '.hdp-claimregel:not(.hdp-claimregel-kop)' );
		var derden = false;
		for ( var r = 0; r < regels.length; r++ ) {
			var nummer = regels[ r ].querySelector( '[name="onderdeel_nummer[]"]' );
			if ( ! nummer || '' === nummer.value.trim() ) {
				continue;
			}
			var uren = regels[ r ].querySelector( '[name="onderdeel_uren[]"]' );
			if ( uren && '' === uren.value.trim() ) {
				return d.meldingUren;
			}
			var herkomst = regels[ r ].querySelector( '[name="onderdeel_herkomst[]"]' );
			if ( herkomst && 'derden' === herkomst.value ) {
				derden = true;
			}
		}
		if ( derden && ! bestanden.length ) {
			return d.meldingFactuur;
		}

		return '';
	}
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

/**
 * De zoekbalk in de header (zie HDP_Zoeken).
 *
 * Toont al tijdens het typen per soort een paar treffers: artikelen,
 * bestellingen, claims, machines en documenten. Pijltjes omhoog/omlaag
 * lopen door de treffers, Enter opent de gekozen (of de eerste), Escape
 * sluit. "/" springt vanaf elke pagina naar het veld. Op een telefoon is
 * het eerst alleen een vergrootglas; dat klapt het veld uit.
 */
( function () {
	var balk = document.querySelector( '[data-hdp-zoekbalk]' );
	var gegevens = window.HDP_PORTAAL_ZOEKEN;
	if ( ! balk || ! gegevens ) {
		return;
	}

	var form = balk.querySelector( '.hdp-zoekbalk-form' );
	var veld = balk.querySelector( '.hdp-zoekbalk-veld' );
	var paneel = balk.querySelector( '.hdp-zoekbalk-paneel' );
	var openKnop = balk.querySelector( '.hdp-zoekbalk-open' );
	var teksten = gegevens.teksten || {};
	var timer = null;
	var lopend = null;
	var actief = -1;
	var laatsteTerm = '';

	function treffers() {
		return paneel.querySelectorAll( '.hdp-zoekbalk-item' );
	}

	function sluiten() {
		paneel.hidden = true;
		paneel.innerHTML = '';
		veld.setAttribute( 'aria-expanded', 'false' );
		veld.removeAttribute( 'aria-activedescendant' );
		actief = -1;
	}

	function melding( tekst ) {
		paneel.innerHTML = '';
		var p = document.createElement( 'p' );
		p.className = 'hdp-zoekbalk-melding';
		p.textContent = tekst;
		paneel.appendChild( p );
		paneel.hidden = false;
		veld.setAttribute( 'aria-expanded', 'true' );
	}

	function link( item, id ) {
		var a = document.createElement( 'a' );
		a.className = 'hdp-zoekbalk-item';
		a.href = item.url;
		a.id = id;
		a.setAttribute( 'role', 'option' );
		if ( item.nieuwe_tab ) {
			a.target = '_blank';
			a.rel = 'noopener';
			a.setAttribute( 'aria-description', teksten.nieuw || '' );
		}
		var titel = document.createElement( 'span' );
		titel.className = 'hdp-zoekbalk-item-titel';
		titel.textContent = item.titel;
		a.appendChild( titel );
		if ( item.sub ) {
			var sub = document.createElement( 'span' );
			sub.className = 'hdp-zoekbalk-item-sub';
			sub.textContent = item.sub;
			a.appendChild( sub );
		}
		return a;
	}

	function tonen( data ) {
		paneel.innerHTML = '';
		actief = -1;
		var teller = 0;

		( data.groepen || [] ).forEach( function ( groep ) {
			var blok = document.createElement( 'div' );
			blok.className = 'hdp-zoekbalk-groep';
			blok.setAttribute( 'role', 'group' );
			var kop = document.createElement( 'p' );
			kop.className = 'hdp-zoekbalk-groep-kop';
			kop.id = 'hdp-zoekbalk-kop-' + groep.sleutel;
			kop.textContent = groep.titel;
			blok.setAttribute( 'aria-labelledby', kop.id );
			blok.appendChild( kop );
			groep.items.forEach( function ( item ) {
				blok.appendChild( link( item, 'hdp-zoekbalk-item-' + teller++ ) );
			} );
			paneel.appendChild( blok );
		} );

		if ( ! teller ) {
			var p = document.createElement( 'p' );
			p.className = 'hdp-zoekbalk-melding';
			p.textContent = teksten.niets || '';
			paneel.appendChild( p );
		}

		if ( data.meer ) {
			var meer = link( { url: data.meer.url, titel: data.meer.tekst }, 'hdp-zoekbalk-item-' + teller++ );
			meer.classList.add( 'hdp-zoekbalk-meer' );
			paneel.appendChild( meer );
		}

		paneel.hidden = false;
		veld.setAttribute( 'aria-expanded', 'true' );
	}

	function zoeken( term ) {
		if ( lopend ) {
			lopend.abort();
		}
		lopend = window.AbortController ? new AbortController() : null;
		melding( teksten.laden || '' );

		var url = gegevens.ajaxUrl + '?action=hdp_portaal_zoeken&nonce=' + encodeURIComponent( gegevens.nonce ) + '&q=' + encodeURIComponent( term );
		fetch( url, { credentials: 'same-origin', signal: lopend ? lopend.signal : undefined } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( antwoord ) {
				if ( veld.value.trim() !== term ) {
					return;
				}
				if ( antwoord && antwoord.success ) {
					tonen( antwoord.data );
				} else {
					melding( teksten.fout || '' );
				}
			} )
			.catch( function ( fout ) {
				if ( fout && 'AbortError' === fout.name ) {
					return;
				}
				melding( teksten.fout || '' );
			} );
	}

	function markeer( index ) {
		var lijst = treffers();
		if ( ! lijst.length ) {
			return;
		}
		actief = ( index + lijst.length ) % lijst.length;
		lijst.forEach( function ( a, i ) {
			a.classList.toggle( 'is-actief', i === actief );
			a.setAttribute( 'aria-selected', i === actief ? 'true' : 'false' );
		} );
		veld.setAttribute( 'aria-activedescendant', lijst[ actief ].id );
		lijst[ actief ].scrollIntoView( { block: 'nearest' } );
	}

	veld.addEventListener( 'input', function () {
		var term = veld.value.trim();
		clearTimeout( timer );
		if ( term.length < 2 ) {
			laatsteTerm = '';
			sluiten();
			return;
		}
		timer = setTimeout( function () {
			laatsteTerm = term;
			zoeken( term );
		}, 220 );
	} );

	veld.addEventListener( 'focus', function () {
		var term = veld.value.trim();
		if ( term.length >= 2 && paneel.hidden ) {
			zoeken( term );
		}
	} );

	veld.addEventListener( 'keydown', function ( e ) {
		if ( 'ArrowDown' === e.key ) {
			e.preventDefault();
			markeer( actief + 1 );
		} else if ( 'ArrowUp' === e.key ) {
			e.preventDefault();
			markeer( actief - 1 );
		} else if ( 'Escape' === e.key ) {
			if ( ! paneel.hidden ) {
				sluiten();
			} else {
				veld.blur();
				balk.classList.remove( 'is-open' );
				openKnop.setAttribute( 'aria-expanded', 'false' );
			}
		}
	} );

	// Enter: de gekozen treffer, of anders de eerste. Zijn er (nog) geen
	// treffers, dan gewoon de webshop doorzoeken zoals zonder script.
	form.addEventListener( 'submit', function ( e ) {
		var lijst = treffers();
		var doel = lijst[ actief >= 0 ? actief : 0 ];
		if ( doel && veld.value.trim() === laatsteTerm ) {
			e.preventDefault();
			doel.click();
		}
	} );

	document.addEventListener( 'click', function ( e ) {
		if ( ! balk.contains( e.target ) ) {
			sluiten();
			balk.classList.remove( 'is-open' );
			openKnop.setAttribute( 'aria-expanded', 'false' );
		}
	} );

	// "/" vanaf elke pagina, behalve als je al ergens aan het typen bent.
	document.addEventListener( 'keydown', function ( e ) {
		if ( '/' !== e.key || e.ctrlKey || e.metaKey || e.altKey ) {
			return;
		}
		var doel = e.target;
		if ( doel && ( doel.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test( doel.tagName ) ) ) {
			return;
		}
		e.preventDefault();
		balk.classList.add( 'is-open' );
		openKnop.setAttribute( 'aria-expanded', 'true' );
		veld.focus();
	} );

	openKnop.addEventListener( 'click', function () {
		var open = balk.classList.toggle( 'is-open' );
		openKnop.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		if ( open ) {
			veld.focus();
		} else {
			sluiten();
		}
	} );
} )();
