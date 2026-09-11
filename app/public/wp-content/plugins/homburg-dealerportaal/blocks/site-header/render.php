<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$huidige_taal = HDP_I18N::huidige_taal();
$link_icoon   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';
?>
<header class="hdp-site-header">
	<div class="hdp-header-inner">
		<a href="/" class="hdp-woordmerk" aria-label="Homburg Dealerportaal – startpagina">
			<img
				class="hdp-logo"
				src="/wp-content/uploads/2026/07/Logo-HOMBURG_WIT-768x102.png"
				srcset="/wp-content/uploads/2026/07/Logo-HOMBURG_WIT-768x102.png 768w, /wp-content/uploads/2026/07/Logo-HOMBURG_WIT-1536x204.png 1536w"
				sizes="150px"
				alt="Homburg"
			>
			<span class="hdp-woordmerk-tekst">
				<span><?php echo esc_html( HDP_I18N::t( 'header_caption' ) ); ?></span>
			</span>
		</a>
		<nav class="hdp-hoofdmenu" aria-label="<?php echo esc_attr( HDP_I18N::t( 'nav_aria' ) ); ?>">
			<a class="hdp-menu-knop" href="https://www.homburg-holland.com" target="_blank" rel="noopener noreferrer">
				<?php echo $link_icoon; // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?>
				Homburg Holland
			</a>
			<a class="hdp-menu-knop" href="https://www.homburg-belgium.com" target="_blank" rel="noopener noreferrer">
				<?php echo $link_icoon; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				Homburg Belgium
			</a>
			<?php if ( class_exists( 'WooCommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() ) ) : ?>
				<?php $hdp_aantal_mandje = WC()->cart ? WC()->cart->get_cart_contents_count() : 0; ?>
				<a class="hdp-mandje-knop" href="<?php echo esc_url( wc_get_cart_url() ); ?>" aria-label="<?php esc_attr_e( 'Winkelmand', 'homburg-dealerportaal' ); ?>">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1.5"/><circle cx="19" cy="21" r="1.5"/><path d="M2 3h3l2.6 12.5a1 1 0 0 0 1 .8h9.7a1 1 0 0 0 1-.8L21 7H6"/></svg>
					<span class="hdp-mandje-aantal"<?php echo $hdp_aantal_mandje ? '' : ' hidden'; ?>><?php echo (int) $hdp_aantal_mandje; ?></span>
				</a>
			<?php endif; ?>
			<?php if ( is_user_logged_in() && class_exists( 'WooCommerce' ) && function_exists( 'homburg_wc_favoriete_ids' ) ) : ?>
				<?php $hdp_aantal_favorieten = count( homburg_wc_favoriete_ids() ); ?>
				<a class="hdp-favorieten-knop" href="<?php echo esc_url( wc_get_account_endpoint_url( 'favorieten' ) ); ?>" aria-label="<?php esc_attr_e( 'Mijn favorieten', 'homburg-dealerportaal' ); ?>">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
					<span class="hdp-favorieten-aantal"<?php echo $hdp_aantal_favorieten ? '' : ' hidden'; ?>><?php echo (int) $hdp_aantal_favorieten; ?></span>
				</a>
			<?php endif; ?>
			<?php if ( is_user_logged_in() && class_exists( 'HDP_Account' ) ) : ?>
				<div class="hdp-header-account">
					<?php HDP_Account::render_instellingen_knop_en_paneel(); ?>
					<a class="hdp-header-uitloggen" href="<?php echo esc_url( wp_logout_url( home_url( '/dealerportaal/' ) ) ); ?>">
						<?php echo HDP_Icons::svg_icoon( 'uitloggen' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?>
						<?php echo esc_html( HDP_I18N::t( 'btn_uitloggen' ) ); ?>
					</a>
				</div>
			<?php endif; ?>
			<div class="hdp-taalswitch" role="group" aria-label="Taal / Langue">
				<a href="<?php echo esc_url( add_query_arg( 'hdp_taal', 'nl' ) ); ?>" class="hdp-taal-knop<?php echo 'nl' === $huidige_taal ? ' hdp-taal-actief' : ''; ?>"<?php echo 'nl' === $huidige_taal ? ' aria-current="true"' : ''; ?>>NL</a>
				<a href="<?php echo esc_url( add_query_arg( 'hdp_taal', 'fr' ) ); ?>" class="hdp-taal-knop<?php echo 'fr' === $huidige_taal ? ' hdp-taal-actief' : ''; ?>"<?php echo 'fr' === $huidige_taal ? ' aria-current="true"' : ''; ?>>FR</a>
			</div>
		</nav>
	</div>
</header>
