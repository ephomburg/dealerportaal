<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Praat met Supabase.
 *
 * Bewust geen "de Supabase-koppeling" maar koppelingen met een naam: Homburg
 * heeft twee Supabase-projecten (één voor de Homburg App, één voor het
 * portaal zelf) en elke module vraagt om degene die hij nodig heeft. Komt er
 * later een derde bij, dan is dat een regel erbij in VERBINDINGEN.
 *
 * Alle aanroepen gebeuren server-side met de secret key (voorheen
 * service_role). Die sleutel geeft toegang tot álle gegevens en mag dus
 * nooit in iets terechtkomen dat een bezoeker in zijn browser kan bekijken —
 * er gaat hier niets naar JavaScript. Wie wat mag zien bepaalt WordPress,
 * dat immers weet wie er is ingelogd; zie HDP_Garantie.
 *
 * De sleutels staan in wp-config.php (buiten git, niet in een deploy, niet in
 * een database-export). Voor het gemak op een testomgeving mag het ook via
 * Instellingen > Dealerportaal; de constante wint altijd.
 */
class HDP_Supabase {

	/** De Supabase van de Homburg App — daar staat de garantieadministratie. */
	const APP = 'app';

	/** De Supabase van het dealerportaal zelf. Nog niet in gebruik. */
	const PORTAAL = 'portaal';

	/**
	 * Per verbinding: welke constante in wp-config.php, en welke
	 * instelling als terugval.
	 */
	private static function verbindingen() {
		return array(
			self::APP     => array(
				'url_constante'     => 'HDP_SUPABASE_APP_URL',
				'sleutel_constante' => 'HDP_SUPABASE_APP_KEY',
				'url_instelling'    => 'supabase_app_url',
				'sleutel_instelling' => 'supabase_app_key',
			),
			self::PORTAAL => array(
				'url_constante'     => 'HDP_SUPABASE_PORTAAL_URL',
				'sleutel_constante' => 'HDP_SUPABASE_PORTAAL_KEY',
				'url_instelling'    => 'supabase_portaal_url',
				'sleutel_instelling' => 'supabase_portaal_key',
			),
		);
	}

	public static function url( $verbinding ) {
		return self::waarde( $verbinding, 'url' );
	}

	private static function sleutel( $verbinding ) {
		return self::waarde( $verbinding, 'sleutel' );
	}

	private static function waarde( $verbinding, $soort ) {
		$opzet = self::verbindingen();
		if ( ! isset( $opzet[ $verbinding ] ) ) {
			return '';
		}

		$constante = $opzet[ $verbinding ][ $soort . '_constante' ];
		if ( defined( $constante ) && constant( $constante ) ) {
			return untrailingslashit( (string) constant( $constante ) );
		}

		return untrailingslashit( (string) HDP_Settings::get( $opzet[ $verbinding ][ $soort . '_instelling' ] ) );
	}

	/** Is deze verbinding ingesteld? Zo niet, dan hoort de pagina dat netjes te melden. */
	public static function beschikbaar( $verbinding ) {
		return '' !== self::url( $verbinding ) && '' !== self::sleutel( $verbinding );
	}

	/**
	 * Haalt rijen op.
	 *
	 * @param string $verbinding Een van de constanten hierboven.
	 * @param string $bron       Tabel- of viewnaam.
	 * @param array  $query      PostgREST-parameters, bijv.
	 *                           array( 'select' => '*', 'dealer_id' => 'eq.37' ).
	 * @return array|WP_Error
	 */
	public static function selecteer( $verbinding, $bron, $query = array() ) {
		return self::aanroep( $verbinding, 'GET', $bron, $query );
	}

	/** Voegt rijen toe en geeft ze terug zoals de database ze heeft opgeslagen. */
	public static function voeg_toe( $verbinding, $tabel, $rijen ) {
		return self::aanroep( $verbinding, 'POST', $tabel, array(), $rijen );
	}

	/** Werkt rijen bij die aan $query voldoen. */
	public static function werk_bij( $verbinding, $tabel, $query, $waarden ) {
		return self::aanroep( $verbinding, 'PATCH', $tabel, $query, $waarden );
	}

