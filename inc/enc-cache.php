<?php
/**
 * NOAA chart tile cache for the Wind & Radar map.
 *
 * The map's NOAA chart (ENC Chart Display Service, a WMS) is slow and drops
 * tiles. This keeps a copy of each chart tile on our own server as a plain PNG
 * under /wp-content/uploads/oyc-enc/{z}/{x}/{y}.png, which Apache serves in
 * ~50 ms with long browser caching.
 *
 * How a tile reaches the map:
 *   1. The map asks for the static file. If it is there, done.
 *   2. If not (404), it asks admin-ajax oyc_enc_tile, which fetches that one
 *      tile from NOAA, saves it, and returns it. Every later viewer gets step 1.
 *   3. If that fails too, the map asks NOAA directly (as before).
 *
 * Home waters (western Long Island Sound) are pre-loaded in the background by
 * WP-Cron, a small batch every 15 minutes, so the first viewer is fast too.
 * Home-waters tiles are renewed weekly (NOAA updates the charts weekly); other
 * tiles older than OYC_ENC_MAX_AGE are deleted daily and re-fetched on demand. Only tiles inside OYC_ENC_AREA and zoom
 * 9-17 are cached, and the cache stops growing at OYC_ENC_MAX_BYTES, so the
 * endpoint can't be used to fill the disk. Not for navigation.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OYC_ENC_WMS', 'https://gis.charttools.noaa.gov/arcgis/rest/services/MCS/NOAAChartDisplay/MapServer/exts/MaritimeChartService/WMSServer' );
define( 'OYC_ENC_LAYERS', '2,3,4,5,6,7' );            // must match encLayer in inc/wind-map.php
define( 'OYC_ENC_MAX_AGE', 30 * DAY_IN_SECONDS );
/* Home-waters tiles are re-fetched once they are this old, so the chart there
   picks up NOAA's weekly updates (Local Notice to Mariners corrections). */
define( 'OYC_ENC_REFRESH', 7 * DAY_IN_SECONDS );
define( 'OYC_ENC_MAX_BYTES', 600 * 1024 * 1024 );     // 600 MB ceiling
/* Area we cache at all (Chesapeake to Cape Cod), and home waters we pre-load. */
define( 'OYC_ENC_AREA', '38.0,-77.0,42.5,-69.5' );     // south,west,north,east
define( 'OYC_ENC_HOME', '40.78,-73.84,41.08,-73.38' );
define( 'OYC_ENC_HOME_ZOOMS', '9,10,11,12,13,14,15,16' );   // every zoom the chart caches (~8,600 tiles, ~250 MB)
/* Tiles at this zoom and below are drawn at 2x pixels (more chart detail when
   zoomed out, but text and symbols come out half size). From the next zoom in,
   1x, so soundings and labels are readable. Must match ENC_HIRES_MAX in
   inc/wind-map.php. Bump OYC_ENC_VER whenever the tile request changes, so old
   cached tiles are thrown away. */
define( 'OYC_ENC_HIRES_MAX', 12 );
define( 'OYC_ENC_VER', 4 );
/* The "detail" chart layer. Zoomed out (zoom 10-11) NOAA draws its small-scale
   charts, where Long Island Sound is one flat light-blue area that looks just
   like the street map. Asking for the approach-scale charts only (ENC usage
   band 4, display_params IntendedUsage "4") gives white deep water, blue
   shallows and soundings there. Those charts don't cover open ocean, so this is
   a second layer over the normal one: where it is empty the normal chart shows
   through. Cached under oyc-enc/d/. Must match encDetail in inc/wind-map.php. */
define( 'OYC_ENC_DETAIL_ZOOMS', '10,11' );
define( 'OYC_ENC_DETAIL', '{"ECDISParameters":{"version":"10.9.1","DynamicParameters":{"Parameter":[{"name":"IntendedUsage","value":"4"}]}}}' );
/* The "safe water" layers s6..s60 and h6..h60 (never shown on the map). The
   passage checker and Autoroute in inc/wind-map.php read their pixels to find
   land and water shallower than the boat's safety depth (draft + 4 ft). NOAA
   draws them with the safety contour at that depth (6, 10, 12, 18, 30 or 60 ft,
   the charted contours; NOAA itself would pick the next deeper contour), and
   only the base and standard chart categories, so soundings and labels are
   left out: land is transparent, water shallower than the contour (and drying
   areas) is tinted, deeper water is white.
   s = the chart NOAA picks for the scale. Some approach-scale charts carry only
   coarse depth areas (parts of Fishers Island Sound read "shallow" at any
   contour), so h = harbour-scale charts only (IntendedUsage "5"), which have
   the real contours where they exist and are empty elsewhere. The page uses h
   where it has chart and s everywhere else. Zooms 11-13 only. Must match
   SAFE_FT in inc/wind-map.php. */
