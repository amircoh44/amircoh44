<?php
/**
 * Admin smoke tests — render every admin screen and assert no fatals.
 * Exits non-zero on failure.
 *
 * @package SeoSprinkler\Tests
 */

$core = getenv( 'WP_CORE' );
if ( ! $core ) { fwrite( STDERR, "WP_CORE not set\n" ); exit( 1 ); }

define( 'WP_ADMIN', true );
$_SERVER['HTTP_HOST']      = 'localhost:8088';
$_SERVER['REQUEST_URI']    = '/wp-admin/';
$_SERVER['REQUEST_METHOD'] = 'GET';

require $core . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
wp_set_current_user( 1 );

$pass = 0; $fail = 0;
function render( $name, $cb, $needle ) {
	global $pass, $fail;
	try {
		ob_start();
		$cb();
		$out = ob_get_clean();
	} catch ( \Throwable $e ) {
		ob_end_clean();
		$fail++;
		echo "FAIL  $name  (" . $e->getMessage() . ")\n";
		return;
	}
	if ( strlen( $out ) > 50 && ( ! $needle || false !== strpos( $out, $needle ) ) ) {
		$pass++;
		echo 'PASS  ' . $name . '  (' . strlen( $out ) . " bytes)\n";
	} else {
		$fail++;
		echo "FAIL  $name  (len=" . strlen( $out ) . ", needle missing)\n";
	}
}

$plugin = spr();
$admin  = $plugin->service( 'admin' );
$admin->register_settings_fields();
SPR_Settings::register();

render( 'dashboard', function () use ( $admin ) { $admin->render_dashboard(); }, 'SEO Sprinkler' );
render( 'image audit', function () use ( $admin ) { $admin->render_images(); }, 'Image Audit' );
render( 'schema audit', function () use ( $admin ) { $admin->render_schema(); }, 'Schema Audit' );
render( 'internal links', function () use ( $admin ) { $admin->render_links(); }, 'Internal Links' );
render( 'settings', function () use ( $admin ) { $admin->render_settings(); }, 'Content cleaner' );

// Regression: a field title containing tag names (e.g. the cleaner's
// "Stray <script> and <style> blocks") must be HTML-escaped, not echoed as live
// markup — otherwise the injected <script> eats the rest of the page, including
// the Save Changes button.
ob_start();
$admin->render_settings();
$settings_html = ob_get_clean();
foreach ( array(
	'cleaner label is HTML-escaped'              => false !== strpos( $settings_html, 'Stray &lt;script&gt;' ),
	'no raw <script> injected by a field title'  => false === strpos( $settings_html, 'Stray <script>' ),
	'Save button renders after all sections'     => false !== strpos( $settings_html, 'Save Changes' ),
	'settings render as tabs'                    => false !== strpos( $settings_html, 'nav-tab-wrapper' ),
	'a panel exists for every tab'               => substr_count( $settings_html, 'spr-tab-panel' ) >= count( SPR_Admin::settings_tabs() ),
	'sections still render inside tabs'          => false !== strpos( $settings_html, 'AI (bring your own API)' ),
) as $check_name => $cond ) {
	if ( $cond ) { $pass++; echo "PASS  $check_name\n"; } else { $fail++; echo "FAIL  $check_name\n"; }
}

$da = $plugin->service( 'dist_admin' );
$_GET = array();
render( 'distribution list', function () use ( $da ) { $da->render(); }, 'Content Distribution' );
$_GET = array( 'action' => 'new' );
render( 'distribution form', function () use ( $da ) { $da->render(); }, 'Targeting' );

$la  = $plugin->service( 'link_admin' );
$src = get_posts( array( 'post_type' => 'post', 'numberposts' => 1, 'fields' => 'ids' ) );
$_GET = array();
render( 'link audit list', function () use ( $la ) { $la->render(); }, 'Link Audit' );
$_GET = array( 'action' => 'view', 'post' => $src ? $src[0] : 0 );
render( 'link audit detail', function () use ( $la ) { $la->render(); }, 'Links in' );

$ca = $plugin->service( 'cleaner_admin' );
$_GET = array();
render( 'content cleaner', function () use ( $ca ) { $ca->render(); }, 'Change log' );

$ba = $plugin->service( 'business_admin' );
$ba->register_setting();
render( 'business profile', function () use ( $ba ) { $ba->render(); }, 'Business Profile' );

// Geocoder: address -> coordinates (HTTP mocked, no network).
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	if ( false !== strpos( $url, 'nominatim.openstreetmap.org' ) ) {
		return array(
			'response' => array( 'code' => 200 ),
			'headers'  => array(),
			'body'     => wp_json_encode( array( array( 'lat' => '40.7128', 'lon' => '-74.0060', 'display_name' => 'New York, NY, USA' ) ) ),
		);
	}
	return $pre;
}, 10, 3 );
$geo = $ba->geocode( 'Empire State Building, New York' );
foreach ( array(
	'geocode parses latitude'  => ( is_array( $geo ) && abs( $geo['lat'] - 40.7128 ) < 0.001 ),
	'geocode parses longitude' => ( is_array( $geo ) && abs( $geo['lon'] + 74.0060 ) < 0.001 ),
	'geocode returns a label'  => ( is_array( $geo ) && false !== strpos( $geo['display'], 'New York' ) ),
) as $check_name => $cond ) {
	if ( $cond ) { $pass++; echo "PASS  $check_name\n"; } else { $fail++; echo "FAIL  $check_name\n"; }
}

$ea = $plugin->service( 'export_admin' );
render( 'export / migrate', function () use ( $ea ) { $ea->render(); }, 'Export / Migrate' );

echo "\n===== $pass passed, $fail failed =====\n";
exit( $fail > 0 ? 1 : 0 );
