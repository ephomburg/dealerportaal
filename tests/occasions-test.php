<?php

/**
 * Test de occasions en restpartijen (occasions.php in het thema).
 *
 * Wat hier fout kan gaan: een verkochte partij die te koop blijft staan
 * (het thema zet de voorraad van gewone artikelen geforceerd op "op
 * voorraad"), en een occasion die een latere PowerAll-koppeling niet als
 * handmatig herkent.
 *
 * @group hdp-occasions
 */
class Occasions_Test extends WP_UnitTestCase {

	public static function wpSetUpBeforeClass() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		$map = dirname( __DIR__ ) . '/app/public/wp-content/themes/homburg-dealerportaal/includes/woocommerce/';
		foreach ( array( 'bestellen.php', 'product-image.php', 'occasions.php' ) as $bestand ) {
			require_once $map . $bestand;
		}
	}

	public function setUp(): void {
		parent::setUp();
		if ( ! function_exists( 'homburg_wc_is_occasion' ) ) {
			$this->markTestSkipped( 'WooCommerce is niet geladen.' );
		}
		homburg_wc_occasions_categorie_klaarzetten();

		// De testomgeving zet de filters na elke test terug naar hoe ze bij
		// de allereerste test waren; de thema-bestanden komen pas later.
		add_filter( 'woocommerce_product_get_stock_status', 'homburg_wc_forceer_instock', 20, 2 );
		add_filter( 'woocommerce_product_is_visible', 'homburg_wc_uitverkochte_occasion_onzichtbaar', 10, 2 );
	}

	private function maak( $aantal, $occasion = true ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'Stalen plaat' );
		$product->set_regular_price( '45' );
		$product->set_status( 'publish' );
		if ( $occasion ) {
			$product->set_category_ids( array( get_term_by( 'slug', HOMBURG_WC_OCCASIONS, 'product_cat' )->term_id ) );
		}
		if ( null !== $aantal ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( $aantal );
		}
		$product->save();
		return wc_get_product( $product->get_id() );
	}

	public function test_categorie_staat_klaar() {
		$this->assertInstanceOf( WP_Term::class, get_term_by( 'slug', HOMBURG_WC_OCCASIONS, 'product_cat' ) );
		$this->assertNotSame( '', homburg_wc_occasions_url() );
	}

	public function test_uitverkochte_occasion_is_echt_uitverkocht_en_onzichtbaar() {
		$op = $this->maak( 0 );
		$this->assertSame( 'outofstock', $op->get_stock_status(), 'De gewone "altijd op voorraad"-regel mag niet voor occasions gelden.' );
		$this->assertFalse( $op->is_visible() );
		$this->assertContains( $op->get_id(), homburg_wc_occasion_ids( 'outofstock' ) );
	}

	public function test_gewoon_artikel_blijft_altijd_leverbaar() {
		$artikel = $this->maak( 0, false );
		$this->assertSame( 'instock', $artikel->get_stock_status() );
	}

	public function test_nog_beschikbaar() {
		$this->assertSame( sprintf( HDP_I18N::t( 'occasion_nog_meer' ), 7 ), homburg_wc_occasion_beschikbaar( $this->maak( 7 ) ) );
		$this->assertSame( HDP_I18N::t( 'occasion_nog_een' ), homburg_wc_occasion_beschikbaar( $this->maak( 1 ) ) );
	}

	public function test_occasion_zonder_aantal_krijgt_voorraadbeheer_en_een_stuk() {
		$product = $this->maak( null );
		homburg_wc_occasion_voorraad_aan( $product );
		$this->assertTrue( $product->get_manage_stock() );
		$this->assertSame( 1, $product->get_stock_quantity() );
		$this->assertSame( 'no', $product->get_backorders() );
	}

	public function test_niet_meer_in_de_wagen_dan_er_is() {
		$this->assertSame( 3, homburg_wc_occasion_max_aantal( -1, $this->maak( 3 ) ) );
	}

	public function test_handmatig_kenmerk() {
		$product = $this->maak( 2 );
		$this->assertFalse( homburg_wc_is_handmatig( $product->get_id() ) );
		update_post_meta( $product->get_id(), HOMBURG_WC_HANDMATIG, '1' );
		$this->assertTrue( homburg_wc_is_handmatig( $product->get_id() ) );
	}
}
