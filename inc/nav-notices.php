<?php
/**
 * Coast Guard Local Notice to Mariners (District 1) on the Wind & Radar map.
 *
 * The weekly District 1 Local Notice to Mariners is where the Army Corps'
 * dredging and survey work, buoys off station, bridge work, safety zones and
 * marine construction are announced. NAVCEN (the Coast Guard Navigation
 * Center) publishes that data as GeoJSON files, listed on its Maritime Safety
 * Information page (navcen.uscg.gov/msi).
 *
 * How it works:
 *   - Twice a day WP-Cron reads the MSI page, finds the District 1 Local Notice
 *     GeoJSON files, downloads them and keeps only the notices that touch Long
 *     Island Sound and its approaches (OYC_NN_BOX). The result is one small
 *     option (oyc_nn_cache), so the map never waits on NAVCEN.
 *   - The map's "Notices" layer asks admin-ajax oyc_nn for that list and draws
 *     a warning pin per notice (plus its outline for zones and lines).
 *   - admin-ajax oyc_nn_probe is a read-only test bench: NAVCEN can't be reached
 *     from the developer's sandbox, but this web server can, so it reports which
 *     files were found and what their fields look like.
 *
 * File URLs can be pinned with the OYC_NN_SOURCES constant (comma-separated)
 * if NAVCEN renames them. Not for navigation.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OYC_NN_MSI', 'https://www.navcen.uscg.gov/msi' );
/* Long Island Sound from the East River (Hell Gate) to Block Island Sound,
   with its harbors and rivers: south, west, north, east. */
define( 'OYC_NN_BOX', '40.70,-73.97,41.45,-71.75' );
define( 'OYC_NN_MAX_ITEMS', 400 );
define( 'OYC_NN_UA', 'Mozilla/5.0 (compatible; OYC-website/1.0; +https://orientayachtclub.com)' );

/** GET a NAVCEN URL. Returns the body or '' (and notes the error). */
function oyc_nn_get( $url, $max = 20971520 ) {
	$r = wp_remote_get( $url, array(
		'timeout'             => 40,
		'user-agent'          => OYC_NN_UA,
		'limit_response_size' => $max,
	) );
	if ( is_wp_error( $r ) ) {
		$GLOBALS['oyc_nn_err'] = $r->get_error_message();
		return '';
	}
	$code = (int) wp_remote_retrieve_response_code( $r );
	if ( 200 !== $code ) {
		$GLOBALS['oyc_nn_err'] = 'HTTP ' . $code . ' from ' . $url;
		return '';
	}
	return (string) wp_remote_retrieve_body( $r );
}

/**
 * Every .geojson link on the MSI page: [['url'=>, 'text'=>], ...], cached for
 * a day. Link text is kept because file names alone may not say the district.
 */
function oyc_nn_links( $fresh = false ) {
	$c = get_transient( 'oyc_nn_links' );
	if ( is_array( $c ) && ! $fresh ) {
		return $c;
	}
	$html = oyc_nn_get( OYC_NN_MSI, 4194304 );
	if ( '' === $html ) {
		return is_array( $c ) ? $c : array();
	}
	$out = array();
	if ( preg_match_all( '#<a\b[^>]*href=["\']([^"\']+\.geojson[^"\']*)["\'][^>]*>(.*?)</a>#is', $html, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $a ) {
			$u = html_entity_decode( $a[1], ENT_QUOTES );
			if ( 0 === strpos( $u, '//' ) ) {
				$u = 'https:' . $u;
			} elseif ( 0 === strpos( $u, '/' ) ) {
				$u = 'https://www.navcen.uscg.gov' . $u;
			} elseif ( ! preg_match( '#^https?://#i', $u ) ) {
				$u = 'https://www.navcen.uscg.gov/' . ltrim( $u, './' );
			}
			if ( ! preg_match( '#^https://(www\.)?navcen\.uscg\.gov/#i', $u ) ) {
				continue;   // only ever fetch from NAVCEN
			}
			$u         = str_replace( ' ', '%20', $u );
			$out[ $u ] = array(
				'url'  => $u,
				'text' => trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( html_entity_decode( $a[2], ENT_QUOTES ) ) ) ),
			);
		}
	}
	$out = array_values( $out );
	set_transient( 'oyc_nn_links', $out, DAY_IN_SECONDS );
	return $out;
}

