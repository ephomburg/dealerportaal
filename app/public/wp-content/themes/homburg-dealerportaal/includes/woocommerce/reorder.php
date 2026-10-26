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
	// Op de losse bestelpagina zet WooCommerce zelf al een knop "Opnieuw
	// bestellen" onder de tabel; met deze erbij stond hij er twee keer.
	if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'view-order' ) ) {
		return $acties;
	}

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

add_filter( 'woocommerce_order_get_formatted_billing_address', 'homburg_wc_factuuradres_terugval', 10, 3 );
/**
 * Een bestelling zonder eigen factuuradres toonde "N/B". Dan liever het
 * factuuradres dat nu bij het dealeraccount staat: dat is waar de factuur
 * naartoe gaat. (Staat daar ook niets, dan blijft WooCommerce' eigen
 * "N/B" staan: een leeg resultaat vervangt WooCommerce daar zelf door.)
 *
 * @param string   $adres Het opgemaakte adres, of de "leeg"-tekst.
 * @param array    $ruw   De losse adresvelden van de bestelling.
 * @param WC_Order $order De bestelling.
 */
function homburg_wc_factuuradres_terugval( $adres, $ruw, $order ) {
	if ( array_filter( array_intersect_key( (array) $ruw, array_flip( array( 'first_name', 'last_name', 'company', 'address_1', 'city' ) ) ) ) ) {
		return $adres;
	}

	$klant = $order->get_customer_id() ? new WC_Customer( $order->get_customer_id() ) : null;
	if ( ! $klant || ! $klant->get_billing_address_1() ) {
		return $adres;
	}

	return WC()->countries->get_formatted_address(
		array(
			'first_name' => $klant->get_billing_first_name(),
			'last_name'  => $klant->get_billing_last_name(),
			'company'    => $klant->get_billing_company(),
			'address_1'  => $klant->get_billing_address_1(),
			'address_2'  => $klant->get_billing_address_2(),
			'city'       => $klant->get_billing_city(),
			'postcode'   => $klant->get_billing_postcode(),
			'country'    => $klant->get_billing_country(),
		)
	);
}
