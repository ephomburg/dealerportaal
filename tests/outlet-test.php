<?php

/**
 * Test de outlet (outlet.php in het thema).
 *
 * Wat hier fout kan gaan: een verkochte partij die te koop blijft staan
 * (het thema zet de voorraad van gewone artikelen geforceerd op "op
 * voorraad"), en een outletproduct dat een latere PowerAll-koppeling niet als
 * handmatig herkent.
 *
 * @group hdp-outlet
 */
class Outlet_Test extends WP_UnitTestCase {

	public static function wpSetUpBeforeClass() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		$map = dirname( __DIR__ ) . '/app/public/wp-content/themes/homburg-dealerportaal/includes/woocommerce/';
		foreach ( array( 'bestellen.php', 'product-image.php', 'outlet.php' ) as $bestand ) {
			require_once $map . $bestand;
		}
	}

	public function setUp(): void {
		parent::setUp();
		if ( ! function_exists( 'homburg_wc_is_outlet' ) ) {
			$this->markTestSkipped( 'WooCommerce is niet geladen.' );
		}
		homburg_wc_outlet_categorie_klaarzetten();

		// De testomgeving zet de filters na elke test terug naar hoe ze bij
		// de allereerste test waren; de thema-bestanden komen pas later.
		add_filter( 'woocommerce_product_get_stock_status', 'homburg_wc_forceer_instock', 20, 2 );
		add_filter( 'woocommerce_product_is_visible', 'homburg_wc_uitverkochte_outlet_onzichtbaar', 10, 2 );
	}

	private function maak( $aantal, $outlet = true ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'Stalen plaat' );
		$product->set_regular_price( '45' );
		$product->set_status( 'publish' );
		if ( $outlet ) {
			$product->set_category_ids( array( get_term_by( 'slug', HOMBURG_WC_OUTLET, 'product_cat' )->term_id ) );
		}
		if ( null !== $aantal ) {
			$product->set_manage_stock( true );
			$product->set_stock_quantity( $aantal );
		}
		$product->save();
		return wc_get_product( $product->get_id() );
	}

	public function test_categorie_staat_klaar() {
		$this->assertInstanceOf( WP_Term::class, get_term_by( 'slug', HOMBURG_WC_OUTLET, 'product_cat' ) );
		$this->assertNotSame( '', homburg_wc_outlet_url() );
	}

	public function test_oude_categorie_wordt_hernoemd_en_houdt_haar_producten() {
		$term = get_term_by( 'slug', HOMBURG_WC_OUTLET, 'product_cat' );
		wp_update_term(
			$term->term_id,
			'product_cat',
			array(
				'name' => 'Occasions & restpartijen',
				'slug' => 'occasions',
			)
		);
		$product = $this->maak( 2, false );
		wp_set_object_terms( $product->get_id(), $term->term_id, 'product_cat' );

		homburg_wc_outlet_categorie_klaarzetten();

		$nu = get_term_by( 'slug', HOMBURG_WC_OUTLET, 'product_cat' );
		$this->assertSame( $term->term_id, $nu->term_id );
		$this->assertSame( 'Outlet', $nu->name );
		$this->assertFalse( get_term_by( 'slug', 'occasions', 'product_cat' ) );
		$this->assertTrue( homburg_wc_is_outlet( $product->get_id() ) );
	}

	public function test_menu_toont_outlet_ook_als_er_niets_te_koop_is() {
		$this->assertSame( array(), homburg_wc_outlet_ids( 'instock' ) );

		HDP_Roles::activate();
		$dealer = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $dealer, 'hdp_goedgekeurd', '1' );
		wp_set_current_user( $dealer );
		$pagina = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:homburg/portaal-kaart {"titel":"Webshop","icoon":"webshop","url":"/shop/"} /-->',
			)
		);
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $pagina );

		ob_start();
		HDP_Zijbalk::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( esc_url( homburg_wc_outlet_url() ), $html );
		$this->assertStringContainsString( HDP_I18N::t( 'zijbalk_outlet' ), $html );
	}

	public function test_uitverkochte_outlet_is_echt_uitverkocht_en_onzichtbaar() {
		$op = $this->maak( 0 );
		$this->assertSame( 'outofstock', $op->get_stock_status(), 'De gewone "altijd op voorraad"-regel mag niet voor de outlet gelden.' );
		$this->assertFalse( $op->is_visible() );
		$this->assertContains( $op->get_id(), homburg_wc_outlet_ids( 'outofstock' ) );
	}

	public function test_gewoon_artikel_blijft_altijd_leverbaar() {
		$artikel = $this->maak( 0, false );
		$this->assertSame( 'instock', $artikel->get_stock_status() );
	}

	public function test_nog_beschikbaar() {
		$this->assertSame( sprintf( HDP_I18N::t( 'outlet_nog_meer' ), 7 ), homburg_wc_outlet_beschikbaar( $this->maak( 7 ) ) );
		$this->assertSame( HDP_I18N::t( 'outlet_nog_een' ), homburg_wc_outlet_beschikbaar( $this->maak( 1 ) ) );
	}

	public function test_outlet_zonder_aantal_krijgt_voorraadbeheer_en_een_stuk() {
		$product = $this->maak( null );
		homburg_wc_outlet_voorraad_aan( $product );
		$this->assertTrue( $product->get_manage_stock() );
		$this->assertSame( 1, $product->get_stock_quantity() );
		$this->assertSame( 'no', $product->get_backorders() );
	}

	public function test_niet_meer_in_de_wagen_dan_er_is() {
		$this->assertSame( 3, homburg_wc_outlet_max_aantal( -1, $this->maak( 3 ) ) );
	}

	public function test_handmatig_kenmerk() {
		$product = $this->maak( 2 );
		$this->assertFalse( homburg_wc_is_handmatig( $product->get_id() ) );
		update_post_meta( $product->get_id(), HOMBURG_WC_HANDMATIG, '1' );
		$this->assertTrue( homburg_wc_is_handmatig( $product->get_id() ) );
	}
}
