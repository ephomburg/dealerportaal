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
