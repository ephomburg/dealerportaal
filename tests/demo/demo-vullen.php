<?php
/**
 * Vult het portaal met voorbeeldgegevens voor een presentatie: een eigen
 * demo-account en bij elk onderdeel iets om te laten zien.
 *
 * Draaien: .\wp.ps1 eval-file tests\demo\demo-vullen.php
 * Opruimen: tests\demo\demo-weg.php
 *
 * Alles krijgt het kenmerk _hdp_demo, en de garantierijen hangen aan het
 * e-mailadres van het demo-account: zo raakt het opruimen niets anders. Tijdens
 * het vullen gaat er geen mail de deur uit.
 */

const HDP_DEMO_META  = '_hdp_demo';
const HDP_DEMO_LOGIN = 'hdp-test-demo';
const HDP_DEMO_MAIL  = 'marketing+hdp-demo@homburg-holland.com';

if ( get_user_by( 'login', HDP_DEMO_LOGIN ) ) {
	exit( "GESTOPT: het demo-account bestaat al. Draai eerst demo-weg.php.\n" );
}

add_filter( 'pre_wp_mail', '__return_true' );

/* --- Account --------------------------------------------------------------- */

$wachtwoord = 'Demo-' . wp_generate_password( 14, false );
$dealer     = wp_insert_user(
	array(
		'user_login'   => HDP_DEMO_LOGIN,
		'user_pass'    => $wachtwoord,
		'user_email'   => HDP_DEMO_MAIL,
		'first_name'   => 'Demo',
		'last_name'    => 'Dealer',
		'display_name' => 'Demo Dealer',
		'role'         => 'dealer',
	)
);
if ( is_wp_error( $dealer ) ) {
	exit( 'GESTOPT: ' . $dealer->get_error_message() . "\n" );
}
update_user_meta( $dealer, 'hdp_goedgekeurd', '1' );
update_user_meta( $dealer, 'hdp_testaccount', '1' );
update_user_meta( $dealer, 'wc_last_active', time() );
$adres = array(
	'first_name' => 'Demo',
	'last_name'  => 'Dealer',
	'company'    => 'Demo Dealer BV',
	'address_1'  => 'Voorbeeldweg 1',
	'postcode'   => '9000 AA',
	'city'       => 'Voorbeeldstad',
	'country'    => 'NL',
);
foreach ( $adres as $veld => $waarde ) {
	update_user_meta( $dealer, 'billing_' . $veld, $waarde );
	update_user_meta( $dealer, 'shipping_' . $veld, $waarde );
}
update_user_meta( $dealer, 'billing_email', HDP_DEMO_MAIL );
update_user_meta( $dealer, 'billing_phone', '0500 000000' );
wp_set_current_user( $dealer );

/** Een term aanmaken, of de bestaande gebruiken. Alleen wat hier nieuw is, wordt straks opgeruimd. */
function hdp_demo_term( $naam, $taxonomie ) {
	$bestaand = term_exists( $naam, $taxonomie );
	if ( $bestaand ) {
		return (int) $bestaand['term_id'];
	}
	$nieuw = wp_insert_term( $naam, $taxonomie );
	if ( is_wp_error( $nieuw ) ) {
		exit( 'GESTOPT: ' . $nieuw->get_error_message() . "\n" );
	}
	update_term_meta( $nieuw['term_id'], HDP_DEMO_META, 1 );
	return (int) $nieuw['term_id'];
}

/* --- Webshop --------------------------------------------------------------- */

