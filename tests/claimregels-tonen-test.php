<?php

/**
 * Test dat een dealer zijn eigen ingediende onderdelen terugziet, en dat
 * een lange machinelijst wordt ingekort.
 *
 * Het portaal schreef de claimregels wel weg maar las ze nooit terug: een
 * dealer vulde onderdeelnummers, bedragen en uren in en zag daar op zijn
 * eigen ticket niets van. Hij kon dus ook niet nagaan of hij het goed had
 * ingevuld.
 *
 * @group hdp-regels-tonen
 */
class Claimregels_Tonen_Test extends WP_UnitTestCase {

	/** Een regel zoals hij in de claimadministratie staat. */
	private function rij( $nummer, $aantal, $cent, $uren = null, $homburg = true ) {
		return array(
			'nummer'          => $nummer,
			'aantal'          => $aantal,
			'bedrag_cent'     => $cent,
			'homburg_factuur' => $homburg,
			'uren'            => $uren,
		);
	}

	private function claim( $regels ) {
		return array(
			'nummer'          => 'T100042',
			'klacht'          => 'Wielsensor defect',
			'onderdelen'      => '',
			'factuurnummer'   => 'F-2026-118',
			'klantreferentie' => 'Jansen, perceel noord',
			'opmerkingen'     => 'Hierover gemaild met Gerard',
			'regels'          => array_map( 'HDP_Garantie::regel_uit_rij', $regels ),
		);
	}

	private function kaart( $claim ) {
		$methode = new ReflectionMethod( 'HDP_Garantie_Render', 'render_claimgegevens' );
		$methode->setAccessible( true );

		ob_start();
		$methode->invoke( null, $claim );

		return ob_get_clean();
	}

	/* --- De regels zelf -------------------------------------------------- */

	public function test_de_ingediende_onderdelen_staan_op_het_ticket() {
		$html = $this->kaart(
			$this->claim(
				array(
					$this->rij( '660-00-10201-00', 1, 124725, 2.5 ),
					$this->rij( '10714', 2, 102131, 0, false ),
				)
			)
		);

		$this->assertStringContainsString( '660-00-10201-00', $html );
		$this->assertStringContainsString( '10714', $html );
	}

	/** Bedragen staan in centen opgeslagen en horen als euro's op het scherm. */
	public function test_bedragen_worden_als_euro_getoond() {
		$html = $this->kaart( $this->claim( array( $this->rij( 'A1', 1, 124725, 1 ) ) ) );

		$this->assertStringContainsString( '1.247,25', $html );
		$this->assertStringNotContainsString( '124725', $html );
	}

	/** Het totaal telt het aantal mee: twee stuks van € 10 is € 20. */
	public function test_het_totaal_telt_de_aantallen_mee() {
		$html = $this->kaart(
			$this->claim(
				array(
					$this->rij( 'A1', 2, 1000, 1 ),
					$this->rij( 'B2', 1, 500, 1 ),
				)
			)
		);

		$this->assertStringContainsString( '25,00', $html );
	}

	public function test_de_uren_staan_erbij_en_worden_opgeteld() {
		$html = $this->kaart(
			$this->claim(
				array(
					$this->rij( 'A1', 1, 1000, 2.5 ),
					$this->rij( 'B2', 1, 1000, 1.25 ),
				)
			)
		);

		$this->assertStringContainsString( 'hdp-ticket-regels-uren', $html );
		$this->assertStringContainsString( '3,75', $html );
	}

	/**
	 * Een oudere claim heeft geen uren in zijn regels staan. Dan hoort die
	 * kolom gewoon weg te blijven in plaats van vijf keer een streepje.
	 */
	public function test_zonder_uren_verschijnt_die_kolom_niet() {
		$html = $this->kaart( $this->claim( array( $this->rij( 'A1', 1, 1000 ) ) ) );

		$this->assertStringNotContainsString( 'hdp-ticket-regels-uren', $html );
		$this->assertStringNotContainsString( HDP_I18N::t( 'garantie_regel_uren' ), $html );
	}

	public function test_de_herkomst_van_de_factuur_staat_erbij() {
		$html = $this->kaart(
			$this->claim(
				array(
					$this->rij( 'A1', 1, 1000, 1, true ),
					$this->rij( 'B2', 1, 1000, 1, false ),
				)
			)
		);

		$this->assertStringContainsString( HDP_I18N::t( 'garantie_regel_homburg' ), $html );
		$this->assertStringContainsString( HDP_I18N::t( 'garantie_regel_derden' ), $html );
	}

	/** Zonder regels hoort er geen lege tabel te staan. */
	public function test_zonder_regels_geen_tabel() {
		$html = $this->kaart( $this->claim( array() ) );

		$this->assertStringNotContainsString( 'hdp-ticket-regels', $html );
	}

	/* --- De andere velden die de dealer invulde -------------------------- */

	/**
	 * Factuurnummer, klantreferentie en opmerkingen werden ook niet
	 * teruggelezen uit de administratie.
	 */
	public function test_ook_de_overige_opgaven_komen_terug() {
		$html = $this->kaart( $this->claim( array() ) );

		$this->assertStringContainsString( 'F-2026-118', $html );
		$this->assertStringContainsString( 'Jansen, perceel noord', $html );
		$this->assertStringContainsString( 'Hierover gemaild met Gerard', $html );
	}

	/* --- Wat er uit de administratie komt -------------------------------- */

	/**
	 * De regels komen uit een JSON-kolom, dus er is geen garantie dat alle
	 * velden erin staan. Oudere claims hebben geen uren, en een regel kan
	 * zonder bedrag zijn opgeslagen.
	 */
	public function test_een_onvolledige_regel_levert_geen_fout_op() {
		$regel = HDP_Garantie::regel_uit_rij( array( 'nummer' => 'A1' ) );

		$this->assertSame( 'A1', $regel['nummer'] );
		$this->assertSame( 1, $regel['aantal'] );
		$this->assertNull( $regel['bedrag_cent'] );
		$this->assertNull( $regel['uren'] );
		$this->assertTrue( $regel['homburg_factuur'], 'Zonder opgave gaan we uit van een Homburg-factuur.' );
	}

	public function test_nul_uren_blijft_nul_en_wordt_geen_leegte() {
		$regel = HDP_Garantie::regel_uit_rij( array( 'nummer' => 'A1', 'uren' => 0 ) );

		$this->assertSame( 0.0, $regel['uren'] );
	}
}
