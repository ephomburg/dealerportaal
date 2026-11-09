<?php

/**
 * Test de trainingen (HDP_Trainingen).
 *
 * Wat hier fout kan gaan: iemand meldt zich aan voor een volle of gesloten
 * training, of een training van een merk dat hij niet heeft; een verplicht veld
 * blijft leeg; dezelfde persoon telt twee keer; of de mail gaat niet of in de
 * verkeerde taal naar het team.
 *
 * @group hdp-trainingen
 */
class Trainingen_Test extends WP_UnitTestCase {

	private $dealer;
	private $mails = array();

	public function setUp(): void {
		parent::setUp();
		HDP_Roles::activate();
		HDP_Trainingen::controleer_tabel();

		$this->dealer = self::factory()->user->create(
			array(
				'role'         => 'dealer',
				'display_name' => 'Jan Dealer',
				'user_email'   => 'jan@dealer.test',
			)
		);
		update_user_meta( $this->dealer, 'hdp_goedgekeurd', '1' );
		wp_set_current_user( $this->dealer );

		update_option( HDP_Settings::OPTION, array( 'contact_email' => 'team@homburg.test' ) );

		$this->mails = array();
		add_filter( 'pre_wp_mail', array( $this, 'vang_mail' ), 10, 2 );
	}

