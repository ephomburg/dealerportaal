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

		// Filters in een zijkolom met vinkjes (2026-11-12, zoals het merkfilter
		// van de webshop): merk (meerdere tegelijk, met aantallen), de taal
		// van het bestand en de soort bestand. Alles afgeleid uit de
		// downloads zelf: een nieuw merk of bestandstype verschijnt vanzelf.
		$merken = array();
		$talen  = array();
		$soorten = array();
		foreach ( $downloads as $download ) {
			$merk = HDP_Downloads_CPT::get_merk( $download->ID );
			if ( $merk ) {
				$merken[ $merk ] = ( isset( $merken[ $merk ] ) ? $merken[ $merk ] : 0 ) + 1;
			}
			foreach ( self::talen_van( $download->ID ) as $taal ) {
				$talen[ $taal ] = true;
			}
			foreach ( self::soorten_van( $download->ID ) as $soort ) {
				$soorten[ $soort ] = ( isset( $soorten[ $soort ] ) ? $soorten[ $soort ] : 0 ) + 1;
			}
		}
		// Grootste merk bovenaan: daar zoeken de meeste dealers.
		uksort(
			$merken,
			static function ( $a, $b ) use ( $merken ) {
				return $merken[ $b ] <=> $merken[ $a ] ?: strcasecmp( $a, $b );
			}
		);
		$taalvolgorde = array_values( array_intersect( array( 'nl', 'fr', 'en' ), array_keys( $talen ) ) );
		$soortvolgorde = array_values( array_intersect( array( 'pdf', 'excel', 'afbeelding', 'zip', 'overig' ), array_keys( $soorten ) ) );

		// Content is beeldmateriaal: dat wil een dealer zien voordat hij het
		// downloadt. Daar dus een galerij met voorbeelden in plaats van een
		// lijst met bestandsnamen.
		$galerij = 'content' === $categorie;

		ob_start();
		?>
		<div class="hdp-dl-indeling">
		<details class="hdp-dl-filters" id="hdp-dl-filters">
			<summary class="hdp-dl-filters-knop"><?php echo esc_html( HDP_I18N::t( 'filters_knop' ) ); ?> <span class="hdp-dl-filters-aantal" id="hdp-filters-aantal"></span></summary>
			<?php if ( count( $merken ) > 1 ) : ?>
				<fieldset class="hdp-dl-filtergroep" id="hdp-filter-merk">
					<legend><?php echo esc_html( HDP_I18N::t( 'filter_merk' ) ); ?></legend>
					<?php foreach ( $merken as $merk => $aantal ) : ?>
						<label class="hdp-dl-optie">
							<input type="checkbox" value="<?php echo esc_attr( $merk ); ?>" data-label="<?php echo esc_attr( $merk ); ?>">
							<span class="hdp-dl-optie-naam"><?php echo esc_html( $merk ); ?></span>
							<span class="hdp-dl-optie-aantal"><?php echo (int) $aantal; ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
			<?php endif; ?>
			<?php if ( count( $taalvolgorde ) > 1 ) : ?>
				<fieldset class="hdp-dl-filtergroep" id="hdp-filter-taal">
					<legend><?php echo esc_html( HDP_I18N::t( 'filter_taal_bestand' ) ); ?></legend>
					<label class="hdp-dl-optie">
						<input type="radio" name="hdp-dl-taal" value="" checked>
						<span class="hdp-dl-optie-naam"><?php echo esc_html( HDP_I18N::t( 'filter_alle_talen' ) ); ?></span>
					</label>
					<?php foreach ( $taalvolgorde as $taal ) : ?>
						<label class="hdp-dl-optie">
							<input type="radio" name="hdp-dl-taal" value="<?php echo esc_attr( $taal ); ?>" data-label="<?php echo esc_attr( HDP_I18N::t( 'filter_taal_' . $taal ) ); ?>">
							<span class="hdp-dl-optie-naam"><?php echo esc_html( HDP_I18N::t( 'filter_taal_' . $taal ) ); ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
			<?php endif; ?>
			<?php if ( count( $soortvolgorde ) > 1 ) : ?>
				<fieldset class="hdp-dl-filtergroep" id="hdp-filter-soort">
					<legend><?php echo esc_html( HDP_I18N::t( 'filter_soort' ) ); ?></legend>
					<?php foreach ( $soortvolgorde as $soort ) : ?>
						<label class="hdp-dl-optie">
							<input type="checkbox" value="<?php echo esc_attr( $soort ); ?>" data-label="<?php echo esc_attr( HDP_I18N::t( 'soort_' . $soort ) ); ?>">
							<span class="hdp-dl-optie-naam"><?php echo esc_html( HDP_I18N::t( 'soort_' . $soort ) ); ?></span>
							<span class="hdp-dl-optie-aantal"><?php echo (int) $soorten[ $soort ]; ?></span>
						</label>
					<?php endforeach; ?>
				</fieldset>
			<?php endif; ?>
		</details>

		<div class="hdp-dl-hoofd">
		<div class="hdp-dl-werkbalk">
			<div class="hdp-dl-zoekrij">
				<div class="hdp-zoekveld">
					<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
					<input type="text" id="hdp-zoeken" placeholder="<?php echo esc_attr( HDP_I18N::t( 'zoek_placeholder' ) ); ?>" aria-label="<?php echo esc_attr( HDP_I18N::t( 'zoek_placeholder' ) ); ?>" autocomplete="off">
				</div>
				<select id="hdp-sorteer" class="hdp-sorteer-select" aria-label="<?php echo esc_attr( HDP_I18N::t( 'sorteren_label' ) ); ?>">
					<option value="datum-nieuw"><?php echo esc_html( HDP_I18N::t( 'sorteer_nieuw' ) ); ?></option>
					<option value="datum-oud"><?php echo esc_html( HDP_I18N::t( 'sorteer_oud' ) ); ?></option>
					<option value="naam-az"><?php echo esc_html( HDP_I18N::t( 'sorteer_naam' ) ); ?></option>
				</select>
			</div>
			<div class="hdp-filterrij-onder">
				<p class="hdp-telling" aria-live="polite" aria-atomic="true"><strong id="hdp-telling-zichtbaar" data-een="<?php echo esc_attr( HDP_I18N::t( 'telling_een_bestand' ) ); ?>" data-meer="<?php echo esc_attr( HDP_I18N::t( 'telling_bestanden' ) ); ?>"><?php echo esc_html( sprintf( HDP_I18N::t( 1 === count( $downloads ) ? 'telling_een_bestand' : 'telling_bestanden' ), count( $downloads ) ) ); ?></strong></p>
				<div class="hdp-actieve-filters" id="hdp-actieve-filters" data-weghalen="<?php echo esc_attr( HDP_I18N::t( 'filter_weghalen' ) ); ?>"></div>
				<button type="button" class="hdp-wis-filters" id="hdp-wis-filters" hidden><?php echo esc_html( HDP_I18N::t( 'wis_filters' ) ); ?></button>
			</div>
		</div>

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
		</div>
		</div>

		<script>
		(function () {
			var zoekveld = document.getElementById('hdp-zoeken');
			var lijst = document.getElementById('hdp-download-lijst');
			var items = lijst ? lijst.querySelectorAll('.hdp-download-item') : [];
			var tellingEl = document.getElementById('hdp-telling-zichtbaar');
			var leegEl = document.getElementById('hdp-leeg-resultaat');
			var wisKnop = document.getElementById('hdp-wis-filters');
			var sorteerSelect = document.getElementById('hdp-sorteer');
			var actiefVak = document.getElementById('hdp-actieve-filters');
			var filtersVak = document.getElementById('hdp-dl-filters');
			var aantalVak = document.getElementById('hdp-filters-aantal');

			// Op een breed scherm staat de zijkolom altijd open; op een
			// telefoon is het een knop "Filters" die je openklapt.
			var breed = window.matchMedia('(min-width: 901px)');
			function openOpBreed() { if (filtersVak && breed.matches) { filtersVak.open = true; } }
			openOpBreed();
			if (breed.addEventListener) { breed.addEventListener('change', openOpBreed); }

			function gekozen(groep) {
				var vak = document.getElementById(groep);
				return vak ? Array.prototype.slice.call(vak.querySelectorAll('input:checked')).filter(function (i) { return i.value !== ''; }) : [];
			}

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

			function waarden(lijstInputs) { return lijstInputs.map(function (i) { return i.value; }); }

			function filterToepassen() {
				var zoekterm = zoekveld ? zoekveld.value.trim().toLowerCase() : '';
				var merken = gekozen('hdp-filter-merk');
				var taal = gekozen('hdp-filter-taal');
				var soorten = gekozen('hdp-filter-soort');
				var merkW = waarden(merken), taalW = waarden(taal), soortW = waarden(soorten);
				var zichtbaar = 0;
				items.forEach(function (item) {
					// Titel, omschrijving en merk.
					var voldoetZoek = !zoekterm || item.dataset.zoek.indexOf(zoekterm) !== -1;
					var voldoetMerk = !merkW.length || merkW.indexOf(item.dataset.merk) !== -1;
					var voldoetTaal = !taalW.length || item.dataset.talen.split(' ').indexOf(taalW[0]) !== -1;
					var itemSoorten = item.dataset.soorten.split(' ');
					var voldoetSoort = !soortW.length || soortW.some(function (s) { return itemSoorten.indexOf(s) !== -1; });
					var zicht = voldoetZoek && voldoetMerk && voldoetTaal && voldoetSoort;
					item.style.display = zicht ? '' : 'none';
					if (zicht) { zichtbaar++; }
				});
				if (tellingEl) { tellingEl.textContent = (zichtbaar === 1 ? tellingEl.dataset.een : tellingEl.dataset.meer).replace('%d', zichtbaar); }
				if (leegEl) { leegEl.classList.toggle('hdp-zichtbaar', zichtbaar === 0); }

				// Wat er aan staat, als knopjes met een kruisje.
				var alle = merken.concat(taal, soorten);
				if (actiefVak) {
					actiefVak.textContent = '';
					alle.forEach(function (invoer) {
						var knop = document.createElement('button');
						knop.type = 'button';
						knop.className = 'hdp-actief-filter';
						knop.setAttribute('aria-label', actiefVak.dataset.weghalen.replace('%s', invoer.dataset.label));
						knop.appendChild(document.createTextNode(invoer.dataset.label + ' '));
						var kruis = document.createElement('span');
						kruis.setAttribute('aria-hidden', 'true');
						kruis.textContent = '×';
						knop.appendChild(kruis);
						knop.addEventListener('click', function () {
							if (invoer.type === 'radio') {
								var allesTalen = document.querySelector('#hdp-filter-taal input[value=""]');
								if (allesTalen) { allesTalen.checked = true; }
							} else {
								invoer.checked = false;
							}
							filterToepassen();
						});
						actiefVak.appendChild(knop);
					});
				}
				if (aantalVak) { aantalVak.textContent = alle.length ? '(' + alle.length + ')' : ''; }
				if (wisKnop) { wisKnop.hidden = !(alle.length || zoekterm); }
			}

			if (zoekveld) { zoekveld.addEventListener('input', filterToepassen); }
			if (filtersVak) { filtersVak.addEventListener('change', filterToepassen); }

			if (wisKnop) {
				wisKnop.addEventListener('click', function () {
					if (zoekveld) { zoekveld.value = ''; }
					if (filtersVak) {
						filtersVak.querySelectorAll('input[type="checkbox"]').forEach(function (i) { i.checked = false; });
						var allesTalen = filtersVak.querySelector('#hdp-filter-taal input[value=""]');
						if (allesTalen) { allesTalen.checked = true; }
					}
					filterToepassen();
				});
			}
		})();
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * In welke taal (of talen) dit bestand is: 'nl', 'fr', 'en'.
	 *
	 * De opgeslagen regio's: 'nl' en 'be' zijn allebei Nederlandstalig,
	 * 'be-fr' Frans, 'en' Engels. Zonder regio is het voor iedereen bedoeld
	 * en telt het in elke taal mee.
	 *
	 * @return string[]
	 */
	public static function talen_van( $download_id ) {
		$regios = HDP_Downloads_CPT::get_regios( $download_id );
		if ( ! $regios ) {
			return array( 'nl', 'fr', 'en' );
		}
		$talen = array();
		if ( array_intersect( $regios, array( 'nl', 'be' ) ) ) {
			$talen[] = 'nl';
		}
		if ( in_array( 'be-fr', $regios, true ) ) {
			$talen[] = 'fr';
		}
		if ( in_array( 'en', $regios, true ) ) {
			$talen[] = 'en';
		}
		return $talen;
	}

	/**
	 * Welke soorten bestand er bij deze download horen (hoofdbestand en
	 * extra formaten): 'pdf', 'excel', 'afbeelding', 'zip' of 'overig'.
	 *
	 * @return string[]
	 */
	public static function soorten_van( $download_id ) {
		$per_extensie = array(
			'PDF'  => 'pdf',
			'XLS'  => 'excel',
			'XLSX' => 'excel',
			'XLSM' => 'excel',
			'CSV'  => 'excel',
			'JPG'  => 'afbeelding',
			'JPEG' => 'afbeelding',
			'PNG'  => 'afbeelding',
			'WEBP' => 'afbeelding',
			'GIF'  => 'afbeelding',
			'SVG'  => 'afbeelding',
			'ZIP'  => 'zip',
		);
		$soorten = array();
		foreach ( HDP_Downloads_CPT::bestanden( $download_id ) as $attachment_id ) {
			$extensie  = HDP_Downloads_CPT::type_label( $download_id, $attachment_id );
			$soorten[] = isset( $per_extensie[ $extensie ] ) ? $per_extensie[ $extensie ] : 'overig';
		}
		return array_values( array_unique( $soorten ) );
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
		// Eigen voorbeeldafbeelding, een foto, of pagina 1 van de PDF; 0 = een icoon met het type.
		$bron      = HDP_Downloads_CPT::voorbeeld_bron( $download->ID );
		$openen    = HDP_Downloads_CPT::download_url( $download->ID, 0, $bekijkbaar ? 'bekijken' : '' );
		$voorbeeld = HDP_Downloads_CPT::download_url( $download->ID, 0, 'voorbeeld' );
		// Lukt het plaatje toch niet, dan blijft het icoon staan in plaats van een kapot beeld.
		$bij_fout = "this.parentNode.classList.add('is-leeg');this.remove();";

		ob_start();
		?>
		<div class="hdp-download-item<?php echo $galerij ? ' hdp-content-kaart' : ''; ?>"
			data-titel="<?php echo esc_attr( mb_strtolower( $download->post_title ) ); ?>"
			data-zoek="<?php echo esc_attr( mb_strtolower( implode( ' ', array_filter( array( $download->post_title, $omschrijving, $merk ) ) ) ) ); ?>"
			data-merk="<?php echo esc_attr( $merk ); ?>"
			data-regios="<?php echo esc_attr( implode( ' ', $regios ) ); ?>"
			data-talen="<?php echo esc_attr( implode( ' ', self::talen_van( $download->ID ) ) ); ?>"
			data-soorten="<?php echo esc_attr( implode( ' ', self::soorten_van( $download->ID ) ) ); ?>"
			data-datum="<?php echo esc_attr( get_post_time( 'U', true, $download ) ); ?>"
			data-nieuw="<?php echo $nieuw ? '1' : '0'; ?>">
			<?php if ( $galerij ) : ?>
				<a class="hdp-content-beeld" href="<?php echo esc_url( $openen ); ?>" <?php echo $bekijkbaar ? 'target="_blank" rel="noopener"' : ''; ?> tabindex="-1" aria-hidden="true">
					<?php if ( $bron ) : ?>
						<img src="<?php echo esc_url( $voorbeeld ); ?>" alt="" loading="lazy" onerror="<?php echo esc_attr( $bij_fout ); ?>">
					<?php endif; ?>
					<span class="hdp-content-beeld-type"><?php echo esc_html( HDP_Downloads_CPT::type_label( $download->ID ) ); ?></span>
				</a>
			<?php else : ?>
				<a class="hdp-download-voorbeeld<?php echo $bron ? '' : ' is-leeg'; ?>" href="<?php echo esc_url( $openen ); ?>" <?php echo $bekijkbaar ? 'target="_blank" rel="noopener"' : ''; ?> tabindex="-1" aria-hidden="true">
					<?php if ( $bron ) : ?>
						<img src="<?php echo esc_url( $voorbeeld ); ?>" alt="" loading="lazy" width="56" height="74" onerror="<?php echo esc_attr( $bij_fout ); ?>">
					<?php endif; ?>
					<svg class="hdp-download-voorbeeld-icoon" viewBox="0 0 24 24" fill="none" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/></svg>
					<span class="hdp-download-type"><?php echo esc_html( HDP_Downloads_CPT::type_label( $download->ID ) ); ?></span>
				</a>
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
