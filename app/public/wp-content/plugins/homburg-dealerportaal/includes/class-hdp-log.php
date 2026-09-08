<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lichte foutmonitoring voor deze plugin. Vangt:
 * - fatale PHP-fouten (register_shutdown_function) die anders een witte
 *   pagina / stille 500 geven;
 * - niet-afgevangen excepties (set_exception_handler);
 * - expliciete meldingen vanuit de plugincode via HDP_Log::schrijf().
 *
 * Alleen fouten waarvan het bestandspad in deze plugin ligt worden gelogd —
 * geen ruis van WordPress-core of andere plugins. Log komt in
 * wp-content/uploads/hdp-logs/hdp-JJJJ-MM.log (per maand, map afgeschermd).
 * Bij een fatale fout gaat er ook een (gethrottelde) e-mail naar het
 * beheeradres.
 *
 * Bekijken: Instellingen > Dealerportaal foutenlog.
 */
class HDP_Log {

	const MAP            = 'hdp-logs';
	const BEHEER_EMAIL   = 'marketing@homburg-holland.com';
	const EMAIL_THROTTLE = 900; // seconden dat dezelfde fout geen 2e mail stuurt.

	/** @var callable|null Vorige exception-handler, om door te ketenen. */
	private static $vorige_exception_handler = null;

	public static function init() {
		self::$vorige_exception_handler = set_exception_handler( array( __CLASS__, 'vang_exceptie' ) );
		register_shutdown_function( array( __CLASS__, 'vang_fatale_fout' ) );
		add_action( 'admin_menu', array( __CLASS__, 'registreer_menu' ) );
	}

	/* ---------------------------------------------------------------------
	 * Schrijven
	 * ------------------------------------------------------------------- */

	/**
	 * Schrijf een regel naar het maandlogbestand.
	 *
	 * @param string $bericht Vrije tekst.
	 * @param string $niveau  INFO | WAARSCHUWING | FOUT | FATAAL.
	 */
	public static function schrijf( $bericht, $niveau = 'FOUT' ) {
		$map = self::map_pad();
		if ( ! $map ) {
			return;
		}

		$regel = sprintf(
			"[%s] %s: %s%s\n",
			gmdate( 'Y-m-d H:i:s' ),
			$niveau,
			trim( preg_replace( '/\s+/', ' ', $bericht ) ),
			'' !== ( $ctx = self::context() ) ? '  ' . $ctx : ''
		);

		$bestand = $map . '/hdp-' . gmdate( 'Y-m' ) . '.log';
		// LOCK_EX: meerdere requests tegelijk mogen elkaars regel niet halveren.
		@file_put_contents( $bestand, $regel, FILE_APPEND | LOCK_EX );

		if ( 'FATAAL' === $niveau ) {
			self::mail_bij_fataal( $bericht );
		}
	}

	/* ---------------------------------------------------------------------
	 * Vangers
	 * ------------------------------------------------------------------- */

	public static function vang_exceptie( $exceptie ) {
		if ( self::in_plugin( $exceptie->getFile() ) ) {
			self::schrijf(
				sprintf(
					'Onafgevangen %s: %s in %s:%d',
					get_class( $exceptie ),
					$exceptie->getMessage(),
					self::kort_pad( $exceptie->getFile() ),
					$exceptie->getLine()
				),
				'FATAAL'
			);
		}

		// Doorketenen naar de vorige handler, zodat WordPress' eigen
		// afhandeling (foutpagina) niet wegvalt.
		if ( self::$vorige_exception_handler && is_callable( self::$vorige_exception_handler ) ) {
			call_user_func( self::$vorige_exception_handler, $exceptie );
		}
	}

	public static function vang_fatale_fout() {
		$fout = error_get_last();
		if ( ! $fout ) {
			return;
		}

		$fataal = array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR );
		if ( ! in_array( $fout['type'], $fataal, true ) || ! self::in_plugin( $fout['file'] ) ) {
			return;
		}

