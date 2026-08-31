<?php
/**
 * Template Name: Radar & Wind — Live Map
 *
 * Full-bleed dark live-map page in the weather-board style. Publicly
 * accessible. Embeds Windy.com's free map widget (animated wind particles,
 * weather radar, rain, waves, temperature) centered on Mamaroneck Harbor /
 * western Long Island Sound, with in-page layer chips that swap the overlay.
 *
 * Auto-renders for a Page with slug "radar" (page-{slug} hierarchy), so no
 * template assignment is needed — just create a Page titled "Radar".
 *
 * @package Orienta_Yacht_Club
 */

// Site menu for the slim nav bar — same primary-menu-with-fallback as
// page-weather.php so the two boards navigate identically.
$oyc_radar_menu = '';
if ( has_nav_menu( 'primary' ) ) {
	$oyc_radar_menu = wp_nav_menu( array(
		'theme_location' => 'primary',
		'container'      => false,
		'items_wrap'     => '<ul class="sitebar-menu">%3$s</ul>',
		'depth'          => 1,
		'echo'           => false,
		'fallback_cb'    => '__return_empty_string',
	) );
}
if ( ! $oyc_radar_menu ) {
	$oyc_radar_menu = '<ul class="sitebar-menu">'
		. '<li><a href="' . esc_url( home_url( '/' ) ) . '">Home</a></li>'
		. '<li><a href="' . esc_url( home_url( '/#about' ) ) . '">About</a></li>'
		. '<li><a href="' . esc_url( home_url( '/#membership' ) ) . '">Membership</a></li>'
		. '<li><a href="' . esc_url( home_url( '/#sailing' ) ) . '">Boating</a></li>'
		. '<li><a href="' . esc_url( home_url( '/#visitors' ) ) . '">Visitors</a></li>'
		. '<li><a href="' . esc_url( home_url( '/weather/' ) ) . '">Weather</a></li>'
		. '<li><a href="' . esc_url( home_url( '/contact/' ) ) . '">Contact</a></li>'
		. '</ul>';
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Radar &amp; Wind — Mamaroneck Harbor · Orienta Yacht Club</title>
<style>
	:root{
		/* Same OYC brand palette as the weather board. */
		--bg1:#0b2a4a; --bg2:#04162a; --panel:rgba(11,42,74,.55); --panel2:rgba(7,32,58,.72);
		--edge:rgba(245,239,226,.12); --edge2:rgba(245,239,226,.24);
		--ink:#f5efe2; --muted:#aeb9c8; --faint:#84909f;
		--teal:#d4a851; --teal2:#b08a3e;
	}
	*{box-sizing:border-box;margin:0;padding:0}
	html,body{height:100%}
	body{
		font-family:"Arial Narrow","Helvetica Neue",Arial,sans-serif;
		background:radial-gradient(1200px 700px at 70% -10%,#12283f 0%,var(--bg1) 45%,var(--bg2) 100%);
		color:var(--ink); min-height:100vh; padding:18px; overflow-x:hidden;
		-webkit-font-smoothing:antialiased;
	}
	.wrap{max-width:1600px;margin:0 auto;display:flex;flex-direction:column;gap:16px;min-height:calc(100vh - 36px)}

	/* ---- site menu bar (same as the weather board) ---- */
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

	/* ---- top bar: title + layer chips ---- */
	.topbar{display:flex;align-items:center;justify-content:space-between;gap:14px 20px;
		padding:14px 20px;border:1px solid var(--edge);border-radius:16px;background:var(--panel2);flex-wrap:wrap}
	.brand{display:flex;align-items:baseline;gap:14px;flex-wrap:wrap}
	.brand h1{font-weight:800;letter-spacing:.06em;font-size:clamp(20px,2.4vw,34px);text-transform:uppercase;
		background:linear-gradient(180deg,#fff,#bfe4f5);-webkit-background-clip:text;background-clip:text;color:transparent}
	.brand .sub{color:var(--teal);letter-spacing:.22em;font-size:12px;text-transform:uppercase;font-weight:700}
	.chips{display:flex;gap:8px;flex-wrap:wrap}
	.chip{background:rgba(245,239,226,.05);border:1px solid var(--edge);color:var(--muted);border-radius:999px;
		padding:7px 16px;font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;cursor:pointer;
		transition:color .15s,background .15s,border-color .15s}
	.chip:hover{color:var(--teal);border-color:var(--edge2)}
	.chip.active{background:var(--teal);border-color:var(--teal);color:#0b2a4a}

	/* ---- map ---- */
	.map-card{flex:1;display:flex;border:1px solid var(--edge);border-radius:16px;overflow:hidden;
		background:var(--panel);min-height:480px;position:relative}
	.map-card iframe{flex:1;width:100%;border:0;display:block}

	.credit{text-align:center;color:rgba(255,255,255,0.55);font-size:0.82rem;letter-spacing:0.02em}
	.credit a{color:var(--muted)}
	.credit a:hover{color:var(--teal)}
</style>
</head>
<body>

<div class="wrap">
	<!-- SITE MENU -->
	<nav class="sitebar" aria-label="Site menu">
		<a class="sitebar-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>">&#8962;&nbsp;OYC</a>
		<button class="sitebar-toggle" aria-expanded="false" aria-label="Menu"><span class="sitebar-bars" aria-hidden="true"></span></button>
		<?php echo $oyc_radar_menu; ?>
	</nav>

	<!-- TOP BAR -->
	<div class="topbar">
		<div class="brand">
			<h1>Radar &amp; Wind</h1>
			<span class="sub">Mamaroneck Harbor &middot; Live Map</span>
		</div>
		<div class="chips" id="layerChips" role="group" aria-label="Map layer">
			<button type="button" class="chip active" data-overlay="wind">Wind</button>
			<button type="button" class="chip" data-overlay="radar">Radar</button>
			<button type="button" class="chip" data-overlay="rain">Rain</button>
			<button type="button" class="chip" data-overlay="waves">Waves</button>
			<button type="button" class="chip" data-overlay="temp">Temp</button>
		</div>
	</div>

	<!-- MAP -->
	<div class="map-card">
		<iframe id="windyMap" title="Live wind and radar map (Windy.com)" src="about:blank"></iframe>
	</div>

	<p class="credit">Live map &amp; model data by <a href="https://www.windy.com/" target="_blank" rel="noopener">Windy.com</a>.
		<em>Forecasts are best treated as an opinion. Poseidon always has the final word.</em></p>
</div>

<script>
(function(){
	"use strict";

	// ---------- SITE MENU (mobile hamburger) ----------
	var sbNav = document.querySelector('.sitebar');
	var sbBtn = document.querySelector('.sitebar-toggle');
	if(sbNav && sbBtn){
		sbBtn.addEventListener('click', function(){
			var open = sbNav.classList.toggle('open');
			sbBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
	}

	// ---------- WINDY EMBED ----------
	// Centered on the western Sound so the harbor, Execution Rocks and the
	// approaches are all in frame; the detail marker sits on Mamaroneck Harbor.
	// Knots + Fahrenheit to match the weather board. Switching chips swaps the
	// overlay param and reloads the embed (a moment of black is normal).
	var BASE = 'https://embed.windy.com/embed2.html'
		+ '?lat=40.92&lon=-73.70&zoom=10&level=surface&product=ecmwf'
		+ '&detailLat=40.946&detailLon=-73.732&marker=true&message=true'
		+ '&calendar=now&type=map&location=coordinates'
		+ '&metricWind=kt&metricTemp=%C2%B0F&radarRange=-1'
		+ '&menu=&pressure=&detail=&overlay=';
	var frame = document.getElementById('windyMap');
	var chips = document.querySelectorAll('#layerChips .chip');
	function setOverlay(name){
		frame.src = BASE + encodeURIComponent(name);
		for(var i=0;i<chips.length;i++){
			chips[i].classList.toggle('active', chips[i].getAttribute('data-overlay') === name);
		}
	}
	for(var i=0;i<chips.length;i++){
		chips[i].addEventListener('click', function(){ setOverlay(this.getAttribute('data-overlay')); });
	}
	setOverlay('wind');
}());
</script>

</body>
</html>
