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

	return $pid;
}

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

	// Diagnostic: surface the Calendarize it! storage (custom table?) + save hooks.
	register_rest_route( 'oyc/v1', '/cal-debug', array(
		'methods'             => 'GET',
		'callback'            => 'oyc_rest_cal_debug',
		'permission_callback' => function () { return current_user_can( 'manage_options' ); },
	) );
} );

function oyc_rest_cal_debug( $req ) {
	global $wpdb, $wp_filter;
	$tables = $wpdb->get_col( 'SHOW TABLES' );
	$cal    = array_values( array_filter( $tables, function ( $t ) {
		return preg_match( '/cal|fc_|rhc|event/i', $t );
	} ) );
	$hooks = array();
	foreach ( array( 'save_post', 'save_post_events' ) as $h ) {
		if ( empty( $wp_filter[ $h ] ) ) { continue; }
		foreach ( $wp_filter[ $h ]->callbacks as $cbs ) {
			foreach ( $cbs as $cb ) {
				$f = $cb['function'];
				if ( is_array( $f ) ) {
					$hooks[ $h ][] = ( is_object( $f[0] ) ? get_class( $f[0] ) : $f[0] ) . '::' . $f[1];
				} elseif ( is_string( $f ) ) {
					$hooks[ $h ][] = $f;
				} else {
					$hooks[ $h ][] = 'Closure';
				}
			}
		}
	}
	$classes = array_values( array_filter( get_declared_classes(), function ( $c ) {
		return preg_match( '/rhc|calendariz|fc_event|event_dates|fervens/i', $c );
	} ) );
	$funcs = array_values( array_filter( get_defined_functions()['user'], function ( $fn ) {
		return preg_match( '/rhc_|calendariz|rebuild.*event|event.*rebuild|fc_save|save_event/i', $fn );
	} ) );
	return array(
		'cal_tables' => $cal,
		'save_hooks' => $hooks,
		'classes'    => array_slice( $classes, 0, 50 ),
		'funcs'      => array_slice( $funcs, 0, 60 ),
	);
}

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
	return array( 'deleted' => true, 'id' => $id, 'title' => $title );
}
