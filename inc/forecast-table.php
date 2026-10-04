<?php
/**
 * OYC Forecast Table — SailFlow-style multi-model hourly forecast for the
 * weather board (/weather/), replacing the old 48-Hour Outlook card.
 *
 * - Wind / sky / temp / precip / cloud / pressure: Open-Meteo (keyless, CORS-ok,
 *   fetched client-side, multi-model: BLEND/GFS/ECMWF/ICON/GEM).
 * - Wave FORECAST: Open-Meteo Marine API (client-side).
 * - Observed "now" waves: nearest live NDBC buoy 44022 (Execution Rocks) -> 44040
 *   (Western LI Sound). NDBC sends no CORS header, so it is read through the
 *   server-side proxy below (admin-ajax) and cached in a transient.
 *
 * Rendered full-width as a self-contained light "readout sheet" on the dark
 * board. All CSS is scoped under .oyc-ft and IDs are prefixed oycft- to avoid
 * colliding with the board's own markup/styles; the JS runs in an IIFE.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ── Server-side buoy proxy: latest observed wave from NDBC 44022 → 44040 ── */
add_action( 'wp_ajax_oyc_buoy',        'oyc_buoy_proxy' );
add_action( 'wp_ajax_nopriv_oyc_buoy', 'oyc_buoy_proxy' );
function oyc_buoy_proxy() {
	$cached = get_transient( 'oyc_buoy_obs' );
	if ( false !== $cached ) { wp_send_json( $cached ); }

	$out = array( 'ok' => false );
	foreach ( array( array( '44022', 'Execution Rocks' ), array( '44040', 'Western LI Sound' ) ) as $st ) {
		$resp = wp_remote_get( 'https://www.ndbc.noaa.gov/data/realtime2/' . $st[0] . '.txt', array( 'timeout' => 6 ) );
		if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) { continue; }
		$rows = preg_split( '/\r?\n/', trim( (string) wp_remote_retrieve_body( $resp ) ) );
		$p = null;
		foreach ( $rows as $ln ) { $ln = trim( $ln ); if ( '' === $ln || '#' === $ln[0] ) { continue; } $p = preg_split( '/\s+/', $ln ); break; }
		if ( ! $p || count( $p ) < 12 ) { continue; }
		if ( 'MM' === $p[8] ) { continue; }                 // WVHT missing → no wave sensor / no data
		$wvht = (float) $p[8];                                // metres
		$obs  = gmmktime( (int) $p[3], (int) $p[4], 0, (int) $p[1], (int) $p[2], (int) $p[0] );
		$age  = ( time() - $obs ) / 60;
		if ( $age > 180 ) { continue; }                       // stale
		$out = array(
			'ok'      => true,
			'ft'      => round( $wvht * 3.28084, 1 ),
			'dir'     => ( 'MM' === $p[11] ) ? null : (float) $p[11],
			'dpd'     => ( 'MM' === $p[9] )  ? null : (float) $p[9],
			'ageMin'  => (int) round( $age ),
			'station' => $st[1],
			'id'      => $st[0],
		);
		break;
	}
	// Cache even an offline (ok:false) result so we don't hammer NDBC while the
	// seasonal LIS buoys are hauled out.
	set_transient( 'oyc_buoy_obs', $out, 20 * MINUTE_IN_SECONDS );
	wp_send_json( $out );
}

/* ── All live LIS buoys as an array (for the Wind page's Wave tab markers) ──
   Same NDBC source as oyc_buoy, but returns every station currently reporting
   waves, each with coordinates, instead of just the first live one. ── */
add_action( 'wp_ajax_oyc_buoys',        'oyc_buoys_proxy' );
add_action( 'wp_ajax_nopriv_oyc_buoys', 'oyc_buoys_proxy' );
function oyc_buoys_proxy() {
	$cached = get_transient( 'oyc_buoys_obs' );
	if ( false !== $cached ) { wp_send_json( $cached ); }

	$stations = array(
		array( '44022', 'Execution Rocks',  40.883, -73.728 ),
		array( '44040', 'Western LI Sound', 40.956, -73.580 ),
	);
	$out = array();
	foreach ( $stations as $st ) {
		$resp = wp_remote_get( 'https://www.ndbc.noaa.gov/data/realtime2/' . $st[0] . '.txt', array( 'timeout' => 6 ) );
		if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) { continue; }
		$rows = preg_split( '/\r?\n/', trim( (string) wp_remote_retrieve_body( $resp ) ) );
		$p = null;
		foreach ( $rows as $ln ) { $ln = trim( $ln ); if ( '' === $ln || '#' === $ln[0] ) { continue; } $p = preg_split( '/\s+/', $ln ); break; }
		if ( ! $p || count( $p ) < 12 || 'MM' === $p[8] ) { continue; }
		$obs = gmmktime( (int) $p[3], (int) $p[4], 0, (int) $p[1], (int) $p[2], (int) $p[0] );
		$age = ( time() - $obs ) / 60;
		if ( $age > 180 ) { continue; }
		$out[] = array(
			'id' => $st[0], 'station' => $st[1], 'lat' => $st[2], 'lon' => $st[3],
			'ft' => round( (float) $p[8] * 3.28084, 1 ),
			'dir' => ( 'MM' === $p[11] ) ? null : (float) $p[11],
			'dpd' => ( 'MM' === $p[9] ) ? null : (float) $p[9],
			'ageMin' => (int) round( $age ),
		);
	}
	set_transient( 'oyc_buoys_obs', $out, 20 * MINUTE_IN_SECONDS );
	wp_send_json( $out );
}

