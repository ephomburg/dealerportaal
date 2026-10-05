<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nette "hier kunt u niet verder"-schermen in de huisstijl van het portaal,
 * in plaats van het kale witte WordPress-foutscherm.
 *
 * Aanleiding: het downloadendpoint stopt bij geen toegang met wp_die(). Dat
 * levert een pagina zonder logo, zonder menu, zonder taalkeuze en zonder weg
 * terug — een dealer die daar belandt denkt dat het portaal stuk is, terwijl
 * het bestand simpelweg niet voor dat account bedoeld is. Sinds de
 * merkcontrole in het endpoint zit (1.51.0) is dat scherm ook nog eens
 * vaker bereikbaar.
 *
 * Werkt via WordPress' eigen wp_die_handler-filter, maar alleen voor onze
 * eigen aanroepen: die geven $args['hdp_portaal'] mee. Al het andere
 * (WordPress zelf, andere plugins) gaat gewoon naar de standaardafhandeling.
 * In de testomgeving hangt PHPUnit zijn eigen handler ná deze in dezelfde
 * filter, waardoor die wint en wp_die() daar netjes een WPDieException
 * blijft gooien — de tests merken hier dus niets van.
 */
class HDP_Foutscherm {

	public static function init() {
		add_filter( 'wp_die_handler', array( __CLASS__, 'kies_handler' ) );
	}

	public static function kies_handler( $handler ) {
		return array( __CLASS__, 'toon' );
	}

	/**
	 * Is dit een van onze eigen wp_die()-aanroepen? Alleen die krijgen het
	 * portaalscherm; WordPress' eigen foutmeldingen en die van andere
	 * plugins moeten hun normale afhandeling houden.
	 */
	public static function is_portaalscherm( $args ) {
		return is_array( $args ) && ! empty( $args['hdp_portaal'] );
	}

