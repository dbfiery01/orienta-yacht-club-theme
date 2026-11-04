<?php
/**
 * Race tools on the Wind & Radar map: Polars & Target Speed overlay, and the
 * GPS Race Log with a shared team archive + replay.
 *
 * Racers only. Access goes through oyc_race_tools_can_use(): racers and the
 * Race Committee when the racing hub (inc/racing.php, oyc_is_racer) is loaded,
 * otherwise site admins only, so the tools can be tested before the hub ships.
 *
 * Storage (all non-public post types, no REST, no URLs):
 *   oyc_polar        one boat's polar table
 *     post_title      boat name
 *     _oyc_polar      JSON {tws:[kt..], twa:[deg..], bsp:[[kt..]..]}  (rows = twa)
 *     _yacht          oyc_yacht post ID (optional, racing hub)
 *   oyc_race_track   one recorded race
 *     post_title      "Race name" (defaults to boat + date)
 *     _oyc_track      JSON [[t(s since start), lat, lon, sog kt, cog °T, tws, twd, pct]..]
 *     _start          unix time of the first point
 *     _boat           boat name, _polar polar post ID, _yacht yacht ID, _race race ID
 *     _source         'live' | 'gpx'
 *     _share          'team' (default: uploader, same-boat crew in that race,
 *                     Race Committee) | 'racers' (every racer)
 *
 * The browser does all the maths (polar lookup, % of target, tack/gybe and
 * speed-drop detection); the server validates and stores.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OYC_RT_MAX_POINTS', 30000 );      // ~8 h at one point per second
define( 'OYC_RT_MAX_POLAR_DIM', 80 );      // rows / columns in a polar table

/* ─────────────────────────────────────────────────────────────────────────────
 * Access
 * ───────────────────────────────────────────────────────────────────────────── */

/** Racers (and the Race Committee) once the racing hub is in; admins until then. */
function oyc_race_tools_can_use( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return false;
	}
	if ( user_can( $user_id, 'manage_options' ) ) {
		$ok = true;
	} elseif ( function_exists( 'oyc_is_racer' ) ) {
		$ok = (bool) oyc_is_racer( $user_id );
	} else {
		$ok = false;
	}
	return (bool) apply_filters( 'oyc_race_tools_can_use', $ok, $user_id );
}

/** Race Committee / admins: may see and remove any polar or track. */
function oyc_race_tools_is_committee( $user_id = 0 ) {
	$user_id = $user_id ? (int) $user_id : get_current_user_id();
	if ( user_can( $user_id, 'manage_options' ) ) {
		return true;
	}
	return function_exists( 'oyc_race_can_manage' ) && oyc_race_can_manage( $user_id );
}

/* ─────────────────────────────────────────────────────────────────────────────
 * Post types
 * ───────────────────────────────────────────────────────────────────────────── */

add_action( 'init', function () {
	$common = array(
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_in_rest'        => false,
		'has_archive'         => false,
		'rewrite'             => false,
		'query_var'           => false,
		'show_ui'             => false,
		'supports'            => array( 'title', 'author' ),
		'capability_type'     => 'post',
		'map_meta_cap'        => true,
	);
	register_post_type( 'oyc_polar', $common + array( 'label' => __( 'Boat polars', 'orienta-yacht-club' ) ) );
	register_post_type( 'oyc_race_track', $common + array( 'label' => __( 'Race tracks', 'orienta-yacht-club' ) ) );
} );

/* ─────────────────────────────────────────────────────────────────────────────
 * Helpers
 * ───────────────────────────────────────────────────────────────────────────── */

function oyc_rt_json_out( $data, $ok = true ) {
	$ok ? wp_send_json_success( $data ) : wp_send_json_error( $data, 400 );
}

/** Nonce + racer check for every AJAX call. */
function oyc_rt_guard() {
	nocache_headers();
	if ( ! check_ajax_referer( 'oyc_race_tools', 'nonce', false ) ) {
		wp_send_json_error( array( 'msg' => 'Your session expired. Reload the page and try again.' ), 403 );
	}
	if ( ! oyc_race_tools_can_use() ) {
		wp_send_json_error( array( 'msg' => 'Race tools are for racers only.' ), 403 );
	}
}

