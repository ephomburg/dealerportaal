<?php
/**
 * "Snel bestellen" — een dealer die een lijst artikelnummers paraat heeft
 * (bijv. uit een eigen inkooplijst) kan die in één keer plakken/intypen
 * i.p.v. elk apart op te zoeken. Eigen "Mijn account"-tabblad, net als de
 * bestaande "Bestellingen"/"Downloads" van WooCommerce zelf.
 *
 * Formaat: één artikelnummer per regel, optioneel gevolgd door een aantal
 * (bijv. "10714", "10714 x3", "10714 3", "10714,3" — alles na het
 * artikelnummer dat op een getal lijkt wordt als aantal gelezen).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

add_action( 'init', 'homburg_wc_snelbestellen_endpoint' );
function homburg_wc_snelbestellen_endpoint() {
	add_rewrite_endpoint( 'snelbestellen', EP_ROOT | EP_PAGES );
}

add_filter( 'woocommerce_get_query_vars', 'homburg_wc_snelbestellen_query_var' );
/**
 * WooCommerce houdt zijn "Mijn account"-eindpunten (orders, downloads, …)
 * in een EIGEN register bij (los van WordPress' eigen query_vars-filter,
 * die add_rewrite_endpoint() overigens ook nog nodig heeft voor de
 * rewrite-rule zelf) — o.a. is_wc_endpoint_url() en de titel-vervanging
 * van wc_page_endpoint_title() kijken hier specifiek naar. Zonder deze
 * regel laadt de pagina prima, maar blijft de title/kop op "Mijn account"
 * staan i.p.v. "Snel bestellen".
 */
function homburg_wc_snelbestellen_query_var( $vars ) {
	$vars['snelbestellen'] = 'snelbestellen';
	return $vars;
}

/**
 * Eenmalig de rewrite-rules verversen zodat /mijn-account/snelbestellen/
 * meteen werkt, zonder dat er handmatig naar Instellingen > Permalinks
 * genavigeerd hoeft te worden. Ruimt zichzelf op na één keer.
 */
add_action( 'init', 'homburg_wc_snelbestellen_flush', 20 );
function homburg_wc_snelbestellen_flush() {
	if ( ! get_option( 'hdp_snelbestellen_flushed' ) ) {
		flush_rewrite_rules();
		update_option( 'hdp_snelbestellen_flushed', 1 );
	}
}

add_filter( 'woocommerce_endpoint_snelbestellen_title', 'homburg_wc_snelbestellen_titel' );
function homburg_wc_snelbestellen_titel() {
	return __( 'Snel bestellen', 'homburg-dealerportaal-theme' );
}

add_filter( 'woocommerce_account_menu_items', 'homburg_wc_snelbestellen_menu' );
function homburg_wc_snelbestellen_menu( $items ) {
	// Vlak na "Bestellingen" invoegen, vóór de rest.
	$nieuw = array();
	foreach ( $items as $key => $label ) {
		$nieuw[ $key ] = $label;
		if ( 'orders' === $key ) {
			$nieuw['snelbestellen'] = __( 'Snel bestellen', 'homburg-dealerportaal-theme' );
		}
	}
	return $nieuw;
}

