<?php

/**
 * Test HDP_Bestandsbeveiliging: het bestand achter een download hoort niet
 * meer rechtstreeks in de openbare uploadsmap te staan, en de bijlage hoort
 * niet meer via de openbare REST-API op te sommen te zijn.
 *
 * @group hdp-bestandsbeveiliging
 */
class Bestandsbeveiliging_Test extends WP_UnitTestCase {

	private $attachment_id;
	private $download_id;

	public function setUp(): void {
		parent::setUp();
		HDP_Roles::activate();

		$tmp_bestand = get_temp_dir() . 'hdp-beveilig-' . wp_generate_password( 12, false ) . '.txt';
		file_put_contents( $tmp_bestand, 'geheime prijslijst' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- alleen in de testsuite.
		$this->attachment_id = self::factory()->attachment->create_upload_object( $tmp_bestand );

		$this->download_id = self::factory()->post->create(
			array( 'post_type' => HDP_Downloads_CPT::POST_TYPE )
		);
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/** Koppelen van het bestand aan een download is het moment waarop het afgeschermd wordt. */
	private function koppel() {
		update_post_meta( $this->download_id, '_hdp_attachment_id', $this->attachment_id );
	}

	public function test_bestand_verhuist_bij_koppelen_naar_de_afgeschermde_map() {
		$pad_ervoor = get_attached_file( $this->attachment_id );
		$this->assertStringNotContainsString( HDP_Bestandsbeveiliging::MAP, $pad_ervoor );

		$this->koppel();

		$pad_erna = get_attached_file( $this->attachment_id );
		$this->assertStringContainsString( HDP_Bestandsbeveiliging::MAP, $pad_erna );
		$this->assertFileExists( $pad_erna );
		$this->assertFileDoesNotExist( $pad_ervoor );
		$this->assertTrue( HDP_Bestandsbeveiliging::is_beveiligd( $this->attachment_id ) );
	}

	/**
	 * Zonder deze bewakers zou de map op de live server (Apache/LiteSpeed)
	 * gewoon uitleverbaar blijven en was de verhuizing zinloos.
	 */
	public function test_afgeschermde_map_krijgt_bewakingsbestanden() {
		$this->koppel();

		$map = dirname( get_attached_file( $this->attachment_id ) );

		$this->assertFileExists( $map . '/.htaccess' );
		$this->assertFileExists( $map . '/index.php' );
		$this->assertStringContainsString( 'Require all denied', file_get_contents( $map . '/.htaccess' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- alleen in de testsuite.
	}

	/**
	 * Zodat een gedeelde of uitgelekte bijlage-URL alsnog langs de
	 * inlog- en merkcontrole gaat — dit werkt op élke webserver, ook als
	 * .htaccess genegeerd wordt.
	 */
	public function test_bijlage_url_wijst_naar_het_gecontroleerde_endpoint() {
		$this->koppel();

		$url = wp_get_attachment_url( $this->attachment_id );

		$this->assertSame( HDP_Downloads_CPT::download_url( $this->download_id ), $url );
		$this->assertStringNotContainsString( '/uploads/', $url );
	}

	public function test_gewone_bijlage_zonder_download_houdt_haar_normale_url() {
		$losse_id = self::factory()->attachment->create_upload_object(
			$this->maak_tijdelijk_bestand()
		);

		$this->assertStringContainsString( '/uploads/', wp_get_attachment_url( $losse_id ) );
		$this->assertFalse( HDP_Bestandsbeveiliging::is_beveiligd( $losse_id ) );
	}

	public function test_beveiligde_bijlage_staat_niet_in_de_openbare_rest_medialijst() {
		$this->koppel();
		wp_set_current_user( 0 );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/media' ) );
		$ids      = wp_list_pluck( $response->get_data(), 'id' );

		$this->assertNotContains( $this->attachment_id, $ids );
	}

	public function test_beheerder_ziet_de_bijlage_wel_gewoon_in_de_rest_medialijst() {
		$this->koppel();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/media' ) );
		$ids      = wp_list_pluck( $response->get_data(), 'id' );

		$this->assertContains( $this->attachment_id, $ids );
	}

	public function test_losse_rest_opvraag_geeft_geen_bestands_url_aan_bezoekers() {
		$this->koppel();
		wp_set_current_user( 0 );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/media/' . $this->attachment_id ) );
		$data     = $response->get_data();

		$this->assertArrayNotHasKey( 'source_url', $data );
		$this->assertArrayNotHasKey( 'guid', $data );
		// Met de bestandsnaam plus de (vaste) mapnaam is de directe URL
		// anders alsnog samen te stellen.
		$this->assertArrayNotHasKey( 'filename', $data );
	}

	/**
	 * De .htaccess in de afgeschermde map werkt alleen op Apache/LiteSpeed;
	 * nginx negeert hem stilzwijgend. De zelfcontrole moet dat verschil
	 * opmerken, anders lijkt alles veilig terwijl het dat niet is.
	 */
	public function test_zelfcontrole_merkt_op_dat_de_map_openbaar_is() {
		$this->koppel();
		delete_transient( 'hdp_beveiliging_controle_gedaan' );
		delete_option( HDP_Bestandsbeveiliging::CONTROLE_OPTIE );

		// De testomgeving doet geen echte HTTP-requests; hier een server die
		// het controlebestand netjes uitlevert — precies het geval dat een
		// waarschuwing moet opleveren.
		add_filter( 'pre_http_request', array( $this, 'antwoord_200' ) );
		HDP_Bestandsbeveiliging::controleer_afscherming();
		remove_filter( 'pre_http_request', array( $this, 'antwoord_200' ) );

		$this->assertSame( 'openbaar', get_option( HDP_Bestandsbeveiliging::CONTROLE_OPTIE ) );
	}

	public function test_zelfcontrole_is_tevreden_als_de_map_dichtstaat() {
		$this->koppel();
		delete_transient( 'hdp_beveiliging_controle_gedaan' );
		delete_option( HDP_Bestandsbeveiliging::CONTROLE_OPTIE );

		add_filter( 'pre_http_request', array( $this, 'antwoord_403' ) );
		HDP_Bestandsbeveiliging::controleer_afscherming();
		remove_filter( 'pre_http_request', array( $this, 'antwoord_403' ) );

		$this->assertSame( 'afgeschermd', get_option( HDP_Bestandsbeveiliging::CONTROLE_OPTIE ) );
	}

	public function antwoord_200() {
		return array(
			'response' => array( 'code' => 200 ),
			'body'     => 'controlebestand beveiliging',
		);
	}

	public function antwoord_403() {
		return array(
			'response' => array( 'code' => 403 ),
			'body'     => '',
		);
	}

	/** Het endpoint blijft het bestand gewoon uitleveren na de verhuizing. */
	public function test_goedgekeurde_dealer_krijgt_het_verhuisde_bestand_nog_steeds() {
		$this->koppel();

		$dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $dealer_id, 'hdp_goedgekeurd', '1' );
		wp_set_current_user( $dealer_id );

		$bestand = HDP_Downloads_CPT::resolve_download( $this->download_id );

		$this->assertFileExists( $bestand['pad'] );
		$this->assertSame( 'geheime prijslijst', file_get_contents( $bestand['pad'] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- alleen in de testsuite.
	}

	/** Voorbeelden in de mediabibliotheek mogen de dealerteller niet vervuilen. */
	public function test_download_door_beheerder_telt_niet_mee() {
		$this->koppel();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		HDP_Downloads_CPT::resolve_download( $this->download_id );

		$this->assertSame( 0, HDP_Downloads_CPT::get_download_teller( $this->download_id ) );
	}

	private function maak_tijdelijk_bestand() {
		$pad = get_temp_dir() . 'hdp-los-' . wp_generate_password( 12, false ) . '.txt';
		file_put_contents( $pad, 'openbaar bestand' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- alleen in de testsuite.
		return $pad;
	}
}
