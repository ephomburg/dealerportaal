<?php
/**
 * Losse productpagina — de opzet uit het ontwerpvoorstel: het
 * artikelnummer groot en kopieerbaar direct onder de titel, "excl. btw"
 * bij de prijs, een aantal-stappenteller met +/- knoppen, en een
 * "Specificaties"-tab i.p.v. beoordelingen. Werkt via WooCommerce-hooks;
 * geen sjablonen gekopieerd (de aantal-invoer zelf ook niet — alleen de
 * before/after-hooks eromheen gebruikt).
 *
 * Nog niet hier (wacht op de PowerAll-koppeling): staffelprijzen, "past op
 * deze machines" en kruisverwijzingen (vervangt / opgevolgd door).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

add_action( 'woocommerce_single_product_summary', 'homburg_wc_sku_prominent', 7 );
/**
 * Artikelnummer groot in monospace + een "Kopieer"-knop, net onder de
 * titel (prioriteit 5) en het merk (prioriteit 6).
 */
function homburg_wc_sku_prominent() {
	global $product;

	if ( ! $product ) {
		return;
	}

	$sku = $product->get_sku();
	if ( ! $sku ) {
		return;
	}
	?>
	<div class="hdp-wc-skuline">
		<span class="hdp-wc-sku-groot" data-hdp-sku><?php echo esc_html( $sku ); ?></span>
		<button type="button" class="hdp-wc-kopieer" data-hdp-copy>
			<?php esc_html_e( 'Kopieer', 'homburg-dealerportaal-theme' ); ?>
		</button>
	</div>
	<?php
}

/**
 * Het artikelnummer/de categorie stonden als losse tekstregel onder de
 * bestelknop; die info staat nu in de "Specificaties"-tab (zie hieronder),
 * dus de standaardregel hier weghalen om dubbeling te voorkomen.
 */
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );

add_filter( 'woocommerce_product_tabs', 'homburg_wc_product_tabs', 98 );
/**
 * Beoordelingen weg (nog niet gewenst), en een altijd-zichtbare
 * "Specificaties"-tab ervoor in de plaats — anders blijft er, zolang een
 * product geen omschrijving heeft, geen enkele tab meer over.
 */
function homburg_wc_product_tabs( $tabs ) {
	unset( $tabs['reviews'] );

	$tabs['hdp_specificaties'] = array(
		'title'    => __( 'Specificaties', 'homburg-dealerportaal-theme' ),
		'priority' => 5,
		'callback' => 'homburg_wc_tab_specificaties',
	);

	return $tabs;
}

/**
 * Inhoud van de "Specificaties"-tab: alleen de velden die het product
 * daadwerkelijk heeft (artikelnummer, categorie, merk, gewicht, afmeting).
 * Leeg blijft leeg — geen lege rijen.
 */
function homburg_wc_tab_specificaties() {
	global $product;

	if ( ! $product ) {
		return;
	}

	$rijen = array();

	if ( $product->get_sku() ) {
		$rijen[ __( 'Artikelnummer', 'homburg-dealerportaal-theme' ) ] = esc_html( $product->get_sku() );
	}

	$categorieen = wc_get_product_category_list( $product->get_id() );
	if ( $categorieen ) {
		$rijen[ __( 'Categorie', 'homburg-dealerportaal-theme' ) ] = wp_kses_post( $categorieen );
	}

	$merken = get_the_terms( $product->get_id(), 'product_brand' );
	if ( $merken && ! is_wp_error( $merken ) ) {
		$rijen[ __( 'Merk', 'homburg-dealerportaal-theme' ) ] = esc_html( $merken[0]->name );
	}

	if ( $product->get_weight() ) {
		$rijen[ __( 'Gewicht', 'homburg-dealerportaal-theme' ) ] = esc_html( wc_format_weight( $product->get_weight() ) );
	}

	if ( $product->get_length() || $product->get_width() || $product->get_height() ) {
		$rijen[ __( 'Afmetingen', 'homburg-dealerportaal-theme' ) ] = esc_html( wc_format_dimensions( $product->get_dimensions( false ) ) );
	}

	if ( ! $rijen ) {
		return;
	}
	?>
	<table class="hdp-wc-specs">
		<tbody>
			<?php foreach ( $rijen as $label => $waarde ) : ?>
				<tr>
					<th><?php echo esc_html( $label ); ?></th>
					<td><?php echo $waarde; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hierboven al ge-escaped. ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}

add_action( 'woocommerce_before_quantity_input_field', 'homburg_wc_qty_min_knop' );
add_action( 'woocommerce_after_quantity_input_field', 'homburg_wc_qty_plus_knop' );
/**
 * +/- knoppen om het aantal, alleen op de losse productpagina (op
 * winkelmand/checkout blijft de standaard-invoer staan — dat is een
 * aparte context met eigen ajax-afhandeling).
 */
function homburg_wc_qty_min_knop() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	echo '<button type="button" class="hdp-qty-btn hdp-qty-min" aria-label="' . esc_attr__( 'Minder', 'homburg-dealerportaal-theme' ) . '">&minus;</button>';
}
function homburg_wc_qty_plus_knop() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}
	echo '<button type="button" class="hdp-qty-btn hdp-qty-plus" aria-label="' . esc_attr__( 'Meer', 'homburg-dealerportaal-theme' ) . '">&plus;</button>';
}

add_action( 'wp_enqueue_scripts', 'homburg_wc_single_inline_js', 30 );
/**
 * Kleine, afhankelijkheidsvrije helpers voor de productpagina: de
 * "Kopieer"-knop bij het artikelnummer, en de +/- knoppen bij het aantal
 * (die versturen een "change"-event, zodat WooCommerce's eigen scripts —
 * bijv. voor variabele prijzen — er normaal op reageren). Alleen op de
 * productpagina geladen.
 */
function homburg_wc_single_inline_js() {
	if ( ! function_exists( 'is_product' ) || ! is_product() ) {
		return;
	}

	$js = <<<'JS'
(function () {
	var btn = document.querySelector('[data-hdp-copy]');
	var sku = document.querySelector('[data-hdp-sku]');
	if (btn && sku) {
		var origineel = btn.textContent.trim();
		btn.addEventListener('click', function () {
			var tekst = sku.textContent.trim();
			var klaar = function () {
				btn.textContent = 'Gekopieerd';
				btn.classList.add('is-gekopieerd');
				setTimeout(function () { btn.textContent = origineel; btn.classList.remove('is-gekopieerd'); }, 1600);
			};
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(tekst).then(klaar, klaar);
			} else {
				klaar();
			}
		});
	}

	document.addEventListener('click', function (e) {
		var knop = e.target.closest('.hdp-qty-btn');
		if (!knop) { return; }
		var veld = knop.parentElement.querySelector('input.qty');
		if (!veld) { return; }
		var stap = parseFloat(veld.step) || 1;
		var min = veld.min !== '' ? parseFloat(veld.min) : 0;
		var max = veld.max !== '' ? parseFloat(veld.max) : Infinity;
		var waarde = parseFloat(veld.value) || 0;
		waarde = knop.classList.contains('hdp-qty-plus') ? waarde + stap : waarde - stap;
		waarde = Math.min(max, Math.max(min, waarde));
		veld.value = waarde;
		veld.dispatchEvent(new Event('change', { bubbles: true }));
	});
})();
JS;

	wp_register_script( 'hdp-wc-single', '', array(), wp_get_theme()->get( 'Version' ), true );
	wp_enqueue_script( 'hdp-wc-single' );
	wp_add_inline_script( 'hdp-wc-single', $js );
}
