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

/* ── Wind-map Open-Meteo proxies (cached) ───────────────────────────────────
   The /wind/ map (and its embed on /weather/) fetches Open-Meteo per visitor:
   the LOCAL wind grid on load, and the HRRR precip nowcast in Radar mode. On a
   public board that trips Open-Meteo's per-IP 429 limit — the radar (whose
   loader swallows errors) then silently shows nothing. Serve both from a shared
   server cache so no visitor hits Open-Meteo directly. The LOCAL grid is a fixed
   0.2° box (LI Sound → Cape Cod), regenerated here to match the JS makeGrid(). */
/* Fixed lat/lon point list for a box, row-major — mirrors the JS makeGrid(). */
function oyc_grid_points( $la1, $la2, $lo1, $lo2, $dx, $dy ) {
	$ny = (int) round( ( $la1 - $la2 ) / $dy ) + 1;
	$nx = (int) round( ( $lo2 - $lo1 ) / $dx ) + 1;
	$lats = array(); $lons = array();
	for ( $i = 0; $i < $ny; $i++ ) { $lats[] = round( $la1 - $i * $dy, 3 ); }
	for ( $j = 0; $j < $nx; $j++ ) { $lons[] = round( $lo1 + $j * $dx, 3 ); }
	$lat = array(); $lon = array();
	for ( $i = 0; $i < $ny; $i++ ) { for ( $j = 0; $j < $nx; $j++ ) { $lat[] = $lats[ $i ]; $lon[] = $lons[ $j ]; } }
	return array( $lat, $lon );
}
/* Named grids — must match the JS makeGrid() constants in wind-map.php. */
function oyc_named_grid( $name ) {
	if ( 'basin' === $name ) { return oyc_grid_points( 63, -27, -102, 18, 3.0, 3.0 ); }   // 31x41 = 1271
	return oyc_grid_points( 42.2, 40.4, -74.2, -69.8, 0.2, 0.2 );                          // local 10x23 = 230
}
function oyc_local_grid_csv() {
	list( $lat, $lon ) = oyc_named_grid( 'local' );
	return array( implode( ',', $lat ), implode( ',', $lon ) );
}
/* CSV lat/lon for one client chunk [chunk*$ch, +$ch) — $ch MUST match the JS
   chunk size for that dataset (basin wind CH=150, waves CH=140). null if out of range. */
function oyc_chunk_csv( $lat, $lon, $chunk, $ch ) {
	$n = count( $lat );
	$a = $chunk * $ch;
	if ( $a < 0 || $a >= $n ) { return null; }
	$len = min( $ch, $n - $a );
	return array( implode( ',', array_slice( $lat, $a, $len ) ), implode( ',', array_slice( $lon, $a, $len ) ) );
}

/* Serve a raw JSON body (already-encoded string) and stop — avoids decoding and
   re-encoding the large multi-point Open-Meteo payloads on every cache hit. */
function oyc_send_raw_json( $body ) {
	if ( ! headers_sent() ) { header( 'Content-Type: application/json; charset=utf-8' ); }
	echo $body; // phpcs:ignore WordPress.Security.EscapeOutput — upstream JSON served verbatim
	wp_die();
}

/* Fetch $url, cache the raw body under $key for $ttl on success, and serve it.
   On failure serve "[]" WITHOUT caching so the client retries (its parser treats
   an empty array as "no data" → retry). */
function oyc_om_cached( $key, $url, $ttl ) {
	$body = get_transient( $key );
	if ( false !== $body ) { oyc_send_raw_json( $body ); }
	$r = wp_remote_get( $url, array( 'timeout' => 15, 'headers' => array( 'User-Agent' => 'OYC-Weather/1.0' ) ) );
	if ( ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r ) ) {
		$body = (string) wp_remote_retrieve_body( $r );
		$c = ( '' !== $body ) ? $body[0] : '';
		if ( '[' === $c || '{' === $c ) {           // looks like JSON
			set_transient( $key, $body, $ttl );
			oyc_send_raw_json( $body );
		}
	}
	oyc_send_raw_json( '[]' );
}

