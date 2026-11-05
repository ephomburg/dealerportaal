<?php

/**
 * Test de beoordelingen van trainingen (HDP_Training_Reviews).
 *
 * Wat hier fout kan gaan: mail naar iemand die niet was of al heeft beoordeeld,
 * een link waarmee iemand anders stemt, dubbel stemmen, of een reactie die
 * zichtbaar wordt zonder akkoord van de deelnemer en Homburg.
 *
 * @group hdp-trainingen
 */
class Training_Reviews_Test extends WP_UnitTestCase {

	private $dealer;
	private $mails = array();

	public function setUp(): void {
		parent::setUp();
		HDP_Roles::activate();
		HDP_Trainingen::controleer_tabel();

		$this->dealer = self::factory()->user->create( array( 'role' => 'dealer', 'display_name' => 'Jan Dealer' ) );
		update_user_meta( $this->dealer, 'hdp_goedgekeurd', '1' );
		wp_set_current_user( $this->dealer );
		update_option( HDP_Settings::OPTION, array( 'contact_email' => 'team@homburg.test' ) );

		$this->mails = array();
		add_filter( 'pre_wp_mail', array( $this, 'vang_mail' ), 10, 2 );
	}

	public function tearDown(): void {
		remove_filter( 'pre_wp_mail', array( $this, 'vang_mail' ) );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function vang_mail( $niets, $mail ) {
		$this->mails[] = $mail;
		return true;
	}

	private function datum( $dagen ) {
		return gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' UTC' ) + $dagen * DAY_IN_SECONDS );
	}

	private function training( $titel = 'Basiscursus' ) {
		$id = self::factory()->post->create(
			array(
				'post_type'    => HDP_Trainingen::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $titel,
				'post_content' => 'Uitleg.',
			)
		);
		update_post_meta( $id, HDP_Trainingen::META_DATUM, $this->datum( 30 ) );
		update_post_meta(
			$id,
			HDP_Trainingen::META_VELDEN,
			wp_slash(
				wp_json_encode(
					array(
						array( 'id' => 'functie', 'label' => 'Functie', 'label_fr' => '', 'soort' => 'tekst', 'verplicht' => false, 'opties' => array() ),
					)
				)
			)
		);
		return $id;
	}

	/** Aanmelden kan alleen vooraf; daarna de training in het verleden zetten. */
	private function aanmelding( $training, $naam = 'Piet Monteur', $mail = 'piet@dealer.test' ) {
		$id = HDP_Trainingen::meld_aan(
			$training,
			array(
				'naam'         => $naam,
				'email'        => $mail,
				'veld_functie' => 'Monteur',
			)
		);
		$this->assertIsInt( $id );
		return $id;
	}

	private function afgelopen( $training, $dagen = 1 ) {
		update_post_meta( $training, HDP_Trainingen::META_DATUM, $this->datum( -$dagen ) );
		$this->mails = array();
	}

	private function rij( $id ) {
		return HDP_Training_Reviews::aanmelding_bij_link( $id, HDP_Training_Reviews::sleutel( $id ) );
	}

	public function test_uitnodiging_gaat_de_dag_na_de_training_uit_en_maar_een_keer() {
		$training = $this->training();
		$this->aanmelding( $training );
		$this->mails = array();

		$this->assertSame( 0, HDP_Training_Reviews::verstuur_ronde(), 'Vóór de training geen mail.' );

		$this->afgelopen( $training );
		$this->assertSame( 1, HDP_Training_Reviews::verstuur_ronde() );
		$this->assertSame( 'piet@dealer.test', $this->mails[0]['to'] );
		$this->assertStringContainsString( 'beoordeel=', $this->mails[0]['message'] );
		$this->assertStringContainsString( 'Basiscursus', $this->mails[0]['subject'] );

		$this->assertSame( 0, HDP_Training_Reviews::verstuur_ronde(), 'Niet nog een keer.' );
	}

	public function test_bij_meer_dagen_telt_de_laatste_dag_en_oude_trainingen_krijgen_geen_mail() {
		$training = $this->training();
		$this->aanmelding( $training );
		update_post_meta( $training, HDP_Trainingen::META_DATUM, $this->datum( -3 ) );
		update_post_meta( $training, HDP_Trainingen::META_EINDDATUM, $this->datum( 0 ) );
		$this->assertSame( 0, HDP_Training_Reviews::verstuur_ronde() );

		delete_post_meta( $training, HDP_Trainingen::META_EINDDATUM );
		update_post_meta( $training, HDP_Trainingen::META_DATUM, $this->datum( -( HDP_Training_Reviews::VENSTER_DAGEN + 1 ) ) );
		$this->assertSame( 0, HDP_Training_Reviews::verstuur_ronde() );
	}

	public function test_geen_mail_bij_geannuleerd_afwezig_of_al_beoordeeld() {
		$training = $this->training();
		$a        = $this->aanmelding( $training, 'Een Persoon', 'een@dealer.test' );
		$b        = $this->aanmelding( $training, 'Twee Persoon', 'twee@dealer.test' );
		$c        = $this->aanmelding( $training, 'Drie Persoon', 'drie@dealer.test' );
		HDP_Trainingen::annuleer( $a );
		HDP_Training_Reviews::zet_aanwezig( $b, false );
		$this->afgelopen( $training );
		HDP_Training_Reviews::bewaar( $this->rij( $c ), array( 'cijfer' => 8 ) );
		$this->mails = array();

		$this->assertSame( 0, HDP_Training_Reviews::verstuur_ronde() );
		$this->assertNull( $this->rij( $a ), 'Geannuleerd: link werkt niet.' );
		$this->assertNull( $this->rij( $b ), 'Niet aanwezig: link werkt niet.' );
	}

	public function test_een_herinnering_na_een_week_en_daarna_niets_meer() {
		global $wpdb;
		$training = $this->training();
		$id       = $this->aanmelding( $training );
		$this->afgelopen( $training );
		HDP_Training_Reviews::verstuur_ronde();
		$this->mails = array();

		$this->assertSame( 0, HDP_Training_Reviews::verstuur_ronde(), 'Niet meteen.' );

		$wpdb->update( HDP_Trainingen::tabel(), array( 'review_uitnodiging' => gmdate( 'Y-m-d H:i:s', time() - 8 * DAY_IN_SECONDS ) ), array( 'id' => $id ) );
		$this->assertSame( 1, HDP_Training_Reviews::verstuur_ronde() );
		$this->assertStringContainsString( 'Vorige week', $this->mails[0]['message'] );
		$this->assertSame( 0, HDP_Training_Reviews::verstuur_ronde() );
	}

	public function test_mail_in_de_taal_van_de_aanmelding() {
		$training = $this->training();
		update_post_meta( $training, HDP_Trainingen::META_TITEL_FR, 'Cours de base' );
		$_COOKIE[ HDP_I18N::COOKIE ] = 'fr';
		$this->aanmelding( $training );
		unset( $_COOKIE[ HDP_I18N::COOKIE ] );
		$this->afgelopen( $training );

		HDP_Training_Reviews::verstuur_ronde();

		$this->assertStringContainsString( 'Cours de base', $this->mails[0]['subject'] );
		$this->assertStringContainsString( 'Bonjour', $this->mails[0]['message'] );
	}

	public function test_link_alleen_geldig_met_de_juiste_sleutel_en_na_afloop() {
		$training = $this->training();
		$id       = $this->aanmelding( $training );

		$this->assertNull( $this->rij( $id ), 'Nog niet geweest.' );
		$this->afgelopen( $training );
		$this->assertIsArray( $this->rij( $id ) );
		$this->assertNull( HDP_Training_Reviews::aanmelding_bij_link( $id, 'verzonnen' ) );
		$this->assertNull( HDP_Training_Reviews::aanmelding_bij_link( $id + 1, HDP_Training_Reviews::sleutel( $id ) ), 'Sleutel hoort bij één aanmelding.' );
	}

	public function test_cijfer_moet_kloppen_en_stemmen_kan_een_keer() {
		$training = $this->training();
		$id       = $this->aanmelding( $training );
		$this->afgelopen( $training );
		$rij = $this->rij( $id );

		foreach ( array( 0, 11, -3, 'veel' ) as $fout ) {
			$this->assertSame( 'training_review_fout_cijfer', HDP_Training_Reviews::bewaar( $rij, array( 'cijfer' => $fout ) ) );
		}
		$this->assertTrue( HDP_Training_Reviews::bewaar( $rij, array( 'cijfer' => 9 ) ) );
		$this->assertTrue( HDP_Training_Reviews::bewaar( $rij, array( 'cijfer' => 1 ) ), 'Dubbel versturen is geen fout.' );

		$stand = HDP_Training_Reviews::stand( $training );
		$this->assertSame( 1, $stand['aantal'] );
		$this->assertSame( 9.0, (float) $stand['gemiddelde'] );
	}

	public function test_teammail_alleen_bij_een_reactie() {
		$training = $this->training();
		$a        = $this->aanmelding( $training, 'Een Persoon', 'een@dealer.test' );
		$b        = $this->aanmelding( $training, 'Twee Persoon', 'twee@dealer.test' );
		$this->afgelopen( $training );

		HDP_Training_Reviews::bewaar( $this->rij( $a ), array( 'cijfer' => 7 ) );
		$this->assertCount( 0, $this->mails );

		HDP_Training_Reviews::bewaar( $this->rij( $b ), array( 'cijfer' => 7, 'reactie' => 'Prima dag', 'mag_tonen' => '1' ) );
		$this->assertCount( 1, $this->mails );
		$this->assertSame( 'team@homburg.test', $this->mails[0]['to'] );
		$this->assertStringContainsString( 'Prima dag', $this->mails[0]['message'] );
	}

	public function test_reactie_pas_zichtbaar_met_akkoord_van_deelnemer_en_homburg() {
		global $wpdb;
		$training = $this->training();
		$a        = $this->aanmelding( $training, 'Een Persoon', 'een@dealer.test' );
		$b        = $this->aanmelding( $training, 'Twee Persoon', 'twee@dealer.test' );
		$c        = $this->aanmelding( $training, 'Drie Persoon', 'drie@dealer.test' );
		$this->afgelopen( $training );
		HDP_Training_Reviews::bewaar( $this->rij( $a ), array( 'cijfer' => 8, 'reactie' => 'Mag erbij', 'mag_tonen' => '1' ) );
		HDP_Training_Reviews::bewaar( $this->rij( $b ), array( 'cijfer' => 6, 'reactie' => 'Liever niet' ) );
		HDP_Training_Reviews::bewaar( $this->rij( $c ), array( 'cijfer' => 9, 'mag_tonen' => '1' ) );

		$this->assertSame( array(), HDP_Training_Reviews::zichtbare_reacties( $training ), 'Nog niet goedgekeurd.' );

		$ids = $wpdb->get_col( $wpdb->prepare( 'SELECT id FROM ' . HDP_Training_Reviews::tabel() . ' WHERE training_id = %d', $training ) ); // phpcs:ignore WordPress.DB
		foreach ( $ids as $review ) {
			HDP_Training_Reviews::zet_zichtbaar( $review, true );
		}
		$this->assertSame( array( 'Mag erbij' ), wp_list_pluck( HDP_Training_Reviews::zichtbare_reacties( $training ), 'reactie' ), 'Zonder toestemming of tekst blijft het verborgen.' );
	}

	public function test_publieke_naam_verbergt_de_achternaam() {
		$this->assertSame( 'Piet M.', HDP_Training_Reviews::korte_naam( 'Piet Monteur' ) );
		$this->assertSame( 'Tom de J.', HDP_Training_Reviews::korte_naam( 'Tom de Jong' ) );
		$this->assertSame( 'Sanne', HDP_Training_Reviews::korte_naam( 'Sanne' ) );
		$this->assertSame(
			'Piet M., monteur',
			HDP_Training_Reviews::publieke_naam(
				array(
					'deelnemer_naam' => 'Piet Monteur',
					'antwoorden'     => array(
						array( 'id' => 'x', 'label' => 'Functie', 'waarde' => 'Monteur' ),
					),
				)
			)
		);
	}

	public function test_vervolgtraining_toont_cijfers_pas_vanaf_drie() {
		global $wpdb;
		$eerste  = $this->training( 'Deel 1' );
		$vervolg = $this->training( 'Deel 2' );
		update_post_meta( $vervolg, HDP_Trainingen::META_VERVOLG_OP, $eerste );
		$a = $this->aanmelding( $eerste, 'Een Persoon', 'een@dealer.test' );
		$b = $this->aanmelding( $eerste, 'Twee Persoon', 'twee@dealer.test' );
		$c = $this->aanmelding( $eerste, 'Drie Persoon', 'drie@dealer.test' );
		$this->afgelopen( $eerste );

		$_GET['training'] = $vervolg;
		HDP_Training_Reviews::bewaar( $this->rij( $a ), array( 'cijfer' => 10, 'reactie' => '<b>Topdag</b> & meer', 'mag_tonen' => '1' ) );
		HDP_Training_Reviews::bewaar( $this->rij( $b ), array( 'cijfer' => 9 ) );
		$html = HDP_Trainingen_Render::render_pagina( array() );
		$this->assertStringContainsString( 'Deel 1', $html, 'Het vervolg-label staat er altijd.' );
		$this->assertStringNotContainsString( 'hdp-training-reviews', $html );

		HDP_Training_Reviews::bewaar( $this->rij( $c ), array( 'cijfer' => 6 ) );
		$wpdb->query( 'UPDATE ' . HDP_Training_Reviews::tabel() . " SET zichtbaar = 1 WHERE reactie <> ''" ); // phpcs:ignore WordPress.DB
		$html = HDP_Trainingen_Render::render_pagina( array() );
		unset( $_GET['training'] );

		$this->assertStringContainsString( 'hdp-training-reviews', $html );
		$this->assertStringContainsString( '8,3', $html );
		$this->assertStringContainsString( 'Topdag', $html );
		$this->assertStringNotContainsString( '<b>Topdag', $html, 'Een reactie is platte tekst.' );
	}

	public function test_formulier_werkt_zonder_inloggen_met_de_link() {
		$training = $this->training();
		$id       = $this->aanmelding( $training );
		$this->afgelopen( $training );
		wp_set_current_user( 0 );

		$_GET['beoordeel'] = $id;
		$_GET['sleutel']   = HDP_Training_Reviews::sleutel( $id );
		$html              = do_blocks( '<!-- wp:homburg/trainingen-pagina /-->' );
		$this->assertStringContainsString( 'name="cijfer"', $html );

		$_GET['sleutel'] = 'fout';
		$html            = do_blocks( '<!-- wp:homburg/trainingen-pagina /-->' );
		unset( $_GET['beoordeel'], $_GET['sleutel'] );
		$this->assertStringNotContainsString( 'name="cijfer"', $html );
		$this->assertStringContainsString( HDP_I18N::t( 'training_review_ongeldig' ), $html );
	}
}
