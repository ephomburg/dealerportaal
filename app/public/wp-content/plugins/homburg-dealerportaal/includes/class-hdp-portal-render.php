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

		$merken           = get_user_meta( $user->ID, 'hdp_merken', true );
		$webshop_url      = HDP_Settings::get( 'webshop_url' );
		$configurator_url = HDP_Settings::get( 'configurator_url' );

		$portaal_intro       = HDP_I18N::kies( $a['portaalIntro'], $a['portaalIntroFr'] );
		$kaart1_titel        = HDP_I18N::kies( $a['kaart1Titel'], $a['kaart1TitelFr'] );
		$kaart1_omschrijving = HDP_I18N::kies( $a['kaart1Omschrijving'], $a['kaart1OmschrijvingFr'] );
		$kaart1_knoptekst    = HDP_I18N::kies( $a['kaart1Knoptekst'], $a['kaart1KnoptekstFr'] );
		$kaart2_titel        = HDP_I18N::kies( $a['kaart2Titel'], $a['kaart2TitelFr'] );
		$kaart2_omschrijving = HDP_I18N::kies( $a['kaart2Omschrijving'], $a['kaart2OmschrijvingFr'] );
		$kaart2_knoptekst    = HDP_I18N::kies( $a['kaart2Knoptekst'], $a['kaart2KnoptekstFr'] );
		$kaart3_titel        = HDP_I18N::kies( $a['kaart3Titel'], $a['kaart3TitelFr'] );
		$kaart3_omschrijving = HDP_I18N::kies( $a['kaart3Omschrijving'], $a['kaart3OmschrijvingFr'] );
		$kaart3_knoptekst    = HDP_I18N::kies( $a['kaart3Knoptekst'], $a['kaart3KnoptekstFr'] );
		$kaart4_titel        = HDP_I18N::kies( $a['kaart4Titel'], $a['kaart4TitelFr'] );
		$kaart4_omschrijving = HDP_I18N::kies( $a['kaart4Omschrijving'], $a['kaart4OmschrijvingFr'] );
		$kaart4_knoptekst    = HDP_I18N::kies( $a['kaart4Knoptekst'], $a['kaart4KnoptekstFr'] );
		?>
		<div class="hdp-hero alignfull" style="background-image:url('<?php echo esc_url( $a['heroAfbeelding'] ); ?>')" aria-hidden="true"></div>
		<div class="hdp-portaal alignfull">
			<section class="hdp-welkom alignfull">
				<div class="hdp-welkom-inner">
					<div class="hdp-welkom-top">
						<h1><?php echo esc_html( HDP_I18N::t( 'welkom_prefix' ) ); ?> <?php echo esc_html( $user->display_name ); ?></h1>
						<a class="hdp-btn-logout" href="<?php echo esc_url( wp_logout_url( home_url( '/dealerportaal/' ) ) ); ?>"><?php echo HDP_Icons::svg_icoon( 'uitloggen' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?><?php echo esc_html( HDP_I18N::t( 'btn_uitloggen' ) ); ?></a>
					</div>
					<p><?php echo esc_html( $portaal_intro ); ?></p>
					<?php if ( $merken ) : ?>
						<p class="hdp-merken"><?php echo esc_html( HDP_I18N::t( 'geautoriseerd_voor' ) ); ?> <?php echo esc_html( $merken ); ?></p>
					<?php endif; ?>
				</div>
			</section>

			<?php HDP_Herbestellen::render(); ?>

			<section class="hdp-kaarten-sectie" aria-label="Portaalopties">
				<article class="hdp-kaart">
					<?php HDP_Icons::render_icoon( 'webshop' ); ?>
					<h2><?php echo esc_html( $kaart1_titel ); ?></h2>
					<p><?php echo esc_html( $kaart1_omschrijving ); ?></p>
					<?php if ( $webshop_url ) : ?>
						<a class="hdp-btn" href="<?php echo esc_url( $webshop_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $kaart1_knoptekst ); ?></a>
					<?php else : ?>
						<a class="hdp-btn" href="#"><?php echo esc_html( $kaart1_knoptekst ); ?></a>
					<?php endif; ?>
				</article>

				<article class="hdp-kaart">
					<?php HDP_Icons::render_icoon( 'configurator' ); ?>
					<h2><?php echo esc_html( $kaart2_titel ); ?></h2>
					<p><?php echo esc_html( $kaart2_omschrijving ); ?></p>
					<?php if ( $configurator_url ) : ?>
						<a class="hdp-btn" href="<?php echo esc_url( $configurator_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $kaart2_knoptekst ); ?></a>
					<?php else : ?>
						<p class="hdp-nog-niet"><?php echo esc_html( HDP_I18N::t( 'nog_niet_geconfigureerd' ) ); ?></p>
					<?php endif; ?>
				</article>

				<article class="hdp-kaart">
					<?php HDP_Icons::render_icoon( 'downloads' ); ?>
					<h2><?php echo esc_html( $kaart3_titel ); ?></h2>
					<p><?php echo esc_html( $kaart3_omschrijving ); ?></p>
					<a class="hdp-btn" href="<?php echo esc_url( home_url( '/downloads/' ) ); ?>"><?php echo esc_html( $kaart3_knoptekst ); ?></a>
				</article>

				<article class="hdp-kaart">
					<?php HDP_Icons::render_icoon( 'content' ); ?>
					<h2><?php echo esc_html( $kaart4_titel ); ?></h2>
					<p><?php echo esc_html( $kaart4_omschrijving ); ?></p>
					<a class="hdp-btn" href="<?php echo esc_url( home_url( '/content/' ) ); ?>"><?php echo esc_html( $kaart4_knoptekst ); ?></a>
				</article>
			</section>
		</div>
		<?php
	}
}
