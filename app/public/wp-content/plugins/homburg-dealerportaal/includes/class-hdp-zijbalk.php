<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Het vaste zijmenu van het welkomscherm.
 *
 * De onderdelen komen niet uit een lijst hier in de code, maar uit de
 * portaalkaarten die al op de startpagina staan. Zo blijft het beheer waar
 * het was — titel, Franse titel en link staan gewoon in de pagina-editor —
 * en kan er nooit een onderdeel in het menu staan dat op de pagina niet
 * bestaat, of andersom. Voegt iemand een kaart toe, dan staat die er ook
 * in het menu bij.
 *
 * Het menu is bewust kaal: logo, account, favorieten en de taalkeuze staan
 * in de balk bovenaan en horen hier niet nog een keer. Dit is alleen de
 * wegwijzer naar de onderdelen van het portaal.
 */
class HDP_Zijbalk {

	/** Hoe lang de aantallen in de knoppen blijven staan voor ze opnieuw opgehaald worden. */
	const TELLING_CACHE = 120;

	/**
	 * In welk kopje een onderdeel terechtkomt, op basis van zijn icoon.
	 *
	 * Zeven onderdelen onder elkaar lezen als een opsomming; met kopjes
	 * erboven is in één blik te zien waar iets staat. Het icoon is het
	 * houvast en niet de titel: een kaart die hernoemd wordt (of in het
	 * Frans staat) blijft zo op zijn plek.
	 *
	 * Een onbekend icoon valt onder "materiaal", het laatste kopje — dan
	 * verdwijnt een nieuw toegevoegde kaart nooit uit het menu.
	 */
	const GROEP_PER_ICOON = array(
		'webshop'      => 'bestellen',
		'configurator' => 'bestellen',
		'bestellen'    => 'bestellen',
		'garantie'     => 'service',
		'sleutel'      => 'service',
		'machine'      => 'service',
		'technisch'    => 'service',
		'downloads'    => 'materiaal',
		'content'      => 'materiaal',
		'huisstijl'    => 'materiaal',
	);

	/** De kopjes, op volgorde. */
	const GROEPEN = array( 'bestellen', 'service', 'materiaal' );

	/** Het sjabloon waarmee een pagina zegt dat ze bij het portaal hoort. */
	const SJABLOON = 'portaalpagina';

	public static function init() {
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
	}

	/**
	 * Staat op deze pagina het zijmenu?
	 *
	 * Het sjabloon zet het omhulsel altijd neer, ook voor een bezoeker die
	 * niet ingelogd is. Zonder deze vlag bleef daar een lege donkere kolom
	 * van staan.
	 *
	 * @return bool
	 */
	public static function gebruikt_zijmenu() {
		if ( ! is_user_logged_in() || ! HDP_Roles::mag_portaal_zien( get_current_user_id() ) ) {
			return false;
		}

		if ( is_front_page() ) {
			return true;
		}

		// De accountpagina's (bestellingen, favorieten, snel bestellen) en de
		// winkel zelf horen net zo goed bij het portaal, maar hebben hun
		// eigen sjablonen.
		if ( function_exists( 'is_woocommerce' ) && ( is_woocommerce() || is_cart() || is_checkout() || is_account_page() ) ) {
			return true;
		}

		return self::SJABLOON === get_page_template_slug();
	}

	/**
	 * Een klasse op <body> zodat de opmaak weet dat deze pagina het
	 * zijmenu heeft: de balk bovenaan en de voettekst zien er dan anders
	 * uit (één doorlopend donker vlak met het werkblad erin).
	 *
	 * @param array $classes Klassen van WordPress.
	 * @return array
	 */
	public static function body_class( $classes ) {
		if ( self::gebruikt_zijmenu() ) {
			$classes[] = 'hdp-app-layout';
		}

		return $classes;
	}

	/**
	 * De onderdelen uit de portaalkaarten van de startpagina.
	 *
	 * @return array Lijst met icoon, titel, url en nieuwe_tab.
	 */
	public static function onderdelen() {
		$pagina = get_post( (int) get_option( 'page_on_front' ) );
		if ( ! $pagina ) {
			return array();
		}

		$gevonden = array();
		self::verzamel_kaarten( parse_blocks( $pagina->post_content ), $gevonden );

		return $gevonden;
	}

	/**
	 * Loopt de blokken van de pagina af op zoek naar portaalkaarten.
	 *
	 * Recursief, want de kaarten zitten in een groepsblok — en als iemand ze
	 * ooit in kolommen zet, zitten ze een niveau dieper.
	 *
	 * @param array $blokken  Blokken zoals parse_blocks() ze teruggeeft.
	 * @param array $gevonden Wordt gevuld; bij verwijzing.
	 */
	private static function verzamel_kaarten( $blokken, &$gevonden ) {
		foreach ( $blokken as $blok ) {
			if ( 'homburg/portaal-kaart' === $blok['blockName'] ) {
				$kaart = self::kaart_naar_onderdeel( isset( $blok['attrs'] ) ? $blok['attrs'] : array() );
				if ( $kaart ) {
					$gevonden[] = $kaart;
				}
			}

			if ( ! empty( $blok['innerBlocks'] ) ) {
				self::verzamel_kaarten( $blok['innerBlocks'], $gevonden );
			}
		}
	}

