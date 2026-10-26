<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rendert het dealerportaal zelf: het inlogscherm (via HDP_Login) voor
 * uitgelogde/niet-goedgekeurde bezoekers, en na inloggen de welkomstkop,
 * "snel herbestellen", de 4 hoofdkaarten en de infosectie.
 *
 * Bewerkbare teksten (kaarten, intro's) hebben een Nederlands en een
 * Frans blokattribuut; HDP_I18N::kies() kiest de juiste op basis van de
 * taalswitch en valt terug op het Nederlands als het Frans nog leeg is.
 * Vaste teksten (labels, knoppen, meldingen) komen uit HDP_I18N::t().
 */
class HDP_Portal_Render {

	/** Tot wanneer de dealer de melding over nieuwe documenten heeft weggeklikt. */
	const META_NIEUW_WEG = 'hdp_nieuw_weggeklikt_tot';

	const NIEUW_WEG_VELD  = 'hdp_nieuw_weg';
	const NIEUW_WEG_NONCE = 'hdp_nieuw_wegklikken';

	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'verwerk_wegklikken' ) );
	}

	/**
	 * De melding over nieuwe documenten wegklikken.
	 *
	 * Bewaart niet "weggeklikt ja/nee" maar tot welk moment: komt er daarna
	 * een document bij, dan is de melding weer terecht en verschijnt hij
	 * vanzelf. Met een enkele vlag zou de dealer hem één keer wegklikken en
	 * er daarna nooit meer iets van horen.
	 */
	public static function verwerk_wegklikken() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- de nonce wordt hieronder gecontroleerd zodra we weten dát dit onze knop is.
		if ( ! isset( $_POST[ self::NIEUW_WEG_VELD ] ) || ! is_user_logged_in() ) {
			return;
		}

		check_admin_referer( self::NIEUW_WEG_NONCE );

		update_user_meta( get_current_user_id(), self::META_NIEUW_WEG, time() );

		// Terug naar dezelfde pagina, zodat een verversing niet opnieuw
		// verstuurt.
		wp_safe_redirect( remove_query_arg( array() ) );
		exit;
	}

	public static function render_dealerportaal( $a ) {
		ob_start();

		if ( is_user_logged_in() ) {
			self::render_portal_content();
		} else {
			HDP_Login::render_login( HDP_Login::login_foutmelding(), $a );
		}

		return ob_get_clean();
	}

	private static function render_portal_content() {
		$user = wp_get_current_user();

		if ( ! HDP_Roles::mag_portaal_zien( $user->ID ) ) {
			?>
			<div class="hdp-login-sectie">
				<div class="hdp-login-kaart hdp-wacht-kaart">
					<?php HDP_Icons::render_icoon( 'wachten' ); ?>
					<h1><?php echo esc_html( HDP_I18N::t( 'account_titel' ) ); ?></h1>
					<p class="hdp-intro"><?php echo esc_html( HDP_I18N::t( 'account_tekst' ) ); ?></p>
					<?php
					// Een dealer die hier belandt kan nergens heen. Vertel dus
					// wat er nu gebeurt, hoe lang dat duurt en waar ze terecht
					// kunnen als het te lang duurt — anders is dit scherm het
					// eindstation van de allereerste kennismaking met het
					// portaal.
					?>
					<p class="hdp-wacht-stappen"><?php echo esc_html( HDP_I18N::t( 'account_stappen' ) ); ?></p>
					<?php self::render_contactregel(); ?>
				</div>
			</div>
			<?php
			return;
		}

		?>
		<div class="hdp-welkomscherm">
			<?php self::render_welkomkop( $user ); ?>
			<?php // Berichten van Homburg staan bovenaan: een mededeling die je pas tegenkomt nadat je ergens heen geklikt hebt, is te laat. ?>
			<?php HDP_Berichten::render(); ?>
			<?php self::render_vandaag(); ?>
			<?php
			// "Nieuw voor jou" staat sinds de nieuwe indeling als eigen blok
			// in de pagina (links naast de merken), zie render_nieuw_voor_jou().
			// "Snel herbestellen" staat niet meer op de startpagina: het zit
			// bij de bestellingen, en "artikelnummers plakken" staat onder het
			// zoekveld.
			?>
		</div>
		<?php
	}

	/** De openingstijden van het magazijn: werkdagen, van ... tot ... uur. */
	const MAGAZIJN_OPEN  = 8;
	const MAGAZIJN_DICHT = 17;

	/**
	 * Is het magazijn nu bereikbaar, en zo niet: wanneer weer?
	 *
	 * Een dealer met een vraag wil weten of bellen nu zin heeft. Dat stond
	 * er alleen als tijden in een lopende tekst.
	 *
	 * @param int $nu Tijdstempel in de tijdzone van de site.
	 * @return array{open: bool, tekst: string}
	 */
	public static function magazijn_status( $nu ) {
		// gmdate en niet wp_date: $nu is al de lokale tijd (strtotime op current_time()); wp_date zou de tijdzone er nóg een keer bij optellen.
		$dag = (int) gmdate( 'N', $nu ); // 1 = maandag, 7 = zondag.
		$uur = (int) gmdate( 'G', $nu );
		$tot = sprintf( '%02d:00', self::MAGAZIJN_DICHT );
		$van = sprintf( '%02d:00', self::MAGAZIJN_OPEN );

		if ( $dag <= 5 && $uur >= self::MAGAZIJN_OPEN && $uur < self::MAGAZIJN_DICHT ) {
			return array(
				'open'  => true,
				'tekst' => sprintf( HDP_I18N::t( 'magazijn_open' ), $tot ),
			);
		}

		// Wanneer weer: vandaag nog (vóór openingstijd op een werkdag),
		// morgen, of de eerstvolgende maandag.
		if ( $dag <= 5 && $uur < self::MAGAZIJN_OPEN ) {
			$wanneer = sprintf( HDP_I18N::t( 'magazijn_vandaag' ), $van );
		} elseif ( $dag <= 4 ) {
			$wanneer = sprintf( HDP_I18N::t( 'magazijn_morgen' ), $van );
		} else {
			$wanneer = sprintf( HDP_I18N::t( 'magazijn_maandag' ), $van );
		}

		return array(
			'open'  => false,
			'tekst' => sprintf( HDP_I18N::t( 'magazijn_dicht' ), $wanneer ),
		);
	}

	/**
	 * "Vandaag voor jou": vier tegels met wat er voor de dealer klaarligt.
	 *
	 * De startpagina had grote merkentegels en naslag, maar zei niets over
	 * wat er op de dealer wacht. Dit zijn de vier vragen waarmee iemand
	 * inlogt: moet ik iets doen bij garantie, wat loopt er nog, is er iets
	 * nieuws, en ligt er nog iets in mijn winkelwagen.
	 */
	private static function render_vandaag() {
		$tegels = array();

		// Garantie: alleen als die koppeling er is. De telling is kort
		// gebufferd en dezelfde als die van het rode cijfer in het zijmenu.
		if ( class_exists( 'HDP_Garantie' ) && HDP_Garantie::beschikbaar() ) {
			$telling  = HDP_Zijbalk::claimtellingen();
			$wacht    = (int) $telling['wacht'];
			$tegels[] = array(
				'url'     => add_query_arg( 'toon', $wacht ? 'wacht' : 'alles', home_url( '/garantie/' ) ),
				'label'   => HDP_I18N::t( 'tegel_garantie' ),
				'getal'   => $wacht,
				'titel'   => HDP_I18N::t( $wacht ? ( 1 === $wacht ? 'tegel_garantie_wacht_een' : 'tegel_garantie_wacht_meer' ) : 'tegel_garantie_niets' ),
				'tekst'   => $telling['loopt'] ? sprintf( HDP_I18N::t( 'tegel_garantie_loopt' ), (int) $telling['loopt'] ) : HDP_I18N::t( 'tegel_garantie_naar' ),
				'aandacht' => $wacht > 0,
			);
		}

		if ( function_exists( 'wc_get_orders' ) ) {
			$lopend   = wc_get_orders(
				array(
					'customer_id' => get_current_user_id(),
					'status'      => array( 'processing', 'on-hold' ),
					'orderby'     => 'date',
					'order'       => 'DESC',
					'limit'       => 50,
				)
			);
			$laatste  = $lopend ? $lopend[0] : null;
			$tegels[] = array(
				'url'     => add_query_arg( 'hdp_bs', 'processing', wc_get_account_endpoint_url( 'orders' ) ),
				'label'   => HDP_I18N::t( 'tegel_bestellingen' ),
				'getal'   => count( $lopend ),
				'titel'   => HDP_I18N::t( $lopend ? 'tegel_bestellingen_loopt' : 'tegel_bestellingen_geen' ),
				'tekst'   => $laatste && $laatste->get_date_created()
					? sprintf( HDP_I18N::t( 'tegel_bestellingen_laatste' ), $laatste->get_order_number(), HDP_I18N::datum( 'j F', $laatste->get_date_created()->getOffsetTimestamp() ) )
					: HDP_I18N::t( 'tegel_bestellingen_alle' ),
				'aandacht' => false,
			);
		}

		$nieuw    = count( self::nieuwe_downloads() );
		$tegels[] = array(
			'url'     => home_url( '/downloads/' ),
			'label'   => HDP_I18N::t( 'tegel_downloads' ),
			'getal'   => $nieuw,
			'titel'   => HDP_I18N::t( $nieuw ? 'tegel_downloads_nieuw' : 'tegel_downloads_geen' ),
			'tekst'   => HDP_I18N::t( 'tegel_downloads_naar' ),
			'aandacht' => false,
		);

		if ( function_exists( 'WC' ) && WC()->cart ) {
			$in_wagen = (int) WC()->cart->get_cart_contents_count();
			$tegels[] = array(
				'url'     => $in_wagen ? wc_get_cart_url() : wc_get_page_permalink( 'shop' ),
				'label'   => HDP_I18N::t( 'tegel_winkelwagen' ),
				'getal'   => $in_wagen,
				'titel'   => HDP_I18N::t( $in_wagen ? 'tegel_winkelwagen_vol' : 'tegel_winkelwagen_leeg' ),
				'tekst'   => HDP_I18N::t( $in_wagen ? 'tegel_winkelwagen_afronden' : 'tegel_winkelwagen_naar_webshop' ),
				'aandacht' => false,
			);
		}
		?>
		<section class="hdp-vandaag" aria-labelledby="hdp-vandaag-kop">
			<h2 id="hdp-vandaag-kop" class="hdp-vandaag-kop"><?php echo esc_html( HDP_I18N::t( 'vandaag_kop' ) ); ?></h2>
			<div class="hdp-vandaag-tegels">
				<?php foreach ( $tegels as $tegel ) : ?>
					<a class="hdp-vandaag-tegel<?php echo $tegel['aandacht'] ? ' hdp-vandaag-tegel-aandacht' : ''; ?>" href="<?php echo esc_url( $tegel['url'] ); ?>">
						<span class="hdp-vandaag-tegel-boven">
							<span class="hdp-vandaag-tegel-label"><?php echo esc_html( $tegel['label'] ); ?></span>
							<span class="hdp-vandaag-tegel-getal"><?php echo esc_html( (string) $tegel['getal'] ); ?></span>
						</span>
						<strong class="hdp-vandaag-tegel-titel"><?php echo esc_html( $tegel['titel'] ); ?></strong>
						<span class="hdp-vandaag-tegel-tekst"><?php echo esc_html( $tegel['tekst'] ); ?> &rarr;</span>
					</a>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * Het blok "Nieuw voor jou" (homburg/nieuw-voor-jou), links op de
	 * startpagina.
	 *
	 * Zelfde lijst als altijd (render_nieuw_melding()), maar staat er niets
	 * nieuws, dan een rustige regel in plaats van niets: in een kolom naast
	 * de merken zou een lege plek eruitzien alsof er iets kapot is.
	 */
	public static function render_nieuw_voor_jou() {
		if ( ! is_user_logged_in() || ! HDP_Roles::mag_portaal_zien( get_current_user_id() ) ) {
			return '';
		}

		ob_start();
		self::render_nieuw_melding();
		$lijst = ob_get_clean();

		if ( '' !== trim( $lijst ) ) {
			return $lijst;
		}

		ob_start();
		?>
		<section class="hdp-nieuw-sectie hdp-nieuw-sectie-leeg" aria-labelledby="hdp-nieuw-kop">
			<div class="hdp-nieuw-kop">
				<h2 id="hdp-nieuw-kop"><?php echo esc_html( HDP_I18N::t( 'nieuw_voor_u' ) ); ?></h2>
				<div class="hdp-nieuw-kop-rechts">
					<a href="<?php echo esc_url( home_url( '/downloads/' ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'nieuw_alle_downloads' ) ); ?></a>
				</div>
			</div>
			<p class="hdp-nieuw-leeg"><?php echo esc_html( HDP_I18N::t( 'nieuw_niets' ) ); ?></p>
		</section>
		<?php
		return ob_get_clean();
	}

	/**
	 * De kop van het welkomscherm: groet, datum en een zoekveld.
	 *
	 * Verving de paginabrede foto. Die foto nam driehonderd pixels van het
	 * eerste scherm in beslag en vertelde niets; een dealer die inlogt wil
	 * weten waar hij is, welke dag het is en waar hij iets kan zoeken.
	 *
	 * @param WP_User $user De ingelogde dealer.
	 */
	private static function render_welkomkop( $user ) {
		// Via de lokale tijd als tekst en niet via current_time('timestamp'):
		// dat laatste is afgeraden, en dit levert hetzelfde soort tijdstempel
		// op als overal elders in deze code (strtotime op een datumtekst),
		// waar HDP_I18N::datum() op rekent.
		$nu = strtotime( current_time( 'mysql' ) );
		?>
		<?php $magazijn = self::magazijn_status( $nu ); ?>
		<div class="hdp-welkomkop">
			<div class="hdp-welkomkop-boven">
				<div class="hdp-welkomkop-tekst">
					<h1><?php echo esc_html( self::groet( $nu ) ); ?>, <?php echo esc_html( $user->display_name ); ?></h1>
					<p class="hdp-welkomkop-datum"><?php echo esc_html( HDP_I18N::datum( 'l j F Y', $nu ) ); ?></p>
				</div>
				<p class="hdp-magazijn-status<?php echo $magazijn['open'] ? ' hdp-magazijn-open' : ''; ?>">
					<span class="hdp-magazijn-bol" aria-hidden="true"></span><?php echo esc_html( $magazijn['tekst'] ); ?>
				</p>
			</div>
			<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
				<?php
				// Over de volle breedte en onder de groet, niet ernaast in de
				// hoek: dit is de snelste weg de webshop in en daarmee het
				// meestgebruikte ding op deze pagina.
				?>
				<form class="hdp-welkomkop-zoek" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
					<label for="hdp-home-zoek" class="hdp-vh"><?php echo esc_html( HDP_I18N::t( 'home_zoek_label' ) ); ?></label>
					<input id="hdp-home-zoek" type="search" name="s" placeholder="<?php echo esc_attr( HDP_I18N::t( 'home_zoek_plaatshouder' ) ); ?>">
					<?php // Zonder dit zoekt WordPress door berichten en pagina's heen; hier gaat het altijd om artikelen. ?>
					<input type="hidden" name="post_type" value="product">
					<button type="submit"><?php echo esc_html( HDP_I18N::t( 'home_zoek_knop' ) ); ?></button>
				</form>
				<?php // De drie andere snelle wegen de webshop in, direct onder het zoekveld. ?>
				<nav class="hdp-welkomkop-snel" aria-label="<?php echo esc_attr( HDP_I18N::t( 'home_snel_label' ) ); ?>">
					<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'snelbestellen' ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'snelbestel_kop' ) ); ?></a>
					<a href="<?php echo esc_url( wc_get_account_endpoint_url( 'favorieten' ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'home_snel_favorieten' ) ); ?></a>
					<a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'home_snel_webshop' ) ); ?> &rarr;</a>
				</nav>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Goedemorgen / goedemiddag / goedenavond.
	 *
	 * Met eigen woorden en niet via de taalbestanden van WordPress: die
	 * bestaan in dit project niet in het Frans, dus alle vertaling loopt
	 * via HDP_I18N.
	 *
	 * @param int $tijdstip Tijdstempel in de tijdzone van de site.
	 * @return string
	 */
	private static function groet( $tijdstip ) {
		// Zie magazijn_status(): de tijdstempel is al lokaal, dus gmdate.
		$uur = (int) gmdate( 'G', $tijdstip );

		if ( $uur < 12 ) {
			return HDP_I18N::t( 'groet_ochtend' );
		}

		return $uur < 18 ? HDP_I18N::t( 'groet_middag' ) : HDP_I18N::t( 'groet_avond' );
	}

	/**
	 * "Er staat iets nieuws voor u klaar"-regel op de startpagina.
	 *
	 * Zonder dit is de automatische FileBird-koppeling eenrichtingsverkeer:
	 * bestanden verschijnen vanzelf in het portaal, maar een dealer moet
	 * gáán kijken om dat te ontdekken. Telt bewust via
	 * HDP_Downloads_Render::zichtbare_downloads(), dezelfde bron als de
	 * downloadspagina zelf — anders kan hier "3 nieuw" staan terwijl de
	 * dealer er door zijn merkrechten nul van te zien krijgt.
	 */
	private static function render_nieuw_melding() {
		$nieuwe = self::nieuwe_downloads();
		if ( ! $nieuwe ) {
			return;
		}

		// Niet alles: drie regels is genoeg om te laten zien wát er klaarstaat
		// zonder dat de startpagina een tweede downloadspagina wordt.
		$tonen = array_slice( $nieuwe, 0, 3 );
		$rest  = count( $nieuwe ) - count( $tonen );
		?>
		<section class="hdp-nieuw-sectie" aria-labelledby="hdp-nieuw-kop">
			<div class="hdp-nieuw-kop">
				<h2 id="hdp-nieuw-kop"><?php echo esc_html( HDP_I18N::t( 'nieuw_voor_u' ) ); ?></h2>
				<div class="hdp-nieuw-kop-rechts">
					<a href="<?php echo esc_url( home_url( '/downloads/' ) ); ?>"><?php echo esc_html( HDP_I18N::t( 'nieuw_alle_downloads' ) ); ?></a>
					<?php // Een eigen formulier en geen knop binnen een link: dat laatste is geen geldige opbouw. ?>
					<form class="hdp-nieuw-weg" method="post">
						<?php wp_nonce_field( self::NIEUW_WEG_NONCE ); ?>
						<input type="hidden" name="<?php echo esc_attr( self::NIEUW_WEG_VELD ); ?>" value="1">
						<button type="submit" title="<?php echo esc_attr( HDP_I18N::t( 'nieuw_melding_weg' ) ); ?>">
							<svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><line x1="6" y1="6" x2="18" y2="18"></line><line x1="18" y1="6" x2="6" y2="18"></line></svg>
							<span><?php echo esc_html( HDP_I18N::t( 'nieuw_melding_weg' ) ); ?></span>
						</button>
					</form>
				</div>
			</div>

			<div class="hdp-nieuw-lijst">
				<?php foreach ( $tonen as $i => $download ) : ?>
					<?php
					$merk    = (string) get_post_meta( $download->ID, '_hdp_merk', true );
					$wanneer = HDP_I18N::datum( 'j F', (int) get_post_time( 'U', false, $download->ID ) );
					?>
					<a class="hdp-nieuw-regel" href="<?php echo esc_url( home_url( '/downloads/' ) ); ?>">
						<span class="hdp-nieuw-regel-icoon<?php echo 0 === $i ? ' hdp-nieuw-regel-icoon-vers' : ''; ?>" aria-hidden="true">
							<?php echo HDP_Icons::svg_icoon( 'downloads' ); // phpcs:ignore WordPress.Security.EscapeOutput -- vaste, statische SVG. ?>
						</span>
						<span class="hdp-nieuw-regel-tekst">
							<strong><?php echo esc_html( get_the_title( $download->ID ) ); ?></strong>
							<span><?php echo esc_html( $merk ? $merk . ' · ' . $wanneer : $wanneer ); ?></span>
						</span>
						<?php if ( 0 === $i ) : ?>
							<span class="hdp-nieuw-badge"><?php echo esc_html( HDP_I18N::t( 'badge_nieuw' ) ); ?></span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>

				<?php if ( $rest > 0 ) : ?>
					<a class="hdp-nieuw-regel hdp-nieuw-regel-rest" href="<?php echo esc_url( home_url( '/downloads/' ) ); ?>">
						<?php echo esc_html( 1 === $rest ? HDP_I18N::t( 'nieuw_nog_een' ) : sprintf( HDP_I18N::t( 'nieuw_nog_meer' ), $rest ) ); ?>
					</a>
				<?php endif; ?>
			</div>
		</section>
		<?php
	}

	/**
	 * De documenten die voor deze dealer nieuw zijn, nieuwste eerst.
	 *
	 * Telt via HDP_Downloads_Render::zichtbare_downloads(), dezelfde bron
	 * als de downloadspagina — anders kan hier iets staan wat de dealer
	 * door zijn merkrechten niet eens mag zien.
	 *
	 * @return array Lijst WP_Post.
	 */
	private static function nieuwe_downloads() {
		// Alles van vóór het wegklikken telt niet meer mee; wat daarna is
		// toegevoegd wél.
		$sinds  = (int) get_user_meta( get_current_user_id(), self::META_NIEUW_WEG, true );
		$nieuwe = array();

		foreach ( HDP_Downloads_Render::zichtbare_downloads( 'download' )['downloads'] as $download ) {
			if ( ! HDP_Downloads_CPT::is_nieuw( $download->ID ) ) {
				continue;
			}
			if ( $sinds && (int) get_post_time( 'U', true, $download->ID ) <= $sinds ) {
				continue;
			}
			$nieuwe[] = $download;
		}

		usort(
			$nieuwe,
			static function ( $a, $b ) {
				return (int) get_post_time( 'U', true, $b->ID ) - (int) get_post_time( 'U', true, $a->ID );
			}
		);

		return $nieuwe;
	}

	/** Contactgegevens uit de plugin-instellingen; stil als die niet ingevuld zijn. */
	private static function render_contactregel() {
		$email    = HDP_Settings::get( 'contact_email' );
		$telefoon = HDP_Settings::get( 'contact_telefoon' );

		if ( ! $email && ! $telefoon ) {
			return;
		}
		?>
		<p class="hdp-wacht-contact">
			<?php echo esc_html( HDP_I18N::t( 'account_duurt_lang' ) ); ?>
		</p>
		<?php // Een <div> (het icoon-omhulsel van HDP_Icons) mag niet binnen een <p>: browsers sluiten die alinea dan voortijdig en de opmaak valt uiteen. ?>
		<div class="hdp-wacht-gegevens">
			<?php if ( $telefoon ) : ?>
				<a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $telefoon ) ); ?>">
					<?php HDP_Icons::render_icoon( 'tel', 'hdp-inline-icoon' ); ?><?php echo esc_html( $telefoon ); ?>
				</a>
			<?php endif; ?>
			<?php if ( $email ) : ?>
				<a href="mailto:<?php echo esc_attr( $email ); ?>">
					<?php HDP_Icons::render_icoon( 'mail', 'hdp-inline-icoon' ); ?><?php echo esc_html( $email ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
	}
}
