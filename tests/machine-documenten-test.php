<?php

/**
 * Machine aanmelden: verplichte documenten (2026-11-12).
 *
 * Bij elke aanmelding een PDI-formulier en de factuur aan de eindklant
 * (met onleesbaar gemaakte bedragen); bij Väderstad daarnaast het
 * QI-formulier.
 *
 * @group hdp-machine-documenten
 */
class Machine_Documenten_Test extends WP_UnitTestCase {

	public function test_eindklant_is_verplicht() {
		$velden = HDP_Garantie::machinevelden();
		$this->assertTrue( $velden['klant']['verplicht'] );
		$this->assertTrue( $velden['factuur_eindklant']['verplicht'] );
	}

	public function test_pdi_en_factuur_zijn_altijd_verplicht() {
		$mist = HDP_Garantie::ontbrekende_machinebestanden( 'HARDI', array() );
		$this->assertSame( array( 'pdi', 'factuur_eindklant' ), $mist, 'Bij HARDI geen QI.' );
	}

	public function test_qi_alleen_bij_vaderstad() {
		$this->assertContains( 'qi', HDP_Garantie::ontbrekende_machinebestanden( 'Väderstad', array() ) );
		$this->assertContains( 'qi', HDP_Garantie::ontbrekende_machinebestanden( 'Vaderstad', array() ), 'Ook zonder puntjes.' );
		$this->assertNotContains( 'qi', HDP_Garantie::ontbrekende_machinebestanden( 'Bogballe', array() ) );
	}

	public function test_compleet_is_niets_meer_nodig() {
		$alles = array( 'pdi' => true, 'qi' => true, 'factuur_eindklant' => true );
		$this->assertSame( array(), HDP_Garantie::ontbrekende_machinebestanden( 'Väderstad', $alles ) );
		$this->assertSame( array(), HDP_Garantie::ontbrekende_machinebestanden( 'HARDI', array( 'pdi' => true, 'factuur_eindklant' => true ) ) );
	}

	public function test_formulier_toont_de_velden() {
		$methode = new ReflectionMethod( 'HDP_Garantie_Render', 'render_formulier' );
		$methode->setAccessible( true );
		$html = $methode->invoke( null, 'machine' );

		$this->assertMatchesRegularExpression( '/name="pdi\[\]"[^>]*required/', $html );
		$this->assertMatchesRegularExpression( '/name="factuur_eindklant\[\]"[^>]*required/', $html );
		// QI: verborgen en (nog) niet verplicht tot Väderstad gekozen is.
		$this->assertStringContainsString( 'data-alleen-merk="Väderstad" hidden', $html );
		$this->assertDoesNotMatchRegularExpression( '/name="qi\[\]"[^>]*required/', $html );
		$this->assertStringContainsString( 'id="hdp-machine-merk"', $html );
		$this->assertStringContainsString( HDP_I18N::t( 'garantie_veld_factuur_eindklant_hint' ), $html );
	}
}