	/**
	 * Eén kaart naar een menu-onderdeel, of null als er niets te linken valt.
	 *
	 * @param array $attrs Blokattributen van de kaart.
	 * @return array|null
	 */
	private static function kaart_naar_onderdeel( $attrs ) {
		$titel = HDP_I18N::kies(
			isset( $attrs['titel'] ) ? $attrs['titel'] : '',
			isset( $attrs['titelFr'] ) ? $attrs['titelFr'] : ''
		);

		// De link staat óf rechtstreeks op de kaart, óf als sleutel naar de
		// plugin-instellingen (zo staat de webshop-URL op één plek).
		$url = isset( $attrs['url'] ) ? trim( (string) $attrs['url'] ) : '';
		if ( ! empty( $attrs['instellingSleutel'] ) ) {
			$uit_instelling = HDP_Settings::get( $attrs['instellingSleutel'] );
			if ( $uit_instelling ) {
				$url = $uit_instelling;
			}
		}

		if ( '' === $titel || '' === $url ) {
			return null;
		}

		return array(
			'icoon'      => isset( $attrs['icoon'] ) ? $attrs['icoon'] : 'webshop',
			'titel'      => $titel,
			'url'        => 0 === strpos( $url, '/' ) ? home_url( $url ) : $url,
			'nieuwe_tab' => ! empty( $attrs['nieuweTab'] ),
		);
	}

	/** Hoeveel claims wachten op déze dealer. */
	public static function claims_die_wachten() {
		$telling = self::claimtellingen();

		return $telling['wacht'];
	}

	/**
	 * Hoeveel claims er op de dealer wachten en hoeveel er lopen.
	 *
	 * In één keer, want het zijmenu en de knoppen op het welkomscherm
	 * vragen om dezelfde lijst. Twee losse tellingen zouden twee verzoeken
	 * naar de claimadministratie betekenen voor één paginalaad.
	 *
	 * Kort gebufferd: dat lijstje komt uit de claimadministratie en die zit
	 * niet in deze site. Zonder buffer zou elke paginalaad van het
	 * welkomscherm op dat verzoek moeten wachten.
	 *
	 * @return array wacht, loopt.
	 */
	public static function claimtellingen() {
		$leeg = array(
			'wacht' => 0,
			'loopt' => 0,
		);

		if ( ! class_exists( 'HDP_Garantie' ) || ! HDP_Garantie::beschikbaar() ) {
			return $leeg;
		}

		$sleutel   = 'hdp_claimtelling_' . get_current_user_id();
		$gebufferd = get_transient( $sleutel );
		if ( is_array( $gebufferd ) ) {
			return $gebufferd;
		}

		$claims = HDP_Garantie::claims();
		if ( is_wp_error( $claims ) ) {
			// Ligt de claimadministratie eruit, dan hoort het welkomscherm
			// gewoon te laden — geen getal is beter dan een foutmelding
			// naast het menu.
			return $leeg;
		}

		$telling = $leeg;
		foreach ( $claims as $claim ) {
			if ( HDP_Garantie::is_afgerond( $claim['status'] ) ) {
				continue;
			}
			if ( HDP_Garantie::STATUS_ACTIE_DEALER === $claim['status'] ) {
				++$telling['wacht'];
			} else {
				++$telling['loopt'];
			}
		}

		set_transient( $sleutel, $telling, self::TELLING_CACHE );

		return $telling;
	}

