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

// De losse product_brand-taxonomie staat bij vrijwel geen onderdeel
// ingevuld (zie shop-filters.php); het raster liet de merkregel daarom
// meestal gewoon weg en toonde de categorie op het blauwdrukvlak. In de
// lijst is "Merk" een eigen kolom, en een kolom die bij 9 van de 10 rijen
// leeg blijft is erger dan geen kolom. Vandaar dezelfde terugval als het
// blauwdrukvlak gebruikt.
$is_categorie = false;
if ( '' === $brand ) {
	$categorieen = get_the_terms( $product->get_id(), 'product_cat' );
	if ( $categorieen && ! is_wp_error( $categorieen ) ) {
		$brand        = $categorieen[0]->name;
		$is_categorie = true;
	}
}
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
			<?php // --cat markeert de terugval, zodat het raster hem kan verbergen: daar staat die categorie al op het blauwdrukvlak. ?>
			<span class="hdp-card__brand<?php echo $is_categorie ? ' hdp-card__brand--cat' : ''; ?>"><?php echo esc_html( $brand ); ?></span>
		<?php endif; ?>

		<h3 class="hdp-card__title">
			<a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
		</h3>

		<?php if ( $sku ) : ?>
			<span class="hdp-card__sku"><?php echo esc_html( $sku ); ?></span>
		<?php endif; ?>

		<div class="hdp-card__price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>

		<?php
		// Aantalveld voor de lijstweergave: een dealer bestelt zelden één
		// bout. In de rasterweergave staat dit op display:none — daar is
		// geen kolom voor. Alleen bij producten die je zo in de mand kunt
		// leggen; bij een variabel product is de knop een link naar de
		// productpagina en zegt een aantal nog niets.
		if ( $product->is_purchasable() && $product->is_in_stock() && ! $product->is_type( 'variable' ) ) :
			?>
			<label class="hdp-card__aantal">
				<span class="hdp-vh">
					<?php
					/* translators: %s: productnaam. */
					printf( esc_html__( 'Aantal %s', 'homburg-dealerportaal-theme' ), esc_html( $product->get_name() ) );
					?>
				</span>
				<input type="number" min="1" step="1" value="1" inputmode="numeric" data-hdp-aantal>
			</label>
			<?php
		endif;

		woocommerce_template_loop_add_to_cart( array( 'class' => 'button hdp-card__btn' ) );
		?>
	</div>
</li>
