<?php
/**
 * Dynamic iCal feed for the club calendar.
 *
 * Serves a live .ics at  /?oyc_calendar_ics=1  (add &download=1 to force a
 * file download instead of an inline subscribe feed). Events are pulled live
 * from Calendarize it! via its own front-end endpoint, so the feed always
 * reflects whatever admins have entered — no static snapshot.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'template_redirect', 'oyc_calendar_ics_feed', 0 );
function oyc_calendar_ics_feed() {
	if ( ! isset( $_GET['oyc_calendar_ics'] ) ) {
		return;
	}

	// Pull live events from Calendarize it! (loopback to its public endpoint).
	$start = strtotime( '-1 year' );
	$end   = strtotime( '+2 years' );
	$src   = home_url( '/?rhc_action=get_calendar_events&post_type[]=events&start=' . $start . '&end=' . $end );

	$events = array();
	$resp   = wp_remote_get( $src, array( 'timeout' => 20, 'sslverify' => false ) );
	if ( ! is_wp_error( $resp ) ) {
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( ! empty( $data['EVENTS'] ) && is_array( $data['EVENTS'] ) ) {
			$events = $data['EVENTS'];
		}
	}

	$download = isset( $_GET['download'] );
	nocache_headers();
	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: ' . ( $download ? 'attachment' : 'inline' ) . '; filename="orienta-yacht-club.ics"' );

	$esc = function ( $s ) {
		$s = wp_strip_all_tags( (string) $s );
		$s = str_replace( array( "\r\n", "\n", "\r" ), '\\n', $s );
		return preg_replace( '/([,;\\\\])/', '\\\\$1', $s );
	};

	$lines = array(
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//Orienta Yacht Club//Calendar//EN',
		'CALSCALE:GREGORIAN',
		'METHOD:PUBLISH',
		'X-WR-CALNAME:Orienta Yacht Club',
		'NAME:Orienta Yacht Club',
		'X-WR-TIMEZONE:America/New_York',
	);

	foreach ( $events as $ev ) {
		$title = isset( $ev['title'] ) ? $ev['title'] : '';
		if ( '' === $title ) {
			continue;
		}
		$start_str = ! empty( $ev['start'] ) ? $ev['start'] : ( ! empty( $ev['fc_start'] ) ? $ev['fc_start'] . ' 00:00:00' : '' );
		if ( ! $start_str ) {
			continue;
		}
		$ts     = strtotime( $start_str );
		$allday = ! empty( $ev['allDay'] );
		$uid    = ( isset( $ev['local_id'] ) ? $ev['local_id'] : substr( md5( $title . $start_str ), 0, 12 ) ) . '@orientayachtclub.com';

		$lines[] = 'BEGIN:VEVENT';
		$lines[] = 'UID:' . $uid;
		$lines[] = 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' );
		if ( $allday ) {
			$lines[] = 'DTSTART;VALUE=DATE:' . date( 'Ymd', $ts );
			$lines[] = 'DTEND;VALUE=DATE:' . date( 'Ymd', strtotime( '+1 day', $ts ) );
		} else {
			$end_str = ! empty( $ev['end'] ) ? $ev['end'] : $start_str;
			$lines[] = 'DTSTART:' . date( 'Ymd\THis', $ts );
			$lines[] = 'DTEND:' . date( 'Ymd\THis', strtotime( $end_str ) );
		}
		$lines[] = 'SUMMARY:' . $esc( $title );
		if ( ! empty( $ev['description'] ) ) {
			$lines[] = 'DESCRIPTION:' . $esc( $ev['description'] );
		}
		if ( ! empty( $ev['url'] ) ) {
			$lines[] = 'URL:' . $esc( $ev['url'] );
		}
		$lines[] = 'END:VEVENT';
	}

	$lines[] = 'END:VCALENDAR';
	echo implode( "\r\n", $lines ) . "\r\n";
	exit;
}
