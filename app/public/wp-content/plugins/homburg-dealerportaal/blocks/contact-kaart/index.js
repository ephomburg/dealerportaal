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

	// Zelfde iconenset als HDP_Icons (PHP) — alleen voor de iconenkiezer
	// en de canvas-preview hier; de front-end gebruikt HDP_Icons zelf.
	var ICOON_PADEN = {
		mail: '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
		tel: '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
		gebruiker: '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
		link: '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>'
	};
	var ICOON_OPTIES = [
		{ label: 'Envelop (mail)', value: 'mail' },
		{ label: 'Telefoon', value: 'tel' },
		{ label: 'Persoon', value: 'gebruiker' },
		{ label: 'Link (extern)', value: 'link' }
	];

	function icoonSvg( type ) {
		var pad = ICOON_PADEN[ type ] || ICOON_PADEN.mail;
		return '<svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + pad + '</svg>';
	}

	function knopPreview( icoon, tekst ) {
		if ( ! tekst ) {
			return null;
		}
		return el( 'span', {
			className: 'hdp-info-link',
			dangerouslySetInnerHTML: { __html: icoonSvg( icoon ) + ' ' + tekst }
		} );
	}

	blocks.registerBlockType( 'homburg/contact-kaart', {
		edit: function ( props ) {
			var a = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps( { className: 'hdp-info-kaart' } );

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
						{ title: 'Contactgegevens', initialOpen: true },
						el( TextControl, {
							label: 'E-mailadres',
							type: 'email',
							value: a.email,
							help: 'Wordt automatisch een mailto:-knop.',
							onChange: function ( waarde ) { setAttributes( { email: waarde } ); }
						} ),
						el( TextControl, {
							label: 'Telefoonnummer',
							value: a.telefoon,
							help: 'Weergave zoals ingevuld; de link wordt tel:+cijfers.',
							onChange: function ( waarde ) { setAttributes( { telefoon: waarde } ); }
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
					)
				),
				el(
					'article',
					blockProps,
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
						( a.email || a.telefoon ) ? el(
							'div',
							{ className: 'hdp-info-links' },
							knopPreview( 'mail', a.email ),
							knopPreview( 'tel', a.telefoon )
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
