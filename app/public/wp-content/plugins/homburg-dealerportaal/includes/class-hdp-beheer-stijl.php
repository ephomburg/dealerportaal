<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * De beheerkant (wp-admin) in Homburg-stijl (2026-11-12, optie A van het
 * ontwerp): donker menu zoals het portaal, Homburg-rood voor wat actief is
 * en voor knoppen, Montserrat in menu en bovenbalk. In de bovenbalk
 * "HOMBURG BEHEER" in plaats van het WordPress-logo, plus een knop naar het
 * portaal. Op het dashboard een eigen welkomstblok met de drie dingen die
 * hier het vaakst gebeuren.
 *
 * Alleen opmaak en een paar links: aan rechten of schermen verandert niets.
 */
class HDP_Beheer_Stijl {

	const FONT = 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap';

	public static function init() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'laad' ) );
		// De bovenbalk staat ook op de voorkant voor wie ingelogd is als beheerder.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'laad_voorkant' ) );
		// De volgorde in de balk is de volgorde van toevoegen: het merk vóór
		// alles (prioriteit 1), "Naar het portaal" direct na de sitenaam
		// (30), en pas helemaal aan het eind het WordPress-logo en de
		// sitenaam eruit.
		add_action( 'admin_bar_menu', array( __CLASS__, 'merk' ), 1 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'portaalknop' ), 31 );
		add_action( 'admin_bar_menu', array( __CLASS__, 'opruimen' ), 999 );
		add_action( 'admin_init', array( __CLASS__, 'welkom_vervangen' ) );
	}

	public static function laad() {
		wp_enqueue_style( 'hdp-beheer-font', self::FONT, array(), null ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- Google Fonts beheert zelf de versie.
		wp_enqueue_style( 'hdp-beheer', HDP_PLUGIN_URL . 'assets/css/beheer.css', array( 'hdp-beheer-font' ), HDP_VERSION );
	}

	public static function laad_voorkant() {
		if ( is_admin_bar_showing() ) {
			self::laad();
		}
	}

	/**
	 * "HOMBURG BEHEER" helemaal links, naar het dashboard.
	 *
	 * @param WP_Admin_Bar $balk De bovenbalk.
	 */
	public static function merk( $balk ) {
		$balk->add_node(
			array(
				'id'    => 'hdp-merk',
				'title' => 'HOMBURG<span class="hdp-b-sub">BEHEER</span>',
				'href'  => admin_url(),
				'meta'  => array( 'title' => 'Dashboard' ),
			)
		);
	}

	/**
	 * In wp-admin: "Naar het portaal" op de plek van de sitenaam.
	 *
	 * @param WP_Admin_Bar $balk De bovenbalk.
	 */
	public static function portaalknop( $balk ) {
		if ( ! is_admin() ) {
			return;
		}
		$balk->add_node(
			array(
				'id'    => 'hdp-portaal',
				'title' => 'Naar het portaal',
				'href'  => home_url( '/' ),
			)
		);
	}

	/**
	 * Het WordPress-logo eruit, en in wp-admin de sitenaam (die vervangt
	 * "Naar het portaal").
	 *
	 * @param WP_Admin_Bar $balk De bovenbalk.
	 */
	public static function opruimen( $balk ) {
		$balk->remove_node( 'wp-logo' );
		if ( is_admin() ) {
			$balk->remove_node( 'site-name' );
		}
	}

	/** Het welkomstblok van WordPress eruit, dat van Homburg erin. */
	public static function welkom_vervangen() {
		remove_action( 'welcome_panel', 'wp_welcome_panel' );
		add_action( 'welcome_panel', array( __CLASS__, 'welkom' ) );
	}

	public static function welkom() {
		$gebruiker = wp_get_current_user();
		$naam      = $gebruiker->first_name ? $gebruiker->first_name : $gebruiker->display_name;
		?>
		<div class="hdp-b-welkom">
			<span class="hdp-b-welkom-eyebrow">HOMBURG DEALERPORTAAL</span>
			<h2><?php echo esc_html( sprintf( 'Welkom terug, %s', $naam ) ); ?></h2>
			<p>Hier beheer je de downloads, berichten, trainingen en de webshop van het portaal.</p>
			<div class="hdp-b-welkom-knoppen">
				<a class="hdp-b-rood" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . HDP_Downloads_CPT::POST_TYPE ) ); ?>">Download toevoegen</a>
				<a class="hdp-b-wit" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . HDP_Berichten::POST_TYPE ) ); ?>">Bericht plaatsen</a>
				<?php if ( current_user_can( 'manage_options' ) ) : ?>
					<a class="hdp-b-rand" href="<?php echo esc_url( admin_url( 'index.php?page=' . HDP_Gebruik_Scherm::PAGINA ) ); ?>">Portaalgebruik bekijken</a>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}
}
