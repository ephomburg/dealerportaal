<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rendert de garantiepagina: zelfde opzet en opmaak als de downloads- en
 * contentpagina (zelfde welkomstkop, zelfde "terug naar portaal", zelfde
 * kaarten), zodat het garantieportaal aanvoelt als een onderdeel van het
 * dealerportaal en niet als een losse website.
 *
 * De gegevens komen uit de claimadministratie in de Supabase van de Homburg
 * App (zie HDP_Garantie). Die kan onbereikbaar zijn; daarom haalt deze
 * klasse de lijsten één keer op, kijkt of er een fout terugkwam, en toont
 * dan een storingsmelding in plaats van lege lijsten. De rest van het
 * portaal — webshop, downloads — staat daar los van en werkt gewoon door.
 */
class HDP_Garantie_Render {

	public static function render_garantie_pagina( $a ) {
		if ( ! is_user_logged_in() || ! HDP_Garantie::mag_namens() ) {
			return self::render_garantie_inhoud( $a );
		}

		// Een Homburg-medewerker: eerst de balk "Je werkt namens …". Zonder
		// gekozen dealer alleen die balk, zodat er niets op zijn eigen naam
		// terechtkomt.
		$balk = self::render_namens_balk();
		if ( ! HDP_Garantie::namens_dealer() ) {
			return '<div class="hdp-portaal alignfull"><section class="hdp-garantie">' . $balk . '</section></div>';
		}

		$html = self::render_garantie_inhoud( $a );
		// Binnen de garantiesectie, zodat de balk even breed is als de inhoud.
		$plek = strpos( $html, '<section class="hdp-garantie' );
		$plek = false === $plek ? false : strpos( $html, '>', $plek );
		return false === $plek ? $balk . $html : substr_replace( $html, $balk, $plek + 1, 0 );
	}

