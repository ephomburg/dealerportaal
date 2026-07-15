<?php

/**
 * @group hdp-roles
 */
class Roles_Test extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		// De 'dealer'-rol + capability bestaan pas na activatie; die draait
		// normaal via register_activation_hook(), wat in de testomgeving niet
		// vanzelf gebeurt.
		HDP_Roles::activate();
	}

	public function test_beheerder_mag_altijd_ook_zonder_goedkeuring() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );

		$this->assertTrue( HDP_Roles::mag_portaal_zien( $admin_id ) );
	}

	public function test_goedgekeurde_dealer_mag_zien() {
		$dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $dealer_id, 'hdp_goedgekeurd', '1' );

		$this->assertTrue( HDP_Roles::mag_portaal_zien( $dealer_id ) );
	}

	public function test_niet_goedgekeurde_dealer_mag_niet_zien() {
		$dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );

		$this->assertFalse( HDP_Roles::mag_portaal_zien( $dealer_id ) );
	}

	public function test_gebruiker_zonder_dealerrol_mag_niet_zien_zelfs_met_goedkeuring_meta() {
		// Iemand met alleen de standaard 'subscriber'-rol heeft de
		// hdp_bekijk_portaal-capability niet, ook niet als de meta per
		// ongeluk toch op '1' zou staan.
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		update_user_meta( $subscriber_id, 'hdp_goedgekeurd', '1' );

		$this->assertFalse( HDP_Roles::mag_portaal_zien( $subscriber_id ) );
	}

	public function test_niet_bestaande_gebruiker_mag_niet_zien() {
		$this->assertFalse( HDP_Roles::mag_portaal_zien( 0 ) );
	}
}
