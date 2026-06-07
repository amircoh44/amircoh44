/**
 * SEO Sprinkler meta box: fill content with images / undo.
 *
 * @package SeoSprinkler
 */
( function ( $ ) {
	'use strict';

	var cfg  = window.SPR_METABOX || {};
	var i18n = cfg.i18n || {};

	function status( msg ) {
		$( '#spr-fill-status' ).text( msg );
	}

	function fill() {
		if ( ! window.confirm( i18n.confirmFill || 'Continue?' ) ) {
			return;
		}
		var $btn = $( this ).prop( 'disabled', true );
		status( i18n.working || 'Working…' );

		$.post( cfg.ajaxUrl, {
			action: 'spr_fill_images',
			nonce: cfg.nonce,
			post_id: $btn.data( 'post' ),
			align: $( '#spr-fill-align' ).val(),
			size: $( '#spr-fill-size' ).val(),
			count: $( '#spr-fill-count' ).val(),
			every: $( '#spr-fill-every' ).val()
		} ).done( function ( res ) {
			if ( res && res.success ) {
				if ( ! res.data.inserted ) {
					status( i18n.none || 'No images found.' );
					$btn.prop( 'disabled', false );
					return;
				}
				status( ( i18n.inserted || 'Inserted %d.' ).replace( '%d', res.data.inserted ) );
				window.location.reload();
			} else {
				status( ( res && res.data && res.data.message ) || i18n.error || 'Error' );
				$btn.prop( 'disabled', false );
			}
		} ).fail( function () {
			status( i18n.error || 'Error' );
			$btn.prop( 'disabled', false );
		} );
	}

	function undo() {
		if ( ! window.confirm( i18n.confirmUndo || 'Revert?' ) ) {
			return;
		}
		var $btn = $( this ).prop( 'disabled', true );
		status( i18n.working || 'Working…' );

		$.post( cfg.ajaxUrl, {
			action: 'spr_remove_images',
			nonce: cfg.nonce,
			post_id: $btn.data( 'post' )
		} ).done( function ( res ) {
			if ( res && res.success ) {
				status( i18n.removed || 'Reverted. Reloading…' );
				window.location.reload();
			} else {
				status( ( res && res.data && res.data.message ) || i18n.error || 'Error' );
				$btn.prop( 'disabled', false );
			}
		} ).fail( function () {
			status( i18n.error || 'Error' );
			$btn.prop( 'disabled', false );
		} );
	}

	function aiGenerate() {
		var $btn = $( this ),
			$res = $( '#spr-ai-result' );
		$( '.spr-ai-go' ).prop( 'disabled', true );
		$res.val( i18n.working || 'Working…' );

		$.post( cfg.ajaxUrl, {
			action: 'spr_ai_generate',
			nonce: cfg.nonce,
			post_id: $btn.data( 'post' ),
			kind: $btn.data( 'kind' )
		} ).done( function ( res ) {
			$res.val( ( res && res.success ) ? res.data.text : ( ( res && res.data && res.data.message ) || i18n.error || 'Error' ) );
		} ).fail( function () {
			$res.val( i18n.error || 'Error' );
		} ).always( function () {
			$( '.spr-ai-go' ).prop( 'disabled', false );
		} );
	}

	$( function () {
		$( document ).on( 'click', '#spr-fill-go', fill );
		$( document ).on( 'click', '#spr-fill-remove', undo );
		$( document ).on( 'click', '.spr-ai-go', aiGenerate );
	} );
} )( jQuery );