/* LOCAL wind field — 7-day, 3-hourly. Cached 15 min. */
add_action( 'wp_ajax_oyc_wind_local',        'oyc_wind_local_proxy' );
add_action( 'wp_ajax_nopriv_oyc_wind_local', 'oyc_wind_local_proxy' );
function oyc_wind_local_proxy() {
	list( $lat, $lon ) = oyc_local_grid_csv();
	$url = 'https://api.open-meteo.com/v1/forecast?latitude=' . $lat . '&longitude=' . $lon
		. '&hourly=wind_speed_10m,wind_direction_10m,wind_gusts_10m,pressure_msl,temperature_2m,precipitation'
		. '&wind_speed_unit=ms&temperature_unit=fahrenheit&precipitation_unit=inch&temporal_resolution=hourly_3&forecast_days=7&timezone=America%2FNew_York';
	oyc_om_cached( 'oyc_wind_local', $url, 15 * MINUTE_IN_SECONDS );
}

/* HRRR precip for Radar mode — 15-min steps, recent past (−1 h) → +18 h. Cached 10 min (near-real-time). */
add_action( 'wp_ajax_oyc_wind_radar',        'oyc_wind_radar_proxy' );
add_action( 'wp_ajax_nopriv_oyc_wind_radar', 'oyc_wind_radar_proxy' );
function oyc_wind_radar_proxy() {
	list( $lat, $lon ) = oyc_local_grid_csv();
	$url = 'https://api.open-meteo.com/v1/forecast?latitude=' . $lat . '&longitude=' . $lon
		. '&minutely_15=precipitation&forecast_minutely_15=80&past_minutes=60&timezone=America%2FNew_York';
	oyc_om_cached( 'oyc_wind_radar', $url, 10 * MINUTE_IN_SECONDS );
}

/* BASIN wind field (Atlantic 3° grid), one client chunk per request so each
   stays small & separately cached. ?chunk=N — CH=150 MUST match the JS loadBasin
   chunk size. Cached 15 min. */
add_action( 'wp_ajax_oyc_wind_basin',        'oyc_wind_basin_proxy' );
add_action( 'wp_ajax_nopriv_oyc_wind_basin', 'oyc_wind_basin_proxy' );
function oyc_wind_basin_proxy() {
	$chunk = isset( $_GET['chunk'] ) ? max( 0, (int) $_GET['chunk'] ) : 0;
	list( $lat, $lon ) = oyc_named_grid( 'basin' );
	$cs = oyc_chunk_csv( $lat, $lon, $chunk, 150 );
	if ( null === $cs ) { oyc_send_raw_json( '[]' ); }
	$url = 'https://api.open-meteo.com/v1/forecast?latitude=' . $cs[0] . '&longitude=' . $cs[1]
		. '&hourly=wind_speed_10m,wind_direction_10m,pressure_msl,temperature_2m'
		. '&wind_speed_unit=ms&temperature_unit=fahrenheit&precipitation_unit=inch&temporal_resolution=hourly_3&forecast_days=7&timezone=America%2FNew_York';
	oyc_om_cached( 'oyc_wind_basin_' . $chunk, $url, 15 * MINUTE_IN_SECONDS );
}

/* Wave field (Open-Meteo Marine) for the Wave tab, one client chunk per request.
   ?grid=local|basin&chunk=N — CH=140 MUST match the JS loadWaves chunk size.
   Waves move slowly → cached 30 min. */
add_action( 'wp_ajax_oyc_waves',        'oyc_waves_proxy' );
add_action( 'wp_ajax_nopriv_oyc_waves', 'oyc_waves_proxy' );
function oyc_waves_proxy() {
	$grid  = ( isset( $_GET['grid'] ) && 'basin' === $_GET['grid'] ) ? 'basin' : 'local';
	$chunk = isset( $_GET['chunk'] ) ? max( 0, (int) $_GET['chunk'] ) : 0;
	list( $lat, $lon ) = oyc_named_grid( $grid );
	$cs = oyc_chunk_csv( $lat, $lon, $chunk, 140 );
	if ( null === $cs ) { oyc_send_raw_json( '[]' ); }
	$url = 'https://marine-api.open-meteo.com/v1/marine?latitude=' . $cs[0] . '&longitude=' . $cs[1]
		. '&hourly=wave_height&temporal_resolution=hourly_3&forecast_days=7&timezone=America%2FNew_York';
	oyc_om_cached( 'oyc_waves_' . $grid . '_' . $chunk, $url, 30 * MINUTE_IN_SECONDS );
}

