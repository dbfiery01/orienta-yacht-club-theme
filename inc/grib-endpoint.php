<?php
/**
 * GRIB download endpoint for the Marine Weather page.
 *
 * Serves the latest NOAA GFS 0.25° forecast for the western Long Island Sound
 * box as a single multi-message GRIB2 file that members can load into their
 * nav software (OpenCPN, zyGrib/XyGrib, PredictWind, Expedition, qtVlm…).
 *
 * It fetches a handful of forecast hours from NOAA NOMADS' GRIB filter
 * (10 m wind U/V, surface gust, MSL pressure), concatenates them, and caches
 * the result in a transient for a few hours (NOMADS has no CORS, so this must
 * be server-side). Endpoint: /wp-admin/admin-ajax.php?action=oyc_grib
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'wp_ajax_oyc_grib',        'oyc_grib_download' );
add_action( 'wp_ajax_nopriv_oyc_grib', 'oyc_grib_download' );

function oyc_grib_download() {
	// Latest available GFS cycle: models publish ~3.5–5 h after the cycle, so
	// step back 5 h and floor to the nearest 6-hourly run (00/06/12/18Z).
	$t   = time() - 5 * HOUR_IN_SECONDS;
	$hh  = sprintf( '%02d', floor( (int) gmdate( 'G', $t ) / 6 ) * 6 );
	$ymd = gmdate( 'Ymd', $t );
	$run = $ymd . $hh;

	$cache = get_transient( 'oyc_grib_bin' );
	if ( is_array( $cache ) && isset( $cache['run'], $cache['b64'] ) && $cache['run'] === $run && $cache['b64'] ) {
		oyc_grib_send( base64_decode( $cache['b64'] ), $run );
	}

	// Denser early, sparser later — a useful 48 h picture without hammering NOMADS.
	$hours = array( 0, 3, 6, 9, 12, 18, 24, 36, 48 );
	$bin   = '';
	foreach ( $hours as $fh ) {
		$url = 'https://nomads.ncep.noaa.gov/cgi-bin/filter_gfs_0p25.pl?'
			. 'dir=%2Fgfs.' . $ymd . '%2F' . $hh . '%2Fatmos'
			. '&file=gfs.t' . $hh . 'z.pgrb2.0p25.' . sprintf( 'f%03d', $fh )
			. '&var_UGRD=on&var_VGRD=on&var_GUST=on&var_PRMSL=on'
			. '&lev_10_m_above_ground=on&lev_surface=on&lev_mean_sea_level=on'
			. '&subregion=&toplat=41.7&leftlon=-74.7&rightlon=-71.8&bottomlat=40.1';
		$resp = wp_remote_get( $url, array( 'timeout' => 8, 'headers' => array( 'User-Agent' => 'OYC-Weather/1.0' ) ) );
		if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) { continue; }
		$body = wp_remote_retrieve_body( $resp );
		if ( 'GRIB' === substr( $body, 0, 4 ) ) { $bin .= $body; }
	}

	if ( '' === $bin ) {
		status_header( 503 );
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo 'GRIB temporarily unavailable — the forecast run may still be publishing. Please try again in a few minutes.';
		exit;
	}

	set_transient( 'oyc_grib_bin', array( 'run' => $run, 'b64' => base64_encode( $bin ) ), 3 * HOUR_IN_SECONDS );
	oyc_grib_send( $bin, $run );
}

function oyc_grib_send( $bin, $run ) {
	nocache_headers();
	header( 'Content-Type: application/octet-stream' );
	header( 'Content-Disposition: attachment; filename="oyc-lis-gfs-' . $run . '.grb2"' );
	header( 'Content-Length: ' . strlen( $bin ) );
	echo $bin;
	exit;
}

/* ── Active Atlantic named storms, from NOAA/NHC (no CORS → server proxy) ──
   Returns the current tropical cyclones in the Atlantic basin (id starts AL)
   with position, class, winds, pressure and movement, PLUS the official NHC
   forecast track (points out to +120 h), for the Wind page's basin view. The
   marker glides along the track as the time slider moves. Cached 30 min.
   Endpoint: admin-ajax.php?action=oyc_storms ── */
add_action( 'wp_ajax_oyc_storms',        'oyc_storms_proxy' );
add_action( 'wp_ajax_nopriv_oyc_storms', 'oyc_storms_proxy' );

/* Parse the NHC forecast advisory (TCM text product) into track points.
   Returns [ {t:<epoch ms UTC>, lat, lon}, … ] — the current center first,
   then each FORECAST VALID position. Empty array on any failure (the client
   falls back to the storm's current position). */
