<?php
/**
 * Tap-to-call: turn plain phone numbers in page/post content into tel: links.
 *
 * Runs on the_content (after wpautop). Existing links — including any current
 * tel: links — are shielded first so a number already inside an <a> is never
 * double-wrapped, and link hrefs (e.g. URLs with number sequences) are left
 * untouched. Only US-style numbers with an area code and separators match, so
 * ZIP codes (5 digits) and years are ignored.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'the_content', 'oyc_linkify_phone_numbers', 20 );

/**
 * @param string $content Post content HTML.
 * @return string
 */
function oyc_linkify_phone_numbers( $content ) {
	if ( is_admin() || is_feed() || '' === trim( (string) $content ) ) {
		return $content;
	}

	// 1. Shield existing anchors so numbers already inside a link stay untouched.
	$shield  = array();
	$content = preg_replace_callback(
		'#<a\b[^>]*>.*?</a>#is',
		function ( $m ) use ( &$shield ) {
			$token            = '@@OYCTEL' . count( $shield ) . 'X@@';
			$shield[ $token ] = $m[0];
			return $token;
		},
		$content
	);

	// 2. Linkify US phone numbers: (914) 698-9858 | 914-698-9858 | 914.698.9858
	$content = preg_replace_callback(
		'/\(?\d{3}\)?[\s.\-]?\d{3}[\s.\-]\d{4}/',
		function ( $m ) {
			$digits = preg_replace( '/\D/', '', $m[0] );
			if ( 10 !== strlen( $digits ) ) {
				return $m[0];
			}
			return '<a href="tel:+1' . $digits . '">' . $m[0] . '</a>';
		},
		$content
	);

	// 3. Restore the shielded anchors.
	return $shield ? strtr( $content, $shield ) : $content;
}
