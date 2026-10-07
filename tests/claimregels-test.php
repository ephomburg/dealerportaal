<?php

/**
 * Test het uitlezen van "te claimen onderdelen" en de factuurplicht.
 *
 * Dit is het stuk dat stil fout kan gaan: vier losse rijtjes uit het
 * formulier worden hier weer één lijst. Lopen die rijtjes niet gelijk, dan
 * belandt het bedrag van regel 2 bij onderdeel 3 en merkt niemand het —
 * de claim komt er gewoon doorheen, met verkeerde bedragen.
 *
 * @group hdp-claimregels
 */
class Claimregels_Test extends WP_UnitTestCase {

	public function tearDown(): void {
		unset(
			$_POST['onderdeel_nummer'],
			$_POST['onderdeel_aantal'],
			$_POST['onderdeel_bedrag'],
			$_POST['onderdeel_herkomst']
		);
		parent::tearDown();
	}

	private function roep( $naam, $argumenten = array() ) {
		$methode = new ReflectionMethod( 'HDP_Garantie_Formulier', $naam );
		$methode->setAccessible( true );
		return $methode->invokeArgs( null, $argumenten );
	}

	private function vul_in( $regels ) {
		$_POST['onderdeel_nummer']   = wp_list_pluck( $regels, 0 );
		$_POST['onderdeel_aantal']   = wp_list_pluck( $regels, 1 );
		$_POST['onderdeel_bedrag']   = wp_list_pluck( $regels, 2 );
		$_POST['onderdeel_herkomst'] = wp_list_pluck( $regels, 3 );
	}

	/* --- Bedragen zoals een mens ze typt ------------------------------ */

	public function test_bedrag_begrijpt_nederlandse_notatie() {
		// Uitkomst in hele centen.
		$verwacht = array(
			'12,99'    => 1299,
			'1.021,31' => 102131,
			'2.502,19' => 250219,
			'1021.31'  => 102131,
			'15'       => 1500,
			'€ 15,00'  => 1500,
			' 48,64 '  => 4864,
		);

		foreach ( $verwacht as $ingetypt => $uitkomst ) {
			$this->assertSame( $uitkomst, $this->roep( 'bedrag', array( $ingetypt ) ), "Bedrag \"$ingetypt\" komt er verkeerd uit." );
		}
	}

	public function test_bedrag_zonder_waarde_blijft_leeg() {
		$this->assertNull( $this->roep( 'bedrag', array( '' ) ) );
		$this->assertNull( $this->roep( 'bedrag', array( '   ' ) ) );
		$this->assertNull( $this->roep( 'bedrag', array( 'op aanvraag' ) ) );
	}

	/* --- De regels zelf ------------------------------------------------ */

	public function test_lege_regels_vallen_weg() {
		// Er staan altijd een paar lege regels klaar; die horen niet als
		// onderdeel in de claim te belanden.
		$this->vul_in(
			array(
				array( '10714', '2', '1.021,31', 'homburg' ),
				array( '', '', '', 'homburg' ),
				array( '', '1', '', 'homburg' ),
			)
		);

		$regels = $this->roep( 'claimregels' );

		$this->assertCount( 1, $regels );
		$this->assertSame( '10714', $regels[0]['nummer'] );
		$this->assertSame( 2, $regels[0]['aantal'] );
		$this->assertSame( 102131, $regels[0]['bedrag_cent'] );
		$this->assertTrue( $regels[0]['homburg_factuur'] );
	}

	/**
	 * De kern: na het wegvallen van een lege regel moeten bedrag en aantal
	 * nog steeds bij het júíste onderdeel horen.
	 */
	public function test_waarden_blijven_bij_hun_eigen_onderdeel() {
		$this->vul_in(
			array(
				array( '', '', '', 'homburg' ),
				array( '10611', '1', '448,51', 'derden' ),
				array( '', '', '', 'homburg' ),
				array( '11669', '4', '5,74', 'homburg' ),
			)
		);

		$regels = $this->roep( 'claimregels' );

		$this->assertCount( 2, $regels );
		$this->assertSame( '10611', $regels[0]['nummer'] );
		$this->assertSame( 44851, $regels[0]['bedrag_cent'] );
		$this->assertFalse( $regels[0]['homburg_factuur'] );
		$this->assertSame( '11669', $regels[1]['nummer'] );
		$this->assertSame( 4, $regels[1]['aantal'] );
		$this->assertTrue( $regels[1]['homburg_factuur'] );
	}

	public function test_aantal_is_minstens_een() {
		$this->vul_in(
			array(
				array( '10714', '0', '10,00', 'homburg' ),
				array( '10715', '', '10,00', 'homburg' ),
			)
		);

		$regels = $this->roep( 'claimregels' );

		$this->assertSame( 1, $regels[0]['aantal'] );
		$this->assertSame( 1, $regels[1]['aantal'] );
	}

	/* --- De factuurplicht ---------------------------------------------- */

	public function test_herkent_een_onderdeel_van_een_andere_leverancier() {
		$alleen_homburg = array(
			array( 'nummer' => '1', 'homburg_factuur' => true ),
			array( 'nummer' => '2', 'homburg_factuur' => true ),
		);
		$met_derden     = array(
			array( 'nummer' => '1', 'homburg_factuur' => true ),
			array( 'nummer' => '2', 'homburg_factuur' => false ),
		);

		$this->assertFalse( $this->roep( 'heeft_derden', array( $alleen_homburg ) ) );
		$this->assertTrue( $this->roep( 'heeft_derden', array( $met_derden ) ) );
		$this->assertFalse( $this->roep( 'heeft_derden', array( array() ) ) );
	}

	public function test_herkent_of_er_echt_een_bestand_bij_zit() {
		$this->assertFalse( $this->roep( 'heeft_bestand', array( array() ) ) );
		// Een leeg uploadveld stuurt wél een rij mee, maar zonder naam.
		$this->assertFalse( $this->roep( 'heeft_bestand', array( array( 'name' => array( '' ) ) ) ) );
		$this->assertTrue( $this->roep( 'heeft_bestand', array( array( 'name' => array( '', 'factuur.pdf' ) ) ) ) );
	}
}