/** Yacht posts the current user skippers (racing hub), id => name. */
function oyc_rt_my_yachts() {
	$out = array();
	if ( function_exists( 'oyc_race_yachts' ) ) {
		foreach ( (array) oyc_race_yachts( get_current_user_id() ) as $y ) {
			$y = get_post( $y );
			if ( $y ) {
				$out[ $y->ID ] = $y->post_title;
			}
		}
	}
	return $out;
}

/** Recent races (racing hub), newest first, id => "date · name". */
function oyc_rt_recent_races() {
	$out = array();
	if ( ! post_type_exists( 'oyc_race' ) ) {
		return $out;
	}
	$q = get_posts( array(
		'post_type'      => 'oyc_race',
		'post_status'    => 'publish',
		'posts_per_page' => 30,
		'meta_key'       => '_oyc_race_date',
		'orderby'        => 'meta_value',
		'order'          => 'DESC',
		'meta_query'     => array( array( 'key' => '_oyc_race_date', 'value' => wp_date( 'Y-m-d', time() + DAY_IN_SECONDS ), 'compare' => '<=' ) ),
	) );
	foreach ( $q as $r ) {
		$out[ $r->ID ] = get_post_meta( $r->ID, '_oyc_race_date', true ) . ' · ' . $r->post_title;
	}
	return $out;
}

function oyc_rt_can_edit( $post ) {
	if ( ! $post ) {
		return false;
	}
	$uid = get_current_user_id();
	if ( (int) $post->post_author === $uid || oyc_race_tools_is_committee() ) {
		return true;
	}
	$yacht = (int) get_post_meta( $post->ID, '_yacht', true );
	return $yacht && ( $y = get_post( $yacht ) ) && (int) $y->post_author === $uid;
}

/** Same-boat crew for a race, per the racing hub's entries. */
function oyc_rt_user_sailed( $race_id, $boat, $yacht_id, $user_id ) {
	if ( ! $race_id || ! post_type_exists( 'oyc_race_entry' ) ) {
		return false;
	}
	$entries = get_posts( array(
		'post_type'      => 'oyc_race_entry',
		'post_status'    => 'any',
		'post_parent'    => (int) $race_id,
		'author'         => (int) $user_id,
		'posts_per_page' => 5,
	) );
	foreach ( $entries as $e ) {
		if ( $yacht_id && (int) get_post_meta( $e->ID, '_yacht', true ) === (int) $yacht_id ) {
			return true;
		}
		$eb = trim( (string) get_post_meta( $e->ID, '_boat', true ) );
		if ( '' !== $eb && '' !== $boat && 0 === strcasecmp( $eb, $boat ) ) {
			return true;
		}
	}
	return false;
}

function oyc_rt_can_view_track( $post ) {
	if ( ! $post || 'oyc_race_track' !== $post->post_type ) {
		return false;
	}
	if ( oyc_rt_can_edit( $post ) ) {
		return true;
	}
	if ( 'racers' === get_post_meta( $post->ID, '_share', true ) ) {
		return true;
	}
	return oyc_rt_user_sailed(
		(int) get_post_meta( $post->ID, '_race', true ),
		(string) get_post_meta( $post->ID, '_boat', true ),
		(int) get_post_meta( $post->ID, '_yacht', true ),
		get_current_user_id()
	);
}

