<?php

/**
 * Test HDP_Admin_Upload::mag_gebruiken() (toegang tot het adminportaal) en
 * verwerk_gebruiker_bijwerken_kern() (opslaan vanuit het gebruikersoverzicht),
 * los van de wp_safe_redirect()+exit in de echte hook — zelfde opzet als
 * Login_Test/Account_Test.
 *
 * @group hdp-admin-upload
 */
class Admin_Upload_Access_Test extends WP_UnitTestCase {

	private $verzonden_mails = array();

	public function setUp(): void {
		parent::setUp();
		// De 'dealer'-rol bestaat pas na activatie; zie Roles_Test voor
		// dezelfde toelichting.
		HDP_Roles::activate();

		$this->verzonden_mails = array();
		add_filter( 'wp_mail', array( $this, 'vang_mail_op' ) );
	}

	public function tearDown(): void {
		remove_filter( 'wp_mail', array( $this, 'vang_mail_op' ) );
		wp_set_current_user( 0 );
		unset( $_GET['hdp_gz'] );
		parent::tearDown();
	}

	public function vang_mail_op( $args ) {
		$this->verzonden_mails[] = $args;
		return $args;
	}

	// ---- mag_gebruiken() ------------------------------------------------

	public function test_uitgelogde_bezoeker_mag_niet() {
		$this->assertFalse( HDP_Admin_Upload::mag_gebruiken() );
	}

	public function test_dealer_mag_niet() {
		$dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		wp_set_current_user( $dealer_id );

		$this->assertFalse( HDP_Admin_Upload::mag_gebruiken() );
	}