/* NAVCEN's Local Notice to Mariners files (one set for the whole country,
   split into numbered parts), by file-name prefix, with the label the map
   shows. Left out on purpose: the Light List, Western Rivers buoys, the
   "Corrected" files (discrepancies already fixed), permanent/advance/proposed
   chart changes, space operations, autonomous vessels and TESS advisories. */
function oyc_nn_kinds() {
	return array(
		'discFedAid'  => 'Aid to navigation discrepancy',
		'discPriAid'  => 'Private aid discrepancy',
		'tmpChange'   => 'Temporary change',
		'hazNav'      => 'Hazard to navigation',
		'safeZone'    => 'Safety zone',
		'marCon'      => 'Marine construction',
		'bridge'      => 'Bridge',
		'marEvent'    => 'Marine event',
		'msib'        => 'Marine safety bulletin',
		'navalAct'    => 'Naval activity',
		'misc'        => 'Notice',
	);
}

/** The NAVCEN kind of a file URL ('' if it is not one we read). */
function oyc_nn_kind( $u ) {
	$b = basename( (string) wp_parse_url( $u, PHP_URL_PATH ) );
	foreach ( array_keys( oyc_nn_kinds() ) as $k ) {
		if ( preg_match( '/^' . $k . '(Line|Poly)?_\d+\.geojson$/', $b ) ) {
			return $k;
		}
	}
	return '';
}

/** Is this link one of the Local Notice to Mariners files we read? */
function oyc_nn_is_d1_lnm( $l ) {
	return '' !== oyc_nn_kind( $l['url'] );
}

/** The Local Notice to Mariners files to read. */
function oyc_nn_sources() {
	if ( defined( 'OYC_NN_SOURCES' ) && OYC_NN_SOURCES ) {
		return array_values( array_filter( array_map( 'trim', explode( ',', OYC_NN_SOURCES ) ) ) );
	}
	return array_values( array_map( function ( $l ) {
		return $l['url'];
	}, array_filter( oyc_nn_links(), 'oyc_nn_is_d1_lnm' ) ) );
}

/* All [lon, lat] pairs in a GeoJSON geometry. */
function oyc_nn_coords( $g, &$out ) {
	if ( ! is_array( $g ) ) {
		return;
	}
	if ( isset( $g['type'] ) && 'GeometryCollection' === $g['type'] && ! empty( $g['geometries'] ) ) {
		foreach ( $g['geometries'] as $gg ) {
			oyc_nn_coords( $gg, $out );
		}
		return;
	}
	$walk = function ( $c ) use ( &$walk, &$out ) {
		if ( is_array( $c ) && 2 <= count( $c ) && is_numeric( $c[0] ) && is_numeric( $c[1] ) ) {
			$out[] = array( (float) $c[0], (float) $c[1] );
		} elseif ( is_array( $c ) ) {
			foreach ( $c as $cc ) {
				$walk( $cc );
			}
		}
	};
	if ( isset( $g['coordinates'] ) ) {
		$walk( $g['coordinates'] );
	}
}

/* First property whose name matches the pattern and has a usable value. */
function oyc_nn_pick( $p, $re ) {
	foreach ( $p as $k => $v ) {
		if ( preg_match( $re, $k ) && is_scalar( $v ) && '' !== trim( (string) $v ) ) {
			return trim( (string) $v );
		}
	}
	return '';
}

function oyc_nn_clean( $s, $n, $lines = false ) {
	$s = wp_strip_all_tags( str_replace( array( "\r\n", "\r" ), "\n", (string) $s ) );
	$s = $lines ? trim( preg_replace( array( '/[ \t]+/', '/ *\n */', '/\n{3,}/' ), array( ' ', "\n", "\n\n" ), $s ) ) : trim( preg_replace( '/\s+/', ' ', $s ) );
	return mb_strlen( $s ) > $n ? mb_substr( $s, 0, $n - 1 ) . '…' : $s;
}

