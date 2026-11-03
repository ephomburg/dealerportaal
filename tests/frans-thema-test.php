<?php

/**
 * Bewaakt het Frans van de teksten die niet in HDP_I18N::teksten() staan: die
 * uit het thema, en de overstap naar de taalbestanden van WordPress.
 *
 * Aanleiding: de teksttest keek alleen naar de woordenlijst van de plugin,
 * terwijl de webshop in het Frans nog "Zoeken" en "In winkelwagen" toonde.
 *
 * @group hdp-frans
 */
class Frans_Thema_Test extends WP_UnitTestCase {

	public function tearDown(): void {
		unset( $_COOKIE[ HDP_I18N::COOKIE ] );
		parent::tearDown();
	}

	/** Elke tekst die het thema via __() en verwanten gebruikt. */
	private function themateksten() {
		$map = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( dirname( __DIR__ ) . '/app/public/wp-content/themes/homburg-dealerportaal' ) );
		$uit = array();
		foreach ( $map as $bestand ) {
			if ( 'php' !== $bestand->getExtension() ) {
				continue;
			}
			preg_match_all( "~(?:esc_html_e|esc_html__|esc_attr_e|esc_attr__|__|_e)\(\s*'([^']+)',\s*'homburg-dealerportaal-theme'~", file_get_contents( $bestand->getPathname() ), $treffers );
			foreach ( $treffers[1] as $tekst ) {
				$uit[ $tekst ] = true;
			}
		}
		return array_keys( $uit );
	}

	public function test_elke_themetekst_heeft_frans() {
		$teksten = $this->themateksten();
		$this->assertNotEmpty( $teksten );

		$frans = HDP_I18N::thema_teksten();
		$mist  = array();
		foreach ( $teksten as $tekst ) {
			if ( empty( $frans[ $tekst ] ) ) {
				$mist[] = $tekst;
			}
		}
		$this->assertSame( array(), $mist, "Zonder Frans:\n" . implode( "\n", $mist ) );
	}

	public function test_invulplekken_kloppen() {
		foreach ( HDP_I18N::thema_teksten() as $nl => $fr ) {
			$this->assertSame( substr_count( $nl, '%' ), substr_count( $fr, '%' ), $nl );
		}
	}

	public function test_themetekst_volgt_de_taal() {
		$this->assertSame( 'Zoeken', HDP_I18N::thema_tekst( 'Zoeken', 'Zoeken' ) );

		$_COOKIE[ HDP_I18N::COOKIE ] = 'fr';
		$this->assertSame( 'Rechercher', HDP_I18N::thema_tekst( 'Zoeken', 'Zoeken' ) );
		$this->assertSame( 'Onbekend', HDP_I18N::thema_tekst( 'Onbekend', 'Onbekend' ) );
	}

	public function test_nederlands_blijft_op_de_eigen_locale() {
		$this->assertSame( 'nl_NL', HDP_I18N::frontend_locale( 'nl_NL' ) );
	}

	public function test_portaal_is_niet_voor_zoekmachines() {
		update_option( 'blog_public', '1' );
		$this->assertSame( '0', (string) get_option( 'blog_public' ) );
	}
}
