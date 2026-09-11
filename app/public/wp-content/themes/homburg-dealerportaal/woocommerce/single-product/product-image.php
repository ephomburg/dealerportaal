<?php
/**
 * Productafbeelding op de losse productpagina.
 *
 * Eigen, eenvoudige opzet volgens het ontwerpvoorstel: één vierkant
 * beeldvlak met vaste (begrensde) breedte. Geen galerij-slider en geen
 * opacity-fade, zodat de afbeelding niet de halve pagina kan vullen en
 * niet afhankelijk is van WooCommerce's flexslider-script.
 *
 * Overschrijft woocommerce/templates/single-product/product-image.php.
 *
 * @package Homburg_Dealerportaal
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( ! $product ) {
	return;
}

$image_id = $product->get_image_id();
?>
<div class="hdp-pdp-media">
	<?php
	if ( $image_id ) {
		echo wp_get_attachment_image(
			$image_id,
			'woocommerce_single',
			false,
			array( 'class' => 'hdp-pdp-media__img' )
		); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	} else {
		homburg_wc_blauwdruk_plaat( $product, 'hdp-pdp-media__img' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escapet intern.
	}
	?>
</div>