	public function test_beheerder_mag_altijd() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$this->assertTrue( HDP_Admin_Upload::mag_gebruiken() );
	}

	public function test_toegestaan_emailadres_mag_ook_zonder_beheerdersrol() {
		$dealer_id = self::factory()->user->create(
			array(
				'role'       => 'dealer',
				'user_email' => 'ep@homburg-holland.com',
			)
		);
		wp_set_current_user( $dealer_id );

		$this->assertTrue( HDP_Admin_Upload::mag_gebruiken() );
	}

	public function test_toegestaan_emailadres_is_niet_hoofdlettergevoelig() {
		$dealer_id = self::factory()->user->create(
			array(
				'role'       => 'dealer',
				'user_email' => 'EP@Homburg-Holland.com',
			)
		);
		wp_set_current_user( $dealer_id );

		$this->assertTrue( HDP_Admin_Upload::mag_gebruiken() );
	}

	public function test_ander_emailadres_zonder_beheerdersrol_mag_niet() {
		$dealer_id = self::factory()->user->create(
			array(
				'role'       => 'dealer',
				'user_email' => 'iemand-anders@homburg-holland.com',
			)
		);
		wp_set_current_user( $dealer_id );

		$this->assertFalse( HDP_Admin_Upload::mag_gebruiken() );
	}

	// ---- verwerk_gebruiker_bijwerken_kern() ------------------------------

	public function test_goedkeuring_en_merken_worden_opgeslagen() {
		$dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );

		$bestemming = HDP_Admin_Upload::verwerk_gebruiker_bijwerken_kern(
			array(
				'hdp_gebruiker_id' => $dealer_id,
				'hdp_goedgekeurd'  => '1',
				'hdp_merken'       => array( 'HARDI', 'Vaderstad' ),
			),
			home_url( '/adminportaal/' )
		);

		$this->assertStringContainsString( 'hdp_upload_status=gelukt', $bestemming );
		$this->assertSame( '1', get_user_meta( $dealer_id, 'hdp_goedgekeurd', true ) );
		$this->assertSame( 'HARDI, Vaderstad', get_user_meta( $dealer_id, 'hdp_merken', true ) );
	}

	public function test_niet_bestaand_merk_wordt_genegeerd() {
		// Beschermt tegen een handmatig samengestelde request die een
		// willekeurige waarde als merk probeert op te slaan.
		$dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );

		HDP_Admin_Upload::verwerk_gebruiker_bijwerken_kern(
			array(
				'hdp_gebruiker_id' => $dealer_id,
				'hdp_merken'       => array( 'HARDI', 'Niet-bestaand merk' ),
			),
			home_url( '/adminportaal/' )
		);

		$this->assertSame( 'HARDI', get_user_meta( $dealer_id, 'hdp_merken', true ) );
	}

	public function test_goedkeuring_uitvinken_wist_de_meta() {
		$dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $dealer_id, 'hdp_goedgekeurd', '1' );

		// Een niet-aangevinkte checkbox stuurt geen 'hdp_goedgekeurd'-veld mee.
		HDP_Admin_Upload::verwerk_gebruiker_bijwerken_kern(
			array( 'hdp_gebruiker_id' => $dealer_id ),
			home_url( '/adminportaal/' )
		);

		$this->assertSame( '', get_user_meta( $dealer_id, 'hdp_goedgekeurd', true ) );
	}

	public function test_onbekende_gebruiker_geeft_foutmelding_zonder_wijziging() {
		$bestemming = HDP_Admin_Upload::verwerk_gebruiker_bijwerken_kern(
			array( 'hdp_gebruiker_id' => 999999999 ),
			home_url( '/adminportaal/' )
		);

		$this->assertStringContainsString( 'hdp_upload_status=fout', $bestemming );
	}

	public function test_niet_dealer_gebruiker_wordt_geweigerd() {
		// Voorkomt dat via dit formulier per ongeluk (of moedwillig) een
		// willekeurig ander account — bijv. een beheerder — aangepast wordt.
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$bestemming = HDP_Admin_Upload::verwerk_gebruiker_bijwerken_kern(
			array(
				'hdp_gebruiker_id' => $subscriber_id,
				'hdp_goedgekeurd'  => '1',
			),
			home_url( '/adminportaal/' )
		);

		$this->assertStringContainsString( 'hdp_upload_status=fout', $bestemming );
		$this->assertSame( '', get_user_meta( $subscriber_id, 'hdp_goedgekeurd', true ) );
	}

	// ---- render() end-to-end --------------------------------------------

	private function render() {
		return HDP_Admin_Upload::render(
			array(
				'titel' => 'Adminportaal',
				'intro' => 'Beheer downloads en dealers.',
			)
		);
	}

	public function test_niet_toegestane_bezoeker_ziet_alleen_niet_beschikbaar() {
		$dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		wp_set_current_user( $dealer_id );

		$html = $this->render();

		$this->assertStringContainsString( 'niet beschikbaar', $html );
		$this->assertStringNotContainsString( 'hdp-gebruikers-lijst', $html );
	}

	public function test_beheerder_ziet_gebruikersoverzicht_met_dealernaam() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		self::factory()->user->create(
			array(
				'role'         => 'dealer',
				'display_name' => 'Zichtbare Dealer',
			)
		);

		$html = $this->render();

		$this->assertStringContainsString( 'hdp-gebruikers-lijst', $html );
		$this->assertStringContainsString( 'Zichtbare Dealer', $html );
	}

	public function test_zoeken_toont_alleen_matchende_dealer() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		self::factory()->user->create( array( 'role' => 'dealer', 'display_name' => 'Jansen Landbouw' ) );
		self::factory()->user->create( array( 'role' => 'dealer', 'display_name' => 'De Vries Machines' ) );

		$_GET['hdp_gz'] = 'jansen';
		$html            = $this->render();

		$this->assertStringContainsString( 'Jansen Landbouw', $html );
		$this->assertStringNotContainsString( 'De Vries Machines', $html );
	}

	public function test_zoeken_zonder_resultaat_toont_lege_staat_niet_algemene() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		self::factory()->user->create( array( 'role' => 'dealer', 'display_name' => 'Jansen Landbouw' ) );

		$_GET['hdp_gz'] = 'onvindbaar';
		$html            = $this->render();

		$this->assertStringContainsString( 'Geen dealers gevonden', $html );
	}

	// ---- verwerk_bulk_goedkeuren_kern() ----------------------------------

	public function test_bulk_goedkeuren_keurt_alle_geselecteerden_goed_en_verstuurt_mails() {
		$dealer_a = self::factory()->user->create( array( 'role' => 'dealer', 'user_email' => 'a@example.test' ) );
		$dealer_b = self::factory()->user->create( array( 'role' => 'dealer', 'user_email' => 'b@example.test' ) );

		$bestemming = HDP_Admin_Upload::verwerk_bulk_goedkeuren_kern(
			array( 'hdp_bulk_ids' => array( $dealer_a, $dealer_b ) ),
			home_url( '/adminportaal/' )
		);

		$this->assertStringContainsString( 'hdp_upload_status=gelukt', $bestemming );
		$this->assertSame( '1', get_user_meta( $dealer_a, 'hdp_goedgekeurd', true ) );
		$this->assertSame( '1', get_user_meta( $dealer_b, 'hdp_goedgekeurd', true ) );
		$this->assertCount( 2, $this->verzonden_mails );
	}

	public function test_bulk_goedkeuren_slaat_al_goedgekeurde_dealer_over_zonder_dubbele_mail() {
		$dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $dealer_id, 'hdp_goedgekeurd', '1' );

		HDP_Admin_Upload::verwerk_bulk_goedkeuren_kern(
			array( 'hdp_bulk_ids' => array( $dealer_id ) ),
			home_url( '/adminportaal/' )
		);

		$this->assertCount( 0, $this->verzonden_mails );
	}

	public function test_bulk_goedkeuren_negeert_niet_dealer_ids() {
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$bestemming = HDP_Admin_Upload::verwerk_bulk_goedkeuren_kern(
			array( 'hdp_bulk_ids' => array( $subscriber_id ) ),
			home_url( '/adminportaal/' )
		);

		$this->assertStringContainsString( 'hdp_upload_status=fout', $bestemming );
		$this->assertSame( '', get_user_meta( $subscriber_id, 'hdp_goedgekeurd', true ) );
	}

	public function test_bulk_goedkeuren_zonder_selectie_geeft_foutmelding() {
		$bestemming = HDP_Admin_Upload::verwerk_bulk_goedkeuren_kern( array(), home_url( '/adminportaal/' ) );

		$this->assertStringContainsString( 'hdp_upload_status=fout', $bestemming );
	}

	// ---- verwerk_bulk_downloads_kern() -----------------------------------

	private function maak_download( $merk = '', $regios = '' ) {
		$id = self::factory()->post->create( array( 'post_type' => HDP_Downloads_CPT::POST_TYPE ) );
		if ( $merk ) {
			update_post_meta( $id, '_hdp_merk', $merk );
		}
		if ( $regios ) {
			update_post_meta( $id, '_hdp_regios', $regios );
		}
		return $id;
	}

	public function test_bulk_merk_instellen_wijzigt_merk_maar_laat_regio_ongemoeid() {
		$download_id = $this->maak_download( 'HARDI', 'nl' );

		HDP_Admin_Upload::verwerk_bulk_downloads_kern(
			array(
				'hdp_bulk_download_ids' => array( $download_id ),
				'hdp_bulk_merk'         => 'Vaderstad',
			),
			home_url( '/adminportaal/' )
		);

		$this->assertSame( 'Vaderstad', HDP_Downloads_CPT::get_merk( $download_id ) );
		$this->assertSame( array( 'nl' ), HDP_Downloads_CPT::get_regios( $download_id ) );
	}

	public function test_bulk_regio_instellen_wijzigt_regio_maar_laat_merk_ongemoeid() {
		$download_id = $this->maak_download( 'HARDI', 'nl' );

		HDP_Admin_Upload::verwerk_bulk_downloads_kern(
			array(
				'hdp_bulk_download_ids'   => array( $download_id ),
				'hdp_bulk_regio_wijzigen' => '1',
				'hdp_bulk_regio_be_fr'    => '1',
			),
			home_url( '/adminportaal/' )
		);

		$this->assertSame( 'HARDI', HDP_Downloads_CPT::get_merk( $download_id ) );
		$this->assertSame( array( 'be-fr' ), HDP_Downloads_CPT::get_regios( $download_id ) );
	}

	public function test_bulk_regio_wijzigen_zonder_aangevinkte_regios_wist_regio() {
		$download_id = $this->maak_download( '', 'nl,be' );

		HDP_Admin_Upload::verwerk_bulk_downloads_kern(
			array(
				'hdp_bulk_download_ids'   => array( $download_id ),
				'hdp_bulk_regio_wijzigen' => '1',
			),
			home_url( '/adminportaal/' )
		);

		$this->assertSame( array(), HDP_Downloads_CPT::get_regios( $download_id ) );
	}

	public function test_bulk_merk_ongewijzigd_sentinel_laat_merk_ongemoeid() {
		$download_id = $this->maak_download( 'HARDI' );

		$bestemming = HDP_Admin_Upload::verwerk_bulk_downloads_kern(
			array(
				'hdp_bulk_download_ids' => array( $download_id ),
				'hdp_bulk_merk'         => '__ongewijzigd__',
			),
			home_url( '/adminportaal/' )
		);

		// Niets aangevinkt/gekozen om te wijzigen -> nette foutmelding i.p.v.
		// stilzwijgend niets doen.
		$this->assertStringContainsString( 'hdp_upload_status=fout', $bestemming );
		$this->assertSame( 'HARDI', HDP_Downloads_CPT::get_merk( $download_id ) );
	}

	public function test_bulk_onbekend_merk_wordt_genegeerd() {
		$download_id = $this->maak_download( 'HARDI' );

		HDP_Admin_Upload::verwerk_bulk_downloads_kern(
			array(
				'hdp_bulk_download_ids'   => array( $download_id ),
				'hdp_bulk_merk'           => 'Niet-bestaand merk',
				'hdp_bulk_regio_wijzigen' => '1',
				'hdp_bulk_regio_nl'       => '1',
			),
			home_url( '/adminportaal/' )
		);

		// Het onbekende merk wordt genegeerd, maar de (wel geldige)
		// regiowijziging gaat gewoon door.
		$this->assertSame( 'HARDI', HDP_Downloads_CPT::get_merk( $download_id ) );
		$this->assertSame( array( 'nl' ), HDP_Downloads_CPT::get_regios( $download_id ) );
	}

	public function test_bulk_zonder_selectie_geeft_foutmelding() {
		$bestemming = HDP_Admin_Upload::verwerk_bulk_downloads_kern(
			array( 'hdp_bulk_merk' => 'HARDI' ),
			home_url( '/adminportaal/' )
		);

		$this->assertStringContainsString( 'hdp_upload_status=fout', $bestemming );
	}

	public function test_bulk_negeert_niet_download_posttypes() {
		$pagina_id = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$bestemming = HDP_Admin_Upload::verwerk_bulk_downloads_kern(
			array(
				'hdp_bulk_download_ids' => array( $pagina_id ),
				'hdp_bulk_merk'         => 'HARDI',
			),
			home_url( '/adminportaal/' )
		);

		$this->assertStringContainsString( 'hdp_upload_status=fout', $bestemming );
	}
}