/** Validate a polar table posted as JSON; returns the clean array or null. */
function oyc_rt_clean_polar( $raw ) {
	$p = json_decode( (string) $raw, true );
	if ( ! is_array( $p ) || empty( $p['tws'] ) || empty( $p['twa'] ) || empty( $p['bsp'] ) ) {
		return null;
	}
	$tws = array_map( 'floatval', array_values( (array) $p['tws'] ) );
	$twa = array_map( 'floatval', array_values( (array) $p['twa'] ) );
	if ( count( $tws ) < 2 || count( $twa ) < 2 || count( $tws ) > OYC_RT_MAX_POLAR_DIM || count( $twa ) > OYC_RT_MAX_POLAR_DIM ) {
		return null;
	}
	$bsp = array();
	foreach ( array_values( (array) $p['bsp'] ) as $row ) {
		$row = array_values( (array) $row );
		if ( count( $row ) !== count( $tws ) ) {
			return null;
		}
		$clean = array();
		foreach ( $row as $v ) {
			$v       = (float) $v;
			$clean[] = ( $v >= 0 && $v < 60 ) ? round( $v, 2 ) : 0;
		}
		$bsp[] = $clean;
	}
	if ( count( $bsp ) !== count( $twa ) ) {
		return null;
	}
	foreach ( $tws as $v ) {
		if ( $v < 0 || $v > 80 ) {
			return null;
		}
	}
	foreach ( $twa as $v ) {
		if ( $v < 0 || $v > 180 ) {
			return null;
		}
	}
	return array( 'tws' => $tws, 'twa' => $twa, 'bsp' => $bsp );
}

function oyc_rt_polar_row( $post ) {
	return array(
		'id'      => $post->ID,
		'name'    => $post->post_title,
		'yacht'   => (int) get_post_meta( $post->ID, '_yacht', true ),
		'owner'   => get_the_author_meta( 'display_name', $post->post_author ),
		'canEdit' => oyc_rt_can_edit( $post ),
		'polar'   => json_decode( (string) get_post_meta( $post->ID, '_oyc_polar', true ), true ),
	);
}

function oyc_rt_track_row( $post, $with_points = false ) {
	$row = array(
		'id'       => $post->ID,
		'name'     => $post->post_title,
		'boat'     => (string) get_post_meta( $post->ID, '_boat', true ),
		'start'    => (int) get_post_meta( $post->ID, '_start', true ),
		'dur'      => (int) get_post_meta( $post->ID, '_dur', true ),
		'nm'       => (float) get_post_meta( $post->ID, '_nm', true ),
		'polar'    => (int) get_post_meta( $post->ID, '_polar', true ),
		'race'     => (int) get_post_meta( $post->ID, '_race', true ),
		'source'   => (string) get_post_meta( $post->ID, '_source', true ),
		'share'    => get_post_meta( $post->ID, '_share', true ) === 'racers' ? 'racers' : 'team',
		'by'       => get_the_author_meta( 'display_name', $post->post_author ),
		'canEdit'  => oyc_rt_can_edit( $post ),
	);
	if ( $with_points ) {
		$row['pts'] = json_decode( (string) get_post_meta( $post->ID, '_oyc_track', true ), true );
	}
	return $row;
}

/* ─────────────────────────────────────────────────────────────────────────────
 * AJAX: polars
 * ───────────────────────────────────────────────────────────────────────────── */

