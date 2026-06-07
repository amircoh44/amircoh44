<?php
/**
 * Front-end smoke tests — verify the_content filters (internal linking +
 * content distribution) and auto-clean-on-save, in a real singular main-query
 * loop. Exits non-zero on failure.
 *
 * @package SeoArticleBooster\Tests
 */

$core = getenv( 'WP_CORE' );
if ( ! $core ) { fwrite( STDERR, "WP_CORE not set\n" ); exit( 1 ); }
$_SERVER['HTTP_HOST'] = 'localhost:8088';
$_SERVER['REQUEST_URI'] = '/';
require $core . '/wp-load.php';

// Automatic linking, distribution and auto-clean are Pro/Expert features; run
// these front-end checks as Expert so the gated behaviour is exercised.
update_option( 'sab_edition', 'expert' );

$pass = 0; $fail = 0;
function check( $name, $cond ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS  $name\n"; }
	else { $fail++; echo "FAIL  $name\n"; }
}

// Target whose title becomes a linkable phrase.
$target = wp_insert_post( array( 'post_title' => 'Plumbing Services Experts', 'post_content' => '<p>About us.</p>', 'post_status' => 'publish' ) );
// Source mentions that exact phrase and has two paragraphs.
$source = wp_insert_post( array( 'post_title' => 'Home Guide', 'post_content' => '<p>We provide Plumbing Services Experts advice here.</p><p>Second paragraph.</p>', 'post_status' => 'publish' ) );

// A distribution rule: inject HTML after the first paragraph, match all posts.
$rules = new SAB_Injection_Rules();
update_option(
	'sab_injection_rules',
	array(
		'r_test' => $rules->sanitize_rule(
			array(
				'id'             => 'r_test',
				'title'          => 'CTA',
				'enabled'        => 1,
				'post_types'     => array( 'post' ),
				'payload_type'   => 'html',
				'html'           => '<div class="cta">CALL-NOW-7788</div>',
				'placement'      => 'after_paragraph',
				'position'       => 1,
				'max_insertions' => 1,
			)
		),
	)
);

// Force a fresh index (no Yoast => falls back to all published posts).
delete_transient( 'sab_link_index' );
delete_transient( 'sab_sitemap_urls' );

// Simulate a singular main-query loop so the_content filters run for real.
$GLOBALS['wp_the_query'] = new WP_Query( array( 'p' => $source ) );
$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];
$rendered = '';
while ( $GLOBALS['wp_query']->have_posts() ) {
	$GLOBALS['wp_query']->the_post();
	$rendered = apply_filters( 'the_content', get_post()->post_content );
}
wp_reset_query();

check( 'internal link injected on phrase', false !== strpos( $rendered, 'sab-internal-link' ) && false !== strpos( $rendered, '>Plumbing Services Experts</a>' ) );
check( 'link points to the target post', false !== strpos( $rendered, get_permalink( $target ) ) );
check( 'distribution block injected', false !== strpos( $rendered, 'CALL-NOW-7788' ) && false !== strpos( $rendered, 'sab-injection' ) );
check( 'CTA placed after first paragraph', strpos( $rendered, 'Second paragraph' ) > strpos( $rendered, 'CALL-NOW-7788' ) );

// Auto-clean on save.
$settings = (array) get_option( 'sab_settings' );
$settings['cleaner_autosave'] = 1;
update_option( 'sab_settings', $settings );
SAB_Settings::flush_cache();

$cid   = wp_insert_post( array( 'post_title' => 'Dirty', 'post_content' => "```html\n<p>Body</p>\n```\n<p></p><p>Real</p>", 'post_status' => 'publish', 'post_type' => 'post' ) );
$saved = get_post( $cid )->post_content;
check( 'autosave-clean stripped code fence', false === strpos( $saved, '```' ) );
check( 'autosave-clean removed empty <p>', ! preg_match( '#<p>\s*</p>#', $saved ) );
check( 'autosave-clean kept real content', false !== strpos( $saved, 'Real' ) );

echo "\n===== $pass passed, $fail failed =====\n";
exit( $fail > 0 ? 1 : 0 );
