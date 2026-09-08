<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rendert de downloads- en content-pagina's: welkomstkop + de beveiligde,
 * doorzoekbare/filterbare/sorteerbare lijst (zie render_downloads_lijst()).
 */
class HDP_Downloads_Render {

	public static function render_downloads_pagina( $a ) {
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
			<section class="hdp-welkom alignfull hdp-welkom-zonder-hero">
				<div class="hdp-welkom-inner">
					<a class="hdp-terug-boven" href="<?php echo esc_url( home_url( '/dealerportaal/' ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'terug_naar_portaal' ) ); ?></a>
					<div class="hdp-welkom-top">
						<h1><?php echo esc_html( $titel ); ?></h1>
						<a class="hdp-btn-logout" href="<?php echo esc_url( wp_logout_url( home_url( '/dealerportaal/' ) ) ); ?>"><?php echo HDP_Icons::svg_icoon( 'uitloggen' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?><?php echo esc_html( HDP_I18N::t( 'btn_uitloggen' ) ); ?></a>
					</div>
					<p><?php echo esc_html( $omschrijving ); ?></p>
				</div>
			</section>
			<section class="hdp-downloads">
				<?php echo self::render_downloads_lijst( 'download' ); // phpcs:ignore WordPress.Security.EscapeOutput -- reeds ge-escaped in render_downloads_lijst(). ?>
				<p class="hdp-terug"><a class="hdp-btn hdp-btn-secundair" href="<?php echo esc_url( home_url( '/dealerportaal/' ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'terug_naar_portaal' ) ); ?></a></p>
			</section>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Bibliotheek met content voor social media/advertenties — zelfde
	 * opzet als de downloadspagina (zoeken, merk-/regiofilter, beveiligd
	 * endpoint), maar dan gefilterd op categorie "content" i.p.v.
	 * "download". Zie render_downloads_lijst().
	 */
	public static function render_content_pagina( $a ) {
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
			<section class="hdp-welkom alignfull hdp-welkom-zonder-hero">
				<div class="hdp-welkom-inner">
					<a class="hdp-terug-boven" href="<?php echo esc_url( home_url( '/dealerportaal/' ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'terug_naar_portaal' ) ); ?></a>
					<div class="hdp-welkom-top">
						<h1><?php echo esc_html( $titel ); ?></h1>
						<a class="hdp-btn-logout" href="<?php echo esc_url( wp_logout_url( home_url( '/dealerportaal/' ) ) ); ?>"><?php echo HDP_Icons::svg_icoon( 'uitloggen' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?><?php echo esc_html( HDP_I18N::t( 'btn_uitloggen' ) ); ?></a>
					</div>
					<p><?php echo esc_html( $omschrijving ); ?></p>
				</div>
			</section>
			<section class="hdp-downloads">
				<?php echo self::render_downloads_lijst( 'content' ); // phpcs:ignore WordPress.Security.EscapeOutput -- reeds ge-escaped in render_downloads_lijst(). ?>
				<p class="hdp-terug"><a class="hdp-btn hdp-btn-secundair" href="<?php echo esc_url( home_url( '/dealerportaal/' ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'terug_naar_portaal' ) ); ?></a></p>
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
	private static function render_downloads_lijst( $categorie = 'download' ) {
		$meta_query = 'content' === $categorie
			? array( array( 'key' => '_hdp_categorie', 'value' => 'content' ) )
			: array(
				'relation' => 'OR',
				array( 'key' => '_hdp_categorie', 'value' => 'download' ),
				array( 'key' => '_hdp_categorie', 'compare' => 'NOT EXISTS' ),
			);

		$downloads = get_posts(
			array(
				'post_type'      => HDP_Downloads_CPT::POST_TYPE,
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- kleine, beheerste dataset (dealerdownloads), geen gebruikersinvoer.
			)
		);

		if ( ! $downloads ) {
			$leeg_sleutel = 'content' === $categorie ? 'nog_geen_content' : 'nog_geen_downloads';
			return '<p class="hdp-nog-niet">' . esc_html( HDP_I18N::t( $leeg_sleutel ) ) . '</p>';
		}

		// Taalgebonden zichtbaarheid: een NL-taalbezoeker ziet bestanden
		// getagd met regio 'nl' of 'be', een FR-taalbezoeker alleen 'be-fr'
		// — zo verschijnt een bestand automatisch bij de juiste taalgroep in
		// plaats van dat de dealer dat zelf via de regiofilter moet uitzoeken.
		// Bestanden zonder regio-tag (van vóór dit onderscheid, of bewust
		// voor iedereen bedoeld) blijven in beide talen zichtbaar.
		$toegestane_regios = HDP_I18N::is_frans() ? array( 'be-fr' ) : array( 'nl', 'be' );
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
			$leeg_sleutel = 'content' === $categorie ? 'geen_content_taal' : 'geen_downloads_taal';
			return '<p class="hdp-nog-niet">' . esc_html( HDP_I18N::t( $leeg_sleutel ) ) . '</p>';
		}

		// Merkgebonden zichtbaarheid: een dealer met een ingestelde
		// merkenlijst (hdp_merken) ziet alleen bestanden van die merken (of
		// zonder merk-tag) — zelfde "geen tag/geen restrictie = zichtbaar
		// voor iedereen"-principe als bij taal/regio hierboven. Een dealer
		// zonder ingestelde merken blijft alles zien, zodat dealers die nog
		// niet aan een merk gekoppeld zijn niet per ongeluk niets meer zien.
		$toegestane_merken = HDP_Merken::naar_array( get_user_meta( get_current_user_id(), 'hdp_merken', true ) );
		if ( $toegestane_merken ) {
			$downloads = array_values(
				array_filter(
					$downloads,
					static function ( $download ) use ( $toegestane_merken ) {
						$merk = HDP_Downloads_CPT::get_merk( $download->ID );
						return ! $merk || in_array( $merk, $toegestane_merken, true );
					}
				)
			);

			if ( ! $downloads ) {
				$leeg_sleutel = 'content' === $categorie ? 'geen_content_merk' : 'geen_downloads_merk';
				return '<p class="hdp-nog-niet">' . esc_html( HDP_I18N::t( $leeg_sleutel ) ) . '</p>';
			}
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
					<span class="hdp-filtergroep-label"><?php echo esc_html( HDP_I18N::t( 'filter_regio' ) ); ?></span>
					<div class="hdp-chips" id="hdp-regio-chips">
						<button type="button" class="hdp-chip hdp-chip-actief" data-regio="alle"><?php echo esc_html( HDP_I18N::t( 'filter_alles' ) ); ?></button>
						<?php if ( HDP_I18N::is_frans() ) : ?>
							<button type="button" class="hdp-chip" data-regio="be-fr"><?php echo esc_html( HDP_I18N::t( 'filter_belgie_fr' ) ); ?></button>
						<?php else : ?>
							<button type="button" class="hdp-chip" data-regio="nl"><?php echo esc_html( HDP_I18N::t( 'filter_nederland' ) ); ?></button>
							<button type="button" class="hdp-chip" data-regio="be"><?php echo esc_html( HDP_I18N::t( 'filter_belgie' ) ); ?></button>
						<?php endif; ?>
					</div>
				</div>
				<?php if ( $merken ) : ?>
				<div class="hdp-filtergroep">
					<span class="hdp-filtergroep-label"><?php echo esc_html( HDP_I18N::t( 'filter_merk' ) ); ?></span>
					<div class="hdp-chips" id="hdp-merk-chips">
						<?php foreach ( $merken as $merk ) : ?>
							<button type="button" class="hdp-chip" data-merk="<?php echo esc_attr( $merk ); ?>"><?php echo esc_html( $merk ); ?></button>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endif; ?>
				<div class="hdp-filtergroep">
					<span class="hdp-filtergroep-label"><?php echo esc_html( HDP_I18N::t( 'sorteren_label' ) ); ?></span>
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

		<div class="hdp-download-lijst" id="hdp-download-lijst">
			<?php foreach ( $downloads as $download ) : ?>
				<?php
				$merk   = HDP_Downloads_CPT::get_merk( $download->ID );
				$regios = HDP_Downloads_CPT::get_regios( $download->ID );
				?>
				<div class="hdp-download-item"
					data-titel="<?php echo esc_attr( strtolower( $download->post_title ) ); ?>"
					data-merk="<?php echo esc_attr( $merk ); ?>"
					data-regios="<?php echo esc_attr( implode( ' ', $regios ) ); ?>"
					data-datum="<?php echo esc_attr( get_post_time( 'U', true, $download ) ); ?>">
					<span class="hdp-download-type"><?php echo esc_html( HDP_Downloads_CPT::type_label( $download->ID ) ); ?></span>
					<span class="hdp-download-info">
						<strong><?php echo esc_html( $download->post_title ); ?></strong>
						<?php if ( $download->post_content ) : ?>
							<span><?php echo esc_html( wp_strip_all_tags( $download->post_content ) ); ?></span>
						<?php endif; ?>
					</span>
					<a class="hdp-btn" href="<?php echo esc_url( HDP_Downloads_CPT::download_url( $download->ID ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'btn_downloaden' ) ); ?></a>
				</div>
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

			function filterToepassen() {
				var zoekterm = zoekveld ? zoekveld.value.trim().toLowerCase() : '';
				var zichtbaar = 0;
				items.forEach(function (item) {
					var voldoetZoek = !zoekterm || item.dataset.titel.indexOf(zoekterm) !== -1;
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
}