/** The map label for a file ("Marine construction"). */
function oyc_nn_cat_from_url( $u ) {
	$k     = oyc_nn_kind( $u );
	$kinds = oyc_nn_kinds();
	return $k ? $kinds[ $k ] : 'Notice';
}

/* A NAVCEN date (epoch milliseconds) as epoch seconds, or 0. */
function oyc_nn_ms( $v ) {
	return is_numeric( $v ) && $v > 0 ? (int) floor( $v / 1000 ) : 0;
}

/* NAVCEN descriptions open with lines the popup already shows (the
   "Waterway/General/Category" path, the notice number, "From: .. To: ..",
   "Location:" and bare coordinates): drop those, keep the notice itself. */
function oyc_nn_tidy( $text ) {
	$out = array();
	foreach ( preg_split( '/\r?\n/', (string) $text ) as $ln ) {
		$t = trim( $ln );
		if ( preg_match( '#^(General/|[^/]+/General/)#', $t )
			|| preg_match( '/^\d+$/', $t )
			|| preg_match( '/^From:\s*\d{4}-\d{2}-\d{2}/', $t )
			|| preg_match( '/^Location:[\s\d.,°\-NSEW\/]*$/u', $t )
			|| ( preg_match( '/\d/', $t ) && preg_match( '/^[\d\s.,°\'’"\-NSEW\/()]+$/u', $t ) ) ) {
			continue;
		}
		$out[] = $ln;
	}
	return trim( implode( "\n", $out ) );
}

/* Is this point in the Sound? OYC_NN_BOX less Long Island's south-shore bays,
   which share its longitudes: the Sound's southern edge steps north going east. */
function oyc_nn_in_sound( $lat, $lon ) {
	list( $s, $w, $n, $e ) = array_map( 'floatval', explode( ',', OYC_NN_BOX ) );
	if ( $lat < $s || $lat > $n || $lon < $w || $lon > $e ) {
		return false;
	}
	$south = $lon < -73.40 ? 40.76 : ( $lon < -72.70 ? 40.88 : 40.95 );
	return $lat >= $south;
}

/** NAVCEN's own fields -> title, text, dates, id. Empty when not NAVCEN's layout. */
function oyc_nn_navcen_fields( $p, $cat ) {
	if ( isset( $p['TITLE'] ) || isset( $p['MSI_UID'] ) ) {
		/* notices: TITLE is "Waterway/General/Sub category/Type" */
		$parts = array_map( 'trim', explode( '/', (string) ( isset( $p['TITLE'] ) ? $p['TITLE'] : '' ) ) );
		$where = $parts[0] ? $parts[0] : ( isset( $p['WATERWAY_NAME'] ) ? $p['WATERWAY_NAME'] : '' );
		$what  = ! empty( $p['TYPE'] ) ? $p['TYPE'] : ( ! empty( $p['SUB_CATEGORY'] ) ? $p['SUB_CATEGORY'] : $cat );
		$text  = isset( $p['DESCRIPTION'] ) ? (string) $p['DESCRIPTION'] : '';
		$to    = oyc_nn_ms( isset( $p['END_DATE'] ) ? $p['END_DATE'] : 0 );
		/* the description's own "To:" date can be earlier than END_DATE (a
		   July 4 fireworks zone listed until late December): use the earlier */
		if ( preg_match( '/\bTo:\s*(\d{4}-\d{2}-\d{2})/', $text, $m ) ) {
			$t2 = strtotime( $m[1] . ' 23:59:59 UTC' );
			if ( $t2 && ( ! $to || $t2 < $to ) ) {
				$to = $t2;
			}
		}
		return array(
			'id'    => ! empty( $p['MSI_UID'] ) ? 'Notice ' . $p['MSI_UID'] : '',
			'cat'   => $cat . ( $what && 0 !== strcasecmp( $what, $cat ) ? ' · ' . $what : '' ),
			'title' => $where ? $where : $what,
			'text'  => oyc_nn_tidy( $text ),
			'from'  => oyc_nn_ms( isset( $p['BEGIN_DATE'] ) ? $p['BEGIN_DATE'] : 0 ),
			'to'    => $to,
		);
	}
	if ( isset( $p['LLNR'] ) || isset( $p['DISCREP_STATUS'] ) || isset( $p['ATON_GROUP'] ) ) {
		/* aid discrepancies and temporary changes: one buoy or light each */
		$st = array();
		foreach ( array( 'DISCREP_STATUS', 'DISCREP_CORR_STATUS', 'MSI_STATUS' ) as $k ) {
			if ( ! empty( $p[ $k ] ) && ! preg_match( '/unreported|^approved$/i', trim( $p[ $k ] ) ) ) {
				$st[] = $p[ $k ];
			}
		}
		return array(
			'id'    => trim( ( ! empty( $p['LLNR'] ) ? 'LLNR ' . $p['LLNR'] : '' ) . ( ! empty( $p['BNM_NUM'] ) ? ' · BNM ' . $p['BNM_NUM'] : '' ), ' ·' ),
			'cat'   => $cat,
			'title' => ! empty( $p['NAME'] ) ? $p['NAME'] : $cat,
			'text'  => $st ? implode( "\n", $st ) : 'Reported discrepancy: missing, off station, light out or damaged. Details in the weekly Local Notice to Mariners.',
			'from'  => oyc_nn_ms( isset( $p['CREATE_DATE'] ) ? $p['CREATE_DATE'] : 0 ),
			'to'    => 0,
		);
	}
	return array();
}