	public function tearDown(): void {
		// De database gaat na elke test terug naar de beginstand; een TRUNCATE zou dat doorbreken.
		remove_filter( 'pre_wp_mail', array( $this, 'vang_mail' ) );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function vang_mail( $niets, $mail ) {
		$this->mails[] = $mail;
		return true;
	}

	private function maak_training( $meta = array(), $velden = array() ) {
		$id = self::factory()->post->create(
			array(
				'post_type'    => HDP_Trainingen::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => 'Service Väderstad',
				'post_content' => 'Uitleg over de training.',
			)
		);
		foreach ( $meta + array( HDP_Trainingen::META_DATUM => gmdate( 'Y-m-d', time() + 30 * DAY_IN_SECONDS ) ) as $sleutel => $waarde ) {
			update_post_meta( $id, $sleutel, $waarde );
		}
		update_post_meta( $id, HDP_Trainingen::META_VELDEN, wp_slash( wp_json_encode( $velden ) ) );
		return $id;
	}

	private function veld( $id, $soort = 'tekst', $verplicht = true, $opties = array() ) {
		return array(
			'id'        => $id,
			'label'     => 'Vraag ' . $id,
			'label_fr'  => 'Question ' . $id,
			'soort'     => $soort,
			'verplicht' => $verplicht,
			'opties'    => $opties,
		);
	}

	public function test_aanmelding_bewaart_vraag_en_antwoord() {
		$training = $this->maak_training( array(), array( $this->veld( 'tel', 'telefoon' ) ) );

		$id = HDP_Trainingen::meld_aan(
			$training,
			array(
				'naam'     => 'Piet Monteur',
				'email'    => 'piet@dealer.test',
				'veld_tel' => '0612345678',
			)
		);

		$this->assertIsInt( $id );
		$rij = HDP_Trainingen::aanmeldingen( $training )[0];
		$this->assertSame( 'Piet Monteur', $rij['deelnemer_naam'] );
		$this->assertSame( 'Jan Dealer', $rij['dealer_naam'] );
		$this->assertSame( 'Vraag tel', $rij['antwoorden'][0]['label'] );
		$this->assertSame( '0612345678', $rij['antwoorden'][0]['waarde'] );
		$this->assertSame( 1, HDP_Trainingen::aantal_aangemeld( $training ) );
	}

	public function test_bevestiging_gaat_naar_deelnemer_met_dealer_in_cc_en_teammail_naar_team() {
		$training = $this->maak_training();
		HDP_Trainingen::meld_aan(
			$training,
			array(
				'naam'  => 'Piet',
				'email' => 'piet@dealer.test',
			)
		);

		$this->assertCount( 2, $this->mails );
		$this->assertSame( 'piet@dealer.test', $this->mails[0]['to'] );
		$this->assertContains( 'Cc: jan@dealer.test', $this->mails[0]['headers'] );
		$this->assertStringContainsString( 'Service Väderstad', $this->mails[0]['message'] );
		$this->assertSame( 'team@homburg.test', $this->mails[1]['to'] );
		$this->assertContains( 'Reply-To: piet@dealer.test', $this->mails[1]['headers'] );
	}

	public function test_titel_met_streepje_en_ampersand_blijft_leesbaar() {
		$training = $this->maak_training();
		wp_update_post(
			array(
				'ID'         => $training,
				'post_title' => 'Service - onderhoud & afstellen',
			)
		);
		HDP_Trainingen::meld_aan(
			$training,
			array(
				'naam'  => 'Piet',
				'email' => 'piet@dealer.test',
			)
		);

		$this->assertSame( 'Service - onderhoud & afstellen', HDP_Trainingen::titel( $training ) );
		$this->assertStringContainsString( 'Service - onderhoud & afstellen', $this->mails[0]['subject'] );
		$this->assertStringContainsString( 'Service - onderhoud & afstellen', $this->mails[1]['message'] );
		$this->assertSame( 'Service - onderhoud & afstellen', HDP_Trainingen::aanmeldingen( $training )[0]['training_titel'] );
	}

	public function test_eigen_trainingsadres_gaat_voor_op_algemeen_adres() {
		update_option(
			HDP_Settings::OPTION,
			array(
				'contact_email'  => 'team@homburg.test',
				'training_email' => 'training@homburg.test',
			)
		);
		$this->assertSame( 'training@homburg.test', HDP_Trainingen::team_email() );
	}

	public function test_franstalige_krijgt_franse_bevestiging_en_team_nederlands() {
		$training = $this->maak_training( array( HDP_Trainingen::META_TITEL_FR => 'Formation Väderstad' ), array( $this->veld( 'a' ) ) );
		$_COOKIE[ HDP_I18N::COOKIE ] = 'fr';
		HDP_Trainingen::meld_aan(
			$training,
			array(
				'naam'   => 'Luc',
				'email'  => 'luc@dealer.test',
				'veld_a' => 'antwoord',
			)
		);
		unset( $_COOKIE[ HDP_I18N::COOKIE ] );

		$this->assertStringContainsString( 'Formation Väderstad', $this->mails[0]['subject'] );
		$this->assertStringContainsString( 'Nieuwe aanmelding', $this->mails[1]['message'] );
		$this->assertStringContainsString( 'Vraag a: antwoord', $this->mails[1]['message'] );
		$this->assertSame( 'fr', HDP_Trainingen::aanmeldingen( $training )[0]['taal'] );
	}

	public function test_verplichte_velden_en_ongeldige_invoer_worden_geweigerd() {
		$training = $this->maak_training(
			array(),
			array(
				$this->veld( 'tekst' ),
				$this->veld( 'getal', 'getal' ),
				$this->veld( 'keuze', 'keuze', true, array( 'Vegetarisch | Végétarien' ) ),
				$this->veld( 'akkoord', 'vinkje' ),
			)
		);

		$fouten = HDP_Trainingen::meld_aan(
			$training,
			array(
				'naam'       => '',
				'email'      => 'geen-mail',
				'veld_getal' => 'veel',
				'veld_keuze' => 'Verzonnen',
			)
		);

		$this->assertSame( 'training_fout_verplicht', $fouten['naam'] );
		$this->assertSame( 'training_fout_email', $fouten['email'] );
		$this->assertSame( 'training_fout_verplicht', $fouten['veld_tekst'] );
		$this->assertSame( 'training_fout_getal', $fouten['veld_getal'] );
		$this->assertSame( 'training_fout_verplicht', $fouten['veld_keuze'] );
		$this->assertSame( 'training_fout_verplicht', $fouten['veld_akkoord'] );
		$this->assertSame( 0, HDP_Trainingen::aantal_aangemeld( $training ) );
		$this->assertCount( 0, $this->mails );
	}

	public function test_keuze_bewaart_de_nederlandse_tekst() {
		$veld = $this->veld( 'dieet', 'keuze', true, array( 'Vegetarisch | Végétarien', 'Geen' ) );
		$this->assertSame(
			array(
				'Vegetarisch' => 'Vegetarisch',
				'Geen'        => 'Geen',
			),
			HDP_Trainingen::opties( $veld )
		);

		$training = $this->maak_training( array(), array( $veld ) );
		$this->assertIsInt(
			HDP_Trainingen::meld_aan(
				$training,
				array(
					'naam'       => 'Piet',
					'email'      => 'piet@dealer.test',
					'veld_dieet' => 'Vegetarisch',
				)
			)
		);
	}

	public function test_zelfde_persoon_kan_niet_twee_keer_maar_een_collega_wel() {
		$training = $this->maak_training();
		$piet     = array(
			'naam'  => 'Piet',
			'email' => 'piet@dealer.test',
		);

		$this->assertIsInt( HDP_Trainingen::meld_aan( $training, $piet ) );
		$this->assertSame( array( 'email' => 'training_fout_dubbel' ), HDP_Trainingen::meld_aan( $training, array( 'naam' => 'Piet', 'email' => 'PIET@dealer.test' ) ) );
		$this->assertIsInt( HDP_Trainingen::meld_aan( $training, array( 'naam' => 'Kees', 'email' => 'kees@dealer.test' ) ) );
	}

	public function test_volle_training_weigert_tot_er_een_plaats_vrijkomt() {
		$training = $this->maak_training( array( HDP_Trainingen::META_PLAATSEN => 1 ) );
		$eerste   = HDP_Trainingen::meld_aan( $training, array( 'naam' => 'Piet', 'email' => 'piet@dealer.test' ) );

		$this->assertSame( 'vol', HDP_Trainingen::stand( $training ) );
		$kees = array( 'naam' => 'Kees', 'email' => 'kees@dealer.test' );
		$this->assertSame( array( 'algemeen' => 'training_fout_gesloten' ), HDP_Trainingen::meld_aan( $training, $kees ) );

		HDP_Trainingen::annuleer( $eerste );
		$this->assertSame( 'open', HDP_Trainingen::stand( $training ) );
		$this->assertIsInt( HDP_Trainingen::meld_aan( $training, $kees ) );
	}

	public function test_na_de_sluitdatum_kan_niet_meer_en_voorbij_verdwijnt() {
		$gisteren = gmdate( 'Y-m-d', time() - 2 * DAY_IN_SECONDS );

		$gesloten = $this->maak_training( array( HDP_Trainingen::META_SLUIT => $gisteren ) );
		$this->assertSame( 'gesloten', HDP_Trainingen::stand( $gesloten ) );
		$this->assertContains( $gesloten, HDP_Trainingen::komende() );

		$voorbij = $this->maak_training( array( HDP_Trainingen::META_DATUM => $gisteren ) );
		$this->assertSame( 'voorbij', HDP_Trainingen::stand( $voorbij ) );
		$this->assertNotContains( $voorbij, HDP_Trainingen::komende() );

		// Bij meer dagen telt de laatste dag.
		$loopt = $this->maak_training(
			array(
				HDP_Trainingen::META_DATUM     => $gisteren,
				HDP_Trainingen::META_EINDDATUM => gmdate( 'Y-m-d', time() + 2 * DAY_IN_SECONDS ),
			)
		);
		$this->assertContains( $loopt, HDP_Trainingen::komende() );
	}

	public function test_training_met_ander_merk_is_niet_te_zien_en_niet_te_boeken() {
		update_user_meta( $this->dealer, 'hdp_merken', 'Bogballe' );
		$training = $this->maak_training( array( HDP_Trainingen::META_MERKEN => 'HARDI' ) );

		$this->assertNotContains( $training, HDP_Trainingen::komende() );
		$this->assertSame( array( 'algemeen' => 'training_fout_geen_toegang' ), HDP_Trainingen::meld_aan( $training, array( 'naam' => 'Piet', 'email' => 'piet@dealer.test' ) ) );

		update_user_meta( $this->dealer, 'hdp_merken', 'Bogballe, HARDI' );
		$this->assertContains( $training, HDP_Trainingen::komende() );
	}

	public function test_niet_goedgekeurde_dealer_kan_zich_niet_aanmelden() {
		delete_user_meta( $this->dealer, 'hdp_goedgekeurd' );
		$training = $this->maak_training();
		$this->assertSame( array( 'algemeen' => 'training_fout_geen_toegang' ), HDP_Trainingen::meld_aan( $training, array( 'naam' => 'Piet', 'email' => 'piet@dealer.test' ) ) );
	}

	public function test_gegevenspaneel_toont_datum_plaatsen_en_aanmeldknop() {
		$training = $this->maak_training(
			array(
				HDP_Trainingen::META_DATUM    => '2099-12-13',
				HDP_Trainingen::META_LOCATIE  => 'Leeuwarden',
				HDP_Trainingen::META_KOSTEN   => '€ 95',
				HDP_Trainingen::META_PLAATSEN => 2,
			)
		);
		HDP_Trainingen::meld_aan( $training, array( 'naam' => 'Piet', 'email' => 'piet@dealer.test' ) );

		$_GET['training'] = $training;
		$html             = HDP_Trainingen_Render::render_pagina( array() );
		$this->assertStringContainsString( 'hdp-training-blad', $html );
		$this->assertStringContainsString( 'Leeuwarden', $html );
		$this->assertStringContainsString( HDP_I18N::t( 'training_plaats_over' ), $html );
		$this->assertStringContainsString( sprintf( HDP_I18N::t( 'training_plaatsen_bezet' ), 1, 2 ), $html );
		$this->assertSame( 1, substr_count( $html, 'is-bezet' ) );
		$this->assertStringContainsString( 'href="#aanmelden"', $html );
		$this->assertSame( 1, substr_count( $html, '<h1' ) );

		// Vol: geen knop en geen formulier meer.
		HDP_Trainingen::meld_aan( $training, array( 'naam' => 'Kees', 'email' => 'kees@dealer.test' ) );
		$html = HDP_Trainingen_Render::render_pagina( array() );
		unset( $_GET['training'] );
		$this->assertStringNotContainsString( 'href="#aanmelden"', $html );
		$this->assertStringNotContainsString( '<form', $html );
		$this->assertStringContainsString( HDP_I18N::t( 'training_stand_vol' ), $html );
	}

	public function test_zonder_datum_staat_er_datum_volgt_zonder_kalenderblad() {
		$training = $this->maak_training();
		delete_post_meta( $training, HDP_Trainingen::META_DATUM );

		$_GET['training'] = $training;
		$html             = HDP_Trainingen_Render::render_pagina( array() );
		unset( $_GET['training'] );

		$this->assertStringContainsString( HDP_I18N::t( 'training_datum_volgt' ), $html );
		$this->assertStringNotContainsString( 'hdp-training-blad', $html );
		$this->assertStringNotContainsString( 'hdp-training-stoelen', $html );
	}

	public function test_tijden_uit_vrije_tekst() {
		$this->assertSame( array( '09:00', '16:30' ), HDP_Trainingen::tijden( '09:00 - 16:30' ) );
		$this->assertSame( array( '09:00', '16:00' ), HDP_Trainingen::tijden( '0900 – 1600' ) );
		$this->assertSame( array( '09:00', '17:00' ), HDP_Trainingen::tijden( '9-17 uur' ) );
		$this->assertSame( array( '09:30', '12:15' ), HDP_Trainingen::tijden( '9.30 tot 12.15' ) );
		$this->assertNull( HDP_Trainingen::tijden( 'hele dag' ) );
		$this->assertNull( HDP_Trainingen::tijden( '09:00' ) );
		$this->assertNull( HDP_Trainingen::tijden( '17:00 - 09:00' ) );
		$this->assertNull( HDP_Trainingen::tijden( '25:00 - 26:00' ) );
	}

	private function ontvouw( $ics ) {
		return str_replace( "\r\n ", '', $ics );
	}

	public function test_agenda_met_tijden_in_utc_en_veilige_tekst() {
		update_option( 'timezone_string', 'Europe/Amsterdam' );
		$training = $this->maak_training(
			array(
				HDP_Trainingen::META_DATUM   => '2030-12-13',
				HDP_Trainingen::META_TIJD    => '09:00 - 16:30',
				HDP_Trainingen::META_LOCATIE => 'Stiens, Friesland',
			)
		);
		wp_update_post( array( 'ID' => $training, 'post_content' => str_repeat( 'Lange uitleg; met tekens, ', 12 ) ) );

		$ics = HDP_Trainingen::agenda_bestand( $training );

		$this->assertStringStartsWith( "BEGIN:VCALENDAR\r\n", $ics );
		$this->assertStringEndsWith( "END:VCALENDAR\r\n", $ics );
		$this->assertStringContainsString( 'DTSTART:20301213T080000Z', $ics );
		$this->assertStringContainsString( 'DTEND:20301213T153000Z', $ics );
		$this->assertStringContainsString( 'LOCATION:Stiens\, Friesland', $this->ontvouw( $ics ) );
		$this->assertStringContainsString( 'Lange uitleg\; met tekens\,', $this->ontvouw( $ics ) );
		foreach ( explode( "\r\n", $ics ) as $regel ) {
			$this->assertLessThanOrEqual( 75, strlen( $regel ) );
		}
	}

	public function test_agenda_meer_dagen_of_onduidelijke_tijd_wordt_hele_dagen() {
		$training = $this->maak_training(
			array(
				HDP_Trainingen::META_DATUM     => '2030-11-19',
				HDP_Trainingen::META_EINDDATUM => '2030-11-26',
				HDP_Trainingen::META_TIJD      => '09:00 - 16:00',
			)
		);
		$ics      = HDP_Trainingen::agenda_bestand( $training );
		$this->assertStringContainsString( 'DTSTART;VALUE=DATE:20301119', $ics );
		$this->assertStringContainsString( 'DTEND;VALUE=DATE:20301127', $ics );
		$this->assertStringContainsString( '09:00 - 16:00', $this->ontvouw( $ics ) );

		$training = $this->maak_training(
			array(
				HDP_Trainingen::META_DATUM => '2030-11-19',
				HDP_Trainingen::META_TIJD  => 'in overleg',
			)
		);
		$this->assertStringContainsString( 'DTEND;VALUE=DATE:20301120', HDP_Trainingen::agenda_bestand( $training ) );
	}

	public function test_zonder_datum_geen_agenda() {
		$training = $this->maak_training();
		delete_post_meta( $training, HDP_Trainingen::META_DATUM );
		$this->assertSame( '', HDP_Trainingen::agenda_bestand( $training ) );
	}

	public function test_bevestiging_heeft_agendabijlage_en_ruimt_die_op() {
		$training = $this->maak_training( array( HDP_Trainingen::META_DATUM => '2030-12-13' ) );
		$inhoud   = '';
		$pad      = '';
		add_filter(
			'pre_wp_mail',
			static function ( $niets, $mail ) use ( &$inhoud, &$pad ) {
				if ( ! empty( $mail['attachments'] ) ) {
					$pad    = $mail['attachments'][0];
					$inhoud = file_get_contents( $pad );
				}
				return $niets;
			},
			5,
			2
		);

		HDP_Trainingen::meld_aan( $training, array( 'naam' => 'Piet', 'email' => 'piet@dealer.test' ) );

		$this->assertStringEndsWith( '.ics', $pad );
		$this->assertStringContainsString( 'BEGIN:VEVENT', $inhoud );
		$this->assertFileDoesNotExist( $pad );
		$this->assertStringContainsString( HDP_I18N::t( 'training_mail_agenda' ), $this->mails[0]['message'] );
		$this->assertEmpty( $this->mails[1]['attachments'] );
	}

	public function test_agendaknop_alleen_na_aanmelden_en_met_datum() {
		$training         = $this->maak_training();
		$_GET['training'] = $training;
		$this->assertStringNotContainsString( 'hdp-training-agenda', HDP_Trainingen_Render::render_pagina( array() ) );

		HDP_Trainingen::meld_aan( $training, array( 'naam' => 'Piet', 'email' => 'piet@dealer.test' ) );
		$this->assertStringContainsString( 'hdp-training-agenda', HDP_Trainingen_Render::render_pagina( array() ) );

		delete_post_meta( $training, HDP_Trainingen::META_DATUM );
		$this->assertStringNotContainsString( 'hdp-training-agenda', HDP_Trainingen_Render::render_pagina( array() ) );
		unset( $_GET['training'] );
	}

	public function test_tijd_uit_formulier_kan_alleen_in_de_vaste_notatie() {
		$this->assertSame( '09:00 - 16:00', HDP_Trainingen::tijd_uit_formulier( '09:00', '16:00' ) );
		$this->assertSame( '', HDP_Trainingen::tijd_uit_formulier( '', '' ) );
		$this->assertNull( HDP_Trainingen::tijd_uit_formulier( '09:00', '' ) );
		$this->assertNull( HDP_Trainingen::tijd_uit_formulier( '', '16:00' ) );
		$this->assertNull( HDP_Trainingen::tijd_uit_formulier( '16:00', '09:00' ) );
		$this->assertNull( HDP_Trainingen::tijd_uit_formulier( '0900', '1600' ) );
		$this->assertNull( HDP_Trainingen::tijd_uit_formulier( '25:00', '26:00' ) );
	}

	public function test_oude_tijd_wordt_netjes_getoond() {
		$training = $this->maak_training( array( HDP_Trainingen::META_TIJD => '0900 - 1600' ) );
		$this->assertSame( '09:00 - 16:00', HDP_Trainingen::tijd( $training ) );

		update_post_meta( $training, HDP_Trainingen::META_TIJD, 'hele dag' );
		$this->assertSame( 'hele dag', HDP_Trainingen::tijd( $training ) );
	}

	public function test_overzicht_dealer_verdeelt_en_laat_alleen_eigen_aanmeldingen_zien() {
		$gisteren = gmdate( 'Y-m-d', time() - 2 * DAY_IN_SECONDS );
		$later    = $this->maak_training( array( HDP_Trainingen::META_DATUM => '2099-05-01' ) );
		$eerder   = $this->maak_training( array( HDP_Trainingen::META_DATUM => '2099-01-01' ) );
		$voorbij  = $this->maak_training();
		$weg      = $this->maak_training();
		$piet     = array( 'naam' => 'Piet', 'email' => 'piet@dealer.test' );

		$eerste = HDP_Trainingen::meld_aan( $later, $piet );
		HDP_Trainingen::meld_aan( $later, array( 'naam' => 'Kees', 'email' => 'kees@dealer.test' ) );
		HDP_Trainingen::meld_aan( $eerder, $piet );
		HDP_Trainingen::meld_aan( $voorbij, $piet );
		HDP_Trainingen::meld_aan( $weg, $piet );
		HDP_Trainingen::annuleer( $eerste );
		update_post_meta( $voorbij, HDP_Trainingen::META_DATUM, $gisteren );
		wp_delete_post( $weg, true );

		$ander = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $ander, 'hdp_goedgekeurd', '1' );
		HDP_Trainingen::meld_aan( $later, array( 'naam' => 'Vreemde', 'email' => 'vreemde@dealer.test' ), $ander );

		$overzicht = HDP_Trainingen::overzicht_dealer( $this->dealer );

		$this->assertSame( array( $eerder, $later ), wp_list_pluck( $overzicht['komend'], 'id' ) );
		$namen = wp_list_pluck( $overzicht['komend'][1]['rijen'], 'deelnemer_naam' );
		$this->assertSame( array( 'Piet', 'Kees' ), $namen, 'Een geannuleerde aanmelding blijft zichtbaar; die van een andere dealer niet.' );
		$this->assertSame( HDP_Trainingen::STATUS_GEANNULEERD, $overzicht['komend'][1]['rijen'][0]['status'] );

		$this->assertSame( array( $voorbij, $weg ), wp_list_pluck( $overzicht['eerder'], 'id' ) );
		$this->assertSame( 'Service Väderstad', $overzicht['eerder'][1]['titel'], 'Een verwijderde training houdt haar titel.' );
		$this->assertFalse( $overzicht['eerder'][1]['zichtbaar'] );
		$this->assertSame( array( 'komend' => array(), 'eerder' => array() ), HDP_Trainingen::overzicht_dealer( self::factory()->user->create() ) );
	}