add_action( 'woocommerce_account_snelbestellen_endpoint', 'homburg_wc_snelbestellen_inhoud' );
function homburg_wc_snelbestellen_inhoud() {
	$resultaat = null;

	if ( ! empty( $_POST['hdp_snelbestellen_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hdp_snelbestellen_nonce'] ) ), 'hdp-snelbestellen' ) ) {
		$resultaat = homburg_wc_snelbestellen_verwerken( isset( $_POST['hdp_snelbestellen_lijst'] ) ? wp_unslash( $_POST['hdp_snelbestellen_lijst'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- per regel hieronder alsnog sanitized.
	}
	?>
	<p>
		<?php esc_html_e( 'Ken je de artikelnummers al uit het hoofd of heb je zelf een inkooplijst? Plak of typ ze hieronder, één per regel — je hoeft ze niet apart op te zoeken.', 'homburg-dealerportaal-theme' ); ?>
	</p>

	<?php if ( $resultaat ) : ?>
		<?php if ( $resultaat['toegevoegd'] ) : ?>
			<div class="woocommerce-message" role="alert">
				<?php
				printf(
					/* translators: %d: aantal toegevoegde artikelen */
					esc_html( _n( '%d artikel toegevoegd aan je winkelmand.', '%d artikelen toegevoegd aan je winkelmand.', count( $resultaat['toegevoegd'] ), 'homburg-dealerportaal-theme' ) ),
					count( $resultaat['toegevoegd'] )
				);
				?>
				<a href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php esc_html_e( 'Naar de winkelmand', 'homburg-dealerportaal-theme' ); ?></a>
			</div>
		<?php endif; ?>
		<?php if ( $resultaat['niet_gevonden'] ) : ?>
			<div class="woocommerce-error" role="alert">
				<?php esc_html_e( 'Niet gevonden (controleer het artikelnummer):', 'homburg-dealerportaal-theme' ); ?>
				<strong><?php echo esc_html( implode( ', ', $resultaat['niet_gevonden'] ) ); ?></strong>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<div class="hdp-snelbestellen-zoeken">
		<label for="hdp-snelbestellen-zoekveld"><?php esc_html_e( 'Weet je het artikelnummer niet uit het hoofd? Zoek het hier op:', 'homburg-dealerportaal-theme' ); ?></label>
		<div class="hdp-zoekveld">
			<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
			<input type="text" id="hdp-snelbestellen-zoekveld" autocomplete="off" placeholder="<?php esc_attr_e( 'Typ een naam of artikelnummer…', 'homburg-dealerportaal-theme' ); ?>">
		</div>
	</div>

	<form method="post" class="hdp-snelbestellen-form">
		<textarea
			id="hdp_snelbestellen_lijst"
			name="hdp_snelbestellen_lijst"
			rows="10"
			placeholder="<?php echo esc_attr( "10714\n660-00-10201-00 x3\n11513,2" ); ?>"
		><?php echo isset( $_POST['hdp_snelbestellen_lijst'] ) ? esc_textarea( wp_unslash( $_POST['hdp_snelbestellen_lijst'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- esc_textarea hierboven. ?></textarea>
		<p class="hdp-snelbestellen-hulp"><?php esc_html_e( 'Eén artikelnummer per regel. Zet er zo gewenst een aantal achter, bijv. "10714 x3".', 'homburg-dealerportaal-theme' ); ?></p>
		<?php wp_nonce_field( 'hdp-snelbestellen', 'hdp_snelbestellen_nonce' ); ?>
		<button type="submit" class="button"><?php esc_html_e( 'Toevoegen aan winkelmand', 'homburg-dealerportaal-theme' ); ?></button>
	</form>
	<?php
}

add_action( 'wp_enqueue_scripts', 'homburg_wc_snelbestellen_zoeken_enqueue' );
/**
 * Live zoeksuggesties boven het "Snel bestellen"-tekstvak — hergebruikt
 * dezelfde AJAX-actie/zoeklogica als de winkelpagina (zie
 * search-autosuggest.php), maar een klik op een suggestie voegt hier een
 * regel toe aan de textarea i.p.v. naar de productpagina te gaan. Bedoeld
 * voor een dealer die het artikelnummer niet uit het hoofd kent — wie dat
 * wél doet, kan de textarea gewoon blijven gebruiken zoals voorheen.
 */
function homburg_wc_snelbestellen_zoeken_enqueue() {
	if ( ! function_exists( 'is_wc_endpoint_url' ) || ! is_wc_endpoint_url( 'snelbestellen' ) ) {
		return;
	}

	$js = <<<'JS'
(function () {
	var veld = document.getElementById('hdp-snelbestellen-zoekveld');
	var lijst = document.getElementById('hdp_snelbestellen_lijst');
	if (!veld || !lijst) { return; }

	var wrapper = document.createElement('div');
	wrapper.className = 'hdp-zoek-suggesties';
	wrapper.hidden = true;
	veld.parentElement.style.position = 'relative';
	veld.parentElement.appendChild(wrapper);

	var timer = null;
	var actieveController = null;

	function verbergen() { wrapper.hidden = true; wrapper.innerHTML = ''; }

	function regelToevoegen(sku) {
		var huidig = lijst.value.replace(/\s+$/, '');
		lijst.value = (huidig ? huidig + '\n' : '') + sku + ' x1';
		lijst.focus();
		lijst.scrollTop = lijst.scrollHeight;
	}

	function tonen(producten) {
		if (!producten.length) { verbergen(); return; }
		wrapper.innerHTML = '';
		producten.forEach(function (p) {
			if (!p.sku) { return; }
			var knop = document.createElement('button');
			knop.type = 'button';
			knop.className = 'hdp-zoek-suggestie';
			var beeld = p.beeld
				? '<img src="' + p.beeld + '" alt="" class="hdp-zoek-suggestie-img">'
				: '<span class="hdp-zoek-suggestie-img hdp-zoek-suggestie-img--leeg">' + p.sku + '</span>';
			knop.innerHTML = beeld +
				'<span class="hdp-zoek-suggestie-tekst">' +
					'<span class="hdp-zoek-suggestie-naam">' + p.naam + '</span>' +
					'<span class="hdp-zoek-suggestie-sku">' + p.sku + '</span>' +
				'</span>' +
				'<span class="hdp-zoek-suggestie-prijs">' + p.prijs + '</span>';
			knop.addEventListener('click', function () {
				regelToevoegen(p.sku);
				verbergen();
				veld.value = '';
				veld.focus();
			});
			wrapper.appendChild(knop);
		});
		wrapper.hidden = false;
	}

	veld.addEventListener('input', function () {
		var term = veld.value.trim();
		clearTimeout(timer);
		if (term.length < 2) { verbergen(); return; }

		timer = setTimeout(function () {
			if (actieveController) { actieveController.abort(); }
			actieveController = new AbortController();

			var url = HDP_SNELBESTELLEN_ZOEK.ajaxUrl + '?action=hdp_zoeksuggesties&nonce=' + encodeURIComponent(HDP_SNELBESTELLEN_ZOEK.nonce) + '&term=' + encodeURIComponent(term);
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

	wp_register_script( 'hdp-wc-snelbestellen-zoeken', '', array(), wp_get_theme()->get( 'Version' ), true );
	wp_enqueue_script( 'hdp-wc-snelbestellen-zoeken' );
	wp_localize_script(
		'hdp-wc-snelbestellen-zoeken',
		'HDP_SNELBESTELLEN_ZOEK',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'hdp-zoeksuggesties' ),
		)
	);
	wp_add_inline_script( 'hdp-wc-snelbestellen-zoeken', $js );
}

/**
 * Verwerkt de geplakte lijst: per regel het artikelnummer (en optioneel
 * aantal) eruit halen, opzoeken en aan de winkelmand toevoegen.
 *
 * @param string $lijst De ruwe tekst uit de textarea.
 * @return array{toegevoegd: string[], niet_gevonden: string[]}
 */
function homburg_wc_snelbestellen_verwerken( $lijst ) {
	$toegevoegd    = array();
	$niet_gevonden = array();

	foreach ( preg_split( '/[\r\n]+/', $lijst ) as $regel ) {
		$regel = trim( sanitize_text_field( $regel ) );
		if ( '' === $regel ) {
			continue;
		}

		// Alles vóór het eerste "aantal-achtige" stuk (spatie/komma/x + cijfers
		// aan het eind van de regel) is het artikelnummer; wat daarna volgt,
		// is het aantal. Geen aantal gevonden? Dan gewoon 1 stuk.
		if ( preg_match( '/^(.+?)[\s,;]+x?\s*(\d+)\s*$/i', $regel, $match ) ) {
			$sku    = trim( $match[1] );
			$aantal = max( 1, (int) $match[2] );
		} else {
			$sku    = $regel;
			$aantal = 1;
		}

		$product_id = wc_get_product_id_by_sku( $sku );
		if ( ! $product_id ) {
			$niet_gevonden[] = $sku;
			continue;
		}

		$toegevoegd_id = WC()->cart->add_to_cart( $product_id, $aantal );
		if ( $toegevoegd_id ) {
			$toegevoegd[] = $sku;
		} else {
			$niet_gevonden[] = $sku;
		}
	}

	return array(
		'toegevoegd'    => $toegevoegd,
		'niet_gevonden' => $niet_gevonden,
	);
}
