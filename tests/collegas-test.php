<?php

/**
 * Test collega's (HDP_Collegas): het hoofdaccount van een dealer geeft
 * collega's een eigen inlog.
 *
 * Wat hier fout kan gaan: een collega ziet de bestellingen of claims van een
 * ander bedrijf, een verwijderde collega komt nog binnen, een collega zonder
 * recht kan toch bestellen of garantie doen, of er komen meer dan vijf.
 *
 * @group hdp-collegas
 */
class Collegas_Test extends WP_UnitTestCase {

	private $hoofd;
	private $mails = array();

	public function setUp(): void {
		parent::setUp();
		HDP_Roles::activate();
		$this->hoofd = $this->dealer( 'info@loonbedrijf.test', 'Loonbedrijf Jansen' );
		update_user_meta( $this->hoofd, 'hdp_merken', 'Väderstad' );
		update_user_meta( $this->hoofd, HDP_PowerAll_Dealers::META_RELATIE, '23524' );
		$this->mails = array();
		add_filter( 'pre_wp_mail', array( $this, 'vang_mail' ), 10, 2 );
	}

	public function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'vang_mail' ), 10 );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function vang_mail( $niets, $mail ) {
		$this->mails[] = $mail;
		return true;
	}

	private function dealer( $email, $bedrijf ) {
		$id = self::factory()->user->create( array( 'role' => 'dealer', 'user_email' => $email, 'display_name' => $bedrijf ) );
		update_user_meta( $id, 'hdp_goedgekeurd', '1' );
		update_user_meta( $id, 'billing_company', $bedrijf );
		return $id;
	}

	private function collega( $naam = 'Frits Garantie', $email = 'frits@loonbedrijf.test', $rechten = array( 'garantie' => true ) ) {
		$collega = HDP_Collegas::voeg_toe( $this->hoofd, $naam, $email, $rechten );
		$this->assertInstanceOf( WP_User::class, $collega );
		return $collega->ID;
	}

	public function test_collega_toevoegen_stuurt_een_uitnodiging_met_link() {
		$id = $this->collega();

		$this->assertSame( $this->hoofd, HDP_Collegas::hoofd_id( $id ) );
		$this->assertTrue( HDP_Roles::mag_portaal_zien( $id ) );
		$this->assertCount( 1, $this->mails );
		$this->assertSame( 'frits@loonbedrijf.test', $this->mails[0]['to'] );
		$this->assertStringContainsString( 'Loonbedrijf Jansen', $this->mails[0]['message'] );
		$this->assertMatchesRegularExpression( '/hdp_welkom=' . $id . '&(amp;)?sleutel=\w{32}/', $this->mails[0]['message'] );
		$this->assertSame( 'uitgenodigd', HDP_PowerAll_Dealers::accountstatus( $id )['code'] );
	}

	public function test_collega_deelt_merken_en_klantnummer_met_het_hoofdaccount() {
		$id = $this->collega();
		$this->assertSame( 'Väderstad', get_user_meta( $id, 'hdp_merken', true ) );
		$this->assertSame( '23524', (string) HDP_PowerAll_Prijzen::relatie( $id ) );

		update_user_meta( $this->hoofd, 'hdp_merken', 'Väderstad, HARDI' );
		$this->assertSame( 'Väderstad, HARDI', get_user_meta( $id, 'hdp_merken', true ), 'Altijd gelijk met het hoofdaccount.' );
	}

	public function test_maximaal_vijf_collegas() {
		for ( $i = 1; $i <= 5; $i++ ) {
			$this->collega( 'Collega ' . $i, 'c' . $i . '@loonbedrijf.test' );
		}
		$zesde = HDP_Collegas::voeg_toe( $this->hoofd, 'Zesde', 'c6@loonbedrijf.test', array() );
		$this->assertWPError( $zesde );
		$this->assertSame( 'max', $zesde->get_error_code() );
		$this->assertSame( 5, HDP_Collegas::MAX );

		// Na verwijderen is er weer plek.
		HDP_Collegas::verwijder( $this->hoofd, HDP_Collegas::collegas( $this->hoofd )[0]->ID );
		$this->assertInstanceOf( WP_User::class, HDP_Collegas::voeg_toe( $this->hoofd, 'Zesde', 'c6@loonbedrijf.test', array() ) );
	}

	public function test_geen_dubbel_account_en_een_collega_kan_zelf_geen_collegas_toevoegen() {
		$andere = $this->dealer( 'info@ander.test', 'Ander bedrijf' );
		$this->assertSame( 'bestaat', HDP_Collegas::voeg_toe( $this->hoofd, 'Iemand', 'info@ander.test', array() )->get_error_code() );

		$id = $this->collega();
		$this->assertFalse( HDP_Collegas::mag_beheren( $id ) );
		$this->assertSame( 'geen_toegang', HDP_Collegas::voeg_toe( $id, 'Kees', 'kees@loonbedrijf.test', array() )->get_error_code() );
		$this->assertTrue( HDP_Collegas::mag_beheren( $andere ) );
	}

	public function test_rechten_per_collega() {
		$id = $this->collega( 'Frits', 'frits@loonbedrijf.test', array( 'garantie' => true ) );
		$this->assertFalse( HDP_Collegas::mag( $id, 'bestellen' ) );
		$this->assertTrue( HDP_Collegas::mag( $id, 'garantie' ) );
		$this->assertTrue( HDP_Collegas::mag( $this->hoofd, 'bestellen' ), 'Het hoofdaccount mag alles.' );

		$this->assertTrue( HDP_Collegas::zet_rechten( $this->hoofd, $id, array( 'bestellen' => true ) ) );
		$this->assertTrue( HDP_Collegas::mag( $id, 'bestellen' ) );
		$this->assertFalse( HDP_Collegas::mag( $id, 'garantie' ) );

		// Een ander bedrijf kan de rechten van deze collega niet wijzigen.
		$andere = $this->dealer( 'info@ander.test', 'Ander bedrijf' );
		$this->assertFalse( HDP_Collegas::zet_rechten( $andere, $id, array( 'garantie' => true ) ) );
	}

	public function test_zonder_bestelrecht_niets_in_de_winkelwagen() {
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			$this->markTestSkipped( 'WooCommerce is niet geladen.' );
		}
		$product = new WC_Product_Simple();
		$product->set_regular_price( '10' );
		$product->save();

		wp_set_current_user( $this->collega() );
		$this->assertFalse( HDP_Collegas::mag_kopen( true, $product ) );
		wp_set_current_user( $this->hoofd );
		$this->assertTrue( HDP_Collegas::mag_kopen( true, $product ) );
	}

	public function test_verwijderde_collega_komt_niet_meer_binnen_maar_bestellingen_blijven() {
		$id = $this->collega();
		$this->assertTrue( HDP_Collegas::verwijder( $this->hoofd, $id ) );

		$this->assertFalse( HDP_Roles::mag_portaal_zien( $id ) );
		$this->assertSame( array(), HDP_Collegas::collegas( $this->hoofd ) );
		$this->assertContains( $id, HDP_Collegas::klant_ids( $this->hoofd ), 'Zijn bestellingen horen nog bij het bedrijf.' );

		// Weer toevoegen zet hem terug.
		$terug = HDP_Collegas::voeg_toe( $this->hoofd, 'Frits', 'frits@loonbedrijf.test', array() );
		$this->assertSame( $id, $terug->ID );
		$this->assertTrue( HDP_Roles::mag_portaal_zien( $id ) );
	}

	public function test_bedrijf_niet_meer_goedgekeurd_dan_collega_ook_niet() {
		$id = $this->collega();
		update_user_meta( $this->hoofd, 'hdp_goedgekeurd', '' );
		$this->assertFalse( HDP_Roles::mag_portaal_zien( $id ) );
	}

	public function test_hoofdaccount_verwijderd_dan_collegas_eruit() {
		$id = $this->collega();
		wp_delete_user( $this->hoofd );
		$this->assertFalse( HDP_Roles::mag_portaal_zien( $id ) );
	}

	public function test_bestellingen_van_het_bedrijf_en_niet_van_een_ander() {
		if ( ! function_exists( 'wc_create_order' ) ) {
			$this->markTestSkipped( 'WooCommerce is niet geladen.' );
		}
		$collega = $this->collega();
		$andere  = $this->dealer( 'info@ander.test', 'Ander bedrijf' );
		$van_hoofd   = wc_create_order( array( 'customer_id' => $this->hoofd ) );
		$van_collega = wc_create_order( array( 'customer_id' => $collega ) );
		$van_ander   = wc_create_order( array( 'customer_id' => $andere ) );

		$ids = wc_get_orders( array( 'customer' => HDP_Collegas::klant_ids( $collega ), 'limit' => -1, 'return' => 'ids' ) );
		sort( $ids );
		$this->assertSame( array( $van_hoofd->get_id(), $van_collega->get_id() ), $ids );

		$this->assertTrue( user_can( $collega, 'view_order', $van_hoofd->get_id() ) );
		$this->assertTrue( user_can( $this->hoofd, 'view_order', $van_collega->get_id() ) );
		$this->assertFalse( user_can( $collega, 'view_order', $van_ander->get_id() ) );
		$this->assertFalse( user_can( $andere, 'view_order', $van_collega->get_id() ) );
	}

	public function test_niemand_ingelogd_geeft_geen_bestellingen_van_gasten() {
		$this->assertSame( array( -1 ), HDP_Collegas::klant_ids( 0 ) );
	}

	public function test_garantie_op_naam_van_het_bedrijf_met_de_indiener_erbij() {
		$id = $this->collega( 'Frits Garantie', 'frits@loonbedrijf.test', array( 'garantie' => true ) );
		wp_set_current_user( $id );

		$velden = HDP_Garantie::dealervelden();
		$this->assertSame( (string) $this->hoofd, $velden['dealer_id'] );
		$this->assertSame( 'Loonbedrijf Jansen', $velden['dealer_bedrijf'] );
		$this->assertSame( "Ingediend door Frits Garantie (frits@loonbedrijf.test).\nGraag snel", HDP_Garantie::met_indiener( 'Graag snel' ) );

		wp_set_current_user( $this->hoofd );
		$this->assertSame( 'Graag snel', HDP_Garantie::met_indiener( 'Graag snel' ), 'Het hoofdaccount zelf: geen extra regel.' );
	}

	public function test_zonder_garantierecht_geen_garantie() {
		$id = $this->collega( 'Kees', 'kees@loonbedrijf.test', array( 'bestellen' => true ) );
		wp_set_current_user( $id );
		$this->assertSame( array(), HDP_Garantie::dealervelden() );
		$this->assertFalse( HDP_Garantie::beschikbaar() );
	}

	public function test_menu_alleen_voor_het_hoofdaccount_en_snel_bestellen_alleen_met_bestelrecht() {
		$items = array( 'orders' => 'Bestellingen', 'snelbestellen' => 'Snel bestellen', 'mijn-trainingen' => 'Mijn trainingen', 'customer-logout' => 'Uitloggen' );

		wp_set_current_user( $this->hoofd );
		$this->assertArrayHasKey( HDP_Collegas::ENDPOINT, HDP_Collegas::menu( $items ) );

		wp_set_current_user( $this->collega() );
		$menu = HDP_Collegas::menu( $items );
		$this->assertArrayNotHasKey( HDP_Collegas::ENDPOINT, $menu );
		$this->assertArrayNotHasKey( 'snelbestellen', $menu );
	}
}
