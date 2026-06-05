/**
 * Link Audit admin: batch scan + inline mini-WYSIWYG link editor.
 *
 * @package SeoArticleBooster
 */
( function ( $ ) {
	'use strict';

	var cfg  = window.SAB_LINKS || {};
	var i18n = cfg.i18n || {};
	var MAX  = parseInt( cfg.maxChars, 10 ) || 300;

	function request( action, data ) {
		return $.post( cfg.ajaxUrl, $.extend( { action: action, nonce: cfg.nonce }, data || {} ) );
	}

	/* -----------------------------------------------------------------
	 * List: batch scan
	 * --------------------------------------------------------------- */
	function runScan() {
		var $btn   = $( '#sab-links-scan' ),
			$prog  = $( '#sab-links-progress' ),
			$table = $( '#sab-links-results' ),
			$tbody = $table.find( 'tbody' );

		$btn.prop( 'disabled', true );
		$tbody.empty();
		$table.hide();
		$prog.show();

		function next( paged ) {
			request( 'sab_scan_links', { paged: paged } )
				.done( function ( res ) {
					if ( ! res || ! res.success ) {
						$prog.find( '.sab-progress__label' ).text( i18n.error || 'Error' );
						$btn.prop( 'disabled', false );
						return;
					}
					var d = res.data;
					$.each( d.rows, function ( _, row ) {
						$tbody.append( renderRow( row ) );
					} );
					$table.show();

					var pct = d.total ? Math.round( ( d.scanned / d.total ) * 100 ) : 100;
					$prog.find( '.sab-progress__bar > span' ).css( 'width', pct + '%' );
					$prog.find( '.sab-progress__label' ).text( ( i18n.scanning || 'Scanning…' ) + ' ' + d.scanned + ' / ' + d.total );

					if ( d.done ) {
						$prog.find( '.sab-progress__label' ).text( i18n.done || 'Done.' );
						$btn.prop( 'disabled', false );
						return;
					}
					next( d.next_page );
				} )
				.fail( function () {
					$prog.find( '.sab-progress__label' ).text( i18n.error || 'Error' );
					$btn.prop( 'disabled', false );
				} );
		}
		next( 1 );
	}

	function renderRow( row ) {
		var $tr = $( '<tr/>' );
		$( '<td/>' ).append( $( '<a/>' ).attr( 'href', row.view_link ).text( row.title ) ).appendTo( $tr );
		$( '<td/>' ).html( '<span class="sab-badge ' + ( row.internal > 0 ? 'sab-badge--ok' : 'sab-badge--warn' ) + '">' + row.internal + '</span>' ).appendTo( $tr );
		$( '<td/>' ).html( '<span class="sab-badge sab-badge--neutral">' + row.external + '</span>' ).appendTo( $tr );
		$( '<td/>' ).append( $( '<a class="button button-small"/>' ).attr( 'href', row.view_link ).text( 'View' ) ).appendTo( $tr );
		return $tr;
	}

	/* -----------------------------------------------------------------
	 * Detail: edit modal
	 * --------------------------------------------------------------- */
	var current = { index: null, $row: null };

	function textLen() {
		return ( $( '#sab-wysiwyg' ).text() || '' ).replace( / /g, ' ' ).trim().length;
	}

	function updateCount() {
		var len = textLen();
		$( '#sab-charnow' ).text( len );
		$( '.sab-charcount' ).toggleClass( 'sab-over', len > MAX );
		$( '#sab-link-save' ).prop( 'disabled', len > MAX || 0 === len );
	}

	function openModal( $btn ) {
		current.index = parseInt( $btn.data( 'index' ), 10 );
		current.$row  = $btn.closest( 'tr' );

		$( '#sab-wysiwyg' ).html( $btn.data( 'html' ) || $btn.closest( 'tr' ).find( '.sab-link-text' ).text() );
		$( '#sab-link-url-input' ).val( $btn.data( 'url' ) || '' );
		$( '#sab-modal-error' ).hide().text( '' );
		updateCount();
		$( '#sab-link-modal' ).show();
		$( '#sab-wysiwyg' ).focus();
	}

	function closeModal() {
		$( '#sab-link-modal' ).hide();
		current = { index: null, $row: null };
	}

	function save() {
		var $err = $( '#sab-modal-error' ).hide().text( '' );
		if ( textLen() > MAX ) {
			$err.text( i18n.tooLong || 'Too long.' ).show();
			return;
		}

		var $save     = $( '#sab-link-save' );
		var saveLabel = $save.data( 'label' ) || $save.text();
		$save.data( 'label', saveLabel ).prop( 'disabled', true ).text( i18n.saving || 'Saving…' );

		request( 'sab_save_link', {
			post_id: $( '#sab-links-table' ).data( 'post' ),
			index: current.index,
			url: $( '#sab-link-url-input' ).val(),
			html: $( '#sab-wysiwyg' ).html()
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
					current.$row.find( '.sab-link-text' ).text( d.anchor_text );
					current.$row.find( '.sab-link-url a' ).attr( 'href', d.url ).text( d.url );
					var badge = ( 'internal' === d.type )
						? '<span class="sab-badge sab-badge--ok">' + 'Internal' + '</span>'
						: '<span class="sab-badge sab-badge--neutral">' + 'External' + '</span>';
					current.$row.find( 'td' ).eq( 1 ).html( badge );
					current.$row.find( '.sab-edit-link' ).data( 'url', d.url ).data( 'html', d.anchor_html );
				}

				// Update the totals.
				if ( d.counts ) {
					$( '#sab-counts .sab-int' ).text( 'Internal: ' + d.counts.internal );
					$( '#sab-counts .sab-ext' ).text( 'External: ' + d.counts.external );
				}

				closeModal();
			} )
			.fail( function () {
				$err.text( i18n.error || 'Error' ).show();
				$save.prop( 'disabled', false ).text( saveLabel );
			} );
	}

	$( function () {
		$( '#sab-links-scan' ).on( 'click', runScan );

		// Toolbar formatting (simple WYSIWYG).
		$( '.sab-wysiwyg-toolbar' ).on( 'click', 'button', function ( e ) {
			e.preventDefault();
			document.execCommand( $( this ).data( 'cmd' ), false, null );
			$( '#sab-wysiwyg' ).focus();
			updateCount();
		} );

		$( '#sab-wysiwyg' ).on( 'input keyup', updateCount );

		$( document ).on( 'click', '.sab-edit-link', function () {
			openModal( $( this ) );
		} );
		$( '#sab-link-save' ).on( 'click', save );
		$( '#sab-link-cancel, .sab-modal__backdrop' ).on( 'click', closeModal );
	} );
} )( jQuery );
