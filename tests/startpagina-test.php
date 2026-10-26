<?php

/**
 * De nieuwe startpagina: magazijnstatus, "Vandaag voor jou", en het blok
 * "Nieuw voor jou" met een lege staat.
 *
 * @group hdp-startpagina
 */
class Startpagina_Test extends WP_UnitTestCase {

	private $dealer_id;

	public function setUp(): void {
		parent::setUp();
		HDP_Roles::activate();

		$this->dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $this->dealer_id, 'hdp_goedgekeurd', '1' );
		wp_set_current_user( $this->dealer_id );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/** Een lokale tijd, zoals de startpagina hem doorgeeft (strtotime op de lokale tijd als tekst). */
	private function om( $tijd ) {
		return strtotime( $tijd );
	}

	/* --- Magazijn ---------------------------------------------------- */

	public function test_magazijn_open_op_een_werkdag_overdag() {
		$status = HDP_Portal_Render::magazijn_status( $this->om( '2026-10-26 10:00:00' ) ); // maandag

		$this->assertTrue( $status['open'] );
		$this->assertStringContainsString( '17:00', $status['tekst'] );
	}

	public function test_magazijn_om_vijf_uur_dicht_en_morgen_weer_open() {
		$status = HDP_Portal_Render::magazijn_status( $this->om( '2026-10-28 17:00:00' ) ); // woensdag

		$this->assertFalse( $status['open'] );
		$this->assertStringContainsString( sprintf( HDP_I18N::t( 'magazijn_morgen' ), '08:00' ), $status['tekst'] );
	}

	public function test_magazijn_vroeg_in_de_ochtend_vandaag_nog_open() {
		$status = HDP_Portal_Render::magazijn_status( $this->om( '2026-10-27 07:30:00' ) ); // dinsdag

		$this->assertFalse( $status['open'] );
		$this->assertStringContainsString( sprintf( HDP_I18N::t( 'magazijn_vandaag' ), '08:00' ), $status['tekst'] );
	}

	/** Vrijdagavond en het weekend: pas maandag weer, niet "morgen". */
	public function test_magazijn_in_het_weekend_pas_maandag() {
		foreach ( array( '2026-10-30 18:00:00', '2026-10-31 11:00:00', '2026-11-01 11:00:00' ) as $tijd ) {
			$status = HDP_Portal_Render::magazijn_status( $this->om( $tijd ) );
			$this->assertFalse( $status['open'], $tijd );
			$this->assertStringContainsString( sprintf( HDP_I18N::t( 'magazijn_maandag' ), '08:00' ), $status['tekst'], $tijd );
		}
	}

	/**
	 * De tijd die binnenkomt is al lokaal. Werd hij nog eens naar de
	 * tijdzone van de site omgerekend, dan sprong het magazijn op een site
	 * in Nederlandse tijd een of twee uur te vroeg op "gesloten".
	 */
	public function test_magazijn_rekent_niet_dubbel_met_de_tijdzone() {
		update_option( 'timezone_string', 'Europe/Amsterdam' );

		$status = HDP_Portal_Render::magazijn_status( $this->om( '2026-10-26 16:30:00' ) );

		update_option( 'timezone_string', '' );
		$this->assertTrue( $status['open'], 'Om half vijf lokale tijd is het magazijn open.' );
	}

	/* --- Vandaag voor jou ---------------------------------------------- */

	public function test_startpagina_toont_vandaag_voor_jou_en_geen_herbestellen() {
		$html = HDP_Portal_Render::render_dealerportaal( array() );

		$this->assertStringContainsString( 'hdp-vandaag', $html );
		$this->assertStringContainsString( HDP_I18N::t( 'vandaag_kop' ), $html );
		$this->assertStringContainsString( HDP_I18N::t( 'tegel_downloads' ), $html );
		$this->assertStringNotContainsString( 'hdp-herbestellen-sectie', $html );
		$this->assertStringContainsString( 'hdp-magazijn-status', $html );
	}

	/** Een nieuw document telt mee in de tegel. */
	public function test_nieuw_document_telt_in_de_tegel() {
		self::factory()->post->create(
			array(
				'post_type'  => HDP_Downloads_CPT::POST_TYPE,
				'post_title' => 'Verse prijslijst',
			)
		);

		$html = HDP_Portal_Render::render_dealerportaal( array() );

		$this->assertStringContainsString( HDP_I18N::t( 'tegel_downloads_nieuw' ), $html );
	}

	/* --- Nieuw voor jou ------------------------------------------------- */

	/** Zonder nieuwe documenten een rustige regel, geen lege plek in de kolom. */
	public function test_nieuw_voor_jou_heeft_een_lege_staat() {
		$html = HDP_Portal_Render::render_nieuw_voor_jou();

		$this->assertStringContainsString( HDP_I18N::t( 'nieuw_niets' ), $html );
		$this->assertStringContainsString( '/downloads/', $html );
	}

	public function test_nieuw_voor_jou_toont_de_documenten() {
		self::factory()->post->create(
			array(
				'post_type'  => HDP_Downloads_CPT::POST_TYPE,
				'post_title' => 'Verse prijslijst',
			)
		);

		$html = HDP_Portal_Render::render_nieuw_voor_jou();

		$this->assertStringContainsString( 'Verse prijslijst', $html );
		$this->assertStringNotContainsString( HDP_I18N::t( 'nieuw_niets' ), $html );
	}

	public function test_nieuw_voor_jou_is_leeg_voor_wie_geen_toegang_heeft() {
		wp_set_current_user( 0 );

		$this->assertSame( '', HDP_Portal_Render::render_nieuw_voor_jou() );
	}
}
