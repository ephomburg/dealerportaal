( function ( blocks, element, serverSideRender ) {
	var el = element.createElement;

	// Niets in te stellen: in de editor alleen een voorbeeld van wat de
	// dealer ziet.
	blocks.registerBlockType( 'homburg/nieuw-voor-jou', {
		edit: function ( props ) {
			return el( serverSideRender, { block: 'homburg/nieuw-voor-jou', attributes: props.attributes } );
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.serverSideRender );