	/** De keuzelijst voor medewerkers: namens welke dealer ze werken. */
	private static function render_namens_balk() {
		$gekozen = HDP_Garantie::namens_dealer();
		ob_start();
		?>
		<form class="hdp-g-namens" method="get" action="<?php echo esc_url( home_url( '/garantie/' ) ); ?>">
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( HDP_Garantie_Formulier::NAMENS_NONCE ) ); ?>">
			<label for="hdp-g-namens-keuze"><?php echo esc_html( HDP_I18N::t( 'garantie_namens_label' ) ); ?></label>
			<select id="hdp-g-namens-keuze" name="hdp_namens" onchange="this.form.submit()">
				<option value="0"><?php echo esc_html( HDP_I18N::t( 'garantie_namens_kies' ) ); ?></option>
				<?php foreach ( HDP_Garantie::kiesbare_dealers() as $id => $naam ) : ?>
					<option value="<?php echo (int) $id; ?>" <?php selected( $gekozen, $id ); ?>><?php echo esc_html( $naam ); ?></option>
				<?php endforeach; ?>
			</select>
			<noscript><button type="submit" class="hdp-btn hdp-btn-klein"><?php echo esc_html( HDP_I18N::t( 'garantie_namens_knop' ) ); ?></button></noscript>
			<p><?php echo esc_html( HDP_I18N::t( $gekozen ? 'garantie_namens_uitleg' : 'garantie_namens_kies_eerst' ) ); ?></p>
		</form>
		<?php
		return ob_get_clean();
	}

	private static function render_garantie_inhoud( $a ) {
		$mag_zien = is_user_logged_in() && HDP_Roles::mag_portaal_zien( get_current_user_id() );

		if ( ! $mag_zien ) {
			ob_start();
			HDP_Login::render_login( HDP_Login::login_foutmelding(), $a );
			return ob_get_clean();
		}

		// Een collega zonder het recht "garantie": uitleg in plaats van de pagina.
		if ( class_exists( 'HDP_Collegas' ) && ! HDP_Collegas::mag( get_current_user_id(), 'garantie' ) ) {
			return '<div class="hdp-portaal alignfull"><section class="hdp-collega-geen-toegang"><p>'
				. esc_html( sprintf( HDP_I18N::t( 'collega_geen_garantie' ), HDP_Collegas::bedrijfsnaam( HDP_Collegas::hoofd_id() ) ) )
				. '</p></section></div>';
		}

		// Een ticket opent normaal rechts in het overzicht, zodat de lijst
		// ernaast blijft staan. Het volledige scherm — met de route, de
		// machinekaart en de bijlagen, die breedte nodig hebben — is een
		// bewuste stap verder.
		//
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- alleen een keuze uit de URL om te bepalen wát getoond wordt; de toegangscontrole zit in HDP_Garantie::claim_voor_dealer() en in de rolcontrole hierboven.
		$gevraagd = isset( $_GET['ticket'] ) ? sanitize_text_field( wp_unslash( $_GET['ticket'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- zie hierboven.
		if ( '' !== $gevraagd ) {
			return self::render_ticket( $gevraagd );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- zie hierboven.
		$nieuw = isset( $_GET['nieuw'] ) ? sanitize_key( wp_unslash( $_GET['nieuw'] ) ) : '';
		if ( 'machine' === $nieuw ) {
			return self::render_formulier( 'machine' );
		}
		if ( 'claim' === $nieuw ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- alleen lezen.
			$gekozen = isset( $_GET['soort'] ) ? sanitize_key( wp_unslash( $_GET['soort'] ) ) : '';
			if ( ! in_array( $gekozen, HDP_Garantie::CLAIMSOORTEN, true ) ) {
				return self::render_soortkeuze();
			}
			return self::render_formulier( 'claim', $gekozen );
		}

		$titel        = HDP_I18N::kies( $a['titel'], $a['titelFr'] );
		$omschrijving = HDP_I18N::kies( $a['omschrijving'], $a['omschrijvingFr'] );

		ob_start();
		?>
		<div class="hdp-portaal alignfull">
			<?php
			HDP_Paginakop::render(
				array_merge(
					array(
						'titel' => $titel,
						'intro' => $omschrijving,
					),
					HDP_Paginakop::terug_naar_portaal()
				)
			);
			?>
			<section class="hdp-garantie">
				<?php
				// Eén keer ophalen en doorgeven: anders doet elke sectie zijn
				// eigen ronde naar de database.
				$claims   = HDP_Garantie::lopende_claims();
				$machines = HDP_Garantie::machines();
				$storing  = is_wp_error( $claims ) || is_wp_error( $machines );
				?>
				<?php self::render_meldingen(); ?>
				<?php if ( $storing ) : ?>
					<?php self::render_storing(); ?>
					<?php self::render_ingangen(); ?>
				<?php else : ?>
					<?php self::render_werkblad(); ?>
					<?php self::render_zoekscript(); ?>
				<?php endif; ?>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Hoe de claims gegroepeerd worden in de zijkolom.
	 *
	 * Niet per losse status maar per betekenis — dezelfde indeling als de
	 * statuskleuren gebruiken. Een dealer wil weten "moet ik iets doen",
	 * niet of iets nu bij ons of bij de fabrikant ligt; dat staat in het
	 * label van de claim zelf.
	 */
	private static function groepen() {
		return array(
			'wacht' => array( HDP_Garantie::STATUS_ACTIE_DEALER ),
			'loopt' => array_values(
				array_diff(
					HDP_Garantie::STATUSSEN,
					array_merge( array( HDP_Garantie::STATUS_ACTIE_DEALER ), HDP_Garantie::EIND_STATUSSEN )
				)
			),
			'klaar' => HDP_Garantie::EIND_STATUSSEN,
		);
	}

	/** Welke groep nu actief is, of 'machines' voor de machinelijst. */
	private static function gekozen_groep() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- alleen lezen.
		$toon = isset( $_GET['toon'] ) ? sanitize_key( wp_unslash( $_GET['toon'] ) ) : '';

		return in_array( $toon, array_merge( array( 'alles', 'machines' ), array_keys( self::groepen() ) ), true )
			? $toon
			: 'alles';
	}

	/** Het ticket dat rechts openstaat, als er een gekozen is. */
	private static function gekozen_ticket() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- alleen lezen.
		return isset( $_GET['ticket'] ) ? sanitize_text_field( wp_unslash( $_GET['ticket'] ) ) : '';
	}

	/**
	 * Het overzicht (optie C, 2026-11-13): eerst wat op de dealer wacht,
	 * daaronder twee kaarten — Mijn claims en Mijn machines.
	 *
	 * Hiervoor stonden er vier kolommen naast elkaar: het vaste portaalmenu,
	 * een eigen filtermenu, de lijst en het geopende ticket. Dat was te veel
	 * tegelijk. De knoppen staan nu bovenaan, de filters in een keuzelijst
	 * boven de claims, en een ticket opent op een eigen pagina.
	 */
	private static function render_werkblad() {
		$alle     = HDP_Garantie::claims();
		$machines = HDP_Garantie::machines();
		$alle     = is_wp_error( $alle ) ? array() : $alle;
		$machines = is_wp_error( $machines ) ? array() : $machines;

		if ( 'machines' === self::gekozen_groep() ) {
			self::render_machines_pagina( $machines );
			return;
		}
		?>
		<div class="hdp-g-overzicht">
			<div class="hdp-g-acties">
				<a class="hdp-btn hdp-btn-secundair" href="<?php echo esc_url( add_query_arg( 'nieuw', 'machine', home_url( '/garantie/' ) ) ); ?>">
					<?php echo esc_html( HDP_I18N::t( 'garantie_ingang_machine_knop' ) ); ?>
				</a>
				<a class="hdp-btn" href="<?php echo esc_url( add_query_arg( 'nieuw', 'claim', home_url( '/garantie/' ) ) ); ?>">
					<?php echo esc_html( HDP_I18N::t( 'garantie_ingang_claim_knop' ) ); ?>
				</a>
			</div>

			<?php self::render_wacht_blok( $alle ); ?>

			<div class="hdp-g-twee">
				<section class="hdp-g-kaart hdp-g-claims" aria-labelledby="hdp-g-claims-kop">
					<?php self::render_claimkaart( $alle, $machines ); ?>
				</section>
				<section class="hdp-g-kaart hdp-g-machines" aria-labelledby="hdp-g-machines-kop">
					<?php self::render_machinekaart_overzicht( $machines ); ?>
				</section>
			</div>
		</div>
		<?php
	}

	/** Een ticket opent op zijn eigen pagina. */
	private static function ticket_url( $nummer ) {
		return add_query_arg( 'ticket', $nummer, home_url( '/garantie/' ) );
	}

	/**
	 * Wat op de dealer wacht, bovenaan in een donker blok, met per claim de
	 * knop om te reageren. Niets dat wacht: dan staat het blok er niet.
	 */
	private static function render_wacht_blok( $claims ) {
		$wacht = self::filter_claims( $claims, 'wacht' );
		if ( ! $wacht ) {
			return;
		}
		?>
		<section class="hdp-g-wacht" aria-labelledby="hdp-g-wacht-kop">
			<h2 class="hdp-g-wacht-kop" id="hdp-g-wacht-kop">
				<?php echo esc_html( HDP_I18N::t( 'garantie_rail_wacht' ) ); ?> &middot; <?php echo (int) count( $wacht ); ?>
			</h2>
			<?php foreach ( $wacht as $claim ) : ?>
				<div class="hdp-g-wacht-regel">
					<div class="hdp-g-wacht-tekst">
						<span class="hdp-g-wacht-meta">
							<?php echo esc_html( $claim['nummer'] ); ?>
							<?php if ( ! empty( $claim['merk'] ) ) : ?>
								&middot; <?php echo esc_html( $claim['merk'] ); ?>
							<?php endif; ?>
							&middot; <?php echo esc_html( HDP_I18N::t( 'garantie_ingediend_op' ) . ' ' . HDP_I18N::datum( 'j F Y', strtotime( $claim['ingediend'] ) ) ); ?>
						</span>
						<strong><?php echo esc_html( HDP_Garantie::claimtitel( $claim ) ); ?></strong>
						<span class="hdp-g-wacht-vraag"><?php echo esc_html( HDP_I18N::t( 'garantie_actie_nodig' ) ); ?></span>
					</div>
					<a class="hdp-btn" href="<?php echo esc_url( self::ticket_url( $claim['nummer'] ) . '#hdp-ticket-antwoord' ); ?>">
						<?php echo esc_html( HDP_I18N::t( 'garantie_antwoord_label' ) ); ?>
					</a>
				</div>
			<?php endforeach; ?>
		</section>
		<?php
	}

	/**
	 * De kaart "Mijn claims": een keuzelijst voor de groep (met aantallen),
	 * het zoekveld en de lijst. Filteren gebeurt in de browser; ?toon=…
	 * in de link kiest de groep vooraf.
	 */
	private static function render_claimkaart( $claims, $machines ) {
		$actief = self::gekozen_groep();
		$aantal = array(
			'alles' => count( $claims ),
			'wacht' => count( self::filter_claims( $claims, 'wacht' ) ),
			'loopt' => count( self::filter_claims( $claims, 'loopt' ) ),
			'klaar' => count( self::filter_claims( $claims, 'klaar' ) ),
		);
		?>
		<div class="hdp-g-kaart-kop">
			<h2 id="hdp-g-claims-kop">
				<?php echo esc_html( HDP_I18N::t( 'garantie_mijn_claims' ) ); ?>
				<span class="hdp-garantie-aantal"><?php echo (int) count( $claims ); ?></span>
			</h2>
			<?php if ( $claims ) : ?>
				<select id="hdp-g-filter" class="hdp-sorteer-select" aria-label="<?php echo esc_attr( HDP_I18N::t( 'garantie_rail_claims' ) ); ?>">
					<?php foreach ( $aantal as $groep => $getal ) : ?>
						<option value="<?php echo esc_attr( $groep ); ?>" <?php selected( $actief, $groep ); ?>>
							<?php echo esc_html( HDP_I18N::t( 'garantie_rail_' . $groep ) . ' (' . $getal . ')' ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			<?php endif; ?>
		</div>
		<?php if ( ! $claims ) : ?>
			<?php
			$tekst = HDP_I18N::t( 'garantie_geen_claims_totaal' ) . ( $machines ? ' ' . HDP_I18N::t( 'garantie_nog_niets_met_machines' ) : '' );
			echo HDP_Icons::render_lege_status( 'garantie', $tekst, add_query_arg( 'nieuw', 'claim', home_url( '/garantie/' ) ), HDP_I18N::t( 'garantie_ingang_claim_knop' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- render_lege_status() escapet elk veld zelf.
			?>
		<?php else : ?>
			<?php self::render_zoekveld(); ?>
			<?php self::render_claimlijst( $claims ); ?>
			<p class="hdp-g-filter-leeg" id="hdp-g-filter-leeg" hidden><?php echo esc_html( HDP_I18N::t( 'garantie_geen_claims_filter' ) ); ?></p>
			<script>
			(function () {
				var keuze = document.getElementById('hdp-g-filter');
				var leeg = document.getElementById('hdp-g-filter-leeg');
				var items = document.querySelectorAll('.hdp-garantie-lijst [data-groep]');
				if (!keuze) { return; }
				function toepassen() {
					var groep = keuze.value, zichtbaar = 0;
					items.forEach(function (item) {
						var past = 'alles' === groep || item.dataset.groep === groep;
						item.classList.toggle('hdp-g-buiten-filter', !past);
						if (past) { zichtbaar++; }
					});
					if (leeg) { leeg.hidden = zichtbaar > 0; }
				}
				keuze.addEventListener('change', toepassen);
				toepassen();
			})();
			</script>
		<?php endif; ?>
		<?php
	}

	/** Hoeveel machines er in de kaart op het overzicht staan. */
	const MACHINES_IN_KAART = 5;

	/**
	 * De kaart "Mijn machines" op het overzicht: de eerste paar, met de
	 * stand van de garantie, en een link naar de volledige lijst. Onderaan
	 * bij wie je terechtkunt met een vraag.
	 */
	private static function render_machinekaart_overzicht( $machines ) {
		?>
		<div class="hdp-g-kaart-kop">
			<h2 id="hdp-g-machines-kop">
				<?php echo esc_html( HDP_I18N::t( 'garantie_mijn_machines' ) ); ?>
				<span class="hdp-garantie-aantal"><?php echo (int) count( $machines ); ?></span>
			</h2>
		</div>
		<?php if ( ! $machines ) : ?>
			<?php
			echo HDP_Icons::render_lege_status( 'garantie', HDP_I18N::t( 'garantie_nog_niets_tekst' ), add_query_arg( 'nieuw', 'machine', home_url( '/garantie/' ) ), HDP_I18N::t( 'garantie_ingang_machine_knop' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- render_lege_status() escapet elk veld zelf.
			?>
		<?php else : ?>
			<ul class="hdp-g-machinelijst">
				<?php foreach ( array_slice( $machines, 0, self::MACHINES_IN_KAART ) as $machine ) : ?>
					<?php
					// Eén label met kleur per machine: loopt (groen, met balkje),
					// bijna verlopen (oranje), verlopen (grijs), wordt beoordeeld
					// (oranje) of afgewezen (rood).
					$stand       = HDP_Garantie::garantiestand( $machine );
					$beoordeling = isset( $machine['status'] ) ? $machine['status'] : 'goedgekeurd';
					$balkje      = false;
					if ( 'goedgekeurd' !== $beoordeling ) {
						$stand_tekst = HDP_I18N::t( 'garantie_machine_' . $beoordeling );
						$soort       = 'afgewezen' === $beoordeling ? 'rood' : 'oranje';
					} elseif ( $stand['verlopen'] ) {
						$stand_tekst = sprintf( HDP_I18N::t( 'garantie_verlopen_op' ), HDP_I18N::datum( 'j M Y', strtotime( $stand['tot'] ) ) );
						$soort       = 'grijs';
					} else {
						$stand_tekst = sprintf( HDP_I18N::t( 'garantie_tot_en_met' ), HDP_I18N::datum( 'j M Y', strtotime( $stand['tot'] ) ) );
						$soort       = $stand['bijna'] ? 'oranje' : 'groen';
						$balkje      = true;
					}
					$onder = array_filter( array( isset( $machine['merk'] ) ? $machine['merk'] : '', isset( $machine['klant'] ) ? $machine['klant'] : '' ) );
					$link = add_query_arg(
						array(
							'toon'        => 'machines',
							'serienummer' => $machine['serienummer'],
						),
						home_url( '/garantie/' )
					);
					?>
					<li>
						<a href="<?php echo esc_url( $link . '#hdp-machine-gekozen' ); ?>">
							<?php HDP_Icons::render_icoon( 'machine', 'hdp-g-machine-icoon' ); ?>
							<span class="hdp-g-machine-tekst">
								<strong><?php echo esc_html( $machine['machine'] ); ?></strong>
								<span class="hdp-g-machine-sub"><?php echo esc_html( implode( ' · ', array_merge( array( $machine['serienummer'] ), $onder ) ) ); ?></span>
								<span class="hdp-g-machine-stand">
									<span class="hdp-g-machine-label hdp-g-machine-label-<?php echo esc_attr( $soort ); ?>"><?php echo esc_html( $stand_tekst ); ?></span>
									<?php if ( $balkje ) : ?>
										<span class="hdp-g-machine-balk hdp-g-machine-balk-<?php echo esc_attr( $soort ); ?>" aria-hidden="true"><i style="width:<?php echo esc_attr( (string) $stand['verstreken'] ); ?>%"></i></span>
									<?php endif; ?>
								</span>
							</span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="hdp-g-alle-machines">
				<a href="<?php echo esc_url( add_query_arg( 'toon', 'machines', home_url( '/garantie/' ) ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'garantie_alle_machines' ) ); ?></a>
			</p>
		<?php endif; ?>
		<?php self::render_rail_contact(); ?>
		<?php
	}

	/** Alle machines op een eigen pagina, met zoeken en een weg terug. */
	private static function render_machines_pagina( $machines ) {
		?>
		<div class="hdp-g-machinepagina">
			<p class="hdp-g-terug">
				<a href="<?php echo esc_url( home_url( '/garantie/' ) ); ?>">&larr; <?php echo esc_html( HDP_I18N::t( 'garantie_terug_naar_garantie' ) ); ?></a>
				<a class="hdp-btn hdp-btn-secundair" href="<?php echo esc_url( add_query_arg( 'nieuw', 'machine', home_url( '/garantie/' ) ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'garantie_ingang_machine_knop' ) ); ?></a>
			</p>
			<?php self::render_zoekveld(); ?>
			<?php self::render_machinelijst( $machines ); ?>
		</div>
		<?php
	}

	/** De claims die bij een groep horen. */
	private static function filter_claims( $claims, $groep ) {
		$groepen = self::groepen();
		if ( ! isset( $groepen[ $groep ] ) ) {
			return $claims;
		}

		return array_values(
			array_filter(
				$claims,
				static function ( $claim ) use ( $groepen, $groep ) {
					return in_array( $claim['status'], $groepen[ $groep ], true );
				}
			)
		);
	}

	/** In welke groep van de keuzelijst een claim valt. */
	private static function groep_van( $claim ) {
		foreach ( self::groepen() as $groep => $statussen ) {
			if ( in_array( $claim['status'], $statussen, true ) ) {
				return $groep;
			}
		}
		return 'loopt';
	}

	/** Bij wie je terechtkunt met een garantievraag; stil als dat niet is ingesteld. */
	private static function render_rail_contact() {
		$email    = HDP_Settings::get( 'garantie_email' );
		$telefoon = HDP_Settings::get( 'garantie_telefoon' );

		if ( ! $email && ! $telefoon ) {
			return;
		}
		?>
		<div class="hdp-g-contact">
			<span class="hdp-g-contact-kop"><?php echo esc_html( HDP_I18N::t( 'garantie_rail_contact' ) ); ?></span>
			<?php if ( $telefoon ) : ?>
				<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $telefoon ) ); ?>">
					<?php HDP_Icons::render_icoon( 'tel', 'hdp-rail-contact-icoon' ); ?>
					<span><?php echo esc_html( $telefoon ); ?></span>
				</a>
			<?php endif; ?>
			<?php if ( $email ) : ?>
				<a href="mailto:<?php echo esc_attr( $email ); ?>">
					<?php HDP_Icons::render_icoon( 'mail', 'hdp-rail-contact-icoon' ); ?>
					<span><?php echo esc_html( $email ); ?></span>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * De twee dingen die een dealer hier kan doen, bovenaan de pagina.
	 * Allebei leiden ze naar een eigen scherm: een formulier dat open onder
	 * een lijst hangt leest als een naschrift in plaats van als een taak.
	 */
	private static function render_ingangen() {
		$ingangen = array(
			'machine' => 'garantie',
			'claim'   => 'sleutel',
		);
		?>
		<div class="hdp-garantie-ingangen">
			<?php foreach ( $ingangen as $soort => $icoon ) : ?>
				<article class="hdp-garantie-ingang">
					<?php HDP_Icons::render_icoon( $icoon ); ?>
					<h2><?php echo esc_html( HDP_I18N::t( 'garantie_ingang_' . $soort . '_titel' ) ); ?></h2>
					<p><?php echo esc_html( HDP_I18N::t( 'garantie_ingang_' . $soort . '_tekst' ) ); ?></p>
					<a class="hdp-btn" href="<?php echo esc_url( add_query_arg( 'nieuw', $soort, home_url( '/garantie/' ) ) ); ?>">
						<?php echo esc_html( HDP_I18N::t( 'garantie_ingang_' . $soort . '_knop' ) ); ?>
					</a>
				</article>
			<?php endforeach; ?>
		</div>
		<?php
	}

	/**
	 * Wat op de dealer wacht, bovenaan en in kleur. De vraag waarmee iemand
	 * deze pagina opent is bijna altijd "moet ik iets doen?" — dus staat het
	 * antwoord daarop vóór alle lijsten.
	 */
	private static function render_wacht_op_u() {
		$claims = HDP_Garantie::claims_die_wachten_op_dealer();

		if ( ! $claims ) {
			return;
		}

		$eerste = $claims[0];
		$aantal = count( $claims );
		?>
		<div class="hdp-garantie-aandacht">
			<?php HDP_Icons::render_icoon( 'wachten', 'hdp-aandacht-icoon' ); ?>
			<div class="hdp-aandacht-tekst">
				<strong>
					<?php
					echo esc_html(
						1 === $aantal
							? HDP_I18N::t( 'garantie_wacht_een' )
							: sprintf( HDP_I18N::t( 'garantie_wacht_meer' ), $aantal )
					);
					?>
				</strong>
				<span>
					<?php echo esc_html( $eerste['nummer'] ); ?> &middot;
					<?php echo esc_html( HDP_Garantie::machinenaam( $eerste['machine'], $eerste['serienummer'] ) ); ?>
				</span>
			</div>
			<a class="hdp-btn" href="<?php echo esc_url( add_query_arg( 'ticket', $eerste['nummer'], home_url( '/garantie/' ) ) ); ?>">
				<?php echo esc_html( HDP_I18N::t( 'garantie_bekijken' ) ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Melding als de claimadministratie even niet bereikbaar is.
	 *
	 * Een storing daar mag de rest van het portaal niet meeslepen: de webshop
	 * en de downloads werken gewoon door, alleen deze pagina zegt eerlijk dat
	 * ze de gegevens nu niet kan ophalen.
	 */
	/**
	 * De uitkomst van een zojuist verstuurd formulier. Komt uit een
	 * kortlopende transient, omdat er na het opslaan is doorverwezen —
	 * anders dient een dealer bij het verversen zijn claim nog eens in.
	 */
	private static function render_meldingen() {
		$geslaagd = HDP_Garantie_Formulier::geslaagd();
		$fout     = HDP_Garantie_Formulier::fout();

		if ( $geslaagd ) {
			?>
			<p class="hdp-garantie-gelukt">
				<strong><?php echo esc_html( $geslaagd['melding'] ); ?></strong>
				<?php if ( ! empty( $geslaagd['mislukt'] ) ) : ?>
					<?php // Een mislukte bijlage laat de claim zelf staan; die is al ingediend. ?>
					<span><?php echo esc_html( sprintf( HDP_I18N::t( 'garantie_bijlagen_mislukt' ), implode( ', ', $geslaagd['mislukt'] ) ) ); ?></span>
				<?php endif; ?>
			</p>
			<?php
		}

		if ( $fout ) {
			?>
			<p class="hdp-garantie-storing"><strong><?php echo esc_html( $fout['melding'] ); ?></strong></p>
			<?php
		}
	}

	private static function render_storing() {
		?>
		<p class="hdp-garantie-storing">
			<strong><?php echo esc_html( HDP_I18N::t( 'garantie_storing_kop' ) ); ?></strong>
			<?php echo esc_html( HDP_I18N::t( 'garantie_storing_tekst' ) ); ?>
		</p>
		<?php
	}

	/**
	 * Eén zoekveld voor allebei de lijsten. Een dealer zoekt op wat hij in
	 * zijn hoofd heeft — een serienummer, een machinenaam, een claimnummer —
	 * en wil niet eerst hoeven bedenken of dat bij "claims" of bij
	 * "machines" hoort. Het filtert daarom beide tegelijk.
	 */
	private static function render_zoekveld() {
		?>
		<div class="hdp-garantie-zoeken">
			<div class="hdp-zoekveld">
				<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
				<label class="screen-reader-text" for="hdp-garantie-zoeken"><?php echo esc_html( HDP_I18N::t( 'garantie_zoek_label' ) ); ?></label>
				<input type="search" id="hdp-garantie-zoeken" autocomplete="off"
					placeholder="<?php echo esc_attr( HDP_I18N::t( 'garantie_zoek_placeholder' ) ); ?>">
			</div>
			<p class="hdp-garantie-geen-treffers" id="hdp-garantie-geen-treffers" aria-live="polite">
				<?php echo esc_html( HDP_I18N::t( 'garantie_zoek_geen_treffers' ) ); ?>
			</p>
		</div>
		<?php
	}

	/** Hoeveel machines er staan voordat de rest wordt ingeklapt. */
	const MACHINES_ZICHTBAAR = 12;

	/**
	 * De knop die de rest van de machines uitklapt.
	 *
	 * @param int $totaal Hoeveel machines er in totaal zijn.
	 */
	private static function render_machines_meer( $totaal ) {
		$rest = $totaal - self::MACHINES_ZICHTBAAR;
		if ( $rest < 1 ) {
			return;
		}
		?>
		<button type="button" class="hdp-btn hdp-btn-secundair hdp-machines-meer" id="hdp-machines-meer"
			aria-controls="hdp-machines" aria-expanded="false">
			<?php echo esc_html( sprintf( HDP_I18N::t( 'garantie_machines_meer' ), $rest ) ); ?>
		</button>
		<script>
		(function () {
			var knop  = document.getElementById('hdp-machines-meer');
			var lijst = document.getElementById('hdp-machines');
			if (!knop || !lijst) { return; }

			knop.addEventListener('click', function () {
				lijst.classList.remove('hdp-machines-ingeklapt');
				knop.setAttribute('aria-expanded', 'true');
				knop.hidden = true;
			});

			// Zoeken hoort door de hele lijst te gaan: een machine die
			// ingeklapt stond en wel een treffer is, moet gewoon
			// tevoorschijn komen.
			var zoekveld = document.getElementById('hdp-garantie-zoeken');
			if (zoekveld) {
				zoekveld.addEventListener('input', function () {
					if ('' === zoekveld.value.trim()) {
						return;
					}
					lijst.classList.remove('hdp-machines-ingeklapt');
					knop.hidden = true;
				});
			}
		})();
		</script>
		<?php
	}

	/**
	 * Zoeken gebeurt in de browser: de lijsten zijn klein en staan al op de
	 * pagina, dus een nieuwe serverronde per toetsaanslag zou alleen maar
	 * trager voelen. Elk item draagt zijn doorzoekbare tekst in
	 * data-zoek mee (zie render_claimlijst() en render_machinelijst()).
	 */
	private static function render_zoekscript() {
		?>
		<script>
		(function () {
			var zoekveld = document.getElementById('hdp-garantie-zoeken');
			if (!zoekveld) { return; }

			var secties = [];
			['hdp-garantie-lijst', 'hdp-machines'].forEach(function (klasse) {
				var lijst = document.querySelector('.' + klasse);
				if (!lijst) { return; }
				secties.push({
					lijst: lijst,
					// De sectiekop hoort mee te verdwijnen als er in die
					// lijst niets overblijft; een kop boven niets is
					// verwarrender dan geen kop.
					kop: lijst.previousElementSibling,
					items: Array.prototype.slice.call(lijst.querySelectorAll('[data-zoek]'))
				});
			});

			var geenTreffers = document.getElementById('hdp-garantie-geen-treffers');

			function filteren() {
				var term = zoekveld.value.trim().toLowerCase();
				var totaal = 0;

				secties.forEach(function (sectie) {
					var zichtbaar = 0;
					sectie.items.forEach(function (item) {
						var treffer = !term || item.dataset.zoek.indexOf(term) !== -1;
						item.hidden = !treffer;
						if (treffer) { zichtbaar++; }
					});
					sectie.lijst.hidden = zichtbaar === 0;
					if (sectie.kop && sectie.kop.classList.contains('hdp-garantie-kop')) {
						sectie.kop.hidden = zichtbaar === 0;
					}
					totaal += zichtbaar;
				});

				if (geenTreffers) {
					geenTreffers.classList.toggle('hdp-zichtbaar', totaal === 0);
				}
			}

			zoekveld.addEventListener('input', filteren);
		})();
		</script>
		<?php
	}

	/**
	 * De claims die nog lopen. Afgehandelde claims staan bewust niet in dit
	 * overzicht: die vragen niets meer, en zouden de lijst alleen maar langer
	 * maken naarmate een dealer er meer indient. Ze blijven bereikbaar via
	 * "alle claims".
	 */
	private static function render_claimlijst( $claims ) {
		$groep = self::gekozen_groep();
		?>
		<?php if ( ! $claims ) : ?>
			<?php
			// Een lege lijst hoort te zeggen wát er leeg is, en wat je nu
			// kunt doen — niet alleen dat er niets staat.
			$leeg_alles = 'alles' === $groep;
			$leeg_tekst = HDP_I18N::t( $leeg_alles ? 'garantie_geen_claims_totaal' : 'garantie_geen_claims_filter' );
			$leeg_knop  = HDP_I18N::t( $leeg_alles ? 'garantie_ingang_claim_knop' : 'garantie_rail_toon_alles' );
			$leeg_url   = $leeg_alles
				? add_query_arg( 'nieuw', 'claim', home_url( '/garantie/' ) )
				: add_query_arg( 'toon', 'alles', home_url( '/garantie/' ) );

			echo HDP_Icons::render_lege_status( 'garantie', $leeg_tekst, $leeg_url, $leeg_knop ); // phpcs:ignore WordPress.Security.EscapeOutput -- render_lege_status() escapet elk veld zelf.
			?>
		<?php else : ?>
			<div class="hdp-garantie-lijst">
				<?php foreach ( $claims as $claim ) : ?>
					<?php
					// Een claim waar de dealer zélf iets moet doen hoort eruit
					// te springen; de overige statussen zijn ter informatie.
					$actie_nodig = HDP_Garantie::STATUS_ACTIE_DEALER === $claim['status'];
					?>
					<a class="hdp-garantie-item<?php echo $actie_nodig ? ' hdp-garantie-item-actie' : ''; ?>"
						href="<?php echo esc_url( self::ticket_url( $claim['nummer'] ) ); ?>"
						data-groep="<?php echo esc_attr( self::groep_van( $claim ) ); ?>"
						data-zoek="<?php echo esc_attr( self::zoektekst( array( $claim['nummer'], HDP_Garantie::claimtitel( $claim ), $claim['merk'], $claim['klacht'] ) ) ); ?>">
						<div class="hdp-garantie-item-hoofd">
							<span class="hdp-garantie-nummer"><?php echo esc_html( $claim['nummer'] ); ?></span>
							<span class="<?php echo esc_attr( HDP_Garantie::status_klasse( $claim['status'] ) ); ?>">
								<?php echo esc_html( HDP_Garantie::status_label( $claim['status'] ) ); ?>
							</span>
						</div>
						<div class="hdp-garantie-item-info">
							<?php // Machine met serienummer, of bij een onderdelenclaim het probleem met het factuurnummer; zie HDP_Garantie::claimtitel(). ?>
							<strong><?php echo esc_html( HDP_Garantie::claimtitel( $claim ) ); ?></strong>
							<span>
								<?php echo esc_html( HDP_I18N::t( 'garantie_ingediend_op' ) ); ?>
								<?php echo esc_html( HDP_I18N::datum( 'j F Y', strtotime( $claim['ingediend'] ) ) ); ?>
							</span>
						</div>
						<?php if ( $actie_nodig ) : ?>
							<p class="hdp-garantie-actie-tekst"><?php echo esc_html( HDP_I18N::t( 'garantie_actie_nodig' ) ); ?></p>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * De tekst waarop een kaart doorzocht wordt: alles wat een dealer
	 * redelijkerwijs zou intypen, in kleine letters aan elkaar.
	 */
	private static function zoektekst( $delen ) {
		return strtolower( implode( ' ', array_filter( $delen ) ) );
	}

	/**
	 * De aangemelde machines, met per machine hoelang de garantie nog loopt.
	 * Die balk is het punt van deze lijst: zonder dat ziet niemand aankomen
	 * dat dekking afloopt, en dat is nu juist het moment om nog te claimen.
	 */
	private static function render_machinelijst( $machines ) {
		// Een dealer met honderd machines krijgt anders honderd kaarten
		// achter elkaar. Ze staan er allemaal — de zoekbalk filtert in de
		// browser en moet ze dus kunnen vinden — maar wat boven deze grens
		// uitkomt staat ingeklapt tot iemand erom vraagt.
		$inklappen = count( $machines ) > self::MACHINES_ZICHTBAAR;
		$gezocht   = isset( $_GET['serienummer'] ) ? sanitize_text_field( wp_unslash( $_GET['serienummer'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- alleen een weergavekeuze.
		?>
		<div class="hdp-garantie-kop">
			<h2>
				<?php echo esc_html( HDP_I18N::t( 'garantie_mijn_machines' ) ); ?>
				<span class="hdp-garantie-aantal"><?php echo esc_html( (string) count( $machines ) ); ?></span>
			</h2>
		</div>

		<?php if ( ! $machines ) : ?>
			<p class="hdp-gesprek-leeg"><?php echo esc_html( HDP_I18N::t( 'garantie_geen_machines' ) ); ?></p>
		<?php else : ?>
			<div class="hdp-machines<?php echo $inklappen ? ' hdp-machines-ingeklapt' : ''; ?>" id="hdp-machines">
				<?php foreach ( $machines as $nummer => $machine ) : ?>
					<?php
					// Vanuit de zoekbalk: deze machine in beeld en opgelicht, ook
					// als hij anders onder "toon meer" zou vallen.
					$gekozen   = '' !== $gezocht && $gezocht === (string) $machine['serienummer'];
					$verborgen = $inklappen && $nummer >= self::MACHINES_ZICHTBAAR && ! $gekozen;
					?>
					<?php
					$stand  = HDP_Garantie::garantiestand( $machine );
					$lopend = count(
						array_filter(
							HDP_Garantie::claims_van_machine( $machine['serienummer'] ),
							static function ( $claim ) {
								return ! HDP_Garantie::is_afgerond( $claim['status'] );
							}
						)
					);

					$beoordeling = isset( $machine['status'] ) ? $machine['status'] : 'goedgekeurd';

					$balk = 'hdp-machine-balk';
					if ( $stand['verlopen'] ) {
						$balk .= ' hdp-machine-balk-verlopen';
					} elseif ( $stand['bijna'] ) {
						$balk .= ' hdp-machine-balk-bijna';
					}
					?>
					<article class="hdp-machine<?php echo $verborgen ? ' hdp-machine-verborgen' : ''; ?><?php echo $gekozen ? ' hdp-machine-gekozen' : ''; ?>"
						<?php echo $gekozen ? 'id="hdp-machine-gekozen"' : ''; ?>
						data-zoek="<?php echo esc_attr( self::zoektekst( array( $machine['machine'], $machine['serienummer'], $machine['merk'], $machine['klant'] ) ) ); ?>">
						<?php HDP_Icons::render_icoon( 'machine', 'hdp-machine-icoon' ); ?>
						<div class="hdp-machine-info">
							<strong><?php echo esc_html( HDP_Garantie::machinenaam( $machine['machine'], $machine['serienummer'] ) ); ?></strong>
							<span>
								<?php if ( $machine['hectares'] ) : ?>
									<?php echo esc_html( $machine['hectares'] ); ?> ha &middot;
								<?php endif; ?>
								<?php
								if ( 1 === $lopend ) {
									echo esc_html( HDP_I18N::t( 'garantie_machine_lopend_een' ) );
								} elseif ( $lopend ) {
									echo esc_html( sprintf( HDP_I18N::t( 'garantie_machine_lopend' ), $lopend ) );
								} else {
									echo esc_html( HDP_I18N::t( 'garantie_machine_geen_lopend' ) );
								}
								?>
							</span>
						</div>
						<?php if ( 'goedgekeurd' !== $beoordeling ) : ?>
							<?php
							// Een machine die nog beoordeeld moet worden of is
							// afgewezen: dat hoort de dealer te zien, want het
							// bepaalt of een claim erop verder kan.
							?>
							<div class="hdp-machine-beoordeling hdp-machine-beoordeling-<?php echo esc_attr( $beoordeling ); ?>">
								<strong><?php echo esc_html( HDP_I18N::t( 'garantie_machine_' . $beoordeling ) ); ?></strong>
								<?php if ( 'afgewezen' === $beoordeling && ! empty( $machine['reden'] ) ) : ?>
									<span><?php echo esc_html( $machine['reden'] ); ?></span>
								<?php endif; ?>
							</div>
						<?php endif; ?>
						<div class="hdp-machine-garantie">
							<span class="hdp-machine-garantie-label">
								<?php if ( $stand['verlopen'] ) : ?>
									<?php echo esc_html( sprintf( HDP_I18N::t( 'garantie_verlopen_op' ), HDP_I18N::datum( 'j M Y', strtotime( $stand['tot'] ) ) ) ); ?>
								<?php else : ?>
									<?php echo esc_html( sprintf( HDP_I18N::t( 'garantie_tot_en_met' ), HDP_I18N::datum( 'j M Y', strtotime( $stand['tot'] ) ) ) ); ?>
									<?php if ( $stand['bijna'] ) : ?>
										<strong>&mdash; <?php echo esc_html( sprintf( HDP_I18N::t( 'garantie_nog_maanden' ), $stand['maanden'] ) ); ?></strong>
									<?php endif; ?>
								<?php endif; ?>
							</span>
							<span class="<?php echo esc_attr( $balk ); ?>">
								<i style="width:<?php echo esc_attr( (string) $stand['verstreken'] ); ?>%"></i>
							</span>
						</div>
						<?php if ( ! HDP_Garantie::waarom_niet_claimbaar( $machine ) ) : ?>
							<?php
							// Rechtstreeks een claim op déze machine. Eerder: naar
							// "Claim indienen", "machine" kiezen, en dezelfde
							// machine in de lijst opnieuw opzoeken.
							$claim_url = add_query_arg(
								array(
									'nieuw'   => 'claim',
									'soort'   => HDP_Garantie::SOORT_MACHINE,
									'machine' => $machine['serienummer'],
								),
								home_url( '/garantie/' )
							);
							?>
							<a class="hdp-btn hdp-btn-secundair hdp-btn-klein hdp-machine-claim" href="<?php echo esc_url( $claim_url ); ?>">
								<?php echo esc_html( HDP_I18N::t( 'garantie_ingang_claim_knop' ) ); ?>
							</a>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
			<?php if ( $inklappen ) : ?>
				<?php self::render_machines_meer( count( $machines ) ); ?>
			<?php endif; ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Eerst de vraag waar de claim over gaat.
	 *
	 * Een machineclaim en een onderdelenclaim vragen om heel andere dingen:
	 * de een om een serienummer en reparatietijd, de ander om een
	 * factuurnummer en wat er mis is met de levering. Die in één formulier
	 * proppen met velden die aan- en uitspringen levert een scherm op waar
	 * niemand doorheen komt. Dus: één vraag vooraf, en daarna een formulier
	 * dat alleen vraagt wat er bij hoort.
	 */
	private static function render_soortkeuze() {
		ob_start();
		?>
		<div class="hdp-portaal alignfull">
			<?php
			HDP_Paginakop::render(
				array(
					'titel'       => HDP_I18N::t( 'garantie_kies_soort_titel' ),
					'intro'       => HDP_I18N::t( 'garantie_kies_soort_tekst' ),
					'terug_url'   => home_url( '/garantie/' ),
					'terug_tekst' => HDP_I18N::t( 'garantie_terug_naar_garantie' ),
				)
			);
			?>
			<section class="hdp-garantie">
				<?php self::render_meldingen(); ?>
				<div class="hdp-garantie-ingangen">
					<?php
					$keuzes = array(
						HDP_Garantie::SOORT_MACHINE   => 'garantie',
						HDP_Garantie::SOORT_ONDERDEEL => 'bestellen',
					);
					?>
					<?php foreach ( $keuzes as $keuze => $icoon ) : ?>
						<article class="hdp-garantie-ingang">
							<?php HDP_Icons::render_icoon( $icoon ); ?>
							<h2><?php echo esc_html( HDP_I18N::t( 'garantie_soort_' . $keuze . '_titel' ) ); ?></h2>
							<p><?php echo esc_html( HDP_I18N::t( 'garantie_soort_' . $keuze . '_tekst' ) ); ?></p>
							<a class="hdp-btn" href="<?php echo esc_url( add_query_arg( array( 'nieuw' => 'claim', 'soort' => $keuze ), home_url( '/garantie/' ) ) ); ?>">
								<?php echo esc_html( HDP_I18N::t( 'garantie_soort_' . $keuze . '_knop' ) ); ?>
							</a>
						</article>
					<?php endforeach; ?>
				</div>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * De mededeling die bij een onderdelenclaim nergens te missen mag zijn:
	 * wij beginnen pas als het onderdeel hier is.
	 *
	 * Dit stond eerst als hintregel onder een vinkje "ik stuur onderdelen
	 * retour". Daarmee was het iets dat de dealer kon overslaan, terwijl het
	 * bepaalt of zijn claim überhaupt in behandeling komt. Dus: boven het
	 * formulier, in een eigen kader, voordat hij begint met invullen.
	 *
	 * Bij "niet ontvangen" is er niets om terug te sturen. Welk antwoord dat
	 * is staat in data-zonder-onderdeel; het gedeelde script verbergt het
	 * blok dan. Zo staat die uitzondering niet nog eens los in de JavaScript.
	 *
	 * @param string $nummer Het ticketnummer, als dat al bestaat.
	 */
	private static function render_retour_attentie( $nummer = '' ) {
		$uitleg = '' === $nummer
			? HDP_I18N::t( 'garantie_retour_uitleg' )
			: sprintf( HDP_I18N::t( 'garantie_retour_uitleg_nummer' ), $nummer );
		?>
		<div class="hdp-garantie-attentie" id="hdp-retour-attentie"
			data-zonder-onderdeel="<?php echo esc_attr( HDP_Garantie::PROBLEEM_ZONDER_ONDERDEEL ); ?>">
			<?php // Eigen badge en niet hdp-kaart-icoon: dat is een vlak van 52px voor een kaarttegel, veel te zwaar boven een mededeling van drie regels. ?>
			<?php HDP_Icons::render_icoon( 'bestellen', 'hdp-garantie-attentie-icoon' ); ?>
			<div>
				<strong><?php echo esc_html( HDP_I18N::t( 'garantie_retour_kop' ) ); ?></strong>
				<p><?php echo esc_html( $uitleg ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * De regels van "te claimen onderdelen".
	 *
	 * Vier kolommen per regel, en bij een machineclaim vijf: daar staan de
	 * uren per onderdeel erbij. Eén totaal voor de hele claim zei niets
	 * over wat er aan wélk onderdeel is gedaan.
	 *
	 * De herkomst is een keuzemenu en geen vinkje: een vinkje dat uit staat
	 * stuurt niets mee, waardoor de rijtjes niet meer gelijk lopen en bedrag
	 * 2 bij onderdeel 3 belandt.
	 *
	 * @param array $eerder    Wat er na een afgekeurde invoer al stond.
	 * @param bool  $met_uren  Of de urenkolom erbij hoort.
	 */
	private static function render_claimregels( $eerder, $met_uren = false ) {
		$eerder = is_array( $eerder ) ? array_values( $eerder ) : array();
		// Altijd een lege regel onderaan om in te vullen, en minstens drie.
		$aantal = max( 3, count( $eerder ) + 1 );
		$hint   = $met_uren ? 'garantie_regels_hint_uren' : 'garantie_regels_hint';
		?>
		<span class="hdp-veld-hint"><?php echo esc_html( HDP_I18N::t( $hint ) ); ?></span>
		<div class="hdp-claimregels<?php echo $met_uren ? ' hdp-claimregels-met-uren' : ''; ?>" id="hdp-claimregels">
			<div class="hdp-claimregel hdp-claimregel-kop" aria-hidden="true">
				<span><?php echo esc_html( HDP_I18N::t( 'garantie_regel_nummer' ) ); ?></span>
				<span><?php echo esc_html( HDP_I18N::t( 'garantie_regel_aantal' ) ); ?></span>
				<span><?php echo esc_html( HDP_I18N::t( 'garantie_regel_bedrag' ) ); ?></span>
				<?php if ( $met_uren ) : ?>
					<span><?php echo esc_html( HDP_I18N::t( 'garantie_regel_uren' ) ); ?></span>
				<?php endif; ?>
				<span><?php echo esc_html( HDP_I18N::t( 'garantie_regel_herkomst' ) ); ?></span>
			</div>
			<?php for ( $i = 0; $i < $aantal; $i++ ) : ?>
				<?php
				$regel  = isset( $eerder[ $i ] ) ? (array) $eerder[ $i ] : array();
				$nummer = isset( $regel['nummer'] ) ? $regel['nummer'] : '';
				$stuks  = isset( $regel['aantal'] ) ? $regel['aantal'] : '';
				// Opgeslagen in centen; hier weer als "1021,31" in het veld.
				$bedrag = isset( $regel['bedrag_cent'] ) && null !== $regel['bedrag_cent']
					? number_format( $regel['bedrag_cent'] / 100, 2, ',', '' )
					: '';
				$derden = isset( $regel['homburg_factuur'] ) && ! $regel['homburg_factuur'];
				// 0 is een geldig antwoord (onderdeel geleverd, geen tijd aan
				// besteed), dus niet op leegheid maar op null controleren.
				$uren    = isset( $regel['uren'] ) && null !== $regel['uren'] ? $regel['uren'] : '';
				$regelnr = $i + 1;
				?>
				<div class="hdp-claimregel">
					<label>
						<span class="hdp-vh"><?php printf( esc_html( HDP_I18N::t( 'garantie_regel_nummer_van' ) ), (int) $regelnr ); ?></span>
						<input type="text" name="onderdeel_nummer[]" value="<?php echo esc_attr( $nummer ); ?>" autocomplete="off">
					</label>
					<label>
						<span class="hdp-vh"><?php printf( esc_html( HDP_I18N::t( 'garantie_regel_aantal_van' ) ), (int) $regelnr ); ?></span>
						<input type="number" min="1" step="1" name="onderdeel_aantal[]" value="<?php echo esc_attr( $stuks ); ?>">
					</label>
					<label>
						<span class="hdp-vh"><?php printf( esc_html( HDP_I18N::t( 'garantie_regel_bedrag_van' ) ), (int) $regelnr ); ?></span>
						<input type="text" inputmode="decimal" name="onderdeel_bedrag[]" value="<?php echo esc_attr( $bedrag ); ?>" placeholder="0,00">
					</label>
					<?php if ( $met_uren ) : ?>
						<label>
							<span class="hdp-vh"><?php printf( esc_html( HDP_I18N::t( 'garantie_regel_uren_van' ) ), (int) $regelnr ); ?></span>
							<?php // Geen "required": een lege regel onderaan hoort niet tegengehouden te worden. De controle gebeurt bij het indienen, op de regels die daadwerkelijk ingevuld zijn. ?>
							<input type="number" min="0" step="0.25" name="onderdeel_uren[]" value="<?php echo esc_attr( $uren ); ?>" placeholder="0">
						</label>
					<?php endif; ?>
					<label>
						<span class="hdp-vh"><?php printf( esc_html( HDP_I18N::t( 'garantie_regel_herkomst_van' ) ), (int) $regelnr ); ?></span>
						<select name="onderdeel_herkomst[]">
							<option value="homburg" <?php selected( ! $derden ); ?>><?php echo esc_html( HDP_I18N::t( 'garantie_regel_homburg' ) ); ?></option>
							<option value="derden" <?php selected( $derden ); ?>><?php echo esc_html( HDP_I18N::t( 'garantie_regel_derden' ) ); ?></option>
						</select>
					</label>
				</div>
			<?php endfor; ?>
		</div>
		<button type="button" class="hdp-btn hdp-btn-secundair hdp-btn-klein" id="hdp-regel-erbij"><?php echo esc_html( HDP_I18N::t( 'garantie_regel_toevoegen' ) ); ?></button>
		<script>
		(function () {
			var knop = document.getElementById( 'hdp-regel-erbij' );
			var lijst = document.getElementById( 'hdp-claimregels' );
			if ( ! knop || ! lijst ) { return; }

			knop.addEventListener( 'click', function () {
				var regels = lijst.querySelectorAll( '.hdp-claimregel:not(.hdp-claimregel-kop)' );
				var laatste = regels[ regels.length - 1 ];
				if ( ! laatste ) { return; }

				var nieuw = laatste.cloneNode( true );
				nieuw.querySelectorAll( 'input' ).forEach( function ( veld ) { veld.value = ''; } );
				nieuw.querySelectorAll( 'select' ).forEach( function ( veld ) { veld.selectedIndex = 0; } );
				lijst.appendChild( nieuw );
				var eerste = nieuw.querySelector( 'input' );
				if ( eerste ) { eerste.focus(); }
			} );
		})();
		</script>
		<?php
	}

	/**
	 * Een formulier op zijn eigen scherm: machine aanmelden of claim
	 * indienen. Bewust niet meer onder de lijst op het overzicht — daar las
	 * het als een naschrift in plaats van als een taak, en met twee
	 * formulieren was dat helemaal niet vol te houden.
	 *
	 * De velden komen uit HDP_Garantie::velden() en ::machinevelden(),
	 * dezelfde lijsten waar straks de Supabase-tabellen op gebaseerd worden
	 * — zo kunnen ontwerp en database niet uit elkaar lopen.
	 *
	 * Alle velden staan op "disabled": er is nog niets om naartoe te
	 * versturen, en een formulier dat je wél kunt invullen maar dat niets
	 * doet is erger dan geen formulier.
	 *
	 * @param string $soort       'machine' (aanmelden) of 'claim' (indienen).
	 * @param string $claimsoort  Bij een claim: 'machine' of 'onderdeel'.
	 */
	private static function render_formulier( $soort, $claimsoort = HDP_Garantie::SOORT_MACHINE ) {
		$is_claim     = 'claim' === $soort;
		$is_onderdeel = $is_claim && HDP_Garantie::SOORT_ONDERDEEL === $claimsoort;
		$velden       = $is_claim ? HDP_Garantie::invulvelden( $claimsoort ) : HDP_Garantie::machinevelden();
		$titelsleutel = $is_onderdeel ? 'garantie_soort_onderdeel' : ( $is_claim ? 'garantie_soort_machine' : 'garantie_ingang_machine' );

		ob_start();
		?>
		<div class="hdp-portaal alignfull">
			<?php
			HDP_Paginakop::render(
				array(
					'titel'       => HDP_I18N::t( $titelsleutel . '_titel' ),
					'intro'       => HDP_I18N::t( $titelsleutel . '_tekst' ),
					'terug_url'   => home_url( '/garantie/' ),
					'terug_tekst' => HDP_I18N::t( 'garantie_terug_naar_garantie' ),
				)
			);
			?>
			<section class="hdp-garantie">
				<?php self::render_meldingen(); ?>
				<form class="hdp-garantie-formulier" method="post" enctype="multipart/form-data"
					action="<?php echo esc_url( add_query_arg( $is_claim ? array( 'nieuw' => 'claim', 'soort' => $claimsoort ) : array( 'nieuw' => $soort ), home_url( '/garantie/' ) ) ); ?>"
					<?php echo self::controle_attributen(); // phpcs:ignore WordPress.Security.EscapeOutput -- controle_attributen() escapet elke waarde zelf. ?>>
					<?php wp_nonce_field( HDP_Garantie_Formulier::NONCE ); ?>
					<input type="hidden" name="<?php echo esc_attr( HDP_Garantie_Formulier::ACTIE_VELD ); ?>" value="<?php echo esc_attr( $soort ); ?>">
					<?php if ( $is_claim ) : ?>
						<input type="hidden" name="soort" value="<?php echo esc_attr( $claimsoort ); ?>">
					<?php endif; ?>
					<?php if ( $is_claim && ! $is_onderdeel ) : ?>
						<?php self::render_machinekeuze(); ?>
					<?php endif; ?>
					<?php if ( $is_onderdeel ) : ?>
						<?php self::render_retour_attentie(); ?>
					<?php endif; ?>

					<div class="hdp-garantie-velden">
						<?php foreach ( $velden as $naam => $veld ) : ?>
							<?php
							// Bij een onderdelenclaim is "defect" niet altijd het
							// goede woord — een doos die verkeerd geleverd is,
							// is nergens stuk. Daar heet dit veld Toelichting.
							$label     = ( $is_onderdeel && 'klacht' === $naam )
								? HDP_I18N::t( 'garantie_veld_klacht_onderdeel' )
								: HDP_I18N::t( 'garantie_veld_' . $naam );
							// Naast de verplichte foto's van vóór en na is dit het
							// vak voor de rest (factuur van een andere leverancier e.d.).
							if ( $is_onderdeel && 'fotos' === $naam ) {
								$label = HDP_I18N::t( 'garantie_veld_fotos_extra' );
							}
							$breed     = in_array( $veld['type'], array( 'textarea', 'file', 'regels' ), true );
							$veld_id   = 'hdp-' . $soort . '-' . $naam;
							$verplicht = $veld['verplicht'];
							?>
							<?php
							// Na een afgekeurde invoer staat hier weer wat de
							// dealer al had ingetypt; een lang klachtverhaal
							// opnieuw moeten typen is het verschil tussen "even
							// opnieuw" en "laat maar".
							$eerder = HDP_Garantie_Formulier::eerder( 'fotos' === $naam ? '' : $naam );
							?>
							<?php // Een div en geen p: de onderdelenregels zijn blokelementen, en die sluiten een <p> stilzwijgend af — de regels en de knop belandden dan als losse vakjes in het raster. ?>
							<div class="hdp-veld<?php echo $breed ? ' hdp-veld-breed' : ''; ?><?php echo 'checkbox' === $veld['type'] ? ' hdp-veld-vinkje' : ''; ?>"<?php echo ! empty( $veld['met_onderdeel'] ) ? ' data-met-onderdeel="1"' : ''; ?><?php echo ! empty( $veld['alleen_merk'] ) ? ' data-alleen-merk="' . esc_attr( $veld['alleen_merk'] ) . '" hidden' : ''; ?>>
								<label for="<?php echo esc_attr( $veld_id ); ?>">
									<?php echo esc_html( $label ); ?>
									<?php if ( $verplicht ) : ?>
										<span class="hdp-veld-verplicht" aria-hidden="true">*</span>
									<?php endif; ?>
								</label>
								<?php if ( 'textarea' === $veld['type'] ) : ?>
									<textarea id="<?php echo esc_attr( $veld_id ); ?>" name="<?php echo esc_attr( $naam ); ?>" rows="3" <?php echo $verplicht ? 'required' : ''; ?>><?php echo esc_textarea( $eerder ); ?></textarea>
								<?php elseif ( 'file' === $veld['type'] ) : ?>
									<?php
									// De vaste verplichte documenten hebben elk hun eigen veld;
									// "fotos"/"bijlagen" is het vak voor de rest.
									$bestandsveld = in_array( $naam, array( 'fotos', 'bijlagen' ), true ) ? ( 'machine' === $soort ? 'bijlagen' : 'fotos' ) : $naam;
									// Een veld voor één merk (QI) maakt het script pas verplicht bij dat merk.
									$nu_verplicht = $verplicht && empty( $veld['alleen_merk'] );
									?>
									<input type="file" id="<?php echo esc_attr( $veld_id ); ?>" name="<?php echo esc_attr( $bestandsveld ); ?>[]" multiple
										accept="<?php echo esc_attr( self::toegestane_bestanden() ); ?>" <?php echo $nu_verplicht ? 'required' : ''; ?>>
									<?php if ( in_array( $naam, array( 'fotos', 'bijlagen' ), true ) ) : ?>
										<span class="hdp-veld-hint"><?php echo esc_html( HDP_I18N::t( 'claim' === $soort ? ( $is_onderdeel ? 'garantie_fotos_extra_hint' : 'garantie_fotos_hint' ) : 'garantie_bijlagen_hint' ) ); ?></span>
									<?php endif; ?>
								<?php elseif ( 'regels' === $veld['type'] ) : ?>
									<?php self::render_claimregels( HDP_Garantie_Formulier::eerder( 'regels', array() ), ! empty( $veld['uren'] ) ); ?>
								<?php elseif ( 'probleem' === $veld['type'] ) : ?>
									<select id="<?php echo esc_attr( $veld_id ); ?>" name="<?php echo esc_attr( $naam ); ?>" required>
										<option value=""><?php echo esc_html( HDP_I18N::t( 'garantie_kies' ) ); ?></option>
										<?php foreach ( HDP_Garantie::ONDERDEEL_PROBLEMEN as $probleem ) : ?>
											<option value="<?php echo esc_attr( $probleem ); ?>" <?php selected( $eerder, $probleem ); ?>><?php echo esc_html( HDP_I18N::t( 'garantie_probleem_' . $probleem ) ); ?></option>
										<?php endforeach; ?>
									</select>
								<?php elseif ( 'checkbox' === $veld['type'] ) : ?>
									<input type="checkbox" id="<?php echo esc_attr( $veld_id ); ?>" name="<?php echo esc_attr( $naam ); ?>" value="1" <?php checked( (bool) $eerder ); ?> <?php echo $verplicht ? 'required' : ''; ?>>
								<?php elseif ( 'uren' === $veld['type'] ) : ?>
									<input type="number" min="0" step="0.25" id="<?php echo esc_attr( $veld_id ); ?>" name="<?php echo esc_attr( $naam ); ?>"
										value="<?php echo esc_attr( $eerder ); ?>">
								<?php elseif ( 'merk' === $veld['type'] ) : ?>
									<select id="<?php echo esc_attr( $veld_id ); ?>" name="<?php echo esc_attr( $naam ); ?>" required>
										<option value=""><?php echo esc_html( HDP_I18N::t( 'garantie_kies' ) ); ?></option>
										<?php foreach ( HDP_Merken::lijst() as $merk ) : ?>
											<option value="<?php echo esc_attr( $merk ); ?>" <?php selected( $eerder, $merk ); ?>><?php echo esc_html( $merk ); ?></option>
										<?php endforeach; ?>
									</select>
								<?php else : ?>
									<?php // Een aankoopdatum ligt nooit in de toekomst; een tikfout als 2062 gaf een garantie die decennia doorliep. ?>
									<input type="<?php echo esc_attr( $veld['type'] ); ?>" id="<?php echo esc_attr( $veld_id ); ?>" name="<?php echo esc_attr( $naam ); ?>"
										value="<?php echo esc_attr( $eerder ); ?>" <?php echo $verplicht ? 'required' : ''; ?><?php echo 'date' === $veld['type'] ? ' max="' . esc_attr( current_time( 'Y-m-d' ) ) . '"' : ''; ?><?php echo ! empty( $veld['lengte'] ) ? ' minlength="' . (int) $veld['lengte'] . '" maxlength="' . (int) $veld['lengte'] . '" autocomplete="off" title="' . esc_attr( sprintf( HDP_I18N::t( 'garantie_fout_factuurnummer' ), (int) $veld['lengte'] ) ) . '"' : ''; ?>>
								<?php endif; ?>
								<?php if ( ! empty( $veld['hint'] ) ) : ?>
									<span class="hdp-veld-hint"><?php echo esc_html( HDP_I18N::t( 'garantie_veld_' . $naam . '_hint' ) ); ?></span>
								<?php endif; ?>
							</div>
						<?php endforeach; ?>
					</div>

					<p class="hdp-garantie-verzenden">
						<button type="submit" class="hdp-btn"><?php echo esc_html( HDP_I18N::t( 'garantie_ingang_' . $soort . '_knop' ) ); ?></button>
						<a class="hdp-btn hdp-btn-secundair" href="<?php echo esc_url( home_url( '/garantie/' ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'garantie_annuleren' ) ); ?></a>
					</p>
				</form>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * De grenzen en meldingen voor de controle vóór het versturen (zie
	 * dealerportaal.js).
	 *
	 * Wat de server afkeurt, kwam pas na een volledige retour terug — en
	 * dan waren de gekozen foto's weg. Bij een te grote verzending was het
	 * nog erger: dan kwam de dealer zonder enige melding op een leeg
	 * formulier terug. Dezelfde regels hier in de browser vangen dat af
	 * voordat er iets verstuurd wordt. De server blijft gewoon controleren.
	 *
	 * @return string HTML-attributen.
	 */
	private static function controle_attributen() {
		// Wat de server in één verzending aankan, met wat ruimte voor de
		// tekstvelden; en nooit meer dan wat we per bestand toestaan maal het
		// aantal.
		$server = wp_convert_hr_to_bytes( ini_get( 'post_max_size' ) );
		$totaal = $server > 0 ? (int) ( $server * 0.95 ) : HDP_Garantie::BIJLAGE_MAX * HDP_Garantie::BIJLAGE_AANTAL;
		$totaal = min( $totaal, HDP_Garantie::BIJLAGE_MAX * HDP_Garantie::BIJLAGE_AANTAL );

		$attributen = array(
			'data-hdp-controle'   => '1',
			'data-max-aantal'     => (string) HDP_Garantie::BIJLAGE_AANTAL,
			'data-max-bestand'    => (string) HDP_Garantie::BIJLAGE_MAX,
			'data-max-totaal'     => (string) $totaal,
			'data-melding-aantal' => sprintf( HDP_I18N::t( 'garantie_js_te_veel' ), HDP_Garantie::BIJLAGE_AANTAL ),
			'data-melding-bestand' => sprintf( HDP_I18N::t( 'garantie_js_bestand_te_groot' ), '%s', size_format( HDP_Garantie::BIJLAGE_MAX ) ),
			'data-melding-totaal' => sprintf( HDP_I18N::t( 'garantie_js_totaal_te_groot' ), size_format( $totaal ) ),
			'data-melding-uren'   => HDP_I18N::t( 'garantie_fout_uren_per_regel' ),
			'data-melding-factuur' => HDP_I18N::t( 'garantie_fout_factuur_nodig' ),
		);

		$html = '';
		foreach ( $attributen as $naam => $waarde ) {
			$html .= ' ' . $naam . '="' . esc_attr( $waarde ) . '"';
		}

		return $html;
	}

	/** Welke bestandstypen het uploadveld aanbiedt. */
	private static function toegestane_bestanden() {
		return '.' . implode( ',.', HDP_Garantie::BIJLAGE_TYPES );
	}

	/**
	 * Een claim gaat altijd over een aangemelde machine. Die kiezen scheelt
	 * de dealer het overtypen van serienummer en aankoopdatum, én het
	 * voorkomt tikfouten in precies het veld waar de fabrikant op controleert.
	 */
	private static function render_machinekeuze( $machines = null ) {
		// De lijst mag meegegeven worden; dan is dit scherm te beproeven
		// zonder de claimadministratie aan te spreken.
		if ( null === $machines ) {
			$machines = HDP_Garantie::machines();
		}
		if ( is_wp_error( $machines ) ) {
			$machines = array();
		}
		?>
		<?php
		// Een dealer met honderd machines kan in een keuzelijst van honderd
		// regels niets vinden: de typehulp van de browser zoekt alleen vanaf
		// het begin van de regel, dus op serienummer werkt die niet. Vanaf
		// een stuk of acht komt er daarom een zoekveld boven dat de lijst
		// meteen uitdunt. Bij een handvol machines zou dat alleen in de weg
		// staan.
		$veel = count( $machines ) >= 8;
		?>
		<div class="hdp-veld hdp-veld-breed hdp-machinekeuze">
			<label for="hdp-claim-machinekeuze">
				<?php echo esc_html( HDP_I18N::t( 'garantie_kies_machine' ) ); ?>
				<span class="hdp-veld-verplicht" aria-hidden="true">*</span>
			</label>
			<?php if ( $veel ) : ?>
				<input type="search" class="hdp-machinezoek" id="hdp-machinezoek"
					placeholder="<?php echo esc_attr( HDP_I18N::t( 'garantie_machine_zoek' ) ); ?>"
					aria-label="<?php echo esc_attr( HDP_I18N::t( 'garantie_machine_zoek' ) ); ?>"
					autocomplete="off">
			<?php endif; ?>
			<?php
			// Voorgekozen: na een afgekeurde invoer wat er stond, anders de
			// machine waarvan de dealer op "Claim indienen" klikte.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- alleen een voorkeuze; bij indienen wordt de machine opnieuw binnen de eigen machines opgezocht.
			$vooraf        = isset( $_GET['machine'] ) ? sanitize_text_field( wp_unslash( $_GET['machine'] ) ) : '';
			$gekozen       = HDP_Garantie_Formulier::eerder( 'machine', $vooraf );
			$niet_kiesbaar = false;
			?>
			<select id="hdp-claim-machinekeuze" name="machinekeuze" required<?php echo $veel ? ' size="8"' : ''; ?>>
				<option value=""><?php echo esc_html( HDP_I18N::t( 'garantie_kies' ) ); ?></option>
				<?php foreach ( $machines as $machine ) : ?>
					<?php
					// Het serienummer is de waarde: de machine wordt bij het
					// indienen opgezocht binnen de eigen machines, zodat je
					// niet op andermans machine kunt claimen.
					//
					// Een afgewezen machine of een machine met verlopen
					// garantie staat er wel bij — anders zoekt de dealer zich
					// suf naar een machine die "weg" is — maar is niet te
					// kiezen. Eerder weigerde de database pas nádat het hele
					// formulier was ingevuld, en verlopen werd niet eens
					// tegengehouden.
					$waarom = HDP_Garantie::waarom_niet_claimbaar( $machine );
					$stand  = HDP_Garantie::garantiestand( $machine );
					if ( $waarom ) {
						$niet_kiesbaar = true;
					}
					?>
					<option value="<?php echo esc_attr( $machine['serienummer'] ); ?>" <?php selected( $waarom ? '' : $gekozen, $machine['serienummer'] ); ?><?php disabled( (bool) $waarom ); ?>>
						<?php echo esc_html( HDP_Garantie::machinenaam( $machine['machine'], $machine['serienummer'] ) ); ?>
						<?php if ( ! empty( $machine['klant'] ) ) : ?>
							<?php // De klantnaam erbij: wie honderd machines heeft, zoekt meestal "die van Jansen" en niet een serienummer uit zijn hoofd. ?>
							&middot; <?php echo esc_html( $machine['klant'] ); ?>
						<?php endif; ?>
						<?php if ( 'afgewezen' === $waarom ) : ?>
							— <?php echo esc_html( HDP_I18N::t( 'garantie_machine_afgewezen' ) ); ?>
						<?php elseif ( 'in_behandeling' === $waarom ) : ?>
							— <?php echo esc_html( HDP_I18N::t( 'garantie_machine_in_behandeling' ) ); ?>
						<?php elseif ( 'verlopen' === $waarom ) : ?>
							— <?php echo esc_html( sprintf( HDP_I18N::t( 'garantie_verlopen_op' ), HDP_I18N::datum( 'j M Y', strtotime( $stand['tot'] ) ) ) ); ?>
						<?php else : ?>
							<?php if ( 'goedgekeurd' !== $machine['status'] ) : ?>
								— <?php echo esc_html( HDP_I18N::t( 'garantie_machine_' . $machine['status'] ) ); ?>
							<?php endif; ?>
							<?php if ( $stand['tot'] ) : ?>
								&middot; <?php echo esc_html( sprintf( HDP_I18N::t( 'garantie_tot_en_met' ), HDP_I18N::datum( 'j M Y', strtotime( $stand['tot'] ) ) ) ); ?>
							<?php endif; ?>
						<?php endif; ?>
					</option>
				<?php endforeach; ?>
			</select>
			<span class="hdp-veld-hint">
				<?php if ( $niet_kiesbaar ) : ?>
					<?php echo esc_html( HDP_I18N::t( 'garantie_machine_niet_kiesbaar' ) ); ?>
				<?php endif; ?>
				<?php echo esc_html( HDP_I18N::t( 'garantie_kies_machine_hint' ) ); ?>
				<a href="<?php echo esc_url( add_query_arg( 'nieuw', 'machine', home_url( '/garantie/' ) ) ); ?>">
					<?php echo esc_html( HDP_I18N::t( 'garantie_ingang_machine_knop' ) ); ?>
				</a>
			</span>
		</div>
		<?php if ( $veel ) : ?>
		<script>
		(function () {
			var zoek  = document.getElementById( 'hdp-machinezoek' );
			var lijst = document.getElementById( 'hdp-claim-machinekeuze' );
			if ( ! zoek || ! lijst ) { return; }

			var opties = Array.prototype.slice.call( lijst.options );

			zoek.addEventListener( 'input', function () {
				var term = zoek.value.trim().toLowerCase();

				opties.forEach( function ( optie, i ) {
					// De eerste optie is "maak een keuze" en blijft staan,
					// anders heeft de lijst even geen geldige waarde.
					if ( 0 === i || '' === term ) {
						optie.hidden = false;
						return;
					}
					optie.hidden = -1 === optie.text.toLowerCase().indexOf( term );
				} );

				// Staat de gekozen machine er niet meer bij, dan hoort de
				// keuze losgelaten te worden; anders verstuurt het formulier
				// iets dat je niet meer ziet staan.
				if ( lijst.selectedOptions.length && lijst.selectedOptions[0].hidden ) {
					lijst.selectedIndex = 0;
				}
			} );
		})();
		</script>
		<?php endif; ?>
		<?php
	}

	// --- Eén ticket -----------------------------------------------------

	/**
	 * Het detailscherm van één garantieticket.
	 *
	 * Opzet als werkblad en niet als document: links het gesprek, dat leeft
	 * en groeit, rechts een kolom met de feiten die blijft staan terwijl je
	 * leest. Eerder stond alles onder elkaar, waardoor je bij elk nieuw
	 * bericht verder moest scrollen om te zien wélke machine het ook alweer
	 * was — en stond "dit ticket wacht op u" onderaan de pagina in plaats
	 * van bovenaan.
	 */
	private static function render_ticket( $nummer ) {
		$claim = HDP_Garantie::claim_voor_dealer( $nummer );

		if ( is_wp_error( $claim ) ) {
			ob_start();
			?>
			<div class="hdp-portaal alignfull">
				<section class="hdp-garantie">
					<?php self::render_storing(); ?>
				</section>
			</div>
			<?php
			return ob_get_clean();
		}

		if ( ! $claim ) {
			return self::render_onbekend_ticket();
		}

		ob_start();
		?>
		<div class="hdp-portaal alignfull">
			<section class="hdp-welkom alignfull hdp-welkom-zonder-hero">
				<div class="hdp-welkom-inner">
					<a class="hdp-terug-boven" href="<?php echo esc_url( home_url( '/garantie/' ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'garantie_terug_naar_overzicht' ) ); ?></a>
				</div>
			</section>
			<section class="hdp-garantie hdp-ticket">
				<?php self::render_meldingen(); ?>
				<?php self::render_ticket_kop( $claim ); ?>

				<div class="hdp-ticket-werkblad">
					<div class="hdp-ticket-gesprek">
						<?php self::render_gesprek( $claim ); ?>
					</div>
					<aside class="hdp-ticket-zijkolom">
						<?php self::render_aan_zet( $claim ); ?>
						<?php if ( ! HDP_Garantie::is_onderdeelclaim( $claim ) ) : ?>
							<?php // Een onderdelenclaim hangt niet aan een machine; hier stond een lege machinekaart. ?>
							<?php self::render_machinekaart( $claim ); ?>
						<?php endif; ?>
						<?php self::render_claimgegevens( $claim ); ?>
						<?php self::render_bijlagen( $claim ); ?>
					</aside>
				</div>

			</section>
		</div>
		<?php
		return ob_get_clean();
	}

	private static function render_onbekend_ticket() {
		ob_start();
		?>
		<div class="hdp-portaal alignfull">
			<section class="hdp-garantie">
				<?php
				echo HDP_Icons::render_lege_status( 'downloads', HDP_I18N::t( 'garantie_ticket_onbekend' ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- render_lege_status() escapet zelf.
				?>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * De kop: om welke machine gaat het, waar staat het ticket, en hoelang
	 * al. Met daaronder de route als balk over de volle breedte — als
	 * verticale lijst gebruikte die een kleine kolom links en bleef de rest
	 * van de band leeg.
	 */
	private static function render_ticket_kop( $claim ) {
		$dagen = HDP_Garantie::dagen_in_status( $claim );
		?>
		<header class="hdp-ticket-kop">
			<div class="hdp-ticket-kop-boven">
				<div class="hdp-ticket-kop-tekst">
					<p class="hdp-ticket-eyebrow">
						<?php echo esc_html( HDP_I18N::t( 'garantie_ticket' ) ); ?>
						<?php echo esc_html( $claim['nummer'] ); ?>
					</p>
					<h2><?php echo esc_html( HDP_Garantie::claimtitel( $claim ) ); ?></h2>
					<p class="hdp-ticket-meta">
						<?php
						// Een onderdelenclaim heeft geen merk; daar stond een los
						// puntje vooraan de regel.
						$soortnaam = HDP_Garantie::is_onderdeelclaim( $claim )
							? HDP_I18N::t( 'garantie_soort_onderdeel_titel' )
							: $claim['merk'];
						?>
						<?php if ( $soortnaam ) : ?>
							<?php echo esc_html( $soortnaam ); ?> &middot;
						<?php endif; ?>
						<?php echo esc_html( HDP_I18N::t( 'garantie_ingediend_op' ) ); ?>
						<?php echo esc_html( HDP_I18N::datum( 'j F Y', strtotime( $claim['ingediend_op'] ) ) ); ?>
						<?php if ( $dagen > 0 ) : ?>
							&middot;
							<?php
							// Hoelang een ticket al op dezelfde status staat is
							// precies wat je wilt weten en stond nergens.
							echo '<strong>' . esc_html(
								1 === $dagen
									? HDP_I18N::t( 'garantie_dag_in_status' )
									: sprintf( HDP_I18N::t( 'garantie_dagen_in_status' ), $dagen )
							) . '</strong>';
							?>
						<?php endif; ?>
					</p>
				</div>
				<span class="<?php echo esc_attr( HDP_Garantie::status_klasse( $claim['status'] ) ); ?> hdp-status-groot">
					<?php echo esc_html( HDP_Garantie::status_label( $claim['status'] ) ); ?>
				</span>
			</div>
			<?php self::render_route( $claim ); ?>
			<?php
			// Wachten we op een pakket, dan hoort dat hier te staan en niet
			// alleen in de bevestiging die de dealer één keer ziet.
			if ( HDP_Garantie::onderdeel_verwacht( $claim ) && empty( $claim['onderdeel_ontvangen_op'] ) ) {
				self::render_retour_attentie( $claim['nummer'] );
			}
			?>
		</header>
		<?php
	}

	/**
	 * De route als horizontale balk: welke stappen zijn gezet, waar ligt het
	 * nu, en wat komt er nog.
	 */
	private static function render_route( $claim ) {
		$stappen = HDP_Garantie::route( $claim );
		?>
		<ol class="hdp-route">
			<?php foreach ( $stappen as $stap ) : ?>
				<?php
				$klassen = array( 'hdp-route-stap' );
				if ( $stap['gedaan'] ) {
					$klassen[] = 'hdp-route-gedaan';
				}
				if ( $stap['huidig'] ) {
					$klassen[] = 'hdp-route-huidig';
				}
				if ( $stap['huidig'] && HDP_Garantie::is_afwijzing( $stap['status'] ) ) {
					$klassen[] = 'hdp-route-afwijzing';
				}
				if ( ! $stap['status'] && 'retour' !== $stap['fase'] ) {
					$klassen[] = 'hdp-route-komt-nog';
				}
				if ( ! empty( $stap['wacht'] ) ) {
					// Waar we op wachten mag opvallen; dat is hier het hele punt.
					$klassen[] = 'hdp-route-wacht';
				}

				if ( $stap['status'] ) {
					$label = HDP_Garantie::status_label( $stap['status'] );
				} elseif ( 'retour' === $stap['fase'] ) {
					$label = HDP_I18N::t( empty( $stap['wacht'] ) ? 'garantie_retour_ontvangen' : 'garantie_retour_onderweg' );
				} else {
					$label = HDP_I18N::t( 'garantie_fase_' . $stap['fase'] );
				}
				?>
				<li class="<?php echo esc_attr( implode( ' ', $klassen ) ); ?>">
					<span class="hdp-route-bol" aria-hidden="true"></span>
					<strong><?php echo esc_html( $label ); ?></strong>
					<span class="hdp-route-wie">
						<?php
						// Een datum hoorde alleen te verschijnen als er ook
						// een naam bij stond. Bij "onderdeel ontvangen" is er
						// geen naam — er kwam een pakket binnen — en dan viel
						// de datum weg, terwijl die juist het punt is.
						$heeft_iets = $stap['wie'] || $stap['wanneer'] || ! empty( $stap['hint'] );
						?>
						<?php if ( ! $heeft_iets ) : ?>
							&nbsp;
						<?php else : ?>
							<?php if ( ! empty( $stap['hint'] ) ) : ?>
								<?php echo esc_html( $stap['hint'] ); ?>
							<?php else : ?>
								<?php echo esc_html( $stap['wie'] ); ?>
								<?php
								if ( $stap['wie'] && $stap['wanneer'] ) :
									?>
									&middot;<?php endif; ?>
								<?php if ( $stap['wanneer'] ) : ?>
									<?php echo esc_html( HDP_I18N::datum( 'j M H:i', strtotime( $stap['wanneer'] ) ) ); ?>
								<?php endif; ?>
							<?php endif; ?>
						<?php endif; ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ol>
		<?php
	}

	/** Wie er aan zet is, bovenaan de zijkolom in plaats van onderaan de pagina. */
	private static function render_aan_zet( $claim ) {
		$bij = HDP_Garantie::ligt_bij( $claim );

		if ( ! $bij ) {
			self::render_besluit( $claim );
			return;
		}

		if ( 'homburg' === $bij ) {
			?>
			<p class="hdp-ticket-ligtbij"><?php echo esc_html( HDP_I18N::t( 'garantie_ligt_bij_homburg' ) ); ?></p>
			<?php
			return;
		}

		// De dealer is aan zet: dat verdient een eigen blok met de vraag
		// erbij en een knop ernaartoe.
		$vraag = self::laatste_vraag( $claim );
		?>
		<div class="hdp-ticket-actie">
			<strong><?php echo esc_html( HDP_I18N::t( 'garantie_ligt_bij_u' ) ); ?></strong>
			<?php if ( $vraag ) : ?>
				<p><?php echo esc_html( $vraag ); ?></p>
			<?php endif; ?>
			<a class="hdp-btn" href="#hdp-ticket-antwoord"><?php echo esc_html( HDP_I18N::t( 'garantie_antwoord_label' ) ); ?></a>
		</div>
		<?php
	}

	/**
	 * Het besluit op een afgeronde claim, bovenaan de zijkolom.
	 *
	 * Hier stond alleen "deze claim is afgehandeld". Wát het besluit was en
	 * waarom — vooral bij een afwijzing — moest de dealer zelf uit het
	 * gesprek opdiepen. Nu staat het besluit hier in zijn eigen kleur, met de
	 * laatste toelichting van Homburg erbij.
	 */
	private static function render_besluit( $claim ) {
		$afwijzing   = HDP_Garantie::is_afwijzing( $claim['status'] );
		$toelichting = self::laatste_vraag( $claim );
		?>
		<div class="hdp-ticket-besluit <?php echo $afwijzing ? 'hdp-ticket-besluit-afgewezen' : 'hdp-ticket-besluit-goedgekeurd'; ?>">
			<span class="hdp-ticket-besluit-kop"><?php echo esc_html( HDP_I18N::t( 'garantie_besluit_kop' ) ); ?></span>
			<strong><?php echo esc_html( HDP_Garantie::status_label( $claim['status'] ) ); ?></strong>
			<?php if ( $toelichting ) : ?>
				<p><?php echo esc_html( $toelichting ); ?></p>
			<?php endif; ?>
			<p class="hdp-ticket-besluit-vraag"><?php echo esc_html( HDP_I18N::t( 'garantie_besluit_vragen' ) ); ?></p>
		</div>
		<?php
	}

	/** Het laatste bericht van Homburg: de vraag in het "jij bent aan zet"-blok, of de toelichting bij een besluit. */
	private static function laatste_vraag( $claim ) {
		$berichten = HDP_Garantie::berichten( $claim );

		for ( $i = count( $berichten ) - 1; $i >= 0; $i-- ) {
			if ( 'homburg' === $berichten[ $i ]['afzender'] ) {
				return $berichten[ $i ]['tekst'];
			}
		}

		return '';
	}

	/**
	 * De machine waar deze claim over gaat, met hoelang de garantie nog
	 * loopt en hoeveel claims er eerder op liepen. Die gegevens stonden er
	 * al in de administratie maar werden nergens getoond.
	 */
	private static function render_machinekaart( $claim ) {
		$stand   = HDP_Garantie::garantiestand(
			array(
				'aankoopdatum' => $claim['aankoopdatum'],
				'garantie_tot' => isset( $claim['garantie_tot'] ) ? $claim['garantie_tot'] : '',
			)
		);
		$anderen = array_filter(
			HDP_Garantie::claims_van_machine( $claim['serienummer'] ),
			static function ( $ander ) use ( $claim ) {
				return $ander['nummer'] !== $claim['nummer'];
			}
		);

		$balk = 'hdp-machine-balk';
		if ( $stand['verlopen'] ) {
			$balk .= ' hdp-machine-balk-verlopen';
		} elseif ( $stand['bijna'] ) {
			$balk .= ' hdp-machine-balk-bijna';
		}
		?>
		<section class="hdp-ticket-kaart">
			<h3><?php echo esc_html( HDP_I18N::t( 'garantie_veld_machine' ) ); ?></h3>
			<div class="hdp-ticket-kaart-body">
				<strong class="hdp-ticket-machinenaam"><?php echo esc_html( $claim['machine'] ); ?></strong>
				<span class="hdp-ticket-serienummer"><?php echo esc_html( $claim['serienummer'] ); ?></span>

				<?php if ( $stand['tot'] ) : ?>
					<span class="hdp-machine-garantie-label">
						<?php if ( $stand['verlopen'] ) : ?>
							<?php echo esc_html( sprintf( HDP_I18N::t( 'garantie_verlopen_op' ), HDP_I18N::datum( 'j M Y', strtotime( $stand['tot'] ) ) ) ); ?>
						<?php else : ?>
							<?php echo esc_html( sprintf( HDP_I18N::t( 'garantie_tot_en_met' ), HDP_I18N::datum( 'j M Y', strtotime( $stand['tot'] ) ) ) ); ?>
							<?php if ( $stand['bijna'] ) : ?>
								<strong>&mdash; <?php echo esc_html( sprintf( HDP_I18N::t( 'garantie_nog_maanden' ), $stand['maanden'] ) ); ?></strong>
							<?php endif; ?>
						<?php endif; ?>
					</span>
					<span class="<?php echo esc_attr( $balk ); ?>"><i style="width:<?php echo esc_attr( (string) $stand['verstreken'] ); ?>%"></i></span>
				<?php endif; ?>

				<dl class="hdp-ticket-feiten">
					<?php if ( $claim['aankoopdatum'] ) : ?>
						<div>
							<dt><?php echo esc_html( HDP_I18N::t( 'garantie_veld_aankoopdatum' ) ); ?></dt>
							<dd><?php echo esc_html( HDP_I18N::datum( 'j M Y', strtotime( $claim['aankoopdatum'] ) ) ); ?></dd>
						</div>
					<?php endif; ?>
					<?php if ( $claim['hectares'] ) : ?>
						<div>
							<dt><?php echo esc_html( HDP_I18N::t( 'garantie_veld_hectares' ) ); ?></dt>
							<dd><?php echo esc_html( $claim['hectares'] ); ?></dd>
						</div>
					<?php endif; ?>
					<?php if ( $anderen ) : ?>
						<div>
							<dt><?php echo esc_html( HDP_I18N::t( 'garantie_andere_claims' ) ); ?></dt>
							<dd><?php echo esc_html( (string) count( $anderen ) ); ?></dd>
						</div>
					<?php endif; ?>
				</dl>

				<?php if ( 'goedgekeurd' !== $claim['machine_status'] && $claim['machine_status'] ) : ?>
					<div class="hdp-machine-beoordeling hdp-machine-beoordeling-<?php echo esc_attr( $claim['machine_status'] ); ?>">
						<strong><?php echo esc_html( HDP_I18N::t( 'garantie_machine_' . $claim['machine_status'] ) ); ?></strong>
						<?php if ( ! empty( $claim['machine_reden'] ) ) : ?>
							<span><?php echo esc_html( $claim['machine_reden'] ); ?></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}

	/** Wat de dealer bij het indienen heeft opgegeven. */
	private static function render_claimgegevens( $claim ) {
		?>
		<section class="hdp-ticket-kaart">
			<h3><?php echo esc_html( HDP_I18N::t( 'garantie_claimgegevens' ) ); ?></h3>
			<div class="hdp-ticket-kaart-body">
				<?php $is_onderdeel = HDP_Garantie::is_onderdeelclaim( $claim ); ?>
				<dl class="hdp-ticket-klacht">
					<?php if ( $is_onderdeel && ! empty( $claim['onderdeel_probleem'] ) && in_array( $claim['onderdeel_probleem'], HDP_Garantie::ONDERDEEL_PROBLEMEN, true ) ) : ?>
						<?php // Wat er mis was met de levering kozen dealers wel, maar het stond nergens op hun ticket. ?>
						<dt><?php echo esc_html( HDP_I18N::t( 'garantie_veld_onderdeel_probleem' ) ); ?></dt>
						<dd><?php echo esc_html( HDP_I18N::t( 'garantie_probleem_' . $claim['onderdeel_probleem'] ) ); ?></dd>
					<?php endif; ?>
					<?php // Zelfde naam als op het formulier: bij een onderdelenclaim is "defect" vaak niet het goede woord. ?>
					<dt><?php echo esc_html( HDP_I18N::t( $is_onderdeel ? 'garantie_veld_klacht_onderdeel' : 'garantie_veld_klacht' ) ); ?></dt>
					<dd><?php echo esc_html( $claim['klacht'] ); ?></dd>
					<?php if ( $claim['onderdelen'] ) : ?>
						<dt><?php echo esc_html( HDP_I18N::t( 'garantie_veld_onderdelen' ) ); ?></dt>
						<dd><?php echo esc_html( $claim['onderdelen'] ); ?></dd>
					<?php endif; ?>
					<?php foreach ( array( 'factuurnummer', 'klantreferentie', 'opmerkingen' ) as $veld ) : ?>
						<?php if ( ! empty( $claim[ $veld ] ) ) : ?>
							<dt><?php echo esc_html( HDP_I18N::t( 'garantie_veld_' . $veld ) ); ?></dt>
							<dd><?php echo esc_html( $claim[ $veld ] ); ?></dd>
						<?php endif; ?>
					<?php endforeach; ?>
				</dl>

				<?php self::render_ingediende_regels( $claim ); ?>
			</div>
		</section>
		<?php
	}

	/**
	 * De onderdelen die de dealer bij het indienen heeft opgegeven.
	 *
	 * Stonden er niet: het portaal schreef ze wel weg maar las ze nooit
	 * terug. Een dealer vulde onderdeelnummers, bedragen en uren in en zag
	 * daar op zijn eigen ticket niets van — en kon dus ook niet nagaan of
	 * hij het goed had ingevuld.
	 *
	 * @param array $claim De claim.
	 */
	private static function render_ingediende_regels( $claim ) {
		$regels = isset( $claim['regels'] ) ? $claim['regels'] : array();
		if ( ! $regels ) {
			return;
		}

		$met_uren = false;
		$totaal   = 0;
		foreach ( $regels as $regel ) {
			if ( null !== $regel['uren'] ) {
				$met_uren = true;
			}
			$totaal += (int) $regel['bedrag_cent'] * max( 1, (int) $regel['aantal'] );
		}

		$uren = HDP_Garantie::tel_uren_op( $regels );
		?>
		<div class="hdp-ticket-regels<?php echo $met_uren ? ' hdp-ticket-regels-uren' : ''; ?>">
			<h4><?php echo esc_html( HDP_I18N::t( 'garantie_veld_regels' ) ); ?></h4>

			<?php
			// Per onderdeel één regel: het nummer, en daaronder de rest met
			// een woord ervoor. Dit stond als tabel met vijf kolommen in de
			// smalle zijkolom van het ticket; de koppen liepen over elkaar en
			// bedrag en uren plakten aan elkaar ("€ 251,271,5").
			?>
			<?php foreach ( $regels as $regel ) : ?>
				<?php
				$delen = array( HDP_I18N::t( 'garantie_regel_aantal' ) . ' ' . (int) $regel['aantal'] );
				if ( null !== $regel['bedrag_cent'] ) {
					$delen[] = '€ ' . number_format( $regel['bedrag_cent'] / 100, 2, ',', '.' );
				}
				if ( $met_uren && null !== $regel['uren'] ) {
					$delen[] = HDP_I18N::t( 'garantie_regel_uren' ) . ' ' . rtrim( rtrim( number_format( $regel['uren'], 2, ',', '' ), '0' ), ',' );
				}
				$delen[] = HDP_I18N::t( 'garantie_regel_herkomst' ) . ': ' . HDP_I18N::t( $regel['homburg_factuur'] ? 'garantie_regel_homburg' : 'garantie_regel_derden' );
				?>
				<div class="hdp-ticket-regel">
					<span class="hdp-ticket-regel-nummer"><?php echo esc_html( $regel['nummer'] ); ?></span>
					<span class="hdp-ticket-regel-details"><?php echo esc_html( implode( ' · ', $delen ) ); ?></span>
				</div>
			<?php endforeach; ?>

			<p class="hdp-ticket-regels-totaal">
				<?php echo esc_html( sprintf( HDP_I18N::t( 'garantie_regels_totaal' ), '€ ' . number_format( $totaal / 100, 2, ',', '.' ) ) ); ?>
				<?php if ( '' !== $uren ) : ?>
					<?php echo esc_html( sprintf( HDP_I18N::t( 'garantie_regels_totaal_uren' ), rtrim( rtrim( number_format( (float) $uren, 2, ',', '' ), '0' ), ',' ) ) ); ?>
				<?php endif; ?>
			</p>
		</div>
		<?php
	}

	private static function render_bijlagen( $claim ) {
		$bijlagen = isset( $claim['bijlagen'] ) ? $claim['bijlagen'] : array();
		?>
		<section class="hdp-ticket-kaart">
			<h3>
				<?php echo esc_html( HDP_I18N::t( 'garantie_bijlagen' ) ); ?>
				<span class="hdp-ticket-aantal"><?php echo esc_html( (string) count( $bijlagen ) ); ?></span>
			</h3>
			<div class="hdp-ticket-kaart-body">
				<?php if ( ! $bijlagen ) : ?>
					<p class="hdp-gesprek-leeg"><?php echo esc_html( HDP_I18N::t( 'garantie_geen_bijlagen' ) ); ?></p>
				<?php else : ?>
					<ul class="hdp-bijlagen">
						<?php foreach ( $bijlagen as $bijlage ) : ?>
							<?php
							// Een foto toont zichzelf als voorbeeldje; de rest
							// (pdf, heic) krijgt het icoon. Zonder link — als de
							// opslag even niet antwoordde — blijft de naam staan.
							$foto = $bijlage['url'] && HDP_Garantie::is_afbeelding( $bijlage['naam'] );
							?>
							<li class="hdp-bijlage">
								<?php if ( $bijlage['url'] ) : ?>
									<a class="hdp-bijlage-link" href="<?php echo esc_url( $bijlage['url'] ); ?>" target="_blank" rel="noopener noreferrer">
								<?php endif; ?>
								<?php if ( $foto ) : ?>
									<img class="hdp-bijlage-voorbeeld" src="<?php echo esc_url( $bijlage['url'] ); ?>" alt="" loading="lazy">
								<?php else : ?>
									<?php echo HDP_Icons::svg_icoon( 'bijlage' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG zonder gebruikersinvoer. ?>
								<?php endif; ?>
								<span class="hdp-bijlage-naam"><?php echo esc_html( $bijlage['naam'] ); ?></span>
								<?php if ( 'homburg' === $bijlage['door'] ) : ?>
									<span class="hdp-bijlage-van"><?php echo esc_html( HDP_I18N::t( 'garantie_bijlage_van_homburg' ) ); ?></span>
								<?php endif; ?>
								<?php if ( $bijlage['url'] ) : ?>
									</a>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				<?php
				// Een foto nasturen kon eerder niet: bijlagen gingen alleen
				// mee bij het indienen. Terwijl Homburg er juist vaak om
				// vraagt — daar liep het proces dood.
				?>
				<p class="hdp-bijlage-toevoegen">
					<a href="#hdp-ticket-antwoord"><?php echo esc_html( HDP_I18N::t( 'garantie_bijlage_toevoegen' ) ); ?></a>
				</p>
			</div>
		</section>
		<?php
	}

	/**
	 * Het gesprek bij dit ticket. Hier zit de transparantie: elke opmerking
	 * die Homburg in de app achterlaat staat hier voor de dealer. Berichten
	 * van beide kanten staan door elkaar, op volgorde van tijd.
	 */
	private static function render_gesprek( $claim ) {
		$berichten = HDP_Garantie::berichten( $claim );
		?>
		<section class="hdp-ticket-kaart hdp-gesprek">
			<h3>
				<?php echo esc_html( HDP_I18N::t( 'garantie_gesprek' ) ); ?>
				<span class="hdp-ticket-aantal"><?php echo esc_html( (string) count( $berichten ) ); ?></span>
			</h3>

			<?php if ( ! $berichten ) : ?>
				<p class="hdp-gesprek-leeg hdp-gesprek-leeg-ruim"><?php echo esc_html( HDP_I18N::t( 'garantie_gesprek_leeg' ) ); ?></p>
			<?php endif; ?>

			<?php foreach ( $berichten as $bericht ) : ?>
				<?php $van_dealer = 'dealer' === $bericht['afzender']; ?>
				<article class="hdp-bericht<?php echo $van_dealer ? ' hdp-bericht-dealer' : ''; ?>">
					<div class="hdp-bericht-kop">
						<span class="hdp-bericht-avatar<?php echo $van_dealer ? '' : ' hdp-bericht-avatar-homburg'; ?>" aria-hidden="true">
							<?php echo esc_html( self::initialen( $bericht['wie'] ) ); ?>
						</span>
						<strong><?php echo esc_html( $van_dealer ? HDP_I18N::t( 'garantie_van_dealer' ) : $bericht['wie'] ); ?></strong>
						<?php if ( $bericht['status'] ) : ?>
							<span class="<?php echo esc_attr( HDP_Garantie::status_klasse( $bericht['status'] ) ); ?>"><?php echo esc_html( HDP_Garantie::status_label( $bericht['status'] ) ); ?></span>
						<?php endif; ?>
						<?php if ( $bericht['wanneer'] ) : ?>
							<time><?php echo esc_html( HDP_I18N::datum( 'j M H:i', strtotime( $bericht['wanneer'] ) ) ); ?></time>
						<?php endif; ?>
					</div>
					<p class="hdp-bericht-tekst"><?php echo esc_html( $bericht['tekst'] ); ?></p>
				</article>
			<?php endforeach; ?>

			<form class="hdp-bericht-antwoord" id="hdp-ticket-antwoord" method="post" enctype="multipart/form-data"
				action="<?php echo esc_url( add_query_arg( 'ticket', $claim['nummer'], home_url( '/garantie/' ) ) ); ?>"
				<?php echo self::controle_attributen(); // phpcs:ignore WordPress.Security.EscapeOutput -- controle_attributen() escapet elke waarde zelf. ?>>
				<?php wp_nonce_field( HDP_Garantie_Formulier::NONCE ); ?>
				<input type="hidden" name="<?php echo esc_attr( HDP_Garantie_Formulier::ACTIE_VELD ); ?>" value="bericht">
				<input type="hidden" name="ticket" value="<?php echo esc_attr( $claim['nummer'] ); ?>">

				<label for="hdp-ticket-antwoord-tekst"><?php echo esc_html( HDP_I18N::t( 'garantie_antwoord_label' ) ); ?></label>
				<textarea id="hdp-ticket-antwoord-tekst" name="bericht" rows="3"
					placeholder="<?php echo esc_attr( HDP_I18N::t( 'garantie_antwoord_hint' ) ); ?>"><?php echo esc_textarea( HDP_Garantie_Formulier::eerder( 'bericht' ) ); ?></textarea>

				<div class="hdp-bericht-antwoord-knoppen">
					<button type="submit" class="hdp-btn"><?php echo esc_html( HDP_I18N::t( 'garantie_antwoord_versturen' ) ); ?></button>
					<label class="hdp-bericht-bijlage">
						<input type="file" name="bijlagen[]" multiple accept="<?php echo esc_attr( self::toegestane_bestanden() ); ?>">
						<span>
							<?php
							// svg_icoon() in plaats van render_icoon(): die laatste
							// wikkelt het icoon in een <div>, en dat mag niet
							// binnen een <span>.
							echo HDP_Icons::svg_icoon( 'bijlage' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG zonder gebruikersinvoer.
							?>
							<?php echo esc_html( HDP_I18N::t( 'garantie_bijlage_meesturen' ) ); ?>
						</span>
					</label>
				</div>
			</form>
		</section>
		<?php
	}

	/** Initialen voor het rondje bij een bericht. */
	private static function initialen( $naam ) {
		$delen     = preg_split( '/\s+/', trim( (string) $naam ) );
		$initialen = '';

		foreach ( $delen as $deel ) {
			if ( '' === $deel ) {
				continue;
			}
			// Tussenvoegsels als "van den" zeggen niets; die slaan we over.
			if ( mb_strtolower( $deel ) === $deel && count( $delen ) > 1 ) {
				continue;
			}
			$initialen .= mb_strtoupper( mb_substr( $deel, 0, 1 ) );
			if ( mb_strlen( $initialen ) >= 2 ) {
				break;
			}
		}

		return $initialen ? $initialen : '?';
	}
}