	public function test_mijn_trainingen_in_het_account() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			$this->markTestSkipped( 'WooCommerce is niet geladen.' );
		}
		$map = dirname( __DIR__ ) . '/app/public/wp-content/themes/homburg-dealerportaal/includes/woocommerce/';
		require_once $map . 'bestellen.php';
		require_once $map . 'mijn-trainingen.php';

		$menu = homburg_wc_mijn_trainingen_menu( array( 'mijn-merken' => 'Mijn merken', 'customer-logout' => 'Uitloggen' ) );
		$this->assertSame( array( 'mijn-merken', 'mijn-trainingen', 'customer-logout' ), array_keys( $menu ) );

		ob_start();
		homburg_wc_mijn_trainingen_inhoud();
		$this->assertStringContainsString( HDP_I18N::t( 'training_mijn_leeg' ), ob_get_clean() );

		$training = $this->maak_training( array( HDP_Trainingen::META_DATUM => '2099-05-01' ) );
		$weg      = HDP_Trainingen::meld_aan( $training, array( 'naam' => 'Piet', 'email' => 'piet@dealer.test' ) );

		ob_start();
		homburg_wc_mijn_trainingen_inhoud();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'Service Väderstad', $html );
		$this->assertStringContainsString( 'Piet', $html );
		$this->assertStringContainsString( 'agenda=1', $html );

		// Niemand meer aangemeld: dan valt er niets meer in de agenda te zetten.
		HDP_Trainingen::annuleer( $weg );
		ob_start();
		homburg_wc_mijn_trainingen_inhoud();
		$html = ob_get_clean();
		$this->assertStringContainsString( HDP_I18N::t( 'training_mijn_geannuleerd' ), $html );
		$this->assertStringNotContainsString( 'agenda=1', $html );
	}

	public function test_concept_is_niet_te_zien() {
		$training = $this->maak_training();
		wp_update_post( array( 'ID' => $training, 'post_status' => 'draft' ) );
		$this->assertFalse( HDP_Trainingen::mag_zien( $training ) );
	}

	public function test_oud_antwoord_blijft_als_de_vraag_wordt_gewijzigd() {
		$training = $this->maak_training( array(), array( $this->veld( 'a' ) ) );
		HDP_Trainingen::meld_aan( $training, array( 'naam' => 'Piet', 'email' => 'piet@dealer.test', 'veld_a' => 'antwoord' ) );

		update_post_meta( $training, HDP_Trainingen::META_VELDEN, wp_json_encode( array() ) );

		$this->assertSame( 'Vraag a', HDP_Trainingen::aanmeldingen( $training )[0]['antwoorden'][0]['label'] );
	}

	public function test_velden_uit_formulier_houdt_nummer_en_slaat_lege_regel_over() {
		$velden = HDP_Trainingen::velden_uit_formulier(
			array(
				'hdp_veld_id'        => array( 'bestaand', '', '' ),
				'hdp_veld_label'     => array( 'Maat', '', 'Dieet' ),
				'hdp_veld_label_fr'  => array( 'Taille', '', '' ),
				'hdp_veld_soort'     => array( 'tekst', 'tekst', 'keuze' ),
				'hdp_veld_opties'    => array( 'genegeerd', '', "Geen\n\nVegetarisch | Végétarien" ),
				'hdp_veld_verplicht' => array( '1', '0', '0' ),
			)
		);

		$this->assertCount( 2, $velden );
		$this->assertSame( 'bestaand', $velden[0]['id'] );
		$this->assertTrue( $velden[0]['verplicht'] );
		$this->assertSame( array(), $velden[0]['opties'] );
		$this->assertNotSame( '', $velden[1]['id'] );
		$this->assertSame( array( 'Geen', 'Vegetarisch | Végétarien' ), $velden[1]['opties'] );
	}

	public function test_csv_cel_maakt_formules_onschadelijk() {
		$this->assertSame( '"\'=SOM(A1)"', HDP_Trainingen::csv_cel( '=SOM(A1)' ) );
		$this->assertSame( '"Piet ""de"" Vries"', HDP_Trainingen::csv_cel( 'Piet "de" Vries' ) );
	}

	public function test_nieuwe_training_komt_in_nieuw_voor_jou() {
		$training = $this->maak_training();
		$regels   = HDP_Trainingen::nieuwe( 0, 7 );
		$this->assertSame( 'Service Väderstad', $regels[0]['titel'] );
		$this->assertStringContainsString( 'training=' . $training, $regels[0]['url'] );

		// Weggeklikt: telt niet meer.
		$this->assertSame( array(), HDP_Trainingen::nieuwe( time() + 60, 7 ) );
	}

	public function test_pagina_toont_alleen_ingelogde_dealer_de_training_en_formulier() {
		$training = $this->maak_training( array(), array( $this->veld( 'a' ) ) );

		$_GET['training'] = $training;
		$html             = HDP_Trainingen_Render::render_pagina( array() );
		unset( $_GET['training'] );

		$this->assertStringContainsString( 'Uitleg over de training.', $html );
		$this->assertStringContainsString( 'name="veld_a"', $html );
		$this->assertStringContainsString( 'value="Jan Dealer"', $html );

		wp_set_current_user( 0 );
		$this->assertStringNotContainsString( 'Uitleg over de training.', do_blocks( '<!-- wp:homburg/trainingen-pagina /-->' ) );
	}

	/* === Eerder gegeven ================================================= */

	private function dagen_geleden( $dagen ) {
		return gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' UTC' ) - $dagen * DAY_IN_SECONDS );
	}

	/** Een geweeste training met deelnemers: eerst aanmelden, daarna de datum terugzetten. */
	private function geweest( $dagen, $deelnemers = array(), $meta = array() ) {
		$id = $this->maak_training( $meta );
		foreach ( $deelnemers as $naam ) {
			HDP_Trainingen::meld_aan( $id, array( 'naam' => $naam, 'email' => strtolower( $naam ) . '@dealer.test' ) );
		}
		update_post_meta( $id, HDP_Trainingen::META_DATUM, $this->dagen_geleden( $dagen ) );
		return $id;
	}

	public function test_eerdere_laatste_eerst_en_standaard_twee_jaar_terug() {
		$recent  = $this->geweest( 10 );
		$ouder   = $this->geweest( 100 );
		$oud     = $this->geweest( 800 );
		$komend  = $this->maak_training();
		$concept = $this->geweest( 5 );
		wp_update_post( array( 'ID' => $concept, 'post_status' => 'draft' ) );
		$zonder = $this->maak_training();
		delete_post_meta( $zonder, HDP_Trainingen::META_DATUM );

		$this->assertSame( array( $recent, $ouder ), HDP_Trainingen::eerdere() );
		$this->assertSame( array( $recent, $ouder, $oud ), HDP_Trainingen::eerdere( 0, true ) );
		$this->assertNotContains( $komend, HDP_Trainingen::eerdere( 0, true ) );
	}

	public function test_meerdaagse_training_telt_vanaf_de_laatste_dag() {
		$loopt = $this->maak_training(
			array(
				HDP_Trainingen::META_DATUM     => $this->dagen_geleden( 1 ),
				HDP_Trainingen::META_EINDDATUM => gmdate( 'Y-m-d', time() + 2 * DAY_IN_SECONDS ),
			)
		);
		$this->assertNotContains( $loopt, HDP_Trainingen::eerdere() );
	}

	public function test_eerdere_volgt_de_merken_van_de_dealer() {
		update_user_meta( $this->dealer, 'hdp_merken', 'Väderstad' );
		$eigen  = $this->geweest( 10, array(), array( HDP_Trainingen::META_MERKEN => 'Väderstad' ) );
		$vreemd = $this->geweest( 20, array(), array( HDP_Trainingen::META_MERKEN => 'Bogballe' ) );

		$this->assertSame( array( $eigen ), HDP_Trainingen::eerdere() );
		$this->assertNotContains( $vreemd, HDP_Trainingen::eerdere( 0, true ) );
	}

	public function test_volgende_van_eerst_nieuwe_editie_dan_vervolg() {
		$geweest = $this->geweest( 30 );
		$this->assertSame( 0, HDP_Trainingen::volgende_van( $geweest ) );

		$vervolg = $this->maak_training( array( HDP_Trainingen::META_VERVOLG_OP => $geweest ) );
		wp_update_post( array( 'ID' => $vervolg, 'post_title' => 'Service Väderstad deel 2' ) );
		$this->assertSame( $vervolg, HDP_Trainingen::volgende_van( $geweest ) );

		$editie = $this->maak_training( array( HDP_Trainingen::META_DATUM => '2099-01-01' ) );
		$this->assertSame( $editie, HDP_Trainingen::volgende_van( $geweest ), 'Dezelfde training opnieuw gaat voor het vervolg.' );
	}

	public function test_deden_mee_zonder_afgemelden_en_afwezigen() {
		global $wpdb;
		$training = $this->maak_training();
		$piet     = HDP_Trainingen::meld_aan( $training, array( 'naam' => 'Piet', 'email' => 'piet@dealer.test' ) );
		$kees     = HDP_Trainingen::meld_aan( $training, array( 'naam' => 'Kees', 'email' => 'kees@dealer.test' ) );
		HDP_Trainingen::meld_aan( $training, array( 'naam' => 'Anna', 'email' => 'anna@dealer.test' ) );
		HDP_Trainingen::annuleer( $piet );
		$wpdb->update( HDP_Trainingen::tabel(), array( 'aanwezig' => 0 ), array( 'id' => $kees ) );
		update_post_meta( $training, HDP_Trainingen::META_DATUM, $this->dagen_geleden( 3 ) );

		$this->assertSame( array( 'Anna' ), wp_list_pluck( HDP_Trainingen::deden_mee( $training ), 'deelnemer_naam' ) );
	}

	public function test_overzicht_toont_eerder_gegeven() {
		$html = HDP_Trainingen_Render::render_pagina( array() );
		$this->assertStringNotContainsString( HDP_I18N::t( 'training_eerder_titel' ), $html, 'Zonder geweeste trainingen geen leeg blok.' );

		$geweest = $this->geweest( 10, array( 'Piet' ), array( HDP_Trainingen::META_LOCATIE => 'Stiens' ) );
		$this->geweest( 800 );
		$html = HDP_Trainingen_Render::render_pagina( array() );

		$this->assertStringContainsString( HDP_I18N::t( 'training_eerder_titel' ), $html );
		$this->assertStringContainsString( HDP_I18N::t( 'training_leeg' ), $html, 'Er staat nog steeds dat er niets gepland is.' );
		$this->assertStringContainsString( 'Stiens', $html );
		$this->assertStringContainsString( sprintf( HDP_I18N::t( 'training_eerder_erbij' ), 1 ), $html );
		$this->assertStringContainsString( esc_url( HDP_Trainingen::url( $geweest ) ), $html );
		$this->assertSame( 1, substr_count( $html, 'class="hdp-training-eerder-link"' ), 'De oude training staat er standaard niet in.' );
		$this->assertStringContainsString( HDP_I18N::t( 'training_eerder_meer' ), $html );

		$_GET['eerder'] = 'alle';
		$html           = HDP_Trainingen_Render::render_pagina( array() );
		unset( $_GET['eerder'] );
		$this->assertSame( 2, substr_count( $html, 'class="hdp-training-eerder-link"' ) );
		$this->assertStringNotContainsString( HDP_I18N::t( 'training_eerder_meer' ), $html );
	}

	public function test_andere_dealer_ziet_niet_wie_er_van_ons_was() {
		$this->geweest( 10, array( 'Piet' ) );
		$ander = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $ander, 'hdp_goedgekeurd', '1' );
		wp_set_current_user( $ander );

		$html = HDP_Trainingen_Render::render_pagina( array() );
		$this->assertStringContainsString( HDP_I18N::t( 'training_eerder_titel' ), $html );
		$this->assertStringNotContainsString( 'hdp-training-label-aangemeld', $html );
	}

	public function test_detail_van_een_geweeste_training() {
		$geweest = $this->geweest( 10, array( 'Piet' ) );
		$editie  = $this->maak_training( array( HDP_Trainingen::META_DATUM => '2099-03-04' ) );

		$_GET['training'] = $geweest;
		$html             = HDP_Trainingen_Render::render_pagina( array() );
		unset( $_GET['training'] );

		$this->assertStringContainsString( esc_html( sprintf( HDP_I18N::t( 'training_gegeven_op' ), HDP_Trainingen::datumtekst( $geweest ) ) ), $html );
		$this->assertStringNotContainsString( '<form', $html );
		$this->assertStringNotContainsString( HDP_I18N::t( 'training_stand_voorbij_uitleg' ), $html );
		$this->assertStringNotContainsString( HDP_I18N::t( 'training_in_outlook' ), $html );
		$this->assertStringContainsString( HDP_I18N::t( 'training_deden_mee' ), $html );
		$this->assertStringContainsString( 'Piet', $html );
		$this->assertStringContainsString( HDP_I18N::t( 'training_volgende_editie' ), $html );
		$this->assertStringContainsString( esc_url( HDP_Trainingen::url( $editie ) ), $html );
	}

	public function test_geweeste_training_toont_haar_eigen_cijfers() {
		global $wpdb;
		$geweest = $this->geweest( 10 );
		foreach ( array( 9, 8, 7 ) as $i => $cijfer ) {
			$wpdb->insert(
				HDP_Training_Reviews::tabel(),
				array(
					'aanmelding_id' => 900 + $i,
					'training_id'   => $geweest,
					'cijfer'        => $cijfer,
					'reactie'       => 0 === $i ? 'Heel leerzaam.' : '',
					'mag_tonen'     => 1,
					'zichtbaar'     => 1,
					'publieke_naam' => 'Sanne B., monteur',
					'aangemaakt'    => current_time( 'mysql', true ),
				)
			);
		}

		$_GET['training'] = $geweest;
		$html             = HDP_Trainingen_Render::render_pagina( array() );
		unset( $_GET['training'] );
		$this->assertStringContainsString( HDP_I18N::t( 'training_review_sectie_eigen' ), $html );
		$this->assertStringContainsString( sprintf( HDP_I18N::t( 'training_review_eigen' ), 3 ), $html );
		$this->assertStringContainsString( 'Heel leerzaam.', $html );

		$overzicht = HDP_Trainingen_Render::render_pagina( array() );
		$this->assertStringContainsString( 'hdp-training-eerder-cijfer', $overzicht );
		$this->assertStringContainsString( '<b>8,0</b>', $overzicht );
	}
}
