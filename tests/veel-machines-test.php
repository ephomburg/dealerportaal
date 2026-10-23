<?php

/**
 * Test wat er gebeurt bij een dealer met veel machines.
 *
 * Twee dingen gingen daar mis. De machinelijst telde per machine de
 * lopende claims, en haalde daarvoor elke keer opnieuw de hele claimlijst
 * uit de claimadministratie: bij honderd machines waren dat honderd
 * ronden voor één pagina. En de keuzelijst op het claimformulier was een
 * gewone dropdown — met honderd regels niet doorheen te komen, want de
 * typehulp van de browser zoekt alleen vanaf het begin van de regel en
 * dus niet op serienummer.
 *
 * @group hdp-veel-machines
 */
class Veel_Machines_Test extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		// Zonder dit heeft de rol "dealer" de portaalrechten niet en geldt
		// een testdealer als onbevoegd; claims() stopt dan meteen.
		HDP_Roles::activate();
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/** Een machinelijst zoals HDP_Garantie::machines() die teruggeeft. */
	private function machines( $aantal ) {
		$machines = array();

		for ( $i = 1; $i <= $aantal; $i++ ) {
			$machines[] = array(
				'id'           => (string) $i,
				'machine'      => 'HARDI Commander ' . $i,
				'serienummer'  => sprintf( 'SN-%05d', $i ),
				'merk'         => 'HARDI',
				'aankoopdatum' => '2024-01-01',
				'garantie_tot' => '2027-01-01',
				'hectares'     => '',
				'klant'        => 'Klant ' . $i,
				'status'       => 'goedgekeurd',
				'reden'        => '',
			);
		}

		return $machines;
	}

	private function keuze( $machines ) {
		$methode = new ReflectionMethod( 'HDP_Garantie_Render', 'render_machinekeuze' );
		$methode->setAccessible( true );

		ob_start();
		$methode->invoke( null, $machines );

		return ob_get_clean();
	}

	/* --- Het zoekveld ---------------------------------------------------- */

	/** Bij een handvol machines zou een zoekveld alleen in de weg staan. */
	public function test_weinig_machines_krijgen_geen_zoekveld() {
		$html = $this->keuze( $this->machines( 3 ) );

		$this->assertStringNotContainsString( 'hdp-machinezoek', $html );
		$this->assertStringContainsString( 'HARDI Commander 1', $html );
	}

	public function test_veel_machines_krijgen_een_zoekveld() {
		$html = $this->keuze( $this->machines( 100 ) );

		$this->assertStringContainsString( 'hdp-machinezoek', $html );
		$this->assertStringContainsString( HDP_I18N::t( 'garantie_machine_zoek' ), $html );
	}

	/** Alle machines staan er wel in; het zoekveld dunt alleen uit. */
	public function test_alle_machines_staan_in_de_lijst() {
		$html = $this->keuze( $this->machines( 100 ) );

		$this->assertSame( 101, substr_count( $html, '<option' ), 'Honderd machines plus de regel "maak een keuze".' );
		$this->assertStringContainsString( 'SN-00001', $html );
		$this->assertStringContainsString( 'SN-00100', $html );
	}

	/**
	 * Het serienummer moet in de zichtbare tekst staan en niet alleen in de
	 * waarde: het zoekveld kijkt naar wat er op het scherm staat, dus anders
	 * valt er op serienummer niet te zoeken.
	 */
	public function test_het_serienummer_staat_in_de_zichtbare_tekst() {
		$html = $this->keuze( $this->machines( 100 ) );

		$this->assertMatchesRegularExpression( '/<option[^>]*>[^<]*SN-00042/', $html );
	}

	/* --- Het aantal ronden naar de administratie ------------------------- */

	/**
	 * De kern van het snelheidsprobleem: is de claimlijst binnen deze
	 * paginalaad al opgehaald, dan mag er geen tweede ronde meer komen.
	 * Zonder dat kost een lijst van honderd machines honderd ronden, want
	 * elke machine telt zijn eigen lopende claims.
	 *
	 * We zetten de buffer zelf klaar en kijken of hij gebruikt wórdt — met
	 * een echte verbinding meten zou deze test afhankelijk maken van de
	 * claimadministratie.
	 */
	public function test_een_gevulde_buffer_wordt_gebruikt_in_plaats_van_opnieuw_ophalen() {
		$dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $dealer_id, 'hdp_goedgekeurd', '1' );
		wp_set_current_user( $dealer_id );

		$buffer = new ReflectionProperty( 'HDP_Garantie', 'gebufferd' );
		$buffer->setAccessible( true );
		$buffer->setValue(
			null,
			array(
				'claims_' . $dealer_id => array(
					array(
						'nummer'      => 'T100001',
						'status'      => 'ingediend',
						'serienummer' => 'SN-00001',
					),
					array(
						'nummer'      => 'T100002',
						'status'      => 'ingediend',
						'serienummer' => 'SN-00002',
					),
				),
			)
		);

		$verzoeken = 0;
		$tellen    = function () use ( &$verzoeken ) {
			++$verzoeken;
		};
		add_action( 'http_api_debug', $tellen );

		$gevonden = array();
		for ( $i = 0; $i < 50; $i++ ) {
			$gevonden = HDP_Garantie::claims_van_machine( 'SN-00001' );
		}

		remove_action( 'http_api_debug', $tellen );
		$buffer->setValue( null, array() );
		wp_set_current_user( 0 );

		$this->assertSame( 0, $verzoeken, 'Met een gevulde buffer hoort er niets meer opgehaald te worden.' );
		$this->assertCount( 1, $gevonden, 'En het antwoord komt uit de buffer, gefilterd op serienummer.' );
		$this->assertSame( 'T100001', $gevonden[0]['nummer'] );
	}
}
