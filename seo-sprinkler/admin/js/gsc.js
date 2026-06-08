/**
 * Search Console: batched index-status check + submit-now.
 *
 * @package SeoSprinkler
 */
( function ( $ ) {
	'use strict';

	var cfg  = window.SPR_GSC || {};
	var i18n = cfg.i18n || {};

	function post( data ) {
		return $.post( cfg.ajaxUrl, $.extend( { nonce: cfg.nonce }, data ) );
	}

	function setProgress( pct, label ) {
		var $p = $( '#spr-gsc-progress' ).show();
		$p.find( '.spr-progress__bar > span' ).css( 'width', Math.min( 100, Math.max( 0, pct ) ) + '%' );
		$p.find( '.spr-progress__label' ).text( label );
	}

	function resultRow( icon, text ) {
		return '<div class="spr-scan-result"><span class="dashicons ' + icon + '"></span> <span class="spr-scan-result__t">' + text + '</span></div>';
	}

	function list( urls ) {
		if ( ! urls || ! urls.length ) { return ''; }
		var items = urls.slice( 0, 25 ).map( function ( u ) {
			return '<li><a href="' + u + '" target="_blank" rel="noopener">' + u + '</a></li>';
		} ).join( '' );
		var more = urls.length > 25 ? '<li>… +' + ( urls.length - 25 ) + '</li>' : '';
		return '<ul class="spr-gsc-urls">' + items + more + '</ul>';
	}

	function runCheck() {
		var $check  = $( '#spr-gsc-check' ),
			$submit = $( '#spr-gsc-submit' ),
			$res    = $( '#spr-gsc-results' ).hide().empty();

		$check.prop( 'disabled', true );
		$submit.prop( 'disabled', true );
		setProgress( 2, i18n.checking || 'Checking…' );

		function next( paged ) {
			post( { action: 'spr_gsc_check', paged: paged } )
				.done( function ( r ) {
					if ( ! r || ! r.success ) {
						setProgress( 100, ( r && r.data && r.data.message ) || i18n.error || 'Error' );
						$check.prop( 'disabled', false );
						$submit.prop( 'disabled', false );
						return;
					}
					var d = r.data || {};
					var pct = d.total ? Math.round( ( d.scanned / d.total ) * 100 ) : 100;
					setProgress( pct, ( i18n.checking || 'Checking…' ) + ' ' + ( d.scanned || 0 ) + ' / ' + ( d.total || 0 ) );

					if ( d.done ) {
						setProgress( 100, i18n.done || 'Done.' );
						var html = ''
							+ resultRow( 'dashicons-warning', ( d.not_indexed ? d.not_indexed.length : 0 ) + ' not indexed — ' + ( d.queued || 0 ) + ' queued (up to ' + ( d.quota || 10 ) + '/day)' )
							+ resultRow( 'dashicons-yes-alt', ( d.added ? d.added.length : 0 ) + ' newly indexed' )
							+ resultRow( 'dashicons-dismiss', ( d.dropped ? d.dropped.length : 0 ) + ' dropped from the index' );
						if ( d.not_indexed && d.not_indexed.length ) { html += '<h4>' + 'Not indexed' + '</h4>' + list( d.not_indexed ); }
						if ( d.dropped && d.dropped.length ) { html += '<h4>' + 'Dropped' + '</h4>' + list( d.dropped ); }
						if ( d.added && d.added.length ) { html += '<h4>' + 'Newly indexed' + '</h4>' + list( d.added ); }
						$res.html( html ).show();
						$( '#spr-gsc-queued' ).text( d.queued || 0 );
						$check.prop( 'disabled', false );
						$submit.prop( 'disabled', false );
						return;
					}
					next( d.next_page );
				} )
				.fail( function () {
					setProgress( 100, i18n.error || 'Error' );
					$check.prop( 'disabled', false );
					$submit.prop( 'disabled', false );
				} );
		}
		next( 1 );
	}

	function runSubmit() {
		var $btn = $( '#spr-gsc-submit' ).prop( 'disabled', true ),
			$res = $( '#spr-gsc-results' );
		setProgress( 30, i18n.submitting || 'Submitting…' );
		post( { action: 'spr_gsc_submit' } )
			.done( function ( r ) {
				if ( r && r.success ) {
					var d = r.data || {};
					setProgress( 100, i18n.done || 'Done.' );
					var msg = ( d.submitted ? d.submitted.length : 0 ) + ' submitted, ' + ( d.remaining || 0 ) + ' still queued';
					$res.show().prepend( resultRow( 'dashicons-upload', msg ) );
					$( '#spr-gsc-queued' ).text( d.remaining || 0 );
				} else {
					setProgress( 100, ( r && r.data && r.data.message ) || i18n.error || 'Error' );
				}
			} )
			.fail( function () { setProgress( 100, i18n.error || 'Error' ); } )
			.always( function () { $btn.prop( 'disabled', false ); } );
	}

	$( function () {
		$( '#spr-gsc-check' ).on( 'click', runCheck );
		$( '#spr-gsc-submit' ).on( 'click', runSubmit );

		// Copy the redirect URI to the clipboard for the one-time setup.
		$( '.spr-copy-btn' ).on( 'click', function () {
			var text = $( this ).data( 'copy' ),
				$msg = $( this ).siblings( '.spr-copied' );
			var done = function () { $msg.show().delay( 1800 ).fadeOut( 300 ); };
			if ( navigator.clipboard && navigator.clipboard.writeText ) {
				navigator.clipboard.writeText( text ).then( done, function () {} );
			} else {
				var $t = $( '<textarea>' ).val( text ).appendTo( 'body' ).select();
				try { document.execCommand( 'copy' ); done(); } catch ( e ) {}
				$t.remove();
			}
		} );
	} );
} )( jQuery );
