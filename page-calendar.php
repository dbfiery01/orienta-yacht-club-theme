<?php
/**
 * Template Name: Club Calendar
 * Displays the OYC season calendar with month grid + list views.
 *
 * @package Orienta_Yacht_Club
 */

get_header();
?>

<div class="page-hero">
	<div class="container">
		<h1 class="page-hero-title"><?php the_title(); ?></h1>
	</div>
</div>

<section class="section cal-section">
	<div class="container">

		<!-- Legend + view toggle -->
		<div class="cal-toolbar">
			<div class="cal-legend">
				<span class="cal-dot cal-dot--race"></span><?php esc_html_e( 'Racing', 'orienta-yacht-club' ); ?>
				<span class="cal-dot cal-dot--social"></span><?php esc_html_e( 'Social', 'orienta-yacht-club' ); ?>
				<span class="cal-dot cal-dot--fishing"></span><?php esc_html_e( 'Fishing', 'orienta-yacht-club' ); ?>
				<span class="cal-dot cal-dot--meeting"></span><?php esc_html_e( 'Meeting', 'orienta-yacht-club' ); ?>
			</div>
			<div class="cal-view-toggle">
				<button class="cal-view-btn active" data-view="month" aria-label="<?php esc_attr_e( 'Month view', 'orienta-yacht-club' ); ?>">
					<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="1" y="1" width="6" height="6" rx="1" fill="currentColor"/><rect x="9" y="1" width="6" height="6" rx="1" fill="currentColor"/><rect x="1" y="9" width="6" height="6" rx="1" fill="currentColor"/><rect x="9" y="9" width="6" height="6" rx="1" fill="currentColor"/></svg>
					<?php esc_html_e( 'Month', 'orienta-yacht-club' ); ?>
				</button>
				<button class="cal-view-btn" data-view="list" aria-label="<?php esc_attr_e( 'List view', 'orienta-yacht-club' ); ?>">
					<svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="1" y="2" width="14" height="2" rx="1" fill="currentColor"/><rect x="1" y="7" width="14" height="2" rx="1" fill="currentColor"/><rect x="1" y="12" width="14" height="2" rx="1" fill="currentColor"/></svg>
					<?php esc_html_e( 'List', 'orienta-yacht-club' ); ?>
				</button>
			</div>
		</div>

		<?php
		$oyc_ics_https  = home_url( '/?oyc_calendar_ics=1' );
		$oyc_ics_dl     = home_url( '/?oyc_calendar_ics=1&download=1' );
		$oyc_ics_webcal = preg_replace( '#^https?://#', 'webcal://', $oyc_ics_https );
		$oyc_google     = 'https://calendar.google.com/calendar/r?cid=' . rawurlencode( $oyc_ics_webcal );
		$oyc_outlook    = 'https://outlook.live.com/calendar/0/addfromweb?url=' . rawurlencode( $oyc_ics_https ) . '&name=' . rawurlencode( 'Orienta Yacht Club' );
		?>
		<div class="cal-export-row">
			<label for="cal-export-select" class="cal-export-label"><?php esc_html_e( 'Add this calendar to:', 'orienta-yacht-club' ); ?></label>
			<select id="cal-export-select" class="cal-export-select">
				<option value="apple" selected><?php esc_html_e( 'Apple Calendar (subscribe)', 'orienta-yacht-club' ); ?></option>
				<option value="google"><?php esc_html_e( 'Google Calendar (subscribe)', 'orienta-yacht-club' ); ?></option>
				<option value="outlook"><?php esc_html_e( 'Outlook (subscribe)', 'orienta-yacht-club' ); ?></option>
				<option value="download"><?php esc_html_e( 'Download .ics file', 'orienta-yacht-club' ); ?></option>
			</select>
			<button type="button" class="cal-export-btn" id="cal-export-go"><?php esc_html_e( 'Add', 'orienta-yacht-club' ); ?></button>
		</div>
		<script>
		var OYC_FEED = {
			apple:    <?php echo wp_json_encode( $oyc_ics_webcal ); ?>,
			google:   <?php echo wp_json_encode( $oyc_google ); ?>,
			outlook:  <?php echo wp_json_encode( $oyc_outlook ); ?>,
			download: <?php echo wp_json_encode( $oyc_ics_dl ); ?>
		};
		</script>

		<!-- Month nav -->
		<div class="cal-nav" id="cal-nav">
			<button class="cal-nav-btn" id="cal-prev" aria-label="Previous month">&#8592;</button>
			<h2 class="cal-month-label" id="cal-month-label"></h2>
			<button class="cal-nav-btn" id="cal-next" aria-label="Next month">&#8594;</button>
		</div>

		<!-- Month grid view -->
		<div id="cal-month-view">
			<div class="cal-grid-head">
				<?php foreach ( ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d ) : ?>
					<div class="cal-dow"><?php echo esc_html( $d ); ?></div>
				<?php endforeach; ?>
			</div>
			<div class="cal-grid" id="cal-grid"></div>
		</div>

		<!-- List view -->
		<div id="cal-list-view">
			<div class="cal-list-toolbar">
				<label for="cal-date-picker" class="cal-list-pick-label"><?php esc_html_e( 'Jump to a date', 'orienta-yacht-club' ); ?></label>
				<input type="date" id="cal-date-picker" class="cal-date-picker" />
			</div>
			<div class="cal-list" id="cal-list"></div>
		</div>

		<!-- Event detail popup -->
		<div class="cal-popup" id="cal-popup" role="dialog" aria-modal="true" aria-labelledby="cal-popup-title" hidden>
			<div class="cal-popup-inner">
				<button class="cal-popup-close" id="cal-popup-close" aria-label="Close">&#x2715;</button>
				<span class="cal-popup-cat" id="cal-popup-cat"></span>
				<h3 id="cal-popup-title"></h3>
				<p class="cal-popup-date" id="cal-popup-date"></p>
				<p class="cal-popup-desc" id="cal-popup-desc"></p>
			</div>
		</div>

	</div>
