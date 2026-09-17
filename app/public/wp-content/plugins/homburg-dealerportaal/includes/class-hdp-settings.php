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
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_media' ) );
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

		add_settings_section( 'hdp_merklogos', "Merklogo's", array( __CLASS__, 'sectie_merklogos_intro' ), 'hdp-instellingen' );
		foreach ( HDP_Merken::lijst() as $hdp_merk ) {
			add_settings_field(
				'hdp_merklogo_' . sanitize_title( $hdp_merk ),
				$hdp_merk,
				array( __CLASS__, 'field_merklogo' ),
				'hdp-instellingen',
				'hdp_merklogos',
				array( 'merk' => $hdp_merk )
			);
		}
	}

	public static function sectie_merklogos_intro() {
		echo '<p>Upload hier per merk een logo (bijv. een transparante PNG). Zodra een merk een logo heeft, verschijnt dat i.p.v. platte tekst op het dashboard van dealers die voor dat merk geautoriseerd zijn.</p>';
	}

	public static function sanitize( $input ) {
		$merk_logos = array();
		if ( isset( $input['merk_logos'] ) && is_array( $input['merk_logos'] ) ) {
			foreach ( $input['merk_logos'] as $hdp_merk => $hdp_attachment_id ) {
				$hdp_attachment_id = absint( $hdp_attachment_id );
				if ( $hdp_attachment_id && in_array( $hdp_merk, HDP_Merken::lijst(), true ) ) {
					$merk_logos[ $hdp_merk ] = $hdp_attachment_id;
				}
			}
		}

		return array(
			'webshop_url'      => isset( $input['webshop_url'] ) ? esc_url_raw( $input['webshop_url'] ) : '',
			'configurator_url' => isset( $input['configurator_url'] ) ? esc_url_raw( $input['configurator_url'] ) : '',
			'merk_logos'       => $merk_logos,
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

	public static function field_merklogo( $args ) {
		$hdp_merk          = $args['merk'];
		$hdp_attachment_id = self::merk_logo_attachment_id( $hdp_merk );
		$hdp_url           = $hdp_attachment_id ? wp_get_attachment_image_url( $hdp_attachment_id, 'medium' ) : '';
		?>
		<div class="hdp-logo-veld">
			<img
				src="<?php echo esc_url( $hdp_url ); ?>"
				alt=""
				class="hdp-logo-preview"
				style="max-height:44px; max-width:180px; display:<?php echo $hdp_url ? 'block' : 'none'; ?>; margin-bottom:8px; background:#f4f5f7; padding:6px; border-radius:4px;"
			>
			<input type="hidden" class="hdp-logo-id" name="<?php echo esc_attr( self::OPTION ); ?>[merk_logos][<?php echo esc_attr( $hdp_merk ); ?>]" value="<?php echo esc_attr( $hdp_attachment_id ); ?>">
			<button type="button" class="button hdp-logo-kies"><?php echo $hdp_attachment_id ? 'Logo wijzigen' : 'Logo kiezen'; ?></button>
			<button type="button" class="button hdp-logo-wis" style="<?php echo $hdp_attachment_id ? '' : 'display:none;'; ?>">Verwijderen</button>
		</div>
		<?php
	}

	private static function merk_logo_attachment_id( $merk ) {
		$opties = get_option( self::OPTION, array() );
		return isset( $opties['merk_logos'][ $merk ] ) ? (int) $opties['merk_logos'][ $merk ] : 0;
	}

	/**
	 * Logo-URL voor een merk, of '' als er (nog) geen logo is geüpload —
	 * de front-end (HDP_Portal_Render) valt in dat geval terug op platte
	 * tekst, dus hoeft nergens zelf te controleren of er een merk bestaat.
	 */
	public static function merk_logo_url( $merk, $size = 'medium' ) {
		$hdp_attachment_id = self::merk_logo_attachment_id( $merk );
		if ( ! $hdp_attachment_id ) {
			return '';
		}
		$hdp_url = wp_get_attachment_image_url( $hdp_attachment_id, $size );
		return $hdp_url ? $hdp_url : '';
	}

	/**
	 * WordPress' eigen media-uploader (dezelfde als bij een uitgelichte
	 * afbeelding), alleen op dit instellingenscherm — één gedeeld scriptje
	 * i.p.v. dat per merk-veld te herhalen (event delegation op de
	 * gedeelde .hdp-logo-kies/.hdp-logo-wis-knoppen).
	 */
	public static function enqueue_media( $hook ) {
		if ( 'settings_page_hdp-instellingen' !== $hook ) {
			return;
		}

		wp_enqueue_media();

		$js = <<<'JS'
(function ($) {
	$(document).on('click', '.hdp-logo-kies', function (e) {
		e.preventDefault();
		var veld = $(this).closest('.hdp-logo-veld');
		var frame = wp.media({
			title: 'Kies een logo',
			button: { text: 'Gebruiken' },
			library: { type: 'image' },
			multiple: false
		});
		frame.on('select', function () {
			var attachment = frame.state().get('selection').first().toJSON();
			var afbeelding = (attachment.sizes && attachment.sizes.medium) ? attachment.sizes.medium.url : attachment.url;
			veld.find('.hdp-logo-id').val(attachment.id);
			veld.find('.hdp-logo-preview').attr('src', afbeelding).show();
			veld.find('.hdp-logo-kies').text('Logo wijzigen');
			veld.find('.hdp-logo-wis').show();
		});
		frame.open();
	});

	$(document).on('click', '.hdp-logo-wis', function (e) {
		e.preventDefault();
		var veld = $(this).closest('.hdp-logo-veld');
		veld.find('.hdp-logo-id').val('');
		veld.find('.hdp-logo-preview').hide();
		veld.find('.hdp-logo-kies').text('Logo kiezen');
		$(this).hide();
	});
})(jQuery);
JS;

		wp_register_script( 'hdp-instellingen-logos', '', array( 'jquery' ), HDP_VERSION, true );
		wp_enqueue_script( 'hdp-instellingen-logos' );
		wp_add_inline_script( 'hdp-instellingen-logos', $js );
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
