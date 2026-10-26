<?php

/**
 * Bewaakt de teksten in HDP_I18N.
 *
 * Aanleiding: in de Franse teksten stonden 76 reeksen als é in plaats
 * van é. In een PHP-string tussen enkele aanhalingstekens betekent \u
 * niets, dus die reeks kwam letterlijk op het scherm — "pièces" in
 * plaats van "pièces". Ze waren erin geslopen via bewerkscripts en vielen
 * niet op omdat ze alleen in het Frans stonden.
 *
 * @group hdp-teksten
 */
class Teksten_Test extends WP_UnitTestCase {

	/** Alle vertalingen, plat: sleutel.taal => tekst. */
	private function alle_teksten() {
		$methode = new ReflectionMethod( 'HDP_I18N', 'teksten' );
		$methode->setAccessible( true );

		$plat = array();
		foreach ( $methode->invoke( null ) as $sleutel => $talen ) {
			foreach ( (array) $talen as $taal => $tekst ) {
				if ( is_string( $tekst ) ) {
					$plat[ $sleutel . '.' . $taal ] = $tekst;
				}
			}
		}

		return $plat;
	}

	/**
	 * De kern: geen enkele tekst mag een onvertaalde \uXXXX-reeks bevatten.
	 * Die komt zo op het scherm terecht.
	 */
	public function test_geen_letterlijke_unicode_ontsnappingen() {
		$fout = array();

		foreach ( $this->alle_teksten() as $waar => $tekst ) {
			if ( preg_match( '/\\\\u[0-9a-fA-F]{4}/', $tekst ) ) {
				$fout[] = $waar;
			}
		}

		$this->assertSame(
			array(),
			$fout,
			"Deze teksten bevatten een \\uXXXX-reeks die als zodanig op het scherm komt:\n" . implode( "\n", $fout )
		);
	}

	/** Ook geen losse \x-reeksen, dezelfde val. */
	public function test_geen_letterlijke_hex_ontsnappingen() {
		$fout = array();

		foreach ( $this->alle_teksten() as $waar => $tekst ) {
			if ( preg_match( '/\\\\x[0-9a-fA-F]{2}/', $tekst ) ) {
				$fout[] = $waar;
			}
		}

		$this->assertSame( array(), $fout, 'Teksten met een \\xXX-reeks: ' . implode( ', ', $fout ) );
	}

	/**
	 * En geen losse backslash voor een gewoon teken.
	 *
	 * In een PHP-tekst tussen enkele aanhalingstekens betekent een backslash
	 * alleen iets vóór een andere backslash of een aanhalingsteken. Staat
	 * hij ergens anders, dan komt hij letterlijk op het scherm: er stond
	 * "klant\…" en "numéro" werd "num\éro". Zelfde oorzaak als hierboven —
	 * een bewerkscript dat een escape half liet staan.
	 */
	public function test_geen_losse_backslash_voor_een_gewoon_teken() {
		$fout = array();

		foreach ( $this->alle_teksten() as $waar => $tekst ) {
			if ( preg_match( '/\\\\[^\\\\\']/u', $tekst ) ) {
				$fout[] = $waar . ' => ' . $tekst;
			}
		}

		$this->assertSame(
			array(),
			$fout,
			"Deze teksten hebben een losse backslash die op het scherm komt:\n" . implode( "\n", $fout )
		);
	}

	/**
	 * Elke sleutel hoort zowel Nederlands als Frans te hebben. Ontbreekt het
	 * Frans, dan valt HDP_I18N terug op het Nederlands — dat is te verdedigen,
	 * maar het hoort een bewuste keuze te zijn en geen vergeten regel.
	 */
	public function test_elke_tekst_heeft_nederlands_en_frans() {
		$methode = new ReflectionMethod( 'HDP_I18N', 'teksten' );
		$methode->setAccessible( true );

		$zonder_frans = array();
		foreach ( $methode->invoke( null ) as $sleutel => $talen ) {
			$talen = (array) $talen;
			$this->assertArrayHasKey( 'nl', $talen, "Sleutel \"$sleutel\" heeft geen Nederlandse tekst." );

			if ( empty( $talen['fr'] ) ) {
				$zonder_frans[] = $sleutel;
			}
		}

		$this->assertSame( array(), $zonder_frans, 'Zonder Franse tekst: ' . implode( ', ', $zonder_frans ) );
	}

	/**
	 * Het portaal spreekt een dealer aan met "je". Dat is één keer in één
	 * keer omgezet — ruim zestig teksten — omdat het een mengsel was: de
	 * startpagina zei "jouw socials", de rest "u heeft" en "uw account".
	 * Zo'n mengsel ontstaat weer zodra er één nieuwe tekst met "u" bijkomt.
	 * (Het Frans blijft "vous": "tu" is in zakelijk Frans ongebruikelijk.)
	 */
	public function test_nederlands_spreekt_de_dealer_aan_met_je() {
		$fout = array();

		foreach ( $this->alle_teksten() as $waar => $tekst ) {
			if ( '.nl' === substr( $waar, -3 ) && preg_match( '/(?<![\p{L}])(u|uw)(?![\p{L}])/iu', $tekst ) ) {
				$fout[] = $waar . ' => ' . $tekst;
			}
		}

		$this->assertSame( array(), $fout, "Deze teksten gebruiken nog \"u\" of \"uw\":\n" . implode( "\n", $fout ) );
	}

	/** De teksten van het nieuwe zijmenu horen er allemaal te zijn. */
	public function test_het_zijmenu_heeft_zijn_teksten() {
		foreach ( array( 'zijbalk_label', 'zijbalk_bestellingen', 'zijbalk_groep_bestellen', 'zijbalk_groep_service', 'zijbalk_groep_materiaal' ) as $sleutel ) {
			$this->assertNotSame(
				$sleutel,
				HDP_I18N::t( $sleutel ),
				"De tekst \"$sleutel\" ontbreekt; HDP_I18N geeft dan de sleutel zelf terug."
			);
		}
	}
}
