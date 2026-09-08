<?php

/**
 * Test HDP_Account: weergavenaam/wachtwoord wijzigen (verwerk_instellingen_kern)
 * en e-mailadres wijzigen inclusief de bevestigingslink-stap
 * (verwerk_email_wijziging_kern / verwerk_email_bevestiging_kern /
 * verwerk_email_annulering_kern). Getest via de *_kern()-methodes, los van
 * de wp_safe_redirect()+exit in de echte hooks (exit is niet aan te roepen
 * binnen PHPUnit) — zelfde opzet als Login_Test.
 *
 * @group hdp-account
 */
class Account_Test extends WP_UnitTestCase {

	private $dealer_id;
	private $terug_naar;

	public function setUp(): void {
		parent::setUp();

		$this->dealer_id = self::factory()->user->create(
			array(
				'role'       => 'dealer',
				'user_login' => 'accounttestdealer',
				'user_email' => 'oud@example.test',
				'user_pass'  => 'HuidigWachtwoord123!',
			)
		);

		$this->terug_naar = home_url( '/dealerportaal/' );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function dealer() {
		// Verse WP_User ophalen i.p.v. cachen: user_pass/user_email wijzigen
		// tijdens de test, en get_userdata() zonder cache-omweg voorkomt dat
		// we per ongeluk tegen een verouderd object aan toetsen.
		clean_user_cache( $this->dealer_id );
		return get_userdata( $this->dealer_id );
	}

	// ---- Weergavenaam / wachtwoord ----------------------------------

	public function test_lege_weergavenaam_wordt_geweigerd() {
		$bestemming = HDP_Account::verwerk_instellingen_kern(
			$this->dealer(),
			array( 'weergavenaam' => '   ' ),
			$this->terug_naar
		);

		$this->assertStringContainsString( 'hdp_instellingen_status=naam_leeg', $bestemming );
	}

	public function test_weergavenaam_opslaan_zonder_wachtwoordvelden_vereist_geen_huidig_wachtwoord() {
		$bestemming = HDP_Account::verwerk_instellingen_kern(
			$this->dealer(),
			array( 'weergavenaam' => 'Nieuwe Naam' ),
			$this->terug_naar
		);

		$this->assertStringContainsString( 'hdp_instellingen_status=ok', $bestemming );
		$this->assertSame( 'Nieuwe Naam', $this->dealer()->display_name );
	}

	public function test_wachtwoord_wijzigen_met_onjuist_huidig_wachtwoord_wordt_geweigerd() {
		$oude_hash  = $this->dealer()->user_pass;
		$bestemming = HDP_Account::verwerk_instellingen_kern(
			$this->dealer(),
			array(
				'weergavenaam'             => 'Naam',
				'huidig_wachtwoord'        => 'verkeerd-wachtwoord',
				'nieuw_wachtwoord'         => 'NieuwWachtwoord123!',
				'nieuw_wachtwoord_herhaal' => 'NieuwWachtwoord123!',
			),
			$this->terug_naar
		);

		$this->assertStringContainsString( 'hdp_instellingen_status=huidig_fout', $bestemming );
		$this->assertSame( $oude_hash, $this->dealer()->user_pass );
	}

	public function test_te_kort_nieuw_wachtwoord_wordt_geweigerd() {
		$bestemming = HDP_Account::verwerk_instellingen_kern(
			$this->dealer(),
			array(
				'weergavenaam'             => 'Naam',
				'huidig_wachtwoord'        => 'HuidigWachtwoord123!',
				'nieuw_wachtwoord'         => 'kort',
				'nieuw_wachtwoord_herhaal' => 'kort',
			),
			$this->terug_naar
		);

		$this->assertStringContainsString( 'hdp_instellingen_status=te_kort', $bestemming );
	}

	public function test_niet_overeenkomende_wachtwoorden_worden_geweigerd() {
		$bestemming = HDP_Account::verwerk_instellingen_kern(
			$this->dealer(),
			array(
				'weergavenaam'             => 'Naam',
				'huidig_wachtwoord'        => 'HuidigWachtwoord123!',
				'nieuw_wachtwoord'         => 'NieuwWachtwoord123!',
				'nieuw_wachtwoord_herhaal' => 'AndersWachtwoord123!',
			),
			$this->terug_naar
		);

		$this->assertStringContainsString( 'hdp_instellingen_status=mismatch', $bestemming );
	}

	public function test_geldige_wachtwoordwijziging_wordt_doorgevoerd_en_houdt_sessie_actief() {
		wp_set_current_user( $this->dealer_id );

		$bestemming = HDP_Account::verwerk_instellingen_kern(
			$this->dealer(),
			array(
				'weergavenaam'             => 'Naam',
				'huidig_wachtwoord'        => 'HuidigWachtwoord123!',
				'nieuw_wachtwoord'         => 'NieuwWachtwoord123!',
				'nieuw_wachtwoord_herhaal' => 'NieuwWachtwoord123!',
			),
			$this->terug_naar
		);

		$this->assertStringContainsString( 'hdp_instellingen_status=ok', $bestemming );
		$this->assertTrue( wp_check_password( 'NieuwWachtwoord123!', $this->dealer()->user_pass, $this->dealer_id ) );
		// De dealer moet na de wijziging ingelogd blijven i.p.v. uitgelogd te raken.
		$this->assertSame( $this->dealer_id, get_current_user_id() );
	}

	// ---- E-mailadres wijzigen -----------------------------------------

	public function test_ongeldig_nieuw_emailadres_wordt_geweigerd() {
		$bestemming = HDP_Account::verwerk_email_wijziging_kern(
			$this->dealer(),
			array( 'nieuw_email' => 'geen-emailadres' ),
			$this->terug_naar
		);

		$this->assertStringContainsString( 'hdp_instellingen_status=email_ongeldig', $bestemming );
	}

	public function test_zelfde_emailadres_wordt_geweigerd() {
		$bestemming = HDP_Account::verwerk_email_wijziging_kern(
			$this->dealer(),
			array( 'nieuw_email' => 'oud@example.test' ),
			$this->terug_naar
		);

		$this->assertStringContainsString( 'hdp_instellingen_status=email_zelfde', $bestemming );
	}

	public function test_emailadres_van_ander_account_wordt_geweigerd() {
		self::factory()->user->create( array( 'user_email' => 'bezet@example.test' ) );

		$bestemming = HDP_Account::verwerk_email_wijziging_kern(
			$this->dealer(),
			array( 'nieuw_email' => 'bezet@example.test' ),
			$this->terug_naar
		);

		$this->assertStringContainsString( 'hdp_instellingen_status=email_in_gebruik', $bestemming );
	}

	public function test_geldige_emailwijziging_wijzigt_account_nog_niet_maar_zet_pending_meta() {
		$bestemming = HDP_Account::verwerk_email_wijziging_kern(
			$this->dealer(),
			array( 'nieuw_email' => 'nieuw@example.test' ),
			$this->terug_naar
		);

		$this->assertStringContainsString( 'hdp_instellingen_status=email_verzonden', $bestemming );
		// Het echte account-e-mailadres verandert pas na bevestiging.
		$this->assertSame( 'oud@example.test', $this->dealer()->user_email );

		$opgeslagen = get_user_meta( $this->dealer_id, 'hdp_nieuw_email', true );
		$this->assertSame( 'nieuw@example.test', $opgeslagen['email'] );
		$this->assertNotEmpty( $opgeslagen['sleutel'] );
	}

	public function test_bevestiging_met_juiste_sleutel_wijzigt_het_emailadres() {
		HDP_Account::verwerk_email_wijziging_kern( $this->dealer(), array( 'nieuw_email' => 'nieuw@example.test' ), $this->terug_naar );
		$opgeslagen = get_user_meta( $this->dealer_id, 'hdp_nieuw_email', true );

		$bestemming = HDP_Account::verwerk_email_bevestiging_kern( $this->dealer_id, $opgeslagen['sleutel'], $this->terug_naar );

		$this->assertStringContainsString( 'hdp_instellingen_status=email_bevestigd', $bestemming );
		$this->assertSame( 'nieuw@example.test', $this->dealer()->user_email );
		$this->assertSame( '', get_user_meta( $this->dealer_id, 'hdp_nieuw_email', true ) );
	}

	public function test_bevestiging_met_onjuiste_sleutel_wijzigt_niets() {
		HDP_Account::verwerk_email_wijziging_kern( $this->dealer(), array( 'nieuw_email' => 'nieuw@example.test' ), $this->terug_naar );

		$bestemming = HDP_Account::verwerk_email_bevestiging_kern( $this->dealer_id, 'onjuiste-sleutel', $this->terug_naar );

		$this->assertStringContainsString( 'hdp_instellingen_status=email_ongeldige_link', $bestemming );
		$this->assertSame( 'oud@example.test', $this->dealer()->user_email );
	}

	public function test_bevestiging_met_verlopen_sleutel_wordt_geweigerd() {
		HDP_Account::verwerk_email_wijziging_kern( $this->dealer(), array( 'nieuw_email' => 'nieuw@example.test' ), $this->terug_naar );
		$opgeslagen             = get_user_meta( $this->dealer_id, 'hdp_nieuw_email', true );
		$opgeslagen['verloopt'] = time() - 10;
		update_user_meta( $this->dealer_id, 'hdp_nieuw_email', $opgeslagen );

		$bestemming = HDP_Account::verwerk_email_bevestiging_kern( $this->dealer_id, $opgeslagen['sleutel'], $this->terug_naar );

		$this->assertStringContainsString( 'hdp_instellingen_status=email_ongeldige_link', $bestemming );
		$this->assertSame( 'oud@example.test', $this->dealer()->user_email );
	}

	public function test_annuleren_verwijdert_de_openstaande_wijziging() {
		HDP_Account::verwerk_email_wijziging_kern( $this->dealer(), array( 'nieuw_email' => 'nieuw@example.test' ), $this->terug_naar );

		$bestemming = HDP_Account::verwerk_email_annulering_kern( $this->dealer_id, $this->terug_naar );

		$this->assertStringContainsString( 'hdp_instellingen_status=email_geannuleerd', $bestemming );
		$this->assertSame( '', get_user_meta( $this->dealer_id, 'hdp_nieuw_email', true ) );
	}

	// ---- Rate limiting op e-mailwijziging ------------------------------

	public function test_email_wijziging_wordt_geblokkeerd_na_te_veel_pogingen() {
		for ( $i = 1; $i <= HDP_Account::MAX_EMAIL_POGINGEN; $i++ ) {
			$bestemming = HDP_Account::verwerk_email_wijziging_kern(
				$this->dealer(),
				array( 'nieuw_email' => "nieuw{$i}@example.test" ),
				$this->terug_naar
			);
			$this->assertStringContainsString( 'hdp_instellingen_status=email_verzonden', $bestemming );
		}

		$bestemming = HDP_Account::verwerk_email_wijziging_kern(
			$this->dealer(),
			array( 'nieuw_email' => 'nogeen@example.test' ),
			$this->terug_naar
		);

		$this->assertStringContainsString( 'hdp_instellingen_status=email_te_veel_pogingen', $bestemming );
	}

	public function test_limiet_geldt_niet_voor_een_andere_dealer() {
		for ( $i = 1; $i <= HDP_Account::MAX_EMAIL_POGINGEN; $i++ ) {
			HDP_Account::verwerk_email_wijziging_kern(
				$this->dealer(),
				array( 'nieuw_email' => "nieuw{$i}@example.test" ),
				$this->terug_naar
			);
		}

		$andere_dealer_id = self::factory()->user->create(
			array(
				'role'       => 'dealer',
				'user_email' => 'anderedealer@example.test',
			)
		);

		$bestemming = HDP_Account::verwerk_email_wijziging_kern(
			get_userdata( $andere_dealer_id ),
			array( 'nieuw_email' => 'nog-vrij@example.test' ),
			$this->terug_naar
		);

		$this->assertStringContainsString( 'hdp_instellingen_status=email_verzonden', $bestemming );
	}

	public function test_succesvolle_bevestiging_wist_de_pogingenteller() {
		for ( $i = 1; $i <= HDP_Account::MAX_EMAIL_POGINGEN; $i++ ) {
			HDP_Account::verwerk_email_wijziging_kern(
				$this->dealer(),
				array( 'nieuw_email' => "nieuw{$i}@example.test" ),
				$this->terug_naar
			);
		}
		$opgeslagen = get_user_meta( $this->dealer_id, 'hdp_nieuw_email', true );
		HDP_Account::verwerk_email_bevestiging_kern( $this->dealer_id, $opgeslagen['sleutel'], $this->terug_naar );

		$bestemming = HDP_Account::verwerk_email_wijziging_kern(
			$this->dealer(),
			array( 'nieuw_email' => 'weer-een-nieuwe@example.test' ),
			$this->terug_naar
		);

		$this->assertStringContainsString( 'hdp_instellingen_status=email_verzonden', $bestemming );
	}
}
