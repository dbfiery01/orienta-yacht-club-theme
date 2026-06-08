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

			// Patch specific members by email: [ { email, set:{field:val,...} } ].
			$patch = $req->get_param( 'patch' );
			if ( is_array( $patch ) ) {
				$cur = oyc_get_roster();
				$changed = 0; $unmatched = array();
				foreach ( $patch as $p ) {
					if ( ! is_array( $p ) || empty( $p['email'] ) || empty( $p['set'] ) || ! is_array( $p['set'] ) ) {
						continue;
					}
					$em = strtolower( trim( (string) $p['email'] ) );
					$hit = false;
					foreach ( $cur as $i => $m ) {
						$me = strtolower( trim( (string) ( isset( $m['email'] ) ? $m['email'] : '' ) ) );
						if ( $me === $em ) {
							$cur[ $i ] = array_merge( $m, $p['set'] );
							$changed++; $hit = true;
						}
					}
					if ( ! $hit ) { $unmatched[] = $em; }
				}
				update_option( 'oyc_fleet_roster', wp_json_encode( $cur ), false );
				return array( 'ok' => true, 'changed' => $changed, 'unmatched' => $unmatched, 'total' => count( $cur ) );
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

	// Admin-only: move the roster PDF out of the public uploads path into a
	// protected folder (with an .htaccess deny) so only the members-only
	// streaming endpoint below can serve it. Run once.
	register_rest_route( 'oyc/v1', '/protect-roster-pdf', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return current_user_can( 'manage_options' ); },
		'callback'            => function () {
			$up   = wp_upload_dir();
			$src  = $up['basedir'] . '/2026/01/2025-Fleet-Roster-Final-Edition.pdf';
			$dir  = $up['basedir'] . '/oyc-protected';
			$cur  = (string) get_option( 'oyc_roster_pdf_path', '' );
			if ( $cur && file_exists( $cur ) ) {
				return array( 'ok' => true, 'already' => true );
			}
			if ( ! file_exists( $dir ) ) {
				wp_mkdir_p( $dir );
			}
			file_put_contents( $dir . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n" );
			file_put_contents( $dir . '/index.html', '' );
			if ( ! file_exists( $src ) ) {
				return new WP_REST_Response( array( 'error' => 'source PDF not found', 'looked' => $src ), 404 );
			}
			$dest = $dir . '/roster-' . wp_generate_password( 16, false, false ) . '.pdf';
			if ( @rename( $src, $dest ) || ( @copy( $src, $dest ) && @unlink( $src ) ) ) {
				update_option( 'oyc_roster_pdf_path', $dest, false );
				return array( 'ok' => true, 'protected' => true );
			}
			return new WP_REST_Response( array( 'error' => 'could not move file' ), 500 );
		},
	) );
} );

/**
 * Members-only streaming of the protected roster PDF at /fleet-roster-pdf/.
 * Non-members are sent to the login screen; the file itself lives in a
 * deny-all folder so the old public URL no longer serves it.
 */
add_action( 'template_redirect', function () {
	$path = strtok( $_SERVER['REQUEST_URI'] ?? '', '?' );
	if ( '/fleet-roster-pdf' !== rtrim( (string) $path, '/' ) ) {
		return;
	}
	if ( ! is_user_logged_in() ) {
		auth_redirect();
		exit;
	}
	$file = (string) get_option( 'oyc_roster_pdf_path', '' );
	if ( ! $file || ! file_exists( $file ) ) {
		status_header( 404 );
		exit;
	}
	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: inline; filename="2025-Fleet-Roster.pdf"' );
	header( 'Content-Length: ' . filesize( $file ) );
	header( 'X-Robots-Tag: noindex, nofollow' );
	readfile( $file );
	exit;
}, 0 );
