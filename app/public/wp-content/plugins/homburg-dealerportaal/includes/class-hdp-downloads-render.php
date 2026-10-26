<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rendert de downloads-, content- en huisstijlpagina's: paginakop + de
 * beveiligde, doorzoekbare/filterbare/sorteerbare lijst (zie
 * render_downloads_lijst()).
 *
 * Alle drie dezelfde pagina met een andere categorie ervoor. Ze stonden
 * eerst als drie keer hetzelfde blok code naast elkaar; bij de derde was
 * dat niet meer vol te houden.
 */
class HDP_Downloads_Render {

	public static function render_downloads_pagina( $a ) {
		return self::render_categorie_pagina( $a, 'download' );
	}

	/**
	 * Bibliotheek met content voor social media/advertenties — zelfde opzet
	 * als de downloadspagina, gefilterd op categorie "content".
	 */
	public static function render_content_pagina( $a ) {
		return self::render_categorie_pagina( $a, 'content' );
	}

	/**
	 * De huisstijl: logo's, kleuren en lettertypen van Homburg en van de
	 * merken die we voeren. Dealers zetten het Homburg-logo op hun eigen
	 * site, briefpapier en advertenties; tot nu toe moesten ze daarvoor
	 * mailen of een bestand uit een oude nieuwsbrief vissen.
	 */
	public static function render_huisstijl_pagina( $a ) {
		return self::render_categorie_pagina( $a, 'huisstijl' );
	}

