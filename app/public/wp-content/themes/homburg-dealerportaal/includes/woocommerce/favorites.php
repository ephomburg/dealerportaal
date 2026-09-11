<?php
/**
 * Persoonlijke favorietenlijst per dealer — voor onderdelen die vaak
 * besteld worden. Opgeslagen als user meta (array van product-ID's), geen
 * eigen tabel nodig. Het hartje op de kaart/productpagina wisselt de
 * status via AJAX; "Mijn favorieten" (My Account-tabblad) toont ze in
 * dezelfde kaartweergave als de winkel.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

const HDP_FAVORIETEN_META = '_hdp_favorieten';

function homburg_wc_favoriete_ids( $user_id = 0 ) {
	$user_id = $user_id ?: get_current_user_id();
	if ( ! $user_id ) {
		return array();
	}
	$ids = get_user_meta( $user_id, HDP_FAVORIETEN_META, true );
	return is_array( $ids ) ? array_map( 'intval', $ids ) : array();
}

function homburg_wc_is_favoriet( $product_id, $user_id = 0 ) {
	return in_array( (int) $product_id, homburg_wc_favoriete_ids( $user_id ), true );
}

add_action( 'wp_ajax_hdp_favoriet_wisselen', 'homburg_wc_favoriet_wisselen_ajax' );
function homburg_wc_favoriet_wisselen_ajax() {
	check_ajax_referer( 'hdp-favorieten', 'nonce' );

	$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
	$user_id    = get_current_user_id();

	if ( ! $product_id || ! $user_id ) {
		wp_send_json_error();
	}

	$ids = homburg_wc_favoriete_ids( $user_id );
	if ( in_array( $product_id, $ids, true ) ) {
		$ids       = array_values( array_diff( $ids, array( $product_id ) ) );
		$nu_favoriet = false;
	} else {
		$ids[]       = $product_id;
		$nu_favoriet = true;
	}
	update_user_meta( $user_id, HDP_FAVORIETEN_META, $ids );

	wp_send_json_success( array( 'favoriet' => $nu_favoriet ) );
}

/**
 * Het hartje zelf — herbruikbaar voor zowel de kaart (content-product.php)
 * als de losse productpagina. Niet-ingelogde bezoekers zien 'm niet (een
 * niet-goedgekeurde dealer kan toch niet bestellen, dus favorieten
 * bewaren is dan ook niet relevant).
 */
function homburg_wc_favoriet_knop( $product_id, $extra_class = '', $met_tekst = false ) {
	if ( ! is_user_logged_in() ) {
		return;
	}

	$favoriet   = homburg_wc_is_favoriet( $product_id );
	$tekst_aan  = __( 'Bewaard als favoriet', 'homburg-dealerportaal-theme' );
	$tekst_uit  = __( 'Bewaar als favoriet', 'homburg-dealerportaal-theme' );
	$tekst      = $favoriet ? $tekst_aan : $tekst_uit;
	printf(
		'<button type="button" class="hdp-favoriet-knop %1$s%2$s" data-hdp-favoriet-product="%3$d" data-hdp-tekst-aan="%4$s" data-hdp-tekst-uit="%5$s" aria-pressed="%6$s" aria-label="%7$s">%8$s%9$s</button>',
		esc_attr( $extra_class ),
		$favoriet ? ' is-favoriet' : '',
		(int) $product_id,
		esc_attr( $tekst_aan ),
		esc_attr( $tekst_uit ),
		$favoriet ? 'true' : 'false',
		$met_tekst ? esc_attr( $tekst ) : esc_attr__( 'Favoriet', 'homburg-dealerportaal-theme' ),
		'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>', // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG.
		$met_tekst ? '<span class="hdp-favoriet-knop__tekst">' . esc_html( $tekst ) . '</span>' : ''
	);
}

add_action( 'woocommerce_single_product_summary', 'homburg_wc_favoriet_op_productpagina', 8 );
/**
 * Op de losse productpagina vlak na de artikelnummer-regel (prioriteit 7,
 * zie product-single.php) — een rustige, ongebruikte plek in plaats van
 * ergens tussen merk/titel te dringen.
 */
function homburg_wc_favoriet_op_productpagina() {
	global $product;
	if ( $product ) {
		homburg_wc_favoriet_knop( $product->get_id(), 'hdp-favoriet-knop--pdp', true );
	}
}

