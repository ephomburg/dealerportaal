<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collega's: een dealer (het hoofdaccount, bijvoorbeeld info@) geeft
 * collega's een eigen inlog onder Mijn account > Collega's.
 *
 * Een collega werkt voor het bedrijf van het hoofdaccount: hij krijgt
 * dezelfde merken, hetzelfde PowerAll-klantnummer (dus dezelfde prijzen),
 * ziet de bestellingen en garantieclaims van het hele bedrijf, en een claim
 * die hij indient staat op naam van het bedrijf. Per collega kiest het
 * hoofdaccount of hij mag bestellen en of hij garantie mag doen.
 *
 * Hoe: een collega heeft META_HOOFD (het ID van het hoofdaccount). De
 * gedeelde gegevens (merken, klantnummer) leest hij via een filter
 * rechtstreeks van het hoofdaccount, zodat ze nooit uit de pas lopen.
 * Verwijderen sluit hem buiten maar laat zijn bestellingen bij het bedrijf.
 */
class HDP_Collegas {

	const META_HOOFD   = 'hdp_hoofdaccount';
	const META_RECHTEN = 'hdp_collega_rechten';
	const META_WEG     = 'hdp_collega_weg';
	const ENDPOINT     = 'collegas';
	const NONCE        = 'hdp_collegas';
	const MAX          = 5;
	const RECHTEN      = array( 'bestellen', 'garantie' );

	/** Adresvelden die een nieuwe collega bij het afrekenen van het bedrijf overneemt. */
	const ADRESVELDEN = array( 'company', 'address_1', 'address_2', 'postcode', 'city', 'state', 'country', 'phone' );

	public static function init() {
		add_filter( 'get_user_metadata', array( __CLASS__, 'deel_meta' ), 10, 4 );
		add_filter( 'woocommerce_is_purchasable', array( __CLASS__, 'mag_kopen' ), 20, 2 );
		add_filter( 'user_has_cap', array( __CLASS__, 'bestelling_van_bedrijf' ), 20, 4 );
		foreach ( array( 'billing', 'shipping' ) as $soort ) {
			foreach ( self::ADRESVELDEN as $veld ) {
				if ( 'shipping' === $soort && 'phone' === $veld ) {
					continue;
				}
				add_filter( 'woocommerce_customer_get_' . $soort . '_' . $veld, array( __CLASS__, 'adres_van_bedrijf' ), 10, 2 );
			}
		}

		add_action( 'init', array( __CLASS__, 'registreer_endpoint' ) );
		add_action( 'init', array( __CLASS__, 'flush_eenmalig' ), 20 );
		add_filter( 'woocommerce_get_query_vars', array( __CLASS__, 'query_var' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'menu' ), 22 );
		add_action( 'template_redirect', array( __CLASS__, 'melding_geen_bestellen' ) );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', array( __CLASS__, 'titel' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( __CLASS__, 'render' ) );
		add_action( 'template_redirect', array( __CLASS__, 'verwerk' ) );

		add_action( 'edit_user_profile', array( __CLASS__, 'render_profiel' ), 15 );
		add_action( 'delete_user', array( __CLASS__, 'hoofd_verwijderd' ) );
	}

	/* ---------------------------------------------------------------------
	 * Wie hoort bij wie
	 * ------------------------------------------------------------------- */

	/** Het hoofdaccount bij een collega; anders de gebruiker zelf. */
	public static function hoofd_id( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$hoofd   = $user_id ? (int) get_user_meta( $user_id, self::META_HOOFD, true ) : 0;
		return $hoofd ? $hoofd : $user_id;
	}

	public static function is_collega( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		return $user_id && (int) get_user_meta( $user_id, self::META_HOOFD, true ) > 0;
	}

	public static function is_weg( $user_id ) {
		return (bool) get_user_meta( $user_id, self::META_WEG, true );
	}

