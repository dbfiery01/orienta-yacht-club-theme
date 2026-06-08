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
			// Preferred path: send members as a native JSON array (WP parses the
			// JSON body, so no string/base64 decoding — malformed JSON is rejected
			// by REST, making it self-validating). Small batches dodge size limits.
			//   { batch: [ {member}, ... ], mode: "replace"|"append" }
			$batch = $req->get_param( 'batch' );
			if ( is_array( $batch ) ) {
				$mode = (string) $req->get_param( 'mode' );
				$cur  = ( 'replace' === $mode ) ? array() : oyc_get_roster();
				foreach ( $batch as $m ) {
					if ( is_array( $m ) ) {
						$cur[] = $m;
					}
				}
				update_option( 'oyc_fleet_roster', wp_json_encode( $cur ), false );
				return array( 'ok' => true, 'added' => count( $batch ), 'total' => count( $cur ) );
			}

			// Chunked upload (small bodies dodge WAF/body-size limits):
			//   {reset:true}                -> clear the buffer
			//   {append:"<base64 chunk>"}   -> append a chunk to the buffer
			//   {finalize:true}             -> decode buffer (gzip ok) -> store
			// or single-shot {b64:"..."}.
			$reset    = (bool) $req->get_param( 'reset' );
			$finalize = (bool) $req->get_param( 'finalize' );
			$append   = (string) $req->get_param( 'append' );
			$b64      = (string) $req->get_param( 'b64' );

			if ( $reset ) {
				update_option( 'oyc_fleet_roster_buf', '', false );
			}
			if ( '' !== $append ) {
				$buf = (string) get_option( 'oyc_fleet_roster_buf', '' );
				update_option( 'oyc_fleet_roster_buf', $buf . $append, false );
				return array( 'ok' => true, 'buffered' => strlen( $buf ) + strlen( $append ) );
			}

			$decode_b64 = '';
			if ( $finalize ) {
				$payload = (string) get_option( 'oyc_fleet_roster_buf', '' );
			} elseif ( '' !== $b64 ) {
				$payload = $b64;
			} else {
				return array( 'ok' => true, 'buffered' => strlen( (string) get_option( 'oyc_fleet_roster_buf', '' ) ) );
			}

			// 1) Raw JSON (no encoding) — cleanest, avoids base64/+ pipeline issues.
			$arr = json_decode( $payload, true );
			$binlen = -1; $gz_ok = false; $gz_avail = function_exists( 'gzdecode' );
			// 2) Fall back to base64 / base64url / gzip (lenient decode).
			if ( ! is_array( $arr ) ) {
				$conv = str_replace( ' ', '+', strtr( $payload, '-_', '+/' ) );
				$bin  = base64_decode( $conv );
				$binlen = ( false === $bin ) ? -1 : strlen( $bin );
				$arr  = json_decode( (string) $bin, true );
				if ( ! is_array( $arr ) && $gz_avail ) {
					$un = @gzdecode( (string) $bin );
					$gz_ok = ( false !== $un );
					if ( $gz_ok ) {
						$arr = json_decode( $un, true );
					}
				}
				if ( ! is_array( $arr ) && $gz_avail ) {
					$un2 = @gzinflate( substr( (string) $bin, 10 ) ); // raw deflate fallback
					if ( false !== $un2 ) {
						$arr = json_decode( $un2, true );
					}
				}
			}
			if ( ! is_array( $arr ) ) {
				return new WP_REST_Response( array(
					'error'    => 'invalid json',
					'len'      => strlen( $payload ),
					'md5'      => md5( $payload ),
					'binlen'   => $binlen,
					'gz_avail' => $gz_avail,
					'gz_ok'    => $gz_ok,
				), 400 );
			}
			update_option( 'oyc_fleet_roster', wp_json_encode( $arr ), false );
			delete_option( 'oyc_fleet_roster_buf' );
			return array( 'ok' => true, 'count' => count( $arr ) );
		},
	) );
} );
