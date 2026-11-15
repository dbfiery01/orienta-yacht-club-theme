<?php
/**
 * Offshore passage brief — the Claude-written part of the chart's "Passage brief" card.
 *
 * The polar router runs in the browser (inc/wind-map.php) and posts only its
 * computed numbers here; Claude turns them into a verdict, a short watch list
 * and a few sentences of advice, using only those numbers.
 *
 * Setup: add the API key to wp-config.php —
 *     define( 'OYC_ANTHROPIC_API_KEY', 'sk-ant-...' );
 * (or paste it in Settings → Passage brief, stored in the `oyc_anthropic_api_key` option). Without a key the card
 * shows its rule-based summary instead and the "Ask Claude" button is hidden.
 *
 * Signed-in members only, 25 briefs per member per day, identical requests
 * cached for 3 hours.
 *
 * @package Orienta_Yacht_Club
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** API key from wp-config (preferred) or the option. */
function oyc_passage_brief_key() {
	if ( defined( 'OYC_ANTHROPIC_API_KEY' ) && OYC_ANTHROPIC_API_KEY ) {
		return (string) OYC_ANTHROPIC_API_KEY;
	}
	return (string) get_option( 'oyc_anthropic_api_key', '' );
}

/** True when this visitor can ask Claude (key set and signed in). */
function oyc_passage_brief_ai_ready() {
	return is_user_logged_in() && '' !== oyc_passage_brief_key();
}

/* ─────────────────────────────────────────────────────────────────────────────
 * Settings → Passage brief: paste the Anthropic API key here to switch the
 * "Ask Claude" button on (wp-config's OYC_ANTHROPIC_API_KEY wins if set).
 * ───────────────────────────────────────────────────────────────────────────── */
add_action( 'admin_menu', function () {
	add_options_page( 'Passage brief', 'Passage brief', 'manage_options', 'oyc-passage-brief', 'oyc_passage_brief_settings_page' );
} );

add_action( 'admin_init', function () {
	register_setting( 'oyc_passage_brief', 'oyc_anthropic_api_key', array(
		'type'              => 'string',
		'sanitize_callback' => function ( $v ) {
			$v = trim( sanitize_text_field( (string) $v ) );
			if ( '' === $v ) {   // blank submit keeps the saved key
				return get_option( 'oyc_anthropic_api_key', '' );
			}
			return $v;
		},
	) );
} );

/** One tiny request to check the key works. Returns array( ok, message ). */
function oyc_passage_brief_test_key() {
	$key = oyc_passage_brief_key();
	if ( '' === $key ) {
		return array( false, 'No key saved yet.' );
	}
	$resp = wp_remote_post(
		'https://api.anthropic.com/v1/messages',
		array(
			'timeout' => 30,
			'headers' => array(
				'content-type'      => 'application/json',
				'x-api-key'         => $key,
				'anthropic-version' => '2026-10-21',
				'anthropic-beta'    => 'server-side-fallback-2026-07-01',
			),
			'body'    => wp_json_encode( array(
				'model'      => 'claude-opus-5-5',
				'fallbacks'  => 'default',
				'max_tokens' => 16,
				'messages'   => array( array( 'role' => 'user', 'content' => 'Reply with the single word: ready' ) ),
			) ),
		)
	);
	if ( is_wp_error( $resp ) ) {
		return array( false, 'The server couldn’t reach api.anthropic.com: ' . $resp->get_error_message() );
	}
	$code = (int) wp_remote_retrieve_response_code( $resp );
	$json = json_decode( wp_remote_retrieve_body( $resp ), true );
	if ( 200 === $code ) {
		return array( true, 'Connected. Claude answered using ' . ( is_array( $json ) && ! empty( $json['model'] ) ? $json['model'] : 'the API' ) . '.' );
	}
	$err = is_array( $json ) && ! empty( $json['error']['message'] ) ? $json['error']['message'] : '';
	if ( 401 === $code ) {
		return array( false, 'The key was rejected (401). Check it was copied in full.' );
	}
	return array( false, 'Anthropic returned HTTP ' . $code . ( $err ? ': ' . $err : '' ) );
}

