<?php

/**
 * Test het garantieportaal. De claims komen nu nog uit voorbeelddata; wat
 * hier getest wordt zijn de afspraken die straks óók moeten gelden als de
 * echte claims uit de claimadministratie komen: de statuslijst, de
 * veldlijst, en dat de pagina alleen voor goedgekeurde dealers opengaat.
 *
 * @group hdp-garantie
 */
class Garantie_Test extends WP_UnitTestCase {

	private $attributen;

	public function setUp(): void {
		parent::setUp();
		HDP_Roles::activate();

		$this->attributen = array(
			'heroAfbeelding' => 'https://example.test/hero.jpg',
			'titel'          => 'Garantie',
			'titelFr'        => 'Garantie',
			'omschrijving'   => 'Dien hier een garantieclaim in.',
			'omschrijvingFr' => 'Introduisez ici une demande de garantie.',
			'loginIntro'     => 'Log in met uw dealeraccount.',
			'loginIntroFr'   => 'Connectez-vous avec votre compte concessionnaire.',
		);
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		unset( $_COOKIE[ HDP_I18N::COOKIE ] );
		parent::tearDown();
	}

	private function als_goedgekeurde_dealer() {
		$id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $id, 'hdp_goedgekeurd', '1' );
		wp_set_current_user( $id );
		return $id;
	}

	/**
	 * Garantieclaims zijn bedrijfsgevoelig; de pagina hoort dicht te zitten
	 * voor wie niet is ingelogd of nog op goedkeuring wacht. Zelfde regel
	 * als bij downloads.
	 */
	public function test_pagina_is_dicht_voor_bezoekers_zonder_portaaltoegang() {
		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringNotContainsString( 'hdp-garantie-lijst', $html );
		$this->assertStringContainsString( 'hdp-login', $html );
	}

	public function test_pagina_is_dicht_voor_dealer_die_nog_op_goedkeuring_wacht() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'dealer' ) ) );

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringNotContainsString( 'hdp-garantie-lijst', $html );
	}

	/**
	 * Regressietest: het inlogscherm las blind $a['loginIntro'], maar de
	 * downloads-, content- en garantiepagina definiëren dat attribuut niet
	 * — een uitgelogde bezoeker kreeg daar dus een PHP-waarschuwing. Het
	 * scherm hoort gewoon zonder intro te verschijnen.
	 */
	public function test_inlogscherm_werkt_ook_zonder_inlogtekst_attributen() {
		$zonder = $this->attributen;
		unset( $zonder['loginIntro'], $zonder['loginIntroFr'] );

		$html = HDP_Garantie_Render::render_garantie_pagina( $zonder );

		$this->assertStringContainsString( 'hdp-login', $html );
	}

	public function test_goedgekeurde_dealer_ziet_het_overzicht() {
		$this->als_goedgekeurde_dealer();

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringContainsString( 'hdp-garantie-ingangen', $html );
		$this->assertStringContainsString( 'hdp-garantie-lijst', $html );
		$this->assertStringContainsString( 'hdp-machines', $html );
	}

	/**
	 * Het formulier hing eerst open onder de claimlijst; daar las het als een
	 * naschrift in plaats van als een taak, en met twee formulieren (machine
	 * aanmelden en claim indienen) was dat helemaal niet vol te houden.
	 */
	public function test_overzicht_toont_geen_formulier_meer() {
		$this->als_goedgekeurde_dealer();

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringNotContainsString( 'hdp-garantie-velden', $html );
	}

	public function test_beide_formulieren_hebben_een_eigen_scherm() {
		$this->als_goedgekeurde_dealer();

		foreach ( array( 'machine', 'claim' ) as $soort ) {
			$_GET['nieuw'] = $soort;
			$html          = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );
			unset( $_GET['nieuw'] );

			$this->assertStringContainsString( 'hdp-garantie-velden', $html, "Formulier $soort ontbreekt." );
			$this->assertStringNotContainsString( 'hdp-garantie-lijst', $html, "Formulier $soort hoort niet naast de claimlijst te staan." );
			$this->assertStringNotContainsString( 'hdp-machines', $html, "Formulier $soort hoort niet naast de machinelijst te staan." );
		}
	}

	/**
	 * Een claim gaat over een aangemelde machine. Die kiezen scheelt het
	 * overtypen van serienummer en aankoopdatum - precies de velden waarop
	 * de fabrikant controleert.
	 */
	public function test_claimformulier_vraagt_niet_wat_de_machine_al_weet() {
		$this->als_goedgekeurde_dealer();

		$_GET['nieuw'] = 'claim';
		$html          = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );
		unset( $_GET['nieuw'] );

		$this->assertStringContainsString( 'hdp-claim-machinekeuze', $html );
		$this->assertStringNotContainsString( 'hdp-claim-serienummer', $html );
		$this->assertStringNotContainsString( 'hdp-claim-aankoopdatum', $html );

		// Het aantal hectares vraagt hij wel: dat is de stand bij de klacht.
		$this->assertStringContainsString( 'hdp-claim-hectares', $html );
	}

	public function test_machinekeuze_toont_alle_aangemelde_machines() {
		$this->als_goedgekeurde_dealer();

		$_GET['nieuw'] = 'claim';
		$html          = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );
		unset( $_GET['nieuw'] );

		foreach ( HDP_Garantie::voorbeeldmachines() as $machine ) {
			$this->assertStringContainsString(
				esc_html( HDP_Garantie::machinenaam( $machine['machine'], $machine['serienummer'] ) ),
				$html
			);
		}
	}

	/** Een dealer heeft vaak meerdere machines van hetzelfde type staan. */
	public function test_serienummer_staat_in_de_titels() {
		$this->als_goedgekeurde_dealer();

		$overzicht = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );
		$this->assertStringContainsString( 'HARDI Navigator 4000 · HN4-220718-0391', $overzicht );

		$_GET['ticket'] = 'GAR-2026-0184';
		$ticket         = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );
		unset( $_GET['ticket'] );
		$this->assertStringContainsString( 'HARDI Navigator 4000 · HN4-220718-0391', $ticket );
	}

	/** Het overzicht toont wat loopt; afgehandelde claims vragen niets meer. */
	public function test_overzicht_toont_alleen_lopende_claims() {
		$this->als_goedgekeurde_dealer();

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringContainsString( 'GAR-2026-0184', $html );
		$this->assertStringContainsString( 'GAR-2026-0177', $html );
		$this->assertStringNotContainsString( 'GAR-2026-0169', $html );
		$this->assertStringNotContainsString( 'GAR-2026-0151', $html );
	}

	public function test_wat_op_de_dealer_wacht_staat_boven_de_lijsten() {
		$this->als_goedgekeurde_dealer();

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringContainsString( 'hdp-garantie-aandacht', $html );
		$this->assertLessThan(
			strpos( $html, 'hdp-garantie-lijst' ),
			strpos( $html, 'hdp-garantie-aandacht' ),
			'De aandachtsbalk hoort voor de claimlijst te staan.'
		);
	}

	/* --- Zoeken -------------------------------------------------------- */

	public function test_overzicht_heeft_een_zoekveld() {
		$this->als_goedgekeurde_dealer();

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringContainsString( 'hdp-garantie-zoeken', $html );
		$this->assertStringContainsString( 'id="hdp-garantie-zoeken"', $html );
	}

	/**
	 * Een dealer zoekt op wat hij in zijn hoofd heeft: een serienummer, een
	 * machinenaam, een claimnummer. Elk van die dingen moet een treffer
	 * geven, anders is het veld een loze belofte.
	 */
	public function test_er_kan_op_serienummer_machinenaam_en_claimnummer_gezocht_worden() {
		$this->als_goedgekeurde_dealer();

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		preg_match_all( '/data-zoek="([^"]*)"/', $html, $treffers );
		$zoekteksten = implode( ' | ', $treffers[1] );

		$this->assertStringContainsString( 'hn4-220718-0391', $zoekteksten, 'Zoeken op serienummer moet werken.' );
		$this->assertStringContainsString( 'hardi navigator 4000', $zoekteksten, 'Zoeken op machinenaam moet werken.' );
		$this->assertStringContainsString( 'gar-2026-0184', $zoekteksten, 'Zoeken op claimnummer moet werken.' );
	}

	/** Zoeken moet beide lijsten tegelijk raken, niet alleen de claims. */
	public function test_zowel_claims_als_machines_zijn_doorzoekbaar() {
		$this->als_goedgekeurde_dealer();

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$claims   = HDP_Garantie::lopende_claims();
		$machines = HDP_Garantie::voorbeeldmachines();

		$this->assertSame(
			count( $claims ) + count( $machines ),
			substr_count( $html, 'data-zoek=' ),
			'Elke claim en elke machine hoort doorzoekbaar te zijn.'
		);
	}

	/** De zoektekst staat in kleine letters, zodat hoofdletters niet uitmaken. */
	public function test_zoektekst_is_hoofdletterongevoelig() {
		$this->als_goedgekeurde_dealer();

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		preg_match_all( '/data-zoek="([^"]*)"/', $html, $treffers );
		foreach ( $treffers[1] as $tekst ) {
			$this->assertSame( $tekst, mb_strtolower( $tekst ) );
		}
	}

	/* --- Garantiestand per machine ------------------------------------ */

	public function test_garantiestand_herkent_een_verlopen_garantie() {
		$stand = HDP_Garantie::garantiestand( HDP_Garantie::voorbeeldmachines()['BM35-190916-0042'] );

		$this->assertTrue( $stand['verlopen'] );
		$this->assertFalse( $stand['bijna'] );
		$this->assertSame( 100, $stand['verstreken'] );
	}

	public function test_garantiestand_waarschuwt_als_dekking_bijna_afloopt() {
		$machine = array(
			'aankoopdatum' => gmdate( 'Y-m-d', strtotime( '-4 years' ) ),
			'garantie_tot' => gmdate( 'Y-m-d', strtotime( '+3 months' ) ),
		);

		$stand = HDP_Garantie::garantiestand( $machine );

		$this->assertFalse( $stand['verlopen'] );
		$this->assertTrue( $stand['bijna'], 'Een garantie die binnen een half jaar afloopt hoort op te vallen.' );
	}

	public function test_garantiestand_blijft_binnen_nul_en_honderd() {
		$machine = array(
			'aankoopdatum' => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
			'garantie_tot' => gmdate( 'Y-m-d', strtotime( '+6 years' ) ),
		);

		$stand = HDP_Garantie::garantiestand( $machine );

		$this->assertGreaterThanOrEqual( 0, $stand['verstreken'] );
		$this->assertLessThanOrEqual( 100, $stand['verstreken'] );
	}

	public function test_claims_worden_aan_hun_machine_gekoppeld() {
		$claims = HDP_Garantie::claims_van_machine( 'HN4-220718-0391' );

		$this->assertCount( 1, $claims );
		$this->assertSame( 'GAR-2026-0184', $claims[0]['nummer'] );
	}

	/**
	 * Zolang er geen koppeling met de claimadministratie is, mag niemand de
	 * voorbeeldrijen voor echte claims aanzien — die zouden anders met
	 * verzonnen claimnummers gaan bellen.
	 */
	public function test_voorbeeldweergave_is_als_zodanig_gemarkeerd() {
		$this->als_goedgekeurde_dealer();

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringContainsString( 'hdp-garantie-voorbeeld', $html );
		$this->assertStringContainsString( 'nog geen koppeling', $html );
	}

	/**
	 * Het formulier is ontwerp, nog geen werkend formulier. Elk veld hoort
	 * daarom uitgeschakeld te zijn: invullen wat niet verzonden wordt is
	 * erger dan geen formulier.
	 */
	public function test_formuliervelden_staan_allemaal_uitgeschakeld() {
		$this->als_goedgekeurde_dealer();

		$_GET['nieuw'] = 'claim';
		$html          = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );
		unset( $_GET['nieuw'] );

		// De invulvelden plus de machinekeuze erboven.
		$this->assertSame( count( HDP_Garantie::invulvelden() ) + 1, substr_count( $html, 'disabled' ) );
	}

	/** Elk veld uit de lijst hoort ook echt op de pagina te staan. */
	public function test_elk_invulveld_staat_op_het_claimformulier() {
		$this->als_goedgekeurde_dealer();

		$_GET['nieuw'] = 'claim';
		$html          = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );
		unset( $_GET['nieuw'] );

		foreach ( array_keys( HDP_Garantie::invulvelden() ) as $naam ) {
			$this->assertStringContainsString( 'hdp-claim-' . $naam, $html, "Veld $naam ontbreekt op het claimformulier." );
		}
	}

	public function test_elk_machineveld_staat_op_het_aanmeldformulier() {
		$this->als_goedgekeurde_dealer();

		$_GET['nieuw'] = 'machine';
		$html          = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );
		unset( $_GET['nieuw'] );

		foreach ( array_keys( HDP_Garantie::machinevelden() ) as $naam ) {
			$this->assertStringContainsString( 'hdp-machine-' . $naam, $html, "Veld $naam ontbreekt op het aanmeldformulier." );
		}
	}

	/**
	 * De statussen krijgen straks exact deze waarden in de
	 * claimadministratie. Elke status moet dus een label hebben, anders
	 * ziet de dealer een leeg vakje.
	 */
	public function test_elke_status_heeft_een_nederlands_en_frans_label() {
		foreach ( HDP_Garantie::STATUSSEN as $status ) {
			$nl = HDP_Garantie::status_label( $status );
			$this->assertNotSame( 'garantie_status_' . $status, $nl, "Nederlands label voor $status ontbreekt." );

			$_COOKIE[ HDP_I18N::COOKIE ] = 'fr';
			$fr                          = HDP_Garantie::status_label( $status );
			unset( $_COOKIE[ HDP_I18N::COOKIE ] );

			$this->assertNotSame( 'garantie_status_' . $status, $fr, "Frans label voor $status ontbreekt." );
			$this->assertNotSame( $nl, $fr, "Frans label voor $status is niet vertaald." );
		}
	}

	public function test_elk_formulierveld_heeft_een_nederlands_en_frans_label() {
		$alle = array_merge( array_keys( HDP_Garantie::velden() ), array_keys( HDP_Garantie::machinevelden() ) );
		foreach ( array_unique( $alle ) as $naam ) {
			$sleutel = 'garantie_veld_' . $naam;
			$this->assertNotSame( $sleutel, HDP_I18N::t( $sleutel ), "Nederlands label voor $naam ontbreekt." );
		}
	}

	/**
	 * Zet iemand in de app straks een status die hier niet bekend is, dan
	 * mag de dealer geen leeg of onbegrijpelijk vakje zien.
	 */
	public function test_onbekende_status_valt_terug_op_in_behandeling() {
		$this->assertSame( 'in_behandeling', HDP_Garantie::geldige_status( 'iets-nieuws-uit-de-app' ) );
		$this->assertSame( 'in_behandeling', HDP_Garantie::geldige_status( '' ) );
		$this->assertSame( 'goedgekeurd', HDP_Garantie::geldige_status( 'goedgekeurd' ) );
	}

	/** De status waarbij de dealer zelf aan zet is, hoort visueel op te vallen. */
	public function test_claim_die_op_de_dealer_wacht_krijgt_nadruk() {
		$this->als_goedgekeurde_dealer();

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringContainsString( 'hdp-garantie-item-actie', $html );
		$this->assertStringContainsString( 'hdp-garantie-actie-tekst', $html );
		$this->assertContains( HDP_Garantie::STATUS_ACTIE_DEALER, HDP_Garantie::STATUSSEN );
	}

	/* =====================================================================
	 * Ticketmodel
	 * ================================================================== */

	/**
	 * De route is geen rechte lijn: tijdens de behandeling kan een claim bij
	 * Homburg of bij de fabrikant liggen, en de afloop is goedkeuring of een
	 * van twee soorten afwijzing. De fase-indeling is wat die vertakkingen
	 * op één tijdlijn houdt.
	 */
	public function test_elke_status_hoort_bij_een_fase() {
		foreach ( HDP_Garantie::STATUSSEN as $status ) {
			$fase = HDP_Garantie::fase_van( $status );
			$this->assertContains( $fase, HDP_Garantie::FASE_VOLGORDE, "Status $status hoort bij geen bekende fase." );
		}
	}

	public function test_beide_soorten_afwijzing_tellen_als_afgerond() {
		$this->assertTrue( HDP_Garantie::is_afgerond( 'afgewezen' ) );
		$this->assertTrue( HDP_Garantie::is_afgerond( 'afgewezen_fabrikant' ) );
		$this->assertTrue( HDP_Garantie::is_afgerond( 'goedgekeurd' ) );

		$this->assertFalse( HDP_Garantie::is_afgerond( 'bij_fabrikant' ) );
		$this->assertFalse( HDP_Garantie::is_afgerond( 'info_nodig' ) );
	}

	public function test_afwijzing_wordt_onderscheiden_van_goedkeuring() {
		$this->assertTrue( HDP_Garantie::is_afwijzing( 'afgewezen_fabrikant' ) );
		$this->assertFalse( HDP_Garantie::is_afwijzing( 'goedgekeurd' ) );
	}

	/** De laatste gezette stap is de huidige; alles daarvoor is gedaan. */
	public function test_route_markeert_gedane_huidige_en_komende_stappen() {
		$claim   = HDP_Garantie::voorbeeldclaims()['GAR-2026-0184'];
		$stappen = HDP_Garantie::route( $claim );

		$this->assertTrue( $stappen[0]['gedaan'] );
		$this->assertFalse( $stappen[0]['huidig'] );

		$huidig = array_values( array_filter( $stappen, static function ( $s ) { return $s['huidig']; } ) );
		$this->assertCount( 1, $huidig );
		$this->assertSame( 'info_nodig', $huidig[0]['status'] );

		// De fase "besluit" is nog niet geweest en hoort er grijs onder te staan.
		$komend = array_values( array_filter( $stappen, static function ( $s ) { return '' === $s['status']; } ) );
		$this->assertCount( 1, $komend );
		$this->assertSame( 'besluit', $komend[0]['fase'] );
	}

	public function test_afgerond_ticket_toont_geen_komende_stappen_meer() {
		$claim   = HDP_Garantie::voorbeeldclaims()['GAR-2026-0151'];
		$stappen = HDP_Garantie::route( $claim );

		foreach ( $stappen as $stap ) {
			$this->assertNotSame( '', $stap['status'], 'Een afgehandeld ticket hoort geen open stappen meer te tonen.' );
		}
	}

	/** Een los bericht (zonder statuswijziging) hoort niet in de route. */
	public function test_los_bericht_staat_wel_in_het_gesprek_maar_niet_in_de_route() {
		$claim = HDP_Garantie::voorbeeldclaims()['GAR-2026-0177'];

		$statussen = HDP_Garantie::statuswijzigingen( $claim );
		foreach ( $statussen as $regel ) {
			$this->assertNotEmpty( $regel['status'] );
		}

		$berichten = HDP_Garantie::berichten( $claim );
		$afzenders = wp_list_pluck( $berichten, 'afzender' );

		$this->assertContains( 'dealer', $afzenders, 'Een ticket moet ook berichten van de dealer kunnen tonen.' );
		$this->assertContains( 'homburg', $afzenders );
		$this->assertGreaterThan( count( $statussen ), count( $berichten ) );
	}

	public function test_ligt_bij_wijst_de_juiste_partij_aan() {
		$claims = HDP_Garantie::voorbeeldclaims();

		$this->assertSame( 'dealer', HDP_Garantie::ligt_bij( $claims['GAR-2026-0184'] ) );
		$this->assertSame( 'homburg', HDP_Garantie::ligt_bij( $claims['GAR-2026-0177'] ) );
		$this->assertSame( '', HDP_Garantie::ligt_bij( $claims['GAR-2026-0169'] ) );
	}

	/**
	 * Regressiebewaking vooraf: ticketnummers lopen op, dus zonder controle
	 * zou een dealer andermans claims kunnen openen door het nummer in de URL
	 * te veranderen — exact de fout die bij de downloads gemaakt bleek.
	 */
	public function test_ticket_opvragen_vereist_portaaltoegang() {
		$this->assertNull( HDP_Garantie::claim_voor_dealer( 'GAR-2026-0184' ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'dealer' ) ) );
		$this->assertNull( HDP_Garantie::claim_voor_dealer( 'GAR-2026-0184' ), 'Een dealer die nog op goedkeuring wacht hoort geen tickets te kunnen openen.' );

		$this->als_goedgekeurde_dealer();
		$this->assertNotNull( HDP_Garantie::claim_voor_dealer( 'GAR-2026-0184' ) );
	}

	public function test_onbekend_ticketnummer_geeft_geen_gegevens_prijs() {
		$this->als_goedgekeurde_dealer();

		$this->assertNull( HDP_Garantie::claim_voor_dealer( 'GAR-2026-9999' ) );

		$_GET['ticket'] = 'GAR-2026-9999';
		$html           = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );
		unset( $_GET['ticket'] );

		$this->assertStringNotContainsString( 'hdp-ticket-kop', $html );
		$this->assertStringContainsString( 'bestaat niet', $html );
	}

	public function test_ticketscherm_toont_route_gesprek_gegevens_en_bijlagen() {
		$this->als_goedgekeurde_dealer();

		$_GET['ticket'] = 'GAR-2026-0184';
		$html           = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );
		unset( $_GET['ticket'] );

		$this->assertStringContainsString( 'hdp-ticket-kop', $html );
		$this->assertStringContainsString( 'hdp-route', $html );
		$this->assertStringContainsString( 'hdp-gesprek', $html );
		$this->assertStringContainsString( 'hdp-ticket-gegevens', $html );
		$this->assertStringContainsString( 'hdp-bijlage', $html );
		// Het antwoordvak: zonder dat is het geen ticket maar een mededeling.
		$this->assertStringContainsString( 'hdp-bericht-antwoord', $html );
	}

	/** Elke opmerking die Homburg achterlaat hoort de dealer terug te lezen. */
	public function test_opmerkingen_van_homburg_komen_bij_de_dealer_terecht() {
		$this->als_goedgekeurde_dealer();

		$_GET['ticket'] = 'GAR-2026-0184';
		$html           = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );
		unset( $_GET['ticket'] );

		foreach ( HDP_Garantie::berichten( HDP_Garantie::voorbeeldclaims()['GAR-2026-0184'] ) as $bericht ) {
			$this->assertStringContainsString( esc_html( $bericht['tekst'] ), $html );
		}
	}

	public function test_overzicht_linkt_naar_de_losse_tickets() {
		$this->als_goedgekeurde_dealer();

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		foreach ( HDP_Garantie::lopende_claims() as $claim ) {
			$this->assertStringContainsString( 'ticket=' . $claim['nummer'], $html );
		}
	}

	public function test_pagina_volgt_de_franse_taalkeuze() {
		$this->als_goedgekeurde_dealer();
		$_COOKIE[ HDP_I18N::COOKIE ] = 'fr';

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringContainsString( 'Demandes en cours', $html );
		$this->assertStringNotContainsString( 'Lopende claims', $html );
	}
}
