<?php
/**
 * De bestelstroom voor dealers: winkelwagen, afrekenen, de bestelling.
 *
 * - Artikelnummers overal waar een bestelling staat (dealers denken in
 *   nummers, niet in omschrijvingen).
 * - Een veld "Jouw referentie" bij afrekenen (inkoopnummer of eindklant).
 * - De leveringsvoorwaarden als link bij afrekenen, in de taal van de dealer.
 * - Bestellen op rekening, met levering volgens afspraak: geen online
 *   betaling, Homburg factureert achteraf.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

/** De sleutel van het referentieveld (WooCommerce' eigen extra-veld-API). */
const HOMBURG_WC_REFERENTIE = 'homburg/referentie';

/** De vertaling, als de plugin er is; anders de Nederlandse tekst. */
function homburg_wc_tekst( $sleutel, $nl ) {
	return class_exists( 'HDP_I18N' ) ? HDP_I18N::t( $sleutel ) : $nl;
}

/* --- Artikelnummers ---------------------------------------------------- */

add_filter( 'woocommerce_get_item_data', 'homburg_wc_artikelnummer_in_wagen', 10, 2 );
/**
 * Het artikelnummer onder de naam in de winkelwagen en het besteloverzicht
 * bij afrekenen. Daar stond alleen de omschrijving.
 */
function homburg_wc_artikelnummer_in_wagen( $gegevens, $regel ) {
	$product = isset( $regel['data'] ) ? $regel['data'] : null;
	if ( $product instanceof WC_Product && $product->get_sku() ) {
		$gegevens[] = array(
			'key'   => homburg_wc_tekst( 'wc_artikelnummer', 'Artikelnummer' ),
			'value' => $product->get_sku(),
		);
	}
	return $gegevens;
}

add_action( 'woocommerce_order_item_meta_start', 'homburg_wc_artikelnummer_bij_bestelling', 10, 3 );
/** Hetzelfde bij een geplaatste bestelling (Mijn account > Bestelling). */
function homburg_wc_artikelnummer_bij_bestelling( $item_id, $item, $order ) {
	$product = is_callable( array( $item, 'get_product' ) ) ? $item->get_product() : null;
	if ( $product && $product->get_sku() ) {
		echo '<span class="hdp-wc-regel-sku">' . esc_html( homburg_wc_tekst( 'wc_artikelnummer', 'Artikelnummer' ) . ': ' . $product->get_sku() ) . '</span>';
	}
}

add_filter( 'woocommerce_email_order_items_args', 'homburg_wc_artikelnummer_in_mail' );
/** En in de e-mails: WooCommerce kan het artikelnummer daar zelf tonen, het staat alleen uit. */
function homburg_wc_artikelnummer_in_mail( $args ) {
	$args['show_sku'] = true;
	return $args;
}

/* --- Jouw referentie ---------------------------------------------------- */

add_action( 'woocommerce_init', 'homburg_wc_referentie_veld' );
/**
 * "Jouw referentie" bij afrekenen: een eigen inkoopnummer of de naam van de
 * eindklant, zodat de dealer de bestelling in zijn eigen administratie
 * terugvindt. Via WooCommerce' eigen API voor extra velden: het komt dan
 * vanzelf terug in de bevestiging, de e-mail, bij de bestelling en in het
 * beheer.
 */
function homburg_wc_referentie_veld() {
	if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
		return;
	}

	woocommerce_register_additional_checkout_field(
		array(
			'id'            => HOMBURG_WC_REFERENTIE,
			'label'         => homburg_wc_tekst( 'wc_referentie', 'Jouw referentie' ),
			'optionalLabel' => homburg_wc_tekst( 'wc_referentie_optioneel', 'Jouw referentie (optioneel)' ),
			'location'      => 'order',
			'type'          => 'text',
			'attributes'    => array(
				'maxLength' => 60,
			),
		)
	);
}

/**
 * De referentie bij een bestelling, of '' als die er niet is.
 *
 * @param WC_Order $order De bestelling.
 */
function homburg_wc_referentie( $order ) {
	// WooCommerce bewaart een extra veld van de plek "order" als metagegeven
	// met dit voorvoegsel (CheckoutFields::OTHER_FIELDS_PREFIX).
	return $order instanceof WC_Order ? (string) $order->get_meta( '_wc_other/' . HOMBURG_WC_REFERENTIE ) : '';
}

/* --- Leveringsvoorwaarden ------------------------------------------------ */

