<?php
/**
 * Digital membership card.
 *
 *   /member-card/    Members only. The logged-in member's card: name, club
 *                    member number, membership type, dues year, member since,
 *                    and a QR code. Officers can preview anyone's card with
 *                    ?uid=<user id>.
 *   /verify-member/  Public, for club staff. Scanning the card's QR opens it and
 *                    shows a big VALID / NOT VALID panel with the member's name,
 *                    number and type, and nothing else from their profile.
 *
 * The QR carries the user ID plus a random per-member card key (user meta
 * oyc_card_key). Nothing in it can be guessed from a member number, and an
 * officer can "Reissue" a card from the Members page, which makes every old
 * QR (screenshots, printouts) show NOT VALID.
 *
 * A card is valid when the account may see the members' area (approved or
 * long-standing, not a visiting racer) and, when an officer has recorded a
 * "Dues paid through" year, that year is not in the past.
 *
 * Profile fields (user meta, set by officers on the Members page or by CSV):
 *   oyc_member_number      the club's own member number
 *   oyc_membership_type    e.g. Regular, Family, Senior, Life
 *   oyc_dues_paid_through  a year, e.g. 2026
 *
 * Both pages work with or without a WP Page record (same 404 fallback as the
 * Bulletin Board), and both are excluded from page caching and indexing.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OYC_CARD_SLUG', 'member-card' );
define( 'OYC_VERIFY_SLUG', 'verify-member' );

/*
 * While the card is being trialled, both sides carry a "not yet live" band.
 * Set to false (here, or define it in wp-config.php) when the club launches it.
 */
if ( ! defined( 'OYC_CARD_NOT_LIVE' ) ) {
	define( 'OYC_CARD_NOT_LIVE', true );
}

/**
 * The "not yet live" band, or '' once the card is launched.
 */
function oyc_card_not_live_band() {
	if ( ! OYC_CARD_NOT_LIVE ) {
		return '';
	}
	return '<span class="mcard__notlive">' . esc_html__( 'Preview · This card is not yet live', 'orienta-yacht-club' ) . '</span>';
}

/**
 * Officer-editable card fields: meta key => label.
 */
function oyc_card_fields() {
	return array(
		'oyc_member_number'     => __( 'Member Number', 'orienta-yacht-club' ),
		'oyc_membership_type'   => __( 'Membership Type', 'orienta-yacht-club' ),
		'oyc_dues_paid_through' => __( 'Dues Paid Through (year)', 'orienta-yacht-club' ),
	);
}

/**
 * Suggestions for the Membership Type field (free text is still allowed).
 */
function oyc_card_membership_types() {
	return array( 'Regular', 'Family', 'Senior', 'Junior', 'Associate', 'Honorary', 'Life' );
}

/**
 * Clean a value for one of the card fields.
 */
function oyc_card_clean_field( $key, $value ) {
	$value = trim( sanitize_text_field( (string) $value ) );
	if ( 'oyc_dues_paid_through' === $key && '' !== $value ) {
		// Accept "2026", "Dec 31 2026", "2026-12-31", "12/31/2026": keep the year.
		if ( preg_match( '/(19|20)\d{2}/', $value, $m ) ) {
			return $m[0];
		}
		return '';
	}
	if ( 'oyc_member_number' === $key ) {
		$value = ltrim( $value, '#' );
		return substr( $value, 0, 20 );
	}
	return substr( $value, 0, 40 );
}

/* ─────────────────────────────────────────────────────────────────────────────
 * URLs, keys and status
 * ───────────────────────────────────────────────────────────────────────────── */

function oyc_card_url( $args = array() ) {
	$page = get_page_by_path( OYC_CARD_SLUG );
	$url  = $page ? get_permalink( $page ) : home_url( '/' . OYC_CARD_SLUG . '/' );
	return $args ? add_query_arg( $args, $url ) : $url;
}

function oyc_card_verify_base_url() {
	$page = get_page_by_path( OYC_VERIFY_SLUG );
	return $page ? get_permalink( $page ) : home_url( '/' . OYC_VERIFY_SLUG . '/' );
}

/**
 * The member's card key, created on first use.
 */
function oyc_card_key( $user_id ) {
	$key = (string) get_user_meta( $user_id, 'oyc_card_key', true );
	if ( ! preg_match( '/^[a-z0-9]{12}$/', $key ) ) {
		$key = strtolower( wp_generate_password( 12, false, false ) );
		update_user_meta( $user_id, 'oyc_card_key', $key );
	}
	return $key;
}