	public static function toon( $bericht, $titel, $args ) {
		if ( ! self::is_portaalscherm( $args ) ) {
			_default_wp_die_handler( $bericht, $titel, $args );
			return;
		}

		$status = isset( $args['response'] ) ? (int) $args['response'] : 500;

		if ( ! headers_sent() ) {
			status_header( $status );
			nocache_headers();
			header( 'Content-Type: text/html; charset=utf-8' );
		}

		echo self::html( $args['hdp_portaal'], $status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- html() bouwt de pagina zelf op en escapet daarbinnen per veld.
		exit;
	}

	/**
	 * Stopt de afhandeling met een portaalscherm. Het meegegeven $bericht
	 * blijft de platte tekst voor de standaardafhandeling (en voor de
	 * testomgeving); welk scherm getoond wordt bepaalt $sleutel.
	 */
	public static function stop( $sleutel, $bericht, $titel, $status ) {
		wp_die(
			esc_html( $bericht ),
			esc_html( $titel ),
			array(
				'response'    => absint( $status ),
				'hdp_portaal' => sanitize_key( $sleutel ),
			)
		);
	}

	/**
	 * De schermen. Per sleutel een titel, uitleg en of er een contactregel
	 * onder hoort — bewust uitleg die zegt wat er aan de hand is en wat de
	 * dealer kan doen, niet alleen "geen toegang".
	 */
	private static function schermen() {
		return array(
			'niet_ingelogd'   => array(
				'icoon'   => 'slot',
				'titel'   => array( 'nl' => 'U bent niet meer ingelogd', 'fr' => 'Vous n\'êtes plus connecté' ),
				'tekst'   => array(
					'nl' => 'Uw sessie is verlopen. Log opnieuw in om dit bestand te downloaden.',
					'fr' => 'Votre session a expiré. Reconnectez-vous pour télécharger ce fichier.',
				),
				'contact' => false,
			),
			'wacht_goedkeuring' => array(
				'icoon'   => 'wachten',
				'titel'   => array( 'nl' => 'Uw account is nog in behandeling', 'fr' => 'Votre compte est en cours de validation' ),
				'tekst'   => array(
					'nl' => 'Zodra uw account is goedgekeurd kunt u alle bestanden in het dealerportaal downloaden. U krijgt hier automatisch bericht van.',
					'fr' => 'Dès que votre compte sera approuvé, vous pourrez télécharger tous les fichiers du portail concessionnaire. Vous en serez averti automatiquement.',
				),
				'contact' => true,
			),
			'geen_merkrecht'  => array(
				'icoon'   => 'slot',
				'titel'   => array( 'nl' => 'Dit bestand hoort bij een ander merk', 'fr' => 'Ce fichier concerne une autre marque' ),
				'tekst'   => array(
					'nl' => 'Dit bestand hoort bij een merk dat niet aan uw account gekoppeld is. Voert u dit merk wel? Neem dan even contact op, dan zetten we het voor u open.',
					'fr' => "Ce fichier concerne une marque qui n'est pas liée à votre compte. Vous distribuez cette marque ? Contactez-nous et nous l'activerons pour vous.",
				),
				'contact' => true,
			),
			'niet_gevonden'   => array(
				'icoon'   => 'downloads',
				'titel'   => array( 'nl' => 'Dit bestand bestaat niet meer', 'fr' => "Ce fichier n'existe plus" ),
				'tekst'   => array(
					'nl' => 'Het bestand is verplaatst of verwijderd. Op de downloadspagina staat de actuele lijst.',
					'fr' => 'Le fichier a été déplacé ou supprimé. La liste actuelle se trouve sur la page de téléchargements.',
				),
				'contact' => false,
			),
		);
	}

	/**
	 * Bouwt de volledige pagina. Los van toon() zodat dit getest kan worden
	 * zonder de exit (niet aanroepbaar binnen PHPUnit).
	 *
	 * Bewust een op zichzelf staande pagina en niet het blokthema-sjabloon:
	 * dit draait midden in een afgebroken request, waar de normale
	 * paginaopbouw niet meer betrouwbaar is. De huisstijl komt uit dezelfde
	 * stylesheet als de rest van het portaal; de kleurvariabelen daarvan
	 * komen normaal van het thema en worden hier meegegeven.
	 */
	public static function html( $sleutel, $status = 403 ) {
		$schermen = self::schermen();
		$scherm   = isset( $schermen[ $sleutel ] ) ? $schermen[ $sleutel ] : $schermen['niet_gevonden'];

		$taal    = HDP_I18N::is_frans() ? 'fr' : 'nl';
		$titel   = $scherm['titel'][ $taal ];
		$tekst   = $scherm['tekst'][ $taal ];
		$portaal = home_url( '/dealerportaal/' );

		ob_start();
		?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr( $taal ); ?>" data-taal="<?php echo esc_attr( $taal ); ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $titel ); ?></title>
	<?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- losse pagina buiten de normale wp_head()-opbouw; wp_enqueue_style() heeft hier niets om in te haken. ?>
	<link rel="stylesheet" href="<?php echo esc_url( HDP_PLUGIN_URL . 'assets/css/dealerportaal.css?ver=' . HDP_VERSION ); ?>">
	<style>
		/* Normaal komen deze uit theme.json; op dit losse scherm staat het
		   blokthema niet aan, dus hier expliciet. */
		:root {
			--wp--preset--color--homburg-rood: #c00d0d;
			--wp--preset--color--homburg-rood-donker: #9a0a0a;
			--wp--preset--color--donkergrijs: #2b2d31;
			--wp--preset--color--grijs-tekst: #55585e;
			--wp--preset--color--grijs-licht: #f4f5f7;
			--wp--preset--color--wit: #ffffff;
			--wp--preset--color--rand: #e2e4e8;
		}
		body {
			margin: 0;
			min-height: 100vh;
			display: flex;
			align-items: center;
			justify-content: center;
			padding: 1.5rem;
			background: var(--wp--preset--color--grijs-licht);
			color: var(--wp--preset--color--donkergrijs);
			font-family: 'Montserrat', Arial, sans-serif;
			box-sizing: border-box;
		}
		.hdp-fout-kaart {
			max-width: 32rem;
			width: 100%;
			background: var(--wp--preset--color--wit);
			border: 1px solid var(--wp--preset--color--rand);
			border-radius: 10px;
			padding: 2.5rem 2rem;
			text-align: center;
			box-shadow: 0 2px 16px rgba(0, 0, 0, .06);
		}
		.hdp-fout-kaart h1 { font-size: 1.4rem; margin: 1rem 0 .75rem; }
		.hdp-fout-kaart p { margin: 0 0 1.25rem; line-height: 1.6; color: var(--wp--preset--color--grijs-tekst); }
		.hdp-fout-contact { font-size: .95rem; }
		.hdp-fout-contact a { color: var(--wp--preset--color--homburg-rood); }
		.hdp-fout-knoppen { display: flex; gap: .75rem; justify-content: center; flex-wrap: wrap; }
		.hdp-fout-kaart .hdp-btn { display: inline-block; }
		.hdp-fout-code { margin-top: 1.75rem; font-size: .8rem; color: var(--wp--preset--color--grijs-tekst); opacity: .7; }
		@media (max-width: 30rem) {
			.hdp-fout-kaart { padding: 2rem 1.25rem; }
			.hdp-fout-knoppen .hdp-btn { width: 100%; }
		}
	</style>
</head>
<body>
	<main class="hdp-fout-kaart">
		<?php HDP_Icons::render_icoon( $scherm['icoon'] ); ?>
		<h1><?php echo esc_html( $titel ); ?></h1>
		<p><?php echo esc_html( $tekst ); ?></p>
		<?php if ( $scherm['contact'] ) : ?>
			<?php $contact = self::contactregel( $taal ); ?>
			<?php if ( $contact ) : ?>
				<p class="hdp-fout-contact"><?php echo wp_kses( $contact, array( 'a' => array( 'href' => array() ) ) ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
		<div class="hdp-fout-knoppen">
			<a class="hdp-btn" href="<?php echo esc_url( $portaal ); ?>"><?php echo esc_html( HDP_I18N::t( 'terug_naar_portaal' ) ); ?></a>
			<?php if ( 'niet_gevonden' === $sleutel || 'geen_merkrecht' === $sleutel ) : ?>
				<a class="hdp-btn hdp-btn-secundair" href="<?php echo esc_url( home_url( '/downloads/' ) ); ?>"><?php echo esc_html( 'fr' === $taal ? 'Tous les téléchargements' : 'Alle downloads' ); ?></a>
			<?php endif; ?>
		</div>
		<p class="hdp-fout-code"><?php echo esc_html( 'fr' === $taal ? 'Code' : 'Code' ); ?> <?php echo esc_html( (string) $status ); ?></p>
	</main>
</body>
</html>
		<?php
		return ob_get_clean();
	}

	/** Contactregel uit de plugin-instellingen; leeg als daar niets staat. */
	private static function contactregel( $taal ) {
		$email    = HDP_Settings::get( 'contact_email' );
		$telefoon = HDP_Settings::get( 'contact_telefoon' );

		if ( ! $email && ! $telefoon ) {
			return '';
		}

		$delen = array();
		if ( $telefoon ) {
			$delen[] = '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $telefoon ) ) . '">' . esc_html( $telefoon ) . '</a>';
		}
		if ( $email ) {
			$delen[] = '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
		}

		$aanhef = 'fr' === $taal ? 'Nous contacter&nbsp;:' : 'Contact opnemen:';

		return $aanhef . ' ' . implode( ' &middot; ', $delen );
	}
}
