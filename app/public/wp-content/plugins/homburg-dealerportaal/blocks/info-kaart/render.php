<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array $attributes Blokattributen (icoon, titel(Fr), tekst(Fr), link1/2 Label/Url).
 *
 * Dynamisch (i.p.v. statisch) gerenderd, net als de andere blokken in
 * deze plugin: de editor toont via RichText al een direct bewerkbare
 * WYSIWYG-weergave (zie index.js), en dit bestand hoeft dus alleen de
 * front-end op te bouwen vanuit de opgeslagen attributen.
 *
 * NL en FR staan altijd allebei in de HTML (met .hdp-taal-nl/.hdp-taal-fr);
 * welke zichtbaar is bepaalt de CSS op basis van data-taal op de <html>-tag
 * (zie HDP_I18N::voeg_data_taal_toe()).
 */

$titel       = isset( $attributes['titel'] ) ? $attributes['titel'] : '';
$titel_fr    = isset( $attributes['titelFr'] ) ? $attributes['titelFr'] : '';
$tekst       = isset( $attributes['tekst'] ) ? $attributes['tekst'] : '';
$tekst_fr    = isset( $attributes['tekstFr'] ) ? $attributes['tekstFr'] : '';
$icoon       = isset( $attributes['icoon'] ) ? $attributes['icoon'] : 'bestellen';
$link1_label = isset( $attributes['link1Label'] ) ? $attributes['link1Label'] : '';
$link1_url   = isset( $attributes['link1Url'] ) ? $attributes['link1Url'] : '';
$link2_label = isset( $attributes['link2Label'] ) ? $attributes['link2Label'] : '';
$link2_url   = isset( $attributes['link2Url'] ) ? $attributes['link2Url'] : '';
?>
<article <?php echo get_block_wrapper_attributes( array( 'class' => 'hdp-info-kaart' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- core escapet dit al. ?>>
	<?php HDP_Icons::render_icoon( $icoon, 'hdp-info-icoon' ); ?>
	<div class="hdp-info-body">
		<?php if ( $titel ) : ?>
			<h3 class="hdp-taal-nl"><?php echo wp_kses_post( $titel ); ?></h3>
		<?php endif; ?>
		<?php if ( $titel_fr ) : ?>
			<h3 class="hdp-taal-fr"><?php echo wp_kses_post( $titel_fr ); ?></h3>
		<?php endif; ?>
		<?php if ( $tekst ) : ?>
			<p class="hdp-taal-nl"><?php echo wp_kses_post( $tekst ); ?></p>
		<?php endif; ?>
		<?php if ( $tekst_fr ) : ?>
			<p class="hdp-taal-fr"><?php echo wp_kses_post( $tekst_fr ); ?></p>
		<?php endif; ?>
		<?php if ( $link1_label || $link2_label ) : ?>
			<div class="hdp-info-links">
				<?php if ( $link1_label ) : ?>
					<a class="hdp-info-link" href="<?php echo esc_url( $link1_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo HDP_Icons::svg_icoon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?> <?php echo esc_html( $link1_label ); ?></a>
				<?php endif; ?>
				<?php if ( $link2_label ) : ?>
					<a class="hdp-info-link" href="<?php echo esc_url( $link2_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo HDP_Icons::svg_icoon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php echo esc_html( $link2_label ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</article>