add_action( 'wp_ajax_oyc_rt_polars', function () {
	oyc_rt_guard();
	$rows = array();
	foreach ( get_posts( array( 'post_type' => 'oyc_polar', 'post_status' => 'private', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC' ) ) as $p ) {
		$rows[] = oyc_rt_polar_row( $p );
	}
	oyc_rt_json_out( array( 'polars' => $rows ) );
} );

add_action( 'wp_ajax_oyc_rt_polar_save', function () {
	oyc_rt_guard();
	$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$polar = oyc_rt_clean_polar( isset( $_POST['polar'] ) ? wp_unslash( $_POST['polar'] ) : '' );
	$id    = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
	$yacht = isset( $_POST['yacht'] ) ? (int) $_POST['yacht'] : 0;
	if ( '' === $name || ! $polar ) {
		oyc_rt_json_out( array( 'msg' => 'Give the boat a name and a valid polar table.' ), false );
	}
	if ( $yacht && ! array_key_exists( $yacht, oyc_rt_my_yachts() ) && ! oyc_race_tools_is_committee() ) {
		$yacht = 0;
	}
	if ( $id ) {
		$post = get_post( $id );
		if ( ! $post || 'oyc_polar' !== $post->post_type || ! oyc_rt_can_edit( $post ) ) {
			oyc_rt_json_out( array( 'msg' => 'You can only change your own boat’s polars.' ), false );
		}
		wp_update_post( array( 'ID' => $id, 'post_title' => $name ) );
	} else {
		$id = wp_insert_post( array( 'post_type' => 'oyc_polar', 'post_status' => 'private', 'post_title' => $name, 'post_author' => get_current_user_id() ), true );
		if ( is_wp_error( $id ) ) {
			oyc_rt_json_out( array( 'msg' => 'Could not save the polars.' ), false );
		}
	}
	update_post_meta( $id, '_oyc_polar', wp_slash( wp_json_encode( $polar ) ) );
	$yacht ? update_post_meta( $id, '_yacht', $yacht ) : delete_post_meta( $id, '_yacht' );
	oyc_rt_json_out( array( 'polar' => oyc_rt_polar_row( get_post( $id ) ) ) );
} );

add_action( 'wp_ajax_oyc_rt_polar_delete', function () {
	oyc_rt_guard();
	$post = get_post( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 );
	if ( ! $post || 'oyc_polar' !== $post->post_type || ! oyc_rt_can_edit( $post ) ) {
		oyc_rt_json_out( array( 'msg' => 'You can only remove your own boat’s polars.' ), false );
	}
	wp_delete_post( $post->ID, true );
	oyc_rt_json_out( array( 'deleted' => $post->ID ) );
} );

/* ─────────────────────────────────────────────────────────────────────────────
 * AJAX: race tracks
 * ───────────────────────────────────────────────────────────────────────────── */

add_action( 'wp_ajax_oyc_rt_tracks', function () {
	oyc_rt_guard();
	$rows = array();
	foreach ( get_posts( array( 'post_type' => 'oyc_race_track', 'post_status' => 'private', 'posts_per_page' => 300, 'orderby' => 'date', 'order' => 'DESC' ) ) as $p ) {
		if ( oyc_rt_can_view_track( $p ) ) {
			$rows[] = oyc_rt_track_row( $p );
		}
	}
	oyc_rt_json_out( array( 'tracks' => $rows ) );
} );

add_action( 'wp_ajax_oyc_rt_track_get', function () {
	oyc_rt_guard();
	$post = get_post( isset( $_GET['id'] ) ? (int) $_GET['id'] : 0 );
	if ( ! oyc_rt_can_view_track( $post ) ) {
		oyc_rt_json_out( array( 'msg' => 'That race log is not shared with you.' ), false );
	}
	oyc_rt_json_out( array( 'track' => oyc_rt_track_row( $post, true ) ) );
} );

add_action( 'wp_ajax_oyc_rt_track_save', function () {
	oyc_rt_guard();
	$pts = json_decode( isset( $_POST['pts'] ) ? wp_unslash( $_POST['pts'] ) : '', true );
	if ( ! is_array( $pts ) || count( $pts ) < 10 ) {
		oyc_rt_json_out( array( 'msg' => 'That log is too short to save (fewer than 10 GPS points).' ), false );
	}
	if ( count( $pts ) > OYC_RT_MAX_POINTS ) {
		$pts = array_slice( $pts, 0, OYC_RT_MAX_POINTS );
	}
	$clean = array();
	$nm    = 0;
	$prev  = null;
	foreach ( $pts as $p ) {
		if ( ! is_array( $p ) || count( $p ) < 3 ) {
			continue;
		}
		$lat = (float) $p[1];
		$lon = (float) $p[2];
		if ( $lat < -90 || $lat > 90 || $lon < -180 || $lon > 180 ) {
			continue;
		}
		$row = array( max( 0, (int) $p[0] ), round( $lat, 6 ), round( $lon, 6 ) );
		for ( $i = 3; $i <= 7; $i++ ) {
			$row[] = ( isset( $p[ $i ] ) && is_numeric( $p[ $i ] ) ) ? round( (float) $p[ $i ], 1 ) : null;
		}
		if ( $prev ) {
			$d  = deg2rad( 1 );
			$x  = ( $lon - $prev[2] ) * $d * cos( ( $lat + $prev[1] ) / 2 * $d );
			$y  = ( $lat - $prev[1] ) * $d;
			$nm += sqrt( $x * $x + $y * $y ) * 3440.065;
		}
		$clean[] = $row;
		$prev    = $row;
	}
	if ( count( $clean ) < 10 ) {
		oyc_rt_json_out( array( 'msg' => 'That log has too few valid GPS points to save.' ), false );
	}
	$start = isset( $_POST['start'] ) ? (int) $_POST['start'] : time();
	$boat  = isset( $_POST['boat'] ) ? sanitize_text_field( wp_unslash( $_POST['boat'] ) ) : '';
	$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	if ( '' === $name ) {
		$name = trim( $boat . ' ' . wp_date( 'M j, Y', $start ) );
	}
	$race  = isset( $_POST['race'] ) ? (int) $_POST['race'] : 0;
	$yacht = isset( $_POST['yacht'] ) ? (int) $_POST['yacht'] : 0;
	$polar = isset( $_POST['polar'] ) ? (int) $_POST['polar'] : 0;
	if ( $race && 'oyc_race' !== get_post_type( $race ) ) {
		$race = 0;
	}
	if ( $yacht && 'oyc_yacht' !== get_post_type( $yacht ) ) {
		$yacht = 0;
	}
	if ( $polar && 'oyc_polar' !== get_post_type( $polar ) ) {
		$polar = 0;
	}
	$id = wp_insert_post( array( 'post_type' => 'oyc_race_track', 'post_status' => 'private', 'post_title' => $name, 'post_author' => get_current_user_id() ), true );
	if ( is_wp_error( $id ) ) {
		oyc_rt_json_out( array( 'msg' => 'Could not save the race log.' ), false );
	}
	$last = end( $clean );
	update_post_meta( $id, '_oyc_track', wp_slash( wp_json_encode( $clean ) ) );
	update_post_meta( $id, '_start', $start );
	update_post_meta( $id, '_dur', (int) $last[0] );
	update_post_meta( $id, '_nm', round( $nm, 2 ) );
	update_post_meta( $id, '_boat', $boat );
	update_post_meta( $id, '_polar', $polar );
	update_post_meta( $id, '_race', $race );
	update_post_meta( $id, '_yacht', $yacht );
	update_post_meta( $id, '_source', ( isset( $_POST['source'] ) && 'gpx' === $_POST['source'] ) ? 'gpx' : 'live' );
	update_post_meta( $id, '_share', ( isset( $_POST['share'] ) && 'racers' === $_POST['share'] ) ? 'racers' : 'team' );
	oyc_rt_json_out( array( 'track' => oyc_rt_track_row( get_post( $id ) ) ) );
} );

add_action( 'wp_ajax_oyc_rt_track_update', function () {
	oyc_rt_guard();
	$post = get_post( isset( $_POST['id'] ) ? (int) $_POST['id'] : 0 );
	if ( ! $post || 'oyc_race_track' !== $post->post_type || ! oyc_rt_can_edit( $post ) ) {
		oyc_rt_json_out( array( 'msg' => 'You can only change your own race logs.' ), false );
	}
	if ( isset( $_POST['do'] ) && 'delete' === $_POST['do'] ) {
		wp_delete_post( $post->ID, true );
		oyc_rt_json_out( array( 'deleted' => $post->ID ) );
	}
	if ( isset( $_POST['name'] ) ) {
		$n = sanitize_text_field( wp_unslash( $_POST['name'] ) );
		if ( '' !== $n ) {
			wp_update_post( array( 'ID' => $post->ID, 'post_title' => $n ) );
		}
	}
	if ( isset( $_POST['share'] ) ) {
		update_post_meta( $post->ID, '_share', 'racers' === $_POST['share'] ? 'racers' : 'team' );
	}
	if ( isset( $_POST['polar'] ) ) {
		$pol = (int) $_POST['polar'];
		update_post_meta( $post->ID, '_polar', ( $pol && 'oyc_polar' === get_post_type( $pol ) ) ? $pol : 0 );
	}
	oyc_rt_json_out( array( 'track' => oyc_rt_track_row( get_post( $post->ID ) ) ) );
} );

/* ─────────────────────────────────────────────────────────────────────────────
 * Front end: markup + assets, appended to the wind map for racers only
 * ───────────────────────────────────────────────────────────────────────────── */

/** HTML appended inside #oycwm by oyc_wind_map_html(); '' for non-racers. */
/**
 * Built-in class polars (assets/polar-library.json): ORC certificate VPP data
 * for common Long Island Sound boats, one certificate per class. Racers pick
 * one from a dropdown and use it as is, or load it into the Polars form to
 * adjust and save as their own boat's.
 */
function oyc_rt_polar_library() {
	$f = get_template_directory() . '/assets/polar-library.json';
	$j = file_exists( $f ) ? json_decode( (string) file_get_contents( $f ), true ) : null;
	return ( is_array( $j ) && ! empty( $j['boats'] ) ) ? $j : array( 'source' => '', 'boats' => array() );
}

function oyc_race_tools_markup() {
	if ( ! oyc_race_tools_can_use() ) {
		return '';
	}
	$dir  = get_template_directory();
	$uri  = get_template_directory_uri();
	$ver  = function ( $rel ) use ( $dir ) {
		return file_exists( $dir . $rel ) ? (string) filemtime( $dir . $rel ) : OYC_VERSION;
	};
	$cfg = array(
		'ajax'      => admin_url( 'admin-ajax.php' ),
		'nonce'     => wp_create_nonce( 'oyc_race_tools' ),
		'yachts'    => oyc_rt_my_yachts(),
		'lib'       => oyc_rt_polar_library(),
		'races'     => oyc_rt_recent_races(),
		'hub'       => function_exists( 'oyc_is_racer' ),
		'adminOnly' => ! function_exists( 'oyc_is_racer' ),
	);
	ob_start();
	?>
<link rel="stylesheet" href="<?php echo esc_url( $uri . '/assets/race-tools.css?ver=' . $ver( '/assets/race-tools.css' ) ); ?>">
<section class="rt-panel" id="rtPanel" aria-label="Race tools: polars, target speed and race log">
	<div class="rt-head">
		<h3>Race tools <span class="rt-tag">Racers</span></h3>
		<div class="rt-tabs" role="tablist">
			<button type="button" class="rt-tab on" data-tab="live" role="tab" aria-selected="true">Live</button>
			<button type="button" class="rt-tab" data-tab="polars" role="tab" aria-selected="false">Polars</button>
			<button type="button" class="rt-tab" data-tab="archive" role="tab" aria-selected="false">Race archive</button>
		</div>
	</div>

	<div class="rt-pane on" data-pane="live">
		<div class="rt-row">
			<label>Boat polars <select id="rtPolarSel"><option value="">— none —</option></select></label>
			<span class="rt-note rt-libnote" id="rtLibNote" hidden></span>
			<label class="rt-chk"><input type="checkbox" id="rtShowPct"> Show target speed on the map</label>
		</div>
		<details class="rt-wind">
			<summary>Wind: <span id="rtWindSrc">forecast model at the boat</span></summary>
			<p class="rt-note">The map’s wind is the forecast model at your position, not your masthead, so the % is a guide. For a truer number, type in your instruments’ true wind. It stays in use for this race until you clear it.</p>
			<div class="rt-row">
				<label>TWS <input type="number" id="rtTws" min="0" max="60" step="0.5" inputmode="decimal" placeholder="kt"></label>
				<label>TWD <input type="number" id="rtTwd" min="0" max="359" step="1" inputmode="numeric" placeholder="°M"></label>
				<button type="button" id="rtWindSet">Use</button>
				<button type="button" id="rtWindClr" class="rt-sec">Back to forecast</button>
			</div>
		</details>
		<div class="rt-rec">
			<div class="rt-row">
				<label>Boat <input type="text" id="rtBoat" maxlength="60" placeholder="Boat name"></label>
				<label id="rtRaceWrap" hidden>Race <select id="rtRaceSel"><option value="">— not linked —</option></select></label>
			</div>
			<div class="rt-row">
				<button type="button" id="rtRecBtn" class="rt-go">&#9679; Start race log</button>
				<span class="rt-recstat" id="rtRecStat"></span>
			</div>
			<p class="rt-note">Recording runs while this page stays open on screen. Keep the phone on a charger with this page in front: the screen is kept awake, and every point is saved on the phone as you go, so a lost signal or no cell coverage loses nothing. Tap <b>Finish</b> after the line to save it to the team archive.</p>
			<div class="rt-pending" id="rtPending" hidden></div>
		</div>
	</div>

	<div class="rt-pane" data-pane="polars">
		<div class="rt-list" id="rtPolarList"></div>
		<form class="rt-form" id="rtPolarForm">
			<h4 id="rtPolarFormH">Add your boat’s polars</h4>
			<div class="rt-row">
				<label>Start from a class <select id="rtPLib"><option value="">— pick a boat class —</option></select></label>
				<label>Scale speeds <input type="number" id="rtPScale" min="70" max="120" step="1" value="100" inputmode="numeric" style="width:5em"> %</label>
				<button type="button" class="rt-sec" id="rtPScaleGo">Apply</button>
			</div>
			<p class="rt-note">Class polars are one ORC certificate per class (source: ORC VPP data via jieter/orc-data). Load one, then refine it: scale the speeds to suit your sails, bottom and crew (e.g. 95%), edit any cell in the table below, or replace it with your own file. Save it under your boat’s name.</p>
			<div class="rt-row">
				<label>Boat name <input type="text" id="rtPName" maxlength="60" required></label>
				<label id="rtPYachtWrap" hidden>Yacht profile <select id="rtPYacht"><option value="">—</option></select></label>
			</div>
			<label class="rt-file">Polar file (.csv, .txt, .pol) <input type="file" id="rtPFile" accept=".csv,.txt,.pol,.tsv,text/plain,text/csv"></label>
			<label>…or paste the table <textarea id="rtPText" rows="6" spellcheck="false" placeholder="twa/tws;6;8;10;12;14;16;20&#10;52;5.6;6.4;6.9;7.1;7.2;7.3;7.4&#10;60;5.9;6.7;7.1;7.3;7.5;7.6;7.7&#10;…"></textarea></label>
			<p class="rt-note">Works with ORC, Expedition, B&amp;G and most sail-maker polar files: a grid with true wind speeds across the top row and true wind angles down the first column (comma, semicolon, tab or space separated).</p>
			<div class="rt-prev" id="rtPPrev"></div>
			<div class="rt-row">
				<button type="submit" class="rt-go" id="rtPSave">Save polars</button>
				<button type="button" class="rt-sec" id="rtPCancel" hidden>Cancel</button>
				<span class="rt-msg" id="rtPMsg"></span>
			</div>
		</form>
	</div>

	<div class="rt-pane" data-pane="archive">
		<div class="rt-row">
			<label class="rt-file">Import a GPX track <input type="file" id="rtGpx" accept=".gpx,application/gpx+xml"></label>
			<span class="rt-msg" id="rtGpxMsg"></span>
		</div>
		<div class="rt-list" id="rtTrackList"><p class="rt-note">Loading…</p></div>
		<div class="rt-replay" id="rtReplay" hidden>
			<div class="rt-rhead"><b id="rtRName"></b> <button type="button" class="rt-sec" id="rtRClose">Close replay</button></div>
			<div class="rt-stats" id="rtRStats"></div>
			<div class="rt-row rt-scrub">
				<button type="button" id="rtRPlay" aria-label="Play">&#9654;</button>
				<select id="rtRSpeed" aria-label="Replay speed"><option value="10">10×</option><option value="30" selected>30×</option><option value="60">60×</option><option value="120">120×</option></select>
				<input type="range" id="rtRSlider" min="0" max="0" value="0" aria-label="Replay position">
				<span id="rtRNow" class="rt-rnow"></span>
			</div>
			<div class="rt-legend" id="rtRLegend"></div>
			<div class="rt-events" id="rtREvents"></div>
		</div>
	</div>
	<?php if ( $cfg['adminOnly'] ) : ?>
	<p class="rt-note rt-adm">Admins only for now: racers get these tools when the racing hub goes live.</p>
	<?php endif; ?>
</section>
<script>window.OYC_RT=<?php echo wp_json_encode( $cfg ); ?>;</script>
<script src="<?php echo esc_url( $uri . '/assets/race-tools.js?ver=' . $ver( '/assets/race-tools.js' ) ); ?>" defer></script>
	<?php
	return ob_get_clean();
}
