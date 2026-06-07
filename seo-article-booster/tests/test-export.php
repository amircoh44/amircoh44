<?php
/**
 * Exporter + SEO-detector tests, incl. PII gating. Exits non-zero on failure.
 *
 * @package SeoArticleBooster\Tests
 */

// Simulate Yoast being active so the SEO detector has something to find.
define( 'WPSEO_VERSION', '99.9' );

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
function find_by_id( $list, $id ) {
	foreach ( (array) $list as $row ) { if ( isset( $row['id'] ) && $row['id'] == $id ) { return $row; } }
	return null;
}

// SEO detector.
check( 'detector finds Yoast', isset( SAB_SEO_Detector::active()['yoast'] ) );
check( 'meta keys include focus keyword', in_array( '_yoast_wpseo_focuskw', SAB_SEO_Detector::all_meta_keys(), true ) );

// Content fixtures.
$term   = wp_insert_term( 'Plumbing', 'category' );
$cat_id = is_wp_error( $term ) ? (int) $term->error_data['term_exists'] : (int) $term['term_id'];
$post   = wp_insert_post(
	array(
		'post_title'    => 'Leaky Faucet',
		'post_content'  => '<p>Fix it.</p>',
		'post_status'   => 'publish',
		'post_category' => array( $cat_id ),
	)
);
update_post_meta( $post, 'custom_field', 'hello' );
update_post_meta( $post, '_yoast_wpseo_focuskw', 'leaky faucet' );
update_post_meta( $post, '_yoast_wpseo_metadesc', 'How to fix a leaky faucet' );

$att = wp_insert_attachment(
	array(
		'post_mime_type' => 'image/png',
		'post_title'     => 'Faucet photo',
		'post_status'    => 'inherit',
		'post_excerpt'   => 'A caption',
		'post_content'   => 'A description',
	),
	false,
	0
);
update_post_meta( $att, '_wp_attached_file', 'faucet.png' );
wp_update_attachment_metadata( $att, array( 'width' => 800, 'height' => 600, 'sizes' => array() ) );
update_post_meta( $att, '_wp_attachment_image_alt', 'faucet alt' );

$exporter = new SAB_Exporter();

// Default manifest (no PII).
$m = $exporter->build_manifest();
check( 'manifest has site name', isset( $m['site']['name'] ) );
check( 'manifest lists post types', ! empty( $m['post_types'] ) );
check( 'manifest has posts', ! empty( $m['posts'] ) );
check( 'no users section by default', ! isset( $m['users'] ) );
check( 'no attachments among posts', empty( array_filter( $m['posts'], function ( $p ) { return 'attachment' === $p['type']; } ) ) );

$rec = find_by_id( $m['posts'], $post );
check( 'post exported', null !== $rec );
check( 'post meta exported', $rec && isset( $rec['meta']['custom_field'] ) && 'hello' === $rec['meta']['custom_field'] );
check( 'post terms exported', $rec && isset( $rec['terms']['category'] ) && in_array( 'plumbing', $rec['terms']['category'], true ) );
check( 'post SEO normalised', $rec && isset( $rec['seo']['focus_keyword'] ) && 'leaky faucet' === $rec['seo']['focus_keyword'] );

$mrec = find_by_id( $m['media'], $att );
check( 'media exported', null !== $mrec );
check( 'media has alt/caption/description', $mrec && 'faucet alt' === $mrec['alt'] && 'A caption' === $mrec['caption'] && 'A description' === $mrec['description'] );
check( 'seo section lists active plugin', isset( $m['seo'] ) && ! empty( $m['seo']['active_plugins'] ) );
check( 'taxonomies section present', isset( $m['taxonomies']['category'] ) );

// PII gating.
$m2 = $exporter->build_manifest( array( 'include_users' => true ) );
check( 'users included when opted in', isset( $m2['users'] ) && ! empty( $m2['users'] ) );
check( 'no email without include_emails', ! isset( $m2['users'][0]['email'] ) );
check( 'no admin_email without include_emails', ! isset( $m2['site']['admin_email'] ) );

$m3 = $exporter->build_manifest( array( 'include_users' => true, 'include_emails' => true ) );
check( 'email included with include_emails', isset( $m3['users'][0]['email'] ) );
check( 'admin_email included with include_emails', isset( $m3['site']['admin_email'] ) );

echo "\n===== $pass passed, $fail failed =====\n";
exit( $fail > 0 ? 1 : 0 );
