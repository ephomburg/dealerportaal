<?php

/**
 * Test HDP_Foutscherm: de "hier kunt u niet verder"-schermen in de huisstijl
 * van het portaal. Getest wordt html() — de opbouw van de pagina — want
 * toon() eindigt op exit() en is daarmee niet aanroepbaar binnen PHPUnit.
 *
 * @group hdp-foutscherm
 */
class Foutscherm_Test extends WP_UnitTestCase {

	public function tearDown(): void {
		unset( $_COOKIE[ HDP_I18N::COOKIE ] );
		delete_option( HDP_Settings::OPTION );
		parent::tearDown();
	}

	private function met_contactgegevens() {
		update_option(
			HDP_Settings::OPTION,
			array(
				'contact_email'    => 'verkoop@voorbeeld.test',
				'contact_telefoon' => '+31 (0)512 36 55 55',
			)
		);
	}

	/**
	 * De kern van dit scherm: een dealer moet er weg kunnen komen. Het kale
	 * WordPress-foutscherm dat hier eerst stond had geen enkele link.
	 */
	public function test_scherm_biedt_altijd_een_weg_terug() {
		$html = HDP_Foutscherm::html( 'geen_merkrecht', 403 );

		$this->assertStringContainsString( esc_url( home_url( '/dealerportaal/' ) ), $html );
		$this->assertStringContainsString( esc_url( home_url( '/downloads/' ) ), $html );
	}

	public function test_scherm_gebruikt_de_stijl_van_het_portaal() {
		$html = HDP_Foutscherm::html( 'geen_merkrecht', 403 );

		$this->assertStringContainsString( 'dealerportaal.css', $html );
		$this->assertStringContainsString( 'hdp-fout-kaart', $html );
	}

	/** Elk geval krijgt zijn eigen uitleg — niet overal "geen toegang". */
	public function test_elk_geval_heeft_een_eigen_boodschap() {
		$merk        = HDP_Foutscherm::html( 'geen_merkrecht', 403 );
		$ingelogd    = HDP_Foutscherm::html( 'niet_ingelogd', 403 );
		$goedkeuring = HDP_Foutscherm::html( 'wacht_goedkeuring', 403 );
		$weg         = HDP_Foutscherm::html( 'niet_gevonden', 404 );

		$this->assertStringContainsString( 'merk dat niet aan uw account gekoppeld is', $merk );
		$this->assertStringContainsString( 'niet meer ingelogd', $ingelogd );
		$this->assertStringContainsString( 'in behandeling', $goedkeuring );
		$this->assertStringContainsString( 'bestaat niet meer', $weg );
	}

	public function test_onbekende_sleutel_valt_terug_op_niet_gevonden() {
		$html = HDP_Foutscherm::html( 'iets-wat-niet-bestaat', 404 );

		$this->assertStringContainsString( 'bestaat niet meer', $html );
	}

	public function test_scherm_volgt_de_franse_taalkeuze() {
		$_COOKIE[ HDP_I18N::COOKIE ] = 'fr';

		$html = HDP_Foutscherm::html( 'geen_merkrecht', 403 );

		$this->assertStringContainsString( 'lang="fr"', $html );
		$this->assertStringContainsString( 'Ce fichier concerne une autre marque', $html );
	}

	public function test_contactgegevens_verschijnen_waar_ze_helpen() {
		$this->met_contactgegevens();

		$html = HDP_Foutscherm::html( 'geen_merkrecht', 403 );

		$this->assertStringContainsString( 'verkoop@voorbeeld.test', $html );
		$this->assertStringContainsString( 'tel:+310512365555', $html );
	}

	/**
	 * Niet elk scherm is een contactvraag: bij een verlopen sessie is
	 * opnieuw inloggen het antwoord, niet bellen.
	 */
	public function test_geen_contactregel_waar_die_niet_hoort() {
		$this->met_contactgegevens();

		$html = HDP_Foutscherm::html( 'niet_ingelogd', 403 );

		$this->assertStringNotContainsString( 'verkoop@voorbeeld.test', $html );
	}

	public function test_zonder_ingevulde_contactgegevens_blijft_het_scherm_heel() {
		$html = HDP_Foutscherm::html( 'geen_merkrecht', 403 );

		$this->assertStringNotContainsString( 'Contact opnemen', $html );
		$this->assertStringContainsString( 'hdp-fout-kaart', $html );
	}

	public function test_paginakop_bevat_de_statuscode_voor_herkenbaarheid() {
		$this->assertStringContainsString( 'Code 403', HDP_Foutscherm::html( 'geen_merkrecht', 403 ) );
		$this->assertStringContainsString( 'Code 404', HDP_Foutscherm::html( 'niet_gevonden', 404 ) );
	}

	/**
	 * De filter hangt in WordPress' globale wp_die_handler. Zonder deze
	 * grens zou élke foutmelding in WordPress — ook die van de beheerkant
	 * of van andere plugins — ineens het dealerportaalscherm worden.
	 */
	public function test_alleen_eigen_aanroepen_krijgen_het_portaalscherm() {
		$this->assertTrue( HDP_Foutscherm::is_portaalscherm( array( 'hdp_portaal' => 'geen_merkrecht' ) ) );

		$this->assertFalse( HDP_Foutscherm::is_portaalscherm( array( 'response' => 500 ) ) );
		$this->assertFalse( HDP_Foutscherm::is_portaalscherm( array() ) );
		$this->assertFalse( HDP_Foutscherm::is_portaalscherm( '' ) );
	}
}
