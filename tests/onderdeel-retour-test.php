<?php

/**
 * Test de regel achter een onderdelenclaim: wij nemen die pas in
 * behandeling zodra het onderdeel bij ons in Stiens binnen is.
 *
 * Dat was eerder een vinkje ("ik stuur onderdelen retour") met een
 * hintregel eronder. Daarmee was het iets dat de dealer kon overslaan,
 * terwijl het bepaalt of zijn claim überhaupt in behandeling komt. Nu is
 * het de regel, en hoort het op elk scherm te staan waar de dealer met
 * zulke claims te maken heeft.
 *
 * Eén uitzondering: is het onderdeel nooit aangekomen, dan is er niets om
 * terug te sturen — dat eisen zou de claim voorgoed laten hangen.
 *
 * @group hdp-onderdeel-retour
 */
class Onderdeel_Retour_Test extends WP_UnitTestCase {

	/**
	 * Een claim zoals HDP_Garantie::claim_voor_dealer() die teruggeeft.
	 *
	 * @param string $soort     'onderdeel' of 'machine'.
	 * @param string $probleem  Bij een onderdelenclaim: wat er mis is.
	 * @param bool   $retour    Het oude vinkje; bij een machineclaim de keuze.
	 * @param string $ontvangen Wanneer het pakket binnenkwam, of ''.
	 */
	private function claim( $soort, $probleem = 'defect', $retour = false, $ontvangen = '' ) {
		return array(
			'nummer'                 => 'T100007',
			'status'                 => 'ingediend',
			'ingediend_op'           => '2026-10-01 09:00:00',
			'machine'                => '',
			'serienummer'            => '',
			'merk'                   => 'HARDI',
			'soort'                  => $soort,
			'onderdeel_probleem'     => $probleem,
			'onderdeel_retour'       => $retour,
			'onderdeel_ontvangen_op' => $ontvangen,
			'verloop'                => array(
				array(
					'status'  => 'ingediend',
					'wie'     => 'Test Dealer',
					'wanneer' => '2026-10-01 09:00:00',
				),
			),
		);
	}

	private function fases( $claim ) {
		return array_map(
			static function ( $stap ) {
				return $stap['fase'];
			},
			HDP_Garantie::route( $claim )
		);
	}

	private function stap( $claim, $fase ) {
		foreach ( HDP_Garantie::route( $claim ) as $stap ) {
			if ( $fase === $stap['fase'] ) {
				return $stap;
			}
		}
		return null;
	}

	/* --- De regel zelf -------------------------------------------------- */

	/**
	 * De kern: bij een onderdelenclaim verwachten we het onderdeel, ook
	 * zonder dat de dealer ergens iets heeft aangevinkt.
	 */
	public function test_onderdelenclaim_verwacht_altijd_een_onderdeel() {
		$this->assertTrue( HDP_Garantie::onderdeel_verwacht( $this->claim( 'onderdeel', 'defect', false ) ) );
	}

	/** Voor elk probleem waarbij de dealer iets in handen heeft. */
	public function test_elk_probleem_met_een_onderdeel_verwacht_dat_onderdeel() {
		foreach ( HDP_Garantie::ONDERDEEL_PROBLEMEN as $probleem ) {
			if ( HDP_Garantie::PROBLEEM_ZONDER_ONDERDEEL === $probleem ) {
				continue;
			}
			$this->assertTrue(
				HDP_Garantie::onderdeel_verwacht( $this->claim( 'onderdeel', $probleem ) ),
				'Bij "' . $probleem . '" hoort het onderdeel naar Stiens te komen.'
			);
		}
	}

	/** Nooit aangekomen = niets om terug te sturen. */
	public function test_niet_ontvangen_verwacht_geen_onderdeel() {
		$this->assertFalse( HDP_Garantie::onderdeel_verwacht( $this->claim( 'onderdeel', 'niet_ontvangen' ) ) );
	}

	/**
	 * Bij een machineclaim blijft het de keuze van de dealer: die reparatie
	 * is vaak ter plekke gedaan en dan komt er niets retour.
	 */
	public function test_machineclaim_volgt_het_vinkje() {
		$this->assertFalse( HDP_Garantie::onderdeel_verwacht( $this->claim( 'machine', '', false ) ) );
		$this->assertTrue( HDP_Garantie::onderdeel_verwacht( $this->claim( 'machine', '', true ) ) );
	}

	/* --- De route ------------------------------------------------------- */

	public function test_onderdelenclaim_krijgt_de_retourstap_zonder_vinkje() {
		$this->assertContains( 'retour', $this->fases( $this->claim( 'onderdeel', 'defect', false ) ) );
	}

	public function test_niet_ontvangen_krijgt_geen_retourstap() {
		$this->assertNotContains( 'retour', $this->fases( $this->claim( 'onderdeel', 'niet_ontvangen' ) ) );
	}

	/**
	 * Zolang het pakket onderweg is, hoort ook bij "in behandeling" te staan
	 * waarom daar nog niets gebeurt. Anders ziet de dealer een lege grijze
	 * stap en lijkt het alsof wij stilzitten.
	 */
	public function test_behandeling_vertelt_dat_hij_op_het_onderdeel_wacht() {
		$stap = $this->stap( $this->claim( 'onderdeel', 'defect', false, '' ), 'behandeling' );

		$this->assertSame( HDP_I18N::t( 'garantie_behandeling_na_ontvangst' ), $stap['hint'] );
	}

