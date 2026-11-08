/**
 * Membership card + Verify page.
 *  - Draws the card's QR code (assets/qrcode.js, MIT, Kazuhiko Arase) as SVG.
 *  - Runs a live clock so staff can tell the card / result is on screen now,
 *    not a screenshot.
 */
(function () {
	'use strict';

	document.querySelectorAll('[data-oyc-qr]').forEach(function (el) {
		if (typeof window.qrcode !== 'function') {
			return;
		}
		try {
			var qr = window.qrcode(0, 'M');
			qr.addData(el.getAttribute('data-oyc-qr'));
			qr.make();
			el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
		} catch (e) {
			el.textContent = '';
		}
	});

	// Tap / click / Enter flips the card between front and back (QR).
	document.querySelectorAll('[data-oyc-flip]').forEach(function (wrap) {
		var btn = wrap.querySelector('button');
		if (!btn) {
			return;
		}
		btn.addEventListener('click', function () {
			var on = wrap.classList.toggle('is-flipped');
			btn.setAttribute('aria-pressed', on ? 'true' : 'false');
		});
		if (/[?&]side=back\b/.test(location.search)) {
			wrap.classList.add('is-flipped');
			btn.setAttribute('aria-pressed', 'true');
		}
	});

	var clocks = document.querySelectorAll('[data-oyc-clock]');
	if (!clocks.length) {
		return;
	}
	var fmt = function (d) {
		try {
			return d.toLocaleString(undefined, {
				weekday: 'short', month: 'short', day: 'numeric',
				hour: 'numeric', minute: '2-digit', second: '2-digit'
			});
		} catch (e) {
			return d.toString();
		}
	};
	var tick = function () {
		var now = new Date();
		var txt = fmt(now);
		clocks.forEach(function (c) {
			c.textContent = txt;
			c.setAttribute('datetime', now.toISOString());
		});
	};
	tick();
	setInterval(tick, 1000);
})();
