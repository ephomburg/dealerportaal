<?php

/**
 * Downloads: nette titels, meerdere formaten onder één titel, nieuwe
 * versies, formaat/grootte/datum in de lijst, bekijken in de browser,
 * zoeken in omschrijving en merk, en de contentgalerij.
 *
 * @group hdp-downloads
 */
class Downloads_Versies_Test extends WP_UnitTestCase {

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
			'titel'          => 'Downloads',
			'titelFr'        => 'Téléchargements',
			'omschrijving'   => 'Intro',
			'omschrijvingFr' => 'Intro',
		);
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/** Een echt bestand in de mediabibliotheek. */
	private function bijlage( $extensie = 'txt', $inhoud = 'testinhoud' ) {
		$pad = get_temp_dir() . 'hdp-versie-' . wp_generate_password( 8, false ) . '.' . $extensie;
		file_put_contents( $pad, $inhoud ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- alleen in de testsuite.
		return self::factory()->attachment->create_upload_object( $pad );
	}

	/** Een echte JPG, zodat er een voorbeeldplaatje van te maken is. */
	private function foto() {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD ontbreekt in deze testomgeving.' );
		}
		$pad  = get_temp_dir() . 'hdp-foto-' . wp_generate_password( 8, false ) . '.jpg';
		$beeld = imagecreatetruecolor( 1200, 800 );
		imagejpeg( $beeld, $pad );
		imagedestroy( $beeld );
		return self::factory()->attachment->create_upload_object( $pad );
	}

	private function download( $titel, $attachment_id, $categorie = 'download', $extra = array() ) {
		$id = self::factory()->post->create(
			array(
				'post_type'  => HDP_Downloads_CPT::POST_TYPE,
				'post_title' => $titel,
			)
		);
		update_post_meta( $id, '_hdp_attachment_id', $attachment_id );
		update_post_meta( $id, '_hdp_categorie', $categorie );
		HDP_Downloads_CPT::zet_extra_bestanden( $id, $extra );
		return $id;
	}

	/* --- Nette titels ------------------------------------------------- */

	public function test_nette_titel_uit_een_bestandsnaam() {
		$this->assertSame( 'Gebruikershandleiding Homburg Junior 1980-2011 NL', HDP_Downloads_CPT::nette_titel( 'Gebruikershandleiding_HOMBURG_JUNIOR_-_1980-2011_-_NL.pdf' ) );
		$this->assertSame( 'Prijslijst Rabe machines 2024 Rev 2', HDP_Downloads_CPT::nette_titel( 'Prijslijst-Rabe-machines-2024-Rev-2.pdf' ) );
		// Merknamen houden hun eigen schrijfwijze, ook als ze in hoofdletters stonden.
		$this->assertSame( 'Prijslijst HARDI 2026', HDP_Downloads_CPT::nette_titel( 'prijslijst_hardi_2026.pdf' ) );
		// Het "-1" dat WordPress achter een dubbele naam plakt, verdwijnt.
		$this->assertSame( 'Onderdelen Parts Homburg Hurricane 2014', HDP_Downloads_CPT::nette_titel( 'Onderdelen_-_Parts_HOMBURG_HURRICANE_2014-1.pdf' ) );
	}

	/* --- Meerdere formaten ---------------------------------------------- */

	public function test_een_extra_formaat_is_op_te_halen_en_afgeschermd() {
		$pdf  = $this->bijlage( 'txt', 'pdf' );
		$xlsx = $this->bijlage( 'csv', 'a;b' );
		$id   = $this->download( 'Onderdelenlijst', $pdf, 'download', array( $xlsx ) );

		$bestand = HDP_Downloads_CPT::resolve_download( $id, $xlsx );

		$this->assertSame( $xlsx, $bestand['attachment_id'] );
		$this->assertTrue( HDP_Bestandsbeveiliging::is_beveiligd( $xlsx ) );
		$this->assertStringContainsString( 'bestand=' . $xlsx, HDP_Downloads_CPT::download_url( $id, $xlsx ) );
		$this->assertStringNotContainsString( 'bestand=', HDP_Downloads_CPT::download_url( $id, $pdf ) );
	}

	/** Een bestand dat niet bij déze download hoort, is niet via die download op te halen. */
	public function test_een_vreemd_bestand_via_een_toegestane_download_wordt_geweigerd() {
		$eigen  = $this->bijlage();
		$vreemd = $this->bijlage();
		$id     = $this->download( 'Eigen', $eigen );
		$this->download( 'Ander merk', $vreemd );

		$this->expectException( WPDieException::class );
		$this->expectExceptionCode( 404 );

		HDP_Downloads_CPT::resolve_download( $id, $vreemd );
	}

	/** Meerdere formaten staan onder één titel, met een knop per formaat. */
	public function test_meerdere_formaten_onder_een_titel() {
		$id = $this->download( 'Onderdelenlijst', $this->bijlage( 'txt' ), 'download', array( $this->bijlage( 'csv' ) ) );

		$html = HDP_Downloads_Render::render_downloads_pagina( $this->attributen );

		$this->assertSame( 1, substr_count( $html, 'Onderdelenlijst' ) );
		$this->assertMatchesRegularExpression( '/>\s*TXT\s*<\/a>/', $html );
		$this->assertMatchesRegularExpression( '/>\s*CSV\s*<\/a>/', $html );
		$this->assertStringContainsString( 'hdp_download=' . $id, $html );
	}

	/* --- Nieuwe versie --------------------------------------------------- */

	public function test_nieuwe_versie_vervangt_het_bestand_en_is_weer_nieuw() {
		$oud = $this->bijlage();
		$id  = $this->download( 'Prijslijst', $oud );
		wp_update_post(
			array(
				'ID'            => $id,
				'post_date'     => '2024-01-01 10:00:00',
				'post_date_gmt' => '2024-01-01 09:00:00',
			)
		);
		$this->assertFalse( HDP_Downloads_CPT::is_nieuw( $id ) );

		$nieuw = $this->bijlage();
		HDP_Downloads_CPT::vervang_bestand( $id, $nieuw );

		$this->assertSame( $nieuw, HDP_Downloads_CPT::resolve_download( $id )['attachment_id'] );
		$this->assertTrue( HDP_Downloads_CPT::is_nieuw( $id ), 'Een nieuwe versie hoort als nieuw te tellen.' );
		$this->assertTrue( HDP_Bestandsbeveiliging::is_beveiligd( $nieuw ) );
	}

	/* --- De lijst -------------------------------------------------------- */

	public function test_formaat_grootte_en_datum_staan_bij_een_bestand() {
		$id = $this->download( 'Handleiding', $this->bijlage( 'txt', 'tien bytes' ) );
		wp_update_post(
			array(
				'ID'        => $id,
				'post_date' => '2026-03-12 10:00:00',
			)
		);

		$html = HDP_Downloads_Render::render_downloads_pagina( $this->attributen );

		$this->assertMatchesRegularExpression( '/hdp-download-meta">TXT 10 B · 12 \p{L}+\.? 2026</u', $html );
	}

	/** Bekijken alleen voor wat een browser zelf kan tonen. */
	public function test_bekijken_alleen_bij_een_bekijkbaar_bestand() {
		$this->download( 'Tekstbestand', $this->bijlage( 'txt' ) );
		$html = HDP_Downloads_Render::render_downloads_pagina( $this->attributen );
		$this->assertStringNotContainsString( 'modus=bekijken', $html );

		$this->download( 'Foto', $this->foto() );
		$html = HDP_Downloads_Render::render_downloads_pagina( $this->attributen );
		$this->assertStringContainsString( 'modus=bekijken', $html );

		$this->assertTrue( HDP_Downloads_CPT::is_bekijkbaar( 'application/pdf' ) );
		$this->assertFalse( HDP_Downloads_CPT::is_bekijkbaar( 'image/svg+xml' ), 'Een SVG kan scripts bevatten en hoort niet in de browser geopend te worden.' );
	}

	/** Zoeken gebeurt in titel, omschrijving en merk — niet alleen de titel. */
	public function test_zoektekst_bevat_omschrijving_en_merk() {
		$id = $this->download( 'Prijslijst 2026', $this->bijlage() );
		wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => 'Inclusief zoutstrooiers',
			)
		);
		update_post_meta( $id, '_hdp_merk', 'Bogballe' );

		$html = HDP_Downloads_Render::render_downloads_pagina( $this->attributen );

		$this->assertStringContainsString( 'data-zoek="prijslijst 2026 inclusief zoutstrooiers bogballe"', $html );
	}

	/** Een voorbeeldplaatje opvragen telt niet als download. */
	public function test_voorbeeld_telt_niet_als_download() {
		$id = $this->download( 'Beeld', $this->foto(), 'content' );

		HDP_Downloads_CPT::resolve_download( $id, 0, false );
		$this->assertSame( 0, HDP_Downloads_CPT::get_download_teller( $id ) );

		HDP_Downloads_CPT::resolve_download( $id );
		$this->assertSame( 1, HDP_Downloads_CPT::get_download_teller( $id ) );
	}

	/* --- Contentgalerij -------------------------------------------------- */

	public function test_content_is_een_galerij_met_voorbeeld_en_kopieerknop() {
		$id = $this->download( 'Lenteactie', $this->foto(), 'content' );
		wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => 'Nu bij je dealer: de lenteactie!',
			)
		);

		$html = HDP_Downloads_Render::render_content_pagina( $this->attributen );

		$this->assertStringContainsString( 'hdp-content-galerij', $html );
		$this->assertStringContainsString( 'modus=voorbeeld', $html );
		$this->assertStringContainsString( 'data-tekst="Nu bij je dealer: de lenteactie!"', $html );
		$this->assertStringContainsString( HDP_Downloads_CPT::ZIP_VAR . '=content', $html );
	}

	public function test_voorbeeldplaatje_is_klein_en_staat_afgeschermd() {
		$foto = $this->foto();
		$this->download( 'Beeld', $foto, 'content' );

		$pad = HDP_Downloads_CPT::voorbeeld_pad( $foto );

		$this->assertFileExists( $pad );
		$this->assertStringContainsString( HDP_Bestandsbeveiliging::MAP . '/voorbeelden', wp_normalize_path( $pad ) );
		$this->assertSame( HDP_Downloads_CPT::VOORBEELD_BREEDTE, getimagesize( $pad )[0] );
	}

	/** De zip bevat alleen wat deze dealer ook los mag zien. */
	public function test_zip_bevat_alleen_wat_de_dealer_mag_zien() {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->markTestSkipped( 'ZipArchive ontbreekt in deze testomgeving.' );
		}
		update_user_meta( $this->dealer_id, 'hdp_merken', 'Bogballe' );

		$mag  = $this->download( 'Bogballe-beeld', $this->bijlage( 'txt', 'mag' ), 'content' );
		$niet = $this->download( 'HARDI-beeld', $this->bijlage( 'txt', 'niet' ), 'content' );
		update_post_meta( $mag, '_hdp_merk', 'Bogballe' );
		update_post_meta( $niet, '_hdp_merk', 'HARDI' );

		$pad = HDP_Downloads_CPT::maak_zip( 'content' );
		$zip = new ZipArchive();
		$zip->open( $pad );
		$inhoud = array();
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$inhoud[] = $zip->getFromIndex( $i );
		}
		$zip->close();
		wp_delete_file( $pad );

		$this->assertSame( array( 'mag' ), $inhoud );
	}
}
