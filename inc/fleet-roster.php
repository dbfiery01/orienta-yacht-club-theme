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
 * Email comparison key: lower case, and for Gmail the dots and any +tag in
 * the name are ignored (Gmail delivers dan.smith@ and dansmith@ to the same
 * inbox), so a roster address and an account address that differ only that
 * way are treated as the same member.
 */
function oyc_email_key( $email ) {
	$e = strtolower( trim( (string) $email ) );
	if ( false === strpos( $e, '@' ) ) {
		return $e;
	}
	list( $local, $domain ) = explode( '@', $e, 2 );
	if ( in_array( $domain, array( 'gmail.com', 'googlemail.com' ), true ) ) {
		$local  = str_replace( '.', '', preg_replace( '/\+.*$/', '', $local ) );
		$domain = 'gmail.com';
	}
	return $local . '@' . $domain;
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
			update_option( 'oyc_fleet_roster_updated', time(), false );
			if ( function_exists( 'oyc_roster_pdf_save' ) ) {
				oyc_roster_pdf_save();
			}
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
	// The PDF built from the latest Upload Roster; built now if it is missing
	// (e.g. the roster was loaded before this existed). The old 2025 edition
	// (oyc_roster_pdf_path) is no longer served but is left on the server.
	$file = (string) get_option( 'oyc_roster_pdf_generated', '' );
	if ( ( ! $file || ! file_exists( $file ) ) && oyc_get_roster() && function_exists( 'oyc_roster_pdf_save' ) ) {
		oyc_roster_pdf_save();
		$file = (string) get_option( 'oyc_roster_pdf_generated', '' );
	}
	if ( ! $file || ! file_exists( $file ) ) {
		status_header( 404 );
		exit;
	}
	$ts = (int) get_option( 'oyc_fleet_roster_updated', 0 );
	status_header( 200 );
	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: inline; filename="OYC-Fleet-Roster' . ( $ts ? '-' . wp_date( 'Y-m-d', $ts ) : '' ) . '.pdf"' );
	header( 'Content-Length: ' . filesize( $file ) );
	header( 'X-Robots-Tag: noindex, nofollow' );
	readfile( $file );
	exit;
}, 0 );

/* ─────────────────────────────────────────────────────────────────────────────
 * Profile edits → Fleet Roster directory (page and PDF)
 *
 * When a member's profile is saved (by the member, an officer, or an admin),
 * the changed fields are copied into that member's Fleet Roster entry, matched
 * by email (then by unique first + last name), and the PDF is rebuilt once at
 * the end of the request. Only the fields that changed are copied, so a roster
 * upload's data is not overwritten by older account values.
 *
 * A new member who is not on the roster yet (an account an officer created, or
 * a sign-up an officer confirmed) is added to the directory from their profile.
 * Other accounts that are not on the roster (admins, unconfirmed or old
 * sign-ups) are left out. Roster uploads replace the directory as before.
 * ───────────────────────────────────────────────────────────────────────────── */

/** User meta key => directory field. */
function oyc_roster_sync_map() {
	return array(
		'first_name'            => 'name',
		'last_name'             => 'name',
		'billing_address_1'     => 'street',
		'billing_address_2'     => 'street',
		'billing_city'          => 'city',
		'billing_state'         => 'state',
		'billing_postcode'      => 'zip',
		'oyc_phone'             => 'phone',
		'oyc_membership_type'   => 'category',
		'oyc_member_since'      => 'since',
		'oyc_locker'            => 'locker',
		'oyc_dinghy_rack'       => 'dinghy',
		'oyc_member_number'     => 'number',
		'oyc_dues_paid_through' => 'dues',
		'active'                => 'status', // WP-Members approval: may add a new member.
	);
}

/** Queue a user's changed directory field for syncing at the end of the request. */
function oyc_roster_sync_queue( $user_id, $field, $old_email = '' ) {
	if ( ! empty( $GLOBALS['oyc_roster_importing'] ) ) {
		return; // A roster upload is replacing the directory itself.
	}
	global $oyc_roster_sync;
	if ( ! is_array( $oyc_roster_sync ) ) {
		$oyc_roster_sync = array();
		add_action( 'shutdown', 'oyc_roster_sync_run' );
	}
	$q = $oyc_roster_sync[ $user_id ] ?? array( 'fields' => array(), 'old_email' => '' );
	$q['fields'][ $field ] = true;
	if ( $old_email ) {
		$q['old_email'] = $old_email;
	}
	$oyc_roster_sync[ $user_id ] = $q;
}

