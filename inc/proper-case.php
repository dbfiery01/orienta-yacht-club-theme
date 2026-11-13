<?php
/**
 * Proper case for names and street addresses on display.
 *
 * Only text typed in ALL CAPS or all lowercase is changed ("JOHN SMITH",
 * "12 main st"); mixed case the member chose ("McDonald", "DeLuca") is left
 * alone. Stored data is not modified.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param string $s    Text to fix.
 * @param string $kind 'name' or 'street'.
 */
function oyc_proper_case( $s, $kind = 'name' ) {
	$s = (string) $s;
	if ( '' === trim( $s ) ) {
		return $s;
	}
	$upper = mb_strtoupper( $s, 'UTF-8' );
	$lower = mb_strtolower( $s, 'UTF-8' );
	if ( $s !== $upper && $s !== $lower ) {
		return $s; // already mixed case.
	}
	if ( $upper === $lower ) {
		return $s; // no letters (e.g. a house number only).
	}

	// Words that stay upper case.
	$keep_upper = array( 'II', 'III', 'IV', 'VI', 'VII', 'MD', 'DDS', 'PHD', 'CPA', 'USCG', 'USN' );
	if ( 'street' === $kind ) {
		$keep_upper = array_merge( $keep_upper, array( 'N', 'S', 'E', 'W', 'NE', 'NW', 'SE', 'SW', 'PO', 'NY', 'CT', 'NJ', 'FL', 'MA', 'RI', 'PA', 'US' ) );
	}

	$out = $lower;
	$i   = 0;
	$out = preg_replace_callback(
		'/[\p{L}\p{N}]+/u',
		function ( $m ) use ( $keep_upper, $kind, &$i ) {
			$w  = $m[0];
			$up = mb_strtoupper( $w, 'UTF-8' );
			$lo = mb_strtolower( $w, 'UTF-8' );
			$first = 0 === $i++;
			if ( in_array( $up, $keep_upper, true ) ) {
				return $up;
			}
			if ( preg_match( '/^\d+(st|nd|rd|th)$/i', $w ) ) {
				return $lo; // 2nd, 41st.
			}
			if ( preg_match( '/^\d+[a-z]$/i', $w ) ) {
				return $up; // unit 12B.
			}
			if ( ! $first && 'street' === $kind && in_array( $lo, array( 'and', 'of', 'the' ), true ) ) {
				return $lo;
			}
			if ( 'name' === $kind && preg_match( '/^mc(\p{L})(\p{L}*)$/u', $lo, $mm ) ) {
				return 'Mc' . mb_strtoupper( $mm[1], 'UTF-8' ) . $mm[2];
			}
			return mb_strtoupper( mb_substr( $lo, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $lo, 1, null, 'UTF-8' ); // O'Brien.
		},
		$out
	);
	return $out;
}
