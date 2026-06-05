/**
 * Content Distribution admin: image picker + payload-type field toggling.
 *
 * @package SeoArticleBooster
 */
( function ( $ ) {
	'use strict';

	var dist = window.SAB_DIST || {};

	/**
	 * Show only the payload fields relevant to the selected payload type.
	 */
	function syncPayloadFields() {
		var type = $( '.sab-payload-type' ).val();
		$( '.sab-when' ).hide();
		$( '.sab-when-' + type ).show();
	}

	$( function () {
		// Payload type toggle.
		$( '.sab-payload-type' ).on( 'change', syncPayloadFields );
		syncPayloadFields();

		// Media picker for the image payload.
		var frame;
		$( '#sab-choose-image' ).on( 'click', function ( e ) {
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

				$( '#sab-image-id' ).val( att.id );
				$( '#sab-image-preview' ).html(
					$( '<img/>' ).attr( 'src', url ).css( { maxWidth: '120px', height: 'auto' } )
				);
				$( '#sab-remove-image' ).prop( 'disabled', false );
			} );

			frame.open();
		} );

		// Remove the chosen image.
		$( '#sab-remove-image' ).on( 'click', function ( e ) {
			e.preventDefault();
			$( '#sab-image-id' ).val( '' );
			$( '#sab-image-preview' ).empty();
			$( this ).prop( 'disabled', true );
		} );
	} );
} )( jQuery );
