<?php

/**
 * Test de uren per onderdeel bij een machineclaim.
 *
 * Er was één veld "te claimen tijd" voor de hele claim. Daarmee kwam alle
 * tijd op één hoop en viel achteraf niet meer te zien waar hij aan op was
 * gegaan — niet bij ons en niet bij de fabrikant. Nu staat er een
 * urenkolom in de regels en moet die ingevuld zijn.
 *
 * Nul is een geldig antwoord: een onderdeel kan geleverd zijn zonder dat
 * er tijd aan besteed is. Niets invullen is dat niet.
 *
 * @group hdp-uren
 */
class Uren_Per_Onderdeel_Test extends WP_UnitTestCase {

	/* --- De velden ------------------------------------------------------ */

	/** De regels van een machineclaim vragen om uren, die van een onderdelenclaim niet. */
	public function test_alleen_de_machineclaim_vraagt_uren_per_regel() {
		$machine   = HDP_Garantie::velden( HDP_Garantie::SOORT_MACHINE );
		$onderdeel = HDP_Garantie::velden( HDP_Garantie::SOORT_ONDERDEEL );

		$this->assertTrue( ! empty( $machine['regels']['uren'] ), 'Een machineclaim hoort uren per regel te vragen.' );
		$this->assertTrue( empty( $onderdeel['regels']['uren'] ), 'Een onderdelenclaim gaat over geleverde onderdelen, niet over werkuren.' );
	}

	/**
	 * Het losse veld voor de hele claim is bij een machineclaim vervallen:
	 * twee plekken waar tijd staat, is één te veel.
	 */
	public function test_het_losse_urenveld_is_weg_bij_een_machineclaim() {
		$this->assertArrayNotHasKey( 'te_claimen_tijd', HDP_Garantie::velden( HDP_Garantie::SOORT_MACHINE ) );
		$this->assertArrayHasKey( 'te_claimen_tijd', HDP_Garantie::velden( HDP_Garantie::SOORT_ONDERDEEL ) );
	}

	/* --- Optellen ------------------------------------------------------- */

	public function test_de_uren_van_de_regels_worden_opgeteld() {
		$regels = array(
			array( 'nummer' => 'A1', 'uren' => 1.5 ),
			array( 'nummer' => 'B2', 'uren' => 2.25 ),
			array( 'nummer' => 'C3', 'uren' => 0 ),
		);

		$this->assertSame( 3.75, HDP_Garantie::tel_uren_op( $regels ) );
	}

	/** Nul telt wél mee als antwoord: het totaal wordt dan 0, niet "niets". */
	public function test_alleen_nullen_leveren_het_getal_nul_op() {
		$regels = array( array( 'nummer' => 'A1', 'uren' => 0 ) );

		$this->assertSame( 0.0, HDP_Garantie::tel_uren_op( $regels ) );
	}

	/** Staat er nergens een urenopgave, dan is het totaal leeg en niet 0. */
	public function test_zonder_opgave_blijft_het_totaal_leeg() {
		$this->assertSame( '', HDP_Garantie::tel_uren_op( array( array( 'nummer' => 'A1', 'uren' => null ) ) ) );
		$this->assertSame( '', HDP_Garantie::tel_uren_op( array() ) );
	}

	/* --- Het formulier -------------------------------------------------- */

	private function regels_uit_formulier( $uren ) {
		$_POST['onderdeel_nummer']   = array( 'A1', 'B2' );
		$_POST['onderdeel_aantal']   = array( '1', '2' );
		$_POST['onderdeel_bedrag']   = array( '10,00', '20,50' );
		$_POST['onderdeel_herkomst'] = array( 'homburg', 'homburg' );
		$_POST['onderdeel_uren']     = $uren;

		$methode = new ReflectionMethod( 'HDP_Garantie_Formulier', 'claimregels' );
		$methode->setAccessible( true );
		$regels = $methode->invoke( null );

		foreach ( array( 'onderdeel_nummer', 'onderdeel_aantal', 'onderdeel_bedrag', 'onderdeel_herkomst', 'onderdeel_uren' ) as $sleutel ) {
			unset( $_POST[ $sleutel ] );
		}

		return $regels;
	}

