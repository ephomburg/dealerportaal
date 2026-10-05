<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Garantieportaal — de vaste begrippen.
 *
 * De claims zelf komen later uit Supabase: daar wordt de administratie
 * bijgehouden en daar beoordeelt Homburg ze via de Homburg App. Deze
 * website is de voordeur: die weet wie er inlogt, toont het formulier en
 * toont de stand van zaken.
 *
 * De statuslijst hieronder is bewust in één keer vastgelegd, nog vóór die
 * koppeling bestaat. Reden: zodra de app een status mag kiezen die hier
 * niet in staat, ziet de dealer een leeg of onbegrijpelijk vakje. De
 * Supabase-kolom krijgt dus exact deze waarden, en de website doet niets
 * anders dan ze naar Nederlands/Frans vertalen.
 */
class HDP_Garantie {

	/**
	 * De statussen. De sleutels zijn wat er in de database komt te staan;
	 * de labels komen uit HDP_I18N, zodat een Franstalige dealer zijn eigen
	 * taal ziet zonder dat er een tweede lijst bijkomt.
	 *
	 * Het verloop is niet één rechte lijn — er zitten twee vertakkingen in:
	 * tijdens de behandeling kan een claim bij Homburg liggen óf bij de
	 * fabrikant, en de afloop is goedgekeurd, afgewezen door Homburg, of
	 * afgewezen door de fabrikant. Daarom werkt de route met fases (zie
	 * FASES): per fase wordt getoond wat er in díé claim gebeurd is, en wat
	 * er nog komt staat er grijs onder.
	 */
	const STATUSSEN = array(
		'ingediend',
		'in_behandeling',
		'bij_fabrikant',
		'info_nodig',
		'goedgekeurd',
		'afgewezen',
		'afgewezen_fabrikant',
	);

	/**
	 * Welke status bij welke fase van de route hoort. De fase bepaalt waar
	 * een stap in de tijdlijn terechtkomt; binnen een fase is het de
	 * status die zegt wat er precies aan de hand is.
	 */
	const FASES = array(
		'ingediend'           => 'ingediend',
		'in_behandeling'      => 'behandeling',
		'bij_fabrikant'       => 'behandeling',
		'info_nodig'          => 'behandeling',
		'goedgekeurd'         => 'besluit',
		'afgewezen'           => 'besluit',
		'afgewezen_fabrikant' => 'besluit',
	);

	/** De fases in volgorde, voor de grijze "komt nog"-stappen onderaan de route. */
	const FASE_VOLGORDE = array( 'ingediend', 'behandeling', 'besluit' );

	/** De enige status waarbij de dealer zélf aan zet is — die mag opvallen. */
	const STATUS_ACTIE_DEALER = 'info_nodig';

	/** Statussen waarmee een claim klaar is; daarna komt er geen stap meer bij. */
	const EIND_STATUSSEN = array( 'goedgekeurd', 'afgewezen', 'afgewezen_fabrikant' );

	public static function status_label( $status ) {
		return HDP_I18N::t( 'garantie_status_' . self::geldige_status( $status ) );
	}

	/** CSS-klasse voor het statuslabel (kleur). */
	public static function status_klasse( $status ) {
		return 'hdp-garantie-status hdp-garantie-status-' . str_replace( '_', '-', self::geldige_status( $status ) );
	}

	public static function fase_van( $status ) {
		$status = self::geldige_status( $status );
		return self::FASES[ $status ];
	}

	public static function is_afgerond( $status ) {
		return in_array( self::geldige_status( $status ), self::EIND_STATUSSEN, true );
	}

	/** Een afwijzing hoort anders te lezen dan een goedkeuring, ook in de route. */
	public static function is_afwijzing( $status ) {
		return in_array( self::geldige_status( $status ), array( 'afgewezen', 'afgewezen_fabrikant' ), true );
	}

	/**
	 * Een onbekende status (bijv. nadat iemand in de app een nieuwe waarde
	 * invoert) valt terug op "in behandeling" in plaats van een leeg vakje:
	 * dat is het veiligste wat je een dealer kunt tonen zolang niemand weet
	 * wat het betekent.
	 */
	public static function geldige_status( $status ) {
		return in_array( $status, self::STATUSSEN, true ) ? $status : 'in_behandeling';
	}

