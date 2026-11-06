/* PPI Core — registo dos blocos dinâmicos no editor (pré-visualização renderizada no servidor). */
(function (wp, cfg) {
	'use strict';
	if (!wp || !cfg) { return; }
	var el = wp.element.createElement, SSR = wp.serverSideRender, IC = wp.blockEditor.InspectorControls, BP = wp.blockEditor.useBlockProps;
	var C = wp.components;
	cfg.blocks.forEach(function (b) {
		wp.blocks.registerBlockType(b.name, {
			apiVersion: 3, title: b.title, category: 'ppi', icon: 'admin-home', attributes: b.attributes,
			supports: { html: false, align: ['wide', 'full'] },
			edit: function (props) {
				var a = props.attributes, set = props.setAttributes, controls = [];
				Object.keys(b.attributes).forEach(function (k) {
					var def = b.attributes[k];
					if (k === 'type') {
						controls.push(el(C.SelectControl, { key: k, label: 'Tipo de formulário', value: a[k], options: Object.keys(cfg.formTypes).map(function (v) { return { value: v, label: cfg.formTypes[v] }; }), onChange: function (v) { var o = {}; o[k] = v; set(o); } }));
					} else if (def.type === 'number') {
						controls.push(el(C.RangeControl, { key: k, label: k, value: a[k], min: 1, max: 6, onChange: function (v) { var o = {}; o[k] = v; set(o); } }));
					} else {
						controls.push(el(C.TextControl, { key: k, label: k === 'heading' ? 'Título (vazio = predefinido)' : (k === 'text' ? 'Texto de apoio' : k), value: a[k], onChange: function (v) { var o = {}; o[k] = v; set(o); } }));
					}
				});
				return el('div', BP(), controls.length ? el(IC, {}, el(C.PanelBody, { title: 'PPI' }, controls)) : null,
					el(C.Disabled, {}, el(SSR, { block: b.name, attributes: a })));
			},
			save: function () { return null; }
		});
	});
})(window.wp, window.ppiBlocks);