	/**
	 * Voegt toe, of werkt bij als de rij al bestaat (op de primaire sleutel).
	 * Gebruikt voor de dealers-tabel: die houden we bij elk bezoek actueel.
	 */
	public static function zet_klaar( $verbinding, $tabel, $rijen ) {
		return self::aanroep(
			$verbinding,
			'POST',
			$tabel,
			array(),
			$rijen,
			array( 'Prefer: resolution=merge-duplicates,return=representation' )
		);
	}

	/**
	 * Zet een bestand in een opslagbak (Supabase Storage).
	 *
	 * De bak 'garantie-bijlagen' staat op privé: foto's en facturen van
	 * dealers zijn niet openbaar. De app opent ze met tijdelijke links.
	 *
	 * @param string $pad    Pad binnen de bak, zonder de baknaam.
	 * @param string $inhoud De ruwe bestandsinhoud.
	 * @return true|WP_Error
	 */
	public static function upload( $verbinding, $bak, $pad, $inhoud, $mime ) {
		if ( ! self::beschikbaar( $verbinding ) ) {
			return new WP_Error( 'hdp_supabase_niet_ingesteld', 'Supabase-verbinding "' . $verbinding . '" is niet ingesteld.' );
		}

		$sleutel  = self::sleutel( $verbinding );
		$antwoord = wp_remote_post(
			self::url( $verbinding ) . '/storage/v1/object/' . $bak . '/' . ltrim( $pad, '/' ),
			array(
				'headers' => array(
					'apikey'        => $sleutel,
					'Authorization' => 'Bearer ' . $sleutel,
					'Content-Type'  => $mime,
					// Niet overschrijven: elk bestand krijgt een eigen pad, dus
					// een botsing betekent dat er iets anders mis is.
					'x-upsert'      => 'false',
				),
				'body'    => $inhoud,
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $antwoord ) ) {
			HDP_Log::schrijf( 'Supabase-opslag onbereikbaar: ' . $antwoord->get_error_message(), 'WAARSCHUWING' );
			return $antwoord;
		}

		$code = (int) wp_remote_retrieve_response_code( $antwoord );
		if ( $code < 200 || $code >= 300 ) {
			$melding = self::melding_uit( wp_remote_retrieve_body( $antwoord ) );
			HDP_Log::schrijf( 'Supabase-opslag gaf HTTP ' . $code . ': ' . $melding, 'WAARSCHUWING' );
			return new WP_Error( 'hdp_supabase_opslag', $melding );
		}

		return true;
	}

