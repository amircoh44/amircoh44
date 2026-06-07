/**
 * Business Profile: logo/image media pickers.
 *
 * @package SeoSprinkler
 */
( function ( $ ) {
	'use strict';

	var cfg = window.SPR_BIZ || {};

	$( function () {
		$( '.spr-media' ).each( function () {
			var $wrap   = $( this ),
				$id     = $wrap.find( '.spr-media-id' ),
				$prev   = $wrap.find( '.spr-media-preview' ),
				$clear  = $wrap.find( '.spr-media-clear' ),
				frame;

			$wrap.on( 'click', '.spr-media-choose', function ( e ) {
				e.preventDefault();
				if ( frame ) { frame.open(); return; }
				frame = wp.media( {
					title: cfg.choose || 'Select image',
					button: { text: cfg.use || 'Use this image' },
					multiple: false
				} );
				frame.on( 'select', function () {
					var att = frame.state().get( 'selection' ).first().toJSON();
					$id.val( att.id );
					var url = ( att.sizes && att.sizes.medium ) ? att.sizes.medium.url : att.url;
					$prev.html( '<img src="' + url + '" alt="" style="max-width:160px;height:auto" />' );
					$clear.show();
				} );
				frame.open();
			} );

			$clear.on( 'click', function ( e ) {
				e.preventDefault();
				$id.val( '' );
				$prev.empty();
				$( this ).hide();
			} );
		} );
	} );
} )( jQuery );
