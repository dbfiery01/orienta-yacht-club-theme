<?php
/**
 * AIS ships on the Wind & Radar map (chart plotter "AIS ships" toggle).
 *
 * Source: aisstream.io, a free live AIS feed. It is a websocket that must be
 * opened server-side (browser connections are refused, and the API key must not
 * reach the page), so the site listens for a few seconds at a time and keeps the
 * vessels it heard in one transient:
 *
 *   - A viewer with "AIS ships" on polls admin-ajax oyc_ais every 30 s.
 *   - When the store is older than OYC_AIS_REFRESH seconds, that request
 *     listens for up to OYC_AIS_LISTEN seconds (after the response is sent,
 *     where PHP-FPM allows) and merges what it heard. Nobody watching = no
 *     traffic to aisstream at all.
 *   - A vessel drops out OYC_AIS_TTL seconds after its last report.
 *
 * API key: Settings → AIS ships in wp-admin (option oyc_aisstream_key), or the
 * constant OYC_AISSTREAM_KEY in wp-config.php. Never in this (public) repo.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'OYC_AIS_STORE', 'oyc_ais_vessels' );
define( 'OYC_AIS_TTL', 900 );      // vessel kept 15 min after its last report
define( 'OYC_AIS_REFRESH', 90 );   // listen again when the store is this old
define( 'OYC_AIS_LISTEN', 8 );     // seconds per listen

/** Western Long Island Sound + the East River approach: [[lat,lon],[lat,lon]]. */
function oyc_ais_box() {
	return apply_filters( 'oyc_ais_box', array( array( 40.70, -74.05 ), array( 41.15, -73.30 ) ) );
}

function oyc_ais_key() {
	if ( defined( 'OYC_AISSTREAM_KEY' ) && OYC_AISSTREAM_KEY ) {
		return (string) OYC_AISSTREAM_KEY;
	}
	return trim( (string) get_option( 'oyc_aisstream_key', '' ) );
}

/* ─────────────────────────────────────────────────────────────────────────────
 * Settings → AIS ships
 * ───────────────────────────────────────────────────────────────────────────── */

add_action( 'admin_menu', function () {
	add_options_page( 'AIS ships', 'AIS ships', 'manage_options', 'oyc-ais', 'oyc_ais_settings_page' );
} );

add_action( 'admin_init', function () {
	register_setting( 'oyc_ais', 'oyc_aisstream_key', array(
		'type'              => 'string',
		'sanitize_callback' => function ( $v ) {
			$v = trim( sanitize_text_field( (string) $v ) );
			if ( '' === $v ) {   // blank submit keeps the saved key
				return get_option( 'oyc_aisstream_key', '' );
			}
			delete_transient( OYC_AIS_STORE );
			delete_option( 'oyc_ais_status' );
			return $v;
		},
	) );
} );