/**
 * Replace the card key so every existing QR stops verifying.
 */
function oyc_card_reissue( $user_id ) {
	delete_user_meta( $user_id, 'oyc_card_key' );
	return oyc_card_key( $user_id );
}

/**
 * The URL a member's QR code opens.
 */
function oyc_card_verify_url( $user_id ) {
	return add_query_arg( 'c', (int) $user_id . '-' . oyc_card_key( $user_id ), oyc_card_verify_base_url() );
}

/**
 * Resolve a scanned "c" value to a user, or null when it is not a current card.
 *
 * @return array{user:WP_User|null, reason:string}
 */
function oyc_card_lookup( $code ) {
	$code = strtolower( trim( (string) $code ) );
	if ( ! preg_match( '/^(\d{1,10})-([a-z0-9]{12})$/', $code, $m ) ) {
		return array( 'user' => null, 'reason' => 'unreadable' );
	}
	$user = get_userdata( (int) $m[1] );
	if ( ! $user ) {
		return array( 'user' => null, 'reason' => 'unknown' );
	}
	$key = (string) get_user_meta( $user->ID, 'oyc_card_key', true );
	if ( '' === $key || ! hash_equals( $key, $m[2] ) ) {
		return array( 'user' => null, 'reason' => 'reissued' );
	}
	return array( 'user' => $user, 'reason' => '' );
}

/**
 * Card details for display.
 */
function oyc_card_data( WP_User $user ) {
	$name = trim( $user->first_name . ' ' . $user->last_name );
	if ( '' === $name ) {
		$name = $user->display_name;
	}

	$since    = (string) get_user_meta( $user->ID, 'oyc_member_since', true );
	$since_ts = $since ? strtotime( $since ) : false;
	if ( $since_ts ) {
		$since = date( 'Y', $since_ts );
	} elseif ( '' === $since && $user->user_registered ) {
		$since = date( 'Y', strtotime( $user->user_registered ) );
	}

	return array(
		'name'    => $name,
		'number'  => (string) get_user_meta( $user->ID, 'oyc_member_number', true ),
		'type'    => (string) get_user_meta( $user->ID, 'oyc_membership_type', true ),
		'through' => (string) get_user_meta( $user->ID, 'oyc_dues_paid_through', true ),
		'since'   => $since,
	);
}

/**
 * Whether a member's card is valid today.
 *
 * @return array{valid:bool, reason:string} reason is '' when valid, else
 *               'not_member' | 'expired'.
 */
function oyc_card_status( WP_User $user ) {
	$state = function_exists( 'oyc_user_approval_state' ) ? oyc_user_approval_state( $user->ID ) : 'approved';
	if ( ! in_array( $state, array( 'approved', 'legacy' ), true )
		|| ( function_exists( 'oyc_is_guest_racer' ) && oyc_is_guest_racer( $user->ID ) ) ) {
		return array( 'valid' => false, 'reason' => 'not_member' );
	}
	$through = (int) get_user_meta( $user->ID, 'oyc_dues_paid_through', true );
	if ( $through && $through < (int) current_time( 'Y' ) ) {
		return array( 'valid' => false, 'reason' => 'expired' );
	}
	return array( 'valid' => true, 'reason' => '' );
}

/**
 * Other accounts that already use a member number (to warn about duplicates).
 *
 * @return WP_User[]
 */
function oyc_card_number_taken_by( $number, $except_id = 0 ) {
	if ( '' === (string) $number ) {
		return array();
	}
	$users = get_users( array(
		'meta_key'   => 'oyc_member_number',
		'meta_value' => (string) $number,
		'exclude'    => $except_id ? array( (int) $except_id ) : array(),
		'number'     => 5,
	) );
	return $users;
}

/* ─────────────────────────────────────────────────────────────────────────────
 * Routing, caching, assets
 * ───────────────────────────────────────────────────────────────────────────── */

function oyc_card_current_route() {
	global $wp;
	if ( is_page( OYC_CARD_SLUG ) ) {
		return OYC_CARD_SLUG;
	}
	if ( is_page( OYC_VERIFY_SLUG ) ) {
		return OYC_VERIFY_SLUG;
	}
	$req = isset( $wp->request ) ? untrailingslashit( (string) $wp->request ) : '';
	return in_array( $req, array( OYC_CARD_SLUG, OYC_VERIFY_SLUG ), true ) ? $req : '';
}

