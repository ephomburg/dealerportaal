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

	blocks.registerBlockType( 'homburg/dealerportaal', {
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
						{ title: 'Hero-afbeelding', initialOpen: false },
						veld( attributes, setAttributes, 'Afbeeldings-URL', 'heroAfbeelding', false )
					),
					el(
						PanelBody,
						{ title: 'Inlogscherm', initialOpen: false },
						veld( attributes, setAttributes, 'Introductietekst (NL)', 'loginIntro', true ),
						veld( attributes, setAttributes, 'Introductietekst (FR)', 'loginIntroFr', true )
					),
					el(
						PanelBody,
						{ title: 'Portaal (na inloggen)', initialOpen: false },
						veld( attributes, setAttributes, 'Introductietekst (NL)', 'portaalIntro', true ),
						veld( attributes, setAttributes, 'Introductietekst (FR)', 'portaalIntroFr', true )
					)
				),
				el( ServerSideRender, {
					block: 'homburg/dealerportaal',
					attributes: attributes,
				} )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.blockEditor, window.wp.components, window.wp.serverSideRender );