function oyc_ais_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( isset( $_POST['oyc_ais_clear'] ) && check_admin_referer( 'oyc_ais_clear' ) ) {
		delete_option( 'oyc_aisstream_key' );
		delete_transient( OYC_AIS_STORE );
		delete_option( 'oyc_ais_status' );
		echo '<div class="notice notice-success"><p>API key removed. The AIS ships toggle is off for everyone.</p></div>';
	}
	if ( isset( $_POST['oyc_ais_test'] ) && check_admin_referer( 'oyc_ais_clear' ) ) {
		oyc_ais_refresh( true );
	}
	$has    = '' !== oyc_ais_key();
	$st     = get_option( 'oyc_ais_status', array() );
	$store  = oyc_ais_load();
	?>
	<div class="wrap">
		<h1>AIS ships</h1>
		<p>Shows live AIS vessels on the weather page's Wind &amp; Radar map (the <b>AIS ships</b> toggle). Data comes from <a href="https://aisstream.io" target="_blank" rel="noopener">aisstream.io</a>: sign in there (free), open <b>API Keys</b>, create a key and paste it below. Live and staging each need the key saved.</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'oyc_ais' ); ?>
			<table class="form-table" role="presentation"><tr>
				<th scope="row"><label for="oyc_aisstream_key">aisstream.io API key</label></th>
				<td>
					<input type="password" id="oyc_aisstream_key" name="oyc_aisstream_key" value="" class="regular-text" autocomplete="off" placeholder="<?php echo $has ? esc_attr__( 'Saved. Paste a new key to replace it', 'orienta-yacht-club' ) : ''; ?>">
					<?php if ( defined( 'OYC_AISSTREAM_KEY' ) && OYC_AISSTREAM_KEY ) : ?><p class="description">A key is set in wp-config.php (OYC_AISSTREAM_KEY) and is used instead.</p><?php endif; ?>
				</td>
			</tr></table>
			<?php submit_button( 'Save key' ); ?>
		</form>
		<?php if ( $has ) : ?>
			<form method="post">
				<?php wp_nonce_field( 'oyc_ais_clear' ); ?>
				<p>
					<button type="submit" name="oyc_ais_test" value="1" class="button">Test now (listens ~<?php echo (int) OYC_AIS_LISTEN; ?> s)</button>
					<button type="submit" name="oyc_ais_clear" value="1" class="button-link-delete" onclick="return confirm('Remove the AIS API key?');">Remove key</button>
				</p>
			</form>
			<h2>Status</h2>
			<p>
				<?php
				if ( ! empty( $st['t'] ) ) {
					printf(
						'Last listen %s ago: %s. ',
						esc_html( human_time_diff( (int) $st['t'] ) ),
						esc_html( $st['msg'] )
					);
				} else {
					echo 'Not run yet. It runs when someone turns on AIS ships, or press Test now. ';
				}
				printf( '%d vessels in the area right now.', count( $store['v'] ) );
				?>
			</p>
		<?php endif; ?>
	</div>
	<?php
}

/* ─────────────────────────────────────────────────────────────────────────────
 * Store
 * ───────────────────────────────────────────────────────────────────────────── */

function oyc_ais_load() {
	$s = get_transient( OYC_AIS_STORE );
	if ( ! is_array( $s ) || ! isset( $s['v'] ) ) {
		$s = array( 't' => 0, 'v' => array() );
	}
	$cut = time() - OYC_AIS_TTL;
	foreach ( $s['v'] as $m => $v ) {
		if ( empty( $v['t'] ) || $v['t'] < $cut ) {
			unset( $s['v'][ $m ] );
		}
	}
	return $s;
}

/** Ship-type code → short label. */
function oyc_ais_type_label( $t ) {
	$t = (int) $t;
	if ( 36 === $t ) { return 'Sailing'; }
	if ( 37 === $t ) { return 'Pleasure craft'; }
	if ( 30 === $t ) { return 'Fishing'; }
	if ( 31 === $t || 32 === $t || 52 === $t ) { return 'Tug / towing'; }
	if ( 33 === $t ) { return 'Dredging'; }
	if ( 35 === $t || 55 === $t ) { return 'Military / law enforcement'; }
	if ( 50 === $t ) { return 'Pilot'; }
	if ( 51 === $t ) { return 'Search & rescue'; }
	if ( $t >= 40 && $t <= 49 ) { return 'High-speed craft'; }
	if ( $t >= 60 && $t <= 69 ) { return 'Passenger / ferry'; }
	if ( $t >= 70 && $t <= 79 ) { return 'Cargo'; }
	if ( $t >= 80 && $t <= 89 ) { return 'Tanker'; }
	return '';
}

