<?php

/**
 * Test de trainingen naar PowerAll (HDP_Training_PowerAll).
 *
 * Wat hier fout kan gaan: een gratis training gaat toch naar PowerAll, een
 * aanmelding komt twee keer in PowerAll, de verkeerde prijs of klant, een
 * proefaanmelding gaat later alsnog echt, of een dealer zonder klantnummer
 * glipt erdoor zonder dat het team het merkt.
 *
 * PowerAll wordt nagebootst; er gaat geen verzoek naar buiten.
 *
 * @group hdp-powerall
 * @group hdp-trainingen
 */
class Training_PowerAll_Test extends WP_UnitTestCase {

	private $dealer;
	private $verzoeken = array();
	private $storing   = false;
	private $mails     = array();

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
		delete_option( HDP_Trainingen::DB_OPTIE );
		HDP_Trainingen::controleer_tabel();
		delete_option( HDP_Training_PowerAll::OPTION_TELLER );
		update_option( HDP_PowerAll_Orders::OPTION, array( 'modus' => 'aan', 'nummering' => 'portaal', 'trainingsartikel' => 'TRAINING' ) );
		update_option( HDP_Settings::OPTION, array( 'contact_email' => 'team@homburg.test' ) );

		$this->dealer = self::factory()->user->create( array( 'role' => 'dealer', 'display_name' => 'Jan Dealer', 'user_email' => 'jan@dealer.test' ) );
		update_user_meta( $this->dealer, 'hdp_goedgekeurd', '1' );
		update_user_meta( $this->dealer, 'billing_company', 'Loonbedrijf Jansen' );
		update_user_meta( $this->dealer, HDP_PowerAll_Dealers::META_RELATIE, '23524' );
		wp_set_current_user( $this->dealer );