	/**
	 * De daadwerkelijke aanroep.
	 *
	 * Een storing bij Supabase mag nooit de rest van het portaal meeslepen:
	 * dit geeft dan een WP_Error terug en schrijft een regel in het logboek,
	 * zodat de garantiepagina een melding kan tonen terwijl de webshop en de
	 * downloads gewoon doorwerken.
	 */
	private static function aanroep( $verbinding, $methode, $bron, $query = array(), $body = null, $extra_headers = array() ) {
		if ( ! self::beschikbaar( $verbinding ) ) {
			return new WP_Error( 'hdp_supabase_niet_ingesteld', 'Supabase-verbinding "' . $verbinding . '" is niet ingesteld.' );
		}

		$url = self::url( $verbinding ) . '/rest/v1/' . ltrim( $bron, '/' );
		if ( $query ) {
			$url = add_query_arg( array_map( 'rawurlencode', $query ), $url );
		}

		$sleutel = self::sleutel( $verbinding );
		$headers = array(
			'apikey'        => $sleutel,
			'Authorization' => 'Bearer ' . $sleutel,
			'Content-Type'  => 'application/json',
			'Prefer'        => 'return=representation',
		);
		foreach ( $extra_headers as $regel ) {
			list( $naam, $waarde ) = array_map( 'trim', explode( ':', $regel, 2 ) );
			$headers[ $naam ]      = $waarde;
		}

		$args = array(
			'method'  => $methode,
			'headers' => $headers,
			'timeout' => 15,
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$antwoord = wp_remote_request( $url, $args );

		if ( is_wp_error( $antwoord ) ) {
			HDP_Log::schrijf( 'Supabase (' . $verbinding . ') onbereikbaar: ' . $antwoord->get_error_message(), 'WAARSCHUWING' );
			return $antwoord;
		}

		$code  = (int) wp_remote_retrieve_response_code( $antwoord );
		$tekst = wp_remote_retrieve_body( $antwoord );

		if ( $code < 200 || $code >= 300 ) {
			// De database weigert bewust van alles (een claim op een afgewezen
			// machine, een status zonder toelichting). Die melding is
			// bruikbaar, dus die gaat mee terug in plaats van verloren.
			$melding = self::melding_uit( $tekst );
			HDP_Log::schrijf( 'Supabase (' . $verbinding . ') ' . $methode . ' ' . $bron . ' gaf HTTP ' . $code . ': ' . $melding, 'WAARSCHUWING' );
			return new WP_Error(
				'hdp_supabase_fout',
				$melding,
				array(
					'status'  => $code,
					// De foutcode van Postgres. Een bewuste weigering uit de
					// database (RAISE) heeft P0001; een technische fout iets
					// anders. Zo kan de weergave het verschil zien.
					'pg_code' => self::code_uit( $tekst ),
				)
			);
		}

		$data = json_decode( $tekst, true );

		return is_array( $data ) ? $data : array();
	}

	/** De Postgres-foutcode uit een Supabase-antwoord, of '' als die er niet in staat. */
	private static function code_uit( $tekst ) {
		$data = json_decode( $tekst, true );
		return is_array( $data ) && isset( $data['code'] ) ? (string) $data['code'] : '';
	}

	/**
	 * Tijdelijke links naar bestanden in een privé opslagbak, in één ronde.
	 *
	 * De bak met foto's en facturen is niet openbaar. Wie een bestand mag
	 * zien bepaalt WordPress (zie HDP_Garantie::claim_voor_dealer); daarna
	 * krijgt alleen die bezoeker een link die na $seconden vanzelf vervalt.
	 *
	 * @param string   $bak      De opslagbak.
	 * @param string[] $paden    Paden binnen de bak.
	 * @param int      $seconden Hoelang de links geldig blijven.
	 * @return array Pad => volledige URL. Een pad zonder link ontbreekt; bij
	 *               een storing is de lijst leeg.
	 */
	public static function tijdelijke_links( $verbinding, $bak, $paden, $seconden = 3600 ) {
		$paden = array_values( array_filter( array_map( 'strval', (array) $paden ) ) );
		if ( ! $paden || ! self::beschikbaar( $verbinding ) ) {
			return array();
		}

		$sleutel  = self::sleutel( $verbinding );
		$antwoord = wp_remote_post(
			self::url( $verbinding ) . '/storage/v1/object/sign/' . $bak,
			array(
				'headers' => array(
					'apikey'        => $sleutel,
					'Authorization' => 'Bearer ' . $sleutel,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'expiresIn' => (int) $seconden,
						'paths'     => $paden,
					)
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $antwoord ) ) {
			HDP_Log::schrijf( 'Supabase-opslag onbereikbaar bij tijdelijke links: ' . $antwoord->get_error_message(), 'WAARSCHUWING' );
			return array();
		}

		$data = json_decode( wp_remote_retrieve_body( $antwoord ), true );
		if ( (int) wp_remote_retrieve_response_code( $antwoord ) >= 300 || ! is_array( $data ) ) {
			return array();
		}

		$links = array();
		foreach ( $data as $rij ) {
			if ( empty( $rij['path'] ) || empty( $rij['signedURL'] ) ) {
				continue;
			}
			// Supabase geeft de link relatief aan /storage/v1 terug.
			$links[ (string) $rij['path'] ] = self::url( $verbinding ) . '/storage/v1' . $rij['signedURL'];
		}

		return $links;
	}

	/** Haalt de leesbare foutmelding uit een Supabase-antwoord. */
	private static function melding_uit( $tekst ) {
		$data = json_decode( $tekst, true );
		if ( is_array( $data ) && ! empty( $data['message'] ) ) {
			return (string) $data['message'];
		}
		return '' !== trim( (string) $tekst ) ? (string) $tekst : 'Onbekende fout.';
	}
}
