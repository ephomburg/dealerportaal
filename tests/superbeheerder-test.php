<?php

/**
 * Test de superbeheerder (HDP_Roles::is_superbeheerder): een beheerder van
 * Homburg (ep@) ziet en mag alles in het portaal, ook als er bij zijn account
 * merken zijn ingevuld en zonder klantnummer. Een gewone dealer blijft beperkt.
 *
 * @group hdp-superbeheerder
 */
class Superbeheerder_Test extends WP_UnitTestCase {

	private $beheerder;
	private $dealer;

	public function setUp(): void {
		parent::setUp();
		HDP_Roles::activate();
		$this->beheerder = self::factory()->user->create( array( 'role' => 'administrator', 'user_email' => 'ep@homburg.test' ) );
		// Ook met merken ingevuld: een beheerder blijft alles zien.
		update_user_meta( $this->beheerder, 'hdp_merken', 'Bogballe' );
		$this->dealer = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $this->dealer, 'hdp_goedgekeurd', '1' );
		update_user_meta( $this->dealer, 'hdp_merken', 'Bogballe' );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function test_beheerder_is_superbeheerder_en_dealer_niet() {
		$this->assertTrue( HDP_Roles::is_superbeheerder( $this->beheerder ) );
		$this->assertFalse( HDP_Roles::is_superbeheerder( $this->dealer ) );
		$this->assertFalse( HDP_Roles::is_superbeheerder( 0 ) );
		$this->assertTrue( HDP_Roles::mag_portaal_zien( $this->beheerder ) );
	}

	public function test_beheerder_ziet_downloads_van_elk_merk() {
		$download = self::factory()->post->create( array( 'post_type' => HDP_Downloads_CPT::POST_TYPE ) );
		update_post_meta( $download, '_hdp_merk', 'HARDI' );

		$this->assertTrue( HDP_Downloads_CPT::mag_merk_zien( $download, $this->beheerder ) );
		$this->assertFalse( HDP_Downloads_CPT::mag_merk_zien( $download, $this->dealer ) );
	}

	public function test_beheerder_ziet_trainingen_van_elk_merk() {
		$training = self::factory()->post->create( array( 'post_type' => HDP_Trainingen::POST_TYPE, 'post_status' => 'publish' ) );
		update_post_meta( $training, HDP_Trainingen::META_MERKEN, 'HARDI' );

		$this->assertTrue( HDP_Trainingen::mag_zien( $training, $this->beheerder ) );
		$this->assertFalse( HDP_Trainingen::mag_zien( $training, $this->dealer ) );
	}

	public function test_beheerder_ziet_de_hele_webshop() {
		$this->assertFalse( HDP_Webshop_Merken::geldt( $this->beheerder ) );
		$this->assertTrue( HDP_Webshop_Merken::geldt( $this->dealer ) );
	}

	public function test_beheerder_kan_op_rekening_bestellen_zonder_klantnummer() {
		update_option( HDP_PowerAll_Orders::OPTION, array( 'modus' => 'aan' ) );
		$this->assertTrue( HDP_PowerAll_Orders::mag_op_rekening( $this->beheerder ) );
		$this->assertFalse( HDP_PowerAll_Orders::mag_op_rekening( $this->dealer ), 'Een dealer zonder klantnummer niet.' );
	}

	public function test_beheerder_kan_de_outlet_bestellen() {
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is niet geladen.' );
		}
		update_option( HDP_PowerAll_Orders::OPTION, array( 'modus' => 'aan' ) );
		$outlet = new WC_Product_Simple();
		$outlet->set_regular_price( '100' );
		$outlet->save();

		wp_set_current_user( $this->beheerder );
		$this->assertTrue( HDP_PowerAll_Orders::buiten_powerall_bestelbaar( true, $outlet ) );
		wp_set_current_user( $this->dealer );
		$this->assertFalse( HDP_PowerAll_Orders::buiten_powerall_bestelbaar( true, $outlet ) );
	}

	public function test_beheerder_kan_collegas_beheren() {
		$this->assertTrue( HDP_Collegas::mag_beheren( $this->beheerder ) );
	}

	public function test_bestelling_van_beheerder_gaat_niet_naar_powerall_met_duidelijke_reden() {
		if ( ! function_exists( 'wc_create_order' ) ) {
			$this->markTestSkipped( 'WooCommerce is niet geladen.' );
		}
		$order = wc_create_order( array( 'customer_id' => $this->beheerder ) );
		$fout  = HDP_PowerAll_Orders::bouw( $order );
		$this->assertWPError( $fout );
		$this->assertStringContainsString( 'beheerder zonder klantnummer', $fout->get_error_message() );
	}
}
