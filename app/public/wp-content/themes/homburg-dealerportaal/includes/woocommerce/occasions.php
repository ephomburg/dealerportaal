<?php
/**
 * Occasions en restpartijen: losse partijen die Homburg zelf aanbiedt,
 * naast het gewone assortiment uit PowerAll. Bijvoorbeeld tien stalen
 * platen, of een gebruikt onderdeel dat van een machine is gekomen.
 *
 * Een occasion is een gewoon WooCommerce-product in de categorie
 * "Occasions & restpartijen". Daarbij:
 * - een veld "Staat" (nieuw / als nieuw / gebruikt) met een korte
 *   toelichting, in het productscherm onder "Algemeen";
 * - op = op: de voorraad wordt altijd bijgehouden, en wat uitverkocht is
 *   verdwijnt uit de winkel;
 * - een label en "Nog 7 beschikbaar" op de kaart en de productpagina;
 * - een eigen onderdeel "Occasions" in het menu links, zolang er iets te
 *   koop is (zie HDP_Zijbalk), en een regel in "Nieuw voor jou".
 *
 * PowerAll: elke occasion krijgt het kenmerk _hdp_handmatig. Een koppeling
 * die het assortiment inleest, moet producten met dat kenmerk overslaan
 * (homburg_wc_is_handmatig()) — anders ruimt hij ze op omdat ze niet in
 * PowerAll staan.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

const HOMBURG_WC_OCCASIONS         = 'occasions';
const HOMBURG_WC_HANDMATIG         = '_hdp_handmatig';
const HOMBURG_WC_STAAT             = '_hdp_staat';
const HOMBURG_WC_STAAT_TOELICHTING = '_hdp_staat_toelichting';

/** De mogelijke staten, met hun tekstsleutel in HDP_I18N. */
function homburg_wc_staten() {
	return array(
		'nieuw'     => homburg_wc_tekst( 'occasion_staat_nieuw', 'Nieuw' ),
		'als-nieuw' => homburg_wc_tekst( 'occasion_staat_als_nieuw', 'Als nieuw' ),
		'gebruikt'  => homburg_wc_tekst( 'occasion_staat_gebruikt', 'Gebruikt' ),
	);
}

/* --- De categorie -------------------------------------------------------- */

add_action( 'init', 'homburg_wc_occasions_categorie_klaarzetten', 20 );
/**
 * Zorgt dat de categorie er is, ook op live na een deploy: daar draait geen
 * installatiestap. Daarna één opvraag van een term per pagina.
 */
function homburg_wc_occasions_categorie_klaarzetten() {
	if ( get_term_by( 'slug', HOMBURG_WC_OCCASIONS, 'product_cat' ) ) {
		return;
	}
	wp_insert_term(
		'Occasions & restpartijen',
		'product_cat',
		array(
			'slug'        => HOMBURG_WC_OCCASIONS,
			'description' => 'Losse partijen en gebruikte onderdelen die Homburg zelf aanbiedt. Op = op.',
		)
	);
}

function homburg_wc_occasions_url() {
	$term = get_term_by( 'slug', HOMBURG_WC_OCCASIONS, 'product_cat' );
	$url  = $term ? get_term_link( $term ) : '';
	return is_wp_error( $url ) ? '' : (string) $url;
}

/** @param WC_Product|int $product */
function homburg_wc_is_occasion( $product ) {
	$id = $product instanceof WC_Product ? $product->get_id() : (int) $product;
	if ( $product instanceof WC_Product && $product->get_parent_id() ) {
		$id = $product->get_parent_id();
	}
	return $id && has_term( HOMBURG_WC_OCCASIONS, 'product_cat', $id );
}

/**
 * Handmatig in WooCommerce beheerd, niet uit PowerAll. Een toekomstige
 * PowerAll-koppeling moet deze producten overslaan, zowel bij bijwerken als
 * bij opruimen.
 */
function homburg_wc_is_handmatig( $product_id ) {
	return '1' === get_post_meta( (int) $product_id, HOMBURG_WC_HANDMATIG, true );
}