	/**
	 * De collega's van een hoofdaccount, op naam.
	 *
	 * @return WP_User[]
	 */
	public static function collegas( $hoofd_id, $ook_weg = false ) {
		if ( ! $hoofd_id ) {
			return array();
		}
		$lijst = get_users(
			array(
				'meta_key'   => self::META_HOOFD, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value' => (string) (int) $hoofd_id, // phpcs:ignore WordPress.DB.SlowDBQuery
				'orderby'    => 'display_name',
			)
		);
		if ( $ook_weg ) {
			return $lijst;
		}
		return array_values(
			array_filter(
				$lijst,
				static function ( $collega ) {
					return ! self::is_weg( $collega->ID );
				}
			)
		);
	}

	/**
	 * Alle accounts van het bedrijf: het hoofdaccount en al zijn collega's,
	 * ook verwijderde (hun bestellingen blijven van het bedrijf).
	 *
	 * @return int[]
	 */
	public static function klant_ids( $user_id = 0 ) {
		$hoofd = self::hoofd_id( $user_id );
		if ( ! $hoofd ) {
			// Niemand ingelogd: een nummer dat nooit bestaat. 0 zou de bestellingen van gasten geven.
			return array( -1 );
		}
		$ids = array( $hoofd );
		foreach ( self::collegas( $hoofd, true ) as $collega ) {
			$ids[] = (int) $collega->ID;
		}
		return $ids;
	}

	/** Mag deze gebruiker dit (bestellen, garantie)? Iedereen behalve een collega mag alles. */
	public static function mag( $user_id, $recht ) {
		$user_id = (int) $user_id;
		if ( ! $user_id || ! self::is_collega( $user_id ) ) {
			return true;
		}
		if ( self::is_weg( $user_id ) ) {
			return false;
		}
		$rechten = self::rechten( $user_id );
		return ! empty( $rechten[ $recht ] );
	}

	/** @return array<string, bool> */
	public static function rechten( $user_id ) {
		$opgeslagen = (array) get_user_meta( $user_id, self::META_RECHTEN, true );
		$rechten    = array();
		foreach ( self::RECHTEN as $recht ) {
			$rechten[ $recht ] = ! empty( $opgeslagen[ $recht ] );
		}
		return $rechten;
	}

	/**
	 * Voor HDP_Roles::mag_portaal_zien(): een collega komt alleen binnen
	 * zolang hij niet is verwijderd en zijn hoofdaccount zelf binnen mag.
	 */
	public static function collega_mag_binnen( $user_id ) {
		if ( ! self::is_collega( $user_id ) ) {
			return true;
		}
		if ( self::is_weg( $user_id ) ) {
			return false;
		}
		$hoofd = self::hoofd_id( $user_id );
		return $hoofd !== (int) $user_id && HDP_Roles::mag_portaal_zien( $hoofd );
	}

	/** Mag deze gebruiker collega's beheren? Alleen een dealer die zelf geen collega is. */
	public static function mag_beheren( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$user    = $user_id ? get_userdata( $user_id ) : null;
		return $user
			&& in_array( HDP_Roles::ROLE, (array) $user->roles, true )
			&& ! self::is_collega( $user_id )
			&& HDP_Roles::mag_portaal_zien( $user_id );
	}

	/* ---------------------------------------------------------------------
	 * Gedeelde gegevens
	 * ------------------------------------------------------------------- */

	/** De sleutels die een collega van zijn hoofdaccount leest. */
	private static function gedeelde_sleutels() {
		return array( 'hdp_merken', HDP_PowerAll_Dealers::META_RELATIE );
	}

	/** get_user_metadata: merken en klantnummer van een collega komen van het hoofdaccount. */
	public static function deel_meta( $waarde, $user_id, $sleutel, $enkel ) {
		if ( null !== $waarde || ! in_array( $sleutel, self::gedeelde_sleutels(), true ) ) {
			return $waarde;
		}
		$hoofd = (int) get_user_meta( $user_id, self::META_HOOFD, true );
		if ( ! $hoofd || $hoofd === (int) $user_id ) {
			return $waarde;
		}
		$van_hoofd = get_user_meta( $hoofd, $sleutel, $enkel );
		return $enkel ? array( $van_hoofd ) : $van_hoofd;
	}

