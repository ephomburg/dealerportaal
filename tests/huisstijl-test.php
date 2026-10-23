<?php

/**
 * Test de huisstijlbibliotheek: de derde categorie naast downloads en
 * content.
 *
 * Het gevoelige punt is dat de drie categorieën elkaars bestanden niet
 * oppikken. Bestanden van vóór dit onderscheid hebben geen categorie-meta
 * en horen bij "download"; die erfenis mag niet door een nieuwe categorie
 * worden opgeslokt. Dat ging bij het bouwen van deze pagina meteen mis en
 * leverde een lege huisstijlpagina op.
 *
 * @group hdp-huisstijl
 */
class Huisstijl_Test extends WP_UnitTestCase {

	private $dealer_id;

	public function setUp(): void {
		parent::setUp();
		HDP_Roles::activate();
		HDP_Downloads_CPT::register_post_type();

		$this->dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $this->dealer_id, 'hdp_goedgekeurd', '1' );
		wp_set_current_user( $this->dealer_id );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * @param string|null $categorie Null = helemaal geen meta, zoals bij
	 *                               bestanden van vóór dit onderscheid.
	 */
	private function maak_bestand( $titel, $categorie, $merk = 'Algemeen', $regio = '' ) {
		$id = self::factory()->post->create(
			array(
				'post_type'   => HDP_Downloads_CPT::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $titel,
			)
		);
		if ( null !== $categorie ) {
			update_post_meta( $id, '_hdp_categorie', $categorie );
		}
		update_post_meta( $id, '_hdp_merk', $merk );
		update_post_meta( $id, '_hdp_regios', $regio );

		return $id;
	}

	private function titels( $categorie ) {
		$uitkomst = HDP_Downloads_Render::zichtbare_downloads( $categorie );

		return array_map(
			static function ( $post ) {
				return $post->post_title;
			},
			$uitkomst['downloads']
		);
	}

	/* --- De drie categorieën blijven uit elkaar ------------------------ */

	public function test_huisstijl_toont_alleen_huisstijlbestanden() {
		$this->maak_bestand( 'Homburg logo', 'huisstijl' );
		$this->maak_bestand( 'Prijslijst', 'download' );
		$this->maak_bestand( 'Instagram-post', 'content' );

		$this->assertSame( array( 'Homburg logo' ), $this->titels( 'huisstijl' ) );
	}

	public function test_downloads_pikt_geen_huisstijl_op() {
		$this->maak_bestand( 'Homburg logo', 'huisstijl' );
		$this->maak_bestand( 'Prijslijst', 'download' );

		$this->assertSame( array( 'Prijslijst' ), $this->titels( 'download' ) );
	}

	public function test_content_pikt_geen_huisstijl_op() {
		$this->maak_bestand( 'Homburg logo', 'huisstijl' );
		$this->maak_bestand( 'Instagram-post', 'content' );

		$this->assertSame( array( 'Instagram-post' ), $this->titels( 'content' ) );
	}

	/**
	 * Bestanden van vóór dit onderscheid hebben geen categorie-meta. Die
	 * horen bij downloads te blijven en nergens anders op te duiken.
	 */
	public function test_oude_bestanden_zonder_categorie_blijven_downloads() {
		$this->maak_bestand( 'Oude handleiding', null );

		$this->assertSame( array( 'Oude handleiding' ), $this->titels( 'download' ) );
		$this->assertSame( array(), $this->titels( 'huisstijl' ) );
		$this->assertSame( array(), $this->titels( 'content' ) );
	}

	/* --- Logo's zijn voor iedereen ------------------------------------- */

	/**
	 * Een logo heeft geen taal. De FileBird-mappen onder huisstijl hebben
	 * daarom geen taalcode, wat een lege regio oplevert — en dan hoort het
	 * bestand bij zowel een Nederlandse als een Franstalige dealer te staan.
	 */
	public function test_logo_zonder_regio_is_in_beide_talen_zichtbaar() {
		$this->maak_bestand( 'Homburg logo', 'huisstijl', 'Algemeen', '' );

		$this->assertSame( array( 'Homburg logo' ), $this->titels( 'huisstijl' ) );

		$_COOKIE[ HDP_I18N::COOKIE ] = 'fr';
		$frans                       = $this->titels( 'huisstijl' );
		unset( $_COOKIE[ HDP_I18N::COOKIE ] );

		$this->assertSame( array( 'Homburg logo' ), $frans );
	}

