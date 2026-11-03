<?php
/**
 * Trainingen: Homburg zet een training klaar, dealers melden zich aan.
 *
 * Een training is een eigen berichtsoort in wp-admin. Daar stelt Homburg per
 * training ook in welke gegevens het nodig heeft (zie velden()); naam en
 * e-mail van de deelnemer worden altijd gevraagd.
 *
 * De aanmeldingen staan in een eigen tabel, los van de training. Ze bewaren
 * een kopie van de vraag én het antwoord: een veld dat later wordt hernoemd
 * of verwijderd, verandert niets aan wat iemand destijds heeft ingevuld. De
 * interne Homburg App kan ze later overnemen; zie de uitleg die daarvoor is
 * meegegeven. Tot die tijd staat het overzicht onder Trainingen > Aanmeldingen.
 *
 * Eén aanmelding is één deelnemer. Meerdere mensen van een dealer melden zich
 * dus apart aan, en bezetten elk een plaats.
 *
 * @package Homburg_Dealerportaal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * De trainingen, de aanmeldingen en het beheer ervan.
 */
class HDP_Trainingen {

	const POST_TYPE = 'hdp_training';
	const TABEL     = 'hdp_training_aanmeldingen';
	const DB_OPTIE  = 'hdp_trainingen_db_versie';
	const DB_VERSIE = '1';

	const META_DATUM     = '_hdp_training_datum';
	const META_EINDDATUM = '_hdp_training_einddatum';
	const META_TIJD      = '_hdp_training_tijd';
	const META_LOCATIE   = '_hdp_training_locatie';
	const META_SLUIT     = '_hdp_training_sluit';
	const META_PLAATSEN  = '_hdp_training_plaatsen';
	const META_KOSTEN    = '_hdp_training_kosten';
	const META_MERKEN    = '_hdp_training_merken';
	const META_TITEL_FR  = '_hdp_training_titel_fr';
	const META_TEKST_FR  = '_hdp_training_tekst_fr';
	const META_VELDEN    = '_hdp_training_velden';

	const STATUS_AANGEMELD  = 'aangemeld';
	const STATUS_GEANNULEERD = 'geannuleerd';

	/** De soorten velden die Homburg per training kan vragen. */
	const VELDSOORTEN = array(
		'tekst'    => 'Korte tekst',
		'lang'     => 'Lange tekst',
		'telefoon' => 'Telefoonnummer',
		'email'    => 'E-mailadres',
		'getal'    => 'Getal',
		'datum'    => 'Datum',
		'keuze'    => 'Keuzelijst',
		'vinkje'   => 'Vinkje (ja/nee)',
	);

