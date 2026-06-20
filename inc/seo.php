<?php
/**
 * SEO helpers:
 *  1) Keep the staging mirror out of Google (domain-aware → production unaffected).
 *  2) Redirect the retired /fishing/ URL to /boating/.
 *  3) LocalBusiness (yacht club) structured data on the front page.
 *  4) Per-page SEO titles + meta descriptions (via Yoast filters, kept in code
 *     so they travel with the theme).
 *
 * @package Orienta_Yacht_Club
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** True when served from the staging host. */
function oyc_is_staging() {
	return ! empty( $_SERVER['HTTP_HOST'] ) && 0 === stripos( $_SERVER['HTTP_HOST'], 'staging.' );
}

/* 1) Staging → noindex (works whether Yoast manages robots as a string or array). */
add_filter( 'wpseo_robots', function ( $robots ) {
	return oyc_is_staging() ? 'noindex, nofollow' : $robots;
}, 99 );
add_filter( 'wpseo_robots_array', function ( $robots ) {
	if ( oyc_is_staging() && is_array( $robots ) ) {
		$robots['index']  = 'noindex';
		$robots['follow'] = 'nofollow';
	}
	return $robots;
}, 99 );
// Fallback if Yoast is inactive.
add_action( 'wp_head', function () {
	if ( oyc_is_staging() && ! defined( 'WPSEO_VERSION' ) ) {
		echo '<meta name="robots" content="noindex, nofollow">' . "\n";
	}
}, 1 );

/* 2) Retired /fishing/ → /boating/ (fishing content now lives on the Boating page). */
add_action( 'template_redirect', function () {
	$path = trim( strtok( $_SERVER['REQUEST_URI'] ?? '', '?' ), '/' );
	if ( 'fishing' === $path ) {
		wp_safe_redirect( home_url( '/boating/' ), 301 );
		exit;
	}
} );

/* 3) LocalBusiness / yacht-club structured data (front page). */
add_action( 'wp_head', function () {
	if ( ! is_front_page() ) { return; }
	$schema = array(
		'@context'     => 'https://schema.org',
		'@type'        => array( 'SportsActivityLocation', 'LocalBusiness' ),
		'name'         => 'Orienta Yacht Club',
		'url'          => home_url( '/' ),
		'logo'         => get_template_directory_uri() . '/assets/favicon-192.png',
		'image'        => home_url( '/wp-content/uploads/2022/12/orienta-logo-social.jpg' ),
		'telephone'    => '+1-914-698-9858',
		'email'        => 'secretary@orientayachtclub.com',
		'foundingDate' => '1907',
		'description'  => 'A welcoming yacht club on Mamaroneck Harbor since 1907 — sailing, racing, fishing, moorings, dining and a full social calendar on Long Island Sound.',
		'address'      => array(
			'@type'           => 'PostalAddress',
			'streetAddress'   => '325 Boston Post Road',
			'addressLocality' => 'Mamaroneck',
			'addressRegion'   => 'NY',
			'postalCode'      => '10543',
			'addressCountry'  => 'US',
		),
		'sameAs'       => array(
			'https://www.facebook.com/Orienta-Yacht-Club-Mamaroneck-217273081663504/',
		),
	);
	echo '<script type="application/ld+json">' . wp_json_encode( $schema ) . '</script>' . "\n";
}, 20 );

