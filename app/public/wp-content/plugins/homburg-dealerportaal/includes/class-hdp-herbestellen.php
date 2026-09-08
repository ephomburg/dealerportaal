<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Toont eerder bestelde producten van de ingelogde dealer met een
 * één-klik "opnieuw bestellen"-knop, zodat veelbestelde onderdelen niet
 * elke keer opnieuw in de webshop opgezocht hoeven te worden.
 *
 * Doet niets zolang WooCommerce niet actief is (net als de
 * WooCommerce-integratie in het thema, die dezelfde class_exists-wacht
 * hanteert).
 */
class HDP_Herbestellen {

	const MAX_PRODUCTEN    = 6;
	const MAX_BESTELLINGEN = 10;

	public static function render() {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return;
		}

		$producten = self::recente_producten( get_current_user_id() );
		if ( ! $producten ) {
			return;
		}
		?>
		<section class="hdp-herbestellen-sectie" aria-label="<?php echo esc_attr( HDP_I18N::t( 'herbestellen_label' ) ); ?>">
			<div class="hdp-herbestellen-kop">
				<h2 class="hdp-herbestellen-titel"><?php echo esc_html( HDP_I18N::t( 'herbestellen_label' ) ); ?></h2>
				<a class="hdp-herbestellen-alle" href="<?php echo esc_url( home_url( '/bestelgeschiedenis/' ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'bekijk_alle_bestellingen' ) ); ?> &rarr;</a>
			</div>
			<div class="hdp-herbestellen-grid">
				<?php foreach ( $producten as $product ) : ?>
					<article class="hdp-herbestel-kaart">
						<div class="hdp-herbestel-afbeelding"><?php echo $product->get_image( 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput -- get_image() wordt door WooCommerce zelf al veilig samengesteld. ?></div>
						<div class="hdp-herbestel-info">
							<strong><?php echo esc_html( $product->get_name() ); ?></strong>
							<span><?php echo wp_kses_post( $product->get_price_html() ); ?></span>
						</div>
						<a class="hdp-btn hdp-btn-klein" href="<?php echo esc_url( add_query_arg( 'add-to-cart', $product->get_id(), wc_get_cart_url() ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'herbestellen_knop' ) ); ?></a>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * Unieke producten uit de meest recente bestellingen van deze dealer,
	 * meest recent besteld eerst, max. self::MAX_PRODUCTEN. Alleen
	 * afgeronde/in-behandeling-bestellingen tellen mee (geen mislukte of
	 * geannuleerde bestellingen).
	 */
	private static function recente_producten( $user_id ) {
		$bestellingen = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => self::MAX_BESTELLINGEN,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'status'      => array( 'wc-completed', 'wc-processing' ),
			)
		);

		$producten = array();
		foreach ( $bestellingen as $bestelling ) {
			foreach ( $bestelling->get_items() as $item ) {
				$product = $item->get_product();
				if ( ! $product || ! $product->is_purchasable() || isset( $producten[ $product->get_id() ] ) ) {
					continue;
				}
				$producten[ $product->get_id() ] = $product;
				if ( count( $producten ) >= self::MAX_PRODUCTEN ) {
					break 2;
				}
			}
		}

		return array_values( $producten );
	}
}