$artikelen = array(
	array( 'Väderstad', 'Zaaischijf 390 mm', 'DEMO-2001', 38.90, null ),
);
$producten = array();
foreach ( $artikelen as $a ) {
	$p = new WC_Product_Simple();
	$p->set_name( $a[1] );
	$p->set_sku( $a[2] );
	$p->set_regular_price( (string) $a[3] );
	if ( $a[4] ) {
		$p->set_sale_price( (string) $a[4] );
	}
	$p->set_short_description( 'Voorbeeldartikel.' );
	$p->set_status( 'publish' );
	$p->set_category_ids( array( hdp_demo_term( $a[0], 'product_cat' ) ) );
	$p->update_meta_data( HDP_DEMO_META, 1 );
	$p->update_meta_data( '_hdp_handmatig', '1' );
	$id = $p->save();
	wp_set_object_terms( $id, array( hdp_demo_term( $a[0], 'product_brand' ) ), 'product_brand' );
	$producten[ $a[2] ] = $id;
}
// De veldfoto van de inlogpagina als productfoto. Lukt ophalen niet, dan valt de kaart terug op de eigen plaat.
$res = wp_remote_get( 'https://www.homburg-belgium.com/wp-content/uploads/2023/09/Homburg-Holland-precisielandbouw-man-in-veld.jpg', array( 'timeout' => 30 ) );
if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$upload = wp_upload_bits( 'demo-zaaischijf.jpg', null, wp_remote_retrieve_body( $res ) );
	if ( empty( $upload['error'] ) ) {
		$foto = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => 'Zaaischijf 390 mm',
				'post_status'    => 'inherit',
			),
			$upload['file'],
			$producten['DEMO-2001']
		);
		update_post_meta( $foto, HDP_DEMO_META, 1 );
		wp_update_attachment_metadata( $foto, wp_generate_attachment_metadata( $foto, $upload['file'] ) );
		$met_foto = wc_get_product( $producten['DEMO-2001'] );
		$met_foto->set_image_id( $foto );
		$met_foto->save();
	}
}
update_user_meta( $dealer, '_hdp_favorieten', array( $producten['DEMO-2001'] ) );
echo 'webshop: ' . count( $producten ) . " artikelen\n";

/* --- Outlet ---------------------------------------------------------------- */

$outlet_term = get_term_by( 'slug', 'outlet', 'product_cat' );
$outlet      = array(
	array( 'Stalen plaat 2000×1000×5 mm', 'DEMO-9001', 45.00, 10, 'nieuw', 'Restpartij uit eigen magazijn' ),
	array( 'Set spuitboomdelen', 'DEMO-9002', 85.00, 3, 'als-nieuw', 'Ongebruikt, verpakking geopend' ),
	array( 'Hydrauliekpomp uit inruilmachine', 'DEMO-9003', 320.00, 1, 'gebruikt', 'Getest en werkend, lichte gebruikssporen' ),
);
foreach ( $outlet as $a ) {
	$p = new WC_Product_Simple();
	$p->set_name( $a[0] );
	$p->set_sku( $a[1] );
	$p->set_regular_price( (string) $a[2] );
	$p->set_manage_stock( true );
	$p->set_stock_quantity( $a[3] );
	$p->set_backorders( 'no' );
	$p->set_short_description( 'Voorbeeldartikel.' );
	$p->set_status( 'publish' );
	$p->set_category_ids( array( $outlet_term->term_id ) );
	$p->update_meta_data( HDP_DEMO_META, 1 );
	$p->update_meta_data( '_hdp_handmatig', '1' );
	$p->update_meta_data( '_hdp_staat', $a[4] );
	$p->update_meta_data( '_hdp_staat_toelichting', $a[5] );
	$p->save();
}
echo 'outlet: ' . count( $outlet ) . " artikelen\n";

/* --- Bestellingen ---------------------------------------------------------- */

