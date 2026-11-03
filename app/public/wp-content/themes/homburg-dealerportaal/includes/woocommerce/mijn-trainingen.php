<?php
/**
 * "Mijn trainingen" in het accountmenu: wie van deze dealer is aangemeld voor
 * welke training, ook nadat die is geweest. De aanmeldingen zelf staan in
 * HDP_Trainingen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

add_action( 'init', 'homburg_wc_mijn_trainingen_endpoint' );
function homburg_wc_mijn_trainingen_endpoint() {
	add_rewrite_endpoint( 'mijn-trainingen', EP_ROOT | EP_PAGES );
}

add_filter( 'woocommerce_get_query_vars', 'homburg_wc_mijn_trainingen_query_var' );
function homburg_wc_mijn_trainingen_query_var( $vars ) {
	$vars['mijn-trainingen'] = 'mijn-trainingen';
	return $vars;
}

add_action( 'init', 'homburg_wc_mijn_trainingen_flush', 20 );
/** Eenmalig, zodat het adres meteen werkt; zie ook bulk-order.php. */
function homburg_wc_mijn_trainingen_flush() {
	if ( ! get_option( 'hdp_mijn_trainingen_flushed' ) ) {
		flush_rewrite_rules();
		update_option( 'hdp_mijn_trainingen_flushed', 1 );
	}
}

add_filter( 'woocommerce_endpoint_mijn-trainingen_title', 'homburg_wc_mijn_trainingen_titel' );
function homburg_wc_mijn_trainingen_titel() {
	return homburg_wc_tekst( 'training_mijn', 'Mijn trainingen' );
}

add_filter( 'woocommerce_account_menu_items', 'homburg_wc_mijn_trainingen_menu', 21 );
/** Vlak na Mijn merken. */
function homburg_wc_mijn_trainingen_menu( $items ) {
	$nieuw = array();
	foreach ( $items as $key => $label ) {
		$nieuw[ $key ] = $label;
		if ( 'mijn-merken' === $key ) {
			$nieuw['mijn-trainingen'] = homburg_wc_tekst( 'training_mijn', 'Mijn trainingen' );
		}
	}
	return $nieuw;
}

add_action( 'woocommerce_account_mijn-trainingen_endpoint', 'homburg_wc_mijn_trainingen_inhoud' );
function homburg_wc_mijn_trainingen_inhoud() {
	$overzicht = HDP_Trainingen::overzicht_dealer();

	if ( ! $overzicht['komend'] && ! $overzicht['eerder'] ) {
		?>
		<p class="hdp-mijn-trainingen-leeg"><?php echo esc_html( homburg_wc_tekst( 'training_mijn_leeg', 'Er is vanuit jouw bedrijf nog niemand aangemeld voor een training.' ) ); ?></p>
		<a class="button" href="<?php echo esc_url( HDP_Trainingen::url() ); ?>"><?php echo esc_html( homburg_wc_tekst( 'training_mijn_bekijk', 'Bekijk de trainingen' ) ); ?></a>
		<?php
		return;
	}

	foreach ( array(
		'komend' => homburg_wc_tekst( 'training_mijn_komend', 'Komend' ),
		'eerder' => homburg_wc_tekst( 'training_mijn_eerder', 'Eerder' ),
	) as $groep => $kop ) {
		if ( ! $overzicht[ $groep ] ) {
			continue;
		}
		?>
		<h2 class="hdp-mijn-trainingen-kop"><?php echo esc_html( $kop ); ?></h2>
		<ul class="hdp-mijn-trainingen">
			<?php foreach ( $overzicht[ $groep ] as $training ) : ?>
				<?php homburg_wc_mijn_trainingen_regel( $training, 'komend' === $groep ); ?>
			<?php endforeach; ?>
		</ul>
		<?php
	}
}

/**
 * @param array $training Uit HDP_Trainingen::overzicht_dealer().
 * @param bool  $komend   Alleen dan kan de training nog in de agenda.
 */
function homburg_wc_mijn_trainingen_regel( $training, $komend ) {
	$id     = $training['id'];
	$feiten = array_filter(
		array(
			$training['datum'] ? HDP_Trainingen::datumtekst( $id ) : ( $komend ? homburg_wc_tekst( 'training_datum_volgt', 'Datum volgt' ) : '' ),
			HDP_Trainingen::POST_TYPE === get_post_type( $id ) ? HDP_Trainingen::tijd( $id ) : '',
			(string) get_post_meta( $id, HDP_Trainingen::META_LOCATIE, true ),
		)
	);
	$actief = array_filter(
		$training['rijen'],
		static function ( $rij ) {
			return HDP_Trainingen::STATUS_AANGEMELD === $rij['status'];
		}
	);
	?>
	<li>
		<h3>
			<?php if ( $training['zichtbaar'] ) : ?>
				<a href="<?php echo esc_url( HDP_Trainingen::url( $id ) ); ?>"><?php echo esc_html( $training['titel'] ); ?></a>
			<?php else : ?>
				<?php echo esc_html( $training['titel'] ); ?>
			<?php endif; ?>
		</h3>
		<?php if ( $feiten ) : ?>
			<p class="hdp-mijn-trainingen-feiten"><?php echo esc_html( implode( ' · ', $feiten ) ); ?></p>
		<?php endif; ?>
		<ul class="hdp-mijn-trainingen-namen">
			<?php foreach ( $training['rijen'] as $rij ) : ?>
				<?php $geannuleerd = HDP_Trainingen::STATUS_GEANNULEERD === $rij['status']; ?>
				<li<?php echo $geannuleerd ? ' class="is-geannuleerd"' : ''; ?>>
					<?php echo esc_html( $rij['deelnemer_naam'] ); ?>
					<?php if ( $geannuleerd ) : ?>
						<span><?php echo esc_html( homburg_wc_tekst( 'training_mijn_geannuleerd', 'Geannuleerd' ) ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( $komend && $actief && $training['datum'] ) : ?>
			<a class="hdp-mijn-trainingen-agenda" href="<?php echo esc_url( HDP_Trainingen::agenda_url( $id ) ); ?>"><?php echo esc_html( homburg_wc_tekst( 'training_in_outlook', 'In Outlook zetten' ) ); ?></a>
		<?php endif; ?>
	</li>
	<?php
}
