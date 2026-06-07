<?php
/**
 * Google Search Console engine tests — pure logic + a mocked Indexing call.
 * No network: the spr_gsc_http filter short-circuits every request.
 *
 * @package SeoSprinkler\Tests
 */

$core = getenv( 'WP_CORE' );
if ( ! $core ) { fwrite( STDERR, "WP_CORE not set\n" ); exit( 1 ); }
$_SERVER['HTTP_HOST'] = 'localhost:8088';
$_SERVER['REQUEST_URI'] = '/';
require $core . '/wp-load.php';

$pass = 0; $fail = 0;
function check( $name, $cond ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS  $name\n"; }
	else { $fail++; echo "FAIL  $name\n"; }
}

// classify(): coverageState -> simple state.
check( 'classify indexed', 'indexed' === SPR_GSC::classify( 'Submitted and indexed', 'PASS' ) );
check( 'classify indexed (not in sitemap)', 'indexed' === SPR_GSC::classify( 'Indexed, not submitted in sitemap', 'PASS' ) );
check( 'classify crawled-not-indexed', 'not_indexed' === SPR_GSC::classify( 'Crawled - currently not indexed', 'NEUTRAL' ) );
check( 'classify discovered-not-indexed', 'not_indexed' === SPR_GSC::classify( 'Discovered - currently not indexed', 'NEUTRAL' ) );
check( 'classify unknown URL', 'not_indexed' === SPR_GSC::classify( 'URL is unknown to Google', 'NEUTRAL' ) );
check( 'classify excluded', 'not_indexed' === SPR_GSC::classify( 'Excluded by ‘noindex’ tag', 'FAIL' ) );

// compute_delta(): added / dropped / not_indexed.
$prev = array(
	'https://x/a' => 'indexed',
	'https://x/b' => 'indexed',
	'https://x/c' => 'not_indexed',
);
$curr = array(
	'https://x/a' => 'indexed',     // unchanged
	'https://x/b' => 'not_indexed', // dropped
	'https://x/c' => 'indexed',     // added
	'https://x/d' => 'not_indexed', // new, not indexed
);
$delta = SPR_GSC::compute_delta( $prev, $curr );
check( 'delta added is /c', array( 'https://x/c' ) === $delta['added'] );
check( 'delta dropped is /b', array( 'https://x/b' ) === $delta['dropped'] );
check( 'delta not_indexed = b + d', in_array( 'https://x/b', $delta['not_indexed'], true ) && in_array( 'https://x/d', $delta['not_indexed'], true ) && 2 === count( $delta['not_indexed'] ) );

// auth_url() carries the client, redirect, scopes and offline access.
$gsc = new SPR_GSC();
$gsc->save_credentials( 'cid.apps.googleusercontent.com', 'secret', 'https://example.com/' );
check( 'is_configured after creds', $gsc->is_configured() );
$url = $gsc->auth_url( 'STATE123' );
check( 'auth_url has client_id', false !== strpos( $url, 'client_id=cid.apps' ) );
check( 'auth_url requests offline', false !== strpos( $url, 'access_type=offline' ) );
check( 'auth_url has indexing scope', false !== strpos( rawurldecode( $url ), 'auth/indexing' ) );
check( 'auth_url has state', false !== strpos( $url, 'STATE123' ) );
check( 'property returns saved value', 'https://example.com/' === $gsc->property() );

// Daily quota default.
check( 'daily quota default 10', 10 === $gsc->daily_quota() );

// process_queue() with a mocked Indexing API (no network). Pretend connected.
update_option(
	'spr_gsc',
	array_merge(
		(array) get_option( 'spr_gsc', array() ),
		array( 'refresh_token' => 'r', 'access_token' => 't', 'token_expires' => time() + 9999 )
	)
);
update_option(
	'spr_gsc_state',
	array( 'states' => array(), 'queue' => array( 'https://x/1', 'https://x/2', 'https://x/3' ), 'submitted' => array(), 'daily_date' => '', 'daily_count' => 0, 'log' => array() )
);
add_filter(
	'spr_gsc_http',
	function ( $pre, $method, $url ) {
		// Mock the Indexing publish endpoint as success.
		if ( false !== strpos( $url, 'urlNotifications:publish' ) ) {
			return array( 'code' => 200, 'body' => array( 'urlNotificationMetadata' => array() ) );
		}
		return $pre;
	},
	10,
	3
);
$res = $gsc->process_queue( 2 );
check( 'process_queue submits the limit', 2 === count( $res['submitted'] ) );
check( 'process_queue leaves the rest queued', 1 === (int) $res['remaining'] );
$st = get_option( 'spr_gsc_state' );
check( 'process_queue records daily_count', 2 === (int) $st['daily_count'] );
check( 'process_queue moves to submitted', isset( $st['submitted']['https://x/1'] ) );

// Daily cap is enforced across calls within the same day.
$res2 = $gsc->process_queue( 50 );
check( 'daily cap stops further submits', count( $res2['submitted'] ) <= 8 && ( 2 + count( $res2['submitted'] ) ) <= 10 );

echo "\n===== $pass passed, $fail failed =====\n";
exit( $fail > 0 ? 1 : 0 );
