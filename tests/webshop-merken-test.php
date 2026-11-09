<?php

/**
 * Test de webshop per merk (HDP_Webshop_Merken).
 *
 * Wat hier fout kan gaan: een dealer ziet, vindt of bestelt artikelen van een
 * merk waarvoor hij geen dealer is; een collega van hetzelfde bedrijf houdt
 * oude merken; Homburg zelf ziet niet meer alles.
 *
 * @group hdp-webshop
 */
class Webshop_Merken_Test extends WP_UnitTestCase {

	private $dealer;
	private $cats = array();
	private $prod = array();

	public function setUp(): void {
		parent::setUp();
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is niet geladen.' );
		}
		HDP_Roles::activate();
		foreach ( array( 'vaderstad' => 'Väderstad', 'bogballe' => 'Bogballe', 'homburg' => 'Homburg', 'diversen' => 'Diversen', 'outlet' => 'Outlet' ) as $slug => $naam ) {
			$term                = get_term_by( 'slug', $slug, 'product_cat' );
			$this->cats[ $slug ] = $term ? (int) $term->term_id : (int) wp_insert_term( $naam, 'product_cat', array( 'slug' => $slug ) )['term_id'];
			$product             = new WC_Product_Simple();
			$product->set_name( 'Artikel ' . $naam );
			$product->set_sku( 'T-' . strtoupper( $slug ) );
			$product->set_regular_price( '10' );
			$product->set_category_ids( array( $this->cats[ $slug ] ) );
			$product->save();
			$this->prod[ $slug ] = $product->get_id();
		}
		$this->dealer = $this->maak_dealer( 'Väderstad' );
		wp_set_current_user( $this->dealer );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function maak_dealer( $merken, $relatie = '' ) {
		$id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $id, 'hdp_goedgekeurd', '1' );
		if ( $relatie ) {
			update_user_meta( $id, HDP_PowerAll_Dealers::META_RELATIE, $relatie );
		}
		update_user_meta( $id, 'hdp_merken', $merken );
		return $id;
	}

	private function zichtbare_slugs() {
		$q   = new WP_Query( array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) );
		$ids = array_map( 'intval', $q->posts );
		return array_keys( array_filter( $this->prod, static function ( $id ) use ( $ids ) { return in_array( $id, $ids, true ); } ) );
	}

	public function test_dealer_ziet_eigen_merk_diversen_en_outlet() {
		$this->assertEqualsCanonicalizing( array( 'vaderstad', 'diversen', 'outlet' ), $this->zichtbare_slugs() );
	}

	public function test_homburg_is_een_eigen_merk() {
		$this->assertNotContains( 'homburg', $this->zichtbare_slugs() );
		update_user_meta( $this->dealer, 'hdp_merken', 'Väderstad, Homburg' );
		$this->assertTrue( HDP_Webshop_Merken::mag_product( $this->prod['homburg'], $this->dealer ) );
	}

	public function test_zonder_merken_alleen_diversen_en_outlet() {
		wp_set_current_user( $this->maak_dealer( '' ) );
		$this->assertEqualsCanonicalizing( array( 'diversen', 'outlet' ), $this->zichtbare_slugs() );
	}

	public function test_ander_merk_niet_zichtbaar_en_niet_te_bestellen() {
		$ander = wc_get_product( $this->prod['bogballe'] );
		$this->assertFalse( $ander->is_visible() );
		$this->assertFalse( $ander->is_purchasable() );
		$this->assertFalse( apply_filters( 'woocommerce_add_to_cart_validation', true, $this->prod['bogballe'], 1 ) );

		$eigen = wc_get_product( $this->prod['vaderstad'] );
		$this->assertTrue( $eigen->is_visible() );
		$this->assertTrue( $eigen->is_purchasable() );
	}

	public function test_ander_merk_niet_in_het_merkfilter() {
		$slugs = wp_list_pluck( get_terms( array( 'taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => false ) ), 'slug' );
		$this->assertContains( 'vaderstad', $slugs );
		$this->assertNotContains( 'bogballe', $slugs );
		$this->assertNotContains( 'homburg', $slugs );
	}

	public function test_homburg_medewerker_ziet_alles() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$this->assertEqualsCanonicalizing( array_keys( $this->prod ), $this->zichtbare_slugs() );
		$this->assertTrue( wc_get_product( $this->prod['bogballe'] )->is_purchasable() );
	}

	public function test_collegas_delen_de_merken() {
		$eerste = $this->maak_dealer( 'Bogballe', '4711' );
		$tweede = $this->maak_dealer( 'Bogballe', '4711' );
		$ander  = $this->maak_dealer( 'Bogballe', '9999' );

		update_user_meta( $eerste, 'hdp_merken', 'Väderstad, Homburg' );

		$this->assertSame( 'Väderstad, Homburg', get_user_meta( $tweede, 'hdp_merken', true ) );
		$this->assertSame( 'Bogballe', get_user_meta( $ander, 'hdp_merken', true ), 'Een ander bedrijf houdt zijn eigen merken.' );
	}
}
