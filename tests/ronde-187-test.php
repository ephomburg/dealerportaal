<?php

/**
 * Test de ronde van 1.87.0: wat de beveiliging met rust laat, extra formaten,
 * mails aan het team, en de contactinstellingen.
 *
 * @group hdp-ronde-187
 */
class Ronde_187_Test extends WP_UnitTestCase {

	public static function wpSetUpBeforeClass() {
		$map = dirname( __DIR__ ) . '/app/public/wp-content/themes/homburg-dealerportaal/includes/woocommerce/';
		if ( class_exists( 'WooCommerce' ) && ! function_exists( 'homburg_wc_teammail_begin' ) ) {
			require_once $map . 'bestellen.php';
		}
	}

	public function tearDown(): void {
		unset( $_COOKIE[ HDP_I18N::COOKIE ] );
		HDP_I18N::alleen_nederlands( false );
		parent::tearDown();
	}

	/** Een bijlage met een echt bestand, want daar kijkt de beveiliging naar. */
	private function bijlage( $naam, $mime ) {
		$u = wp_upload_bits( $naam, null, 'x' );
		return self::factory()->attachment->create_object(
			array(
				'file'           => $u['file'],
				'post_mime_type' => $mime,
				'post_title'     => $naam,
			)
		);
	}

	public function test_siteicoon_wordt_niet_verplaatst() {
		$id = $this->bijlage( 'icoon-test.png', 'image/png' );
		update_option( 'site_icon', $id );

		$this->assertTrue( HDP_Bestandsbeveiliging::wordt_elders_gebruikt( $id ) );
		$pad = get_attached_file( $id );
		$this->assertFalse( HDP_Bestandsbeveiliging::beveilig( $id ) );
		$this->assertSame( $pad, get_attached_file( $id ) );
		$this->assertFileExists( $pad );

		delete_option( 'site_icon' );
	}

	public function test_afbeelding_in_een_pagina_blijft_staan() {
		$id  = $this->bijlage( 'plaatje-pagina.png', 'image/png' );
		$url = wp_get_attachment_url( $id );
		self::factory()->post->create( array( 'post_type' => 'page', 'post_content' => '<img src="' . $url . '">' ) );

		$this->assertTrue( HDP_Bestandsbeveiliging::wordt_elders_gebruikt( $id ) );
	}

	public function test_verkleinde_versie_in_een_pagina_telt_ook() {
		$id  = $this->bijlage( 'plaatje-maat.png', 'image/png' );
		$url = preg_replace( '/\.png$/', '-768x102.png', wp_get_attachment_url( $id ) );
		self::factory()->post->create( array( 'post_type' => 'page', 'post_content' => '<img src="' . $url . '">' ) );

		$this->assertTrue( HDP_Bestandsbeveiliging::wordt_elders_gebruikt( $id ) );
	}

	public function test_naam_die_er_alleen_op_lijkt_telt_niet() {
		$id = $this->bijlage( 'logo.png', 'image/png' );
		self::factory()->post->create( array( 'post_type' => 'page', 'post_content' => '<img src="/wp-content/uploads/logo-prijzen.png">' ) );

		$this->assertFalse( HDP_Bestandsbeveiliging::wordt_elders_gebruikt( $id ) );
	}

	public function test_pdf_wordt_altijd_beveiligd_ook_met_een_link() {
		$id  = $this->bijlage( 'prijslijst-test.pdf', 'application/pdf' );
		$url = wp_get_attachment_url( $id );
		self::factory()->post->create( array( 'post_type' => 'page', 'post_content' => '<a href="' . $url . '">prijzen</a>' ) );

		$this->assertFalse( HDP_Bestandsbeveiliging::wordt_elders_gebruikt( $id ) );
		$this->assertTrue( HDP_Bestandsbeveiliging::beveilig( $id ) );
	}

	public function test_ongebruikte_afbeelding_wordt_gewoon_beveiligd() {
		$id = $this->bijlage( 'los-logo.png', 'image/png' );
		$this->assertTrue( HDP_Bestandsbeveiliging::beveilig( $id ) );
	}

	public function test_extra_formaat_wordt_herkend() {
		$hoofd = $this->bijlage( 'hoofd.pdf', 'application/pdf' );
		$extra = $this->bijlage( 'extra.xlsx', 'application/vnd.ms-excel' );
		$los   = $this->bijlage( 'los.pdf', 'application/pdf' );

		$download = self::factory()->post->create( array( 'post_type' => HDP_Downloads_CPT::POST_TYPE ) );
		update_post_meta( $download, '_hdp_attachment_id', $hoofd );
		HDP_Downloads_CPT::zet_extra_bestanden( $download, array( $extra ) );

		$this->assertTrue( HDP_FileBird_Sync::is_extra_formaat( $extra ) );
		$this->assertFalse( HDP_FileBird_Sync::is_extra_formaat( $los ) );
	}

	public function test_alleen_nederlands_gaat_voor_de_keuze_van_de_bezoeker() {
		$_COOKIE[ HDP_I18N::COOKIE ] = 'fr';
		$this->assertSame( 'fr', HDP_I18N::huidige_taal() );

		HDP_I18N::alleen_nederlands( true );
		$this->assertSame( 'nl', HDP_I18N::huidige_taal() );
		$this->assertSame( 'nl_NL', HDP_I18N::frontend_locale( 'nl_NL' ) );

		HDP_I18N::alleen_nederlands( false );
		$this->assertSame( 'fr', HDP_I18N::huidige_taal() );
	}

	public function test_teammail_toont_nederlandse_namen() {
		if ( ! function_exists( 'homburg_wc_teammail_totalen' ) ) {
			$this->markTestSkipped( 'WooCommerce is niet geladen.' );
		}
		$regels = array( 'x' => array( 'label' => 'x', 'value' => HDP_I18N::t_in( 'wc_op_rekening', 'fr' ) ) );

		$this->assertSame( $regels, homburg_wc_teammail_totalen( $regels ), 'Buiten een teammail blijft het staan.' );

		HDP_I18N::alleen_nederlands( true );
		$uit = homburg_wc_teammail_totalen( $regels );
		$this->assertSame( HDP_I18N::t_in( 'wc_op_rekening', 'nl' ), $uit['x']['value'] );
	}

	public function test_garantiecontact_wordt_bewaard() {
		$uit = HDP_Settings::sanitize( array( 'garantie_email' => 'service@homburg-holland.com', 'garantie_telefoon' => '+31 (0)58 257 15 55' ) );
		$this->assertSame( 'service@homburg-holland.com', $uit['garantie_email'] );
		$this->assertSame( '+31 (0)58 257 15 55', $uit['garantie_telefoon'] );
	}
}
