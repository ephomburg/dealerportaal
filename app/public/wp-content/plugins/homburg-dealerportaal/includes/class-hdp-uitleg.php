<?php
/**
 * Korte uitleg voor dealers: "Zo werkt het dealerportaal".
 *
 * Een kaart bovenaan de startpagina met de vijf dingen die je hier kunt
 * doen, elk met één zin en een link. Hij staat er vanzelf tot de dealer op
 * "Begrepen" klikt; daarna is hij terug te halen via het accountmenu
 * rechtsboven ("Uitleg over het portaal").
 *
 * Alleen wat voor deze dealer ook echt bestaat: garantie alleen als die
 * koppeling er is, en bij de webshop staat "bestellen op rekening" pas als
 * bestellen aanstaat.
 *
 * @package Homburg_Dealerportaal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HDP_Uitleg {

	/** Wanneer de dealer op "Begrepen" klikte. */
	const META = 'hdp_uitleg_gezien';

	const VELD  = 'hdp_uitleg_weg';
	const NONCE = 'hdp_uitleg_wegklikken';

	/** ?uitleg=1 haalt de uitleg terug. */
	const PARAM = 'uitleg';

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'verwerk_wegklikken' ) );
	}

	/** De startpagina met de uitleg open. */
	public static function url() {
		return add_query_arg( self::PARAM, '1', home_url( '/dealerportaal/' ) ) . '#hdp-uitleg';
	}

	public static function is_gezien( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		return (bool) get_user_meta( $user_id, self::META, true );
	}

	/** Zelf opgevraagd via het menu (en niet vanzelf getoond). */
	private static function is_opgevraagd() {
		return ! empty( $_GET[ self::PARAM ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- alleen tonen.
	}

	public static function moet_tonen( $user_id = 0 ) {
		return ! self::is_gezien( $user_id ) || self::is_opgevraagd();
	}

	public static function verwerk_wegklikken() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- de nonce wordt hieronder gecontroleerd zodra we weten dát dit onze knop is.
		if ( ! isset( $_POST[ self::VELD ] ) || ! is_user_logged_in() ) {
			return;
		}
		check_admin_referer( self::NONCE );
		update_user_meta( get_current_user_id(), self::META, time() );
		wp_safe_redirect( remove_query_arg( self::PARAM ) );
		exit;
	}

	/**
	 * De stappen, in de volgorde waarin een dealer ze meestal nodig heeft.
	 *
	 * @return array<int, array{icoon: string, titel: string, tekst: string, url: string}>
	 */
	public static function stappen() {
		$stappen = array();

		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$bestellen = function_exists( 'homburg_wc_bestellen_aan' ) && homburg_wc_bestellen_aan();
			$stappen[] = array(
				'icoon' => 'webshop',
				'titel' => HDP_I18N::t( 'uitleg_webshop_titel' ),
				'tekst' => HDP_I18N::t( $bestellen ? 'uitleg_webshop_bestellen' : 'uitleg_webshop_tekst' ),
				'url'   => wc_get_page_permalink( 'shop' ),
			);
		}

		$stappen[] = array(
			'icoon' => 'downloads',
			'titel' => HDP_I18N::t( 'uitleg_downloads_titel' ),
			'tekst' => HDP_I18N::t( 'uitleg_downloads_tekst' ),
			'url'   => home_url( '/downloads/' ),
		);

		if ( class_exists( 'HDP_Garantie' ) && HDP_Garantie::beschikbaar() ) {
			$stappen[] = array(
				'icoon' => 'garantie',
				'titel' => HDP_I18N::t( 'uitleg_garantie_titel' ),
				'tekst' => HDP_I18N::t( 'uitleg_garantie_tekst' ),
				'url'   => home_url( '/garantie/' ),
			);
		}

		if ( class_exists( 'HDP_Trainingen' ) ) {
			$stappen[] = array(
				'icoon' => 'training',
				'titel' => HDP_I18N::t( 'uitleg_trainingen_titel' ),
				'tekst' => HDP_I18N::t( 'uitleg_trainingen_tekst' ),
				'url'   => HDP_Trainingen::url(),
			);
		}

		if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
			$stappen[] = array(
				'icoon' => 'gebruiker',
				'titel' => HDP_I18N::t( 'uitleg_account_titel' ),
				'tekst' => HDP_I18N::t( 'uitleg_account_tekst' ),
				'url'   => wc_get_account_endpoint_url( 'dashboard' ),
			);
		}

		return $stappen;
	}

	public static function render() {
		if ( ! is_user_logged_in() || ! self::moet_tonen() ) {
			return;
		}

		$email    = HDP_Settings::get( 'contact_email' );
		$telefoon = HDP_Settings::get( 'contact_telefoon' );
		$terug    = self::is_gezien();
		?>
		<section class="hdp-uitleg" id="hdp-uitleg" aria-labelledby="hdp-uitleg-kop">
			<div class="hdp-uitleg-boven">
				<div>
					<p class="hdp-uitleg-eyebrow"><?php echo esc_html( HDP_I18N::t( 'uitleg_eyebrow' ) ); ?></p>
					<h2 id="hdp-uitleg-kop" class="hdp-uitleg-kop"><?php echo esc_html( HDP_I18N::t( 'uitleg_kop' ) ); ?></h2>
					<p class="hdp-uitleg-intro"><?php echo esc_html( HDP_I18N::t( 'uitleg_intro' ) ); ?></p>
				</div>
				<?php self::render_knop( $terug ); ?>
			</div>

			<ol class="hdp-uitleg-stappen">
				<?php foreach ( self::stappen() as $stap ) : ?>
					<li>
						<a class="hdp-uitleg-stap" href="<?php echo esc_url( $stap['url'] ); ?>">
							<span class="hdp-uitleg-icoon" aria-hidden="true"><?php echo HDP_Icons::svg_icoon( $stap['icoon'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?></span>
							<strong><?php echo esc_html( $stap['titel'] ); ?></strong>
							<span class="hdp-uitleg-tekst"><?php echo esc_html( $stap['tekst'] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ol>

			<div class="hdp-uitleg-onder">
				<p><?php echo esc_html( HDP_I18N::t( 'uitleg_tip' ) ); ?></p>
				<?php if ( $email || $telefoon ) : ?>
					<p class="hdp-uitleg-vragen">
						<?php echo esc_html( HDP_I18N::t( 'uitleg_vragen' ) ); ?>
						<?php if ( $telefoon ) : ?>
							<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $telefoon ) ); ?>"><?php echo esc_html( $telefoon ); ?></a>
						<?php endif; ?>
						<?php if ( $email && $telefoon ) : ?>
							&middot;
						<?php endif; ?>
						<?php if ( $email ) : ?>
							<a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
						<?php endif; ?>
					</p>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}

	/** "Begrepen" bij de eerste keer; "Sluiten" als de dealer hem zelf terughaalde. */
	private static function render_knop( $terug ) {
		if ( $terug ) {
			?>
			<a class="hdp-uitleg-knop" href="<?php echo esc_url( remove_query_arg( self::PARAM ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'sluiten' ) ); ?></a>
			<?php
			return;
		}
		?>
		<form method="post" class="hdp-uitleg-form">
			<?php wp_nonce_field( self::NONCE ); ?>
			<button type="submit" name="<?php echo esc_attr( self::VELD ); ?>" value="1" class="hdp-uitleg-knop"><?php echo esc_html( HDP_I18N::t( 'uitleg_begrepen' ) ); ?></button>
		</form>
		<?php
	}
}