function oyc_passage_brief_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( isset( $_POST['oyc_pb_clear'] ) && check_admin_referer( 'oyc_pb_tools' ) ) {
		delete_option( 'oyc_anthropic_api_key' );
		echo '<div class="notice notice-success"><p>API key removed. The Ask Claude button is off.</p></div>';
	}
	if ( isset( $_POST['oyc_pb_test'] ) && check_admin_referer( 'oyc_pb_tools' ) ) {
		$t = oyc_passage_brief_test_key();
		echo '<div class="notice notice-' . ( $t[0] ? 'success' : 'error' ) . '"><p>' . esc_html( $t[1] ) . '</p></div>';
	}
	$in_config = defined( 'OYC_ANTHROPIC_API_KEY' ) && OYC_ANTHROPIC_API_KEY;
	$has       = '' !== oyc_passage_brief_key();
	?>
	<div class="wrap">
		<h1>Passage brief</h1>
		<p>Switches on <b>Ask Claude for the brief</b> on the chart's Passage brief card (signed-in members only, 25 briefs per member per day). Create a key at <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">console.anthropic.com</a> under <b>API keys</b> and paste it below. Live and staging each need the key saved.</p>
		<p>Status: <b><?php echo $has ? 'On' : 'Off'; ?></b><?php echo $in_config ? ' (key set in wp-config.php)' : ''; ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'oyc_passage_brief' ); ?>
			<table class="form-table" role="presentation"><tr>
				<th scope="row"><label for="oyc_anthropic_api_key">Anthropic API key</label></th>
				<td>
					<input type="password" id="oyc_anthropic_api_key" name="oyc_anthropic_api_key" value="" class="regular-text" autocomplete="off" placeholder="<?php echo '' !== get_option( 'oyc_anthropic_api_key', '' ) ? esc_attr__( 'Saved. Paste a new key to replace it', 'orienta-yacht-club' ) : 'sk-ant-…'; ?>">
					<?php if ( $in_config ) : ?><p class="description">A key is set in wp-config.php (OYC_ANTHROPIC_API_KEY) and is used instead.</p><?php endif; ?>
				</td>
			</tr></table>
			<?php submit_button( 'Save key' ); ?>
		</form>
		<?php if ( $has ) : ?>
			<form method="post">
				<?php wp_nonce_field( 'oyc_pb_tools' ); ?>
				<?php submit_button( 'Test connection', 'secondary', 'oyc_pb_test', false ); ?>
				<?php if ( '' !== get_option( 'oyc_anthropic_api_key', '' ) ) { submit_button( 'Remove key', 'delete', 'oyc_pb_clear', false ); } ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

add_action( 'wp_ajax_oyc_passage_brief', 'oyc_passage_brief_ajax' );
add_action( 'wp_ajax_nopriv_oyc_passage_brief', 'oyc_passage_brief_ajax' );