define( 'OYC_ENC_SAFE_FT', '6,10,12,18,30,60' );
define( 'OYC_ENC_SAFE_ZOOMS', '11,12,13' );

/** A tile variant we serve: '' (chart), 'd' (detail) or 's<ft>' (safe water). Anything else → false. */
function oyc_enc_variant( $v ) {
	$v = (string) $v;
	if ( '' === $v || 'd' === $v ) {
		return $v;
	}
	if ( preg_match( '/^(s|h)(\d{1,2})$/', $v, $m ) && in_array( (int) $m[2], array_map( 'intval', explode( ',', OYC_ENC_SAFE_FT ) ), true ) ) {
		return $m[1] . (int) $m[2];
	}
	if ( preg_match( '/^(c|cd|ch|cx|ce)(\d{1,2})$/', $v, $m ) && (int) $m[2] === (int) OYC_ENC_CHART_FT ) {
		return $m[1] . (int) $m[2];
	}
	return false;
}

/** Is $v a safe-water variant (s12, h12, ...)? */
function oyc_enc_is_safe( $v ) {
	return (bool) preg_match( '/^[sh]\d/', (string) $v );
}

/** The chart the member sees: c5 (normal), cd5 (detail layer, zoom 10-11), ch5 (harbour charts, zoom 10-11), ce5 (approach charts with every sounding, zoom 12-13), cx5 (harbour charts with every sounding, zoom 12+). */
function oyc_enc_is_contour( $v ) {
	return (bool) preg_match( '/^c[dhxe]?\d/', (string) $v );
}

/* The chart on the map is drawn with NOAA's shallow/safety/deep contours all
   at 5 ft (variant c5, and cd5 for the zoom 10-11 detail layer), so only water
   shallower than 5 ft is shaded blue and everything deeper is white with its
   soundings (Brad, 2026-11-15). Approach-scale cells (e.g. Fishers Island
   Sound) still draw flat blue at any contour, so at zoom 10-12 a harbour-band
   layer ch5 (IntendedUsage "5", real contours and soundings) sits on top. NOAA's default shades all water under ~30 m.
   From zoom 13 in, the same approach cells are still flat blue with no
   soundings (Fishers Island Sound, Brad 2026-11-15), and the harbour charts hide
   their soundings at these scales (NOAA's SCAMIN rule), so cx5 = harbour charts
   with HonorScamin 1 (every sounding drawn) sits on top at zoom 13-17.
   The home-waters warmer keeps c5 / cd5 loaded; wind-map.php reads this. */
define( 'OYC_ENC_CHART_FT', 5 );
/* Soundings by zoom (Brad, 2026-11-15): one plain chart style at every zoom,
   fewer soundings as you zoom out but the shoals still marked.
     z10      c5 + cd5 + ch5 layers, as before
     z11      c5, and where c5 is only a flat blue area (most of the Sound at
              this scale) cd5, then ch5 where cd5 is flat blue too
     z12-13   ce5 = approach charts with every sounding (NOAA compiles these
              with few soundings, chosen to show the shoals), and cx5 where
              an approach chart is flat blue (Fishers Island Sound)
     z14+     cx5 = harbour charts with every sounding
   The z11-13 mixing is done in the browser from cached tiles: see encMix in
   inc/wind-map.php. */


/** NOAA display settings for a safe-water or contour variant (contour in metres). */
function oyc_enc_safe_params( $v ) {
	preg_match( '/^([a-z]+)(\d+)$/', (string) $v, $mm );
	$k = $mm[1];
	$m = round( (int) $mm[2] * 0.3048, 2 );
	if ( 10 === (int) $mm[2] ) {
		$m = 3; // 10 ft: NOAA uses the next contour at or deeper than this, so ask for 3 m (9.8 ft), the metric charts' contour, rather than 3.05 m, which would jump to 5 m.
	}
	$p = array();
	if ( 's' === $k || 'h' === $k ) {
		$p[] = '{"name":"DisplayCategory","value":"1,2"}';
	}
	foreach ( array( 'ShallowContour', 'SafetyContour', 'DeepContour', 'SafetyDepth' ) as $n ) {
		$p[] = '{"name":"' . $n . '","value":' . $m . '}';
	}
	if ( 'h' === $k || 'ch' === $k || 'cx' === $k ) {
		$p[] = '{"name":"IntendedUsage","value":"5"}';
	}
	if ( 'ce' === $k ) {
		$p[] = '{"name":"IntendedUsage","value":"4"}';
	}
	if ( 'cx' === $k || 'ce' === $k ) {
		$p[] = '{"name":"HonorScamin","value":1}';
	} elseif ( 'cd' === $k ) {
		$p[] = '{"name":"IntendedUsage","value":"4"}';
	}
	return '{"ECDISParameters":{"version":"10.9.1","DynamicParameters":{"Parameter":[' . implode( ',', $p ) . ']}}}';
}

