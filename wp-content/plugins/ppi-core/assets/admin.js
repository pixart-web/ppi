/* PPI Core — editor de imóveis: separadores acessíveis, galeria ordenável, ponto focal, linhas de características. */
(function ($) {
	'use strict';
	var t = (window.ppiAdmin && ppiAdmin.i18n) || {};

	// Separadores (padrão ARIA tabs: setas, Home/End).
	$('[data-ppi-tabs]').each(function () {
		var $root = $(this), $tabs = $root.find('[role=tab]');
		function select($tab) {
			$tabs.attr({ 'aria-selected': 'false', tabindex: '-1' });
			$root.find('[role=tabpanel]').attr('hidden', true);
			$tab.attr({ 'aria-selected': 'true', tabindex: '0' }).trigger('focus');
			$('#' + $tab.attr('aria-controls')).removeAttr('hidden');
		}
		$tabs.on('click', function () { select($(this)); });
		$tabs.on('keydown', function (e) {
			var i = $tabs.index(this), n = $tabs.length, k = e.key;
			if (k === 'ArrowRight') { select($tabs.eq((i + 1) % n)); e.preventDefault(); }
			if (k === 'ArrowLeft') { select($tabs.eq((i - 1 + n) % n)); e.preventDefault(); }
			if (k === 'Home') { select($tabs.eq(0)); e.preventDefault(); }
			if (k === 'End') { select($tabs.eq(n - 1)); e.preventDefault(); }
		});
		// Abrir o separador com o primeiro campo inválido ao submeter.
		$('#post').on('invalid', 'input,select', function () {
			var $p = $(this).closest('[role=tabpanel]');
			if ($p.length && $p.is('[hidden]')) { select($('#' + $p.attr('aria-labelledby'))); }
		}).find('input,select').each(function () { this.addEventListener('invalid', function () { $(this).trigger('invalid'); }); });
	});

	// Galeria e plantas.
	$('[data-ppi-media]').each(function () {
		var $box = $(this), $list = $box.find('[data-ppi-media-list]'), $input = $box.find('[data-ppi-media-input]'), frame;
		$list.find('li').attr('data-cover', t.cover || '');
		function sync() {
			$input.val($list.children().map(function () { return $(this).data('id'); }).get().join(','));
			$list.find('li').attr('data-cover', t.cover || '');
		}
		$list.sortable({ update: sync, tolerance: 'pointer' });
		$box.on('click', '[data-ppi-media-remove]', function () { $(this).closest('li').remove(); sync(); });
		$box.on('click', '[data-ppi-media-add]', function (e) {
			e.preventDefault();
			if (!frame) {
				frame = wp.media({ title: t.choose, button: { text: t.add }, library: { type: 'image' }, multiple: 'add' });
				frame.on('select', function () {
					frame.state().get('selection').each(function (a) {
						a = a.toJSON();
						if ($list.find('[data-id="' + a.id + '"]').length) { return; }
						var thumb = (a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url;
						var full = (a.sizes && a.sizes.medium_large) ? a.sizes.medium_large.url : a.url;
						$('<li>').attr({ 'data-id': a.id, 'data-full': full, 'data-focal': '50% 50%' })
							.append($('<img alt="" width="96" height="96">').attr('src', thumb))
							.append('<span class="ppi-media__actions"><button type="button" class="button-link" data-ppi-focal>' + t.focal + '</button> <button type="button" class="button-link ppi-danger" data-ppi-media-remove>' + t.remove + '</button></span>')
							.appendTo($list);
					});
					sync();
				});
			}
			frame.open();
		});
		// Ponto focal: clicar na fotografia.
		$box.on('click', '[data-ppi-focal]', function () {
			var $li = $(this).closest('li'), opener = this;
			var $o = $('<div class="ppi-focal" role="dialog" aria-modal="true" aria-label="' + t.focal + '"><div class="ppi-focal__box"><p>' + t.focalHelp + '</p><div class="ppi-focal__img"><img alt=""><span class="ppi-focal__dot"></span></div><p><button type="button" class="button">OK</button> <span class="ppi-focal__msg" role="status"></span></p></div></div>');
			var f = String($li.data('focal') || '50% 50%').split(' ');
			$o.find('img').attr('src', $li.data('full'));
			$o.find('.ppi-focal__dot').css({ left: f[0], top: f[1] });
			function close() { $o.remove(); opener.focus(); }
			$o.on('click', '.button', close).on('keydown', function (e) { if (e.key === 'Escape') { close(); } });
			$o.find('img').on('click', function (e) {
				var r = this.getBoundingClientRect(), x = Math.round((e.clientX - r.left) / r.width * 100), y = Math.round((e.clientY - r.top) / r.height * 100);
				$o.find('.ppi-focal__dot').css({ left: x + '%', top: y + '%' });
				$.post(ajaxurl, { action: 'ppi_focal', nonce: ppiAdmin.nonce, id: $li.data('id'), x: x, y: y }).done(function (res) {
					$li.data('focal', res.data.focal).find('img').css('object-position', res.data.focal);
					$o.find('.ppi-focal__msg').text(t.saved);
				});
			});
			$('body').append($o);
			$o.find('.button').trigger('focus');
		});
	});

	// Linhas de características.
	$('[data-ppi-features]').each(function () {
		var $tb = $(this).find('tbody');
		$(document).on('click', '[data-ppi-row-add]', function () {
			var i = $tb.children().length, $r = $tb.children().last().clone();
			$r.find('input').each(function () { this.value = ''; this.name = this.name.replace(/\[\d+\]/, '[' + i + ']'); });
			$tb.append($r); $r.find('input').first().trigger('focus');
		});
		$tb.on('click', '[data-ppi-row-remove]', function () { if ($tb.children().length > 1) { $(this).closest('tr').remove(); } else { $(this).closest('tr').find('input').val(''); } });
	});
})(jQuery);
