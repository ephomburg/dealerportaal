<?php
/**
 * Basis-integratie van WooCommerce in het Homburg Dealerportaal-thema.
 *
 * Raakt geen WordPress- of WooCommerce-kernbestanden aan; voegt alleen
 * thema-ondersteuning toe en laadt de eigen stylesheet, uitsluitend
 * wanneer WooCommerce actief is.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooCommerce' ) ) {
	return;
}

add_action( 'after_setup_theme', 'homburg_wc_theme_support' );
/**
 * Meldt het thema aan bij WooCommerce. Zonder deze declaratie neemt
 * WooCommerce zijn eigen (afwijkende) standaardbreedtes en galerij-opties
 * aan in plaats van die van dit thema.
 */
function homburg_wc_theme_support() {
	add_theme_support( 'woocommerce' );
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );
}

add_filter( 'woocommerce_get_price_suffix', 'homburg_wc_prijs_suffix', 20 );
/**
 * "excl. btw" achter elke prijs, via WooCommerce's eigen suffix-mechanisme
 * zodat het overal precies één keer verschijnt (kaart, productpagina,
 * winkelmand). Vervangt eventuele instelling in WooCommerce > Instellingen.
 */
function homburg_wc_prijs_suffix() {
	return ' <span class="hdp-wc-btw">' . esc_html__( 'excl. btw', 'homburg-dealerportaal-theme' ) . '</span>';
}

add_filter( 'woocommerce_empty_price_html', 'homburg_wc_prijs_op_aanvraag' );
/**
 * Een product zonder prijs is een product op aanvraag: dat kan niet in de
 * winkelwagen, en zonder deze tekst stond er bij de prijs helemaal niets.
 */
function homburg_wc_prijs_op_aanvraag() {
	return '<span class="hdp-wc-op-aanvraag">' . esc_html( homburg_wc_prijs_op_aanvraag_tekst() ) . '</span>';
}

function homburg_wc_prijs_op_aanvraag_tekst() {
	return class_exists( 'HDP_I18N' ) ? HDP_I18N::t( 'wc_prijs_op_aanvraag' ) : 'Prijs op aanvraag';
}

add_action( 'woocommerce_single_product_summary', 'homburg_wc_op_aanvraag_contact', 31 );
/** Op de productpagina: bij wie je de prijs opvraagt, als dat is ingesteld. */
function homburg_wc_op_aanvraag_contact() {
	global $product;
	if ( ! $product || '' !== $product->get_price() || ! class_exists( 'HDP_Settings' ) ) {
		return;
	}
	$email    = HDP_Settings::get( 'contact_email' );
	$telefoon = HDP_Settings::get( 'contact_telefoon' );
	if ( ! $email && ! $telefoon ) {
		return;
	}
	echo '<p class="hdp-wc-op-aanvraag-contact">' . esc_html( HDP_I18N::t( 'wc_op_aanvraag_contact' ) ) . ' ';
	if ( $email ) {
		echo '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
	}
	if ( $telefoon ) {
		echo ( $email ? ' · ' : '' ) . esc_html( $telefoon );
	}
	echo '</p>';
}

add_filter( 'woocommerce_coupons_enabled', '__return_false' );
/**
 * Homburg werkt niet met kortingsbonnen — haalt het coupon-veld (en de
 * bijbehorende instellingen/functionaliteit) overal weg i.p.v. het per
 * pagina te verbergen.
 */

add_action( 'woocommerce_before_single_product', 'homburg_wc_terug_naar_winkel_knop' );
/**
 * "Terug naar de winkel"-knop op de losse productpagina — die draait nog
 * op de klassieke templates, dus deze hook volstaat daar.
 */
function homburg_wc_terug_naar_winkel_knop() {
	echo homburg_wc_terug_naar_winkel_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- escapet intern.
}

add_filter( 'gettext_woocommerce', 'homburg_wc_zonder_beoordelingen', 10, 2 );
/** Bij de weergavenaam noemt WooCommerce "beoordelingen"; die bestaan in het portaal niet. */
function homburg_wc_zonder_beoordelingen( $vertaling, $origineel ) {
	if ( 'This will be how your name will be displayed in the account section and in reviews' === $origineel ) {
		// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- bestaande tekst van WooCommerce.
		return __( 'This will be how your name will be displayed in the account section', 'woocommerce' );
	}
	return $vertaling;
}

function homburg_wc_terug_naar_winkel_html() {
	return sprintf(
		'<a class="hdp-terug-naar-winkel" href="%1$s"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>%2$s</a>',
		esc_url( get_permalink( wc_get_page_id( 'shop' ) ) ),
		esc_html__( 'Terug naar de webshop', 'homburg-dealerportaal-theme' )
	);
}