function oyc_enc_dir() {
	$u = wp_upload_dir();
	return trailingslashit( $u['basedir'] ) . 'oyc-enc';
}
function oyc_enc_url() {
	$u = wp_upload_dir();
	return set_url_scheme( trailingslashit( $u['baseurl'] ) . 'oyc-enc' );
}

/* Tile x/y range for a lat/lon box at zoom z (Web Mercator). */
function oyc_enc_tile_x( $lon, $z ) {
	return (int) floor( ( $lon + 180 ) / 360 * ( 1 << $z ) );
}
function oyc_enc_tile_y( $lat, $z ) {
	$r = deg2rad( $lat );
	return (int) floor( ( 1 - log( tan( $r ) + 1 / cos( $r ) ) / M_PI ) / 2 * ( 1 << $z ) );
}
function oyc_enc_in_box( $z, $x, $y, $box ) {
	list( $s, $w, $n, $e ) = array_map( 'floatval', explode( ',', $box ) );
	return $x >= oyc_enc_tile_x( $w, $z ) && $x <= oyc_enc_tile_x( $e, $z )
		&& $y >= oyc_enc_tile_y( $n, $z ) && $y <= oyc_enc_tile_y( $s, $z );
}

/* The WMS GetMap URL for one tile, exactly as the map's EncWMS layer builds it
   (EPSG:3857, 2x pixels at zoom OYC_ENC_HIRES_MAX and below). */
function oyc_enc_wms_url( $z, $x, $y, $v = '' ) {
	$o    = 20037508.342789244;
	$size = 2 * $o / ( 1 << $z );
	$bbox = array( -$o + $x * $size, $o - ( $y + 1 ) * $size, -$o + ( $x + 1 ) * $size, $o - $y * $size );
	$px   = $z <= OYC_ENC_HIRES_MAX ? 512 : 256;
	return add_query_arg( array(
		'service'     => 'WMS',
		'request'     => 'GetMap',
		'version'     => '1.3.0',
		'layers'      => OYC_ENC_LAYERS,
		'styles'      => '',
		'format'      => 'image/png',
		'transparent' => 'true',
		'crs'         => 'EPSG:3857',
		'width'       => $px,
		'height'      => $px,
		'bbox'        => implode( ',', $bbox ),
	) + ( 'd' === $v ? array( 'display_params' => rawurlencode( OYC_ENC_DETAIL ) ) : ( ( oyc_enc_is_safe( $v ) || oyc_enc_is_contour( $v ) ) ? array( 'display_params' => rawurlencode( oyc_enc_safe_params( $v ) ) ) : array() ) ), OYC_ENC_WMS );
}

function oyc_enc_file( $z, $x, $y, $v = '' ) {
	return oyc_enc_dir() . ( '' !== (string) $v ? '/' . $v : '' ) . "/$z/$x/$y.png";
}

/** Is (z, v) a layer/zoom we serve? */
function oyc_enc_zoom_ok( $z, $v ) {
	if ( oyc_enc_is_safe( $v ) ) {
		return in_array( $z, array_map( 'intval', explode( ',', OYC_ENC_SAFE_ZOOMS ) ), true );
	}
	if ( oyc_enc_is_contour( $v ) ) {
		if ( 0 === strpos( $v, 'ch' ) ) {
			return $z >= 10 && $z <= 11;
		}
		if ( 0 === strpos( $v, 'ce' ) ) {
			return $z >= 12 && $z <= 13;
		}
		if ( 0 === strpos( $v, 'cx' ) ) {
			return $z >= 12 && $z <= 17;
		}
		return 0 === strpos( $v, 'cd' ) ? in_array( $z, array_map( 'intval', explode( ',', OYC_ENC_DETAIL_ZOOMS ) ), true ) : ( $z >= 9 && $z <= 17 );
	}
	return 'd' === $v ? in_array( $z, array_map( 'intval', explode( ',', OYC_ENC_DETAIL_ZOOMS ) ), true ) : ( $z >= 9 && $z <= 17 );
}

/** One GetMap request. Returns the PNG bytes, '' on a failure, or 'XML' when
    NOAA answered but with an error document instead of a picture. */
function oyc_enc_get( $url ) {
	$r = wp_remote_get( $url, array( 'timeout' => 25 ) );
	if ( is_wp_error( $r ) ) {
		return '';
	}
	$png = wp_remote_retrieve_body( $r );
	if ( 200 === (int) wp_remote_retrieve_response_code( $r ) && strlen( $png ) >= 60 && "\x89PNG" === substr( $png, 0, 4 ) ) {
		return $png;
	}
	if ( '' !== $png && '<' === substr( ltrim( $png ), 0, 1 ) ) {
		$GLOBALS['oyc_enc_err'] = mb_substr( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $png ) ) ), 0, 300 );
		return 'XML';
	}
	return '';
}