/** Merge one decoded aisstream message into the vessel list. */
function oyc_ais_absorb( &$vessels, $msg ) {
	if ( ! is_array( $msg ) || empty( $msg['MetaData']['MMSI'] ) ) {
		return;
	}
	$md   = $msg['MetaData'];
	$mmsi = (string) (int) $md['MMSI'];
	$type = isset( $msg['MessageType'] ) ? $msg['MessageType'] : '';
	$body = isset( $msg['Message'][ $type ] ) && is_array( $msg['Message'][ $type ] ) ? $msg['Message'][ $type ] : array();
	$v    = isset( $vessels[ $mmsi ] ) ? $vessels[ $mmsi ] : array( 'n' => '', 'ty' => 0 );

	$name = isset( $md['ShipName'] ) ? trim( preg_replace( '/[@\s]+/', ' ', (string) $md['ShipName'] ) ) : '';
	if ( '' !== $name ) {
		$v['n'] = substr( $name, 0, 40 );
	}

	if ( in_array( $type, array( 'PositionReport', 'StandardClassBPositionReport', 'ExtendedClassBPositionReport' ), true ) ) {
		$lat = isset( $body['Latitude'] ) ? (float) $body['Latitude'] : ( isset( $md['latitude'] ) ? (float) $md['latitude'] : 91 );
		$lon = isset( $body['Longitude'] ) ? (float) $body['Longitude'] : ( isset( $md['longitude'] ) ? (float) $md['longitude'] : 181 );
		if ( abs( $lat ) > 90 || abs( $lon ) > 180 ) {
			return;
		}
		$v['lat'] = round( $lat, 5 );
		$v['lon'] = round( $lon, 5 );
		$sog      = isset( $body['Sog'] ) ? (float) $body['Sog'] : null;
		$cog      = isset( $body['Cog'] ) ? (float) $body['Cog'] : null;
		$hdg      = isset( $body['TrueHeading'] ) ? (int) $body['TrueHeading'] : null;
		$v['sog'] = ( null !== $sog && $sog < 102 ) ? round( $sog, 1 ) : null;
		$v['cog'] = ( null !== $cog && $cog < 360 ) ? round( $cog ) : null;
		$v['hdg'] = ( null !== $hdg && $hdg < 360 ) ? $hdg : null;
		$v['b']   = 'PositionReport' === $type ? 'A' : 'B';
		$v['t']   = time();
	} elseif ( 'ShipStaticData' === $type ) {
		if ( ! empty( $body['Type'] ) ) {
			$v['ty'] = (int) $body['Type'];
		}
		if ( ! empty( $body['Name'] ) ) {
			$v['n'] = substr( trim( preg_replace( '/[@\s]+/', ' ', $body['Name'] ) ), 0, 40 );
		}
	} elseif ( 'StaticDataReport' === $type ) {
		if ( ! empty( $body['ReportB']['ShipType'] ) ) {
			$v['ty'] = (int) $body['ReportB']['ShipType'];
		}
		if ( ! empty( $body['ReportA']['Name'] ) ) {
			$v['n'] = substr( trim( preg_replace( '/[@\s]+/', ' ', $body['ReportA']['Name'] ) ), 0, 40 );
		}
	} else {
		return;
	}
	$vessels[ $mmsi ] = $v;
}

/* ─────────────────────────────────────────────────────────────────────────────
 * Minimal websocket client (TLS, one subscription, read for N seconds)
 * ───────────────────────────────────────────────────────────────────────────── */

function oyc_ais_ws_send( $sock, $payload, $opcode = 1 ) {
	$len  = strlen( $payload );
	$head = chr( 0x80 | $opcode );
	if ( $len < 126 ) {
		$head .= chr( 0x80 | $len );
	} elseif ( $len < 65536 ) {
		$head .= chr( 0x80 | 126 ) . pack( 'n', $len );
	} else {
		$head .= chr( 0x80 | 127 ) . pack( 'J', $len );
	}
	$mask = random_bytes( 4 );
	$out  = '';
	for ( $i = 0; $i < $len; $i++ ) {
		$out .= $payload[ $i ] ^ $mask[ $i % 4 ];
	}
	return false !== fwrite( $sock, $head . $mask . $out );
}

/** Read exactly $n bytes before $deadline, or false. */
function oyc_ais_ws_read( $sock, $n, $deadline ) {
	$buf = '';
	while ( strlen( $buf ) < $n ) {
		if ( microtime( true ) > $deadline || feof( $sock ) ) {
			return false;
		}
		$chunk = fread( $sock, $n - strlen( $buf ) );
		if ( false === $chunk ) {
			return false;
		}
		if ( '' === $chunk ) {
			usleep( 20000 );
			continue;
		}
		$buf .= $chunk;
	}
	return $buf;
}

