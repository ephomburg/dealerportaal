( function ( blocks, element, blockEditor, components ) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var SelectControl = components.SelectControl;
	var ToggleControl = components.ToggleControl;

	blocks.registerBlockType( 'homburg/merken-tegel', {
		edit: function ( props ) {
			var a = props.attributes;
			var setAttributes = props.setAttributes;
			var kleurKlasse = 'rood' === a.kleur ? 'hdp-merktegel-rood' : 'hdp-merktegel-donker';
			var blockProps = useBlockProps( { className: 'hdp-merktegel ' + kleurKlasse } );

			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: 'Merktegel', initialOpen: true },
						el( TextControl, {
							label: 'Merknaam',
							value: a.titel,
							onChange: function ( waarde ) { setAttributes( { titel: waarde } ); }
						} ),
						el( TextControl, {
							label: 'Linktekst',
							value: a.linkTekst,
							help: 'Bijv. "Parts catalogue" of "media.bogballe.com".',
							onChange: function ( waarde ) { setAttributes( { linkTekst: waarde } ); }
						} ),
						el( TextControl, {
							label: 'URL',
							value: a.url,
							onChange: function ( waarde ) { setAttributes( { url: waarde } ); }
						} ),
						el( SelectControl, {
							label: 'Kleur',
							value: a.kleur,
							options: [
								{ label: 'Donkergrijs', value: 'donker' },
								{ label: 'Homburg-rood', value: 'rood' }
							],
							onChange: function ( waarde ) { setAttributes( { kleur: waarde } ); }
						} ),
						el( ToggleControl, {
							label: 'Open in nieuw tabblad',
							checked: a.nieuweTab,
							onChange: function ( waarde ) { setAttributes( { nieuweTab: waarde } ); }
						} )
					),
					el(
						PanelBody,
						{ title: 'Frans (FR)', initialOpen: false },
						el( TextControl, {
							label: 'Linktekst (FR)',
							value: a.linkTekstFr,
							onChange: function ( waarde ) { setAttributes( { linkTekstFr: waarde } ); }
						} )
					)
				),
				el(
					'div',
					blockProps,
					el( 'span', { className: 'hdp-merktegel-titel' }, a.titel || 'Merknaam' ),
					el( 'span', { className: 'hdp-merktegel-link' }, a.linkTekst || 'Linktekst' )
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