add_filter( 'template_include', function ( $template ) {
	global $wp, $wp_query;
	$req = isset( $wp->request ) ? untrailingslashit( (string) $wp->request ) : '';
	$map = array(
		OYC_CARD_SLUG   => array( 'page-member-card.php', __( 'Membership Card', 'orienta-yacht-club' ) ),
		OYC_VERIFY_SLUG => array( 'page-verify-member.php', __( 'Verify Membership', 'orienta-yacht-club' ) ),
	);
	if ( isset( $map[ $req ] ) && is_page( $req ) ) {
		// A WP Page with this slug exists; use our template whatever template it picked.
		$file = locate_template( $map[ $req ][0] );
		return $file ? $file : $template;
	}
	if ( ! is_404() || ! isset( $map[ $req ] ) ) {
		return $template;
	}
	$file = locate_template( $map[ $req ][0] );
	if ( ! $file ) {
		return $template;
	}
	$title            = $map[ $req ][1];
	$wp_query->is_404 = false;
	status_header( 200 );
	add_filter( 'pre_get_document_title', fn() => $title . ' — ' . get_bloginfo( 'name' ) );
	add_filter( 'body_class', fn( $c ) => array_merge( array_diff( $c, array( 'error404' ) ), array( 'page', 'oyc-page-' . $req, 'oyc-card-page' ) ) );
	return $file;
}, 20 );

add_action( 'template_redirect', function () {
	if ( '' === oyc_card_current_route() ) {
		return;
	}
	nocache_headers();
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true );
	}
	header( 'Referrer-Policy: no-referrer' );
}, 1 );

add_filter( 'wp_robots', function ( $robots ) {
	if ( '' !== oyc_card_current_route() ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		unset( $robots['index'], $robots['follow'] );
	}
	return $robots;
} );

add_action( 'wp_enqueue_scripts', function () {
	if ( '' === oyc_card_current_route() ) {
		return;
	}
	$dir = get_template_directory();
	$uri = get_template_directory_uri();
	$ver = function ( $rel ) use ( $dir ) {
		return file_exists( $dir . $rel ) ? (string) filemtime( $dir . $rel ) : OYC_VERSION;
	};
	wp_enqueue_style( 'oyc-member-card', $uri . '/assets/member-card.css', array( 'oyc-style' ), $ver( '/assets/member-card.css' ) );
	if ( OYC_CARD_SLUG === oyc_card_current_route() ) {
		wp_enqueue_script( 'oyc-qrcode', $uri . '/assets/qrcode.js', array(), '1.4.4', true );
	}
	wp_enqueue_script( 'oyc-member-card', $uri . '/assets/member-card.js', array(), $ver( '/assets/member-card.js' ), true );
}, 100 );

/* ─────────────────────────────────────────────────────────────────────────────
 * Officer: CSV import of member numbers
 * ───────────────────────────────────────────────────────────────────────────── */

/**
 * Map a CSV header cell to a field name.
 */
function oyc_card_csv_header( $cell ) {
	$h = strtolower( preg_replace( '/[^a-z0-9]+/i', ' ', (string) $cell ) );
	$h = trim( preg_replace( '/\s+/', ' ', $h ) );
	$map = array(
		'email'                 => array( 'email', 'e mail', 'email address' ),
		'login'                 => array( 'username', 'user name', 'login', 'user login' ),
		'first'                 => array( 'first', 'first name', 'firstname', 'given name' ),
		'last'                  => array( 'last', 'last name', 'lastname', 'surname', 'family name' ),
		'name'                  => array( 'name', 'full name', 'member name', 'member' ),
		'oyc_member_number'     => array( 'member number', 'member no', 'member num', 'member', 'member id', 'number', 'no', 'membership number', 'membership no' ),
		'oyc_membership_type'   => array( 'membership type', 'member type', 'type', 'membership', 'class', 'category' ),
		'oyc_dues_paid_through' => array( 'dues paid through', 'paid through', 'valid through', 'expires', 'expiry', 'expiration', 'dues year', 'season', 'year' ),
		'oyc_member_since'      => array( 'member since', 'joined', 'since', 'year joined' ),
	);
	// "member #" and "#" arrive as "member" / "" after the cleanup above.
	if ( preg_match( '/#/', (string) $cell ) ) {
		return 'oyc_member_number';
	}
	foreach ( $map as $field => $aliases ) {
		if ( in_array( $h, $aliases, true ) ) {
			// "member" alone is ambiguous: a number column is far more likely in a roster.
			if ( 'member' === $h ) {
				return 'oyc_member_number';
			}
			return $field;
		}
	}
	return '';
}

