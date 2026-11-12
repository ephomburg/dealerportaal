<?php

/**
 * Het Engels als derde taal van het portaal (2026-11-12).
 *
 * Bewaakt dat elke portaaltekst en elke themetekst een Engelse versie
 * heeft, dat de invulplekken (%s, %d) kloppen, en dat de plekken die op
 * de taal letten ook het Engels kennen.
 *
 * @group hdp-engels
 */
class Engels_Test extends WP_UnitTestCase {

	public function tearDown(): void {
		unset( $_COOKIE[ HDP_I18N::COOKIE ] );
		parent::tearDown();
	}

	private function engels() {
		$_COOKIE[ HDP_I18N::COOKIE ] = 'en';
	}

	private function alle_sleutels() {
		$m = new ReflectionMethod( 'HDP_I18N', 'teksten' );
		$m->setAccessible( true );
		return $m->invoke( null );
	}

	public function test_engels_is_een_taal() {
		$this->assertContains( 'en', HDP_I18N::TALEN );
		$this->engels();
		$this->assertSame( 'en', HDP_I18N::huidige_taal() );
		$this->assertTrue( HDP_I18N::is_engels() );
		$this->assertTrue( HDP_I18N::is_vertaald() );
		$this->assertFalse( HDP_I18N::is_frans() );
	}

	public function test_onbekende_taal_blijft_nederlands() {
		$_COOKIE[ HDP_I18N::COOKIE ] = 'de';
		$this->assertSame( 'nl', HDP_I18N::huidige_taal() );
		$this->assertFalse( HDP_I18N::is_vertaald() );
	}

	public function test_elke_portaaltekst_heeft_engels() {
		$engels = HDP_I18N_EN::teksten();
		$mist   = array_diff( array_keys( $this->alle_sleutels() ), array_keys( $engels ) );
		$this->assertSame( array(), array_values( $mist ), "Zonder Engels:\n" . implode( "\n", $mist ) );
	}

	public function test_geen_engelse_tekst_zonder_sleutel() {
		$over = array_diff( array_keys( HDP_I18N_EN::teksten() ), array_keys( $this->alle_sleutels() ) );
		$this->assertSame( array(), array_values( $over ), 'Engelse tekst bij een sleutel die niet (meer) bestaat.' );
	}

	public function test_invulplekken_in_portaalteksten_kloppen() {
		$alle = $this->alle_sleutels();
		foreach ( HDP_I18N_EN::teksten() as $sleutel => $en ) {
			preg_match_all( '/%(\d\$)?[sd]/', $alle[ $sleutel ]['nl'], $nl );
			preg_match_all( '/%(\d\$)?[sd]/', $en, $eng );
			sort( $nl[0] );
			sort( $eng[0] );
			$this->assertSame( $nl[0], $eng[0], $sleutel );
		}
	}

	public function test_elke_themetekst_heeft_engels() {
		$mist = array_diff( array_keys( HDP_I18N::thema_teksten() ), array_keys( HDP_I18N_EN::thema() ) );
		$this->assertSame( array(), array_values( $mist ), "Zonder Engels:\n" . implode( "\n", $mist ) );
		foreach ( HDP_I18N_EN::thema() as $nl => $en ) {
			$this->assertSame( substr_count( $nl, '%' ), substr_count( $en, '%' ), $nl );
		}
	}

	public function test_teksten_volgen_de_taal() {
		$this->assertSame( 'Inloggen', HDP_I18N::t( 'btn_inloggen' ) );
		$this->engels();
		$this->assertSame( 'Log in', HDP_I18N::t( 'btn_inloggen' ) );
		$this->assertSame( 'Search', HDP_I18N::thema_tekst( 'Zoeken', 'Zoeken' ) );
		$this->assertSame( 'Log in', HDP_I18N::t_in( 'btn_inloggen', 'en' ) );
		$this->assertSame( 'Connexion', HDP_I18N::t_in( 'btn_inloggen', 'fr' ) );
	}

	public function test_eigen_inhoud_valt_terug_op_nederlands() {
		$this->engels();
		$this->assertSame( 'Nederlands', HDP_I18N::kies( 'Nederlands', 'Français' ) );
		$this->assertSame( 'English', HDP_I18N::kies( 'Nederlands', 'Français', 'English' ) );
	}

	public function test_bekende_startpaginatekst_wordt_engels() {
		$this->engels();
		$this->assertSame( 'Warranty', HDP_I18N::kies( 'Garantie', 'Garantie' ) );
		$this->assertSame( 'Eigen tekst', HDP_I18N::kies( 'Eigen tekst', '' ), 'Onbekende tekst blijft Nederlands.' );

		$blok = array( 'blockName' => 'core/heading' );
		$this->assertSame( '<h3 class="hdp-taal-nl">Order parts</h3>', HDP_I18N::engelse_inhoud( '<h3 class="hdp-taal-nl">Onderdelen bestellen</h3>', $blok ) );
		$this->assertSame( '<p>Iets anders met Content erin</p>', HDP_I18N::engelse_inhoud( '<p>Iets anders met Content erin</p>', array( 'blockName' => 'core/paragraph' ) ) );

		unset( $_COOKIE[ HDP_I18N::COOKIE ] );
		$this->assertSame( 'Garantie', HDP_I18N::kies( 'Garantie', 'Garantie' ) );
		$this->assertSame( '<h3>Onderdelen bestellen</h3>', HDP_I18N::engelse_inhoud( '<h3>Onderdelen bestellen</h3>', $blok ) );
	}

	public function test_kaartblok_met_eigen_engels() {
		$this->engels();
		$html = render_block(
			array(
				'blockName'    => 'homburg/portaal-kaart',
				'attrs'        => array( 'titel' => 'Garantie', 'titelEn' => 'Warranty claims', 'tekst' => 'Eigen tekst', 'knoptekst' => 'Naar garantie', 'url' => '/garantie/' ),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
		$this->assertStringContainsString( 'Warranty claims', $html );
		$this->assertStringContainsString( 'Go to warranty', $html );
		$this->assertStringContainsString( 'Eigen tekst', $html );
	}

	public function test_engelse_locale_voor_wordpress_en_woocommerce() {
		$this->engels();
		$this->assertSame( 'en_US', HDP_I18N::frontend_locale( 'nl_NL' ) );
	}

	public function test_datum_in_het_engels() {
		$this->engels();
		$ts = strtotime( '2026-10-22 14:05:00' );
		$this->assertSame( '22 October 2026', HDP_I18N::datum( 'j F Y', $ts ) );
		$this->assertSame( 'Thursday 22 Oct', HDP_I18N::datum( 'l j M', $ts ) );
		$this->assertSame( 'Sun', HDP_I18N::datum( 'D', strtotime( '2026-11-15 10:00:00' ) ) );
	}

	public function test_taalknop_toont_drie_talen() {
		$html = render_block( array( 'blockName' => 'homburg/site-header', 'attrs' => array(), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ) );
		foreach ( array( 'nl', 'fr', 'en' ) as $taal ) {
			$this->assertStringContainsString( 'hdp_taal=' . $taal, $html );
		}
	}

	public function test_foutscherm_in_het_engels() {
		$this->engels();
		$html = HDP_Foutscherm::html( 'niet_gevonden', 404 );
		$this->assertStringContainsString( 'This file no longer exists', $html );
		$this->assertStringContainsString( 'lang="en"', $html );
	}
}