	/**
	 * De velden van een claim, zoals voorgesteld. Staan hier zodat het
	 * formulierontwerp en straks de Supabase-tabel uit dezelfde lijst komen
	 * en niet uit elkaar kunnen lopen.
	 *
	 * 'type' is het soort invoerveld, 'verplicht' of het ingevuld moet zijn.
	 */
	public static function velden() {
		return array(
			// Deze drie staan al bij de aangemelde machine en worden daar
			// overgenomen: ze horen wél in de claim (en dus in de tabel),
			// maar de dealer hoeft ze niet opnieuw in te typen. Dat scheelt
			// werk én tikfouten in precies de velden waarop de fabrikant
			// controleert.
			'machine'      => array( 'type' => 'text', 'verplicht' => true, 'uit_machine' => true ),
			'serienummer'  => array( 'type' => 'text', 'verplicht' => true, 'uit_machine' => true ),
			'aankoopdatum' => array( 'type' => 'date', 'verplicht' => true, 'uit_machine' => true ),
			// Het aantal hectares vraagt de dealer wél opnieuw: dat is de
			// stand op het moment van de klacht, niet die bij aanmelding.
			'hectares'     => array( 'type' => 'number', 'verplicht' => false ),
			'klacht'       => array( 'type' => 'textarea', 'verplicht' => true ),
			'onderdelen'   => array( 'type' => 'textarea', 'verplicht' => false ),
			'fotos'        => array( 'type' => 'file', 'verplicht' => false ),
		);
	}

	/** De velden die de dealer op het claimformulier zelf invult. */
	public static function invulvelden() {
		return array_filter(
			self::velden(),
			static function ( $veld ) {
				return empty( $veld['uit_machine'] );
			}
		);
	}

	/**
	 * De velden waarmee een machine voor garantie wordt aangemeld. Aparte
	 * lijst van velden(): dat gaat over een claim, dit over de machine zelf.
	 */
	public static function machinevelden() {
		return array(
			'machine'      => array( 'type' => 'text', 'verplicht' => true ),
			'merk'         => array( 'type' => 'merk', 'verplicht' => true ),
			'serienummer'  => array( 'type' => 'text', 'verplicht' => true ),
			'aankoopdatum' => array( 'type' => 'date', 'verplicht' => true ),
			'klant'        => array( 'type' => 'text', 'verplicht' => false ),
			'hectares'     => array( 'type' => 'number', 'verplicht' => false ),
		);
	}

	// --- Gegevens: komen uit de Supabase van de Homburg App -------------
	//
	// Daar houdt Homburg de garantieadministratie bij; dit portaal is de
	// voordeur. Elke opvraag wordt gefilterd op het WordPress-accountnummer
	// van de ingelogde dealer. Dat filter zit hier, op één plek, en nergens
	// anders: claimnummers lopen op, dus zonder dat filter zou een dealer
	// andermans claims kunnen openen door het nummer in de URL te veranderen.

	/**
	 * Machine plus serienummer, als één naam. De dealer heeft vaak meerdere
	 * machines van hetzelfde type staan; zonder serienummer weet die niet
	 * welke bedoeld wordt.
	 */
	public static function machinenaam( $machine, $serienummer ) {
		if ( ! $serienummer ) {
			return $machine;
		}
		return $machine . ' · ' . $serienummer;
	}

