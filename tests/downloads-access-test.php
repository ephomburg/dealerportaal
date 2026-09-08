<?php

/**
 * Test HDP_Downloads_CPT::resolve_download() — de testbare kern van het
 * downloadendpoint, los van de headers/readfile()/exit in
 * handle_download() zelf (exit is niet aan te roepen binnen PHPUnit).
 *
 * @group hdp-downloads
 */
class Downloads_Access_Test extends WP_UnitTestCase {

	private $goedgekeurde_dealer_id;
	private $niet_goedgekeurde_dealer_id;
	private $download_met_bestand_id;
	private $download_zonder_bestand_id;

	public function setUp(): void {
		parent::setUp();
		HDP_Roles::activate();

		$this->goedgekeurde_dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $this->goedgekeurde_dealer_id, 'hdp_goedgekeurd', '1' );

		$this->niet_goedgekeurde_dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );

		$this->download_zonder_bestand_id = self::factory()->post->create(
			array( 'post_type' => HDP_Downloads_CPT::POST_TYPE )
		);

		// wp_tempnam() geeft een bestand zonder (toegestane) extensie, wat
		// wp_upload_bits() in create_upload_object() zou laten falen — vandaar
		// hier een eigen .txt-bestand.
		$tmp_bestand = get_temp_dir() . 'hdp-test-' . wp_generate_password( 12, false ) . '.txt';
		file_put_contents( $tmp_bestand, 'testinhoud' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- alleen in de testsuite, geen productiecode.
		$attachment_id = self::factory()->attachment->create_upload_object( $tmp_bestand );

		$this->download_met_bestand_id = self::factory()->post->create(
			array( 'post_type' => HDP_Downloads_CPT::POST_TYPE )
		);
		update_post_meta( $this->download_met_bestand_id, '_hdp_attachment_id', $attachment_id );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function test_niet_ingelogd_krijgt_403() {
		$this->expectException( WPDieException::class );
		$this->expectExceptionCode( 403 );

		HDP_Downloads_CPT::resolve_download( $this->download_met_bestand_id );
	}

	public function test_niet_goedgekeurde_dealer_krijgt_403() {
		wp_set_current_user( $this->niet_goedgekeurde_dealer_id );

		$this->expectException( WPDieException::class );
		$this->expectExceptionCode( 403 );

		HDP_Downloads_CPT::resolve_download( $this->download_met_bestand_id );
	}

	public function test_niet_bestaande_download_geeft_404() {
		wp_set_current_user( $this->goedgekeurde_dealer_id );

		$this->expectException( WPDieException::class );
		$this->expectExceptionCode( 404 );

		HDP_Downloads_CPT::resolve_download( 999999999 );
	}

	public function test_download_zonder_gekoppeld_bestand_geeft_404() {
		wp_set_current_user( $this->goedgekeurde_dealer_id );

		$this->expectException( WPDieException::class );
		$this->expectExceptionCode( 404 );

		HDP_Downloads_CPT::resolve_download( $this->download_zonder_bestand_id );
	}

	public function test_goedgekeurde_dealer_krijgt_bestandsgegevens_en_telt_mee() {
		wp_set_current_user( $this->goedgekeurde_dealer_id );

		$this->assertSame( 0, HDP_Downloads_CPT::get_download_teller( $this->download_met_bestand_id ) );

		$bestand = HDP_Downloads_CPT::resolve_download( $this->download_met_bestand_id );

		$this->assertSame( $this->download_met_bestand_id, $bestand['post_id'] );
		$this->assertFileExists( $bestand['pad'] );
		$this->assertSame( 1, HDP_Downloads_CPT::get_download_teller( $this->download_met_bestand_id ) );

		// Nogmaals downloaden telt verder op.
		HDP_Downloads_CPT::resolve_download( $this->download_met_bestand_id );
		$this->assertSame( 2, HDP_Downloads_CPT::get_download_teller( $this->download_met_bestand_id ) );
	}

	public function test_beheerder_mag_ook_zonder_goedkeuring_downloaden() {
		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$bestand = HDP_Downloads_CPT::resolve_download( $this->download_met_bestand_id );

		$this->assertSame( $this->download_met_bestand_id, $bestand['post_id'] );
	}

	public function test_categorie_zonder_meta_telt_als_download_voor_terugwaartse_compatibiliteit() {
		$this->assertSame( 'download', HDP_Downloads_CPT::get_categorie( $this->download_zonder_bestand_id ) );

		update_post_meta( $this->download_zonder_bestand_id, '_hdp_categorie', 'content' );
		$this->assertSame( 'content', HDP_Downloads_CPT::get_categorie( $this->download_zonder_bestand_id ) );
	}

	public function test_get_regios_ondersteunt_franstalig_belgie_naast_nl_en_be() {
		update_post_meta( $this->download_zonder_bestand_id, '_hdp_regios', 'nl,be,be-fr' );

		$this->assertSame( array( 'nl', 'be', 'be-fr' ), HDP_Downloads_CPT::get_regios( $this->download_zonder_bestand_id ) );
	}
}