/* ── Marine Forecast data proxy (Open-Meteo multi-model wx + marine waves) ──
   Fetched server-side and cached in a transient so a public page load never
   calls Open-Meteo per-visitor — that tripped Open-Meteo's per-IP 429 rate
   limit once the /weather/ board also embedded the wind map (which hits
   Open-Meteo too). One shared fetch per ~15 min for the whole site.
   Endpoint: admin-ajax.php?action=oyc_marine_fc ── */
add_action( 'wp_ajax_oyc_marine_fc',        'oyc_marine_fc_proxy' );
add_action( 'wp_ajax_nopriv_oyc_marine_fc', 'oyc_marine_fc_proxy' );
function oyc_marine_fc_proxy() {
	$cached = get_transient( 'oyc_marine_fc_b42' );
	if ( false !== $cached ) { wp_send_json( $cached ); }

	$lat = '40.93717'; $lon = '-73.70217'; // Buoy 42 (matches the client constants)
	$wx_url = 'https://api.open-meteo.com/v1/forecast?latitude=' . $lat . '&longitude=' . $lon
		. '&hourly=wind_speed_10m,wind_gusts_10m,wind_direction_10m,temperature_2m,precipitation_probability,cloud_cover,weather_code,pressure_msl'
		. '&models=best_match,gfs_seamless,ecmwf_ifs025,icon_seamless,gem_seamless'
		. '&wind_speed_unit=kn&temperature_unit=fahrenheit&forecast_days=7&timezone=America%2FNew_York';
	$mar_url = 'https://marine-api.open-meteo.com/v1/marine?latitude=' . $lat . '&longitude=' . $lon
		. '&hourly=wave_height,wave_period,wave_direction&length_unit=imperial&forecast_days=7&timezone=America%2FNew_York';

	$args = array( 'timeout' => 12, 'headers' => array( 'User-Agent' => 'OYC-Weather/1.0' ) );

	$wx = null;
	$r  = wp_remote_get( $wx_url, $args );
	if ( ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r ) ) {
		$wx = json_decode( (string) wp_remote_retrieve_body( $r ), true );
	}
	if ( ! is_array( $wx ) || empty( $wx['hourly'] ) ) {
		// Cache a miss only briefly so the next visitor retries (don't hammer, don't hide for long).
		$out = array( 'ok' => false, 'err' => 'wx unavailable' );
		set_transient( 'oyc_marine_fc_b42', $out, 2 * MINUTE_IN_SECONDS );
		wp_send_json( $out );
	}

	$mar = null; // waves are optional — the table still renders without them
	$rm  = wp_remote_get( $mar_url, $args );
	if ( ! is_wp_error( $rm ) && 200 === (int) wp_remote_retrieve_response_code( $rm ) ) {
		$mar = json_decode( (string) wp_remote_retrieve_body( $rm ), true );
		if ( ! is_array( $mar ) ) { $mar = null; }
	}

	// Cache the full reading 15 min; but if the marine/wave fetch failed, keep it
	// short (3 min) so waves recover quickly instead of showing "—" for 15 min.
	$out = array( 'ok' => true, 'wx' => $wx, 'marine' => $mar );
	set_transient( 'oyc_marine_fc_b42', $out, ( null === $mar ? 3 : 15 ) * MINUTE_IN_SECONDS );
	wp_send_json( $out );
}

/* ── Point forecast at an arbitrary lat/lon (for the map's passage pins) ──
   Same exact-point / hourly / BLEND method as the ER board, so a pin reads
   accurate forecast values at that spot (a pin on Execution Rock equals the
   headline). Cached per rounded point so repeat pins are free. Single-point
   BLEND uses the plain (unsuffixed) hourly keys.
   Endpoint: admin-ajax.php?action=oyc_point_fc&lat=..&lon=.. ── */