add_action( 'added_user_meta', 'oyc_roster_sync_meta', 10, 3 );
add_action( 'updated_user_meta', 'oyc_roster_sync_meta', 10, 3 );
add_action( 'deleted_user_meta', 'oyc_roster_sync_meta', 10, 3 );
function oyc_roster_sync_meta( $meta_id, $user_id, $key ) {
	$map = oyc_roster_sync_map();
	if ( is_string( $key ) && isset( $map[ $key ] ) ) {
		oyc_roster_sync_queue( (int) $user_id, $map[ $key ] );
	}
}

add_action( 'profile_update', function ( $user_id, $old ) {
	$u = get_userdata( $user_id );
	if ( $u && $old && strtolower( $u->user_email ) !== strtolower( (string) $old->user_email ) ) {
		oyc_roster_sync_queue( (int) $user_id, 'email', (string) $old->user_email );
	}
}, 10, 2 );

/**
 * Index of a member's directory entry, or null: by email (Gmail dots and
 * +tags ignored), then by unique first + last name, then by unique last name
 * whose first name starts the same way (Dan / Daniel, Bill / William is not
 * matched).
 */
function oyc_roster_find_entry( $roster, $emails, $first, $last ) {
	foreach ( array_filter( (array) $emails ) as $em ) {
		$k = oyc_email_key( $em );
		foreach ( $roster as $i => $e ) {
			if ( is_array( $e ) && '' !== $k && oyc_email_key( $e['email'] ?? '' ) === $k ) {
				return $i;
			}
		}
	}
	$first = trim( (string) $first );
	$last  = trim( (string) $last );
	if ( '' === $last ) {
		return null;
	}
	$full = array();
	$near = array();
	foreach ( $roster as $i => $e ) {
		if ( ! is_array( $e ) ) {
			continue;
		}
		$n     = trim( (string) ( $e['name'] ?? '' ) );
		$parts = preg_split( '/\s+/', $n );
		$el    = (string) array_pop( $parts );
		$ef    = implode( ' ', $parts );
		if ( '' !== $first && 0 === strcasecmp( $n, $first . ' ' . $last ) ) {
			$full[] = $i;
		}
		if ( 0 === strcasecmp( $el, $last ) && '' !== $first && '' !== $ef ) {
			$a = strtolower( $first );
			$b = strtolower( $ef );
			if ( 0 === strpos( $a, $b ) || 0 === strpos( $b, $a ) ) {
				$near[] = $i;
			}
		}
	}
	if ( 1 === count( $full ) ) {
		return $full[0];
	}
	return 1 === count( $near ) ? $near[0] : null;
}

/** Apply queued profile changes to the directory; rebuild the PDF if anything changed. */
function oyc_roster_sync_run() {
	global $oyc_roster_sync;
	$queue           = is_array( $oyc_roster_sync ) ? $oyc_roster_sync : array();
	$oyc_roster_sync = null;
	$roster          = oyc_get_roster();
	if ( ! $queue || ! $roster ) {
		return;
	}
	$changed = false;
	foreach ( $queue as $user_id => $q ) {
		$u = get_userdata( $user_id );
		if ( ! $u ) {
			continue;
		}
		$meta  = fn( $k ) => trim( (string) get_user_meta( $user_id, $k, true ) );
		$first = $meta( 'first_name' );
		$last  = $meta( 'last_name' );

		// Find the member's entry: email (old email if it just changed), then unique name.
		$idx = null;
		$idx = oyc_roster_find_entry( $roster, array( $q['old_email'], $u->user_email ), $first, $last );
		if ( null === $idx ) {
			// Not on the roster: add a new member, built from the whole profile.
			$entry = oyc_roster_entry_from_account( $u );
			if ( $entry ) {
				$roster  = oyc_roster_insert_sorted( $roster, $entry, $last );
				$changed = true;
			}
			continue;
		}

		$e = $roster[ $idx ];
		foreach ( array_keys( $q['fields'] ) as $f ) {
			switch ( $f ) {
				case 'name':
					if ( '' !== $first || '' !== $last ) {
						$e['name'] = trim( $first . ' ' . $last );
					}
					break;
				case 'status':
					break;
				case 'email':
					$e['email'] = $u->user_email;
					break;
				case 'street':
					$e['street'] = trim( $meta( 'billing_address_1' ) . ' ' . $meta( 'billing_address_2' ) );
					break;
				case 'phone':
					$phones    = isset( $e['phones'] ) && is_array( $e['phones'] ) ? array_values( $e['phones'] ) : array();
					$phones[0] = $meta( 'oyc_phone' );
					$e['phones'] = array_values( array_filter( $phones, 'strlen' ) );
					break;
				default:
					$keys = array_flip( oyc_roster_sync_map() );
					$e[ $f ] = $meta( $keys[ $f ] );
			}
		}
		if ( $e !== $roster[ $idx ] ) {
			$roster[ $idx ] = $e;
			$changed        = true;
		}
	}
	if ( $changed ) {
		update_option( 'oyc_fleet_roster', wp_json_encode( array_values( $roster ) ), false );
		update_option( 'oyc_fleet_roster_updated', time(), false );
		if ( function_exists( 'oyc_roster_pdf_save' ) ) {
			oyc_roster_pdf_save();
		}
	}
}