	/**
	 * Hoe staat de garantie van een machine ervoor?
	 *
	 * Geeft naast "verlopen of niet" ook hoeveel er nog van de looptijd over
	 * is, zodat de weergave een balk kan tonen. Een dealer ziet dan aankomen
	 * dat dekking afloopt in plaats van er achteraf tegenaan te lopen.
	 *
	 * @return array{verlopen: bool, bijna: bool, maanden: int, verstreken: int, tot: string}
	 */
	public static function garantiestand( $machine, $nu = 0 ) {
		$nu    = $nu ? (int) $nu : time();
		$start = isset( $machine['aankoopdatum'] ) ? strtotime( $machine['aankoopdatum'] ) : 0;
		$eind  = isset( $machine['garantie_tot'] ) ? strtotime( $machine['garantie_tot'] ) : 0;

		if ( ! $start || ! $eind || $eind <= $start ) {
			return array(
				'verlopen'   => false,
				'bijna'      => false,
				'maanden'    => 0,
				'verstreken' => 0,
				'tot'        => '',
			);
		}

		$verlopen = $nu >= $eind;
		$maanden  = $verlopen ? 0 : (int) floor( ( $eind - $nu ) / ( 30 * DAY_IN_SECONDS ) );

		// Hoeveel procent van de looptijd is om; begrensd op 0–100 zodat de
		// balk niet buiten zijn vakje loopt bij een datum in de toekomst.
		$verstreken = (int) round( ( ( $nu - $start ) / ( $eind - $start ) ) * 100 );
		$verstreken = max( 0, min( 100, $verstreken ) );

		return array(
			'verlopen'   => $verlopen,
			// Binnen een half jaar aflopen is het moment om nog te claimen.
			'bijna'      => ! $verlopen && $maanden <= 6,
			'maanden'    => $maanden,
			'verstreken' => $verstreken,
			'tot'        => isset( $machine['garantie_tot'] ) ? $machine['garantie_tot'] : '',
		);
	}

	/** Het accountnummer waarop gefilterd wordt; '' als er niemand (geldig) is ingelogd. */
	private static function dealer_id( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( ! $user_id || ! HDP_Roles::mag_portaal_zien( $user_id ) ) {
			return '';
		}

		return (string) $user_id;
	}

	public static function beschikbaar() {
		return HDP_Supabase::beschikbaar( HDP_Supabase::APP );
	}

	/** De dealergegevens zoals ze bij een machine of claim moeten worden meegestuurd. */
	public static function dealervelden( $user_id = 0 ) {
		$dealer_id = self::dealer_id( $user_id );
		$user      = $dealer_id ? get_userdata( (int) $dealer_id ) : null;

		if ( ! $user ) {
			return array();
		}

		return array(
			'dealer_id'      => $dealer_id,
			'dealer_naam'    => $user->display_name ? $user->display_name : $user->user_login,
			'dealer_bedrijf' => (string) get_user_meta( $user->ID, 'billing_company', true ),
			'dealer_email'   => $user->user_email,
		);
	}

	/** De aangemelde machines van deze dealer, nieuwste eerst. */
	public static function machines( $user_id = 0 ) {
		$dealer_id = self::dealer_id( $user_id );
		if ( '' === $dealer_id ) {
			return array();
		}

		$rijen = HDP_Supabase::selecteer(
			HDP_Supabase::APP,
			'machines',
			array(
				'select'    => '*',
				'dealer_id' => 'eq.' . $dealer_id,
				'order'     => 'aangemaakt_op.desc',
			)
		);

		return is_wp_error( $rijen ) ? $rijen : array_map( array( __CLASS__, 'machine_uit_rij' ), $rijen );
	}

	/**
	 * De claims van deze dealer, met de machinegegevens erbij. PostgREST kan
	 * die in één opvraag meeleveren via de koppeling tussen de tabellen, dus
	 * dat scheelt een tweede ronde naar de database.
	 */
	public static function claims( $user_id = 0 ) {
		$dealer_id = self::dealer_id( $user_id );
		if ( '' === $dealer_id ) {
			return array();
		}

		$rijen = HDP_Supabase::selecteer(
			HDP_Supabase::APP,
			'claims',
			array(
				'select'    => '*,machines(machine,merk,serienummer,aankoopdatum,garantie_tot,klant,status,reden)',
				'dealer_id' => 'eq.' . $dealer_id,
				'order'     => 'ingediend_op.desc',
			)
		);

		return is_wp_error( $rijen ) ? $rijen : array_map( array( __CLASS__, 'claim_uit_rij' ), $rijen );
	}

