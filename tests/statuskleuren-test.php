<?php

/**
 * Bewaakt dat elke status een kleur heeft, en dat die kleur uit het palet
 * komt.
 *
 * Dit is twee keer eerder misgegaan: er werd een status toegevoegd
 * (bij_fabrikant, afgewezen_fabrikant) en die stond daarna als kale tekst
 * op het scherm, omdat niemand eraan dacht er ook een CSS-regel bij te
 * schrijven. Een test is hier de enige plek waar dat op te vangen is —
 * PHP en CSS weten verder niets van elkaar.
 *
 * @group hdp-statuskleuren
 */
class Statuskleuren_Test extends WP_UnitTestCase {

	/** De vijf betekenissen uit :root. */
	private const BETEKENISSEN = array( 'neutraal', 'loopt', 'aanzet', 'klaar', 'gestopt' );

	private function css() {
		static $css = null;
		if ( null === $css ) {
			$css = (string) file_get_contents( // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- een bestand uit de plugin zelf, geen externe bron.
				dirname( __DIR__ ) . '/app/public/wp-content/plugins/homburg-dealerportaal/assets/css/dealerportaal.css'
			);
		}
		return $css;
	}

	/** De CSS zonder het :root-blok, waar de kleuren juist wél mogen staan. */
	private function css_zonder_palet() {
		$css = $this->css();
		$van = strpos( $css, ':root' );
		$tot = strpos( $css, '}', $van );

		return substr( $css, 0, $van ) . substr( $css, $tot );
	}

	/* --- Het palet zelf ------------------------------------------------ */

	public function test_alle_vijf_betekenissen_bestaan() {
		$css = $this->css();

		foreach ( self::BETEKENISSEN as $betekenis ) {
			foreach ( array( 'vlak', 'tekst' ) as $soort ) {
				$this->assertStringContainsString(
					"--hdp-$betekenis-$soort:",
					$css,
					"Het palet mist --hdp-$betekenis-$soort."
				);
			}
		}
	}

	/* --- Elke status een kleur ----------------------------------------- */

	/**
	 * De kern. Komt er een status bij in HDP_Garantie::STATUSSEN, dan moet
	 * hier ook een regel voor bestaan — anders valt hij terug op kale tekst.
	 */
	public function test_elke_garantiestatus_heeft_een_eigen_regel() {
		$css = $this->css();

		foreach ( HDP_Garantie::STATUSSEN as $status ) {
			// De CSS-class gebruikt streepjes waar de status liggende
			// streepjes heeft: in_behandeling wordt in-behandeling.
			$class = '.hdp-garantie-status-' . str_replace( '_', '-', $status );

			$this->assertStringContainsString(
				$class,
				$css,
				"Status \"$status\" heeft geen kleur; die komt dan als kale tekst op het scherm."
			);
		}
	}

	public function test_elke_bestelstatus_heeft_een_eigen_regel() {
		// De statussen die WooCommerce standaard kent en die in de
		// bestellijst van een dealer kunnen opduiken.
		$statussen = array( 'completed', 'processing', 'on-hold', 'cancelled', 'failed', 'refunded' );
		$css       = $this->css();

		foreach ( $statussen as $status ) {
			$this->assertStringContainsString(
				'.hdp-bestel-status-' . $status,
				$css,
				"Bestelstatus \"$status\" heeft geen kleur."
			);
		}
	}

	/* --- Uit het palet, niet uit het hoofd ----------------------------- */

	/**
	 * Een statusregel hoort naar een betekenis te wijzen. Staat er een
	 * hexcode, dan is er weer een kleur "erbij verzonnen" en loopt het
	 * tweede palet opnieuw uiteen.
	 */
	public function test_statusregels_gebruiken_het_palet_en_geen_losse_kleuren() {
		$regels = array();
		preg_match_all(
			'#^\.hdp-(?:garantie-status|bestel-status|machine-beoordeling)-[^\s{]*\s*\{[^}]*\}#m',
			$this->css(),
			$treffers
		);
		$regels = $treffers[0];

		$this->assertNotEmpty( $regels, 'Geen statusregels gevonden; is de CSS verplaatst?' );

		foreach ( $regels as $regel ) {
			$this->assertDoesNotMatchRegularExpression(
				'/#[0-9a-fA-F]{3,8}\b/',
				$regel,
				"Deze statusregel gebruikt een losse kleur in plaats van het palet:\n" . trim( $regel )
			);
		}
	}

	/**
	 * Nergens buiten :root hoort nog een van de oude, met de hand gekozen
	 * statuskleuren te staan. Dat waren er vijftien, in twee sets die
	 * elkaar bijna overlapten.
	 */
	public function test_de_oude_losse_statuskleuren_zijn_verdwenen() {
		$oud = array(
			'#1a7d3a' => 'oude groen van "afgerond"',
			'#ecfdf1' => 'oude groenvlak van "afgerond"',
			'#8a6100' => 'oude oranje van "wordt verwerkt"',
			'#fff8e6' => 'oude oranjevlak van "wordt verwerkt"',
			'#8a4800' => 'derde bruintint',
			'#e8e4fb' => 'paars van "bij fabrikant"',
			'#46338f' => 'paarse tekst van "bij fabrikant"',
		);

		$css = $this->css_zonder_palet();

		foreach ( $oud as $kleur => $wat ) {
			$this->assertStringNotContainsStringIgnoringCase(
				$kleur,
				$css,
				"De $wat ($kleur) staat er nog; die hoort nu uit het palet te komen."
			);
		}
	}
}
