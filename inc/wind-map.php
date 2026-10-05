<?php
/**
 * Reusable Wind & Radar map component — the full Leaflet wind/radar/temp/precip/
 * waves map, self-contained and CSS-scoped under #oycwm so it can drop into any
 * page (its own /wind/ template, or a card on /weather/) without clashing.
 * Pass $embed=true to hide the standalone top bar & disclaimer (card use).
 * @package Orienta_Yacht_Club
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

function oyc_wind_map_html( $embed = false ) {
	$oyc_ajax = esc_url( admin_url( 'admin-ajax.php' ) );
	ob_start();
	?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet-velocity@1.7.0/dist/leaflet-velocity.min.css">
<style>
#oycwm{--navy:#0b2a4a;--navy-ink:#04162a;--harbor:#1583cf;--brass:#b08a3e;--ink:#16324a;--mute:#5a6b7d;--faint:#8a99a8;--line:#e0e7f0;--panel:#f5f9fd;--cream:#f7f3ea;}
#oycwm *{box-sizing:border-box;margin:0;padding:0}
#oycwm{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
	color:var(--ink);padding:18px;-webkit-font-smoothing:antialiased}
/* min-height:0 guards against a host page's global `.wrap{min-height:100vh}`
   leaking in and stretching the card (the /weather/ board defines exactly that) */
