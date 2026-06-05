/**
 * Dismiss / restore the duplicate-H1 warning from the post editor.
 *
 * @package SeoArticleBooster
 */
( function ( $ ) {
	'use strict';

	var cfg = window.SAB_H1 || {};

	function setIgnore( postId, ignore, done ) {
		$.post( cfg.ajaxUrl, {
			action: 'sab_ignore_h1',
			nonce: cfg.nonce,
			post_id: postId,
			ignore: ignore ? 1 : 0
		} ).done( function ( res ) {
			if ( res && res.success && typeof done === 'function' ) {
				done();
			}
		} );
	}

	$( function () {
		// "Ignore for this post" in the warning notice.
		$( document ).on( 'click', '.sab-h1-ignore', function () {
			var $notice = $( this ).closest( '.sab-h1-notice' ),
				postId  = $notice.data( 'post' );
			setIgnore( postId, true, function () {
				$notice.slideUp( 150 );
			} );
		} );

		// "show warning" link in the Publish box restores it.
		$( document ).on( 'click', '.sab-h1-restore', function ( e ) {
			e.preventDefault();
			var postId = $( this ).closest( '.sab-pub-h1' ).data( 'post' );
			setIgnore( postId, false, function () {
				window.location.reload();
			} );
		} );
	} );
} )( jQuery );
