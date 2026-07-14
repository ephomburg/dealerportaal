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
					),
					el(
						PanelBody,
						{ title: 'Kaart 1 – Webshop', initialOpen: false },
						veld( attributes, setAttributes, 'Titel (NL)', 'kaart1Titel', false ),
						veld( attributes, setAttributes, 'Titel (FR)', 'kaart1TitelFr', false ),
						veld( attributes, setAttributes, 'Omschrijving (NL)', 'kaart1Omschrijving', true ),
						veld( attributes, setAttributes, 'Omschrijving (FR)', 'kaart1OmschrijvingFr', true ),
						veld( attributes, setAttributes, 'Knoptekst (NL)', 'kaart1Knoptekst', false ),
						veld( attributes, setAttributes, 'Knoptekst (FR)', 'kaart1KnoptekstFr', false )
					),
					el(
						PanelBody,
						{ title: 'Kaart 2 – Configurator', initialOpen: false },
						veld( attributes, setAttributes, 'Titel (NL)', 'kaart2Titel', false ),
						veld( attributes, setAttributes, 'Titel (FR)', 'kaart2TitelFr', false ),
						veld( attributes, setAttributes, 'Omschrijving (NL)', 'kaart2Omschrijving', true ),
						veld( attributes, setAttributes, 'Omschrijving (FR)', 'kaart2OmschrijvingFr', true ),
						veld( attributes, setAttributes, 'Knoptekst (NL)', 'kaart2Knoptekst', false ),
						veld( attributes, setAttributes, 'Knoptekst (FR)', 'kaart2KnoptekstFr', false )
					),
					el(
						PanelBody,
						{ title: 'Kaart 3 – Downloads', initialOpen: false },
						veld( attributes, setAttributes, 'Titel (NL)', 'kaart3Titel', false ),
						veld( attributes, setAttributes, 'Titel (FR)', 'kaart3TitelFr', false ),
						veld( attributes, setAttributes, 'Omschrijving (NL)', 'kaart3Omschrijving', true ),
						veld( attributes, setAttributes, 'Omschrijving (FR)', 'kaart3OmschrijvingFr', true ),
						veld( attributes, setAttributes, 'Knoptekst (NL)', 'kaart3Knoptekst', false ),
						veld( attributes, setAttributes, 'Knoptekst (FR)', 'kaart3KnoptekstFr', false )
					),
					el(
						PanelBody,
						{ title: 'Kaart 4 – Content', initialOpen: false },
						veld( attributes, setAttributes, 'Titel (NL)', 'kaart4Titel', false ),
						veld( attributes, setAttributes, 'Titel (FR)', 'kaart4TitelFr', false ),
						veld( attributes, setAttributes, 'Omschrijving (NL)', 'kaart4Omschrijving', true ),
						veld( attributes, setAttributes, 'Omschrijving (FR)', 'kaart4OmschrijvingFr', true ),
						veld( attributes, setAttributes, 'Knoptekst (NL)', 'kaart4Knoptekst', false ),
						veld( attributes, setAttributes, 'Knoptekst (FR)', 'kaart4KnoptekstFr', false )
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
