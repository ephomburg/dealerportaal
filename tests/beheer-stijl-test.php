<?php

/**
 * De beheerkant in Homburg-stijl (2026-11-12).
 *
 * @group hdp-beheer-stijl
 */
class Beheer_Stijl_Test extends WP_UnitTestCase {

	public function test_stijl_staat_klaar() {
		$this->assertFileExists( HDP_PLUGIN_DIR . 'assets/css/beheer.css' );
		$this->assertNotFalse( has_action( 'admin_enqueue_scripts', array( 'HDP_Beheer_Stijl', 'laad' ) ) );
	}

	public function test_bovenbalk_krijgt_merk_en_zonder_wordpresslogo() {
		require_once ABSPATH . WPINC . '/class-wp-admin-bar.php';
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$balk = new WP_Admin_Bar();
		$balk->add_node( array( 'id' => 'wp-logo', 'title' => 'WordPress' ) );
		HDP_Beheer_Stijl::merk( $balk );
		HDP_Beheer_Stijl::opruimen( $balk );

		$this->assertNotNull( $balk->get_node( 'hdp-merk' ) );
		$this->assertStringContainsString( 'HOMBURG', $balk->get_node( 'hdp-merk' )->title );
		$this->assertNull( $balk->get_node( 'wp-logo' ) );
	}

	public function test_welkomstblok_met_de_drie_knoppen() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator', 'first_name' => 'Erik' ) ) );
		ob_start();
		HDP_Beheer_Stijl::welkom();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'Welkom terug, Erik', $html );
		$this->assertStringContainsString( 'post_type=hdp_download', $html );
		$this->assertStringContainsString( 'post_type=hdp_bericht', $html );
		$this->assertStringContainsString( 'page=hdp-gebruik', $html );
	}
}
