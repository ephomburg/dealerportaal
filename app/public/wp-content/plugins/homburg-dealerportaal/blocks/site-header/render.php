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
			<div class="hdp-taalswitch" role="group" aria-label="Taal / Langue">
				<a href="<?php echo esc_url( add_query_arg( 'hdp_taal', 'nl' ) ); ?>" class="hdp-taal-knop<?php echo 'nl' === $huidige_taal ? ' hdp-taal-actief' : ''; ?>"<?php echo 'nl' === $huidige_taal ? ' aria-current="true"' : ''; ?>>NL</a>
				<a href="<?php echo esc_url( add_query_arg( 'hdp_taal', 'fr' ) ); ?>" class="hdp-taal-knop<?php echo 'fr' === $huidige_taal ? ' hdp-taal-actief' : ''; ?>"<?php echo 'fr' === $huidige_taal ? ' aria-current="true"' : ''; ?>>FR</a>
			</div>
		</nav>
	</div>
</header>
