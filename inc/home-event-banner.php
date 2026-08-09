<?php
/**
 * Home page "Upcoming Events" banner.
 *
 * A slim, full-reel-width strip that sits between the menu bar and the photo
 * reel on the front page. It shows the single next upcoming event pulled live
 * from the club calendar (the same Calendarize it! endpoint the /calendar/ page
 * uses), so it updates itself whenever events change — no code edits. Clicking
 * it opens the calendar's List view. Hides itself when nothing is upcoming.
 *
 * Placed via wp_footer at priority 11 (after the child-theme photo carousel at
 * priority 10) so the carousel is already in the DOM when we slot in above it.
 *
 * @package Orienta_Yacht_Club
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_footer', 'oyc_home_event_banner', 11 );
function oyc_home_event_banner() {
	if ( ! is_front_page() ) {
		return;
	}
	$cal_url = esc_url( home_url( '/calendar/?view=list' ) );
	?>
	<style id="oyc-ann-css">
	.oyc-ann{display:block;box-sizing:border-box;width:min(1052px,92%);margin:0 auto;background:var(--navy,#0a2949);color:var(--cream,#f5efe2);text-decoration:none;border-radius:10px;overflow:hidden;border:1px solid rgba(212,168,81,.35);box-shadow:0 6px 20px rgba(4,22,42,.14);transition:background .2s ease,box-shadow .2s ease}
	.oyc-ann[hidden]{display:none}
	.oyc-ann:hover{background:#0d3358;box-shadow:0 8px 26px rgba(4,22,42,.22)}
	.oyc-ann:focus-visible{outline:2px solid var(--brass-bright,#d4a851);outline-offset:2px}
	.oyc-ann__inner{display:flex;align-items:center;gap:16px;padding:12px 20px}
	.oyc-ann__kicker{display:inline-flex;align-items:center;gap:8px;font-size:.7rem;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--brass-bright,#d4a851);white-space:nowrap}
	.oyc-ann__title{font-weight:800;font-size:1.02rem}
	.oyc-ann__sep{opacity:.4}
	.oyc-ann__meta{color:rgba(245,239,226,.85);font-size:.95rem;white-space:nowrap}
	.oyc-ann__cta{margin-left:auto;font-weight:700;color:var(--brass-bright,#d4a851);white-space:nowrap;display:inline-flex;align-items:center;gap:6px}
	.oyc-ann__cta .arw{transition:transform .2s ease}
	.oyc-ann:hover .oyc-ann__cta .arw{transform:translateX(3px)}
	@media(max-width:640px){.oyc-ann__meta,.oyc-ann__sep{display:none}.oyc-ann__inner{gap:10px;padding:11px 14px}.oyc-ann__cta{font-size:.9rem}}
	</style>
	<a class="oyc-ann" id="oycAnn" href="<?php echo $cal_url; ?>" hidden>
		<span class="oyc-ann__inner">
			<span class="oyc-ann__kicker"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>Upcoming Events</span>
			<span class="oyc-ann__title" id="oycAnnTitle"></span>
			<span class="oyc-ann__sep" id="oycAnnSep" hidden>&middot;</span>
			<span class="oyc-ann__meta" id="oycAnnMeta"></span>
			<span class="oyc-ann__cta">View calendar <span class="arw">&rarr;</span></span>
		</span>
	</a>
	<script>
	(function(){
		var a=document.getElementById('oycAnn'); if(!a) return;
		var header=document.querySelector('.site-header,header');
		var carousel=document.querySelector('.oyc-carousel');
		var shown=false;
		// Slot the banner between the (fixed) header and the reel, taking over the
		// header clearance so the reel tucks up right beneath it. Only once we have
		// an event to show — otherwise the reel keeps its own header margin.
		function place(){
			if(!shown) return;
			var hH=header?header.offsetHeight:72;
			if(carousel && carousel.parentNode){
				if(a.nextElementSibling!==carousel){ carousel.parentNode.insertBefore(a, carousel); }
				a.style.marginTop=(hH+12)+'px';
				carousel.style.marginTop='12px';
			} else if(header && header.parentNode){
				if(header.nextElementSibling!==a){ header.parentNode.insertBefore(a, header.nextSibling); }
				a.style.marginTop=(hH+12)+'px';
			}
		}
		window.addEventListener('resize', place);
		function pad(n){ return String(n).padStart(2,'0'); }
		var now=new Date();
		var todayStr=now.getFullYear()+'-'+pad(now.getMonth()+1)+'-'+pad(now.getDate());
		var start=Math.floor(new Date(now.getFullYear(),now.getMonth(),now.getDate()).getTime()/1000);
		var end=Math.floor(new Date(now.getFullYear()+1,now.getMonth(),1).getTime()/1000);
		fetch('/?rhc_action=get_calendar_events&post_type[]=events&start='+start+'&end='+end,{credentials:'same-origin',cache:'no-store'})
			.then(function(r){ return r.json(); })
			.then(function(d){
				var evs=(((d&&d.EVENTS)||[])).filter(function(e){ return e.fc_start && e.title && e.fc_start>=todayStr; })
					.sort(function(x,y){ return x.fc_start.localeCompare(y.fc_start) || (x.start||'').localeCompare(y.start||''); });
				if(!evs.length){ return; }
				var ev=evs[0], dt=new Date(ev.fc_start+'T00:00:00');
				var DOW=['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
				var MON=['January','February','March','April','May','June','July','August','September','October','November','December'];
				var when=DOW[dt.getDay()]+', '+MON[dt.getMonth()]+' '+dt.getDate();
				var tm='';
				if(!ev.allDay){ var m=/(\d{2}):(\d{2})/.exec(ev.start||''); if(m){ var h=+m[1], ap=h<12?'AM':'PM', h12=h%12||12; tm=' · '+h12+':'+m[2]+' '+ap; } }
				document.getElementById('oycAnnTitle').textContent=ev.title;
				document.getElementById('oycAnnMeta').textContent=when+tm;
				document.getElementById('oycAnnSep').hidden=false;
				a.hidden=false; shown=true; place();
			})
			.catch(function(){});
	})();
	</script>
	<?php
}