$bestellingen = array(
	array( 'processing', 1, 'Onderhoud klant Jansen', array( 'DEMO-2001' => 8 ) ),
	array( 'processing', 3, 'Voorraad werkplaats', array( 'DEMO-2001' => 4 ) ),
	array( 'completed', 18, 'Seizoensbeurt zaaimachine', array( 'DEMO-2001' => 12 ) ),
	array( 'completed', 45, 'Spoedbestelling', array( 'DEMO-2001' => 2 ) ),
);
foreach ( $bestellingen as $b ) {
	$o = wc_create_order(
		array(
			'customer_id' => $dealer,
			'created_via' => 'checkout',
		)
	);
	foreach ( $b[3] as $sku => $aantal ) {
		$o->add_product( wc_get_product( $producten[ $sku ] ), $aantal );
	}
	$o->set_address( $adres + array( 'email' => HDP_DEMO_MAIL, 'phone' => '0500 000000' ), 'billing' );
	$o->set_address( $adres, 'shipping' );
	$o->set_payment_method( 'cheque' );
	$o->set_payment_method_title( 'Op rekening' );
	$o->update_meta_data( '_wc_other/homburg/referentie', $b[2] );
	$o->update_meta_data( HDP_DEMO_META, 1 );
	$o->set_date_created( time() - $b[1] * DAY_IN_SECONDS );
	$o->calculate_totals();
	$o->set_status( $b[0] );
	$o->save();
}
echo 'bestellingen: ' . count( $bestellingen ) . "\n";

/* --- Trainingen ------------------------------------------------------------ */

$velden = wp_slash(
	wp_json_encode(
		array(
			array( 'id' => 'telefoon', 'label' => 'Telefoonnummer', 'label_fr' => 'Numéro de téléphone', 'soort' => 'telefoon', 'verplicht' => true, 'opties' => array() ),
			array( 'id' => 'functie', 'label' => 'Functie', 'label_fr' => 'Fonction', 'soort' => 'tekst', 'verplicht' => true, 'opties' => array() ),
			array( 'id' => 'lunch', 'label' => 'Lunch', 'label_fr' => 'Déjeuner', 'soort' => 'keuze', 'verplicht' => true, 'opties' => array( 'Geen voorkeur | Pas de préférence', 'Vegetarisch | Végétarien' ) ),
			array( 'id' => 'opmerking', 'label' => 'Opmerkingen', 'label_fr' => 'Remarques', 'soort' => 'lang', 'verplicht' => false, 'opties' => array() ),
		),
		JSON_UNESCAPED_UNICODE
	)
);
$maak_training = static function ( $titel, $titel_fr, $tekst, $tekst_fr, $dagen, $tijd, $locatie, $kosten, $plaatsen ) use ( $velden ) {
	$id = wp_insert_post(
		array(
			'post_type'    => HDP_Trainingen::POST_TYPE,
			'post_status'  => 'publish',
			'post_title'   => $titel,
			'post_content' => $tekst,
		)
	);
	update_post_meta( $id, HDP_DEMO_META, 1 );
	update_post_meta( $id, HDP_Trainingen::META_TITEL_FR, $titel_fr );
	update_post_meta( $id, HDP_Trainingen::META_TEKST_FR, $tekst_fr );
	update_post_meta( $id, HDP_Trainingen::META_DATUM, gmdate( 'Y-m-d', time() + $dagen * DAY_IN_SECONDS ) );
	update_post_meta( $id, HDP_Trainingen::META_TIJD, $tijd );
	update_post_meta( $id, HDP_Trainingen::META_LOCATIE, $locatie );
	update_post_meta( $id, HDP_Trainingen::META_KOSTEN, $kosten );
	update_post_meta( $id, HDP_Trainingen::META_PLAATSEN, $plaatsen );
	update_post_meta( $id, HDP_Trainingen::META_VELDEN, $velden );
	return $id;
};
$antwoord = static function ( $naam, $mail, $functie ) {
	return array(
		'naam'           => $naam,
		'email'          => $mail,
		'veld_telefoon'  => '0500 000000',
		'veld_functie'   => $functie,
		'veld_lunch'     => 'Geen voorkeur',
		'veld_opmerking' => '',
	);
};
$t1 = $maak_training(
	'Afstellen en onderhoud Väderstad Tempo',
	'Réglage et entretien Väderstad Tempo',
	"In deze praktijktraining leer je de Tempo zelf afstellen en onderhouden, zodat je de klant direct kunt helpen.\n\nOp het programma staan het afstellen van de zaai-elementen, het uitlezen van foutmeldingen en het preventief onderhoud voor het seizoen. Neem je eigen werkkleding en veiligheidsschoenen mee.",
	"Lors de cette formation pratique, vous apprendrez à régler et entretenir vous-même le Tempo.\n\nAu programme : le réglage des éléments semeurs, la lecture des messages d’erreur et l’entretien préventif avant la saison.",
	40,
	'09:00 - 16:30',
	'Homburg, Stiens',
	'€ 95 excl. btw',
	12
);
HDP_Trainingen::meld_aan( $t1, $antwoord( 'Piet Monteur', 'piet@voorbeeld.test', 'Monteur' ), $dealer );
HDP_Trainingen::meld_aan( $t1, $antwoord( 'Sanne Werkplaats', 'sanne@voorbeeld.test', 'Chef werkplaats' ), $dealer );
$maak_training(
	'Kalibreren van Bogballe-strooiers',
	'Calibrage des épandeurs Bogballe',
	"Een middag over het juist kalibreren van Bogballe-strooiers, met de veelgemaakte fouten en hoe je die voorkomt.\n\nVoor nieuwe medewerkers en iedereen die zijn kennis wil opfrissen.",
	'Une demi-journée consacrée au calibrage correct des épandeurs Bogballe.',
	65,
	'13:00 - 17:00',
	'Homburg, Stiens',
	'Gratis',
	20
);
$voorbij = $maak_training(
	'Basiscursus HARDI-veldspuiten',
	'Cours de base pulvérisateurs HARDI',
	'De basis van het onderhoud aan HARDI-veldspuiten, van pomp tot spuitboom.',
	'Les bases de l’entretien des pulvérisateurs HARDI.',
	30,
	'09:00 - 16:00',
	'Homburg, Stiens',
	'€ 75 excl. btw',
	10
);
HDP_Trainingen::meld_aan( $voorbij, $antwoord( 'Piet Monteur', 'piet@voorbeeld.test', 'Monteur' ), $dealer );
update_post_meta( $voorbij, HDP_Trainingen::META_DATUM, gmdate( 'Y-m-d', time() - 30 * DAY_IN_SECONDS ) );
echo "trainingen: 3\n";

