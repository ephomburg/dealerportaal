<?php

/**
 * Test de "nieuw"-markering: recent toegevoegde bestanden krijgen een label
 * op de downloadspagina en een attentieregel op de portaalstartpagina.
 * Zonder dit blijft het automatisch binnenkomen van bestanden via FileBird
 * voor de dealer onzichtbaar.
 *
 * @group hdp-nieuw
 */
class Nieuw_Markering_Test extends WP_UnitTestCase {

	private $dealer_id;
	private $attributen;

	public function setUp(): void {
		parent::setUp();
		HDP_Roles::activate();

		$this->dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $this->dealer_id, 'hdp_goedgekeurd', '1' );
		wp_set_current_user( $this->dealer_id );

		$this->attributen = array(
			'heroAfbeelding' => 'https://example.test/hero.jpg',
			'titel'          => 'Downloads voor dealers',
			'titelFr'        => 'Téléchargements pour revendeurs',
			'omschrijving'   => 'Intro NL',
			'omschrijvingFr' => 'Intro FR',
		);
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function maak_download( $titel, $dagen_geleden = 0, $merk = '' ) {
		$id = self::factory()->post->create(
			array(
				'post_type'     => HDP_Downloads_CPT::POST_TYPE,
				'post_title'    => $titel,
				'post_date'     => gmdate( 'Y-m-d H:i:s', time() - ( $dagen_geleden * DAY_IN_SECONDS ) ),
				'post_date_gmt' => gmdate( 'Y-m-d H:i:s', time() - ( $dagen_geleden * DAY_IN_SECONDS ) ),
			)
		);
		if ( $merk ) {
			update_post_meta( $id, '_hdp_merk', $merk );
		}
		return $id;
	}

	public function test_recent_bestand_geldt_als_nieuw() {
		$vers = $this->maak_download( 'Verse prijslijst', 2 );

		$this->assertTrue( HDP_Downloads_CPT::is_nieuw( $vers ) );
	}

	public function test_ouder_bestand_geldt_niet_meer_als_nieuw() {
		$oud = $this->maak_download( 'Oude prijslijst', HDP_Downloads_CPT::NIEUW_DAGEN + 5 );

		$this->assertFalse( HDP_Downloads_CPT::is_nieuw( $oud ) );
	}

	public function test_grens_ligt_op_het_ingestelde_aantal_dagen() {
		$net_binnen = $this->maak_download( 'Net binnen', HDP_Downloads_CPT::NIEUW_DAGEN - 1 );
		$net_buiten = $this->maak_download( 'Net buiten', HDP_Downloads_CPT::NIEUW_DAGEN + 1 );

		$this->assertTrue( HDP_Downloads_CPT::is_nieuw( $net_binnen ) );
		$this->assertFalse( HDP_Downloads_CPT::is_nieuw( $net_buiten ) );
	}

	public function test_downloadspagina_labelt_alleen_de_nieuwe_bestanden() {
		$this->maak_download( 'Verse prijslijst', 1 );
		$this->maak_download( 'Oude prijslijst', 90 );

		$html = HDP_Downloads_Render::render_downloads_pagina( $this->attributen );

		$this->assertSame( 1, substr_count( $html, 'hdp-nieuw-badge' ) );

		// Het label hoort bij de verse titel, niet ergens los op de pagina.
		$compact = preg_replace( '/\s+/', ' ', $html );
		$this->assertStringContainsString( 'Verse prijslijst <span class="hdp-nieuw-badge">', $compact );
		$this->assertStringNotContainsString( 'Oude prijslijst <span class="hdp-nieuw-badge">', $compact );
	}

	/**
	 * De teller moet dezelfde zichtbaarheid aanhouden als de downloadspagina.
	 * Zou hij zijn eigen query doen, dan kan de startpagina "1 nieuw" melden
	 * terwijl de dealer er door zijn merkrechten nul van te zien krijgt.
	 */
	public function test_teller_telt_alleen_wat_deze_dealer_ook_echt_mag_zien() {
		update_user_meta( $this->dealer_id, 'hdp_merken', 'HARDI' );

		$this->maak_download( 'Nieuwe HARDI-prijslijst', 1, 'HARDI' );
		$this->maak_download( 'Nieuwe Vaderstad-prijslijst', 1, 'Väderstad' );

		$zichtbaar = HDP_Downloads_Render::zichtbare_downloads( 'download' )['downloads'];
		$nieuw     = array_filter(
			$zichtbaar,
			static function ( $download ) {
				return HDP_Downloads_CPT::is_nieuw( $download->ID );
			}
		);

		$this->assertCount( 1, $nieuw );
	}

	public function test_zichtbare_downloads_meldt_waarop_de_lijst_leeg_liep() {
		update_user_meta( $this->dealer_id, 'hdp_merken', 'HARDI' );

		$this->assertSame( 'geen', HDP_Downloads_Render::zichtbare_downloads( 'download' )['leeg'] );

		$this->maak_download( 'Vaderstad-prijslijst', 1, 'Väderstad' );
		$this->assertSame( 'merk', HDP_Downloads_Render::zichtbare_downloads( 'download' )['leeg'] );

		$this->maak_download( 'HARDI-prijslijst', 1, 'HARDI' );
		$this->assertSame( '', HDP_Downloads_Render::zichtbare_downloads( 'download' )['leeg'] );
	}
}
