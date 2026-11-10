<?php
/**
 * Gebruikers > Merken per dealer: alle dealers onder elkaar, per merk een
 * vinkje, en in één keer opslaan. Zelfde opslag als het vak "Toegestane
 * merken" op het profiel (hdp_merken, via HDP_Merken::uit_selectie()).
 *
 * Collega's staan er niet in: die krijgen de merken van hun hoofdaccount
 * (HDP_Collegas). Alleen de rijen die je veranderde, worden verstuurd, zodat
 * het ook met honderden dealers binnen de grenzen van een formulier blijft.
 *
 * @package Homburg_Dealerportaal
 */

defined( 'ABSPATH' ) || exit;

class HDP_Merken_Overzicht {

	const PAGINA = 'hdp-merken-per-dealer';
	const NONCE  = 'hdp_merken_overzicht';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'registreer_menu' ) );
		add_action( 'admin_post_' . self::NONCE, array( __CLASS__, 'verwerk' ) );
	}

	public static function registreer_menu() {
		add_users_page( 'Merken per dealer', 'Merken per dealer', 'edit_users', self::PAGINA, array( __CLASS__, 'render' ) );
	}

	public static function url( $melding = '' ) {
		$url = admin_url( 'users.php?page=' . self::PAGINA );
		return $melding ? add_query_arg( 'hdp_melding', $melding, $url ) : $url;
	}

	/**
	 * De dealers in het overzicht: dealeraccounts die zelf geen collega zijn,
	 * op bedrijfsnaam.
	 *
	 * @return WP_User[]
	 */
	public static function dealers() {
		$dealers = array_values(
			array_filter(
				get_users( array( 'role' => HDP_Roles::ROLE ) ),
				static function ( $u ) {
					return ! ( class_exists( 'HDP_Collegas' ) && HDP_Collegas::is_collega( $u->ID ) );
				}
			)
		);
		usort(
			$dealers,
			static function ( $a, $b ) {
				return strcasecmp( self::naam( $a ), self::naam( $b ) );
			}
		);
		return $dealers;
	}

	public static function naam( WP_User $u ) {
		$bedrijf = trim( (string) get_user_meta( $u->ID, 'billing_company', true ) );
		return '' !== $bedrijf ? $bedrijf : ( $u->display_name ? $u->display_name : $u->user_login );
	}

	/**
	 * Slaat de gewijzigde rijen op.
	 *
	 * @param array $rijen user_id => lijst merken (of kommagescheiden tekst).
	 * @return int Hoeveel dealers zijn bijgewerkt.
	 */
	public static function sla_op( array $rijen ) {
		$aantal = 0;
		foreach ( $rijen as $user_id => $merken ) {
			$user = get_userdata( (int) $user_id );
			if ( ! $user || ! in_array( HDP_Roles::ROLE, (array) $user->roles, true ) ) {
				continue;
			}
			if ( class_exists( 'HDP_Collegas' ) && HDP_Collegas::is_collega( $user->ID ) ) {
				continue;
			}
			$lijst  = is_array( $merken ) ? $merken : HDP_Merken::naar_array( (string) $merken );
			$waarde = HDP_Merken::uit_selectie( array_map( 'trim', $lijst ) );
			if ( (string) get_user_meta( $user->ID, 'hdp_merken', true ) !== $waarde ) {
				update_user_meta( $user->ID, 'hdp_merken', $waarde );
				++$aantal;
			}
		}
		return $aantal;
	}

	public static function verwerk() {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( 'Geen toegang.' );
		}
		check_admin_referer( self::NONCE );
		$rijen = isset( $_POST['hdp_rij'] ) && is_array( $_POST['hdp_rij'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['hdp_rij'] ) ) : array();
		$aantal = self::sla_op( $rijen );
		wp_safe_redirect( self::url( 'opgeslagen-' . $aantal ) );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}
		$merken  = HDP_Merken::lijst();
		$dealers = self::dealers();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- alleen een melding.
		$melding = isset( $_GET['hdp_melding'] ) ? sanitize_key( wp_unslash( $_GET['hdp_melding'] ) ) : '';
		?>
		<div class="wrap hdp-merken-overzicht">
			<h1>Merken per dealer</h1>
			<p>Vink per dealer de merken aan die hij voert. Een dealer ziet in de webshop alleen de artikelen van zijn merken (plus Diversen en de outlet), en bij documenten en trainingen alleen wat bij zijn merken hoort. Collega's krijgen vanzelf de merken van hun hoofdaccount.</p>
			<?php if ( 0 === strpos( $melding, 'opgeslagen-' ) ) : ?>
				<?php $n = (int) substr( $melding, strlen( 'opgeslagen-' ) ); ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $n ? sprintf( 'Opgeslagen: %d dealer(s) bijgewerkt.', $n ) : 'Opgeslagen. Er was niets veranderd.' ); ?></p></div>
			<?php endif; ?>

			<?php if ( ! $dealers ) : ?>
				<p>Er zijn nog geen dealers.</p>
			</div>
				<?php
				return;
			endif;
			?>
			<p>
				<label for="hdp-merken-zoek" class="screen-reader-text">Zoek een dealer</label>
				<input type="search" id="hdp-merken-zoek" class="regular-text" placeholder="Zoek op bedrijf, e-mail of klantnummer">
				<span id="hdp-merken-telling" style="margin-left:8px;color:#50575e"><?php echo esc_html( count( $dealers ) ); ?> dealers</span>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="hdp-merken-form">
				<?php wp_nonce_field( self::NONCE ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::NONCE ); ?>">
				<div style="overflow-x:auto;max-width:100%">
					<table class="widefat striped hdp-merken-tabel">
						<thead>
							<tr>
								<th scope="col" style="min-width:220px">Dealer</th>
								<?php foreach ( $merken as $i => $merk ) : ?>
									<th scope="col" class="hdp-merk-kop">
										<button type="button" class="button-link hdp-kolom" data-kolom="<?php echo esc_attr( $i ); ?>" title="<?php echo esc_attr( 'Iedereen ' . $merk . ' aan of uit' ); ?>"><?php echo esc_html( $merk ); ?></button>
									</th>
								<?php endforeach; ?>
								<th scope="col">Let op</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $dealers as $dealer ) : ?>
								<?php
								$gekozen = HDP_Merken::naar_array( get_user_meta( $dealer->ID, 'hdp_merken', true ) );
								$relatie = HDP_PowerAll_Prijzen::relatie( $dealer->ID );
								$collega = class_exists( 'HDP_Collegas' ) ? count( HDP_Collegas::collegas( $dealer->ID ) ) : 0;
								$zoek    = mb_strtolower( implode( ' ', array( self::naam( $dealer ), $dealer->user_email, $relatie ? $relatie : '' ) ) );
								?>
								<tr data-zoek="<?php echo esc_attr( $zoek ); ?>">
									<th scope="row" style="font-weight:400">
										<a href="<?php echo esc_url( get_edit_user_link( $dealer->ID ) ); ?>"><strong><?php echo esc_html( self::naam( $dealer ) ); ?></strong></a><br>
										<span style="color:#50575e"><?php echo esc_html( $dealer->user_email . ( $relatie ? ' · klant ' . $relatie : '' ) . ( $collega ? ' · ' . $collega . ' collega(\'s)' : '' ) ); ?></span>
										<input type="hidden" class="hdp-rij-waarde" name="hdp_rij[<?php echo esc_attr( $dealer->ID ); ?>]" value="<?php echo esc_attr( implode( ', ', $gekozen ) ); ?>" disabled>
									</th>
									<?php foreach ( $merken as $i => $merk ) : ?>
										<td class="hdp-merk-cel">
											<input type="checkbox" class="hdp-merk-vak" data-kolom="<?php echo esc_attr( $i ); ?>" value="<?php echo esc_attr( $merk ); ?>" aria-label="<?php echo esc_attr( self::naam( $dealer ) . ': ' . $merk ); ?>" <?php checked( in_array( $merk, $gekozen, true ) ); ?>>
										</td>
									<?php endforeach; ?>
									<td class="hdp-merk-let-op" style="color:#996800;min-width:180px"><?php echo $gekozen ? '' : esc_html( 'Geen merken: ziet in de webshop alleen Diversen en de outlet' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<p class="submit" style="position:sticky;bottom:0;background:#f0f0f1;padding:12px 0;margin:0">
					<button type="submit" class="button button-primary">Opslaan</button>
					<span id="hdp-merken-gewijzigd" style="margin-left:10px;color:#50575e"></span>
				</p>
			</form>
			<style>
				.hdp-merken-tabel th.hdp-merk-kop { writing-mode: vertical-rl; transform: rotate(180deg); white-space: nowrap; padding: 10px 6px; vertical-align: bottom; }
				.hdp-merken-tabel td.hdp-merk-cel { text-align: center; padding: 8px 4px; }
				.hdp-merken-tabel tr.hdp-gewijzigd > th { box-shadow: inset 4px 0 0 #2271b1; }
			</style>
			<script>
			( function () {
				var form = document.getElementById( 'hdp-merken-form' );
				var zoek = document.getElementById( 'hdp-merken-zoek' );
				var telling = document.getElementById( 'hdp-merken-telling' );
				var gewijzigd = document.getElementById( 'hdp-merken-gewijzigd' );
				var rijen = Array.prototype.slice.call( form.querySelectorAll( 'tbody tr' ) );

				function werkBij( rij ) {
					var veld = rij.querySelector( '.hdp-rij-waarde' );
					var merken = Array.prototype.slice.call( rij.querySelectorAll( '.hdp-merk-vak:checked' ) ).map( function ( v ) { return v.value; } );
					veld.value = merken.join( ', ' );
					veld.disabled = false;
					rij.classList.add( 'hdp-gewijzigd' );
					rij.querySelector( '.hdp-merk-let-op' ).textContent = merken.length ? '' : 'Geen merken: ziet in de webshop alleen Diversen en de outlet';
					var n = form.querySelectorAll( 'tr.hdp-gewijzigd' ).length;
					gewijzigd.textContent = n + ' dealer(s) gewijzigd, nog niet opgeslagen';
				}

				form.addEventListener( 'change', function ( e ) {
					if ( e.target.classList.contains( 'hdp-merk-vak' ) ) {
						werkBij( e.target.closest( 'tr' ) );
					}
				} );

				// Kop aanklikken: dat merk voor alle zichtbare dealers aan (of uit, als het al overal aan stond).
				form.querySelectorAll( '.hdp-kolom' ).forEach( function ( knop ) {
					knop.addEventListener( 'click', function () {
						var kolom = knop.getAttribute( 'data-kolom' );
						var zichtbaar = rijen.filter( function ( r ) { return r.style.display !== 'none'; } );
						var vakken = zichtbaar.map( function ( r ) { return r.querySelector( '.hdp-merk-vak[data-kolom="' + kolom + '"]' ); } );
						var allesAan = vakken.every( function ( v ) { return v.checked; } );
						vakken.forEach( function ( v ) {
							if ( v.checked === allesAan ) {
								v.checked = ! allesAan;
								werkBij( v.closest( 'tr' ) );
							}
						} );
					} );
				} );

				zoek.addEventListener( 'input', function () {
					var term = zoek.value.trim().toLowerCase();
					var n = 0;
					rijen.forEach( function ( r ) {
						var past = ! term || r.getAttribute( 'data-zoek' ).indexOf( term ) !== -1;
						r.style.display = past ? '' : 'none';
						if ( past ) { n++; }
					} );
					telling.textContent = n + ' dealers';
				} );

				window.addEventListener( 'beforeunload', function ( e ) {
					if ( form.querySelector( 'tr.hdp-gewijzigd' ) && ! form.dataset.bezig ) {
						e.preventDefault();
						e.returnValue = '';
					}
				} );
				form.addEventListener( 'submit', function () { form.dataset.bezig = '1'; } );
			} )();
			</script>
		</div>
		<?php
	}
}
