/**
 * Admin scripts for SEO Sprinkler.
 *
 * Drives the batched AJAX jobs (image scan, link apply/revert) and the one-shot
 * actions (rebuild index, refresh sitemap). All requests carry the shared nonce
 * exposed via the localized `SPR` object.
 *
 * @package SeoSprinkler
 */
( function ( $ ) {
	'use strict';

	var i18n = ( window.SPR && SPR.i18n ) || {};

	/**
	 * Fire a plugin AJAX action.
	 *
	 * @param {string} action  The wp_ajax action suffix (without the spr_ namespace handled server-side).
	 * @param {Object} data    Extra POST data.
	 * @return {jqXHR}
	 */
	function request( action, data ) {
		return $.post(
			SPR.ajaxUrl,
			$.extend( { action: action, nonce: SPR.nonce }, data || {} )
		);
	}

	/**
	 * Update a progress bar + label.
	 *
	 * @param {jQuery} $wrap   Progress wrapper.
	 * @param {number} percent 0-100.
	 * @param {string} label   Status text.
	 */
	function setProgress( $wrap, percent, label ) {
		$wrap.show();
		$wrap.find( '.spr-progress__bar > span' ).css( 'width', Math.min( 100, Math.max( 0, percent ) ) + '%' );
		$wrap.find( '.spr-progress__label' ).text( label );
	}

	/* -----------------------------------------------------------------
	 * Image audit scan (batched)
	 * --------------------------------------------------------------- */
	function runScan() {
		var $btn      = $( '#spr-scan-start' ),
			$progress = $( '#spr-scan-progress' ),
			$table    = $( '#spr-deficient-table' ),
			$tbody    = $table.find( 'tbody' ),
			$empty    = $( '#spr-scan-empty' ),
			deficient = 0;

		$btn.prop( 'disabled', true );
		$tbody.empty();
		$table.hide();
		$empty.hide();
		setProgress( $progress, 2, i18n.scanning || 'Scanning…' );

		function nextPage( paged ) {
			request( 'spr_scan_images', { paged: paged } )
				.done( function ( res ) {
					if ( ! res || ! res.success ) {
						setProgress( $progress, 100, i18n.error || 'Error' );
						$btn.prop( 'disabled', false );
						return;
					}

					var d = res.data;

					// Append any deficient rows from this batch.
					if ( d.deficient && d.deficient.length ) {
						$.each( d.deficient, function ( _, post ) {
							deficient++;
							$tbody.append( renderDeficientRow( post, d.min ) );
						} );
						$table.show();
					}

					var percent = d.total ? Math.round( ( d.scanned / d.total ) * 100 ) : 100;
					setProgress(
						$progress,
						percent,
						( i18n.scanning || 'Scanning…' ) + ' ' + d.scanned + ' / ' + d.total
					);

					if ( d.done ) {
						setProgress( $progress, 100, ( i18n.done || 'Done.' ) + ' ' + deficient + ' flagged.' );
						$btn.prop( 'disabled', false );
						if ( 0 === deficient ) {
							$empty.text( i18n.noDeficient || 'All articles meet the minimum.' ).show();
						}
						return;
					}

					nextPage( d.next_page );
				} )
				.fail( function () {
					setProgress( $progress, 100, i18n.error || 'Error' );
					$btn.prop( 'disabled', false );
				} );
		}

		nextPage( 1 );
	}

	/**
	 * Build a table row for a deficient post.
	 *
	 * @param {Object} post Post payload.
	 * @param {number} min  Required minimum.
	 * @return {jQuery}
	 */
	function renderDeficientRow( post, min ) {
		var $tr = $( '<tr/>' );

		$( '<td/>' ).append(
			$( '<strong/>' ).text( post.title )
		).appendTo( $tr );

		$( '<td/>' ).html(
			'<span class="spr-badge spr-badge--warn">' + post.count + ' / ' + min + '</span>'
		).appendTo( $tr );

		buildActions( post ).appendTo( $tr );

		return $tr;
	}

	/**
	 * Build an Edit/View actions cell shared by the audit tables.
	 *
	 * @param {Object} post Post payload with edit_link/view_link.
	 * @return {jQuery}
	 */
	function buildActions( post, withTest ) {
		var $actions = $( '<td/>' );
		if ( post.edit_link ) {
			$( '<a class="button button-small"/>' ).attr( 'href', post.edit_link ).text( i18n.edit || 'Edit' ).appendTo( $actions );
		}
		if ( post.view_link ) {
			$actions.append( ' ' );
			$( '<a class="button button-small" target="_blank" rel="noopener"/>' ).attr( 'href', post.view_link ).text( i18n.view || 'View' ).appendTo( $actions );
		}
		if ( withTest && post.view_link ) {
			$actions.append( ' ' );
			$( '<a class="button button-small" target="_blank" rel="noopener" title="Google Rich Results Test"/>' )
				.attr( 'href', 'https://search.google.com/test/rich-results?url=' + encodeURIComponent( post.view_link ) )
				.text( i18n.testGoogle || 'Test on Google' )
				.appendTo( $actions );
		}
		return $actions;
	}

	/* -----------------------------------------------------------------
	 * Schema audit scan (batched)
	 * --------------------------------------------------------------- */
	function runSchemaScan() {
		var $btn      = $( '#spr-schema-start' ),
			$progress = $( '#spr-schema-progress' ),
			$table    = $( '#spr-schema-table' ),
			$tbody    = $table.find( 'tbody' ),
			$empty    = $( '#spr-schema-empty' ),
			flagged   = 0;

		$btn.prop( 'disabled', true );
		$tbody.empty();
		$table.hide();
		$empty.hide();
		setProgress( $progress, 2, i18n.schemaScanning || 'Checking schema…' );

		function nextPage( paged ) {
			request( 'spr_scan_schema', { paged: paged } )
				.done( function ( res ) {
					if ( ! res || ! res.success ) {
						setProgress( $progress, 100, i18n.error || 'Error' );
						$btn.prop( 'disabled', false );
						return;
					}

					var d = res.data;

					if ( d.missing && d.missing.length ) {
						$.each( d.missing, function ( _, post ) {
							flagged++;
							$tbody.append( renderSchemaRow( post ) );
						} );
						$table.show();
					}

					var percent = d.total ? Math.round( ( d.scanned / d.total ) * 100 ) : 100;
					setProgress(
						$progress,
						percent,
						( i18n.schemaScanning || 'Checking schema…' ) + ' ' + d.scanned + ' / ' + d.total
					);

					if ( d.done ) {
						setProgress( $progress, 100, ( i18n.done || 'Done.' ) + ' ' + flagged + ' ' + ( i18n.flagged || 'flagged' ) + '.' );
						$btn.prop( 'disabled', false );
						if ( 0 === flagged ) {
							$empty.text( i18n.noMissingSchema || 'All articles expose structured data.' ).show();
						}
						return;
					}

					nextPage( d.next_page );
				} )
				.fail( function () {
					setProgress( $progress, 100, i18n.error || 'Error' );
					$btn.prop( 'disabled', false );
				} );
		}

		nextPage( 1 );
	}

	/**
	 * Build a table row for a post flagged by the schema scan.
	 *
	 * @param {Object} post Post payload.
	 * @return {jQuery}
	 */
	function renderSchemaRow( post ) {
		var $tr = $( '<tr/>' );

		$( '<td/>' ).append( $( '<strong/>' ).text( post.title || '(no title)' ) ).appendTo( $tr );

		var label, cls;
		if ( 'error' === post.status ) {
			label = ( i18n.statusError || 'Could not fetch' ) + ( post.note ? ' (' + post.note + ')' : '' );
			cls   = 'spr-badge--neutral';
		} else if ( 'insufficient' === post.status ) {
			label = ( i18n.statusInsufficient || 'Missing required type' ) + ( post.types && post.types.length ? ': ' + post.types.join( ', ' ) : '' );
			cls   = 'spr-badge--warn';
		} else {
			label = i18n.statusNone || 'No structured data';
			cls   = 'spr-badge--warn';
		}

		$( '<td/>' ).html( '<span class="spr-badge ' + cls + '"></span>' ).find( 'span' ).text( label ).end().appendTo( $tr );

		buildActions( post, true ).appendTo( $tr );

		return $tr;
	}

	/* -----------------------------------------------------------------
	 * Sitemap coverage: schema scan across all sitemap URLs (batched)
	 * --------------------------------------------------------------- */
	function runSitemapSchemaScan() {
		var $btn      = $( '#spr-sitemap-schema-start' ),
			$progress = $( '#spr-sitemap-schema-progress' ),
			$table    = $( '#spr-sitemap-schema-table' ),
			$tbody    = $table.find( 'tbody' ),
			$empty    = $( '#spr-sitemap-schema-empty' ),
			missing   = 0;

		$btn.prop( 'disabled', true );
		$tbody.empty();
		$table.hide();
		$empty.hide();
		setProgress( $progress, 2, i18n.schemaScanning || 'Checking schema…' );

		function nextPage( paged ) {
			request( 'spr_scan_sitemap_schema', { paged: paged } )
				.done( function ( res ) {
					if ( ! res || ! res.success ) {
						setProgress( $progress, 100, i18n.error || 'Error' );
						$btn.prop( 'disabled', false );
						return;
					}
					var d = res.data;
					$.each( d.rows || [], function ( _, row ) {
						if ( 'ok' !== row.status ) { missing++; }
						$tbody.append( renderSitemapSchemaRow( row ) );
					} );
					if ( d.rows && d.rows.length ) { $table.show(); }

					var percent = d.total ? Math.round( ( d.scanned / d.total ) * 100 ) : 100;
					setProgress( $progress, percent, ( i18n.schemaScanning || 'Checking…' ) + ' ' + d.scanned + ' / ' + d.total );

					if ( d.done ) {
						setProgress( $progress, 100, ( i18n.done || 'Done.' ) + ' ' + missing + ' ' + ( i18n.flagged || 'flagged' ) + '.' );
						$btn.prop( 'disabled', false );
						if ( d.total === 0 ) {
							$empty.text( i18n.noSitemapUrls || 'No URLs were found in the sitemap(s).' ).show();
						}
						return;
					}
					nextPage( d.next_page );
				} )
				.fail( function () {
					setProgress( $progress, 100, i18n.error || 'Error' );
					$btn.prop( 'disabled', false );
				} );
		}
		nextPage( 1 );
	}

	function renderSitemapSchemaRow( row ) {
		var $tr = $( '<tr/>' );
		$( '<td/>' ).html(
			$( '<a/>' ).attr( { href: row.url, target: '_blank', rel: 'noopener' } ).text( row.url )
		).appendTo( $tr );

		var label, cls;
		if ( 'ok' === row.status ) {
			label = ( row.types && row.types.length ) ? row.types.join( ', ' ) : ( i18n.schemaOk || 'OK' );
			cls   = 'spr-badge--ok';
		} else if ( 'error' === row.status ) {
			label = ( i18n.statusError || 'Could not fetch' ) + ( row.note ? ' (' + row.note + ')' : '' );
			cls   = 'spr-badge--neutral';
		} else if ( 'insufficient' === row.status ) {
			label = ( i18n.statusInsufficient || 'Missing required type' ) + ( row.types && row.types.length ? ': ' + row.types.join( ', ' ) : '' );
			cls   = 'spr-badge--warn';
		} else {
			label = i18n.statusNone || 'No structured data';
			cls   = 'spr-badge--warn';
		}
		$( '<td/>' ).html( '<span class="spr-badge ' + cls + '"></span>' ).find( 'span' ).text( label ).end().appendTo( $tr );
		return $tr;
	}

	/* -----------------------------------------------------------------
	 * Rebuild index / refresh sitemap (one-shot)
	 * --------------------------------------------------------------- */
	function oneShot( action, $btn, busyText ) {
		var $status = $( '#spr-index-status' );
		$btn.prop( 'disabled', true );
		$status.text( busyText || i18n.working || 'Working…' );

		request( action )
			.done( function ( res ) {
				if ( res && res.success ) {
					$status.text( res.data.message );
				} else {
					$status.text( ( res && res.data && res.data.message ) || i18n.error || 'Error' );
				}
			} )
			.fail( function () {
				$status.text( i18n.error || 'Error' );
			} )
			.always( function () {
				$btn.prop( 'disabled', false );
			} );
	}

	/* -----------------------------------------------------------------
	 * Apply / revert permanent links (batched)
	 * --------------------------------------------------------------- */
	function runApply( action, confirmMsg, busyText ) {
		if ( confirmMsg && ! window.confirm( confirmMsg ) ) {
			return;
		}

		var $progress = $( '#spr-apply-progress' ),
			$buttons  = $( '#spr-apply-links, #spr-revert-links' ),
			totalEdited = 0,
			totalLinks  = 0;

		$buttons.prop( 'disabled', true );
		setProgress( $progress, 2, busyText || i18n.working || 'Working…' );

		function nextPage( paged ) {
			request( action, { paged: paged } )
				.done( function ( res ) {
					if ( ! res || ! res.success ) {
						setProgress( $progress, 100, i18n.error || 'Error' );
						$buttons.prop( 'disabled', false );
						return;
					}

					var d = res.data;
					totalEdited += ( d.updated || 0 );
					totalLinks  += ( d.links || d.removed || 0 );

					var percent = d.total ? Math.round( ( d.scanned / d.total ) * 100 ) : 100;
					setProgress(
						$progress,
						percent,
						( busyText || '' ) + ' ' + d.scanned + ' / ' + d.total
					);

					if ( d.done ) {
						setProgress(
							$progress,
							100,
							( i18n.done || 'Done.' ) + ' ' + totalEdited + ' posts updated, ' + totalLinks + ' links.'
						);
						$buttons.prop( 'disabled', false );
						return;
					}

					nextPage( d.next_page );
				} )
				.fail( function () {
					setProgress( $progress, 100, i18n.error || 'Error' );
					$buttons.prop( 'disabled', false );
				} );
		}

		nextPage( 1 );
	}

	/* -----------------------------------------------------------------
	 * Wire up event handlers.
	 * --------------------------------------------------------------- */
	$( function () {
		$( '#spr-scan-start' ).on( 'click', runScan );
		$( '#spr-schema-start' ).on( 'click', runSchemaScan );
		$( '#spr-sitemap-schema-start' ).on( 'click', runSitemapSchemaScan );

		$( '#spr-rebuild-index' ).on( 'click', function () {
			oneShot( 'spr_rebuild_index', $( this ), i18n.working );
		} );

		$( '#spr-refresh-sitemap' ).on( 'click', function () {
			oneShot( 'spr_refresh_sitemap', $( this ), i18n.working );
		} );

		$( '#spr-apply-links' ).on( 'click', function () {
			runApply( 'spr_apply_links', i18n.confirmApply, i18n.applying );
		} );

		$( '#spr-revert-links' ).on( 'click', function () {
			runApply( 'spr_revert_links', i18n.confirmRevert, i18n.reverting );
		} );
	} );
} )( jQuery );
