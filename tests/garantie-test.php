<?php

/**
 * Test het garantieportaal.
 *
 * De gegevens komen uit de claimadministratie in de Supabase van de Homburg
 * App. Die wordt hier nagebootst via WordPress' eigen pre_http_request-filter:
 * de tests draaien daardoor zonder internet, zijn snel, en — belangrijker —
 * ze controleren óók wát er aan Supabase gevraagd wordt. Juist daar zit de
 * afscherming: elke opvraag hoort gefilterd te zijn op het accountnummer van
 * de ingelogde dealer.
 *
 * @group hdp-garantie
 */
class Garantie_Test extends WP_UnitTestCase {

	private $attributen;
	private $dealer_id;

	/** De opvragen die de nagebootste Supabase binnenkreeg, voor controle achteraf. */
	private $opvragen = array();

	/** Zet op true om elke Supabase-aanroep te laten mislukken (storingstest). */
	private $storing = false;

	/** Wat er is weggeschreven, per tabel, voor controle achteraf. */
	private $geschreven = array();

	/** Zet op een melding om de database een schrijfactie te laten weigeren. */
	private $weigeren = '';

	public function setUp(): void {
		parent::setUp();
		HDP_Roles::activate();

		if ( ! defined( 'HDP_SUPABASE_APP_URL' ) ) {
			define( 'HDP_SUPABASE_APP_URL', 'https://test.supabase.co' );
			define( 'HDP_SUPABASE_APP_KEY', 'test-sleutel' );
		}

		$this->attributen = array(
			'heroAfbeelding' => 'https://example.test/hero.jpg',
			'titel'          => 'Garantie',
			'titelFr'        => 'Garantie',
			'omschrijving'   => 'Dien hier een garantieclaim in.',
			'omschrijvingFr' => 'Introduisez ici une demande de garantie.',
			'loginIntro'     => 'Log in met uw dealeraccount.',
			'loginIntroFr'   => 'Connectez-vous avec votre compte concessionnaire.',
		);

		add_filter( 'pre_http_request', array( $this, 'nagebootste_supabase' ), 10, 3 );
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this, 'nagebootste_supabase' ), 10 );
		wp_set_current_user( 0 );
		unset( $_GET['ticket'], $_GET['nieuw'], $_GET['soort'], $_COOKIE[ HDP_I18N::COOKIE ] );
		parent::tearDown();
	}

	/* =====================================================================
	 * De nagebootste claimadministratie
	 * ================================================================== */

	public function nagebootste_supabase( $kort, $args, $url ) {
		$this->opvragen[] = $url;

		if ( $this->storing ) {
			return new WP_Error( 'http_request_failed', 'Verbinding mislukt.' );
		}

		$bron  = basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		$query = array();
		wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

		if ( isset( $args['method'] ) && 'POST' === $args['method'] ) {
			return $this->schrijfactie( $bron, $args );
		}

		// De afscherming nabootsen zoals de database hem toepast: zonder
		// dealerfilter komt er niets terug.
		$dealer = isset( $query['dealer_id'] ) ? str_replace( 'eq.', '', $query['dealer_id'] ) : null;

		$rijen = array();
		if ( 'machines' === $bron ) {
			$rijen = $this->machines( $dealer );
		} elseif ( 'claims' === $bron ) {
			$rijen = $this->claims( $dealer );
			if ( isset( $query['nummer'] ) ) {
				$nummer = str_replace( 'eq.', '', $query['nummer'] );
				$rijen  = array_values(
					array_filter(
						$rijen,
						static function ( $claim ) use ( $nummer ) {
							return $claim['nummer'] === $nummer;
						}
					)
				);
			}
		} elseif ( 'verloop_voor_dealer' === $bron ) {
			$rijen = $this->verloop();
		}

		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( $rijen ),
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
		);
	}

	/**
	 * Een schrijfactie nabootsen. De echte database weigert bewust van alles
	 * (een serienummer dat al bestaat, een claim op een afgewezen machine);
	 * $weigeren laat dat hier ook gebeuren.
	 */
	private function schrijfactie( $bron, $args ) {
		$rijen = json_decode( isset( $args['body'] ) ? $args['body'] : '[]', true );

		if ( $this->weigeren ) {
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( array( 'message' => $this->weigeren ) ),
				'response' => array(
					'code'    => 400,
					'message' => 'Bad Request',
				),
				'cookies'  => array(),
			);
		}

		$this->geschreven[ $bron ][] = $rijen[0];

		// De database vult zelf het nummer, de status en de tijd in.
		$terug = array_merge(
			array(
				'id'           => $bron . '-nieuw',
				'nummer'       => 'T100042',
				'status'       => 'claims' === $bron ? 'ingediend' : 'in_behandeling',
				'ingediend_op' => '2026-10-05T12:00:00+00:00',
			),
			(array) $rijen[0]
		);

		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( array( $terug ) ),
			'response' => array(
				'code'    => 201,
				'message' => 'Created',
			),
			'cookies'  => array(),
		);
	}

	private function machines( $dealer ) {
		$alle = array(
			array(
				'id'           => 'm-1',
				'dealer_id'    => (string) $this->dealer_id,
				'machine'      => 'HARDI Navigator 4000',
				'merk'         => 'HARDI',
				'serienummer'  => 'HN4-220718-0391',
				'aankoopdatum' => '2022-07-18',
				'garantie_tot' => '2027-07-18',
				'hectares'     => '1.240',
				'klant'        => 'Mts. Van Dijk',
				'status'       => 'goedgekeurd',
				'reden'        => null,
			),
			array(
				'id'           => 'm-2',
				'dealer_id'    => (string) $this->dealer_id,
				'machine'      => 'Bogballe M35W',
				'merk'         => 'Bogballe',
				'serienummer'  => 'BM35-240503-0042',
				'aankoopdatum' => '2024-05-03',
				'garantie_tot' => '2029-05-03',
				'hectares'     => '',
				'klant'        => 'Loonbedrijf Kamps',
				'status'       => 'in_behandeling',
				'reden'        => null,
			),
			array(
				'id'           => 'm-9',
				'dealer_id'    => '9001',
				'machine'      => 'Machine van een andere dealer',
				'merk'         => 'Garford',
				'serienummer'  => 'GRC-999',
				'aankoopdatum' => '2023-09-14',
				'garantie_tot' => '2026-12-14',
				'hectares'     => '',
				'klant'        => '',
				'status'       => 'goedgekeurd',
				'reden'        => null,
			),
		);

		return $this->alleen_van( $alle, $dealer );
	}

	private function claims( $dealer ) {
		$hardi = array(
			'machine'      => 'HARDI Navigator 4000',
			'merk'         => 'HARDI',
			'serienummer'  => 'HN4-220718-0391',
			'aankoopdatum' => '2022-07-18',
			'garantie_tot' => '2027-07-18',
			'klant'        => 'Mts. Van Dijk',
			'status'       => 'goedgekeurd',
			'reden'        => null,
		);

		$alle = array(
			array(
				'id'           => 'c-1',
				'nummer'       => 'T100001',
				'dealer_id'    => (string) $this->dealer_id,
				'machine_id'   => 'm-1',
				'status'       => 'info_nodig',
				'hectares'     => '1.240',
				'klacht'       => 'Spuitboom zakt weg.',
				'onderdelen'   => 'Cilinder links.',
				'behandelaar'  => 'Gerard de Boer',
				'ingediend_op' => '2026-10-02T20:16:00+00:00',
				'dealer_naam'  => 'Marijn van den Akker',
				'machines'     => $hardi,
			),
			array(
				'id'           => 'c-2',
				'nummer'       => 'T100002',
				'dealer_id'    => (string) $this->dealer_id,
				'machine_id'   => 'm-1',
				'status'       => 'goedgekeurd',
				'hectares'     => '',
				'klacht'       => 'Afgehandelde claim.',
				'onderdelen'   => '',
				'behandelaar'  => 'Erik Postma',
				'ingediend_op' => '2026-09-09T10:22:00+00:00',
				'dealer_naam'  => 'Marijn van den Akker',
				'machines'     => $hardi,
			),
			array(
				'id'           => 'c-9',
				'nummer'       => 'T100009',
				'dealer_id'    => '9001',
				'machine_id'   => 'm-9',
				'status'       => 'in_behandeling',
				'hectares'     => '',
				'klacht'       => 'Claim van een andere dealer.',
				'onderdelen'   => '',
				'behandelaar'  => null,
				'ingediend_op' => '2026-08-26T08:15:00+00:00',
				'dealer_naam'  => 'Jan Veldhuis',
				'machines'     => array(
					'machine'      => 'Machine van een andere dealer',
					'merk'         => 'Garford',
					'serienummer'  => 'GRC-999',
					'aankoopdatum' => '2023-09-14',
					'garantie_tot' => '2026-12-14',
					'klant'        => '',
					'status'       => 'goedgekeurd',
					'reden'        => null,
				),
			),
		);

		return $this->alleen_van( $alle, $dealer );
	}

	private function alleen_van( $rijen, $dealer ) {
		if ( null === $dealer ) {
			return array();
		}

		return array_values(
			array_filter(
				$rijen,
				static function ( $rij ) use ( $dealer ) {
					return $rij['dealer_id'] === $dealer;
				}
			)
		);
	}

	private function verloop() {
		return array(
			array(
				'afzender'  => 'homburg',
				'wie'       => 'Gerard de Boer',
				'status'    => 'in_behandeling',
				'opmerking' => 'Claim ontvangen.',
				'wanneer'   => '2026-10-03T08:41:00+00:00',
			),
			array(
				'afzender'  => 'homburg',
				'wie'       => 'Gerard de Boer',
				'status'    => 'info_nodig',
				'opmerking' => 'Graag een foto van het typeplaatje.',
				'wanneer'   => '2026-10-03T09:12:00+00:00',
			),
			array(
				'afzender'  => 'dealer',
				'wie'       => 'Marijn van den Akker',
				'status'    => null,
				'opmerking' => 'Foto volgt morgen.',
				'wanneer'   => '2026-10-03T16:02:00+00:00',
			),
		);
	}

	private function als_goedgekeurde_dealer() {
		$this->dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $this->dealer_id, 'hdp_goedgekeurd', '1' );
		wp_set_current_user( $this->dealer_id );

		return $this->dealer_id;
	}

	/* =====================================================================
	 * Toegang
	 * ================================================================== */

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
	 * Regressiebewaking: claimnummers lopen op. Zonder filter op het
	 * accountnummer zou een dealer andermans claims kunnen openen door het
	 * nummer in de URL te veranderen — exact de fout die bij de downloads
	 * gemaakt bleek.
	 */
	public function test_elke_opvraag_is_gefilterd_op_de_ingelogde_dealer() {
		$id = $this->als_goedgekeurde_dealer();

		HDP_Garantie::claims();
		HDP_Garantie::machines();

		$this->assertNotEmpty( $this->opvragen );
		foreach ( $this->opvragen as $url ) {
			$this->assertStringContainsString( 'dealer_id=eq.' . $id, $url, 'Opvraag zonder dealerfilter: ' . $url );
		}
	}

	public function test_claim_van_een_andere_dealer_is_niet_op_te_vragen() {
		$this->als_goedgekeurde_dealer();

		$this->assertNull( HDP_Garantie::claim_voor_dealer( 'T100009' ) );
		$this->assertNotNull( HDP_Garantie::claim_voor_dealer( 'T100001' ) );
	}

	public function test_zonder_portaaltoegang_wordt_er_niets_opgevraagd() {
		wp_set_current_user( 0 );

		$this->assertSame( array(), HDP_Garantie::claims() );
		$this->assertSame( array(), HDP_Garantie::machines() );
		$this->assertSame( array(), $this->opvragen, 'Er hoort niets naar de administratie te gaan.' );
	}

	/* =====================================================================
	 * Overzicht
	 * ================================================================== */

	public function test_goedgekeurde_dealer_ziet_het_overzicht() {
		$this->als_goedgekeurde_dealer();

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringContainsString( 'hdp-garantie-ingangen', $html );
		$this->assertStringContainsString( 'hdp-garantie-lijst', $html );
		$this->assertStringContainsString( 'hdp-machines', $html );
	}

	public function test_overzicht_toont_alleen_lopende_claims() {
		$this->als_goedgekeurde_dealer();

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringContainsString( 'T100001', $html );
		$this->assertStringNotContainsString( 'T100002', $html, 'Een afgehandelde claim hoort niet in het overzicht.' );
		$this->assertStringNotContainsString( 'T100009', $html, 'Een claim van een andere dealer hoort hier nooit te staan.' );
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

	/** Een dealer heeft vaak meerdere machines van hetzelfde type staan. */
	public function test_serienummer_staat_in_de_titels() {
		$this->als_goedgekeurde_dealer();

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringContainsString( 'HARDI Navigator 4000 · HN4-220718-0391', $html );
	}

	/**
	 * Een machine die nog beoordeeld moet worden: dat hoort de dealer te
	 * zien, want het bepaalt of een claim erop verder kan.
	 */
	public function test_machine_die_nog_beoordeeld_wordt_is_herkenbaar() {
		$this->als_goedgekeurde_dealer();

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringContainsString( 'hdp-machine-beoordeling-in_behandeling', $html );
		$this->assertStringContainsString( esc_html( HDP_I18N::t( 'garantie_machine_in_behandeling' ) ), $html );
	}

	/* =====================================================================
	 * Storing
	 * ================================================================== */

	/**
	 * Is de claimadministratie onbereikbaar, dan hoort de pagina dat te
	 * zeggen in plaats van lege lijsten te tonen — anders denkt een dealer
	 * dat zijn claims verdwenen zijn. De rest van het portaal (webshop,
	 * downloads) staat hier los van.
	 */
	public function test_storing_toont_een_melding_in_plaats_van_lege_lijsten() {
		$this->als_goedgekeurde_dealer();
		$this->storing = true;

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringContainsString( 'hdp-garantie-storing', $html );
		$this->assertStringNotContainsString( 'hdp-garantie-lijst', $html );
		// De ingangen blijven staan: aanmelden en indienen moeten blijven kunnen.
		$this->assertStringContainsString( 'hdp-garantie-ingangen', $html );
	}

	public function test_storing_op_het_ticketscherm_zegt_niet_dat_het_ticket_niet_bestaat() {
		$this->als_goedgekeurde_dealer();
		$this->storing  = true;
		$_GET['ticket'] = 'T100001';

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringContainsString( 'hdp-garantie-storing', $html );
		$this->assertStringNotContainsString( 'bestaat niet', $html );
	}

	/* =====================================================================
	 * Ticketscherm
	 * ================================================================== */

	public function test_ticketscherm_toont_route_gesprek_en_gegevens() {
		$this->als_goedgekeurde_dealer();
		$_GET['ticket'] = 'T100001';

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringContainsString( 'hdp-ticket-kop', $html );
		$this->assertStringContainsString( 'hdp-route', $html );
		$this->assertStringContainsString( 'hdp-gesprek', $html );
		$this->assertStringContainsString( 'hdp-ticket-klacht', $html );
		$this->assertStringContainsString( 'hdp-bericht-antwoord', $html );
	}

	/**
	 * Het scherm is een werkblad: gesprek links, feiten rechts in een kolom
	 * die blijft staan. Eerder stond alles onder elkaar, waardoor je bij elk
	 * nieuw bericht verder moest scrollen om te zien wélke machine het was.
	 */
	public function test_ticketscherm_heeft_twee_kolommen() {
		$this->als_goedgekeurde_dealer();
		$_GET['ticket'] = 'T100001';

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringContainsString( 'hdp-ticket-werkblad', $html );
		$this->assertStringContainsString( 'hdp-ticket-gesprek', $html );
		$this->assertStringContainsString( 'hdp-ticket-zijkolom', $html );
	}

	/**
	 * "Dit ticket wacht op u" stond onderaan de pagina, onder de bijlagen.
	 * Dat is het belangrijkste feit van het scherm en hoort bovenaan.
	 */
	public function test_wie_aan_zet_is_staat_boven_het_gesprek() {
		$this->als_goedgekeurde_dealer();
		$_GET['ticket'] = 'T100001';

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringContainsString( 'hdp-ticket-actie', $html );
		// En met de vraag van Homburg erbij, zodat je niet hoeft te zoeken.
		$this->assertStringContainsString( 'Graag een foto van het typeplaatje.', $html );
	}

	public function test_machinekaart_toont_de_garantiestand_bij_het_ticket() {
		$this->als_goedgekeurde_dealer();
		$_GET['ticket'] = 'T100001';

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringContainsString( 'hdp-ticket-machinenaam', $html );
		$this->assertStringContainsString( 'hdp-machine-balk', $html );
	}

	/* --- Hoelang staat een ticket al stil? ----------------------------- */

	public function test_dagen_in_status_telt_vanaf_de_laatste_statuswijziging() {
		$claim = array(
			'status'       => 'info_nodig',
			'ingediend_op' => gmdate( 'c', strtotime( '-30 days' ) ),
			'verloop'      => array(
				array( 'status' => 'in_behandeling', 'wanneer' => gmdate( 'c', strtotime( '-20 days' ) ), 'wie' => '', 'afzender' => 'homburg', 'opmerking' => '' ),
				array( 'status' => 'info_nodig', 'wanneer' => gmdate( 'c', strtotime( '-3 days' ) ), 'wie' => '', 'afzender' => 'homburg', 'opmerking' => '' ),
			),
		);

		// Niet 30 (ingediend) en niet 20 (vorige status), maar 3.
		$this->assertSame( 3, HDP_Garantie::dagen_in_status( $claim ) );
	}

	public function test_dagen_in_status_valt_terug_op_het_moment_van_indienen() {
		$claim = array(
			'status'       => 'ingediend',
			'ingediend_op' => gmdate( 'c', strtotime( '-5 days' ) ),
			'verloop'      => array(),
		);

		$this->assertSame( 5, HDP_Garantie::dagen_in_status( $claim ) );
	}

	/* --- Bijlage nasturen ---------------------------------------------- */

	/**
	 * Homburg vraagt om een foto, dus moet een dealer er een kunnen sturen.
	 * Eerder konden bijlagen alleen mee bij het indienen — daar liep het
	 * proces dood.
	 */
	public function test_antwoordvak_kan_een_bestand_meesturen() {
		$this->als_goedgekeurde_dealer();
		$_GET['ticket'] = 'T100001';

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringContainsString( 'enctype="multipart/form-data"', $html );
		$this->assertStringContainsString( 'name="bijlagen[]"', $html );
	}

	public function test_bericht_zonder_tekst_maar_met_bestand_krijgt_een_beschrijving() {
		$this->als_goedgekeurde_dealer();

		HDP_Garantie::stuur_bericht(
			'T100001',
			'',
			array(
				'name'     => array( 'typeplaatje.jpg' ),
				'error'    => array( UPLOAD_ERR_NO_FILE ),
				'size'     => array( 0 ),
				'tmp_name' => array( '' ),
			)
		);

		$regel = $this->geschreven['verloop'][0];
		$this->assertStringContainsString( 'typeplaatje.jpg', $regel['opmerking'] );
	}

	public function test_bericht_zonder_tekst_en_zonder_bestand_wordt_geweigerd() {
		$this->als_goedgekeurde_dealer();

		$this->assertWPError( HDP_Garantie::stuur_bericht( 'T100001', '', array() ) );
		$this->assertArrayNotHasKey( 'verloop', $this->geschreven );
	}

	public function test_onbekend_ticketnummer_geeft_geen_gegevens_prijs() {
		$this->als_goedgekeurde_dealer();
		$_GET['ticket'] = 'T999999';

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringNotContainsString( 'hdp-ticket-kop', $html );
		$this->assertStringContainsString( 'bestaat niet', $html );
	}

	public function test_opmerkingen_van_homburg_komen_bij_de_dealer_terecht() {
		$this->als_goedgekeurde_dealer();
		$_GET['ticket'] = 'T100001';

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringContainsString( 'Graag een foto van het typeplaatje.', $html );
		$this->assertStringContainsString( 'Foto volgt morgen.', $html );
	}

	/**
	 * Het verloop wordt gelezen via de view verloop_voor_dealer, die de
	 * interne notities van Homburg weglaat. Rechtstreeks uit de verloop-tabel
	 * lezen zou werken, maar hangt dan aan een filter dat je kunt vergeten —
	 * en dan lekt er een interne notitie naar een dealer.
	 */
	public function test_verloop_wordt_via_de_afgeschermde_view_gelezen() {
		$this->als_goedgekeurde_dealer();

		HDP_Garantie::claim_voor_dealer( 'T100001' );

		$verlooplezingen = array_filter(
			$this->opvragen,
			static function ( $url ) {
				return false !== strpos( $url, '/verloop' );
			}
		);

		$this->assertNotEmpty( $verlooplezingen );
		foreach ( $verlooplezingen as $url ) {
			$this->assertStringContainsString( 'verloop_voor_dealer', $url, 'Verloop hoort via de afgeschermde view gelezen te worden: ' . $url );
		}
	}

	/**
	 * De administratie schrijft bij het indienen zelf geen regel; die eerste
	 * stap leiden we af uit de claim. Komt er later wél zo'n regel, dan mag
	 * er geen tweede "Ingediend" bij komen.
	 */
	public function test_route_begint_altijd_met_ingediend_en_nooit_dubbel() {
		$this->als_goedgekeurde_dealer();

		$claim   = HDP_Garantie::claim_voor_dealer( 'T100001' );
		$stappen = HDP_Garantie::route( $claim );

		$this->assertSame( 'ingediend', $stappen[0]['status'] );
		$this->assertSame( $claim['dealer_naam'], $stappen[0]['wie'] );

		$ingediend = array_filter(
			$stappen,
			static function ( $stap ) {
				return 'ingediend' === $stap['status'];
			}
		);
		$this->assertCount( 1, $ingediend );
	}

	public function test_route_markeert_gedane_huidige_en_komende_stappen() {
		$this->als_goedgekeurde_dealer();

		$claim   = HDP_Garantie::claim_voor_dealer( 'T100001' );
		$stappen = HDP_Garantie::route( $claim );

		$huidig = array_values(
			array_filter(
				$stappen,
				static function ( $stap ) {
					return $stap['huidig'];
				}
			)
		);
		$this->assertCount( 1, $huidig );
		$this->assertSame( 'info_nodig', $huidig[0]['status'] );

		$komend = array_values(
			array_filter(
				$stappen,
				static function ( $stap ) {
					return '' === $stap['status'];
				}
			)
		);
		$this->assertCount( 1, $komend );
		$this->assertSame( 'besluit', $komend[0]['fase'] );
	}

	/* =====================================================================
	 * Formulieren
	 * ================================================================== */

	public function test_alle_formulieren_hebben_een_eigen_scherm() {
		$this->als_goedgekeurde_dealer();

		$schermen = array(
			array( 'machine', '' ),
			array( 'claim', 'machine' ),
			array( 'claim', 'onderdeel' ),
		);

		foreach ( $schermen as $scherm ) {
			list( $nieuw, $soort ) = $scherm;

			$_GET['nieuw'] = $nieuw;
			if ( $soort ) {
				$_GET['soort'] = $soort;
			}
			$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );
			unset( $_GET['nieuw'], $_GET['soort'] );

			$this->assertStringContainsString( 'hdp-garantie-velden', $html, "Formulier $nieuw $soort ontbreekt." );
			$this->assertStringNotContainsString( 'hdp-garantie-lijst', $html );
			$this->assertStringNotContainsString( 'hdp-machines', $html );
		}
	}

	/**
	 * Een machineclaim en een onderdelenclaim vragen om heel andere dingen,
	 * dus staat de vraag "waar gaat het over" ervoor. Zonder die keuze hoort
	 * er nog geen formulier te staan.
	 */
	public function test_claim_begint_met_de_vraag_waar_het_over_gaat() {
		$this->als_goedgekeurde_dealer();
		$_GET['nieuw'] = 'claim';

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringNotContainsString( 'hdp-garantie-velden', $html, 'Er hoort hier nog geen formulier te staan.' );
		$this->assertStringContainsString( 'soort=machine', $html );
		$this->assertStringContainsString( 'soort=onderdeel', $html );
	}

	/** Een onbekende soort valt terug op het machineformulier, niet op een fout. */
	public function test_onbekende_claimsoort_toont_de_keuze() {
		$this->als_goedgekeurde_dealer();
		$_GET['nieuw'] = 'claim';
		$_GET['soort'] = 'iets-anders';

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringNotContainsString( 'hdp-garantie-velden', $html );
	}

	/**
	 * Onderdelen komen uit een doos, niet van een serienummer: het
	 * machinekeuzemenu hoort hier niet te staan, een factuurnummer en de
	 * vraag wat er mis is juist wel.
	 */
	public function test_onderdelenclaim_vraagt_geen_machine_maar_wel_een_factuur() {
		$this->als_goedgekeurde_dealer();
		$_GET['nieuw'] = 'claim';
		$_GET['soort'] = 'onderdeel';

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringNotContainsString( 'hdp-claim-machinekeuze', $html );
		$this->assertStringContainsString( 'hdp-claim-factuurnummer', $html );
		$this->assertStringContainsString( 'hdp-claim-onderdeel_probleem', $html );
		$this->assertStringContainsString( 'onderdeel_nummer[]', $html );
	}

	public function test_claimformulier_vraagt_de_nieuwe_velden() {
		$this->als_goedgekeurde_dealer();
		$_GET['nieuw'] = 'claim';
		$_GET['soort'] = 'machine';

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		foreach ( array( 'te_claimen_tijd', 'klantreferentie', 'opmerkingen', 'onderdeel_retour' ) as $veld ) {
			$this->assertStringContainsString( 'hdp-claim-' . $veld, $html, "Veld $veld ontbreekt." );
		}
		$this->assertStringContainsString( 'onderdeel_nummer[]', $html );
	}

	public function test_velden_verschillen_per_claimsoort() {
		$machine   = array_keys( HDP_Garantie::velden( 'machine' ) );
		$onderdeel = array_keys( HDP_Garantie::velden( 'onderdeel' ) );

		$this->assertContains( 'serienummer', $machine );
		$this->assertNotContains( 'serienummer', $onderdeel );
		$this->assertContains( 'onderdeel_probleem', $onderdeel );
		$this->assertNotContains( 'onderdeel_probleem', $machine );

		// Wat allebei vragen, blijft allebei vragen.
		foreach ( array( 'klacht', 'regels', 'te_claimen_tijd', 'opmerkingen', 'fotos' ) as $gedeeld ) {
			$this->assertContains( $gedeeld, $machine );
			$this->assertContains( $gedeeld, $onderdeel );
		}
	}

	public function test_claimformulier_vraagt_niet_wat_de_machine_al_weet() {
		$this->als_goedgekeurde_dealer();
		$_GET['nieuw'] = 'claim';
		$_GET['soort'] = 'machine';

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringContainsString( 'hdp-claim-machinekeuze', $html );
		$this->assertStringNotContainsString( 'hdp-claim-serienummer', $html );
		$this->assertStringContainsString( 'hdp-claim-hectares', $html );
	}

	public function test_machinekeuze_toont_de_eigen_machines() {
		$this->als_goedgekeurde_dealer();
		$_GET['nieuw'] = 'claim';
		$_GET['soort'] = 'machine';

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringContainsString( 'HARDI Navigator 4000 · HN4-220718-0391', $html );
		$this->assertStringNotContainsString( 'Machine van een andere dealer', $html );
	}

	/* =====================================================================
	 * Indienen
	 * ================================================================== */

	public function test_machine_aanmelden_stuurt_de_dealergegevens_mee() {
		$this->als_goedgekeurde_dealer();

		$machine = HDP_Garantie::meld_machine_aan(
			array(
				'machine'      => 'Rabe Corvus TWS',
				'merk'         => 'Rabe',
				'serienummer'  => 'RC-0001',
				'aankoopdatum' => '2025-03-11',
				'klant'        => 'Testklant',
				'hectares'     => '120',
			)
		);

		$this->assertIsArray( $machine );

		$weggeschreven = $this->geschreven['machines'][0];
		$this->assertSame( (string) $this->dealer_id, $weggeschreven['dealer_id'] );
		$this->assertNotEmpty( $weggeschreven['dealer_naam'], 'De app kan niet in WordPress kijken, dus de naam moet mee.' );
		$this->assertSame( 'RC-0001', $weggeschreven['serienummer'] );

		// De status zet de database zelf; die hoort hier niet meegestuurd te worden.
		$this->assertArrayNotHasKey( 'status', $weggeschreven );
	}

	/**
	 * De machine wordt opgezocht binnen de eigen machines. Zou het portaal
	 * een meegestuurd machine-id vertrouwen, dan kon een dealer een claim op
	 * andermans machine indienen.
	 */
	public function test_claim_indienen_zoekt_de_machine_binnen_de_eigen_machines() {
		$this->als_goedgekeurde_dealer();

		$claim = HDP_Garantie::dien_claim_in(
			array(
				'machine'    => 'HN4-220718-0391',
				'klacht'     => 'Spuitboom zakt weg.',
				'onderdelen' => '',
				'hectares'   => '1.240',
			)
		);

		$this->assertIsArray( $claim );
		$this->assertSame( 'm-1', $this->geschreven['claims'][0]['machine_id'] );
		$this->assertSame( (string) $this->dealer_id, $this->geschreven['claims'][0]['dealer_id'] );
	}

	public function test_claim_op_een_machine_van_een_ander_wordt_geweigerd() {
		$this->als_goedgekeurde_dealer();

		$claim = HDP_Garantie::dien_claim_in(
			array(
				'machine'    => 'GRC-999',
				'klacht'     => 'Poging tot claim op andermans machine.',
				'onderdelen' => '',
				'hectares'   => '',
			)
		);

		$this->assertWPError( $claim );
		$this->assertArrayNotHasKey( 'claims', $this->geschreven, 'Er hoort niets weggeschreven te zijn.' );
	}

	/** Claimen op een machine die nog beoordeeld wordt, mag gewoon. */
	public function test_claim_op_een_nog_te_beoordelen_machine_mag() {
		$this->als_goedgekeurde_dealer();

		$claim = HDP_Garantie::dien_claim_in(
			array(
				'machine'    => 'BM35-240503-0042',
				'klacht'     => 'Weegcellen instabiel.',
				'onderdelen' => '',
				'hectares'   => '',
			)
		);

		$this->assertIsArray( $claim );
		$this->assertSame( 'm-2', $this->geschreven['claims'][0]['machine_id'] );
	}

	/**
	 * Een bericht van de dealer zet bewust geen status: de dealer beantwoordt
	 * een vraag, Homburg bepaalt wat dat voor de status betekent.
	 */
	public function test_bericht_van_de_dealer_zet_geen_status() {
		$this->als_goedgekeurde_dealer();

		HDP_Garantie::stuur_bericht( 'T100001', 'Foto volgt vanmiddag.' );

		$regel = $this->geschreven['verloop'][0];
		$this->assertSame( 'dealer', $regel['afzender'] );
		$this->assertSame( 'Foto volgt vanmiddag.', $regel['opmerking'] );
		$this->assertArrayNotHasKey( 'status', $regel );
		$this->assertArrayNotHasKey( 'intern', $regel, 'Een dealer schrijft nooit een interne notitie.' );
	}

	public function test_bericht_bij_een_ticket_van_een_ander_wordt_geweigerd() {
		$this->als_goedgekeurde_dealer();

		$resultaat = HDP_Garantie::stuur_bericht( 'T100009', 'Poging tot meelezen.' );

		$this->assertWPError( $resultaat );
		$this->assertArrayNotHasKey( 'verloop', $this->geschreven );
	}

	public function test_leeg_bericht_wordt_geweigerd() {
		$this->als_goedgekeurde_dealer();

		$this->assertWPError( HDP_Garantie::stuur_bericht( 'T100001', '   ' ) );
	}

	/** De melding van de database is bruikbaar voor de dealer en gaat mee terug. */
	public function test_een_weigering_van_de_database_komt_als_melding_terug() {
		$this->als_goedgekeurde_dealer();
		$this->weigeren = 'Deze machine is nog niet goedgekeurd voor garantie.';

		$claim = HDP_Garantie::dien_claim_in(
			array(
				'machine'    => 'HN4-220718-0391',
				'klacht'     => 'Test.',
				'onderdelen' => '',
				'hectares'   => '',
			)
		);

		$this->assertWPError( $claim );
		$this->assertStringContainsString( 'nog niet goedgekeurd', $claim->get_error_message() );
	}

	/* --- De formulieren zelf ------------------------------------------- */

	public function test_formulieren_zijn_echte_formulieren_met_een_nonce() {
		$this->als_goedgekeurde_dealer();

		$schermen = array(
			array( 'machine', '' ),
			array( 'claim', 'machine' ),
			array( 'claim', 'onderdeel' ),
		);

		foreach ( $schermen as $scherm ) {
			list( $nieuw, $soort ) = $scherm;

			$_GET['nieuw'] = $nieuw;
			if ( $soort ) {
				$_GET['soort'] = $soort;
			}
			$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );
			unset( $_GET['nieuw'], $_GET['soort'] );

			$this->assertStringContainsString( '<form', $html, "Formulier $nieuw $soort is geen form." );
			$this->assertStringContainsString( '_wpnonce', $html, "Formulier $nieuw $soort heeft geen nonce." );
			$this->assertStringContainsString( 'type="submit"', $html, "Formulier $nieuw $soort heeft geen verzendknop." );
			$this->assertStringNotContainsString( 'disabled', $html, "Formulier $nieuw $soort staat nog uit." );
		}
	}

	public function test_antwoordvak_op_een_ticket_is_een_werkend_formulier() {
		$this->als_goedgekeurde_dealer();
		$_GET['ticket'] = 'T100001';

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringContainsString( 'name="bericht"', $html );
		$this->assertStringContainsString( 'value="bericht"', $html );
		$this->assertStringNotContainsString( 'hdp-btn-uit', $html, 'De knop hoort niet meer uitgeschakeld te zijn.' );
	}

	/* =====================================================================
	 * Begrippen — het contract met de claimadministratie
	 * ================================================================== */

	public function test_elke_status_hoort_bij_een_fase() {
		foreach ( HDP_Garantie::STATUSSEN as $status ) {
			$this->assertContains( HDP_Garantie::fase_van( $status ), HDP_Garantie::FASE_VOLGORDE, "Status $status hoort bij geen bekende fase." );
		}
	}

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

	public function test_elk_formulierveld_heeft_een_label() {
		$alle = array_merge( array_keys( HDP_Garantie::velden() ), array_keys( HDP_Garantie::machinevelden() ) );
		foreach ( array_unique( $alle ) as $naam ) {
			$sleutel = 'garantie_veld_' . $naam;
			$this->assertNotSame( $sleutel, HDP_I18N::t( $sleutel ), "Label voor $naam ontbreekt." );
		}
	}

	/** Zet iemand in de app een status die hier niet bekend is, dan geen leeg vakje. */
	public function test_onbekende_status_valt_terug_op_in_behandeling() {
		$this->assertSame( 'in_behandeling', HDP_Garantie::geldige_status( 'iets-nieuws-uit-de-app' ) );
		$this->assertSame( 'goedgekeurd', HDP_Garantie::geldige_status( 'goedgekeurd' ) );
	}

	public function test_beide_soorten_afwijzing_tellen_als_afgerond() {
		$this->assertTrue( HDP_Garantie::is_afgerond( 'afgewezen' ) );
		$this->assertTrue( HDP_Garantie::is_afgerond( 'afgewezen_fabrikant' ) );
		$this->assertFalse( HDP_Garantie::is_afgerond( 'bij_fabrikant' ) );
	}

	public function test_ligt_bij_wijst_de_juiste_partij_aan() {
		$this->assertSame( 'dealer', HDP_Garantie::ligt_bij( array( 'status' => 'info_nodig' ) ) );
		$this->assertSame( 'homburg', HDP_Garantie::ligt_bij( array( 'status' => 'bij_fabrikant' ) ) );
		$this->assertSame( '', HDP_Garantie::ligt_bij( array( 'status' => 'goedgekeurd' ) ) );
	}

	/* --- Garantiestand per machine ------------------------------------ */

	public function test_garantiestand_herkent_een_verlopen_garantie() {
		$stand = HDP_Garantie::garantiestand(
			array(
				'aankoopdatum' => '2019-09-16',
				'garantie_tot' => '2024-09-16',
			)
		);

		$this->assertTrue( $stand['verlopen'] );
		$this->assertSame( 100, $stand['verstreken'] );
	}

	public function test_garantiestand_waarschuwt_als_dekking_bijna_afloopt() {
		$stand = HDP_Garantie::garantiestand(
			array(
				'aankoopdatum' => gmdate( 'Y-m-d', strtotime( '-4 years' ) ),
				'garantie_tot' => gmdate( 'Y-m-d', strtotime( '+3 months' ) ),
			)
		);

		$this->assertFalse( $stand['verlopen'] );
		$this->assertTrue( $stand['bijna'], 'Een garantie die binnen een half jaar afloopt hoort op te vallen.' );
	}

	public function test_garantiestand_blijft_binnen_nul_en_honderd() {
		$stand = HDP_Garantie::garantiestand(
			array(
				'aankoopdatum' => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
				'garantie_tot' => gmdate( 'Y-m-d', strtotime( '+6 years' ) ),
			)
		);

		$this->assertGreaterThanOrEqual( 0, $stand['verstreken'] );
		$this->assertLessThanOrEqual( 100, $stand['verstreken'] );
	}

	/* --- Zoeken -------------------------------------------------------- */

	public function test_zowel_claims_als_machines_zijn_doorzoekbaar() {
		$this->als_goedgekeurde_dealer();

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		preg_match_all( '/data-zoek="([^"]*)"/', $html, $treffers );
		$zoekteksten = implode( ' | ', $treffers[1] );

		$this->assertStringContainsString( 'hn4-220718-0391', $zoekteksten, 'Zoeken op serienummer moet werken.' );
		$this->assertStringContainsString( 't100001', $zoekteksten, 'Zoeken op claimnummer moet werken.' );
	}

	public function test_pagina_volgt_de_franse_taalkeuze() {
		$this->als_goedgekeurde_dealer();
		$_COOKIE[ HDP_I18N::COOKIE ] = 'fr';

		$html = HDP_Garantie_Render::render_garantie_pagina( $this->attributen );

		$this->assertStringContainsString( 'Demandes en cours', $html );
		$this->assertStringNotContainsString( 'Lopende claims', $html );
	}
}