add_action( 'wp_ajax_oyc_point_fc',        'oyc_point_fc_proxy' );
add_action( 'wp_ajax_nopriv_oyc_point_fc', 'oyc_point_fc_proxy' );
function oyc_point_fc_proxy() {
	$lat = isset( $_GET['lat'] ) ? (float) $_GET['lat'] : 999;
	$lon = isset( $_GET['lon'] ) ? (float) $_GET['lon'] : 999;
	// Only within the map's data extent (Atlantic basin box) — bounds the fetch & cache.
	if ( $lat > 63 || $lat < -27 || $lon > 18 || $lon < -102 ) { oyc_send_raw_json( '{"ok":false}' ); }
	$rlat = round( $lat, 2 ); $rlon = round( $lon, 2 ); // ~1 km cache granularity
	$key  = 'oyc_ptfc_' . md5( $rlat . '_' . $rlon );
	$cached = get_transient( $key );
	if ( false !== $cached ) { oyc_send_raw_json( $cached ); }

	$args   = array( 'timeout' => 12, 'headers' => array( 'User-Agent' => 'OYC-Weather/1.0' ) );
	$wx_url = 'https://api.open-meteo.com/v1/forecast?latitude=' . $rlat . '&longitude=' . $rlon
		. '&hourly=wind_speed_10m,wind_gusts_10m,wind_direction_10m,pressure_msl,temperature_2m,precipitation_probability'
		. '&wind_speed_unit=kn&temperature_unit=fahrenheit&forecast_days=7&timezone=America%2FNew_York';
	$mar_url = 'https://marine-api.open-meteo.com/v1/marine?latitude=' . $rlat . '&longitude=' . $rlon
		. '&hourly=wave_height&length_unit=imperial&forecast_days=7&timezone=America%2FNew_York';

	$wx = null;
	$r  = wp_remote_get( $wx_url, $args );
	if ( ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r ) ) {
		$wx = json_decode( (string) wp_remote_retrieve_body( $r ), true );
	}
	if ( ! is_array( $wx ) || empty( $wx['hourly'] ) ) { oyc_send_raw_json( '{"ok":false}' ); } // don't cache a miss

	$mar = null;
	$rm  = wp_remote_get( $mar_url, $args );
	if ( ! is_wp_error( $rm ) && 200 === (int) wp_remote_retrieve_response_code( $rm ) ) {
		$mar = json_decode( (string) wp_remote_retrieve_body( $rm ), true );
		if ( ! is_array( $mar ) ) { $mar = null; }
	}
	$out = wp_json_encode( array( 'ok' => true, 'wx' => $wx, 'marine' => $mar ) );
	set_transient( $key, $out, 15 * MINUTE_IN_SECONDS );
	oyc_send_raw_json( $out );
}

