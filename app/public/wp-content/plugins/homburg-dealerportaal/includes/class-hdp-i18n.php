<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lichte, eigen taalwissel voor het dealerportaal (NL/FR) — geen
 * vertaalplugin, want de inhoud van dit portaal wordt al door onszelf
 * gerenderd. De taalkeuze staat nu in een cookie die de bezoeker zelf
 * omzet via de knop in de header. Later kan hier automatisch op
 * doorgeschakeld worden op basis van dealerrechten (bijv. "is deze
 * dealer Frans?") door vóór de cookie-check gewoon een eigen regel toe
 * te voegen in huidige_taal() — de rest van de site hoeft daarvoor niet
 * aangepast te worden, want alles loopt al via HDP_I18N::t().
 */
class HDP_I18N {

	const COOKIE = 'hdp_taal';
	const TALEN  = array( 'nl', 'fr' );

	public static function init() {
		add_action( 'init', array( __CLASS__, 'verwerk_taalwissel' ) );
		add_filter( 'language_attributes', array( __CLASS__, 'voeg_data_taal_toe' ) );
	}

	/**
	 * Zet de huidige taal als data-taal op de <html>-tag. Statische
	 * (niet-PHP-gerenderde) blokken zoals homburg/info-kaart tonen zowel de
	 * NL- als de FR-tekst in de HTML en verbergen er via zuivere CSS één van
	 * de twee — zo werkt de taalwissel ook voor content die niet meer per
	 * request door PHP wordt opgebouwd.
	 */
	public static function voeg_data_taal_toe( $output ) {
		return $output . ' data-taal="' . esc_attr( self::huidige_taal() ) . '"';
	}

	/**
	 * Wisselt alleen de weergavetaal (cookie), zonder verdere state te
	 * wijzigen — een link naar bijv. ?hdp_taal=fr moet overal (ook vanuit
	 * een bladwijzer) blijven werken, dus bewust geen nonce hier.
	 */
	public static function verwerk_taalwissel() {
		if ( ! isset( $_GET['hdp_taal'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$taal = sanitize_key( wp_unslash( $_GET['hdp_taal'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $taal, self::TALEN, true ) ) {
			return;
		}

		setcookie( self::COOKIE, $taal, time() + YEAR_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN );
		$_COOKIE[ self::COOKIE ] = $taal;

		wp_safe_redirect( remove_query_arg( 'hdp_taal' ) );
		exit;
	}

	public static function huidige_taal() {
		$taal = isset( $_COOKIE[ self::COOKIE ] ) ? sanitize_key( wp_unslash( $_COOKIE[ self::COOKIE ] ) ) : '';
		return in_array( $taal, self::TALEN, true ) ? $taal : 'nl';
	}

	public static function is_frans() {
		return 'fr' === self::huidige_taal();
	}

	/**
	 * Wisselt tussen twee waarden op basis van de huidige taal — handig
	 * voor bewerkbare blokvelden die al een Nederlandse waarde uit
	 * block.json/de editor hebben en er een Franse naast krijgen.
	 * Valt terug op $nl wanneer $fr leeg is (nog niet ingevuld).
	 */
	public static function kies( $nl, $fr ) {
		if ( self::is_frans() && '' !== trim( (string) $fr ) ) {
			return $fr;
		}
		return $nl;
	}

	/**
	 * Maand- en dagnamen in het Frans.
	 *
	 * Met de hand en niet via WordPress' eigen taalomschakeling: er staan
	 * op deze site geen Franse taalbestanden, dus switch_to_locale() valt
	 * terug op het Engels — "22 October 2026" in plaats van "22 octobre
	 * 2026". Dat is erger dan het Nederlands laten staan. Het portaal
	 * draagt zijn eigen teksten (zie teksten() hieronder); dit hoort
	 * daarbij.
	 */
	const MAANDEN_FR = array(
		'janvier',
		'février',
		'mars',
		'avril',
		'mai',
		'juin',
		'juillet',
		'août',
		'septembre',
		'octobre',
		'novembre',
		'décembre',
	);

	const MAANDEN_FR_KORT = array(
		'janv.',
		'févr.',
		'mars',
		'avr.',
		'mai',
		'juin',
		'juil.',
		'août',
		'sept.',
		'oct.',
		'nov.',
		'déc.',
	);

	/** Vanaf zondag, zoals het formaatteken "w" telt. */
	const DAGEN_FR = array(
		'dimanche',
		'lundi',
		'mardi',
		'mercredi',
		'jeudi',
		'vendredi',
		'samedi',
	);

	const DAGEN_FR_KORT = array( 'dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.' );

	/**
	 * Een datum in de taal die de dealer zelf heeft gekozen.
	 *
	 * WordPress' eigen date_i18n() volgt de taal van de site, niet die van
	 * de bezoeker. Daardoor stond er bij een Waalse dealer "22 oktober
	 * 2026" tussen verder Franse tekst — op elf plekken in het portaal.
	 *
	 * Gebruik dit overal waar een datum aan een dealer getoond wordt.
	 *
	 * @param string $formaat     Formaat zoals bij date(), bijv. 'j F Y'.
	 * @param int    $tijdstempel Unix-tijd.
	 * @return string
	 */
	public static function datum( $formaat, $tijdstempel ) {
		if ( ! self::is_frans() ) {
			return date_i18n( $formaat, $tijdstempel );
		}

		$maand = (int) date_i18n( 'n', $tijdstempel );
		$dag   = (int) date_i18n( 'w', $tijdstempel );

		$woorden = array(
			'F' => self::MAANDEN_FR[ $maand - 1 ],
			'M' => self::MAANDEN_FR_KORT[ $maand - 1 ],
			'l' => self::DAGEN_FR[ $dag ],
			'D' => self::DAGEN_FR_KORT[ $dag ],
		);

		// Teken voor teken, zodat een al ontsnapt teken (\j) met rust
		// gelaten wordt en alleen een echt formaatteken vervangen wordt.
		$uit    = '';
		$lengte = strlen( $formaat );

		for ( $i = 0; $i < $lengte; $i++ ) {
			$teken = $formaat[ $i ];

			if ( '\\' === $teken ) {
				$uit .= $teken;
				if ( isset( $formaat[ $i + 1 ] ) ) {
					++$i;
					$uit .= $formaat[ $i ];
				}
				continue;
			}

			$uit .= isset( $woorden[ $teken ] ) ? self::letterlijk( $woorden[ $teken ] ) : $teken;
		}

		return date_i18n( $uit, $tijdstempel );
	}

	/**
	 * Zet tekst om naar iets wat date() letterlijk overneemt.
	 *
	 * Zonder dit leest date() de letters in "octobre" als formaattekens en
	 * komt er onzin uit.
	 */
	private static function letterlijk( $tekst ) {
		return preg_replace( '/([A-Za-z])/', '\\\\$1', $tekst );
	}

	/** Vaste, niet-bewerkbare teksten (labels, knoppen, meldingen). */
	public static function t( $sleutel ) {
		$teksten = self::teksten();
		$taal    = self::huidige_taal();

		if ( isset( $teksten[ $sleutel ][ $taal ] ) ) {
			return $teksten[ $sleutel ][ $taal ];
		}
		return isset( $teksten[ $sleutel ]['nl'] ) ? $teksten[ $sleutel ]['nl'] : $sleutel;
	}

	private static function teksten() {
		return array(
			// Header/footer
			'header_caption'    => array( 'nl' => 'Dealerportaal', 'fr' => 'Portail concessionnaire' ),
			// Header: stonden er als vaste Nederlandse tekst, ook voor een Franstalige dealer.
			'header_favorieten'   => array( 'nl' => 'Mijn favorieten', 'fr' => 'Mes favoris' ),
			'header_links'        => array( 'nl' => 'Links', 'fr' => 'Liens' ),
			'header_links_label'  => array( 'nl' => 'Links naar Homburg Holland en Belgium', 'fr' => 'Liens vers Homburg Holland et Belgium' ),
			'header_winkelwagen'  => array( 'nl' => 'Winkelwagen', 'fr' => 'Panier' ),
			'header_mijn_account' => array( 'nl' => 'Mijn account', 'fr' => 'Mon compte' ),
			'header_accountmenu'  => array( 'nl' => 'Accountmenu', 'fr' => 'Menu du compte' ),
			// Occasions en restpartijen (occasions.php in het thema).
			'zijbalk_occasions'        => array( 'nl' => 'Occasions', 'fr' => 'Occasions' ),
			'occasion_label'           => array( 'nl' => 'Occasion', 'fr' => 'Occasion' ),
			'occasion_staat'           => array( 'nl' => 'Staat', 'fr' => 'État' ),
			'occasion_staat_nieuw'     => array( 'nl' => 'Nieuw', 'fr' => 'Neuf' ),
			'occasion_staat_als_nieuw' => array( 'nl' => 'Als nieuw', 'fr' => 'Comme neuf' ),
			'occasion_staat_gebruikt'  => array( 'nl' => 'Gebruikt', 'fr' => 'D’occasion' ),
			'occasion_nog_een'         => array( 'nl' => 'Nog 1 beschikbaar', 'fr' => 'Encore 1 disponible' ),
			'occasion_nog_meer'        => array( 'nl' => 'Nog %d beschikbaar', 'fr' => 'Encore %d disponibles' ),
			'occasion_op_is_op'        => array( 'nl' => 'Op = op', 'fr' => 'Jusqu’à épuisement du stock' ),
			// Zoekbalk in de header (HDP_Zoeken).
			'zoeken_label'          => array( 'nl' => 'Zoeken in het portaal', 'fr' => 'Rechercher dans le portail' ),
			'zoeken_placeholder'    => array( 'nl' => 'Zoek artikel, claim, bestelling of document…', 'fr' => 'Rechercher article, réclamation, commande ou document…' ),
			'zoeken_groep_paginas'      => array( 'nl' => 'Onderdelen', 'fr' => 'Rubriques' ),
			'zoeken_groep_artikelen'    => array( 'nl' => 'Artikelen', 'fr' => 'Articles' ),
			'zoeken_groep_bestellingen' => array( 'nl' => 'Bestellingen', 'fr' => 'Commandes' ),
			'zoeken_groep_claims'       => array( 'nl' => 'Garantieclaims', 'fr' => 'Réclamations de garantie' ),
			'zoeken_groep_machines'     => array( 'nl' => 'Machines', 'fr' => 'Machines' ),
			'zoeken_groep_downloads'    => array( 'nl' => 'Documenten', 'fr' => 'Documents' ),
			'zoeken_soort_download'     => array( 'nl' => 'Download', 'fr' => 'Téléchargement' ),
			'zoeken_soort_content'      => array( 'nl' => 'Content', 'fr' => 'Contenu' ),
			'zoeken_soort_huisstijl'    => array( 'nl' => 'Huisstijl', 'fr' => 'Charte graphique' ),
			'zoeken_bestelling'     => array( 'nl' => 'Bestelling #%s', 'fr' => 'Commande n° %s' ),
			'zoeken_alle_artikelen' => array( 'nl' => 'Alle artikelen met „%s” in de webshop', 'fr' => 'Tous les articles « %s » dans la boutique' ),
			'zoeken_laden'          => array( 'nl' => 'Zoeken…', 'fr' => 'Recherche…' ),
			'zoeken_niets'          => array( 'nl' => 'Niets gevonden. Probeer een artikelnummer, serienummer of ordernummer.', 'fr' => 'Aucun résultat. Essayez un numéro d’article, de série ou de commande.' ),
			'zoeken_fout'           => array( 'nl' => 'Zoeken lukt nu even niet. Probeer het zo opnieuw.', 'fr' => 'La recherche est momentanément indisponible. Réessayez dans un instant.' ),
			'zoeken_nieuw_tabblad'  => array( 'nl' => 'opent in een nieuw tabblad', 'fr' => 's’ouvre dans un nouvel onglet' ),
			'nav_aria'          => array( 'nl' => 'Homburg-websites', 'fr' => 'Sites Homburg' ),
			'footer_volg_ons'   => array( 'nl' => 'Volg ons', 'fr' => 'Suivez-nous' ),
			'footer_copyright'  => array(
				'nl' => 'Homburg Machinehandel BV – Dealerportaal. Alle rechten voorbehouden.',
				'fr' => 'Homburg Machinehandel BV – Portail concessionnaire. Tous droits réservés.',
			),
			'footer_privacy'    => array( 'nl' => 'Privacyverklaring', 'fr' => 'Politique de confidentialité' ),
			'footer_admin'      => array( 'nl' => 'Adminportaal (test)', 'fr' => 'Portail admin (test)' ),

			// Login
			'login_titel'       => array( 'nl' => 'Inloggen dealerportaal', 'fr' => 'Connexion au portail concessionnaire' ),
			'label_gebruiker'   => array( 'nl' => 'Gebruikersnaam', 'fr' => "Nom d'utilisateur" ),
			'label_wachtwoord'  => array( 'nl' => 'Wachtwoord', 'fr' => 'Mot de passe' ),
			'btn_inloggen'      => array( 'nl' => 'Inloggen', 'fr' => 'Connexion' ),
			'login_fout'        => array(
				'nl' => 'Onjuiste gebruikersnaam of wachtwoord. Probeer het opnieuw.',
				'fr' => "Nom d'utilisateur ou mot de passe incorrect. Veuillez réessayer.",
			),
			'login_geblokkeerd' => array(
				'nl' => 'Te veel mislukte inlogpogingen. Probeer het over 15 minuten opnieuw.',
				'fr' => 'Trop de tentatives de connexion échouées. Réessayez dans 15 minutes.',
			),
			'account_titel'     => array( 'nl' => 'Account in behandeling', 'fr' => 'Compte en cours de validation' ),
			'account_tekst'     => array(
				'nl' => 'Je aanmelding is bij ons binnengekomen. We controleren even of je gegevens bij een bekend dealeraccount horen.',
				'fr' => 'Votre inscription nous est bien parvenue. Nous vérifions si vos données correspondent à un compte concessionnaire connu.',
			),
			'account_stappen'   => array(
				'nl' => 'Je hoeft hier niets voor te doen: zodra je account is goedgekeurd, krijg je daar automatisch een e-mail over. Doorgaans gebeurt dat binnen één werkdag.',
				'fr' => "Vous n'avez rien à faire : dès que votre compte sera approuvé, vous recevrez automatiquement un e-mail. Cela se fait généralement en un jour ouvrable.",
			),
			'account_duurt_lang' => array(
				'nl' => 'Duurt het langer, of heb je haast? Neem gerust even contact op.',
				'fr' => "Cela prend plus de temps, ou vous êtes pressé ? N'hésitez pas à nous contacter.",
			),

			// Garantieportaal
			// Garantie — formulieren
			'garantie_kies'              => array( 'nl' => '— Maak een keuze —', 'fr' => '— Faites un choix —' ),
			'garantie_annuleren'         => array( 'nl' => 'Annuleren', 'fr' => 'Annuler' ),
			// Alleen bij "machine aanmelden"; de claim heeft garantie_fotos_hint.
			'garantie_bijlagen_hint'     => array(
				'nl' => 'Bijvoorbeeld de factuur of afleverbon, en een foto van het typeplaatje. Daarmee kunnen we je aanmelding sneller beoordelen. JPG, PNG, WEBP, HEIC of PDF; maximaal 10 bestanden van 10 MB.',
				'fr' => 'Par exemple la facture ou le bon de livraison, et une photo de la plaque signalétique. Cela nous permet de traiter votre enregistrement plus rapidement. JPG, PNG, WEBP, HEIC ou PDF ; maximum 10 fichiers de 10 Mo.',
			),
			'garantie_veld_bijlagen'     => array( 'nl' => 'Factuur of afleverbon', 'fr' => 'Facture ou bon de livraison' ),
			/* translators: %s zijn de bestandsnamen die niet gelukt zijn. */
			'garantie_bijlagen_mislukt'  => array(
				'nl' => 'Deze bestanden konden we niet opslaan: %s. Je kunt ze alsnog meesturen via een bericht bij het ticket.',
				'fr' => "Nous n'avons pas pu enregistrer ces fichiers : %s. Vous pouvez encore les envoyer via un message sur le ticket.",
			),

			'garantie_ok_machine'        => array(
				'nl' => 'Je machine is aangemeld. We beoordelen de aanmelding; je kunt er alvast een claim op indienen.',
				'fr' => "Votre machine est enregistrée. Nous examinons l'enregistrement ; vous pouvez déjà introduire une demande.",
			),
			/* translators: %s is het ticketnummer. */
			'garantie_ok_claim'          => array(
				'nl' => 'Je claim is ingediend onder nummer %1$s. Stuur je onderdelen retour naar Stiens? Vermeld dan %1$s op de zending — de claim kan pas behandeld worden zodra het onderdeel binnen is. Hieronder zie je de stand van zaken.',
				'fr' => 'Votre demande a été introduite sous le numéro %1$s. Vous renvoyez des pièces à Stiens ? Mentionnez alors %1$s sur l’envoi — la demande ne peut être traitée qu’une fois la pièce reçue. Vous voyez l’état ci-dessous.',
			),
			/* translators: %s is het ticketnummer. */
			'garantie_ok_claim_onderdeel' => array(
				'nl' => 'Je claim is ingediend onder nummer %1$s. Stuur het onderdeel naar Stiens en vermeld %1$s op de zending — wij nemen de claim in behandeling zodra het onderdeel bij ons binnen is.',
				'fr' => 'Votre demande a été introduite sous le numéro %1$s. Renvoyez la pièce à Stiens en mentionnant %1$s sur l’envoi — nous traitons la demande dès que la pièce nous est parvenue.',
			),
			'garantie_dag_in_status'     => array( 'nl' => '1 dag op deze status', 'fr' => '1 jour à ce statut' ),
			/* translators: %d is een aantal dagen. */
			'garantie_dagen_in_status'   => array( 'nl' => '%d dagen op deze status', 'fr' => '%d jours à ce statut' ),
			'garantie_andere_claims'     => array( 'nl' => 'Eerdere claims', 'fr' => 'Demandes précédentes' ),
			'garantie_bijlage_toevoegen' => array( 'nl' => 'Foto of document nasturen', 'fr' => 'Envoyer une photo ou un document' ),
			'garantie_bijlage_meesturen' => array( 'nl' => 'Bestand meesturen', 'fr' => 'Joindre un fichier' ),
			/* translators: %s zijn de bestandsnamen. */
			'garantie_bericht_alleen_bijlagen' => array(
				'nl' => 'Bijlage(n) meegestuurd: %s',
				'fr' => 'Pièce(s) jointe(s) envoyée(s) : %s',
			),
			'garantie_ok_bericht'        => array(
				'nl' => 'Je bericht is verstuurd.',
				'fr' => 'Votre message a été envoyé.',
			),

			'garantie_fout_geen_toegang' => array(
				'nl' => 'Je hebt geen toegang tot het garantieportaal.',
				'fr' => "Vous n'avez pas accès au portail de garantie.",
			),
			/* translators: %s zijn de namen van de ontbrekende velden. */
			'garantie_fout_ontbreekt'    => array(
				'nl' => 'Vul eerst in: %s.',
				'fr' => 'Complétez d’abord : %s.',
			),
			'garantie_fout_merk'         => array(
				'nl' => 'Kies een merk uit de lijst.',
				'fr' => 'Choisissez une marque dans la liste.',
			),
			'garantie_fout_machine'      => array(
				'nl' => 'Kies de machine waar het om gaat.',
				'fr' => 'Choisissez la machine concernée.',
			),
			'garantie_fout_klacht'       => array(
				'nl' => 'Beschrijf kort wat er aan de hand is.',
				'fr' => 'Décrivez brièvement le problème.',
			),
			'garantie_fout_leeg_bericht' => array(
				'nl' => 'Typ eerst een bericht.',
				'fr' => "Saisissez d'abord un message.",
			),
			'garantie_fout_serienummer_bestaat' => array(
				'nl' => 'Dit serienummer is al aangemeld. Staat de machine niet in je lijst? Neem dan even contact op.',
				'fr' => 'Ce numéro de série est déjà enregistré. La machine ne figure pas dans votre liste ? Contactez-nous.',
			),
			'garantie_fout_algemeen'     => array(
				'nl' => 'Er ging iets mis bij het opslaan. Probeer het nog een keer.',
				'fr' => "Une erreur s'est produite lors de l'enregistrement. Réessayez.",
			),
			/* translators: %s is een datum. */
			'garantie_fout_verlopen'     => array(
				'nl' => 'De garantie op deze machine is verlopen op %s. Er kan geen claim meer op worden ingediend.',
				'fr' => 'La garantie de cette machine a expiré le %s. Aucune demande ne peut plus être introduite.',
			),
			'garantie_fout_machine_afgewezen' => array(
				'nl' => 'Deze machine is afgewezen voor garantie. Er kan geen claim op worden ingediend.',
				'fr' => 'Cette machine a été refusée pour la garantie. Aucune demande ne peut être introduite.',
			),
			'garantie_machine_niet_kiesbaar' => array(
				'nl' => 'Machines met verlopen garantie of een afgewezen aanmelding staan erbij, maar zijn niet te kiezen.',
				'fr' => 'Les machines dont la garantie a expiré ou dont l’enregistrement a été refusé figurent dans la liste, mais ne peuvent pas être choisies.',
			),
			'garantie_fout_datum_toekomst' => array(
				'nl' => 'De aankoopdatum ligt in de toekomst. Controleer de datum.',
				'fr' => 'La date d’achat se situe dans le futur. Vérifiez la date.',
			),
			'garantie_bestanden_opnieuw' => array(
				'nl' => 'Kies je bestanden opnieuw: die blijven na een foutmelding niet bewaard.',
				'fr' => 'Sélectionnez à nouveau vos fichiers : ils ne sont pas conservés après un message d’erreur.',
			),
			/* translators: %s is een maximale grootte, bijv. "100 MB". */
			'garantie_fout_te_groot'     => array(
				'nl' => 'Er is niets verstuurd: de bestanden waren samen groter dan %s. Stuur minder of kleinere bestanden mee.',
				'fr' => 'Rien n’a été envoyé : les fichiers dépassaient ensemble %s. Joignez moins de fichiers ou des fichiers plus petits.',
			),
			// Controle in de browser, vóór het versturen.
			/* translators: %d is het maximale aantal bestanden. */
			'garantie_js_te_veel'        => array(
				'nl' => 'Je kunt maximaal %d bestanden tegelijk meesturen.',
				'fr' => 'Vous pouvez joindre au maximum %d fichiers à la fois.',
			),
			/* translators: %1$s is een bestandsnaam, %2$s een maximale grootte. */
			'garantie_js_bestand_te_groot' => array(
				'nl' => '%1$s is groter dan %2$s. Kies een kleiner bestand.',
				'fr' => '%1$s dépasse %2$s. Choisissez un fichier plus petit.',
			),
			/* translators: %s is een maximale grootte. */
			'garantie_js_totaal_te_groot' => array(
				'nl' => 'De bestanden zijn samen groter dan %s. Stuur minder of kleinere bestanden mee.',
				'fr' => 'Les fichiers dépassent ensemble %s. Joignez moins de fichiers ou des fichiers plus petits.',
			),
			'garantie_bijlage_van_homburg' => array( 'nl' => 'Van Homburg', 'fr' => 'De Homburg' ),

			'garantie_storing_kop'     => array( 'nl' => 'Even niet beschikbaar.', 'fr' => 'Momentanément indisponible.' ),
			'garantie_storing_tekst'   => array(
				'nl' => 'We kunnen je garantiegegevens op dit moment niet ophalen. Probeer het over een paar minuten opnieuw; lukt het dan nog niet, neem dan even contact op.',
				'fr' => 'Nous ne pouvons pas récupérer vos données de garantie pour le moment. Réessayez dans quelques minutes ; si cela ne fonctionne toujours pas, contactez-nous.',
			),
			'garantie_machine_in_behandeling' => array(
				'nl' => 'Aanmelding wordt beoordeeld',
				'fr' => "L'enregistrement est en cours d'examen",
			),
			'garantie_machine_afgewezen'      => array(
				'nl' => 'Aanmelding afgewezen',
				'fr' => 'Enregistrement refusé',
			),
			'garantie_voorbeeld_kop'   => array( 'nl' => 'Voorbeeldweergave.', 'fr' => 'Aperçu.' ),
			'garantie_voorbeeld_tekst' => array(
				'nl' => 'De claims hieronder zijn verzonnen voorbeelden om het ontwerp te laten zien. Er is nog geen koppeling met de claimadministratie.',
				'fr' => "Les demandes ci-dessous sont des exemples fictifs destinés à illustrer la présentation. Il n'y a pas encore de lien avec l'administration des demandes.",
			),
			'garantie_mijn_claims'     => array( 'nl' => 'Mijn garantieclaims', 'fr' => 'Mes demandes de garantie' ),
			'garantie_btn_nieuw'       => array( 'nl' => 'Nieuwe claim indienen', 'fr' => 'Introduire une demande' ),
			'garantie_serienummer'     => array( 'nl' => 'Serienummer:', 'fr' => 'Numéro de série :' ),
			'garantie_ingediend_op'    => array( 'nl' => 'Ingediend op:', 'fr' => 'Introduite le :' ),
			'garantie_actie_nodig'     => array(
				'nl' => 'Wij hebben aanvullende informatie van je nodig om deze claim verder te kunnen behandelen.',
				'fr' => "Nous avons besoin d'informations complémentaires de votre part pour traiter cette demande.",
			),
			'garantie_nieuwe_claim'    => array( 'nl' => 'Nieuwe garantieclaim', 'fr' => 'Nouvelle demande de garantie' ),
			'garantie_formulier_intro' => array(
				'nl' => "Vul de gegevens van de machine en de klacht zo volledig mogelijk in. Foto's versnellen de behandeling aanzienlijk.",
				'fr' => 'Complétez les données de la machine et de la panne aussi précisément que possible. Des photos accélèrent considérablement le traitement.',
			),
			'garantie_nog_niet_actief' => array(
				'nl' => 'Dit formulier is nog niet in gebruik — het laat alleen zien welke gegevens er gevraagd gaan worden.',
				'fr' => "Ce formulaire n'est pas encore actif — il montre uniquement quelles données seront demandées.",
			),

			// Garantie — statussen. Dezelfde sleutels als HDP_Garantie::STATUSSEN
			// en straks als de statuskolom in de claimadministratie.
			'garantie_status_ingediend'      => array( 'nl' => 'Ingediend', 'fr' => 'Introduite' ),
			'garantie_status_in_behandeling' => array( 'nl' => 'In behandeling', 'fr' => 'En traitement' ),
			'garantie_status_info_nodig'     => array( 'nl' => 'Informatie nodig', 'fr' => 'Informations requises' ),
			'garantie_status_goedgekeurd'    => array( 'nl' => 'Goedgekeurd', 'fr' => 'Approuvée' ),
			'garantie_status_afgewezen'      => array( 'nl' => 'Afgewezen', 'fr' => 'Refusée' ),
			'garantie_status_bij_fabrikant'  => array( 'nl' => 'Bij de fabrikant', 'fr' => 'Chez le fabricant' ),
			'garantie_status_afgewezen_fabrikant' => array( 'nl' => 'Afgewezen door fabrikant', 'fr' => 'Refusée par le fabricant' ),

			// Garantie — fases, voor de stappen die nog moeten komen.
			'garantie_fase_ingediend'   => array( 'nl' => 'Indienen', 'fr' => 'Introduction' ),
			'garantie_fase_behandeling' => array( 'nl' => 'Behandeling', 'fr' => 'Traitement' ),
			'garantie_fase_besluit'     => array( 'nl' => 'Besluit', 'fr' => 'Décision' ),

			// Garantie — overzichtspagina (twee ingangen)
			'garantie_ingang_machine_titel' => array( 'nl' => 'Machine aanmelden', 'fr' => 'Enregistrer une machine' ),
			'garantie_ingang_machine_tekst' => array(
				'nl' => 'Meld een geleverde machine aan voor garantie. Daarna kun je er claims op indienen zonder gegevens over te typen.',
				'fr' => 'Enregistrez une machine livrée pour la garantie. Vous pourrez ensuite introduire des demandes sans retaper les données.',
			),
			'garantie_ingang_machine_knop'  => array( 'nl' => 'Machine aanmelden', 'fr' => 'Enregistrer une machine' ),
			'garantie_ingang_claim_titel'   => array( 'nl' => 'Garantieclaim indienen', 'fr' => 'Introduire une demande de garantie' ),
			'garantie_ingang_claim_tekst'   => array(
				'nl' => "Is er een onderdeel stuk? Kies de machine, beschrijf de klacht en stuur foto's mee.",
				'fr' => 'Une pièce est défectueuse ? Choisissez la machine, décrivez la panne et joignez des photos.',
			),
			'garantie_ingang_claim_knop'    => array( 'nl' => 'Claim indienen', 'fr' => 'Introduire une demande' ),

			'garantie_wacht_een'    => array( 'nl' => 'Eén claim wacht op jou', 'fr' => 'Une demande attend votre réponse' ),
			/* translators: %d is het aantal claims. */
			'garantie_wacht_meer'   => array( 'nl' => '%d claims wachten op jou', 'fr' => '%d demandes attendent votre réponse' ),
			'garantie_bekijken'     => array( 'nl' => 'Bekijken', 'fr' => 'Consulter' ),

			'garantie_zoek_label'          => array( 'nl' => 'Zoeken in claims en machines', 'fr' => 'Rechercher dans les demandes et les machines' ),
			'garantie_zoek_placeholder'    => array(
				'nl' => 'Zoek op machine, serienummer of claimnummer',
				'fr' => 'Rechercher par machine, numéro de série ou numéro de demande',
			),
			'garantie_zoek_geen_treffers'  => array(
				'nl' => 'Niets gevonden. Probeer een deel van de machinenaam of het serienummer.',
				'fr' => 'Aucun résultat. Essayez une partie du nom de la machine ou du numéro de série.',
			),
			'garantie_lopende_claims'      => array( 'nl' => 'Lopende claims', 'fr' => 'Demandes en cours' ),
			'garantie_geen_lopende_claims' => array( 'nl' => 'Je hebt op dit moment geen lopende claims.', 'fr' => "Vous n'avez actuellement aucune demande en cours." ),
			/* translators: %d is het totale aantal claims. */
			'garantie_toon_alle_claims'    => array( 'nl' => 'Alle claims tonen (%d)', 'fr' => 'Afficher toutes les demandes (%d)' ),

			'groet_ochtend'           => array( 'nl' => 'Goedemorgen', 'fr' => 'Bonjour' ),
			'groet_middag'            => array( 'nl' => 'Goedemiddag', 'fr' => 'Bonjour' ),
			'groet_avond'             => array( 'nl' => 'Goedenavond', 'fr' => 'Bonsoir' ),
			'nieuw_voor_u'            => array( 'nl' => 'Nieuw voor jou', 'fr' => 'Nouveau pour vous' ),
			'nieuw_alle_downloads'    => array( 'nl' => 'Alle downloads', 'fr' => 'Tous les téléchargements' ),
			'nieuw_nog_een'           => array( 'nl' => 'En nog één nieuw item', 'fr' => 'Et une autre nouveauté' ),
			/* translators: %d is een aantal documenten. */
			'nieuw_nog_meer'          => array( 'nl' => 'En nog %d nieuwe items', 'fr' => 'Et %d autres nouveautés' ),
			'home_zoek_plaatshouder'  => array(
				'nl' => 'Zoek een artikelnummer of omschrijving in de webshop…',
				'fr' => 'Recherchez un numéro d’article ou une description dans la boutique…',
			),
			'nieuw_melding_weg'       => array( 'nl' => 'Niet meer tonen', 'fr' => 'Ne plus afficher' ),
			'zijbalk_home'            => array( 'nl' => 'Home', 'fr' => 'Accueil' ),
			'zijbalk_bestellingen'    => array( 'nl' => 'Bestellingen', 'fr' => 'Commandes' ),
			'zijbalk_label'           => array( 'nl' => 'Onderdelen van het dealerportaal', 'fr' => 'Sections du portail revendeur' ),
			'zijbalk_groep_bestellen' => array( 'nl' => 'Bestellen', 'fr' => 'Commander' ),
			'zijbalk_groep_service'   => array( 'nl' => 'Service', 'fr' => 'Service' ),
			'zijbalk_groep_materiaal' => array( 'nl' => 'Materiaal', 'fr' => 'Documents' ),
			'home_zoek_label'         => array( 'nl' => 'Artikelnummer of zoekterm', 'fr' => 'Numéro d’article ou mot-clé' ),
			'home_zoek_knop'          => array( 'nl' => 'Zoek', 'fr' => 'Rechercher' ),
			'snelbestel_kop'          => array( 'nl' => 'Artikelnummers plakken', 'fr' => 'Coller des numéros d’article' ),
			'snelbestel_tekst'        => array( 'nl' => 'Een hele lijst in één keer in je winkelwagen.', 'fr' => 'Toute une liste dans le panier en une fois.' ),
			// Startpagina: snelle wegen onder het zoekveld.
			'home_snel_label'         => array( 'nl' => 'Snel naar', 'fr' => 'Accès rapide' ),
			'home_snel_favorieten'    => array( 'nl' => 'Mijn favorieten', 'fr' => 'Mes favoris' ),
			'home_snel_webshop'       => array( 'nl' => 'Hele webshop bekijken', 'fr' => 'Voir toute la boutique' ),
			// Startpagina: is het magazijn nu bereikbaar?
			/* translators: %s is een tijd, bijv. 17:00. */
			'magazijn_open'           => array( 'nl' => 'Magazijn nu bereikbaar · tot %s', 'fr' => 'Entrepôt joignable · jusqu’à %s' ),
			/* translators: %s is wanneer weer, bijv. "morgen vanaf 08:00". */
			'magazijn_dicht'          => array( 'nl' => 'Magazijn gesloten · weer bereikbaar %s', 'fr' => 'Entrepôt fermé · joignable %s' ),
			'magazijn_vandaag'        => array( 'nl' => 'vandaag vanaf %s', 'fr' => 'aujourd’hui dès %s' ),
			'magazijn_morgen'         => array( 'nl' => 'morgen vanaf %s', 'fr' => 'demain dès %s' ),
			'magazijn_maandag'        => array( 'nl' => 'maandag vanaf %s', 'fr' => 'lundi dès %s' ),
			// Startpagina: "Vandaag voor jou".
			'vandaag_kop'             => array( 'nl' => 'Vandaag voor jou', 'fr' => 'Pour vous aujourd’hui' ),
			'tegel_garantie'          => array( 'nl' => 'Garantie', 'fr' => 'Garantie' ),
			'tegel_garantie_wacht_een' => array( 'nl' => 'Claim wacht op jou', 'fr' => 'Une demande attend votre réponse' ),
			'tegel_garantie_wacht_meer' => array( 'nl' => 'Claims wachten op jou', 'fr' => 'Des demandes attendent votre réponse' ),
			'tegel_garantie_niets'    => array( 'nl' => 'Niets dat op jou wacht', 'fr' => 'Rien ne vous attend' ),
			/* translators: %d is een aantal claims. */
			'tegel_garantie_loopt'    => array( 'nl' => '%d lopende claims', 'fr' => '%d demandes en cours' ),
			'tegel_garantie_naar'     => array( 'nl' => 'Naar garantie', 'fr' => 'Vers la garantie' ),
			'tegel_bestellingen'      => array( 'nl' => 'Bestellingen', 'fr' => 'Commandes' ),
			'tegel_bestellingen_loopt' => array( 'nl' => 'In behandeling', 'fr' => 'En cours de traitement' ),
			'tegel_bestellingen_geen' => array( 'nl' => 'Niets in behandeling', 'fr' => 'Rien en cours' ),
			/* translators: %1$s is een ordernummer, %2$s een datum. */
			'tegel_bestellingen_laatste' => array( 'nl' => 'Laatste: #%1$s, %2$s', 'fr' => 'Dernière : n° %1$s, %2$s' ),
			'tegel_bestellingen_alle' => array( 'nl' => 'Alle bestellingen', 'fr' => 'Toutes les commandes' ),
			'tegel_downloads'         => array( 'nl' => 'Downloads', 'fr' => 'Téléchargements' ),
			'tegel_downloads_nieuw'   => array( 'nl' => 'Nieuwe documenten', 'fr' => 'Nouveaux documents' ),
			'tegel_downloads_geen'    => array( 'nl' => 'Geen nieuwe documenten', 'fr' => 'Aucun nouveau document' ),
			'tegel_downloads_naar'    => array( 'nl' => 'Naar downloads', 'fr' => 'Vers les téléchargements' ),
			'tegel_winkelwagen'       => array( 'nl' => 'Winkelwagen', 'fr' => 'Panier' ),
			'tegel_winkelwagen_vol'   => array( 'nl' => 'Nog niet besteld', 'fr' => 'Pas encore commandé' ),
			'tegel_winkelwagen_leeg'  => array( 'nl' => 'Je winkelwagen is leeg', 'fr' => 'Votre panier est vide' ),
			'tegel_winkelwagen_afronden' => array( 'nl' => 'Bestelling afronden', 'fr' => 'Finaliser la commande' ),
			'tegel_winkelwagen_naar_webshop' => array( 'nl' => 'Naar de webshop', 'fr' => 'Vers la boutique' ),
			'nieuw_niets'             => array( 'nl' => 'Er staat niets nieuws voor je klaar.', 'fr' => 'Rien de nouveau ne vous attend.' ),
			'berichten_titel'         => array( 'nl' => 'Berichten van Homburg', 'fr' => 'Messages de Homburg' ),
			/* translators: %s is de titel van het bericht. */
			'bericht_wegklikken'      => array(
				'nl' => 'Bericht "%s" wegklikken',
				'fr' => 'Masquer le message "%s"',
			),
			'garantie_retour_onderweg'  => array( 'nl' => 'Onderdeel onderweg', 'fr' => 'Pièce en route' ),
			/* translators: %s is het ticketnummer. */
			'garantie_retour_vermeld'   => array(
				'nl' => 'Vermeld %s op de zending',
				'fr' => 'Mentionnez %s sur l\'envoi',
			),
			'garantie_retour_ontvangen' => array( 'nl' => 'Onderdeel ontvangen', 'fr' => 'Pièce reçue' ),
			'garantie_behandeling_na_ontvangst' => array(
				'nl' => 'Start zodra het onderdeel binnen is',
				'fr' => 'Débute dès réception de la pièce',
			),
			'garantie_retour_kop'       => array(
				'nl' => 'Stuur het onderdeel naar Stiens',
				'fr' => 'Renvoyez la pièce à Stiens',
			),
			'garantie_retour_uitleg'    => array(
				'nl' => 'Wij beoordelen het onderdeel zelf; op een foto is meestal niet te zien wat er mis is. Daarom nemen wij een onderdelenclaim pas in behandeling zodra het onderdeel bij ons in Stiens binnen is. Vermeld het ticketnummer op de zending — dat nummer krijg je direct nadat je deze claim hebt ingediend.',
				'fr' => 'Nous évaluons la pièce elle-même ; une photo ne montre généralement pas ce qui ne va pas. C’est pourquoi nous ne traitons une demande de garantie sur pièces qu’à partir du moment où la pièce nous est parvenue à Stiens. Mentionnez le numéro de ticket sur l’envoi — vous recevez ce numéro dès que vous avez introduit cette demande.',
			),
			/* translators: %s is het ticketnummer. */
			'garantie_retour_uitleg_nummer' => array(
				'nl' => 'Wij nemen deze claim in behandeling zodra het onderdeel bij ons in Stiens binnen is. Vermeld %s op de zending, dan weten wij meteen waar het pakket bij hoort.',
				'fr' => 'Nous traitons cette demande dès que la pièce nous est parvenue à Stiens. Mentionnez %s sur l’envoi, afin que nous sachions immédiatement à quoi se rapporte le colis.',
			),
			'garantie_rail_claims'    => array( 'nl' => 'Claims', 'fr' => 'Demandes' ),
			'garantie_rail_alles'     => array( 'nl' => 'Alles', 'fr' => 'Tout' ),
			'garantie_rail_wacht'     => array( 'nl' => 'Wacht op jou', 'fr' => 'En attente de vous' ),
			'garantie_rail_loopt'     => array( 'nl' => 'In behandeling', 'fr' => 'En cours' ),
			'garantie_rail_klaar'     => array( 'nl' => 'Afgehandeld', 'fr' => 'Traité' ),
			'garantie_rail_aangemeld' => array( 'nl' => 'Aangemeld', 'fr' => 'Enregistrées' ),
			'garantie_rail_toon_alles' => array( 'nl' => 'Toon alle claims', 'fr' => 'Afficher toutes les demandes' ),
			'garantie_geen_claims_totaal' => array(
				'nl' => 'Je hebt nog geen claims ingediend.',
				'fr' => "Vous n'avez encore introduit aucune demande.",
			),
			'garantie_geen_claims_filter' => array(
				'nl' => 'Geen claims in deze groep.',
				'fr' => 'Aucune demande dans ce groupe.',
			),
			'garantie_kies_een_claim' => array(
				'nl' => 'Kies links een claim om hem hier te lezen.',
				'fr' => 'Sélectionnez une demande à gauche pour la lire ici.',
			),
			'garantie_nog_niets_tekst' => array(
				'nl' => 'Een claim op bestelde onderdelen kun je meteen indienen. Voor een claim op een machine meld je eerst die machine aan.',
				'fr' => "Une demande pour des pièces commandées peut être introduite immédiatement. Pour une demande sur une machine, enregistrez d'abord cette machine.",
			),
			'garantie_nog_niets_met_machines' => array(
				'nl' => 'Zodra je een claim indient, lees je hem hier terug.',
				'fr' => 'Dès que vous introduisez une demande, vous la retrouvez ici.',
			),
			'garantie_open_volledig'  => array(
				'nl' => 'Open het volledige ticket',
				'fr' => 'Ouvrir le ticket complet',
			),
			'garantie_mijn_machines'  => array( 'nl' => 'Mijn machines', 'fr' => 'Mes machines' ),
			'garantie_geen_machines'  => array(
				'nl' => 'Je hebt nog geen machines aangemeld. Meld een machine aan om er claims op in te kunnen dienen.',
				'fr' => "Vous n'avez pas encore enregistré de machine. Enregistrez-en une pour pouvoir introduire des demandes.",
			),
			'garantie_machine_lopend_een'  => array( 'nl' => '1 lopende claim', 'fr' => '1 demande en cours' ),
			/* translators: %d is het aantal lopende claims. */
			'garantie_machine_lopend'      => array( 'nl' => '%d lopende claims', 'fr' => '%d demandes en cours' ),
			'garantie_machine_geen_lopend' => array( 'nl' => 'geen lopende claims', 'fr' => 'aucune demande en cours' ),
			/* translators: %s is een datum. */
			'garantie_tot_en_met'   => array( 'nl' => 'Garantie t/m %s', 'fr' => "Garantie jusqu'au %s" ),
			/* translators: %s is een datum. */
			'garantie_verlopen_op'  => array( 'nl' => 'Garantie verlopen %s', 'fr' => 'Garantie expirée le %s' ),
			/* translators: %d is een aantal maanden. */
			'garantie_nog_maanden'  => array( 'nl' => 'nog %d maanden', 'fr' => 'encore %d mois' ),

			'garantie_terug_naar_garantie' => array( 'nl' => 'Terug naar garantie', 'fr' => 'Retour à la garantie' ),
			'garantie_kies_machine'        => array( 'nl' => 'Om welke machine gaat het?', 'fr' => "De quelle machine s'agit-il ?" ),
			'garantie_kies_machine_hint'   => array(
				'nl' => 'Staat de machine er niet bij?',
				'fr' => 'La machine ne figure pas dans la liste ?',
			),
			'garantie_veld_klant'          => array( 'nl' => 'Eindklant', 'fr' => 'Client final' ),
			'garantie_veld_merk'           => array( 'nl' => 'Merk', 'fr' => 'Marque' ),

			// Garantie — ticketscherm
			'garantie_ticket'            => array( 'nl' => 'Garantieticket', 'fr' => 'Ticket de garantie' ),
			'garantie_route'             => array( 'nl' => 'Route', 'fr' => 'Parcours' ),
			'garantie_gesprek'           => array( 'nl' => 'Berichten', 'fr' => 'Messages' ),
			'garantie_gesprek_leeg'      => array( 'nl' => 'Nog geen berichten bij dit ticket.', 'fr' => 'Pas encore de messages pour ce ticket.' ),
			'garantie_claimgegevens'     => array( 'nl' => 'Claimgegevens', 'fr' => 'Données de la demande' ),
			'garantie_bijlagen'          => array( 'nl' => 'Bijlagen', 'fr' => 'Pièces jointes' ),
			'garantie_geen_bijlagen'     => array( 'nl' => 'Geen bijlagen bij dit ticket.', 'fr' => 'Aucune pièce jointe pour ce ticket.' ),
			'garantie_van_homburg'       => array( 'nl' => 'Homburg', 'fr' => 'Homburg' ),
			'garantie_van_dealer'        => array( 'nl' => 'Jij', 'fr' => 'Vous' ),
			'garantie_bij_status'        => array( 'nl' => 'Bij status:', 'fr' => 'Pour le statut :' ),
			'garantie_antwoord_label'    => array( 'nl' => 'Reageren op dit ticket', 'fr' => 'Répondre à ce ticket' ),
			'garantie_antwoord_hint'     => array(
				'nl' => 'Stel een vraag of stuur de gevraagde informatie door.',
				'fr' => 'Posez une question ou transmettez les informations demandées.',
			),
			'garantie_antwoord_versturen' => array( 'nl' => 'Versturen', 'fr' => 'Envoyer' ),
			'garantie_terug_naar_overzicht' => array( 'nl' => 'Terug naar mijn claims', 'fr' => 'Retour à mes demandes' ),
			'garantie_ticket_onbekend'   => array(
				'nl' => 'Dit ticket bestaat niet, of hoort niet bij je account.',
				'fr' => "Ce ticket n'existe pas ou n'appartient pas à votre compte.",
			),
			'garantie_ligt_bij_u'        => array(
				'nl' => 'Dit ticket wacht op jou.',
				'fr' => 'Ce ticket attend votre réponse.',
			),
			'garantie_ligt_bij_homburg'  => array(
				'nl' => 'Dit ticket ligt bij Homburg. Je hoeft nu niets te doen.',
				'fr' => "Ce ticket est chez Homburg. Vous n'avez rien à faire pour le moment.",
			),
			// Het besluit bovenaan een afgeronde claim (zie render_besluit()).
			'garantie_besluit_kop'       => array( 'nl' => 'Besluit', 'fr' => 'Décision' ),
			'garantie_besluit_vragen'    => array(
				'nl' => 'Vragen over dit besluit? Stuur een bericht bij dit ticket.',
				'fr' => 'Des questions sur cette décision ? Envoyez un message sur ce ticket.',
			),

			// Garantie — formuliervelden. Sleutels komen overeen met HDP_Garantie::velden().
			'garantie_veld_machine'      => array( 'nl' => 'Machine', 'fr' => 'Machine' ),
			'garantie_veld_serienummer'  => array( 'nl' => 'Serienummer', 'fr' => 'Numéro de série' ),
			'garantie_veld_aankoopdatum' => array( 'nl' => 'Aankoopdatum', 'fr' => "Date d'achat" ),
			'garantie_veld_hectares'     => array( 'nl' => 'Aantal hectares', 'fr' => "Nombre d'hectares" ),
			// "Wat is er aan de hand?" suggereert dat je meldt tijdens het
			// probleem. Een claim komt juist ná de reparatie, dus wat hier
			// hoort te staan is het hele verhaal: wat er gebeurde, welke
			// diagnose, en wat er vervangen is.
			'garantie_veld_klacht'       => array( 'nl' => 'Te claimen defect', 'fr' => 'Défaut à déclarer' ),
			'garantie_veld_klacht_hint'  => array(
				'nl' => 'Beschrijf wat er gebeurd is, welke diagnose je hebt gesteld en wat je hebt vervangen. Bijvoorbeeld: storing wielsensor, diagnose gesteld, sensor vervangen.',
				'fr' => "Décrivez ce qui s'est passé, le diagnostic posé et ce que vous avez remplacé. Par exemple : panne du capteur de roue, diagnostic posé, capteur remplacé.",
			),
			'garantie_veld_onderdelen'   => array( 'nl' => 'Te claimen onderdelen', 'fr' => 'Pièces à déclarer' ),
			'garantie_veld_onderdelen_hint' => array(
				'nl' => 'Eén onderdeel per regel, met onderdeelnummer, aantal en bedrag. Vermeld erbij of het van een Homburg-factuur komt; zo niet, stuur die factuur mee als bijlage.',
				'fr' => "Une pièce par ligne, avec le numéro, la quantité et le montant. Indiquez s'il s'agit d'une facture Homburg ; sinon, joignez cette facture.",
			),
			// Garantie — keuze vooraf en onderdelenclaim
			'garantie_kies_soort_titel' => array( 'nl' => 'Waar gaat je claim over?', 'fr' => 'Sur quoi porte votre demande ?' ),
			'garantie_kies_soort_tekst' => array(
				'nl' => 'De twee soorten vragen om verschillende gegevens, dus kies je eerst waar het om gaat.',
				'fr' => 'Les deux types demandent des informations différentes ; choisissez donc d’abord de quoi il s’agit.',
			),
			'garantie_soort_machine_titel' => array( 'nl' => 'Een machine', 'fr' => 'Une machine' ),
			'garantie_soort_machine_tekst' => array(
				'nl' => 'Een defect aan een machine die je bij ons hebt aangemeld.',
				'fr' => 'Un défaut sur une machine que vous avez enregistrée chez nous.',
			),
			'garantie_soort_machine_knop'  => array( 'nl' => 'Claim op een machine', 'fr' => 'Demande pour une machine' ),
			'garantie_soort_onderdeel_titel' => array( 'nl' => 'Bestelde onderdelen', 'fr' => 'Pièces commandées' ),
			'garantie_soort_onderdeel_tekst' => array(
				'nl' => 'Onderdelen die defect of beschadigd aankwamen, verkeerd geleverd zijn, verkeerd besteld of niet aangekomen.',
				'fr' => 'Des pièces arrivées défectueuses ou endommagées, mal livrées, mal commandées ou non reçues.',
			),
			'garantie_soort_onderdeel_knop'  => array( 'nl' => 'Claim op onderdelen', 'fr' => 'Demande pour des pièces' ),

			'garantie_veld_onderdeel_probleem' => array( 'nl' => 'Wat is er met de onderdelen?', 'fr' => 'Que se passe-t-il avec les pièces ?' ),
			'garantie_probleem_defect'                => array( 'nl' => 'Defect', 'fr' => 'Défectueux' ),
			'garantie_probleem_beschadigd_aangekomen' => array( 'nl' => 'Beschadigd aangekomen', 'fr' => 'Arrivé endommagé' ),
			'garantie_probleem_onjuist_geleverd'      => array( 'nl' => 'Onjuist geleverd', 'fr' => 'Mal livré' ),
			'garantie_probleem_onjuist_besteld'       => array( 'nl' => 'Onjuist besteld', 'fr' => 'Mal commandé' ),
			'garantie_probleem_niet_ontvangen'        => array( 'nl' => 'Niet ontvangen', 'fr' => 'Non reçu' ),

			'garantie_veld_klacht_onderdeel' => array( 'nl' => 'Toelichting', 'fr' => 'Précisions' ),
			'garantie_veld_factuurnummer'      => array( 'nl' => 'Homburg-factuurnummer', 'fr' => 'Numéro de facture Homburg' ),
			'garantie_veld_factuurnummer_hint' => array(
				'nl' => 'Het factuur- of ordernummer waarop deze onderdelen zijn geleverd.',
				'fr' => 'Le numéro de facture ou de commande sur lequel ces pièces ont été livrées.',
			),

			'garantie_veld_regels'           => array( 'nl' => 'Te claimen onderdelen', 'fr' => 'Pièces à déclarer' ),
			'garantie_regels_hint'           => array(
				'nl' => 'Vul alleen de regels in die je claimt. Komt een onderdeel van een andere leverancier, stuur die factuur dan mee als bijlage.',
				'fr' => 'Ne remplissez que les lignes que vous déclarez. Si une pièce provient d’un autre fournisseur, joignez cette facture.',
			),
			'garantie_regel_nummer'          => array( 'nl' => 'Onderdeelnummer', 'fr' => 'Numéro de pièce' ),
			'garantie_regel_aantal'          => array( 'nl' => 'Aantal', 'fr' => 'Quantité' ),
			'garantie_regel_bedrag'          => array( 'nl' => 'Bedrag', 'fr' => 'Montant' ),
			'garantie_regel_herkomst'        => array( 'nl' => 'Factuur', 'fr' => 'Facture' ),
			'garantie_regel_homburg'         => array( 'nl' => 'Homburg', 'fr' => 'Homburg' ),
			'garantie_regel_derden'          => array( 'nl' => 'Andere leverancier', 'fr' => 'Autre fournisseur' ),
			'garantie_regel_toevoegen'       => array( 'nl' => 'Regel toevoegen', 'fr' => 'Ajouter une ligne' ),
			/* translators: %d is het regelnummer. */
			'garantie_regel_nummer_van'      => array( 'nl' => 'Onderdeelnummer regel %d', 'fr' => 'Numéro de pièce ligne %d' ),
			/* translators: %d is het regelnummer. */
			'garantie_regel_aantal_van'      => array( 'nl' => 'Aantal regel %d', 'fr' => 'Quantité ligne %d' ),
			/* translators: %d is het regelnummer. */
			'garantie_regel_bedrag_van'      => array( 'nl' => 'Bedrag regel %d', 'fr' => 'Montant ligne %d' ),
			/* translators: %d is het regelnummer. */
			'garantie_regel_herkomst_van'    => array( 'nl' => 'Factuur regel %d', 'fr' => 'Facture ligne %d' ),

			'garantie_veld_te_claimen_tijd'      => array( 'nl' => 'Te claimen tijd (uren)', 'fr' => 'Temps à déclarer (heures)' ),
			'garantie_veld_te_claimen_tijd_hint' => array(
				'nl' => 'Alleen de tijd voor het opsporen en repareren van het defect. Reistijd telt niet mee.',
				'fr' => 'Uniquement le temps consacré au diagnostic et à la réparation. Le temps de déplacement ne compte pas.',
			),
			'garantie_veld_klantreferentie'      => array( 'nl' => 'Referentie van de klant', 'fr' => 'Référence du client' ),
			'garantie_veld_klantreferentie_hint' => array(
				'nl' => 'Bijvoorbeeld je eigen werkordernummer, zodat je deze claim later terugvindt.',
				'fr' => 'Par exemple votre propre numéro de bon de travail, pour retrouver cette demande plus tard.',
			),
			'garantie_veld_opmerkingen'          => array( 'nl' => 'Opmerkingen', 'fr' => 'Remarques' ),
			'garantie_veld_opmerkingen_hint'     => array(
				'nl' => 'Alles wat wij verder moeten weten. Bijvoorbeeld: hierover gemaild met Gerard op 11-5.',
				'fr' => 'Tout ce que nous devons savoir en plus. Par exemple : courriel à Gerard le 11-5.',
			),
			'garantie_veld_onderdeel_retour'      => array( 'nl' => 'Ik stuur onderdelen retour naar Stiens', 'fr' => 'Je renvoie des pièces à Stiens' ),
			'garantie_veld_onderdeel_retour_hint' => array(
				'nl' => 'Vermeld het ticketnummer op de zending. De claim kan pas behandeld worden zodra het onderdeel binnen is.',
				'fr' => 'Mentionnez le numéro de ticket sur l’envoi. La demande ne peut être traitée qu’une fois la pièce reçue.',
			),

			'garantie_machine_zoek'        => array(
				'nl' => 'Zoek op machine, serienummer of klant…',
				'fr' => 'Rechercher par machine, numéro de série ou client…',
			),
			/* translators: %d is een aantal machines. */
			'garantie_machines_meer'       => array( 'nl' => 'Toon de overige %d machines', 'fr' => 'Afficher les %d autres machines' ),
			/* translators: %s is een bedrag. */
			'garantie_regels_totaal'       => array( 'nl' => 'Totaal te claimen: %s', 'fr' => 'Total à déclarer : %s' ),
			/* translators: %s is een aantal uren. */
			'garantie_regels_totaal_uren'  => array( 'nl' => '· %s uur', 'fr' => '· %s heures' ),
			'garantie_regel_uren'          => array( 'nl' => 'Uren', 'fr' => 'Heures' ),
			/* translators: %d is het regelnummer. */
			'garantie_regel_uren_van'      => array( 'nl' => 'Uren regel %d', 'fr' => 'Heures ligne %d' ),
			'garantie_regels_hint_uren'    => array(
				'nl' => 'Vul per onderdeel in hoeveel uur eraan is gewerkt, exclusief reistijd. Is er aan een onderdeel geen tijd besteed, vul dan 0 in.',
				'fr' => 'Indiquez pour chaque pièce le nombre d’heures de travail, hors temps de trajet. Si aucune heure n’a été consacrée à une pièce, indiquez 0.',
			),
			'garantie_fout_uren_per_regel' => array(
				'nl' => 'Vul bij elk onderdeel de uren in. Is er aan een onderdeel geen tijd besteed, vul dan 0 in.',
				'fr' => 'Indiquez les heures pour chaque pièce. Si aucune heure n’a été consacrée à une pièce, indiquez 0.',
			),
			'garantie_fout_geen_regels'    => array(
				'nl' => 'Vul minstens één te claimen onderdeel in.',
				'fr' => 'Indiquez au moins une pièce à déclarer.',
			),
			'garantie_fout_factuur_nodig'  => array(
				'nl' => 'Er staat een onderdeel van een andere leverancier bij. Stuur die factuur mee als bijlage.',
				'fr' => 'Une pièce provient d’un autre fournisseur. Joignez cette facture.',
			),
			'garantie_fout_probleem'       => array(
				'nl' => 'Kies wat er met de onderdelen aan de hand is.',
				'fr' => 'Choisissez ce qui ne va pas avec les pièces.',
			),

			'garantie_veld_fotos'        => array( 'nl' => "Foto's", 'fr' => 'Photos' ),
			// Eigen hint voor de claim: op het machineformulier slaat "foto
			// van het defect" nergens op, daar is nog niets stuk.
			'garantie_fotos_hint'        => array(
				'nl' => 'Stuur in elk geval een foto van het typeplaatje met het serienummer mee, en een foto van het defect zelf. JPG, PNG, WEBP, HEIC of PDF; maximaal 10 bestanden van 10 MB.',
				'fr' => 'Joignez en tout cas une photo de la plaque signalétique avec le numéro de série et une photo du défaut. JPG, PNG, WEBP, HEIC ou PDF ; maximum 10 fichiers de 10 Mo.',
			),

			// Nieuw-markering
			'badge_nieuw'       => array( 'nl' => 'Nieuw', 'fr' => 'Nouveau' ),
			'nieuw_melding_een' => array(
				'nl' => 'Er staat 1 nieuw document voor je klaar',
				'fr' => 'Un nouveau document vous attend',
			),
			/* translators: %d is het aantal nieuwe documenten. */
			'nieuw_melding_meer' => array(
				'nl' => 'Er staan %d nieuwe documenten voor je klaar',
				'fr' => '%d nouveaux documents vous attendent',
			),
			'btn_uitloggen'     => array( 'nl' => 'Uitloggen', 'fr' => 'Déconnexion' ),
			'wachtwoord_vergeten' => array( 'nl' => 'Wachtwoord vergeten?', 'fr' => 'Mot de passe oublié ?' ),
			'onthoud_mij'       => array( 'nl' => 'Onthoud mij op dit apparaat', 'fr' => 'Se souvenir de moi sur cet appareil' ),

			// Accountinstellingen
			'btn_instellingen'  => array( 'nl' => 'Instellingen', 'fr' => 'Paramètres' ),
			'sluiten'           => array( 'nl' => 'Sluiten', 'fr' => 'Fermer' ),
			'instellingen_titel' => array( 'nl' => 'Accountinstellingen', 'fr' => 'Paramètres du compte' ),
			'instellingen_intro' => array(
				'nl' => 'Wijzig hier je weergavenaam en wachtwoord voor het dealerportaal.',
				'fr' => 'Modifiez ici votre nom d\'affichage et votre mot de passe pour le portail concessionnaire.',
			),
			'label_weergavenaam' => array( 'nl' => 'Weergavenaam', 'fr' => "Nom d'affichage" ),
			'instellingen_wachtwoord_titel' => array(
				'nl' => 'Wachtwoord wijzigen (optioneel)',
				'fr' => 'Modifier le mot de passe (facultatif)',
			),
			'label_huidig_wachtwoord' => array( 'nl' => 'Huidig wachtwoord', 'fr' => 'Mot de passe actuel' ),
			'label_nieuw_wachtwoord'  => array( 'nl' => 'Nieuw wachtwoord', 'fr' => 'Nouveau mot de passe' ),
			'label_nieuw_wachtwoord_herhaal' => array( 'nl' => 'Herhaal nieuw wachtwoord', 'fr' => 'Répétez le nouveau mot de passe' ),
			'btn_opslaan'       => array( 'nl' => 'Opslaan', 'fr' => 'Enregistrer' ),
			'instellingen_opgeslagen' => array( 'nl' => 'Je wijzigingen zijn opgeslagen.', 'fr' => 'Vos modifications ont été enregistrées.' ),
			'instellingen_fout_naam' => array(
				'nl' => 'Vul een weergavenaam in.',
				'fr' => "Veuillez saisir un nom d'affichage.",
			),
			'instellingen_fout_huidig' => array(
				'nl' => 'Het huidige wachtwoord is onjuist.',
				'fr' => 'Le mot de passe actuel est incorrect.',
			),
			'instellingen_fout_kort' => array(
				'nl' => 'Het nieuwe wachtwoord moet minimaal 8 tekens bevatten.',
				'fr' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.',
			),
			'instellingen_fout_mismatch' => array(
				'nl' => 'De wachtwoorden komen niet overeen.',
				'fr' => 'Les mots de passe ne correspondent pas.',
			),
			'instellingen_email_titel' => array( 'nl' => 'E-mailadres wijzigen', 'fr' => "Modifier l'adresse e-mail" ),
			'instellingen_huidig_email_label' => array( 'nl' => 'Huidig e-mailadres:', 'fr' => 'Adresse e-mail actuelle :' ),
			'label_nieuw_email' => array( 'nl' => 'Nieuw e-mailadres', 'fr' => 'Nouvelle adresse e-mail' ),
			'btn_email_versturen' => array( 'nl' => 'Bevestigingslink versturen', 'fr' => 'Envoyer le lien de confirmation' ),
			'btn_email_annuleren' => array( 'nl' => 'Annuleren', 'fr' => 'Annuler' ),
			'instellingen_email_in_afwachting' => array(
				'nl' => 'Er staat een wijziging naar %s klaar, in afwachting van bevestiging.',
				'fr' => 'Une modification vers %s est en attente de confirmation.',
			),
			'instellingen_email_verzonden' => array(
				'nl' => 'We hebben een bevestigingslink gestuurd naar het nieuwe e-mailadres. Klik op de link in die e-mail om de wijziging te voltooien.',
				'fr' => 'Nous avons envoyé un lien de confirmation à la nouvelle adresse e-mail. Cliquez sur le lien dans cet e-mail pour finaliser la modification.',
			),
			'instellingen_email_bevestigd' => array( 'nl' => 'Je e-mailadres is gewijzigd.', 'fr' => 'Votre adresse e-mail a été modifiée.' ),
			'instellingen_email_geannuleerd' => array( 'nl' => 'De wijziging is geannuleerd.', 'fr' => 'La modification a été annulée.' ),
			'instellingen_fout_email_ongeldig' => array(
				'nl' => 'Vul een geldig e-mailadres in.',
				'fr' => 'Veuillez saisir une adresse e-mail valide.',
			),
			'instellingen_fout_email_zelfde' => array(
				'nl' => 'Dit is al je huidige e-mailadres.',
				'fr' => 'Il s\'agit déjà de votre adresse e-mail actuelle.',
			),
			'instellingen_fout_email_in_gebruik' => array(
				'nl' => 'Dit e-mailadres is al bij een ander account in gebruik.',
				'fr' => 'Cette adresse e-mail est déjà utilisée par un autre compte.',
			),
			'instellingen_fout_email_link' => array(
				'nl' => 'Deze bevestigingslink is ongeldig of verlopen.',
				'fr' => 'Ce lien de confirmation est invalide ou a expiré.',
			),
			'instellingen_fout_email_te_veel_pogingen' => array(
				'nl' => 'Je hebt te vaak een e-mailwijziging aangevraagd. Probeer het over een uur opnieuw.',
				'fr' => "Vous avez demandé trop souvent une modification d'e-mail. Réessayez dans une heure.",
			),
			'email_wijzig_onderwerp' => array(
				'nl' => 'Bevestig je nieuwe e-mailadres — Dealerportaal Homburg',
				'fr' => 'Confirmez votre nouvelle adresse e-mail — Portail concessionnaire Homburg',
			),
			'email_wijzig_body' => array(
				'nl' => "Beste dealer,\n\nJe hebt een wijziging van je e-mailadres aangevraagd voor het Homburg-dealerportaal. Klik op onderstaande link om deze wijziging te bevestigen:\n\n%s\n\nHeb je dit niet zelf aangevraagd? Dan kun je deze e-mail negeren.\n\nMet vriendelijke groet,\nHomburg Machinehandel",
				'fr' => "Cher/Chère revendeur/revendeuse,\n\nVous avez demandé une modification de votre adresse e-mail pour le portail concessionnaire Homburg. Cliquez sur le lien ci-dessous pour confirmer cette modification :\n\n%s\n\nVous n'êtes pas à l'origine de cette demande ? Vous pouvez ignorer cet e-mail.\n\nCordialement,\nHomburg Machinehandel",
			),

			// Portaal
			'welkom_prefix'     => array( 'nl' => 'Welkom,', 'fr' => 'Bienvenue,' ),
			'geautoriseerd_voor' => array( 'nl' => 'Geautoriseerd voor:', 'fr' => 'Autorisé pour :' ),
			'nog_niet_geconfigureerd' => array( 'nl' => 'Nog niet geconfigureerd', 'fr' => 'Pas encore configuré' ),
			'herbestellen_label' => array( 'nl' => 'Snel herbestellen', 'fr' => 'Recommander rapidement' ),
			'herbestellen_knop'  => array( 'nl' => 'Opnieuw bestellen', 'fr' => 'Commander à nouveau' ),
			'bekijk_alle_bestellingen' => array( 'nl' => 'Bekijk alle bestellingen', 'fr' => 'Voir toutes les commandes' ),

			// Bestelgeschiedenis
			'bestel_geen_webshop' => array(
				'nl' => 'De webshopkoppeling is nog niet actief.',
				'fr' => "Le lien vers la boutique en ligne n'est pas encore actif.",
			),
			'bestel_geen_bestellingen' => array(
				'nl' => 'Je hebt nog geen bestellingen geplaatst.',
				'fr' => "Vous n'avez pas encore passé de commande.",
			),
			'bestel_naar_webshop' => array( 'nl' => 'Naar de webshop', 'fr' => 'Vers la boutique en ligne' ),
			'bestel_bekijken'   => array( 'nl' => 'Bekijk bestelling', 'fr' => 'Voir la commande' ),
			'bestel_vorige'     => array( 'nl' => 'Vorige', 'fr' => 'Précédent' ),
			'bestel_volgende'   => array( 'nl' => 'Volgende', 'fr' => 'Suivant' ),
			'bestel_pagina_van' => array( 'nl' => 'Pagina %1$s van %2$s', 'fr' => 'Page %1$s sur %2$s' ),
			'bestel_zoek_placeholder' => array( 'nl' => 'Zoek op ordernummer of naam…', 'fr' => 'Rechercher par numéro de commande ou nom…' ),
			'bestel_filter_status_label' => array( 'nl' => 'Status', 'fr' => 'Statut' ),
			'bestel_alle_statussen' => array( 'nl' => 'Alle statussen', 'fr' => 'Tous les statuts' ),
			/* translators: %s is de eigen referentie van de dealer. */
			'bestel_referentie'     => array( 'nl' => 'Ref. %s', 'fr' => 'Réf. %s' ),
			// Webshop: winkelwagen, afrekenen en bestelling (thema, includes/woocommerce/bestellen.php).
			'wc_artikelnummer'      => array( 'nl' => 'Artikelnummer', 'fr' => 'Référence' ),
			'wc_referentie'         => array( 'nl' => 'Jouw referentie', 'fr' => 'Votre référence' ),
			'wc_referentie_optioneel' => array( 'nl' => 'Jouw referentie (optioneel)', 'fr' => 'Votre référence (facultatif)' ),
			'wc_voorwaarden'        => array( 'nl' => 'Algemene voorwaarden', 'fr' => 'Conditions générales' ),
			'wc_op_rekening'        => array( 'nl' => 'Op rekening', 'fr' => 'Sur facture' ),
			'wc_op_rekening_uitleg' => array( 'nl' => 'Je ontvangt de factuur van Homburg. Betalen volgens je gebruikelijke afspraken.', 'fr' => 'Vous recevez la facture de Homburg. Paiement selon vos conditions habituelles.' ),
			'wc_levering_afspraak'  => array( 'nl' => 'Levering volgens afspraak', 'fr' => 'Livraison selon accord' ),
			'bestel_filteren'   => array( 'nl' => 'Filteren', 'fr' => 'Filtrer' ),
			'bestel_leeg_resultaat' => array(
				'nl' => 'Geen bestellingen gevonden voor deze zoekopdracht/status.',
				'fr' => 'Aucune commande trouvée pour cette recherche/ce statut.',
			),

			// Downloads
			// Zoekt in titel, omschrijving en merk (zie HDP_Downloads_Render::render_item()).
			'zoek_placeholder'  => array( 'nl' => 'Zoek op titel, omschrijving of merk…', 'fr' => 'Rechercher par titre, description ou marque…' ),
			'filter_regio'      => array( 'nl' => 'Regio', 'fr' => 'Région' ),
			'filter_alles'      => array( 'nl' => 'Alles', 'fr' => 'Tous' ),
			'filter_nederland'  => array( 'nl' => 'Nederland', 'fr' => 'Pays-Bas' ),
			'filter_belgie'     => array( 'nl' => 'België', 'fr' => 'Belgique' ),
			'filter_belgie_fr'  => array( 'nl' => 'België (Frans)', 'fr' => 'Belgique (francophone)' ),
			'filter_engels'     => array( 'nl' => 'Engels', 'fr' => 'Anglais' ),
			'filter_merk'       => array( 'nl' => 'Merk', 'fr' => 'Marque' ),
			'sorteren_label'    => array( 'nl' => 'Sorteren', 'fr' => 'Trier' ),
			'sorteer_nieuw'     => array( 'nl' => 'Nieuwste eerst', 'fr' => "Plus récents d'abord" ),
			'sorteer_oud'       => array( 'nl' => 'Oudste eerst', 'fr' => "Plus anciens d'abord" ),
			'sorteer_naam'      => array( 'nl' => 'Naam (A-Z)', 'fr' => 'Nom (A-Z)' ),
			'telling_van'       => array( 'nl' => 'van', 'fr' => 'sur' ),
			'telling_zichtbaar' => array( 'nl' => 'bestanden zichtbaar', 'fr' => 'fichiers visibles' ),
			'wis_filters'       => array( 'nl' => 'Filters wissen', 'fr' => 'Réinitialiser les filtres' ),
			'leeg_resultaat'    => array(
				'nl' => 'Geen downloads gevonden voor deze combinatie van filters.',
				'fr' => 'Aucun téléchargement trouvé pour cette combinaison de filtres.',
			),
			'nog_geen_downloads' => array( 'nl' => 'Nog geen downloads beschikbaar', 'fr' => 'Aucun téléchargement disponible pour le moment' ),
			'nog_geen_content'  => array( 'nl' => 'Nog geen content beschikbaar', 'fr' => 'Aucun contenu disponible pour le moment' ),
			'geen_downloads_taal' => array(
				'nl' => 'Geen downloads beschikbaar in deze taal.',
				'fr' => 'Aucun téléchargement disponible dans cette langue.',
			),
			'geen_content_taal' => array(
				'nl' => 'Geen content beschikbaar in deze taal.',
				'fr' => 'Aucun contenu disponible dans cette langue.',
			),
			'geen_downloads_merk' => array(
				'nl' => 'Geen downloads beschikbaar voor je geautoriseerde merken.',
				'fr' => 'Aucun téléchargement disponible pour vos marques autorisées.',
			),
			'geen_content_merk' => array(
				'nl' => 'Geen content beschikbaar voor je geautoriseerde merken.',
				'fr' => 'Aucun contenu disponible pour vos marques autorisées.',
			),
			'terug_naar_portaal' => array( 'nl' => '← Terug naar het portaal', 'fr' => '← Retour au portail' ),
			'btn_downloaden'    => array( 'nl' => 'Downloaden', 'fr' => 'Télécharger' ),
			// Een PDF of afbeelding in de browser openen in plaats van opslaan.
			'btn_bekijken'      => array( 'nl' => 'Bekijken', 'fr' => 'Voir' ),
			// Contentgalerij.
			'btn_kopieer_tekst' => array( 'nl' => 'Kopieer tekst', 'fr' => 'Copier le texte' ),
			'tekst_gekopieerd'  => array( 'nl' => 'Gekopieerd', 'fr' => 'Copié' ),
			'btn_alles_downloaden' => array( 'nl' => 'Alles downloaden (zip)', 'fr' => 'Tout télécharger (zip)' ),
		);
	}
}
