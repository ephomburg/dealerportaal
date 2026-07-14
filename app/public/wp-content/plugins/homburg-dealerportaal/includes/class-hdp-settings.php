<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Instellingenscherm (Instellingen > Dealerportaal) voor de externe
 * webshop- en configurator-URL's, zodat Homburg deze zelf kan invullen
 * zonder in de code te hoeven wijzigen.
 */
class HDP_Settings {

	const OPTION = 'hdp_instellingen';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	public static function register_menu() {
		add_options_page(
			'Dealerportaal',
			'Dealerportaal',
			'manage_options',
			'hdp-instellingen',
			array( __CLASS__, 'render_page' )
		);
	}

	public static function register_settings() {
		register_setting( 'hdp_instellingen_group', self::OPTION, array( __CLASS__, 'sanitize' ) );

		add_settings_section( 'hdp_urls', 'Externe koppelingen', '__return_false', 'hdp-instellingen' );

		add_settings_field( 'webshop_url', 'Webshop-URL', array( __CLASS__, 'field_webshop_url' ), 'hdp-instellingen', 'hdp_urls' );
		add_settings_field( 'configurator_url', 'Productconfigurator-URL', array( __CLASS__, 'field_configurator_url' ), 'hdp-instellingen', 'hdp_urls' );
	}

	public static function sanitize( $input ) {
		return array(
			'webshop_url'      => isset( $input['webshop_url'] ) ? esc_url_raw( $input['webshop_url'] ) : '',
			'configurator_url' => isset( $input['configurator_url'] ) ? esc_url_raw( $input['configurator_url'] ) : '',
		);
	}

	public static function get( $key ) {
		$opties = get_option( self::OPTION, array() );
		return isset( $opties[ $key ] ) ? $opties[ $key ] : '';
	}

	public static function field_webshop_url() {
		printf(
			'<input type="url" name="%s[webshop_url]" value="%s" class="regular-text" placeholder="https://">',
			esc_attr( self::OPTION ),
			esc_attr( self::get( 'webshop_url' ) )
		);
	}

	public static function field_configurator_url() {
		printf(
			'<input type="url" name="%s[configurator_url]" value="%s" class="regular-text" placeholder="https://">',
			esc_attr( self::OPTION ),
			esc_attr( self::get( 'configurator_url' ) )
		);
	}

	public static function render_page() {
		?>
		<div class="wrap">
			<h1>Dealerportaal-instellingen</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'hdp_instellingen_group' );
				do_settings_sections( 'hdp-instellingen' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