	/**
	 * De pagina zelf. Buiten de categorie is er niets verschillends aan.
	 *
	 * @param array  $a         Blokattributen.
	 * @param string $categorie 'download', 'content' of 'huisstijl'.
	 * @return string
	 */
	private static function render_categorie_pagina( $a, $categorie ) {
		$mag_zien = is_user_logged_in() && HDP_Roles::mag_portaal_zien( get_current_user_id() );

		if ( ! $mag_zien ) {
			ob_start();
			HDP_Login::render_login( HDP_Login::login_foutmelding(), $a );
			return ob_get_clean();
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
			<section class="hdp-downloads">
				<?php echo self::render_downloads_lijst( $categorie ); // phpcs:ignore WordPress.Security.EscapeOutput -- reeds ge-escaped in render_downloads_lijst(). ?>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * $categorie bepaalt of dit de technische downloads ('download': prijs-
	 * lijsten, handleidingen, e.d.) of de socialmedia-/advertentiecontent
	 * ('content') toont. Bestaande downloads van vóór dit onderscheid
	 * hebben geen _hdp_categorie-meta; die tellen mee als 'download'.
	 */
	/**
	 * De downloads die déze bezoeker mag zien, na categorie-, taal- en
	 * merkfiltering — inclusief waaróp de lijst eventueel leeg liep, zodat de
	 * pagina een passende lege staat kan tonen.
	 *
	 * Apart van het renderen omdat de teller "X nieuwe documenten" op de
	 * portaalstartpagina exact dezelfde zichtbaarheid moet aanhouden. Zou die
	 * zijn eigen query doen, dan kan hij "3 nieuw" melden terwijl de dealer er
	 * op de downloadspagina nul ziet.
	 *
	 * @return array{downloads: WP_Post[], leeg: string} leeg is '', 'geen', 'taal' of 'merk'.
	 */
	public static function zichtbare_downloads( $categorie = 'download' ) {
		// Alleen "download" vangt ook de bestanden van vóór dit onderscheid
		// op: die hebben geen categorie-meta en waren altijd downloads. Een
		// nieuwe categorie mag die erfenis niet opslokken.
		$meta_query = 'download' === $categorie
			? array(
				'relation' => 'OR',
				array( 'key' => '_hdp_categorie', 'value' => 'download' ),
				array( 'key' => '_hdp_categorie', 'compare' => 'NOT EXISTS' ),
			)
			: array( array( 'key' => '_hdp_categorie', 'value' => $categorie ) );

		$downloads = get_posts(
			array(
				'post_type'      => HDP_Downloads_CPT::POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- kleine, beheerste dataset (dealerdownloads), geen gebruikersinvoer.
			)
		);

		if ( ! $downloads ) {
			return array( 'downloads' => array(), 'leeg' => 'geen' );
		}

		// Taalgebonden zichtbaarheid: een NL-taalbezoeker ziet bestanden
		// getagd met regio 'nl' of 'be', een FR-taalbezoeker alleen 'be-fr'
		// — zo verschijnt een bestand automatisch bij de juiste taalgroep in
		// plaats van dat de dealer dat zelf via de regiofilter moet uitzoeken.
		// Bestanden zonder regio-tag (van vóór dit onderscheid, of bewust
		// voor iedereen bedoeld) blijven in beide talen zichtbaar. "en"
		// (Engelstalige bestanden) staat los van de NL/FR-schermtaal — die
		// blijven voor iedereen zichtbaar, ongeacht de taalkeuze.
		$toegestane_regios = HDP_I18N::is_frans() ? array( 'be-fr', 'en' ) : array( 'nl', 'be', 'en' );
		$downloads         = array_values(
			array_filter(
				$downloads,
				static function ( $download ) use ( $toegestane_regios ) {
					$regios = HDP_Downloads_CPT::get_regios( $download->ID );
					return ! $regios || array_intersect( $regios, $toegestane_regios );
				}
			)
		);

		if ( ! $downloads ) {
			return array( 'downloads' => array(), 'leeg' => 'taal' );
		}

		// Merkgebonden zichtbaarheid — via HDP_Downloads_CPT::mag_merk_zien(),
		// exact dezelfde controle die resolve_download() bij het uitleveren
		// van het bestand doet. Bewust gedeeld: staan die twee los, dan is
		// dit filter alleen cosmetisch en blijft het bestand op te halen via
		// het ID in de URL.
		$downloads = array_values(
			array_filter(
				$downloads,
				static function ( $download ) {
					return HDP_Downloads_CPT::mag_merk_zien( $download->ID );
				}
			)
		);

		if ( ! $downloads ) {
			return array( 'downloads' => array(), 'leeg' => 'merk' );
		}

		return array( 'downloads' => $downloads, 'leeg' => '' );
	}

	private static function render_downloads_lijst( $categorie = 'download' ) {
		$resultaat = self::zichtbare_downloads( $categorie );
		$downloads = $resultaat['downloads'];

		if ( $resultaat['leeg'] ) {
			$sleutels = array(
				'geen' => 'content' === $categorie ? 'nog_geen_content' : 'nog_geen_downloads',
				'taal' => 'content' === $categorie ? 'geen_content_taal' : 'geen_downloads_taal',
				'merk' => 'content' === $categorie ? 'geen_content_merk' : 'geen_downloads_merk',
			);
			return HDP_Icons::render_lege_status(
				'content' === $categorie ? 'content' : 'downloads',
				HDP_I18N::t( $sleutels[ $resultaat['leeg'] ] )
			);
		}

		// Merken voor de filterchips worden automatisch afgeleid uit de
		// downloads zelf — als Homburg later een nieuw merk invult bij een
		// download, verschijnt de chip vanzelf, zonder codewijziging.
		$merken = array();
		foreach ( $downloads as $download ) {
			$merk = HDP_Downloads_CPT::get_merk( $download->ID );
			if ( $merk && ! in_array( $merk, $merken, true ) ) {
				$merken[] = $merk;
			}
		}
		sort( $merken );

		ob_start();
		?>
		<div class="hdp-dl-werkbalk">
			<div class="hdp-zoekveld">
				<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
				<input type="text" id="hdp-zoeken" placeholder="<?php echo esc_attr( HDP_I18N::t( 'zoek_placeholder' ) ); ?>" autocomplete="off">
			</div>
			<div class="hdp-filterrij">
				<div class="hdp-filtergroep">
					<?php // Geen <label for>: dit zijn knoppen, geen invoerveld. Een groep met een naam is hier het juiste gereedschap, zodat de losse knoppen als "Regio: Nederland" worden aangekondigd. ?>
					<span class="hdp-filtergroep-label" id="hdp-regio-label"><?php echo esc_html( HDP_I18N::t( 'filter_regio' ) ); ?></span>
					<div class="hdp-chips" id="hdp-regio-chips" role="group" aria-labelledby="hdp-regio-label">
						<button type="button" class="hdp-chip hdp-chip-actief" data-regio="alle"><?php echo esc_html( HDP_I18N::t( 'filter_alles' ) ); ?></button>
						<?php if ( HDP_I18N::is_frans() ) : ?>
							<button type="button" class="hdp-chip" data-regio="be-fr"><?php echo esc_html( HDP_I18N::t( 'filter_belgie_fr' ) ); ?></button>
						<?php else : ?>
							<button type="button" class="hdp-chip" data-regio="nl"><?php echo esc_html( HDP_I18N::t( 'filter_nederland' ) ); ?></button>
							<button type="button" class="hdp-chip" data-regio="be"><?php echo esc_html( HDP_I18N::t( 'filter_belgie' ) ); ?></button>
						<?php endif; ?>
						<button type="button" class="hdp-chip" data-regio="en"><?php echo esc_html( HDP_I18N::t( 'filter_engels' ) ); ?></button>
					</div>
				</div>
				<?php if ( $merken ) : ?>
				<div class="hdp-filtergroep">
					<span class="hdp-filtergroep-label" id="hdp-merk-label"><?php echo esc_html( HDP_I18N::t( 'filter_merk' ) ); ?></span>
					<div class="hdp-chips" id="hdp-merk-chips" role="group" aria-labelledby="hdp-merk-label">
						<?php foreach ( $merken as $merk ) : ?>
							<button type="button" class="hdp-chip" data-merk="<?php echo esc_attr( $merk ); ?>"><?php echo esc_html( $merk ); ?></button>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>
				<div class="hdp-filtergroep">
					<label class="hdp-filtergroep-label" for="hdp-sorteer"><?php echo esc_html( HDP_I18N::t( 'sorteren_label' ) ); ?></label>
					<select id="hdp-sorteer" class="hdp-sorteer-select">
						<option value="datum-nieuw"><?php echo esc_html( HDP_I18N::t( 'sorteer_nieuw' ) ); ?></option>
						<option value="datum-oud"><?php echo esc_html( HDP_I18N::t( 'sorteer_oud' ) ); ?></option>
						<option value="naam-az"><?php echo esc_html( HDP_I18N::t( 'sorteer_naam' ) ); ?></option>
					</select>
				</div>
			</div>
			<div class="hdp-filterrij-onder">
				<p class="hdp-telling" aria-live="polite" aria-atomic="true"><strong id="hdp-telling-zichtbaar"><?php echo count( $downloads ); ?></strong> <?php echo esc_html( HDP_I18N::t( 'telling_van' ) ); ?> <?php echo count( $downloads ); ?> <?php echo esc_html( HDP_I18N::t( 'telling_zichtbaar' ) ); ?></p>
				<button type="button" class="hdp-wis-filters" id="hdp-wis-filters"><?php echo esc_html( HDP_I18N::t( 'wis_filters' ) ); ?></button>
			</div>
		</div>

		<?php
		// Content is beeldmateriaal: dat wil een dealer zien voordat hij het
		// downloadt. Daar dus een galerij met voorbeelden in plaats van een
		// lijst met bestandsnamen.
		$galerij = 'content' === $categorie;
		?>
		<?php if ( $galerij ) : ?>
			<p class="hdp-content-alles">
				<a class="hdp-btn hdp-btn-secundair" href="<?php echo esc_url( HDP_Downloads_CPT::zip_url( 'content' ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'btn_alles_downloaden' ) ); ?></a>
			</p>
		<?php endif; ?>
		<div class="hdp-download-lijst<?php echo $galerij ? ' hdp-content-galerij' : ''; ?>" id="hdp-download-lijst">
			<?php foreach ( $downloads as $download ) : ?>
				<?php echo self::render_item( $download, $galerij ); // phpcs:ignore WordPress.Security.EscapeOutput -- render_item() escapet elk veld zelf. ?>
			<?php endforeach; ?>
		</div>

		<p class="hdp-leeg-resultaat" id="hdp-leeg-resultaat"><?php echo esc_html( HDP_I18N::t( 'leeg_resultaat' ) ); ?></p>

		<script>
		(function () {
			var zoekveld = document.getElementById('hdp-zoeken');
			var lijst = document.getElementById('hdp-download-lijst');
			var items = lijst ? lijst.querySelectorAll('.hdp-download-item') : [];
			var tellingEl = document.getElementById('hdp-telling-zichtbaar');
			var leegEl = document.getElementById('hdp-leeg-resultaat');
			var regioChips = document.getElementById('hdp-regio-chips');
			var merkChips = document.getElementById('hdp-merk-chips');
			var wisKnop = document.getElementById('hdp-wis-filters');
			var sorteerSelect = document.getElementById('hdp-sorteer');
			var actieveRegio = 'alle';
			var actieveMerken = [];

			function sorteerToepassen() {
				if (!sorteerSelect || !lijst) { return; }
				var volgorde = sorteerSelect.value;
				Array.prototype.slice.call(items).sort(function (a, b) {
					if (volgorde === 'naam-az') { return a.dataset.titel.localeCompare(b.dataset.titel); }
					var datumA = parseInt(a.dataset.datum, 10);
					var datumB = parseInt(b.dataset.datum, 10);
					return volgorde === 'datum-oud' ? datumA - datumB : datumB - datumA;
				}).forEach(function (item) { lijst.appendChild(item); });
			}

			if (sorteerSelect) { sorteerSelect.addEventListener('change', sorteerToepassen); }

			// "Kopieer tekst" bij content: de posttekst die bij een beeld hoort.
			if (lijst) {
				lijst.addEventListener('click', function (e) {
					var knop = e.target.closest('.hdp-kopieer-tekst');
					if (!knop || !navigator.clipboard) { return; }
					navigator.clipboard.writeText(knop.dataset.tekst).then(function () {
						var oud = knop.textContent;
						knop.textContent = knop.dataset.gekopieerd;
						setTimeout(function () { knop.textContent = oud; }, 1800);
					});
				});
			}

			function filterToepassen() {
				var zoekterm = zoekveld ? zoekveld.value.trim().toLowerCase() : '';
				var zichtbaar = 0;
				items.forEach(function (item) {
					// Titel, omschrijving en merk: eerder alleen de titel.
					var voldoetZoek = !zoekterm || item.dataset.zoek.indexOf(zoekterm) !== -1;
					var voldoetRegio = actieveRegio === 'alle' || item.dataset.regios.split(' ').indexOf(actieveRegio) !== -1;
					var voldoetMerk = actieveMerken.length === 0 || actieveMerken.indexOf(item.dataset.merk) !== -1;
					var zicht = voldoetZoek && voldoetRegio && voldoetMerk;
					item.style.display = zicht ? '' : 'none';
					if (zicht) { zichtbaar++; }
				});
				if (tellingEl) { tellingEl.textContent = zichtbaar; }
				if (leegEl) { leegEl.classList.toggle('hdp-zichtbaar', zichtbaar === 0); }
			}

			if (zoekveld) { zoekveld.addEventListener('input', filterToepassen); }

			if (regioChips) {
				regioChips.addEventListener('click', function (e) {
					var chip = e.target.closest('.hdp-chip');
					if (!chip) { return; }
					actieveRegio = chip.dataset.regio;
					regioChips.querySelectorAll('.hdp-chip').forEach(function (c) { c.classList.remove('hdp-chip-actief'); });
					chip.classList.add('hdp-chip-actief');
					filterToepassen();
				});
			}

			if (merkChips) {
				merkChips.addEventListener('click', function (e) {
					var chip = e.target.closest('.hdp-chip');
					if (!chip) { return; }
					var merk = chip.dataset.merk;
					var idx = actieveMerken.indexOf(merk);
					if (idx === -1) { actieveMerken.push(merk); chip.classList.add('hdp-chip-actief'); }
					else { actieveMerken.splice(idx, 1); chip.classList.remove('hdp-chip-actief'); }
					filterToepassen();
				});
			}

			if (wisKnop) {
				wisKnop.addEventListener('click', function () {
					if (zoekveld) { zoekveld.value = ''; }
					actieveRegio = 'alle';
					actieveMerken = [];
					document.querySelectorAll('.hdp-chip').forEach(function (c) { c.classList.remove('hdp-chip-actief'); });
					var alles = regioChips ? regioChips.querySelector('[data-regio="alle"]') : null;
					if (alles) { alles.classList.add('hdp-chip-actief'); }
					filterToepassen();
				});
			}
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Eén bestand in de lijst of de galerij.
	 *
	 * Bij elk bestand staat nu wat voor bestand het is, hoe groot, en van
	 * wanneer — er stond alleen een titel, waardoor niet te zien was welke
	 * prijslijst de nieuwste was. Heeft een document meerdere formaten (PDF
	 * en Excel), dan staan die onder één titel met een knop per formaat in
	 * plaats van als twee losse regels.
	 *
	 * @param WP_Post $download De download.
	 * @param bool    $galerij  Als kaart met voorbeeld (content) of als regel.
	 * @return string
	 */
	private static function render_item( $download, $galerij = false ) {
		$merk         = HDP_Downloads_CPT::get_merk( $download->ID );
		$regios       = HDP_Downloads_CPT::get_regios( $download->ID );
		$bestanden    = HDP_Downloads_CPT::bestanden( $download->ID );
		$hoofd        = $bestanden ? $bestanden[0] : 0;
		$omschrijving = trim( wp_strip_all_tags( $download->post_content ) );
		$nieuw        = HDP_Downloads_CPT::is_nieuw( $download->ID );

		// Formaat en grootte per bestand: "PDF 14 MB · XLSX 593 KB".
		$formaten = array();
		foreach ( $bestanden as $attachment_id ) {
			$grootte    = HDP_Downloads_CPT::bestandsgrootte( $attachment_id );
			$formaten[] = trim( HDP_Downloads_CPT::type_label( $download->ID, $attachment_id ) . ' ' . ( $grootte ? size_format( $grootte, $grootte >= MB_IN_BYTES ? 1 : 0 ) : '' ) );
		}
		$meta = implode( ' · ', array_filter( array_merge( $formaten, array( HDP_I18N::datum( 'j M Y', (int) get_post_time( 'U', false, $download ) ) ) ) ) );

		$bekijkbaar = $hoofd && HDP_Downloads_CPT::is_bekijkbaar( get_post_mime_type( $hoofd ) );

		ob_start();
		?>
		<div class="hdp-download-item<?php echo $galerij ? ' hdp-content-kaart' : ''; ?>"
			data-titel="<?php echo esc_attr( mb_strtolower( $download->post_title ) ); ?>"
			data-zoek="<?php echo esc_attr( mb_strtolower( implode( ' ', array_filter( array( $download->post_title, $omschrijving, $merk ) ) ) ) ); ?>"
			data-merk="<?php echo esc_attr( $merk ); ?>"
			data-regios="<?php echo esc_attr( implode( ' ', $regios ) ); ?>"
			data-datum="<?php echo esc_attr( get_post_time( 'U', true, $download ) ); ?>"
			data-nieuw="<?php echo $nieuw ? '1' : '0'; ?>">
			<?php if ( $galerij ) : ?>
				<a class="hdp-content-beeld" href="<?php echo esc_url( HDP_Downloads_CPT::download_url( $download->ID, 0, $bekijkbaar ? 'bekijken' : '' ) ); ?>" <?php echo $bekijkbaar ? 'target="_blank" rel="noopener"' : ''; ?> tabindex="-1" aria-hidden="true">
					<?php if ( $hoofd && HDP_Downloads_CPT::is_afbeelding( $hoofd ) ) : ?>
						<img src="<?php echo esc_url( HDP_Downloads_CPT::download_url( $download->ID, 0, 'voorbeeld' ) ); ?>" alt="" loading="lazy">
					<?php else : ?>
						<span class="hdp-content-beeld-type"><?php echo esc_html( HDP_Downloads_CPT::type_label( $download->ID ) ); ?></span>
					<?php endif; ?>
				</a>
			<?php else : ?>
				<span class="hdp-download-type"><?php echo esc_html( HDP_Downloads_CPT::type_label( $download->ID ) ); ?></span>
			<?php endif; ?>
			<span class="hdp-download-info">
				<strong>
					<?php echo esc_html( $download->post_title ); ?>
					<?php if ( $nieuw ) : ?>
						<span class="hdp-nieuw-badge"><?php echo esc_html( HDP_I18N::t( 'badge_nieuw' ) ); ?></span>
					<?php endif; ?>
				</strong>
				<?php if ( $omschrijving ) : ?>
					<span class="<?php echo $galerij ? 'hdp-content-bijschrift' : ''; ?>"><?php echo esc_html( $omschrijving ); ?></span>
				<?php endif; ?>
				<span class="hdp-download-meta"><?php echo esc_html( $meta ); ?></span>
			</span>
			<span class="hdp-download-knoppen">
				<?php if ( $galerij && $omschrijving ) : ?>
					<?php // De tekst die bij het beeld hoort, in één klik klaar om te plakken bij een post. ?>
					<button type="button" class="hdp-btn hdp-btn-secundair hdp-kopieer-tekst" data-tekst="<?php echo esc_attr( $omschrijving ); ?>" data-gekopieerd="<?php echo esc_attr( HDP_I18N::t( 'tekst_gekopieerd' ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'btn_kopieer_tekst' ) ); ?></button>
				<?php endif; ?>
				<?php if ( $bekijkbaar ) : ?>
					<?php // Een handleiding even inkijken zonder hem eerst op te slaan. ?>
					<a class="hdp-btn hdp-btn-secundair" href="<?php echo esc_url( HDP_Downloads_CPT::download_url( $download->ID, 0, 'bekijken' ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( HDP_I18N::t( 'btn_bekijken' ) ); ?></a>
				<?php endif; ?>
				<?php if ( count( $bestanden ) > 1 ) : ?>
					<?php foreach ( $bestanden as $attachment_id ) : ?>
						<a class="hdp-btn" href="<?php echo esc_url( HDP_Downloads_CPT::download_url( $download->ID, $attachment_id ) ); ?>"
							aria-label="<?php echo esc_attr( HDP_I18N::t( 'btn_downloaden' ) . ' ' . HDP_Downloads_CPT::type_label( $download->ID, $attachment_id ) ); ?>">
							<?php echo esc_html( HDP_Downloads_CPT::type_label( $download->ID, $attachment_id ) ); ?>
						</a>
					<?php endforeach; ?>
				<?php else : ?>
					<a class="hdp-btn" href="<?php echo esc_url( HDP_Downloads_CPT::download_url( $download->ID ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'btn_downloaden' ) ); ?></a>
				<?php endif; ?>
			</span>
		</div>
		<?php
		return ob_get_clean();
	}
}
