<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array $attributes Blokattributen (icoon, titel(Fr), tekst(Fr), email, telefoon).
 *
 * Zelfde opzet en opmaak als homburg/info-kaart: NL en FR staan allebei in
 * de HTML (.hdp-taal-nl/.hdp-taal-fr), de CSS toont de juiste op basis van
 * data-taal op de <html>-tag. De e-mail- en telefoonknoppen worden uit de
 * losse velden opgebouwd (mailto:/tel:), zodat de beheerder geen links hoeft
 * te typen.
 */

$titel       = isset( $attributes['titel'] ) ? $attributes['titel'] : '';
$titel_fr    = isset( $attributes['titelFr'] ) ? $attributes['titelFr'] : '';
$tekst       = isset( $attributes['tekst'] ) ? $attributes['tekst'] : '';
$tekst_fr    = isset( $attributes['tekstFr'] ) ? $attributes['tekstFr'] : '';
$icoon       = isset( $attributes['icoon'] ) ? $attributes['icoon'] : 'mail';
$email       = isset( $attributes['email'] ) ? trim( $attributes['email'] ) : '';
$telefoon    = isset( $attributes['telefoon'] ) ? trim( $attributes['telefoon'] ) : '';
$telefoon_href = $telefoon ? 'tel:' . preg_replace( '/[^\d+]/', '', $telefoon ) : '';
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
		<?php if ( $email || $telefoon ) : ?>
			<div class="hdp-info-links">
				<?php if ( $email ) : ?>
					<a class="hdp-info-link" href="<?php echo esc_url( 'mailto:' . $email ); ?>"><?php echo HDP_Icons::svg_icoon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?> <?php echo esc_html( $email ); ?></a>
				<?php endif; ?>
				<?php if ( $telefoon ) : ?>
					<a class="hdp-info-link" href="<?php echo esc_url( $telefoon_href ); ?>"><?php echo HDP_Icons::svg_icoon( 'tel' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?> <?php echo esc_html( $telefoon ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</article>
