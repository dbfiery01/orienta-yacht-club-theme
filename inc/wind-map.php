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
/* Full-screen is a desktop-only affordance — the expanded overlay never worked
   well on phones/tablets, so hide the button on touch devices and let the map
   sit inline (it scrolls fine within the page). */
@media (hover:none) and (pointer:coarse){ #oycwm .fsbtn{display:none !important} }
/* full-screen (native Fullscreen API, or the .isfull class as a CSS fallback) */
#oycwm .card.isfull{position:fixed;inset:0;z-index:2147483647;margin:0;border:0;border-radius:0;background:#fff;display:flex;flex-direction:column;max-width:none}
#oycwm .card.isfull .mapwrap{flex:1 1 auto;min-height:0}
#oycwm .card.isfull .maprot{height:100%}  /* the maprot wrapper must fill the card, else #map height:100% collapses to 0 (blank map on maximize) */
#oycwm .card.isfull #map{height:100%}
#oycwm .card.isfull .satpanel{flex:1 1 auto;max-height:none}
/* Native-fullscreen backdrop: default is black — match the themed card ground. */
#oycwm .card.isfull::backdrop{background:#fff}
/* Floating hamburger menu (shown only in landscape full-screen — see below).
   Inert elsewhere: the button is hidden and the panel only ever appears when
   JS has folded the tabs + layer toggles into it. Colours use the component
   tokens so it tracks light/dark automatically. */
#oycwm .burger{display:none;flex-direction:column;align-items:center;justify-content:center;gap:4px;width:44px;height:34px;flex:none;border:1px solid var(--line);border-radius:9px;background:var(--panel);color:var(--ink);cursor:pointer}
#oycwm .burger span{display:block;width:18px;height:2px;background:currentColor;border-radius:2px;transition:.2s}
#oycwm .card.menuopen .burger span:nth-child(1){transform:translateY(6px) rotate(45deg)}
#oycwm .card.menuopen .burger span:nth-child(2){opacity:0}
#oycwm .card.menuopen .burger span:nth-child(3){transform:translateY(-6px) rotate(-45deg)}
#oycwm .mobpanel{flex-direction:column;gap:9px;position:absolute;z-index:600;left:10px;right:10px;top:50px;max-height:calc(100% - 120px);overflow:auto;-webkit-overflow-scrolling:touch;background:var(--panel);border:1px solid var(--line);border-radius:14px;padding:11px;box-shadow:0 14px 36px rgba(0,0,0,.34)}
#oycwm .mobpanel .tabs{flex-wrap:wrap}
#oycwm .mobpanel .layers{display:flex;flex-wrap:wrap;padding:0}

/* Landscape (phones AND tablets): full-screen folds the tab bar and every layer
   toggle into the floating hamburger, so only a slim [burger … Exit] bar and the
   time slider stay in flow and the MAP gets almost the whole height. The Buoy
   readout, help hint, route tip and footnote are dropped. Keyed on
   orientation+max-height so it covers iPad landscape (~820px) but never phone or
   tablet portrait, where there is room. */
@media (orientation:landscape) and (max-height:900px){
	#oycwm .card.isfull .hd{padding:7px 12px;gap:8px}
	#oycwm .card.isfull .hd h2,
	#oycwm .card.isfull .hd .st{display:none}
	#oycwm .card.isfull .erbar{display:none}
	#oycwm .card.isfull .layers .hint{display:none}
	#oycwm .card.isfull .ctrl{padding:5px 14px}
	#oycwm .card.isfull .tiphelp,
	#oycwm .card.isfull .foot{display:none}
	#oycwm .card.isfull.hasburger .burger{display:inline-flex}
	#oycwm .card.isfull.hasburger .fsbtn{margin-left:auto}
	#oycwm .card.isfull .mobpanel{display:none}
	#oycwm .card.isfull.menuopen .mobpanel{display:flex}
}
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
/* ---- layer presets + Layers drawer (weather page Chart tab) ---- */
#oycwm .presetbar{display:flex;flex-wrap:wrap;align-items:center;justify-content:flex-start;gap:8px 12px;padding:2px 18px 10px}
#oycwm .presets{display:inline-flex;border:1px solid var(--line);border-radius:10px;overflow:hidden;background:#fff}
#oycwm .pre-b{border:0;border-right:1px solid var(--line);background:transparent;color:var(--ink);font:inherit;font-size:.85rem;font-weight:600;padding:0 18px;min-height:40px;cursor:pointer}
#oycwm .pre-b:last-child{border-right:0}
#oycwm .pre-b:hover{background:var(--panel)}
#oycwm .pre-b[aria-pressed="true"]{background:var(--navy);color:#fff}
#oycwm .lyr-btn{display:inline-flex;align-items:center;gap:7px;min-height:40px;padding:0 14px;border:1px solid var(--line);border-radius:10px;background:#fff;color:var(--ink);font:inherit;font-size:.85rem;font-weight:600;cursor:pointer}
#oycwm .pre-b.empty{color:var(--mute);font-style:italic}
#oycwm .mine-save{border:0;background:none;color:var(--harbor);font:inherit;font-size:.78rem;font-weight:600;padding:4px 2px;cursor:pointer;text-decoration:underline;text-underline-offset:3px;white-space:nowrap;min-height:32px}
#oycwm .mine-save.ok{color:#2e7d32;text-decoration:none}
[data-theme="dark"] #oycwm .mine-save{color:#d4a851}
#oycwm .lyr-btn[aria-expanded="true"]{border-color:var(--navy);background:var(--navy);color:#fff}
#oycwm .pre-b:focus-visible,#oycwm .lyr-btn:focus-visible,#oycwm .lyr-x:focus-visible{outline:3px solid var(--harbor);outline-offset:2px}
#oycwm .lyr-drawer{position:absolute;z-index:1002;top:10px;right:10px;width:262px;max-height:calc(100% - 104px);overflow:auto;overscroll-behavior:contain;
	background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:0 6px 24px rgba(11,42,74,.22);padding:8px 12px 12px}
#oycwm .lyr-drawer[hidden]{display:none}
#oycwm .lyr-hd{display:flex;justify-content:space-between;align-items:center;font-size:.9rem;
	/* stays pinned while the layer list scrolls, so the close \00d7 is always reachable */
	position:sticky;top:-8px;z-index:2;margin:-8px -12px 0;padding:8px 12px 4px;background:inherit;border-bottom:1px solid var(--line)}
#oycwm .lyr-x{border:0;background:none;font-size:22px;line-height:1;width:36px;height:36px;margin-right:-8px;cursor:pointer;color:var(--mute);border-radius:8px}
#oycwm .lyr-drawer .layers{flex-direction:column;flex-wrap:nowrap;gap:8px;padding:0;background:transparent}
#oycwm .lyr-sec + .lyr-sec{margin-top:8px;padding-top:8px;border-top:1px solid var(--line)}
#oycwm .lyr-g{display:flex;flex-direction:column}
#oycwm .lyr-g h3{font-size:.68rem;letter-spacing:.14em;text-transform:uppercase;color:var(--mute);font-weight:700;margin:4px 0 2px}
#oycwm .lyr-drawer .layers label{border:0;border-radius:6px;padding:4px 6px;min-height:32px;font-weight:500;font-size:.84rem}
#oycwm .lyr-drawer .layers label:hover{background:var(--panel)}
#oycwm .lyr-drawer .lay-pop{margin:2px 0 6px}
#oycwm .lyr-help summary{font-size:.74rem;color:var(--mute);cursor:pointer;padding:4px 0;min-height:28px}
#oycwm .lyr-help .hint{display:block;margin-top:4px;line-height:1.45}
#oycwm .card.hasburger .lyr-drawer,#oycwm .card.hasburger .lyr-btn{display:none}
@media (max-width:760px){
	#oycwm .presetbar{padding:2px 12px 10px}
	#oycwm .presets{flex:1}
	#oycwm .pre-b{flex:1;padding:0 6px}
	/* phones: the drawer floats over the map (it used to sit above it and push the map off screen) */
	#oycwm .lyr-drawer{top:8px;left:52px;right:8px;width:auto;max-height:calc(100% - 16px)}
	#oycwm .lyr-drawer .lyr-sec{display:block}
	#oycwm .lyr-drawer .lyr-g{display:grid;grid-template-columns:1fr 1fr;column-gap:6px}
	#oycwm .lyr-drawer .lyr-g h3,#oycwm .lyr-drawer .lyr-g .lay-pop{grid-column:1/-1}
}
[data-theme="dark"] #oycwm .presets,[data-theme="dark"] #oycwm .lyr-btn{background:#0e2a47;border-color:rgba(245,239,226,.30);color:#f0eadd}
[data-theme="dark"] #oycwm .pre-b{color:#f0eadd;border-color:rgba(245,239,226,.30)}
[data-theme="dark"] #oycwm .pre-b:hover,[data-theme="dark"] #oycwm .lyr-drawer .layers label:hover{background:rgba(245,239,226,.08)}
[data-theme="dark"] #oycwm .pre-b[aria-pressed="true"],[data-theme="dark"] #oycwm .lyr-btn[aria-expanded="true"]{background:#d4a851;color:#0b2a4a;border-color:#d4a851}
[data-theme="dark"] #oycwm .lyr-drawer{background:#0b2240;border-color:rgba(245,239,226,.25);color:#f0eadd}
[data-theme="dark"] #oycwm .lyr-g h3,[data-theme="dark"] #oycwm .lyr-help summary,[data-theme="dark"] #oycwm .lyr-x{color:#c3cedb}
[data-theme="dark"] #oycwm .lyr-sec + .lyr-sec{border-color:rgba(245,239,226,.2)}
/* My boat (GPS, From my boat, heads-up, sharing) is for signed-in members only (v1.7.408) */
#oycwm.nologin .mbr-only{display:none!important}
/* "My boat" navigation disclaimer (v1.7.405) */
.oyc-disc{position:fixed;inset:0;z-index:100000;background:rgba(5,20,38,.62);display:flex;align-items:center;justify-content:center;padding:16px}
.oyc-disc .dbox{background:#fff;color:#0b2a4a;max-width:440px;width:100%;border-radius:12px;padding:20px 20px 16px;box-shadow:0 12px 40px rgba(0,0,0,.35);font:15px/1.45 system-ui,-apple-system,sans-serif;max-height:90vh;overflow:auto}
.oyc-disc h3{margin:0 0 10px;font-size:1.05rem}
.oyc-disc ul{margin:0 0 12px;padding-left:20px}
.oyc-disc li{margin:0 0 6px}
.oyc-disc label{display:flex;gap:9px;align-items:flex-start;font-weight:600;margin:4px 0 14px;cursor:pointer}
.oyc-disc label input{margin-top:3px;width:18px;height:18px;flex:none}
.oyc-disc .dbtns{display:flex;gap:10px;justify-content:flex-end}
.oyc-disc button{font:inherit;font-weight:600;border-radius:8px;padding:9px 16px;cursor:pointer;border:1px solid #0b2a4a;background:#fff;color:#0b2a4a}
.oyc-disc button.ok{background:#0b2a4a;color:#fff}
.oyc-disc button.ok:disabled{opacity:.4;cursor:not-allowed}
[data-theme="dark"] .oyc-disc .dbox{background:#0b2240;color:#f0eadd;border:1px solid rgba(245,239,226,.25)}
[data-theme="dark"] .oyc-disc button{background:transparent;color:#f0eadd;border-color:rgba(245,239,226,.4)}
[data-theme="dark"] .oyc-disc button.ok{background:#d4a851;color:#0b2a4a;border-color:#d4a851}
#oycwm .legend{display:flex;align-items:center;gap:2px;padding:0 18px 8px;font-size:.72rem;color:var(--mute);flex-wrap:wrap}
#oycwm .barbhint{display:flex;align-items:center;gap:9px;padding:0 18px 8px;font-size:.72rem;color:var(--mute);line-height:1.35}
#oycwm .barbhint svg{flex:none}
#oycwm .barbhint b{color:var(--ink);font-weight:800}
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
#oycwm .maprot{position:relative}
#oycwm #map{height:min(64vh,600px);width:100%;background:#dbe7f0}
/* ---- Heads-up (course-up) navigation view ----
   Tapping the compass on the chart switches between north-up and heads-up
   (it turns My location on if needed). In heads-up the chart turns so the
   boat's heading is up (course over ground when moving, the phone's compass
   when slow or stopped). The angle and
   zoom are eased every animation frame in JS (headsUpTick), so it turns
   smoothly instead of stepping with each GPS fix. Map dragging, the lat/long
   grid, map buttons and open cards hide (they'd be rotated); pinch / wheel zoom
   still works, around the boat. The north arrow + boat + badges counter-rotate
   so they stay right. Tap the compass again (or turn My location off) for north-up. */
#oycwm .navrose{position:absolute;z-index:596;right:10px;bottom:46px;width:40px;height:40px;border-radius:50%;border:1px solid var(--line);background:rgba(255,255,255,.92);box-shadow:0 2px 8px rgba(11,42,74,.25);cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;-webkit-tap-highlight-color:transparent;touch-action:manipulation}
#oycwm .navrose svg{display:block}
#oycwm .mapwrap.headsup{overflow:hidden}
/* mouse cursor on the chart: an ARROW in select mode (left button: click things,
   drop waypoints) and a HAND only while panning (right button held). Leaflet's
   grab hand and the hand over markers/routes are switched to the arrow; map
   buttons and the waypoint cards keep their own cursors. */
#oycwm .leaflet-container,#oycwm .leaflet-container.leaflet-grab,#oycwm .leaflet-container.leaflet-dragging .leaflet-grab,
#oycwm .leaflet-container .leaflet-interactive,#oycwm .leaflet-container .leaflet-marker-icon,#oycwm .leaflet-container .leaflet-marker-icon *,
#oycwm .leaflet-container .wp-ic,#oycwm .leaflet-container .wp-ic:active{cursor:default!important}
#oycwm .leaflet-container.rpan,#oycwm .leaflet-container.rpan *{cursor:grabbing!important}
#oycwm .mapwrap.headsup .maprot{transform:rotate(calc(-1 * var(--hdg,0deg))) scale(var(--hus,1.5));transform-origin:center;will-change:transform}
#oycwm .mapwrap.headsup .grat,
#oycwm .mapwrap.headsup .map-ll,
#oycwm .mapwrap.headsup .leaflet-control-container,
#oycwm .mapwrap.headsup .leaflet-popup{display:none !important}
#oycwm .mapwrap.headsup .wp-ic{transform:translate(-50%,-50%) rotate(var(--hdg,0deg))}
#oycwm .navfollow{display:none;position:absolute;z-index:596;left:50%;bottom:16px;transform:translateX(-50%);align-items:center;gap:6px;padding:7px 11px;border-radius:999px;border:1px solid #1a73e8;background:#1a73e8;color:#fff;font-size:12px;font-weight:800;cursor:pointer;box-shadow:0 2px 10px rgba(26,115,232,.45);white-space:nowrap}
#oycwm .navfollow svg{display:block}
#oycwm .mapwrap.headsup.hufree .navfollow{display:flex}
#oycwm .mapwrap.headsup .navrose{background:#1a73e8;border-color:#1a73e8;box-shadow:0 2px 10px rgba(26,115,232,.5)}
#oycwm .mapwrap.headsup .navrose svg{transform:rotate(calc(-1 * var(--hdg,0deg)))}
/* ---- Race course builder ---- */
/* starts right of the +/- zoom buttons (they sit above it and used to cover the COURSE label) */
#oycwm .racebar{position:absolute;z-index:596;left:52px;top:10px;display:none;flex-wrap:wrap;align-items:center;gap:6px;max-width:calc(100% - 62px);background:rgba(11,42,74,.92);color:#fff;padding:7px 10px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.3)}
#oycwm .racebar.on{display:flex}
/* race wind dials: one up and to the right of each race mark, arrow the way the wind blows, speed + direction FROM (°T) for the slider hour */
#oycwm .wdial{background:none;border:none;pointer-events:none}
#oycwm .wdial .wd{display:flex;flex-direction:column;align-items:center;gap:1px;width:64px}
#oycwm .wdial svg{width:34px;height:34px;display:block;overflow:visible}
#oycwm .wdial .wd-face{fill:none;stroke:rgba(11,42,74,.55)}
#oycwm .wdial .wd-arrow{stroke:#1a73e8;fill:#1a73e8}
#oycwm .wdial .wd-txt{font-size:9.5px;font-weight:800;color:#0b2a4a;white-space:nowrap;font-variant-numeric:tabular-nums;text-shadow:0 0 2px #fff,0 0 2px #fff,0 0 3px #fff}
[data-theme="dark"] #oycwm .wdial .wd-face{fill:none;stroke:rgba(11,42,74,.55)}

#oycwm .racebar .rc-lab{font-size:.72rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#bfe4f5}
#oycwm .racebar input{width:116px;font:inherit;font-size:.85rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;padding:5px 8px;border:1px solid rgba(255,255,255,.3);border-radius:7px;background:#fff;color:#0b2a4a}
#oycwm .racebar button{font:inherit;font-size:.74rem;font-weight:700;padding:6px 11px;border-radius:999px;border:1px solid var(--navy);background:var(--navy);color:#fff;cursor:pointer}
#oycwm .racebar button:hover{background:var(--harbor);border-color:var(--harbor)}
#oycwm .racebar .rb-clear{background:#fff;color:var(--navy)}
#oycwm .racebar .rc-info{flex-basis:100%;font-size:.72rem;font-weight:600;color:#dbeaf5}
/* Left stack: a live "Underway" boat card (mobile) above the race course sheet
   (per-mark cards: lat/long, wind, wave, and the leg to the next mark). Each
   card has a −/+ collapse button on the right. */
#oycwm .leftstack{position:absolute;z-index:595;left:10px;top:70px;display:flex;flex-direction:column;gap:6px;width:min(214px,60%);pointer-events:none}
#oycwm .boatinfo,#oycwm .racesheet{background:rgba(11,42,74,.92);color:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,.3);font-size:.72rem;line-height:1.35;pointer-events:auto}
#oycwm .racesheet{display:none;flex-direction:column;gap:6px;padding:8px;max-height:calc(100% - 200px);overflow-y:auto;-webkit-overflow-scrolling:touch}
#oycwm .racesheet.on{display:flex}
#oycwm .boatinfo{display:none;padding:7px 9px}
#oycwm .rs-card{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.15);border-radius:8px;padding:6px 8px}
#oycwm .rs-h{display:flex;align-items:center;gap:6px}
#oycwm .rs-badge{display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:50%;background:#e8820c;border:1.5px solid #7a3d00;color:#fff;font-size:.66rem;font-weight:800;flex:none}
#oycwm .rs-nm{font-weight:700;color:#dbeaf5}
#oycwm .bi-title{font-weight:800;letter-spacing:.03em;text-transform:uppercase;font-size:.7rem;color:#bfe4f5}
#oycwm .rs-ll{color:#bfe4f5;margin-top:2px;font-variant-numeric:tabular-nums}
#oycwm .rs-ww{margin-top:2px;color:#eaf4fb}
#oycwm .rs-ww b,#oycwm .rs-leg b{color:#fff;font-weight:800}
#oycwm .rs-leg{margin-top:4px;padding-top:4px;border-top:1px solid rgba(255,255,255,.15);color:#cfe6f3}
#oycwm .card-min-btn{margin-left:auto;flex:none;width:20px;height:20px;border-radius:6px;border:1px solid rgba(255,255,255,.3);background:rgba(255,255,255,.12);color:#fff;font-size:15px;font-weight:800;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0}
#oycwm .card-min-btn:hover{background:rgba(255,255,255,.22)}
#oycwm .rs-card.min .rs-body,#oycwm .boatinfo.min .rs-body{display:none}
#oycwm .bi-row{display:flex;align-items:baseline;gap:5px;margin-top:3px}
#oycwm .bi-v{font-size:1rem;font-weight:800;color:#fff;font-variant-numeric:tabular-nums}
#oycwm .bi-k{font-size:.68rem;color:#bfe4f5}
#oycwm .mapwrap.headsup .racesheet{display:none !important}
/* mobile "hide all cards" master toggle */
#oycwm .stack-toggle{display:none;align-self:flex-start;pointer-events:auto;cursor:pointer;background:rgba(11,42,74,.92);color:#fff;border:1px solid rgba(255,255,255,.25);border-radius:999px;padding:4px 11px;font-size:.7rem;font-weight:800;box-shadow:0 2px 10px rgba(0,0,0,.3)}
#oycwm .stack-toggle:hover{background:var(--harbor,#13507a)}
#oycwm .leftstack.stack-hidden .boatinfo,#oycwm .leftstack.stack-hidden .racesheet{display:none !important}
@media (max-width:880px){#oycwm .boatinfo.on{display:block}#oycwm .leftstack.has-cards .stack-toggle{display:inline-flex}}
/* phones: the card stack (My boat / Underway, race sheet) and its Hide/Show cards
   pill sit to the RIGHT of the +/- zoom buttons, so the zoom buttons never cover them */
@media (max-width:880px){
	#oycwm .leftstack{left:46px;top:40px}
	#oycwm .leftstack .stack-toggle{position:absolute;top:-32px;left:0}
}
#oycwm .race-mk{background:none;border:none}
#oycwm .race-ic{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:20px;height:20px;border-radius:50%;background:#e8820c;border:2px solid #7a3d00;box-shadow:0 0 0 1.5px #fff,0 1px 3px rgba(0,0,0,.45);color:#fff;font:800 11px/18px -apple-system,sans-serif;text-align:center}
#oycwm .mapwrap.headsup .race-ic{transform:translate(-50%,-50%) rotate(var(--hdg,0deg))}
#oycwm .race-leg{background:none;border:none;pointer-events:none}
#oycwm .race-leg .rl-wrap{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1px;pointer-events:auto;cursor:grab;touch-action:none}
#oycwm .race-leg.rl-grab .rl-wrap{cursor:grabbing}
#oycwm .race-leg b{font-size:10.5px;font-weight:800;color:#7a3d00;background:rgba(255,255,255,.85);border-radius:5px;padding:1px 6px;white-space:nowrap;box-shadow:0 0 0 1px rgba(122,61,0,.2);text-shadow:0 0 2px #fff}
#oycwm .race-leg i{font-style:normal;font-size:9.5px;font-weight:800;color:#0b3a66;background:rgba(224,240,251,.94);border-radius:5px;padding:1px 6px;white-space:nowrap;box-shadow:0 0 0 1px rgba(11,58,102,.28);text-shadow:0 0 2px #fff}
#oycwm .race-leg u{text-decoration:none;font-size:9.5px;font-weight:800;color:#06675a;background:rgba(216,245,239,.94);border-radius:5px;padding:1px 6px;white-space:nowrap;box-shadow:0 0 0 1px rgba(6,103,90,.3);text-shadow:0 0 2px #fff}
#oycwm .satpanel{width:100%;min-height:280px;background:#0b1622;display:flex;align-items:center;justify-content:center;padding:6px}
#oycwm .satpanel img{max-width:100%;max-height:min(66vh,640px);height:auto;width:auto;display:block}
#oycwm .satpanel.loading::after{content:'Loading satellite\2026';color:#9fb4c6;font-size:.85rem}
#oycwm #satLayers .sat-t{border:1px solid var(--line);background:var(--panel);color:var(--mute);font-weight:700;font-size:.78rem;letter-spacing:.02em;padding:5px 13px;border-radius:999px;cursor:pointer}
#oycwm #satLayers .sat-t:hover{border-color:var(--harbor)}
#oycwm #satLayers .sat-t.on{background:var(--harbor);color:#fff;border-color:var(--harbor)}
#oycwm .chart-sel{border:1px solid var(--line);background:#fff;color:var(--ink);font-weight:600;font-size:.82rem;padding:6px 12px;border-radius:10px;cursor:pointer;min-width:220px}
#oycwm .chart-sel:hover{border-color:var(--harbor)}
/* lower-left overlay stack: the hover readout sits ABOVE the route tip so it
   never follows (or blocks) the cursor */
#oycwm .map-ll{position:absolute;z-index:596;left:12px;bottom:12px;display:flex;flex-direction:column;gap:8px;align-items:stretch;pointer-events:none;width:min(300px,72%)}
#oycwm .hovertip{box-sizing:border-box;background:rgba(11,42,74,.94);color:#fff;font-size:12px;font-weight:600;padding:6px 10px;border-radius:7px;line-height:1.35;display:none;box-shadow:0 4px 14px rgba(0,0,0,.35)}
#oycwm .hovertip .hd2{color:#bfe4f5;font-size:10px;letter-spacing:.05em;text-transform:uppercase;display:block;margin-bottom:1px}
#oycwm .tiphelp{box-sizing:border-box;background:rgba(255,255,255,.9);border:1px solid var(--line);color:var(--mute);font-size:11px;font-weight:600;padding:6px 10px;border-radius:8px;line-height:1.35}
/* map tips box: +/- toggle, starts collapsed (just the "+ Map tips" pill) */
#oycwm .tiphelp{position:relative;pointer-events:auto}
#oycwm .tiphelp .tip-tog{float:right;display:inline-flex;align-items:center;gap:5px;margin:-2px -4px 2px 8px;padding:1px 6px;border:1px solid var(--line);border-radius:6px;background:transparent;color:inherit;font:inherit;font-weight:700;cursor:pointer;line-height:1.3}
#oycwm .tiphelp .tip-sign{font-size:13px;line-height:1;width:10px;text-align:center}
#oycwm .tiphelp .tip-lbl{display:none}
#oycwm .tiphelp.collapsed{align-self:flex-start;padding:4px 6px}
#oycwm .tiphelp.collapsed .tip-tog{float:none;margin:0;border:0}
#oycwm .tiphelp.collapsed .tip-lbl{display:inline}
#oycwm .tiphelp.collapsed .tip-txt{display:none}
/* route export bar — appears top-right once waypoints are dropped */
#oycwm .routebar{position:absolute;z-index:596;right:10px;top:10px;display:none;flex-direction:column;gap:6px;align-items:flex-end;max-width:70%}
#oycwm .routebar.on{display:flex}
#oycwm .route-info{background:rgba(11,42,74,.92);color:#fff;font-size:11px;font-weight:700;padding:5px 10px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.3);white-space:nowrap}
#oycwm .route-btns{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}
#oycwm .route-btns button,#oycwm .route-btns a{display:inline-flex;text-decoration:none;align-items:center;gap:5px;padding:7px 12px;border:1px solid var(--navy);background:var(--navy);color:#fff;border-radius:999px;cursor:pointer;font-size:.74rem;font-weight:700;line-height:1;box-shadow:0 2px 8px rgba(0,0,0,.25)}
#oycwm .route-btns button:hover,#oycwm .route-btns a:hover{background:var(--harbor);border-color:var(--harbor)}
#oycwm .route-btns .rb-clear{background:#fff;color:var(--navy)}
/* Autoroute: red with a count when a leg crosses land or shoal water */
#oycwm .route-btns .rb-auto{background:#fff;color:var(--navy)}
#oycwm .route-btns .rb-auto:hover{background:#eef4fa;color:var(--navy)}
#oycwm .route-btns .rb-auto.warn{background:#c0392b;border-color:#c0392b;color:#fff}
#oycwm .route-btns .rb-auto.warn:hover{background:#a93226;border-color:#a93226;color:#fff}
#oycwm .arpanel{background:#0b2a4a;color:#fff;padding:10px 12px;font-size:.74rem;line-height:1.4;border-bottom:1px solid var(--line)}
#oycwm .arpanel[hidden]{display:none}
#oycwm .ar-h{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
#oycwm .ar-x{border:0;background:none;color:#dbe9f3;font-size:18px;line-height:1;cursor:pointer;padding:0 4px}
#oycwm .ar-list{margin:0 0 8px;padding-left:18px;display:flex;flex-direction:column;gap:8px}
#oycwm .ar-list li>span:first-child{display:block;margin-bottom:4px}
#oycwm .ar-list li.ok>span:first-child{color:#9fe0b4}
#oycwm .ar-list li.no>span:first-child{opacity:.6;text-decoration:line-through}
#oycwm .ar-note{margin:0 0 8px;color:#dbe9f3;font-size:.68rem;display:flex;gap:6px;align-items:center}
#oycwm .wpt .s.sw-bad,#oycwm .co-sw.sw-bad{color:#c0392b;font-weight:700;white-space:normal}
#oycwm .wpt .s.sw-shoal,#oycwm .co-sw.sw-shoal{color:#b9600f;font-weight:700;white-space:normal}
#oycwm .co-sw{display:block;font-size:.72rem;margin-top:2px}
[data-theme="dark"] #oycwm .wpt .s.sw-bad{color:#ff8a7a}
[data-theme="dark"] #oycwm .wpt .s.sw-shoal{color:#f5b041}
#oycwm .leg-hit{cursor:copy}
#oycwm .lyr-draft{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin-top:6px;font-size:.8rem}
#oycwm .lyr-draft label{font-weight:700}
#oycwm .lyr-draft input{width:62px;padding:3px 6px;border:1px solid var(--line);border-radius:6px;font:inherit;background:var(--panel);color:var(--ink)}
#oycwm .lyr-draft .hint{flex-basis:100%;align-self:auto}
#oycwm .route-btns .rb-clear:hover{background:#eef4fa;color:var(--navy)}
/* Passage table: each waypoint's card as a row in a side table beside the map
   (stacked under it on narrow screens). Hidden until the first waypoint; the route
   summary + Save/Clear buttons move from the map's corner into its header. */
#oycwm .mapgrid{display:flex;align-items:stretch;position:relative}
#oycwm .mapgrid .mapwrap{flex:1 1 auto;min-width:0}
#oycwm .wptab{display:none;flex:0 0 260px;flex-direction:column;min-height:0;max-height:min(64vh,600px);border-left:1px solid var(--line);background:var(--panel)}
#oycwm .mapgrid.has-wp .wptab{display:flex}
#oycwm .wptab .routebar{position:static;display:flex;flex-direction:row;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;max-width:none;padding:10px 8px;border-bottom:1px solid var(--line)}
#oycwm .wptab .route-info{background:none;color:var(--navy);box-shadow:none;padding:0;font-size:.78rem;white-space:normal}
#oycwm .wptab .route-btns button,#oycwm .wptab .route-btns a{box-shadow:none}
#oycwm .wpt-wrap{flex:1 1 auto;min-height:0;overflow:auto;-webkit-overflow-scrolling:touch}
#oycwm .wpt{width:100%;border-collapse:collapse;font-size:.74rem;color:var(--ink);font-variant-numeric:tabular-nums}
#oycwm .wpt th{position:sticky;top:0;z-index:1;background:var(--panel);text-align:left;font-size:.62rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--mute);padding:7px 5px;border-bottom:1px solid var(--line);white-space:nowrap}
#oycwm .wpt td{padding:7px 5px;border-bottom:1px solid var(--line);vertical-align:top;line-height:1.3}
#oycwm .wpt tbody tr{cursor:pointer}
#oycwm .wpt tbody tr:hover{background:rgba(21,131,207,.09)}
#oycwm .wpt .n{width:24px;padding-left:10px}
/* drag a row by its number to change the route order */
#oycwm .wpt td.n{cursor:grab;touch-action:none;-webkit-user-select:none;user-select:none}
#oycwm .wpt tbody tr{cursor:grab;-webkit-user-select:none;user-select:none;-webkit-touch-callout:none}
#oycwm .wpt td.n span{position:relative}
#oycwm .wpt td.n span::after{content:'⋮⋮';position:absolute;left:-11px;top:1px;font-size:9px;letter-spacing:-2px;color:var(--faint);line-height:16px}
#oycwm .wpt tr.wp-drag{position:relative;z-index:2;background:#eaf3fb;box-shadow:0 6px 16px rgba(11,42,74,.28);cursor:grabbing}
#oycwm .wpt tr.wp-shift{transition:transform .12s ease}
[data-theme="dark"] #oycwm .wpt tr.wp-drag{background:#1b4268}
#oycwm .wpt .n span{display:inline-block;min-width:20px;height:20px;padding:0 3px;border-radius:11px;background:#1583cf;border:2px solid #0b2a4a;color:#fff;font-size:11px;font-weight:800;line-height:16px;text-align:center;box-sizing:border-box}
#oycwm .wpt .v{display:block;font-weight:800;white-space:nowrap}
#oycwm .wpt .s{display:block;color:var(--mute);font-size:.68rem;white-space:nowrap}
#oycwm .wpt .x{width:22px;padding-right:8px;text-align:right}
#oycwm .wpt .x button{border:none;background:none;color:var(--mute);font-size:13px;font-weight:700;line-height:1;cursor:pointer;padding:2px 4px;border-radius:5px}
#oycwm .wpt .x button:hover{background:#fdecea;color:#c0392b}
#oycwm .wpt-foot{padding:8px 12px;font-size:.68rem;color:var(--mute);border-top:1px solid var(--line)}
#oycwm .wpt .sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
/* Every waypoint is one compact row — number · distance · wind · seas + precip ·
   remove — that fits the 260px column (desktop) and panel (phones) without
   sideways scrolling. Each value carries its own unit, so the column headings
   stay hidden (screen readers keep them). The last track takes the spare width
   and pushes the ✕ to the right edge. */
#oycwm .wpt,#oycwm .wpt tbody{display:block}
#oycwm .wpt thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}
#oycwm .wpt tbody tr{display:grid;grid-template-columns:24px auto auto auto 1fr;grid-template-areas:"n d w s x";column-gap:8px;padding:7px 8px;border-bottom:1px solid var(--line)}
#oycwm .wpt td{padding:0;border:0}
#oycwm .wpt .n{grid-area:n;width:auto;padding:0}
#oycwm .wpt .dist{grid-area:d}
#oycwm .wpt .wind{grid-area:w}
#oycwm .wpt .sea{grid-area:s}
#oycwm .wpt .x{grid-area:x;width:auto;padding:0;justify-self:end}
#oycwm .wptab .route-btns button,#oycwm .wptab .route-btns a{padding:6px 7px;font-size:.68rem;white-space:nowrap}
/* Save route · GRIB · Clear · Hide on ONE line inside the 260px table (wraps only
   in the collapsed floating bar, which sizes itself) */
#oycwm .wptab:not(.collapsed) .route-btns{flex-wrap:wrap;gap:4px;margin-left:auto}
/* "Hide" / "Show table (N)" works at every width: hiding collapses the
   table to its header (route summary + buttons), floating at the map's top-right.
   On desktop that also drops the side column, so the map takes the full width. */
#oycwm .route-btns .rb-tog{background:#fff;color:var(--navy)}
#oycwm .route-btns .rb-tog:hover{background:#eef4fa;color:var(--navy)}
#oycwm .wptab.collapsed .wpt-wrap,
#oycwm .wptab.collapsed .wpt-foot{display:none}
#oycwm .mapgrid.has-wp .wptab.collapsed{position:absolute;top:10px;right:10px;z-index:1001;flex:none;width:auto;max-width:calc(100% - 70px);max-height:none;border:1px solid var(--line);border-radius:12px;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,.32)}
#oycwm .wptab.collapsed .routebar{border-bottom:0}
/* Narrow screens (phones): the table floats over the upper-right of the map, so it
   is in view while you tap waypoints (stacked BELOW the tall map it sat off-screen,
   and swiping over the map pans it instead of scrolling the page). Its list is
   sized in JS (wpFit) to show exactly two waypoints and scrolls for the rest. The
   left 56px stay clear for the zoom buttons. */
@media (max-width:900px){
	#oycwm .mapgrid{display:block}
	#oycwm .wptab{position:absolute;top:8px;right:8px;z-index:1001;width:min(260px,calc(100% - 56px));max-height:none;border:1px solid var(--line);border-radius:12px;overflow:hidden;box-shadow:0 8px 24px rgba(0,0,0,.32)}
	#oycwm .wptab .routebar{padding:8px;gap:6px}
	#oycwm .mapgrid.has-wp .map-ll{display:none}
	#oycwm .wpt{font-size:.7rem}
	#oycwm .card.isfull .mapgrid .mapwrap{height:100%}
}
#oycwm .card.isfull .mapgrid{flex:1 1 auto;min-height:0}
/* Layers drawer open (wider than phones): the floating waypoint/route card and the
   target-speed badge move left of the drawer (262px + 10px gap), so neither is hidden
   under it; they slide back when it closes. Phones keep the table under the slider. */
#oycwm .mapgrid .wptab,#oycwm .mapgrid .rt-badge{transition:right .18s ease}
#oycwm .card:not(.hasburger) .mapgrid.lyr-open.has-wp .wptab.collapsed{right:282px;max-width:calc(100% - 352px)}
#oycwm .card:not(.hasburger) .mapgrid.lyr-open .rt-badge{right:282px}
@media (min-width:761px) and (max-width:900px){
	#oycwm .card:not(.hasburger) .mapgrid.lyr-open .wptab{right:280px;width:min(260px,calc(100% - 336px))}
}
#oycwm .card.isfull .wptab{max-height:none}
#oycwm .card .foot{padding:10px 18px 16px;font-size:.72rem;color:var(--mute)}
#oycwm .leaflet-control.velocity-control{background:rgba(11,42,74,.82);color:#fff;padding:5px 9px;border-radius:8px;font-size:12px;font-weight:600}
#oycwm .iso-lbl{background:none;border:none;box-shadow:none;color:#334;font-size:10px;font-weight:700;text-shadow:0 0 3px #fff,0 0 3px #fff}
/* lat/lon graticule: light dashed grid lines + edge labels (same look as the
   pressure labels) */
#oycwm .grat{position:absolute;inset:0;z-index:500;pointer-events:none;overflow:hidden}
#oycwm .grat span{position:absolute;color:#334;font-size:10px;font-weight:700;text-shadow:0 0 3px #fff,0 0 3px #fff,0 0 4px #fff;white-space:nowrap}
#oycwm .grat .gl{position:absolute;pointer-events:none}
/* A thin white halo (box-shadow) keeps the dash legible over both light water
   and busy land tiles without having to make the line heavy. */
#oycwm .grat .gl-h{left:0;right:0;border-top:1px dashed rgba(12,23,42,.45);box-shadow:0 1px 0 rgba(255,255,255,.2),0 -1px 0 rgba(255,255,255,.2)}
#oycwm .grat .gl-v{top:0;bottom:0;border-left:1px dashed rgba(12,23,42,.45);box-shadow:1px 0 0 rgba(255,255,255,.2),-1px 0 0 rgba(255,255,255,.2)}
/* numbered passage waypoint marker */
#oycwm .wp-mk{background:none;border:none}
#oycwm .wp-ic{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);min-width:22px;height:22px;padding:0 4px;border-radius:12px;background:#1583cf;border:2px solid #0b2a4a;box-shadow:0 0 0 1.5px #fff,0 1px 3px rgba(0,0,0,.45);color:#fff;font-size:12px;font-weight:800;line-height:20px;text-align:center;box-sizing:border-box;cursor:grab}
#oycwm .wp-ic:active{cursor:grabbing}
/* waypoint callout = the draggable blue number, NOT the data box: hide Leaflet's
   popup tip (the little white diamond that sat on the marker and blocked grabbing
   it) and let popupAnchor lift the box clear of the number. */
#oycwm .pin-pop .leaflet-popup-tip-container,#oycwm .pin-pop .leaflet-popup-tip{display:none}
#oycwm .pin-pop .leaflet-popup-content-wrapper{box-shadow:0 4px 16px rgba(11,42,74,.26)}
/* cards are movable: drag one off the route and a leader line ties it back to its
   point (wireCardDrag); double-click / double-tap snaps it back */
#oycwm .pin-pop .leaflet-popup-content-wrapper{cursor:grab;touch-action:none;user-select:none;-webkit-user-select:none}
#oycwm .pin-pop.co-dragging .leaflet-popup-content-wrapper{cursor:grabbing;box-shadow:0 8px 24px rgba(11,42,74,.38)}
#oycwm .pin-pop .co-lead{position:absolute;left:0;top:0;height:2px;margin-top:-1px;background:#0b2a4a;opacity:.75;box-shadow:0 0 0 1px rgba(255,255,255,.7);transform-origin:0 50%;pointer-events:none;display:none}
#oycwm .map-label{background:none;border:none;box-shadow:none;display:flex;align-items:center;gap:4px;white-space:nowrap;font-weight:800;font-size:11px;color:#0b2a4a;text-shadow:0 0 3px #fff,0 0 4px #fff,0 0 4px #fff;transform:translate(-4px,-7px)}
/* POI markers — reciprocal clubs (burgee pennant) + dock & dine (navy fork circle) */
#oycwm .poi-mk{background:none;border:none}
#oycwm .poi-club{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:24px;height:17px;background:#fff;border:1.5px solid #0b2a4a;border-radius:3px;box-shadow:0 1px 3px rgba(0,0,0,.4);cursor:pointer;box-sizing:border-box}
#oycwm .poi-club::after{content:"";position:absolute;left:2px;top:2px;border-left:14px solid #a67c24;border-top:4.5px solid transparent;border-bottom:4.5px solid transparent}
#oycwm .poi-dine{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:21px;height:21px;border-radius:50%;background:#0b2a4a;border:1.5px solid #fff;box-shadow:0 0 0 1px #0b2a4a,0 1px 3px rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;cursor:pointer;box-sizing:border-box}
#oycwm .poi-dine svg{display:block}
#oycwm .poi-fuel{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:21px;height:21px;border-radius:50%;background:#a67c24;border:1.5px solid #fff;box-shadow:0 0 0 1px #6e5318,0 1px 3px rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;cursor:pointer;box-sizing:border-box}
#oycwm .poi-fuel svg{display:block}
#oycwm .poi-anc{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:21px;height:21px;border-radius:50%;background:#fff;border:1.5px solid #0b2a4a;box-shadow:0 1px 3px rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;cursor:pointer;box-sizing:border-box}
#oycwm .poi-anc svg{display:block}
/* fishing spots: teal disc with a fish; faded when nothing there is in season this month */
#oycwm .poi-fish{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:21px;height:21px;border-radius:50%;background:#0f7a6c;border:1.5px solid #fff;box-shadow:0 0 0 1px #0a5a50,0 1px 3px rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;cursor:pointer;box-sizing:border-box}
#oycwm .poi-fish svg{display:block}
#oycwm .poi-fish.off{opacity:.5}
/* POI / fishing spot card: a real card width (Leaflet sometimes measured the
   content at its 50px minimum, giving a one-word-per-line strip as tall as the
   map), capped height with its own scroll so it never runs off the chart */
#oycwm .poi-pop .leaflet-popup-content{min-width:min(250px,calc(100vw - 64px));max-width:min(290px,calc(100vw - 64px));max-height:min(340px,calc(100vh - 160px));overflow-y:auto;overscroll-behavior:contain;box-sizing:border-box;text-align:left}
#oycwm .poi-pop .pp-sp{margin-top:6px;display:flex;flex-wrap:wrap;gap:4px}
#oycwm .poi-pop .pp-sp span{white-space:nowrap;font-size:11px;font-weight:700;border-radius:999px;padding:2px 7px;background:#e3f2ef;color:#0a5a50}
#oycwm .poi-pop .pp-sp span.out{background:#eef1f4;color:#7a8896;font-weight:600}
#oycwm .poi-pop .pp-sp-k{font-size:10.5px;color:#7a8896;margin-top:6px;letter-spacing:.04em}
#oycwm .poi-pop .pp-name{font-weight:800;font-size:13px;color:#0b2a4a}
#oycwm .poi-pop .pp-sub{font-size:11.5px;color:#5a6b7d;margin-top:1px;font-weight:600}
#oycwm .poi-pop .pp-desc{font-size:12px;color:#334455;margin-top:6px;line-height:1.45}
#oycwm .poi-pop .pp-links{margin-top:7px;display:flex;gap:10px;flex-wrap:wrap}
#oycwm .poi-pop .pp-links a{font-size:12px;font-weight:700;color:#1583cf;text-decoration:none}
#oycwm .poi-pop .pp-links a:hover{text-decoration:underline}
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
#oycwm .pin-co{position:relative}
#oycwm .pin-co .co-t{font-weight:800;color:var(--navy);font-size:.8rem;margin-bottom:1px;padding:0 18px;text-align:center}
#oycwm .pin-co .co-t .co-tot{font-weight:700;color:var(--navy);font-size:.72rem;white-space:nowrap}
#oycwm .pin-co .co-ll{font-weight:600;color:var(--faint);font-size:.66rem;text-align:center;margin-bottom:4px}
#oycwm .pin-co .co-row{display:flex;gap:8px;font-size:.74rem;justify-content:center;white-space:nowrap}
#oycwm .pin-co .co-row .v{font-weight:800;color:var(--ink)}
#oycwm .pin-co .co-row .k{color:var(--mute);font-size:.7rem}
#oycwm .pin-co .co-dist{font-size:.74rem;font-weight:800;color:var(--harbor);margin:-1px 0 2px}
#oycwm .pin-co .co-total{font-size:.72rem;font-weight:800;color:var(--navy);text-align:center;margin:0 0 5px}
/* removal is now a small ✕ in the callout's top-right corner (no labelled row) */
#oycwm .pin-co .co-x{position:absolute;top:2px;right:2px;width:20px;height:20px;display:flex;align-items:center;justify-content:center;padding:0;border:none;background:none;color:var(--mute);font-size:13px;font-weight:700;line-height:1;cursor:pointer;border-radius:5px;user-select:none;-webkit-user-select:none}
#oycwm .pin-co .co-x:hover{background:#fdecea;color:#c0392b}
/* live "from you" bearing/distance line + the GPS location marker */
#oycwm .pin-co .co-leg{font-size:.72rem;font-weight:800;color:#0b2a4a;text-align:center;margin:0 0 2px}
#oycwm .pin-co .co-you{font-size:.72rem;font-weight:800;color:#1a73e8;text-align:center;margin:0 0 4px}
#oycwm .me-mk{background:none;border:none}
#oycwm .me-mk svg{display:block;filter:drop-shadow(0 0 1.5px #fff) drop-shadow(0 0 1px #fff);transform:rotate(var(--hdg,0deg))}
/* leg-distance labels at each route-segment midpoint (light map tiles in both
   themes, so no dark variant — matches the isobar / lat-long labels) */
#oycwm .leg-lbl{display:flex;flex-direction:column;align-items:center;justify-content:center;background:none;border:none;pointer-events:none}
/* chart plotter: the wind angle you'd sail on that leg, under its course/distance */
#oycwm .leg-lbl i{font-style:normal;font-size:9.5px;font-weight:700;color:#7a3d00;background:rgba(255,255,255,.85);border-radius:5px;padding:0 5px;margin-top:1px;white-space:nowrap;box-shadow:0 0 0 1px rgba(11,42,74,.10)}
#oycwm .wpt .s.twa{color:#9a4a00}
#oycwm .route-btns .rb-tog[aria-pressed="true"]{background:#1a73e8;border-color:#1a73e8;color:#fff}
/* other boats sharing their position (light map tiles in both themes) */
#oycwm .fl-mk{background:none;border:none}
#oycwm .lay-lbl{background:none;border:none;pointer-events:none}
#oycwm .lay-lbl b{position:absolute;transform:translate(-50%,-50%);font-size:9.5px;font-weight:800;background:rgba(255,255,255,.88);border-radius:4px;padding:0 4px;white-space:nowrap}
#oycwm .fl-ic{position:relative;transform:translate(-50%,-50%);width:26px;height:26px}
#oycwm .fl-ic svg{position:relative;z-index:1;display:block;overflow:visible;filter:drop-shadow(0 0 1.5px #fff) drop-shadow(0 0 1px #fff)}
#oycwm .fl-lbl{position:absolute;left:20px;top:20px;font-size:10.5px;font-weight:800;color:#7a3000;background:rgba(255,255,255,.9);border-radius:5px;padding:1px 6px;white-space:nowrap;line-height:1.25;box-shadow:0 0 0 1px rgba(11,42,74,.14)}
#oycwm .fl-lbl small{display:block;font-size:9.5px;font-weight:700;color:#5a6b7d}
#oycwm .fl-stale .fl-lbl{color:#5a6b7d}
/* AIS vessels: small arrowheads coloured by type, name label from zoom 12 */
#oycwm .ais-mk{background:none;border:none}
#oycwm .ais-ic{position:relative;transform:translate(-50%,-50%);width:18px;height:18px}
#oycwm .ais-ic svg{position:relative;z-index:1;display:block;overflow:visible;filter:drop-shadow(0 0 1px #fff) drop-shadow(0 0 1px #fff)}
#oycwm .ais-lbl{position:absolute;left:14px;top:12px;font-size:9.5px;font-weight:700;color:#24394d;background:rgba(255,255,255,.82);border-radius:4px;padding:0 4px;white-space:nowrap;line-height:1.3}
#oycwm.ais-far .ais-lbl{display:none}
#oycwm .ais-note{display:none;font-size:.74rem;color:var(--mute);padding:0 18px 6px}
#oycwm .ais-note.on{display:block}
/* Coast Guard Local Notice to Mariners pins (inc/nav-notices.php) */
#oycwm .nn-mk{background:none;border:none}
#oycwm .nn-ic{transform:translate(-50%,-50%);width:22px;height:22px;filter:drop-shadow(0 1px 1px rgba(0,0,0,.35))}
#oycwm .nn-ic svg{display:block}
.pin-pop .nn-cat{font-size:.68rem;letter-spacing:.08em;text-transform:uppercase;color:#b25d00;font-weight:700}
.pin-pop .nn-txt{display:block;margin-top:4px;max-height:180px;overflow:auto;line-height:1.4;white-space:pre-line}
.pin-pop .nn-you{display:block;margin-top:4px}
.pin-pop .nn-src{display:block;margin-top:6px;font-size:.72rem;color:#5d6d7e}
#oycwm .layers select{font:inherit;font-size:.74rem;border:1px solid var(--line);border-radius:6px;padding:1px 4px;background:#fff;color:var(--ink)}
#oycwm .lay-btn{font:inherit;font-size:.74rem;font-weight:700;border:1px solid var(--line);border-radius:6px;padding:1px 6px;background:#fff;color:var(--ink);cursor:pointer;font-variant-numeric:tabular-nums}
#oycwm .lay-btn::after{content:" \25BE";font-weight:400}
#oycwm .lay-pop{flex-basis:100%;border:1px solid var(--line);border-radius:10px;padding:10px 12px;background:#fff;display:flex;flex-direction:column;gap:8px;font-size:.8rem;color:var(--ink)}
#oycwm .lay-pop[hidden]{display:none}
#oycwm .lp-h{display:flex;justify-content:space-between;align-items:center}
#oycwm .lp-x{border:0;background:none;font-size:1.3rem;line-height:1;cursor:pointer;color:var(--ink);padding:0 4px}
#oycwm .lp-seg{display:inline-flex;align-self:flex-start;border:1px solid var(--line);border-radius:8px;overflow:hidden;flex-wrap:wrap}
#oycwm .lp-seg button{border:0;background:none;font:inherit;font-weight:600;padding:6px 12px;cursor:pointer;color:var(--ink)}
#oycwm .lp-seg button[aria-pressed="true"]{background:var(--harbor);color:#fff}
#oycwm .lp-pane{display:flex;flex-direction:column;gap:8px}
#oycwm .lp-pane[hidden]{display:none}
#oycwm .lp-chips{display:flex;flex-wrap:wrap;gap:6px}
#oycwm .lp-chips button{border:1px solid var(--line);background:none;font:inherit;border-radius:999px;padding:4px 11px;cursor:pointer;color:var(--ink)}
#oycwm .lp-chips button[aria-pressed="true"]{background:var(--harbor);border-color:var(--harbor);color:#fff}
#oycwm .lp-row{display:flex;flex-wrap:wrap;align-items:center;gap:6px 10px}
#oycwm .lp-lbl{font-weight:600}
#oycwm .lp-pair{display:inline-flex;align-items:center;gap:6px}
#oycwm .lp-step{display:inline-flex;align-items:center;border:1px solid var(--line);border-radius:8px;overflow:hidden}
#oycwm .lp-step button{width:38px;height:34px;border:0;background:none;font-size:1.15rem;cursor:pointer;color:var(--ink);touch-action:manipulation}
#oycwm .lp-step button:active{background:rgba(87,166,214,.18)}
#oycwm .lp-step output{min-width:3.2em;text-align:center;font-weight:800;font-size:.95rem;font-variant-numeric:tabular-nums}
#oycwm .lp-sec{border:1px solid var(--line);background:none;font:inherit;border-radius:6px;padding:3px 9px;cursor:pointer;color:var(--ink)}
#oycwm .lp-note{color:var(--faint);font-size:.74rem}
#oycwm .lp-now{margin:0;font-weight:600}
#oycwm .lay-pop .lp-note+.lp-note{margin:0}
#oycwm .leg-lbl b{font-size:10.5px;font-weight:800;color:#0b2a4a;background:rgba(255,255,255,.85);border-radius:5px;padding:1px 6px;white-space:nowrap;box-shadow:0 0 0 1px rgba(11,42,74,.14);text-shadow:0 0 2px #fff}
/* western-Sound tidal-current arrows (NOAA current predictions) — light map
   tiles in both themes, so no dark variant */
#oycwm .cur-mk{background:none;border:none}
#oycwm .cur-ic{display:flex;flex-direction:column;align-items:center;pointer-events:none}
#oycwm .cur-ic svg{display:block}
#oycwm .cur-lbl{font-size:11.5px;font-weight:800;line-height:1;margin-top:-4px;text-shadow:0 0 2px #fff,0 0 3px #fff,0 0 4px #fff,0 0 4px #fff;white-space:nowrap}
#oycwm .cur-dot{display:block;width:13px;height:13px;border-radius:50%;box-shadow:0 0 0 2px #fff,0 1px 3px rgba(0,0,0,.35)}
#oycwm .leaflet-popup-content{margin:9px 12px}
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
#oycwm.embed #map{height:clamp(460px,calc(100vh - 230px),900px)}
/* one toolbar row: view tabs, presets + Layers, status, full screen */
#oycwm.embed .hd{gap:8px 10px;padding:4px 14px 10px}
#oycwm.embed .tabs{gap:2px}
#oycwm.embed .tabs button{padding:6px 11px}
#oycwm.embed .hd .presetbar{padding:0;gap:8px;flex-wrap:nowrap}
#oycwm.embed .hd .pre-b{padding:0 13px;min-height:36px}
#oycwm.embed .hd .lyr-btn{min-height:36px}
#oycwm.embed .hd .st{order:5;font-size:.72rem}
#oycwm.embed .hd .fsbtn{order:6}
/* Buoy 42 as one compact line at the end of the time-slider row */
#oycwm .ctrl{flex-wrap:wrap;row-gap:6px}
#oycwm .ctrl .erbar{margin:0;padding:4px 10px;border-radius:9px;gap:4px 10px;flex-wrap:nowrap;background:transparent}
#oycwm .ctrl .erbar .er-name{font-size:.74rem;white-space:nowrap}
#oycwm .ctrl .erbar .er-stats{gap:2px 12px;flex-wrap:nowrap;margin-left:0}
#oycwm .ctrl .erbar .st-item{flex-direction:row-reverse;align-items:baseline;gap:4px;white-space:nowrap}
#oycwm .ctrl .erbar .st-item .v{font-size:.84rem}
#oycwm .ctrl .erbar .st-item .k{font-size:.6rem}
@media (max-width:1100px){
	#oycwm .ctrl .erbar{flex-basis:100%;overflow-x:auto;scrollbar-width:none}
	#oycwm .ctrl .erbar::-webkit-scrollbar{display:none}
}
@media (max-width:760px){
	#oycwm.embed .hd .presetbar{display:contents}
	#oycwm.embed .hd .presets{flex:1 1 100%}
	#oycwm.embed .hd .pre-b{padding:0 4px;font-size:.78rem;white-space:nowrap}
	#oycwm.embed .hd .mine-save{margin-right:auto}
	#oycwm.embed .tabs{width:100%;flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;border-radius:12px}
	#oycwm.embed .tabs::-webkit-scrollbar{display:none}
	#oycwm.embed .tabs button{flex:none;padding:7px 12px}
	#oycwm.embed #map{height:clamp(340px,calc(100vh - 330px),640px);height:clamp(340px,calc(100svh - 330px),640px)}
	#oycwm.embed .hd{padding:2px 10px 8px;gap:6px}
	#oycwm.embed .hd .st{display:none}
	#oycwm.embed .hd .pre-b,#oycwm.embed .hd .lyr-btn,#oycwm.embed .hd .fsbtn{min-height:34px}
	#oycwm .ctrl{padding:6px 10px;gap:8px}
	#oycwm .ctrl .tlabel{min-width:0}
}

/* Phones: a lean Chart tab. No Sail/Race/Fish/Cruise or My chart presets (a saved
   My chart still opens the map that way), no Buoy 42 line, and the waypoint table
   sits right under the time slider (moved there in JS, wpPlace) instead of
   floating over the map; same rows, drag-to-reorder, tap-to-zoom, Hide/Show. */
@media (max-width:760px){
	#oycwm .presetbar .presets,#oycwm .presetbar .mine-save{display:none !important}
	#oycwm .ctrl .erbar{display:none !important}
	#oycwm .wptab.wp-below{position:static;width:auto;max-width:none;max-height:none;border:0;border-bottom:1px solid var(--line);border-radius:0;box-shadow:none;overflow:visible}
	#oycwm .wptab.wp-below.wp-on{display:flex}
	#oycwm .wptab.wp-below.wp-img{display:none}
	#oycwm .wptab.wp-below .routebar{padding:6px 10px;gap:6px}
	#oycwm .wptab.wp-below .route-info{flex-basis:100%}
	#oycwm .wptab.wp-below .route-btns{flex-wrap:wrap;gap:4px;margin-left:0;width:100%;justify-content:flex-start}   /* wraps: Autoroute made it six buttons */
	#oycwm .wptab.wp-below .route-btns button,#oycwm .wptab.wp-below .route-btns a{padding:6px 8px;font-size:.66rem}
	#oycwm .wptab.wp-below .wpt tbody tr{padding:6px 10px 6px 18px}
	#oycwm .wptab.wp-below .wpt-foot{padding:5px 10px}
}

#oycwm:not(.embed){padding:16px;background:radial-gradient(1200px 700px at 70% -10%,#ffffff 0%,#eaf1f8 50%,#dde8f2 100%);border-radius:16px}
/* ── Dark theme (driven by the board's data-theme="dark"): dark chrome; the
   Leaflet map and its overlays (barbs, isobars, graticule, routebar, tip help)
   stay on the light base map, as designed ── */
[data-theme="dark"] #oycwm{--ink:#f0eadd;--mute:#aeb9c8;--faint:#8ba0b5;--line:rgba(245,239,226,.15);--panel:#0e2a47}
[data-theme="dark"] #oycwm .erbar{background:linear-gradient(180deg,#143455,#0f2744)}
[data-theme="dark"] #oycwm .erbar .er-name{color:#f0eadd}
[data-theme="dark"] #oycwm .wptab .route-info{color:#f0eadd}
[data-theme="dark"] #oycwm .ctrl button{background:rgba(255,255,255,.06);color:#e7edf4;border-color:rgba(245,239,226,.18)}
[data-theme="dark"] #oycwm .chart-sel{background:rgba(255,255,255,.06);color:#e7edf4}
/* Slider time label: the big time uses var(--navy) (#0b2a4a, not remapped), which
   was invisible on the dark control bar — only the --mute date below showed. */
[data-theme="dark"] #oycwm .ctrl .tlabel{color:#f0eadd}
[data-theme="dark"] #oycwm .barbhint svg line{stroke:#cdd8e4}
[data-theme="dark"] #oycwm .barbhint svg circle{fill:#cdd8e4}
/* Full screen: on the board the card is transparent (embed) and the fullscreen
   backdrop is white, so the card's chrome (tab row, padding) showed the white
   through in dark mode. Give the expanded card a solid dark ground so the whole
   view is dark (the Leaflet map still renders its light tiles below). */
[data-theme="dark"] #oycwm .card.isfull{background:#081a2c}
[data-theme="dark"] #oycwm .card.isfull::backdrop{background:#081a2c}
/* NB: no dark-mode override for the graticule lines — the Leaflet tiles stay
   light in both themes, so the base dark-dash + white-halo rule is correct in
   dark mode too. A light-dash/dark-halo variant read as a fuzzy double line. */
/* The layer-toggle bar is transparent, so in full screen (where the card sits
   over a white backdrop at 55% opacity) the light label/hint text washed out and
   was hard to read. Give the bar a solid dark background; strengthen the toggle
   pills (visible border, blue fill when checked) and brighten the hint text. */
[data-theme="dark"] #oycwm .layers{background:#0e2a47}
[data-theme="dark"] #oycwm .layers label{border-color:rgba(245,239,226,.30)}
[data-theme="dark"] #oycwm .layers label:has(input:checked){background:rgba(87,166,214,.20);border-color:rgba(87,166,214,.6)}
[data-theme="dark"] #oycwm .layers .hint{color:#c3cedb}
[data-theme="dark"] #oycwm .layers select{background:#0e2a47;color:#f0eadd;border-color:rgba(245,239,226,.30)}
[data-theme="dark"] #oycwm .lay-btn{background:#0e2a47;color:#f0eadd;border-color:rgba(245,239,226,.30)}
[data-theme="dark"] #oycwm .lay-pop{background:#0b2240;color:#f0eadd;border-color:rgba(245,239,226,.25)}
[data-theme="dark"] #oycwm .lay-pop .lp-note{color:#c3cedb}
[data-theme="dark"] #oycwm .lp-seg,[data-theme="dark"] #oycwm .lp-step,[data-theme="dark"] #oycwm .lp-chips button,[data-theme="dark"] #oycwm .lp-sec{border-color:rgba(245,239,226,.30)}
/* Route-help tip (over the light map) + wind-barb legend were light text on a
   white/transparent background → nearly invisible in dark mode. Make them dark
   panels with light text. */
[data-theme="dark"] #oycwm .tiphelp{background:rgba(9,27,46,.93);color:#eef3f8;border-color:rgba(245,239,226,.22)}
[data-theme="dark"] #oycwm .barbhint{background:#0e2a47;color:#e7edf4}
[data-theme="dark"] #oycwm .barbhint b{color:#ffffff}
/* Map popups/tooltips (waypoint callouts, POI + buoy popups) sit on the LIGHT
   base map as WHITE cards, so re-assert the dark-ink tokens inside them — the
   #oycwm dark remap above lightens --ink/--mute/--faint for the dark CHROME
   (control bar, readout), which otherwise washed the white callout text out. */
[data-theme="dark"] #oycwm .leaflet-popup-content,
[data-theme="dark"] #oycwm .leaflet-tooltip{--ink:#16324a;--mute:#5a6b7d;--faint:#8a99a8}
/* ---- Offshore passage brief (Layers > Passage brief; off by default) ----
   Polar router + Claude brief (JS: passRun / renderPassCard; server: inc/passage-brief.php).
   Desktop: a 312px card at the map's top-right (steps left of the open Layers
   drawer). Phones: a bottom sheet that opens collapsed to its summary row. */
#oycwm .passcard{display:none;position:absolute;z-index:1000;top:10px;right:10px;width:312px;max-height:calc(100% - 20px);flex-direction:column;
	background:rgba(11,42,74,.95);color:#eaf4fb;border:1px solid rgba(255,255,255,.14);border-radius:12px;box-shadow:0 8px 28px rgba(0,0,0,.38);font-size:.74rem;line-height:1.4;overflow:hidden;transition:right .18s ease}
#oycwm .passcard.on{display:flex}
#oycwm .passcard.offmode,#oycwm .mapwrap.headsup .passcard{display:none !important}
#oycwm .card:not(.hasburger) .mapgrid.lyr-open .passcard{right:282px}
#oycwm .card:not(.hasburger) .mapgrid.lyr-open .passfab{right:282px;transition:right .18s ease}
#oycwm .mapgrid.has-wp .passcard{top:64px;max-height:calc(100% - 74px)}
#oycwm .pc-hd{display:flex;align-items:center;gap:8px;padding:9px 10px 8px 12px;border-bottom:1px solid rgba(255,255,255,.12);flex:none}
#oycwm .pc-hd .pc-ttl{font-weight:800;letter-spacing:.04em;text-transform:uppercase;font-size:.72rem;color:#fff}
#oycwm .pc-chip{display:inline-flex;align-items:center;gap:4px;border-radius:999px;padding:1px 7px;font-size:.6rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;background:rgba(212,168,81,.18);color:#d4a851;border:1px solid rgba(212,168,81,.45)}
#oycwm .pc-hd .pc-sp{flex:1}
#oycwm .pc-ib{flex:none;width:28px;height:28px;border-radius:7px;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.08);color:#fff;font-size:15px;font-weight:800;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0}
#oycwm .pc-ib:hover{background:rgba(255,255,255,.2)}
#oycwm .pc-ib:focus-visible,#oycwm .pc-btn:focus-visible{outline:2px solid #d4a851;outline-offset:2px}
#oycwm .pc-body{overflow-y:auto;-webkit-overflow-scrolling:touch;overscroll-behavior:contain;padding:10px 12px 12px;display:flex;flex-direction:column;gap:10px}
#oycwm .passcard.min{display:none !important}   /* minimized = the glyph at the chart's lower right */
/* Passage brief glyph: always on the chart (lower right, above the compass); opens the brief */
#oycwm .passfab{position:absolute;z-index:997;right:10px;bottom:96px;height:40px;min-width:40px;border-radius:20px;border:1px solid rgba(255,255,255,.25);background:rgba(11,42,74,.95);color:#f3e2b8;
	box-shadow:0 2px 10px rgba(0,0,0,.35);cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;padding:0 10px;font:inherit;-webkit-tap-highlight-color:transparent}
#oycwm .passfab:hover{background:#0b2a4a;border-color:#d4a851}
#oycwm .passfab:focus-visible{outline:2px solid #d4a851;outline-offset:2px}
#oycwm .passfab svg{display:block;flex:none}
#oycwm .passfab .pf-star{position:absolute;top:-4px;right:-3px;font-size:11px;line-height:1;color:#d4a851;text-shadow:0 0 3px rgba(0,0,0,.6)}
#oycwm .passfab .pf-lbl{display:none;font-size:11.5px;font-weight:800;color:#fff;white-space:nowrap;font-variant-numeric:tabular-nums}
#oycwm .passfab.act .pf-lbl:not(:empty){display:inline}
#oycwm .passfab.act{border-color:#d4a851}
#oycwm .passfab.hide,#oycwm .passfab.offmode,#oycwm .mapwrap.headsup .passfab{display:none !important}
#oycwm .pc-route{font-weight:700;color:#fff;font-size:.8rem}
#oycwm .pc-route small{display:block;font-weight:500;color:#9fc3da;font-size:.68rem;margin-top:1px}
#oycwm .pc-kpis{display:grid;grid-template-columns:repeat(3,1fr);gap:6px}
#oycwm .pc-kpi{background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.12);border-radius:8px;padding:6px 7px}
#oycwm .pc-kpi b{display:block;font-size:1rem;font-weight:800;color:#fff;font-variant-numeric:tabular-nums;white-space:nowrap}
#oycwm .pc-kpi span{font-size:.6rem;letter-spacing:.08em;text-transform:uppercase;color:#9fc3da}
#oycwm .pc-verdict{display:flex;gap:8px;align-items:flex-start;border-radius:8px;padding:7px 9px;background:rgba(232,168,60,.14);border:1px solid rgba(232,168,60,.45)}
#oycwm .pc-verdict .pc-vb{flex:none;border-radius:5px;padding:1px 6px;font-size:.62rem;font-weight:800;letter-spacing:.06em;background:#e8a83c;color:#1b1203}
#oycwm .pc-verdict.go{background:rgba(76,175,80,.14);border-color:rgba(76,175,80,.5)}
#oycwm .pc-verdict.go .pc-vb{background:#4caf50;color:#fff}
#oycwm .pc-sec h4{margin:0 0 4px;font-size:.6rem;letter-spacing:.14em;text-transform:uppercase;color:#9fc3da;font-weight:800}
#oycwm .pc-legs{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:5px}
#oycwm .pc-leg{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:8px;padding:6px 8px;cursor:pointer}
#oycwm .pc-leg:hover{background:rgba(255,255,255,.11)}
#oycwm .pc-leg.gs{border-color:rgba(232,104,60,.55)}
#oycwm .pc-lh{display:flex;align-items:center;gap:6px;font-weight:700;color:#fff}
#oycwm .pc-n{flex:none;width:18px;height:18px;border-radius:50%;background:#d4a851;color:#1b1203;font-size:.64rem;font-weight:800;display:flex;align-items:center;justify-content:center}
#oycwm .pc-lh .pc-eta{margin-left:auto;font-weight:800;color:#d4a851;font-variant-numeric:tabular-nums;white-space:nowrap;text-align:right;line-height:1.15}
#oycwm .pc-lh .pc-eta small{display:block;font-size:.58rem;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:#9fc3da;max-width:110px;overflow:hidden;text-overflow:ellipsis}
#oycwm .pc-lg{display:grid;grid-template-columns:auto 1fr;column-gap:8px;row-gap:1px;margin-top:4px;font-variant-numeric:tabular-nums}
#oycwm .pc-lg dt{color:#9fc3da}
#oycwm .pc-lg dd{margin:0;color:#eaf4fb}
#oycwm .pc-lg dd b{color:#fff}
#oycwm .pc-tag{display:inline-block;border-radius:4px;padding:0 5px;margin-left:4px;font-size:.6rem;font-weight:800;letter-spacing:.04em;background:rgba(255,255,255,.14);color:#fff;vertical-align:1px}
#oycwm .pc-tag.motor{background:rgba(232,168,60,.25);color:#f3c879}
#oycwm .pc-list{margin:0;padding-left:16px}
#oycwm .pc-list li{margin:2px 0}
#oycwm .pc-ai{background:rgba(212,168,81,.08);border-left:3px solid #d4a851;border-radius:0 8px 8px 0;padding:7px 9px;color:#f1e9d6}
#oycwm .pc-ai p{margin:0}
#oycwm .pc-chatlog{display:flex;flex-direction:column;gap:6px;margin:4px 0 8px}
#oycwm .pc-msg{padding:7px 9px;border-radius:10px;font-size:.76rem;line-height:1.45;max-width:92%;white-space:normal;overflow-wrap:anywhere}
#oycwm .pc-msg.me{align-self:flex-end;background:#d4a851;color:#0b2a4a;border-bottom-right-radius:3px;font-weight:600}
#oycwm .pc-msg.cl{align-self:flex-start;background:rgba(212,168,81,.08);border-left:3px solid #d4a851;color:#f1e9d6;border-radius:0 10px 10px 0}
#oycwm .pc-msg.sys{align-self:center;background:none;color:#9fc3da;font-style:italic;font-size:.68rem;text-align:center;padding:2px 6px}
#oycwm .pc-msg.wait{display:flex;align-items:center;gap:6px;color:#9fc3da}
#oycwm .pc-chips{display:flex;flex-wrap:wrap;gap:5px;margin:4px 0 8px}
#oycwm .pc-chip-q{font:inherit;font-size:.7rem;color:#dbe9f3;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.22);border-radius:999px;padding:5px 10px;cursor:pointer;text-align:left;min-height:30px}
#oycwm .pc-chip-q:hover{border-color:#d4a851;color:#fff}
#oycwm .pc-ask{display:flex;gap:6px;align-items:stretch}
#oycwm .pc-ask textarea{flex:1;min-width:0;font:inherit;font-size:.76rem;color:#fff;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.22);border-radius:8px;padding:6px 8px;resize:vertical;min-height:40px}
#oycwm .pc-ask textarea::placeholder{color:#8fb0c6}
#oycwm .pc-ask .pc-btn{flex:none;padding:0 14px}
#oycwm .pc-prop{border:1px solid #b48cff;background:rgba(180,140,255,.1);border-radius:10px;padding:8px 10px;margin:4px 0 8px}
#oycwm .pc-prop h4{color:#cdb4ff;margin:0 0 4px}
#oycwm .pc-prop p{margin:0 0 6px;font-size:.74rem;color:#f1e9d6}
#oycwm .pc-prop .pc-pr-rt{color:#dbe9f3;font-size:.7rem}
#oycwm .pc-cmp{width:100%;border-collapse:collapse;font-size:.68rem;margin:2px 0 6px;font-variant-numeric:tabular-nums}
#oycwm .pc-cmp th{text-align:left;color:#9fc3da;font-weight:700;padding:3px 4px 3px 0;vertical-align:top;white-space:nowrap}
#oycwm .pc-cmp td{color:#fff;padding:3px 4px;vertical-align:top;border-top:1px solid rgba(255,255,255,.1)}
#oycwm .pc-chg{list-style:none;margin:0 0 6px;padding:0;display:flex;flex-direction:column;gap:5px;counter-reset:chg}
#oycwm .pc-chg li{display:flex;flex-direction:column;gap:5px;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.14);border-radius:8px;padding:6px 8px;font-size:.72rem;color:#fff;counter-increment:chg}
#oycwm .pc-chg li>div::before{content:counter(chg) ". ";color:#cdb4ff;font-weight:800}
#oycwm .pc-chg li.ok{border-color:#5fbf7f;background:rgba(95,191,127,.12)}
#oycwm .pc-chg li.no{opacity:.55}
#oycwm .pc-chg small{color:#9fc3da}
#oycwm .pc-chg span:not(.pc-yn){display:block;color:#dbe9f3;font-size:.68rem;margin-top:2px}
#oycwm .pc-chg span.pc-swwarn{color:#ffb4a8;font-weight:700}
#oycwm .pc-yn{display:flex;gap:6px}
#oycwm .pc-yn button{flex:1;font:inherit;font-size:.68rem;font-weight:700;border-radius:6px;padding:4px 6px;min-height:30px;cursor:pointer;background:transparent;color:#dbe9f3;border:1px solid rgba(255,255,255,.25)}
#oycwm .pc-yn .pc-y.on{background:#5fbf7f;border-color:#5fbf7f;color:#0b2a4a}
#oycwm .pc-yn .pc-n.on{background:#c2491f;border-color:#c2491f;color:#fff}
#oycwm .pc-cmp tr:first-child td{color:#9fc3da;font-weight:700;border-top:0}
#oycwm .pc-sec-plan summary{list-style:none;display:flex;gap:8px;cursor:pointer;min-height:28px;align-items:center}
#oycwm .pc-sec-plan summary::-webkit-details-marker{display:none}
#oycwm .pc-sec-plan summary h4{margin:0}
#oycwm .pc-sec-plan summary span{flex:1;color:#eaf4fb;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
#oycwm .pc-sec-plan summary b{color:#d4a851;font-size:.7rem}
#oycwm .pc-sec-plan[open] summary b{visibility:hidden}
#oycwm .pc-sec-plan .pc-acts{margin-top:8px}
#oycwm .pc-plan{display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:6px}
#oycwm .pc-plan label{display:flex;flex-direction:column;gap:2px;font-size:.6rem;letter-spacing:.06em;text-transform:uppercase;color:#9fc3da;font-weight:700}
#oycwm .pc-plan select,#oycwm .pc-plan input{font:inherit;font-size:.74rem;letter-spacing:0;text-transform:none;color:#fff;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.2);border-radius:6px;padding:5px 6px;min-height:32px;width:100%;box-sizing:border-box}
#oycwm .pc-plan select option{color:#0b2a4a}
#oycwm .pc-acts{display:flex;gap:6px;flex-wrap:wrap}
#oycwm .pc-btn{flex:1 1 auto;min-height:34px;border-radius:8px;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.08);color:#fff;font:inherit;font-size:.74rem;font-weight:700;cursor:pointer;padding:0 10px;white-space:nowrap}
#oycwm .pc-btn.pri{background:#d4a851;border-color:#d4a851;color:#1b1203}
#oycwm .pc-btn:hover{filter:brightness(1.08)}
#oycwm .pc-btn[disabled]{opacity:.55;cursor:default;filter:none}
#oycwm .pc-foot{font-size:.62rem;color:#87a9c0;border-top:1px solid rgba(255,255,255,.1);padding-top:7px}
#oycwm .pc-busy .pc-body>*:not(.pc-sec-plan){opacity:.35;transition:opacity .2s}
#oycwm .pass-lbl{background:rgba(11,42,74,.9);color:#fff;border:0;border-radius:6px;font-size:11px;font-weight:700;padding:2px 6px;box-shadow:0 1px 4px rgba(0,0,0,.35)}
#oycwm .pass-lbl.gs{background:#c2491f}
#oycwm .pass-lbl:before{display:none}
@media (max-width:760px){
	#oycwm .passcard{top:auto;bottom:8px;left:8px;right:8px;width:auto;max-height:62%;background:rgba(11,42,74,.985)}
	#oycwm .card:not(.hasburger) .mapgrid.lyr-open .passcard,#oycwm .mapgrid.has-wp .passcard{right:8px;top:auto;max-height:62%}
	#oycwm .pc-hd{cursor:pointer}
}
#oycwm .pc-status{display:flex;align-items:center;gap:8px;color:#d4a851;font-weight:700}
#oycwm .pc-spin{width:14px;height:14px;border-radius:50%;border:2px solid rgba(212,168,81,.3);border-top-color:#d4a851;animation:pcspin .8s linear infinite;flex:none}
@keyframes pcspin{to{transform:rotate(360deg)}}
@media (prefers-reduced-motion:reduce){#oycwm .pc-spin{animation:none}}
#oycwm .pc-verdict.hold{background:rgba(220,70,50,.16);border-color:rgba(220,70,50,.55)}
#oycwm .pc-verdict.hold .pc-vb{background:#dc4632;color:#fff}
#oycwm .pc-best{margin-top:5px;padding:5px 8px;border-radius:7px;background:rgba(76,175,80,.13);border:1px solid rgba(76,175,80,.4)}
#oycwm .pc-link{border:0;background:none;color:#d4a851;font:inherit;font-weight:800;text-decoration:underline;cursor:pointer;padding:0 2px;min-height:24px}
#oycwm .pc-note{margin:6px 0 0;font-size:.68rem;color:#9fc3da}
#oycwm .pc-note.warn{color:#f3c879}
#oycwm .pc-plan .pc-wide{grid-column:1/-1}
#oycwm .pc-pt{display:flex;flex-wrap:wrap;align-items:center;gap:6px 8px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.16);border-radius:6px;padding:4px 8px;min-height:36px;box-sizing:border-box}
#oycwm .pc-pt.on{border-color:#d4a851;background:rgba(212,168,81,.14)}
#oycwm .pc-pl{font-size:.6rem;letter-spacing:.06em;text-transform:uppercase;color:#9fc3da;font-weight:700;width:44px;flex:none}
#oycwm .pc-pv{flex:1;min-width:0;font-size:.74rem;color:#fff;font-weight:700;overflow:hidden;text-overflow:ellipsis}
#oycwm .pc-pv small{display:block;font-weight:500;color:#9fc3da;font-size:.64rem}
#oycwm .pc-pt .pc-link{flex:none;white-space:nowrap}
#oycwm .pc-plan .pc-pt select.pc-choose{flex:1 1 100%;min-height:30px;font-size:.7rem;padding:3px 6px}
#oycwm .pc-plan label.pc-chk{flex-direction:row;align-items:center;gap:8px;text-transform:none;letter-spacing:0;font-size:.72rem;color:#dbe9f3;font-weight:600}
#oycwm .pc-plan label.pc-chk input{width:auto;min-height:0}
#oycwm .leaflet-container.pass-picking,#oycwm .leaflet-container.pass-picking .leaflet-interactive{cursor:crosshair}
#oycwm .pc-plan input::-webkit-calendar-picker-indicator{filter:invert(1)}
#oycwm .pass-eta-mk{background:none;border:0}
#oycwm .pass-eta-mk .pass-lbl{display:inline-block;white-space:nowrap;pointer-events:none}
#oycwm .pin-co .co-nm{font-size:.78rem;font-weight:800;color:var(--navy);text-align:center;margin:0 18px 1px}
#oycwm .pin-co .co-eta{font-size:.74rem;font-weight:800;color:#8a6420;text-align:center;margin:0 0 2px}
#oycwm .wpt td .s.nm{font-weight:800;color:var(--navy)}
#oycwm .wpt td .s.eta.pass{color:#8a6420;font-weight:700}
[data-theme="dark"] #oycwm .pin-co .co-nm,[data-theme="dark"] #oycwm .wpt td .s.nm{color:#f0eadd}
[data-theme="dark"] #oycwm .pin-co .co-eta,[data-theme="dark"] #oycwm .wpt td .s.eta.pass{color:#e2c07a}
#oycwm .pc-racetip .pc-pv,#oycwm .pc-bm .pc-pv{flex:1 1 100%;white-space:normal}
#oycwm .pc-racetip .pc-pv small{display:block;font-weight:400;opacity:.8}
#oycwm .pass-lbl.tick{background:rgba(255,255,255,.88);color:#0b2a4a;font-size:10px;padding:1px 4px}
#oycwm .pc-reclist{list-style:none;margin:6px 0 0;padding:0;display:flex;flex-direction:column;gap:3px}
#oycwm .pc-reclist li{display:flex;flex-wrap:wrap;justify-content:space-between;gap:2px 8px;padding:4px 7px;border-radius:6px;background:rgba(255,255,255,.05);border-left:3px solid #4caf50}
#oycwm .pc-reclist li.bad{border-left-color:#dc4632}
#oycwm .pc-reclist small{flex-basis:100%;color:#9fc3da}
#oycwm .pc-rec p{margin:0}
#oycwm .pc-mini{display:none;font-weight:700;color:#d4a851;font-size:.7rem;white-space:nowrap;font-variant-numeric:tabular-nums}
#oycwm .passcard.min .pc-mini{display:inline}
#oycwm .pin-co .co-pass{font-size:.7rem;color:#16324a;text-align:center;margin:0 0 3px;line-height:1.35}
#oycwm .pin-co .co-pass b{font-weight:800}
#oycwm .pin-co .co-pass .pc-tag{display:inline-block;margin-left:4px;font-size:.6rem;font-weight:800;padding:0 4px;border-radius:3px;background:#f3e2b8;color:#6b4a10}
#oycwm .pin-co .co-pass.warn,#oycwm .wpt td .s.pass.warn{color:#b3261e}
#oycwm .wpt td .s.pass{color:#5a4a2a}
[data-theme="dark"] #oycwm .pin-co .co-pass{color:#dce8f2}
[data-theme="dark"] #oycwm .wpt td .s.pass{color:#d9c79d}
#oycwm .pc-hd .pc-ttl{white-space:nowrap}
@media (max-width:760px){#oycwm .passcard.min .pc-chip{display:none}}
</style>
<div id="oycwm" class="<?php echo esc_attr( trim( ( $embed ? 'embed' : '' ) . ( is_user_logged_in() ? '' : ' nologin' ) ) ); ?>">
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
				<button id="tabWind" class="on" role="tab" aria-selected="true">Chart Plotter</button>
				<button id="tabSat" role="tab" aria-selected="false">Satellite</button>
				<button id="tabCharts" role="tab" aria-selected="false" title="Additional charts (NWS/OPC)" aria-label="Additional charts">Add&rsquo;l Charts</button>
			</div>
			<!-- layer presets + the Layers drawer toggle (the drawer itself sits over the map) -->
			<div class="presetbar" id="presetBar">
				<button type="button" class="lyr-btn" id="lyrBtn" aria-expanded="true" aria-controls="lyrDrawer"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M12 3 2 8l10 5 10-5z"/><path d="m2 13 10 5 10-5"/></svg>Layers</button>
				<div class="presets" role="group" aria-label="Layer presets">
					<button type="button" class="pre-b pre-mine" data-preset="mine" aria-pressed="false" title="Your saved map setup">My Chart</button>
				</div>
				<button type="button" class="mine-save" id="mineSave" title="Save the current layers, view, map position and boat polars as My Chart">Save</button>
			</div>
			<span class="st" id="st">Loading&hellip;</span>
			<button type="button" class="fsbtn" id="oycwmFs" title="Full screen" aria-label="Full screen"></button>
		</div>

		<div class="ais-note" id="aisNote"></div>
		<div class="ais-note" id="nnNote"></div>
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
		</div>

		<div class="mapgrid" id="mapGrid">
		<div class="mapwrap">
			<aside class="lyr-drawer" id="lyrDrawer" aria-label="Map layers">
				<div class="lyr-hd"><b>Layers</b><button type="button" class="lyr-x" id="lyrClose" aria-label="Close layers">&times;</button></div>
				<div class="layers lyr-sec" id="windLayers">
					<div class="lyr-g" role="group" aria-labelledby="lyrGW"><h3 id="lyrGW">Weather</h3>
						<label><input type="checkbox" id="tgParticles"> Animated</label>
						<label><input type="checkbox" id="tgWindColor"> Wind color</label>
						<label><input type="checkbox" id="tgArrows"> Wind barbs</label>
						<label><input type="checkbox" id="tgIso"> Isobars</label>
						<label><input type="checkbox" id="tgRadarOv"> Radar</label>
						<label><input type="checkbox" id="tgTemp"> Temp</label>
						<label><input type="checkbox" id="tgPrecip"> Precip</label>
						<label><input type="checkbox" id="tgWave"> Waves</label>
						<label><input type="checkbox" id="tgCur"> Current</label>
						<label><input type="checkbox" id="tgGulf"> Gulf Stream</label>
					</div>
					<div class="lyr-g" role="group" aria-labelledby="lyrGN"><h3 id="lyrGN">Navigation</h3>
						<label class="mbr-only"><input type="checkbox" id="tgLoc"> My location</label>
						<label title="Line ahead of each boat showing where it will be at its current speed and course">Vector <select id="vecMin" aria-label="Course vector length"><option value="0">off</option><option value="5">5 min</option><option value="10" selected>10 min</option><option value="30">30 min</option></select></label>
						<label title="Lines you can sail straight to a mark on, each tack, from the forecast wind"><input type="checkbox" id="tgLay"> Laylines <button type="button" class="lay-btn" id="layBtn" aria-expanded="false" aria-controls="layPop" title="Layline angles">45°</button></label>
						<div class="lay-pop" id="layPop" hidden role="group" aria-label="Layline angles">
								<div class="lp-h"><b>Layline angles</b><button type="button" class="lp-x" id="lpClose" aria-label="Close">&times;</button></div>
								<div class="lp-seg" role="group" aria-label="How to set the angle">
									<button type="button" data-lm="type" aria-pressed="true">Boat type</button><button type="button" data-lm="polar" aria-pressed="false">My polars</button><button type="button" data-lm="tacks" aria-pressed="false">My tacks</button>
								</div>
								<div class="lp-pane" data-lp="type">
									<div class="lp-chips" id="lpTypes"></div>
									<div class="lp-row">
										<span class="lp-pair"><span class="lp-lbl">Upwind</span><span class="lp-step"><button type="button" data-ls="up" data-d="-1" aria-label="Point higher, 1 degree">&minus;</button><output id="lpUp">45°</output><button type="button" data-ls="up" data-d="1" aria-label="Point lower, 1 degree">+</button></span></span>
										<span class="lp-pair"><span class="lp-lbl">Gybe at</span><span class="lp-step"><button type="button" data-ls="gybe" data-d="-1" aria-label="Gybe higher, 1 degree">&minus;</button><output id="lpGybe">145°</output><button type="button" data-ls="gybe" data-d="1" aria-label="Gybe deeper, 1 degree">+</button></span></span>
									</div>
								</div>
								<div class="lp-pane" data-lp="polar" hidden>
									<div class="lp-row"><span class="lp-lbl">Offset</span><span class="lp-step"><button type="button" data-ls="off" data-d="-1" aria-label="Offset down, 1 degree">&minus;</button><output id="lpOff">+0°</output><button type="button" data-ls="off" data-d="1" aria-label="Offset up, 1 degree">+</button></span>
										<span class="lp-note">+2° if you point 2° lower (and gybe 2° higher) than your polars.</span></div>
								</div>
								<div class="lp-pane" data-lp="tacks" hidden>
									<div class="lp-row"><span class="lp-note" id="lpMeas"></span><button type="button" class="lp-sec" id="lpReset">Clear measurements</button></div>
									<span class="lp-note">With My location on, each clean tack or gybe is measured from your course over ground: half the change in course. The last three are averaged.</span>
								</div>
								<p class="lp-now" id="lpNow"></p>
								<p class="lp-note">Angles are true wind angles. Close-hauled at 45° true is roughly 30&ndash;35° apparent on your instruments.</p>
						</div>
						<label><input type="checkbox" id="tgFleet"> Other boats</label>
						<label title="Vessels transmitting AIS (ships, ferries, tugs, larger yachts)"><input type="checkbox" id="tgAis"> AIS ships</label>
						<?php if ( function_exists( 'oyc_nn_ready' ) && oyc_nn_ready() ) : ?>
						<label title="Coast Guard Local Notice to Mariners: dredging, surveys, buoys off station, construction, safety zones"><input type="checkbox" id="tgNotice"> Notices</label>
						<?php endif; ?>
						<label class="mbr-only" title="Members: show your boat to others on this map while the page is open"><input type="checkbox" id="tgShare"> Share my position</label>
						<label><input type="checkbox" id="tgRace"> Race</label>
						<?php if ( function_exists( 'oyc_race_tools_can_use' ) && oyc_race_tools_can_use() ) : ?>
						<label title="Racers: polars and target speed, race log and the team race archive (the panel under the map)"><input type="checkbox" id="tgRaceTools"> Race tools</label>
						<?php endif; ?>
						<label title="Offshore passage brief: Long Island Sound to Bermuda routed through the forecast with Shannon 43 polars, The Race tide and the Gulf Stream, plus a written brief from Claude"><input type="checkbox" id="tgPassage"> Passage brief</label>
					</div>
					<div class="lyr-draft"><label for="boatDraft">My draft</label><input type="text" id="boatDraft" inputmode="text" autocomplete="off" placeholder="4'10&quot;" value="6'" title="Feet and inches, e.g. 4'10&quot; or 4.8"><span class="hint" id="draftNote"></span></div>
					<details class="lyr-help"><summary>What these mean</summary><span class="hint">Laylines = the starboard (green) and port (red) close-hauled or gybing lines into the next waypoint, or into each upwind/downwind leg of your route or race; tap the angle to set it by boat type, from your polars, or measured from your own tacks; with Race on, every race mark shows its laylines and wind dial for the slider hour &middot; Vector line = direction of travel &middot; AIS ships = vessels transmitting AIS (mostly commercial traffic and larger yachts; via aisstream.io, updated every couple of minutes) &middot; Notices = the Coast Guard District&nbsp;1 Local Notice to Mariners for the Sound (Army Corps dredging and surveys, buoys missing or off station, lights out, bridge and marine construction, safety zones), checked twice a day, on unless you switch it off; tap a warning pin for the notice &middot; Other boats = members sharing their live position (a boat drops off 10&nbsp;min after its last fix) &middot; Share my position works while this page is open on screen &middot; Vector = where each boat will be at its current speed and course &middot; Route legs show the true wind angle (TWA) you&rsquo;d sail at the slider&rsquo;s forecast hour &middot; My location = your live GPS; each waypoint then shows its magnetic bearing (&deg;M) and distance from you &middot; Current = NOAA tidal current at western-Sound stations (reverses with the tide; scrub the slider) &middot; zoom out for the Atlantic pattern &middot; Gulf Stream = ocean current, Florida&rarr;Newfoundland</span></details>
				</div>
				<div class="layers lyr-sec" id="poiLayers">
					<div class="lyr-g" role="group" aria-labelledby="lyrGP"><h3 id="lyrGP">Chart &amp; places</h3>
						<label><input type="checkbox" id="tgChart" checked> NOAA chart</label>
						<label><input type="checkbox" id="tgClubs" checked> Reciprocal clubs</label>
						<label><input type="checkbox" id="tgDine" checked> Dock &amp; dine</label>
						<label><input type="checkbox" id="tgFuel" checked> Fuel docks</label>
						<label><input type="checkbox" id="tgAnch" checked> Anchorages</label>
						<label><input type="checkbox" id="tgFish" checked> Fishing spots</label>
					</div>
					<details class="lyr-help"><summary>About the chart and places</summary><span class="hint">NOAA chart = official electronic chart data (depths, buoys, lights, rocks), not for navigation &middot; tap a pin for distance from OYC &amp; links &middot; fuel, anchorage and fishing notes are local knowledge — verify on the chart and call ahead</span></details>
				</div>
			</aside>
			<div class="maprot" id="mapRot">
				<div id="map"></div>
				<div class="grat" id="gratic" aria-hidden="true"></div>
			</div>
			<button type="button" class="navrose mbr-only" id="navRose" title="Heads-up / North-up view" aria-label="Toggle heads-up (course-up) view" aria-pressed="false"><svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true"><polygon points="12,3 16,13 12,11 8,13" fill="#c0392b"/><polygon points="12,21 8,11 12,13 16,11" fill="#8aa0ac"/></svg></button>
			<button type="button" class="navfollow mbr-only" id="navFollow" title="Centre on the boat and follow it again" aria-label="Follow boat"><svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><circle cx="12" cy="12" r="3.5" fill="currentColor"/><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 1v4M12 19v4M1 12h4M19 12h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>Follow boat</button>
			<div class="racebar" id="raceBar">
				<span class="rc-lab">Course</span>
				<input type="text" id="raceCourse" placeholder="e.g. A F C A" autocapitalize="characters" autocorrect="off" spellcheck="false" aria-label="Race course — mark letters in order">
				<button type="button" id="racePlot">Plot</button>
				<button type="button" id="raceClear" class="rb-clear">Clear</button>
				<span class="rc-info" id="raceInfo"></span>
			</div>
			<div class="leftstack" id="leftStack">
				<button type="button" class="stack-toggle" id="stackToggle" aria-label="Hide cards">Hide cards</button>
				<div class="boatinfo" id="boatInfo" aria-label="Live boat course and speed"></div>
				<div class="racesheet" id="raceSheet" aria-label="Race course details"></div>
			</div>
			<aside class="passcard" id="passCard" aria-label="Offshore passage brief" aria-live="polite"></aside>
			<button type="button" class="passfab" id="passFab" title="Passage brief: route your waypoints and ask Claude" aria-label="Open the passage brief" aria-expanded="false"><svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true"><path d="M11 3v13H4.5L11 3z" fill="currentColor"/><path d="M12.5 6.5c3.2 2.4 5 5.6 5.5 9.5h-5.5V6.5z" fill="currentColor" opacity=".75"/><path d="M3 18.5h18l-2.2 2.5H5.2L3 18.5z" fill="currentColor"/></svg><span class="pf-star" aria-hidden="true">✦</span><span class="pf-lbl" id="passFabLbl"></span></button>
			<div class="satpanel" id="satPanel" style="display:none"><img id="satImg" alt="NOAA GOES-East infrared satellite — North Atlantic"></div>
			<div class="map-ll">
				<div class="hovertip" id="hoverTip"></div>
				<div class="tiphelp collapsed" id="tipHelp"><button type="button" class="tip-tog" id="tipTog" aria-expanded="false" aria-controls="tipText" title="Show map tips"><span class="tip-sign" aria-hidden="true">+</span><span class="tip-lbl">Map tips</span></button><span class="tip-txt" id="tipText">Click the map anywhere to plot a route (your first point is the start, or tap From my boat), then tap Show table for every point&rsquo;s forecast (drag a row up or down to change the order). Drag a point to move it. Tap a point to show its card, then drag the card anywhere (double-tap it to snap back). Use &#10005; on a card or in the table to remove a point. Wind barbs: the staff points the direction the <b>wind comes from</b>; feathers give the speed (half&nbsp;=&nbsp;5, full&nbsp;=&nbsp;10, pennant&nbsp;=&nbsp;50&nbsp;kt).</span></div>
			</div>
		</div>
		<aside class="wptab collapsed" id="wpTab" aria-label="Passage waypoints"><?php /* starts hidden: only the summary bar + buttons show until "Show table" */ ?>
			<div class="routebar" id="routeBar">
				<div class="route-info" id="routeInfo"></div>
				<div class="route-btns">
					<button type="button" id="rbExport" title="Download the route + forecast as GPX (for your chartplotter) and CSV">&#8681;&nbsp;Save route</button>
					<a id="rbGrib" href="<?php echo $oyc_ajax; ?>?action=oyc_grib" rel="nofollow" title="Latest NOAA GFS forecast for western Long Island Sound as a GRIB2 file: 10 m wind, gusts and sea-level pressure, 0-48 h. Opens in OpenCPN, XyGrib, PredictWind Offshore, Expedition, qtVlm and most GRIB viewers. The first download after a new model run can take a few seconds.">&#8681;&nbsp;GRIB</a>
					<button type="button" class="rb-tog mbr-only" id="rbFromBoat" aria-pressed="false" title="Start the route from your live GPS position (turns on My location)">From my boat</button>
					<button type="button" class="rb-auto" id="rbAuto" title="Check the legs for land and shallow water and fix them">Autoroute</button>
					<button type="button" class="rb-clear" id="rbClear" title="Remove all waypoints">Clear</button>
					<button type="button" class="rb-tog" id="rbToggle" aria-expanded="false" aria-controls="wpTab" title="Hide or show the waypoint table">Show table</button>
				</div>
			</div>
			<div class="arpanel" id="arPanel" hidden aria-live="polite"></div>
			<div class="wpt-wrap">
				<table class="wpt">
					<thead><tr><th class="n">#</th><th>Dist (nm)</th><th>Wind</th><th>Seas &middot; precip</th><th class="x"><span class="sr">Remove</span></th></tr></thead>
					<tbody id="wptBody"></tbody>
				</table>
			</div>
			<div class="wpt-foot" id="wptFoot"></div>
		</aside>
		</div>
		<div class="foot" id="foot">Wind, pressure &amp; temp from <a href="https://open-meteo.com" target="_blank" rel="noopener">Open-Meteo</a> (GFS-based). Radar close-up = HRRR precipitation (Open-Meteo); national view = NEXRAD mosaic + HRRR reflectivity (Iowa State IEM).</div>
	</div>

	<?php echo function_exists( 'oyc_race_tools_markup' ) ? oyc_race_tools_markup() : ''; // racers only: polars, target speed, race log ?>

	<p class="disclaimer">Forecasts are best treated as an opinion. Poseidon always has the final word.</p>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/leaflet-velocity@1.7.0/dist/leaflet-velocity.min.js"></script>
<script>
(function(){
"use strict";
var AJAX=<?php echo wp_json_encode( $oyc_ajax ); ?>;
var STAT=<?php echo wp_json_encode( esc_url_raw( function_exists( 'oyc_weather_static_url' ) ? oyc_weather_static_url() : '' ) ); ?>; // cron-warmed static JSON (Apache-served, no WP boot)
/* Fetch the cron-warmed static file first (served by Apache in ~50ms, vs ~1.3s
   for admin-ajax which boots all of WordPress), falling back to the proxy if the
   file is missing or stale. Resolves to a Response so callers keep using .json(). */
function statFetch(file, ajaxAction, maxAgeMin, timeout){
	if(!STAT) return fetchT(AJAX+'?action='+ajaxAction, timeout);
	return fetch(STAT+file+'.json',{cache:'no-store'}).then(function(r){
		if(!r.ok) throw 0;
		var lm=r.headers.get('last-modified');
		if(lm && (Date.now()-Date.parse(lm)) > maxAgeMin*60000) throw 0; // stale → proxy
		return r;
	}).catch(function(){ return fetchT(AJAX+'?action='+ajaxAction, timeout); });
}
var RAMP=['#8fc0dd','#5aa6d0','#3f93c9','#d9c07a','#e0a13f','#dd7f3a','#cf5638','#b23a2a','#8f2d20'];
document.getElementById('scale').innerHTML=RAMP.map(function(c){return '<i style="background:'+c+'"></i>';}).join('');
function spdColor(kt){var b=[[8,'#8fc0dd'],[11,'#5aa6d0'],[14,'#3f93c9'],[17,'#d9c07a'],[20,'#e0a13f'],[24,'#dd7f3a'],[28,'#cf5638'],[34,'#b23a2a'],[999,'#8f2d20']];for(var i=0;i<b.length;i++)if(kt<b[i][0])return b[i][1];}
var KT=1.94384;
var ER={lat:40.92465,lon:-73.70251}; /* Buoy 42 = Scotch Caps Lighted Bell Buoy 42, at its NOAA ENC charted position (the old 40.93717,-73.70217 sat ~0.75 nm north, off the chart's R "42"); the weather-readout point, same as the /weather/ board */
/* great-circle distance in nautical miles (haversine, R=3440.065 nm) */
function distNM(lat1,lon1,lat2,lon2){var R=3440.065,d=Math.PI/180,
	a=Math.sin((lat2-lat1)*d/2)*Math.sin((lat2-lat1)*d/2)+Math.cos(lat1*d)*Math.cos(lat2*d)*Math.sin((lon2-lon1)*d/2)*Math.sin((lon2-lon1)*d/2);
	return 2*R*Math.asin(Math.min(1,Math.sqrt(a)));}
function nmFromBuoy42(lat,lon){return distNM(ER.lat,ER.lon,lat,lon);}
/* ---------- My location (live GPS) + magnetic bearing to each waypoint ----------
   Geolocation works on the phone over HTTPS. Magnetic variation is ~13°W for the
   western Sound, so magnetic bearing = true + 13. The marker + the "From you"
   line on each waypoint card update live via watchPosition and on drag. */
var MAGVAR=13;
function brgTrue(lat1,lon1,lat2,lon2){var d=Math.PI/180,p1=lat1*d,p2=lat2*d,dl=(lon2-lon1)*d;
	var y=Math.sin(dl)*Math.cos(p2),x=Math.cos(p1)*Math.sin(p2)-Math.sin(p1)*Math.cos(p2)*Math.cos(dl);
	return (Math.atan2(y,x)/d+360)%360;}
function magBrg(lat1,lon1,lat2,lon2){return Math.round((brgTrue(lat1,lon1,lat2,lon2)+MAGVAR)%360);}
var myTrack=[], myLoc=null, myHeading=null, mySpeedKt=null, prevFix=null, locLayer=null, locWatch=null, locFirst=false;
var headsUp=false, mapwrapEl=null, myMk=null;
function mwEl(){if(!mapwrapEl)mapwrapEl=document.querySelector('#oycwm .mapwrap');return mapwrapEl;}
function applyHdg(){if(!headsUp)mwEl().style.setProperty('--hdg',(myHeading!=null?myHeading:0)+'deg');}
/* heads-up locks dragging, box zoom and the keyboard (they'd move the rotated
   chart the wrong way); pinch and wheel zoom stay on but zoom around the centre
   (the boat). Taps are un-rotated in mapPointFromEvent so they land right. */
function lockMap(lock){['dragging','doubleClickZoom','boxZoom','keyboard'].forEach(function(k){try{if(map[k]){lock?map[k].disable():map[k].enable();}}catch(e){}});
	map.options.touchZoom=lock?'center':true;map.options.scrollWheelZoom=lock?'center':true;}
/* Heads-up animation. HU.disp is the angle on screen (not wrapped to 0-360, so
   359°→1° turns 2°, not 358°); each frame it eases toward the target heading
   with a ~0.35 s time constant, and the 1.5x zoom-in that hides the corners
   eases in and out the same way. The boat marker glides between GPS fixes. */
var HU={disp:0,scale:1,raf:0,last:0,cmp:null,cmpT:0,exiting:false,mk:null,mkFrom:null,mkTo:null,mkT0:0,free:false};
/* Panning in heads-up stops following the boat until you tap "Follow boat"
   (the button shows only while following is off). */
function huFree(){return HU.free;}
function setHuFree(on){HU.free=!!on;mwEl().classList.toggle('hufree',HU.free);
	if(!HU.free&&headsUp&&myLoc){try{map.setView([myLoc.lat,myLoc.lon],map.getZoom(),{animate:true});}catch(e){}}}
function huTarget(){
	if(HU.exiting)return 0;
	var moving=mySpeedKt!=null&&mySpeedKt>=1.5&&myHeading!=null;
	if(moving)return myHeading;                                  /* course over ground */
	if(HU.cmp!=null&&Date.now()-HU.cmpT<4000)return HU.cmp;       /* slow/stopped: phone compass */
	return myHeading;                                            /* last known course (or hold) */
}
function headsUpTick(t){
	HU.raf=0;
	var dt=HU.last?Math.min(0.1,Math.max(0,(t-HU.last)/1000)):0.016;HU.last=t;
	var tg=huTarget(),k=1-Math.exp(-dt/0.35);
	var diff=0;if(tg!=null){diff=((tg-HU.disp)%360+540)%360-180;HU.disp+=Math.abs(diff)<0.05?diff:diff*k;}
	var sTg=HU.exiting?1:1.5;HU.scale+=(sTg-HU.scale)*k;
	var w=mwEl();w.style.setProperty('--hdg',HU.disp.toFixed(2)+'deg');w.style.setProperty('--hus',HU.scale.toFixed(3));
	/* boat marker: glide from the previous fix to the new one over ~0.9 s */
	var f=1;if(HU.mk&&HU.mkTo){f=Math.min(1,(Date.now()-HU.mkT0)/900);var a=HU.mkFrom||HU.mkTo;
		HU.mk.setLatLng([a[0]+(HU.mkTo[0]-a[0])*f,a[1]+(HU.mkTo[1]-a[1])*f]);}
	if(HU.exiting){var r=((HU.disp%360)+360)%360;if((r<0.5||r>359.5)&&HU.scale<1.01){HU.exiting=false;HU.disp=0;HU.scale=1;
		w.classList.remove('headsup');w.style.removeProperty('--hus');applyHdg();HU.last=0;return;}}
	/* settled (pointing at the target, zoom done, boat arrived): rest until the
	   next GPS fix or compass reading wakes it, to save battery */
	if(!HU.exiting&&Math.abs(diff)<0.05&&Math.abs(sTg-HU.scale)<0.002&&f>=1){HU.last=0;return;}
	HU.raf=requestAnimationFrame(headsUpTick);
}
function huKick(){if(!HU.raf){HU.last=0;HU.raf=requestAnimationFrame(headsUpTick);}}
function setHeadsUp(on){
	if(on===headsUp&&!HU.exiting)return;
	headsUp=on;lockMap(on);HU.free=false;mwEl().classList.remove('hufree');var w=mwEl();
	if(on){HU.exiting=false;if(!w.classList.contains('headsup')){HU.disp=0;HU.scale=1;w.style.setProperty('--hdg','0deg');w.style.setProperty('--hus','1');w.classList.add('headsup');}
		if(myLoc){try{map.setView([myLoc.lat,myLoc.lon],map.getZoom(),{animate:false});}catch(e){}}
		compassStart();}
	else{HU.exiting=true;compassStop();}   /* turn back to north and zoom out, then drop the class */
	huKick();
}
/* map taps while rotated: Leaflet reads the tap against the un-rotated box, so
   undo the rotation + scale about the map's centre first */
function mapPointFromEvent(e){
	var c=map.getContainer(),r=c.getBoundingClientRect(),ev=(e.touches&&e.touches[0])||(e.changedTouches&&e.changedTouches[0])||e,
		cx=r.left+r.width/2,cy=r.top+r.height/2,dx=(ev.clientX-cx)/HU.scale,dy=(ev.clientY-cy)/HU.scale,a=HU.disp*Math.PI/180;
	return L.point(c.clientWidth/2+dx*Math.cos(a)-dy*Math.sin(a),c.clientHeight/2+dx*Math.sin(a)+dy*Math.cos(a));
}
/* phone compass, for when the boat is too slow for a GPS course: iOS gives
   webkitCompassHeading (magnetic), Android deviceorientationabsolute (alpha,
   counter-clockwise from north). Converted to true with MAGVAR and corrected
   for a sideways (landscape) screen; lightly smoothed. */
var cmpOn=false;
function onCompass(e){
	var h=null;
	if(e.webkitCompassHeading!=null&&isFinite(e.webkitCompassHeading))h=e.webkitCompassHeading-MAGVAR;
	else if(e.absolute&&e.alpha!=null&&isFinite(e.alpha))h=360-e.alpha-MAGVAR;
	if(h==null)return;
	var so=(screen.orientation&&typeof screen.orientation.angle==='number')?screen.orientation.angle:(window.orientation||0);
	h=((h+so)%360+360)%360;
	if(HU.cmp==null)HU.cmp=h;else{var d=((h-HU.cmp)%360+540)%360-180;HU.cmp=((HU.cmp+d*0.3)%360+360)%360;}
	HU.cmpT=Date.now();
	if(headsUp)huKick();
}
function compassStart(){
	if(cmpOn)return;cmpOn=true;
	try{
		var D=window.DeviceOrientationEvent;
		if(D&&typeof D.requestPermission==='function'){   /* iOS 13+: must be asked from a tap */
			D.requestPermission().then(function(st){if(st==='granted'&&cmpOn)window.addEventListener('deviceorientation',onCompass);}).catch(function(){});
		}else if('ondeviceorientationabsolute' in window)window.addEventListener('deviceorientationabsolute',onCompass);
		else if(D)window.addEventListener('deviceorientation',onCompass);
	}catch(e){}
}
function compassStop(){cmpOn=false;HU.cmp=null;try{window.removeEventListener('deviceorientation',onCompass);window.removeEventListener('deviceorientationabsolute',onCompass);}catch(e){}}
/* velocity from a least-squares line through the fixes of the last win ms:
   {kt, crs} or null (fewer than 3 fixes, or under 2 s of them) */
function trackVel(tr,win){
	if(!tr||tr.length<3)return null;
	var tEnd=tr[tr.length-1].t,pts=tr.filter(function(p){return tEnd-p.t<=win;});
	if(pts.length<3||(tEnd-pts[0].t)<2000)return null;
	var r=pts[pts.length-1],ky=111320,kx=111320*Math.cos(r.lat*Math.PI/180),n=pts.length,st=0,sx=0,sy=0,stt=0,stx=0,sty=0;
	pts.forEach(function(p){var t=(p.t-tEnd)/1000,x=(p.lon-r.lon)*kx,y=(p.lat-r.lat)*ky;st+=t;sx+=x;sy+=y;stt+=t*t;stx+=t*x;sty+=t*y;});
	var d=n*stt-st*st;if(!(d>0))return null;
	var vx=(n*stx-st*sx)/d,vy=(n*sty-st*sy)/d;   /* m/s east, north */
	return {kt:Math.sqrt(vx*vx+vy*vy)*1.94384,crs:(Math.atan2(vx,vy)*180/Math.PI+360)%360};
}
function setMyLoc(lat,lon,acc,heading,speed){
	/* Prefer the device's own speed/heading; fall back to deltas from the previous
	   fix for phones that report null speed (many Androids; iOS when stationary).
	   Only count real movement — displacement must exceed the GPS accuracy, else
	   it's just jitter and we read 0 kt. */
	/* Course made good from the last ~30 s of fixes: the bearing from the oldest
	   fix that is clearly further away than the GPS error. One-second deltas
	   (the old method) are smaller than the error at sailing speeds, so the
	   course froze on a stale value and the boat's line pointed the wrong way. */
	var nowT=Date.now(),dSpd=null,dCrs=null,need=Math.max(acc||15,8)*1.5;
	myTrack.push({lat:lat,lon:lon,t:nowT});
	while(myTrack.length&&nowT-myTrack[0].t>30000)myTrack.shift();
	for(var k=0;k<myTrack.length-1;k++){var f=myTrack[k],dm=distNM(f.lat,f.lon,lat,lon)*1852,dt=(nowT-f.t)/3600000;
		if(dt>0&&dm>need){dSpd=(dm/1852)/dt;dCrs=brgTrue(f.lat,f.lon,lat,lon);break;}}
	/* ...but the course from the NEWEST fix that is still well clear of the GPS
	   error (2x), so after a tack the course (and the heads-up chart) follows
	   within seconds instead of averaging over the whole 30 s */
	for(var k2=myTrack.length-2;k2>=0;k2--){var f2=myTrack[k2];
		if(distNM(f2.lat,f2.lon,lat,lon)*1852>need*2){dCrs=brgTrue(f2.lat,f2.lon,lat,lon);break;}}
	if(dSpd==null&&myTrack.length>1&&nowT-myTrack[0].t>8000)dSpd=0;   /* 8 s+ without real movement: stopped */
	prevFix={lat:lat,lon:lon,t:nowT};
	myLoc={lat:lat,lon:lon};
	var devSpd=(speed!=null&&isFinite(speed)&&speed>=0)?speed*1.94384:null,
		devHd=(heading!=null&&isFinite(heading)&&heading>=0&&devSpd!=null&&devSpd>=0.5)?heading:null; /* the device's heading is its course over ground, valid only while moving */
	var tv=trackVel(myTrack,3000);
	if(tv&&tv.kt>=1)dCrs=tv.crs;   /* course from the same 3 s fit once moving: quicker after a tack */
	var hd=(devHd!=null)?devHd:dCrs;
	if(hd!=null)myHeading=hd; /* direction of travel; keep the last good one when stopped */
	/* SOG from the GPS positions themselves: a straight-line (least-squares) fit
	   of the last 3 s of fixes (Brad's choice: longer felt laggy). The phone's own speed reading measures how fast
	   the phone moves, so on a rocking boat it adds the swing of the mast/cockpit
	   and reads high; the swing goes back and forth, so over a few seconds of
	   positions it largely cancels out. Falls back to the device / track value
	   until there are 3 fixes over 2 s. */
	mySpeedKt=(tv!=null)?tv.kt:((devSpd!=null)?devSpd:dSpd); /* knots */
	try{if(hd!=null)detectTack(hd,mySpeedKt,lat,lon);}catch(e){}
	applyHdg();
	if(!locLayer)locLayer=L.layerGroup().addTo(map);
	locLayer.clearLayers();
	if(acc&&acc<4000)L.circle([lat,lon],{radius:acc,color:'#1a73e8',weight:1,opacity:.5,fillColor:'#1a73e8',fillOpacity:.1,interactive:false}).addTo(locLayer);
	var boat='<svg width="30" height="30" viewBox="-15 -15 30 30" style="overflow:visible"><path d="M0,-12.5 C5.5,-9 6.5,-1 6,6 L4,9.5 L-4,9.5 L-6,6 C-6.5,-1 -5.5,-9 0,-12.5 Z" fill="none" stroke="#15293b" stroke-width="2" stroke-linejoin="round"/><circle cx="0" cy="0" r="3.2" fill="#1a73e8" stroke="#fff" stroke-width="1.2"/></svg>';
	var prevMk=HU.mk?HU.mk.getLatLng():null;
	myMk=HU.mk=L.marker(prevMk&&headsUp?prevMk:[lat,lon],{icon:L.divIcon({className:'me-mk',html:boat,iconSize:[30,30],iconAnchor:[15,15]}),interactive:false,keyboard:false,zIndexOffset:950}).addTo(locLayer);
	HU.mkFrom=prevMk&&headsUp?[prevMk.lat,prevMk.lng]:null;HU.mkTo=[lat,lon];HU.mkT0=Date.now();
	drawVector(locLayer,lat,lon,mySpeedKt,myHeading,'#1a73e8');
	if(headsUp){
		/* keep you centred so the chart turns around you: the first fix jumps
		   there; after that a linear ~0.9 s pan per fix, so the chart glides */
		if(!locFirst){locFirst=true;try{map.setView([lat,lon],Math.max(map.getZoom(),13),{animate:false});}catch(e){}}
		else if(!huFree()){try{map.panTo([lat,lon],{animate:true,duration:0.9,easeLinearity:1,noMoveStart:true});}catch(e){}}
		huKick();
	}
	else if(!locFirst){locFirst=true;try{map.setView([lat,lon],Math.max(map.getZoom(),13),{animate:true});}catch(e){}}
	advanceNextWp();
	if(routeFromBoat&&pins.length){drawRouteLine();updateRouteBar();}
	drawLaylines();
	rerenderCallouts();updateBoatInfo();
	if(typeof FLEET!=='undefined'&&FLEET&&FLEET.share)postFix(false); /* chart plotter: share this fix with other boats */
	if(window.OYCWM&&OYCWM._fix)OYCWM._fix(); /* race tools (racers only) listen for each GPS fix */
}
/* ---------- chart plotter helpers ---------- */
/* point d nm from lat/lon on true bearing brg */
function destPt(lat,lon,brg,d){var R=3440.065,k=Math.PI/180,a=d/R,p1=lat*k,l1=lon*k,b=brg*k;
	var p2=Math.asin(Math.sin(p1)*Math.cos(a)+Math.cos(p1)*Math.sin(a)*Math.cos(b));
	var l2=l1+Math.atan2(Math.sin(b)*Math.sin(a)*Math.cos(p1),Math.cos(a)-Math.sin(p1)*Math.sin(p2));
	return [p2/k,((l2/k+540)%360)-180];}
function vecMin(){var el=document.getElementById('vecMin');return el?(+el.value||0):10;}
/* course vector: a line from the boat to where it will be in vecMin() minutes at
   its current SOG/COG, with a dot at the tip (only while it's moving) */
/* ...and a thin dashed course line carrying on ahead in the direction of
   travel, so the heading reads even when the boat is slow */
function drawVector(layer,lat,lon,sog,cog,col){
	if(sog==null||sog<0.5||cog==null)return;
	var vm=vecMin(),vd=vm?sog*vm/60:0,e=vd?destPt(lat,lon,cog,vd):[lat,lon];
	var ext=destPt(lat,lon,cog,Math.max(vd*2,1.5));
	L.polyline([e,ext],{color:col,weight:1.5,opacity:.6,dashArray:'4 5',interactive:false}).addTo(layer);
	if(!vd)return;
	L.polyline([[lat,lon],e],{color:col,weight:3,opacity:.9,interactive:false}).addTo(layer);
	L.circleMarker(e,{radius:3.5,color:'#fff',weight:1.5,fillColor:col,fillOpacity:1,interactive:false}).addTo(layer);
}
/* ---------- laylines ----------
   Through a mark, the two lines you could sail straight to it on: close-hauled
   on starboard (green) and port (red) for a mark upwind, or on each gybe for a
   mark downwind, using the angles from layAng() (Layline angles panel) and
   the forecast wind at the mark. Drawn for the waypoint you're sailing to (with
   My location on), otherwise for every upwind or downwind leg of the route and
   of a plotted race course. */
var layLayer=L.layerGroup(),racePts=[];
function layOn(){var t=document.getElementById('tgLay');return !!(t&&t.checked);}
function layWind(lat,lon,live){var w=live?modelWind(lat,lon,Date.now()):null;if(w)return {twd:w.twd,tws:w.tws};
	var r=null;try{r=readPoint(lat,lon,curTi);}catch(e){}return (r&&r.dir!=null)?{twd:r.dir,tws:r.kt}:null;}
/* geometry for a mark M approached from F: {up, stbd:{brg,heading}, port:{..}, twa} */
function layGeom(F,M,live){
	var w=layWind(M[0],M[1],live);if(!w)return null;
	var crs=brgTrue(F[0],F[1],M[0],M[1]),tw=twaFrom(w.twd,crs,w.tws),LA=layAng(w.tws),up=tw.twa<90,A=up?LA.up:LA.dn;
	var sH=up?w.twd-A:w.twd+180+A,pH=up?w.twd+A:w.twd+180-A;   /* close-hauled / gybing headings on each tack */
	function n(x){return ((x%360)+360)%360;}
	return {up:up,twa:tw.twa,twd:w.twd,stbd:{heading:n(sH),back:n(sH+180)},port:{heading:n(pH),back:n(pH+180)},
		lays:up?tw.twa>=A-2:tw.twa<=182-A,up_a:LA.up,dn_a:LA.dn,  /* can sail straight there without tacking/gybing */
		d:distNM(F[0],F[1],M[0],M[1])};
}
function drawLayFor(F,M,live,force){
	var g=layGeom(F,M,live);if(!g)return;
	if(!force&&g.lays)return;   /* planning view: only legs you can't sail straight */
	/* length in nm from the leg, but never shorter than ~120 px on screen, so
	   the lines stay readable when zoomed out (v1.7.412; redrawn on zoom) */
	var nmPx=156543.03*Math.cos(M[0]*Math.PI/180)/Math.pow(2,map.getZoom())/1852;
	var len=Math.max(Math.min(Math.max(g.d*1.15,0.4),6),120*nmPx);
	[['stbd','#1e8e3e'],['port','#c0392b']].forEach(function(sc){var o=g[sc[0]],end=destPt(M[0],M[1],o.back,len);
		L.polyline([M,end],{color:sc[1],weight:2,opacity:.85,dashArray:'8 6',interactive:false}).addTo(layLayer);
		L.marker(end,{icon:L.divIcon({className:'lay-lbl',html:'<b style="color:'+sc[1]+'">'+(sc[0]==='stbd'?'Stbd':'Port')+' '+pad3(o.heading+MAGVAR)+'°M</b>',iconSize:[0,0]}),interactive:false,keyboard:false,zIndexOffset:390}).addTo(layLayer);});
}
function drawLaylines(){
	layLayer.clearLayers();
	var rc=document.getElementById('tgRace'),race=!!(rc&&rc.checked&&typeof MARKS!=='undefined');
	if(!layOn()&&!race){if(map.hasLayer(layLayer))map.removeLayer(layLayer);return;}
	if(!map.hasLayer(layLayer))layLayer.addTo(map);
	/* Laylines on (v1.7.410): every waypoint shows its laylines, for the leg
	   that arrives at it (upwind or downwind). The waypoint you're sailing to
	   uses your live position + live wind; a first waypoint with no leg into it
	   gets its upwind laylines. */
	if(layOn()&&pins.length){
		var pts=routePts(),off=pts.length-pins.length,live=!!(myLoc&&pins[nextWp]);
		for(var i=0;i<pins.length;i++){var M=[pins[i].lat,pins[i].lon];
			if(live&&i===nextWp){drawLayFor([myLoc.lat,myLoc.lon],M,true,true);continue;}
			var F=pts[i+off-1];
			if(F)drawLayFor(F,M,false,true);
			else{var w0=layWind(M[0],M[1],false);if(w0)drawLayFor(destPt(M[0],M[1],(w0.twd+180)%360,1),M,false,true);}
		}
	}
	/* Race on: laylines at every race mark, from the forecast at the slider hour
	   (they move as you scrub). With a course plotted, each mark gets the
	   laylines for the leg that rounds it (upwind or downwind); without one,
	   every mark gets its upwind (windward-mark) laylines. */
	if(race){
		if(racePts.length>=2){var seen={};
			for(var j=1;j<racePts.length;j++){var g=layGeom(racePts[j-1],racePts[j],false);if(!g)continue;
				var key=racePts[j][0].toFixed(5)+','+racePts[j][1].toFixed(5)+(g.up?'u':'d');if(seen[key])continue;seen[key]=1;
				drawLayFor(racePts[j-1],racePts[j],false,true);}
		}else for(var k in MARKS){var m=MARKS[k],w=layWind(m.lat,m.lon,false);if(!w)continue;
			drawLayFor(destPt(m.lat,m.lon,(w.twd+180)%360,1),[m.lat,m.lon],false,true);}
	}
}
/* true wind angle on a leg a→b, from the forecast wind at the leg's midpoint at
   the slider's hour: {twa 0..180, side 'stbd'|'port'|'', pos point of sail} */
function pointOfSail(t,kt){var u=45;try{if(LAY)u=layAng(kt).up;}catch(e){}return t<u?'beat':t<60?'close-hauled':t<80?'close reach':t<110?'beam reach':t<150?'broad reach':t<165?'run':'dead run';}
/* the waypoint at [lat,lon], if any (its exact-point forecast backs up the grid) */
function pinAt(p){for(var k=0;k<pins.length;k++)if(pins[k].lat===p[0]&&pins[k].lon===p[1])return pins[k];return null;}
function legTWA(a,b,ti){
	if(ti==null)ti=curTi;
	/* the wind shown for the leg's end waypoint at the slider hour (its row and
	   card), so the TWA matches the numbers on screen; else the map's forecast
	   at the leg midpoint */
	var mid=[(a[0]+b[0])/2,(a[1]+b[1])/2],r=null,pb=pinAt(b);
	try{if(pb)r=pinReading(pb.lat,pb.lon,ti,pb.fc);}catch(e){r=null;}
	if(!r||r.dir==null){try{r=readPoint(mid[0],mid[1],ti);}catch(e){r=null;}}
	if(!r||r.dir==null)return null;
	return twaFrom(r.dir,brgTrue(a[0],a[1],b[0],b[1]),r.kt);
}
function twaFrom(twd,crs,kt){var rel=((twd-crs+540)%360)-180,t=Math.abs(rel);
	return {twa:t,side:(t<2||t>178)?'':(rel>=0?'stbd':'port'),pos:pointOfSail(t,kt),kt:kt};}
function twaText(o){return o?('TWA '+Math.round(o.twa)+'°'+(o.side?' '+o.side:'')+' &middot; '+o.pos):'';}
function locFail(msg){
	stopLoc();var t=document.getElementById('tgLoc');if(t)t.checked=false;
	alert(msg);
}
/* "My boat" disclaimer (v1.7.405): before the first GPS fix — via My location,
   From my boat, the Heads-up compass or Share my position — the visitor must tick
   and accept that the chart plotter is for information only. Accepting is kept
   in this browser (oyc_nav_disc); Cancel turns My location back off. */
var DISC_KEY='oyc_nav_disc_v2',discOpen=false;
/* an acceptance counts for 120 days (v1.7.411); the browser keeps its time in ms */
var DISC_DAYS=120;
function discOk(){try{var t=parseInt(localStorage.getItem(DISC_KEY)||'0',10);return t>0&&Date.now()-t<DISC_DAYS*864e5;}catch(e){return false;}}
function discSet(ms){try{localStorage.setItem(DISC_KEY,String(ms||Date.now()));}catch(e){}}
/* signed-in members: acceptance is recorded on their profile (inc/nav-disclaimer.php)
   with a date-time stamp, and one already on file counts in any browser */
var DISC={logged:false,nonce:'',accepted:null};
fetch(AJAX+'?action=oyc_nav_disc_me',{credentials:'same-origin',cache:'no-store'}).then(function(r){return r.json();}).then(function(d){
	if(!d)return;DISC=d;
	/* the member's profile is the record: within 120 days → no box; otherwise ask again */
	if(d.accepted){discSet(Date.parse(String(d.accepted).replace(' ','T')+'Z')||Date.now());}
	else if(d.logged){try{localStorage.removeItem(DISC_KEY);}catch(e){}}
}).catch(function(){});
function discRecord(){
	if(!DISC.logged||!DISC.nonce)return;
	var fd=new FormData();fd.append('action','oyc_nav_disc_ok');fd.append('nonce',DISC.nonce);
	fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){if(d&&d.success)DISC.accepted=d.data.t;}).catch(function(){});
}
function showNavDisclaimer(onAccept,onCancel){
	if(discOpen)return;discOpen=true;
	var w=document.createElement('div');w.className='oyc-disc';w.setAttribute('role','dialog');w.setAttribute('aria-modal','true');w.setAttribute('aria-labelledby','oycDiscH');
	w.innerHTML='<div class="dbox"><h3 id="oycDiscH">Not for navigation</h3>'
		+'<ul><li>This chart plotter is for <b>information purposes only</b> and is not meant for navigation.</li>'
		+'<li>Use official <b>NOAA nautical charts</b> for navigation.</li>'
		+'<li>This tool does <b>not replace good seamanship</b>. You are responsible for the safe navigation of your boat.</li>'
		+'<li>By using this tool you agree to hold <b>Orienta Yacht Club (OYC)</b> and <b>Intualytics LLC</b> harmless and blameless for any damage that may arise from its use.</li></ul>'
		+'<label><input type="checkbox" id="oycDiscCb"> I understand and agree</label>'
		+'<div class="dbtns"><button type="button" class="no" id="oycDiscNo">Cancel</button><button type="button" class="ok" id="oycDiscOk" disabled>Continue</button></div></div>';
	(document.fullscreenElement||document.webkitFullscreenElement||document.body).appendChild(w);
	var cb=w.querySelector('#oycDiscCb'),ok=w.querySelector('#oycDiscOk'),no=w.querySelector('#oycDiscNo');
	function close(){discOpen=false;if(w.parentNode)w.parentNode.removeChild(w);}
	cb.addEventListener('change',function(){ok.disabled=!cb.checked;});
	ok.addEventListener('click',function(){if(!cb.checked)return;discSet();discRecord();close();onAccept();});
	no.addEventListener('click',function(){close();onCancel();});
	setTimeout(function(){try{cb.focus();}catch(e){}},0);
}
var LOGGED=<?php echo is_user_logged_in() ? 'true' : 'false'; ?>;
function startLoc(){
	if(!LOGGED){ /* controls are hidden for visitors; this catches any other path */
		var t=document.getElementById('tgLoc');if(t)t.checked=false;if(headsUp)setHeadsUp(false);if(routeFromBoat)setFromBoat(false);
		alert('My boat (your live GPS position) is for signed-in members. Please log in to use it.');
		return;
	}
	if(!discOk()){
		showNavDisclaimer(startLocNow,function(){stopLoc();var t=document.getElementById('tgLoc');if(t)t.checked=false;});
		return;
	}
	startLocNow();
}
function startLocNow(){
	locFirst=false;
	if(!navigator.geolocation){locFail('Location is not available in this browser.');return;}
	locWatch=navigator.geolocation.watchPosition(
		function(p){setMyLoc(p.coords.latitude,p.coords.longitude,p.coords.accuracy,p.coords.heading,p.coords.speed);},
		function(err){
			var m=(err&&err.code===1)
				? 'Location is blocked for this site.\n\nOn iPhone: Settings → Privacy & Security → Location Services → On, then scroll to Safari Websites → “While Using”. Or tap “aA” in Safari’s address bar → Website Settings → Location → Allow. Then tap My location again.'
				: 'Could not get your location — make sure Location Services are on and you have a signal, then try again.';
			locFail(m);
		},
		{enableHighAccuracy:true,maximumAge:5000,timeout:20000}
	);
}
function stopLoc(){
	if(locWatch!=null&&navigator.geolocation)navigator.geolocation.clearWatch(locWatch);
	locWatch=null;myLoc=null;myHeading=null;mySpeedKt=null;prevFix=null;myTrack=[];HU.mk=myMk=null;HU.mkTo=null;if(headsUp)setHeadsUp(false);applyHdg();if(locLayer)locLayer.clearLayers();
	if(typeof FLEET!=='undefined'&&FLEET&&FLEET.share)stopShare();
	if(routeFromBoat)setFromBoat(false);
	rerenderCallouts();updateBoatInfo();
}

/* the compass on the chart: North-up ↔ Heads-up (needs GPS, so it turns on My
   location if it isn't already). Asking for the phone compass happens here,
   inside the tap, because iPhones only allow it from a tap. */
(function(){var f=document.getElementById('navFollow');if(f)f.addEventListener('click',function(){setHuFree(false);});})();
(function(){var b=document.getElementById('navRose');if(!b)return;
	b.addEventListener('click',function(){
		if(!headsUp){var t=document.getElementById('tgLoc');if(!locWatch){if(t)t.checked=true;startLoc();}setHeadsUp(true);}
		else setHeadsUp(false);
		b.setAttribute('aria-pressed',headsUp?'true':'false');
	});
})();

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
	g.SP=[];g.DR=[];g.PR=[];g.GU=[];g.TP=[];g.PP=[];g.WV=[];g.WP=[];g.FRAMES=[];
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
	/* a corner from a basin chunk that hasn't loaded (or failed) is null: no reading, not 0 kt */
	if(at(g.SP,r0,c0)==null||at(g.SP,r0,c1)==null||at(g.SP,r1,c0)==null||at(g.SP,r1,c1)==null)return null;
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
	var wv=null,wp=null;
	if(g.WV&&g.WV.length){var ws=[at(g.WV,r0,c0,wti),at(g.WV,r0,c1,wti),at(g.WV,r1,c0,wti),at(g.WV,r1,c1,wti)].filter(function(x){return x!=null;});
		if(ws.length)wv=ws.reduce(function(a,b){return a+b;},0)/ws.length;}
	if(g.WP&&g.WP.length){var ps=[at(g.WP,r0,c0,wti),at(g.WP,r0,c1,wti),at(g.WP,r1,c0,wti),at(g.WP,r1,c1,wti)].filter(function(x){return x!=null;});
		if(ps.length)wp=ps.reduce(function(a,b){return a+b;},0)/ps.length;}
	return {kt:sp*KT,dir:dir,mb:mb==null?null:mb,gust:gu==null?null:gu*KT,tempF:tp,precip:pp,waveFt:wv==null?null:wv*3.28084,wavePer:wp};
}
function sampleBest(lat,lon,ti){
	if(lat<=LOCAL.LA1&&lat>=LOCAL.LA2&&lon>=LOCAL.LO1&&lon<=LOCAL.LO2){var s=sampleGrid(LOCAL,lat,lon,ti);if(s)return s;}
	return sampleGrid(BASIN,lat,lon,ti);
}
var CARD=['N','NNE','NE','ENE','E','ESE','SE','SSE','S','SSW','SW','WSW','W','WNW','NW','NNW'];
function card(d){return CARD[Math.round(d/22.5)%16];}
function fmt(iso){var d=new Date(iso);return {big:(d.getHours()%12||12)+' '+(d.getHours()>=12?'PM':'AM'),small:d.toLocaleDateString('en-US',{weekday:'short',month:'short',day:'numeric'})};}

/* ---------- map ---------- */
/* Opening view for everyone (Brad, 2026-11-14): Mamaroneck Harbor top-left, Milton
   Harbor channel at the top, Buoy 42 bottom-centre. Fitted to the screen once the map
   has a real size (it may start in a hidden tab), so phones and desktops show the same
   water. A saved My chart view (applied later) or My location still win. */
var HOME_VIEW=L.latLngBounds([40.922,-73.742],[40.948,-73.682]);
var map=L.map('map',{worldCopyJump:true}).setView(HOME_VIEW.getCenter(),14);
(function(){function fit(){try{map.fitBounds(HOME_VIEW,{animate:false});}catch(e){}}
	if(map.getContainer().clientWidth)fit();else map.once('resize',fit);})();
/* heads-up: un-rotate map taps (mapPointFromEvent, with My location) */
(function(){var orig=map.mouseEventToContainerPoint;
	map.mouseEventToContainerPoint=function(e){return mwEl().classList.contains('headsup')?mapPointFromEvent(e):orig.call(map,e);};})();
/* Mouse, the same in north-up and heads-up:
   - LEFT button only clicks: pick things on the chart (markers, cards, buttons)
     and drop waypoints. A left drag on the open map does nothing — Leaflet's own
     drag is stopped for the mouse here (touch drag on phones/tablets is untouched),
     and markers (drag a waypoint to move it), cards and map buttons keep theirs.
     Shift + left drag still box-zooms in north-up.
   - RIGHT button pans: hold it anywhere on the map and drag. The browser's
     right-click menu is kept off the map so it doesn't pop up over the drag
     (macOS opens it on press, not release).
   In heads-up each mouse move is un-rotated (as mapPointFromEvent) so the chart
   follows the hand, and panning turns off follow-the-boat until "Follow boat". */
(function(){var el=map.getContainer(),rp=null;
	function onMapSurface(t){return el.contains(t)&&!(t.closest&&t.closest('.leaflet-control,.leaflet-popup,.leaflet-marker-icon'));}
	var lp=null,eat=false;
	mwEl().addEventListener('mousedown',function(e){
		if(e.button!==0||e.shiftKey&&!headsUp||!onMapSurface(e.target))return;
		e.stopPropagation();e.preventDefault(); /* no left-drag pan (click still fires) and no text/tile drag */
		lp={x:e.clientX,y:e.clientY};
	},true);
	/* a left press that wandered off (a drag) is not a click: don't drop a waypoint for it */
	document.addEventListener('mouseup',function(e){if(e.button===0&&lp){eat=Math.abs(e.clientX-lp.x)+Math.abs(e.clientY-lp.y)>6;lp=null;}},true);
	mwEl().addEventListener('click',function(e){if(eat){eat=false;if(el.contains(e.target)){e.stopPropagation();e.preventDefault();}}},true);
	function mv(e){if(!rp)return;if(!(e.buttons&2)){up();return;}
		var dx=e.clientX-rp.x,dy=e.clientY-rp.y;
		if(!rp.on){if(Math.abs(e.clientX-rp.x0)+Math.abs(e.clientY-rp.y0)<4)return;
			rp.on=true;el.classList.add('rpan');map.stop();map.fire('movestart');
			if(headsUp)setHuFree(true);}
		rp.x=e.clientX;rp.y=e.clientY;
		if(!dx&&!dy)return;
		if(mwEl().classList.contains('headsup')){var a=HU.disp*Math.PI/180,sc=HU.scale||1,ux=(dx*Math.cos(a)-dy*Math.sin(a))/sc,uy=(dx*Math.sin(a)+dy*Math.cos(a))/sc;dx=ux;dy=uy;}
		if(map._rawPanBy){map._rawPanBy(L.point(-dx,-dy));map.fire('move');} /* like Leaflet's drag: move now, moveend once at the end */
		else map.panBy([-dx,-dy],{animate:false});}
	function up(){if(!rp)return;var was=rp.on;rp=null;el.classList.remove('rpan');
		document.removeEventListener('mousemove',mv,true);document.removeEventListener('mouseup',up,true);
		if(was)map.fire('moveend');}
	el.addEventListener('mousedown',function(e){
		if(e.button!==2||!onMapSurface(e.target))return;
		e.preventDefault();
		rp={x:e.clientX,y:e.clientY,x0:e.clientX,y0:e.clientY,on:false};el.classList.add('rpan'); /* hand as soon as the right button is down */
		document.addEventListener('mousemove',mv,true);document.addEventListener('mouseup',up,true);
	});
	el.addEventListener('contextmenu',function(e){e.preventDefault();});
})();
/* Light base (OSM). The wind-speed fill is kept translucent so it reads as a
   tint over the map, with dark barbs and dark flow lines legible on top. */
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OpenStreetMap',maxZoom:19}).addTo(map);
/* NOAA nautical chart over the street map. NOAA retired its raster (paper-style)
   chart tiles in 2025; this is its Chart Display Service, which draws the official
   electronic charts (ENC) with standard chart symbols. Only the water-side layers
   are requested, on a transparent background, so land stays the OSM street map:
   2 depths & soundings, 3 rocks/wrecks/obstructions, 4 traffic routes, 5 special
   areas (cable, anchorage), 6 buoys/beacons/lights, 7 small-craft facilities.
   The service picks detail by map scale and shows little at the board's default
   zoom 12, so tiles at zoom <= ENC_HIRES_MAX (12) are requested at 2x pixels
   (more detail, but text and symbols draw half-size). From zoom 13 in, 1x, so
   soundings and labels are full size and readable. Shown from zoom 9 in.
   Only water shallower than 5 ft is shaded light blue; deeper water is white
   with its soundings (variant c5 / cd5, see OYC_ENC_CHART_FT; NOAA's default
   would shade everything under ~30 m, most of the Sound). The "NOAA chart"
   chip toggles it (wired with the POI chips below). Not for navigation. */
/* Tiles come from our own server's cache first (inc/enc-cache.php): the static
   PNG in uploads/oyc-enc, else admin-ajax oyc_enc_tile (fetches from NOAA once
   and saves it), else NOAA directly. noaaUrl() is the direct WMS request. */
var ENC_CACHE=<?php echo wp_json_encode( esc_url_raw( function_exists( 'oyc_enc_url' ) ? oyc_enc_url() : '' ) ); ?>,ENC_AJAX=AJAX;
/* Zoomed out (10-11) NOAA draws Long Island Sound as one flat light-blue area,
   indistinguishable from the street map. encDetail asks for the approach-scale
   charts only (IntendedUsage "4": white deep water, blue shallows, soundings)
   and sits over the normal chart; offshore, where those charts don't exist, it
   is empty and the normal chart shows through. See OYC_ENC_DETAIL. */
var ENC_DETAIL=<?php echo wp_json_encode( defined( 'OYC_ENC_DETAIL' ) ? OYC_ENC_DETAIL : '' ); ?>;
/* chart shading contour variants (5 ft): same display_params the server uses */
var ENC_CV=<?php echo wp_json_encode( defined( 'OYC_ENC_CHART_FT' ) ? 'c' . OYC_ENC_CHART_FT : '' ); ?>,ENC_CP=<?php echo wp_json_encode( defined( 'OYC_ENC_CHART_FT' ) ? oyc_enc_safe_params( 'c' . OYC_ENC_CHART_FT ) : '' ); ?>,ENC_CDP=<?php echo wp_json_encode( defined( 'OYC_ENC_CHART_FT' ) ? oyc_enc_safe_params( 'cd' . OYC_ENC_CHART_FT ) : '' ); ?>,ENC_CHP=<?php echo wp_json_encode( defined( 'OYC_ENC_CHART_FT' ) ? oyc_enc_safe_params( 'ch' . OYC_ENC_CHART_FT ) : '' ); ?>,ENC_CXP=<?php echo wp_json_encode( defined( 'OYC_ENC_CHART_FT' ) ? oyc_enc_safe_params( 'cx' . OYC_ENC_CHART_FT ) : '' ); ?>;
/* cache version: changes whenever the cached tiles are thrown away, so browsers
   don't keep showing tiles from an older chart style (they cache them a week) */
var ENC_VER=<?php echo (int) ( defined( 'OYC_ENC_VER' ) ? OYC_ENC_VER : 1 ); ?>;
var ENC_HIRES_MAX=12;/* must match OYC_ENC_HIRES_MAX in inc/enc-cache.php */
var ENC_WMS='https://gis.charttools.noaa.gov/arcgis/rest/services/MCS/NOAAChartDisplay/MapServer/exts/MaritimeChartService/WMSServer';
var EncWMS=L.TileLayer.WMS.extend({
	options:{encVar:''},
	noaaUrl:function(c){var f=(c.z<=ENC_HIRES_MAX)?2:1,ts=this.getTileSize();this.wmsParams.width=ts.x*f;this.wmsParams.height=ts.y*f;return L.TileLayer.WMS.prototype.getTileUrl.call(this,c);},
	ajaxUrl:function(c){return ENC_AJAX+'?action=oyc_enc_tile&z='+c.z+'&x='+c.x+'&y='+c.y+(this.options.encVar?'&v='+this.options.encVar:'')+'&cv='+ENC_VER;},
	getTileUrl:function(c){return ENC_CACHE?(ENC_CACHE+(this.options.encVar?'/'+this.options.encVar:'')+'/'+c.z+'/'+c.x+'/'+c.y+'.png?cv='+ENC_VER):this.noaaUrl(c);}});
function encOpts(o){return L.extend({layers:'2,3,4,5,6,7',format:'image/png',transparent:true,version:'1.3.0',keepBuffer:4},o);}
var encBase=new EncWMS(ENC_WMS,encOpts(L.extend({minZoom:9,maxZoom:19,zIndex:2,attribution:'NOAA ENC chart &mdash; not for navigation'},ENC_CV?{encVar:ENC_CV,display_params:ENC_CP}:{})));
var encLayer=L.layerGroup([encBase]);
if(ENC_DETAIL)encLayer.addLayer(new EncWMS(ENC_WMS,encOpts({minZoom:10,maxZoom:11,zIndex:3,encVar:ENC_CV?'cd'+ENC_CV.slice(1):'d',display_params:ENC_CV?ENC_CDP:ENC_DETAIL})));
/* harbour-scale charts on top at zoom 10-12: approach charts (e.g. Fishers
   Island Sound) shade all their water blue whatever the contour */
if(ENC_CV)encLayer.addLayer(new EncWMS(ENC_WMS,encOpts({minZoom:10,maxZoom:12,zIndex:4,encVar:'ch'+ENC_CV.slice(1),display_params:ENC_CHP})));
/* zoom 13 in: the same harbour charts with every sounding drawn (NOAA otherwise
   hides them at these scales, and approach charts there are flat blue with
   none), see cx in OYC_ENC_CHART_FT. Not pre-loaded: tiles come from NOAA and
   our server caches them in the background. */
if(ENC_CV&&ENC_CXP)encLayer.addLayer(new EncWMS(ENC_WMS,encOpts({minZoom:13,maxZoom:19,zIndex:4,encVar:'cx'+ENC_CV.slice(1),display_params:ENC_CXP})));
/* The chart takes priority over the street map: NOAA's chart server often drops
   or times out a tile (worst right after a zoom, when it gets a burst of
   requests), and each lost tile left a square of the street map's blue water
   showing through. Retry a failed tile up to 5 times with growing back-off,
   and keep the previous zoom's chart tiles on screen until the new level's
   have actually loaded, so a zoom never uncovers the street map. */
/* Server load: a tile missing from the cache used to go straight to admin-ajax,
   so one map view of a new chart style sent dozens of PHP requests at once, each
   waiting on NOAA, and filled every PHP worker (site down 2026-11-15). Now a
   missing tile is shown from NOAA directly, and our server is asked to cache it
   in the background, at most ENC_WARM_MAX requests at a time per visitor. */
var encWarmQ=[],encWarmN=0,ENC_WARM_MAX=2;
function encWarm(u){if(encWarmQ.length>=60)encWarmQ.shift();encWarmQ.push(u);encWarmPump();}
function encWarmPump(){while(encWarmN<ENC_WARM_MAX&&encWarmQ.length){var u=encWarmQ.pop(),im=new Image();encWarmN++;
	im.onload=im.onerror=function(){encWarmN--;setTimeout(encWarmPump,250);};im.src=u;}}
encLayer.eachLayer(function(lyr){
	lyr.on('tileerror',function(e){
		var t=e.tile,n=+(t.getAttribute('data-try')||0);if(n>=5){t.removeAttribute('data-try');if(lyr._map)lyr._pruneTiles();return;}
		t.setAttribute('data-try',n+1);
		/* not cached yet → show it from NOAA now and have our server cache it in
		   the background (throttled); retries back off and stay on NOAA */
		var c=e.coords,url=lyr.noaaUrl(c);
		if(ENC_CACHE&&n===0)encWarm(lyr.ajaxUrl(c));
		setTimeout(function(){if(!lyr._map||!t.parentNode)return;
			t.src=url+'&_retry='+(n+1);},n===0?0:700*Math.pow(2,n));
	});
	lyr.on('tileload',function(e){e.tile.removeAttribute('data-try');});
	var prune=lyr._pruneTiles;
	/* Leaflet drops the old zoom level as soon as every new tile has *finished*,
	   including ones that failed; hold the old level while any new tile is
	   still retrying (data-try set), then prune as normal. */
	lyr._pruneTiles=function(){
		for(var k in this._tiles){var tl=this._tiles[k];if(tl.current&&tl.el&&tl.el.getAttribute('data-try'))return;}
		return prune.apply(this,arguments);};
	lyr.on('tileload',function(){if(lyr._map)lyr._pruneTiles();});
});
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
/* BLEND = hourly mean of GFS/ECMWF/ICON/GEM (direction vector-averaged), the
   same consensus the Marine Forecast board's BLEND button shows. Open-Meteo's
   best_match is just GFS over the US, so it is only a fallback now. */
var FC_BLEND=['gfs_seamless','ecmwf_ifs025','icon_seamless','gem_seamless'];
function fcBlend(h,base){
	var arrs=FC_BLEND.map(function(m){return h[base+'_'+m];}).filter(Boolean);
	if(!arrs.length)return null;
	var n=h.time.length,out=new Array(n),isDir=base==='wind_direction_10m';
	for(var i=0;i<n;i++){var a=0,b=0,c=0;arrs.forEach(function(r){var v=r[i];if(v!=null&&!isNaN(v)){if(isDir){var q=v*Math.PI/180;a+=Math.sin(q);b+=Math.cos(q);}else a+=v;c++;}});
		out[i]=!c?null:(isDir?(Math.atan2(a,b)*180/Math.PI+360)%360:a/c);}
	return out;
}
function fcKey(h,base){var k=base+'_best_match';return h[k]!==undefined?k:base;}
function fcCol(h,base){return fcBlend(h,base)||h[fcKey(h,base)];}
function fcSeriesFrom(res){
	if(!res||!res.ok||!res.wx||!res.wx.hourly)return null;
	var wx=res.wx,h=wx.hourly,off=(wx.utc_offset_seconds||0)*1000;
	var t=h.time.map(function(s){return Date.parse(s+':00Z')-off;});
	var wave={},wper={};
	if(res.marine&&res.marine.hourly&&res.marine.hourly.wave_height){var mh=res.marine.hourly;mh.time.forEach(function(tt,i){wave[tt]=mh.wave_height[i];if(mh.wave_period)wper[tt]=mh.wave_period[i];});}
	return {t:t, kt:fcCol(h,'wind_speed_10m'), gust:fcCol(h,'wind_gusts_10m'), dir:fcCol(h,'wind_direction_10m'),
		mb:fcCol(h,'pressure_msl'), tempF:fcCol(h,'temperature_2m'), pop:fcCol(h,'precipitation_probability'),
		waveFt:h.time.map(function(s){return wave[s]!=null?wave[s]:null;}),
		wavePer:h.time.map(function(s){return wper[s]!=null?wper[s]:null;})};
}
/* Browser-direct wave fetch — Open-Meteo rate-limits (HTTP 429) the SERVER's
   marine calls when the host IP is throttled, so the cached proxy comes back
   with marine:null and waves vanish from the pins/readout. The visitor's IP is
   not throttled, so pull the waves straight from the browser and merge them in.
   Shape matches the server's marine payload, so fcSeriesFrom reads it unchanged. */
function fetchMarineDirect(lat,lon){
	var u='https://marine-api.open-meteo.com/v1/marine?latitude='+(+lat).toFixed(3)+'&longitude='+(+lon).toFixed(3)
		+'&hourly=wave_height,wave_period&length_unit=imperial&forecast_days=7&timezone=America%2FNew_York';
	return fetch(u).then(function(r){if(!r.ok)throw new Error('mar '+r.status);return r.json();});
}
/* Resolve to a {wx,marine} response with waves present: if the server gave us
   the wind forecast but no marine (429), fill the marine in from the browser. */
function withMarine(res,lat,lon){
	if(res&&res.wx&&res.wx.hourly&&(!res.marine||!res.marine.hourly)){
		return fetchMarineDirect(lat,lon).then(function(mar){res.marine=mar;return res;},function(){return res;});
	}
	return Promise.resolve(res);
}
function fcAt(S,i){return {kt:S.kt[i],gust:S.gust[i],dir:S.dir[i],mb:S.mb[i],tempF:S.tempF[i],pop:S.pop?S.pop[i]:null,waveFt:S.waveFt[i],wavePer:S.wavePer?S.wavePer[i]:null};}
function fcInterp(S,ep){if(!S||!S.t.length)return null;var T=S.t,n=T.length;
	if(ep<=T[0])return fcAt(S,0);
	if(ep>=T[n-1])return fcAt(S,n-1);
	for(var i=1;i<n;i++){if(ep<=T[i]){var fr=(ep-T[i-1])/(T[i]-T[i-1]),a=fcAt(S,i-1),b=fcAt(S,i);
		var li=function(x,y){return (x!=null&&y!=null)?x+(y-x)*fr:x;};
		return {kt:li(a.kt,b.kt),gust:li(a.gust,b.gust),dir:a.dir,mb:li(a.mb,b.mb),tempF:li(a.tempF,b.tempF),pop:li(a.pop,b.pop),waveFt:li(a.waveFt,b.waveFt),wavePer:a.wavePer};}}
	return fcAt(S,n-1);
}
function fcNearest(S,ep){if(!S||!S.t.length)return null;var T=S.t,best=0,bd=1e15;for(var i=0;i<T.length;i++){var dd=Math.abs(T[i]-ep);if(dd<bd){bd=dd;best=i;}}return fcAt(S,best);}
/* sample a series at a slider frame: snap to the nearest actual hour at the live
   "now" frame (matches the board exactly), interpolate otherwise */
function fcForFrame(S,epoch,isNow){return isNow?fcNearest(S,Date.now()):fcInterp(S,epoch);}

var ERFC=null;
function loadERFC(){
	statFetch('marine_fc','oyc_marine_fc',25,12000).then(function(r){return r.json();}).then(function(res){
		return withMarine(res,ER.lat,ER.lon); /* fill waves from the browser if the server was 429'd */
	}).then(function(res){
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
	return {kt:a.kt+(b.kt-a.kt)*fr,dir:a.dir,mb:li(a.mb,b.mb),gust:li(a.gust,b.gust),tempF:li(a.tempF,b.tempF),precip:li(a.precip,b.precip),waveFt:li(a.waveFt,b.waveFt),wavePer:a.wavePer};
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
var pins=[],pinSeq=0;
/* Chart plotter: a route starts at its first point (Pt 1), anywhere on the chart,
   or, with "From my boat" on, at your live GPS position. nextWp is the point
   you're sailing to (it moves on as you come within 0.05 nm of each one). */
var routeFromBoat=false,nextWp=0;
function routeOrigin(){return (routeFromBoat&&myLoc)?{lat:myLoc.lat,lon:myLoc.lon,name:'My boat'}:null;}
function routePts(){var o=routeOrigin(),pts=o?[[o.lat,o.lon]]:[];for(var k=0;k<pins.length;k++)pts.push([pins[k].lat,pins[k].lon]);return pts;}
function advanceNextWp(){
	if(!myLoc||!pins.length){nextWp=0;return;}
	if(nextWp>=pins.length)nextWp=pins.length-1;
	while(nextWp<pins.length-1&&distNM(myLoc.lat,myLoc.lon,pins[nextWp].lat,pins[nextWp].lon)<0.05)nextWp++;
}
function setFromBoat(on){
	routeFromBoat=on;var b=document.getElementById('rbFromBoat');
	if(b){b.setAttribute('aria-pressed',on?'true':'false');b.textContent=on?'From my boat ✓':'From my boat';}
	if(on){nextWp=0;var t=document.getElementById('tgLoc');if(!locWatch){if(t)t.checked=true;startLoc();}}
	refreshRoute();
}
function pinReading(lat,lon,ti,fc){
	if(fc&&TIMES[ti]!=null){var s=fcForFrame(fc,new Date(TIMES[ti]).getTime(),ti===NOWI);if(s)return s;}
	return readPoint(lat,lon,ti);
}
/* ── Passage route: ordered, numbered waypoints ──────────────────────────
   WP1's leg is measured from Buoy 42; every later waypoint's leg is measured
   from the previous waypoint. Deleting a waypoint re-numbers the rest and
   recomputes the legs + route line. */
var routeLayer=L.layerGroup().addTo(map),legHitLayer=L.layerGroup().addTo(map);   /* legHitLayer: see drawLegHits */
function wpIcon(n){return L.divIcon({className:'wp-mk',html:'<div class="wp-ic">'+n+'</div>',iconSize:[24,24],iconAnchor:[12,12],popupAnchor:[0,-22]});}
/* First point is the Start (leg measured from Buoy 42); later points are
   Waypoint 1..n, each leg measured from the previous point. */
function wpBadge(i){return ''+(i+1);}
function legInfo(pin){
	var i=pins.indexOf(pin);
	if(i<=0){var o=routeOrigin();return o?{label:'Pt 1',dist:distNM(o.lat,o.lon,pin.lat,pin.lon),from:o.name}:{label:'Pt 1',dist:0,from:null,start:true};}
	return {label:'Pt '+(i+1),dist:distNM(pins[i-1].lat,pins[i-1].lon,pin.lat,pin.lon),from:'Pt '+i};
}
/* cumulative route length from the Start up to a given point (sum of its legs) */
function cumFromStart(pin){var i=pins.indexOf(pin),t=0,o=routeOrigin();if(o&&i>=0&&pins.length)t+=distNM(o.lat,o.lon,pins[0].lat,pins[0].lon);for(var j=1;j<=i;j++)t+=distNM(pins[j-1].lat,pins[j-1].lon,pins[j].lat,pins[j].lon);return t;}
/* full route length Start → last waypoint */
function routeTotalFromWP1(){return pins.length?cumFromStart(pins[pins.length-1]):0;}
function pinContent(pin,ti){
	var lat=pin.lat,lon=pin.lon,s=pinReading(lat,lon,ti,pin.fc);
	var ll=lat.toFixed(4)+', '+lon.toFixed(4),lg=legInfo(pin),i=pins.indexOf(pin);
	/* Start → distance from Buoy 42. Every waypoint shows its cumulative distance
	   "from start"; waypoints past the first also show their own leg from the
	   previous point (for WP1 the leg IS the distance from start, so show it once). */
	/* Per-leg distances now live as labels on the route line (drawLegLabels); the
	   card carries only the cumulative distance from the Start waypoint. The Start
	   itself shows none (its distance from Buoy 42 is the first leg label). */
	/* 3-line card: (1) label + cumulative total, (2) coords, (3) one weather line.
	   Per-leg distances live on the route line (drawLegLabels). */
	/* No "Pt N" label — the numbered marker already identifies the point. Start
	   shows only coords + weather; later points add a "Total X nm" line. */
	/* leg course (magnetic bearing) + distance from the previous point — Pt 1's leg
	   is measured from Buoy 42 */
	var prev=(i===0)?routeOrigin():pins[i-1];
	var tw=prev?legTWA([prev.lat,prev.lon],[lat,lon]):null;
	var legLine=prev
		?('<div class="co-leg">Leg '+magBrg(prev.lat,prev.lon,lat,lon)+'&deg;M &middot; '+lg.dist.toFixed(1)+' nm'+(tw?'<br>'+twaText(tw):'')+'</div>')
		:'<div class="co-leg">Start</div>';
	/* live bearing + distance from the viewer's GPS, when "My location" is on */
	var youLine = myLoc ? '<div class="co-you">From you &middot; '+magBrg(myLoc.lat,myLoc.lon,lat,lon)+'&deg;M &middot; '+distNM(myLoc.lat,myLoc.lon,lat,lon).toFixed(1)+' nm</div>' : '';
	var pe=passEtaFor(pin),pl=passLegFor(pin);   /* Passage brief ETA + the leg into here, while its run is current */
	if(pl){var mt=passMotorTag(pl),cft=pl.cf.easy+pl.cf.lively+pl.cf.rough;
		legLine='<div class="co-leg">Leg '+pad3(pl.brgM)+'&deg;M &middot; '+pl.nm.toFixed(1)+' nm</div><div class="co-pass">'
			+(pl.tws==null?'Wind beyond the forecast':pEsc(pLegWind(pl))+' &middot; TWA '+Math.round(pl.twa)+'&deg;')
			+'<br>'+pEsc(pl.pos)+' &middot; <b>'+pl.bsp.toFixed(1)+' kt</b>'+(mt?'<span class="pc-tag">'+mt+'</span>':'')
			+(pl.seaMax!=null?' &middot; seas to '+Math.round(pl.seaMax)+' ft':'')
			+(cft>0?'<br>'+pCfTxt(pl.cf)+(pl.upH>=2?' &middot; '+Math.round(pl.upH)+' h to windward':''):'')+'</div>'
			+(pl.nm<0.5?'<div class="co-pass warn">Almost on top of the previous point: you can delete one.</div>':'');}
	var hd='<div class="co-x" data-wp="'+pin.id+'" role="button" tabindex="0" title="Remove point" aria-label="Remove point">✕</div>'
		+(pin.name?'<div class="co-nm">'+pEsc(pin.name)+'</div>':'')
		+legLine
		+swNote(pin,'co-sw')
		+depNote(pin,'co-sw co-dep')
		+(pe?'<div class="co-eta">'+(i===0&&!routeOrigin()?'Depart ':'Passage ETA ')+pEsc(pWhen(pe))+'</div>':'')
		+(i===0?'':'<div class="co-t">Total '+cumFromStart(pin).toFixed(1)+' nm</div>')
		+'<div class="co-ll">'+ll+'</div>'+youLine;
	if(!s)return '<div class="pin-co">'+hd+'<div class="co-row">'+(pin._fcWait?'Loading forecast&hellip;':'No data here')+'</div></div>';
	/* precip: probability % from the point forecast, else grid amount in inches */
	var pcp=(s.pop!=null)?('<span><span class="v">'+Math.round(s.pop)+'%</span> <span class="k">rain</span></span>')
		:(s.precip!=null?'<span><span class="v">'+s.precip.toFixed(2)+'</span> <span class="k">in rain</span></span>':'');
	/* weather line: wind (+gust), direction, wave height + period, pressure, air temp, then precip */
	var wx='<div class="co-row"><span><span class="v">'+Math.round(s.kt)+'</span> <span class="k">kt</span></span>'
		+(s.gust!=null?'<span><span class="v">'+Math.round(s.gust)+'</span> <span class="k">g</span></span>':'')
		+'<span><span class="v">'+card(s.dir)+'</span> <span class="k">'+Math.round(s.dir)+'°</span></span>'
		+(s.waveFt!=null?'<span><span class="v">'+s.waveFt.toFixed(1)+'</span> <span class="k">ft</span></span>':'')
		+(s.waveFt!=null&&s.wavePer!=null?'<span><span class="v">'+Math.round(s.wavePer)+'</span> <span class="k">s</span></span>':'')
		+(s.mb!=null?'<span><span class="v">'+Math.round(s.mb)+'</span> <span class="k">mb</span></span>':'')
		+(s.tempF!=null?'<span><span class="v">'+Math.round(s.tempF)+'°F</span> <span class="k">temp</span></span>':'')
		+pcp+'</div>';
	return '<div class="pin-co">'+hd+wx+'</div>';
}
/* dashed route line Buoy 42 → WP1 → … — ONE persistent polyline updated in place
   (setLatLngs) so a live drag doesn't thrash the SVG layer each frame. */
var routeLine=null, legLayer=L.layerGroup().addTo(map);
/* A small "X.X nm" label at each segment midpoint (Buoy 42→Start, Start→WP1, …).
   Skipped when the segment is shorter than ~40 px on screen so short legs / a
   zoomed-out view never pile labels on top of each other. Re-run on draw, drag
   and map move/zoom (the pixel length changes with zoom). */
function drawLegLabels(){
	legLayer.clearLayers();
	var pts=routePts();if(pts.length<2)return;
	for(var i=0;i<pts.length-1;i++){
		var a=pts[i],b=pts[i+1],pa=map.latLngToContainerPoint(a),pb=map.latLngToContainerPoint(b);
		if(Math.hypot(pb.x-pa.x,pb.y-pa.y)<40)continue;
		var d=distNM(a[0],a[1],b[0],b[1]),mid=[(a[0]+b[0])/2,(a[1]+b[1])/2];
		var leg=magBrg(a[0],a[1],b[0],b[1])+'°M &middot; '+d.toFixed(1)+' nm'; /* leg course (magnetic) + distance to the next point */
		var tw=legTWA(a,b);   /* + the wind angle you'd sail on it */
		L.marker(mid,{icon:L.divIcon({className:'leg-lbl',html:'<b>'+leg+'</b>'+(tw?'<i>'+twaText(tw)+'</i>':''),iconSize:[160,32],iconAnchor:[80,16]}),interactive:false,keyboard:false,zIndexOffset:400}).addTo(legLayer);
	}
}
function drawRouteLine(){
	var pts=routePts();
	if(pts.length<2){routeLayer.clearLayers();routeLine=null;legLayer.clearLayers();legHitLayer.clearLayers();return;}
	if(routeLine&&routeLayer.hasLayer(routeLine)){routeLine.setLatLngs(pts);}
	else{routeLayer.clearLayers();routeLine=L.polyline(pts,{color:'#0b2a4a',weight:2,opacity:.7,dashArray:'5 5',interactive:false}).addTo(routeLayer);}
	drawLegHits(pts);
	drawLegLabels();
	drawLaylines();
}
/* Click a leg of the route line to put a new waypoint there, between its two
   points (Brad, 2026-11-15). Each leg gets a wide invisible line to click; the
   new point goes on the line under the click, and can be dragged from there. */
function drawLegHits(pts){
	legHitLayer.clearLayers();
	if(pins.some(function(p){return p._dragging;}))return;
	var o=routeOrigin()?1:0;
	for(var k=0;k<pts.length-1;k++)(function(k){
		var a=pts[k],b=pts[k+1],at=k+1-o;   /* index in pins of the leg's end point */
		L.polyline([a,b],{color:'#0b2a4a',weight:18,opacity:0,className:'leg-hit',bubblingMouseEvents:false})
			.bindTooltip('Click to add a waypoint here',{sticky:true,direction:'top',offset:[0,-8]})
			.on('click',function(e){
				L.DomEvent.stop(e);
				if(typeof PASS!=='undefined'&&PASS.picking){passPick(e);return;}
				var rc=document.getElementById('tgRace');if(mode==='radar'||(rc&&rc.checked))return;
				var A=map.latLngToLayerPoint(a),B=map.latLngToLayerPoint(b),P=L.LineUtil.closestPointOnSegment(map.latLngToLayerPoint(e.latlng),A,B),ll=map.layerPointToLatLng(P);
				addPin(ll.lat,((ll.lng+540)%360)-180,{at:Math.max(0,at)});
			}).addTo(legHitLayer);
	})(k);
}
/* redraw the route line + re-number markers + refresh every callout (indices moved) */
function refreshRoute(){
	savePins();   /* My waypoints are kept in this browser */
	passPinsChanged();   /* the Passage brief routes these same waypoints */
	advanceNextWp();
	drawRouteLine();
	for(var i=0;i<pins.length;i++){pins[i].marker.setIcon(wpIcon(wpBadge(i)));pins[i].popup.setContent(pinContent(pins[i],curTi));}
	updateRouteBar();
	renderWpTable();
	swSchedule();   /* land / shoal check on every leg */
}
/* re-render only the callout text for every pin (numbers don't change on a move) */
function rerenderCallouts(){for(var i=0;i<pins.length;i++)pins[i].popup.setContent(pinContent(pins[i],curTi));renderWpTable();}
/* fetch a waypoint's exact-point forecast (cached server proxy) and re-render it */
/* Browser-direct point WIND forecast — used when the server's oyc_point_fc is
   429'd (ok:false). The visitor's IP isn't throttled, so the pin still gets its
   own forecast (then withMarine adds the waves). Matches the server's keys. */
function clientPointWx(lat,lon){
	var u='https://api.open-meteo.com/v1/forecast?latitude='+(+lat).toFixed(3)+'&longitude='+(+lon).toFixed(3)
		+'&hourly=wind_speed_10m,wind_gusts_10m,wind_direction_10m,pressure_msl,temperature_2m,precipitation_probability'
		+'&models=gfs_seamless,ecmwf_ifs025,icon_seamless,gem_seamless'
		+'&wind_speed_unit=kn&temperature_unit=fahrenheit&forecast_days=7&timezone=America%2FNew_York';
	return fetch(u).then(function(r){if(!r.ok)throw new Error('wx '+r.status);return r.json();}).then(function(wx){return {ok:true,wx:wx,marine:null};});
}
/* Every waypoint reads its OWN exact-point forecast, so it never depends on the
   Atlantic grid having loaded around it. If both the server and the browser are
   rate-limited, it tries again after 20 s, 1 min and 3 min; until then the card
   says "Loading forecast…" (or shows the grid, where that has loaded). */
function loadPinFC(pin,tries){
	tries=tries||0;var at=pin.lat.toFixed(3)+','+pin.lon.toFixed(3);
	pin._fcWait=true;
	function retry(){
		if(pin._gone||(pin.lat.toFixed(3)+','+pin.lon.toFixed(3))!==at)return; /* removed or moved: a newer load owns it */
		if(tries<3){setTimeout(function(){loadPinFC(pin,tries+1);},[20000,60000,180000][tries]);return;}
		pin._fcWait=false;pin.popup.setContent(pinContent(pin,curTi));renderWpTable();
	}
	fetchT(AJAX+'?action=oyc_point_fc&lat='+pin.lat.toFixed(3)+'&lon='+pin.lon.toFixed(3),12000)
		.then(function(r){return r.json();}).catch(function(){return null;})
		.then(function(res){ return (res&&res.ok&&res.wx&&res.wx.hourly) ? res : clientPointWx(pin.lat,pin.lon); }) /* server wx 429'd → browser-direct wx */
		.then(function(res){return withMarine(res,pin.lat,pin.lon);}) /* fill waves from the browser if the server was 429'd */
		.then(function(res){
			var S=fcSeriesFrom(res);if(pin._gone)return;if(!S){retry();return;}
			if((pin.lat.toFixed(3)+','+pin.lon.toFixed(3))!==at)return;
			pin.fc=S;pin._fcWait=false;
			refreshRoute(); /* leg wind angles, speeds and ETA use this point's forecast too */
		}).catch(retry);
}
/* opt: name (e.g. "The Race"), race / gs flags, at = position in the route (default: the end) */
function addPin(lat,lon,opt){
	opt=opt||{};
	var m=L.marker([lat,lon],{icon:wpIcon(wpBadge(pins.length)),keyboard:false,zIndexOffset:800,draggable:true,autoPan:true}).addTo(map);
	/* closeButton:false — removal is the explicit "✕ Remove" control in the callout,
	   NOT closing the popup. Removal is deliberately decoupled from the marker click
	   and popup-close so grabbing a marker to DRAG it can never delete it. */
	var p=L.popup({autoClose:false,closeOnClick:false,closeButton:false,autoPan:false,maxWidth:360,className:'pin-pop'}).setLatLng([lat,lon]);
	var pin={marker:m,popup:p,lat:lat,lon:lon,fc:null,id:++pinSeq,name:opt.name||null,race:!!opt.race,gs:!!opt.gs,nLat:lat,nLon:lon};
	if(opt.at!=null&&opt.at>=0&&opt.at<pins.length)pins.splice(opt.at,0,pin);else pins.push(pin);
	p.setContent(pinContent(pin,curTi)); /* grid placeholder until the point forecast arrives */
	m.bindPopup(p);
	wireCardDrag(pin);
	/* The card starts closed: the waypoint table beside the map carries the same
	   readings. A TAP on the numbered marker toggles its card open on the map
	   (Leaflet's bindPopup click-toggle). Dragging the marker is not a click, so
	   re-placing a point never opens or closes it. */
	/* drag to re-place: rubber-band the route line live, then recompute legs +
	   pull a fresh point forecast for the new spot on drop. Numbers don't change on
	   a move, so re-render callouts only (no setIcon, which would churn the icon). */
	m.on('dragstart',function(){pin._dragging=true;pin.fc=null;hoverTip.style.display='none';}); /* drop the old-spot forecast so callouts fall back to grid at the new spot */
	m.on('drag',function(ev){var ll=ev.target.getLatLng();pin.lat=ll.lat;pin.lon=((ll.lng+540)%360)-180;pin.popup.setLatLng(ll);drawRouteLine();rerenderCallouts();updateRouteBar();}); /* live: line + distances update as you drag */
	m.on('dragend',function(ev){var ll=ev.target.getLatLng();pin.lat=ll.lat;pin.lon=((ll.lng+540)%360)-180;pin._dragging=false;pinUnname(pin);drawRouteLine();rerenderCallouts();updateRouteBar();savePins();passPinsChanged();swSchedule();loadPinFC(pin);}); /* pull the exact-point forecast for the new spot */
	hoverTip.style.display='none'; /* clear the cursor readout left by the drop tap/move */
	refreshRoute();
	loadPinFC(pin);
}
/* ---------- movable cards ----------
   A card can be dragged anywhere so it doesn't hide the route or another card.
   Its spot is kept as a pixel offset on the popup, so it rides along when the map
   pans/zooms or the point itself is dragged; a leader line joins a moved card to
   its point. Double-click / double-tap the card to snap it back. */
var CO_BASE=L.point(L.Popup.prototype.options.offset||[0,7]);
function coApply(pin){
	var p=pin.popup,o=pin.coOff||[0,0];
	p.options.offset=[CO_BASE.x+o[0],CO_BASE.y+o[1]];
	if(p.isOpen()){if(p._updatePosition)p._updatePosition();else p.update();}
	coLead(pin);
}
/* leader line from the card's nearest edge to the point (measured on screen) */
function coLead(pin){
	var c=pin.popup.getElement&&pin.popup.getElement(),mk=pin.marker.getElement&&pin.marker.getElement();
	if(!c||!mk||!pin.popup.isOpen())return;
	var ln=c.querySelector('.co-lead'),o=pin.coOff||[0,0];
	if(!o[0]&&!o[1]){if(ln)ln.style.display='none';return;}
	if(!ln){ln=document.createElement('div');ln.className='co-lead';c.appendChild(ln);}
	var cr=c.getBoundingClientRect(),mr=mk.getBoundingClientRect();
	var px=mr.left+mr.width/2-cr.left,py=mr.top+mr.height/2-cr.top,
		sx=Math.max(0,Math.min(cr.width,px)),sy=Math.max(0,Math.min(cr.height,py)),
		dx=px-sx,dy=py-sy,len=Math.hypot(dx,dy)-13; /* stop at the marker's edge */
	if(len<4){ln.style.display='none';return;}
	ln.style.display='block';ln.style.left=sx+'px';ln.style.top=sy+'px';ln.style.width=len+'px';
	ln.style.transform='rotate('+Math.atan2(dy,dx)+'rad)';
}
function wireCardDrag(pin){
	var p=pin.popup;
	p.on('add',function(){
		var c=p.getElement();if(!c)return;
		if(c._coWired){coLead(pin);return;}
		c._coWired=true;
		var w=c.querySelector('.leaflet-popup-content-wrapper')||c,st=null,lastTap=0;
		w.addEventListener('pointerdown',function(e){
			if(e.button>0||(e.target.closest&&e.target.closest('.co-x')))return;
			var o=pin.coOff||[0,0];st={x:e.clientX,y:e.clientY,ox:o[0],oy:o[1],id:e.pointerId,moved:false};
			try{w.setPointerCapture(e.pointerId);}catch(_){}
			e.preventDefault();e.stopPropagation();
		});
		w.addEventListener('pointermove',function(e){
			if(!st||e.pointerId!==st.id)return;
			var dx=e.clientX-st.x,dy=e.clientY-st.y;
			if(!st.moved&&Math.abs(dx)+Math.abs(dy)<4)return;
			if(!st.moved){st.moved=true;c.classList.add('co-dragging');hoverTip.style.display='none';}
			pin.coOff=[st.ox+dx,st.oy+dy];coApply(pin);
		});
		function end(e){
			if(!st||e.pointerId!==st.id)return;
			var tap=!st.moved&&e.type==='pointerup';st=null;c.classList.remove('co-dragging');
			try{w.releasePointerCapture(e.pointerId);}catch(_){}
			if(!tap)return;
			var now=Date.now();
			if(now-lastTap<350){lastTap=0;pin.coOff=[0,0];coApply(pin);}else lastTap=now; /* double-tap: snap back */
		}
		w.addEventListener('pointerup',end);w.addEventListener('pointercancel',end);
		coLead(pin);
	});
	/* content re-renders can change the card's width → re-aim the line once laid out */
	p.on('contentupdate',function(){requestAnimationFrame(function(){coLead(pin);});});
}
function removePin(pin){if(pin._gone)return;pin._gone=true;pin._dragging=false;try{if(pin.marker.dragging&&pin.marker.dragging.disable)pin.marker.dragging.disable();}catch(e){}map.removeLayer(pin.marker);map.closePopup(pin.popup);pins=pins.filter(function(x){return x!==pin;});refreshRoute();}
/* a named point (The Race, St. George's…) dragged well away from its spot loses the name */
function pinUnname(pin){if(pin.name&&distNM(pin.lat,pin.lon,pin.nLat,pin.nLon)>2){pin.name=null;pin.race=false;pin.gs=false;}}
/* move a waypoint (start/finish picks, Claude's approved moves) */
function movePin(pin,lat,lon,name){
	pin.lat=lat;pin.lon=lon;pin.nLat=lat;pin.nLon=lon;pin.fc=null;pin.name=name||null;
	pin.race=!!name&&name==='The Race';pin.gs=false;
	pin.marker.setLatLng([lat,lon]);pin.popup.setLatLng([lat,lon]);
	refreshRoute();loadPinFC(pin);
}
/* My waypoints survive a reload: kept in this browser (like My chart) */
var PINS_KEY='oyc_wm_pins',pinsLoading=false;
function savePins(){if(pinsLoading)return;try{localStorage.setItem(PINS_KEY,JSON.stringify(pins.map(function(p){var o={lat:+p.lat.toFixed(5),lon:+p.lon.toFixed(5)};if(p.name)o.n=p.name;if(p.race)o.race=1;if(p.gs)o.gs=1;return o;})));}catch(e){}}
function loadPins(list){
	pinsLoading=true;
	try{(list||[]).slice(0,60).forEach(function(q){if(q&&isFinite(q.lat)&&isFinite(q.lon)&&Math.abs(q.lat)<=85)addPin(+q.lat,+q.lon,{name:q.n?String(q.n).slice(0,40):null,race:!!q.race,gs:!!q.gs});});}catch(e){}
	pinsLoading=false;savePins();
}
function updatePins(ti){for(var i=0;i<pins.length;i++)pins[i].popup.setContent(pinContent(pins[i],ti));renderWpTable();drawLegLabels();drawLaylines();updateBoatInfo();}

/* ── Safe water: land and shoal check on every leg, and Autoroute ──────────
   Reads the "safe water" chart tiles (inc/enc-cache.php, OYC_ENC_SAFE_FT): NOAA's
   chart drawn with its safety contour at the boat's draft + 4 ft, rounded up to
   the next contour we cache (6, 10, 12, 18, 30 or 60 ft; on older charts with
   only 6/12 ft contours NOAA uses the 12 ft line for 10).
   Land is transparent, water shallower than the contour (and drying banks) is
   tinted, deeper water is white. Every leg inside the chart area (Chesapeake to
   Cape Cod) is sampled pixel by pixel as waypoints are connected; a leg over land
   or shoal water turns red/orange and its waypoint says why. Autoroute searches a
   grid of the same pixels for the shortest way round, simplifies it to a few
   waypoints and offers them to approve or reject; when it can't find a way, it
   asks Claude, and checks Claude's waypoints the same way. Not for navigation:
   rocks, wrecks and buoys are not checked. */
var SAFE_FT=[6,10,12,18,30,60],SAFE_MARGIN=4,SAFE_AREA=[38,-77,42.5,-69.5],DRAFT_KEY='oyc_wm_draft';/* SAFE_FT, SAFE_AREA must match OYC_ENC_SAFE_FT, OYC_ENC_AREA */
/* draft typed as 4'10", 4' 10, 4 10, 4ft 10in or 4.8 → feet; NaN if unreadable */
function parseDraft(t){t=String(t||'').trim().replace(/[\u2019\u2032]/g,"'").replace(/[\u201d\u2033]/g,'"');
	var m=t.match(/^(\d+(?:\.\d+)?)\s*(?:'|ft|feet)?\s*(?:(\d+(?:\.\d+)?)\s*(?:"|''|in|inches)?)?$/i);
	if(!m)return NaN;var f=parseFloat(m[1]),i=m[2]!=null?parseFloat(m[2]):0;if(m[2]!=null&&(i>=12||/\./.test(m[1])))return NaN;return f+i/12;}
/* feet → 4'10" (whole inches) */
function draftTxt(d){var i=Math.round(d*12),f=Math.floor(i/12);i-=f*12;return f+"'"+(i?i+'"':'');}
function boatDraft(){var d=6;try{var v=parseFloat(localStorage.getItem(DRAFT_KEY));if(isFinite(v)&&v>=1&&v<=25)d=v;}catch(e){}return d;}
function safeFt(){var need=boatDraft()+SAFE_MARGIN;for(var i=0;i<SAFE_FT.length;i++)if(SAFE_FT[i]>=need-0.01)return SAFE_FT[i];return 60;}/* Brad 2026-11-15: draft + 4 ft, next contour up */
function swVar(){return 's'+safeFt();}
function swIn(lat,lon){return lat>=SAFE_AREA[0]&&lat<=SAFE_AREA[2]&&lon>=SAFE_AREA[1]&&lon<=SAFE_AREA[3];}
function swTS(z){return z<=ENC_HIRES_MAX?512:256;}
function swPx(lat,lon,z){var n=Math.pow(2,z)*swTS(z),s=Math.sin(lat*Math.PI/180);return [(lon+180)/360*n,(0.5-Math.log((1+s)/(1-s))/(4*Math.PI))*n];}
function swLL(gx,gy,z){var n=Math.pow(2,z)*swTS(z),m=Math.PI-2*Math.PI*gy/n;return [180/Math.PI*Math.atan(Math.sinh(m)),gx/n*360-180];}
/* pixels per nautical mile at this latitude and zoom */
function swPxNm(lat,z){var a=swPx(lat,0,z),b=swPx(lat+1/60,0,z);return Math.abs(a[1]-b[1]);}
/* leg length → zoom: harbour detail for short legs, fewer tiles for long ones */
function swZoom(nm){return nm<=12?13:(nm<=40?12:11);}
/* pixel colour → 0 deep enough · 1 too shallow / drying · 2 land · 3 line, symbol or label.
   h (harbour-scale charts) wins wherever it has chart; s (the chart for the scale) elsewhere. */
function swCode(px,j){var r=px[j],g=px[j+1],b=px[j+2];
	if(px[j+3]<110)return 2;
	if(r>=236&&g>=236&&b>=236)return 0;
	if(Math.abs(r-175)<26&&Math.abs(g-205)<22&&Math.abs(b-225)<22)return 1;
	if(Math.abs(r-214)<16&&Math.abs(g-219)<16&&Math.abs(b-201)<16)return 1;
	return 3;}
function swClassify(ps,ph,t){
	var n=t.n*t.n,o=new Uint8Array(n),water=0;
	for(var i=0,j=0;i<n;i++,j+=4){var c=(ph&&ph[j+3]>=110)?swCode(ph,j):(ps?swCode(ps,j):2);if(c<2)water++;o[i]=c;}
	t.empty=water<20;   /* no water at all: inland, or no chart here */
	return o;
}
var SWT={},SWT_N=0;
/* one variant's pixels (cache, then our server fetching from NOAA), or null */
function swImg(v,z,x,y,n){
	var urls=[];if(ENC_CACHE)urls.push(ENC_CACHE+'/'+v+'/'+z+'/'+x+'/'+y+'.png?cv='+ENC_VER);
	urls.push(ENC_AJAX+'?action=oyc_enc_tile&z='+z+'&x='+x+'&y='+y+'&v='+v+'&cv='+ENC_VER);
	return new Promise(function(res){var i=0;
		function next(){if(i>=urls.length){res(null);return;}var im=new Image();
			im.onload=function(){try{var c=document.createElement('canvas');c.width=c.height=n;var g=c.getContext('2d');g.drawImage(im,0,0,n,n);res(g.getImageData(0,0,n,n).data);}catch(e){res(null);}};
			im.onerror=next;im.src=urls[i++];}
		next();});
}
function swTile(v,z,x,y){
	var k=v+'/'+z+'/'+x+'/'+y,t=SWT[k];if(t)return t.p;
	if(++SWT_N>900){SWT={};SWT_N=1;}   /* keep memory bounded */
	t=SWT[k]={d:null,n:swTS(z),empty:false,fail:false};
	t.p=Promise.all([swImg(v,z,x,y,t.n),swImg('h'+v.slice(1),z,x,y,t.n)]).then(function(r){
		if(!r[0]&&!r[1]){t.fail=true;return t;}
		t.d=swClassify(r[0],r[1],t);return t;});
	return t.p;
}
function swLoad(v,z,keys){return Promise.all(keys.map(function(k){return swTile(v,z,k[0],k[1]);}));}
/* code at a global pixel: 0-3 as above, -1 not loaded / failed, -2 no chart data */
function swAt(v,z,gx,gy){var T=swTS(z),x=Math.floor(gx/T),y=Math.floor(gy/T),t=SWT[v+'/'+z+'/'+x+'/'+y];
	if(!t||!t.d)return -1;if(t.empty)return -2;return t.d[Math.floor(gy-y*T)*T+Math.floor(gx-x*T)];}
function swTilesFor(pts,z){var T=swTS(z),seen={},out=[];pts.forEach(function(p){var x=Math.floor(p[0]/T),y=Math.floor(p[1]/T),k=x+','+y;if(!seen[k]){seen[k]=1;out.push([x,y]);}});return out;}
/* What's at one point: Promise of 0 safe · 1 shallow · 2 land · -1 unknown */
function swPoint(lat,lon){
	if(!swIn(lat,lon))return Promise.resolve(-1);
	var v=swVar(),z=13,p=swPx(lat,lon,z);
	return swLoad(v,z,swTilesFor([p],z)).then(function(){var c=swAt(v,z,p[0],p[1]);return c===3?0:(c<0?-1:c);});
}
/* One leg a→b ({lat,lon}). Resolves {st:'ok'|'land'|'shallow'|'skip', runs:[{k,a:[lat,lon],b:[lat,lon]}]} */
function swCheckLeg(a,b,v){
	v=v||swVar();
	if(!swIn(a.lat,a.lon)||!swIn(b.lat,b.lon))return Promise.resolve({st:'skip',runs:[]});
	var nm=distNM(a.lat,a.lon,b.lat,b.lon);if(nm<0.02)return Promise.resolve({st:'ok',runs:[]});
	var z=swZoom(nm),p=swPx(a.lat,a.lon,z),q=swPx(b.lat,b.lon,z),len=Math.hypot(q[0]-p[0],q[1]-p[1]),n=Math.max(2,Math.ceil(len/0.7)),S=[];
	for(var i=0;i<=n;i++)S.push([p[0]+(q[0]-p[0])*i/n,p[1]+(q[1]-p[1])*i/n]);
	var keys=swTilesFor(S,z);if(keys.length>160)return Promise.resolve({st:'skip',runs:[]});
	return swLoad(v,z,keys).then(function(){
		var ppn=swPxNm(a.lat,z),endS=0.12*ppn,endL=3,runs=[],cur=null;
		S.forEach(function(s,i){
			var d=Math.min(i,n-i)*len/n,c=swAt(v,z,s[0],s[1]),k=null;
			if(c===2&&d>endL)k='land';else if(c===1&&d>endS)k='shallow';
			if(cur&&(k!==cur.k)){if(cur.n>=(cur.k==='land'?2:3))runs.push(cur);cur=null;}
			if(k){if(!cur)cur={k:k,a:swLL(s[0],s[1],z),n:0};cur.b=swLL(s[0],s[1],z);cur.n++;}
		});
		if(cur&&cur.n>=(cur.k==='land'?2:3))runs.push(cur);
		return {st:runs.some(function(r){return r.k==='land';})?'land':(runs.length?'shallow':'ok'),runs:runs};
	});
}
/* ── the check on My waypoints, re-run (debounced) whenever the route changes ── */
var SW={res:null,key:'',busy:false,timer:0,fix:null,layer:L.layerGroup().addTo(map),prev:L.layerGroup().addTo(map)};
function swLegs(){var o=routeOrigin(),L2=[],prev=o?{lat:o.lat,lon:o.lon,id:'o'}:null;
	pins.forEach(function(p){if(prev)L2.push({a:prev,b:p,toId:p.id});prev=p;});return L2;}
function swKey(){return swVar()+'|'+swLegs().map(function(l){return l.a.lat.toFixed(5)+','+l.a.lon.toFixed(5)+'>'+l.b.lat.toFixed(5)+','+l.b.lon.toFixed(5);}).join(';')+'|'+pins.map(function(p){return p.lat.toFixed(5)+','+p.lon.toFixed(5);}).join(';');}
function swSchedule(){clearTimeout(SW.timer);SW.timer=setTimeout(swRun,450);}
function swRun(){
	if(pins.some(function(p){return p._dragging;})){swSchedule();return;}
	var key=swKey();if(key===SW.key&&SW.res)return;SW.key=key;
	var legs=swLegs(),v=swVar();SW.running=!!pins.length;
	if(SW.fix&&!SW.fix.busy){SW.fix=null;SW.prev.clearLayers();swPanel();}   /* the route changed: old fixes no longer fit */
	if(!pins.length){SW.res=null;swDraw();swUI();return;}
	Promise.all([Promise.all(legs.map(function(l){return swCheckLeg(l.a,l.b,v);})),Promise.all(pins.map(function(p){return swPoint(p.lat,p.lon);}))]).then(function(r){
		if(SW.key!==key)return;   /* the route changed while this ran */
		var res={legs:{},pins:{},n:0,ft:safeFt()};
		legs.forEach(function(l,i){res.legs[l.toId]=r[0][i];if(r[0][i].st==='land'||r[0][i].st==='shallow')res.n++;});
		pins.forEach(function(p,i){res.pins[p.id]=r[1][i];if(r[1][i]===2)res.n++;});
		SW.res=res;SW.running=false;swDraw();swUI();depRun();
		if(SW.wantAuto){SW.wantAuto=false;SW.fix=null;swAutoroute();}   /* Autoroute was clicked while this ran */
	});
}
/* ── Depth at each waypoint (Brad 2026-11-15) ──
   The charted depth range under a pin, from the same cached safe-water tiles
   (zoom 13): each contour variant s<ft>/h<ft> says "deeper" or "shallower than
   <ft>" there, so a binary search over 6/10/12/18/30/60 ft gives the band, e.g.
   12-18 ft (about 3 contours per pin). It is the chart's depth area, not the
   exact sounding. Pins are done one at a time (each contour is 2 tiles), so a
   long route never sends a burst of tile requests to our server. */
var DEP={res:{},busy:false};
function depKey(p){return p.lat.toFixed(5)+','+p.lon.toFixed(5);}
/* what the chart says at a point for one contour: 0 deeper · 1 shallower · 2 land · -1 unknown.
   A 7×7 pixel patch, ignoring lines, symbols and labels, majority wins. */
function depAt(lat,lon,f){var z=13,v='s'+f,p=swPx(lat,lon,z);
	return swLoad(v,z,swTilesFor([[p[0]-3,p[1]-3],[p[0]+3,p[1]+3],[p[0]-3,p[1]+3],[p[0]+3,p[1]-3]],z)).then(function(){
		var n=[0,0,0];for(var dx=-3;dx<=3;dx++)for(var dy=-3;dy<=3;dy++){var c=swAt(v,z,p[0]+dx,p[1]+dy);if(c>=0&&c<=2)n[c]++;}
		if(n[0]+n[1]+n[2]===0)return -1;
		return n[2]>n[0]+n[1]?2:(n[0]>=n[1]?0:1);});}
/* Promise of {lo,hi} in feet (lo 0 = under the first contour, hi null = deeper than the last), 'land' or null */
function depPoint(lat,lon){
	if(!swIn(lat,lon))return Promise.resolve(null);
	var lo=0,hi=SAFE_FT.length-1,deep=-1;   /* deep = index of the deepest contour known to be deeper */
	function step(){if(lo>hi)return Promise.resolve({lo:deep<0?0:SAFE_FT[deep],hi:deep+1<SAFE_FT.length?SAFE_FT[deep+1]:null});
		var m=(lo+hi)>>1;return depAt(lat,lon,SAFE_FT[m]).then(function(c){
			if(c===2)return 'land';if(c<0)return null;
			if(c===0){deep=m;lo=m+1;}else hi=m-1;return step();});}
	return step();
}
function depRun(){
	if(DEP.busy)return;var p=null;
	for(var i=0;i<pins.length;i++)if(!(depKey(pins[i]) in DEP.res)&&!pins[i]._dragging){p=pins[i];break;}
	if(!p)return;var k=depKey(p);DEP.busy=true;
	depPoint(p.lat,p.lon).then(function(r){DEP.res[k]=r;},function(){DEP.res[k]=null;}).then(function(){
		DEP.busy=false;
		pins.forEach(function(q){if(depKey(q)===k)q.popup.setContent(pinContent(q,curTi));});
		renderWpTable();setTimeout(depRun,150);});
}
/* Depth line for a pin's card / table row. NOAA shades a depth area by its
   SHOALEST depth (DRVAL1), so these tiles say how shallow the charted area
   around the point gets, not the depth at the point: in a coarse area (e.g.
   charted 6-18 ft as one area) a 12 ft spot read "6-10 ft" (Brad, 2026-11-15,
   off Fishers Island). So it reads "6 ft or more": the lower bound is sure,
   the upper one isn't. Orange only when the area is charted under 6 ft. */
function depNote(pin,cls){
	var r=DEP.res[depKey(pin)];if(r===undefined)return swIn(pin.lat,pin.lon)&&pins.length?'<span class="'+cls+'">Depth &hellip;</span>':'';
	if(!r||r==='land')return '';
	var low=r.lo===0,t=low?'under '+r.hi+' ft':(r.lo+' ft or more');
	return '<span class="'+cls+(low?' sw-shoal':'')+'" title="Least charted depth of the depth area at this point (NOAA chart). The water here may be deeper: check the soundings. Not for navigation.">Depth '+t+'</span>';
}
function swDraw(){
	SW.layer.clearLayers();var r=SW.res;if(!r)return;
	Object.keys(r.legs).forEach(function(id){r.legs[id].runs.forEach(function(u){
		var col=u.k==='land'?'#d7263d':'#e67e22';
		L.polyline([u.a,u.b],{color:col,weight:7,opacity:.55,lineCap:'round',interactive:false}).addTo(SW.layer);
		L.circleMarker(u.a,{radius:4,color:col,weight:2,fillColor:'#fff',fillOpacity:1,interactive:false}).addTo(SW.layer);});});
}
/* the warning line for the leg into a pin (and the pin itself) */
function swNote(pin,cls){
	var r=SW.res;if(!r)return '';var h='',l=r.legs[pin.id],c=r.pins[pin.id];
	if(c===2)h+='<span class="'+cls+' sw-bad">&#9888; This point is on land</span>';
	if(l&&l.st==='land')h+='<span class="'+cls+' sw-bad">&#9888; Leg crosses land &middot; Autoroute</span>';
	else if(l&&l.st==='shallow')h+='<span class="'+cls+' sw-shoal">&#9888; Leg crosses water under '+r.ft+' ft &middot; Autoroute</span>';
	return h;
}
function swUI(){
	var b=document.getElementById('rbAuto');
	if(b){var n=SW.res?SW.res.n:0;b.classList.toggle('warn',n>0);b.innerHTML=n?'&#9888;&nbsp;Autoroute ('+n+')':'Autoroute';
		b.title=n?('Fix '+n+' leg'+(n>1?'s':'')+' that cross land or water under '+safeFt()+' ft'):'Check the legs for land and water under '+safeFt()+' ft (draft '+draftTxt(boatDraft())+' + '+SAFE_MARGIN+' ft)';}
	for(var i=0;i<pins.length;i++)pins[i].popup.setContent(pinContent(pins[i],curTi));
	renderWpTable();swPanel();
}
/* ── Autoroute ── */
function swHeap(){var a=[],f=null;return {push:function(i,p){a.push([p,i]);var k=a.length-1;while(k>0){var q=(k-1)>>1;if(a[q][0]<=a[k][0])break;var t=a[q];a[q]=a[k];a[k]=t;k=q;}},
	pop:function(){var top=a[0],e=a.pop();if(a.length){a[0]=e;var k=0;for(;;){var l=2*k+1,r=l+1,m=k;if(l<a.length&&a[l][0]<a[m][0])m=l;if(r<a.length&&a[r][0]<a[m][0])m=r;if(m===k)break;var t=a[m];a[m]=a[k];a[k]=t;k=m;}}return top[1];},
	size:function(){return a.length;}};}
/* Build a blocked/free grid over the box around a→b (pad in nm), at zoom z.
   Resolves {W,H,f,x0,y0,z,blk:Uint8Array (0 free,1 shallow,2 land), ok} */
function swGrid(a,b,padNm,v,z){
	var p=swPx(a.lat,a.lon,z),q=swPx(b.lat,b.lon,z),pad=padNm*swPxNm((a.lat+b.lat)/2,z);
	var x0=Math.min(p[0],q[0])-pad,x1=Math.max(p[0],q[0])+pad,y0=Math.min(p[1],q[1])-pad,y1=Math.max(p[1],q[1])+pad,T=swTS(z);
	var keys=[];for(var tx=Math.floor(x0/T);tx<=Math.floor(x1/T);tx++)for(var ty=Math.floor(y0/T);ty<=Math.floor(y1/T);ty++)keys.push([tx,ty]);
	if(keys.length>220)return Promise.resolve(null);
	return swLoad(v,z,keys).then(function(){
		var f=Math.max(1,Math.ceil(Math.max(x1-x0,y1-y0)/650)),W=Math.ceil((x1-x0)/f),H=Math.ceil((y1-y0)/f),blk=new Uint8Array(W*H);
		for(var cy=0;cy<H;cy++)for(var cx=0;cx<W;cx++){var worst=0;
			for(var sy=0;sy<f&&worst<2;sy++)for(var sx=0;sx<f;sx++){var c=swAt(v,z,x0+cx*f+sx+.5,y0+cy*f+sy+.5);
				if(c===2||c<0){worst=2;break;}if(c===1)worst=1;}
			blk[cy*W+cx]=worst;}
		return {W:W,H:H,f:f,x0:x0,y0:y0,z:z,blk:blk};
	});
}
function swCell(G,lat,lon){var p=swPx(lat,lon,G.z);return [Math.max(0,Math.min(G.W-1,Math.floor((p[0]-G.x0)/G.f))),Math.max(0,Math.min(G.H-1,Math.floor((p[1]-G.y0)/G.f)))];}
function swCellLL(G,cx,cy){return swLL(G.x0+(cx+.5)*G.f,G.y0+(cy+.5)*G.f,G.z);}
/* shortest water path a→b on the grid, simplified to turning points ([lat,lon] list, ends excluded), or null */
function swPath(G,a,b,clNm){
	var W=G.W,H=G.H,N=W*H,src=G.blk,blk=new Uint8Array(N),i,x,y;
	/* keep clNm (0.05 nm ≈ 90 m, else 0.025) off land and shoal water: grow blocked cells by the clearance */
	var cl=Math.max(1,Math.round((clNm||0.05)*swPxNm((a.lat+b.lat)/2,G.z)/G.f)),opened=new Uint8Array(N);
	for(y=0;y<H;y++)for(x=0;x<W;x++){if(!src[y*W+x])continue;
		for(var dy=-cl;dy<=cl;dy++)for(var dx=-cl;dx<=cl;dx++){var X=x+dx,Y=y+dy;if(X>=0&&Y>=0&&X<W&&Y<H)blk[Y*W+X]=1;}}
	var s=swCell(G,a.lat,a.lon),t=swCell(G,b.lat,b.lon);
	/* waypoints often sit in a shallow harbour or on the shore: open a small circle round each end, but never through land */
	[s,t].forEach(function(e){var r=Math.max(2,Math.round(0.15*swPxNm(a.lat,G.z)/G.f));
		for(var dy=-r;dy<=r;dy++)for(var dx=-r;dx<=r;dx++){var X=e[0]+dx,Y=e[1]+dy;if(X<0||Y<0||X>=W||Y>=H||dx*dx+dy*dy>r*r)continue;if(src[Y*W+X]!==2){blk[Y*W+X]=0;opened[Y*W+X]=1;}}
		blk[e[1]*W+e[0]]=0;});
	var si=s[1]*W+s[0],ti=t[1]*W+t[0],g=new Float32Array(N).fill(Infinity),par=new Int32Array(N).fill(-1),done=new Uint8Array(N),h=swHeap();
	g[si]=0;h.push(si,0);var DX=[1,-1,0,0,1,1,-1,-1],DY=[0,0,1,-1,1,-1,1,-1],DC=[1,1,1,1,1.4142,1.4142,1.4142,1.4142],tx=t[0],ty=t[1],found=false,it=0;
	while(h.size()){var c=h.pop();if(done[c])continue;done[c]=1;if(c===ti){found=true;break;}if(++it>900000)break;
		var cx=c%W,cy=(c-cx)/W;
		for(var k=0;k<8;k++){var nx=cx+DX[k],ny=cy+DY[k];if(nx<0||ny<0||nx>=W||ny>=H)continue;var ni=ny*W+nx;if(blk[ni]||done[ni])continue;
			var ng=g[c]+DC[k];if(ng<g[ni]){g[ni]=ng;par[ni]=c;var ddx=Math.abs(nx-tx),ddy=Math.abs(ny-ty);h.push(ni,ng+Math.max(ddx,ddy)+0.4142*Math.min(ddx,ddy));}}}
	if(!found)return null;
	var path=[];for(var u=ti;u>=0;u=par[u])path.push(u);path.reverse();
	function clear(i1,i2){var x1=i1%W,y1=(i1-x1)/W,x2=i2%W,y2=(i2-x2)/W,n=Math.max(Math.abs(x2-x1),Math.abs(y2-y1))*2;
		for(var j=1;j<n;j++){var X=Math.round(x1+(x2-x1)*j/n),Y=Math.round(y1+(y2-y1)*j/n);if(blk[Y*W+X])return false;}return true;}
	/* string-pull: from each turning point, go straight to the farthest point still in clear water */
	var out=[],at=0;
	while(at<path.length-1){var best=at+1;for(var j=path.length-1;j>at+1;j--){if(clear(path[at],path[j])){best=j;break;}}
		if(best<path.length-1)out.push(best);at=best;}
	/* tidy up: drop any turning point whose neighbours see each other without crossing
	   land or shoal water (the clearance relaxed), shortest hops first */
	function open2(i1,i2){var x1=i1%W,y1=(i1-x1)/W,x2=i2%W,y2=(i2-x2)/W,n=Math.max(Math.abs(x2-x1),Math.abs(y2-y1))*2;
		for(var j=1;j<n;j++){var X=Math.round(x1+(x2-x1)*j/n),Y=Math.round(y1+(y2-y1)*j/n),q=Y*W+X;if(src[q]&&!opened[q])return false;}return true;}
	var V=[si].concat(out.map(function(j){return path[j];}),[ti]),changed=true;
	while(changed&&V.length>2){changed=false;var bestK=-1,bestD=Infinity;
		for(var k=1;k<V.length-1;k++){if(!open2(V[k-1],V[k+1]))continue;var xa=V[k-1]%W,xb=V[k]%W,d=Math.hypot(xb-xa,(V[k]-xb)/W-(V[k-1]-xa)/W);if(d<bestD){bestD=d;bestK=k;}}
		if(bestK>0){V.splice(bestK,1);changed=true;}}
	return V.slice(1,-1).slice(0,8).map(function(c){var X=c%W;return swCellLL(G,X,(c-X)/W);});
}
/* nearest clear water to a point (for a waypoint on land), or null */
function swNearestWater(lat,lon,v){
	var z=13,ppn=swPxNm(lat,z),p=swPx(lat,lon,z),R=Math.round(1.5*ppn),S=[];
	for(var dy=-R;dy<=R;dy+=8)for(var dx=-R;dx<=R;dx+=8)S.push([p[0]+dx,p[1]+dy]);
	return swLoad(v,z,swTilesFor(S,z)).then(function(){
		for(var r=2;r<=R;r+=2){var best=null;
			for(var a=0;a<64;a++){var an=a/64*2*Math.PI,x=p[0]+r*Math.cos(an),y=p[1]+r*Math.sin(an);
				if(swAt(v,z,x,y)!==0)continue;var ok=true;   /* and some room around it */
				for(var b=0;b<8&&ok;b++){var bn=b/8*2*Math.PI;if(swAt(v,z,x+5*Math.cos(bn),y+5*Math.sin(bn))!==0)ok=false;}
				if(ok){best=[x,y];break;}}
			if(best)return swLL(best[0],best[1],z);}
		return null;});
}
/* a coarse text picture of the grid for Claude: # land · ~ too shallow · . deep enough */
function swAscii(G){
	var cw=Math.max(1,Math.ceil(G.W/64)),ch=Math.max(1,Math.ceil(G.H/44)),rows=[];
	for(var y=0;y<G.H;y+=ch){var s='';for(var x=0;x<G.W;x+=cw){var w=0;
		for(var yy=y;yy<Math.min(G.H,y+ch);yy++)for(var xx=x;xx<Math.min(G.W,x+cw);xx++)w=Math.max(w,G.blk[yy*G.W+xx]);
		s+=w===2?'#':(w===1?'~':'.');}rows.push(s);}
	var nw=swLL(G.x0,G.y0,G.z),se=swLL(G.x0+G.W*G.f,G.y0+G.H*G.f,G.z);
	return {north:+nw[0].toFixed(4),south:+se[0].toFixed(4),west:+nw[1].toFixed(4),east:+se[1].toFixed(4),rows:rows};
}
function swAskClaude(a,b,G,v,why){
	if(!PB_AJAX_OK||!PB_LOGGED)return Promise.resolve({err:PB_LOGGED?'Claude isn’t switched on for this site.':'Sign in to let Claude try this leg.'});
	var fd=new FormData();fd.append('action','oyc_autoroute_ai');fd.append('nonce',PB_NONCE);
	fd.append('leg',JSON.stringify({from:{lat:+a.lat.toFixed(4),lon:+a.lon.toFixed(4)},to:{lat:+b.lat.toFixed(4),lon:+b.lon.toFixed(4)},safety_ft:safeFt(),draft_ft:boatDraft(),problem:why,grid:G?swAscii(G):null}));
	var ctl=new AbortController(),tm=setTimeout(function(){ctl.abort();},100000);
	return fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin',signal:ctl.signal}).then(function(x){clearTimeout(tm);return x.json();}).then(function(j){
		if(!j||!j.ok)return {err:(j&&j.msg)||'Claude couldn’t answer just now.'};
		var pts=(j.waypoints||[]).filter(function(w){return isFinite(w.lat)&&isFinite(w.lon);}).slice(0,8).map(function(w){return [+w.lat,+w.lon];});
		if(!pts.length)return {err:j.note||'Claude didn’t find a way round either.'};
		var seq=[a].concat(pts.map(function(p){return {lat:p[0],lon:p[1]};}),[b]);
		return Promise.all(seq.slice(1).map(function(q,i){return swCheckLeg(seq[i],q,v);})).then(function(rs){
			var bad=rs.filter(function(r){return r.st==='land'||r.st==='shallow';}).length;
			return bad?{err:'Claude’s route still crosses '+(rs.some(function(r){return r.st==='land';})?'land':'shoal water')+'. Add a waypoint by hand.'}:{pts:pts,note:j.note||''};});
	}).catch(function(){return {err:'Couldn’t reach Claude just now.'};});
}
/* fix one bad leg: grid search, a wider search, then Claude */
function swFixLeg(a,b,v,why){
	var nm=distNM(a.lat,a.lon,b.lat,b.lon),z=swZoom(nm),lastG=null;
	function tryPad(pad,zz){return swGrid(a,b,pad,v,zz).then(function(G){if(!G)return null;lastG=G;return swPath(G,a,b,0.05)||swPath(G,a,b,0.025);});}
	return tryPad(Math.max(1.2,0.35*nm),z)
		.then(function(p){return p||tryPad(Math.max(4,1.0*nm),Math.max(11,z-1));})
		.then(function(p){
			if(p){var seq=[a].concat(p.map(function(q){return {lat:q[0],lon:q[1]};}),[b]);
				return Promise.all(seq.slice(1).map(function(q,i){return swCheckLeg(seq[i],q,v);})).then(function(rs){
					if(rs.every(function(r){return r.st==='ok'||r.st==='skip';}))return {pts:p,src:'auto'};
					return swAskClaude(a,b,lastG,v,why).then(function(c){return c.pts?{pts:c.pts,src:'claude',note:c.note}:{err:c.err};});});}
			return swAskClaude(a,b,lastG,v,why).then(function(c){return c.pts?{pts:c.pts,src:'claude',note:c.note}:{err:c.err};});
		});
}
function swAutoroute(){
	if(SW.fix&&SW.fix.busy)return;
	var r=SW.res,v=swVar();
	/* the leg check is still loading chart tiles (a long route takes a while the first time): say so, and go on when it's done */
	if(!r||SW.running||SW.key!==swKey()){SW.wantAuto=true;SW.fix={busy:true,items:[],msg:'',checking:true};swPanel();if(!SW.running)swRun();return;}
	if(!r.n){SW.fix={busy:false,items:[],msg:'No land or water under '+r.ft+' ft on your legs (draft '+draftTxt(boatDraft())+' + '+SAFE_MARGIN+' ft). Legs outside the chart area, from Chesapeake Bay to Cape Cod, aren’t checked.'};swPanel();return;}
	SW.fix={busy:true,items:[],msg:''};swPanel();
	var legs=swLegs(),jobs=[];
	pins.forEach(function(p){if(r.pins[p.id]===2)jobs.push(swNearestWater(p.lat,p.lon,v).then(function(ll){
		return ll?{kind:'move',pin:p,to:ll,why:'Point '+(pins.indexOf(p)+1)+' is on land: move it to the nearest clear water',st:null}:{kind:'none',why:'Point '+(pins.indexOf(p)+1)+' is on land and there’s no clear water close by. Move it by hand.'};}));});
	legs.forEach(function(l){var c=r.legs[l.toId];if(!c||(c.st!=='land'&&c.st!=='shallow'))return;
		if(r.pins[l.b.id]===2||(l.a.id&&r.pins[l.a.id]===2))return;   /* fix the point first */
		var why=c.st==='land'?'crosses land':'crosses water under '+r.ft+' ft';
		var lbl='Leg '+(l.a.id==='o'?'from you':pins.indexOf(l.a)+1)+'→'+(pins.indexOf(l.b)+1);
		jobs.push(swFixLeg(l.a,l.b,v,why).then(function(f){
			if(!f.pts)return {kind:'none',why:lbl+' '+why+'. '+(f.err||'No way round found.')};
			var extra=0,seq=[[l.a.lat,l.a.lon]].concat(f.pts,[[l.b.lat,l.b.lon]]);for(var i=1;i<seq.length;i++)extra+=distNM(seq[i-1][0],seq[i-1][1],seq[i][0],seq[i][1]);
			extra-=distNM(l.a.lat,l.a.lon,l.b.lat,l.b.lon);
			return {kind:'add',before:l.b,from:l.a,pts:f.pts,src:f.src,st:null,
				why:lbl+' '+why+': add '+f.pts.length+' waypoint'+(f.pts.length>1?'s':'')+' round it (+'+extra.toFixed(1)+' nm)'+(f.src==='claude'?' · suggested by Claude'+(f.note?': '+f.note:''):'')};}));});
	Promise.all(jobs).then(function(items){SW.fix={busy:false,items:items,msg:''};swPanel();swPrev();});
}
function swPrev(){
	SW.prev.clearLayers();var F=SW.fix;if(!F)return;
	F.items.forEach(function(it){if(it.st===false)return;
		if(it.kind==='add'){var seq=[[it.from.lat,it.from.lon]].concat(it.pts,[[it.before.lat,it.before.lon]]);
			L.polyline(seq,{color:'#8e44ad',weight:it.st?4:2.5,opacity:.9,dashArray:it.st?null:'6 5',interactive:false}).addTo(SW.prev);
			it.pts.forEach(function(p){L.circleMarker(p,{radius:5,color:'#8e44ad',weight:2,fillColor:'#fff',fillOpacity:1,interactive:false}).addTo(SW.prev);});}
		else if(it.kind==='move'){L.polyline([[it.pin.lat,it.pin.lon],it.to],{color:'#8e44ad',weight:2,dashArray:'3 4',interactive:false}).addTo(SW.prev);
			L.circleMarker(it.to,{radius:6,color:'#8e44ad',weight:2,fillColor:'#fff',fillOpacity:1,interactive:false}).addTo(SW.prev);}});
}
function swPanel(){
	var el=document.getElementById('arPanel');if(!el)return;var F=SW.fix;
	if(!F){el.hidden=true;el.innerHTML='';return;}
	el.hidden=false;var h='<div class="ar-h"><b>Autoroute</b><button type="button" class="ar-x" data-ar="x" aria-label="Close Autoroute">&times;</button></div>';
	if(F.busy)h+='<p class="ar-note"><span class="pc-spin" aria-hidden="true"></span>'+(F.checking?'Checking your legs against the chart&hellip; (the first time on a long route takes up to a minute)':'Finding a way round&hellip;')+'</p>';
	else if(F.msg)h+='<p class="ar-note">'+pEsc(F.msg)+'</p>';
	else{var nOk=0;h+='<ol class="ar-list">';
		F.items.forEach(function(it,k){if(it.st===true)nOk++;
			h+='<li class="'+(it.st===true?'ok':it.st===false?'no':'')+'"><span>'+pEsc(it.why)+'</span>'
				+(it.kind==='none'?'':'<span class="pc-yn"><button type="button" class="pc-y'+(it.st===true?' on':'')+'" data-ara="'+k+'" aria-pressed="'+(it.st===true)+'">✓ Approve</button><button type="button" class="pc-n'+(it.st===false?' on':'')+'" data-arr="'+k+'" aria-pressed="'+(it.st===false)+'">✕ Reject</button></span>')+'</li>';});
		h+='</ol><p class="ar-note">Purple on the chart. Checked against land and the '+safeFt()+' ft depth contour (draft '+draftTxt(boatDraft())+' + '+SAFE_MARGIN+' ft; set My draft in Layers). Rocks, wrecks and buoys aren’t checked: look at the chart. Not for navigation.</p>'
			+'<div class="pc-acts"><button type="button" class="pc-btn pri" data-ar="ok"'+(nOk?'':' disabled')+'>Apply '+(nOk?nOk+' approved':'approved')+'</button><button type="button" class="pc-btn" data-ar="x">Dismiss</button></div>';}
	el.innerHTML=h;
}
function swApply(){
	var F=SW.fix;if(!F)return;SW.fix=null;SW.prev.clearLayers();
	F.items.forEach(function(it){if(it.st!==true)return;
		if(it.kind==='move'&&!it.pin._gone)movePin(it.pin,it.to[0],it.to[1],it.pin.name);
		else if(it.kind==='add'&&!it.before._gone){it.pts.forEach(function(p){addPin(p[0],p[1],{at:pins.indexOf(it.before)});});}});
	swPanel();swSchedule();
}
document.addEventListener('click',function(e){
	var t=e.target.closest&&e.target.closest('#arPanel [data-ar],#arPanel [data-ara],#arPanel [data-arr],#rbAuto');if(!t)return;
	if(t.id==='rbAuto'){swAutoroute();return;}
	var F=SW.fix;
	if(t.hasAttribute('data-ar')){if(t.getAttribute('data-ar')==='ok')swApply();else{SW.fix=null;SW.prev.clearLayers();swPanel();}return;}
	if(!F)return;var k=+(t.getAttribute('data-ara')||t.getAttribute('data-arr')),it=F.items[k];if(!it)return;
	var v=t.hasAttribute('data-ara');it.st=(it.st===v)?null:v;swPanel();swPrev();
});
/* Draft (Layers): saved in this browser; changes the depth the legs are checked against */
(function(){var inp=document.getElementById('boatDraft');if(!inp)return;
	function note(){var n=document.getElementById('draftNote');if(n)n.textContent='Legs are checked for land and water under '+safeFt()+' ft (draft + '+SAFE_MARGIN+' ft, next charted depth).';}
	inp.value=draftTxt(boatDraft());note();
	inp.addEventListener('change',function(){var d=parseDraft(inp.value);if(!isFinite(d)||d<1||d>25){inp.value=draftTxt(boatDraft());return;}
		inp.value=draftTxt(d);try{localStorage.setItem(DRAFT_KEY,String(+d.toFixed(3)));}catch(e){}note();SW.key='';SW.fix=null;SW.prev.clearLayers();swPanel();swSchedule();});})();

/* ---------- waypoint table: the pins' cards as rows beside the map ----------
   Each row shows the same reading as that pin's card (pinReading at the slider's
   frame), re-rendered wherever the cards are. The table appears with the first
   point; the map is resized when it does. A row click centres the map on that
   point; the row's ✕ removes it. */
var wptShown=false;
function wpRow(pin,i,ti){
	/* Kept to distance, wind (+ air temp) and seas so the column stays narrow; position and
	   pressure remain on the point's map card (and in the CSV export). */
	var s=pinReading(pin.lat,pin.lon,ti,pin.fc),lg=legInfo(pin);
	var prev=(i===0)?routeOrigin():pins[i-1]; /* Pt 1 is the start unless the route runs from your boat */
	var dist,pe=passEtaFor(pin);
	if(!prev)dist='<span class="v">Start</span><span class="s">'+pin.lat.toFixed(3)+', '+pin.lon.toFixed(3)+'</span>'+(pe?'<span class="s eta">Depart '+pEsc(pWhen(pe))+'</span>':'');
	else{var lb=magBrg(prev.lat,prev.lon,pin.lat,pin.lon),tw=legTWA([prev.lat,prev.lon],[pin.lat,pin.lon]); /* leg course (magnetic) + wind angle from the previous point */
		var pl=passLegFor(pin);if(pl)tw=null;   /* a current passage run's leg replaces the slider-hour wind angle */
		dist='<span class="v">'+lb+'°M</span><span class="s">'+(i===0?lg.dist.toFixed(1)+' nm from you':'leg '+lg.dist.toFixed(1)+' · total '+cumFromStart(pin).toFixed(1))+'</span>'
			+(tw?'<span class="s twa">TWA '+Math.round(tw.twa)+'°'+(tw.side?' '+tw.side:'')+'</span>':'');
		if(pl){var mt=passMotorTag(pl);
			dist+='<span class="s pass">'+(pl.twa!=null?'TWA '+Math.round(pl.twa)+'° · ':'')+pEsc(pl.pos)+' '+pl.bsp.toFixed(1)+' kt'+(mt?' · '+(mt==='MOTOR-SAIL'?'motor':mt.toLowerCase()):'')+'</span>'
				+'<span class="s pass">'+(pl.seaMax!=null?'seas '+Math.round(pl.seaMax)+' ft · ':'')+(function(c){var t=c.easy+c.lively+c.rough;return t?Math.round(100*c.easy/t)+'% easy'+(c.rough>0.5?' · '+Math.round(c.rough)+' h rough':''):'';})(pl.cf)+'</span>';}
		if(pe)dist+='<span class="s eta pass">Passage ETA '+pEsc(pWhen(pe))+'</span>';
		var pl=pe?null:planLeg(WPPLAN,i);if(pl)dist+='<span class="s eta">'+pl.v.toFixed(1)+' kt in '+Math.round(pl.kt)+' kt wind'+(pl.ft>0.5?', '+pl.ft.toFixed(1)+' ft seas':'')+(pl.sea>=0.03?' (&minus;'+Math.round(pl.sea*100)+'%)':'')+' &middot; ETA '+etaText(pl.eta)+'</span>';}
	var wind='<span class="s">'+(pin._fcWait?'Loading&hellip;':'No data')+'</span>',sea='';
	if(s){
		wind='<span class="v">'+Math.round(s.kt)+' kt '+card(s.dir)+'</span><span class="s">'+(s.gust!=null?'g'+Math.round(s.gust)+' · ':'')+Math.round(s.dir)+'°</span>'
			+(s.tempF!=null?'<span class="s">'+Math.round(s.tempF)+'°F temp</span>':''); /* air temp, labelled like the map card */
		var pc=(s.pop!=null)?Math.round(s.pop)+'% rain':(s.precip!=null?s.precip.toFixed(2)+'" rain':'—'); /* " not " in": fits 260px */
		sea='<span class="v">'+(s.waveFt!=null?s.waveFt.toFixed(1)+' ft'+(s.wavePer!=null?' · '+Math.round(s.wavePer)+' s':''):'—')+'</span><span class="s">'+pc+'</span>';
	}
	if(pin.name)dist='<span class="s nm">'+pEsc(pin.name)+'</span>'+dist;
	dist+=swNote(pin,'s')+depNote(pin,'s dep');
	return '<tr data-wp="'+pin.id+'"><td class="n" title="Drag the row to change the order"><span>'+wpBadge(i)+'</span></td><td class="dist">'+dist+'</td><td class="wind">'+wind+'</td><td class="sea">'+sea+'</td>'
		+'<td class="x"><button type="button" data-wpx="'+pin.id+'" title="Remove point" aria-label="Remove point '+wpBadge(i)+'">&#10005;</button></td></tr>';
}
var wpDrag=null;   /* a row being dragged to a new place in the route (see below) */
var WPPLAN=null;
function renderWpTable(){
	var grid=document.getElementById('mapGrid'),body=document.getElementById('wptBody');if(!grid||!body)return;
	if(wpDrag)return;   /* don't rebuild the rows mid-drag (GPS fixes re-render the table) */
	var on=pins.length>0,ti=curTi,h='';
	WPPLAN=on?routePlan():null;
	for(var i=0;i<pins.length;i++)h+=wpRow(pins[i],i,ti);
	updateRouteBar(); /* the ETA follows the slider's departure hour */
	body.innerHTML=h;
	var f=(on&&TIMES[ti]!=null)?fmt(TIMES[ti]):null;
	document.getElementById('wptFoot').textContent=f?('Forecast for '+f.big+', '+f.small+'. Move the time slider to update.'):'';
	if(on!==wptShown){wptShown=on;grid.classList.toggle('has-wp',on);wpPlace();
		setTimeout(function(){try{map.invalidateSize({pan:false});drawGraticule();}catch(e){}},0);}
	wpToggleLabel();
	wpFit();
}
/* Hide/Show table, at every width */
function wpToggleLabel(){var tab=document.getElementById('wpTab'),b=document.getElementById('rbToggle');if(!tab||!b)return;
	var c=tab.classList.contains('collapsed');b.textContent=c?('Show table ('+pins.length+')'):'Hide';b.setAttribute('aria-expanded',c?'false':'true');}
/* Phones: size the list to exactly its first two rows (it scrolls for the rest).
   Desktop: no cap — the side column runs the map's height. */
var WP_NARROW=window.matchMedia('(max-width:900px)');
function wpFit(){
	var wrap=document.querySelector('#oycwm .wpt-wrap');if(!wrap)return;
	if(!WP_NARROW.matches){wrap.style.maxHeight='';return;}
	var rows=document.querySelectorAll('#wptBody tr'),h=0;
	for(var i=0;i<rows.length&&i<2;i++)h+=rows[i].getBoundingClientRect().height;
	wrap.style.maxHeight=h?Math.ceil(h)+'px':'';
}
/* Phones: the table lives under the time slider (between it and the map), so it
   never covers the chart. wpPlace moves the one table element there and back
   (same element, so every handler keeps working). The map keeps its full size. */
var WP_BELOW=window.matchMedia('(max-width:760px)');
function wpPlace(){
	var tab=document.getElementById('wpTab'),grid=document.getElementById('mapGrid');if(!tab||!grid)return;
	var below=WP_BELOW.matches;
	if(below&&tab.parentNode===grid)grid.parentNode.insertBefore(tab,grid);
	else if(!below&&tab.parentNode!==grid)grid.appendChild(tab);
	tab.classList.toggle('wp-below',below);
	tab.classList.toggle('wp-on',grid.classList.contains('has-wp'));
}
wpPlace();
if(WP_BELOW.addEventListener)WP_BELOW.addEventListener('change',function(){wpPlace();wpFit();});
else if(WP_BELOW.addListener)WP_BELOW.addListener(function(){wpPlace();wpFit();});
window.addEventListener('resize',wpFit);
(function(){var b=document.getElementById('rbToggle'),tab=document.getElementById('wpTab');if(!b||!tab)return;
	b.addEventListener('click',function(){
		tab.classList.toggle('collapsed');wpToggleLabel();wpFit();
		/* desktop: the side column comes and goes, so the map's width changes */
		setTimeout(function(){try{map.invalidateSize({pan:false});drawGraticule();}catch(e){}},0);
	});})();
(function(){
	var tab=document.getElementById('wpTab');if(!tab)return;
	tab.addEventListener('click',function(e){
		var x=e.target.closest?e.target.closest('[data-wpx]'):null,i;
		if(x){e.preventDefault();var id=+x.getAttribute('data-wpx');for(i=0;i<pins.length;i++){if(pins[i].id===id){removePin(pins[i]);break;}}return;}
		var tr=e.target.closest?e.target.closest('tr[data-wp]'):null;
		if(tr){var id2=+tr.getAttribute('data-wp');for(i=0;i<pins.length;i++){if(pins[i].id===id2){focusPin(pins[i]);break;}}}
	});
	/* centre on the tapped waypoint and zoom in to harbour scale (zoom 11) if the
	   map is further out — never zoom out, never all the way in. Where the table
	   floats over the map (phones, full screen), centre in the uncovered part. */
	function focusPin(p){
		var z=Math.min(Math.max(map.getZoom(),11),map.getMaxZoom()||18),dx=0,dy=0;
		try{
			var mr=map.getContainer().getBoundingClientRect(),tr=tab.getBoundingClientRect();
			/* table floating over the map's right side: centre in the larger free
			   area, either the strip to its left or the strip below it */
			/* only the part of the map actually on screen counts (page scroll, the
			   phone's fixed bottom tab bar) */
			var top=Math.max(mr.top,0),bot=Math.min(mr.bottom,window.innerHeight),
			    bar=document.querySelector('.wx-tabs');
			if(bar&&getComputedStyle(bar).position==='fixed')bot=Math.min(bot,bar.getBoundingClientRect().top);
			var cy=(mr.top+mr.bottom)/2,fx=(mr.left+mr.right)/2,fy=(top+bot)/2;
			if(tr.width&&tr.left>mr.left&&tr.left<mr.right&&tr.top<bot&&tr.bottom>top){
				var leftW=tr.left-mr.left,belowH=Math.max(0,bot-tr.bottom);
				if(leftW*(bot-top)>=belowH*mr.width)fx=(mr.left+tr.left)/2;
				else fy=(tr.bottom+bot)/2;
			}
			dx=mr.left+mr.width/2-fx;dy=cy-fy;
		}catch(e){}
		var pt=map.project([p.lat,p.lon],z).add([dx,dy]);
		map.setView(map.unproject(pt,z),z,{animate:true});
	}
})();
/* Reorder: grab a waypoint row (anywhere but its ✕) and drag it up or down; the
   other rows slide aside, and on release the point moves to that place in the
   route (legs, totals, wind angles and the map numbering all follow).
   Mouse/pen: the drag starts once you move 5 px (a plain click still centres the
   map on the point). Touch: press the row's number and drag, or press and hold
   anywhere on the row for a moment, so a normal swipe still scrolls the list. */
(function(){
	var body=document.getElementById('wptBody');if(!body)return;
	var pend=null,holdT=null,lastEnd=0;
	function rowsOf(){return [].slice.call(body.querySelectorAll('tr[data-wp]'));}
	function start(tr,y,id){
		var rows=rowsOf();if(rows.length<2||rows.indexOf(tr)<0)return false;
		var wrap=body.closest('.wpt-wrap');
		wpDrag={tr:tr,rows:rows,rects:rows.map(function(r){return r.getBoundingClientRect();}),from:rows.indexOf(tr),to:rows.indexOf(tr),y0:y,id:id,wrap:wrap,s0:wrap?wrap.scrollTop:0,
			smax:wrap?wrap.scrollHeight-wrap.clientHeight:0};   /* measured before the dragged row's transform adds overflow */
		tr.classList.add('wp-drag');rows.forEach(function(r){if(r!==tr)r.classList.add('wp-shift');});
		try{tr.setPointerCapture(id);}catch(_){}
		if(navigator.vibrate)try{navigator.vibrate(15);}catch(_){}
		return true;
	}
	function clearPend(){pend=null;if(holdT){clearTimeout(holdT);holdT=null;}}
	body.addEventListener('pointerdown',function(e){
		if(e.button>0||wpDrag)return;
		if(e.target.closest&&e.target.closest('button,[data-wpx]'))return;   /* the ✕ stays a remove button */
		var tr=e.target.closest?e.target.closest('tr[data-wp]'):null;if(!tr)return;
		var onNum=!!e.target.closest('td.n');
		clearPend();
		if(e.pointerType==='touch'&&onNum){e.preventDefault();start(tr,e.clientY,e.pointerId);return;}
		pend={tr:tr,x:e.clientX,y:e.clientY,id:e.pointerId,touch:e.pointerType==='touch'};
		if(pend.touch)holdT=setTimeout(function(){if(pend){var q=pend;clearPend();start(q.tr,q.y,q.id);}},350);   /* press and hold */
	});
	body.addEventListener('pointermove',function(e){
		if(pend&&e.pointerId===pend.id){
			var mv=Math.hypot(e.clientX-pend.x,e.clientY-pend.y);
			if(pend.touch){if(mv>8)clearPend();return;}           /* moved before the hold: it's a scroll */
			if(mv>5){var q=pend;clearPend();start(q.tr,q.y,q.id);}else return;
		}
		var d=wpDrag;if(!d||e.pointerId!==d.id)return;e.preventDefault();
		/* phones show two rows and scroll the rest: holding near the list's top or
		   bottom edge scrolls it, and positions are measured in list coordinates */
		if(d.wrap&&d.smax>2){var wr=d.wrap.getBoundingClientRect();
			if(e.clientY>wr.bottom-24)d.wrap.scrollTop=Math.min(d.smax,d.wrap.scrollTop+10);else if(e.clientY<wr.top+24)d.wrap.scrollTop=Math.max(0,d.wrap.scrollTop-10);}
		var ds=d.wrap?d.wrap.scrollTop-d.s0:0,dy=e.clientY-d.y0+ds,R0=d.rects[d.from],cy=R0.top+R0.height/2+dy,to=0;
		d.tr.style.transform='translateY('+dy+'px)';
		for(var i=0;i<d.rects.length;i++){if(i!==d.from&&cy>d.rects[i].top+d.rects[i].height/2)to++;}
		d.to=to;
		for(i=0;i<d.rows.length;i++){if(i===d.from)continue;var sh=0;
			if(d.from<to&&i>d.from&&i<=to)sh=-R0.height;else if(d.from>to&&i>=to&&i<d.from)sh=R0.height;
			d.rows[i].style.transform=sh?'translateY('+sh+'px)':'';}
	});
	/* once a touch drag is live, stop the browser from scrolling the list/page */
	body.addEventListener('touchmove',function(e){if(wpDrag)e.preventDefault();},{passive:false});
	function end(e){
		if(pend&&(!e||e.pointerId===pend.id))clearPend();
		var d=wpDrag;if(!d||(e&&e.pointerId!==d.id))return;wpDrag=null;
		d.rows.forEach(function(r){r.style.transform='';r.classList.remove('wp-drag','wp-shift');});
		lastEnd=Date.now();
		if(d.to!==d.from){var p=pins.splice(d.from,1)[0];pins.splice(d.to,0,p);nextWp=0;refreshRoute();}
		else renderWpTable();
	}
	body.addEventListener('pointerup',end);body.addEventListener('pointercancel',end);
	body.addEventListener('lostpointercapture',end);
	/* the click that ends a drag shouldn't also pan the map; nor the long-press menu */
	body.addEventListener('click',function(e){if(Date.now()-lastEnd<400)e.stopPropagation();},true);
	body.addEventListener('contextmenu',function(e){if(wpDrag||pend)e.preventDefault();});
})();
map.on('click',function(e){if(typeof passPick==='function'&&passPick(e))return;/* passage brief start/finish pick */if(mode==='radar')return;var rc=document.getElementById('tgRace');if(rc&&rc.checked)return;/* Race on → map taps don't drop waypoints */addPin(e.latlng.lat,((e.latlng.lng+540)%360)-180);});
/* explicit "✕ Remove" in a callout (event-delegated so it survives popup re-renders) */
map.getContainer().addEventListener('click',function(e){
	var btn=e.target.closest?e.target.closest('.co-x[data-wp]'):null;
	if(!btn)return;
	e.preventDefault();e.stopPropagation();
	var id=+btn.getAttribute('data-wp');
	for(var i=0;i<pins.length;i++){if(pins[i].id===id){removePin(pins[i]);break;}}
},true);

/* ---------- route export: GPX (any chartplotter) + CSV forecast sheet ----------
   One-click save of the finalized route. GPX carries the waypoints both as
   standalone <wpt>s and as an ordered <rte>, with the forecast for each point
   embedded in <desc> so it rides into the plotter as a waypoint note. The CSV
   is the same forecast as a spreadsheet for planning. Forecast is taken at the
   frame the time slider is on (labelled in the files); on the Radar tab it
   falls back to the nearest-to-now forecast frame. */
function exportTi(){var t=(mode==='radar')?NOWI:curTi;return Math.max(0,Math.min((TIMES.length||1)-1,t||0));}
function xmlEsc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function fcText(s){
	if(!s)return 'No forecast data at this point.';
	var p=[Math.round(s.kt)+' kt '+card(s.dir)+' ('+Math.round(s.dir)+'°)'+(s.gust!=null?' g'+Math.round(s.gust):'')];
	if(s.mb!=null)p.push(Math.round(s.mb)+' mb');
	if(s.tempF!=null)p.push(Math.round(s.tempF)+'°F');
	if(s.waveFt!=null)p.push(s.waveFt.toFixed(1)+' ft seas'+(s.wavePer!=null?' ('+Math.round(s.wavePer)+' s)':''));
	if(s.pop!=null)p.push(Math.round(s.pop)+'% precip');else if(s.precip!=null)p.push(s.precip.toFixed(2)+' in precip');
	return p.join(' · ');
}
function routePoints(){
	/* Your boat first when the route runs from it (matches the drawn route line), then Pt 1…Pt n. */
	var ti=exportTi(),o=routeOrigin(),list=o?[{name:'My boat',lat:o.lat,lon:o.lon,leg:0,cum:0,origin:true,s:pinReading(o.lat,o.lon,ti,null)}]:[],cum=0;
	for(var i=0;i<pins.length;i++){
		var lg=legInfo(pins[i]);
		if(i>0)cum+=distNM(pins[i-1].lat,pins[i-1].lon,pins[i].lat,pins[i].lon);else cum=lg.dist;
		list.push({name:lg.label,lat:pins[i].lat,lon:pins[i].lon,leg:lg.dist,legFrom:lg.from,cum:cum,s:pinReading(pins[i].lat,pins[i].lon,ti,pins[i].fc)});
	}
	return list;
}
function stamp(){var d=new Date();function z(n){return (n<10?'0':'')+n;}return ''+d.getFullYear()+z(d.getMonth()+1)+z(d.getDate())+'-'+z(d.getHours())+z(d.getMinutes());}
function validLabel(){var ti=exportTi(),v=(TIMES[ti]!=null)?new Date(TIMES[ti]):new Date();return v.toLocaleString('en-US',{weekday:'short',month:'short',day:'numeric',hour:'numeric',minute:'2-digit'});}
function buildGPX(){
	var vlbl=validLabel(),pts=routePoints(),nm='OYC Passage '+stamp(),wpt='',rtept='';
	for(var i=0;i<pts.length;i++){
		var pt=pts[i],
			desc=xmlEsc(fcText(pt.s)+' — valid '+vlbl+((pt.leg!=null&&!pt.origin&&pt.legFrom)?(' — '+pt.leg.toFixed(1)+' nm from '+pt.legFrom):'')),
			lat=pt.lat.toFixed(6),lon=pt.lon.toFixed(6),name=xmlEsc(pt.name);
		wpt+='  <wpt lat="'+lat+'" lon="'+lon+'">\n    <name>'+name+'</name>\n    <desc>'+desc+'</desc>\n    <sym>'+(pt.origin?'Anchor':'Waypoint')+'</sym>\n  </wpt>\n';
		rtept+='    <rtept lat="'+lat+'" lon="'+lon+'">\n      <name>'+name+'</name>\n      <desc>'+desc+'</desc>\n    </rtept>\n';
	}
	/* NB: build the XML prolog token by concatenation so the literal open-tag
	   sequence never appears in this PHP template source. On a host with
	   short_open_tag=On it would be parsed as a PHP open tag and fatal the whole
	   site at include time (functions.php require_once's this file globally). */
	return '<'+'?xml version="1.0" encoding="UTF-8"?'+'>\n'
		+'<gpx version="1.1" creator="Orienta Yacht Club — Harbor Conditions" xmlns="http://www.topografix.com/GPX/1/1" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd">\n'
		+'  <metadata>\n    <name>'+xmlEsc(nm)+'</name>\n    <desc>'+xmlEsc('Passage route from OYC Harbor Conditions. Forecast valid '+vlbl+'. Wind/seas are a planning forecast, not observed — verify before departure.')+'</desc>\n    <time>'+new Date().toISOString()+'</time>\n  </metadata>\n'
		+wpt+'  <rte>\n    <name>'+xmlEsc(nm)+'</name>\n'+rtept+'  </rte>\n</gpx>\n';
}
function csvCell(v){v=(v==null?'':String(v));return /[",\n]/.test(v)?'"'+v.replace(/"/g,'""')+'"':v;}
function buildCSV(){
	var pts=routePoints(),rows=['OYC Passage Route — forecast valid '+validLabel(),
		['Point','Latitude','Longitude','Leg (nm)','Cumulative from start (nm)','Wind (kt)','Gust (kt)','Dir','Bearing (deg)','Pressure (mb)','Air temp (F)','Seas (ft)','Wave period (s)'].map(csvCell).join(',')];
	for(var i=0;i<pts.length;i++){
		var pt=pts[i],s=pt.s||{};
		rows.push([pt.name,pt.lat.toFixed(6),pt.lon.toFixed(6),
			pt.origin?'':pt.leg.toFixed(1),pt.origin?'':pt.cum.toFixed(1),
			s.kt!=null?Math.round(s.kt):'',s.gust!=null?Math.round(s.gust):'',
			s.dir!=null?card(s.dir):'',s.dir!=null?Math.round(s.dir):'',
			s.mb!=null?Math.round(s.mb):'',s.tempF!=null?Math.round(s.tempF):'',
			s.waveFt!=null?s.waveFt.toFixed(1):'',s.wavePer!=null?Math.round(s.wavePer):''
		].map(csvCell).join(','));
	}
	return rows.join('\r\n')+'\r\n';
}
function dlFile(name,mime,text){
	try{var url=URL.createObjectURL(new Blob([text],{type:mime})),a=document.createElement('a');
		a.href=url;a.download=name;a.style.display='none';document.body.appendChild(a);a.click();
		setTimeout(function(){document.body.removeChild(a);URL.revokeObjectURL(url);},2000);
	}catch(e){}
}
function exportRoute(){
	if(!pins.length)return;
	var base='OYC-passage-'+stamp();
	dlFile(base+'.gpx','application/gpx+xml',buildGPX());
	setTimeout(function(){dlFile(base+'.csv','text/csv',buildCSV());},500);
}
/* ---------- Race course builder (OYC marks A–J, Sailing Instructions Table 3) ----------
   COORDS ARE BEST-READ FROM THE CHART PHOTO — VERIFY against the NOAA marks and
   correct any (each is one line). Enter a course as a letter string like the RC
   board (e.g. "A F C A"); it overlays the chart with each leg's magnetic bearing
   + distance and the total. */
var MARKS={
	// Positions least-squares fit to the Table 4 distance/bearing grid, anchored
	// on A at its NOAA-charted G"1" can position. Every leg reproduces Table 4
	// within 0.04 nm / 2 deg. See tools/race-marks-fit.js for the solve.
	A:{lat:40.92220,lon:-73.72070,buoy:'G "1" can',loc:'Off Delancey Pt, Larchmont (start/finish)'},
	B:{lat:40.92439,lon:-73.70313,buoy:'Fl R "42" bell',loc:'SSW Peningo Pt'},
	C:{lat:40.93487,lon:-73.68891,buoy:'R "40A" nun',loc:'Parsonage Pt / Porgy Shoal'},
	D:{lat:40.90888,lon:-73.63743,buoy:'Fl G "21" gong',loc:'N Matinecock Pt'},
	E:{lat:40.87798,lon:-73.65918,buoy:'G "1" can',loc:'N Weeks Pt'},
	F:{lat:40.86023,lon:-73.67562,buoy:'R "6" bell',loc:'Picket Rock, Hempstead Hbr entr'},
	G:{lat:40.87967,lon:-73.72161,buoy:'Fl G "23" gong',loc:'N Prospect Pt'},
	H:{lat:40.86977,lon:-73.73896,buoy:'Fl G "25" can',loc:'NW Sands Pt'},
	I:{lat:40.89153,lon:-73.73720,buoy:'G "1" can',loc:'N Execution Rocks'},
	J:{lat:40.90333,lon:-73.73931,buoy:'Fl R "2"',loc:'Hens & Chickens'}
};
var raceMarkLayer=L.layerGroup(), raceCourseLayer=L.layerGroup(), raceStr='';
/* dragged-aside leg labels: per-leg {dlat,dlon} offset from the leg midpoint, so a
   label you move stays put while the time slider re-plots. Reset when the course
   marks change (raceSeqKey). */
var raceLegOff={}, raceSeqKey='', legDragLine=null, lastLegTap={t:0,i:-1};
var rsMin={}, boatMin=false;   /* collapsed state: rsMin keyed by leg index, boatMin for the Underway card */
function raceLeader(mid,pos){return L.polyline([mid,pos],{color:'#7a3d00',weight:1,opacity:.55,dashArray:'3 3',interactive:false});}
/* make one leg label draggable (pointer events on its visible boxes only, so the
   map still pans through the gaps); a thin leader ties it to the leg, and a
   double-tap snaps it back to the midpoint — mirrors the waypoint-card behaviour. */
function makeLegDraggable(m,i,mid){
	var el=m.getElement();if(!el)return;
	var wrap=el.querySelector('.rl-wrap');if(!wrap)return;
	var st=null;
	wrap.addEventListener('pointerdown',function(e){
		st={x:e.clientX,y:e.clientY,ll:m.getLatLng(),moved:false};
		try{wrap.setPointerCapture(e.pointerId);}catch(_){}
		el.classList.add('rl-grab');map.dragging.disable();e.preventDefault();e.stopPropagation();
	});
	wrap.addEventListener('pointermove',function(e){
		if(!st)return;
		var dx=e.clientX-st.x,dy=e.clientY-st.y;if(Math.abs(dx)+Math.abs(dy)>3)st.moved=true;
		var p=map.latLngToContainerPoint(st.ll),nll=map.containerPointToLatLng(L.point(p.x+dx,p.y+dy));
		m.setLatLng(nll);
		if(!legDragLine){legDragLine=raceLeader(mid,[nll.lat,nll.lng]).addTo(raceCourseLayer);}else legDragLine.setLatLngs([mid,[nll.lat,nll.lng]]);
	});
	function end(e){
		if(!st)return;
		el.classList.remove('rl-grab');map.dragging.enable();try{wrap.releasePointerCapture(e.pointerId);}catch(_){}
		if(st.moved){var ll=m.getLatLng();raceLegOff[i]=[ll.lat-mid[0],ll.lng-mid[1]];legDragLine=null;st=null;plotCourse(raceStr);return;}
		var now=Date.now();
		if(now-lastLegTap.t<350&&lastLegTap.i===i){delete raceLegOff[i];lastLegTap={t:0,i:-1};st=null;plotCourse(raceStr);return;}
		lastLegTap={t:now,i:i};st=null;
	}
	wrap.addEventListener('pointerup',end);wrap.addEventListener('pointercancel',end);
}
/* ---------- layline angles ----------
   How close the boat points (upwind, degrees off the true wind) and how deep it
   runs (the true wind angle it gybes at), from one of three methods the user
   picks in the Layline angles panel:
     type   boat type preset, fine-tuned ±1° (default Racer-cruiser 45° / 145°)
     polar  best-VMG angles from the racer's selected polars at the wind speed
            (race-tools.js sets OYCWM.polarAngles), plus an offset
     tacks  measured from the user's own tacks/gybes (half the change in COG)
   polar/tacks fall back to the boat type when they have nothing to go on.
   layAng(tws) -> {up, dn (off dead downwind), gybe (TWA), src}. */
var LAY_TYPES=[{k:'cruiser',n:'Cruiser',up:50,gybe:140},{k:'rc',n:'Racer-cruiser',up:45,gybe:145},{k:'perf',n:'Performance',up:42,gybe:150},{k:'sport',n:'Sport boat',up:40,gybe:155}];
var LAY={mode:'type',type:'rc',up:45,gybe:145,off:0,tk:[],gy:[]};
(function(){try{var j=JSON.parse(localStorage.getItem('oyc_lay')||'null');if(j&&typeof j==='object'){for(var k in LAY)if(j[k]!=null)LAY[k]=j[k];}
	else{var o=+localStorage.getItem('oyc_lay_tack');if(o>=30&&o<=60)LAY.up=o;}}catch(e){}})();
function laySave(){try{localStorage.setItem('oyc_lay',JSON.stringify(LAY));}catch(e){}}
function layMed(a){a=(a||[]).slice(-3).slice().sort(function(x,y){return x-y;});return a.length?a[Math.floor(a.length/2)]:null;}
function layPolarFn(){return (window.OYCWM&&typeof OYCWM.polarAngles==='function')?OYCWM.polarAngles:null;}
function layAng(tws){
	var up=LAY.up,gybe=LAY.gybe,src='boat type';
	if(LAY.mode==='polar'){var f=layPolarFn(),pa=f?f(tws!=null?tws:10):null;
		if(pa&&pa.up&&pa.gybe){up=pa.up+LAY.off;gybe=pa.gybe-LAY.off;src='polars';}else src='boat type (no polars chosen)';}
	else if(LAY.mode==='tacks'){var mu=layMed(LAY.tk),md=layMed(LAY.gy);
		if(mu!=null)up=mu;if(md!=null)gybe=180-md;
		src=(mu!=null||md!=null)?'your tacks'+(mu==null||md==null?' + boat type':''):'boat type (no tacks measured yet)';}
	up=Math.max(25,Math.min(65,up));gybe=Math.max(110,Math.min(180,gybe));
	return {up:up,dn:180-gybe,gybe:gybe,src:src};
}
/* tack/gybe detector: a steady course, then a steady course 50–150° away ~1 min
   later (the 30 s COG window smooths the turn), while moving at 1.5 kt or more */
var cogHist=[],lastTackT=0;
function cmean(a){var x=0,y=0;a.forEach(function(e){x+=Math.sin(e.c*Math.PI/180);y+=Math.cos(e.c*Math.PI/180);});var r=Math.sqrt(x*x+y*y)/a.length;return {m:((Math.atan2(x,y)*180/Math.PI)+360)%360,steady:r>0.99};}
function detectTack(cog,sog,lat,lon){
	var t=Date.now();if(cog==null||sog==null||sog<1.5){return;}
	cogHist.push({t:t,c:cog});while(cogHist.length&&t-cogHist[0].t>100000)cogHist.shift();
	if(t-lastTackT<60000)return;
	var bef=cogHist.filter(function(e){return t-e.t>=65000&&t-e.t<=95000;}),aft=cogHist.filter(function(e){return t-e.t<=15000;});
	if(bef.length<4||aft.length<4)return;
	var B=cmean(bef),A=cmean(aft);if(!B.steady||!A.steady)return;
	var diff=Math.abs(((A.m-B.m+540)%360)-180);if(diff<50||diff>150)return;
	var w=modelWind(lat,lon,t);if(!w)return;
	var twaB=twaFrom(w.twd,B.m,w.tws).twa,half=Math.round(diff/2*2)/2;
	if(twaB<90){if(half<25||half>65)return;LAY.tk.push(half);LAY.tk=LAY.tk.slice(-5);}
	else{if(half>60)return;LAY.gy.push(half);LAY.gy=LAY.gy.slice(-5);}
	lastTackT=t;laySave();layUI();if(LAY.mode==='tacks'){drawLaylines();replotRace();}
}
function layUI(){
	var b=document.getElementById('layBtn');if(!b)return;
	var w=(typeof map!=='undefined')?(function(){try{var c=map.getCenter();return modelWind(c.lat,c.lng,Date.now());}catch(e){return null;}})():null;
	var A=layAng(w?w.tws:null);
	b.textContent=Math.round(A.up)+'°';b.title='Layline angles: upwind '+Math.round(A.up)+'°, gybe at '+Math.round(A.gybe)+'° ('+A.src+')';
	document.querySelectorAll('#layPop [data-lm]').forEach(function(x){var on=x.getAttribute('data-lm')===LAY.mode;x.setAttribute('aria-pressed',on?'true':'false');});
	document.querySelectorAll('#layPop [data-lp]').forEach(function(x){x.hidden=x.getAttribute('data-lp')!==LAY.mode;});
	document.querySelectorAll('#lpTypes button').forEach(function(x){x.setAttribute('aria-pressed',x.getAttribute('data-k')===LAY.type?'true':'false');});
	document.getElementById('lpUp').textContent=LAY.up+'°';document.getElementById('lpGybe').textContent=LAY.gybe+'°';
	document.getElementById('lpOff').textContent=(LAY.off>=0?'+':'')+LAY.off+'°';
	var mu=layMed(LAY.tk),md=layMed(LAY.gy);
	document.getElementById('lpMeas').textContent=(mu!=null?'Upwind '+mu+'° from '+Math.min(3,LAY.tk.length)+' tack'+(LAY.tk.length>1?'s':''):'No tacks measured yet')+' · '+(md!=null?'gybe at '+(180-md)+'° from '+Math.min(3,LAY.gy.length)+' gybe'+(LAY.gy.length>1?'s':''):'no gybes yet');
	document.getElementById('lpNow').textContent='Using '+A.src+': upwind '+Math.round(A.up)+'°, gybe at '+Math.round(A.gybe)+'°'+(LAY.mode==='polar'&&A.src==='polars'&&w?' at '+Math.round(w.tws)+' kt':'');
}
function layChanged(){laySave();layUI();drawLaylines();replotRace();updateBoatInfo();}
function layInit(){
	var b=document.getElementById('layBtn'),P=document.getElementById('layPop');if(!b||!P)return;
	document.getElementById('lpTypes').innerHTML=LAY_TYPES.map(function(t){return '<button type="button" data-k="'+t.k+'" aria-pressed="false">'+t.n+' '+t.up+'°</button>';}).join('');
	function open(on){P.hidden=!on;b.setAttribute('aria-expanded',on?'true':'false');if(on)layUI();}
	b.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();open(P.hidden);});
	document.getElementById('lpClose').addEventListener('click',function(){open(false);});
	P.addEventListener('click',function(e){e.stopPropagation();});
	P.querySelectorAll('[data-lm]').forEach(function(x){x.addEventListener('click',function(){LAY.mode=x.getAttribute('data-lm');layChanged();});});
	P.querySelectorAll('#lpTypes button').forEach(function(x){x.addEventListener('click',function(){var t=LAY_TYPES.filter(function(y){return y.k===x.getAttribute('data-k');})[0];if(!t)return;LAY.type=t.k;LAY.up=t.up;LAY.gybe=t.gybe;layChanged();});});
	P.querySelectorAll('[data-ls]').forEach(function(x){var t1,t2,k=x.getAttribute('data-ls'),d=+x.getAttribute('data-d');
		function step(){if(k==='up')LAY.up=Math.max(30,Math.min(60,LAY.up+d));else if(k==='gybe')LAY.gybe=Math.max(120,Math.min(180,LAY.gybe+d));else LAY.off=Math.max(-8,Math.min(8,LAY.off+d));layChanged();}
		function stop(){clearTimeout(t1);clearInterval(t2);}
		x.addEventListener('pointerdown',function(e){e.preventDefault();step();t1=setTimeout(function(){t2=setInterval(step,110);},400);});
		['pointerup','pointerleave','pointercancel'].forEach(function(ev){x.addEventListener(ev,stop);});
		x.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();step();}});});
	document.getElementById('lpReset').addEventListener('click',function(){LAY.tk=[];LAY.gy=[];layChanged();});
	layUI();
}
function pad3(n){n=((Math.round(n)%360)+360)%360;return (n<10?'00':n<100?'0':'')+n;}
/* Nearest NOAA tidal-current station's set (true — the way it flows TO) and drift
   (kt) at the given epoch, or null (data not loaded, or no prediction within 3 h).
   The stations sit right in the race area (Execution Rks, Delancey Pt, Peningo,
   Matinecock), so nearest-station is a fair per-leg estimate. */
function currentAt(lat,lon,epoch){
	if(typeof CURDATA==='undefined'||!CURDATA)return null;
	var best=null,bd=1/0;
	for(var i=0;i<CURSTA.length;i++){var st=CURSTA[i],dd=(st.lat-lat)*(st.lat-lat)+(st.lon-lon)*(st.lon-lon);if(dd<bd){bd=dd;best=st;}}
	var arr=best&&CURDATA[best.id];if(!arr||!arr.length)return null;
	var bp=null,btd=1/0;for(var k=0;k<arr.length;k++){var td=Math.abs(arr[k].t-epoch);if(td<btd){btd=td;bp=arr[k];}}
	if(!bp||btd>3*36e5)return null;
	return {sp:Math.abs(bp.v),dir:(bp.v>=0)?bp.fl:bp.eb};
}
/* Full multi-line label for one race leg, for the current slider time:
   (1) course (°M) + rhumb distance, with the real beat/run distance appended when
       the rhumb line is inside the boat's pointing angle; (2) true wind angle +
       point of sail, or — on a beat/run — the two tack/gybe headings (laylines);
       (3) tidal set (°M) + drift (kt) and whether it helps (fair), hurts (foul) or
       pushes across the leg. Wind + current sampled at the leg midpoint. */
function legLabelHTML(a,b,d,mid){
	var course=brgTrue(a[0],a[1],b[0],b[1]),RAD=Math.PI/180;
	var l1='<b>'+magBrg(a[0],a[1],b[0],b[1])+'°M &middot; '+d.toFixed(1)+' nm',l2='',l3='';
	var r=(typeof readPoint==='function')?readPoint(mid[0],mid[1],curTi):null;
	if(r&&r.dir!=null){
		var rel=((r.dir-course+540)%360)-180,twa=Math.abs(rel),side=(twa<2||twa>178)?'':(rel>=0?'S':'P'),LA=layAng(r.kt);
		if(twa<LA.up){            /* can't lay the rhumb line — beat */
			l1+=' &middot; beat '+(d*Math.cos(twa*RAD)/Math.cos(LA.up*RAD)).toFixed(1);
			l2='<i>TWA '+Math.round(twa)+'° &middot; tacks '+pad3(r.dir+LA.up+MAGVAR)+'/'+pad3(r.dir-LA.up+MAGVAR)+'°M</i>';
		}else if(twa>180-LA.dn){  /* dead run — gybe downwind */
			var dw=r.dir+180;
			l1+=' &middot; run '+(d*Math.cos((180-twa)*RAD)/Math.cos(LA.dn*RAD)).toFixed(1);
			l2='<i>TWA '+Math.round(twa)+'° &middot; gybes '+pad3(dw+LA.dn+MAGVAR)+'/'+pad3(dw-LA.dn+MAGVAR)+'°M</i>';
		}else{
			var pos=twa<55?'close-hauled':twa<80?'reach':twa<110?'beam':'broad';
			l2='<i>TWA '+Math.round(twa)+'°'+side+' &middot; '+pos+'</i>';
		}
	}
	l1+='</b>';
	var cur=currentAt(mid[0],mid[1],curEpoch());
	if(cur){
		if(cur.sp<0.1){l3='<u>slack current</u>';}   /* show slack explicitly, don't hide the line */
		else{var al=cur.sp*Math.cos((((cur.dir-course+540)%360)-180)*RAD);
			l3='<u>set '+pad3(cur.dir+MAGVAR)+'°M &middot; '+cur.sp.toFixed(1)+' kt &middot; '+(Math.abs(al)<0.1?'cross':(al>0?'fair':'foul'))+'</u>';}
	}
	return l1+l2+l3;
}
/* re-plot the active race course (e.g. when the time slider moves, so TWA tracks
   the forecast hour) */
function replotRace(){var cb=document.getElementById('tgRace');if(cb&&cb.checked&&raceStr)plotCourse(raceStr);else drawLaylines();drawWindDial();}
/* Wind dials at the race marks while Race is on and Wind barbs are off: the
   forecast wind at each mark for the slider's hour, drawn up and to the right
   of the mark. Arrow points the way the wind blows; the text is where it blows
   FROM (°T, as forecasts give it). */
var raceWindLayer=L.layerGroup();
function drawWindDial(){
	raceWindLayer.clearLayers();
	var rc=document.getElementById('tgRace'),ba=document.getElementById('tgArrows');
	if(!rc||!rc.checked||(ba&&ba.checked)||mode==='radar'){if(map.hasLayer(raceWindLayer))map.removeLayer(raceWindLayer);return;}
	if(!map.hasLayer(raceWindLayer))raceWindLayer.addTo(map);
	/* the marks of the plotted course, else every mark */
	var seq=(raceStr||'').toUpperCase().replace(/[^A-J]/g,'').split('').filter(function(x,i,a){return MARKS[x]&&a.indexOf(x)===i;});
	if(seq.length<2)seq=Object.keys(MARKS);
	seq.forEach(function(k){var m=MARKS[k],r=null;try{r=readPoint(m.lat,m.lon,curTi);}catch(e){}
		if(!r||r.dir==null||r.kt==null)return;
		var to=Math.round((r.dir+180)%360);
		var html='<div class="wd" title="'+k+': forecast wind '+Math.round(r.kt)+' kt from '+pad3(r.dir)+'°T ('+pad3(r.dir+MAGVAR)+'°M)"><svg viewBox="-17 -17 34 34" aria-hidden="true"><circle class="wd-face" r="15.5" stroke-width="1"/>'
			+'<g transform="rotate('+to+')"><line class="wd-arrow" x1="0" y1="10" x2="0" y2="-5" stroke-width="2.4" stroke-linecap="round"/><polygon class="wd-arrow" points="0,-11.5 -4.5,-4 4.5,-4"/></g></svg>'
			+'<span class="wd-txt">'+Math.round(r.kt)+' kt '+pad3(r.dir)+'°T</span></div>';
		L.marker([m.lat,m.lon],{icon:L.divIcon({className:'wdial',html:html,iconSize:[64,50],iconAnchor:[-2,54]}),interactive:false,keyboard:false,zIndexOffset:720}).addTo(raceWindLayer);
	});
}
function drawRaceMarks(){
	raceMarkLayer.clearLayers();
	for(var k in MARKS){var m=MARKS[k];
		L.marker([m.lat,m.lon],{icon:L.divIcon({className:'race-mk',html:'<span class="race-ic">'+k+'</span>',iconSize:[0,0]}),title:k+' — '+m.buoy+' ('+m.loc+')',zIndexOffset:700}).addTo(raceMarkLayer);
	}
}
/* ---- Race course sheet (left-side per-mark detail cards) ---- */
function fmtRaceLL(lat,lon){return Math.abs(lat).toFixed(4)+'°'+(lat>=0?'N':'S')+', '+Math.abs(lon).toFixed(4)+'°'+(lon>=0?'E':'W');}
/* wind + wave at a mark, for the current slider time */
function raceWindWave(lat,lon){
	var r=(typeof readPoint==='function')?readPoint(lat,lon,curTi):null;if(!r)return '—';
	var out=[];
	if(r.kt!=null)out.push('<b>'+Math.round(r.kt)+'</b> kt'+(r.dir!=null?' '+card(r.dir):''));
	if(r.waveFt!=null)out.push('<b>'+r.waveFt.toFixed(1)+'</b> ft wave');
	return out.length?out.join(' &middot; '):'—';
}
/* "128°M · 3.1 nm · TWA 95°S beam" for the leg a→b, at the current slider time */
function raceLegLine(a,b){
	var course=brgTrue(a[0],a[1],b[0],b[1]),mid=[(a[0]+b[0])/2,(a[1]+b[1])/2];
	var s=magBrg(a[0],a[1],b[0],b[1])+'°M &middot; '+distNM(a[0],a[1],b[0],b[1]).toFixed(1)+' nm';
	var r=(typeof readPoint==='function')?readPoint(mid[0],mid[1],curTi):null;
	if(r&&r.dir!=null){
		var rel=((r.dir-course+540)%360)-180,twa=Math.abs(rel),side=(twa<2||twa>178)?'':(rel>=0?'S':'P');
		var LA=layAng(r.kt),pos=twa<LA.up?'beat':(twa>180-LA.dn?'run':(twa<55?'close-hauled':twa<80?'reach':twa<110?'beam':'broad'));
		s+=' &middot; TWA '+Math.round(twa)+'°'+side+' '+pos;
	}
	return s;
}
/* −/+ collapse button for a card header */
function cardMinBtn(min){return '<button type="button" class="card-min-btn" aria-label="'+(min?'Expand':'Collapse')+'">'+(min?'+':'−')+'</button>';}
/* position the left stack just below the course bar (or at the top when it's off) */
function layoutLeftStack(){
	var ls=document.getElementById('leftStack'),rb=document.getElementById('raceBar');if(!ls)return;
	ls.style.top=((rb&&rb.classList.contains('on'))?(rb.offsetTop+rb.offsetHeight+8):10)+'px';
	var bi=document.getElementById('boatInfo'),rsEl=document.getElementById('raceSheet');
	ls.classList.toggle('has-cards',(bi&&bi.classList.contains('on'))||(rsEl&&rsEl.classList.contains('on')));  /* show the "hide all" toggle only when there are cards */
}
/* mobile master toggle: hide/show every card at once */
/* Map tips box: +/- toggle. Collapsed by default; remembers the reader's choice per browser. */
(function(){var box=document.getElementById('tipHelp'),b=document.getElementById('tipTog');if(!box||!b)return;
	function set(open){box.classList.toggle('collapsed',!open);b.setAttribute('aria-expanded',open?'true':'false');
		b.title=open?'Hide map tips':'Show map tips';b.querySelector('.tip-sign').textContent=open?'\u2212':'+';}
	var o=false;try{o=localStorage.getItem('oyc_wm_tips')==='1';}catch(e){}set(o);
	b.addEventListener('click',function(e){e.stopPropagation();var open=box.classList.contains('collapsed');set(open);
		try{localStorage.setItem('oyc_wm_tips',open?'1':'0');}catch(e2){}});})();
(function(){var b=document.getElementById('stackToggle'),ls=document.getElementById('leftStack');if(!b||!ls)return;
	b.addEventListener('click',function(){var hide=!ls.classList.contains('stack-hidden');ls.classList.toggle('stack-hidden',hide);b.textContent=hide?'Show cards':'Hide cards';b.setAttribute('aria-label',b.textContent);});
})();
/* live boat card (mobile) — SOG + COG/heading from GPS; shown whenever My
   location has a fix, so it's visible at rest too (reads "Underway" once moving) */
function updateBoatInfo(){
	var el=document.getElementById('boatInfo');if(!el)return;
	if(!myLoc){el.classList.remove('on');el.innerHTML='';layoutLeftStack();return;}
	var sog=(mySpeedKt!=null)?mySpeedKt:0, moving=sog>=0.5;
	var hd=(moving&&myHeading!=null)?Math.round(((myHeading+MAGVAR)%360+360)%360):null;
	var rows='<div class="bi-row"><span class="bi-v">'+sog.toFixed(1)+'</span><span class="bi-k">kt SOG</span></div>'
		+'<div class="bi-row"><span class="bi-v">'+(hd!=null?pad3(hd)+'°M':'—')+'</span><span class="bi-k">COG'+(hd!=null?' '+card(hd):'')+'</span></div>';
	/* chart plotter: live wind angle on your course (forecast wind at your spot) */
	var w=modelWind(myLoc.lat,myLoc.lon,Date.now());
	if(w&&moving&&myHeading!=null){var tw=twaFrom(w.twd,myHeading,w.tws);
		rows+='<div class="bi-row"><span class="bi-v">'+Math.round(tw.twa)+'°'+(tw.side?' '+tw.side:'')+'</span><span class="bi-k">TWA · '+tw.pos+' · '+Math.round(w.tws)+' kt</span></div>';}
	/* and the leg to the next waypoint of your route */
	if(pins.length&&pins[nextWp]){var np=pins[nextWp],nb=brgTrue(myLoc.lat,myLoc.lon,np.lat,np.lon),nt=w?twaFrom(w.twd,nb,w.tws):null;
		rows+='<div class="bi-row"><span class="bi-v">'+pad3(nb+MAGVAR)+'°M</span><span class="bi-k">to Pt '+(nextWp+1)+' · '+distNM(myLoc.lat,myLoc.lon,np.lat,np.lon).toFixed(1)+' nm'+(nt?' · TWA '+Math.round(nt.twa)+'°'+(nt.side?' '+nt.side:''):'')+'</span></div>';
		if(layOn()){var lg=layGeom([myLoc.lat,myLoc.lon],[np.lat,np.lon],true);
			if(lg)rows+='<div class="bi-row"><span class="bi-v">'+(lg.lays?'Laying':(lg.up?'Beat':'Gybe'))+'</span><span class="bi-k">'+(lg.lays?'you can sail straight to Pt '+(nextWp+1):'tack on the laylines · stbd '+pad3(lg.stbd.heading+MAGVAR)+'° / port '+pad3(lg.port.heading+MAGVAR)+'°M')+'</span></div>';}}
	el.innerHTML='<div class="rs-h"><span class="bi-title">'+(moving?'Underway':'My boat')+'</span>'+cardMinBtn(boatMin)+'</div><div class="rs-body">'+rows+'</div>';
	el.classList.toggle('min',boatMin);el.classList.add('on');
	layoutLeftStack();
}
/* Race course ETA from the chosen polars (Race tools): each leg's speed from the
   wind and sea at the mark it sails TO, at the slider's hour (the same reading
   as that mark's card), tacking/gybing via best VMG, less the sea-state loss.
   Start = the slider's hour. null without polars or with a leg too light. */
function racePlan(pts){
	var W=window.OYCWM;if(!W||typeof W.polarBsp!=='function'||!pts||pts.length<2)return null;
	var t0=curEpoch(),t=t0,legs=[];
	for(var i=0;i<pts.length-1;i++){
		var a=pts[i],b=pts[i+1],d=distNM(a[0],a[1],b[0],b[1]),r=null;
		try{r=readPoint(b[0],b[1],curTi);}catch(e){r=null;}
		var sp=planSpeed(W,r,brgTrue(a[0],a[1],b[0],b[1]));
		if(!sp||!(sp.v>0.5))return {legs:legs,ok:false,name:W.polarName||''};
		var h=d/sp.v;t+=h*36e5;legs.push({v:sp.v,hrs:h,eta:t,sea:sp.sea});
	}
	return {legs:legs,ok:true,eta:t,hrs:(t-t0)/36e5,name:W.polarName||''};
}
function clockText(ms){var d=new Date(ms),m=d.getMinutes();return (d.getHours()%12||12)+':'+(m<10?'0':'')+m+' '+(d.getHours()>=12?'PM':'AM');}
var RACEPLAN=null;
function renderRaceSheet(){
	var el=document.getElementById('raceSheet');if(!el)return;
	var cb=document.getElementById('tgRace');
	var seq=(raceStr||'').toUpperCase().replace(/[^A-J]/g,'').split('').filter(function(k){return MARKS[k];});
	if(!cb||!cb.checked||seq.length<2){el.classList.remove('on');el.innerHTML='';layoutLeftStack();return;}
	var h='';
	for(var i=0;i<seq.length;i++){var m=MARKS[seq[i]],nx=(i<seq.length-1)?MARKS[seq[i+1]]:null,mn=!!rsMin[i];
		h+='<div class="rs-card'+(mn?' min':'')+'" data-i="'+i+'"><div class="rs-h"><span class="rs-badge">'+seq[i]+'</span><span class="rs-nm">'+m.buoy+'</span>'+cardMinBtn(mn)+'</div>'
			+'<div class="rs-body"><div class="rs-ll">'+fmtRaceLL(m.lat,m.lon)+'</div>'
			+'<div class="rs-ww">'+raceWindWave(m.lat,m.lon)+'</div>'
			+(nx?'<div class="rs-leg">&rarr; '+seq[i+1]+': '+raceLegLine([m.lat,m.lon],[nx.lat,nx.lon])
				+((RACEPLAN&&RACEPLAN.legs[i])?'<br><b>'+RACEPLAN.legs[i].v.toFixed(1)+' kt'+(RACEPLAN.legs[i].sea>=0.03?' (seas &minus;'+Math.round(RACEPLAN.legs[i].sea*100)+'%)':'')+' &middot; '+durText(RACEPLAN.legs[i].hrs)+' &middot; at '+seq[i+1]+' '+clockText(RACEPLAN.legs[i].eta)+'</b>':'')+'</div>':'')
			+'</div></div>';
	}
	el.innerHTML=h;el.classList.add('on');
	layoutLeftStack();
	/* waves are lazy per grid — pull them once so the wave figures fill in */
	if(typeof loadWaves==='function'&&activeGrid&&!activeGrid.wvLoaded&&!activeGrid.wvLoading){loadWaves(activeGrid,renderRaceSheet);}
}
/* one delegated handler for every card's −/+ button (cards are re-rendered, the
   stack is stable) */
(function(){var ls=document.getElementById('leftStack');if(!ls)return;
	ls.addEventListener('click',function(e){
		var btn=e.target.closest&&e.target.closest('.card-min-btn');if(!btn)return;
		var cardEl=btn.closest('.rs-card, .boatinfo');if(!cardEl)return;
		var mn;
		if(cardEl.classList.contains('boatinfo')){boatMin=!boatMin;mn=boatMin;}
		else{var i=cardEl.getAttribute('data-i');mn=!rsMin[i];rsMin[i]=mn;}
		cardEl.classList.toggle('min',mn);btn.textContent=mn?'+':'−';btn.setAttribute('aria-label',mn?'Expand':'Collapse');
	});
})();
function plotCourse(str){
	raceCourseLayer.clearLayers();
	raceStr=(str!=null)?str:raceStr;   /* remember the course so time changes can re-plot it */
	var info=document.getElementById('raceInfo');
	var seq=(raceStr||'').toUpperCase().replace(/[^A-J]/g,'').split(''),pts=[],bad=[];
	var seqKey=seq.join('');if(seqKey!==raceSeqKey){raceLegOff={};rsMin={};raceSeqKey=seqKey;legDragLine=null;}  /* new course → forget dragged-aside labels + collapse state */
	for(var i=0;i<seq.length;i++){var m=MARKS[seq[i]];if(m)pts.push([m.lat,m.lon]);else bad.push(seq[i]);}
	racePts=pts;if(typeof drawLaylines==='function')drawLaylines();
	if(pts.length<2){RACEPLAN=null;if(info)info.textContent=seq.length?'Enter at least two marks (A–J).':'';renderRaceSheet();return;}
	L.polyline(pts,{color:'#b5362a',weight:2.5,opacity:.85,dashArray:'6 4',interactive:false}).addTo(raceCourseLayer);
	/* pull the tidal-current predictions so legs can show set/drift even when the
	   Current overlay isn't on; re-plots once they arrive */
	if(typeof CURDATA!=='undefined'&&!CURDATA&&typeof loadCurrents==='function')loadCurrents(replotRace);
	var tot=0;
	for(i=0;i<pts.length-1;i++){
		var a=pts[i],b=pts[i+1],d=distNM(a[0],a[1],b[0],b[1]);tot+=d;
		var mid=[(a[0]+b[0])/2,(a[1]+b[1])/2];
		var off=raceLegOff[i],lpos=off?[mid[0]+off[0],mid[1]+off[1]]:mid;
		if(off)raceLeader(mid,lpos).addTo(raceCourseLayer);   /* leader back to the leg it belongs to */
		var lm=L.marker(lpos,{icon:L.divIcon({className:'race-leg',html:'<div class="rl-wrap" title="Drag aside · double-tap to snap back">'+legLabelHTML(a,b,d,mid)+'</div>',iconSize:[0,0]}),interactive:false,keyboard:false,zIndexOffset:710}).addTo(raceCourseLayer);
		makeLegDraggable(lm,i,mid);
	}
	RACEPLAN=racePlan(pts);
	if(info)info.textContent=seq.join(' – ')+'  ·  '+tot.toFixed(1)+' nm'
		+(RACEPLAN?(RACEPLAN.ok?'  ·  finish '+clockText(RACEPLAN.eta)+' ('+durText(RACEPLAN.hrs)+')'+(RACEPLAN.name?' · '+RACEPLAN.name:''):'  ·  ETA needs wind on every leg'):'')
		+(bad.length?'  ·  unknown: '+bad.join(','):'');
	renderRaceSheet();drawWindDial();
}
document.getElementById('tgRace').addEventListener('change',function(){
	var bar=document.getElementById('raceBar');
	if(this.checked){raceMarkLayer.addTo(map);raceCourseLayer.addTo(map);drawRaceMarks();replotRace();if(bar)bar.classList.add('on');}
	else{map.removeLayer(raceMarkLayer);raceMarkLayer.clearLayers();map.removeLayer(raceCourseLayer);raceCourseLayer.clearLayers();if(bar)bar.classList.remove('on');renderRaceSheet();}
	drawLaylines();drawWindDial();
});
(function(){var p=document.getElementById('racePlot'),c=document.getElementById('raceClear'),inp=document.getElementById('raceCourse');if(!p)return;
	p.addEventListener('click',function(){plotCourse(inp.value);});
	inp.addEventListener('keydown',function(e){if(e.key==='Enter'){e.preventDefault();plotCourse(inp.value);}});
	c.addEventListener('click',function(){inp.value='';plotCourse('');});
})();
/* ---------- route leg speeds + ETA from the chosen polars ----------
   With polars picked in Race tools (race-tools.js sets OYCWM.polarBsp), each leg
   gets a planned speed made good from the forecast wind at the time the boat is
   expected there: leaving at the slider's hour, leg by leg. Upwind of the best
   VMG angle it tacks (VMG / cos of the course off the wind), likewise gybing
   downwind; otherwise the polar speed at that wind angle, less the sea-state
   loss (seaLoss) from the forecast waves. Under ~0.5 kt (or no
   wind reading) the ETA is left out rather than guessed. */
function tiFor(ep){var b=0,bd=1e18;for(var i=0;i<TIMES.length;i++){var d=Math.abs(new Date(TIMES[i]).getTime()-ep);if(d<bd){bd=d;b=i;}}return b;}
/* Sea-state speed loss. Polars are flat-water numbers; in a seaway a heavy
   cruiser loses speed to pitching and added wave resistance, most of all
   heading into it. Loss per foot of significant wave height: ~3.5% close-hauled,
   ~2% reaching, ~1.2% running, scaled by steepness (a 7 s sea is the reference;
   shorter = steeper = worse, longer swell = gentler), capped at 50%. So 8.5 ft at
   7 s takes about 30% off upwind, 17% on a reach and 10% running. */
function seaLossFor(r,twa){
	if(!r||r.waveFt==null||!(r.waveFt>0.5))return 0;
	var per=(r.wavePer>0)?r.wavePer:7,steep=Math.max(0.6,Math.min(1.4,7/per));
	var c=twa<60?0.035:(twa<=110?0.035-(twa-60)/50*0.015:(twa<=150?0.02-(twa-110)/40*0.008:0.012));
	return Math.min(0.5,r.waveFt*c*steep);
}
/* Wind + sea where the boat is: the map's forecast at that spot, else the
   nearer end waypoint's own exact-point forecast (offshore, before the Atlantic
   grid loads). */
function planSample(lat,lon,ti,pa,pb){
	var r=null;try{r=readPoint(lat,lon,ti);}catch(e){}
	if(r&&r.kt!=null&&r.dir!=null&&r.waveFt!=null)return r;
	var near=null,da=pa?distNM(lat,lon,pa.lat,pa.lon):1e9,db=pb?distNM(lat,lon,pb.lat,pb.lon):1e9;
	near=(da<=db)?(pa||pb):(pb||pa);
	var q=null;try{if(near)q=pinReading(near.lat,near.lon,ti,near.fc);}catch(e){}
	if(!r||r.kt==null||r.dir==null)return q;
	if(q&&r.waveFt==null){r.waveFt=q.waveFt;r.wavePer=q.wavePer;}
	return r;
}
/* Planned speed made good on course crs in reading r: tack upwind of the best
   VMG angle, gybe downwind of it, else the polar speed; less the sea loss. */
function planSpeed(W,r,crs){
	if(!r||r.kt==null||r.dir==null)return null;
	var D=Math.PI/180,tw=twaFrom(r.dir,crs,r.kt),v;
	var up=W.polarVmg?W.polarVmg(r.kt,true):null,dn=W.polarVmg?W.polarVmg(r.kt,false):null;
	if(up&&up.vmg>0&&tw.twa<up.twa)v=up.vmg/Math.max(0.2,Math.cos(tw.twa*D));
	else if(dn&&dn.vmg>0&&tw.twa>dn.twa)v=dn.vmg/Math.max(0.2,Math.abs(Math.cos(tw.twa*D)));
	else v=W.polarBsp(r.kt,tw.twa);
	var sl=seaLossFor(r,tw.twa);
	return {v:v*(1-sl),sea:sl,kt:r.kt,twa:tw.twa,ft:r.waveFt};
}
/* Each leg's speed comes from the conditions SHOWN for its end waypoint at the
   slider's hour (the same wind and sea as that waypoint's row and card), so the
   numbers on screen and the speed agree; move the slider to plan another
   departure/weather hour. Where a waypoint has no reading yet, the map's
   forecast at the leg midpoint is used. ETA = slider hour + the legs' times. */
function routePlan(){
	var W=window.OYCWM;if(!W||typeof W.polarBsp!=='function'||!TIMES.length)return null;
	var pts=routePts();if(pts.length<2)return null;
	var ti=curTi,t0=curEpoch(),t=t0,legs=[],ok=true;
	for(var j=0;j<pts.length-1;j++){
		var a=pts[j],b=pts[j+1],dist=distNM(a[0],a[1],b[0],b[1]),pb=pinAt(b),r=null;
		try{if(pb)r=pinReading(pb.lat,pb.lon,ti,pb.fc);}catch(e){r=null;}
		if(!r||r.kt==null||r.dir==null)r=planSample((a[0]+b[0])/2,(a[1]+b[1])/2,ti,pinAt(a),pb);
		var sp=ok?planSpeed(W,r,brgTrue(a[0],a[1],b[0],b[1])):null;
		if(!sp||!(sp.v>0.5)){ok=false;legs.push(null);continue;}
		var hrs=dist/sp.v;t+=hrs*36e5;
		legs.push({v:sp.v,hrs:hrs,eta:t,sea:sp.sea,kt:sp.kt,ft:sp.ft,twa:sp.twa});
	}
	return {legs:legs,ok:ok,eta:ok?t:null,hrs:ok?(t-t0)/36e5:null,past:false,name:W.polarName||''};
}
function etaText(ms){var d=new Date(ms);return d.toLocaleDateString('en-US',{weekday:'short'})+' '+(d.getMonth()+1)+'/'+d.getDate()+' '+(d.getHours()%12||12)+(d.getMinutes()>=30?':30':'')+' '+(d.getHours()>=12?'PM':'AM');}
function durText(h){var m=Math.round(h*60);if(m<60)return m+' min';var dd=Math.floor(m/1440),hh=Math.round((m-dd*1440)/60);if(hh===24){dd++;hh=0;}return (dd?dd+'d ':'')+hh+'h';}
/* the planned leg that ENDS at waypoint i (legs run origin→Pt1 when routed from your boat) */
function planLeg(P,i){if(!P)return null;var j=routeOrigin()?i:i-1;return j>=0?P.legs[j]:null;}
function updateRouteBar(){
	var rb=document.getElementById('routeBar');if(!rb)return;
	if(!pins.length){rb.classList.remove('on');return;}
	rb.classList.add('on');
	/* count every numbered point, matching the rows in the table */
	var o=routeOrigin();
	var info=(pins.length===1)
		?(o?('1 waypoint · '+distNM(o.lat,o.lon,pins[0].lat,pins[0].lon).toFixed(1)+' nm from you'):'Start set · tap the map for the next point')
		:(pins.length+' waypoints · '+routeTotalFromWP1().toFixed(1)+' nm'+(o?' from you':''));
	var PR=passCur();   /* a current Passage brief run replaces the slider-hour ETA */
	if(PR){info+=' · Passage ETA '+pWhen(PR.eta)+' ('+pDur(PR.hrs)+')';document.getElementById('routeInfo').textContent=info;return;}
	var P=pins.length>1||routeOrigin()?routePlan():null;
	if(P)info+=P.ok?(' · ETA '+etaText(P.eta)+' ('+durText(P.hrs)+(P.past?', past the forecast':'')+')'+(P.name?' · '+P.name:'')):' · ETA needs wind on every leg';
	document.getElementById('routeInfo').textContent=info;
}
(function(){
	var rb=document.getElementById('routeBar');
	if(rb&&L.DomEvent){L.DomEvent.disableClickPropagation(rb);L.DomEvent.disableScrollPropagation(rb);}
	var ex=document.getElementById('rbExport');if(ex)ex.addEventListener('click',exportRoute);
	var cl=document.getElementById('rbClear');if(cl)cl.addEventListener('click',function(){while(pins.length)removePin(pins[0]);});
	var fb=document.getElementById('rbFromBoat');if(fb)fb.addEventListener('click',function(){setFromBoat(!routeFromBoat);});
})();

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
	/* null = no data yet (a basin chunk still loading, or one that failed): skipped, never read as 0 mb */
	var mn=1e9,mx=-1e9;for(r=0;r<NY;r++)for(c=0;c<NX;c++){var pv=P[r][c];if(pv==null)continue;if(pv<mn)mn=pv;if(pv>mx)mx=pv;}
	if(mn>mx)return;
	function lerp(a,b,t,axa,axb){return axa+(axb-axa)*((t-a)/(b-a));}
	var labN={}; /* per-isobar segment counter, so each line is labelled a few times, spaced out */
	for(var thr=Math.ceil(mn/step)*step;thr<=mx;thr+=step){
		for(r=0;r<NY-1;r++)for(c=0;c<NX-1;c++){
			var tl=P[r][c],tr=P[r][c+1],br=P[r+1][c+1],bl=P[r+1][c],pts=[];
			if(tl==null||tr==null||br==null||bl==null)continue;
			if((tl-thr)*(tr-thr)<0)pts.push([g.lats[r],lerp(tl,tr,thr,g.lons[c],g.lons[c+1])]);
			if((tr-thr)*(br-thr)<0)pts.push([lerp(tr,br,thr,g.lats[r],g.lats[r+1]),g.lons[c+1]]);
			if((bl-thr)*(br-thr)<0)pts.push([g.lats[r+1],lerp(bl,br,thr,g.lons[c],g.lons[c+1])]);
			if((tl-thr)*(bl-thr)<0)pts.push([lerp(tl,bl,thr,g.lats[r],g.lats[r+1]),g.lons[c]]);
			if(pts.length>=2){
				L.polyline([pts[0],pts[1]],{color:'#37506b',weight:1.2,opacity:.8,interactive:false}).addTo(isoLayer);
				if(pts.length===4)L.polyline([pts[2],pts[3]],{color:'#37506b',weight:1.2,opacity:.8,interactive:false}).addTo(isoLayer);
				/* Label each pressure line with its value (mb): once near its start
				   (so every line gets at least one) and again every 9th segment for
				   long lines, keeping the labels spaced along the isobar. */
				var n=(labN[thr]=(labN[thr]||0)+1);
				if(n===2||n%9===0){
					L.marker(pts[0],{icon:L.divIcon({className:'iso-lbl',html:Math.round(thr)+'',iconSize:[30,14],iconAnchor:[15,7]}),interactive:false}).addTo(isoLayer);
				}
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
		if(v==null)continue;
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

/* ---------- western LI Sound tidal current (NOAA current predictions) ----------
   Open-Meteo's global ocean model is null inside the Sound, so we use NOAA's
   harmonic current-prediction stations: each one gives a signed Velocity_Major
   (knots; +flood / −ebb) plus mean flood/ebb set directions. We draw a teal
   arrow per station pointing the way the current SETS at the slider's time, with
   a kt label — so it reverses as you scrub the tide. Points only (NOAA has no
   gridded current for the Sound). Fetched browser-direct (CORS ok, not IP
   throttled like the prod host) and cached. */
var CURSTA=[
	{id:'LIS1036',lat:40.873,lon:-73.733}, /* Execution Rocks (off OYC) */
	{id:'ACT3201',lat:40.939,lon:-73.675}, /* Peningo Neck — off Mamaroneck */
	{id:'ACT3236',lat:40.917,lon:-73.712}, /* Delancey Point */
	{id:'LIS1035',lat:40.925,lon:-73.656}, /* Matinecock Point NW */
	{id:'ACT3176',lat:40.960,lon:-73.561}, /* Greenwich Point */
	{id:'ACT3181',lat:40.925,lon:-73.567}, /* Oak Neck Point */
	{id:'LIS1037',lat:40.843,lon:-73.762}, /* Hart Island SE */
	{id:'LIS1038',lat:40.801,lon:-73.792}, /* Throgs Neck Bridge */
	{id:'ACT3346',lat:40.802,lon:-73.827}, /* Bronx-Whitestone Bridge */
	{id:'ACT3161',lat:40.883,lon:-73.533}  /* nr Cold Spring Harbor (W of Soper Pt) */
];
var curLayer=L.layerGroup(), CURDATA=null, curLoading=false;
function curOn(){var el=document.getElementById('tgCur');return !!(el&&el.checked);}
function curColor(sp){return sp<0.15?'#44525c':sp<0.7?'#0b8f9c':sp<1.5?'#076f86':'#083e74';}
function curArrowHTML(dir,sp){
	var c=curColor(sp);
	if(sp<0.1)return '<div class="cur-ic"><span class="cur-dot" style="background:'+c+'"></span><b class="cur-lbl" style="color:'+c+'">slack</b></div>';
	var L=Math.min(44,20+sp*11);
	/* white casing stroke under the coloured arrow gives it an outline so it pops
	   against both pale water and busy land tiles */
	var svg='<svg width="56" height="56" viewBox="-28 -28 56 56" style="overflow:visible"><g transform="rotate('+Math.round(dir)+')">'
		+'<line x1="0" y1="'+(L/2).toFixed(1)+'" x2="0" y2="'+(-L/2).toFixed(1)+'" stroke="#fff" stroke-width="8" stroke-linecap="round"/>'
		+'<path d="M0,'+(-L/2-3).toFixed(1)+' L-7,'+(-L/2+8).toFixed(1)+' L7,'+(-L/2+8).toFixed(1)+' Z" fill="none" stroke="#fff" stroke-width="5" stroke-linejoin="round"/>'
		+'<line x1="0" y1="'+(L/2).toFixed(1)+'" x2="0" y2="'+(-L/2).toFixed(1)+'" stroke="'+c+'" stroke-width="4.5" stroke-linecap="round"/>'
		+'<path d="M0,'+(-L/2-3).toFixed(1)+' L-7,'+(-L/2+8).toFixed(1)+' L7,'+(-L/2+8).toFixed(1)+' Z" fill="'+c+'"/>'
		+'</g></svg>';
	return '<div class="cur-ic">'+svg+'<b class="cur-lbl" style="color:'+c+'">'+sp.toFixed(1)+' kt</b></div>';
}
function loadCurrents(cb){
	if(CURDATA){cb&&cb();return;}
	if(curLoading)return;curLoading=true;
	function ymd(d){return d.getFullYear()+String(d.getMonth()+1).padStart(2,'0')+String(d.getDate()).padStart(2,'0');}
	var t0=new Date(),t1=new Date(t0.getTime()+7*864e5);
	var base='https://api.tidesandcurrents.noaa.gov/api/prod/datagetter?product=currents_predictions&application=OYC_wind_map&time_zone=gmt&units=english&format=json&interval=60&bin=1&begin_date='+ymd(t0)+'&end_date='+ymd(t1);
	var data={},pending=CURSTA.length;
	CURSTA.forEach(function(st){
		fetch(base+'&station='+st.id).then(function(r){return r.json();}).then(function(j){
			var arr=(j.current_predictions&&j.current_predictions.cp)||[];
			data[st.id]=arr.map(function(p){return {t:new Date(p.Time.replace(' ','T')+'Z').getTime(),v:+p.Velocity_Major,fl:+p.meanFloodDir,eb:+p.meanEbbDir};});
		}).catch(function(){data[st.id]=[];}).then(function(){if(--pending===0){CURDATA=data;curLoading=false;cb&&cb();}});
	});
}
function drawCurrents(){
	curLayer.clearLayers();
	if(!curOn()||!CURDATA)return;
	var tE=curEpoch();
	CURSTA.forEach(function(st){
		var arr=CURDATA[st.id];if(!arr||!arr.length)return;
		var best=null,bd=1/0;for(var i=0;i<arr.length;i++){var d=Math.abs(arr[i].t-tE);if(d<bd){bd=d;best=arr[i];}}
		if(!best||bd>3*36e5)return; /* nearest prediction is >3 h away → skip */
		var sp=Math.abs(best.v),dir=(best.v>=0)?best.fl:best.eb;
		L.marker([st.lat,st.lon],{icon:L.divIcon({className:'cur-mk',html:curArrowHTML(dir,sp),iconSize:[60,74],iconAnchor:[30,30]}),interactive:false,keyboard:false,zIndexOffset:360}).addTo(curLayer);
	});
}

/* ---------- wind-mode render ---------- */
function showWind(ti){ti=Math.max(0,Math.min(TIMES.length-1,ti));curTi=ti;slider.value=ti;
	if(vl&&map.hasLayer(vl)&&activeGrid.FRAMES[ti])vl.setData(activeGrid.FRAMES[ti]);
	var f=fmt(TIMES[ti]);tlabel.innerHTML=f.big+'<small>'+f.small+(ti===NOWI?' · now':'')+'</small>';
	refreshOverlays();drawField();if(!map.hasLayer(hlLayer))hlLayer.addTo(map);drawHL(activeGrid,ti);updatePins(ti);updateER(ti);updateStormsAt(new Date(TIMES[ti]).getTime());if(gsOn())drawGulf(ti);if(curOn())drawCurrents();drawRadarOv();replotRace();}

/* ---------- grid switching by zoom ---------- */
function chooseGrid(){var b=map.getBounds();
	return (b.getSouth()>=LOCAL.LA2-0.6&&b.getNorth()<=LOCAL.LA1+0.6&&b.getWest()>=LOCAL.LO1-0.6&&b.getEast()<=LOCAL.LO2+0.6)?LOCAL:BASIN;}
function setFoot(){
	document.getElementById('foot').innerHTML=(activeGrid===BASIN?'Atlantic basin wind field ':'Long Island Sound to Cape Cod ')+'&middot; Open-Meteo (GFS). Radar = HRRR precip.'
		+((BASIN.note&&chooseGrid()===BASIN)?' &middot; <b>'+BASIN.note+'</b>':'');
}
/* Isobars are what make the Atlantic view readable, so they come on the first time
   a visitor zooms out to it — unless the visitor has switched them off themselves. */
var isoTouched=false;
function autoIso(){var cb=document.getElementById('tgIso');if(isoTouched||cb.checked)return;cb.checked=true;isoLayer.addTo(map);refreshOverlays();}
function maybeSwitchGrid(){
	if(mode==='radar')return;
	var want=chooseGrid();
	if(want===BASIN&&!BASIN.loaded){loadBasin();want=LOCAL;}
	var switched=false;
	if(want!==activeGrid&&want.loaded){activeGrid=want;buildVL(activeGrid,curTi);refreshOverlays();drawField();drawHL(activeGrid,curTi);switched=true;
		if(activeGrid===BASIN)autoIso();}
	if(switched||chooseGrid()===BASIN)setFoot();
}
map.on('zoomend',maybeSwitchGrid);
map.on('zoomend',function(){if(map.hasLayer(layLayer))drawLaylines();}); /* laylines keep a minimum on-screen length */

/* ---------- hover pointer ---------- */
var hoverTip=document.getElementById('hoverTip');
map.on('mousemove',function(e){
	if(!activeGrid.loaded){hoverTip.style.display='none';return;}
	var lon=((e.latlng.lng+540)%360)-180;
	var s=readPoint(e.latlng.lat,lon,curTi);
	if(!s){hoverTip.style.display='none';return;}
	/* fixed box in the lower-left (see .map-ll) — no longer tracks the cursor */
	hoverTip.style.display='block';
	var gsh=gsAt(e.latlng.lat,lon);
	hoverTip.innerHTML='<span class="hd2">'+e.latlng.lat.toFixed(4)+', '+lon.toFixed(4)+' &middot; '+nmFromBuoy42(e.latlng.lat,lon).toFixed(1)+' nm from Buoy 42</span>'
		+Math.round(s.kt)+' kt '+card(s.dir)+(s.mb!=null?' · '+Math.round(s.mb)+' mb':'')+(s.tempF!=null?' · '+Math.round(s.tempF)+'°F':'')+(s.waveFt!=null?' · '+s.waveFt.toFixed(1)+' ft seas'+(s.wavePer!=null?' ('+Math.round(s.wavePer)+' s)':''):'')
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
	g.WV=new Array(N);g.WP=new Array(N);var wvt=null,ci=0,CONC=2;
	function absorb(arr,a){var list=Array.isArray(arr)?arr:[arr],got=0;for(var k=0;k<list.length;k++){var h=list[k].hourly;if(h){if(!wvt&&h.time)wvt=h.time;g.WV[a+k]=h.wave_height||null;g.WP[a+k]=h.wave_period||null;got++;}}return got;}
	/* Browser-direct wave fetch for a chunk — the server proxy returns [] when the
	   host IP is 429'd by marine-api, so pull the same chunk straight from the
	   visitor's connection (not throttled). Matches the proxy's hourly_3 steps. */
	function clientWaves(idx){var a=chunks[idx][0],b=chunks[idx][1];
		var u='https://marine-api.open-meteo.com/v1/marine?latitude='+g.LAT.slice(a,b).join(',')+'&longitude='+g.LON.slice(a,b).join(',')
			+'&hourly=wave_height,wave_period&temporal_resolution=hourly_3&forecast_days=7&timezone=America%2FNew_York';
		return fetch(u).then(function(r){if(!r.ok)throw 0;return r.json();}).then(function(arr){absorb(arr,a);}).catch(function(){});
	}
	function fc(idx,tries){var a=chunks[idx][0];
		/* cached per-chunk server proxy (CH=140 matches oyc_waves) */
		return fetchT(AJAX+'?action=oyc_waves&grid='+(g.name||'local')+'&chunk='+idx,15000)
			.then(function(r){if(!r.ok)throw new Error(r.status);return r.json();})
			.then(function(arr){ if(absorb(arr,a)===0) return clientWaves(idx); /* server empty (429) → browser-direct */ })
			.catch(function(){if(tries<2)return new Promise(function(res){setTimeout(res,800*(tries+1));}).then(function(){return fc(idx,tries+1);}); return clientWaves(idx);});
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
		maybeSwitchGrid(); /* picks up a zoom-out that happened before the local grid (and TIMES) arrived */
	}
	/* cron-warmed static file first, then the proxy; browser direct if the host can't reach Open-Meteo */
	statFetch('wind_local','oyc_wind_local',90,15000) /* warmed hourly now */
		.then(function(r){return r.ok?r.json():null;})
		.catch(function(){return null;})
		.then(function(arr){return valid(arr)?arr:clientLocal();})
		.then(function(arr){if(!valid(arr))throw new Error('grid');render(arr);})
		.catch(function(e){
			if(tries<2){setTimeout(function(){loadLocal(tries+1);},1500);return;}
			document.getElementById('st').textContent='Wind field unavailable ('+e+')';
		});
}

/* Atlantic basin loader, in 150-point chunks (CH=150 matches oyc_wind_basin).
   Each chunk comes from the cron-warmed static file first (refreshed once per
   00Z/12Z GFS run, accepted up to 72 h old), then the cached proxy, then, spaced
   out, straight from Open-Meteo.
   Chunks are drawn as they arrive. Values are placed on the master time axis by
   TIMESTAMP, not by index: a cached chunk can come from an earlier GFS run whose
   series starts on the previous day. A chunk that never loads stays null (blank)
   rather than being faked as calm air at 1013 mb. */
function loadBasin(){
	if(BASIN.loaded||BASIN.loading||!TIMES.length)return; /* needs the local grid's time axis */
	BASIN.loading=true;
	var N=BASIN.LAT.length,CH=150,chunks=[];
	for(var s=0;s<N;s+=CH)chunks.push([s,Math.min(s+CH,N)]);
	var T=TIMES.length,TIDX={};for(var t=0;t<T;t++)TIDX[TIMES[t]]=t;
	function blank(){var b=new Array(T);for(var i=0;i<T;i++)b[i]=null;return b;}
	BASIN.SP=[];BASIN.DR=[];BASIN.PR=[];BASIN.TP=[];
	for(var i=0;i<N;i++){BASIN.SP.push(blank());BASIN.DR.push(blank());BASIN.PR.push(blank());BASIN.TP.push(blank());}
	var got=0,failed=0,ci=0,CONC=3,timer=null;
	BASIN.note='Loading Atlantic data… 0 of '+chunks.length;
	function align(src,times){var out=blank();if(!src)return out;for(var j=0;j<times.length;j++){var ti=TIDX[times[j]];if(ti!=null)out[ti]=src[j];}return out;}
	function valid(arr){return Array.isArray(arr)&&arr.length&&arr[0]&&arr[0].hourly&&arr[0].hourly.time;}
	function store(a,arr){var list=Array.isArray(arr)?arr:[arr];
		for(var k=0;k<list.length;k++){var h=list[k]&&list[k].hourly;if(!h||!h.time)continue;var gi=a+k;
			BASIN.SP[gi]=align(h.wind_speed_10m,h.time);BASIN.DR[gi]=align(h.wind_direction_10m,h.time);
			BASIN.PR[gi]=align(h.pressure_msl,h.time);BASIN.TP[gi]=align(h.temperature_2m,h.time);}}
	/* browser direct fetch of this chunk's points — same grid slice/params as the
	   proxy — for when the host can't reach Open-Meteo (keeps the national view alive) */
	function clientChunk(a,b){
		var u='https://api.open-meteo.com/v1/forecast?latitude='+BASIN.LAT.slice(a,b).join(',')+'&longitude='+BASIN.LON.slice(a,b).join(',')
			+'&hourly=wind_speed_10m,wind_direction_10m,pressure_msl,temperature_2m'
			+'&wind_speed_unit=ms&temperature_unit=fahrenheit&precipitation_unit=inch&temporal_resolution=hourly_3&forecast_days=7&timezone=America%2FNew_York';
		return fetchT(u,30000).then(function(r){if(!r.ok)throw new Error('b '+r.status);return r.json();});
	}
	/* redraw at most every 250 ms as chunks land; switch to the basin view on the first one */
	function progress(){if(!timer)timer=setTimeout(paint,250);}
	function paint(){timer=null;
		var done=got+failed>=chunks.length;
		if(got){buildFrames(BASIN);BASIN.loaded=true;}
		if(done)BASIN.loading=false;
		BASIN.note=done?(failed?'Some Atlantic data is unavailable right now, so those areas are blank':''):'Loading Atlantic data… '+(got+failed)+' of '+chunks.length;
		if(activeGrid===BASIN&&mode!=='radar'){
			if(vl&&map.hasLayer(vl)&&BASIN.FRAMES[curTi])vl.setData(BASIN.FRAMES[curTi]);
			refreshOverlays();drawField();drawHL(BASIN,curTi);setFoot();
		}else maybeSwitchGrid();
	}
	/* Browser-direct fallback, for when the server has nothing (it is the only
	   path that asks Open-Meteo from the visitor's own connection). Open-Meteo
	   counts each of a chunk's 150 points as a call, against ~600 a minute per
	   address, so these run ONE AT A TIME and at least 25 s apart (~360 calls a
	   minute); a 429 waits 65 s for the minute to reset and tries again, up to 3
	   times. Chunks still draw as each one lands. */
	var dq=Promise.resolve(),lastDirect=0,GAP=25000;
	function direct(a,b){
		function attempt(n){
			var wait=Math.max(0,lastDirect+GAP-Date.now());
			return new Promise(function(res){setTimeout(res,wait);}).then(function(){
				lastDirect=Date.now();
				return clientChunk(a,b).catch(function(e){
					if(n<3)return new Promise(function(res){setTimeout(res,String(e).indexOf('429')>=0?65000:5000);}).then(function(){return attempt(n+1);});
					throw e;
				});
			});
		}
		var p=dq.then(function(){return attempt(0);});
		dq=p.catch(function(){});
		return p;
	}
	/* server first: the cron-warmed static file (accepted up to 72 h old), then the
	   cached proxy, which never fires the grid at Open-Meteo itself */
	function fromServer(idx){
		return statFetch('wind_basin_'+idx,'oyc_wind_basin&chunk='+idx,72*60,15000)
			.then(function(r){return r.ok?r.json():null;}).catch(function(){return null;});
	}
	/* resolves once the SERVER stage is done, so a chunk waiting in the direct
	   queue doesn't hold up the next chunks' (fast) server reads */
	function fetchChunk(idx){
		var a=chunks[idx][0],b=chunks[idx][1];
		function ok(arr){store(a,arr);got++;progress();}
		function bad(){failed++;progress();}
		return fromServer(idx)
			.then(function(arr){return valid(arr)?arr:new Promise(function(res){setTimeout(res,2000);}).then(function(){return fromServer(idx);});})
			.then(function(arr){
				if(valid(arr)){ok(arr);return;}
				direct(a,b).then(function(arr){if(valid(arr))ok(arr);else bad();},bad); /* not awaited */
			},bad);
	}
	function pump(){
		if(ci>=chunks.length)return Promise.resolve();
		var batch=[];for(var n=0;n<CONC&&ci<chunks.length;n++,ci++)batch.push(fetchChunk(ci));
		return Promise.all(batch).then(pump);
	}
	if(chooseGrid()===BASIN)setFoot();
	pump();
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
	stop();mode=m;setTimeout(drawWindDial,0);
	[['wind','tabWind'],['radar','tabRadar'],['temp','tabTemp'],['precip','tabPrecip'],['wave','tabWave'],['sat','tabSat'],['charts','tabCharts']].forEach(function(x){var b=document.getElementById(x[1]);
		if(b){b.classList.toggle('on',m===x[0]);b.setAttribute('aria-selected',m===x[0]);}});
	document.getElementById('windLayers').style.display=m==='wind'?'flex':'none';
	document.getElementById('radarLayers').style.display=m==='radar'?'flex':'none';
	document.getElementById('satLayers').style.display=m==='sat'?'flex':'none';
	document.getElementById('chartLayers').style.display=m==='charts'?'flex':'none';
	document.getElementById('tipHelp').style.display=m==='wind'?'block':'none';
	var pcd=document.getElementById('passCard');if(pcd)pcd.classList.toggle('offmode',m!=='wind');
	var pfb=document.getElementById('passFab');if(pfb)pfb.classList.toggle('offmode',m!=='wind');
	/* satellite & charts = standalone NOAA images; swap the interactive map for the image panel */
	var isImg=(m==='sat'||m==='charts');
	document.getElementById('map').style.display=isImg?'none':'';
	document.getElementById('satPanel').style.display=isImg?'flex':'none';
	document.getElementById('ctrlBar').style.display=isImg?'none':'flex';
	document.getElementById('erBar').style.display=isImg?'none':'';
	var wpT=document.getElementById('wpTab');if(wpT){wpT.classList.toggle('wp-img',isImg);try{wpFit();}catch(e){}}
	if(isImg){setParticlesVisible(false);map.removeLayer(arrowsLayer);map.removeLayer(isoLayer);map.removeLayer(buoyLayer);updateBarbHint();
		document.getElementById('legend').style.display='none';(m==='sat')?loadSat(satWhich):loadChart();return;}
	setTimeout(function(){map.invalidateSize();drawGraticule();},0); /* map container may have been hidden by satellite mode */
	syncBuoys();
	if(m==='radar'){
		setParticlesVisible(false);map.removeLayer(arrowsLayer);map.removeLayer(isoLayer);map.removeLayer(hlLayer);updateBarbHint();
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
	updateLegend();updateBarbHint();
	showWind(Math.min(curTi,Math.max(0,TIMES.length-1)));
}
document.getElementById('tabWind').addEventListener('click',function(){setMode('wind');});
/* Radar / Temp / Precip / Waves view buttons removed (v1.7.404); setMode keeps those modes but nothing opens them */
document.getElementById('tabSat').addEventListener('click',function(){setMode('sat');});
document.getElementById('tabCharts').addEventListener('click',function(){setMode('charts');});
[].forEach.call(document.querySelectorAll('#satLayers .sat-t'),function(b){b.addEventListener('click',function(){loadSat(b.getAttribute('data-sat'));});});
document.getElementById('chartSel').addEventListener('change',loadChart);

/* ---------- layer toggles ---------- */
/* ---- POI layers: reciprocal clubs + dock & dine (assets/map-pois.json) ----
   Always-on chips (not tied to a view tab); choice persists per visitor. */
var poiClubs=L.layerGroup(),poiDine=L.layerGroup(),poiFuel=L.layerGroup(),poiAnch=L.layerGroup(),poiFish=L.layerGroup();
var POI_HOME={lat:40.9486,lon:-73.7296};
function poiNm(lat,lng){var R=3440.065,dLa=(lat-POI_HOME.lat)*Math.PI/180,dLo=(lng-POI_HOME.lon)*Math.PI/180,a=Math.sin(dLa/2)*Math.sin(dLa/2)+Math.cos(POI_HOME.lat*Math.PI/180)*Math.cos(lat*Math.PI/180)*Math.sin(dLo/2)*Math.sin(dLo/2);return R*2*Math.atan2(Math.sqrt(a),Math.sqrt(1-a));}
function poiDir(lat,lng){var la1=POI_HOME.lat*Math.PI/180,la2=lat*Math.PI/180,dLo=(lng-POI_HOME.lon)*Math.PI/180;var b=(Math.atan2(Math.sin(dLo)*Math.cos(la2),Math.cos(la1)*Math.sin(la2)-Math.sin(la1)*Math.cos(la2)*Math.cos(dLo))*180/Math.PI+360)%360;return ['N','NE','E','SE','S','SW','W','NW'][Math.round(b/45)%8];}
var POI_FORK='<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round"><path d="M5 3v8M8.4 3v8M6.7 11v10M5 3c0 3.2 3.4 3.2 3.4 0M16.2 3c-1.6 0-2.7 2.1-2.7 5.2 0 2 .9 3 1.6 3V21M16.2 3c1.6 0 2.7 2.1 2.7 5.2 0 2-.9 3-1.6 3"/></svg>';
/* shared popup options: card width + keep the whole card inside the map when it opens */
var POI_POP={className:'poi-pop',minWidth:250,maxWidth:290,autoPanPadding:[16,16]};
/* re-measure once the card is on screen, so its width/height never come from a
   half-laid-out moment (tab switch, expand) */
map.on('popupopen',function(e){var pp=e.popup;if(!pp||!/poi-pop/.test(pp.options.className||''))return;requestAnimationFrame(function(){if(pp.isOpen&&pp.isOpen())pp.update();});});
var POI_KIND={club:'Reciprocal club',dine:'Dock &amp; dine',fuel:'Fuel dock',anc:'Anchorage',fish:'Fishing spot'};
function poiPopup(p,kind){
	var d=poiNm(p.lat,p.lng);
	var sub=(POI_KIND[kind]||'')+' &middot; '+(d<10?d.toFixed(1):Math.round(d))+' nm '+poiDir(p.lat,p.lng)+(p.town?' &middot; '+p.town:'');
	var links='<a href="#" class="pp-route" data-lat="'+p.lat+'" data-lng="'+p.lng+'">&#x2693; Add to route</a>';
	if(p.url)links+='<a href="'+p.url+'" target="_blank" rel="noopener">Club site</a>';
	links+='<a href="https://www.google.com/search?q='+encodeURIComponent(p.q||p.name)+'" target="_blank" rel="noopener">'+(kind==='dine'?'Reviews on Google':'Search on Google')+'</a>';
	return '<div class="pp-name">'+p.name+'</div><div class="pp-sub">'+sub+'</div>'+(p.desc?'<div class="pp-desc">'+p.desc+'</div>':'')+'<div class="pp-links">'+links+'</div>';
}
/* Fishing spot popup: structure, the species anglers target there (in season this
   month first, the rest greyed with their months), and one practical tip. Months
   come from the spot's "months" map in map-pois.json (1 = Jan). */
var FISH_MON=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
var fishMk={};
/* The weather board's Fishing card ("Top spots today") calls this to show a spot:
   turns the layer on if it was off, centres the map on the pin and opens its popup. */
window.oycFishFocus=function(name){
	var m=fishMk[name];if(!m)return false;
	var cb=document.getElementById('tgFish');if(cb&&!cb.checked){cb.checked=true;cb.dispatchEvent(new Event('change'));}
	map.setView(m.getLatLng(),Math.max(map.getZoom(),12));m.openPopup();return true;
};
var FISH_RULES={NY:'https://dec.ny.gov/things-to-do/saltwater-fishing/recreational-fishing-regulations',CT:'https://portal.ct.gov/DEEP/Fishing/Saltwater-Fishing-Guide/Species-Regulations'};
function fishMonths(ms){
	/* [4,5,9,10,11] -> "Apr–May, Sep–Nov" */
	var out=[],i=0;ms=(ms||[]).slice().sort(function(a,b){return a-b;});
	while(i<ms.length){var a=ms[i];while(i+1<ms.length&&ms[i+1]===ms[i]+1)i++;out.push(a===ms[i]?FISH_MON[a-1]:FISH_MON[a-1]+'–'+FISH_MON[ms[i]-1]);i++;}
	return out.join(', ');
}
function fishInSeason(p,mon){return (p.species||[]).filter(function(s){var m=p.months&&p.months[s];return !m||m.indexOf(mon)>=0;});}
function fishPopup(p){
	var d=poiNm(p.lat,p.lng),mon=new Date().getMonth()+1,now=fishInSeason(p,mon);
	var sub='Fishing spot &middot; '+(d<10?d.toFixed(1):Math.round(d))+' nm '+poiDir(p.lat,p.lng)+(p.area?' &middot; '+p.area:'');
	var chips=(p.species||[]).slice().sort(function(a,b){return (now.indexOf(b)>=0)-(now.indexOf(a)>=0);}).map(function(s){
		var on=now.indexOf(s)>=0,m=p.months&&p.months[s];
		return '<span'+(on?'':' class="out"')+' title="'+(m?fishMonths(m):'')+'">'+s+(on?'':' &middot; '+(m?fishMonths(m):''))+'</span>';
	}).join('');
	return '<div class="pp-name">'+p.name+'</div><div class="pp-sub">'+sub+'</div>'
		+(p.structure?'<div class="pp-desc">'+p.structure+'</div>':'')
		+'<div class="pp-sp-k">'+(!now.length?'NOTHING IN SEASON THIS MONTH':(now.length===(p.species||[]).length?'IN SEASON NOW':'IN SEASON NOW (GREY = OTHER MONTHS)'))+'</div><div class="pp-sp">'+chips+'</div>'
		+(p.tip?'<div class="pp-desc">'+p.tip+'</div>':'')
		+'<div class="pp-links"><a href="#" class="pp-route" data-lat="'+p.lat+'" data-lng="'+p.lng+'">&#x2693; Add to route</a>'
		+'<a href="'+(p.state==='CT'?FISH_RULES.CT:FISH_RULES.NY)+'" target="_blank" rel="noopener">'+(p.state==='CT'?'CT':'NY')+' size &amp; season rules</a></div>';
}
fetch('<?php echo esc_url( get_template_directory_uri() ); ?>/assets/map-pois.json?v='+encodeURIComponent('<?php echo esc_js( OYC_VERSION ); ?>')).then(function(r){if(!r.ok)throw 0;return r.json();}).then(function(j){
	(j.clubs||[]).forEach(function(p){
		L.marker([p.lat,p.lng],{icon:L.divIcon({className:'poi-mk',html:'<div class="poi-club" title="'+String(p.name).replace(/"/g,'&quot;')+'"></div>',iconSize:[0,0]}),zIndexOffset:800}).bindPopup(poiPopup(p,'club'),POI_POP).addTo(poiClubs);
	});
	(j.dine||[]).forEach(function(p){
		L.marker([p.lat,p.lng],{icon:L.divIcon({className:'poi-mk',html:'<div class="poi-dine" title="'+String(p.name).replace(/"/g,'&quot;')+'">'+POI_FORK+'</div>',iconSize:[0,0]}),zIndexOffset:800}).bindPopup(poiPopup(p,'dine'),POI_POP).addTo(poiDine);
	});
	var POI_PUMP='<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 21V5a2 2 0 0 1 2-2h5a2 2 0 0 1 2 2v16M3.5 21h12M14 10h2a2 2 0 0 1 2 2v5.5a1.5 1.5 0 0 0 3 0V9l-2.5-2.5M6.8 7h5.4v4H6.8z"/></svg>';
	(j.fuel||[]).forEach(function(p){
		L.marker([p.lat,p.lng],{icon:L.divIcon({className:'poi-mk',html:'<div class="poi-fuel" title="'+String(p.name).replace(/"/g,'&quot;')+'">'+POI_PUMP+'</div>',iconSize:[0,0]}),zIndexOffset:800}).bindPopup(poiPopup(p,'fuel'),POI_POP).addTo(poiFuel);
	});
	var POI_ANCHOR='<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#0b2a4a" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="2.2"/><path d="M12 7.2V21M12 21c-4 0-7.2-3-7.6-6.8M12 21c4 0 7.2-3 7.6-6.8M3 12.8l1.4 1.4 1.4-1.4M18.2 14.2l1.4-1.4 1.4 1.4M8.6 10h6.8"/></svg>';
	(j.anc||[]).forEach(function(p){
		L.marker([p.lat,p.lng],{icon:L.divIcon({className:'poi-mk',html:'<div class="poi-anc" title="'+String(p.name).replace(/"/g,'&quot;')+'">'+POI_ANCHOR+'</div>',iconSize:[0,0]}),zIndexOffset:800}).bindPopup(poiPopup(p,'anc'),POI_POP).addTo(poiAnch);
	});
	var POI_FISH='<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2.5 12c2.6-4 6.1-6 10-6 3.6 0 6.3 2 8 6-1.7 4-4.4 6-8 6-3.9 0-7.4-2-10-6Z"/><path d="M2.5 12 .8 8.5M2.5 12 .8 15.5"/><circle cx="16.3" cy="10.6" r=".9" fill="#fff"/></svg>';
	var fishMon=new Date().getMonth()+1;
	(j.fish||[]).forEach(function(p){
		var off=fishInSeason(p,fishMon).length?'':' off';
		fishMk[p.name]=L.marker([p.lat,p.lng],{icon:L.divIcon({className:'poi-mk',html:'<div class="poi-fish'+off+'" title="'+String(p.name).replace(/"/g,'&quot;')+'">'+POI_FISH+'</div>',iconSize:[0,0]}),zIndexOffset:790}).bindPopup(fishPopup(p),POI_POP).addTo(poiFish);
	});
}).catch(function(){});
function poiWire(id,layer,key){
	var cb=document.getElementById(id),st=null;
	try{st=localStorage.getItem(key);}catch(e){}
	cb.checked=(st===null)?true:(st==='1');
	if(cb.checked)layer.addTo(map);
	cb.addEventListener('change',function(){
		if(cb.checked)layer.addTo(map);else map.removeLayer(layer);
		try{localStorage.setItem(key,cb.checked?'1':'0');}catch(e){}
	});
}
poiWire('tgClubs',poiClubs,'oyc_wm_clubs');
poiWire('tgDine',poiDine,'oyc_wm_dine');
poiWire('tgFuel',poiFuel,'oyc_wm_fuel');
poiWire('tgAnch',poiAnch,'oyc_wm_anch');
poiWire('tgFish',poiFish,'oyc_wm_fish');
poiWire('tgChart',encLayer,'oyc_wm_chart');
/* "Add to route" inside a POI popup drops a REGULAR passage waypoint at the
   destination via addPin() — same pin as a map click, so it's draggable,
   numbered, forecast-fetching and GPX/CSV-exportable, and the route line runs
   from Buoy 42 like always. Capture phase: Leaflet popups stop click
   propagation, so a bubble listener would never hear it. */
document.addEventListener('click',function(ev){
	var a=ev.target.closest?ev.target.closest('.pp-route'):null;
	if(!a)return;
	ev.preventDefault();ev.stopPropagation();
	addPin(parseFloat(a.getAttribute('data-lat')),parseFloat(a.getAttribute('data-lng')));
	map.closePopup();
},true);

document.getElementById('tgParticles').addEventListener('change',function(){if(mode!=='wind')return;setParticlesVisible(this.checked);});
/* the "wind from" hint rides with the barbs — visible only while they're shown */
function updateBarbHint(){var h=document.getElementById('barbHint');if(h)h.style.display=map.hasLayer(arrowsLayer)?'flex':'none';}
document.getElementById('tgArrows').addEventListener('change',function(){if(this.checked){arrowsLayer.addTo(map);refreshOverlays();}else map.removeLayer(arrowsLayer);updateBarbHint();drawWindDial();});
document.getElementById('tgIso').addEventListener('change',function(){isoTouched=true;if(this.checked){isoLayer.addTo(map);refreshOverlays();}else map.removeLayer(isoLayer);});
document.getElementById('tgWindColor').addEventListener('change',function(){drawField();updateLegend();});
function pickField(which,cb){var ids={temp:'tgTemp',precip:'tgPrecip',wave:'tgWave'};
	if(cb.checked){fieldMode=which;for(var k in ids){if(k!==which)document.getElementById(ids[k]).checked=false;}}
	else if(fieldMode===which){fieldMode=null;}drawField();updateLegend();syncBuoys();}
document.getElementById('tgTemp').addEventListener('change',function(){pickField('temp',this);});
document.getElementById('tgPrecip').addEventListener('change',function(){pickField('precip',this);});
document.getElementById('tgWave').addEventListener('change',function(){pickField('wave',this);syncBuoys();});
/* buoy observation markers ride with the Waves layer (they used to come with the
   Waves view button, removed in v1.7.404) */
function buoysWanted(){var w=document.getElementById('tgWave');return (mode==='wave'||mode==='wind')&&!!(w&&w.checked);}
function syncBuoys(){if(buoysWanted())loadBuoys(function(){if(buoysWanted())buoyLayer.addTo(map);});else map.removeLayer(buoyLayer);}
/* Radar overlay on the chart plotter (v1.7.404, replaces the old Radar view
   button): IEM tiles that follow the time slider. Past / now = NEXRAD mosaic
   (refreshed every 5 min), later hours = HRRR simulated reflectivity, out to
   +18 h; beyond that HRRR has no frames, so the overlay steps aside. */
var RO={layer:null,url:'',timer:null};
function radarOvOn(){var el=document.getElementById('tgRadarOv');return !!(el&&el.checked);}
function radarOvUrl(){
	var t=TIMES.length?new Date(TIMES[Math.max(0,Math.min(TIMES.length-1,curTi))]).getTime():Date.now();
	var off=Math.round((t-Date.now())/60000);
	if(off<=7)return nexradUrl(0);
	off=Math.max(15,Math.round(off/15)*15);
	return off>1080?'':wideRadarUrl(off);
}
function drawRadarOv(){
	if(!RO)return; /* showWind can run before this block has set RO up */
	if(!radarOvOn()||mode!=='wind'){if(RO.layer){map.removeLayer(RO.layer);RO.layer=null;RO.url='';}return;}
	var u=radarOvUrl();
	if(!u){if(RO.layer){map.removeLayer(RO.layer);RO.layer=null;RO.url='';}return;}
	if(RO.layer&&RO.url===u)return;
	RO.url=u;
	if(RO.layer)RO.layer.setUrl(u);
	else{RO.layer=L.tileLayer(u,{opacity:0.65,zIndex:344,interactive:false,attribution:'NEXRAD &amp; HRRR &middot; Iowa State IEM',updateWhenZooming:false});RO.layer.addTo(map);}
}
document.getElementById('tgRadarOv').addEventListener('change',function(){
	drawRadarOv();
	if(this.checked&&!RO.timer)RO.timer=setInterval(function(){if(RO.layer&&RO.url===nexradUrl(0)){RO.layer.setUrl(nexradUrl(0)+'?t='+Date.now());}},300000);
	else if(!this.checked&&RO.timer){clearInterval(RO.timer);RO.timer=null;}
});
document.getElementById('tgGulf').addEventListener('change',function(){
	if(this.checked){gsBandLayer.addTo(map);gsArrows.addTo(map);
		/* NOAA frontal analysis = position only; no Open-Meteo marine fetch (keeps
		   the heavy per-point current off marine-api, which the prod host throttles) */
		loadGSWall(function(){drawGulf(curTi);updateLegend();});
	}else{map.removeLayer(gsBandLayer);gsBandLayer.clearLayers();map.removeLayer(gsArrows);gsArrows.clearLayers();gsBandBuilt=false;updateLegend();}
});
/* ── Offshore passage brief (Layers > Passage brief, off by default) ──
   A polar router steps a Shannon 43 along the passage route through the GFS
   wind field this map already loads (LOCAL grid in the Sound, the 3° Atlantic
   BASIN grid offshore), half an hour at a time, so each leg uses the wind
   forecast for when the boat is actually there. Boat speed comes from the
   polars (bilinear); below the motoring floor it motor-sails at the floor.
   Adds The Race tidal current (NOAA LIS1017), the NOAA Gulf Stream north/south
   walls, Open-Meteo marine seas, and scans departure times for the best window.
   The result fills the card on the right of the chart; "Ask Claude" sends the
   computed numbers to the oyc_passage_brief endpoint (inc/passage-brief.php)
   for the written brief. Without that, a rule-based summary is shown. */
var PB_AJAX_OK=<?php echo wp_json_encode( function_exists( 'oyc_passage_brief_ai_ready' ) && oyc_passage_brief_ai_ready() ); ?>;
var PB_NONCE=<?php echo wp_json_encode( function_exists( 'wp_create_nonce' ) ? wp_create_nonce( 'oyc_passage_brief' ) : '' ); ?>;
var PB_LOGGED=<?php echo wp_json_encode( is_user_logged_in() ); ?>;
var PB_ADMIN=<?php echo wp_json_encode( current_user_can( 'manage_options' ) ); ?>;
var PASS={
	boat:'Shannon 43',floor:6.5,
	/* Shannon 43 polars: boat speed (kt), rows = TWA°, columns = TWS kt */
	TWA:[40,52,60,75,90,110,120,135,150,165],TWS:[4,6,8,10,12,14,16,20,24],
	V:[[1.2,2.1,3.0,3.8,4.4,4.8,5.0,5.2,5.1],
	   [2.0,3.1,4.2,5.1,5.7,6.1,6.4,6.6,6.5],
	   [2.3,3.5,4.7,5.6,6.3,6.7,7.0,7.2,7.1],
	   [2.4,3.8,5.0,6.0,6.7,7.1,7.4,7.6,7.6],
	   [2.4,3.8,5.1,6.2,6.9,7.3,7.6,7.9,8.0],
	   [2.3,3.7,5.0,6.1,6.9,7.4,7.8,8.2,8.3],
	   [2.2,3.5,4.9,5.9,6.7,7.3,7.7,8.2,8.3],
	   [1.9,3.1,4.3,5.5,6.3,6.9,7.4,8.0,8.3],
	   [1.6,2.6,3.7,4.8,5.7,6.4,6.9,7.6,8.1],
	   [1.4,2.4,3.4,4.5,5.4,6.0,6.6,7.3,7.8]],
	/* "Load Bermuda route" drops these as My waypoints */
	BERMUDA:[{n:'OYC',lat:40.930,lon:-73.720},{n:'The Race',lat:41.230,lon:-72.090,race:true},{n:'Gulf Stream crossing',lat:38.4,lon:-70.0,gs:true},{n:"St. George’s",lat:32.37,lon:-64.68}],
	TIDE_ST:'LIS1017',TIDE:null,TIDE_KEY:'',SEAS:null,SEAS_KEY:'',
	res:null,ai:null,busy:false,status:'',departMs:null,
	/* The Race (tidal gate): current applies to any route that passes within 8 nm of it */
	RACE:{lat:41.230,lon:-72.090},
	/* the passage IS My waypoints (pins): Pt 1 → … → last, or from My boat. picking = 'from'/'to' while a start/finish tap is awaited */
	picking:null
};
/* Restore My waypoints. Once only: a start/finish route saved by the old planner
   (oyc_pass_route) becomes waypoints, with The Race if it was routed that way. */
(function(){
	var saved=null,old=null;try{saved=JSON.parse(localStorage.getItem(PINS_KEY)||'null');old=JSON.parse(localStorage.getItem('oyc_pass_route')||'null');}catch(e){}
	if(!Array.isArray(saved)&&old&&old.route==='custom'&&old.custom&&old.custom.from&&old.custom.to){
		var C=old.custom,w=[C.from].concat(Array.isArray(C.via)?C.via:[]).concat([C.to]);
		if(C.viaRace!==false){for(var j=0;j<w.length-1;j++){if((pInSound(w[j])&&pOutside(w[j+1]))||(pOutside(w[j])&&pInSound(w[j+1]))){w.splice(j+1,0,{n:'The Race',lat:PASS.RACE.lat,lon:PASS.RACE.lon,race:1});break;}}}
		saved=w.map(function(q){return {lat:q.lat,lon:q.lon,n:(q.n&&q.n.indexOf('°')<0&&!/^WP \d/.test(q.n))?q.n:null,race:q.race?1:0};});
	}
	try{localStorage.removeItem('oyc_pass_route');}catch(e){}
	if(Array.isArray(saved)&&saved.length)loadPins(saved);
})();
/* known places, so a tapped point near one gets its name */
var PASS_PLACES=[{n:'OYC',lat:40.930,lon:-73.720},{n:"St. George’s",lat:32.37,lon:-64.68},{n:'The Race',lat:41.230,lon:-72.090},{n:'Newport',lat:41.485,lon:-71.325},{n:'Block Island',lat:41.17,lon:-71.58},{n:'Montauk',lat:41.07,lon:-71.93},{n:'Cape May',lat:38.93,lon:-74.90},{n:'Norfolk',lat:36.95,lon:-76.33},{n:'Beaufort NC',lat:34.70,lon:-76.66}];
function pLL(lat,lon){return Math.abs(lat).toFixed(2)+'°'+(lat>=0?'N':'S')+' '+Math.abs(lon).toFixed(2)+'°'+(lon<=0?'W':'E');}
function passPlaceName(lat,lon){for(var i=0;i<PASS_PLACES.length;i++){var q=PASS_PLACES[i];if(distNM(lat,lon,q.lat,q.lon)<3)return q.n;}return pLL(lat,lon);}
/* does a leg from a (inside Long Island Sound) to b (out past it) have to go through The Race? */
function pInSound(q){return q.lat>40.75&&q.lat<41.4&&q.lon<-72.15&&q.lon>-74.05;}
function pOutside(q){return q.lon>-72.05||q.lat<40.75;}
/* the passage as waypoints [{n,lat,lon,pid}]: My boat (when the route runs from it) then
   My waypoints in order; null until there are two points */
function passWps(){
	if(typeof pins==='undefined')return null;
	var o=(typeof routeOrigin==='function')?routeOrigin():null,out=o?[{n:'My boat',lat:o.lat,lon:o.lon,boat:true}]:[];
	pins.forEach(function(p,k){out.push({n:p.name||('WP '+(k+1)),lat:p.lat,lon:p.lon,race:!!p.race,gs:!!p.gs,pid:p.id});});
	return out.length>=2?out:null;
}
/* fingerprint of a route: a run is "current" while the waypoints still match it
   (My boat to ~0.5 nm, so GPS jitter doesn't count as a change) */
function passRouteKey(w){var s=w.map(function(q){return q.boat?'b'+q.lat.toFixed(2)+','+q.lon.toFixed(2):q.lat.toFixed(4)+','+q.lon.toFixed(4)+q.n;}).join('|'),h=0;
	for(var i=0;i<s.length;i++){h=(h*31+s.charCodeAt(i))|0;}return 'r'+(h>>>0).toString(36);}
/* the Passage brief run, while it still matches the waypoints. Its ETAs and leg
   details stay on the waypoint cards and table whether or not the brief is open
   (Brad, 2026-11-15); the run is kept up to date from My waypoints either way. */
function passCur(){
	if(typeof PASS==='undefined'||!PASS.res)return null;
	var w=passWps();return (w&&passRouteKey(w)===PASS.res.key)?PASS.res:null;
}
/* that run's ETA at one of My waypoints (departure time at the first point) */
function passEtaFor(pin){var r=passCur();if(!r)return null;for(var i=0;i<r.wps.length;i++)if(r.wps[i].pid===pin.id)return i===0?r.depart:r.legs[i-1].end;return null;}
/* that run's leg ENDING at one of My waypoints (null for the start): course, wind, sailing, seas, comfort */
function passLegFor(pin){var r=passCur();if(!r)return null;for(var i=1;i<r.wps.length;i++)if(r.wps[i].pid===pin.id)return r.legs[i-1];return null;}
/* motor share of a leg as a short tag */
function passMotorTag(l){var mf=l.h?l.motorH/l.h:0;return mf>0.5?'MOTOR-SAIL':mf>0.15?'MOTOR '+Math.round(mf*100)+'%':'';}
/* My waypoints changed (added, moved, removed, reordered): re-route quietly so the
   ETAs follow; Claude's written brief is kept but marked out of date. */
var passPinsWait=null;
function passPinsChanged(){
	if(typeof PASS==='undefined'){clearTimeout(passPinsWait);passPinsWait=setTimeout(passPinsChanged,500);return;}   /* waypoints restored before the planner is set up */
	if(PASS.applying)return;
	clearTimeout(PASS.reT);
	PASS.reT=setTimeout(function(){
		var w=passWps(),k=w?passRouteKey(w):'';
		if(!PASS.res&&w&&!PASS.busy){passBuild({quiet:true});return;}   /* first run, brief open or not */
		if(PASS.res&&PASS.res.key!==k){
			if(PASS.busy){PASS.dirty=true;return;}
			if(w){passBuild({quiet:true});return;}
			PASS.res=null;PASS.prop=null;
		}
		if(!PASS.busy){renderPassCard();drawPassRoute();}
	},900);
}
/* The Race: when a leg leaves (or enters) the Sound and no waypoint is at The Race,
   the planner offers it. Returns the waypoint index to add it after, or -1. */
function passRaceTip(w){
	if(!w||PASS.raceNo===passRouteKey(w))return -1;
	for(var i=0;i<w.length;i++)if(distNM(w[i].lat,w[i].lon,PASS.RACE.lat,PASS.RACE.lon)<3)return -1;
	for(var j=0;j<w.length-1;j++)if((pInSound(w[j])&&pOutside(w[j+1]))||(pOutside(w[j])&&pInSound(w[j+1])))return j;
	return -1;
}
/* index in pins for "after route waypoint j" (j = the My boat point → the front) */
function passPinAfter(w,j){var id=w[j]&&w[j].pid;if(id==null)return 0;for(var k=0;k<pins.length;k++)if(pins[k].id===id)return k+1;return pins.length;}
function passPinOf(w,j){var id=w[j]&&w[j].pid;if(id==null)return null;for(var k=0;k<pins.length;k++)if(pins[k].id===id)return pins[k];return null;}
function passAddRace(){
	var w=passWps(),j=passRaceTip(w);if(j<0)return;
	addPin(PASS.RACE.lat,PASS.RACE.lon,{name:'The Race',race:true,at:passPinAfter(w,j)});
}
/* set the start (Pt 1) or finish (last waypoint) from a tap or a destination: moves
   that waypoint, or adds one when there isn't one to move. Other waypoints stay. */
function passSetEnd(k,lat,lon,name){
	var nm=(name&&name.indexOf('°')<0)?name:null;
	if(k==='from'){if(routeFromBoat)setFromBoat(false);if(pins.length)movePin(pins[0],lat,lon,nm);else addPin(lat,lon,{name:nm,race:nm==='The Race'});}
	else{var need=routeOrigin()?1:2;if(pins.length>=need)movePin(pins[pins.length-1],lat,lon,nm);else addPin(lat,lon,{name:nm,race:nm==='The Race'});}
}
/* "Load Bermuda route": the preset as My waypoints (replace them, or add after) */
function passLoadBermuda(how){
	PASS.bmAsk=false;PASS.applying=true;
	if(how==='replace'){while(pins.length)removePin(pins[0]);}
	PASS.BERMUDA.forEach(function(q){addPin(q.lat,q.lon,{name:q.n,race:!!q.race,gs:!!q.gs});});
	PASS.applying=false;PASS.res=null;PASS.ai=null;
	var c=document.getElementById('passCard');if(c)c._planOpen=false;
	passFit();passBuild();
}
/* chart tap while picking a start/finish: called first by the map click handler */
function passPick(e){
	if(!PASS.picking)return false;var c=document.getElementById('passCard');if(!c||!c.classList.contains('on')){PASS.picking=null;return false;}
	var lat=e.latlng.lat,lon=((e.latlng.lng+540)%360)-180,k=PASS.picking;
	PASS.picking=null;map.getContainer().classList.remove('pass-picking');
	passSetEnd(k,+lat.toFixed(4),+lon.toFixed(4),passPlaceName(lat,lon));   /* your other waypoints stay */
	c._planOpen=true;if(c._pickMin){c._pickMin=false;passSetMin(false);}
	renderPassCard();drawPassRoute();return true;
}
/* Start/Finish list: the preset destinations (and My boat as a start) */
function passChooseOpts(k){
	var h='<option value="">Choose a destination…</option><optgroup label="Destinations">';
	PASS_PLACES.forEach(function(q,i){h+='<option value="p:'+i+'">'+pEsc(q.n)+'</option>';});
	h+='</optgroup>';
	if(k==='from')h+='<optgroup label="Live"><option value="b">My boat (GPS)</option></optgroup>';
	return h;
}
function passChoose(k,v){
	PASS.picking=null;map.getContainer().classList.remove('pass-picking');
	var c=document.getElementById('passCard');c._planOpen=true;
	if(v==='b'){if(k==='from')setFromBoat(true);}
	else if(v.charAt(0)==='p'){var pl=PASS_PLACES[+v.slice(2)];if(pl)passSetEnd(k,pl.lat,pl.lon,pl.n);}
	renderPassCard();drawPassRoute();passFit();
}
function passStartPick(k){
	var c=document.getElementById('passCard');PASS.picking=k;c._planOpen=true;
	map.getContainer().classList.add('pass-picking');
	/* phones: drop the bottom sheet to its summary row so the chart is free to tap */
	if(window.matchMedia('(max-width:760px)').matches&&!c._min){c._pickMin=true;passSetMin(true);}
	renderPassCard();
}
var passLayer=L.layerGroup();
function pEsc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function pTz(ms,o){try{return new Date(ms).toLocaleString('en-US',Object.assign({timeZone:'America/New_York'},o));}catch(e){return new Date(ms).toLocaleString('en-US',o);}}
function pWhen(ms){return pTz(ms,{weekday:'short'})+' '+pTz(ms,{hour:'2-digit',minute:'2-digit',hour12:false});}
function pWhenLong(ms){return pTz(ms,{weekday:'short',day:'numeric',month:'short'})+' '+pTz(ms,{hour:'2-digit',minute:'2-digit',hour12:false});}
function pDur(h){var d=Math.floor(h/24),hh=Math.round(h-d*24);if(hh===24){d++;hh=0;}return d?(d+'d '+hh+'h'):(hh+'h');}
function pAng(a){a=((a%360)+360)%360;return a>180?360-a:a;}
function pMag(lon){return lon<-72.5?13:14;}   /* west variation, LIS ~13°, offshore/Bermuda ~14° */
/* polar speed at TWA 40-165 (clamped), TWS (below 4 kt scales to 0, above 24 held) */
function polarBsp(twa,tws){
	var A=PASS.TWA,S=PASS.TWS,V=PASS.V;
	twa=Math.max(A[0],Math.min(A[A.length-1],twa));
	var scale=1;if(tws<S[0]){scale=Math.max(0,tws)/S[0];tws=S[0];}if(tws>S[S.length-1])tws=S[S.length-1];
	var i=0;while(i<A.length-2&&twa>A[i+1])i++;var j=0;while(j<S.length-2&&tws>S[j+1])j++;
	var fa=(twa-A[i])/(A[i+1]-A[i]),fs=(tws-S[j])/(S[j+1]-S[j]);
	var v=V[i][j]*(1-fa)*(1-fs)+V[i+1][j]*fa*(1-fs)+V[i][j+1]*(1-fa)*fs+V[i+1][j+1]*fa*fs;
	return v*scale;
}
/* best speed made good along a course at TWA a: straight, or tacking/gybing on both boards */
function sailSpeed(a,tws){
	var best={v:(a>=40&&a<=165)?polarBsp(a,tws):0,how:''},t,v;
	if(a<60)for(t=40;t<=60;t+=2){if(t<a)continue;v=polarBsp(t,tws)*Math.cos(t*Math.PI/180)/Math.cos(a*Math.PI/180);if(v>best.v)best={v:v,how:'tack'};}
	if(a>135)for(t=135;t<=165;t+=3){if(t>a)continue;v=polarBsp(t,tws)*Math.cos((180-t)*Math.PI/180)/Math.cos((180-a)*Math.PI/180);if(v>best.v)best={v:v,how:'gybe'};}
	if(best.how===''&&best.v<=0)best={v:0,how:'tack'};
	return best;
}
function pPos(twa,how){if(how==='tack')return 'Beat';if(how==='gybe')return 'Run (gybing)';return twa<60?'Close-hauled':twa<80?'Close reach':twa<110?'Beam reach':twa<150?'Broad reach':'Run';}
function pDest(lat,lon,brg,nm){var R=3440.065,d=nm/R,b=brg*Math.PI/180,p1=lat*Math.PI/180,l1=lon*Math.PI/180;
	var p2=Math.asin(Math.sin(p1)*Math.cos(d)+Math.cos(p1)*Math.sin(d)*Math.cos(b));
	var l2=l1+Math.atan2(Math.sin(b)*Math.sin(d)*Math.cos(p1),Math.cos(d)-Math.sin(p1)*Math.sin(p2));
	return {lat:p2*180/Math.PI,lon:((l2*180/Math.PI+540)%360)-180};}
/* forecast wind at a place and an epoch: interpolated between the 3-hourly frames (null past the forecast) */
function windAtTime(lat,lon,ms){
	if(TIMES.length<2)return null;
	var t0=new Date(TIMES[0]).getTime(),step=new Date(TIMES[1]).getTime()-t0,f=(ms-t0)/step;
	if(f<0)f=0;if(f>TIMES.length-1)return null;
	var i0=Math.floor(f),i1=Math.min(i0+1,TIMES.length-1),fr=f-i0;
	var a=sampleBest(lat,lon,i0),b=sampleBest(lat,lon,i1);if(!a)return b;if(!b)return a;
	function uv(s){var r=s.dir*Math.PI/180;return [-s.kt*Math.sin(r),-s.kt*Math.cos(r)];}
	var p=uv(a),q=uv(b),u=p[0]*(1-fr)+q[0]*fr,v=p[1]*(1-fr)+q[1]*fr;
	return {kt:Math.sqrt(u*u+v*v),dir:(Math.atan2(-u,-v)*180/Math.PI+360)%360};
}
/* The Race tidal current (kt; + = flood setting 255° into the Sound, − = ebb 073° out) */
function tideAt(ms){var T=PASS.TIDE;if(!T||!T.length)return null;var b=null,bd=1/0;
	for(var i=0;i<T.length;i++){var d=Math.abs(T[i].t-ms);if(d<bd){bd=d;b=i;}}
	if(bd>2*36e5)return null;var x=T[b],y=T[x.t<=ms?Math.min(b+1,T.length-1):Math.max(b-1,0)];
	if(y===x)return x.v;var fr=(ms-x.t)/(y.t-x.t);return x.v+(y.v-x.v)*fr;}
function nextSlack(ms){var T=PASS.TIDE;if(!T)return null;for(var i=1;i<T.length;i++){if(T[i].t<=ms)continue;if((T[i-1].v<=0)!==(T[i].v<=0)){var f=T[i-1].v/(T[i-1].v-T[i].v);return T[i-1].t+f*(T[i].t-T[i-1].t);}}return null;}
function seasAt(lat,lon,ms){var S=PASS.SEAS;if(!S)return null;var bi=-1,bd=1/0;
	for(var i=0;i<S.pts.length;i++){var d=distNM(lat,lon,S.pts[i].lat,S.pts[i].lon);if(d<bd){bd=d;bi=i;}}
	if(bi<0||bd>150)return null;var p=S.pts[bi],k=Math.round((ms-S.t0)/36e5);if(k<0||k>=p.h.length)return null;return p.h[k];}
/* comfort class for one moment: easy, lively or rough (null past the forecast).
   Upwind (TWA under 55° or tacking) is judged harsher: the boat is heeled and slamming into the sea. */
function passCls(w,twa,how,sea){
	if(!w)return null;var k=w.kt,s=sea||0,up=twa<55||how==='tack',dn=twa>155||how==='gybe';
	if(k>=28||s>=10||(up&&(k>=20||s>=7)))return 'rough';
	if(k>=18||s>=6||(up&&(k>=13||s>=4))||(dn&&s>=5))return 'lively';
	return 'easy';
}
function pCfTxt(cf,tot){var t=tot||(cf.easy+cf.lively+cf.rough);if(!t)return '—';var f=function(x){return Math.round(100*x/t);};
	return f(cf.easy)+'% easy · '+f(cf.lively)+'% lively · '+f(cf.rough)+'% rough';}
function gsWallLat(list,lon){if(!list||!list.length)return null;var b=null,bd=1/0;list.forEach(function(c){var d=Math.abs(c[1]-lon);if(d<bd){bd=d;b=c;}});return bd<=1.5?b[0]:null;}

/* step the boat along the route from departMs. Returns per-leg results + flags. */
function passRun(wps,departMs){
	var STEP=0.5,FL=PASS.floor,t=departMs,pos={lat:wps[0].lat,lon:wps[0].lon},legs=[],track=[{lat:pos.lat,lon:pos.lon,t:t}],noFc=0,gate=null,maxKt=0,gs={},tl=[],nextS=departMs,CF={easy:0,lively:0,rough:0},upH=0;
	var RP=PASS.RACE,raceMin=1/0,raceT=null,hasW=(typeof GSW!=='undefined'&&GSW),nWall=null,sWall=null,anyWall=false;
	for(var i=0;i<wps.length-1;i++){
		var to=wps[i+1],rem=distNM(pos.lat,pos.lon,to.lat,to.lon),legNm=rem,brg0=brgTrue(pos.lat,pos.lon,to.lat,to.lon);
		var L0={from:wps[i].n,to:to.n,nm:legNm,brgM:(brg0+pMag(pos.lon))%360,start:t,h:0,motorH:0,su:0,sv:0,n:0,ktMin:1/0,ktMax:0,twaS:0,how:{},cf:{easy:0,lively:0,rough:0},upH:0,seaMax:null,noFc:0,gs:!!to.gs};
		var guard=0;
		while(rem>0.01&&guard++<4000){
			var brg=brgTrue(pos.lat,pos.lon,to.lat,to.lon),w=windAtTime(pos.lat,pos.lon,t),eff,twa=null,how='';
			if(w){twa=pAng(w.dir-brg);var s=sailSpeed(twa,w.kt);eff=s.v;how=s.how;
				var r=w.dir*Math.PI/180;L0.su+=-w.kt*Math.sin(r);L0.sv+=-w.kt*Math.cos(r);L0.n++;L0.twaS+=twa;
				L0.ks=(L0.ks||0)+w.kt;if(L0.d0==null)L0.d0=w.dir;L0.d1=w.dir;L0.ktMin=Math.min(L0.ktMin,w.kt);L0.ktMax=Math.max(L0.ktMax,w.kt);maxKt=Math.max(maxKt,w.kt);}
			else{eff=0;L0.noFc++;noFc++;}
			var motor=eff<FL,sog=Math.max(eff,FL);
			/* tidal current through The Race: within 8 nm either side of it, on any route */
			var dR=distNM(pos.lat,pos.lon,RP.lat,RP.lon);if(dR<raceMin){raceMin=dR;raceT=t;}
			if(dR<8){var c=tideAt(t);if(c!=null){var set=c>=0?255:73;sog=Math.max(2,sog+Math.abs(c)*Math.cos((brg-set)*Math.PI/180));}}
			var d=Math.min(rem,sog*STEP),dt=d/sog;
			var sea=seasAt(pos.lat,pos.lon,t);if(sea!=null)L0.seaMax=Math.max(L0.seaMax==null?0:L0.seaMax,sea);
			/* comfort: how the boat would feel this half hour */
			var cls=passCls(w,twa,how,sea);if(cls){L0.cf[cls]+=dt;CF[cls]+=dt;}
			var upw=!!w&&!motor&&(twa<55||how==='tack');if(upw){L0.upH+=dt;upH+=dt;}
			if(t>=nextS){nextS+=6*36e5;tl.push({t:t,leg:i,lat:pos.lat,lon:pos.lon,kt:w?w.kt:null,dir:w?w.dir:null,twa:twa,how:how,motor:motor,sea:sea,cls:cls});}
			/* Gulf Stream: note the wind as the boat crosses between the walls (walls taken at the boat's longitude) */
			var nw=hasW?gsWallLat(GSW.north,pos.lon):null,sw=hasW?gsWallLat(GSW.south,pos.lon):null;if(nw!=null)anyWall=true;
			if(nw!=null&&sw!=null&&pos.lat<=nw&&pos.lat>=sw){if(gs.inT==null){gs.inT=t;nWall=nw;sWall=sw;gs.lon=pos.lon;}gs.outT=t+dt*36e5;if(w){gs.kt=Math.max(gs.kt||0,w.kt);gs.dirs=(gs.dirs||[]).concat([w.dir]);}}
			pos=pDest(pos.lat,pos.lon,brg,d);t+=dt*36e5;rem-=d;L0.h+=dt;if(motor)L0.motorH+=dt;if(how)L0.how[how]=(L0.how[how]||0)+dt;
			track.push({lat:pos.lat,lon:pos.lon,t:t});
		}
		pos={lat:to.lat,lon:to.lon};L0.end=t;
		if(L0.n){L0.tws=L0.ks/L0.n;L0.twd=(Math.atan2(-L0.su,-L0.sv)*180/Math.PI+360)%360;L0.ktAvg=null;L0.twa=L0.twaS/L0.n;}
		L0.bsp=L0.nm/L0.h;var hw='';Object.keys(L0.how).forEach(function(k){if(L0.how[k]>L0.h*0.4)hw=k;});L0.pos=L0.twa==null?'—':pPos(L0.twa,hw);
		legs.push(L0);
	}
	if(raceMin<8&&raceT!=null){var cv=tideAt(raceT);gate={t:raceT,v:cv,slack:nextSlack(raceT)};}
	var tot=legs.reduce(function(a,l){return a+l.nm;},0),hrs=(t-departMs)/36e5,mot=legs.reduce(function(a,l){return a+l.motorH;},0);
	var arrW=windAtTime(wps[wps.length-1].lat,wps[wps.length-1].lon,t-6*36e5);
	var flags=[];
	if(gate&&gate.v!=null&&gate.v>0.4)flags.push({k:'tide',s:2,txt:'Foul flood at The Race ('+gate.v.toFixed(1)+' kt against you)'});
	if(gs.inT!=null&&gs.dirs){var wac=gs.dirs.some(function(d){return d>=10&&d<=130;});if(wac&&(gs.kt||0)>=12)flags.push({k:'gs',s:(gs.kt>=20?3:2),txt:'Wind against the Gulf Stream ('+card(gs.dirs[0])+' '+Math.round(gs.kt)+' kt): steep, breaking seas'});}
	if(arrW&&arrW.kt>=20){flags.push({k:'arr',s:arrW.kt>=28?3:2,txt:'Arrival into '+card(arrW.dir)+' '+Math.round(arrW.kt)+' kt'});}
	if(maxKt>=34)flags.push({k:'gale',s:3,txt:'Gale-force wind on the route (to '+Math.round(maxKt)+' kt)'});
	var seaMax=legs.reduce(function(a,l){return l.seaMax!=null?Math.max(a,l.seaMax):a;},0);
	if(seaMax>=12)flags.push({k:'sea',s:3,txt:'Seas to '+Math.round(seaMax)+' ft'});else if(seaMax>=8)flags.push({k:'sea',s:2,txt:'Seas to '+Math.round(seaMax)+' ft'});
	if(mot>hrs*0.4)flags.push({k:'motor',s:1,txt:'Light air: about '+Math.round(mot)+' h of motor-sailing'});
	if(noFc)flags.push({k:'fc',s:1,txt:'Last '+Math.round(noFc*STEP)+' h run past the 7-day forecast (assumed '+PASS.floor+' kt)'});
	var score=hrs+0.4*mot+flags.reduce(function(a,f){return a+(f.k==='fc'||f.k==='motor'?0:f.s*4);},0);
	return {cf:CF,upH:upH,tl:tl,depart:departMs,eta:t,nm:tot,hrs:hrs,motorH:mot,legs:legs,track:track,gate:gate,gs:{nWall:nWall,sWall:sWall,lon:gs.lon,avail:!!(hasW&&anyWall),inT:gs.inT,outT:gs.outT,kt:gs.kt,dir:gs.dirs?gs.dirs[Math.floor(gs.dirs.length/2)]:null},arr:arrW,maxKt:maxKt,seaMax:seaMax||null,flags:flags,score:score,noFcH:noFc*STEP};
}
/* try departures 3 h apart from 24 h before to 72 h after the chosen time (not in the past, inside the forecast) */
function passScan(wps,base){
	var now=Date.now(),end=TIMES.length?new Date(TIMES[TIMES.length-1]).getTime():now,out=[];
	for(var o=-24;o<=72;o+=3){var dm=base+o*36e5;if(dm<now-36e5||dm>end-24*36e5)continue;var r=passRun(wps,dm);if(r.noFcH>0)continue;out.push({depart:dm,eta:r.eta,score:r.score,hrs:r.hrs,flags:r.flags,gate:r.gate});}
	out.sort(function(a,b){return a.score-b.score;});return out[0]||null;
}
/* leg wind: direction at the start (â at the end if it swings 45Â°+) and the speed range */
function pLegWind(l){var a=card(l.d0),b=card(l.d1),sw=pAng(l.d1-l.d0)>=45;var lo=Math.round(l.ktMin),hi=Math.round(l.ktMax);
	return (sw?a+'→'+b:card(l.twd))+' '+(hi-lo>=3?lo+'–'+hi:Math.round(l.tws))+' kt';}
function passVerdict(r){var s=r.flags.reduce(function(a,f){return Math.max(a,f.s);},0);
	return s>=3?{lvl:'hold',tag:'HOLD'}:s===2?{lvl:'watch',tag:'GO · WATCH'}:{lvl:'go',tag:'GO'};}
function passSummary(r){
	var v=passVerdict(r),bits=[];
	bits.push(Math.round(r.nm)+' nm in about '+pDur(r.hrs)+', arriving '+pWhenLong(r.eta)+' ET.');
	if(r.motorH>r.hrs*0.3)bits.push('Expect around '+Math.round(r.motorH)+' h of motor-sailing in light air.');
	r.flags.filter(function(f){return f.s>=2;}).forEach(function(f){bits.push(f.txt+'.');});
	if(r.best&&Math.abs(r.best.depart-r.depart)>=3*36e5&&r.best.score<r.score-2)bits.push('A better window: leave '+pWhenLong(r.best.depart)+' to arrive '+pWhenLong(r.best.eta)+'.');
	return {verdict:v,text:bits.join(' ')};
}
/* data loaders: Atlantic wind grid, The Race current, Gulf Stream walls, seas along the route */
function passWaitBasin(cb){
	if(BASIN.loaded&&!BASIN.loading)return cb();
	try{loadBasin();}catch(e){}
	var t0=Date.now();(function poll(){if((BASIN.loaded&&!BASIN.loading)||Date.now()-t0>120000)return cb();setTimeout(poll,1000);})();
}
function passLoadTide(ms,cb){
	function ymd(d){return d.getUTCFullYear()+String(d.getUTCMonth()+1).padStart(2,'0')+String(d.getUTCDate()).padStart(2,'0');}
	var b=new Date(ms-24*36e5),e=new Date(ms+9*864e5),key=ymd(b)+ymd(e);if(PASS.TIDE&&PASS.TIDE_KEY===key)return cb();
	fetchT('https://api.tidesandcurrents.noaa.gov/api/prod/datagetter?product=currents_predictions&application=OYC_wind_map&time_zone=gmt&units=english&format=json&interval=30&station='+PASS.TIDE_ST+'&begin_date='+ymd(b)+'&end_date='+ymd(e),15000)
		.then(function(r){return r.json();}).then(function(j){var a=(j.current_predictions&&j.current_predictions.cp)||[];
			PASS.TIDE=a.map(function(p){return {t:new Date(p.Time.replace(' ','T')+'Z').getTime(),v:+p.Velocity_Major};});PASS.TIDE_KEY=key;})
		.catch(function(){PASS.TIDE=null;}).then(cb);
}
function passLoadSeas(wps,cb){
	var key=wps.map(function(w){return w.lat+','+w.lon;}).join('|');if(PASS.SEAS&&PASS.SEAS_KEY===key)return cb();
	var pts=[];for(var i=0;i<wps.length-1;i++){var a=wps[i],b=wps[i+1],d=distNM(a.lat,a.lon,b.lat,b.lon),n=Math.max(1,Math.round(d/110)),br=brgTrue(a.lat,a.lon,b.lat,b.lon);
		for(var k=(i===0?0:1);k<=n;k++){var p=pDest(a.lat,a.lon,br,d*k/n);pts.push({lat:+p.lat.toFixed(2),lon:+p.lon.toFixed(2)});}}
	pts=pts.filter(function(p){return !(p.lon<-72.3);});   /* inside the Sound the waves layer already covers it */
	if(!pts.length)return cb();
	fetchT('https://marine-api.open-meteo.com/v1/marine?latitude='+pts.map(function(p){return p.lat;}).join(',')+'&longitude='+pts.map(function(p){return p.lon;}).join(',')+'&hourly=wave_height&length_unit=imperial&forecast_days=8&timezone=GMT',15000)
		.then(function(r){if(!r.ok)throw 0;return r.json();}).then(function(arr){arr=Array.isArray(arr)?arr:[arr];var t0=new Date(arr[0].hourly.time[0]+'Z').getTime();
			PASS.SEAS={t0:t0,pts:pts.map(function(p,i){return {lat:p.lat,lon:p.lon,h:(arr[i]&&arr[i].hourly.wave_height)||[]};})};PASS.SEAS_KEY=key;})
		.catch(function(){PASS.SEAS=null;}).then(cb);
}
/* opt.quiet: a re-run after My waypoints changed. Keeps Claude's brief (marked out of
   date) and the chat, and doesn't log the window for the track record. */
function passBuild(opt){
	var quiet=!!(opt&&opt.quiet);
	if(PASS.busy)return;PASS.busy=true;if(!quiet){PASS.ai=null;PASS.aiStale=false;}
	var wps=passWps();
	if(!wps){PASS.busy=false;PASS.status='';PASS.res=null;renderPassCard();return;}
	function st(s){PASS.status=s;renderPassCard();}
	if(!TIMES.length){st('Waiting for the wind forecast…');var t0=Date.now();(function w(){if(TIMES.length||Date.now()-t0>60000){PASS.busy=false;passBuild();return;}setTimeout(w,800);})();PASS.busy=true;return;}
	if(PASS.departMs==null){var n=new Date();n.setMinutes(0,0,0);PASS.departMs=n.getTime()+36e5;}
	st('Loading the Atlantic wind field…');
	passWaitBasin(function(){st('Tides, Gulf Stream and seas…');
		var left=3;function one(){if(--left)return;st('Routing…');setTimeout(function(){
			wps=passWps()||wps;   /* the waypoints may have moved while the data loaded */
			var r=passRun(wps,PASS.departMs);r.best=passScan(wps,PASS.departMs);r.wps=wps;r.key=passRouteKey(wps);PASS.res=r;
			if(quiet){if(PASS.ai)PASS.aiStale=true;}else{PASS.chat=PASS.chatKeep||[];PASS.chatKeep=null;}
			PASS.chatErr='';PASS.prop=null;PASS.busy=false;PASS.status='';
			renderPassCard();drawPassRoute();rerenderCallouts();   /* ETAs onto My waypoints' cards, table and route bar */
			if(!quiet){passLog(r,'chosen');if(r.best&&Math.abs(r.best.depart-r.depart)>=3*36e5)passLog(passRun(wps,r.best.depart),'best');}
			if(!PASS.rec)passLoadRecord();
			if(PASS.dirty){PASS.dirty=false;passPinsChanged();}},30);}
		passLoadTide(PASS.departMs,one);passLoadSeas(wps,one);
		if(typeof loadGSWall==='function')loadGSWall(one);else one();
	});
}
/* compact facts for Claude: only computed numbers, so the brief can't invent any */
function passFacts(r){
	return {boat:PASS.boat,route:r.wps.map(function(w){return w.n;}).join(' -> '),motoring_floor_kt:PASS.floor,
		depart:pWhenLong(r.depart)+' ET',eta:pWhenLong(r.eta)+' ET',distance_nm:Math.round(r.nm),hours:Math.round(r.hrs),motor_hours:Math.round(r.motorH),
		legs:r.legs.map(function(l){return {leg:l.from+' -> '+l.to,course_mag:Math.round(l.brgM),nm:Math.round(l.nm),wind:l.tws==null?'beyond forecast':pLegWind(l),
			twa:l.twa==null?null:Math.round(l.twa),point_of_sail:l.pos,avg_speed_kt:+l.bsp.toFixed(1),motor_hours:Math.round(l.motorH),max_seas_ft:l.seaMax==null?null:Math.round(l.seaMax),eta:pWhenLong(l.end)+' ET'};}),
		race_gate:r.gate&&r.gate.v!=null?{at:pWhenLong(r.gate.t)+' ET',current_kt:+r.gate.v.toFixed(1),state:Math.abs(r.gate.v)<0.3?'near slack':r.gate.v<0?'ebb (fair, sets 073)':'flood (foul, sets 255)',next_slack:r.gate.slack?pWhenLong(r.gate.slack)+' ET':null}:null,
		gulf_stream:r.gs.inT!=null?{north_wall_lat:r.gs.nWall,south_wall_lat:r.gs.sWall,in_stream:r.gs.inT?pWhenLong(r.gs.inT)+' to '+pWhenLong(r.gs.outT)+' ET':null,wind:r.gs.dir!=null?card(r.gs.dir)+' up to '+Math.round(r.gs.kt)+' kt':null}:null,
		arrival_wind:r.arr?card(r.arr.dir)+' '+Math.round(r.arr.kt)+' kt':null,max_wind_kt:Math.round(r.maxKt),max_seas_ft:r.seaMax==null?null:Math.round(r.seaMax),
		flags:r.flags.map(function(f){return f.txt;}),
		best_departure:r.best?{depart:pWhenLong(r.best.depart)+' ET',eta:pWhenLong(r.best.eta)+' ET',hours:Math.round(r.best.hrs)}:null,
		track_record:PASS.rec&&PASS.rec.verified?{windows_checked:PASS.rec.verified,go_calls:PASS.rec.go,go_calls_without_dangerous_crossing:PASS.rec.go_good,hold_calls:PASS.rec.hold,hold_calls_right:PASS.rec.hold_good,avg_wind_error_kt:PASS.rec.wind_mae}:null,
		forecast_source:'GFS via Open-Meteo, 3-hourly; The Race NOAA LIS1017; Gulf Stream walls NOAA/NAVO; seas Open-Meteo marine'};
}
function passAskClaude(){
	var r=PASS.res;if(!r||PASS.aiBusy)return;PASS.aiBusy=true;PASS.aiErr='';PASS.scrollTo='#pcTake';renderPassCard();
	var fd=new FormData();fd.append('action','oyc_passage_brief');fd.append('nonce',PB_NONCE);fd.append('facts',JSON.stringify(passFacts(r)));
	fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'}).then(function(x){return x.json();}).then(function(j){
		if(j&&j.ok&&j.brief){PASS.ai=j.brief;PASS.aiStale=false;}else PASS.aiErr=(j&&j.msg)||'Claude couldn’t write the brief just now.';
	}).catch(function(){PASS.aiErr='Claude couldn’t write the brief just now.';}).then(function(){PASS.aiBusy=false;PASS.scrollTo='#pcTake';renderPassCard();});
}
/* ── conversation with Claude about this passage ──
   Claude gets the routed facts plus comfort (per leg and every 6 h along the
   track) and a few alternatives the router has actually run: doglegs either
   side of the rhumb line on the longest leg (different wind angles) and the
   most comfortable departure in the next 3 days. So "would another point of
   sail be more comfortable?" is answered from numbers, not guesses. */
function passAltSum(label,x,w2,extra){
	var o={option:label,depart:pWhenLong(x.depart)+' ET',eta:pWhenLong(x.eta)+' ET',distance_nm:Math.round(x.nm),hours:Math.round(x.hrs),
		comfort_hours:{easy:Math.round(x.cf.easy),lively:Math.round(x.cf.lively),rough:Math.round(x.cf.rough)},hours_to_windward:Math.round(x.upH),motor_hours:Math.round(x.motorH),
		max_wind_kt:Math.round(x.maxKt),max_seas_ft:x.seaMax==null?null:Math.round(x.seaMax),
		legs:x.legs.map(function(l){return {leg:l.from+' -> '+l.to,point_of_sail:l.pos,twa:l.twa==null?null:Math.round(l.twa),wind:l.tws==null?null:pLegWind(l)};}),
		flags:x.flags.map(function(f){return f.txt;})};
	if(extra)for(var k in extra)o[k]=extra[k];return o;
}
function passAlts(r){
	if(r.alts)return r.alts;var W=r.wps,out=[],li=0;
	r.legs.forEach(function(l,i){if(l.nm>r.legs[li].nm)li=i;});
	var a=W[li],b=W[li+1],L=r.legs[li];
	if(L.nm>=120){var br=brgTrue(a.lat,a.lon,b.lat,b.lon),mid=pDest(a.lat,a.lon,br,L.nm/2);
		[-1,1].forEach(function(sg){[0.12,0.25].forEach(function(f){
			var off=Math.max(10,Math.round(L.nm*f/5)*5),ob=(br+90*sg+360)%360,q=pDest(mid.lat,mid.lon,ob,off);
			var w2=W.slice(0,li+1).concat([{n:'Dogleg',lat:q.lat,lon:q.lon}]).concat(W.slice(li+1)),x=passRun(w2,r.depart);
			if(x.noFcH>0)return;
			out.push(passAltSum('Dogleg '+off+' nm '+card(ob)+' of the rhumb line on the '+a.n+' to '+b.n+' leg',x,w2,{dogleg_waypoint:{lat:+q.lat.toFixed(2),lon:+q.lon.toFixed(2)}}));});});}
	var now=Date.now(),end=TIMES.length?new Date(TIMES[TIMES.length-1]).getTime():now,best=null,bs=1/0;
	for(var o=-24;o<=72;o+=6){var dm=r.depart+o*36e5;if(o===0||dm<now-36e5||dm>end-24*36e5)continue;
		var x=passRun(W,dm);if(x.noFcH>0)continue;var sc=x.cf.rough*3+x.cf.lively+x.upH*0.3;if(sc<bs){bs=sc;best=x;}}
	if(best)out.push(passAltSum('Same route, leaving '+pWhenLong(best.depart)+' ET (the most comfortable departure within 3 days)',best,W));
	r.alts=out;return out;
}
function passChatFacts(r){
	if(r.chatFacts)return r.chatFacts;
	var f=passFacts(r);
	f.comfort={definitions:'easy = under 18 kt and seas under 6 ft (upwind: under 13 kt and 4 ft); rough = 28+ kt, 10+ ft seas, or upwind in 20+ kt or 7+ ft; lively = in between, or running dead downwind in 5+ ft (rolly). Upwind = TWA under 55 degrees or tacking.',
		hours:{easy:Math.round(r.cf.easy),lively:Math.round(r.cf.lively),rough:Math.round(r.cf.rough)},hours_to_windward:Math.round(r.upH),
		legs:r.legs.map(function(l){return {leg:l.from+' -> '+l.to,easy_h:Math.round(l.cf.easy),lively_h:Math.round(l.cf.lively),rough_h:Math.round(l.cf.rough),to_windward_h:Math.round(l.upH)};})};
	f.timeline_every_6h=r.tl.map(function(p){return {at:pWhenLong(p.t)+' ET',leg:r.legs[p.leg]?r.legs[p.leg].from+' -> '+r.legs[p.leg].to:'',pos:p.lat.toFixed(1)+','+p.lon.toFixed(1),
		wind:p.kt==null?'beyond forecast':card(p.dir)+' '+Math.round(p.kt)+' kt',twa:p.twa==null?null:Math.round(p.twa),sailing:p.motor?'motor-sailing':(p.twa==null?null:pPos(p.twa,p.how)),seas_ft:p.sea==null?null:+p.sea.toFixed(1),comfort:p.cls};});
	f.route_waypoints=r.wps.map(function(q,i){return {index:i,name:q.n,lat:+q.lat.toFixed(3),lon:+q.lon.toFixed(3)};});
	f.alternatives=passAlts(r);
	f.alternatives_note='Alternatives were run through the same router and forecast. Dogleg waypoints are not checked against land or shipping lanes.';
	/* the chart check on My waypoints (land, and water under the boat's safety depth) */
	if(SW.res){f.chart_check={safety_depth_ft:SW.res.ft,draft_ft:boatDraft(),problems:[]};
		pins.forEach(function(p,i){var l=SW.res.legs[p.id],c=SW.res.pins[p.id];
			if(c===2)f.chart_check.problems.push('waypoint '+(i+1)+' is on land');
			if(l&&(l.st==='land'||l.st==='shallow'))f.chart_check.problems.push('the leg into waypoint '+(i+1)+' crosses '+(l.st==='land'?'land':'water under '+SW.res.ft+' ft'));});
		f.chart_check.note='Problems can be fixed with the Autoroute button. Any waypoint you suggest is checked against the chart before the member applies it.';}
	r.chatFacts=f;return f;
}
/* Claude's suggested changes, one by one: each waypoint add / move / remove and a new
   departure can be approved or rejected on its own. The router re-runs the
   approved set so the comparison is always real numbers. */
function passPropose(pr){
	var r=PASS.res;if(!r||!pr)return;var n=r.wps.length,ch=[];
	(pr.changes||[]).slice(0,10).forEach(function(c){
		var a=c.action,at=Math.round(+c.at);if(!isFinite(at)||at<0||at>n-1)return;
		if((a==='move'||a==='remove')&&(at===0||at===n-1))return;   /* start and finish stay */
		if(a==='add'&&at===n-1)at=n-2;
		if(a!=='remove'&&!(isFinite(c.lat)&&isFinite(c.lon)&&Math.abs(c.lat)<=70&&Math.abs(c.lon)<=180))return;
		ch.push({action:a,at:at,n:String(c.name||'Waypoint').slice(0,40),lat:+(+c.lat||0).toFixed(4),lon:+(+c.lon||0).toFixed(4),why:String(c.why||'').slice(0,300),st:null});
	});
	var dep=null;if(pr.depart){var m=String(pr.depart).match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/);if(m){var d=new Date(+m[1],+m[2]-1,+m[3],+m[4],+m[5]).getTime();if(isFinite(d)&&Math.abs(d-r.depart)>=36e5)dep={ms:d,why:String(pr.depart_why||'').slice(0,300),st:null};}}
	if(!ch.length&&!dep)return;
	PASS.prop={summary:String(pr.summary||'').slice(0,400),changes:ch,dep:dep};
	/* check every suggested point against the chart: land or shoal water is flagged on the card */
	ch.forEach(function(c){if(c.action==='remove')return;swPoint(c.lat,c.lon).then(function(k){if(k===2||k===1){c.sw=k;if(PASS.prop&&PASS.prop.changes===ch)renderPassCard();}});});
	passPropRun();drawPassRoute();
}
/* route + departure with the given changes applied (filter picks which) */
function passPropRoute(pick){
	var P=PASS.prop,r=PASS.res,W=r.wps.map(function(q){return {n:q.n,lat:q.lat,lon:q.lon,race:q.race,adds:[]};});
	P.changes.forEach(function(c){if(!pick(c))return;var w=W[c.at];
		if(c.action==='remove')w.gone=true;
		else if(c.action==='move'){w.n=c.n;w.lat=c.lat;w.lon=c.lon;w.race=distNM(c.lat,c.lon,PASS.RACE.lat,PASS.RACE.lon)<3;}
		else w.adds.push({n:c.n,lat:c.lat,lon:c.lon,race:distNM(c.lat,c.lon,PASS.RACE.lat,PASS.RACE.lon)<3});});
	var out=[];W.forEach(function(w){if(!w.gone)out.push({n:w.n,lat:w.lat,lon:w.lon,race:w.race});w.adds.forEach(function(a){out.push(a);});});
	out.forEach(function(q){if(q.race){q.n='The Race';q.lat=PASS.RACE.lat;q.lon=PASS.RACE.lon;}});
	return {wps:out,depart:(P.dep&&pick(P.dep))?P.dep.ms:r.depart};
}
function passPropRun(){
	var P=PASS.prop;if(!P)return;
	var all=passPropRoute(function(){return true;}),mine=passPropRoute(function(c){return c.st===true;});
	P.all=all;P.allRun=passRun(all.wps,all.depart);P.mine=mine;P.mineRun=passRun(mine.wps,mine.depart);
}
function passPropHTML(){
	var P=PASS.prop,r=PASS.res;if(!P||!r)return '';var A=P.allRun,M=P.mineRun,nOk=P.changes.filter(function(c){return c.st===true;}).length+(P.dep&&P.dep.st===true?1:0);
	function row(lbl,f){return '<tr><th>'+lbl+'</th><td>'+f(r)+'</td><td>'+f(A)+'</td><td>'+f(M)+'</td></tr>';}
	function yn(it,k){return '<span class="pc-yn"><button type="button" class="pc-y'+(it.st===true?' on':'')+'" data-pa="'+k+'" aria-pressed="'+(it.st===true)+'">✓ Approve</button><button type="button" class="pc-n'+(it.st===false?' on':'')+'" data-pr="'+k+'" aria-pressed="'+(it.st===false)+'">✕ Reject</button></span>';}
	var h='<div class="pc-prop"><h4>Claude suggests</h4>'+(P.summary?'<p>'+pEsc(P.summary)+'</p>':'')+'<ol class="pc-chg">';
	P.changes.forEach(function(c,k){var tgt=r.wps[c.at];
		var what=c.action==='add'?'Add <b>'+pEsc(c.n)+'</b> after '+pEsc(tgt.n):c.action==='move'?'Move <b>'+pEsc(tgt.n)+'</b>':'Remove <b>'+pEsc(tgt.n)+'</b>';
		h+='<li class="'+(c.st===true?'ok':c.st===false?'no':'')+'"><div>'+what+(c.action!=='remove'?' <small>'+pLL(c.lat,c.lon)+'</small>':'')+(c.why?'<span>'+pEsc(c.why)+'</span>':'')+(c.sw?'<span class="pc-swwarn">&#9888; This point is '+(c.sw===2?'on land':'in water under '+safeFt()+' ft')+' on the chart</span>':'')+'</div>'+yn(c,k)+'</li>';});
	if(P.dep)h+='<li class="'+(P.dep.st===true?'ok':P.dep.st===false?'no':'')+'"><div>Leave <b>'+pEsc(pWhenLong(P.dep.ms))+' ET</b> instead'+(P.dep.why?'<span>'+pEsc(P.dep.why)+'</span>':'')+'</div>'+yn(P.dep,'d')+'</li>';
	h+='</ol><table class="pc-cmp"><tr><th></th><td>Now</td><td>All</td><td>Approved'+(nOk?' ('+nOk+')':'')+'</td></tr>'
		+row('Distance',function(x){return Math.round(x.nm)+' nm';})+row('Underway',function(x){return pDur(x.hrs);})+row('ETA',function(x){return pWhen(x.eta);})
		+row('Easy',function(x){var t=x.cf.easy+x.cf.lively+x.cf.rough;return t?Math.round(100*x.cf.easy/t)+'%':'—';})
		+row('Rough',function(x){return Math.round(x.cf.rough)+' h';})+row('Windward',function(x){return Math.round(x.upH)+' h';})
		+row('Max wind',function(x){return Math.round(x.maxKt)+' kt';})+row('Call',function(x){return passVerdict(x).tag;})+'</table>'
		+(A.noFcH>0||M.noFcH>0?'<p class="pc-note warn">Part of a suggested route runs past the 7-day forecast.</p>':'')
		+'<p class="pc-note">Purple on the chart. Check any new waypoint clears land and traffic before you sail it.</p>'
		+'<div class="pc-acts"><button type="button" class="pc-btn pri" id="pcPropOk"'+(nOk?'':' disabled')+'>Apply '+(nOk?nOk+' approved':'approved')+'</button><button type="button" class="pc-btn" id="pcPropNo">Dismiss</button></div></div>';
	return h;
}
function passPropMark(k,v){var P=PASS.prop;if(!P)return;var it=k==='d'?P.dep:P.changes[+k];if(!it)return;it.st=(it.st===v)?null:v;passPropRun();renderPassCard();drawPassRoute();}
function passPropApply(ok){
	var P=PASS.prop,r=PASS.res;if(!P||!r)return;PASS.prop=null;PASS.chat=PASS.chat||[];
	function lbl(c){var t=r.wps[c.at];return c.action==='add'?'add '+c.n+' after '+t.n:c.action+' '+t.n;}
	var acc=P.changes.filter(function(c){return c.st===true;}).map(lbl),rej=P.changes.filter(function(c){return c.st!==true;}).map(lbl);
	if(P.dep)(P.dep.st===true?acc:rej).push('leave '+pWhenLong(P.dep.ms)+' ET');
	if(!ok||!acc.length){PASS.chat.push({role:'user',text:'(Dismissed the suggestions'+(rej.length?': '+rej.join('; '):'')+'.)'});renderPassCard();drawPassRoute();return;}
	/* approved changes edit My waypoints: adds first (positions taken before anything
	   moves), then moves, then removes; every change is matched to its waypoint */
	var W=r.wps,ok2=P.changes.filter(function(c){return c.st===true;}),cur={};PASS.applying=true;
	ok2.forEach(function(c){if(c.action!=='add')return;
		var rc=distNM(c.lat,c.lon,PASS.RACE.lat,PASS.RACE.lon)<3,off=cur[c.at]||0;cur[c.at]=off+1;
		addPin(rc?PASS.RACE.lat:c.lat,rc?PASS.RACE.lon:c.lon,{name:rc?'The Race':c.n,race:rc,at:passPinAfter(W,c.at)+off});});
	ok2.forEach(function(c){if(c.action!=='move')return;var pn=passPinOf(W,c.at);if(pn)movePin(pn,c.lat,c.lon,c.n);});
	ok2.forEach(function(c){if(c.action!=='remove')return;var pn=passPinOf(W,c.at);if(pn)removePin(pn);});
	PASS.applying=false;
	PASS.departMs=P.mine.depart;
	PASS.chatKeep=PASS.chat.concat([{role:'user',text:'(Applied to my waypoints: '+acc.join('; ')+(rej.length?'. Rejected: '+rej.join('; '):'')+'. The route has been re-run.)'}]);
	PASS.res=null;passBuild();
}
var PASS_CHIPS=['Recommend the best route for comfort and safety','How comfortable will this passage be?','Would another route or point of sail be more comfortable?','What’s the roughest stretch, and when?'];
function passChatSend(q){
	var r=PASS.res;q=String(q||'').trim();if(!r||!q||PASS.chatBusy)return;
	if(q.length>600)q=q.slice(0,600);
	PASS.chat=PASS.chat||[];var hist=PASS.chat.slice(-10).map(function(m){return {role:m.role,text:m.text};});
	PASS.chat.push({role:'user',text:q});PASS.chatDraft='';PASS.chatBusy=true;PASS.chatErr='';PASS.scrollTo='.pc-msg.last';renderPassCard();
	var fd=new FormData();fd.append('action','oyc_passage_chat');fd.append('nonce',PB_NONCE);
	fd.append('facts',JSON.stringify(passChatFacts(r)));fd.append('history',JSON.stringify(hist));fd.append('q',q);
	var ctl=new AbortController(),tm=setTimeout(function(){ctl.abort();},120000);
	fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin',signal:ctl.signal}).then(function(x){clearTimeout(tm);return x.json();}).then(function(j){
		if(j&&j.ok&&j.answer){PASS.prop=null;PASS.chat.push({role:'assistant',text:j.answer});if(j.proposal)passPropose(j.proposal);}
		else{PASS.chat.pop();PASS.chatDraft=q;PASS.chatErr=(j&&j.msg)||'Claude couldn’t answer just now.';}
	}).catch(function(){PASS.chat.pop();PASS.chatDraft=q;PASS.chatErr='Claude couldn’t answer just now. Try again.';})
	.then(function(){PASS.chatBusy=false;PASS.scrollTo=PASS.chat.length&&PASS.chat[PASS.chat.length-1].role==='assistant'?'.pc-msg.last':'.pc-chatsec';renderPassCard();});
}
function passChatHTML(){
	if(!PB_AJAX_OK)return '';
	var C=PASS.chat||[],h='<div class="pc-sec pc-chatsec"><h4>Ask Claude about this passage</h4>';
	if(C.length){h+='<div class="pc-chatlog" aria-live="polite">'+C.map(function(m,i){return '<div class="pc-msg '+(i===C.length-1&&!PASS.chatBusy?'last ':'')+(m.role==='user'?(/^\(/.test(m.text)?'sys':'me'):'cl')+'">'+pEsc(m.text).replace(/\n/g,'<br>')+'</div>';}).join('')
		+(PASS.chatBusy?'<div class="pc-msg cl wait last"><span class="pc-spin" aria-hidden="true"></span>Claude is thinking…</div>':'')+'</div>';}
	h+=passPropHTML();
	if(!PASS.chatBusy)h+='<div class="pc-chips">'+PASS_CHIPS.map(function(c,i){return '<button type="button" class="pc-chip-q" data-q="'+i+'">'+pEsc(c)+'</button>';}).join('')+'</div>';
	if(PASS.chatErr)h+='<p class="pc-note warn">'+pEsc(PASS.chatErr)+'</p>';
	h+='<div class="pc-ask"><textarea id="pcQ" rows="2" maxlength="600" placeholder="e.g. Would leaving Tuesday be a gentler ride?"'+(PASS.chatBusy?' disabled':'')+'>'+pEsc(PASS.chatDraft||'')+'</textarea>'
		+'<button type="button" class="pc-btn pri" id="pcSend"'+(PASS.chatBusy?' disabled':'')+' aria-label="Send question">Ask</button></div>'
		+'<p class="pc-note">Claude answers from this run of the router and forecast, including a few alternative routes and departures it has already tried.</p></div>';
	return h;
}
/* ── window accuracy: log each forward-looking window (members), show the verified track record ── */
function passSamples(r){
	var out=[],hasW=(typeof GSW!=='undefined'&&GSW),last=-1e15;
	r.track.forEach(function(p,i){if(p.t-last<3*36e5&&i!==r.track.length-1)return;last=p.t;
		var w=windAtTime(p.lat,p.lon,p.t),sea=seasAt(p.lat,p.lon,p.t);
		out.push({t:Math.round(p.t),lat:p.lat,lon:p.lon,kt:w?+w.kt.toFixed(1):null,dir:w?Math.round(w.dir):null,sea:sea==null?null:+sea.toFixed(1),gs:(function(){var nW=hasW?gsWallLat(GSW.north,p.lon):null,sW=hasW?gsWallLat(GSW.south,p.lon):null;return nW!=null&&sW!=null&&p.lat<=nW&&p.lat>=sW;})()});});
	return out;
}
function passLog(r,kind){
	if(!PB_LOGGED||!r||r.noFcH>0)return;
	var fd=new FormData();fd.append('action','oyc_passage_log');fd.append('nonce',PB_NONCE);
	fd.append('window',JSON.stringify({route:r.key||'pins',kind:kind,run:TIMES[0]||'',floor:PASS.floor,depart:Math.round(r.depart),eta:Math.round(r.eta),verdict:passVerdict(r).lvl,samples:passSamples(r)}));
	fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'}).catch(function(){});
}
function passLoadRecord(verify){
	if(PASS.recLoading)return;PASS.recLoading=true;
	fetchT(AJAX+'?action=oyc_passage_record'+(verify?'&verify=1':''),verify?120000:12000).then(function(x){return x.json();})
		.then(function(j){if(j&&j.ok){PASS.rec=j.record;PASS.recAdmin=!!j.admin;}}).catch(function(){})
		.then(function(){PASS.recLoading=false;renderPassCard();});
}
function passRecordHTML(){
	var R=PASS.rec;if(!R)return '<div class="pc-sec"><h4>Window track record</h4><p class="pc-note">'+(PASS.recLoading?'Loading…':'Unavailable right now.')+'</p></div>';
	var h='<div class="pc-sec pc-rec"><h4>Window track record</h4>';
	if(!R.verified)h+='<p>No windows checked yet'+(R.pending?' · '+R.pending+' waiting for the boat to “arrive”':'')+'. Each routed window is saved and, once the passage would be over, scored against the weather that actually happened.</p>';
	else{
		var pct=R.go?Math.round(100*R.go_good/R.go):null;
		h+='<div class="pc-kpis"><div class="pc-kpi"><b>'+(pct==null?'—':pct+'%')+'</b><span>GO calls safe</span></div><div class="pc-kpi"><b>'+R.verified+'</b><span>Checked</span></div><div class="pc-kpi"><b>±'+R.wind_mae+'</b><span>Wind err kt</span></div></div>';
		h+='<p class="pc-note">'+R.go_good+' of '+R.go+' GO calls had no dangerous crossing'+(R.hold?' · '+R.hold_good+' of '+R.hold+' HOLD calls were right to wait':'')+(R.pending?' · '+R.pending+' pending':'')+'.</p>';
		h+='<ul class="pc-reclist">'+R.recent.map(function(e){return '<li class="'+(e.good?'ok':'bad')+'"><span>'+pEsc(pWhenLong(e.depart))+(e.kind==='best'?' · suggested':'')+'</span><span>'+pEsc(e.verdict==='hold'?'HOLD':e.verdict==='go'?'GO':'GO·WATCH')+' → '+pEsc(e.actual)+(e.good?' ✓':' ✗')+'</span>'+(e.worst&&e.actual!=='safe'?'<small>'+pEsc(e.worst)+'</small>':'')+'</li>';}).join('')+'</ul>';
	}
	if(PASS.recAdmin)h+='<button type="button" class="pc-link" id="pcRecCheck">Check finished windows now</button>';
	h+='<p class="pc-note">Scored against GFS analysis and Open-Meteo marine data along the route. Dangerous = 34+ kt, 12+ ft seas, or 20+ kt against the Gulf Stream.</p></div>';
	return h;
}
function passGpx(){
	var r=PASS.res;if(!r)return;var w=r.wps,nm=w[0].n+' to '+w[w.length-1].n,x='<'+'?xml version="1.0" encoding="UTF-8"?'+'>\n<gpx version="1.1" creator="OYC Passage brief" xmlns="http://www.topografix.com/GPX/1/1">\n<rte><name>'+pEsc(nm)+'</name>\n';
	w.forEach(function(p,i){var eta=i===0?r.depart:r.legs[i-1].end;x+='<rtept lat="'+p.lat.toFixed(5)+'" lon="'+p.lon.toFixed(5)+'"><name>'+pEsc(p.n)+'</name><desc>'+(i===0?'Depart ':'ETA ')+pEsc(pWhenLong(eta))+' ET</desc></rtept>\n';});
	x+='</rte>\n</gpx>\n';var a=document.createElement('a');a.href=URL.createObjectURL(new Blob([x],{type:'application/gpx+xml'}));a.download='oyc-passage-'+w[w.length-1].n.replace(/[^A-Za-z0-9]+/g,'-').replace(/^-|-$/g,'').toLowerCase()+'.gpx';document.body.appendChild(a);a.click();setTimeout(function(){URL.revokeObjectURL(a.href);a.remove();},500);
}
function pLocalInput(ms){var d=new Date(ms),p=function(n){return String(n).padStart(2,'0');};return d.getFullYear()+'-'+p(d.getMonth()+1)+'-'+p(d.getDate())+'T'+p(d.getHours())+':'+p(d.getMinutes());}
function renderPassCard(){
	var c=document.getElementById('passCard');if(!c)return;
	var r=PASS.res,dep=PASS.departMs||Date.now(),W=passWps(),np=(typeof pins!=='undefined')?pins.length:0,boat=!!routeOrigin();
	var stale=!!(r&&(!W||passRouteKey(W)!==r.key));
	var open=!r||c._planOpen||!!PASS.picking,W0=W?W[0].n:'—',W1=W?W[W.length-1].n:'—';
	var rw=r?r.wps:W,via=rw&&rw.length>2?(rw.length===3&&rw[1].race?'via The Race':(rw.length-2)+' waypoint'+(rw.length>3?'s':'')+' between'):'';
	var h='<div class="pc-hd" id="pcHd"><span class="pc-ttl">Passage brief</span><span class="pc-chip">✦ Claude</span><span class="pc-sp"></span>'
		+(PASS.picking?'<span class="pc-mini">Tap the chart: '+(PASS.picking==='from'?'start':'finish')+'</span>':r?'<span class="pc-mini">'+Math.round(r.nm)+' nm · ETA '+pWhen(r.eta)+'</span>':'')
		+'<button type="button" class="pc-ib" id="pcMin" aria-label="Minimize passage brief" title="Minimize to the chart button" aria-expanded="true">–</button>'
		+'<button type="button" class="pc-ib" id="pcX" aria-label="Close passage brief">×</button></div><div class="pc-body">';
	h+='<details class="pc-sec pc-sec-plan"'+(open?' open':'')+'><summary><h4>Plan</h4><span>'+pEsc(W0)+' → '+pEsc(W1)+' · '+pEsc(pWhenLong(dep))+'</span><b>Edit</b></summary><div class="pc-plan">'
		+'<p class="pc-note pc-wide">'+(W?'Your '+np+' waypoint'+(np===1?'':'s')+(boat?', from your boat':'')+', in order. Tap the chart to add one; drag a waypoint or a table row to change it. The ETAs go onto your waypoints.':'The passage is your waypoints. Tap the chart to drop them, set a start and finish here, or load the Bermuda route.')+'</p>';
	['from','to'].forEach(function(k){var on=PASS.picking===k,q=W?(k==='from'?W[0]:W[W.length-1]):(k==='from'&&np?{n:pins[0].name||'WP 1',lat:pins[0].lat,lon:pins[0].lon}:null);
		var fixed=k==='from'&&boat;   /* routing from your boat: the start is your GPS position */
		h+='<div class="pc-pt pc-wide'+(on?' on':'')+'"><span class="pc-pl">'+(k==='from'?'Start':'Finish')+'</span><span class="pc-pv">'+(on?'Tap the chart…':q?pEsc(q.n)+(q.n.indexOf('°')<0?'<small>'+pLL(q.lat,q.lon)+'</small>':''):'Not set')+'</span>'
			+(fixed?'<span class="pc-note">From my boat is on</span>':'<button type="button" class="pc-link" data-pick="'+k+'">'+(on?'Cancel':'Set on chart')+'</button>'
			+'<select class="pc-choose" data-choose="'+k+'" aria-label="'+(k==='from'?'Start':'Finish')+' at a destination">'+passChooseOpts(k)+'</select>')+'</div>';});
	if(W&&W.length>2)h+='<div class="pc-pt pc-wide"><span class="pc-pl">Via</span><span class="pc-pv">'+W.slice(1,-1).map(function(q){return pEsc(q.n);}).join(' → ')+'</span></div>';
	var rt=passRaceTip(W);
	if(PASS.bmAsk)h+='<div class="pc-pt pc-wide pc-bm"><span class="pc-pv">Load the Bermuda route (OYC, The Race, Gulf Stream crossing, St. George’s) in place of your '+np+' waypoint'+(np===1?'':'s')+', or after them?</span><span class="pc-yn"><button type="button" class="pc-y" data-bm="replace">Replace</button><button type="button" class="pc-y" data-bm="append">Add after</button><button type="button" class="pc-n" data-bm="no">Cancel</button></span></div>';
	else h+='<div class="pc-wide"><button type="button" class="pc-link" id="pcBermuda">Load Bermuda route</button></div>';
	h+=''
		+'<label>Depart (ET)<input type="datetime-local" id="pcDep" value="'+pLocalInput(dep)+'" step="3600"></label>'
		+'<label>Motor below<input type="number" id="pcFloor" min="0" max="9" step="0.5" value="'+PASS.floor+'"></label>'
		+'<label class="pc-wide">Boat<select disabled><option>'+PASS.boat+' (polars)</option></select></label></div>'
		+'<p class="pc-note">Legs are straight lines between your waypoints: add waypoints to route around land.</p>'
		+'<div class="pc-acts"><button type="button" class="pc-btn pri" id="pcRun"'+(PASS.busy||!W?' disabled':'')+'>'+(PASS.busy?'Routing…':'Route it')+'</button></div></details>';
	if(PASS.status)h+='<div class="pc-status"><span class="pc-spin" aria-hidden="true"></span>'+pEsc(PASS.status)+'</div>';
	else if(stale&&W)h+='<div class="pc-status">Your waypoints changed: re-routing…</div>';
	if(r){
		var sm=passSummary(r),v=PASS.ai&&PASS.ai.verdict?{lvl:PASS.ai.verdict.level,tag:PASS.ai.verdict.tag}:sm.verdict,vt=PASS.ai&&PASS.ai.verdict?PASS.ai.verdict.text:(r.flags.filter(function(f){return f.s>=2;}).map(function(f){return f.txt;}).join('. ')||'No red flags on this run of the forecast.');
		var r0=r.wps[0].n,r1=r.wps[r.wps.length-1].n;
		h+='<div class="pc-route">'+pEsc(r0==='OYC'?'Mamaroneck (OYC)':r0)+' → '+pEsc(r1==="St. George’s"?'Bermuda · St. George’s':r1)+'<small>'+PASS.boat+' · depart '+pEsc(pWhenLong(r.depart))+' ET'+(via?' · '+via:'')+'</small></div>'
			+'<div class="pc-kpis"><div class="pc-kpi"><b>'+Math.round(r.nm)+'</b><span>nm</span></div><div class="pc-kpi"><b>'+pDur(r.hrs)+'</b><span>Underway</span></div><div class="pc-kpi"><b>'+pWhen(r.eta)+'</b><span>ETA ET</span></div></div>'
			+'<div class="pc-verdict '+pEsc(v.lvl)+'"><span class="pc-vb">'+pEsc(v.tag)+'</span><span>'+pEsc(vt)+'</span></div>';
		var g=r.gate,gl='';
		if(g){gl='The Race '+pWhen(g.t);if(g.v!=null)gl+=' · '+(Math.abs(g.v)<0.3?'near slack':g.v<0?'ebb '+Math.abs(g.v).toFixed(1)+' kt, fair':'flood '+g.v.toFixed(1)+' kt, foul')+(g.slack?' (slack '+pWhen(g.slack)+')':'');else gl+=' · tide unavailable';}
		h+='<div class="pc-sec"><h4>Departure'+(g?' &amp; tidal gate':'')+'</h4>'+pEsc(pWhenLong(r.depart))+' from '+pEsc(r0)+(gl?' · '+pEsc(gl):'');
		if(r.best&&Math.abs(r.best.depart-r.depart)>=3*36e5&&r.best.score<r.score-2)h+='<div class="pc-best">Better window: leave <b>'+pEsc(pWhenLong(r.best.depart))+'</b> → ETA '+pEsc(pWhen(r.best.eta))+' <button type="button" class="pc-link" id="pcUseBest">Use</button></div>';
		h+='</div><p class="pc-note">Each leg’s course, wind, sailing, seas, comfort and ETA are on your waypoints: tap a waypoint or see the table.</p>';
		var s=r.gs,sl='';
		if(s.inT){sl='North wall '+s.nWall.toFixed(1)+'°N, south wall '+s.sWall.toFixed(1)+'°N where you cross, near '+Math.abs(s.lon).toFixed(0)+'°W (NOAA).';
			sl+=' In the Stream '+pWhen(s.inT)+'–'+pWhen(s.outT)+(s.dir!=null?', wind '+card(s.dir)+' to '+Math.round(s.kt)+' kt':'')+'.';
			var wac=r.flags.some(function(f){return f.k==='gs';});sl+=wac?' Wind against current: expect steep seas.':' No wind against the current.';}
		else if(s.avail)sl='This route doesn’t cross the Gulf Stream (NOAA wall analysis).';
		else sl='Gulf Stream wall analysis unavailable right now.';
		h+='<div class="pc-sec"><h4>Gulf Stream</h4>'+pEsc(sl)+'</div>';
		var watch=PASS.ai&&PASS.ai.watch&&PASS.ai.watch.length?PASS.ai.watch:r.flags.map(function(f){return f.txt;});
		if(!watch.length)watch=['Nothing flagged. Re-run before you go: the forecast updates every 6 hours.'];
		h+='<div class="pc-sec"><h4>Watch</h4><ul class="pc-list">'+watch.map(function(w){return '<li>'+pEsc(w)+'</li>';}).join('')+'</ul></div>';
		h+='<div class="pc-sec" id="pcTake"><h4>'+(PASS.ai?'Claude’s take':'Summary')+'</h4><div class="pc-ai"><p>'+pEsc(PASS.ai?PASS.ai.take:sm.text)+'</p></div>';
		if(PASS.ai&&PASS.aiStale)h+='<p class="pc-note warn">Your waypoints changed since Claude wrote this. The numbers above are up to date.</p><div class="pc-acts" style="margin-top:6px"><button type="button" class="pc-btn" id="pcAsk"'+(PASS.aiBusy?' disabled':'')+'>'+(PASS.aiBusy?'Claude is writing…':'✦ Refresh Claude’s brief')+'</button></div>';
		if(!PASS.ai){
			if(PB_AJAX_OK)h+='<div class="pc-acts" style="margin-top:8px"><button type="button" class="pc-btn pri" id="pcAsk"'+(PASS.aiBusy?' disabled':'')+'>'+(PASS.aiBusy?'Claude is writing…':'✦ Ask Claude for the brief')+'</button></div>';
			else h+='<p class="pc-note">'+(PB_LOGGED?'Claude brief isn’t switched on for this site yet.'+(PB_ADMIN?' Add the API key in WP Admin → Settings → Passage brief.':''):'Sign in to ask Claude for a written brief.')+'</p>';
			if(PASS.aiErr)h+='<p class="pc-note warn">'+pEsc(PASS.aiErr)+'</p>';
		}
		h+='</div>'+passChatHTML()+passRecordHTML()+'<div class="pc-acts"><button type="button" class="pc-btn" id="pcGpx">Save GPX</button><button type="button" class="pc-btn" id="pcRerun"'+(PASS.busy?' disabled':'')+'>Re-run forecast</button></div>'
			+'<div class="pc-foot">'+PASS.boat+' polars, motor-sailing below '+PASS.floor+' kt · GFS (Open-Meteo), 3-hourly · NOAA LIS1017 current · NOAA Gulf Stream walls · Open-Meteo seas'+(r.noFcH?' · last '+Math.round(r.noFcH)+' h past the 7-day forecast':'')+' · planning aid, not for navigation</div>';
	}
	h+='</div>';
	var ae=document.activeElement,qf=ae&&ae.id==='pcQ',ob=c.querySelector('.pc-body'),st=ob?ob.scrollTop:0;
	c.innerHTML=h;
	/* a redraw keeps the reader where they were; after Claude answers, scroll to the answer */
	var nb=c.querySelector('.pc-body');if(nb){nb.scrollTop=st;
		if(PASS.scrollTo){var el=c.querySelector(PASS.scrollTo);if(el)nb.scrollTop+=el.getBoundingClientRect().top-nb.getBoundingClientRect().top-8;}}
	PASS.scrollTo=null;
	if(qf){var qa=document.getElementById('pcQ');if(qa&&!qa.disabled){qa.focus();qa.selectionStart=qa.selectionEnd=qa.value.length;}}
	if(c._min)passSetMin(true);
	passFabSync();
}
function passRaceHTML(){return '<div class="pc-pt pc-wide pc-racetip"><span class="pc-pv">This route leaves Long Island Sound. Go through The Race?<small>Adds it to your waypoints; the router times the tidal current there.</small></span><span class="pc-yn"><button type="button" class="pc-y" data-race="y">✓ Add The Race</button><button type="button" class="pc-n" data-race="n">✕ No thanks</button></span></div>';}
/* the glyph: shows whenever the card isn't open full; its label carries the picking prompt or the run's ETA */
function passFabSync(){
	var f=document.getElementById('passFab'),c=document.getElementById('passCard');if(!f||!c)return;
	var on=c.classList.contains('on'),open=on&&!c._min;
	f.classList.toggle('hide',open);f.classList.toggle('act',on);f.setAttribute('aria-expanded',open?'true':'false');
	var r=on?passCur():null,lb=document.getElementById('passFabLbl');
	if(lb)lb.textContent=PASS.picking?('Tap the chart: '+(PASS.picking==='from'?'start':'finish')):PASS.busy?'Routing…':r?('ETA '+pWhen(r.eta)):'';
	f.setAttribute('aria-label',open?'Passage brief is open':(on?'Open the passage brief'+(r?', ETA '+pWhen(r.eta):''):'Open the passage brief'));
}
function passSetMin(m){var c=document.getElementById('passCard'),b=document.getElementById('pcMin');if(!c)return;c._min=m;c.classList.toggle('min',m);passFabSync();
	if(b){b.textContent=m?'+':'–';b.setAttribute('aria-expanded',m?'false':'true');b.setAttribute('aria-label',(m?'Expand':'Collapse')+' passage brief');}}
function drawPassRoute(){
	passLayer.clearLayers();
	var pc=document.getElementById('passCard');if(!pc||!pc.classList.contains('on')){map.removeLayer(passLayer);return;}   /* suggestions only with the brief open */
	/* Opening the brief doesn't change the chart (Brad, 2026-11-15): no ETA labels or
	   12-hour marks on the map; the ETAs and leg details are on the waypoint cards
	   and table. Only Claude's suggested changes are drawn, while they wait for approval. */
	if(!(PASS.prop&&PASS.prop.all)){map.removeLayer(passLayer);return;}
	if(PASS.prop&&PASS.prop.all){var PP=PASS.prop;
		L.polyline(PP.all.wps.map(function(w){return [w.lat,w.lon];}),{color:'#b48cff',weight:2,dashArray:'4 6',opacity:.8,interactive:false}).addTo(passLayer);
		if(PP.changes.some(function(c){return c.st===true;}))L.polyline(PP.mine.wps.map(function(w){return [w.lat,w.lon];}),{color:'#b48cff',weight:4,opacity:.9,interactive:false}).addTo(passLayer);
		PP.changes.forEach(function(c,k){if(c.action==='remove')return;L.circleMarker([c.lat,c.lon],{radius:6,color:'#fff',weight:2,fillColor:c.st===false?'#777':'#b48cff',fillOpacity:1})
			.bindTooltip((k+1)+'. '+pEsc(c.n),{permanent:true,direction:'right',offset:[8,0],className:'pass-lbl'}).addTo(passLayer);});}
	passLayer.addTo(map);
}
function passFit(){
	var WP=PASS.res?PASS.res.wps:passWps();if(!WP)return;
	var b=L.latLngBounds(WP.map(function(w){return [w.lat,w.lon];})),phone=window.matchMedia('(max-width:760px)').matches;
	var g=document.getElementById('mapGrid'),lyr=g&&g.classList.contains('lyr-open');
	try{map.fitBounds(b,phone?{paddingTopLeft:[40,30],paddingBottomRight:[20,70]}:{paddingTopLeft:[60,40],paddingBottomRight:[(lyr?640:380),40]});}catch(e){}
}
document.getElementById('tgPassage').addEventListener('change',function(){
	var c=document.getElementById('passCard');
	if(this.checked){c.classList.add('on');passSetMin(false);
		renderPassCard();drawPassRoute();var w=passWps();if(w&&(!PASS.res||PASS.res.key!==passRouteKey(w)))passBuild();else rerenderCallouts();}   /* the map stays where it is (Brad, 2026-11-15) */
	else{c.classList.remove('on');PASS.picking=null;map.getContainer().classList.remove('pass-picking');map.removeLayer(passLayer);passLayer.clearLayers();rerenderCallouts();passFabSync();}   /* passage ETAs off the waypoints */
});
document.getElementById('passCard').addEventListener('click',function(e){
	var t=e.target,c=this;
	if(t.closest('#pcX')){var cb=document.getElementById('tgPassage');cb.checked=false;cb.dispatchEvent(new Event('change',{bubbles:true}));return;}
	if(t.closest('#pcMin')){passSetMin(true);try{document.getElementById('passFab').focus();}catch(e3){}return;}
	if(t.closest('.pc-sec-plan summary')){setTimeout(function(){var d=c.querySelector('.pc-sec-plan');c._planOpen=!!(d&&d.open);},0);return;}
	if(t.closest('#pcRun')||t.closest('#pcRerun')){
		var di=document.getElementById('pcDep'),fl=document.getElementById('pcFloor');
		if(di&&di.value){var ms=new Date(di.value).getTime();if(isFinite(ms))PASS.departMs=ms;}
		if(fl&&isFinite(+fl.value))PASS.floor=Math.max(0,Math.min(9,+fl.value));
		if(t.closest('#pcRerun')){PASS.TIDE_KEY='';PASS.SEAS_KEY='';}
		c._planOpen=false;passBuild();return;}
	if(t.closest('#pcUseBest')&&PASS.res&&PASS.res.best){PASS.departMs=PASS.res.best.depart;c._planOpen=false;passBuild();return;}
	var pk=t.closest('[data-pick]');if(pk){var k=pk.getAttribute('data-pick');if(PASS.picking===k){PASS.picking=null;map.getContainer().classList.remove('pass-picking');if(c._pickMin){c._pickMin=false;passSetMin(false);}renderPassCard();}else passStartPick(k);return;}
	if(t.closest('#pcAsk')){passAskClaude();return;}
	var cq=t.closest('[data-q]');if(cq){passChatSend(PASS_CHIPS[+cq.getAttribute('data-q')]);return;}
	var pa=t.closest('[data-pa]');if(pa){passPropMark(pa.getAttribute('data-pa'),true);return;}
	var pj=t.closest('[data-pr]');if(pj){passPropMark(pj.getAttribute('data-pr'),false);return;}
	if(t.closest('#pcPropOk')){passPropApply(true);return;}
	if(t.closest('#pcPropNo')){passPropApply(false);return;}
	if(t.closest('#pcBermuda')){if(pins.length){PASS.bmAsk=true;c._planOpen=true;renderPassCard();}else passLoadBermuda('append');return;}
	var bm=t.closest('[data-bm]');if(bm){var hw=bm.getAttribute('data-bm');if(hw==='no'){PASS.bmAsk=false;renderPassCard();}else passLoadBermuda(hw);return;}
	var rc=t.closest('[data-race]');if(rc){if(rc.getAttribute('data-race')==='y')passAddRace();else{var rw=passWps();PASS.raceNo=rw?passRouteKey(rw):'';renderPassCard();}return;}
	if(t.closest('#pcSend')){var qa=document.getElementById('pcQ');if(qa)passChatSend(qa.value);return;}
	if(t.closest('#pcGpx')){passGpx();return;}
	if(t.closest('#pcRecCheck')){PASS.recLoading=false;passLoadRecord(true);return;}
	var lg=t.closest('.pc-leg');
	if(lg){var i=+lg.getAttribute('data-leg'),w=PASS.res?PASS.res.wps:passWps(),a=w[i],z=w[i+1],ph=window.matchMedia('(max-width:760px)').matches;
		try{map.fitBounds([[a.lat,a.lon],[z.lat,z.lon]],{paddingTopLeft:[60,40],paddingBottomRight:[ph?20:380,ph?200:40]});}catch(e2){}}
});
document.getElementById('passCard').addEventListener('change',function(e){
	var t=e.target;
	if(t.getAttribute&&t.getAttribute('data-choose')){if(t.value)passChoose(t.getAttribute('data-choose'),t.value);}
});
/* glyph: opens the brief (turning it on if it's off), or brings a minimized one back */
(function(){var f=document.getElementById('passFab');if(!f)return;
	if(L.DomEvent){L.DomEvent.disableClickPropagation(f);}
	f.addEventListener('click',function(){
		var cb=document.getElementById('tgPassage'),c=document.getElementById('passCard');
		if(PASS.picking){PASS.picking=null;map.getContainer().classList.remove('pass-picking');c._pickMin=false;}
		if(!cb.checked){cb.checked=true;cb.dispatchEvent(new Event('change',{bubbles:true}));}
		else{passSetMin(false);renderPassCard();}
	});
	passFabSync();})();
document.getElementById('passCard').addEventListener('input',function(e){if(e.target.id==='pcQ')PASS.chatDraft=e.target.value;});
document.getElementById('passCard').addEventListener('keydown',function(e){
	if(e.target.id==='pcQ'&&e.key==='Enter'&&!e.shiftKey){e.preventDefault();passChatSend(e.target.value);return;}if((e.key==='Enter'||e.key===' ')&&e.target.classList&&e.target.classList.contains('pc-leg')){e.preventDefault();e.target.click();}});
document.getElementById('tgCur').addEventListener('change',function(){
	if(this.checked){curLayer.addTo(map);loadCurrents(drawCurrents);}
	else{map.removeLayer(curLayer);curLayer.clearLayers();}
});
document.getElementById('tgLoc').addEventListener('change',function(){ if(this.checked)startLoc(); else stopLoc(); });
document.getElementById('tgRadarWind').addEventListener('change',function(){if(this.checked){arrowsLayer.addTo(map);drawBarbs();}else map.removeLayer(arrowsLayer);updateBarbHint();});
/* redraw the screen-lattice barbs after pan/zoom so density stays constant */
/* ── lat/lon labels on the chart edges (graticule) — same look as pressure labels ── */
function gratStep(span){var s=[30,20,10,5,2,1,0.5,0.25,0.1,0.05,0.02,0.01,0.005,0.002,0.001];for(var i=0;i<s.length;i++){if(span/s[i]>=3)return s[i];}return 0.001;}
function gratDec(step){return step>=1?0:(step>=0.1?1:(step>=0.01?2:3));}
function gratLat(v,d){return Math.abs(v).toFixed(d)+'°'+(v>=0?'N':'S');}
function gratLon(v,d){v=((v+540)%360)-180;return Math.abs(v).toFixed(d)+'°'+(v>=0?'E':'W');}
function drawGraticule(){
	var host=document.getElementById('gratic');if(!host)return;
	if(mode==='sat'||mode==='charts'){host.innerHTML='';return;}
	var cont=map.getContainer(),size=map.getSize();
	/* Leaflet can cache a stale size (e.g. 0 width) if the map was laid out after
	   init; re-sync from the live container so the edge math is correct */
	if(cont&&cont.clientWidth&&cont.clientHeight&&(size.x!==cont.clientWidth||size.y!==cont.clientHeight)){map.invalidateSize({pan:false,animate:false});size=map.getSize();}
	var W=size.x,H=size.y;if(!W||!H){host.innerHTML='';return;}
	var b=map.getBounds(),S=b.getSouth(),N=b.getNorth(),Wd=b.getWest(),Ed=b.getEast(),out='';
	var ls=gratStep(N-S),ld=gratDec(ls),lat=Math.ceil(S/ls)*ls,g;
	for(g=0;lat<=N&&g<80;lat+=ls,g++){
		var y=Math.round(map.latLngToContainerPoint([lat,Wd]).y);if(y<0||y>H)continue;
		out+='<i class="gl gl-h" style="top:'+y+'px"></i>';
		if(y<10||y>H-10)continue;
		var t=gratLat(lat,ld);
		out+='<span style="left:3px;top:'+y+'px;transform:translateY(-50%)">'+t+'</span>'
			+'<span style="right:3px;top:'+y+'px;transform:translateY(-50%)">'+t+'</span>';
	}
	var os=gratStep(Ed-Wd),od=gratDec(os),lon=Math.ceil(Wd/os)*os,h;
	for(h=0;lon<=Ed&&h<80;lon+=os,h++){
		var x=Math.round(map.latLngToContainerPoint([S,lon]).x);if(x<0||x>W)continue;
		out+='<i class="gl gl-v" style="left:'+x+'px"></i>';
		if(x<16||x>W-16)continue;
		var lt=gratLon(lon,od);
		out+='<span style="left:'+x+'px;top:3px;transform:translateX(-50%)">'+lt+'</span>'
			+'<span style="left:'+x+'px;bottom:3px;transform:translateX(-50%)">'+lt+'</span>';
	}
	host.innerHTML=out;
}
map.on('moveend',function(){if(mode==='radar'){if(document.getElementById('tgRadarWind').checked)drawBarbs();}else if(document.getElementById('tgArrows').checked)drawBarbs();drawGraticule();drawLegLabels();});
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
	/* Scroll lock. iOS Safari ignores body{overflow:hidden} on the document
	   scroller, so with the page scrolled the background kept scrolling behind
	   the fixed overlay and the retracting toolbar mis-sized inset:0 — stranding
	   the Exit button and gestures ("locks the screen"). Pinning the body at
	   -scrollY freezes it reliably; we restore the offset on exit. */
	var savedY=0;
	function lockScroll(on){
		var b=document.body;
		if(on){ savedY=window.pageYOffset||document.documentElement.scrollTop||0;
			b.style.top=(-savedY)+'px';b.style.position='fixed';b.style.left='0';b.style.right='0';b.style.width='100%';b.style.overflow='hidden';
		}else{ b.style.position='';b.style.top='';b.style.left='';b.style.right='';b.style.width='';b.style.overflow='';
			window.scrollTo(0,savedY);
		}
	}
	/* Native Fullscreen API — on iPad Safari and desktop this hides the browser's
	   own address bar and tab strip for a true full-screen map. Layered on TOP of
	   the .isfull overlay (which stays the single source of styling), so if the
	   request is unsupported or rejected (iPhone Safari never grants it) the
	   overlay still fills the viewport exactly as before. Must run in the click
	   gesture, which apply() does. */
	function nativeFs(on){
		try{
			if(on){
				var rq=fsEl.requestFullscreen||fsEl.webkitRequestFullscreen;
				if(rq&&!(document.fullscreenElement||document.webkitFullscreenElement)){var p=rq.call(fsEl);if(p&&p.catch)p.catch(function(){});}
			}else{
				var ex=document.exitFullscreen||document.webkitExitFullscreen;
				if(ex&&(document.fullscreenElement||document.webkitFullscreenElement))ex.call(document);
			}
		}catch(e){}
	}
	function apply(on){
		fsEl.classList.toggle('isfull',on);
		lockScroll(on);
		backdrop(on);
		nativeFs(on);
		btn.innerHTML=on?COL:EXP;btn.title=on?'Exit full screen':'Full screen';btn.setAttribute('aria-label',btn.title);
		setTimeout(function(){
			map.invalidateSize();
			/* Leaflet repositions geo-anchored layers, but the velocity particle canvas
			   and the screen-lattice barbs/isobars don't re-fit on their own. Re-run the
			   full frame render so EVERY overlay matches the new size — wind particles
			   (show→vl.setData restarts the sim), wind barbs, isobars, temp/precip/waves
			   field, H/L, storms and the Gulf Stream. */
			setTimeout(function(){try{show(curTi);}catch(e){}drawGraticule();},40);
		},90);
	}
	btn.addEventListener('click',function(){apply(!fsEl.classList.contains('isfull'));});
	document.addEventListener('keydown',function(e){if(e.key==='Escape'&&fsEl.classList.contains('isfull'))apply(false);});
	/* If the user leaves native full-screen by the system gesture/Esc rather than
	   our Exit button, collapse the overlay too so the two stay in sync. */
	['fullscreenchange','webkitfullscreenchange'].forEach(function(ev){
		document.addEventListener(ev,function(){
			if(!(document.fullscreenElement||document.webkitFullscreenElement)&&fsEl.classList.contains('isfull'))apply(false);
		});
	});
})();

/* ---------- landscape full-screen: fold the tabs + layer toggles into a
   floating hamburger so the map gets the whole height ----------
   The tab bar and every .layers row are MOVED into a floating panel (their event
   listeners travel with the nodes, so tabs and checkboxes keep working) and
   restored to their original spots when leaving this mode. Only active in
   landscape full-screen on a short viewport. */
(function(){
	var card=document.querySelector('#oycwm .card'),
	    hd=card&&card.querySelector('.hd'),
	    tabs=card&&card.querySelector('.tabs'),
	    exitBtn=document.getElementById('oycwmFs');
	if(!card||!hd||!tabs)return;
	var layers=[].slice.call(card.querySelectorAll('.layers'));
	var burger=document.createElement('button');
	burger.type='button';burger.className='burger';burger.setAttribute('aria-label','Views & layers');burger.setAttribute('aria-expanded','false');
	burger.innerHTML='<span></span><span></span><span></span>';
	var panel=document.createElement('div');panel.className='mobpanel';
	/* comment anchors mark where each moved node came from, so we can put it back */
	var tabsHome=document.createComment('tabs');tabs.parentNode.insertBefore(tabsHome,tabs);
	var layerHomes=layers.map(function(l){var c=document.createComment('ly');l.parentNode.insertBefore(c,l);return c;});
	var mq=window.matchMedia('(orientation:landscape) and (max-height:900px)'),on=false;
	function enter(){
		if(on)return;on=true;
		hd.insertBefore(burger,hd.firstChild);
		panel.appendChild(tabs);
		layers.forEach(function(l){panel.appendChild(l);});
		card.insertBefore(panel,card.querySelector('.ctrl')||card.querySelector('.mapwrap'));
		card.classList.add('hasburger');
		refit();
	}
	function leave(){
		if(!on)return;on=false;
		card.classList.remove('hasburger','menuopen');burger.setAttribute('aria-expanded','false');
		if(burger.parentNode)burger.parentNode.removeChild(burger);
		tabsHome.parentNode.insertBefore(tabs,tabsHome);
		layerHomes.forEach(function(c,i){c.parentNode.insertBefore(layers[i],c);});
		if(panel.parentNode)panel.parentNode.removeChild(panel);
		refit();
	}
	function refit(){setTimeout(function(){try{map.invalidateSize();}catch(e){}},60);}
	burger.addEventListener('click',function(){
		var o=card.classList.toggle('menuopen');burger.setAttribute('aria-expanded',o?'true':'false');
	});
	function sync(){ if(card.classList.contains('isfull')&&mq.matches)enter();else leave(); }
	new MutationObserver(sync).observe(card,{attributes:true,attributeFilter:['class']});
	if(mq.addEventListener)mq.addEventListener('change',sync);else if(mq.addListener)mq.addListener(sync);
	window.addEventListener('orientationchange',sync);
	sync();
})();

/* model wind (kt, direction FROM °T) at lat/lon for epoch ms, interpolated
   between the 3-hourly frames; null until the grids load */
function modelWind(lat,lon,epoch){
	if(!TIMES.length)return null;epoch=epoch||Date.now();
	var e0=new Date(TIMES[0]).getTime(),step=TIMES.length>1?(new Date(TIMES[1]).getTime()-e0):1;
	var f=(epoch-e0)/step;if(f<0)f=0;if(f>TIMES.length-1)f=TIMES.length-1;
	var i0=Math.floor(f),i1=Math.min(i0+1,TIMES.length-1),fr=f-i0,a,b;
	try{a=sampleBest(lat,lon,i0);b=sampleBest(lat,lon,i1);}catch(e){return null;}
	if(!a||!b){a=a||b;return a?{tws:a.kt,twd:a.dir}:null;}
	var dd=((b.dir-a.dir+540)%360)-180;
	return {tws:a.kt+(b.kt-a.kt)*fr,twd:(a.dir+dd*fr+360)%360};
}

/* ---------- Chart plotter: other boats (inc/fleet-share.php) ----------
   "Share my position" (members, logged in) posts your GPS fix every ~15 s while
   the page is open; "Other boats" (anyone) polls every 20 s and draws each boat
   sharing now, turned to its course, with its course vector. The server keeps a
   boat for 10 min after its last fix and nothing after that. */
var FLEET={layer:L.layerGroup(),timer:null,boats:[],me:null,share:false,lastPost:0,name:''};
function lsGet(k){try{return localStorage.getItem(k);}catch(e){return null;}}
function lsSet(k,v){try{if(v==null)localStorage.removeItem(k);else localStorage.setItem(k,v);}catch(e){}}
function fleetOn(){var t=document.getElementById('tgFleet');return !!(t&&t.checked);}
function fleetAge(s){return s<60?Math.round(s)+' s':Math.round(s/60)+' min';}
function drawFleet(){
	FLEET.layer.clearLayers();if(!fleetOn())return;
	FLEET.boats.forEach(function(b){
		if(FLEET.me&&b.k===FLEET.me.key)return;   /* that's you: your own boat is the blue one */
		var stale=b.age>180,col=stale?'#8aa0ac':'#d35400',moving=b.sog!=null&&b.sog>=0.5&&b.cog!=null;
		var hull='<svg width="26" height="26" viewBox="-15 -15 30 30" style="transform:rotate('+(b.cog!=null?b.cog:0)+'deg)"><path d="M0,-12.5 C5.5,-9 6.5,-1 6,6 L4,9.5 L-4,9.5 L-6,6 C-6.5,-1 -5.5,-9 0,-12.5 Z" fill="'+col+'" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/></svg>';
		var lbl='<span class="fl-lbl">'+xmlEsc(b.name||'Boat')+'<small>'+(b.sog!=null?b.sog.toFixed(1)+' kt':'')+(moving?' · '+pad3(b.cog+MAGVAR)+'°M':'')+' · '+fleetAge(b.age)+' ago</small></span>';
		var m=L.marker([b.lat,b.lon],{icon:L.divIcon({className:'fl-mk'+(stale?' fl-stale':''),html:'<div class="fl-ic">'+hull+lbl+'</div>',iconSize:[0,0]}),keyboard:false,zIndexOffset:900}).addTo(FLEET.layer);
		m.bindPopup(function(){
			var h='<b>'+xmlEsc(b.name||'Boat')+'</b><br>'+(b.sog!=null?b.sog.toFixed(1)+' kt':'speed —')+(moving?' · COG '+pad3(b.cog+MAGVAR)+'°M':'')+'<br>Last fix '+fleetAge(b.age)+' ago';
			if(myLoc)h+='<br>From you '+magBrg(myLoc.lat,myLoc.lon,b.lat,b.lon)+'°M · '+distNM(myLoc.lat,myLoc.lon,b.lat,b.lon).toFixed(1)+' nm';
			return h;},{className:'pin-pop'});
		if(!stale)drawVector(FLEET.layer,b.lat,b.lon,b.sog,b.cog,col);
	});
}
function pollFleet(){
	if(!fleetOn()||document.visibilityState==='hidden')return;
	fetchT(AJAX+'?action=oyc_fleet&_='+Date.now(),10000).then(function(r){return r.json();})
		.then(function(res){if(res&&res.ok){FLEET.boats=res.boats||[];drawFleet();}}).catch(function(){});
}
function setFleet(on){
	var t=document.getElementById('tgFleet');if(t)t.checked=on;lsSet('oyc_fleet_view',on?'1':null);
	if(on){FLEET.layer.addTo(map);pollFleet();if(!FLEET.timer)FLEET.timer=setInterval(pollFleet,20000);}
	else{if(FLEET.timer){clearInterval(FLEET.timer);FLEET.timer=null;}FLEET.layer.clearLayers();map.removeLayer(FLEET.layer);}
}
function fleetForm(o){var fd=new FormData();for(var k in o)if(o[k]!=null)fd.append(k,o[k]);return fd;}
function postFix(force){
	if(!FLEET.share||!FLEET.me||!myLoc)return;var now=Date.now();if(!force&&now-FLEET.lastPost<15000)return;FLEET.lastPost=now;
	var mv=mySpeedKt!=null&&mySpeedKt>=0.5&&myHeading!=null;
	fetch(AJAX,{method:'POST',credentials:'same-origin',body:fleetForm({action:'oyc_fleet_pos',nonce:FLEET.me.nonce,lat:myLoc.lat.toFixed(5),lon:myLoc.lon.toFixed(5),
		sog:mySpeedKt!=null?mySpeedKt.toFixed(1):null,cog:mv?myHeading.toFixed(0):null,name:FLEET.name})})
		.then(function(r){if(r.status===403)stopShare('Sharing stopped: your sign-in has expired. Reload the page, log in, and switch Share my position on again.');}).catch(function(){});
}
function shareBox(on){var t=document.getElementById('tgShare');if(t)t.checked=on;}
function startShare(){
	fetchT(AJAX+'?action=oyc_fleet_me&_='+Date.now(),10000).then(function(r){return r.json();}).then(function(res){
		if(!res||!res.ok){shareBox(false);
			if(res&&res.why==='login'){if(confirm('Log in as a club member to share your position with other boats. Go to the login page?'))location.href=res.login;}
			else alert('Sharing your position is for club members and racers.');
			return;}
		var nm=prompt('Boat name to show other boats:',lsGet('oyc_fleet_name')||res.name||'');
		if(nm===null){shareBox(false);return;}
		nm=String(nm).trim().slice(0,40)||res.name||'';lsSet('oyc_fleet_name',nm);
		FLEET.me=res;FLEET.name=nm;FLEET.share=true;
		var t=document.getElementById('tgLoc');if(!locWatch){if(t)t.checked=true;startLoc();}
		if(!fleetOn())setFleet(true);
		postFix(true);
	}).catch(function(){shareBox(false);alert('Could not reach the club site. Check your signal and try again.');});
}
function stopShare(msg){
	if(FLEET.share&&FLEET.me){try{fetch(AJAX,{method:'POST',credentials:'same-origin',keepalive:true,body:fleetForm({action:'oyc_fleet_off',nonce:FLEET.me.nonce})});}catch(e){}}
	FLEET.share=false;shareBox(false);if(msg)alert(msg);
}
(function(){
	var f=document.getElementById('tgFleet'),s=document.getElementById('tgShare'),v=document.getElementById('vecMin');
	if(f)f.addEventListener('change',function(){setFleet(this.checked);});
	if(s)s.addEventListener('change',function(){if(this.checked)startShare();else stopShare();});
	if(v){var saved=lsGet('oyc_vec_min');if(saved!=null&&v.querySelector('option[value="'+saved+'"]'))v.value=saved;
		v.addEventListener('change',function(){lsSet('oyc_vec_min',this.value);drawFleet();
			if(myLoc&&locLayer){locLayer.eachLayer(function(l){if(l instanceof L.Polyline||l instanceof L.CircleMarker)if(!(l instanceof L.Circle))locLayer.removeLayer(l);});drawVector(locLayer,myLoc.lat,myLoc.lon,mySpeedKt,myHeading,'#1a73e8');}});}
	if(lsGet('oyc_fleet_view')==='1')setFleet(true);
	document.addEventListener('visibilitychange',function(){if(document.visibilityState==='visible'){pollFleet();if(FLEET.share)postFix(true);}});
	window.addEventListener('pagehide',function(){if(FLEET.share)stopShare();});
})();

/* ---------- Chart plotter: AIS ships (inc/ais.php, aisstream.io) ----------
   Polls every 30 s while the toggle is on; the server keeps the vessels heard in
   the western Sound for 15 min after their last report. */
var AIS={layer:L.layerGroup(),timer:null,list:[]};
function aisOn(){var t=document.getElementById('tgAis');return !!(t&&t.checked);}
function aisColor(tc){if(tc===36||tc===37)return '#7b3fb0';if(tc>=60&&tc<=69)return '#1f6fd1';if(tc>=70&&tc<=79)return '#2e8b57';if(tc>=80&&tc<=89)return '#c0392b';if(tc===31||tc===32||tc===52||tc===50||tc===33)return '#b9770e';if(tc===35||tc===55||tc===51)return '#34495e';return '#5d6d7e';}
function aisNote(t){var n=document.getElementById('aisNote');if(!n)return;n.textContent=t||'';n.classList.toggle('on',!!t);}
function aisZoomCls(){var w=document.getElementById('oycwm');if(w)w.classList.toggle('ais-far',map.getZoom()<12);}
function drawAis(){
	AIS.layer.clearLayers();if(!aisOn())return;
	var vm=vecMin();
	AIS.list.forEach(function(v){
		var col=aisColor(v.tc),stale=v.age>600,dir=(v.cog!=null&&v.sog!=null&&v.sog>=1)?v.cog:((v.hdg!=null)?v.hdg:v.cog);  /* point along the direction of travel, like its vector */
		var shape=dir!=null?'<path d="M0,-9 L5.5,7 L0,4 L-5.5,7 Z" fill="'+col+'" stroke="#fff" stroke-width="1.3" stroke-linejoin="round"/>':'<circle r="4.5" fill="'+col+'" stroke="#fff" stroke-width="1.3"/>';
		var svg='<svg width="18" height="18" viewBox="-10 -10 20 20" style="opacity:'+(stale?.55:1)+';transform:rotate('+(dir||0)+'deg)">'+shape+'</svg>';
		var nm=v.n||('MMSI '+v.m);
		var m=L.marker([v.lat,v.lon],{icon:L.divIcon({className:'ais-mk',html:'<div class="ais-ic">'+svg+'<span class="ais-lbl">'+xmlEsc(nm)+'</span></div>',iconSize:[0,0]}),keyboard:false,zIndexOffset:850}).addTo(AIS.layer);
		m.bindPopup(function(){
			var h='<b>'+xmlEsc(nm)+'</b>'+(v.ty?'<br>'+xmlEsc(v.ty):'')
				+'<br>'+(v.sog!=null?v.sog.toFixed(1)+' kt':'speed —')+(v.cog!=null?' · COG '+pad3(v.cog+MAGVAR)+'°M':'')
				+'<br>Last report '+fleetAge(v.age)+' ago · MMSI '+xmlEsc(v.m);
			if(myLoc)h+='<br>From you '+magBrg(myLoc.lat,myLoc.lon,v.lat,v.lon)+'°M · '+distNM(myLoc.lat,myLoc.lon,v.lat,v.lon).toFixed(1)+' nm';
			return h;},{className:'pin-pop'});
		if(vm&&!stale)drawVector(AIS.layer,v.lat,v.lon,v.sog,v.cog,col);
	});
}
function pollAis(){
	if(!aisOn()||document.visibilityState==='hidden')return;
	fetchT(AJAX+'?action=oyc_ais&_='+Date.now(),25000).then(function(r){return r.json();}).then(function(res){
		if(!res||!res.ok)return;
		if(!res.enabled){aisNote('AIS ships aren’t set up on this site yet (an admin adds the aisstream.io key under Settings → AIS ships).');AIS.list=[];drawAis();return;}
		AIS.list=res.vessels||[];drawAis();aisViewNote();
	}).catch(function(){});
}
/* say so when the vessels heard are all outside the current view (the feed
   covers the western Sound down to New York Harbor) */
function aisViewNote(){
	if(!aisOn())return;
	if(!AIS.list.length){aisNote('No AIS vessels heard between New York Harbor and the western Sound in the last 15 minutes.');return;}
	var b=map.getBounds(),c=map.getCenter(),inView=0,near=null,nd=1e9;
	AIS.list.forEach(function(v){if(b.contains([v.lat,v.lon]))inView++;var d=distNM(c.lat,c.lng,v.lat,v.lon);if(d<nd){nd=d;near=v;}});
	aisNote(inView?'':(AIS.list.length+' AIS vessel'+(AIS.list.length>1?'s':'')+' heard, none in this view. Nearest: '+(near.n||('MMSI '+near.m))+', '+nd.toFixed(0)+' nm from the map centre. Zoom out to see them.'));
}
function setAis(on){
	var t=document.getElementById('tgAis');if(t)t.checked=on;lsSet('oyc_ais_view',on?'1':null);
	if(on){AIS.layer.addTo(map);aisZoomCls();pollAis();if(!AIS.timer)AIS.timer=setInterval(pollAis,30000);}
	else{if(AIS.timer){clearInterval(AIS.timer);AIS.timer=null;}AIS.layer.clearLayers();map.removeLayer(AIS.layer);aisNote('');}
}
(function(){
	var t=document.getElementById('tgAis');if(t)t.addEventListener('change',function(){setAis(this.checked);});
	var ly=document.getElementById('tgLay');
	layInit();
	if(ly){ly.addEventListener('change',function(){lsSet('oyc_lay_view',this.checked?'1':null);drawLaylines();updateBoatInfo();});
		if(lsGet('oyc_lay_view')==='1'){ly.checked=true;drawLaylines();}}
	map.on('zoomend',aisZoomCls);map.on('moveend',aisViewNote);
	var v=document.getElementById('vecMin');if(v)v.addEventListener('change',drawAis);
	if(lsGet('oyc_ais_view')==='1')setAis(true);
	document.addEventListener('visibilitychange',function(){if(document.visibilityState==='visible')pollAis();});
})();

/* ---------- Coast Guard Local Notice to Mariners (inc/nav-notices.php) ----------
   The server reads the District 1 notices from NAVCEN twice a day and keeps the
   ones in the Sound; this draws a warning pin per notice (and the outline of a
   zone or line). Loaded once when the layer is switched on. */
var NN={layer:L.layerGroup(),items:null,t:0,busy:false};
function nnOn(){var t=document.getElementById('tgNotice');return !!(t&&t.checked);}
function nnNote(t){var n=document.getElementById('nnNote');if(!n)return;n.textContent=t||'';n.classList.toggle('on',!!t);}
function nnDate(v){
	if(v==null||v==='')return '';
	var n=+v;if(isFinite(n)&&n>1e9){var d=new Date(n>1e11?n:n*1000);return d.toLocaleDateString(undefined,{month:'short',day:'numeric',year:'numeric'});}
	var d2=new Date(v);if(/^\d{4}-\d{2}-\d{2}/.test(v)&&!isNaN(d2))return d2.toLocaleDateString(undefined,{month:'short',day:'numeric',year:'numeric'});
	return String(v);
}
var NN_ICON='<svg viewBox="0 0 24 24" width="22" height="22"><path d="M12 2.5 23 21.5H1z" fill="#f39c12" stroke="#fff" stroke-width="1.6" stroke-linejoin="round"/><path d="M12 9v5.5" stroke="#3a2500" stroke-width="2.4" stroke-linecap="round"/><circle cx="12" cy="17.8" r="1.4" fill="#3a2500"/></svg>';
function drawNotices(){
	NN.layer.clearLayers();if(!nnOn()||!NN.items)return;
	NN.items.forEach(function(it){
		var pop=function(){
			var h=(it.cat?'<span class="nn-cat">'+xmlEsc(it.cat)+'</span><br>':'')+'<b>'+xmlEsc(it.title||'Notice')+'</b>';
			var f=nnDate(it.from),to=nnDate(it.to);
			if(f||to)h+='<br>'+(f&&!to?'Since ':'')+(f?xmlEsc(f):'')+(to?' &rarr; '+xmlEsc(to):'');
			if(it.text&&it.text!==it.title)h+='<span class="nn-txt">'+xmlEsc(it.text)+'</span>';
			if(myLoc)h+='<span class="nn-you">From you '+magBrg(myLoc.lat,myLoc.lon,it.lat,it.lon)+'°M · '+distNM(myLoc.lat,myLoc.lon,it.lat,it.lon).toFixed(1)+' nm</span>';
			h+='<span class="nn-src">Coast Guard District 1 Local Notice to Mariners'+(it.id?' · '+xmlEsc(it.id):'')+(NN.t?' · checked '+new Date(NN.t*1000).toLocaleDateString(undefined,{month:'short',day:'numeric'}):'')+' · <a href="https://www.navcen.uscg.gov/msi" target="_blank" rel="noopener">NAVCEN</a>. Not for navigation.</span>';
			return h;};
		if(it.geom){try{L.geoJSON(it.geom,{style:{color:'#e67e22',weight:2,dashArray:'6 4',fillColor:'#f39c12',fillOpacity:.12},pointToLayer:function(g,ll){return L.circleMarker(ll,{radius:3,color:'#e67e22'});}}).bindPopup(pop,{className:'pin-pop',maxWidth:300}).addTo(NN.layer);}catch(e){}}
		L.marker([it.lat,it.lon],{icon:L.divIcon({className:'nn-mk',html:'<div class="nn-ic">'+NN_ICON+'</div>',iconSize:[0,0]}),keyboard:false,zIndexOffset:800,title:it.title||'Notice'}).bindPopup(pop,{className:'pin-pop',maxWidth:300}).addTo(NN.layer);
	});
	nnViewNote();
}
function nnViewNote(){
	if(!nnOn()||!NN.items)return;
	if(!NN.items.length){nnNote(NN.t?'No Coast Guard notices for the Sound right now.':'Coast Guard notices are still loading on the server. Try again in a few minutes.');return;}
	var b=map.getBounds(),n=0;NN.items.forEach(function(it){if(b.contains([it.lat,it.lon]))n++;});
	nnNote(n?'':NN.items.length+' Coast Guard notice'+(NN.items.length>1?'s':'')+' in the Sound, none in this view. Zoom out to see them.');
}
function loadNotices(){
	if(NN.busy)return;NN.busy=true;
	fetchT(AJAX+'?action=oyc_nn',20000).then(function(r){return r.json();}).then(function(res){
		NN.busy=false;if(!res||!res.ok)return;
		NN.items=res.items||[];NN.t=res.t||0;drawNotices();
	}).catch(function(){NN.busy=false;nnNote('Couldn’t load the Coast Guard notices. Try again later.');});
}
function setNotices(on){
	var t=document.getElementById('tgNotice');if(t)t.checked=on;lsSet('oyc_nn_view',on?null:'0');   /* on by default: only "off" is remembered */
	if(on){NN.layer.addTo(map);if(NN.items)drawNotices();else loadNotices();}
	else{NN.layer.clearLayers();map.removeLayer(NN.layer);nnNote('');}
}
(function(){
	var t=document.getElementById('tgNotice');if(!t)return;   /* no toggle until the server has read NAVCEN's files */
	t.addEventListener('change',function(){setNotices(this.checked);});
	map.on('moveend',nnViewNote);
	setNotices(lsGet('oyc_nn_view')!=='0');
})();

/* Small read-only API for the racers' race tools (assets/race-tools.js):
   live boat state, model wind at any point/time, and the Leaflet map. */
window.OYCWM={
	map:map,
	/* race-tools.js sets polarAngles(tws)->{up,gybe} for the chosen polars, then calls this */
	polarAngles:null,
	/* race-tools.js calls this when the polars change: leg speeds + ETA */
	routeRefresh:function(){if(pins.length)refreshRoute();replotRace();},
	layRefresh:function(){layUI();if(LAY.mode==='polar'){drawLaylines();replotRace();updateBoatInfo();}},
	magvar:MAGVAR,
	boat:function(){return myLoc?{lat:myLoc.lat,lon:myLoc.lon,sog:mySpeedKt,cog:myHeading}:null;},
	/* model wind (kt, direction FROM °T) at lat/lon for epoch ms, interpolated
	   between the 3-hourly frames; null until the grids load */
	wind:modelWind,
	locOn:function(){return !!locWatch;},
	startLoc:function(){var t=document.getElementById('tgLoc');if(!locWatch){if(t)t.checked=true;startLoc();}},
	_fix:null
};
loadLocal();
map.whenReady(function(){setTimeout(drawGraticule,400);});
})();

/* ---------- Race tools layer (racers only) ----------
   The Race tools panel under the map (and its target-speed badge on the map) show
   only while Layers > Navigation > Race tools is ticked. The Race preset ticks it,
   and My chart keeps it like any other layer. First visit: on only if the last
   preset used was Race. */
(function(){
	var cb=document.getElementById('tgRaceTools'),root=document.getElementById('oycwm');
	if(!cb||!root)return;
	var st=null,pre=null;try{st=localStorage.getItem('oyc_wm_racetools');pre=localStorage.getItem('oyc_wm_preset');}catch(e){}
	cb.checked=(st===null)?(pre==='race'):(st==='1');
	function vis(){root.classList.toggle('rt-hide',!cb.checked);}
	vis();
	cb.addEventListener('change',function(){
		vis();
		try{localStorage.setItem('oyc_wm_racetools',cb.checked?'1':'0');}catch(e){}
	});
	/* switching the Race layer on (drawer, Race preset or a My chart that has it)
	   also brings up Race tools, so the polars are always one tap away */
	var rc=document.getElementById('tgRace');
	if(rc){
		rc.addEventListener('change',function(){if(rc.checked&&!cb.checked){cb.checked=true;cb.dispatchEvent(new Event('change',{bubbles:true}));}});
		if(rc.checked&&!cb.checked){cb.checked=true;vis();}
	}
})();

/* ---------- My chart + Layers drawer ----------
   A preset sets the main layer switches in one tap by ticking/unticking the real
   checkboxes (and firing their change events), so every layer's own wiring and
   saved state work exactly as if the user had clicked. My location, Share my
   position, Animated, Wind color and the vector length are left as they are. */
(function(){
	var bar=document.getElementById('presetBar'),drawer=document.getElementById('lyrDrawer'),btn=document.getElementById('lyrBtn');
	if(!bar||!drawer||!btn)return;
	var MANAGED=['tgArrows','tgIso','tgRadarOv','tgTemp','tgPrecip','tgWave','tgCur','tgGulf','tgLay','tgFleet','tgAis','tgRace','tgRaceTools','tgChart','tgClubs','tgDine','tgFuel','tgAnch','tgFish'];
	var PRESETS={
		/* the Sail / Race / Fish / Cruise buttons were removed (v1.7.448): each of
		   their layers is its own switch in the Layers drawer, and My chart keeps
		   the visitor's own mix */
	};
	/* "My chart": the visitor's own saved setup (layers incl. Animated / Wind
	   color, the view tab, and the map centre + zoom), kept in this browser. */
	var EXTRA=['tgParticles','tgWindColor'],MINE_KEY='oyc_wm_mine',mine=null;
	var VIEWS=['tabWind','tabRadar','tabTemp','tabPrecip','tabWave','tabSat','tabCharts'];
	try{mine=JSON.parse(localStorage.getItem(MINE_KEY)||'null');}catch(e){mine=null;}
	if(!mine||!mine.layers||!mine.layers.join)mine=null;
	function syncMine(){
		if(mine){PRESETS.mine=mine.layers.filter(function(id){return MANAGED.indexOf(id)>=0;});
			/* a My chart saved before the Race tools layer existed: Race brings it up */
			if(PRESETS.mine.indexOf('tgRace')>=0&&PRESETS.mine.indexOf('tgRaceTools')<0&&document.getElementById('tgRaceTools'))PRESETS.mine.push('tgRaceTools');}
		else delete PRESETS.mine;
		var mb=bar.querySelector('.pre-mine');if(mb)mb.classList.toggle('empty',!mine);
		var sv=document.getElementById('mineSave');if(sv)sv.textContent='Save';
	}
	function saveMine(){
		var on=MANAGED.concat(EXTRA).filter(function(id){var cb=document.getElementById(id);return cb&&cb.checked;});
		var view='tabWind';VIEWS.forEach(function(v){var b=document.getElementById(v);if(b&&b.getAttribute('aria-selected')==='true')view=v;});
		var c=null,z=null,lm=window.OYCWM&&window.OYCWM.map;try{var ll=lm.getCenter();c=[+ll.lat.toFixed(4),+ll.lng.toFixed(4)];z=lm.getZoom();}catch(e){}
		mine={layers:on,view:view,c:c,z:z};
		/* racers: the boat polars picked in Race tools ride along with My chart */
		var ps=document.getElementById('rtPolarSel');if(ps)mine.polar=ps.value||'';
		try{localStorage.setItem(MINE_KEY,JSON.stringify(mine));localStorage.setItem('oyc_wm_preset','mine');}catch(e){}
		syncMine();mark();
		var sv=document.getElementById('mineSave');
		if(sv){sv.textContent='Saved \u2713';sv.classList.add('ok');setTimeout(function(){sv.classList.remove('ok');syncMine();},1600);}
	}
	function applyMine(){
		if(!mine)return;
		applying=true;
		EXTRA.forEach(function(id){var cb=document.getElementById(id);if(cb&&cb.checked!==(mine.layers.indexOf(id)>=0)){cb.checked=!cb.checked;cb.dispatchEvent(new Event('change',{bubbles:true}));}});
		applying=false;
		if(typeof mine.polar==='string'){
			/* race-tools.js reads oyc_rt_polar (JSON) when its polar list loads */
			try{if(mine.polar)localStorage.setItem('oyc_rt_polar',JSON.stringify(mine.polar));else localStorage.removeItem('oyc_rt_polar');}catch(e){}
			var ps=document.getElementById('rtPolarSel');
			if(ps&&ps.value!==mine.polar&&[].some.call(ps.options,function(o){return o.value===mine.polar;})){ps.value=mine.polar;ps.dispatchEvent(new Event('change',{bubbles:true}));}
		}
		var tb=document.getElementById(mine.view||'tabWind');
		if(tb&&tb.getAttribute('aria-selected')!=='true')tb.click();
		var lm=window.OYCWM&&window.OYCWM.map;if(!lm||!mine.c||!mine.z)return;
		var c=mine.c,z=mine.z;
		function go(){try{lm.setView(c,z,{animate:false});}catch(e){}}
		/* the map may sit in a hidden tab (zero size); Leaflet would shift the
		   centre when it is first shown, so wait for it to have a real size */
		if(lm.getContainer().clientWidth)go();
		else lm.once('resize',function(){setTimeout(go,0);});
	}
	syncMine();
	var applying=false;
	function matches(){
		var hit=null;
		Object.keys(PRESETS).forEach(function(k){
			var want=PRESETS[k],ok=MANAGED.every(function(id){var cb=document.getElementById(id);return !cb||cb.checked===(want.indexOf(id)>=0);});
			if(ok)hit=k;
		});
		return hit;
	}
	function mark(){
		var on=matches();
		[].forEach.call(bar.querySelectorAll('.pre-b'),function(b){b.setAttribute('aria-pressed',b.getAttribute('data-preset')===on?'true':'false');});
	}
	function apply(k){
		if(k==='mine'&&!mine){saveMine();return;}   /* first tap on an empty My chart saves the current setup */
		var want=PRESETS[k];if(!want)return;
		var tw=document.getElementById('tabWind');
		if(k!=='mine'&&tw&&tw.getAttribute('aria-selected')!=='true')tw.click();   /* presets are map-view layers */
		applying=true;
		/* switch layers off first, then on, so a layer that depends on another
		   (Race needs the barbs/dials) ends up in its normal "on" state */
		MANAGED.forEach(function(id){var cb=document.getElementById(id);if(cb&&cb.checked&&want.indexOf(id)<0){cb.checked=false;cb.dispatchEvent(new Event('change',{bubbles:true}));}});
		want.forEach(function(id){var cb=document.getElementById(id);if(cb&&!cb.checked){cb.checked=true;cb.dispatchEvent(new Event('change',{bubbles:true}));}});
		applying=false;
		if(k==='mine')applyMine();
		try{localStorage.setItem('oyc_wm_preset',k);}catch(e){}
		mark();
	}
	bar.addEventListener('click',function(e){var b=e.target.closest?e.target.closest('.pre-b'):null;if(b)apply(b.getAttribute('data-preset'));});
	var svb=document.getElementById('mineSave');if(svb)svb.addEventListener('click',saveMine);
	/* a saved My chart is how the map opens */
	if(mine)setTimeout(function(){apply('mine');},0);
	drawer.addEventListener('change',function(){if(!applying)mark();});
	/* Layers drawer: open by default on wide screens, closed on phones; the
	   visitor's last choice wins */
	function setOpen(o,save){
		drawer.hidden=!o;btn.setAttribute('aria-expanded',o?'true':'false');
		/* the floating waypoint/route card (and target-speed badge) step left of the
		   open drawer so both stay in view, and go back when it closes */
		var g=document.getElementById('mapGrid');if(g)g.classList.toggle('lyr-open',o);
		if(save){try{localStorage.setItem('oyc_wm_drawer',o?'1':'0');}catch(e){}}
	}
	var saved=null;try{saved=localStorage.getItem('oyc_wm_drawer');}catch(e){}
	setOpen(saved===null?!window.matchMedia('(max-width:760px)').matches:saved==='1',false);
	btn.addEventListener('click',function(){setOpen(drawer.hidden,true);});
	document.getElementById('lyrClose').addEventListener('click',function(){setOpen(false,true);btn.focus();});
	drawer.addEventListener('keydown',function(e){if(e.key==='Escape'){setOpen(false,true);btn.focus();}});
	setTimeout(mark,0);
	window.addEventListener('load',mark);
})();
</script>
</div>
	<?php
	return ob_get_clean();
}