/**
 * Import a roster CSV. Matches each row to an account by email, then username,
 * then first + last name (only when exactly one account has that name). Blank
 * cells never overwrite existing values.
 *
 * @param string $path    Uploaded file path.
 * @param bool   $dry_run Report what would change without saving.
 * @return array{updated:int, unchanged:int, unmatched:array, duplicates:array, error:string}
 */
function oyc_card_import_csv( $path, $dry_run = false ) {
	$out = array( 'updated' => 0, 'unchanged' => 0, 'unmatched' => array(), 'duplicates' => array(), 'error' => '' );

	$fh = fopen( $path, 'r' );
	if ( ! $fh ) {
		$out['error'] = __( 'The file could not be read.', 'orienta-yacht-club' );
		return $out;
	}

	$header = fgetcsv( $fh, 0, ',', '"', '\\' );
	if ( $header && isset( $header[0] ) ) {
		$header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header[0] ); // Excel BOM
	}
	$cols = array();
	foreach ( (array) $header as $i => $cell ) {
		$f = oyc_card_csv_header( $cell );
		if ( $f && ! in_array( $f, $cols, true ) ) {
			$cols[ $i ] = $f;
		}
	}
	if ( ! in_array( 'oyc_member_number', $cols, true ) && ! in_array( 'oyc_membership_type', $cols, true ) && ! in_array( 'oyc_dues_paid_through', $cols, true ) ) {
		fclose( $fh );
		$out['error'] = __( 'No "Member Number", "Membership Type" or "Dues Paid Through" column was found in the first row.', 'orienta-yacht-club' );
		return $out;
	}
	if ( ! array_intersect( array( 'email', 'login', 'first', 'name' ), $cols ) ) {
		fclose( $fh );
		$out['error'] = __( 'The file needs an Email, Username or Name column to match rows to accounts.', 'orienta-yacht-club' );
		return $out;
	}

	$fields   = array_merge( array_keys( oyc_card_fields() ), array( 'oyc_member_since' ) );
	$seen_num = array();
	$line     = 1;

	while ( ( $row = fgetcsv( $fh, 0, ',', '"', '\\' ) ) !== false ) {
		$line++;
		if ( ! array_filter( $row, fn( $v ) => '' !== trim( (string) $v ) ) ) {
			continue;
		}
		$r = array();
		foreach ( $cols as $i => $f ) {
			$r[ $f ] = trim( (string) ( $row[ $i ] ?? '' ) );
		}
		if ( empty( $r['first'] ) && empty( $r['last'] ) && ! empty( $r['name'] ) ) {
			$n = $r['name'];
			if ( false !== strpos( $n, ',' ) ) { // "Smith, John"
				list( $l, $fn ) = array_map( 'trim', explode( ',', $n, 2 ) );
				$r['first'] = $fn;
				$r['last']  = $l;
			} else {
				$parts      = preg_split( '/\s+/', $n );
				$r['last']  = array_pop( $parts );
				$r['first'] = implode( ' ', $parts );
			}
		}

		$user = null;
		if ( ! empty( $r['email'] ) && is_email( $r['email'] ) ) {
			$user = get_user_by( 'email', $r['email'] );
		}
		if ( ! $user && ! empty( $r['login'] ) ) {
			$user = get_user_by( 'login', $r['login'] );
		}
		if ( ! $user && ! empty( $r['first'] ) && ! empty( $r['last'] ) ) {
			$found = get_users( array(
				'number'     => 2,
				'meta_query' => array(
					'relation' => 'AND',
					array( 'key' => 'first_name', 'value' => $r['first'] ),
					array( 'key' => 'last_name', 'value' => $r['last'] ),
				),
			) );
			if ( 1 === count( $found ) ) {
				$user = $found[0];
			}
		}

		$label = trim( ( $r['first'] ?? '' ) . ' ' . ( $r['last'] ?? '' ) );
		if ( '' === $label ) {
			$label = $r['email'] ?? ( $r['login'] ?? '' );
		}

		if ( ! $user || ! oyc_officer_can_manage_user( $user->ID ) ) {
			$out['unmatched'][] = sprintf( __( 'Row %1$d: %2$s', 'orienta-yacht-club' ), $line, $label ? $label : __( '(no name)', 'orienta-yacht-club' ) );
			continue;
		}

		$changed = false;
		foreach ( $fields as $f ) {
			if ( ! isset( $r[ $f ] ) || '' === $r[ $f ] ) {
				continue;
			}
			$val = 'oyc_member_since' === $f ? sanitize_text_field( $r[ $f ] ) : oyc_card_clean_field( $f, $r[ $f ] );
			if ( '' === $val ) {
				continue;
			}
			if ( 'oyc_member_number' === $f ) {
				if ( isset( $seen_num[ $val ] ) && $seen_num[ $val ] !== $user->ID ) {
					$out['duplicates'][] = sprintf( __( 'Row %1$d: member number %2$s is also on an earlier row', 'orienta-yacht-club' ), $line, $val );
				}
				$seen_num[ $val ] = $user->ID;
			}
			if ( (string) get_user_meta( $user->ID, $f, true ) !== $val ) {
				$changed = true;
				if ( ! $dry_run ) {
					update_user_meta( $user->ID, $f, $val );
				}
			}
		}
		$out[ $changed ? 'updated' : 'unchanged' ]++;
	}
	fclose( $fh );

	if ( ! $dry_run && function_exists( 'oyc_audit_log' ) ) {
		oyc_audit_log( 'user.card_import', sprintf( 'Imported member numbers from CSV: %d updated, %d unchanged, %d unmatched', $out['updated'], $out['unchanged'], count( $out['unmatched'] ) ), 0 );
	}
	return $out;
}

