<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array $attributes Blokattributen (titel, linkTekst(Fr), url, kleur, nieuweTab).
 *
 * Eén tegel in de merken-hub bij "Overige informatie" (zie
 * patterns/overige-informatie.php voor de opzet). Dynamisch blok, net als
 * de andere kaart-blokken in deze plugin.
 */

$titel      = isset( $attributes['titel'] ) ? $attributes['titel'] : '';
$link_tekst = HDP_I18N::kies(
	isset( $attributes['linkTekst'] ) ? $attributes['linkTekst'] : '',
	isset( $attributes['linkTekstFr'] ) ? $attributes['linkTekstFr'] : ''
);
$url        = isset( $attributes['url'] ) ? $attributes['url'] : '';
$kleur      = isset( $attributes['kleur'] ) && 'rood' === $attributes['kleur'] ? 'rood' : 'donker';
$nieuwe_tab = ! empty( $attributes['nieuweTab'] );

if ( ! $titel || ! $url ) {
	return;
}
?>
<a
	<?php echo get_block_wrapper_attributes( array( 'class' => 'hdp-merktegel hdp-merktegel-' . $kleur ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- core escapet dit al. ?>
	href="<?php echo esc_url( $url ); ?>"
	<?php echo $nieuwe_tab ? 'target="_blank" rel="noopener noreferrer"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische waarde. ?>
>
	<span class="hdp-merktegel-titel"><?php echo esc_html( $titel ); ?></span>
	<?php if ( $link_tekst ) : ?>
		<span class="hdp-merktegel-link"><?php echo esc_html( $link_tekst ); ?></span>
	<?php endif; ?>
</a>
