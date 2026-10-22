<?php

/**
 * Test de retourstap in de route van een ticket.
 *
 * Stuurt een dealer een onderdeel op, dan ligt de claim stil tot dat
 * pakket binnen is. Zonder die stap ziet hij alleen "ingediend" staan en
 * lijkt het alsof er niets gebeurt, terwijl de bal bij de post ligt.
 *
 * De stap mag alleen verschijnen als er ook echt iets onderweg is, en moet
 * tussen "ingediend" en "behandeling" staan — niet erachteraan.
 *
 * @group hdp-retourstap
 */
class Retourstap_Test extends WP_UnitTestCase {

	/**
	 * Een claim zoals HDP_Garantie::claim_voor_dealer() die teruggeeft.
	 *
	 * @param bool   $retour    Heeft de dealer een onderdeel opgestuurd?
	 * @param string $ontvangen Wanneer het binnenkwam, of '' als dat nog niet zo is.
	 * @param string $status    Huidige status van de claim.
	 */
	private function claim( $retour, $ontvangen = '', $status = 'in_behandeling' ) {
		return array(
			'nummer'                 => 'T100001',
			'status'                 => $status,
			'ingediend_op'           => '2026-10-01 09:00:00',
			'onderdeel_retour'       => $retour,
			'onderdeel_ontvangen_op' => $ontvangen,
			'verloop'                => array(
				array(
					'status'  => 'ingediend',
					'wie'     => 'Test Dealer',
					'wanneer' => '2026-10-01 09:00:00',
				),
				array(
					'status'  => $status,
					'wie'     => 'Gerard de Boer',
					'wanneer' => '2026-10-03 11:00:00',
				),
			),
		);
	}

	/** De fases van de route, op volgorde. */
	private function fases( $claim ) {
		return array_map(
			static function ( $stap ) {
				return $stap['fase'];
			},
			HDP_Garantie::route( $claim )
		);
	}

	private function retourstap( $claim ) {
		foreach ( HDP_Garantie::route( $claim ) as $stap ) {
			if ( 'retour' === $stap['fase'] ) {
				return $stap;
			}
		}
		return null;
	}

	/* --- Wanneer de stap verschijnt ------------------------------------ */

	/**
	 * Stuurt de dealer niets op, dan hoort er ook geen stap te staan.
	 * Een grijze "onderdeel onderweg" bij elke claim is ruis.
	 */
	public function test_geen_stap_als_er_niets_onderweg_is() {
		$this->assertNull( $this->retourstap( $this->claim( false ) ) );
		$this->assertNotContains( 'retour', $this->fases( $this->claim( false ) ) );
	}

	public function test_stap_verschijnt_als_de_dealer_iets_opstuurt() {
		$this->assertNotNull( $this->retourstap( $this->claim( true ) ) );
	}

	/* --- Waar de stap staat -------------------------------------------- */

	/**
	 * De kern: tussen indienen en behandelen. Komt hij erachter, dan
	 * vertelt de route het verhaal in de verkeerde volgorde.
	 */
	public function test_stap_staat_tussen_ingediend_en_behandeling() {
		$fases = $this->fases( $this->claim( true ) );

		$ingediend = array_search( 'ingediend', $fases, true );
		$retour    = array_search( 'retour', $fases, true );
		$behandeling = array_search( 'behandeling', $fases, true );

		$this->assertNotFalse( $ingediend );
		$this->assertNotFalse( $retour );
		$this->assertNotFalse( $behandeling );
		$this->assertGreaterThan( $ingediend, $retour, 'De retourstap hoort ná het indienen te komen.' );
		$this->assertLessThan( $behandeling, $retour, 'De retourstap hoort vóór de behandeling te komen.' );
	}

	/* --- De twee toestanden -------------------------------------------- */

	public function test_nog_niet_binnen_is_een_openstaande_stap() {
		$stap = $this->retourstap( $this->claim( true, '' ) );

		$this->assertTrue( $stap['wacht'], 'Zolang het onderdeel niet binnen is, wacht de claim erop.' );
		$this->assertFalse( $stap['gedaan'] );
	}

	public function test_binnengekomen_onderdeel_is_een_afgeronde_stap() {
		$stap = $this->retourstap( $this->claim( true, '2026-10-02 14:30:00' ) );

		$this->assertTrue( $stap['gedaan'] );
		$this->assertFalse( $stap['wacht'] );
		$this->assertSame( '2026-10-02 14:30:00', $stap['wanneer'], 'De datum van binnenkomst hoort erbij te staan.' );
	}

	/**
	 * De stap mag niet meedingen naar "huidig": de route kent precies één
	 * huidige stap, en dat is de laatste statuswijziging.
	 */
	public function test_stap_is_nooit_de_huidige_stap() {
		foreach ( array( '', '2026-10-02 14:30:00' ) as $ontvangen ) {
			$this->assertFalse( $this->retourstap( $this->claim( true, $ontvangen ) )['huidig'] );
		}

		$huidig = array_filter(
			HDP_Garantie::route( $this->claim( true ) ),
			static function ( $stap ) {
				return ! empty( $stap['huidig'] );
			}
		);
		$this->assertCount( 1, $huidig, 'Er hoort precies één huidige stap te zijn.' );
	}

	/**
	 * Zolang het pakket onderweg is hoort het ticketnummer erbij te staan:
	 * zonder dat nummer op de doos kan niemand het terugvinden.
	 */
	public function test_onderweg_noemt_het_ticketnummer() {
		$stap = $this->retourstap( $this->claim( true, '' ) );

		$this->assertStringContainsString( 'T100001', $stap['hint'] );
	}

	public function test_ontvangen_onderdeel_heeft_geen_hint_meer() {
		$stap = $this->retourstap( $this->claim( true, '2026-10-02 14:30:00' ) );

		$this->assertSame( '', $stap['hint'] );
	}

	/* --- In het scherm -------------------------------------------------- */

	public function test_de_stap_komt_met_de_juiste_tekst_op_het_scherm() {
		$methode = new ReflectionMethod( 'HDP_Garantie_Render', 'render_route' );
		$methode->setAccessible( true );

		ob_start();
		$methode->invoke( null, $this->claim( true, '' ) );
		$onderweg = ob_get_clean();

		ob_start();
		$methode->invoke( null, $this->claim( true, '2026-10-02 14:30:00' ) );
		$binnen = ob_get_clean();

		$this->assertStringContainsString( HDP_I18N::t( 'garantie_retour_onderweg' ), $onderweg );
		$this->assertStringContainsString( 'hdp-route-wacht', $onderweg );

		$this->assertStringContainsString( 'T100001', $onderweg, 'Het ticketnummer hoort bij de stap te staan.' );

		$this->assertStringContainsString( HDP_I18N::t( 'garantie_retour_ontvangen' ), $binnen );
		$this->assertStringNotContainsString( 'hdp-route-wacht', $binnen );

		// De datum van binnenkomst hoort zichtbaar te zijn, ook al staat er
		// geen naam bij — er kwam een pakket binnen, niemand "deed" iets.
		// Via dezelfde helper als het scherm gebruikt, zodat deze test niet
		// afhangt van de taal van de testomgeving.
		$this->assertStringContainsString(
			HDP_I18N::datum( 'j M H:i', strtotime( '2026-10-02 14:30:00' ) ),
			$binnen
		);
	}
}
