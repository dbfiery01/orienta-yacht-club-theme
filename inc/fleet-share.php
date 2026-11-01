<?php
/**
 * Fleet positions for the Wind & Radar map's chart plotter.
 *
 * Logged-in members (and racers) can switch on "Share my position"; while the
 * map is open their browser posts its GPS fix here every ~15 s. Anyone viewing
 * the map can switch on "Other boats" to see the boats sharing right now.
 *
 * Nothing is kept: every position lives in one transient, each entry drops out
 * OYC_FLEET_TTL seconds after its last fix, and switching sharing off removes
 * it at once. Entries are keyed by an opaque hash, never a user ID or email.
 *
 * Endpoints (admin-ajax):
 *   oyc_fleet_me   (priv + nopriv) may I share? → nonce, my key, default boat name
 *   oyc_fleet_pos  (priv, POST)    store my fix {lat, lon, sog, cog, name}
 *   oyc_fleet_off  (priv, POST)    stop sharing (drop my entry)
 *   oyc_fleet      (priv + nopriv) every live position
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OYC_FLEET_TTL', 600 );        // a boat disappears 10 min after its last fix
define( 'OYC_FLEET_KEY', 'oyc_fleet_positions' );

/** Members and racers may share; visitors only watch. */
function oyc_fleet_can_share( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return false;
	}
	$ok = user_can( $user_id, 'manage_options' )
		|| ( function_exists( 'oyc_user_may_view_members_content' ) && get_current_user_id() === $user_id && oyc_user_may_view_members_content() )
		|| ( function_exists( 'oyc_is_racer' ) && oyc_is_racer( $user_id ) );
	return (bool) apply_filters( 'oyc_fleet_can_share', $ok, $user_id );
}

/** Opaque, stable per-user key (so the browser can hide its own boat). */
function oyc_fleet_user_key( $user_id ) {
	return substr( hash_hmac( 'sha256', 'fleet|' . (int) $user_id, wp_salt( 'auth' ) ), 0, 16 );
}

/** Live entries only (expired ones pruned). */
function oyc_fleet_load() {
	$all = get_transient( OYC_FLEET_KEY );
	if ( ! is_array( $all ) ) {
		return array();
	}
	$cut = time() - OYC_FLEET_TTL;
	foreach ( $all as $k => $p ) {
		if ( empty( $p['t'] ) || $p['t'] < $cut ) {
			unset( $all[ $k ] );
		}
	}
	return $all;
}

function oyc_fleet_save( $all ) {
	if ( empty( $all ) ) {
		delete_transient( OYC_FLEET_KEY );
		return;
	}
	set_transient( OYC_FLEET_KEY, $all, OYC_FLEET_TTL + 60 );
}

/** Boat name to suggest: the member's first yacht, else their name. */
function oyc_fleet_default_name( $user_id ) {
	if ( function_exists( 'oyc_race_yachts' ) ) {
		$y = oyc_race_yachts( $user_id );
		if ( $y ) {
			return get_the_title( $y[0] );
		}
	}
	$u = get_userdata( $user_id );
	if ( ! $u ) {
		return '';
	}
	$n = trim( $u->first_name . ' ' . $u->last_name );
	return '' !== $n ? $n : $u->display_name;
}

function oyc_fleet_nocache() {
	nocache_headers();
	header( 'X-Robots-Tag: noindex' );
}

add_action( 'wp_ajax_oyc_fleet_me', 'oyc_fleet_me' );
add_action( 'wp_ajax_nopriv_oyc_fleet_me', 'oyc_fleet_me' );
function oyc_fleet_me() {
	oyc_fleet_nocache();
	$uid = get_current_user_id();
	if ( ! $uid ) {
		wp_send_json( array( 'ok' => false, 'why' => 'login', 'login' => wp_login_url( home_url( '/weather/' ) ) ) );
	}
	if ( ! oyc_fleet_can_share( $uid ) ) {
		wp_send_json( array( 'ok' => false, 'why' => 'member' ) );
	}
	wp_send_json( array(
		'ok'    => true,
		'nonce' => wp_create_nonce( 'oyc_fleet' ),
		'key'   => oyc_fleet_user_key( $uid ),
		'name'  => oyc_fleet_default_name( $uid ),
	) );
}

add_action( 'wp_ajax_oyc_fleet_pos', 'oyc_fleet_pos' );
function oyc_fleet_pos() {
	oyc_fleet_nocache();
	$uid = get_current_user_id();
	if ( ! $uid || ! check_ajax_referer( 'oyc_fleet', 'nonce', false ) || ! oyc_fleet_can_share( $uid ) ) {
		wp_send_json( array( 'ok' => false ), 403 );
	}
	$lat = isset( $_POST['lat'] ) ? (float) $_POST['lat'] : 999;
	$lon = isset( $_POST['lon'] ) ? (float) $_POST['lon'] : 999;
	if ( $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180 ) {
		wp_send_json( array( 'ok' => false, 'why' => 'pos' ), 400 );
	}
	$num = function ( $k, $min, $max ) {
		if ( ! isset( $_POST[ $k ] ) || '' === $_POST[ $k ] ) {
			return null;
		}
		$v = (float) $_POST[ $k ];
		return ( $v >= $min && $v <= $max ) ? round( $v, 1 ) : null;
	};
	$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$name = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 40 ) : substr( $name, 0, 40 );
	if ( '' === $name ) {
		$name = oyc_fleet_default_name( $uid );
	}
	$key = oyc_fleet_user_key( $uid );
	$all = oyc_fleet_load();
	$all[ $key ] = array(
		'lat'  => round( $lat, 5 ),
		'lon'  => round( $lon, 5 ),
		'sog'  => $num( 'sog', 0, 60 ),
		'cog'  => $num( 'cog', 0, 360 ),
		'name' => $name,
		't'    => time(),
	);
	oyc_fleet_save( $all );
	wp_send_json( array( 'ok' => true ) );
}

add_action( 'wp_ajax_oyc_fleet_off', 'oyc_fleet_off' );
function oyc_fleet_off() {
	oyc_fleet_nocache();
	$uid = get_current_user_id();
	if ( ! $uid || ! check_ajax_referer( 'oyc_fleet', 'nonce', false ) ) {
		wp_send_json( array( 'ok' => false ), 403 );
	}
	$all = oyc_fleet_load();
	unset( $all[ oyc_fleet_user_key( $uid ) ] );
	oyc_fleet_save( $all );
	wp_send_json( array( 'ok' => true ) );
}

add_action( 'wp_ajax_oyc_fleet', 'oyc_fleet_list' );
add_action( 'wp_ajax_nopriv_oyc_fleet', 'oyc_fleet_list' );
function oyc_fleet_list() {
	oyc_fleet_nocache();
	$now   = time();
	$boats = array();
	foreach ( oyc_fleet_load() as $k => $p ) {
		$boats[] = array(
			'k'    => $k,
			'lat'  => $p['lat'],
			'lon'  => $p['lon'],
			'sog'  => $p['sog'],
			'cog'  => $p['cog'],
			'name' => $p['name'],
			'age'  => max( 0, $now - (int) $p['t'] ),
		);
	}
	wp_send_json( array( 'ok' => true, 'boats' => $boats ) );
}
