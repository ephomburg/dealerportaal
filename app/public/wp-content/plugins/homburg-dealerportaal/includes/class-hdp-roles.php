<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HDP_Roles {

	const ROLE = 'dealer';
	const CAP  = 'hdp_bekijk_portaal';

	const PAGINAS_VERSIE_OPTION = 'hdp_paginas_versie';

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

		// Zelfherstellend, los van register_activation_hook(): die draait
		// alleen bij het (opnieuw) activeren van de plugin, maar een update
		// die een nieuwe autopagina toevoegt (zoals bestelgeschiedenis) moet
		// ook op een al actieve installatie verschijnen zonder handmatige
		// deactivatie/reactivatie. Bewust op het 'init'-hook (niet hier
		// direct): dit HDP_Roles::init() draait zelf al binnen
		// 'plugins_loaded', en wp_insert_post() heeft via get_permalink()
		// $wp_rewrite nodig, dat pas ná 'plugins_loaded' wordt opgebouwd —
		// direct aanroepen crasht dus met "get_page_permastruct() on null".
		add_action( 'init', array( __CLASS__, 'controleer_paginas' ) );
	}

	public static function controleer_paginas() {
		// get_option() hier is verwaarloosbaar duur: hooguit één rij
		// verschil zodra HDP_VERSION wijzigt.
		if ( get_option( self::PAGINAS_VERSIE_OPTION ) !== HDP_VERSION ) {
			self::maak_ontbrekende_paginas();
			update_option( self::PAGINAS_VERSIE_OPTION, HDP_VERSION );
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

		self::maak_ontbrekende_paginas();
		update_option( self::PAGINAS_VERSIE_OPTION, HDP_VERSION );
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	private static function maak_ontbrekende_paginas() {
		self::maak_pagina_indien_nodig( 'dealerportaal', 'Dealerportaal', '<!-- wp:homburg/dealerportaal /-->' );
		self::maak_pagina_indien_nodig( 'downloads', 'Downloads', '<!-- wp:homburg/downloads-pagina /-->' );
		self::maak_pagina_indien_nodig( 'content', 'Content', '<!-- wp:homburg/content-pagina /-->' );
		self::maak_pagina_indien_nodig( 'bestelgeschiedenis', 'Bestelgeschiedenis', '<!-- wp:homburg/bestelgeschiedenis-pagina /-->' );
		self::maak_pagina_indien_nodig( 'adminportaal', 'Adminportaal', '<!-- wp:homburg/admin-upload /-->' );
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

	/**
	 * Stuurt een tweetalige bevestigingsmail zodra een dealeraccount voor
	 * het eerst wordt goedgekeurd, zodat de dealer niet meer zelf hoeft te
	 * controleren of het account al actief is. Bewust tweetalig in één
	 * mail i.p.v. via HDP_I18N::t() (die de taal van de huidige bezoeker
	 * gebruikt): hier is de "bezoeker" de beheerder die goedkeurt, niet de
	 * dealer die de mail ontvangt — er is nergens een opgeslagen
	 * taalvoorkeur per dealeraccount om op te varen.
	 *
	 * Aangeroepen vanuit zowel het front-end gebruikersoverzicht
	 * (HDP_Admin_Upload) als het wp-admin-gebruikersprofiel
	 * (HDP_User_Fields) — de twee plekken waar goedkeuring wordt gezet —
	 * en alleen bij de overgang van niet-goedgekeurd naar goedgekeurd, niet
	 * bij elke opslag van een al goedgekeurde dealer.
	 */
	public static function stuur_goedkeuringsmail( $gebruiker ) {
		$login_url = home_url( '/dealerportaal/' );
		$onderwerp = 'Uw dealeraccount is goedgekeurd / Votre compte revendeur est approuvé — Dealerportaal Homburg';
		$body      = sprintf(
			"Beste %1\$s,\n\nUw dealeraccount voor het Homburg-dealerportaal is goedgekeurd. U kunt nu inloggen:\n%2\$s\n\nMet vriendelijke groet,\nHomburg Machinehandel\n\n---\n\nCher/Chère %1\$s,\n\nVotre compte revendeur pour le portail concessionnaire Homburg a été approuvé. Vous pouvez maintenant vous connecter :\n%2\$s\n\nCordialement,\nHomburg Machinehandel",
			$gebruiker->display_name,
			$login_url
		);

		wp_mail( $gebruiker->user_email, $onderwerp, $body );
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