/** Fetch one tile from NOAA and store it. Returns the PNG bytes, or ''. */
function oyc_enc_fetch( $z, $x, $y, $v = '' ) {
	$png = oyc_enc_get( oyc_enc_wms_url( $z, $x, $y, $v ) );
	if ( '' === $png || 'XML' === $png ) {
		return '';
	}
	$bytes = (int) get_option( 'oyc_enc_bytes', 0 );
	if ( $bytes < OYC_ENC_MAX_BYTES ) {
		$f = oyc_enc_file( $z, $x, $y, $v );
		oyc_enc_htaccess();
		if ( wp_mkdir_p( dirname( $f ) ) ) {
			$tmp = $f . '.' . wp_generate_password( 6, false ) . '.tmp';
			if ( false !== file_put_contents( $tmp, $png ) && rename( $tmp, $f ) ) {
				update_option( 'oyc_enc_bytes', $bytes + strlen( $png ), false );
			} else {
				@unlink( $tmp );
			}
		}
	}
	return $png;
}

/* Apache rules for the cache folder: a missing tile is a fast plain 404 (the
   folder's own RewriteEngine replaces WordPress's rewrite, which would boot all
   of WordPress just to say "not found"), and tiles are cached by browsers. */
function oyc_enc_htaccess() {
	$dir = oyc_enc_dir();
	if ( file_exists( $dir . '/.htaccess' ) || ! wp_mkdir_p( $dir ) ) {
		return;
	}
	@file_put_contents( $dir . '/.htaccess', "# OYC NOAA chart tile cache (inc/enc-cache.php)\n<IfModule mod_rewrite.c>\nRewriteEngine On\n</IfModule>\nErrorDocument 404 \"Not cached\"\n<IfModule mod_headers.c>\nHeader set Cache-Control \"public, max-age=604800\"\n</IfModule>\n" ); // phpcs:ignore
}

function oyc_enc_fresh( $f ) {
	return file_exists( $f ) && ( time() - filemtime( $f ) ) < OYC_ENC_MAX_AGE;
}

/* admin-ajax: one tile, from the cache or fetched from NOAA and cached. */
add_action( 'wp_ajax_oyc_enc_tile', 'oyc_enc_tile' );
add_action( 'wp_ajax_nopriv_oyc_enc_tile', 'oyc_enc_tile' );
function oyc_enc_tile() {
	$z = isset( $_GET['z'] ) ? (int) $_GET['z'] : -1;
	$x = isset( $_GET['x'] ) ? (int) $_GET['x'] : -1;
	$y = isset( $_GET['y'] ) ? (int) $_GET['y'] : -1;
	$v = oyc_enc_variant( isset( $_GET['v'] ) ? sanitize_key( wp_unslash( $_GET['v'] ) ) : '' );
	$n = $z >= 0 && $z < 30 ? ( 1 << $z ) : 0;
	if ( false === $v || ! oyc_enc_zoom_ok( $z, $v ) || $x < 0 || $y < 0 || $x >= $n || $y >= $n ) {
		status_header( 400 );
		exit;
	}
	$f   = oyc_enc_file( $z, $x, $y, $v );
	$png = '';
	if ( oyc_enc_fresh( $f ) ) {
		$png = (string) file_get_contents( $f );
	} elseif ( oyc_enc_in_box( $z, $x, $y, OYC_ENC_AREA ) ) {
		$png = oyc_enc_fetch( $z, $x, $y, $v );
	} else {
		/* outside our waters: send the map straight to NOAA */
		wp_redirect( oyc_enc_wms_url( $z, $x, $y, $v ), 302 );
		exit;
	}
	if ( '' === $png ) {
		status_header( 502 );
		header( 'Cache-Control: no-store' );
		exit;
	}
	status_header( 200 );
	header( 'Content-Type: image/png' );
	header( 'Content-Length: ' . strlen( $png ) );
	header( 'Cache-Control: public, max-age=604800' );
	header( 'X-Robots-Tag: noindex' );
	echo $png; // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
}

/* admin-ajax oyc_enc_probe: a read-only test bench for NOAA's chart display
   settings, used to tune OYC_ENC_DETAIL (NOAA's chart server can't be reached
   from the developer's sandbox, but this web server can). Fetches one tile
   (z, x, y inside OYC_ENC_AREA; default: open water off Execution Rock) twice,
   with NOAA's defaults and with the settings in p ("Name:value,Name:value",
   names from a fixed list, simple values only), and returns both pictures as
   base64 with NOAA's answers. Changes nothing; results cached 10 minutes; a
   new request at most every 10 seconds. */