add_action( 'wp_enqueue_scripts', 'homburg_wc_favorieten_enqueue', 30 );
function homburg_wc_favorieten_enqueue() {
	if ( ! is_user_logged_in() || ( ! is_woocommerce() && ! is_account_page() ) ) {
		return;
	}

	$js = <<<'JS'
(function () {
	document.addEventListener('click', function (e) {
		var knop = e.target.closest('.hdp-favoriet-knop');
		if (!knop) { return; }
		e.preventDefault();

		var productId = knop.dataset.hdpFavorietProduct;
		var tekstVeld = knop.querySelector('.hdp-favoriet-knop__tekst');
		var was = knop.classList.contains('is-favoriet');

		function badgeBijwerken(erbij) {
			var badge = document.querySelector('.hdp-favorieten-aantal');
			if (!badge) { return; }
			var aantal = Math.max(0, (parseInt(badge.textContent, 10) || 0) + (erbij ? 1 : -1));
			badge.textContent = aantal;
			badge.hidden = aantal === 0;
		}

		function toon(actief) {
			knop.classList.toggle('is-favoriet', actief);
			knop.setAttribute('aria-pressed', String(actief));
			if (tekstVeld) { tekstVeld.textContent = actief ? knop.dataset.hdpTekstAan : knop.dataset.hdpTekstUit; }
		}

		toon(!was);
		badgeBijwerken(!was);

		var data = new URLSearchParams();
		data.set('action', 'hdp_favoriet_wisselen');
		data.set('nonce', HDP_FAVORIETEN.nonce);
		data.set('product_id', productId);

		fetch(HDP_FAVORIETEN.ajaxUrl, { method: 'POST', body: data })
			.then(function (r) { return r.json(); })
			.then(function (res) {
				if (!res.success) {
					toon(was); // terugdraaien als het serverzijdig toch mislukte.
					badgeBijwerken(was);
				}
			})
			.catch(function () {
				toon(was);
				badgeBijwerken(was);
			});
	});
})();
JS;

	wp_register_script( 'hdp-wc-favorieten', '', array(), wp_get_theme()->get( 'Version' ), true );
	wp_enqueue_script( 'hdp-wc-favorieten' );
	wp_localize_script(
		'hdp-wc-favorieten',
		'HDP_FAVORIETEN',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'hdp-favorieten' ),
		)
	);
	wp_add_inline_script( 'hdp-wc-favorieten', $js );
}

/* ==========================================================================
   "MIJN FAVORIETEN" — My Account-tabblad, zelfde opzet als "Snel bestellen"
   (bulk-order.php): eigen eindpunt + menu-item + titel.
   ========================================================================== */

add_action( 'init', 'homburg_wc_favorieten_endpoint' );
function homburg_wc_favorieten_endpoint() {
	add_rewrite_endpoint( 'favorieten', EP_ROOT | EP_PAGES );
}

add_filter( 'woocommerce_get_query_vars', 'homburg_wc_favorieten_query_var' );
function homburg_wc_favorieten_query_var( $vars ) {
	$vars['favorieten'] = 'favorieten';
	return $vars;
}

add_action( 'init', 'homburg_wc_favorieten_flush', 20 );
function homburg_wc_favorieten_flush() {
	if ( ! get_option( 'hdp_favorieten_flushed' ) ) {
		flush_rewrite_rules();
		update_option( 'hdp_favorieten_flushed', 1 );
	}
}

add_filter( 'woocommerce_endpoint_favorieten_title', 'homburg_wc_favorieten_titel' );
function homburg_wc_favorieten_titel() {
	return __( 'Mijn favorieten', 'homburg-dealerportaal-theme' );
}

add_filter( 'woocommerce_account_menu_items', 'homburg_wc_favorieten_menu' );
function homburg_wc_favorieten_menu( $items ) {
	$nieuw = array();
	foreach ( $items as $key => $label ) {
		$nieuw[ $key ] = $label;
		if ( 'snelbestellen' === $key ) {
			$nieuw['favorieten'] = __( 'Mijn favorieten', 'homburg-dealerportaal-theme' );
		}
	}
	return $nieuw;
}

add_action( 'woocommerce_account_favorieten_endpoint', 'homburg_wc_favorieten_inhoud' );
/**
 * Deze pagina gaat uitsluitend over favoriete producten uit de webshop —
 * geen zin om daar het hele "Mijn account"-menu (bestellingen, downloads,
 * adressen, uitloggen...) naast te tonen. De navigatie wordt daarom via
 * CSS verborgen (zie .woocommerce-favorieten in woocommerce.css) en de
 * inhoud krijgt de volle breedte, net als de winkelpagina zelf.
 */
function homburg_wc_favorieten_inhoud() {
	$ids = homburg_wc_favoriete_ids();
	?>
	<div class="hdp-favorieten-pagina">
		<?php echo homburg_wc_terug_naar_winkel_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- escapet intern. ?>
		<h1><?php esc_html_e( 'Mijn favorieten', 'homburg-dealerportaal-theme' ); ?></h1>
		<?php if ( ! $ids ) : ?>
			<p class="hdp-favorieten-leeg"><?php esc_html_e( 'Je hebt nog geen favorieten. Klik op het hartje bij een product om het hier te bewaren.', 'homburg-dealerportaal-theme' ); ?></p>
			<?php return; ?>
		<?php endif; ?>
		<?php
		$producten = new WP_Query(
			array(
				'post_type'      => 'product',
				'post__in'       => $ids,
				'orderby'        => 'post__in',
				'posts_per_page' => -1,
			)
		);

		if ( $producten->have_posts() ) :
			?>
			<p class="hdp-favorieten-aantal-tekst">
				<?php
				printf(
					/* translators: %d: aantal favorieten */
					esc_html( _n( '%d favoriet product.', '%d favoriete producten.', $producten->post_count, 'homburg-dealerportaal-theme' ) ),
					(int) $producten->post_count
				);
				?>
			</p>
			<ul class="products hdp-favorieten-lijst">
				<?php
				while ( $producten->have_posts() ) {
					$producten->the_post();
					wc_get_template_part( 'content', 'product' );
				}
				?>
			</ul>
			<?php
			wp_reset_postdata();
		endif;
		?>
	</div>
	<?php
}
