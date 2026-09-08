<?php

/**
 * Test dat een dealer een goedkeuringsmail krijgt bij de overgang van
 * niet-goedgekeurd naar goedgekeurd — vanuit zowel het front-end
 * gebruikersoverzicht (HDP_Admin_Upload) als het wp-admin-gebruikersprofiel
 * (HDP_User_Fields) — en NIET bij een herhaalde opslag van een reeds
 * goedgekeurde dealer. Vangt wp_mail()-aanroepen af via het 'wp_mail'-filter
 * i.p.v. daadwerkelijk te versturen (geen mailserver in de testomgeving).
 *
 * @group hdp-goedkeuringsmail
 */
class Goedkeuringsmail_Test extends WP_UnitTestCase {

	private $verzonden_mails = array();

	public function setUp(): void {
		parent::setUp();
		HDP_Roles::activate();

		$this->verzonden_mails = array();
		add_filter( 'wp_mail', array( $this, 'vang_mail_op' ) );
	}

	public function tearDown(): void {
		remove_filter( 'wp_mail', array( $this, 'vang_mail_op' ) );
		parent::tearDown();
	}

	public function vang_mail_op( $args ) {
		$this->verzonden_mails[] = $args;
		return $args;
	}

	// ---- Via HDP_Admin_Upload (gebruikersoverzicht) --------------------

	public function test_goedkeuring_via_gebruikersoverzicht_stuurt_mail() {
		$dealer_id = self::factory()->user->create( array( 'role' => 'dealer', 'user_email' => 'dealer@example.test' ) );

		HDP_Admin_Upload::verwerk_gebruiker_bijwerken_kern(
			array(
				'hdp_gebruiker_id' => $dealer_id,
				'hdp_goedgekeurd'  => '1',
			),
			home_url( '/adminportaal/' )
		);

		$this->assertCount( 1, $this->verzonden_mails );
		$this->assertSame( 'dealer@example.test', $this->verzonden_mails[0]['to'] );
	}

	public function test_opnieuw_opslaan_van_goedgekeurde_dealer_stuurt_geen_mail() {
		$dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $dealer_id, 'hdp_goedgekeurd', '1' );

		HDP_Admin_Upload::verwerk_gebruiker_bijwerken_kern(
			array(
				'hdp_gebruiker_id' => $dealer_id,
				'hdp_goedgekeurd'  => '1',
				'hdp_merken'       => array( 'HARDI' ),
			),
			home_url( '/adminportaal/' )
		);

		$this->assertCount( 0, $this->verzonden_mails );
	}

	public function test_afkeuren_stuurt_geen_mail() {
		$dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );

		HDP_Admin_Upload::verwerk_gebruiker_bijwerken_kern(
			array( 'hdp_gebruiker_id' => $dealer_id ),
			home_url( '/adminportaal/' )
		);

		$this->assertCount( 0, $this->verzonden_mails );
	}

	// ---- Via HDP_User_Fields (wp-admin-gebruikersprofiel) --------------

	public function test_goedkeuring_via_wp_admin_profiel_stuurt_mail() {
		$dealer_id = self::factory()->user->create( array( 'role' => 'dealer', 'user_email' => 'wpadmin-dealer@example.test' ) );

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$_POST['hdp_user_fields_nonce'] = wp_create_nonce( 'hdp_user_fields' );
		$_POST['hdp_goedgekeurd']       = '1';

		HDP_User_Fields::save_fields( $dealer_id );

		unset( $_POST['hdp_user_fields_nonce'], $_POST['hdp_goedgekeurd'] );
		wp_set_current_user( 0 );

		$this->assertCount( 1, $this->verzonden_mails );
		$this->assertSame( 'wpadmin-dealer@example.test', $this->verzonden_mails[0]['to'] );
	}
}
