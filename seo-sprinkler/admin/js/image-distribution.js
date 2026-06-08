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
			format: $( '#spr-format' ).val() || 'auto',
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
	function runFill( iconsMode ) {
		var $rows = selectedRows();
		if ( ! $rows.length ) {
			window.alert( i18n.pickSome || 'Select at least one article.' );
			return;
		}
		var confirmMsg = iconsMode ? ( i18n.iconsConfirm || 'Sprinkle icons into the selected articles?' ) : ( i18n.confirm || 'Insert images into the selected articles?' );
		if ( ! window.confirm( confirmMsg ) ) {
			return;
		}

		var $all    = $( '#spr-imgdist-fill, #spr-imgdist-review, #spr-imgdist-review-all, #spr-imgdist-icons, #spr-imgdist-remove, #spr-imgdist-scan' ),
			$prog   = $( '#spr-imgdist-progress' ),
			opts    = options(),
			used    = [],
			list    = $rows.toArray(),
			total   = list.length,
			i       = 0;
		if ( iconsMode ) {
			opts.icons = 1;
		}

		$all.prop( 'disabled', true );
		$prog.show();
		$prog.find( '.spr-progress__bar > span' ).css( 'width', '0%' );

		function step() {
			if ( i >= total ) {
				$prog.find( '.spr-progress__label' ).text( i18n.done || 'Done.' );
				$all.prop( 'disabled', false );
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
							updateRowCount( id, d.count ); // Reflect the new image count.
						} else if ( 'builder' === d.skipped ) {
							$res.text( i18n.skipBuilder || 'page builder — skipped' );
						} else if ( 'no_images' === d.skipped ) {
							$res.text( iconsMode ? ( i18n.noIcons || 'no icons' ) : ( i18n.skipNone || 'no images' ) );
						} else if ( 'enough' === d.skipped ) {
							$res.text( ( i18n.skipEnough || 'already has' ) + ' ' + ( d.count || '' ) + ( d.target ? ' / ' + d.target : '' ) );
						} else {
							$res.text( '—' );
						}
						used = used.concat( d.used || [] );
					} else {
						$res.text( ( res && res.data && res.data.message ) ? res.data.message : ( i18n.error || 'error' ) );
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

	/* ---- Sprinkle context-matched icons (placement chosen by the user) ---- */
	function runIcons() {
		var $rows = selectedRows();
		if ( ! $rows.length ) {
			window.alert( i18n.pickSome || 'Select at least one article.' );
			return;
		}
		if ( ! window.confirm( i18n.iconsConfirm || 'Sprinkle icons into the selected articles?' ) ) {
			return;
		}
		var placement = $( '#spr-icon-placement' ).val() || 'headings',
			$all      = $( '#spr-imgdist-fill, #spr-imgdist-review, #spr-imgdist-review-all, #spr-imgdist-icons, #spr-imgdist-remove, #spr-imgdist-scan' ),
			$prog     = $( '#spr-imgdist-progress' ),
			used      = [],
			list      = $rows.toArray(),
			total     = list.length,
			i         = 0;

		$all.prop( 'disabled', true );
		$prog.show();
		$prog.find( '.spr-progress__bar > span' ).css( 'width', '0%' );

		function step() {
			if ( i >= total ) {
				$prog.find( '.spr-progress__label' ).text( i18n.done || 'Done.' );
				$all.prop( 'disabled', false );
				return;
			}
			var $tr  = $( list[ i ] ),
				id   = $tr.data( 'id' ),
				$res = $tr.find( '.spr-imgdist-result' );
			$res.text( ( i18n.sprinkling || 'Sprinkling icons' ) + '…' );

			post( { action: 'spr_imgdist_icons', post_id: id, placement: placement, 'exclude[]': used } )
				.done( function ( res ) {
					if ( res && res.success ) {
						var d = res.data || {};
						if ( d.inserted > 0 ) {
							$res.html( '<span class="spr-badge spr-badge--ok">+' + d.inserted + '</span>' );
						} else if ( 'builder' === d.skipped ) {
							$res.text( i18n.skipBuilder || 'page builder — skipped' );
						} else if ( 'no_icons' === d.skipped ) {
							$res.text( i18n.noIcons || 'no icons' );
						} else if ( 'no_points' === d.skipped || 'no_match' === d.skipped ) {
							$res.text( i18n.iconsNoPoints || 'nowhere to place' );
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
					$prog.find( '.spr-progress__label' ).text( ( i18n.sprinkling || 'Sprinkling icons' ) + ' ' + i + ' / ' + total );
					step();
				} );
		}
		step();
	}

	/* ---- Preview & edit ALL proposed images, then insert them in one go ---- */
	function disableBars( on ) {
		$( '#spr-imgdist-fill, #spr-imgdist-review, #spr-imgdist-review-all, #spr-imgdist-remove, #spr-imgdist-scan' ).prop( 'disabled', on );
	}

	function raField( labelText, value, cls, isArea ) {
		var $p = $( '<p/>' );
		$( '<label/>' ).text( labelText ).appendTo( $p );
		var $input = isArea ? $( '<textarea rows="2" class="widefat"/>' ) : $( '<input type="text" class="widefat"/>' );
		$input.addClass( cls ).val( value || '' ).appendTo( $p );
		return $p;
	}

	function buildRaCard( postId, p ) {
		var $card = $( '<div class="spr-ra-card"/>' ).attr( { 'data-post': postId, 'data-att': p.id } );

		var $thumb = $( '<div class="spr-ra-card__thumb"/>' );
		$( '<img/>' ).attr( 'src', p.thumb || '' ).attr( 'alt', p.alt || '' ).appendTo( $thumb );
		var $skip = $( '<label class="spr-ra-skip"/>' );
		$( '<input type="checkbox" class="spr-ra-skipcb"/>' ).appendTo( $skip );
		$skip.append( document.createTextNode( ' ' + ( i18n.skip || 'Skip' ) ) );
		$( '<div/>' ).append( $skip ).appendTo( $thumb );
		// Per-image alignment (defaults to the global choice above).
		var globalAlign = $( '#spr-align' ).val() || 'center';
		var $align = $( '<select class="spr-ra-align"/>' );
		$.each( [ [ 'center', i18n.alignCenter || 'Middle' ], [ 'left', i18n.alignLeft || 'Left' ], [ 'right', i18n.alignRight || 'Right' ] ], function ( _, o ) {
			$( '<option/>' ).val( o[0] ).text( o[1] ).prop( 'selected', o[0] === globalAlign ).appendTo( $align );
		} );
		$( '<div class="spr-ra-alignwrap"/>' ).append( $align ).appendTo( $thumb );
		$card.append( $thumb );

		var $f = $( '<div class="spr-ra-card__fields"/>' );
		$f.append( raField( i18n.altLabel || 'Alt text', p.alt, 'spr-ra-alt' ) );
		$f.append( raField( i18n.capLabel || 'Caption', p.caption, 'spr-ra-cap' ) );
		$f.append( raField( i18n.ttlLabel || 'Image title', p.title, 'spr-ra-ttl' ) );
		$f.append( raField( i18n.descLabel || 'Image description', p.description, 'spr-ra-desc', true ) );
		$card.append( $f );
		return $card;
	}

	function buildRaGroup( postId, title, proposals ) {
		var $g = $( '<div class="spr-ra-group"/>' ).attr( 'data-post', postId );
		$( '<div class="spr-ra-group__title"/>' )
			.append( $( '<span class="dashicons dashicons-media-document"></span>' ) )
			.append( document.createTextNode( ' ' + ( title || '' ) ) )
			.appendTo( $g );
		$.each( proposals, function ( _, p ) { $g.append( buildRaCard( postId, p ) ); } );
		return $g;
	}

	function activeRaCards() {
		return $( '#spr-review-all-list .spr-ra-card' ).filter( function () {
			return ! $( this ).find( '.spr-ra-skipcb' ).is( ':checked' );
		} );
	}

	function updateRaCount() {
		var n = activeRaCards().length;
		$( '#spr-review-all-count' ).text( n + ' ' + ( i18n.selected || 'to insert' ) );
		$( '#spr-review-all-insert' ).prop( 'disabled', n === 0 );
	}

	function startReviewAll() {
		var $rows = selectedRows();
		if ( ! $rows.length ) {
			window.alert( i18n.pickSome || 'Select at least one article.' );
			return;
		}
		if ( ! window.confirm( i18n.reviewAllConfirm || 'Build an editable preview of every image for the selected articles?' ) ) {
			return;
		}

		var $panel = $( '#spr-review-all' ),
			$list  = $( '#spr-review-all-list' ).empty(),
			$empty = $( '#spr-review-all-empty' ).hide(),
			$prog  = $( '#spr-imgdist-progress' ),
			ids    = $rows.toArray().map( function ( tr ) { return $( tr ).data( 'id' ); } ),
			used   = [],
			opts   = options(),
			i      = 0,
			any    = false;

		disableBars( true );
		$panel.hide();
		$prog.show();
		$prog.find( '.spr-progress__bar > span' ).css( 'width', '0%' );
		$prog.find( '.spr-progress__label' ).text( i18n.gathering || 'Preparing images…' );

		function nextPost() {
			if ( i >= ids.length ) {
				$prog.hide();
				disableBars( false );
				if ( ! any ) {
					$empty.text( i18n.raEmpty || 'Nothing to insert.' ).show();
				}
				$panel.show();
				updateRaCount();
				if ( $panel.offset() ) {
					$( 'html, body' ).animate( { scrollTop: $panel.offset().top - 40 }, 250 );
				}
				return;
			}
			var id = ids[ i ];
			post( $.extend( { action: 'spr_imgdist_propose', post_id: id, 'exclude[]': used }, opts ) )
				.done( function ( res ) {
					if ( res && res.success ) {
						var d = res.data || {};
						if ( ( d.proposals || [] ).length ) {
							any = true;
							$list.append( buildRaGroup( id, d.title, d.proposals ) );
							$.each( d.proposals, function ( _, p ) { used.push( p.id ); } );
						}
					}
				} )
				.always( function () {
					i++;
					$prog.find( '.spr-progress__bar > span' ).css( 'width', Math.round( ( i / ids.length ) * 100 ) + '%' );
					nextPost();
				} );
		}
		nextPost();
	}

	function insertReviewedAll() {
		var cards = activeRaCards().toArray();
		if ( ! cards.length ) {
			return;
		}
		var $prog   = $( '#spr-imgdist-progress' ),
			opts    = options(),
			total   = cards.length,
			i       = 0,
			perPost = {};

		disableBars( true );
		$( '#spr-review-all-insert, #spr-review-all-cancel' ).prop( 'disabled', true );
		$prog.show();
		$prog.find( '.spr-progress__bar > span' ).css( 'width', '0%' );
		$prog.find( '.spr-progress__label' ).text( ( i18n.insertingAll || 'Inserting' ) + ' 0 / ' + total );

		function step() {
			if ( i >= cards.length ) {
				$prog.find( '.spr-progress__label' ).text( i18n.done || 'Done.' );
				$.each( perPost, function ( pid, n ) {
					var $tr = rowById( pid );
					$tr.find( '.spr-imgdist-result' ).html( '<span class="spr-badge spr-badge--ok">+' + n + '</span>' );
					$tr.find( '.spr-badge--warn' ).removeClass( 'spr-badge--warn' ).addClass( 'spr-badge--ok' );
				} );
				$( '#spr-review-all' ).hide();
				disableBars( false );
				return;
			}
			var $card = $( cards[ i ] ).css( 'opacity', 0.45 ),
				pid   = $card.data( 'post' ),
				att   = $card.data( 'att' );

			post( $.extend( {}, opts, {
				action: 'spr_imgdist_apply',
				post_id: pid,
				attachment_id: att,
				align: $card.find( '.spr-ra-align' ).val() || opts.align,
				alt: $card.find( '.spr-ra-alt' ).val(),
				caption: $card.find( '.spr-ra-cap' ).val(),
				title: $card.find( '.spr-ra-ttl' ).val(),
				description: $card.find( '.spr-ra-desc' ).val()
			} ) )
				.done( function ( res ) {
					if ( res && res.success ) {
						var d = res.data || {};
						perPost[ pid ] = ( perPost[ pid ] || 0 ) + ( d.inserted || 0 );
						updateRowCount( pid, d.count );
					}
				} )
				.always( function () {
					i++;
					$prog.find( '.spr-progress__bar > span' ).css( 'width', Math.round( ( i / total ) * 100 ) + '%' );
					$prog.find( '.spr-progress__label' ).text( ( i18n.insertingAll || 'Inserting' ) + ' ' + i + ' / ' + total );
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
		$( '#spr-imgdist-fill' ).on( 'click', function () { runFill( false ); } );
		$( '#spr-imgdist-icons' ).on( 'click', runIcons );
		$( '#spr-imgdist-review' ).on( 'click', startReview );
		$( '#spr-imgdist-review-all' ).on( 'click', startReviewAll );
		$( '#spr-imgdist-remove' ).on( 'click', runRemove );

		// Bulk "preview & edit all" controls.
		$( '#spr-review-all' ).on( 'change', '.spr-ra-skipcb', function () {
			$( this ).closest( '.spr-ra-card' ).toggleClass( 'is-skipped', $( this ).is( ':checked' ) );
			updateRaCount();
		} );
		$( '#spr-review-all-insert' ).on( 'click', insertReviewedAll );
		$( '#spr-review-all-cancel' ).on( 'click', function () { $( '#spr-review-all' ).hide(); } );
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
