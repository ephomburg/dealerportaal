<?php

/**
 * De logo's van de site staan in de plugin, niet in de mediabibliotheek.
 *
 * Aanleiding: het witte logo is als huisstijl-download in FileBird gezet. Een
 * download wordt beveiligd, dus het bestand verhuisde en de openbare versies
 * werden verwijderd. Header en footer verwezen daar rechtstreeks naar, en
 * iedere nieuwe bezoeker zag geen logo meer.
 *
 * @group hdp-logo
 */
class Logo_Test extends WP_UnitTestCase {

	public function test_geen_verwijzing_naar_de_mediabibliotheek() {
		foreach ( array( 'blocks/site-header/render.php', 'blocks/site-footer/render.php', 'includes/class-hdp-login.php' ) as $bestand ) {
			$inhoud = file_get_contents( HDP_PLUGIN_DIR . $bestand );
			$this->assertDoesNotMatchRegularExpression( '#wp-content/uploads/[^"\']*Logo-HOMBURG#', $inhoud, $bestand );
		}
	}

	public function test_logobestanden_staan_in_de_plugin() {
		foreach ( array( 'Logo-HOMBURG_WIT-768x102.png', 'Logo-HOMBURG_WIT-1536x204.png', 'Logo-HOMBURG_RGB-300x40.png' ) as $logo ) {
			$this->assertFileExists( HDP_PLUGIN_DIR . 'assets/img/' . $logo );
		}
	}
}
