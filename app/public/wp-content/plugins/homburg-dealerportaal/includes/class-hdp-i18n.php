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
				'nl' => 'Uw aanmelding is bij ons binnengekomen. We controleren even of uw gegevens bij een bekend dealeraccount horen.',
				'fr' => 'Votre inscription nous est bien parvenue. Nous vérifions si vos données correspondent à un compte concessionnaire connu.',
			),
			'account_stappen'   => array(
				'nl' => 'U hoeft hier niets voor te doen: zodra uw account is goedgekeurd krijgt u daar automatisch een e-mail over. Doorgaans gebeurt dat binnen één werkdag.',
				'fr' => "Vous n'avez rien à faire : dès que votre compte sera approuvé, vous recevrez automatiquement un e-mail. Cela se fait généralement en un jour ouvrable.",
			),
			'account_duurt_lang' => array(
				'nl' => 'Duurt het langer, of heeft u haast? Neem gerust even contact op.',
				'fr' => "Cela prend plus de temps, ou vous êtes pressé ? N'hésitez pas à nous contacter.",
			),

			// Garantieportaal
			// Garantie — formulieren
			'garantie_kies'              => array( 'nl' => '— Maak een keuze —', 'fr' => '— Faites un choix —' ),
			'garantie_annuleren'         => array( 'nl' => 'Annuleren', 'fr' => 'Annuler' ),
			'garantie_bijlagen_hint'     => array(
				'nl' => 'JPG, PNG, WEBP, HEIC of PDF. Maximaal 10 bestanden van 10 MB.',
				'fr' => 'JPG, PNG, WEBP, HEIC ou PDF. Maximum 10 fichiers de 10 Mo.',
			),
			/* translators: %s zijn de bestandsnamen die niet gelukt zijn. */
			'garantie_bijlagen_mislukt'  => array(
				'nl' => 'Deze bestanden konden we niet opslaan: %s. U kunt ze alsnog meesturen via een bericht bij het ticket.',
				'fr' => "Nous n'avons pas pu enregistrer ces fichiers : %s. Vous pouvez encore les envoyer via un message sur le ticket.",
			),

			'garantie_ok_machine'        => array(
				'nl' => 'Uw machine is aangemeld. We beoordelen de aanmelding; u kunt er alvast een claim op indienen.',
				'fr' => "Votre machine est enregistrée. Nous examinons l'enregistrement ; vous pouvez déjà introduire une demande.",
			),
			/* translators: %s is het ticketnummer. */
			'garantie_ok_claim'          => array(
				'nl' => 'Uw claim is ingediend onder nummer %s. U ziet hieronder de stand van zaken.',
				'fr' => 'Votre demande a été introduite sous le numéro %s. Vous en voyez l’état ci-dessous.',
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
				'nl' => 'Uw bericht is verstuurd.',
				'fr' => 'Votre message a été envoyé.',
			),

			'garantie_fout_geen_toegang' => array(
				'nl' => 'U heeft geen toegang tot het garantieportaal.',
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
				'nl' => 'Dit serienummer is al aangemeld. Staat de machine niet in uw lijst? Neem dan even contact op.',
				'fr' => 'Ce numéro de série est déjà enregistré. La machine ne figure pas dans votre liste ? Contactez-nous.',
			),
			'garantie_fout_algemeen'     => array(
				'nl' => 'Er ging iets mis bij het opslaan. Probeer het nog een keer.',
				'fr' => "Une erreur s'est produite lors de l'enregistrement. Réessayez.",
			),

			'garantie_storing_kop'     => array( 'nl' => 'Even niet beschikbaar.', 'fr' => 'Momentanément indisponible.' ),
			'garantie_storing_tekst'   => array(
				'nl' => 'We kunnen uw garantiegegevens op dit moment niet ophalen. Probeer het over een paar minuten opnieuw; lukt het dan nog niet, neem dan even contact op.',
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
				'nl' => 'Wij hebben aanvullende informatie van u nodig om deze claim verder te kunnen behandelen.',
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
				'nl' => 'Meld een geleverde machine aan voor garantie. Daarna kunt u er claims op indienen zonder gegevens over te typen.',
				'fr' => 'Enregistrez une machine livrée pour la garantie. Vous pourrez ensuite introduire des demandes sans retaper les données.',
			),
			'garantie_ingang_machine_knop'  => array( 'nl' => 'Machine aanmelden', 'fr' => 'Enregistrer une machine' ),
			'garantie_ingang_claim_titel'   => array( 'nl' => 'Garantieclaim indienen', 'fr' => 'Introduire une demande de garantie' ),
			'garantie_ingang_claim_tekst'   => array(
				'nl' => "Is er een onderdeel stuk? Kies de machine, beschrijf de klacht en stuur foto's mee.",
				'fr' => 'Une pièce est défectueuse ? Choisissez la machine, décrivez la panne et joignez des photos.',
			),
			'garantie_ingang_claim_knop'    => array( 'nl' => 'Claim indienen', 'fr' => 'Introduire une demande' ),

			'garantie_wacht_een'    => array( 'nl' => 'Eén claim wacht op u', 'fr' => 'Une demande attend votre réponse' ),
			/* translators: %d is het aantal claims. */
			'garantie_wacht_meer'   => array( 'nl' => '%d claims wachten op u', 'fr' => '%d demandes attendent votre réponse' ),
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
			'garantie_geen_lopende_claims' => array( 'nl' => 'U heeft op dit moment geen lopende claims.', 'fr' => "Vous n'avez actuellement aucune demande en cours." ),
			/* translators: %d is het totale aantal claims. */
			'garantie_toon_alle_claims'    => array( 'nl' => 'Alle claims tonen (%d)', 'fr' => 'Afficher toutes les demandes (%d)' ),

			'garantie_mijn_machines'  => array( 'nl' => 'Mijn machines', 'fr' => 'Mes machines' ),
			'garantie_geen_machines'  => array(
				'nl' => 'U heeft nog geen machines aangemeld. Meld een machine aan om er claims op in te kunnen dienen.',
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
			'garantie_van_dealer'        => array( 'nl' => 'U', 'fr' => 'Vous' ),
			'garantie_bij_status'        => array( 'nl' => 'Bij status:', 'fr' => 'Pour le statut :' ),
			'garantie_antwoord_label'    => array( 'nl' => 'Reageren op dit ticket', 'fr' => 'Répondre à ce ticket' ),
			'garantie_antwoord_hint'     => array(
				'nl' => 'Stel een vraag of stuur de gevraagde informatie door.',
				'fr' => 'Posez une question ou transmettez les informations demandées.',
			),
			'garantie_antwoord_versturen' => array( 'nl' => 'Versturen', 'fr' => 'Envoyer' ),
			'garantie_terug_naar_overzicht' => array( 'nl' => 'Terug naar mijn claims', 'fr' => 'Retour à mes demandes' ),
			'garantie_ticket_onbekend'   => array(
				'nl' => 'Dit ticket bestaat niet, of hoort niet bij uw account.',
				'fr' => "Ce ticket n'existe pas ou n'appartient pas à votre compte.",
			),
			'garantie_ligt_bij_u'        => array(
				'nl' => 'Dit ticket wacht op u.',
				'fr' => 'Ce ticket attend votre réponse.',
			),
			'garantie_ligt_bij_homburg'  => array(
				'nl' => 'Dit ticket ligt bij Homburg. U hoeft nu niets te doen.',
				'fr' => "Ce ticket est chez Homburg. Vous n'avez rien à faire pour le moment.",
			),
			'garantie_ligt_bij_klaar'    => array(
				'nl' => 'Dit ticket is afgehandeld.',
				'fr' => 'Ce ticket est clôturé.',
			),

			// Garantie — formuliervelden. Sleutels komen overeen met HDP_Garantie::velden().
			'garantie_veld_machine'      => array( 'nl' => 'Machine', 'fr' => 'Machine' ),
			'garantie_veld_serienummer'  => array( 'nl' => 'Serienummer', 'fr' => 'Numéro de série' ),
			'garantie_veld_aankoopdatum' => array( 'nl' => 'Aankoopdatum', 'fr' => "Date d'achat" ),
			'garantie_veld_hectares'     => array( 'nl' => 'Aantal hectares', 'fr' => "Nombre d'hectares" ),
			'garantie_veld_klacht'       => array( 'nl' => 'Wat is er aan de hand?', 'fr' => 'Quel est le problème ?' ),
			'garantie_veld_onderdelen'   => array( 'nl' => 'Benodigde onderdelen', 'fr' => 'Pièces nécessaires' ),
			'garantie_veld_fotos'        => array( 'nl' => "Foto's", 'fr' => 'Photos' ),

			// Nieuw-markering
			'badge_nieuw'       => array( 'nl' => 'Nieuw', 'fr' => 'Nouveau' ),
			'nieuw_melding_een' => array(
				'nl' => 'Er staat 1 nieuw document voor u klaar',
				'fr' => 'Un nouveau document vous attend',
			),
			/* translators: %d is het aantal nieuwe documenten. */
			'nieuw_melding_meer' => array(
				'nl' => 'Er staan %d nieuwe documenten voor u klaar',
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
				'nl' => 'Wijzig hier uw weergavenaam en wachtwoord voor het dealerportaal.',
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
			'instellingen_opgeslagen' => array( 'nl' => 'Uw wijzigingen zijn opgeslagen.', 'fr' => 'Vos modifications ont été enregistrées.' ),
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
			'instellingen_email_bevestigd' => array( 'nl' => 'Uw e-mailadres is gewijzigd.', 'fr' => 'Votre adresse e-mail a été modifiée.' ),
			'instellingen_email_geannuleerd' => array( 'nl' => 'De wijziging is geannuleerd.', 'fr' => 'La modification a été annulée.' ),
			'instellingen_fout_email_ongeldig' => array(
				'nl' => 'Vul een geldig e-mailadres in.',
				'fr' => 'Veuillez saisir une adresse e-mail valide.',
			),
			'instellingen_fout_email_zelfde' => array(
				'nl' => 'Dit is al uw huidige e-mailadres.',
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
				'nl' => 'U heeft te vaak een e-mailwijziging aangevraagd. Probeer het over een uur opnieuw.',
				'fr' => "Vous avez demandé trop souvent une modification d'e-mail. Réessayez dans une heure.",
			),
			'email_wijzig_onderwerp' => array(
				'nl' => 'Bevestig uw nieuwe e-mailadres — Dealerportaal Homburg',
				'fr' => 'Confirmez votre nouvelle adresse e-mail — Portail concessionnaire Homburg',
			),
			'email_wijzig_body' => array(
				'nl' => "Beste dealer,\n\nU heeft een wijziging van uw e-mailadres aangevraagd voor het Homburg-dealerportaal. Klik op onderstaande link om deze wijziging te bevestigen:\n\n%s\n\nHeeft u dit niet zelf aangevraagd? Dan kunt u deze e-mail negeren.\n\nMet vriendelijke groet,\nHomburg Machinehandel",
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
				'nl' => 'U heeft nog geen bestellingen geplaatst.',
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
			'bestel_filteren'   => array( 'nl' => 'Filteren', 'fr' => 'Filtrer' ),
			'bestel_leeg_resultaat' => array(
				'nl' => 'Geen bestellingen gevonden voor deze zoekopdracht/status.',
				'fr' => 'Aucune commande trouvée pour cette recherche/ce statut.',
			),

			// Downloads
			'zoek_placeholder'  => array( 'nl' => 'Zoek op bestandsnaam…', 'fr' => 'Rechercher par nom de fichier…' ),
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
			'telling_zichtbaar' => array( 'nl' => 'downloads zichtbaar', 'fr' => 'téléchargements visibles' ),
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
				'nl' => 'Geen downloads beschikbaar voor uw geautoriseerde merken.',
				'fr' => 'Aucun téléchargement disponible pour vos marques autorisées.',
			),
			'geen_content_merk' => array(
				'nl' => 'Geen content beschikbaar voor uw geautoriseerde merken.',
				'fr' => 'Aucun contenu disponible pour vos marques autorisées.',
			),
			'terug_naar_portaal' => array( 'nl' => '← Terug naar het portaal', 'fr' => '← Retour au portail' ),
			'btn_downloaden'    => array( 'nl' => 'Downloaden', 'fr' => 'Télécharger' ),
		);
	}
}
