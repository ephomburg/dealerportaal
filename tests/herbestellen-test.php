<?php

/**
 * Test HDP_Herbestellen::render() — toont eerder bestelde producten van
 * de ingelogde dealer met een "opnieuw bestellen"-knop.
 *
 * @group hdp-herbestellen
 */
class Herbestellen_Test extends WP_UnitTestCase {

	private $dealer_id;

	public function setUp(): void {
		parent::setUp();

		if ( ! function_exists( 'wc_get_orders' ) ) {
			$this->markTestSkipped( 'WooCommerce is niet actief in de testomgeving.' );
		}

		$this->dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		wp_set_current_user( $this->dealer_id );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function maak_product( $naam ) {
		$product = new WC_Product_Simple();
		$product->set_name( $naam );
		$product->set_regular_price( '19.95' );
		$product->set_status( 'publish' );
		$product->save();
		return $product;
	}

	private function maak_bestelling( $product, $status = 'completed' ) {
		$order = wc_create_order( array( 'customer_id' => $this->dealer_id ) );
		$order->add_product( $product, 1 );
		$order->calculate_totals();
		$order->set_status( $status );
		$order->save();
		return $order;
	}

	private function render_html() {
		ob_start();
		HDP_Herbestellen::render();
		return ob_get_clean();
	}

	public function test_toont_niets_zonder_bestelgeschiedenis() {
		$this->assertSame( '', $this->render_html() );
	}

	public function test_toont_product_uit_afgeronde_bestelling_met_werkende_bestelknop() {
		$product = $this->maak_product( 'Zaaischijf 18cm' );
		$this->maak_bestelling( $product );

		$html = $this->render_html();

		$this->assertStringContainsString( 'Zaaischijf 18cm', $html );
		$this->assertStringContainsString( 'add-to-cart=' . $product->get_id(), $html );
	}

	public function test_negeert_mislukte_bestellingen() {
		$product = $this->maak_product( 'Nooit-verzonden-onderdeel' );
		$this->maak_bestelling( $product, 'failed' );

		$this->assertSame( '', $this->render_html() );
	}

	public function test_toont_elk_product_maar_een_keer_ook_bij_meerdere_bestellingen() {
		$product = $this->maak_product( 'Veelbestelde bout' );
		$this->maak_bestelling( $product );
		$this->maak_bestelling( $product );

		$html = $this->render_html();

		$this->assertSame( 1, substr_count( $html, 'Veelbestelde bout' ) );
	}

	public function test_toont_niet_meer_dan_max_producten() {
		for ( $i = 1; $i <= HDP_Herbestellen::MAX_PRODUCTEN + 2; $i++ ) {
			$this->maak_bestelling( $this->maak_product( 'Product ' . $i ) );
		}

		$html = $this->render_html();

		$this->assertSame( HDP_Herbestellen::MAX_PRODUCTEN, substr_count( $html, 'hdp-herbestel-kaart' ) );
	}

	public function test_andere_dealer_ziet_deze_producten_niet() {
		$product = $this->maak_product( 'Alleen voor dealer A' );
		$this->maak_bestelling( $product );

		$andere_dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		wp_set_current_user( $andere_dealer_id );

		$this->assertSame( '', $this->render_html() );
	}
}