	/**
	 * Eén claim, maar alleen als die van déze dealer is.
	 *
	 * Het filter op dealer_id staat bewust in de opvraag zelf en niet in een
	 * controle achteraf: zo kan de database domweg niets anders teruggeven.
	 *
	 * @return array|null|WP_Error
	 */
	public static function claim_voor_dealer( $nummer, $user_id = 0 ) {
		$dealer_id = self::dealer_id( $user_id );
		if ( '' === $dealer_id || '' === trim( (string) $nummer ) ) {
			return null;
		}

		$rijen = HDP_Supabase::selecteer(
			HDP_Supabase::APP,
			'claims',
			array(
				'select'    => '*,machines(machine,merk,serienummer,aankoopdatum,garantie_tot,klant,status,reden)',
				'dealer_id' => 'eq.' . $dealer_id,
				'nummer'    => 'eq.' . $nummer,
				'limit'     => '1',
			)
		);

		if ( is_wp_error( $rijen ) ) {
			return $rijen;
		}
		if ( ! $rijen ) {
			return null;
		}

		$claim = self::claim_uit_rij( $rijen[0] );

		$claim['verloop']  = self::verloop_van( $rijen[0]['id'], $claim );
		$claim['bijlagen'] = self::bijlagen_van( $rijen[0]['id'] );

		return $claim;
	}

	/**
	 * Het verloop van een claim, altijd via verloop_voor_dealer: die view
	 * laat de interne notities van Homburg weg. Rechtstreeks uit verloop
	 * lezen zou werken, maar dan hangt het aan een filter dat je kunt
	 * vergeten — en dan lekt er een interne notitie naar een dealer.
	 */
	private static function verloop_van( $claim_id, $claim ) {
		$rijen = HDP_Supabase::selecteer(
			HDP_Supabase::APP,
			'verloop_voor_dealer',
			array(
				'select'   => 'afzender,wie,status,opmerking,wanneer',
				'claim_id' => 'eq.' . $claim_id,
				'order'    => 'wanneer.asc,volgnr.asc',
			)
		);

		if ( is_wp_error( $rijen ) ) {
			$rijen = array();
		}

		$verloop = array();
		foreach ( $rijen as $rij ) {
			$verloop[] = array(
				'afzender'  => $rij['afzender'],
				'status'    => (string) $rij['status'],
				'wie'       => (string) $rij['wie'],
				'wanneer'   => $rij['wanneer'],
				'opmerking' => (string) $rij['opmerking'],
			);
		}

		// De administratie schrijft bij het indienen zelf geen regel; die
		// eerste stap leiden we af uit de claim. Mocht daar later wél een
		// regel voor komen, dan staat hij er al en voegen we niets toe —
		// anders zou er tweemaal "Ingediend" in de route staan.
		$heeft_ingediend = false;
		foreach ( $verloop as $regel ) {
			if ( 'ingediend' === $regel['status'] ) {
				$heeft_ingediend = true;
				break;
			}
		}

		if ( ! $heeft_ingediend ) {
			array_unshift(
				$verloop,
				array(
					'afzender'  => 'dealer',
					'status'    => 'ingediend',
					'wie'       => $claim['dealer_naam'],
					'wanneer'   => $claim['ingediend_op'],
					'opmerking' => '',
				)
			);
		}

		return $verloop;
	}

	private static function bijlagen_van( $claim_id ) {
		$rijen = HDP_Supabase::selecteer(
			HDP_Supabase::APP,
			'bijlagen',
			array(
				'select'   => 'bestandsnaam,pad',
				'claim_id' => 'eq.' . $claim_id,
				'order'    => 'aangemaakt_op.asc',
			)
		);

		return is_wp_error( $rijen ) ? array() : wp_list_pluck( $rijen, 'bestandsnaam' );
	}

	/* --- Van databaserij naar wat de weergave verwacht ------------------ */

	private static function machine_uit_rij( $rij ) {
		return array(
			'id'           => $rij['id'],
			'serienummer'  => (string) $rij['serienummer'],
			'machine'      => (string) $rij['machine'],
			'merk'         => (string) $rij['merk'],
			'aankoopdatum' => (string) $rij['aankoopdatum'],
			'garantie_tot' => (string) $rij['garantie_tot'],
			'hectares'     => (string) $rij['hectares'],
			'klant'        => (string) $rij['klant'],
			'status'       => (string) $rij['status'],
			'reden'        => (string) $rij['reden'],
		);
	}

