<?php

/**
 * Test de berichten van Homburg op de startpagina.
 *
 * Het gevoelige deel is de merkafscherming: een bericht voor
 * Väderstad-dealers hoort niet bij een Rabe-dealer op het scherm te staan.
 * Dat gaat stil mis — er komt geen foutmelding van, je ziet alleen te veel
 * of te weinig.
 *
 * @group hdp-berichten
 */
class Berichten_Test extends WP_UnitTestCase {

	private $dealer_id;

	public function setUp(): void {
		parent::setUp();
		HDP_Roles::activate();
		HDP_Berichten::register_post_type();

		$this->dealer_id = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $this->dealer_id, 'hdp_goedgekeurd', '1' );
	}

	public function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/**
	 * @param string $merken Kommagescheiden, of '' voor alle dealers.
	 * @param string $tot    Einddatum Y-m-d, of '' voor onbeperkt.
	 */
	private function maak_bericht( $titel, $merken = '', $tot = '', $soort = 'melding' ) {
		$id = self::factory()->post->create(
			array(
				'post_type'   => HDP_Berichten::POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $titel,
			)
		);
		update_post_meta( $id, HDP_Berichten::META_MERKEN, $merken );
		update_post_meta( $id, HDP_Berichten::META_TOT, $tot );
		update_post_meta( $id, HDP_Berichten::META_SOORT, $soort );

		return $id;
	}

	private function titels( $user_id ) {
		return array_map(
			static function ( $post ) {
				return $post->post_title;
			},
			HDP_Berichten::voor_dealer( $user_id )
		);
	}

	/* --- Merken -------------------------------------------------------- */

	public function test_bericht_zonder_merk_is_voor_iedereen() {
		$this->maak_bericht( 'Gewijzigde openingstijden' );
		update_user_meta( $this->dealer_id, 'hdp_merken', 'Rabe' );

		$this->assertSame( array( 'Gewijzigde openingstijden' ), $this->titels( $this->dealer_id ) );
	}

	public function test_dealer_ziet_alleen_berichten_van_zijn_eigen_merken() {
		$this->maak_bericht( 'Väderstad levertijden', 'Väderstad' );
		$this->maak_bericht( 'Rabe prijswijziging', 'Rabe' );
		update_user_meta( $this->dealer_id, 'hdp_merken', 'Rabe' );

		$this->assertSame( array( 'Rabe prijswijziging' ), $this->titels( $this->dealer_id ) );
	}

	/** Eén overlappend merk is genoeg. */
	public function test_bericht_voor_meerdere_merken_telt_bij_elk_van_die_merken() {
		$this->maak_bericht( 'Onderhoudsbeurt webshop', 'Väderstad, Rabe' );
		update_user_meta( $this->dealer_id, 'hdp_merken', 'Rabe, Bogballe' );

		$this->assertSame( array( 'Onderhoudsbeurt webshop' ), $this->titels( $this->dealer_id ) );
	}

	/**
	 * Zelfde regel als bij downloads: staat er bij een dealer nog geen merk
	 * ingesteld, dan ziet hij alles. Anders krijgt een net aangemaakt
	 * account stilletjes niets te zien.
	 */
	public function test_dealer_zonder_ingestelde_merken_ziet_alles() {
		$this->maak_bericht( 'Väderstad levertijden', 'Väderstad' );
		delete_user_meta( $this->dealer_id, 'hdp_merken' );

		$this->assertSame( array( 'Väderstad levertijden' ), $this->titels( $this->dealer_id ) );
	}

	/* --- Einddatum ----------------------------------------------------- */

	public function test_verlopen_bericht_verdwijnt() {
		$this->maak_bericht( 'Oude actie', '', gmdate( 'Y-m-d', strtotime( '-1 day' ) ) );

		$this->assertSame( array(), $this->titels( $this->dealer_id ) );
	}

	/** "Tot en met": op de laatste dag staat het er nog. */
	public function test_bericht_blijft_staan_op_zijn_laatste_dag() {
		$this->maak_bericht( 'Loopt vandaag af', '', current_time( 'Y-m-d' ) );

		$this->assertSame( array( 'Loopt vandaag af' ), $this->titels( $this->dealer_id ) );
	}

	public function test_bericht_zonder_einddatum_blijft_staan() {
		$this->maak_bericht( 'Blijft staan', '', '' );

		$this->assertSame( array( 'Blijft staan' ), $this->titels( $this->dealer_id ) );
	}

	/* --- Hoeveelheid --------------------------------------------------- */

	/**
	 * Boven drie wordt het een nieuwsbrief en vallen de tegels eronder weg.
	 * De nieuwste blijven staan.
	 */
	public function test_hoogstens_drie_berichten_tegelijk() {
		foreach ( range( 1, 5 ) as $n ) {
			$this->maak_bericht( "Bericht $n" );
		}

		$this->assertCount( HDP_Berichten::MAXIMAAL, HDP_Berichten::voor_dealer( $this->dealer_id ) );
	}

	/* --- Weergave ------------------------------------------------------ */

	public function test_belangrijk_bericht_krijgt_de_aandachtskleur() {
		wp_set_current_user( $this->dealer_id );
		$this->maak_bericht( 'Storing in de webshop', '', '', 'belangrijk' );

		ob_start();
		HDP_Berichten::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'hdp-bericht-belangrijk', $html );
		$this->assertStringContainsString( 'Storing in de webshop', $html );
	}

	/* --- Wegklikken ---------------------------------------------------- */

	/**
	 * Zonder dit blijft een mededeling van vorige maand eeuwig boven de
	 * tegels staan, ook als de dealer hem allang gelezen heeft.
	 */
	public function test_weggeklikt_bericht_verdwijnt_voor_die_dealer() {
		$id = $this->maak_bericht( 'Magazijn gesloten' );
		update_user_meta( $this->dealer_id, HDP_Berichten::META_WEG, array( $id ) );

		$this->assertSame( array(), $this->titels( $this->dealer_id ) );
	}

	/** Maar alleen voor hém: een collega hoort het nog gewoon te zien. */
	public function test_wegklikken_geldt_per_dealer() {
		$id      = $this->maak_bericht( 'Magazijn gesloten' );
		$collega = self::factory()->user->create( array( 'role' => 'dealer' ) );
		update_user_meta( $collega, 'hdp_goedgekeurd', '1' );
		update_user_meta( $this->dealer_id, HDP_Berichten::META_WEG, array( $id ) );

		$this->assertSame( array(), $this->titels( $this->dealer_id ) );
		$this->assertSame( array( 'Magazijn gesloten' ), $this->titels( $collega ) );
	}

	/** Een nieuw bericht komt gewoon weer boven, ook na eerder wegklikken. */
	public function test_een_nieuw_bericht_komt_wel_door() {
		$oud = $this->maak_bericht( 'Oud bericht' );
		update_user_meta( $this->dealer_id, HDP_Berichten::META_WEG, array( $oud ) );
		$this->maak_bericht( 'Nieuw bericht' );

		$this->assertSame( array( 'Nieuw bericht' ), $this->titels( $this->dealer_id ) );
	}

	public function test_elk_bericht_heeft_een_wegklikknop() {
		wp_set_current_user( $this->dealer_id );
		$this->maak_bericht( 'Magazijn gesloten' );

		ob_start();
		HDP_Berichten::render();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'hdp-bericht-weg', $html );
		// Een formulier met nonce, geen kale link: wegklikken verandert iets.
		$this->assertStringContainsString( '_wpnonce', $html );
		$this->assertStringContainsString( '<form', $html );

		// Het nummer hoort in een verborgen veld te staan en niet op de
		// knop. Het gedeelde scriptje zet een verzendknop tijdens het
		// versturen op "bezig"; een knop die op dat moment uitgeschakeld
		// wordt telt niet meer als verzender, en dan komt zijn waarde
		// nooit aan. Dat kostte een zoektocht.
		$this->assertMatchesRegularExpression(
			'#<input type="hidden" name="' . preg_quote( HDP_Berichten::WEG_VELD, '#' ) . '"#',
			$html,
			'Het bericht-id hoort in een verborgen veld te staan.'
		);
	}

	/* --- Nederlands en Frans ------------------------------------------- */

	private function maak_tweetalig_bericht() {
		$id = $this->maak_bericht( 'Magazijn gesloten' );
		wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => 'Op 1 november is het magazijn gesloten.',
			)
		);
		update_post_meta( $id, HDP_Berichten::META_TITEL_FR, 'Entrepôt fermé' );
		update_post_meta( $id, HDP_Berichten::META_TEKST_FR, 'L’entrepôt est fermé le 1er novembre.' );

		return $id;
	}

	private function render_als( $taal ) {
		wp_set_current_user( $this->dealer_id );
		$_COOKIE[ HDP_I18N::COOKIE ] = $taal;

		ob_start();
		HDP_Berichten::render();
		$html = ob_get_clean();

		unset( $_COOKIE[ HDP_I18N::COOKIE ] );

		return $html;
	}

	public function test_franstalige_dealer_krijgt_de_franse_tekst() {
		$this->maak_tweetalig_bericht();

		$html = $this->render_als( 'fr' );

		$this->assertStringContainsString( 'Entrep', $html );
		$this->assertStringContainsString( 'novembre', $html );
		$this->assertStringNotContainsString( 'Magazijn gesloten', $html );
	}

	public function test_nederlandse_dealer_krijgt_de_nederlandse_tekst() {
		$this->maak_tweetalig_bericht();

		$html = $this->render_als( 'nl' );

		$this->assertStringContainsString( 'Magazijn gesloten', $html );
		$this->assertStringNotContainsString( 'novembre', $html );
	}

	/**
	 * Het Franse veld is niet verplicht. Blijft het leeg, dan hoort een
	 * Franstalige dealer de Nederlandse tekst te zien — niet een lege kaart.
	 */
	public function test_zonder_vertaling_ziet_een_franse_dealer_het_nederlands() {
		$id = $this->maak_bericht( 'Alleen in het Nederlands' );
		wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => 'Deze tekst is niet vertaald.',
			)
		);

		$html = $this->render_als( 'fr' );

		$this->assertStringContainsString( 'Alleen in het Nederlands', $html );
		$this->assertStringContainsString( 'niet vertaald', $html );
	}

	public function test_zonder_berichten_staat_er_niets() {
		wp_set_current_user( $this->dealer_id );

		ob_start();
		HDP_Berichten::render();

		$this->assertSame( '', ob_get_clean(), 'Een lege berichtenbalk hoort helemaal niet te verschijnen.' );
	}

	/** Een concept hoort nergens te staan tot het gepubliceerd is. */
	public function test_concept_is_niet_zichtbaar() {
		$id = $this->maak_bericht( 'Nog niet af' );
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'draft',
			)
		);

		$this->assertSame( array(), $this->titels( $this->dealer_id ) );
	}
}