	/** Een leeg adresveld bij een collega: dat van het bedrijf. */
	public static function adres_van_bedrijf( $waarde, $klant ) {
		if ( '' !== (string) $waarde || ! $klant instanceof WC_Customer || ! $klant->get_id() ) {
			return $waarde;
		}
		$hoofd = (int) get_user_meta( $klant->get_id(), self::META_HOOFD, true );
		if ( ! $hoofd ) {
			return $waarde;
		}
		$filter = current_filter();
		$sleutel = substr( $filter, strlen( 'woocommerce_customer_get_' ) );
		return (string) get_user_meta( $hoofd, $sleutel, true );
	}

	/** Een collega zonder het recht "bestellen" kan niets in de winkelwagen leggen. */
	public static function mag_kopen( $mag, $product ) {
		if ( $mag && is_user_logged_in() && ! self::mag( get_current_user_id(), 'bestellen' ) ) {
			return false;
		}
		return $mag;
	}

	/** Een bestelling van iemand uit het eigen bedrijf mag je bekijken. */
	public static function bestelling_van_bedrijf( $allcaps, $caps, $args, $user ) {
		if ( empty( $args[0] ) || 'view_order' !== $args[0] || empty( $args[2] ) || ! empty( $allcaps['view_order'] ) ) {
			return $allcaps;
		}
		if ( ! function_exists( 'wc_get_order' ) || ! HDP_Roles::mag_portaal_zien( (int) $user->ID ) ) {
			return $allcaps;
		}
		$order = wc_get_order( (int) $args[2] );
		if ( $order && $order->get_customer_id() && in_array( (int) $order->get_customer_id(), self::klant_ids( (int) $user->ID ), true ) ) {
			$allcaps['view_order'] = true;
		}
		return $allcaps;
	}

	/* ---------------------------------------------------------------------
	 * Toevoegen, wijzigen, verwijderen
	 * ------------------------------------------------------------------- */

	private static function schone_rechten( $invoer ) {
		$invoer  = (array) $invoer;
		$rechten = array();
		foreach ( self::RECHTEN as $recht ) {
			$rechten[ $recht ] = ! empty( $invoer[ $recht ] );
		}
		return $rechten;
	}

