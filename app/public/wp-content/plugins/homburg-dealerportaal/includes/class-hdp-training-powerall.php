<?php
/**
 * Trainingen naar PowerAll: elke aanmelding voor een betaalde training wordt
 * meteen een order in PowerAll, op het klantnummer van de dealer, één per
 * deelnemer. PowerAll factureert die order zoals elke andere, zodat de dealer
 * vóór de training kan betalen.
 *
 * - PowerAll accepteert alleen regels met een bestaande artikelcode; daarom
 *   een vast trainingsartikel (WooCommerce > PowerAll). De naam van de
 *   training, de datum en de deelnemer staan in de omschrijving van de regel.
 * - Een eigen reeks: TR1, TR2, ... In PowerAll ordernummer 91000001 en verder
 *   (de webshop gebruikt 90000001 en verder), of PowerAll nummert zelf.
 * - Dezelfde stand als de webshop: uit, proef of aan.
 * - Wordt een aanmelding geannuleerd nadat de order in PowerAll staat, dan
 *   moet die daar met de hand weg; het portaal kan in PowerAll niets verwijderen.
 *
 * @package Homburg_Dealerportaal
 */

defined( 'ABSPATH' ) || exit;

class HDP_Training_PowerAll {

	const META_PRIJS    = '_hdp_training_prijs';
	const HOOK          = 'hdp_powerall_training_versturen';
	const OPTION_TELLER = 'hdp_tr_teller';
	const TR_BASIS      = 91000000;
	const ACTIE_OPNIEUW = 'hdp_training_pa_opnieuw';

	public static function init() {
		add_action( self::HOOK, array( __CLASS__, 'verstuur' ) );
		add_action( HDP_PowerAll_Orders::HOOK_HERHAAL, array( __CLASS__, 'herhaal' ) );
		add_action( 'admin_post_' . self::ACTIE_OPNIEUW, array( __CLASS__, 'verwerk_opnieuw' ) );
	}

	/** De prijs per persoon, excl. btw; 0 = gratis. */
	public static function prijs( $training_id ) {
		return max( 0, round( (float) get_post_meta( $training_id, self::META_PRIJS, true ), 2 ) );
	}

	/** 95,00 (met een komma, in het Nederlands en het Frans). */
	private static function bedrag( $prijs ) {
		return number_format( (float) $prijs, 2, ',', '.' );
	}

	/** "€ 95,00 excl. btw per persoon", of '' als de training gratis is. */
	public static function prijstekst( $training_id ) {
		$prijs = self::prijs( $training_id );
		return $prijs > 0 ? sprintf( HDP_I18N::t( 'training_prijs_pp' ), '€ ' . self::bedrag( $prijs ) ) : '';
	}

	/** Gaat een aanmelding voor deze training naar PowerAll? */
	public static function gaat_naar_powerall( $training_id ) {
		return self::prijs( $training_id ) > 0 && 'uit' !== HDP_PowerAll_Orders::modus();
	}

