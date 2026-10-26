<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registreert het "Download" post type waarmee Homburg via het
 * WordPress-beheerscherm bestanden kan koppelen (prijslijsten, brochures,
 * etc.), en levert die bestanden alleen aan ingelogde, goedgekeurde
 * dealers via een beveiligd endpoint (niet via een publiek te raden URL).
 */
class HDP_Downloads_CPT {

	const POST_TYPE = 'hdp_download';
	const QUERY_VAR = 'hdp_download';

	/**
	 * Extra formaten van hetzelfde document (bijv. de Excel naast de PDF).
	 * Eén metaregel per bestand, zodat er op gezocht kan worden zonder in
	 * een lijstje te moeten graven. Het hoofdbestand blijft _hdp_attachment_id.
	 */
	const META_EXTRA = '_hdp_extra_bestand';

	/** Hoe breed een voorbeeldplaatje in de contentgalerij wordt. */
	const VOORBEELD_BREEDTE = 640;

	/**
	 * Geen echt merk maar een "voor alle dealers"-markering: bestanden met
	 * dit merk vallen bewust buiten de merkrechten-controle (zie
	 * mag_merk_zien()). Staat daarom óók niet in HDP_Merken::lijst(), want
	 * je kunt het niet aan een dealer toewijzen.
	 */
	const MERK_ALGEMEEN = 'Algemeen';

