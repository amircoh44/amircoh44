<?php
/**
 * Syndication tests — payload, webhook parsing, edition gating.
 * Exits non-zero on failure.
 *
 * @package SeoArticleBooster\Tests
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

$syn  = new SAB_Syndication();
$post = wp_insert_post(
	array(
		'post_title'   => 'Hello World Syndication',
		'post_content' => '<p>Body text here for the excerpt.</p>',
		'post_status'  => 'publish',
	)
);

$payload = $syn->build_payload( $post );
check( 'payload event is post_published', isset( $payload['event'] ) && 'post_published' === $payload['event'] );
check( 'payload has post url', ! empty( $payload['post']['url'] ) );
check( 'payload title matches', isset( $payload['post']['title'] ) && 'Hello World Syndication' === $payload['post']['title'] );
check( 'payload has excerpt', isset( $payload['post']['excerpt'] ) && '' !== $payload['post']['excerpt'] );
check( 'payload has site name', isset( $payload['site']['name'] ) );

// Webhook parsing.
$s = (array) get_option( 'sab_settings' );
$s['syndicate_webhooks'] = "https://a.test/hook\nhttps://b.test/hook\n";
update_option( 'sab_settings', $s );
SAB_Settings::flush_cache();
check( 'two webhooks parsed', 2 === count( $syn->webhooks() ) );

// Edition gating (force over the free limit).
$GLOBALS['sab_test_limit'] = -1;
add_filter( 'sab_free_page_limit', function () { return $GLOBALS['sab_test_limit']; } );
update_option( 'sab_edition', 'free' );
check( 'free over-limit cannot syndicate', ! SAB_Edition::can( 'syndication' ) );
update_option( 'sab_edition', 'expert' );
check( 'expert can syndicate', SAB_Edition::can( 'syndication' ) );
update_option( 'sab_edition', 'free' );

echo "\n===== $pass passed, $fail failed =====\n";
exit( $fail > 0 ? 1 : 0 );