/* 4) Per-page titles + meta descriptions (slug-keyed, output via Yoast). */
function oyc_seo_map() {
	return array(
		'home' => array(
			'title' => 'Orienta Yacht Club | Sailing, Racing & Moorings in Mamaroneck, NY',
			'desc'  => 'A welcoming yacht club on Mamaroneck Harbor since 1907 — sailing, racing, fishing, moorings, dining and a full social calendar on Long Island Sound.',
		),
		'about' => array(
			'title' => 'About Us | Orienta Yacht Club, Mamaroneck NY',
			'desc'  => 'Founded in 1907, Orienta Yacht Club is one of the oldest clubs on Long Island Sound, at the head of Mamaroneck Harbor\'s East Basin.',
		),
		'membership' => array(
			'title' => 'Membership | Orienta Yacht Club, Mamaroneck NY',
			'desc'  => 'Join Orienta Yacht Club in Mamaroneck, NY. Regular, Junior and Associate memberships with moorings, racing, dining and clubhouse access.',
		),
		'boating' => array(
			'title' => 'Boating, Sailing & Racing | Orienta Yacht Club',
			'desc'  => 'Sail and race out of Mamaroneck on Long Island Sound — the Governor\'s Cup regatta, YRALIS fleet racing, frostbiting and an active fishing community.',
		),
		'visitors' => array(
			'title' => 'Visiting Boaters & Transient Moorings | Orienta Yacht Club',
			'desc'  => 'Cruising into Mamaroneck Harbor? Reserve a transient mooring at Orienta Yacht Club, get approach directions and hail the launch on VHF 68.',
		),
		'contact' => array(
			'title' => 'Contact | Orienta Yacht Club, Mamaroneck NY',
			'desc'  => 'Get in touch with Orienta Yacht Club, 325 Boston Post Road, Mamaroneck, NY. Phone 914-698-9858; launch on VHF Channel 68.',
		),
		'approach' => array(
			'title' => 'Approaching Orienta by Water | Orienta Yacht Club',
			'desc'  => 'A boater\'s guide to entering Mamaroneck Harbor and finding Orienta Yacht Club at the head of the East Basin, with launch hailing details.',
		),
		'mamaroneck-harbor' => array(
			'title' => 'Mamaroneck Harbor Guide | Orienta Yacht Club',
			'desc'  => 'Weather, tides, provisions, moorings and harbor services for Mamaroneck Harbor on Long Island Sound, from Orienta Yacht Club.',
		),
		'facilities' => array(
			'title' => 'Club Facilities | Orienta Yacht Club, Mamaroneck NY',
			'desc'  => 'Clubhouse, docks, launch service, moorings, dining and boat yard at Orienta Yacht Club on Mamaroneck Harbor.',
		),
		'reciprocity-list' => array(
			'title' => 'Reciprocal Yacht Clubs | Orienta Yacht Club',
			'desc'  => 'Yacht clubs offering reciprocal privileges to Orienta Yacht Club members across Long Island Sound and beyond.',
		),
		'sailing-instructions' => array(
			'title' => 'Sailing Instructions | Orienta Yacht Club',
			'desc'  => 'Sailing instructions and racing details for events hosted by Orienta Yacht Club on Western Long Island Sound.',
		),
		'storm-warnings' => array(
			'title' => 'Storm Warnings & Marine Forecast | Orienta Yacht Club',
			'desc'  => 'Current marine forecasts, tides and storm warnings for Mamaroneck Harbor and Western Long Island Sound.',
		),
		'calendar' => array(
			'title' => 'Club Calendar & Events | Orienta Yacht Club',
			'desc'  => 'Races, social events, dining and club happenings at Orienta Yacht Club in Mamaroneck, NY.',
		),
	);
}
function oyc_seo_current() {
	$slug = is_front_page() ? 'home' : ( is_page() ? get_post_field( 'post_name', get_queried_object_id() ) : '' );
	$map  = oyc_seo_map();
	return ( $slug && isset( $map[ $slug ] ) ) ? $map[ $slug ] : null;
}
add_filter( 'wpseo_title', function ( $title ) {
	$e = oyc_seo_current();
	return ( $e && ! empty( $e['title'] ) ) ? $e['title'] : $title;
} );
add_filter( 'wpseo_metadesc', function ( $desc ) {
	$e = oyc_seo_current();
	return ( $e && ! empty( $e['desc'] ) ) ? $e['desc'] : $desc;
} );