/** Staan we op de occasionspagina of op de pagina van een occasion? Voor het menu links. */
function homburg_wc_in_occasions() {
	if ( is_product_category( HOMBURG_WC_OCCASIONS ) ) {
		return true;
	}
	return is_product() && homburg_wc_is_occasion( get_queried_object_id() );
}

/** Hoeveel occasions er nu te koop zijn. */
function homburg_wc_aantal_occasions() {
	static $aantal = null;
	if ( null === $aantal ) {
		$aantal = count( homburg_wc_occasion_ids( 'instock' ) );
	}
	return $aantal;
}

/**
 * @param string $voorraad 'instock' of 'outofstock'.
 * @return int[]
 */
function homburg_wc_occasion_ids( $voorraad ) {
	return get_posts(
		array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- kleine set.
				array(
					'taxonomy' => 'product_cat',
					'field'    => 'slug',
					'terms'    => HOMBURG_WC_OCCASIONS,
				),
			),
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- kleine set.
				array(
					'key'   => '_stock_status',
					'value' => $voorraad,
				),
			),
		)
	);
}

/* --- Productscherm in wp-admin ------------------------------------------- */

add_action( 'woocommerce_product_options_general_product_data', 'homburg_wc_occasion_velden' );
function homburg_wc_occasion_velden() {
	echo '<div class="options_group">';
	woocommerce_wp_select(
		array(
			'id'          => HOMBURG_WC_STAAT,
			'label'       => 'Staat (occasion)',
			'options'     => array( '' => '—' ) + homburg_wc_staten(),
			'desc_tip'    => true,
			'description' => 'Alleen voor producten in de categorie "Occasions & restpartijen". Staat op de kaart en de productpagina.',
		)
	);
	woocommerce_wp_text_input(
		array(
			'id'          => HOMBURG_WC_STAAT_TOELICHTING,
			'label'       => 'Toelichting staat',
			'placeholder' => 'Bijv. lichte roestplekken, verder gaaf',
			'desc_tip'    => true,
			'description' => 'Kort; staat op de productpagina onder de staat.',
		)
	);
	woocommerce_wp_checkbox(
		array(
			'id'          => HOMBURG_WC_HANDMATIG,
			'label'       => 'Handmatig beheerd',
			'cbvalue'     => '1',
			'description' => 'Niet uit PowerAll: wordt bij het inlezen van het assortiment overgeslagen. Bij een occasion staat dit vanzelf aan.',
		)
	);
	echo '</div>';
}

add_action( 'woocommerce_admin_process_product_object', 'homburg_wc_occasion_opslaan' );
/**
 * Slaat de velden op, en houdt een occasion "op = op": zonder voorraadbeheer
 * telt WooCommerce niet af en blijft een verkochte partij te koop staan.
 */
function homburg_wc_occasion_opslaan( $product ) {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce controleert de nonce van het productscherm al.
	$staat = isset( $_POST[ HOMBURG_WC_STAAT ] ) ? sanitize_key( wp_unslash( $_POST[ HOMBURG_WC_STAAT ] ) ) : '';
	$product->update_meta_data( HOMBURG_WC_STAAT, isset( homburg_wc_staten()[ $staat ] ) ? $staat : '' );

	$toelichting = isset( $_POST[ HOMBURG_WC_STAAT_TOELICHTING ] ) ? sanitize_text_field( wp_unslash( $_POST[ HOMBURG_WC_STAAT_TOELICHTING ] ) ) : '';
	$product->update_meta_data( HOMBURG_WC_STAAT_TOELICHTING, $toelichting );

	$handmatig = ! empty( $_POST[ HOMBURG_WC_HANDMATIG ] );
	// phpcs:enable

	// De categorie komt uit hetzelfde formulier; die is op dit moment nog
	// niet opgeslagen, dus hier uit de invoer lezen.
	$occasion = homburg_wc_occasion_in_invoer() || homburg_wc_is_occasion( $product );

	if ( $occasion ) {
		$handmatig = true;
		homburg_wc_occasion_voorraad_aan( $product );
	}

	$product->update_meta_data( HOMBURG_WC_HANDMATIG, $handmatig ? '1' : '' );
}