/**
 * Listen to aisstream for $secs seconds; returns array of decoded messages, or
 * WP_Error.
 */
function oyc_ais_listen( $key, $secs ) {
	$url  = apply_filters( 'oyc_ais_endpoint', 'tls://stream.aisstream.io:443' );
	$host = apply_filters( 'oyc_ais_host', 'stream.aisstream.io' );
	$ctx  = stream_context_create( array( 'ssl' => array( 'verify_peer' => true, 'peer_name' => $host, 'SNI_enabled' => true ) ) );
	$sock = @stream_socket_client( $url, $errno, $errstr, 8, STREAM_CLIENT_CONNECT, $ctx );
	if ( ! $sock ) {
		return new WP_Error( 'connect', 'could not connect to aisstream.io (' . $errstr . ')' );
	}
	stream_set_timeout( $sock, 2 );
	$wskey = base64_encode( random_bytes( 16 ) );
	fwrite( $sock, "GET /v0/stream HTTP/1.1\r\nHost: $host\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: $wskey\r\nSec-WebSocket-Version: 13\r\n\r\n" );
	$deadline = microtime( true ) + 6;
	$resp     = '';
	while ( false === strpos( $resp, "\r\n\r\n" ) ) {
		$line = fgets( $sock );
		if ( false === $line ) {
			if ( microtime( true ) > $deadline || feof( $sock ) ) {
				fclose( $sock );
				return new WP_Error( 'handshake', 'aisstream.io did not answer the connection' );
			}
			continue;
		}
		$resp .= $line;
	}
	if ( ! preg_match( '#^HTTP/1\.1 101#', $resp ) ) {
		fclose( $sock );
		return new WP_Error( 'handshake', 'aisstream.io refused the connection (' . strtok( $resp, "\r\n" ) . ')' );
	}
	$box = oyc_ais_box();
	oyc_ais_ws_send( $sock, wp_json_encode( array(
		'APIKey'             => $key,
		'BoundingBoxes'      => array( $box ),
		'FilterMessageTypes' => array( 'PositionReport', 'StandardClassBPositionReport', 'ExtendedClassBPositionReport', 'ShipStaticData', 'StaticDataReport' ),
	) ) );

	$out      = array();
	$end      = microtime( true ) + $secs;
	$frag     = '';
	$err      = null;
	stream_set_blocking( $sock, false );
	while ( microtime( true ) < $end && count( $out ) < 3000 ) {
		$h = oyc_ais_ws_read( $sock, 2, $end );
		if ( false === $h ) {
			break;
		}
		$fin = ord( $h[0] ) & 0x80;
		$op  = ord( $h[0] ) & 0x0f;
		$len = ord( $h[1] ) & 0x7f;
		if ( 126 === $len ) {
			$x = oyc_ais_ws_read( $sock, 2, $end + 2 );
			if ( false === $x ) { break; }
			$len = unpack( 'n', $x )[1];
		} elseif ( 127 === $len ) {
			$x = oyc_ais_ws_read( $sock, 8, $end + 2 );
			if ( false === $x ) { break; }
			$len = unpack( 'J', $x )[1];
		}
		if ( $len > 1048576 ) {
			break;
		}
		$data = $len ? oyc_ais_ws_read( $sock, $len, $end + 2 ) : '';
		if ( false === $data ) {
			break;
		}
		if ( 9 === $op ) {                       // ping → pong
			oyc_ais_ws_send( $sock, $data, 10 );
			continue;
		}
		if ( 8 === $op ) {                       // server closed (bad key, etc.)
			$reason = strlen( $data ) > 2 ? substr( $data, 2 ) : '';
			if ( ! $out ) {
				$err = 'aisstream.io closed the connection' . ( $reason ? ' (' . $reason . ')' : '' );
			}
			break;
		}
		if ( 1 === $op || 2 === $op || 0 === $op ) {
			$frag .= $data;
			if ( $fin ) {
				$j = json_decode( $frag, true );
				$frag = '';
				if ( is_array( $j ) ) {
					if ( isset( $j['error'] ) ) {
						$err = 'aisstream.io: ' . $j['error'];
						break;
					}
					$out[] = $j;
				}
			}
		}
	}
	@oyc_ais_ws_send( $sock, '', 8 );
	fclose( $sock );
	if ( $err ) {
		return new WP_Error( 'stream', $err );
	}
	return $out;
}

