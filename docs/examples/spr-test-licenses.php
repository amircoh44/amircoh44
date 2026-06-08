<?php
/**
 * Plugin Name: SEO Sprinkler — Test Licences (DEV ONLY)
 * Description: Validates a couple of hard-coded keys offline so you can test the
 *              License key field without deploying the licence server. Delete this
 *              file when you are done — it is a developer bypass, not for production.
 *              Use the BUNDLED build (pinned builds ignore keys, since the constant wins).
 *
 * Install: copy to  wp-content/mu-plugins/spr-test-licenses.php  (auto-loads; no activation)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'spr_validate_license', function ( $edition, $key ) {
	$test_keys = array(
		'SPR-5C53-5JZ3-7PUG-559B' => 'expert',
		'SPR-2E86-9KDW-XDL3-ULHN' => 'pro',
	);
	$key = strtoupper( trim( (string) $key ) );
	return isset( $test_keys[ $key ] ) ? $test_keys[ $key ] : $edition;
}, 10, 2 );
