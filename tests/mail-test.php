<?php

/**
 * Test de afzender van de portaalmails (HDP_Mail).
 *
 * Wat hier fout kan gaan: een afzender op homburg-holland.com, die ontvangers
 * weigeren; of een teammail of WooCommerce-mail waarvan het eigen antwoordadres
 * of de eigen afzender wordt overschreven.
 *
 * @group hdp-mail
 */
class Mail_Test extends WP_UnitTestCase {

	private function verstuur( $headers = array() ) {
		reset_phpmailer_instance();
		$this->assertTrue( wp_mail( 'iemand@voorbeeld.test', 'Onderwerp', 'Tekst', $headers ) );
		return tests_retrieve_phpmailer_instance();
	}

	public function test_standaardmail_komt_van_homburg_met_antwoord_naar_marketing() {
		$mailer = $this->verstuur();

		$this->assertSame( 'Homburg', $mailer->FromName );
		$this->assertStringStartsWith( 'noreply@', $mailer->From );
		$this->assertStringNotContainsString( 'homburg-holland.com', $mailer->From );
		$this->assertStringContainsString( 'Reply-To: ' . HDP_Mail::ANTWOORD_ADRES, $mailer->get_sent()->header );
	}

	public function test_eigen_antwoordadres_blijft_staan() {
		$kop = $this->verstuur( array( 'Reply-To: piet@dealer.test' ) )->get_sent()->header;

		$this->assertStringContainsString( 'Reply-To: piet@dealer.test', $kop );
		$this->assertStringNotContainsString( HDP_Mail::ANTWOORD_ADRES, $kop );
	}

	public function test_eigen_afzender_blijft_staan() {
		$mailer = $this->verstuur( "From: Winkel <winkel@voorbeeld.test>\r\nCc: ander@voorbeeld.test" );

		$this->assertSame( 'winkel@voorbeeld.test', $mailer->From );
		$this->assertSame( 'Winkel', $mailer->FromName );
		$this->assertStringNotContainsString( HDP_Mail::ANTWOORD_ADRES, $mailer->get_sent()->header );
	}

	public function test_www_hoort_niet_in_het_adres() {
		$filter = static function () {
			return 'https://www.voorbeeld.test';
		};
		add_filter( 'home_url', $filter );
		$adres = HDP_Mail::adres( 'wordpress@voorbeeld.test' );
		remove_filter( 'home_url', $filter );

		$this->assertSame( 'noreply@voorbeeld.test', $adres );
	}
}
