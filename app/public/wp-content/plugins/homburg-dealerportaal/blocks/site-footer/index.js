( function ( blocks, element, serverSideRender ) {
	var el = element.createElement;

	blocks.registerBlockType( 'homburg/site-footer', {
		edit: function () {
			return el( serverSideRender, { block: 'homburg/site-footer' } );
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.serverSideRender );
