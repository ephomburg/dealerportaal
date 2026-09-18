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

$titel          = isset( $attributes['titel'] ) ? $attributes['titel'] : '';
$link_tekst     = HDP_I18N::kies(
	isset( $attributes['linkTekst'] ) ? $attributes['linkTekst'] : '',
	isset( $attributes['linkTekstFr'] ) ? $attributes['linkTekstFr'] : ''
);
$url            = isset( $attributes['url'] ) ? $attributes['url'] : '';
$kleur          = isset( $attributes['kleur'] ) && 'rood' === $attributes['kleur'] ? 'rood' : 'donker';
$nieuwe_tab     = ! empty( $attributes['nieuweTab'] );
$afbeelding_url = isset( $attributes['afbeeldingUrl'] ) ? $attributes['afbeeldingUrl'] : '';
$heeft_foto     = (bool) $afbeelding_url;

if ( ! $titel || ! $url ) {
	return;
}

$tegel_class = 'hdp-merktegel hdp-merktegel-' . $kleur . ( $heeft_foto ? ' hdp-merktegel-foto' : '' );
$tegel_stijl = $heeft_foto ? 'background-image:url(' . esc_url( $afbeelding_url ) . ');' : '';
?>
<a
	<?php echo get_block_wrapper_attributes( array( 'class' => $tegel_class, 'style' => $tegel_stijl ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- core escapet dit al. ?>
	href="<?php echo esc_url( $url ); ?>"
	<?php echo $nieuwe_tab ? 'target="_blank" rel="noopener noreferrer"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische waarde. ?>
>
	<span class="hdp-merktegel-tekst">
		<span class="hdp-merktegel-titel"><?php echo esc_html( $titel ); ?></span>
		<?php if ( $link_tekst ) : ?>
			<span class="hdp-merktegel-link"><?php echo esc_html( $link_tekst ); ?></span>
		<?php endif; ?>
	</span>
	<span class="hdp-merktegel-pijl" aria-hidden="true">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="13 6 19 12 13 18"/></svg>
	</span>
</a>
