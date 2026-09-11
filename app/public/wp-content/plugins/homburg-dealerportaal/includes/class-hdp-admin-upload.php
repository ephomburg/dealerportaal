<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Upload-scherm ("adminportaal") + gebruikersoverzicht, beide alleen
 * toegankelijk voor WordPress-beheerders en de in mag_gebruiken() vermelde
 * e-mailadressen.
 *
 * Bewust nu al functioneel: een geüpload bestand + titel + merk + regio
 * wordt direct als "hdp_download" gepubliceerd (hetzelfde systeem als de
 * downloadspagina), zodat het meteen met de juiste tags op /downloads/
 * verschijnt. Wanneer de Supabase-koppeling er is, verandert alleen wáár
 * het bestand wordt opgeslagen — deze pagina en het downloadsysteem zelf
 * hoeven dan niet opnieuw gebouwd te worden.
 */
class HDP_Admin_Upload {

	const NONCE_ACTIE                 = 'hdp_admin_upload';
	const GEBRUIKER_NONCE_ACTIE       = 'hdp_gebruiker_bijwerken';
	const BULK_GOEDKEUREN_NONCE_ACTIE = 'hdp_bulk_goedkeuren';
	const BULK_DOWNLOADS_NONCE_ACTIE  = 'hdp_bulk_downloads';

	/**
	 * E-mailadressen die het adminportaal mogen gebruiken zónder dat ze
	 * een WordPress-Beheerder-account hebben. Iedereen met de ingebouwde
	 * manage_options-capability (elke "Beheerder") mag het portaal sowieso
	 * al gebruiken; deze lijst is alleen voor uitzonderingen daarbovenop.
	 */
	const TOEGESTANE_ADMIN_EMAILS = array( 'ep@homburg-holland.com' );

	public static function init() {
		add_action( 'init', array( __CLASS__, 'registreer_blok' ) );
		add_action( 'init', array( __CLASS__, 'verwerk_upload' ) );
		add_action( 'init', array( __CLASS__, 'verwerk_gebruiker_bijwerken' ) );
		add_action( 'init', array( __CLASS__, 'verwerk_bulk_goedkeuren' ) );
		add_action( 'init', array( __CLASS__, 'verwerk_bulk_downloads' ) );
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
		wp_enqueue_script( 'hdp-dealerportaal', HDP_PLUGIN_URL . 'assets/js/dealerportaal.js', array(), HDP_VERSION, true );
	}

	/**
	 * Centrale toegangscontrole voor het hele adminportaal (upload-scherm
	 * én gebruikersoverzicht) — precies één plek om dit aan te passen.
	 */
	public static function mag_gebruiken() {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		if ( ! is_user_logged_in() ) {
			return false;
		}

		$email             = strtolower( wp_get_current_user()->user_email );
		$toegestane_emails = array_map( 'strtolower', self::TOEGESTANE_ADMIN_EMAILS );
		return in_array( $email, $toegestane_emails, true );
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

	private static function terug_naar_url() {
		$terug_naar = wp_get_referer();
		if ( ! $terug_naar ) {
			$terug_naar = home_url( '/adminportaal/' );
		}
		return remove_query_arg( array( 'hdp_upload_status', 'hdp_upload_bericht' ), $terug_naar );
	}

	public static function verwerk_upload() {
		if ( ! self::mag_gebruiken() ) {
			return;
		}

		if ( ! isset( $_POST['hdp_admin_upload_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hdp_admin_upload_nonce'] ) ), self::NONCE_ACTIE ) ) {
			return;
		}

		$terug_naar = self::terug_naar_url();

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
		if ( ! empty( $_POST['hdp_regio_be_fr'] ) ) {
			$regios[] = 'be-fr';
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

	/**
	 * Verwerkt het opslaan van goedkeuring + merken vanuit één rij in het
	 * gebruikersoverzicht. Zelfde Post/Redirect/Get-patroon als
	 * verwerk_upload() hierboven, met een eigen nonce-actie omdat het een
	 * ander formulier/andere velden betreft.
	 */
	public static function verwerk_gebruiker_bijwerken() {
		if ( ! isset( $_POST['hdp_gebruiker_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hdp_gebruiker_nonce'] ) ), self::GEBRUIKER_NONCE_ACTIE ) ) {
			return;
		}

