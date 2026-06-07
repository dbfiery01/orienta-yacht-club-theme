<?php
/**
 * One-time seeder: US federal holidays (2026–2027) into the Calendarize it!
 * calendar (CPT `events`).
 *
 * Trigger (admin only):  /wp-admin/?oyc_seed_holidays=1
 * Runs once — guarded by the `oyc_holidays_seeded_2026_2027` option. Each new
 * event is created by cloning an existing all-day event's meta (so the
 * Calendarize it! schema is reproduced exactly) and overriding the date/title.
 *
 * Safe to delete this file (and its require in functions.php) after seeding.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_init', function () {
	if ( empty( $_GET['oyc_seed_holidays'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( get_option( 'oyc_holidays_seeded_2026_2027' ) ) {
		wp_die( 'Holidays already seeded — nothing to do. (Delete the option oyc_holidays_seeded_2026_2027 to re-run.)' );
	}

	// US federal holidays, observed dates. All-day, single day.
	$holidays = array(
		array( '2026-01-01', 'New Year\'s Day' ),
		array( '2026-01-19', 'Martin Luther King Jr. Day' ),
		array( '2026-02-16', 'Presidents\' Day' ),
		array( '2026-05-25', 'Memorial Day' ),
		array( '2026-06-19', 'Juneteenth' ),
		array( '2026-07-04', 'Independence Day' ),
		array( '2026-09-07', 'Labor Day' ),
		array( '2026-10-12', 'Columbus Day' ),
		array( '2026-11-11', 'Veterans Day' ),
		array( '2026-11-26', 'Thanksgiving Day' ),
		array( '2026-12-25', 'Christmas Day' ),
		array( '2027-01-01', 'New Year\'s Day' ),
		array( '2027-01-18', 'Martin Luther King Jr. Day' ),
		array( '2027-02-15', 'Presidents\' Day' ),
		array( '2027-05-31', 'Memorial Day' ),
		array( '2027-06-19', 'Juneteenth' ),
		array( '2027-07-04', 'Independence Day' ),
		array( '2027-09-06', 'Labor Day' ),
		array( '2027-10-11', 'Columbus Day' ),
		array( '2027-11-11', 'Veterans Day' ),
		array( '2027-11-25', 'Thanksgiving Day' ),
		array( '2027-12-25', 'Christmas Day' ),
	);

	// Find a template: an existing all-day `events` post (fc_start has no time part).
	$candidates = get_posts( array(
		'post_type'      => 'events',
		'post_status'    => 'publish',
		'posts_per_page' => 50,
		'fields'         => 'ids',
		'meta_key'       => 'fc_start',
	) );
	$tpl_id = 0;
	foreach ( $candidates as $cid ) {
		$fs = (string) get_post_meta( $cid, 'fc_start', true );
		if ( $fs !== '' && strlen( $fs ) <= 10 ) { // "Y-m-d" => all-day
			$tpl_id = $cid;
			break;
		}
	}
	if ( ! $tpl_id && $candidates ) {
		$tpl_id = $candidates[0];
	}
	if ( ! $tpl_id ) {
		wp_die( 'No existing Calendarize it! event found to use as a template — cannot infer the meta schema.' );
	}

	$tpl_meta = get_post_meta( $tpl_id );
	$skip     = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_thumbnail_id' );

	$made = 0;
	$log  = array();
	foreach ( $holidays as $h ) {
		list( $date, $title ) = $h;

		$pid = wp_insert_post( array(
			'post_type'    => 'events',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_content' => '',
		) );
		if ( ! $pid || is_wp_error( $pid ) ) {
			$log[] = 'FAILED: ' . $title . ' ' . $date;
			continue;
		}

		// Clone the template's meta so the Calendarize it! schema is exact.
		foreach ( $tpl_meta as $key => $values ) {
			if ( in_array( $key, $skip, true ) ) {
				continue;
			}
			delete_post_meta( $pid, $key );
			foreach ( $values as $v ) {
				add_post_meta( $pid, $key, maybe_unserialize( $v ) );
			}
		}

		// Override the date for this holiday (single all-day event).
		update_post_meta( $pid, 'fc_start', $date );
		update_post_meta( $pid, 'fc_end', $date );

		$made++;
		$log[] = $title . ' — ' . $date . ' (#' . $pid . ')';
	}

	update_option( 'oyc_holidays_seeded_2026_2027', 1 );
	wp_die( 'Seeded ' . $made . ' holiday events (template #' . (int) $tpl_id . '):<br>' . implode( '<br>', array_map( 'esc_html', $log ) ) );
} );
