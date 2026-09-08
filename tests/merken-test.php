<?php

/**
 * Test HDP_Merken — de gedeelde merkenlijst en de conversiehelpers tussen
 * het opgeslagen kommagescheiden formaat (user-meta hdp_merken) en een
 * array (checkboxwaarden uit een formulier).
 *
 * @group hdp-merken
 */
class Merken_Test extends WP_UnitTestCase {

	public function test_naar_array_zet_kommagescheiden_waarde_om() {
		$this->assertSame( array( 'HARDI', 'Vaderstad' ), HDP_Merken::naar_array( 'HARDI, Vaderstad' ) );
	}

	public function test_naar_array_trimt_spaties_en_negeert_lege_waarden() {
		$this->assertSame( array( 'HARDI', 'Vaderstad' ), HDP_Merken::naar_array( ' HARDI ,, Vaderstad ,' ) );
	}

	public function test_naar_array_van_lege_waarde_is_lege_array() {
		$this->assertSame( array(), HDP_Merken::naar_array( '' ) );
		$this->assertSame( array(), HDP_Merken::naar_array( '   ' ) );
	}

	public function test_uit_selectie_zet_array_om_naar_kommagescheiden_string() {
		$this->assertSame( 'HARDI, Vaderstad', HDP_Merken::uit_selectie( array( 'HARDI', 'Vaderstad' ) ) );
	}

	public function test_uit_selectie_filtert_onbekende_waarden_weg() {
		$this->assertSame( 'HARDI', HDP_Merken::uit_selectie( array( 'HARDI', 'Niet-bestaand merk' ) ) );
	}

	public function test_uit_selectie_van_lege_array_is_lege_string() {
		$this->assertSame( '', HDP_Merken::uit_selectie( array() ) );
	}

	public function test_lijst_bevat_geen_duplicaten() {
		$lijst = HDP_Merken::lijst();
		$this->assertSame( count( $lijst ), count( array_unique( $lijst ) ) );
	}
}
