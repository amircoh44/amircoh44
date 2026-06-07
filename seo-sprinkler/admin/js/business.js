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

		// ---- Auto-fill identity fields from the WordPress site (blanks only) ----
		var site = cfg.site || {},
			i18n = cfg.i18n || {};

		function fillIfEmpty( id, val ) {
			if ( ! val ) { return; }
			var $f = $( '#spr-' + id );
			if ( $f.length && '' === $.trim( $f.val() ) ) { $f.val( val ); }
		}

		$( '#spr-autofill' ).on( 'click', function () {
			fillIfEmpty( 'name', site.name );
			fillIfEmpty( 'url', site.url );
			fillIfEmpty( 'description', site.description );
			fillIfEmpty( 'email', site.email );
			if ( site.logoId && site.logoUrl ) {
				var $logo = $( '.spr-media[data-key="logo_id"]' );
				if ( $logo.length && '' === $.trim( $logo.find( '.spr-media-id' ).val() ) ) {
					$logo.find( '.spr-media-id' ).val( site.logoId );
					$logo.find( '.spr-media-preview' ).html( '<img src="' + site.logoUrl + '" alt="" style="max-width:160px;height:auto" />' );
					$logo.find( '.spr-media-clear' ).show();
				}
			}
			$( '#spr-autofill-status' ).text( i18n.filled || 'Filled — review and Save.' );
		} );

		// ---- Geocode the address into latitude / longitude ----
		$( '#spr-geocode' ).on( 'click', function () {
			var $btn = $( this ),
				$status = $( '#spr-geocode-status' );
			$btn.prop( 'disabled', true );
			$status.css( 'color', '' ).text( i18n.looking || 'Looking up…' );
			$.post( cfg.ajax, {
				action: 'spr_geocode',
				nonce: cfg.geocodeNonce,
				street: $( '#spr-street' ).val(),
				locality: $( '#spr-locality' ).val(),
				region: $( '#spr-region' ).val(),
				postal_code: $( '#spr-postal_code' ).val(),
				country: $( '#spr-country' ).val()
			} ).done( function ( res ) {
				if ( res && res.success && res.data && res.data.lat ) {
					$( '#spr-latitude' ).val( res.data.lat );
					$( '#spr-longitude' ).val( res.data.lon );
					$status.css( 'color', '#0a7c3f' ).text(
						( i18n.matched || 'Matched:' ) + ' ' + ( res.data.display || ( res.data.lat + ', ' + res.data.lon ) )
					);
				} else {
					$status.css( 'color', '#b3261e' ).text(
						( res && res.data && res.data.message ) ? res.data.message : ( i18n.geoFail || 'Lookup failed.' )
					);
				}
			} ).fail( function () {
				$status.css( 'color', '#b3261e' ).text( i18n.geoFail || 'Lookup failed.' );
			} ).always( function () {
				$btn.prop( 'disabled', false );
			} );
		} );
	} );
} )( jQuery );
