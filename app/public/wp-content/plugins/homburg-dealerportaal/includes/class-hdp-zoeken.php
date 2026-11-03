<?php
/**
 * Eén zoekbalk voor het hele portaal.
 *
 * Een dealer die een artikelnummer, serienummer, claimnummer, ordernummer
 * of de naam van een document weet, hoeft niet eerst te bedenken in welk
 * onderdeel dat staat. Het veld in de header zoekt in alles tegelijk en
 * toont per soort een paar treffers. Elke bron gebruikt dezelfde
 * zichtbaarheid als de pagina waar hij vandaan komt: een download die de
 * dealer op de downloadspagina niet ziet, vindt hij hier ook niet, en
 * claims en bestellingen zijn altijd alleen die van hemzelf.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HDP_Zoeken {

	/** Zoveel treffers per soort; de rest staat op de pagina zelf. */
	const PER_GROEP = 5;

	/** Korter dan dit geeft vooral ruis. */
	const MIN_LENGTE = 2;

	/** Zo lang (seconden) blijven claims en machines bewaard; gelijk aan de teller in het menu. */
	const GARANTIE_CACHE = 120;

	public static function init() {
		add_action( 'wp_ajax_hdp_portaal_zoeken', array( __CLASS__, 'ajax' ) );
	}

	public static function ajax() {
		check_ajax_referer( 'hdp-portaal-zoeken', 'nonce' );

		if ( ! HDP_Roles::mag_portaal_zien( get_current_user_id() ) ) {
			wp_send_json_error( null, 403 );
		}

		$term = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';

		$resultaten = self::resultaten( $term );

		// Voor het overzicht Portaalgebruik, vooral de termen die niets opleveren.
		if ( mb_strlen( $term ) >= self::MIN_LENGTE ) {
			$treffers = 0;
			foreach ( $resultaten['groepen'] as $groep ) {
				$treffers += count( $groep['items'] );
			}
			HDP_Gebruik::noteer_zoekopdracht( $term, $treffers );
		}

		wp_send_json_success( $resultaten );
	}

	/**
	 * Alle treffers voor deze zoekterm, gegroepeerd.
	 *
	 * @return array{groepen: array, meer: array|null}
	 */
	public static function resultaten( $term ) {
		$term = trim( (string) $term );
		if ( mb_strlen( $term ) < self::MIN_LENGTE ) {
			return array( 'groepen' => array(), 'meer' => null );
		}

		$groepen = array(
			array( 'sleutel' => 'paginas', 'titel' => HDP_I18N::t( 'zoeken_groep_paginas' ), 'items' => self::paginas( $term ) ),
			array( 'sleutel' => 'merken', 'titel' => HDP_I18N::t( 'zoeken_groep_merken' ), 'items' => self::merken( $term ) ),
			array( 'sleutel' => 'artikelen', 'titel' => HDP_I18N::t( 'zoeken_groep_artikelen' ), 'items' => self::artikelen( $term ) ),
			array( 'sleutel' => 'bestellingen', 'titel' => HDP_I18N::t( 'zoeken_groep_bestellingen' ), 'items' => self::bestellingen( $term ) ),
			array( 'sleutel' => 'claims', 'titel' => HDP_I18N::t( 'zoeken_groep_claims' ), 'items' => self::claims( $term ) ),
			array( 'sleutel' => 'machines', 'titel' => HDP_I18N::t( 'zoeken_groep_machines' ), 'items' => self::machines( $term ) ),
			array( 'sleutel' => 'downloads', 'titel' => HDP_I18N::t( 'zoeken_groep_downloads' ), 'items' => self::downloads( $term ) ),
		);

		$meer = null;
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$meer = array(
				'url'   => add_query_arg( 'zoek', rawurlencode( $term ), wc_get_page_permalink( 'shop' ) ),
				'tekst' => sprintf( HDP_I18N::t( 'zoeken_alle_artikelen' ), $term ),
			);
		}

		return array(
			'groepen' => array_values(
				array_filter(
					$groepen,
					static function ( $groep ) {
						return ! empty( $groep['items'] );
					}
				)
			),
			'meer'    => $meer,
		);
	}

	/**
	 * Zit de zoekterm in een van deze teksten? Hoofdletters tellen niet mee,
	 * en spaties, streepjes en punten ook niet: "100 13325" vindt zo ook
	 * "100-13325-715".
	 *
	 * @param string   $term   De zoekterm.
	 * @param string[] $velden De teksten om in te zoeken.
	 */
	public static function past( $term, $velden ) {
		$naald = self::normaliseer( $term );
		if ( '' === $naald ) {
			return false;
		}

		foreach ( $velden as $veld ) {
			if ( '' !== (string) $veld && false !== mb_strpos( self::normaliseer( $veld ), $naald ) ) {
				return true;
			}
		}

		return false;
	}

	private static function normaliseer( $tekst ) {
		return mb_strtolower( preg_replace( '/[\s\-\.\/_]+/u', '', remove_accents( (string) $tekst ) ) );
	}

	/** Een treffer zoals de zoekbalk hem toont. */
	private static function item( $titel, $sub, $url, $nieuwe_tab = false ) {
		return array(
			'titel'      => (string) $titel,
			'sub'        => (string) $sub,
			'url'        => (string) $url,
			'nieuwe_tab' => (bool) $nieuwe_tab,
		);
	}

	/** De onderdelen uit het menu links, zodat "garantie" je ook gewoon naar Garantie brengt. */
	private static function paginas( $term ) {
		$onderdelen = HDP_Zijbalk::onderdelen();

		// Staan niet als kaart op de startpagina, maar zoekt een dealer wel.
		if ( function_exists( 'wc_get_account_endpoint_url' ) ) {
			$onderdelen[] = array( 'titel' => HDP_I18N::t( 'zijbalk_bestellingen' ), 'url' => wc_get_account_endpoint_url( 'orders' ), 'nieuwe_tab' => false );
			$onderdelen[] = array( 'titel' => HDP_I18N::t( 'header_favorieten' ), 'url' => wc_get_account_endpoint_url( 'favorieten' ), 'nieuwe_tab' => false );
		}

		$items = array();
		foreach ( $onderdelen as $onderdeel ) {
			if ( self::past( $term, array( $onderdeel['titel'] ) ) ) {
				$items[] = self::item( $onderdeel['titel'], '', $onderdeel['url'], $onderdeel['nieuwe_tab'] );
			}
		}
		return array_slice( $items, 0, self::PER_GROEP );
	}

	/** Een merk in één klik: de winkel gefilterd op dat merk (de hoofdcategorieën). */
	private static function merken( $term ) {
		if ( ! function_exists( 'wc_get_page_permalink' ) ) {
			return array();
		}

		$termen = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'parent'     => 0,
				'hide_empty' => true,
			)
		);
		if ( is_wp_error( $termen ) ) {
			return array();
		}

		$items = array();
		foreach ( $termen as $merk ) {
			if ( 'occasions' === $merk->slug || ! self::past( $term, array( $merk->name ) ) ) {
				continue;
			}
			$items[] = self::item(
				$merk->name,
				sprintf( HDP_I18N::t( 'zoeken_aantal_artikelen' ), (int) $merk->count ),
				add_query_arg( 'merk', array( $merk->slug ), wc_get_page_permalink( 'shop' ) )
			);
		}
		return array_slice( $items, 0, self::PER_GROEP );
	}

	/** Titel, artikelnummer of merk; dezelfde zoeklogica als de winkelpagina. */
	private static function artikelen( $term ) {
		if ( ! function_exists( 'homburg_wc_zoek_product_ids' ) ) {
			return array();
		}

		$items = array();
		foreach ( homburg_wc_zoek_product_ids( $term, self::PER_GROEP * 2 ) as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product || ! $product->is_visible() ) {
				continue;
			}
			// Als platte tekst, dus "€ 55,00" en niet "&euro;&nbsp;55,00".
			$prijs   = '' === $product->get_price()
				? HDP_I18N::t( 'wc_prijs_op_aanvraag' )
				: html_entity_decode( wp_strip_all_tags( wc_price( wc_get_price_to_display( $product ) ) ), ENT_QUOTES, 'UTF-8' );
			$soort   = function_exists( 'homburg_wc_is_occasion' ) && homburg_wc_is_occasion( $product ) ? HDP_I18N::t( 'occasion_label' ) . ' · ' : '';
			$items[] = self::item(
				$product->get_name(),
				$soort . trim( $product->get_sku() . ' · ' . $prijs, ' ·' ),
				get_permalink( $id )
			);
			if ( count( $items ) >= self::PER_GROEP ) {
				break;
			}
		}
		return $items;
	}

	/**
	 * Eigen bestellingen: op ordernummer, eigen referentie of een artikel erin.
	 *
	 * Ook oude bestellingen. Alles elke letter opnieuw inlezen is te traag,
	 * dus de doorzoekbare tekst wordt per dealer bewaard; een nieuwe bestelling
	 * geeft vanzelf een nieuwe sleutel. Alleen de treffers worden echt geladen.
	 */
	private static function bestellingen( $term ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$items = array();
		foreach ( self::bestel_index() as $id => $tekst ) {
			if ( ! self::past( $term, array( $tekst ) ) ) {
				continue;
			}
			$order = wc_get_order( $id );
			if ( ! $order ) {
				continue;
			}

			$referentie = function_exists( 'homburg_wc_referentie' ) ? homburg_wc_referentie( $order ) : '';
			$datum      = $order->get_date_created() ? HDP_I18N::datum( 'j F Y', $order->get_date_created()->getOffsetTimestamp() ) : '';
			$sub        = array_filter( array( $datum, wc_get_order_status_name( $order->get_status() ), $referentie ? sprintf( HDP_I18N::t( 'bestel_referentie' ), $referentie ) : '' ) );
			$items[]    = self::item(
				sprintf( HDP_I18N::t( 'zoeken_bestelling' ), $order->get_order_number() ),
				implode( ' · ', $sub ),
				$order->get_view_order_url()
			);
			if ( count( $items ) >= self::PER_GROEP ) {
				break;
			}
		}
		return $items;
	}

	/**
	 * Per bestelling van deze dealer de tekst om in te zoeken, nieuwste eerst.
	 *
	 * @return array<int, string>
	 */
	private static function bestel_index() {
		$user_id = get_current_user_id();
		$ids     = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => -1,
				'orderby'     => 'date',
				'order'       => 'DESC',
				'return'      => 'ids',
			)
		);
		if ( ! $ids ) {
			return array();
		}

		$sleutel = 'hdp_zoek_best_' . $user_id . '_' . md5( implode( ',', $ids ) );
		$index   = get_transient( $sleutel );
		if ( is_array( $index ) ) {
			return $index;
		}

		$index = array();
		foreach ( $ids as $id ) {
			$order = wc_get_order( $id );
			if ( ! $order ) {
				continue;
			}
			$velden = array( $order->get_order_number(), function_exists( 'homburg_wc_referentie' ) ? homburg_wc_referentie( $order ) : '' );
			foreach ( $order->get_items() as $regel ) {
				$velden[] = $regel->get_name();
				$product  = is_callable( array( $regel, 'get_product' ) ) ? $regel->get_product() : null;
				if ( $product ) {
					$velden[] = $product->get_sku();
				}
			}
			$index[ $id ] = implode( ' | ', array_filter( $velden ) );
		}

		set_transient( $sleutel, $index, HOUR_IN_SECONDS );
		return $index;
	}

	/**
	 * Claims of machines van de externe database, kort bewaard: de zoekbalk
	 * vraagt bij elke letter, en dat waren elke keer twee ronden naar buiten.
	 * Een storing wordt niet bewaard.
	 *
	 * @param string $soort 'claims' of 'machines'.
	 * @return array
	 */
	private static function garantiegegevens( $soort ) {
		if ( ! HDP_Garantie::beschikbaar() ) {
			return array();
		}

		$sleutel = 'hdp_zoek_' . $soort . '_' . get_current_user_id();
		$lijst   = get_transient( $sleutel );
		if ( is_array( $lijst ) ) {
			return $lijst;
		}

		$lijst = 'claims' === $soort ? HDP_Garantie::claims() : HDP_Garantie::machines();
		if ( is_wp_error( $lijst ) ) {
			return array();
		}

		set_transient( $sleutel, $lijst, self::GARANTIE_CACHE );
		return $lijst;
	}

	/** Na een nieuwe claim of machine: de volgende zoekopdracht vindt hem meteen. */
	public static function vergeet_garantiegegevens() {
		delete_transient( 'hdp_zoek_claims_' . get_current_user_id() );
		delete_transient( 'hdp_zoek_machines_' . get_current_user_id() );
	}

	/** Eigen claims: op claimnummer, serienummer, machine, factuurnummer of referentie. */
	private static function claims( $term ) {
		$items = array();
		foreach ( self::garantiegegevens( 'claims' ) as $claim ) {
			$velden = array( $claim['nummer'], $claim['serienummer'], $claim['machine'], $claim['merk'], $claim['factuurnummer'], $claim['klantreferentie'] );
			if ( ! self::past( $term, $velden ) ) {
				continue;
			}
			$items[] = self::item(
				$claim['nummer'] . ' · ' . HDP_Garantie::claimtitel( $claim ),
				HDP_Garantie::status_label( $claim['status'] ),
				add_query_arg( 'ticket', $claim['nummer'], home_url( '/garantie/' ) )
			);
			if ( count( $items ) >= self::PER_GROEP ) {
				break;
			}
		}
		return $items;
	}

	/** Eigen machines: op serienummer, type, merk of eindklant. */
	private static function machines( $term ) {
		$items = array();
		foreach ( self::garantiegegevens( 'machines' ) as $machine ) {
			if ( ! self::past( $term, array( $machine['serienummer'], $machine['machine'], $machine['merk'], $machine['klant'] ) ) ) {
				continue;
			}
			$items[] = self::item(
				HDP_Garantie::machinenaam( $machine['machine'], $machine['serienummer'] ),
				trim( $machine['merk'] . ( $machine['klant'] ? ' · ' . $machine['klant'] : '' ), ' ·' ),
				add_query_arg(
					array(
						'toon'        => 'machines',
						'serienummer' => $machine['serienummer'],
					),
					home_url( '/garantie/' )
				) . '#hdp-machine-gekozen'
			);
			if ( count( $items ) >= self::PER_GROEP ) {
				break;
			}
		}
		return $items;
	}

	/** Downloads, content en huisstijl: precies wat de dealer op die pagina's ziet. */
	private static function downloads( $term ) {
		$items = array();
		foreach ( HDP_Downloads_CPT::CATEGORIEEN as $categorie ) {
			$resultaat = HDP_Downloads_Render::zichtbare_downloads( $categorie );
			foreach ( $resultaat['downloads'] as $download ) {
				$merk = HDP_Downloads_CPT::get_merk( $download->ID );
				if ( ! self::past( $term, array( $download->post_title, $merk, wp_strip_all_tags( $download->post_content ) ) ) ) {
					continue;
				}
				$items[] = self::item(
					$download->post_title,
					implode( ' · ', array_filter( array( HDP_I18N::t( 'zoeken_soort_' . $categorie ), HDP_Downloads_CPT::type_label( $download->ID ), $merk ) ) ),
					HDP_Downloads_CPT::download_url( $download->ID, 0, 'bekijken' ),
					true
				);
				if ( count( $items ) >= self::PER_GROEP ) {
					return $items;
				}
			}
		}
		return $items;
	}

	/** Wat het script in de header nodig heeft. */
	public static function script_gegevens() {
		return array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'hdp-portaal-zoeken' ),
			'teksten' => array(
				'laden'  => HDP_I18N::t( 'zoeken_laden' ),
				'niets'  => HDP_I18N::t( 'zoeken_niets' ),
				'fout'   => HDP_I18N::t( 'zoeken_fout' ),
				'nieuw'  => HDP_I18N::t( 'zoeken_nieuw_tabblad' ),
			),
		);
	}
}