		$this->verzoeken = array();
		$this->storing   = false;
		$this->mails     = array();
		add_filter( 'pre_http_request', array( $this, 'nep_powerall' ), 10, 3 );
		add_filter( 'pre_wp_mail', array( $this, 'vang_mail' ), 10, 2 );
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this, 'nep_powerall' ), 10 );
		remove_filter( 'pre_wp_mail', array( $this, 'vang_mail' ), 10 );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function vang_mail( $niets, $mail ) {
		$this->mails[] = $mail;
		return true;
	}

	public function nep_powerall( $niets, $args, $url ) {
		$this->assertSame( 'powerall.test', wp_parse_url( $url, PHP_URL_HOST ), 'Een test mag nooit naar de echte PowerAll.' );
		$pad               = trim( str_replace( 'https://powerall.test/v1', '', strtok( $url, '?' ) ), '/' );
		$body              = isset( $args['body'] ) ? json_decode( $args['body'], true ) : null;
		$this->verzoeken[] = array( $args['method'], $pad, $body );
		if ( $this->storing ) {
			return array( 'response' => array( 'code' => 503, 'message' => '' ), 'body' => '{}', 'headers' => array(), 'cookies' => array() );
		}
		$data = array( 'Data' => array( 'Id' => 'abc', 'EntryNumber' => $body['EntryNumber'] ?? 2260099, 'TotalNetPriceExVat' => '95.00' ) );
		return array( 'response' => array( 'code' => 200, 'message' => '' ), 'body' => wp_json_encode( $data ), 'headers' => array(), 'cookies' => array() );
	}

	private function naar( $pad ) {
		return array_values( array_filter( $this->verzoeken, static function ( $v ) use ( $pad ) { return $v[1] === $pad; } ) );
	}

	private function training( $prijs = '95' ) {
		$id = self::factory()->post->create( array( 'post_type' => HDP_Trainingen::POST_TYPE, 'post_status' => 'publish', 'post_title' => 'Service Väderstad Tempo' ) );
		update_post_meta( $id, HDP_Trainingen::META_DATUM, '2027-01-14' );
		update_post_meta( $id, HDP_Training_PowerAll::META_PRIJS, $prijs );
		return $id;
	}

	private function meld_aan( $training, $naam = 'Piet Monteur', $email = 'piet@dealer.test' ) {
		$id = HDP_Trainingen::meld_aan( $training, array( 'naam' => $naam, 'email' => $email ) );
		$this->assertIsInt( $id );
		// Op de site doet de wachtrij dit op de achtergrond, direct na het aanmelden.
		if ( 'wacht' === HDP_Training_PowerAll::aanmelding( $id )['pa_status'] ) {
			HDP_Training_PowerAll::verstuur( $id );
		}
		return $id;
	}

	public function test_aanmelding_wordt_order_per_deelnemer_met_prijs_en_klant() {
		$training = $this->training( '95.50' );
		$eerste   = $this->meld_aan( $training );
		$tweede   = $this->meld_aan( $training, 'Kees Monteur', 'kees@dealer.test' );

		$orders = $this->naar( 'sales-orders' );
		$this->assertCount( 2, $orders, 'Eén order per deelnemer.' );
		$body = $orders[0][2];
		$this->assertSame( '23524', $body['RelationId'] );
		$this->assertSame( 91000001, $body['EntryNumber'] );
		$this->assertSame( 'TR1', $body['Reference1'] );
		$this->assertSame( 'Training Piet Monteur', $body['Reference2'] );
		$this->assertSame( 'TRAINING', $body['Lines'][0]['ProductCode'] );
		$this->assertEquals( 1, $body['Lines'][0]['QuantityOrdered'] );
		$this->assertEquals( 95.5, $body['Lines'][0]['GrossPrice'] );
		$this->assertSame( 0, $body['Lines'][0]['DiscountPercentage'] );
		$this->assertStringContainsString( 'Service Väderstad Tempo', $body['Lines'][0]['Description'] );
		$this->assertStringContainsString( '14-01-2027', $body['Lines'][0]['Description'] );
		$this->assertStringContainsString( 'Piet Monteur', $body['Lines'][0]['Description'] );
		$this->assertSame( 'TR2', $orders[1][2]['Reference1'] );

		$rij = HDP_Training_PowerAll::aanmelding( $eerste );
		$this->assertSame( 'verstuurd', $rij['pa_status'] );
		$this->assertSame( '91000001', $rij['pa_nummer'] );
		$this->assertSame( '95.50', $rij['prijs'], 'De prijs van het moment van aanmelden wordt bewaard.' );
		$this->assertSame( 'Order TR2, nummer 91000002', HDP_Training_PowerAll::statustekst( HDP_Training_PowerAll::aanmelding( $tweede ) ) );
	}

	public function test_gratis_training_gaat_niet_naar_powerall() {
		$id = $this->meld_aan( $this->training( '' ) );
		$this->assertSame( array(), $this->verzoeken );
		$this->assertSame( '', HDP_Training_PowerAll::aanmelding( $id )['pa_status'] );
		$this->assertStringNotContainsString( 'Kosten', $this->mails[1]['message'] );
	}

	public function test_nogmaals_versturen_maakt_hem_niet_dubbel() {
		$id = $this->meld_aan( $this->training() );
		$this->assertSame( 'al verstuurd', HDP_Training_PowerAll::verstuur( $id ) );
		HDP_Training_PowerAll::herhaal();
		$this->assertCount( 1, $this->naar( 'sales-orders' ) );
	}

	public function test_storing_probeert_opnieuw_met_hetzelfde_nummer() {
		$this->storing = true;
		$id            = $this->meld_aan( $this->training() );
		$rij           = HDP_Training_PowerAll::aanmelding( $id );
		$this->assertSame( 'fout', $rij['pa_status'] );
		$this->assertSame( '1', (string) $rij['pa_pogingen'] );

		$this->storing = false;
		HDP_Training_PowerAll::herhaal();
		$orders = $this->naar( 'sales-orders' );
		$this->assertCount( 2, $orders );
		$this->assertSame( $orders[0][2]['EntryNumber'], $orders[1][2]['EntryNumber'], 'Zelfde nummer: PowerAll maakt hem nooit dubbel.' );
		$this->assertSame( 'verstuurd', HDP_Training_PowerAll::aanmelding( $id )['pa_status'] );
	}

	public function test_zonder_klantnummer_geen_order_maar_wel_een_waarschuwing_aan_het_team() {
		delete_user_meta( $this->dealer, HDP_PowerAll_Dealers::META_RELATIE );
		$id  = $this->meld_aan( $this->training() );
		$rij = HDP_Training_PowerAll::aanmelding( $id );

		$this->assertSame( array(), $this->naar( 'sales-orders' ) );
		$this->assertSame( 'fout', $rij['pa_status'] );
		$this->assertStringContainsString( 'klantnummer', $rij['pa_fout'] );
		$team = $this->mails[1];
		$this->assertSame( 'team@homburg.test', $team['to'] );
		$this->assertStringContainsString( 'LET OP', $team['message'] );
		$this->assertStringContainsString( 'met de hand', $team['message'] );
		HDP_Training_PowerAll::herhaal();
		$this->assertSame( array(), $this->naar( 'sales-orders' ), 'Geen eindeloze pogingen.' );
	}

	public function test_zonder_trainingsartikel_geen_order() {
		update_option( HDP_PowerAll_Orders::OPTION, array( 'modus' => 'aan', 'nummering' => 'portaal' ) );
		$id = $this->meld_aan( $this->training() );
		$this->assertSame( array(), $this->naar( 'sales-orders' ) );
		$this->assertStringContainsString( 'trainingsartikel', HDP_Training_PowerAll::aanmelding( $id )['pa_fout'] );
	}

	public function test_proef_maakt_niets_aan_en_gaat_later_niet_alsnog_echt() {
		update_option( HDP_PowerAll_Orders::OPTION, array( 'modus' => 'proef', 'trainingsartikel' => 'TRAINING' ) );
		$id = $this->meld_aan( $this->training() );
		$this->assertCount( 1, $this->naar( 'sales-orders/simulate' ) );
		$this->assertSame( array(), $this->naar( 'sales-orders' ) );
		$this->assertSame( 'proef', HDP_Training_PowerAll::aanmelding( $id )['pa_status'] );

		update_option( HDP_PowerAll_Orders::OPTION, array( 'modus' => 'aan', 'trainingsartikel' => 'TRAINING' ) );
		$this->assertSame( 'overgeslagen', HDP_Training_PowerAll::verstuur( $id ) );
		$this->assertSame( array(), $this->naar( 'sales-orders' ) );
	}

	public function test_powerall_uit_stuurt_niets() {
		update_option( HDP_PowerAll_Orders::OPTION, array( 'modus' => 'uit', 'trainingsartikel' => 'TRAINING' ) );
		$id = $this->meld_aan( $this->training() );
		$this->assertSame( array(), $this->verzoeken );
		$this->assertSame( '', HDP_Training_PowerAll::aanmelding( $id )['pa_status'] );
		$this->assertStringContainsString( 'met de hand', $this->mails[1]['message'] );
	}

	public function test_powerall_nummert_zelf() {
		update_option( HDP_PowerAll_Orders::OPTION, array( 'modus' => 'aan', 'nummering' => 'powerall', 'trainingsartikel' => 'TRAINING' ) );
		$id   = $this->meld_aan( $this->training() );
		$body = $this->naar( 'sales-orders' )[0][2];
		$this->assertArrayNotHasKey( 'EntryNumber', $body );
		$this->assertSame( 'TR1', $body['Reference1'] );
		$this->assertSame( '2260099', HDP_Training_PowerAll::aanmelding( $id )['pa_nummer'] );
	}

	public function test_geannuleerde_aanmelding_gaat_niet_meer() {
		$this->storing = true;
		$id            = $this->meld_aan( $this->training() );
		HDP_Trainingen::annuleer( $id );
		$this->storing = false;
		HDP_Training_PowerAll::herhaal();
		$this->assertSame( 'overgeslagen', HDP_Training_PowerAll::verstuur( $id ) );
		$this->assertCount( 1, $this->naar( 'sales-orders' ), 'Alleen de eerste, mislukte poging.' );
	}

	public function test_teammail_noemt_de_kosten_en_de_order() {
		$this->meld_aan( $this->training( '95' ) );
		$this->assertStringContainsString( 'Kosten: € 95,00 excl. btw', $this->mails[1]['message'] );
		$this->assertStringContainsString( 'order in PowerAll', $this->mails[1]['message'] );
	}

	public function test_dealer_ziet_de_prijs_als_er_geen_kostentekst_is() {
		$training = $this->training( '95' );
		$this->assertContains( '€ 95,00 excl. btw per persoon', HDP_Trainingen::feiten( $training ) );
		update_post_meta( $training, HDP_Trainingen::META_KOSTEN, '€ 95 incl. lunch' );
		$this->assertContains( '€ 95 incl. lunch', HDP_Trainingen::feiten( $training ) );
	}

	public function test_webshopnummers_lopen_los_van_trainingen() {
		$this->meld_aan( $this->training() );
		delete_option( HDP_PowerAll_Orders::OPTION_TELLER );
		$this->assertSame( 1, HDP_PowerAll_Orders::volgende_ws(), 'Een training telt niet mee in WS1, WS2, ...' );
	}
}
