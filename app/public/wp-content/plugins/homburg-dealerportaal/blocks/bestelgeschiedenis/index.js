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

	blocks.registerBlockType( 'homburg/bestelgeschiedenis-pagina', {
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
						veld( attributes, setAttributes, 'Afbeeldings-URL (hero, bij uitgelogd)', 'heroAfbeelding', false ),
						veld( attributes, setAttributes, 'Inlogintro (NL)', 'loginIntro', true ),
						veld( attributes, setAttributes, 'Inlogintro (FR)', 'loginIntroFr', true ),
						veld( attributes, setAttributes, 'Titel (NL)', 'titel', false ),
						veld( attributes, setAttributes, 'Titel (FR)', 'titelFr', false ),
						veld( attributes, setAttributes, 'Omschrijving (NL)', 'omschrijving', true ),
						veld( attributes, setAttributes, 'Omschrijving (FR)', 'omschrijvingFr', true )
					)
				),
				el( ServerSideRender, {
					block: 'homburg/bestelgeschiedenis-pagina',
					attributes: attributes,
				} )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.serverSideRender );
