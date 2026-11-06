<?php
/**
 * Verbinding met PowerAll Connect, de API van het ERP van Homburg.
 *
 * Let op: dit is de echte administratie, er is geen testomgeving. Deze class
 * kan daarom alleen lezen (GET). Iets wegschrijven, zoals een order, komt er
 * pas bij als daar een afspraak over is.
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
		if ( ! self::beschikbaar() ) {
			return new WP_Error( 'hdp_powerall_uit', 'De toegang tot PowerAll is niet ingesteld.' );
		}

		$url = untrailingslashit( (string) HDP_POWERALL_URL ) . '/' . ltrim( $pad, '/' );
		if ( $query ) {
			$url .= '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
		}

		$antwoord = wp_remote_get(
			$url,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array(
					'Authorization' => 'Basic ' . base64_encode( HDP_POWERALL_TENANT . ':' . HDP_POWERALL_TOKEN ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Basic Auth.
					'Accept'        => 'application/json',
				),
			)
		);

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