function oyc_storm_track( $tcm_url, $issuance_iso ) {
	$pts = array();
	if ( ! $tcm_url ) { return $pts; }
	$r = wp_remote_get( $tcm_url, array( 'timeout' => 5, 'headers' => array( 'User-Agent' => 'OYC-Weather/1.0' ) ) );
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) { return $pts; }
	$txt = wp_strip_all_tags( (string) wp_remote_retrieve_body( $r ) );

	$base  = strtotime( (string) $issuance_iso );
	if ( ! $base ) { $base = time(); }
	$byear = (int) gmdate( 'Y', $base );
	$bmon  = (int) gmdate( 'n', $base );
	// DD/HHMM (UTC) → epoch ms. Advisories run up to +120 h, so a day-of-month
	// that lands well before the issuance means it rolled into the next month.
	$mk = function ( $dd, $hh, $mm ) use ( $base, $byear, $bmon ) {
		$t = gmmktime( $hh, $mm, 0, $bmon, $dd, $byear );
		if ( $t < $base - 2 * DAY_IN_SECONDS ) {
			$mo = $bmon + 1; $y = $byear;
			if ( $mo > 12 ) { $mo = 1; $y++; }
			$t = gmmktime( $hh, $mm, 0, $mo, $dd, $y );
		}
		return $t * 1000;
	};
	$sgn = function ( $v, $hemi ) { $h = strtoupper( $hemi ); return ( 'S' === $h || 'W' === $h ) ? -abs( (float) $v ) : abs( (float) $v ); };

	// Current center: "CENTER LOCATED NEAR 27.1N  44.6W AT 28/1500Z"
	if ( preg_match( '/CENTER LOCATED NEAR\s+([0-9.]+)([NS])\s+([0-9.]+)([EW])\s+AT\s+(\d{2})\/(\d{2})(\d{2})Z/i', $txt, $m ) ) {
		$pts[] = array( 't' => $mk( (int) $m[5], (int) $m[6], (int) $m[7] ), 'lat' => $sgn( $m[1], $m[2] ), 'lon' => $sgn( $m[3], $m[4] ) );
	}
	// Forecast points: "FORECAST VALID 29/0000Z 26.0N  45.5W"  (skip DISSIPATED lines w/o coords)
	if ( preg_match_all( '/FORECAST VALID\s+(\d{2})\/(\d{2})(\d{2})Z\s+([0-9.]+)([NS])\s+([0-9.]+)([EW])/i', $txt, $ms, PREG_SET_ORDER ) ) {
		foreach ( $ms as $m ) {
			$pts[] = array( 't' => $mk( (int) $m[1], (int) $m[2], (int) $m[3] ), 'lat' => $sgn( $m[4], $m[5] ), 'lon' => $sgn( $m[6], $m[7] ) );
		}
	}
	return $pts;
}

function oyc_storms_proxy() {
	$cached = get_transient( 'oyc_storms_atl_v3' );
	if ( false !== $cached ) { wp_send_json( $cached ); }

	$out  = array();
	$hit  = false;
	$resp = wp_remote_get( 'https://www.nhc.noaa.gov/CurrentStorms.json', array( 'timeout' => 8, 'headers' => array( 'User-Agent' => 'OYC-Weather/1.0' ) ) );
	if ( ! is_wp_error( $resp ) && 200 === (int) wp_remote_retrieve_response_code( $resp ) ) {
		$hit = true;
		$j = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
		$storms = ( is_array( $j ) && isset( $j['activeStorms'] ) && is_array( $j['activeStorms'] ) ) ? $j['activeStorms'] : array();
		foreach ( $storms as $s ) {
			$id = isset( $s['id'] ) ? (string) $s['id'] : '';
			if ( 0 !== stripos( $id, 'AL' ) ) { continue; }  // Atlantic basin only (ids are lowercase, e.g. "al062026")
			if ( ! isset( $s['latitudeNumeric'], $s['longitudeNumeric'] ) ) { continue; }
			$lat = (float) $s['latitudeNumeric'];
			$lon = (float) $s['longitudeNumeric'];
			// Force hemisphere sign from the labelled string fields (numeric sign varies).
			if ( isset( $s['latitude'] )  && false !== stripos( (string) $s['latitude'],  'S' ) ) { $lat = -abs( $lat ); }
			if ( isset( $s['longitude'] ) && false !== stripos( (string) $s['longitude'], 'W' ) ) { $lon = -abs( $lon ); }
			$fa_url = isset( $s['forecastAdvisory']['url'] ) ? (string) $s['forecastAdvisory']['url'] : '';
			$fa_iss = isset( $s['forecastAdvisory']['issuance'] ) ? (string) $s['forecastAdvisory']['issuance']
				: ( isset( $s['lastUpdate'] ) ? (string) $s['lastUpdate'] : '' );
			$track  = oyc_storm_track( $fa_url, $fa_iss );
			$out[] = array(
				'name'  => isset( $s['name'] ) ? $s['name'] : '',
				'cls'   => isset( $s['classification'] ) ? $s['classification'] : '',
				'kt'    => isset( $s['intensity'] ) ? (int) $s['intensity'] : null,
				'mb'    => isset( $s['pressure'] ) ? (int) $s['pressure'] : null,
				'lat'   => $lat,
				'lon'   => $lon,
				'dir'   => isset( $s['movementDir'] ) ? $s['movementDir'] : null,
				'spd'   => isset( $s['movementSpeed'] ) ? $s['movementSpeed'] : null,
				'track' => $track, // [{t:ms,lat,lon}] current → +120h, [] if unavailable
			);
		}
	}
	// Cache a good result for 30 min; cache a failed/empty fetch only briefly so a
	// transient outage doesn't hide storms for half an hour.
	set_transient( 'oyc_storms_atl_v3', $out, ( $hit ? 30 : 8 ) * MINUTE_IN_SECONDS );
	wp_send_json( $out );
}