/* ─────────────────────────────────────────────────────────────────────────────
 * Card badges (fun icons on the front of the card)
 * ───────────────────────────────────────────────────────────────────────────── */

/**
 * Badge slug => label. Stored as a comma list in user meta oyc_card_badges.
 * Members pick their own on the card page; officers can set them too.
 */
function oyc_card_badge_list() {
	return array(
		'power' => __( 'Power Boater', 'orienta-yacht-club' ),
		'sail'  => __( 'Sailor', 'orienta-yacht-club' ),
		'fish'  => __( 'Fisherman', 'orienta-yacht-club' ),
		'u42'   => __( '42 & Under', 'orienta-yacht-club' ),
	);
}

function oyc_card_get_badges( $user_id ) {
	$raw = (string) get_user_meta( $user_id, 'oyc_card_badges', true );
	return array_values( array_intersect( array_keys( oyc_card_badge_list() ), array_filter( explode( ',', $raw ) ) ) );
}

function oyc_card_set_badges( $user_id, $badges ) {
	$clean = array_values( array_intersect( array_keys( oyc_card_badge_list() ), array_map( 'sanitize_key', (array) $badges ) ) );
	update_user_meta( $user_id, 'oyc_card_badges', implode( ',', $clean ) );
	return $clean;
}

/**
 * Inline SVG for a badge (drawn for a 40×40 medallion, currentColor strokes).
 */
function oyc_card_badge_svg( $slug ) {
	$svgs = array(
		// Motor yacht: hull, cabin, windows, wake.
		'power' => '<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M6 24h27l-4 6H10z" fill="currentColor"/><path d="M12 24v-5h10l5 5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round"/><path d="M15 17v-3h5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M4 33c3 0 3-1.5 6-1.5s3 1.5 6 1.5 3-1.5 6-1.5 3 1.5 6 1.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
		// Sloop: mainsail, jib, hull, wave.
		'sail'  => '<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M20 6v19H9z" fill="currentColor"/><path d="M22 9l9 16h-9z" fill="currentColor" opacity=".75"/><path d="M7 27h26l-4 4H11z" fill="currentColor"/><path d="M4 35c3 0 3-1.5 6-1.5s3 1.5 6 1.5 3-1.5 6-1.5 3 1.5 6 1.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
		// Fish on a hook line.
		'fish'  => '<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M8 21c4-6 13-8 20-3l5-4v12l-5-4c-7 5-16 3-20-1z" fill="currentColor"/><circle cx="13" cy="20" r="1.4" fill="#0b2a4a"/><path d="M20 4v7a3 3 0 0 1-6 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
		// "42 & U" lettering.
		'u42'   => '<svg viewBox="0 0 40 40" aria-hidden="true"><text x="20" y="19" text-anchor="middle" font-family="Open Sans, Arial, sans-serif" font-weight="800" font-size="15" fill="currentColor">42</text><text x="20" y="31" text-anchor="middle" font-family="Open Sans, Arial, sans-serif" font-weight="700" font-size="9" fill="currentColor">&amp; U</text></svg>',
	);
	return $svgs[ $slug ] ?? '';
}

