/**
 * Content Cleaner admin: batched scan/clean + per-post revert.
 *
 * @package SeoArticleBooster
 */
( function ( $ ) {
	'use strict';

	var cfg  = window.SAB_CLEAN || {};
	var i18n = cfg.i18n || {};

	function request( action, data ) {
		return $.post( cfg.ajaxUrl, $.extend( { action: action, nonce: cfg.nonce }, data || {} ) );
	}

	function setProgress( pct, label ) {
		var $p = $( '#sab-junk-progress' ).show();
		$p.find( '.sab-progress__bar > span' ).css( 'width', pct + '%' );
		$p.find( '.sab-progress__label' ).text( label );
	}

	/* Batched scan or clean. `mode` = 'scan' | 'clean'. */
	function runBatch( mode ) {
		if ( 'clean' === mode && ! window.confirm( i18n.confirmClean || 'Continue?' ) ) {
			return;
		}

		var action  = ( 'clean' === mode ) ? 'sab_clean_junk' : 'sab_scan_junk',
			busy    = ( 'clean' === mode ) ? ( i18n.cleaning || 'Cleaning…' ) : ( i18n.scanning || 'Scanning…' ),
			$btns   = $( '#sab-junk-scan, #sab-junk-clean' ),
			$table  = $( '#sab-junk-results' ),
			$tbody  = $table.find( 'tbody' ),
			$empty  = $( '#sab-junk-empty' ),
			found   = 0,
			cleaned = 0;

		$btns.prop( 'disabled', true );
		$tbody.empty();
		$table.hide();
		$empty.hide();
		setProgress( 2, busy );

		function next( paged ) {
			request( action, { paged: paged } )
				.done( function ( res ) {
					if ( ! res || ! res.success ) {
						setProgress( 100, i18n.error || 'Error' );
						$btns.prop( 'disabled', false );
						return;
					}
					var d = res.data;

					if ( 'scan' === mode && d.rows ) {
						$.each( d.rows, function ( _, row ) {
							found++;
							var $tr = $( '<tr/>' );
							$( '<td/>' ).append( $( '<a/>' ).attr( 'href', row.edit_link || '#' ).text( row.title ) ).appendTo( $tr );
							$( '<td/>' ).text( row.issues ).appendTo( $tr );
							$tbody.append( $tr );
						} );
						if ( found ) {
							$table.show();
						}
					}
					if ( 'clean' === mode ) {
						cleaned += ( d.cleaned || 0 );
					}

					var pct = d.total ? Math.round( ( d.scanned / d.total ) * 100 ) : 100;
					setProgress( pct, busy + ' ' + d.scanned + ' / ' + d.total );

					if ( d.done ) {
						if ( 'clean' === mode ) {
							setProgress( 100, ( i18n.done || 'Done.' ) + ' ' + cleaned + ' cleaned.' );
							if ( cleaned > 0 ) {
								window.location.reload(); // Refresh the log.
								return;
							}
						} else {
							setProgress( 100, i18n.done || 'Done.' );
							if ( 0 === found ) {
								$empty.text( i18n.noIssues || 'No junk found.' ).show();
							}
						}
						$btns.prop( 'disabled', false );
						return;
					}
					next( d.next_page );
				} )
				.fail( function () {
					setProgress( 100, i18n.error || 'Error' );
					$btns.prop( 'disabled', false );
				} );
		}
		next( 1 );
	}

	$( function () {
		$( '#sab-junk-scan' ).on( 'click', function () { runBatch( 'scan' ); } );
		$( '#sab-junk-clean' ).on( 'click', function () { runBatch( 'clean' ); } );

		// Per-post revert from the log.
		$( '#sab-clean-log' ).on( 'click', '.sab-revert', function () {
			if ( ! window.confirm( i18n.confirmRevert || 'Revert?' ) ) {
				return;
			}
			var $btn = $( this ).prop( 'disabled', true ),
				$row = $btn.closest( 'tr' );

			request( 'sab_revert_clean', { post_id: $btn.data( 'post' ) } )
				.done( function ( res ) {
					if ( res && res.success ) {
						$row.find( '.sab-log-status' ).html( '<span class="sab-badge sab-badge--neutral">' + ( i18n.reverted || 'Reverted' ) + '</span>' );
						$row.find( '.sab-log-action' ).empty();
					} else {
						$btn.prop( 'disabled', false );
						window.alert( ( res && res.data && res.data.message ) || i18n.error || 'Error' );
					}
				} )
				.fail( function () {
					$btn.prop( 'disabled', false );
					window.alert( i18n.error || 'Error' );
				} );
		} );
	} );
} )( jQuery );