/**
 * Whether an account that is not on the roster should be added to the Fleet
 * Roster as a new member: created by an officer, or confirmed by an officer,
 * and not marked Expelled/Deceased. Site admins are only added when they have
 * a membership type.
 */
function oyc_roster_account_is_new_member( $u ) {
	$type = strtolower( trim( (string) get_user_meta( $u->ID, 'oyc_membership_type', true ) ) );
	if ( function_exists( 'oyc_roster_inactive_types' ) && in_array( $type, oyc_roster_inactive_types(), true ) ) {
		return false;
	}
	if ( user_can( $u, 'manage_options' ) && '' === $type ) {
		return false;
	}
	if ( '1' === (string) get_user_meta( $u->ID, 'oyc_officer_created', true ) ) {
		return true;
	}
	return '1' === (string) get_user_meta( $u->ID, 'active', true );
}

/** A Fleet Roster entry built from a new member's profile (null if not eligible). */
function oyc_roster_entry_from_account( $u ) {
	if ( ! oyc_roster_account_is_new_member( $u ) ) {
		return null;
	}
	$meta = fn( $k ) => trim( (string) get_user_meta( $u->ID, $k, true ) );
	$name = trim( $meta( 'first_name' ) . ' ' . $meta( 'last_name' ) );
	if ( '' === $name ) {
		return null;
	}
	return array(
		'name'     => $name,
		'spouse'   => '',
		'email'    => $u->user_email,
		'phones'   => array_values( array_filter( array( $meta( 'oyc_phone' ) ), 'strlen' ) ),
		'street'   => trim( $meta( 'billing_address_1' ) . ' ' . $meta( 'billing_address_2' ) ),
		'city'     => $meta( 'billing_city' ),
		'state'    => $meta( 'billing_state' ),
		'zip'      => $meta( 'billing_postcode' ),
		'boat'     => '',
		'boatLen'  => '',
		'boatType' => '',
		'category' => $meta( 'oyc_membership_type' ),
		'since'    => $meta( 'oyc_member_since' ),
		'locker'   => $meta( 'oyc_locker' ),
		'dinghy'   => $meta( 'oyc_dinghy_rack' ),
		'number'   => $meta( 'oyc_member_number' ),
		'dues'     => $meta( 'oyc_dues_paid_through' ),
		'listed'   => trim( $meta( 'last_name' ) . ', ' . $meta( 'first_name' ), ', ' ),
	);
}

/** Insert an entry in last-name order (the roster's order) without re-sorting the rest. */
function oyc_roster_insert_sorted( $roster, $entry, $last ) {
	$key = strtolower( ( '' !== $last ? $last : (string) preg_replace( '/^.*\s/', '', $entry['name'] ) ) . ' ' . $entry['name'] );
	$out = array();
	$done = false;
	foreach ( $roster as $e ) {
		$n  = is_array( $e ) ? trim( (string) ( $e['name'] ?? '' ) ) : '';
		$ek = strtolower( (string) preg_replace( '/^.*\s/', '', $n ) . ' ' . $n );
		if ( ! $done && strcmp( $ek, $key ) > 0 ) {
			$out[] = $entry;
			$done  = true;
		}
		$out[] = $e;
	}
	if ( ! $done ) {
		$out[] = $entry;
	}
	return $out;
}
