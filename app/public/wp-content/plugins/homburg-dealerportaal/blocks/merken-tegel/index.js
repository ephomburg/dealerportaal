( function ( blocks, element, blockEditor, components ) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps = blockEditor.useBlockProps;
	var MediaUpload = blockEditor.MediaUpload;
	var MediaUploadCheck = blockEditor.MediaUploadCheck;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var SelectControl = components.SelectControl;
	var ToggleControl = components.ToggleControl;
	var Button = components.Button;

	blocks.registerBlockType( 'homburg/merken-tegel', {
		edit: function ( props ) {
			var a = props.attributes;
			var setAttributes = props.setAttributes;
			var kleurKlasse = 'rood' === a.kleur ? 'hdp-merktegel-rood' : 'hdp-merktegel-donker';
			var tegelStijl = a.afbeeldingUrl ? { backgroundImage: 'url(' + a.afbeeldingUrl + ')' } : {};
			var blockProps = useBlockProps( {
				className: 'hdp-merktegel ' + kleurKlasse + ( a.afbeeldingUrl ? ' hdp-merktegel-foto' : '' ),
				style: tegelStijl
			} );

			function kiesAfbeelding( media ) {
				setAttributes( { afbeeldingId: media.id, afbeeldingUrl: media.url } );
			}

			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: 'Merktegel', initialOpen: true },
						el( 'p', { className: 'components-base-control__help' }, 'Achtergrondfoto' ),
						el(
							MediaUploadCheck,
							{},
							el( MediaUpload, {
								onSelect: kiesAfbeelding,
								allowedTypes: [ 'image' ],
								value: a.afbeeldingId,
								render: function ( obj ) {
									return el(
										Fragment,
										{},
										el( Button, {
											variant: 'secondary',
											onClick: obj.open
										}, a.afbeeldingUrl ? 'Andere foto kiezen' : 'Foto kiezen' ),
										a.afbeeldingUrl ? el( Button, {
											variant: 'link',
											isDestructive: true,
											onClick: function () { setAttributes( { afbeeldingId: 0, afbeeldingUrl: '' } ); }
										}, 'Foto verwijderen' ) : null
									);
								}
							} )
						),
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
							label: 'Kleur (zolang er geen foto is gekozen)',
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
					el(
						'span',
						{ className: 'hdp-merktegel-tekst' },
						el( 'span', { className: 'hdp-merktegel-titel' }, a.titel || 'Merknaam' ),
						el( 'span', { className: 'hdp-merktegel-link' }, a.linkTekst || 'Linktekst' )
					),
					el( 'span', { className: 'hdp-merktegel-pijl' }, '→' )
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
