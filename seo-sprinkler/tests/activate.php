<?php
/**
 * Activate the plugin and fail loudly on any activation error.
 *
 * @package SeoSprinkler\Tests
 */

$core = getenv( 'WP_CORE' );
if ( ! $core || ! file_exists( $core . '/wp-load.php' ) ) {
	fwrite( STDERR, "WP_CORE is not set or wp-load.php is missing.\n" );
	exit( 1 );
}

define( 'WP_ADMIN', true );
$_SERVER['HTTP_HOST']   = 'localhost:8088';
$_SERVER['REQUEST_URI'] = '/wp-admin/';

require $core . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$plugin = 'seo-sprinkler/seo-sprinkler.php';
$result = activate_plugin( $plugin );

if ( is_wp_error( $result ) ) {
	fwrite( STDERR, 'ACTIVATION ERROR: ' . $result->get_error_message() . "\n" );
	exit( 1 );
}

if ( ! is_plugin_active( $plugin ) ) {
	fwrite( STDERR, "Plugin did not activate.\n" );
	exit( 1 );
}

echo "Plugin activated cleanly.\n";
