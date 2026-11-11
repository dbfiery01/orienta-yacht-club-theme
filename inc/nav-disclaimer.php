<?php
/**
 * "Not for navigation" disclaimer on the chart plotter (wind map) — server side.
 *
 * The dialog itself lives in inc/wind-map.php (showNavDisclaimer). When a
 * signed-in member ticks and accepts it, the browser posts here and the
 * acceptance is recorded on their user profile with a UTC date-time stamp and
 * the disclaimer version. Visitors who are not signed in are remembered only in
 * their browser.
 *
 * Endpoints (admin-ajax):
 *   oyc_nav_disc_me (priv + nopriv, GET)  → {logged, accepted, nonce}
 *   oyc_nav_disc_ok (priv, POST, nonce)   record an acceptance
 *
 * User meta:
 *   oyc_nav_disc_ack      latest acceptance {v, t (UTC, Y-m-d H:i:s)}
 *   oyc_nav_disc_history  every acceptance, newest last (capped at 50)
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Bump together with DISC_KEY in inc/wind-map.php whenever the wording changes. */
define( 'OYC_NAV_DISC_VER', 2 );

/** Latest acceptance for a user, or null. */
function oyc_nav_disc_get( $user_id ) {
	$a = get_user_meta( (int) $user_id, 'oyc_nav_disc_ack', true );
	return ( is_array( $a ) && ! empty( $a['t'] ) ) ? $a : null;
}

function oyc_nav_disc_me() {
	nocache_headers();
	$uid = get_current_user_id();
	if ( ! $uid ) {
		wp_send_json( array( 'logged' => false ) );
	}
	$a = oyc_nav_disc_get( $uid );
	wp_send_json( array(
		'logged'   => true,
		'accepted' => ( $a && (int) $a['v'] >= OYC_NAV_DISC_VER ) ? $a['t'] : null,
		'nonce'    => wp_create_nonce( 'oyc_nav_disc' ),
	) );
}
add_action( 'wp_ajax_oyc_nav_disc_me', 'oyc_nav_disc_me' );
add_action( 'wp_ajax_nopriv_oyc_nav_disc_me', 'oyc_nav_disc_me' );

function oyc_nav_disc_ok() {
	nocache_headers();
	$uid = get_current_user_id();
	if ( ! $uid || ! check_ajax_referer( 'oyc_nav_disc', 'nonce', false ) ) {
		wp_send_json_error( null, 403 );
	}
	$rec = array(
		'v' => OYC_NAV_DISC_VER,
		't' => current_time( 'mysql', true ), // UTC
	);
	update_user_meta( $uid, 'oyc_nav_disc_ack', $rec );
	$hist = get_user_meta( $uid, 'oyc_nav_disc_history', true );
	$hist = is_array( $hist ) ? $hist : array();
	$hist[] = $rec;
	update_user_meta( $uid, 'oyc_nav_disc_history', array_slice( $hist, -50 ) );
	wp_send_json_success( $rec );
}
add_action( 'wp_ajax_oyc_nav_disc_ok', 'oyc_nav_disc_ok' );

/** Read-only row on the wp-admin user profile. */
function oyc_nav_disc_profile( $user ) {
	$hist = get_user_meta( $user->ID, 'oyc_nav_disc_history', true );
	$hist = is_array( $hist ) ? $hist : array();
	$fmt  = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
	?>
	<h2><?php esc_html_e( 'Chart Plotter Disclaimer', 'orienta-yacht-club' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th><?php esc_html_e( 'Not-for-navigation acknowledgement', 'orienta-yacht-club' ); ?></th>
			<td>
				<?php
				if ( ! $hist ) {
					esc_html_e( 'Not accepted yet.', 'orienta-yacht-club' );
				} else {
					echo '<ul style="margin:0">';
					foreach ( array_reverse( $hist ) as $h ) {
						$ts = strtotime( $h['t'] . ' UTC' );
						echo '<li>' . esc_html( wp_date( $fmt, $ts ) ) . ' (' . esc_html( gmdate( 'Y-m-d H:i', $ts ) ) . ' UTC) &middot; version ' . (int) $h['v'] . '</li>';
					}
					echo '</ul>';
				}
				?>
				<p class="description"><?php esc_html_e( 'Recorded when the member ticks "I understand and agree" on the chart plotter\'s Not for navigation box (information only, use NOAA charts, does not replace good seamanship, holds OYC and Intualytics LLC harmless).', 'orienta-yacht-club' ); ?></p>
			</td>
		</tr>
	</table>
	<?php
}
add_action( 'show_user_profile', 'oyc_nav_disc_profile' );
add_action( 'edit_user_profile', 'oyc_nav_disc_profile' );