add_filter( 'the_content', 'homburg_wc_terug_naar_winkel_bij_blokken' );
/**
 * Winkelmand en afrekenen draaien op WooCommerce-blokken (geen klassieke
 * templates), dus de hooks hierboven vuren daar niet — de knop hier in
 * plaats daarvan vóór de bloktekst plakken.
 */
function homburg_wc_terug_naar_winkel_bij_blokken( $content ) {
	if ( ( is_cart() || is_checkout() ) && is_main_query() && in_the_loop() ) {
		// De winkelwagen had geen paginakop; het WooCommerce-blok zelf heeft er geen.
		$kop = is_cart() ? '<h1 class="hdp-wc-paginakop">' . esc_html( get_the_title( wc_get_page_id( 'cart' ) ) ) . '</h1>' : '';
		return homburg_wc_terug_naar_winkel_html() . $kop . $content;
	}
	return $content;
}

add_action( 'template_redirect', 'homburg_wc_oude_adressen', 1 );
/**
 * De webshop had Engelse adressen (/shop/, /cart/, /my-account/ ...). Wie
 * zo'n oud adres nog heeft, bijvoorbeeld als bladwijzer of in een mail, komt
 * op het nieuwe Nederlandse adres uit, met de rest van het pad erachter.
 */
function homburg_wc_oude_adressen() {
	if ( ! is_404() ) {
		return;
	}
	$pad    = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$query  = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_QUERY ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	$oud    = array(
		'/shop/'             => wc_get_page_id( 'shop' ),
		'/cart/'             => wc_get_page_id( 'cart' ),
		'/checkout/'         => wc_get_page_id( 'checkout' ),
		'/my-account/'       => wc_get_page_id( 'myaccount' ),
		'/product-category/' => 'categorie',
	);
	foreach ( $oud as $begin => $doel ) {
		if ( 0 !== strpos( trailingslashit( $pad ), $begin ) ) {
			continue;
		}
		$rest = substr( trailingslashit( $pad ), strlen( $begin ) );
		if ( 'categorie' === $doel ) {
			$instellingen = (array) get_option( 'woocommerce_permalinks', array() );
			$categorie    = trim( (string) ( $instellingen['category_base'] ?? '' ), '/' );
			$basis        = home_url( '/' . ( '' !== $categorie ? $categorie : 'product-category' ) . '/' );
		} else {
			$basis = get_permalink( $doel );
		}
		if ( ! $basis || trailingslashit( $basis ) === trailingslashit( home_url( $begin ) ) ) {
			return;
		}
		wp_safe_redirect( trailingslashit( $basis ) . $rest . ( '' !== $query ? '?' . $query : '' ), 301 );
		exit;
	}
}

add_filter( 'page_template_hierarchy', 'homburg_wc_sjabloon_per_pagina' );
/**
 * De winkelpagina's hebben eigen sjablonen (page-my-account.html enz.).
 * WordPress zoekt die op aan de hand van het adres van de pagina; sinds de
 * adressen Nederlands zijn (/mijn-account/ i.p.v. /my-account/) vond hij
 * het accountsjabloon niet meer en viel hij terug op een kale pagina zonder
 * werkvlak en zijmenu. Daarom hier op de pagina zelf, los van het adres.
 *
 * @param string[] $sjablonen Sjablonen die WordPress probeert, op volgorde.
 * @return string[]
 */
function homburg_wc_sjabloon_per_pagina( $sjablonen ) {
	if ( ! function_exists( 'wc_get_page_id' ) ) {
		return $sjablonen;
	}
	$id    = get_queried_object_id();
	$vaste = array(
		'myaccount' => 'page-my-account.php',
		'cart'      => 'page-cart.php',
		'checkout'  => 'page-checkout.php',
	);
	foreach ( $vaste as $pagina => $sjabloon ) {
		if ( $id && wc_get_page_id( $pagina ) === $id && ! in_array( $sjabloon, $sjablonen, true ) ) {
			array_unshift( $sjablonen, $sjabloon );
		}
	}
	return $sjablonen;
}

add_filter( 'woocommerce_output_related_products_args', 'homburg_wc_gerelateerd_args' );
/**
 * Gerelateerde producten: drie stuks in drie kolommen, zodat de rij
 * onderaan de productpagina netjes vol staat.
 */
function homburg_wc_gerelateerd_args( $args ) {
	$args['posts_per_page'] = 3;
	$args['columns']        = 3;
	return $args;
}

