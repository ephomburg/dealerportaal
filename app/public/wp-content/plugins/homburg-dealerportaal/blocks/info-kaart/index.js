( function ( blocks, element, blockEditor, components ) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var InspectorControls = blockEditor.InspectorControls;
	var RichText = blockEditor.RichText;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var TextareaControl = components.TextareaControl;
	var SelectControl = components.SelectControl;

	// Zelfde iconenset als HDP_Icons (PHP) — dit blok is bewust statisch
	// (geen render.php), dus de paden staan ook hier, alleen voor de
	// iconen die in de infosectie gebruikt worden.
	var ICOON_PADEN = {
		bestellen: '<path d="M20.59 13.41 11 3.83A2 2 0 0 0 9.59 3.24L4 3a1 1 0 0 0-1 1l.24 5.59a2 2 0 0 0 .59 1.41l9.58 9.58a2 2 0 0 0 2.83 0l4.35-4.34a2 2 0 0 0 0-2.83Z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
		mail: '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
		technisch: '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94Z"/>',
		bogballe: '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2Z"/>',
		vaderstad: '<path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2Z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7Z"/>',
		link: '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>'
	};
	var ICOON_OPTIES = [
		{ label: 'Bestellen (label/tag)', value: 'bestellen' },
		{ label: 'Envelop (mail)', value: 'mail' },
		{ label: 'Sleutel (technisch)', value: 'technisch' },
		{ label: 'Map (bogballe)', value: 'bogballe' },
		{ label: 'Boek (vaderstad)', value: 'vaderstad' },
		{ label: 'Link (extern)', value: 'link' }
	];

	function icoonSvg( type ) {
		var pad = ICOON_PADEN[ type ] || ICOON_PADEN.link;
		return '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + pad + '</svg>';
	}

	function linkRij( label, url ) {
		if ( ! label ) {
			return null;
		}
		return el( 'a', {
			className: 'hdp-info-link',
			href: url || '#',
			target: '_blank',
			rel: 'noopener noreferrer',
			dangerouslySetInnerHTML: { __html: icoonSvg( 'link' ) + ' ' + label }
		} );
	}

	blocks.registerBlockType( 'homburg/info-kaart', {
		edit: function ( props ) {
			var a = props.attributes;
			var setAttributes = props.setAttributes;

			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: 'Icoon', initialOpen: true },
						el( SelectControl, {
							label: 'Icoon',
							value: a.icoon,
							options: ICOON_OPTIES,
							onChange: function ( waarde ) { setAttributes( { icoon: waarde } ); }
						} )
					),
					el(
						PanelBody,
						{ title: 'Frans (FR)', initialOpen: false },
						el( TextControl, {
							label: 'Titel (FR)',
							value: a.titelFr,
							onChange: function ( waarde ) { setAttributes( { titelFr: waarde } ); }
						} ),
						el( TextareaControl, {
							label: 'Tekst (FR)',
							value: a.tekstFr,
							onChange: function ( waarde ) { setAttributes( { tekstFr: waarde } ); }
						} )
					),
					el(
						PanelBody,
						{ title: 'Links', initialOpen: false },
						el( TextControl, {
							label: 'Link 1 — tekst',
							value: a.link1Label,
							onChange: function ( waarde ) { setAttributes( { link1Label: waarde } ); }
						} ),
						el( TextControl, {
							label: 'Link 1 — URL',
							value: a.link1Url,
							onChange: function ( waarde ) { setAttributes( { link1Url: waarde } ); }
						} ),
						el( TextControl, {
							label: 'Link 2 — tekst',
							value: a.link2Label,
							onChange: function ( waarde ) { setAttributes( { link2Label: waarde } ); }
						} ),
						el( TextControl, {
							label: 'Link 2 — URL',
							value: a.link2Url,
							onChange: function ( waarde ) { setAttributes( { link2Url: waarde } ); }
						} )
					)
				),
				el(
					'article',
					{ className: 'hdp-info-kaart' },
					el( 'div', {
						className: 'hdp-info-icoon',
						'aria-hidden': 'true',
						dangerouslySetInnerHTML: { __html: icoonSvg( a.icoon ) }
					} ),
					el(
						'div',
						{ className: 'hdp-info-body' },
						el( RichText, {
							tagName: 'h3',
							value: a.titel,
							onChange: function ( waarde ) { setAttributes( { titel: waarde } ); },
							placeholder: 'Titel (NL)'
						} ),
						el( RichText, {
							tagName: 'p',
							value: a.tekst,
							onChange: function ( waarde ) { setAttributes( { tekst: waarde } ); },
							placeholder: 'Tekst (NL)'
						} ),
						( a.link1Label || a.link2Label ) ? el(
							'div',
							{ className: 'hdp-info-links' },
							linkRij( a.link1Label, a.link1Url ),
							linkRij( a.link2Label, a.link2Url )
						) : null
					)
				)
			);
		},
		// Dynamisch blok (zie render.php): de front-end wordt vanuit de
		// attributen opgebouwd, niet vanuit hier opgeslagen HTML.
		save: function () {
			return null;
		}
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components );