	private static function claim_uit_rij( $rij ) {
		$machine = isset( $rij['machines'] ) && is_array( $rij['machines'] ) ? $rij['machines'] : array();

		return array(
			'id'              => $rij['id'],
			'nummer'          => (string) $rij['nummer'],
			'status'          => self::geldige_status( (string) $rij['status'] ),
			'klacht'          => (string) $rij['klacht'],
			'onderdelen'      => (string) $rij['onderdelen'],
			'hectares'        => (string) $rij['hectares'],
			'behandelaar'     => (string) $rij['behandelaar'],
			'ingediend'       => (string) $rij['ingediend_op'],
			'ingediend_op'    => (string) $rij['ingediend_op'],
			'dealer_naam'     => (string) $rij['dealer_naam'],
			'machine_id'      => $rij['machine_id'],
			'machine'         => isset( $machine['machine'] ) ? (string) $machine['machine'] : '',
			'merk'            => isset( $machine['merk'] ) ? (string) $machine['merk'] : '',
			'serienummer'     => isset( $machine['serienummer'] ) ? (string) $machine['serienummer'] : '',
			'aankoopdatum'    => isset( $machine['aankoopdatum'] ) ? (string) $machine['aankoopdatum'] : '',
			'garantie_tot'    => isset( $machine['garantie_tot'] ) ? (string) $machine['garantie_tot'] : '',
			'machine_status'  => isset( $machine['status'] ) ? (string) $machine['status'] : '',
			'machine_reden'   => isset( $machine['reden'] ) ? (string) $machine['reden'] : '',
			'bijlagen'        => array(),
			'verloop'         => array(),
		);
	}

	/** Claims die nog lopen — dat is wat op het overzicht hoort te staan. */
	public static function lopende_claims( $user_id = 0 ) {
		$claims = self::claims( $user_id );
		if ( is_wp_error( $claims ) ) {
			return $claims;
		}

		return array_values(
			array_filter(
				$claims,
				static function ( $claim ) {
					return ! self::is_afgerond( $claim['status'] );
				}
			)
		);
	}

	/** Claims waar de dealer zelf aan zet is. */
	public static function claims_die_wachten_op_dealer( $user_id = 0 ) {
		$claims = self::claims( $user_id );
		if ( is_wp_error( $claims ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$claims,
				static function ( $claim ) {
					return 'dealer' === self::ligt_bij( $claim );
				}
			)
		);
	}

	/** De claims die bij één machine horen. */
	public static function claims_van_machine( $serienummer, $user_id = 0 ) {
		$claims = self::claims( $user_id );
		if ( is_wp_error( $claims ) ) {
			return array();
		}

		return array_values(
			array_filter(
				$claims,
				static function ( $claim ) use ( $serienummer ) {
					return $claim['serienummer'] === $serienummer;
				}
			)
		);
	}

	/**
	 * De statuswijzigingen uit het verloop, in volgorde. Een regel zonder
	 * status is een los bericht en hoort niet in de route thuis.
	 */
	public static function statuswijzigingen( $claim ) {
		$verloop = isset( $claim['verloop'] ) ? $claim['verloop'] : array();

		return array_values(
			array_filter(
				$verloop,
				static function ( $regel ) {
					return ! empty( $regel['status'] );
				}
			)
		);
	}

	/**
	 * Het gesprek bij een claim: alles uit het verloop waar tekst bij staat,
	 * van beide kanten, in volgorde. Interne notities van Homburg zitten hier
	 * niet bij — die laat de view verloop_voor_dealer al weg.
	 */
	public static function berichten( $claim ) {
		$verloop = isset( $claim['verloop'] ) ? $claim['verloop'] : array();

		$berichten = array();
		foreach ( $verloop as $regel ) {
			if ( empty( $regel['opmerking'] ) ) {
				continue;
			}
			$berichten[] = array(
				'afzender'  => isset( $regel['afzender'] ) ? $regel['afzender'] : 'homburg',
				'wie'       => isset( $regel['wie'] ) ? $regel['wie'] : '',
				'wanneer'   => isset( $regel['wanneer'] ) ? $regel['wanneer'] : '',
				'tekst'     => $regel['opmerking'],
				'status'    => isset( $regel['status'] ) ? $regel['status'] : '',
			);
		}

		return $berichten;
	}

	/**
	 * Sinds wanneer staat deze claim op zijn huidige status?
	 *
	 * Dat is wat een dealer wil weten ("ligt dit al een week stil?") en het
	 * stond nergens. Komt uit de laatste statuswijziging in het verloop; is
	 * die er niet, dan uit het moment van indienen.
	 */
	public static function status_sinds( $claim ) {
		$wijzigingen = self::statuswijzigingen( $claim );

		for ( $i = count( $wijzigingen ) - 1; $i >= 0; $i-- ) {
			if ( isset( $claim['status'] ) && $wijzigingen[ $i ]['status'] === $claim['status'] ) {
				return $wijzigingen[ $i ]['wanneer'];
			}
		}

		return isset( $claim['ingediend_op'] ) ? $claim['ingediend_op'] : '';
	}

