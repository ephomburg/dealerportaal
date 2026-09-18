<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Koppelt FileBird (gratis versie) aan de Downloads-CPT: een bestand dat in
 * FileBird in een map staat met de naam "Merk (Taalcode)" — bijv.
 * "HARDI (NL)", "Väderstad (FR)", "Bogballe (EN)" — verschijnt automatisch
 * op de downloadspagina onder dat merk/die regio, zonder dat daar met de
 * hand een Download-bericht voor aangemaakt hoeft te worden.
 *
 * FileBird's gratis versie heeft geen aparte "bestand toegevoegd"-hook,
 * maar de kernfunctie die daadwerkelijk een map aan een bestand koppelt
 * (\FileBird\Model\Folder::setFoldersForPosts(), aangeroepen zowel bij het
 * uploaden mét een geselecteerde map als bij het nadien slepen naar een
 * andere map) vuurt zelf gewoon een reguliere WordPress-hook
 * ("fbv_after_set_folder"). Die is voldoende — geen periodieke controle
 * nodig.
 *
 * Alleen categorie "download" (prijslijsten/handleidingen); "content"
 * (social/advertentiemateriaal) blijft voorlopig een handmatig proces.
 *
 * Om te voorkomen dat een toevallig zo genoemde map ergens anders in
 * FileBird (bijv. foto's voor elders op de site) hier per ongeluk ook in
 * meegaat, telt een merk-map alleen mee als hij een DIRECTE submap is van
 * één vaste, herkenbare hoofdmap (zie ROOT_FOLDER_NAAM) — al het andere in
 * FileBird wordt genegeerd, ongeacht de naam.
 */
class HDP_FileBird_Sync {

	/** Meta-sleutel die aangeeft dat déze download door deze koppeling is aangemaakt (i.p.v. met de hand). */
	const BRON_META = '_hdp_filebird_bron';

	/** Alleen submappen van déze hoofdmap (op het hoogste niveau in FileBird) tellen mee. */
	const ROOT_FOLDER_NAAM = 'Downloads dealerportaal';

	public static function init() {
		if ( ! class_exists( '\FileBird\Model\Folder' ) ) {
			return;
		}
		add_action( 'fbv_after_set_folder', array( __CLASS__, 'on_folder_gewijzigd' ), 10, 2 );
		add_action( 'fbv_after_assign_folder', array( __CLASS__, 'on_bulk_toegewezen' ), 10, 2 );

		// Eenmalig (via een option-vlag, niet bij elke paginalaad) de
		// hoofdmap + per-merk NL/FR/EN-submappen aanmaken zodat die meteen
		// klaarstaan in de mediabibliotheek — alleen relevant in wp-admin,
		// dealers komen hier nooit. Draait na een deploy dus vanzelf één
		// keer mee bij het eerstvolgende bezoek aan wp-admin, zonder dat
		// daar op de live site met de hand iets voor aangemaakt hoeft te
		// worden.
		if ( is_admin() && ! get_option( 'hdp_filebird_mappen_aangemaakt' ) ) {
			add_action( 'admin_init', array( __CLASS__, 'maak_mappen_indien_nodig' ) );
		}
	}

	public static function maak_mappen_indien_nodig() {
		$root    = \FileBird\Model\Folder::newOrGet( self::ROOT_FOLDER_NAAM, 0 );
		$root_id = is_array( $root ) ? (int) $root['id'] : (int) $root->id;

		foreach ( HDP_Downloads_CPT::merk_opties() as $merk ) {
			foreach ( array( 'NL', 'FR', 'EN' ) as $taalcode ) {
				\FileBird\Model\Folder::newOrGet( $merk . ' (' . $taalcode . ')', $root_id );
			}
		}

		update_option( 'hdp_filebird_mappen_aangemaakt', 1 );
	}

	/** Eén bestand naar een (andere) map verplaatst — bij upload of handmatig slepen. */
	public static function on_folder_gewijzigd( $attachment_id, $folder_id ) {
		self::synchroniseer( (int) $attachment_id, (int) $folder_id );
	}

	/** Meerdere bestanden tegelijk aan een map toegewezen (bulkactie in de mediabibliotheek). */
	public static function on_bulk_toegewezen( $folder_id, $attachment_ids ) {
		foreach ( (array) $attachment_ids as $attachment_id ) {
			self::synchroniseer( (int) $attachment_id, (int) $folder_id );
		}
	}

	private static function synchroniseer( $attachment_id, $folder_id ) {
		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			return;
		}

		$bestaande_id = self::vind_download_voor_attachment( $attachment_id );
		$match        = $folder_id > 0 ? self::match_merk_en_regio( $folder_id ) : null;

		if ( ! $match ) {
			// Weg uit een merk-map (of naar "Niet-gecategoriseerd") — alleen
			// een door déze koppeling aangemaakte download opruimen; een met
			// de hand aangemaakte download nooit aankomen.
			if ( $bestaande_id && get_post_meta( $bestaande_id, self::BRON_META, true ) ) {
				wp_trash_post( $bestaande_id );
			}
			return;
		}

		list( $merk, $regio ) = $match;

		if ( $bestaande_id ) {
			// Al een download voor dit bestand (met de hand of eerder door
			// deze koppeling aangemaakt) — alleen merk/regio bijwerken naar
			// de nieuwe map, titel/omschrijving met rust laten.
			update_post_meta( $bestaande_id, '_hdp_merk', $merk );
			update_post_meta( $bestaande_id, '_hdp_regios', $regio );
			if ( 'trash' === get_post_status( $bestaande_id ) ) {
				// wp_untrash_post() probeert de status van vóór het trashen
				// terug te zetten via post-meta die niet altijd betrouwbaar
				// blijkt (leverde hier "concept" op i.p.v. "gepubliceerd") —
				// gewoon zelf expliciet publiceren, dat is toch altijd de
				// bedoelde status voor een actieve download.
				wp_update_post(
					array(
						'ID'          => $bestaande_id,
						'post_status' => 'publish',
					)
				);
			}
			return;
		}

		$titel = get_the_title( $attachment_id );
		if ( ! $titel ) {
			$titel = basename( get_attached_file( $attachment_id ) );
		}

		$download_id = wp_insert_post(
			array(
				'post_type'   => HDP_Downloads_CPT::POST_TYPE,
				'post_title'  => $titel,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $download_id ) || ! $download_id ) {
			return;
		}

		update_post_meta( $download_id, '_hdp_attachment_id', $attachment_id );
		update_post_meta( $download_id, '_hdp_categorie', 'download' );
		update_post_meta( $download_id, '_hdp_merk', $merk );
		update_post_meta( $download_id, '_hdp_regios', $regio );
		update_post_meta( $download_id, self::BRON_META, 1 );
	}

	private static function vind_download_voor_attachment( $attachment_id ) {
		$posts = get_posts(
			array(
				'post_type'      => HDP_Downloads_CPT::POST_TYPE,
				// 'any' sluit de prullenbak stiekem uit — zonder deze
				// expliciete lijst zou een bestand dat ooit uit een merk-map
				// werd gehaald (en dus getrasht) bij terugzetten een tweede,
				// dubbele download krijgen i.p.v. de oude terug te zetten.
				'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'trash' ),
				'meta_key'       => '_hdp_attachment_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- kleine, beheerste dataset (dealerdownloads).
				'meta_value'     => $attachment_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		);
		return $posts ? (int) $posts[0] : 0;
	}

	/**
	 * @return array{0:string,1:string}|null [merk, regio] of null als de
	 *                                        mapnaam niet aan het patroon
	 *                                        "Merk (Taalcode)" voldoet, of
	 *                                        het merk niet herkend wordt.
	 */
	private static function match_merk_en_regio( $folder_id ) {
		$folder = \FileBird\Model\Folder::findById( $folder_id, 'name,parent' );
		if ( ! $folder || ! self::is_directe_submap_van_root( (int) $folder->parent ) ) {
			return null;
		}

		$naam = trim( (string) $folder->name );

		if ( ! preg_match( '/^(.+?)\s*\((NL|FR|EN)\)$/i', $naam, $matches ) ) {
			return null;
		}

		$merk = self::vind_merk( trim( $matches[1] ) );
		if ( ! $merk ) {
			return null;
		}

		$regio_per_taalcode = array(
			'NL' => 'nl',
			'FR' => 'be-fr',
			'EN' => 'en',
		);

		return array( $merk, $regio_per_taalcode[ strtoupper( $matches[2] ) ] );
	}

	/**
	 * Is $parent_id de hoofdmap zelf (ROOT_FOLDER_NAAM, op het hoogste
	 * niveau van FileBird)? Zo wordt een gelijknamige map die de gebruiker
	 * ergens anders in FileBird aanmaakt (los van deze hoofdmap) genegeerd.
	 */
	private static function is_directe_submap_van_root( $parent_id ) {
		if ( $parent_id <= 0 ) {
			return false;
		}
		$root = \FileBird\Model\Folder::findById( $parent_id, 'name,parent' );
		return $root && 0 === (int) $root->parent && self::ROOT_FOLDER_NAAM === trim( (string) $root->name );
	}

	/**
	 * Vergelijkt zonder hoofdletter-/accentgevoeligheid, zodat "Vaderstad"
	 * (zonder umlaut — makkelijk gemaakt bij het typen van een mapnaam) ook
	 * "Väderstad" uit HDP_Downloads_CPT::merk_opties() vindt.
	 */
	private static function vind_merk( $ruwe_naam ) {
		$genormaliseerd = remove_accents( mb_strtolower( $ruwe_naam ) );
		foreach ( HDP_Downloads_CPT::merk_opties() as $optie ) {
			if ( remove_accents( mb_strtolower( $optie ) ) === $genormaliseerd ) {
				return $optie;
			}
		}
		return null;
	}
}
