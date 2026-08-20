<?php
/**
 * Scope the Google Maps JavaScript API to pages that actually need it.
 *
 * Calendarize it! enqueues `google-api3` (the Maps JS API) and `rhc_gmap3` on
 * every page. That is ~1.3MB of JavaScript site-wide, and nothing on this site
 * currently uses it:
 *
 *   - /contact/       uses a Google Maps *embed iframe*, which loads Google's own
 *                     page and needs no API script.
 *   - /dock-and-dine/ uses Leaflet / OpenStreetMap.
 *   - Events created through inc/calendar-api.php set fc_event_map to ''.
 *
 * So it is dropped by default and re-enabled only for a single event that has a
 * map configured. Anything else that needs it can opt in through the
 * `oyc_needs_google_maps` filter rather than reverting this.
 *
 * Scripts are dequeued, NOT deregistered: if some other plugin genuinely depends
 * on the handle, WordPress will still resolve and print it, which is correct.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Whether the current request needs the Google Maps JavaScript API.
 *
 * @return bool
 */
function oyc_needs_google_maps() {
	$needs = false;

	// A single Calendarize it! event that has a map configured.
	if ( is_singular( 'events' ) ) {
		$needs = '' !== trim( (string) get_post_meta( get_the_ID(), 'fc_event_map', true ) );
	}

	/**
	 * Opt a page back into the Maps API.
	 *
	 * add_filter( 'oyc_needs_google_maps', function ( $needs ) {
	 *     return $needs || is_page( 'some-page-with-a-map' );
	 * } );
	 *
	 * @param bool $needs Whether the Maps API is required here.
	 */
	return (bool) apply_filters( 'oyc_needs_google_maps', $needs );
}

/**
 * Drop the Maps API where it is not needed. Late priority so it runs after the
 * plugins that enqueue it.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_admin() || oyc_needs_google_maps() ) {
		return;
	}

	foreach ( array( 'google-api3', 'rhc_gmap3' ) as $handle ) {
		wp_dequeue_script( $handle );
	}
}, 100 );
