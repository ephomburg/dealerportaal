( function ( blocks, element, blockEditor, components, serverSideRender ) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var TextareaControl = components.TextareaControl;
	var ServerSideRender = serverSideRender;

	blocks.registerBlockType( 'homburg/admin-upload', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;

			return el(
				Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: 'Teksten', initialOpen: true },
						el( TextControl, {
							label: 'Titel',
							value: attributes.titel,
							onChange: function ( waarde ) { setAttributes( { titel: waarde } ); },
						} ),
						el( TextareaControl, {
							label: 'Introductietekst',
							value: attributes.intro,
							onChange: function ( waarde ) { setAttributes( { intro: waarde } ); },
						} )
					)
				),
				el( ServerSideRender, {
					block: 'homburg/admin-upload',
					attributes: attributes,
				} )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.serverSideRender );