#oycwm .wrap{max-width:1160px;margin:0 auto;display:flex;flex-direction:column;gap:16px;min-height:0}
#oycwm a{color:var(--harbor)}
#oycwm .topbar{display:flex;align-items:center;justify-content:space-between;gap:10px 20px;flex-wrap:wrap;padding:10px 22px;border:1px solid var(--line);border-radius:16px;background:#fff}
#oycwm .tb-left{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
#oycwm .tb-brand{display:inline-flex;align-items:center;line-height:0;text-decoration:none}
#oycwm .tb-logo{height:44px;width:auto;display:block}
#oycwm .tb-title{color:var(--brass);font-weight:800;letter-spacing:.16em;text-transform:uppercase;font-size:13px}
#oycwm .tb-nav{display:flex;gap:6px 16px;flex-wrap:wrap}
#oycwm .tb-nav a{color:var(--mute);text-decoration:none;font-size:.82rem;font-weight:600;letter-spacing:.03em}
#oycwm .tb-nav a:hover{color:var(--harbor)}
#oycwm .card{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 6px 22px rgba(11,42,74,.08);overflow:hidden}
#oycwm .hd{display:flex;flex-wrap:wrap;align-items:center;gap:8px 14px;padding:14px 18px 8px}
#oycwm .hd h2{margin:0;font-size:1.12rem;color:var(--navy);font-weight:700}
#oycwm .hd .st{margin-left:auto;font-size:.75rem;color:var(--mute)}
#oycwm .fsbtn{flex:none;margin-left:8px;display:inline-flex;align-items:center;gap:6px;padding:6px 13px;border:1px solid var(--navy);background:var(--navy);color:#fff;border-radius:999px;cursor:pointer;font-size:.76rem;font-weight:700;letter-spacing:.02em;line-height:1}
#oycwm .fsbtn:hover{background:var(--harbor);border-color:var(--harbor)}
#oycwm .fsbtn svg{display:block}
/* full-screen (native Fullscreen API, or the .isfull class as a CSS fallback) */
#oycwm .card.isfull{position:fixed;inset:0;z-index:2147483647;margin:0;border:0;border-radius:0;background:#fff;display:flex;flex-direction:column;max-width:none}
#oycwm .card.isfull .mapwrap{flex:1 1 auto;min-height:0}
#oycwm .card.isfull #map{height:100%}
#oycwm .card.isfull .satpanel{flex:1 1 auto;max-height:none}
#oycwm .tabs{display:flex;flex-wrap:wrap;gap:6px;background:var(--panel);border:1px solid var(--line);border-radius:999px;padding:3px}
#oycwm .tabs button{border:0;background:none;color:var(--mute);font-weight:700;font-size:.8rem;letter-spacing:.04em;padding:6px 16px;border-radius:999px;cursor:pointer}
#oycwm .tabs button.on{background:var(--harbor);color:#fff}
#oycwm .erbar{display:flex;flex-wrap:wrap;align-items:center;gap:6px 16px;margin:2px 18px 6px;padding:9px 14px;border:1px solid var(--line);border-radius:12px;background:linear-gradient(180deg,#fbfdff,#f3f8fd)}
#oycwm .erbar .er-name{font-weight:800;color:var(--navy);font-size:.82rem;letter-spacing:.02em;display:flex;align-items:center;gap:7px}
#oycwm .erbar .er-stats{display:flex;flex-wrap:wrap;gap:4px 18px;margin-left:auto}
#oycwm .erbar .st-item{display:flex;flex-direction:column;line-height:1.15}
#oycwm .erbar .st-item .v{font-weight:800;color:var(--ink);font-size:1.02rem}
#oycwm .erbar .st-item .k{font-size:.64rem;color:var(--faint);text-transform:uppercase;letter-spacing:.08em;font-weight:700}
#oycwm .layers{display:flex;flex-wrap:wrap;gap:6px 8px;padding:2px 18px 8px}
#oycwm .layers label{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--line);border-radius:999px;padding:5px 12px;font-size:.78rem;font-weight:600;color:var(--ink);cursor:pointer;user-select:none}
#oycwm .layers label:hover{border-color:var(--harbor)}
#oycwm .layers input{accent-color:var(--harbor)}
#oycwm .layers .hint{color:var(--faint);font-size:.74rem;align-self:center}
#oycwm .legend{display:flex;align-items:center;gap:2px;padding:0 18px 8px;font-size:.72rem;color:var(--mute);flex-wrap:wrap}
#oycwm .legend .sc{display:flex;height:12px;border-radius:3px;overflow:hidden;width:190px;margin:0 8px}
#oycwm .legend .sc i{flex:1}
#oycwm .legend.radar .sc i:nth-child(1){background:#8ec7ff}
#oycwm .legend.radar .sc i:nth-child(2){background:#4a9cf0}
#oycwm .legend.radar .sc i:nth-child(3){background:#2ecc71}
#oycwm .legend.radar .sc i:nth-child(4){background:#f1c40f}
#oycwm .legend.radar .sc i:nth-child(5){background:#e67e22}
#oycwm .legend.radar .sc i:nth-child(6){background:#e74c3c}
#oycwm .legend.radar .sc i:nth-child(7){background:#b03a7a}
#oycwm .ctrl{display:flex;align-items:center;gap:12px;padding:8px 18px;border-top:1px solid var(--line);border-bottom:1px solid var(--line);background:var(--panel)}
#oycwm .ctrl button{border:1px solid var(--line);background:#fff;color:var(--navy);width:38px;height:34px;border-radius:9px;cursor:pointer;font-size:15px;flex:none}
#oycwm .ctrl button:hover{border-color:var(--harbor)}
#oycwm .ctrl input[type=range]{flex:1;accent-color:var(--harbor);min-width:120px}
#oycwm .ctrl .tlabel{font-weight:700;color:var(--navy);font-size:.9rem;white-space:nowrap;min-width:150px;text-align:right}
#oycwm .ctrl .tlabel small{display:block;font-weight:600;color:var(--mute);font-size:.72rem}
#oycwm .mapwrap{position:relative}
#oycwm #map{height:min(64vh,600px);width:100%;background:#dbe7f0}
#oycwm .satpanel{width:100%;min-height:280px;background:#0b1622;display:flex;align-items:center;justify-content:center;padding:6px}
#oycwm .satpanel img{max-width:100%;max-height:min(66vh,640px);height:auto;width:auto;display:block}
#oycwm .satpanel.loading::after{content:'Loading satellite\2026';color:#9fb4c6;font-size:.85rem}
#oycwm #satLayers .sat-t{border:1px solid var(--line);background:var(--panel);color:var(--mute);font-weight:700;font-size:.78rem;letter-spacing:.02em;padding:5px 13px;border-radius:999px;cursor:pointer}
#oycwm #satLayers .sat-t:hover{border-color:var(--harbor)}
#oycwm #satLayers .sat-t.on{background:var(--harbor);color:#fff;border-color:var(--harbor)}
#oycwm .chart-sel{border:1px solid var(--line);background:#fff;color:var(--ink);font-weight:600;font-size:.82rem;padding:6px 12px;border-radius:10px;cursor:pointer;min-width:220px}
#oycwm .chart-sel:hover{border-color:var(--harbor)}
#oycwm .hovertip{position:absolute;z-index:600;pointer-events:none;background:rgba(11,42,74,.92);color:#fff;font-size:12px;font-weight:600;padding:5px 9px;border-radius:7px;white-space:nowrap;transform:translate(-50%,calc(-100% - 12px));display:none;box-shadow:0 4px 12px rgba(0,0,0,.3)}
#oycwm .hovertip .hd2{color:#bfe4f5;font-size:10px;letter-spacing:.05em;text-transform:uppercase;display:block}
#oycwm .tiphelp{position:absolute;z-index:590;left:12px;bottom:12px;background:rgba(255,255,255,.9);border:1px solid var(--line);color:var(--mute);font-size:11px;font-weight:600;padding:6px 10px;border-radius:8px;max-width:230px;line-height:1.35}
#oycwm .card .foot{padding:10px 18px 16px;font-size:.72rem;color:var(--mute)}
#oycwm .leaflet-control.velocity-control{background:rgba(11,42,74,.82);color:#fff;padding:5px 9px;border-radius:8px;font-size:12px;font-weight:600}
#oycwm .iso-lbl{background:none;border:none;box-shadow:none;color:#334;font-size:10px;font-weight:700;text-shadow:0 0 3px #fff,0 0 3px #fff}
#oycwm .map-label{background:none;border:none;box-shadow:none;display:flex;align-items:center;gap:4px;white-space:nowrap;font-weight:800;font-size:11px;color:#0b2a4a;text-shadow:0 0 3px #fff,0 0 4px #fff,0 0 4px #fff;transform:translate(-4px,-7px)}
#oycwm .barb-mk svg{filter:drop-shadow(0 0 1px #fff) drop-shadow(0 0 1px #fff)}
#oycwm .gs-mk{background:none;border:none}
#oycwm .gs-arrow{will-change:transform}
#oycwm .gs-flow{animation:oycgsflow 1.7s linear infinite}
#oycwm .gs-flow svg{display:block;filter:drop-shadow(0 0 1px rgba(255,255,255,.95))}
@keyframes oycgsflow{0%{transform:translateY(6px);opacity:.05}25%{opacity:1}70%{opacity:1}100%{transform:translateY(-8px);opacity:.05}}
@media (prefers-reduced-motion:reduce){#oycwm .gs-flow{animation:none;opacity:1;transform:none}}
#oycwm .map-label .ml-dot{width:7px;height:7px;border-radius:50%;background:#b08a3e;border:1.5px solid #fff;box-shadow:0 0 2px rgba(0,0,0,.45);flex:none}
#oycwm .storm-mk{background:none;border:none}
#oycwm .storm-ic{position:relative;transform:translate(-50%,-50%)}
#oycwm .storm-sym{display:block;font-size:22px;line-height:0;filter:drop-shadow(0 0 2px #06121f) drop-shadow(0 0 2px #06121f)}
#oycwm .storm-lbl{position:absolute;left:14px;top:-9px;font-weight:800;font-size:10px;color:#fff;padding:2px 6px;border-radius:6px;white-space:nowrap;box-shadow:0 1px 5px rgba(0,0,0,.5);letter-spacing:.02em}
#oycwm .oyc-mk{background:none;border:none}
#oycwm .oyc-ic{position:relative;transform:translate(-50%,-50%)}
#oycwm .oyc-dot{display:block;width:13px;height:13px;border-radius:50%;background:#0b2a4a;border:2px solid #f7d774;box-shadow:0 0 0 1.5px #fff,0 1px 3px rgba(0,0,0,.45)}
#oycwm .oyc-lbl{position:absolute;left:13px;top:-3px;font-weight:800;font-size:11px;color:#0b2a4a;letter-spacing:.05em;text-shadow:0 0 3px #fff,0 0 4px #fff,0 0 4px #fff;white-space:nowrap}
#oycwm .b42-mk{background:none;border:none}
#oycwm .b42-ic{position:relative;transform:translate(-50%,-50%)}
#oycwm .b42-dot{display:block;width:13px;height:13px;border-radius:50%;background:#f7d774;border:2px solid #b08a3e;box-shadow:0 0 0 1.5px #fff,0 1px 3px rgba(0,0,0,.45)}
#oycwm .b42-lbl{position:absolute;left:13px;top:-3px;font-weight:800;font-size:11px;color:#8a6a1e;letter-spacing:.05em;text-shadow:0 0 3px #fff,0 0 4px #fff,0 0 4px #fff;white-space:nowrap}
#oycwm .erref-mk{background:none;border:none}
#oycwm .erref-ic{position:relative;transform:translate(-50%,-50%)}
#oycwm .erref-dot{display:block;width:9px;height:9px;border-radius:50%;background:#64748b;border:1.5px solid #fff;box-shadow:0 0 0 1px rgba(0,0,0,.3)}
#oycwm .erref-lbl{position:absolute;left:11px;top:-2px;font-weight:700;font-size:10px;color:#475569;text-shadow:0 0 3px #fff,0 0 3px #fff;white-space:nowrap}
#oycwm .buoy-mk{background:none;border:none}
#oycwm .buoy-ic{position:relative;color:#0b2a4a;font-size:13px;font-weight:900;line-height:0;text-shadow:0 0 3px #fff,0 0 3px #fff;transform:translate(-50%,-50%)}
#oycwm .buoy-lbl{position:absolute;left:11px;top:-8px;font-size:10px;font-weight:700;color:#0b2a4a;background:rgba(255,255,255,.9);border:1px solid var(--line);border-radius:5px;padding:2px 6px;line-height:1.2;white-space:nowrap;box-shadow:0 2px 6px rgba(11,42,74,.15)}
#oycwm .hl-mk{background:none;border:none}
#oycwm .hl{transform:translate(-50%,-50%);text-align:center;font-family:Georgia,"Times New Roman",serif;font-weight:800;font-size:27px;line-height:.8}
#oycwm .hl b{display:block;text-shadow:0 0 3px #fff,0 0 5px #fff,0 0 6px #fff}
#oycwm .hl.lo b{color:#c0392b}
#oycwm .hl.hi b{color:#1f6fb0}
#oycwm .hl span{display:block;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;font-size:10px;font-weight:700;color:#26384a;text-shadow:0 0 2px #fff,0 0 3px #fff,0 0 3px #fff;margin-top:1px}
#oycwm .pin-co .co-t{font-weight:800;color:var(--navy);font-size:.8rem;margin-bottom:3px;display:flex;justify-content:space-between;gap:10px;align-items:baseline}
#oycwm .pin-co .co-t .co-ll{font-weight:600;color:var(--faint);font-size:.66rem}
#oycwm .pin-co .co-row{display:flex;gap:12px;font-size:.78rem}
#oycwm .pin-co .co-row .v{font-weight:800;color:var(--ink)}
#oycwm .pin-co .co-row .k{color:var(--mute);font-size:.7rem}
#oycwm .pin-co .co-dist{font-size:.74rem;font-weight:800;color:var(--harbor);margin:-1px 0 5px}
#oycwm .pin-co .co-x{margin-top:5px;font-size:.66rem;color:var(--faint)}
#oycwm .leaflet-popup-content{margin:9px 12px}
#oycwm .dl{padding:16px 18px}
#oycwm .dl h2{margin:0 0 4px;font-size:1.05rem;color:var(--navy);font-weight:700}
#oycwm .dl p{color:var(--mute);font-size:.86rem;line-height:1.5;margin:6px 0}
#oycwm .dl-btn{display:inline-flex;align-items:center;gap:8px;margin-top:10px;background:var(--harbor);color:#fff;text-decoration:none;font-weight:700;font-size:.9rem;padding:11px 20px;border-radius:999px;transition:.15s}
#oycwm .dl-btn:hover{background:#0f6fb0}
#oycwm .dl .apps{color:var(--faint);font-size:.78rem;margin-top:10px}
#oycwm .disclaimer{text-align:center;color:var(--mute);font-size:.82rem;font-style:italic}
/* ---- embedded (card on /weather/): dissolve the component's own frame so it
   fills the host card flush and reads as one piece with it ---- */
#oycwm.embed{padding:0;background:transparent;min-height:0}
#oycwm.embed .topbar, #oycwm.embed .disclaimer{display:none}
#oycwm.embed .wrap{gap:0;max-width:none;margin:0}
/* drop the inner card chrome (border / shadow / radius / white fill) — the host
   .card already provides the panel, border and rounded corners */
#oycwm.embed .card{border:0;border-radius:0;box-shadow:none;background:transparent}
/* the host card's <h2> already titles this, so hide the component's own title;
   the view tabs then lead the header row */
#oycwm.embed .hd{padding:2px 18px 8px}
#oycwm.embed .hd h2{display:none}
#oycwm.embed #map{height:690px}
/* keep the GRIB download on the board but compact — just the button, dropping
   the heading and description paragraphs that made the block tall */
#oycwm.embed .card.dl{padding:8px 18px 4px}
#oycwm.embed .card.dl h2, #oycwm.embed .card.dl p{display:none}
#oycwm.embed .card.dl .dl-btn{margin:0}

#oycwm:not(.embed){padding:16px;background:radial-gradient(1200px 700px at 70% -10%,#ffffff 0%,#eaf1f8 50%,#dde8f2 100%);border-radius:16px}
</style>
<div id="oycwm" class="<?php echo $embed ? 'embed' : ''; ?>">
<div class="wrap">
	<div class="topbar">
		<div class="tb-left">
			<a class="tb-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> — Home"><?php oyc_burgee( 'tb-logo' ); ?></a>
			<div class="tb-title">Mamaroneck Harbor &middot; Wind &amp; Radar</div>
		</div>
		<nav class="tb-nav" aria-label="Weather pages">
			<a href="<?php echo esc_url( home_url( '/weather/' ) ); ?>">Live Conditions</a>
		</nav>
	</div>
	<script>/* standalone page: promote the lazy-load placeholder logo to its real src */
	(function(){var i=document.querySelector('.tb-logo');if(!i)return;var d=i.getAttribute('data-src')||i.getAttribute('data-lazy-src')||i.getAttribute('data-smush-src');if(d){i.src=d;i.removeAttribute('loading');}})();</script>

	<!-- WIND & RADAR MAP -->
	<div class="card">
		<div class="hd">
			<h2>Wind &amp; Radar Map</h2>
			<div class="tabs" id="viewTabs" role="tablist">
				<button id="tabWind" class="on" role="tab" aria-selected="true">Wind</button>
				<button id="tabRadar" role="tab" aria-selected="false">Radar</button>
				<button id="tabTemp" role="tab" aria-selected="false">Temp</button>
				<button id="tabPrecip" role="tab" aria-selected="false">Precip</button>
				<button id="tabWave" role="tab" aria-selected="false">Waves</button>
				<button id="tabSat" role="tab" aria-selected="false">Satellite</button>
				<button id="tabCharts" role="tab" aria-selected="false">Charts</button>
			</div>
			<span class="st" id="st">Loading&hellip;</span>
			<button type="button" class="fsbtn" id="oycwmFs" title="Full screen" aria-label="Full screen"></button>
		</div>

		<div class="erbar" id="erBar">
			<div class="er-name">&#9678; <span>Buoy 42</span></div>
			<div class="er-stats" id="erStats">
				<div class="st-item"><span class="v" id="erWind">&mdash;</span><span class="k">Wind</span></div>
				<div class="st-item"><span class="v" id="erGust">&mdash;</span><span class="k">Gust</span></div>
				<div class="st-item"><span class="v" id="erDir">&mdash;</span><span class="k">From</span></div>
				<div class="st-item"><span class="v" id="erPres">&mdash;</span><span class="k">Pressure</span></div>
				<div class="st-item"><span class="v" id="erTemp">&mdash;</span><span class="k">Temp</span></div>
				<div class="st-item"><span class="v" id="erPrecip">&mdash;</span><span class="k">Precip</span></div>
				<div class="st-item"><span class="v" id="erWave">&mdash;</span><span class="k">Waves</span></div>
			</div>
		</div>

		<div class="layers" id="windLayers">
			<label><input type="checkbox" id="tgParticles"> Animated</label>
			<label><input type="checkbox" id="tgWindColor"> Wind color</label>
			<label><input type="checkbox" id="tgArrows"> Wind barbs</label>
			<label><input type="checkbox" id="tgIso"> Isobars</label>
			<label><input type="checkbox" id="tgTemp"> Temp</label>
			<label><input type="checkbox" id="tgPrecip"> Precip</label>
			<label><input type="checkbox" id="tgWave"> Waves</label>
			<label><input type="checkbox" id="tgGulf"> Gulf Stream</label>
			<span class="hint">Zoom out for the Atlantic pattern &middot; temp shades the whole basin, precip the Sound &middot; Gulf Stream = ocean current, Florida&rarr;Newfoundland</span>
		</div>
		<div class="layers" id="radarLayers" style="display:none">
			<label><input type="checkbox" id="tgRadarWind"> Wind barbs</label>
			<span class="hint">Past hour &rarr; +18&nbsp;h forecast (NOAA HRRR) &middot; zoom out for the national radar</span>
		</div>
		<div class="layers" id="satLayers" style="display:none">
			<button class="sat-t on" data-sat="latest">Latest</button>
			<button class="sat-t" data-sat="18">18Z</button>
			<button class="sat-t" data-sat="12">12Z</button>
			<button class="sat-t" data-sat="06">06Z</button>
			<button class="sat-t" data-sat="00">00Z</button>
			<span class="hint">NOAA GOES-East Ch.13 infrared &middot; North Atlantic (NWS/OPC radiofax)</span>
		</div>
		<div class="layers" id="chartLayers" style="display:none">
			<select id="chartSel" class="chart-sel" aria-label="Chart">
				<optgroup label="Surface Analysis">
					<option value="PYAD10.gif">Surface Analysis &mdash; latest</option>
					<option value="PYAA10.gif">Surface Analysis &mdash; 00Z</option>
					<option value="PYAB01.gif">Surface Analysis &mdash; 06Z</option>
					<option value="PYAC01.gif">Surface Analysis &mdash; 12Z</option>
					<option value="PYAD01.gif">Surface Analysis &mdash; 18Z</option>
					<option value="PYAA12.gif">Surface Analysis, W Atlantic (Pt.2)</option>
				</optgroup>
				<optgroup label="Surface Forecast">
					<option value="PPAE10.gif">Surface Forecast &mdash; 24&nbsp;hr</option>
					<option value="QDTM10.gif">Surface Forecast &mdash; 48&nbsp;hr</option>
					<option value="PPAK98.gif">Surface Forecast &mdash; 72&nbsp;hr</option>
					<option value="PWAM99.gif">Surface Forecast &mdash; 96&nbsp;hr</option>
				</optgroup>
				<optgroup label="Wind &amp; Waves">
					<option value="PWAA90.gif">Wind/Wave Analysis &mdash; latest</option>
					<option value="PWAA88.gif">Wind/Wave Analysis &mdash; 00Z</option>
					<option value="PWAB88.gif">Wind/Wave Analysis &mdash; 06Z</option>
					<option value="PWAA89.gif">Wind/Wave Analysis &mdash; 12Z</option>
					<option value="PWAD89.gif">Wind/Wave Analysis &mdash; 18Z</option>
					<option value="PWAE10.gif">Wind/Wave Forecast &mdash; 24&nbsp;hr</option>
					<option value="PJAI10.gif">Wind/Wave Forecast &mdash; 48&nbsp;hr</option>
					<option value="PJAK88.gif">Wind/Wave Forecast &mdash; 72&nbsp;hr</option>
					<option value="PJAM98.gif">Wind/Wave Forecast &mdash; 96&nbsp;hr</option>
					<option value="PJAI20.gif">Wave Period &mdash; 48&nbsp;hr</option>
					<option value="PJAA99.gif">Sea State Analysis &mdash; 12Z</option>
				</optgroup>
				<optgroup label="500&nbsp;mb (upper air)">
					<option value="PPAA10.gif">500mb Analysis &mdash; latest</option>
					<option value="PPAA50.gif">500mb Analysis &mdash; 00Z</option>
					<option value="PPAA51.gif">500mb Analysis &mdash; 12Z</option>
					<option value="PPAE11.gif">500mb Forecast &mdash; 24&nbsp;hr</option>
					<option value="PPAI10.gif">500mb Forecast &mdash; 48&nbsp;hr</option>
					<option value="PPAM50.gif">500mb Forecast &mdash; 96&nbsp;hr</option>
				</optgroup>
				<optgroup label="Tropical Cyclone Danger Area">
					<option value="PWEK11.gif">Tropical Danger Area &mdash; latest</option>
					<option value="PWEK89.gif">Tropical Danger Area &mdash; VT03Z</option>
					<option value="PWEK90.gif">Tropical Danger Area &mdash; VT09Z</option>
					<option value="PWEK91.gif">Tropical Danger Area &mdash; VT15Z</option>
					<option value="PWEK88.gif">Tropical Danger Area &mdash; VT21Z</option>
				</optgroup>
			</select>
			<span class="hint">NWS/OPC marine surface, wind/wave &amp; 500&nbsp;mb charts (radiofax)</span>
		</div>

		<div class="legend" id="legend" style="display:none">0 kt <span class="sc" id="scale"></span> 40+ kt</div>

		<div class="ctrl" id="ctrlBar">
			<button id="play" title="Play/pause">&#9654;</button>
			<input type="range" id="slider" min="0" max="0" value="0" step="1" aria-label="Time">
			<div class="tlabel" id="tlabel">&mdash;<small>&nbsp;</small></div>
		</div>

		<div class="mapwrap">
			<div id="map"></div>
			<div class="satpanel" id="satPanel" style="display:none"><img id="satImg" alt="NOAA GOES-East infrared satellite — North Atlantic"></div>
			<div class="hovertip" id="hoverTip"></div>
			<div class="tiphelp" id="tipHelp">Click the map to pin a spot along your passage &mdash; its callout tracks the slider. Click a pin again to remove it.</div>
		</div>
		<div class="foot" id="foot">Wind, pressure &amp; temp from <a href="https://open-meteo.com" target="_blank" rel="noopener">Open-Meteo</a> (GFS-based). Radar close-up = HRRR precipitation (Open-Meteo); national view = NEXRAD mosaic + HRRR reflectivity (Iowa State IEM).</div>
	</div>

	<!-- GRIB DOWNLOAD -->
	<div class="card dl">
		<h2>Download GRIB</h2>
		<p>Grab the latest <strong>NOAA GFS</strong> forecast for the Sound as a GRIB2 file to open in your own navigation software &mdash; 10&nbsp;m wind, gusts and sea-level pressure, out to 48&nbsp;hours over the western Long Island Sound box.</p>
		<a class="dl-btn" href="<?php echo $oyc_ajax; ?>?action=oyc_grib" rel="nofollow">&#8681;&nbsp;Download GRIB (latest GFS &middot; 0&ndash;48&nbsp;h)</a>
		<p class="apps">Opens in OpenCPN, zyGrib / XyGrib, PredictWind Offshore, Expedition, qtVlm and most GRIB viewers. The first download after a new model run can take a few seconds while it is prepared.</p>
	</div>

	<p class="disclaimer">Forecasts are best treated as an opinion. Poseidon always has the final word.</p>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/leaflet-velocity@1.7.0/dist/leaflet-velocity.min.js"></script>
<script>
(function(){
"use strict";
var AJAX=<?php echo wp_json_encode( $oyc_ajax ); ?>;
var RAMP=['#8fc0dd','#5aa6d0','#3f93c9','#d9c07a','#e0a13f','#dd7f3a','#cf5638','#b23a2a','#8f2d20'];
document.getElementById('scale').innerHTML=RAMP.map(function(c){return '<i style="background:'+c+'"></i>';}).join('');
function spdColor(kt){var b=[[8,'#8fc0dd'],[11,'#5aa6d0'],[14,'#3f93c9'],[17,'#d9c07a'],[20,'#e0a13f'],[24,'#dd7f3a'],[28,'#cf5638'],[34,'#b23a2a'],[999,'#8f2d20']];for(var i=0;i<b.length;i++)if(kt<b[i][0])return b[i][1];}
var KT=1.94384;
var ER={lat:40.93717,lon:-73.70217}; /* Buoy 42 — the weather-readout point, same as the /weather/ board */
/* great-circle distance in nautical miles (haversine, R=3440.065 nm) */
function distNM(lat1,lon1,lat2,lon2){var R=3440.065,d=Math.PI/180,
	a=Math.sin((lat2-lat1)*d/2)*Math.sin((lat2-lat1)*d/2)+Math.cos(lat1*d)*Math.cos(lat2*d)*Math.sin((lon2-lon1)*d/2)*Math.sin((lon2-lon1)*d/2);
	return 2*R*Math.asin(Math.min(1,Math.sqrt(a)));}
function nmFromBuoy42(lat,lon){return distNM(ER.lat,ER.lon,lat,lon);}

/* ---- overlay color ramps (temp °F, precip in/3h) ---- */
var TEMP_COLS=['#3b4cc0','#7ba8dc','#93c47d','#ffd966','#e69138','#cc0000'];
var PRECIP_COLS=['#c9e8ff','#6db8ff','#3b7cff','#7d4bd6','#c0392b'];
var RADAR_COLS=['#8ec7ff','#4a9cf0','#2ecc71','#f1c40f','#e67e22','#e74c3c','#b03a7a'];
var TEMP_STOPS=[[0,[59,76,192]],[32,[123,168,220]],[45,[147,196,125]],[60,[255,217,102]],[75,[230,145,56]],[90,[204,0,0]]];
var PRECIP_STOPS=[[0.01,[201,232,255]],[0.05,[109,184,255]],[0.10,[59,124,255]],[0.25,[125,75,214]],[0.60,[192,57,43]]];
function lerpC(a,b,t){return [Math.round(a[0]+(b[0]-a[0])*t),Math.round(a[1]+(b[1]-a[1])*t),Math.round(a[2]+(b[2]-a[2])*t)];}
function rampColor(v,stops){if(v==null||isNaN(v))return null;if(v<=stops[0][0])return stops[0][1].concat(255);
	for(var i=1;i<stops.length;i++){if(v<=stops[i][0]){var t=(v-stops[i-1][0])/(stops[i][0]-stops[i-1][0]);return lerpC(stops[i-1][1],stops[i][1],t).concat(255);}}
	return stops[stops.length-1][1].concat(255);}
function tempColor(v){return rampColor(v,TEMP_STOPS);}
function precipColor(v){if(v==null||v<0.005)return null;return rampColor(v,PRECIP_STOPS);}
var WAVE_COLS=['#bfe9ef','#7fd0c8','#5bb98f','#e6d24a','#e08b3a','#cf5638','#8f2d6b'];
var WAVE_STOPS=[[0.3,[191,233,239]],[1,[127,208,200]],[2,[91,185,143]],[4,[230,210,74]],[7,[224,139,58]],[11,[143,45,107]]];
function waveColor(v){if(v==null||v<0.15)return null;return rampColor(v,WAVE_STOPS);}/* v in feet */
/* Gulf Stream ocean-current speed (kt). The whole basin has a diffuse ~0.3-0.7 kt
   surface drift; only the Gulf Stream runs fast (~1.5-4 kt). So the alpha ramps
   with speed — slow drift fades to nothing, the fast stream glows boldly — which
   isolates the stream instead of painting the whole ocean. */
var GS_COLS=['#3a97dc','#2f6fc4','#22b06a','#ecc233','#f0861c','#e03526','#a01f6b'];
var GS_STOPS=[[0.6,[58,151,220]],[1.2,[47,111,196]],[1.8,[34,176,106]],[2.5,[236,194,51]],[3.2,[240,134,28]],[4,[224,53,38]],[5,[160,31,107]]];
function gsColor(v){if(v==null||v<0.7)return null;
	var c=rampColor(v,GS_STOPS);                       /* [r,g,b,255] */
	var a=v>=1.8?1:(v-0.7)/1.1;                          /* 0 at 0.7 kt → full at 1.8 kt */
	return [c[0],c[1],c[2],Math.round(255*a)];}/* v in knots */
/* Windy-style continuous wind-speed fill (spectral ramp, kt) — painted under
   the white particle streaks so the whole speed field & lows read at a glance. */
var WINDFILL_COLS=['#3a6bb0','#54aeae','#79c58a','#c3de77','#f2e15a','#f4b04a','#ef7d43','#df4e3c','#b23150','#7d2b6b'];
var WINDFILL_STOPS=[[0,[58,107,176]],[5,[84,174,174]],[9,[121,197,138]],[13,[195,222,119]],[18,[242,225,90]],[23,[244,176,74]],[30,[239,125,67]],[40,[223,78,60]],[52,[178,49,80]],[65,[125,43,107]]];
function windFillColor(vms){if(vms==null||isNaN(vms))return null;return rampColor(vms*KT,WINDFILL_STOPS);}

/* ---------- grid factory ---------- */
function makeGrid(o){
	var g={name:o.name||'',LA1:o.LA1,LA2:o.LA2,LO1:o.LO1,LO2:o.LO2,DX:o.DX,DY:o.DY,gust:!!o.gust,loaded:false,loading:false};
	g.NY=Math.round((g.LA1-g.LA2)/g.DY)+1;g.NX=Math.round((g.LO2-g.LO1)/g.DX)+1;
	g.lats=[];g.lons=[];var i,j;
	for(i=0;i<g.NY;i++)g.lats.push(+(g.LA1-i*g.DY).toFixed(3));
	for(j=0;j<g.NX;j++)g.lons.push(+(g.LO1+j*g.DX).toFixed(3));
	g.LAT=[];g.LON=[];
	for(i=0;i<g.NY;i++)for(j=0;j<g.NX;j++){g.LAT.push(g.lats[i]);g.LON.push(g.lons[j]);}
	g.HDR={parameterCategory:2,nx:g.NX,ny:g.NY,lo1:g.LO1,lo2:g.LO2,la1:g.LA1,la2:g.LA2,dx:g.DX,dy:g.DY};
	g.SP=[];g.DR=[];g.PR=[];g.GU=[];g.TP=[];g.PP=[];g.WV=[];g.FRAMES=[];
	g.wvMap=null;g.wvLoaded=false;g.wvLoading=false;
	return g;
}
/* Regional grid — all of Long Island Sound east to Cape Cod / Nantucket, at a
   coarser 0.2 deg so the wider box stays a single Open-Meteo call. */
var LOCAL=makeGrid({name:'local',LA1:42.2,LA2:40.4,LO1:-74.2,LO2:-69.8,DX:0.2,DY:0.2,gust:true});
/* Whole Atlantic basin: Greenland/Iceland south to Brazil & southern Africa,
   Gulf/Caribbean west to the West-African coast. 3 deg keeps this large box
   light enough to fetch client-side while still resolving synoptic patterns. */
var BASIN=makeGrid({name:'basin',LA1:63,LA2:-27,LO1:-102,LO2:18,DX:3.0,DY:3.0,gust:false});
/* Gulf Stream corridor — Straits of Florida up to the Grand Banks / Newfoundland,
   1 deg. Carries ocean-current speed (kt) + direction, not wind. */
var GS=makeGrid({name:'gs',LA1:46,LA2:24,LO1:-80,LO2:-48,DX:1.0,DY:1.0});
GS.SPD=[];GS.DIR=[];GS.gsMap=null;

var TIMES=[],NOWI=0;               /* master 3-hourly time axis (shared) */
function frame(g,ti){
	var u=new Array(g.LAT.length),v=new Array(g.LAT.length);
	for(var k=0;k<g.LAT.length;k++){var sp=g.SP[k][ti]||0,dr=(g.DR[k][ti]||0)*Math.PI/180;u[k]=-sp*Math.sin(dr);v[k]=-sp*Math.cos(dr);}
	return [{header:Object.assign({parameterNumber:2,refTime:TIMES[ti],forecastTime:0},g.HDR),data:u},
			{header:Object.assign({parameterNumber:3,refTime:TIMES[ti],forecastTime:0},g.HDR),data:v}];
}
function buildFrames(g){g.FRAMES=[];for(var t=0;t<TIMES.length;t++)g.FRAMES.push(frame(g,t));}

/* bilinear sample of a grid at (lat,lon) for time index ti -> {kt,dir,mb,gust} or null */
function sampleGrid(g,lat,lon,ti){
	if(!g||!g.loaded)return null;
	var fr=(g.LA1-lat)/g.DY,fc=(lon-g.LO1)/g.DX;
	if(fr<-0.001||fr>g.NY-1+0.001||fc<-0.001||fc>g.NX-1+0.001)return null;
	var r0=Math.max(0,Math.floor(fr)),c0=Math.max(0,Math.floor(fc));
	var r1=Math.min(r0+1,g.NY-1),c1=Math.min(c0+1,g.NX-1);
	var dr=fr-r0,dc=fc-c0;
	function at(arr,r,c,tx){return (arr[r*g.NX+c]||[])[tx==null?ti:tx];}
	function bil(arr,tx){var a=at(arr,r0,c0,tx),b=at(arr,r0,c1,tx),c_=at(arr,r1,c0,tx),d=at(arr,r1,c1,tx);
		if(a==null||b==null||c_==null||d==null)return null;
		return a*(1-dr)*(1-dc)+b*(1-dr)*dc+c_*dr*(1-dc)+d*dr*dc;}
	function uv(r,c){var sp=at(g.SP,r,c)||0,d=(at(g.DR,r,c)||0)*Math.PI/180;return [-sp*Math.sin(d),-sp*Math.cos(d)];}
	var q00=uv(r0,c0),q01=uv(r0,c1),q10=uv(r1,c0),q11=uv(r1,c1);
	var uu=q00[0]*(1-dr)*(1-dc)+q01[0]*(1-dr)*dc+q10[0]*dr*(1-dc)+q11[0]*dr*dc;
	var vv=q00[1]*(1-dr)*(1-dc)+q01[1]*(1-dr)*dc+q10[1]*dr*(1-dc)+q11[1]*dr*dc;
	var sp=Math.sqrt(uu*uu+vv*vv);
	var dir=(Math.atan2(-uu,-vv)*180/Math.PI+360)%360;
	var mb=bil(g.PR);var gu=g.gust?bil(g.GU):null;
	var tp=(g.TP&&g.TP.length)?bil(g.TP):null;var pp=(g.PP&&g.PP.length)?bil(g.PP):null;
	var wti=(g.wvMap&&g.wvMap[ti]!=null)?g.wvMap[ti]:ti;
	/* Waves: tolerant bilinear — average only the non-null (water) corners, so a
	   coastal point whose grid box also touches land still reads a value that
	   matches the /weather/ board's exact-point marine query (strict bil would
	   return null and show "—"). */
	var wv=null;
	if(g.WV&&g.WV.length){var ws=[at(g.WV,r0,c0,wti),at(g.WV,r0,c1,wti),at(g.WV,r1,c0,wti),at(g.WV,r1,c1,wti)].filter(function(x){return x!=null;});
		if(ws.length)wv=ws.reduce(function(a,b){return a+b;},0)/ws.length;}
	return {kt:sp*KT,dir:dir,mb:mb==null?null:mb,gust:gu==null?null:gu*KT,tempF:tp,precip:pp,waveFt:wv==null?null:wv*3.28084};
}
function sampleBest(lat,lon,ti){
	if(lat<=LOCAL.LA1&&lat>=LOCAL.LA2&&lon>=LOCAL.LO1&&lon<=LOCAL.LO2){var s=sampleGrid(LOCAL,lat,lon,ti);if(s)return s;}
	return sampleGrid(BASIN,lat,lon,ti);
}
var CARD=['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
function card(d){return CARD[Math.round(d/22.5)%16];}
function fmt(iso){var d=new Date(iso);return {big:(d.getHours()%12||12)+' '+(d.getHours()>=12?'PM':'AM'),small:d.toLocaleDateString('en-US',{weekday:'short',month:'short',day:'numeric'})};}

/* ---------- map ---------- */
var map=L.map('map',{worldCopyJump:true}).setView([40.915,-73.68],12);
/* Light base (OSM). The wind-speed fill is kept translucent so it reads as a
   tint over the map, with dark barbs and dark flow lines legible on top. */
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OpenStreetMap',maxZoom:19}).addTo(map);
/* default view: Mamaroneck harbor / western Sound (set at map init above) */

var arrowsLayer=L.layerGroup(),isoLayer=L.layerGroup(),hlLayer=L.layerGroup();
var vl=null,activeGrid=LOCAL,mode='wind',curTi=0,playing=false,timer=null;
var slider=document.getElementById('slider'),tlabel=document.getElementById('tlabel'),playBtn=document.getElementById('play');

/* ---------- Buoy 42 strip + labelled marker (the weather-readout point) ---------- */
L.marker([ER.lat,ER.lon],{icon:L.divIcon({className:'b42-mk',html:'<div class="b42-ic"><i class="b42-dot"></i><span class="b42-lbl">Buoy 42</span></div>',iconSize:[0,0]}),keyboard:false,zIndexOffset:1000}).addTo(map);
/* Execution Rock — kept as a reference point on the map (not the readout) */
L.marker([40.8833,-73.7283],{icon:L.divIcon({className:'erref-mk',html:'<div class="erref-ic"><i class="erref-dot"></i><span class="erref-lbl">Execution Rock</span></div>',iconSize:[0,0]}),keyboard:false,zIndexOffset:900}).addTo(map);

/* Atlantic passage landmarks — visible once you zoom out to the basin. */
var PLACES=[
	{n:'Bermuda',lat:32.30,lon:-64.78},
	{n:'Azores',lat:37.74,lon:-25.68},
	{n:'Canary Is.',lat:28.30,lon:-15.80},
	{n:'Cape Verde',lat:16.00,lon:-24.00}
];
var placeLayer=L.layerGroup();
PLACES.forEach(function(p){
	L.marker([p.lat,p.lon],{icon:L.divIcon({className:'map-label',html:'<span class="ml-dot"></span>'+p.n,iconSize:[0,0],iconAnchor:[0,0]}),interactive:false,keyboard:false}).addTo(placeLayer);
});
placeLayer.addTo(map);

/* Home port — Orienta Yacht Club, East Basin of Mamaroneck Harbor (325 E Boston
   Post Rd, between Derecktor and McMichael). Visible in every view. */
L.marker([40.9486,-73.7296],{icon:L.divIcon({className:'oyc-mk',html:'<div class="oyc-ic"><i class="oyc-dot"></i><span class="oyc-lbl">OYC</span></div>',iconSize:[0,0]}),keyboard:false,zIndexOffset:1000})
	.addTo(map).bindTooltip('Orienta Yacht Club · Mamaroneck Harbor',{direction:'top',offset:[6,-6]});

/* Active Atlantic named storms from NOAA/NHC (server proxy oyc_storms) — a
   cyclone marker per storm, coloured by class; visible once you zoom out to
   the basin. Refreshed on load. */
var stormLayer=L.layerGroup().addTo(map),trackLayer=L.layerGroup().addTo(map),STORMS=[];
function stormColor(cls){return cls==='HU'?'#c0392b':(cls==='TS'?'#e67e22':'#4a8fb5');}
/* epoch (ms) of the frame the slider is currently on, in either mode */
function curEpoch(){if(mode==='radar'){return (RV.frames.length&&RV.frames[RV.idx])?Date.now()+RV.frames[RV.idx].off*60000:Date.now();}
	return (TIMES.length&&TIMES[curTi])?new Date(TIMES[curTi]).getTime():Date.now();}
/* storm position at epoch tE, linearly interpolated along the forecast track;
   dim=true outside the forecast window (before the current fix, or past +120h) */
function stormAt(pts,tE){if(!pts||!pts.length)return null;
	if(tE<=pts[0].t)return{lat:pts[0].lat,lon:pts[0].lon,dim:tE<pts[0].t-36e5};
	var last=pts[pts.length-1];if(tE>=last.t)return{lat:last.lat,lon:last.lon,dim:true};
	for(var i=1;i<pts.length;i++){if(tE<=pts[i].t){var a=pts[i-1],b=pts[i],f=(b.t>a.t)?(tE-a.t)/(b.t-a.t):0;
		return{lat:a.lat+(b.lat-a.lat)*f,lon:a.lon+(b.lon-a.lon)*f,dim:false};}}
	return{lat:last.lat,lon:last.lon,dim:true};}
/* glide every storm marker to where it is forecast to be at time tE */
function updateStormsAt(tE){if(!tE)return;STORMS.forEach(function(st){var p=stormAt(st.pts,tE);if(!p)return;
	st.marker.setLatLng([p.lat,p.lon]);var el=st.marker.getElement();if(el)el.style.opacity=p.dim?'0.5':'1';});}
function loadStorms(){
	fetchT(AJAX+'?action=oyc_storms',9000).then(function(r){return r.json();}).then(function(list){
		stormLayer.clearLayers();trackLayer.clearLayers();STORMS=[];
		(list||[]).forEach(function(s){
			var col=stormColor(s.cls),lbl=s.name+' · '+s.cls+(s.kt!=null?' '+s.kt+' kt':'');
			var pts=(s.track&&s.track.length)?s.track.map(function(p){return{t:+p.t,lat:+p.lat,lon:+p.lon};})
				:[{t:Date.now(),lat:s.lat,lon:s.lon}];
			if(pts.length>1){ /* dotted forecast track + a dot at each advisory position */
				L.polyline(pts.map(function(p){return[p.lat,p.lon];}),{color:col,weight:2,opacity:.75,dashArray:'3 5',interactive:false}).addTo(trackLayer);
				pts.forEach(function(p,i){if(i)L.circleMarker([p.lat,p.lon],{radius:2.5,color:'#fff',weight:1,fillColor:col,fillOpacity:.9,interactive:false}).addTo(trackLayer);});
			}
			var html='<div class="storm-ic"><span class="storm-sym">&#127744;</span><span class="storm-lbl" style="background:'+col+'">'+lbl+'</span></div>';
			var m=L.marker([pts[0].lat,pts[0].lon],{icon:L.divIcon({className:'storm-mk',html:html,iconSize:[0,0]}),keyboard:false,zIndexOffset:1100}).addTo(stormLayer);
			/* tooltip drops BELOW the icon so it never covers the name label above it */
			m.bindTooltip(s.name+' ('+s.cls+') · '+(s.kt!=null?s.kt+' kt':'')+(s.mb!=null?' · '+s.mb+' mb':'')+(s.dir?' · moving '+s.dir+' '+s.spd+' kt':''),{direction:'bottom',offset:[0,14]});
			STORMS.push({marker:m,pts:pts});
		});
		updateStormsAt(curEpoch());
	}).catch(function(){});
}
loadStorms();
loadERFC(); /* Execution Rock point-forecast series (matches the Marine Forecast board) */
function setER(s){if(!s)return;
	document.getElementById('erWind').textContent=Math.round(s.kt)+' kt';
	document.getElementById('erGust').textContent=s.gust!=null?Math.round(s.gust)+' kt':'—';
	document.getElementById('erDir').textContent=card(s.dir)+' '+Math.round(s.dir)+'°';
	document.getElementById('erPres').textContent=s.mb!=null?Math.round(s.mb)+' mb':'—';
	document.getElementById('erTemp').textContent=s.tempF!=null?Math.round(s.tempF)+'°F':'—';
	/* precip: probability % from the point forecast (matches the Marine Forecast
	   board); falls back to the grid's amount in inches if only that is available */
	document.getElementById('erPrecip').textContent=(s.pop!=null?Math.round(s.pop)+'%':(s.precip!=null?s.precip.toFixed(2)+' in':'—'));
	document.getElementById('erWave').textContent=s.waveFt!=null?s.waveFt.toFixed(1)+' ft':'—';}

/* Execution Rock point-forecast series — the SAME cached exact-point, hourly,
   BLEND (best_match) data the Marine Forecast table uses, so the map's ER
   readout agrees with the board instead of drifting from the coarse
   0.2°/3-hourly wind grid it visualises. */
/* ---- point-forecast series (shared by the ER readout and passage pins) ----
   Build {t,kt,gust,dir,mb,tempF,pop,waveFt} from a {wx,marine} response. Handles
   both the best_match-suffixed keys from the multi-model board feed and the plain
   keys from a single-point fetch. */
function fcKey(h,base){var k=base+'_best_match';return h[k]!==undefined?k:base;}
function fcSeriesFrom(res){
	if(!res||!res.ok||!res.wx||!res.wx.hourly)return null;
	var wx=res.wx,h=wx.hourly,off=(wx.utc_offset_seconds||0)*1000;
	var t=h.time.map(function(s){return Date.parse(s+':00Z')-off;});
	var wave={};
	if(res.marine&&res.marine.hourly&&res.marine.hourly.wave_height){var mh=res.marine.hourly;mh.time.forEach(function(tt,i){wave[tt]=mh.wave_height[i];});}
	return {t:t, kt:h[fcKey(h,'wind_speed_10m')], gust:h[fcKey(h,'wind_gusts_10m')], dir:h[fcKey(h,'wind_direction_10m')],
		mb:h[fcKey(h,'pressure_msl')], tempF:h[fcKey(h,'temperature_2m')], pop:h[fcKey(h,'precipitation_probability')],
		waveFt:h.time.map(function(s){return wave[s]!=null?wave[s]:null;})};
}
function fcAt(S,i){return {kt:S.kt[i],gust:S.gust[i],dir:S.dir[i],mb:S.mb[i],tempF:S.tempF[i],pop:S.pop?S.pop[i]:null,waveFt:S.waveFt[i]};}
function fcInterp(S,ep){if(!S||!S.t.length)return null;var T=S.t,n=T.length;
	if(ep<=T[0])return fcAt(S,0);
	if(ep>=T[n-1])return fcAt(S,n-1);
	for(var i=1;i<n;i++){if(ep<=T[i]){var fr=(ep-T[i-1])/(T[i]-T[i-1]),a=fcAt(S,i-1),b=fcAt(S,i);
		var li=function(x,y){return (x!=null&&y!=null)?x+(y-x)*fr:x;};
		return {kt:li(a.kt,b.kt),gust:li(a.gust,b.gust),dir:a.dir,mb:li(a.mb,b.mb),tempF:li(a.tempF,b.tempF),pop:li(a.pop,b.pop),waveFt:li(a.waveFt,b.waveFt)};}}
	return fcAt(S,n-1);
}
function fcNearest(S,ep){if(!S||!S.t.length)return null;var T=S.t,best=0,bd=1e15;for(var i=0;i<T.length;i++){var dd=Math.abs(T[i]-ep);if(dd<bd){bd=dd;best=i;}}return fcAt(S,best);}
/* sample a series at a slider frame: snap to the nearest actual hour at the live
   "now" frame (matches the board exactly), interpolate otherwise */
function fcForFrame(S,epoch,isNow){return isNow?fcNearest(S,Date.now()):fcInterp(S,epoch);}

var ERFC=null;
function loadERFC(){
	fetchT(AJAX+'?action=oyc_marine_fc',12000).then(function(r){return r.json();}).then(function(res){
		var S=fcSeriesFrom(res);if(!S)return;ERFC=S;
		/* refresh the readout for the frame the slider is on right now */
		if(mode==='radar'){var fr=RV.frames[RV.idx];setER(erForFrame(curEpoch(),!!(fr&&fr.off===0)));}
		else setER(erForFrame(new Date(TIMES[curTi]).getTime(), curTi===NOWI));
	}).catch(function(){});
}
/* ER readout prefers the exact-point forecast; falls back to the grid until it loads */
function erForFrame(epoch,isNow){var s=ERFC?fcForFrame(ERFC,epoch,isNow):null;return s||erAtEpoch(isNow?Date.now():epoch);}
function updateER(ti){setER(erForFrame(new Date(TIMES[ti]).getTime(), ti===NOWI));}
function erAtEpoch(epoch){
	if(!LOCAL.loaded||TIMES.length<2)return null;
	var e0=new Date(TIMES[0]).getTime(),step=(new Date(TIMES[1]).getTime()-e0);
	var f=(epoch-e0)/step;if(f<0)f=0;if(f>TIMES.length-1)f=TIMES.length-1;
	var i0=Math.floor(f),i1=Math.min(i0+1,TIMES.length-1),fr=f-i0;
	var a=sampleGrid(LOCAL,ER.lat,ER.lon,i0),b=sampleGrid(LOCAL,ER.lat,ER.lon,i1);
	if(!a||!b)return a||b;
	function li(x,y){return (x!=null&&y!=null)?x+(y-x)*fr:x;}
	return {kt:a.kt+(b.kt-a.kt)*fr,dir:a.dir,mb:li(a.mb,b.mb),gust:li(a.gust,b.gust),tempF:li(a.tempF,b.tempF),precip:li(a.precip,b.precip),waveFt:li(a.waveFt,b.waveFt)};
}

/* Reading for a clicked/hovered point: AT Execution Rock use the exact point
   forecast (so a pin there matches the ER readout and the Marine Forecast board);
   anywhere else use the grid field the map actually draws. Keeps every per-point
   readout — ER bar, passage pins, hover — consistent across all tabs. */
function nearER(lat,lon){return Math.abs(lat-ER.lat)<0.03&&Math.abs(lon-ER.lon)<0.03;}
function readPoint(lat,lon,ti){
	if(ERFC&&nearER(lat,lon)&&TIMES[ti]!=null){var s=erForFrame(new Date(TIMES[ti]).getTime(),ti===NOWI);if(s)return s;}
	return sampleBest(lat,lon,ti);
}

/* ---------- pinned passage points ----------
   Each pin fetches its OWN exact-point forecast (same method as the Marine
   Forecast board), so its readout is accurate at that spot and a pin dropped on
   Execution Rock exactly matches the ER headline. Until that loads we show the
   grid field as a placeholder. */
var pins=[];
function pinReading(lat,lon,ti,fc){
	if(fc&&TIMES[ti]!=null){var s=fcForFrame(fc,new Date(TIMES[ti]).getTime(),ti===NOWI);if(s)return s;}
	return readPoint(lat,lon,ti);
}
function pinContent(lat,lon,ti,fc){
	var s=pinReading(lat,lon,ti,fc);
	var ll=lat.toFixed(4)+', '+lon.toFixed(4);
	var dist='<div class="co-dist">'+nmFromBuoy42(lat,lon).toFixed(1)+' nm from Buoy 42</div>';
	if(!s)return '<div class="pin-co"><div class="co-t">Passage point<span class="co-ll">'+ll+'</span></div>'+dist+'<div class="co-row">No data here</div><div class="co-x">tap × to remove</div></div>';
	var row='<div class="co-row"><span><span class="v">'+Math.round(s.kt)+'</span> <span class="k">kt</span></span>'
		+(s.gust!=null?'<span><span class="v">'+Math.round(s.gust)+'</span> <span class="k">gust</span></span>':'')
		+'<span><span class="v">'+card(s.dir)+'</span> <span class="k">'+Math.round(s.dir)+'°</span></span>'
		+(s.mb!=null?'<span><span class="v">'+Math.round(s.mb)+'</span> <span class="k">mb</span></span>':'')+'</div>';
	/* precip: probability % from the point forecast, else grid amount in inches */
	var pcp=(s.pop!=null)?('<span><span class="v">'+Math.round(s.pop)+'%</span> <span class="k">precip</span></span>')
		:(s.precip!=null?'<span><span class="v">'+s.precip.toFixed(2)+'</span> <span class="k">in</span></span>':'');
	var row2='<div class="co-row">'+(s.tempF!=null?'<span><span class="v">'+Math.round(s.tempF)+'°F</span> <span class="k">air</span></span>':'')
		+pcp
		+(s.waveFt!=null?'<span><span class="v">'+s.waveFt.toFixed(1)+'</span> <span class="k">ft sea</span></span>':'')+'</div>';
	return '<div class="pin-co"><div class="co-t">Passage point<span class="co-ll">'+ll+'</span></div>'+dist+row+row2+'<div class="co-x">tap × to remove</div></div>';
}
/* fetch a pin's exact-point forecast (cached server proxy) and re-render it */
function loadPinFC(pin){
	fetchT(AJAX+'?action=oyc_point_fc&lat='+pin.lat.toFixed(3)+'&lon='+pin.lon.toFixed(3),12000)
		.then(function(r){return r.json();}).then(function(res){
			var S=fcSeriesFrom(res);if(!S||pin._gone)return;pin.fc=S;
			pin.popup.setContent(pinContent(pin.lat,pin.lon,curTi,pin.fc));
		}).catch(function(){});
}
function addPin(lat,lon){
	var m=L.circleMarker([lat,lon],{radius:6,color:'#0b2a4a',weight:2,fillColor:'#1583cf',fillOpacity:1}).addTo(map);
	var p=L.popup({autoClose:false,closeOnClick:false,closeButton:true,autoPan:false,className:'pin-pop'}).setLatLng([lat,lon]);
	var pin={marker:m,popup:p,lat:lat,lon:lon,fc:null};
	p.setContent(pinContent(lat,lon,curTi,null)); /* grid placeholder until the point forecast arrives */
	m.bindPopup(p);m.openPopup();
	m.on('click',function(e){L.DomEvent.stop(e);removePin(pin);});
	m.on('popupclose',function(){removePin(pin);}); /* the corner × removes the point too */
	pins.push(pin);
	loadPinFC(pin);
}
function removePin(pin){if(pin._gone)return;pin._gone=true;map.removeLayer(pin.marker);map.closePopup(pin.popup);pins=pins.filter(function(x){return x!==pin;});}
function updatePins(ti){for(var i=0;i<pins.length;i++)pins[i].popup.setContent(pinContent(pins[i].lat,pins[i].lon,ti,pins[i].fc));}
map.on('click',function(e){if(mode==='radar')return;addPin(e.latlng.lat,((e.latlng.lng+540)%360)-180);});

/* ---------- live NDBC buoy markers (shown in the Wave view) ---------- */
var buoyLayer=L.layerGroup(),buoysLoaded=false;
function loadBuoys(cb){
	if(buoysLoaded){cb&&cb();return;}
	fetchT(AJAX+'?action=oyc_buoys',9000).then(function(r){return r.json();}).then(function(list){
		buoyLayer.clearLayers();
		(list||[]).forEach(function(b){
			var lbl=b.ft.toFixed(1)+' ft'+(b.dpd?' @ '+Math.round(b.dpd)+'s':'')+'<br>'+b.station+' · '+b.ageMin+'m ago';
			var html='<div class="buoy-ic">&#9670;<span class="buoy-lbl">'+lbl+'</span></div>';
			L.marker([b.lat,b.lon],{icon:L.divIcon({className:'buoy-mk',html:html,iconSize:[0,0]}),interactive:false,keyboard:false}).addTo(buoyLayer);
		});
		buoysLoaded=true;cb&&cb();
	}).catch(function(){buoysLoaded=true;/* offline / hauled out — no markers */});
}

/* ---------- overlays (arrows + isobars) ---------- */
/* GRIB / station-model wind barbs: staff points into the wind (toward the
   direction it comes from); pennant=50 kt, full barb=10 kt, half barb=5 kt,
   open circle=calm. Rounded to the nearest 5 kt like a nav-grade plot. */
function barbSVG(kt,col){
	var L=26,step=4.2,FB=11,HB=6.5,PB=9,out='';
	var spd=Math.round(kt/5)*5;
	if(spd<3)return '<circle cx="0" cy="0" r="3.6" fill="none" stroke="'+col+'" stroke-width="1.6"/>';
	out+='<line x1="0" y1="0" x2="0" y2="'+(-L)+'" stroke="'+col+'" stroke-width="1.8"/>';
	var pen=Math.floor(spd/50);spd-=pen*50;var full=Math.floor(spd/10);spd-=full*10;var half=Math.floor(spd/5);
	var pos=-L;
	for(var i=0;i<pen;i++){out+='<path d="M0,'+pos+' L'+PB+','+(pos+step*0.6).toFixed(1)+' L0,'+(pos+step*1.2).toFixed(1)+' Z" fill="'+col+'"/>';pos+=step*1.5;}
	if(pen>0)pos+=step*0.3;
	for(i=0;i<full;i++){out+='<line x1="0" y1="'+pos.toFixed(1)+'" x2="'+FB+'" y2="'+(pos-4).toFixed(1)+'" stroke="'+col+'" stroke-width="1.8"/>';pos+=step;}
	for(i=0;i<half;i++){if(full===0&&pen===0&&i===0)pos+=step;out+='<line x1="0" y1="'+pos.toFixed(1)+'" x2="'+HB+'" y2="'+(pos-2.3).toFixed(1)+'" stroke="'+col+'" stroke-width="1.8"/>';pos+=step;}
	return out;
}
/* Barbs on a fixed SCREEN lattice (not one-per-grid-cell), sampled by bilinear
   interpolation — so the view stays filled with barbs at any zoom (more appear
   as you zoom in) and redraws on pan/zoom. */
function drawBarbs(){arrowsLayer.clearLayers();
	var g=activeGrid;if(!g||!g.loaded)return;
	var ti=(mode==='radar')?Math.min(NOWI,Math.max(0,TIMES.length-1)):curTi;
	var size=map.getSize(),step=72,s0=Math.round(step/2);
	for(var py=s0;py<size.y;py+=step)for(var px=s0;px<size.x;px+=step){
		var ll=map.containerPointToLatLng([px,py]),lon=((ll.lng+540)%360)-180;
		var s=sampleBest(ll.lat,lon,ti);if(!s||s.kt<3)continue;/* skip calm — no grid of circles */
		var html='<svg width="46" height="42" viewBox="-23 -34 46 42" style="overflow:visible"><g transform="rotate('+Math.round(s.dir)+')">'+barbSVG(s.kt,'#0b2a4a')+'</g></svg>';
		L.marker([ll.lat,lon],{icon:L.divIcon({className:'barb-mk',html:html,iconSize:[46,42],iconAnchor:[23,34]}),interactive:false,keyboard:false}).addTo(arrowsLayer);
	}}
function drawIso(g,ti,step){isoLayer.clearLayers();
	var NY=g.NY,NX=g.NX,P=[];for(var r=0;r<NY;r++){P[r]=[];for(var c=0;c<NX;c++)P[r][c]=g.PR[r*NX+c][ti];}
	var mn=1e9,mx=-1e9;for(r=0;r<NY;r++)for(c=0;c<NX;c++){if(P[r][c]<mn)mn=P[r][c];if(P[r][c]>mx)mx=P[r][c];}
	function lerp(a,b,t,axa,axb){return axa+(axb-axa)*((t-a)/(b-a));}
	for(var thr=Math.ceil(mn/step)*step;thr<=mx;thr+=step){
		for(r=0;r<NY-1;r++)for(c=0;c<NX-1;c++){
			var tl=P[r][c],tr=P[r][c+1],br=P[r+1][c+1],bl=P[r+1][c],pts=[];
			if((tl-thr)*(tr-thr)<0)pts.push([g.lats[r],lerp(tl,tr,thr,g.lons[c],g.lons[c+1])]);
			if((tr-thr)*(br-thr)<0)pts.push([lerp(tr,br,thr,g.lats[r],g.lats[r+1]),g.lons[c+1]]);
			if((bl-thr)*(br-thr)<0)pts.push([g.lats[r+1],lerp(bl,br,thr,g.lons[c],g.lons[c+1])]);
			if((tl-thr)*(bl-thr)<0)pts.push([lerp(tl,bl,thr,g.lats[r],g.lats[r+1]),g.lons[c]]);
			if(pts.length>=2){
				L.polyline([pts[0],pts[1]],{color:'#37506b',weight:1.2,opacity:.8,interactive:false}).addTo(isoLayer);
				if(pts.length===4)L.polyline([pts[2],pts[3]],{color:'#37506b',weight:1.2,opacity:.8,interactive:false}).addTo(isoLayer);
				if(r%3===0&&c===Math.floor(NX/2))L.marker(pts[0],{icon:L.divIcon({className:'iso-lbl',html:Math.round(thr)+'',iconSize:[26,12]}),interactive:false}).addTo(isoLayer);
			}
		}
	}}
function refreshOverlays(){var g=activeGrid;
	if(document.getElementById('tgArrows').checked)drawBarbs();
	if(document.getElementById('tgIso').checked)drawIso(g,curTi,g===BASIN?4:1);}

/* Mark pressure centres (H / L) at local maxima / minima of the MSLP field.
   A cell must be the strict extremum over a (2R+1)² window AND differ from the
   window mean by >= THR mb, which suppresses noise on the near-uniform regional
   grid and leaves the real synoptic centres on the basin view. */
function drawHL(g,ti){hlLayer.clearLayers();
	var NY=g.NY,NX=g.NX,R=2,THR=1.6;
	function P(r,c){return g.PR[r*NX+c][ti];}
	for(var r=R;r<NY-R;r++)for(var c=R;c<NX-R;c++){
		var v=P(r,c),isL=true,isH=true,sum=0,n=0;
		for(var dr=-R;dr<=R&&(isL||isH);dr++)for(var dc=-R;dc<=R;dc++){if(!dr&&!dc)continue;var nv=P(r+dr,c+dc);if(nv==null)continue;sum+=nv;n++;if(nv<=v)isL=false;if(nv>=v)isH=false;}
		if((isL||isH)&&n){var mean=sum/n;if(Math.abs(v-mean)<THR)continue;
			var lo=isL,html='<div class="hl '+(lo?'lo':'hi')+'"><b>'+(lo?'L':'H')+'</b><span>'+Math.round(v)+'</span></div>';
			L.marker([g.lats[r],g.lons[c]],{icon:L.divIcon({className:'hl-mk',html:html,iconSize:[0,0]}),interactive:false,keyboard:false}).addTo(hlLayer);
		}
	}}

/* ---- temp / precip color-field overlays (rendered from the active grid as a
   small canvas the browser upsamples into a smooth field) ---- */
var fieldMode=null,fieldOverlay=null;
/* Which color field to paint: the Temp/Precip TAB forces it; in Wind view the
   Temp/Precip checkboxes drive it as an overlay; Radar shows none. */
function effectiveField(){
	if(mode==='temp')return 'temp';
	if(mode==='precip')return 'precip';
	if(mode==='wave')return 'wave';
	if(mode==='wind'){if(fieldMode)return fieldMode;return document.getElementById('tgWindColor').checked?'windspd':null;}
	return null;
}
function fieldDataURL(g,arr,ti,ramp){
	/* Supersample the grid with bilinear interpolation into a larger canvas so the
	   fill is smooth (not a blocky mosaic), and feather the outer ~1.5 cells to
	   transparent so the grid-box edge never shows as a hard rectangle. Nulls
	   (land / no data) are dropped from the interpolation weights. */
	var NX=g.NX,NY=g.NY,K=6,W=(NX-1)*K+1,H=(NY-1)*K+1;
	var cv=document.createElement('canvas');cv.width=W;cv.height=H;var ctx=cv.getContext('2d');
	var img=ctx.createImageData(W,H),d=img.data;
	function val(r,c){return (arr[r*NX+c]||[])[ti];}
	for(var y=0;y<H;y++){var fr=y/K,r0=Math.floor(fr),r1=Math.min(r0+1,NY-1),dry=fr-r0;
		for(var x=0;x<W;x++){var fc=x/K,c0=Math.floor(fc),c1=Math.min(c0+1,NX-1),dcx=fc-c0;
			var a=val(r0,c0),b=val(r0,c1),cc=val(r1,c0),dd=val(r1,c1);
			var s=0,w=0,wt;
			if(a!=null){wt=(1-dry)*(1-dcx);s+=a*wt;w+=wt;}
			if(b!=null){wt=(1-dry)*dcx;s+=b*wt;w+=wt;}
			if(cc!=null){wt=dry*(1-dcx);s+=cc*wt;w+=wt;}
			if(dd!=null){wt=dry*dcx;s+=dd*wt;w+=wt;}
			var i=(y*W+x)*4;
			var col=w>0?ramp(s/w):null;
			if(col){var ed=Math.min(fr,fc,(NY-1)-fr,(NX-1)-fc),fade=ed>=1.5?1:(0.15+0.85*ed/1.5);
				d[i]=col[0];d[i+1]=col[1];d[i+2]=col[2];d[i+3]=Math.round(col[3]*fade*(w<0.999?w:1));}else d[i+3]=0;
		}}
	ctx.putImageData(img,0,0);return cv.toDataURL();
}
function clearField(){if(fieldOverlay){map.removeLayer(fieldOverlay);fieldOverlay=null;}}
function drawField(){
	var ef=effectiveField();
	if(!ef){clearField();return;}
	var g=activeGrid;
	if(ef==='wave'&&!g.wvLoaded){clearField();loadWaves(g,function(){if(effectiveField()==='wave')showWind(curTi);});return;}
	var arr,ramp,op;
	if(ef==='temp'){arr=g.TP;ramp=tempColor;op=0.55;}
	else if(ef==='precip'){arr=(g===LOCAL?LOCAL.PP:null);ramp=precipColor;op=0.6;}
	else if(ef==='wave'){arr=g.WV;ramp=waveColor;op=0.62;}
	else{arr=g.SP;ramp=windFillColor;op=0.42;}/* windspd — translucent wind-speed tint under dark barbs/streaks */
	var fti=(ef==='wave'&&g.wvMap&&g.wvMap[curTi]!=null)?g.wvMap[curTi]:curTi;
	if(!g.loaded||!arr||!arr.length){clearField();return;}
	var url=fieldDataURL(g,arr,fti,ramp),bounds=[[g.LA2,g.LO1],[g.LA1,g.LO2]];
	if(fieldOverlay){fieldOverlay.setBounds(bounds);fieldOverlay.setUrl(url);fieldOverlay.setOpacity(op);}
	else{fieldOverlay=L.imageOverlay(url,bounds,{opacity:op,interactive:false});fieldOverlay.addTo(map);if(fieldOverlay.setZIndex)fieldOverlay.setZIndex(350);}
}
function setLegend(l0,l1,cols){var lg=document.getElementById('legend');lg.className='legend';lg.style.display='';
	lg.innerHTML=l0+' <span class="sc">'+cols.map(function(c){return '<i style="background:'+c+'"></i>';}).join('')+'</span> '+l1;}
function updateLegend(){
	if(mode==='radar')return setLegend('light','heavy',RADAR_COLS);
	var ef=effectiveField();
	if(!ef){ /* no color field — show the Gulf Stream current scale if it's the active overlay */
		document.getElementById('legend').style.display='none';return; /* Gulf Stream band is self-explanatory via the hint + wall lines */}
	if(ef==='temp')return setLegend('0°F','90°F',TEMP_COLS);
	if(ef==='precip')return setLegend('0 in','0.6+ in',PRECIP_COLS);
	if(ef==='wave')return setLegend('calm','12+ ft',WAVE_COLS);
	if(ef==='windspd')return setLegend('0 kt','65+ kt',WINDFILL_COLS);
	setLegend('0 kt','40+ kt',RAMP);}

/* ---------- Gulf Stream (ocean current) overlay ----------
   Ocean-current speed + direction over the Florida→Newfoundland corridor, from
   Open-Meteo Marine (cached proxy). A translucent speed band (the fast core reads
   warm) plus flow arrows, both synced to the forecast slider. */
var gsBandLayer=L.layerGroup(),gsArrows=L.layerGroup(),GSW=null,gsBandBuilt=false;
function gsOn(){var el=document.getElementById('tgGulf');return !!(el&&el.checked);}
function gsTiFor(ti){return (GS.gsMap&&GS.gsMap[ti]!=null)?GS.gsMap[ti]:ti;}
/* NOAA Ocean Prediction Center Gulf Stream frontal analysis — the authoritative
   north/south wall positions (daily, from IR-satellite SST fronts). Gives the
   real stream shape; the Open-Meteo ocean current supplies speed + forecast. */
function loadGSWall(cb){
	if(GSW){cb&&cb();return;}
	fetchT(AJAX+'?action=oyc_gs_wall',12000).then(function(r){return r.json();}).then(function(res){
		if(res&&res.ok&&res.north&&res.north.length)GSW=res;cb&&cb();
	}).catch(function(){cb&&cb();});
}
function gsBearing(a,b){var la1=a[0]*Math.PI/180,la2=b[0]*Math.PI/180,dl=(b[1]-a[1])*Math.PI/180;
	var y=Math.sin(dl)*Math.cos(la2),x=Math.cos(la1)*Math.sin(la2)-Math.sin(la1)*Math.cos(la2)*Math.cos(dl);
	return (Math.atan2(y,x)*180/Math.PI+360)%360;}
function gsSpeedAt(lat,lon,gt){if(!GS.loaded||lat<GS.LA2||lat>GS.LA1||lon<GS.LO1||lon>GS.LO2)return null;
	var r=Math.round((GS.LA1-lat)/GS.DY),c=Math.round((lon-GS.LO1)/GS.DX);
	if(r<0||r>=GS.NY||c<0||c>=GS.NX)return null;return (GS.SPD[r*GS.NX+c]||[])[gt];}
function loadGulf(cb){
	if(GS.loaded){cb&&cb();return;}if(GS.loading)return;GS.loading=true;
	var N=GS.LAT.length,CH=140,chunks=[];for(var s=0;s<N;s+=CH)chunks.push([s,Math.min(s+CH,N)]);
	GS.SPD=new Array(N);GS.DIR=new Array(N);var gst=null,ci=0,CONC=2,KT=0.539957; /* km/h → kt */
	function fc(idx,tries){var a=chunks[idx][0];
		return fetchT(AJAX+'?action=oyc_gulfstream&chunk='+idx,15000)
			.then(function(r){if(!r.ok)throw new Error(r.status);return r.json();})
			.then(function(arr){var list=Array.isArray(arr)?arr:[arr];for(var k=0;k<list.length;k++){var h=list[k].hourly;if(h){if(!gst&&h.time)gst=h.time;
				GS.SPD[a+k]=(h.ocean_current_velocity||[]).map(function(v){return v==null?null:v*KT;});
				GS.DIR[a+k]=h.ocean_current_direction||[];}}})
			.catch(function(){if(tries<2)return new Promise(function(res){setTimeout(res,800*(tries+1));}).then(function(){return fc(idx,tries+1);});});
	}
	function pump(){if(ci>=chunks.length)return Promise.resolve();var batch=[];for(var n=0;n<CONC&&ci<chunks.length;n++,ci++)batch.push(fc(ci,0));
		return Promise.all(batch).then(function(){return new Promise(function(res){setTimeout(res,300);}).then(pump);});}
	pump().then(function(){
		if(gst)GS.gsMap=TIMES.map(function(t){var tt=new Date(t).getTime(),best=0,bd=1e15;for(var i=0;i<gst.length;i++){var d=Math.abs(new Date(gst[i]).getTime()-tt);if(d<bd){bd=d;best=i;}}return best;});
		GS.loaded=true;GS.loading=false;cb&&cb();
	});
}
function drawGulf(ti){
	if(!gsOn()){gsBandLayer.clearLayers();gsArrows.clearLayers();gsBandBuilt=false;return;}
	if(!GSW)return;
	var gt=gsTiFor(ti);
	/* the stream band from the NOAA walls — static (daily analysis), built once.
	   Trim the north wall to the south wall's eastern extent so the enclosing ring
	   closes cleanly; arrows still trace the full north wall past that. */
	if(!gsBandBuilt){
		gsBandLayer.clearLayers();
		var southEast=Math.max.apply(null,GSW.south.map(function(p){return p[1];}));
		var nb=GSW.north.filter(function(p){return p[1]<=southEast;});
		if(nb.length>1){
			L.polygon(nb.concat(GSW.south),{color:'#c96a1e',weight:1,opacity:.35,fillColor:'#f0902f',fillOpacity:.22,interactive:false}).addTo(gsBandLayer);
		}
		L.polyline(GSW.south,{color:'#2f6fc4',weight:1.6,opacity:.65,interactive:false}).addTo(gsBandLayer); /* cool south wall */
		L.polyline(GSW.north,{color:'#c0392b',weight:3,opacity:.92,interactive:false}).addTo(gsBandLayer);   /* warm north wall */
		gsBandBuilt=true;
	}
	/* animated flow arrows striding along the axis, coloured by the forecast current
	   speed at that spot (Open-Meteo), each drifting downstream */
	gsArrows.clearLayers();
	var nw=GSW.north,step=4;
	for(var i=step;i<nw.length-step;i+=step){
		var p=nw[i],dir=gsBearing(nw[i-step],nw[i+step]);
		var hex='#0b3550'; /* uniform dark flow arrows (no per-point current fetch) */
		var delay=(-(((i/step)%17)*0.1)).toFixed(1);
		var html='<div class="gs-arrow" style="transform:rotate('+Math.round(dir)+'deg)"><div class="gs-flow" style="animation-delay:'+delay+'s">'
			+'<svg width="24" height="24" viewBox="0 0 24 24"><line x1="12" y1="21" x2="12" y2="7" stroke="'+hex+'" stroke-width="2.6"/><path d="M12 2 L7 10 L17 10 Z" fill="'+hex+'"/></svg></div></div>';
		L.marker(p,{icon:L.divIcon({className:'gs-mk',html:html,iconSize:[24,24],iconAnchor:[12,12]}),interactive:false,keyboard:false}).addTo(gsArrows);
	}
}
/* current speed/dir at a hovered point, for the readout tooltip */
function gsAt(lat,lon){
	if(!gsOn()||!GS.loaded||lat<GS.LA2||lat>GS.LA1||lon<GS.LO1||lon>GS.LO2)return null;
	var r=Math.round((GS.LA1-lat)/GS.DY),c=Math.round((lon-GS.LO1)/GS.DX);
	if(r<0||r>=GS.NY||c<0||c>=GS.NX)return null;
	var gt=gsTiFor(curTi),sp=(GS.SPD[r*GS.NX+c]||[])[gt],dr=(GS.DIR[r*GS.NX+c]||[])[gt];
	return sp==null?null:{kt:sp,dir:dr};
}

/* ---------- velocity layer ----------
   leaflet-velocity 1.7 throws (getSize/null map) if the layer is removed while
   an animation frame or map-event handler is still queued. So we add it ONCE
   and never remove it — we just show/hide its canvas and swap data on grid
   change. One velocity scale serves both grids (slowed ~50% per request). */
function setParticlesVisible(on){var cvs=map.getContainer().getElementsByTagName('canvas');
	for(var i=0;i<cvs.length;i++)cvs[i].style.display=on?'':'none';}
function buildVL(g,ti){
	if(vl){vl.setData(g.FRAMES[ti]);return;}
	vl=L.velocityLayer({displayValues:false,data:g.FRAMES[ti],maxVelocity:26,velocityScale:0.0045,
		lineWidth:1.7,particleAge:100,particleMultiplier:1/320,colorScale:['#16324a','#0b2a4a'],frameRate:20});
	vl.addTo(map);
	setParticlesVisible(mode==='wind'&&document.getElementById('tgParticles').checked);
}

/* ---------- wind-mode render ---------- */
function showWind(ti){ti=Math.max(0,Math.min(TIMES.length-1,ti));curTi=ti;slider.value=ti;
	if(vl&&map.hasLayer(vl)&&activeGrid.FRAMES[ti])vl.setData(activeGrid.FRAMES[ti]);
	var f=fmt(TIMES[ti]);tlabel.innerHTML=f.big+'<small>'+f.small+(ti===NOWI?' · now':'')+'</small>';
	refreshOverlays();drawField();if(!map.hasLayer(hlLayer))hlLayer.addTo(map);drawHL(activeGrid,ti);updatePins(ti);updateER(ti);updateStormsAt(new Date(TIMES[ti]).getTime());if(gsOn())drawGulf(ti);}

/* ---------- grid switching by zoom ---------- */
function chooseGrid(){var b=map.getBounds();
	return (b.getSouth()>=LOCAL.LA2-0.6&&b.getNorth()<=LOCAL.LA1+0.6&&b.getWest()>=LOCAL.LO1-0.6&&b.getEast()<=LOCAL.LO2+0.6)?LOCAL:BASIN;}
function maybeSwitchGrid(){
	if(mode==='radar')return;
	var want=chooseGrid();
	if(want===BASIN&&!BASIN.loaded){loadBasin();want=LOCAL;}
	if(want!==activeGrid&&want.loaded){activeGrid=want;buildVL(activeGrid,curTi);refreshOverlays();drawField();drawHL(activeGrid,curTi);
		document.getElementById('foot').innerHTML=(activeGrid===BASIN?'Atlantic basin wind field ':'Long Island Sound to Cape Cod ')+'&middot; Open-Meteo (GFS). Radar = HRRR precip.';}
}
map.on('zoomend',maybeSwitchGrid);

/* ---------- hover pointer ---------- */
var hoverTip=document.getElementById('hoverTip');
map.on('mousemove',function(e){
	if(!activeGrid.loaded){hoverTip.style.display='none';return;}
	var lon=((e.latlng.lng+540)%360)-180;
	var s=readPoint(e.latlng.lat,lon,curTi);
	if(!s){hoverTip.style.display='none';return;}
	hoverTip.style.display='block';
	hoverTip.style.left=e.containerPoint.x+'px';hoverTip.style.top=e.containerPoint.y+'px';
	var gsh=gsAt(e.latlng.lat,lon);
	hoverTip.innerHTML='<span class="hd2">'+e.latlng.lat.toFixed(4)+', '+lon.toFixed(4)+' &middot; '+nmFromBuoy42(e.latlng.lat,lon).toFixed(1)+' nm from Buoy 42</span>'
		+Math.round(s.kt)+' kt '+card(s.dir)+(s.mb!=null?' · '+Math.round(s.mb)+' mb':'')+(s.tempF!=null?' · '+Math.round(s.tempF)+'°F':'')+(s.waveFt!=null?' · '+s.waveFt.toFixed(1)+' ft':'')
		+(gsh?' · <span style="color:#bcdcf0">GS '+gsh.kt.toFixed(1)+' kt '+card(gsh.dir)+'</span>':'');
});
map.on('mouseout',function(){hoverTip.style.display='none';});

/* ---------- Open-Meteo loaders (all via cached admin-ajax proxies) ----------
   fetch with an abort timeout so a hung request becomes a retriable error
   (plain fetch has no timeout — a stalled call would hang forever). */
function fetchT(url,ms){var ctl=new AbortController();var id=setTimeout(function(){ctl.abort();},ms||12000);
	return fetch(url,{signal:ctl.signal}).then(function(r){clearTimeout(id);return r;},function(e){clearTimeout(id);throw e;});}

/* ---------- waves (Open-Meteo Marine = NOAA WaveWatch III) ----------
   Loaded lazily per grid the first time the Wave view is opened. The marine
   model resolves open ocean & offshore coastal water only — the enclosed Sound
   comes back null (transparent); the NDBC buoy markers cover the Sound itself.
   Marine's time axis is offset from the weather grid, so map each weather frame
   to the nearest marine step (g.wvMap). Wave height in metres → feet at read. */
function loadWaves(g,cb){
	if(g.wvLoaded){cb&&cb();return;}
	if(g.wvLoading)return;
	g.wvLoading=true;
	var N=g.LAT.length,CH=140,chunks=[];for(var s=0;s<N;s+=CH)chunks.push([s,Math.min(s+CH,N)]);
	g.WV=new Array(N);var wvt=null,ci=0,CONC=2;
	function fc(idx,tries){var a=chunks[idx][0];
		/* cached per-chunk server proxy (CH=140 matches oyc_waves) */
		return fetchT(AJAX+'?action=oyc_waves&grid='+(g.name||'local')+'&chunk='+idx,15000)
			.then(function(r){if(!r.ok)throw new Error(r.status);return r.json();})
			.then(function(arr){var list=Array.isArray(arr)?arr:[arr];for(var k=0;k<list.length;k++){var h=list[k].hourly;if(h){if(!wvt&&h.time)wvt=h.time;g.WV[a+k]=h.wave_height||null;}}})
			.catch(function(){if(tries<2)return new Promise(function(res){setTimeout(res,800*(tries+1));}).then(function(){return fc(idx,tries+1);});});
	}
	function pump(){if(ci>=chunks.length)return Promise.resolve();var batch=[];for(var n=0;n<CONC&&ci<chunks.length;n++,ci++)batch.push(fc(ci,0));
		return Promise.all(batch).then(function(){return new Promise(function(res){setTimeout(res,300);}).then(pump);});}
	pump().then(function(){
		if(wvt){g.wvMap=TIMES.map(function(t){var tt=new Date(t).getTime(),best=0,bd=1e15;for(var i=0;i<wvt.length;i++){var d=Math.abs(new Date(wvt[i]).getTime()-tt);if(d<bd){bd=d;best=i;}}return best;});}
		g.wvLoaded=true;g.wvLoading=false;cb&&cb();
	});
}

function loadLocal(tries){
	LOCAL.loading=true;tries=tries||0;
	LOCAL.SP=[];LOCAL.DR=[];LOCAL.GU=[];LOCAL.PR=[];LOCAL.TP=[];LOCAL.PP=[];
	function valid(arr){return Array.isArray(arr)&&arr.length&&arr[0]&&arr[0].hourly;}
	/* Direct browser fetch of the same grid/params — used when the cached server
	   proxy can't reach Open-Meteo (host IP throttled/blocked). Uses the visitor's
	   own connection so the wind field loads instead of showing "unavailable". */
	function clientLocal(){
		var u='https://api.open-meteo.com/v1/forecast?latitude='+LOCAL.LAT.join(',')+'&longitude='+LOCAL.LON.join(',')
			+'&hourly=wind_speed_10m,wind_direction_10m,wind_gusts_10m,pressure_msl,temperature_2m,precipitation'
			+'&wind_speed_unit=ms&temperature_unit=fahrenheit&precipitation_unit=inch&temporal_resolution=hourly_3&forecast_days=7&timezone=America%2FNew_York';
		return fetch(u).then(function(r){if(!r.ok)throw new Error('wx '+r.status);return r.json();});
	}
	function render(arr){
		TIMES=arr[0].hourly.time;
		for(var k=0;k<arr.length;k++){LOCAL.SP.push(arr[k].hourly.wind_speed_10m);LOCAL.DR.push(arr[k].hourly.wind_direction_10m);LOCAL.GU.push(arr[k].hourly.wind_gusts_10m);LOCAL.PR.push(arr[k].hourly.pressure_msl);LOCAL.TP.push(arr[k].hourly.temperature_2m);LOCAL.PP.push(arr[k].hourly.precipitation);}
		var now=Date.now(),bd=1e15;for(var t=0;t<TIMES.length;t++){var dd=Math.abs(new Date(TIMES[t]).getTime()-now);if(dd<bd){bd=dd;NOWI=t;}}
		buildFrames(LOCAL);LOCAL.loaded=true;LOCAL.loading=false;
		activeGrid=LOCAL;slider.max=TIMES.length-1;
		buildVL(LOCAL,NOWI);showWind(NOWI);updateLegend();
		document.getElementById('st').textContent='7-day forecast · '+TIMES.length+' frames · '+new Date(now).toLocaleString('en-US',{hour:'numeric',minute:'2-digit',month:'short',day:'numeric'});
		/* basin grid is deferred — maybeSwitchGrid() loads it on demand the first
		   time you zoom out to the Atlantic, so the harbor view doesn't fire its 9
		   proxy calls (cuts the initial admin-ajax burst that could trip host 429s) */
		loadWaves(LOCAL,function(){updateER(curTi);});
	}
	/* cached server proxy first; browser direct if the host can't reach Open-Meteo */
	fetchT(AJAX+'?action=oyc_wind_local',15000)
		.then(function(r){return r.ok?r.json():null;})
		.catch(function(){return null;})
		.then(function(arr){return valid(arr)?arr:clientLocal();})
		.then(function(arr){if(!valid(arr))throw new Error('grid');render(arr);})
		.catch(function(e){
			if(tries<2){setTimeout(function(){loadLocal(tries+1);},1500);return;}
			document.getElementById('st').textContent='Wind field unavailable ('+e+')';
		});
}

/* chunked basin loader with limited concurrency + retry/backoff */
function loadBasin(){
	if(BASIN.loaded||BASIN.loading)return;BASIN.loading=true;
	var N=BASIN.LAT.length,CH=150,chunks=[];
	for(var s=0;s<N;s+=CH)chunks.push([s,Math.min(s+CH,N)]);
	BASIN.SP=new Array(N);BASIN.DR=new Array(N);BASIN.PR=new Array(N);BASIN.TP=new Array(N);
	var ci=0,CONC=2;
	function fetchChunk(idx,tries){
		var a=chunks[idx][0],b=chunks[idx][1];
		function valid(arr){return Array.isArray(arr)&&arr.length&&arr[0]&&arr[0].hourly;}
		function store(arr){var list=Array.isArray(arr)?arr:[arr];
			for(var k=0;k<list.length;k++){var gi=a+k;if(list[k]&&list[k].hourly){BASIN.SP[gi]=list[k].hourly.wind_speed_10m;BASIN.DR[gi]=list[k].hourly.wind_direction_10m;BASIN.PR[gi]=list[k].hourly.pressure_msl;BASIN.TP[gi]=list[k].hourly.temperature_2m;}}}
		/* browser direct fetch of this chunk's points — same grid slice/params as the
		   proxy — for when the host can't reach Open-Meteo (keeps the national view alive) */
		function clientChunk(){
			var u='https://api.open-meteo.com/v1/forecast?latitude='+BASIN.LAT.slice(a,b).join(',')+'&longitude='+BASIN.LON.slice(a,b).join(',')
				+'&hourly=wind_speed_10m,wind_direction_10m,pressure_msl,temperature_2m'
				+'&wind_speed_unit=ms&temperature_unit=fahrenheit&precipitation_unit=inch&temporal_resolution=hourly_3&forecast_days=7&timezone=America%2FNew_York';
			return fetch(u).then(function(r){if(!r.ok)throw new Error('b '+r.status);return r.json();});
		}
		/* cached per-chunk server proxy (CH=150 matches oyc_wind_basin), browser fallback */
		return fetchT(AJAX+'?action=oyc_wind_basin&chunk='+idx,12000)
			.then(function(r){return r.ok?r.json():null;}).catch(function(){return null;})
			.then(function(arr){return valid(arr)?arr:clientChunk();})
			.then(function(arr){if(!valid(arr))throw new Error('chunk');store(arr);})
			.catch(function(){
				if(tries<2)return new Promise(function(res){setTimeout(res,800*(tries+1));}).then(function(){return fetchChunk(idx,tries+1);});
			});
	}
	function pump(){
		if(ci>=chunks.length)return Promise.resolve();
		var batch=[];for(var n=0;n<CONC&&ci<chunks.length;n++,ci++)batch.push(fetchChunk(ci,0));
		return Promise.all(batch).then(function(){return new Promise(function(res){setTimeout(res,300);}).then(pump);});
	}
	pump().then(function(){
		for(var i=0;i<N;i++){if(!BASIN.SP[i]){BASIN.SP[i]=TIMES.map(function(){return 0;});BASIN.DR[i]=TIMES.map(function(){return 0;});BASIN.PR[i]=TIMES.map(function(){return 1013;});BASIN.TP[i]=TIMES.map(function(){return null;});}}
		buildFrames(BASIN);BASIN.loaded=true;BASIN.loading=false;
		maybeSwitchGrid();
	});
}

/* ---------- RADAR — precipitation (NOAA HRRR via Open-Meteo) ----------
   The whole timeline (recent past → +18 h) is ONE smooth, supersampled precip
   field, so the "radar" reads consistently defined & smooth. (NEXRAD tiles were
   dropped — they looked blocky next to the smooth forecast field.) 15-min steps. */
var FC={PP15:[],tEpoch:[],loaded:false,loading:false},fcOverlay=null,RV={frames:[],idx:0,loaded:false};
/* precip rate (mm / 15 min) → radar-style intensity colour */
var PRECIP_RADAR_STOPS=[[0.1,[142,199,255]],[0.4,[74,156,240]],[1,[46,204,113]],[2.5,[241,196,15]],[5,[230,126,34]],[10,[231,76,60]],[20,[176,58,122]]];
function precipRadarColor(mm){if(mm==null||mm<0.05)return null;return rampColor(mm,PRECIP_RADAR_STOPS);}
function loadFC(cb){
	if(FC.loaded){cb&&cb();return;}if(FC.loading)return;FC.loading=true;
	/* cached server proxy — same reason as the wind grid (radar was silently
	   blank whenever the direct Open-Meteo call got 429'd) */
	fetchT(AJAX+'?action=oyc_wind_radar',15000).then(function(r){if(!r.ok)throw new Error(r.status);return r.json();}).then(function(arr){
		var list=Array.isArray(arr)?arr:[arr];
		if(!list.length||!list[0].minutely_15)throw new Error('radar');
		FC.PP15=list.map(function(p){return (p.minutely_15&&p.minutely_15.precipitation)||[];});
		var tm=(list[0].minutely_15&&list[0].minutely_15.time)||[];
		FC.tEpoch=tm.map(function(t){return new Date(t).getTime();});
		FC.loaded=true;FC.loading=false;cb&&cb();
	}).catch(function(){FC.loading=false;
		/* one delayed retry (e.g. the proxy cache is still warming) so the radar
		   recovers without needing the user to nudge the slider */
		if(!FC.retried){FC.retried=true;setTimeout(function(){if(!FC.loaded&&mode==='radar')loadFC(cb);},1500);}
	});
}
function fcStep(t){var best=0,bd=1e15;for(var i=0;i<FC.tEpoch.length;i++){var dd=Math.abs(FC.tEpoch[i]-t);if(dd<bd){bd=dd;best=i;}}return best;}
function fmtOff(off){if(off===0)return 'now';if(off<0)return off+' min';var h=Math.floor(off/60),m=off%60;return '+'+(h?h+'h'+(m?' '+m+'m':''):m+'m');}
function loadRadar(cb){
	if(RV.loaded){cb&&cb();return;}
	/* recent past (−1 h) → +18 h in 15-min steps (HRRR is full-res to ~+18 h; the
	   proxy fetches past_minutes=60 + forecast_minutely_15=72 to cover this) */
	var offs=[];for(var m=-60;m<=1080;m+=15)offs.push(m);
	RV.frames=offs.map(function(o){return {off:o};});RV.idx=Math.max(0,offs.indexOf(0));RV.loaded=true;
	loadFC();cb&&cb();
}
/* ---- wide-area radar: NEXRAD mosaic (observed) + HRRR REFD (forecast) --------
   Shown when the view is zoomed out past the local grid, for MyRadar-style
   continental coverage; the smooth local HRRR field still serves the zoomed-in
   view. One unified timeline from the IEM tile cache (keyless, CORS-ok):
   past hour → NEXRAD national mosaic (5-min -mNNm layers), now → +18 h →
   HRRR simulated reflectivity forecast (REFD, 15-min F#### layers, latest run).
   HRRR frames use the latest run (INIT 0), so lead time is approximate to ~1 h. */
var NX={layer:null,idx:0},radarWasWide=null;
var WF=(function(){var a=[],m;for(m=-55;m<=0;m+=5)a.push(m);for(m=15;m<=1080;m+=15)a.push(m);return a;})();
var IEM_TMS='https://mesonet.agron.iastate.edu/cache/tile.py/1.0.0/';
function nexradUrl(off){return IEM_TMS+'nexrad-n0q-900913'+(off<0?'-m'+('0'+(-off)).slice(-2)+'m':'')+'/{z}/{x}/{y}.png';}
function wideRadarUrl(off){return off>0?IEM_TMS+'hrrr::REFD-F'+('000'+off).slice(-4)+'-0/{z}/{x}/{y}.png':nexradUrl(off);}
/* wide whenever the viewport spills past the local box (same test the wind grid
   uses to switch LOCAL→BASIN), so "zoom out" reveals the national view */
function radarWide(){var b=map.getBounds();
	return !(b.getSouth()>=LOCAL.LA2-0.6&&b.getNorth()<=LOCAL.LA1+0.6&&b.getWest()>=LOCAL.LO1-0.6&&b.getEast()<=LOCAL.LO2+0.6);}
function removeNexrad(){if(NX.layer){map.removeLayer(NX.layer);NX.layer=null;}}
function radarOffsets(wide){return wide?WF:RV.frames.map(function(f){return f.off;});}
function nearestFrameIdx(offs,off){var bi=0,bd=1e15;for(var i=0;i<offs.length;i++){var d=Math.abs(offs[i]-off);if(d<bd){bd=d;bi=i;}}return bi;}
function radarFrameCount(){return radarWide()?WF.length:RV.frames.length;}
function showWideFrame(i){
	if(fcOverlay)fcOverlay.setOpacity(0); /* drop the tiny local patch beneath the national view */
	i=Math.max(0,Math.min(WF.length-1,i));NX.idx=i;slider.value=i;curTi=i;
	var off=WF[i],t=Date.now()+off*60000,fcst=off>0;
	if(NX.layer)NX.layer.setUrl(wideRadarUrl(off));
	else{NX.layer=L.tileLayer(wideRadarUrl(off),{opacity:0.72,zIndex:344,interactive:false,attribution:'NEXRAD &amp; HRRR &middot; Iowa State IEM',updateWhenZooming:false});NX.layer.addTo(map);}
	tlabel.innerHTML=new Date(t).toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'})+'<small>'+fmtOff(off)+' &middot; '+(fcst?'HRRR fcst':'NEXRAD')+'</small>';
	setER(erForFrame(t,off===0));
}
function showRadar(i){return radarWide()?showWideFrame(i):showHRRR(i);}
function showHRRR(i){
	removeNexrad();
	if(!RV.frames.length)return;
	i=Math.max(0,Math.min(RV.frames.length-1,i));RV.idx=i;slider.value=i;curTi=i;
	var off=RV.frames[i].off,t=Date.now()+off*60000,d=new Date(t);
	var tag=off>0?' · forecast':(off<0?' · recent':'');
	if(!FC.loaded){if(fcOverlay)fcOverlay.setOpacity(0);loadFC(function(){if(mode==='radar')showRadar(i);});
		tlabel.innerHTML=d.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'})+'<small>'+fmtOff(off)+' · loading…</small>';setER(erForFrame(t,off===0));return;}
	var step=fcStep(t),url=fieldDataURL(LOCAL,FC.PP15,step,precipRadarColor),bounds=[[LOCAL.LA2,LOCAL.LO1],[LOCAL.LA1,LOCAL.LO2]];
	if(fcOverlay){fcOverlay.setBounds(bounds);fcOverlay.setUrl(url);fcOverlay.setOpacity(0.82);}
	else{fcOverlay=L.imageOverlay(url,bounds,{opacity:0.82,interactive:false});fcOverlay.addTo(map);if(fcOverlay.setZIndex)fcOverlay.setZIndex(345);}
	tlabel.innerHTML=d.toLocaleTimeString('en-US',{hour:'numeric',minute:'2-digit'})+'<small>'+fmtOff(off)+tag+'</small>';
	setER(erForFrame(t,off===0));updateStormsAt(t);
}

/* ---------- unified slider / play ---------- */
function show(i){mode==='radar'?showRadar(i):showWind(i);}
slider.addEventListener('input',function(){stop();show(+slider.value);});
playBtn.addEventListener('click',function(){playing?stop():play();});
function play(){var max=(mode==='radar'?radarFrameCount():TIMES.length);if(!max)return;playing=true;playBtn.innerHTML='&#10073;&#10073;';
	/* pace each frame: the zoomed-out radar fetches fresh tiles per step so it needs
	   the most time to render; the local smooth field is quicker; wind is data-only */
	var step=(mode==='radar')?(radarWide()?1200:850):700;
	timer=setInterval(function(){var n=+slider.value+1;if(n>=max)n=0;show(n);},step);}
function stop(){playing=false;playBtn.innerHTML='&#9654;';if(timer){clearInterval(timer);timer=null;}}

/* ---------- view tabs (Wind | Radar | Temp | Precip) ---------- */
/* ---------- Satellite (NOAA GOES-East IR, NWS/OPC radiofax) ----------
   These are standalone equirectangular chart JPEGs (own grid + coastlines), so
   the Satellite tab shows the image full-panel rather than overlaying the map.
   Files decoded from the NWS radiofax schedule (rfaxatl.txt). */
var FAX_BASE='https://tgftp.nws.noaa.gov/fax/',
    SAT_FILES={latest:'evnt99.jpg','18':'evnt18.jpg','12':'evnt12.jpg','06':'evnt06.jpg','00':'evnt00.jpg'},
    satWhich='latest';
/* shared image-panel loader for any NWS/OPC radiofax product (sat .jpg or chart .gif) */
function showFax(file,label,failMsg){
	var img=document.getElementById('satImg'),panel=document.getElementById('satPanel'),st=document.getElementById('st');
	panel.classList.add('loading');img.style.visibility='hidden';
	var bucket=Math.floor(Date.now()/(30*60*1000)); /* refresh every ~30 min, cache within */
	img.onload=function(){panel.classList.remove('loading');img.style.visibility='visible';st.textContent=label;};
	img.onerror=function(){panel.classList.remove('loading');st.textContent=failMsg||'Chart unavailable';};
	img.src=FAX_BASE+file+'?t='+bucket;
}
function loadSat(which){
	satWhich=which||satWhich;
	showFax(SAT_FILES[satWhich], 'GOES-East Ch.13 IR · '+(satWhich==='latest'?'latest':satWhich+'Z')+' · NWS/OPC', 'Satellite image unavailable');
	[].forEach.call(document.querySelectorAll('#satLayers .sat-t'),function(b){b.classList.toggle('on',b.getAttribute('data-sat')===satWhich);});
}
function loadChart(){var sel=document.getElementById('chartSel'),opt=sel.options[sel.selectedIndex];
	showFax(sel.value, (opt.text||'Chart').replace(/\s+/g,' ').trim()+' · NWS/OPC');}

function setMode(m){
	stop();mode=m;
	[['wind','tabWind'],['radar','tabRadar'],['temp','tabTemp'],['precip','tabPrecip'],['wave','tabWave'],['sat','tabSat'],['charts','tabCharts']].forEach(function(x){var b=document.getElementById(x[1]);
		if(b){b.classList.toggle('on',m===x[0]);b.setAttribute('aria-selected',m===x[0]);}});
	document.getElementById('windLayers').style.display=m==='wind'?'flex':'none';
	document.getElementById('radarLayers').style.display=m==='radar'?'flex':'none';
	document.getElementById('satLayers').style.display=m==='sat'?'flex':'none';
	document.getElementById('chartLayers').style.display=m==='charts'?'flex':'none';
	document.getElementById('tipHelp').style.display=m==='wind'?'block':'none';
	/* satellite & charts = standalone NOAA images; swap the interactive map for the image panel */
	var isImg=(m==='sat'||m==='charts');
	document.getElementById('map').style.display=isImg?'none':'';
	document.getElementById('satPanel').style.display=isImg?'flex':'none';
	document.getElementById('ctrlBar').style.display=isImg?'none':'flex';
	document.getElementById('erBar').style.display=isImg?'none':'';
	if(isImg){setParticlesVisible(false);map.removeLayer(arrowsLayer);map.removeLayer(isoLayer);map.removeLayer(buoyLayer);
		document.getElementById('legend').style.display='none';(m==='sat')?loadSat(satWhich):loadChart();return;}
	setTimeout(function(){map.invalidateSize();},0); /* map container may have been hidden by satellite mode */
	if(m==='wave'){loadBuoys(function(){if(mode==='wave')buoyLayer.addTo(map);});}else{map.removeLayer(buoyLayer);}
	if(m==='radar'){
		setParticlesVisible(false);map.removeLayer(arrowsLayer);map.removeLayer(isoLayer);map.removeLayer(hlLayer);
		updateLegend();drawField();
		radarWasWide=radarWide();
		loadRadar(function(){
			var wide=radarWide();
			slider.max=Math.max(0,(wide?WF.length:RV.frames.length)-1);
			showRadar(nearestFrameIdx(radarOffsets(wide),0)); /* start on 'now' */
		});
		return;
	}
	if(RV.layer){map.removeLayer(RV.layer);RV.layer=null;}
	if(fcOverlay){map.removeLayer(fcOverlay);fcOverlay=null;}
	removeNexrad();
	slider.max=Math.max(0,TIMES.length-1);
	if(m==='wind'){
		setParticlesVisible(document.getElementById('tgParticles').checked);
		if(document.getElementById('tgArrows').checked)arrowsLayer.addTo(map);
		if(document.getElementById('tgIso').checked)isoLayer.addTo(map);
	}else{ /* Temp / Precip: dedicated field views — base map + color field only */
		setParticlesVisible(false);map.removeLayer(arrowsLayer);map.removeLayer(isoLayer);
	}
	updateLegend();
	showWind(Math.min(curTi,Math.max(0,TIMES.length-1)));
}
document.getElementById('tabWind').addEventListener('click',function(){setMode('wind');});
document.getElementById('tabRadar').addEventListener('click',function(){setMode('radar');});
document.getElementById('tabTemp').addEventListener('click',function(){setMode('temp');});
document.getElementById('tabPrecip').addEventListener('click',function(){setMode('precip');});
document.getElementById('tabWave').addEventListener('click',function(){setMode('wave');});
document.getElementById('tabSat').addEventListener('click',function(){setMode('sat');});
document.getElementById('tabCharts').addEventListener('click',function(){setMode('charts');});
[].forEach.call(document.querySelectorAll('#satLayers .sat-t'),function(b){b.addEventListener('click',function(){loadSat(b.getAttribute('data-sat'));});});
document.getElementById('chartSel').addEventListener('change',loadChart);

/* ---------- layer toggles ---------- */
document.getElementById('tgParticles').addEventListener('change',function(){if(mode!=='wind')return;setParticlesVisible(this.checked);});
document.getElementById('tgArrows').addEventListener('change',function(){if(this.checked){arrowsLayer.addTo(map);refreshOverlays();}else map.removeLayer(arrowsLayer);});
document.getElementById('tgIso').addEventListener('change',function(){if(this.checked){isoLayer.addTo(map);refreshOverlays();}else map.removeLayer(isoLayer);});
document.getElementById('tgWindColor').addEventListener('change',function(){drawField();updateLegend();});
function pickField(which,cb){var ids={temp:'tgTemp',precip:'tgPrecip',wave:'tgWave'};
	if(cb.checked){fieldMode=which;for(var k in ids){if(k!==which)document.getElementById(ids[k]).checked=false;}}
	else if(fieldMode===which){fieldMode=null;}drawField();updateLegend();}
document.getElementById('tgTemp').addEventListener('change',function(){pickField('temp',this);});
document.getElementById('tgPrecip').addEventListener('change',function(){pickField('precip',this);});
document.getElementById('tgWave').addEventListener('change',function(){pickField('wave',this);});
document.getElementById('tgGulf').addEventListener('change',function(){
	if(this.checked){gsBandLayer.addTo(map);gsArrows.addTo(map);
		/* NOAA frontal analysis = position only; no Open-Meteo marine fetch (keeps
		   the heavy per-point current off marine-api, which the prod host throttles) */
		loadGSWall(function(){drawGulf(curTi);updateLegend();});
	}else{map.removeLayer(gsBandLayer);gsBandLayer.clearLayers();map.removeLayer(gsArrows);gsArrows.clearLayers();gsBandBuilt=false;updateLegend();}
});
document.getElementById('tgRadarWind').addEventListener('change',function(){if(this.checked){arrowsLayer.addTo(map);drawBarbs();}else map.removeLayer(arrowsLayer);});
/* redraw the screen-lattice barbs after pan/zoom so density stays constant */
map.on('moveend',function(){if(mode==='radar'){if(document.getElementById('tgRadarWind').checked)drawBarbs();}else if(document.getElementById('tgArrows').checked)drawBarbs();});
/* radar: switch between the local HRRR field and the national NEXRAD mosaic as
   the view zooms across the local-grid boundary (resets the slider to 'now') */
map.on('zoomend',function(){
	if(mode!=='radar')return;
	var w=radarWide();
	if(w!==radarWasWide){
		var curOff=radarWasWide?WF[NX.idx]:((RV.frames[RV.idx]||{}).off||0); /* keep the time across the switch */
		radarWasWide=w;stop();
		slider.max=Math.max(0,(w?WF.length:RV.frames.length)-1);
		show(nearestFrameIdx(radarOffsets(w),curOff));
	}
});

/* ---------- expand / collapse (CSS maximise that fills the window) ----------
   Deliberately NOT the native Fullscreen API: fullscreening the inner card moves
   it to the browser top layer, where the dark ::backdrop + ancestor-scoped CSS
   vars render the header chrome unreadable. The .isfull overlay keeps the whole
   themed component intact and reads correctly. */
(function(){
	var fsEl=document.querySelector('#oycwm .card'),btn=document.getElementById('oycwmFs');
	if(!fsEl||!btn)return;
	var EXP='<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M16 21h3a2 2 0 0 0 2-2v-3"/></svg><span>Expand</span>',
	    COL='<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8h3a2 2 0 0 0 2-2V3M16 3v3a2 2 0 0 0 2 2h3M3 16h3a2 2 0 0 1 2 2v3M16 21v-3a2 2 0 0 1 2-2h3"/></svg><span>Exit</span>';
	btn.innerHTML=EXP;
	/* opaque full-viewport backdrop as a direct child of <body> — guarantees no
	   other card bleeds through behind the expanded card, even if some ancestor
	   ever constrains the fixed card's positioning */
	var bd=null;
	function backdrop(on){
		if(on){ if(!bd){bd=document.createElement('div');bd.setAttribute('aria-hidden','true');bd.style.cssText='position:fixed;inset:0;background:#fff;z-index:2147483646';} if(!bd.parentNode)document.body.appendChild(bd); }
		else if(bd&&bd.parentNode){bd.parentNode.removeChild(bd);}
	}
	function apply(on){
		fsEl.classList.toggle('isfull',on);
		document.body.style.overflow=on?'hidden':'';
		backdrop(on);
		btn.innerHTML=on?COL:EXP;btn.title=on?'Exit full screen':'Full screen';btn.setAttribute('aria-label',btn.title);
		setTimeout(function(){
			map.invalidateSize();
			/* Leaflet repositions geo-anchored layers, but the velocity particle canvas
			   and the screen-lattice barbs/isobars don't re-fit on their own. Re-run the
			   full frame render so EVERY overlay matches the new size — wind particles
			   (show→vl.setData restarts the sim), wind barbs, isobars, temp/precip/waves
			   field, H/L, storms and the Gulf Stream. */
			setTimeout(function(){try{show(curTi);}catch(e){}},40);
		},90);
	}
	btn.addEventListener('click',function(){apply(!fsEl.classList.contains('isfull'));});
	document.addEventListener('keydown',function(e){if(e.key==='Escape'&&fsEl.classList.contains('isfull'))apply(false);});
})();

loadLocal();
})();
</script>
</div>
	<?php
	return ob_get_clean();
}
