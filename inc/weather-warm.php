<?php
/**
 * Weather data warmer.
 *
 * Keeps the board's always-loaded Open-Meteo / NOAA readings hot in the
 * transient cache via WP-Cron, so a visitor hits warm cache (<0.1s) instead of
 * the 2-5s live upstream fetch that dominated the board's load profile. Covers
 * the local wind grid, the Buoy-42 marine reading (wind + waves), the live buoy
 * observation, and the sitewide header glyph.
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

/* The warm run: force-refresh each always-loaded reading. Every build is guarded
   by its own in-flight lock, so a concurrent live request is never double-fetched
   — if a visitor is mid-fetch the warmer simply skips that key this tick. */
add_action( 'oyc_warm_weather', 'oyc_warm_weather_run' );
function oyc_warm_weather_run() {
	// Local wind field (Open-Meteo) — the default map layer, loaded on every view.
	if ( function_exists( 'oyc_wind_local_spec' ) && function_exists( 'oyc_om_fetch_store' ) ) {
		list( $k, $u, $t ) = oyc_wind_local_spec();
		oyc_om_fetch_store( $k, $u, $t );
	}
	// Buoy-42 marine reading — the Execution Rock readout + the Marine Forecast table.
	if ( function_exists( 'oyc_marine_fc_build' ) ) { oyc_marine_fc_build( true ); }
	// Live buoy observation (NDBC).
	if ( function_exists( 'oyc_buoy_build' ) ) { oyc_buoy_build( true ); }
	// Sitewide header weather glyph.
	if ( function_exists( 'oyc_header_wx_build' ) ) { oyc_header_wx_build( true ); }
}