	/* --- Merkrechten gelden ook hier ----------------------------------- */

	public function test_merk_van_een_andere_dealer_blijft_verborgen() {
		update_user_meta( $this->dealer_id, 'hdp_merken', 'Väderstad' );
		$this->maak_bestand( 'Väderstad logo', 'huisstijl', 'Väderstad' );
		$this->maak_bestand( 'Stanhay logo', 'huisstijl', 'Stanhay' );

		$this->assertSame( array( 'Väderstad logo' ), $this->titels( 'huisstijl' ) );
	}

	/* --- De categorie zelf --------------------------------------------- */

	public function test_huisstijl_is_een_geldige_categorie() {
		$id = $this->maak_bestand( 'Homburg logo', 'huisstijl' );

		$this->assertSame( 'huisstijl', HDP_Downloads_CPT::get_categorie( $id ) );
		$this->assertContains( 'huisstijl', HDP_Downloads_CPT::CATEGORIEEN );
	}

	/** Een onzinwaarde mag nooit blijven staan; die valt terug op download. */
	public function test_onbekende_categorie_valt_terug_op_download() {
		$id = $this->maak_bestand( 'Raar bestand', 'iets-anders' );

		$this->assertSame( 'download', HDP_Downloads_CPT::get_categorie( $id ) );
	}

	/* --- De FileBird-mappen -------------------------------------------- */

	/**
	 * Elke categorie hoort een hoofdmap te hebben om mee gevuld te worden.
	 * Zonder dat is een categorie alleen met de hand te vullen, en dat is
	 * precies wat de koppeling moest wegnemen.
	 */
	public function test_elke_categorie_heeft_een_filebird_hoofdmap() {
		$met_map = array_values( HDP_FileBird_Sync::ROOTS );

		foreach ( HDP_Downloads_CPT::CATEGORIEEN as $categorie ) {
			$this->assertContains( $categorie, $met_map, "Categorie \"$categorie\" heeft geen FileBird-hoofdmap." );
		}
	}

	/** En omgekeerd: een hoofdmap mag niet naar een onbekende categorie wijzen. */
	public function test_elke_hoofdmap_wijst_naar_een_bestaande_categorie() {
		foreach ( HDP_FileBird_Sync::ROOTS as $map => $categorie ) {
			$this->assertContains( $categorie, HDP_Downloads_CPT::CATEGORIEEN, "Map \"$map\" wijst naar een onbekende categorie." );
		}
	}

	/**
	 * De mappen worden één keer per versie aangemaakt. Komt er een hoofdmap
	 * bij zonder dat dit nummer omhooggaat, dan verschijnt die map nooit op
	 * een site waar het aanmaken al gedraaid heeft — en doet de koppeling
	 * daar stilletjes niets.
	 */
	public function test_mappenversie_hoort_bij_het_aantal_hoofdmappen() {
		$this->assertGreaterThanOrEqual(
			2,
			HDP_FileBird_Sync::MAPPEN_VERSIE,
			'Bij het toevoegen van een hoofdmap hoort MAPPEN_VERSIE omhoog.'
		);
	}

	/* --- De pagina ------------------------------------------------------ */

	public function test_de_pagina_toont_de_huisstijlbestanden() {
		$this->maak_bestand( 'Homburg logo', 'huisstijl' );
		$this->maak_bestand( 'Prijslijst', 'download' );

		$html = HDP_Downloads_Render::render_huisstijl_pagina(
			array(
				'heroAfbeelding' => '',
				'titel'          => 'Huisstijl & media',
				'titelFr'        => 'Identité visuelle',
				'omschrijving'   => 'Logo\'s en kleuren.',
				'omschrijvingFr' => 'Logos et couleurs.',
			)
		);

		$this->assertStringContainsString( 'Homburg logo', $html );
		$this->assertStringNotContainsString( 'Prijslijst', $html );
		$this->assertStringContainsString( 'Huisstijl &amp; media', $html );
	}
}
