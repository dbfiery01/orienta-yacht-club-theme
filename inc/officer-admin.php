<?php
/**
 * OYC Officer Area — shared guards, guardrails, notices and navigation.
 *
 * Every officer page calls oyc_officer_guard() before emitting output. The
 * user-management guardrails live here so the rules are enforced in one place:
 *
 *  - A non-administrator can never view, edit, or delete an Administrator account.
 *  - A non-administrator can never grant the Administrator role (no escalation).
 *  - Nobody can delete their own account from the officer area.
 *  - The last remaining Administrator cannot be deleted.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Gate an officer page. Sends guests to login and blocks logged-in users who
 * lack the capability. Must be called before get_header().
 *
 * @param string $cap Capability required by the page.
 */
function oyc_officer_guard( $cap = 'oyc_access_officer_area' ) {
	global $oyc_is_officer_page;
	$oyc_is_officer_page = true;

	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( get_permalink() ) );
		exit;
	}

	if ( ! current_user_can( $cap ) ) {
		wp_die(
			esc_html__( 'You do not have permission to view this page.', 'orienta-yacht-club' ),
			esc_html__( 'Permission denied', 'orienta-yacht-club' ),
			array( 'response' => 403, 'back_link' => true )
		);
	}
}

/* ─────────────────────────────────────────────────────────────────────────────
 * Notices
 * ───────────────────────────────────────────────────────────────────────────── */

global $oyc_officer_notices;
$oyc_officer_notices = array();

/**
 * Queue a notice for display at the top of the current officer page.
 *
 * @param string $msg  Message text.
 * @param string $type 'success' | 'error'.
 */
function oyc_officer_notice( $msg, $type = 'success' ) {
	global $oyc_officer_notices;
	$oyc_officer_notices[] = array( 'msg' => $msg, 'type' => $type );
}

/** Render and clear any queued notices. */
function oyc_officer_render_notices() {
	global $oyc_officer_notices;
	foreach ( (array) $oyc_officer_notices as $n ) {
		printf(
			'<div class="officer-notice officer-notice--%1$s">%2$s</div>',
			esc_attr( $n['type'] ),
			esc_html( $n['msg'] )
		);
	}
	$oyc_officer_notices = array();
}

/* ─────────────────────────────────────────────────────────────────────────────
 * User-management guardrails
 * ───────────────────────────────────────────────────────────────────────────── */

/**
 * Roles the current user is allowed to assign. Administrator is withheld from
 * everyone who is not already an administrator, which blocks escalation.
 *
 * @return array role slug => display name
 */
function oyc_officer_assignable_roles() {
	$roles = wp_roles()->get_names();

	if ( ! current_user_can( 'administrator' ) ) {
		unset( $roles['administrator'] );
	}

	return $roles;
}

/**
 * Whether the current user may act on the target account.
 *
 * @param int $target_id User ID.
 * @return bool
 */
function oyc_officer_can_manage_user( $target_id ) {
	$target_id = (int) $target_id;

	if ( ! $target_id || ! current_user_can( 'oyc_manage_members' ) ) {
		return false;
	}

	$target = get_userdata( $target_id );
	if ( ! $target ) {
		return false;
	}

	// Administrator accounts are visible only to other administrators.
	if ( user_can( $target, 'administrator' ) && ! current_user_can( 'administrator' ) ) {
		return false;
	}

	return true;
}

/**
 * Whether the target account may be deleted, and why not if it may not.
 *
 * @param int $target_id User ID.
 * @return true|WP_Error
 */
function oyc_officer_can_delete_user( $target_id ) {
	$target_id = (int) $target_id;

	if ( ! oyc_officer_can_manage_user( $target_id ) ) {
		return new WP_Error( 'denied', __( 'You cannot manage that account.', 'orienta-yacht-club' ) );
	}

	if ( $target_id === get_current_user_id() ) {
		return new WP_Error( 'self', __( 'You cannot delete your own account here.', 'orienta-yacht-club' ) );
	}

	if ( user_can( $target_id, 'administrator' ) ) {
		$admins = get_users( array( 'role' => 'administrator', 'fields' => 'ID', 'number' => 2 ) );
		if ( count( $admins ) < 2 ) {
			return new WP_Error( 'last_admin', __( 'This is the last administrator account and cannot be deleted.', 'orienta-yacht-club' ) );
		}
	}

	return true;
}