	/**
	 * Voegt een collega toe en stuurt hem de uitnodiging.
	 *
	 * @return WP_User|WP_Error
	 */
	public static function voeg_toe( $hoofd_id, $naam, $email, $rechten ) {
		$hoofd_id = (int) $hoofd_id;
		$naam     = trim( sanitize_text_field( (string) $naam ) );
		$email    = sanitize_email( (string) $email );

		if ( ! self::mag_beheren( $hoofd_id ) ) {
			return new WP_Error( 'geen_toegang', HDP_I18N::t( 'collega_fout_toegang' ) );
		}
		if ( '' === $naam ) {
			return new WP_Error( 'naam', HDP_I18N::t( 'collega_fout_naam' ) );
		}
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'email', HDP_I18N::t( 'collega_fout_email' ) );
		}

		$bestaand = get_user_by( 'email', $email );
		if ( $bestaand ) {
			// Eerder verwijderd bij ditzelfde bedrijf: weer terugzetten.
			if ( (int) get_user_meta( $bestaand->ID, self::META_HOOFD, true ) === $hoofd_id && self::is_weg( $bestaand->ID ) ) {
				delete_user_meta( $bestaand->ID, self::META_WEG );
				update_user_meta( $bestaand->ID, 'hdp_goedgekeurd', '1' );
				update_user_meta( $bestaand->ID, self::META_RECHTEN, self::schone_rechten( $rechten ) );
				wp_update_user(
					array(
						'ID'           => $bestaand->ID,
						'display_name' => $naam,
					)
				);
				$collega = get_userdata( $bestaand->ID );
				self::nodig_uit( $collega );
				return $collega;
			}
			return new WP_Error( 'bestaat', HDP_I18N::t( 'collega_fout_bestaat' ) );
		}

		if ( count( self::collegas( $hoofd_id ) ) >= self::MAX ) {
			return new WP_Error( 'max', sprintf( HDP_I18N::t( 'collega_fout_max' ), self::MAX ) );
		}

		$delen = preg_split( '/\s+/', $naam, 2 );
		$id    = wp_insert_user(
			array(
				'user_login'   => self::vrije_login( $email ),
				'user_pass'    => wp_generate_password( 32, true, true ),
				'user_email'   => $email,
				'display_name' => $naam,
				'nickname'     => $naam,
				'first_name'   => $delen[0],
				'last_name'    => isset( $delen[1] ) ? $delen[1] : '',
				'role'         => HDP_Roles::ROLE,
			)
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		update_user_meta( $id, self::META_HOOFD, $hoofd_id );
		update_user_meta( $id, self::META_RECHTEN, self::schone_rechten( $rechten ) );
		update_user_meta( $id, 'hdp_goedgekeurd', '1' );
		if ( class_exists( 'HDP_Gebruik' ) && HDP_Gebruik::is_testaccount( $hoofd_id ) ) {
			update_user_meta( $id, HDP_Gebruik::TEST_META, '1' );
		}

		$collega = get_userdata( $id );
		self::nodig_uit( $collega );
		return $collega;
	}

	private static function vrije_login( $email ) {
		$basis = sanitize_user( 'collega-' . strstr( $email, '@', true ), true );
		$basis = '' !== $basis ? substr( $basis, 0, 50 ) : 'collega';
		$login = $basis;
		$i     = 2;
		while ( username_exists( $login ) ) {
			$login = $basis . '-' . $i;
			++$i;
		}
		return $login;
	}

	/** De collega van dit hoofdaccount, of null als hij daar niet bij hoort. */
	private static function eigen_collega( $hoofd_id, $collega_id ) {
		$collega = get_userdata( (int) $collega_id );
		if ( ! $collega || (int) get_user_meta( $collega->ID, self::META_HOOFD, true ) !== (int) $hoofd_id ) {
			return null;
		}
		return $collega;
	}

	public static function zet_rechten( $hoofd_id, $collega_id, $rechten ) {
		$collega = self::eigen_collega( $hoofd_id, $collega_id );
		if ( ! $collega || self::is_weg( $collega->ID ) ) {
			return false;
		}
		update_user_meta( $collega->ID, self::META_RECHTEN, self::schone_rechten( $rechten ) );
		return true;
	}

	/** Buitensluiten: niet meer inloggen, meteen uitgelogd. Bestellingen blijven. */
	public static function verwijder( $hoofd_id, $collega_id ) {
		$collega = self::eigen_collega( $hoofd_id, $collega_id );
		if ( ! $collega ) {
			return false;
		}
		update_user_meta( $collega->ID, self::META_WEG, time() );
		update_user_meta( $collega->ID, 'hdp_goedgekeurd', '' );
		delete_user_meta( $collega->ID, HDP_PowerAll_Dealers::META_UITNODIGING );
		WP_Session_Tokens::get_instance( $collega->ID )->destroy_all();
		return true;
	}

	public static function nodig_opnieuw_uit( $hoofd_id, $collega_id ) {
		$collega = self::eigen_collega( $hoofd_id, $collega_id );
		if ( ! $collega || self::is_weg( $collega->ID ) ) {
			return false;
		}
		return self::nodig_uit( $collega );
	}

	/** De mail aan de collega, met de link om een wachtwoord te kiezen. */
	public static function nodig_uit( WP_User $collega ) {
		$hoofd   = get_userdata( self::hoofd_id( $collega->ID ) );
		$bedrijf = $hoofd ? self::bedrijfsnaam( $hoofd->ID ) : '';
		$link    = HDP_PowerAll_Dealers::uitnodigingslink( $collega );
		$portaal = home_url( '/dealerportaal/' );
		$dagen   = HDP_PowerAll_Dealers::GELDIG_DAGEN;

		$onderwerp = sprintf( '%1$s: je account voor het Homburg-dealerportaal / votre compte pour le portail concessionnaire Homburg', $bedrijf );
		$tekst     = sprintf(
			"Beste %1\$s,\n\n%2\$s heeft je toegevoegd aan het dealerportaal van Homburg. Je werkt daar voor %2\$s: je ziet de bestellingen en garantieclaims van het bedrijf, de documentatie en de trainingen.\n\nKies via deze link je wachtwoord:\n%3\$s\n\nDe link werkt %4\$d dagen en één keer. Daarna log je in op %5\$s met je e-mailadres (%6\$s) en je wachtwoord.\n\nMet vriendelijke groet,\nHomburg Machinehandel\n\n---\n\nCher/Chère %1\$s,\n\n%2\$s vous a ajouté(e) au portail concessionnaire Homburg. Vous y travaillez pour %2\$s : vous voyez les commandes et les demandes de garantie de l'entreprise, la documentation et les formations.\n\nChoisissez votre mot de passe via ce lien :\n%3\$s\n\nLe lien est valable %4\$d jours et une seule fois. Ensuite, connectez-vous sur %5\$s avec votre adresse e-mail (%6\$s) et votre mot de passe.\n\nCordialement,\nHomburg Machinehandel",
			$collega->display_name,
			$bedrijf,
			$link,
			$dagen,
			$portaal,
			$collega->user_email
		);

		return (bool) wp_mail( $collega->user_email, $onderwerp, $tekst );
	}

	/** De bedrijfsnaam van het hoofdaccount, anders zijn naam. */
	public static function bedrijfsnaam( $hoofd_id ) {
		$bedrijf = trim( (string) get_user_meta( $hoofd_id, 'billing_company', true ) );
		if ( '' !== $bedrijf ) {
			return $bedrijf;
		}
		$hoofd = get_userdata( $hoofd_id );
		return $hoofd ? ( $hoofd->display_name ? $hoofd->display_name : $hoofd->user_login ) : '';
	}

	/** Het hoofdaccount wordt verwijderd: zijn collega's kunnen dan ook niet meer in. */
	public static function hoofd_verwijderd( $user_id ) {
		foreach ( self::collegas( $user_id ) as $collega ) {
			self::verwijder( $user_id, $collega->ID );
		}
	}

	/* ---------------------------------------------------------------------
	 * Mijn account > Collega's
	 * ------------------------------------------------------------------- */

	public static function registreer_endpoint() {
		add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES );
	}

	public static function flush_eenmalig() {
		if ( ! get_option( 'hdp_collegas_flushed' ) ) {
			flush_rewrite_rules();
			update_option( 'hdp_collegas_flushed', 1 );
		}
	}

	public static function query_var( $vars ) {
		$vars[ self::ENDPOINT ] = self::ENDPOINT;
		return $vars;
	}

	public static function titel() {
		return HDP_I18N::t( 'collega_menu' );
	}

	/**
	 * Een collega zonder het recht "bestellen" ziet in de webshop en de
	 * winkelwagen waarom er geen bestelknop staat.
	 */
	public static function melding_geen_bestellen() {
		if ( ! is_user_logged_in() || self::mag( get_current_user_id(), 'bestellen' ) || ! function_exists( 'is_woocommerce' ) ) {
			return;
		}
		if ( ! ( is_woocommerce() || is_cart() || is_checkout() ) ) {
			return;
		}
		$tekst = sprintf( HDP_I18N::t( 'collega_geen_bestellen' ), self::bedrijfsnaam( self::hoofd_id() ) );
		if ( ! wc_has_notice( $tekst, 'notice' ) ) {
			wc_add_notice( $tekst, 'notice' );
		}
	}

	/** Vlak na Mijn trainingen, alleen voor het hoofdaccount. Een collega die niet mag bestellen, ziet "Snel bestellen" niet. */
	public static function menu( $items ) {
		if ( is_user_logged_in() && ! self::mag( get_current_user_id(), 'bestellen' ) ) {
			unset( $items['snelbestellen'] );
		}
		if ( ! self::mag_beheren() ) {
			return $items;
		}
		$nieuw = array();
		$gezet = false;
		foreach ( $items as $sleutel => $label ) {
			$nieuw[ $sleutel ] = $label;
			if ( 'mijn-trainingen' === $sleutel ) {
				$nieuw[ self::ENDPOINT ] = HDP_I18N::t( 'collega_menu' );
				$gezet                   = true;
			}
		}
		if ( ! $gezet ) {
			$nieuw = array_slice( $nieuw, 0, -1, true ) + array( self::ENDPOINT => HDP_I18N::t( 'collega_menu' ) ) + array_slice( $nieuw, -1, 1, true );
		}
		return $nieuw;
	}

	public static function url( $melding = '' ) {
		$url = wc_get_account_endpoint_url( self::ENDPOINT );
		return $melding ? add_query_arg( 'hdp_cm', $melding, $url ) : $url;
	}

	public static function verwerk() {
		if ( ! isset( $_POST['hdp_collega_actie'], $_POST['hdp_collega_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hdp_collega_nonce'] ) ), self::NONCE ) || ! self::mag_beheren() ) {
			return;
		}
		$hoofd   = get_current_user_id();
		$actie   = sanitize_key( wp_unslash( $_POST['hdp_collega_actie'] ) );
		$collega = isset( $_POST['hdp_collega'] ) ? absint( $_POST['hdp_collega'] ) : 0;
		$rechten = isset( $_POST['hdp_rechten'] ) ? array_map( 'sanitize_key', array_keys( (array) wp_unslash( $_POST['hdp_rechten'] ) ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- alleen de sleutels, opgeschoond.
		$rechten = array_fill_keys( $rechten, true );

		switch ( $actie ) {
			case 'toevoegen':
				$uitkomst = self::voeg_toe(
					$hoofd,
					isset( $_POST['hdp_naam'] ) ? sanitize_text_field( wp_unslash( $_POST['hdp_naam'] ) ) : '',
					isset( $_POST['hdp_email'] ) ? sanitize_email( wp_unslash( $_POST['hdp_email'] ) ) : '',
					$rechten
				);
				$melding  = is_wp_error( $uitkomst ) ? 'fout_' . $uitkomst->get_error_code() : 'toegevoegd';
				break;
			case 'rechten':
				$melding = self::zet_rechten( $hoofd, $collega, $rechten ) ? 'opgeslagen' : 'fout_toegang';
				break;
			case 'uitnodigen':
				$melding = self::nodig_opnieuw_uit( $hoofd, $collega ) ? 'uitgenodigd' : 'fout_mail';
				break;
			case 'verwijderen':
				$melding = self::verwijder( $hoofd, $collega ) ? 'verwijderd' : 'fout_toegang';
				break;
			default:
				return;
		}

		wp_safe_redirect( self::url( $melding ) );
		exit;
	}

	private static function melding( $code ) {
		$fouten = array(
			'fout_naam'    => 'collega_fout_naam',
			'fout_email'   => 'collega_fout_email',
			'fout_bestaat' => 'collega_fout_bestaat',
			'fout_toegang' => 'collega_fout_toegang',
			'fout_mail'    => 'collega_fout_mail',
		);
		$goed   = array(
			'toegevoegd'  => 'collega_ok_toegevoegd',
			'opgeslagen'  => 'collega_ok_opgeslagen',
			'uitgenodigd' => 'collega_ok_uitgenodigd',
			'verwijderd'  => 'collega_ok_verwijderd',
		);
		if ( 'fout_max' === $code ) {
			return array( 'fout', sprintf( HDP_I18N::t( 'collega_fout_max' ), self::MAX ) );
		}
		if ( isset( $goed[ $code ] ) ) {
			return array( 'ok', HDP_I18N::t( $goed[ $code ] ) );
		}
		if ( isset( $fouten[ $code ] ) ) {
			return array( 'fout', HDP_I18N::t( $fouten[ $code ] ) );
		}
		if ( 0 === strpos( $code, 'fout_' ) ) {
			return array( 'fout', HDP_I18N::t( 'collega_fout_algemeen' ) );
		}
		return null;
	}

	private static function status( $collega_id ) {
		$status = HDP_PowerAll_Dealers::accountstatus( $collega_id );
		if ( 'gekozen' === $status['code'] ) {
			return array( 'actief', HDP_I18N::t( 'collega_status_actief' ) );
		}
		if ( 'verlopen' === $status['code'] ) {
			return array( 'verlopen', HDP_I18N::t( 'collega_status_verlopen' ) );
		}
		return array( 'uitgenodigd', HDP_I18N::t( 'collega_status_uitgenodigd' ) );
	}

	private static function rechten_vakjes( $rechten, $id_basis ) {
		foreach ( self::RECHTEN as $recht ) {
			$id = $id_basis . '-' . $recht;
			?>
			<label class="hdp-collega-recht" for="<?php echo esc_attr( $id ); ?>">
				<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="hdp_rechten[<?php echo esc_attr( $recht ); ?>]" value="1" <?php checked( ! empty( $rechten[ $recht ] ) ); ?>>
				<?php echo esc_html( HDP_I18N::t( 'collega_recht_' . $recht ) ); ?>
			</label>
			<?php
		}
	}

	public static function render() {
		if ( ! self::mag_beheren() ) {
			echo '<p>' . esc_html( HDP_I18N::t( 'collega_fout_toegang' ) ) . '</p>';
			return;
		}
		$hoofd    = get_current_user_id();
		$collegas = self::collegas( $hoofd );
		$melding  = isset( $_GET['hdp_cm'] ) ? self::melding( sanitize_key( wp_unslash( $_GET['hdp_cm'] ) ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- alleen een melding tonen.
		?>
		<div class="hdp-collegas">
			<p class="hdp-collegas-intro"><?php echo esc_html( sprintf( HDP_I18N::t( 'collega_intro' ), self::bedrijfsnaam( $hoofd ) ) ); ?></p>

			<?php if ( $melding ) : ?>
				<div class="hdp-collegas-melding hdp-collegas-melding-<?php echo esc_attr( $melding[0] ); ?>" role="status"><?php echo esc_html( $melding[1] ); ?></div>
			<?php endif; ?>

			<?php if ( $collegas ) : ?>
				<ul class="hdp-collegas-lijst">
					<?php foreach ( $collegas as $collega ) : ?>
						<?php $status = self::status( $collega->ID ); ?>
						<li class="hdp-collega">
							<div class="hdp-collega-wie">
								<strong><?php echo esc_html( $collega->display_name ); ?></strong>
								<span><?php echo esc_html( $collega->user_email ); ?></span>
								<span class="hdp-collega-status hdp-collega-status-<?php echo esc_attr( $status[0] ); ?>"><?php echo esc_html( $status[1] ); ?></span>
							</div>
							<form method="post" class="hdp-collega-rechten">
								<?php wp_nonce_field( self::NONCE, 'hdp_collega_nonce' ); ?>
								<input type="hidden" name="hdp_collega" value="<?php echo esc_attr( $collega->ID ); ?>">
								<?php self::rechten_vakjes( self::rechten( $collega->ID ), 'hdp-recht-' . $collega->ID ); ?>
								<button type="submit" name="hdp_collega_actie" value="rechten" class="button hdp-collega-knop"><?php echo esc_html( HDP_I18N::t( 'collega_opslaan' ) ); ?></button>
							</form>
							<div class="hdp-collega-acties">
								<?php if ( 'actief' !== $status[0] ) : ?>
									<form method="post">
										<?php wp_nonce_field( self::NONCE, 'hdp_collega_nonce' ); ?>
										<input type="hidden" name="hdp_collega" value="<?php echo esc_attr( $collega->ID ); ?>">
										<button type="submit" name="hdp_collega_actie" value="uitnodigen" class="hdp-collega-link"><?php echo esc_html( HDP_I18N::t( 'collega_opnieuw' ) ); ?></button>
									</form>
								<?php endif; ?>
								<form method="post" onsubmit="return confirm(<?php echo esc_attr( wp_json_encode( sprintf( HDP_I18N::t( 'collega_verwijderen_zeker' ), $collega->display_name ) ) ); ?>);">
									<?php wp_nonce_field( self::NONCE, 'hdp_collega_nonce' ); ?>
									<input type="hidden" name="hdp_collega" value="<?php echo esc_attr( $collega->ID ); ?>">
									<button type="submit" name="hdp_collega_actie" value="verwijderen" class="hdp-collega-link hdp-collega-weg"><?php echo esc_html( HDP_I18N::t( 'collega_verwijderen' ) ); ?></button>
								</form>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="hdp-collegas-leeg"><?php echo esc_html( HDP_I18N::t( 'collega_leeg' ) ); ?></p>
			<?php endif; ?>

			<form method="post" class="hdp-collega-nieuw">
				<h2><?php echo esc_html( HDP_I18N::t( 'collega_nieuw_kop' ) ); ?></h2>
				<?php wp_nonce_field( self::NONCE, 'hdp_collega_nonce' ); ?>
				<p class="form-row">
					<label for="hdp-collega-naam"><?php echo esc_html( HDP_I18N::t( 'collega_naam' ) ); ?></label>
					<input type="text" class="input-text" id="hdp-collega-naam" name="hdp_naam" autocomplete="off" required>
				</p>
				<p class="form-row">
					<label for="hdp-collega-email"><?php echo esc_html( HDP_I18N::t( 'collega_email' ) ); ?></label>
					<input type="email" class="input-text" id="hdp-collega-email" name="hdp_email" autocomplete="off" required>
				</p>
				<fieldset class="hdp-collega-nieuw-rechten">
					<legend><?php echo esc_html( HDP_I18N::t( 'collega_mag' ) ); ?></legend>
					<?php self::rechten_vakjes( array_fill_keys( self::RECHTEN, true ), 'hdp-recht-nieuw' ); ?>
				</fieldset>
				<p class="hdp-collega-uitleg"><?php echo esc_html( HDP_I18N::t( 'collega_nieuw_uitleg' ) ); ?></p>
				<button type="submit" name="hdp_collega_actie" value="toevoegen" class="button"><?php echo esc_html( HDP_I18N::t( 'collega_toevoegen' ) ); ?></button>
			</form>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * wp-admin: in het profiel zien bij wie iemand hoort
	 * ------------------------------------------------------------------- */

	public static function render_profiel( $user ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$collegas = self::collegas( $user->ID, true );
		$is_coll  = self::is_collega( $user->ID );
		if ( ! $collegas && ! $is_coll ) {
			return;
		}
		?>
		<h2 id="hdp-collegas">Collega's</h2>
		<table class="form-table" role="presentation">
			<?php if ( $is_coll ) : ?>
				<?php $hoofd = get_userdata( self::hoofd_id( $user->ID ) ); ?>
				<tr>
					<th>Collega van</th>
					<td>
						<?php if ( $hoofd ) : ?>
							<a href="<?php echo esc_url( get_edit_user_link( $hoofd->ID ) ); ?>"><?php echo esc_html( self::bedrijfsnaam( $hoofd->ID ) . ' (' . $hoofd->user_email . ')' ); ?></a>
						<?php endif; ?>
						<p class="description">
							<?php
							$rechten = self::rechten( $user->ID );
							$mag     = array();
							foreach ( $rechten as $recht => $aan ) {
								if ( $aan ) {
									$mag[] = 'bestellen' === $recht ? 'bestellen' : 'garantie';
								}
							}
							echo esc_html( self::is_weg( $user->ID ) ? 'Verwijderd door het hoofdaccount; kan niet meer inloggen.' : 'Mag: ' . ( $mag ? implode( ', ', $mag ) : 'alleen kijken' ) . '. Merken, klantnummer en prijzen komen van het hoofdaccount.' );
							?>
						</p>
					</td>
				</tr>
			<?php else : ?>
				<tr>
					<th>Collega's van dit account</th>
					<td>
						<?php foreach ( $collegas as $collega ) : ?>
							<a href="<?php echo esc_url( get_edit_user_link( $collega->ID ) ); ?>"><?php echo esc_html( $collega->display_name . ' (' . $collega->user_email . ')' ); ?></a><?php echo self::is_weg( $collega->ID ) ? ' — verwijderd' : ''; ?><br>
						<?php endforeach; ?>
					</td>
				</tr>
			<?php endif; ?>
		</table>
		<?php
	}
}
