<?php

/**
 * Test het bijhouden van portaalgebruik.
 *
 * Wat hier fout kan gaan: beheerders die meetellen (dan zegt het overzicht
 * niets meer over dealers), en de zoekbalk die bij elke toetsaanslag een
 * halve term vastlegt.
 *
 * @group hdp-gebruik
 */
class Gebruik_Test extends WP_UnitTestCase {

	private $dealer_id;

	public function setUp(): void {
		parent::setUp();
		HDP_Roles::activate();
		delete_option( HDP_Gebruik::DB_OPTIE );
		HDP_Gebruik::controleer_tabel();

		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . HDP_Gebruik::tabel() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$this->dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $this->dealer_id, 'hdp_goedgekeurd', '1' );
		wp_set_current_user( $this->dealer_id );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function rijen( $soort ) {
		global $wpdb;
		return $wpdb->get_col( $wpdb->prepare( 'SELECT sleutel FROM ' . HDP_Gebruik::tabel() . ' WHERE soort = %s ORDER BY id', $soort ) ); // phpcs:ignore WordPress.DB
	}

	public function test_beheerder_telt_niet_mee() {
		$beheerder = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->assertFalse( HDP_Gebruik::telt_mee( $beheerder ) );

		HDP_Gebruik::noteer( 'download', '12', 0, $beheerder );
		$this->assertSame( array(), $this->rijen( 'download' ) );
	}

	public function test_niet_goedgekeurde_dealer_telt_niet_mee() {
		$wacht = self::factory()->user->create( array( 'role' => 'dealer' ) );
		$this->assertFalse( HDP_Gebruik::telt_mee( $wacht ) );
	}

	public function test_alleen_het_eindwoord_van_typen_telt() {
		HDP_Gebruik::noteer_zoekopdracht( 'har', 3 );
		HDP_Gebruik::noteer_zoekopdracht( 'hard', 3 );
		HDP_Gebruik::noteer_zoekopdracht( 'Hardi', 3 );

		$this->assertSame( array( 'hardi' ), $this->rijen( 'zoek' ) );
	}

	public function test_een_andere_term_blijft_staan() {
		HDP_Gebruik::noteer_zoekopdracht( 'hardi', 3 );
		HDP_Gebruik::noteer_zoekopdracht( 'vaderstad', 0 );

		$this->assertSame( array( 'hardi', 'vaderstad' ), $this->rijen( 'zoek' ) );
	}

	public function test_termen_zonder_resultaat() {
		HDP_Gebruik::noteer_zoekopdracht( 'prijslijst 2027', 0 );
		HDP_Gebruik::noteer_zoekopdracht( 'hardi', 4 );

		$termen = wp_list_pluck( HDP_Gebruik::zoektermen( 30, true ), 'term' );
		$this->assertSame( array( 'prijslijst 2027' ), $termen );
	}

	public function test_een_bezoek_per_dag() {
		HDP_Gebruik::noteer_bezoek();
		// Een kwartier later op dezelfde dag: nog steeds één bezoekdag.
		update_user_meta( $this->dealer_id, HDP_Gebruik::LAATST_META, time() - 20 * MINUTE_IN_SECONDS );
		if ( wp_date( 'Y-m-d', time() - 20 * MINUTE_IN_SECONDS ) !== wp_date( 'Y-m-d' ) ) {
			$this->markTestSkipped( 'Draait vlak na middernacht.' );
		}
		HDP_Gebruik::noteer_bezoek();

		$this->assertCount( 1, $this->rijen( 'bezoek' ) );
	}

	public function test_stille_dealer_op_de_lijst_actieve_niet() {
		$stil = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $stil, 'hdp_goedgekeurd', '1' );
		update_user_meta( $stil, HDP_Gebruik::LAATST_META, time() - 45 * DAY_IN_SECONDS );
		update_user_meta( $this->dealer_id, HDP_Gebruik::LAATST_META, time() - DAY_IN_SECONDS );

		$ids = wp_list_pluck( HDP_Gebruik::stille_dealers(), 'id' );
		$this->assertContains( $stil, $ids );
		$this->assertNotContains( $this->dealer_id, $ids );
	}
}
