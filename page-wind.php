<?php
/**
 * Template Name: Wind
 *
 * Self-hosted animated wind map over the western Long Island Sound (Leaflet +
 * leaflet-velocity, fed by Open-Meteo's GFS-based grid) with a 7-day time
 * slider and toggleable static layers (wind arrows, isobars), plus a
 * "Download GRIB" button that serves the latest GFS regional GRIB2 (see
 * inc/grib-endpoint.php) for members' nav software.
 *
 * Auto-renders for a Page with slug "wind" (page-{slug} hierarchy),
 * or assign this template to any Page.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! headers_sent() ) { nocache_headers(); }
$oyc_ajax = esc_url( admin_url( 'admin-ajax.php' ) );
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Wind — Mamaroneck Harbor · Orienta Yacht Club</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet-velocity@1.7.0/dist/leaflet-velocity.min.css">
<style>
:root{--navy:#0b2a4a;--navy-ink:#04162a;--harbor:#1583cf;--brass:#b08a3e;--ink:#16324a;--mute:#5a6b7d;--faint:#8a99a8;--line:#e0e7f0;--panel:#f5f9fd;--cream:#f7f3ea;}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;
	background:radial-gradient(1200px 700px at 70% -10%,#ffffff 0%,#eaf1f8 50%,#dde8f2 100%);color:var(--ink);min-height:100vh;padding:18px;-webkit-font-smoothing:antialiased}
.wrap{max-width:1160px;margin:0 auto;display:flex;flex-direction:column;gap:16px}
a{color:var(--harbor)}
/* header */
.topbar{display:flex;align-items:center;justify-content:space-between;gap:10px 20px;flex-wrap:wrap;
	padding:10px 22px;border:1px solid var(--line);border-radius:16px;background:#fff}
.tb-left{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.tb-brand{display:inline-flex;align-items:center;line-height:0;text-decoration:none}
.tb-logo{height:44px;width:auto;display:block}
.tb-title{color:var(--brass);font-weight:800;letter-spacing:.16em;text-transform:uppercase;font-size:13px}
.tb-nav{display:flex;gap:6px 16px;flex-wrap:wrap}
.tb-nav a{color:var(--mute);text-decoration:none;font-size:.82rem;font-weight:600;letter-spacing:.03em}
.tb-nav a:hover{color:var(--harbor)}
/* cards */
.card{background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 6px 22px rgba(11,42,74,.08);overflow:hidden}
.hd{display:flex;flex-wrap:wrap;align-items:baseline;gap:6px 14px;padding:14px 18px 8px}
.hd h2{margin:0;font-size:1.12rem;color:var(--navy);font-weight:700}
.hd .loc{font-size:.83rem;color:var(--mute)}
.hd .st{margin-left:auto;font-size:.75rem;color:var(--mute)}
.layers{display:flex;flex-wrap:wrap;gap:6px 8px;padding:2px 18px 8px}
.layers label{display:inline-flex;align-items:center;gap:6px;border:1px solid var(--line);border-radius:999px;padding:5px 12px;font-size:.78rem;font-weight:600;color:var(--ink);cursor:pointer;user-select:none}
.layers label:hover{border-color:var(--harbor)}
.layers input{accent-color:var(--harbor)}
.legend{display:flex;align-items:center;gap:2px;padding:0 18px 8px;font-size:.72rem;color:var(--mute);flex-wrap:wrap}
.legend .sc{display:flex;height:12px;border-radius:3px;overflow:hidden;width:190px;margin:0 8px}
.legend .sc i{flex:1}
.ctrl{display:flex;align-items:center;gap:12px;padding:8px 18px;border-top:1px solid var(--line);border-bottom:1px solid var(--line);background:var(--panel)}
.ctrl button{border:1px solid var(--line);background:#fff;color:var(--navy);width:38px;height:34px;border-radius:9px;cursor:pointer;font-size:15px;flex:none}
.ctrl button:hover{border-color:var(--harbor)}
.ctrl input[type=range]{flex:1;accent-color:var(--harbor);min-width:120px}
.ctrl .tlabel{font-weight:700;color:var(--navy);font-size:.9rem;white-space:nowrap;min-width:150px;text-align:right}
.ctrl .tlabel small{display:block;font-weight:600;color:var(--mute);font-size:.72rem}
#map{height:min(62vh,560px);width:100%;background:#dbe7f0}
.card .foot{padding:10px 18px 16px;font-size:.72rem;color:var(--mute)}
.leaflet-control.velocity-control{background:rgba(11,42,74,.82);color:#fff;padding:5px 9px;border-radius:8px;font-size:12px;font-weight:600}
.iso-lbl{background:none;border:none;box-shadow:none;color:#334;font-size:10px;font-weight:700;text-shadow:0 0 3px #fff,0 0 3px #fff}
/* download card */
.dl{padding:16px 18px}
.dl h2{margin:0 0 4px;font-size:1.05rem;color:var(--navy);font-weight:700}
.dl p{color:var(--mute);font-size:.86rem;line-height:1.5;margin:6px 0}
.dl-btn{display:inline-flex;align-items:center;gap:8px;margin-top:10px;background:var(--harbor);color:#fff;
	text-decoration:none;font-weight:700;font-size:.9rem;padding:11px 20px;border-radius:999px;transition:.15s}
.dl-btn:hover{background:#0f6fb0}
.dl .apps{color:var(--faint);font-size:.78rem;margin-top:10px}
.disclaimer{text-align:center;color:var(--mute);font-size:.82rem;font-style:italic}
</style>
</head>
<body>
<div class="wrap">
	<div class="topbar">
		<div class="tb-left">
			<a class="tb-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> — Home"><?php oyc_burgee( 'tb-logo' ); ?></a>
			<div class="tb-title">Mamaroneck Harbor &middot; Wind</div>
		</div>
		<nav class="tb-nav" aria-label="Weather pages">
			<a href="<?php echo esc_url( home_url( '/weather/' ) ); ?>">Live Conditions</a>
			<a href="<?php echo esc_url( home_url( '/radar/' ) ); ?>">Radar</a>
		</nav>
	</div>
	<script>/* standalone page: promote the lazy-load placeholder logo to its real src */
	(function(){var i=document.querySelector('.tb-logo');if(!i)return;var d=i.getAttribute('data-src')||i.getAttribute('data-lazy-src')||i.getAttribute('data-smush-src');if(d){i.src=d;i.removeAttribute('loading');}})();</script>

	<!-- WIND MAP -->
	<div class="card">
		<div class="hd"><h2>Wind Map</h2><span class="loc">Western Long Island Sound &middot; 7-day wind field</span><span class="st" id="st">Loading&hellip;</span></div>
		<div class="layers">
			<label><input type="checkbox" id="tgParticles" checked> Animated</label>
			<label><input type="checkbox" id="tgArrows"> Wind arrows</label>
			<label><input type="checkbox" id="tgIso"> Isobars</label>
		</div>
		<div class="legend">0 kt <span class="sc" id="scale"></span> 40+ kt</div>
		<div class="ctrl">
			<button id="play" title="Play/pause">&#9654;</button>
			<input type="range" id="slider" min="0" max="0" value="0" step="1" aria-label="Forecast time">
			<div class="tlabel" id="tlabel">&mdash;<small>&nbsp;</small></div>
		</div>
		<div id="map"></div>
		<div class="foot">Wind &amp; pressure from <a href="https://open-meteo.com" target="_blank" rel="noopener">Open-Meteo</a> (GFS-based), 0.15&deg; grid, 3-hourly to 7 days.</div>
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
var RAMP=['#8fc0dd','#5aa6d0','#3f93c9','#d9c07a','#e0a13f','#dd7f3a','#cf5638','#b23a2a','#8f2d20'];
document.getElementById('scale').innerHTML=RAMP.map(function(c){return '<i style="background:'+c+'"></i>';}).join('');
function spdColor(kt){var b=[[8,'#8fc0dd'],[11,'#5aa6d0'],[14,'#3f93c9'],[17,'#d9c07a'],[20,'#e0a13f'],[24,'#dd7f3a'],[28,'#cf5638'],[34,'#b23a2a'],[999,'#8f2d20']];for(var i=0;i<b.length;i++)if(kt<b[i][0])return b[i][1];}

var LA1=41.3,LA2=40.5,LO1=-74.1,LO2=-72.3,DX=0.15,DY=0.15;
var NY=Math.round((LA1-LA2)/DY)+1,NX=Math.round((LO2-LO1)/DX)+1;
var lats=[],lons=[],i,j;
for(i=0;i<NY;i++)lats.push(+(LA1-i*DY).toFixed(2));
for(j=0;j<NX;j++)lons.push(+(LO1+j*DX).toFixed(2));
var LAT=[],LON=[];
for(i=0;i<NY;i++)for(j=0;j<NX;j++){LAT.push(lats[i]);LON.push(lons[j]);}
var HDR={parameterCategory:2,nx:NX,ny:NY,lo1:LO1,lo2:LO2,la1:LA1,la2:LA2,dx:DX,dy:DY};

var map=L.map('map').setView([40.92,-73.4],9);
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{attribution:'&copy; OpenStreetMap contributors',maxZoom:19}).addTo(map);
map.fitBounds([[LA2,LO1],[LA1,LO2]]);

var SP=[],DR=[],PR=[],TIMES=[],FRAMES=[],vl=null,NOWI=0,playing=false,timer=null,curTi=0;
var arrowsLayer=L.layerGroup(),isoLayer=L.layerGroup();
var slider=document.getElementById('slider'),tlabel=document.getElementById('tlabel'),playBtn=document.getElementById('play');

function frame(ti){var u=new Array(LAT.length),v=new Array(LAT.length);
	for(var k=0;k<LAT.length;k++){var sp=SP[k][ti]||0,dr=(DR[k][ti]||0)*Math.PI/180;u[k]=-sp*Math.sin(dr);v[k]=-sp*Math.cos(dr);}
	return [{header:Object.assign({parameterNumber:2,refTime:TIMES[ti],forecastTime:0},HDR),data:u},
			{header:Object.assign({parameterNumber:3,refTime:TIMES[ti],forecastTime:0},HDR),data:v}];}
function fmt(iso){var d=new Date(iso);return {big:(d.getHours()%12||12)+' '+(d.getHours()>=12?'PM':'AM'),small:d.toLocaleDateString('en-US',{weekday:'short',month:'short',day:'numeric'})};}

function drawArrows(ti){arrowsLayer.clearLayers();
	for(var k=0;k<LAT.length;k++){var sp=(SP[k][ti]||0)*1.94384,dir=DR[k][ti]||0;
		var len=Math.min(20,7+sp*0.55),col=spdColor(sp),rot=(dir+180)%360;
		var html='<svg width="30" height="30" viewBox="-15 -15 30 30" style="overflow:visible"><g transform="rotate('+rot+')">'
			+'<line x1="0" y1="'+(len/2).toFixed(1)+'" x2="0" y2="'+(-len/2).toFixed(1)+'" stroke="'+col+'" stroke-width="2.2"/>'
			+'<path d="M0,'+(-len/2).toFixed(1)+' L3.5,'+(-len/2+5).toFixed(1)+' L-3.5,'+(-len/2+5).toFixed(1)+' Z" fill="'+col+'"/></g></svg>';
		L.marker([LAT[k],LON[k]],{icon:L.divIcon({className:'',html:html,iconSize:[30,30],iconAnchor:[15,15]}),interactive:false}).addTo(arrowsLayer);
	}}
function drawIso(ti){isoLayer.clearLayers();
	var P=[];for(var r=0;r<NY;r++){P[r]=[];for(var c=0;c<NX;c++)P[r][c]=PR[r*NX+c][ti];}
	var mn=1e9,mx=-1e9;for(r=0;r<NY;r++)for(c=0;c<NX;c++){if(P[r][c]<mn)mn=P[r][c];if(P[r][c]>mx)mx=P[r][c];}
	var STEP=1,latAt=function(r){return lats[r];},lonAt=function(c){return lons[c];};
	function lerp(a,b,t,axa,axb){return axa+(axb-axa)*((t-a)/(b-a));}
	for(var thr=Math.ceil(mn/STEP)*STEP;thr<=mx;thr+=STEP){
		for(r=0;r<NY-1;r++)for(c=0;c<NX-1;c++){
			var tl=P[r][c],tr=P[r][c+1],br=P[r+1][c+1],bl=P[r+1][c],pts=[];
			if((tl-thr)*(tr-thr)<0)pts.push([latAt(r),lerp(tl,tr,thr,lonAt(c),lonAt(c+1))]);
			if((tr-thr)*(br-thr)<0)pts.push([lerp(tr,br,thr,latAt(r),latAt(r+1)),lonAt(c+1)]);
			if((bl-thr)*(br-thr)<0)pts.push([latAt(r+1),lerp(bl,br,thr,lonAt(c),lonAt(c+1))]);
			if((tl-thr)*(bl-thr)<0)pts.push([lerp(tl,bl,thr,latAt(r),latAt(r+1)),lonAt(c)]);
			if(pts.length>=2){
				L.polyline([pts[0],pts[1]],{color:'#37506b',weight:1.4,opacity:.85,interactive:false}).addTo(isoLayer);
				if(pts.length===4)L.polyline([pts[2],pts[3]],{color:'#37506b',weight:1.4,opacity:.85,interactive:false}).addTo(isoLayer);
				if(r%2===0&&c===Math.floor(NX/2))L.marker(pts[0],{icon:L.divIcon({className:'iso-lbl',html:Math.round(thr)+'',iconSize:[26,12]}),interactive:false}).addTo(isoLayer);
			}
		}
	}}
function refreshOverlays(){if(document.getElementById('tgArrows').checked)drawArrows(curTi);if(document.getElementById('tgIso').checked)drawIso(curTi);}
function show(ti){ti=Math.max(0,Math.min(FRAMES.length-1,ti));curTi=ti;slider.value=ti;
	if(vl)vl.setData(FRAMES[ti]);var f=fmt(TIMES[ti]);tlabel.innerHTML=f.big+'<small>'+f.small+(ti===NOWI?' · now':'')+'</small>';refreshOverlays();}

var url='https://api.open-meteo.com/v1/forecast?latitude='+LAT.join(',')+'&longitude='+LON.join(',')
	+'&hourly=wind_speed_10m,wind_direction_10m,pressure_msl&wind_speed_unit=ms&temporal_resolution=hourly_3&forecast_days=7&timezone=America%2FNew_York';
fetch(url).then(function(r){return r.json();}).then(function(arr){
	if(!Array.isArray(arr))throw new Error('grid');
	TIMES=arr[0].hourly.time;
	for(var k=0;k<arr.length;k++){SP.push(arr[k].hourly.wind_speed_10m);DR.push(arr[k].hourly.wind_direction_10m);PR.push(arr[k].hourly.pressure_msl);}
	var now=Date.now(),bd=1e15;for(var t=0;t<TIMES.length;t++){var dd=Math.abs(new Date(TIMES[t]).getTime()-now);if(dd<bd){bd=dd;NOWI=t;}}
	for(t=0;t<TIMES.length;t++)FRAMES.push(frame(t));
	slider.max=FRAMES.length-1;
	vl=L.velocityLayer({displayValues:true,displayOptions:{velocityType:'Wind',position:'bottomleft',emptyString:'No wind data',showCardinal:true,speedUnit:'kt',directionString:'From',speedString:'Wind'},
		data:FRAMES[NOWI],maxVelocity:20,velocityScale:0.012,lineWidth:2.2,particleAge:80,particleMultiplier:1/260,colorScale:RAMP,frameRate:20});
	vl.addTo(map);
	show(NOWI);
	document.getElementById('st').textContent='7-day forecast · '+FRAMES.length+' frames · updated '+new Date(now).toLocaleString('en-US',{hour:'numeric',minute:'2-digit',month:'short',day:'numeric'});
}).catch(function(e){document.getElementById('st').textContent='Wind field unavailable ('+e+')';});

slider.addEventListener('input',function(){stop();show(+slider.value);});
playBtn.addEventListener('click',function(){playing?stop():play();});
function play(){if(!FRAMES.length)return;playing=true;playBtn.innerHTML='&#10073;&#10073;';timer=setInterval(function(){var n=+slider.value+1;if(n>=FRAMES.length)n=0;show(n);},700);}
function stop(){playing=false;playBtn.innerHTML='&#9654;';if(timer){clearInterval(timer);timer=null;}}
document.getElementById('tgParticles').addEventListener('change',function(){if(this.checked){if(vl)vl.addTo(map);}else if(vl)map.removeLayer(vl);});
document.getElementById('tgArrows').addEventListener('change',function(){if(this.checked){arrowsLayer.addTo(map);drawArrows(curTi);}else map.removeLayer(arrowsLayer);});
document.getElementById('tgIso').addEventListener('change',function(){if(this.checked){isoLayer.addTo(map);drawIso(curTi);}else map.removeLayer(isoLayer);});
})();
</script>
</body>
</html>
