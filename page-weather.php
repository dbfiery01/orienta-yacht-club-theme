<?php
/**
 * Template Name: Weather — Live Conditions
 *
 * Full-bleed dark "live harbor conditions" signage dashboard for Mamaroneck
 * Harbor, modeled on the club's Yodeck display. Publicly accessible. Renders a
 * self-contained HTML document (no site header/footer) and pulls LIVE data
 * client-side from:
 *   - NOAA CO-OPS  (tide predictions + Kings Point wind/met)
 *   - NWS api.weather.gov  (ANZ335 marine forecast + active alerts)
 *
 * Auto-renders for a Page with slug "weather" (page-{slug} hierarchy), so no
 * template assignment is needed — just create a Page titled "Weather".
 *
 * Station IDs / zone are editable constants at the top of the <script> block.
 *
 * @package Orienta_Yacht_Club
 */

nocache_headers();

// Site menu for the slim nav bar — real primary menu when assigned, else the
// same core links header.php falls back to.
$oyc_weather_menu = '';
if ( has_nav_menu( 'primary' ) ) {
	$oyc_weather_menu = wp_nav_menu( array(
		'theme_location' => 'primary',
		'container'      => false,
		'items_wrap'     => '<ul class="sitebar-menu">%3$s</ul>',
		'depth'          => 1,
		'echo'           => false,
		'fallback_cb'    => '__return_empty_string',
	) );
}
if ( ! $oyc_weather_menu ) {
	$oyc_weather_menu = '<ul class="sitebar-menu">'
		. '<li><a href="' . esc_url( home_url( '/' ) ) . '">Home</a></li>'
		. '<li><a href="' . esc_url( home_url( '/#about' ) ) . '">About</a></li>'
		. '<li><a href="' . esc_url( home_url( '/#membership' ) ) . '">Membership</a></li>'
		. '<li><a href="' . esc_url( home_url( '/#sailing' ) ) . '">Boating</a></li>'
		. '<li><a href="' . esc_url( home_url( '/#visitors' ) ) . '">Visitors</a></li>'
		. '<li><a href="' . esc_url( home_url( '/calendar/' ) ) . '">Calendar</a></li>'
		. '<li><a href="' . esc_url( home_url( '/contact/' ) ) . '">Contact</a></li>'
		. '</ul>';
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Live Conditions — Mamaroneck Harbor · Orienta Yacht Club</title>
<style>
	:root{
		/* OYC brand palette — navy ground, brass-gold primary accent, harbor-blue contrast.
		   Var names --teal/--amber kept for a minimal diff; values are now brand colors. */
		--bg1:#0b2a4a; --bg2:#04162a; --panel:rgba(11,42,74,.55); --panel2:rgba(7,32,58,.72);
		--edge:rgba(245,239,226,.12); --edge2:rgba(245,239,226,.24);
		--ink:#f5efe2; --muted:#aeb9c8; --faint:#84909f;
		--teal:#d4a851; --teal2:#b08a3e; --amber:#57a6d6; --red:#c0392b; --green:#4ade80;
	}
	*{box-sizing:border-box;margin:0;padding:0}
	html,body{height:100%}
	body{
		font-family:"Arial Narrow","Helvetica Neue",Arial,sans-serif;
		background:radial-gradient(1200px 700px at 70% -10%,#12283f 0%,var(--bg1) 45%,var(--bg2) 100%);
		color:var(--ink); min-height:100vh; padding:18px; overflow-x:hidden;
		-webkit-font-smoothing:antialiased;
	}
	.mono{font-family:ui-monospace,"SF Mono",Menlo,Consolas,monospace;font-variant-numeric:tabular-nums}
	.wrap{max-width:1600px;margin:0 auto;display:flex;flex-direction:column;gap:16px;min-height:calc(100vh - 36px)}

	/* ---- site menu bar ---- */
	.sitebar{display:flex;align-items:center;gap:8px 22px;flex-wrap:wrap;
		padding:9px 20px;border:1px solid var(--edge);border-radius:12px;background:var(--panel2)}
	.sitebar-brand{color:var(--teal);font-weight:800;letter-spacing:.12em;text-transform:uppercase;
		font-size:13px;text-decoration:none;white-space:nowrap}
	.sitebar-brand:hover{color:var(--ink)}
	.sitebar-menu{display:flex;flex-wrap:wrap;gap:4px 20px;list-style:none;margin-left:auto;padding:0}
	.sitebar-menu li{margin:0}
	.sitebar-menu a{color:var(--muted);text-decoration:none;font-size:13px;letter-spacing:.05em;
		font-weight:600;transition:color .15s}
	.sitebar-menu a:hover{color:var(--teal)}
	@media (max-width:700px){ .sitebar{padding:9px 14px} .sitebar-menu{gap:4px 14px;margin-left:0} }

	/* ---- top bar ---- */
	.topbar{display:flex;align-items:center;justify-content:space-between;gap:20px;
		padding:14px 20px;border:1px solid var(--edge);border-radius:16px;background:var(--panel2);flex-wrap:wrap}
	.brand{display:flex;align-items:baseline;gap:14px;flex-wrap:wrap}
	.brand h1{font-weight:800;letter-spacing:.06em;font-size:clamp(20px,2.4vw,34px);text-transform:uppercase;
		background:linear-gradient(180deg,#fff,#bfe4f5);-webkit-background-clip:text;background-clip:text;color:transparent}
	.brand .sub{color:var(--teal);letter-spacing:.22em;font-size:12px;text-transform:uppercase;font-weight:700}
	.clockwrap{text-align:right;display:flex;flex-direction:column;align-items:flex-end;gap:2px}
	.updated{color:var(--muted);font-size:12px;letter-spacing:.04em;display:flex;align-items:center;gap:7px}
	.dot{width:8px;height:8px;border-radius:50%;background:var(--faint);box-shadow:0 0 0 0 rgba(74,222,128,.5)}
	.dot.ok{background:var(--green);animation:pulse 2.4s infinite}
	@keyframes pulse{0%{box-shadow:0 0 0 0 rgba(74,222,128,.45)}70%{box-shadow:0 0 0 7px rgba(74,222,128,0)}100%{box-shadow:0 0 0 0 rgba(74,222,128,0)}}
	.clock{font-size:clamp(26px,3.4vw,44px);font-weight:700;line-height:1;letter-spacing:.02em}
	.clock .ap{font-size:.5em;color:var(--teal);margin-left:.25em;font-weight:700}
	.datestr{color:var(--muted);font-size:12px;letter-spacing:.18em;text-transform:uppercase}

	/* ---- grid ---- */
	.grid{display:grid;grid-template-columns:minmax(260px,1fr) minmax(420px,1.9fr) minmax(280px,1.05fr);
		gap:16px;flex:1}
	.col{display:flex;flex-direction:column;gap:16px}
	.card{border:1px solid var(--edge);border-radius:16px;background:var(--panel);
		padding:16px 18px;position:relative;overflow:hidden}
	.card h2{font-size:12px;letter-spacing:.2em;text-transform:uppercase;color:var(--teal);font-weight:700;
		display:flex;justify-content:space-between;align-items:center;gap:10px}
	.card h2 .sta{color:var(--faint);font-size:10px;letter-spacing:.12em;font-weight:600}

	/* tide */
	.tide-now{display:flex;align-items:flex-end;gap:10px;margin-top:12px}
	.tide-val{font-size:clamp(46px,6vw,74px);font-weight:800;line-height:.85;color:#fff}
	.tide-unit{color:var(--muted);font-size:15px;letter-spacing:.14em;padding-bottom:10px;font-weight:700}
	.trend{margin-top:12px;display:inline-flex;align-items:center;gap:8px;font-weight:800;letter-spacing:.14em;
		text-transform:uppercase;font-size:14px}
	.trend.falling{color:var(--amber)} .trend.rising{color:var(--teal)}
	.tide-cap{color:var(--faint);font-size:11px;margin-top:10px;letter-spacing:.03em}

	/* next tides */
	.nt{display:flex;flex-direction:column;gap:2px;margin-top:12px}
	.nt-row{display:grid;grid-template-columns:22px 46px 1fr auto;align-items:center;gap:10px;
		padding:9px 0;border-top:1px solid var(--edge)}
	.nt-row:first-child{border-top:0}
	.nt-ar{font-size:15px;font-weight:800}
	.nt-lo .nt-ar{color:var(--amber)} .nt-hi .nt-ar{color:var(--teal)}
	.nt-kind{font-size:11px;letter-spacing:.16em;color:var(--muted);text-transform:uppercase;font-weight:700}
	.nt-time{font-weight:700;font-size:16px}
	.nt-ft{color:var(--muted);font-size:14px;text-align:right}

	/* sun moon */
	.sm{display:grid;grid-template-columns:1fr 1fr auto;gap:14px;margin-top:14px;align-items:center}
	.sm .it{display:flex;flex-direction:column;gap:3px}
	.sm .k{color:var(--faint);font-size:10px;letter-spacing:.14em;text-transform:uppercase}
	.sm .v{font-weight:700;font-size:17px}
	.moon{width:46px;height:46px;border-radius:50%;background:#0c1c2e;position:relative;overflow:hidden;
		border:1px solid var(--edge2);justify-self:end}
	.moon .lit{position:absolute;inset:0;background:radial-gradient(circle at 50% 40%,#eef6ff,#c7dcec);}
	.moon .shadow{position:absolute;inset:0;background:#0a1220;border-radius:50%}

	/* graph */
	.graph-card{flex:1;display:flex;flex-direction:column}
	.graph-wrap{flex:1;min-height:190px;margin-top:8px;position:relative}
	#tideOutage{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-align:center;padding:20px}
	#tideOutage .to-t{color:var(--teal);font-weight:800;letter-spacing:.18em;text-transform:uppercase;font-size:14px}
	#tideOutage .to-s{color:var(--faint);font-size:12.5px;max-width:440px;line-height:1.5}
	svg.tidegraph{width:100%;height:100%;display:block}
	.axis{fill:var(--faint);font-size:11px;font-family:ui-monospace,Menlo,monospace}
	.hilo-lbl{fill:var(--ink);font-size:12px;font-weight:700;font-family:ui-monospace,Menlo,monospace;text-anchor:middle}

	/* forecast */
	.fc{display:flex;flex-direction:column;gap:12px;margin-top:12px}
	.fc-row{display:grid;grid-template-columns:76px 1fr;gap:14px;align-items:start}
	.fc-when{color:var(--teal);font-weight:800;letter-spacing:.1em;text-transform:uppercase;font-size:13px;padding-top:2px}
	.fc-txt{color:#cfe3f2;font-size:13.5px;line-height:1.5}

	/* wind */
	.wind-body{display:flex;align-items:center;gap:16px;margin-top:12px}
	.dial{width:120px;height:120px;flex:none;position:relative}
	.dial svg{width:100%;height:100%;transform:rotate(0deg)}
	.wind-read{display:flex;flex-direction:column}
	.wind-spd{font-size:52px;font-weight:800;line-height:.9;color:#fff}
	.wind-lab{display:flex;gap:18px;margin-top:8px}
	.wind-lab .k{color:var(--faint);font-size:10px;letter-spacing:.14em;text-transform:uppercase}
	.wind-lab .v{font-weight:700;font-size:15px}
	.wind-arr-ico{display:inline-block;margin-right:6px;color:var(--teal);transition:transform .5s ease}

	/* waves */
	.wave-body{display:flex;align-items:center;gap:20px;margin-top:12px;flex-wrap:wrap}
	.wave-main{display:flex;align-items:flex-end;gap:8px}
	.wave-val{font-size:44px;font-weight:800;line-height:.9;color:#fff}
	.wave-unit{color:var(--muted);font-size:13px;letter-spacing:.14em;text-transform:uppercase;padding-bottom:6px;font-weight:700}
	.wave-lab{display:flex;gap:18px}
	.wave-lab .k{color:var(--faint);font-size:10px;letter-spacing:.14em;text-transform:uppercase}
	.wave-lab .v{font-weight:700;font-size:15px}
	.wave-cap{color:var(--faint);font-size:11px;margin-top:12px;letter-spacing:.03em}

	/* conditions */
	.cond{display:grid;grid-template-columns:1fr 1fr;gap:1px;margin-top:12px;
		background:var(--edge);border:1px solid var(--edge);border-radius:12px;overflow:hidden}
	.cond .cell{background:var(--panel);padding:14px 14px}
	.cond .cell.full{grid-column:1/-1}
	.cond .v{font-size:24px;font-weight:800;color:#fff}
	.cond .k{color:var(--faint);font-size:10px;letter-spacing:.14em;text-transform:uppercase;margin-top:4px}

	/* alert */
	.alertbar{display:flex;align-items:stretch;gap:0;border-radius:14px;overflow:hidden;
		border:1px solid rgba(232,84,74,.5);background:rgba(60,16,14,.5)}
	.alertbar.hidden{display:none}
	.alert-tag{background:var(--red);color:#fff;font-weight:800;letter-spacing:.12em;text-transform:uppercase;
		font-size:13px;display:flex;align-items:center;gap:8px;padding:0 18px;white-space:nowrap}
	.marquee{flex:1;overflow:hidden;position:relative;display:flex;align-items:center}
	.marquee span{display:inline-block;white-space:nowrap;padding-left:100%;color:#ffd9d4;font-size:14px;
		font-weight:600;letter-spacing:.02em;animation:scroll 34s linear infinite}
	@keyframes scroll{from{transform:translateX(0)}to{transform:translateX(-100%)}}

	.miss{color:var(--faint)}
	@media (max-width:1100px){ .grid{grid-template-columns:1fr}}
	@media (max-width:560px){ .topbar{padding:12px 14px} .card{padding:14px} }
</style>
</head>
<body>

<div class="wrap">
	<!-- SITE MENU -->
	<nav class="sitebar" aria-label="Site menu">
		<a class="sitebar-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">&#8962;&nbsp;OYC</a>
		<?php echo $oyc_weather_menu; ?>
	</nav>

	<!-- TOP BAR -->
	<div class="topbar">
		<div class="brand">
			<h1>Orienta Yacht Club</h1>
			<span class="sub">Mamaroneck Harbor &middot; Live Conditions</span>
		</div>
		<div class="clockwrap">
			<div class="updated"><span class="dot" id="statusDot"></span><span id="updated">Connecting&hellip;</span></div>
			<div class="clock mono"><span id="clock">--:--:--</span><span class="ap" id="ampm">--</span></div>
			<div class="datestr" id="datestr">&mdash;</div>
		</div>
	</div>

	<div class="grid">
		<!-- LEFT COLUMN -->
		<div class="col">
			<div class="card">
				<h2>Current Tide Level <span class="sta" id="tideSta">STA. 8518091</span></h2>
				<div class="tide-now"><span class="tide-val" id="tideVal">&mdash;</span><span class="tide-unit">ft MLLW</span></div>
				<div class="trend" id="tideTrend"><span id="tideTrendAr">&mdash;</span> <span id="tideTrendTxt">&mdash;</span></div>
				<div class="tide-cap">Predicted level, interpolated to the minute</div>
			</div>

			<div class="card">
				<h2>Next Tides</h2>
				<div class="nt" id="nextTides">
					<div class="nt-row"><span class="miss" style="grid-column:1/-1">Loading&hellip;</span></div>
				</div>
			</div>

			<div class="card">
				<h2>Sun &amp; Moon</h2>
				<div class="sm">
					<div class="it"><span class="k">&#9728; Sunrise</span><span class="v" id="sunrise">&mdash;</span></div>
					<div class="it"><span class="k">&#9790; Sunset</span><span class="v" id="sunset">&mdash;</span></div>
					<div class="moon" id="moon" title="Moon phase"></div>
				</div>
				<div class="sm" style="margin-top:10px;grid-template-columns:1fr auto">
					<div class="it"><span class="k">Moon phase</span><span class="v" id="moonName">&mdash;</span></div>
					<div class="it" style="text-align:right"><span class="k">Illuminated</span><span class="v" id="moonPct">&mdash;</span></div>
				</div>
			</div>
		</div>

		<!-- CENTER COLUMN -->
		<div class="col">
			<div class="card graph-card">
				<h2>48-Hour Tide Graph <span class="sta">NOAA Predictions</span></h2>
				<div class="graph-wrap"><svg class="tidegraph" id="tideGraph" viewBox="0 0 1000 320" preserveAspectRatio="none"></svg></div>
			</div>
			<div class="card">
				<h2>48-Hour Marine Forecast <span class="sta" id="fcZone">NWS Zone ANZ335</span></h2>
				<div class="fc" id="forecast"><div class="fc-row"><span class="miss">Loading forecast&hellip;</span></div></div>
			</div>
		</div>

		<!-- RIGHT COLUMN -->
		<div class="col">
			<div class="card">
				<h2>Wind <span class="sta" id="windSta">STA. 8516945</span></h2>
				<div class="wind-body">
					<div class="dial" id="windDial"></div>
					<div class="wind-read">
						<span class="wind-spd" id="windSpd">&mdash;</span>
						<div class="wind-lab">
							<div class="it"><div class="k">From</div><div class="v"><span class="wind-arr-ico" id="windArrow" style="display:none">&#10148;</span><span id="windDir">&mdash;</span></div></div>
							<div class="it"><div class="k">Gusts</div><div class="v" id="windGust">&mdash;</div></div>
						</div>
					</div>
				</div>
			</div>
			<div class="card">
				<h2>Wave Conditions <span class="sta" id="waveSta">NWS Seas &middot; Current</span></h2>
				<div class="wave-body">
					<div class="wave-main"><span class="wave-val miss" id="waveHt">&mdash;</span><span class="wave-unit">seas</span></div>
					<div class="wave-lab">
						<div class="it"><div class="k">Period</div><div class="v" id="wavePer">&mdash;</div></div>
						<div class="it"><div class="k">Direction</div><div class="v" id="waveDir">&mdash;</div></div>
					</div>
				</div>
				<div class="wave-cap" id="waveCap">Forecast seas, current period</div>
			</div>

			<div class="card" style="flex:1">
				<h2>Conditions</h2>
				<div class="cond">
					<div class="cell"><div class="v" id="airTemp">&mdash;</div><div class="k">Air Temp</div></div>
					<div class="cell"><div class="v" id="waterTemp">&mdash;</div><div class="k">Water Temp</div></div>
					<div class="cell"><div class="v" id="pressure">&mdash;</div><div class="k">Pressure</div></div>
					<div class="cell"><div class="v" id="humidity">&mdash;</div><div class="k">Humidity</div></div>
					<div class="cell full"><div class="v" id="visibility">&mdash;</div><div class="k">Visibility</div></div>
				</div>
			</div>
		</div>
	</div>

	<!-- ALERT -->
	<div class="alertbar hidden" id="alertBar">
		<div class="alert-tag">&#9888; Marine Alert</div>
		<div class="marquee"><span id="alertText"></span></div>
	</div>
</div>

<script>
(function(){
	"use strict";
	// ===== EDITABLE CONFIG — verify against your Yodeck dashboard =====
	var CFG = {
		TIDE_STATION: '8518091',   // NOAA CO-OPS tide predictions (Mamaroneck, LI Sound)
		MET_STATION:  '8516945',   // NOAA CO-OPS wind/met (Kings Point, NY — nearest reporting)
		NWS_ZONE:     'ANZ335',    // NWS marine forecast zone (LI Sound West)
		CWF_OFFICE:   'OKX',       // NWS office issuing the Coastal Waters Forecast for ANZ3xx
		LAT: 40.939, LON: -73.734  // Mamaroneck (sun/moon + land+marine alerts)
	};
	document.getElementById('tideSta').textContent = 'STA. ' + CFG.TIDE_STATION;
	document.getElementById('windSta').textContent = 'STA. ' + CFG.MET_STATION;
	document.getElementById('fcZone').textContent  = 'NWS Zone ' + CFG.NWS_ZONE;

	var $ = function(id){ return document.getElementById(id); };
	var pad = function(n){ return String(n).padStart(2,'0'); };
	var COOPS = 'https://api.tidesandcurrents.noaa.gov/api/prod/datagetter';

	function ymd(d){ return '' + d.getFullYear() + pad(d.getMonth()+1) + pad(d.getDate()); }
	function ymdhm(d){ return ymd(d) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()); }
	function hm(d){ var h=d.getHours(), ap=h>=12?'PM':'AM'; h=h%12||12; return h + ':' + pad(d.getMinutes()) + ' ' + ap.toLowerCase(); }
	function hmt(d){ var h=d.getHours(), ap=h>=12?'PM':'AM'; h=h%12||12; return h + ':' + pad(d.getMinutes()) + ' ' + ap; }

	function markUpdated(ok){
		$('statusDot').className = 'dot' + (ok ? ' ok' : '');
		if(ok){ var n=new Date(); $('updated').textContent = 'Last good update ' + hmt(n); }
	}

	// ---------- CLOCK ----------
	var DOW=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
	var MON=['January','February','March','April','May','June','July','August','September','October','November','December'];
	function tick(){
		var d=new Date(), h=d.getHours(), ap=h>=12?'PM':'AM'; h=h%12||12;
		$('clock').textContent = pad(h)+':'+pad(d.getMinutes())+':'+pad(d.getSeconds());
		$('ampm').textContent = ap;
		$('datestr').textContent = DOW[d.getDay()]+', '+MON[d.getMonth()]+' '+d.getDate();
	}
	tick(); setInterval(tick, 1000);

	// ---------- fetch helpers ----------
	function coops(product, extra){
		var p = Object.assign({ product:product, station:(product==='predictions'?CFG.TIDE_STATION:CFG.MET_STATION),
			application:'OYC-LiveConditions', time_zone:'lst_ldt', units:'english', format:'json' }, extra||{});
		var qs = Object.keys(p).map(function(k){ return k+'='+encodeURIComponent(p[k]); }).join('&');
		return fetch(COOPS+'?'+qs).then(function(r){ return r.json(); });
	}
	function nws(path){
		return fetch('https://api.weather.gov/'+path, { headers:{ 'Accept':'application/geo+json' } })
			.then(function(r){ if(!r.ok) throw new Error('nws '+r.status); return r.json(); });
	}

	// ---------- TIDES ----------
	// Tide predictions are deterministic (harmonic), so a successfully-fetched
	// window stays valid for its dates. We cache a wide (30-day) window in
	// localStorage and fall back to it whenever the live NOAA feed is down —
	// the board keeps showing correct tides straight through a NOAA outage.
	var tideSeries = [];
	var TIDE_CACHE_DAYS = 30;
	function tideKey(kind){ return 'oyc_tide_' + CFG.TIDE_STATION + '_' + kind; }
	function saveTideCache(kind, preds){
		try{ localStorage.setItem(tideKey(kind), JSON.stringify({ at:new Date().getTime(), preds:preds })); }catch(e){}
	}
	function readTideCache(kind){
		try{
			var o = JSON.parse(localStorage.getItem(tideKey(kind)));
			if(!o || !o.preds || !o.preds.length) return null;
			// A window fetched >28 days ago may no longer cover today.
			if(new Date().getTime() - o.at > 28*86400000) return null;
			return o.preds;
		}catch(e){ return null; }
	}
	function parsePreds(preds){ return preds.map(function(p){ return { t:new Date(p.t.replace(' ','T')), v:parseFloat(p.v) }; }); }
	function setTideCached(mode){
		var cap = document.querySelector('.tide-cap');
		if(!cap) return;
		cap.textContent = (mode === 'cached')  ? 'Predicted level — cached (live NOAA feed offline)'
		               : (mode === 'bundled') ? 'Predicted level — bundled predictions (live NOAA feed offline)'
		               : (mode === 'down')    ? 'NOAA tide service unavailable — retrying automatically'
		               : 'Predicted level, interpolated to the minute';
	}

	// Shown in the graph window when NOAA is down AND no cache/bundle could fill
	// in — an empty graph reads as "broken"; say what's actually happening.
	function showTideOutage(){
		if(tideSeries.length >= 2) return;               // keep last-good data
		var wrap = document.querySelector('.graph-wrap');
		if(wrap && !document.getElementById('tideOutage')){
			var d = document.createElement('div');
			d.id = 'tideOutage';
			d.innerHTML = '<div class="to-t">&#9888; NOAA tide service unavailable</div>'
				+ '<div class="to-s">The live tide feed (api.tidesandcurrents.noaa.gov) is not responding. '
				+ 'Tide data will reappear automatically as soon as NOAA recovers.</div>';
			wrap.appendChild(d);
		}
		setTideCached('down');
		var nt = $('nextTides');
		if(nt && /Loading/.test(nt.textContent)){
			nt.innerHTML = '<div class="nt-row"><span class="miss" style="grid-column:1/-1">NOAA tide service unavailable</span></div>';
		}
		// retry sooner than the normal 30-min cycle while the outage notice is up
		if(!showTideOutage._t){
			showTideOutage._t = setTimeout(function(){ showTideOutage._t = null; loadTides(); }, 5*60*1000);
		}
	}
	function clearTideOutage(){
		var d = document.getElementById('tideOutage');
		if(d) d.parentNode.removeChild(d);
	}

	// Last-resort fallback: hi/lo predictions bundled with the theme (deterministic,
	// pre-generated from NOAA). Covers a cold start during a NOAA outage, when
	// localStorage has never been seeded. Smooth curve via cosine interpolation.
	var BUNDLED_TIDES_URL = '<?php echo esc_url( get_template_directory_uri() . '/assets/tide-predictions-8518091.json' ); ?>';
	var bundledEvents = null;
	function bundledTides(){
		if(bundledEvents) return Promise.resolve(bundledEvents);
		return fetch(BUNDLED_TIDES_URL).then(function(r){
			if(!r.ok) throw new Error('no bundle');
			return r.json();
		}).then(function(j){
			if(!j || !j.events || !j.events.length) throw new Error('empty bundle');
			bundledEvents = j.events;
			return bundledEvents;
		});
	}
	function eventsToSeries(events, fromMs, toMs){
		var ev = parsePreds(events), out = [], step = 30*60*1000, i = 1;
		if(ev.length < 2) return out;
		for(var t = fromMs; t <= toMs; t += step){
			while(i < ev.length && ev[i].t.getTime() < t){ i++; }
			if(i >= ev.length || ev[i-1].t.getTime() > t) continue;
			var a = ev[i-1], b = ev[i];
			var f = (t - a.t.getTime()) / (b.t.getTime() - a.t.getTime());
			out.push({ t:new Date(t), v: a.v + (b.v - a.v) * (1 - Math.cos(Math.PI * f)) / 2 });
		}
		return out;
	}
	// Refresh the wide cache in the background (on load + every 6h) so the
	// fallback stays current without hammering NOAA on every 30-min refresh.
	function refreshTideCache(){
		var start=new Date(); start.setHours(0,0,0,0);
		coops('predictions', { begin_date:ymdhm(start), range:TIDE_CACHE_DAYS*24, datum:'MLLW', interval:'30' })
			.then(function(j){ if(j && j.predictions && j.predictions.length) saveTideCache('series', j.predictions); }).catch(function(){});
		coops('predictions', { begin_date:ymdhm(start), range:TIDE_CACHE_DAYS*24, datum:'MLLW', interval:'hilo' })
			.then(function(j){ if(j && j.predictions && j.predictions.length) saveTideCache('hilo', j.predictions); }).catch(function(){});
	}
	function loadTides(){
		var start=new Date(); start.setHours(0,0,0,0);
		// 48h smooth series for the graph — live, else cache, else bundled.
		coops('predictions', { begin_date:ymdhm(start), range:48, datum:'MLLW', interval:'30' })
			.then(function(j){
				if(!j || !j.predictions || !j.predictions.length) throw new Error('no predictions');
				tideSeries = parsePreds(j.predictions);
				drawGraph(); updateCurrentTide(); setTideCached('live'); markUpdated(true);
			}).catch(function(){
				var dayStart=new Date(); dayStart.setHours(0,0,0,0);
				var end = dayStart.getTime() + 48*3600*1000;
				var c = readTideCache('series');
				if(c){
					var win = parsePreds(c).filter(function(p){ return p.t.getTime()>=dayStart.getTime() && p.t.getTime()<=end; });
					tideSeries = (win.length>=2) ? win : parsePreds(c);
					drawGraph(); updateCurrentTide(); setTideCached('cached');
					return;
				}
				bundledTides().then(function(ev){
					var series = eventsToSeries(ev, dayStart.getTime(), end);
					if(series.length < 2){ showTideOutage(); return; }
					tideSeries = series;
					drawGraph(); updateCurrentTide(); setTideCached('bundled');
				}).catch(function(){ showTideOutage(); });
			});
		// high/low list for "next tides" — live, else cache, else bundled.
		coops('predictions', { begin_date:ymdhm(new Date()), range:48, datum:'MLLW', interval:'hilo' })
			.then(function(j){
				if(!j || !j.predictions || !j.predictions.length) throw new Error('no hilo');
				renderNextTides(j.predictions);
			}).catch(function(){
				var c = readTideCache('hilo');
				if(c){ renderNextTides(c); return; }
				bundledTides().then(function(ev){ renderNextTides(ev); }).catch(function(){});
			});
	}
	function interp(series, when){
		if(series.length<2) return null;
		for(var i=1;i<series.length;i++){
			if(series[i].t >= when){
				var a=series[i-1], b=series[i], f=(when-a.t)/(b.t-a.t);
				return { v:a.v+(b.v-a.v)*f, slope:(b.v-a.v) };
			}
		}
		return { v:series[series.length-1].v, slope:0 };
	}
	function updateCurrentTide(){
		var now=new Date(), r=interp(tideSeries, now);
		if(!r) return;
		$('tideVal').textContent = r.v.toFixed(1);
		var rising = r.slope >= 0;
		var el=$('tideTrend');
		el.className = 'trend ' + (rising?'rising':'falling');
		$('tideTrendAr').innerHTML = rising ? '&#9650;' : '&#9660;';
		$('tideTrendTxt').textContent = rising ? 'Rising' : 'Falling';
	}
	function renderNextTides(preds){
		var now=new Date(), out=[], i;
		for(i=0;i<preds.length;i++){
			var t=new Date(preds[i].t.replace(' ','T'));
			if(t>now){ out.push({ t:t, v:parseFloat(preds[i].v), hi:preds[i].type==='H' }); }
			if(out.length>=4) break;
		}
		if(!out.length){ return; }
		$('nextTides').innerHTML = out.map(function(x){
			return '<div class="nt-row '+(x.hi?'nt-hi':'nt-lo')+'">'
				+ '<span class="nt-ar">'+(x.hi?'&#9650;':'&#9660;')+'</span>'
				+ '<span class="nt-kind">'+(x.hi?'High':'Low')+'</span>'
				+ '<span class="nt-time">'+hmt(x.t)+'</span>'
				+ '<span class="nt-ft">'+x.v.toFixed(1)+' ft</span></div>';
		}).join('');
	}

	// ---------- TIDE GRAPH ----------
	function drawGraph(){
		var svg=$('tideGraph'); if(tideSeries.length<2){ return; }
		clearTideOutage();
		var W=1000,H=320, padL=8,padR=8,padT=26,padB=26;
		var xs=tideSeries.map(function(p){return p.t.getTime();});
		var vs=tideSeries.map(function(p){return p.v;});
		var minX=xs[0], maxX=xs[xs.length-1];
		var minV=Math.min.apply(null,vs), maxV=Math.max.apply(null,vs);
		var pv=(maxV-minV)*0.18||1; minV-=pv; maxV+=pv;
		function X(t){ return padL+(t-minX)/(maxX-minX)*(W-padL-padR); }
		function Y(v){ return padT+(1-(v-minV)/(maxV-minV))*(H-padT-padB); }
		var d='', i;
		for(i=0;i<tideSeries.length;i++){ d+=(i?'L':'M')+X(xs[i]).toFixed(1)+' '+Y(vs[i]).toFixed(1)+' '; }
		var area=d+'L'+X(maxX).toFixed(1)+' '+(H-padB)+' L'+X(minX).toFixed(1)+' '+(H-padB)+' Z';
		// hi/lo markers
		var marks='';
		for(i=1;i<tideSeries.length-1;i++){
			if((vs[i]>=vs[i-1]&&vs[i]>vs[i+1])||(vs[i]<=vs[i-1]&&vs[i]<vs[i+1])){
				var mx=X(xs[i]), my=Y(vs[i]), hi=vs[i]>=vs[i-1];
				marks+='<circle cx="'+mx.toFixed(1)+'" cy="'+my.toFixed(1)+'" r="3.5" fill="#d4a851"/>';
				marks+='<text class="hilo-lbl" x="'+mx.toFixed(1)+'" y="'+(hi?my-9:my+18).toFixed(1)+'">'+vs[i].toFixed(1)+'</text>';
			}
		}
		// day/6h ticks
		var ticks='';
		var t0=new Date(minX); t0.setMinutes(0,0,0);
		for(var tt=t0.getTime(); tt<=maxX; tt+=6*3600*1000){
			var dt=new Date(tt); if(tt<minX) continue;
			var gx=X(tt), lab=(dt.getHours()===0)?(MON[dt.getMonth()].slice(0,3)+' '+dt.getDate()):((dt.getHours()%12||12)+(dt.getHours()>=12?'p':'a'));
			var major = (dt.getHours()===0);
			ticks+='<line x1="'+gx.toFixed(1)+'" y1="'+padT+'" x2="'+gx.toFixed(1)+'" y2="'+(H-padB)+'" stroke="rgba(245,239,226,'+(major?'.30':'.16')+')"'+(major?' stroke-width="1.5"':'')+'/>';
			ticks+='<text class="axis" x="'+gx.toFixed(1)+'" y="'+(H-8)+'" text-anchor="middle">'+lab+'</text>';
		}
		// now line
		var nowX=X(Date.now());
		var nowLine = (Date.now()>=minX&&Date.now()<=maxX)
			? '<line x1="'+nowX.toFixed(1)+'" y1="'+padT+'" x2="'+nowX.toFixed(1)+'" y2="'+(H-padB)+'" stroke="#57a6d6" stroke-width="3" stroke-dasharray="6 4"/>'
			  + '<circle cx="'+nowX.toFixed(1)+'" cy="'+Y(interp(tideSeries,new Date()).v).toFixed(1)+'" r="4.5" fill="#57a6d6"/>' : '';
		svg.innerHTML =
			'<defs><linearGradient id="tg" x1="0" y1="0" x2="0" y2="1">'
			+ '<stop offset="0" stop-color="#d4a851" stop-opacity=".38"/>'
			+ '<stop offset="1" stop-color="#d4a851" stop-opacity="0"/></linearGradient></defs>'
			+ ticks
			+ '<path d="'+area+'" fill="url(#tg)"/>'
			+ '<path d="'+d+'" fill="none" stroke="#e6c374" stroke-width="2.4" stroke-linejoin="round"/>'
			+ marks + nowLine;
	}

	// ---------- WIND ----------
	function dirCardinal(deg){
		var dirs=['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
		return dirs[Math.round(deg/22.5)%16];
	}
	function drawDial(deg){
		// arrow points FROM the wind direction toward center
		var r=54, cx=60, cy=60, ticks='';
		for(var a=0;a<360;a+=30){
			var rad=(a-90)*Math.PI/180, x1=cx+Math.cos(rad)*(r-3), y1=cy+Math.sin(rad)*(r-3), x2=cx+Math.cos(rad)*(r-9), y2=cy+Math.sin(rad)*(r-9);
			ticks+='<line x1="'+x1.toFixed(1)+'" y1="'+y1.toFixed(1)+'" x2="'+x2.toFixed(1)+'" y2="'+y2.toFixed(1)+'" stroke="rgba(120,180,220,.35)" stroke-width="'+(a%90===0?2:1)+'"/>';
		}
		var needle='';
		if(deg!=null){
			// needle flies WITH the wind: tail at the FROM side, arrowhead pointing
			// downwind (wind from NW => arrow points SE), weather-vane convention.
			var rr=(deg+90)*Math.PI/180;
			var tipx=cx+Math.cos(rr)*(r-10), tipy=cy+Math.sin(rr)*(r-10);            // downwind side
			var tailx=cx-Math.cos(rr)*(r-22), taily=cy-Math.sin(rr)*(r-22);
			var w1x=tipx-Math.cos(rr-0.42)*12, w1y=tipy-Math.sin(rr-0.42)*12;
			var w2x=tipx-Math.cos(rr+0.42)*12, w2y=tipy-Math.sin(rr+0.42)*12;
			needle='<line x1="'+tailx.toFixed(1)+'" y1="'+taily.toFixed(1)+'" x2="'+((tipx+w1x+w2x)/3).toFixed(1)+'" y2="'+((tipy+w1y+w2y)/3).toFixed(1)+'" stroke="#d4a851" stroke-width="3.5" stroke-linecap="round"/>'
				+ '<polygon points="'+tipx.toFixed(1)+','+tipy.toFixed(1)+' '+w1x.toFixed(1)+','+w1y.toFixed(1)+' '+w2x.toFixed(1)+','+w2y.toFixed(1)+'" fill="#d4a851"/>';
		}
		// inline arrow beside the cardinal readout — points the way the wind BLOWS
		var arr=$('windArrow');
		if(arr){
			if(deg==null){ arr.style.display='none'; }
			else{ arr.style.display='inline-block'; arr.style.transform='rotate('+(((deg+90)%360)).toFixed(0)+'deg)'; }
		}
		$('windDial').innerHTML = '<svg viewBox="0 0 120 120">'
			+ '<circle cx="60" cy="60" r="'+r+'" fill="rgba(8,20,34,.6)" stroke="rgba(120,180,220,.25)"/>'
			+ ticks
			+ '<text x="60" y="16" fill="#aeb9c8" font-size="9" text-anchor="middle" font-family="monospace">N</text>'
			+ '<text x="60" y="112" fill="#84909f" font-size="9" text-anchor="middle" font-family="monospace">S</text>'
			+ needle + '<circle cx="60" cy="60" r="3" fill="#aeb9c8"/></svg>';
	}
	function windFromObs(){
		fetchObs().then(function(p){
			var kts = obsKts(p.windSpeed);
			if(kts == null){ return; }
			$('windSpd').textContent = Math.round(kts);
			var deg = (p.windDirection && p.windDirection.value != null) ? p.windDirection.value : null;
			$('windDir').textContent = (deg != null) ? dirCardinal(deg) : '—';
			var g = obsKts(p.windGust);
			$('windGust').textContent = (g != null) ? (Math.round(g)+' kt') : '—';
			$('windSta').textContent = 'NWS ASOS';
			drawDial(deg); markUpdated(true);
		}).catch(function(){});
	}
	function loadWind(){
		coops('wind', { date:'latest' }).then(function(j){
			var d = j && j.data && j.data[0];
			if(!d){ windFromObs(); return; }
			$('windSpd').textContent = Math.round(parseFloat(d.s));
			var deg = d.d!=null ? parseFloat(d.d) : null;
			$('windDir').textContent = (d.dr || (deg!=null?dirCardinal(deg):'—'));
			$('windGust').textContent = (d.g!=null && d.g!=='' ) ? (Math.round(parseFloat(d.g))+' kt') : '—';
			$('windSta').textContent = 'STA. ' + CFG.MET_STATION;
			drawDial(deg); markUpdated(true);
		}).catch(function(){ windFromObs(); });
	}
	drawDial(null);

	// ---------- NWS OBSERVATIONS (shared ASOS source: KHPN, then KLGA) ----------
	// Kings Point has no humidity/visibility sensors, and a CO-OPS outage takes
	// out wind/temp/pressure too — the nearest ASOS stations fill both gaps.
	// One merged observation is shared by all consumers (5-min memo).
	var OBS_STATIONS = ['KHPN','KLGA'];
	var obsProps = null, obsAt = 0;
	function fetchObs(){
		if(obsProps && (new Date().getTime() - obsAt) < 5*60*1000){ return Promise.resolve(obsProps); }
		var FIELDS = ['relativeHumidity','visibility','temperature','barometricPressure','windSpeed','windDirection','windGust'];
		var merged = {};
		function missing(){ return FIELDS.some(function(k){ return !(merged[k] && merged[k].value != null); }); }
		function step(i){
			if(i >= OBS_STATIONS.length || !missing()){
				obsProps = merged; obsAt = new Date().getTime();
				return Promise.resolve(merged);
			}
			return nws('stations/'+OBS_STATIONS[i]+'/observations/latest').then(function(j){
				var p = j && j.properties;
				if(p){ FIELDS.forEach(function(k){
					if(!(merged[k] && merged[k].value != null) && p[k] && p[k].value != null){ merged[k] = p[k]; }
				}); }
				return step(i+1);
			}).catch(function(){ return step(i+1); });
		}
		return step(0);
	}
	function obsKts(m){ // windSpeed/windGust measurement -> knots (unit-aware)
		if(!m || m.value == null) return null;
		var u = m.unitCode || '';
		if(u.indexOf('km_h') >= 0) return m.value / 1.852;
		if(u.indexOf('m_s') >= 0)  return m.value * 1.94384;
		return m.value;
	}
	function obsCell(elId, pick){
		fetchObs().then(function(p){
			var v = pick(p);
			$(elId).textContent = (v != null) ? v : '—';
			$(elId).classList.toggle('miss', v == null);
			if(v != null) markUpdated(true);
		}).catch(function(){});
	}

	// ---------- CONDITIONS ----------
	// CO-OPS first; on failure/no-data fall back to the shared NWS observation
	// (water temp has no ASOS equivalent, so it keeps the dash during outages).
	function metOne(product, elId, fmt, fb){
		coops(product, { date:'latest' }).then(function(j){
			var d=j && j.data && j.data[0];
			if(d && d.v!=null && d.v!==''){
				$(elId).textContent = fmt(parseFloat(d.v));
				$(elId).classList.remove('miss');
			} else if(fb){ fb(); }
			else {
				$(elId).textContent = '—';
				$(elId).classList.add('miss');
			}
		}).catch(function(){ if(fb){ fb(); } });
	}
	function loadConditions(){
		metOne('air_temperature','airTemp',function(v){return Math.round(v)+'°';}, function(){
			obsCell('airTemp', function(p){ return (p.temperature && p.temperature.value!=null) ? Math.round(p.temperature.value*9/5+32)+'°' : null; });
		});
		metOne('water_temperature','waterTemp',function(v){return Math.round(v)+'°';});
		metOne('air_pressure','pressure',function(v){return Math.round(v)+' mb';}, function(){
			obsCell('pressure', function(p){ return (p.barometricPressure && p.barometricPressure.value!=null) ? Math.round(p.barometricPressure.value/100)+' mb' : null; });
		});
		// humidity + visibility always come from the ASOS observation
		obsCell('humidity', function(p){ return (p.relativeHumidity && p.relativeHumidity.value!=null) ? Math.round(p.relativeHumidity.value)+'%' : null; });
		obsCell('visibility', function(p){ return (p.visibility && p.visibility.value!=null) ? (p.visibility.value/1852).toFixed(1)+' nm' : null; });
	}

	// ---------- FORECAST ----------
	// Marine zones don't expose /zones/.../forecast — parse the Coastal Waters
	// Forecast (CWF) text product and pull the ANZ335 block's period lines.
	function loadForecast(){
		nws('products/types/CWF/locations/'+CFG.CWF_OFFICE).then(function(list){
			var g = list && list['@graph'];
			var id = g && g[0] && g[0]['@id'];
			if(!id) throw new Error('no CWF');
			return fetch(id).then(function(r){ return r.json(); });
		}).then(function(prod){
			var text = (prod && prod.productText) || '';
			var blocks = text.split('$$'), blk=null, i;
			for(i=0;i<blocks.length;i++){ if(/\bANZ335\b/.test(blocks[i])){ blk=blocks[i]; break; } }
			if(!blk) throw new Error('no ANZ335');
			var periods=[], re=/\.([A-Z0-9 ]+?)\.\.\.([\s\S]*?)(?=\n\.[A-Z0-9]|\n\n|$)/g, m;
			while((m=re.exec(blk))){
				var body=m[2].replace(/\s+/g,' ').trim();
				if(body) periods.push({ name:m[1].trim(), body:body });
			}
			if(!periods.length) throw new Error('no periods');
			$('forecast').innerHTML = periods.slice(0,2).map(function(p){
				return '<div class="fc-row"><div class="fc-when">'+p.name+'</div>'
					+ '<div class="fc-txt">'+p.body+'</div></div>';
			}).join('');
			renderWaves(periods);
			markUpdated(true);
		}).catch(function(){});
	}

	// Current-period wave conditions, derived from the same CWF text.
	// "Seas" gives the significant wave height (always present); the optional
	// "Wave Detail: <DIR> <ft> at <sec> seconds" line adds period + direction.
	function renderWaves(periods){
		if(!periods || !periods.length) return;
		var cur = periods[0], body = cur.body || '';
		var seas = body.match(/Seas\s+([^.,;]+)/i);
		var ht = seas ? seas[1].trim().replace(/(\d+)\s*ft or less/i,'≤1 ft').replace(/^around\s+/i,'') : null;
		var wd = body.match(/Wave Detail:\s*([NSEW]{1,3})\s+[\d.]+\s*ft\s+at\s+([\d.]+)\s*second/i);
		$('waveHt').textContent = ht || '—';
		$('waveHt').classList.toggle('miss', !ht);
		$('wavePer').textContent = wd ? (wd[2] + ' s') : '—';
		$('waveDir').textContent = wd ? wd[1] : '—';
		$('waveCap').textContent = 'Forecast seas — ' + (cur.name || 'current period');
	}

	// ---------- ALERTS ----------
	function loadAlerts(){
		nws('alerts/active?point='+CFG.LAT+'%2C'+CFG.LON).then(function(j){
			var f = (j && j.features) || [];
			if(!f.length){ $('alertBar').classList.add('hidden'); return; }
			var parts = f.map(function(a){
				var pr=a.properties||{};
				return (pr.event||'Alert') + ' — ' + (pr.headline || pr.event || '');
			});
			$('alertText').textContent = parts.join('    •    ');
			$('alertBar').classList.remove('hidden');
		}).catch(function(){});
	}

	// ---------- SUN / MOON (computed, no network) ----------
	var rad=Math.PI/180, dayMs=86400000, J1970=2440588, J2000=2451545, e=rad*23.4397;
	function toJulian(d){return d.valueOf()/dayMs-0.5+J1970;}
	function fromJulian(j){return new Date((j+0.5-J1970)*dayMs);}
	function toDays(d){return toJulian(d)-J2000;}
	function ra(l,b){return Math.atan2(Math.sin(l)*Math.cos(e)-Math.tan(b)*Math.sin(e),Math.cos(l));}
	function dec(l,b){return Math.asin(Math.sin(b)*Math.cos(e)+Math.cos(b)*Math.sin(e)*Math.sin(l));}
	function sma(d){return rad*(357.5291+0.98560028*d);}
	function ecl(M){var C=rad*(1.9148*Math.sin(M)+0.02*Math.sin(2*M)+0.0003*Math.sin(3*M));return M+C+rad*102.9372+Math.PI;}
	function sunCoords(d){var M=sma(d),L=ecl(M);return {dec:dec(L,0),ra:ra(L,0)};}
	function moonCoords(d){var L=rad*(218.316+13.176396*d),M=rad*(134.963+13.064993*d),F=rad*(93.272+13.229350*d),
		l=L+rad*6.289*Math.sin(M),b=rad*5.128*Math.sin(F),dt=385001-20905*Math.cos(M);
		return {ra:ra(l,b),dec:dec(l,b),dist:dt};}
	function sunTimes(date,lat,lng){
		var lw=rad*-lng, phi=rad*lat, d=toDays(date), n=Math.round(d-0.0009-lw/(2*Math.PI)),
			ds=0.0009+(0+lw)/(2*Math.PI)+n, M=sma(ds), L=ecl(M), de=dec(L,0),
			Jnoon=J2000+ds+0.0053*Math.sin(M)-0.0069*Math.sin(2*L),
			h0=-0.833*rad, w=Math.acos((Math.sin(h0)-Math.sin(phi)*Math.sin(de))/(Math.cos(phi)*Math.cos(de))),
			a=0.0009+(w+lw)/(2*Math.PI)+n, Jset=J2000+a+0.0053*Math.sin(M)-0.0069*Math.sin(2*L),
			Jrise=Jnoon-(Jset-Jnoon);
		return {sunrise:fromJulian(Jrise), sunset:fromJulian(Jset)};
	}
	function moonIllum(date){
		var d=toDays(date), s=sunCoords(d), m=moonCoords(d), sdist=149598000,
			phi=Math.acos(Math.sin(s.dec)*Math.sin(m.dec)+Math.cos(s.dec)*Math.cos(m.dec)*Math.cos(s.ra-m.ra)),
			inc=Math.atan2(sdist*Math.sin(phi),m.dist-sdist*Math.cos(phi)),
			angle=Math.atan2(Math.cos(s.dec)*Math.sin(s.ra-m.ra),Math.sin(s.dec)*Math.cos(m.dec)-Math.cos(s.dec)*Math.sin(m.dec)*Math.cos(s.ra-m.ra));
		return {fraction:(1+Math.cos(inc))/2, phase:0.5+0.5*inc*(angle<0?-1:1)/Math.PI};
	}
	// Classic two-arc moon phase: outer limb (semicircle) + terminator ellipse.
	function moonSvg(f, waxing){
		var R=21, C=23, rx=(R*Math.abs(1-2*f)).toFixed(2);
		var outer = waxing ? 1 : 0;
		var inner = (f>0.5) ? outer : (1-outer);
		var d='M '+C+' '+(C-R)+' A '+R+' '+R+' 0 0 '+outer+' '+C+' '+(C+R)
			+' A '+rx+' '+R+' 0 0 '+inner+' '+C+' '+(C-R)+' Z';
		return '<svg viewBox="0 0 46 46" width="100%" height="100%">'
			+ '<circle cx="'+C+'" cy="'+C+'" r="'+R+'" fill="#0a1220"/>'
			+ '<path d="'+d+'" fill="#eef6ff"/></svg>';
	}
	function moonName(p){
		if(p<0.03||p>0.97) return 'New Moon';
		if(p<0.22) return 'Waxing Crescent';
		if(p<0.28) return 'First Quarter';
		if(p<0.47) return 'Waxing Gibbous';
		if(p<0.53) return 'Full Moon';
		if(p<0.72) return 'Waning Gibbous';
		if(p<0.78) return 'Last Quarter';
		return 'Waning Crescent';
	}
	function loadSunMoon(){
		var st=sunTimes(new Date(), CFG.LAT, CFG.LON);
		$('sunrise').textContent = hm(st.sunrise);
		$('sunset').textContent  = hm(st.sunset);
		var mi=moonIllum(new Date());
		$('moonPct').textContent = Math.round(mi.fraction*100)+'%';
		$('moonName').textContent = moonName(mi.phase);
		$('moon').innerHTML = moonSvg(mi.fraction, mi.phase<0.5);
	}

	// ---------- INIT + refresh ----------
	refreshTideCache();                                // seed the 30-day fallback cache
	loadTides(); loadWind(); loadConditions(); loadForecast(); loadAlerts(); loadSunMoon();
	setInterval(updateCurrentTide, 60*1000);          // re-interpolate current level each minute
	setInterval(function(){ drawGraph(); }, 5*60*1000);
	setInterval(loadTides, 30*60*1000);
	setInterval(refreshTideCache, 6*60*60*1000);       // keep the fallback cache fresh
	setInterval(loadWind, 5*60*1000);
	setInterval(loadConditions, 10*60*1000);
	setInterval(loadForecast, 30*60*1000);
	setInterval(loadAlerts, 5*60*1000);
	setInterval(loadSunMoon, 30*60*1000);
})();
</script>
</body>
</html>
<?php
exit;
