( function ( blocks, element, blockEditor, components, serverSideRender ) {
	var el = element.createElement;
	var Fragment = element.Fragment;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var TextareaControl = components.TextareaControl;
	var ServerSideRender = serverSideRender;

	function veld( attributes, setAttributes, label, key, meerdereRegels ) {
		var Control = meerdereRegels ? TextareaControl : TextControl;
		return el( Control, {
			label: label,
			value: attributes[ key ],
			onChange: function ( waarde ) {
				var wijziging = {};
				wijziging[ key ] = waarde;
				setAttributes( wijziging );
			},
		} );
	}

	blocks.registerBlockType( 'homburg/downloads-pagina', {
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
						veld( attributes, setAttributes, 'Afbeeldings-URL (hero)', 'heroAfbeelding', false ),
						veld( attributes, setAttributes, 'Titel', 'titel', false ),
						veld( attributes, setAttributes, 'Omschrijving', 'omschrijving', true )
					)
				),
				el( ServerSideRender, {
					block: 'homburg/downloads-pagina',
					attributes: attributes,
				} )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.serverSideRender );
