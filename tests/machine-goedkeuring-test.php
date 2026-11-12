<?php

/**
 * Pas claimen na goedkeuring (2026-11-12): een aangemelde machine moet
 * eerst door Homburg geaccepteerd zijn voordat er een claim op kan.
 *
 * @group hdp-machine-goedkeuring
 */
class Machine_Goedkeuring_Test extends WP_UnitTestCase {

	private function machine( $status ) {
		return array(
			'id'           => 'm1',
			'machine'      => 'Tempo T6',
			'serienummer'  => 'SN-1',
			'status'       => $status,
			'aankoopdatum' => gmdate( 'Y-m-d', strtotime( '-2 months' ) ),
			'garantie_tot' => gmdate( 'Y-m-d', strtotime( '+10 months' ) ),
		);
	}

	public function test_alleen_goedgekeurd_is_claimbaar() {
		$this->assertSame( '', HDP_Garantie::waarom_niet_claimbaar( $this->machine( 'goedgekeurd' ) ) );
		$this->assertSame( 'in_behandeling', HDP_Garantie::waarom_niet_claimbaar( $this->machine( 'in_behandeling' ) ) );
		$this->assertSame( 'in_behandeling', HDP_Garantie::waarom_niet_claimbaar( $this->machine( '' ) ), 'Zonder status: nog niet goedgekeurd.' );
		$this->assertSame( 'afgewezen', HDP_Garantie::waarom_niet_claimbaar( $this->machine( 'afgewezen' ) ) );
	}

	public function test_goedgekeurd_maar_verlopen_blijft_verlopen() {
		$m                 = $this->machine( 'goedgekeurd' );
		$m['aankoopdatum'] = gmdate( 'Y-m-d', strtotime( '-3 years' ) );
		$m['garantie_tot'] = gmdate( 'Y-m-d', strtotime( '-1 year' ) );
		$this->assertSame( 'verlopen', HDP_Garantie::waarom_niet_claimbaar( $m ) );
	}

	public function test_teksten_zeggen_dat_goedkeuring_eerst_komt() {
		foreach ( array( 'nl', 'fr', 'en' ) as $taal ) {
			$this->assertNotSame( 'garantie_fout_machine_in_behandeling', HDP_I18N::t_in( 'garantie_fout_machine_in_behandeling', $taal ), $taal );
		}
		$this->assertStringContainsString( 'goedgekeurd', HDP_I18N::t_in( 'garantie_ok_machine', 'nl' ) );
	}
}
