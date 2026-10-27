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

	/** Titel of artikelnummer; dezelfde zoeklogica als de winkelpagina. */
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
			$prijs   = html_entity_decode( wp_strip_all_tags( wc_price( wc_get_price_to_display( $product ) ) ), ENT_QUOTES, 'UTF-8' );
			$items[] = self::item(
				$product->get_name(),
				trim( $product->get_sku() . ' · ' . $prijs, ' ·' ),
				get_permalink( $id )
			);
			if ( count( $items ) >= self::PER_GROEP ) {
				break;
			}
		}
		return $items;
	}

	/** Eigen bestellingen: op ordernummer, eigen referentie of een artikel erin. */
	private static function bestellingen( $term ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return array();
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => get_current_user_id(),
				'limit'       => 100,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		$items = array();
		foreach ( $orders as $order ) {
			$referentie = function_exists( 'homburg_wc_referentie' ) ? homburg_wc_referentie( $order ) : '';
			$velden     = array( $order->get_order_number(), $referentie );
			foreach ( $order->get_items() as $regel ) {
				$velden[] = $regel->get_name();
				$product  = is_callable( array( $regel, 'get_product' ) ) ? $regel->get_product() : null;
				if ( $product ) {
					$velden[] = $product->get_sku();
				}
			}

			if ( ! self::past( $term, $velden ) ) {
				continue;
			}

			$datum   = $order->get_date_created() ? HDP_I18N::datum( 'j F Y', $order->get_date_created()->getOffsetTimestamp() ) : '';
			$sub     = array_filter( array( $datum, wc_get_order_status_name( $order->get_status() ), $referentie ? sprintf( HDP_I18N::t( 'bestel_referentie' ), $referentie ) : '' ) );
			$items[] = self::item(
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

	/** Eigen claims: op claimnummer, serienummer, machine, factuurnummer of referentie. */
	private static function claims( $term ) {
		if ( ! HDP_Garantie::beschikbaar() ) {
			return array();
		}

		$claims = HDP_Garantie::claims();
		if ( is_wp_error( $claims ) ) {
			return array();
		}

		$items = array();
		foreach ( $claims as $claim ) {
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
		if ( ! HDP_Garantie::beschikbaar() ) {
			return array();
		}

		$machines = HDP_Garantie::machines();
		if ( is_wp_error( $machines ) ) {
			return array();
		}

		$items = array();
		foreach ( $machines as $machine ) {
			if ( ! self::past( $term, array( $machine['serienummer'], $machine['machine'], $machine['merk'], $machine['klant'] ) ) ) {
				continue;
			}
			$items[] = self::item(
				HDP_Garantie::machinenaam( $machine['machine'], $machine['serienummer'] ),
				trim( $machine['merk'] . ( $machine['klant'] ? ' · ' . $machine['klant'] : '' ), ' ·' ),
				add_query_arg( 'toon', 'machines', home_url( '/garantie/' ) )
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