function oyc_passage_brief_ajax() {
	if ( ! is_user_logged_in() ) {
		wp_send_json( array( 'ok' => false, 'msg' => 'Sign in to ask Claude for a written brief.' ) );
	}
	if ( ! check_ajax_referer( 'oyc_passage_brief', 'nonce', false ) ) {
		wp_send_json( array( 'ok' => false, 'msg' => 'This page has expired. Reload it and try again.' ) );
	}
	$key = oyc_passage_brief_key();
	if ( '' === $key ) {
		wp_send_json( array( 'ok' => false, 'msg' => 'Claude brief isn’t switched on for this site yet.' ) );
	}

	$raw   = isset( $_POST['facts'] ) ? wp_unslash( $_POST['facts'] ) : '';
	$facts = json_decode( $raw, true );
	if ( ! is_array( $facts ) || strlen( $raw ) > 20000 ) {
		wp_send_json( array( 'ok' => false, 'msg' => 'The route data didn’t come through. Try again.' ) );
	}
	$facts_json = wp_json_encode( $facts );

	$cache_key = 'oyc_pb_' . md5( $facts_json );
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		wp_send_json( array( 'ok' => true, 'brief' => $cached, 'cached' => true ) );
	}

	$uid   = get_current_user_id();
	$count_key = 'oyc_pb_n_' . $uid . '_' . gmdate( 'Ymd' );
	$count = (int) get_transient( $count_key );
	if ( $count >= 25 ) {
		wp_send_json( array( 'ok' => false, 'msg' => 'That’s today’s limit of Claude briefs. The routed numbers above are still current.' ) );
	}

	$system = "You write offshore passage briefs for members of a yacht club in Mamaroneck, NY. "
		. "You are given the output of a polar router that has already stepped the boat through the GFS wind forecast, "
		. "plus The Race tidal current, the NOAA Gulf Stream wall analysis and forecast seas. "
		. "The member picks the route (a preset, their own waypoints, or a start and finish); race_gate and gulf_stream are null when the route does not pass them. "
		. "Use only the numbers provided: never invent wind speeds, times, distances or hazards that are not in the data. "
		. "Write for an experienced cruising sailor, in plain English, without markdown. "
		. "verdict.level is go (no concerns), watch (go, but with named concerns) or hold (wait for a better window). "
		. "verdict.tag is 'GO', 'GO · WATCH' or 'HOLD'. verdict.text is one sentence of at most 25 words. "
		. "watch is 2 to 5 short items, each under 20 words, most important first. "
		. "take is 2 to 4 sentences: the shape of the passage, the main risk, and what you would do about timing. "
		. "If best_departure is clearly better, say so and why. Mention that this is a planning aid only if a hazard is serious.";

	$schema = array(
		'type'                 => 'object',
		'properties'           => array(
			'verdict' => array(
				'type'                 => 'object',
				'properties'           => array(
					'level' => array( 'type' => 'string', 'enum' => array( 'go', 'watch', 'hold' ) ),
					'tag'   => array( 'type' => 'string' ),
					'text'  => array( 'type' => 'string' ),
				),
				'required'             => array( 'level', 'tag', 'text' ),
				'additionalProperties' => false,
			),
			'watch'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
			'take'    => array( 'type' => 'string' ),
		),
		'required'             => array( 'verdict', 'watch', 'take' ),
		'additionalProperties' => false,
	);

	$body = array(
		'model'         => 'claude-opus-5-5',
		'max_tokens'    => 8000,
		'fallbacks'     => 'default',
		'output_config' => array(
			'effort' => 'low',
			'format' => array( 'type' => 'json_schema', 'schema' => $schema ),
		),
		'system'        => $system,
		'messages'      => array(
			array( 'role' => 'user', 'content' => "Router output for this passage (JSON):\n" . $facts_json ),
		),
	);

	/* Raw HTTP rather than the PHP SDK: the theme deploys through WP Pusher with no Composer step. */
	$resp = wp_remote_post(
		'https://api.anthropic.com/v1/messages',
		array(
			'timeout' => 60,
			'headers' => array(
				'content-type'      => 'application/json',
				'x-api-key'         => $key,
				'anthropic-version' => '2026-10-21',
				'anthropic-beta'    => 'server-side-fallback-2026-07-01',
			),
			'body'    => wp_json_encode( $body ),
		)
	);
	if ( is_wp_error( $resp ) ) {
		wp_send_json( array( 'ok' => false, 'msg' => 'Couldn’t reach Claude just now. Try again in a minute.' ) );
	}
	$code = (int) wp_remote_retrieve_response_code( $resp );
	$json = json_decode( wp_remote_retrieve_body( $resp ), true );
	if ( 200 !== $code || ! is_array( $json ) ) {
		$msg = ( 429 === $code || 529 === $code ) ? 'Claude is busy right now. Try again in a minute.' : 'Claude couldn’t write the brief just now.';
		wp_send_json( array( 'ok' => false, 'msg' => $msg, 'code' => $code ) );
	}
	if ( isset( $json['stop_reason'] ) && 'refusal' === $json['stop_reason'] ) {
		wp_send_json( array( 'ok' => false, 'msg' => 'Claude declined to write this brief. The routed numbers above still stand.' ) );
	}
	$text = '';
	foreach ( (array) ( $json['content'] ?? array() ) as $block ) {
		if ( isset( $block['type'] ) && 'text' === $block['type'] ) {
			$text = (string) $block['text'];
			break;
		}
	}
	$brief = json_decode( $text, true );
	if ( ! is_array( $brief ) || empty( $brief['verdict'] ) || ! isset( $brief['take'] ) ) {
		wp_send_json( array( 'ok' => false, 'msg' => 'Claude’s reply came back incomplete. Try again.' ) );
	}
	$level = in_array( $brief['verdict']['level'] ?? '', array( 'go', 'watch', 'hold' ), true ) ? $brief['verdict']['level'] : 'watch';
	$clean = array(
		'verdict' => array(
			'level' => $level,
			'tag'   => sanitize_text_field( $brief['verdict']['tag'] ?? '' ),
			'text'  => sanitize_text_field( $brief['verdict']['text'] ?? '' ),
		),
		'watch'   => array_slice( array_map( 'sanitize_text_field', array_filter( (array) ( $brief['watch'] ?? array() ), 'is_string' ) ), 0, 6 ),
		'take'    => sanitize_textarea_field( $brief['take'] ),
	);

	set_transient( $count_key, $count + 1, DAY_IN_SECONDS );
	set_transient( $cache_key, $clean, 3 * HOUR_IN_SECONDS );
	wp_send_json( array( 'ok' => true, 'brief' => $clean ) );
}

