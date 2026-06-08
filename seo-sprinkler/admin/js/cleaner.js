/**
 * Content Cleaner admin: batched scan, clean-all, clean one by one (with
 * per-article exclusion) and per-post revert.
 *
 * @package SeoSprinkler
 */
( function ( $ ) {
	'use strict';

	var cfg  = window.SPR_CLEAN || {};
	var i18n = cfg.i18n || {};

	function request( action, data ) {
		return $.post( cfg.ajaxUrl, $.extend( { action: action, nonce: cfg.nonce }, data || {} ) );
	}

	function setProgress( pct, label ) {
		var $p = $( '#spr-junk-progress' ).show();
		$p.find( '.spr-progress__bar > span' ).css( 'width', pct + '%' );
		$p.find( '.spr-progress__label' ).text( label );
	}

	function selectedCleanRows() {
		return $( '#spr-junk-results tbody tr' ).filter( function () {
			return $( this ).find( '.spr-junk-cb' ).is( ':checked' );
		} );
	}

	function updateSelCount() {
		var n = selectedCleanRows().length;
		$( '#spr-junk-selcount' ).text( n + ' ' + ( i18n.selected || 'selected' ) );
		$( '#spr-junk-clean-sel' ).prop( 'disabled', n === 0 );
	}

	/* Build one scan-result row (checkbox + article + junk + per-row Clean). */
	function buildScanRow( row ) {
		var $tr = $( '<tr/>' ).attr( 'data-post', row.id );
		$( '<td/>' ).append( $( '<input type="checkbox" class="spr-junk-cb" checked/>' ) ).appendTo( $tr );
		$( '<td/>' ).append( $( '<a/>' ).attr( 'href', row.edit_link || '#' ).text( row.title ) ).appendTo( $tr );
		$( '<td/>' ).text( row.issues ).appendTo( $tr );
		var $act = $( '<td/>' );
		$( '<button type="button" class="button button-small spr-clean-one"/>' )
			.attr( 'data-post', row.id )
			.text( i18n.cleanLabel || 'Clean' )
			.appendTo( $act );
		$act.append( ' ' );
		$( '<span class="spr-junk-res"/>' ).appendTo( $act );
		$act.appendTo( $tr );
		return $tr;
	}

	/* Batched scan, or clean-all (server-side over every flagged post). */
	function runBatch( mode ) {
		if ( 'clean' === mode && ! window.confirm( i18n.confirmClean || 'Continue?' ) ) {
			return;
		}

		var action  = ( 'clean' === mode ) ? 'spr_clean_junk' : 'spr_scan_junk',
			busy    = ( 'clean' === mode ) ? ( i18n.cleaning || 'Cleaning…' ) : ( i18n.scanning || 'Scanning…' ),
			$btns   = $( '#spr-junk-scan, #spr-junk-clean, #spr-junk-clean-sel' ),
			$table  = $( '#spr-junk-results' ),
			$tbody  = $table.find( 'tbody' ),
			$empty  = $( '#spr-junk-empty' ),
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
						if ( res && res.data && res.data.message ) {
							window.alert( res.data.message );
						}
						return;
					}
					var d = res.data;

					if ( 'scan' === mode && d.rows ) {
						$.each( d.rows, function ( _, row ) {
							found++;
							$tbody.append( buildScanRow( row ) );
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
							$( '#spr-junk-all' ).prop( 'checked', true );
							updateSelCount();
						}
						$btns.prop( 'disabled', false );
						updateSelCount();
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

	/* Clean a set of scanned rows one by one (honours the checkboxes/exclusions). */
	function cleanRows( list ) {
		if ( ! list.length ) {
			return;
		}
		var $btns = $( '#spr-junk-scan, #spr-junk-clean, #spr-junk-clean-sel' ),
			total = list.length,
			i     = 0,
			done  = 0,
			blocked = false;

		$btns.prop( 'disabled', true );
		setProgress( 2, i18n.cleaning || 'Cleaning…' );

		function step() {
			if ( blocked || i >= total ) {
				setProgress( 100, ( i18n.done || 'Done.' ) + ' ' + done + ' cleaned.' );
				$btns.prop( 'disabled', false );
				updateSelCount();
				return;
			}
			var $row = $( list[ i ] ),
				id   = $row.data( 'post' ),
				$res = $row.find( '.spr-junk-res' );
			$res.text( i18n.cleaning || 'Cleaning…' );

			request( 'spr_clean_one', { post_id: id } )
				.done( function ( res ) {
					if ( res && res.success ) {
						var d = res.data || {};
						if ( d.changed ) {
							done++;
							$res.html( '<span class="spr-badge spr-badge--ok">' + ( i18n.cleanedTag || 'Cleaned' ) + ' −' + ( d.removed || 0 ) + '</span>' );
						} else {
							$res.text( i18n.cleanNone || 'Already clean' );
						}
						$row.find( '.spr-clean-one' ).remove();
						$row.find( '.spr-junk-cb' ).prop( 'checked', false ).prop( 'disabled', true );
					} else {
						$res.text( i18n.error || 'Error' );
						if ( res && res.data && res.data.upgrade ) {
							blocked = true; // Edition gate — stop the run.
							window.alert( ( res.data && res.data.message ) || i18n.error || 'Error' );
						}
					}
				} )
				.fail( function () { $res.text( i18n.error || 'Error' ); } )
				.always( function () {
					i++;
					setProgress( Math.round( ( i / total ) * 100 ), ( i18n.cleaning || 'Cleaning…' ) + ' ' + i + ' / ' + total );
					step();
				} );
		}
		step();
	}

	$( function () {
		$( '#spr-junk-scan' ).on( 'click', function () { runBatch( 'scan' ); } );
		$( '#spr-junk-clean' ).on( 'click', function () { runBatch( 'clean' ); } );

		$( '#spr-junk-clean-sel' ).on( 'click', function () {
			var $rows = selectedCleanRows();
			if ( ! $rows.length ) {
				window.alert( i18n.pickSome || 'Select at least one article.' );
				return;
			}
			if ( ! window.confirm( i18n.confirmCleanSel || 'Clean the selected articles?' ) ) {
				return;
			}
			cleanRows( $rows.toArray() );
		} );

		// Per-row "Clean" (one by one).
		$( '#spr-junk-results' ).on( 'click', '.spr-clean-one', function () {
			cleanRows( [ $( this ).closest( 'tr' )[ 0 ] ] );
		} );

		// Selection + exclusion controls.
		$( '#spr-junk-results' ).on( 'change', '.spr-junk-cb', updateSelCount );
		$( '#spr-junk-all' ).on( 'change', function () {
			$( '.spr-junk-cb:not(:disabled)' ).prop( 'checked', $( this ).is( ':checked' ) );
			updateSelCount();
		} );

		// Per-post revert from the log.
		$( '#spr-clean-log' ).on( 'click', '.spr-revert', function () {
			if ( ! window.confirm( i18n.confirmRevert || 'Revert?' ) ) {
				return;
			}
			var $btn = $( this ).prop( 'disabled', true ),
				$row = $btn.closest( 'tr' );

			request( 'spr_revert_clean', { post_id: $btn.data( 'post' ) } )
				.done( function ( res ) {
					if ( res && res.success ) {
						$row.find( '.spr-log-status' ).html( '<span class="spr-badge spr-badge--neutral">' + ( i18n.reverted || 'Reverted' ) + '</span>' );
						$row.find( '.spr-log-action' ).empty();
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
