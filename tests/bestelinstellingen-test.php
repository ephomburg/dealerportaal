<?php

/**
 * Test het vinkje "Bestellen in de webshop" (bestellen.php in het thema).
 *
 * Belangrijk: twee keer toepassen mag niets dubbel aanmaken, en het
 * vinkje uitzetten moet afrekenen echt onmogelijk maken.
 *
 * @group hdp-bestelinstellingen
 */
class Bestelinstellingen_Test extends WP_UnitTestCase {

	public static function wpSetUpBeforeClass() {
		if ( class_exists( 'WooCommerce' ) && ! function_exists( 'homburg_wc_bestelinstellingen_toepassen' ) ) {
			require_once dirname( __DIR__ ) . '/app/public/wp-content/themes/homburg-dealerportaal/includes/woocommerce/bestellen.php';
		}
	}

	public function setUp(): void {
		parent::setUp();
		if ( ! function_exists( 'homburg_wc_bestelinstellingen_toepassen' ) ) {
			$this->markTestSkipped( 'WooCommerce is niet geladen.' );
		}
	}

	private function gebieden_voor( $land ) {
		$aantal = 0;
		foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
			foreach ( $zone['zone_locations'] as $plek ) {
				if ( 'country' === $plek->type && $land === $plek->code ) {
					++$aantal;
				}
			}
		}
		return $aantal;
	}

	public function test_zet_alles_klaar_en_maakt_niets_dubbel() {
		$eerste = homburg_wc_bestelinstellingen_toepassen();
		$this->assertNotEmpty( $eerste );

		$cheque = get_option( 'woocommerce_cheque_settings' );
		$this->assertSame( 'yes', $cheque['enabled'] );
		$this->assertSame( 'shipping', get_option( 'woocommerce_ship_to_destination' ) );
		$this->assertSame( 1, $this->gebieden_voor( 'NL' ) );
		$this->assertSame( 1, $this->gebieden_voor( 'BE' ) );

		$this->assertSame( array(), homburg_wc_bestelinstellingen_toepassen(), 'De tweede keer is er niets meer te doen.' );
		$this->assertSame( 1, $this->gebieden_voor( 'NL' ) );
	}

	public function test_vinkje_uit_zet_op_rekening_uit() {
		homburg_wc_bestelinstellingen_toepassen();

		homburg_wc_bestellen_omgezet( array( 'bestellen_aan' => '1' ), array( 'bestellen_aan' => '' ) );

		$cheque = get_option( 'woocommerce_cheque_settings' );
		$this->assertSame( 'no', $cheque['enabled'] );
	}

	public function test_vinkje_uit_dat_al_uit_stond_raakt_niets() {
		update_option( 'woocommerce_cheque_settings', array( 'enabled' => 'yes' ) );

		homburg_wc_bestellen_omgezet( array( 'bestellen_aan' => '' ), array( 'bestellen_aan' => '' ) );

		$cheque = get_option( 'woocommerce_cheque_settings' );
		$this->assertSame( 'yes', $cheque['enabled'] );
	}
}
