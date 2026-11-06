/* PPI Estates — navegação móvel acessível (sem JavaScript o menu fica sempre visível). */
(function () {
	'use strict';
	var btn = document.querySelector('[data-nav-toggle]');
	var panel = document.querySelector('[data-nav-panel]');
	if (!btn || !panel) { return; }
	var mq = window.matchMedia('(max-width: 960px)');
	function set(open) {
		btn.setAttribute('aria-expanded', String(open));
		panel.classList.toggle('is-open', open);
		document.body.classList.toggle('nav-open', open && mq.matches);
	}
	btn.addEventListener('click', function () {
		var open = btn.getAttribute('aria-expanded') !== 'true';
		set(open);
		if (open) { var first = panel.querySelector('a'); if (first) { first.focus(); } }
	});
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && btn.getAttribute('aria-expanded') === 'true') { set(false); btn.focus(); }
	});
	// Manter o foco dentro do menu aberto em ecrã pequeno.
	panel.addEventListener('keydown', function (e) {
		if (e.key !== 'Tab' || !mq.matches || btn.getAttribute('aria-expanded') !== 'true') { return; }
		var f = panel.querySelectorAll('a, button'), last = f[f.length - 1];
		if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); btn.focus(); }
	});
	btn.addEventListener('keydown', function (e) {
		if (e.key === 'Tab' && !e.shiftKey && btn.getAttribute('aria-expanded') === 'true' && mq.matches) {
			var a = panel.querySelector('a'); if (a) { e.preventDefault(); a.focus(); }
		}
	});
	mq.addEventListener('change', function () { set(false); });
})();
