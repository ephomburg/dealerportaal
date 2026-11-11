<?php

/**
 * Test de uitleg voor dealers (HDP_Uitleg) en het inzicht voor Homburg
 * (uitbreidingen van HDP_Gebruik, en opnieuw versturen naar PowerAll).
 *
 * Wat hier fout kan gaan: de uitleg komt na "Begrepen" steeds terug, of is
 * niet terug te halen; een stap verwijst naar iets wat deze dealer niet
 * heeft; de cijfers tellen testaccounts mee; zoeken in de webshop telt bij
 * elke klik op een filter opnieuw; een Excel-cel wordt een formule; een
 * bestelling die al in PowerAll staat kan nog eens verstuurd worden.
 *
 * @group hdp-gebruik
 */
class Uitleg_Inzicht_Test extends WP_UnitTestCase {

	private $dealer;

	public function setUp(): void {
		parent::setUp();
		HDP_Roles::activate();
		delete_option( HDP_Gebruik::DB_OPTIE );
		HDP_Gebruik::controleer_tabel();
		global $wpdb;
		$wpdb->query( 'DELETE FROM ' . HDP_Gebruik::tabel() ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$this->dealer = $this->maak_dealer();
		wp_set_current_user( $this->dealer );
		unset( $_GET['uitleg'], $_GET['zoek'], $_COOKIE[ HDP_I18N::COOKIE ] );
	}

	public function tearDown(): void {
		unset( $_GET['uitleg'], $_GET['zoek'], $_COOKIE[ HDP_I18N::COOKIE ] );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function maak_dealer( $relatie = '' ) {
		$id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $id, 'hdp_goedgekeurd', '1' );
		if ( $relatie ) {
			update_user_meta( $id, HDP_PowerAll_Dealers::META_RELATIE, $relatie );
		}
		return $id;
	}

	/* --- Uitleg -------------------------------------------------------- */

	public function test_uitleg_staat_er_tot_begrepen_en_is_terug_te_halen() {
		$this->assertTrue( HDP_Uitleg::moet_tonen() );

		update_user_meta( $this->dealer, HDP_Uitleg::META, time() );
		$this->assertFalse( HDP_Uitleg::moet_tonen(), 'Na Begrepen staat hij er niet meer vanzelf.' );

		$_GET['uitleg'] = '1';
		$this->assertTrue( HDP_Uitleg::moet_tonen(), 'Via het accountmenu komt hij terug.' );
	}

	public function test_uitleg_toont_de_stappen_met_begrepen_knop() {
		ob_start();
		HDP_Uitleg::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Zo werkt het dealerportaal', $html );
		$this->assertStringContainsString( 'Begrepen', $html );
		$this->assertStringContainsString( home_url( '/downloads/' ), $html );
		$this->assertStringContainsString( HDP_Trainingen::url(), $html );
		$this->assertStringContainsString( 'name="' . HDP_Uitleg::VELD . '"', $html );
		$this->assertStringContainsString( 'name="_wpnonce"', $html );
	}

	public function test_teruggehaalde_uitleg_heeft_sluiten_in_plaats_van_begrepen() {
		update_user_meta( $this->dealer, HDP_Uitleg::META, time() );
		$_GET['uitleg'] = '1';
		ob_start();
		HDP_Uitleg::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Sluiten', $html );
		$this->assertStringNotContainsString( HDP_Uitleg::VELD, $html );
	}

	public function test_uitgelogd_geen_uitleg() {
		wp_set_current_user( 0 );
		ob_start();
		HDP_Uitleg::render();
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_garantie_alleen_als_die_er_is() {
		$iconen = wp_list_pluck( HDP_Uitleg::stappen(), 'icoon' );
		if ( HDP_Garantie::beschikbaar() ) {
			$this->assertContains( 'garantie', $iconen );
		} else {
			$this->assertNotContains( 'garantie', $iconen );
		}
		$this->assertContains( 'downloads', $iconen );
	}

	public function test_uitleg_ook_in_het_frans() {
		$_COOKIE[ HDP_I18N::COOKIE ] = 'fr';
		$this->assertSame( 'Comment fonctionne le portail concessionnaire', HDP_I18N::t( 'uitleg_kop' ) );
		$this->assertSame( 'Compris', HDP_I18N::t( 'uitleg_begrepen' ) );
		$this->assertSame( 'Explications sur le portail', HDP_I18N::t( 'uitleg_menu' ) );
	}

	/* --- Zoeken in de webshop ------------------------------------------ */

	private function rijen( $soort ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT sleutel, aantal FROM ' . HDP_Gebruik::tabel() . ' WHERE soort = %s ORDER BY id', $soort ) ); // phpcs:ignore WordPress.DB
	}

	public function test_webshopzoek_telt_een_keer_per_term() {
		if ( ! function_exists( 'is_shop' ) ) {
			$this->markTestSkipped( 'WooCommerce is niet geladen.' );
		}
		// In de testomgeving is er geen winkelpagina; maak er een, zoals op de site.
		$winkel = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Webshop' ) );
		update_option( 'woocommerce_shop_page_id', $winkel );
		$this->go_to( add_query_arg( 'zoek', 'Strooischijf', get_permalink( $winkel ) ) );
		$this->assertTrue( is_shop() );
		$_GET['zoek'] = 'Strooischijf';
		HDP_Gebruik::noteer_webshopzoek();
		HDP_Gebruik::noteer_webshopzoek(); // Bijv. een filter aangeklikt: dezelfde term.

		$rijen = $this->rijen( 'webshopzoek' );
		$this->assertCount( 1, $rijen );
		$this->assertSame( 'strooischijf', $rijen[0]->sleutel );

		$niets = HDP_Gebruik::zoektermen( 30, false, 20, 'webshopzoek' );
		$this->assertSame( 'strooischijf', $niets[0]->term );
		$this->assertSame( array(), HDP_Gebruik::zoektermen( 30, false, 20, 'zoek' ), 'De zoekbalk en de webshop blijven gescheiden.' );
	}

	public function test_webshopzoek_niet_buiten_de_webshop() {
		$this->go_to( home_url( '/?zoek=hardi' ) );
		$_GET['zoek'] = 'hardi';
		HDP_Gebruik::noteer_webshopzoek();
		$this->assertCount( 0, $this->rijen( 'webshopzoek' ) );
	}

	/* --- Uitnodigingen ------------------------------------------------- */

	public function test_uitrol_telt_elke_stap() {
		$niet        = $this->maak_dealer( '9001' );
		$uitgenodigd = $this->maak_dealer( '9002' );
		$verlopen    = $this->maak_dealer( '9003' );
		$gekozen     = $this->maak_dealer( '9004' );
		$test        = $this->maak_dealer( '9005' );
		update_user_meta( $uitgenodigd, HDP_PowerAll_Dealers::META_UITNODIGING, array( 'hash' => 'x', 'tot' => time() + DAY_IN_SECONDS, 'verstuurd' => time() ) );
		update_user_meta( $verlopen, HDP_PowerAll_Dealers::META_UITNODIGING, array( 'hash' => 'x', 'tot' => time() - DAY_IN_SECONDS, 'verstuurd' => time() - 20 * DAY_IN_SECONDS ) );
		update_user_meta( $gekozen, HDP_PowerAll_Dealers::META_WACHTWOORD, time() );
		update_user_meta( $gekozen, HDP_Gebruik::LAATST_META, time() );
		update_user_meta( $test, HDP_Gebruik::TEST_META, '1' );

		$stand = HDP_Gebruik::uitrol();

		$this->assertSame( 4, $stand['totaal'], 'Het testaccount telt niet mee.' );
		$this->assertSame( 1, $stand['niet'] );
		$this->assertSame( 1, $stand['uitgenodigd'] );
		$this->assertSame( 1, $stand['verlopen'] );
		$this->assertSame( 1, $stand['gekozen'] );
		$this->assertSame( 1, $stand['geweest'] );
		$this->assertEqualsCanonicalizing( array( $uitgenodigd, $verlopen ), wp_list_pluck( $stand['nalopen'], 'id' ) );
		$this->assertSame( 'niet', HDP_PowerAll_Dealers::accountstatus( $niet )['code'] );
	}

	/* --- Trainingen ---------------------------------------------------- */

	public function test_trainingen_aanmeldingen_en_gemiddeld_cijfer() {
		HDP_Trainingen::controleer_tabel();
		global $wpdb;
		$training = self::factory()->post->create( array( 'post_type' => HDP_Trainingen::POST_TYPE, 'post_title' => 'Spuittechniek' ) );
		$tweede   = $this->maak_dealer();
		$test     = $this->maak_dealer();
		update_user_meta( $test, HDP_Gebruik::TEST_META, '1' );
		$ids = array();
		foreach ( array( $this->dealer, $this->dealer, $tweede, $test ) as $user ) {
			$wpdb->insert( HDP_Trainingen::tabel(), array( 'training_id' => $training, 'training_titel' => 'Spuittechniek', 'user_id' => $user, 'deelnemer_naam' => 'X', 'deelnemer_email' => 'x@example.com', 'antwoorden' => '[]', 'status' => 'aangemeld', 'aangemaakt' => current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB
			$ids[] = (int) $wpdb->insert_id;
		}
		foreach ( array( 0 => 8, 2 => 6, 3 => 1 ) as $index => $cijfer ) {
			$wpdb->insert( HDP_Training_Reviews::tabel(), array( 'aanmelding_id' => $ids[ $index ], 'training_id' => $training, 'cijfer' => $cijfer, 'reactie' => '', 'aangemaakt' => current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB
		}

		$lijst = HDP_Gebruik::trainingen( 30 );

		$this->assertCount( 1, $lijst );
		$this->assertSame( 'Spuittechniek', $lijst[0]['titel'] );
		$this->assertSame( 3, $lijst[0]['aanmeldingen'], 'Het testaccount telt niet mee.' );
		$this->assertSame( 2, $lijst[0]['bedrijven'] );
		$this->assertSame( 2, $lijst[0]['cijfers'] );
		$this->assertEqualsWithDelta( 7.0, $lijst[0]['gemiddeld'], 0.001 );
	}

	/* --- Per dealer en export ------------------------------------------ */

	public function test_per_dealer_en_export() {
		HDP_Gebruik::noteer( 'bezoek', '', 0, $this->dealer );
		HDP_Gebruik::noteer( 'download', '12', 0, $this->dealer );
		HDP_Gebruik::noteer( 'webshopzoek', 'zaaischijf', 0, $this->dealer );
		update_user_meta( $this->dealer, 'billing_company', '=Loonbedrijf' );

		$c = HDP_Gebruik::dealerlijst( 90, $this->dealer )[0];
		$this->assertSame( 1, $c['bezoekdagen'] );
		$this->assertSame( 1, $c['downloads'] );
		$this->assertSame( array( 'zaaischijf' ), $c['termen'] );

		$rijen = HDP_Gebruik::export_rijen( 30 );
		$this->assertSame( 'Bedrijf', $rijen[0][0] );
		$this->assertCount( 2, $rijen, 'Kopregel plus deze ene dealer.' );
		$this->assertSame( "'=Loonbedrijf", HDP_Gebruik::veilige_cel( $rijen[1][0] ), 'Geen formule in Excel.' );
		$this->assertSame( 'Zelf aangemeld', $rijen[1][7] );
	}

	public function test_profielblok_alleen_voor_beheerder() {
		ob_start();
		HDP_Gebruik_Scherm::render_profiel( get_userdata( $this->dealer ) );
		$this->assertSame( '', ob_get_clean(), 'Een dealer ziet dit niet.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		HDP_Gebruik_Scherm::render_profiel( get_userdata( $this->dealer ) );
		$this->assertStringContainsString( 'Portaalgebruik', ob_get_clean() );
	}

	/* --- Opnieuw naar PowerAll ----------------------------------------- */

	public function test_opnieuw_versturen_alleen_bij_een_fout() {
		if ( ! function_exists( 'wc_create_order' ) ) {
			$this->markTestSkipped( 'WooCommerce is niet geladen.' );
		}
		$order = wc_create_order();
		$this->assertArrayNotHasKey( HDP_PowerAll_Orders::ACTIE_OPNIEUW, HDP_PowerAll_Orders::bestelactie( array(), $order ) );

		$order->update_meta_data( HDP_PowerAll_Orders::META_STATUS, 'fout' );
		$order->save();
		$this->assertArrayHasKey( HDP_PowerAll_Orders::ACTIE_OPNIEUW, HDP_PowerAll_Orders::bestelactie( array(), $order ) );

		$order->update_meta_data( HDP_PowerAll_Orders::META_ID, 'abc' );
		$order->save();
		$this->assertArrayNotHasKey( HDP_PowerAll_Orders::ACTIE_OPNIEUW, HDP_PowerAll_Orders::bestelactie( array(), $order ), 'Staat al in PowerAll: nooit nog eens.' );
	}

	public function test_proefbestelling_gaat_nooit_alsnog_echt_naar_powerall() {
		if ( ! function_exists( 'wc_create_order' ) ) {
			$this->markTestSkipped( 'WooCommerce is niet geladen.' );
		}
		$verzoeken = 0;
		$tel       = static function ( $pre, $args, $url ) use ( &$verzoeken ) {
			++$verzoeken;
			return array( 'response' => array( 'code' => 500 ), 'body' => '{}' );
		};
		add_filter( 'pre_http_request', $tel, 10, 3 );
		update_option( HDP_PowerAll_Orders::OPTION, array( 'modus' => 'aan' ) );

		$proef = wc_create_order( array( 'status' => 'processing' ) );
		$proef->update_meta_data( HDP_PowerAll_Orders::META_STATUS, 'proef' );
		$proef->save();

		$this->assertSame( 'overgeslagen', HDP_PowerAll_Orders::verstuur( $proef->get_id() ) );
		HDP_PowerAll_Orders::herhaal();

		remove_filter( 'pre_http_request', $tel, 10 );
		delete_option( HDP_PowerAll_Orders::OPTION );
		$this->assertSame( 0, $verzoeken, 'Er is niets naar PowerAll gestuurd.' );
		$this->assertSame( 'proef', wc_get_order( $proef->get_id() )->get_meta( HDP_PowerAll_Orders::META_STATUS ) );
	}

	public function test_fouten_bij_powerall_in_het_overzicht() {
		if ( ! function_exists( 'wc_create_order' ) ) {
			$this->markTestSkipped( 'WooCommerce is niet geladen.' );
		}
		$goed = wc_create_order();
		$goed->update_meta_data( HDP_PowerAll_Orders::META_STATUS, 'verstuurd' );
		$goed->save();
		$fout = wc_create_order();
		$fout->update_meta_data( HDP_PowerAll_Orders::META_STATUS, 'fout' );
		$fout->save();

		$this->assertSame( array( $fout->get_id() ), array_map( static function ( $o ) { return $o->get_id(); }, HDP_Gebruik::powerall_fouten() ) );
	}
}
