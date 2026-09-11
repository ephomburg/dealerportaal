<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Eén centrale bron voor de merken die Homburg voert — gebruikt bij het
 * kiezen van een "Merk" bij het uploaden (HDP_Admin_Upload) én bij het
 * instellen van de "toegestane merken" van een dealer (HDP_Admin_Upload's
 * gebruikersoverzicht en HDP_User_Fields in wp-admin). Voorheen waren dat
 * twee losse vrije tekstvelden die makkelijk uit de pas konden lopen
 * (tikfouten, ander hoofdlettergebruik) met de vaste merkenlijst bij
 * uploaden — nu delen ze allemaal dezelfde lijst en hetzelfde opslagformaat.
 */
class HDP_Merken {

	public static function lijst() {
		return array(
			'Ag Leader',
			'Bogballe',
			'Bredal',
			'Draincleaners',
			'Ekobot',
			'Escarda',
			'Garford',
			'HARDI',
			'Novaxi',
			'PerPlant',
			'Rabe',
			'SmartSOLUTIONS',
			'Stanhay',
			'Tefen',
			'The Handler',
			'Väderstad',
			'Zürn',
		);
	}

	/**
	 * Zet een opgeslagen, kommagescheiden hdp_merken-waarde om naar een
	 * array (getrimd, zonder lege waarden) — voor het aanvinken van de
	 * juiste checkboxes bij het tonen van een formulier.
	 */
	public static function naar_array( $csv ) {
		if ( '' === trim( (string) $csv ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $csv ) ) ) );
	}

	/**
	 * Zet een geselecteerde lijst (bijv. rechtstreeks uit $_POST['hdp_merken'])
	 * om naar het opgeslagen kommagescheiden formaat, en filtert daarbij
	 * alles weg dat niet in de vaste lijst voorkomt — zodat via een
	 * handmatig samengestelde request nooit een willekeurige waarde in de
	 * user-meta terecht kan komen.
	 */
	public static function uit_selectie( $geselecteerd ) {
		$geldig = array_values( array_intersect( (array) $geselecteerd, self::lijst() ) );
		return implode( ', ', $geldig );
	}
}
