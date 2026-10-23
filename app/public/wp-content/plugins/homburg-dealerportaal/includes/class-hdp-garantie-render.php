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
 *
 * De indienformulieren zijn nog ontwerp: de velden staan uit. Lezen gebeurt
 * al wel echt.
 */
class HDP_Garantie_Render {

	public static function render_garantie_pagina( $a ) {
		$mag_zien = is_user_logged_in() && HDP_Roles::mag_portaal_zien( get_current_user_id() );

		if ( ! $mag_zien ) {
			ob_start();
			HDP_Login::render_login( HDP_Login::login_foutmelding(), $a );
			return ob_get_clean();
		}

		// Een ticket opent normaal rechts in het overzicht, zodat de lijst
		// ernaast blijft staan. Het volledige scherm — met de route, de
		// machinekaart en de bijlagen, die breedte nodig hebben — is een
		// bewuste stap verder.
		//
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- alleen een keuze uit de URL om te bepalen wát getoond wordt; de toegangscontrole zit in HDP_Garantie::claim_voor_dealer() en in de rolcontrole hierboven.
		$gevraagd = isset( $_GET['ticket'] ) ? sanitize_text_field( wp_unslash( $_GET['ticket'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- zie hierboven.
		if ( '' !== $gevraagd && ! empty( $_GET['volledig'] ) ) {
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
	 * Het werkblad: filters links, claims in het midden, de gekozen claim
	 * rechts.
	 *
	 * Dit was een stapel losse secties onder elkaar — eerst twee kaarten,
	 * dan een zoekveld, dan de claims, dan de machines. Om één claim te
	 * lezen moest je weg van het overzicht en daarna terug, en na die
	 * terugweg stond je weer bovenaan. Dezelfde indeling als Contactpersonen
	 * in de Homburg App, zodat dealer en binnendienst naar hetzelfde kijken.
	 */
	private static function render_werkblad() {
		$alle     = HDP_Garantie::claims();
		$machines = HDP_Garantie::machines();
		$alle     = is_wp_error( $alle ) ? array() : $alle;
		$machines = is_wp_error( $machines ) ? array() : $machines;
		$groep    = self::gekozen_groep();
		?>
		<div class="hdp-garantie-werkblad">
			<?php self::render_rail( $alle, $machines, $groep ); ?>

			<div class="hdp-garantie-kolom-lijst">
				<?php self::render_zoekveld(); ?>
				<?php if ( 'machines' === $groep ) : ?>
					<?php self::render_machinelijst( $machines ); ?>
				<?php else : ?>
					<?php self::render_claimlijst( self::filter_claims( $alle, $groep ) ); ?>
				<?php endif; ?>
			</div>

			<div class="hdp-garantie-kolom-detail">
				<?php self::render_detail( $alle ); ?>
			</div>
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

	/**
	 * De filterkolom links, met achter elke regel hoeveel het er zijn.
	 *
	 * Die tellingen zijn het punt: zonder die cijfers moet je elke groep
	 * openklikken om te zien of er iets in zit.
	 */
	private static function render_rail( $claims, $machines, $actief ) {
		$regels = array(
			'alles' => count( $claims ),
			'wacht' => count( self::filter_claims( $claims, 'wacht' ) ),
			'loopt' => count( self::filter_claims( $claims, 'loopt' ) ),
			'klaar' => count( self::filter_claims( $claims, 'klaar' ) ),
		);
		?>
		<aside class="hdp-garantie-rail">
			<div class="hdp-rail-knoppen">
				<a class="hdp-btn" href="<?php echo esc_url( add_query_arg( 'nieuw', 'claim', home_url( '/garantie/' ) ) ); ?>">
					<?php echo esc_html( HDP_I18N::t( 'garantie_ingang_claim_knop' ) ); ?>
				</a>
				<a class="hdp-btn hdp-btn-secundair" href="<?php echo esc_url( add_query_arg( 'nieuw', 'machine', home_url( '/garantie/' ) ) ); ?>">
					<?php echo esc_html( HDP_I18N::t( 'garantie_ingang_machine_knop' ) ); ?>
				</a>
			</div>

			<div class="hdp-rail-groep">
				<p class="hdp-rail-kop"><?php echo esc_html( HDP_I18N::t( 'garantie_rail_claims' ) ); ?></p>
				<?php foreach ( $regels as $sleutel => $aantal ) : ?>
					<a class="hdp-rail-regel<?php echo $actief === $sleutel ? ' hdp-rail-actief' : ''; ?><?php echo ( 'wacht' === $sleutel && $aantal ) ? ' hdp-rail-wacht' : ''; ?>"
						href="<?php echo esc_url( add_query_arg( 'toon', $sleutel, home_url( '/garantie/' ) ) ); ?>"
						<?php echo $actief === $sleutel ? 'aria-current="page"' : ''; ?>>
						<span><?php echo esc_html( HDP_I18N::t( 'garantie_rail_' . $sleutel ) ); ?></span>
						<span class="hdp-rail-aantal"><?php echo esc_html( (string) $aantal ); ?></span>
					</a>
				<?php endforeach; ?>
			</div>

			<div class="hdp-rail-groep">
				<p class="hdp-rail-kop"><?php echo esc_html( HDP_I18N::t( 'garantie_mijn_machines' ) ); ?></p>
				<a class="hdp-rail-regel<?php echo 'machines' === $actief ? ' hdp-rail-actief' : ''; ?>"
					href="<?php echo esc_url( add_query_arg( 'toon', 'machines', home_url( '/garantie/' ) ) ); ?>"
					<?php echo 'machines' === $actief ? 'aria-current="page"' : ''; ?>>
					<span><?php echo esc_html( HDP_I18N::t( 'garantie_rail_aangemeld' ) ); ?></span>
					<span class="hdp-rail-aantal"><?php echo esc_html( (string) count( $machines ) ); ?></span>
				</a>
			</div>
		</aside>
		<?php
	}

	/**
	 * De rechterkolom: de gekozen claim, of de uitnodiging er een te kiezen.
	 *
	 * Bewust een kortere versie van het ticket dan het volledige scherm: de
	 * route, de machinekaart en de bijlagen hebben breedte nodig en staan
	 * daar. Hier staat waar het om draait — bij wie het ligt en wat er
	 * gezegd is — met een link naar de rest.
	 */
	private static function render_detail( $claims ) {
		$nummer = self::gekozen_ticket();

		if ( '' === $nummer ) {
			self::render_detail_leeg( $claims );
			return;
		}

		$claim = HDP_Garantie::claim_voor_dealer( $nummer );

		if ( is_wp_error( $claim ) || ! $claim ) {
			$onbekend = HDP_I18N::t( 'garantie_ticket_onbekend' );
			echo HDP_Icons::render_lege_status( 'downloads', $onbekend ); // phpcs:ignore WordPress.Security.EscapeOutput -- render_lege_status() escapet elk veld zelf.
			return;
		}
		?>
		<div class="hdp-detail-kaart">
			<?php self::render_ticket_kop( $claim ); ?>
			<?php self::render_aan_zet( $claim ); ?>
			<?php self::render_gesprek( $claim ); ?>
			<p class="hdp-detail-volledig">
				<a href="<?php echo esc_url( add_query_arg( array( 'ticket' => $claim['nummer'], 'volledig' => '1' ), home_url( '/garantie/' ) ) ); ?>">
					<?php echo esc_html( HDP_I18N::t( 'garantie_open_volledig' ) ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Wat er rechts staat als er nog niets gekozen is.
	 *
	 * Voor een nieuwe dealer is dit het eerste wat hij van garantie ziet,
	 * met nul machines en nul claims. Dat scherm hoort te vertellen wat hij
	 * kan doen, niet alleen te melden dat er niets is.
	 */
	private static function render_detail_leeg( $claims ) {
		if ( ! $claims ) {
			$tekst = HDP_I18N::t( 'garantie_nog_niets_tekst' );
			$knop  = HDP_I18N::t( 'garantie_ingang_machine_knop' );
			$url   = add_query_arg( 'nieuw', 'machine', home_url( '/garantie/' ) );

			echo HDP_Icons::render_lege_status( 'garantie', $tekst, $url, $knop ); // phpcs:ignore WordPress.Security.EscapeOutput -- render_lege_status() escapet elk veld zelf.
			return;
		}

		$kies = HDP_I18N::t( 'garantie_kies_een_claim' );
		echo HDP_Icons::render_lege_status( 'garantie', $kies ); // phpcs:ignore WordPress.Security.EscapeOutput -- render_lege_status() escapet elk veld zelf.
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
		// De kop volgt het gekozen filter: onder "Afgehandeld" stond eerder
		// nog steeds "Lopende claims" boven de lijst.
		$groep = self::gekozen_groep();
		?>
		<div class="hdp-garantie-kop">
			<h2>
				<?php echo esc_html( HDP_I18N::t( 'garantie_rail_' . $groep ) ); ?>
				<span class="hdp-garantie-aantal"><?php echo esc_html( (string) count( $claims ) ); ?></span>
			</h2>
		</div>

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
						href="<?php echo esc_url( add_query_arg( 'ticket', $claim['nummer'], home_url( '/garantie/' ) ) ); ?>"
						data-zoek="<?php echo esc_attr( self::zoektekst( array( $claim['nummer'], $claim['machine'], $claim['serienummer'], $claim['merk'], $claim['klacht'] ) ) ); ?>">
						<div class="hdp-garantie-item-hoofd">
							<span class="hdp-garantie-nummer"><?php echo esc_html( $claim['nummer'] ); ?></span>
							<span class="<?php echo esc_attr( HDP_Garantie::status_klasse( $claim['status'] ) ); ?>">
								<?php echo esc_html( HDP_Garantie::status_label( $claim['status'] ) ); ?>
							</span>
						</div>
						<div class="hdp-garantie-item-info">
							<?php // Serienummer hoort in de titel: een dealer heeft vaak meerdere machines van hetzelfde type staan. ?>
							<strong><?php echo esc_html( HDP_Garantie::machinenaam( $claim['machine'], $claim['serienummer'] ) ); ?></strong>
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
					<?php $verborgen = $inklappen && $nummer >= self::MACHINES_ZICHTBAAR; ?>
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
					<article class="hdp-machine<?php echo $verborgen ? ' hdp-machine-verborgen' : ''; ?>"
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
					action="<?php echo esc_url( add_query_arg( $is_claim ? array( 'nieuw' => 'claim', 'soort' => $claimsoort ) : array( 'nieuw' => $soort ), home_url( '/garantie/' ) ) ); ?>">
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
							<div class="hdp-veld<?php echo $breed ? ' hdp-veld-breed' : ''; ?>">
								<label for="<?php echo esc_attr( $veld_id ); ?>">
									<?php echo esc_html( $label ); ?>
									<?php if ( $verplicht ) : ?>
										<span class="hdp-veld-verplicht" aria-hidden="true">*</span>
									<?php endif; ?>
								</label>
								<?php if ( 'textarea' === $veld['type'] ) : ?>
									<textarea id="<?php echo esc_attr( $veld_id ); ?>" name="<?php echo esc_attr( $naam ); ?>" rows="3" <?php echo $verplicht ? 'required' : ''; ?>><?php echo esc_textarea( $eerder ); ?></textarea>
								<?php elseif ( 'file' === $veld['type'] ) : ?>
									<input type="file" id="<?php echo esc_attr( $veld_id ); ?>" name="<?php echo esc_attr( 'machine' === $soort ? 'bijlagen' : 'fotos' ); ?>[]" multiple
										accept="<?php echo esc_attr( self::toegestane_bestanden() ); ?>">
									<span class="hdp-veld-hint"><?php echo esc_html( HDP_I18N::t( 'claim' === $soort ? 'garantie_fotos_hint' : 'garantie_bijlagen_hint' ) ); ?></span>
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
									<input type="checkbox" id="<?php echo esc_attr( $veld_id ); ?>" name="<?php echo esc_attr( $naam ); ?>" value="1" <?php checked( (bool) $eerder ); ?>>
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
									<input type="<?php echo esc_attr( $veld['type'] ); ?>" id="<?php echo esc_attr( $veld_id ); ?>" name="<?php echo esc_attr( $naam ); ?>"
										value="<?php echo esc_attr( $eerder ); ?>" <?php echo $verplicht ? 'required' : ''; ?>>
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
			<select id="hdp-claim-machinekeuze" name="machinekeuze" required<?php echo $veel ? ' size="8"' : ''; ?>>
				<option value=""><?php echo esc_html( HDP_I18N::t( 'garantie_kies' ) ); ?></option>
				<?php foreach ( $machines as $machine ) : ?>
					<?php
					// Het serienummer is de waarde: de machine wordt bij het
					// indienen opgezocht binnen de eigen machines, zodat je
					// niet op andermans machine kunt claimen.
					?>
					<option value="<?php echo esc_attr( $machine['serienummer'] ); ?>" <?php selected( HDP_Garantie_Formulier::eerder( 'machine' ), $machine['serienummer'] ); ?>>
						<?php echo esc_html( HDP_Garantie::machinenaam( $machine['machine'], $machine['serienummer'] ) ); ?>
						<?php if ( ! empty( $machine['klant'] ) ) : ?>
							<?php // De klantnaam erbij: wie honderd machines heeft, zoekt meestal "die van Jansen" en niet een serienummer uit zijn hoofd. ?>
							&middot; <?php echo esc_html( $machine['klant'] ); ?>
						<?php endif; ?>
						<?php if ( 'goedgekeurd' !== $machine['status'] ) : ?>
							— <?php echo esc_html( HDP_I18N::t( 'garantie_machine_' . $machine['status'] ) ); ?>
						<?php endif; ?>
					</option>
				<?php endforeach; ?>
			</select>
			<span class="hdp-veld-hint">
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
						<?php self::render_machinekaart( $claim ); ?>
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
					<h2><?php echo esc_html( HDP_Garantie::machinenaam( $claim['machine'], $claim['serienummer'] ) ); ?></h2>
					<p class="hdp-ticket-meta">
						<?php echo esc_html( $claim['merk'] ); ?> &middot;
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
			?>
			<p class="hdp-ticket-ligtbij hdp-ticket-ligtbij-klaar"><?php echo esc_html( HDP_I18N::t( 'garantie_ligt_bij_klaar' ) ); ?></p>
			<?php
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

	/** De laatste vraag van Homburg, voor in het "u bent aan zet"-blok. */
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
				<dl class="hdp-ticket-klacht">
					<dt><?php echo esc_html( HDP_I18N::t( 'garantie_veld_klacht' ) ); ?></dt>
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

			<div class="hdp-ticket-regel hdp-ticket-regel-kop" aria-hidden="true">
				<span><?php echo esc_html( HDP_I18N::t( 'garantie_regel_nummer' ) ); ?></span>
				<span><?php echo esc_html( HDP_I18N::t( 'garantie_regel_aantal' ) ); ?></span>
				<span><?php echo esc_html( HDP_I18N::t( 'garantie_regel_bedrag' ) ); ?></span>
				<?php if ( $met_uren ) : ?>
					<span><?php echo esc_html( HDP_I18N::t( 'garantie_regel_uren' ) ); ?></span>
				<?php endif; ?>
				<span><?php echo esc_html( HDP_I18N::t( 'garantie_regel_herkomst' ) ); ?></span>
			</div>

			<?php foreach ( $regels as $regel ) : ?>
				<div class="hdp-ticket-regel">
					<span class="hdp-ticket-regel-nummer"><?php echo esc_html( $regel['nummer'] ); ?></span>
					<span><?php echo esc_html( (string) $regel['aantal'] ); ?></span>
					<span><?php echo esc_html( null === $regel['bedrag_cent'] ? '—' : '€ ' . number_format( $regel['bedrag_cent'] / 100, 2, ',', '.' ) ); ?></span>
					<?php if ( $met_uren ) : ?>
						<span><?php echo esc_html( null === $regel['uren'] ? '—' : rtrim( rtrim( number_format( $regel['uren'], 2, ',', '' ), '0' ), ',' ) ); ?></span>
					<?php endif; ?>
					<span><?php echo esc_html( HDP_I18N::t( $regel['homburg_factuur'] ? 'garantie_regel_homburg' : 'garantie_regel_derden' ) ); ?></span>
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
							<li class="hdp-bijlage">
								<?php HDP_Icons::render_icoon( 'downloads', 'hdp-bijlage-icoon' ); ?>
								<span><?php echo esc_html( $bijlage ); ?></span>
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
				action="<?php echo esc_url( add_query_arg( 'ticket', $claim['nummer'], home_url( '/garantie/' ) ) ); ?>">
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
