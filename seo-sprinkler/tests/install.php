<?php
/**
 * Programmatically install WordPress (used by the smoke-test bootstrap).
 *
 * Expects the WP_CORE environment variable to point at a WordPress core
 * directory that already has a wp-config.php (with the SQLite drop-in).
 *
 * @package SeoSprinkler\Tests
 */

$core = getenv( 'WP_CORE' );
if ( ! $core || ! file_exists( $core . '/wp-load.php' ) ) {
	fwrite( STDERR, "WP_CORE is not set or wp-load.php is missing.\n" );
	exit( 1 );
}

define( 'WP_INSTALLING', true );
$_SERVER['HTTP_HOST']       = 'localhost:8088';
$_SERVER['REQUEST_URI']     = '/';
$_SERVER['REQUEST_METHOD']  = 'GET';

require $core . '/wp-load.php';
require ABSPATH . 'wp-admin/includes/upgrade.php';

if ( is_blog_installed() ) {
	echo "WordPress already installed.\n";
	exit( 0 );
}

$result = wp_install( 'SPR CI', 'admin', 'admin@example.com', true, '', 'password' );
echo 'Installed WordPress; admin user_id=' . $result['user_id'] . "\n";
