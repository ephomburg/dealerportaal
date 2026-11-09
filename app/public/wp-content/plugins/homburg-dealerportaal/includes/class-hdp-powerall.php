<?php
/**
 * Verbinding met PowerAll Connect, de API van het ERP van Homburg.
 *
 * Let op: dit is de echte administratie, er is geen testomgeving. Lezen kan
 * altijd (GET). Versturen (POST) kan alleen naar de vaste lijst in
 * POST_TOEGESTAAN; een echte order gaat alleen via HDP_PowerAll_Orders, en dan
 * alleen als die op "Aan" staat.
 *
 * De toegang staat in wp-config.php (HDP_POWERALL_URL, HDP_POWERALL_TENANT en
 * HDP_POWERALL_TOKEN) en nooit in de code of de database. Ontbreekt die, dan
 * doet alles wat PowerAll nodig heeft gewoon niets.
 *
 * @package Homburg_Dealerportaal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HDP_PowerAll {

	const TIMEOUT = 30;

	/** Is de toegang ingesteld? */
	public static function beschikbaar() {
		return defined( 'HDP_POWERALL_URL' ) && defined( 'HDP_POWERALL_TENANT' ) && defined( 'HDP_POWERALL_TOKEN' )
			&& '' !== (string) HDP_POWERALL_URL && '' !== (string) HDP_POWERALL_TOKEN;
	}

	/**
	 * Haalt gegevens op.
	 *
	 * @param string $pad   Bijv. 'relations' of 'relations/23524'.
	 * @param array  $query Bijv. array( 'filters' => 'Active:"True"', 'limit' => 200 ).
	 * @return mixed|WP_Error Het onderdeel "Data" uit het antwoord.
	 */
	public static function get( $pad, $query = array() ) {
		return self::vraag( 'GET', $pad, $query );
	}

	/**
	 * Alleen voor de adressen hieronder: twee die niets veranderen (prijzen
	 * berekenen, een order proefdraaien) en het echte versturen van een order.
	 */
	const POST_TOEGESTAAN = array( 'products/prices', 'sales-orders/simulate', 'sales-orders' );

	/**
	 * @param string $pad  Een van POST_TOEGESTAAN.
	 * @param array  $body Wordt als JSON verstuurd.
	 * @return mixed|WP_Error Het onderdeel "Data" uit het antwoord.
	 */
	public static function post( $pad, array $body ) {
		if ( ! in_array( $pad, self::POST_TOEGESTAAN, true ) ) {
			return new WP_Error( 'hdp_powerall_geweigerd', 'Dit adres is niet toegestaan: ' . $pad );
		}
		return self::vraag( 'POST', $pad, array(), $body );
	}

	private static function vraag( $methode, $pad, $query = array(), $body = null ) {
		if ( ! self::beschikbaar() ) {
			return new WP_Error( 'hdp_powerall_uit', 'De toegang tot PowerAll is niet ingesteld.' );
		}

		$url = untrailingslashit( (string) HDP_POWERALL_URL ) . '/' . ltrim( $pad, '/' );
		if ( $query ) {
			$url .= '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		}

		$args = array(
			'method'  => $methode,
			'timeout' => self::TIMEOUT,
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( HDP_POWERALL_TENANT . ':' . HDP_POWERALL_TOKEN ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Basic Auth.
				'Accept'        => 'application/json',
			),
		);
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}

		$antwoord = wp_remote_request( $url, $args );

		if ( is_wp_error( $antwoord ) ) {
			HDP_Log::schrijf( 'PowerAll niet bereikbaar (' . $pad . '): ' . $antwoord->get_error_message(), 'WAARSCHUWING' );
			return new WP_Error( 'hdp_powerall_onbereikbaar', 'PowerAll is niet bereikbaar: ' . $antwoord->get_error_message() );
		}

		$status = (int) wp_remote_retrieve_response_code( $antwoord );
		$data   = json_decode( (string) wp_remote_retrieve_body( $antwoord ), true );

		if ( 200 !== $status || ! is_array( $data ) ) {
			$melding = is_array( $data ) && ! empty( $data['Error']['Message'] ) ? (string) $data['Error']['Message'] : 'antwoordcode ' . $status;
			HDP_Log::schrijf( 'PowerAll gaf een fout (' . $pad . '): ' . $melding, 'WAARSCHUWING' );
			return new WP_Error( 'hdp_powerall_fout', 'PowerAll gaf een fout: ' . $melding, array( 'status' => $status ) );
		}

		return isset( $data['Data'] ) ? $data['Data'] : null;
	}
}