/**
 * Save badges from the card page (the member's own, or an officer for a member).
 */
add_action( 'admin_post_oyc_card_badges', function () {
	if ( ! is_user_logged_in() || ! check_admin_referer( 'oyc_card_badges', 'oyc_card_badges_nonce' ) ) {
		wp_die( esc_html__( 'Please log in again.', 'orienta-yacht-club' ), '', array( 'response' => 403 ) );
	}
	$uid  = (int) ( $_POST['user_id'] ?? 0 );
	$self = get_current_user_id();
	$args = array( 'badges' => 'saved' );
	if ( $uid && $uid !== $self ) {
		require_once get_template_directory() . '/inc/officer-admin.php';
		if ( ! current_user_can( 'oyc_manage_members' ) || ! oyc_officer_can_manage_user( $uid ) ) {
			wp_die( esc_html__( 'You cannot change that member\'s card.', 'orienta-yacht-club' ), '', array( 'response' => 403 ) );
		}
		$args['uid'] = $uid;
	} else {
		$uid = $self;
	}
	oyc_card_set_badges( $uid, wp_unslash( (array) ( $_POST['badges'] ?? array() ) ) );
	if ( isset( $_POST['cert_name'] ) ) {
		oyc_card_set_certs( $uid, wp_unslash( (array) $_POST['cert_name'] ), wp_unslash( (array) ( $_POST['cert_expires'] ?? array() ) ) );
	}
	wp_safe_redirect( oyc_card_url( $args ) );
	exit;
} );

/* ─────────────────────────────────────────────────────────────────────────────
 * Licenses & certifications (shown on the card back and the Verify page)
 * ───────────────────────────────────────────────────────────────────────────── */

/**
 * Suggestions offered while typing (free text is still allowed).
 */
function oyc_card_cert_suggestions() {
	return array(
		'NY Boater Safety Certificate',
		'USCG Captain — OUPV (6-Pack)',
		'USCG Master License',
		'US Sailing Keelboat Certification',
		'America\'s Boating Club course',
		'Marine VHF Radio Operator',
		'First Aid / CPR',
		'NY Fishing License',
	);
}

/**
 * The member's licenses/certifications: up to 6 entries of
 * array( 'name' => string, 'expires' => string year or '' ).
 */
function oyc_card_get_certs( $user_id ) {
	$raw = get_user_meta( $user_id, 'oyc_card_certs', true );
	return is_array( $raw ) ? array_slice( $raw, 0, 6 ) : array();
}

function oyc_card_set_certs( $user_id, $names, $expires ) {
	$out = array();
	foreach ( (array) $names as $i => $n ) {
		$n = substr( trim( sanitize_text_field( (string) $n ) ), 0, 60 );
		if ( '' === $n ) {
			continue;
		}
		$e = trim( sanitize_text_field( (string) ( $expires[ $i ] ?? '' ) ) );
		$e = preg_match( '/(19|20)\d{2}/', $e, $m ) ? $m[0] : '';
		$out[] = array( 'name' => $n, 'expires' => $e );
		if ( count( $out ) >= 6 ) {
			break;
		}
	}
	update_user_meta( $user_id, 'oyc_card_certs', $out );
	return $out;
}

/**
 * Whether a certification has passed its expiry year.
 */
function oyc_card_cert_expired( $cert ) {
	return ! empty( $cert['expires'] ) && (int) $cert['expires'] < (int) current_time( 'Y' );
}

/**
 * Small HTML list of certifications, shared by the card back and Verify page.
 */
function oyc_card_certs_html( $certs, $class = 'mcard__certs' ) {
	if ( ! $certs ) {
		return '';
	}
	$html = '<ul class="' . esc_attr( $class ) . '">';
	foreach ( $certs as $c ) {
		$exp   = oyc_card_cert_expired( $c );
		$html .= '<li' . ( $exp ? ' class="is-expired"' : '' ) . '>' . esc_html( $c['name'] );
		if ( $c['expires'] ) {
			/* translators: %s: year. */
			$html .= ' <span class="mcard__cert-exp">' . esc_html( sprintf( $exp ? __( 'expired %s', 'orienta-yacht-club' ) : __( 'exp. %s', 'orienta-yacht-club' ), $c['expires'] ) ) . '</span>';
		}
		$html .= '</li>';
	}
	return $html . '</ul>';
}
