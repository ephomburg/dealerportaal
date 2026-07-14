( function ( blocks, element, serverSideRender ) {
	var el = element.createElement;

	blocks.registerBlockType( 'homburg/site-header', {
		edit: function () {
			return el( serverSideRender, { block: 'homburg/site-header' } );
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp.blocks, window.wp.element, window.wp.serverSideRender );
