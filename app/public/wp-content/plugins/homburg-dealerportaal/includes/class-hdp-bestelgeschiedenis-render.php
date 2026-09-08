<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rendert de bestelgeschiedenispagina: welkomstkop + een doorzoekbare,
 * op status filterbare, gepagineerde lijst van alle WooCommerce-bestellingen
 * van de ingelogde dealer, elk met een link naar de bijbehorende
 * ordergegevens in WooCommerce's eigen "Mijn account"-omgeving (die hoeft
 * dit portaal dus niet zelf te herbouwen).
 *
 * Anders dan HDP_Herbestellen (die alleen de recentste afgeronde
 * bestellingen toont om snel opnieuw te bestellen) toont deze pagina
 * bewust élke bestelling, in elke status, zodat een dealer hier ook een
 * mislukte of geannuleerde bestelling kan terugvinden.
 */
class HDP_Bestelgeschiedenis_Render {

	const PER_PAGINA = 10;

	public static function render_bestelgeschiedenis_pagina( $a ) {
		$mag_zien = is_user_logged_in() && HDP_Roles::mag_portaal_zien( get_current_user_id() );

		if ( ! $mag_zien ) {
			ob_start();
			HDP_Login::render_login( HDP_Login::login_foutmelding(), $a );
			return ob_get_clean();
		}

		$titel        = HDP_I18N::kies( $a['titel'], $a['titelFr'] );
		$omschrijving = HDP_I18N::kies( $a['omschrijving'], $a['omschrijvingFr'] );

		ob_start();
		?>
		<div class="hdp-portaal alignfull">
			<section class="hdp-welkom alignfull hdp-welkom-zonder-hero">
				<div class="hdp-welkom-inner">
					<a class="hdp-terug-boven" href="<?php echo esc_url( home_url( '/dealerportaal/' ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'terug_naar_portaal' ) ); ?></a>
					<div class="hdp-welkom-top">
						<h1><?php echo esc_html( $titel ); ?></h1>
						<a class="hdp-btn-logout" href="<?php echo esc_url( wp_logout_url( home_url( '/dealerportaal/' ) ) ); ?>"><?php echo HDP_Icons::svg_icoon( 'uitloggen' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?><?php echo esc_html( HDP_I18N::t( 'btn_uitloggen' ) ); ?></a>
					</div>
					<p><?php echo esc_html( $omschrijving ); ?></p>
				</div>
			</section>
			<section class="hdp-downloads">
				<?php echo self::render_bestellingen_lijst(); // phpcs:ignore WordPress.Security.EscapeOutput -- reeds ge-escaped in render_bestellingen_lijst(). ?>
				<p class="hdp-terug"><a class="hdp-btn hdp-btn-secundair" href="<?php echo esc_url( home_url( '/dealerportaal/' ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'terug_naar_portaal' ) ); ?></a></p>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_bestellingen_lijst() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return '<p class="hdp-nog-niet">' . esc_html( HDP_I18N::t( 'bestel_geen_webshop' ) ) . '</p>';
		}

		// Zoeken/filteren/paginering zijn puur leesgedrag (geen
		// state-wijziging), vandaar geen nonce nodig — zelfde afweging als
		// bij HDP_I18N::verwerk_taalwissel().
		$pagina        = isset( $_GET['hdp_bp'] ) ? max( 1, absint( wp_unslash( $_GET['hdp_bp'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$zoekterm      = isset( $_GET['hdp_bz'] ) ? sanitize_text_field( wp_unslash( $_GET['hdp_bz'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status_filter = isset( $_GET['hdp_bs'] ) ? sanitize_key( wp_unslash( $_GET['hdp_bs'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$statussen         = wc_get_order_statuses();
		$geldige_statussen = array_map(
			static function ( $sleutel ) {
				return str_replace( 'wc-', '', $sleutel );
			},
			array_keys( $statussen )
		);
		if ( '' !== $status_filter && ! in_array( $status_filter, $geldige_statussen, true ) ) {
			$status_filter = '';
		}

		$filter_actief = '' !== $zoekterm || '' !== $status_filter;

		$query_args = array(
			'customer_id' => get_current_user_id(),
			'status'      => $status_filter ? array( $status_filter ) : array_keys( $statussen ),
			'orderby'     => 'date',
			'order'       => 'DESC',
			'limit'       => self::PER_PAGINA,
			'paged'       => $pagina,
			'paginate'    => true,
		);
		if ( '' !== $zoekterm ) {
			// Een ordernummer is in standaard-WooCommerce gewoon het
			// post-ID — geen doorzoekbaar tekstveld, dus WooCommerce's
			// 's'-zoekparameter (die op facturatiegegevens zoekt) matcht
			// daar niet betrouwbaar op. Bij een zuiver numerieke zoekterm
			// daarom filteren op exact ID via post__in; anders op naam via
			// 's'. Combineert in beide gevallen correct met de
			// customer_id-restrictie hierboven (WP_Query AND'ed de args),
			// dus een dealer kan zo nooit andermans ordernummer raden.
			if ( ctype_digit( $zoekterm ) ) {
				$query_args['post__in'] = array( (int) $zoekterm );
			} else {
				$query_args['s'] = $zoekterm;
			}
		}

		$resultaat = wc_get_orders( $query_args );

		ob_start();
		?>
		<form method="get" class="hdp-dl-werkbalk">
			<div class="hdp-zoekveld">
				<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
				<input type="text" name="hdp_bz" value="<?php echo esc_attr( $zoekterm ); ?>" placeholder="<?php echo esc_attr( HDP_I18N::t( 'bestel_zoek_placeholder' ) ); ?>" autocomplete="off">
			</div>
			<div class="hdp-filterrij">
				<div class="hdp-filtergroep">
					<span class="hdp-filtergroep-label"><?php echo esc_html( HDP_I18N::t( 'bestel_filter_status_label' ) ); ?></span>
					<select name="hdp_bs" class="hdp-sorteer-select">
						<option value=""><?php echo esc_html( HDP_I18N::t( 'bestel_alle_statussen' ) ); ?></option>
						<?php foreach ( $statussen as $sleutel => $label ) : ?>
							<?php $kort = str_replace( 'wc-', '', $sleutel ); ?>
							<option value="<?php echo esc_attr( $kort ); ?>" <?php selected( $status_filter, $kort ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>
			<div class="hdp-filterrij-onder">
				<button type="submit" class="hdp-btn hdp-btn-secundair hdp-btn-klein"><?php echo esc_html( HDP_I18N::t( 'bestel_filteren' ) ); ?></button>
				<?php if ( $filter_actief ) : ?>
					<a class="hdp-wis-filters" href="<?php echo esc_url( remove_query_arg( array( 'hdp_bz', 'hdp_bs', 'hdp_bp' ) ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'wis_filters' ) ); ?></a>
				<?php endif; ?>
			</div>
		</form>

		<?php if ( ! $resultaat->orders ) : ?>
			<?php if ( $filter_actief ) : ?>
				<p class="hdp-nog-niet"><?php echo esc_html( HDP_I18N::t( 'bestel_leeg_resultaat' ) ); ?></p>
			<?php else : ?>
				<?php $webshop_url = HDP_Settings::get( 'webshop_url' ); ?>
				<p class="hdp-nog-niet"><?php echo esc_html( HDP_I18N::t( 'bestel_geen_bestellingen' ) ); ?></p>
				<?php if ( $webshop_url ) : ?>
					<p><a class="hdp-btn" href="<?php echo esc_url( $webshop_url ); ?>"><?php echo esc_html( HDP_I18N::t( 'bestel_naar_webshop' ) ); ?></a></p>
				<?php endif; ?>
			<?php endif; ?>
		<?php else : ?>
			<div class="hdp-bestel-lijst">
				<?php foreach ( $resultaat->orders as $bestelling ) : ?>
					<?php $status = $bestelling->get_status(); ?>
					<div class="hdp-bestel-item">
						<div class="hdp-bestel-info">
							<strong>#<?php echo esc_html( $bestelling->get_order_number() ); ?></strong>
							<span><?php echo esc_html( wc_format_datetime( $bestelling->get_date_created() ) ); ?></span>
						</div>
						<span class="hdp-bestel-status hdp-bestel-status-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( wc_get_order_status_name( $status ) ); ?></span>
						<span class="hdp-bestel-totaal"><?php echo wp_kses_post( $bestelling->get_formatted_order_total() ); // phpcs:ignore WordPress.Security.EscapeOutput -- get_formatted_order_total() wordt door WooCommerce zelf al veilig samengesteld. ?></span>
						<a class="hdp-btn hdp-btn-klein" href="<?php echo esc_url( $bestelling->get_view_order_url() ); ?>"><?php echo esc_html( HDP_I18N::t( 'bestel_bekijken' ) ); ?></a>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( $resultaat->max_num_pages > 1 ) : ?>
				<div class="hdp-bestel-paginering">
					<?php if ( $pagina > 1 ) : ?>
						<a class="hdp-btn hdp-btn-secundair hdp-btn-klein" href="<?php echo esc_url( add_query_arg( 'hdp_bp', $pagina - 1 ) ); ?>">&larr; <?php echo esc_html( HDP_I18N::t( 'bestel_vorige' ) ); ?></a>
					<?php endif; ?>
					<span class="hdp-bestel-paginacijfer"><?php echo esc_html( sprintf( HDP_I18N::t( 'bestel_pagina_van' ), $pagina, $resultaat->max_num_pages ) ); ?></span>
					<?php if ( $pagina < $resultaat->max_num_pages ) : ?>
						<a class="hdp-btn hdp-btn-secundair hdp-btn-klein" href="<?php echo esc_url( add_query_arg( 'hdp_bp', $pagina + 1 ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'bestel_volgende' ) ); ?> &rarr;</a>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		<?php endif; ?>
		<?php
		return ob_get_clean();
	}
}
