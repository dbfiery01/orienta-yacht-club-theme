<?php
/**
 * OYC Racing — race RSVP, racers-only hub, Crew Wanted / Boat Wanted board,
 * crew and yacht profiles, and emailing racers.
 *
 * Built on the same pattern as the Bulletin Board (inc/bulletin-board.php):
 * one page at /racing/ (served with or without a WP Page record), front-end
 * forms that post to admin-post.php, a nonce and a capability check on every
 * write, Post/Redirect/Get back to the page.
 *
 * WHO SEES WHAT
 *   Guests            the list of upcoming races (name, date, area) and a
 *                     "Sign up to race" form for visiting sailors.
 *   Visiting sailors  the sign-up creates a login with the `oyc_guest_racer`
 *                     role, pending until a race officer approves it. Guest
 *                     racers reach ONLY the racing hub, never the members' area.
 *   Members           RSVP straight away while logged in.
 *   Racers            anyone who has RSVP'd: access lasts through December 31
 *                     of the race's year (user meta `oyc_racer_until`).
 *   Race Committee    `oyc_manage_races`: officers and admins automatically,
 *                     plus anyone given the "Race Committee" role. Adds and
 *                     edits races, approves visiting sailors, emails racers,
 *                     downloads the entry list, sees emergency contacts.
 *
 * PRIVACY: the theme repo is public, so no racer data is ever stored in it.
 * Everything lives in the site database. Racers never see each other's email
 * addresses or phone numbers: messages go through the site by email (with the
 * sender as Reply-To). Phones and emergency contacts are shown only to the Race
 * Committee. Race documents are stored in a deny-all folder and streamed only
 * to racers.
 *
 * DATA
 *   oyc_race        post_title name, post_content details;
 *                   _oyc_race_date (Y-m-d), _oyc_race_time (H:i first warning),
 *                   _oyc_race_area, _oyc_race_deadline (Y-m-d), _oyc_race_status
 *                   ('' | 'postponed' | 'cancelled'), _oyc_race_docs (array of
 *                   {file,name,label,mime}), _oyc_race_cal (calendar event ID).
 *   oyc_race_entry  one per sailor per race; post_parent = race, author = sailor,
 *                   status 'publish' or 'pending' (visiting sailor awaiting
 *                   approval). Meta _role (skipper|crew), _boat, _sail, _class,
 *                   _rating, _crew, _need ('1' = skipper needs crew / crew needs
 *                   a boat), _notes, _yacht (oyc_yacht ID when the boat
 *                   name matches one of the skipper's yachts, else 0).
 *   oyc_yacht       author = owner/skipper; _class, _sail, _rating, _loa,
 *                   _club, _oyc_bb_photo, _hidden; post_content about.
 *   oyc_crew_ad     author; _type ('crew' = boat needs crew, 'boat' = sailor
 *                   looking for a boat), _race (0 = any race), _positions,
 *                   _closed; post_content details.
 *   user meta       oyc_racer_until, oyc_phone, oyc_racer_club,
 *                   oyc_crew_positions (array), oyc_crew_level, oyc_crew_about,
 *                   oyc_crew_hidden, oyc_emergency_name/phone/relationship
 *                   (same keys as Edit Profile).
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OYC_RACE_SLUG', 'racing' );
define( 'OYC_RACE_ROLES_VERSION', '1.0.0' );
define( 'OYC_RACE_DOC_MAX', 20 * 1024 * 1024 );
define( 'OYC_RACE_MSG_PER_DAY', 25 );

/* ─────────────────────────────────────────────────────────────────────────────
 * Roles
 * ───────────────────────────────────────────────────────────────────────────── */

add_action( 'init', function () {
	if ( get_option( 'oyc_race_roles_version' ) === OYC_RACE_ROLES_VERSION ) {
		return;
	}
	remove_role( 'oyc_race_committee' );
	remove_role( 'oyc_guest_racer' );
	add_role( 'oyc_race_committee', __( 'Race Committee', 'orienta-yacht-club' ), array( 'read' => true, 'oyc_manage_races' => true ) );
	add_role( 'oyc_guest_racer', __( 'Visiting Racer', 'orienta-yacht-club' ), array( 'read' => true ) );
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		$admin->add_cap( 'oyc_manage_races' );
	}
	update_option( 'oyc_race_roles_version', OYC_RACE_ROLES_VERSION );
}, 6 );

/* ─────────────────────────────────────────────────────────────────────────────
 * Post types (all private: no URLs, search, feeds or REST)
 * ───────────────────────────────────────────────────────────────────────────── */

add_action( 'init', function () {
	$private = array(
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_in_rest'        => false,
		'has_archive'         => false,
		'rewrite'             => false,
		'query_var'           => false,
		'show_ui'             => true,
		'show_in_menu'        => 'oyc-applications',
		'supports'            => array( 'title', 'editor', 'author' ),
		'capability_type'     => 'post',
		'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
		'map_meta_cap'        => true,
	);
	register_post_type( 'oyc_race', array_merge( $private, array( 'label' => __( 'Races', 'orienta-yacht-club' ) ) ) );
	register_post_type( 'oyc_race_entry', array_merge( $private, array( 'label' => __( 'Race Entries', 'orienta-yacht-club' ), 'show_in_menu' => false ) ) );
	register_post_type( 'oyc_yacht', array_merge( $private, array( 'label' => __( 'Yachts', 'orienta-yacht-club' ), 'show_in_menu' => false ) ) );
	register_post_type( 'oyc_crew_ad', array_merge( $private, array( 'label' => __( 'Crew Board', 'orienta-yacht-club' ), 'show_in_menu' => false ) ) );
} );

/* ─────────────────────────────────────────────────────────────────────────────
 * Routing: /racing/ with or without a Page record (same as the Bulletin Board)
 * ───────────────────────────────────────────────────────────────────────────── */

function oyc_race_url( $args = array(), $hash = '' ) {
	$page = get_page_by_path( OYC_RACE_SLUG );
	$url  = $page ? get_permalink( $page ) : home_url( '/' . OYC_RACE_SLUG . '/' );
	$url  = $args ? add_query_arg( $args, $url ) : $url;
	return $hash ? $url . '#' . $hash : $url;
}

/**
 * The existing Racing Page's own editor content (regattas, links, results),
 * split into one card per <h3> section so it sits in the hub's look. The
 * leading "Racing" heading (the hero already says it), empty paragraphs and
 * sections repeated word for word are dropped. Once the Race Committee
 * edits the sections on the hub, oyc_race_link_sections() uses its own copy.
 *
 * @return array{intro:string,sections:array<int,array{title:string,body:string}>}
 */
function oyc_race_page_sections() {
	$page = get_page_by_path( OYC_RACE_SLUG );
	$out  = array( 'intro' => '', 'sections' => array() );
	if ( ! $page || '' === trim( (string) $page->post_content ) ) {
		return $out;
	}
	$html = apply_filters( 'the_content', $page->post_content );
	$html = preg_replace( '#^\s*<h2[^>]*>\s*Racing\s*</h2>#i', '', trim( $html ) );
	$html = preg_replace( '#<p>(\s|&nbsp;|<a\b[^>]*>\s*</a>|<br\s*/?>)*</p>#i', '', $html );
	$parts = preg_split( '#(?=<h3\b)#i', $html );
	$seen  = array();
	foreach ( $parts as $i => $chunk ) {
		$chunk = trim( $chunk );
		if ( '' === $chunk ) {
			continue;
		}
		if ( ! preg_match( '#^<h3\b[^>]*>(.*?)</h3>(.*)$#is', $chunk, $m ) ) {
			$out['intro'] .= $chunk;
			continue;
		}
		$title = trim( html_entity_decode( wp_strip_all_tags( $m[1] ), ENT_QUOTES, 'UTF-8' ) );
		$key   = md5( strtolower( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $chunk ) ) ) );
		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ]      = true;
		$out['sections'][] = array( 'title' => $title, 'body' => trim( $m[2] ) );
	}
	return $out;
}

/**
 * The "Regattas, results & links" cards. Until the Race Committee first edits
 * them they come from the Racing Page's content; the first add, edit, move or
 * delete copies them into the oyc_race_links option, which is the source from
 * then on (so the committee needs no wp-admin access).
 *
 * @return array[] Each: id, title, body (HTML).
 */
function oyc_race_link_sections() {
	$saved = get_option( 'oyc_race_links', null );
	if ( is_array( $saved ) ) {
		return array_values( array_filter( $saved, 'is_array' ) );
	}
	$out = array();
	foreach ( oyc_race_page_sections()['sections'] as $i => $sec ) {
		$out[] = array( 'id' => 'p' . $i, 'title' => $sec['title'], 'body' => $sec['body'] );
	}
	return $out;
}

