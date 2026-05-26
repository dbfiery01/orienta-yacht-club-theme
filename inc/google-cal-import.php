<?php
/**
 * Google Calendar → The Events Calendar auto-importer.
 *
 * Fetches the club's public Google Calendar .ics feed on a daily WP-Cron
 * schedule and upserts events into The Events Calendar (TEC).
 *
 * HOW TO ACTIVATE
 * ---------------
 * 1. Open your Google Calendar.
 * 2. Beside the calendar name click ⋮ → Settings and sharing.
 * 3. Scroll to "Integrate calendar" and copy the "Secret address in iCal format"
 *    (or the public iCal URL if the calendar is public).
 * 4. In WordPress: Appearance → Customize  OR  paste the URL directly into
 *    the OYC_GCAL_ICS_URL constant below (between the single quotes):
 *
 *       define( 'OYC_GCAL_ICS_URL', 'https://calendar.google.com/calendar/ical/...' );
 *
 * 5. Save. The import runs once per day automatically; to run it immediately
 *    visit: /wp-admin/admin.php?oyc_gcal_import=1
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ── 1. Configuration ─────────────────────────────────────── */

/**
 * Paste your Google Calendar .ics URL here (replace the empty string).
 * You can also define this in wp-config.php to keep it out of version control.
 */
if ( ! defined( 'OYC_GCAL_ICS_URL' ) ) {
	define( 'OYC_GCAL_ICS_URL', '' ); // ← paste your .ics URL here
}

/* How far ahead (days) to import events. */
if ( ! defined( 'OYC_GCAL_LOOKAHEAD_DAYS' ) ) {
	define( 'OYC_GCAL_LOOKAHEAD_DAYS', 365 );
}

/* ── 2. Cron schedule ─────────────────────────────────────── */

add_action( 'wp', 'oyc_gcal_schedule_cron' );
function oyc_gcal_schedule_cron() {
	if ( ! wp_next_scheduled( 'oyc_gcal_import_cron' ) ) {
		wp_schedule_event( time(), 'daily', 'oyc_gcal_import_cron' );
	}
}
add_action( 'oyc_gcal_import_cron', 'oyc_gcal_do_import' );

/* ── 3. Manual trigger via query string (admin only) ──────── */

add_action( 'init', function () {
	if ( isset( $_GET['oyc_gcal_import'] ) && current_user_can( 'manage_options' ) ) {
		$result = oyc_gcal_do_import();
		wp_die( 'Google Calendar import complete. ' . intval( $result ) . ' event(s) created/updated.
<br><a href="' . admin_url( 'edit.php?post_type=tribe_events' ) . '">View events →</a>' );
	}
} );

/* ── 4. Core import function ──────────────────────────────── */

function oyc_gcal_do_import() {
	$ics_url = OYC_GCAL_ICS_URL;
	if ( ! $ics_url ) {
		return 0; // Not configured yet
	}

	if ( ! class_exists( 'Tribe__Events__Main' ) ) {
		return 0; // TEC not active
	}

	$response = wp_remote_get( $ics_url, [ 'timeout' => 30 ] );
	if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
		error_log( 'OYC Google Cal import error: ' . ( is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_response_code( $response ) ) );
		return 0;
	}

	$ics_data = wp_remote_retrieve_body( $response );
	$events   = oyc_parse_ics( $ics_data );
	$count    = 0;

	$cutoff = current_time( 'timestamp' ) + ( OYC_GCAL_LOOKAHEAD_DAYS * DAY_IN_SECONDS );

	foreach ( $events as $ev ) {
		if ( $ev['start_ts'] > $cutoff ) {
			continue; // Too far ahead
		}
		if ( $ev['start_ts'] < strtotime( '-1 day' ) ) {
			continue; // Already past
		}
		oyc_upsert_tec_event( $ev );
		$count++;
	}

	set_transient( 'oyc_gcal_last_import', current_time( 'mysql' ), WEEK_IN_SECONDS );
	return $count;
}

/* ── 5. Minimal iCal parser ───────────────────────────────── */

function oyc_parse_ics( $ics ) {
	// Unfold long lines
	$ics   = preg_replace( '/\r\n[ \t]/', '', $ics );
	$lines = preg_split( '/\r\n|\n|\r/', $ics );

	$events    = [];
	$current   = null;
	$in_event  = false;

	foreach ( $lines as $line ) {
		$line = trim( $line );
		if ( $line === 'BEGIN:VEVENT' ) {
			$in_event = true;
			$current  = [];
			continue;
		}
		if ( $line === 'END:VEVENT' ) {
			$in_event = false;
			if ( $current && isset( $current['start_ts'], $current['title'] ) ) {
				$events[] = $current;
			}
			$current = null;
			continue;
		}
		if ( ! $in_event ) continue;

		[ $key, $value ] = array_pad( explode( ':', $line, 2 ), 2, '' );

		// Strip TZID or VALUE params from key
		$key = strtoupper( preg_replace( '/;.*$/', '', $key ) );
		$value = stripcslashes( $value );

		switch ( $key ) {
			case 'SUMMARY':
				$current['title'] = $value;
				break;
			case 'DESCRIPTION':
				$current['description'] = $value;
				break;
			case 'LOCATION':
				$current['location'] = $value;
				break;
			case 'DTSTART':
				$current['start_ts'] = oyc_ics_to_ts( $value );
				$current['all_day']  = ( strlen( $value ) === 8 );
				break;
			case 'DTEND':
				$current['end_ts'] = oyc_ics_to_ts( $value );
				break;
			case 'UID':
				$current['uid'] = $value;
				break;
		}
	}
	return $events;
}

