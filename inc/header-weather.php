<?php
/**
 * Header weather glyph — a subtle, live current-conditions icon + temperature as
 * the first item of the primary nav, linking to the /weather/ board.
 *
 * The glyph shape reflects the current WMO weather code (sun / partly cloudy /
 * overcast / rain / snow / fog / thunder). It's a monochrome stroke icon so it
 * inherits the nav colour on every page (dark on solid headers, cream over hero
 * photos). Data is Open-Meteo current conditions at the harbor. Hidden until the
 * fetch resolves, so it never flashes an empty box or shifts the nav on failure.
 *
 * Parent-theme feature (deploys via the parent WP Pusher row). Loaded from
 * header.php so it registers before wp_nav_menu()/wp_head() run.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Prepend the weather link as the first item of the primary nav.
add_filter( 'wp_nav_menu_items', function ( $items, $args ) {
	if ( ! isset( $args->theme_location ) || 'primary' !== $args->theme_location ) {
		return $items;
	}
	$link = '<a class="oyc-wx-link" id="oycWxLink" href="' . esc_url( home_url( '/weather/' ) ) . '" hidden'
		. ' aria-label="' . esc_attr__( 'Harbor weather', 'orienta-yacht-club' ) . '">'
		. '<span class="oyc-wx-ico" id="oycWxIco" aria-hidden="true"></span>'
		. '<span class="oyc-wx-temp" id="oycWxTemp"></span></a>';
	return '<li class="oyc-wx-item menu-item">' . $link . '</li>' . $items;
}, 10, 2 );

// Styles (in <head> so the icon is styled the moment JS reveals it).
add_action( 'wp_head', function () {
	?>
	<style id="oyc-wx-css">
	.oyc-wx-item{display:flex;align-items:center}
	.oyc-wx-link{display:inline-flex;align-items:center;gap:5px;color:inherit;text-decoration:none;opacity:.82;transition:opacity .2s ease,color .2s ease}
	.oyc-wx-link[hidden]{display:none}
	.oyc-wx-link:hover{opacity:1;color:var(--harbor)}
	.oyc-wx-link .oyc-wx-ico{display:inline-flex}
	.oyc-wx-link .oyc-wx-ico svg{display:block}
	.oyc-wx-link .oyc-wx-temp{font-size:.95rem;font-weight:600;font-variant-numeric:tabular-nums;letter-spacing:.01em}
	.oyc-wx-item .oyc-wx-link::after{display:none !important}
	</style>
	<?php
} );

// Fetch current conditions and render the glyph + temperature.
add_action( 'wp_footer', function () {
	?>
	<script>
	(function(){
		var link=document.getElementById('oycWxLink'); if(!link) return;
		var icoEl=document.getElementById('oycWxIco'), tempEl=document.getElementById('oycWxTemp');
		function G(inner){ return '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+inner+'</svg>'; }
		var sunRays='<line x1="12" y1="1.8" x2="12" y2="3.6"/><line x1="12" y1="20.4" x2="12" y2="22.2"/><line x1="1.8" y1="12" x2="3.6" y2="12"/><line x1="20.4" y1="12" x2="22.2" y2="12"/><line x1="4.6" y1="4.6" x2="5.9" y2="5.9"/><line x1="18.1" y1="18.1" x2="19.4" y2="19.4"/><line x1="4.6" y1="19.4" x2="5.9" y2="18.1"/><line x1="18.1" y1="5.9" x2="19.4" y2="4.6"/>';
		var cloud='<path d="M7.5 18h9a3.4 3.4 0 0 0 .2-6.8 4.8 4.8 0 0 0-9.2-1.3A3.5 3.5 0 0 0 7.5 18Z"/>';
		function glyph(c, cc){
			if(c>=95) return G(cloud+'<polyline points="12,14.5 10,19 12.6,19 11,22.8"/>');                                   // thunder
			if((c>=71&&c<=77)||c===85||c===86) return G(cloud+'<g fill="currentColor" stroke="none"><circle cx="9" cy="20.8" r="1"/><circle cx="12.2" cy="21.8" r="1"/><circle cx="15.4" cy="20.8" r="1"/></g>'); // snow
			if((c>=51&&c<=67)||(c>=80&&c<=82)) return G(cloud+'<line x1="9" y1="20" x2="8" y2="22.6"/><line x1="12.5" y1="20" x2="11.5" y2="22.6"/><line x1="16" y1="20" x2="15" y2="22.6"/>'); // rain
			if(c===45||c===48) return G('<line x1="4" y1="9" x2="20" y2="9"/><line x1="6" y1="13" x2="18" y2="13"/><line x1="4" y1="17" x2="20" y2="17"/>'); // fog
			if(c===3 || (cc!=null&&cc>=80)) return G(cloud);                                                                    // overcast
			if(c===2 || (cc!=null&&cc>=35)) return G('<circle cx="8" cy="8" r="3"/><line x1="8" y1="2.6" x2="8" y2="3.8"/><line x1="2.6" y1="8" x2="3.8" y2="8"/><line x1="4.2" y1="4.2" x2="5.1" y2="5.1"/><path d="M8 17.5h8a3 3 0 0 0 .2-6 4.3 4.3 0 0 0-8-1.2A3.1 3.1 0 0 0 8 17.5Z"/>'); // partly cloudy
			return G('<circle cx="12" cy="12" r="4.2"/>'+sunRays);                                                              // clear
		}
		var LABEL={0:'Clear',1:'Mainly clear',2:'Partly cloudy',3:'Overcast',45:'Fog',48:'Fog',51:'Light drizzle',53:'Drizzle',55:'Drizzle',56:'Freezing drizzle',57:'Freezing drizzle',61:'Light rain',63:'Rain',65:'Heavy rain',66:'Freezing rain',67:'Freezing rain',71:'Light snow',73:'Snow',75:'Heavy snow',77:'Snow grains',80:'Showers',81:'Showers',82:'Heavy showers',85:'Snow showers',86:'Snow showers',95:'Thunderstorm',96:'Thunderstorm',99:'Thunderstorm'};
		function load(){
			fetch('https://api.open-meteo.com/v1/forecast?latitude=40.939&longitude=-73.734&current=weather_code,cloud_cover,temperature_2m&temperature_unit=fahrenheit&timezone=America/New_York',{cache:'no-store'})
				.then(function(r){ return r.json(); })
				.then(function(d){
					var cur=d&&d.current; if(!cur||cur.temperature_2m==null) return;
					var code=cur.weather_code, temp=Math.round(cur.temperature_2m), label=LABEL[code]||'Weather';
					icoEl.innerHTML=glyph(code, cur.cloud_cover);
					tempEl.textContent=temp+'°';
					link.setAttribute('title', label+' · '+temp+'° — harbor weather');
					link.setAttribute('aria-label', label+', '+temp+' degrees. Open the weather page.');
					link.hidden=false;
				})
				.catch(function(){});
		}
		load();
		setInterval(load, 15*60*1000); // refresh with Open-Meteo's ~15-min cadence
	})();
	</script>
	<?php
} );
