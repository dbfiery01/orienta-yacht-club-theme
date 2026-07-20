<?php
/**
 * Baseline security response headers: clickjacking (X-Frame-Options),
 * MIME-sniffing (X-Content-Type-Options), referrer leakage (Referrer-Policy),
 * and unused browser features (Permissions-Policy).
 *
 * Each header is only added if the response doesn't already carry it, so this
 * never duplicates a header that WP core or a security plugin already sends.
 * NOTE: HTTPS enforcement (redirect + HSTS) is intentionally NOT here — that
 * belongs at the server/host/WAF level, not in theme code.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'send_headers', function () {
	if ( headers_sent() ) {
		return;
	}

	// Header names already present on this response (lower-cased).
	$present = array();
	foreach ( headers_list() as $header ) {
		$present[ strtolower( trim( strtok( $header, ':' ) ) ) ] = true;
	}

	$add = function ( $name, $value ) use ( $present ) {
		if ( empty( $present[ strtolower( $name ) ] ) ) {
			header( $name . ': ' . $value );
		}
	};

	// SAMEORIGIN still allows the club's own pages to embed each other (and
	// digital-signage players that load the URL directly), while blocking
	// third-party framing / clickjacking.
	$add( 'X-Frame-Options', 'SAMEORIGIN' );
	$add( 'X-Content-Type-Options', 'nosniff' );
	$add( 'Referrer-Policy', 'strict-origin-when-cross-origin' );
	// Disable browser features the site doesn't use (kept minimal so media
	// embeds like YouTube — autoplay/fullscreen/etc. — are unaffected).
	$add( 'Permissions-Policy', 'geolocation=(), camera=(), microphone=(), payment=(), usb=()' );
}, 20 );