/** One GeoJSON feature to our compact notice, or null when outside OYC_NN_BOX. */
function oyc_nn_item( $f, $cat ) {
	$pts = array();
	oyc_nn_coords( isset( $f['geometry'] ) ? $f['geometry'] : null, $pts );
	$p = isset( $f['properties'] ) && is_array( $f['properties'] ) ? $f['properties'] : array();
	/* some files carry the position only as properties */
	if ( ! $pts ) {
		$la = oyc_nn_pick( $p, '/^(lat|latitude|y)$/i' );
		$lo = oyc_nn_pick( $p, '/^(lon|lng|long|longitude|x)$/i' );
		if ( is_numeric( $la ) && is_numeric( $lo ) ) {
			$pts[] = array( (float) $lo, (float) $la );
		}
	}
	if ( ! $pts ) {
		return null;
	}
	list( $s, $w, $n, $e ) = array_map( 'floatval', explode( ',', OYC_NN_BOX ) );
	$in = false;
	$sx = 0;
	$sy = 0;
	foreach ( $pts as $c ) {
		$sx += $c[0];
		$sy += $c[1];
		if ( oyc_nn_in_sound( $c[1], $c[0] ) ) {
			$in = true;
		}
	}
	if ( ! $in ) {
		return null;
	}
	$nf = oyc_nn_navcen_fields( $p, $cat );
	if ( $nf ) {
		if ( $nf['to'] && $nf['to'] < time() ) {
			return null;   // ended
		}
		$it = array(
			'id'    => oyc_nn_clean( $nf['id'], 60 ),
			'cat'   => oyc_nn_clean( $nf['cat'], 90 ),
			'title' => oyc_nn_clean( $nf['title'], 140 ),
			'text'  => oyc_nn_clean( $nf['text'], 1500, true ),
			'from'  => $nf['from'] ? $nf['from'] * 1000 : '',
			'to'    => $nf['to'] ? $nf['to'] * 1000 : '',
			'lat'   => round( $sy / count( $pts ), 5 ),
			'lon'   => round( $sx / count( $pts ), 5 ),
		);
		$gt = isset( $f['geometry']['type'] ) ? $f['geometry']['type'] : '';
		if ( $gt && 'Point' !== $gt && 'MultiPoint' !== $gt && count( $pts ) <= 300 ) {
			$it['geom'] = oyc_nn_round_geom( $f['geometry'] );
		}
		return $it;
	}
	$title = oyc_nn_pick( $p, '/^(title|subject|name|heading|aid_?name|aidname|structure|location_?name)$/i' );
	if ( '' === $title ) {
		$title = oyc_nn_pick( $p, '/(title|subject|name)/i' );
	}
	$text = oyc_nn_pick( $p, '/^(description|desc|text|narrative|remarks?|details?|message|body|discrepancy|condition|status|summary)$/i' );
	if ( '' === $text ) {
		$text = oyc_nn_pick( $p, '/(descr|narr|remark|detail|discrep|message|text)/i' );
	}
	$it = array(
		'id'    => oyc_nn_clean( oyc_nn_pick( $p, '/^(id|objectid|lnm|bnm|llnr|ll_?num|number)$|(lnm|bnm|notice).*(no|num|number|id)$/i' ), 40 ),
		'cat'   => oyc_nn_clean( oyc_nn_pick( $p, '/^(category|subcategory|sub_?category|type|notice_?type)$/i' ) ?: $cat, 60 ),
		'title' => oyc_nn_clean( $title ?: $cat, 140 ),
		'text'  => oyc_nn_clean( $text, 900 ),
		'from'  => oyc_nn_clean( oyc_nn_pick( $p, '/(start|effective|begin|from|issued|date_?reported|reported|created)/i' ), 40 ),
		'to'    => oyc_nn_clean( oyc_nn_pick( $p, '/(end|expir|until|cancel|through|thru)/i' ), 40 ),
		'lat'   => round( $sy / count( $pts ), 5 ),
		'lon'   => round( $sx / count( $pts ), 5 ),
	);
	/* lines and zones: keep the outline (rounded, capped) so the map can draw it */
	$gt = isset( $f['geometry']['type'] ) ? $f['geometry']['type'] : '';
	if ( $gt && 'Point' !== $gt && 'MultiPoint' !== $gt && count( $pts ) <= 300 ) {
		$it['geom'] = oyc_nn_round_geom( $f['geometry'] );
	}
	return $it;
}

