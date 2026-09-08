<?php

/**
 * Test de merkgebonden zichtbaarheid van downloads/content: een dealer met
 * een ingestelde merkenlijst (hdp_merken) ziet alleen bestanden van die
 * merken (of zonder merk-tag); een dealer zonder ingestelde merken blijft
 * alles zien. Zelfde opzet als Downloads_Taal_Test.
 *
 * @group hdp-downloads-merk
 */
class Downloads_Merk_Test extends WP_UnitTestCase {

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

	private function maak_download( $titel, $merk = '' ) {
		$id = self::factory()->post->create(
			array(
				'post_type'  => HDP_Downloads_CPT::POST_TYPE,
				'post_title' => $titel,
			)
		);
		if ( $merk ) {
			update_post_meta( $id, '_hdp_merk', $merk );
		}
		return $id;
	}

	public function test_dealer_zonder_merken_ziet_alles() {
		$this->maak_download( 'HARDI-prijslijst', 'HARDI' );
		$this->maak_download( 'Vaderstad-prijslijst', 'Vaderstad' );

		$html = HDP_Downloads_Render::render_downloads_pagina( $this->attributen );

		$this->assertStringContainsString( 'HARDI-prijslijst', $html );
		$this->assertStringContainsString( 'Vaderstad-prijslijst', $html );
	}

	public function test_dealer_met_merken_ziet_alleen_geautoriseerde_merken() {
		update_user_meta( $this->dealer_id, 'hdp_merken', 'HARDI' );

		$this->maak_download( 'HARDI-prijslijst', 'HARDI' );
		$this->maak_download( 'Vaderstad-prijslijst', 'Vaderstad' );

		$html = HDP_Downloads_Render::render_downloads_pagina( $this->attributen );

		$this->assertStringContainsString( 'HARDI-prijslijst', $html );
		$this->assertStringNotContainsString( 'Vaderstad-prijslijst', $html );
	}

	public function test_bestand_zonder_merktag_blijft_zichtbaar_ondanks_merkbeperking() {
		update_user_meta( $this->dealer_id, 'hdp_merken', 'HARDI' );

		$this->maak_download( 'Algemene brochure zonder merk' );

		$html = HDP_Downloads_Render::render_downloads_pagina( $this->attributen );

		$this->assertStringContainsString( 'Algemene brochure zonder merk', $html );
	}

	public function test_geen_geautoriseerde_downloads_toont_merkspecifieke_lege_staat() {
		update_user_meta( $this->dealer_id, 'hdp_merken', 'HARDI' );

		$this->maak_download( 'Vaderstad-prijslijst', 'Vaderstad' );

		$html = HDP_Downloads_Render::render_downloads_pagina( $this->attributen );

		$this->assertStringContainsString( 'Geen downloads beschikbaar voor uw geautoriseerde merken.', $html );
	}

	public function test_taal_en_merkfilter_werken_samen() {
		update_user_meta( $this->dealer_id, 'hdp_merken', 'HARDI' );
		$_COOKIE[ HDP_I18N::COOKIE ] = 'fr';

		$hardi_be    = $this->maak_download( 'HARDI BE-FR', 'HARDI' );
		update_post_meta( $hardi_be, '_hdp_regios', 'be-fr' );
		$hardi_nl = $this->maak_download( 'HARDI NL', 'HARDI' );
		update_post_meta( $hardi_nl, '_hdp_regios', 'nl' );

		$html = HDP_Downloads_Render::render_downloads_pagina( $this->attributen );

		$this->assertStringContainsString( 'HARDI BE-FR', $html );
		$this->assertStringNotContainsString( 'HARDI NL', $html );

		unset( $_COOKIE[ HDP_I18N::COOKIE ] );
	}
}
