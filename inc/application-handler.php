<?php
/**
 * OYC Membership Application Handler
 *
 * - Registers the `oyc_application` custom post type for storing submissions.
 * - Hooks into Contact Form 7 to save every submitted application as a CPT post.
 * - Adds admin list-table columns so staff can review submissions at a glance.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ──────────────────────────────────────────────
 * 1. Register custom post type: oyc_application
 * ────────────────────────────────────────────── */

add_action( 'init', function () {
	register_post_type( 'oyc_application', array(
		'label'               => 'Applications',
		'labels'              => array(
			'name'               => 'Membership Applications',
			'singular_name'      => 'Membership Application',
			'menu_name'          => 'Applications',
			'all_items'          => 'All Applications',
			'view_item'          => 'View Application',
			'search_items'       => 'Search Applications',
			'not_found'          => 'No applications found.',
			'not_found_in_trash' => 'No applications in trash.',
		),
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => false, // managed via OYC Inbox menu in admin-inbox.php
		'menu_position'       => 25,
		'supports'            => array( 'title', 'custom-fields' ),
		'capability_type'     => 'post',
		'map_meta_cap'        => true,
	) );
} );

/* ──────────────────────────────────────────────
 * 2. Save CF7 submission → oyc_application post
 * ────────────────────────────────────────────── */

/**
 * Save a membership application on mail_sent OR mail_failed so submissions are
 * never lost when the mail server is unavailable (e.g. local wp-now environment).
 */
function oyc_save_application( $contact_form ) {
	// Only capture the membership application form (by title)
	if ( strpos( strtolower( $contact_form->title() ), 'membership application' ) === false ) {
		return;
	}

	$submission = WPCF7_Submission::get_instance();
	if ( ! $submission ) {
		return;
	}

	$data = $submission->get_posted_data();

	// Build a readable title: "Last, First — date"
	$first = isset( $data['first-name'] ) ? sanitize_text_field( $data['first-name'] ) : '';
	$last  = isset( $data['last-name']  ) ? sanitize_text_field( $data['last-name']  ) : '';
	$title = trim( "$last, $first" ) ?: 'Application — ' . current_time( 'Y-m-d' );
	$title .= ' — ' . current_time( 'M j, Y' );

	$post_id = wp_insert_post( array(
		'post_title'  => $title,
		'post_type'   => 'oyc_application',
		'post_status' => 'publish',
		'post_author' => 1,
	) );

	if ( is_wp_error( $post_id ) ) {
		return;
	}

	// Map of field names → readable labels for postmeta
	$fields = array(
		'first-name'           => 'First Name',
		'last-name'            => 'Last Name',
		'address'              => 'Address',
		'city'                 => 'City',
		'state'                => 'State',
		'zip'                  => 'Zip',
		'email'                => 'Email',
		'mobile-phone'         => 'Mobile Phone',
		'home-phone'           => 'Home Phone',
		'family-names'         => 'Family Members',
		'employer'             => 'Employer',
		'join-reason'          => 'Principal Reason for Joining',
		'join-reason-other'    => 'Joining Reason (Other)',
		'hear-source'          => 'How They Heard About OYC',
		'hear-source-other'    => 'Source (Other)',
		'know-members'         => 'Knows Current Members',
		'know-members-who'     => 'Member Names Known',
		'other-club'           => 'Member of Another Club',
		'other-club-which'     => 'Other Club Name(s)',
		'previous-club'        => 'Previous Club Membership',
		'previous-club-details'=> 'Previous Club Details',
		'owns-boat'            => 'Currently Owns a Boat',
		'boat-description'     => 'Boat Description',
		'boating-experience'   => 'Boating Experience',
		'boating-duration'     => 'Years Boating',
		'boating-frequency'    => 'Boating Frequency',
		'boat-location'        => 'Base of Operations',
		'previous-boats'       => 'Previous Boats',
		'training-courses'     => 'Training Courses',
		'has-licenses'         => 'Holds Licenses/Certificates',
		'competence-rating'    => 'Competence Rating (1-10)',
		'skills-to-contribute' => 'Skills to Contribute',
	);

	foreach ( $fields as $field => $label ) {
		if ( isset( $data[ $field ] ) && $data[ $field ] !== '' ) {
			$value = is_array( $data[ $field ] )
				? implode( ', ', array_map( 'sanitize_text_field', $data[ $field ] ) )
				: sanitize_textarea_field( $data[ $field ] );
			update_post_meta( $post_id, 'oyc_app_' . str_replace( '-', '_', $field ), $value );
		}
	}

	// Store submission timestamp and remote IP for reference
	update_post_meta( $post_id, 'oyc_app_submitted_at', current_time( 'mysql' ) );
	update_post_meta( $post_id, 'oyc_app_remote_ip',    $submission->get_meta( 'remote_ip' ) ?? '' );

	// Notify the club of a new membership application.
	$app_email = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
	$app_phone = isset( $data['mobile-phone'] ) ? sanitize_text_field( $data['mobile-phone'] ) : '';
	$notify_to = oyc_inbox_email();
	$subject   = 'ORIENTA APPLICATION — ' . trim( "$first $last" );
	$lines     = array(
		'A new membership application was submitted via the website.',
		'',
		'Name: ' . trim( "$first $last" ),
		'Email: ' . $app_email,
		'Phone: ' . $app_phone,
		'',
		'View in the OYC Inbox: ' . admin_url( 'admin.php?page=oyc-applications' ),
	);
	$headers = array();
	if ( $app_email && is_email( $app_email ) ) {
		$headers[] = 'Reply-To: ' . trim( "$first $last" ) . ' <' . $app_email . '>';
	}
	wp_mail( $notify_to, $subject, implode( "\n", $lines ), $headers );
}

