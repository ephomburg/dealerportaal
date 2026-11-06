<?php

/**
 * Test de dealers uit PowerAll (HDP_PowerAll_Dealers).
 *
 * Wat hier fout kan gaan: accounts voor klanten die niet zijn aangewezen, een
 * beheerdersaccount dat aan een klant wordt gekoppeld, dubbele accounts, een
 * mail zonder dat iemand dat wilde, of een wachtwoordlink die vaker, te laat of
 * door iemand anders te gebruiken is.
 *
 * PowerAll wordt hier nagebootst; er gaat geen verzoek naar buiten.
 *
 * @group hdp-powerall
 */
class PowerAll_Dealers_Test extends WP_UnitTestCase {

	private $mails     = array();
	private $verzoeken = array();
	private $relaties  = array();
	private $storing   = false;

	public static function wpSetUpBeforeClass() {
		if ( ! defined( 'HDP_POWERALL_URL' ) ) {
			define( 'HDP_POWERALL_URL', 'https://powerall.test/v1' );
			define( 'HDP_POWERALL_TENANT', 'test' );
			define( 'HDP_POWERALL_TOKEN', 'geen-echte-sleutel' );
		}
	}

	public function setUp(): void {
		parent::setUp();
		HDP_Roles::activate();
		delete_option( HDP_PowerAll_Dealers::OPTION );
		$this->mails     = array();
		$this->verzoeken = array();
		$this->storing   = false;
		$this->relaties  = array(
			23524 => $this->relatie( 23524, 'Test 1', 'dealer1@voorbeeld.test' ),
		);
		add_filter( 'pre_http_request', array( $this, 'nep_powerall' ), 10, 3 );
		add_filter( 'pre_wp_mail', array( $this, 'vang_mail' ), 10, 2 );
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this, 'nep_powerall' ), 10 );
		remove_filter( 'pre_wp_mail', array( $this, 'vang_mail' ), 10 );
		$_GET = array();
		parent::tearDown();
	}

	private function relatie( $code, $naam, $email, $extra = array() ) {
		return $extra + array(
			'RelationCode'          => $code,
			'Name1'                 => $naam,
			'EmailAddress'          => $email,
			'AddressLine'           => 'Dorpsstraat 1',
			'ZipCode'               => '9051 BM',
			'Town'                  => 'Stiens',
			'Phone'                 => '058 123 45 67',
			'Active'                => true,
			'AcceptOrdersForDebtor' => 'Accept',
		);
	}

	public function nep_powerall( $niets, $args, $url ) {
		$this->assertSame( 'powerall.test', wp_parse_url( $url, PHP_URL_HOST ), 'Een test mag nooit naar de echte PowerAll.' );
		$this->assertSame( 'GET', $args['method'] ?? 'GET', 'Uit PowerAll wordt alleen gelezen.' );
		$this->verzoeken[] = $url;
		if ( $this->storing ) {
			return array( 'response' => array( 'code' => 503, 'message' => '' ), 'body' => '', 'headers' => array(), 'cookies' => array() );
		}
		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		preg_match_all( '/"(\d+)"/', $query['filters'] ?? '', $codes );
		$data = array_values( array_intersect_key( $this->relaties, array_flip( array_map( 'intval', $codes[1] ) ) ) );
		return array( 'response' => array( 'code' => 200, 'message' => 'OK' ), 'body' => wp_json_encode( array( 'Data' => $data ) ), 'headers' => array(), 'cookies' => array() );
	}

	public function vang_mail( $niets, $mail ) {
		$this->mails[] = $mail;
		return true;
	}

	private function stel_in( $klantnummers, $auto = false ) {
		update_option( HDP_PowerAll_Dealers::OPTION, array( 'klantnummers' => $klantnummers, 'auto_uitnodigen' => $auto ? '1' : '' ) );
	}

	private function link_uit_mail() {
		$this->assertNotEmpty( $this->mails, 'Er is geen uitnodiging verstuurd.' );
		preg_match( '#https?://\S*hdp_welkom=(\d+)&sleutel=(\w+)#', end( $this->mails )['message'], $m );
		$this->assertNotEmpty( $m, 'Er staat geen link in de uitnodiging.' );
		return array( (int) $m[1], $m[2] );
	}

	public function test_zonder_klantnummers_gebeurt_er_niets() {
		$verslag = HDP_PowerAll_Dealers::ophalen();

		$this->assertNotSame( '', $verslag['fout'] );
		$this->assertEmpty( $this->verzoeken );
		$this->assertNull( HDP_PowerAll_Dealers::gebruiker_bij_klantnummer( 23524 ) );
	}

	public function test_maakt_goedgekeurd_dealeraccount_zonder_mail() {
		$this->stel_in( '23524' );
		HDP_PowerAll_Dealers::ophalen();

		$dealer = HDP_PowerAll_Dealers::gebruiker_bij_klantnummer( 23524 );
		$this->assertInstanceOf( WP_User::class, $dealer );
		$this->assertSame( 'dealer1@voorbeeld.test', $dealer->user_email );
		$this->assertSame( array( 'dealer' ), $dealer->roles );
		$this->assertSame( 'Test 1', $dealer->display_name );
		$this->assertTrue( HDP_Roles::mag_portaal_zien( $dealer->ID ) );
		$this->assertSame( 'Stiens', get_user_meta( $dealer->ID, 'billing_city', true ) );
		$this->assertSame( 'actief', HDP_PowerAll_Dealers::status( $dealer->ID ) );
		$this->assertEmpty( $this->mails, 'Zonder "meteen uitnodigen" hoort er geen mail te gaan.' );
	}

	public function test_andere_klanten_krijgen_geen_account_en_geen_mail() {
		$this->relaties[23525] = $this->relatie( 23525, 'Test 2', 'dealer2@voorbeeld.test' );
		$this->relaties[100]   = $this->relatie( 100, 'Echte klant', 'klant@voorbeeld.test' );
		$this->stel_in( '23524', true );
		HDP_PowerAll_Dealers::ophalen();

		$this->assertNotNull( HDP_PowerAll_Dealers::gebruiker_bij_klantnummer( 23524 ) );
		$this->assertNull( HDP_PowerAll_Dealers::gebruiker_bij_klantnummer( 23525 ) );
		$this->assertNull( HDP_PowerAll_Dealers::gebruiker_bij_klantnummer( 100 ) );
		$this->assertSame( array( 'dealer1@voorbeeld.test' ), wp_list_pluck( $this->mails, 'to' ) );
		foreach ( $this->verzoeken as $url ) {
			parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
			$this->assertSame( 'RelationCode$["23524"]', $query['filters'], 'Alleen het aangewezen klantnummer wordt opgevraagd.' );
		}
	}

	public function test_tweede_keer_geen_dubbel_account_maar_bijgewerkt() {
		$this->stel_in( '23524' );
		HDP_PowerAll_Dealers::ophalen();
		$this->relaties[23524]['Town']                  = 'Leeuwarden';
		$this->relaties[23524]['AcceptOrdersForDebtor'] = 'Block';
		HDP_PowerAll_Dealers::ophalen();

		$this->assertCount( 1, get_users( array( 'meta_key' => HDP_PowerAll_Dealers::META_RELATIE ) ) );
		$dealer = HDP_PowerAll_Dealers::gebruiker_bij_klantnummer( 23524 );
		$this->assertSame( 'Leeuwarden', get_user_meta( $dealer->ID, 'billing_city', true ) );
		$this->assertSame( 'geblokkeerd', HDP_PowerAll_Dealers::status( $dealer->ID ) );
	}

	public function test_beheerder_met_zelfde_adres_wordt_niet_gekoppeld() {
		$beheerder = self::factory()->user->create( array( 'role' => 'administrator', 'user_email' => 'dealer1@voorbeeld.test' ) );
		$this->stel_in( '23524', true );
		$verslag = HDP_PowerAll_Dealers::ophalen();

		$this->assertSame( '', get_user_meta( $beheerder, HDP_PowerAll_Dealers::META_RELATIE, true ) );
		$this->assertNull( HDP_PowerAll_Dealers::gebruiker_bij_klantnummer( 23524 ) );
		$this->assertStringContainsString( 'Overgeslagen', $verslag['regels'][0]['uitkomst'] );
		$this->assertEmpty( $this->mails );
	}

	public function test_bestaande_dealer_met_zelfde_adres_wordt_gekoppeld() {
		$bestaand = self::factory()->user->create( array( 'role' => 'dealer', 'user_email' => 'dealer1@voorbeeld.test' ) );
		$this->stel_in( '23524', true );
		HDP_PowerAll_Dealers::ophalen();

		$this->assertSame( $bestaand, HDP_PowerAll_Dealers::gebruiker_bij_klantnummer( 23524 )->ID );
		$this->assertEmpty( $this->mails, 'Een bestaand account krijgt geen nieuwe uitnodiging.' );
	}

	public function test_opvuller_als_adres_wordt_overgeslagen() {
		$this->relaties[23524]['EmailAddress'] = '..';
		$this->relaties[23524]['Town']         = '.';
		$this->stel_in( '23524' );
		$verslag = HDP_PowerAll_Dealers::ophalen();

		$this->assertNull( HDP_PowerAll_Dealers::gebruiker_bij_klantnummer( 23524 ) );
		$this->assertStringContainsString( 'geen geldig e-mailadres', $verslag['regels'][0]['uitkomst'] );
	}

	public function test_onbekend_klantnummer_staat_in_verslag() {
		$this->stel_in( '23524, 99999' );
		$verslag = HDP_PowerAll_Dealers::ophalen();

		$this->assertSame( array( 23524, 99999 ), wp_list_pluck( $verslag['regels'], 'code' ) );
		$this->assertSame( 'Niet gevonden in PowerAll.', $verslag['regels'][1]['uitkomst'] );
	}

	public function test_storing_verandert_niets() {
		$this->storing = true;
		$this->stel_in( '23524' );
		$verslag = HDP_PowerAll_Dealers::ophalen();

		$this->assertStringContainsString( 'PowerAll', $verslag['fout'] );
		$this->assertEmpty( $verslag['regels'] );
		$this->assertNull( HDP_PowerAll_Dealers::gebruiker_bij_klantnummer( 23524 ) );
	}

	public function test_inactieve_klant_krijgt_geen_uitnodiging() {
		$this->relaties[23524]['Active'] = false;
		$this->stel_in( '23524', true );
		HDP_PowerAll_Dealers::ophalen();

		$this->assertSame( 'inactief', HDP_PowerAll_Dealers::status( HDP_PowerAll_Dealers::gebruiker_bij_klantnummer( 23524 )->ID ) );
		$this->assertEmpty( $this->mails );
	}

	public function test_uitnodiging_en_wachtwoord_kiezen() {
		$this->stel_in( '23524', true );
		HDP_PowerAll_Dealers::ophalen();
		list( $id, $sleutel ) = $this->link_uit_mail();

		$this->assertSame( 'dealer1@voorbeeld.test', $this->mails[0]['to'] );
		$this->assertSame( HDP_PowerAll_Dealers::gebruiker_bij_klantnummer( 23524 )->ID, $id );
		$this->assertStringContainsString( 'mot de passe', $this->mails[0]['message'] );

		$this->assertSame( 'kort', HDP_PowerAll_Dealers::zet_wachtwoord( $id, $sleutel, 'kort', 'kort' ) );
		$this->assertSame( 'verschil', HDP_PowerAll_Dealers::zet_wachtwoord( $id, $sleutel, 'Lang-genoeg-1', 'Lang-genoeg-2' ) );
		$this->assertSame( 'link', HDP_PowerAll_Dealers::zet_wachtwoord( $id, 'verkeerd', 'Lang-genoeg-1', 'Lang-genoeg-1' ) );
		$this->assertSame( 'link', HDP_PowerAll_Dealers::zet_wachtwoord( $id + 1, $sleutel, 'Lang-genoeg-1', 'Lang-genoeg-1' ), 'De sleutel hoort alleen bij dit account.' );

		$this->assertTrue( HDP_PowerAll_Dealers::zet_wachtwoord( $id, $sleutel, 'Lang-genoeg-1', 'Lang-genoeg-1' ) );
		$this->assertInstanceOf( WP_User::class, wp_authenticate( 'dealer1@voorbeeld.test', 'Lang-genoeg-1' ), 'Inloggen met het e-mailadres werkt.' );
		$this->assertSame( 'link', HDP_PowerAll_Dealers::zet_wachtwoord( $id, $sleutel, 'Nog-een-keer-1', 'Nog-een-keer-1' ), 'De link werkt maar één keer.' );
	}

	public function test_verlopen_link_werkt_niet() {
		$this->stel_in( '23524' );
		HDP_PowerAll_Dealers::ophalen();
		$dealer = HDP_PowerAll_Dealers::gebruiker_bij_klantnummer( 23524 );
		HDP_PowerAll_Dealers::nodig_uit( $dealer );
		list( $id, $sleutel ) = $this->link_uit_mail();

		$gegevens        = get_user_meta( $id, HDP_PowerAll_Dealers::META_UITNODIGING, true );
		$gegevens['tot'] = time() - 1;
		update_user_meta( $id, HDP_PowerAll_Dealers::META_UITNODIGING, $gegevens );

		$this->assertNull( HDP_PowerAll_Dealers::gebruiker_bij_link( $id, $sleutel ) );
	}

	public function test_nieuwe_uitnodiging_maakt_oude_link_ongeldig() {
		$this->stel_in( '23524' );
		HDP_PowerAll_Dealers::ophalen();
		$dealer = HDP_PowerAll_Dealers::gebruiker_bij_klantnummer( 23524 );
		HDP_PowerAll_Dealers::nodig_uit( $dealer );
		list( $id, $oud ) = $this->link_uit_mail();
		HDP_PowerAll_Dealers::nodig_uit( $dealer );
		list( , $nieuw ) = $this->link_uit_mail();

		$this->assertNull( HDP_PowerAll_Dealers::gebruiker_bij_link( $id, $oud ) );
		$this->assertNotNull( HDP_PowerAll_Dealers::gebruiker_bij_link( $id, $nieuw ) );
	}

	public function test_scherm_via_de_link() {
		$this->stel_in( '23524' );
		HDP_PowerAll_Dealers::ophalen();
		HDP_PowerAll_Dealers::nodig_uit( HDP_PowerAll_Dealers::gebruiker_bij_klantnummer( 23524 ) );
		list( $id, $sleutel ) = $this->link_uit_mail();
		wp_set_current_user( 0 );

		$_GET = array( 'hdp_welkom' => (string) $id, 'sleutel' => $sleutel );
		ob_start();
		HDP_Login::render_login( '', array( 'heroAfbeelding' => '' ) );
		$goed = ob_get_clean();
		$this->assertStringContainsString( 'name="wachtwoord_herhaal"', $goed );
		$this->assertStringContainsString( 'dealer1@voorbeeld.test', $goed );
		$this->assertStringNotContainsString( 'hdp_login_nonce', $goed );

		$_GET['sleutel'] = 'verkeerd';
		ob_start();
		HDP_Login::render_login( '', array( 'heroAfbeelding' => '' ) );
		$fout = ob_get_clean();
		$this->assertStringNotContainsString( 'name="wachtwoord_herhaal"', $fout );
		$this->assertStringContainsString( HDP_I18N::t( 'welkom_verlopen' ), $fout );
	}
}
