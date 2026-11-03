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
}