add_action( 'wp_ajax_oyc_enc_probe', 'oyc_enc_probe' );
add_action( 'wp_ajax_nopriv_oyc_enc_probe', 'oyc_enc_probe' );
function oyc_enc_probe() {
	$z = isset( $_GET['z'] ) ? (int) $_GET['z'] : 13;
	$x = isset( $_GET['x'] ) ? (int) $_GET['x'] : 2418;
	$y = isset( $_GET['y'] ) ? (int) $_GET['y'] : 3074;
	if ( $z < 9 || $z > 16 || ! oyc_enc_in_box( $z, $x, $y, OYC_ENC_AREA ) ) {
		wp_send_json( array( 'error' => 'tile outside the cached area' ), 400 );
	}
	$names  = array( 'DisplayDepthUnits', 'TwoDepthShades', 'ShallowContour', 'SafetyContour', 'SafetyDepth', 'DeepContour', 'TextHalo', 'DisplayCategory', 'HonorScamin', 'IntendedUsage', 'ColorScheme', 'AreaSymbolizationType', 'PointSymbolizationType', 'DisplaySafeSoundings', 'OptionalDeepSoundings', 'LabelContours', 'LabelSafetyContours', 'DisplayNOBJNM', 'RemoveDuplicateText', 'DisplayLightSectors', 'DisplayFrames', 'DataQuality', 'IsolatedDangers' );
	$params = array();
	$raw    = isset( $_GET['p'] ) ? sanitize_text_field( wp_unslash( $_GET['p'] ) ) : '';
	foreach ( array_filter( explode( ',', $raw ) ) as $pair ) {
		$kv = explode( ':', $pair, 2 );
		if ( 2 === count( $kv ) && in_array( $kv[0], $names, true ) && preg_match( '/^[0-9.;]{1,20}$/', $kv[1] ) ) {
			$v        = str_replace( ';', ',', $kv[1] );
			$params[] = array( 'name' => $kv[0], 'value' => is_numeric( $v ) ? $v + 0 : $v );
		}
	}
	$key = 'oyc_enc_pr_' . md5( $z . '/' . $x . '/' . $y . '|' . wp_json_encode( $params ) . '|' . ( isset( $_GET['v'] ) ? '1' : '' ) );
	$out = get_transient( $key );
	if ( ! $out ) {
		if ( get_transient( 'oyc_enc_probe_lock' ) ) {
			wp_send_json( array( 'wait' => 'try again in 10 seconds' ), 429 );
		}
		set_transient( 'oyc_enc_probe_lock', 1, 10 );
		$dp   = $params ? wp_json_encode( array( 'ECDISParameters' => array( 'version' => isset( $_GET['v'] ) ? '10.6.1 P5' : '10.9.1', 'DynamicParameters' => array( 'Parameter' => $params ) ) ) ) : '';
		$wms  = oyc_enc_wms_url( $z, $x, $y );
		$out  = array( 'tile' => "$z/$x/$y", 'display_params' => $dp );
		foreach ( array( 'default' => $wms, 'with_p' => $dp ? $wms . '&display_params=' . rawurlencode( $dp ) : '' ) as $k => $url ) {
			if ( ! $url ) {
				continue;
			}
			$r = wp_remote_get( $url, array( 'timeout' => 25 ) );
			if ( is_wp_error( $r ) ) {
				$out[ $k ] = $r->get_error_message();
				continue;
			}
			$b         = wp_remote_retrieve_body( $r );
			$isp       = "\x89PNG" === substr( $b, 0, 4 );
			$out[ $k ] = array(
				'code' => wp_remote_retrieve_response_code( $r ),
				'md5'  => md5( $b ),
				'body' => $isp ? 'data:image/png;base64,' . base64_encode( $b ) : mb_substr( wp_strip_all_tags( $b ), 0, 600 ),
			);
		}
		set_transient( $key, $out, 10 * MINUTE_IN_SECONDS );
	}
	header( 'X-Robots-Tag: noindex' );
	wp_send_json( $out );
}

/* admin-ajax oyc_enc_fi: read-only test of NOAA's GetFeatureInfo at one point
   (lat, lon inside OYC_ENC_AREA; fmt = xml | html | json), to see whether the
   chart server can give the depth area (DRVAL1-DRVAL2) and soundings at a
   waypoint. Shares the probe's 10-second lock; answers cached 10 minutes. */
