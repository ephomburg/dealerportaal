<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rendert het dealerportaal zelf: het inlogscherm (via HDP_Login) voor
 * uitgelogde/niet-goedgekeurde bezoekers, en na inloggen de welkomstkop,
 * "snel herbestellen", de 4 hoofdkaarten en de infosectie.
 *
 * Bewerkbare teksten (kaarten, intro's) hebben een Nederlands en een
 * Frans blokattribuut; HDP_I18N::kies() kiest de juiste op basis van de
 * taalswitch en valt terug op het Nederlands als het Frans nog leeg is.
 * Vaste teksten (labels, knoppen, meldingen) komen uit HDP_I18N::t().
 */
class HDP_Portal_Render {

	public static function render_dealerportaal( $a ) {
		ob_start();

		if ( is_user_logged_in() ) {
			self::render_portal_content( $a );
		} else {
			HDP_Login::render_login( HDP_Login::login_foutmelding(), $a );
		}

		return ob_get_clean();
	}

	private static function render_portal_content( $a ) {
		$user = wp_get_current_user();

		if ( ! HDP_Roles::mag_portaal_zien( $user->ID ) ) {
			?>
			<div class="hdp-login-sectie">
				<div class="hdp-login-kaart">
					<?php HDP_Icons::render_icoon( 'wachten' ); ?>
					<h1><?php echo esc_html( HDP_I18N::t( 'account_titel' ) ); ?></h1>
					<p class="hdp-intro"><?php echo esc_html( HDP_I18N::t( 'account_tekst' ) ); ?></p>
					<a class="hdp-btn-logout" href="<?php echo esc_url( wp_logout_url( home_url( '/dealerportaal/' ) ) ); ?>"><?php echo HDP_Icons::svg_icoon( 'uitloggen' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?><?php echo esc_html( HDP_I18N::t( 'btn_uitloggen' ) ); ?></a>
				</div>
			</div>
			<?php
			return;
		}

		$merken = get_user_meta( $user->ID, 'hdp_merken', true );

		$portaal_intro = HDP_I18N::kies( $a['portaalIntro'], $a['portaalIntroFr'] );
		?>
		<div class="hdp-hero alignfull" style="background-image:url('<?php echo esc_url( $a['heroAfbeelding'] ); ?>')" aria-hidden="true"></div>
		<div class="hdp-portaal alignfull">
			<section class="hdp-welkom alignfull">
				<div class="hdp-welkom-inner">
					<div class="hdp-welkom-top">
						<h1><?php echo esc_html( HDP_I18N::t( 'welkom_prefix' ) ); ?> <?php echo esc_html( $user->display_name ); ?></h1>
					</div>
					<p><?php echo esc_html( $portaal_intro ); ?></p>
					<?php if ( $merken ) : ?>
						<div class="hdp-merken">
							<span class="hdp-merken-label"><?php echo esc_html( HDP_I18N::t( 'geautoriseerd_voor' ) ); ?></span>
							<div class="hdp-merken-logos">
								<?php foreach ( HDP_Merken::naar_array( $merken ) as $hdp_merk ) : ?>
									<?php $hdp_logo_url = HDP_Settings::merk_logo_url( $hdp_merk ); ?>
									<?php if ( $hdp_logo_url ) : ?>
										<img class="hdp-merk-logo" src="<?php echo esc_url( $hdp_logo_url ); ?>" alt="<?php echo esc_attr( $hdp_merk ); ?>">
									<?php else : ?>
										<span class="hdp-merk-badge"><?php echo esc_html( $hdp_merk ); ?></span>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</section>

			<?php HDP_Herbestellen::render(); ?>
		</div>
		<?php
	}
}
