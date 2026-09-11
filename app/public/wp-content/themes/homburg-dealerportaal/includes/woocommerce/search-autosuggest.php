<?php
/**
 * Live zoeksuggesties onder de zoekbalk op de winkelpagina: een paar
 * treffers (foto/placeholder, naam, artikelnummer, prijs) al terwijl je
 * typt, i.p.v. pas na op "Zoeken" te klikken. Gebruikt dezelfde
 * titel+artikelnummer-zoeklogica als de winkelpagina zelf
 * (homburg_wc_zoek_product_ids(), zie shop-filters.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

add_action( 'wp_ajax_hdp_zoeksuggesties', 'homburg_wc_zoeksuggesties_ajax' );
add_action( 'wp_ajax_nopriv_hdp_zoeksuggesties', 'homburg_wc_zoeksuggesties_ajax' );
function homburg_wc_zoeksuggesties_ajax() {
	check_ajax_referer( 'hdp-zoeksuggesties', 'nonce' );

	$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
	if ( mb_strlen( $term ) < 2 ) {
		wp_send_json_success( array() );
	}

	$ids       = homburg_wc_zoek_product_ids( $term, 6 );
	$producten = array();

	foreach ( $ids as $id ) {
		$product = wc_get_product( $id );
		if ( ! $product || ! $product->is_visible() ) {
			continue;
		}

		$image_id = $product->get_image_id();
		$producten[] = array(
			'naam'   => $product->get_name(),
			'sku'    => $product->get_sku(),
			'prijs'  => wp_strip_all_tags( wc_price( wc_get_price_to_display( $product ) ) ),
			'url'    => get_permalink( $id ),
			'beeld'  => $image_id ? wp_get_attachment_image_url( $image_id, 'woocommerce_thumbnail' ) : '',
		);
	}

	wp_send_json_success( $producten );
}

add_action( 'wp_enqueue_scripts', 'homburg_wc_zoeksuggesties_enqueue', 31 );
/**
 * Alleen op dezelfde pagina's als de zoekbalk zelf (zie
 * homburg_wc_shop_werkbalk_open() in shop-filters.php).
 */
function homburg_wc_zoeksuggesties_enqueue() {
	if ( ! is_shop() && ! is_product_taxonomy() ) {
		return;
	}

	$js = <<<'JS'
(function () {
	var veld = document.querySelector('.hdp-shop-zoeken input[name="zoek"]');
	if (!veld) { return; }

	var wrapper = document.createElement('div');
	wrapper.className = 'hdp-zoek-suggesties';
	wrapper.hidden = true;
	veld.parentElement.style.position = 'relative';
	veld.parentElement.appendChild(wrapper);

	var timer = null;
	var actieveController = null;

	function verbergen() { wrapper.hidden = true; wrapper.innerHTML = ''; }

	function tonen(producten) {
		if (!producten.length) { verbergen(); return; }
		wrapper.innerHTML = producten.map(function (p) {
			var beeld = p.beeld
				? '<img src="' + p.beeld + '" alt="" class="hdp-zoek-suggestie-img">'
				: '<span class="hdp-zoek-suggestie-img hdp-zoek-suggestie-img--leeg">' + (p.sku || '') + '</span>';
			return (
				'<a class="hdp-zoek-suggestie" href="' + p.url + '">' +
					beeld +
					'<span class="hdp-zoek-suggestie-tekst">' +
						'<span class="hdp-zoek-suggestie-naam">' + p.naam + '</span>' +
						(p.sku ? '<span class="hdp-zoek-suggestie-sku">' + p.sku + '</span>' : '') +
					'</span>' +
					'<span class="hdp-zoek-suggestie-prijs">' + p.prijs + '</span>' +
				'</a>'
			);
		}).join('');
		wrapper.hidden = false;
	}

	veld.addEventListener('input', function () {
		var term = veld.value.trim();
		clearTimeout(timer);
		if (term.length < 2) { verbergen(); return; }

		timer = setTimeout(function () {
			if (actieveController) { actieveController.abort(); }
			actieveController = new AbortController();

			var url = HDP_ZOEK.ajaxUrl + '?action=hdp_zoeksuggesties&nonce=' + encodeURIComponent(HDP_ZOEK.nonce) + '&term=' + encodeURIComponent(term);
			fetch(url, { signal: actieveController.signal })
				.then(function (r) { return r.json(); })
				.then(function (data) { tonen(data.success ? data.data : []); })
				.catch(function () {});
		}, 250);
	});

	document.addEventListener('click', function (e) {
		if (!wrapper.contains(e.target) && e.target !== veld) { verbergen(); }
	});
	veld.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') { verbergen(); }
	});
})();
JS;

	wp_register_script( 'hdp-wc-zoeksuggesties', '', array(), wp_get_theme()->get( 'Version' ), true );
	wp_enqueue_script( 'hdp-wc-zoeksuggesties' );
	wp_localize_script(
		'hdp-wc-zoeksuggesties',
		'HDP_ZOEK',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'hdp-zoeksuggesties' ),
		)
	);
	wp_add_inline_script( 'hdp-wc-zoeksuggesties', $js );
}
