<?php
/**
 * Berichten van Homburg aan dealers, op de startpagina van het portaal.
 *
 * De informatiestroom liep tot nu toe één kant op: bestanden en claims
 * verschenen vanzelf, maar er was geen manier om iets te zéggen. Een
 * prijswijziging, een leveringsprobleem bij één fabrikant, een gewijzigde
 * openingstijd — dat ging per mail en kwam daarmee bij iedereen terecht of
 * bij niemand.
 *
 * Een bericht kan op merken gericht worden, net als een download: alleen
 * Väderstad-dealers zien het Väderstad-nieuws. Zonder merk is het voor
 * iedereen.
 *
 * @package Homburg_Dealerportaal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Beheert en toont de berichten op de startpagina.
 */
class HDP_Berichten {

	const POST_TYPE = 'hdp_bericht';

	/** Meta: op welke merken dit bericht gericht is (kommagescheiden). */
	const META_MERKEN = '_hdp_bericht_merken';

	/** Meta: tot en met welke datum het bericht getoond wordt (leeg = blijft staan). */
	const META_TOT = '_hdp_bericht_tot';

	/** Meta: 'melding' of 'belangrijk'. */
	const META_SOORT = '_hdp_bericht_soort';

	/**
	 * Hoeveel berichten er hoogstens tegelijk op de startpagina staan.
	 *
	 * Drie is de grens waarboven het van een mededeling een nieuwsbrief
	 * wordt en de tegels eronder wegvallen. Staat er een vierde klaar, dan
	 * verdwijnt het oudste vanzelf — dat dwingt af dat oude berichten een
	 * einddatum krijgen in plaats van eeuwig te blijven staan.
	 */
	const MAXIMAAL = 3;

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'opslaan' ) );
		add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'kolommen' ) );
		add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'kolom_inhoud' ), 10, 2 );
	}

	public static function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			array(
				'label'           => 'Berichten',
				'labels'          => array(
					'name'          => 'Berichten',
					'singular_name' => 'Bericht',
					'add_new_item'  => 'Nieuw bericht plaatsen',
					'edit_item'     => 'Bericht bewerken',
					'search_items'  => 'Berichten zoeken',
					'not_found'     => 'Nog geen berichten geplaatst.',
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-megaphone',
				'menu_position'   => 26,
				'supports'        => array( 'title', 'editor' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}

	/* === Beheer in wp-admin ============================================ */

	public static function add_meta_box() {
		add_meta_box( 'hdp_bericht_bereik', 'Voor wie en hoelang', array( __CLASS__, 'render_meta_box' ), self::POST_TYPE, 'side' );
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'hdp_bericht_opslaan', 'hdp_bericht_nonce' );

		$gekozen = HDP_Merken::naar_array( get_post_meta( $post->ID, self::META_MERKEN, true ) );
		$tot     = get_post_meta( $post->ID, self::META_TOT, true );
		$soort   = get_post_meta( $post->ID, self::META_SOORT, true );
		?>
		<p>
			<strong>Merken</strong><br>
			<span class="description">Niets aanvinken = alle dealers zien dit bericht.</span>
		</p>
		<?php foreach ( HDP_Merken::lijst() as $merk ) : ?>
			<label style="display:block">
				<input type="checkbox" name="hdp_bericht_merken[]" value="<?php echo esc_attr( $merk ); ?>"
					<?php checked( in_array( $merk, $gekozen, true ) ); ?>>
				<?php echo esc_html( $merk ); ?>
			</label>
		<?php endforeach; ?>

		<p style="margin-top:1em">
			<label for="hdp_bericht_soort"><strong>Soort</strong></label><br>
			<select name="hdp_bericht_soort" id="hdp_bericht_soort" class="widefat">
				<option value="melding" <?php selected( 'belangrijk' !== $soort ); ?>>Melding</option>
				<option value="belangrijk" <?php selected( 'belangrijk', $soort ); ?>>Belangrijk</option>
			</select>
			<span class="description">"Belangrijk" valt op; gebruik dat spaarzaam, anders valt het niet meer op.</span>
		</p>

		<p style="margin-top:1em">
			<label for="hdp_bericht_tot"><strong>Tonen tot en met</strong></label><br>
			<input type="date" name="hdp_bericht_tot" id="hdp_bericht_tot" class="widefat"
				value="<?php echo esc_attr( $tot ); ?>">
			<span class="description">Leeg laten = blijft staan tot u het bericht weghaalt.</span>
		</p>
		<?php
	}

	public static function opslaan( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['hdp_bericht_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['hdp_bericht_nonce'] ) ), 'hdp_bericht_opslaan' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Via HDP_Merken::uit_selectie(): dat filtert alles weg wat niet in
		// de vaste merkenlijst staat, zodat een met de hand samengesteld
		// verzoek hier geen willekeurige waarde kan achterlaten.
		$merken = isset( $_POST['hdp_bericht_merken'] )
			? HDP_Merken::uit_selectie( array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['hdp_bericht_merken'] ) ) )
			: '';
		update_post_meta( $post_id, self::META_MERKEN, $merken );

		$soort = isset( $_POST['hdp_bericht_soort'] ) ? sanitize_key( wp_unslash( $_POST['hdp_bericht_soort'] ) ) : 'melding';
		update_post_meta( $post_id, self::META_SOORT, 'belangrijk' === $soort ? 'belangrijk' : 'melding' );

		$tot = isset( $_POST['hdp_bericht_tot'] ) ? sanitize_text_field( wp_unslash( $_POST['hdp_bericht_tot'] ) ) : '';
		update_post_meta( $post_id, self::META_TOT, preg_match( '/^\d{4}-\d{2}-\d{2}$/', $tot ) ? $tot : '' );
	}

	public static function kolommen( $kolommen ) {
		$nieuw = array();
		foreach ( $kolommen as $sleutel => $label ) {
			$nieuw[ $sleutel ] = $label;
			if ( 'title' === $sleutel ) {
				$nieuw['hdp_merken'] = 'Voor';
				$nieuw['hdp_tot']    = 'Tot en met';
			}
		}
		return $nieuw;
	}

	public static function kolom_inhoud( $kolom, $post_id ) {
		if ( 'hdp_merken' === $kolom ) {
			$merken = get_post_meta( $post_id, self::META_MERKEN, true );
			echo esc_html( $merken ? $merken : 'Alle dealers' );
		}
		if ( 'hdp_tot' === $kolom ) {
			$tot = get_post_meta( $post_id, self::META_TOT, true );
			if ( ! $tot ) {
				echo '—';
				return;
			}
			echo esc_html( date_i18n( 'j M Y', strtotime( $tot ) ) );
			if ( self::verlopen( $tot ) ) {
				echo ' <em>(verlopen)</em>';
			}
		}
	}

	/* === Wat een dealer te zien krijgt ================================= */

	/** Is deze einddatum voorbij? Een lege datum verloopt nooit. */
	private static function verlopen( $tot ) {
		if ( ! $tot ) {
			return false;
		}

		// Tot en met: op de dag zelf staat het er nog.
		return current_time( 'Y-m-d' ) > $tot;
	}

	/**
	 * Mag deze dealer dit bericht zien?
	 *
	 * Dezelfde regels als bij downloads, zodat "merk" overal hetzelfde
	 * betekent: zonder merk is het voor iedereen, en een dealer bij wie nog
	 * geen merken zijn ingesteld ziet alles — die mag niet per ongeluk
	 * niets meer te zien krijgen.
	 */
	public static function mag_zien( $post_id, $user_id = 0 ) {
		$merken = HDP_Merken::naar_array( get_post_meta( $post_id, self::META_MERKEN, true ) );
		if ( ! $merken ) {
			return true;
		}

		$user_id    = $user_id ? (int) $user_id : get_current_user_id();
		$toegestaan = HDP_Merken::naar_array( get_user_meta( $user_id, 'hdp_merken', true ) );

		if ( ! $toegestaan ) {
			return true;
		}

		return (bool) array_intersect( $merken, $toegestaan );
	}

	/**
	 * De berichten die nu voor deze dealer gelden, nieuwste eerst.
	 *
	 * @param int $user_id Leeg = de huidige bezoeker.
	 * @return WP_Post[]
	 */
	public static function voor_dealer( $user_id = 0 ) {
		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'post_status'      => 'publish',
				// Ruimer ophalen dan we tonen: na het wegfilteren van
				// verlopen en niet-passende berichten moeten er nog genoeg
				// overblijven om MAXIMAAL te kunnen vullen.
				'numberposts'      => 30,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);

		$zichtbaar = array();
		foreach ( $posts as $post ) {
			if ( self::verlopen( get_post_meta( $post->ID, self::META_TOT, true ) ) ) {
				continue;
			}
			if ( ! self::mag_zien( $post->ID, $user_id ) ) {
				continue;
			}

			$zichtbaar[] = $post;

			if ( count( $zichtbaar ) >= self::MAXIMAAL ) {
				break;
			}
		}

		return $zichtbaar;
	}

	/* === Weergave op de startpagina ==================================== */

	/**
	 * De berichten boven de tegels.
	 *
	 * Daar en nergens anders: dit is het eerste wat een dealer ziet, en een
	 * mededeling die je pas tegenkomt nadat je ergens naartoe geklikt hebt,
	 * is te laat.
	 */
	public static function render() {
		$berichten = self::voor_dealer();
		if ( ! $berichten ) {
			return;
		}
		?>
		<section class="hdp-berichten" aria-label="<?php echo esc_attr( HDP_I18N::t( 'berichten_titel' ) ); ?>">
			<?php foreach ( $berichten as $post ) : ?>
				<?php $belangrijk = 'belangrijk' === get_post_meta( $post->ID, self::META_SOORT, true ); ?>
				<article class="hdp-bericht<?php echo $belangrijk ? ' hdp-bericht-belangrijk' : ''; ?>">
					<div class="hdp-bericht-icoon" aria-hidden="true">
						<?php HDP_Icons::render_icoon( $belangrijk ? 'technisch' : 'mail' ); ?>
					</div>
					<div class="hdp-bericht-tekst">
						<h2><?php echo esc_html( get_the_title( $post ) ); ?></h2>
						<?php echo wp_kses_post( wpautop( $post->post_content ) ); ?>
						<p class="hdp-bericht-datum">
							<?php echo esc_html( date_i18n( 'j F Y', get_post_time( 'U', true, $post ) ) ); ?>
						</p>
					</div>
				</article>
			<?php endforeach; ?>
		</section>
		<?php
	}
}
