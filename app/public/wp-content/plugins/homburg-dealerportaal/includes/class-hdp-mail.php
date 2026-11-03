<?php
/**
 * De afzender van de mails van het portaal.
 *
 * WordPress mailt standaard als "WordPress <wordpress@...>". Het adres blijft
 * bewust op het domein van deze site: homburg-holland.com staat alleen mail toe
 * van zijn eigen servers en laat de rest weigeren (DMARC p=reject). Een adres op
 * dat domein zou hier dus niet aankomen, en dat is aan WordPress niet te zien.
 * Antwoorden komen wel bij Homburg binnen.
 *
 * Alleen de standaardafzender wordt vervangen: wie zelf een afzender of
 * antwoordadres meegeeft, zoals WooCommerce of de teammail over een training,
 * houdt die.
 *
 * @package Homburg_Dealerportaal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stelt de standaardafzender in.
 */
class HDP_Mail {

	const NAAM           = 'Homburg';
	const ANTWOORD_ADRES = 'marketing@homburg-holland.com';

	public static function init() {
		add_filter( 'wp_mail_from', array( __CLASS__, 'adres' ) );
		add_filter( 'wp_mail_from_name', array( __CLASS__, 'naam' ) );
		add_filter( 'wp_mail', array( __CLASS__, 'antwoordadres' ) );
	}

	public static function adres( $adres ) {
		if ( 0 !== strpos( (string) $adres, 'wordpress@' ) ) {
			return $adres;
		}
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		return 'noreply@' . preg_replace( '/^www\./', '', $host );
	}

	public static function naam( $naam ) {
		return 'WordPress' === $naam ? self::NAAM : $naam;
	}

	/** Zet het antwoordadres erbij, tenzij de mail al een antwoordadres of eigen afzender heeft. */
	public static function antwoordadres( $mail ) {
		$headers = isset( $mail['headers'] ) ? $mail['headers'] : array();
		$regels  = is_array( $headers ) ? $headers : explode( "\n", str_replace( "\r\n", "\n", (string) $headers ) );
		foreach ( $regels as $regel ) {
			if ( preg_match( '/^\s*(reply-to|from)\s*:/i', (string) $regel ) ) {
				return $mail;
			}
		}
		$regels[]        = 'Reply-To: ' . self::ANTWOORD_ADRES;
		$mail['headers'] = $regels;
		return $mail;
	}
}
