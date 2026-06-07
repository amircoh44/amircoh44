<?php
/**
 * Settings view — the registered Settings API fields, split into tabs.
 *
 * Each tab is a settings "page" slug (spr-settings-<tab>); every section was
 * registered under its tab's slug (see SPR_Admin::page_for()). All panels live
 * in one <form>, so the single "Save Changes" button persists every tab at once.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$spr_tabs  = SPR_Admin::settings_tabs();
$spr_first = (string) key( $spr_tabs );
?>
<div class="wrap spr-wrap spr-settings">
	<h1><?php esc_html_e( 'SEO Sprinkler — Settings', 'seo-sprinkler' ); ?></h1>

	<h2 class="nav-tab-wrapper spr-tabs">
		<?php foreach ( $spr_tabs as $spr_slug => $spr_label ) : ?>
			<a href="#<?php echo esc_attr( $spr_slug ); ?>"
				class="nav-tab spr-tab<?php echo ( $spr_slug === $spr_first ) ? ' nav-tab-active' : ''; ?>"
				data-tab="<?php echo esc_attr( $spr_slug ); ?>"><?php echo esc_html( $spr_label ); ?></a>
		<?php endforeach; ?>
	</h2>

	<form action="options.php" method="post">
		<?php settings_fields( SPR_Settings::GROUP ); ?>
		<?php foreach ( $spr_tabs as $spr_slug => $spr_label ) : ?>
			<div class="spr-tab-panel<?php echo ( $spr_slug === $spr_first ) ? ' is-active' : ''; ?>" data-tab="<?php echo esc_attr( $spr_slug ); ?>">
				<?php if ( 'business' === $spr_slug ) : ?>
					<h2><?php esc_html_e( 'Business profile', 'seo-sprinkler' ); ?></h2>
					<p><?php esc_html_e( 'Your business profile (name, address, phone, hours, geo-coordinates, social links) is the single source of truth SEO Sprinkler uses to build your JSON-LD schema graph. It has its own editor with an address auto-fill and a latitude/longitude lookup.', 'seo-sprinkler' ); ?></p>
					<p>
						<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=spr-business' ) ); ?>">
							<span class="dashicons dashicons-id" style="margin-top:4px"></span>
							<?php esc_html_e( 'Open the Business Profile editor', 'seo-sprinkler' ); ?>
						</a>
					</p>
				<?php else : ?>
					<?php do_settings_sections( 'spr-settings-' . $spr_slug ); ?>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
		<?php submit_button(); ?>
	</form>
</div>

<style>
	.spr-settings .spr-tab-panel { display: none; }
	.spr-settings .spr-tab-panel.is-active { display: block; }
	.spr-settings .spr-tab-panel > h2:first-child { margin-top: 1em; }
</style>
<script>
( function () {
	var wrap = document.querySelector( '.spr-settings' );
	if ( ! wrap ) { return; }
	var tabs   = wrap.querySelectorAll( '.spr-tab' );
	var panels = wrap.querySelectorAll( '.spr-tab-panel' );
	var STORE  = 'sprSettingsTab';

	function activate( slug ) {
		var matched = false;
		Array.prototype.forEach.call( panels, function ( p ) {
			var on = p.getAttribute( 'data-tab' ) === slug;
			p.classList.toggle( 'is-active', on );
			if ( on ) { matched = true; }
		} );
		if ( ! matched ) { return; }
		Array.prototype.forEach.call( tabs, function ( t ) {
			t.classList.toggle( 'nav-tab-active', t.getAttribute( 'data-tab' ) === slug );
		} );
		try { window.localStorage.setItem( STORE, slug ); } catch ( e ) {}
	}

	Array.prototype.forEach.call( tabs, function ( t ) {
		t.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			activate( t.getAttribute( 'data-tab' ) );
		} );
	} );

	// Restore the active tab: URL hash first (deep links), else the last one used.
	var initial = ( window.location.hash || '' ).replace( /^#/, '' );
	if ( ! initial ) {
		try { initial = window.localStorage.getItem( STORE ) || ''; } catch ( e ) {}
	}
	if ( initial ) { activate( initial ); }
} )();
</script>
