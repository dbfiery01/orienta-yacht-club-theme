<?php
/**
 * Fleet Roster — members-only searchable directory.
 *
 * PRIVACY: the roster contains member PII (names, addresses, phones, emails).
 * It is stored ONLY in the site database (the `oyc_fleet_roster` option, which
 * is NOT exposed via REST and NOT autoloaded) — never in the theme repo, which
 * is public. The data is rendered server-side and ONLY to logged-in members.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return the decoded roster array (server-side only).
 */
function oyc_get_roster() {
	$json = get_option( 'oyc_fleet_roster', '' );
	$arr  = json_decode( (string) $json, true );
	return is_array( $arr ) ? $arr : array();
}

/**
 * Admin-only write endpoint to load/replace the roster JSON in the DB.
 * Body: { b64: "<base64 of the JSON array>" }. Admin (manage_options) only.
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'oyc/v1', '/roster', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		'callback'            => function ( $req ) {
			$b64 = (string) $req->get_param( 'b64' );
			if ( '' === $b64 ) {
				return new WP_REST_Response( array( 'error' => 'no data' ), 400 );
			}
			$json = base64_decode( $b64, true );
			$arr  = json_decode( (string) $json, true );
			if ( ! is_array( $arr ) ) {
				return new WP_REST_Response( array( 'error' => 'invalid json' ), 400 );
			}
			// Store compact, not autoloaded.
			update_option( 'oyc_fleet_roster', wp_json_encode( $arr ), false );
			return array( 'ok' => true, 'count' => count( $arr ) );
		},
	) );
} );
