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
function oyc_officer_page_url( $template, $fallback_slug ) {
	$pages = get_pages( array(
		'meta_key'   => '_wp_page_template',
		'meta_value' => $template,
		'number'     => 1,
	) );

	if ( ! empty( $pages ) ) {
		return get_permalink( $pages[0]->ID );
	}

	return home_url( '/' . $fallback_slug . '/' );
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
			'template' => 'page-officer-hub.php',
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

/* ─────────────────────────────────────────────────────────────────────────────
 * Assets
 * ───────────────────────────────────────────────────────────────────────────── */

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_user_logged_in() || ! current_user_can( 'oyc_access_officer_area' ) ) {
		return;
	}

	$templates = array(
		'page-officer-hub.php',
		'page-officer-events.php',
		'page-officer-messages.php',
		'page-officer-members.php',
	);

	if ( ! is_page_template( $templates ) ) {
		return;
	}

	wp_enqueue_style(
		'oyc-officer',
		get_template_directory_uri() . '/assets/officer.css',
		array(),
		wp_get_theme()->get( 'Version' )
	);
}, 20 );
