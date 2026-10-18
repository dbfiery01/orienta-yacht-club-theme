<?php
/**
 * Weather data warmer.
 *
 * Keeps the board's always-loaded Open-Meteo / NOAA readings hot in the
 * transient cache via WP-Cron, so a visitor hits warm cache (<0.1s) instead of
 * the 2-5s live upstream fetch that dominated the board's load profile. Covers
 * the local wind grid, the Buoy-42 marine reading (wind + waves), the live buoy
 * observation, the sitewide header glyph, and (slowly, a chunk at a time) the
 * Atlantic basin wind field.
 *
 * WP-Cron fires on site traffic, in a background request after the page is sent,
 * so the warming fetch never blocks a visitor — the one whose visit triggers the
 * run doesn't wait, and the next visitor reads warm cache. Transients live 15
 * min; refreshing every 5 min means they effectively never expire under normal
 * traffic, which also collapses the per-visitor upstream calls into one shared
 * fetch (and sidesteps the Open-Meteo 429s the per-request fetches were tripping).
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* A 5-minute cron cadence (transients are 15 min, so a 5-min refresh keeps them
   warm with margin). */
add_filter( 'cron_schedules', function ( $schedules ) {
	if ( ! isset( $schedules['oyc_5min'] ) ) {
		$schedules['oyc_5min'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'OYC: every 5 minutes', 'orienta-yacht-club' ),
		);
	}
	return $schedules;
} );

/* Ensure the event is scheduled (cheap no-op once it exists). */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'oyc_warm_weather' ) ) {
		wp_schedule_event( time() + 60, 'oyc_5min', 'oyc_warm_weather' );
	}
} );

/* Unschedule if the theme is switched away, so we don't keep firing from a
   parent/child the site no longer uses. */
add_action( 'switch_theme', function () {
	$ts = wp_next_scheduled( 'oyc_warm_weather' );
	if ( $ts ) { wp_unschedule_event( $ts, 'oyc_warm_weather' ); }
} );

/* ── Static JSON mirror ──────────────────────────────────────────────────────
   The cron also writes each warmed reading to a flat file under
   /wp-content/uploads/oyc-weather/, which the client fetches DIRECTLY. Apache
   serves a static file in ~50ms, vs ~1.3s for an admin-ajax call (which boots
   all of WordPress even on a cache hit — the real data-layer floor on this host).
   The client keeps the admin-ajax proxy as a fallback (and uses it when the file
   is stale, e.g. if cron ever stops). */
function oyc_weather_static_dir() {
	$u = wp_upload_dir();
	return array( trailingslashit( $u['basedir'] ) . 'oyc-weather', trailingslashit( $u['baseurl'] ) . 'oyc-weather' );
}
/* Public base URL the client reads from (also injected into the board scripts). */
function oyc_weather_static_url() {
	list( , $url ) = oyc_weather_static_dir();
	return trailingslashit( $url );
}
/* Atomically write $json to <uploads>/oyc-weather/$name.json. */
function oyc_weather_static_write( $name, $json ) {
	if ( ! is_string( $json ) || '' === $json || ! preg_match( '/^[a-z0-9_]+$/', $name ) ) { return; }
	list( $dir ) = oyc_weather_static_dir();
	if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) { return; }
	$tmp = $dir . '/.' . $name . '.' . wp_generate_password( 6, false ) . '.tmp';
	if ( false !== file_put_contents( $tmp, $json ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! @rename( $tmp, $dir . '/' . $name . '.json' ) ) { @unlink( $tmp ); } // phpcs:ignore
	}
}

/* The warm run: force-refresh each always-loaded reading, then mirror it to a
   static file. Every build is guarded by its own in-flight lock, so a concurrent
   live request is never double-fetched — if a visitor is mid-fetch the warmer
   skips that key this tick (and keeps last tick's static file). */
add_action( 'oyc_warm_weather', 'oyc_warm_weather_run' );
function oyc_warm_weather_run() {
	// Local wind field (Open-Meteo) — the default map layer, loaded on every view.
	if ( function_exists( 'oyc_wind_local_spec' ) && function_exists( 'oyc_om_fetch_store' ) ) {
		list( $k, $u, $t ) = oyc_wind_local_spec();
		$body = oyc_om_fetch_store( $k, $u, $t );
		if ( ! is_string( $body ) || '' === $body ) { $body = get_transient( $k . '_st' ); } // coalesced/failed → last-known
		if ( is_string( $body ) && '' !== $body ) { oyc_weather_static_write( 'wind_local', $body ); }
	}
	// Buoy-42 marine reading — the Execution Rock readout + the Marine Forecast table.
	if ( function_exists( 'oyc_marine_fc_build' ) ) {
		$m = oyc_marine_fc_build( true );
		if ( is_array( $m ) && ! empty( $m['ok'] ) ) { oyc_weather_static_write( 'marine_fc', wp_json_encode( $m ) ); }
	}
	// Live buoy observation (NDBC).
	if ( function_exists( 'oyc_buoy_build' ) ) {
		oyc_weather_static_write( 'buoy', wp_json_encode( oyc_buoy_build( true ) ) );
	}
	// Sitewide header weather glyph.
	if ( function_exists( 'oyc_header_wx_build' ) ) {
		$h = oyc_header_wx_build( true );
		if ( is_array( $h ) && ! empty( $h['ok'] ) ) { oyc_weather_static_write( 'header_wx', wp_json_encode( $h ) ); }
	}
	// Atlantic basin wind field (the zoomed-out map view).
	if ( function_exists( 'oyc_wind_basin_spec' ) ) { oyc_warm_basin_chunk(); }
}

/* Refresh at most ONE 150-point basin chunk per tick: the stalest one, and only
   once it is 6 h old (GFS runs every 6 h). Nine chunks take ~45 min to cycle, so
   the warmer never asks Open-Meteo for the whole 1,271-point grid at once. A chunk
   whose fetch failed is left alone for 30 min. Each chunk is mirrored to
   wind_basin_N.json, from the fresh fetch or else the last-known copy. */
function oyc_warm_basin_chunk() {
	$n    = oyc_basin_chunk_count();
	$done = get_option( 'oyc_basin_warmed', array() ); // chunk => last successful fetch
	$try  = get_option( 'oyc_basin_tried', array() );  // chunk => last attempt
	$now  = time();
	$pick = null;
	$best = PHP_INT_MAX;
	for ( $i = 0; $i < $n; $i++ ) {
		$ok = isset( $done[ $i ] ) ? (int) $done[ $i ] : 0;
		$at = isset( $try[ $i ] ) ? (int) $try[ $i ] : 0;
		if ( $now - $ok < 6 * HOUR_IN_SECONDS || $now - $at < 30 * MINUTE_IN_SECONDS ) { continue; }
		if ( $ok < $best ) { $best = $ok; $pick = $i; }
	}
	if ( null === $pick ) { return; }
	$try[ $pick ] = $now;
	update_option( 'oyc_basin_tried', $try, false );

	list( $key, $url, $ttl, $stale ) = oyc_wind_basin_spec( $pick );
	$body = oyc_om_fetch_store( $key, $url, $ttl, $stale );
	if ( is_string( $body ) && '' !== $body ) {
		$done[ $pick ] = $now;
		update_option( 'oyc_basin_warmed', $done, false );
	} else {
		$body = get_transient( $key . '_st' );
	}
	if ( is_string( $body ) && '' !== $body ) { oyc_weather_static_write( 'wind_basin_' . $pick, $body ); }
}