		if ( ! self::mag_gebruiken() ) {
			return;
		}

		wp_safe_redirect( self::verwerk_gebruiker_bijwerken_kern( $_POST, self::terug_naar_url() ) );
		exit;
	}

	/**
	 * Testbare kern, zelfde opzet als HDP_Account::verwerk_instellingen_kern().
	 */
	public static function verwerk_gebruiker_bijwerken_kern( array $post, $terug_naar ) {
		$gebruiker_id = isset( $post['hdp_gebruiker_id'] ) ? absint( wp_unslash( $post['hdp_gebruiker_id'] ) ) : 0;
		$gebruiker    = get_userdata( $gebruiker_id );

		if ( ! $gebruiker || ! in_array( HDP_Roles::ROLE, (array) $gebruiker->roles, true ) ) {
			return self::status_url( $terug_naar, 'fout', 'Onbekende dealer.' );
		}

		$was_goedgekeurd   = (bool) get_user_meta( $gebruiker_id, 'hdp_goedgekeurd', true );
		$wordt_goedgekeurd = ! empty( $post['hdp_goedgekeurd'] );
		update_user_meta( $gebruiker_id, 'hdp_goedgekeurd', $wordt_goedgekeurd ? '1' : '' );

		if ( $wordt_goedgekeurd && ! $was_goedgekeurd ) {
			HDP_Roles::stuur_goedkeuringsmail( $gebruiker );
		}

		$merken = isset( $post['hdp_merken'] ) ? HDP_Merken::uit_selectie( wp_unslash( $post['hdp_merken'] ) ) : '';
		update_user_meta( $gebruiker_id, 'hdp_merken', $merken );

		return self::status_url( $terug_naar, 'gelukt', $gebruiker->display_name . ' is bijgewerkt.' );
	}

	/**
	 * Bulk-goedkeuren van meerdere dealers tegelijk vanuit het
	 * gebruikersoverzicht — scheelt bij een stapel nieuwe aanvragen het
	 * één-voor-één doorlopen van elke rij. Alleen dealers die nog niet
	 * goedgekeurd zijn krijgen een selectievakje (zie render), dus elke
	 * geselecteerde ID hier is altijd een overgang naar goedgekeurd en
	 * krijgt dus ook de goedkeuringsmail.
	 */
	public static function verwerk_bulk_goedkeuren() {
		if ( ! isset( $_POST['hdp_bulk_goedkeuren_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hdp_bulk_goedkeuren_nonce'] ) ), self::BULK_GOEDKEUREN_NONCE_ACTIE ) ) {
			return;
		}

		if ( ! self::mag_gebruiken() ) {
			return;
		}

		wp_safe_redirect( self::verwerk_bulk_goedkeuren_kern( $_POST, self::terug_naar_url() ) );
		exit;
	}

	public static function verwerk_bulk_goedkeuren_kern( array $post, $terug_naar ) {
		$ids = isset( $post['hdp_bulk_ids'] ) ? array_map( 'absint', (array) wp_unslash( $post['hdp_bulk_ids'] ) ) : array();

		$aantal = 0;
		foreach ( $ids as $gebruiker_id ) {
			$gebruiker = get_userdata( $gebruiker_id );
			if ( ! $gebruiker || ! in_array( HDP_Roles::ROLE, (array) $gebruiker->roles, true ) ) {
				continue;
			}
			if ( (bool) get_user_meta( $gebruiker_id, 'hdp_goedgekeurd', true ) ) {
				continue;
			}

			update_user_meta( $gebruiker_id, 'hdp_goedgekeurd', '1' );
			HDP_Roles::stuur_goedkeuringsmail( $gebruiker );
			++$aantal;
		}

		if ( 0 === $aantal ) {
			return self::status_url( $terug_naar, 'fout', 'Geen dealers geselecteerd om goed te keuren.' );
		}

		return self::status_url( $terug_naar, 'gelukt', $aantal . ' dealer(s) goedgekeurd.' );
	}

	/**
	 * Bulk merk/regio instellen voor meerdere downloads tegelijk. Zet
	 * (vervangt) het merk en/of de regio's van alle geselecteerde
	 * downloads — een los selectievakje per veld ("wijzigen: ja/nee")
	 * bepaalt of dat veld sowieso wordt aangeraakt, zodat "niets aanvinken"
	 * bij regio ondubbelzinnig "regio's wissen" betekent i.p.v. per ongeluk
	 * "laat regio ongemoeid" — die twee zijn met alleen checkboxes niet van
	 * elkaar te onderscheiden.
	 */
	public static function verwerk_bulk_downloads() {
		if ( ! isset( $_POST['hdp_bulk_downloads_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hdp_bulk_downloads_nonce'] ) ), self::BULK_DOWNLOADS_NONCE_ACTIE ) ) {
			return;
		}

		if ( ! self::mag_gebruiken() ) {
			return;
		}

		wp_safe_redirect( self::verwerk_bulk_downloads_kern( $_POST, self::terug_naar_url() ) );
		exit;
	}

	public static function verwerk_bulk_downloads_kern( array $post, $terug_naar ) {
		$ids = isset( $post['hdp_bulk_download_ids'] ) ? array_map( 'absint', (array) wp_unslash( $post['hdp_bulk_download_ids'] ) ) : array();

		if ( ! $ids ) {
			return self::status_url( $terug_naar, 'fout', 'Geen downloads geselecteerd.' );
		}

		$merk_wijzigen = isset( $post['hdp_bulk_merk'] ) && '__ongewijzigd__' !== $post['hdp_bulk_merk'];
		$nieuw_merk    = '';
		if ( $merk_wijzigen ) {
			$nieuw_merk = sanitize_text_field( wp_unslash( $post['hdp_bulk_merk'] ) );
			if ( '' !== $nieuw_merk && ! in_array( $nieuw_merk, HDP_Downloads_CPT::merk_opties(), true ) ) {
				$merk_wijzigen = false;
			}
		}

		$regio_wijzigen = ! empty( $post['hdp_bulk_regio_wijzigen'] );
		$nieuwe_regios  = array();
		if ( $regio_wijzigen ) {
			if ( ! empty( $post['hdp_bulk_regio_nl'] ) ) {
				$nieuwe_regios[] = 'nl';
			}
			if ( ! empty( $post['hdp_bulk_regio_be'] ) ) {
				$nieuwe_regios[] = 'be';
			}
			if ( ! empty( $post['hdp_bulk_regio_be_fr'] ) ) {
				$nieuwe_regios[] = 'be-fr';
			}
		}

		if ( ! $merk_wijzigen && ! $regio_wijzigen ) {
			return self::status_url( $terug_naar, 'fout', 'Kies eerst wat u wilt wijzigen.' );
		}

		$aantal = 0;
		foreach ( $ids as $download_id ) {
			$download = get_post( $download_id );
			if ( ! $download || HDP_Downloads_CPT::POST_TYPE !== $download->post_type ) {
				continue;
			}

			if ( $merk_wijzigen ) {
				update_post_meta( $download_id, '_hdp_merk', $nieuw_merk );
			}
			if ( $regio_wijzigen ) {
				update_post_meta( $download_id, '_hdp_regios', implode( ',', $nieuwe_regios ) );
			}
			++$aantal;
		}

		if ( 0 === $aantal ) {
			return self::status_url( $terug_naar, 'fout', 'Geen geldige downloads gevonden om te wijzigen.' );
		}

		return self::status_url( $terug_naar, 'gelukt', $aantal . ' download(s) bijgewerkt.' );
	}

	private static function status_url( $url, $status, $bericht ) {
		return add_query_arg(
			array(
				'hdp_upload_status'  => $status,
				'hdp_upload_bericht' => rawurlencode( $bericht ),
			),
			$url
		);
	}

	private static function redirect_met_status( $url, $status, $bericht ) {
		wp_safe_redirect( self::status_url( $url, $status, $bericht ) );
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

			<h2 class="hdp-admin-recent-titel">Gebruikers</h2>
			<?php echo self::render_gebruikers_overzicht(); // phpcs:ignore WordPress.Security.EscapeOutput -- reeds ge-escaped in render_gebruikers_overzicht(). ?>

			<h2 class="hdp-admin-recent-titel">Nieuw bestand uploaden</h2>
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
					<select id="hdp_merk" name="hdp_merk">
						<option value="">— Geen merk —</option>
						<?php foreach ( HDP_Downloads_CPT::merk_opties() as $merk ) : ?>
							<option value="<?php echo esc_attr( $merk ); ?>"><?php echo esc_html( $merk ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="hdp-veld">
					<span class="hdp-admin-label">Regio</span>
					<label class="hdp-admin-checkbox"><input type="checkbox" name="hdp_regio_nl" value="1"> Nederland</label>
					<label class="hdp-admin-checkbox"><input type="checkbox" name="hdp_regio_be" value="1"> België</label>
					<label class="hdp-admin-checkbox"><input type="checkbox" name="hdp_regio_be_fr" value="1"> België (Franstalig)</label>
				</div>

				<button type="submit" class="hdp-btn">Uploaden</button>
			</form>

			<h2 class="hdp-admin-recent-titel">Recent geüpload</h2>
			<?php echo self::render_recente_uploads(); // phpcs:ignore WordPress.Security.EscapeOutput -- reeds ge-escaped in render_recente_uploads(). ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Overzicht van alle dealeraccounts, met per rij een eigen mini-formulier
	 * om goedkeuring en toegestane merken direct hier in te stellen — zonder
	 * dat een beheerder daarvoor nog naar het wp-admin-gebruikersprofiel
	 * (HDP_User_Fields) hoeft. Die admin-schermvelden blijven overigens
	 * gewoon bestaan; dit is een tweede, front-end ingang naar dezelfde
	 * user-meta (hdp_goedgekeurd, hdp_merken).
	 */
	private static function render_gebruikers_overzicht() {
		// Zoeken is puur leesgedrag (geen state-wijziging), vandaar geen
		// nonce nodig — zelfde afweging als bij de paginering op de
		// bestelgeschiedenispagina.
		$zoekterm = isset( $_GET['hdp_gz'] ) ? sanitize_text_field( wp_unslash( $_GET['hdp_gz'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$args = array(
			'role'    => HDP_Roles::ROLE,
			'orderby' => 'display_name',
		);
		if ( '' !== $zoekterm ) {
			$args['search']         = '*' . $zoekterm . '*';
			$args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
		}
		$dealers = get_users( $args );

		if ( ! $dealers && '' === $zoekterm ) {
			return '<p class="hdp-nog-niet">Nog geen dealeraccounts.</p>';
		}

		ob_start();
		?>
		<form method="get" class="hdp-gebruikers-zoek">
			<div class="hdp-zoekveld">
				<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
				<input type="text" name="hdp_gz" value="<?php echo esc_attr( $zoekterm ); ?>" placeholder="Zoek dealer op naam of e-mail…">
			</div>
			<?php if ( '' !== $zoekterm ) : ?>
				<a class="hdp-wis-filters" href="<?php echo esc_url( remove_query_arg( 'hdp_gz' ) ); ?>">Filters wissen</a>
			<?php endif; ?>
		</form>

		<?php if ( ! $dealers ) : ?>
			<p class="hdp-nog-niet">Geen dealers gevonden voor "<?php echo esc_html( $zoekterm ); ?>".</p>
		<?php else : ?>
			<?php
			// Los, buiten de per-rij formulieren staand formulier voor de
			// bulkactie hieronder — een <form> kan geen ander <form> bevatten,
			// dus de selectievakjes per rij koppelen via het form-attribuut
			// (HTML5) aan dit formulier i.p.v. dat het formulier de rijen omvat.
			?>
			<form method="post" id="hdp-bulk-goedkeuren-form">
				<?php wp_nonce_field( self::BULK_GOEDKEUREN_NONCE_ACTIE, 'hdp_bulk_goedkeuren_nonce' ); ?>
			</form>
			<?php $er_zijn_niet_goedgekeurden = false; ?>
			<div class="hdp-gebruikers-lijst">
				<?php foreach ( $dealers as $dealer ) : ?>
					<?php
					$goedgekeurd    = (bool) get_user_meta( $dealer->ID, 'hdp_goedgekeurd', true );
					$gekozen_merken = HDP_Merken::naar_array( get_user_meta( $dealer->ID, 'hdp_merken', true ) );
					if ( ! $goedgekeurd ) {
						$er_zijn_niet_goedgekeurden = true;
					}
					?>
					<div class="hdp-gebruiker-item">
						<?php if ( ! $goedgekeurd ) : ?>
							<input type="checkbox" name="hdp_bulk_ids[]" value="<?php echo esc_attr( $dealer->ID ); ?>" form="hdp-bulk-goedkeuren-form" class="hdp-bulk-checkbox" aria-label="Selecteer <?php echo esc_attr( $dealer->display_name ); ?> voor bulk-goedkeuring">
						<?php endif; ?>
						<div class="hdp-gebruiker-info">
							<strong><?php echo esc_html( $dealer->display_name ); ?></strong>
							<span><?php echo esc_html( $dealer->user_email ); ?></span>
						</div>
						<form method="post" class="hdp-gebruiker-formulier">
							<?php wp_nonce_field( self::GEBRUIKER_NONCE_ACTIE, 'hdp_gebruiker_nonce' ); ?>
							<input type="hidden" name="hdp_gebruiker_id" value="<?php echo esc_attr( $dealer->ID ); ?>">
							<label class="hdp-admin-checkbox"><input type="checkbox" name="hdp_goedgekeurd" value="1" <?php checked( $goedgekeurd ); ?>> Goedgekeurd</label>
							<div class="hdp-gebruiker-merken">
								<?php foreach ( HDP_Merken::lijst() as $merk ) : ?>
									<label class="hdp-admin-checkbox"><input type="checkbox" name="hdp_merken[]" value="<?php echo esc_attr( $merk ); ?>" <?php checked( in_array( $merk, $gekozen_merken, true ) ); ?>> <?php echo esc_html( $merk ); ?></label>
								<?php endforeach; ?>
							</div>
							<button type="submit" class="hdp-btn hdp-btn-klein">Opslaan</button>
						</form>
					</div>
				<?php endforeach; ?>
			</div>
			<?php if ( $er_zijn_niet_goedgekeurden ) : ?>
				<p class="hdp-bulk-actie">
					<button type="submit" form="hdp-bulk-goedkeuren-form" class="hdp-btn hdp-btn-secundair hdp-btn-klein">Geselecteerden goedkeuren</button>
				</p>
			<?php endif; ?>
		<?php endif; ?>
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
		<?php
		// Zelfde reden als bij het bulk-goedkeuren-formulier: een los
		// formulier i.p.v. de lijst erin te wikkelen, want <form> mag geen
		// <form> bevatten en elk download-item heeft al geen eigen formulier
		// nodig — het selectievakje koppelt direct aan dit formulier.
		?>
		<form method="post" id="hdp-bulk-downloads-form" class="hdp-bulk-downloads-balk">
			<?php wp_nonce_field( self::BULK_DOWNLOADS_NONCE_ACTIE, 'hdp_bulk_downloads_nonce' ); ?>
			<div class="hdp-veld">
				<label for="hdp_bulk_merk">Merk instellen voor geselecteerde</label>
				<select id="hdp_bulk_merk" name="hdp_bulk_merk">
					<option value="__ongewijzigd__">— Niet wijzigen —</option>
					<option value="">— Geen merk —</option>
					<?php foreach ( HDP_Downloads_CPT::merk_opties() as $merk ) : ?>
						<option value="<?php echo esc_attr( $merk ); ?>"><?php echo esc_html( $merk ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="hdp-veld">
				<span class="hdp-admin-label">Regio instellen voor geselecteerde</span>
				<label class="hdp-admin-checkbox"><input type="checkbox" name="hdp_bulk_regio_wijzigen" value="1"> Regio wijzigen</label>
				<label class="hdp-admin-checkbox"><input type="checkbox" name="hdp_bulk_regio_nl" value="1"> Nederland</label>
				<label class="hdp-admin-checkbox"><input type="checkbox" name="hdp_bulk_regio_be" value="1"> België</label>
				<label class="hdp-admin-checkbox"><input type="checkbox" name="hdp_bulk_regio_be_fr" value="1"> België (Franstalig)</label>
			</div>
			<button type="submit" class="hdp-btn hdp-btn-secundair hdp-btn-klein">Toepassen op geselecteerde</button>
		</form>

		<div class="hdp-download-lijst">
			<?php foreach ( $downloads as $download ) : ?>
				<div class="hdp-download-item">
					<input type="checkbox" name="hdp_bulk_download_ids[]" value="<?php echo esc_attr( $download->ID ); ?>" form="hdp-bulk-downloads-form" class="hdp-bulk-checkbox" aria-label="Selecteer <?php echo esc_attr( $download->post_title ); ?> voor bulkwijziging">
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
