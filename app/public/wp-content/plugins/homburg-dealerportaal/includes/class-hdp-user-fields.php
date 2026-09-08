<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Voegt dealerrechten (goedkeuring, merken, korting) toe aan het
 * gebruikersprofielscherm, zodat een beheerder dealers kan autoriseren
 * zonder in de database te hoeven kijken.
 */
class HDP_User_Fields {

	public static function init() {
		add_action( 'show_user_profile', array( __CLASS__, 'render_fields' ) );
		add_action( 'edit_user_profile', array( __CLASS__, 'render_fields' ) );
		add_action( 'personal_options_update', array( __CLASS__, 'save_fields' ) );
		add_action( 'edit_user_profile_update', array( __CLASS__, 'save_fields' ) );
	}

	public static function render_fields( $user ) {
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}

		$goedgekeurd = get_user_meta( $user->ID, 'hdp_goedgekeurd', true );
		$merken      = get_user_meta( $user->ID, 'hdp_merken', true );
		$korting     = get_user_meta( $user->ID, 'hdp_korting', true );

		wp_nonce_field( 'hdp_user_fields', 'hdp_user_fields_nonce' );
		?>
		<h2>Dealerportaal</h2>
		<table class="form-table">
			<tr>
				<th><label for="hdp_goedgekeurd">Dealeraccount goedgekeurd</label></th>
				<td>
					<label>
						<input type="checkbox" name="hdp_goedgekeurd" id="hdp_goedgekeurd" value="1" <?php checked( $goedgekeurd, '1' ); ?>>
						Deze gebruiker mag inloggen op het dealerportaal
					</label>
				</td>
			</tr>
			<tr>
				<th><label for="hdp_merken">Toegestane merken</label></th>
				<td>
					<?php $gekozen_merken = HDP_Merken::naar_array( $merken ); ?>
					<?php foreach ( HDP_Merken::lijst() as $merk ) : ?>
						<label>
							<input type="checkbox" name="hdp_merken[]" value="<?php echo esc_attr( $merk ); ?>" <?php checked( in_array( $merk, $gekozen_merken, true ) ); ?>>
							<?php echo esc_html( $merk ); ?>
						</label><br>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr>
				<th><label for="hdp_korting">Kortingscode / -percentage</label></th>
				<td>
					<input type="text" name="hdp_korting" id="hdp_korting" value="<?php echo esc_attr( $korting ); ?>" class="regular-text">
				</td>
			</tr>
		</table>
		<?php
	}

	public static function save_fields( $user_id ) {
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}

		if ( ! isset( $_POST['hdp_user_fields_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hdp_user_fields_nonce'] ) ), 'hdp_user_fields' ) ) {
			return;
		}

		$was_goedgekeurd   = (bool) get_user_meta( $user_id, 'hdp_goedgekeurd', true );
		$wordt_goedgekeurd = isset( $_POST['hdp_goedgekeurd'] );
		update_user_meta( $user_id, 'hdp_goedgekeurd', $wordt_goedgekeurd ? '1' : '' );

		if ( $wordt_goedgekeurd && ! $was_goedgekeurd ) {
			HDP_Roles::stuur_goedkeuringsmail( get_userdata( $user_id ) );
		}

		$merken = isset( $_POST['hdp_merken'] ) ? HDP_Merken::uit_selectie( wp_unslash( $_POST['hdp_merken'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- HDP_Merken::uit_selectie() filtert de waarden zelf via array_intersect() tegen de vaste merkenlijst.
		update_user_meta( $user_id, 'hdp_merken', $merken );

		if ( isset( $_POST['hdp_korting'] ) ) {
			update_user_meta( $user_id, 'hdp_korting', sanitize_text_field( wp_unslash( $_POST['hdp_korting'] ) ) );
		}
	}
}
