<?php

/**
 * Test het vernieuwde overzicht Portaalgebruik (1.110.0).
 *
 * Wat hier fout kan gaan: een collega telt als apart bedrijf (dan klopt
 * "actieve bedrijven" niet, en staat een bedrijf op de bellijst terwijl een
 * collega er gisteren nog was); een onderdeel telt bij elke klik opnieuw;
 * een bestelling zonder klant komt bij de ingelogde beheerder terecht; de
 * webshopcijfers rekenen de toeslag als artikel; "Vraagt aandacht" roept
 * iets terwijl alles in orde is.
 *
 * @group hdp-gebruik
 */
class Gebruik_Overzicht_Test extends WP_UnitTestCase {

	private $hoofd;
	private $collega;

	public function setUp(): void {
		parent::setUp();
		HDP_Roles::activate();
		delete_option( HDP_Gebruik::DB_OPTIE );
		HDP_Gebruik::controleer_tabel();
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . HDP_Gebruik::tabel() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		HDP_Gebruik::vergeet_orders();

		$this->hoofd   = $this->dealer( 'Loonbedrijf Jansen' );
		$this->collega = $this->dealer( 'Frits' );
		update_user_meta( $this->collega, HDP_Collegas::META_HOOFD, $this->hoofd );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		HDP_Gebruik::vergeet_orders();
		parent::tearDown();
	}

	private function dealer( $bedrijf ) {
		$id = self::factory()->user->create( array( 'role' => 'dealer', 'display_name' => $bedrijf ) );
		update_user_meta( $id, 'hdp_goedgekeurd', '1' );
		update_user_meta( $id, 'billing_company', $bedrijf );
		return $id;
	}

	private function bestelling( $klant, $bedrag = 100.0, $sku = 'SCH-1' ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'Strooischijf' );
		$product->set_sku( $sku );
		$product->set_regular_price( (string) $bedrag );
		$merk = wp_insert_term( 'Väderstad', 'product_cat' );
		$merk = is_wp_error( $merk ) ? (int) $merk->get_error_data( 'term_exists' ) : (int) $merk['term_id'];
		$sub  = wp_insert_term( 'Onderdelen ' . wp_rand(), 'product_cat', array( 'parent' => $merk ) );
		$product->set_category_ids( array( (int) $sub['term_id'] ) );
		$product->save();