function oyc_ics_to_ts( $value ) {
	$value = preg_replace( '/[^0-9TZ]/', '', $value );
	if ( strlen( $value ) === 8 ) {
		// All-day: YYYYMMDD
		return mktime( 0, 0, 0, substr( $value, 4, 2 ), substr( $value, 6, 2 ), substr( $value, 0, 4 ) );
	}
	// Datetime: YYYYMMDDTHHMMSSZ
	return strtotime( $value );
}

/* ── 6. Upsert into The Events Calendar ───────────────────── */

function oyc_upsert_tec_event( $ev ) {
	$uid       = sanitize_text_field( $ev['uid'] ?? '' );
	$title     = sanitize_text_field( $ev['title'] );
	$desc      = sanitize_textarea_field( $ev['description'] ?? '' );
	$location  = sanitize_text_field( $ev['location'] ?? '' );
	$start_ts  = $ev['start_ts'];
	$end_ts    = $ev['end_ts'] ?? ( $start_ts + 3600 );
	$all_day   = ! empty( $ev['all_day'] );

	$start_date = date( 'Y-m-d H:i:s', $start_ts );
	$end_date   = date( 'Y-m-d H:i:s', $end_ts );

	// Check for existing event by UID (stored in postmeta)
	$existing = null;
	if ( $uid ) {
		$query = new WP_Query( [
			'post_type'      => 'tribe_events',
			'post_status'    => 'any',
			'meta_key'       => '_oyc_gcal_uid',
			'meta_value'     => $uid,
			'posts_per_page' => 1,
		] );
		if ( $query->have_posts() ) {
			$existing = $query->posts[0]->ID;
		}
	}

	$post_data = [
		'post_title'   => $title,
		'post_content' => $desc,
		'post_status'  => 'publish',
		'post_type'    => 'tribe_events',
	];

	if ( $existing ) {
		$post_data['ID'] = $existing;
		$post_id = wp_update_post( $post_data );
	} else {
		$post_id = wp_insert_post( $post_data );
	}

	if ( ! $post_id || is_wp_error( $post_id ) ) {
		return;
	}

	// TEC event meta
	update_post_meta( $post_id, '_EventStartDate',    $start_date );
	update_post_meta( $post_id, '_EventEndDate',      $end_date );
	update_post_meta( $post_id, '_EventStartDateUTC', gmdate( 'Y-m-d H:i:s', $start_ts ) );
	update_post_meta( $post_id, '_EventEndDateUTC',   gmdate( 'Y-m-d H:i:s', $end_ts ) );
	update_post_meta( $post_id, '_EventAllDay',       $all_day ? 'yes' : 'no' );
	update_post_meta( $post_id, '_EventTimezone',     'America/New_York' );
	update_post_meta( $post_id, '_EventDuration',     $end_ts - $start_ts );
	update_post_meta( $post_id, '_EventCurrencySymbol', '' );
	update_post_meta( $post_id, '_EventCost',         '' );
	update_post_meta( $post_id, '_oyc_gcal_uid',      $uid );

	// Venue (location string)
	if ( $location ) {
		update_post_meta( $post_id, '_EventVenueID', 0 );
		update_post_meta( $post_id, '_EventLocation', $location );
	}
}

/* ── 7. Admin notice when not configured ──────────────────── */

add_action( 'admin_notices', function () {
	$screen = get_current_screen();
	if ( ! $screen || $screen->post_type !== 'tribe_events' ) return;

	if ( OYC_GCAL_ICS_URL ) {
		$last = get_transient( 'oyc_gcal_last_import' );
		echo '<div class="notice notice-success"><p>';
		echo '<strong>OYC Google Calendar sync is active.</strong> ';
		echo $last ? 'Last import: ' . esc_html( $last ) . '. ' : '';
		echo '<a href="' . esc_url( admin_url( 'edit.php?post_type=tribe_events&oyc_gcal_import=1' ) ) . '">Run import now →</a>';
		echo '</p></div>';
		return;
	}

	echo '<div class="notice notice-warning"><p>';
	echo '<strong>OYC Google Calendar not connected.</strong> ';
	echo 'Open <code>inc/google-cal-import.php</code> in your theme and paste your Google Calendar .ics URL into <code>OYC_GCAL_ICS_URL</code>. ';
	echo '<a href="https://support.google.com/calendar/answer/37648" target="_blank">How to get your .ics URL →</a>';
	echo '</p></div>';
} );