/* --- Bericht op de startpagina --------------------------------------------- */

// Bestaande berichten alleen voor dit account verbergen; ze blijven verder staan.
update_user_meta(
	$dealer,
	HDP_Berichten::META_WEG,
	array_map(
		'intval',
		get_posts(
			array(
				'post_type'      => HDP_Berichten::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		)
	)
);
$bericht = wp_insert_post(
	array(
		'post_type'    => HDP_Berichten::POST_TYPE,
		'post_status'  => 'publish',
		'post_title'   => 'Nieuwe trainingen gepland',
		'post_content' => 'De trainingen voor het komende seizoen staan klaar. Bekijk ze onder Trainingen en meld je collega’s direct aan.',
	)
);
update_post_meta( $bericht, HDP_DEMO_META, 1 );
update_post_meta( $bericht, HDP_Berichten::META_TITEL_FR, 'Nouvelles formations planifiées' );
update_post_meta( $bericht, HDP_Berichten::META_TEKST_FR, 'Les formations de la prochaine saison sont disponibles. Consultez-les sous Formations et inscrivez directement vos collègues.' );
update_post_meta( $bericht, HDP_Berichten::META_SOORT, 'melding' );
echo "bericht: 1\n";

/* --- Downloads, Huisstijl en Content --------------------------------------- */

/** Een eenvoudige PDF van één pagina met een titel. */
function hdp_demo_pdf( $titel ) {
	$veilig   = str_replace( array( '\\', '(', ')' ), array( '\\\\', '\\(', '\\)' ), iconv( 'UTF-8', 'Windows-1252//TRANSLIT', $titel ) );
	$inhoud   = "BT /F1 22 Tf 60 760 Td ({$veilig}) Tj ET\nBT /F1 12 Tf 60 730 Td (Voorbeeldbestand voor de presentatie van het dealerportaal.) Tj ET";
	$objecten = array(
		'<< /Type /Catalog /Pages 2 0 R >>',
		'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
		'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
		'<< /Length ' . strlen( $inhoud ) . " >>\nstream\n{$inhoud}\nendstream",
		'<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
	);
	$pdf      = "%PDF-1.4\n";
	$posities = array();
	foreach ( $objecten as $i => $object ) {
		$posities[] = strlen( $pdf );
		$pdf       .= ( $i + 1 ) . " 0 obj\n{$object}\nendobj\n";
	}
	$xref = strlen( $pdf );
	$pdf .= 'xref' . "\n0 " . ( count( $objecten ) + 1 ) . "\n0000000000 65535 f \n";
	foreach ( $posities as $positie ) {
		$pdf .= sprintf( "%010d 00000 n \n", $positie );
	}
	return $pdf . 'trailer << /Size ' . ( count( $objecten ) + 1 ) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
}

/** Een afbeelding in de Homburg-kleuren met het logo en een titel, midden in beeld: kaarten snijden de zijkanten af. */
function hdp_demo_afbeelding( $titel, $rood ) {
	$klein  = imagecreatetruecolor( 300, 200 );
	$donker = imagecolorallocate( $klein, 43, 45, 49 );
	$kleur  = imagecolorallocate( $klein, 192, 13, 13 );
	$wit    = imagecolorallocate( $klein, 255, 255, 255 );
	imagefilledrectangle( $klein, 0, 0, 300, 200, $rood ? $kleur : $donker );
	imagefilledrectangle( $klein, 0, 176, 300, 200, $rood ? $donker : $kleur );
	$regels = explode( "\n", wordwrap( $titel, 24, "\n", true ) );
	foreach ( $regels as $i => $regel ) {
		$x = (int) ( ( 300 - strlen( $regel ) * imagefontwidth( 5 ) ) / 2 );
		imagestring( $klein, 5, $x, 92 + $i * 20, $regel, $wit );
	}
	$groot = imagecreatetruecolor( 1200, 800 );
	imagecopyresampled( $groot, $klein, 0, 0, 0, 0, 1200, 800, 300, 200 );
	$logo = imagecreatefrompng( HDP_PLUGIN_DIR . 'assets/img/Logo-HOMBURG_WIT-768x102.png' );
	imagealphablending( $groot, true );
	imagecopyresampled( $groot, $logo, 330, 190, 0, 0, 540, (int) ( 540 * imagesy( $logo ) / imagesx( $logo ) ), imagesx( $logo ), imagesy( $logo ) );
	ob_start();
	imagepng( $groot );
	$png = ob_get_clean();
	imagedestroy( $klein );
	imagedestroy( $groot );
	imagedestroy( $logo );
	return $png;
}

/** Bestand plus downloadregel. Via dezelfde velden als een gewone upload, dus ook afgeschermd opgeslagen. */
function hdp_demo_download( $titel, $bestandsnaam, $inhoud, $mime, $categorie, $merk, $dagen_geleden ) {
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$upload = wp_upload_bits( $bestandsnaam, null, $inhoud );
	if ( ! empty( $upload['error'] ) ) {
		exit( 'GESTOPT: ' . $upload['error'] . "\n" );
	}
	$bijlage = wp_insert_attachment(
		array(
			'post_mime_type' => $mime,
			'post_title'     => $titel,
			'post_status'    => 'inherit',
		),
		$upload['file']
	);
	update_post_meta( $bijlage, HDP_DEMO_META, 1 );
	wp_update_attachment_metadata( $bijlage, wp_generate_attachment_metadata( $bijlage, $upload['file'] ) );

	$datum = gmdate( 'Y-m-d H:i:s', time() - $dagen_geleden * DAY_IN_SECONDS );
	$id    = wp_insert_post(
		array(
			'post_type'     => HDP_Downloads_CPT::POST_TYPE,
			'post_status'   => 'publish',
			'post_title'    => $titel,
			'post_date_gmt' => $datum,
			'post_date'     => get_date_from_gmt( $datum ),
		)
	);
	update_post_meta( $id, HDP_DEMO_META, 1 );
	update_post_meta( $id, '_hdp_categorie', $categorie );
	update_post_meta( $id, '_hdp_merk', $merk );
	update_post_meta( $id, '_hdp_regios', '' );
	update_post_meta( $id, '_hdp_attachment_id', $bijlage );
	return $id;
}

$aantal_in = static function ( $categorie ) {
	return count(
		get_posts(
			array(
				'post_type'      => HDP_Downloads_CPT::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_hdp_categorie',
				'meta_value'     => $categorie,
			)
		)
	);
};

// Bestaande bestanden blijven staan. Wat al gevuld is, krijgt alleen de twee nieuwe documenten.
$lijst = array(
	array( 'Prijslijst HARDI 2026 (voorbeeld)', 'HARDI', 1 ),
	array( 'Onderdelenlijst Väderstad Tempo (voorbeeld)', 'Väderstad', 2 ),
);
if ( ! $aantal_in( 'download' ) ) {
	$lijst = array_merge(
		$lijst,
		array(
			array( 'Prijslijst Väderstad 2026 (voorbeeld)', 'Väderstad', 20 ),
			array( 'Gebruikershandleiding Bogballe M-serie (voorbeeld)', 'Bogballe', 25 ),
			array( 'Onderdelenboek Rabe ploegen (voorbeeld)', 'Rabe', 30 ),
			array( 'Gebruikershandleiding Draincleaner (voorbeeld)', 'Draincleaners', 40 ),
		)
	);
}
foreach ( $lijst as $d ) {
	hdp_demo_download( $d[0], 'demo-' . sanitize_title( $d[0] ) . '.pdf', hdp_demo_pdf( $d[0] ), 'application/pdf', 'download', $d[1], $d[2] );
}
echo 'downloads: ' . count( $lijst ) . "\n";

$huisstijl = 0;
if ( ! $aantal_in( 'huisstijl' ) ) {
	foreach ( array( 'Logo Homburg rood (voorbeeld)', 'Logo Homburg wit (voorbeeld)', 'Kleurenkaart Homburg (voorbeeld)' ) as $i => $titel ) {
		hdp_demo_download( $titel, 'demo-' . sanitize_title( $titel ) . '.png', hdp_demo_afbeelding( $titel, 0 === $i ), 'image/png', 'huisstijl', HDP_Downloads_CPT::MERK_ALGEMEEN, 30 );
		++$huisstijl;
	}
}
echo "huisstijl: $huisstijl\n";

$content = array(
	array( 'Social post voorjaarsactie HARDI', 'HARDI', true ),
	array( 'Advertentie Tempo zaaimachine', 'Väderstad', false ),
	array( 'Banner Bogballe strooiers', 'Bogballe', true ),
	array( 'Social post onderhoudsseizoen', HDP_Downloads_CPT::MERK_ALGEMEEN, false ),
);
foreach ( $content as $c ) {
	hdp_demo_download( $c[0], 'demo-' . sanitize_title( $c[0] ) . '.png', hdp_demo_afbeelding( $c[0], $c[2] ), 'image/png', 'content', $c[1], 12 );
}
echo 'content: ' . count( $content ) . "\n";

/* --- Garantie -------------------------------------------------------------- */
// Staat in de administratie van de Homburg App, dus ook daar zichtbaar tot het opruimen.
// Op het account van de presentator (ep@): claims zijn alleen zichtbaar voor de eigen dealer.

$presentator = get_user_by( 'email', 'ep@homburg-holland.com' );
if ( $presentator && HDP_Supabase::beschikbaar( HDP_Supabase::APP ) ) {
	wp_set_current_user( $presentator->ID );
	$a      = HDP_Supabase::APP;
	$stop   = static function ( $uitkomst, $wat ) {
		if ( is_wp_error( $uitkomst ) ) {
			exit( "GESTOPT bij $wat: " . $uitkomst->get_error_message() . "\n" );
		}
		return $uitkomst;
	};
	$stempel = static function ( $dagen ) {
		return gmdate( 'c', time() - (int) ( $dagen * DAY_IN_SECONDS ) );
	};
	$machines = array(
		array( 'Väderstad Tempo V8', 'Väderstad', 'DEMO-TV8-0001', 'Akkerbouwbedrijf De Voorbeeld', 'goedgekeurd', 320 ),
		array( 'HARDI Navigator 4000', 'HARDI', 'DEMO-HN4-0002', 'Loonbedrijf Voorbeeld', 'goedgekeurd', 210 ),
		array( 'Bogballe M35W', 'Bogballe', 'DEMO-BM35-0003', 'Mts. Voorbeeld', 'goedgekeurd', 150 ),
		array( 'Rabe Albatros', 'Rabe', 'DEMO-RA-0004', 'Akkerbouwbedrijf De Voorbeeld', 'in_behandeling', 20 ),
	);
	foreach ( $machines as $m ) {
		$rij = $stop(
			HDP_Garantie::meld_machine_aan(
				array(
					'machine'      => $m[0],
					'merk'         => $m[1],
					'serienummer'  => $m[2],
					'aankoopdatum' => gmdate( 'Y-m-d', time() - $m[5] * DAY_IN_SECONDS ),
					'klant'        => $m[3],
				)
			),
			'machine'
		);
		$stop(
			HDP_Supabase::werk_bij(
				$a,
				'machines',
				array( 'id' => 'eq.' . $rij['id'] ),
				array(
					'status'       => $m[4],
					'garantie_tot' => gmdate( 'Y-m-d', time() + ( 730 - $m[5] ) * DAY_IN_SECONDS ),
					'hectares'     => (string) ( $m[5] * 3 ),
				)
			),
			'machine bijwerken'
		);
	}
	HDP_Garantie::machines();

	// De database zet de indiendatum altijd op nu. Het verloop speelt daarom kort
	// daarna, anders staan de stappen in de claim vóór de indiening.
	$zet_stap = static function ( $claim, $stap, $nummer ) use ( $a, $stop, $stempel ) {
		// De status van een claim volgt uit het verloop; de database staat niets anders toe.
		$stop(
			HDP_Supabase::voeg_toe(
				$a,
				'verloop',
				array(
					array(
						'claim_id'  => $claim['id'],
						'status'    => empty( $stap['status'] ) ? null : $stap['status'],
						'wie'       => $stap['wie'],
						'afzender'  => $stap['afzender'],
						'opmerking' => $stap['tekst'],
						'wanneer'   => $stempel( -0.02 * $nummer ),
						'intern'    => false,
					),
				)
			),
			'verloop'
		);
	};

	$service = 'Homburg Service';
	$claims  = array(
		array(
			'invoer'  => array( 'soort' => 'machine', 'machine' => 'DEMO-TV8-0001', 'hectares' => '860', 'klacht' => 'Zaai-element 3 geeft onregelmatige afgifte, ook na afstellen en reinigen.', 'klantreferentie' => 'Werkbon 2041' ),
			'dagen'   => 9,
			'stappen' => array(
				array( 'status' => 'in_behandeling', 'wie' => $service, 'afzender' => 'homburg', 'tekst' => 'Bedankt, we hebben je claim in behandeling genomen.', 'dagen' => 8 ),
				array( 'status' => 'bij_fabrikant', 'wie' => $service, 'afzender' => 'homburg', 'tekst' => 'Doorgestuurd naar de fabrikant. De reactietermijn is doorgaans twee weken.', 'dagen' => 6 ),
				array( 'wie' => $presentator->display_name, 'afzender' => 'dealer', 'tekst' => 'De klant vraagt ernaar. Is er al iets bekend?', 'dagen' => 2 ),
				array( 'wie' => $service, 'afzender' => 'homburg', 'tekst' => 'Nog niets terug. We hebben vandaag gerappelleerd en komen er snel op terug.', 'dagen' => 1 ),
			),
		),
		array(
			'invoer'  => array( 'soort' => 'machine', 'machine' => 'DEMO-HN4-0002', 'hectares' => '430', 'klacht' => 'Spuitboom zakt tijdens het werk aan de linkerzijde weg. Hydrauliek lijkt in orde.' ),
			'dagen'   => 5,
			'stappen' => array(
				array( 'status' => 'info_nodig', 'wie' => $service, 'afzender' => 'homburg', 'tekst' => 'Kun je een foto van het typeplaatje en de cilinder aan de linkerzijde sturen? Dan kunnen we dit beoordelen.', 'dagen' => 1 ),
			),
		),
		array(
			'invoer'  => array( 'soort' => 'machine', 'machine' => 'DEMO-BM35-0003', 'hectares' => '290', 'klacht' => 'Lager van de strooischijf maakt lawaai en loopt warm.', 'klantreferentie' => 'Werkbon 1987' ),
			'dagen'   => 21,
			'stappen' => array(
				array( 'status' => 'in_behandeling', 'wie' => $service, 'afzender' => 'homburg', 'tekst' => 'Claim is in behandeling.', 'dagen' => 20 ),
				array( 'status' => 'goedgekeurd', 'wie' => $service, 'afzender' => 'homburg', 'tekst' => 'Goedgekeurd. Het vervangende lager is onderweg.', 'dagen' => 14 ),
			),
		),
		array(
			// Een machineclaim: de database weigert nu onderdelenclaims met "Onbekende machine".
			'invoer'  => array( 'soort' => 'machine', 'machine' => 'DEMO-TV8-0001', 'hectares' => '910', 'klacht' => 'Terminal toont af en toe een storing op de doseereenheid.' ),
			'dagen'   => 0.2,
			'stappen' => array(),
		),
	);
	foreach ( $claims as $c ) {
		$machine = null;
		foreach ( HDP_Garantie::machines() as $kandidaat ) {
			if ( $kandidaat['serienummer'] === $c['invoer']['machine'] ) {
				$machine = $kandidaat;
			}
		}
		$rijen = $stop(
			HDP_Supabase::voeg_toe(
				$a,
				'claims',
				array(
					array_merge(
						HDP_Garantie::dealervelden(),
						array(
							'soort'                 => 'machine',
							'machine_id'            => $machine['id'],
							'hectares'              => $c['invoer']['hectares'],
							'klacht'                => $c['invoer']['klacht'],
							'klantreferentie'       => isset( $c['invoer']['klantreferentie'] ) ? $c['invoer']['klantreferentie'] : null,
							'te_claimen_onderdelen' => array(),
							'onderdeel_retour'      => false,
						)
					),
				)
			),
			'claim'
		);
		$claim = $rijen[0];
		foreach ( array_values( $c['stappen'] ) as $i => $stap ) {
			$zet_stap( $claim, $stap, $i + 1 );
		}
		if ( $c['stappen'] ) {
			$stop( HDP_Supabase::werk_bij( $a, 'claims', array( 'id' => 'eq.' . $claim['id'] ), array( 'behandelaar' => $service, 'opgepakt_op' => $stempel( -0.01 ) ) ), 'behandelaar' );
		}
	}
	echo 'garantie: ' . count( $machines ) . ' machines, ' . count( $claims ) . " claims\n";
} else {
	echo "garantie: overgeslagen, geen verbinding\n";
}

global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_hdp\\_%' OR option_name LIKE '\\_transient\\_timeout\\_hdp\\_%'" );
wc_delete_product_transients();

echo "\nKLAAR. Inloggen: " . HDP_DEMO_LOGIN . ' / ' . $wachtwoord . "\n";
