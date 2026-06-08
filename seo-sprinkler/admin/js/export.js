/**
 * Export / Migrate: build the media ZIP in batches, then download it.
 *
 * @package SeoSprinkler
 */
( function ( $ ) {
	'use strict';

	var cfg  = window.SPR_EXPORT || {};
	var i18n = cfg.i18n || {};

	function setProgress( pct, label ) {
		var $p = $( '#spr-export-progress' ).show();
		$p.find( '.spr-progress__bar > span' ).css( 'width', pct + '%' );
		$p.find( '.spr-progress__label' ).text( label );
	}

	/** Collect the export options from the form for AJAX. */
	function formData( extra ) {
		var $form = $( '#spr-export-form' );
		var data  = { nonce: cfg.nonce };
		$.each( $form.serializeArray(), function ( _, f ) {
			if ( 'action' === f.name || 'spr_export_nonce' === f.name ) { return; }
			// Keep array fields (post_types[]) intact.
			if ( /\[\]$/.test( f.name ) ) {
				data[ f.name ] = data[ f.name ] || [];
				data[ f.name ].push( f.value );
			} else {
				data[ f.name ] = f.value;
			}
		} );
		return $.extend( data, extra || {} );
	}

	function piiSelected() {
		return $( 'input[name="include_users"]' ).is( ':checked' )
			|| $( 'input[name="include_emails"]' ).is( ':checked' )
			|| $( 'input[name="include_all_options"]' ).is( ':checked' );
	}

	function buildZip() {
		if ( piiSelected() && ! $( '#spr-authorize' ).is( ':checked' ) ) {
			window.alert( i18n.needAuth || 'Please authorise first.' );
			return;
		}

		var $btn = $( this ).prop( 'disabled', true );
		setProgress( 2, i18n.preparing || 'Preparing…' );

		$.post( cfg.ajaxUrl, formData( { action: 'spr_export_zip_start' } ) )
			.done( function ( res ) {
				if ( ! res || ! res.success ) {
					setProgress( 100, ( res && res.data && res.data.message ) || i18n.error || 'Error' );
					$btn.prop( 'disabled', false );
					return;
				}
				var token = res.data.token,
					total = res.data.total;

				if ( 0 === total ) {
					finish( token, $btn );
					return;
				}

				function batch() {
					$.post( cfg.ajaxUrl, formData( { action: 'spr_export_zip_batch', token: token } ) )
						.done( function ( r ) {
							if ( ! r || ! r.success ) {
								setProgress( 100, ( r && r.data && r.data.message ) || i18n.error || 'Error' );
								$btn.prop( 'disabled', false );
								return;
							}
							var pct = r.data.total ? Math.round( ( r.data.scanned / r.data.total ) * 100 ) : 100;
							setProgress( pct, ( i18n.adding || 'Adding media…' ) + ' ' + r.data.scanned + ' / ' + r.data.total );
							if ( r.data.done ) {
								finish( token, $btn );
							} else {
								batch();
							}
						} )
						.fail( function () {
							setProgress( 100, i18n.error || 'Error' );
							$btn.prop( 'disabled', false );
						} );
				}
				batch();
			} )
			.fail( function () {
				setProgress( 100, i18n.error || 'Error' );
				$btn.prop( 'disabled', false );
			} );
	}

	function finish( token, $btn ) {
		setProgress( 100, i18n.ready || 'ZIP ready — downloading…' );
		var url = cfg.downloadUrl + '?action=spr_export_zip_download&token=' + encodeURIComponent( token ) + '&_wpnonce=' + encodeURIComponent( cfg.nonce );
		window.location.href = url;
		$btn.prop( 'disabled', false );
	}

	function updateWarning() {
		$( '#spr-export-warning' ).toggle( piiSelected() );
	}

	$( function () {
		$( '#spr-export-zip' ).on( 'click', buildZip );
		// The two submit buttons share one form — set the action they post to.
		$( '#spr-export-json-btn' ).on( 'click', function () { $( '#spr-export-action' ).val( 'spr_export_json' ); } );
		$( '#spr-export-files-btn' ).on( 'click', function () { $( '#spr-export-action' ).val( 'spr_export_files' ); } );
		$( 'input[name="include_users"], input[name="include_emails"], input[name="include_all_options"]' ).on( 'change', updateWarning );
		updateWarning();
	} );
} )( jQuery );