	public function test_na_ontvangst_verdwijnt_die_toelichting() {
		$stap = $this->stap( $this->claim( 'onderdeel', 'defect', false, '2026-10-03 10:00:00' ), 'behandeling' );

		$this->assertEmpty( $stap['hint'] );
	}

	/* --- Het formulier -------------------------------------------------- */

	/** Geen vinkje meer dat de dealer kan vergeten. */
	public function test_het_vinkje_staat_niet_meer_op_het_onderdelenformulier() {
		$velden = HDP_Garantie::invulvelden( HDP_Garantie::SOORT_ONDERDEEL );

		$this->assertArrayNotHasKey( 'onderdeel_retour', $velden );
	}

	/** Bij een machineclaim is het wél een keuze en hoort het er dus te staan. */
	public function test_het_vinkje_blijft_op_het_machineformulier() {
		$velden = HDP_Garantie::invulvelden( HDP_Garantie::SOORT_MACHINE );

		$this->assertArrayHasKey( 'onderdeel_retour', $velden );
	}

	/**
	 * De mededeling hoort boven het formulier te staan, voordat de dealer
	 * begint met invullen — niet als naschrift onderaan.
	 */
	public function test_het_formulier_meldt_de_regel_vooraf() {
		$methode = new ReflectionMethod( 'HDP_Garantie_Render', 'render_formulier' );
		$methode->setAccessible( true );

		$html = $methode->invoke( null, 'claim', HDP_Garantie::SOORT_ONDERDEEL );

		$this->assertStringContainsString( 'hdp-garantie-attentie', $html );
		$this->assertStringContainsString( HDP_I18N::t( 'garantie_retour_kop' ), $html );

		// Boven de velden, niet eronder.
		$this->assertLessThan(
			strpos( $html, 'hdp-garantie-velden' ),
			strpos( $html, 'hdp-garantie-attentie' ),
			'De mededeling hoort boven de velden te staan.'
		);
	}

	/**
	 * Bij "niet ontvangen" is er niets om op te sturen; het blok verbergt
	 * zich dan. Dat gebeurt in de browser, en het antwoord waar het om gaat
	 * geeft de PHP mee in een data-attribuut — zodat die uitzondering niet
	 * ook nog eens los in de JavaScript staat.
	 */
	public function test_het_formulier_geeft_de_uitzondering_mee_aan_de_browser() {
		$methode = new ReflectionMethod( 'HDP_Garantie_Render', 'render_formulier' );
		$methode->setAccessible( true );

		$html = $methode->invoke( null, 'claim', HDP_Garantie::SOORT_ONDERDEEL );

		$this->assertStringContainsString(
			'data-zonder-onderdeel="' . HDP_Garantie::PROBLEEM_ZONDER_ONDERDEEL . '"',
			$html
		);
		$this->assertStringContainsString( 'hdp-claim-onderdeel_probleem', $html );
	}

	/* --- Het ticketscherm ----------------------------------------------- */

	/**
	 * Niet alleen in de bevestiging die de dealer één keer ziet: zolang wij
	 * op het pakket wachten hoort het op de claim zelf te staan, met het
	 * ticketnummer dat op de doos moet.
	 */
	public function test_de_claim_blijft_de_regel_melden_met_het_ticketnummer() {
		$methode = new ReflectionMethod( 'HDP_Garantie_Render', 'render_ticket_kop' );
		$methode->setAccessible( true );

		ob_start();
		$methode->invoke( null, $this->claim( 'onderdeel', 'defect', false, '' ) );
		$wachtend = ob_get_clean();

		$this->assertStringContainsString( 'hdp-garantie-attentie', $wachtend );
		$this->assertStringContainsString( 'T100007', $wachtend );
	}

	public function test_na_ontvangst_verdwijnt_de_melding_van_de_claim() {
		$methode = new ReflectionMethod( 'HDP_Garantie_Render', 'render_ticket_kop' );
		$methode->setAccessible( true );

		ob_start();
		$methode->invoke( null, $this->claim( 'onderdeel', 'defect', false, '2026-10-03 10:00:00' ) );
		$binnen = ob_get_clean();

		$this->assertStringNotContainsString( 'hdp-garantie-attentie', $binnen );
	}

	public function test_een_machineclaim_zonder_retour_meldt_niets() {
		$methode = new ReflectionMethod( 'HDP_Garantie_Render', 'render_ticket_kop' );
		$methode->setAccessible( true );

		ob_start();
		$methode->invoke( null, $this->claim( 'machine', '', false ) );
		$html = ob_get_clean();

		$this->assertStringNotContainsString( 'hdp-garantie-attentie', $html );
	}

	/* --- De bevestiging na indienen ------------------------------------- */

	/**
	 * De bevestiging is het moment waarop de dealer het moet lezen. Die
	 * tekst hoort het ticketnummer te noemen en te zeggen dat de behandeling
	 * op het onderdeel wacht, in beide talen.
	 */
	public function test_de_bevestiging_noemt_nummer_en_voorwaarde_in_beide_talen() {
		foreach ( array( 'nl', 'fr' ) as $taal ) {
			$_COOKIE[ HDP_I18N::COOKIE ] = $taal;
			$tekst                       = sprintf( HDP_I18N::t( 'garantie_ok_claim_onderdeel' ), 'T100007' );
			unset( $_COOKIE[ HDP_I18N::COOKIE ] );

			$this->assertStringContainsString( 'T100007', $tekst );
			$this->assertStringContainsString( 'Stiens', $tekst );
			$this->assertNotSame( 'garantie_ok_claim_onderdeel', $tekst, 'De tekst ontbreekt in het ' . $taal . '.' );
		}
	}
}
