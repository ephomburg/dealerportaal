<?php
/**
 * Zoeken, filteren op merk en een raster/lijst-weergavekeuze voor de
 * winkelpagina en categorieoverzichten.
 *
 * "Merk" is hier de hoofdcategorie (product_cat, parent = 0) — de enige
 * merk-achtige data die de huidige testset uit PowerAll meegeeft (de
 * losse product_brand-taxonomie staat bij vrijwel geen product ingevuld).
 * Zodra PowerAll een echt merkveld levert, kan dit bestand overschakelen
 * naar product_brand zonder dat de rest van de sjabloon wijzigt.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

/**
 * De winkelpagina (is_shop()) is een post-type-archief, geen zoekopdracht.
 * Door hier zelf "s" op de hoofdquery te zetten doet WordPress/WooCommerce
 * alsof het wél een zoekopdracht is (title/inhoud + WooCommerce's eigen
 * uitbreiding naar SKU), zonder dat het sjabloon of de archief-lus wijzigt.
 */
add_action( 'pre_get_posts', 'homburg_wc_shop_zoeken_en_filteren' );
function homburg_wc_shop_zoeken_en_filteren( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( ! $query->is_post_type_archive( 'product' ) && ! $query->is_tax( get_object_taxonomies( 'product' ) ) ) {
		return;
	}

	if ( ! empty( $_GET['zoek'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- alleen lezen, geen schrijfactie.
		$gevonden = homburg_wc_zoek_product_ids( sanitize_text_field( wp_unslash( $_GET['zoek'] ) ) );
		// Lege post__in ([]) betekent voor WP_Query "geen filter" (dus alles
		// tonen) — bij nul treffers hier expliciet [0] meegeven, dat matcht
		// gegarandeerd geen enkel product.
		$query->set( 'post__in', $gevonden ? $gevonden : array( 0 ) );
	}

	if ( ! empty( $_GET['merk'] ) && is_array( $_GET['merk'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$slugs = array_map( 'sanitize_title', wp_unslash( $_GET['merk'] ) );

		$tax_query   = (array) $query->get( 'tax_query' );
		$tax_query[] = array(
			'taxonomy' => 'product_cat',
			'field'    => 'slug',
			'terms'    => $slugs,
		);
		$query->set( 'tax_query', $tax_query );
	}
}

/**
 * Producten die matchen op titel/inhoud (WordPress' eigen zoekopdracht) OF
 * artikelnummer (SKU) — WooCommerce breidt de zoekopdracht hier standaard
 * niet naar uit. Wordt zowel door de winkelpagina zelf gebruikt als door de
 * live zoeksuggesties (zie search-autosuggest.php).
 *
 * @param string $term  De zoekterm.
 * @param int    $limit -1 voor alles (winkelpagina), of een aantal (suggesties).
 * @return int[] Product-ID's.
 */
function homburg_wc_zoek_product_ids( $term, $limit = -1 ) {
	$titel_ids = get_posts(
		array(
			'post_type'      => 'product',
			's'              => $term,
			'posts_per_page' => $limit,
			'fields'         => 'ids',
		)
	);

	global $wpdb;
	$limiet_sql = $limit > 0 ? ' LIMIT ' . (int) $limit : '';
	$sku_ids    = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value LIKE %s" . $limiet_sql, // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $limiet_sql is hierboven al met (int) gecast, geen user input.
			'%' . $wpdb->esc_like( $term ) . '%'
		)
	);

	$gevonden = array_unique( array_merge( $titel_ids, array_map( 'intval', $sku_ids ) ) );

	return $limit > 0 ? array_slice( $gevonden, 0, $limit ) : $gevonden;
}

/**
 * De actief geselecteerde merken uit de URL, als array van slugs.
 */
function homburg_wc_actieve_merken() {
	if ( empty( $_GET['merk'] ) || ! is_array( $_GET['merk'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return array();
	}
	return array_map( 'sanitize_title', wp_unslash( $_GET['merk'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
}

/**
 * De URL voor het "chip"-kruisje bij een los actief merkfilter: dezelfde
 * actieve merken, min deze ene — i.p.v. (zoals "Filter wissen") meteen
 * alles wegvegen.
 */
function homburg_wc_chip_url_zonder( $slug, $actieve_merken ) {
	$resterend = array_values( array_diff( $actieve_merken, array( $slug ) ) );
	$url       = remove_query_arg( 'merk' );
	return $resterend ? add_query_arg( 'merk', $resterend, $url ) : $url;
}

add_action( 'woocommerce_before_shop_loop', 'homburg_wc_shop_werkbalk_open', 1 );
/**
 * Opent de lay-out: een linker "Merk"-kolom en een rechterkolom die de
 * rest van de standaard-winkelinhoud bevat (resultaattelling, sortering,
 * productraster, paginering — die hoeven hierdoor niet aangepast). Bovenin
 * de rechterkolom komt de zoekbalk + raster/lijst-schakelaar.
 */
function homburg_wc_shop_werkbalk_open() {
	if ( ! is_shop() && ! is_product_taxonomy() ) {
		return;
	}

	$merken         = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'parent'     => 0,
			'hide_empty' => true,
			'orderby'    => 'name',
		)
	);
	$actieve_merken = homburg_wc_actieve_merken();
	$zoekterm       = isset( $_GET['zoek'] ) ? sanitize_text_field( wp_unslash( $_GET['zoek'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	?>
	<div class="hdp-shop-layout">
		<?php if ( ! is_wp_error( $merken ) && $merken ) : ?>
			<aside class="hdp-shop-merken">
				<details class="hdp-shop-merken-details" open>
					<summary><?php esc_html_e( 'Merk', 'homburg-dealerportaal-theme' ); ?></summary>
					<form method="get" class="hdp-shop-merken-form">
						<?php if ( $zoekterm ) : ?>
							<input type="hidden" name="zoek" value="<?php echo esc_attr( $zoekterm ); ?>">
						<?php endif; ?>
						<ul class="hdp-shop-merken-lijst">
							<?php foreach ( $merken as $merk ) : ?>
								<li>
									<label>
										<input
											type="checkbox"
											name="merk[]"
											value="<?php echo esc_attr( $merk->slug ); ?>"
											<?php checked( in_array( $merk->slug, $actieve_merken, true ) ); ?>
										>
										<span><?php echo esc_html( $merk->name ); ?></span>
										<em>(<?php echo (int) $merk->count; ?>)</em>
									</label>
								</li>
							<?php endforeach; ?>
						</ul>
						<?php if ( $actieve_merken ) : ?>
							<a class="hdp-shop-merken-wissen" href="<?php echo esc_url( remove_query_arg( array( 'merk' ) ) ); ?>"><?php esc_html_e( 'Filter wissen', 'homburg-dealerportaal-theme' ); ?></a>
						<?php endif; ?>
					</form>
				</details>
			</aside>
		<?php endif; ?>
		<div class="hdp-shop-main">
			<?php if ( $actieve_merken ) : ?>
				<div class="hdp-shop-chips">
					<?php foreach ( $actieve_merken as $slug ) : ?>
						<?php
						$term_obj = ! is_wp_error( $merken ) ? wp_list_filter( $merken, array( 'slug' => $slug ) ) : array();
						$naam     = $term_obj ? reset( $term_obj )->name : $slug;
						?>
						<a class="hdp-shop-chip" href="<?php echo esc_url( homburg_wc_chip_url_zonder( $slug, $actieve_merken ) ); ?>">
							<?php echo esc_html( $naam ); ?>
							<span aria-hidden="true">&times;</span>
						</a>
					<?php endforeach; ?>
					<a class="hdp-shop-chip hdp-shop-chip--wis" href="<?php echo esc_url( remove_query_arg( 'merk' ) ); ?>"><?php esc_html_e( 'Alles wissen', 'homburg-dealerportaal-theme' ); ?></a>
				</div>
			<?php endif; ?>
			<div class="hdp-shop-balk">
				<form class="hdp-shop-zoeken" method="get">
					<?php foreach ( $actieve_merken as $slug ) : ?>
						<input type="hidden" name="merk[]" value="<?php echo esc_attr( $slug ); ?>">
					<?php endforeach; ?>
					<div class="hdp-shop-zoeken__veld">
						<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
						<input
							type="search"
							name="zoek"
							value="<?php echo esc_attr( $zoekterm ); ?>"
							placeholder="<?php esc_attr_e( 'Zoek op naam of artikelnummer…', 'homburg-dealerportaal-theme' ); ?>"
						>
					</div>
					<button type="submit"><?php esc_html_e( 'Zoeken', 'homburg-dealerportaal-theme' ); ?></button>
				</form>
				<div class="hdp-shop-weergave" role="group" aria-label="<?php esc_attr_e( 'Weergave', 'homburg-dealerportaal-theme' ); ?>">
					<button type="button" class="hdp-weergave-knop" data-hdp-weergave="raster" aria-label="<?php esc_attr_e( 'Rasterweergave', 'homburg-dealerportaal-theme' ); ?>">
						<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="1" y="1" width="6" height="6" rx="1" fill="currentColor"/><rect x="9" y="1" width="6" height="6" rx="1" fill="currentColor"/><rect x="1" y="9" width="6" height="6" rx="1" fill="currentColor"/><rect x="9" y="9" width="6" height="6" rx="1" fill="currentColor"/></svg>
					</button>
					<button type="button" class="hdp-weergave-knop" data-hdp-weergave="lijst" aria-label="<?php esc_attr_e( 'Lijstweergave', 'homburg-dealerportaal-theme' ); ?>">
						<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="1" y="1.5" width="14" height="3" rx="1" fill="currentColor"/><rect x="1" y="6.5" width="14" height="3" rx="1" fill="currentColor"/><rect x="1" y="11.5" width="14" height="3" rx="1" fill="currentColor"/></svg>
					</button>
				</div>
	<?php
}

add_action( 'woocommerce_before_shop_loop', 'homburg_wc_shop_balk_breek', 15 );
/**
 * Een expliciet regeleinde tussen de zoekrij (zoeken + weergave) en de
 * resultaattelling/sortering-rij, die WooCommerce's eigen hooks op
 * prioriteit 20/30 direct als kind van ".hdp-shop-balk" toevoegen — zonder
 * dit breekpunt zou flexbox proberen alle vier op één regel te passen.
 */
function homburg_wc_shop_balk_breek() {
	if ( ! is_shop() && ! is_product_taxonomy() ) {
		return;
	}
	echo '<div class="hdp-shop-balk__breek" aria-hidden="true"></div>';
}

add_action( 'woocommerce_before_shop_loop', 'homburg_wc_shop_balk_sluiten', 35 );
/**
 * Sluit ".hdp-shop-balk" — na resultaattelling (prioriteit 20) en
 * sortering (30), vóór het productraster zelf. Zo vallen zoeken,
 * weergaveschakelaar, resultaattelling én sortering samen binnen dezelfde
 * donkere "Homburg-balk".
 */
function homburg_wc_shop_balk_sluiten() {
	if ( ! is_shop() && ! is_product_taxonomy() ) {
		return;
	}
	echo '</div>';
}

add_action( 'woocommerce_before_shop_loop', 'homburg_wc_lijst_kolomkoppen', 40 );
/**
 * Kolomkoppen boven de lijstweergave.
 *
 * In een lijst staat per rij alleen de waarde, niet waar die voor staat —
 * zonder kop is de rechterkolom "een getal" in plaats van "de prijs". In
 * de rasterweergave staat dit blok op display:none (zie woocommerce.css).
 *
 * aria-hidden: elke rij noemt zelf al artikelnummer, naam en prijs; de
 * koppen zijn puur visueel en zouden voorgelezen alleen ruis zijn.
 */
function homburg_wc_lijst_kolomkoppen() {
	if ( ! is_shop() && ! is_product_taxonomy() ) {
		return;
	}
	?>
	<div class="hdp-lijst-kop" aria-hidden="true">
		<span><?php esc_html_e( 'Nummer', 'homburg-dealerportaal-theme' ); ?></span>
		<span><?php esc_html_e( 'Omschrijving', 'homburg-dealerportaal-theme' ); ?></span>
		<span><?php esc_html_e( 'Merk', 'homburg-dealerportaal-theme' ); ?></span>
		<span class="hdp-lijst-kop__prijs"><?php esc_html_e( 'Prijs excl.', 'homburg-dealerportaal-theme' ); ?></span>
		<span></span>
	</div>
	<?php
}

add_filter( 'loop_shop_per_page', 'homburg_wc_aantal_per_pagina', 20 );
/**
 * 48 onderdelen per pagina in plaats van 16.
 *
 * Een rij in de lijst is een fractie van de hoogte van een tegel, dus er
 * passen er veel meer op een scherm. Met 16 per pagina waren er voor 250
 * onderdelen zestien pagina te doorlopen; nu zes.
 */
function homburg_wc_aantal_per_pagina() {
	return 48;
}

/**
 * Welke weergave deze aanvraag krijgt: 'lijst' (standaard) of 'raster'.
 *
 * Onderdelen hebben vrijwel nooit een foto, dus een tegel reserveert een
 * groot vlak voor een placeholder met het artikelnummer erin — een nummer
 * dat er twee regels lager nog een keer staat. Een lijst toont hetzelfde
 * in een fractie van de ruimte; het raster blijft bestaan voor wie het
 * liever zo ziet.
 *
 * Dit wordt server-side bepaald zodat de pagina meteen goed staat; de
 * voorkeur van een bezoeker die zelf raster koos staat in localStorage en
 * wordt daarna door de JS hieronder toegepast.
 */
function homburg_wc_weergave() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- alleen lezen, geen schrijfactie.
	$uit_url = isset( $_GET['weergave'] ) ? sanitize_key( wp_unslash( $_GET['weergave'] ) ) : '';

	return 'raster' === $uit_url ? 'raster' : 'lijst';
}

add_filter( 'woocommerce_product_loop_start', 'homburg_wc_lijst_class_op_loop' );
/**
 * Zet de lijst-class meteen in de HTML in plaats van hem door JavaScript
 * te laten toevoegen — anders ziet een bezoeker eerst het raster en klapt
 * de pagina daarna om.
 */
function homburg_wc_lijst_class_op_loop( $html ) {
	if ( ( ! is_shop() && ! is_product_taxonomy() ) || 'lijst' !== homburg_wc_weergave() ) {
		return $html;
	}

	return str_replace( 'class="products', 'class="products hdp-lijst-weergave', $html );
}

add_filter( 'body_class', 'homburg_wc_lijst_class_op_body' );
/**
 * Dezelfde keuze op <body>, zodat de kolomkoppen — die buiten ul.products
 * staan en er in de HTML aan voorafgaan — zich erop kunnen richten. CSS
 * kan niet terugkijken naar een later element.
 */
function homburg_wc_lijst_class_op_body( $classes ) {
	if ( ( is_shop() || is_product_taxonomy() ) && 'lijst' === homburg_wc_weergave() ) {
		$classes[] = 'hdp-shop-lijst';
	}

	return $classes;
}

add_action( 'woocommerce_after_shop_loop', 'homburg_wc_shop_werkbalk_sluiten', 100 );
/**
 * Sluit de twee div's die homburg_wc_shop_werkbalk_open() opent — na de
 * paginering, die immers ook op deze hook (prioriteit 10) hangt.
 */
function homburg_wc_shop_werkbalk_sluiten() {
	if ( ! is_shop() && ! is_product_taxonomy() ) {
		return;
	}
	?>
		</div>
	</div>
	<?php
}

add_action( 'wp_enqueue_scripts', 'homburg_wc_shop_inline_js', 30 );
/**
 * Onthoudt de gekozen weergave (raster/lijst) per bezoeker via
 * localStorage en past 'm meteen toe door een class op ul.products te
 * zetten — geen aparte pagina-herlaad nodig.
 */
function homburg_wc_shop_inline_js() {
	if ( ! is_shop() && ! is_product_taxonomy() ) {
		return;
	}

	$js = <<<'JS'
(function () {
	// Op mobiel (zelfde 780px-omslagpunt als .hdp-shop-layout/.hdp-shop-merken
	// in woocommerce.css, waar het filter van zijbalk naar een gestapeld blok
	// boven de resultaten gaat) staat het merkenfilter standaard open — dat
	// duwt zoekbalk, sortering en alle producten onder 14 aanvinkopties.
	// Hier alsnog dichtklappen; de <details>/<summary> zelf (incl. het
	// pijltje-icoon) is al langer klikbaar om 'm weer open te klappen.
	var merkDetails = document.querySelector('.hdp-shop-merken-details');
	if (merkDetails && window.matchMedia('(max-width: 780px)').matches) {
		merkDetails.removeAttribute('open');
	}

	var SLEUTEL = 'hdp-shop-weergave';
	var lijst = document.querySelector('ul.products');
	var knoppen = document.querySelectorAll('[data-hdp-weergave]');
	if (!lijst || !knoppen.length) { return; }

	function toepassen(weergave) {
		lijst.classList.toggle('hdp-lijst-weergave', weergave === 'lijst');
		// Ook op <body>, voor de kolomkoppen die vóór ul.products staan.
		document.body.classList.toggle('hdp-shop-lijst', weergave === 'lijst');
		knoppen.forEach(function (knop) {
			knop.classList.toggle('is-actief', knop.dataset.hdpWeergave === weergave);
			knop.setAttribute('aria-pressed', knop.dataset.hdpWeergave === weergave ? 'true' : 'false');
		});
	}

	// Lijst is de standaard en staat al in de HTML (zie homburg_wc_weergave);
	// dit herstelt alleen de keuze van een bezoeker die zelf raster koos.
	var uitUrl = new URLSearchParams(window.location.search).get('weergave');
	var opgeslagen = 'lijst';
	try {
		opgeslagen = uitUrl || localStorage.getItem(SLEUTEL) || 'lijst';
	} catch (e) {}
	toepassen(opgeslagen);

	// Het aantalveld in een lijstrij doorgeven aan WooCommerce.
	//
	// De bestelknop in de kaart is een gewone link (content-product.php geeft
	// een eigen class mee, waardoor WooCommerce's ajax_add_to_cart-classes
	// vervallen): klikken navigeert naar ?add-to-cart=123. Het aantal moet
	// dus in die URL staan. data-quantity wordt voor de zekerheid ook gezet,
	// zodat het blijft werken als de knop later wél via AJAX gaat.
	document.addEventListener('input', function (e) {
		var veld = e.target.closest ? e.target.closest('[data-hdp-aantal]') : null;
		if (!veld) { return; }
		var rij = veld.closest('.hdp-card__body');
		var knop = rij && rij.querySelector('.hdp-card__btn');
		if (!knop || !knop.getAttribute('href')) { return; }

		var aantal = parseInt(veld.value, 10);
		if (!(aantal > 0)) { aantal = 1; }
		knop.dataset.quantity = aantal;

		var url = new URL(knop.getAttribute('href'), window.location.href);
		url.searchParams.set('quantity', aantal);
		knop.setAttribute('href', url.pathname + url.search);
	});

	// Merkfilter direct toepassen bij het aan-/uitvinken.
	var merkForm = document.querySelector('.hdp-shop-merken-form');
	if (merkForm) {
		merkForm.querySelectorAll('input[type="checkbox"]').forEach(function (vak) {
			vak.addEventListener('change', function () { merkForm.requestSubmit ? merkForm.requestSubmit() : merkForm.submit(); });
		});
	}

	knoppen.forEach(function (knop) {
		knop.addEventListener('click', function () {
			var weergave = knop.dataset.hdpWeergave;
			toepassen(weergave);
			try { localStorage.setItem(SLEUTEL, weergave); } catch (e) {}
		});
	});

	// Duidelijke, korte bevestiging op de bestelknop zelf i.p.v. alleen het
	// kleine "Bekijk winkelwagen"-linkje ernaast — WooCommerce's eigen
	// jQuery-event na een geslaagde AJAX-toevoeging vanuit het raster/lijst.
	if (window.jQuery) {
		jQuery(document.body).on('added_to_cart', function (e, fragments, cart_hash, $knop) {
			if (!$knop || !$knop.length) { return; }
			var knop = $knop[0];
			if (!knop.dataset.hdpOrigineel) { knop.dataset.hdpOrigineel = knop.textContent; }
			knop.textContent = 'Toegevoegd ✓';
			knop.classList.add('hdp-net-toegevoegd');
			clearTimeout(knop._hdpTimer);
			knop._hdpTimer = setTimeout(function () {
				knop.textContent = knop.dataset.hdpOrigineel;
				knop.classList.remove('hdp-net-toegevoegd');
			}, 1700);
		});
	}
})();
JS;

	wp_register_script( 'hdp-wc-shop', '', array( 'jquery' ), wp_get_theme()->get( 'Version' ), true );
	wp_enqueue_script( 'hdp-wc-shop' );
	wp_add_inline_script( 'hdp-wc-shop', $js );
}
