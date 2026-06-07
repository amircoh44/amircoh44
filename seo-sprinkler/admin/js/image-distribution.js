/**
 * Image Distribution: find below-minimum articles, then bulk-fill with images.
 * The fill loop accumulates the attachment IDs used so each article pulls a
 * different image — spreading across the whole library.
 *
 * @package SeoSprinkler
 */
( function ( $ ) {
	'use strict';

	var cfg  = window.SPR_IMGDIST || {};
	var i18n = cfg.i18n || {};

	function post( data ) {
		return $.post( cfg.ajaxUrl, $.extend( { nonce: cfg.nonce }, data ) );
	}

	function options() {
		var altMode = $( 'input[name="spr-alt"]:checked' ).val() || $( '#spr-alt-fixed' ).val() || 'auto';
		return {
			mode: $( 'input[name="spr-mode"]:checked' ).val() || 'per_article',
			target: $( '#spr-target' ).val(),
			per_words: $( '#spr-per-words' ).val(),
			align: $( '#spr-align' ).val(),
			size: $( '#spr-size' ).val(),
			alt_mode: altMode
		};
	}

	function selectedRows() {
		return $( '#spr-imgdist-table tbody tr' ).filter( function () {
			return $( this ).find( '.spr-imgdist-cb' ).is( ':checked' );
		} );
	}

	function updateSelCount() {
		var n = selectedRows().length;
		$( '#spr-imgdist-selcount' ).text( n + ' ' + ( n === 1 ? 'article selected' : 'articles selected' ) );
	}

	/* ---- Scan for below-minimum articles ---- */
	function runScan() {
		var $btn   = $( '#spr-imgdist-scan' ),
			$prog  = $( '#spr-imgdist-progress' ),
			$wrap  = $( '#spr-imgdist-wrap' ),
			$empty = $( '#spr-imgdist-empty' ),
			$tbody = $( '#spr-imgdist-table tbody' );

		$btn.prop( 'disabled', true );
		$tbody.empty();
		$wrap.hide();
		$empty.hide();
		$prog.show();
		$prog.find( '.spr-progress__label' ).text( i18n.scanning || 'Scanning…' );

		function next( paged ) {
			post( { action: 'spr_imgdist_scan', paged: paged } )
				.done( function ( res ) {
					if ( ! res || ! res.success ) {
						$prog.find( '.spr-progress__label' ).text( i18n.error || 'Error' );
						$btn.prop( 'disabled', false );
						return;
					}
					var d = res.data;
					$.each( d.deficient || [], function ( _, row ) {
						$tbody.append( buildRow( row ) );
					} );

					var pct = d.total ? Math.round( ( d.scanned / d.total ) * 100 ) : 100;
					$prog.find( '.spr-progress__bar > span' ).css( 'width', pct + '%' );

					if ( d.done ) {
						$prog.hide();
						$btn.prop( 'disabled', false );
						if ( $tbody.children().length ) {
							$wrap.show();
							$( '#spr-imgdist-all' ).prop( 'checked', true );
							updateSelCount();
						} else {
							$empty.text( i18n.noneFound || 'Nothing to fill.' ).show();
						}
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

	function buildRow( row ) {
		var $tr = $( '<tr/>' ).attr( 'data-id', row.id );
		$( '<td/>' ).append(
			$( '<input type="checkbox" class="spr-imgdist-cb" checked/>' )
		).appendTo( $tr );
		var $title = row.edit_link ? $( '<a/>' ).attr( 'href', row.edit_link ).attr( 'target', '_blank' ).text( row.title )
			: $( '<span/>' ).text( row.title );
		$( '<td/>' ).append( $title ).appendTo( $tr );
		$( '<td/>' ).html( '<span class="spr-badge spr-badge--warn">' + row.count + '</span>' ).appendTo( $tr );
		$( '<td class="spr-imgdist-result"/>' ).text( '—' ).appendTo( $tr );
		return $tr;
	}

	/* ---- Fill the selected articles (sequential; spreads images) ---- */
	function runFill() {
		var $rows = selectedRows();
		if ( ! $rows.length ) {
			window.alert( i18n.pickSome || 'Select at least one article.' );
			return;
		}
		if ( ! window.confirm( i18n.confirm || 'Insert images into the selected articles?' ) ) {
			return;
		}

		var $btn  = $( '#spr-imgdist-fill' ),
			$scan = $( '#spr-imgdist-scan' ),
			$prog = $( '#spr-imgdist-progress' ),
			opts  = options(),
			used  = [],
			list  = $rows.toArray(),
			total = list.length,
			i     = 0;

		$btn.prop( 'disabled', true );
		$scan.prop( 'disabled', true );
		$prog.show();
		$prog.find( '.spr-progress__bar > span' ).css( 'width', '0%' );

		function step() {
			if ( i >= total ) {
				$prog.find( '.spr-progress__label' ).text( i18n.done || 'Done.' );
				$btn.prop( 'disabled', false );
				$scan.prop( 'disabled', false );
				return;
			}
			var $tr = $( list[ i ] ),
				id  = $tr.data( 'id' ),
				$res = $tr.find( '.spr-imgdist-result' );
			$res.text( ( i18n.filling || 'Filling' ) + '…' );

			post( $.extend( {
				action: 'spr_imgdist_fill',
				post_id: id,
				'exclude[]': used
			}, opts ) )
				.done( function ( res ) {
					if ( res && res.success ) {
						var d = res.data || {};
						if ( d.inserted > 0 ) {
							$res.html( '<span class="spr-badge spr-badge--ok">+' + d.inserted + '</span>' );
							$tr.find( '.spr-badge--warn' ).removeClass( 'spr-badge--warn' ).addClass( 'spr-badge--ok' );
						} else if ( 'no_images' === d.skipped ) {
							$res.text( i18n.skipNone || 'no images' );
						} else if ( 'enough' === d.skipped ) {
							$res.text( i18n.skipEnough || 'enough' );
						} else {
							$res.text( '—' );
						}
						used = used.concat( d.used || [] );
					} else {
						$res.text( i18n.error || 'error' );
					}
				} )
				.fail( function () { $res.text( i18n.error || 'error' ); } )
				.always( function () {
					i++;
					$prog.find( '.spr-progress__bar > span' ).css( 'width', Math.round( ( i / total ) * 100 ) + '%' );
					$prog.find( '.spr-progress__label' ).text( ( i18n.filling || 'Filling' ) + ' ' + i + ' / ' + total );
					step();
				} );
		}
		step();
	}

	$( function () {
		$( '#spr-imgdist-scan' ).on( 'click', runScan );
		$( '#spr-imgdist-fill' ).on( 'click', runFill );
		$( '#spr-imgdist-all' ).on( 'change', function () {
			$( '.spr-imgdist-cb' ).prop( 'checked', $( this ).is( ':checked' ) );
			updateSelCount();
		} );
		$( '#spr-imgdist-table' ).on( 'change', '.spr-imgdist-cb', updateSelCount );
	} );
} )( jQuery );