/** Staat het vinkje bij de categorie Occasions aan in het productformulier? */
function homburg_wc_occasion_in_invoer() {
	$term = get_term_by( 'slug', HOMBURG_WC_OCCASIONS, 'product_cat' );
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- zie homburg_wc_occasion_opslaan().
	$gekozen = isset( $_POST['tax_input']['product_cat'] ) ? array_map( 'intval', (array) wp_unslash( $_POST['tax_input']['product_cat'] ) ) : array();
	return $term && in_array( (int) $term->term_id, $gekozen, true );
}

/** Voorraadbeheer aan; staat er nog geen aantal, dan 1 (één stuk). */
function homburg_wc_occasion_voorraad_aan( $product ) {
	if ( ! $product->get_manage_stock() ) {
		$product->set_manage_stock( true );
		if ( null === $product->get_stock_quantity() ) {
			$product->set_stock_quantity( 1 );
		}
	}
	// Geen nabestellingen: wat op is, is op.
	$product->set_backorders( 'no' );
}

/* --- Uitverkocht = weg ---------------------------------------------------- */

add_action( 'pre_get_posts', 'homburg_wc_uitverkochte_occasions_weg', 99 );
/**
 * Een uitverkochte occasion komt niet terug (anders dan een gewoon artikel,
 * dat weer aangevuld wordt), dus die hoort niet meer in de winkel.
 *
 * Na de zoekfunctie van de winkel (shop-filters.php): die zet post__in, en
 * dan negeert WordPress post__not_in. Daarom uit allebei halen.
 */
function homburg_wc_uitverkochte_occasions_weg( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! ( is_shop() || is_product_taxonomy() || is_search() ) ) {
		return;
	}
	$weg = homburg_wc_occasion_ids( 'outofstock' );
	if ( ! $weg ) {
		return;
	}
	$binnen = (array) $query->get( 'post__in' );
	if ( $binnen ) {
		$over = array_values( array_diff( array_map( 'intval', $binnen ), $weg ) );
		$query->set( 'post__in', $over ? $over : array( 0 ) );
	} else {
		$query->set( 'post__not_in', array_merge( (array) $query->get( 'post__not_in' ), $weg ) );
	}
}

add_filter( 'woocommerce_product_is_visible', 'homburg_wc_uitverkochte_occasion_onzichtbaar', 10, 2 );
/** Ook niet bij gerelateerde producten, in favorieten of in de zoekbalk. */
function homburg_wc_uitverkochte_occasion_onzichtbaar( $zichtbaar, $product_id ) {
	if ( $zichtbaar && homburg_wc_is_occasion( $product_id ) ) {
		$product = wc_get_product( $product_id );
		return $product && $product->is_in_stock();
	}
	return $zichtbaar;
}

/* --- Wat de dealer ziet --------------------------------------------------- */

/** "Nog 7 beschikbaar", of '' als dat niet bekend is. */
function homburg_wc_occasion_beschikbaar( $product ) {
	$aantal = $product->get_manage_stock() ? (int) $product->get_stock_quantity() : 0;
	if ( $aantal <= 0 ) {
		return '';
	}
	return 1 === $aantal
		? homburg_wc_tekst( 'occasion_nog_een', 'Nog 1 beschikbaar' )
		: sprintf( homburg_wc_tekst( 'occasion_nog_meer', 'Nog %d beschikbaar' ), $aantal );
}

function homburg_wc_occasion_staat( $product ) {
	$staat  = (string) $product->get_meta( HOMBURG_WC_STAAT );
	$staten = homburg_wc_staten();
	return isset( $staten[ $staat ] ) ? $staten[ $staat ] : '';
}

/** Het label op het beeldvlak van de kaart. */
function homburg_wc_occasion_vlag( $product ) {
	if ( ! homburg_wc_is_occasion( $product ) ) {
		return;
	}
	echo '<span class="hdp-card__flag hdp-card__flag--occasion">' . esc_html( homburg_wc_tekst( 'occasion_label', 'Occasion' ) ) . '</span>';
}

