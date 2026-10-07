<?php
/**
 * De webshop is alleen voor goedgekeurde dealers.
 *
 * De startpagina, downloads, content, garantie en bestelgeschiedenis gaan
 * allemaal door HDP_Roles::mag_portaal_zien(); de WooCommerce-kant nooit,
 * omdat die met eigen sjablonen werkt en niet door onze blokken loopt.
 * Daardoor kon iedereen zonder in te loggen de catalogus mét dealerprijzen
 * bekijken — en stonden die prijzen ook nog klaar voor zoekmachines.
 *
 * Het gaat om vier deuren naar dezelfde kamer:
 *
 *   1. de pagina's zelf (winkel, product, merk/categorie, winkelwagen,
 *      afrekenen, productzoekresultaten en hun feeds);
 *   2. de Store API (/wp-json/wc/store/...), die WooCommerce bewust
 *      openbaar maakt voor de winkelwagenblokken;
 *   3. /wp-json/wp/v2/product, de gewone REST-route van het producttype;
 *   4. de sitemap, die producten en productcategorieën aan Google geeft.
 *
 * De beheer-API /wc/v3 blijft ongemoeid: die vraagt al om een sleutel en
 * geeft zonder inloggegevens een 401. Daar hangt mogelijk een koppeling
 * aan (bijvoorbeeld voor de voorraad), en die hoort niet om te vallen door
 * een afscherming die voor bezoekers bedoeld is.
 *
 * @package Homburg_Dealerportaal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sluit de webshop af voor wie geen goedgekeurde dealer is.
 */
class HDP_Webshop_Toegang {

	/**
	 * REST-routes die bij de catalogus horen en dus achter de inlog moeten.
	 *
	 * Bewust een witte lijst van prefixen en geen "alles wat op product
	 * lijkt": een te brede regel hier legt stilletjes een koppeling plat.
	 */
	const AFGESCHERMDE_ROUTES = array(
		'/wc/store',
		'/wp/v2/product',
	);

	/**
	 * Haakt de vier afschermingen in.
	 */
	public static function init() {
		// Prioriteit 1: vóór alles wat de pagina verder opbouwt.
		add_action( 'template_redirect', array( __CLASS__, 'scherm_paginas_af' ), 1 );
		add_filter( 'rest_pre_dispatch', array( __CLASS__, 'scherm_rest_af' ), 10, 3 );
		add_filter( 'wp_sitemaps_post_types', array( __CLASS__, 'haal_producten_uit_sitemap' ) );
		add_filter( 'wp_sitemaps_taxonomies', array( __CLASS__, 'haal_producttaxonomieen_uit_sitemap' ) );
		add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'haal_gebruikers_uit_sitemap' ), 10, 2 );
	}

	/**
	 * Mag de huidige bezoeker de catalogus zien?
	 *
	 * Dezelfde controle als de rest van het portaal gebruikt, zodat een
	 * dealer die nog op goedkeuring wacht hier net zo min binnenkomt als
	 * op de downloadpagina.
	 *
	 * @return bool
	 */
	public static function mag_erin() {
		return HDP_Roles::mag_portaal_zien( get_current_user_id() );
	}

	/**
	 * Stuurt een bezoeker zonder toegang van een winkelpagina naar het
	 * inlogscherm op de startpagina.
	 */
	public static function scherm_paginas_af() {
		if ( ! self::is_winkelpagina() || self::mag_erin() ) {
			return;
		}

		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	/**
	 * Of dit verzoek een pagina uit de catalogus betreft.
	 *
	 * @return bool
	 */
	private static function is_winkelpagina() {
		if ( ! function_exists( 'is_woocommerce' ) ) {
			return false;
		}

		// is_woocommerce() dekt de winkel, losse producten en de merk- en
		// categoriepagina's, ook als die als feed worden opgevraagd.
		if ( is_woocommerce() || is_cart() || is_checkout() ) {
			return true;
		}

		// Een zoekopdracht die producten teruggeeft is net zo goed de
		// catalogus; die loopt niet via is_woocommerce().
		return is_search() && 'product' === get_query_var( 'post_type' );
	}

	/**
	 * Weigert de catalogusroutes van de REST-API.
	 *
	 * @param mixed            $resultaat Antwoord dat al klaarligt, of null.
	 * @param WP_REST_Server   $server    Ongebruikt.
	 * @param WP_REST_Request  $request   Het verzoek.
	 * @return mixed
	 */
	public static function scherm_rest_af( $resultaat, $server, $request ) {
		// Ligt er al een antwoord (of fout) klaar, dan niets doen.
		if ( null !== $resultaat ) {
			return $resultaat;
		}

		if ( ! self::is_afgeschermde_route( $request->get_route() ) || self::mag_erin() ) {
			return $resultaat;
		}

		return new WP_Error(
			'hdp_geen_toegang',
			__( 'Dit onderdeel van het dealerportaal is alleen voor ingelogde dealers.', 'homburg-dealerportaal' ),
			array( 'status' => is_user_logged_in() ? 403 : 401 )
		);
	}

	/**
	 * Of een REST-route bij de catalogus hoort.
	 *
	 * @param string $route Bijvoorbeeld "/wc/store/v1/products".
	 * @return bool
	 */
	public static function is_afgeschermde_route( $route ) {
		foreach ( self::AFGESCHERMDE_ROUTES as $prefix ) {
			if ( 0 === strpos( $route, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Houdt de gebruikerslijst uit de sitemap.
	 *
	 * WordPress geeft daarin /author/<naam>/ prijs, dus de inlognaam van wie
	 * hier publiceert. Op een besloten dealerportaal is dat niets voor
	 * buiten, en auteursarchieven hebben hier sowieso geen functie.
	 *
	 * @param WP_Sitemaps_Provider $provider De aanbieder.
	 * @param string               $naam     'posts', 'taxonomies' of 'users'.
	 * @return WP_Sitemaps_Provider|false
	 */
	public static function haal_gebruikers_uit_sitemap( $provider, $naam ) {
		return 'users' === $naam ? false : $provider;
	}

	/**
	 * Houdt producten uit de sitemap.
	 *
	 * Zonder dit geeft WordPress elke productpagina aan Google, en dan
	 * staan de dealerprijzen in de zoekresultaten ook al is de pagina zelf
	 * inmiddels afgeschermd.
	 *
	 * @param array $types Posttypes in de sitemap.
	 * @return array
	 */
	public static function haal_producten_uit_sitemap( $types ) {
		unset( $types['product'] );

		return $types;
	}

	/**
	 * Idem voor de merk- en categorieoverzichten van de winkel.
	 *
	 * @param array $taxonomieen Taxonomieën in de sitemap.
	 * @return array
	 */
	public static function haal_producttaxonomieen_uit_sitemap( $taxonomieen ) {
		unset( $taxonomieen['product_cat'], $taxonomieen['product_tag'], $taxonomieen['product_brand'] );

		return $taxonomieen;
	}
}
