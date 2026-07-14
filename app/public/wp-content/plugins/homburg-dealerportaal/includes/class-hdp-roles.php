<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HDP_Roles {

	const ROLE = 'dealer';
	const CAP  = 'hdp_bekijk_portaal';

	public static function init() {
		// Zorg dat de capability ook bestaat als de rol al bestond vóór een pluginupdate.
		$role = get_role( self::ROLE );
		if ( $role && ! $role->has_cap( self::CAP ) ) {
			$role->add_cap( self::CAP );
		}

		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( self::CAP ) ) {
			$admin->add_cap( self::CAP );
		}
	}

	public static function activate() {
		add_role(
			self::ROLE,
			'Dealer',
			array(
				'read'    => true,
				self::CAP => true,
			)
		);

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( self::CAP );
		}

		HDP_Downloads_CPT::register_post_type();
		flush_rewrite_rules();

		self::maak_pagina_indien_nodig( 'dealerportaal', 'Dealerportaal', '<!-- wp:homburg/dealerportaal /-->' );
		self::maak_pagina_indien_nodig( 'downloads', 'Downloads', '<!-- wp:homburg/downloads-pagina /-->' );
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Beheerders (die het portaal beheren) hoeven niet eerst zichzelf
	 * als dealer goed te keuren; die goedkeuringseis geldt alleen voor
	 * echte dealeraccounts.
	 */
	public static function mag_portaal_zien( $user_id ) {
		if ( ! user_can( $user_id, self::CAP ) ) {
			return false;
		}

		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		return (bool) get_user_meta( $user_id, 'hdp_goedgekeurd', true );
	}

	private static function maak_pagina_indien_nodig( $slug, $titel, $inhoud ) {
		if ( get_page_by_path( $slug ) ) {
			return;
		}

		wp_insert_post(
			array(
				'post_title'   => $titel,
				'post_name'    => $slug,
				'post_content' => $inhoud,
				'post_status'  => 'publish',
				'post_type'    => 'page',
			)
		);
	}
}
