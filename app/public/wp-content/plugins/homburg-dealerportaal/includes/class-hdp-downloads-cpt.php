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

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_var' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_download' ) );
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
			<label><input type="radio" name="hdp_categorie" value="content" <?php checked( 'content', $categorie ); ?>> Content (voor social media/advertenties)</label>
		</p>
		<p>
			<label for="hdp_merk"><strong>Merk</strong></label><br>
			<input type="text" name="hdp_merk" id="hdp_merk" value="<?php echo esc_attr( $merk ); ?>" class="widefat" placeholder="bijv. Merk A">
		</p>
		<p>
			<strong>Regio</strong><br>
			<label><input type="checkbox" name="hdp_regio_nl" value="1" <?php checked( in_array( 'nl', $regios, true ) ); ?>> Nederland</label><br>
			<label><input type="checkbox" name="hdp_regio_be" value="1" <?php checked( in_array( 'be', $regios, true ) ); ?>> België</label><br>
			<label><input type="checkbox" name="hdp_regio_be_fr" value="1" <?php checked( in_array( 'be-fr', $regios, true ) ); ?>> België (Franstalig)</label>
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

	/** Bestaande downloads van vóór dit onderscheid hebben geen meta; die tellen als 'download'. */
	public static function get_categorie( $post_id ) {
		$waarde = get_post_meta( $post_id, '_hdp_categorie', true );
		return 'content' === $waarde ? 'content' : 'download';
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'hdp_bestand_save', 'hdp_bestand_nonce' );
		$attachment_id = get_post_meta( $post->ID, '_hdp_attachment_id', true );
		$url           = $attachment_id ? wp_get_attachment_url( $attachment_id ) : '';
		?>
		<p>
			<input type="hidden" name="hdp_attachment_id" id="hdp_attachment_id" value="<?php echo esc_attr( $attachment_id ); ?>">
			<button type="button" class="button" id="hdp_kies_bestand">Bestand kiezen</button>
		</p>
		<p id="hdp_bestand_naam"><?php echo $url ? esc_html( basename( $url ) ) : 'Geen bestand gekozen'; ?></p>
		<script>
		jQuery(function ($) {
			var frame;
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
				update_post_meta( $post_id, '_hdp_attachment_id', absint( $_POST['hdp_attachment_id'] ) );
			}
		}

		if ( isset( $_POST['hdp_rechten_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hdp_rechten_nonce'] ) ), 'hdp_rechten_save' ) ) {
			$categorie = isset( $_POST['hdp_categorie'] ) && 'content' === $_POST['hdp_categorie'] ? 'content' : 'download';
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

		$bestand = self::resolve_download( absint( $download_id ) );

		nocache_headers();
		header( 'Content-Type: ' . $bestand['mime'] );
		header( 'Content-Disposition: attachment; filename="' . basename( $bestand['pad'] ) . '"' );
		header( 'Content-Length: ' . filesize( $bestand['pad'] ) );
		header( 'X-Content-Type-Options: nosniff' );
		// WP_Filesystem zou het hele bestand eerst in PHP-geheugen moeten laden;
		// direct streamen naar de output is hier bewust efficiënter, ook bij
		// grotere downloads.
		readfile( $bestand['pad'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	/**
	 * Controleert toegang en levert bestandsgegevens op, of stopt met
	 * wp_die() bij geen toegang/ontbrekend bestand. Los van
	 * handle_download() zodat dit getest kan worden zonder de
	 * uiteindelijke exit uit te voeren (niet aanroepbaar binnen PHPUnit).
	 */
	public static function resolve_download( $download_id ) {
		$mag_zien = is_user_logged_in() && HDP_Roles::mag_portaal_zien( get_current_user_id() );

		if ( ! $mag_zien ) {
			wp_die( esc_html__( 'U heeft geen toegang tot dit bestand.', 'homburg-dealerportaal' ), 'Geen toegang', array( 'response' => 403 ) );
		}

		$post = get_post( $download_id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			wp_die( esc_html__( 'Bestand niet gevonden.', 'homburg-dealerportaal' ), 'Niet gevonden', array( 'response' => 404 ) );
		}

		$attachment_id = get_post_meta( $download_id, '_hdp_attachment_id', true );
		$file          = $attachment_id ? get_attached_file( $attachment_id ) : '';

		if ( ! $attachment_id || ! $file || ! file_exists( $file ) ) {
			wp_die( esc_html__( 'Bestand niet gevonden.', 'homburg-dealerportaal' ), 'Niet gevonden', array( 'response' => 404 ) );
		}

		self::tel_download( $download_id );

		$mime = get_post_mime_type( $attachment_id );

		return array(
			'post_id' => $download_id,
			'pad'     => $file,
			'mime'    => $mime ? $mime : 'application/octet-stream',
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
			echo 'content' === self::get_categorie( $post_id ) ? 'Content' : 'Download';
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

	public static function download_url( $post_id ) {
		return add_query_arg( self::QUERY_VAR, $post_id, home_url( '/' ) );
	}

	public static function type_label( $post_id ) {
		$attachment_id = get_post_meta( $post_id, '_hdp_attachment_id', true );
		if ( ! $attachment_id ) {
			return '';
		}
		$file = get_attached_file( $attachment_id );
		return $file ? strtoupper( pathinfo( $file, PATHINFO_EXTENSION ) ) : '';
	}
}
