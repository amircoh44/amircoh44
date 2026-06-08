<?php
/**
 * License client tests — resolving keys against a (mocked) license server.
 * HTTP is intercepted with the pre_http_request filter; no network is used.
 *
 * @package SeoSprinkler\Tests
 */

$core = getenv( 'WP_CORE' );
if ( ! $core ) { fwrite( STDERR, "WP_CORE not set\n" ); exit( 1 ); }
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_URI'] = '/';
require $core . '/wp-load.php';

$pass = 0; $fail = 0;
function check( $name, $cond ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS  $name\n"; }
	else { $fail++; echo "FAIL  $name\n"; }
}
function set_setting( $key, $value ) {
	$s = (array) get_option( 'spr_settings' );
	$s[ $key ] = $value;
	update_option( 'spr_settings', $s );
	SPR_Settings::flush_cache();
}

// Mock every license-server call.
$mock = array( 'edition' => 'pro', 'error' => false );
add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( &$mock ) {
	if ( false !== strpos( $url, '/api/v1/' ) ) {
		if ( $mock['error'] ) {
			return new WP_Error( 'http_request_failed', 'server down' );
		}
		return array(
			'response' => array( 'code' => 200 ),
			'headers'  => array(),
			'body'     => wp_json_encode( array( 'success' => true, 'edition' => $mock['edition'] ) ),
		);
	}
	return $pre;
}, 10, 3 );

$client = spr()->service( 'license' );
check( 'license client is wired into the plugin', $client instanceof SPR_License_Client );

// No server configured -> entered key cannot resolve, stays free.
set_setting( 'license_server_url', '' );
SPR_Edition::activate_key( 'SPR-NO-SERVER' );
check( 'no server configured => free', 'free' === get_option( 'spr_edition' ) );

// With a server, a valid key resolves to its edition.
set_setting( 'license_server_url', 'https://license.test' );
$mock['edition'] = 'pro';
SPR_Edition::activate_key( 'SPR-VALID-KEY' );
check( 'valid key => pro', 'pro' === SPR_Edition::current() );

$mock['edition'] = 'expert';
SPR_Edition::activate_key( 'SPR-EXPERT-KEY' );
check( 'expert key => expert', 'expert' === SPR_Edition::current() );

// Server reports free (expired/revoked/unknown) -> downgrade on activate.
$mock['edition'] = 'free';
SPR_Edition::activate_key( 'SPR-EXPIRED' );
check( 'server says free => free', 'free' === SPR_Edition::current() );

// Daily re-validation reflects server-side changes.
set_setting( 'license_key', 'SPR-STORED-KEY' );
update_option( 'spr_edition', 'expert' );
$mock['edition'] = 'free';
$client->revalidate();
check( 'revalidate downgrades expert -> free', 'free' === get_option( 'spr_edition' ) );

$mock['edition'] = 'pro';
$client->revalidate();
check( 'revalidate upgrades free -> pro', 'pro' === get_option( 'spr_edition' ) );

// A network error must NOT change the stored edition.
update_option( 'spr_edition', 'pro' );
$mock['error'] = true;
$client->revalidate();
check( 'network error preserves edition', 'pro' === get_option( 'spr_edition' ) );
$mock['error'] = false;

echo "\n===== $pass passed, $fail failed =====\n";
exit( $fail > 0 ? 1 : 0 );