/* ======================================================================
 * Window accuracy log
 *
 * Every routed window a signed-in member looks at (the departure they
 * picked, and the "better window" the router suggests) is saved with the
 * forecast it was based on: the boat's position every 3 h, the predicted
 * wind there, which samples were inside the Gulf Stream, predicted seas and
 * the verdict. Once the boat would have arrived, a daily job fetches what
 * the weather actually did at those same places and times (Open-Meteo GFS
 * historical analysis + marine archive) and scores the call with the same
 * danger rules the card uses:
 *   dangerous = wind 34+ kt, seas 12+ ft, or 20+ kt against the Stream
 *   rough     = wind 25+ kt, seas 8+ ft, or 12+ kt against the Stream
 *   safe      = neither
 * A GO / GO·WATCH call is a good prediction when the crossing was not
 * dangerous; a HOLD is good when it was.
 * ====================================================================== */

const OYC_PB_LOG_OPT = 'oyc_passage_windows';
const OYC_PB_LOG_MAX = 400;

function oyc_pb_log_get() {
	$l = get_option( OYC_PB_LOG_OPT, array() );
	return is_array( $l ) ? $l : array();
}
function oyc_pb_log_put( $l ) {
	if ( count( $l ) > OYC_PB_LOG_MAX ) {
		$l = array_slice( $l, -OYC_PB_LOG_MAX );
	}
	update_option( OYC_PB_LOG_OPT, $l, false );
}

/** Danger class for one sample: 0 safe, 1 rough, 2 dangerous. */
function oyc_pb_danger( $kt, $dir, $sea, $in_gs ) {
	$wac = $in_gs && null !== $dir && $dir >= 10 && $dir <= 130;   /* wind from N-ESE against a NE-setting Stream */
	if ( ( null !== $kt && $kt >= 34 ) || ( null !== $sea && $sea >= 12 ) || ( $wac && $kt >= 20 ) ) {
		return 2;
	}
	if ( ( null !== $kt && $kt >= 25 ) || ( null !== $sea && $sea >= 8 ) || ( $wac && $kt >= 12 ) ) {
		return 1;
	}
	return 0;
}

add_action( 'wp_ajax_oyc_passage_log', 'oyc_passage_log_ajax' );
function oyc_passage_log_ajax() {
	if ( ! check_ajax_referer( 'oyc_passage_brief', 'nonce', false ) ) {
		wp_send_json( array( 'ok' => false ) );
	}
	$w = json_decode( wp_unslash( $_POST['window'] ?? '' ), true );
	if ( ! is_array( $w ) || empty( $w['depart'] ) || empty( $w['samples'] ) || ! is_array( $w['samples'] ) ) {
		wp_send_json( array( 'ok' => false ) );
	}
	$depart = (int) $w['depart'];
	$run    = sanitize_text_field( $w['run'] ?? '' );
	$route  = sanitize_key( $w['route'] ?? 'bermuda' );
	$floor  = round( (float) ( $w['floor'] ?? 6.5 ), 1 );
	$id     = substr( md5( $route . '|' . $depart . '|' . $run . '|' . $floor ), 0, 12 );
	if ( $depart < ( time() - 3600 ) * 1000 ) {
		wp_send_json( array( 'ok' => true, 'skipped' => 'past' ) );   /* only forward-looking calls count */
	}
	$log = oyc_pb_log_get();
	foreach ( $log as $e ) {
		if ( $e['id'] === $id ) {
			wp_send_json( array( 'ok' => true, 'id' => $id, 'dup' => true ) );
		}
	}
	$samples = array();
	foreach ( array_slice( $w['samples'], 0, 60 ) as $s ) {
		$samples[] = array(
			't'   => (int) $s['t'],
			'lat' => round( (float) $s['lat'], 2 ),
			'lon' => round( (float) $s['lon'], 2 ),
			'kt'  => isset( $s['kt'] ) && null !== $s['kt'] ? round( (float) $s['kt'], 1 ) : null,
			'dir' => isset( $s['dir'] ) && null !== $s['dir'] ? (int) $s['dir'] : null,
			'sea' => isset( $s['sea'] ) && null !== $s['sea'] ? round( (float) $s['sea'], 1 ) : null,
			'gs'  => ! empty( $s['gs'] ),
		);
	}
	$log[] = array(
		'id'      => $id,
		'route'   => $route,
		'kind'    => 'best' === ( $w['kind'] ?? '' ) ? 'best' : 'chosen',
		'created' => time(),
		'run'     => $run,
		'floor'   => $floor,
		'depart'  => $depart,
		'eta'     => (int) $w['eta'],
		'verdict' => in_array( $w['verdict'] ?? '', array( 'go', 'watch', 'hold' ), true ) ? $w['verdict'] : 'watch',
		'samples' => $samples,
		'by'      => get_current_user_id(),
	);
	oyc_pb_log_put( $log );
	wp_send_json( array( 'ok' => true, 'id' => $id ) );
}

