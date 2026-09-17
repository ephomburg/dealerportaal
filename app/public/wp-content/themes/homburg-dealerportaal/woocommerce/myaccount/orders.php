<?php
/**
 * Orders — Homburg-versie van dit WooCommerce-template.
 *
 * Er bestonden twee losse "bekijk je bestellingen"-schermen: dit kale
 * standaard-WooCommerce-tabblad (alleen een tabel, geen zoeken/filteren)
 * en de veel uitgebreidere Bestelgeschiedenis-pagina van de plugin
 * (HDP_Bestelgeschiedenis_Render, met zoeken op ordernummer/naam, filteren
 * op status en paginering). Dat was verwarrend — nu hergebruikt "Mijn
 * account" > "Bestellingen" gewoon diezelfde, rijkere lijst.
 *
 * @see wc_get_template()
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_before_account_orders', $has_orders );

echo HDP_Bestelgeschiedenis_Render::render_bestellingen_lijst(); // phpcs:ignore WordPress.Security.EscapeOutput -- render_bestellingen_lijst() escaped elk veld al zelf.

do_action( 'woocommerce_after_account_orders', $has_orders );
