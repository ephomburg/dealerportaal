<?php
/**
 * Het scherm Dashboard > Portaalgebruik, in tabbladen:
 * Overzicht (wat vraagt aandacht, kerncijfers met vergelijking, activiteit
 * per week, onderdelen, apparaat en taal, uitnodigingen), Dealers (één
 * lijst per bedrijf, te sorteren en te filteren), Webshop, Documenten &
 * zoeken, en Trainingen. Plus het blokje op het profiel van een dealer.
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

	public static function registreer_menu() {
		add_dashboard_page( 'Portaalgebruik', 'Portaalgebruik', 'manage_options', self::PAGINA, array( __CLASS__, 'render' ) );
	}

	/* ---------------------------------------------------------------------
	 * Hulpjes
	 * ------------------------------------------------------------------- */

	public static function url( $tab, $dagen, array $extra = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::PAGINA, 'tab' => $tab, 'periode' => (int) $dagen ), $extra ), admin_url( 'index.php' ) );
	}

	private static function geld( $bedrag ) {
		return function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $bedrag ) ) : '€ ' . number_format( (float) $bedrag, 2, ',', '.' );
	}

	private static function datum( $tijdstempel ) {
		return $tijdstempel ? wp_date( 'j M Y', $tijdstempel ) : '—';
	}

	/** "↑ 3 t.o.v. de vorige periode". Null = er valt niets te vergelijken. */
	private static function verschil( $nu, $toen, $geld = false ) {
		if ( null === $toen ) {
			return '<span class="hdp-g-delta">nog geen vergelijking</span>';
		}
		$verschil = $nu - $toen;
		if ( abs( $verschil ) < 0.005 ) {
			return '<span class="hdp-g-delta">gelijk aan de vorige periode</span>';
		}
		$tekst = $geld ? self::geld( abs( $verschil ) ) : number_format_i18n( abs( $verschil ) );
		return '<span class="hdp-g-delta"><b aria-hidden="true">' . ( $verschil > 0 ? '↑' : '↓' ) . '</b> ' . ( $verschil > 0 ? 'meer' : 'minder' ) . ': ' . esc_html( $tekst ) . ' t.o.v. de vorige periode</span>';
	}

	private static function tegel( $waarde, $label, $onder = '' ) {
		echo '<div class="hdp-g-tegel"><span class="hdp-g-tegel-label">' . esc_html( $label ) . '</span><strong>' . wp_kses_post( $waarde ) . '</strong>' . wp_kses_post( $onder ) . '</div>';
	}

	/**
	 * Liggende staven in één kleur, met de waarde ernaast als tekst.
	 *
	 * @param array<int, array{label: string, waarde: float, tekst: string}> $rijen
	 */
	private static function staven( array $rijen, $label_breedte = 150 ) {
		$hoogste = 0;
		foreach ( $rijen as $rij ) {
			$hoogste = max( $hoogste, (float) $rij['waarde'] );
		}
		$hoogste = $hoogste > 0 ? $hoogste : 1;
		echo '<div class="hdp-g-staven" style="--label: ' . (int) $label_breedte . 'px">';
		foreach ( $rijen as $rij ) {
			$breedte = (float) $rij['waarde'] > 0 ? max( 1, round( (float) $rij['waarde'] / $hoogste * 100, 1 ) ) : 0;
			printf(
				'<div class="hdp-g-staaf" title="%1$s: %2$s"><span>%1$s</span><i><u style="width:%3$s%%"></u></i><b>%2$s</b></div>',
				esc_attr( $rij['label'] ),
				esc_html( $rij['tekst'] ),
				esc_attr( (string) $breedte )
			);
		}
		echo '</div>';
	}

	private static function leeg( $tekst ) {
		echo '<p class="hdp-g-leeg">' . esc_html( $tekst ) . '</p>';
	}

	private static function blok_open( $titel, $uitleg = '', $id = '' ) {
		echo '<section class="hdp-g-blok"' . ( $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . '><h2>' . esc_html( $titel ) . '</h2>';
		if ( $uitleg ) {
			echo '<p class="hdp-g-uitleg">' . wp_kses_post( $uitleg ) . '</p>';
		}
	}

	private static function blok_dicht() {
		echo '</section>';
	}

	private static function bedrijf_link( $id, $naam ) {
		return $id ? '<a href="' . esc_url( get_edit_user_link( $id ) ) . '">' . esc_html( $naam ) . '</a>' : esc_html( $naam );
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
		?>
		<div class="wrap hdp-gebruik">
			<?php self::stijl(); ?>
			<h1>Portaalgebruik</h1>

			<nav class="nav-tab-wrapper hdp-g-tabs" aria-label="Onderdelen van portaalgebruik">
				<?php foreach ( self::TABS as $sleutel => $naam ) : ?>
					<a href="<?php echo esc_url( self::url( $sleutel, $dagen ) ); ?>" class="nav-tab <?php echo $sleutel === $tab ? 'nav-tab-active' : ''; ?>"<?php echo $sleutel === $tab ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $naam ); ?></a>
				<?php endforeach; ?>
			</nav>

			<div class="hdp-g-balk">
				<div class="hdp-g-periodes" role="group" aria-label="Periode">
					<?php foreach ( HDP_Gebruik::PERIODES as $waarde => $label ) : ?>
						<a href="<?php echo esc_url( self::url( $tab, $waarde ) ); ?>" class="button<?php echo $waarde === $dagen ? ' button-primary' : ''; ?>"<?php echo $waarde === $dagen ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
				</div>
				<a class="button" href="<?php echo esc_url( HDP_Gebruik::export_url( $dagen ) ); ?>"><span class="dashicons dashicons-download" aria-hidden="true"></span> Alle dealers naar Excel</a>
			</div>

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

			<p class="description hdp-g-voet">Collega's tellen mee bij hun bedrijf. Beheerders en testaccounts (aan te vinken in het gebruikersprofiel) tellen niet mee. Alles staat in de eigen database, zonder cookies of externe dienst; gegevens ouder dan <?php echo (int) HDP_Gebruik::STERMIJN_DAGEN; ?> dagen worden automatisch verwijderd.</p>
		</div>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Overzicht
	 * ------------------------------------------------------------------- */

	private static function tab_overzicht( $dagen ) {
		$nu       = HDP_Gebruik::kerncijfers( $dagen );
		$toen     = HDP_Gebruik::kerncijfers( $dagen, true );
		$vergelijk = HDP_Gebruik::vergelijkbaar( $dagen );
		$aandacht = HDP_Gebruik::aandacht();
		$per_week = HDP_Gebruik::actief_per_week( 12 );
		$delen    = HDP_Gebruik::onderdelen( $dagen );
		$apparaat = HDP_Gebruik::apparaten( $dagen );
		$uitrol   = HDP_Gebruik::uitrol();

		$meting = static function ( $sleutel ) use ( $vergelijk, $toen ) {
			return $vergelijk ? $toen[ $sleutel ] : null;
		};
		?>
		<section class="hdp-g-aandacht" aria-labelledby="hdp-g-aandacht-kop">
			<h2 id="hdp-g-aandacht-kop">Vraagt aandacht</h2>
			<?php if ( $aandacht ) : ?>
				<ul>
					<?php foreach ( $aandacht as $punt ) : ?>
						<li class="is-<?php echo esc_attr( $punt['niveau'] ); ?>">
							<span class="dashicons <?php echo 'fout' === $punt['niveau'] ? 'dashicons-warning' : 'dashicons-flag'; ?>" aria-hidden="true"></span>
							<span class="screen-reader-text"><?php echo 'fout' === $punt['niveau'] ? 'Fout:' : 'Let op:'; ?></span>
							<span class="hdp-g-aandacht-tekst"><?php echo esc_html( $punt['tekst'] ); ?></span>
							<a class="button button-small" href="<?php echo esc_url( $punt['link'] ); ?>"><?php echo esc_html( $punt['knop'] ); ?></a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="hdp-g-alles-goed"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> Alles in orde. Geen fouten richting PowerAll, geen verlopen uitnodigingen, geen vergeten winkelwagens en geen zoekopdrachten zonder resultaat deze week.</p>
			<?php endif; ?>
		</section>

		<div class="hdp-g-tegels">
			<?php
			self::tegel( (int) $nu['actieve_bedrijven'] . ' <small>van ' . (int) $nu['bedrijven'] . '</small>', 'Bedrijven actief geweest', self::verschil( $nu['actieve_bedrijven'], $meting( 'actieve_bedrijven' ) ) );
			self::tegel( (string) (int) $nu['bezoeken'], 'Bezoekdagen', self::verschil( $nu['bezoeken'], $meting( 'bezoeken' ) ) );
			self::tegel( (string) (int) $nu['bestellingen'], 'Bestellingen', self::verschil( $nu['bestellingen'], $toen['bestellingen'] ) );
			self::tegel( esc_html( self::geld( $nu['omzet'] ) ), 'Omzet webshop excl. btw', self::verschil( $nu['omzet'], $toen['omzet'], true ) );
			self::tegel( (string) (int) $nu['downloads'], 'Documenten geopend', self::verschil( $nu['downloads'], $meting( 'downloads' ) ) );
			$zoek = $nu['zoekopdrachten'] + $nu['webshopzoek'];
			$niets = $nu['zonder_treffer'] + $nu['webshop_niets'];
			self::tegel( (string) (int) $zoek, 'Zoekopdrachten', '<span class="hdp-g-delta">' . ( $niets ? (int) $niets . ' zonder resultaat' : 'alles leverde iets op' ) . '</span>' );
			?>
		</div>

		<div class="hdp-g-raster">
			<?php
			self::blok_open( 'Actieve bedrijven per week', 'Hoeveel verschillende bedrijven per week minstens één keer in het portaal waren. Altijd de laatste 12 weken.' );
			self::staven(
				array_map(
					static function ( $week ) {
						return array( 'label' => $week['week'], 'waarde' => $week['aantal'], 'tekst' => (string) $week['aantal'] );
					},
					$per_week
				),
				70
			);
			self::blok_dicht();

			self::blok_open( 'Welke onderdelen worden gebruikt', 'Hoeveel bedrijven elk onderdeel in deze periode openden.' );
			if ( array_sum( wp_list_pluck( $delen, 'dagen' ) ) ) {
				self::staven(
					array_map(
						static function ( $deel ) {
							return array( 'label' => $deel['naam'], 'waarde' => $deel['bedrijven'], 'tekst' => $deel['bedrijven'] . ( 1 === $deel['bedrijven'] ? ' bedrijf' : ' bedrijven' ) );
						},
						array_values( $delen )
					),
					140
				);
			} else {
				self::leeg( 'Nog niets gemeten in deze periode. Dit wordt bijgehouden vanaf versie 1.110.0.' );
			}
			self::blok_dicht();

			self::blok_open( 'Apparaat en taal', 'Waarop en in welke taal dealers het portaal gebruiken, in bezoekdagen.' );
			$app_totaal  = array_sum( $apparaat['apparaat'] );
			$taal_totaal = array_sum( $apparaat['taal'] );
			if ( $app_totaal ) {
				$procent = static function ( $deel, $totaal ) {
					return $totaal ? round( $deel / $totaal * 100 ) : 0;
				};
				self::staven(
					array(
						array( 'label' => 'Computer', 'waarde' => $apparaat['apparaat']['computer'], 'tekst' => $procent( $apparaat['apparaat']['computer'], $app_totaal ) . '%' ),
						array( 'label' => 'Telefoon of tablet', 'waarde' => $apparaat['apparaat']['mobiel'], 'tekst' => $procent( $apparaat['apparaat']['mobiel'], $app_totaal ) . '%' ),
					),
					140
				);
				echo '<div class="hdp-g-tussen"></div>';
				self::staven(
					array(
						array( 'label' => 'Nederlands', 'waarde' => $apparaat['taal']['nl'], 'tekst' => $procent( $apparaat['taal']['nl'], $taal_totaal ) . '%' ),
						array( 'label' => 'Frans', 'waarde' => $apparaat['taal']['fr'], 'tekst' => $procent( $apparaat['taal']['fr'], $taal_totaal ) . '%' ),
					),
					140
				);
			} else {
				self::leeg( 'Nog niets gemeten in deze periode. Dit wordt bijgehouden vanaf versie 1.110.0.' );
			}
			self::blok_dicht();

			if ( $uitrol['totaal'] ) {
				self::blok_open( 'Uitnodigingen (dealers uit PowerAll)', 'Hoe ver de dealers uit PowerAll zijn met hun account. Telt altijd alles, los van de periode. <a href="' . esc_url( self::url( 'dealers', $dagen ) ) . '#nalopen">Wie moet er nog?</a>' );
				?>
				<div class="hdp-g-trechter">
					<div><strong><?php echo (int) $uitrol['totaal']; ?></strong><span>gekoppeld</span></div>
					<div><strong><?php echo (int) ( $uitrol['totaal'] - $uitrol['niet'] ); ?></strong><span>uitgenodigd</span></div>
					<div><strong><?php echo (int) $uitrol['gekozen']; ?></strong><span>wachtwoord gekozen</span></div>
					<div><strong><?php echo (int) $uitrol['geweest']; ?></strong><span>al eens in het portaal</span></div>
				</div>
				<?php
				self::blok_dicht();
			}
			?>
		</div>
		<?php
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

		$tellingen = array_fill_keys( array_keys( self::ACTIVITEITEN ), 0 );
		foreach ( $lijst as $rij ) {
			++$tellingen[ $rij['activiteit']['code'] ];
		}
		?>
		<section class="hdp-g-blok hdp-g-dealers">
			<h2>Alle bedrijven</h2>
			<p class="hdp-g-uitleg">Eén regel per bedrijf; collega's tellen mee. <strong>Actief</strong> = <?php echo (int) HDP_Gebruik::ACTIEF_DAGEN; ?> of meer bezoekdagen in de laatste 30 dagen, <strong>af en toe</strong> = minder vaak, <strong>stil</strong> = langer dan 30 dagen niet geweest. De cijfers gaan over de gekozen periode. Klik op een kolomkop om te sorteren.</p>

			<div class="hdp-g-filters">
				<label class="screen-reader-text" for="hdp-g-zoek">Zoek een bedrijf</label>
				<input type="search" id="hdp-g-zoek" placeholder="Zoek op bedrijf, naam, e-mail of klantnummer" autocomplete="off">
				<div class="hdp-g-keuzes" role="group" aria-label="Filter op activiteit">
					<button type="button" class="button<?php echo '' === $filter ? ' is-gekozen' : ''; ?>" data-activiteit="" aria-pressed="<?php echo '' === $filter ? 'true' : 'false'; ?>">Alle <span class="hdp-g-aantal"><?php echo count( $lijst ); ?></span></button>
					<?php foreach ( self::ACTIVITEITEN as $code => $naam ) : ?>
						<button type="button" class="button<?php echo $code === $filter ? ' is-gekozen' : ''; ?>" data-activiteit="<?php echo esc_attr( $code ); ?>" aria-pressed="<?php echo $code === $filter ? 'true' : 'false'; ?>"><span class="hdp-g-label is-<?php echo esc_attr( $code ); ?>"><?php echo esc_html( $naam ); ?></span> <span class="hdp-g-aantal"><?php echo (int) $tellingen[ $code ]; ?></span></button>
					<?php endforeach; ?>
				</div>
			</div>

			<?php if ( $lijst ) : ?>
				<div class="hdp-g-scroll">
					<table class="widefat striped hdp-g-sorteer" id="hdp-g-dealertabel">
						<thead>
							<tr>
								<th scope="col" aria-sort="ascending"><button type="button" data-kolom="0" data-soort="tekst">Bedrijf</button></th>
								<th scope="col"><button type="button" data-kolom="1" data-soort="getal">Activiteit</button></th>
								<th scope="col" class="num"><button type="button" data-kolom="2" data-soort="getal">Laatst geweest</button></th>
								<th scope="col" class="num"><button type="button" data-kolom="3" data-soort="getal">Bezoekdagen</button></th>
								<th scope="col" class="num"><button type="button" data-kolom="4" data-soort="getal">Documenten</button></th>
								<th scope="col" class="num"><button type="button" data-kolom="5" data-soort="getal">Zoekopdr.</button></th>
								<th scope="col" class="num"><button type="button" data-kolom="6" data-soort="getal">Bestellingen</button></th>
								<th scope="col" class="num"><button type="button" data-kolom="7" data-soort="getal">Omzet</button></th>
								<th scope="col" class="num"><button type="button" data-kolom="8" data-soort="getal">Trainingen</button></th>
							</tr>
						</thead>
						<tbody>
						<?php
						$volgorde = array( 'actief' => 4, 'af-en-toe' => 3, 'stil' => 2, 'nooit' => 1 );
						foreach ( $lijst as $rij ) :
							$zoek = mb_strtolower( implode( ' ', array( $rij['bedrijf'], $rij['naam'], $rij['email'], $rij['klantnummer'] ) ) );
							?>
							<tr data-zoek="<?php echo esc_attr( $zoek ); ?>" data-activiteit="<?php echo esc_attr( $rij['activiteit']['code'] ); ?>">
								<td data-waarde="<?php echo esc_attr( mb_strtolower( $rij['bedrijf'] ) ); ?>">
									<?php echo self::bedrijf_link( $rij['id'], $rij['bedrijf'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- geëscaped in bedrijf_link. ?>
									<br><small><?php echo esc_html( $rij['naam'] . ( $rij['klantnummer'] ? ' · ' . $rij['klantnummer'] : '' ) . ( $rij['collegas'] ? ' · ' . $rij['collegas'] . ( 1 === $rij['collegas'] ? ' collega' : " collega's" ) : '' ) ); ?></small>
								</td>
								<td data-waarde="<?php echo (int) $volgorde[ $rij['activiteit']['code'] ]; ?>"><span class="hdp-g-label is-<?php echo esc_attr( $rij['activiteit']['code'] ); ?>"><?php echo esc_html( $rij['activiteit']['tekst'] ); ?></span></td>
								<td class="num" data-waarde="<?php echo (int) $rij['laatst']; ?>"><?php echo esc_html( $rij['laatst'] ? self::datum( $rij['laatst'] ) : 'Nog nooit' ); ?></td>
								<td class="num" data-waarde="<?php echo (int) $rij['bezoekdagen']; ?>"><?php echo (int) $rij['bezoekdagen']; ?></td>
								<td class="num" data-waarde="<?php echo (int) $rij['downloads']; ?>"><?php echo (int) $rij['downloads']; ?></td>
								<td class="num" data-waarde="<?php echo (int) $rij['zoeken']; ?>"><?php echo (int) $rij['zoeken']; ?></td>
								<td class="num" data-waarde="<?php echo (int) $rij['bestellingen']; ?>"><?php echo (int) $rij['bestellingen']; ?></td>
								<td class="num" data-waarde="<?php echo esc_attr( (string) round( $rij['omzet'], 2 ) ); ?>"><?php echo $rij['omzet'] ? esc_html( self::geld( $rij['omzet'] ) ) : '—'; ?></td>
								<td class="num" data-waarde="<?php echo (int) $rij['trainingen']; ?>"><?php echo (int) $rij['trainingen']; ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<p class="hdp-g-leeg" id="hdp-g-geen-treffer" hidden>Geen bedrijf gevonden.</p>
			<?php else : ?>
				<?php self::leeg( 'Er zijn nog geen goedgekeurde dealers.' ); ?>
			<?php endif; ?>
		</section>

		<?php if ( $uitrol['nalopen'] ) : ?>
			<section class="hdp-g-blok" id="nalopen">
				<h2>Uitgenodigd, nog geen wachtwoord gekozen</h2>
				<p class="hdp-g-uitleg">Een verlopen uitnodiging stuur je opnieuw via <a href="<?php echo esc_url( admin_url( 'users.php?page=' . HDP_PowerAll_Dealers::PAGINA ) ); ?>">Gebruikers &gt; Dealers uit PowerAll</a>.</p>
				<div class="hdp-g-scroll">
					<table class="widefat striped">
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
		self::script_dealers();
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

		if ( $pa_fouten ) :
			?>
			<div class="notice notice-error inline">
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
			<?php
		endif;
		?>
		<div class="hdp-g-tegels">
			<?php
			self::tegel( (string) (int) $nu['bestellingen'], 'Bestellingen', self::verschil( $nu['bestellingen'], $toen['bestellingen'] ) );
			self::tegel( esc_html( self::geld( $nu['omzet'] ) ), 'Omzet excl. btw', self::verschil( $nu['omzet'], $toen['omzet'], true ) );
			self::tegel( esc_html( self::geld( $gemiddeld ) ), 'Gemiddelde bestelling', '<span class="hdp-g-delta">excl. btw, met verzending en toeslag</span>' );
			self::tegel( (string) (int) $cijfers['toeslag']['keer'] . ' <small>keer</small>', 'Kleinordertoeslag', '<span class="hdp-g-delta">samen ' . esc_html( self::geld( $cijfers['toeslag']['bedrag'] ) ) . '</span>' );
			?>
		</div>

		<div class="hdp-g-raster">
			<?php
			self::blok_open( 'Omzet per merk', 'Artikelen excl. btw, zonder verzending en toeslag. Het merk is de hoofdcategorie in de webshop.' );
			if ( $cijfers['merken'] ) {
				$rijen = array();
				foreach ( $cijfers['merken'] as $merk => $bedrag ) {
					$rijen[] = array( 'label' => $merk, 'waarde' => $bedrag, 'tekst' => self::geld( $bedrag ) );
				}
				self::staven( $rijen, 130 );
			} else {
				self::leeg( 'In deze periode geen bestellingen.' );
			}
			self::blok_dicht();

			self::blok_open( 'Meest bestelde artikelen', 'Op aantal stuks.' );
			if ( $cijfers['artikelen'] ) :
				?>
				<div class="hdp-g-scroll"><table class="widefat striped">
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
				self::leeg( 'In deze periode geen bestellingen.' );
			endif;
			self::blok_dicht();

			self::blok_open( 'Per bedrijf', 'Hoogste omzet bovenaan; bestellingen van collega\'s tellen mee bij hun bedrijf.' );
			if ( $bedrijven ) :
				?>
				<div class="hdp-g-scroll"><table class="widefat striped">
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
				self::leeg( 'In deze periode geen bestellingen.' );
			endif;
			self::blok_dicht();

			self::blok_open( 'Winkelwagens die nog niet besteld zijn', 'Nu, los van de periode. <strong>Vergeten</strong> = de dealer is al ' . (int) HDP_Gebruik::VERGETEN_DAGEN . ' dagen of langer niet geweest; misschien een belletje waard.', 'winkelwagens' );
			if ( $wagens ) :
				?>
				<div class="hdp-g-scroll"><table class="widefat striped">
					<thead><tr><th>Bedrijf</th><th class="num">Artikelen</th><th class="num">Waarde</th><th class="num">Laatst geweest</th></tr></thead>
					<tbody>
					<?php foreach ( $wagens as $wagen ) : ?>
						<tr>
							<td><?php echo self::bedrijf_link( $wagen['bedrijf_id'], $wagen['bedrijf'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><?php echo $wagen['bedrijf_id'] !== $wagen['id'] ? '<br><small>' . esc_html( $wagen['naam'] ) . '</small>' : ''; ?></td>
							<td class="num"><?php echo (int) $wagen['stuks']; ?></td>
							<td class="num"><?php echo esc_html( self::geld( $wagen['waarde'] ) ); ?></td>
							<td class="num"><?php echo esc_html( self::datum( $wagen['laatst'] ) ); ?><?php echo $wagen['vergeten'] ? ' <span class="hdp-g-label is-stil">Vergeten</span>' : ''; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table></div>
				<?php
			else :
				self::leeg( 'Geen open winkelwagens.' );
			endif;
			self::blok_dicht();

			self::blok_open( 'Gezocht in de webshop, niets gevonden', 'Het artikel ontbreekt, heet anders, of hoort bij een merk dat deze dealers niet voeren.' );
			self::termen( HDP_Gebruik::zoektermen( $dagen, true, 20, 'webshopzoek' ) );
			self::blok_dicht();

			self::blok_open( 'Meest gezocht in de webshop' );
			self::termen( HDP_Gebruik::zoektermen( $dagen, false, 20, 'webshopzoek' ) );
			self::blok_dicht();
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
			self::blok_open( 'Gezocht, niets gevonden', 'In de zoekbalk bovenin. Vaak een artikel of document dat ontbreekt, of een andere schrijfwijze.' );
			self::termen( HDP_Gebruik::zoektermen( $dagen, true ) );
			self::blok_dicht();

			self::blok_open( 'Meest gezocht', 'Alle zoekopdrachten in de zoekbalk bovenin, vaakst eerst. Zoeken in de webshop staat onder het tabblad Webshop.' );
			self::termen( HDP_Gebruik::zoektermen( $dagen ) );
			self::blok_dicht();

			self::blok_open( 'Meest geopende documenten' );
			if ( $downloads ) :
				?>
				<div class="hdp-g-scroll"><table class="widefat striped">
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
				self::leeg( 'In deze periode nog niets geopend.' );
			endif;
			self::blok_dicht();

			self::blok_open( 'Door niemand geopend', 'Documenten die in deze periode door geen enkele dealer zijn geopend. Verouderd, verkeerd genoemd, of niet te vinden?' );
			if ( $ongebruikt ) :
				?>
				<div class="hdp-g-scroll"><table class="widefat striped">
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
					<p class="hdp-g-uitleg">En nog <?php echo (int) ( count( $ongebruikt ) - 25 ); ?> andere.</p>
				<?php endif; ?>
				<?php
			else :
				self::leeg( 'Elk document is in deze periode minstens één keer geopend.' );
			endif;
			self::blok_dicht();
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
			<div class="notice notice-error inline"><p><strong><?php echo esc_html( $fouten . ( 1 === $fouten ? ' aanmelding is' : ' aanmeldingen zijn' ) ); ?> niet als order in PowerAll aangekomen.</strong> <a href="<?php echo esc_url( HDP_Trainingen::beheer_url() ); ?>">Naar de aanmeldingen</a> en kies daar "Opnieuw versturen".</p></div>
			<?php
		endif;
		?>
		<div class="hdp-g-tegels">
			<?php
			self::tegel( (string) (int) array_sum( wp_list_pluck( $trainingen, 'aanmeldingen' ) ), 'Aanmeldingen' );
			$cijfers = array_sum( wp_list_pluck( $trainingen, 'cijfers' ) );
			$som     = 0.0;
			foreach ( $trainingen as $t ) {
				$som += $t['gemiddeld'] * $t['cijfers'];
			}
			self::tegel( $cijfers ? esc_html( number_format_i18n( $som / $cijfers, 1 ) ) : '—', 'Gemiddeld cijfer', '<span class="hdp-g-delta">' . (int) $cijfers . ' beoordelingen</span>' );
			?>
		</div>
		<?php
		self::blok_open( 'Per training', 'Aanmeldingen en beoordelingen in deze periode (cijfer van 1 tot 10).' );
		if ( $trainingen ) :
			?>
			<div class="hdp-g-scroll"><table class="widefat striped">
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
			self::leeg( 'In deze periode geen aanmeldingen of beoordelingen.' );
		endif;
		self::blok_dicht();
	}

	/* ---------------------------------------------------------------------
	 * Losse onderdelen
	 * ------------------------------------------------------------------- */

	private static function termen( $termen ) {
		if ( ! $termen ) {
			self::leeg( 'In deze periode nog niets.' );
			return;
		}
		?>
		<div class="hdp-g-scroll"><table class="widefat striped">
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
		<table class="form-table" role="presentation">
			<tr><th>Laatst geweest</th><td><?php echo esc_html( $c['laatst'] ? wp_date( 'j F Y', $c['laatst'] ) : 'Nog nooit (sinds de meting)' ); ?> · <span class="hdp-g-label is-<?php echo esc_attr( $c['activiteit']['code'] ); ?>"><?php echo esc_html( $c['activiteit']['tekst'] ); ?></span><?php echo wp_kses_post( $test ); ?></td></tr>
			<tr><th>Laatste 3 maanden</th><td><?php echo esc_html( sprintf( '%d bezoekdagen · %d documenten geopend · %d zoekopdrachten · %d trainingaanmeldingen', $c['bezoekdagen'], $c['downloads'], $c['zoeken'], $c['trainingen'] ) ); ?><?php echo $c['collegas'] ? esc_html( sprintf( ' (met %d %s)', $c['collegas'], 1 === $c['collegas'] ? 'collega' : "collega's" ) ) : ''; ?></td></tr>
			<tr><th>Webshop, laatste 12 maanden</th><td><?php echo esc_html( sprintf( '%d bestellingen, %s excl. btw', $j['bestellingen'], self::geld( $j['omzet'] ) ) ); ?></td></tr>
			<?php if ( $c['termen'] ) : ?>
				<tr><th>Zocht naar</th><td><?php echo esc_html( implode( ', ', $c['termen'] ) ); ?></td></tr>
			<?php endif; ?>
		</table>
		<style>
			.hdp-g-label { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 12px; font-weight: 600; }
			.hdp-g-label.is-actief { background: #edfaef; color: #00450c; }
			.hdp-g-label.is-af-en-toe { background: #fcf9e8; color: #4d3a00; }
			.hdp-g-label.is-stil { background: #fcf0f1; color: #8a2424; }
			.hdp-g-label.is-nooit { background: #f0f0f1; color: #3c434a; }
		</style>
		<?php
	}

	/* ---------------------------------------------------------------------
	 * Opmaak en script
	 * ------------------------------------------------------------------- */

	private static function stijl() {
		?>
		<style>
			.hdp-gebruik { --hdp-rood: #c41214; --hdp-rand: #dcdcde; --hdp-zacht: #50575e; }
			.hdp-gebruik .hdp-g-tabs { margin-bottom: 0; }
			.hdp-gebruik .hdp-g-balk { display: flex; flex-wrap: wrap; gap: 8px 16px; align-items: center; justify-content: space-between; margin: 16px 0; }
			.hdp-gebruik .hdp-g-periodes { display: flex; flex-wrap: wrap; gap: 6px; }
			.hdp-gebruik .hdp-g-balk .dashicons { vertical-align: text-bottom; font-size: 17px; }

			.hdp-gebruik .hdp-g-aandacht { background: #fff; border: 1px solid var(--hdp-rand); border-left: 4px solid var(--hdp-rood); border-radius: 6px; padding: 4px 16px 12px; margin-bottom: 20px; }
			.hdp-gebruik .hdp-g-aandacht h2 { font-size: 15px; margin: 12px 0 8px; }
			.hdp-gebruik .hdp-g-aandacht ul { margin: 0; }
			.hdp-gebruik .hdp-g-aandacht li { display: flex; gap: 10px; align-items: center; padding: 8px 0; margin: 0; border-top: 1px solid #f0f0f1; }
			.hdp-gebruik .hdp-g-aandacht li:first-child { border-top: 0; }
			.hdp-gebruik .hdp-g-aandacht-tekst { flex: 1; }
			.hdp-gebruik .hdp-g-aandacht li.is-fout .dashicons { color: #d63638; }
			.hdp-gebruik .hdp-g-aandacht li.is-let-op .dashicons { color: #b26200; }
			.hdp-gebruik .hdp-g-alles-goed { margin: 0 0 6px; color: #1d2327; }
			.hdp-gebruik .hdp-g-alles-goed .dashicons { color: #008a20; }
			.hdp-gebruik .hdp-g-aandacht:has(.hdp-g-alles-goed) { border-left-color: #008a20; }

			.hdp-gebruik .hdp-g-tegels { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; margin: 0 0 20px; }
			.hdp-gebruik .hdp-g-tegel { background: #fff; border: 1px solid var(--hdp-rand); border-radius: 6px; padding: 14px 16px; display: flex; flex-direction: column; gap: 4px; }
			.hdp-gebruik .hdp-g-tegel-label { color: var(--hdp-zacht); font-size: 13px; }
			.hdp-gebruik .hdp-g-tegel strong { font-size: 26px; line-height: 1.15; color: #1d2327; font-variant-numeric: tabular-nums; }
			.hdp-gebruik .hdp-g-tegel strong small { font-size: 14px; font-weight: 400; color: var(--hdp-zacht); }
			.hdp-gebruik .hdp-g-delta { color: var(--hdp-zacht); font-size: 12px; }
			.hdp-gebruik .hdp-g-delta b { color: #1d2327; }

			.hdp-gebruik .hdp-g-raster { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(440px, 100%), 1fr)); gap: 20px; align-items: start; }
			.hdp-gebruik .hdp-g-blok { background: #fff; border: 1px solid var(--hdp-rand); border-radius: 6px; padding: 4px 16px 16px; margin-bottom: 20px; min-width: 0; }
			.hdp-gebruik .hdp-g-raster .hdp-g-blok { margin-bottom: 0; }
			.hdp-gebruik .hdp-g-blok h2 { font-size: 15px; margin: 14px 0 4px; }
			.hdp-gebruik .hdp-g-uitleg { margin: 0 0 12px; color: var(--hdp-zacht); }
			.hdp-gebruik .hdp-g-blok table { border: 0; box-shadow: none; }
			.hdp-gebruik .hdp-g-scroll { overflow-x: auto; }
			.hdp-gebruik td.num, .hdp-gebruik th.num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
			.hdp-gebruik .hdp-g-leeg { color: var(--hdp-zacht); font-style: italic; }
			.hdp-gebruik .hdp-g-voet { margin-top: 24px; }

			.hdp-gebruik .hdp-g-staven { display: grid; gap: 6px; }
			.hdp-gebruik .hdp-g-staaf { display: grid; grid-template-columns: var(--label) 1fr minmax(60px, auto); align-items: center; gap: 10px; font-size: 13px; padding: 2px 0; }
			.hdp-gebruik .hdp-g-staaf span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
			.hdp-gebruik .hdp-g-staaf i { display: block; height: 12px; }
			.hdp-gebruik .hdp-g-staaf u { display: block; height: 100%; background: var(--hdp-rood); border-radius: 0 4px 4px 0; text-decoration: none; }
			.hdp-gebruik .hdp-g-staaf b { text-align: right; font-weight: 600; color: #1d2327; font-variant-numeric: tabular-nums; white-space: nowrap; }
			.hdp-gebruik .hdp-g-staaf:hover { background: #f6f7f7; }
			.hdp-gebruik .hdp-g-tussen { height: 14px; }

			.hdp-gebruik .hdp-g-trechter { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 8px; }
			.hdp-gebruik .hdp-g-trechter div { background: #f6f7f7; border-radius: 4px; padding: 8px 10px; }
			.hdp-gebruik .hdp-g-trechter strong { display: block; font-size: 20px; }
			.hdp-gebruik .hdp-g-trechter span { font-size: 12px; color: var(--hdp-zacht); }

			.hdp-gebruik .hdp-g-label { display: inline-block; padding: 1px 8px; border-radius: 10px; font-size: 12px; font-weight: 600; white-space: nowrap; }
			.hdp-gebruik .hdp-g-label.is-actief { background: #edfaef; color: #00450c; }
			.hdp-gebruik .hdp-g-label.is-af-en-toe { background: #fcf9e8; color: #4d3a00; }
			.hdp-gebruik .hdp-g-label.is-stil { background: #fcf0f1; color: #8a2424; }
			.hdp-gebruik .hdp-g-label.is-nooit { background: #f0f0f1; color: #3c434a; }

			.hdp-gebruik .hdp-g-filters { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 12px; }
			.hdp-gebruik #hdp-g-zoek { min-width: min(320px, 100%); }
			.hdp-gebruik .hdp-g-keuzes { display: flex; flex-wrap: wrap; gap: 6px; }
			.hdp-gebruik .hdp-g-keuzes .button.is-gekozen { border-color: #1d2327; box-shadow: inset 0 0 0 1px #1d2327; }
			.hdp-gebruik .hdp-g-aantal { color: var(--hdp-zacht); }
			.hdp-gebruik .hdp-g-sorteer th button { background: none; border: 0; padding: 0; font: inherit; font-weight: 600; color: inherit; cursor: pointer; }
			.hdp-gebruik .hdp-g-sorteer th[aria-sort="ascending"] button::after { content: " ▲"; font-size: 10px; }
			.hdp-gebruik .hdp-g-sorteer th[aria-sort="descending"] button::after { content: " ▼"; font-size: 10px; }
			.hdp-gebruik .hdp-g-sorteer th button:focus-visible { outline: 2px solid #2271b1; outline-offset: 2px; }
			.hdp-gebruik .hdp-g-dealers td { vertical-align: middle; }
			.hdp-gebruik .hdp-g-sorteer td:first-child { min-width: 180px; }

			@media (max-width: 600px) {
				.hdp-gebruik .hdp-g-staaf { grid-template-columns: minmax(70px, 38%) 1fr auto; }
				.hdp-gebruik .hdp-g-aandacht li { flex-wrap: wrap; }
			}
		</style>
		<?php
	}

	private static function script_dealers() {
		?>
		<script>
		( function () {
			var tabel = document.getElementById( 'hdp-g-dealertabel' );
			if ( ! tabel ) { return; }
			var tbody = tabel.tBodies[0];
			var zoek = document.getElementById( 'hdp-g-zoek' );
			var knoppen = document.querySelectorAll( '.hdp-g-keuzes button' );
			var geen = document.getElementById( 'hdp-g-geen-treffer' );
			var activiteit = '';
			knoppen.forEach( function ( k ) { if ( k.getAttribute( 'aria-pressed' ) === 'true' ) { activiteit = k.dataset.activiteit; } } );

			function filter() {
				var term = zoek.value.trim().toLowerCase();
				var zichtbaar = 0;
				Array.prototype.forEach.call( tbody.rows, function ( rij ) {
					var toon = ( ! term || rij.dataset.zoek.indexOf( term ) !== -1 ) && ( ! activiteit || rij.dataset.activiteit === activiteit );
					rij.hidden = ! toon;
					if ( toon ) { zichtbaar++; }
				} );
				geen.hidden = zichtbaar > 0;
			}
			zoek.addEventListener( 'input', filter );
			knoppen.forEach( function ( knop ) {
				knop.addEventListener( 'click', function () {
					activiteit = knop.dataset.activiteit;
					knoppen.forEach( function ( k ) { k.classList.toggle( 'is-gekozen', k === knop ); k.setAttribute( 'aria-pressed', k === knop ? 'true' : 'false' ); } );
					filter();
				} );
			} );

			tabel.querySelectorAll( 'th button' ).forEach( function ( knop ) {
				knop.addEventListener( 'click', function () {
					var th = knop.parentNode;
					var kolom = parseInt( knop.dataset.kolom, 10 );
					var tekst = knop.dataset.soort === 'tekst';
					// Eerste klik: tekst A-Z, getallen hoog-laag.
					var oplopend = th.getAttribute( 'aria-sort' ) ? th.getAttribute( 'aria-sort' ) !== 'ascending' : tekst;
					tabel.querySelectorAll( 'th' ).forEach( function ( t ) { t.removeAttribute( 'aria-sort' ); } );
					th.setAttribute( 'aria-sort', oplopend ? 'ascending' : 'descending' );
					var rijen = Array.prototype.slice.call( tbody.rows );
					rijen.sort( function ( a, b ) {
						var x = a.cells[ kolom ].dataset.waarde, y = b.cells[ kolom ].dataset.waarde;
						var v = tekst ? x.localeCompare( y, 'nl' ) : parseFloat( x ) - parseFloat( y );
						return oplopend ? v : -v;
					} );
					rijen.forEach( function ( r ) { tbody.appendChild( r ); } );
				} );
			} );
			filter();
		} )();
		</script>
		<?php
	}
}
