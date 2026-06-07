(function () {
	// ── Homepage: solidify the transparent header once the user scrolls ──
	var header = document.querySelector('.site-header');
	// Fully-immersive pages keep a transparent menu the whole way down (they
	// have a fixed dark photo behind everything); only solidify on the other
	// hero pages that have white content below the hero.
	if (header && document.body.classList.contains('has-hero-header') && !document.body.classList.contains('oyc-immersive')) {
		var onHeaderScroll = function () {
			header.classList.toggle('is-scrolled', window.scrollY > 60);
		};
		onHeaderScroll();
		window.addEventListener('scroll', onHeaderScroll, { passive: true });
	}

	// ── Mobile nav toggle ───────────────────────────────────────────
	var toggle = document.querySelector('.nav-toggle');
	var menu   = document.getElementById('nav-menu');
	if (toggle && menu) {
		toggle.addEventListener('click', function () {
			var open = menu.classList.toggle('open');
			toggle.setAttribute('aria-expanded', String(open));
		});
		var links = menu.querySelectorAll('a');
		for (var i = 0; i < links.length; i++) {
			links[i].addEventListener('click', function () {
				menu.classList.remove('open');
				toggle.setAttribute('aria-expanded', 'false');
			});
		}
	}

	// ── In-page anchor scrolling (e.g. the "Visitors" → #visitors link) ──
	// The site header is position:sticky and html uses scroll-behavior:smooth,
	// so the browser's native fragment scroll lands behind the header (or, on a
	// fresh load with a hash, doesn't fire at all). Handle it explicitly with a
	// header/admin-bar offset.

	function headerOffset() {
		var h = 0;
		var header = document.querySelector('.site-header');
		if (header && getComputedStyle(header).position === 'sticky') {
			h += header.getBoundingClientRect().height;
		}
		var bar = document.getElementById('wpadminbar');
		if (bar && getComputedStyle(bar).position === 'fixed') {
			h += bar.getBoundingClientRect().height;
		}
		return h + 12; // a little breathing room
	}

	function scrollToEl(el) {
		var y = el.getBoundingClientRect().top + window.pageYOffset - headerOffset();
		window.scrollTo({ top: y < 0 ? 0 : y, behavior: 'smooth' });
	}

	// Click on a link that targets an anchor on THIS page → smooth-scroll to it.
	document.addEventListener('click', function (e) {
		var a = e.target && e.target.closest ? e.target.closest('a[href*="#"]') : null;
		if (!a) return;
		var url;
		try { url = new URL(a.href, window.location.href); } catch (err) { return; }
		if (!url.hash || url.hash === '#') return;
		// Only intercept jumps that stay on the current page.
		if (url.host !== window.location.host || url.pathname !== window.location.pathname) return;
		// Links that change the query string (e.g. ?inquiry=membership#contact) must
		// navigate normally so the new query takes effect — don't hijack them.
		if (url.search !== window.location.search) return;
		var target = document.getElementById(decodeURIComponent(url.hash.slice(1)));
		if (!target) return;
		e.preventDefault();
		scrollToEl(target);
		if (window.history && history.pushState) {
			history.pushState(null, '', url.hash);
		} else {
			window.location.hash = url.hash;
		}
	});

	// Landing on the page with a hash (e.g. arriving from another page) →
	// scroll to it once layout, the sticky header and the admin bar have settled.
	function scrollToHashOnLoad() {
		var hash = window.location.hash;
		if (!hash || hash === '#') return;
		var target = document.getElementById(decodeURIComponent(hash.slice(1)));
		if (!target) return;
		setTimeout(function () { scrollToEl(target); }, 80);
	}
	if (document.readyState === 'complete') {
		scrollToHashOnLoad();
	} else {
		window.addEventListener('load', scrollToHashOnLoad);
	}

	// ── Contact form: show a thank-you popup on successful send ──────────
	function oycShowMsgPopup() {
		var pop = document.getElementById('oyc-msg-popup');
		if (!pop) {
			pop = document.createElement('div');
			pop.id = 'oyc-msg-popup';
			pop.className = 'oyc-msg-popup';
			pop.innerHTML =
				'<div class="oyc-msg-popup__card" role="dialog" aria-modal="true" aria-labelledby="oyc-msg-popup-title">' +
					'<div class="oyc-msg-popup__icon" aria-hidden="true">' +
						'<svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>' +
					'</div>' +
					'<h3 id="oyc-msg-popup-title">Thank you for your message.</h3>' +
					'<p>It has been sent.</p>' +
					'<button type="button" class="btn btn-primary oyc-msg-popup__close">Close</button>' +
				'</div>';
			document.body.appendChild(pop);
			var close = function () { pop.classList.remove('is-open'); document.body.classList.remove('oyc-msg-lock'); };
			pop.addEventListener('click', function (e) { if (e.target === pop) close(); });
			pop.querySelector('.oyc-msg-popup__close').addEventListener('click', close);
			document.addEventListener('keydown', function (e) {
				if ((e.key === 'Escape' || e.keyCode === 27) && pop.classList.contains('is-open')) close();
			});
		}
		pop.classList.add('is-open');
		document.body.classList.add('oyc-msg-lock');
		var btn = pop.querySelector('.oyc-msg-popup__close');
		if (btn) btn.focus();
	}
	document.addEventListener('wpcf7mailsent', function (e) {
		var f = (e.target && e.target.tagName === 'FORM') ? e.target
			: (e.target && e.target.querySelector ? e.target.querySelector('form') : null);
		// Only the contact form (which has the inquiry-type dropdown) — the
		// membership application form redirects to its own thank-you page.
		if (!f || !f.querySelector('[name="inquiry-type"]')) return;
		oycShowMsgPopup();
	}, false);

	// ── Accessibility: ensure every form control has an accessible name ──
	// Contact Form 7 fields use placeholders (and radios with adjacent text)
	// instead of <label>s. Derive an aria-label so they pass WCAG "name" reqs.
	function oycLabelControls() {
		var ctrls = document.querySelectorAll(
			'input:not([type=hidden]):not([type=submit]):not([type=button]):not([type=image]), select, textarea'
		);
		Array.prototype.forEach.call(ctrls, function (c) {
			if (c.getAttribute('aria-label') || c.getAttribute('aria-labelledby') || c.title) { return; }
			if (c.labels && c.labels.length) { return; }
			var name = c.getAttribute('placeholder') || '';
			if (!name && (c.type === 'radio' || c.type === 'checkbox')) {
				var item = c.closest('.wpcf7-list-item') || c.closest('label') || c.parentElement;
				if (item) { name = (item.textContent || '').replace(/\s+/g, ' ').trim(); }
			}
			if (!name && c.name) {
				name = c.name.replace(/[\[\]_-]+/g, ' ').replace(/\d+/g, '').trim();
			}
			if (name) { c.setAttribute('aria-label', name); }
		});
	}
	if (document.readyState !== 'loading') { oycLabelControls(); }
	else { document.addEventListener('DOMContentLoaded', oycLabelControls); }
	// Re-run after CF7 re-renders the form (validation / submit).
	document.addEventListener('wpcf7invalid', oycLabelControls, false);
	document.addEventListener('wpcf7submit', oycLabelControls, false);
	document.addEventListener('wpcf7mailsent', oycLabelControls, false);
})();