add_action( 'wpcf7_mail_sent',   'oyc_save_application' );
add_action( 'wpcf7_mail_failed', 'oyc_save_application' );

/* ──────────────────────────────────────────────
 * 3. Duplicate-email guard
 * ────────────────────────────────────────────── */

/**
 * Reject a membership application if an existing submission already uses
 * the same email address. Hooks into CF7's server-side validation so the
 * check runs after the AJAX call — after client-side SWV has already
 * confirmed the address is properly formatted.
 */
// Dedicated AJAX endpoint for the client-side duplicate-email check.
// Runs completely outside CF7's validation pipeline so it never interferes
// with form submission flow.
add_action( 'wp_ajax_nopriv_oyc_check_email', 'oyc_ajax_check_email' );
add_action( 'wp_ajax_oyc_check_email',        'oyc_ajax_check_email' );

function oyc_ajax_check_email() {
	$email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

	if ( ! is_email( $email ) ) {
		wp_send_json( array( 'duplicate' => false ) );
	}

	global $wpdb;
	$exists = $wpdb->get_var( $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta}
		  WHERE meta_key = 'oyc_app_email'
		    AND meta_value = %s
		  LIMIT 1",
		$email
	) );

	wp_send_json( array( 'duplicate' => (bool) $exists ) );
}

/**
 * Fetch the most recent membership application for a member, matched by email,
 * so the Edit Profile form can pre-fill fields the member already provided in
 * their application (no need to re-enter). Returns an empty array if none found.
 *
 * @param string $email Member email to match against the application.
 * @return array Map of profile fields → values.
 */
function oyc_get_member_application( $email ) {
	$email = sanitize_email( (string) $email );
	if ( ! $email ) {
		return array();
	}
	$ids = get_posts( array(
		'post_type'        => 'oyc_application',
		'post_status'      => 'publish',
		'numberposts'      => 1,
		'orderby'          => 'date',
		'order'            => 'DESC',
		'fields'           => 'ids',
		'meta_key'         => 'oyc_app_email',
		'meta_value'       => $email,
		'suppress_filters' => false,
	) );
	if ( empty( $ids ) ) {
		return array();
	}
	$id = (int) $ids[0];
	return array(
		'first_name'   => (string) get_post_meta( $id, 'oyc_app_first_name', true ),
		'last_name'    => (string) get_post_meta( $id, 'oyc_app_last_name', true ),
		'address'      => (string) get_post_meta( $id, 'oyc_app_address', true ),
		'city'         => (string) get_post_meta( $id, 'oyc_app_city', true ),
		'state'        => (string) get_post_meta( $id, 'oyc_app_state', true ),
		'zip'          => (string) get_post_meta( $id, 'oyc_app_zip', true ),
		'mobile_phone' => (string) get_post_meta( $id, 'oyc_app_mobile_phone', true ),
		'home_phone'   => (string) get_post_meta( $id, 'oyc_app_home_phone', true ),
	);
}

/* ──────────────────────────────────────────────
 * 4. Admin list columns for oyc_application
 * ────────────────────────────────────────────── */

add_filter( 'manage_oyc_application_posts_columns', function ( $columns ) {
	return array(
		'cb'             => $columns['cb'],
		'title'          => 'Applicant',
		'oyc_email'      => 'Email',
		'oyc_phone'      => 'Phone',
		'oyc_reason'     => 'Reason for Joining',
		'oyc_owns_boat'  => 'Owns Boat',
		'oyc_competence' => 'Competence',
		'oyc_submitted'  => 'Submitted',
	);
} );

add_action( 'manage_oyc_application_posts_custom_column', function ( $column, $post_id ) {
	switch ( $column ) {
		case 'oyc_email':
			$v = get_post_meta( $post_id, 'oyc_app_email', true );
			echo $v ? '<a href="mailto:' . esc_attr( $v ) . '">' . esc_html( $v ) . '</a>' : '—';
			break;
		case 'oyc_phone':
			echo esc_html( get_post_meta( $post_id, 'oyc_app_mobile_phone', true ) ?: '—' );
			break;
		case 'oyc_reason':
			echo esc_html( get_post_meta( $post_id, 'oyc_app_join_reason', true ) ?: '—' );
			break;
		case 'oyc_owns_boat':
			$v = get_post_meta( $post_id, 'oyc_app_owns_boat', true );
			echo $v ? '<span style="color:' . ( $v === 'Yes' ? '#27ae60' : '#888' ) . '">' . esc_html( $v ) . '</span>' : '—';
			break;
		case 'oyc_competence':
			$v = get_post_meta( $post_id, 'oyc_app_competence_rating', true );
			echo $v ? esc_html( $v ) . ' / 10' : '—';
			break;
		case 'oyc_submitted':
			$v = get_post_meta( $post_id, 'oyc_app_submitted_at', true );
			echo $v ? esc_html( date( 'M j, Y g:i a', strtotime( $v ) ) ) : esc_html( get_the_date( 'M j, Y', $post_id ) );
			break;
	}
}, 10, 2 );

// Make columns sortable
add_filter( 'manage_edit-oyc_application_sortable_columns', function ( $columns ) {
	$columns['oyc_submitted'] = 'date';
	return $columns;
} );

// Default sort: newest first
add_action( 'pre_get_posts', function ( $query ) {
	if ( is_admin() && $query->get( 'post_type' ) === 'oyc_application' && $query->is_main_query() ) {
		if ( ! $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'date' );
			$query->set( 'order',   'DESC' );
		}
	}
} );