/** Listen once (lock-guarded) and merge into the store. */
function oyc_ais_refresh( $force = false ) {
	$key = oyc_ais_key();
	if ( '' === $key ) {
		return;
	}
	if ( ! $force && get_transient( 'oyc_ais_lock' ) ) {
		return;
	}
	set_transient( 'oyc_ais_lock', 1, OYC_AIS_LISTEN + 20 );
	$msgs = oyc_ais_listen( $key, OYC_AIS_LISTEN );
	$s    = oyc_ais_load();
	if ( is_wp_error( $msgs ) ) {
		update_option( 'oyc_ais_status', array( 't' => time(), 'msg' => $msgs->get_error_message(), 'ok' => false ), false );
		$s['t'] = time() - OYC_AIS_REFRESH + 300;   // back off ~5 min after a failure
	} else {
		foreach ( $msgs as $m ) {
			oyc_ais_absorb( $s['v'], $m );
		}
		$s['t'] = time();
		update_option( 'oyc_ais_status', array( 't' => time(), 'msg' => count( $msgs ) . ' messages heard', 'ok' => true ), false );
	}
	set_transient( OYC_AIS_STORE, $s, OYC_AIS_TTL + 300 );
	delete_transient( 'oyc_ais_lock' );
}

/* ─────────────────────────────────────────────────────────────────────────────
 * Map endpoint (public)
 * ───────────────────────────────────────────────────────────────────────────── */

add_action( 'wp_ajax_oyc_ais', 'oyc_ais_ajax' );
add_action( 'wp_ajax_nopriv_oyc_ais', 'oyc_ais_ajax' );
function oyc_ais_ajax() {
	nocache_headers();
	if ( '' === oyc_ais_key() ) {
		wp_send_json( array( 'ok' => true, 'enabled' => false, 'vessels' => array() ) );
	}
	$s     = oyc_ais_load();
	$stale = ( time() - (int) $s['t'] ) > OYC_AIS_REFRESH;
	if ( $stale && ! $s['v'] && ! get_transient( 'oyc_ais_lock' ) ) {
		oyc_ais_refresh();   // first viewer: wait for one listen so the map isn't empty
		$s     = oyc_ais_load();
		$stale = false;
	}
	$now = time();
	$out = array();
	foreach ( $s['v'] as $m => $v ) {
		if ( ! isset( $v['lat'] ) ) {
			continue;   // static data heard, no position yet
		}
		$out[] = array(
			'm'   => (string) $m,
			'n'   => isset( $v['n'] ) ? $v['n'] : '',
			'ty'  => oyc_ais_type_label( isset( $v['ty'] ) ? $v['ty'] : 0 ),
			'tc'  => isset( $v['ty'] ) ? (int) $v['ty'] : 0,
			'lat' => $v['lat'],
			'lon' => $v['lon'],
			'sog' => isset( $v['sog'] ) ? $v['sog'] : null,
			'cog' => isset( $v['cog'] ) ? $v['cog'] : null,
			'hdg' => isset( $v['hdg'] ) ? $v['hdg'] : null,
			'age' => max( 0, $now - (int) $v['t'] ),
		);
	}
	$payload = wp_json_encode( array( 'ok' => true, 'enabled' => true, 'vessels' => $out ) );
	if ( $stale && ! get_transient( 'oyc_ais_lock' ) ) {
		// Send the cached list now, then listen in the background where possible.
		header( 'Content-Type: application/json; charset=utf-8' );
		echo $payload; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( function_exists( 'fastcgi_finish_request' ) ) {
			fastcgi_finish_request();
		}
		ignore_user_abort( true );
		oyc_ais_refresh();
		exit;
	}
	header( 'Content-Type: application/json; charset=utf-8' );
	echo $payload; // phpcs:ignore WordPress.Security.EscapeOutput
	exit;
}