</section>

<style>
	/* Per-day weather overlay on the month grid (Actual / Forecast / Average) */
	.cal-wx{display:flex;flex-direction:column;align-items:flex-start;gap:1px;margin:3px 0 3px;line-height:1.2}
	.cal-wx-lbl{font-size:9px;text-transform:uppercase;letter-spacing:.03em;color:#8a97a5;font-weight:700}
	.cal-wx-r{display:flex;align-items:center;gap:5px}
	.cal-wx-t{font-size:12px}
	.cal-wx-t .hi{color:#e2574c;font-weight:800}
	.cal-wx-t .lo{color:#3b82c4;font-weight:800;margin-left:3px}
	.cal-wx-p{color:#6b7784;font-size:10.5px;display:inline-flex;align-items:center;gap:2px;margin-top:1px}
	.cal-wx--average{opacity:.9}
	.cal-wx--average .cal-wx-lbl{color:#a2adb8}
</style>

<script>
/* ===== OYC Calendar — events loaded live from Calendarize it! ========= */
let EVENTS = [];
function oycInferCat(t){ t=(t||'').toLowerCase(); if(/\brace|cup|wsl|regatta|raft up\b/.test(t))return 'race'; if(/meeting/.test(t))return 'meeting'; if(/fish/.test(t))return 'fishing'; return 'social'; }
function oycLoadEvents(){
  var s=Math.floor(new Date(currentYear-1,0,1).getTime()/1000);
  var e=Math.floor(new Date(currentYear+2,0,1).getTime()/1000);
  return fetch('/?rhc_action=get_calendar_events&post_type[]=events&start='+s+'&end='+e+'&rev=<?php echo function_exists( "oyc_cal_rev" ) ? oyc_cal_rev() : time(); ?>', {credentials:'same-origin', cache:'no-store'})
    .then(function(r){ return r.json(); })
    .then(function(d){
      function _fmtT(dt){ var m=/\d{4}-\d{2}-\d{2} (\d{2}):(\d{2})/.exec(dt||''); if(!m) return ''; var h=+m[1], ap=h<12?'AM':'PM', h12=h%12||12; return h12+':'+m[2]+' '+ap; }
      EVENTS = (((d&&d.EVENTS)||[]).map(function(ev){
        var time='';
        if(!ev.allDay){ var st=_fmtT(ev.start), en=_fmtT(ev.end); time = st ? ((en && en!==st) ? (st+' – '+en) : st) : ''; }
        return { id: ev.id, date: ev.fc_start, title: ev.title, cat: oycInferCat(ev.title), time: time };
      })).filter(function(x){ return x.date && x.title; });
    })
    .catch(function(){ EVENTS=[]; });
}

const CAT_LABELS = { race:'Racing', social:'Social', fishing:'Fishing', meeting:'Meeting' };
const CAT_COLORS = { race:'var(--navy)', social:'var(--brass-bright)', fishing:'var(--harbor)', meeting:'#888' };

let currentYear = 2026, currentMonth = (new Date().getMonth()); // 0-indexed
let currentView = 'month';

const months = ['January','February','March','April','May','June',
                 'July','August','September','October','November','December'];
const daysInMonth = (y,m) => new Date(y, m+1, 0).getDate();
const firstDOW    = (y,m) => new Date(y, m, 1).getDay();

function eventsFor(y, m) {
  const prefix = `${y}-${String(m+1).padStart(2,'0')}`;
  return EVENTS.filter(e => e.date.startsWith(prefix))
               .sort((a,b) => a.date.localeCompare(b.date));
}

/* ---- Daily weather overlay (Open-Meteo): hi/lo, precip, icon per day.
       Past = "Actual", today..+16d = "Forecast", further out = month "Average". ---- */
var WX = {};
var WX_TODAY = (function(){ var t=new Date(); return t.getFullYear()+'-'+String(t.getMonth()+1).padStart(2,'0')+'-'+String(t.getDate()).padStart(2,'0'); })();
var MONTH_AVG = [[39,26],[42,28],[50,34],[61,44],[71,54],[79,63],[84,69],[82,68],[75,61],[64,50],[53,41],[44,32]]; // coastal-Westchester normals [hi,lo]°F
function loadCalWeather(){
  var url='https://api.open-meteo.com/v1/forecast?latitude=40.939&longitude=-73.734&daily=weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum&temperature_unit=fahrenheit&precipitation_unit=inch&timezone=America/New_York&past_days=92&forecast_days=16';
  return fetch(url,{cache:'no-store'}).then(function(r){ return r.json(); }).then(function(j){
    var D=j&&j.daily; if(!D||!D.time) return;
    for(var i=0;i<D.time.length;i++){ var ds=D.time[i];
      WX[ds]={ hi:D.temperature_2m_max[i], lo:D.temperature_2m_min[i], precip:D.precipitation_sum[i], code:D.weather_code[i], kind:(ds<WX_TODAY?'actual':'forecast') }; }
  }).catch(function(){});
}
function avgFor(m){ return { hi:MONTH_AVG[m][0], lo:MONTH_AVG[m][1], precip:null, code:2, kind:'average' }; }
function calWxIcon(c){
  var ray=[0,45,90,135,180,225,270,315].map(function(a){var r=a*Math.PI/180;return '<line x1="'+(12+Math.cos(r)*7.3).toFixed(1)+'" y1="'+(12+Math.sin(r)*7.3).toFixed(1)+'" x2="'+(12+Math.cos(r)*9.6).toFixed(1)+'" y2="'+(12+Math.sin(r)*9.6).toFixed(1)+'" stroke="#f4b400" stroke-width="1.6" stroke-linecap="round"/>';}).join('');
  var sun='<circle cx="12" cy="12" r="5" fill="#f4b400"/>'+ray;
  var cloud='<path d="M7.5 17a3.6 3.6 0 0 1 .2-7.2 5 5 0 0 1 9.5 1.3 3.3 3.3 0 0 1-.3 6.6Z" fill="#9aa7b3"/>';
  var scloud='<circle cx="9" cy="8.5" r="3.4" fill="#f4b400"/>'+cloud;
  var drops='<g stroke="#3b82c4" stroke-width="1.7" stroke-linecap="round"><line x1="9" y1="18.5" x2="8.2" y2="21.5"/><line x1="12" y1="18.5" x2="11.2" y2="21.5"/><line x1="15" y1="18.5" x2="14.2" y2="21.5"/></g>';
  var bolt='<polygon points="12.5,16.5 9.5,21.5 11.7,21.5 10.3,24.5 14.5,19.5 12.3,19.5" fill="#f4b400"/>';
  var fog='<g stroke="#9aa7b3" stroke-width="1.6" stroke-linecap="round"><line x1="7" y1="19.5" x2="17" y2="19.5"/><line x1="8.6" y1="22" x2="15.4" y2="22"/></g>';
  var g; if(c>=95)g=cloud+bolt; else if((c>=51&&c<=67)||(c>=80&&c<=82)||(c>=71&&c<=86))g=cloud+drops;
  else if(c===45||c===48)g=cloud+fog; else if(c===3)g=cloud; else if(c===2)g=scloud; else g=sun;
  return '<svg viewBox="0 0 24 24" width="20" height="20" style="overflow:visible">'+g+'</svg>';
}
function calDrop(){ return '<svg viewBox="0 0 24 24" width="10" height="10"><path d="M12 3C12 3 5.5 11.5 5.5 16a6.5 6.5 0 0 0 13 0C18.5 11.5 12 3 12 3Z" fill="#7fb3dc"/></svg>'; }
function wxBlock(w){
  var el=document.createElement('div'); el.className='cal-wx cal-wx--'+w.kind;
  var t=w.kind==='actual'?'Actual':(w.kind==='average'?'Average':'Forecast');
  var pin=(w.precip!=null)?(w.precip>=0.005?(Math.round(w.precip*100)/100):0):null;
  var ps=(pin!=null)?('<span class="cal-wx-p">'+calDrop()+pin+' in</span>'):'';
  el.innerHTML='<span class="cal-wx-lbl">'+t+'</span><div class="cal-wx-r"><span class="cal-wx-ico">'+calWxIcon(w.code)+'</span><span class="cal-wx-t"><b class="hi">'+Math.round(w.hi)+'°</b><b class="lo">'+Math.round(w.lo)+'°</b></span></div>'+ps;
  return el;
}

function renderMonth() {
  document.getElementById('cal-month-label').textContent =
    `${months[currentMonth]} ${currentYear}`;

  const grid = document.getElementById('cal-grid');
  grid.innerHTML = '';

  const total = daysInMonth(currentYear, currentMonth);
  const start = firstDOW(currentYear, currentMonth);
  const evs   = eventsFor(currentYear, currentMonth);

  // blank leading cells
  for (let i = 0; i < start; i++) {
    const c = document.createElement('div');
    c.className = 'cal-cell cal-cell--empty';
    grid.appendChild(c);
  }

  const today = new Date();

  for (let d = 1; d <= total; d++) {
    const dateStr = `${currentYear}-${String(currentMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    const dayEvs  = evs.filter(e => e.date === dateStr);
    const isToday = (today.getFullYear()===currentYear && today.getMonth()===currentMonth && today.getDate()===d);

    const cell = document.createElement('div');
    cell.className = 'cal-cell' + (isToday ? ' cal-cell--today' : '');

    const num = document.createElement('span');
    num.className = 'cal-day-num';
    num.textContent = d;
    cell.appendChild(num);

    // weather: actual (past) / forecast (near) / month-average (far out)
    var wx = WX[dateStr];
    if (!wx && dateStr >= WX_TODAY) { wx = avgFor(currentMonth); }
    if (wx) { cell.appendChild(wxBlock(wx)); }

    dayEvs.slice(0, 3).forEach(ev => {
      const pill = document.createElement('button');
      pill.className = `cal-pill cal-pill--${ev.cat}`;
      pill.textContent = ev.title;
      pill.addEventListener('click', () => showPopup(ev));
      cell.appendChild(pill);
    });
    if (dayEvs.length > 3) {
      const more = document.createElement('span');
      more.className = 'cal-more';
      more.textContent = `+${dayEvs.length - 3} more`;
      cell.appendChild(more);
    }
    grid.appendChild(cell);
  }
}

function renderList() {
  const list = document.getElementById('cal-list');
  list.innerHTML = '';

  // Show 3 months from current
  for (let mi = 0; mi < 3; mi++) {
    let m = (currentMonth + mi) % 12;
    let y = currentYear + Math.floor((currentMonth + mi) / 12);
    const evs = eventsFor(y, m);
    if (!evs.length) continue;

    const header = document.createElement('h3');
    header.className = 'cal-list-month';
    header.textContent = `${months[m]} ${y}`;
    list.appendChild(header);

    evs.forEach(ev => {
      const d = new Date(ev.date + 'T12:00:00');
      const item = document.createElement('button');
      item.className = `cal-list-item cal-list-item--${ev.cat}`;
      item.innerHTML = `
        <span class="cal-list-date">${d.toLocaleDateString('en-US',{weekday:'short',month:'short',day:'numeric'})}</span>
        <span class="cal-list-body">
          <strong>${ev.title}</strong>
          <span class="cal-list-cat">${CAT_LABELS[ev.cat]}</span>
        </span>`;
      item.addEventListener('click', () => showPopup(ev));
      list.appendChild(item);
    });
  }
  if (!list.children.length) {
    list.innerHTML = '<p class="cal-empty">No events this period.</p>';
  }
}

/* ---- Fetch & show the full event details inside the popup (Calendarize it!) ---- */
function oycLoadEventDetails(ev, el){
  if (!ev.id) { el.textContent = ''; return; }
  fetch('/?rhc_action=get_rendered_item&id=' + encodeURIComponent(ev.id), { credentials: 'same-origin', cache: 'no-store' })
    .then(function(r){ return r.json(); })
    .then(function(d){
      var body = (d && d.DATA && d.DATA.body) || '';
      body = body.replace(/<script[\s\S]*?<\/script>/gi,'').replace(/<style[\s\S]*?<\/style>/gi,'');
      var tmp = document.createElement('div'); tmp.innerHTML = body;
      // Strip admin-only edit links / wp-admin links from the rendered detail.
      Array.prototype.forEach.call(tmp.querySelectorAll('.post-edit-link, a[href*="/wp-admin/"], a[href*="action=edit"]'), function(a){ a.remove(); });
      // Drop a heading that just repeats the event title (popup already shows it).
      Array.prototype.forEach.call(tmp.querySelectorAll('h1,h2,h3,h4'), function(h){ if (h.textContent.trim() === ev.title.trim()) { h.remove(); } });
      var text = (tmp.textContent || '').replace(/[ \t]+/g,' ').replace(/\s*\n\s*/g,'\n').replace(/\n{3,}/g,'\n\n').trim();
      if (text.indexOf(ev.title.trim()) === 0) { text = text.slice(ev.title.trim().length).trim(); }
      el.textContent = text || 'No additional details for this event.';
    })
    .catch(function(){ el.textContent = 'No additional details for this event.'; });
}

function showPopup(ev) {
  const d = new Date(ev.date + 'T12:00:00');
  document.getElementById('cal-popup-cat').textContent   = CAT_LABELS[ev.cat];
  document.getElementById('cal-popup-cat').className     = `cal-popup-cat cal-popup-cat--${ev.cat}`;
  document.getElementById('cal-popup-title').textContent = ev.title;
  var dateStr = d.toLocaleDateString('en-US',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
  if (ev.time) { dateStr += ' · ' + ev.time; }
  document.getElementById('cal-popup-date').textContent  = dateStr;
  var descEl = document.getElementById('cal-popup-desc');
  descEl.hidden = false;
  descEl.style.whiteSpace = 'pre-line';
  descEl.textContent = 'Loading details…';
  oycLoadEventDetails(ev, descEl);
  const popup = document.getElementById('cal-popup');
  // Reveal via the CSS class the stylesheet uses (.is-visible); setting only
  // .hidden left it hidden because of the `#/.cal-popup { display:none }` rule.
  popup.className = 'cal-popup cal-popup--' + ev.cat + ' is-visible';
  popup.hidden = false;
  document.getElementById('cal-popup-close').focus();
}

function closePopup() {
  const popup = document.getElementById('cal-popup');
  popup.classList.remove('is-visible');
  popup.hidden = true;
}

// Export / subscribe: open the chosen provider pointed at the live .ics feed.
(function(){ var go=document.getElementById('cal-export-go'); if(!go) return; go.addEventListener('click', function(){ var v=document.getElementById('cal-export-select').value; var url=(window.OYC_FEED||{})[v]; if(!url) return; if(v==='download'){ window.location.href=url; } else { window.open(url,'_blank','noopener'); } }); })();

function render() {
  renderMonth();
  renderList();
}

// Nav
document.getElementById('cal-prev').addEventListener('click', () => {
  currentMonth--; if (currentMonth < 0) { currentMonth = 11; currentYear--; } render();
});
document.getElementById('cal-next').addEventListener('click', () => {
  currentMonth++; if (currentMonth > 11) { currentMonth = 0; currentYear++; } render();
});

// View toggle
document.querySelectorAll('.cal-view-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.cal-view-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentView = btn.dataset.view;
    // Toggle via the CSS classes the stylesheet actually uses (.is-active /
    // .is-hidden). The previous inline style.display approach was overridden by
    // the `#cal-list-view { display:none }` rule, so the list never showed.
    document.getElementById('cal-month-view').classList.toggle('is-hidden', currentView !== 'month');
    document.getElementById('cal-list-view').classList.toggle('is-active', currentView === 'list');
    document.getElementById('cal-nav').style.display = currentView==='month' ? '' : 'none';
  });
});

// Close popup
document.getElementById('cal-popup-close').addEventListener('click', closePopup);
document.getElementById('cal-popup').addEventListener('click', e => {
  if (e.target === e.currentTarget) closePopup();
});
document.addEventListener('keydown', e => { if (e.key==='Escape') closePopup(); });

// Init — go to current month or May 2026 if past
const now = new Date();
if (now.getFullYear() === 2026) { currentMonth = now.getMonth(); }
else { currentMonth = 4; } // May
loadCalWeather().then(render);
oycLoadEvents().then(function(){ render(); });

// List-view date picker: jump the list to any month/day.
(function () {
  var dp = document.getElementById('cal-date-picker');
  if (!dp) return;
  var pad = function (n) { return String(n).padStart(2, '0'); };
  dp.value = currentYear + '-' + pad(currentMonth + 1) + '-' + pad(Math.min(new Date().getDate(), 28));
  dp.addEventListener('change', function () {
    if (!dp.value) return;
    var parts = dp.value.split('-').map(Number);
    currentYear = parts[0];
    currentMonth = parts[1] - 1;
    render();
    var lv = document.getElementById('cal-list-view');
    if (lv) { lv.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  });
})();

// On phones the month-grid event pills are hidden (cells are too small to fit
// them), which made the calendar look empty. Default to the readable List view
// where every event is visible. Users can still switch back to Month.
if ( window.matchMedia('(max-width: 520px)').matches ) {
  var _listBtn = document.querySelector('.cal-view-btn[data-view="list"]');
  if ( _listBtn ) { _listBtn.click(); }
}
</script>

<?php get_footer(); ?>