add_filter( 'woocommerce_catalog_orderby', 'homburg_wc_sorteeropties' );
/**
 * Alleen sorteringen waar een dealer iets aan heeft.
 *
 * WooCommerce biedt ook "populariteit" en "gemiddelde waardering" aan, maar
 * hier worden geen beoordelingen gegeven en is populariteit niet iets waar
 * een dealer op zoekt. Wel gemist: sorteren op artikelnummer, terwijl dealers
 * daar juist in denken.
 *
 * "menu_order" blijft de sleutel van de standaardsortering (die staat zo in
 * de WooCommerce-instellingen), maar sorteert hier op omschrijving — zie
 * homburg_wc_sorteer_args(). "relevance" voegt WooCommerce zelf toe op een
 * zoekresultaat; die blijft dan staan, anders klopt de keuzelijst daar niet.
 */
function homburg_wc_sorteeropties( $opties ) {
	$eigen = array(
		'menu_order' => __( 'Omschrijving (A–Z)', 'homburg-dealerportaal-theme' ),
		'sku'        => __( 'Artikelnummer', 'homburg-dealerportaal-theme' ),
		'price'      => __( 'Prijs: laag naar hoog', 'homburg-dealerportaal-theme' ),
		'price-desc' => __( 'Prijs: hoog naar laag', 'homburg-dealerportaal-theme' ),
	);

	if ( isset( $opties['relevance'] ) ) {
		$eigen = array( 'relevance' => $opties['relevance'] ) + $eigen;
	}

	return $eigen;
}

add_filter( 'woocommerce_get_catalog_ordering_args', 'homburg_wc_sorteer_args', 10, 2 );
/**
 * De query achter de eigen sorteringen.
 *
 * WooCommerce sorteert "menu_order" op handmatige volgorde en pas daarna op
 * titel. Niemand zet hier een handmatige volgorde, maar mocht dat ooit
 * gebeuren, dan zou "Omschrijving (A–Z)" stilletjes iets anders doen dan er
 * staat — dus hier puur op titel.
 */
function homburg_wc_sorteer_args( $args, $orderby ) {
	if ( 'menu_order' === $orderby ) {
		$args['orderby']  = 'title';
		$args['order']    = 'ASC';
		$args['meta_key'] = ''; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- leegmaken, geen sortering op meta.
	} elseif ( 'sku' === $orderby ) {
		$args['orderby']  = 'meta_value';
		$args['order']    = 'ASC';
		$args['meta_key'] = '_sku'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- eenmalige sortering op de winkelpagina.
	}

	return $args;
}

add_filter( 'woocommerce_date_format', 'homburg_wc_datumformaat' );
/**
 * Datums in de WooCommerce-schermen (o.a. "geplaatst op ..." bij een losse
 * bestelling) als "17 september 2026". Ze volgden de algemene
 * site-instelling, en die stond op de Engelse volgorde "september 17, 2026".
 */
function homburg_wc_datumformaat() {
	return 'j F Y';
}

add_filter( 'woocommerce_page_title', 'homburg_wc_webshop_titel' );
/**
 * "Webshop" als titel boven de winkel, zoals hij overal elders in het
 * portaal heet (startpagina, zijmenu). De WooCommerce-pagina zelf heet
 * "Winkel", en dat stond er dan als enige plek anders.
 */
function homburg_wc_webshop_titel( $titel ) {
	return is_shop() ? __( 'Webshop', 'homburg-dealerportaal-theme' ) : $titel;
}

add_action( 'wp_enqueue_scripts', 'homburg_wc_enqueue_assets', 20 );
/**
 * Eigen WooCommerce-stylesheet, losgekoppeld van de rest van het thema en
 * alleen geladen op WooCommerce-pagina's, zodat portaalpagina's die niets
 * met de webshop te maken hebben hier geen last van kunnen krijgen.
 */
function homburg_wc_enqueue_assets() {
	if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() && ! is_account_page() ) {
		return;
	}

	// woocommerce.css hergebruikt de knoppen/kaarten/iconen uit de plugin
	// (dealerportaal.css) i.p.v. die hier te herhalen — dat bestand laadt
	// normaal alleen voor ingelogde bezoekers of op de portaalpagina's zelf
	// (zie HDP_Blocks::enqueue_assets), dus hier expliciet ook meenemen als
	// afhankelijkheid. Zonder dit zou een uitgelogde webshopbezoeker de
	// CSS-variabelen (--hdp-radius/--hdp-schaduw) mislopen die
	// woocommerce.css gebruikt.
	if ( defined( 'HDP_PLUGIN_URL' ) && defined( 'HDP_VERSION' ) ) {
		wp_enqueue_style( 'hdp-dealerportaal', HDP_PLUGIN_URL . 'assets/css/dealerportaal.css', array(), HDP_VERSION );
	}

	wp_enqueue_style(
		'homburg-woocommerce',
		get_theme_file_uri( 'assets/css/woocommerce.css' ),
		array( 'homburg-dealerportaal-theme', 'hdp-dealerportaal' ),
		wp_get_theme()->get( 'Version' )
	);
}