add_action( 'wp_ajax_oyc_enc_fi', 'oyc_enc_fi' );
add_action( 'wp_ajax_nopriv_oyc_enc_fi', 'oyc_enc_fi' );
function oyc_enc_fi() {
	$lat = isset( $_GET['lat'] ) ? (float) $_GET['lat'] : 0;
	$lon = isset( $_GET['lon'] ) ? (float) $_GET['lon'] : 0;
	list( $s, $w, $n, $e ) = array_map( 'floatval', explode( ',', OYC_ENC_AREA ) );
	if ( $lat < $s || $lat > $n || $lon < $w || $lon > $e ) {
		wp_send_json( array( 'error' => 'outside the chart area' ), 400 );
	}
	$fmts = array( 'xml' => 'text/xml', 'html' => 'text/html', 'json' => 'application/json', 'plain' => 'text/plain' );
	$fk   = isset( $_GET['fmt'] ) && is_string( $_GET['fmt'] ) ? sanitize_key( wp_unslash( $_GET['fmt'] ) ) : 'xml';
	if ( ! isset( $fmts[ $fk ] ) ) {
		$fk = 'xml';
	}
	$key  = 'oyc_enc_fi_' . md5( round( $lat, 5 ) . ',' . round( $lon, 5 ) . $fk );
	$out  = get_transient( $key );
	if ( ! $out ) {
		if ( get_transient( 'oyc_enc_probe_lock' ) ) {
			wp_send_json( array( 'wait' => 'try again in 10 seconds' ), 429 );
		}
		set_transient( 'oyc_enc_probe_lock', 1, 10 );
		$o  = 20037508.342789244;
		$x  = $lon * $o / 180;
		$y  = log( tan( ( 90 + $lat ) * M_PI / 360 ) ) * $o / M_PI;
		$h  = 50; // metres each side: 101 px at ~1 m
		$url = add_query_arg( array(
			'service'      => 'WMS',
			'request'      => 'GetFeatureInfo',
			'version'      => '1.3.0',
			'layers'       => OYC_ENC_LAYERS,
			'query_layers' => OYC_ENC_LAYERS,
			'styles'       => '',
			'crs'          => 'EPSG:3857',
			'bbox'         => implode( ',', array( $x - $h, $y - $h, $x + $h, $y + $h ) ),
			'width'        => 101,
			'height'       => 101,
			'i'            => 50,
			'j'            => 50,
			'info_format'  => $fmts[ $fk ],
			'feature_count' => 50,
		), OYC_ENC_WMS );
		$r   = wp_remote_get( $url, array( 'timeout' => 15 ) );
		$out = is_wp_error( $r ) ? array( 'error' => $r->get_error_message() ) : array(
			'code' => wp_remote_retrieve_response_code( $r ),
			'type' => wp_remote_retrieve_header( $r, 'content-type' ),
			'body' => mb_substr( (string) wp_remote_retrieve_body( $r ), 0, 20000 ),
		);
		set_transient( $key, $out, 10 * MINUTE_IN_SECONDS );
	}
	header( 'X-Robots-Tag: noindex' );
	wp_send_json( $out );
}

/* admin-ajax oyc_enc_depth: the charted depth at a waypoint (Brad, 2026-11-15:
   "the depth should come from the soundings"). One NOAA GetFeatureInfo query
   around the point (OYC_ENC_DEP_R metres each side) returns the chart features
   there: the nearest sounding (SOUNDG) and the depth area (DEPARE, DRVAL1-2) of
   the most detailed chart. Load: each point (rounded to ~10 m) is asked once and
   kept 30 days; only ONE query runs at a time across the whole site (a 12 s
   lock), with an 8 s timeout, and a page asking while another runs gets
   {busy:1} and retries a few seconds later. &raw=1 returns NOAA's answer as-is. */
define( 'OYC_ENC_DEP_R', 300 );
add_action( 'wp_ajax_oyc_enc_depth', 'oyc_enc_depth' );
add_action( 'wp_ajax_nopriv_oyc_enc_depth', 'oyc_enc_depth' );
function oyc_enc_depth() {
	header( 'X-Robots-Tag: noindex' );
	$lat = isset( $_GET['lat'] ) ? round( (float) $_GET['lat'], 4 ) : 0;
	$lon = isset( $_GET['lon'] ) ? round( (float) $_GET['lon'], 4 ) : 0;
	list( $s, $w, $n, $e ) = array_map( 'floatval', explode( ',', OYC_ENC_AREA ) );
	if ( $lat < $s || $lat > $n || $lon < $w || $lon > $e ) {
		wp_send_json( array( 'none' => 1 ) );
	}
	$raw = ! empty( $_GET['raw'] );
	$key = 'oyc_dep_' . md5( $lat . ',' . $lon . '|' . OYC_ENC_DEP_R . ( $raw ? '|raw' : '' ) );
	$out = get_transient( $key );
	if ( false !== $out ) {
		header( 'Cache-Control: public, max-age=86400' );
		wp_send_json( $out );
	}
	if ( get_transient( 'oyc_enc_dep_lock' ) ) {
		header( 'Cache-Control: no-store' );
		wp_send_json( array( 'busy' => 1 ) );
	}
	set_transient( 'oyc_enc_dep_lock', 1, 12 );
	$o  = 20037508.342789244;
	$x  = $lon * $o / 180;
	$y  = log( tan( ( 90 + $lat ) * M_PI / 360 ) ) * $o / M_PI;
	$h  = OYC_ENC_DEP_R / cos( deg2rad( $lat ) ); // ground metres → Web Mercator metres
	$px = 61;
	$url = add_query_arg( array(
		'service'       => 'WMS',
		'request'       => 'GetFeatureInfo',
		'version'       => '1.3.0',
		'layers'        => OYC_ENC_LAYERS,
		'query_layers'  => OYC_ENC_LAYERS,
		'styles'        => '',
		'crs'           => 'EPSG:3857',
		'bbox'          => implode( ',', array( $x - $h, $y - $h, $x + $h, $y + $h ) ),
		'width'         => $px,
		'height'        => $px,
		'i'             => 30,
		'j'             => 30,
		'info_format'   => 'text/xml',
		'feature_count' => 200,
	), OYC_ENC_WMS );
	$r = wp_remote_get( $url, array( 'timeout' => 8 ) );
	delete_transient( 'oyc_enc_dep_lock' );
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		header( 'Cache-Control: no-store' );
		wp_send_json( array( 'error' => 1 ) );
	}
	$body = (string) wp_remote_retrieve_body( $r );
	if ( $raw ) {
		$out = array( 'body' => mb_substr( $body, 0, 60000 ) );
		set_transient( $key, $out, 10 * MINUTE_IN_SECONDS );
		wp_send_json( $out );
	}
	$out = oyc_enc_depth_parse( $body, $lat, $lon );
	set_transient( $key, $out, 30 * DAY_IN_SECONDS );
	header( 'Cache-Control: public, max-age=86400' );
	wp_send_json( $out );
}

