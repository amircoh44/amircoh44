/**
 * Export / Migrate: build the media ZIP in batches, then download it.
 *
 * @package SeoArticleBooster
 */
( function ( $ ) {
	'use strict';

	var cfg  = window.SAB_EXPORT || {};
	var i18n = cfg.i18n || {};

	function setProgress( pct, label ) {
		var $p = $( '#sab-export-progress' ).show();
		$p.find( '.sab-progress__bar > span' ).css( 'width', pct + '%' );
		$p.find( '.sab-progress__label' ).text( label );
	}

	/** Collect the export options from the form for AJAX. */
	function formData( extra ) {
		var $form = $( '#sab-export-form' );
		var data  = { nonce: cfg.nonce };
		$.each( $form.serializeArray(), function ( _, f ) {
			if ( 'action' === f.name || 'sab_export_nonce' === f.name ) { return; }
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
		if ( piiSelected() && ! $( '#sab-authorize' ).is( ':checked' ) ) {
			window.alert( i18n.needAuth || 'Please authorise first.' );
			return;
		}

		var $btn = $( this ).prop( 'disabled', true );
		setProgress( 2, i18n.preparing || 'Preparing…' );

		$.post( cfg.ajaxUrl, formData( { action: 'sab_export_zip_start' } ) )
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
					$.post( cfg.ajaxUrl, formData( { action: 'sab_export_zip_batch', token: token } ) )
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
		var url = cfg.downloadUrl + '?action=sab_export_zip_download&token=' + encodeURIComponent( token ) + '&_wpnonce=' + encodeURIComponent( cfg.nonce );
		window.location.href = url;
		$btn.prop( 'disabled', false );
	}

	$( function () {
		$( '#sab-export-zip' ).on( 'click', buildZip );
	} );
} )( jQuery );
