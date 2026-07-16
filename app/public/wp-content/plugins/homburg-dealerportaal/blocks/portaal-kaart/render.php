<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @var array $attributes Blokattributen (icoon, titel(Fr), tekst(Fr), knoptekst(Fr), url, instellingSleutel, nieuweTab).
 *
 * Dynamisch blok, zelfde opzet als de andere plugin-blokken: edit() toont
 * een direct bewerkbare WYSIWYG-weergave (RichText), dit bestand bouwt de
 * front-end op vanuit de opgeslagen attributen. Draait bij elk verzoek
 * opnieuw, dus kan HDP_I18N::kies() gewoon rechtstreeks de juiste taal
 * kiezen (geen aparte NL/FR-markup + CSS-toggle nodig zoals bij een
 * statisch blok).
 *
 * De uiteindelijke link komt ofwel uit een centrale instelling
 * (instellingSleutel, bijv. "webshop_url" — beheerd via
 * Instellingen > Dealerportaal, zodat die op één plek staat en niet per
 * kaart apart hoeft te worden bijgewerkt) ofwel uit een vaste, in dit
 * blok ingevulde URL.
 */

$titel     = HDP_I18N::kies( isset( $attributes['titel'] ) ? $attributes['titel'] : '', isset( $attributes['titelFr'] ) ? $attributes['titelFr'] : '' );
$tekst     = HDP_I18N::kies( isset( $attributes['tekst'] ) ? $attributes['tekst'] : '', isset( $attributes['tekstFr'] ) ? $attributes['tekstFr'] : '' );
$knoptekst = HDP_I18N::kies( isset( $attributes['knoptekst'] ) ? $attributes['knoptekst'] : '', isset( $attributes['knoptekstFr'] ) ? $attributes['knoptekstFr'] : '' );

$icoon                   = isset( $attributes['icoon'] ) ? $attributes['icoon'] : 'webshop';
$url                     = isset( $attributes['url'] ) ? $attributes['url'] : '';
$instelling_sleutel      = isset( $attributes['instellingSleutel'] ) ? $attributes['instellingSleutel'] : '';
$nieuwe_tab              = ! empty( $attributes['nieuweTab'] );
$gekoppeld_niet_ingevuld = false;

if ( $instelling_sleutel ) {
	$url_uit_instelling = HDP_Settings::get( $instelling_sleutel );
	if ( $url_uit_instelling ) {
		$url        = $url_uit_instelling;
		$nieuwe_tab = true; // Instellingen-URL's zijn altijd extern (webshop/configurator).
	} else {
		$gekoppeld_niet_ingevuld = true;
	}
}
?>
<article class="hdp-kaart">
	<?php HDP_Icons::render_icoon( $icoon ); ?>
	<?php if ( $titel ) : ?>
		<h2><?php echo wp_kses_post( $titel ); ?></h2>
	<?php endif; ?>
	<?php if ( $tekst ) : ?>
		<p><?php echo wp_kses_post( $tekst ); ?></p>
	<?php endif; ?>
	<?php if ( $gekoppeld_niet_ingevuld ) : ?>
		<p class="hdp-nog-niet"><?php echo esc_html( HDP_I18N::t( 'nog_niet_geconfigureerd' ) ); ?></p>
	<?php elseif ( $url && $knoptekst ) : ?>
		<a class="hdp-btn" href="<?php echo esc_url( $url ); ?>"<?php echo $nieuwe_tab ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>><?php echo esc_html( $knoptekst ); ?></a>
	<?php endif; ?>
</article>
