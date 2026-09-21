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
		$this->assertSame( array( 'HARDI', 'Väderstad' ), HDP_Merken::naar_array( 'HARDI, Väderstad' ) );
	}

	public function test_naar_array_trimt_spaties_en_negeert_lege_waarden() {
		$this->assertSame( array( 'HARDI', 'Väderstad' ), HDP_Merken::naar_array( ' HARDI ,, Väderstad ,' ) );
	}

	public function test_naar_array_van_lege_waarde_is_lege_array() {
		$this->assertSame( array(), HDP_Merken::naar_array( '' ) );
		$this->assertSame( array(), HDP_Merken::naar_array( '   ' ) );
	}

	public function test_uit_selectie_zet_array_om_naar_kommagescheiden_string() {
		$this->assertSame( 'HARDI, Väderstad', HDP_Merken::uit_selectie( array( 'HARDI', 'Väderstad' ) ) );
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

	/**
	 * De twee merklijsten mogen niet uit elkaar lopen. Een merk waarop je
	 * downloads kunt taggen (HDP_Downloads_CPT::merk_opties(), ook de bron
	 * van de FileBird-mapnamen) maar dat je niet aan een dealer kunt
	 * toewijzen (HDP_Merken::lijst(), de checkboxes in wp-admin) levert
	 * bestanden op die voor elke dealer mét merkrechten onzichtbaar zijn —
	 * zonder enige melding. Omgekeerd mag wel: een merk waarvoor (nog) geen
	 * downloads bestaan.
	 */
	public function test_elk_taggbaar_merk_is_ook_aan_een_dealer_toe_te_wijzen() {
		$taggbaar = array_diff(
			HDP_Downloads_CPT::merk_opties(),
			array( HDP_Downloads_CPT::MERK_ALGEMEEN )
		);

		$onbekend = array_diff( $taggbaar, HDP_Merken::lijst() );

		$this->assertSame(
			array(),
			array_values( $onbekend ),
			'Deze merken staan wel in merk_opties() maar niet in HDP_Merken::lijst(), '
				. 'dus downloads met dit merk zijn voor dealers met merkrechten onzichtbaar: '
				. implode( ', ', $onbekend )
		);
	}

	/**
	 * "Algemeen" is bewust geen echt merk (het betekent "voor iedereen met
	 * portaaltoegang") en mag dus niet als dealerrecht aan te vinken zijn.
	 */
	public function test_algemeen_is_geen_toewijsbaar_merk() {
		$this->assertNotContains( HDP_Downloads_CPT::MERK_ALGEMEEN, HDP_Merken::lijst() );
		$this->assertSame( '', HDP_Merken::uit_selectie( array( HDP_Downloads_CPT::MERK_ALGEMEEN ) ) );
	}
}
