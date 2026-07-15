<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tijdelijk, nog niet afgeschermd upload-scherm ("adminportaal").
 *
 * Bewust nu al functioneel: een geüpload bestand + titel + merk + regio
 * wordt direct als "hdp_download" gepubliceerd (hetzelfde systeem als de
 * downloadspagina), zodat het meteen met de juiste tags op /downloads/
 * verschijnt. Wanneer de Supabase-koppeling er is, verandert alleen wáár
 * het bestand wordt opgeslagen — deze pagina en het downloadsysteem zelf
 * hoeven dan niet opnieuw gebouwd te worden.
 *
 * LET OP: dit scherm heeft bewust nog geen inlog- of rechtencontrole
 * (op verzoek, om nu vrij te kunnen testen). Zodra dit alleen voor
 * beheerders zichtbaar moet zijn, is er précies één plek om dat te
 * regelen: HDP_Admin_Upload::mag_gebruiken().
 */
class HDP_Admin_Upload {

	const NONCE_ACTIE = 'hdp_admin_upload';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'registreer_blok' ) );
		add_action( 'init', array( __CLASS__, 'verwerk_upload' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	public static function registreer_blok() {
		register_block_type( HDP_PLUGIN_DIR . 'blocks/admin-upload' );
	}

	public static function enqueue_assets() {
		$post = get_post();
		if ( ! $post || ! has_block( 'homburg/admin-upload', $post ) ) {
			return;
		}

		wp_enqueue_style( 'hdp-dealerportaal', HDP_PLUGIN_URL . 'assets/css/dealerportaal.css', array(), HDP_VERSION );
	}

	/**
	 * Centraal punt om dit scherm later achter een rol/rechtencontrole te
	 * zetten. Geeft nu bewust altijd true terug.
	 */
	public static function mag_gebruiken() {
		return true;
	}

	private static function toegestane_mimes() {
		return array(
			'pdf'      => 'application/pdf',
			'zip'      => 'application/zip',
			'xlsx'     => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'xls'      => 'application/vnd.ms-excel',
			'docx'     => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'doc'      => 'application/msword',
			'csv'      => 'text/csv',
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
		);
	}

	public static function verwerk_upload() {
		if ( ! self::mag_gebruiken() ) {
			return;
		}

		if ( ! isset( $_POST['hdp_admin_upload_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hdp_admin_upload_nonce'] ) ), self::NONCE_ACTIE ) ) {
			return;
		}

		$terug_naar = wp_get_referer();
		if ( ! $terug_naar ) {
			$terug_naar = home_url( '/adminportaal/' );
		}
		$terug_naar = remove_query_arg( array( 'hdp_upload_status', 'hdp_upload_bericht' ), $terug_naar );

		$titel = isset( $_POST['hdp_titel'] ) ? sanitize_text_field( wp_unslash( $_POST['hdp_titel'] ) ) : '';
		if ( ! $titel ) {
			self::redirect_met_status( $terug_naar, 'fout', 'Geef een titel op.' );
		}

		if ( empty( $_FILES['hdp_bestand']['name'] ) ) {
			self::redirect_met_status( $terug_naar, 'fout', 'Kies eerst een bestand.' );
		}

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$upload = wp_handle_upload(
			$_FILES['hdp_bestand'],
			array(
				'test_form' => false,
				'mimes'     => self::toegestane_mimes(),
			)
		);

		if ( isset( $upload['error'] ) ) {
			self::redirect_met_status( $terug_naar, 'fout', $upload['error'] );
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => $upload['type'],
				'post_title'     => $titel,
				'post_status'    => 'inherit',
			),
			$upload['file']
		);

		if ( is_wp_error( $attachment_id ) ) {
			self::redirect_met_status( $terug_naar, 'fout', 'Uploaden van het bestand is mislukt.' );
		}

		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );

		$categorie = isset( $_POST['hdp_categorie'] ) && 'content' === $_POST['hdp_categorie'] ? 'content' : 'download';
		$merk      = isset( $_POST['hdp_merk'] ) ? sanitize_text_field( wp_unslash( $_POST['hdp_merk'] ) ) : '';

		$regios = array();
		if ( ! empty( $_POST['hdp_regio_nl'] ) ) {
			$regios[] = 'nl';
		}
		if ( ! empty( $_POST['hdp_regio_be'] ) ) {
			$regios[] = 'be';
		}

		$download_id = wp_insert_post(
			array(
				'post_type'   => HDP_Downloads_CPT::POST_TYPE,
				'post_title'  => $titel,
				'post_status' => 'publish',
			)
		);

		if ( is_wp_error( $download_id ) ) {
			self::redirect_met_status( $terug_naar, 'fout', 'Aanmaken van de download is mislukt.' );
		}

		update_post_meta( $download_id, '_hdp_attachment_id', $attachment_id );
		update_post_meta( $download_id, '_hdp_categorie', $categorie );
		update_post_meta( $download_id, '_hdp_merk', $merk );
		update_post_meta( $download_id, '_hdp_regios', implode( ',', $regios ) );

		$doelpagina = 'content' === $categorie ? 'de contentpagina' : 'de downloadspagina';
		self::redirect_met_status( $terug_naar, 'gelukt', $titel . ' is geüpload en staat nu op ' . $doelpagina . '.' );
	}

	private static function redirect_met_status( $url, $status, $bericht ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'hdp_upload_status'  => $status,
					'hdp_upload_bericht' => rawurlencode( $bericht ),
				),
				$url
			)
		);
		exit;
	}

	public static function render( $a ) {
		if ( ! self::mag_gebruiken() ) {
			return '<p class="hdp-nog-niet">Dit scherm is niet beschikbaar.</p>';
		}

		ob_start();
		?>
		<div class="hdp-admin-sectie">
			<h1><?php echo esc_html( $a['titel'] ); ?></h1>
			<p class="hdp-admin-intro"><?php echo esc_html( $a['intro'] ); ?></p>

			<?php
			// Post/Redirect/Get: dit zijn alleen weergave-parameters voor de
			// meldingsbanner na een upload, geen actie die state wijzigt —
			// daarom hier bewust geen nonce (de daadwerkelijke upload hierboven
			// is dat wel).
			?>
			<?php if ( isset( $_GET['hdp_upload_status'], $_GET['hdp_upload_bericht'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<?php $status = 'gelukt' === $_GET['hdp_upload_status'] ? 'hdp-admin-melding-ok' : 'hdp-admin-melding-fout'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- alleen vergeleken tegen een vaste letterlijke waarde. ?>
				<div class="hdp-admin-melding <?php echo esc_attr( $status ); ?>">
					<?php echo esc_html( sanitize_text_field( rawurldecode( wp_unslash( $_GET['hdp_upload_bericht'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- al gesanitized (sanitize_text_field) en ge-escaped (esc_html), phpcs ziet dat niet door de rawurldecode()-tussenstap heen. ?>
				</div>
			<?php endif; ?>

			<form method="post" enctype="multipart/form-data" class="hdp-admin-formulier">
				<?php wp_nonce_field( self::NONCE_ACTIE, 'hdp_admin_upload_nonce' ); ?>

				<div class="hdp-veld">
					<label for="hdp_titel">Titel</label>
					<input type="text" id="hdp_titel" name="hdp_titel" required>
				</div>

				<div class="hdp-veld">
					<label for="hdp_bestand">Bestand</label>
					<input type="file" id="hdp_bestand" name="hdp_bestand" required>
				</div>

				<div class="hdp-veld">
					<span class="hdp-admin-label">Categorie</span>
					<label class="hdp-admin-checkbox"><input type="radio" name="hdp_categorie" value="download" checked> Download (prijslijst, handleiding, e.d.)</label>
					<label class="hdp-admin-checkbox"><input type="radio" name="hdp_categorie" value="content"> Content (voor social media/advertenties)</label>
				</div>

				<div class="hdp-veld">
					<label for="hdp_merk">Merk</label>
					<input type="text" id="hdp_merk" name="hdp_merk" placeholder="bijv. HARDI">
				</div>

				<div class="hdp-veld">
					<span class="hdp-admin-label">Regio</span>
					<label class="hdp-admin-checkbox"><input type="checkbox" name="hdp_regio_nl" value="1"> Nederland</label>
					<label class="hdp-admin-checkbox"><input type="checkbox" name="hdp_regio_be" value="1"> België</label>
				</div>

				<button type="submit" class="hdp-btn">Uploaden</button>
			</form>

			<h2 class="hdp-admin-recent-titel">Recent geüpload</h2>
			<?php echo self::render_recente_uploads(); // phpcs:ignore WordPress.Security.EscapeOutput -- reeds ge-escaped in render_recente_uploads(). ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_recente_uploads() {
		$downloads = get_posts(
			array(
				'post_type'      => HDP_Downloads_CPT::POST_TYPE,
				'posts_per_page' => 10,
				'post_status'    => 'publish',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		if ( ! $downloads ) {
			return '<p class="hdp-nog-niet">Nog geen bestanden geüpload.</p>';
		}

		ob_start();
		?>
		<div class="hdp-download-lijst">
			<?php foreach ( $downloads as $download ) : ?>
				<div class="hdp-download-item">
					<span class="hdp-download-type"><?php echo esc_html( HDP_Downloads_CPT::type_label( $download->ID ) ); ?></span>
					<span class="hdp-download-info">
						<strong><?php echo esc_html( $download->post_title ); ?></strong>
						<span>
							<?php
							$categorie = HDP_Downloads_CPT::get_categorie( $download->ID );
							$merk      = HDP_Downloads_CPT::get_merk( $download->ID );
							$regios    = HDP_Downloads_CPT::get_regios( $download->ID );
							echo esc_html( 'content' === $categorie ? 'Content' : 'Download' );
							echo ' · ';
							echo esc_html( $merk ? $merk : 'geen merk' );
							echo ' · ';
							echo esc_html( $regios ? strtoupper( implode( ', ', $regios ) ) : 'geen regio' );
							?>
						</span>
					</span>
					<a class="hdp-btn hdp-btn-secundair" href="<?php echo esc_url( HDP_Downloads_CPT::download_url( $download->ID ) ); ?>">Bekijk</a>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
