<?php

/**
 * Header en footer: vertaald, het adminportaal alleen voor beheerders, en
 * het jaartal zonder JavaScript.
 *
 * @group hdp-header-footer
 */
class Header_Footer_Test extends WP_UnitTestCase {

	public function setUp(): void {
		parent::setUp();
		HDP_Roles::activate();
	}

	public function tearDown(): void {
		unset( $_COOKIE[ HDP_I18N::COOKIE ] );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	private function dealer() {
		$id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $id, 'hdp_goedgekeurd', '1' );
		wp_set_current_user( $id );
	}

	private function header() {
		return do_blocks( '<!-- wp:homburg/site-header /-->' );
	}

	private function footer() {
		return do_blocks( '<!-- wp:homburg/site-footer /-->' );
	}

	/** Een Franstalige dealer kreeg "Links" en "Mijn account" in het Nederlands. */
	public function test_header_is_vertaald_voor_een_franstalige_dealer() {
		$this->dealer();
		$_COOKIE[ HDP_I18N::COOKIE ] = 'fr';

		$html = $this->header();

		$this->assertStringContainsString( 'Mon compte', $html );
		$this->assertStringContainsString( 'Liens', $html );
		$this->assertStringNotContainsString( 'Mijn account', $html );
	}

	public function test_header_in_het_nederlands_blijft_hetzelfde() {
		$this->dealer();

		$html = $this->header();

		$this->assertStringContainsString( 'Mijn account', $html );
		$this->assertStringContainsString( '>Links<', $html );
	}

	/** Het adminportaal stond voor iedereen in de footer, ook op het inlogscherm. */
	public function test_adminportaal_niet_voor_bezoekers_en_dealers() {
		$this->assertStringNotContainsString( '/adminportaal/', $this->footer(), 'Uitgelogd' );

		$this->dealer();
		$this->assertStringNotContainsString( '/adminportaal/', $this->footer(), 'Dealer' );
	}

	public function test_adminportaal_wel_voor_een_beheerder() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertStringContainsString( '/adminportaal/', $this->footer() );
	}

	/** Het jaartal stond er alleen als JavaScript het invulde. */
	public function test_jaartal_staat_er_zonder_javascript() {
		$html = $this->footer();

		$this->assertStringContainsString( '&copy; ' . current_time( 'Y' ), $html );
		$this->assertStringNotContainsString( 'hdp-jaartal', $html );
	}
}