	public function test_de_uren_komen_bij_de_juiste_regel_terecht() {
		$regels = $this->regels_uit_formulier( array( '1,5', '3' ) );

		$this->assertSame( 'A1', $regels[0]['nummer'] );
		$this->assertSame( 1.5, $regels[0]['uren'] );
		$this->assertSame( 'B2', $regels[1]['nummer'] );
		$this->assertSame( 3.0, $regels[1]['uren'] );
	}

	/** Een komma als decimaalteken hoort gewoon te werken; zo typt men hier. */
	public function test_een_komma_wordt_begrepen() {
		$regels = $this->regels_uit_formulier( array( '0,25', '0' ) );

		$this->assertSame( 0.25, $regels[0]['uren'] );
	}

	/**
	 * Niets ingevuld wordt null en niet 0 — anders zou het formulier
	 * "nul uur" invullen voor iemand die het vergeten is.
	 */
	public function test_niets_invullen_is_geen_nul() {
		$regels = $this->regels_uit_formulier( array( '', '0' ) );

		$this->assertNull( $regels[0]['uren'] );
		$this->assertSame( 0.0, $regels[1]['uren'] );
	}

	/** Een negatief aantal uren bestaat niet. */
	public function test_negatieve_uren_worden_nul() {
		$regels = $this->regels_uit_formulier( array( '-3', '1' ) );

		$this->assertSame( 0.0, $regels[0]['uren'] );
	}

	/* --- De controle ---------------------------------------------------- */

	private function mist_uren( $regels ) {
		$methode = new ReflectionMethod( 'HDP_Garantie_Formulier', 'mist_uren' );
		$methode->setAccessible( true );

		return $methode->invoke( null, $regels );
	}

	public function test_een_regel_zonder_uren_wordt_opgemerkt() {
		$this->assertTrue(
			$this->mist_uren(
				array(
					array( 'nummer' => 'A1', 'uren' => 2 ),
					array( 'nummer' => 'B2', 'uren' => null ),
				)
			)
		);
	}

	public function test_overal_uren_ingevuld_is_in_orde() {
		$this->assertFalse(
			$this->mist_uren(
				array(
					array( 'nummer' => 'A1', 'uren' => 2 ),
					array( 'nummer' => 'B2', 'uren' => 0 ),
				)
			)
		);
	}

	/* --- Op het scherm --------------------------------------------------- */

	public function test_het_machineformulier_toont_de_urenkolom() {
		$methode = new ReflectionMethod( 'HDP_Garantie_Render', 'render_claimregels' );
		$methode->setAccessible( true );

		ob_start();
		$methode->invoke( null, array(), true );
		$met = ob_get_clean();

		ob_start();
		$methode->invoke( null, array(), false );
		$zonder = ob_get_clean();

		$this->assertStringContainsString( 'onderdeel_uren[]', $met );
		$this->assertStringContainsString( HDP_I18N::t( 'garantie_regel_uren' ), $met );
		$this->assertStringContainsString( 'hdp-claimregels-met-uren', $met );

		$this->assertStringNotContainsString( 'onderdeel_uren[]', $zonder );
	}

	/** Na een afgekeurde invoer staan de uren er weer, nul inbegrepen. */
	public function test_ingevulde_uren_blijven_staan_na_een_fout() {
		$methode = new ReflectionMethod( 'HDP_Garantie_Render', 'render_claimregels' );
		$methode->setAccessible( true );

		ob_start();
		$methode->invoke(
			null,
			array(
				array( 'nummer' => 'A1', 'uren' => 2.5 ),
				array( 'nummer' => 'B2', 'uren' => 0 ),
			),
			true
		);
		$html = ob_get_clean();

		$this->assertStringContainsString( 'value="2.5"', $html );
		$this->assertStringContainsString( 'value="0"', $html );
	}
}
