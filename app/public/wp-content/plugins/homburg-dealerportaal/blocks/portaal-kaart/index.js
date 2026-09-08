( function ( blocks, element, blockEditor, components ) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var InspectorControls = blockEditor.InspectorControls;
	var RichText = blockEditor.RichText;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var TextareaControl = components.TextareaControl;
	var SelectControl = components.SelectControl;
	var ToggleControl = components.ToggleControl;

	// Zelfde iconenset als HDP_Icons (PHP) — alleen voor de iconenkiezer
	// hier in de editor; de front-end gebruikt gewoon HDP_Icons zelf.
	var ICOON_PADEN = {
		webshop: '<circle cx="9" cy="21" r="1.5"/><circle cx="19" cy="21" r="1.5"/><path d="M2 3h3l2.6 12.5a1 1 0 0 0 1 .8h9.7a1 1 0 0 0 1-.8L21 7H6"/>',
		configurator: '<path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.87l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.7 1.7 0 0 0-1.87-.34 1.7 1.7 0 0 0-1.03 1.56V21a2 2 0 1 1-4 0v-.09a1.7 1.7 0 0 0-1.12-1.56 1.7 1.7 0 0 0-1.87.34l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.7 1.7 0 0 0 4.6 15a1.7 1.7 0 0 0-1.56-1.03H3a2 2 0 1 1 0-4h.09A1.7 1.7 0 0 0 4.65 8.85a1.7 1.7 0 0 0-.34-1.87l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.7 1.7 0 0 0 1.87.34h.01A1.7 1.7 0 0 0 10.05 3V3a2 2 0 1 1 4 0v.09c0 .68.4 1.29 1.03 1.56a1.7 1.7 0 0 0 1.87-.34l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.7 1.7 0 0 0-.34 1.87v.01c.27.62.88 1.03 1.56 1.03H21a2 2 0 1 1 0 4h-.09c-.68 0-1.29.4-1.51 1z"/>',
		downloads: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M7 10l5 5 5-5"/><path d="M12 15V3"/>',
		content: '<rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>',
		link: '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>'
	};
	var ICOON_OPTIES = [
		{ label: 'Winkelwagen (webshop)', value: 'webshop' },
		{ label: 'Tandwiel (configurator)', value: 'configurator' },
		{ label: 'Download-pijl', value: 'downloads' },
		{ label: 'Afbeelding (content)', value: 'content' },
		{ label: 'Link (extern)', value: 'link' }
	];
	var INSTELLING_OPTIES = [
		{ label: 'Geen — gebruik het URL-veld hieronder', value: '' },
		{ label: 'Webshop-URL (Instellingen > Dealerportaal)', value: 'webshop_url' },
		{ label: 'Productconfigurator-URL (Instellingen > Dealerportaal)', value: 'configurator_url' }
	];

	function icoonSvg( type ) {
		var pad = ICOON_PADEN[ type ] || ICOON_PADEN.link;
		return '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + pad + '</svg>';
	}

	blocks.registerBlockType( 'homburg/portaal-kaart', {
		edit: function ( props ) {
			var a = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps( { className: 'hdp-kaart' } );

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
						} ),
						el( TextControl, {
							label: 'Knoptekst (FR)',
							value: a.knoptekstFr,
							onChange: function ( waarde ) { setAttributes( { knoptekstFr: waarde } ); }
						} )
					),
					el(
						PanelBody,
						{ title: 'Bestemming', initialOpen: true },
						el( SelectControl, {
							label: 'Koppel aan instelling',
							value: a.instellingSleutel,
							options: INSTELLING_OPTIES,
							onChange: function ( waarde ) { setAttributes( { instellingSleutel: waarde } ); }
						} ),
						! a.instellingSleutel ? el( TextControl, {
							label: 'URL',
							value: a.url,
							onChange: function ( waarde ) { setAttributes( { url: waarde } ); }
						} ) : null,
						! a.instellingSleutel ? el( ToggleControl, {
							label: 'Open in nieuw tabblad',
							checked: a.nieuweTab,
							onChange: function ( waarde ) { setAttributes( { nieuweTab: waarde } ); }
						} ) : null
					)
				),
				el(
					'article',
					blockProps,
					el( 'div', {
						className: 'hdp-kaart-icoon',
						'aria-hidden': 'true',
						dangerouslySetInnerHTML: { __html: icoonSvg( a.icoon ) }
					} ),
					el( RichText, {
						tagName: 'h2',
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
					el( RichText, {
						tagName: 'span',
						className: 'hdp-btn',
						value: a.knoptekst,
						onChange: function ( waarde ) { setAttributes( { knoptekst: waarde } ); },
						placeholder: 'Knoptekst (NL)'
					} ),
					a.instellingSleutel ? el( 'p', { style: { fontSize: '0.75rem', opacity: 0.6 } }, 'Link komt uit: ' + INSTELLING_OPTIES.filter( function ( o ) { return o.value === a.instellingSleutel; } )[ 0 ].label ) : null
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
