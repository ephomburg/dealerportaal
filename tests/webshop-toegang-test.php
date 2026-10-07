<?php

/**
 * Test de afscherming van de webshop (HDP_Webshop_Toegang).
 *
 * De catalogus stond open: zonder in te loggen waren productnamen,
 * dealerprijzen en bestelknoppen gewoon te zien, en de Store API gaf
 * dezelfde gegevens als JSON. De vraag die deze tests bewaken is steeds
 * dezelfde: wie mag erbij, en via welke deuren.
 *
 * @group hdp-webshop-toegang
 */
class Webshop_Toegang_Test extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		HDP_Roles::activate();
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function maak_dealer( $goedgekeurd ) {
		$id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		if ( $goedgekeurd ) {
			update_user_meta( $id, 'hdp_goedgekeurd', '1' );
		}
		return $id;
	}

	/* --- Wie mag erbij ------------------------------------------------ */

	public function test_uitgelogde_bezoeker_mag_niet() {
		wp_set_current_user( 0 );

		$this->assertFalse( HDP_Webshop_Toegang::mag_erin() );
	}

	/**
	 * Een dealer die zich heeft aangemeld maar nog niet is goedgekeurd,
	 * hoort de prijzen nog niet te zien — net als op de downloadpagina.
	 */
	public function test_dealer_zonder_goedkeuring_mag_niet() {
		wp_set_current_user( $this->maak_dealer( false ) );

		$this->assertFalse( HDP_Webshop_Toegang::mag_erin() );
	}

	public function test_goedgekeurde_dealer_mag_wel() {
		wp_set_current_user( $this->maak_dealer( true ) );

		$this->assertTrue( HDP_Webshop_Toegang::mag_erin() );
	}

	public function test_beheerder_mag_wel() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertTrue( HDP_Webshop_Toegang::mag_erin() );
	}

	/* --- Welke deuren -------------------------------------------------- */

	public function test_catalogusroutes_zijn_afgeschermd() {
		$dicht = array(
			'/wc/store/v1/products',
			'/wc/store/v1/products/61',
			'/wc/store/products',
			'/wc/store/v1/cart',
			'/wp/v2/product',
			'/wp/v2/product/61',
		);

		foreach ( $dicht as $route ) {
			$this->assertTrue( HDP_Webshop_Toegang::is_afgeschermde_route( $route ), "$route hoort afgeschermd te zijn." );
		}
	}

	/**
	 * /wc/v3 vraagt zelf al om een sleutel en er kan een koppeling aan
	 * hangen. Een te brede afscherming legt die stilletjes plat; vandaar
	 * dat deze test er expliciet buiten blijft.
	 */
	public function test_beheer_api_en_overige_routes_blijven_ongemoeid() {
		$open = array(
			'/wc/v3/products',
			'/wc/v2/orders',
			'/wp/v2/posts',
			'/wp/v2/pages',
			'/',
			'/oembed/1.0/embed',
		);

		foreach ( $open as $route ) {
			$this->assertFalse( HDP_Webshop_Toegang::is_afgeschermde_route( $route ), "$route hoort niet door deze afscherming geraakt te worden." );
		}
	}

	/* --- De REST-afhandeling zelf -------------------------------------- */

	public function test_rest_geeft_een_fout_voor_wie_niet_mag() {
		wp_set_current_user( 0 );

		$antwoord = HDP_Webshop_Toegang::scherm_rest_af( null, null, new WP_REST_Request( 'GET', '/wc/store/v1/products' ) );

		$this->assertInstanceOf( 'WP_Error', $antwoord );
		$this->assertSame( 401, $antwoord->get_error_data()['status'] );
	}

	public function test_rest_laat_een_goedgekeurde_dealer_door() {
		wp_set_current_user( $this->maak_dealer( true ) );

		$antwoord = HDP_Webshop_Toegang::scherm_rest_af( null, null, new WP_REST_Request( 'GET', '/wc/store/v1/products' ) );

		$this->assertNull( $antwoord );
	}

	/**
	 * Ligt er al een antwoord klaar (een andere filter was ons voor), dan
	 * mag deze afscherming dat niet overschrijven.
	 */
	public function test_rest_laat_een_bestaand_antwoord_met_rust() {
		wp_set_current_user( 0 );

		$bestaand = new WP_REST_Response( array( 'iets' => 'anders' ) );
		$antwoord = HDP_Webshop_Toegang::scherm_rest_af( $bestaand, null, new WP_REST_Request( 'GET', '/wc/store/v1/products' ) );

		$this->assertSame( $bestaand, $antwoord );
	}

	/* --- De sitemap ---------------------------------------------------- */

	public function test_producten_staan_niet_meer_in_de_sitemap() {
		$types = HDP_Webshop_Toegang::haal_producten_uit_sitemap(
			array(
				'post'    => 'blijft',
				'page'    => 'blijft',
				'product' => 'moet weg',
			)
		);

		$this->assertArrayNotHasKey( 'product', $types );
		$this->assertArrayHasKey( 'page', $types, 'Gewone pagina s horen in de sitemap te blijven.' );
	}

	public function test_merk_en_categorie_staan_niet_meer_in_de_sitemap() {
		$taxonomieen = HDP_Webshop_Toegang::haal_producttaxonomieen_uit_sitemap(
			array(
				'category'      => 'blijft',
				'product_cat'   => 'moet weg',
				'product_brand' => 'moet weg',
				'product_tag'   => 'moet weg',
			)
		);

		$this->assertSame( array( 'category' => 'blijft' ), $taxonomieen );
	}
}