add_filter( 'woocommerce_account_menu_items', 'homburg_wc_verberg_downloads_tabblad' );
/**
 * WooCommerce's eigen "Downloads"-tabblad gaat over digitale
 * productdownloads (aankopen met een downloadbaar bestand erbij) — iets
 * wat Homburg niet verkoopt, dus deze pagina toont altijd de melding "Geen
 * downloads beschikbaar". De écht bruikbare downloads (brochures,
 * handleidingen) staan op een aparte pagina (/downloads/, zie
 * HDP_Downloads_Render) die hier niets mee te maken heeft — dit
 * verwarrende, altijd-lege tabblad daarom gewoon uit het menu.
 */
function homburg_wc_verberg_downloads_tabblad( $items ) {
	unset( $items['downloads'] );
	return $items;
}

add_filter( 'woocommerce_account_menu_items', 'homburg_wc_verberg_dashboard_tabblad' );
/**
 * Haalt "Dashboard" uit het accountmenu — zie
 * homburg_wc_myaccount_naar_bestellingen() hieronder voor het waarom. Een
 * menu-item dat je doorstuurt naar het item eronder hoort er niet te staan.
 */
function homburg_wc_verberg_dashboard_tabblad( $items ) {
	unset( $items['dashboard'] );
	return $items;
}

add_action( 'template_redirect', 'homburg_wc_myaccount_naar_bestellingen' );
/**
 * Stuurt de kale /mijn-account/ door naar Bestellingen.
 *
 * Het portaal had twee voordeuren: de startpagina met tegels (webshop,
 * downloads, garantie...) en daarnaast het WooCommerce-dashboard, dat in
 * dezelfde kaartstijl nog een tegeloverzicht toonde — met "Downloads" zelfs
 * in beide. Wie op "Mijn account" klikte, leek weer op de startpagina te
 * komen.
 *
 * De startpagina is nu de enige hub. "Mijn account" opent op de
 * bestellingen, waar een dealer feitelijk voor komt; de rest (adressen,
 * accountdetails, favorieten, snel bestellen) blijft gewoon in de
 * navigatiekolom ernaast staan. De doorverwijzing blijft bestaan voor wie
 * /mijn-account/ nog als bladwijzer heeft.
 */
function homburg_wc_myaccount_naar_bestellingen() {
	// Uitgelogd staat hier het inlogformulier; dat moet blijven.
	if ( ! is_user_logged_in() || ! function_exists( 'is_account_page' ) ) {
		return;
	}

	// is_wc_endpoint_url() zonder argument is alleen onwaar op /mijn-account/
	// zelf, dus niet op de onderliggende tabbladen.
	if ( ! is_account_page() || is_wc_endpoint_url() ) {
		return;
	}

	wp_safe_redirect( wc_get_account_endpoint_url( 'orders' ) );
	exit;
}

add_filter( 'woocommerce_customer_email_verification_should_show_prompt', '__return_false' );
/**
 * WooCommerce's melding "Bevestig uw e-mailadres" op Mijn account →
 * Bestellingen dient om eerdere gastbestellingen (zonder account) aan een
 * account te koppelen — dit portaal kent geen gasten: alleen goedgekeurde,
 * ingelogde dealers kunnen hier iets bestellen. De melding hoort dus nooit
 * ergens op te slaan; verbergen via WooCommerce's eigen schakelaar hiervoor
 * i.p.v. de tekst te laten staan (en te vertalen) voor een situatie die
 * hier niet voorkomt.
 */

add_action( 'woocommerce_before_account_navigation', 'homburg_wc_myaccount_kop' );
/**
 * Dezelfde paginakop als de rest van het portaal boven Mijn account.
 *
 * Stond hier eerder als een losse titel binnen de WooCommerce-wrapper, en
 * zei altijd "Mijn account" — ook als je op Bestellingen of Adressen keek.
 * Nu staat het onderdeel waar je bent in de titel, met "Mijn account" als
 * bovenschrift, en een terugweg naar het portaal die er helemaal niet was.
 *
 * Dit hook-punt zit binnen de WooCommerce-wrapper, dus de kop krijgt de
 * compacte vorm: dezelfde onderdelen in dezelfde volgorde, zonder de brede
 * band die op de andere pagina's over de volle breedte loopt.
 */
