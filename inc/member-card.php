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
	$name = oyc_proper_case( $name );

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
	add_filter( 'body_class', fn( $c ) => array_merge( array_diff( $c, array( 'error404' ) ), array( 'page', 'has-hero-header', 'oyc-page-' . $req, 'oyc-immersive', 'oyc-card-page' ) ) );
	return $file;
}, 20 );

// Same light "immersive" page style as the Members Area and Bulletin Board,
// also when a WP Page record serves the route.
add_filter( 'body_class', function ( $classes ) {
	if ( '' !== oyc_card_current_route() ) {
		$classes = array_unique( array_merge( $classes, array( 'has-hero-header', 'oyc-immersive', 'oyc-card-page' ) ) );
	}
	return $classes;
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

/* Roster CSV/Excel import: see inc/roster-import.php. */

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
		'u42'   => __( '42 & You', 'orienta-yacht-club' ),
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

/* ─────────────────────────────────────────────────────────────────────────────
 * Boat, locker and dinghy rack glyphs (front of the card, from roster data)
 * Shown only when the member's data has them; members can't toggle these.
 * ───────────────────────────────────────────────────────────────────────────── */

/**
 * The member's Fleet Roster directory entry (boat name/size/type live only
 * there), matched by email, then by first + last name.
 */
function oyc_card_roster_entry( WP_User $user ) {
	static $cache = array();
	if ( isset( $cache[ $user->ID ] ) ) {
		return $cache[ $user->ID ];
	}
	$found = array();
	if ( function_exists( 'oyc_get_roster' ) ) {
		$norm  = fn( $v ) => strtolower( preg_replace( '/[^a-z0-9]/i', '', (string) $v ) );
		$email = strtolower( trim( $user->user_email ) );
		$name  = $norm( $user->first_name . $user->last_name );
		$by_name = array();
		foreach ( oyc_get_roster() as $e ) {
			if ( ! is_array( $e ) ) {
				continue;
			}
			if ( '' !== $email && strtolower( trim( (string) ( $e['email'] ?? '' ) ) ) === $email ) {
				$found = $e;
				break;
			}
			if ( ! $by_name && '' !== $name && $norm( $e['name'] ?? '' ) === $name ) {
				$by_name = $e;
			}
		}
		if ( ! $found ) {
			$found = $by_name;
		}
	}
	$cache[ $user->ID ] = $found;
	return $found;
}

/**
 * Roster boat type => glyph slug. Sloop and any sail boat → sloop, Ketch (and
 * other two-masters) → ketch, Trawler → trawler, Power → express cruiser.
 * Returns '' for blank or unrecognised types (no glyph).
 */
function oyc_card_boat_glyph( $type ) {
	$t = strtolower( trim( (string) $type ) );
	if ( '' === $t ) {
		return '';
	}
	if ( preg_match( '/ketch|yawl|schooner/', $t ) ) {
		return 'ketch';
	}
	if ( false !== strpos( $t, 'trawler' ) ) {
		return 'trawler';
	}
	if ( preg_match( '/power|motor|cruiser|express|sport ?fish|center console|runabout|lobster/', $t ) ) {
		return 'cruiser';
	}
	if ( preg_match( '/sloop|sail|cutter|catamaran|trimaran|sunfish|laser|day ?sailer/', $t ) ) {
		return 'sloop';
	}
	return '';
}

/**
 * Locker / Dinghy Rack: hidden when blank or "No", shown for anything else.
 */
function oyc_card_has_value( $v ) {
	$v = strtolower( trim( (string) $v, " \t\n\r\0\x0B." ) );
	return '' !== $v && 'no' !== $v;
}

/**
 * Glyphs for the front of the card: list of [slug, label, title].
 */
function oyc_card_gear( WP_User $user ) {
	$entry = oyc_card_roster_entry( $user );
	$meta  = function ( $mk, $dk ) use ( $user, $entry ) {
		$v = trim( (string) get_user_meta( $user->ID, $mk, true ) );
		return '' !== $v ? $v : trim( (string) ( $entry[ $dk ] ?? '' ) );
	};
	$labels = array(
		'sloop'   => __( 'Sloop', 'orienta-yacht-club' ),
		'ketch'   => __( 'Ketch', 'orienta-yacht-club' ),
		'trawler' => __( 'Trawler', 'orienta-yacht-club' ),
		'cruiser' => __( 'Power Boat', 'orienta-yacht-club' ),
	);
	$out  = oyc_card_role_glyphs( $user );
	$boat = oyc_card_boat_glyph( $entry['boatType'] ?? '' );
	if ( $boat ) {
		$title = trim( implode( ' · ', array_filter( array( (string) ( $entry['boat'] ?? '' ), trim( ( $entry['boatLen'] ?? '' ) . ' ' . ( $entry['boatType'] ?? '' ) ) ), 'strlen' ) ) );
		$out[] = array( 'boat-' . $boat, $labels[ $boat ], $title );
	}
	$locker = $meta( 'oyc_locker', 'locker' );
	if ( oyc_card_has_value( $locker ) ) {
		$is_num = (bool) preg_match( '/\d/', $locker );
		/* translators: %s: locker number(s). */
		$label = $is_num ? sprintf( __( 'Locker %s', 'orienta-yacht-club' ), $locker ) : __( 'Locker', 'orienta-yacht-club' );
		$out[] = array( 'locker', $label, $is_num ? $label : __( 'Locker', 'orienta-yacht-club' ) . ': ' . $locker );
	}
	$dinghy = $meta( 'oyc_dinghy_rack', 'dinghy' );
	if ( oyc_card_has_value( $dinghy ) ) {
		$out[] = array( 'dinghy', __( 'Dinghy', 'orienta-yacht-club' ), __( 'Dinghy Rack', 'orienta-yacht-club' ) . ': ' . $dinghy );
	}
	return $out;
}

/**
 * Role glyphs (nothing for a plain Subscriber): Club Officer and Race
 * Committee → "Officer"; Administrator → "Admin"; any Trustee role → "Board Member".
 */
function oyc_card_role_glyphs( WP_User $user ) {
	$names   = function_exists( 'wp_roles' ) ? wp_roles()->get_names() : array();
	$officer = false;
	$admin   = false;
	$board   = false;
	foreach ( (array) $user->roles as $role ) {
		$label = strtolower( $role . ' ' . ( $names[ $role ] ?? '' ) );
		if ( false !== strpos( $label, 'trustee' ) ) {
			$board = true;
		} elseif ( 'administrator' === $role ) {
			$admin = true;
		} elseif ( in_array( $role, array( 'oyc_officer', 'oyc_race_committee' ), true ) ) {
			$officer = true;
		}
	}
	$out = array();
	if ( $officer ) {
		$out[] = array( 'officer', __( 'Officer', 'orienta-yacht-club' ), __( 'Club Officer', 'orienta-yacht-club' ) );
	}
	if ( $admin ) {
		$out[] = array( 'admin', __( 'Admin', 'orienta-yacht-club' ), __( 'Website administrator', 'orienta-yacht-club' ) );
	}
	if ( $board ) {
		$out[] = array( 'board', __( 'Board Member', 'orienta-yacht-club' ), __( 'Board of Trustees', 'orienta-yacht-club' ) );
	}
	return $out;
}

/**
 * Inline SVG for a boat / locker / dinghy glyph (40×40, currentColor; bow to the right).
 */
function oyc_card_gear_svg( $slug ) {
	$wave = '<path d="M4 35c3 0 3-1.5 6-1.5s3 1.5 6 1.5 3-1.5 6-1.5 3 1.5 6 1.5 3-1.5 6-1.5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>';
	$svgs = array(
		// Sloop: one mast, mainsail + jib.
		'boat-sloop'   => '<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M19 5v20h-9z" fill="currentColor"/><path d="M21 8l10 17H21z" fill="currentColor" opacity=".75"/><path d="M6 27h28l-4.5 4.5H10z" fill="currentColor"/>' . $wave . '</svg>',
		// Ketch: tall mainmast forward, shorter mizzen aft, jib.
		'boat-ketch'   => '<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M22 4v21h-8z" fill="currentColor"/><path d="M24 8l8 17h-8z" fill="currentColor" opacity=".75"/><path d="M12 12v13H6z" fill="currentColor"/><path d="M5 27h30l-4.5 4.5H9z" fill="currentColor"/>' . $wave . '</svg>',
		// Trawler: high bow, deckhouse with windows, flybridge, mast.
		'boat-trawler' => '<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M4 23h33l-3 8H8z" fill="currentColor"/><path d="M9 23v-8h20l3 8z" fill="currentColor"/><rect x="12" y="10" width="11" height="5" rx="1" fill="currentColor" opacity=".8"/><path d="M17.5 10V4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/><path d="M12 17.5h4v3h-4zM18 17.5h4v3h-4zM24 17.5h3.5l1.2 3H24z" fill="#0b2a4a"/>' . $wave . '</svg>',
		// Express cruiser: low sleek hull, raked windscreen, radar arch.
		'boat-cruiser' => '<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M3 24h28l6-1-4 7H8z" fill="currentColor"/><path d="M11 24l2-5h10l7 5z" fill="currentColor" opacity=".8"/><path d="M14 19l2.5-6h4l1 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M23.5 19.5l3 2.7" stroke="#0b2a4a" stroke-width="1.4"/>' . $wave . '</svg>',
		// Officer: anchor under a star.
		'officer'      => '<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M20 2.5l1.6 3.3 3.6.5-2.6 2.5.6 3.6-3.2-1.7-3.2 1.7.6-3.6-2.6-2.5 3.6-.5z" fill="currentColor"/><circle cx="20" cy="16.5" r="2.3" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M20 18.8V35M14.5 22h11" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><path d="M9 27c0 5 5 8 11 8s11-3 11-8" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><path d="M6.5 29.5L9 26.5l2.8 2.6M33.5 29.5L31 26.5l-2.8 2.6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
		// Admin: key.
		'admin'        => '<svg viewBox="0 0 40 40" aria-hidden="true"><circle cx="13" cy="15" r="7.5" fill="none" stroke="currentColor" stroke-width="2.6"/><circle cx="13" cy="15" r="2.4" fill="currentColor"/><path d="M18.5 20.5L33 35M27 29l3-3M30.5 32.5l3-3" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/></svg>',
		// Board member: gavel and block.
		'board'        => '<svg viewBox="0 0 40 40" aria-hidden="true"><g transform="rotate(-40 18 15)"><rect x="9" y="9" width="18" height="9" rx="2" fill="currentColor"/><rect x="7" y="8" width="3" height="11" rx="1" fill="currentColor"/><rect x="26" y="8" width="3" height="11" rx="1" fill="currentColor"/><rect x="16.5" y="17" width="3" height="17" rx="1.5" fill="currentColor"/></g><rect x="18" y="31" width="17" height="4" rx="1.5" fill="currentColor"/><rect x="20" y="28.5" width="13" height="3" rx="1" fill="currentColor" opacity=".75"/></svg>',
		// Locker: two-door cabinet with handles and vents.
		'locker'       => '<svg viewBox="0 0 40 40" aria-hidden="true"><rect x="9" y="5" width="22" height="30" rx="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M20 5v30" stroke="currentColor" stroke-width="1.6"/><path d="M12.5 10h5M12.5 13h5M22.5 10h5M22.5 13h5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M17.3 20v4M22.7 20v4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
		// Dinghy: small tender with a seat and crossed oars, on the rack bar.
		'dinghy'       => '<svg viewBox="0 0 40 40" aria-hidden="true"><path d="M8 13l24 13M32 13L8 26" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M5 21h30l-5 8H11z" fill="currentColor"/><path d="M5 21h30" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M17 24h6" stroke="#0b2a4a" stroke-width="1.6" stroke-linecap="round"/><path d="M5 33h30" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M9 33v3M31 33v3" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
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
