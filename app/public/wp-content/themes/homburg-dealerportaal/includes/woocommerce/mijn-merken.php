<?php
/**
 * "Mijn merken" in het accountmenu: voor welke merken deze dealer toegang
 * heeft. Die rechten bepalen welke documenten en berichten hij ziet, en
 * zonder dit overzicht was niet te zien waarom een document ontbrak.
 *
 * Alleen lezen: de merken worden door Homburg ingesteld, in het
 * gebruikersprofiel.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

add_action( 'init', 'homburg_wc_mijn_merken_endpoint' );
function homburg_wc_mijn_merken_endpoint() {
	add_rewrite_endpoint( 'mijn-merken', EP_ROOT | EP_PAGES );
}

add_filter( 'woocommerce_get_query_vars', 'homburg_wc_mijn_merken_query_var' );
function homburg_wc_mijn_merken_query_var( $vars ) {
	$vars['mijn-merken'] = 'mijn-merken';
	return $vars;
}

add_action( 'init', 'homburg_wc_mijn_merken_flush', 20 );
/** Eenmalig, zodat het adres meteen werkt; zie ook bulk-order.php. */
function homburg_wc_mijn_merken_flush() {
	if ( ! get_option( 'hdp_mijn_merken_flushed' ) ) {
		flush_rewrite_rules();
		update_option( 'hdp_mijn_merken_flushed', 1 );
	}
}

add_filter( 'woocommerce_endpoint_mijn-merken_title', 'homburg_wc_mijn_merken_titel' );
function homburg_wc_mijn_merken_titel() {
	return __( 'Mijn merken', 'homburg-dealerportaal-theme' );
}

add_filter( 'woocommerce_account_menu_items', 'homburg_wc_mijn_merken_menu', 20 );
/** Vlak na de favorieten. */
function homburg_wc_mijn_merken_menu( $items ) {
	$nieuw = array();
	foreach ( $items as $key => $label ) {
		$nieuw[ $key ] = $label;
		if ( 'favorieten' === $key ) {
			$nieuw['mijn-merken'] = __( 'Mijn merken', 'homburg-dealerportaal-theme' );
		}
	}
	return $nieuw;
}

add_action( 'woocommerce_account_mijn-merken_endpoint', 'homburg_wc_mijn_merken_inhoud' );
function homburg_wc_mijn_merken_inhoud() {
	$eigen = HDP_Merken::naar_array( get_user_meta( get_current_user_id(), 'hdp_merken', true ) );
	// Geen eigen merken: alleen de algemene documenten (zie HDP_Downloads_CPT::mag_merk_zien()).
	$merken = $eigen;
	?>
	<p class="hdp-mijn-merken-intro">
		<?php
		if ( $eigen ) {
			esc_html_e( 'Je hebt toegang tot de documenten van deze merken. Algemene documenten zie je altijd.', 'homburg-dealerportaal-theme' );
		} else {
			esc_html_e( 'Er zijn nog geen merken aan je account gekoppeld. Je ziet daarom alleen de algemene documenten en trainingen. Neem contact op met Homburg om je merken te laten toevoegen.', 'homburg-dealerportaal-theme' );
		}
		?>
	</p>

	<ul class="hdp-mijn-merken">
		<?php foreach ( $merken as $merk ) : ?>
			<?php $logo = HDP_Settings::merk_logo_url( $merk ); ?>
			<li>
				<?php if ( $logo ) : ?>
					<img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $merk ); ?>" loading="lazy">
				<?php else : ?>
					<?php echo esc_html( $merk ); ?>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>

	<?php
	$email    = HDP_Settings::get( 'contact_email' );
	$telefoon = HDP_Settings::get( 'contact_telefoon' );
	if ( $email || $telefoon ) :
		?>
		<p class="hdp-mijn-merken-contact">
			<?php esc_html_e( 'Klopt dit niet, of wil je een merk erbij? Neem contact op:', 'homburg-dealerportaal-theme' ); ?>
			<?php if ( $email ) : ?>
				<a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
			<?php endif; ?>
			<?php if ( $telefoon ) : ?>
				<?php echo $email ? '&middot;' : ''; ?>
				<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $telefoon ) ); ?>"><?php echo esc_html( $telefoon ); ?></a>
			<?php endif; ?>
		</p>
		<?php
	endif;
}
