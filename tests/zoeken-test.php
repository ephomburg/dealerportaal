<?php

/**
 * Test de zoekbalk in de header.
 *
 * Het gevoelige punt is zichtbaarheid: de zoekbalk mag niets laten zien
 * wat de dealer op de pagina zelf ook niet ziet. Een download voor een
 * ander merk, of de bestelling van een andere dealer, hoort hier dus niet
 * tussen te staan.
 *
 * @group hdp-zoeken
 */
class Zoeken_Test extends WP_UnitTestCase {

	private $dealer_id;

	public function setUp(): void {
		parent::setUp();
		HDP_Roles::activate();

		$this->dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $this->dealer_id, 'hdp_goedgekeurd', '1' );
		wp_set_current_user( $this->dealer_id );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/** Alle titels van één groep, of een lege lijst als die groep er niet is. */
	private function titels( $resultaten, $sleutel ) {
		foreach ( $resultaten['groepen'] as $groep ) {
			if ( $sleutel === $groep['sleutel'] ) {
				return wp_list_pluck( $groep['items'], 'titel' );
			}
		}
		return array();
	}

	public function test_hoofdletters_spaties_en_streepjes_tellen_niet_mee() {
		$this->assertTrue( HDP_Zoeken::past( '100 13325', array( '100-13325-715' ) ) );
		$this->assertTrue( HDP_Zoeken::past( 'HARDI', array( 'Hardi Machines' ) ) );
		$this->assertTrue( HDP_Zoeken::past( 'equipe', array( 'Équipe' ) ) );
		$this->assertFalse( HDP_Zoeken::past( 'vaderstad', array( 'Hardi', '' ) ) );
		$this->assertFalse( HDP_Zoeken::past( ' - ', array( 'iets' ) ), 'Een term van alleen leestekens vindt niet alles.' );
	}

	public function test_te_korte_zoekterm_geeft_niets() {
		$resultaten = HDP_Zoeken::resultaten( 'a' );
		$this->assertSame( array(), $resultaten['groepen'] );
	}

	public function test_vindt_een_zichtbare_download_op_titel() {
		$id = self::factory()->post->create( array( 'post_type' => HDP_Downloads_CPT::POST_TYPE, 'post_title' => 'Prijslijst onderdelen 2026' ) );
		update_post_meta( $id, '_hdp_categorie', 'download' );

		$this->assertContains( 'Prijslijst onderdelen 2026', $this->titels( HDP_Zoeken::resultaten( 'prijslijst' ), 'downloads' ) );
	}

	public function test_download_voor_een_ander_merk_blijft_verborgen() {
		$id = self::factory()->post->create( array( 'post_type' => HDP_Downloads_CPT::POST_TYPE, 'post_title' => 'Prijslijst geheim merk' ) );
		update_post_meta( $id, '_hdp_categorie', 'download' );
		update_post_meta( $id, '_hdp_merk', 'Merk dat deze dealer niet voert' );
		// Een dealer zonder merkenlijst ziet alles; deze voert alleen Hardi.
		update_user_meta( $this->dealer_id, 'hdp_merken', 'Hardi' );

		// Dezelfde controle als de downloadspagina; daar moet hij ook weg zijn.
		$this->assertFalse( HDP_Downloads_CPT::mag_merk_zien( $id ) );
		$this->assertNotContains( 'Prijslijst geheim merk', $this->titels( HDP_Zoeken::resultaten( 'geheim' ), 'downloads' ) );
	}

	public function test_alleen_eigen_bestellingen() {
		if ( ! function_exists( 'wc_create_order' ) ) {
			$this->markTestSkipped( 'WooCommerce is niet geladen.' );
		}

		$eigen = wc_create_order( array( 'customer_id' => $this->dealer_id ) );
		$eigen->save();

		$ander_id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		$ander    = wc_create_order( array( 'customer_id' => $ander_id ) );
		$ander->save();

		$eigen_titels = $this->titels( HDP_Zoeken::resultaten( (string) $eigen->get_order_number() ), 'bestellingen' );
		$this->assertCount( 1, $eigen_titels );

		$this->assertSame( array(), $this->titels( HDP_Zoeken::resultaten( (string) $ander->get_order_number() ), 'bestellingen' ) );
	}

	public function test_menuonderdeel_op_naam() {
		if ( ! function_exists( 'wc_get_account_endpoint_url' ) ) {
			$this->markTestSkipped( 'WooCommerce is niet geladen.' );
		}
		$this->assertContains( HDP_I18N::t( 'zijbalk_bestellingen' ), $this->titels( HDP_Zoeken::resultaten( 'bestel' ), 'paginas' ) );
	}
}