/** Fetch observed (analysis) wind + seas for one logged window and score it. */
function oyc_pb_verify_entry( $e ) {
	$s = $e['samples'];
	if ( ! $s ) {
		return null;
	}
	$lats = implode( ',', array_map( function ( $x ) { return $x['lat']; }, $s ) );
	$lons = implode( ',', array_map( function ( $x ) { return $x['lon']; }, $s ) );
	$d0   = gmdate( 'Y-m-d', (int) ( $s[0]['t'] / 1000 ) );
	$d1   = gmdate( 'Y-m-d', (int) ( end( $s )['t'] / 1000 ) );
	$q    = '?latitude=' . $lats . '&longitude=' . $lons . '&start_date=' . $d0 . '&end_date=' . $d1 . '&timezone=GMT';
	$args = array( 'timeout' => 25, 'headers' => array( 'User-Agent' => 'OYC-Weather/1.0' ) );
	$wr   = wp_remote_get( 'https://historical-forecast-api.open-meteo.com/v1/forecast' . $q . '&hourly=wind_speed_10m,wind_direction_10m&wind_speed_unit=kn&models=gfs_seamless', $args );
	if ( is_wp_error( $wr ) || 200 !== (int) wp_remote_retrieve_response_code( $wr ) ) {
		return null;
	}
	$wj = json_decode( wp_remote_retrieve_body( $wr ), true );
	$wj = isset( $wj['hourly'] ) ? array( $wj ) : (array) $wj;
	$mr = wp_remote_get( 'https://marine-api.open-meteo.com/v1/marine' . $q . '&hourly=wave_height&length_unit=imperial', $args );
	$mj = ( ! is_wp_error( $mr ) && 200 === (int) wp_remote_retrieve_response_code( $mr ) ) ? json_decode( wp_remote_retrieve_body( $mr ), true ) : null;
	$mj = is_array( $mj ) ? ( isset( $mj['hourly'] ) ? array( $mj ) : $mj ) : array();

	$pred_cls = 0; $act_cls = 0; $err = array(); $worst = '';
	foreach ( $s as $i => $x ) {
		$h = $wj[ $i ]['hourly'] ?? null;
		if ( ! $h || empty( $h['time'] ) ) {
			continue;
		}
		$k = (int) round( ( $x['t'] / 1000 - strtotime( $h['time'][0] . ':00Z' ) ) / 3600 );
		if ( $k < 0 || $k >= count( $h['time'] ) ) {
			continue;
		}
		$akt  = $h['wind_speed_10m'][ $k ] ?? null;
		$adir = $h['wind_direction_10m'][ $k ] ?? null;
		$mh   = $mj[ $i ]['hourly'] ?? null;
		$asea = ( $mh && isset( $mh['wave_height'][ $k ] ) ) ? $mh['wave_height'][ $k ] : null;
		$pc = oyc_pb_danger( $x['kt'], $x['dir'], $x['sea'], $x['gs'] );
		$ac = oyc_pb_danger( $akt, $adir, $asea, $x['gs'] );
		$pred_cls = max( $pred_cls, $pc );
		if ( $ac > $act_cls ) {
			$act_cls = $ac;
			$worst   = round( (float) $akt ) . ' kt from ' . (int) $adir . '°' . ( null !== $asea ? ', seas ' . round( (float) $asea ) . ' ft' : '' ) . ( $x['gs'] ? ' in the Gulf Stream' : '' );
		}
		if ( null !== $akt && null !== $x['kt'] ) {
			$err[] = abs( $akt - $x['kt'] );
		}
	}
	if ( ! $err ) {
		return null;
	}
	$labels = array( 'safe', 'rough', 'dangerous' );
	$go     = 'hold' !== $e['verdict'];
	return array(
		'at'        => time(),
		'actual'    => $labels[ $act_cls ],
		'predicted' => $labels[ $pred_cls ],
		'good'      => $go ? ( $act_cls < 2 ) : ( 2 === $act_cls ),
		'wind_mae'  => round( array_sum( $err ) / count( $err ), 1 ),
		'worst'     => $worst,
		'n'         => count( $err ),
	);
}

