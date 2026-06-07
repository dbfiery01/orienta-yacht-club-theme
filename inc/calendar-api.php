<?php
/**
 * OYC Calendar API — create / list / delete Calendarize it! events (CPT `events`)
 * with the full set of meta the plugin's calendar query needs. Lets the calendar
 * be managed programmatically (e.g. from an admin assistant) without the admin UI.
 *
 * REST (auth: logged-in admin via cookie + X-WP-Nonce):
 *   GET    /wp-json/oyc/v1/events                 → list events
 *   POST   /wp-json/oyc/v1/events                 → create  {title,start,end,allday,calendar[]}
 *   DELETE /wp-json/oyc/v1/events/<id>            → delete an event
 *
 * Dates: "YYYY-MM-DD" (all-day) or "YYYY-MM-DD HH:MM:SS" (timed).
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create a Calendarize it! event with all required meta. Returns post ID or WP_Error.
 *
 * @param array $args title, start, end, allday(bool), calendar(array of term ids), status.
 */
function oyc_create_calendar_event( $args ) {
	$d = wp_parse_args( $args, array(
		'title'    => '',
		'start'    => '',
		'end'      => '',
		'allday'   => true,
		'calendar' => array(),
		'status'   => 'publish',
		'content'  => '',
	) );

	if ( '' === trim( (string) $d['title'] ) || '' === trim( (string) $d['start'] ) ) {
		return new WP_Error( 'oyc_missing', 'title and start are required' );
	}

	$start_date = substr( (string) $d['start'], 0, 10 );
	$end_date   = $d['end'] ? substr( (string) $d['end'], 0, 10 ) : $start_date;
	$allday     = ! empty( $d['allday'] );

	if ( $allday ) {
		$start_time = '';
		$end_time   = '';
		$start_dt   = $start_date . ' 00:00:00';
		$end_dt     = $end_date . ' 00:00:00';
	} else {
		$start_time = trim( substr( (string) $d['start'], 11 ) );
		$start_time = $start_time ? $start_time : '00:00:00';
		$end_time   = $d['end'] ? trim( substr( (string) $d['end'], 11 ) ) : $start_time;
		$end_time   = $end_time ? $end_time : $start_time;
		$start_dt   = $start_date . ' ' . $start_time;
		$end_dt     = $end_date . ' ' . $end_time;
	}

	$pid = wp_insert_post( array(
		'post_type'    => 'events',
		'post_status'  => $d['status'],
		'post_title'   => $d['title'],
		'post_content' => $d['content'],
	) );
	if ( ! $pid || is_wp_error( $pid ) ) {
		return $pid ? $pid : new WP_Error( 'oyc_insert_failed', 'wp_insert_post failed' );
	}

	$meta = array(
		'fc_allday'         => $allday ? '1' : '',
		'fc_start'          => $start_date,
		'fc_end'            => $end_date,
		'fc_start_time'     => $start_time,
		'fc_end_time'       => $end_time,
		'fc_start_datetime' => $start_dt,
		'fc_end_datetime'   => $end_dt,
		'fc_range_start'    => $start_dt,
		'fc_range_end'      => $end_dt,
		'fc_color'          => '#',
		'fc_text_color'     => '#',
		'fc_click_link'     => 'view',
		'fc_click_target'   => '_blank',
		'fc_event_map'      => '',
		'fc_interval'       => '',
		'fc_rrule'          => '',
		'fc_end_interval'   => '',
		'fc_dow_except'     => '',
		'fc_exdate'         => '',
		'fc_rdate'          => '',
	);
	foreach ( $meta as $k => $v ) {
		update_post_meta( $pid, $k, $v );
	}

	if ( ! empty( $d['calendar'] ) ) {
		wp_set_object_terms( $pid, array_map( 'intval', (array) $d['calendar'] ), 'calendar' );
	}

	// Write Calendarize it!'s occurrence-index row — the calendar queries this
	// table (wp_rhc_events), not post meta. Then clear its cache.
	oyc_rhc_index_event( $pid, $start_dt, $end_dt, $allday );

	return $pid;
}

/**
 * Insert/refresh the Calendarize it! occurrence-index row for an event and
 * clear the calendar cache so the change is visible immediately.
 */
