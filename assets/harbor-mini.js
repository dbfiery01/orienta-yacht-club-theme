/**
 * [oyc_harbor_mini] live widget — time, current tide, sunrise/sunset for
 * Mamaroneck Harbor. Data sources match the full /weather/ board:
 *   - NOAA CO-OPS tide predictions (station 8518091), interpolated to the minute
 *   - Sunrise/sunset computed locally (no network)
 * Only loaded on pages that contain the shortcode (see inc/harbor-mini.php).
 */
(function () {
	"use strict";
	var root = document.querySelector('.oyc-harbor-mini');
	if (!root) { return; }
	function pad(n) { return String(n).padStart(2, '0'); }
	function set(field, txt) {
		var el = root.querySelector('[data-ohm="' + field + '"]');
		if (el) { el.textContent = txt; }
	}

	// ---- clock ----
	function tick() {
		var d = new Date(), h = d.getHours(), ap = h >= 12 ? 'PM' : 'AM';
		h = h % 12 || 12;
		set('time', h + ':' + pad(d.getMinutes()) + ' ' + ap);
	}
	tick();
	setInterval(tick, 30000);

	// ---- current tide (NOAA CO-OPS, Mamaroneck 8518091) ----
	// Predictions are deterministic, so we cache a 14-day window in localStorage
	// and fall back to it when NOAA is down — the tide keeps showing through an
	// outage, and it renders instantly from cache on load.
	var COOPS = 'https://api.tidesandcurrents.noaa.gov/api/prod/datagetter';
	var TIDE_KEY = 'oyc_hm_tide_8518091';
	var tideSeries = [];
	function ymd(d) { return '' + d.getFullYear() + pad(d.getMonth() + 1) + pad(d.getDate()); }
	function ymdhm(d) { return ymd(d) + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes()); }
	function parseTide(preds) { return preds.map(function (x) { return { t: new Date(x.t.replace(' ', 'T')), v: parseFloat(x.v) }; }); }
	function saveTideCache(preds) {
		try { localStorage.setItem(TIDE_KEY, JSON.stringify({ at: new Date().getTime(), preds: preds })); } catch (e) { }
	}
	function readTideCache() {
		try {
			var o = JSON.parse(localStorage.getItem(TIDE_KEY));
			if (!o || !o.preds || !o.preds.length) { return null; }
			if (new Date().getTime() - o.at > 12 * 86400000) { return null; } // may no longer cover now
			return o.preds;
		} catch (e) { return null; }
	}
	function renderCurrentTide() {
		if (tideSeries.length < 2) { return; }
		var now = new Date(), r = null, i;
		for (i = 1; i < tideSeries.length; i++) {
			if (tideSeries[i].t >= now) {
				var a = tideSeries[i - 1], b = tideSeries[i], f = (now - a.t) / (b.t - a.t);
				r = { v: a.v + (b.v - a.v) * f, slope: b.v - a.v };
				break;
			}
		}
		if (!r) { return; }
		set('tide', r.v.toFixed(1) + ' ft ' + (r.slope >= 0 ? '▲' : '▼'));
	}
	function fetchTide(retries) {
		var start = new Date();
		start.setHours(0, 0, 0, 0);
		var p = {
			product: 'predictions', datum: 'MLLW', interval: '30', units: 'english',
			time_zone: 'lst_ldt', format: 'json', station: '8518091',
			application: 'OYC-harbor-mini', begin_date: ymdhm(start), range: '336' // 14 days
		};
		var qs = Object.keys(p).map(function (k) { return k + '=' + encodeURIComponent(p[k]); }).join('&');
		fetch(COOPS + '?' + qs).then(function (r) {
			if (!r.ok) { throw new Error('coops ' + r.status); }
			return r.json();
		}).then(function (j) {
			if (!j || !j.predictions || j.predictions.length < 2) { throw new Error('no predictions'); }
			saveTideCache(j.predictions);
			tideSeries = parseTide(j.predictions);
			renderCurrentTide();
		}).catch(function () {
			// NOAA down: fall back to cache, then to the bundled hi/lo predictions
			// shipped with the theme (cosine-interpolated); else retry a few times.
			if (!tideSeries.length) {
				var c = readTideCache();
				if (c) { tideSeries = parseTide(c); renderCurrentTide(); return; }
				bundledTide();
			}
			if (retries > 0) { setTimeout(function () { fetchTide(retries - 1); }, 20000); }
		});
	}
	function bundledTide() {
		var url = (window.OYC_HM && window.OYC_HM.bundled) || null;
		if (!url) { return; }
		fetch(url).then(function (r) {
			if (!r.ok) { throw new Error('no bundle'); }
			return r.json();
		}).then(function (j) {
			if (!j || !j.events || j.events.length < 2) { return; }
			var ev = parseTide(j.events), now = new Date(), i;
			for (i = 1; i < ev.length; i++) {
				if (ev[i].t >= now) {
					if (ev[i - 1].t > now) { return; }
					var a = ev[i - 1], b = ev[i];
					var f = (now - a.t) / (b.t - a.t);
					var v = a.v + (b.v - a.v) * (1 - Math.cos(Math.PI * f)) / 2;
					if (!tideSeries.length) { set('tide', v.toFixed(1) + ' ft ' + (b.v - a.v >= 0 ? '▲' : '▼')); }
					return;
				}
			}
		}).catch(function () { });
	}
	var _cached = readTideCache();
	if (_cached) { tideSeries = parseTide(_cached); renderCurrentTide(); } // instant paint from cache
	fetchTide(3);
	setInterval(renderCurrentTide, 60000);          // re-interpolate current level each minute
	setInterval(function () { fetchTide(3); }, 6 * 3600 * 1000); // refresh window + cache every 6h

	// ---- sunrise / sunset (computed, no network) ----
	var rad = Math.PI / 180, dayMs = 86400000, J1970 = 2440588, J2000 = 2451545, e = rad * 23.4397;
	function toJulian(d) { return d.valueOf() / dayMs - 0.5 + J1970; }
	function fromJulian(j) { return new Date((j + 0.5 - J1970) * dayMs); }
	function toDays(d) { return toJulian(d) - J2000; }
	function dec(l, b) { return Math.asin(Math.sin(b) * Math.cos(e) + Math.cos(b) * Math.sin(e) * Math.sin(l)); }
	function sma(d) { return rad * (357.5291 + 0.98560028 * d); }
	function ecl(M) { return M + rad * (1.9148 * Math.sin(M) + 0.02 * Math.sin(2 * M) + 0.0003 * Math.sin(3 * M)) + rad * 102.9372 + Math.PI; }
	function sunTimes(date, lat, lng) {
		var lw = rad * -lng, phi = rad * lat, d = toDays(date),
			n = Math.round(d - 0.0009 - lw / (2 * Math.PI)),
			ds = 0.0009 + lw / (2 * Math.PI) + n, M = sma(ds), L = ecl(M), de = dec(L, 0),
			Jnoon = J2000 + ds + 0.0053 * Math.sin(M) - 0.0069 * Math.sin(2 * L),
			h0 = -0.833 * rad,
			w = Math.acos((Math.sin(h0) - Math.sin(phi) * Math.sin(de)) / (Math.cos(phi) * Math.cos(de))),
			a = 0.0009 + (w + lw) / (2 * Math.PI) + n,
			Jset = J2000 + a + 0.0053 * Math.sin(M) - 0.0069 * Math.sin(2 * L),
			Jrise = Jnoon - (Jset - Jnoon);
		return { sunrise: fromJulian(Jrise), sunset: fromJulian(Jset) };
	}
	function hm(d) { var h = d.getHours(), ap = h >= 12 ? 'PM' : 'AM'; h = h % 12 || 12; return h + ':' + pad(d.getMinutes()) + ' ' + ap.toLowerCase(); }
	var st = sunTimes(new Date(), 40.939, -73.734);
	set('sunrise', hm(st.sunrise));
	set('sunset', hm(st.sunset));
})();