/** Score every window whose boat would have arrived 12+ h ago (a few per run, to stay inside Open-Meteo's limits). */
function oyc_pb_verify_due( $max = 8 ) {
	$log  = oyc_pb_log_get();
	$done = 0;
	$now  = time() * 1000;
	foreach ( $log as &$e ) {
		if ( isset( $e['check'] ) || $e['eta'] + 12 * 3600 * 1000 > $now ) {
			continue;
		}
		if ( $now - $e['eta'] > 60 * 86400 * 1000 ) {
			$e['check'] = array( 'at' => time(), 'expired' => true );
			continue;
		}
		$c = oyc_pb_verify_entry( $e );
		if ( $c ) {
			$e['check'] = $c;
		}
		if ( ++$done >= $max ) {
			break;
		}
	}
	unset( $e );
	oyc_pb_log_put( $log );
	return $done;
}
add_action( 'oyc_passage_verify', 'oyc_pb_verify_due' );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'oyc_passage_verify' ) ) {
		wp_schedule_event( time() + 600, 'twicedaily', 'oyc_passage_verify' );
	}
} );

/** Track record for the card: totals plus the latest verified windows. */
function oyc_pb_record() {
	$log = oyc_pb_log_get();
	$v = array_values( array_filter( $log, function ( $e ) { return isset( $e['check']['actual'] ); } ) );
	$go = array_values( array_filter( $v, function ( $e ) { return 'hold' !== $e['verdict']; } ) );
	$hold = array_values( array_filter( $v, function ( $e ) { return 'hold' === $e['verdict']; } ) );
	$mae = $v ? round( array_sum( array_map( function ( $e ) { return $e['check']['wind_mae']; }, $v ) ) / count( $v ), 1 ) : null;
	$pending = count( array_filter( $log, function ( $e ) { return ! isset( $e['check'] ); } ) );
	usort( $v, function ( $a, $b ) { return $b['depart'] <=> $a['depart']; } );
	$recent = array_map( function ( $e ) {
		return array(
			'depart'  => $e['depart'],
			'kind'    => $e['kind'],
			'verdict' => $e['verdict'],
			'actual'  => $e['check']['actual'],
			'good'    => $e['check']['good'],
			'mae'     => $e['check']['wind_mae'],
			'worst'   => $e['check']['worst'],
		);
	}, array_slice( $v, 0, 8 ) );
	return array(
		'verified'  => count( $v ),
		'pending'   => $pending,
		'go'        => count( $go ),
		'go_good'   => count( array_filter( $go, function ( $e ) { return $e['check']['good']; } ) ),
		'hold'      => count( $hold ),
		'hold_good' => count( array_filter( $hold, function ( $e ) { return $e['check']['good']; } ) ),
		'wind_mae'  => $mae,
		'recent'    => $recent,
	);
}
add_action( 'wp_ajax_oyc_passage_record', 'oyc_passage_record_ajax' );
add_action( 'wp_ajax_nopriv_oyc_passage_record', 'oyc_passage_record_ajax' );
function oyc_passage_record_ajax() {
	if ( current_user_can( 'manage_options' ) && ! empty( $_GET['verify'] ) ) {
		oyc_pb_verify_due( 8 );   /* admins: "Check now" */
	}
	wp_send_json( array( 'ok' => true, 'record' => oyc_pb_record(), 'admin' => current_user_can( 'manage_options' ) ) );
}