/* ─────────────────────────────────────────────────────────────────────────────
 * Navigation
 * ───────────────────────────────────────────────────────────────────────────── */

/**
 * Resolve an officer page URL by its template file, falling back to a slug so
 * the links still work before the pages are wired up in wp-admin.
 *
 * @param string $template      Template filename.
 * @param string $fallback_slug Slug to use if no page uses that template.
 * @return string
 */
function oyc_officer_page_url( $template, $slug ) {
	// Slug first. These pages are defined by slug — the template hierarchy matches
	// page-{slug}.php automatically — and it resolves to exactly one page.
	// get_pages() with meta_key/meta_value is NOT safe here: when no page has the
	// template assigned (the normal case, since the hierarchy sets no
	// _wp_page_template meta) it does not reliably filter, and every link ends up
	// pointing at whichever page comes back first.
	$page = get_page_by_path( $slug );

	if ( $page ) {
		return get_permalink( $page );
	}

	// Otherwise honour a page that has the template explicitly assigned.
	$ids = get_posts( array(
		'post_type'      => 'page',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_wp_page_template',
		'meta_value'     => $template,
	) );

	if ( $ids ) {
		return get_permalink( $ids[0] );
	}

	return home_url( '/' . $slug . '/' );
}

/**
 * The officer area's section links, filtered to what the current user may reach.
 *
 * @return array
 */
function oyc_officer_sections() {
	$all = array(
		array(
			'key'      => 'hub',
			'label'    => __( 'Officer Hub', 'orienta-yacht-club' ),
			'cap'      => 'oyc_access_officer_area',
			'template' => 'page-officers.php',
			'slug'     => 'officers',
		),
		array(
			'key'      => 'events',
			'label'    => __( 'Calendar Events', 'orienta-yacht-club' ),
			'cap'      => 'oyc_manage_events',
			'template' => 'page-officer-events.php',
			'slug'     => 'officer-events',
		),
		array(
			'key'      => 'messages',
			'label'    => __( 'Messages', 'orienta-yacht-club' ),
			'cap'      => 'oyc_manage_messages',
			'template' => 'page-officer-messages.php',
			'slug'     => 'officer-messages',
		),
		array(
			'key'      => 'members',
			'label'    => __( 'Members', 'orienta-yacht-club' ),
			'cap'      => 'oyc_manage_members',
			'template' => 'page-officer-members.php',
			'slug'     => 'officer-members',
		),
	);

	$out = array();
	foreach ( $all as $s ) {
		if ( current_user_can( $s['cap'] ) ) {
			$s['url'] = oyc_officer_page_url( $s['template'], $s['slug'] );
			$out[]    = $s;
		}
	}

	return $out;
}

/**
 * The officer sections minus the hub itself, for places that are already a hub
 * (the hub page's own tiles, the dashboard block).
 *
 * @return array
 */
function oyc_officer_subsections() {
	return array_values( array_filter( oyc_officer_sections(), function ( $s ) {
		return 'hub' !== $s['key'];
	} ) );
}

/**
 * Render the officer sub-navigation.
 *
 * @param string $current Key of the active section.
 */
function oyc_officer_nav( $current = '' ) {
	$sections = oyc_officer_sections();
	if ( ! $sections ) {
		return;
	}

	echo '<nav class="officer-nav" aria-label="' . esc_attr__( 'Officer area', 'orienta-yacht-club' ) . '">';
	foreach ( $sections as $s ) {
		printf(
			'<a class="officer-nav__link%1$s" href="%2$s"%3$s>%4$s</a>',
			$s['key'] === $current ? ' is-current' : '',
			esc_url( $s['url'] ),
			$s['key'] === $current ? ' aria-current="page"' : '',
			esc_html( $s['label'] )
		);
	}
	echo '</nav>';
}

/**
 * The officer area's page templates.
 *
 * @return string[]
 */
function oyc_officer_templates() {
	return array(
		'page-officers.php',
		'page-officer-events.php',
		'page-officer-messages.php',
		'page-officer-members.php',
	);
}

/**
 * Whether the current request is an officer-area page.
 *
 * Set by oyc_officer_guard(), which every officer template calls before
 * get_header() — i.e. before wp_enqueue_scripts, body_class and wp_head all
 * run. is_page_template() alone is NOT sufficient: WordPress's template
 * hierarchy auto-matches page-{slug}.php to a page of that slug, so these
 * pages render the officer template with no _wp_page_template meta set and
 * is_page_template() returns false. The flag is true whenever an officer
 * template is actually executing, however WordPress resolved it.
 *
 * @return bool
 */