function oyc_race_handle_links() {
	oyc_race_require_manage( 'oyc_race_links' );
	$list = oyc_race_link_sections();
	$id   = sanitize_key( wp_unslash( $_POST['link_id'] ?? '' ) );
	$do   = sanitize_key( wp_unslash( $_POST['do'] ?? 'save' ) );
	$at   = null;
	foreach ( $list as $i => $sec ) {
		if ( $id && $sec['id'] === $id ) {
			$at = $i;
			break;
		}
	}
	if ( ( $id || 'save' !== $do ) && null === $at ) {
		oyc_race_redirect( 'linkmissing', array(), 'links' );
	}
	if ( 'delete' === $do ) {
		array_splice( $list, $at, 1 );
		$notice = 'linkdeleted';
	} elseif ( 'up' === $do || 'down' === $do ) {
		$to = 'up' === $do ? $at - 1 : $at + 1;
		if ( $to >= 0 && $to < count( $list ) ) {
			$tmp         = $list[ $to ];
			$list[ $to ] = $list[ $at ];
			$list[ $at ] = $tmp;
		}
		$notice = 'linkmoved';
	} else {
		$title = oyc_race_text( 'oyc_title', 100 );
		$body  = trim( wp_kses_post( wp_unslash( $_POST['oyc_body'] ?? '' ) ) );
		if ( '' === $title ) {
			oyc_race_redirect( 'linkmissing', $id ? array( 'linkedit' => $id ) : array( 'linkedit' => 'new' ), 'links-form' );
		}
		if ( strlen( $body ) > 30000 ) {
			$body = substr( $body, 0, 30000 );
		}
		// Plain typed text (no tags): keep line breaks and turn web addresses into links.
		if ( $body === wp_strip_all_tags( $body ) ) {
			$body = make_clickable( wpautop( esc_html( $body ) ) );
		}
		$sec = array( 'id' => $id ? $id : 'l' . strtolower( wp_generate_password( 8, false, false ) ), 'title' => $title, 'body' => $body );
		if ( null === $at ) {
			$list[] = $sec;
		} else {
			$list[ $at ] = $sec;
		}
		$notice = 'linksaved';
	}
	update_option( 'oyc_race_links', array_values( $list ), false );
	oyc_race_redirect( $notice, array(), 'links' );
}
add_action( 'admin_post_oyc_race_links', 'oyc_race_handle_links' );

function oyc_race_is_hub() {
	global $oyc_race_fallback;
	return ! empty( $oyc_race_fallback ) || is_page( OYC_RACE_SLUG );
}

function oyc_race_request_is_hub() {
	return is_page( OYC_RACE_SLUG ) || ( isset( $GLOBALS['wp']->request ) && OYC_RACE_SLUG === untrailingslashit( $GLOBALS['wp']->request ) );
}

add_filter( 'template_include', function ( $template ) {
	global $wp, $wp_query, $oyc_race_fallback;
	if ( ! is_404() || ! isset( $wp->request ) || OYC_RACE_SLUG !== untrailingslashit( $wp->request ) ) {
		return $template;
	}
	$hub = locate_template( 'page-racing.php' );
	if ( ! $hub ) {
		return $template;
	}
	$oyc_race_fallback = true;
	$wp_query->is_404  = false;
	status_header( 200 );
	add_filter( 'pre_get_document_title', fn() => __( 'Racing', 'orienta-yacht-club' ) . ' — ' . get_bloginfo( 'name' ) );
	add_filter( 'body_class', fn( $c ) => array_merge( array_diff( $c, array( 'error404' ) ), array( 'page', 'has-hero-header', 'oyc-page-' . OYC_RACE_SLUG, 'oyc-immersive' ) ) );
	return $hub;
}, 20 );

add_action( 'template_redirect', function () {
	if ( oyc_race_request_is_hub() ) {
		nocache_headers();
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}
	}
}, 1 );

// The public race list stays indexable (it replaces the old public Racing
// page); racer views, forms results and race pages are not indexed.
add_filter( 'wp_robots', function ( $robots ) {
	if ( oyc_race_is_hub() && ( ! empty( $_GET['view'] ) || ! empty( $_GET['rs'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		unset( $robots['index'], $robots['follow'] );
	}
	return $robots;
} );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! oyc_race_is_hub() ) {
		return;
	}
	$rel = '/assets/racing.css';
	$f   = get_template_directory() . $rel;
	wp_enqueue_style( 'oyc-racing', get_template_directory_uri() . $rel, array( 'oyc-style' ), file_exists( $f ) ? (string) filemtime( $f ) : OYC_VERSION );
}, 100 );

/* ─────────────────────────────────────────────────────────────────────────────
 * Access
 * ───────────────────────────────────────────────────────────────────────────── */

function oyc_race_can_manage( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	return $user_id && ( user_can( $user_id, 'oyc_manage_races' ) || user_can( $user_id, 'manage_options' ) );
}

/**
 * A visiting sailor's login: the Visiting Racer role and nothing that grants
 * more (an officer who also has the role is not treated as a guest).
 */
function oyc_is_guest_racer( $user_id = 0 ) {
	$user = $user_id ? get_userdata( (int) $user_id ) : wp_get_current_user();
	if ( ! $user || ! $user->exists() ) {
		return false;
	}
	return in_array( 'oyc_guest_racer', (array) $user->roles, true )
		&& ! user_can( $user, 'oyc_access_officer_area' )
		&& ! user_can( $user, 'oyc_manage_races' )
		&& ! user_can( $user, 'manage_options' );
}

function oyc_race_today() {
	return current_time( 'Y-m-d' );
}

/**
 * Racer access: Race Committee always; otherwise an approved login whose
 * season access (oyc_racer_until) has not run out.
 */
function oyc_is_racer( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return false;
	}
	if ( oyc_race_can_manage( $user_id ) ) {
		return true;
	}
	$until = (string) get_user_meta( $user_id, 'oyc_racer_until', true );
	if ( '' === $until || $until < oyc_race_today() ) {
		return false;
	}
	if ( oyc_is_guest_racer( $user_id ) ) {
		return '1' === (string) get_user_meta( $user_id, 'active', true );
	}
	return function_exists( 'oyc_user_approval_state' )
		? in_array( oyc_user_approval_state( $user_id ), array( 'approved', 'legacy' ), true )
		: true;
}

/**
 * Give racer access through December 31 of the race's year (never shortens it).
 */
function oyc_race_grant( $user_id, $race_id ) {
	$date  = (string) get_post_meta( $race_id, '_oyc_race_date', true );
	$year  = $date ? substr( $date, 0, 4 ) : current_time( 'Y' );
	$until = $year . '-12-31';
	$now   = (string) get_user_meta( $user_id, 'oyc_racer_until', true );
	if ( $until > $now ) {
		update_user_meta( $user_id, 'oyc_racer_until', $until );
	}
}

/* ── Keep visiting racers out of the members' area ────────────────────────── */

/**
 * Templates and page slugs that are for club members only. A visiting racer
 * who reaches one is sent to the racing hub instead.
 */
function oyc_race_member_templates() {
	return array(
		'page-bulletin-board.php', 'page-dashboard.php', 'page-member-card.php', 'page-edit-profile.php', 'page-fleet-roster.php',
		'page-members-only.php', 'page-oyc-resources.php', 'page-photo-gallery.php', 'page-video-gallery.php',
		'page-video-streaming.php', 'page-officers.php', 'page-officer-applications.php', 'page-officer-events.php',
		'page-officer-members.php', 'page-officer-messages.php',
	);
}

add_filter( 'template_include', function ( $template ) {
	if ( oyc_is_guest_racer() && in_array( basename( (string) $template ), oyc_race_member_templates(), true ) ) {
		wp_safe_redirect( oyc_race_url( array( 'rs' => 'members' ) ) );
		exit;
	}
	return $template;
}, 999 );

add_action( 'template_redirect', function () {
	if ( ! oyc_is_guest_racer() ) {
		return;
	}
	$path = trim( (string) strtok( $_SERVER['REQUEST_URI'] ?? '', '?' ), '/' );
	$bad  = 0 === strpos( $path, 'member-doc/' );
	if ( ! $bad && is_singular( 'page' ) ) {
		$id  = get_queried_object_id();
		$bad = (bool) get_post_meta( $id, '_oyc_members_only', true )
			|| in_array( get_post_field( 'post_name', $id ), array( 'members-area', '2026-fee-schedule', 'slip-waiting-list', 'dock-assignments', 'constitution-and-bylaws-2026', 'club-rental-agreement', 'member-guidelines-2026', 'member-guidelines', 'member-rental-agreement', 'reciprocity-list' ), true );
	}
	if ( $bad ) {
		wp_safe_redirect( oyc_race_url( array( 'rs' => 'members' ) ) );
		exit;
	}
}, 0 );

// Visiting racers may only use the racing forms (admin_init runs before every
// admin-post.php action).
add_action( 'admin_init', function () {
	if ( 'admin-post.php' !== ( $GLOBALS['pagenow'] ?? '' ) || ! oyc_is_guest_racer() ) {
		return;
	}
	$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
	if ( 0 !== strpos( $action, 'oyc_race_' ) ) {
		wp_safe_redirect( oyc_race_url( array( 'rs' => 'members' ) ) );
		exit;
	}
}, 0 );

// A visiting racer can't log in until a race officer approves them.
add_filter( 'authenticate', function ( $user ) {
	if ( $user instanceof WP_User && oyc_is_guest_racer( $user->ID ) && '1' !== (string) get_user_meta( $user->ID, 'active', true ) ) {
		return new WP_Error( 'oyc_racer_pending', __( 'Your racer login is waiting for a race officer to approve it. You will get an email when it is ready.', 'orienta-yacht-club' ) );
	}
	return $user;
}, 99 );

