<?php

/**
 * Test het portaalkaart-blok, met nadruk op de stand "over de volle
 * breedte": die is bewust een eigenschap van de kaart zelf en geen CSS-regel
 * die "de vijfde kaart" aanwijst, zodat Homburg in de editor kan bepalen
 * welke kaart breed staat zonder dat er code aan te pas komt.
 *
 * @group hdp-portaalkaart
 */
class Portaalkaart_Test extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'homburg/portaal-kaart' ) ) {
			register_block_type( HDP_PLUGIN_DIR . 'blocks/portaal-kaart' );
		}
	}

	private function render( $attrs = array() ) {
		return render_block(
			array(
				'blockName'    => 'homburg/portaal-kaart',
				'attrs'        => $attrs,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}

	private function basis( $extra = array() ) {
		return array_merge(
			array(
				'icoon'     => 'garantie',
				'titel'     => 'Garantie',
				'tekst'     => 'Dien een garantieclaim in.',
				'knoptekst' => 'Naar garantie',
				'url'       => '/garantie/',
			),
			$extra
		);
	}

	public function test_gewone_kaart_blijft_ongewijzigd() {
		$html = $this->render( $this->basis() );

		$this->assertStringContainsString( 'hdp-kaart', $html );
		$this->assertStringNotContainsString( 'hdp-kaart-breed', $html );
		// De gewone kaart zet titel en tekst rechtstreeks in de kaart; dat
		// is wat de bestaande opmaak (knop onderaan) laat werken.
		$this->assertStringNotContainsString( 'hdp-kaart-tekst', $html );
	}

	public function test_brede_kaart_krijgt_eigen_klasse_en_tekstblok() {
		$html = $this->render( $this->basis( array( 'breed' => true ) ) );

		$this->assertStringContainsString( 'hdp-kaart-breed', $html );
		// Titel en tekst in één blok, zodat dat de ruimte tussen icoon en
		// knop kan opvullen.
		$this->assertStringContainsString( 'hdp-kaart-tekst', $html );
	}

	public function test_brede_kaart_houdt_titel_tekst_en_knop() {
		$html = $this->render( $this->basis( array( 'breed' => true ) ) );

		$this->assertStringContainsString( 'Garantie', $html );
		$this->assertStringContainsString( 'Dien een garantieclaim in.', $html );
		$this->assertStringContainsString( 'Naar garantie', $html );
		$this->assertStringContainsString( 'href="/garantie/"', $html );
	}

	/**
	 * Het patroon dat de startpagina opbouwt moet de garantiekaart breed
	 * zetten — anders valt die bij een nieuwe pagina weer alleen op een
	 * lege rij.
	 */
	public function test_patroon_zet_de_garantiekaart_op_volle_breedte() {
		$patroon = file_get_contents( HDP_PLUGIN_DIR . 'patterns/portaalkaarten-sectie.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- alleen in de testsuite.

		$this->assertStringContainsString( '"url":"/garantie/","breed":true', $patroon );
	}

	/** Het schild hoort in de iconenkiezer van de editor te staan, niet alleen in PHP. */
	public function test_garantie_icoon_bestaat_in_php_en_in_de_editor() {
		$this->assertArrayHasKey( 'garantie', HDP_Icons::icoon_paden() );

		$editor = file_get_contents( HDP_PLUGIN_DIR . 'blocks/portaal-kaart/index.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- alleen in de testsuite.
		$this->assertStringContainsString( "value: 'garantie'", $editor );
	}
}
