(function( $ ) {
	'use strict';

	function extendVisibilityClasses() {
		var selectors = [
			'.show_if_simple',
			'.show_if_virtual',
			'.show_if_downloadable',
			'.show_if_external',
			'.hide_if_grouped'
		];

		selectors.forEach( function( selector ) {
			$( selector ).addClass( 'show_if_codfaa_auction' );
		} );
	}

	function toggleAuctionPanels( productType ) {
		var type = productType || $( '#product-type' ).val();

		$( '.show_if_codfaa_auction' ).toggle( 'codfaa_auction' === type );
		$( '.hide_if_codfaa_auction' ).toggle( 'codfaa_auction' !== type );
	}

	$( document.body ).on( 'woocommerce-product-type-change', function( event, type ) {
		toggleAuctionPanels( type );
	} );

	$( document ).ready( function() {
		extendVisibilityClasses();
		toggleAuctionPanels();
	} );
})( jQuery );
