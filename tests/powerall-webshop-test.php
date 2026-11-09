<?php

/**
 * Test de webshop met PowerAll: artikelen ophalen, de prijs per dealer en het
 * versturen van bestellingen.
 *
 * Wat hier fout kan gaan: een met de hand aangemaakt product (outlet) wordt
 * overschreven, een storing haalt de webshop leeg, een dealer ziet de prijs van
 * een ander, een order gaat twee keer of echt terwijl het "proef" is.
 *
 * PowerAll wordt nagebootst; er gaat geen verzoek naar buiten.
 *
 * @group hdp-powerall
 */
class PowerAll_Webshop_Test extends WP_UnitTestCase {

	private $artikelen = array();
	private $verzoeken = array();
	private $storing   = false;
	private $dealer;

	public static function wpSetUpBeforeClass() {
		if ( ! defined( 'HDP_POWERALL_URL' ) ) {
			define( 'HDP_POWERALL_URL', 'https://powerall.test/v1' );
			define( 'HDP_POWERALL_TENANT', 'test' );
			define( 'HDP_POWERALL_TOKEN', 'geen-echte-sleutel' );
		}
	}

	public function setUp(): void {
		parent::setUp();
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is niet geladen.' );
		}
		HDP_Roles::activate();
		delete_option( HDP_PowerAll_Artikelen::OPTION );
		delete_option( HDP_PowerAll_Orders::OPTION );
		$this->verzoeken = array();
		$this->storing   = false;
		$this->artikelen = array(
			$this->artikel( 'VD-100', 'Zaaischijf', '070', '38.90' ),
			$this->artikel( 'BG-200', 'Strooischijf', '030', '120.00' ),
			$this->artikel( 'HA-300', 'Pomp', '010', '0' ),
		);
		$this->dealer = self::factory()->user->create( array( 'role' => 'dealer', 'first_name' => 'Jan', 'last_name' => 'Dealer', 'user_email' => 'jan@dealer.test' ) );
		update_user_meta( $this->dealer, 'hdp_goedgekeurd', '1' );
		update_user_meta( $this->dealer, HDP_PowerAll_Dealers::META_RELATIE, '23524' );
		HDP_PowerAll_Prijzen::vergeet( 23524 );
		add_filter( 'pre_http_request', array( $this, 'nep_powerall' ), 10, 3 );
		add_filter( 'pre_wp_mail', '__return_true' );
	}

	public function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this, 'nep_powerall' ), 10 );
		remove_filter( 'pre_wp_mail', '__return_true' );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function artikel( $code, $naam, $groep, $prijs ) {
		return array(
			'ProductCode'  => $code,
			'Description1' => $naam,
			'MainGroup'    => $groep,
			'SalesPrice'   => $prijs,
			'SalesUnit'    => 'Stuk',
			'TransferPrice' => '1.00',
		);
	}

	private function antwoord( $code, $data ) {
		return array( 'response' => array( 'code' => $code, 'message' => '' ), 'body' => wp_json_encode( $data ), 'headers' => array(), 'cookies' => array() );
	}

	public function nep_powerall( $niets, $args, $url ) {
		$this->assertSame( 'powerall.test', wp_parse_url( $url, PHP_URL_HOST ), 'Een test mag nooit naar de echte PowerAll.' );
		$pad               = trim( str_replace( 'https://powerall.test/v1', '', strtok( $url, '?' ) ), '/' );
		$body              = isset( $args['body'] ) ? json_decode( $args['body'], true ) : null;
		$this->verzoeken[] = array( $args['method'], $pad, $body );
		if ( $this->storing ) {
			return $this->antwoord( 503, array() );
		}
		if ( 'products' === $pad ) {
			parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $q );
			return $this->antwoord( 200, array( 'Data' => array_slice( $this->artikelen, (int) $q['offset'], (int) $q['limit'] ) ) );
		}
		if ( 'products/prices' === $pad ) {
			$data = array();
			foreach ( $body['Lines'] as $regel ) {
				if ( 'VD-100' === $regel['ProductCode'] ) {
					$data[] = array( 'ProductCode' => 'VD-100', 'NetPriceExVat' => '31.12' );
				}
			}
			return $this->antwoord( 200, array( 'Data' => $data ) );
		}
		if ( 'sales-orders/simulate' === $pad || 'sales-orders' === $pad ) {
			return $this->antwoord( 200, array( 'Data' => array( 'Id' => 'abc-123', 'EntryNumber' => 2026001, 'TotalNetPriceExVat' => '77.80' ) ) );
		}
		return $this->antwoord( 404, array() );
	}

	private function verzoeken_naar( $pad ) {
		return array_values( array_filter( $this->verzoeken, static function ( $v ) use ( $pad ) { return $v[1] === $pad; } ) );
	}

	/* === Artikelen ====================================================== */

	public function test_ronde_maakt_producten_met_merk_en_zonder_inkoopprijs() {
		HDP_PowerAll_Artikelen::ronde_nu();

		$product = wc_get_product( wc_get_product_id_by_sku( 'VD-100' ) );
		$this->assertSame( 'Zaaischijf', $product->get_name() );
		$this->assertSame( '38.90', $product->get_regular_price() );
		$this->assertSame( 'publish', $product->get_status() );
		$this->assertSame( array( 'vaderstad' ), wp_list_pluck( get_the_terms( $product->get_id(), 'product_cat' ), 'slug' ) );
		$this->assertStringNotContainsString( '1.00', wp_json_encode( get_post_meta( $product->get_id() ) ), 'De inkoopprijs komt niet in de webshop.' );
		$this->assertSame( '', wc_get_product( wc_get_product_id_by_sku( 'HA-300' ) )->get_price(), 'Zonder prijs niet te bestellen.' );
		$this->assertSame( 3, HDP_PowerAll_Artikelen::stand()['vorige']['nieuw'] );
	}

	public function test_met_de_hand_aangemaakt_product_blijft_ongemoeid() {
		$hand = new WC_Product_Simple();
		$hand->set_name( 'Outletpomp' );
		$hand->set_sku( 'BG-200' );
		$hand->set_regular_price( '10' );
		$hand->save();

		HDP_PowerAll_Artikelen::ronde_nu();
		$this->artikelen = array( $this->artikelen[0] );
		HDP_PowerAll_Artikelen::ronde_nu();

		$hand = wc_get_product( $hand->get_id() );
		$this->assertSame( 'Outletpomp', $hand->get_name() );
		$this->assertSame( '10', $hand->get_regular_price() );
		$this->assertSame( 'publish', $hand->get_status() );
	}

	public function test_artikel_dat_wegvalt_gaat_op_concept_en_komt_terug() {
		HDP_PowerAll_Artikelen::ronde_nu();
		$bewaard         = $this->artikelen;
		$this->artikelen = array( $bewaard[0], $bewaard[1] );
		HDP_PowerAll_Artikelen::ronde_nu();
		$id = wc_get_product_id_by_sku( 'HA-300' );
		$this->assertSame( 'draft', get_post_status( $id ) );

		$this->artikelen = $bewaard;
		HDP_PowerAll_Artikelen::ronde_nu();
		$this->assertSame( 'publish', get_post_status( $id ) );
	}

	public function test_storing_haalt_de_webshop_niet_leeg() {
		HDP_PowerAll_Artikelen::ronde_nu();
		$this->storing = true;
		$stand         = HDP_PowerAll_Artikelen::ronde_nu();

		$this->assertNotSame( '', $stand['vorige']['fout'] );
		$this->assertSame( 'publish', get_post_status( wc_get_product_id_by_sku( 'VD-100' ) ) );
	}

	public function test_ongewijzigd_artikel_wordt_niet_opnieuw_opgeslagen() {
		HDP_PowerAll_Artikelen::ronde_nu();
		$stand = HDP_PowerAll_Artikelen::ronde_nu();
		$this->assertSame( 0, $stand['vorige']['nieuw'] );
		$this->assertSame( 0, $stand['vorige']['gewijzigd'] );
		$this->assertSame( 3, $stand['vorige']['gezien'] );
	}

	/* === Prijzen ======================================================== */

	public function test_dealer_ziet_eigen_prijs_beheerder_de_verkoopprijs() {
		HDP_PowerAll_Artikelen::ronde_nu();
		$id = wc_get_product_id_by_sku( 'VD-100' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( '38.90', wc_get_product( $id )->get_price() );

		wp_set_current_user( $this->dealer );
		$this->assertSame( '31.12', wc_get_product( $id )->get_price() );
		$this->assertSame( 23524, $this->verzoeken_naar( 'products/prices' )[0][2]['RelationCode'] );

		$this->assertSame( '120.00', wc_get_product( wc_get_product_id_by_sku( 'BG-200' ) )->get_price(), 'Zonder antwoord van PowerAll de gewone prijs.' );
	}

	public function test_prijs_wordt_onthouden() {
		HDP_PowerAll_Artikelen::ronde_nu();
		wp_set_current_user( $this->dealer );
		$id = wc_get_product_id_by_sku( 'VD-100' );
		wc_get_product( $id )->get_price();
		wc_get_product( $id )->get_price();
		$this->assertCount( 1, $this->verzoeken_naar( 'products/prices' ) );
	}

	/* === Bestellingen =================================================== */

	private function bestelling( $extra_product = null ) {
		HDP_PowerAll_Artikelen::ronde_nu();
		$order = wc_create_order( array( 'customer_id' => $this->dealer ) );
		$order->add_product( wc_get_product( wc_get_product_id_by_sku( 'VD-100' ) ), 2 );
		if ( $extra_product ) {
			$order->add_product( $extra_product, 1 );
		}
		$order->set_billing_email( 'jan@dealer.test' );
		$order->set_shipping_company( 'Dealer BV' );
		$order->set_shipping_address_1( 'Dorpsstraat 1' );
		$order->set_shipping_postcode( '9051 BM' );
		$order->set_shipping_city( 'Stiens' );
		$order->update_meta_data( '_wc_other/homburg/referentie', 'Inkoop 4711' );
		$order->calculate_totals();
		$order->save();
		return $order;
	}

	public function test_proef_maakt_niets_aan_in_powerall() {
		$order = $this->bestelling();
		$this->assertSame( 'proef', HDP_PowerAll_Orders::modus(), 'Standaard alleen proef.' );
		$this->assertSame( 'proef', HDP_PowerAll_Orders::verstuur( $order->get_id() ) );

		$this->assertCount( 0, $this->verzoeken_naar( 'sales-orders' ) );
		$proef = $this->verzoeken_naar( 'sales-orders/simulate' );
		$this->assertCount( 1, $proef );
		$body = $proef[0][2];
		$this->assertSame( '23524', $body['RelationId'] );
		$this->assertSame( 'Portaal ' . $order->get_order_number(), $body['Reference1'] );
		$this->assertSame( 'Inkoop 4711', $body['Reference2'] );
		$this->assertSame( array( array( 'ProductCode' => 'VD-100', 'QuantityOrdered' => 2 ) ), $body['Lines'] );
		$this->assertSame( 'Dealer BV', $body['DeliveryName1'] );
		$this->assertSame( '', (string) wc_get_order( $order->get_id() )->get_meta( HDP_PowerAll_Orders::META_NUMMER ) );
	}

	public function test_aan_verstuurt_een_keer_en_bewaart_het_nummer() {
		update_option( HDP_PowerAll_Orders::OPTION, array( 'modus' => 'aan' ) );
		$order = $this->bestelling();

		$this->assertSame( 'verstuurd', HDP_PowerAll_Orders::verstuur( $order->get_id() ) );
		$this->assertSame( 'al verstuurd', HDP_PowerAll_Orders::verstuur( $order->get_id() ) );

		$echt = $this->verzoeken_naar( 'sales-orders' );
		$this->assertCount( 1, $echt );
		$this->assertArrayNotHasKey( 'EntryNumber', $echt[0][2], 'PowerAll nummert zelf.' );
		$this->assertSame( '2026001', wc_get_order( $order->get_id() )->get_meta( HDP_PowerAll_Orders::META_NUMMER ) );
	}

	public function test_uit_verstuurt_niets() {
		update_option( HDP_PowerAll_Orders::OPTION, array( 'modus' => 'uit' ) );
		$order = $this->bestelling();
		$this->assertSame( 'overgeslagen', HDP_PowerAll_Orders::verstuur( $order->get_id() ) );
		$this->assertCount( 0, $this->verzoeken_naar( 'sales-orders/simulate' ) );
	}

	public function test_outletregel_via_verzamelartikel() {
		$outlet = new WC_Product_Simple();
		$outlet->set_name( 'Set spuitboomdelen' );
		$outlet->set_sku( 'DEMO-9002' );
		$outlet->set_regular_price( '85' );
		$outlet->save();
		$order = $this->bestelling( $outlet );

		$this->assertSame( 'fout', HDP_PowerAll_Orders::verstuur( $order->get_id() ) );
		$this->assertStringContainsString( 'verzamelartikel', wc_get_order( $order->get_id() )->get_meta( HDP_PowerAll_Orders::META_FOUT ) );

		update_option( HDP_PowerAll_Orders::OPTION, array( 'modus' => 'proef', 'verzamelartikel' => 'OUTLET' ) );
		$this->assertSame( 'proef', HDP_PowerAll_Orders::verstuur( $order->get_id() ) );
		$regels = end( $this->verzoeken )[2]['Lines'];
		$this->assertSame( 'OUTLET', $regels[1]['ProductCode'] );
		$this->assertSame( 'Set spuitboomdelen (DEMO-9002)', $regels[1]['Description'] );
		$this->assertEquals( 85, $regels[1]['GrossPrice'] );
	}

	public function test_dealer_zonder_klantnummer_gaat_niet() {
		delete_user_meta( $this->dealer, HDP_PowerAll_Dealers::META_RELATIE );
		$order = $this->bestelling();
		$this->assertSame( 'fout', HDP_PowerAll_Orders::verstuur( $order->get_id() ) );
		$this->assertCount( 0, $this->verzoeken_naar( 'sales-orders/simulate' ) );
	}

	public function test_alleen_vaste_adressen_kunnen_iets_versturen() {
		$this->assertInstanceOf( WP_Error::class, HDP_PowerAll::post( 'relations', array() ) );
		$this->assertCount( 0, $this->verzoeken );
	}
}