/* ── Markup + scoped styles + widget script ──────────────────────────────── */
function oyc_forecast_table_html() {
	$ajax  = esc_url( admin_url( 'admin-ajax.php' ) );
	$inner = <<<'HTML'
<div class="oyc-ft-card">
  <div class="hd"><div class="ttl">Marine Forecast</div><span class="loc">Buoy 42 &middot; hourly, 7 days &middot; knots / ft / &deg;F</span><span class="upd" id="oycft-upd"></span><button type="button" class="fsbtn" id="oycftFs" title="Full screen" aria-label="Full screen"></button></div>
  <div class="models" id="oycft-models"></div>
  <div class="obs" id="oycft-obs" hidden></div>
  <div class="scroll" id="oycft-scroll"><div id="oycft-tbl" class="msg">Loading forecast&hellip;</div></div>
  <div class="nav" id="oycft-nav">
    <div class="navwrap" id="oycft-navwrap"><div class="ribbon" id="oycft-ribbon"></div><div class="navwin" id="oycft-navwin"></div></div>
    <div class="navdays" id="oycft-navdays"></div>
  </div>
  <div class="legend" id="oycft-legend"></div>
  <div class="foot">Wind/sky &amp; wave forecast from Open-Meteo; observed &ldquo;now&rdquo; waves from NDBC buoy 44022 (&rarr; 44040) when live.</div>
</div>
<style>
.oyc-ft{margin:0 0 20px}
.oyc-ft *{box-sizing:border-box}
.oyc-ft .oyc-ft-card{background:#fff;border:1px solid #dfe7f0;border-radius:14px;box-shadow:0 8px 26px rgba(4,16,30,.32);overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#1e2a38;font-variant-numeric:tabular-nums}
.oyc-ft .hd{display:flex;flex-wrap:wrap;align-items:baseline;gap:6px 14px;padding:15px 18px 8px}
.oyc-ft .hd .ttl{font-size:1.1rem;color:#0b2a4a;font-weight:700}
.oyc-ft .hd .loc{font-size:.83rem;color:#6b7280}
.oyc-ft .hd .upd{margin-left:auto;font-size:.72rem;color:#6b7280}
.oyc-ft .fsbtn{flex:none;align-self:center;margin-left:10px;display:inline-flex;align-items:center;gap:6px;padding:6px 13px;border:1px solid #0b2a4a;background:#0b2a4a;color:#fff;border-radius:999px;cursor:pointer;font-size:.74rem;font-weight:700;letter-spacing:.02em;line-height:1}
.oyc-ft .fsbtn:hover{background:#1583cf;border-color:#1583cf}
.oyc-ft .fsbtn svg{display:block}
/* full-screen (native Fullscreen API, or the .isfull class as a CSS fallback) */
.oyc-ft .oyc-ft-card.isfull{position:fixed;inset:0;z-index:99999;margin:0;border:0;border-radius:0;box-shadow:none;display:flex;flex-direction:column;background:#fff}
.oyc-ft .oyc-ft-card.isfull .scroll{flex:1 1 auto;min-height:0}
.oyc-ft .models{display:flex;flex-wrap:wrap;gap:6px;padding:6px 18px 10px}
.oyc-ft .models button{border:1px solid #dfe7f0;background:#fff;font:inherit;font-size:.74rem;font-weight:600;color:#6b7280;padding:5px 11px;border-radius:999px;cursor:pointer;transition:.15s;letter-spacing:0}
.oyc-ft .models button:hover{border-color:#1583cf}
.oyc-ft .models button.on{background:#1583cf;border-color:#1583cf;color:#fff}
.oyc-ft .models button.blend.on{background:#0b2a4a;border-color:#0b2a4a}
.oyc-ft .obs{display:flex;align-items:center;gap:7px;padding:0 18px 10px;font-size:.79rem;color:#0b2a4a}
.oyc-ft .obs .dot{width:9px;height:9px;border-radius:50%;background:#1583cf;box-shadow:0 0 0 3px rgba(21,131,207,.22);flex:none}
.oyc-ft .scroll{overflow-x:auto;-webkit-overflow-scrolling:touch}
.oyc-ft table{border-collapse:collapse;width:auto}
.oyc-ft th.rl{position:sticky;left:0;z-index:3;background:#0b2a4a;color:#fff;text-align:left;font-size:.71rem;font-weight:700;padding:0 9px;white-space:nowrap;border-bottom:1px solid rgba(255,255,255,.12);min-width:64px}
.oyc-ft td,.oyc-ft th{height:33px}
.oyc-ft td{min-width:44px;width:44px;text-align:center;font-size:.78rem;border-right:1px solid rgba(255,255,255,.6);padding:0}
.oyc-ft tr.dayh td{background:#f7f3ea;color:#0b2a4a;font-weight:700;font-size:.77rem;border-bottom:1px solid #dfe7f0;text-align:left;padding-left:8px}
.oyc-ft tr.dayh td.rl{background:#0b2a4a}
.oyc-ft tr.hr td{background:#f5f9fd;color:#6b7280;font-size:.7rem;font-weight:600;border-bottom:1px solid #dfe7f0}
.oyc-ft tr.wind .v,.oyc-ft tr.wave .v{font-weight:700}
.oyc-ft tr.wind .ar,.oyc-ft tr.wave .ar{display:block;line-height:0;margin:0 auto}
.oyc-ft tr.sky td{background:#fff}
.oyc-ft tr.gust td{font-weight:600}
.oyc-ft td.divmid{box-shadow:inset 2px 0 0 #0b2a4a}
.oyc-ft tr.wave td.obscell{box-shadow:inset 0 0 0 2px #0b2a4a}
.oyc-ft .rl-ico{opacity:.9;margin-right:5px;vertical-align:-2px}
.oyc-ft .nav{padding:8px 18px 4px}
.oyc-ft .navwrap{position:relative;border:1px solid #dfe7f0;border-radius:7px;overflow:hidden;cursor:pointer;background:#fff}
.oyc-ft .ribbon{display:flex;height:30px}
.oyc-ft .ribbon i{flex:1 0 auto}
.oyc-ft .navwin{position:absolute;top:0;bottom:0;border:2px solid #0b2a4a;background:rgba(11,42,74,.10);border-radius:6px;pointer-events:none}
.oyc-ft .navdays{display:flex;font-size:.64rem;color:#6b7280;padding-top:2px}
.oyc-ft .navdays span{flex:1;text-align:center;border-left:1px solid #dfe7f0}
.oyc-ft .navdays span:first-child{border-left:0}
.oyc-ft .legend{display:flex;flex-wrap:wrap;gap:5px 13px;align-items:center;padding:10px 18px 4px;font-size:.72rem;color:#6b7280}
.oyc-ft .legend .sw{display:inline-flex;align-items:center;gap:5px}
.oyc-ft .legend .sw i{width:15px;height:12px;border-radius:3px;display:inline-block}
.oyc-ft .foot{padding:8px 18px 16px;font-size:.72rem;color:#6b7280}
.oyc-ft .msg{padding:40px 18px;text-align:center;color:#6b7280}
</style>
<script>
(function(){
  var ROOT=document.currentScript&&document.currentScript.closest?document.currentScript.closest('.oyc-ft'):document.querySelector('.oyc-ft');
  if(!ROOT)ROOT=document.querySelector('.oyc-ft');
  var AJAX=ROOT.getAttribute('data-ajax');
  var LAT=40.93717, LON=-73.70217;
  var MODELS=[{id:'best_match',label:'BLEND',blend:1},{id:'gfs_seamless',label:'GFS'},{id:'ecmwf_ifs025',label:'ECMWF'},{id:'icon_seamless',label:'ICON'},{id:'gem_seamless',label:'GEM'}];
  var DIRS=['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
  var card=function(d){return DIRS[Math.round((d%360)/22.5)%16];};
  var MODEL='best_match', DATA=null, WAVE={}, NOW=0, BUOY=null;
  var $=function(id){return document.getElementById(id);};

  function windCell(kt){var b=[[5,'#e9f1f7','#0b2a4a'],[8,'#c2ddec','#0b2a4a'],[11,'#8fc0dd','#0b2a4a'],[14,'#5aa6d0','#0b2a4a'],[17,'#d9c07a','#0b2a4a'],[20,'#e0a13f','#3a2600'],[24,'#dd7f3a','#fff'],[28,'#cf5638','#fff'],[34,'#b23a2a','#fff'],[999,'#8f2d20','#fff']];for(var i=0;i<b.length;i++)if(kt<b[i][0])return{bg:b[i][1],fg:b[i][2]};}
  function tempCell(f){var b=[[38,'#9cc6e0','#0b2a4a'],[48,'#c2ddec','#0b2a4a'],[58,'#eef3f6','#0b2a4a'],[66,'#f0e6c8','#3a2600'],[74,'#e8c877','#3a2600'],[82,'#e2a552','#3a2600'],[999,'#dd8a3f','#fff']];for(var i=0;i<b.length;i++)if(f<b[i][0])return{bg:b[i][1],fg:b[i][2]};}
  function waveCell(ft){var b=[[0.5,'#eef5fa','#0b2a4a'],[1,'#dbeaf5','#0b2a4a'],[1.5,'#bcd9ec','#0b2a4a'],[2.5,'#8fc0dd','#0b2a4a'],[4,'#5aa6d0','#fff'],[6,'#3f8bc0','#fff'],[999,'#2e6f9e','#fff']];for(var i=0;i<b.length;i++)if(ft<b[i][0])return{bg:b[i][1],fg:b[i][2]};}
  function precipCell(p){var a=Math.min(.9,p/100*.9);return{bg:'rgba(21,131,207,'+a+')',fg:p>=50?'#fff':'#0b2a4a'};}
  function cloudCell(c){var a=Math.min(.55,c/100*.55);return{bg:'rgba(72,94,116,'+a+')',fg:c>=70?'#fff':'#334'};}
  function presCell(v){return{bg:v<1010?'rgba(224,161,63,.14)':(v>1020?'rgba(21,131,207,.14)':'#f3f6f9'),fg:'#0b2a4a'};}
  function arrow(dir,fg){return '<svg class="ar" width="15" height="15" viewBox="-8 -8 16 16"><path transform="rotate('+((dir+180)%360)+')" d="M0,-6 L3.6,5 L0,2 L-3.6,5 Z" fill="'+fg+'"/></svg>';}
  function sky(code,pp,cc){
    var S='<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0b2a4a" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">';
    var sun='<circle cx="12" cy="12" r="4.2" fill="#e0a13f" stroke="#c9862b"/><g stroke="#c9862b"><line x1="12" y1="3" x2="12" y2="5.3"/><line x1="12" y1="18.7" x2="12" y2="21"/><line x1="3" y1="12" x2="5.3" y2="12"/><line x1="18.7" y1="12" x2="21" y2="12"/><line x1="5.6" y1="5.6" x2="7.2" y2="7.2"/><line x1="16.8" y1="16.8" x2="18.4" y2="18.4"/><line x1="16.8" y1="7.2" x2="18.4" y2="5.6"/><line x1="5.6" y1="18.4" x2="7.2" y2="16.8"/></g>';
    var cloud='<path d="M7 18h9a3.4 3.4 0 0 0 .3-6.78A5 5 0 0 0 7 12.2 3.4 3.4 0 0 0 7 18z" fill="#dbe4ec" stroke="#9fb0c2"/>';
    var rain='<g stroke="#1583cf" stroke-width="1.9"><line x1="9" y1="19.5" x2="8" y2="22"/><line x1="13" y1="19.5" x2="12" y2="22"/><line x1="17" y1="19.5" x2="16" y2="22"/></g>';
    if(pp>=45)return S+cloud+rain+'</svg>';
    if(pp>=20)return S+'<g opacity=".9" transform="translate(-2,-2) scale(.82)">'+sun+'</g>'+cloud+'<line x1="12" y1="19.6" x2="11.2" y2="21.4" stroke="#1583cf" stroke-width="1.8"/></svg>';
    if(cc>=80)return S+cloud+'</svg>';
    if(cc>=40)return S+'<g transform="translate(-3,-3) scale(.8)">'+sun+'</g>'+cloud+'</svg>';
    return S+sun+'</svg>';
  }
  function ic(t){var c='#cfe0ee';var w='<svg class="rl-ico" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="'+c+'" stroke-width="2">';
    if(t==='wind')return w+'<path d="M3 8h11a3 3 0 1 0-3-3M3 16h15a3 3 0 1 1-3 3"/></svg>';
    if(t==='gust')return w+'<path d="M3 10h9a2.5 2.5 0 1 0-2.5-2.5M3 15h13a2.5 2.5 0 1 1-2.5 2.5"/></svg>';
    if(t==='temp')return w+'<path d="M12 3v10a3 3 0 1 0 2 0V3z"/></svg>';
    if(t==='rain')return w+'<path d="M8 18l-1 2M12 18l-1 2M16 18l-1 2M6 15a4 4 0 0 1 1-7.5A5 5 0 0 1 17 9a3.5 3.5 0 0 1 0 6"/></svg>';
    if(t==='wave')return w+'<path d="M2 12c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2 2-2 4-2M2 17c2 0 2-2 4-2s2 2 4 2 2-2 4-2 2 2 4 2 2-2 4-2"/></svg>';
    if(t==='pres')return w+'<circle cx="12" cy="12" r="8"/><path d="M12 12l4-2"/></svg>';return '';}

  function k(base,m){var kk=base+'_'+m;return DATA.hourly[kk]!==undefined?kk:base;}
  function cols(){var h=DATA.hourly,m=MODEL;return{
    time:h.time, ws:h[k('wind_speed_10m',m)], wg:h[k('wind_gusts_10m',m)], wd:h[k('wind_direction_10m',m)],
    tp:h[k('temperature_2m',m)], pr:h[k('precipitation_probability',m)]||[], cc:h[k('cloud_cover',m)],
    wc:h[k('weather_code',m)]||[], ps:h[k('pressure_msl',m)]||[]};}

  function loadBuoy(){
    if(!AJAX)return Promise.resolve(null);
    return fetch(AJAX+'?action=oyc_buoy').then(function(r){return r.json();}).then(function(o){
      return (o&&o.ok&&o.ft!=null)?{ft:o.ft,dir:o.dir,dpd:o.dpd,ageMin:o.ageMin,station:o.station,id:o.id}:null;
    }).catch(function(){return null;});
  }

  // Waves: fetch Open-Meteo Marine straight from the browser (client-side), the
  // same way the NWS cards already bypass the host. Our WP host firewalls
  // outbound to marine-api.open-meteo.com (a different server from the wind API
  // it CAN reach), so the server proxy's wave fetch comes back empty — but the
  // visitor's browser reaches marine-api fine (CORS-ok). This is the authoritative
  // hourly wave source; the server-side marine (if any) is just a secondary fill.
  var MAR_URL='https://marine-api.open-meteo.com/v1/marine?latitude=40.93717&longitude=-73.70217'
    +'&hourly=wave_height,wave_period,wave_direction&length_unit=imperial&forecast_days=7&timezone=America%2FNew_York';
  function mergeWaves(mh){
    if(!mh||!mh.time||!mh.wave_height)return 0;var n=0;
    mh.time.forEach(function(t,i){var h=mh.wave_height[i];if(h!=null){WAVE[t]={h:h,p:(mh.wave_period||[])[i],dir:(mh.wave_direction||[])[i]};n++;}});
    return n;
  }
  function loadWavesClient(){
    return fetch(MAR_URL).then(function(r){if(!r.ok)throw new Error('mar '+r.status);return r.json();})
      .then(function(j){return mergeWaves(j&&j.hourly);}).catch(function(){return 0;});
  }

  // Fetch wind/sky through the cached server proxy (admin-ajax) so visitors never
  // hit Open-Meteo directly — avoids the per-IP 429 rate limit on the public board.
  fetch(AJAX+'?action=oyc_marine_fc').then(function(r){if(!r.ok)throw new Error('fc '+r.status);return r.json();}).then(function(res){
    if(!res||!res.ok||!res.wx||!res.wx.hourly)throw new Error(res&&res.err?res.err:'no data');
    var d=res.wx, mar=res.marine;
    DATA=d;
    if(mar&&mar.hourly&&mar.hourly.wave_height){var mh=mar.hourly;mh.time.forEach(function(t,i){WAVE[t]={h:mh.wave_height[i],p:(mh.wave_period||[])[i],dir:(mh.wave_direction||[])[i]};});}
    var off=(d.utc_offset_seconds||0)*1000,now=Date.now(),bi=0,bd=1e15;
    d.hourly.time.forEach(function(t,i){var u=Date.parse(t+':00Z')-off;if(Math.abs(u-now)<bd){bd=Math.abs(u-now);bi=i;}});NOW=bi;
    $('oycft-upd').textContent='Updated '+new Date(now).toLocaleString('en-US',{hour:'numeric',minute:'2-digit',month:'short',day:'numeric'});
    buildModels();buildLegend();render();
    loadWavesClient().then(function(n){if(n)render();}); // client-side waves (host can't reach marine-api)
    loadBuoy().then(function(b){BUOY=b;render();});
    $('oycft-scroll').addEventListener('scroll',syncNav);
    window.addEventListener('resize',function(){clearTimeout(window._oycft);window._oycft=setTimeout(function(){renderRibbon();syncNav();},150);});
  }).catch(function(e){$('oycft-tbl').textContent='Could not load forecast ('+e+').';});

  function buildModels(){var box=$('oycft-models');MODELS.forEach(function(m){var b=document.createElement('button');b.textContent=m.label;b.setAttribute('data-id',m.id);if(m.blend)b.className='blend';if(m.id===MODEL)b.className+=' on';b.onclick=function(){MODEL=m.id;[].forEach.call(box.children,function(c){c.classList.toggle('on',c.getAttribute('data-id')===MODEL);});render();};box.appendChild(b);});}
  function buildLegend(){var bins=[['<8','#c2ddec'],['8-11','#8fc0dd'],['11-14','#5aa6d0'],['14-17','#d9c07a'],['17-20','#e0a13f'],['20-24','#dd7f3a'],['24-28','#cf5638'],['28+','#b23a2a']];
    $('oycft-legend').innerHTML='<b style="color:#0b2a4a">Wind kt</b>&nbsp;'+bins.map(function(x){return '<span class="sw"><i style="background:'+x[1]+'"></i>'+x[0]+'</span>';}).join('');}

  function render(){
    var c=cols(),s=NOW,e=c.time.length-1,idx=[],i;for(i=s;i<=e;i++)idx.push(i);
    $('oycft-tbl').className='';
    var days={};idx.forEach(function(i){var dk=c.time[i].slice(0,10);(days[dk]=days[dk]||[]).push(i);});
    var day='<tr class="dayh"><td class="rl"></td>',di=0;Object.keys(days).forEach(function(dk){var dt=new Date(dk+'T12:00');day+='<td colspan="'+days[dk].length+'"'+(di>0?' class="divmid"':'')+'>'+dt.toLocaleDateString('en-US',{weekday:'short',month:'short',day:'numeric'})+'</td>';di++;});day+='</tr>';
    var dv=function(i){return (new Date(c.time[i]).getHours()===0&&i!==s)?' class="divmid"':'';};
    var rl=function(icn,t){return '<th class="rl">'+icn+t+'</th>';};
    var hr='<tr class="hr">'+rl('','Hour');idx.forEach(function(i){var H=new Date(c.time[i]).getHours();hr+='<td'+dv(i)+'>'+(H%12||12)+(H<12?'a':'p')+'</td>';});hr+='</tr>';
    var wind='<tr class="wind">'+rl(ic('wind'),'Wind');idx.forEach(function(i){var cc=windCell(c.ws[i]);wind+='<td style="background:'+cc.bg+';color:'+cc.fg+'"'+dv(i)+'>'+arrow(c.wd[i],cc.fg)+'<span class="v">'+Math.round(c.ws[i])+'</span></td>';});wind+='</tr>';
    var gust='<tr class="gust">'+rl(ic('gust'),'Gust');idx.forEach(function(i){var cc=windCell(c.wg[i]);gust+='<td style="background:'+cc.bg+';color:'+cc.fg+';opacity:.92"'+dv(i)+'>'+Math.round(c.wg[i])+'</td>';});gust+='</tr>';
    var skyR='<tr class="sky">'+rl('','Sky');idx.forEach(function(i){skyR+='<td'+dv(i)+'>'+sky(c.wc[i]||0,c.pr[i]||0,c.cc[i]||0)+'</td>';});skyR+='</tr>';
    var temp='<tr class="temp">'+rl(ic('temp'),'°F');idx.forEach(function(i){var cc=tempCell(c.tp[i]);temp+='<td style="background:'+cc.bg+';color:'+cc.fg+'"'+dv(i)+'>'+Math.round(c.tp[i])+'</td>';});temp+='</tr>';
    var pre='<tr class="pr">'+rl(ic('rain'),'Precip');idx.forEach(function(i){var p=c.pr[i]||0,cc=precipCell(p);pre+='<td style="background:'+cc.bg+';color:'+cc.fg+'"'+dv(i)+'>'+p+'</td>';});pre+='</tr>';
    var wave='<tr class="wave">'+rl(ic('wave'),'Waves');idx.forEach(function(i,col){
      var obs=(col===0&&BUOY&&BUOY.ft!=null);var wv=WAVE[c.time[i]];
      var h=obs?BUOY.ft:(wv&&wv.h!=null?wv.h:null);
      if(h==null){wave+='<td'+dv(i)+' style="color:#6b7280">–</td>';return;}
      var cc=waveCell(h),dir=obs?(BUOY.dir||0):(wv?(wv.dir||0):0);
      var cls=obs?' class="obscell"':dv(i);
      var tip=obs?('observed'+(BUOY.dpd?' · '+BUOY.dpd.toFixed(0)+'s':'')):(wv&&wv.p?wv.p.toFixed(0)+'s':'');
      wave+='<td'+cls+' style="background:'+cc.bg+';color:'+cc.fg+'" title="'+tip+'">'+arrow(dir,cc.fg)+'<span class="v">'+h.toFixed(1)+'</span></td>';
    });wave+='</tr>';
    var cl='<tr class="cl">'+rl('','Cloud');idx.forEach(function(i){var v=c.cc[i]||0,cc=cloudCell(v);cl+='<td style="background:'+cc.bg+';color:'+cc.fg+'"'+dv(i)+'>'+v+'</td>';});cl+='</tr>';
    var ps='<tr class="ps">'+rl(ic('pres'),'Pres');idx.forEach(function(i){var v=Math.round(c.ps[i]||0),cc=presCell(v);ps+='<td style="background:'+cc.bg+';color:'+cc.fg+';font-size:.66rem"'+dv(i)+'>'+v+'</td>';});ps+='</tr>';
    $('oycft-tbl').innerHTML='<table>'+day+hr+temp+wind+gust+skyR+pre+wave+cl+ps+'</table>';
    updateObs();renderRibbon();syncNav();
  }
  function updateObs(){var el=$('oycft-obs');if(!BUOY){el.hidden=true;return;}el.hidden=false;
    el.innerHTML='<span class="dot"></span>Observed now: <b style="margin:0 3px">'+BUOY.ft.toFixed(1)+' ft</b>'+(BUOY.dpd?' @ '+BUOY.dpd.toFixed(0)+'s':'')+(BUOY.dir!=null?' from '+card(BUOY.dir):'')+' — '+BUOY.station+' buoy · '+Math.round(BUOY.ageMin)+' min ago';}
  function renderRibbon(){
    if(!DATA)return;var c=cols(),s=NOW,e=c.time.length-1,rib=$('oycft-ribbon'),html='',i;
    for(i=s;i<=e;i++){html+='<i style="background:'+windCell(c.ws[i]).bg+'"></i>';}
    rib.innerHTML=html;
    var dks=[];for(i=s;i<=e;i++){var dk=c.time[i].slice(0,10);if(dks.indexOf(dk)<0)dks.push(dk);}
    $('oycft-navdays').innerHTML=dks.map(function(dk){var dt=new Date(dk+'T12:00');return '<span>'+dt.toLocaleDateString('en-US',{weekday:'short'})+'</span>';}).join('');
    var nw=$('oycft-navwrap');nw.onclick=function(ev){var r=nw.getBoundingClientRect(),f=(ev.clientX-r.left)/r.width,sc=$('oycft-scroll');sc.scrollLeft=f*sc.scrollWidth-sc.clientWidth/2;};
  }
  function syncNav(){var sc=$('oycft-scroll'),win=$('oycft-navwin'),wrap=$('oycft-navwrap'),tot=sc.scrollWidth||1;win.style.left=(sc.scrollLeft/tot*wrap.clientWidth)+'px';win.style.width=(sc.clientWidth/tot*wrap.clientWidth)+'px';}

  /* ---------- expand / collapse (CSS maximise that fills the window) ----------
     Not the native Fullscreen API — see the note in the map component; the .isfull
     overlay keeps the themed card readable. */
  (function(){
    var fsEl=document.querySelector('.oyc-ft .oyc-ft-card'),btn=$('oycftFs');
    if(!fsEl||!btn)return;
    var EXP='<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M16 21h3a2 2 0 0 0 2-2v-3"/></svg><span>Expand</span>',
        COL='<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8h3a2 2 0 0 0 2-2V3M16 3v3a2 2 0 0 0 2 2h3M3 16h3a2 2 0 0 1 2 2v3M16 21v-3a2 2 0 0 1 2-2h3"/></svg><span>Exit</span>';
    btn.innerHTML=EXP;
    function apply(on){
      fsEl.classList.toggle('isfull',on);
      document.body.style.overflow=on?'hidden':'';
      btn.innerHTML=on?COL:EXP;btn.title=on?'Exit full screen':'Full screen';btn.setAttribute('aria-label',btn.title);
      setTimeout(function(){try{renderRibbon();syncNav();}catch(e){}},90);
    }
    btn.addEventListener('click',function(){apply(!fsEl.classList.contains('isfull'));});
    document.addEventListener('keydown',function(e){if(e.key==='Escape'&&fsEl.classList.contains('isfull'))apply(false);});
  })();
})();
</script>
HTML;
	return '<section class="oyc-ft" data-ajax="' . $ajax . '">' . $inner . '</section>';
}
