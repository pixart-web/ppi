/*!
 * PPI Core — melhoramento progressivo do site público.
 * Sem este ficheiro: pesquisa, paginação, ordenação, galeria (links para as imagens) e formulários funcionam.
 * Com ele: filtros sem recarregar, favoritos/comparação, galeria em ecrã inteiro, envio de formulários sem sair da página,
 * consentimento de cookies e conteúdos externos.
 */
(function () {
	'use strict';
	var cfg = window.ppiCfg || {};
	var t = cfg.i18n || {};
	var doc = document;
	var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

	function $(s, c) { return (c || doc).querySelector(s); }
	function $$(s, c) { return Array.prototype.slice.call((c || doc).querySelectorAll(s)); }
	function store(k, v) {
		try {
			if (v === undefined) { return JSON.parse(localStorage.getItem(k) || '[]'); }
			localStorage.setItem(k, JSON.stringify(v));
		} catch (e) { return v === undefined ? [] : null; }
	}
	function announce(msg) {
		var r = $('#ppi-live');
		if (!r) { r = doc.createElement('div'); r.id = 'ppi-live'; r.className = 'screen-reader-text'; r.setAttribute('role', 'status'); r.setAttribute('aria-live', 'polite'); doc.body.appendChild(r); }
		r.textContent = ''; setTimeout(function () { r.textContent = msg; }, 30);
	}

	doc.documentElement.classList.add('ppi-js');
	function reveal(root) { $$('[data-ppi-js][hidden]', root).forEach(function (n) { n.hidden = false; }); }
	reveal();

	/* ---------------------------------------------------------------- Consentimento */
	var consent = (function () {
		var m = doc.cookie.match(/(?:^|; )ppi_consent=([^;]+)/);
		try { return m ? JSON.parse(decodeURIComponent(m[1])) : null; } catch (e) { return null; }
	})();
	function saveConsent(c) {
		c.v = 1; c.t = Date.now();
		consent = c;
		doc.cookie = 'ppi_consent=' + encodeURIComponent(JSON.stringify(c)) + '; Max-Age=' + (180 * 86400) + '; Path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
		applyConsent();
	}
	function applyConsent() {
		if (!consent) { return; }
		if (consent.external) { $$('[data-ppi-embed]').forEach(loadEmbed); }
		var box = $('[data-ppi-consent]');
		if (consent.analytics && box && box.dataset.ga && !window.ppiGa) {
			window.ppiGa = true;
			var s = doc.createElement('script'); s.async = true; s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(box.dataset.ga);
			doc.head.appendChild(s);
			window.dataLayer = window.dataLayer || [];
			window.gtag = function () { window.dataLayer.push(arguments); };
			window.gtag('js', new Date()); window.gtag('config', box.dataset.ga, { anonymize_ip: true });
		}
	}
	function track(name, params) {
		// Eventos sem dados pessoais; só com medição configurada e aceite.
		if (consent && consent.analytics && window.gtag) { window.gtag('event', name, params || {}); }
	}
	function openConsent(focus) {
		var box = $('[data-ppi-consent]');
		if (!box) { return; }
		box.hidden = false; doc.body.classList.add('ppi-consent-open');
		var prefs = $('[data-ppi-consent-prefs]', box);
		if (consent && prefs) {
			$$('input[name]', prefs).forEach(function (i) { i.checked = !!consent[i.name]; });
		}
		if (focus) { var b = $('[data-ppi-consent-act="accept"]', box); if (b) { b.focus(); } }
	}
	function closeConsent() {
		var box = $('[data-ppi-consent]');
		if (box) { box.hidden = true; doc.body.classList.remove('ppi-consent-open'); }
	}
	doc.addEventListener('click', function (e) {
		var a = e.target.closest('[data-ppi-consent-act]');
		if (a) {
			var box = $('[data-ppi-consent]'), prefs = $('[data-ppi-consent-prefs]', box), act = a.dataset.ppiConsentAct;
			if (act === 'accept') { saveConsent({ analytics: true, external: true }); closeConsent(); announce(t.consentSaved); }
			if (act === 'reject') { saveConsent({ analytics: false, external: false }); closeConsent(); announce(t.consentSaved); }
			if (act === 'customize') {
				prefs.hidden = !prefs.hidden; a.setAttribute('aria-expanded', String(!prefs.hidden));
				$('[data-ppi-consent-act="save"]', box).hidden = prefs.hidden;
				if (!prefs.hidden) { var f = $('input:not([disabled])', prefs); if (f) { f.focus(); } }
			}
			if (act === 'save') {
				var c = { analytics: false, external: false };
				$$('input[name]', prefs).forEach(function (i) { c[i.name] = i.checked; });
				saveConsent(c); closeConsent(); announce(t.consentSaved);
			}
		}
		if (e.target.closest('[data-ppi-consent-open]')) { e.preventDefault(); openConsent(true); }
	});
	if (!consent) { openConsent(false); } else { applyConsent(); }

	function loadEmbed(box) {
		if (box.dataset.loaded) { return; }
		box.dataset.loaded = '1';
		var f = doc.createElement('iframe');
		f.src = box.dataset.src; f.title = box.dataset.title || ''; f.loading = 'lazy';
		f.setAttribute('allow', 'fullscreen; picture-in-picture'); f.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
		box.innerHTML = ''; box.appendChild(f); box.classList.add('is-loaded');
	}
	doc.addEventListener('click', function (e) {
		var b = e.target.closest('[data-ppi-embed-load]');
		if (b) { loadEmbed(b.closest('[data-ppi-embed]')); }
	});

	/* ---------------------------------------------------------------- Favoritos e comparação */
	var FAV = 'ppi_favs', CMP = 'ppi_compare';
	function syncButtons(root) {
		var favs = store(FAV), cmp = store(CMP);
		$$('[data-ppi-fav]', root).forEach(function (b) {
			var on = favs.indexOf(b.dataset.ppiFav) > -1;
			b.setAttribute('aria-pressed', String(on));
			var l = b.querySelector('span'); if (l) { l.textContent = on ? t.saved : t.save; }
		});
		$$('[data-ppi-compare]', root).forEach(function (b) {
			var on = cmp.indexOf(b.dataset.ppiCompare) > -1;
			b.setAttribute('aria-pressed', String(on));
			var l = b.querySelector('span'); if (l) { l.textContent = on ? t.comparing : t.compare; }
		});
		$$('[data-ppi-fav-count]').forEach(function (n) { n.textContent = favs.length ? String(favs.length) : ''; n.hidden = !favs.length; });
		$$('[data-ppi-cmp-count]').forEach(function (n) { n.textContent = cmp.length ? String(cmp.length) : ''; n.hidden = !cmp.length; });
		var bar = $('[data-ppi-cmpbar]');
		if (bar) {
			bar.hidden = cmp.length < 1;
			var link = $('a', bar); if (link) { link.href = cfg.compareUrl + (cfg.compareUrl.indexOf('?') > -1 ? '&' : '?') + 'refs=' + encodeURIComponent(cmp.join(',')); }
			var txt = $('[data-ppi-cmpbar-text]', bar); if (txt) { txt.textContent = (t.compareCount || '%d').replace('%d', cmp.length); }
		}
	}
	doc.addEventListener('click', function (e) {
		var f = e.target.closest('[data-ppi-fav]'), c = e.target.closest('[data-ppi-compare]');
		if (f) {
			var favs = store(FAV), r = f.dataset.ppiFav, i = favs.indexOf(r);
			if (i > -1) { favs.splice(i, 1); announce(t.removedFav.replace('%s', r)); } else { favs.unshift(r); announce(t.addedFav.replace('%s', r)); track('add_to_wishlist', { item_id: r }); }
			store(FAV, favs.slice(0, 100)); syncButtons();
			if (f.closest('[data-ppi-saved]') && i > -1) { var card = f.closest('.ppi-card'); if (card) { card.remove(); } renderSaved(); }
		}
		if (c) {
			var cmp = store(CMP), rr = c.dataset.ppiCompare, j = cmp.indexOf(rr);
			if (j > -1) { cmp.splice(j, 1); announce(t.removedCmp.replace('%s', rr)); }
			else if (cmp.length >= 3) { announce(t.cmpFull); alert(t.cmpFull); return; } // eslint-disable-line no-alert
			else { cmp.push(rr); announce(t.addedCmp.replace('%s', rr)); }
			store(CMP, cmp); syncButtons();
		}
		var rm = e.target.closest('[data-ppi-compare-remove]');
		if (rm) {
			var list = store(CMP).filter(function (x) { return x !== rm.dataset.ppiCompareRemove; });
			store(CMP, list);
			location.href = cfg.compareUrl + (list.length ? (cfg.compareUrl.indexOf('?') > -1 ? '&' : '?') + 'refs=' + encodeURIComponent(list.join(',')) : '');
		}
	});
	syncButtons();
	window.addEventListener('storage', function () { syncButtons(); });

	function renderSaved() {
		var box = $('[data-ppi-saved]');
		if (!box) { return; }
		var favs = store(FAV), list = $('[data-ppi-list]', box), empty = $('[data-ppi-empty]', box);
		if (!favs.length) { list.innerHTML = ''; empty.hidden = false; return; }
		empty.hidden = true;
		if (list.dataset.loaded === favs.join(',')) { return; }
		box.setAttribute('aria-busy', 'true');
		fetch(box.dataset.endpoint + '?lang=' + encodeURIComponent(box.dataset.lang) + '&refs=' + encodeURIComponent(favs.join(',')), { headers: { Accept: 'application/json' } })
			.then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.json(); })
			.then(function (d) {
				list.innerHTML = '';
				d.items.forEach(function (it) {
					if (it.available && it.html) { list.insertAdjacentHTML('beforeend', it.html); return; }
					var n = doc.createElement('article'); n.className = 'ppi-card ppi-card--gone';
					var body = doc.createElement('div'); body.className = 'ppi-card__body';
					var h = doc.createElement('h2'); h.className = 'ppi-card__title'; h.textContent = it.title || (t.refLabel + ' ' + it.ref);
					var p = doc.createElement('p'); p.className = 'ppi-card__price'; p.textContent = (it.status || t.unavailable) + ' — ' + t.unavailableHelp;
					var b = doc.createElement('button'); b.type = 'button'; b.className = 'ppi-btn ppi-btn--ghost ppi-btn--small'; b.dataset.ppiFav = it.ref; b.textContent = t.removeFromFav;
					body.appendChild(h); body.appendChild(p); body.appendChild(b); n.appendChild(body); list.appendChild(n);
				});
				list.dataset.loaded = favs.join(',');
				reveal(list); syncButtons(list);
			})
			.catch(function () { list.innerHTML = '<p class="ppi-error">' + t.loadError + '</p>'; })
			.then(function () { box.setAttribute('aria-busy', 'false'); });
	}
	renderSaved();

	// Página de comparação: se aberta sem ?refs, usar a seleção local.
	var cpage = $('[data-ppi-compare-page]');
	if (cpage && location.search.indexOf('refs=') < 0) {
		var sel = store(CMP);
		if (sel.length) { location.replace(cfg.compareUrl + (cfg.compareUrl.indexOf('?') > -1 ? '&' : '?') + 'refs=' + encodeURIComponent(sel.join(','))); }
	}

	/* ---------------------------------------------------------------- Catálogo: filtros sem recarregar */
	var catalog = $('[data-ppi-catalog]');
	if (catalog && window.fetch && window.history && history.pushState) {
		var ctrl = null;
		var load = function (url, push) {
			var res = $('[data-ppi-results]');
			if (ctrl) { ctrl.abort(); }
			ctrl = window.AbortController ? new AbortController() : null;
			res.setAttribute('aria-busy', 'true'); catalog.classList.add('is-loading');
			fetch(url, { headers: { 'X-PPI-Partial': '1' }, signal: ctrl ? ctrl.signal : undefined, credentials: 'same-origin' })
				.then(function (r) { if (!r.ok) { throw new Error(r.status); } return r.text(); })
				.then(function (html) {
					var d = new DOMParser().parseFromString(html, 'text/html');
					var nr = $('[data-ppi-results]', d), nf = $('[data-ppi-filters]', d);
					if (!nr) { throw new Error('parse'); }
					res.replaceWith(nr);
					var of = $('[data-ppi-filters]', catalog);
					if (of && nf) {
						var active = doc.activeElement && doc.activeElement.id;
						of.replaceWith(nf);
						if (active && $('#' + active)) { $('#' + active).focus(); }
					}
					if (push) { history.pushState({ ppi: 1 }, '', url); }
					doc.title = d.title;
					reveal(catalog); syncButtons(catalog);
					var cnt = $('.ppi-results__count', nr); if (cnt) { announce(cnt.textContent.trim()); }
					track('search', { search_term: 'filters' });
				})
				.catch(function (e) {
					if (e && e.name === 'AbortError') { return; }
					var r = $('[data-ppi-results]'); r.setAttribute('aria-busy', 'false');
					var msg = doc.createElement('p'); msg.className = 'ppi-error'; msg.setAttribute('role', 'alert');
					msg.innerHTML = t.searchError + ' <a href="' + url.replace(/"/g, '') + '">' + t.reload + '</a>';
					r.prepend(msg);
				})
				.then(function () { catalog.classList.remove('is-loading'); });
		};
		var formUrl = function (form) {
			var fd = new FormData(form), q = new URLSearchParams(), carac = [];
			fd.forEach(function (v, k) {
				if (!v) { return; }
				if (k === 'carac[]') { carac.push(v); return; }
				q.set(k, v);
			});
			if (carac.length) { q.set('carac', carac.sort().join(',')); }
			var base = form.getAttribute('action');
			return base + (q.toString() ? '?' + q.toString() : '');
		};
		var timer;
		catalog.addEventListener('change', function (e) {
			var form = e.target.closest('form');
			if (!form || (!form.matches('[data-ppi-filters]') && !form.matches('[data-ppi-sort]'))) { return; }
			if (e.target.name === 'ref') { return; }
			if (form.matches('[data-ppi-sort]')) {
				var f = $('[data-ppi-filters]', catalog), u = new URL(formUrl(f), location.href);
				var o = e.target.value; o === 'recentes' ? u.searchParams.delete('ordem') : u.searchParams.set('ordem', o);
				u.searchParams.delete('pagina');
				load(u.toString(), true); return;
			}
			clearTimeout(timer); timer = setTimeout(function () { load(withSort(formUrl(form)), true); }, 120);
		});
		var withSort = function (url) {
			var s = $('#ppi-sort'), u = new URL(url, location.href);
			if (s && s.value !== 'recentes') { u.searchParams.set('ordem', s.value); }
			return u.toString();
		};
		catalog.addEventListener('submit', function (e) {
			var form = e.target;
			if (form.matches('[data-ppi-filters]')) { e.preventDefault(); load(withSort(formUrl(form)), true); }
		});
		catalog.addEventListener('click', function (e) {
			var a = e.target.closest('.ppi-pages a, .ppi-active a');
			if (!a) { return; }
			e.preventDefault(); load(a.href, true);
			if (a.closest('.ppi-pages')) { var top = $('[data-ppi-results]'); if (top) { top.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' }); } }
		});
		window.addEventListener('popstate', function () { load(location.href, false); });
		// Filtros em ecrã pequeno: painel que abre/fecha.
		var tog = $('[data-ppi-filters-toggle]', catalog);
		if (tog) {
			tog.addEventListener('click', function () {
				var open = catalog.classList.toggle('filters-open');
				tog.setAttribute('aria-expanded', String(open));
				if (open) { var first = $('[data-ppi-filters] select, [data-ppi-filters] input', catalog); if (first) { first.focus(); } }
			});
			catalog.addEventListener('keydown', function (e) {
				if (e.key === 'Escape' && catalog.classList.contains('filters-open')) { catalog.classList.remove('filters-open'); tog.setAttribute('aria-expanded', 'false'); tog.focus(); }
			});
		}
	}

	/* ---------------------------------------------------------------- Galeria em ecrã inteiro */
	$$('[data-ppi-gallery]').forEach(function (g) {
		var dlg = $('[data-ppi-lightbox]', g);
		if (!dlg || typeof dlg.showModal !== 'function') { return; }
		var slides = $$('.ppi-lightbox__slide', dlg), cur = 0, opener = null, n = slides.length;
		function show(i) {
			cur = (i + n) % n;
			slides.forEach(function (s, k) {
				s.hidden = k !== cur;
				if (Math.abs(k - cur) <= 1 || (cur === 0 && k === n - 1) || (cur === n - 1 && k === 0)) {
					var img = $('img', s);
					if (img && !img.src && img.dataset.src) { img.srcset = img.dataset.srcset || ''; img.src = img.dataset.src; }
				}
			});
			$('[data-ppi-current]', dlg).textContent = String(cur + 1);
		}
		function open(i, from) {
			opener = from || doc.activeElement; show(i); dlg.showModal(); doc.body.classList.add('ppi-noscroll');
			var c = $('[data-ppi-close]', dlg); if (c) { c.focus(); }
		}
		dlg.addEventListener('close', function () { doc.body.classList.remove('ppi-noscroll'); if (opener && opener.focus) { opener.focus(); } });
		g.addEventListener('click', function (e) {
			var o = e.target.closest('[data-ppi-open]');
			if (o) { e.preventDefault(); open(parseInt(o.dataset.ppiOpen, 10) || 0, o); }
		});
		$('[data-ppi-close]', dlg).addEventListener('click', function () { dlg.close(); });
		$('[data-ppi-prev]', dlg).addEventListener('click', function () { show(cur - 1); });
		$('[data-ppi-next]', dlg).addEventListener('click', function () { show(cur + 1); });
		dlg.addEventListener('keydown', function (e) {
			if (e.key === 'ArrowRight') { show(cur + 1); e.preventDefault(); }
			if (e.key === 'ArrowLeft') { show(cur - 1); e.preventDefault(); }
			if (e.key === 'Home') { show(0); e.preventDefault(); }
			if (e.key === 'End') { show(n - 1); e.preventDefault(); }
		});
		dlg.addEventListener('click', function (e) { if (e.target === dlg) { dlg.close(); } });
		var x0 = null, y0 = null;
		var trk = $('[data-ppi-track]', dlg);
		trk.addEventListener('pointerdown', function (e) { x0 = e.clientX; y0 = e.clientY; });
		trk.addEventListener('pointerup', function (e) {
			if (x0 === null) { return; }
			var dx = e.clientX - x0, dy = e.clientY - y0; x0 = null;
			if (Math.abs(dx) > 40 && Math.abs(dx) > Math.abs(dy)) { show(cur + (dx < 0 ? 1 : -1)); }
		});
	});

	/* ---------------------------------------------------------------- Formulários */
	$$('[data-ppi-form]').forEach(function (form) {
		var visit = $('[data-ppi-visit-fields]', form);
		function mode() {
			var m = $('input[name="type"]:checked', form);
			if (visit && m) { visit.hidden = m.value !== 'visit'; }
		}
		$$('[data-ppi-mode]', form).forEach(function (r) { r.addEventListener('change', mode); });
		mode();
		var focusTarget = $('[data-ppi-focus]', form); if (focusTarget) { focusTarget.focus(); }
		if (!window.fetch || !window.FormData) { return; }
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			if (form.dataset.busy) { return; }
			var btn = $('.ppi-form__submit', form), status = $('[data-ppi-status]', form), label = btn.textContent;
			$$('.ppi-error', form).forEach(function (n) { n.remove(); });
			$$('[aria-invalid]', form).forEach(function (n) { n.removeAttribute('aria-invalid'); n.closest('.ppi-field').classList.remove('has-error'); });
			// Validação local mínima (o servidor valida sempre).
			var bad = null;
			$$('[required]', form).forEach(function (i) {
				var empty = i.type === 'file' ? !i.files.length : !String(i.value).trim();
				var invalid = empty || (i.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(i.value));
				if (invalid) { fieldError(i, empty ? t.required : t.emailInvalid); bad = bad || i; }
			});
			if (bad) { status.textContent = t.fixErrors; bad.focus(); return; }
			form.dataset.busy = '1'; btn.disabled = true; btn.textContent = btn.dataset.labelBusy || label;
			status.textContent = t.sending;
			var sent = new FormData(form), sentType = sent.get('type');
			fetch(form.getAttribute('action'), { method: 'POST', body: sent, headers: { Accept: 'application/json' }, credentials: 'same-origin' })
				.then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
				.then(function (x) {
					if (x.d.ok) {
						var done = doc.createElement('div'); done.className = 'ppi-form__done'; done.setAttribute('role', 'status'); done.tabIndex = -1; done.textContent = x.d.message;
						form.innerHTML = ''; form.appendChild(done); done.focus();
						track(sentType === 'visit' ? 'request_visit' : 'generate_lead', {});
						return;
					}
					var first = null;
					Object.keys(x.d.errors || {}).forEach(function (k) {
						var inp = form.elements[k];
						if (inp && inp.nodeType) { fieldError(inp, x.d.errors[k]); first = first || inp; }
					});
					status.textContent = (x.d.errors && x.d.errors._form) || x.d.message || t.fixErrors;
					if (first) { first.focus(); }
				})
				.catch(function () { status.textContent = t.sendError; })
				.then(function () { delete form.dataset.busy; if (btn.isConnected) { btn.disabled = false; btn.textContent = label; } });
		});
		// Token novo por carregamento (a página pode vir de cache).
		var tok = $('[data-ppi-token]', form);
		if (tok && window.crypto && crypto.randomUUID) { tok.value = crypto.randomUUID(); }
	});
	function fieldError(input, msg) {
		var wrap = input.closest('.ppi-field') || input.parentNode;
		wrap.classList.add('has-error');
		input.setAttribute('aria-invalid', 'true');
		var id = (input.id || input.name) + '-err-js';
		var p = doc.createElement('p'); p.className = 'ppi-error'; p.id = id; p.textContent = msg;
		wrap.appendChild(p);
		input.setAttribute('aria-describedby', ((input.getAttribute('aria-describedby') || '') + ' ' + id).trim());
	}

	/* ---------------------------------------------------------------- Barra móvel da ficha */
	var mbar = $('[data-ppi-mbar]'), contact = $('#contactar');
	if (mbar && contact && 'IntersectionObserver' in window) {
		new IntersectionObserver(function (es) {
			es.forEach(function (en) { mbar.classList.toggle('is-hidden', en.isIntersecting); });
		}, { threshold: 0.05 }).observe(contact);
		doc.addEventListener('focusin', function (e) { mbar.classList.toggle('is-hidden', !!e.target.closest('form, [data-ppi-consent]')); });
	}
	doc.addEventListener('click', function (e) {
		var g = e.target.closest('[data-ppi-goto-form]');
		if (!g || !contact) { return; }
		e.preventDefault();
		contact.scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
		var v = $('input[name="type"][value="visit"]', contact); if (v) { v.checked = true; v.dispatchEvent(new Event('change')); }
		var nm = $('input[name="name"]', contact); if (nm) { setTimeout(function () { nm.focus({ preventScroll: true }); }, reduce ? 0 : 350); }
	});
})();
