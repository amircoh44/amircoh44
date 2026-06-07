/**
 * Content Distribution admin: image picker + payload-type field toggling.
 *
 * @package SeoSprinkler
 */
( function ( $ ) {
	'use strict';

	var dist = window.SPR_DIST || {};

	/**
	 * Show only the payload fields relevant to the selected payload type.
	 */
	function syncPayloadFields() {
		var type = $( '.spr-payload-type' ).val();
		$( '.spr-when' ).hide();
		$( '.spr-when-' + type ).show();
	}

	$( function () {
		// Payload type toggle.
		$( '.spr-payload-type' ).on( 'change', syncPayloadFields );
		syncPayloadFields();

		// Media picker for the image payload.
		var frame;
		$( '#spr-choose-image' ).on( 'click', function ( e ) {
			e.preventDefault();

			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media( {
				title: dist.frameTitle || 'Select an image',
				button: { text: dist.useImage || 'Use this image' },
				library: { type: 'image' },
				multiple: false
			} );

			frame.on( 'select', function () {
				var att = frame.state().get( 'selection' ).first().toJSON(),
					url = ( att.sizes && att.sizes.thumbnail ) ? att.sizes.thumbnail.url : att.url;

				$( '#spr-image-id' ).val( att.id );
				$( '#spr-image-preview' ).html(
					$( '<img/>' ).attr( 'src', url ).css( { maxWidth: '120px', height: 'auto' } )
				);
				$( '#spr-remove-image' ).prop( 'disabled', false );
			} );

			frame.open();
		} );

		// Remove the chosen image.
		$( '#spr-remove-image' ).on( 'click', function ( e ) {
			e.preventDefault();
			$( '#spr-image-id' ).val( '' );
			$( '#spr-image-preview' ).empty();
			$( this ).prop( 'disabled', true );
		} );
	} );
} )( jQuery );
