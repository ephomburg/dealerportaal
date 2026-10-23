<?php
/**
 * Eén paginakop voor het hele portaal.
 *
 * Elke pagina had zijn eigen kop. Downloads, Content en Garantie deelden
 * er één — acht keer dezelfde regels gekopieerd — maar de Winkel had een
 * klein kruimelpad met een kale titel en Mijn account alleen een titel met
 * een streep eronder. De terugweg was al even wisselvallig: Garantie had
 * er twee (boven én onder), Bestellingen en Winkel geen enkele.
 *
 * Voor een dealer betekent dat: elke pagina opnieuw zoeken waar je bent en
 * hoe je terugkomt. Dus staat het nu overal op dezelfde plek, uit dezelfde
 * functie.
 *
 * De startpagina houdt bewust zijn eigen fotohero: dat is de voordeur en
 * geen onderliggende pagina.
 *
 * @package Homburg_Dealerportaal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rendert de kop van een portaalpagina.
 */
class HDP_Paginakop {

	/**
	 * @param array $args {
	 *     @type string $titel        De paginatitel (verplicht).
	 *     @type string $bovenschrift Klein label erboven, bijv. "Mijn account".
	 *     @type string $intro        Eén regel uitleg onder de titel.
	 *     @type string $terug_url    Waar de terugweg heen gaat.
	 *     @type string $terug_tekst  Wat er op die link staat.
	 *     @type bool   $compact      Zonder de brede band eromheen. Voor
	 *                                plekken waar de kop binnen de inhoud
	 *                                staat in plaats van erboven — Mijn
	 *                                account bijvoorbeeld, waar WooCommerce
	 *                                de pagina zelf opbouwt.
	 * }
	 */
	public static function render( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'titel'        => '',
				'bovenschrift' => '',
				'intro'        => '',
				'terug_url'    => '',
				'terug_tekst'  => '',
				'compact'      => false,
			)
		);

		if ( '' === $args['titel'] ) {
			return;
		}

		// Staat het zijmenu er, dan is Home daar de weg terug en hoeft die
		// link hier niet nog een keer. Alleen die ene link vervalt: een
		// "terug naar garantie" op een ticket wijst naar een pagina die niet
		// in het menu staat en blijft dus gewoon staan.
		if ( home_url( '/dealerportaal/' ) === $args['terug_url'] && HDP_Zijbalk::gebruikt_zijmenu() ) {
			$args['terug_url'] = '';
		}

		$buiten = $args['compact'] ? 'div' : 'section';
		$klasse = $args['compact'] ? 'hdp-paginakop-compact' : 'hdp-welkom alignfull hdp-welkom-zonder-hero';
		?>
		<<?php echo esc_attr( $buiten ); ?> class="<?php echo esc_attr( $klasse ); ?>">
			<div class="hdp-welkom-inner">
				<?php if ( $args['terug_url'] && $args['terug_tekst'] ) : ?>
					<a class="hdp-terug-boven" href="<?php echo esc_url( $args['terug_url'] ); ?>"><?php echo esc_html( $args['terug_tekst'] ); ?></a>
				<?php endif; ?>
				<?php if ( $args['bovenschrift'] ) : ?>
					<p class="hdp-kop-bovenschrift"><?php echo esc_html( $args['bovenschrift'] ); ?></p>
				<?php endif; ?>
				<h1><?php echo esc_html( $args['titel'] ); ?></h1>
				<?php if ( $args['intro'] ) : ?>
					<p><?php echo esc_html( $args['intro'] ); ?></p>
				<?php endif; ?>
			</div>
		</<?php echo esc_attr( $buiten ); ?>>
		<?php
	}

	/**
	 * De gebruikelijke terugweg: naar de startpagina van het portaal.
	 *
	 * @return array Losse argumenten om in render() te mengen.
	 */
	public static function terug_naar_portaal() {
		return array(
			'terug_url'   => home_url( '/dealerportaal/' ),
			'terug_tekst' => HDP_I18N::t( 'terug_naar_portaal' ),
		);
	}
}