function oyc_nn_round_geom( $g ) {
	if ( isset( $g['coordinates'] ) ) {
		$r                = function ( $c ) use ( &$r ) {
			if ( is_array( $c ) && 2 <= count( $c ) && is_numeric( $c[0] ) && is_numeric( $c[1] ) ) {
				return array( round( (float) $c[0], 5 ), round( (float) $c[1], 5 ) );
			}
			return is_array( $c ) ? array_map( $r, $c ) : $c;
		};
		$g['coordinates'] = $r( $g['coordinates'] );
	}
	return array_intersect_key( $g, array( 'type' => 1, 'coordinates' => 1, 'geometries' => 1 ) );
}

/** Read every source file and save the Sound's notices. Returns the cache. */
add_action( 'oyc_nn_refresh', 'oyc_nn_refresh' );
function oyc_nn_refresh() {
	if ( get_transient( 'oyc_nn_lock' ) ) {
		return get_option( 'oyc_nn_cache', array() );
	}
	set_transient( 'oyc_nn_lock', 1, 10 * MINUTE_IN_SECONDS );
	@set_time_limit( 300 ); // phpcs:ignore
	$old   = get_option( 'oyc_nn_cache', array() );
	$items = array();
	$seen  = array();
	$files = array();
	$errs  = array();
	foreach ( array_slice( oyc_nn_sources(), 0, 40 ) as $u ) {
		$GLOBALS['oyc_nn_err'] = '';
		$body = oyc_nn_get( $u );
		$j    = '' !== $body ? json_decode( $body, true ) : null;
		unset( $body );
		if ( ! is_array( $j ) ) {
			$errs[] = basename( $u ) . ': ' . ( $GLOBALS['oyc_nn_err'] ?: 'not GeoJSON' );
			continue;
		}
		$cat  = oyc_nn_cat_from_url( $u );
		$feat = isset( $j['features'] ) && is_array( $j['features'] ) ? $j['features'] : ( isset( $j['type'] ) && 'Feature' === $j['type'] ? array( $j ) : array() );
		$kept = 0;
		foreach ( $feat as $f ) {
			$it = is_array( $f ) ? oyc_nn_item( $f, $cat ) : null;
			if ( ! $it ) {
				continue;
			}
			$k = md5( $it['title'] . '|' . $it['lat'] . '|' . $it['lon'] . '|' . $it['text'] );
			if ( isset( $seen[ $k ] ) ) {
				continue;
			}
			$seen[ $k ] = 1;
			$items[]    = $it;
			$kept++;
		}
		$files[] = array( 'file' => basename( rawurldecode( $u ) ), 'cat' => $cat, 'features' => count( $feat ), 'kept' => $kept );
		unset( $j, $feat );
	}
	$items = array_slice( $items, 0, OYC_NN_MAX_ITEMS );
	/* NAVCEN unreachable or every file failed: keep the last good list */
	if ( ! $files && ! empty( $old['items'] ) ) {
		$old['err']   = implode( '; ', $errs ) ?: 'no District 1 notice files found';
		$old['tried'] = time();
		update_option( 'oyc_nn_cache', $old, false );
		delete_transient( 'oyc_nn_lock' );
		return $old;
	}
	$cache = array(
		't'     => time(),
		'tried' => time(),
		'items' => $items,
		'files' => $files,
		'err'   => $files ? implode( '; ', $errs ) : ( implode( '; ', $errs ) ?: 'no District 1 notice files found on ' . OYC_NN_MSI ),
	);
	update_option( 'oyc_nn_cache', $cache, false );
	delete_transient( 'oyc_nn_lock' );
	return $cache;
}