		$order = wc_create_order( array( 'customer_id' => $klant ) );
		$order->add_product( $product, 2 );
		$order->set_billing_company( 'Loonbedrijf Jansen' );
		$order->calculate_totals( false );
		$order->set_status( 'processing' );
		$order->save();
		return $order;
	}

	/* --- Per bedrijf ----------------------------------------------------- */

	public function test_bedrijf_van_zonder_account_is_niemand() {
		wp_set_current_user( $this->hoofd );
		$this->assertSame( 0, HDP_Gebruik::bedrijf_van( 0 ), 'Niet de ingelogde gebruiker.' );
		$this->assertSame( $this->hoofd, HDP_Gebruik::bedrijf_van( $this->collega ) );
	}

	public function test_collega_telt_bij_het_bedrijf() {
		HDP_Gebruik::noteer( 'bezoek', 'computer|nl', 0, $this->hoofd );
		HDP_Gebruik::noteer( 'bezoek', 'mobiel|nl', 0, $this->collega );
		HDP_Gebruik::noteer( 'download', '12', 0, $this->collega );

		$this->assertSame( array( $this->hoofd ), wp_list_pluck( HDP_Gebruik::bedrijven(), 'ID' ) );
		$cijfers = HDP_Gebruik::kerncijfers( 30 );
		$this->assertSame( 1, $cijfers['actieve_bedrijven'], 'Hoofdaccount en collega zijn één bedrijf.' );
		$this->assertSame( 1, $cijfers['bedrijven'] );

		$lijst = HDP_Gebruik::dealerlijst( 30 );
		$this->assertCount( 1, $lijst );
		$this->assertSame( 'Loonbedrijf Jansen', $lijst[0]['bedrijf'] );
		$this->assertSame( 1, $lijst[0]['collegas'] );
		$this->assertSame( 1, $lijst[0]['bezoekdagen'], 'Twee accounts op dezelfde dag = één bezoekdag van het bedrijf.' );
		$this->assertSame( 1, $lijst[0]['downloads'] );
	}

	public function test_oud_account_dat_niet_meer_meetelt_telt_niet_als_actief() {
		HDP_Gebruik::noteer( 'bezoek', 'computer|nl', 0, $this->hoofd );
		$weg = $this->dealer( 'Oud bedrijf' );
		HDP_Gebruik::noteer( 'bezoek', 'computer|nl', 0, $weg );
		HDP_Gebruik::noteer_onderdeel_voor( $weg, 'webshop' );
		update_user_meta( $weg, HDP_Gebruik::TEST_META, '1' ); // Later als testaccount aangevinkt.

		$cijfers = HDP_Gebruik::kerncijfers( 30 );
		$this->assertSame( 1, $cijfers['actieve_bedrijven'] );
		$this->assertSame( 1, $cijfers['bedrijven'], 'Nooit "2 van 1".' );
		$weken = HDP_Gebruik::actief_per_week( 1 );
		$this->assertSame( 1, $weken[0]['aantal'], 'Ook per week alleen bedrijven die meetellen.' );
		$this->assertSame( 0, HDP_Gebruik::onderdelen( 30 )['webshop']['bedrijven'] );
	}

	public function test_bedrijf_niet_stil_als_een_collega_er_was() {
		update_user_meta( $this->hoofd, HDP_Gebruik::LAATST_META, time() - 50 * DAY_IN_SECONDS );
		$this->assertContains( $this->hoofd, wp_list_pluck( HDP_Gebruik::stille_dealers(), 'id' ) );

		update_user_meta( $this->collega, HDP_Gebruik::LAATST_META, time() - DAY_IN_SECONDS );
		$ids = wp_list_pluck( HDP_Gebruik::stille_dealers(), 'id' );
		$this->assertNotContains( $this->hoofd, $ids );
		$this->assertNotContains( $this->collega, $ids, 'Een collega staat nooit los op de bellijst.' );
	}

	public function test_activiteit() {
		$this->assertSame( 'nooit', HDP_Gebruik::activiteit( 0, 0 )['code'] );
		$this->assertSame( 'actief', HDP_Gebruik::activiteit( time(), HDP_Gebruik::ACTIEF_DAGEN )['code'] );
		$this->assertSame( 'af-en-toe', HDP_Gebruik::activiteit( time(), 1 )['code'] );
		$this->assertSame( 'af-en-toe', HDP_Gebruik::activiteit( time() - 10 * DAY_IN_SECONDS, 0 )['code'], 'Geweest volgens WooCommerce, van voor de meting.' );
		$this->assertSame( 'stil', HDP_Gebruik::activiteit( time() - 45 * DAY_IN_SECONDS, 0 )['code'] );
	}

	/* --- Onderdelen, apparaat en taal ---------------------------------- */

	public function test_onderdeel_telt_een_keer_per_dag() {
		HDP_Gebruik::noteer_onderdeel_voor( $this->hoofd, 'webshop' );
		HDP_Gebruik::noteer_onderdeel_voor( $this->hoofd, 'webshop' );
		HDP_Gebruik::noteer_onderdeel_voor( $this->hoofd, 'downloads' );
		HDP_Gebruik::noteer_onderdeel_voor( $this->collega, 'webshop' );
		HDP_Gebruik::noteer_onderdeel_voor( $this->hoofd, 'bestaat-niet' );

		$delen = HDP_Gebruik::onderdelen( 30 );
		$this->assertSame( array_keys( HDP_Gebruik::ONDERDELEN ), array_keys( $delen ), 'Alle onderdelen, in menuvolgorde.' );
		$this->assertSame( 1, $delen['webshop']['bedrijven'], 'Hoofdaccount en collega: één bedrijf.' );
		$this->assertSame( 2, $delen['webshop']['dagen'] );
		$this->assertSame( 1, $delen['downloads']['bedrijven'] );
		$this->assertSame( 0, $delen['garantie']['bedrijven'] );
	}

	public function test_apparaat_en_taal() {
		HDP_Gebruik::noteer( 'bezoek', 'computer|nl', 0, $this->hoofd );
		HDP_Gebruik::noteer( 'bezoek', 'mobiel|fr', 0, $this->collega );
		HDP_Gebruik::noteer( 'bezoek', '', 0, $this->hoofd ); // Van voor deze versie.

		$uit = HDP_Gebruik::apparaten( 30 );
		$this->assertSame( array( 'computer' => 1, 'mobiel' => 1 ), $uit['apparaat'] );
		$this->assertSame( array( 'nl' => 1, 'fr' => 1 ), $uit['taal'] );
	}

	public function test_bezoek_legt_apparaat_en_taal_vast() {
		wp_set_current_user( $this->hoofd );
		HDP_Gebruik::noteer_bezoek();
		global $wpdb;
		$sleutel = $wpdb->get_var( 'SELECT sleutel FROM ' . HDP_Gebruik::tabel() . " WHERE soort = 'bezoek'" ); // phpcs:ignore WordPress.DB
		$this->assertMatchesRegularExpression( '/^(computer|mobiel)\|(nl|fr)$/', (string) $sleutel );
	}

	/* --- Webshop --------------------------------------------------------- */

	public function test_bestelling_van_collega_bij_het_bedrijf_en_per_merk() {
		if ( ! function_exists( 'wc_create_order' ) ) {
			$this->markTestSkipped( 'WooCommerce is niet geladen.' );
		}
		$order = $this->bestelling( $this->collega, 40 );
		$fee   = new WC_Order_Item_Fee();
		$fee->set_name( 'Kleinordertoeslag' );
		$fee->set_total( '12.50' );
		$fee->add_meta_data( HDP_Kleinordertoeslag::META, '1', true );
		$order->add_item( $fee );
		$order->calculate_totals( false );
		$order->save();

		$beheerder = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->bestelling( $beheerder, 500, 'SCH-2' ); // Telt niet mee.
		HDP_Gebruik::vergeet_orders();

		$lijst = HDP_Gebruik::dealerlijst( 30 );
		$this->assertSame( 1, $lijst[0]['bestellingen'], 'De bestelling van de collega staat bij het bedrijf.' );
		$this->assertEqualsWithDelta( 92.5, $lijst[0]['omzet'], 0.01, 'Artikelen plus toeslag, zonder btw.' );

		$cijfers = HDP_Gebruik::webshopcijfers( 30 );
		$this->assertSame( array( 'Väderstad' => 80.0 ), $cijfers['merken'], 'Merk = hoofdcategorie; de toeslag is geen artikel.' );
		$this->assertSame( 2, $cijfers['artikelen'][0]['stuks'] );
		$this->assertSame( 'SCH-1', $cijfers['artikelen'][0]['sku'] );
		$this->assertSame( 1, $cijfers['toeslag']['keer'] );
		$this->assertEqualsWithDelta( 12.5, $cijfers['toeslag']['bedrag'], 0.01 );
		$this->assertSame( 1, HDP_Gebruik::kerncijfers( 30 )['bestellingen'], 'De beheerder telt niet mee.' );
	}

	public function test_vergeten_winkelwagen() {
		$sleutel = '_woocommerce_persistent_cart_' . get_current_blog_id();
		update_user_meta( $this->collega, $sleutel, array( 'cart' => array( 'a' => array( 'product_id' => 1, 'quantity' => 3, 'line_total' => 45.5 ) ) ) );
		update_user_meta( $this->collega, HDP_Gebruik::LAATST_META, time() - 5 * DAY_IN_SECONDS );
		update_user_meta( $this->hoofd, $sleutel, array( 'cart' => array() ) );

		$wagens = HDP_Gebruik::open_winkelwagens();
		$this->assertCount( 1, $wagens, 'Een lege wagen telt niet.' );
		$this->assertSame( $this->hoofd, $wagens[0]['bedrijf_id'] );
		$this->assertSame( 3, $wagens[0]['stuks'] );
		$this->assertEqualsWithDelta( 45.5, $wagens[0]['waarde'], 0.01 );
		$this->assertTrue( $wagens[0]['vergeten'] );
	}

	/* --- Vraagt aandacht ------------------------------------------------- */

	public function test_aandacht_leeg_als_alles_in_orde_is() {
		update_user_meta( $this->hoofd, HDP_Gebruik::LAATST_META, time() );
		$this->assertSame( array(), HDP_Gebruik::aandacht() );
	}

	public function test_aandacht_bij_zoeken_zonder_resultaat_en_vergeten_wagen() {
		HDP_Gebruik::noteer( 'webshopzoek', 'zaaischijf xl', 0, $this->hoofd );
		update_user_meta( $this->hoofd, '_woocommerce_persistent_cart_' . get_current_blog_id(), array( 'cart' => array( 'a' => array( 'product_id' => 1, 'quantity' => 1, 'line_total' => 10 ) ) ) );
		update_user_meta( $this->hoofd, HDP_Gebruik::LAATST_META, time() - 10 * DAY_IN_SECONDS );

		$teksten = implode( ' | ', wp_list_pluck( HDP_Gebruik::aandacht(), 'tekst' ) );
		$this->assertStringContainsString( 'gevulde winkelwagen', $teksten );
		$this->assertStringContainsString( 'niets opleverde', $teksten );
	}

	public function test_stilgevallen_bedrijf_in_aandacht() {
		update_user_meta( $this->hoofd, HDP_Gebruik::LAATST_META, time() - 40 * DAY_IN_SECONDS );
		$this->assertStringContainsString( 'niet geweest', implode( ' ', wp_list_pluck( HDP_Gebruik::aandacht(), 'tekst' ) ) );

		// Nog nooit geweest is geen "stilgevallen": daarvoor is de bellijst.
		update_user_meta( $this->hoofd, HDP_Gebruik::LAATST_META, '' );
		$this->assertSame( array(), HDP_Gebruik::aandacht() );
	}

	/* --- Het scherm ------------------------------------------------------ */

	public function test_alle_tabbladen_tonen_zonder_fouten() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		HDP_Gebruik::noteer( 'bezoek', 'computer|nl', 0, $this->hoofd );
		foreach ( array_keys( HDP_Gebruik_Scherm::TABS ) as $tab ) {
			$_GET['tab'] = $tab;
			ob_start();
			HDP_Gebruik_Scherm::render();
			$html = ob_get_clean();
			$this->assertStringContainsString( 'nav-tab-active', $html, $tab );
			$this->assertStringContainsString( HDP_Gebruik_Scherm::TABS[ $tab ] === 'Documenten & zoeken' ? 'Documenten &amp; zoeken' : HDP_Gebruik_Scherm::TABS[ $tab ], $html );
		}
		unset( $_GET['tab'] );
		$_GET['tab'] = 'dealers';
		ob_start();
		HDP_Gebruik_Scherm::render();
		$this->assertStringContainsString( 'Loonbedrijf Jansen', ob_get_clean() );
		unset( $_GET['tab'] );
	}
}
