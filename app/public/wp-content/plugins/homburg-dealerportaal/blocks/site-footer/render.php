<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$nl_titel = HDP_I18N::kies( 'Homburg Nederland', 'Homburg Pays-Bas' );
$be_titel = HDP_I18N::kies( 'Homburg België', 'Homburg Belgique' );
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
				<p>
					It Noarderfjild 21<br>9051 BM Stiens<br>Nederland<br>
					Tel: <a href="tel:+31582571555">+31 (0)58 257 15 55</a><br>
					E-mail: <a href="mailto:info@homburg-holland.com">info@homburg-holland.com</a>
				</p>
			</div>
			<div>
				<h3><?php echo esc_html( $be_titel ); ?></h3>
				<p>
					Liersesteenweg 211L<br>2220 Heist-op-den-Berg<br>België<br>
					Tel: <a href="tel:+32472942821">+32 (0)472 94 28 21</a><br>
					E-mail: <a href="mailto:info@homburg-belgium.com">info@homburg-belgium.com</a>
				</p>
			</div>
			<div>
				<h3><?php echo esc_html( HDP_I18N::t( 'footer_volg_ons' ) ); ?></h3>
				<p>
					<a href="https://www.facebook.com/HomburgHolland/" target="_blank" rel="noopener noreferrer">Facebook</a><br>
					<a href="https://www.instagram.com/homburgholland/" target="_blank" rel="noopener noreferrer">Instagram</a><br>
					<a href="https://www.linkedin.com/company/3143182" target="_blank" rel="noopener noreferrer">LinkedIn</a><br>
					<a href="https://www.youtube.com/channel/UCaDNVuY2ImMLvmmKsHs0Ylg" target="_blank" rel="noopener noreferrer">YouTube</a>
				</p>
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