/** De Metaalunievoorwaarden van Homburg, per taal. */
function homburg_wc_voorwaarden_url() {
	$frans = class_exists( 'HDP_I18N' ) && HDP_I18N::is_frans();

	return $frans
		? 'https://www.homburg-holland.com/wp-content/uploads/2025/01/Metaalunievoorwaarden-2025-Frans.pdf'
		: 'https://www.homburg-holland.com/wp-content/uploads/2025/01/Metaalunievoorwaarden-2025-Nederlands.pdf';
}

add_action( 'wp_enqueue_scripts', 'homburg_wc_voorwaarden_link', 20 );
/**
 * Bij afrekenen stond "je gaat akkoord met onze Algemene voorwaarden" zonder
 * link: er was geen voorwaardenpagina ingesteld. Het afrekenblok haalt die
 * link uit zijn instellingen in de browser; hier krijgt het de PDF in de
 * taal van de dealer. Geen aparte pagina nodig die naar een PDF verwijst.
 */
function homburg_wc_voorwaarden_link() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || ! wp_script_is( 'wc-settings', 'registered' ) ) {
		return;
	}

	$voorwaarden = array(
		'id'        => 1,
		'title'     => homburg_wc_tekst( 'wc_voorwaarden', 'Algemene voorwaarden' ),
		'permalink' => homburg_wc_voorwaarden_url(),
	);

	wp_add_inline_script(
		'wc-settings',
		'if ( window.wcSettings && window.wcSettings.storePages ) { window.wcSettings.storePages.terms = ' . wp_json_encode( $voorwaarden ) . '; }',
		'after'
	);
}

/* --- Afleveradres ------------------------------------------------------- */

add_action( 'template_redirect', 'homburg_wc_afleveradres_vooraf' );
/**
 * Een dealer laat meestal op zijn eigen adres leveren. Heeft hij nog geen
 * afleveradres, dan begon afrekenen met een leeg adresformulier dat eerst
 * ingevuld moest worden. Nu staat daar zijn factuuradres al, met "zelfde
 * adres voor facturering" aangevinkt; leveren bij een eindklant blijft
 * gewoon mogelijk door het aan te passen.
 */
function homburg_wc_afleveradres_vooraf() {
	if ( ! is_checkout() || ! is_user_logged_in() || ! WC()->customer ) {
		return;
	}

	$klant = WC()->customer;
	if ( $klant->get_shipping_address_1() || ! $klant->get_billing_address_1() ) {
		return;
	}

	foreach ( array( 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'postcode', 'country', 'state' ) as $veld ) {
		$klant->{"set_shipping_$veld"}( $klant->{"get_billing_$veld"}() );
	}
	$klant->save();
}

/* --- Op rekening, levering volgens afspraak ------------------------------ */

/**
 * Bestellen op rekening gebruikt WooCommerce' ingebouwde betaalmethode
 * "cheque" — die is er al en werkt in het afrekenblok — onder een eigen naam
 * en in de taal van de dealer. Zonder online betaling, dus de bestelling
 * gaat meteen "in behandeling" in plaats van "in de wacht" (zoals bij een
 * cheque die nog moet binnenkomen).
 */
add_filter( 'option_woocommerce_cheque_settings', 'homburg_wc_op_rekening_teksten' );
function homburg_wc_op_rekening_teksten( $instellingen ) {
	if ( ! is_array( $instellingen ) || is_admin() ) {
		return $instellingen;
	}
	$instellingen['title']        = homburg_wc_tekst( 'wc_op_rekening', 'Op rekening' );
	$instellingen['description']  = homburg_wc_tekst( 'wc_op_rekening_uitleg', 'Je ontvangt de factuur van Homburg. Betalen volgens je gebruikelijke afspraken.' );
	$instellingen['instructions'] = '';
	return $instellingen;
}

add_filter( 'woocommerce_cheque_process_payment_order_status', 'homburg_wc_op_rekening_status' );
function homburg_wc_op_rekening_status() {
	return 'processing';
}

add_filter( 'woocommerce_package_rates', 'homburg_wc_levering_teksten', 20 );
/**
 * De verzendregel heet in de instellingen "Levering volgens afspraak"; hier
 * in de taal van de dealer.
 */
function homburg_wc_levering_teksten( $tarieven ) {
	foreach ( $tarieven as $tarief ) {
		if ( 'flat_rate' === $tarief->get_method_id() && 0.0 === (float) $tarief->get_cost() ) {
			$tarief->set_label( homburg_wc_tekst( 'wc_levering_afspraak', 'Levering volgens afspraak' ) );
		}
	}
	return $tarieven;
}