function homburg_wc_myaccount_kop() {
	if ( ! class_exists( 'HDP_Paginakop' ) ) {
		return;
	}

	HDP_Paginakop::render(
		array_merge(
			array(
				'bovenschrift' => get_the_title(),
				'titel'        => homburg_wc_account_onderdeel(),
				'compact'      => true,
			),
			HDP_Paginakop::terug_naar_portaal()
		)
	);
}

/**
 * De naam van het onderdeel waar de dealer nu is: Bestellingen, Adressen,
 * Accountdetails. WooCommerce weet dat zelf voor zijn eigen endpoints,
 * inclusief "Bestelling #307" bij een losse bestelling.
 *
 * @return string
 */
function homburg_wc_account_onderdeel() {
	$endpoint = ( function_exists( 'WC' ) && WC()->query ) ? WC()->query->get_current_endpoint() : '';

	// Dezelfde labels als het menu ernaast gebruikt, zodat de titel altijd
	// overeenkomt met het tabblad dat oplicht. (Niet via
	// wc_get_endpoint_title(): die functie bestaat in deze WooCommerce-versie
	// niet en liet de hele accountpagina halverwege afbreken.)
	$items = function_exists( 'wc_get_account_menu_items' ) ? wc_get_account_menu_items() : array();

	// Een losse bestelling hoort bij het tabblad Bestellingen.
	if ( 'view-order' === $endpoint ) {
		$endpoint = 'orders';
	}

	if ( $endpoint && isset( $items[ $endpoint ] ) ) {
		return $items[ $endpoint ];
	}

	// Laatste redmiddel: zonder titel laat de paginakop zichzelf weg, en dan
	// staat de pagina er zonder enige kop — erger dan een algemene titel.
	$titel = get_the_title();

	return $titel ? $titel : __( 'Mijn account', 'homburg-dealerportaal-theme' );
}

add_action( 'woocommerce_before_main_content', 'homburg_wc_winkel_kop', 5 );
/**
 * Dezelfde paginakop boven de winkel.
 *
 * Daar stond een klein grijs kruimelpad ("Home / Winkel") met een kale
 * titel eronder, en geen enkele manier om terug te komen — op elke andere
 * portaalpagina staat die link linksboven.
 */
function homburg_wc_winkel_kop() {
	if ( ! is_shop() && ! is_product_taxonomy() ) {
		return;
	}

	if ( ! class_exists( 'HDP_Paginakop' ) ) {
		return;
	}

	HDP_Paginakop::render(
		array_merge(
			array(
				'titel' => woocommerce_page_title( false ),
			),
			HDP_Paginakop::terug_naar_portaal()
		)
	);
}

add_action( 'wp', 'homburg_wc_winkel_oude_kop_weg' );
/**
 * De oude titel en het kruimelpad weghalen; die zijn nu vervangen.
 */
function homburg_wc_winkel_oude_kop_weg() {
	if ( ! function_exists( 'is_shop' ) || ( ! is_shop() && ! is_product_taxonomy() ) ) {
		return;
	}

	remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
	add_filter( 'woocommerce_show_page_title', '__return_false' );
}

add_filter( 'the_title', 'homburg_wc_paginatitel', 10, 2 );
add_filter( 'single_post_title', 'homburg_wc_paginatitel', 10, 2 );
/**
 * De titels van de WooCommerce-pagina's staan in de database, in het
 * Nederlands. Via __() komen ze langs de Franse teksten van het thema.
 *
 * @param string      $titel De titel.
 * @param int|WP_Post $post  De pagina.
 */
function homburg_wc_paginatitel( $titel, $post = 0 ) {
	if ( is_admin() || ! class_exists( 'HDP_I18N' ) || ! HDP_I18N::is_vertaald() ) {
		return $titel;
	}
	$id = $post instanceof WP_Post ? $post->ID : (int) $post;
	$paginas = array(
		wc_get_page_id( 'myaccount' ) => __( 'Mijn account', 'homburg-dealerportaal-theme' ),
		wc_get_page_id( 'cart' )      => __( 'Winkelwagen', 'homburg-dealerportaal-theme' ),
		wc_get_page_id( 'checkout' )  => __( 'Afrekenen', 'homburg-dealerportaal-theme' ),
		wc_get_page_id( 'shop' )      => __( 'Webshop', 'homburg-dealerportaal-theme' ),
	);
	return isset( $paginas[ $id ] ) ? $paginas[ $id ] : $titel;
}
