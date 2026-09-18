<?php
/**
 * My Account Dashboard — Homburg-versie van dit WooCommerce-template.
 * Overschrijft de kale standaardtekst ("Vanaf je account dashboard kun
 * je...") door een welkomstregel, snelkoppelingen naar de belangrijkste
 * onderdelen (in dezelfde kaartstijl als het dealerportaal zelf) en een
 * voorproefje van de laatste bestellingen.
 *
 * @see wc_get_template() — dit bestand wint automatisch van
 *      woocommerce/templates/myaccount/dashboard.php zodra het hier staat.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hdp_allowed_html = array(
	'a' => array(
		'href' => array(),
	),
);
?>

<div class="hdp-dashboard">
	<p class="hdp-dashboard-groet">
		<?php
		printf(
			wp_kses( __( 'Hallo %1$s (niet %1$s? <a href="%2$s">Log uit</a>)', 'homburg-dealerportaal-theme' ), $hdp_allowed_html ),
			'<strong>' . esc_html( $current_user->display_name ) . '</strong>',
			esc_url( wc_logout_url() )
		);
		?>
	</p>

	<div class="hdp-dashboard-kaarten">
		<div class="hdp-kaart">
			<?php HDP_Icons::render_icoon( 'bestellen' ); ?>
			<h2><?php esc_html_e( 'Bestellingen', 'homburg-dealerportaal-theme' ); ?></h2>
			<p><?php esc_html_e( 'Bekijk je bestelgeschiedenis en de status van je orders.', 'homburg-dealerportaal-theme' ); ?></p>
			<a class="hdp-btn hdp-btn-klein hdp-btn-vol" href="<?php echo esc_url( wc_get_endpoint_url( 'orders' ) ); ?>"><?php esc_html_e( 'Naar bestellingen', 'homburg-dealerportaal-theme' ); ?></a>
		</div>

		<div class="hdp-kaart">
			<?php HDP_Icons::render_icoon( 'bestellen' ); ?>
			<h2><?php esc_html_e( 'Snel bestellen', 'homburg-dealerportaal-theme' ); ?></h2>
			<p><?php esc_html_e( 'Ken je de artikelnummers al? Plak of typ ze in één keer in je winkelmand.', 'homburg-dealerportaal-theme' ); ?></p>
			<a class="hdp-btn hdp-btn-klein hdp-btn-vol" href="<?php echo esc_url( wc_get_endpoint_url( 'snelbestellen' ) ); ?>"><?php esc_html_e( 'Snel bestellen', 'homburg-dealerportaal-theme' ); ?></a>
		</div>

		<div class="hdp-kaart">
			<?php HDP_Icons::render_icoon( 'hart' ); ?>
			<h2><?php esc_html_e( 'Mijn favorieten', 'homburg-dealerportaal-theme' ); ?></h2>
			<p><?php esc_html_e( 'De producten die je hebt bewaard om snel terug te vinden.', 'homburg-dealerportaal-theme' ); ?></p>
			<a class="hdp-btn hdp-btn-klein hdp-btn-vol" href="<?php echo esc_url( wc_get_endpoint_url( 'favorieten' ) ); ?>"><?php esc_html_e( 'Bekijk favorieten', 'homburg-dealerportaal-theme' ); ?></a>
		</div>

		<div class="hdp-kaart">
			<?php HDP_Icons::render_icoon( 'downloads' ); ?>
			<h2><?php esc_html_e( 'Downloads', 'homburg-dealerportaal-theme' ); ?></h2>
			<p><?php esc_html_e( 'Brochures, handleidingen en ander materiaal voor jouw merken.', 'homburg-dealerportaal-theme' ); ?></p>
			<a class="hdp-btn hdp-btn-klein hdp-btn-vol" href="<?php echo esc_url( home_url( '/downloads/' ) ); ?>"><?php esc_html_e( 'Naar downloads', 'homburg-dealerportaal-theme' ); ?></a>
		</div>

		<div class="hdp-kaart">
			<?php HDP_Icons::render_icoon( 'adres' ); ?>
			<h2><?php esc_html_e( 'Adressen', 'homburg-dealerportaal-theme' ); ?></h2>
			<p><?php esc_html_e( 'Beheer je verzend- en factuuradres.', 'homburg-dealerportaal-theme' ); ?></p>
			<a class="hdp-btn hdp-btn-klein hdp-btn-vol" href="<?php echo esc_url( wc_get_endpoint_url( 'edit-address' ) ); ?>"><?php esc_html_e( 'Naar adressen', 'homburg-dealerportaal-theme' ); ?></a>
		</div>

		<div class="hdp-kaart">
			<?php HDP_Icons::render_icoon( 'gebruiker' ); ?>
			<h2><?php esc_html_e( 'Accountdetails', 'homburg-dealerportaal-theme' ); ?></h2>
			<p><?php esc_html_e( 'Wijzig je naam, e-mailadres en wachtwoord.', 'homburg-dealerportaal-theme' ); ?></p>
			<a class="hdp-btn hdp-btn-klein hdp-btn-vol" href="<?php echo esc_url( wc_get_endpoint_url( 'edit-account' ) ); ?>"><?php esc_html_e( 'Naar accountdetails', 'homburg-dealerportaal-theme' ); ?></a>
		</div>
	</div>

	<?php
	// paginate=false + limit=3: alleen de 3 meest recente orders, geen
	// paginatelling nodig zoals bij de volledige bestellingenlijst.
	$hdp_recente_bestellingen = function_exists( 'wc_get_orders' ) ? wc_get_orders(
		array(
			'customer_id' => get_current_user_id(),
			'orderby'     => 'date',
			'order'       => 'DESC',
			'limit'       => 3,
		)
	) : array();
	?>

	<div class="hdp-dashboard-recent">
		<div class="hdp-dashboard-recent-kop">
			<h2><?php esc_html_e( 'Recente bestellingen', 'homburg-dealerportaal-theme' ); ?></h2>
			<?php if ( $hdp_recente_bestellingen ) : ?>
				<a href="<?php echo esc_url( wc_get_endpoint_url( 'orders' ) ); ?>"><?php esc_html_e( 'Bekijk alles', 'homburg-dealerportaal-theme' ); ?></a>
			<?php endif; ?>
		</div>

		<?php if ( ! $hdp_recente_bestellingen ) : ?>
			<?php
			echo HDP_Icons::render_lege_status( // phpcs:ignore WordPress.Security.EscapeOutput -- render_lege_status() escaped elk veld al zelf.
				'bestellen',
				__( 'Je hebt nog geen bestellingen geplaatst.', 'homburg-dealerportaal-theme' ),
				get_permalink( wc_get_page_id( 'shop' ) ),
				__( 'Ga naar de winkel', 'homburg-dealerportaal-theme' )
			);
			?>
		<?php else : ?>
			<div class="hdp-bestel-lijst">
				<?php foreach ( $hdp_recente_bestellingen as $hdp_bestelling ) : ?>
					<?php $hdp_status = $hdp_bestelling->get_status(); ?>
					<div class="hdp-bestel-item">
						<div class="hdp-bestel-info">
							<strong>#<?php echo esc_html( $hdp_bestelling->get_order_number() ); ?></strong>
							<span><?php echo esc_html( wc_format_datetime( $hdp_bestelling->get_date_created() ) ); ?></span>
						</div>
						<span class="hdp-bestel-status hdp-bestel-status-<?php echo esc_attr( $hdp_status ); ?>"><?php echo esc_html( wc_get_order_status_name( $hdp_status ) ); ?></span>
						<span class="hdp-bestel-totaal"><?php echo wp_kses_post( $hdp_bestelling->get_formatted_order_total() ); // phpcs:ignore WordPress.Security.EscapeOutput -- get_formatted_order_total() wordt door WooCommerce zelf al veilig samengesteld. ?></span>
						<a class="hdp-btn hdp-btn-klein" href="<?php echo esc_url( $hdp_bestelling->get_view_order_url() ); ?>"><?php esc_html_e( 'Bekijken', 'homburg-dealerportaal-theme' ); ?></a>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</div>