	/** Staan we nu op deze pagina? Dan hoort het onderdeel opgelicht te zijn. */
	private static function is_huidige( $url ) {
		$hier = untrailingslashit( (string) wp_parse_url( home_url( add_query_arg( array() ) ), PHP_URL_PATH ) );
		$daar = untrailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) );

		// Alleen binnen de eigen site: een externe link (de configurator) is
		// nooit "de pagina waar u nu bent".
		$eigen = 0 === strpos( $url, home_url() );

		return $eigen && '' !== $daar && $hier === $daar;
	}

	/**
	 * Hoort deze link bij de winkel, en zijn we ergens in de winkel?
	 *
	 * Een productpagina, de winkelwagen en afrekenen hebben elk een eigen
	 * adres, dus op padvergelijking alleen dooft "Webshop" in het menu zodra
	 * je op een product klikt — terwijl je de winkel helemaal niet verlaten
	 * hebt.
	 *
	 * @param string $url De link van een menu-onderdeel.
	 * @return bool
	 */
	private static function is_winkelgebied( $url ) {
		if ( ! function_exists( 'is_woocommerce' ) || ! function_exists( 'wc_get_page_permalink' ) ) {
			return false;
		}

		// De accountpagina's horen bij "Bestellingen" en niet bij de winkel.
		if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() ) {
			return false;
		}

		$winkel = untrailingslashit( (string) wp_parse_url( wc_get_page_permalink( 'shop' ), PHP_URL_PATH ) );
		$daar   = untrailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) );

		return '' !== $winkel && $winkel === $daar;
	}

	public static function render() {
		if ( ! is_user_logged_in() || ! HDP_Roles::mag_portaal_zien( get_current_user_id() ) ) {
			return;
		}

		$onderdelen = self::onderdelen();
		if ( ! $onderdelen ) {
			return;
		}

		$wachtend = self::claims_die_wachten();

		// Bestellingen staat niet als kaart op de pagina, maar hoort wel in
		// deze rij: het is waar een dealer na het bestellen het vaakst
		// terugkomt.
		if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
			$onderdelen[] = array(
				'icoon'      => 'bestellen',
				'titel'      => HDP_I18N::t( 'zijbalk_bestellingen' ),
				'url'        => wc_get_account_endpoint_url( 'orders' ),
				'nieuwe_tab' => false,
			);
		}

		$per_groep = self::verdeel_over_groepen( $onderdelen );
		$home      = home_url( '/dealerportaal/' );
		?>
		<nav class="hdp-zijbalk" aria-label="<?php echo esc_attr( HDP_I18N::t( 'zijbalk_label' ) ); ?>">

			<?php // Zonder kopje en bovenaan: dit is de weg terug, geen onderdeel. ?>
			<ul class="hdp-zijbalk-lijst hdp-zijbalk-lijst-home">
				<li>
					<a href="<?php echo esc_url( $home ); ?>" class="hdp-zijbalk-link<?php echo is_front_page() ? ' hdp-zijbalk-huidig' : ''; ?>"<?php echo is_front_page() ? ' aria-current="page"' : ''; ?>>
						<?php echo HDP_Icons::svg_icoon( 'home' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?>
						<span class="hdp-zijbalk-tekst"><?php echo esc_html( HDP_I18N::t( 'zijbalk_home' ) ); ?></span>
					</a>
				</li>
			</ul>

			<?php foreach ( $per_groep as $groep => $in_groep ) : ?>
			<p class="hdp-zijbalk-kop"><?php echo esc_html( HDP_I18N::t( 'zijbalk_groep_' . $groep ) ); ?></p>

			<ul class="hdp-zijbalk-lijst">
				<?php foreach ( $in_groep as $onderdeel ) : ?>
					<?php
					$huidig = self::is_huidige( $onderdeel['url'] ) || self::is_winkelgebied( $onderdeel['url'] );
					// Alleen bij garantie telt er iets mee; dat hangt aan de
					// claim-URL en niet aan de titel, zodat een hernoemde
					// kaart het getal niet kwijtraakt.
					$badge = $wachtend && false !== strpos( $onderdeel['url'], '/garantie' ) ? $wachtend : 0;
					?>
					<li>
						<a
							href="<?php echo esc_url( $onderdeel['url'] ); ?>"
							class="hdp-zijbalk-link<?php echo $huidig ? ' hdp-zijbalk-huidig' : ''; ?>"
							<?php echo $huidig ? ' aria-current="page"' : ''; ?>
							<?php echo $onderdeel['nieuwe_tab'] ? ' target="_blank" rel="noopener noreferrer"' : ''; ?>
						>
							<?php echo HDP_Icons::svg_icoon( $onderdeel['icoon'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?>
							<span class="hdp-zijbalk-tekst"><?php echo esc_html( $onderdeel['titel'] ); ?></span>
							<?php if ( $onderdeel['nieuwe_tab'] ) : ?>
								<span class="hdp-zijbalk-extern" aria-hidden="true">
									<?php echo HDP_Icons::svg_icoon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?>
								</span>
							<?php endif; ?>
							<?php if ( $badge ) : ?>
								<span class="hdp-zijbalk-badge"><?php echo esc_html( (string) $badge ); ?></span>
							<?php endif; ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php endforeach; ?>
		</nav>
		<?php
	}

	/**
	 * De onderdelen onder de kopjes hangen, lege kopjes weggelaten.
	 *
	 * @param array $onderdelen Zoals onderdelen() ze teruggeeft.
	 * @return array groep => lijst onderdelen.
	 */
	private static function verdeel_over_groepen( $onderdelen ) {
		$per_groep = array_fill_keys( self::GROEPEN, array() );

		foreach ( $onderdelen as $onderdeel ) {
			$icoon = $onderdeel['icoon'];
			$groep = isset( self::GROEP_PER_ICOON[ $icoon ] ) ? self::GROEP_PER_ICOON[ $icoon ] : 'materiaal';

			$per_groep[ $groep ][] = $onderdeel;
		}

		return array_filter( $per_groep );
	}
}