	/**
	 * Direct na het opslaan van een aanmelding: vastleggen wat er met de
	 * factuur gebeurt, en de order op de achtergrond versturen.
	 *
	 * @return string De status: '' (gratis of PowerAll uit), 'wacht' of 'fout'.
	 */
	public static function na_aanmelding( array $rij ) {
		$training_id = (int) $rij['training_id'];
		if ( ! self::gaat_naar_powerall( $training_id ) ) {
			return '';
		}
		self::zet( $rij['id'], array( 'prijs' => self::prijs( $training_id ) ) );

		$waarom = self::waarom_niet( $rij );
		if ( $waarom ) {
			self::zet( $rij['id'], array( 'pa_status' => 'fout', 'pa_fout' => $waarom, 'pa_pogingen' => HDP_PowerAll_Orders::MAX_POGINGEN ) );
			HDP_Log::schrijf( 'Trainingsaanmelding ' . $rij['id'] . ' niet naar PowerAll: ' . $waarom, 'WAARSCHUWING' );
			return 'fout';
		}

		self::zet( $rij['id'], array( 'pa_status' => 'wacht' ) );
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK, array( (int) $rij['id'] ), 'hdp-powerall' );
		} else {
			self::verstuur( $rij['id'] );
		}
		return 'wacht';
	}

	/** Waarom deze aanmelding niet naar PowerAll kan; '' als het wel kan. */
	private static function waarom_niet( array $rij ) {
		if ( ! HDP_PowerAll_Prijzen::relatie( (int) $rij['user_id'] ) ) {
			return 'Deze dealer is niet aan een klantnummer in PowerAll gekoppeld. Factureer deze aanmelding met de hand.';
		}
		if ( '' === HDP_PowerAll_Orders::trainingsartikel() ) {
			return 'Er is nog geen trainingsartikel ingesteld (WooCommerce > PowerAll).';
		}
		return '';
	}

	/** Eén aanmelding, uitgepakt; null als die er niet is. */
	public static function aanmelding( $id ) {
		global $wpdb;
		$tabel = HDP_Trainingen::tabel();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- eigen tabel.
		$rij = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabel} WHERE id = %d", (int) $id ), ARRAY_A );
		return $rij ? HDP_Trainingen::pak_uit( $rij ) : null;
	}

	private static function zet( $id, array $velden ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- eigen tabel.
		$wpdb->update( HDP_Trainingen::tabel(), $velden, array( 'id' => (int) $id ) );
	}

	/**
	 * De verkooporder voor PowerAll.
	 *
	 * @return array|WP_Error
	 */
	public static function bouw( array $rij ) {
		$waarom = self::waarom_niet( $rij );
		if ( $waarom ) {
			return new WP_Error( 'hdp_pa_training', $waarom );
		}
		$training_id = (int) $rij['training_id'];
		$prijs       = (float) $rij['prijs'] > 0 ? (float) $rij['prijs'] : self::prijs( $training_id );
		$titel       = html_entity_decode( (string) $rij['training_titel'], ENT_QUOTES, 'UTF-8' );
		$datum       = (string) get_post_meta( $training_id, HDP_Trainingen::META_DATUM, true );
		$datum       = $datum ? gmdate( 'd-m-Y', strtotime( $datum ) ) : '';
		$bedrijf     = $rij['dealer_bedrijf'] ? $rij['dealer_bedrijf'] : $rij['dealer_naam'];

		return array(
			'RelationId'   => (string) HDP_PowerAll_Prijzen::relatie( (int) $rij['user_id'] ),
			'Reference1'   => $rij['pa_tr'] ? 'TR' . (int) $rij['pa_tr'] : 'Training ' . (int) $rij['id'],
			'Reference2'   => mb_substr( 'Training ' . $rij['deelnemer_naam'], 0, 40 ),
			'EmailAddress' => mb_substr( (string) $rij['dealer_email'], 0, 75 ),
			'InternalNote' => implode(
				"\n",
				array(
					'Trainingsaanmelding via het dealerportaal door ' . $bedrijf . ' (' . $rij['dealer_email'] . ').',
					'Training: ' . $titel . ( $datum ? ' op ' . $datum : '' ),
					'Deelnemer: ' . $rij['deelnemer_naam'] . ' (' . $rij['deelnemer_email'] . ')',
				)
			),
			'Lines'        => array(
				array(
					'ProductCode'        => HDP_PowerAll_Orders::trainingsartikel(),
					'QuantityOrdered'    => 1,
					'Description'        => mb_substr( 'Training ' . $titel . ( $datum ? ' – ' . $datum : '' ) . ' – ' . $rij['deelnemer_naam'], 0, 1024 ),
					'GrossPrice'         => round( $prijs, 2 ),
					'DiscountPercentage' => 0,
				),
			),
		);
	}

	/**
	 * Verstuurt de order (of draait hem proef).
	 *
	 * @return string 'verstuurd', 'proef', 'fout', 'overgeslagen' of 'al verstuurd'.
	 */
	public static function verstuur( $aanmelding_id ) {
		$rij = self::aanmelding( $aanmelding_id );
		if ( ! $rij || HDP_Trainingen::STATUS_AANGEMELD !== $rij['status'] ) {
			return 'overgeslagen';
		}
		$status = (string) $rij['pa_status'];
		if ( 'verstuurd' === $status ) {
			return 'al verstuurd';
		}
		$modus = HDP_PowerAll_Orders::modus();
		if ( 'uit' === $modus || '' === $status ) {
			return 'overgeslagen';
		}
		// Uit de proeftijd gaat nooit alsnog echt; "bezig" kan betekenen dat hij er al staat.
		if ( 'aan' === $modus && in_array( $status, array( 'proef', 'bezig' ), true ) ) {
			return 'overgeslagen';
		}

		if ( 'proef' === $modus ) {
			$body = self::bouw( $rij );
			if ( is_wp_error( $body ) ) {
				return self::mislukt( $rij, $body->get_error_message(), false );
			}
			$body['EntryNumber'] = HDP_PowerAll_Orders::PROEF_NUMMER;
			$antwoord            = HDP_PowerAll::post( 'sales-orders/simulate', $body );
			if ( is_wp_error( $antwoord ) ) {
				return self::mislukt( $rij, $antwoord->get_error_message(), false );
			}
			self::zet( $rij['id'], array( 'pa_status' => 'proef', 'pa_fout' => null ) );
			return 'proef';
		}

		// Een eigen nummer, één keer: bij een nieuwe poging hetzelfde, zodat PowerAll hem niet dubbel maakt.
		if ( ! (int) $rij['pa_tr'] ) {
			$rij['pa_tr'] = HDP_PowerAll_Orders::teller( self::OPTION_TELLER );
			self::zet( $rij['id'], array( 'pa_tr' => $rij['pa_tr'] ) );
		}
		$body = self::bouw( $rij );
		if ( is_wp_error( $body ) ) {
			return self::mislukt( $rij, $body->get_error_message(), false );
		}
		if ( 'portaal' === HDP_PowerAll_Orders::nummering() ) {
			$body['EntryNumber'] = self::TR_BASIS + (int) $rij['pa_tr'];
		}

		self::zet( $rij['id'], array( 'pa_status' => 'bezig' ) );
		$antwoord = HDP_PowerAll::post( 'sales-orders', $body );
		if ( is_wp_error( $antwoord ) ) {
			return self::mislukt( $rij, $antwoord->get_error_message(), true );
		}
		self::zet(
			$rij['id'],
			array(
				'pa_status' => 'verstuurd',
				'pa_nummer' => (string) ( $antwoord['EntryNumber'] ?? '' ),
				'pa_fout'   => null,
			)
		);
		return 'verstuurd';
	}

	private static function mislukt( array $rij, $melding, $opnieuw ) {
		self::zet(
			$rij['id'],
			array(
				'pa_status'   => 'fout',
				'pa_fout'     => $melding,
				'pa_pogingen' => $opnieuw ? (int) $rij['pa_pogingen'] + 1 : HDP_PowerAll_Orders::MAX_POGINGEN,
			)
		);
		HDP_Log::schrijf( 'PowerAll-order voor trainingsaanmelding ' . $rij['id'] . ': ' . $melding, 'WAARSCHUWING' );
		return 'fout';
	}

	/** Elk uur: wat door een storing niet aankwam (of bleef liggen) opnieuw proberen. */
	public static function herhaal() {
		if ( 'aan' !== HDP_PowerAll_Orders::modus() ) {
			return;
		}
		global $wpdb;
		$tabel = HDP_Trainingen::tabel();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- eigen tabel.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$tabel} WHERE status = %s AND ( ( pa_status = 'fout' AND pa_pogingen < %d ) OR ( pa_status = 'wacht' AND aangemaakt < %s ) ) ORDER BY id ASC LIMIT 20",
				HDP_Trainingen::STATUS_AANGEMELD,
				HDP_PowerAll_Orders::MAX_POGINGEN,
				gmdate( 'Y-m-d H:i:s', time() - 15 * MINUTE_IN_SECONDS )
			)
		);
		foreach ( $ids as $id ) {
			self::verstuur( (int) $id );
		}
	}

	/** Beheer: "Opnieuw versturen" bij een aanmelding die niet lukte. */
	public static function verwerk_opnieuw() {
		$id          = isset( $_GET['aanmelding'] ) ? absint( $_GET['aanmelding'] ) : 0;
		$training_id = isset( $_GET['training'] ) ? absint( $_GET['training'] ) : 0;
		check_admin_referer( self::ACTIE_OPNIEUW . '_' . $id );
		if ( ! current_user_can( HDP_Trainingen::CAP_BEHEER ) ) {
			wp_die( 'Geen toegang.' );
		}
		$rij = self::aanmelding( $id );
		if ( $rij && 'fout' === $rij['pa_status'] ) {
			if ( ! (float) $rij['prijs'] ) {
				self::zet( $id, array( 'prijs' => self::prijs( (int) $rij['training_id'] ) ) );
			}
			self::zet( $id, array( 'pa_pogingen' => 0 ) );
			self::verstuur( $id );
		}
		wp_safe_redirect( HDP_Trainingen::beheer_url( $training_id ) );
		exit;
	}

	/** Wat het team ziet in de kolom "Factuur (PowerAll)". */
	public static function statustekst( array $rij ) {
		switch ( (string) $rij['pa_status'] ) {
			case 'wacht':
				return 'Wordt verstuurd';
			case 'bezig':
				return 'Bezig met versturen (staat hij er niet, kijk dan in PowerAll)';
			case 'verstuurd':
				return 'Order ' . ( $rij['pa_tr'] ? 'TR' . (int) $rij['pa_tr'] . ', ' : '' ) . 'nummer ' . $rij['pa_nummer'];
			case 'proef':
				return 'Alleen proef (niets aangemaakt)';
			case 'fout':
				return 'Niet gelukt: ' . $rij['pa_fout'];
		}
		return '';
	}

	/** Voor de teammail: wat er met de factuur gebeurt. */
	public static function regel_voor_team( array $rij, $status ) {
		$training_id = (int) $rij['training_id'];
		if ( self::prijs( $training_id ) <= 0 ) {
			return '';
		}
		$bedrag = 'Kosten: € ' . self::bedrag( self::prijs( $training_id ) ) . ' excl. btw.';
		if ( 'uit' === HDP_PowerAll_Orders::modus() ) {
			return $bedrag . ' PowerAll staat uit: factureer deze aanmelding met de hand.';
		}
		if ( 'fout' === $status ) {
			$rij = self::aanmelding( $rij['id'] );
			return $bedrag . ' LET OP: gaat niet naar PowerAll. ' . ( $rij ? $rij['pa_fout'] : '' );
		}
		return $bedrag . ( 'proef' === HDP_PowerAll_Orders::modus() ? ' PowerAll staat op proef: er komt geen echte order.' : ' Er komt automatisch een order in PowerAll.' );
	}
}
