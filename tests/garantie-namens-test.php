<?php

/**
 * Homburg-medewerkers (Erik, Gerard) doen garantie namens elke dealer
 * (2026-11-13).
 *
 * @group hdp-garantie-namens
 */
class Garantie_Namens_Test extends WP_UnitTestCase {

	private $dealer;
	private $collega;
	private $medewerker;
	private $beheerder;

	public function setUp(): void {
		parent::setUp();
		$this->dealer = self::factory()->user->create( array( 'role' => 'dealer', 'display_name' => 'Loonbedrijf Kamps' ) );
		update_user_meta( $this->dealer, 'hdp_goedgekeurd', '1' );
		update_user_meta( $this->dealer, 'billing_company', 'Kamps BV' );

		$this->collega = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $this->collega, 'hdp_goedgekeurd', '1' );
		update_user_meta( $this->collega, HDP_Collegas::META_HOOFD, $this->dealer );

		$this->medewerker = self::factory()->user->create( array( 'role' => 'dealer', 'display_name' => 'Gerard' ) );
		update_user_meta( $this->medewerker, 'hdp_goedgekeurd', '1' );

		$this->beheerder = self::factory()->user->create( array( 'role' => 'administrator', 'display_name' => 'Erik Postma' ) );
	}

	public function test_wie_mag_namens_werken() {
		$this->assertTrue( HDP_Garantie::mag_namens( $this->beheerder ), 'Beheerders altijd.' );
		$this->assertFalse( HDP_Garantie::mag_namens( $this->dealer ) );
		update_user_meta( $this->medewerker, HDP_Garantie::META_MAG_NAMENS, '1' );
		$this->assertTrue( HDP_Garantie::mag_namens( $this->medewerker ), 'Met het vinkje bij het account.' );
	}

	public function test_alleen_hoofdaccounts_van_dealers_zijn_te_kiezen() {
		$dealers = HDP_Garantie::kiesbare_dealers();
		$this->assertArrayHasKey( $this->dealer, $dealers );
		$this->assertStringContainsString( 'Kamps BV', $dealers[ $this->dealer ] );
		$this->assertArrayNotHasKey( $this->collega, $dealers, 'Een collega werkt al voor zijn bedrijf.' );
		$this->assertArrayNotHasKey( $this->beheerder, $dealers );
	}

	public function test_zonder_gekozen_dealer_gebeurt_er_niets_op_eigen_naam() {
		wp_set_current_user( $this->beheerder );
		$this->assertSame( array(), HDP_Garantie::dealervelden() );

		$html = HDP_Garantie_Render::render_garantie_pagina( array( 'titel' => 'Garantie', 'titelFr' => '', 'omschrijving' => '', 'omschrijvingFr' => '' ) );
		$this->assertStringContainsString( 'hdp-g-namens', $html );
		$this->assertStringContainsString( HDP_I18N::t( 'garantie_namens_kies_eerst' ), $html );
		$this->assertStringNotContainsString( 'hdp-g-overzicht', $html );
	}

	public function test_met_gekozen_dealer_gaat_alles_op_diens_naam() {
		wp_set_current_user( $this->beheerder );
		$this->assertTrue( HDP_Garantie::kies_namens( $this->dealer ) );

		$velden = HDP_Garantie::dealervelden();
		$this->assertSame( (string) $this->dealer, $velden['dealer_id'] );
		$this->assertSame( 'Kamps BV', $velden['dealer_bedrijf'] );

		$opmerking = HDP_Garantie::met_indiener( 'Graag snel.' );
		$this->assertStringContainsString( 'Erik Postma (Homburg) namens de dealer', $opmerking );
		$this->assertStringContainsString( 'Graag snel.', $opmerking );
	}

	public function test_geen_onzin_kiezen_en_dealers_mogen_het_niet() {
		wp_set_current_user( $this->beheerder );
		$this->assertFalse( HDP_Garantie::kies_namens( $this->collega ) );
		$this->assertFalse( HDP_Garantie::kies_namens( 999999 ) );

		wp_set_current_user( $this->dealer );
		$this->assertFalse( HDP_Garantie::kies_namens( $this->collega ) );
		$velden = HDP_Garantie::dealervelden();
		$this->assertSame( (string) $this->dealer, $velden['dealer_id'], 'Een gewone dealer blijft gewoon zichzelf.' );
	}
}
