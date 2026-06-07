<?php
/**
 * One-time tooling for seeding US federal holidays (2026–2027) into the
 * Calendarize it! calendar (CPT `events`). Admin-only. Remove after use.
 *
 * Modes (visit as an administrator):
 *   /wp-admin/?oyc_hol=dump&id=4771   → dump a post's meta (to learn the schema)
 *   /wp-admin/?oyc_hol=clean          → delete previously-seeded holidays + reset flag
 *   /wp-admin/?oyc_hol=seed[&tpl=ID]  → create the holidays (clones a working all-day
 *                                       event and retargets every date string)
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function oyc_holiday_list() {
	return array(
		array( '2026-01-01', "New Year's Day" ), array( '2026-01-19', 'Martin Luther King Jr. Day' ),
		array( '2026-02-16', "Presidents' Day" ), array( '2026-05-25', 'Memorial Day' ),
		array( '2026-06-19', 'Juneteenth' ), array( '2026-07-04', 'Independence Day' ),
		array( '2026-09-07', 'Labor Day' ), array( '2026-10-12', 'Columbus Day' ),
		array( '2026-11-11', 'Veterans Day' ), array( '2026-11-26', 'Thanksgiving Day' ),
		array( '2026-12-25', 'Christmas Day' ),
		array( '2027-01-01', "New Year's Day" ), array( '2027-01-18', 'Martin Luther King Jr. Day' ),
		array( '2027-02-15', "Presidents' Day" ), array( '2027-05-31', 'Memorial Day' ),
		array( '2027-06-19', 'Juneteenth' ), array( '2027-07-04', 'Independence Day' ),
		array( '2027-09-06', 'Labor Day' ), array( '2027-10-11', 'Columbus Day' ),
		array( '2027-11-11', 'Veterans Day' ), array( '2027-11-25', 'Thanksgiving Day' ),
		array( '2027-12-25', 'Christmas Day' ),
	);
}

add_action( 'admin_init', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$mode = isset( $_GET['oyc_hol'] ) ? sanitize_key( $_GET['oyc_hol'] ) : '';
	if ( ! $mode ) {
		return;
	}

	/* ---- DUMP ---- */
	if ( 'dump' === $mode ) {
		$id  = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$raw = get_post_meta( $id );
		$out = array();
		foreach ( $raw as $k => $vals ) {
			$out[ $k ] = array_map( 'maybe_unserialize', $vals );
		}
		$terms = wp_get_object_terms( $id, 'calendar', array( 'fields' => 'ids' ) );
		wp_die( '<h3>Meta for #' . $id . ' (calendar terms: ' . esc_html( implode( ',', (array) $terms ) ) . ')</h3><pre style="white-space:pre-wrap">' . esc_html( var_export( $out, true ) ) . '</pre>' );
	}

	/* ---- CLEAN: delete events that exactly match a holiday date+title ---- */
	if ( 'clean' === $mode ) {
		$pairs = array();
		foreach ( oyc_holiday_list() as $h ) {
			$pairs[ $h[0] . '|' . $h[1] ] = true;
		}
		$ids = get_posts( array( 'post_type' => 'events', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ) );
		$deleted = 0;
		foreach ( $ids as $pid ) {
			$key = substr( (string) get_post_meta( $pid, 'fc_start', true ), 0, 10 ) . '|' . get_the_title( $pid );
			if ( isset( $pairs[ $key ] ) || get_post_meta( $pid, '_oyc_holiday_seed', true ) ) {
				wp_delete_post( $pid, true );
				$deleted++;
			}
		}
		delete_option( 'oyc_holidays_seeded_2026_2027' );
		wp_die( 'Deleted ' . $deleted . ' seeded holiday events and reset the flag.' );
	}

	/* ---- SEED ---- */
	if ( 'seed' === $mode ) {
		if ( get_option( 'oyc_holidays_seeded_2026_2027' ) ) {
			wp_die( 'Already seeded. Run ?oyc_hol=clean first to redo.' );
		}

		$tpl_id = isset( $_GET['tpl'] ) ? (int) $_GET['tpl'] : 0;
		if ( ! $tpl_id ) {
			$cands = get_posts( array( 'post_type' => 'events', 'post_status' => 'publish', 'posts_per_page' => 100, 'fields' => 'ids', 'meta_key' => 'fc_start' ) );
			foreach ( $cands as $cid ) {
				$fs = (string) get_post_meta( $cid, 'fc_start', true );
				$rr = (string) get_post_meta( $cid, 'fc_rrule', true );
				if ( '' !== $fs && strlen( $fs ) <= 10 && '' === $rr ) { $tpl_id = $cid; break; }
			}
		}
		if ( ! $tpl_id ) {
			wp_die( 'No single all-day template event found. Pass ?oyc_hol=seed&tpl=ID.' );
		}

		$tpl_meta  = get_post_meta( $tpl_id );
		$tpl_start = (string) get_post_meta( $tpl_id, 'fc_start', true );
		$tpl_end   = (string) get_post_meta( $tpl_id, 'fc_end', true );
		$find      = array( $tpl_start, $tpl_end, str_replace( '-', '', $tpl_start ), str_replace( '-', '', $tpl_end ) );
		$tpl_terms = wp_get_object_terms( $tpl_id, 'calendar', array( 'fields' => 'ids' ) );
		$skip      = array( '_edit_lock', '_edit_last', '_wp_old_slug', '_thumbnail_id' );

		$made = 0; $log = array();
		foreach ( oyc_holiday_list() as $h ) {
			list( $date, $title ) = $h;
			$repl = array( $date, $date, str_replace( '-', '', $date ), str_replace( '-', '', $date ) );

			$pid = wp_insert_post( array( 'post_type' => 'events', 'post_status' => 'publish', 'post_title' => $title, 'post_content' => '' ) );
			if ( ! $pid || is_wp_error( $pid ) ) { $log[] = 'FAILED ' . $title; continue; }

			foreach ( $tpl_meta as $key => $values ) {
				if ( in_array( $key, $skip, true ) ) { continue; }
				delete_post_meta( $pid, $key );
				foreach ( $values as $v ) {
					$val = maybe_unserialize( $v );
					if ( is_string( $val ) ) {
						$val = str_replace( $find, $repl, $val );
					}
					add_post_meta( $pid, $key, $val );
				}
			}
			update_post_meta( $pid, 'fc_start', $date );
			update_post_meta( $pid, 'fc_end', $date );
			update_post_meta( $pid, '_oyc_holiday_seed', 1 );
			if ( ! empty( $tpl_terms ) ) {
				wp_set_object_terms( $pid, array_map( 'intval', $tpl_terms ), 'calendar' );
			}

			$made++; $log[] = $title . ' — ' . $date . ' (#' . $pid . ')';
		}

		update_option( 'oyc_holidays_seeded_2026_2027', 1 );
		wp_die( 'Seeded ' . $made . ' holidays from template #' . (int) $tpl_id . ' (start ' . esc_html( $tpl_start ) . '):<br>' . implode( '<br>', array_map( 'esc_html', $log ) ) );
	}
} );
