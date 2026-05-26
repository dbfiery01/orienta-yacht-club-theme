(function () {
	var toggle = document.querySelector('.nav-toggle');
	var menu   = document.getElementById('nav-menu');
	if (!toggle || !menu) return;

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
})();
