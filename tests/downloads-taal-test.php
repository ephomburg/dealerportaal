<?php

/**
 * Test de taalgebonden zichtbaarheid van downloads/content op basis van de
 * regio-tag: 'nl'/'be' verschijnen bij de NL-taalversie, 'be-fr' bij de
 * FR-taalversie, en bestanden zonder regio-tag blijven in beide talen
 * zichtbaar (achterwaartse compatibiliteit met bestaande uploads).
 *
 * @group hdp-downloads-taal
 */
class Downloads_Taal_Test extends WP_UnitTestCase {

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
		unset( $_COOKIE[ HDP_I18N::COOKIE ] );
		parent::tearDown();
	}

	private function maak_download( $titel, $regios = null ) {
		$id = self::factory()->post->create(
			array(
				'post_type'  => HDP_Downloads_CPT::POST_TYPE,
				'post_title' => $titel,
			)
		);
		if ( null !== $regios ) {
			update_post_meta( $id, '_hdp_regios', implode( ',', $regios ) );
		}
		return $id;
	}

	private function zet_taal( $taal ) {
		$_COOKIE[ HDP_I18N::COOKIE ] = $taal;
	}

	public function test_nl_taal_toont_nl_en_be_maar_niet_be_fr() {
		$this->maak_download( 'Prijslijst NL', array( 'nl' ) );
		$this->maak_download( 'Prijslijst BE', array( 'be' ) );
		$this->maak_download( 'Liste des prix FR', array( 'be-fr' ) );

		$this->zet_taal( 'nl' );
		$html = HDP_Downloads_Render::render_downloads_pagina( $this->attributen );

		$this->assertStringContainsString( 'Prijslijst NL', $html );
		$this->assertStringContainsString( 'Prijslijst BE', $html );
		$this->assertStringNotContainsString( 'Liste des prix FR', $html );
	}

	public function test_fr_taal_toont_alleen_be_fr() {
		$this->maak_download( 'Prijslijst NL', array( 'nl' ) );
		$this->maak_download( 'Prijslijst BE', array( 'be' ) );
		$this->maak_download( 'Liste des prix FR', array( 'be-fr' ) );

		$this->zet_taal( 'fr' );
		$html = HDP_Downloads_Render::render_downloads_pagina( $this->attributen );

		$this->assertStringNotContainsString( 'Prijslijst NL', $html );
		$this->assertStringNotContainsString( 'Prijslijst BE', $html );
		$this->assertStringContainsString( 'Liste des prix FR', $html );
	}

	public function test_bestand_zonder_regiotag_blijft_in_beide_talen_zichtbaar() {
		$this->maak_download( 'Oude download zonder regio' );

		$this->zet_taal( 'nl' );
		$this->assertStringContainsString( 'Oude download zonder regio', HDP_Downloads_Render::render_downloads_pagina( $this->attributen ) );

		$this->zet_taal( 'fr' );
		$this->assertStringContainsString( 'Oude download zonder regio', HDP_Downloads_Render::render_downloads_pagina( $this->attributen ) );
	}

	public function test_fr_taal_zonder_franstalige_bestanden_toont_taalspecifieke_lege_staat() {
		$this->maak_download( 'Alleen voor Nederland', array( 'nl' ) );

		$this->zet_taal( 'fr' );
		$html = HDP_Downloads_Render::render_downloads_pagina( $this->attributen );

		$this->assertStringContainsString( 'Aucun téléchargement disponible dans cette langue.', $html );
	}

	public function test_regiofilterchips_tonen_alleen_taalrelevante_opties() {
		$this->maak_download( 'Prijslijst NL', array( 'nl' ) );

		$this->zet_taal( 'nl' );
		$html_nl = HDP_Downloads_Render::render_downloads_pagina( $this->attributen );
		$this->assertStringContainsString( 'data-regio="nl"', $html_nl );
		$this->assertStringNotContainsString( 'data-regio="be-fr"', $html_nl );

		$this->maak_download( 'Liste FR', array( 'be-fr' ) );
		$this->zet_taal( 'fr' );
		$html_fr = HDP_Downloads_Render::render_downloads_pagina( $this->attributen );
		$this->assertStringContainsString( 'data-regio="be-fr"', $html_fr );
		$this->assertStringNotContainsString( 'data-regio="nl"', $html_fr );
	}
}
