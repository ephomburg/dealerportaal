<?php
/**
 * Het scherm Dashboard > Portaalgebruik, in tabbladen:
 * - Overzicht: een samenvatting in gewone taal met drie kerncijfers en hun
 *   verloop, omzet per week, hoe actief de bedrijven zijn, wat er net
 *   gebeurde, van bezoek naar bestelling, meest besteld, en welke
 *   onderdelen gebruikt worden;
 * - Dealers: één regel per bedrijf met een detailpaneel (bellen, mailen,
 *   wat ze doen, waar ze naar zochten, hun accounts);
 * - Webshop, Documenten & zoeken, Trainingen.
 * Plus het blokje op het profiel van een dealer.
 *
 * De cijfers komen uit HDP_Gebruik; hier staat alleen de weergave.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HDP_Gebruik_Scherm {

	const PAGINA = 'hdp-gebruik';

	const TABS = array(
		'overzicht'  => 'Overzicht',
		'dealers'    => 'Dealers',
		'webshop'    => 'Webshop',
		'inhoud'     => 'Documenten & zoeken',
		'trainingen' => 'Trainingen',
	);

	const ACTIVITEITEN = array(
		'actief'    => 'Actief',
		'af-en-toe' => 'Af en toe',
		'stil'      => 'Stil',
		'nooit'     => 'Nog nooit',
	);

	/** De kleur per activiteit (ring, stip). Altijd met de naam erbij, nooit alleen kleur. */
	const ACTIVITEIT_KLEUR = array(
		'actief'    => '#1f9d4c',
		'af-en-toe' => '#e0a100',
		'stil'      => '#c41214',
		'nooit'     => '#b9bcc2',
	);

	const AVATARKLEUREN = array( '#c41214', '#17191c', '#34507a', '#5b3a8f', '#146c34', '#8a5a00', '#3d4148', '#8a1012' );

	const TIJDLIJN_ICOON = array(
		'bestelling' => array( 'cart', 'is-groen' ),
		'download'   => array( 'media-document', 'is-blauw' ),
		'zoek'       => array( 'search', 'is-geel' ),
		'training'   => array( 'welcome-learn-more', 'is-paars' ),
		'garantie'   => array( 'shield', 'is-rood' ),
		'fout'       => array( 'warning', 'is-rood' ),
	);

	public static function registreer_menu() {
		add_dashboard_page( 'Portaalgebruik', 'Portaalgebruik', 'manage_options', self::PAGINA, array( __CLASS__, 'render' ) );
	}

	/* ---------------------------------------------------------------------
	 * Hulpjes
	 * ------------------------------------------------------------------- */

	public static function url( $tab, $dagen, array $extra = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGINA, 'tab' => $tab, 'periode' => (int) $dagen ), $extra ), admin_url( 'index.php' ) );
	}

	/** € 1.786,50 */
	private static function geld( $bedrag ) {
		return '€ ' . number_format( (float) $bedrag, 2, ',', '.' );
	}

	/** € 48.320 (zonder centen, voor grote getallen). */
	private static function geld_rond( $bedrag ) {
		return '€ ' . number_format( round( (float) $bedrag ), 0, ',', '.' );
	}

	private static function datum( $tijdstempel ) {
		return $tijdstempel ? wp_date( 'j M Y', $tijdstempel ) : '—';
	}

	/** "vandaag", "gisteren", "3 dagen geleden", of een datum. */
	private static function wanneer( $tijdstempel ) {
		if ( ! $tijdstempel ) {
			return 'nog nooit';
		}
		$dagen = (int) round( ( strtotime( wp_date( 'Y-m-d' ) ) - strtotime( wp_date( 'Y-m-d', $tijdstempel ) ) ) / DAY_IN_SECONDS );
		if ( $dagen <= 0 ) {
			return 'vandaag';
		}
		if ( 1 === $dagen ) {
			return 'gisteren';
		}
		return $dagen < 60 ? $dagen . ' dagen geleden' : wp_date( 'j M Y', $tijdstempel );
	}

	/** Het tijdstip in "Net gebeurd": 10:42, gisteren, of 3 nov. */
	private static function tijdstip( $tijdstempel ) {
		if ( wp_date( 'Y-m-d', $tijdstempel ) === wp_date( 'Y-m-d' ) ) {
			return wp_date( 'H:i', $tijdstempel );
		}
		if ( wp_date( 'Y-m-d', $tijdstempel ) === wp_date( 'Y-m-d', time() - DAY_IN_SECONDS ) ) {
			return 'gisteren';
		}
		return wp_date( 'j M', $tijdstempel );
	}

	private static function periode_tekst( $dagen ) {
		return str_replace( 'Laatste ', '', HDP_Gebruik::PERIODES[ $dagen ] );
	}

	/**
	 * Een pilletje met het verschil: "↑ 3" groen, "↓ 2" rood, "=" grijs.
	 * Null = er valt niets te vergelijken: dan niets.
	 */
	private static function pil( $nu, $toen, $soort = 'getal' ) {
		if ( null === $toen ) {
			return '';
		}
		$verschil = $nu - $toen;
		if ( abs( $verschil ) < 0.005 ) {
			return '<span class="hdp-g-pil">=</span>';
		}
		if ( 'procent' === $soort && $toen > 0 ) {
			$tekst = round( abs( $verschil ) / $toen * 100 ) . '%';
		} elseif ( 'procent' === $soort || 'geld' === $soort ) {
			$tekst = self::geld_rond( abs( $verschil ) );
		} else {
			$tekst = number_format_i18n( abs( $verschil ) );
		}
		$op = $verschil > 0;
		return '<span class="hdp-g-pil is-' . ( $op ? 'op' : 'neer' ) . '"><span aria-hidden="true">' . ( $op ? '↑' : '↓' ) . '</span><span class="screen-reader-text">' . ( $op ? 'meer:' : 'minder:' ) . '</span> ' . esc_html( $tekst ) . '</span>';
	}

	/** Een lijntje van het verloop (12 weken), als SVG met een omschrijving. */
	private static function lijntje( array $waarden, $omschrijving ) {
		$hoogste = max( 1, max( $waarden ) );
		$stap    = count( $waarden ) > 1 ? 120 / ( count( $waarden ) - 1 ) : 120;
		$punten  = array();
		foreach ( array_values( $waarden ) as $i => $waarde ) {
			$punten[] = round( $i * $stap, 1 ) . ',' . round( 32 - $waarde / $hoogste * 28, 1 );
		}
		return '<svg class="hdp-g-lijntje" width="120" height="36" viewBox="0 0 120 36" role="img" aria-label="' . esc_attr( $omschrijving ) . '"><polyline points="' . esc_attr( implode( ' ', $punten ) ) . '" fill="none" stroke="#ff8a8c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></polyline></svg>';
	}

	/** Een kerncijfer op een witte kaart: icoon, label, groot getal, eventueel een regel eronder. */
	private static function tegel( $waarde, $label, $onder = '', $icoon = 'chart-bar' ) {
		echo '<div class="hdp-g-tegel"><span class="hdp-g-tegel-kop"><span class="hdp-g-icoon dashicons dashicons-' . esc_attr( $icoon ) . '" aria-hidden="true"></span>' . esc_html( $label ) . '</span><strong>' . wp_kses_post( $waarde ) . '</strong>' . ( $onder ? '<span class="hdp-g-tegel-onder">' . wp_kses_post( $onder ) . '</span>' : '' ) . '</div>';
	}

	/**
	 * Liggende staven in één kleur, met de waarde ernaast als tekst.
	 *
	 * @param array<int, array{label: string, waarde: float, tekst: string}> $rijen
	 */
	private static function staven( array $rijen ) {
		$hoogste = 0;
		foreach ( $rijen as $rij ) {
			$hoogste = max( $hoogste, (float) $rij['waarde'] );
		}
		$hoogste = $hoogste > 0 ? $hoogste : 1;
		echo '<div class="hdp-g-staven">';
		foreach ( $rijen as $rij ) {
			$breedte = (float) $rij['waarde'] > 0 ? max( 2, round( (float) $rij['waarde'] / $hoogste * 100, 1 ) ) : 0;
			printf(
				'<div class="hdp-g-staaf" title="%1$s: %2$s"><span class="hdp-g-staaf-kop"><span>%1$s</span><b>%2$s</b></span><i><u style="width:%3$s%%"></u></i></div>',
				esc_attr( $rij['label'] ),
				esc_html( $rij['tekst'] ),
				esc_attr( (string) $breedte )
			);
		}
		echo '</div>';
	}

	private static function leeg( $tekst, $icoon = 'chart-bar' ) {
		echo '<div class="hdp-g-leegte"><span class="dashicons dashicons-' . esc_attr( $icoon ) . '" aria-hidden="true"></span><p>' . esc_html( $tekst ) . '</p></div>';
	}

	private static function kaart_open( $titel, $uitleg = '', $id = '', $extra = '' ) {
		echo '<section class="hdp-g-kaart ' . esc_attr( $extra ) . '"' . ( $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . '><div class="hdp-g-kaart-kop"><h2>' . esc_html( $titel ) . '</h2>';
		if ( $uitleg ) {
			echo '<p>' . wp_kses_post( $uitleg ) . '</p>';
		}
		echo '</div>';
	}

	private static function kaart_dicht() {
		echo '</section>';
	}

	private static function bedrijf_link( $id, $naam ) {
		return $id ? '<a href="' . esc_url( get_edit_user_link( $id ) ) . '">' . esc_html( $naam ) . '</a>' : esc_html( $naam );
	}

	/** "LV" voor Loonbedrijf De Vries: de eerste letters van de laatste twee echte woorden. */
	public static function initialen( $naam ) {
		$woorden = array_values(
			array_filter(
				preg_split( '/\s+/', trim( (string) $naam ) ),
				static function ( $woord ) {
					return mb_strlen( $woord ) > 2;
				}
			)
		);
		if ( ! $woorden ) {
			return mb_strtoupper( mb_substr( trim( (string) $naam ), 0, 1 ) );
		}
		$letters = '';
		foreach ( array_slice( $woorden, -2 ) as $woord ) {
			$letters .= mb_substr( $woord, 0, 1 );
		}
		return mb_strtoupper( $letters );
	}

	private static function avatar( $id, $naam, $groot = false ) {
		$kleur = self::AVATARKLEUREN[ abs( (int) $id ) % count( self::AVATARKLEUREN ) ];
		return '<span class="hdp-g-avatar' . ( $groot ? ' is-groot' : '' ) . '" style="background:' . esc_attr( $kleur ) . '" aria-hidden="true">' . esc_html( self::initialen( $naam ) ) . '</span>';
	}

	private static function label( $code, $tekst ) {
		return '<span class="hdp-g-label is-' . esc_attr( $code ) . '">' . esc_html( $tekst ) . '</span>';
	}

	/* ---------------------------------------------------------------------
	 * Het scherm
	 * ------------------------------------------------------------------- */

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- alleen weergavekeuzes.
		$dagen = isset( $_GET['periode'] ) ? (int) $_GET['periode'] : 30;
		$tab   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overzicht';
		// phpcs:enable
		if ( ! isset( HDP_Gebruik::PERIODES[ $dagen ] ) ) {
			$dagen = 30;
		}
		if ( ! isset( self::TABS[ $tab ] ) ) {
			$tab = 'overzicht';
		}
		$aantal_bedrijven = count( HDP_Gebruik::bedrijven() );
		?>
		<div class="wrap hdp-gebruik">
			<?php self::stijl(); ?>
			<div class="hdp-g-kop">
				<h1>Portaalgebruik</h1>
				<div class="hdp-g-kop-acties">
					<div class="hdp-g-segment" role="group" aria-label="Periode">
						<?php foreach ( HDP_Gebruik::PERIODES as $waarde => $label ) : ?>
							<a href="<?php echo esc_url( self::url( $tab, $waarde ) ); ?>" class="<?php echo $waarde === $dagen ? 'is-actief' : ''; ?>"<?php echo $waarde === $dagen ? ' aria-current="true"' : ''; ?>><?php echo esc_html( self::periode_tekst( $waarde ) ); ?></a>
						<?php endforeach; ?>
					</div>
					<a class="hdp-g-knop" href="<?php echo esc_url( HDP_Gebruik::export_url( $dagen ) ); ?>"><span class="dashicons dashicons-download" aria-hidden="true"></span> Naar Excel</a>
				</div>
			</div>

			<nav class="hdp-g-tabs" aria-label="Onderdelen van portaalgebruik">
				<?php foreach ( self::TABS as $sleutel => $naam ) : ?>
					<a href="<?php echo esc_url( self::url( $sleutel, $dagen ) ); ?>" class="<?php echo $sleutel === $tab ? 'is-actief' : ''; ?>"<?php echo $sleutel === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $naam ); ?><?php echo 'dealers' === $sleutel ? ' <span class="hdp-g-tab-aantal">' . (int) $aantal_bedrijven . '</span>' : ''; ?></a>
				<?php endforeach; ?>
			</nav>

			<?php
			switch ( $tab ) {
				case 'dealers':
					self::tab_dealers( $dagen );
					break;
				case 'webshop':
					self::tab_webshop( $dagen );
					break;
				case 'inhoud':
					self::tab_inhoud( $dagen );
					break;
				case 'trainingen':
					self::tab_trainingen( $dagen );
					break;
				default:
					self::tab_overzicht( $dagen );
			}
			?>

			<p class="hdp-g-voet">Collega's tellen mee bij hun bedrijf. Beheerders en testaccounts (aan te vinken in het gebruikersprofiel) tellen niet mee. Alles staat in de eigen database, zonder cookies of externe dienst; gegevens ouder dan <?php echo (int) HDP_Gebruik::STERMIJN_DAGEN; ?> dagen worden automatisch verwijderd.</p>
		</div>
		<?php
		self::script();
	}

	/* ---------------------------------------------------------------------
	 * Overzicht
	 * ------------------------------------------------------------------- */

	/**
	 * De samenvatting bovenaan, in gewone taal.
	 *
	 * @return string[] array( hoofdzin met <em> om het bedrag, regel eronder )
	 */
	public static function samenvatting( $dagen, $nu, $toen, $vergelijk, $trechter, $delen ) {
		$periode = self::periode_tekst( $dagen );
		if ( ! $nu['bedrijven'] ) {
			return array( 'Er zijn nog geen dealers die meetellen.', '' );
		}

		if ( 1 === (int) $nu['bedrijven'] ) {
			$zin = $nu['actieve_bedrijven'] ? 'Het enige bedrijf was in het portaal.' : 'Het enige bedrijf was niet in het portaal.';
		} else {
			$zin = sprintf(
				1 === (int) $nu['actieve_bedrijven'] ? '%d van de %d bedrijven was in het portaal.' : '%d van de %d bedrijven waren in het portaal.',
				$nu['actieve_bedrijven'],
				$nu['bedrijven']
			);
		}
		if ( $trechter['besteld'] ) {
			$zin .= ' ' . sprintf( 1 === (int) $trechter['besteld'] ? '%d bedrijf bestelde, samen voor %s.' : '%d bedrijven bestelden, samen voor %s.', $trechter['besteld'], '<em>' . esc_html( self::geld_rond( $nu['omzet'] ) ) . '</em>' );
		} else {
			$zin .= ' Er is niets besteld.';
		}

		$delen_zin = array();
		if ( $vergelijk ) {
			$verschil = $nu['actieve_bedrijven'] - $toen['actieve_bedrijven'];
			if ( $verschil ) {
				$delen_zin[] = sprintf( '%d %s %s', abs( $verschil ), 1 === abs( $verschil ) ? 'bedrijf' : 'bedrijven', $verschil > 0 ? 'meer' : 'minder' );
			}
		}
		$omzet = $nu['omzet'] - $toen['omzet'];
		if ( abs( $omzet ) >= 1 ) {
			$delen_zin[] = sprintf( '%s %s omzet', self::geld_rond( abs( $omzet ) ), $omzet > 0 ? 'meer' : 'minder' );
		}
		$onder = $delen_zin ? 'Dat is ' . implode( ' en ', $delen_zin ) . ' dan de ' . $periode . ' ervoor.' : '';

		$gebruikt = array_filter(
			$delen,
			static function ( $deel, $sleutel ) {
				return $deel['bedrijven'] > 0 && ! in_array( $sleutel, array( 'start', 'account' ), true );
			},
			ARRAY_FILTER_USE_BOTH
		);
		uasort(
			$gebruikt,
			static function ( $a, $b ) {
				return $b['bedrijven'] <=> $a['bedrijven'];
			}
		);
		$namen = array_map( 'mb_strtolower', array_slice( array_values( wp_list_pluck( $gebruikt, 'naam' ) ), 0, 2 ) );
		if ( $namen ) {
			$onder .= ( $onder ? ' ' : '' ) . 'Meest gebruikt: de ' . $namen[0] . ( isset( $namen[1] ) ? ', daarna ' . $namen[1] : '' ) . '.';
		}
		return array( $zin, $onder );
	}

	private static function tab_overzicht( $dagen ) {
		$nu        = HDP_Gebruik::kerncijfers( $dagen );
		$toen      = HDP_Gebruik::kerncijfers( $dagen, true );
		$vergelijk = HDP_Gebruik::vergelijkbaar( $dagen );
		$trechter  = HDP_Gebruik::trechter( $dagen );
		$delen     = HDP_Gebruik::onderdelen( $dagen );
		$per_week  = HDP_Gebruik::actief_per_week( 12 );
		$shop_week = HDP_Gebruik::webshop_per_week( 12 );
		$bedrijven = HDP_Gebruik::dealerlijst( 30 );
		$gebeurd   = HDP_Gebruik::gebeurtenissen( 8, 7 );
		$cijfers   = HDP_Gebruik::webshopcijfers( $dagen );
		$apparaat  = HDP_Gebruik::apparaten( $dagen );
		$uitrol    = HDP_Gebruik::uitrol();

		list( $zin, $onder ) = self::samenvatting( $dagen, $nu, $toen, $vergelijk, $trechter, $delen );
		?>
		<section class="hdp-g-held" aria-labelledby="hdp-g-held-kop">
			<div class="hdp-g-held-tekst">
				<span class="hdp-g-held-label" id="hdp-g-held-kop"><?php echo esc_html( HDP_Gebruik::PERIODES[ $dagen ] ); ?> in het kort</span>
				<p class="hdp-g-held-zin"><?php echo wp_kses( $zin, array( 'em' => array() ) ); ?></p>
				<?php if ( $onder ) : ?>
					<p class="hdp-g-held-onder"><?php echo esc_html( $onder ); ?></p>
				<?php endif; ?>
			</div>
			<div class="hdp-g-held-cijfers">
				<div class="hdp-g-held-cijfer">
					<div><span>Actieve bedrijven</span><strong><?php echo (int) $nu['actieve_bedrijven']; ?> <?php echo wp_kses_post( self::pil( $nu['actieve_bedrijven'], $vergelijk ? $toen['actieve_bedrijven'] : null ) ); ?></strong></div>
					<?php echo self::lijntje( wp_list_pluck( $per_week, 'aantal' ), 'Actieve bedrijven per week, laatste 12 weken' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- geëscaped in lijntje. ?>
				</div>
				<div class="hdp-g-held-cijfer">
					<div><span>Bestellingen</span><strong><?php echo (int) $nu['bestellingen']; ?> <?php echo wp_kses_post( self::pil( $nu['bestellingen'], $toen['bestellingen'] ) ); ?></strong></div>
					<?php echo self::lijntje( wp_list_pluck( $shop_week, 'bestellingen' ), 'Bestellingen per week, laatste 12 weken' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
				<div class="hdp-g-held-cijfer">
					<div><span>Omzet excl. btw</span><strong><?php echo esc_html( self::geld_rond( $nu['omzet'] ) ); ?> <?php echo wp_kses_post( self::pil( $nu['omzet'], $toen['omzet'], 'procent' ) ); ?></strong></div>
					<?php echo self::lijntje( wp_list_pluck( $shop_week, 'omzet' ), 'Omzet per week, laatste 12 weken' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>
		</section>
		<?php if ( ! $vergelijk && HDP_Gebruik::meting_sinds() ) : ?>
			<p class="hdp-g-voetnoot">Bezoeken worden bijgehouden sinds <?php echo esc_html( wp_date( 'j F Y', HDP_Gebruik::meting_sinds() ) ); ?>. Zodra dat langer loopt dan de gekozen periode, worden ook de actieve bedrijven met de periode ervoor vergeleken.</p>
		<?php endif; ?>

		<div class="hdp-g-rij-flex">
			<?php
			self::kaart_open( 'Omzet per week', 'Webshop, excl. btw · wijs een week aan voor details', '', 'is-breed' );
			self::omzetgrafiek( $shop_week );
			self::kaart_dicht();

			self::kaart_open( 'Hoe actief zijn de bedrijven', 'Laatste 30 dagen · klik voor de namen', '', 'is-smal' );
			self::donut( $bedrijven, $dagen );
			self::kaart_dicht();
			?>
		</div>

		<div class="hdp-g-raster">
			<?php
			self::kaart_open( 'Net gebeurd', 'De laatste 7 dagen' );
			self::tijdlijn( $gebeurd );
			self::kaart_dicht();

			self::kaart_open( 'Van bezoek naar bestelling', 'Hoeveel bedrijven elke stap haalden' );
			self::trechter( $trechter );
			self::kaart_dicht();

			self::kaart_open( 'Meest besteld', 'Op aantal stuks' );
			self::meest_besteld( array_slice( $cijfers['artikelen'], 0, 5 ) );
			self::kaart_dicht();

			self::kaart_open( 'Wat gebruiken ze', 'Bedrijven per onderdeel' );
			self::wat_gebruiken_ze( $delen, $apparaat );
			self::kaart_dicht();

			if ( $uitrol['totaal'] && $uitrol['gekozen'] < $uitrol['totaal'] ) {
				self::kaart_open( 'Uitnodigingen', 'Dealers uit PowerAll, los van de periode · <a href="' . esc_url( self::url( 'dealers', $dagen ) ) . '#nalopen">wie moet er nog?</a>' );
				self::trechter_uitrol( $uitrol );
				self::kaart_dicht();
			}
			?>
		</div>
		<?php
	}

	/** Staande kolommen per week; aanwijzen of tabben toont het bedrag en de bestellingen. */
	private static function omzetgrafiek( array $weken ) {
		$hoogste = max( wp_list_pluck( $weken, 'omzet' ) );
		if ( $hoogste <= 0 ) {
			self::leeg( 'Nog geen bestellingen in de laatste 12 weken.', 'cart' );
			return;
		}
		$gemiddeld = array_sum( wp_list_pluck( $weken, 'omzet' ) ) / count( $weken );
		$laatste   = end( $weken );
		$uitleg    = static function ( $week, $deze ) {
			return 'week ' . $week['nr'] . ( $deze ? ' (deze week)' : '' ) . ' · ' . $week['bestellingen'] . ( 1 === $week['bestellingen'] ? ' bestelling' : ' bestellingen' );
		};
		$standaard_bedrag = self::geld_rond( $laatste['omzet'] );
		$standaard_uitleg = $uitleg( $laatste, true );
		?>
		<div class="hdp-g-grafiek" data-bedrag="<?php echo esc_attr( $standaard_bedrag ); ?>" data-uitleg="<?php echo esc_attr( $standaard_uitleg ); ?>">
			<div class="hdp-g-uitlezing" aria-live="polite"><strong><?php echo esc_html( $standaard_bedrag ); ?></strong><span><?php echo esc_html( $standaard_uitleg ); ?></span></div>
			<div class="hdp-g-kolommen">
				<div class="hdp-g-gemiddeld" style="bottom: <?php echo esc_attr( (string) round( $gemiddeld / $hoogste * 196 ) ); ?>px" aria-hidden="true"><span>gemiddeld <?php echo esc_html( self::geld_rond( $gemiddeld ) ); ?></span></div>
				<?php foreach ( $weken as $i => $week ) : ?>
					<?php $deze = count( $weken ) - 1 === $i; ?>
					<button type="button" class="hdp-g-kolom<?php echo $deze ? ' is-deze' : ''; ?>" data-bedrag="<?php echo esc_attr( self::geld_rond( $week['omzet'] ) ); ?>" data-uitleg="<?php echo esc_attr( $uitleg( $week, $deze ) ); ?>" aria-label="<?php echo esc_attr( $week['week'] . ': ' . self::geld_rond( $week['omzet'] ) . ', ' . $week['bestellingen'] . ' bestellingen' ); ?>">
						<i style="height: <?php echo esc_attr( (string) ( $week['omzet'] > 0 ? max( 3, round( $week['omzet'] / $hoogste * 196 ) ) : 2 ) ); ?>px"></i>
					</button>
				<?php endforeach; ?>
			</div>
			<div class="hdp-g-weeknummers" aria-hidden="true">
				<?php foreach ( $weken as $i => $week ) : ?>
					<span<?php echo count( $weken ) - 1 === $i ? ' class="is-deze"' : ''; ?>><?php echo (int) $week['nr']; ?></span>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	/** De bedrijven naar activiteit, als ring met een legenda die naar de dealerlijst linkt. */
	private static function donut( array $bedrijven, $dagen ) {
		if ( ! $bedrijven ) {
			self::leeg( 'Er zijn nog geen goedgekeurde dealers.', 'groups' );
			return;
		}
		$tellingen = array_fill_keys( array_keys( self::ACTIVITEITEN ), 0 );
		foreach ( $bedrijven as $rij ) {
			++$tellingen[ $rij['activiteit']['code'] ];
		}
		$totaal    = count( $bedrijven );
		$omtrek    = 2 * M_PI * 56;
		$tussen    = count( array_filter( $tellingen ) ) > 1 ? 2.0 : 0.0;
		$verschuif = 0.0;
		$tekst     = array();
		foreach ( self::ACTIVITEITEN as $code => $naam ) {
			$tekst[] = $naam . ' ' . $tellingen[ $code ];
		}
		?>
		<div class="hdp-g-donut-blok">
			<div class="hdp-g-donut">
				<svg width="148" height="148" viewBox="0 0 148 148" role="img" aria-label="<?php echo esc_attr( $totaal . ' bedrijven: ' . implode( ', ', $tekst ) ); ?>">
					<g transform="rotate(-90 74 74)" fill="none" stroke-width="18">
						<?php
						foreach ( self::ACTIVITEITEN as $code => $naam ) {
							if ( ! $tellingen[ $code ] ) {
								continue;
							}
							$lengte = $tellingen[ $code ] / $totaal * $omtrek;
							printf(
								'<circle cx="74" cy="74" r="56" stroke="%1$s" stroke-dasharray="%2$s %3$s" stroke-dashoffset="%4$s"></circle>',
								esc_attr( self::ACTIVITEIT_KLEUR[ $code ] ),
								esc_attr( (string) round( max( 0.5, $lengte - $tussen ), 2 ) ),
								esc_attr( (string) round( $omtrek, 2 ) ),
								esc_attr( (string) round( -$verschuif, 2 ) )
							);
							$verschuif += $lengte;
						}
						?>
					</g>
				</svg>
				<div class="hdp-g-donut-midden"><strong><?php echo (int) $totaal; ?></strong><span><?php echo 1 === $totaal ? 'bedrijf' : 'bedrijven'; ?></span></div>
			</div>
			<ul class="hdp-g-legenda">
				<?php foreach ( self::ACTIVITEITEN as $code => $naam ) : ?>
					<li><a href="<?php echo esc_url( self::url( 'dealers', $dagen, array( 'activiteit' => $code ) ) ); ?>"><i style="background: <?php echo esc_attr( self::ACTIVITEIT_KLEUR[ $code ] ); ?>" aria-hidden="true"></i><span><?php echo esc_html( $naam ); ?></span><b><?php echo (int) $tellingen[ $code ]; ?></b></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	private static function tijdlijn( array $gebeurd ) {
		if ( ! $gebeurd ) {
			self::leeg( 'De afgelopen 7 dagen is er nog niets gebeurd.', 'clock' );
			return;
		}
		echo '<ol class="hdp-g-tijdlijn">';
		foreach ( $gebeurd as $punt ) {
			$icoon = isset( self::TIJDLIJN_ICOON[ $punt['soort'] ] ) ? self::TIJDLIJN_ICOON[ $punt['soort'] ] : array( 'marker', 'is-grijs' );
			printf(
				'<li><time datetime="%1$s">%2$s</time><span class="hdp-g-tijdlijn-icoon %3$s dashicons dashicons-%4$s" aria-hidden="true"></span><span><strong>%5$s</strong>%6$s %7$s</span></li>',
				esc_attr( gmdate( 'c', $punt['tijd'] ) ),
				esc_html( self::tijdstip( $punt['tijd'] ) ),
				esc_attr( $icoon[1] ),
				esc_attr( $icoon[0] ),
				esc_html( $punt['wie'] ),
				$punt['bij'] ? ' <span class="hdp-g-zacht">(' . esc_html( $punt['bij'] ) . ')</span>' : '',
				esc_html( $punt['tekst'] )
			);
		}
		echo '</ol>';
	}

	private static function trechter( array $stap ) {
		if ( ! $stap['portaal'] ) {
			self::leeg( 'Nog geen bezoeken in deze periode.', 'filter' );
			return;
		}
		$stappen = array(
			array( 'In het portaal', $stap['portaal'] ),
			array( 'Webshop geopend', $stap['webshop'] ),
			array( 'Iets in de winkelwagen', $stap['winkelwagen'] ),
			array( 'Besteld', $stap['besteld'] ),
		);
		echo '<div class="hdp-g-trechter">';
		foreach ( $stappen as $i => $s ) {
			$procent = round( $s[1] / $stap['portaal'] * 100 );
			printf(
				'<div><span class="hdp-g-trechter-kop"><span>%1$s</span><b>%2$d%3$s</b></span><i><u class="is-%4$d" style="width:%5$d%%"></u></i></div>',
				esc_html( $s[0] ),
				(int) $s[1],
				$i ? ' <span class="hdp-g-zacht">· ' . (int) $procent . '%</span>' : '',
				(int) $i + 1,
				(int) max( $s[1] ? 2 : 0, $procent )
			);
		}
		echo '</div>';
		$niet = $stap['winkelwagen'] - $stap['besteld'];
		if ( $niet > 0 ) {
			echo '<p class="hdp-g-opmerking">' . esc_html( sprintf( 1 === $niet ? '%d bedrijf deed iets in de winkelwagen maar bestelde niet.' : '%d bedrijven deden iets in de winkelwagen maar bestelden niet.', $niet ) ) . '</p>';
		}
	}

	private static function meest_besteld( array $artikelen ) {
		if ( ! $artikelen ) {
			self::leeg( 'In deze periode is nog niets besteld.', 'cart' );
			return;
		}
		$hoogste = max( 1, max( wp_list_pluck( $artikelen, 'stuks' ) ) );
		echo '<ol class="hdp-g-top">';
		foreach ( array_values( $artikelen ) as $i => $artikel ) {
			printf(
				'<li><span class="hdp-g-top-nr">%1$d</span><span class="hdp-g-top-naam"><span title="%2$s">%2$s</span><i style="width:%3$d%%"></i></span><b>%4$d</b></li>',
				(int) $i + 1,
				esc_html( $artikel['naam'] ),
				(int) max( 3, round( $artikel['stuks'] / $hoogste * 100 ) ),
				(int) $artikel['stuks']
			);
		}
		echo '</ol>';
	}

	private static function wat_gebruiken_ze( array $delen, array $apparaat ) {
		$gebruikt = array_filter(
			$delen,
			static function ( $deel ) {
				return $deel['bedrijven'] > 0;
			}
		);
		if ( ! $gebruikt ) {
			self::leeg( 'Nog geen gegevens in deze periode. Dit vult zich vanzelf zodra dealers het portaal gebruiken.', 'screenoptions' );
			return;
		}
		uasort(
			$gebruikt,
			static function ( $a, $b ) {
				return $b['bedrijven'] <=> $a['bedrijven'];
			}
		);
		echo '<div class="hdp-g-vakken">';
		$rang = 0;
		foreach ( array_slice( $gebruikt, 0, 6 ) as $deel ) {
			++$rang;
			printf( '<div class="is-%1$d"><span>%2$s</span><strong>%3$d</strong></div>', (int) $rang, esc_html( $deel['naam'] ), (int) $deel['bedrijven'] );
		}
		echo '</div>';
		$app_totaal  = array_sum( $apparaat['apparaat'] );
		$taal_totaal = array_sum( $apparaat['taal'] );
		if ( $app_totaal ) {
			printf(
				'<p class="hdp-g-opmerking">%d%% op de telefoon of tablet · %d%% in het Frans</p>',
				(int) round( $apparaat['apparaat']['mobiel'] / $app_totaal * 100 ),
				(int) ( $taal_totaal ? round( $apparaat['taal']['fr'] / $taal_totaal * 100 ) : 0 )
			);
		}
	}

	private static function trechter_uitrol( array $uitrol ) {
		$stappen = array(
			array( 'Gekoppeld', $uitrol['totaal'] ),
			array( 'Uitgenodigd', $uitrol['totaal'] - $uitrol['niet'] ),
			array( 'Wachtwoord gekozen', $uitrol['gekozen'] ),
			array( 'Al eens in het portaal', $uitrol['geweest'] ),
		);
		echo '<div class="hdp-g-trechter">';
		foreach ( $stappen as $i => $s ) {
			printf(
				'<div><span class="hdp-g-trechter-kop"><span>%1$s</span><b>%2$d</b></span><i><u class="is-%3$d" style="width:%4$d%%"></u></i></div>',
				esc_html( $s[0] ),
				(int) $s[1],
				(int) $i + 1,
				(int) ( $uitrol['totaal'] ? max( $s[1] ? 2 : 0, round( $s[1] / $uitrol['totaal'] * 100 ) ) : 0 )
			);
		}
		echo '</div>';
	}

	/* ---------------------------------------------------------------------
	 * Dealers
	 * ------------------------------------------------------------------- */

	private static function tab_dealers( $dagen ) {
		$lijst  = HDP_Gebruik::dealerlijst( $dagen );
		$uitrol = HDP_Gebruik::uitrol();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- alleen een filterkeuze.
		$filter = isset( $_GET['activiteit'] ) ? sanitize_key( wp_unslash( $_GET['activiteit'] ) ) : '';
		if ( ! isset( self::ACTIVITEITEN[ $filter ] ) ) {
			$filter = '';
		}

		if ( ! $lijst ) {
			self::kaart_open( 'Dealers' );
			self::leeg( 'Er zijn nog geen goedgekeurde dealers.', 'groups' );
			self::kaart_dicht();
			return;
		}

		usort(
			$lijst,
			static function ( $a, $b ) {
				return array( $b['omzet'], $b['bezoekdagen'], $a['bedrijf'] ) <=> array( $a['omzet'], $a['bezoekdagen'], $b['bedrijf'] );
			}
		);
		$tellingen = array_fill_keys( array_keys( self::ACTIVITEITEN ), 0 );
		foreach ( $lijst as $rij ) {
			++$tellingen[ $rij['activiteit']['code'] ];
		}
		$volgorde = array( 'actief' => 4, 'af-en-toe' => 3, 'stil' => 2, 'nooit' => 1 );
		?>
		<div class="hdp-g-dealers" data-filter="<?php echo esc_attr( $filter ); ?>">
			<section class="hdp-g-kaart hdp-g-dealerlijst" aria-label="Alle bedrijven">
				<div class="hdp-g-filters">
					<label class="screen-reader-text" for="hdp-g-zoek">Zoek een bedrijf</label>
					<input type="search" id="hdp-g-zoek" placeholder="Zoek op bedrijf, plaats, naam of klantnummer" autocomplete="off">
					<div class="hdp-g-keuzes" role="group" aria-label="Filter op activiteit">
						<button type="button" data-activiteit="" aria-pressed="<?php echo '' === $filter ? 'true' : 'false'; ?>">Alle <span><?php echo count( $lijst ); ?></span></button>
						<?php foreach ( self::ACTIVITEITEN as $code => $naam ) : ?>
							<button type="button" data-activiteit="<?php echo esc_attr( $code ); ?>" aria-pressed="<?php echo $code === $filter ? 'true' : 'false'; ?>"><i style="background: <?php echo esc_attr( self::ACTIVITEIT_KLEUR[ $code ] ); ?>" aria-hidden="true"></i><?php echo esc_html( $naam ); ?> <span><?php echo (int) $tellingen[ $code ]; ?></span></button>
						<?php endforeach; ?>
					</div>
				</div>

				<div class="hdp-g-lijstkop">
					<button type="button" data-sorteer="bedrijf" data-soort="tekst">Bedrijf</button>
					<button type="button" data-sorteer="activiteit">Activiteit</button>
					<span class="hdp-g-kol-weken">Laatste 12 weken</span>
					<button type="button" data-sorteer="bestellingen" class="is-rechts hdp-g-kol-bestellingen">Bestellingen</button>
					<button type="button" data-sorteer="omzet" class="is-rechts" aria-sort="descending">Omzet</button>
				</div>
				<div class="hdp-g-rijen" id="hdp-g-rijen">
					<?php foreach ( $lijst as $rij ) : ?>
						<?php
						$hoogste = max( 1, max( $rij['weken'] ) );
						$zoek    = mb_strtolower( implode( ' ', array( $rij['bedrijf'], $rij['plaats'], $rij['naam'], $rij['email'], $rij['klantnummer'] ) ) );
						?>
						<button type="button" class="hdp-g-rij" aria-pressed="false" aria-controls="hdp-g-detail-<?php echo (int) $rij['id']; ?>"
							data-id="<?php echo (int) $rij['id']; ?>"
							data-zoek="<?php echo esc_attr( $zoek ); ?>"
							data-activiteit="<?php echo esc_attr( $rij['activiteit']['code'] ); ?>"
							data-s-bedrijf="<?php echo esc_attr( mb_strtolower( $rij['bedrijf'] ) ); ?>"
							data-s-activiteit="<?php echo (int) $volgorde[ $rij['activiteit']['code'] ]; ?>"
							data-s-bestellingen="<?php echo (int) $rij['bestellingen']; ?>"
							data-s-omzet="<?php echo esc_attr( (string) round( $rij['omzet'], 2 ) ); ?>">
							<span class="hdp-g-rij-naam">
								<?php echo self::avatar( $rij['id'], $rij['bedrijf'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- geëscaped in avatar. ?>
								<span><strong><?php echo esc_html( $rij['bedrijf'] ); ?></strong><small><?php echo esc_html( implode( ' · ', array_filter( array( $rij['plaats'], self::wanneer( $rij['laatst'] ) ) ) ) ); ?></small></span>
							</span>
							<span class="hdp-g-kol-activiteit"><?php echo self::label( $rij['activiteit']['code'], $rij['activiteit']['tekst'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
							<span class="hdp-g-mini hdp-g-kol-weken" aria-label="<?php echo esc_attr( 'Bezoekdagen per week: ' . implode( ', ', $rij['weken'] ) ); ?>">
								<?php foreach ( $rij['weken'] as $waarde ) : ?>
									<i class="<?php echo $waarde ? ( 'stil' === $rij['activiteit']['code'] ? 'is-zacht' : 'is-vol' ) : ''; ?>" style="height: <?php echo (int) ( $waarde ? max( 4, round( $waarde / $hoogste * 26 ) ) : 2 ); ?>px"></i>
								<?php endforeach; ?>
							</span>
							<span class="is-rechts hdp-g-kol-bestellingen"><?php echo (int) $rij['bestellingen']; ?></span>
							<span class="is-rechts hdp-g-vet"><?php echo $rij['omzet'] ? esc_html( self::geld_rond( $rij['omzet'] ) ) : '—'; ?></span>
						</button>
					<?php endforeach; ?>
				</div>
				<p class="hdp-g-leeg-tekst" id="hdp-g-geen-treffer" hidden>Geen bedrijf gevonden.</p>
			</section>

			<aside class="hdp-g-kaart hdp-g-detail" aria-label="Details van het gekozen bedrijf" id="hdp-g-detail">
				<?php foreach ( $lijst as $rij ) : ?>
					<?php self::detail( $rij ); ?>
				<?php endforeach; ?>
			</aside>
		</div>

		<?php if ( $uitrol['nalopen'] ) : ?>
			<section class="hdp-g-kaart" id="nalopen">
				<div class="hdp-g-kaart-kop"><h2>Uitgenodigd, nog geen wachtwoord gekozen</h2><p>Een verlopen uitnodiging stuur je opnieuw via <a href="<?php echo esc_url( admin_url( 'users.php?page=' . HDP_PowerAll_Dealers::PAGINA ) ); ?>">Gebruikers &gt; Dealers uit PowerAll</a>.</p></div>
				<div class="hdp-g-scroll">
					<table class="hdp-g-tabel">
						<thead><tr><th>Dealer</th><th>Klantnummer</th><th>Stand</th></tr></thead>
						<tbody>
						<?php foreach ( $uitrol['nalopen'] as $dealer ) : ?>
							<tr>
								<td><?php echo self::bedrijf_link( $dealer['id'], $dealer['naam'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><br><small><?php echo esc_html( $dealer['email'] ); ?></small></td>
								<td><?php echo esc_html( $dealer['relatie'] ); ?></td>
								<td><?php echo esc_html( $dealer['status']['tekst'] ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>
		<?php endif; ?>
		<?php
	}

	/** Het detailpaneel van één bedrijf (verborgen tot het in de lijst gekozen wordt). */
	private static function detail( array $rij ) {
		$telefoon = preg_replace( '/[^0-9+]/', '', $rij['telefoon'] );
		$delen    = array();
		foreach ( $rij['onderdelen'] as $sleutel => $aantal ) {
			if ( isset( HDP_Gebruik::ONDERDELEN[ $sleutel ] ) ) {
				$delen[] = array( 'label' => HDP_Gebruik::ONDERDELEN[ $sleutel ], 'waarde' => $aantal, 'tekst' => $aantal . ( 1 === (int) $aantal ? ' dag' : ' dagen' ) );
			}
		}
		?>
		<div class="hdp-g-detail-paneel" id="hdp-g-detail-<?php echo (int) $rij['id']; ?>" data-id="<?php echo (int) $rij['id']; ?>" hidden>
			<div class="hdp-g-detail-kop">
				<?php echo self::avatar( $rij['id'], $rij['bedrijf'], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div>
					<h2><?php echo esc_html( $rij['bedrijf'] ); ?></h2>
					<p><?php echo esc_html( implode( ' · ', array_filter( array( $rij['plaats'], $rij['klantnummer'] ? 'klantnummer ' . $rij['klantnummer'] : '' ) ) ) ); ?></p>
				</div>
			</div>
			<div class="hdp-g-detail-acties">
				<?php if ( $telefoon ) : ?>
					<a class="is-donker" href="<?php echo esc_url( 'tel:' . $telefoon ); ?>"><span class="dashicons dashicons-phone" aria-hidden="true"></span><?php echo esc_html( $rij['telefoon'] ); ?></a>
				<?php endif; ?>
				<a href="<?php echo esc_url( 'mailto:' . $rij['email'] ); ?>"><span class="dashicons dashicons-email" aria-hidden="true"></span>Mailen</a>
				<a href="<?php echo esc_url( get_edit_user_link( $rij['id'] ) ); ?>"><span class="dashicons dashicons-admin-users" aria-hidden="true"></span>Profiel</a>
			</div>
			<div class="hdp-g-detail-cijfers">
				<div><span>Bezoekdagen</span><strong><?php echo (int) $rij['bezoekdagen']; ?></strong></div>
				<div><span>Bestellingen</span><strong><?php echo (int) $rij['bestellingen']; ?></strong></div>
				<div><span>Omzet</span><strong><?php echo esc_html( $rij['omzet'] ? self::geld_rond( $rij['omzet'] ) : '—' ); ?></strong></div>
			</div>
			<p class="hdp-g-opmerking"><?php echo esc_html( sprintf( 'Laatst geweest: %s · laatste bestelling: %s · %d documenten geopend · %d trainingaanmeldingen', self::wanneer( $rij['laatst'] ), $rij['laatste_bestelling'] ? self::datum( $rij['laatste_bestelling'] ) : 'geen in deze periode', $rij['downloads'], $rij['trainingen'] ) ); ?></p>

			<h3>Wat ze doen</h3>
			<?php if ( $delen ) : ?>
				<?php self::staven( array_slice( $delen, 0, 6 ) ); ?>
			<?php else : ?>
				<p class="hdp-g-leeg-tekst">Nog niets gemeten in deze periode.</p>
			<?php endif; ?>

			<h3>Zocht naar</h3>
			<?php if ( $rij['termen'] ) : ?>
				<div class="hdp-g-chips">
					<?php foreach ( $rij['termen'] as $term ) : ?>
						<span><?php echo esc_html( $term ); ?></span>
					<?php endforeach; ?>
				</div>
			<?php else : ?>
				<p class="hdp-g-leeg-tekst">Niets gezocht in deze periode.</p>
			<?php endif; ?>

			<h3>Accounts</h3>
			<ul class="hdp-g-accounts">
				<?php foreach ( $rij['personen'] as $persoon ) : ?>
					<li><span><?php echo esc_html( $persoon['naam'] ); ?> <small><?php echo esc_html( $persoon['rol'] ); ?></small></span><span class="hdp-g-zacht"><?php echo esc_html( self::wanneer( $persoon['laatst'] ) ); ?></span></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Webshop
	 * ------------------------------------------------------------------- */

	private static function tab_webshop( $dagen ) {
		$nu        = HDP_Gebruik::kerncijfers( $dagen );
		$toen      = HDP_Gebruik::kerncijfers( $dagen, true );
		$cijfers   = HDP_Gebruik::webshopcijfers( $dagen );
		$wagens    = HDP_Gebruik::open_winkelwagens();
		$pa_fouten = HDP_Gebruik::powerall_fouten();
		$bedrijven = array_values(
			array_filter(
				HDP_Gebruik::dealerlijst( $dagen ),
				static function ( $rij ) {
					return $rij['bestellingen'] > 0;
				}
			)
		);
		usort(
			$bedrijven,
			static function ( $a, $b ) {
				return $b['omzet'] <=> $a['omzet'];
			}
		);
		$gemiddeld = $nu['bestellingen'] ? $nu['omzet'] / $nu['bestellingen'] : 0;
		$vorige    = ' t.o.v. vorige ' . self::periode_tekst( $dagen );

		if ( $pa_fouten ) :
			?>
			<div class="hdp-g-melding">
				<span class="dashicons dashicons-warning" aria-hidden="true"></span>
				<div>
					<?php if ( 'aan' === HDP_PowerAll_Orders::modus() ) : ?>
						<p><strong><?php echo esc_html( count( $pa_fouten ) . ( 1 === count( $pa_fouten ) ? ' bestelling is' : ' bestellingen zijn' ) ); ?> niet in PowerAll aangekomen.</strong> Voer ze met de hand in PowerAll in, of open de bestelling en kies bij Bestelacties "Opnieuw naar PowerAll versturen".</p>
					<?php else : ?>
						<p><strong>Bij <?php echo esc_html( count( $pa_fouten ) . ( 1 === count( $pa_fouten ) ? ' bestelling' : ' bestellingen' ) ); ?> ging de proef met PowerAll mis.</strong> Er gaat nu nog niets echt naar PowerAll, maar zo zou het straks ook misgaan.</p>
					<?php endif; ?>
					<ul>
						<?php foreach ( array_slice( $pa_fouten, 0, 10 ) as $order ) : ?>
							<li><a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">Bestelling <?php echo esc_html( $order->get_order_number() ); ?></a> — <?php echo esc_html( $order->get_billing_company() ? $order->get_billing_company() : $order->get_formatted_billing_full_name() ); ?>: <?php echo esc_html( (string) $order->get_meta( HDP_PowerAll_Orders::META_FOUT ) ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
			<?php
		endif;
		?>
		<div class="hdp-g-tegels">
			<?php
			self::tegel( (string) (int) $nu['bestellingen'], 'Bestellingen', self::pil( $nu['bestellingen'], $toen['bestellingen'] ) . esc_html( $vorige ), 'cart' );
			self::tegel( esc_html( self::geld_rond( $nu['omzet'] ) ), 'Omzet excl. btw', self::pil( $nu['omzet'], $toen['omzet'], 'procent' ) . esc_html( $vorige ), 'money-alt' );
			self::tegel( esc_html( self::geld( $gemiddeld ) ), 'Gemiddelde bestelling', 'excl. btw, met verzending en toeslag', 'calculator' );
			self::tegel( (int) $cijfers['toeslag']['keer'] . '<small> keer</small>', 'Kleinordertoeslag', 'samen ' . esc_html( self::geld( $cijfers['toeslag']['bedrag'] ) ), 'tag' );
			?>
		</div>

		<div class="hdp-g-raster">
			<?php
			self::kaart_open( 'Omzet per merk', 'Artikelen excl. btw, zonder verzending en toeslag. Het merk is de hoofdcategorie in de webshop.' );
			if ( $cijfers['merken'] ) {
				$rijen = array();
				foreach ( $cijfers['merken'] as $merk => $bedrag ) {
					$rijen[] = array( 'label' => $merk, 'waarde' => $bedrag, 'tekst' => self::geld( $bedrag ) );
				}
				self::staven( $rijen );
			} else {
				self::leeg( 'In deze periode geen bestellingen.', 'cart' );
			}
			self::kaart_dicht();

			self::kaart_open( 'Meest bestelde artikelen', 'Op aantal stuks.' );
			if ( $cijfers['artikelen'] ) :
				?>
				<div class="hdp-g-scroll"><table class="hdp-g-tabel">
					<thead><tr><th>Artikel</th><th class="num">Stuks</th><th class="num">Bedrijven</th><th class="num">Omzet</th></tr></thead>
					<tbody>
					<?php foreach ( array_slice( $cijfers['artikelen'], 0, 15 ) as $artikel ) : ?>
						<tr>
							<td><?php echo get_post( $artikel['id'] ) ? '<a href="' . esc_url( (string) get_edit_post_link( $artikel['id'] ) ) . '">' . esc_html( $artikel['naam'] ) . '</a>' : esc_html( $artikel['naam'] ); ?><?php echo $artikel['sku'] ? '<br><small>' . esc_html( $artikel['sku'] ) . '</small>' : ''; ?></td>
							<td class="num"><?php echo (int) $artikel['stuks']; ?></td>
							<td class="num"><?php echo (int) $artikel['bedrijven']; ?></td>
							<td class="num"><?php echo esc_html( self::geld( $artikel['omzet'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table></div>
				<?php
			else :
				self::leeg( 'In deze periode geen bestellingen.', 'cart' );
			endif;
			self::kaart_dicht();

			self::kaart_open( 'Per bedrijf', 'Hoogste omzet bovenaan; bestellingen van collega\'s tellen mee bij hun bedrijf.' );
			if ( $bedrijven ) :
				?>
				<div class="hdp-g-scroll"><table class="hdp-g-tabel">
					<thead><tr><th>Bedrijf</th><th class="num">Bestellingen</th><th class="num">Omzet</th><th class="num">Laatste</th></tr></thead>
					<tbody>
					<?php foreach ( array_slice( $bedrijven, 0, 25 ) as $rij ) : ?>
						<tr>
							<td><?php echo self::bedrijf_link( $rij['id'], $rij['bedrijf'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td class="num"><?php echo (int) $rij['bestellingen']; ?></td>
							<td class="num"><?php echo esc_html( self::geld( $rij['omzet'] ) ); ?></td>
							<td class="num"><?php echo esc_html( self::datum( $rij['laatste_bestelling'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table></div>
				<?php
			else :
				self::leeg( 'In deze periode geen bestellingen.', 'cart' );
			endif;
			self::kaart_dicht();

			self::kaart_open( 'Winkelwagens die nog niet besteld zijn', 'Nu, los van de periode. <strong>Vergeten</strong> = de dealer is al ' . (int) HDP_Gebruik::VERGETEN_DAGEN . ' dagen of langer niet geweest; misschien een belletje waard.', 'winkelwagens' );
			if ( $wagens ) :
				?>
				<div class="hdp-g-scroll"><table class="hdp-g-tabel">
					<thead><tr><th>Bedrijf</th><th class="num">Artikelen</th><th class="num">Waarde</th><th class="num">Laatst geweest</th></tr></thead>
					<tbody>
					<?php foreach ( $wagens as $wagen ) : ?>
						<tr>
							<td><?php echo self::bedrijf_link( $wagen['bedrijf_id'], $wagen['bedrijf'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $wagen['bedrijf_id'] !== $wagen['id'] ? '<br><small>' . esc_html( $wagen['naam'] ) . '</small>' : ''; ?></td>
							<td class="num"><?php echo (int) $wagen['stuks']; ?></td>
							<td class="num"><?php echo esc_html( self::geld( $wagen['waarde'] ) ); ?></td>
							<td class="num"><?php echo esc_html( self::datum( $wagen['laatst'] ) ); ?><?php echo $wagen['vergeten'] ? ' ' . self::label( 'stil', 'Vergeten' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table></div>
				<?php
			else :
				self::leeg( 'Geen open winkelwagens.', 'cart' );
			endif;
			self::kaart_dicht();

			self::kaart_open( 'Gezocht in de webshop, niets gevonden', 'Het artikel ontbreekt, heet anders, of hoort bij een merk dat deze dealers niet voeren.' );
			self::termen( HDP_Gebruik::zoektermen( $dagen, true, 20, 'webshopzoek' ) );
			self::kaart_dicht();

			self::kaart_open( 'Meest gezocht in de webshop' );
			self::termen( HDP_Gebruik::zoektermen( $dagen, false, 20, 'webshopzoek' ) );
			self::kaart_dicht();
			?>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Documenten & zoeken
	 * ------------------------------------------------------------------- */

	private static function tab_inhoud( $dagen ) {
		$downloads  = HDP_Gebruik::top_downloads( $dagen );
		$ongebruikt = HDP_Gebruik::ongebruikte_downloads( $dagen );
		?>
		<div class="hdp-g-raster">
			<?php
			self::kaart_open( 'Gezocht, niets gevonden', 'In de zoekbalk bovenin. Vaak een artikel of document dat ontbreekt, of een andere schrijfwijze.' );
			self::termen( HDP_Gebruik::zoektermen( $dagen, true ) );
			self::kaart_dicht();

			self::kaart_open( 'Meest gezocht', 'Alle zoekopdrachten in de zoekbalk bovenin, vaakst eerst. Zoeken in de webshop staat onder Webshop.' );
			self::termen( HDP_Gebruik::zoektermen( $dagen ) );
			self::kaart_dicht();

			self::kaart_open( 'Meest geopende documenten' );
			if ( $downloads ) :
				?>
				<div class="hdp-g-scroll"><table class="hdp-g-tabel">
					<thead><tr><th>Document</th><th class="num">Keer</th><th class="num">Dealers</th></tr></thead>
					<tbody>
					<?php foreach ( $downloads as $rij ) : ?>
						<?php $titel = get_the_title( (int) $rij->post_id ); ?>
						<tr>
							<td><?php echo $titel ? '<a href="' . esc_url( (string) get_edit_post_link( (int) $rij->post_id ) ) . '">' . esc_html( $titel ) . '</a>' : '<em>Verwijderd document</em>'; ?></td>
							<td class="num"><?php echo (int) $rij->keer; ?></td>
							<td class="num"><?php echo (int) $rij->dealers; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table></div>
				<?php
			else :
				self::leeg( 'In deze periode nog niets geopend.', 'media-document' );
			endif;
			self::kaart_dicht();

			self::kaart_open( 'Door niemand geopend', 'Documenten die in deze periode door geen enkele dealer zijn geopend. Verouderd, verkeerd genoemd, of niet te vinden?' );
			if ( $ongebruikt ) :
				?>
				<div class="hdp-g-scroll"><table class="hdp-g-tabel">
					<thead><tr><th>Document</th><th class="num">Online sinds</th></tr></thead>
					<tbody>
					<?php foreach ( array_slice( $ongebruikt, 0, 25 ) as $post ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( (string) get_edit_post_link( $post->ID ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></td>
							<td class="num"><?php echo esc_html( self::datum( (int) get_post_time( 'U', true, $post ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table></div>
				<?php if ( count( $ongebruikt ) > 25 ) : ?>
					<p class="hdp-g-opmerking">En nog <?php echo (int) ( count( $ongebruikt ) - 25 ); ?> andere.</p>
				<?php endif; ?>
				<?php
			else :
				self::leeg( 'Elk document is in deze periode minstens één keer geopend.', 'yes-alt' );
			endif;
			self::kaart_dicht();
			?>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Trainingen
	 * ------------------------------------------------------------------- */

	private static function tab_trainingen( $dagen ) {
		$trainingen = HDP_Gebruik::trainingen( $dagen );
		$fouten     = HDP_Gebruik::training_fouten();
		if ( $fouten ) :
			?>
			<div class="hdp-g-melding"><span class="dashicons dashicons-warning" aria-hidden="true"></span><div><p><strong><?php echo esc_html( $fouten . ( 1 === $fouten ? ' aanmelding is' : ' aanmeldingen zijn' ) ); ?> niet als order in PowerAll aangekomen.</strong> <a href="<?php echo esc_url( HDP_Trainingen::beheer_url() ); ?>">Naar de aanmeldingen</a> en kies daar "Opnieuw versturen".</p></div></div>
			<?php
		endif;
		$cijfers = array_sum( wp_list_pluck( $trainingen, 'cijfers' ) );
		$som     = 0.0;
		foreach ( $trainingen as $t ) {
			$som += $t['gemiddeld'] * $t['cijfers'];
		}
		?>
		<div class="hdp-g-tegels">
			<?php
			self::tegel( (string) (int) array_sum( wp_list_pluck( $trainingen, 'aanmeldingen' ) ), 'Aanmeldingen', '', 'welcome-learn-more' );
			self::tegel( $cijfers ? esc_html( number_format_i18n( $som / $cijfers, 1 ) ) : '—', 'Gemiddeld cijfer', (int) $cijfers . ' beoordelingen', 'star-filled' );
			?>
		</div>
		<?php
		self::kaart_open( 'Per training', 'Aanmeldingen en beoordelingen in deze periode (cijfer van 1 tot 10).' );
		if ( $trainingen ) :
			?>
			<div class="hdp-g-scroll"><table class="hdp-g-tabel">
				<thead><tr><th>Training</th><th class="num">Aanmeldingen</th><th class="num">Bedrijven</th><th class="num">Gemiddeld cijfer</th></tr></thead>
				<tbody>
				<?php foreach ( $trainingen as $training ) : ?>
					<tr>
						<td><?php echo get_post( $training['id'] ) ? '<a href="' . esc_url( (string) get_edit_post_link( $training['id'] ) ) . '">' . esc_html( $training['titel'] ) . '</a>' : esc_html( $training['titel'] ); ?></td>
						<td class="num"><?php echo (int) $training['aanmeldingen']; ?></td>
						<td class="num"><?php echo (int) $training['bedrijven']; ?></td>
						<td class="num"><?php echo $training['cijfers'] ? esc_html( number_format_i18n( $training['gemiddeld'], 1 ) . ' (' . $training['cijfers'] . 'x)' ) : '—'; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table></div>
			<?php
		else :
			self::leeg( 'In deze periode geen aanmeldingen of beoordelingen.', 'welcome-learn-more' );
		endif;
		self::kaart_dicht();
	}

	/* ---------------------------------------------------------------------
	 * Losse onderdelen
	 * ------------------------------------------------------------------- */

	private static function termen( $termen ) {
		if ( ! $termen ) {
			self::leeg( 'In deze periode nog niets.', 'search' );
			return;
		}
		?>
		<div class="hdp-g-scroll"><table class="hdp-g-tabel">
			<thead><tr><th>Zoekterm</th><th class="num">Keer</th><th class="num">Dealers</th><th class="num">Laatst</th></tr></thead>
			<tbody>
			<?php foreach ( $termen as $rij ) : ?>
				<tr>
					<td><?php echo esc_html( $rij->term ); ?></td>
					<td class="num"><?php echo (int) $rij->keer; ?></td>
					<td class="num"><?php echo (int) $rij->dealers; ?></td>
					<td class="num"><?php echo esc_html( self::datum( strtotime( $rij->laatst . ' UTC' ) ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table></div>
		<?php
	}

	/** Op het profiel van een dealer: zijn bedrijf in het kort. */
	public static function render_profiel( $user ) {
		if ( ! current_user_can( 'manage_options' ) || ! HDP_Roles::mag_portaal_zien( $user->ID ) || user_can( $user->ID, 'manage_options' ) ) {
			return;
		}
		$bedrijf_id = HDP_Gebruik::bedrijf_van( $user->ID );
		$test       = HDP_Gebruik::is_testaccount( $user->ID ) ? ' <em>(testaccount: telt niet mee in de cijfers)</em>' : '';
		?>
		<h2 id="hdp-portaalgebruik">Portaalgebruik</h2>
		<?php if ( $bedrijf_id !== (int) $user->ID ) : ?>
			<table class="form-table" role="presentation">
				<tr><th>Laatst geweest</th><td><?php echo esc_html( self::datum( HDP_Gebruik::laatst_actief( $user->ID ) ) ); ?><?php echo wp_kses_post( $test ); ?></td></tr>
				<tr><th>Bedrijf</th><td>Collega bij <?php echo self::bedrijf_link( $bedrijf_id, HDP_Gebruik::bedrijfsnaam( $bedrijf_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>. Zijn bezoeken en bestellingen tellen mee bij het bedrijf; de cijfers staan daar.</td></tr>
			</table>
			<?php
			return;
		endif;

		$kwartaal = HDP_Gebruik::dealerlijst( 90, $user->ID );
		$jaar     = HDP_Gebruik::dealerlijst( 365, $user->ID );
		if ( ! $kwartaal || ! $jaar ) {
			return;
		}
		$c = $kwartaal[0];
		$j = $jaar[0];
		?>
		<style>.hdp-g-label{display:inline-block;padding:1px 8px;border-radius:10px;font-size:12px;font-weight:600}.hdp-g-label.is-actief{background:#eaf6ee;color:#146c34}.hdp-g-label.is-af-en-toe{background:#fff3d6;color:#6b4b00}.hdp-g-label.is-stil{background:#fdecec;color:#8a1012}.hdp-g-label.is-nooit{background:#f0f0ec;color:#3d4148}</style>
		<table class="form-table" role="presentation">
			<tr><th>Laatst geweest</th><td><?php echo esc_html( $c['laatst'] ? wp_date( 'j F Y', $c['laatst'] ) : 'Nog nooit (sinds de meting)' ); ?> · <?php echo self::label( $c['activiteit']['code'], $c['activiteit']['tekst'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo wp_kses_post( $test ); ?></td></tr>
			<tr><th>Laatste 3 maanden</th><td><?php echo esc_html( sprintf( '%d bezoekdagen · %d documenten geopend · %d zoekopdrachten · %d trainingaanmeldingen', $c['bezoekdagen'], $c['downloads'], $c['zoeken'], $c['trainingen'] ) ); ?><?php echo $c['collegas'] ? esc_html( sprintf( ' (met %d %s)', $c['collegas'], 1 === $c['collegas'] ? 'collega' : "collega's" ) ) : ''; ?></td></tr>
			<tr><th>Webshop, laatste 12 maanden</th><td><?php echo esc_html( sprintf( '%d bestellingen, %s excl. btw', $j['bestellingen'], self::geld( $j['omzet'] ) ) ); ?></td></tr>
			<?php if ( $c['termen'] ) : ?>
				<tr><th>Zocht naar</th><td><?php echo esc_html( implode( ', ', $c['termen'] ) ); ?></td></tr>
			<?php endif; ?>
			<tr><th>Meer</th><td><a href="<?php echo esc_url( self::url( 'dealers', 90 ) ); ?>">Alle bedrijven in Portaalgebruik</a></td></tr>
		</table>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Opmaak en script
	 * ------------------------------------------------------------------- */

	private static function stijl() {
		?>
		<style>
			.hdp-gebruik { --ink: #17191c; --zacht: #5c6068; --rand: #e3e3df; --vlak: #f7f7f5; --rood: #c41214; color: var(--ink); font-size: 14px; max-width: 1400px; }
			.hdp-gebruik h1, .hdp-gebruik h2, .hdp-gebruik h3 { color: var(--ink); }
			.hdp-gebruik a { color: var(--ink); }
			.hdp-gebruik a:hover, .hdp-gebruik a:focus { color: var(--rood); }
			.hdp-gebruik .hdp-g-zacht { color: var(--zacht); font-weight: 400; }
			.hdp-gebruik .hdp-g-vet { font-weight: 600; }

			.hdp-g-kop { display: flex; flex-wrap: wrap; gap: 12px 16px; align-items: center; justify-content: space-between; margin: 8px 0 16px; }
			.hdp-gebruik .hdp-g-kop h1 { margin: 0; padding: 0; font-size: 28px; font-weight: 700; letter-spacing: -0.02em; line-height: 1.2; }
			.hdp-g-kop-acties { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
			.hdp-g-segment { display: inline-flex; background: #fff; border: 1px solid var(--rand); border-radius: 12px; padding: 4px; gap: 2px; }
			.hdp-gebruik .hdp-g-segment a { padding: 8px 16px; border-radius: 9px; text-decoration: none; color: #3d4148; }
			.hdp-gebruik .hdp-g-segment a.is-actief { background: var(--ink); color: #fff; font-weight: 600; }
			.hdp-gebruik .hdp-g-knop { display: inline-flex; align-items: center; gap: 6px; padding: 10px 16px; border: 1px solid var(--rand); border-radius: 12px; background: #fff; text-decoration: none; font-weight: 500; }
			.hdp-g-knop .dashicons { font-size: 17px; width: 17px; height: 17px; }
			.hdp-g-tabs { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 20px; }
			.hdp-gebruik .hdp-g-tabs a { padding: 9px 16px; border-radius: 999px; text-decoration: none; color: #3d4148; border: 1px solid transparent; }
			.hdp-gebruik .hdp-g-tabs a.is-actief { background: #fff; border-color: var(--rand); color: var(--ink); font-weight: 600; }
			.hdp-g-tab-aantal { color: #6b6f76; font-weight: 400; }

			.hdp-g-kaart { background: #fff; border: 1px solid var(--rand); border-radius: 18px; padding: 22px 24px; display: flex; flex-direction: column; gap: 16px; min-width: 0; box-sizing: border-box; margin-bottom: 20px; }
			.hdp-g-raster .hdp-g-kaart, .hdp-g-rij-flex .hdp-g-kaart, .hdp-g-dealers .hdp-g-kaart { margin-bottom: 0; }
			.hdp-g-kaart-kop { display: flex; flex-direction: column; gap: 2px; }
			.hdp-gebruik .hdp-g-kaart-kop h2 { margin: 0; font-size: 17px; font-weight: 700; }
			.hdp-g-kaart-kop p { margin: 0; color: var(--zacht); font-size: 13px; }
			.hdp-g-raster { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(480px, 100%), 1fr)); gap: 20px; align-items: stretch; margin-bottom: 20px; }
			.hdp-g-rij-flex { display: flex; flex-wrap: wrap; gap: 20px; align-items: stretch; margin-bottom: 20px; }
			.hdp-g-rij-flex .is-breed { flex: 999 1 560px; }
			.hdp-g-rij-flex .is-smal { flex: 1 1 340px; }
			.hdp-g-leegte { display: flex; flex-direction: column; align-items: center; gap: 6px; padding: 26px 16px; background: var(--vlak); border-radius: 12px; text-align: center; color: var(--zacht); }
			.hdp-g-leegte .dashicons { font-size: 26px; width: 26px; height: 26px; }
			.hdp-g-leegte p { margin: 0; max-width: 360px; }
			.hdp-g-leeg-tekst { margin: 0; color: var(--zacht); font-size: 13px; }
			.hdp-g-opmerking { margin: 0; font-size: 13px; color: #3d4148; background: var(--vlak); border-radius: 10px; padding: 10px 12px; }
			.hdp-g-voet { color: var(--zacht); font-size: 12px; margin-top: 8px; }
			.hdp-g-voetnoot { color: var(--zacht); font-size: 12px; margin: -8px 0 20px; }

			.hdp-g-held { background: var(--ink); color: #fff; border-radius: 20px; padding: 30px 34px; display: flex; flex-wrap: wrap; gap: 28px; margin-bottom: 20px; }
			.hdp-g-held-tekst { flex: 999 1 460px; display: flex; flex-direction: column; gap: 12px; justify-content: center; }
			.hdp-g-held-label { color: #b4b8bf; font-size: 12px; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; }
			.hdp-g-held-zin { margin: 0; font-size: 28px; line-height: 1.28; font-weight: 600; letter-spacing: -0.015em; }
			.hdp-g-held-zin em { font-style: normal; color: #ff8a8c; }
			.hdp-g-held-onder { margin: 0; color: #c9ccd1; font-size: 15px; }
			.hdp-g-held-cijfers { flex: 1 1 320px; display: flex; flex-direction: column; gap: 10px; }
			.hdp-g-held-cijfer { display: flex; align-items: center; gap: 16px; padding: 14px 16px; background: #23262b; border-radius: 14px; }
			.hdp-g-held-cijfer > div { flex: 1; display: flex; flex-direction: column; }
			.hdp-g-held-cijfer > div > span { color: #b4b8bf; font-size: 12px; }
			.hdp-g-held-cijfer strong { font-size: 24px; font-weight: 700; font-variant-numeric: tabular-nums; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
			.hdp-g-held .hdp-g-pil { background: #33373d; color: #e3e5e8; }
			.hdp-g-held .hdp-g-pil.is-op { background: #1d3b27; color: #8fe3a5; }
			.hdp-g-held .hdp-g-pil.is-neer { background: #45201f; color: #ffb3b4; }
			.hdp-g-lijntje { flex-shrink: 0; }

			.hdp-g-pil { display: inline-flex; align-items: center; gap: 2px; padding: 2px 9px; border-radius: 999px; background: #f0f0ec; color: #3d4148; font-size: 12px; font-weight: 600; line-height: 1.5; }
			.hdp-g-pil.is-op { background: #eaf6ee; color: #146c34; }
			.hdp-g-pil.is-neer { background: #fdecec; color: #8a1012; }
			.hdp-g-label { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; white-space: nowrap; }
			.hdp-g-label.is-actief { background: #eaf6ee; color: #146c34; }
			.hdp-g-label.is-af-en-toe { background: #fff3d6; color: #6b4b00; }
			.hdp-g-label.is-stil { background: #fdecec; color: #8a1012; }
			.hdp-g-label.is-nooit { background: #f0f0ec; color: #3d4148; }
			.hdp-g-tegels { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 12px; margin-bottom: 20px; }
			.hdp-g-tegel { background: #fff; border: 1px solid var(--rand); border-radius: 16px; padding: 16px 18px; display: flex; flex-direction: column; gap: 10px; }
			.hdp-g-tegel-kop { display: flex; align-items: center; gap: 10px; color: var(--zacht); font-size: 13px; }
			.hdp-g-icoon.dashicons { width: 32px; height: 32px; border-radius: 9px; background: #fcf0f1; color: var(--rood); display: inline-flex; align-items: center; justify-content: center; font-size: 18px; }
			.hdp-g-tegel strong { font-size: 30px; line-height: 1; font-weight: 700; font-variant-numeric: tabular-nums; }
			.hdp-g-tegel strong small { font-size: 15px; font-weight: 400; color: var(--zacht); }
			.hdp-g-tegel-onder { font-size: 12px; color: var(--zacht); }
			.hdp-g-melding { display: flex; gap: 12px; background: #fdf2f2; border: 1px solid #f3c9c9; border-radius: 14px; padding: 14px 18px; margin-bottom: 20px; }
			.hdp-g-melding .dashicons { color: var(--rood); }
			.hdp-g-melding p { margin: 0 0 6px; }
			.hdp-g-melding ul { margin: 0; }

			.hdp-g-grafiek { display: flex; flex-direction: column; gap: 8px; }
			.hdp-g-uitlezing { display: flex; flex-direction: column; align-items: flex-end; margin-top: -50px; min-height: 44px; }
			.hdp-g-uitlezing strong { font-size: 20px; font-weight: 700; font-variant-numeric: tabular-nums; }
			.hdp-g-uitlezing span { font-size: 12px; color: var(--zacht); }
			.hdp-g-kolommen { position: relative; display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 10px; align-items: end; height: 210px; border-bottom: 1px solid var(--rand); }
			.hdp-g-kolom { height: 100%; display: flex; flex-direction: column; justify-content: flex-end; background: none; border: 0; padding: 0; cursor: pointer; }
			.hdp-g-kolom i { display: block; background: #c9ccd1; border-radius: 6px 6px 0 0; transition: background .15s; }
			.hdp-g-kolom.is-deze i { background: var(--ink); }
			.hdp-g-kolom:hover i, .hdp-g-kolom:focus-visible i { background: var(--rood); }
			.hdp-g-kolom:focus-visible { outline: 2px solid #2271b1; outline-offset: 2px; }
			.hdp-g-gemiddeld { position: absolute; left: 0; right: 0; border-top: 1px dashed #b9bcc2; pointer-events: none; }
			.hdp-g-gemiddeld span { position: absolute; right: 0; bottom: 2px; font-size: 11px; color: var(--zacht); background: #fff; padding: 0 4px; }
			.hdp-g-weeknummers { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 10px; text-align: center; font-size: 12px; color: var(--zacht); font-variant-numeric: tabular-nums; }
			.hdp-g-weeknummers .is-deze { color: var(--ink); font-weight: 700; }

			.hdp-g-donut-blok { display: flex; flex-wrap: wrap; align-items: center; gap: 20px; }
			.hdp-g-donut { position: relative; width: 148px; height: 148px; flex-shrink: 0; }
			.hdp-g-donut-midden { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
			.hdp-g-donut-midden strong { font-size: 30px; font-weight: 700; line-height: 1; }
			.hdp-g-donut-midden span { font-size: 12px; color: var(--zacht); }
			.hdp-g-legenda { flex: 1 1 150px; list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
			.hdp-g-legenda li { margin: 0; }
			.hdp-gebruik .hdp-g-legenda a { display: flex; align-items: center; gap: 10px; padding: 9px 12px; border-radius: 10px; background: var(--vlak); text-decoration: none; }
			.hdp-g-legenda i { width: 12px; height: 12px; border-radius: 4px; }
			.hdp-g-legenda span { flex: 1; }

			.hdp-g-tijdlijn { list-style: none; margin: 0; padding: 0; }
			.hdp-g-tijdlijn li { display: grid; grid-template-columns: 58px 28px 1fr; gap: 10px; align-items: start; padding: 9px 0; margin: 0; border-bottom: 1px solid #f0f0ec; }
			.hdp-g-tijdlijn li:last-child { border-bottom: 0; }
			.hdp-g-tijdlijn time { color: #6b6f76; font-size: 12px; font-variant-numeric: tabular-nums; padding-top: 4px; }
			.hdp-g-tijdlijn strong { font-weight: 600; }
			.hdp-g-tijdlijn-icoon.dashicons { width: 26px; height: 26px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; font-size: 15px; }
			.hdp-g-tijdlijn-icoon.is-groen { background: #eaf6ee; color: #146c34; }
			.hdp-g-tijdlijn-icoon.is-blauw { background: #eef1f6; color: #34507a; }
			.hdp-g-tijdlijn-icoon.is-geel { background: #fff3d6; color: #8a5a00; }
			.hdp-g-tijdlijn-icoon.is-paars { background: #f3eefb; color: #5b3a8f; }
			.hdp-g-tijdlijn-icoon.is-rood { background: #fdecec; color: #8a1012; }
			.hdp-g-tijdlijn-icoon.is-grijs { background: #f0f0ec; color: #3d4148; }

			.hdp-g-trechter { display: flex; flex-direction: column; gap: 12px; }
			.hdp-g-trechter-kop, .hdp-g-staaf-kop { display: flex; justify-content: space-between; gap: 10px; margin-bottom: 4px; }
			.hdp-g-trechter b, .hdp-g-staaf b { font-variant-numeric: tabular-nums; white-space: nowrap; }
			.hdp-g-trechter i { display: block; height: 14px; border-radius: 7px; background: #f0f0ec; }
			.hdp-g-trechter u { display: block; height: 100%; border-radius: 7px; text-decoration: none; }
			.hdp-g-trechter u.is-1 { background: var(--ink); }
			.hdp-g-trechter u.is-2 { background: #3d4148; }
			.hdp-g-trechter u.is-3 { background: #6b6f76; }
			.hdp-g-trechter u.is-4 { background: var(--rood); }
			.hdp-g-staven { display: flex; flex-direction: column; gap: 10px; }
			.hdp-g-staaf i { display: block; height: 8px; border-radius: 4px; background: #f0f0ec; }
			.hdp-g-staaf u { display: block; height: 100%; border-radius: 4px; background: var(--rood); text-decoration: none; }
			.hdp-g-staaf-kop span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
			.hdp-g-top { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 12px; }
			.hdp-g-top li { display: grid; grid-template-columns: 24px 1fr auto; gap: 10px; align-items: center; margin: 0; }
			.hdp-g-top-nr { color: var(--zacht); font-weight: 600; }
			.hdp-g-top-naam { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
			.hdp-g-top-naam span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
			.hdp-g-top-naam i { display: block; height: 6px; border-radius: 3px; background: var(--rood); }
			.hdp-g-top b { font-variant-numeric: tabular-nums; }
			.hdp-g-vakken { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
			.hdp-g-vakken div { padding: 12px 14px; border-radius: 12px; }
			.hdp-g-vakken span { display: block; font-size: 12px; }
			.hdp-g-vakken strong { display: block; font-size: 22px; font-weight: 700; }
			.hdp-g-vakken .is-1 { background: var(--ink); color: #fff; }
			.hdp-g-vakken .is-1 span { color: #c9ccd1; }
			.hdp-g-vakken .is-2 { background: #3d4148; color: #fff; }
			.hdp-g-vakken .is-2 span { color: #dcdee2; }
			.hdp-g-vakken .is-3 { background: #e3e3df; }
			.hdp-g-vakken .is-4 { background: #ebebe7; }
			.hdp-g-vakken .is-5 { background: #f0f0ec; }
			.hdp-g-vakken .is-6 { background: var(--vlak); }
			.hdp-g-vakken .is-3 span, .hdp-g-vakken .is-4 span, .hdp-g-vakken .is-5 span, .hdp-g-vakken .is-6 span { color: #3d4148; }

			.hdp-g-dealers { display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start; margin-bottom: 20px; }
			.hdp-g-dealerlijst { flex: 999 1 620px; }
			.hdp-g-detail { flex: 1 1 360px; position: sticky; top: 46px; }
			.hdp-g-filters { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
			.hdp-gebruik #hdp-g-zoek { flex: 1 1 240px; padding: 10px 14px; border: 1px solid #d9d9d4; border-radius: 12px; background: #fafaf8; font-size: 14px; }
			.hdp-g-keuzes { display: flex; flex-wrap: wrap; gap: 6px; }
			.hdp-g-keuzes button { display: inline-flex; align-items: center; gap: 6px; padding: 9px 14px; border-radius: 999px; border: 1px solid var(--rand); background: #fff; font: inherit; cursor: pointer; color: var(--ink); }
			.hdp-g-keuzes button span { color: var(--zacht); }
			.hdp-g-keuzes button[aria-pressed="true"] { background: var(--ink); border-color: var(--ink); color: #fff; font-weight: 600; }
			.hdp-g-keuzes button[aria-pressed="true"] span { color: #c9ccd1; }
			.hdp-g-keuzes i { width: 8px; height: 8px; border-radius: 50%; }
			.hdp-g-lijstkop, .hdp-g-rij { display: grid; grid-template-columns: minmax(200px, 2.2fr) 110px minmax(110px, 1.2fr) 96px 110px; gap: 12px; align-items: center; }
			.hdp-g-lijstkop { padding: 0 12px; }
			.hdp-g-lijstkop button, .hdp-g-lijstkop span { background: none; border: 0; padding: 0; font: inherit; font-size: 12px; font-weight: 600; color: var(--zacht); text-align: left; cursor: pointer; }
			.hdp-g-lijstkop span { cursor: default; }
			.hdp-g-lijstkop .is-rechts, .hdp-g-rij .is-rechts { text-align: right; justify-self: end; }
			.hdp-g-lijstkop button[aria-sort="ascending"]::after { content: " ↑"; }
			.hdp-g-lijstkop button[aria-sort="descending"]::after { content: " ↓"; }
			.hdp-g-rijen { display: flex; flex-direction: column; gap: 4px; }
			.hdp-g-rij { padding: 10px 12px; border-radius: 14px; border: 1px solid transparent; background: #fff; font: inherit; color: inherit; cursor: pointer; text-align: left; width: 100%; }
			.hdp-g-rij[hidden] { display: none; }
			.hdp-g-rij:hover { background: var(--vlak); }
			.hdp-g-rij[aria-pressed="true"] { background: var(--vlak); border-color: var(--ink); }
			.hdp-g-rij:focus-visible { outline: 2px solid #2271b1; outline-offset: 2px; }
			.hdp-g-rij-naam { display: flex; align-items: center; gap: 12px; min-width: 0; }
			.hdp-g-rij-naam > span { display: flex; flex-direction: column; min-width: 0; }
			.hdp-g-rij-naam strong, .hdp-g-rij-naam small { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
			.hdp-g-rij-naam small { color: var(--zacht); font-size: 12px; }
			.hdp-g-avatar { display: inline-flex; flex-shrink: 0; width: 38px; height: 38px; border-radius: 12px; color: #fff; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; }
			.hdp-g-avatar.is-groot { width: 56px; height: 56px; border-radius: 17px; font-size: 20px; }
			.hdp-g-mini { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 2px; align-items: end; height: 26px; }
			.hdp-g-mini i { display: block; border-radius: 2px; background: var(--rand); }
			.hdp-g-mini i.is-vol { background: var(--rood); }
			.hdp-g-mini i.is-zacht { background: #e8a7a8; }
			.hdp-g-detail-paneel { display: flex; flex-direction: column; gap: 16px; }
			.hdp-g-detail-paneel[hidden] { display: none; }
			.hdp-g-detail-kop { display: flex; gap: 14px; align-items: center; }
			.hdp-gebruik .hdp-g-detail-kop h2 { margin: 0; font-size: 19px; font-weight: 700; }
			.hdp-g-detail-kop p { margin: 0; color: var(--zacht); font-size: 13px; }
			.hdp-g-detail-acties { display: flex; flex-wrap: wrap; gap: 8px; }
			.hdp-gebruik .hdp-g-detail-acties a { flex: 1 1 110px; display: inline-flex; justify-content: center; align-items: center; gap: 6px; padding: 11px; border-radius: 12px; border: 1px solid var(--rand); text-decoration: none; font-weight: 600; }
			.hdp-gebruik .hdp-g-detail-acties a.is-donker { background: var(--ink); border-color: var(--ink); color: #fff; }
			.hdp-g-detail-acties .dashicons { font-size: 17px; width: 17px; height: 17px; }
			.hdp-g-detail-cijfers { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
			.hdp-g-detail-cijfers div { padding: 12px; border-radius: 12px; background: var(--vlak); }
			.hdp-g-detail-cijfers span { display: block; font-size: 12px; color: var(--zacht); }
			.hdp-g-detail-cijfers strong { display: block; font-size: 22px; font-weight: 700; font-variant-numeric: tabular-nums; }
			.hdp-gebruik .hdp-g-detail h3 { margin: 4px 0 -6px; font-size: 14px; font-weight: 700; }
			.hdp-g-chips { display: flex; flex-wrap: wrap; gap: 6px; }
			.hdp-g-chips span { padding: 5px 10px; border-radius: 999px; background: #f0f0ec; font-size: 13px; }
			.hdp-g-accounts { list-style: none; margin: 0; padding: 0; }
			.hdp-g-accounts li { display: flex; justify-content: space-between; gap: 10px; font-size: 13px; padding: 8px 0; margin: 0; border-bottom: 1px solid #f0f0ec; }
			.hdp-g-accounts small { color: var(--zacht); }

			.hdp-g-scroll { overflow-x: auto; }
			.hdp-g-tabel { width: 100%; border-collapse: collapse; }
			.hdp-g-tabel th { text-align: left; font-size: 12px; font-weight: 600; color: var(--zacht); padding: 8px 10px; border-bottom: 1px solid var(--rand); }
			.hdp-g-tabel td { padding: 10px; border-bottom: 1px solid #f0f0ec; vertical-align: top; }
			.hdp-g-tabel tr:last-child td { border-bottom: 0; }
			.hdp-g-tabel .num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
			.hdp-g-tabel small { color: var(--zacht); }

			@media (max-width: 900px) {
				.hdp-g-lijstkop, .hdp-g-rij { grid-template-columns: minmax(0, 1fr) auto 90px; }
				.hdp-g-kol-weken, .hdp-g-kol-bestellingen { display: none !important; }
				.hdp-g-detail { position: static; }
				.hdp-g-held { padding: 22px 20px; }
				.hdp-g-held-zin { font-size: 21px; }
				.hdp-g-uitlezing { margin-top: 0; align-items: flex-start; }
				.hdp-g-kolommen, .hdp-g-weeknummers { gap: 4px; }
				.hdp-g-tijdlijn li { grid-template-columns: 50px 28px 1fr; }
			}
			@media (max-width: 600px) {
				.hdp-g-segment { width: 100%; box-sizing: border-box; }
				.hdp-gebruik .hdp-g-segment a { flex: 1; text-align: center; padding: 8px 6px; }
				.hdp-g-lijstkop, .hdp-g-rij { grid-template-columns: minmax(0, 1fr) 84px; }
				.hdp-g-lijstkop button[data-sorteer="activiteit"], .hdp-g-kol-activiteit { display: none; }
				.hdp-g-kaart { padding: 18px 16px; }
				.hdp-g-weeknummers span:nth-child(odd) { visibility: hidden; }
			}
		</style>
		<?php
	}

	private static function script() {
		?>
		<script>
		( function () {
			// Omzet per week: aanwijzen of tabben toont die week.
			document.querySelectorAll( '.hdp-g-grafiek' ).forEach( function ( grafiek ) {
				var uit = grafiek.querySelector( '.hdp-g-uitlezing' );
				function toon( bedrag, uitleg ) {
					uit.querySelector( 'strong' ).textContent = bedrag;
					uit.querySelector( 'span' ).textContent = uitleg;
				}
				grafiek.querySelectorAll( '.hdp-g-kolom' ).forEach( function ( kolom ) {
					[ 'mouseenter', 'focus' ].forEach( function ( soort ) {
						kolom.addEventListener( soort, function () { toon( kolom.dataset.bedrag, kolom.dataset.uitleg ); } );
					} );
				} );
				grafiek.querySelector( '.hdp-g-kolommen' ).addEventListener( 'mouseleave', function () { toon( grafiek.dataset.bedrag, grafiek.dataset.uitleg ); } );
			} );

			// Dealers: kiezen, zoeken, filteren, sorteren.
			var lijst = document.getElementById( 'hdp-g-rijen' );
			if ( ! lijst ) { return; }
			var rijen = Array.prototype.slice.call( lijst.querySelectorAll( '.hdp-g-rij' ) );
			var zoek = document.getElementById( 'hdp-g-zoek' );
			var knoppen = document.querySelectorAll( '.hdp-g-keuzes button' );
			var geen = document.getElementById( 'hdp-g-geen-treffer' );
			var detail = document.getElementById( 'hdp-g-detail' );
			var activiteit = document.querySelector( '.hdp-g-dealers' ).dataset.filter || '';

			function kies( rij, scroll ) {
				rijen.forEach( function ( r ) { r.setAttribute( 'aria-pressed', r === rij ? 'true' : 'false' ); } );
				detail.querySelectorAll( '.hdp-g-detail-paneel' ).forEach( function ( p ) { p.hidden = ! rij || p.dataset.id !== rij.dataset.id; } );
				if ( scroll && window.innerWidth < 900 ) { detail.scrollIntoView( { behavior: 'smooth', block: 'start' } ); }
			}
			function filter() {
				var term = zoek.value.trim().toLowerCase();
				var eerste = null;
				var gekozenZichtbaar = false;
				rijen.forEach( function ( rij ) {
					var toon = ( ! term || rij.dataset.zoek.indexOf( term ) !== -1 ) && ( ! activiteit || rij.dataset.activiteit === activiteit );
					rij.hidden = ! toon;
					if ( toon && ! eerste ) { eerste = rij; }
					if ( toon && rij.getAttribute( 'aria-pressed' ) === 'true' ) { gekozenZichtbaar = true; }
				} );
				geen.hidden = !! eerste;
				detail.hidden = ! eerste;
				if ( ! gekozenZichtbaar ) { kies( eerste, false ); }
			}
			rijen.forEach( function ( rij ) { rij.addEventListener( 'click', function () { kies( rij, true ); } ); } );
			zoek.addEventListener( 'input', filter );
			knoppen.forEach( function ( knop ) {
				knop.setAttribute( 'aria-pressed', knop.dataset.activiteit === activiteit ? 'true' : 'false' );
				knop.addEventListener( 'click', function () {
					activiteit = knop.dataset.activiteit;
					knoppen.forEach( function ( k ) { k.setAttribute( 'aria-pressed', k === knop ? 'true' : 'false' ); } );
					filter();
				} );
			} );
			document.querySelectorAll( '.hdp-g-lijstkop button' ).forEach( function ( knop ) {
				knop.addEventListener( 'click', function () {
					var naam = knop.dataset.sorteer;
					var sleutel = 's' + naam.charAt( 0 ).toUpperCase() + naam.slice( 1 );
					var tekst = knop.dataset.soort === 'tekst';
					var oplopend = knop.getAttribute( 'aria-sort' ) ? knop.getAttribute( 'aria-sort' ) !== 'ascending' : tekst;
					document.querySelectorAll( '.hdp-g-lijstkop button' ).forEach( function ( k ) { k.removeAttribute( 'aria-sort' ); } );
					knop.setAttribute( 'aria-sort', oplopend ? 'ascending' : 'descending' );
					rijen.sort( function ( a, b ) {
						var x = a.dataset[ sleutel ], y = b.dataset[ sleutel ];
						var v = tekst ? x.localeCompare( y, 'nl' ) : parseFloat( x ) - parseFloat( y );
						return oplopend ? v : -v;
					} );
					rijen.forEach( function ( r ) { lijst.appendChild( r ); } );
				} );
			} );
			filter();
		} )();
		</script>
		<?php
	}
}