// Visiting racers land on the racing hub after login.
add_filter( 'login_redirect', function ( $redirect_to, $requested, $user ) {
	if ( $user instanceof WP_User && oyc_is_guest_racer( $user->ID ) ) {
		$safe = $requested ? wp_validate_redirect( $requested, '' ) : '';
		return ( $safe && false !== strpos( $safe, '/' . OYC_RACE_SLUG ) ) ? $safe : oyc_race_url();
	}
	return $redirect_to;
}, 20, 3 );

/* ─────────────────────────────────────────────────────────────────────────────
 * Small helpers
 * ───────────────────────────────────────────────────────────────────────────── */

function oyc_race_clip( $s, $max ) {
	$s = (string) $s;
	return function_exists( 'mb_substr' ) ? mb_substr( $s, 0, $max ) : substr( $s, 0, $max );
}

function oyc_race_text( $key, $max = 120 ) {
	return oyc_race_clip( sanitize_text_field( wp_unslash( $_POST[ $key ] ?? '' ) ), $max ); // phpcs:ignore WordPress.Security.NonceVerification -- callers verify.
}

function oyc_race_area_text( $key, $max = 2000 ) {
	return oyc_race_clip( sanitize_textarea_field( wp_unslash( $_POST[ $key ] ?? '' ) ), $max ); // phpcs:ignore WordPress.Security.NonceVerification -- callers verify.
}

function oyc_race_date_ok( $d ) {
	return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $d );
}

/** Post/Redirect/Get back to the hub. */
function oyc_race_redirect( $notice, $args = array(), $hash = '' ) {
	wp_safe_redirect( oyc_race_url( array_merge( array( 'rs' => $notice, 't' => time() ), $args ), $hash ) );
	exit;
}

function oyc_race_name( $user_id ) {
	$u = get_userdata( (int) $user_id );
	if ( ! $u ) {
		return __( 'Former racer', 'orienta-yacht-club' );
	}
	$n = trim( $u->first_name . ' ' . $u->last_name );
	return '' !== $n ? $n : $u->display_name;
}

function oyc_race_positions() {
	return array(
		'helm'     => __( 'Helm', 'orienta-yacht-club' ),
		'tactics'  => __( 'Tactics', 'orienta-yacht-club' ),
		'main'     => __( 'Main trim', 'orienta-yacht-club' ),
		'trim'     => __( 'Jib / spinnaker trim', 'orienta-yacht-club' ),
		'pit'      => __( 'Pit', 'orienta-yacht-club' ),
		'mast'     => __( 'Mast', 'orienta-yacht-club' ),
		'bow'      => __( 'Bow', 'orienta-yacht-club' ),
		'rail'     => __( 'Rail / anywhere', 'orienta-yacht-club' ),
		'nav'      => __( 'Navigator', 'orienta-yacht-club' ),
	);
}

function oyc_race_levels() {
	return array(
		'new'     => __( 'New to racing', 'orienta-yacht-club' ),
		'some'    => __( 'Some racing', 'orienta-yacht-club' ),
		'regular' => __( 'Regular racer', 'orienta-yacht-club' ),
		'expert'  => __( 'Very experienced', 'orienta-yacht-club' ),
	);
}

function oyc_race_positions_label( $keys ) {
	$all = oyc_race_positions();
	$out = array();
	foreach ( (array) $keys as $k ) {
		if ( isset( $all[ $k ] ) ) {
			$out[] = $all[ $k ];
		}
	}
	return implode( ', ', $out );
}

function oyc_race_posted_positions( $key ) {
	$raw = isset( $_POST[ $key ] ) ? (array) wp_unslash( $_POST[ $key ] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification -- callers verify.
	return array_values( array_intersect( array_map( 'sanitize_key', $raw ), array_keys( oyc_race_positions() ) ) );
}

/** Format "2026-11-14" as "Sat, Nov 14, 2026". */
function oyc_race_fmt_date( $d ) {
	if ( ! oyc_race_date_ok( $d ) ) {
		return '';
	}
	return wp_date( 'D, M j, Y', strtotime( $d . ' 12:00:00' ) );
}

function oyc_race_fmt_time( $t ) {
	if ( ! preg_match( '/^\d{2}:\d{2}$/', (string) $t ) ) {
		return '';
	}
	return wp_date( 'g:i a', strtotime( '2000-01-01 ' . $t . ':00' ), new DateTimeZone( 'UTC' ) );
}

/** Where emails come from: Race Committee name, the site's normal From address. */
function oyc_race_mail_headers( $reply_to = '' ) {
	$h = array( 'Content-Type: text/plain; charset=UTF-8' );
	if ( $reply_to && is_email( $reply_to ) ) {
		$h[] = 'Reply-To: ' . $reply_to;
	}
	return $h;
}

/** Everyone who should hear about a new visiting sailor: Race Committee role + site admin. */
function oyc_race_committee_emails() {
	$emails = array( get_option( 'admin_email' ) );
	foreach ( get_users( array( 'role' => 'oyc_race_committee', 'fields' => array( 'user_email' ) ) ) as $u ) {
		$emails[] = $u->user_email;
	}
	return array_values( array_unique( array_filter( $emails, 'is_email' ) ) );
}

/* ─────────────────────────────────────────────────────────────────────────────
 * Queries
 * ───────────────────────────────────────────────────────────────────────────── */

/**
 * Races, soonest first. $when: 'upcoming' (today on), 'past', or 'all'.
 */
function oyc_race_races( $when = 'upcoming', $limit = -1 ) {
	$args = array(
		'post_type'      => 'oyc_race',
		'post_status'    => 'publish',
		'posts_per_page' => $limit,
		'no_found_rows'  => true,
		'meta_key'       => '_oyc_race_date', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'orderby'        => 'meta_value',
		'order'          => 'past' === $when ? 'DESC' : 'ASC',
	);
	if ( 'all' !== $when ) {
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$args['meta_query'] = array( array( 'key' => '_oyc_race_date', 'value' => oyc_race_today(), 'compare' => 'past' === $when ? '<' : '>=' ) );
	}
	return get_posts( $args );
}

function oyc_race_get( $id ) {
	$p = get_post( (int) $id );
	return ( $p && 'oyc_race' === $p->post_type && 'publish' === $p->post_status ) ? $p : null;
}

/** Entries for a race. $status 'publish', 'pending' or 'any'. */
function oyc_race_entries( $race_id, $status = 'publish' ) {
	return get_posts( array(
		'post_type'      => 'oyc_race_entry',
		'post_status'    => 'any' === $status ? array( 'publish', 'pending' ) : $status,
		'post_parent'    => (int) $race_id,
		'posts_per_page' => -1,
		'orderby'        => 'date',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	) );
}

function oyc_race_user_entry( $race_id, $user_id ) {
	$e = get_posts( array(
		'post_type'      => 'oyc_race_entry',
		'post_status'    => array( 'publish', 'pending' ),
		'post_parent'    => (int) $race_id,
		'author'         => (int) $user_id,
		'posts_per_page' => 1,
		'no_found_rows'  => true,
	) );
	return $e ? $e[0] : null;
}

function oyc_race_user_entries( $user_id ) {
	return get_posts( array(
		'post_type'      => 'oyc_race_entry',
		'post_status'    => array( 'publish', 'pending' ),
		'author'         => (int) $user_id,
		'posts_per_page' => -1,
		'no_found_rows'  => true,
	) );
}

function oyc_race_entry_meta( $entry ) {
	$m = array();
	foreach ( array( 'role', 'boat', 'sail', 'class', 'rating', 'crew', 'need', 'notes' ) as $k ) {
		$m[ $k ] = (string) get_post_meta( $entry->ID, '_' . $k, true );
	}
	$m['role'] = 'crew' === $m['role'] ? 'crew' : 'skipper';
	return $m;
}

/** Visiting sailors waiting for approval. */
function oyc_race_pending_racers() {
	return get_users( array(
		'role'       => 'oyc_guest_racer',
		'meta_key'   => 'oyc_racer_pending', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		'orderby'    => 'registered',
	) );
}

/** Everyone with racer access this season (excludes committee-only accounts with no RSVP). */
function oyc_race_season_racers() {
	$users = get_users( array(
		'meta_key'     => 'oyc_racer_until', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value'   => oyc_race_today(), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		'meta_compare' => '>=',
		'orderby'      => 'display_name',
	) );
	return array_values( array_filter( $users, function ( $u ) {
		return oyc_is_racer( $u->ID );
	} ) );
}

function oyc_race_yachts( $user_id = 0 ) {
	$args = array(
		'post_type'      => 'oyc_yacht',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'orderby'        => 'title',
		'order'          => 'ASC',
		'no_found_rows'  => true,
	);
	if ( $user_id ) {
		$args['author'] = (int) $user_id;
	}
	return get_posts( $args );
}

function oyc_race_ads( $race_id = null ) {
	$posts = get_posts( array(
		'post_type'      => 'oyc_crew_ad',
		'post_status'    => 'publish',
		'posts_per_page' => 200,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	) );
	if ( null !== $race_id ) {
		$posts = array_values( array_filter( $posts, function ( $p ) use ( $race_id ) {
			$r = (int) get_post_meta( $p->ID, '_race', true );
			return 0 === $r || (int) $race_id === $r;
		} ) );
	}
	usort( $posts, function ( $a, $b ) {
		return (int) get_post_meta( $a->ID, '_closed', true ) - (int) get_post_meta( $b->ID, '_closed', true );
	} );
	return $posts;
}

/** Crew profiles racers can browse: racers with at least one position set and not hidden. */
function oyc_race_crew_profiles() {
	$users = get_users( array(
		'meta_key'     => 'oyc_crew_positions', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_compare' => 'EXISTS',
		'orderby'      => 'display_name',
	) );
	return array_values( array_filter( $users, function ( $u ) {
		return ! get_user_meta( $u->ID, 'oyc_crew_hidden', true ) && get_user_meta( $u->ID, 'oyc_crew_positions', true ) && oyc_is_racer( $u->ID );
	} ) );
}

/* ─────────────────────────────────────────────────────────────────────────────
 * Race documents: kept in a deny-all folder, streamed only to racers
 * ───────────────────────────────────────────────────────────────────────────── */

function oyc_race_doc_dir() {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'oyc-race-docs';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( $dir . '/.htaccess' ) ) {
		@file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore
	}
	if ( ! file_exists( $dir . '/index.php' ) ) {
		@file_put_contents( $dir . '/index.php', "<?php\n// Silence.\n" ); // phpcs:ignore
	}
	return $dir;
}

