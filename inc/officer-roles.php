<?php
/**
 * OYC Officer Roles — the "Club Officer" role, its capabilities, and the audit log.
 *
 * Club Officers administer the site from the front-end officer pages without ever
 * seeing wp-admin. The role is deliberately narrow: it carries only the four caps
 * below plus `read`, so an officer account cannot reach plugin settings, the theme
 * editor, or anything else that would let it escalate.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Bump to re-run role registration after changing the capability list. */
define( 'OYC_OFFICER_ROLES_VERSION', '1.0.0' );

/**
 * Capabilities the officer area recognises.
 *
 * @return string[]
 */
function oyc_officer_caps() {
	return array(
		'oyc_access_officer_area',
		'oyc_manage_events',
		'oyc_manage_messages',
		'oyc_manage_members',
	);
}

/**
 * Register the Club Officer role and mirror the caps onto Administrator.
 * Runs once per version bump rather than on every load.
 */
function oyc_officer_register_roles() {
	if ( get_option( 'oyc_officer_roles_version' ) === OYC_OFFICER_ROLES_VERSION ) {
		return;
	}

	remove_role( 'oyc_officer' );

	$caps = array( 'read' => true );
	foreach ( oyc_officer_caps() as $cap ) {
		$caps[ $cap ] = true;
	}

	add_role( 'oyc_officer', __( 'Club Officer', 'orienta-yacht-club' ), $caps );

	// Administrators keep full reach over the officer area.
	$admin = get_role( 'administrator' );
	if ( $admin ) {
		foreach ( oyc_officer_caps() as $cap ) {
			$admin->add_cap( $cap );
		}
	}

	update_option( 'oyc_officer_roles_version', OYC_OFFICER_ROLES_VERSION );
}
add_action( 'init', 'oyc_officer_register_roles', 5 );

/* ─────────────────────────────────────────────────────────────────────────────
 * Audit log — every write the officer area performs is recorded.
 * ───────────────────────────────────────────────────────────────────────────── */

add_action( 'init', function () {
	register_post_type( 'oyc_audit', array(
		'label'           => 'Officer Audit Log',
		'labels'          => array(
			'name'          => 'Audit Log',
			'singular_name' => 'Audit Entry',
		),
		'public'          => false,
		'show_ui'         => false,
		'supports'        => array( 'title' ),
		'capability_type' => 'post',
		'map_meta_cap'    => true,
	) );
} );

/**
 * Record an officer action. Never blocks the calling action if logging fails.
 *
 * @param string $action    Short verb, e.g. 'user.delete'.
 * @param string $summary   Human-readable description.
 * @param int    $object_id Optional related object (user or post ID).
 */
function oyc_audit_log( $action, $summary, $object_id = 0 ) {
	$user = wp_get_current_user();

	$post_id = wp_insert_post( array(
		'post_type'   => 'oyc_audit',
		'post_status' => 'publish',
		'post_title'  => $action . ' — ' . $summary,
		'post_author' => $user->ID,
	), true );

	if ( is_wp_error( $post_id ) ) {
		return;
	}

	update_post_meta( $post_id, 'oyc_audit_action',    $action );
	update_post_meta( $post_id, 'oyc_audit_summary',   $summary );
	update_post_meta( $post_id, 'oyc_audit_object_id', (int) $object_id );
	update_post_meta( $post_id, 'oyc_audit_actor',     $user->user_login );
	update_post_meta( $post_id, 'oyc_audit_actor_id',  $user->ID );
	update_post_meta( $post_id, 'oyc_audit_at',        current_time( 'mysql' ) );
	update_post_meta( $post_id, 'oyc_audit_ip',        oyc_officer_client_ip() );
}

/**
 * Best-effort client IP for the audit trail. The site sits directly on its origin
 * (no CDN), so REMOTE_ADDR is the real client address.
 *
 * @return string
 */
function oyc_officer_client_ip() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
}
