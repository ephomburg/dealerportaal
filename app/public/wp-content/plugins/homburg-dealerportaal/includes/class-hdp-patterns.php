<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Laadt block-patterns uit de map patterns/ van deze plugin. WordPress scant
 * automatisch alleen de patterns/-map van het actieve thema, niet die van een
 * plugin — deze class doet dat alsnog: elk .php-bestand in patterns/ met een
 * headerdocblock (Title/Slug/Categories/Description) wordt geregistreerd, zodat
 * een nieuw pattern direct verschijnt zonder wijziging hier en zonder
 * versiebump.
 *
 * Bestandsopbouw van een pattern (zie patterns/portaalkaarten-sectie.php):
 *
 *   <?php
 *   /**
 *    * Title: Homburg — Portaalkaarten-sectie
 *    * Slug: homburg/portaalkaarten-sectie
 *    * Categories: homburg
 *    * Description: Korte omschrijving.
 *    * /
 *   ?>
 *   <!-- wp:group ... -->
 *   ...
 *   <!-- /wp:group -->
 *
 * Slug altijd homburg/<kebab-case-naam>; categorie altijd (mede) "homburg".
 */
class HDP_Patterns {

	const CATEGORIE = 'homburg';

	public static function init() {
		// Prioriteit 12: ná de core-registratie van pattern-categorieën, zodat
		// onze register_block_pattern_category()-aanroep blijft staan.
		add_action( 'init', array( __CLASS__, 'registreer' ), 12 );
	}

	public static function registreer() {
		if ( ! function_exists( 'register_block_pattern' ) || ! class_exists( 'WP_Block_Patterns_Registry' ) ) {
			return; // Te oude WordPress; block-patterns niet beschikbaar.
		}

		register_block_pattern_category(
			self::CATEGORIE,
			array( 'label' => __( 'Homburg dealerportaal', 'homburg-dealerportaal' ) )
		);

		$registry  = WP_Block_Patterns_Registry::get_instance();
		$bestanden = glob( HDP_PLUGIN_DIR . 'patterns/*.php' );

		foreach ( (array) $bestanden as $bestand ) {
			$kop = get_file_data(
				$bestand,
				array(
					'title'       => 'Title',
					'slug'        => 'Slug',
					'description' => 'Description',
					'categories'  => 'Categories',
					'keywords'    => 'Keywords',
					'inserter'    => 'Inserter',
				)
			);

			if ( empty( $kop['slug'] ) || $registry->is_registered( $kop['slug'] ) ) {
				continue;
			}

			ob_start();
			include $bestand;
			$inhoud = ob_get_clean();

			$args = array(
				'title'   => '' !== $kop['title'] ? $kop['title'] : $kop['slug'],
				'content' => $inhoud,
			);

			if ( '' !== $kop['description'] ) {
				$args['description'] = $kop['description'];
			}
			if ( '' !== $kop['categories'] ) {
				$args['categories'] = array_map( 'trim', explode( ',', $kop['categories'] ) );
			}
			if ( '' !== $kop['keywords'] ) {
				$args['keywords'] = array_map( 'trim', explode( ',', $kop['keywords'] ) );
			}
			if ( '' !== $kop['inserter'] ) {
				$laag              = strtolower( $kop['inserter'] );
				$args['inserter']  = ( 'yes' === $laag || 'true' === $laag || '1' === $laag );
			}

			register_block_pattern( $kop['slug'], $args );
		}
	}
}