function oyc_race_doc_types() {
	return array(
		'pdf'  => 'application/pdf',
		'jpg'  => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'png'  => 'image/png',
		'gif'  => 'image/gif',
		'webp' => 'image/webp',
	);
}

/**
 * Store the uploaded race documents (input name oyc_race_doc[], label
 * oyc_race_doc_label[]). Returns [docs added, number rejected].
 */
function oyc_race_store_docs() {
	$added = array();
	$bad   = 0;
	if ( empty( $_FILES['oyc_race_doc'] ) || ! is_array( $_FILES['oyc_race_doc']['name'] ) ) {
		return array( $added, $bad );
	}
	$files  = $_FILES['oyc_race_doc']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$labels = isset( $_POST['oyc_race_doc_label'] ) ? (array) wp_unslash( $_POST['oyc_race_doc_label'] ) : array(); // phpcs:ignore WordPress.Security.NonceVerification -- caller verifies.
	$types  = oyc_race_doc_types();
	foreach ( $files['name'] as $i => $name ) {
		if ( '' === (string) $name ) {
			continue;
		}
		$tmp  = $files['tmp_name'][ $i ];
		$size = (int) $files['size'][ $i ];
		if ( ! empty( $files['error'][ $i ] ) || $size <= 0 || $size > OYC_RACE_DOC_MAX || ! is_uploaded_file( $tmp ) ) {
			$bad++;
			continue;
		}
		$check = wp_check_filetype_and_ext( $tmp, $name, $types );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
			$bad++;
			continue;
		}
		if ( 0 === strpos( $check['type'], 'image/' ) && false === @getimagesize( $tmp ) ) {
			$bad++;
			continue;
		}
		$stored = wp_generate_password( 24, false ) . '.' . $check['ext'];
		if ( ! @move_uploaded_file( $tmp, oyc_race_doc_dir() . '/' . $stored ) ) { // phpcs:ignore
			$bad++;
			continue;
		}
		$label   = oyc_race_clip( sanitize_text_field( $labels[ $i ] ?? '' ), 80 );
		$added[] = array(
			'file'  => $stored,
			'name'  => sanitize_file_name( $name ),
			'label' => '' !== $label ? $label : sanitize_file_name( pathinfo( $name, PATHINFO_FILENAME ) ),
			'mime'  => $check['type'],
		);
	}
	return array( $added, $bad );
}

function oyc_race_delete_doc_file( $doc ) {
	$f = oyc_race_doc_dir() . '/' . basename( (string) ( $doc['file'] ?? '' ) );
	if ( $doc && is_file( $f ) ) {
		wp_delete_file( $f );
	}
}

function oyc_race_doc_url( $race_id, $i ) {
	return add_query_arg( array( 'action' => 'oyc_race_doc', 'race' => (int) $race_id, 'doc' => (int) $i ), admin_url( 'admin-post.php' ) );
}

