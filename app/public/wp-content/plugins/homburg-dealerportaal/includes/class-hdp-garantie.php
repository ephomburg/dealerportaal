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
	 * Voorbeeldclaims voor het ontwerp, zolang de koppeling met de
	 * claimadministratie er nog niet is. Bewust herkenbaar nep (de
	 * weergave zet er ook een melding boven), zodat niemand dit voor
	 * echte claims aanziet.
	 *
	 * De opbouw is tegelijk het voorstel voor de database: een claim met
	 * vaste gegevens, een lijst bijlagen, en een 'route' — elke statuswijziging
	 * een eigen regel met wie het deed, wanneer, en een opmerking. Die
	 * opmerking is het hele punt: Homburg schrijft hem in de app, de dealer
	 * leest hem hier terug, bij de stap waar hij hoort.
	 */
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

	/**
	 * De aangemelde machines van deze dealer — straks een eigen tabel, met
	 * de claims die eraan hangen. Nu nog voorbeelddata, net als de claims.
	 *
	 * Het serienummer is de sleutel: dat is waar een claim naar verwijst, en
	 * het is wat een machine uniek maakt.
	 */
	public static function voorbeeldmachines() {
		return array(
			'HN4-220718-0391' => array(
				'serienummer'  => 'HN4-220718-0391',
				'machine'      => 'HARDI Navigator 4000',
				'merk'         => 'HARDI',
				'aankoopdatum' => '2022-07-18',
				'garantie_tot' => '2027-07-18',
				'hectares'     => '1.240',
				'klant'        => 'Mts. Van Dijk, Zeewolde',
			),
			'TV8-210402-1174' => array(
				'serienummer'  => 'TV8-210402-1174',
				'machine'      => 'Väderstad Tempo V8',
				'merk'         => 'Väderstad',
				'aankoopdatum' => '2021-04-02',
				'garantie_tot' => '2027-04-02',
				'hectares'     => '860',
				'klant'        => 'Akkerbouwbedrijf De Horst',
			),
			'BM35-190916-0042' => array(
				'serienummer'  => 'BM35-190916-0042',
				'machine'      => 'Bogballe M35W',
				'merk'         => 'Bogballe',
				'aankoopdatum' => '2019-09-16',
				'garantie_tot' => '2024-09-16',
				'hectares'     => '',
				'klant'        => 'Loonbedrijf Kamps',
			),
			'GRC-200611-0863' => array(
				'serienummer'  => 'GRC-200611-0863',
				'machine'      => 'Garford Robocrop',
				'merk'         => 'Garford',
				'aankoopdatum' => '2020-06-11',
				'garantie_tot' => '2025-06-11',
				'hectares'     => '2.310',
				'klant'        => 'Mts. Van Dijk, Zeewolde',
			),
		);
	}

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
			'tot'        => $machine['garantie_tot'],
		);
	}

	/** De claims die bij één machine horen. */
	public static function claims_van_machine( $serienummer ) {
		return array_values(
			array_filter(
				self::voorbeeldclaims(),
				static function ( $claim ) use ( $serienummer ) {
					return isset( $claim['serienummer'] ) && $claim['serienummer'] === $serienummer;
				}
			)
		);
	}

	/** Claims die nog lopen — dat is wat op het overzicht hoort te staan. */
	public static function lopende_claims() {
		return array_values(
			array_filter(
				self::voorbeeldclaims(),
				static function ( $claim ) {
					return ! self::is_afgerond( $claim['status'] );
				}
			)
		);
	}

	/** Claims waar de dealer zelf aan zet is. */
	public static function claims_die_wachten_op_dealer() {
		return array_values(
			array_filter(
				self::voorbeeldclaims(),
				static function ( $claim ) {
					return 'dealer' === self::ligt_bij( $claim );
				}
			)
		);
	}

	public static function voorbeeldclaims() {
		return array(
			'GAR-2026-0184' => array(
				'nummer'       => 'GAR-2026-0184',
				'machine'      => 'HARDI Navigator 4000',
				'merk'         => 'HARDI',
				'serienummer'  => 'HN4-220718-0391',
				'aankoopdatum' => '2022-07-18',
				'hectares'     => '1.240',
				'klacht'       => 'Spuitboom zakt tijdens het werk aan de linkerzijde weg. Hydraulische cilinder lekt zichtbaar bij de onderste bevestiging.',
				'onderdelen'   => 'Hydraulische cilinder links (art. 141-0392) en afdichtingsset.',
				'ingediend'    => '2026-10-02',
				'status'       => 'info_nodig',
				'bijlagen'     => array( 'Cilinder lekkage 1.jpg', 'Cilinder lekkage 2.jpg', 'Typeplaatje machine.jpg' ),
				'verloop'      => array(
					array(
						'afzender'  => 'dealer',
						'status'    => 'ingediend',
						'wie'       => 'Marijn van den Akker',
						'wanneer'   => '2026-10-02 20:16',
						'opmerking' => '',
					),
					array(
						'afzender'  => 'homburg',
						'status'    => 'in_behandeling',
						'wie'       => 'Gerard de Boer',
						'wanneer'   => '2026-10-03 08:41',
						'opmerking' => 'Claim ontvangen. Ik kijk er vandaag naar.',
					),
					array(
						'afzender'  => 'homburg',
						'status'    => 'info_nodig',
						'wie'       => 'Gerard de Boer',
						'wanneer'   => '2026-10-03 09:12',
						'opmerking' => 'Kunt u een foto sturen waarop het typeplaatje én het aantal hectares te zien zijn? De fabrikant vraagt daar bij deze serie altijd om.',
					),
				),
			),
			'GAR-2026-0177' => array(
				'nummer'       => 'GAR-2026-0177',
				'machine'      => 'Väderstad Tempo V8',
				'merk'         => 'Väderstad',
				'serienummer'  => 'TV8-210402-1174',
				'aankoopdatum' => '2021-04-02',
				'hectares'     => '860',
				'klacht'       => 'Zaaihuis element 3 geeft onregelmatige afgifte, ook na afstellen en reinigen.',
				'onderdelen'   => 'Zaaihuis compleet element 3.',
				'ingediend'    => '2026-09-21',
				'status'       => 'bij_fabrikant',
				'bijlagen'     => array( 'Element 3 detail.jpg', 'Testrapport afgifte.pdf' ),
				'verloop'      => array(
					array(
						'afzender'  => 'dealer',
						'status'    => 'ingediend',
						'wie'       => 'Marijn van den Akker',
						'wanneer'   => '2026-09-21 14:03',
						'opmerking' => '',
					),
					array(
						'afzender'  => 'homburg',
						'status'    => 'in_behandeling',
						'wie'       => 'Gerard de Boer',
						'wanneer'   => '2026-09-22 09:30',
						'opmerking' => 'Compleet aangeleverd, dank. Ik leg hem voor aan Väderstad.',
					),
					array(
						'afzender'  => 'homburg',
						'status'    => 'bij_fabrikant',
						'wie'       => 'Gerard de Boer',
						'wanneer'   => '2026-09-24 11:05',
						'opmerking' => 'Doorgestuurd naar Väderstad onder referentie VS-2026-8841. Reactietermijn is doorgaans twee weken.',
					),
					array(
						'afzender'  => 'dealer',
						'status'    => '',
						'wie'       => 'Marijn van den Akker',
						'wanneer'   => '2026-10-01 11:28',
						'opmerking' => 'De klant vraagt ernaar — is er al iets bekend? De machine staat stil.',
					),
					array(
						'afzender'  => 'homburg',
						'status'    => '',
						'wie'       => 'Gerard de Boer',
						'wanneer'   => '2026-10-01 14:02',
						'opmerking' => 'Nog niets terug. Ik heb vandaag gerappelleerd en kom er uiterlijk maandag op terug.',
					),
				),
			),
			'GAR-2026-0169' => array(
				'nummer'       => 'GAR-2026-0169',
				'machine'      => 'Bogballe M35W',
				'merk'         => 'Bogballe',
				'serienummer'  => 'BM35-190916-0042',
				'aankoopdatum' => '2019-09-16',
				'hectares'     => '',
				'klacht'       => 'Weegcellen geven na vervanging van de display geen stabiele waarde meer.',
				'onderdelen'   => 'Weegcelset compleet.',
				'ingediend'    => '2026-09-09',
				'status'       => 'goedgekeurd',
				'bijlagen'     => array( 'Foutmelding display.jpg' ),
				'verloop'      => array(
					array(
						'afzender'  => 'dealer',
						'status'    => 'ingediend',
						'wie'       => 'Marijn van den Akker',
						'wanneer'   => '2026-09-09 10:22',
						'opmerking' => '',
					),
					array(
						'afzender'  => 'homburg',
						'status'    => 'in_behandeling',
						'wie'       => 'Gerard de Boer',
						'wanneer'   => '2026-09-09 15:48',
						'opmerking' => '',
					),
					array(
						'afzender'  => 'homburg',
						'status'    => 'goedgekeurd',
						'wie'       => 'Gerard de Boer',
						'wanneer'   => '2026-09-15 16:02',
						'opmerking' => 'Goedgekeurd. De weegcelset is vandaag verzonden; retourneer de oude set met het bijgevoegde retourlabel.',
					),
				),
			),
			'GAR-2026-0151' => array(
				'nummer'       => 'GAR-2026-0151',
				'machine'      => 'Garford Robocrop',
				'merk'         => 'Garford',
				'serienummer'  => 'GRC-200611-0863',
				'aankoopdatum' => '2020-06-11',
				'hectares'     => '2.310',
				'klacht'       => 'Camera-unit valt uit bij fel tegenlicht.',
				'onderdelen'   => 'Camera-unit.',
				'ingediend'    => '2026-08-26',
				'status'       => 'afgewezen_fabrikant',
				'bijlagen'     => array(),
				'verloop'      => array(
					array(
						'afzender'  => 'dealer',
						'status'    => 'ingediend',
						'wie'       => 'Marijn van den Akker',
						'wanneer'   => '2026-08-26 08:15',
						'opmerking' => '',
					),
					array(
						'afzender'  => 'homburg',
						'status'    => 'bij_fabrikant',
						'wie'       => 'Gerard de Boer',
						'wanneer'   => '2026-08-27 13:40',
						'opmerking' => 'Voorgelegd aan Garford.',
					),
					array(
						'afzender'  => 'homburg',
						'status'    => 'afgewezen_fabrikant',
						'wie'       => 'Gerard de Boer',
						'wanneer'   => '2026-09-04 09:55',
						'opmerking' => 'Garford wijst af: de machine is buiten de garantietermijn van 60 maanden. Wel een coulanceprijs voor de camera-unit beschikbaar — ik bel u daarover.',
					),
				),
			),
		);
	}

	/**
	 * Haalt één claim op voor de ingelogde dealer.
	 *
	 * De controle "hoort deze claim bij déze dealer" zit hier bewust nu al
	 * in, vóór er echte data is. Claimnummers lopen op, dus zonder deze
	 * controle zou een dealer de claims van een ander kunnen inzien door het
	 * nummer in de URL te veranderen — exact de fout die bij de downloads
	 * ook gemaakt bleek (zie HDP_Downloads_CPT::mag_merk_zien()). Als de
	 * koppeling met de claimadministratie er komt, wordt dit de enige plek
	 * waar die vraag beantwoord wordt.
	 *
	 * @return array|null De claim, of null als die niet bestaat of niet van
	 *                    deze dealer is.
	 */
	public static function claim_voor_dealer( $nummer, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();

		if ( ! $user_id || ! HDP_Roles::mag_portaal_zien( $user_id ) ) {
			return null;
		}

		$claims = self::voorbeeldclaims();

		return isset( $claims[ $nummer ] ) ? $claims[ $nummer ] : null;
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
	 * van beide kanten, in volgorde. Een statuswijziging mét opmerking staat
	 * er dus ook in — dat is wat "overal een opmerking kunnen achterlaten"
	 * betekent: de toelichting hangt aan de stap waar hij over gaat, en komt
	 * tegelijk in het gesprek terecht.
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
}
