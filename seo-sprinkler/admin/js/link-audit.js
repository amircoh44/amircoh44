/**
 * Link Audit admin: batch scan + inline mini-WYSIWYG link editor.
 *
 * @package SeoSprinkler
 */
( function ( $ ) {
	'use strict';

	var cfg  = window.SPR_LINKS || {};
	var i18n = cfg.i18n || {};
	var MAX  = parseInt( cfg.maxChars, 10 ) || 300;

	function request( action, data ) {
		return $.post( cfg.ajaxUrl, $.extend( { action: action, nonce: cfg.nonce }, data || {} ) );
	}

	/* -----------------------------------------------------------------
	 * List: batch scan
	 * --------------------------------------------------------------- */
	function runScan() {
		var $btn   = $( '#spr-links-scan' ),
			$prog  = $( '#spr-links-progress' ),
			$table = $( '#spr-links-results' ),
			$tbody = $table.find( 'tbody' );

		$btn.prop( 'disabled', true );
		$tbody.empty();
		$table.hide();
		$prog.show();

		function next( paged ) {
			request( 'spr_scan_links', { paged: paged } )
				.done( function ( res ) {
					if ( ! res || ! res.success ) {
						$prog.find( '.spr-progress__label' ).text( i18n.error || 'Error' );
						$btn.prop( 'disabled', false );
						return;
					}
					var d = res.data;
					$.each( d.rows, function ( _, row ) {
						$tbody.append( renderRow( row ) );
					} );
					$table.show();

					var pct = d.total ? Math.round( ( d.scanned / d.total ) * 100 ) : 100;
					$prog.find( '.spr-progress__bar > span' ).css( 'width', pct + '%' );
					$prog.find( '.spr-progress__label' ).text( ( i18n.scanning || 'Scanning…' ) + ' ' + d.scanned + ' / ' + d.total );

					if ( d.done ) {
						$prog.find( '.spr-progress__label' ).text( i18n.done || 'Done.' );
						$( '#spr-links-updated' ).text( i18n.justScanned || '' );
						sortRows( 'internal', 'asc' ); // Weakest articles on top.
						$btn.prop( 'disabled', false );
						return;
					}
					next( d.next_page );
				} )
				.fail( function () {
					$prog.find( '.spr-progress__label' ).text( i18n.error || 'Error' );
					$btn.prop( 'disabled', false );
				} );
		}
		next( 1 );
	}

	function renderRow( row ) {
		var $tr = $( '<tr/>' ).attr( {
			'data-internal': row.internal | 0,
			'data-external': row.external | 0,
			'data-title': ( row.title || '' ).toLowerCase()
		} );
		$( '<td/>' ).append( $( '<a/>' ).attr( 'href', row.view_link ).text( row.title ) ).appendTo( $tr );
		$( '<td/>' ).html( '<span class="spr-badge ' + ( row.internal > 0 ? 'spr-badge--ok' : 'spr-badge--warn' ) + '">' + row.internal + '</span>' ).appendTo( $tr );
		$( '<td/>' ).html( '<span class="spr-badge spr-badge--neutral">' + row.external + '</span>' ).appendTo( $tr );
		$( '<td/>' ).append( $( '<a class="button button-small"/>' ).attr( 'href', row.view_link ).text( 'View' ) ).appendTo( $tr );
		return $tr;
	}

	/* Sort the results table by a column. Numeric for internal/external, text for
	 * title. Default (and post-scan) order is internal ascending — weakest on top. */
	function sortRows( key, dir ) {
		var $tbody = $( '#spr-links-results tbody' );
		var rows   = $tbody.children( 'tr' ).get();
		rows.sort( function ( a, b ) {
			var av, bv;
			if ( 'title' === key ) {
				av = $( a ).attr( 'data-title' ) || '';
				bv = $( b ).attr( 'data-title' ) || '';
				return 'asc' === dir ? av.localeCompare( bv ) : bv.localeCompare( av );
			}
			av = parseInt( $( a ).attr( 'data-' + key ), 10 ) || 0;
			bv = parseInt( $( b ).attr( 'data-' + key ), 10 ) || 0;
			return 'asc' === dir ? av - bv : bv - av;
		} );
		$.each( rows, function ( _, r ) { $tbody.append( r ); } );

		$( '#spr-links-results thead .spr-sort' )
			.removeClass( 'spr-sort--active spr-sort--asc spr-sort--desc' )
			.filter( '[data-key="' + key + '"]' )
			.addClass( 'spr-sort--active spr-sort--' + dir );
	}

	/* -----------------------------------------------------------------
	 * Detail: edit modal
	 * --------------------------------------------------------------- */
	var current = { index: null, $row: null };

	function textLen() {
		return ( $( '#spr-wysiwyg' ).text() || '' ).replace( / /g, ' ' ).trim().length;
	}

	function updateCount() {
		var len = textLen();
		$( '#spr-charnow' ).text( len );
		$( '.spr-charcount' ).toggleClass( 'spr-over', len > MAX );
		$( '#spr-link-save' ).prop( 'disabled', len > MAX || 0 === len );
	}

	function openModal( $btn ) {
		current.index = parseInt( $btn.data( 'index' ), 10 );
		current.$row  = $btn.closest( 'tr' );

		$( '#spr-wysiwyg' ).html( $btn.data( 'html' ) || $btn.closest( 'tr' ).find( '.spr-link-text' ).text() );
		$( '#spr-link-url-input' ).val( $btn.data( 'url' ) || '' );
		$( '#spr-modal-error' ).hide().text( '' );
		updateCount();
		$( '#spr-link-modal' ).show();
		$( '#spr-wysiwyg' ).focus();
	}

	function closeModal() {
		$( '#spr-link-modal' ).hide();
		current = { index: null, $row: null };
	}

	function save() {
		var $err = $( '#spr-modal-error' ).hide().text( '' );
		if ( textLen() > MAX ) {
			$err.text( i18n.tooLong || 'Too long.' ).show();
			return;
		}

		var $save     = $( '#spr-link-save' );
		var saveLabel = $save.data( 'label' ) || $save.text();
		$save.data( 'label', saveLabel ).prop( 'disabled', true ).text( i18n.saving || 'Saving…' );

		request( 'spr_save_link', {
			post_id: $( '#spr-links-table' ).data( 'post' ),
			index: current.index,
			url: $( '#spr-link-url-input' ).val(),
			html: $( '#spr-wysiwyg' ).html()
		} )
			.done( function ( res ) {
				if ( ! res || ! res.success ) {
					$err.text( ( res && res.data && res.data.message ) || i18n.error || 'Error' ).show();
					$save.prop( 'disabled', false ).text( saveLabel );
					return;
				}
				var d = res.data;

				// Update the row in place.
				if ( current.$row ) {
					current.$row.find( '.spr-link-text' ).text( d.anchor_text );
					current.$row.find( '.spr-link-url a' ).attr( 'href', d.url ).text( d.url );
					var badge = ( 'internal' === d.type )
						? '<span class="spr-badge spr-badge--ok">' + 'Internal' + '</span>'
						: '<span class="spr-badge spr-badge--neutral">' + 'External' + '</span>';
					current.$row.find( 'td' ).eq( 1 ).html( badge );
					current.$row.find( '.spr-edit-link' ).data( 'url', d.url ).data( 'html', d.anchor_html );
				}

				// Update the totals.
				if ( d.counts ) {
					$( '#spr-counts .spr-int' ).text( 'Internal: ' + d.counts.internal );
					$( '#spr-counts .spr-ext' ).text( 'External: ' + d.counts.external );
				}

				closeModal();
			} )
			.fail( function () {
				$err.text( i18n.error || 'Error' ).show();
				$save.prop( 'disabled', false ).text( saveLabel );
			} );
	}

	$( function () {
		$( '#spr-links-scan' ).on( 'click', runScan );

		// Click a column header to sort; toggles asc/desc.
		$( '#spr-links-results' ).on( 'click', '.spr-sort', function () {
			var key = $( this ).data( 'key' ),
				dir = $( this ).hasClass( 'spr-sort--active' ) && $( this ).hasClass( 'spr-sort--asc' ) ? 'desc' : 'asc';
			sortRows( key, dir );
		} );

		// Toolbar formatting (simple WYSIWYG).
		$( '.spr-wysiwyg-toolbar' ).on( 'click', 'button', function ( e ) {
			e.preventDefault();
			document.execCommand( $( this ).data( 'cmd' ), false, null );
			$( '#spr-wysiwyg' ).focus();
			updateCount();
		} );

		$( '#spr-wysiwyg' ).on( 'input keyup', updateCount );

		$( document ).on( 'click', '.spr-edit-link', function () {
			openModal( $( this ) );
		} );
		$( '#spr-link-save' ).on( 'click', save );
		$( '#spr-link-cancel, .spr-modal__backdrop' ).on( 'click', closeModal );
	} );
} )( jQuery );
