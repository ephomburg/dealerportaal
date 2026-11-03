( function ( blocks, element, serverSideRender ) {
	var el = element.createElement;

	// Niets in te stellen: de trainingen zelf staan onder Trainingen in wp-admin.
	blocks.registerBlockType( 'homburg/trainingen-pagina', {
		edit: function ( props ) {
			return el( serverSideRender, { block: 'homburg/trainingen-pagina', attributes: props.attributes } );
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.serverSideRender );
