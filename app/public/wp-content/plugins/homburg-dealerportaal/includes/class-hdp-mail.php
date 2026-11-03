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

	/** Het adres van de webshopmails, en waar antwoorden daarop heen gaan. */
	const WEBSHOP_ADRES = 'info@homburg-holland.com';

	public static function init() {
		add_filter( 'wp_mail_from', array( __CLASS__, 'adres' ) );
		add_filter( 'wp_mail_from_name', array( __CLASS__, 'naam' ) );
		add_filter( 'wp_mail', array( __CLASS__, 'antwoordadres' ) );
		add_filter( 'woocommerce_email_from_address', array( __CLASS__, 'webshop_adres' ) );
		add_filter( 'woocommerce_email_from_name', array( __CLASS__, 'webshop_naam' ) );
		add_filter( 'woocommerce_email_headers', array( __CLASS__, 'webshop_kop' ), 10, 2 );
	}

	/**
	 * Is de overstap gemaakt? Instellingen > Dealerportaal, "Webshopmails vanaf
	 * info@". Pas aanzetten als deze server namens homburg-holland.com mag mailen;
	 * tot die tijd zouden ontvangers de mail weigeren (zie boven).
	 */
	public static function webshop_via_info() {
		return '1' === HDP_Settings::get( 'webshop_via_info' );
	}

	public static function webshop_adres() {
		return self::webshop_via_info() ? self::WEBSHOP_ADRES : self::adres( 'wordpress@' );
	}

	public static function webshop_naam() {
		return self::NAAM;
	}

	/**
	 * Een dealer die op een webshopmail antwoordt, komt altijd bij info@ uit. De
	 * mails aan het team over een bestelling houden de dealer als antwoordadres;
	 * WooCommerce zet dat zelf, op zijn eigen naam.
	 */
	public static function webshop_kop( $kop, $mail_id ) {
		if ( in_array( $mail_id, array( 'new_order', 'cancelled_order', 'failed_order' ), true ) ) {
			return $kop;
		}
		$zonder = preg_replace( '/^reply-to:.*\r?\n?/im', '', (string) $kop );
		return rtrim( $zonder, "\r\n" ) . "\r\nReply-to: " . self::NAAM . ' <' . self::WEBSHOP_ADRES . ">\r\n";
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