function oyc_rhc_index_event( $post_id, $start_dt, $end_dt, $allday ) {
	global $wpdb;
	$tbl = $wpdb->prefix . 'rhc_events';
	$wpdb->delete( $tbl, array( 'post_id' => $post_id ) );
	$wpdb->insert( $tbl, array(
		'event_start' => $start_dt,
		'event_end'   => $end_dt,
		'post_id'     => $post_id,
		'allday'      => $allday ? '1' : '0',
		'number'      => 0,
	) );
	oyc_rhc_clear_cache();
}

function oyc_rhc_clear_cache() {
	global $wpdb;
	if ( function_exists( 'delete_get_calendar_cache' ) ) {
		delete_get_calendar_cache();
	} else {
		$wpdb->query( "DELETE FROM {$wpdb->prefix}rhc_cache" );
	}
	oyc_bump_cal_rev();
}

/**
 * Calendar revision token. The calendar page appends it to the feed URL so a
 * change produces a brand-new URL that no browser/proxy has cached (the feed
 * itself is sent with an 8-hour max-age by Calendarize it!). Unchanged → same
 * URL → still cached/fast.
 */
function oyc_bump_cal_rev() {
	update_option( 'oyc_cal_rev', time() );
}
function oyc_cal_rev() {
	return (int) get_option( 'oyc_cal_rev', 1 );
}
// Bump on admin event edits too (not just the API).
add_action( 'save_post_events', 'oyc_bump_cal_rev' );
add_action( 'before_delete_post', function ( $id ) {
	if ( 'events' === get_post_type( $id ) ) { oyc_bump_cal_rev(); }
} );

add_action( 'rest_api_init', function () {
	$can_edit   = function () { return current_user_can( 'edit_posts' ); };
	$can_delete = function () { return current_user_can( 'delete_posts' ); };

	register_rest_route( 'oyc/v1', '/events', array(
		array( 'methods' => 'GET',  'callback' => 'oyc_rest_list_events',   'permission_callback' => $can_edit ),
		array( 'methods' => 'POST', 'callback' => 'oyc_rest_create_event',  'permission_callback' => $can_edit ),
	) );
	register_rest_route( 'oyc/v1', '/events/(?P<id>\d+)', array(
		'methods'             => 'DELETE',
		'callback'            => 'oyc_rest_delete_event',
		'permission_callback' => $can_delete,
	) );
} );


function oyc_rest_list_events( $req ) {
	$ids = get_posts( array( 'post_type' => 'events', 'post_status' => 'any', 'posts_per_page' => 300, 'fields' => 'ids', 'orderby' => 'meta_value', 'meta_key' => 'fc_start', 'order' => 'ASC' ) );
	$out = array();
	foreach ( $ids as $id ) {
		$out[] = array(
			'id'     => $id,
			'title'  => get_the_title( $id ),
			'start'  => get_post_meta( $id, 'fc_start', true ),
			'end'    => get_post_meta( $id, 'fc_end', true ),
			'allday' => get_post_meta( $id, 'fc_allday', true ),
			'status' => get_post_status( $id ),
		);
	}
	return $out;
}

function oyc_rest_create_event( $req ) {
	$id = oyc_create_calendar_event( (array) $req->get_json_params() );
	if ( is_wp_error( $id ) ) {
		return new WP_REST_Response( array( 'error' => $id->get_error_message() ), 400 );
	}
	return new WP_REST_Response( array(
		'id'    => $id,
		'title' => get_the_title( $id ),
		'start' => get_post_meta( $id, 'fc_start', true ),
	), 201 );
}

function oyc_rest_delete_event( $req ) {
	$id = (int) $req['id'];
	if ( 'events' !== get_post_type( $id ) ) {
		return new WP_REST_Response( array( 'error' => 'Not an event' ), 404 );
	}
	$title = get_the_title( $id );
	wp_delete_post( $id, true );
	global $wpdb;
	$wpdb->delete( $wpdb->prefix . 'rhc_events', array( 'post_id' => $id ) );
	oyc_rhc_clear_cache();
	return array( 'deleted' => true, 'id' => $id, 'title' => $title );
}
