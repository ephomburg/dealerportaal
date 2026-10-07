<?php

/**
 * Test de afscherming van de hele uploadsmap (laag 2).
 *
 * De webserver weigert daar alles waar geen inlogkoekje bij zit, op een
 * korte witte lijst na die het inlogscherm zelf nodig heeft. Dat patroon is
 * het hele mechanisme: klopt het niet, dan is óf de site stuk (te streng) óf
 * staan de dealerbestanden open (te ruim). Vandaar dat het hier tegen echte
 * bestandsnamen van de live site wordt getoetst.
 *
 * @group hdp-uploads-afscherming
 */
class Uploads_Afscherming_Test extends WP_UnitTestCase {

	/** Hetzelfde patroon dat in de .htaccess terechtkomt. */
	private function patroon() {
		$openbaar = implode( '|', array_map( 'preg_quote', HDP_Bestandsbeveiliging::OPENBARE_BESTANDEN ) );
		return '#(^|/)(' . $openbaar . ')[^/]*$#';
	}

	private function mag_erdoor( $pad ) {
		return (bool) preg_match( $this->patroon(), $pad );
	}

	/**
	 * Wat een uitgelogde bezoeker op het inlogscherm te zien krijgt. Mist er
	 * één, dan is de site voor een niet-ingelogde bezoeker stuk.
	 */
	public function test_bestanden_van_het_inlogscherm_blijven_bereikbaar() {
		$nodig = array(
			'2026/07/Logo-HOMBURG_RGB-300x40.png',
			'2026/07/Logo-HOMBURG_WIT-1536x204.png',
			'2026/09/Godin-druppel-klein-100x100.png',
			'2026/09/Godin-druppel-klein-300x300.png',
			'2023/09/Homburg-Holland-precisielandbouw-man-in-veld.jpg',
			'2026/09/dc-100x100.png',
			'2026/09/dc.png',
		);

		foreach ( $nodig as $pad ) {
			$this->assertTrue( $this->mag_erdoor( $pad ), "Het inlogscherm heeft $pad nodig." );
		}
	}

	/** Dealerbestanden horen er juist niet door te komen. */
	public function test_dealerbestanden_worden_tegengehouden() {
		$dicht = array(
			'2026/09/Prijslijst-Rabe-machines-2024-Rev-2.pdf',
			'2026/09/RABE_Sprareparts_032026-DE-NL-FR.pdf',
			'2026/09/Onderdelenboek_HOMBURG_JUNIOR_2014.pdf',
			'2026/09/AccuRite-CTS-Gebruikershandleiding-Nederlands.pdf',
			'2026/09/Homburg-Tefen-2026-Rev1-05-03-26.pdf',
			'2026/07/voorbeeld-prijslijst.txt',
			'hdp-beveiligd/Parts-book-DELTA-2020-004.pdf',
		);

		foreach ( $dicht as $pad ) {
			$this->assertFalse( $this->mag_erdoor( $pad ), "$pad hoort niet openbaar te zijn." );
		}
	}

	/**
	 * De witte lijst mag alleen aan het begin van een bestandsnaam matchen.
	 * Anders glipt elk bestand dat zo'n term ergens in de naam heeft erdoor —
	 * en "dc" staat in meer woorden dan je denkt.
	 */
	public function test_witte_lijst_matcht_alleen_aan_het_begin_van_de_naam() {
		$this->assertFalse( $this->mag_erdoor( '2026/11/handleiding-dc-prijzen-geheim.pdf' ) );
		$this->assertFalse( $this->mag_erdoor( '2026/11/kopie-Logo-HOMBURG-prijzen.pdf' ) );
		$this->assertFalse( $this->mag_erdoor( '2026/11/prijslijst-Godin-druppel-klein.pdf' ) );
	}

	/* --- De regels zelf ------------------------------------------------ */

	public function test_regels_laten_ingelogde_bezoekers_door_en_de_rest_niet() {
		$regels = $this->regels();

		$this->assertStringContainsString( 'RewriteCond %{HTTP_COOKIE} wordpress_logged_in_', $regels );
		$this->assertStringContainsString( '[F,L]', $regels, 'Zonder weigerregel doet de afscherming niets.' );
	}

	/**
	 * Zonder mod_rewrite mag het bestand niets doen in plaats van de site
	 * stukmaken; vandaar de IfModule eromheen.
	 */
	public function test_regels_staan_binnen_een_ifmodule() {
		$regels = $this->regels();

		$this->assertStringContainsString( '<IfModule mod_rewrite.c>', $regels );
		$this->assertStringContainsString( '</IfModule>', $regels );
	}

	public function test_regels_zeggen_erbij_dat_ze_overschreven_worden() {
		// De plugin herschrijft dit bestand; wie het met de hand aanpast moet
		// weten dat dat geen zin heeft.
		$this->assertStringContainsString( 'overschreven', $this->regels() );
	}

	private function regels() {
		$methode = new ReflectionMethod( 'HDP_Bestandsbeveiliging', 'uploads_regels' );
		$methode->setAccessible( true );
		return $methode->invoke( null );
	}
}
