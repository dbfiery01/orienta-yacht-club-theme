<?php
/**
 * Live Yelp ratings for the wind map's Dock & Dine pins.
 *
 * Serves a name-keyed map of {rating, count, price, url} for the dine entries
 * in assets/map-pois.json. Yelp is queried server-side (the key never reaches
 * the browser) and the whole payload is cached in a transient for 12 hours, so
 * Yelp sees at most ~2 lookup rounds a day regardless of traffic — far inside
 * the free tier.
 *
 * Endpoint: admin-ajax (action=oyc_poi_ratings), NOT the REST API — this
 * site's security plugin login-gates all of /wp-json/, including custom
 * namespaces, and the wind map is public.
 *
 * Key: define OYC_YELP_API_KEY in wp-config.php (preferred) or set the
 * 'oyc_yelp_api_key' option. With no key the endpoint returns {} and the
 * popups simply show no ratings line — safe to deploy ahead of the key.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function oyc_poi_yelp_key() {
	if ( defined( 'OYC_YELP_API_KEY' ) && OYC_YELP_API_KEY ) {
		return OYC_YELP_API_KEY;
	}
	return (string) get_option( 'oyc_yelp_api_key', '' );
}

function oyc_poi_ratings_payload() {
	$cached = get_transient( 'oyc_poi_ratings' );
	if ( false !== $cached ) {
		return $cached;
	}

	$key = oyc_poi_yelp_key();
	if ( '' === $key ) {
		// No key yet: cache the empty answer briefly so we don't re-check every hit.
		set_transient( 'oyc_poi_ratings', array(), HOUR_IN_SECONDS );
		return array();
	}

	$file = get_template_directory() . '/assets/map-pois.json';
	$data = json_decode( (string) file_get_contents( $file ), true );
	$out  = array();

	foreach ( (array) ( $data['dine'] ?? array() ) as $p ) {
		if ( empty( $p['name'] ) || ! isset( $p['lat'], $p['lng'] ) ) {
			continue;
		}
		$url  = 'https://api.yelp.com/v3/businesses/search?' . http_build_query( array(
			'term'      => $p['name'],
			'latitude'  => $p['lat'],
			'longitude' => $p['lng'],
			'radius'    => 400, // meters — the pin is at the dock, the business is steps away
			'limit'     => 1,
			'sort_by'   => 'best_match',
		) );
		$resp = wp_remote_get( $url, array(
			'timeout' => 8,
			'headers' => array( 'Authorization' => 'Bearer ' . $key ),
		) );
		if ( is_wp_error( $resp ) || 200 !== wp_remote_retrieve_response_code( $resp ) ) {
			continue;
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		$b    = $body['businesses'][0] ?? null;
		if ( ! $b || empty( $b['rating'] ) ) {
			continue;
		}
		$out[ $p['name'] ] = array(
			'rating' => (float) $b['rating'],
			'count'  => (int) ( $b['review_count'] ?? 0 ),
			'price'  => (string) ( $b['price'] ?? '' ),
			'url'    => (string) ( $b['url'] ?? '' ),
		);
	}

	set_transient( 'oyc_poi_ratings', $out, 12 * HOUR_IN_SECONDS );
	return $out;
}

function oyc_poi_ratings_ajax() {
	wp_send_json( oyc_poi_ratings_payload() );
}
add_action( 'wp_ajax_oyc_poi_ratings', 'oyc_poi_ratings_ajax' );
add_action( 'wp_ajax_nopriv_oyc_poi_ratings', 'oyc_poi_ratings_ajax' );
