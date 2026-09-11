<?php
/**
 * Productkaart in lussen (shop, categorie, zoekresultaten, gerelateerd).
 *
 * Eigen opzet volgens het ontwerpvoorstel: vierkant beeldvlak (met eigen
 * placeholder), merk, titel, artikelnummer, prijs, en één volle-breedte
 * bestelknop. Geen aparte "bekijk product"-knop, geen voorraadweergave.
 *
 * Overschrijft woocommerce/templates/content-product.php (v9.4.0).
 *
 * @package Homburg_Dealerportaal
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! is_a( $product, WC_Product::class ) || ! $product->is_visible() ) {
	return;
}

$permalink = get_permalink( $product->get_id() );
$sku       = $product->get_sku();
$image_id  = $product->get_image_id();
$brands    = get_the_terms( $product->get_id(), 'product_brand' );
$brand     = ( $brands && ! is_wp_error( $brands ) ) ? $brands[0]->name : '';
?>
<li <?php wc_product_class( 'hdp-card', $product ); ?>>
	<a class="hdp-card__media" href="<?php echo esc_url( $permalink ); ?>" aria-hidden="true" tabindex="-1">
		<?php
		if ( $image_id ) {
			echo wp_get_attachment_image( $image_id, 'woocommerce_thumbnail', false, array( 'class' => 'hdp-card__img' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			homburg_wc_blauwdruk_plaat( $product, 'hdp-card__img' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapet intern.
		}

		if ( $product->is_on_sale() ) {
			echo '<span class="hdp-card__flag">' . esc_html__( 'Actie', 'homburg-dealerportaal-theme' ) . '</span>';
		}
		?>
	</a>
	<?php
	// Buiten de <a class="hdp-card__media"> (die aria-hidden is) — een
	// interactieve knop hoort niet in een verborgen link.
	homburg_wc_favoriet_knop( $product->get_id(), 'hdp-card__favoriet' );
	?>

	<div class="hdp-card__body">
		<?php if ( $brand ) : ?>
			<span class="hdp-card__brand"><?php echo esc_html( $brand ); ?></span>
		<?php endif; ?>

		<h3 class="hdp-card__title">
			<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
		</h3>

		<?php if ( $sku ) : ?>
			<span class="hdp-card__sku"><?php echo esc_html( $sku ); ?></span>
		<?php endif; ?>

		<div class="hdp-card__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>

		<?php woocommerce_template_loop_add_to_cart( array( 'class' => 'button hdp-card__btn' ) ); ?>
	</div>
</li>
