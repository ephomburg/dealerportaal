<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Verwerkt wat een dealer op de garantiepagina indient: een machine
 * aanmelden, een claim indienen, of een bericht bij een ticket.
 *
 * Draait op template_redirect, dus vóór er iets is uitgestuurd — zo kan na
 * het opslaan doorverwezen worden naar een nette URL. Dat voorkomt dat een
 * dealer bij het verversen van de pagina zijn claim een tweede keer
 * indient.
 *
 * Gaat er iets mis, dan onthouden we de melding én wat er was ingevuld in
 * een kortlopende transient, en verwijzen we terug naar het formulier. De
 * dealer hoeft dan niet alles opnieuw te typen — bij een lang klachtverhaal
 * is dat het verschil tussen "even opnieuw" en "laat maar".
 */
class HDP_Garantie_Formulier {

	const ACTIE_VELD = 'hdp_garantie_actie';
	const NONCE      = 'hdp_garantie';

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'verwerk' ) );
	}

	/** Waar de melding en de ingevulde waarden tijdelijk staan. */
	private static function bewaarsleutel( $soort ) {
		return 'hdp_garantie_' . $soort . '_' . get_current_user_id();
	}

	public static function fout() {
		$sleutel = self::bewaarsleutel( 'fout' );
		$fout    = get_transient( $sleutel );
		if ( $fout ) {
			delete_transient( $sleutel );
		}
		return $fout ? $fout : array();
	}

	public static function geslaagd() {
		$sleutel = self::bewaarsleutel( 'ok' );
		$melding = get_transient( $sleutel );
		if ( $melding ) {
			delete_transient( $sleutel );
		}
		return $melding ? $melding : array();
	}

	/** Eerder ingevulde waarde, zodat een formulier na een fout niet leeg is. */
	public static function eerder( $veld, $standaard = '' ) {
		static $waarden = null;
		if ( null === $waarden ) {
			$sleutel = self::bewaarsleutel( 'waarden' );
			$waarden = get_transient( $sleutel );
			$waarden = is_array( $waarden ) ? $waarden : array();
			delete_transient( $sleutel );
		}
		return isset( $waarden[ $veld ] ) ? $waarden[ $veld ] : $standaard;
	}

	public static function verwerk() {
		self::vang_te_groot_af();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- de nonce wordt hieronder gecontroleerd zodra we weten dát dit een van onze formulieren is.
		$actie = isset( $_POST[ self::ACTIE_VELD ] ) ? sanitize_key( wp_unslash( $_POST[ self::ACTIE_VELD ] ) ) : '';

		if ( ! in_array( $actie, array( 'machine', 'claim', 'bericht' ), true ) ) {
			return;
		}

		if ( ! is_user_logged_in() || ! HDP_Roles::mag_portaal_zien( get_current_user_id() ) ) {
			self::terug_met_fout( 'garantie', HDP_I18N::t( 'garantie_fout_geen_toegang' ) );
		}

		check_admin_referer( self::NONCE );

		if ( 'machine' === $actie ) {
			self::verwerk_machine();
		} elseif ( 'claim' === $actie ) {
			self::verwerk_claim();
		} else {
			self::verwerk_bericht();
		}
	}

	/**
	 * Een verzending die groter is dan de server aankan.
	 *
	 * Dan gooit PHP álles weg — de bestanden, maar ook de tekstvelden en
	 * het veld dat zegt welk formulier het was. De dealer kwam zonder enige
	 * melding terug op een leeg formulier. Het enige spoor is dat er wel
	 * iets binnenkwam (Content-Length) maar er niets van over is.
	 *
	 * Geen nonce-controle nodig: er wordt niets opgeslagen, alleen een
	 * melding getoond op de pagina waar de dealer al was.
	 */
	private static function vang_te_groot_af() {
		$methode = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '';
		$lengte  = isset( $_SERVER['CONTENT_LENGTH'] ) ? (int) $_SERVER['CONTENT_LENGTH'] : 0;

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- alleen kijken óf er iets is.
		if ( 'post' !== $methode || ! $lengte || ! empty( $_POST ) || ! empty( $_FILES ) ) {
			return;
		}
		if ( ! is_page( 'garantie' ) || ! is_user_logged_in() || ! HDP_Roles::mag_portaal_zien( get_current_user_id() ) ) {
			return;
		}

		$terug = isset( $_SERVER['REQUEST_URI'] ) ? home_url( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ) : home_url( '/garantie/' );

		self::terug_met_fout(
			'garantie',
			sprintf( HDP_I18N::t( 'garantie_fout_te_groot' ), size_format( wp_convert_hr_to_bytes( ini_get( 'post_max_size' ) ) ) ),
			array(),
			$terug
		);
	}

	/* ---------------------------------------------------------------- */

	private static function verwerk_machine() {
		$waarden = array(
			'machine'      => self::tekst( 'machine' ),
			'merk'         => self::tekst( 'merk' ),
			'serienummer'  => self::tekst( 'serienummer' ),
			'aankoopdatum' => self::tekst( 'aankoopdatum' ),
			'klant'        => self::tekst( 'klant' ),
		);

		$ontbreekt = self::ontbrekende_velden( $waarden, HDP_Garantie::machinevelden() );
		if ( $ontbreekt ) {
			self::terug_met_fout( 'machine', self::melding_ontbrekend( $ontbreekt ), $waarden );
		}

		if ( ! in_array( $waarden['merk'], HDP_Merken::lijst(), true ) ) {
			self::terug_met_fout( 'machine', HDP_I18N::t( 'garantie_fout_merk' ), $waarden );
		}

		// Een tikfout als 2062 in plaats van 2026 gaf een garantie die
		// decennia doorliep. Het veld zelf weigert al een datum na vandaag,
		// maar dat is in de browser te omzeilen.
		$aankoop = strtotime( $waarden['aankoopdatum'] );
		if ( ! $aankoop || $aankoop > strtotime( current_time( 'Y-m-d' ) ) ) {
			self::terug_met_fout( 'machine', HDP_I18N::t( 'garantie_fout_datum_toekomst' ), $waarden );
		}

		$machine = HDP_Garantie::meld_machine_aan( $waarden );
		if ( is_wp_error( $machine ) ) {
			self::terug_met_fout( 'machine', self::leesbaar( $machine ), $waarden );
		}

		$mislukt = HDP_Garantie::bewaar_bijlagen( self::bestanden( 'bijlagen' ), 'machine', $machine['id'] );

		self::terug_met_melding(
			'garantie',
			HDP_I18N::t( 'garantie_ok_machine' ),
			$mislukt
		);
	}

	private static function verwerk_claim() {
		$soort     = HDP_Garantie::claimsoort( self::tekst( 'soort' ) );
		$bestanden = self::bestanden( 'fotos' );

		$waarden = array(
			'soort'              => $soort,
			'machine'            => self::tekst( 'machinekeuze' ),
			'onderdeel_probleem' => self::tekst( 'onderdeel_probleem' ),
			'factuurnummer'      => self::tekst( 'factuurnummer' ),
			'klacht'             => self::tekst( 'klacht' ),
			'regels'             => self::claimregels(),
			'te_claimen_tijd'    => self::getal( 'te_claimen_tijd' ),
			'hectares'           => self::getal( 'hectares' ),
			'klantreferentie'    => self::tekst( 'klantreferentie' ),
			'opmerkingen'        => self::tekst( 'opmerkingen' ),
			'onderdeel_retour'   => self::aangevinkt( 'onderdeel_retour' ),
		);

		$terug = add_query_arg(
			array(
				'nieuw' => 'claim',
				'soort' => $soort,
			),
			home_url( '/garantie/' )
		);

		if ( HDP_Garantie::SOORT_MACHINE === $soort && '' === $waarden['machine'] ) {
			self::terug_met_fout( 'claim', HDP_I18N::t( 'garantie_fout_machine' ), $waarden, $terug );
		}

		$ontbreekt = self::ontbrekende_velden( $waarden, HDP_Garantie::velden( $soort ) );
		if ( $ontbreekt ) {
			self::terug_met_fout( 'claim', self::melding_ontbrekend( $ontbreekt ), $waarden, $terug );
		}

		if ( HDP_Garantie::SOORT_ONDERDEEL === $soort && ! $waarden['regels'] ) {
			self::terug_met_fout( 'claim', HDP_I18N::t( 'garantie_fout_geen_regels' ), $waarden, $terug );
		}

		// Bij een machineclaim horen de uren per onderdeel te staan. Zonder
		// die eis belandt alle tijd op één hoop en valt achteraf niet meer
		// te zien waar hij aan op is gegaan.
		if ( HDP_Garantie::SOORT_MACHINE === $soort && self::mist_uren( $waarden['regels'] ) ) {
			self::terug_met_fout( 'claim', HDP_I18N::t( 'garantie_fout_uren_per_regel' ), $waarden, $terug );
		}

		// Onderdelen die niet van een Homburg-factuur komen, kunnen hier niet
		// nagekeken worden; zonder de factuur van de leverancier valt er niets
		// te claimen. Dus: dan moet er een bijlage mee.
		if ( self::heeft_derden( $waarden['regels'] ) && ! self::heeft_bestand( $bestanden ) ) {
			self::terug_met_fout( 'claim', HDP_I18N::t( 'garantie_fout_factuur_nodig' ), $waarden, $terug );
		}

		$claim = HDP_Garantie::dien_claim_in( $waarden );
		if ( is_wp_error( $claim ) ) {
			self::terug_met_fout( 'claim', self::leesbaar( $claim ), $waarden, $terug );
		}

		$mislukt = HDP_Garantie::bewaar_bijlagen( $bestanden, 'claim', $claim['id'] );

		// Verwachten we een onderdeel, dan is dat het eerste wat de dealer
		// moet weten: tot het pakket hier is, gebeurt er niets. Geen "stuurt
		// u onderdelen retour?" maar een opdracht.
		$sleutel = HDP_Garantie::onderdeel_verwacht( $waarden )
			? 'garantie_ok_claim_onderdeel'
			: 'garantie_ok_claim';

		self::terug_met_melding(
			'garantie',
			sprintf( HDP_I18N::t( $sleutel ), $claim['nummer'] ),
			$mislukt,
			add_query_arg( 'ticket', $claim['nummer'], home_url( '/garantie/' ) )
		);
	}

	private static function verwerk_bericht() {
		$nummer = self::tekst( 'ticket' );
		$tekst  = self::tekst( 'bericht' );

		$claim = HDP_Garantie::stuur_bericht( $nummer, $tekst, self::bestanden( 'bijlagen' ) );
		if ( is_wp_error( $claim ) ) {
			self::terug_met_fout( 'bericht', self::leesbaar( $claim ), array( 'bericht' => $tekst ), add_query_arg( 'ticket', $nummer, home_url( '/garantie/' ) ) );
		}

		self::terug_met_melding(
			'bericht',
			HDP_I18N::t( 'garantie_ok_bericht' ),
			isset( $claim['bijlagen_mislukt'] ) ? $claim['bijlagen_mislukt'] : array(),
			add_query_arg( 'ticket', $nummer, home_url( '/garantie/' ) )
		);
	}

	/* ---------------------------------------------------------------- */

	/**
	 * De regels van "te claimen onderdelen", als een lijst voor de database.
	 *
	 * Het formulier stuurt vier gelijke rijtjes (nummer, aantal, bedrag,
	 * herkomst). Lege regels vallen af: er staan er altijd een paar klaar en
	 * de meeste claims vullen die niet allemaal.
	 *
	 * Herkomst is bewust een keuzemenu en geen vinkje: een vinkje dat uit
	 * staat stuurt niets mee, waardoor de rijtjes niet meer gelijk lopen en
	 * bedrag 2 bij onderdeel 3 terechtkomt.
	 *
	 * @return array
	 */
	private static function claimregels() {
		$nummers   = self::lijst( 'onderdeel_nummer' );
		$aantallen = self::lijst( 'onderdeel_aantal' );
		$bedragen  = self::lijst( 'onderdeel_bedrag' );
		$herkomst  = self::lijst( 'onderdeel_herkomst' );
		$uren      = self::lijst( 'onderdeel_uren' );

		$regels = array();
		foreach ( $nummers as $i => $nummer ) {
			$nummer = trim( $nummer );
			if ( '' === $nummer ) {
				continue;
			}

			$aantal = isset( $aantallen[ $i ] ) ? (int) $aantallen[ $i ] : 1;

			// null en niet 0: "niets ingevuld" is iets anders dan "nul uur
			// aan gewerkt", en op dat verschil wordt verderop gecontroleerd.
			$dit_uur = isset( $uren[ $i ] ) ? trim( (string) $uren[ $i ] ) : '';

			$regels[] = array(
				'nummer'          => $nummer,
				'aantal'          => max( 1, $aantal ),
				'bedrag_cent'     => self::bedrag( isset( $bedragen[ $i ] ) ? $bedragen[ $i ] : '' ),
				'homburg_factuur' => 'derden' !== ( isset( $herkomst[ $i ] ) ? $herkomst[ $i ] : 'homburg' ),
				'uren'            => '' === $dit_uur ? null : max( 0.0, (float) str_replace( ',', '.', $dit_uur ) ),
			);
		}

		return $regels;
	}

	/** Of er een regel bij zit waar de uren niet zijn ingevuld. */
	private static function mist_uren( $regels ) {
		foreach ( (array) $regels as $regel ) {
			if ( ! isset( $regel['uren'] ) || null === $regel['uren'] ) {
				return true;
			}
		}

		return false;
	}

	/** Of er minstens één regel van een andere leverancier bij zit. */
	private static function heeft_derden( $regels ) {
		foreach ( (array) $regels as $regel ) {
			if ( empty( $regel['homburg_factuur'] ) ) {
				return true;
			}
		}

		return false;
	}

	/** Of er daadwerkelijk een bestand is meegestuurd. */
	private static function heeft_bestand( $bestanden ) {
		if ( empty( $bestanden['name'] ) ) {
			return false;
		}

		foreach ( (array) $bestanden['name'] as $naam ) {
			if ( '' !== trim( (string) $naam ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Een bedrag zoals een mens het typt: "1.021,31", "1021.31", "€ 12,99".
	 *
	 * Staat er een komma in, dan is dat het decimaalteken en is een punt een
	 * duizendtalscheiding — dat is hier de gangbare schrijfwijze.
	 *
	 * Centen en geen kommagetal: 6,11 is als kommagetal niet precies op te
	 * slaan (het wordt 6.1100000000000003) en dat loopt bij het optellen van
	 * een claimregel of tien zichtbaar mis. Een geheel getal centen is exact.
	 *
	 * @param string $ruw Wat er is ingetypt.
	 * @return int|null Hele centen.
	 */
	private static function bedrag( $ruw ) {
		$ruw = trim( str_replace( array( "\xe2\x82\xac", "\xc2\xa0", ' ' ), '', (string) $ruw ) );
		if ( '' === $ruw ) {
			return null;
		}

		if ( false !== strpos( $ruw, ',' ) ) {
			$ruw = str_replace( ',', '.', str_replace( '.', '', $ruw ) );
		}

		return is_numeric( $ruw ) ? (int) round( (float) $ruw * 100 ) : null;
	}

	/**
	 * Een gewoon getalveld (uren, hectares); leeg blijft leeg i.p.v. 0.
	 *
	 * Bewust niet via bedrag(): dat rekent naar centen, en anderhalf uur is
	 * geen 150.
	 */
	private static function getal( $veld ) {
		$ruw = str_replace( ',', '.', self::tekst( $veld ) );

		return is_numeric( $ruw ) ? $ruw : '';
	}

	private static function aangevinkt( $veld ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer() is al gedraaid in verwerk().
		return ! empty( $_POST[ $veld ] );
	}

	/** Een rijtje gelijknamige velden uit het formulier. */
	private static function lijst( $veld ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- zie verwerk().
		if ( ! isset( $_POST[ $veld ] ) || ! is_array( $_POST[ $veld ] ) ) {
			return array();
		}

		$waarden = array();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- elke waarde gaat hieronder apart door sanitize_text_field().
		foreach ( wp_unslash( (array) $_POST[ $veld ] ) as $waarde ) {
			$waarden[] = sanitize_text_field( $waarde );
		}

		return $waarden;
	}

	private static function tekst( $veld ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- check_admin_referer() is al gedraaid in verwerk().
		if ( ! isset( $_POST[ $veld ] ) ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- zie hierboven.
		return trim( sanitize_textarea_field( wp_unslash( $_POST[ $veld ] ) ) );
	}

	/**
	 * De meegestuurde bestanden, ongewijzigd doorgegeven.
	 *
	 * Opschonen heeft hier geen zin: het gaat om een lijst namen, maten en
	 * tijdelijke paden. HDP_Garantie::bewaar_bijlagen() schoont de naam op,
	 * controleert de grootte en laat WordPress bepalen wat voor bestand het
	 * werkelijk is — op de meegestuurde bestandsnaam wordt niet vertrouwd.
	 */
	private static function bestanden( $veld ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- check_admin_referer() is al gedraaid in verwerk(); de inhoud wordt in bewaar_bijlagen() gecontroleerd.
		return isset( $_FILES[ $veld ] ) ? $_FILES[ $veld ] : array();
	}

	/** Welke verplichte velden zijn leeg gebleven? */
	private static function ontbrekende_velden( $waarden, $velden ) {
		$ontbreekt = array();
		foreach ( $velden as $naam => $veld ) {
			if ( empty( $veld['verplicht'] ) || ! empty( $veld['uit_machine'] ) ) {
				continue;
			}
			if ( 'file' === $veld['type'] ) {
				continue;
			}
			if ( ! isset( $waarden[ $naam ] ) || '' === $waarden[ $naam ] ) {
				$ontbreekt[] = HDP_I18N::t( 'garantie_veld_' . $naam );
			}
		}
		return $ontbreekt;
	}

	private static function melding_ontbrekend( $namen ) {
		return sprintf( HDP_I18N::t( 'garantie_fout_ontbreekt' ), implode( ', ', $namen ) );
	}

	/**
	 * De database weigert bewust van alles en zegt er waarom bij. Die tekst
	 * is bruikbaar voor een dealer ("Deze machine is al aangemeld"), dus die
	 * tonen we — op één na: een technische melding helpt niemand.
	 */
	private static function leesbaar( $fout ) {
		$melding = $fout->get_error_message();

		if ( false !== stripos( $melding, 'duplicate key' ) || false !== stripos( $melding, 'machines_serienummer' ) ) {
			return HDP_I18N::t( 'garantie_fout_serienummer_bestaat' );
		}
		if ( 'hdp_supabase_niet_ingesteld' === $fout->get_error_code() || 'http_request_failed' === $fout->get_error_code() ) {
			return HDP_I18N::t( 'garantie_storing_tekst' );
		}

		// Alleen een bewuste weigering van de database (RAISE, code P0001)
		// gaat letterlijk door: die is voor mensen geschreven. Een
		// technische fout kwam eerder ook letterlijk op het scherm, als
		// Engelse Postgres-tekst of zelfs een HTML-foutpagina. Die staat in
		// het logboek; de dealer krijgt een nette melding.
		if ( 'hdp_supabase_fout' === $fout->get_error_code() ) {
			$data = $fout->get_error_data();
			return ( is_array( $data ) && isset( $data['pg_code'] ) && 'P0001' === $data['pg_code'] )
				? $melding
				: HDP_I18N::t( 'garantie_fout_algemeen' );
		}

		// Onze eigen meldingen (hdp_*) zijn al leesbaar.
		return $melding;
	}

	private static function terug_met_fout( $soort, $melding, $waarden = array(), $url = '' ) {
		// Tekst komt na een fout terug, gekozen bestanden niet: een browser
		// laat een bestandsveld niet door een website vullen. Zonder deze
		// zin verstuurde de dealer de tweede keer zonder foto's, zonder het
		// te merken.
		if ( self::heeft_bestand( self::bestanden( 'fotos' ) ) || self::heeft_bestand( self::bestanden( 'bijlagen' ) ) ) {
			$melding .= ' ' . HDP_I18N::t( 'garantie_bestanden_opnieuw' );
		}

		set_transient( self::bewaarsleutel( 'fout' ), array( 'soort' => $soort, 'melding' => $melding ), 5 * MINUTE_IN_SECONDS );
		if ( $waarden ) {
			set_transient( self::bewaarsleutel( 'waarden' ), $waarden, 5 * MINUTE_IN_SECONDS );
		}

		if ( ! $url ) {
			$url = 'garantie' === $soort
				? home_url( '/garantie/' )
				: add_query_arg( 'nieuw', $soort, home_url( '/garantie/' ) );
		}

		wp_safe_redirect( $url );
		exit;
	}

	private static function terug_met_melding( $soort, $melding, $mislukt = array(), $url = '' ) {
		set_transient(
			self::bewaarsleutel( 'ok' ),
			array(
				'soort'   => $soort,
				'melding' => $melding,
				'mislukt' => $mislukt,
			),
			5 * MINUTE_IN_SECONDS
		);

		wp_safe_redirect( $url ? $url : home_url( '/garantie/' ) );
		exit;
	}
}