add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'oyc_nn_refresh' ) ) {
		wp_schedule_event( time() + 60, 'twicedaily', 'oyc_nn_refresh' );
	}
} );
add_action( 'switch_theme', function () {
	$ts = wp_next_scheduled( 'oyc_nn_refresh' );
	if ( $ts ) {
		wp_unschedule_event( $ts, 'oyc_nn_refresh' );
	}
} );

/** Has the site ever read NAVCEN's notice files? Until it has (NAVCEN refuses
    this server, or nothing has run yet), the map leaves the Notices toggle
    out instead of showing an empty layer. */
function oyc_nn_ready() {
	$c = get_option( 'oyc_nn_cache', array() );
	return ! empty( $c['t'] ) && ! empty( $c['files'] );
}

/* admin-ajax oyc_nn: the saved notices for the map. When the list is missing
   or over a day old, a background refresh is queued (the map never waits). */
add_action( 'wp_ajax_oyc_nn', 'oyc_nn_ajax' );
add_action( 'wp_ajax_nopriv_oyc_nn', 'oyc_nn_ajax' );
function oyc_nn_ajax() {
	$c     = get_option( 'oyc_nn_cache', array() );
	$tried = isset( $c['tried'] ) ? (int) $c['tried'] : 0;
	if ( time() - $tried > DAY_IN_SECONDS + HOUR_IN_SECONDS && ! wp_next_scheduled( 'oyc_nn_refresh_now' ) && ! get_transient( 'oyc_nn_lock' ) ) {
		wp_schedule_single_event( time(), 'oyc_nn_refresh_now' );
	}
	header( 'Cache-Control: public, max-age=1800' );
	header( 'X-Robots-Tag: noindex' );
	wp_send_json( array(
		'ok'    => true,
		't'     => isset( $c['t'] ) ? (int) $c['t'] : 0,
		'items' => isset( $c['items'] ) ? $c['items'] : array(),
		'src'   => OYC_NN_MSI,
	) );
}
add_action( 'oyc_nn_refresh_now', 'oyc_nn_refresh' );

/* admin-ajax oyc_nn_probe: read-only look at what NAVCEN offers, for setting
   this up from the sandbox. Lists every .geojson link on the MSI page, which
   ones are treated as District 1 notices, the saved summary, and (with
   ?f=N, N = index in the link list) the field names and two sample features
   of that one file. Only NAVCEN links from the MSI page can be fetched; one
   new request every 10 seconds; results cached 10 minutes. ?run=1 also runs
   the refresh (at most once every 10 minutes). */