	/** Wie de aanmeldingen mag inzien: de medewerkers van Homburg, geen dealers. */
	const CAP_BEHEER = 'edit_others_posts';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		// Zelfherstellend, zoals bij HDP_Gebruik: een deploy activeert de plugin niet opnieuw.
		add_action( 'init', array( __CLASS__, 'controleer_tabel' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'opslaan' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'kolommen' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'kolom_inhoud' ), 10, 2 );
		add_action( 'admin_menu', array( __CLASS__, 'registreer_menu' ) );
		add_action( 'admin_post_hdp_training_csv', array( __CLASS__, 'exporteer_csv' ) );
		add_action( 'admin_post_hdp_training_annuleer', array( __CLASS__, 'verwerk_annuleren' ) );
	}

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'           => 'Trainingen',
				'labels'          => array(
					'name'          => 'Trainingen',
					'singular_name' => 'Training',
					'add_new'       => 'Nieuwe training',
					'add_new_item'  => 'Nieuwe training plaatsen',
					'edit_item'     => 'Training bewerken',
					'search_items'  => 'Trainingen zoeken',
					'not_found'     => 'Nog geen trainingen geplaatst.',
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-welcome-learn-more',
				'menu_position'   => 27,
				'supports'        => array( 'title', 'editor', 'thumbnail' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}

	public static function tabel() {
		global $wpdb;
		return $wpdb->prefix . self::TABEL;
	}

	public static function controleer_tabel() {
		if ( get_option( self::DB_OPTIE ) === self::DB_VERSIE ) {
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$tabel   = self::tabel();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$tabel} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				training_id bigint(20) unsigned NOT NULL,
				training_titel varchar(255) NOT NULL DEFAULT '',
				user_id bigint(20) unsigned NOT NULL,
				dealer_naam varchar(255) NOT NULL DEFAULT '',
				dealer_bedrijf varchar(255) NOT NULL DEFAULT '',
				dealer_email varchar(191) NOT NULL DEFAULT '',
				deelnemer_naam varchar(255) NOT NULL,
				deelnemer_email varchar(191) NOT NULL,
				antwoorden longtext NOT NULL,
				taal varchar(5) NOT NULL DEFAULT 'nl',
				status varchar(20) NOT NULL DEFAULT 'aangemeld',
				aangemaakt datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY training_status (training_id,status),
				KEY user_training (user_id,training_id)
			) {$charset};"
		);

		update_option( self::DB_OPTIE, self::DB_VERSIE, false );
	}

	/* === De training ==================================================== */

	public static function titel( $training_id ) {
		return HDP_I18N::kies( get_the_title( $training_id ), get_post_meta( $training_id, self::META_TITEL_FR, true ) );
	}

	/** De omschrijving in de taal van de bezoeker, klaar om te tonen. */
	public static function tekst( $training_id ) {
		$tekst = HDP_I18N::kies( get_post_field( 'post_content', $training_id ), get_post_meta( $training_id, self::META_TEKST_FR, true ) );
		return wpautop( wp_kses_post( $tekst ) );
	}

	/**
	 * De velden die Homburg voor deze training heeft ingesteld.
	 *
	 * @return array<int, array{id: string, label: string, label_fr: string, soort: string, verplicht: bool, opties: array}>
	 */
	public static function velden( $training_id ) {
		$velden = json_decode( (string) get_post_meta( $training_id, self::META_VELDEN, true ), true );
		return is_array( $velden ) ? $velden : array();
	}

	/**
	 * De keuzes van een keuzelijst. Per regel "Nederlands | Français"; zonder
	 * streep is er één tekst voor beide talen.
	 *
	 * @return array<string, string> Nederlandse tekst (wat wordt bewaard) => wat de bezoeker ziet.
	 */
	public static function opties( $veld ) {
		$opties = array();
		foreach ( (array) $veld['opties'] as $regel ) {
			$delen                     = array_map( 'trim', explode( '|', (string) $regel, 2 ) );
			$opties[ $delen[0] ] = HDP_I18N::kies( $delen[0], isset( $delen[1] ) ? $delen[1] : '' );
		}
		return $opties;
	}

	/**
	 * Mag deze gebruiker de training zien? Zelfde regel als bij berichten: zonder
	 * merk voor iedereen, en een dealer zonder merkenlijst ziet alles.
	 */
	public static function mag_zien( $training_id, $user_id = 0 ) {
		if ( self::POST_TYPE !== get_post_type( $training_id ) || 'publish' !== get_post_status( $training_id ) ) {
			return false;
		}
		$merken = HDP_Merken::naar_array( get_post_meta( $training_id, self::META_MERKEN, true ) );
		if ( ! $merken ) {
			return true;
		}
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$eigen   = HDP_Merken::naar_array( get_user_meta( $user_id, 'hdp_merken', true ) );
		return ! $eigen || array_intersect( $merken, $eigen );
	}

	/** Is de laatste dag van de training voorbij? Zonder datum ("datum volgt") nooit. */
	public static function is_voorbij( $training_id ) {
		$eind = (string) get_post_meta( $training_id, self::META_EINDDATUM, true );
		$eind = $eind ? $eind : (string) get_post_meta( $training_id, self::META_DATUM, true );
		return $eind && $eind < current_time( 'Y-m-d' );
	}

	/** Hoeveel plaatsen er nog zijn; null als er geen maximum is. */
	public static function plaatsen_over( $training_id ) {
		$plaatsen = (int) get_post_meta( $training_id, self::META_PLAATSEN, true );
		if ( $plaatsen <= 0 ) {
			return null;
		}
		return max( 0, $plaatsen - self::aantal_aangemeld( $training_id ) );
	}

	/**
	 * @return string 'open', 'vol', 'gesloten' (na de sluitingsdatum) of 'voorbij'.
	 */
	public static function stand( $training_id ) {
		if ( self::is_voorbij( $training_id ) ) {
			return 'voorbij';
		}
		$sluit = (string) get_post_meta( $training_id, self::META_SLUIT, true );
		if ( $sluit && $sluit < current_time( 'Y-m-d' ) ) {
			return 'gesloten';
		}
		return 0 === self::plaatsen_over( $training_id ) ? 'vol' : 'open';
	}

	/**
	 * De komende trainingen die deze dealer mag zien, eerstvolgende eerst.
	 * Zonder datum achteraan.
	 *
	 * @return int[]
	 */
	public static function komende( $user_id = 0 ) {
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$ids = array_values(
			array_filter(
				$ids,
				static function ( $id ) use ( $user_id ) {
					return self::mag_zien( $id, $user_id ) && ! self::is_voorbij( $id );
				}
			)
		);
		usort(
			$ids,
			static function ( $a, $b ) {
				$da = (string) get_post_meta( $a, self::META_DATUM, true );
				$db = (string) get_post_meta( $b, self::META_DATUM, true );
				return strcmp( $da ? $da : '9999', $db ? $db : '9999' );
			}
		);
		return $ids;
	}

	/**
	 * Nieuwe trainingen voor "Nieuw voor jou" op de startpagina.
	 *
	 * @param int $sinds Tijdstempel (UTC) van het laatste wegklikken; 0 = nooit.
	 * @param int $dagen Hoe lang iets als nieuw geldt.
	 * @return array<int, array{titel: string, sub: string, url: string, tijd: int}>
	 */
	public static function nieuwe( $sinds, $dagen ) {
		$grens = max( (int) $sinds, time() - (int) $dagen * DAY_IN_SECONDS );
		$lijst = array();
		foreach ( self::komende() as $id ) {
			$tijd = (int) get_post_time( 'U', true, $id );
			if ( $tijd <= $grens || 'open' !== self::stand( $id ) ) {
				continue;
			}
			$lijst[] = array(
				'titel' => self::titel( $id ),
				'sub'   => implode( ' · ', array_filter( array( HDP_I18N::t( 'training_label' ), self::datumtekst( $id ) ) ) ),
				'url'   => self::url( $id ),
				'tijd'  => $tijd,
			);
		}
		return $lijst;
	}

	public static function url( $training_id = 0 ) {
		$url = home_url( '/trainingen/' );
		return $training_id ? add_query_arg( 'training', (int) $training_id, $url ) : $url;
	}

	/** "12 maart 2027", of "12 maart 2027 – 13 maart 2027"; leeg als de datum nog niet vaststaat. */
	public static function datumtekst( $training_id ) {
		$datum = (string) get_post_meta( $training_id, self::META_DATUM, true );
		if ( ! $datum ) {
			return '';
		}
		$tekst = HDP_I18N::datum( 'j F Y', strtotime( $datum ) );
		$eind  = (string) get_post_meta( $training_id, self::META_EINDDATUM, true );
		if ( $eind && $eind !== $datum ) {
			$tekst .= ' – ' . HDP_I18N::datum( 'j F Y', strtotime( $eind ) );
		}
		return $tekst;
	}

	/* === Aanmeldingen =================================================== */

	public static function aantal_aangemeld( $training_id ) {
		global $wpdb;
		$tabel = self::tabel();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- eigen tabel.
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabel} WHERE training_id = %d AND status = %s", $training_id, self::STATUS_AANGEMELD ) );
	}

	/**
	 * @return array Rijen, oudste eerst. 'antwoorden' is al uitgepakt.
	 */
	public static function aanmeldingen( $training_id, $alleen_actief = false ) {
		global $wpdb;
		$tabel = self::tabel();
		$sql   = $wpdb->prepare( "SELECT * FROM {$tabel} WHERE training_id = %d", $training_id ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- eigen tabel.
		if ( $alleen_actief ) {
			$sql .= $wpdb->prepare( ' AND status = %s', self::STATUS_AANGEMELD );
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- hierboven voorbereid.
		$rijen = $wpdb->get_results( $sql . ' ORDER BY aangemaakt ASC, id ASC', ARRAY_A );
		return array_map( array( __CLASS__, 'pak_uit' ), $rijen ? $rijen : array() );
	}

	/** De lopende aanmeldingen van één dealer voor deze training. */
	public static function van_dealer( $training_id, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		return array_values(
			array_filter(
				self::aanmeldingen( $training_id, true ),
				static function ( $rij ) use ( $user_id ) {
					return (int) $rij['user_id'] === $user_id;
				}
			)
		);
	}

	private static function pak_uit( $rij ) {
		$antwoorden        = json_decode( (string) $rij['antwoorden'], true );
		$rij['antwoorden'] = is_array( $antwoorden ) ? $antwoorden : array();
		return $rij;
	}

	/**
	 * Controleert de invoer en slaat de aanmelding op.
	 *
	 * @param int   $training_id
	 * @param array $invoer  Ongeschoond, zonder slashes: 'naam', 'email', en 'veld_<id>' per veld.
	 * @param int   $user_id
	 * @return int|array Het nummer van de aanmelding, of de fouten: veldnaam => tekstsleutel.
	 */
	public static function meld_aan( $training_id, $invoer, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( ! HDP_Roles::mag_portaal_zien( $user_id ) || ! self::mag_zien( $training_id, $user_id ) ) {
			return array( 'algemeen' => 'training_fout_geen_toegang' );
		}
		if ( 'open' !== self::stand( $training_id ) ) {
			return array( 'algemeen' => 'training_fout_gesloten' );
		}

		$fouten = array();
		$naam   = sanitize_text_field( isset( $invoer['naam'] ) ? (string) $invoer['naam'] : '' );
		$email  = sanitize_email( isset( $invoer['email'] ) ? (string) $invoer['email'] : '' );
		if ( '' === $naam ) {
			$fouten['naam'] = 'training_fout_verplicht';
		}
		if ( ! is_email( $email ) ) {
			$fouten['email'] = 'training_fout_email';
		} elseif ( self::is_al_aangemeld( $training_id, $email ) ) {
			$fouten['email'] = 'training_fout_dubbel';
		}

		$antwoorden = array();
		foreach ( self::velden( $training_id ) as $veld ) {
			$sleutel = 'veld_' . $veld['id'];
			$waarde  = self::schoon_antwoord( $veld, isset( $invoer[ $sleutel ] ) ? $invoer[ $sleutel ] : '' );
			$fout    = self::controleer_antwoord( $veld, $waarde );
			if ( $fout ) {
				$fouten[ $sleutel ] = $fout;
			}
			// Altijd de Nederlandse vraag: het team leest het in het Nederlands, ook bij een Franstalige dealer.
			$antwoorden[] = array(
				'id'    => $veld['id'],
				'label' => $veld['label'],
				'soort' => $veld['soort'],
				'waarde' => $waarde,
			);
		}

		if ( $fouten ) {
			return $fouten;
		}

		$user = get_userdata( $user_id );
		$rij  = array(
			'training_id'     => (int) $training_id,
			'training_titel'  => get_the_title( $training_id ),
			'user_id'         => $user_id,
			'dealer_naam'     => $user->display_name ? $user->display_name : $user->user_login,
			'dealer_bedrijf'  => (string) get_user_meta( $user_id, 'billing_company', true ),
			'dealer_email'    => $user->user_email,
			'deelnemer_naam'  => $naam,
			'deelnemer_email' => $email,
			'antwoorden'      => wp_json_encode( $antwoorden, JSON_UNESCAPED_UNICODE ),
			'taal'            => HDP_I18N::huidige_taal(),
			'status'          => self::STATUS_AANGEMELD,
			'aangemaakt'      => current_time( 'mysql', true ),
		);

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- eigen tabel.
		if ( ! $wpdb->insert( self::tabel(), $rij ) ) {
			HDP_Log::schrijf( 'Trainingsaanmelding niet opgeslagen: ' . $wpdb->last_error, 'WAARSCHUWING' );
			return array( 'algemeen' => 'training_fout_opslaan' );
		}

		$rij['id']         = (int) $wpdb->insert_id;
		$rij['antwoorden'] = $antwoorden;

		self::mail_bevestiging( $rij );
		self::mail_team( $rij );

		return $rij['id'];
	}

	private static function is_al_aangemeld( $training_id, $email ) {
		foreach ( self::aanmeldingen( $training_id, true ) as $rij ) {
			if ( 0 === strcasecmp( $rij['deelnemer_email'], $email ) ) {
				return true;
			}
		}
		return false;
	}

	private static function schoon_antwoord( $veld, $waarde ) {
		$waarde = is_scalar( $waarde ) ? (string) $waarde : '';
		switch ( $veld['soort'] ) {
			case 'lang':
				return sanitize_textarea_field( $waarde );
			case 'email':
				return sanitize_email( $waarde );
			case 'vinkje':
				return '' !== $waarde ? 'Ja' : 'Nee';
			default:
				return sanitize_text_field( $waarde );
		}
	}

	/** @return string Tekstsleutel van de fout, of leeg. */
	private static function controleer_antwoord( $veld, $waarde ) {
		// Een verplicht vinkje moet aan: "ik ga akkoord met de voorwaarden".
		$leeg = 'vinkje' === $veld['soort'] ? 'Ja' !== $waarde : '' === $waarde;
		if ( $leeg ) {
			return empty( $veld['verplicht'] ) ? '' : 'training_fout_verplicht';
		}
		switch ( $veld['soort'] ) {
			case 'email':
				return is_email( $waarde ) ? '' : 'training_fout_email';
			case 'getal':
				return is_numeric( $waarde ) ? '' : 'training_fout_getal';
			case 'datum':
				return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $waarde ) ? '' : 'training_fout_datum';
			case 'keuze':
				return isset( self::opties( $veld )[ $waarde ] ) ? '' : 'training_fout_verplicht';
		}
		return '';
	}

	/* === Mail =========================================================== */

	/** De bevestiging aan de deelnemer, in de taal waarin hij zich aanmeldde. */
	private static function mail_bevestiging( $rij ) {
		$id     = (int) $rij['training_id'];
		$regels = array(
			sprintf( HDP_I18N::t( 'training_mail_aanhef' ), $rij['deelnemer_naam'] ),
			'',
			sprintf( HDP_I18N::t( 'training_mail_ontvangen' ), self::titel( $id ) ),
			'',
		);
		foreach ( self::feiten( $id ) as $label => $waarde ) {
			$regels[] = $label . ': ' . $waarde;
		}
		$regels[] = '';
		$regels[] = HDP_I18N::t( 'training_mail_vragen' ) . ' ' . self::contactregel();
		$regels[] = '';
		$regels[] = HDP_I18N::t( 'training_mail_groet' );

		$headers = array();
		if ( is_email( $rij['dealer_email'] ) && 0 !== strcasecmp( $rij['dealer_email'], $rij['deelnemer_email'] ) ) {
			$headers[] = 'Cc: ' . $rij['dealer_email'];
		}

		$gelukt = wp_mail(
			$rij['deelnemer_email'],
			sprintf( HDP_I18N::t( 'training_mail_onderwerp' ), self::titel( $id ) ),
			implode( "\n", $regels ),
			$headers
		);
		if ( ! $gelukt ) {
			HDP_Log::schrijf( 'Bevestiging trainingsaanmelding niet verstuurd (aanmelding ' . $rij['id'] . ').', 'WAARSCHUWING' );
		}
	}

	/** Het bericht aan het team: altijd in het Nederlands. */
	private static function mail_team( $rij ) {
		$aan = self::team_email();
		if ( ! $aan ) {
			return;
		}

		$id        = (int) $rij['training_id'];
		$datum     = (string) get_post_meta( $id, self::META_DATUM, true );
		$bedrijf   = $rij['dealer_bedrijf'] ? $rij['dealer_bedrijf'] : $rij['dealer_naam'];
		$regels    = array(
			'Nieuwe aanmelding voor de training "' . $rij['training_titel'] . '"' . ( $datum ? ' (' . gmdate( 'd-m-Y', strtotime( $datum ) ) . ')' : '' ) . '.',
			'',
			'Dealer: ' . $bedrijf . ' (' . $rij['dealer_email'] . ')',
			'Deelnemer: ' . $rij['deelnemer_naam'],
			'E-mail deelnemer: ' . $rij['deelnemer_email'],
		);
		foreach ( $rij['antwoorden'] as $antwoord ) {
			$regels[] = $antwoord['label'] . ': ' . ( '' !== $antwoord['waarde'] ? $antwoord['waarde'] : '-' );
		}
		$regels[] = '';
		$over     = self::plaatsen_over( $id );
		$regels[] = 'Aangemeld tot nu toe: ' . self::aantal_aangemeld( $id ) . ( null === $over ? '' : ' (nog ' . $over . ' plaats(en) vrij)' );
		$regels[] = 'Alle aanmeldingen: ' . admin_url( 'edit.php?post_type=' . self::POST_TYPE . '&page=hdp-training-aanmeldingen&training=' . $id );

		$gelukt = wp_mail(
			$aan,
			'Nieuwe trainingsaanmelding: ' . $rij['training_titel'] . ' – ' . $rij['deelnemer_naam'],
			implode( "\n", $regels ),
			array( 'Reply-To: ' . $rij['deelnemer_email'] )
		);
		if ( ! $gelukt ) {
			HDP_Log::schrijf( 'Teammail trainingsaanmelding niet verstuurd (aanmelding ' . $rij['id'] . ').', 'WAARSCHUWING' );
		}
	}

	public static function team_email() {
		$adres = HDP_Settings::get( 'training_email' );
		return $adres ? $adres : HDP_Settings::get( 'contact_email' );
	}

	private static function contactregel() {
		return implode( ' / ', array_filter( array( self::team_email(), HDP_Settings::get( 'contact_telefoon' ) ) ) );
	}

	/**
	 * De vaste gegevens van een training, voor de pagina en de mail.
	 *
	 * @return array<string, string> Label => waarde; wat niet is ingevuld ontbreekt.
	 */
	public static function feiten( $training_id ) {
		$feiten = array(
			HDP_I18N::t( 'training_feit_datum' )   => self::datumtekst( $training_id ),
			HDP_I18N::t( 'training_feit_tijd' )    => (string) get_post_meta( $training_id, self::META_TIJD, true ),
			HDP_I18N::t( 'training_feit_locatie' ) => (string) get_post_meta( $training_id, self::META_LOCATIE, true ),
			HDP_I18N::t( 'training_feit_kosten' )  => (string) get_post_meta( $training_id, self::META_KOSTEN, true ),
		);
		return array_filter(
			$feiten,
			static function ( $waarde ) {
				return '' !== $waarde;
			}
		);
	}

	/* === Beheer in wp-admin ============================================= */

	public static function add_meta_boxes() {
		add_meta_box( 'hdp_training_gegevens', 'Gegevens van de training', array( __CLASS__, 'render_gegevens' ), self::POST_TYPE, 'side', 'high' );
		add_meta_box( 'hdp_training_velden', 'Wat moet de deelnemer invullen?', array( __CLASS__, 'render_velden' ), self::POST_TYPE, 'normal', 'high' );
		add_meta_box( 'hdp_training_frans', 'Franse vertaling', array( __CLASS__, 'render_frans' ), self::POST_TYPE, 'normal' );
	}

	public static function render_gegevens( $post ) {
		wp_nonce_field( 'hdp_training_opslaan', 'hdp_training_nonce' );
		$invoer = array(
			array( 'hdp_training_datum', 'Datum', 'date', self::META_DATUM, 'Leeg = "datum volgt".' ),
			array( 'hdp_training_einddatum', 'Einddatum', 'date', self::META_EINDDATUM, 'Alleen bij meer dagen.' ),
			array( 'hdp_training_tijd', 'Tijd', 'text', self::META_TIJD, 'Bijv. 09:00 - 16:00' ),
			array( 'hdp_training_locatie', 'Locatie', 'text', self::META_LOCATIE, '' ),
			array( 'hdp_training_kosten', 'Kosten', 'text', self::META_KOSTEN, 'Bijv. € 95 excl. btw, of "Gratis". Leeg = niet tonen.' ),
			array( 'hdp_training_plaatsen', 'Maximaal aantal plaatsen', 'number', self::META_PLAATSEN, 'Leeg = geen maximum.' ),
			array( 'hdp_training_sluit', 'Aanmelden kan tot en met', 'date', self::META_SLUIT, 'Leeg = tot de training voorbij is.' ),
		);
		foreach ( $invoer as $regel ) {
			printf(
				'<p><label for="%1$s"><strong>%2$s</strong></label><br><input type="%3$s" id="%1$s" name="%1$s" class="widefat" value="%4$s"%5$s>%6$s</p>',
				esc_attr( $regel[0] ),
				esc_html( $regel[1] ),
				esc_attr( $regel[2] ),
				esc_attr( get_post_meta( $post->ID, $regel[3], true ) ),
				'number' === $regel[2] ? ' min="1"' : '',
				$regel[4] ? '<span class="description">' . esc_html( $regel[4] ) . '</span>' : ''
			);
		}

		$gekozen = HDP_Merken::naar_array( get_post_meta( $post->ID, self::META_MERKEN, true ) );
		echo '<p><strong>Merken</strong><br><span class="description">Niets aanvinken = alle dealers zien deze training.</span></p>';
		foreach ( HDP_Merken::lijst() as $merk ) {
			printf(
				'<label style="display:block"><input type="checkbox" name="hdp_training_merken[]" value="%1$s" %2$s> %3$s</label>',
				esc_attr( $merk ),
				checked( in_array( $merk, $gekozen, true ), true, false ),
				esc_html( $merk )
			);
		}
	}

	public static function render_frans( $post ) {
		?>
		<p>
			<label for="hdp_training_titel_fr"><strong>Titel (Frans)</strong></label><br>
			<input type="text" name="hdp_training_titel_fr" id="hdp_training_titel_fr" class="widefat" value="<?php echo esc_attr( get_post_meta( $post->ID, self::META_TITEL_FR, true ) ); ?>">
		</p>
		<p>
			<label for="hdp_training_tekst_fr"><strong>Omschrijving (Frans)</strong></label><br>
			<textarea name="hdp_training_tekst_fr" id="hdp_training_tekst_fr" class="widefat" rows="6"><?php echo esc_textarea( get_post_meta( $post->ID, self::META_TEKST_FR, true ) ); ?></textarea>
			<span class="description">Laat je dit leeg, dan krijgt een Franstalige dealer de Nederlandse tekst te zien.</span>
		</p>
		<?php
	}

	/** De lijst met vragen. Naam en e-mail staan er altijd bij en hoeven hier niet in. */
	public static function render_velden( $post ) {
		$velden = self::velden( $post->ID );
		// Een nieuwe training begint met wat vrijwel altijd nodig is.
		if ( ! $velden && 'auto-draft' === $post->post_status ) {
			$velden = array(
				array(
					'id'        => 'telefoon',
					'label'     => 'Telefoonnummer',
					'label_fr'  => 'Numéro de téléphone',
					'soort'     => 'telefoon',
					'verplicht' => true,
					'opties'    => array(),
				),
			);
		}
		?>
		<p class="description">Naam en e-mailadres van de deelnemer worden altijd gevraagd. Voeg hier toe wat je daarnaast nodig hebt.</p>
		<table class="widefat striped" id="hdp-training-velden">
			<thead>
				<tr>
					<th>Vraag</th>
					<th>Vraag (Frans)</th>
					<th>Soort</th>
					<th>Keuzes (alleen bij keuzelijst)</th>
					<th>Verplicht</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $velden as $veld ) {
					self::render_veldregel( $veld );
				}
				?>
			</tbody>
		</table>
		<p>
			<button type="button" class="button" id="hdp-training-veld-erbij">Vraag toevoegen</button>
			<span class="description">Bij een keuzelijst: één keuze per regel, met het Frans erachter als dat anders is: <code>Vegetarisch | Végétarien</code></span>
		</p>
		<template id="hdp-training-veld-sjabloon">
			<?php
			self::render_veldregel(
				array(
					'id'        => '',
					'label'     => '',
					'label_fr'  => '',
					'soort'     => 'tekst',
					'verplicht' => false,
					'opties'    => array(),
				)
			);
			?>
		</template>
		<script>
			( function () {
				var lijst = document.querySelector( '#hdp-training-velden tbody' );
				document.getElementById( 'hdp-training-veld-erbij' ).addEventListener( 'click', function () {
					lijst.appendChild( document.getElementById( 'hdp-training-veld-sjabloon' ).content.cloneNode( true ) );
				} );
				lijst.addEventListener( 'click', function ( e ) {
					if ( e.target.classList.contains( 'hdp-training-veld-weg' ) ) {
						e.target.closest( 'tr' ).remove();
					}
				} );
			} )();
		</script>
		<?php
	}

	private static function render_veldregel( $veld ) {
		?>
		<tr>
			<td>
				<input type="hidden" name="hdp_veld_id[]" value="<?php echo esc_attr( $veld['id'] ); ?>">
				<input type="text" class="widefat" name="hdp_veld_label[]" value="<?php echo esc_attr( $veld['label'] ); ?>">
			</td>
			<td><input type="text" class="widefat" name="hdp_veld_label_fr[]" value="<?php echo esc_attr( $veld['label_fr'] ); ?>"></td>
			<td>
				<select name="hdp_veld_soort[]">
					<?php foreach ( self::VELDSOORTEN as $soort => $label ) : ?>
						<option value="<?php echo esc_attr( $soort ); ?>" <?php selected( $veld['soort'], $soort ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
			<td><textarea class="widefat" rows="2" name="hdp_veld_opties[]"><?php echo esc_textarea( implode( "\n", (array) $veld['opties'] ) ); ?></textarea></td>
			<td>
				<select name="hdp_veld_verplicht[]">
					<option value="0">Nee</option>
					<option value="1" <?php selected( ! empty( $veld['verplicht'] ) ); ?>>Ja</option>
				</select>
			</td>
			<td><button type="button" class="button-link hdp-training-veld-weg">Weghalen</button></td>
		</tr>
		<?php
	}

	public static function opslaan( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['hdp_training_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['hdp_training_nonce'] ) ), 'hdp_training_opslaan' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		foreach ( array(
			'hdp_training_datum'     => self::META_DATUM,
			'hdp_training_einddatum' => self::META_EINDDATUM,
			'hdp_training_sluit'     => self::META_SLUIT,
		) as $veld => $meta ) {
			$datum = isset( $_POST[ $veld ] ) ? sanitize_text_field( wp_unslash( $_POST[ $veld ] ) ) : '';
			update_post_meta( $post_id, $meta, preg_match( '/^\d{4}-\d{2}-\d{2}$/', $datum ) ? $datum : '' );
		}
		foreach ( array(
			'hdp_training_tijd'    => self::META_TIJD,
			'hdp_training_locatie' => self::META_LOCATIE,
			'hdp_training_kosten'  => self::META_KOSTEN,
			'hdp_training_titel_fr' => self::META_TITEL_FR,
		) as $veld => $meta ) {
			update_post_meta( $post_id, $meta, isset( $_POST[ $veld ] ) ? sanitize_text_field( wp_unslash( $_POST[ $veld ] ) ) : '' );
		}
		update_post_meta( $post_id, self::META_PLAATSEN, isset( $_POST['hdp_training_plaatsen'] ) ? absint( $_POST['hdp_training_plaatsen'] ) : 0 );
		update_post_meta( $post_id, self::META_TEKST_FR, isset( $_POST['hdp_training_tekst_fr'] ) ? wp_kses_post( wp_unslash( $_POST['hdp_training_tekst_fr'] ) ) : '' );
		update_post_meta(
			$post_id,
			self::META_MERKEN,
			isset( $_POST['hdp_training_merken'] ) ? HDP_Merken::uit_selectie( array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['hdp_training_merken'] ) ) ) : ''
		);

		$velden = self::velden_uit_formulier( wp_unslash( $_POST ) );
		update_post_meta( $post_id, self::META_VELDEN, wp_slash( wp_json_encode( $velden, JSON_UNESCAPED_UNICODE ) ) );
	}

	/**
	 * Zet de rijen uit het formulier om. Een regel zonder vraag vervalt. Een
	 * bestaand veld houdt zijn nummer, ook als de vraag wordt herschreven.
	 *
	 * @param array $post Zonder slashes.
	 * @return array
	 */
	public static function velden_uit_formulier( $post ) {
		$velden = array();
		$labels = isset( $post['hdp_veld_label'] ) ? (array) $post['hdp_veld_label'] : array();
		foreach ( $labels as $i => $label ) {
			$label = sanitize_text_field( $label );
			if ( '' === $label ) {
				continue;
			}
			$soort  = isset( $post['hdp_veld_soort'][ $i ] ) ? sanitize_key( $post['hdp_veld_soort'][ $i ] ) : 'tekst';
			$soort  = isset( self::VELDSOORTEN[ $soort ] ) ? $soort : 'tekst';
			$opties = array();
			if ( 'keuze' === $soort && isset( $post['hdp_veld_opties'][ $i ] ) ) {
				$opties = array_values( array_filter( array_map( 'trim', explode( "\n", sanitize_textarea_field( $post['hdp_veld_opties'][ $i ] ) ) ) ) );
			}
			$id       = isset( $post['hdp_veld_id'][ $i ] ) ? sanitize_key( $post['hdp_veld_id'][ $i ] ) : '';
			$velden[] = array(
				'id'        => '' !== $id ? $id : strtolower( wp_generate_password( 8, false ) ),
				'label'     => $label,
				'label_fr'  => isset( $post['hdp_veld_label_fr'][ $i ] ) ? sanitize_text_field( $post['hdp_veld_label_fr'][ $i ] ) : '',
				'soort'     => $soort,
				'verplicht' => ! empty( $post['hdp_veld_verplicht'][ $i ] ),
				'opties'    => $opties,
			);
		}
		return $velden;
	}

	public static function kolommen( $kolommen ) {
		$nieuw = array();
		foreach ( $kolommen as $sleutel => $label ) {
			if ( 'date' === $sleutel ) {
				continue;
			}
			$nieuw[ $sleutel ] = $label;
			if ( 'title' === $sleutel ) {
				$nieuw['hdp_datum']       = 'Datum training';
				$nieuw['hdp_aanmeldingen'] = 'Aanmeldingen';
				$nieuw['hdp_merken']      = 'Voor';
				$nieuw['hdp_talen']       = 'Talen';
			}
		}
		return $nieuw;
	}

	public static function kolom_inhoud( $kolom, $post_id ) {
		if ( 'hdp_datum' === $kolom ) {
			$datum = (string) get_post_meta( $post_id, self::META_DATUM, true );
			echo esc_html( $datum ? gmdate( 'd-m-Y', strtotime( $datum ) ) : 'Datum volgt' );
		}
		if ( 'hdp_aanmeldingen' === $kolom ) {
			$plaatsen = (int) get_post_meta( $post_id, self::META_PLAATSEN, true );
			printf(
				'<a href="%s">%s</a>',
				esc_url( self::beheer_url( $post_id ) ),
				esc_html( self::aantal_aangemeld( $post_id ) . ( $plaatsen ? ' van ' . $plaatsen : '' ) )
			);
		}
		if ( 'hdp_merken' === $kolom ) {
			$merken = get_post_meta( $post_id, self::META_MERKEN, true );
			echo esc_html( $merken ? $merken : 'Alle dealers' );
		}
		if ( 'hdp_talen' === $kolom ) {
			echo esc_html( '' !== trim( (string) get_post_meta( $post_id, self::META_TEKST_FR, true ) ) ? 'NL + FR' : 'alleen NL' );
		}
	}

	private static function beheer_url( $training_id = 0 ) {
		$url = admin_url( 'edit.php?post_type=' . self::POST_TYPE . '&page=hdp-training-aanmeldingen' );
		return $training_id ? add_query_arg( 'training', (int) $training_id, $url ) : $url;
	}

	public static function registreer_menu() {
		add_submenu_page(
			'edit.php?post_type=' . self::POST_TYPE,
			'Aanmeldingen',
			'Aanmeldingen',
			self::CAP_BEHEER,
			'hdp-training-aanmeldingen',
			array( __CLASS__, 'render_aanmeldingen' )
		);
	}

	/** De kolommen voor het beheerscherm en de CSV: vaste gegevens, plus elke vraag die ooit gesteld is. */
	private static function tabelkoppen( $rijen ) {
		$vragen = array();
		foreach ( $rijen as $rij ) {
			foreach ( $rij['antwoorden'] as $antwoord ) {
				$vragen[ $antwoord['id'] ] = $antwoord['label'];
			}
		}
		return $vragen;
	}

	private static function antwoord_op( $rij, $vraag_id ) {
		foreach ( $rij['antwoorden'] as $antwoord ) {
			if ( $antwoord['id'] === $vraag_id ) {
				return (string) $antwoord['waarde'];
			}
		}
		return '';
	}

	private static function bedrijf_van( $rij ) {
		return $rij['dealer_bedrijf'] ? $rij['dealer_bedrijf'] : $rij['dealer_naam'];
	}

	private static function aangemeld_op( $rij ) {
		return get_date_from_gmt( $rij['aangemaakt'], 'd-m-Y H:i' );
	}

	public static function render_aanmeldingen() {
		if ( ! current_user_can( self::CAP_BEHEER ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- alleen een keuze uit de URL.
		$training_id = isset( $_GET['training'] ) ? absint( $_GET['training'] ) : 0;
		$trainingen  = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'publish', 'draft', 'future', 'private' ),
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		if ( ! $training_id && $trainingen ) {
			$training_id = (int) $trainingen[0]->ID;
		}
		?>
		<div class="wrap">
			<h1>Aanmeldingen voor trainingen</h1>
			<?php if ( ! $trainingen ) : ?>
				<p>Er zijn nog geen trainingen geplaatst.</p>
			</div>
				<?php
				return;
			endif;
			?>
			<form method="get" style="margin:1em 0">
				<input type="hidden" name="post_type" value="<?php echo esc_attr( self::POST_TYPE ); ?>">
				<input type="hidden" name="page" value="hdp-training-aanmeldingen">
				<label for="hdp-training-keuze"><strong>Training:</strong></label>
				<select name="training" id="hdp-training-keuze" onchange="this.form.submit()">
					<?php foreach ( $trainingen as $training ) : ?>
						<option value="<?php echo esc_attr( $training->ID ); ?>" <?php selected( $training_id, $training->ID ); ?>><?php echo esc_html( get_the_title( $training ) ); ?></option>
					<?php endforeach; ?>
				</select>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hdp_training_csv&training=' . $training_id ), 'hdp_training_csv' ) ); ?>">Download als Excel-bestand (CSV)</a>
			</form>
			<?php
			$rijen  = self::aanmeldingen( $training_id );
			$vragen = self::tabelkoppen( $rijen );
			if ( ! $rijen ) :
				?>
				<p>Nog niemand aangemeld.</p>
				<?php
			else :
				$plaatsen = (int) get_post_meta( $training_id, self::META_PLAATSEN, true );
				?>
				<p><strong><?php echo esc_html( self::aantal_aangemeld( $training_id ) . ( $plaatsen ? ' van ' . $plaatsen : '' ) ); ?></strong> aangemeld.</p>
				<table class="widefat striped">
					<thead>
						<tr>
							<th>Deelnemer</th>
							<th>E-mail</th>
							<th>Dealer</th>
							<?php foreach ( $vragen as $vraag ) : ?>
								<th><?php echo esc_html( $vraag ); ?></th>
							<?php endforeach; ?>
							<th>Aangemeld op</th>
							<th>Status</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rijen as $rij ) : ?>
							<tr<?php echo self::STATUS_GEANNULEERD === $rij['status'] ? ' style="opacity:.55"' : ''; ?>>
								<td><?php echo esc_html( $rij['deelnemer_naam'] ); ?></td>
								<td><a href="mailto:<?php echo esc_attr( $rij['deelnemer_email'] ); ?>"><?php echo esc_html( $rij['deelnemer_email'] ); ?></a></td>
								<td><?php echo esc_html( self::bedrijf_van( $rij ) ); ?></td>
								<?php foreach ( array_keys( $vragen ) as $vraag_id ) : ?>
									<td><?php echo esc_html( self::antwoord_op( $rij, $vraag_id ) ); ?></td>
								<?php endforeach; ?>
								<td><?php echo esc_html( self::aangemeld_op( $rij ) ); ?></td>
								<td><?php echo esc_html( self::STATUS_GEANNULEERD === $rij['status'] ? 'Geannuleerd' : 'Aangemeld' ); ?></td>
								<td>
									<?php if ( self::STATUS_AANGEMELD === $rij['status'] ) : ?>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hdp_training_annuleer&aanmelding=' . (int) $rij['id'] . '&training=' . $training_id ), 'hdp_training_annuleer_' . (int) $rij['id'] ) ); ?>"
											onclick="return confirm('Deze aanmelding annuleren? De plaats komt weer vrij. De deelnemer krijgt hiervan geen bericht.')">Annuleren</a>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function verwerk_annuleren() {
		$id          = isset( $_GET['aanmelding'] ) ? absint( $_GET['aanmelding'] ) : 0;
		$training_id = isset( $_GET['training'] ) ? absint( $_GET['training'] ) : 0;
		check_admin_referer( 'hdp_training_annuleer_' . $id );
		if ( ! current_user_can( self::CAP_BEHEER ) ) {
			wp_die( 'Geen toegang.' );
		}
		self::annuleer( $id );
		wp_safe_redirect( self::beheer_url( $training_id ) );
		exit;
	}

	public static function annuleer( $aanmelding_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- eigen tabel.
		return (bool) $wpdb->update( self::tabel(), array( 'status' => self::STATUS_GEANNULEERD ), array( 'id' => (int) $aanmelding_id ) );
	}

	/**
	 * Een cel voor de CSV. Puntkomma's en Excel zijn de bedoeling, en Excel
	 * voert wat met = + - @ begint uit als formule; een dealer vult dit zelf in.
	 */
	public static function csv_cel( $waarde ) {
		$waarde = (string) $waarde;
		if ( '' !== $waarde && false !== strpos( "=+-@\t\r", $waarde[0] ) ) {
			$waarde = "'" . $waarde;
		}
		return '"' . str_replace( '"', '""', $waarde ) . '"';
	}

	public static function exporteer_csv() {
		check_admin_referer( 'hdp_training_csv' );
		if ( ! current_user_can( self::CAP_BEHEER ) ) {
			wp_die( 'Geen toegang.' );
		}

		$training_id = isset( $_GET['training'] ) ? absint( $_GET['training'] ) : 0;
		$rijen       = self::aanmeldingen( $training_id );
		$vragen      = self::tabelkoppen( $rijen );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="aanmeldingen-' . sanitize_title( get_the_title( $training_id ) ) . '.csv"' );

		$regels = array( array_merge( array( 'Deelnemer', 'E-mail', 'Dealer', 'E-mail dealer' ), array_values( $vragen ), array( 'Aangemeld op', 'Status' ) ) );
		foreach ( $rijen as $rij ) {
			$antwoorden = array();
			foreach ( array_keys( $vragen ) as $vraag_id ) {
				$antwoorden[] = self::antwoord_op( $rij, $vraag_id );
			}
			$regels[] = array_merge(
				array( $rij['deelnemer_naam'], $rij['deelnemer_email'], self::bedrijf_van( $rij ), $rij['dealer_email'] ),
				$antwoorden,
				array( self::aangemeld_op( $rij ), self::STATUS_GEANNULEERD === $rij['status'] ? 'Geannuleerd' : 'Aangemeld' )
			);
		}

		// De BOM vooraan zorgt dat Excel de é en ë goed toont.
		echo "\xEF\xBB\xBF"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		foreach ( $regels as $regel ) {
			echo implode( ';', array_map( array( __CLASS__, 'csv_cel' ), $regel ) ) . "\r\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV, geen HTML.
		}
		exit;
	}
}