	/**
	 * Vaste merkenlijst voor het indelen van downloads/content — bewust een
	 * andere (kortere, eigen benaming) lijst dan HDP_Merken::lijst(), die
	 * gaat over welke merken een dealer-account mag zien. Hier gaat het om
	 * onder welk merk een bestand op de downloadspagina wordt getoond.
	 * Vervangt het vrije tekstveld van vroeger (typefouten/inconsistente
	 * merknamen gaven dan ook inconsistente filterknoppen op de pagina zelf).
	 */
	public static function merk_opties() {
		return array(
			self::MERK_ALGEMEEN,
			'Draincleaners',
			'HARDI',
			'Väderstad',
			'Bogballe',
			'Garford',
			'Rabe',
			'Tefen',
		);
	}

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_var' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_download' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_zip' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
	}

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'           => 'Downloads',
				'labels'          => array(
					'name'          => 'Downloads',
					'singular_name' => 'Download',
					'add_new_item'  => 'Nieuwe download toevoegen',
					'edit_item'     => 'Download bewerken',
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-download',
				'supports'        => array( 'title', 'editor' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}

	public static function enqueue_admin_assets( $hook ) {
		global $post_type;
		if ( self::POST_TYPE === $post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			wp_enqueue_media();
		}
	}

	public static function add_meta_box() {
		add_meta_box( 'hdp_bestand', 'Bestand', array( __CLASS__, 'render_meta_box' ), self::POST_TYPE, 'side' );
		add_meta_box( 'hdp_rechten', 'Merk & regio', array( __CLASS__, 'render_rechten_meta_box' ), self::POST_TYPE, 'side' );
	}

	/**
	 * Merk (vrije tekst) en regio (NL/BE) waarmee straks bepaald wordt
	 * welke dealers deze download mogen zien. Dienen nu al als filters op
	 * de downloadspagina; de koppeling met echte dealerrechten volgt later.
	 */
	public static function render_rechten_meta_box( $post ) {
		wp_nonce_field( 'hdp_rechten_save', 'hdp_rechten_nonce' );
		$merk      = get_post_meta( $post->ID, '_hdp_merk', true );
		$regios    = self::get_regios( $post->ID );
		$categorie = self::get_categorie( $post->ID );
		?>
		<p>
			<strong>Categorie</strong><br>
			<label><input type="radio" name="hdp_categorie" value="download" <?php checked( 'download', $categorie ); ?>> Download (prijslijst, handleiding, e.d.)</label><br>
			<label><input type="radio" name="hdp_categorie" value="content" <?php checked( 'content', $categorie ); ?>> Content (voor social media/advertenties)</label><br>
			<label><input type="radio" name="hdp_categorie" value="huisstijl" <?php checked( 'huisstijl', $categorie ); ?>> Huisstijl (logo's, kleuren, lettertypen)</label>
		</p>
		<p>
			<label for="hdp_merk"><strong>Merk</strong></label><br>
			<?php $opties = self::merk_opties(); ?>
			<select name="hdp_merk" id="hdp_merk" class="widefat">
				<option value="">— Geen merk —</option>
				<?php if ( $merk && ! in_array( $merk, $opties, true ) ) : ?>
					<option value="<?php echo esc_attr( $merk ); ?>" selected><?php echo esc_html( $merk ); ?> (niet meer in de lijst)</option>
				<?php endif; ?>
				<?php foreach ( $opties as $optie ) : ?>
					<option value="<?php echo esc_attr( $optie ); ?>" <?php selected( $merk, $optie ); ?>><?php echo esc_html( $optie ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<strong>Regio</strong><br>
			<label><input type="checkbox" name="hdp_regio_nl" value="1" <?php checked( in_array( 'nl', $regios, true ) ); ?>> Nederland</label><br>
			<label><input type="checkbox" name="hdp_regio_be" value="1" <?php checked( in_array( 'be', $regios, true ) ); ?>> België</label><br>
			<label><input type="checkbox" name="hdp_regio_be_fr" value="1" <?php checked( in_array( 'be-fr', $regios, true ) ); ?>> België (Franstalig)</label><br>
			<label><input type="checkbox" name="hdp_regio_en" value="1" <?php checked( in_array( 'en', $regios, true ) ); ?>> Engels</label>
		</p>
		<?php
	}

	public static function get_regios( $post_id ) {
		$waarde = get_post_meta( $post_id, '_hdp_regios', true );
		return $waarde ? explode( ',', $waarde ) : array();
	}

	public static function get_merk( $post_id ) {
		return get_post_meta( $post_id, '_hdp_merk', true );
	}

	/**
	 * Mag deze gebruiker dit bestand inzien, op basis van de merkrechten van
	 * zijn dealeraccount?
	 *
	 * Bewust één gedeelde bron van waarheid voor zowel de downloadspagina
	 * (wat er in de lijst verschijnt) als resolve_download() (wat er
	 * daadwerkelijk wordt uitgeleverd). Stonden die twee los van elkaar, dan
	 * was het merkfilter op de pagina alleen cosmetisch: het bestand blijft
	 * dan gewoon op te halen door het ID in de URL te veranderen.
	 *
	 * "Algemeen" en bestanden zonder merk zijn voor iedereen met
	 * portaaltoegang bedoeld — die vallen dus nooit onder een merkbeperking.
	 * Een dealer zonder ingestelde merken ziet eveneens alles (bestaand
	 * gedrag: nog niet aan merken gekoppelde dealers mogen niet per ongeluk
	 * niets meer zien).
	 */
	public static function mag_merk_zien( $post_id, $user_id = 0 ) {
		$merk = self::get_merk( $post_id );
		if ( ! $merk || self::MERK_ALGEMEEN === $merk ) {
			return true;
		}

		$user_id    = $user_id ? (int) $user_id : get_current_user_id();
		$toegestaan = HDP_Merken::naar_array( get_user_meta( $user_id, 'hdp_merken', true ) );

		if ( ! $toegestaan ) {
			return true;
		}

		return in_array( $merk, $toegestaan, true );
	}

	/**
	 * Hoeveel dagen een bestand als "nieuw" geldt.
	 *
	 * Bewust een vast venster en geen "sinds uw vorige bezoek"-teller per
	 * dealer: dat laatste klinkt aardiger, maar telt bij elke tweede
	 * paginalading al naar nul en is daardoor juist onbetrouwbaar. Drie weken
	 * past bij het tempo waarin hier bestanden bijkomen, en betekent voor
	 * iedereen hetzelfde.
	 */
	const NIEUW_DAGEN = 21;

	/** Is dit bestand recent toegevoegd? Zie NIEUW_DAGEN. */
	public static function is_nieuw( $post_id ) {
		$toegevoegd = get_post_time( 'U', true, $post_id );
		if ( ! $toegevoegd ) {
			return false;
		}
		return ( time() - (int) $toegevoegd ) < ( self::NIEUW_DAGEN * DAY_IN_SECONDS );
	}

	/** De categorieën die een bestand kan hebben. */
	const CATEGORIEEN = array( 'download', 'content', 'huisstijl' );

	/** Bestaande downloads van vóór dit onderscheid hebben geen meta; die tellen als 'download'. */
	public static function get_categorie( $post_id ) {
		$waarde = get_post_meta( $post_id, '_hdp_categorie', true );

		return in_array( $waarde, self::CATEGORIEEN, true ) ? $waarde : 'download';
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'hdp_bestand_save', 'hdp_bestand_nonce' );
		$attachment_id = get_post_meta( $post->ID, '_hdp_attachment_id', true );
		$naam          = $attachment_id ? get_attached_file( $attachment_id ) : '';
		$extra         = self::extra_bestanden( $post->ID );
		?>
		<p>
			<input type="hidden" name="hdp_attachment_id" id="hdp_attachment_id" value="<?php echo esc_attr( $attachment_id ); ?>">
			<button type="button" class="button" id="hdp_kies_bestand"><?php echo $attachment_id ? 'Nieuwe versie kiezen' : 'Bestand kiezen'; ?></button>
		</p>
		<p id="hdp_bestand_naam"><?php echo $naam ? esc_html( basename( $naam ) ) : 'Geen bestand gekozen'; ?></p>
		<?php if ( $attachment_id ) : ?>
			<p class="description">Een nieuwe versie vervangt het bestand. De download krijgt de datum van vandaag en staat bij dealers weer als nieuw.</p>
		<?php endif; ?>

		<p><strong>Extra formaten</strong><br><span class="description">Hetzelfde document in een ander formaat, bijv. de Excel naast de PDF.</span></p>
		<input type="hidden" name="hdp_extra_ids" id="hdp_extra_ids" value="<?php echo esc_attr( implode( ',', $extra ) ); ?>">
		<ul id="hdp_extra_lijst" style="margin:0 0 .5em">
			<?php foreach ( $extra as $extra_id ) : ?>
				<li data-id="<?php echo esc_attr( $extra_id ); ?>"><?php echo esc_html( basename( (string) get_attached_file( $extra_id ) ) ); ?> <a href="#" class="hdp-extra-weg">verwijderen</a></li>
			<?php endforeach; ?>
		</ul>
		<p><button type="button" class="button" id="hdp_kies_extra">Formaat toevoegen</button></p>
		<script>
		jQuery(function ($) {
			var frame, extraFrame;
			$('#hdp_kies_bestand').on('click', function (e) {
				e.preventDefault();
				if (frame) { frame.open(); return; }
				frame = wp.media({ title: 'Bestand kiezen', button: { text: 'Gebruik dit bestand' }, multiple: false });
				frame.on('select', function () {
					var attachment = frame.state().get('selection').first().toJSON();
					$('#hdp_attachment_id').val(attachment.id);
					$('#hdp_bestand_naam').text(attachment.filename);
				});
				frame.open();
			});

			function extraBijwerken() {
				$('#hdp_extra_ids').val($('#hdp_extra_lijst li').map(function () { return $(this).data('id'); }).get().join(','));
			}
			$('#hdp_extra_lijst').on('click', '.hdp-extra-weg', function (e) {
				e.preventDefault();
				$(this).closest('li').remove();
				extraBijwerken();
			});
			$('#hdp_kies_extra').on('click', function (e) {
				e.preventDefault();
				if (extraFrame) { extraFrame.open(); return; }
				extraFrame = wp.media({ title: 'Formaat toevoegen', button: { text: 'Toevoegen' }, multiple: true });
				extraFrame.on('select', function () {
					extraFrame.state().get('selection').each(function (bijlage) {
						var a = bijlage.toJSON();
						$('<li>').attr('data-id', a.id).text(a.filename + ' ').append($('<a href="#" class="hdp-extra-weg">verwijderen</a>')).appendTo('#hdp_extra_lijst');
					});
					extraBijwerken();
				});
				extraFrame.open();
			});
		});
		</script>
		<?php
	}

	public static function save_meta_box( $post_id ) {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['hdp_bestand_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hdp_bestand_nonce'] ) ), 'hdp_bestand_save' ) ) {
			if ( isset( $_POST['hdp_attachment_id'] ) ) {
				$nieuw = absint( $_POST['hdp_attachment_id'] );
				$oud   = (int) get_post_meta( $post_id, '_hdp_attachment_id', true );
				if ( $oud && $nieuw && $oud !== $nieuw ) {
					// Een ander bestand bij een bestaande download is een nieuwe
					// versie: zelfde afhandeling als in het adminportaal.
					self::vervang_bestand( $post_id, $nieuw );
				} else {
					update_post_meta( $post_id, '_hdp_attachment_id', $nieuw );
				}
			}
			if ( isset( $_POST['hdp_extra_ids'] ) ) {
				$ids = array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['hdp_extra_ids'] ) ) ) ) );
				self::zet_extra_bestanden( $post_id, $ids );
			}
		}

		if ( isset( $_POST['hdp_rechten_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hdp_rechten_nonce'] ) ), 'hdp_rechten_save' ) ) {
			$gekozen   = isset( $_POST['hdp_categorie'] ) ? sanitize_key( wp_unslash( $_POST['hdp_categorie'] ) ) : '';
			$categorie = in_array( $gekozen, self::CATEGORIEEN, true ) ? $gekozen : 'download';
			update_post_meta( $post_id, '_hdp_categorie', $categorie );

			update_post_meta( $post_id, '_hdp_merk', isset( $_POST['hdp_merk'] ) ? sanitize_text_field( wp_unslash( $_POST['hdp_merk'] ) ) : '' );

			$regios = array();
			if ( ! empty( $_POST['hdp_regio_nl'] ) ) {
				$regios[] = 'nl';
			}
			if ( ! empty( $_POST['hdp_regio_be'] ) ) {
				$regios[] = 'be';
			}
			if ( ! empty( $_POST['hdp_regio_be_fr'] ) ) {
				$regios[] = 'be-fr';
			}
			if ( ! empty( $_POST['hdp_regio_en'] ) ) {
				$regios[] = 'en';
			}
			update_post_meta( $post_id, '_hdp_regios', implode( ',', $regios ) );
		}
	}

	public static function register_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	public static function handle_download() {
		$download_id = get_query_var( self::QUERY_VAR );
		if ( ! $download_id ) {
			return;
		}

		// Welk formaat (bij meerdere) en hoe: downloaden, bekijken in de
		// browser, of een voorbeeldplaatje. Alleen keuzes; de toegang wordt in
		// resolve_download() gecontroleerd.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- alleen lezen, zie hierboven.
		$attachment_id = isset( $_GET['bestand'] ) ? absint( wp_unslash( $_GET['bestand'] ) ) : 0;
		$modus         = isset( $_GET['modus'] ) ? sanitize_key( wp_unslash( $_GET['modus'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Een voorbeeldplaatje telt niet als download: dan zou elke keer dat
		// een dealer de contentpagina opent de teller van elk beeld oplopen.
		$bestand = self::resolve_download( absint( $download_id ), $attachment_id, 'voorbeeld' !== $modus );

		if ( 'voorbeeld' === $modus ) {
			$voorbeeld = self::voorbeeld_pad( $bestand['attachment_id'] );
			if ( ! $voorbeeld ) {
				status_header( 404 );
				exit;
			}
			// Mag even in de browser blijven: anders laadt een galerij met
			// veertig beelden bij elk bezoek alles opnieuw. Wel "private",
			// zodat een tussenliggende server het niet bewaart.
			header( 'Cache-Control: private, max-age=86400' );
			header( 'Content-Type: image/jpeg' );
			header( 'Content-Length: ' . filesize( $voorbeeld ) );
			header( 'X-Content-Type-Options: nosniff' );
			readfile( $voorbeeld ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
			exit;
		}

		// Bekijken opent het bestand in de browser in plaats van het op te
		// slaan — alleen voor wat een browser veilig zelf kan tonen.
		$inline = 'bekijken' === $modus && self::is_bekijkbaar( $bestand['mime'] );

		nocache_headers();
		header( 'Content-Type: ' . $bestand['mime'] );
		header( 'Content-Disposition: ' . ( $inline ? 'inline' : 'attachment' ) . '; filename="' . basename( $bestand['pad'] ) . '"' );
		header( 'Content-Length: ' . filesize( $bestand['pad'] ) );
		header( 'X-Content-Type-Options: nosniff' );
		// WP_Filesystem zou het hele bestand eerst in PHP-geheugen moeten laden;
		// direct streamen naar de output is hier bewust efficiënter, ook bij
		// grotere downloads.
		readfile( $bestand['pad'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/** De parameter voor "alles downloaden" (zie handle_zip()). */
	const ZIP_VAR = 'hdp_zip';

	/** De categorieën waarvoor "alles downloaden" bestaat. */
	const ZIP_CATEGORIEEN = array( 'content' );

	public static function zip_url( $categorie ) {
		return add_query_arg( self::ZIP_VAR, $categorie, home_url( '/' ) );
	}

	/**
	 * Alles uit een categorie in één zip, zoals "alles downloaden" op de
	 * contentpagina.
	 *
	 * Bevat precies wat deze dealer op de pagina ziet: dezelfde taal- en
	 * merkfilter (HDP_Downloads_Render::zichtbare_downloads()), dus nooit een
	 * bestand waar hij los niet bij zou mogen.
	 */
	public static function handle_zip() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- alleen lezen; de toegang wordt hieronder gecontroleerd.
		$categorie = isset( $_GET[ self::ZIP_VAR ] ) ? sanitize_key( wp_unslash( $_GET[ self::ZIP_VAR ] ) ) : '';
		if ( ! in_array( $categorie, self::ZIP_CATEGORIEEN, true ) ) {
			return;
		}

		$pad = self::maak_zip( $categorie );

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="homburg-' . $categorie . '-' . gmdate( 'Y-m-d' ) . '.zip"' );
		header( 'Content-Length: ' . filesize( $pad ) );
		header( 'X-Content-Type-Options: nosniff' );
		readfile( $pad ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		wp_delete_file( $pad );
		exit;
	}

	/**
	 * Maakt de zip en geeft het pad terug; stopt met een foutscherm bij geen
	 * toegang. Los van handle_zip() zodat het te testen is zonder exit.
	 */
	public static function maak_zip( $categorie ) {
		if ( ! is_user_logged_in() ) {
			HDP_Foutscherm::stop( 'niet_ingelogd', __( 'Je bent niet meer ingelogd.', 'homburg-dealerportaal' ), 'Geen toegang', 403 );
		}
		if ( ! HDP_Roles::mag_portaal_zien( get_current_user_id() ) ) {
			HDP_Foutscherm::stop( 'wacht_goedkeuring', __( 'Je account is nog niet goedgekeurd.', 'homburg-dealerportaal' ), 'Geen toegang', 403 );
		}

		$downloads = HDP_Downloads_Render::zichtbare_downloads( $categorie )['downloads'];
		if ( ! $downloads || ! class_exists( 'ZipArchive' ) ) {
			HDP_Foutscherm::stop( 'niet_gevonden', __( 'Bestand niet gevonden.', 'homburg-dealerportaal' ), 'Niet gevonden', 404 );
		}

		// wp_tempnam() staat in een beheerbestand dat op een gewone pagina
		// niet geladen is.
		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		$pad = wp_tempnam( 'hdp-' . $categorie . '.zip' );
		$zip = new ZipArchive();
		if ( true !== $zip->open( $pad, ZipArchive::OVERWRITE ) ) {
			HDP_Foutscherm::stop( 'niet_gevonden', __( 'Bestand niet gevonden.', 'homburg-dealerportaal' ), 'Niet gevonden', 404 );
		}

		$namen = array();
		foreach ( $downloads as $download ) {
			foreach ( self::bestanden( $download->ID ) as $attachment_id ) {
				$bestand = get_attached_file( $attachment_id );
				if ( ! $bestand || ! file_exists( $bestand ) ) {
					continue;
				}
				// Twee bestanden met dezelfde naam mogen elkaar niet overschrijven.
				$naam = wp_basename( $bestand );
				$i    = 2;
				while ( isset( $namen[ $naam ] ) ) {
					$naam = pathinfo( wp_basename( $bestand ), PATHINFO_FILENAME ) . '-' . ( $i++ ) . '.' . pathinfo( $bestand, PATHINFO_EXTENSION );
				}
				$namen[ $naam ] = true;
				$zip->addFile( $bestand, $naam );
			}
			self::tel_download( $download->ID );
		}
		$zip->close();

		return $pad;
	}

	/** Kan een browser dit zelf tonen? PDF en gewone afbeeldingen; geen SVG (kan scripts bevatten). */
	public static function is_bekijkbaar( $mime ) {
		return in_array( $mime, array( 'application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ), true );
	}

	/** Is dit een afbeelding waarvan een voorbeeldplaatje te maken is? */
	public static function is_afbeelding( $attachment_id ) {
		return in_array( get_post_mime_type( $attachment_id ), array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif' ), true );
	}

	/**
	 * Een verkleinde kopie voor de contentgalerij, in de afgeschermde map.
	 *
	 * WordPress maakt zelf ook kleinere formaten, maar die worden bij het
	 * beveiligen weggegooid (ze stonden in de openbare map). Deze wordt pas
	 * gemaakt als hij voor het eerst opgevraagd wordt, en daarna bewaard —
	 * zo werkt het ook voor beelden die er al stonden.
	 *
	 * @return string Pad naar het plaatje, of '' als dat niet lukt.
	 */
	public static function voorbeeld_pad( $attachment_id ) {
		if ( ! self::is_afbeelding( $attachment_id ) ) {
			return '';
		}

		$bron    = get_attached_file( $attachment_id );
		$uploads = wp_upload_dir();
		if ( ! $bron || ! file_exists( $bron ) || ! empty( $uploads['error'] ) ) {
			return '';
		}

		$map  = trailingslashit( $uploads['basedir'] ) . HDP_Bestandsbeveiliging::MAP . '/voorbeelden';
		$doel = $map . '/' . (int) $attachment_id . '-' . self::VOORBEELD_BREEDTE . '.jpg';

		// Opnieuw maken als het origineel nieuwer is (nieuwe versie geüpload).
		if ( file_exists( $doel ) && filemtime( $doel ) >= filemtime( $bron ) ) {
			return $doel;
		}

		if ( ! wp_mkdir_p( $map ) ) {
			return '';
		}

		$editor = wp_get_image_editor( $bron );
		if ( is_wp_error( $editor ) ) {
			return '';
		}
		$editor->resize( self::VOORBEELD_BREEDTE, self::VOORBEELD_BREEDTE, false );
		$editor->set_quality( 78 );
		$opgeslagen = $editor->save( $doel, 'image/jpeg' );

		return is_wp_error( $opgeslagen ) ? '' : $doel;
	}

	/**
	 * Alle bestanden van een download: het hoofdbestand en de extra formaten.
	 *
	 * @return int[] Bijlage-ID's, hoofdbestand eerst.
	 */
	public static function bestanden( $post_id ) {
		$hoofd = (int) get_post_meta( $post_id, '_hdp_attachment_id', true );
		$alle  = array_merge( $hoofd ? array( $hoofd ) : array(), self::extra_bestanden( $post_id ) );

		return array_values( array_unique( array_filter( $alle ) ) );
	}

	/** De extra formaten (zonder het hoofdbestand). */
	public static function extra_bestanden( $post_id ) {
		return array_values( array_filter( array_map( 'intval', (array) get_post_meta( $post_id, self::META_EXTRA, false ) ) ) );
	}

	/** Vervangt de lijst extra formaten. Elke toegevoegde wordt beveiligd (zie HDP_Bestandsbeveiliging). */
	public static function zet_extra_bestanden( $post_id, $ids ) {
		$hoofd = (int) get_post_meta( $post_id, '_hdp_attachment_id', true );
		delete_post_meta( $post_id, self::META_EXTRA );
		foreach ( array_unique( array_map( 'intval', (array) $ids ) ) as $id ) {
			if ( $id && $id !== $hoofd && 'attachment' === get_post_type( $id ) ) {
				add_post_meta( $post_id, self::META_EXTRA, $id );
			}
		}
	}

	/**
	 * Een nieuwe versie: het bestand wordt vervangen, de download blijft.
	 *
	 * Eerder bleef bij een nieuwe prijslijst de oude gewoon staan, en dan
	 * stonden er twee naast elkaar waarvan niemand zag welke gold. Nu blijft
	 * het één regel met het nieuwe bestand. De datum gaat naar vandaag:
	 * die staat in de lijst, de nieuwste staat bovenaan, en hij krijgt weer
	 * het label "Nieuw" — de dealer hoort te merken dat er iets veranderd is.
	 *
	 * Het oude bestand blijft in de mediabibliotheek staan (afgeschermd en
	 * nergens meer aan gekoppeld), zodat een vergissing terug te draaien is.
	 */
	public static function vervang_bestand( $post_id, $attachment_id ) {
		update_post_meta( $post_id, '_hdp_attachment_id', (int) $attachment_id );

		$nu = current_time( 'mysql' );
		wp_update_post(
			array(
				'ID'            => $post_id,
				'post_date'     => $nu,
				'post_date_gmt' => get_gmt_from_date( $nu ),
			)
		);
	}

	/**
	 * Een nette titel uit een bestandsnaam.
	 *
	 * Titels stonden er als "Gebruikershandleiding_HOMBURG_JUNIOR_-_1980-2011_-_NL".
	 * Dit maakt daar "Gebruikershandleiding Homburg Junior 1980-2011 NL" van:
	 * zonder extensie en liggende streepjes, woorden in hoofdletters alleen
	 * met een hoofdletter vooraan — behalve merknamen die zo geschreven
	 * worden (HARDI) en korte afkortingen (NL, CTS).
	 */
	public static function nette_titel( $bestandsnaam ) {
		$naam = pathinfo( (string) $bestandsnaam, PATHINFO_FILENAME );
		// WordPress plakt "-1", "-2" achter een naam die al bestond
		// ("…2014-1"). Alleen na een cijfer: "Rev-2" is een versienummer.
		$naam = preg_replace( '/(?<=\d)-\d$/', '', $naam );
		$naam = str_replace( '_', ' ', $naam );
		// Een los streepje tussen woorden is een scheiding; tussen cijfers
		// (1980-2011) blijft hij staan.
		$naam = preg_replace( '/(?<!\d)-|-(?!\d)/', ' ', $naam );
		$naam = trim( preg_replace( '/\s+/', ' ', $naam ) );

		$merken = array();
		foreach ( array_merge( self::merk_opties(), HDP_Merken::lijst(), array( 'Homburg' ) ) as $merk ) {
			$merken[ mb_strtolower( $merk ) ] = $merk;
		}

		$woorden = explode( ' ', $naam );
		foreach ( $woorden as $i => $woord ) {
			$klein = mb_strtolower( $woord );
			if ( isset( $merken[ $klein ] ) ) {
				$woorden[ $i ] = $merken[ $klein ];
			} elseif ( mb_strlen( $woord ) > 3 && mb_strtoupper( $woord ) === $woord && preg_match( '/\p{L}/u', $woord ) ) {
				$woorden[ $i ] = mb_strtoupper( mb_substr( $klein, 0, 1 ) ) . mb_substr( $klein, 1 );
			}
		}

		$titel = implode( ' ', $woorden );

		return '' === $titel ? (string) $bestandsnaam : mb_strtoupper( mb_substr( $titel, 0, 1 ) ) . mb_substr( $titel, 1 );
	}

	/** Grootte van een bestand in bytes, 0 als het er niet is. */
	public static function bestandsgrootte( $attachment_id ) {
		$pad = get_attached_file( $attachment_id );
		return $pad && file_exists( $pad ) ? (int) filesize( $pad ) : 0;
	}

	/**
	 * Controleert toegang en levert bestandsgegevens op, of stopt met
	 * wp_die() bij geen toegang/ontbrekend bestand. Los van
	 * handle_download() zodat dit getest kan worden zonder de
	 * uiteindelijke exit uit te voeren (niet aanroepbaar binnen PHPUnit).
	 */
	public static function resolve_download( $download_id, $attachment_id = 0, $tellen = true ) {
		// Bewust drie losse gevallen in plaats van één "geen toegang": een
		// verlopen sessie, een account dat nog op goedkeuring wacht en een
		// bestand van een ander merk vragen elk om een ander antwoord aan de
		// dealer (zie HDP_Foutscherm).
		if ( ! is_user_logged_in() ) {
			HDP_Foutscherm::stop( 'niet_ingelogd', __( 'Je bent niet meer ingelogd.', 'homburg-dealerportaal' ), 'Geen toegang', 403 );
		}

		if ( ! HDP_Roles::mag_portaal_zien( get_current_user_id() ) ) {
			HDP_Foutscherm::stop( 'wacht_goedkeuring', __( 'Je account is nog niet goedgekeurd.', 'homburg-dealerportaal' ), 'Geen toegang', 403 );
		}

		$post = get_post( $download_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			HDP_Foutscherm::stop( 'niet_gevonden', __( 'Bestand niet gevonden.', 'homburg-dealerportaal' ), 'Niet gevonden', 404 );
		}

		// Zonder deze controle is het merkfilter op de downloadspagina puur
		// cosmetisch: het bestand blijft dan op te halen door het nummer in
		// de URL op te hogen (de ID's lopen op). Een dealer kon zo bij de
		// prijslijsten van merken die hij niet voert.
		if ( ! self::mag_merk_zien( $download_id ) ) {
			HDP_Foutscherm::stop( 'geen_merkrecht', __( 'Dit bestand hoort bij een merk dat niet aan je account gekoppeld is.', 'homburg-dealerportaal' ), 'Geen toegang', 403 );
		}

		// Zonder keuze het hoofdbestand. Met keuze alleen een bestand dat écht
		// bij déze download hoort: anders was met een ander nummer in de URL
		// elk bestand op te halen via een download waar de dealer wél bij mag.
		$bestanden = self::bestanden( $download_id );
		if ( ! $attachment_id ) {
			$attachment_id = $bestanden ? $bestanden[0] : 0;
		} elseif ( ! in_array( (int) $attachment_id, $bestanden, true ) ) {
			$attachment_id = 0;
		}

		$file = $attachment_id ? get_attached_file( $attachment_id ) : '';

		if ( ! $attachment_id || ! $file || ! file_exists( $file ) ) {
			HDP_Foutscherm::stop( 'niet_gevonden', __( 'Bestand niet gevonden.', 'homburg-dealerportaal' ), 'Niet gevonden', 404 );
		}

		if ( $tellen ) {
			self::tel_download( $download_id );
		}

		$mime = get_post_mime_type( $attachment_id );

		return array(
			'post_id'       => $download_id,
			'attachment_id' => (int) $attachment_id,
			'pad'           => $file,
			'mime'          => $mime ? $mime : 'application/octet-stream',
		);
	}

	public static function columns( $columns ) {
		$columns['hdp_categorie'] = 'Categorie';
		$columns['hdp_bestand']   = 'Bestand';
		$columns['hdp_merk']      = 'Merk';
		$columns['hdp_regio']     = 'Regio';
		$columns['hdp_teller']    = 'Downloads';
		return $columns;
	}

	public static function column_content( $column, $post_id ) {
		if ( 'hdp_categorie' === $column ) {
			$namen = array(
				'download'  => 'Download',
				'content'   => 'Content',
				'huisstijl' => 'Huisstijl',
			);
			echo esc_html( $namen[ self::get_categorie( $post_id ) ] );
			return;
		}

		if ( 'hdp_bestand' === $column ) {
			$attachment_id = get_post_meta( $post_id, '_hdp_attachment_id', true );
			echo $attachment_id ? esc_html( basename( get_attached_file( $attachment_id ) ) ) : '—';
			return;
		}

		if ( 'hdp_merk' === $column ) {
			$merk = self::get_merk( $post_id );
			echo $merk ? esc_html( $merk ) : '—';
			return;
		}

		if ( 'hdp_regio' === $column ) {
			$regios = self::get_regios( $post_id );
			echo $regios ? esc_html( strtoupper( implode( ', ', $regios ) ) ) : '—';
			return;
		}

		if ( 'hdp_teller' === $column ) {
			echo esc_html( self::get_download_teller( $post_id ) );
		}
	}

	/**
	 * Telt bij elke geleverde download op — alleen zichtbaar in het
	 * wp-admin-overzicht (kolom + adminportaal), nooit voor dealers zelf.
	 * Atomair opgehoogd via een directe UPDATE-query om raceconditie bij
	 * gelijktijdige downloads te vermijden.
	 */
	private static function tel_download( $post_id ) {
		// Beheerders tellen niet mee: de teller gaat over dealergebruik, en
		// sinds beveiligde bestanden via dit endpoint getoond worden (zie
		// HDP_Bestandsbeveiliging) zou elk voorbeeld in de mediabibliotheek
		// anders als download meetellen.
		if ( current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! add_post_meta( $post_id, '_hdp_download_teller', 1, true ) ) {
			global $wpdb;
			$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1 WHERE post_id = %d AND meta_key = %s", $post_id, '_hdp_download_teller' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- atomaire increment; via get_post_meta()+update_post_meta() zou de teller bij gelijktijdige downloads kunnen overschrijven.
			// De directe UPDATE hierboven gaat buiten WordPress' objectcache om,
			// die anders de oude waarde zou blijven teruggeven aan
			// get_post_meta() (o.a. bij persistente caching zoals Redis).
			wp_cache_delete( $post_id, 'post_meta' );
		}
	}

	public static function get_download_teller( $post_id ) {
		return (int) get_post_meta( $post_id, '_hdp_download_teller', true );
	}

	/**
	 * @param int    $post_id       De download.
	 * @param int    $attachment_id Een extra formaat; 0 of het hoofdbestand = de gewone link.
	 * @param string $modus         '' (downloaden), 'bekijken' of 'voorbeeld'.
	 */
	public static function download_url( $post_id, $attachment_id = 0, $modus = '' ) {
		$args = array( self::QUERY_VAR => $post_id );

		if ( $attachment_id && (int) get_post_meta( $post_id, '_hdp_attachment_id', true ) !== (int) $attachment_id ) {
			$args['bestand'] = (int) $attachment_id;
		}
		if ( $modus ) {
			$args['modus'] = $modus;
		}

		return add_query_arg( $args, home_url( '/' ) );
	}

	/** Het formaat in hoofdletters (PDF, XLSX); van een download of van één bijlage. */
	public static function type_label( $post_id, $attachment_id = 0 ) {
		$attachment_id = $attachment_id ? $attachment_id : get_post_meta( $post_id, '_hdp_attachment_id', true );
		if ( ! $attachment_id ) {
			return '';
		}
		$file = get_attached_file( $attachment_id );
		return $file ? strtoupper( pathinfo( $file, PATHINFO_EXTENSION ) ) : '';
	}
}
