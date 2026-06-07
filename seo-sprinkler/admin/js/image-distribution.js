/**
 * Image Distribution: find below-minimum articles, then either bulk-fill with
 * images automatically, or review each image one by one (editing alt / caption /
 * title / description before it goes in). Both loops accumulate the attachment
 * IDs used so each article pulls a different image — spreading across the whole
 * media library.
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

	function rowById( id ) {
		return $( '#spr-imgdist-table tbody tr[data-id="' + id + '"]' );
	}

	function updateSelCount() {
		var n = selectedRows().length;
		$( '#spr-imgdist-selcount' ).text( n + ' ' + ( n === 1 ? 'article selected' : 'articles selected' ) );
	}

	function updateRowCount( id, count ) {
		if ( typeof count === 'undefined' || count === null ) {
			return;
		}
		rowById( id ).find( 'td' ).eq( 2 ).find( '.spr-badge' ).text( count );
	}

	/* ---- Scan for below-minimum articles ---- */
	function runScan() {
		var $btn   = $( '#spr-imgdist-scan' ),
			$prog  = $( '#spr-imgdist-progress' ),
			$wrap  = $( '#spr-imgdist-wrap' ),
			$empty = $( '#spr-imgdist-empty' ),
			$saved = $( '#spr-imgdist-saved' ),
			$tbody = $( '#spr-imgdist-table tbody' );

		$btn.prop( 'disabled', true );
		$tbody.empty();
		$saved.remove();
		$wrap.hide();
		$empty.hide();
		$prog.show();
		$prog.find( '.spr-progress__bar > span' ).css( 'width', '0%' );
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

	/* ---- Fill the selected articles automatically (sequential; spreads images) ---- */
	function runFill() {
		var $rows = selectedRows();
		if ( ! $rows.length ) {
			window.alert( i18n.pickSome || 'Select at least one article.' );
			return;
		}
		if ( ! window.confirm( i18n.confirm || 'Insert images into the selected articles?' ) ) {
			return;
		}

		var $btn    = $( '#spr-imgdist-fill' ),
			$review = $( '#spr-imgdist-review' ),
			$scan   = $( '#spr-imgdist-scan' ),
			$prog   = $( '#spr-imgdist-progress' ),
			opts    = options(),
			used    = [],
			list    = $rows.toArray(),
			total   = list.length,
			i       = 0;

		$btn.prop( 'disabled', true );
		$review.prop( 'disabled', true );
		$scan.prop( 'disabled', true );
		$prog.show();
		$prog.find( '.spr-progress__bar > span' ).css( 'width', '0%' );

		function step() {
			if ( i >= total ) {
				$prog.find( '.spr-progress__label' ).text( i18n.done || 'Done.' );
				$btn.prop( 'disabled', false );
				$review.prop( 'disabled', false );
				$scan.prop( 'disabled', false );
				return;
			}
			var $tr  = $( list[ i ] ),
				id   = $tr.data( 'id' ),
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

	/* ---- Remove the images we inserted from the selected articles ---- */
	function runRemove() {
		var $rows = selectedRows();
		if ( ! $rows.length ) {
			window.alert( i18n.pickSome || 'Select at least one article.' );
			return;
		}
		if ( ! window.confirm( i18n.removeConfirm || 'Remove inserted images from the selected articles?' ) ) {
			return;
		}

		var $btn    = $( '#spr-imgdist-remove' ),
			$fill   = $( '#spr-imgdist-fill' ),
			$review = $( '#spr-imgdist-review' ),
			$scan   = $( '#spr-imgdist-scan' ),
			$prog   = $( '#spr-imgdist-progress' ),
			list    = $rows.toArray(),
			total   = list.length,
			i       = 0;

		$btn.prop( 'disabled', true );
		$fill.prop( 'disabled', true );
		$review.prop( 'disabled', true );
		$scan.prop( 'disabled', true );
		$prog.show();
		$prog.find( '.spr-progress__bar > span' ).css( 'width', '0%' );

		function step() {
			if ( i >= total ) {
				$prog.find( '.spr-progress__label' ).text( i18n.done || 'Done.' );
				$btn.prop( 'disabled', false );
				$fill.prop( 'disabled', false );
				$review.prop( 'disabled', false );
				$scan.prop( 'disabled', false );
				return;
			}
			var $tr  = $( list[ i ] ),
				id   = $tr.data( 'id' ),
				$res = $tr.find( '.spr-imgdist-result' );
			$res.text( ( i18n.removing || 'Removing' ) + '…' );

			post( { action: 'spr_imgdist_remove', post_id: id } )
				.done( function ( res ) {
					if ( res && res.success ) {
						var d = res.data || {};
						if ( d.removed > 0 ) {
							$res.html( '<span class="spr-badge spr-badge--neutral">-' + d.removed + '</span>' );
						} else {
							$res.text( i18n.removeNone || 'none' );
						}
						updateRowCount( id, d.count );
					} else {
						$res.text( i18n.error || 'error' );
					}
				} )
				.fail( function () { $res.text( i18n.error || 'error' ); } )
				.always( function () {
					i++;
					$prog.find( '.spr-progress__bar > span' ).css( 'width', Math.round( ( i / total ) * 100 ) + '%' );
					$prog.find( '.spr-progress__label' ).text( ( i18n.removing || 'Removing' ) + ' ' + i + ' / ' + total );
					step();
				} );
		}
		step();
	}

	/* ---- Review each image one by one (edit alt/caption/title/description) ---- */
	var review = {
		queue: [],   // <tr> elements to review
		used: [],    // attachment IDs seen this run (applied or skipped) — keeps it spreading
		pi: 0,       // post index
		post: null,  // { id, title, edit_link, need, proposals }
		qi: 0,       // proposal index within the current post
		inserted: 0, // images inserted into the current post
		all: false,  // approve-all mode
		busy: false
	};

	function setReviewProgress( text ) {
		$( '#spr-review-progress' ).text( text );
	}

	function openReviewModal() {
		$( '.spr-review-lbl-alt' ).text( i18n.altLabel || 'Alt text' );
		$( '.spr-review-lbl-cap' ).text( i18n.capLabel || 'Caption' );
		$( '.spr-review-lbl-ttl' ).text( i18n.ttlLabel || 'Image title' );
		$( '.spr-review-lbl-desc' ).text( i18n.descLabel || 'Image description' );
		$( '#spr-review-approve' ).text( i18n.approve || 'Approve & insert' );
		$( '#spr-review-skip' ).text( i18n.skip || 'Skip' );
		$( '#spr-review-approve-all' ).text( i18n.approveAll || 'Approve all remaining' );
		$( '#spr-review-finish' ).text( i18n.finish || 'Finish' );
		$( 'body' ).addClass( 'spr-modal-open' );
		$( '#spr-review' ).show().attr( 'aria-hidden', 'false' );
	}

	function closeReviewModal() {
		$( 'body' ).removeClass( 'spr-modal-open' );
		$( '#spr-review' ).hide().attr( 'aria-hidden', 'true' );
	}

	function reviewButtons( enabled ) {
		$( '#spr-review-approve, #spr-review-skip, #spr-review-approve-all' ).prop( 'disabled', ! enabled );
		$( '#spr-review .spr-review__spin' ).toggleClass( 'is-active', ! enabled );
	}

	function startReview() {
		var $rows = selectedRows();
		if ( ! $rows.length ) {
			window.alert( i18n.pickSome || 'Select at least one article.' );
			return;
		}
		if ( ! window.confirm( i18n.reviewConfirm || 'Review images one by one?' ) ) {
			return;
		}
		review.queue = $rows.toArray();
		review.used  = [];
		review.pi    = 0;
		review.all   = false;
		review.busy  = false;
		openReviewModal();
		loadReviewPost();
	}

	function loadReviewPost() {
		if ( review.pi >= review.queue.length ) {
			finishReview();
			return;
		}
		var $tr  = $( review.queue[ review.pi ] ),
			id   = $tr.data( 'id' ),
			$res = $tr.find( '.spr-imgdist-result' );
		review.inserted = 0;
		$res.text( ( i18n.reviewing || 'Reviewing' ) + '…' );
		setReviewProgress( ( i18n.reviewing || 'Reviewing' ) + ' ' + ( review.pi + 1 ) + ' / ' + review.queue.length );
		reviewButtons( false );

		post( $.extend( {
			action: 'spr_imgdist_propose',
			post_id: id,
			'exclude[]': review.used
		}, options() ) )
			.done( function ( res ) {
				if ( ! res || ! res.success ) {
					$res.text( i18n.error || 'error' );
					advancePost();
					return;
				}
				var d = res.data || {};
				review.post = {
					id: id,
					title: d.title,
					edit_link: d.edit_link,
					need: d.need,
					proposals: d.proposals || []
				};
				review.qi = 0;
				$( '#spr-review-heading' ).text( d.title || '' );
				if ( ! review.post.proposals.length ) {
					$res.text( review.post.need > 0 ? ( i18n.skipNone || 'no images' ) : ( i18n.skipEnough || 'already had enough' ) );
					advancePost();
					return;
				}
				showProposal();
			} )
			.fail( function () {
				$res.text( i18n.error || 'error' );
				advancePost();
			} );
	}

	function advancePost() {
		review.pi++;
		loadReviewPost();
	}

	function finalizePostRow() {
		if ( ! review.post ) {
			return;
		}
		var $tr  = rowById( review.post.id ),
			$res = $tr.find( '.spr-imgdist-result' );
		if ( review.inserted > 0 ) {
			$res.html( '<span class="spr-badge spr-badge--ok">+' + review.inserted + '</span>' );
			$tr.find( '.spr-badge--warn' ).removeClass( 'spr-badge--warn' ).addClass( 'spr-badge--ok' );
		} else {
			$res.text( i18n.skipped || 'Skipped' );
		}
	}

	function showProposal() {
		if ( ! review.post || review.qi >= review.post.proposals.length ) {
			finalizePostRow();
			advancePost();
			return;
		}
		var p = review.post.proposals[ review.qi ];
		$( '#spr-review-thumb' ).attr( 'src', p.thumb || '' ).attr( 'alt', p.alt || '' );
		$( '#spr-review-alt' ).val( p.alt || '' );
		$( '#spr-review-caption' ).val( p.caption || '' );
		$( '#spr-review-title' ).val( p.title || '' );
		$( '#spr-review-desc' ).val( p.description || '' );
		setReviewProgress(
			( review.post.title || '' ) + ' — ' + ( review.qi + 1 ) + ' / ' + review.post.proposals.length
		);
		reviewButtons( true );
		if ( review.all ) {
			applyProposal();
		}
	}

	function applyProposal() {
		if ( review.busy ) {
			return;
		}
		var p = review.post && review.post.proposals[ review.qi ];
		if ( ! p ) {
			showProposal();
			return;
		}
		review.busy = true;
		reviewButtons( false );

		post( $.extend( {
			action: 'spr_imgdist_apply',
			post_id: review.post.id,
			attachment_id: p.id,
			alt: $( '#spr-review-alt' ).val(),
			caption: $( '#spr-review-caption' ).val(),
			title: $( '#spr-review-title' ).val(),
			description: $( '#spr-review-desc' ).val()
		}, options() ) )
			.done( function ( res ) {
				review.used.push( p.id );
				if ( res && res.success ) {
					var d = res.data || {};
					review.inserted += ( d.inserted || 0 );
					updateRowCount( review.post.id, d.count );
				}
			} )
			.always( function () {
				review.busy = false;
				review.qi++;
				showProposal();
			} );
	}

	function skipProposal() {
		if ( review.busy ) {
			return;
		}
		var p = review.post && review.post.proposals[ review.qi ];
		if ( p ) {
			review.used.push( p.id ); // don't re-show a skipped image this run
		}
		review.qi++;
		showProposal();
	}

	function approveAll() {
		review.all = true;
		applyProposal();
	}

	function finishReview() {
		closeReviewModal();
		$( '#spr-imgdist-progress' ).hide();
		updateSelCount();
	}

	$( function () {
		$( '#spr-imgdist-scan' ).on( 'click', runScan );
		$( '#spr-imgdist-fill' ).on( 'click', runFill );
		$( '#spr-imgdist-review' ).on( 'click', startReview );
		$( '#spr-imgdist-remove' ).on( 'click', runRemove );
		$( '#spr-imgdist-all' ).on( 'change', function () {
			$( '.spr-imgdist-cb' ).prop( 'checked', $( this ).is( ':checked' ) );
			updateSelCount();
		} );
		$( '#spr-imgdist-table' ).on( 'change', '.spr-imgdist-cb', updateSelCount );

		// Reviewer modal controls.
		$( '#spr-review-approve' ).on( 'click', applyProposal );
		$( '#spr-review-skip' ).on( 'click', skipProposal );
		$( '#spr-review-approve-all' ).on( 'click', approveAll );
		$( '#spr-review-finish, #spr-review-close' ).on( 'click', finishReview );
		$( '#spr-review .spr-modal__backdrop' ).on( 'click', finishReview );

		// Saved scan rendered server-side: reflect the current selection count.
		if ( $( '#spr-imgdist-table tbody tr' ).length ) {
			updateSelCount();
		}
	} );
} )( jQuery );
