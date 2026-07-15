<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Vaste merknamen, bewust hetzelfde in NL en FR (zoals ook "Homburg
// Belgium" in de header altijd Engels blijft, ongeacht de taalkeuze).
$nl_titel = 'Homburg Holland';
$be_titel = 'Homburg Belgium';

$tel_icoon  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>';
$mail_icoon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z" style="display:none"/><path d="M22 6c0-1.1-.9-2-2-2H4a2 2 0 0 0-2 2v12c0 1.1.9 2 2 2h16a2 2 0 0 0 2-2V6z"/><path d="m22 6-10 7L2 6"/></svg>';

$socials = array(
	'Facebook'  => array(
		'url'  => 'https://www.facebook.com/HomburgHolland/',
		'path' => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
	),
	'Instagram' => array(
		'url'  => 'https://www.instagram.com/homburgholland/',
		'path' => '<rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>',
	),
	'LinkedIn'  => array(
		'url'  => 'https://www.linkedin.com/company/3143182',
		'path' => '<path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-2-2 2 2 0 0 0-2 2v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/>',
	),
	'YouTube'   => array(
		'url'  => 'https://www.youtube.com/channel/UCaDNVuY2ImMLvmmKsHs0Ylg',
		'path' => '<path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29.94 29.94 0 0 0 1 11.75a29.94 29.94 0 0 0 .46 5.33A2.78 2.78 0 0 0 3.4 19c1.72.46 8.6.46 8.6.46s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2 29.94 29.94 0 0 0 .46-5.25 29.94 29.94 0 0 0-.46-5.33z"/><polygon points="9.75 15.02 15.5 11.75 9.75 8.48 9.75 15.02"/>',
	),
);
?>
<footer class="hdp-site-footer">
	<div class="hdp-footer-boven">
		<div class="hdp-footer-logo">
			<img
				src="/wp-content/uploads/2026/07/Logo-HOMBURG_WIT-768x102.png"
				srcset="/wp-content/uploads/2026/07/Logo-HOMBURG_WIT-768x102.png 768w, /wp-content/uploads/2026/07/Logo-HOMBURG_WIT-1536x204.png 1536w"
				sizes="180px"
				alt="Homburg"
			>
		</div>
		<div class="hdp-footer-inner">
			<div>
				<h3><?php echo esc_html( $nl_titel ); ?></h3>
				<p class="hdp-footer-adres">It Noarderfjild 21<br>9051 BM Stiens<br>Nederland</p>
				<div class="hdp-footer-contact">
					<a href="tel:+31582571555"><?php echo $tel_icoon; // phpcs:ignore WordPress.Security.EscapeOutput ?>+31 (0)58 257 15 55</a>
					<a href="mailto:info@homburg-holland.com"><?php echo $mail_icoon; // phpcs:ignore WordPress.Security.EscapeOutput ?>info@homburg-holland.com</a>
				</div>
			</div>
			<div>
				<h3><?php echo esc_html( $be_titel ); ?></h3>
				<p class="hdp-footer-adres">Liersesteenweg 211L<br>2220 Heist-op-den-Berg<br>België</p>
				<div class="hdp-footer-contact">
					<a href="tel:+32472942821"><?php echo $tel_icoon; // phpcs:ignore WordPress.Security.EscapeOutput ?>+32 (0)472 94 28 21</a>
					<a href="mailto:info@homburg-belgium.com"><?php echo $mail_icoon; // phpcs:ignore WordPress.Security.EscapeOutput ?>info@homburg-belgium.com</a>
				</div>
			</div>
			<div>
				<h3><?php echo esc_html( HDP_I18N::t( 'footer_volg_ons' ) ); ?></h3>
				<div class="hdp-footer-social">
					<?php foreach ( $socials as $naam => $social ) : ?>
						<a class="hdp-social-icoon" href="<?php echo esc_url( $social['url'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $naam ); ?>">
							<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><?php echo $social['path']; // phpcs:ignore WordPress.Security.EscapeOutput ?></svg>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
	</div>
	<div class="hdp-footer-onder">
		&copy; <span id="hdp-jaartal"></span> <?php echo esc_html( HDP_I18N::t( 'footer_copyright' ) ); ?> ·
		<a href="https://www.homburg-holland.com/nl/privacy-statement/" target="_blank" rel="noopener noreferrer"><?php echo esc_html( HDP_I18N::t( 'footer_privacy' ) ); ?></a> ·
		<a href="/adminportaal/"><?php echo esc_html( HDP_I18N::t( 'footer_admin' ) ); ?></a>
	</div>
	<script>
		document.getElementById('hdp-jaartal').textContent = new Date().getFullYear();
	</script>
</footer>
