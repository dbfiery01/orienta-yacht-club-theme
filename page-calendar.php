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
				<a class="cal-popup-link" id="cal-popup-link" target="_blank" rel="noopener" hidden><?php esc_html_e( 'View full event details →', 'orienta-yacht-club' ); ?></a>
				<div class="cal-popup-actions" id="cal-popup-actions"></div>
			</div>
		</div>

	</div>
</section>

<script>
/* ===== OYC Calendar — events loaded live from Calendarize it! ========= */
let EVENTS = [];
function oycInferCat(t){ t=(t||'').toLowerCase(); if(/\brace|cup|wsl|regatta|raft up\b/.test(t))return 'race'; if(/meeting/.test(t))return 'meeting'; if(/fish/.test(t))return 'fishing'; return 'social'; }
function oycLoadEvents(){
  var s=Math.floor(new Date(currentYear-1,0,1).getTime()/1000);
  var e=Math.floor(new Date(currentYear+2,0,1).getTime()/1000);
  return fetch('/?rhc_action=get_calendar_events&post_type[]=events&start='+s+'&end='+e, {credentials:'same-origin'})
    .then(function(r){ return r.json(); })
    .then(function(d){
      function _fmtT(dt){ var m=/\d{4}-\d{2}-\d{2} (\d{2}):(\d{2})/.exec(dt||''); if(!m) return ''; var h=+m[1], ap=h<12?'AM':'PM', h12=h%12||12; return h12+':'+m[2]+' '+ap; }
      EVENTS = (((d&&d.EVENTS)||[]).map(function(ev){
        var time='';
        if(!ev.allDay){ var st=_fmtT(ev.start), en=_fmtT(ev.end); time = st ? ((en && en!==st) ? (st+' – '+en) : st) : ''; }
        var desc=(ev.description||'').replace(/<[^>]+>/g,' ').replace(/&nbsp;/g,' ').replace(/&amp;|&#0?38;/g,'&').replace(/\s+/g,' ').trim();
        return { date: ev.fc_start, title: ev.title, cat: oycInferCat(ev.title), time: time, desc: desc, url: ev.url||'' };
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

/* ---- Add-to-calendar export helpers (Google / Apple / Outlook via .ics) ---- */
function _pad(n){ return String(n).padStart(2,'0'); }
function _ymd(s){ return s.replace(/-/g,''); }                                  // 2026-06-07 -> 20260607
function _nextYmd(s){ var d=new Date(s+'T00:00:00'); d.setDate(d.getDate()+1); return d.getFullYear()+_pad(d.getMonth()+1)+_pad(d.getDate()); }
function _icsEsc(s){ return String(s||'').replace(/\\/g,'\\\\').replace(/;/g,'\\;').replace(/,/g,'\\,').replace(/\r?\n/g,'\\n'); }
function _vevent(ev){ return ['BEGIN:VEVENT','UID:'+_ymd(ev.date)+'-'+ev.title.replace(/[^A-Za-z0-9]/g,'')+'@orientayachtclub.com','DTSTART;VALUE=DATE:'+_ymd(ev.date),'DTEND;VALUE=DATE:'+_nextYmd(ev.date),'SUMMARY:'+_icsEsc(ev.title),'DESCRIPTION:'+_icsEsc(ev.desc),'END:VEVENT']; }
function buildICS(evs){ return ['BEGIN:VCALENDAR','VERSION:2.0','PRODID:-//Orienta Yacht Club//Calendar//EN','CALSCALE:GREGORIAN'].concat([].concat.apply([], evs.map(_vevent)), ['END:VCALENDAR']).join('\r\n'); }
function googleCalUrl(ev){ var p=new URLSearchParams({action:'TEMPLATE',text:ev.title,dates:_ymd(ev.date)+'/'+_nextYmd(ev.date)}); if(ev.desc){ p.set('details',ev.desc); } return 'https://calendar.google.com/calendar/render?'+p.toString(); }
function downloadICS(name,text){ var b=new Blob([text],{type:'text/calendar;charset=utf-8'}); var u=URL.createObjectURL(b); var a=document.createElement('a'); a.href=u; a.download=name; document.body.appendChild(a); a.click(); a.remove(); setTimeout(function(){ URL.revokeObjectURL(u); },1500); }
function renderPopupActions(ev){
  var box=document.getElementById('cal-popup-actions'); if(!box){ return; } box.innerHTML='';
  var label=document.createElement('span'); label.className='cal-addcal-label'; label.textContent='Add to calendar:'; box.appendChild(label);
  var g=document.createElement('a'); g.href=googleCalUrl(ev); g.target='_blank'; g.rel='noopener'; g.className='cal-addcal-btn'; g.textContent='Google'; box.appendChild(g);
  var ics=document.createElement('button'); ics.type='button'; ics.className='cal-addcal-btn'; ics.textContent='Apple / Outlook'; ics.addEventListener('click', function(){ downloadICS(ev.title.replace(/[^A-Za-z0-9]+/g,'-')+'.ics', buildICS([ev])); }); box.appendChild(ics);
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
  descEl.textContent = ev.desc || '';
  descEl.hidden = ! ev.desc;
  var linkEl = document.getElementById('cal-popup-link');
  if (ev.url) { linkEl.href = ev.url; linkEl.hidden = false; } else { linkEl.hidden = true; }
  renderPopupActions(ev);
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
