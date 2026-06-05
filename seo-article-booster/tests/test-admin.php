<?php
/**
 * Admin smoke tests — render every admin screen and assert no fatals.
 * Exits non-zero on failure.
 *
 * @package SeoArticleBooster\Tests
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

$plugin = sab();
$admin  = $plugin->service( 'admin' );
$admin->register_settings_fields();
SAB_Settings::register();

render( 'dashboard', function () use ( $admin ) { $admin->render_dashboard(); }, 'SEO Article Booster' );
render( 'image audit', function () use ( $admin ) { $admin->render_images(); }, 'Image Audit' );
render( 'schema audit', function () use ( $admin ) { $admin->render_schema(); }, 'Schema Audit' );
render( 'internal links', function () use ( $admin ) { $admin->render_links(); }, 'Internal Links' );
render( 'settings', function () use ( $admin ) { $admin->render_settings(); }, 'Content cleaner' );

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

echo "\n===== $pass passed, $fail failed =====\n";
exit( $fail > 0 ? 1 : 0 );
