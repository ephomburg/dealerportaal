<?php

/**
 * Test HDP_Bestelgeschiedenis_Render::render_bestelgeschiedenis_pagina() —
 * toont alle WooCommerce-bestellingen (elke status) van de ingelogde dealer,
 * gepagineerd, met een link naar WooCommerce's eigen orderdetailpagina.
 *
 * Zelfde opzet als Downloads_Access_Test (toegang) en Herbestellen_Test
 * (WooCommerce-orderdata), maar dan voor deze pagina.
 *
 * @group hdp-bestelgeschiedenis
 */
class Bestelgeschiedenis_Test extends WP_UnitTestCase {

	private $dealer_id;
	private $attributen;

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'wc_get_orders' ) ) {
			$this->markTestSkipped( 'WooCommerce is niet actief in de testomgeving.' );
		}

		HDP_Roles::activate();

		$this->dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $this->dealer_id, 'hdp_goedgekeurd', '1' );

		// Zelfde attributenset als blocks/bestelgeschiedenis/block.json, zodat
		// de test tegen dezelfde velden aan loopt als de echte pagina.
		$this->attributen = array(
			'heroAfbeelding' => 'https://example.test/hero.jpg',
			'loginIntro'     => 'Log in met uw dealeraccount.',
			'loginIntroFr'   => 'Connectez-vous avec votre compte revendeur.',
			'titel'          => 'Bestelgeschiedenis',
			'titelFr'        => 'Historique des commandes',
			'omschrijving'   => 'Overzicht van al uw bestellingen.',
			'omschrijvingFr' => 'Aperçu de toutes vos commandes.',
		);
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		unset( $_GET['hdp_bp'], $_GET['hdp_bz'], $_GET['hdp_bs'] );
		parent::tearDown();
	}

	private function render() {
		return HDP_Bestelgeschiedenis_Render::render_bestelgeschiedenis_pagina( $this->attributen );
	}

	private function maak_bestelling( $status = 'completed' ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'Testproduct' );
		$product->set_regular_price( '10.00' );
		$product->set_status( 'publish' );
		$product->save();

		$order = wc_create_order( array( 'customer_id' => $this->dealer_id ) );
		$order->add_product( $product, 1 );
		$order->calculate_totals();
		$order->set_status( $status );
		$order->save();
		return $order;
	}

	public function test_niet_ingelogd_ziet_inlogscherm() {
		$html = $this->render();

		$this->assertStringContainsString( 'gebruikersnaam', $html );
		$this->assertStringNotContainsString( 'hdp-bestel-lijst', $html );
	}

	public function test_niet_goedgekeurde_dealer_ziet_inlogscherm() {
		$niet_goedgekeurd = self::factory()->user->create( array( 'role' => 'dealer' ) );
		wp_set_current_user( $niet_goedgekeurd );

		$html = $this->render();

		$this->assertStringContainsString( 'gebruikersnaam', $html );
	}

	public function test_goedgekeurde_dealer_zonder_bestellingen_ziet_lege_staat() {
		wp_set_current_user( $this->dealer_id );

		$html = $this->render();

		$this->assertStringContainsString( 'nog geen bestellingen', strtolower( $html ) );
	}

	public function test_goedgekeurde_dealer_ziet_eigen_bestelling() {
		wp_set_current_user( $this->dealer_id );
		$order = $this->maak_bestelling();

		$html = $this->render();

		$this->assertStringContainsString( '#' . $order->get_order_number(), $html );
		$this->assertStringContainsString( 'hdp-bestel-status-completed', $html );
		$this->assertStringContainsString( esc_url( $order->get_view_order_url() ), $html );
	}

	public function test_toont_ook_niet_afgeronde_bestellingen_anders_dan_herbestellen() {
		// In tegenstelling tot HDP_Herbestellen (alleen completed/processing)
		// moet de volledige geschiedenis ook een mislukte bestelling tonen.
		wp_set_current_user( $this->dealer_id );
		$order = $this->maak_bestelling( 'failed' );

		$html = $this->render();

		$this->assertStringContainsString( '#' . $order->get_order_number(), $html );
	}

	public function test_andere_dealer_ziet_deze_bestelling_niet() {
		$this->maak_bestelling();

		$andere_dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $andere_dealer_id, 'hdp_goedgekeurd', '1' );
		wp_set_current_user( $andere_dealer_id );

		$html = $this->render();

		$this->assertStringContainsString( 'nog geen bestellingen', strtolower( $html ) );
	}

	public function test_paginering_verschijnt_pas_boven_de_paginagrootte() {
		wp_set_current_user( $this->dealer_id );

		for ( $i = 0; $i < HDP_Bestelgeschiedenis_Render::PER_PAGINA + 1; $i++ ) {
			$this->maak_bestelling();
		}

		$html = $this->render();

		$this->assertStringContainsString( 'hdp-bestel-paginering', $html );
		$this->assertSame( HDP_Bestelgeschiedenis_Render::PER_PAGINA, substr_count( $html, 'hdp-bestel-item' ) );
	}

	public function test_tweede_pagina_toont_de_resterende_bestelling() {
		wp_set_current_user( $this->dealer_id );

		for ( $i = 0; $i < HDP_Bestelgeschiedenis_Render::PER_PAGINA + 1; $i++ ) {
			$this->maak_bestelling();
		}

		$_GET['hdp_bp'] = '2';
		$html            = $this->render();

		$this->assertSame( 1, substr_count( $html, 'hdp-bestel-item' ) );
	}

	// ---- Zoeken/filteren ------------------------------------------------

	public function test_statusfilter_toont_alleen_die_status() {
		wp_set_current_user( $this->dealer_id );
		$afgerond   = $this->maak_bestelling( 'completed' );
		$in_behandeling = $this->maak_bestelling( 'on-hold' );

		$_GET['hdp_bs'] = 'on-hold';
		$html            = $this->render();

		$this->assertStringContainsString( '#' . $in_behandeling->get_order_number(), $html );
		$this->assertStringNotContainsString( '#' . $afgerond->get_order_number(), $html );
	}

	public function test_onbekende_status_in_url_wordt_genegeerd() {
		// Voorkomt dat een handmatig aangepaste ?hdp_bs= de WooCommerce-query
		// met een ongeldige status laat falen of alles verbergt.
		wp_set_current_user( $this->dealer_id );
		$order = $this->maak_bestelling();

		$_GET['hdp_bs'] = 'niet-bestaande-status';
		$html            = $this->render();

		$this->assertStringContainsString( '#' . $order->get_order_number(), $html );
	}

	public function test_zoeken_op_ordernummer_toont_alleen_matchende_bestelling() {
		wp_set_current_user( $this->dealer_id );
		$order_a = $this->maak_bestelling();
		$this->maak_bestelling();

		$_GET['hdp_bz'] = (string) $order_a->get_order_number();
		$html            = $this->render();

		$this->assertStringContainsString( '#' . $order_a->get_order_number(), $html );
	}

	public function test_wis_filters_link_verschijnt_alleen_bij_actief_filter() {
		wp_set_current_user( $this->dealer_id );
		$this->maak_bestelling();

		$html_zonder_filter = $this->render();
		$this->assertStringNotContainsString( 'hdp-wis-filters', $html_zonder_filter );

		$_GET['hdp_bs']  = 'completed';
		$html_met_filter = $this->render();
		$this->assertStringContainsString( 'hdp-wis-filters', $html_met_filter );
	}

	public function test_filter_zonder_resultaat_toont_leeg_resultaat_niet_webshoplink() {
		wp_set_current_user( $this->dealer_id );
		$this->maak_bestelling( 'completed' );

		$_GET['hdp_bs'] = 'cancelled';
		$html            = $this->render();

		$this->assertStringContainsString( 'Geen bestellingen gevonden voor deze zoekopdracht/status.', $html );
	}
}