		self::schrijf(
			sprintf(
				'Fatale fout: %s in %s:%d',
				$fout['message'],
				self::kort_pad( $fout['file'] ),
				$fout['line']
			),
			'FATAAL'
		);
	}

	/* ---------------------------------------------------------------------
	 * E-mail
	 * ------------------------------------------------------------------- */

	private static function mail_bij_fataal( $bericht ) {
		$sleutel = 'hdp_log_mail_' . md5( $bericht );
		if ( get_transient( $sleutel ) ) {
			return; // Recent al gemaild over exact deze fout.
		}
		set_transient( $sleutel, 1, self::EMAIL_THROTTLE );

		$site = wp_parse_url( home_url(), PHP_URL_HOST );
		wp_mail(
			self::BEHEER_EMAIL,
			sprintf( '[%s] Fatale fout in het dealerportaal', $site ),
			"Er is een fatale fout opgetreden in de dealerportaal-plugin op {$site}.\n\n"
				. trim( $bericht ) . "\n\n"
				. "Volledige log: WordPress-beheer > Instellingen > Dealerportaal foutenlog.\n"
				. "(Herhalingen van dezelfde fout worden " . ( self::EMAIL_THROTTLE / 60 ) . " minuten onderdrukt.)"
		);
	}

	/* ---------------------------------------------------------------------
	 * Beheerscherm
	 * ------------------------------------------------------------------- */

	public static function registreer_menu() {
		add_submenu_page(
			'options-general.php',
			'Dealerportaal foutenlog',
			'Dealerportaal foutenlog',
			'manage_options',
			'hdp-foutenlog',
			array( __CLASS__, 'render_scherm' )
		);
	}

	public static function render_scherm() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$map     = self::map_pad();
		$bestand = $map ? $map . '/hdp-' . gmdate( 'Y-m' ) . '.log' : '';

		// Wissen.
		if ( isset( $_POST['hdp_log_wissen'] ) && check_admin_referer( 'hdp_log_wissen' ) ) {
			if ( $bestand && file_exists( $bestand ) ) {
				@unlink( $bestand );
			}
			echo '<div class="notice notice-success"><p>Logbestand van deze maand gewist.</p></div>';
		}

		$regels = ( $bestand && file_exists( $bestand ) )
			? array_reverse( array_slice( file( $bestand, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ), -200 ) )
			: array();
		?>
		<div class="wrap">
			<h1>Dealerportaal foutenlog</h1>
			<p>Fouten uit de dealerportaal-plugin, nieuwste eerst — maand <?php echo esc_html( gmdate( 'Y-m' ) ); ?>.
				Bij een <strong>fatale</strong> fout gaat er ook een e-mail naar <?php echo esc_html( self::BEHEER_EMAIL ); ?>.</p>

			<?php if ( $regels ) : ?>
				<form method="post" style="margin:1em 0">
					<?php wp_nonce_field( 'hdp_log_wissen' ); ?>
					<button type="submit" name="hdp_log_wissen" class="button"
						onclick="return confirm('Logbestand van deze maand wissen?')">Logbestand wissen</button>
				</form>
				<textarea readonly rows="24" style="width:100%;font-family:monospace;font-size:12px;white-space:pre"><?php
					echo esc_textarea( implode( "\n", $regels ) );
				?></textarea>
			<?php else : ?>
				<p><em>Geen fouten gelogd deze maand.</em></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------- */

	/** Absoluut pad naar de (afgeschermde) logmap; maakt 'm aan indien nodig. */
	private static function map_pad() {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return '';
		}
		$map = rtrim( $uploads['basedir'], '/\\' ) . '/' . self::MAP;
		if ( ! is_dir( $map ) ) {
			wp_mkdir_p( $map );
			@file_put_contents( $map . '/.htaccess', "Require all denied\n" );
			@file_put_contents( $map . '/index.html', '' );
		}
		return $map;
	}

	private static function in_plugin( $bestand ) {
		return $bestand && false !== strpos( wp_normalize_path( $bestand ), 'plugins/homburg-dealerportaal/' );
	}

	private static function kort_pad( $bestand ) {
		$bestand = wp_normalize_path( $bestand );
		$pos     = strpos( $bestand, 'homburg-dealerportaal/' );
		return false !== $pos ? substr( $bestand, $pos ) : $bestand;
	}

	private static function context() {
		$delen = array();
		if ( ! empty( $_SERVER['REQUEST_URI'] ) ) {
			$delen[] = 'url=' . esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		}
		$uid = function_exists( 'get_current_user_id' ) ? get_current_user_id() : 0;
		if ( $uid ) {
			$delen[] = 'user=' . $uid;
		}
		return $delen ? '(' . implode( ' ', $delen ) . ')' : '';
	}
}
