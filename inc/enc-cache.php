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
 * Tiles older than OYC_ENC_MAX_AGE are deleted daily and re-fetched on demand
 * (NOAA updates the charts weekly). Only tiles inside OYC_ENC_AREA and zoom
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
define( 'OYC_ENC_MAX_BYTES', 600 * 1024 * 1024 );     // 600 MB ceiling
/* Area we cache at all (Chesapeake to Cape Cod), and home waters we pre-load. */
define( 'OYC_ENC_AREA', '38.0,-77.0,42.5,-69.5' );     // south,west,north,east
define( 'OYC_ENC_HOME', '40.78,-73.84,41.08,-73.38' );
define( 'OYC_ENC_HOME_ZOOMS', '10,11,12,13,14' );
/* Tiles at this zoom and below are drawn at 2x pixels (more chart detail when
   zoomed out, but text and symbols come out half size). From the next zoom in,
   1x, so soundings and labels are readable. Must match ENC_HIRES_MAX in
   inc/wind-map.php. Bump OYC_ENC_VER whenever the tile request changes, so old
   cached tiles are thrown away. */
define( 'OYC_ENC_HIRES_MAX', 12 );
define( 'OYC_ENC_VER', 2 );

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
function oyc_enc_wms_url( $z, $x, $y ) {
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
	), OYC_ENC_WMS );
}

function oyc_enc_file( $z, $x, $y ) {
	return oyc_enc_dir() . "/$z/$x/$y.png";
}

/** Fetch one tile from NOAA and store it. Returns the PNG bytes, or ''. */
function oyc_enc_fetch( $z, $x, $y ) {
	$r = wp_remote_get( oyc_enc_wms_url( $z, $x, $y ), array( 'timeout' => 25 ) );
	if ( is_wp_error( $r ) || 200 !== (int) wp_remote_retrieve_response_code( $r ) ) {
		return '';
	}
	$png = wp_remote_retrieve_body( $r );
	if ( strlen( $png ) < 60 || "\x89PNG" !== substr( $png, 0, 4 ) ) {   // NOAA sends XML on errors
		return '';
	}
	$bytes = (int) get_option( 'oyc_enc_bytes', 0 );
	if ( $bytes < OYC_ENC_MAX_BYTES ) {
		$f = oyc_enc_file( $z, $x, $y );
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
	$n = $z >= 0 && $z < 30 ? ( 1 << $z ) : 0;
	if ( $z < 9 || $z > 17 || $x < 0 || $y < 0 || $x >= $n || $y >= $n ) {
		status_header( 400 );
		exit;
	}
	$f   = oyc_enc_file( $z, $x, $y );
	$png = '';
	if ( oyc_enc_fresh( $f ) ) {
		$png = (string) file_get_contents( $f );
	} elseif ( oyc_enc_in_box( $z, $x, $y, OYC_ENC_AREA ) ) {
		$png = oyc_enc_fetch( $z, $x, $y );
	} else {
		/* outside our waters: send the map straight to NOAA */
		wp_redirect( oyc_enc_wms_url( $z, $x, $y ), 302 );
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
				$out[] = array( $z, $x, $y );
			}
		}
	}
	return $out;
}

/* Fetch up to 25 missing or stale home tiles per run (about a minute of work
   at most); a full pass over home waters takes a few hours the first time. */
add_action( 'oyc_enc_warm', 'oyc_enc_warm' );
function oyc_enc_warm( $limit = 25 ) {
	if ( get_transient( 'oyc_enc_warm_lock' ) ) {
		return 0;
	}
	set_transient( 'oyc_enc_warm_lock', 1, 10 * MINUTE_IN_SECONDS );
	$done  = 0;
	$fails = 0;
	foreach ( oyc_enc_home_tiles() as $t ) {
		if ( $done >= $limit || $fails >= 3 ) {
			break;
		}
		if ( oyc_enc_fresh( oyc_enc_file( $t[0], $t[1], $t[2] ) ) ) {
			continue;
		}
		if ( '' !== oyc_enc_fetch( $t[0], $t[1], $t[2] ) ) {
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