	/** Hoeveel hele dagen staat deze claim al op dezelfde status? */
	public static function dagen_in_status( $claim, $nu = 0 ) {
		$sinds = strtotime( (string) self::status_sinds( $claim ) );

		if ( ! $sinds ) {
			return 0;
		}

		$nu = $nu ? (int) $nu : time();

		return max( 0, (int) floor( ( $nu - $sinds ) / DAY_IN_SECONDS ) );
	}

	/** Bij wie ligt de bal? Bepaalt de regel onderaan het ticket. */
	public static function ligt_bij( $claim ) {
		$status = isset( $claim['status'] ) ? self::geldige_status( $claim['status'] ) : '';

		if ( self::is_afgerond( $status ) ) {
			return '';
		}
		if ( self::STATUS_ACTIE_DEALER === $status ) {
			return 'dealer';
		}
		return 'homburg';
	}

	/**
	 * De route van een claim: de stappen die daadwerkelijk zijn gezet,
	 * gevolgd door de fases die nog moeten komen (grijs). Zo ziet de dealer
	 * niet alleen waar de claim nú ligt, maar ook wat er nog volgt.
	 *
	 * @return array Lijst stappen met: status, fase, wie, wanneer, opmerking,
	 *               gedaan (bool), huidig (bool).
	 */
	public static function route( $claim ) {
		$stappen  = array();
		$gezet    = self::statuswijzigingen( $claim );
		$laatste  = count( $gezet ) - 1;
		$afgerond = isset( $claim['status'] ) && self::is_afgerond( $claim['status'] );
		$gehad    = array();

		foreach ( $gezet as $i => $stap ) {
			$status  = self::geldige_status( $stap['status'] );
			$fase    = self::fase_van( $status );
			$gehad[] = $fase;

			$stappen[] = array(
				'status'    => $status,
				'fase'      => $fase,
				'wie'       => isset( $stap['wie'] ) ? $stap['wie'] : '',
				'wanneer'   => isset( $stap['wanneer'] ) ? $stap['wanneer'] : '',
				'opmerking' => isset( $stap['opmerking'] ) ? $stap['opmerking'] : '',
				'gedaan'    => $i < $laatste,
				'huidig'    => $i === $laatste,
			);
		}

		// Wat nog komt: elke fase die deze claim nog niet geraakt heeft.
		// Bij een afgeronde claim komt er niets meer, ook niet als er een
		// fase is overgeslagen (een claim kan direct naar de fabrikant).
		if ( ! $afgerond ) {
			foreach ( self::FASE_VOLGORDE as $fase ) {
				if ( in_array( $fase, $gehad, true ) ) {
					continue;
				}
				$stappen[] = array(
					'status'    => '',
					'fase'      => $fase,
					'wie'       => '',
					'wanneer'   => '',
					'opmerking' => '',
					'gedaan'    => false,
					'huidig'    => false,
				);
			}
		}

		return $stappen;
	}

	// --- Schrijven: wat de dealer indient --------------------------------
	//
	// Alles gaat rechtstreeks naar de claimadministratie; dit portaal bewaart
	// zelf niets. De database weigert bewust van alles (een serienummer dat al
	// bestaat, een claim op een afgewezen machine) — die meldingen gaan als
	// WP_Error terug naar het formulier in plaats van verloren te gaan.

	/** De opslagbak waarin foto's en facturen terechtkomen. Privé. */
	const BIJLAGEN_BAK = 'garantie-bijlagen';

	/** Wat een dealer mag aanleveren, en hoe groot. */
	const BIJLAGE_TYPES  = array( 'jpg', 'jpeg', 'png', 'webp', 'heic', 'pdf' );
	const BIJLAGE_MAX    = 10485760; // 10 MB per bestand.
	const BIJLAGE_AANTAL = 10;

