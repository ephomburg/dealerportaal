<?php
/**
 * "Opnieuw bestellen" — WooCommerce heeft dit knopje eigenlijk al ingebouwd
 * (op de losse orderpagina, en de eigenlijke "vul de winkelmand met deze
 * order"-logica erachter), maar:
 * 1. staat standaard alleen op de LOSSE orderpagina, niet in het
 *    bestellingenoverzicht zelf — hieronder ook daar toegevoegd;
 * 2. staat standaard alleen aan bij status "voltooid", terwijl dealers hier
 *    zonder onlinebetaling bestellen en dus meestal op "in behandeling" /
 *    "on hold" blijven staan — hieronder uitgebreid.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

add_filter( 'woocommerce_valid_order_statuses_for_order_again', 'homburg_wc_opnieuw_bestellen_statussen' );
function homburg_wc_opnieuw_bestellen_statussen( $statussen ) {
	$statussen[] = 'processing';
	$statussen[] = 'on-hold';
	return $statussen;
}

add_filter( 'woocommerce_my_account_my_orders_actions', 'homburg_wc_opnieuw_bestellen_in_overzicht', 10, 2 );
/**
 * Voegt "Opnieuw bestellen" ook toe als knop in de tabel met alle
 * bestellingen (My Account > Bestellingen), niet alleen op de losse
 * orderpagina — dezelfde URL/nonce als WooCommerce's eigen knop, dus
 * hergebruikt de bestaande (kern-)afhandeling volledig.
 */
function homburg_wc_opnieuw_bestellen_in_overzicht( $acties, $order ) {
	$geldige_statussen = apply_filters( 'woocommerce_valid_order_statuses_for_order_again', array( 'completed' ) );

	if ( $order->has_status( $geldige_statussen ) && is_user_logged_in() ) {
		$acties['again'] = array(
			'url'        => wp_nonce_url( add_query_arg( 'order_again', $order->get_id(), wc_get_cart_url() ), 'woocommerce-order_again' ),
			'name'       => __( 'Opnieuw bestellen', 'homburg-dealerportaal-theme' ),
			'aria-label' => sprintf(
				/* translators: %s: ordernummer */
				__( 'Bestelling %s opnieuw plaatsen', 'homburg-dealerportaal-theme' ),
				$order->get_order_number()
			),
		);
	}

	return $acties;
}
