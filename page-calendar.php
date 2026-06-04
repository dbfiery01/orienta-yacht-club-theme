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
		<div id="cal-list-view" style="display:none;">
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

<script>
/* ===== OYC Calendar — 2026 Events ===================================== */
const EVENTS = [
  /* Real club events imported from orientayachtclub.com (Calendarize it!) */
  { date:"2025-02-15", title:"Mooring Permit Deadline", cat:"social", desc:"" },
  { date:"2025-03-15", title:"St. Patrick\u2019s Day Party", cat:"social", desc:"" },
  { date:"2025-03-30", title:"CPR Training", cat:"social", desc:"Starts at 10:00 AM" },
  { date:"2025-04-08", title:"Early Launch", cat:"social", desc:"Starts at 8:00 AM" },
  { date:"2025-04-08", title:"General Meeting", cat:"meeting", desc:"Starts at 8:00 PM" },
  { date:"2025-04-15", title:"Launch", cat:"social", desc:"" },
  { date:"2025-04-24", title:"Launch", cat:"social", desc:"" },
  { date:"2025-04-26", title:"Launch", cat:"social", desc:"" },
  { date:"2025-05-03", title:"Spring Work Party", cat:"social", desc:"" },
  { date:"2025-05-04", title:"Clubhouse Rented (Frost biters)", cat:"social", desc:"" },
  { date:"2025-05-10", title:"Commissioning Party", cat:"social", desc:"" },
  { date:"2025-06-08", title:"Race #1", cat:"race", desc:"" },
  { date:"2025-06-10", title:"General Meeting", cat:"meeting", desc:"Starts at 8:00 PM" },
  { date:"2025-06-14", title:"Clubhouse Rented", cat:"social", desc:"" },
  { date:"2025-06-28", title:"Race #2", cat:"race", desc:"" },
  { date:"2025-07-04", title:"4th July Party", cat:"social", desc:"" },
  { date:"2025-07-20", title:"Clubhouse rented.", cat:"social", desc:"" },
  { date:"2025-07-25", title:"Christmas in July Social Event", cat:"social", desc:"Starts at 6:30 PM" },
  { date:"2025-08-15", title:"Bo & John Stoffel Social Event", cat:"social", desc:"" },
  { date:"2025-08-16", title:"Governor\u2019s Cup (City Island YC)", cat:"race", desc:"" },
  { date:"2025-08-23", title:"Raft Up & Race Oyster Bay", cat:"race", desc:"" },
  { date:"2025-08-30", title:"Clubhouse Rented \u2013 Charla", cat:"social", desc:"" },
  { date:"2025-09-14", title:"Race #3", cat:"race", desc:"" },
  { date:"2025-09-20", title:"WSL RACE", cat:"race", desc:"" },
  { date:"2025-09-26", title:"Cruise the Coast of Maine", cat:"social", desc:"Starts at 6:30 PM" },
  { date:"2025-10-04", title:"Race #4", cat:"race", desc:"" },
  { date:"2025-10-04", title:"SPECIAL MEETING", cat:"meeting", desc:"" },
  { date:"2025-10-11", title:"Work Party", cat:"social", desc:"Starts at 8:00 AM" },
  { date:"2025-10-14", title:"General Meeting", cat:"meeting", desc:"Starts at 8:00 PM" },
  { date:"2025-10-16", title:"Haul", cat:"social", desc:"Starts at 7:30 AM" },
  { date:"2025-10-20", title:"Haul", cat:"social", desc:"Starts at 7:30 AM" },
  { date:"2025-10-21", title:"Haul", cat:"social", desc:"Starts at 7:30 AM" },
  { date:"2025-10-25", title:"Haul", cat:"social", desc:"Starts at 7:30 AM" },
  { date:"2025-11-08", title:"Commissioning Party", cat:"social", desc:"" },
  { date:"2025-12-17", title:"Clubhouse Rented \u2013 Young", cat:"social", desc:"" },
  { date:"2025-12-25", title:"Clubhouse Rented \u2013 Renda", cat:"social", desc:"" },
  { date:"2025-12-27", title:"Clubhouse Rented \u2013 Peron", cat:"social", desc:"" },
  { date:"2026-01-11", title:"Pancake Breakfast", cat:"social", desc:"Starts at 10:00 AM" },
  { date:"2026-01-17", title:"Clubhouse Rented (Viscogliosi) 10am-4pm", cat:"social", desc:"" },
  { date:"2026-02-07", title:"Clubhouse Rented (Reville)", cat:"social", desc:"" },
  { date:"2026-02-28", title:"Clubhouse Rented 10am-2pm", cat:"social", desc:"" },
  { date:"2026-03-14", title:"Clubhouse Rented (Viscogliosi)", cat:"social", desc:"" },
  { date:"2026-04-11", title:"Clubhouse Occupied 8am-3pm (Winter Storage Crew)", cat:"race", desc:"" },
  { date:"2026-04-14", title:"General Meeting", cat:"meeting", desc:"Starts at 8:00 PM" },
  { date:"2026-04-16", title:"1st Launch", cat:"social", desc:"" },
  { date:"2026-04-24", title:"Social Event 5:30pm", cat:"social", desc:"Starts at 5:30 PM" },
  { date:"2026-04-29", title:"2nd Launch", cat:"social", desc:"" },
  { date:"2026-05-03", title:"Clubhouse rented \u2013 MFA Dinner", cat:"social", desc:"Starts at 5:00 PM" },
  { date:"2026-05-06", title:"3rd Launch", cat:"social", desc:"" },
  { date:"2026-05-16", title:"4th Launch", cat:"social", desc:"" },
  { date:"2026-05-17", title:"Work Party", cat:"social", desc:"" },
  { date:"2026-05-30", title:"Commissioning Party", cat:"social", desc:"" },
  { date:"2026-06-07", title:"Race #1", cat:"race", desc:"" },
  { date:"2026-06-13", title:"Clubhouse Rented \u2013 Dwyer", cat:"social", desc:"" },
  { date:"2026-06-27", title:"Race #2", cat:"race", desc:"" },
  { date:"2026-07-18", title:"Clubhouse Rented (Sganga)", cat:"social", desc:"" },
  { date:"2026-08-14", title:"Summer Social Party (Stoffel)", cat:"social", desc:"" },
  { date:"2026-08-16", title:"Govenor\u2019s Cup", cat:"race", desc:"" },
  { date:"2026-09-12", title:"WSL CUP", cat:"race", desc:"" },
  { date:"2026-09-27", title:"Race #3", cat:"race", desc:"" },
  { date:"2026-10-10", title:"Race #4", cat:"race", desc:"" },
];

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

function showPopup(ev) {
  const d = new Date(ev.date + 'T12:00:00');
  document.getElementById('cal-popup-cat').textContent   = CAT_LABELS[ev.cat];
  document.getElementById('cal-popup-cat').className     = `cal-popup-cat cal-popup-cat--${ev.cat}`;
  document.getElementById('cal-popup-title').textContent = ev.title;
  document.getElementById('cal-popup-date').textContent  =
    d.toLocaleDateString('en-US',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
  document.getElementById('cal-popup-desc').textContent  = ev.desc || '';
  const popup = document.getElementById('cal-popup');
  popup.hidden = false;
  document.getElementById('cal-popup-close').focus();
}

function closePopup() {
  document.getElementById('cal-popup').hidden = true;
}

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
    document.getElementById('cal-month-view').style.display = currentView==='month' ? '' : 'none';
    document.getElementById('cal-list-view').style.display  = currentView==='list'  ? '' : 'none';
    document.getElementById('cal-nav').style.display        = currentView==='month' ? '' : 'none';
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
render();
</script>

<?php get_footer(); ?>
