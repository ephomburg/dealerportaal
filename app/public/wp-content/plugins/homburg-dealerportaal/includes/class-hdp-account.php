<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Accountinstellingen voor de ingelogde dealer zelf: weergavenaam en
 * wachtwoord wijzigen (direct), en e-mailadres wijzigen (pas na bevestiging
 * via een link naar het nieuwe adres — voorkomt dat een gekaapte sessie
 * zomaar het e-mailadres kan overnemen). Volgt bewust hetzelfde patroon als
 * HDP_Login: verwerking op het 'init'-hook vóór er HTML is uitgestuurd,
 * Post/Redirect/Get met een ?hdp_instellingen_status=-vlag, geen admin-ajax.
 */
class HDP_Account {

	const EMAIL_WIJZIGING_META = 'hdp_nieuw_email';

	// Rate limiting op het e-mailwijziging-verzoek: zonder dit zou een
	// ingelogde dealer onbeperkt bevestigingsmails naar willekeurige
	// adressen kunnen laten sturen (het account wijzigt dan weliswaar nog
	// niet, maar het is een goedkope manier om deze site als spam-relay te
	// misbruiken). Zelfde soort teller als HDP_Login::MAX_LOGIN_POGINGEN,
	// maar per gebruiker i.p.v. per IP — hier is de "aanvaller" altijd al
	// een ingelogd account, dus IP-gebaseerd limiteren is makkelijk te
	// omzeilen door simpelweg opnieuw in te loggen vanaf een ander netwerk.
	const MAX_EMAIL_POGINGEN     = 3;
	const EMAIL_POGINGEN_VENSTER = HOUR_IN_SECONDS;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'verwerk_instellingen' ) );
		add_action( 'init', array( __CLASS__, 'verwerk_email_wijziging' ) );
		add_action( 'init', array( __CLASS__, 'verwerk_email_annulering' ) );
		add_action( 'init', array( __CLASS__, 'verwerk_email_bevestiging' ) );
	}

	private static function terug_naar_url() {
		$terug_naar = wp_get_referer();
		if ( ! $terug_naar ) {
			$terug_naar = home_url( '/dealerportaal/' );
		}
		return remove_query_arg( 'hdp_instellingen_status', $terug_naar );
	}

	public static function verwerk_instellingen() {
		if ( ! isset( $_POST['hdp_instellingen_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hdp_instellingen_nonce'] ) ), 'hdp_instellingen' ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		wp_safe_redirect( self::verwerk_instellingen_kern( wp_get_current_user(), $_POST, self::terug_naar_url() ) );
		exit;
	}

	/**
	 * Testbare kern van het opslaan van naam/wachtwoord, los van de
	 * wp_safe_redirect()+exit in verwerk_instellingen() zelf (exit is niet
	 * aan te roepen binnen PHPUnit) — zelfde opzet als
	 * HDP_Login::verwerk_inloggegevens(). Voert de daadwerkelijke wijziging
	 * door en geeft de bestemmings-URL (met ?hdp_instellingen_status=) terug.
	 *
	 * @param WP_User $user       De huidige gebruiker.
	 * @param array   $post       Ruwe $_POST-data (of een gelijkwaardige array in tests).
	 * @param string  $terug_naar Basis-URL om naar terug te keren.
	 */
	public static function verwerk_instellingen_kern( $user, array $post, $terug_naar ) {
		$weergavenaam = isset( $post['weergavenaam'] ) ? trim( sanitize_text_field( wp_unslash( $post['weergavenaam'] ) ) ) : '';

		// Wachtwoorden bewust NIET door sanitize_text_field() halen, om
		// dezelfde reden als bij HDP_Login: dat zou geldige speciale tekens
		// kunnen wijzigen.
		$huidig_wachtwoord        = isset( $post['huidig_wachtwoord'] ) ? (string) wp_unslash( $post['huidig_wachtwoord'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$nieuw_wachtwoord         = isset( $post['nieuw_wachtwoord'] ) ? (string) wp_unslash( $post['nieuw_wachtwoord'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$nieuw_wachtwoord_herhaal = isset( $post['nieuw_wachtwoord_herhaal'] ) ? (string) wp_unslash( $post['nieuw_wachtwoord_herhaal'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		if ( '' === $weergavenaam ) {
			return add_query_arg( 'hdp_instellingen_status', 'naam_leeg', $terug_naar );
		}

		// De wachtwoordvelden zijn optioneel: alleen meenemen/valideren als
		// de dealer ook echt een nieuw wachtwoord heeft ingevuld, zodat een
		// dealer de weergavenaam kan opslaan zonder z'n wachtwoord in te
		// hoeven typen.
		$wachtwoord_wijzigen = '' !== $nieuw_wachtwoord || '' !== $nieuw_wachtwoord_herhaal;

		if ( $wachtwoord_wijzigen ) {
			if ( ! wp_check_password( $huidig_wachtwoord, $user->user_pass, $user->ID ) ) {
				return add_query_arg( 'hdp_instellingen_status', 'huidig_fout', $terug_naar );
			}

			if ( strlen( $nieuw_wachtwoord ) < 8 ) {
				return add_query_arg( 'hdp_instellingen_status', 'te_kort', $terug_naar );
			}

			if ( $nieuw_wachtwoord !== $nieuw_wachtwoord_herhaal ) {
				return add_query_arg( 'hdp_instellingen_status', 'mismatch', $terug_naar );
			}
		}

		$update = array(
			'ID'           => $user->ID,
			'display_name' => $weergavenaam,
		);
		if ( $wachtwoord_wijzigen ) {
			$update['user_pass'] = $nieuw_wachtwoord;
		}

		wp_update_user( $update );

		if ( $wachtwoord_wijzigen ) {
			// Een gewijzigd wachtwoord verwijdert via wp_insert_user() alle
			// sessies van deze gebruiker, ook de huidige — zonder de
			// auth-cookie hier opnieuw te zetten zou de dealer meteen na het
			// opslaan uitgelogd worden.
			wp_clear_auth_cookie();
			wp_set_auth_cookie( $user->ID, true );
			wp_set_current_user( $user->ID );
		}

		return add_query_arg( 'hdp_instellingen_status', 'ok', $terug_naar );
	}

	/**
	 * Stap 1 van het e-mailadres wijzigen: valideert het nieuwe adres en
	 * stuurt er een bevestigingslink naartoe. Het account zelf wijzigt hier
	 * nog niets — dat gebeurt pas in verwerk_email_bevestiging() hieronder,
	 * en alleen als die link daadwerkelijk wordt geopend.
	 */
	public static function verwerk_email_wijziging() {
		if ( ! isset( $_POST['hdp_email_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hdp_email_nonce'] ) ), 'hdp_email_wijzig' ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		wp_safe_redirect( self::verwerk_email_wijziging_kern( wp_get_current_user(), $_POST, self::terug_naar_url() ) );
		exit;
	}

	/**
	 * Testbare kern, zelfde opzet als verwerk_instellingen_kern() hierboven.
	 */
	public static function verwerk_email_wijziging_kern( $user, array $post, $terug_naar ) {
		$nieuw_email = isset( $post['nieuw_email'] ) ? sanitize_email( wp_unslash( $post['nieuw_email'] ) ) : '';

		if ( ! is_email( $nieuw_email ) ) {
			return add_query_arg( 'hdp_instellingen_status', 'email_ongeldig', $terug_naar );
		}

		if ( strtolower( $nieuw_email ) === strtolower( $user->user_email ) ) {
			return add_query_arg( 'hdp_instellingen_status', 'email_zelfde', $terug_naar );
		}

		if ( email_exists( $nieuw_email ) ) {
			return add_query_arg( 'hdp_instellingen_status', 'email_in_gebruik', $terug_naar );
		}

		if ( self::email_limiet_bereikt( $user->ID ) ) {
			return add_query_arg( 'hdp_instellingen_status', 'email_te_veel_pogingen', $terug_naar );
		}
		self::tel_email_poging( $user->ID );

		$sleutel = wp_generate_password( 32, false );
		update_user_meta(
			$user->ID,
			self::EMAIL_WIJZIGING_META,
			array(
				'email'    => $nieuw_email,
				'sleutel'  => $sleutel,
				'verloopt' => time() + DAY_IN_SECONDS,
			)
		);

		$bevestig_url = add_query_arg(
			array(
				'hdp_bevestig_email' => $user->ID,
				'sleutel'            => $sleutel,
			),
			home_url( '/dealerportaal/' )
		);

		wp_mail(
			$nieuw_email,
			HDP_I18N::t( 'email_wijzig_onderwerp' ),
			sprintf( HDP_I18N::t( 'email_wijzig_body' ), $bevestig_url )
		);

		return add_query_arg( 'hdp_instellingen_status', 'email_verzonden', $terug_naar );
	}

	private static function email_pogingen_sleutel( $user_id ) {
		return 'hdp_email_pogingen_' . $user_id;
	}

	private static function email_limiet_bereikt( $user_id ) {
		return (int) get_transient( self::email_pogingen_sleutel( $user_id ) ) >= self::MAX_EMAIL_POGINGEN;
	}

	private static function tel_email_poging( $user_id ) {
		$sleutel = self::email_pogingen_sleutel( $user_id );
		$huidig  = (int) get_transient( $sleutel );
		set_transient( $sleutel, $huidig + 1, self::EMAIL_POGINGEN_VENSTER );
	}

	public static function verwerk_email_annulering() {
		if ( ! isset( $_POST['hdp_email_annuleer_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hdp_email_annuleer_nonce'] ) ), 'hdp_email_annuleer' ) ) {
			return;
		}

		if ( ! is_user_logged_in() ) {
			return;
		}

		wp_safe_redirect( self::verwerk_email_annulering_kern( get_current_user_id(), self::terug_naar_url() ) );
		exit;
	}

	public static function verwerk_email_annulering_kern( $user_id, $terug_naar ) {
		delete_user_meta( $user_id, self::EMAIL_WIJZIGING_META );
		return add_query_arg( 'hdp_instellingen_status', 'email_geannuleerd', $terug_naar );
	}

	/**
	 * Stap 2: wordt geopend via de link in de bevestigingsmail. Bewust
	 * zonder nonce/inlogeis — de sleutel in de link is zelf het bewijs dat
	 * de opener toegang heeft tot de nieuwe mailbox, wat precies is wat
	 * hier geverifieerd moet worden (niet of er een actieve sessie is).
	 */
	public static function verwerk_email_bevestiging() {
		if ( ! isset( $_GET['hdp_bevestig_email'], $_GET['sleutel'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$user_id = absint( wp_unslash( $_GET['hdp_bevestig_email'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sleutel = sanitize_text_field( wp_unslash( $_GET['sleutel'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		wp_safe_redirect( self::verwerk_email_bevestiging_kern( $user_id, $sleutel, home_url( '/dealerportaal/' ) ) );
		exit;
	}

	public static function verwerk_email_bevestiging_kern( $user_id, $sleutel, $terug_naar ) {
		$opgeslagen = get_user_meta( $user_id, self::EMAIL_WIJZIGING_META, true );

		if ( ! is_array( $opgeslagen ) || ! isset( $opgeslagen['sleutel'], $opgeslagen['email'], $opgeslagen['verloopt'] )
			|| ! hash_equals( $opgeslagen['sleutel'], $sleutel ) || time() > $opgeslagen['verloopt'] ) {
			return add_query_arg( 'hdp_instellingen_status', 'email_ongeldige_link', $terug_naar );
		}

		wp_update_user(
			array(
				'ID'         => $user_id,
				'user_email' => $opgeslagen['email'],
			)
		);
		delete_user_meta( $user_id, self::EMAIL_WIJZIGING_META );
		delete_transient( self::email_pogingen_sleutel( $user_id ) );

		return add_query_arg( 'hdp_instellingen_status', 'email_bevestigd', $terug_naar );
	}

	/**
	 * Vertaalt de ?hdp_instellingen_status=-vlag naar [status, melding, groep].
	 * $status is 'ok'/'fout'/'' (bepaalt de styling), $groep is 'profiel' of
	 * 'email' (bepaalt bij welk formulier de melding getoond wordt).
	 */
	private static function status_melding() {
		if ( ! isset( $_GET['hdp_instellingen_status'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return array( '', '', '' );
		}
		$code = sanitize_key( wp_unslash( $_GET['hdp_instellingen_status'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$meldingen = array(
			'ok'                   => array( 'ok', 'instellingen_opgeslagen', 'profiel' ),
			'naam_leeg'            => array( 'fout', 'instellingen_fout_naam', 'profiel' ),
			'huidig_fout'          => array( 'fout', 'instellingen_fout_huidig', 'profiel' ),
			'te_kort'              => array( 'fout', 'instellingen_fout_kort', 'profiel' ),
			'mismatch'             => array( 'fout', 'instellingen_fout_mismatch', 'profiel' ),
			'email_verzonden'      => array( 'ok', 'instellingen_email_verzonden', 'email' ),
			'email_bevestigd'      => array( 'ok', 'instellingen_email_bevestigd', 'email' ),
			'email_geannuleerd'    => array( 'ok', 'instellingen_email_geannuleerd', 'email' ),
			'email_ongeldig'       => array( 'fout', 'instellingen_fout_email_ongeldig', 'email' ),
			'email_zelfde'         => array( 'fout', 'instellingen_fout_email_zelfde', 'email' ),
			'email_in_gebruik'     => array( 'fout', 'instellingen_fout_email_in_gebruik', 'email' ),
			'email_ongeldige_link' => array( 'fout', 'instellingen_fout_email_link', 'email' ),
			'email_te_veel_pogingen' => array( 'fout', 'instellingen_fout_email_te_veel_pogingen', 'email' ),
		);

		if ( ! isset( $meldingen[ $code ] ) ) {
			return array( '', '', '' );
		}

		list( $status, $tekst_sleutel, $groep ) = $meldingen[ $code ];
		return array( $status, HDP_I18N::t( $tekst_sleutel ), $groep );
	}

	private static function render_melding( $status, $melding, $groep, $verwachte_groep ) {
		if ( ! $melding || $groep !== $verwachte_groep ) {
			return;
		}
		?>
		<div class="<?php echo 'ok' === $status ? 'hdp-instellingen-melding-ok' : 'hdp-login-fout hdp-zichtbaar'; ?>" role="alert"><?php echo esc_html( $melding ); ?></div>
		<?php
	}

	/**
	 * Tekent zowel de "Instellingen"-knop als het paneel erachter. Het
	 * paneel opent/sluit met een zuivere CSS checkbox-hack (:checked ~ ...),
	 * zodat hier geen eigen front-end JS voor nodig is — de rest van het
	 * portaal heeft dat ook niet. Na een opgeslagen wijziging of foutmelding
	 * staat de checkbox al "checked" zodat het paneel na de PRG-redirect
	 * meteen weer open staat met de melding zichtbaar.
	 */
	public static function render_instellingen_knop_en_paneel() {
		list( $status, $melding, $groep ) = self::status_melding();
		$open                             = '' !== $status;
		$user                             = wp_get_current_user();
		$email_wijziging                  = get_user_meta( $user->ID, self::EMAIL_WIJZIGING_META, true );
		?>
		<input type="checkbox" id="hdp-instellingen-toggle" class="hdp-instellingen-toggle" <?php echo $open ? 'checked' : ''; ?>>
		<label for="hdp-instellingen-toggle" class="hdp-btn-instellingen"><?php echo HDP_Icons::svg_icoon( 'instellingen' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?><?php echo esc_html( HDP_I18N::t( 'btn_instellingen' ) ); ?></label>

		<div class="hdp-instellingen-overlay">
			<label for="hdp-instellingen-toggle" class="hdp-instellingen-achtergrond" aria-hidden="true"></label>
			<div class="hdp-instellingen-kaart hdp-login-kaart" role="dialog" aria-modal="true" aria-labelledby="hdp-instellingen-titel">
				<label for="hdp-instellingen-toggle" class="hdp-instellingen-sluiten" aria-label="<?php echo esc_attr( HDP_I18N::t( 'sluiten' ) ); ?>">&times;</label>
				<h1 id="hdp-instellingen-titel"><?php echo esc_html( HDP_I18N::t( 'instellingen_titel' ) ); ?></h1>
				<p class="hdp-intro"><?php echo esc_html( HDP_I18N::t( 'instellingen_intro' ) ); ?></p>

				<?php self::render_melding( $status, $melding, $groep, 'profiel' ); ?>

				<form method="post">
					<?php wp_nonce_field( 'hdp_instellingen', 'hdp_instellingen_nonce' ); ?>
					<div class="hdp-veld">
						<label for="weergavenaam"><?php echo esc_html( HDP_I18N::t( 'label_weergavenaam' ) ); ?></label>
						<div class="hdp-veld-invoer">
							<?php echo HDP_Icons::svg_icoon( 'gebruiker' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?>
							<input type="text" id="weergavenaam" name="weergavenaam" value="<?php echo esc_attr( $user->display_name ); ?>" required>
						</div>
					</div>

					<p class="hdp-instellingen-subtitel"><?php echo esc_html( HDP_I18N::t( 'instellingen_wachtwoord_titel' ) ); ?></p>
					<div class="hdp-veld">
						<label for="huidig_wachtwoord"><?php echo esc_html( HDP_I18N::t( 'label_huidig_wachtwoord' ) ); ?></label>
						<div class="hdp-veld-invoer">
							<?php echo HDP_Icons::svg_icoon( 'login' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?>
							<input type="password" id="huidig_wachtwoord" name="huidig_wachtwoord" autocomplete="current-password">
						</div>
					</div>
					<div class="hdp-veld">
						<label for="nieuw_wachtwoord"><?php echo esc_html( HDP_I18N::t( 'label_nieuw_wachtwoord' ) ); ?></label>
						<div class="hdp-veld-invoer">
							<?php echo HDP_Icons::svg_icoon( 'login' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?>
							<input type="password" id="nieuw_wachtwoord" name="nieuw_wachtwoord" autocomplete="new-password" minlength="8">
						</div>
					</div>
					<div class="hdp-veld">
						<label for="nieuw_wachtwoord_herhaal"><?php echo esc_html( HDP_I18N::t( 'label_nieuw_wachtwoord_herhaal' ) ); ?></label>
						<div class="hdp-veld-invoer">
							<?php echo HDP_Icons::svg_icoon( 'login' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?>
							<input type="password" id="nieuw_wachtwoord_herhaal" name="nieuw_wachtwoord_herhaal" autocomplete="new-password" minlength="8">
						</div>
					</div>
					<button type="submit" class="hdp-btn"><?php echo esc_html( HDP_I18N::t( 'btn_opslaan' ) ); ?></button>
				</form>

				<p class="hdp-instellingen-subtitel"><?php echo esc_html( HDP_I18N::t( 'instellingen_email_titel' ) ); ?></p>
				<p class="hdp-instellingen-huidig-email"><?php echo esc_html( HDP_I18N::t( 'instellingen_huidig_email_label' ) ); ?> <strong><?php echo esc_html( $user->user_email ); ?></strong></p>

				<?php self::render_melding( $status, $melding, $groep, 'email' ); ?>

				<?php if ( is_array( $email_wijziging ) && isset( $email_wijziging['email'] ) ) : ?>
					<div class="hdp-instellingen-in-afwachting">
						<span><?php echo esc_html( sprintf( HDP_I18N::t( 'instellingen_email_in_afwachting' ), $email_wijziging['email'] ) ); ?></span>
						<form method="post">
							<?php wp_nonce_field( 'hdp_email_annuleer', 'hdp_email_annuleer_nonce' ); ?>
							<button type="submit" class="hdp-wis-filters"><?php echo esc_html( HDP_I18N::t( 'btn_email_annuleren' ) ); ?></button>
						</form>
					</div>
				<?php else : ?>
					<form method="post">
						<?php wp_nonce_field( 'hdp_email_wijzig', 'hdp_email_nonce' ); ?>
						<div class="hdp-veld">
							<label for="nieuw_email"><?php echo esc_html( HDP_I18N::t( 'label_nieuw_email' ) ); ?></label>
							<div class="hdp-veld-invoer">
								<?php echo HDP_Icons::svg_icoon( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?>
								<input type="email" id="nieuw_email" name="nieuw_email" autocomplete="email" required>
							</div>
						</div>
						<button type="submit" class="hdp-btn hdp-btn-secundair"><?php echo esc_html( HDP_I18N::t( 'btn_email_versturen' ) ); ?></button>
					</form>
				<?php endif; ?>
			</div>
		</div>

		<?php
		// Toetsenbord-/screenreadertoegankelijkheid van het paneel hierboven:
		// de checkbox-hack zelf is al met Tab/Spatie te bedienen (de checkbox
		// blijft met opacity:0 gewoon in de taborde), maar CSS kan geen Escape
		// afvangen of focus verplaatsen — vandaar dit kleine, op dit paneel
		// scopede scriptje. Zelfde aanpak als de inline <script> op de
		// downloadspagina (HDP_Downloads_Render): geen front-end framework,
		// alleen vanilla JS waar CSS het simpelweg niet kan.
		?>
		<script>
		(function () {
			var toggle = document.getElementById('hdp-instellingen-toggle');
			var kaart = toggle ? toggle.parentElement.querySelector('.hdp-instellingen-kaart') : null;
			if (!toggle || !kaart) { return; }

			function focusbareElementen() {
				return kaart.querySelectorAll('button, a[href], input:not([type="hidden"]), select, textarea, [tabindex]:not([tabindex="-1"])');
			}

			toggle.addEventListener('change', function () {
				if (toggle.checked) {
					var els = focusbareElementen();
					if (els.length) { els[0].focus(); }
				} else {
					toggle.focus();
				}
			});

			document.addEventListener('keydown', function (e) {
				if (!toggle.checked) { return; }

				if (e.key === 'Escape') {
					toggle.checked = false;
					toggle.focus();
					return;
				}

				if (e.key === 'Tab') {
					var els = focusbareElementen();
					if (!els.length) { return; }
					var eerste = els[0];
					var laatste = els[els.length - 1];
					if (e.shiftKey && document.activeElement === eerste) {
						e.preventDefault();
						laatste.focus();
					} else if (!e.shiftKey && document.activeElement === laatste) {
						e.preventDefault();
						eerste.focus();
					}
				}
			});
		})();
		</script>
		<?php
	}
}