	/**
	 * Meldt een machine aan voor garantie.
	 *
	 * @param array $waarden Uit het formulier, al opgeschoond.
	 * @return array|WP_Error De aangemaakte machine.
	 */
	public static function meld_machine_aan( $waarden ) {
		$dealer = self::dealervelden();
		if ( ! $dealer ) {
			return new WP_Error( 'hdp_geen_toegang', HDP_I18N::t( 'garantie_fout_geen_toegang' ) );
		}

		$rij = array_merge(
			$dealer,
			array(
				'machine'      => $waarden['machine'],
				'merk'         => $waarden['merk'],
				'serienummer'  => $waarden['serienummer'],
				'aankoopdatum' => $waarden['aankoopdatum'],
				'klant'        => $waarden['klant'],
				'hectares'     => $waarden['hectares'],
			)
		);

		$rijen = HDP_Supabase::voeg_toe( HDP_Supabase::APP, 'machines', array( $rij ) );

		if ( is_wp_error( $rijen ) ) {
			return $rijen;
		}
		if ( ! $rijen ) {
			return new WP_Error( 'hdp_geen_antwoord', HDP_I18N::t( 'garantie_fout_algemeen' ) );
		}

		return $rijen[0];
	}

	/**
	 * Dient een claim in op een eigen machine.
	 *
	 * Het serienummer komt uit het formulier, maar de machine wordt hier
	 * opgezocht binnen de eigen machines — zo kan niemand een claim op
	 * andermans machine indienen door een id mee te sturen.
	 *
	 * @return array|WP_Error De aangemaakte claim.
	 */
	public static function dien_claim_in( $waarden ) {
		$dealer = self::dealervelden();
		if ( ! $dealer ) {
			return new WP_Error( 'hdp_geen_toegang', HDP_I18N::t( 'garantie_fout_geen_toegang' ) );
		}

		$machines = self::machines();
		if ( is_wp_error( $machines ) ) {
			return $machines;
		}

		$machine = null;
		foreach ( $machines as $kandidaat ) {
			if ( $kandidaat['serienummer'] === $waarden['machine'] ) {
				$machine = $kandidaat;
				break;
			}
		}

		if ( ! $machine ) {
			return new WP_Error( 'hdp_onbekende_machine', HDP_I18N::t( 'garantie_fout_machine' ) );
		}

		$rij = array_merge(
			$dealer,
			array(
				'machine_id' => $machine['id'],
				'klacht'     => $waarden['klacht'],
				'onderdelen' => $waarden['onderdelen'],
				'hectares'   => $waarden['hectares'],
			)
		);

		$rijen = HDP_Supabase::voeg_toe( HDP_Supabase::APP, 'claims', array( $rij ) );

		if ( is_wp_error( $rijen ) ) {
			return $rijen;
		}
		if ( ! $rijen ) {
			return new WP_Error( 'hdp_geen_antwoord', HDP_I18N::t( 'garantie_fout_algemeen' ) );
		}

		return $rijen[0];
	}

	/**
	 * Een bericht van de dealer bij een claim.
	 *
	 * Zet bewust geen status: de dealer beantwoordt een vraag, Homburg
	 * bepaalt wat dat voor de status betekent. De claim wordt eerst
	 * opgezocht binnen de eigen claims, zodat je niet op andermans ticket
	 * kunt schrijven door een nummer te raden.
	 *
	 * @return array|WP_Error
	 */
	public static function stuur_bericht( $nummer, $tekst, $bestanden = array() ) {
		$claim = self::claim_voor_dealer( $nummer );

		if ( is_wp_error( $claim ) ) {
			return $claim;
		}
		if ( ! $claim ) {
			return new WP_Error( 'hdp_onbekend_ticket', HDP_I18N::t( 'garantie_ticket_onbekend' ) );
		}

		$tekst       = trim( (string) $tekst );
		$heeft_files = ! empty( $bestanden['name'] ) && is_array( $bestanden['name'] ) && '' !== implode( '', $bestanden['name'] );

		if ( '' === $tekst && ! $heeft_files ) {
			return new WP_Error( 'hdp_leeg_bericht', HDP_I18N::t( 'garantie_fout_leeg_bericht' ) );
		}

		// Alleen bestanden, geen tekst: de administratie eist bij een regel
		// zonder status een toelichting, en een lege regel in het gesprek
		// leest ook nergens naar. Dus noemen we wat er is meegestuurd.
		if ( '' === $tekst ) {
			$namen = array_filter( array_map( 'sanitize_file_name', $bestanden['name'] ) );
			$tekst = sprintf( HDP_I18N::t( 'garantie_bericht_alleen_bijlagen' ), implode( ', ', $namen ) );
		}

		$user = wp_get_current_user();

		$rijen = HDP_Supabase::voeg_toe(
			HDP_Supabase::APP,
			'verloop',
			array(
				array(
					'claim_id'  => $claim['id'],
					'afzender'  => 'dealer',
					'wie'       => $user->display_name ? $user->display_name : $user->user_login,
					'opmerking' => $tekst,
				),
			)
		);

		if ( is_wp_error( $rijen ) ) {
			return $rijen;
		}

		if ( $heeft_files ) {
			$claim['bijlagen_mislukt'] = self::bewaar_bijlagen( $bestanden, 'claim', $claim['id'] );
		}

		return $claim;
	}