/** Pull the depth area and the soundings out of NOAA's GetFeatureInfo XML. Depths in metres. */
function oyc_enc_depth_parse( $body, $lat, $lon ) {
	$area  = null;
	$snd   = array();
	$types = array();
	if ( preg_match_all( '/<Feature\b([^>]*)>(.*?)<\/Feature>/s', $body, $fm, PREG_SET_ORDER ) ) {
		foreach ( $fm as $f ) {
			$acr = preg_match( '/acronym="(\w+)"/', $f[1], $m ) ? $m[1] : '';
			$sc  = preg_match( '/cscl="(\d+)"/', $f[1], $m ) ? (int) $m[1] : 0;
			$types[ $acr ] = true;
			$a = array();
			if ( preg_match_all( '/<Attribute\b[^>]*acronym="(\w+)"[^>]*value="([^"]*)"/', $f[2], $am, PREG_SET_ORDER ) ) {
				foreach ( $am as $x ) {
					$a[ $x[1] ] = html_entity_decode( $x[2] );
				}
			}
			if ( 'DEPARE' === $acr && isset( $a['DRVAL1'] ) && ( ! $area || ( $sc && $sc < $area['scale'] ) ) ) {
				$area = array( 'min' => (float) $a['DRVAL1'], 'max' => isset( $a['DRVAL2'] ) && '' !== $a['DRVAL2'] ? (float) $a['DRVAL2'] : null, 'scale' => $sc );
			}
			if ( 'SOUNDG' === $acr || isset( $a['DEPTH'] ) ) {
				/* a sounding: depth, and its position if NOAA gives one */
				$d = isset( $a['DEPTH'] ) ? $a['DEPTH'] : ( isset( $a['VALSOU'] ) ? $a['VALSOU'] : null );
				$p = null;
				foreach ( array( 'geometry', 'GEOMETRY', 'coordinates', 'position' ) as $g ) {
					if ( isset( $a[ $g ] ) && preg_match( '/(-?\d+\.\d+)[ ,]+(-?\d+\.\d+)/', $a[ $g ], $m ) ) {
						$p = array( (float) $m[1], (float) $m[2] );
					}
				}
				if ( null !== $d && is_numeric( $d ) ) {
					$snd[] = array( 'm' => (float) $d, 'scale' => $sc, 'pos' => $p );
				}
			}
		}
	}
	return array( 'area' => $area, 'soundings' => array_slice( $snd, 0, 40 ), 'types' => array_keys( $types ), 'r' => OYC_ENC_DEP_R );
}

/* ── Background: pre-load home waters, prune old tiles ───────────────────── */

add_filter( 'cron_schedules', function ( $s ) {
	if ( ! isset( $s['oyc_15min'] ) ) {
		$s['oyc_15min'] = array( 'interval' => 15 * MINUTE_IN_SECONDS, 'display' => 'OYC: every 15 minutes' );
	}
	return $s;
} );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'oyc_enc_warm' ) ) {
		wp_schedule_event( time() + 120, 'oyc_15min', 'oyc_enc_warm' );
	}
	if ( ! wp_next_scheduled( 'oyc_enc_prune' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'oyc_enc_prune' );
	}
} );
/* Tile request changed (OYC_ENC_VER bumped): empty the cache once so every
   tile is re-fetched the new way. */
