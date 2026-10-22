<?php

/**
 * Test HDP_I18N::datum().
 *
 * Datums volgden de taal van de site in plaats van die van de dealer,
 * waardoor er bij een Waalse dealer "22 oktober 2026" tussen verder Franse
 * tekst stond. De omzetting gaat teken voor teken door het formaat heen —
 * precies het soort code dat er goed uitziet en toch onzin oplevert als er
 * één letter niet ontsnapt wordt ("octobre" bevat een o, een c, een t...
 * allemaal geldige formaattekens voor date()).
 *
 * @group hdp-datum
 */
class Datum_Test extends WP_UnitTestCase {

	/** 22 oktober 2026, 14:05 — een donderdag. */
	private $tijdstempel;

	public function setUp(): void {
		parent::setUp();
		$this->tijdstempel = strtotime( '2026-10-22 14:05:00' );
	}

	public function tearDown(): void {
		unset( $_COOKIE[ HDP_I18N::COOKIE ] );
		parent::tearDown();
	}

	private function frans() {
		$_COOKIE[ HDP_I18N::COOKIE ] = 'fr';
	}

	/* --- Nederlands blijft Nederlands ---------------------------------- */

	public function test_nederlands_verandert_niet() {
		$this->assertSame(
			date_i18n( 'j F Y', $this->tijdstempel ),
			HDP_I18N::datum( 'j F Y', $this->tijdstempel ),
			'Voor een Nederlandse dealer hoort er niets te veranderen.'
		);
	}

	/* --- Frans --------------------------------------------------------- */

	public function test_volledige_maandnaam_in_het_frans() {
		$this->frans();

		$this->assertSame( '22 octobre 2026', HDP_I18N::datum( 'j F Y', $this->tijdstempel ) );
	}

	public function test_korte_maandnaam_in_het_frans() {
		$this->frans();

		$this->assertSame( '22 oct. 2026', HDP_I18N::datum( 'j M Y', $this->tijdstempel ) );
	}

	public function test_datum_met_tijd() {
		$this->frans();

		$this->assertSame( '22 oct. 14:05', HDP_I18N::datum( 'j M H:i', $this->tijdstempel ) );
	}

	public function test_dagnaam_in_het_frans() {
		$this->frans();

		$this->assertSame( 'jeudi', HDP_I18N::datum( 'l', $this->tijdstempel ) );
		$this->assertSame( 'jeu.', HDP_I18N::datum( 'D', $this->tijdstempel ) );
	}

	/**
	 * Maandnamen met een accent mogen niet half ontsnapt raken: de letters
	 * eromheen wel, het accentteken niet.
	 */
	public function test_maand_met_accent_blijft_heel() {
		$this->frans();

		$februari = strtotime( '2026-02-03 09:00:00' );
		$augustus = strtotime( '2026-08-03 09:00:00' );
		$december = strtotime( '2026-12-03 09:00:00' );

		$this->assertSame( '3 février 2026', HDP_I18N::datum( 'j F Y', $februari ) );
		$this->assertSame( '3 août 2026', HDP_I18N::datum( 'j F Y', $augustus ) );
		$this->assertSame( '3 décembre 2026', HDP_I18N::datum( 'j F Y', $december ) );
	}

	/** Alle twaalf, zodat een verschoven tabel meteen opvalt. */
	public function test_alle_maanden_staan_op_de_juiste_plek() {
		$this->frans();

		$verwacht = array(
			1  => 'janvier',
			2  => 'février',
			3  => 'mars',
			4  => 'avril',
			5  => 'mai',
			6  => 'juin',
			7  => 'juillet',
			8  => 'août',
			9  => 'septembre',
			10 => 'octobre',
			11 => 'novembre',
			12 => 'décembre',
		);

		foreach ( $verwacht as $nummer => $naam ) {
			$ts = strtotime( sprintf( '2026-%02d-15 12:00:00', $nummer ) );
			$this->assertSame( $naam, HDP_I18N::datum( 'F', $ts ), "Maand $nummer komt verkeerd uit." );
		}
	}

	/**
	 * Een al ontsnapt teken in het formaat hoort letterlijk te blijven en
	 * niet alsnog als formaatteken gelezen te worden.
	 */
	public function test_ontsnapte_tekens_blijven_staan() {
		$this->frans();

		$this->assertSame( 'F 22 octobre', HDP_I18N::datum( '\F j F', $this->tijdstempel ) );
	}
}
