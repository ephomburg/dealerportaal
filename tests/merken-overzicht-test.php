<?php

/**
 * Test Gebruikers > Merken per dealer (HDP_Merken_Overzicht).
 *
 * Wat hier fout kan gaan: een collega of een beheerder komt in het overzicht,
 * een onbekend merk wordt opgeslagen, of een collega met hetzelfde
 * klantnummer loopt niet mee.
 *
 * @group hdp-merken
 */
class Merken_Overzicht_Test extends WP_UnitTestCase {

	private function dealer( $bedrijf, $email ) {
		$id = self::factory()->user->create( array( 'role' => 'dealer', 'user_email' => $email ) );
		update_user_meta( $id, 'hdp_goedgekeurd', '1' );
		update_user_meta( $id, 'billing_company', $bedrijf );
		return $id;
	}

	public function setUp(): void {
		parent::setUp();
		HDP_Roles::activate();
		add_filter( 'pre_wp_mail', '__return_true' );
	}

	public function tearDown(): void {
		remove_filter( 'pre_wp_mail', '__return_true' );
		parent::tearDown();
	}

	public function test_alleen_dealers_zonder_collegas_op_bedrijfsnaam() {
		$b     = $this->dealer( 'Bakker Mechanisatie', 'info@bakker.test' );
		$a     = $this->dealer( 'Agro Jansen', 'info@jansen.test' );
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$col   = HDP_Collegas::voeg_toe( $a, 'Frits', 'frits@jansen.test', array() );

		$ids = wp_list_pluck( HDP_Merken_Overzicht::dealers(), 'ID' );
		$this->assertSame( array( $a, $b ), array_map( 'intval', $ids ) );
		$this->assertNotContains( $admin, $ids );
		$this->assertNotContains( $col->ID, $ids );
	}

	public function test_opslaan_alleen_geldige_merken_en_telt_wijzigingen() {
		$a = $this->dealer( 'Agro Jansen', 'info@jansen.test' );
		$b = $this->dealer( 'Bakker Mechanisatie', 'info@bakker.test' );
		update_user_meta( $b, 'hdp_merken', 'HARDI' );

		$n = HDP_Merken_Overzicht::sla_op( array( $a => 'Väderstad, Bogballe, Verzonnen', $b => 'HARDI' ) );

		$this->assertSame( 1, $n, 'Alleen de dealer die echt veranderde.' );
		$this->assertSame( 'Väderstad, Bogballe', get_user_meta( $a, 'hdp_merken', true ), 'Het verzonnen merk valt weg.' );
		$this->assertSame( 'HARDI', get_user_meta( $b, 'hdp_merken', true ) );

		HDP_Merken_Overzicht::sla_op( array( $b => '' ) );
		$this->assertSame( '', get_user_meta( $b, 'hdp_merken', true ), 'Alles uit kan ook.' );
	}

	public function test_geen_collega_of_beheerder_via_het_formulier() {
		$a     = $this->dealer( 'Agro Jansen', 'info@jansen.test' );
		$col   = HDP_Collegas::voeg_toe( $a, 'Frits', 'frits@jansen.test', array() );
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->assertSame( 0, HDP_Merken_Overzicht::sla_op( array( $col->ID => 'HARDI', $admin => 'HARDI' ) ) );
		$this->assertSame( '', (string) get_user_meta( $admin, 'hdp_merken', true ) );
	}

	public function test_collega_met_hetzelfde_klantnummer_loopt_mee() {
		$a = $this->dealer( 'Agro Jansen', 'info@jansen.test' );
		$c = $this->dealer( 'Agro Jansen werkplaats', 'werkplaats@jansen.test' );
		update_user_meta( $a, HDP_PowerAll_Dealers::META_RELATIE, '23524' );
		update_user_meta( $c, HDP_PowerAll_Dealers::META_RELATIE, '23524' );

		HDP_Merken_Overzicht::sla_op( array( $a => 'Väderstad' ) );
		$this->assertSame( 'Väderstad', get_user_meta( $c, 'hdp_merken', true ) );
	}
}
