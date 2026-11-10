<?php

/**
 * Test dat de winkelpagina's hun eigen sjabloon krijgen, los van hun adres.
 *
 * Wat hier fout ging: na de Nederlandse adressen (/mijn-account/) vond
 * WordPress page-my-account.html niet meer en toonde hij Mijn account als
 * kale donkere pagina zonder werkvlak en zijmenu.
 *
 * @group hdp-sjablonen
 */
class Sjabloon_Per_Pagina_Test extends WP_UnitTestCase {

	public static function wpSetUpBeforeClass() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		require_once dirname( __DIR__ ) . '/app/public/wp-content/themes/homburg-dealerportaal/includes/woocommerce/setup.php';
	}

	public function setUp(): void {
		parent::setUp();
		if ( ! function_exists( 'homburg_wc_sjabloon_per_pagina' ) ) {
			$this->markTestSkipped( 'WooCommerce is niet geladen.' );
		}
	}

	private function pagina( $optie, $slug ) {
		$id = self::factory()->post->create( array( 'post_type' => 'page', 'post_name' => $slug, 'post_status' => 'publish' ) );
		update_option( $optie, $id );
		return $id;
	}

	public function test_mijn_account_krijgt_accountsjabloon_ondanks_nederlands_adres() {
		$id = $this->pagina( 'woocommerce_myaccount_page_id', 'mijn-account' );
		$this->go_to( get_permalink( $id ) );

		$sjablonen = homburg_wc_sjabloon_per_pagina( array( 'page-mijn-account.php', 'page-' . $id . '.php', 'page.php' ) );
		$this->assertSame( 'page-my-account.php', $sjablonen[0] );
	}

	public function test_winkelwagen_en_afrekenen_ook() {
		$wagen = $this->pagina( 'woocommerce_cart_page_id', 'winkelwagen' );
		$this->go_to( get_permalink( $wagen ) );
		$this->assertSame( 'page-cart.php', homburg_wc_sjabloon_per_pagina( array( 'page.php' ) )[0] );

		$af = $this->pagina( 'woocommerce_checkout_page_id', 'afrekenen' );
		$this->go_to( get_permalink( $af ) );
		$this->assertSame( 'page-checkout.php', homburg_wc_sjabloon_per_pagina( array( 'page.php' ) )[0] );
	}

	public function test_gewone_pagina_blijft_ongemoeid() {
		$id = self::factory()->post->create( array( 'post_type' => 'page', 'post_name' => 'over-ons', 'post_status' => 'publish' ) );
		$this->go_to( get_permalink( $id ) );
		$this->assertSame( array( 'page-over-ons.php', 'page.php' ), homburg_wc_sjabloon_per_pagina( array( 'page-over-ons.php', 'page.php' ) ) );
	}
}