/** Staat en "nog x beschikbaar" onder de prijs op de kaart. */
function homburg_wc_occasion_kaartregel( $product ) {
	if ( ! homburg_wc_is_occasion( $product ) ) {
		return;
	}
	$delen = array_filter( array( homburg_wc_occasion_staat( $product ), homburg_wc_occasion_beschikbaar( $product ) ) );
	if ( ! $delen ) {
		return;
	}
	echo '<span class="hdp-card__occasion">' . esc_html( implode( ' · ', $delen ) ) . '</span>';
}

add_action( 'woocommerce_single_product_summary', 'homburg_wc_occasion_op_productpagina', 8 );
/** Op de productpagina, onder het artikelnummer: label, staat met toelichting, en hoeveel er nog zijn. */
function homburg_wc_occasion_op_productpagina() {
	global $product;
	if ( ! $product || ! homburg_wc_is_occasion( $product ) ) {
		return;
	}
	$staat       = homburg_wc_occasion_staat( $product );
	$toelichting = (string) $product->get_meta( HOMBURG_WC_STAAT_TOELICHTING );
	$beschikbaar = homburg_wc_occasion_beschikbaar( $product );
	?>
	<div class="hdp-wc-occasion">
		<span class="hdp-wc-occasion-label"><?php echo esc_html( homburg_wc_tekst( 'occasion_label', 'Occasion' ) ); ?></span>
		<?php if ( $staat ) : ?>
			<p class="hdp-wc-occasion-staat">
				<strong><?php echo esc_html( homburg_wc_tekst( 'occasion_staat', 'Staat' ) ); ?>:</strong>
				<?php echo esc_html( $staat ); ?><?php echo $toelichting ? ' — ' . esc_html( $toelichting ) : ''; ?>
			</p>
		<?php endif; ?>
		<?php if ( $beschikbaar ) : ?>
			<p class="hdp-wc-occasion-voorraad"><?php echo esc_html( $beschikbaar ); ?> · <?php echo esc_html( homburg_wc_tekst( 'occasion_op_is_op', 'Op = op' ) ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

add_filter( 'woocommerce_quantity_input_max', 'homburg_wc_occasion_max_aantal', 10, 2 );
/** Niet meer in de winkelwagen kunnen zetten dan er is. */
function homburg_wc_occasion_max_aantal( $max, $product ) {
	if ( $product && homburg_wc_is_occasion( $product ) && $product->get_manage_stock() ) {
		return max( 1, (int) $product->get_stock_quantity() );
	}
	return $max;
}

/**
 * Nieuwe occasions voor "Nieuw voor jou" op de startpagina (zie
 * HDP_Portal_Render). Zelfde venster als bij documenten.
 *
 * @param int $sinds Tijdstempel (UTC) van het laatste wegklikken; 0 = nooit.
 * @param int $dagen Hoe lang iets als nieuw geldt.
 * @return array<int, array{titel: string, sub: string, url: string, tijd: int}>
 */
function homburg_wc_nieuwe_occasions( $sinds, $dagen ) {
	$grens = max( (int) $sinds, time() - (int) $dagen * DAY_IN_SECONDS );
	$lijst = array();

	foreach ( homburg_wc_occasion_ids( 'instock' ) as $id ) {
		$tijd = (int) get_post_time( 'U', true, $id );
		if ( $tijd <= $grens ) {
			continue;
		}
		$product = wc_get_product( $id );
		if ( ! $product || ! $product->is_visible() ) {
			continue;
		}
		$lijst[] = array(
			'titel' => $product->get_name(),
			'sub'   => implode( ' · ', array_filter( array( homburg_wc_tekst( 'occasion_label', 'Occasion' ), homburg_wc_occasion_beschikbaar( $product ) ) ) ),
			'url'   => get_permalink( $id ),
			'tijd'  => $tijd,
		);
	}

	return $lijst;
}
