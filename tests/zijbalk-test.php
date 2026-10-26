<?php

/**
 * Test het zijmenu van het welkomscherm.
 *
 * Het gevoelige punt is dat het menu zijn onderdelen uit de
 * portaalkaarten van de startpagina haalt en niet uit een lijst in de
 * code. Dat is met opzet — zo blijft het beheer in de pagina-editor — maar
 * het betekent ook dat een wijziging daar meteen het menu raakt.
 *
 * @group hdp-zijbalk
 */
class Zijbalk_Test extends WP_UnitTestCase {

	private $dealer_id;
	private $pagina_id;

	public function setUp(): void {
		parent::setUp();
		HDP_Roles::activate();

		$this->dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $this->dealer_id, 'hdp_goedgekeurd', '1' );
		wp_set_current_user( $this->dealer_id );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		update_option( 'page_on_front', 0 );
		update_option( 'show_on_front', 'posts' );
		parent::tearDown();
	}

	/**
	 * Zet een startpagina klaar met de opgegeven kaarten.
	 *
	 * @param array $kaarten Lijst met attributen per portaalkaart.
	 */
	private function maak_startpagina( $kaarten ) {
		$blokken = '';
		foreach ( $kaarten as $attrs ) {
			$blokken .= '<!-- wp:homburg/portaal-kaart ' . wp_json_encode( $attrs ) . ' /-->' . "\n";
		}

		$this->pagina_id = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Dealerportaal',
				'post_content' => '<!-- wp:group {"className":"hdp-kaarten-sectie"} --><div class="wp-block-group hdp-kaarten-sectie">'
					. $blokken
					. '</div><!-- /wp:group -->',
			)
		);

		// Allebei: zonder show_on_front blijft is_front_page() onwaar, ook al
		// staat page_on_front goed.
		update_option( 'show_on_front', 'page' );
		update_option( 'page_on_front', $this->pagina_id );
	}

	private function titels() {
		return array_map(
			static function ( $onderdeel ) {
				return $onderdeel['titel'];
			},
			HDP_Zijbalk::onderdelen()
		);
	}

	private function groepen( $onderdelen ) {
		$methode = new ReflectionMethod( 'HDP_Zijbalk', 'verdeel_over_groepen' );
		$methode->setAccessible( true );

		return $methode->invoke( null, $onderdelen );
	}

	/* --- Waar de onderdelen vandaan komen ------------------------------- */

	public function test_het_menu_volgt_de_kaarten_van_de_startpagina() {
		$this->maak_startpagina(
			array(
				array( 'titel' => 'Webshop', 'icoon' => 'webshop', 'url' => '/shop/' ),
				array( 'titel' => 'Downloads', 'icoon' => 'downloads', 'url' => '/downloads/' ),
			)
		);

		$this->assertSame( array( 'Webshop', 'Downloads' ), $this->titels() );
	}

	/** De volgorde op de pagina is de volgorde in het menu. */
	public function test_de_volgorde_van_de_pagina_blijft_behouden() {
		$this->maak_startpagina(
			array(
				array( 'titel' => 'Garantie', 'icoon' => 'garantie', 'url' => '/garantie/' ),
				array( 'titel' => 'Webshop', 'icoon' => 'webshop', 'url' => '/shop/' ),
			)
		);

		$this->assertSame( array( 'Garantie', 'Webshop' ), $this->titels() );
	}

	/**
	 * Een kaart zonder link kan nergens heen; die hoort niet als dode regel
	 * in het menu te belanden.
	 */
	public function test_een_kaart_zonder_link_komt_niet_in_het_menu() {
		$this->maak_startpagina(
			array(
				array( 'titel' => 'Webshop', 'icoon' => 'webshop', 'url' => '/shop/' ),
				array( 'titel' => 'Nog leeg', 'icoon' => 'content', 'url' => '' ),
			)
		);

		$this->assertSame( array( 'Webshop' ), $this->titels() );
	}

	/** De webshop-link staat in de instellingen, niet op de kaart zelf. */
	public function test_een_link_uit_de_instellingen_wordt_gevolgd() {
		update_option( 'hdp_instellingen', array( 'webshop_url' => 'https://winkel.example.com/' ) );
		$this->maak_startpagina(
			array(
				array( 'titel' => 'Webshop', 'icoon' => 'webshop', 'url' => '', 'instellingSleutel' => 'webshop_url' ),
			)
		);

		$onderdelen = HDP_Zijbalk::onderdelen();

		$this->assertCount( 1, $onderdelen );
		$this->assertSame( 'https://winkel.example.com/', $onderdelen[0]['url'] );
	}

	/* --- De kopjes ------------------------------------------------------ */

	public function test_de_onderdelen_komen_onder_het_juiste_kopje() {
		$onderdelen = array(
			array( 'icoon' => 'webshop', 'titel' => 'Webshop' ),
			array( 'icoon' => 'garantie', 'titel' => 'Garantie' ),
			array( 'icoon' => 'downloads', 'titel' => 'Downloads' ),
			array( 'icoon' => 'huisstijl', 'titel' => 'Huisstijl' ),
		);

		$groepen = $this->groepen( $onderdelen );

		$this->assertSame( array( 'Webshop' ), wp_list_pluck( $groepen['bestellen'], 'titel' ) );
		$this->assertSame( array( 'Garantie' ), wp_list_pluck( $groepen['service'], 'titel' ) );
		$this->assertSame( array( 'Downloads', 'Huisstijl' ), wp_list_pluck( $groepen['materiaal'], 'titel' ) );
	}

	/**
	 * Een kaart met een icoon dat we niet kennen mag niet uit het menu
	 * vallen; die hoort onderaan terecht te komen.
	 */
	public function test_een_onbekend_icoon_valt_onder_materiaal() {
		$groepen = $this->groepen( array( array( 'icoon' => 'iets-nieuws', 'titel' => 'Nieuw ding' ) ) );

		$this->assertSame( array( 'Nieuw ding' ), wp_list_pluck( $groepen['materiaal'], 'titel' ) );
	}

	/** Een kopje zonder onderdelen hoort niet op het scherm te komen. */
	public function test_een_leeg_kopje_verdwijnt() {
		$groepen = $this->groepen( array( array( 'icoon' => 'webshop', 'titel' => 'Webshop' ) ) );

		$this->assertSame( array( 'bestellen' ), array_keys( $groepen ) );
	}

	/** De kopjes staan in een vaste volgorde: eerst bestellen, dan de rest. */
	public function test_de_kopjes_staan_op_volgorde() {
		$groepen = $this->groepen(
			array(
				array( 'icoon' => 'downloads', 'titel' => 'Downloads' ),
				array( 'icoon' => 'garantie', 'titel' => 'Garantie' ),
				array( 'icoon' => 'webshop', 'titel' => 'Webshop' ),
			)
		);

		$this->assertSame( array( 'bestellen', 'service', 'materiaal' ), array_keys( $groepen ) );
	}

	/* --- Wie het ziet --------------------------------------------------- */

	public function test_een_bezoeker_zonder_toegang_ziet_geen_menu() {
		$this->maak_startpagina(
			array( array( 'titel' => 'Webshop', 'icoon' => 'webshop', 'url' => '/shop/' ) )
		);
		wp_set_current_user( 0 );

		ob_start();
		HDP_Zijbalk::render();

		$this->assertSame( '', trim( ob_get_clean() ) );
	}

	/* --- Home ---------------------------------------------------------- */

	/**
	 * Home staat bovenaan het menu en is daarmee overal de weg terug. Zonder
	 * dat kwam een dealer alleen via het logo of een losse link terug op het
	 * welkomscherm.
	 */
	public function test_home_staat_bovenaan_het_menu() {
		$this->maak_startpagina(
			array( array( 'titel' => 'Webshop', 'icoon' => 'webshop', 'url' => '/shop/' ) )
		);

		ob_start();
		HDP_Zijbalk::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( HDP_I18N::t( 'zijbalk_home' ), $html );
		$this->assertStringContainsString( esc_url( home_url( '/dealerportaal/' ) ), $html );

		// Bovenaan, dus vóór het eerste kopje.
		$this->assertLessThan(
			strpos( $html, HDP_I18N::t( 'zijbalk_groep_bestellen' ) ),
			strpos( $html, HDP_I18N::t( 'zijbalk_home' ) ),
			'Home hoort boven de kopjes te staan.'
		);
	}

	/**
	 * Staat Home in het menu, dan hoeft "terug naar het portaal" niet ook nog
	 * eens boven elke pagina.
	 */
	public function test_de_terugknop_vervalt_zodra_het_menu_er_staat() {
		$this->maak_startpagina(
			array( array( 'titel' => 'Webshop', 'icoon' => 'webshop', 'url' => '/shop/' ) )
		);
		$this->go_to( get_permalink( $this->pagina_id ) );
		wp_set_current_user( $this->dealer_id );

		ob_start();
		HDP_Paginakop::render(
			array_merge( array( 'titel' => 'Downloads' ), HDP_Paginakop::terug_naar_portaal() )
		);
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Downloads', $html );
		$this->assertStringNotContainsString( HDP_I18N::t( 'terug_naar_portaal' ), $html );
	}

	/**
	 * Maar een terugknop naar iets anders dan het portaal blijft staan: die
	 * wijst naar een pagina die niet in het menu voorkomt.
	 */
	public function test_een_andere_terugknop_blijft_staan() {
		$this->maak_startpagina(
			array( array( 'titel' => 'Webshop', 'icoon' => 'webshop', 'url' => '/shop/' ) )
		);
		$this->go_to( get_permalink( $this->pagina_id ) );
		wp_set_current_user( $this->dealer_id );

		ob_start();
		HDP_Paginakop::render(
			array(
				'titel'       => 'Ticket T100001',
				'terug_url'   => home_url( '/garantie/' ),
				'terug_tekst' => 'Terug naar garantie',
			)
		);
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Terug naar garantie', $html );
	}

	/* --- Op welke pagina's --------------------------------------------- */

	/**
	 * De opmaak hangt aan een klasse op <body>: met zijmenu ziet de balk
	 * bovenaan en de voettekst er anders uit. Zonder die klasse zou op een
	 * gewone pagina een lege donkere kolom blijven staan.
	 */
	public function test_het_welkomscherm_krijgt_de_app_klasse() {
		$this->maak_startpagina(
			array( array( 'titel' => 'Webshop', 'icoon' => 'webshop', 'url' => '/shop/' ) )
		);
		$this->go_to( get_permalink( $this->pagina_id ) );
		wp_set_current_user( $this->dealer_id );

		$this->assertTrue( HDP_Zijbalk::gebruikt_zijmenu() );
		$this->assertContains( 'hdp-app-layout', HDP_Zijbalk::body_class( array() ) );
	}

	/** Een pagina met het portaalsjabloon hoort het menu ook te krijgen. */
	public function test_een_pagina_met_het_portaalsjabloon_krijgt_het_menu() {
		$pagina = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Downloads',
			)
		);
		update_post_meta( $pagina, '_wp_page_template', HDP_Zijbalk::SJABLOON );

		$this->go_to( get_permalink( $pagina ) );
		wp_set_current_user( $this->dealer_id );

		$this->assertTrue( HDP_Zijbalk::gebruikt_zijmenu() );
	}

	/** Een gewone pagina (privacyverklaring) blijft zoals hij was. */
	public function test_een_gewone_pagina_krijgt_geen_menu() {
		$pagina = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Privacyverklaring',
			)
		);

		$this->go_to( get_permalink( $pagina ) );
		wp_set_current_user( $this->dealer_id );

		$this->assertFalse( HDP_Zijbalk::gebruikt_zijmenu() );
		$this->assertNotContains( 'hdp-app-layout', HDP_Zijbalk::body_class( array() ) );
	}

	/** En een bezoeker zonder toegang nergens. */
	public function test_zonder_toegang_nooit_de_app_klasse() {
		$this->maak_startpagina(
			array( array( 'titel' => 'Webshop', 'icoon' => 'webshop', 'url' => '/shop/' ) )
		);
		$this->go_to( get_permalink( $this->pagina_id ) );
		wp_set_current_user( 0 );

		$this->assertFalse( HDP_Zijbalk::gebruikt_zijmenu() );
	}

	public function test_een_goedgekeurde_dealer_ziet_het_menu_met_kopjes() {
		$this->maak_startpagina(
			array(
				array( 'titel' => 'Webshop', 'icoon' => 'webshop', 'url' => '/shop/' ),
				array( 'titel' => 'Garantie', 'icoon' => 'garantie', 'url' => '/garantie/' ),
			)
		);

		ob_start();
		HDP_Zijbalk::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'hdp-zijbalk', $html );
		$this->assertStringContainsString( 'Webshop', $html );
		$this->assertStringContainsString( HDP_I18N::t( 'zijbalk_groep_bestellen' ), $html );
		$this->assertStringContainsString( HDP_I18N::t( 'zijbalk_groep_service' ), $html );
		// Materiaal is leeg in deze opzet en hoort dus niet te verschijnen.
		$this->assertStringNotContainsString( HDP_I18N::t( 'zijbalk_groep_materiaal' ), $html );
	}

	/** Bestellingen hoort direct onder de webshop, de (externe) configurator daaronder. */
	public function test_bestellingen_staat_tussen_webshop_en_configurator() {
		$this->maak_startpagina(
			array(
				array( 'titel' => 'Webshop', 'icoon' => 'webshop', 'url' => '/shop/' ),
				array( 'titel' => 'Productconfigurator', 'icoon' => 'configurator', 'url' => 'https://quootz.com/homburg/' ),
			)
		);

		ob_start();
		HDP_Zijbalk::render();
		$html = ob_get_clean();

		$webshop      = strpos( $html, '>Webshop<' );
		$bestellingen = strpos( $html, '>' . HDP_I18N::t( 'zijbalk_bestellingen' ) . '<' );
		$configurator = strpos( $html, '>Productconfigurator<' );

		$this->assertLessThan( $bestellingen, $webshop );
		$this->assertLessThan( $configurator, $bestellingen );
	}

	/** Een link naar een andere site krijgt het pijltje, ook zonder het vinkje "nieuw tabblad". */
	public function test_een_externe_link_opent_in_een_nieuw_tabblad() {
		$this->maak_startpagina(
			array(
				array( 'titel' => 'Webshop', 'icoon' => 'webshop', 'url' => '/shop/' ),
				array( 'titel' => 'Productconfigurator', 'icoon' => 'configurator', 'url' => 'https://quootz.com/homburg/' ),
			)
		);

		$onderdelen = HDP_Zijbalk::onderdelen();

		$this->assertFalse( $onderdelen[0]['nieuwe_tab'], 'De eigen webshop blijft in hetzelfde tabblad.' );
		$this->assertTrue( $onderdelen[1]['nieuwe_tab'] );
	}
}
