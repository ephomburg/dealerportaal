<?php
/**
 * Bestellingen uit de webshop naar PowerAll, en het beheerscherm
 * WooCommerce > PowerAll.
 *
 * Een dealer bestelt op rekening; zodra de bestelling binnen is ("In
 * behandeling"), gaat ze als verkooporder naar PowerAll, op zijn klantnummer.
 * Referentie 1 is het bestelnummer in het portaal, Referentie 2 zijn eigen
 * referentie. PowerAll nummert de order en rekent de prijzen; Homburg
 * factureert vanuit PowerAll.
 *
 * Drie standen, onder WooCommerce > PowerAll:
 * - Uit: er gaat niets naar PowerAll.
 * - Proef (standaard): PowerAll controleert de order en rekent hem uit, maar
 *   maakt hem niet aan (/sales-orders/simulate). De uitkomst staat als notitie
 *   bij de bestelling.
 * - Aan: de order wordt echt aangemaakt.
 *
 * Een bestelling gaat nooit twee keer. Lukt het niet, dan probeert het portaal
 * het elk uur opnieuw, tot 5 keer, en staat de fout bij de bestelling.
 *
 * @package Homburg_Dealerportaal
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HDP_PowerAll_Orders {

	const META_STATUS   = '_hdp_pa_status';
	const META_NUMMER   = '_hdp_pa_nummer';
	const META_ID       = '_hdp_pa_id';
	const META_POGINGEN = '_hdp_pa_pogingen';
	const META_FOUT     = '_hdp_pa_fout';

	const OPTION       = 'hdp_powerall_orders';
	const HOOK         = 'hdp_powerall_order_versturen';
	const HOOK_HERHAAL = 'hdp_powerall_orders_herhalen';
	const MAX_POGINGEN = 5;
	const PAGINA       = 'hdp-powerall-webshop';

	/** Alleen bij een proef: PowerAll wil een nummer zolang het zelf niet nummert, en een proef slaat niets op. */
	const PROEF_NUMMER = 99999999;

	public static function init() {
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'plan_versturen' ) );
		add_action( self::HOOK, array( __CLASS__, 'verstuur' ) );
		add_action( self::HOOK_HERHAAL, array( __CLASS__, 'herhaal' ) );
		add_action( 'admin_init', array( __CLASS__, 'plan' ) );
		add_action( 'admin_menu', array( __CLASS__, 'registreer_menu' ), 60 );
		add_action( 'admin_post_hdp_powerall_webshop', array( __CLASS__, 'verwerk_beheer' ) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( __CLASS__, 'toon_in_beheer' ) );
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'toon_aan_dealer' ) );
		add_filter( 'woocommerce_order_actions', array( __CLASS__, 'bestelactie' ), 10, 2 );
		add_action( 'woocommerce_order_action_' . self::ACTIE_OPNIEUW, array( __CLASS__, 'verstuur_opnieuw' ) );
	}

	const ACTIE_OPNIEUW = 'hdp_pa_opnieuw';

	/** Bij een bestelling die niet aankwam: "Opnieuw naar PowerAll versturen" in het menu Bestelacties. */
	public static function bestelactie( $acties, $order = null ) {
		if ( $order instanceof WC_Order && 'fout' === (string) $order->get_meta( self::META_STATUS ) && ! $order->get_meta( self::META_ID ) && 'uit' !== self::modus() ) {
			$acties[ self::ACTIE_OPNIEUW ] = 'aan' === self::modus()
				? 'Opnieuw naar PowerAll versturen (kijk eerst of hij er niet al staat)'
				: 'Proef met PowerAll opnieuw doen';
		}
		return $acties;
	}

	/** Met de hand opnieuw: de teller van de automatische pogingen begint opnieuw. */
	public static function verstuur_opnieuw( $order ) {
		if ( ! $order instanceof WC_Order || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		$order->update_meta_data( self::META_POGINGEN, 0 );
		$order->save();
		self::verstuur( $order->get_id() );
	}

	public static function plan() {
		if ( ! wp_next_scheduled( self::HOOK_HERHAAL ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::HOOK_HERHAAL );
		}
	}

	/* --- Instellingen --------------------------------------------------- */

	private static function instellingen() {
		$waarde = get_option( self::OPTION, array() );
		return is_array( $waarde ) ? $waarde : array();
	}

	/** 'uit', 'proef' of 'aan'. */
	public static function modus() {
		$modus = (string) ( self::instellingen()['modus'] ?? 'proef' );
		return in_array( $modus, array( 'uit', 'proef', 'aan' ), true ) ? $modus : 'proef';
	}

	/** Het PowerAll-artikel voor regels die zelf niet in PowerAll staan, zoals de outlet. */
	public static function verzamelartikel() {
		return trim( (string) ( self::instellingen()['verzamelartikel'] ?? '' ) );
	}

	/* --- Versturen ------------------------------------------------------ */

	public static function plan_versturen( $order_id ) {
		if ( 'uit' === self::modus() ) {
			return;
		}
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK, array( (int) $order_id ), 'hdp-powerall' );
		} else {
			self::verstuur( $order_id );
		}
	}

	/**
	 * De verkooporder voor PowerAll.
	 *
	 * @return array|WP_Error
	 */
	public static function bouw( WC_Order $order ) {
		$relatie = HDP_PowerAll_Prijzen::relatie( $order->get_customer_id() );
		if ( ! $relatie ) {
			return new WP_Error( 'hdp_pa_geen_klant', 'Deze dealer is niet aan een klantnummer in PowerAll gekoppeld.' );
		}

		$regels = array();
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			$aantal  = (float) $item->get_quantity();
			if ( $product && $product->get_meta( HDP_PowerAll_Artikelen::META_BRON ) ) {
				$regels[] = array(
					'ProductCode'     => $product->get_sku(),
					'QuantityOrdered' => $aantal,
				);
				continue;
			}
			// Niet uit PowerAll (bijv. de outlet): via het verzamelartikel, met de omschrijving en de prijs uit de webshop.
			$code = self::verzamelartikel();
			if ( '' === $code ) {
				return new WP_Error( 'hdp_pa_geen_verzamelartikel', '"' . $item->get_name() . '" staat niet in PowerAll en er is geen verzamelartikel ingesteld (WooCommerce > PowerAll).' );
			}
			$sku      = $product ? $product->get_sku() : '';
			$regels[] = array(
				'ProductCode'        => $code,
				'QuantityOrdered'    => $aantal,
				'Description'        => mb_substr( $item->get_name() . ( $sku ? ' (' . $sku . ')' : '' ), 0, 1024 ),
				'GrossPrice'         => round( (float) $order->get_item_subtotal( $item, false, false ), 2 ),
				'DiscountPercentage' => 0,
			);
		}
		if ( ! $regels ) {
			return new WP_Error( 'hdp_pa_leeg', 'De bestelling heeft geen artikelen.' );
		}

		// "Jouw referentie" bij afrekenen (zie homburg_wc_referentie() in het thema).
		$referentie = (string) $order->get_meta( '_wc_other/homburg/referentie' );
		$notitie    = 'Besteld via het dealerportaal door ' . trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) . ' (' . $order->get_billing_email() . ').';
		if ( $order->get_customer_note() ) {
			$notitie .= "\nOpmerking: " . $order->get_customer_note();
		}

		$order_body = array(
			'RelationId'   => (string) $relatie,
			'Reference1'   => mb_substr( 'Portaal ' . $order->get_order_number(), 0, 40 ),
			'Reference2'   => mb_substr( $referentie, 0, 40 ),
			'EmailAddress' => mb_substr( (string) $order->get_billing_email(), 0, 75 ),
			'Phone'        => mb_substr( (string) $order->get_billing_phone(), 0, 20 ),
			'InternalNote' => $notitie,
			'Lines'        => $regels,
		);

		// Het afleveradres uit de bestelling; zonder land, dat neemt PowerAll van de klant.
		if ( $order->get_shipping_address_1() ) {
			$bedrijf                           = (string) $order->get_shipping_company();
			$naam                              = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
			$order_body['DeliveryName1']       = mb_substr( $bedrijf ? $bedrijf : $naam, 0, 50 );
			$order_body['DeliveryName2']       = mb_substr( $bedrijf ? $naam : '', 0, 50 );
			$order_body['DeliveryAddressLine'] = mb_substr( trim( $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2() ), 0, 50 );
			$order_body['DeliveryZipCode']     = mb_substr( (string) $order->get_shipping_postcode(), 0, 9 );
			$order_body['DeliveryTown']        = mb_substr( (string) $order->get_shipping_city(), 0, 26 );
		}

		return array_filter(
			$order_body,
			static function ( $waarde ) {
				return '' !== $waarde && null !== $waarde;
			}
		);
	}

	/**
	 * Verstuurt de bestelling (of draait hem proef).
	 *
	 * @return string De nieuwe status: 'verstuurd', 'proef', 'fout', 'overgeslagen' of 'al verstuurd'.
	 */
	public static function verstuur( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return 'overgeslagen';
		}
		$status = (string) $order->get_meta( self::META_STATUS );
		if ( 'verstuurd' === $status || $order->get_meta( self::META_ID ) ) {
			return 'al verstuurd';
		}
		$modus = self::modus();
		if ( 'uit' === $modus ) {
			return 'overgeslagen';
		}
		// Een bestelling uit de proeftijd gaat nooit alsnog echt naar PowerAll,
		// en "bezig" kan betekenen dat hij er al staat (een time-out na het versturen).
		if ( 'aan' === $modus && in_array( $status, array( 'proef', 'bezig' ), true ) ) {
			return 'overgeslagen';
		}

		$body = self::bouw( $order );
		if ( is_wp_error( $body ) ) {
			return self::mislukt( $order, $body->get_error_message(), $modus, false );
		}

		if ( 'proef' === $modus ) {
			$body['EntryNumber'] = self::PROEF_NUMMER;
			$antwoord            = HDP_PowerAll::post( 'sales-orders/simulate', $body );
			if ( is_wp_error( $antwoord ) ) {
				return self::mislukt( $order, $antwoord->get_error_message(), $modus, false );
			}
			$order->update_meta_data( self::META_STATUS, 'proef' );
			$order->delete_meta_data( self::META_FOUT );
			$order->save();
			$order->add_order_note( 'PowerAll (proef): de order is goedgekeurd, totaal ' . wc_price( (float) ( $antwoord['TotalNetPriceExVat'] ?? 0 ) ) . ' excl. btw. Er is niets aangemaakt in PowerAll.' );
			return 'proef';
		}

		// Echt versturen. Eerst vastleggen dat we bezig zijn, zodat een tweede poging tegelijk niets doet.
		$order->update_meta_data( self::META_STATUS, 'bezig' );
		$order->save();
		$antwoord = HDP_PowerAll::post( 'sales-orders', $body );
		if ( is_wp_error( $antwoord ) ) {
			return self::mislukt( $order, $antwoord->get_error_message(), $modus, true );
		}
		$order->update_meta_data( self::META_STATUS, 'verstuurd' );
		$order->update_meta_data( self::META_ID, (string) ( $antwoord['Id'] ?? '' ) );
		$order->update_meta_data( self::META_NUMMER, (string) ( $antwoord['EntryNumber'] ?? '' ) );
		$order->delete_meta_data( self::META_FOUT );
		$order->save();
		$order->add_order_note( 'Verstuurd naar PowerAll: ordernummer ' . ( $antwoord['EntryNumber'] ?? '?' ) . '.' );
		return 'verstuurd';
	}

	private static function mislukt( WC_Order $order, $melding, $modus, $opnieuw ) {
		$pogingen = (int) $order->get_meta( self::META_POGINGEN ) + 1;
		$order->update_meta_data( self::META_STATUS, 'fout' );
		$order->update_meta_data( self::META_FOUT, $melding );
		$order->update_meta_data( self::META_POGINGEN, $opnieuw ? $pogingen : self::MAX_POGINGEN );
		$order->save();
		$order->add_order_note( 'PowerAll' . ( 'proef' === $modus ? ' (proef)' : '' ) . ': niet gelukt. ' . $melding . ( $opnieuw && $pogingen < self::MAX_POGINGEN ? ' Het portaal probeert het over een uur opnieuw.' : '' ) );
		HDP_Log::schrijf( 'PowerAll-order ' . $order->get_order_number() . ': ' . $melding, 'WAARSCHUWING' );
		return 'fout';
	}

	/**
	 * Bestellingen met deze PowerAll-status, nieuwste eerst.
	 *
	 * WooCommerce kent twee manieren om bestellingen op te slaan. In de oude
	 * (als berichten) negeert wc_get_orders() een meta_query en geeft dan
	 * élke bestelling terug. Daarom hier per opslagvorm, en voor de
	 * zekerheid daarna nog eens per bestelling gecontroleerd.
	 *
	 * @param string   $pa_status    'fout', 'proef', ...
	 * @param int      $limiet       Hoeveel hooguit.
	 * @param string[] $wc_statussen Alleen deze WooCommerce-statussen (zonder "wc-"); leeg = alle.
	 * @return WC_Order[]
	 */
	public static function met_status( $pa_status, $limiet = 50, $wc_statussen = array() ) {
		$hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		if ( $hpos ) {
			$args = array(
				'limit'      => $limiet,
				'orderby'    => 'date',
				'order'      => 'DESC',
				'meta_query' => array( array( 'key' => self::META_STATUS, 'value' => $pa_status ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
			);
			if ( $wc_statussen ) {
				$args['status'] = $wc_statussen;
			}
			$orders = wc_get_orders( $args );
		} else {
			$ids    = get_posts(
				array(
					'post_type'      => 'shop_order',
					'post_status'    => $wc_statussen ? array_map( static function ( $s ) { return 'wc-' . $s; }, $wc_statussen ) : array_keys( wc_get_order_statuses() ),
					'meta_key'       => self::META_STATUS, // phpcs:ignore WordPress.DB.SlowDBQuery
					'meta_value'     => $pa_status, // phpcs:ignore WordPress.DB.SlowDBQuery
					'posts_per_page' => $limiet,
					'orderby'        => 'date',
					'order'          => 'DESC',
					'fields'         => 'ids',
				)
			);
			$orders = array_filter( array_map( 'wc_get_order', $ids ) );
		}
		return array_values(
			array_filter(
				$orders,
				static function ( $order ) use ( $pa_status ) {
					return $order instanceof WC_Order && (string) $order->get_meta( self::META_STATUS ) === $pa_status;
				}
			)
		);
	}

	/** Elk uur: echte orders die door een storing niet aankwamen, opnieuw proberen. */
	public static function herhaal() {
		if ( 'aan' !== self::modus() ) {
			return;
		}
		foreach ( self::met_status( 'fout', 20, array( 'processing' ) ) as $order ) {
			if ( (int) $order->get_meta( self::META_POGINGEN ) < self::MAX_POGINGEN ) {
				self::verstuur( $order->get_id() );
			}
		}
	}

	/* --- Tonen ---------------------------------------------------------- */

	public static function toon_in_beheer( $order ) {
		$status = (string) $order->get_meta( self::META_STATUS );
		if ( '' === $status ) {
			return;
		}
		$teksten = array(
			'verstuurd' => 'Verstuurd, ordernummer ' . $order->get_meta( self::META_NUMMER ),
			'proef'     => 'Alleen proef (niets aangemaakt)',
			'bezig'     => 'Bezig met versturen',
			'fout'      => 'Niet gelukt: ' . $order->get_meta( self::META_FOUT ),
		);
		echo '<p class="form-field form-field-wide"><strong>PowerAll:</strong> ' . esc_html( $teksten[ $status ] ?? $status ) . '</p>';
	}

	public static function toon_aan_dealer( $order ) {
		$nummer = (string) $order->get_meta( self::META_NUMMER );
		if ( '' !== $nummer ) {
			echo '<p class="hdp-pa-ordernummer">' . esc_html( sprintf( HDP_I18N::t( 'pa_ordernummer' ), $nummer ) ) . '</p>';
		}
	}

	/* --- Beheer: WooCommerce > PowerAll --------------------------------- */

	public static function registreer_menu() {
		add_submenu_page( 'woocommerce', 'PowerAll', 'PowerAll', 'manage_woocommerce', self::PAGINA, array( __CLASS__, 'render_beheer' ) );
	}

	public static function beheer_url( $melding = '' ) {
		$url = admin_url( 'admin.php?page=' . self::PAGINA );
		return $melding ? add_query_arg( 'hdp_melding', $melding, $url ) : $url;
	}

	public static function verwerk_beheer() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Geen toegang.' );
		}
		check_admin_referer( 'hdp_powerall_webshop' );
		$actie = isset( $_POST['actie'] ) ? sanitize_key( wp_unslash( $_POST['actie'] ) ) : '';

		if ( 'artikelen' === $actie ) {
			$melding = HDP_PowerAll_Artikelen::start() ? 'artikelen_gestart' : 'artikelen_bezig';
		} else {
			$modus = isset( $_POST['modus'] ) ? sanitize_key( wp_unslash( $_POST['modus'] ) ) : 'proef';
			update_option(
				self::OPTION,
				array(
					'modus'           => in_array( $modus, array( 'uit', 'proef', 'aan' ), true ) ? $modus : 'proef',
					'verzamelartikel' => isset( $_POST['verzamelartikel'] ) ? sanitize_text_field( wp_unslash( $_POST['verzamelartikel'] ) ) : '',
				),
				false
			);
			$melding = 'opgeslagen';
		}
		wp_safe_redirect( self::beheer_url( $melding ) );
		exit;
	}

	private static function datum( $tijd ) {
		return $tijd ? wp_date( 'j-n-Y H:i', (int) $tijd ) : '';
	}

	public static function render_beheer() {
		$stand     = HDP_PowerAll_Artikelen::stand();
		$vorige    = isset( $stand['vorige'] ) && is_array( $stand['vorige'] ) ? $stand['vorige'] : null;
		$modus     = self::modus();
		$melding   = isset( $_GET['hdp_melding'] ) ? sanitize_key( wp_unslash( $_GET['hdp_melding'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$meldingen = array(
			'opgeslagen'        => 'Opgeslagen.',
			'artikelen_gestart' => 'Het bijwerken van de artikelen is begonnen. Dat loopt op de achtergrond en duurt een paar minuten.',
			'artikelen_bezig'   => 'De artikelen worden al bijgewerkt.',
		);
		$aantal    = (int) ( new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'meta_key'       => HDP_PowerAll_Artikelen::META_BRON, // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		) )->found_posts;
		?>
		<div class="wrap">
			<h1>PowerAll</h1>
			<?php if ( isset( $meldingen[ $melding ] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $meldingen[ $melding ] ); ?></p></div>
			<?php endif; ?>
			<?php if ( ! HDP_PowerAll::beschikbaar() ) : ?>
				<div class="notice notice-warning"><p>De toegang tot PowerAll is op deze server niet ingesteld. Er wordt niets opgehaald of verstuurd.</p></div>
			<?php endif; ?>

			<h2>Artikelen</h2>
			<p>Alle actieve artikelen waarbij in PowerAll "verbergen op webshop" uit staat, staan in de webshop, met de verkoopprijs en het merk. Een dealer met een klantnummer ziet zijn eigen prijs. Dit wordt elke nacht bijgewerkt. Met de hand aangemaakte producten, zoals de outlet, blijven ongemoeid.</p>
			<p><strong><?php echo esc_html( number_format_i18n( $aantal ) ); ?></strong> artikelen uit PowerAll in de webshop.
				<?php if ( ! empty( $stand['bezig'] ) ) : ?>
					Bezig met bijwerken: <?php echo esc_html( number_format_i18n( (int) $stand['gezien'] ) ); ?> nagelopen.
				<?php elseif ( $vorige ) : ?>
					Laatst bijgewerkt op <?php echo esc_html( self::datum( $vorige['klaar'] ) ); ?>: <?php echo esc_html( sprintf( '%s nagelopen, %s nieuw, %s gewijzigd, %s uit de webshop gehaald.', number_format_i18n( $vorige['gezien'] ), number_format_i18n( $vorige['nieuw'] ), number_format_i18n( $vorige['gewijzigd'] ), number_format_i18n( $vorige['verborgen'] ) ) ); ?>
					<?php if ( '' !== $vorige['fout'] ) : ?>
						<br><span style="color:#b32d2e">Gestopt door een fout: <?php echo esc_html( $vorige['fout'] ); ?></span>
					<?php endif; ?>
				<?php endif; ?>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'hdp_powerall_webshop' ); ?>
				<input type="hidden" name="action" value="hdp_powerall_webshop">
				<input type="hidden" name="actie" value="artikelen">
				<?php submit_button( 'Artikelen nu bijwerken', 'secondary', 'submit', false, HDP_PowerAll::beschikbaar() ? array() : array( 'disabled' => 'disabled' ) ); ?>
			</form>

			<h2>Bestellingen</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'hdp_powerall_webshop' ); ?>
				<input type="hidden" name="action" value="hdp_powerall_webshop">
				<input type="hidden" name="actie" value="orders">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Naar PowerAll</th>
						<td>
							<fieldset>
								<label><input type="radio" name="modus" value="uit" <?php checked( 'uit', $modus ); ?>> Uit: er gaat niets naar PowerAll</label><br>
								<label><input type="radio" name="modus" value="proef" <?php checked( 'proef', $modus ); ?>> Proef: PowerAll controleert en rekent de order, maar maakt hem niet aan</label><br>
								<label><input type="radio" name="modus" value="aan" <?php checked( 'aan', $modus ); ?>> <strong>Aan: elke bestelling wordt een echte order in PowerAll</strong></label>
							</fieldset>
							<p class="description">De uitkomst staat als notitie bij de bestelling. Voor "Aan" moet in PowerAll bij de verkooporderinstellingen een startnummer voor API-orders staan; anders weigert PowerAll de order.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="hdp-pa-verzamel">Verzamelartikel</label></th>
						<td>
							<input id="hdp-pa-verzamel" name="verzamelartikel" type="text" class="regular-text code" value="<?php echo esc_attr( self::verzamelartikel() ); ?>">
							<p class="description">Het artikelnummer in PowerAll voor regels die zelf niet in PowerAll staan, zoals de outlet. De omschrijving en prijs komen dan uit de webshop.</p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Opslaan' ); ?>
			</form>

			<h2>Dealers</h2>
			<p>Welke klanten een account krijgen, stel je in onder <a href="<?php echo esc_url( HDP_PowerAll_Dealers::beheer_url() ); ?>">Gebruikers &gt; Dealers uit PowerAll</a>.</p>
		</div>
		<?php
	}
}
