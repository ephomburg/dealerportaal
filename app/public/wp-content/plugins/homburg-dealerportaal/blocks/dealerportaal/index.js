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
						veld( attributes, setAttributes, 'Introductietekst', 'loginIntro', true )
					),
					el(
						PanelBody,
						{ title: 'Portaal (na inloggen)', initialOpen: false },
						veld( attributes, setAttributes, 'Introductietekst', 'portaalIntro', true )
					),
					el(
						PanelBody,
						{ title: 'Kaart 1 – Webshop', initialOpen: false },
						veld( attributes, setAttributes, 'Titel', 'kaart1Titel', false ),
						veld( attributes, setAttributes, 'Omschrijving', 'kaart1Omschrijving', true ),
						veld( attributes, setAttributes, 'Knoptekst', 'kaart1Knoptekst', false )
					),
					el(
						PanelBody,
						{ title: 'Kaart 2 – Configurator', initialOpen: false },
						veld( attributes, setAttributes, 'Titel', 'kaart2Titel', false ),
						veld( attributes, setAttributes, 'Omschrijving', 'kaart2Omschrijving', true ),
						veld( attributes, setAttributes, 'Knoptekst', 'kaart2Knoptekst', false )
					),
					el(
						PanelBody,
						{ title: 'Kaart 3 – Downloads', initialOpen: false },
						veld( attributes, setAttributes, 'Titel', 'kaart3Titel', false ),
						veld( attributes, setAttributes, 'Omschrijving', 'kaart3Omschrijving', true ),
						veld( attributes, setAttributes, 'Knoptekst', 'kaart3Knoptekst', false )
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
