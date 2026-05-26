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
  /* ----- MAY ----- */
  { date:'2026-05-02', title:'Opening Day', cat:'social',
    desc:'The official start of the season. All hands welcome on the dock for Opening Day ceremonies, followed by a club lunch.' },
  { date:'2026-05-06', title:'Board of Trustees Meeting', cat:'meeting',
    desc:'Monthly board meeting. Members welcome to attend.' },
  { date:'2026-05-09', title:'Spring Commissioning Work Day', cat:'social',
    desc:'Members pitch in to get the club ship-shape for the season. Lunch provided.' },
  { date:'2026-05-13', title:'Wednesday Night Race #1', cat:'race',
    desc:'First race of the Wednesday Night Series. PHRF and one-design divisions. Start at 6:30 PM.' },
  { date:'2026-05-16', title:'Pursuit Race #1', cat:'race',
    desc:'Club pursuit race — handicap start. Sign up at the dock house.' },
  { date:'2026-05-20', title:'Wednesday Night Race #2', cat:'race', desc:'Wednesday Night Series. Start 6:30 PM.' },
  { date:'2026-05-23', title:'Spring Fishing Tournament', cat:'fishing',
    desc:'Annual spring fishing tournament. Striped bass and bluefish divisions. Weigh-in at 4:00 PM at the dock house.' },
  { date:'2026-05-24', title:'Spring Fishing Awards Dinner', cat:'social',
    desc:'Awards dinner following the Spring Fishing Tournament. Open to all members.' },
  { date:'2026-05-27', title:'Wednesday Night Race #3', cat:'race', desc:'Wednesday Night Series. Start 6:30 PM.' },
  { date:'2026-05-30', title:'Memorial Day Race', cat:'race',
    desc:'Annual Memorial Day Regatta. Cruising and one-design divisions.' },

  /* ----- JUNE ----- */
  { date:'2026-06-03', title:'Board of Trustees Meeting', cat:'meeting', desc:'Monthly board meeting.' },
  { date:'2026-06-03', title:'Wednesday Night Race #4', cat:'race', desc:'Wednesday Night Series. Start 6:30 PM.' },
  { date:'2026-06-06', title:'Cruise to Oyster Bay', cat:'race',
    desc:'OYC Cruising Fleet departs for Oyster Bay. Contact the Cruising Captain to join.' },
  { date:'2026-06-10', title:'Wednesday Night Race #5', cat:'race', desc:'Wednesday Night Series. Start 6:30 PM.' },
  { date:'2026-06-14', title:'Pursuit Race #2', cat:'race', desc:'Club pursuit race. Sign up at the dock house.' },
  { date:'2026-06-17', title:'Wednesday Night Race #6', cat:'race', desc:'Wednesday Night Series. Start 6:30 PM.' },
  { date:'2026-06-20', title:'Summer Solstice Social', cat:'social',
    desc:'Annual Summer Solstice party on the deck. Live music, dinner, and dancing.' },
  { date:'2026-06-24', title:'Wednesday Night Race #7', cat:'race', desc:'Wednesday Night Series. Start 6:30 PM.' },
  { date:'2026-06-27', title:'Junior Sailing Program Begins', cat:'social',
    desc:'First day of the OYC Junior Sailing Program. Open to members\' children ages 8–17.' },
  { date:'2026-06-28', title:'Frostbite Fleet Reunion', cat:'social',
    desc:'Annual gathering of Mamaroneck Frostbite sailors. Open bar and buffet.' },

  /* ----- JULY ----- */
  { date:'2026-07-01', title:'Board of Trustees Meeting', cat:'meeting', desc:'Monthly board meeting.' },
  { date:'2026-07-01', title:'Wednesday Night Race #8', cat:'race', desc:'Wednesday Night Series. Start 6:30 PM.' },
  { date:'2026-07-04', title:'Independence Day Race & Fireworks', cat:'race',
    desc:'4th of July Regatta followed by viewing the Mamaroneck fireworks from the club deck.' },
  { date:'2026-07-08', title:'Wednesday Night Race #9', cat:'race', desc:'Wednesday Night Series. Start 6:30 PM.' },
  { date:'2026-07-11', title:'Pursuit Race #3', cat:'race', desc:'Club pursuit race. Sign up at the dock house.' },
  { date:'2026-07-15', title:'Wednesday Night Race #10', cat:'race', desc:'Wednesday Night Series. Start 6:30 PM.' },
  { date:'2026-07-18', title:'Mid-Summer Fishing Derby', cat:'fishing',
    desc:'Members\' fishing derby — fluke and bluefish divisions. Weigh-in at 3:00 PM.' },
  { date:'2026-07-19', title:'Mid-Summer Fishing Awards', cat:'social',
    desc:'Awards cookout following the Mid-Summer Fishing Derby.' },
  { date:'2026-07-22', title:'Wednesday Night Race #11', cat:'race', desc:'Wednesday Night Series. Start 6:30 PM.' },
  { date:'2026-07-25', title:'Cruise to Stamford YC', cat:'race',
    desc:'OYC Cruising Fleet cruise to Stamford Yacht Club. Reciprocal hospitality.' },
  { date:'2026-07-29', title:'Wednesday Night Race #12', cat:'race', desc:'Wednesday Night Series. Start 6:30 PM.' },

  /* ----- AUGUST ----- */
  { date:'2026-08-05', title:'Board of Trustees Meeting', cat:'meeting', desc:'Monthly board meeting.' },
  { date:'2026-08-05', title:'Wednesday Night Race #13', cat:'race', desc:'Wednesday Night Series. Start 6:30 PM.' },
  { date:'2026-08-08', title:'Pursuit Race #4', cat:'race', desc:'Club pursuit race. Sign up at the dock house.' },
  { date:'2026-08-12', title:'Wednesday Night Race #14', cat:'race', desc:'Wednesday Night Series. Start 6:30 PM.' },
  { date:'2026-08-15', title:'Governor\'s Cup Regatta', cat:'race',
    desc:'The annual Governor\'s Cup Charity Regatta — PHRF Spinnaker, PHRF Non-Spinnaker, and IRC divisions. Registration and notice of race at yachtscoring.com.' },
  { date:'2026-08-15', title:'Governor\'s Cup Awards Dinner', cat:'social',
    desc:'Post-race awards dinner open to all participants and members.' },
  { date:'2026-08-19', title:'Wednesday Night Race #15', cat:'race', desc:'Wednesday Night Series. Start 6:30 PM.' },
  { date:'2026-08-22', title:'Late Summer Striped Bass Tournament', cat:'fishing',
    desc:'Annual late-summer striped bass tournament. Weigh-in at 4:00 PM.' },
  { date:'2026-08-23', title:'Fishing Tournament Awards Dinner', cat:'social',
    desc:'Awards dinner following the Striped Bass Tournament. Members and guests welcome.' },
  { date:'2026-08-26', title:'Wednesday Night Race #16', cat:'race', desc:'Wednesday Night Series. Start 6:30 PM.' },
  { date:'2026-08-29', title:'Pursuit Race #5 / Season Finale', cat:'race',
    desc:'Season finale pursuit race. Trophies presented following the race.' },

  /* ----- SEPTEMBER ----- */
  { date:'2026-09-02', title:'Board of Trustees Meeting', cat:'meeting', desc:'Monthly board meeting.' },
  { date:'2026-09-05', title:'Labor Day Regatta', cat:'race',
    desc:'Annual Labor Day Regatta. One-design and cruising divisions.' },
  { date:'2026-09-09', title:'Wednesday Night Race #17', cat:'race', desc:'Wednesday Night Series. Start 6:15 PM.' },
  { date:'2026-09-12', title:'Fall Cruise to Larchmont YC', cat:'race',
    desc:'OYC Cruising Fleet fall cruise to Larchmont Yacht Club. Reciprocal hospitality.' },
  { date:'2026-09-16', title:'Wednesday Night Race #18 (Final)', cat:'race',
    desc:'Final Wednesday Night Race of the season. Trophy presentation at the club following the race.' },
  { date:'2026-09-19', title:'Fall Fishing Derby', cat:'fishing',
    desc:'Fall fishing derby — stripers, bluefish, and blackfish divisions. Weigh-in at 3:00 PM.' },
  { date:'2026-09-26', title:'Closing Day', cat:'social',
    desc:'Official end of the sailing season. Closing Day ceremony, final prizes, and farewell dinner on the deck.' },

  /* ----- OCTOBER ----- */
  { date:'2026-10-07', title:'Board of Trustees Meeting', cat:'meeting', desc:'Monthly board meeting.' },
  { date:'2026-10-10', title:'Frostbite Series Begins', cat:'race',
    desc:'The Mamaroneck Frostbiters Association kicks off its fall/winter racing series. All are welcome.' },
  { date:'2026-10-17', title:'Haul-out Work Party', cat:'social',
    desc:'Members help haul and winterize boats. Lunch and beer provided for volunteers.' },
  { date:'2026-10-24', title:'Fall Dinner & Prize Giving', cat:'social',
    desc:'Annual fall awards dinner. Season trophies presented. Formal dress encouraged.' },
  { date:'2026-10-31', title:'Halloween Party', cat:'social',
    desc:'Annual Halloween party at the club. Costume contest with prizes.' },

  /* ----- NOVEMBER ----- */
  { date:'2026-11-04', title:'Board of Trustees Meeting', cat:'meeting', desc:'Monthly board meeting.' },
  { date:'2026-11-07', title:'Frostbite Race', cat:'race', desc:'Mamaroneck Frostbite Series.' },
  { date:'2026-11-14', title:'Frostbite Race', cat:'race', desc:'Mamaroneck Frostbite Series.' },
  { date:'2026-11-21', title:'Frostbite Race', cat:'race', desc:'Mamaroneck Frostbite Series.' },
  { date:'2026-11-28', title:'Thanksgiving Frostbite Race', cat:'race',
    desc:'Traditional Thanksgiving Day Frostbite Race. Turkey provided at the dock house!' },

  /* ----- DECEMBER ----- */
  { date:'2026-12-02', title:'Board of Trustees Meeting', cat:'meeting', desc:'Monthly board meeting.' },
  { date:'2026-12-05', title:'Frostbite Race', cat:'race', desc:'Mamaroneck Frostbite Series.' },
  { date:'2026-12-12', title:'Holiday Party', cat:'social',
    desc:'Annual OYC Holiday Party. Dinner, dancing, and seasonal cheer. Members and guests welcome.' },
  { date:'2026-12-19', title:'Frostbite Race', cat:'race', desc:'Mamaroneck Frostbite Series.' },
  { date:'2026-12-26', title:'Frostbite Race', cat:'race', desc:'Mamaroneck Frostbite Series.' },

  /* ----- APRIL (pre-season) ----- */
  { date:'2026-04-01', title:'Board of Trustees Meeting', cat:'meeting', desc:'Monthly board meeting.' },
  { date:'2026-04-11', title:'Spring Work Day', cat:'social',
    desc:'Pre-season volunteer work party. Help get the club grounds, docks, and dock house ready for the season.' },
  { date:'2026-04-18', title:'Spring Launch & Commissioning', cat:'social',
    desc:'First launch of the season. Work parties throughout the day.' },
  { date:'2026-04-25', title:'Membership Orientation', cat:'meeting',
    desc:'Orientation for new members. Light lunch provided. Meet the officers and learn about club activities.' },
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
