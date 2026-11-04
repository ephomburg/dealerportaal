<?php
/**
 * Haalt alles weg wat demo-vullen.php heeft aangemaakt, en niets anders.
 *
 * Draaien: .\wp.ps1 eval-file tests\demo\demo-weg.php
 */

const HDP_DEMO_META  = '_hdp_demo';
const HDP_DEMO_LOGIN = 'hdp-test-demo';
const HDP_DEMO_MAIL  = 'marketing+hdp-demo@homburg-holland.com';

global $wpdb;
require_once ABSPATH . 'wp-admin/includes/user.php';

/* --- Garantie: alleen de voorbeeldmachines en hun claims -------------------- */

if ( HDP_Supabase::beschikbaar( HDP_Supabase::APP ) ) {
	$a     = HDP_Supabase::APP;
	$basis = HDP_Supabase::url( $a );
	$key   = defined( 'HDP_SUPABASE_APP_KEY' ) && HDP_SUPABASE_APP_KEY ? HDP_SUPABASE_APP_KEY : HDP_Settings::get( 'supabase_app_key' );
	$weg   = static function ( $tabel, $filter ) use ( $basis, $key ) {
		$res  = wp_remote_request(
			$basis . '/rest/v1/' . $tabel . '?' . $filter,
			array(
				'method'  => 'DELETE',
				'headers' => array( 'apikey' => $key, 'Authorization' => 'Bearer ' . $key, 'Prefer' => 'return=representation' ),
				'timeout' => 30,
			)
		);
		$code = is_wp_error( $res ) ? 0 : (int) wp_remote_retrieve_response_code( $res );
		$rij  = is_wp_error( $res ) ? null : json_decode( wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 ) {
			exit( "GESTOPT bij $tabel: HTTP $code\n" );
		}
		echo "$tabel: " . count( (array) $rij ) . " weg\n";
	};
	// Alleen de voorbeeldmachines (serienummer DEMO-...) en wat daaraan hangt.
	$machines = HDP_Supabase::selecteer( $a, 'machines', array( 'select' => 'id', 'serienummer' => 'like.DEMO-*' ) );
	if ( is_wp_error( $machines ) ) {
		exit( 'GESTOPT: ' . $machines->get_error_message() . "\n" );
	}
	if ( $machines ) {
		$machine_ids = implode( ',', wp_list_pluck( $machines, 'id' ) );
		$claims      = HDP_Supabase::selecteer( $a, 'claims', array( 'select' => 'id', 'machine_id' => 'in.(' . $machine_ids . ')' ) );
		if ( is_wp_error( $claims ) ) {
			exit( 'GESTOPT: ' . $claims->get_error_message() . "\n" );
		}
		if ( $claims ) {
			$claim_ids = implode( ',', wp_list_pluck( $claims, 'id' ) );
			$weg( 'verloop', 'claim_id=in.(' . $claim_ids . ')' );
			$weg( 'claims', 'id=in.(' . $claim_ids . ')' );
		}
		$weg( 'machines', 'id=in.(' . $machine_ids . ')' );
	}
}

/* --- Webshop --------------------------------------------------------------- */

$bestellingen = wc_get_orders(
	array(
		'limit'      => -1,
		'status'     => array_keys( wc_get_order_statuses() ),
		'meta_key'   => HDP_DEMO_META,
		'meta_value' => 1,
	)
);
foreach ( $bestellingen as $o ) {
	$o->delete( true );
}
echo 'bestellingen: ' . count( $bestellingen ) . " weg\n";

$posts = get_posts(
	array(
		'post_type'      => array( 'product', 'hdp_download', HDP_Trainingen::POST_TYPE, HDP_Berichten::POST_TYPE, 'attachment' ),
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'meta_key'       => HDP_DEMO_META,
		'meta_value'     => 1,
	)
);
$tellers = array();
foreach ( $posts as $post ) {
	$tellers[ $post->post_type ] = ( isset( $tellers[ $post->post_type ] ) ? $tellers[ $post->post_type ] : 0 ) + 1;
	if ( 'attachment' === $post->post_type ) {
		wp_delete_attachment( $post->ID, true );
	} elseif ( 'product' === $post->post_type ) {
		$product = wc_get_product( $post->ID );
		if ( $product ) {
			$product->delete( true );
		}
	} else {
		if ( HDP_Trainingen::POST_TYPE === $post->post_type ) {
			$wpdb->delete( HDP_Trainingen::tabel(), array( 'training_id' => $post->ID ) );
		}
		wp_delete_post( $post->ID, true );
	}
}
echo 'weg: ' . wp_json_encode( $tellers ) . "\n";

foreach ( array( 'product_cat', 'product_brand' ) as $taxonomie ) {
	$termen = get_terms(
		array(
			'taxonomy'   => $taxonomie,
			'hide_empty' => false,
			'meta_key'   => HDP_DEMO_META,
			'meta_value' => 1,
		)
	);
	foreach ( is_wp_error( $termen ) ? array() : $termen as $term ) {
		wp_delete_term( $term->term_id, $taxonomie );
	}
}

$dealer = get_user_by( 'login', HDP_DEMO_LOGIN );
if ( $dealer ) {
	$wpdb->delete( HDP_Trainingen::tabel(), array( 'user_id' => $dealer->ID ) );
	wp_delete_user( $dealer->ID );
	echo "demo-account weg\n";
}

$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_hdp\\_%' OR option_name LIKE '\\_transient\\_timeout\\_hdp\\_%'" );
wc_delete_product_transients();
echo "KLAAR\n";
