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

	/* ---------------------------------------------------------------- */

	private static function verwerk_machine() {
		$waarden = array(
			'machine'      => self::tekst( 'machine' ),
			'merk'         => self::tekst( 'merk' ),
			'serienummer'  => self::tekst( 'serienummer' ),
			'aankoopdatum' => self::tekst( 'aankoopdatum' ),
			'klant'        => self::tekst( 'klant' ),
			'hectares'     => self::tekst( 'hectares' ),
		);

		$ontbreekt = self::ontbrekende_velden( $waarden, HDP_Garantie::machinevelden() );
		if ( $ontbreekt ) {
			self::terug_met_fout( 'machine', self::melding_ontbrekend( $ontbreekt ), $waarden );
		}

		if ( ! in_array( $waarden['merk'], HDP_Merken::lijst(), true ) ) {
			self::terug_met_fout( 'machine', HDP_I18N::t( 'garantie_fout_merk' ), $waarden );
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
		$waarden = array(
			'machine'    => self::tekst( 'machinekeuze' ),
			'klacht'     => self::tekst( 'klacht' ),
			'onderdelen' => self::tekst( 'onderdelen' ),
			'hectares'   => self::tekst( 'hectares' ),
		);

		if ( '' === $waarden['machine'] ) {
			self::terug_met_fout( 'claim', HDP_I18N::t( 'garantie_fout_machine' ), $waarden );
		}
		if ( '' === $waarden['klacht'] ) {
			self::terug_met_fout( 'claim', HDP_I18N::t( 'garantie_fout_klacht' ), $waarden );
		}

		$claim = HDP_Garantie::dien_claim_in( $waarden );
		if ( is_wp_error( $claim ) ) {
			self::terug_met_fout( 'claim', self::leesbaar( $claim ), $waarden );
		}

		$mislukt = HDP_Garantie::bewaar_bijlagen( self::bestanden( 'fotos' ), 'claim', $claim['id'] );

		self::terug_met_melding(
			'garantie',
			sprintf( HDP_I18N::t( 'garantie_ok_claim' ), $claim['nummer'] ),
			$mislukt,
			add_query_arg( 'ticket', $claim['nummer'], home_url( '/garantie/' ) )
		);
	}

	private static function verwerk_bericht() {
		$nummer = self::tekst( 'ticket' );
		$tekst  = self::tekst( 'bericht' );

		$claim = HDP_Garantie::stuur_bericht( $nummer, $tekst );
		if ( is_wp_error( $claim ) ) {
			self::terug_met_fout( 'bericht', self::leesbaar( $claim ), array( 'bericht' => $tekst ), add_query_arg( 'ticket', $nummer, home_url( '/garantie/' ) ) );
		}

		self::terug_met_melding(
			'bericht',
			HDP_I18N::t( 'garantie_ok_bericht' ),
			array(),
			add_query_arg( 'ticket', $nummer, home_url( '/garantie/' ) )
		);
	}

	/* ---------------------------------------------------------------- */

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

		return $melding;
	}

	private static function terug_met_fout( $soort, $melding, $waarden = array(), $url = '' ) {
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