function oyc_race_handle_doc() {
	if ( ! is_user_logged_in() ) {
		auth_redirect();
	}
	if ( ! oyc_is_racer() ) {
		oyc_race_redirect( 'notracer' );
	}
	$race = oyc_race_get( (int) ( $_GET['race'] ?? 0 ) );
	$docs = $race ? (array) get_post_meta( $race->ID, '_oyc_race_docs', true ) : array();
	$i    = (int) ( $_GET['doc'] ?? -1 );
	$doc  = $docs[ $i ] ?? null;
	$file = $doc ? oyc_race_doc_dir() . '/' . basename( (string) $doc['file'] ) : '';
	if ( ! $doc || ! is_file( $file ) ) {
		status_header( 404 );
		exit;
	}
	nocache_headers();
	header( 'Content-Type: ' . $doc['mime'] );
	header( 'Content-Disposition: inline; filename="' . str_replace( '"', '', $doc['name'] ) . '"' );
	header( 'Content-Length: ' . filesize( $file ) );
	header( 'X-Robots-Tag: noindex, nofollow' );
	readfile( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}
add_action( 'admin_post_oyc_race_doc', 'oyc_race_handle_doc' );
add_action( 'admin_post_nopriv_oyc_race_doc', 'oyc_race_handle_doc' );

/* ─────────────────────────────────────────────────────────────────────────────
 * RSVP (logged in) and visiting-sailor sign-up (guests)
 * ───────────────────────────────────────────────────────────────────────────── */

/** RSVPs close at the end of the entry deadline day (or race day if none). */
function oyc_race_rsvp_open( $race ) {
	$status = (string) get_post_meta( $race->ID, '_oyc_race_status', true );
	if ( 'cancelled' === $status ) {
		return false;
	}
	$deadline = (string) get_post_meta( $race->ID, '_oyc_race_deadline', true );
	$date     = (string) get_post_meta( $race->ID, '_oyc_race_date', true );
	$last     = oyc_race_date_ok( $deadline ) ? $deadline : $date;
	return ! $last || oyc_race_today() <= $last;
}

/** Read the RSVP fields shared by both forms. */
function oyc_race_posted_entry() {
	$role = ( isset( $_POST['oyc_role'] ) && 'crew' === $_POST['oyc_role'] ) ? 'crew' : 'skipper'; // phpcs:ignore WordPress.Security.NonceVerification -- callers verify.
	return array(
		'role'   => $role,
		'boat'   => 'skipper' === $role ? oyc_race_text( 'oyc_boat', 60 ) : '',
		'sail'   => 'skipper' === $role ? oyc_race_text( 'oyc_sail', 20 ) : '',
		'class'  => 'skipper' === $role ? oyc_race_text( 'oyc_class', 40 ) : '',
		'rating' => 'skipper' === $role ? oyc_race_text( 'oyc_rating', 12 ) : '',
		'crew'   => 'skipper' === $role ? (string) min( 30, max( 0, (int) ( $_POST['oyc_crew'] ?? 0 ) ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification
		'need'   => ! empty( $_POST['oyc_need'] ) ? '1' : '', // phpcs:ignore WordPress.Security.NonceVerification
		'notes'  => oyc_race_area_text( 'oyc_notes', 500 ),
	);
}

function oyc_race_save_entry( $race_id, $user_id, $m, $status ) {
	$existing = oyc_race_user_entry( $race_id, $user_id );
	$data     = array(
		'post_type'   => 'oyc_race_entry',
		'post_status' => $status,
		'post_parent' => (int) $race_id,
		'post_author' => (int) $user_id,
		'post_title'  => oyc_race_name( $user_id ) . ' — ' . get_the_title( $race_id ),
	);
	if ( $existing ) {
		$data['ID'] = $existing->ID;
		if ( 'pending' === $existing->post_status && 'publish' !== $status ) {
			$data['post_status'] = 'pending';
		}
		$id = wp_update_post( $data, true );
	} else {
		$id = wp_insert_post( $data, true );
	}
	if ( is_wp_error( $id ) || ! $id ) {
		return 0;
	}
	foreach ( $m as $k => $v ) {
		update_post_meta( $id, '_' . $k, $v );
	}
	// Link a skipper's entry to their yacht profile when the boat name matches.
	$yacht = 0;
	if ( 'skipper' === $m['role'] ) {
		foreach ( oyc_race_yachts( $user_id ) as $y ) {
			if ( 0 === strcasecmp( trim( $y->post_title ), trim( $m['boat'] ) ) ) {
				$yacht = $y->ID;
				break;
			}
		}
	}
	update_post_meta( $id, '_yacht', $yacht );
	return (int) $id;
}

/** Phone and emergency contact, saved to the same keys Edit Profile uses. */
function oyc_race_save_contact( $user_id ) {
	foreach ( array( 'oyc_phone' => 30, 'oyc_emergency_name' => 80, 'oyc_emergency_phone' => 30, 'oyc_emergency_relationship' => 40 ) as $k => $max ) {
		if ( isset( $_POST[ $k ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- callers verify.
			update_user_meta( $user_id, $k, oyc_race_text( $k, $max ) );
		}
	}
	if ( isset( $_POST['oyc_racer_club'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		update_user_meta( $user_id, 'oyc_racer_club', oyc_race_text( 'oyc_racer_club', 80 ) );
	}
}

function oyc_race_confirm_email( $user_id, $race ) {
	$u = get_userdata( $user_id );
	if ( ! $u || ! is_email( $u->user_email ) ) {
		return;
	}
	$date = oyc_race_fmt_date( get_post_meta( $race->ID, '_oyc_race_date', true ) );
	/* translators: %s: race name. */
	$subject = sprintf( __( 'You’re entered: %s', 'orienta-yacht-club' ), $race->post_title );
	$body    = sprintf(
		/* translators: 1: first name, 2: race, 3: date, 4: link. */
		__( "Hi %1\$s,\n\nThanks for your RSVP to %2\$s on %3\$s.\n\nRace details, the course chart, the sailing instructions and the Crew Wanted board are in the racers' area:\n%4\$s\n\nYou can change or cancel your RSVP there too.\n\nSee you on the water,\nOrienta Yacht Club Race Committee", 'orienta-yacht-club' ),
		$u->first_name ? $u->first_name : $u->display_name,
		$race->post_title,
		$date,
		oyc_race_url( array( 'view' => 'race', 'race' => $race->ID ) )
	);
	wp_mail( $u->user_email, $subject, $body, oyc_race_mail_headers() );
}

function oyc_race_handle_rsvp() {
	if ( ! is_user_logged_in() ) {
		oyc_race_redirect( 'login' );
	}
	check_admin_referer( 'oyc_race_rsvp' );
	$race = oyc_race_get( (int) ( $_POST['race_id'] ?? 0 ) );
	if ( ! $race ) {
		oyc_race_redirect( 'norace' );
	}
	$uid = get_current_user_id();
	$back = array( 'view' => 'race', 'race' => $race->ID );
	if ( ! oyc_race_rsvp_open( $race ) && ! oyc_race_can_manage() ) {
		oyc_race_redirect( 'closed', $back );
	}
	// Members need an approved login; visiting racers need racer approval.
	if ( oyc_is_guest_racer() ? ! oyc_is_racer() : ! oyc_user_may_view_members_content() ) {
		oyc_race_redirect( 'pending' );
	}
	$m = oyc_race_posted_entry();
	if ( 'skipper' === $m['role'] && '' === $m['boat'] ) {
		oyc_race_redirect( 'noboat', $back, 'rsvp' );
	}
	$had = oyc_race_user_entry( $race->ID, $uid );
	if ( ! oyc_race_save_entry( $race->ID, $uid, $m, 'publish' ) ) {
		oyc_race_redirect( 'failed', $back );
	}
	oyc_race_save_contact( $uid );
	oyc_race_grant( $uid, $race->ID );
	if ( ! $had ) {
		oyc_race_confirm_email( $uid, $race );
	}
	oyc_race_redirect( $had ? 'rsvpupdated' : 'rsvped', $back, 'rsvp' );
}
add_action( 'admin_post_oyc_race_rsvp', 'oyc_race_handle_rsvp' );

function oyc_race_handle_withdraw() {
	if ( ! is_user_logged_in() ) {
		oyc_race_redirect( 'login' );
	}
	check_admin_referer( 'oyc_race_withdraw' );
	$entry = get_post( (int) ( $_POST['entry_id'] ?? 0 ) );
	if ( ! $entry || 'oyc_race_entry' !== $entry->post_type || ( (int) $entry->post_author !== get_current_user_id() && ! oyc_race_can_manage() ) ) {
		oyc_race_redirect( 'denied' );
	}
	$race = (int) $entry->post_parent;
	wp_delete_post( $entry->ID, true );
	oyc_race_redirect( 'withdrawn', array( 'view' => 'race', 'race' => $race ) );
}
add_action( 'admin_post_oyc_race_withdraw', 'oyc_race_handle_withdraw' );

/**
 * Visiting sailor: create a pending Visiting Racer login plus a pending entry.
 * A race officer approves it on the Race Committee tab, which emails them a
 * link to set their password.
 */
function oyc_race_handle_signup() {
	if ( is_user_logged_in() ) {
		oyc_race_redirect( 'already' );
	}
	check_admin_referer( 'oyc_race_signup' );

	// Spam guards: hidden field must stay empty, form must take a few seconds,
	// and no more than 5 sign-ups an hour from one address.
	$started = (int) ( $_POST['oyc_t'] ?? 0 );
	if ( ! empty( $_POST['oyc_website'] ) || ( time() - $started ) < 4 ) {
		oyc_race_redirect( 'signedup' ); // look successful to bots.
	}
	$ip_key = 'oyc_race_su_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) );
	$tries  = (int) get_transient( $ip_key );
	if ( $tries >= 5 ) {
		oyc_race_redirect( 'slowdown' );
	}
	set_transient( $ip_key, $tries + 1, HOUR_IN_SECONDS );

	$race  = oyc_race_get( (int) ( $_POST['race_id'] ?? 0 ) );
	$first = oyc_race_text( 'oyc_first', 40 );
	$last  = oyc_race_text( 'oyc_last', 40 );
	$email = sanitize_email( wp_unslash( $_POST['oyc_email'] ?? '' ) );
	$phone = oyc_race_text( 'oyc_phone', 30 );
	$m     = oyc_race_posted_entry();
	if ( ! $race || '' === $first || '' === $last || ! is_email( $email ) || '' === $phone || ( 'skipper' === $m['role'] && '' === $m['boat'] ) ) {
		oyc_race_redirect( 'sumissing', array(), 'signup' );
	}
	if ( ! oyc_race_rsvp_open( $race ) ) {
		oyc_race_redirect( 'closed' );
	}
	if ( email_exists( $email ) ) {
		oyc_race_redirect( 'haveaccount' );
	}

	$base  = sanitize_user( strtolower( $first . '.' . $last ), true );
	$base  = $base ? $base : 'racer';
	$login = $base;
	$n     = 2;
	while ( username_exists( $login ) ) {
		$login = $base . $n++;
	}
	$uid = wp_insert_user( array(
		'user_login'   => $login,
		'user_email'   => $email,
		'user_pass'    => wp_generate_password( 32, true, true ),
		'first_name'   => $first,
		'last_name'    => $last,
		'display_name' => $first . ' ' . $last,
		'role'         => 'oyc_guest_racer',
	) );
	if ( is_wp_error( $uid ) ) {
		oyc_race_redirect( 'failed' );
	}
	update_user_meta( $uid, 'active', '0' );
	update_user_meta( $uid, 'oyc_racer_pending', '1' );
	oyc_race_save_contact( $uid );
	oyc_race_save_entry( $race->ID, $uid, $m, 'pending' );

	$who  = $first . ' ' . $last;
	$club = oyc_race_text( 'oyc_racer_club', 80 );
	/* translators: 1: name, 2: race. */
	$subject = sprintf( __( 'Visiting racer to approve: %1$s for %2$s', 'orienta-yacht-club' ), $who, $race->post_title );
	$body    = sprintf(
		/* translators: 1: name, 2: club, 3: role/boat, 4: race, 5: link. */
		__( "%1\$s (%2\$s) signed up to race as %3\$s in %4\$s.\n\nApprove or decline on the Race Committee tab:\n%5\$s", 'orienta-yacht-club' ),
		$who,
		$club ? $club : __( 'no club given', 'orienta-yacht-club' ),
		'skipper' === $m['role'] ? sprintf( __( 'skipper of %s', 'orienta-yacht-club' ), $m['boat'] ) : __( 'crew', 'orienta-yacht-club' ),
		$race->post_title,
		oyc_race_url( array( 'view' => 'committee' ), 'pending' )
	);
	foreach ( oyc_race_committee_emails() as $to ) {
		wp_mail( $to, $subject, $body, oyc_race_mail_headers( $email ) );
	}
	oyc_race_redirect( 'signedup' );
}
add_action( 'admin_post_nopriv_oyc_race_signup', 'oyc_race_handle_signup' );
add_action( 'admin_post_oyc_race_signup', 'oyc_race_handle_signup' );

/* ─────────────────────────────────────────────────────────────────────────────
 * Race Committee: races, approvals, email, CSV
 * ───────────────────────────────────────────────────────────────────────────── */

function oyc_race_require_manage( $nonce ) {
	if ( ! is_user_logged_in() ) {
		oyc_race_redirect( 'login' );
	}
	check_admin_referer( $nonce );
	if ( ! oyc_race_can_manage() ) {
		oyc_race_redirect( 'denied' );
	}
}

function oyc_race_handle_save_race() {
	oyc_race_require_manage( 'oyc_race_save' );
	$id    = (int) ( $_POST['race_id'] ?? 0 );
	$race  = $id ? oyc_race_get( $id ) : null;
	$title = oyc_race_text( 'oyc_title', 100 );
	$date  = oyc_race_text( 'oyc_date', 10 );
	$time  = oyc_race_text( 'oyc_time', 5 );
	$dead  = oyc_race_text( 'oyc_deadline', 10 );
	$back  = $id ? array( 'view' => 'committee', 'edit' => $id ) : array( 'view' => 'committee' );
	if ( '' === $title || ! oyc_race_date_ok( $date ) ) {
		oyc_race_redirect( 'racemissing', $back, 'race-form' );
	}
	$data = array(
		'post_type'    => 'oyc_race',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_content' => oyc_race_area_text( 'oyc_details', 6000 ),
	);
	if ( $race ) {
		$data['ID'] = $race->ID;
		$rid        = wp_update_post( $data, true );
	} else {
		$data['post_author'] = get_current_user_id();
		$rid                 = wp_insert_post( $data, true );
	}
	if ( is_wp_error( $rid ) || ! $rid ) {
		oyc_race_redirect( 'failed', $back );
	}
	$rid    = (int) $rid;
	$status = sanitize_key( wp_unslash( $_POST['oyc_status'] ?? '' ) );
	update_post_meta( $rid, '_oyc_race_date', $date );
	update_post_meta( $rid, '_oyc_race_time', preg_match( '/^\d{2}:\d{2}$/', $time ) ? $time : '' );
	update_post_meta( $rid, '_oyc_race_deadline', oyc_race_date_ok( $dead ) ? $dead : '' );
	update_post_meta( $rid, '_oyc_race_area', oyc_race_text( 'oyc_area', 120 ) );
	update_post_meta( $rid, '_oyc_race_status', in_array( $status, array( 'postponed', 'cancelled' ), true ) ? $status : '' );

	$docs   = array_values( (array) get_post_meta( $rid, '_oyc_race_docs', true ) );
	$docs   = array_values( array_filter( $docs, 'is_array' ) );
	$remove = isset( $_POST['oyc_doc_remove'] ) ? array_map( 'intval', (array) $_POST['oyc_doc_remove'] ) : array();
	foreach ( $remove as $i ) {
		if ( isset( $docs[ $i ] ) ) {
			oyc_race_delete_doc_file( $docs[ $i ] );
			unset( $docs[ $i ] );
		}
	}
	list( $added, $bad ) = oyc_race_store_docs();
	update_post_meta( $rid, '_oyc_race_docs', array_values( array_merge( $docs, $added ) ) );

	// New race: optionally add it to the club calendar too.
	if ( ! $race && ! empty( $_POST['oyc_add_cal'] ) && function_exists( 'oyc_create_calendar_event' ) ) {
		$has_time = (bool) get_post_meta( $rid, '_oyc_race_time', true );
		$start    = $has_time ? $date . ' ' . $time . ':00' : $date;
		$end      = $has_time ? gmdate( 'Y-m-d H:i:s', strtotime( $start ) + 4 * HOUR_IN_SECONDS ) : $date;
		$ev       = oyc_create_calendar_event( array(
			'title'   => $title,
			'start'   => $start,
			'end'     => $end,
			'allday'  => ! $has_time,
			'content' => __( 'Racers: details and RSVP at ', 'orienta-yacht-club' ) . oyc_race_url(),
		) );
		if ( $ev && ! is_wp_error( $ev ) ) {
			update_post_meta( $rid, '_oyc_race_cal', (int) $ev );
		}
	}
	if ( function_exists( 'oyc_audit_log' ) ) {
		oyc_audit_log( $race ? 'race.update' : 'race.create', $title . ' (' . $date . ')', $rid );
	}
	oyc_race_redirect( $bad ? 'badfile' : ( $race ? 'raceupdated' : 'raceadded' ), array( 'view' => 'race', 'race' => $rid ) );
}
add_action( 'admin_post_oyc_race_save', 'oyc_race_handle_save_race' );

function oyc_race_handle_delete_race() {
	oyc_race_require_manage( 'oyc_race_delete' );
	$race = oyc_race_get( (int) ( $_POST['race_id'] ?? 0 ) );
	if ( ! $race ) {
		oyc_race_redirect( 'norace', array( 'view' => 'committee' ) );
	}
	foreach ( (array) get_post_meta( $race->ID, '_oyc_race_docs', true ) as $doc ) {
		if ( is_array( $doc ) ) {
			oyc_race_delete_doc_file( $doc );
		}
	}
	foreach ( oyc_race_entries( $race->ID, 'any' ) as $e ) {
		wp_delete_post( $e->ID, true );
	}
	if ( function_exists( 'oyc_audit_log' ) ) {
		oyc_audit_log( 'race.delete', $race->post_title, $race->ID );
	}
	wp_delete_post( $race->ID, true );
	oyc_race_redirect( 'racedeleted', array( 'view' => 'committee' ) );
}
add_action( 'admin_post_oyc_race_delete', 'oyc_race_handle_delete_race' );

/** Approve or decline a visiting sailor. */
function oyc_race_handle_review() {
	oyc_race_require_manage( 'oyc_race_review' );
	$uid = (int) ( $_POST['user_id'] ?? 0 );
	$do  = sanitize_key( wp_unslash( $_POST['do'] ?? '' ) );
	$u   = get_userdata( $uid );
	$back = array( 'view' => 'committee' );
	if ( ! $u || ! oyc_is_guest_racer( $uid ) ) {
		oyc_race_redirect( 'denied', $back );
	}
	$entries = oyc_race_user_entries( $uid );
	if ( 'decline' === $do ) {
		foreach ( $entries as $e ) {
			wp_delete_post( $e->ID, true );
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $uid );
		if ( function_exists( 'oyc_audit_log' ) ) {
			oyc_audit_log( 'racer.decline', $u->display_name . ' (' . $u->user_email . ')' );
		}
		oyc_race_redirect( 'declined', $back, 'pending' );
	}

	update_user_meta( $uid, 'active', '1' );
	update_user_meta( $uid, '_wpmem_user_confirmed', time() );
	delete_user_meta( $uid, 'oyc_racer_pending' );
	$races = array();
	foreach ( $entries as $e ) {
		if ( 'pending' === $e->post_status ) {
			wp_update_post( array( 'ID' => $e->ID, 'post_status' => 'publish' ) );
		}
		oyc_race_grant( $uid, (int) $e->post_parent );
		$races[] = get_the_title( $e->post_parent );
	}
	if ( ! $entries ) {
		update_user_meta( $uid, 'oyc_racer_until', current_time( 'Y' ) . '-12-31' );
	}

	$key  = get_password_reset_key( $u );
	$link = is_wp_error( $key ) ? wp_lostpassword_url( oyc_race_url() ) : network_site_url( 'wp-login.php?action=rp&key=' . rawurlencode( $key ) . '&login=' . rawurlencode( $u->user_login ), 'login' );
	$subject = __( 'Your Orienta Yacht Club racer login is ready', 'orienta-yacht-club' );
	$body    = sprintf(
		/* translators: 1: first name, 2: race names, 3: set-password link, 4: username, 5: hub link. */
		__( "Hi %1\$s,\n\nWelcome aboard! Your entry for %2\$s is confirmed and your racer login is ready.\n\nSet your password here (the link works once):\n%3\$s\n\nYour username is: %4\$s\n\nThen log in to the racers' area for race details, the course chart, sailing instructions and the Crew Wanted board:\n%5\$s\n\nOrienta Yacht Club Race Committee", 'orienta-yacht-club' ),
		$u->first_name ? $u->first_name : $u->display_name,
		$races ? implode( ', ', $races ) : __( 'this season', 'orienta-yacht-club' ),
		$link,
		$u->user_login,
		oyc_race_url()
	);
	$sent = wp_mail( $u->user_email, $subject, $body, oyc_race_mail_headers() );
	if ( function_exists( 'oyc_audit_log' ) ) {
		oyc_audit_log( 'racer.approve', $u->display_name . ' (' . $u->user_email . ')', $uid );
	}
	oyc_race_redirect( $sent ? 'approved' : 'approvednomail', $back, 'pending' );
}
add_action( 'admin_post_oyc_race_review', 'oyc_race_handle_review' );

/**
 * Who an email or CSV goes to. $aud: 'season', or "race:{id}", "skippers:{id}",
 * "crew:{id}". Returns WP_User[] (published entries only).
 */
function oyc_race_audience( $aud ) {
	if ( 'season' === $aud ) {
		return oyc_race_season_racers();
	}
	if ( ! preg_match( '/^(race|skippers|crew):(\d+)$/', (string) $aud, $m ) ) {
		return array();
	}
	$users = array();
	foreach ( oyc_race_entries( (int) $m[2] ) as $e ) {
		$role = oyc_race_entry_meta( $e )['role'];
		if ( 'race' === $m[1] || ( 'skippers' === $m[1] && 'skipper' === $role ) || ( 'crew' === $m[1] && 'crew' === $role ) ) {
			$u = get_userdata( $e->post_author );
			if ( $u ) {
				$users[ $u->ID ] = $u;
			}
		}
	}
	return array_values( $users );
}

function oyc_race_audience_label( $aud ) {
	if ( 'season' === $aud ) {
		return __( 'All racers this season', 'orienta-yacht-club' );
	}
	if ( preg_match( '/^(race|skippers|crew):(\d+)$/', (string) $aud, $m ) ) {
		$t = get_the_title( (int) $m[2] );
		$w = array( 'race' => __( 'Everyone entered in %s', 'orienta-yacht-club' ), 'skippers' => __( 'Skippers entered in %s', 'orienta-yacht-club' ), 'crew' => __( 'Crew entered in %s', 'orienta-yacht-club' ) );
		return sprintf( $w[ $m[1] ], $t );
	}
	return '';
}

/**
 * Email racers. Sent in BCC batches of 40 so nobody sees anyone else's
 * address, To is the club's own address, Reply-To is the sender.
 */
function oyc_race_handle_email() {
	oyc_race_require_manage( 'oyc_race_email' );
	$aud     = sanitize_text_field( wp_unslash( $_POST['oyc_aud'] ?? '' ) );
	$subject = oyc_race_text( 'oyc_subject', 140 );
	$message = oyc_race_area_text( 'oyc_message', 8000 );
	$back    = array( 'view' => 'committee' );
	if ( '' === $subject || '' === $message ) {
		oyc_race_redirect( 'mailmissing', $back, 'email' );
	}
	$users = oyc_race_audience( $aud );
	$to    = array();
	foreach ( $users as $u ) {
		if ( is_email( $u->user_email ) ) {
			$to[] = $u->user_email;
		}
	}
	$to = array_values( array_unique( $to ) );
	if ( ! $to ) {
		oyc_race_redirect( 'mailnobody', $back, 'email' );
	}
	$me   = wp_get_current_user();
	$body = $message . "\n\n—\n" . sprintf( /* translators: %s: sender name */ __( 'Sent by %s for the Orienta Yacht Club Race Committee. Reply to this email to answer them.', 'orienta-yacht-club' ), oyc_race_name( $me->ID ) ) . "\n" . __( "Racers' area: ", 'orienta-yacht-club' ) . oyc_race_url() . "\n";
	$club = get_option( 'admin_email' );
	$ok   = 0;
	foreach ( array_chunk( $to, 40 ) as $batch ) {
		$headers = oyc_race_mail_headers( $me->user_email );
		foreach ( $batch as $addr ) {
			$headers[] = 'Bcc: ' . $addr;
		}
		if ( wp_mail( $club, '[OYC Racing] ' . $subject, $body, $headers ) ) {
			$ok += count( $batch );
		}
	}
	$log = (array) get_option( 'oyc_race_mail_log', array() );
	array_unshift( $log, array( 'time' => time(), 'by' => $me->ID, 'aud' => oyc_race_audience_label( $aud ), 'subject' => $subject, 'sent' => $ok, 'total' => count( $to ) ) );
	update_option( 'oyc_race_mail_log', array_slice( $log, 0, 25 ), false );
	if ( function_exists( 'oyc_audit_log' ) ) {
		oyc_audit_log( 'race.email', sprintf( '"%s" to %d racers (%s)', $subject, $ok, oyc_race_audience_label( $aud ) ) );
	}
	oyc_race_redirect( $ok === count( $to ) ? 'mailsent' : 'mailpartial', array_merge( $back, array( 'n' => $ok ) ), 'email' );
}
add_action( 'admin_post_oyc_race_email', 'oyc_race_handle_email' );

/** CSV of entries (one race) or all racers this season. */
function oyc_race_handle_csv() {
	if ( ! is_user_logged_in() || ! oyc_race_can_manage() ) {
		oyc_race_redirect( 'denied' );
	}
	check_admin_referer( 'oyc_race_csv' );
	$race_id = (int) ( $_GET['race'] ?? 0 );
	$race    = $race_id ? oyc_race_get( $race_id ) : null;
	$name    = $race ? sanitize_title( $race->post_title ) : 'racers-' . current_time( 'Y' );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="oyc-' . $name . '.csv"' );
	$out = fopen( 'php://output', 'w' );
	fputs( $out, "\xEF\xBB\xBF" );
	$safe = function ( $v ) {
		$v = (string) $v;
		return preg_match( '/^[=+\-@\t\r]/', $v ) ? "'" . $v : $v; // no spreadsheet formulas.
	};
	$contact = function ( $uid ) {
		$u = get_userdata( $uid );
		return array(
			oyc_race_name( $uid ),
			$u ? $u->user_email : '',
			get_user_meta( $uid, 'oyc_phone', true ),
			oyc_is_guest_racer( $uid ) ? ( get_user_meta( $uid, 'oyc_racer_club', true ) ?: __( 'Visitor', 'orienta-yacht-club' ) ) : 'OYC',
			get_user_meta( $uid, 'oyc_emergency_name', true ),
			get_user_meta( $uid, 'oyc_emergency_phone', true ),
			get_user_meta( $uid, 'oyc_emergency_relationship', true ),
		);
	};
	$head = array( 'Name', 'Email', 'Phone', 'Club', 'Emergency contact', 'Emergency phone', 'Relationship' );
	if ( $race ) {
		fputcsv( $out, array_merge( $head, array( 'Role', 'Boat', 'Sail #', 'Class', 'Rating', 'Crew', 'Looking for', 'Notes', 'Status' ) ) );
		foreach ( oyc_race_entries( $race->ID, 'any' ) as $e ) {
			$m    = oyc_race_entry_meta( $e );
			$need = $m['need'] ? ( 'skipper' === $m['role'] ? 'Crew' : 'A boat' ) : '';
			fputcsv( $out, array_map( $safe, array_merge( $contact( $e->post_author ), array( ucfirst( $m['role'] ), $m['boat'], $m['sail'], $m['class'], $m['rating'], $m['crew'], $need, $m['notes'], 'pending' === $e->post_status ? 'Awaiting approval' : 'Entered' ) ) ) );
		}
	} else {
		fputcsv( $out, array_merge( $head, array( 'Racer access until' ) ) );
		foreach ( oyc_race_season_racers() as $u ) {
			fputcsv( $out, array_map( $safe, array_merge( $contact( $u->ID ), array( get_user_meta( $u->ID, 'oyc_racer_until', true ) ) ) ) );
		}
	}
	fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	exit;
}
add_action( 'admin_post_oyc_race_csv', 'oyc_race_handle_csv' );

/* ─────────────────────────────────────────────────────────────────────────────
 * Racer profile, yachts, crew board, messages
 * ───────────────────────────────────────────────────────────────────────────── */

function oyc_race_require_racer( $nonce ) {
	if ( ! is_user_logged_in() ) {
		oyc_race_redirect( 'login' );
	}
	check_admin_referer( $nonce );
	if ( ! oyc_is_racer() ) {
		oyc_race_redirect( 'notracer' );
	}
}

function oyc_race_handle_profile() {
	oyc_race_require_racer( 'oyc_race_profile' );
	$uid  = get_current_user_id();
	$back = array( 'view' => 'me' );
	// The Race Committee may edit anyone's racing profile.
	$for = (int) ( $_POST['user_id'] ?? 0 );
	if ( $for && $for !== $uid ) {
		if ( ! oyc_race_can_manage() || ! get_userdata( $for ) ) {
			oyc_race_redirect( 'denied', $back );
		}
		$uid         = $for;
		$back['uid'] = $for;
	}
	oyc_race_save_contact( $uid );
	$pos = oyc_race_posted_positions( 'oyc_positions' );
	if ( $pos ) {
		update_user_meta( $uid, 'oyc_crew_positions', $pos );
	} else {
		delete_user_meta( $uid, 'oyc_crew_positions' );
	}
	$level = sanitize_key( wp_unslash( $_POST['oyc_level'] ?? '' ) );
	update_user_meta( $uid, 'oyc_crew_level', isset( oyc_race_levels()[ $level ] ) ? $level : '' );
	update_user_meta( $uid, 'oyc_crew_about', oyc_race_area_text( 'oyc_about', 1000 ) );
	if ( ! empty( $_POST['oyc_hidden'] ) ) {
		update_user_meta( $uid, 'oyc_crew_hidden', '1' );
	} else {
		delete_user_meta( $uid, 'oyc_crew_hidden' );
	}
	oyc_race_redirect( 'profilesaved', $back, 'profile' );
}
add_action( 'admin_post_oyc_race_profile', 'oyc_race_handle_profile' );

function oyc_race_can_edit_post( $post, $type ) {
	return $post && $type === $post->post_type && ( (int) $post->post_author === get_current_user_id() || oyc_race_can_manage() );
}

function oyc_race_handle_yacht() {
	oyc_race_require_racer( 'oyc_race_yacht' );
	$id    = (int) ( $_POST['yacht_id'] ?? 0 );
	$post  = $id ? get_post( $id ) : null;
	$back  = array( 'view' => 'me' );
	if ( $id && ! oyc_race_can_edit_post( $post, 'oyc_yacht' ) ) {
		oyc_race_redirect( 'denied', $back );
	}
	if ( $post && (int) $post->post_author !== get_current_user_id() ) {
		$back['uid'] = (int) $post->post_author; // Race Committee edit: back to the owner's profile.
	}
	if ( ! empty( $_POST['oyc_delete'] ) && $post ) {
		$photo = (int) get_post_meta( $id, '_oyc_bb_photo', true );
		if ( $photo ) {
			wp_delete_attachment( $photo, true );
		}
		wp_delete_post( $id, true );
		oyc_race_redirect( 'yachtdeleted', $back, 'yachts' );
	}
	if ( ! $id && count( oyc_race_yachts( get_current_user_id() ) ) >= 5 ) {
		oyc_race_redirect( 'failed', $back );
	}
	$name = oyc_race_text( 'oyc_boat', 60 );
	if ( '' === $name ) {
		oyc_race_redirect( 'noboat', $back, 'yachts' );
	}
	$data = array(
		'post_type'    => 'oyc_yacht',
		'post_status'  => 'publish',
		'post_title'   => $name,
		'post_content' => oyc_race_area_text( 'oyc_about', 1500 ),
	);
	if ( $post ) {
		$data['ID'] = $id;
		$rid        = wp_update_post( $data, true );
	} else {
		$data['post_author'] = get_current_user_id();
		$rid                 = wp_insert_post( $data, true );
	}
	if ( is_wp_error( $rid ) || ! $rid ) {
		oyc_race_redirect( 'failed', $back );
	}
	foreach ( array( 'sail' => 20, 'class' => 40, 'rating' => 12, 'loa' => 12, 'club' => 80 ) as $k => $max ) {
		update_post_meta( $rid, '_' . $k, oyc_race_text( 'oyc_' . $k, $max ) );
	}
	if ( ! empty( $_POST['oyc_hidden'] ) ) {
		update_post_meta( $rid, '_hidden', '1' );
	} else {
		delete_post_meta( $rid, '_hidden' );
	}
	// Same photo checks as the Bulletin Board (input name oyc_bb_photo).
	$bad = false;
	if ( function_exists( 'oyc_bb_store_photo' ) ) {
		$new = oyc_bb_store_photo( $rid );
		if ( $new ) {
			$old = (int) get_post_meta( $rid, '_oyc_bb_photo', true );
			if ( $old ) {
				wp_delete_attachment( $old, true );
			}
			update_post_meta( $rid, '_oyc_bb_photo', $new );
		}
		$bad = false === $new;
	}
	oyc_race_redirect( $bad ? 'badphoto' : 'yachtsaved', $back, 'yachts' );
}
add_action( 'admin_post_oyc_race_yacht', 'oyc_race_handle_yacht' );

function oyc_race_handle_ad() {
	oyc_race_require_racer( 'oyc_race_ad' );
	$id   = (int) ( $_POST['ad_id'] ?? 0 );
	$post = $id ? get_post( $id ) : null;
	$back = array( 'view' => 'board' );
	if ( $id && ! oyc_race_can_edit_post( $post, 'oyc_crew_ad' ) ) {
		oyc_race_redirect( 'denied', $back );
	}
	$do = sanitize_key( wp_unslash( $_POST['do'] ?? 'save' ) );
	if ( $post && 'delete' === $do ) {
		wp_delete_post( $id, true );
		oyc_race_redirect( 'addeleted', $back );
	}
	if ( $post && 'close' === $do ) {
		$closed = get_post_meta( $id, '_closed', true );
		$closed ? delete_post_meta( $id, '_closed' ) : update_post_meta( $id, '_closed', '1' );
		oyc_race_redirect( $closed ? 'adreopened' : 'adclosed', $back, 'ad-' . $id );
	}
	$title = oyc_race_text( 'oyc_title', 90 );
	$desc  = oyc_race_area_text( 'oyc_desc', 1500 );
	if ( '' === $title || '' === $desc ) {
		oyc_race_redirect( 'admissing', $back, 'ad-form' );
	}
	$data = array(
		'post_type'    => 'oyc_crew_ad',
		'post_status'  => 'publish',
		'post_title'   => $title,
		'post_content' => $desc,
	);
	if ( $post ) {
		$data['ID'] = $id;
		$rid        = wp_update_post( $data, true );
	} else {
		$data['post_author'] = get_current_user_id();
		$rid                 = wp_insert_post( $data, true );
	}
	if ( is_wp_error( $rid ) || ! $rid ) {
		oyc_race_redirect( 'failed', $back );
	}
	$race = oyc_race_get( (int) ( $_POST['oyc_race'] ?? 0 ) );
	update_post_meta( $rid, '_type', ( isset( $_POST['oyc_type'] ) && 'boat' === $_POST['oyc_type'] ) ? 'boat' : 'crew' );
	update_post_meta( $rid, '_race', $race ? $race->ID : 0 );
	update_post_meta( $rid, '_positions', oyc_race_posted_positions( 'oyc_positions' ) );
	oyc_race_redirect( $post ? 'adupdated' : 'adposted', $back, 'ad-' . $rid );
}
add_action( 'admin_post_oyc_race_ad', 'oyc_race_handle_ad' );

/**
 * Message another racer through the site. The recipient gets an email with the
 * sender as Reply-To; neither address is ever shown on the page.
 */
function oyc_race_handle_message() {
	oyc_race_require_racer( 'oyc_race_message' );
	$to   = get_userdata( (int) ( $_POST['to'] ?? 0 ) );
	$text = oyc_race_area_text( 'oyc_message', 3000 );
	$ret  = sanitize_text_field( wp_unslash( $_POST['oyc_return'] ?? '' ) );
	$back = array();
	parse_str( (string) wp_parse_url( $ret, PHP_URL_QUERY ), $back );
	$back = array_intersect_key( $back, array_flip( array( 'view', 'race' ) ) );
	if ( ! $to || ! oyc_is_racer( $to->ID ) || $to->ID === get_current_user_id() ) {
		oyc_race_redirect( 'denied', $back );
	}
	if ( '' === trim( $text ) ) {
		oyc_race_redirect( 'msgmissing', $back );
	}
	$me    = wp_get_current_user();
	$key   = 'oyc_race_msgs_' . $me->ID;
	$count = (int) get_transient( $key );
	if ( $count >= OYC_RACE_MSG_PER_DAY ) {
		oyc_race_redirect( 'slowdown', $back );
	}
	set_transient( $key, $count + 1, DAY_IN_SECONDS );
	$about = '';
	$ad    = get_post( (int) ( $_POST['about'] ?? 0 ) );
	if ( $ad && in_array( $ad->post_type, array( 'oyc_crew_ad', 'oyc_yacht' ), true ) ) {
		$about = ' — ' . $ad->post_title;
	}
	$from = oyc_race_name( $me->ID );
	/* translators: 1: sender name, 2: " — post title" or nothing. */
	$subject = sprintf( __( 'Message from %1$s (OYC Racing)%2$s', 'orienta-yacht-club' ), $from, $about );
	$body    = sprintf(
		/* translators: 1: recipient first name, 2: sender, 3: message, 4: sender email. */
		__( "Hi %1\$s,\n\n%2\$s sent you a message through the Orienta Yacht Club racers' area:\n\n%3\$s\n\n—\nReply to this email to answer %2\$s directly (%4\$s).", 'orienta-yacht-club' ),
		$to->first_name ? $to->first_name : $to->display_name,
		$from,
		$text,
		$me->user_email
	);
	$sent = wp_mail( $to->user_email, $subject, $body, oyc_race_mail_headers( $me->user_email ) );
	oyc_race_redirect( $sent ? 'msgsent' : 'failed', $back );
}
add_action( 'admin_post_oyc_race_message', 'oyc_race_handle_message' );

/* ─────────────────────────────────────────────────────────────────────────────
 * Rendering helpers used by page-racing.php
 * ───────────────────────────────────────────────────────────────────────────── */

/** A small "Message" button that opens the shared message dialog. */
function oyc_race_msg_button( $user_id, $label = '', $about = 0 ) {
	if ( ! $user_id || (int) $user_id === get_current_user_id() ) {
		return;
	}
	printf(
		'<button type="button" class="btn btn-sm rc-btn rc-msg" data-to="%1$d" data-name="%2$s" data-about="%3$d">%4$s</button>',
		(int) $user_id,
		esc_attr( oyc_race_name( $user_id ) ),
		(int) $about,
		esc_html( $label ? $label : __( 'Message', 'orienta-yacht-club' ) )
	);
}

/** One-button POST form (delete, withdraw, approve, …). */
function oyc_race_post_button( $action, $fields, $label, $class = 'rc-btn', $confirm = '' ) {
	echo '<form method="post" class="rc-inline" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . ( $confirm ? ' onsubmit="return confirm(\'' . esc_js( $confirm ) . '\');"' : '' ) . '>';
	echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
	foreach ( $fields as $k => $v ) {
		echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '">';
	}
	wp_nonce_field( $action );
	echo '<button type="submit" class="btn btn-sm ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button>';
	echo '</form>';
}

function oyc_race_status_badge( $race ) {
	$s = (string) get_post_meta( $race->ID, '_oyc_race_status', true );
	if ( 'cancelled' === $s ) {
		return '<span class="rc-badge rc-badge--bad">' . esc_html__( 'Cancelled', 'orienta-yacht-club' ) . '</span>';
	}
	if ( 'postponed' === $s ) {
		return '<span class="rc-badge rc-badge--warn">' . esc_html__( 'Postponed', 'orienta-yacht-club' ) . '</span>';
	}
	return '';
}

/**
 * Member Dashboard panel: next race, and a link to the racing hub.
 */
function oyc_race_dashboard_panel() {
	$next = oyc_race_races( 'upcoming', 1 );
	echo '<a class="dash-bb dash-race" href="' . esc_url( oyc_race_url() ) . '">';
	echo '<span class="dash-bb__head"><span class="dash-bb__title">' . esc_html__( 'Racing', 'orienta-yacht-club' ) . '</span>';
	echo '<span class="dash-bb__sub">' . esc_html( oyc_is_racer() ? __( 'Racers’ area', 'orienta-yacht-club' ) : __( 'RSVP to race', 'orienta-yacht-club' ) ) . '</span></span>';
	if ( $next ) {
		$r = $next[0];
		echo '<span class="dash-race__next"><span class="dash-race__name">' . esc_html( $r->post_title ) . '</span> ';
		echo '<span class="dash-race__date">' . esc_html( oyc_race_fmt_date( get_post_meta( $r->ID, '_oyc_race_date', true ) ) ) . '</span></span>';
	} else {
		echo '<span class="dash-bb__empty">' . esc_html__( 'No races scheduled yet. Tap here for the racers’ area.', 'orienta-yacht-club' ) . '</span>';
	}
	echo '</a>';
}
