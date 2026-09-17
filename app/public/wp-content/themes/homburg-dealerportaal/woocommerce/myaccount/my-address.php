<?php
/**
 * My Addresses — Homburg-versie van dit WooCommerce-template.
 * Vervangt WooCommerce's eigen twee-koloms-indeling (kale, ongestylede
 * "u-columns"-floats) door één rustige lijst met rijen, in dezelfde stijl
 * als bijv. "Recente bestellingen" op het dashboard.
 *
 * @see wc_get_template()
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hdp_customer_id = get_current_user_id();

if ( ! wc_ship_to_billing_address_only() && wc_shipping_enabled() ) {
	$hdp_adressen = apply_filters(
		'woocommerce_my_account_get_addresses',
		array(
			'billing'  => __( 'Billing address', 'woocommerce' ),
			'shipping' => __( 'Shipping address', 'woocommerce' ),
		),
		$hdp_customer_id
	);
} else {
	$hdp_adressen = apply_filters(
		'woocommerce_my_account_get_addresses',
		array(
			'billing' => __( 'Billing address', 'woocommerce' ),
		),
		$hdp_customer_id
	);
}
?>

<p class="hdp-adressen-intro">
	<?php echo apply_filters( 'woocommerce_my_account_my_address_description', esc_html__( 'The following addresses will be used on the checkout page by default.', 'woocommerce' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</p>

<div class="hdp-adressen-lijst">
	<?php foreach ( $hdp_adressen as $hdp_naam => $hdp_titel ) : ?>
		<?php $hdp_adres = wc_get_account_formatted_address( $hdp_naam ); ?>
		<div class="hdp-adres-rij">
			<?php HDP_Icons::render_icoon( 'adres' ); ?>
			<div class="hdp-adres-tekst">
				<h2><?php echo esc_html( $hdp_titel ); ?></h2>
				<?php if ( $hdp_adres ) : ?>
					<address class="hdp-adres-gevuld"><?php echo wp_kses_post( $hdp_adres ); ?></address>
				<?php else : ?>
					<address class="hdp-adres-leeg"><?php esc_html_e( 'You have not set up this type of address yet.', 'woocommerce' ); ?></address>
				<?php endif; ?>
				<?php do_action( 'woocommerce_my_account_after_my_address', $hdp_naam ); ?>
			</div>
			<a class="hdp-btn hdp-btn-klein <?php echo $hdp_adres ? 'hdp-btn-secundair' : ''; ?>" href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address', $hdp_naam ) ); ?>">
				<?php if ( $hdp_adres ) : ?>
					<?php echo HDP_Icons::svg_icoon( 'bewerken' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?>
					<?php
					printf(
						/* translators: %s: Address title */
						esc_html__( 'Edit %s', 'woocommerce' ),
						esc_html( $hdp_titel )
					);
					?>
				<?php else : ?>
					<?php echo HDP_Icons::svg_icoon( 'toevoegen' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?>
					<?php
					printf(
						/* translators: %s: Address title */
						esc_html__( 'Add %s', 'woocommerce' ),
						esc_html( $hdp_titel )
					);
					?>
				<?php endif; ?>
			</a>
		</div>
	<?php endforeach; ?>
</div>
