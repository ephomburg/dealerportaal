<?php
/**
 * Voorbeeld van trainingsreviews voor een presentatie: cijfers bij de eerdere
 * demotraining en een vervolgtraining die ze laat zien. Draai eerst demo-vullen.php.
 *
 * Alles hangt aan demotrainingen, dus demo-weg.php ruimt het mee op. Er gaat
 * geen mail de deur uit.
 */

add_filter( 'pre_wp_mail', '__return_true' );
global $wpdb;

$eerdere = get_posts(
	array(
		'post_type'      => HDP_Trainingen::POST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'title'          => 'Basiscursus HARDI-veldspuiten',
		'meta_key'       => '_hdp_demo',
		'meta_value'     => 1,
	)
);
if ( ! $eerdere ) {
	exit( "GESTOPT: demotraining niet gevonden\n" );
}
$eerdere = $eerdere[0];
if ( get_posts( array( 'post_type' => HDP_Trainingen::POST_TYPE, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids', 'meta_key' => HDP_Trainingen::META_VERVOLG_OP, 'meta_value' => $eerdere->ID ) ) ) {
	exit( "GESTOPT: het voorbeeld staat er al\n" );
}

$vervolg = wp_insert_post(
	array(
		'post_type'    => HDP_Trainingen::POST_TYPE,
		'post_status'  => 'publish',
		'post_title'   => 'Vervolgcursus HARDI-veldspuiten',
		'post_content' => "In deze vervolgcursus gaan we verder waar de basiscursus ophield: storingen zoeken, de pomp reviseren en de spuitboom afstellen.\n\nVoor iedereen die de basiscursus heeft gevolgd. Neem je werkkleding en veiligheidsschoenen mee.",
	)
);
update_post_meta( $vervolg, '_hdp_demo', 1 );
update_post_meta( $vervolg, HDP_Trainingen::META_VERVOLG_OP, $eerdere->ID );
update_post_meta( $vervolg, HDP_Trainingen::META_TITEL_FR, 'Cours avancé pulvérisateurs HARDI' );
update_post_meta( $vervolg, HDP_Trainingen::META_TEKST_FR, 'Cette formation fait suite au cours de base : recherche de pannes, révision de la pompe et réglage de la rampe.' );
update_post_meta( $vervolg, HDP_Trainingen::META_DATUM, gmdate( 'Y-m-d', time() + 100 * DAY_IN_SECONDS ) );
update_post_meta( $vervolg, HDP_Trainingen::META_TIJD, '09:00 - 16:30' );
update_post_meta( $vervolg, HDP_Trainingen::META_LOCATIE, 'Homburg, Stiens' );
update_post_meta( $vervolg, HDP_Trainingen::META_KOSTEN, '€ 95 excl. btw' );
update_post_meta( $vervolg, HDP_Trainingen::META_PLAATSEN, 12 );
update_post_meta( $vervolg, HDP_Trainingen::META_VELDEN, wp_slash( (string) get_post_meta( $eerdere->ID, HDP_Trainingen::META_VELDEN, true ) ) );

// Aanmeldingen hangen aan dezelfde dealer als de bestaande demo-aanmelding.
$bestaand = HDP_Trainingen::aanmeldingen( $eerdere->ID );
if ( ! $bestaand ) {
	exit( "GESTOPT: geen demo-aanmelding gevonden\n" );
}
$dealer = $bestaand[0];

/** Vult een cijfer in: zichtbaar = goedgekeurd door Homburg. */
$beoordeel = static function ( $aanmelding_id, $cijfer, $reactie, $mag_tonen, $zichtbaar, $dagen ) use ( $wpdb ) {
	$rij = HDP_Training_Reviews::aanmelding_bij_link( $aanmelding_id, HDP_Training_Reviews::sleutel( $aanmelding_id ) );
	if ( ! $rij || true !== HDP_Training_Reviews::bewaar( $rij, array( 'cijfer' => $cijfer, 'reactie' => $reactie, 'mag_tonen' => $mag_tonen ? '1' : '' ) ) ) {
		exit( "GESTOPT: cijfer niet opgeslagen\n" );
	}
	$wpdb->update(
		HDP_Training_Reviews::tabel(),
		array(
			'zichtbaar'  => $zichtbaar ? 1 : 0,
			'aangemaakt' => gmdate( 'Y-m-d H:i:s', time() - $dagen * DAY_IN_SECONDS ),
		),
		array( 'aanmelding_id' => $aanmelding_id )
	);
};

$deelnemers = array(
	array( 'Sanne de Boer', 'Chef werkplaats', 10, 'Eindelijk een training met de machine erbij. De volgende dag kon ik bij een klant meteen de spuitboom beter afstellen.', true, true ),
	array( 'Tom de Jong', 'Monteur', 9, 'Heel praktisch en goed te volgen, ook voor iemand die nog niet lang met HARDI werkt.', true, true ),
	array( 'Henk Visser', 'Monteur', 10, 'Duidelijke uitleg en veel ruimte voor vragen uit de praktijk.', true, true ),
	array( 'Jan Hoekstra', 'Servicemonteur', 8, 'Veel geleerd over het onderhoud van de pomp. Ik had graag meer tijd gehad om het zelf te oefenen.', true, true ),
	array( 'Kees Bakker', 'Verkoop', 7, 'Goede inhoud, maar de groep was aan de grote kant.', true, false ),
	array( 'Mark Smit', 'Monteur', 9, 'Er was weinig tijd voor de lunch, verder prima.', false, false ),
	array( 'Bas Dijkstra', 'Monteur', 9, '', false, false ),
	array( 'Rik Mulder', 'Werkplaats', 8, '', false, false ),
	array( 'Wouter Bos', 'Monteur', 9, '', false, false ),
);
$links = array();
foreach ( $deelnemers as $i => $d ) {
	$mail = sanitize_title( $d[0] ) . '@voorbeeld.test';
	$wpdb->insert(
		HDP_Trainingen::tabel(),
		array(
			'training_id'     => $eerdere->ID,
			'training_titel'  => $eerdere->post_title,
			'user_id'         => $dealer['user_id'],
			'dealer_naam'     => $dealer['dealer_naam'],
			'dealer_bedrijf'  => $dealer['dealer_bedrijf'],
			'dealer_email'    => $dealer['dealer_email'],
			'deelnemer_naam'  => $d[0],
			'deelnemer_email' => $mail,
			'antwoorden'      => wp_json_encode( array( array( 'id' => 'functie', 'label' => 'Functie', 'soort' => 'tekst', 'waarde' => $d[1] ) ), JSON_UNESCAPED_UNICODE ),
			'taal'            => 'nl',
			'status'          => HDP_Trainingen::STATUS_AANGEMELD,
			'aangemaakt'      => gmdate( 'Y-m-d H:i:s', time() - 60 * DAY_IN_SECONDS ),
		)
	);
	$beoordeel( (int) $wpdb->insert_id, $d[2], $d[3], $d[4], $d[5], 25 - $i );
}

$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_hdp\\_%' OR option_name LIKE '\\_transient\\_timeout\\_hdp\\_%'" );

$stand = HDP_Training_Reviews::stand( $eerdere->ID );
echo 'vervolgtraining: ' . $vervolg . ' | ' . HDP_Trainingen::url( $vervolg ) . "\n";
echo 'cijfers: ' . $stand['aantal'] . ', gemiddeld ' . HDP_Training_Reviews::cijfer_tekst( $stand['gemiddelde'] ) . ', zichtbare reacties: ' . count( HDP_Training_Reviews::zichtbare_reacties( $eerdere->ID ) ) . "\n";
echo 'link om zelf een cijfer te geven (' . $dealer['deelnemer_naam'] . '): ' . HDP_Training_Reviews::link( $dealer['id'] ) . "\n";