/* Gulf Stream ocean current — velocity + direction over the Florida→Newfoundland
   corridor (1° grid, 23×33=759 pts), one client chunk per request. CH=140 MUST
   match the JS loadGulf chunk size. Ocean currents move slowly → cached 60 min.
   Endpoint: admin-ajax.php?action=oyc_gulfstream&chunk=N */
add_action( 'wp_ajax_oyc_gulfstream',        'oyc_gulfstream_proxy' );
add_action( 'wp_ajax_nopriv_oyc_gulfstream', 'oyc_gulfstream_proxy' );
function oyc_gulfstream_proxy() {
	$chunk = isset( $_GET['chunk'] ) ? max( 0, (int) $_GET['chunk'] ) : 0;
	list( $lat, $lon ) = oyc_grid_points( 46, 24, -80, -48, 1.0, 1.0 ); // must match the JS GS makeGrid()
	$cs = oyc_chunk_csv( $lat, $lon, $chunk, 140 );
	if ( null === $cs ) { oyc_send_raw_json( '[]' ); }
	$url = 'https://marine-api.open-meteo.com/v1/marine?latitude=' . $cs[0] . '&longitude=' . $cs[1]
		. '&hourly=ocean_current_velocity,ocean_current_direction&temporal_resolution=hourly_3&forecast_days=7&timezone=America%2FNew_York';
	oyc_om_cached( 'oyc_gs_' . $chunk, $url, 60 * MINUTE_IN_SECONDS );
}

/* Gulf Stream frontal analysis (NOAA OPC) — the authoritative north/south wall
   positions from IR-satellite SST fronts, updated ~daily. Parsed to lat/lon
   arrays for the map's Gulf Stream band. Cached 12h.
   Endpoint: admin-ajax.php?action=oyc_gs_wall */
add_action( 'wp_ajax_oyc_gs_wall',        'oyc_gs_wall_proxy' );
add_action( 'wp_ajax_nopriv_oyc_gs_wall', 'oyc_gs_wall_proxy' );
function oyc_gs_wall_coords( $s ) {
	$out = array();
	if ( preg_match_all( '/(\d{1,2}\.\d)N(\d{1,3}\.\d)W/', (string) $s, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $c ) { $out[] = array( (float) $c[1], -1 * (float) $c[2] ); }
	}
	return $out;
}
function oyc_gs_wall_proxy() {
	$cached = get_transient( 'oyc_gs_wall' );
	if ( false !== $cached ) { oyc_send_raw_json( $cached ); }

	$r = wp_remote_get( 'https://ocean.weather.gov/gulf_stream_text.php', array( 'timeout' => 12, 'headers' => array( 'User-Agent' => 'OYC-Weather/1.0' ) ) );
	$body = ( ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r ) ) ? (string) wp_remote_retrieve_body( $r ) : '';
	$pre  = preg_match( '/<pre[^>]*>(.*?)<\/pre>/is', $body, $pm ) ? $pm[1] : $body;

	$date  = preg_match( '/NORTH WALL DATA FOR ([0-9A-Z ]+?):/i', $pre, $dm ) ? trim( $dm[1] ) : '';
	$parts = preg_split( '/SOUTH WALL DATA FOR/i', $pre, 2 );
	$north_seg = preg_replace( '/.*NORTH WALL DATA FOR[^:]*:/is', '', $parts[0] );
	$south_seg = isset( $parts[1] ) ? preg_split( '/\n\s*2\.|FRONTAL DATA/i', $parts[1] )[0] : '';

	$north = oyc_gs_wall_coords( $north_seg );
	$south = oyc_gs_wall_coords( $south_seg );
	if ( count( $north ) < 5 ) {
		// parse failed / product down — cache a miss briefly so we retry, don't hide long
		$miss = wp_json_encode( array( 'ok' => false ) );
		set_transient( 'oyc_gs_wall', $miss, 30 * MINUTE_IN_SECONDS );
		oyc_send_raw_json( $miss );
	}
	$out = wp_json_encode( array( 'ok' => true, 'date' => $date, 'north' => $north, 'south' => $south ) );
	set_transient( 'oyc_gs_wall', $out, 12 * HOUR_IN_SECONDS );
	oyc_send_raw_json( $out );
}
