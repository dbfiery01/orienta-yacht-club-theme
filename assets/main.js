(function () {
	// ── Homepage: solidify the transparent header once the user scrolls ──
	var header = document.querySelector('.site-header');
	if (header && document.body.classList.contains('home')) {
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
})();