	/**
	 * Zet de meegestuurde bestanden in de opslag en legt ze vast bij de
	 * claim of machine.
	 *
	 * Een mislukte bijlage laat de claim zelf staan: die is al ingediend en
	 * weggooien zou erger zijn dan een ontbrekende foto. De dealer krijgt wel
	 * te horen welke bestanden niet gelukt zijn.
	 *
	 * @param array  $bestanden Zoals $_FILES ze aanlevert.
	 * @param string $soort     'claim' of 'machine'.
	 * @return array Namen van de bestanden die niet gelukt zijn.
	 */
	public static function bewaar_bijlagen( $bestanden, $soort, $id ) {
		$mislukt = array();

		if ( empty( $bestanden['name'] ) || ! is_array( $bestanden['name'] ) ) {
			return $mislukt;
		}

		$aantal = min( count( $bestanden['name'] ), self::BIJLAGE_AANTAL );

		for ( $i = 0; $i < $aantal; $i++ ) {
			$naam = sanitize_file_name( (string) $bestanden['name'][ $i ] );

			if ( '' === $naam || UPLOAD_ERR_NO_FILE === (int) $bestanden['error'][ $i ] ) {
				continue;
			}

			if ( UPLOAD_ERR_OK !== (int) $bestanden['error'][ $i ] || (int) $bestanden['size'][ $i ] > self::BIJLAGE_MAX ) {
				$mislukt[] = $naam;
				continue;
			}

			$extensie = strtolower( pathinfo( $naam, PATHINFO_EXTENSION ) );
			if ( ! in_array( $extensie, self::BIJLAGE_TYPES, true ) ) {
				$mislukt[] = $naam;
				continue;
			}

			// Niet op de meegestuurde bestandsnaam vertrouwen: WordPress
			// bepaalt zelf welk soort bestand dit werkelijk is.
			$gecontroleerd = wp_check_filetype_and_ext( $bestanden['tmp_name'][ $i ], $naam );
			$mime          = $gecontroleerd['type'] ? $gecontroleerd['type'] : 'application/octet-stream';

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- een net geüpload tijdelijk bestand; WP_Filesystem voegt hier niets toe.
			$inhoud = file_get_contents( $bestanden['tmp_name'][ $i ] );
			if ( false === $inhoud ) {
				$mislukt[] = $naam;
				continue;
			}

			// Eigen pad per bestand, zodat twee dealers met dezelfde
			// bestandsnaam elkaar niet overschrijven.
			$pad = $soort . 's/' . $id . '/' . wp_generate_password( 8, false ) . '-' . $naam;

			$gelukt = HDP_Supabase::upload( HDP_Supabase::APP, self::BIJLAGEN_BAK, $pad, $inhoud, $mime );
			if ( is_wp_error( $gelukt ) ) {
				$mislukt[] = $naam;
				continue;
			}

			$rij                   = array(
				'bestandsnaam'  => $naam,
				'pad'           => $pad,
				'geupload_door' => 'dealer',
			);
			$rij[ $soort . '_id' ] = $id;

			$vastgelegd = HDP_Supabase::voeg_toe( HDP_Supabase::APP, 'bijlagen', array( $rij ) );
			if ( is_wp_error( $vastgelegd ) ) {
				$mislukt[] = $naam;
			}
		}

		return $mislukt;
	}
}