add_action( 'wp_ajax_oyc_nn_probe', 'oyc_nn_probe' );
add_action( 'wp_ajax_nopriv_oyc_nn_probe', 'oyc_nn_probe' );
function oyc_nn_probe() {
	header( 'X-Robots-Tag: noindex' );
	$f   = isset( $_GET['f'] ) ? (int) $_GET['f'] : -1;
	$run = ! empty( $_GET['run'] );
	$key = 'oyc_nn_pr_' . $f . ( $run ? 'r' : '' );
	$out = get_transient( $key );
	if ( $out ) {
		wp_send_json( $out );
	}
	if ( get_transient( 'oyc_nn_probe_lock' ) ) {
		wp_send_json( array( 'wait' => 'try again in 10 seconds' ), 429 );
	}
	set_transient( 'oyc_nn_probe_lock', 1, 10 );
	$GLOBALS['oyc_nn_err'] = '';
	$links = oyc_nn_links( $f < 0 && ! $run );
	$out   = array(
		'msi_error' => $GLOBALS['oyc_nn_err'],
		'links'     => array_map( function ( $l, $i ) {
			return array( 'i' => $i, 'd1' => oyc_nn_is_d1_lnm( $l ), 'text' => $l['text'], 'url' => $l['url'] );
		}, $links, array_keys( $links ) ),
		'sources'   => oyc_nn_sources(),
	);
	if ( $run && ! get_transient( 'oyc_nn_probe_ran' ) ) {
		set_transient( 'oyc_nn_probe_ran', 1, 10 * MINUTE_IN_SECONDS );
		oyc_nn_refresh();
	}
	$c              = get_option( 'oyc_nn_cache', array() );
	$out['summary'] = array(
		't'      => isset( $c['t'] ) ? gmdate( 'c', $c['t'] ) : null,
		'tried'  => isset( $c['tried'] ) ? gmdate( 'c', $c['tried'] ) : null,
		'files'  => isset( $c['files'] ) ? $c['files'] : array(),
		'err'    => isset( $c['err'] ) ? $c['err'] : '',
		'count'  => isset( $c['items'] ) ? count( $c['items'] ) : 0,
		'sample' => isset( $c['items'] ) ? array_slice( $c['items'], 0, 3 ) : array(),
	);
	if ( $f >= 0 && isset( $links[ $f ] ) ) {
		$GLOBALS['oyc_nn_err'] = '';
		$body = oyc_nn_get( $links[ $f ]['url'] );
		$j    = '' !== $body ? json_decode( $body, true ) : null;
		$file = array( 'url' => $links[ $f ]['url'], 'bytes' => strlen( $body ), 'error' => $GLOBALS['oyc_nn_err'] );
		if ( is_array( $j ) ) {
			$feat           = isset( $j['features'] ) && is_array( $j['features'] ) ? $j['features'] : array();
			$types          = array();
			$fields         = array();
			foreach ( $feat as $ft ) {
				$gt           = isset( $ft['geometry']['type'] ) ? $ft['geometry']['type'] : 'none';
				$types[ $gt ] = isset( $types[ $gt ] ) ? $types[ $gt ] + 1 : 1;
				if ( isset( $ft['properties'] ) && is_array( $ft['properties'] ) ) {
					foreach ( array_keys( $ft['properties'] ) as $k ) {
						$fields[ $k ] = 1;
					}
				}
			}
			$cat             = oyc_nn_cat_from_url( $links[ $f ]['url'] );
			$inbox           = array_values( array_filter( array_map( function ( $ft ) use ( $cat ) {
				return is_array( $ft ) ? oyc_nn_item( $ft, $cat ) : null;
			}, $feat ) ) );
			$file['top']     = array_slice( array_keys( $j ), 0, 20 );
			$file['count']   = count( $feat );
			$file['geom']    = $types;
			$file['fields']  = array_keys( $fields );
			$file['raw']     = array_map( function ( $ft ) {
				if ( isset( $ft['geometry']['coordinates'] ) ) {
					$ft['geometry']['coordinates'] = mb_substr( wp_json_encode( $ft['geometry']['coordinates'] ), 0, 200 );
				}
				return $ft;
			}, array_slice( $feat, 0, 2 ) );
			$file['in_box']  = count( $inbox );
			$file['as_item'] = array_slice( $inbox, 0, 2 );
		} else {
			$file['start'] = mb_substr( (string) $body, 0, 300 );
		}
		$out['file'] = $file;
	}
	set_transient( $key, $out, 10 * MINUTE_IN_SECONDS );
	wp_send_json( $out );
}
