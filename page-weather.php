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

// NOTE: no nocache_headers() here. This template renders a STATIC HTML shell —
// every "live" value (tides, wind, waves, marine forecast, alerts) is fetched
// client-side after load, so the document itself is safe to page-cache. Caching
// it cuts TTFB from ~3s (full PHP render each hit) to ~0.2s. Logged-in users
// still bypass the cache via WP-Optimize, and a cache purge refreshes the menu.

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
<html lang="en" data-theme="light">
<head>
<meta charset="utf-8">
<script>/* apply the visitor's saved color theme before paint (no flash); default = dark */
(function(){try{var t=localStorage.getItem('oyc_theme');document.documentElement.setAttribute('data-theme',(t==='light'||t==='dark')?t:'light');}catch(e){}})();</script>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Live Conditions — Mamaroneck Harbor · Orienta Yacht Club</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Source+Serif+4:opsz,wght@8..60,600&display=swap">
<style>
	:root{
		/* OYC DARK palette (default) — the July "signage" look: navy ground, cream
		   ink, brass-gold + harbor-blue accents. Members switch to light with the
		   header toggle (persisted). Var names --teal (gold) / --amber (harbor) kept. */
		--bg1:#0b2a4a; --bg2:#04162a; --panel:rgba(11,42,74,.55); --panel2:rgba(7,32,58,.72);
		--edge:rgba(245,239,226,.12); --edge2:rgba(245,239,226,.24);
		--ink:#f5efe2; --muted:#aeb9c8; --faint:#84909f;
		--teal:#d4a851; --teal2:#b08a3e; --amber:#57a6d6; --red:#c0392b; --green:#4ade80;
		--fish-track:rgba(245,239,226,.07); --fish-night:rgba(0,0,0,.28); --fish-gold:#d4a851; --fish-blue:#7cbbe2;
	}
	:root[data-theme="light"]{
		/* OYC light palette — white "readout sheet" cards on a soft harbor-light ground */
		--bg1:#eaf1f8; --bg2:#dde8f2; --panel:#ffffff; --panel2:#ffffff;
		--edge:#e0e7f0; --edge2:#cbd8e6;
		--ink:#16324a; --muted:#5a6b7d; --faint:#8a99a8;
		--teal:#b0842f; --teal2:#916b26; --amber:#1583cf; --red:#c0392b; --green:#2e9e5b;
		--fish-track:#eef2f7; --fish-night:#dbe3ed; --fish-gold:#86621f; --fish-blue:#0f68a6;
	}
	*{box-sizing:border-box;margin:0;padding:0}
	html,body{height:100%}
	body{
		font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
		background:radial-gradient(1200px 700px at 70% -10%,#12283f 0%,var(--bg1) 45%,var(--bg2) 100%);
		color:var(--ink); min-height:100vh; padding:18px; overflow-x:hidden;
		-webkit-font-smoothing:antialiased;
	}
	:root[data-theme="light"] body{background:radial-gradient(1200px 700px at 70% -10%,#ffffff 0%,var(--bg1) 50%,var(--bg2) 100%)}
	.mono{font-family:ui-monospace,"SF Mono",Menlo,Consolas,monospace;font-variant-numeric:tabular-nums}
	.wrap{max-width:1600px;margin:0 auto;display:flex;flex-direction:column;gap:16px;min-height:calc(100vh - 36px)}

	/* ---- site menu bar ---- */
	.sitebar{display:flex;align-items:center;gap:8px 22px;flex-wrap:wrap;
		padding:16px 22px;border:1px solid var(--edge);border-radius:12px;background:var(--panel2)}
	.sitebar-brand{display:inline-flex;align-items:center;text-decoration:none;white-space:nowrap;line-height:0}
	.sitebar-logo{height:42px;width:auto;display:block}
	.sitebar-brand:hover .sitebar-logo{opacity:.85}
	.sitebar-menu{display:flex;flex-wrap:wrap;gap:4px 20px;list-style:none;margin-left:auto;padding:0}
	.sitebar-menu li{margin:0}
	.sitebar-menu a{color:var(--muted);text-decoration:none;font-size:13px;letter-spacing:.05em;
		font-weight:600;transition:color .15s}
	.sitebar-menu a:hover{color:var(--teal)}
	.sitebar-toggle{display:none;background:none;border:0;cursor:pointer;padding:8px;margin-left:auto}
	.sitebar-bars{position:relative;display:block;width:20px;height:2px;background:var(--ink);border-radius:2px}
	.sitebar-bars::before,.sitebar-bars::after{content:"";position:absolute;left:0;width:20px;height:2px;
		background:var(--ink);border-radius:2px;transition:transform .2s}
	.sitebar-bars::before{top:-6px} .sitebar-bars::after{top:6px}
	.sitebar.open .sitebar-bars{background:transparent}
	.sitebar.open .sitebar-bars::before{transform:translateY(6px) rotate(45deg)}
	.sitebar.open .sitebar-bars::after{transform:translateY(-6px) rotate(-45deg)}
	@media (max-width:700px){
		.sitebar{padding:9px 14px}
		.sitebar-toggle{display:inline-flex}
		.sitebar-menu{display:none;flex-direction:column;gap:2px;width:100%;margin:8px 0 0;
			padding-top:8px;border-top:1px solid var(--edge)}
		.sitebar.open .sitebar-menu{display:flex}
		.sitebar-menu a{display:block;padding:9px 4px;font-size:15px}
	}

	/* ---- top bar ---- */
	.topbar{display:flex;align-items:center;justify-content:space-between;gap:8px 20px;
		padding:8px 22px;border:1px solid var(--edge);border-radius:16px;background:var(--panel2);flex-wrap:wrap}
	.tb-left{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
	.tb-brand{display:inline-flex;align-items:center;line-height:0;text-decoration:none}
	.tb-logo{height:40px;width:auto;display:block}
	.tb-brand:hover .tb-logo{opacity:.85}
	/* logo: white-ground PNG for light, cream-on-transparent variant for dark */
	:root:not([data-theme="dark"]) .logo-dark{display:none} :root[data-theme="dark"] .logo-light{display:none}
	.tb-title{color:var(--teal);font-weight:800;letter-spacing:.16em;text-transform:uppercase;font-size:13px}
	/* ---- mobile nav hamburger (board keeps its clean no-nav look on desktop) ---- */
	/* color-theme selector (dark / light) — persisted per visitor */
	.theme-toggle{display:inline-flex;align-self:center;margin:0 10px;border:1px solid var(--edge2);border-radius:999px;overflow:hidden;background:var(--panel2);flex:none}
	.theme-toggle .tt-opt{border:0;background:transparent;color:var(--muted);font:inherit;font-size:.72rem;font-weight:700;letter-spacing:.02em;padding:5px 12px;cursor:pointer;display:inline-flex;align-items:center;gap:5px;line-height:1.1;white-space:nowrap}
	.theme-toggle .tt-opt[aria-pressed="true"]{background:var(--ink);color:var(--bg1)}
	.theme-toggle .tt-opt:not([aria-pressed="true"]):hover{color:var(--ink)}
	.tb-toggle{display:none;background:none;border:0;cursor:pointer;padding:8px;margin-left:auto;align-self:center}
	.tb-bars{position:relative;display:block;width:22px;height:2px;background:var(--ink);border-radius:2px}
	.tb-bars::before,.tb-bars::after{content:"";position:absolute;left:0;width:22px;height:2px;background:var(--ink);border-radius:2px;transition:transform .2s}
	.tb-bars::before{top:-6px} .tb-bars::after{top:6px}
	.tb-toggle[aria-expanded="true"] .tb-bars{background:transparent}
	.tb-toggle[aria-expanded="true"] .tb-bars::before{transform:translateY(6px) rotate(45deg)}
	.tb-toggle[aria-expanded="true"] .tb-bars::after{transform:translateY(-6px) rotate(-45deg)}
	.tb-nav{display:none}
	.tb-nav .sitebar-menu{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:0}
	.tb-nav .sitebar-menu a{display:block;color:var(--ink);text-decoration:none;font-size:15px;
		letter-spacing:.04em;padding:11px 6px;border-top:1px solid var(--edge)}
	.tb-nav .sitebar-menu a:hover{color:var(--teal)}
	@media (max-width:700px){
		.tb-toggle{display:inline-flex}
		.tb-nav.open{display:block;width:100%;order:9;margin-top:4px}
	}
	.brand{display:flex;align-items:baseline;gap:14px;flex-wrap:wrap}
	.brand h1{font-weight:800;letter-spacing:.06em;font-size:clamp(16px,1.8vw,24px);text-transform:uppercase;
		background:linear-gradient(180deg,#16324a,#1583cf);-webkit-background-clip:text;background-clip:text;color:transparent}
	.brand .sub{color:var(--teal);letter-spacing:.22em;font-size:12px;text-transform:uppercase;font-weight:700}
	.clockwrap{text-align:right;display:flex;flex-direction:column;align-items:flex-end;gap:2px}
	.updated{color:var(--muted);font-size:12px;letter-spacing:.04em;display:flex;align-items:center;gap:7px}
	.dot{width:8px;height:8px;border-radius:50%;background:var(--faint);box-shadow:0 0 0 0 rgba(74,222,128,.5)}
	.dot.ok{background:var(--green);animation:pulse 2.4s infinite}
	@keyframes pulse{0%{box-shadow:0 0 0 0 rgba(74,222,128,.45)}70%{box-shadow:0 0 0 7px rgba(74,222,128,0)}100%{box-shadow:0 0 0 0 rgba(74,222,128,0)}}
	.clock{font-size:clamp(19px,2.1vw,27px);font-weight:700;line-height:1;letter-spacing:.02em}
	.clock .ap{font-size:.5em;color:var(--teal);margin-left:.25em;font-weight:700}
	.datestr{color:var(--muted);font-size:12px;letter-spacing:.18em;text-transform:uppercase}

	/* ---- grid ---- */
	.grid{display:grid;grid-template-columns:minmax(260px,1fr) minmax(420px,1.9fr) minmax(280px,1.05fr);
		gap:16px;flex:1}
	.col{display:flex;flex-direction:column;gap:16px}
	.card{border:1px solid var(--edge);border-radius:16px;background:var(--panel);
		padding:16px 18px;position:relative;overflow:hidden}
	.card h2{font-size:12px;letter-spacing:.2em;text-transform:uppercase;color:var(--ink);font-weight:700;
		display:flex;justify-content:space-between;align-items:center;gap:10px}
	.card h2 .sta{color:var(--faint);font-size:10px;letter-spacing:.12em;font-weight:600}
	/* Card-title links (-> /wind/): same treatment as the site's tile-heading
	   links — inherit color, gold underline, and the standard external ↗️ mark. */
	.card h2 a.h2link{color:inherit;text-decoration:none;border-bottom:1px solid rgba(212,168,81,.5);
		transition:color .15s,border-color .15s}
	.card h2 a.h2link:hover{color:var(--amber);border-bottom-color:var(--amber)}
	.card h2 a.h2link::after{content:"\00A0\2197\FE0F";font-size:.8em;opacity:.7}


	/* ---- title + view tabs ---- */
	.wx-head{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:10px 24px;
		border-bottom:1px solid var(--edge2);padding:4px 4px 0}
	.wx-title{padding-bottom:10px}
	.wx-title h1{font-size:clamp(24px,3vw,34px);line-height:1.1;font-weight:700;color:var(--ink);letter-spacing:-.01em}
	.wx-sub{color:var(--muted);font-size:13px;margin-top:4px}
	.wx-tabs{display:flex;gap:4px}
	.wx-tab{background:none;border:0;border-bottom:3px solid transparent;color:var(--muted);font:inherit;
		font-size:15px;font-weight:600;padding:12px 18px 11px;cursor:pointer;min-height:44px;transition:color .15s,border-color .15s}
	.wx-tab:hover{color:var(--ink)}
	.wx-tab[aria-selected="true"]{color:var(--ink);border-bottom-color:var(--teal)}
	.wx-tab:focus-visible,.go-btn:focus-visible{outline:3px solid var(--amber);outline-offset:2px}
	.wx-panel{display:flex;flex-direction:column;gap:16px}
	.wx-panel[hidden]{display:none}
	.wx-panel .windmap-card{margin-top:0}

	/* ---- Now: headline tiles ---- */
	.now-stats{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:12px}
	.ns{border:1px solid var(--edge);border-radius:14px;background:var(--panel);padding:14px 16px}
	.ns-k{font-size:11px;letter-spacing:.16em;text-transform:uppercase;color:var(--muted);font-weight:700}
	.ns-v{font-size:clamp(24px,2.4vw,32px);font-weight:700;font-variant-numeric:tabular-nums;line-height:1.15;margin-top:4px;color:var(--ink)}
	.ns-v.miss{color:var(--faint)}
	.ns-s{font-size:13px;color:var(--muted);margin-top:2px;min-height:1.3em}
	.now-grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr);gap:16px;align-items:start}
	.now-main,.now-side{display:flex;flex-direction:column;gap:16px}
	.now-go{margin-top:14px}
	.go-btn{display:inline-flex;align-items:center;min-height:44px;padding:0 18px;border-radius:10px;cursor:pointer;
		background:transparent;border:1px solid var(--edge2);color:var(--ink);font:inherit;font-size:14px;font-weight:600;
		transition:border-color .15s,color .15s}
	.go-btn:hover{border-color:var(--teal);color:var(--teal)}
	.now-teaser p{color:var(--muted);font-size:14px;line-height:1.45;margin:8px 0 14px}
	.nt-big{font-size:26px;font-weight:700;color:var(--ink)}
	.nf-score{display:flex;align-items:center;gap:12px;margin-top:10px}
	.nf-score .fish-dots{margin-top:0}
	.nf-vis{margin:0 0 16px}
	.now-teaser .nf-vis .fish-k{margin-top:0}
	.nh{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:8px;margin-top:14px}
	.nh:empty{display:none}
	.nh-h{border:1px solid var(--edge);border-radius:10px;padding:8px 6px;text-align:center}
	.nh-t{font-size:12px;color:var(--muted);font-weight:600}
	.nh-w{font-size:20px;font-weight:700;color:var(--ink)}
	.nh-w small{font-size:11px;color:var(--muted);font-weight:600}
	.nh-g{font-size:12px;color:var(--muted)}
	.nt-row4{margin-top:12px}
	@media (max-width:1100px){
		.now-stats{grid-template-columns:repeat(3,minmax(0,1fr))}
		.now-grid{grid-template-columns:minmax(0,1fr)}
	}
	@media (max-width:560px){
		.now-stats{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
		.ns{padding:10px 12px}
		.nh{grid-template-columns:repeat(3,minmax(0,1fr))}
		.wx-tabs{width:100%}
		.wx-tab{flex:1;padding:12px 4px 11px;font-size:14px}
	}

	/* tide */
	.tide-now{display:flex;align-items:flex-end;gap:10px;margin-top:12px}
	.tide-val{font-size:clamp(46px,6vw,74px);font-weight:800;line-height:.85;color:var(--ink)}
	.tide-unit{color:var(--muted);font-size:15px;letter-spacing:.14em;padding-bottom:10px;font-weight:700}
	.trend{margin-top:12px;display:inline-flex;align-items:center;gap:8px;font-weight:800;letter-spacing:.14em;
		text-transform:uppercase;font-size:14px}
	/* pressure-tendency arrow in the Conditions card (mirrors the tide trend) */
	.ptrend{margin-left:5px;font-size:.5em;vertical-align:middle;font-weight:800;line-height:1}
	.ptrend.steady{color:var(--faint)}
	/* rising = green, falling = red — shared by the tide trend + the pressure arrow
	   (base values tuned for the dark navy card; lighter overrides for the white card) */
	.trend.rising,.ptrend.rising{color:#39d353} .trend.falling,.ptrend.falling{color:#f0564a}
	:root[data-theme="light"] .trend.rising,:root[data-theme="light"] .ptrend.rising{color:#1e8e4a}
	:root[data-theme="light"] .trend.falling,:root[data-theme="light"] .ptrend.falling{color:#c0392b}
	.tide-cap{color:var(--faint);font-size:11px;margin-top:10px;letter-spacing:.03em}

	/* next tides */
	.nt{display:flex;flex-direction:column;gap:2px;margin-top:12px}
	.nt-row{display:grid;grid-template-columns:22px 46px 1fr auto;align-items:center;gap:10px;
		padding:9px 0;border-top:1px solid var(--edge)}
	.nt-row:first-child{border-top:0}
	.nt-ar{font-size:15px;font-weight:800}
	/* HIGH ▲ green, LOW ▼ red — consistent with the tide trend + pressure arrows */
	.nt-hi .nt-ar{color:#39d353} .nt-lo .nt-ar{color:#f0564a}
	:root[data-theme="light"] .nt-hi .nt-ar{color:#1e8e4a} :root[data-theme="light"] .nt-lo .nt-ar{color:#c0392b}
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
	/* The tide curve is drawn with preserveAspectRatio="none", so it takes the
	   shape of its box: give it a FIXED height so it never stretches, whatever
	   the column heights do. */
	.graph-card{display:flex;flex-direction:column}
	.graph-wrap{flex:none;height:230px;margin-top:8px;position:relative}
	@media (max-width:560px){ .graph-wrap{height:190px} }
	/* Desktop only: each column's LAST card grows so the three columns end level
	   (by default that's Sun & Moon, Wind and Conditions). Conditions spreads its
	   tiles to fill the extra height instead of leaving a blank strip. Guarded to
	   the 3-column layout: once the grid stacks (<=1100px) columns are auto-height,
	   and growing + min-height:0 with .card{overflow:hidden} would clip content. */
	@media (min-width:1101px){
		.grid .col > .card:last-child{flex:1 1 auto;min-height:0}
		[data-card="cond"]{display:flex;flex-direction:column}
		[data-card="cond"] .cond{flex:1;grid-auto-rows:1fr}
		[data-card="cond"] .cond .cell{display:flex;flex-direction:column;justify-content:center}
	}
	#tideOutage{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;text-align:center;padding:20px}
	#tideOutage .to-t{color:var(--teal);font-weight:800;letter-spacing:.18em;text-transform:uppercase;font-size:14px}
	#tideOutage .to-s{color:var(--faint);font-size:12.5px;max-width:440px;line-height:1.5}
	svg.tidegraph{width:100%;height:100%;display:block}
	.axis{fill:var(--muted);font-size:19px;font-family:ui-monospace,Menlo,monospace}
	.hilo-lbl{fill:var(--ink);font-size:21px;font-weight:700;font-family:ui-monospace,Menlo,monospace;text-anchor:middle}

	/* forecast */
	.fc{display:flex;flex-direction:column;gap:12px;margin-top:12px}
	.fc-row{display:grid;grid-template-columns:76px 1fr;gap:14px;align-items:start}
	.fc-when{color:var(--teal);font-weight:800;letter-spacing:.1em;text-transform:uppercase;font-size:13px;padding-top:2px}
	.fc-txt{color:#c3cedb;font-size:13.5px;line-height:1.5}   /* dark-mode base: readable on navy */
	:root[data-theme="light"] .fc-txt{color:#35566b}          /* light mode: original slate on white */

	/* 48-hour outlook — labelled rows: Sky (icons) / Rain (chance %) / Temp / time */
	.precip-card{display:flex;flex-direction:column}
	.px-body{display:flex;flex-direction:column;margin-top:10px;flex:1;min-height:0;justify-content:space-between}
	.px-sum{color:#cfe3f2;font-size:13px}
	.px-row{display:flex;align-items:center}
	.px-lbl{flex:0 0 42px;display:flex;align-items:center;justify-content:flex-end;padding-right:8px;font-size:10px;text-transform:uppercase;letter-spacing:.05em;color:var(--faint);font-weight:700;white-space:nowrap}
	.px-cells{flex:1;display:flex;min-width:0}
	.px-cells>div{flex:1;text-align:center;line-height:1.15}
	.px-row--icons .px-cells>div{display:flex;justify-content:center;align-items:center}
	.px-row--rain .px-cells>div,.px-row--tval .px-cells>div,.px-row--wdir .px-cells>div,.px-row--wspd .px-cells>div{font-size:11px;font-weight:700;font-variant-numeric:tabular-nums}
	.px-row--tval .px-cells>div{color:#ef9a5a}
	.px-row--wdir .px-cells>div{display:flex;justify-content:center;align-items:center;color:#9fbecb}
	.px-row--wspd .px-cells>div{color:#e6c374}
	.px-row--rain,.px-row--wdir{background:rgba(255,255,255,.035);border-radius:5px}
	.px-row--time .px-cells>div{font-size:10.5px;color:var(--muted);font-variant-numeric:tabular-nums}
	.px-row--time .d{color:var(--teal);font-weight:800;letter-spacing:.04em}

	/* wind */
	.wind-body{display:flex;align-items:center;gap:16px;margin-top:12px}
	.dial{width:120px;height:120px;flex:none;position:relative}
	.dial svg{width:100%;height:100%;transform:rotate(0deg)}
	.wind-read{display:flex;flex-direction:column}
	.wind-spd{font-size:52px;font-weight:800;line-height:.9;color:var(--ink)}
	.wind-lab{display:flex;gap:18px;margin-top:8px}
	.wind-lab .k{color:var(--faint);font-size:10px;letter-spacing:.14em;text-transform:uppercase}
	.wind-lab .v{font-weight:700;font-size:15px}
	.wind-arr-ico{display:inline-block;margin-right:6px;color:var(--teal);transition:transform .5s ease}

	/* waves */
	.wave-body{display:flex;align-items:center;gap:20px;margin-top:12px;flex-wrap:wrap}
	.wave-main{display:flex;align-items:flex-end;gap:8px}
	.wave-val{font-size:44px;font-weight:800;line-height:.9;color:var(--ink)}
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
	.cond .v{font-size:24px;font-weight:800;color:var(--ink)}
	.cond .k{color:var(--faint);font-size:10px;letter-spacing:.14em;text-transform:uppercase;margin-top:4px}

	/* fishing */
	.fish-out{display:flex;gap:12px;align-items:center;margin-top:12px;background:rgba(212,168,81,.12);border:1px solid rgba(212,168,81,.38);border-radius:12px;padding:10px 12px}
	.fish-score{flex:none;text-align:center;min-width:64px}
	.fish-word{white-space:nowrap;font-size:21px;font-weight:800;color:var(--teal);line-height:1}
	.fish-dots{display:flex;gap:3px;justify-content:center;margin-top:5px}
	.fish-dots i{width:7px;height:7px;border-radius:50%;background:var(--teal)}
	.fish-dots i.off{background:rgba(212,168,81,.3)}
	.fish-wg{display:flex;align-items:center;justify-content:center;gap:3px;margin-top:6px;font-size:11px;font-weight:700;color:var(--muted);line-height:1}
	.fish-wg svg{flex:none;color:var(--teal)}
	.fish-wg b{color:var(--teal);font-weight:800}
	.fish-out p{flex:1;min-width:0;font-size:12px;line-height:1.45;color:var(--muted)}
	.fish-k{color:var(--faint);font-size:10px;letter-spacing:.14em;text-transform:uppercase;margin-top:14px}
	.fish-tl{position:relative;height:26px;border-radius:7px;background:var(--fish-track);overflow:hidden;margin-top:6px}
	.fish-tl span{position:absolute;top:0;bottom:0}
	.fish-tl .nt{background:var(--fish-night)}
	.fish-tl .w{top:5px;bottom:5px;border-radius:4px}
	.fish-tl .w.best{background:var(--teal)} .fish-tl .w.good{background:var(--amber)} .fish-tl .w.fair{background:var(--faint)}
	.fish-tl .w.past{opacity:.35}
	.fish-tl .now{width:2px;margin-left:-1px;background:var(--ink)}
	.fish-ticks{position:relative;height:15px;font-size:11px;font-variant-numeric:tabular-nums}
	.fish-ticks span{position:absolute;transform:translateX(-50%);white-space:nowrap}
	.fish-ticks.tide span{color:var(--ink);font-weight:700}
	.fish-ticks.hrs span{color:var(--faint)}
	.fish-wins{display:grid;grid-template-columns:auto minmax(0,1fr);gap:7px 10px;margin-top:8px;font-size:12px;align-items:baseline}
	.fish-wins .chip{border-radius:5px;padding:2px 7px;font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase}
	.fish-wins .chip.best{background:rgba(212,168,81,.2);color:var(--fish-gold)}
	.fish-wins .chip.good{background:rgba(87,166,214,.18);color:var(--fish-blue)}
	.fish-wins .chip.fair{background:var(--fish-track);color:var(--muted)}
	.fish-wins b{font-variant-numeric:tabular-nums}
	.fish-wins .why{color:var(--muted)}
	.fish-wins .wx{display:block;color:var(--muted);font-size:11px;margin-top:2px}
	.fish-wins .past{opacity:.5}
	.fish-cond .v{font-size:19px}
	.fish-cond .cell{padding:10px 12px}
	.fish-cond .t{font-size:11px;margin-top:3px;color:var(--muted)}
	.fish-cond .t.down{color:var(--amber)} .fish-cond .t.fall{color:var(--red)} .fish-cond .t.up{color:var(--green)}
	.fish-sol{display:grid;grid-template-columns:1fr 1fr;gap:5px 12px;margin-top:6px;font-size:12px;font-variant-numeric:tabular-nums}
	.fish-sol .k{color:var(--faint);font-size:10px;letter-spacing:.12em;text-transform:uppercase;margin-right:4px}
	.fish-sol .full{grid-column:1/-1;color:var(--muted)}
	.fish-note{margin-top:12px;background:rgba(87,166,214,.12);border-radius:10px;padding:9px 11px;font-size:12px;line-height:1.45}
	.fish-foot{display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;margin-top:12px;font-size:11px;color:var(--faint)}
	.fish-foot a{color:var(--amber);text-decoration:none}
	.fish-foot a:hover{text-decoration:underline}
	/* full-width row: stacks on phones/tablets; three columns on the desktop board */
	.fish-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:12px 28px;margin-top:12px;
		grid-template-areas:"out" "win" "spots" "cond" "sol" "note" "foot"}
	.fish-grid > div > :first-child{margin-top:0}
	.fa-out{grid-area:out} .fa-win{grid-area:win} .fa-cond{grid-area:cond} .fa-sol{grid-area:sol} .fa-note{grid-area:note} .fa-foot{grid-area:foot} .fa-spots{grid-area:spots}
	.fa-spots[hidden]{display:none}
	.fish-spots{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;margin-top:6px}
	.fish-spot{display:grid;grid-template-columns:auto minmax(0,1fr);gap:2px 10px;align-items:start;text-align:left;background:var(--fish-track);border:1px solid var(--edge);border-radius:12px;padding:10px 12px;color:var(--ink);font:inherit;cursor:pointer}
	.fish-spot:hover,.fish-spot:focus-visible{border-color:var(--amber);outline:none}
	.fish-spot .rk{grid-row:1 / span 3;width:22px;height:22px;border-radius:50%;background:#0f7a6c;color:#fff;font-weight:800;font-size:12px;display:flex;align-items:center;justify-content:center;margin-top:1px}
	.fish-spot .nm{font-weight:700;font-size:13px}
	.fish-spot .sp{font-size:12px;color:var(--muted)}
	.fish-spot .td{font-size:11px;color:var(--faint)}
	.fish-spot .td.fit{color:var(--green)}
	.fa-note:empty, .fa-note:has(> [hidden]){display:none}
	@media (min-width:1101px){
		.fish-grid{grid-template-columns:minmax(250px,1fr) minmax(0,1.5fr) minmax(260px,1fr);align-items:start;
			grid-template-rows:auto auto 1fr auto auto;
			grid-template-areas:"out win cond" "sol win cond" "sol win note" "spots spots spots" "foot foot foot"}
	}

	/* alert */
	.weather-disclaimer{text-align:center;color:#6b7280;font-size:0.82rem;margin:0.5rem 0 1rem;letter-spacing:0.02em}
	.weather-legal{max-width:760px;margin:0 auto 1.5rem;text-align:center;color:#8a93a0;font-size:0.7rem;line-height:1.5}
	.weather-owner{max-width:760px;margin:0 auto 1.5rem;text-align:center;color:#8a93a0;font-size:0.7rem;line-height:1.5}
	/* embedded Wind & Radar map card */
	.windmap-card{margin-top:14px}
	/* break the map out of the card padding so it fills edge-to-edge (card clips
	   the rounded bottom corners via overflow:hidden) */
	.windmap-embed{width:auto;margin:12px -18px -16px;overflow:hidden}
	.windmap-embed #oycwm.embed{width:100%}
	@media (max-width:560px){ .windmap-embed{margin:12px -14px -14px} }
	/* ---- marine alert banner: one row per active NWS alert, colored by type ---- */
	.alerts[hidden]{display:none}
	.alerts{display:flex;flex-direction:column;gap:6px}
	.al{border-radius:12px;color:#fff;background:#44586C}
	.al.al-warn{background:#A3261B}
	.al.al-adv{background:#8A4B00}
	.al-row{display:flex;flex-wrap:wrap;align-items:center;gap:6px 14px;padding:10px 12px 10px 16px}
	.al-ico{flex:none;width:22px;height:22px}
	.al-head{font-weight:700;font-size:15px}
	.al-area{font-size:14px;opacity:.88;flex:1 1 200px;min-width:0}
	.al-btns{display:flex;gap:8px;margin-left:auto}
	.al-btn{min-height:40px;padding:0 14px;border-radius:8px;border:1px solid rgba(255,255,255,.55);background:transparent;
		color:#fff;font:inherit;font-size:14px;font-weight:600;cursor:pointer}
	.al-btn:hover{background:rgba(255,255,255,.14)}
	.al-btn:focus-visible{outline:3px solid #fff;outline-offset:2px}
	.al-more{display:none}
	.alerts.many .al-more{display:inline-flex;align-items:center}
	.alerts:not(.open) .al + .al{display:none}
	.al-txt{padding:0 16px 14px 52px;font-size:14px;line-height:1.5;max-width:900px}
	.al-txt p + p{margin-top:8px}
	.al-txt[hidden]{display:none}
	.fc-alert{border-left:0}
	.fc-alert h2{color:#A3261B}
	:root[data-theme="dark"] .fc-alert h2{color:#ff9f8f}
	.fc-alert .al-body{margin-top:10px;font-size:14px;line-height:1.55;color:var(--ink)}
	.fc-alert .al-body p + p{margin-top:8px}
	@media (max-width:560px){
		.al-row{padding:10px 10px 10px 12px}
		.al-txt{padding:0 12px 12px}
		.al-btns{margin-left:0;width:100%}
		.al-btn{flex:1}
	}

	.miss{color:var(--faint)}
	/* Mobile: minmax(0,1fr), NOT bare 1fr. A bare 1fr track is minmax(auto,1fr) —
	   the auto minimum inherits the content's min-content width, so the no-wrap
	   alert marquee text (~4000px) forces the whole track that wide. An explicit
	   0 minimum caps the track at the viewport; the marquee then clips normally. */
	@media (max-width:1100px){ .grid{grid-template-columns:minmax(0,1fr)}}
	@media (max-width:560px){ .topbar{padding:12px 14px} .card{padding:14px} }

	/* ===================== REDESIGN LOOK (step 4) =====================
	   Inter for text, Source Serif 4 for the big headings, the mockup's
	   flat harbor-grey ground and navy ink in light mode; dark mode keeps
	   its navy palette. On phones the view tabs become a bottom tab bar. */
	body{font-family:Inter,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif}
	:root[data-theme="light"]{
		--bg1:#F2F5F8; --bg2:#F2F5F8; --edge:#DCE3EA; --edge2:#C4D0DC;
		--ink:#10263F; --muted:#56677A; --faint:#687889;
		--teal:#B0842F; --teal2:#8A6420; --amber:#0F68A6;
	}
	:root[data-theme="light"] body{background:#F2F5F8}
	/* one flat ground in dark mode too: the old radial gradient was sized to the
	   first screen, so lower down the page showed a second, different navy */
	:root[data-theme="dark"],:root[data-theme="dark"] body{background:#0a2440}
	:root[data-theme="light"]{background:#F2F5F8}
	.wx-title h1{font-family:"Source Serif 4",Georgia,serif;font-weight:600;font-size:clamp(24px,2.6vw,30px);letter-spacing:0}
	.wx-tab{display:inline-flex;align-items:center;gap:6px;font-size:16px;min-height:48px;padding:0 20px}
	.wx-ico{display:none;width:24px;height:24px;flex:none}
	:root[data-theme="light"] .wx-tab[aria-selected="true"]{color:#10263F;border-bottom-color:#B0842F}

	/* headline tiles: one joined strip, like the mockup */
	.now-stats{gap:1px;background:var(--edge);border:1px solid var(--edge);border-radius:14px;overflow:hidden;
		grid-template-columns:repeat(auto-fit,minmax(170px,1fr))}
	.ns{border:0;border-radius:0;padding:16px 20px;background:var(--panel)}
	:root[data-theme="dark"] .ns{background:#0d2c4c}
	.ns-k{text-transform:none;letter-spacing:0;font-size:14px;font-weight:500;color:var(--muted)}
	.ns-v{font-size:clamp(28px,3vw,42px);font-weight:600;letter-spacing:-.5px;margin-top:2px}
	.ns-s{font-size:14px}

	/* cards: sentence-style headings, softer corners */
	.wx-panel .card{border-radius:14px;padding:18px 22px}
	.wx-panel .card > h2{font-size:16px;letter-spacing:0;text-transform:none;font-weight:600;margin-bottom:6px}
	.wx-panel .card > h2 .sta{font-family:Inter,-apple-system,"Segoe UI",Arial,sans-serif;font-weight:500;letter-spacing:.06em}
	.now-main .card > h2,.fc-full-card > h2{font-family:"Source Serif 4",Georgia,serif;font-size:21px}
	.fc-alert > h2{text-transform:none!important;letter-spacing:0!important;font-size:18px!important}
	.go-btn{border-radius:8px}
	.windmap-embed{margin-left:-22px;margin-right:-22px}

	/* ---- phones: bottom tab bar + slimmer top bar ---- */
	@media (max-width:700px){
		body{padding-bottom:calc(84px + env(safe-area-inset-bottom,0px))}
		.wx-head{border-bottom:0;padding-bottom:0}
		.wx-tabs{position:fixed;left:0;right:0;bottom:0;z-index:1500;width:auto;margin:0;gap:0;
			display:grid;grid-template-columns:repeat(4,minmax(0,1fr));
			padding-bottom:env(safe-area-inset-bottom,0px);background:#FFFFFF;border-top:1px solid var(--edge);
			box-shadow:0 -2px 10px rgba(16,38,63,.06)}
		:root[data-theme="dark"] .wx-tabs{background:#071d35;border-top-color:rgba(245,239,226,.16)}
		.wx-tab{flex-direction:column;justify-content:center;gap:2px;min-height:64px;padding:0;border:0;
			font-size:13px;color:var(--muted)}
		.wx-tab[aria-selected="true"],:root[data-theme="light"] .wx-tab[aria-selected="true"]{color:#8A6420;border:0}
		:root[data-theme="dark"] .wx-tab[aria-selected="true"]{color:var(--teal)}
		.wx-ico{display:block}
		.tb-title,.clockwrap{display:none}
		.wx-panel .card{padding:14px}
		.windmap-embed{margin-left:-14px;margin-right:-14px}
		.now-stats{grid-template-columns:repeat(2,minmax(0,1fr))}
		.ns{padding:10px 12px}
		.ns-v{font-size:30px}
		.ns-k,.ns-s{font-size:13px}
	}
	/* Chart tab: the map is the page — no card heading, map starts at the card top */
	.sr-only{position:absolute!important;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
	#panel-chart .windmap-card{padding-top:10px}
	#panel-chart .windmap-embed{margin-top:0}
</style>
</head>
<body>

<div class="wrap">
	<!-- HEADER: burgee logo left · Live Conditions + live clock right -->
	<div class="topbar">
		<div class="tb-left">
			<a class="tb-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> — Home"><?php oyc_burgee( 'tb-logo' ); ?></a>
			<div class="tb-title">Mamaroneck Harbor &middot; Live Conditions</div>
		</div>
		<div class="theme-toggle" id="themeToggle" role="group" aria-label="Color theme">
			<button type="button" class="tt-opt" data-set="dark" aria-pressed="false"><span aria-hidden="true">&#9790;</span> Dark</button>
			<button type="button" class="tt-opt" data-set="light" aria-pressed="true"><span aria-hidden="true">&#9728;</span> Light</button>
		</div>
		<button class="tb-toggle" id="navToggle" aria-expanded="false" aria-controls="boardNav" aria-label="Menu"><span class="tb-bars" aria-hidden="true"></span></button>
		<div class="clockwrap">
			<div class="updated"><span class="dot" id="statusDot"></span><span id="updated">Connecting&hellip;</span></div>
			<div class="clock mono"><span id="clock">--:--:--</span><span class="ap" id="ampm">--</span></div>
			<div class="datestr" id="datestr">&mdash;</div>
		</div>
		<nav class="tb-nav" id="boardNav" aria-label="Site menu"><?php echo $oyc_weather_menu; ?></nav>
	</div>
	<script>
	/* Standalone board: promote the lazy-load placeholder logo to its real src. */
	(function(){[].forEach.call(document.querySelectorAll('.tb-logo'),function(i){var d=i.getAttribute('data-src')||i.getAttribute('data-lazy-src')||i.getAttribute('data-smush-src');if(d){i.src=d;i.removeAttribute('loading');}});})();
	</script>

	<!-- ALERT (prominent, at the top) -->
	<section class="alerts" id="alertBox" aria-label="Marine alerts" hidden></section>

	<!-- TITLE + VIEW TABS (Now · Forecast · Chart · Fishing) -->
	<div class="wx-head">
		<div class="wx-title">
			<h1>Mamaroneck Harbor</h1>
			<p class="wx-sub" id="wxSub">Live conditions</p>
		</div>
		<div class="wx-tabs" role="tablist" aria-label="Weather views">
			<button type="button" role="tab" class="wx-tab" id="tab-now" data-tab="now" aria-controls="panel-now" aria-selected="true"><svg class="wx-ico" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18ZM12 7.5V12l3 2"/></svg><span>Now</span></button>
			<button type="button" role="tab" class="wx-tab" id="tab-forecast" data-tab="forecast" aria-controls="panel-forecast" aria-selected="false" tabindex="-1"><svg class="wx-ico" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16v14H4zM4 10h16M8 3v4M16 3v4"/></svg><span>Forecast</span></button>
			<button type="button" role="tab" class="wx-tab" id="tab-chart" data-tab="chart" aria-controls="panel-chart" aria-selected="false" tabindex="-1"><svg class="wx-ico" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6l6-2 6 2 6-2v14l-6 2-6-2-6 2zM9 4v14M15 6v14"/></svg><span>Chart</span></button>
			<button type="button" role="tab" class="wx-tab" id="tab-fishing" data-tab="fishing" aria-controls="panel-fishing" aria-selected="false" tabindex="-1"><svg class="wx-ico" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12c2.6-4 6-6 9.5-6 3.5 0 6.2 2 8 6-1.8 4-4.5 6-8 6-3.5 0-6.9-2-9.5-6ZM3 12 1 8.5M3 12l-2 3.5M16.5 10.5h.01"/></svg><span>Fishing</span></button>
		</div>
	</div>

	<!-- ===== NOW ===== -->
	<section class="wx-panel" id="panel-now" role="tabpanel" aria-labelledby="tab-now">
		<div class="now-stats" aria-label="Current conditions">
			<div class="ns"><div class="ns-k">Wind</div><div class="ns-v" id="nsWind">&mdash;</div><div class="ns-s" id="nsWindS">&nbsp;</div></div>
			<div class="ns"><div class="ns-k">Seas</div><div class="ns-v" id="nsSeas">&mdash;</div><div class="ns-s" id="nsSeasS">&nbsp;</div></div>
			<div class="ns"><div class="ns-k">Tide</div><div class="ns-v" id="nsTide">&mdash;</div><div class="ns-s" id="nsTideS">&nbsp;</div></div>
			<div class="ns"><div class="ns-k">Pressure</div><div class="ns-v" id="nsPres">&mdash;</div><div class="ns-s" id="nsPresS">&nbsp;</div></div>
			<div class="ns"><div class="ns-k">Air</div><div class="ns-v" id="nsAir">&mdash;</div><div class="ns-s" id="nsAirS">&nbsp;</div></div>
			<div class="ns"><div class="ns-k">Water</div><div class="ns-v" id="nsWater">&mdash;</div><div class="ns-s" id="nsWaterS">&nbsp;</div></div>
		</div>
		<div class="now-grid">
			<div class="now-main">
				<div class="card" data-card="forecast">
					<h2>Marine Forecast <span class="sta" id="fcZone">NWS Zone ANZ335</span></h2>
					<div class="fc" id="forecast"><div class="fc-row"><span class="miss">Loading forecast&hellip;</span></div></div>
					<div class="nh" id="nextHours" aria-label="Next 12 hours in 2-hour blocks: wind, gusts and seas"></div>
					<div class="now-go"><button type="button" class="go-btn" data-go="forecast">Full 7-day forecast</button></div>
				</div>
				<div class="card graph-card" data-card="graph">
					<h2>Tide <span class="sta" id="tideSta">NOAA</span></h2>
					<div class="graph-wrap"><svg class="tidegraph" id="tideGraph" viewBox="0 0 1000 320" preserveAspectRatio="none" role="img" aria-label="Tide curve for the next 48 hours"></svg></div>
					<div class="nt nt-row4" id="nextTides">
						<div class="nt-row"><span class="miss" style="grid-column:1/-1">Loading&hellip;</span></div>
					</div>
				</div>
			</div>
			<div class="now-side">
				<div class="card" data-card="sunmoon">
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
				<div class="card now-teaser" data-card="fishteaser">
					<h2>Fishing today</h2>
					<div class="nf-score"><span class="nt-big" id="nowFishWord">&mdash;</span><span class="fish-dots" id="nowFishDots" aria-hidden="true"></span></div>
					<p id="nowFishWhy">Working out the day&rsquo;s outlook&hellip;</p>
					<div class="nf-vis">
						<div class="fish-k">Best windows</div>
						<div class="fish-tl" id="nowFishTl" role="img" aria-label="Best fishing windows, 5 AM to 9 PM"></div>
						<div class="fish-ticks tide" id="nowFishTide" aria-hidden="true"></div>
						<div class="fish-ticks hrs" aria-hidden="true"><span style="left:6.25%">6a</span><span style="left:25%">9a</span><span style="left:43.75%">12p</span><span style="left:62.5%">3p</span><span style="left:81.25%">6p</span><span style="left:96%">9p</span></div>
						<div class="fish-wins" id="nowFishWins"></div>
					</div>
					<button type="button" class="go-btn" data-go="fishing">See fishing details</button>
				</div>
			</div>
		</div>
		<!-- data sources for the tiles above: the original readout cards stay in
		     the page (hidden) so every loader keeps writing where it always did -->
		<div class="now-src" hidden>
			<div class="card" data-card="tide">
				<h2>Tide Forecast</h2>
				<div class="tide-now"><span class="tide-val" id="tideVal">&mdash;</span><span class="tide-unit">ft MLLW</span></div>
				<div class="trend" id="tideTrend"><span id="tideTrendAr">&mdash;</span> <span id="tideTrendTxt">&mdash;</span></div>
				<div class="tide-cap">Predicted level, interpolated to the minute</div>
			</div>
			<div class="card" data-card="wind">
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
			<div class="card" data-card="waves">
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
			<div class="card" data-card="cond">
				<h2>Conditions</h2>
				<div class="cond">
					<div class="cell"><div class="v" id="airTemp">&mdash;</div><div class="k">Air Temp</div></div>
					<div class="cell"><div class="v" id="hiLoTemp">&mdash;</div><div class="k">Today Hi / Lo</div></div>
					<div class="cell"><div class="v" id="waterTemp">&mdash;</div><div class="k">Water Temp</div></div>
					<div class="cell"><div class="v"><span id="pressure">&mdash;</span><span class="ptrend" id="pressureTrend"></span></div><div class="k">Pressure</div></div>
					<div class="cell"><div class="v" id="humidity">&mdash;</div><div class="k">Humidity</div></div>
					<div class="cell"><div class="v" id="visibility">&mdash;</div><div class="k">Visibility</div></div>
				</div>
			</div>
		</div>
	</section>

	<!-- ===== FORECAST ===== -->
	<section class="wx-panel" id="panel-forecast" role="tabpanel" aria-labelledby="tab-forecast" hidden>
		<div class="card fc-alert" id="fcAlert" hidden></div>
	<?php echo oyc_forecast_table_html(); // full-width multi-model forecast table ?>
		<div class="card fc-full-card">
			<h2>NWS Forecast &middot; Western Long Island Sound</h2>
			<div class="fc" id="forecastFull"><div class="fc-row"><span class="miss">Loading forecast&hellip;</span></div></div>
		</div>
	</section>

	<!-- ===== CHART ===== -->
	<section class="wx-panel" id="panel-chart" role="tabpanel" aria-labelledby="tab-chart" hidden>
		<!-- WIND & RADAR MAP (inline scoped component — no iframe) -->
		<div class="card windmap-card">
			<h2 class="sr-only">Wind &amp; Radar Map</h2>
			<div class="windmap-embed"><?php echo oyc_wind_map_html( true ); ?></div>
		</div>
	</section>

	<!-- ===== FISHING ===== -->
	<section class="wx-panel" id="panel-fishing" role="tabpanel" aria-labelledby="tab-fishing" hidden>
		<!-- FISHING (full-width row: outlook + solunar | best windows | conditions + species) -->
		<div class="card fish-card" id="fishCard">
			<h2>Fishing <span class="sta" id="fishDay">Today</span></h2>
			<div class="fish-grid">
				<div class="fa-out">
					<div class="fish-out">
						<div class="fish-score"><div class="fish-word" id="fishWord">&mdash;</div><div class="fish-dots" id="fishDots" aria-hidden="true"></div><div class="fish-wg" id="fishWordWind"></div></div>
						<p id="fishWhy">Working out the day&rsquo;s outlook&hellip;</p>
					</div>
				</div>
				<div class="fa-win">
					<div class="fish-k">Best windows</div>
					<div class="fish-tl" id="fishTl" role="img" aria-label="Best fishing windows, 5 AM to 9 PM"></div>
					<div class="fish-ticks tide" id="fishTide" aria-hidden="true"></div>
					<div class="fish-ticks hrs" aria-hidden="true"><span style="left:6.25%">6a</span><span style="left:25%">9a</span><span style="left:43.75%">12p</span><span style="left:62.5%">3p</span><span style="left:81.25%">6p</span><span style="left:96%">9p</span></div>
					<div class="fish-wins" id="fishWins"></div>
				</div>
				<div class="fa-cond">
					<div class="cond fish-cond">
						<div class="cell"><div class="v" id="fishWater">&mdash;</div><div class="k">Water</div><div class="t" id="fishWaterT"></div></div>
						<div class="cell"><div class="v" id="fishPres">&mdash;</div><div class="k">Pressure mb</div><div class="t" id="fishPresT"></div></div>
						<div class="cell"><div class="v" id="fishWind">&mdash;</div><div class="k" id="fishWindK">Max wind</div><div class="t" id="fishWindT"></div></div>
						<div class="cell"><div class="v" id="fishCur">&mdash;</div><div class="k">Peak current</div><div class="t" id="fishCurT"></div></div>
					</div>
				</div>
				<div class="fa-sol">
					<div class="fish-k">Solunar</div>
					<div class="fish-sol" id="fishSol"></div>
				</div>
				<div class="fa-note"><div class="fish-note" id="fishNote" hidden></div></div>
				<div class="fa-spots" id="fishSpotsWrap" hidden>
					<div class="fish-k">Top spots today</div>
					<div class="fish-spots" id="fishSpots"></div>
				</div>
				<div class="fa-foot"><div class="fish-foot"><span>Tides and current: NOAA &middot; Solunar: calculated</span><a href="https://dec.ny.gov/things-to-do/saltwater-fishing/recreational-fishing-regulations" target="_blank" rel="noopener">NY DEC rules &#8599;</a></div></div>
			</div>
		</div>
	</section>

	<!-- DISCLAIMER -->
	<p class="weather-disclaimer"><em>Weather forecasts are best treated as an opinion. Poseidon always has the final word.</em></p>
	<p class="weather-legal">The chart, wind, radar, tide, current, and navigation information on this page is provided for general information and planning purposes only and is <strong>not for navigation</strong>. It is drawn from third-party sources, may be delayed, incomplete, or inaccurate, and is no substitute for official government charts and publications, a proper lookout, and prudent seamanship. Orienta Yacht Club makes no representation or warranty, express or implied, as to its accuracy, completeness, or fitness for any purpose, and assumes no liability for any loss, damage, or injury arising from its use or from any errors or omissions. You use this information entirely at your own risk and are responsible for verifying it against official sources.</p>
	<p class="weather-owner">&copy; <?php echo esc_html( date( 'Y' ) ); ?> Intualytics LLC. All rights reserved. This weather board &mdash; including its design, data compilations, and underlying software &mdash; is the property of Intualytics LLC. Intualytics&trade; is a trademark of Intualytics LLC.</p>
</div>

<script>
(function(){
	"use strict";

	// ---------- SITE MENU (mobile hamburger) ----------
	var sbNav = document.querySelector('.tb-nav');
	var sbBtn = document.querySelector('.tb-toggle');
	if(sbNav && sbBtn){
		sbBtn.addEventListener('click', function(){
			var open = sbNav.classList.toggle('open');
			sbBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
	}

	// ---------- COLOR THEME (dark / light, persisted per visitor; default light) ----------
	(function(){
		var KEY = 'oyc_theme', tt = document.getElementById('themeToggle');
		function apply(t){
			t = (t === 'dark') ? 'dark' : 'light';
			document.documentElement.setAttribute('data-theme', t);
			try { localStorage.setItem(KEY, t); } catch(e){}
			if (tt) [].forEach.call(tt.querySelectorAll('.tt-opt'), function(b){
				b.setAttribute('aria-pressed', b.getAttribute('data-set') === t ? 'true' : 'false');
			});
		}
		if (tt) tt.addEventListener('click', function(e){
			var b = e.target.closest ? e.target.closest('.tt-opt') : null;
			if (b) apply(b.getAttribute('data-set'));
		});
		// sync the pill to whatever the <head> script already applied
		apply(document.documentElement.getAttribute('data-theme'));
	})();

	// ---------- VIEW TABS (Now · Forecast · Chart · Fishing) ----------
	// One panel shows at a time. The open tab lives in the URL hash (#chart etc.)
	// so a link or bookmark opens straight to it. Panels that were hidden get a
	// window resize nudge when shown, so the Leaflet map and the forecast table
	// measure themselves at their real size.
	var WX_TABS = ['now','forecast','chart','fishing'];
	var wxTab = null;
	function showTab(id, opts){
		opts = opts || {};
		if(WX_TABS.indexOf(id) < 0){ id = 'now'; }
		if(id === wxTab){ return; }
		wxTab = id;
		WX_TABS.forEach(function(t){
			var on = (t === id), b = document.getElementById('tab-'+t), p = document.getElementById('panel-'+t);
			if(b){ b.setAttribute('aria-selected', on ? 'true' : 'false'); b.tabIndex = on ? 0 : -1; }
			if(p){ p.hidden = !on; }
		});
		document.documentElement.setAttribute('data-wx-tab', id);
		if(!opts.noHash){
			try{ history.replaceState(null, '', id === 'now' ? location.pathname + location.search : '#' + id); }catch(e){}
		}
		if(opts.focus){ var fb = document.getElementById('tab-'+id); if(fb){ fb.focus(); } }
		if(opts.scroll){
			var hd = document.querySelector('.wx-head');
			if(hd && hd.getBoundingClientRect().top < 0){ hd.scrollIntoView({ block:'start' }); }
			/* phones: on the Chart tab bring the map toolbar to the top of the screen
			   so the map itself is in view, not below the site header */
			if(id === 'chart' && window.matchMedia('(max-width:700px)').matches){
				var wc = document.querySelector('#panel-chart .windmap-card');
				if(wc){ setTimeout(function(){ try{ window.scrollTo({ top: wc.getBoundingClientRect().top + window.pageYOffset - 6, behavior:'smooth' }); }catch(e){} }, 60); }
			}
		}
		// let hidden-at-load widgets re-measure now that they are visible
		setTimeout(function(){ try{ window.dispatchEvent(new Event('resize')); }catch(e){} }, 30);
	}
	window.oycWxTab = function(id){ showTab(id, { scroll:true }); };
	(function(){
		var bar = document.querySelector('.wx-tabs');
		if(bar){
			bar.addEventListener('click', function(e){
				var b = e.target.closest ? e.target.closest('.wx-tab') : null;
				if(b){ showTab(b.getAttribute('data-tab'), { scroll:true }); }
			});
			bar.addEventListener('keydown', function(e){
				var i = WX_TABS.indexOf(wxTab), n = null;
				if(e.key === 'ArrowRight'){ n = WX_TABS[(i+1) % WX_TABS.length]; }
				else if(e.key === 'ArrowLeft'){ n = WX_TABS[(i+WX_TABS.length-1) % WX_TABS.length]; }
				else if(e.key === 'Home'){ n = WX_TABS[0]; }
				else if(e.key === 'End'){ n = WX_TABS[WX_TABS.length-1]; }
				if(n){ e.preventDefault(); showTab(n, { focus:true }); }
			});
		}
		// "Full 7-day forecast", "See fishing details", "Open the chart"
		document.addEventListener('click', function(e){
			var g = e.target.closest ? e.target.closest('[data-go]') : null;
			if(g){ e.preventDefault(); showTab(g.getAttribute('data-go'), { scroll:true }); }
		});
		window.addEventListener('hashchange', function(){
			var h = (location.hash || '').replace('#','');
			if(WX_TABS.indexOf(h) >= 0){ showTab(h, { noHash:true }); }
		});
		var h0 = (location.hash || '').replace('#','');
		showTab(WX_TABS.indexOf(h0) >= 0 ? h0 : 'now', { noHash:true });
	})();

	// ===== EDITABLE CONFIG — verify against your Yodeck dashboard =====
	var CFG = {
		TIDE_STATION: '8518091',   // NOAA CO-OPS tide predictions (Mamaroneck, LI Sound)
		MET_STATION:  '8516945',   // NOAA CO-OPS wind/met (Kings Point, NY — nearest reporting)
		NWS_ZONE:     'ANZ335',    // NWS marine forecast zone (LI Sound West)
		CWF_OFFICE:   'OKX',       // NWS office issuing the Coastal Waters Forecast for ANZ3xx
		LAT: 40.939, LON: -73.734, // Mamaroneck (sun/moon + land+marine alerts)
		EXRX_LAT: 40.8833, EXRX_LON: -73.7283 // NDBC 44022 / Execution Rocks (Open-Meteo wind + wave)
	};
	document.getElementById('tideSta').textContent = 'NOAA';
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
		if(ok){ var n=new Date(); $('updated').textContent = 'Last good update ' + hmt(n);
			if($('wxSub')){ $('wxSub').textContent = 'Updated ' + hmt(n) + ' \u00b7 ' + DOW[n.getDay()] + ', ' + MON[n.getMonth()] + ' ' + n.getDate(); } }
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
		               : (mode === 'bundled') ? 'Predicted level — harmonic tide forecast, interpolated to the minute'
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
		var dayStart=new Date(); dayStart.setHours(0,0,0,0);
		var end = dayStart.getTime() + 48*3600*1000;
		// LEAD with the club's manually-forecasted tide bundle (deterministic
		// harmonic predictions synthesized from NOAA). It always covers the current
		// dates, so the tide card + graph render instantly and stay correct
		// regardless of NOAA uptime. Live NOAA is kept as a SECONDARY source only:
		// refreshTideCache() pulls it in the background to keep the deep fallback
		// cache warm, and loadTidesLive() is the fallback path if the bundle itself
		// is ever unavailable. (The bundle file is not modified — only read first.)
		bundledTides().then(function(ev){
			var series = eventsToSeries(ev, dayStart.getTime(), end);
			if(series.length < 2) throw new Error('bundle series too short');
			tideSeries = series;
			drawGraph(); updateCurrentTide(); renderNextTides(ev);
			setTideCached('bundled'); markUpdated(true);
		}).catch(function(){
			loadTidesLive(dayStart, end);
		});
	}
	// Fallback used only when the bundle can't be loaded: the original
	// live-NOAA-first chain (live 30-min series → wide cache → outage notice, and
	// live hi/lo → cache) for both the graph series and the "Next Tides" list.
	function loadTidesLive(dayStart, end){
		coops('predictions', { begin_date:ymdhm(dayStart), range:48, datum:'MLLW', interval:'30' })
			.then(function(j){
				if(!j || !j.predictions || !j.predictions.length) throw new Error('no predictions');
				tideSeries = parsePreds(j.predictions);
				drawGraph(); updateCurrentTide(); setTideCached('live'); markUpdated(true);
			}).catch(function(){
				var c = readTideCache('series');
				if(c){
					var win = parsePreds(c).filter(function(p){ return p.t.getTime()>=dayStart.getTime() && p.t.getTime()<=end; });
					tideSeries = (win.length>=2) ? win : parsePreds(c);
					drawGraph(); updateCurrentTide(); setTideCached('cached');
					return;
				}
				showTideOutage();
			});
		coops('predictions', { begin_date:ymdhm(new Date()), range:48, datum:'MLLW', interval:'hilo' })
			.then(function(j){
				if(!j || !j.predictions || !j.predictions.length) throw new Error('no hilo');
				renderNextTides(j.predictions);
			}).catch(function(){
				var c = readTideCache('hilo');
				if(c){ renderNextTides(c); }
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
		nextTideEvts = out;
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
			ticks+='<line x1="'+gx.toFixed(1)+'" y1="'+padT+'" x2="'+gx.toFixed(1)+'" y2="'+(H-padB)+'" stroke="rgba(11,42,74,'+(major?'.16':'.08')+')"'+(major?' stroke-width="1.5"':'')+'/>';
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
			+ '<path d="'+d+'" fill="none" stroke="#b0842f" stroke-width="2.4" stroke-linejoin="round"/>'
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
	// PRIMARY wind: Open-Meteo model at the Execution Rocks (44022) position — fills
	// in while the UConn buoy is dark. Fallback chain: Kings Point CO-OPS → NWS ASOS.
	function loadWind(){
		fetch('https://api.open-meteo.com/v1/forecast?latitude='+CFG.EXRX_LAT+'&longitude='+CFG.EXRX_LON+'&current=wind_speed_10m,wind_direction_10m,wind_gusts_10m&wind_speed_unit=kn')
			.then(function(r){ if(!r.ok) throw new Error('om '+r.status); return r.json(); })
			.then(function(j){
				var c = j && j.current;
				if(!c || c.wind_speed_10m == null) throw new Error('no om wind');
				$('windSpd').textContent = Math.round(c.wind_speed_10m);
				var deg = (c.wind_direction_10m != null) ? c.wind_direction_10m : null;
				$('windDir').textContent = (deg != null) ? dirCardinal(deg) : '—';
				$('windGust').textContent = (c.wind_gusts_10m != null) ? (Math.round(c.wind_gusts_10m)+' kt') : '—';
				$('windSta').textContent = 'Execution Rock · current';
				drawDial(deg); markUpdated(true);
			}).catch(function(){ loadWindNoaa(); });
	}
	function loadWindNoaa(){
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
		loadPressureTrend();
	}
	// Pressure tendency: the 3-hour change from the harbor pressure forecast
	// (Open-Meteo pressure_msl — same source as the Marine Forecast table) drives a
	// ▲ rising / ▼ falling / → steady glyph beside the Pressure value, mirroring the
	// tide trend indicator.
	function loadPressureTrend(){
		fetch('https://api.open-meteo.com/v1/forecast?latitude='+CFG.EXRX_LAT+'&longitude='+CFG.EXRX_LON+'&hourly=pressure_msl&past_hours=6&forecast_hours=2&timezone=America/New_York')
			.then(function(r){ if(!r.ok) throw new Error('om '+r.status); return r.json(); })
			.then(function(j){
				var H=j&&j.hourly, el=$('pressureTrend');
				if(!el||!H||!H.pressure_msl||!H.time) return;
				var now=Date.now(), bi=0, bd=1e15;
				for(var i=0;i<H.time.length;i++){ var dd=Math.abs(new Date(H.time[i]).getTime()-now); if(dd<bd){ bd=dd; bi=i; } }
				var cur=H.pressure_msl[bi], prev=H.pressure_msl[Math.max(0,bi-3)];
				if(cur==null||prev==null) return;
				var delta=cur-prev;
				if(Math.abs(delta)<0.4){ el.className='ptrend steady'; el.innerHTML='&#8594;'; el.title='Steady ('+delta.toFixed(1)+' mb / 3h)'; }
				else { var rising=delta>0; el.className='ptrend '+(rising?'rising':'falling'); el.innerHTML=rising?'&#9650;':'&#9660;'; el.title=(rising?'Rising':'Falling')+' '+Math.abs(delta).toFixed(1)+' mb / 3h'; }
			}).catch(function(){});
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
			function fcRows(list){
				return list.map(function(p){
					return '<div class="fc-row"><div class="fc-when">'+p.name+'</div>'
						+ '<div class="fc-txt">'+p.body+'</div></div>';
				}).join('');
			}
			$('forecast').innerHTML = fcRows(periods.slice(0,2));
			if($('forecastFull')){ $('forecastFull').innerHTML = fcRows(periods); }
			renderWaves(periods);
			markUpdated(true);
		}).catch(function(){});
	}

	// 48-HOUR OUTLOOK — hourly temperature + precipitation-chance at the harbor
	// (Open-Meteo, keyless + CORS-ok). Renders a weather-icon strip with the precip
	// chance under each hour, a temperature curve with labels, and a time axis.
	function loadPrecip(){
		// The 48-Hour Outlook card was replaced by the full-width forecast table
		// (inc/forecast-table.php). This light fetch now only feeds the Conditions
		// card's today Hi/Lo from the Execution Rock daily forecast.
		fetch('https://api.open-meteo.com/v1/forecast?latitude='+CFG.EXRX_LAT+'&longitude='+CFG.EXRX_LON+'&daily=temperature_2m_max,temperature_2m_min&hourly=temperature_2m,wind_speed_10m,wind_gusts_10m,wind_direction_10m&wind_speed_unit=kn&temperature_unit=fahrenheit&timezone=America/New_York&forecast_days=2')
			.then(function(r){ if(!r.ok) throw new Error('om '+r.status); return r.json(); })
			.then(function(j){
				var D = j && j.daily;
				if(j && j.hourly && j.hourly.time){ nhWind = j.hourly; renderNextHours(); }
				if(D && D.temperature_2m_max && D.temperature_2m_max.length && D.temperature_2m_min && D.temperature_2m_min.length){
					nowHiLo = [Math.round(D.temperature_2m_max[0]), Math.round(D.temperature_2m_min[0]), null, null];
					// when today's high and low happen, from the hourly temperatures
					var H = j.hourly, day = (D.time && D.time[0]) || '';
					if(H && H.temperature_2m && day){
						var hi = null, lo = null;
						H.time.forEach(function(t, k){
							var v = H.temperature_2m[k]; if(v == null || t.slice(0, 10) !== day) return;
							if(hi === null || v > H.temperature_2m[hi]) hi = k;
							if(lo === null || v < H.temperature_2m[lo]) lo = k;
						});
						if(hi !== null){ nowHiLo[2] = new Date(H.time[hi]); nowHiLo[3] = new Date(H.time[lo]); }
					}
					$('hiLoTemp').innerHTML = '<span style="color:#ef9a5a">'+Math.round(D.temperature_2m_max[0])+'&deg;</span>'
						+ '<span style="color:var(--faint)"> / </span>'
						+ '<span style="color:#57a6d6">'+Math.round(D.temperature_2m_min[0])+'&deg;</span>';
					markUpdated(true);
				}
			}).catch(function(){});
	}
	// Icon blends the WMO code with the hour's rain chance (pp %) and cloud cover
	// (cc %) so a "mainly clear" code with a real shower chance still reads wet, and
	// dry hours vary clear -> partly -> overcast instead of all showing the sun.
	function wxIcon(c, pp, cc){
		pp = (pp==null?0:pp); cc = (cc==null?0:cc);
		var ray=[0,45,90,135,180,225,270,315].map(function(a){var r=a*Math.PI/180;return '<line x1="'+(12+Math.cos(r)*7.3).toFixed(1)+'" y1="'+(12+Math.sin(r)*7.3).toFixed(1)+'" x2="'+(12+Math.cos(r)*9.6).toFixed(1)+'" y2="'+(12+Math.sin(r)*9.6).toFixed(1)+'" stroke="#f3c34e" stroke-width="1.5" stroke-linecap="round"/>';}).join('');
		var sun='<circle cx="12" cy="12" r="5" fill="#f3c34e"/>'+ray;
		var cloud='<path d="M7.5 17a3.6 3.6 0 0 1 .2-7.2 5 5 0 0 1 9.5 1.3 3.3 3.3 0 0 1-.3 6.6Z" fill="#c3ccd6"/>';
		var scloud='<circle cx="9" cy="8.5" r="3.4" fill="#f3c34e"/>'+cloud;
		var drops='<g stroke="#57a6d6" stroke-width="1.7" stroke-linecap="round"><line x1="9" y1="18.5" x2="8.2" y2="21.5"/><line x1="12" y1="18.5" x2="11.2" y2="21.5"/><line x1="15" y1="18.5" x2="14.2" y2="21.5"/></g>';
		var ldrops='<g stroke="#57a6d6" stroke-width="1.6" stroke-linecap="round"><line x1="10.5" y1="18.5" x2="9.9" y2="20.9"/><line x1="14" y1="18.5" x2="13.4" y2="20.9"/></g>';
		var bolt='<polygon points="12.5,16.5 9.5,21.5 11.7,21.5 10.3,24.5 14.5,19.5 12.3,19.5" fill="#f3c34e"/>';
		var fog='<g stroke="#b7c1cc" stroke-width="1.5" stroke-linecap="round"><line x1="7" y1="19.5" x2="17" y2="19.5"/><line x1="8.6" y1="22" x2="15.4" y2="22"/></g>';
		var g;
		if(c>=95) g=cloud+bolt;                                                 // thunder
		else if((c>=51&&c<=67)||(c>=80&&c<=82)||(c>=71&&c<=86)) g=cloud+drops;  // forecast rain/snow
		else if(c===45||c===48) g=cloud+fog;                                    // fog
		else if(pp>=45) g=cloud+drops;                                          // dry code but likely showers
		else if(pp>=20) g=scloud+ldrops;                                        // slight shower chance (e.g. 22%)
		else if(cc>=80||c===3) g=cloud;                                         // overcast
		else if(cc>=40||c===2) g=scloud;                                        // partly cloudy
		else g=sun;                                                             // clear
		return '<svg viewBox="0 0 24 24" width="22" height="22" style="overflow:visible">'+g+'</svg>';
	}
	function renderPrecip(S){
		var DOW=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
		function hourLbl(d){ var h=d.getHours(), ap=h>=12?'p':'a'; h=h%12||12; return h+ap; }
		// Arrow points the way the wind blows (downwind): rotate a north-pointing
		// arrow by wind-from + 180°. title keeps the cardinal for hover/readers.
		function windArrow(deg){ var rot=((deg+180)%360).toFixed(0); return '<svg viewBox="0 0 24 24" width="15" height="15" style="display:block;transform:rotate('+rot+'deg)"><path d="M12 4.5 L12 19.5 M7 10 L12 4.5 L17 10" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>'; }
		var n=S.length, temps=S.map(function(x){ return x.temp; });
		var icons='', precips='', tvals='', wdirs='', wspds='', times='';
		for(var m=0;m<n;m++){
			var pv=S[m].precip, pcol = pv>=40?'#57a6d6':(pv>=15?'#8fb4cc':'#6b8ba8');
			icons+='<div>'+wxIcon(S[m].code, S[m].precip, S[m].cloud)+'</div>';
			precips+='<div style="color:'+pcol+'">'+pv+'%</div>';
			tvals+='<div>'+Math.round(S[m].temp)+'°</div>';
			wdirs+=(S[m].wdir!=null)?('<div title="'+dirCardinal(S[m].wdir)+'">'+windArrow(S[m].wdir)+'</div>'):'<div>—</div>';
			wspds+='<div>'+((S[m].wspd!=null)?Math.round(S[m].wspd):'—')+'</div>';
			var isNew=(m>0)&&(S[m].t.getDate()!==S[m-1].t.getDate());
			times+='<div>'+(m===0?'Now':hourLbl(S[m].t))+(isNew?'<br><span class="d">'+DOW[S[m].t.getDay()]+'</span>':'')+'</div>';
		}
		var maxT=Math.round(Math.max.apply(null,temps)), minT=Math.round(Math.min.apply(null,temps));
		var maxP=Math.max.apply(null,S.map(function(x){ return x.precip; }));
		var sum = maxP>=40 ? ('Showers possible — up to '+maxP+'% chance; highs near '+maxT+'°')
		        : maxP>=20 ? ('Mostly dry, slight shower chance; highs near '+maxT+'°')
		        : ('Dry through the period — highs near '+maxT+'°, lows near '+minT+'°');
		$('precipBody').innerHTML = '<div class="px-sum">'+sum+'</div>'
			+ '<div class="px-row px-row--icons"><span class="px-lbl">Sky</span><div class="px-cells">'+icons+'</div></div>'
			+ '<div class="px-row px-row--rain"><span class="px-lbl">Rain</span><div class="px-cells">'+precips+'</div></div>'
			+ '<div class="px-row px-row--tval"><span class="px-lbl">Temp</span><div class="px-cells">'+tvals+'</div></div>'
			+ '<div class="px-row px-row--wdir"><span class="px-lbl">Wind</span><div class="px-cells">'+wdirs+'</div></div>'
			+ '<div class="px-row px-row--wspd"><span class="px-lbl">Kt</span><div class="px-cells">'+wspds+'</div></div>'
			+ '<div class="px-row px-row--time"><span class="px-lbl"></span><div class="px-cells">'+times+'</div></div>';
	}

	// PRIMARY wave source: Open-Meteo marine model at the Execution Rocks (44022)
	// position — fills in while the UConn buoy is dark. Sets omWavesOk on success so
	// the CWF-text fallback below won't overwrite it.
	var omWavesOk = false;
	function loadWavesOM(){
		fetch('https://marine-api.open-meteo.com/v1/marine?latitude='+CFG.EXRX_LAT+'&longitude='+CFG.EXRX_LON+'&current=wave_height,wave_direction,wave_period&hourly=wave_height&length_unit=imperial&timezone=America/New_York&forecast_days=2')
			.then(function(r){ if(!r.ok) throw new Error('om '+r.status); return r.json(); })
			.then(function(j){
				if(j && j.hourly && j.hourly.time){ nhWave = j.hourly; renderNextHours(); }
				var c = j && j.current;
				if(!c || c.wave_height == null) throw new Error('no om waves');
				omWavesOk = true;
				var ht = c.wave_height;
				$('waveHt').textContent = (ht < 1) ? '≤1 ft' : (Math.round(ht*10)/10)+' ft';
				$('waveHt').classList.remove('miss');
				$('wavePer').textContent = (c.wave_period != null) ? (Math.round(c.wave_period)+' s') : '—';
				$('waveDir').textContent = (c.wave_direction != null) ? dirCardinal(c.wave_direction) : '—';
				$('waveCap').textContent = 'Forecast seas — Execution Rock';
				markUpdated(true);
			}).catch(function(){});   // leave the CWF-derived fallback to fill in
	}

	// FALLBACK wave source, derived from the same CWF text. "Seas" gives the
	// significant wave height (always present); the optional "Wave Detail: <DIR>
	// <ft> at <sec> seconds" line adds period + direction. Skipped once Open-Meteo
	// has populated the card.
	function renderWaves(periods){
		if(omWavesOk) return;
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
	// One row per active alert at the club (NWS), newest-most-serious first:
	// warnings red, watches/advisories amber, statements slate. The first row
	// shows "+N more" when there are others; "Details" opens the NWS text.
	// The Forecast tab repeats the top alert in full.
	var ALERT_ICO = '<svg class="al-ico" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2.5 20h19z"/><path d="M12 10v4.5"/><path d="M12 17.5h.01"/></svg>';
	function esc(t){ return String(t == null ? '' : t).replace(/[&<>"]/g, function(c){ return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;' }[c]; }); }
	function alertKind(ev){ return /warning/i.test(ev) ? 'warn' : (/watch|advisory/i.test(ev) ? 'adv' : 'st'); }
	function alertUntil(pr){
		var e = pr.ends || pr.expires; if(!e) return '';
		var d = new Date(e); if(isNaN(d)) return '';
		var now = new Date(), same = d.toDateString() === now.toDateString();
		var tm = new Date(now.getTime() + 86400000).toDateString() === d.toDateString();
		return ' until ' + hmt(d) + (same ? '' : (tm ? ' tomorrow' : ' ' + DOW[d.getDay()]));
	}
	function alertParas(pr){
		var t = [pr.description, pr.instruction].filter(Boolean).join('\n\n');
		return t.split(/\n\s*\n/).map(function(x){ return x.replace(/\s+/g, ' ').trim(); }).filter(Boolean)
			.map(function(x){ return '<p>' + esc(x) + '</p>'; }).join('');
	}
	var ALERT_RANK = { warn:0, adv:1, st:2 };
	function renderAlerts(list){
		var box = $('alertBox'), fc = $('fcAlert');
		if(!list.length){
			box.hidden = true; box.innerHTML = ''; box.removeAttribute('data-sig');
			if(fc){ fc.hidden = true; fc.innerHTML = ''; }
			return;
		}
		list.sort(function(a, b){ return ALERT_RANK[a.kind] - ALERT_RANK[b.kind]; });
		var sig = JSON.stringify(list);
		if(box.getAttribute('data-sig') === sig && !box.hidden){ return; }   // unchanged: keep open details as they are
		box.setAttribute('data-sig', sig);
		var openNow = box.classList.contains('open');
		box.classList.toggle('many', list.length > 1);
		box.innerHTML = list.map(function(a, i){
			var more = (i === 0 && list.length > 1)
				? '<button type="button" class="al-btn al-more" data-al-more aria-expanded="' + (openNow ? 'true' : 'false') + '">' + (openNow ? 'Show less' : '+' + (list.length - 1) + ' more') + '</button>' : '';
			return '<div class="al al-' + a.kind + '">'
				+ '<div class="al-row">' + ALERT_ICO
				+ '<strong class="al-head">' + esc(a.head) + '</strong>'
				+ (a.area ? '<span class="al-area">' + esc(a.area) + '</span>' : '')
				+ '<span class="al-btns">' + more
				+ (a.body ? '<button type="button" class="al-btn" data-al-det="' + i + '" aria-expanded="false" aria-controls="alTxt' + i + '">Details</button>' : '')
				+ '</span></div>'
				+ (a.body ? '<div class="al-txt" id="alTxt' + i + '" hidden>' + a.body + '</div>' : '')
				+ '</div>';
		}).join('');
		box.hidden = false;
		if(fc){
			var t = list[0];
			fc.innerHTML = '<h2>' + esc(t.head) + '</h2>' + (t.area ? '<p class="wx-sub">' + esc(t.area) + '</p>' : '')
				+ '<div class="al-body">' + (t.body || '') + '</div>';
			fc.hidden = false;
		}
	}
	(function(){
		var box = $('alertBox'); if(!box) return;
		box.addEventListener('click', function(e){
			var m = e.target.closest ? e.target.closest('[data-al-more]') : null;
			if(m){
				var o = box.classList.toggle('open'), n = box.querySelectorAll('.al').length - 1;
				m.setAttribute('aria-expanded', o ? 'true' : 'false');
				m.textContent = o ? 'Show less' : '+' + n + ' more';
				return;
			}
			var d = e.target.closest ? e.target.closest('[data-al-det]') : null;
			if(d){
				var p = $('alTxt' + d.getAttribute('data-al-det')); if(!p) return;
				p.hidden = !p.hidden;
				d.setAttribute('aria-expanded', p.hidden ? 'false' : 'true');
				d.textContent = p.hidden ? 'Details' : 'Hide details';
			}
		});
	})();
	function loadAlerts(){
		nws('alerts/active?point='+CFG.LAT+'%2C'+CFG.LON).then(function(j){
			var f = (j && j.features) || [];
			renderAlerts(f.map(function(a){
				var pr = a.properties || {}, ev = pr.event || 'Alert';
				var area = (pr.areaDesc || '').split(';').map(function(x){ return x.trim(); }).filter(Boolean);
				return { kind: alertKind(ev), head: ev + alertUntil(pr),
					area: area.slice(0, 2).join(', ') + (area.length > 2 ? ' and ' + (area.length - 2) + ' more' : ''),
					body: alertParas(pr) };
			}));
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

	// ---------- FISHING ----------
	// The day's fishing outlook (today, or tomorrow after 8 PM) from data the board
	// already uses: tide times (same bundle / NOAA source as Next Tides), NOAA
	// current predictions at Execution Rocks, the Kings Point water temperature and
	// its 3-day change, the Open-Meteo harbor forecast (wind + pressure tendency),
	// and sun/moon positions computed here. Solunar majors = moon overhead or
	// underfoot +/-1 h; minors = moonrise/moonset +/-30 min. Windows score dawn/dusk,
	// the hour either side of a tide turn and a major period at 2 points each and a
	// minor period at 1: 3+ is Good, 4+ (two strong signals) is Best. Current isn't
	// scored: the Execution Rocks predictions are weak (under 0.5 kt) and peak
	// several times a tide, which smeared windows into 3-hour blocks. It's a
	// planning guide, not a promise.
	var FISH_T0 = 5, FISH_T1 = 21;                      // timeline 5 AM - 9 PM
	var FISH_CUR = 'LIS1036';                           // NOAA current station, Execution Rocks
	var FISH_WORDS = ['Poor','Slow','Fair','Good','Excellent'];
	function fishDayStart(){ var d=new Date(); if(d.getHours()>=20){ d.setDate(d.getDate()+1); } d.setHours(0,0,0,0); return d; }
	function fishRange(a,b){ var A=hmt(a), B=hmt(b); return (A.slice(-2)===B.slice(-2)) ? A.slice(0,-3)+'–'+B : A+'–'+B; }
	function fishPct(ms, day){ var a=day.getTime()+FISH_T0*3600000, b=day.getTime()+FISH_T1*3600000; return Math.max(0,Math.min(100,(ms-a)/(b-a)*100)); }

	// moon altitude (radians, with refraction) and the day's rise / set / overhead / underfoot
	function moonAltAt(date){
		var lw=rad*-CFG.LON, phi=rad*CFG.LAT, d=toDays(date), c=moonCoords(d),
			H=rad*(280.16+360.9856235*d)-lw-c.ra,
			h=Math.asin(Math.sin(phi)*Math.sin(c.dec)+Math.cos(phi)*Math.cos(c.dec)*Math.cos(H)), hr=Math.max(h,0);
		return h + 0.0002967/Math.tan(hr+0.00312536/(hr+0.08901179));
	}
	function moonEvents(day){
		var t0=day.getTime(), step=5*60000, n=288, hc=0.133*rad, al=[], i, mx=0, mn=0,
			out={ rise:null, set:null, over:null, under:null };
		for(i=0;i<=n;i++){ al.push(moonAltAt(new Date(t0+i*step))); }
		for(i=1;i<=n;i++){
			if(al[i]>al[mx]) mx=i;
			if(al[i]<al[mn]) mn=i;
			var a=al[i-1]-hc, b=al[i]-hc;
			if(a<0 && b>=0 && !out.rise) out.rise=new Date(t0+(i-1+a/(a-b))*step);
			if(a>=0 && b<0 && !out.set)  out.set =new Date(t0+(i-1+a/(a-b))*step);
		}
		if(mx>0 && mx<n) out.over =new Date(t0+mx*step);
		if(mn>0 && mn<n) out.under=new Date(t0+mn*step);
		return out;
	}

	// data: each resolves to [] / null on failure so the card still renders what it has
	function fishTides(day){
		var t0=day.getTime()-3*3600000, t1=day.getTime()+27*3600000;
		function keep(list){
			return (list||[]).map(function(p){ return { t:new Date(String(p.t).replace(' ','T')), v:parseFloat(p.v), hi:p.type==='H' }; })
				.filter(function(x){ var ms=x.t.getTime(); return ms>=t0 && ms<=t1; });
		}
		return bundledTides().then(function(ev){ var k=keep(ev); if(k.length<2) throw new Error('bundle'); return k; })
			.catch(function(){
				return coops('predictions', { begin_date:ymdhm(new Date(t0)), range:30, datum:'MLLW', interval:'hilo' })
					.then(function(j){ var k=keep(j && j.predictions); if(k.length<2) throw new Error('hilo'); return k; });
			})
			.catch(function(){ return keep(readTideCache('hilo')); });
	}
	function fishCurrents(day){
		var u=COOPS+'?product=currents_predictions&application=OYC-LiveConditions&station='+FISH_CUR
			+'&time_zone=lst_ldt&units=english&format=json&interval=MAX_SLACK&begin_date='+ymd(day)+'&end_date='+ymd(day);
		return fetch(u).then(function(r){ return r.json(); }).then(function(j){
			var cp=j && j.current_predictions && j.current_predictions.cp;
			return (cp||[]).filter(function(c){ return c.Type==='flood' || c.Type==='ebb'; })
				.map(function(c){ return { t:new Date(String(c.Time).replace(' ','T')), v:Math.abs(parseFloat(c.Velocity_Major)), type:c.Type }; });
		}).catch(function(){ return []; });
	}
	function fishWater(){
		return coops('water_temperature', { begin_date:ymdhm(new Date(Date.now()-76*3600000)), end_date:ymdhm(new Date()), interval:'h' })
			.then(function(j){
				var d=((j && j.data) || []).filter(function(x){ return x.v!=null && x.v!==''; })
					.map(function(x){ return { t:new Date(x.t.replace(' ','T')).getTime(), v:parseFloat(x.v) }; });
				if(!d.length) return null;
				var last=d[d.length-1], target=last.t-72*3600000, old=null, bd=1e15;
				d.forEach(function(x){ var dd=Math.abs(x.t-target); if(dd<bd){ bd=dd; old=x; } });
				return { v:last.v, delta:(old && bd<6*3600000) ? last.v-old.v : null };
			}).catch(function(){ return null; });
	}
	function fishWx(){
		return fetch('https://api.open-meteo.com/v1/forecast?latitude='+CFG.EXRX_LAT+'&longitude='+CFG.EXRX_LON
			+'&hourly=wind_speed_10m,wind_gusts_10m,wind_direction_10m,pressure_msl&wind_speed_unit=kn&past_days=1&forecast_days=3&timezone=America%2FNew_York')
			.then(function(r){ if(!r.ok) throw new Error('om '+r.status); return r.json(); })
			.then(function(j){
				var H=j && j.hourly; if(!H || !H.time) return null;
				return H.time.map(function(t,i){ return { t:new Date(t).getTime(), sp:H.wind_speed_10m[i], gu:H.wind_gusts_10m[i], dir:H.wind_direction_10m[i], mb:H.pressure_msl[i] }; });
			}).catch(function(){ return null; });
	}
	function fishWxAt(wx, ms){
		var best=null, bd=1e15;
		(wx||[]).forEach(function(x){ var d=Math.abs(x.t-ms); if(d<bd){ bd=d; best=x; } });
		return (bd<=90*60000) ? best : null;
	}

	// score 10-minute slots and join the strong stretches into windows
	function fishWindows(day, sun, tides, solu){
		var H=3600000, t0=day.getTime()+FISH_T0*H, t1=day.getTime()+FISH_T1*H, step=10*60000, slots=[], per=[], t, i;
		if(solu.over)  per.push({ a:solu.over.getTime()-H,    b:solu.over.getTime()+H,    pts:2, lbl:'major solunar' });
		if(solu.under) per.push({ a:solu.under.getTime()-H,   b:solu.under.getTime()+H,   pts:2, lbl:'major solunar' });
		if(solu.rise)  per.push({ a:solu.rise.getTime()-H/2,  b:solu.rise.getTime()+H/2,  pts:1, lbl:'minor solunar' });
		if(solu.set)   per.push({ a:solu.set.getTime()-H/2,   b:solu.set.getTime()+H/2,   pts:1, lbl:'minor solunar' });
		for(t=t0; t<t1; t+=step){
			var m=t+step/2, sc=0, why=[];
			if(Math.abs(m-sun.sunrise.getTime())<=H){ sc+=2; why.push('dawn'); }
			if(Math.abs(m-sun.sunset.getTime())<=H){ sc+=2; why.push('sunset'); }
			var tideStarts=null;
			for(i=0;i<tides.length;i++){ if(Math.abs(m-tides[i].t.getTime())<=H){ sc+=2; why.push((tides[i].hi?'high':'low')+' tide '+hmt(tides[i].t).slice(0,-3)); tideStarts=tides[i].hi?'ebb':'flood'; break; } }
			for(i=0;i<per.length;i++){ if(m>=per[i].a && m<=per[i].b){ sc+=per[i].pts; why.push(per[i].lbl); break; } }
			slots.push({ a:t, b:t+step, sc:sc, why:why, tide:tideStarts });
		}
		function build(min){
			var wins=[], cur=null;
			slots.forEach(function(s){
				if(s.sc<min) return;
				if(cur && s.a-cur.b<=20*60000){
					cur.b=s.b; cur.peak=Math.max(cur.peak,s.sc);
					s.why.forEach(function(w){ if(cur.why.indexOf(w)<0) cur.why.push(w); });
				} else { cur={ a:s.a, b:s.b, peak:s.sc, why:s.why.slice(), tides:[] }; wins.push(cur); }
				if(s.tide && cur.tides.indexOf(s.tide)<0) cur.tides.push(s.tide);
			});
			return wins;
		}
		var wins=build(3), fair=false;
		if(!wins.length){ wins=build(2); fair=true; }
		wins.forEach(function(w){ w.cls = fair ? 'fair' : (w.peak>=4 ? 'best' : 'good'); });
		wins.sort(function(x,y){ return (y.peak-x.peak) || ((y.b-y.a)-(x.b-x.a)); });
		return wins.slice(0,3).sort(function(x,y){ return x.a-y.a; });
	}

	// species line by water temperature; Aug-Dec reads as the cooling (fall) season
	function fishNote(w, month){
		if(w==null) return '';
		var cooling = month>=7;
		if(w<45) return 'Cold water. Most fish have left the western Sound; a few blackfish hold on deep structure.';
		if(w<52) return cooling ? 'Late season: blackfish on the rock piles and the last of the striped bass.'
			: 'Early season: holdover striped bass waking up in the harbors, flounder on the mud.';
		if(w<58) return cooling ? 'Blackfish at their best on structure; striped bass feeding before they head south.'
			: 'Striped bass arriving and porgies moving in.';
		if(w<66) return cooling ? 'Prime for striped bass and blackfish. Bluefish are thinning out as it cools.'
			: 'Striped bass and porgies; bluefish arrive as it warms.';
		if(w<74) return 'Summer mix: porgy, black sea bass and fluke on the bottom; striped bass and bluefish early and late.';
		return 'Warm water: fish go deeper, so work dawn and dusk. Bluefish, porgy, fluke and black sea bass.';
	}

	// ---- top 3 spots for the day (the map's Fishing spots, assets/map-pois.json) ----
	// Score = the spot's two best species in season this month (2 = prime at
	// today's water temperature, 1 = possible), +1 when its preferred tide (from
	// the fishing reports) starts inside one of today's Best/Good windows, minus
	// 0.15 per nm from OYC; on a windy day far spots lose more. Only the best two
	// species count, so a spot doesn't win just because more were written up.
	// Temperature bands are western-Sound rules of thumb (°F): prime lo-hi, possible lo-hi.
	var FISH_POIS = '<?php echo esc_url( get_template_directory_uri() . '/assets/map-pois.json' ); ?>?v=<?php echo esc_js( defined( 'OYC_VERSION' ) ? OYC_VERSION : '' ); ?>';
	var FISH_HOME = { lat:40.9486, lon:-73.7296 };       // OYC (same point the map measures from)
	var FISH_TEMP = {
		'striped bass':[50,68,45,72], 'blackfish':[48,62,44,66], 'bluefish':[64,76,58,80], 'porgy':[58,72,54,75],
		'fluke':[60,75,56,78], 'black sea bass':[56,70,52,74], 'weakfish':[58,72,55,75], 'false albacore':[62,72,58,75],
		'winter flounder':[40,55,36,58], 'bonito':[64,74,60,76]
	};
	var fishTop = [], fishSpotsP = null;
	function fishSpotList(){
		if(!fishSpotsP){
			fishSpotsP = fetch(FISH_POIS).then(function(r){ if(!r.ok) throw new Error('pois '+r.status); return r.json(); })
				.then(function(j){ return (j && j.fish) || []; })
				.catch(function(){ fishSpotsP = null; return []; });
		}
		return fishSpotsP;
	}
	function fishNm(la1, lo1, la2, lo2){
		var R=3440.065, d=Math.PI/180, a=Math.sin((la2-la1)*d/2), b=Math.sin((lo2-lo1)*d/2);
		var x=a*a+Math.cos(la1*d)*Math.cos(la2*d)*b*b; return 2*R*Math.asin(Math.min(1,Math.sqrt(x)));
	}
	function fishBearing(la1, lo1, la2, lo2){
		var d=Math.PI/180, y=Math.sin((lo2-lo1)*d)*Math.cos(la2*d), x=Math.cos(la1*d)*Math.sin(la2*d)-Math.sin(la1*d)*Math.cos(la2*d)*Math.cos((lo2-lo1)*d);
		return ['N','NE','E','SE','S','SW','W','NW'][Math.round(((Math.atan2(y,x)/d)+360)%360/45)%8];
	}
	function fishRankSpots(spots, mon, water, wins, maxW){
		var starts={}, out=[], wind=maxW ? maxW.sp : 0;
		wins.forEach(function(w){ if(w.cls!=='fair') (w.tides||[]).forEach(function(t){ starts[t]=1; }); });
		spots.forEach(function(p){
			var ws=[];
			(p.species||[]).forEach(function(s){
				var m=p.months && p.months[s]; if(m && m.indexOf(mon)<0) return;
				var b=FISH_TEMP[s], w=1;
				if(b && water!=null){ w = (water>=b[0] && water<=b[1]) ? 2 : ((water>=b[2] && water<=b[3]) ? 1 : 0); }
				if(w) ws.push({ s:s, w:w });
			});
			if(!ws.length) return;
			ws.sort(function(a,b){ return b.w-a.w; });
			var d=fishNm(FISH_HOME.lat, FISH_HOME.lon, p.lat, p.lng), fit=!!(p.tide && starts[p.tide]);
			var sc=ws[0].w+(ws[1] ? ws[1].w : 0)+(fit ? 1 : 0)-0.15*d;
			if(wind>=20) sc-=0.25*Math.max(0,d-5); else if(wind>=15) sc-=0.1*Math.max(0,d-6);
			out.push({ p:p, sc:sc, d:d, ws:ws, fit:fit });
		});
		out.sort(function(a,b){ return b.sc-a.sc; });
		return out.slice(0,3);
	}
	function renderFishSpots(top){
		fishTop = top;
		var wrap=$('fishSpotsWrap');
		if(!top.length){ wrap.hidden=true; return; }
		$('fishSpots').innerHTML = top.map(function(x, i){
			var p=x.p, prime=x.ws.filter(function(o){ return o.w===2; }), list=(prime.length ? prime : x.ws).slice(0,3).map(function(o){ return o.s; }).join(', ');
			var td = p.tide ? '<span class="td'+(x.fit ? ' fit' : '')+'">Best on the '+p.tide+(x.fit ? ' &middot; fits today&rsquo;s windows' : '')+'</span>' : '';
			return '<button type="button" class="fish-spot" data-i="'+i+'" aria-label="'+(i+1)+'. '+p.name.replace(/"/g,'&quot;')+', '+(x.d<10 ? x.d.toFixed(1) : Math.round(x.d))+' nautical miles. Show on the map"><span class="rk">'+(i+1)+'</span>'
				+'<span class="nm">'+p.name+'</span>'
				+'<span class="sp">'+(x.d<10 ? x.d.toFixed(1) : Math.round(x.d))+' nm '+fishBearing(FISH_HOME.lat, FISH_HOME.lon, p.lat, p.lng)+' &middot; '+list+'</span>'+td+'</button>';
		}).join('');
		wrap.hidden=false;
	}
	/* tap a spot: scroll the MAP itself to the middle of the screen (not the top of
	   its card: on phones the layer controls above the map are ~700 px tall) and
	   open that spot's popup (wind-map.php exposes oycFishFocus) */
	$('fishSpots').addEventListener('click', function(e){
		var b=e.target.closest ? e.target.closest('.fish-spot') : null; if(!b) return;
		var x=fishTop[+b.getAttribute('data-i')]; if(!x) return;
		if(window.oycWxTab){ window.oycWxTab('chart'); }
		var mc=document.querySelector('#oycwm .leaflet-container') || document.querySelector('.windmap-card');
		if(mc) mc.scrollIntoView({ behavior:'smooth', block:'center' });
		var tries=0;
		(function go(){ if(window.oycFishFocus && window.oycFishFocus(x.p.name)) return; if(++tries<8) setTimeout(go, 500); })();
	});

	function renderFishing(day, isToday, sun, solu, mi, tides, curs, water, wx, spots){
		var H=3600000, now=Date.now(), t0=day.getTime()+FISH_T0*H, t1=day.getTime()+FISH_T1*H;
		var D3=['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
		$('fishDay').textContent = (isToday ? 'Today' : 'Tomorrow') + ' · ' + D3[day.getDay()] + ' ' + MON[day.getMonth()].slice(0,3) + ' ' + day.getDate();
		var wins = fishWindows(day, sun, tides, solu);

		// timeline: night ends, windows, now marker; tide turns above the hours
		var tl = '<span class="nt" style="left:0;width:'+fishPct(sun.sunrise.getTime(),day).toFixed(1)+'%"></span>'
			+ '<span class="nt" style="left:'+fishPct(sun.sunset.getTime(),day).toFixed(1)+'%;right:0"></span>';
		wins.forEach(function(w){
			var a=fishPct(w.a,day), b=fishPct(w.b,day);
			tl += '<span class="w '+w.cls+(isToday && w.b<now ? ' past' : '')+'" style="left:'+a.toFixed(1)+'%;width:'+Math.max(1,b-a).toFixed(1)+'%"></span>';
		});
		if(isToday && now>t0 && now<t1){ tl += '<span class="now" style="left:'+fishPct(now,day).toFixed(1)+'%"></span>'; }
		$('fishTl').innerHTML = tl;
		$('fishTl').setAttribute('aria-label', wins.length
			? 'Fishing windows: ' + wins.map(function(w){ return w.cls+' '+fishRange(new Date(w.a), new Date(w.b)); }).join(', ')
			: 'No fishing windows found');
		$('fishTide').innerHTML = tides.filter(function(x){ var ms=x.t.getTime(); return ms>=t0 && ms<=t1; }).map(function(x){
			return '<span style="left:'+Math.max(3,Math.min(97,fishPct(x.t.getTime(),day))).toFixed(1)+'%">'+(x.hi?'H':'L')+'</span>';
		}).join('');
		// forecast wind during a window: hourly values inside it (else the hour nearest
		// its middle), as "8-10 kt NE, gusts 14"; direction from the windiest hour
		function winWind(w){
			var hrs=(wx||[]).filter(function(x){ return x.t>=w.a && x.t<=w.b && x.sp!=null; });
			if(!hrs.length){ var mid=fishWxAt(wx, (w.a+w.b)/2); if(mid && mid.sp!=null) hrs=[mid]; }
			if(!hrs.length) return '';
			var lo=Infinity, hi=null, g=null;
			hrs.forEach(function(x){ lo=Math.min(lo,x.sp); if(!hi || x.sp>hi.sp) hi=x; if(x.gu!=null && (g==null || x.gu>g)) g=x.gu; });
			var a=Math.round(lo), b=Math.round(hi.sp);
			return 'Wind '+(a===b ? a : a+'–'+b)+' kt'+(hi.dir!=null ? ' '+dirCardinal(hi.dir) : '')+(g!=null ? ', gusts '+Math.round(g) : '');
		}
		$('fishWins').innerHTML = wins.length ? wins.map(function(w){
			var past = (isToday && w.b<now) ? ' past' : '', lbl = w.cls==='best' ? 'Best' : (w.cls==='good' ? 'Good' : 'Fair'), ww = winWind(w);
			return '<span class="chip '+w.cls+past+'">'+lbl+'</span><span class="'+past+'"><b>'+fishRange(new Date(w.a), new Date(w.b))+'</b> <span class="why">&middot; '+w.why.slice(0,3).join(', ')+'</span>'
				+ (ww ? '<span class="wx">'+ww+'</span>' : '')+'</span>';
		}).join('') : '<span class="chip fair">None</span><span class="why">No overlapping signals today</span>';

		// conditions
		if(water){
			$('fishWater').textContent = Math.round(water.v)+'°F';
			var wt=$('fishWaterT'), dd=water.delta;
			if(dd==null){ wt.textContent=''; wt.className='t'; }
			else if(Math.abs(dd)<1){ wt.textContent='steady over 3 days'; wt.className='t'; }
			else { wt.textContent=(dd<0?'▼ ':'▲ ')+Math.round(Math.abs(dd))+'° in 3 days'; wt.className='t '+(dd<0?'down':'up'); }
		}
		var rate=null, pNow=null;
		if(wx){
			if(isToday){ var pa=fishWxAt(wx, now), pb=fishWxAt(wx, now-3*H); if(pa){ pNow=pa.mb; } if(pa && pb && pa.mb!=null && pb.mb!=null){ rate=pa.mb-pb.mb; } }
			else { var q6=fishWxAt(wx, day.getTime()+6*H), q12=fishWxAt(wx, day.getTime()+12*H), q18=fishWxAt(wx, day.getTime()+18*H);
				if(q12){ pNow=q12.mb; } if(q6 && q18 && q6.mb!=null && q18.mb!=null){ rate=(q18.mb-q6.mb)/4; } }
		}
		if(pNow!=null){ $('fishPres').textContent = Math.round(pNow); }
		var pt=$('fishPresT');
		if(rate==null){ pt.textContent=''; pt.className='t'; }
		else if(rate<=-0.4){ pt.textContent='▼ falling'; pt.className='t fall'; }
		else if(rate>=0.4){ pt.textContent='▲ rising'; pt.className='t up'; }
		else { pt.textContent='→ steady'; pt.className='t'; }
		var maxW=null;
		(wx||[]).forEach(function(x){ if(x.t>=t0 && x.t<=t1 && x.sp!=null && (!maxW || x.sp>maxW.sp)) maxW=x; });
		var maxG=null;
		(wx||[]).forEach(function(x){ if(x.t>=t0 && x.t<=t1 && x.gu!=null && (maxG==null || x.gu>maxG)) maxG=x.gu; });
		if(maxW){
			$('fishWind').textContent = Math.round(maxW.sp)+' kt';
			$('fishWindK').textContent = 'Max wind' + (maxW.dir!=null ? ' · '+dirCardinal(maxW.dir) : '');
			$('fishWindT').textContent = (maxG!=null) ? 'gusts '+Math.round(maxG)+' kt' : '';
		}
		var pk=null;
		curs.forEach(function(c){ var ms=c.t.getTime(); if(ms>=t0 && ms<=t1 && (!pk || c.v>pk.v)) pk=c; });
		$('fishCur').textContent = pk ? pk.v.toFixed(1)+' kt' : '—';
		$('fishCurT').textContent = pk ? pk.type+' at '+hmt(pk.t) : (curs.length ? '' : 'no prediction');

		// solunar
		var sol=[];
		if(solu.over)  sol.push({ t:solu.over,  html:'<span class="k">Major</span>'+fishRange(new Date(solu.over.getTime()-H), new Date(solu.over.getTime()+H)) });
		if(solu.under) sol.push({ t:solu.under, html:'<span class="k">Major</span>'+fishRange(new Date(solu.under.getTime()-H), new Date(solu.under.getTime()+H)) });
		if(solu.rise)  sol.push({ t:solu.rise,  html:'<span class="k">Minor</span>'+fishRange(new Date(solu.rise.getTime()-H/2), new Date(solu.rise.getTime()+H/2)) });
		if(solu.set)   sol.push({ t:solu.set,   html:'<span class="k">Minor</span>'+fishRange(new Date(solu.set.getTime()-H/2), new Date(solu.set.getTime()+H/2)) });
		sol.sort(function(a,b){ return a.t-b.t; });
		$('fishSol').innerHTML = sol.map(function(x){ return '<div>'+x.html+'</div>'; }).join('')
			+ '<div class="full">Moon: '+moonName(mi.phase).toLowerCase()+', '+Math.round(mi.fraction*100)+'% lit</div>';

		// outlook: start at Fair and adjust
		var sc=3, parts=[];
		if(rate!=null && rate<=-0.4){ sc++; parts.push('falling pressure'); }
		else if(rate!=null && rate>=1.0){ sc--; parts.push('pressure rising fast'); }
		var best=wins.filter(function(w){ return w.cls==='best'; })[0], good=wins.filter(function(w){ return w.cls==='good'; })[0];
		if(best){ sc++; parts.push('best around '+hmt(new Date(best.a))+' ('+best.why.slice(0,2).join(', ')+')'); }
		else if(good){ parts.push('a good window around '+hmt(new Date(good.a))); }
		else { sc--; parts.push('no strong windows'); }
		var ph=mi.phase;
		if(ph<0.07 || ph>0.93){ sc++; parts.push('new-moon tides'); }
		else if(Math.abs(ph-0.5)<0.07){ sc++; parts.push('full-moon tides'); }
		if(maxW && maxW.sp>=25){ sc-=2; parts.push('wind up to '+Math.round(maxW.sp)+' kt'); }
		else if(maxW && maxW.sp>=18){ sc--; parts.push('breezy, up to '+Math.round(maxW.sp)+' kt'); }
		sc=Math.max(1,Math.min(5,sc));
		$('fishWord').textContent = FISH_WORDS[sc-1];
		var dots=''; for(var k=1;k<=5;k++){ dots+='<i'+(k>sc?' class="off"':'')+'></i>'; }
		$('fishDots').innerHTML = dots;
		// small wind glyph + speed under the rating (peak wind for the day; the
		// arrow flies the way the wind blows, matching the forecast-table arrows)
		var _wg=$('fishWordWind');
		if(_wg){
			if(maxW && maxW.sp!=null){
				var _rot=(maxW.dir!=null)?(((maxW.dir+180)%360)).toFixed(0):null;
				_wg.innerHTML=(_rot!=null?'<svg viewBox="0 0 24 24" width="13" height="13" style="display:block;transform:rotate('+_rot+'deg)"><path d="M12 4.5 L12 19.5 M7 10 L12 4.5 L17 10" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>':'')
					+'<span><b>'+Math.round(maxW.sp)+'</b> kt</span>';
				_wg.title='Peak wind '+Math.round(maxW.sp)+' kt'+(maxW.dir!=null?' from '+dirCardinal(maxW.dir):'');
			} else { _wg.innerHTML=''; _wg.removeAttribute('title'); }
		}
		var why=parts.join('; ');
		$('fishWhy').textContent = why ? why.charAt(0).toUpperCase()+why.slice(1)+'.' : '';

		var note=fishNote(water ? water.v : null, new Date().getMonth()), ne=$('fishNote');
		ne.textContent = note; ne.hidden = !note;

		renderFishSpots(fishRankSpots(spots||[], day.getMonth()+1, water ? water.v : null, wins, maxW));
	}

	var fishBusy=false;
	function loadFishing(){
		if(fishBusy) return;
		fishBusy=true;
		var day=fishDayStart(), noon=new Date(day.getTime()+12*3600000), isToday=(new Date().getDate()===day.getDate());
		var sun=sunTimes(noon, CFG.LAT, CFG.LON), solu=moonEvents(day), mi=moonIllum(noon);
		Promise.all([fishTides(day), fishCurrents(day), fishWater(), fishWx(), fishSpotList()]).then(function(r){
			fishBusy=false;
			renderFishing(day, isToday, sun, solu, mi, r[0]||[], r[1]||[], r[2], r[3], r[4]);
			markUpdated(true);
		}).catch(function(){ fishBusy=false; });
	}

	// ---------- NOW VIEW: headline tiles, next-hours strip, fishing summary ----------
	// The tiles read the (hidden) original readout cards, so every loader and
	// fallback above keeps working unchanged; a MutationObserver re-syncs them
	// whenever a loader writes a new value.
	var nowHiLo = null, nextTideEvts = [], nhWind = null, nhWave = null;
	function txt(id){ var el = $(id); var t = el ? el.textContent.trim() : ''; return (t === '—' || t === '-') ? '' : t; }
	function setNs(id, v, sub){
		var a = $(id), b = $(id+'S');
		if(a){ a.textContent = v || '—'; a.classList.toggle('miss', !v); }
		if(b){ b.textContent = sub || ' '; }
	}
	function syncNow(){
		var w = txt('windSpd'), wd = txt('windDir'), wg = txt('windGust');
		setNs('nsWind', w ? w + ' kt' : '', [wd ? 'From ' + wd : '', wg ? 'gusts ' + wg.replace(/\s*kt$/, '') : ''].filter(Boolean).join(', '));
		var sh = txt('waveHt'), sp = txt('wavePer'), sd = txt('waveDir');
		setNs('nsSeas', sh, [sp, sd ? 'from ' + sd : ''].filter(Boolean).join(' '));
		var tv = txt('tideVal'), tt = txt('tideTrendTxt'), nx = nextTideEvts[0];
		setNs('nsTide', tv ? tv + ' ft' : '', [tt, nx ? (nx.hi ? 'high ' : 'low ') + hmt(nx.t) : ''].filter(Boolean).join(', '));
		var pr = txt('pressure').replace(/\s*mb$/, ''), pt = $('pressureTrend'), ptt = pt ? (pt.getAttribute('title') || '') : '';
		setNs('nsPres', pr, ptt ? ptt.replace(' mb / 3h', ' mb in 3 h') : (pr ? 'mb' : ''));
		var air = txt('airTemp'), hum = txt('humidity');
		function hr(d){ var h = d.getHours(); return (h % 12 || 12) + (h >= 12 ? ' PM' : ' AM'); }
		setNs('nsAir', air, nowHiLo
			? 'Hi ' + nowHiLo[0] + '°' + (nowHiLo[2] ? ' ' + hr(nowHiLo[2]) : '') + ' · Lo ' + nowHiLo[1] + '°' + (nowHiLo[3] ? ' ' + hr(nowHiLo[3]) : '')
			: (hum ? 'Humidity ' + hum : ''));
		var wt = txt('waterTemp'), vis = txt('visibility');
		setNs('nsWater', wt, vis ? 'Visibility ' + vis : '');
		var fw = txt('fishWord'), fy = txt('fishWhy');
		if($('nowFishWord')){ $('nowFishWord').textContent = fw || '—'; }
		if($('nowFishWhy') && fy){ $('nowFishWhy').textContent = fy; }
		// the Fishing tab's visuals, copied as-is: score dots, the 5 AM-9 PM
		// windows timeline with its tide marks, and the window list
		[['fishDots','nowFishDots'],['fishTl','nowFishTl'],['fishTide','nowFishTide'],['fishWins','nowFishWins']].forEach(function(p){
			var a = $(p[0]), b = $(p[1]);
			if(a && b && b.innerHTML !== a.innerHTML){ b.innerHTML = a.innerHTML; }
		});
		if($('fishTl') && $('nowFishTl')){ $('nowFishTl').setAttribute('aria-label', $('fishTl').getAttribute('aria-label') || ''); }
	}
	var syncT = null;
	function syncSoon(){ clearTimeout(syncT); syncT = setTimeout(syncNow, 60); }
	if(window.MutationObserver){
		var mo = new MutationObserver(syncSoon);
		[document.querySelector('.now-src'), $('fishWord'), $('fishWhy'), $('fishDots'), $('fishTl'), $('fishTide'), $('fishWins')].forEach(function(n){
			if(n){ mo.observe(n, { subtree:true, childList:true, characterData:true, attributes:true, attributeFilter:['title','class'] }); }
		});
	}
	// Next 12 hours in 2-hour blocks on even hours (e.g. 10–12, 12–2, 2–4 …),
	// starting with the block we're in now. Each block shows the wind range
	// over its two hours, the average direction, the top gust and the top seas.
	function renderNextHours(){
		var box = $('nextHours'); if(!box || !nhWind) return;
		var start = new Date(); start.setMinutes(0, 0, 0); start.setHours(start.getHours() - start.getHours() % 2);
		var wv = {};
		if(nhWave && nhWave.time){ nhWave.time.forEach(function(t, k){ wv[t] = nhWave.wave_height[k]; }); }
		function ap(h){ return (h % 12 || 12) + (h % 24 >= 12 ? 'p' : 'a'); }
		var out = [];
		for(var b = 0; b < 6; b++){
			var t0 = start.getTime() + b*2*3600000, t1 = t0 + 2*3600000;
			var sp = [], gs = [], hts = [], u = 0, v = 0;
			nhWind.time.forEach(function(t, k){
				var ms = new Date(t).getTime(); if(ms < t0 || ms >= t1) return;
				var s1 = nhWind.wind_speed_10m[k], g1 = nhWind.wind_gusts_10m[k], d1 = nhWind.wind_direction_10m[k];
				if(s1 != null){ sp.push(s1); if(d1 != null){ u += s1*Math.sin(d1*Math.PI/180); v += s1*Math.cos(d1*Math.PI/180); } }
				if(g1 != null) gs.push(g1);
				if(wv[t] != null) hts.push(wv[t]);
			});
			if(!sp.length) continue;
			var lo = Math.round(Math.min.apply(null, sp)), hi = Math.round(Math.max.apply(null, sp));
			var dir = (u || v) ? (Math.atan2(u, v)*180/Math.PI + 360) % 360 : null;
			var h0 = new Date(t0).getHours(), h1 = new Date(t1).getHours();
			var lbl = (h1 !== 0 && (h0 < 12) === (h1 < 12)) ? ap(h0).slice(0, -1) + '–' + ap(h1) : ap(h0) + '–' + ap(h1);
			out.push('<div class="nh-h"><div class="nh-t">' + lbl + '</div>'
				+ '<div class="nh-w">' + (lo === hi ? lo : lo + '–' + hi) + '<small> kt' + (dir != null ? ' ' + dirCardinal(dir) : '') + '</small></div>'
				+ '<div class="nh-g">' + (gs.length ? 'g' + Math.round(Math.max.apply(null, gs)) : '')
				+ (hts.length ? ' · ' + (Math.round(Math.max.apply(null, hts)*10)/10) + ' ft' : '') + '</div></div>');
		}
		box.innerHTML = out.join('');
	}

	// ---------- INIT + refresh ----------
	refreshTideCache();                                // seed the 30-day fallback cache
	loadTides(); loadWind(); loadWavesOM(); loadConditions(); loadForecast(); loadPrecip(); loadAlerts(); loadSunMoon(); loadFishing();
	setInterval(updateCurrentTide, 60*1000);          // re-interpolate current level each minute
	setInterval(function(){ drawGraph(); }, 5*60*1000);
	setInterval(loadTides, 30*60*1000);
	setInterval(refreshTideCache, 6*60*60*1000);       // keep the fallback cache fresh
	setInterval(loadWind, 5*60*1000);
	setInterval(loadWavesOM, 15*60*1000);
	setInterval(loadConditions, 10*60*1000);
	setInterval(loadForecast, 30*60*1000);
	setInterval(loadPrecip, 15*60*1000);
	setInterval(loadAlerts, 5*60*1000);
	setInterval(loadSunMoon, 30*60*1000);
	setInterval(loadFishing, 15*60*1000);             // windows dim as they pass; flips to tomorrow at 8 PM
	setInterval(renderNextHours, 10*60*1000);         // roll the next-hours strip forward
	syncNow();
})();
</script>
</body>
</html>
<?php
exit;