add_action( 'init', function () {
	if ( (int) get_option( 'oyc_enc_ver', 1 ) >= OYC_ENC_VER ) {
		return;
	}
	update_option( 'oyc_enc_ver', OYC_ENC_VER, true );
	$dir = oyc_enc_dir();
	if ( is_dir( $dir ) ) {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) {
			if ( $f->isDir() ) {
				@rmdir( $f->getPathname() );
			} elseif ( '.htaccess' !== $f->getFilename() ) {
				@unlink( $f->getPathname() );
			}
		}
	}
	update_option( 'oyc_enc_bytes', 0, false );
}, 20 );
add_action( 'switch_theme', function () {
	foreach ( array( 'oyc_enc_warm', 'oyc_enc_prune' ) as $h ) {
		$ts = wp_next_scheduled( $h );
		if ( $ts ) {
			wp_unschedule_event( $ts, $h );
		}
	}
} );

/** Every home-waters tile, in order: [[z,x,y], ...]. */
function oyc_enc_home_tiles() {
	list( $s, $w, $n, $e ) = array_map( 'floatval', explode( ',', OYC_ENC_HOME ) );
	$out = array();
	foreach ( array_map( 'intval', explode( ',', OYC_ENC_HOME_ZOOMS ) ) as $z ) {
		for ( $x = oyc_enc_tile_x( $w, $z ); $x <= oyc_enc_tile_x( $e, $z ); $x++ ) {
			for ( $y = oyc_enc_tile_y( $n, $z ); $y <= oyc_enc_tile_y( $s, $z ); $y++ ) {
				$out[] = array( $z, $x, $y, 'c' . OYC_ENC_CHART_FT );
				if ( oyc_enc_zoom_ok( $z, 'd' ) ) {
					$out[] = array( $z, $x, $y, 'cd' . OYC_ENC_CHART_FT );
				}
				foreach ( array( 'ch', 'ce' ) as $k ) {
					if ( oyc_enc_zoom_ok( $z, $k . OYC_ENC_CHART_FT ) ) {
						$out[] = array( $z, $x, $y, $k . OYC_ENC_CHART_FT );
					}
				}
				if ( $z >= 12 && $z <= 13 ) {   // cx5 fills in where z12-13 approach charts are flat blue
					$out[] = array( $z, $x, $y, 'cx' . OYC_ENC_CHART_FT );
				}
			}
		}
	}
	return $out;
}

/* Fetch up to 40 missing or ageing home tiles per run (about a minute of work).
   A home tile is refreshed once it is OYC_ENC_REFRESH (7 days) old, so home
   waters show NOAA's weekly chart updates; the old tile stays until the new one
   arrives, so there are never gaps. 40 tiles every 15 minutes is ~3,800 a day,
   so all ~8,600 home tiles are renewed in under three days each week. */
add_action( 'oyc_enc_warm', 'oyc_enc_warm' );
function oyc_enc_warm( $limit = 40 ) {
	if ( get_transient( 'oyc_enc_warm_lock' ) ) {
		return 0;
	}
	set_transient( 'oyc_enc_warm_lock', 1, 10 * MINUTE_IN_SECONDS );
	@set_time_limit( 180 ); // phpcs:ignore
	$done  = 0;
	$fails = 0;
	foreach ( oyc_enc_home_tiles() as $t ) {
		if ( $done >= $limit || $fails >= 3 ) {
			break;
		}
		$f = oyc_enc_file( $t[0], $t[1], $t[2], $t[3] );
		if ( file_exists( $f ) && ( time() - filemtime( $f ) ) < OYC_ENC_REFRESH ) {
			continue;
		}
		if ( '' !== oyc_enc_fetch( $t[0], $t[1], $t[2], $t[3] ) ) {
			$done++;
		} else {
			$fails++;   // NOAA having a bad moment: try again next run
		}
	}
	delete_transient( 'oyc_enc_warm_lock' );
	return $done;
}

/* Daily: delete tiles past OYC_ENC_MAX_AGE (re-fetched on demand or by the
   warmer) and recount the cache size. */
add_action( 'oyc_enc_prune', 'oyc_enc_prune' );
function oyc_enc_prune() {
	$dir = oyc_enc_dir();
	if ( ! is_dir( $dir ) ) {
		update_option( 'oyc_enc_bytes', 0, false );
		return;
	}
	$bytes = 0;
	$cut   = time() - OYC_ENC_MAX_AGE;
	$it    = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $f ) {
		if ( ! $f->isFile() ) {
			continue;
		}
		if ( $f->getMTime() < $cut || '.tmp' === substr( $f->getFilename(), -4 ) ) {
			@unlink( $f->getPathname() );
		} else {
			$bytes += $f->getSize();
		}
	}
	update_option( 'oyc_enc_bytes', $bytes, false );
}
