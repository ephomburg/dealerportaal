<?php

/**
 * Test HDP_Blocks::verwerk_inloggegevens() — de testbare kern van de
 * loginverwerking, los van de wp_safe_redirect()+exit in verwerk_login()
 * zelf (exit is niet aan te roepen binnen PHPUnit).
 *
 * We toetsen bewust op de teruggegeven bestemmings-URL en het
 * transient-gedrag i.p.v. op is_user_logged_in(): wp_signon() zet alleen
 * een auth-cookie voor een VOLGEND request, het verandert de huidige
 * gebruiker binnen hetzelfde PHP-proces niet.
 *
 * @group hdp-login
 */
class Login_Test extends WP_UnitTestCase {

	const TEST_IP = '203.0.113.5';

	private $terug_naar;

	public function setUp(): void {
		parent::setUp();
		HDP_Roles::activate();

		$_SERVER['REMOTE_ADDR'] = self::TEST_IP;
		delete_transient( $this->pogingen_sleutel() );

		self::factory()->user->create(
			array(
				'role'       => 'dealer',
				'user_login' => 'testdealer',
				'user_pass'  => 'GeheimWachtwoord123!',
			)
		);
		$dealer = get_user_by( 'login', 'testdealer' );
		update_user_meta( $dealer->ID, 'hdp_goedgekeurd', '1' );

		$this->terug_naar = home_url( '/dealerportaal/' );
	}

	public function tearDown(): void {
		delete_transient( $this->pogingen_sleutel() );
		parent::tearDown();
	}

	private function pogingen_sleutel() {
		return 'hdp_login_pogingen_' . md5( self::TEST_IP );
	}

	public function test_correcte_gegevens_geven_geen_foutmelding_en_wissen_pogingenteller() {
		set_transient( $this->pogingen_sleutel(), 2, HDP_Blocks::LOGIN_BLOKKADE_SECONDEN );

		$bestemming = HDP_Blocks::verwerk_inloggegevens( 'testdealer', 'GeheimWachtwoord123!', $this->terug_naar );

		$this->assertStringNotContainsString( 'hdp_fout', $bestemming );
		$this->assertFalse( get_transient( $this->pogingen_sleutel() ) );
	}

	public function test_onjuist_wachtwoord_geeft_foutmelding_en_telt_poging_op() {
		$bestemming = HDP_Blocks::verwerk_inloggegevens( 'testdealer', 'verkeerd-wachtwoord', $this->terug_naar );

		$this->assertStringContainsString( 'hdp_fout=1', $bestemming );
		$this->assertSame( 1, (int) get_transient( $this->pogingen_sleutel() ) );
	}

	public function test_pogingenteller_loopt_op_bij_herhaalde_mislukte_pogingen() {
		HDP_Blocks::verwerk_inloggegevens( 'testdealer', 'verkeerd-wachtwoord', $this->terug_naar );
		HDP_Blocks::verwerk_inloggegevens( 'testdealer', 'verkeerd-wachtwoord', $this->terug_naar );
		HDP_Blocks::verwerk_inloggegevens( 'testdealer', 'verkeerd-wachtwoord', $this->terug_naar );

		$this->assertSame( 3, (int) get_transient( $this->pogingen_sleutel() ) );
	}

	public function test_na_max_pogingen_wordt_geblokkeerd_ook_met_juist_wachtwoord() {
		for ( $i = 0; $i < HDP_Blocks::MAX_LOGIN_POGINGEN; $i++ ) {
			HDP_Blocks::verwerk_inloggegevens( 'testdealer', 'verkeerd-wachtwoord', $this->terug_naar );
		}

		$bestemming = HDP_Blocks::verwerk_inloggegevens( 'testdealer', 'GeheimWachtwoord123!', $this->terug_naar );

		$this->assertStringContainsString( 'hdp_fout=geblokkeerd', $bestemming );
	}

	public function test_blokkade_raakt_niet_een_ander_ip_adres() {
		for ( $i = 0; $i < HDP_Blocks::MAX_LOGIN_POGINGEN; $i++ ) {
			HDP_Blocks::verwerk_inloggegevens( 'testdealer', 'verkeerd-wachtwoord', $this->terug_naar );
		}

		$_SERVER['REMOTE_ADDR'] = '198.51.100.9';
		$bestemming             = HDP_Blocks::verwerk_inloggegevens( 'testdealer', 'GeheimWachtwoord123!', $this->terug_naar );

		$this->assertStringNotContainsString( 'hdp_fout', $bestemming );

		delete_transient( 'hdp_login_pogingen_' . md5( '198.51.100.9' ) );
	}
}