function oyc_officer_is_officer_page() {
	global $oyc_is_officer_page;

	if ( ! empty( $oyc_is_officer_page ) ) {
		return true;
	}

	return is_page_template( oyc_officer_templates() );
}

/**
 * Body class for the officer pages so their CSS is keyed on the template rather
 * than the page slug (renaming a page must not change the styling).
 */
add_filter( 'body_class', function ( $classes ) {
	if ( oyc_officer_is_officer_page() ) {
		$classes[] = 'oyc-officer-page';
	}
	return $classes;
} );

/* ─────────────────────────────────────────────────────────────────────────────
 * Search engines — the officer area must never be indexed.
 *
 * Guests are redirected to login so a crawler never sees content, but the URLs
 * are still discoverable. Covers Yoast (string and array forms, as inc/seo.php
 * does) and core's wp_robots for the case where Yoast is inactive.
 * ───────────────────────────────────────────────────────────────────────────── */

add_filter( 'wp_robots', function ( $robots ) {
	if ( oyc_officer_is_officer_page() ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		unset( $robots['index'], $robots['follow'] );
	}
	return $robots;
} );

add_filter( 'wpseo_robots', function ( $robots ) {
	return oyc_officer_is_officer_page() ? 'noindex, nofollow' : $robots;
} );

add_filter( 'wpseo_robots_array', function ( $robots ) {
	if ( oyc_officer_is_officer_page() && is_array( $robots ) ) {
		$robots['index']  = 'noindex';
		$robots['follow'] = 'nofollow';
	}
	return $robots;
} );

/* ─────────────────────────────────────────────────────────────────────────────
 * Critical hero CSS — printed inline rather than shipped in officer.css.
 *
 * The parent's body.has-hero-header .page-hero sets
 * min-height: clamp(420px, 56vh, 560px) with 5.5rem/3.5rem padding, which is an
 * exact specificity tie with our override, so the result depends on stylesheet
 * order. Worse, WP-Optimize's minify bundle can serve a stale copy of
 * officer.css after a deploy, dropping these rules entirely. Printing them at
 * wp_head priority 999 puts them after every enqueued stylesheet and outside the
 * bundle, so the compact hero cannot be defeated by cache state or load order.
 * ───────────────────────────────────────────────────────────────────────────── */

add_action( 'wp_head', function () {
	if ( ! oyc_officer_is_officer_page() ) {
		return;
	}

	$sel = 'body.oyc-officer-page';

	$css = "
{$sel} { background:#f5f9fd !important; }
{$sel} .page-hero {
	min-height:0 !important;
	height:auto !important;
	padding:92px 0 12px !important;
	background:#fff !important;
	background-image:none !important;
	border-bottom:1px solid rgba(11,46,74,0.10) !important;
	display:block !important;
}
{$sel} .page-hero::before,
{$sel} .page-hero::after { display:none !important; }
{$sel} .page-hero-title { color:#16324a !important; margin:0 !important; font-size:1.9rem !important; }
{$sel} .page-hero-eyebrow {
	color:#1583cf !important;
	font-size:.85rem !important;
	font-weight:600 !important;
	letter-spacing:.1em !important;
	text-transform:uppercase !important;
	margin:0 0 .2rem !important;
}
{$sel} .section { padding-top:14px !important; padding-bottom:18px !important; }
{$sel} .oyc-carousel { display:none !important; }
";

	echo "<style id=\"oyc-officer-hero\">" . wp_strip_all_tags( $css ) . "</style>\n";
}, 999 );

/* ─────────────────────────────────────────────────────────────────────────────
 * Assets
 * ───────────────────────────────────────────────────────────────────────────── */

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_user_logged_in() || ! current_user_can( 'oyc_access_officer_area' ) ) {
		return;
	}

	// Also loads on the Member Dashboard, which renders the officer link block.
	if ( ! oyc_officer_is_officer_page() && ! is_page_template( 'page-dashboard.php' ) ) {
		return;
	}

	wp_enqueue_style(
		'oyc-officer',
		get_template_directory_uri() . '/assets/officer.css',
		array(),
		wp_get_theme()->get( 'Version' )
	);
}, 20 );
