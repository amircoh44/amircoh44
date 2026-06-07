<?php
/**
 * Image filler tests — relevance, block markup, distribution, fill + revert.
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

/** Create an image attachment with resolvable size metadata (no real file needed). */
function mkimg( $title, $alt ) {
	$id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => $title,
			'post_status'    => 'inherit',
			'post_type'      => 'attachment',
		),
		false,
		0
	);
	update_post_meta( $id, '_wp_attached_file', $title . '.png' );
	wp_update_attachment_metadata( $id, array( 'width' => 600, 'height' => 400, 'file' => $title . '.png', 'sizes' => array() ) );
	if ( $alt ) {
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
	}
	return $id;
}

$plumb = mkimg( 'plumbing-hero', 'plumbing leak repair' );
mkimg( 'sunset-beach', 'ocean view' );
mkimg( 'cute-cat', 'animal' );

$post = wp_insert_post(
	array(
		'post_title'   => 'Plumbing tips for winter',
		'post_content' => '<p>One.</p><p>Two.</p><p>Three.</p><p>Four.</p>',
		'post_status'  => 'publish',
	)
);

$filler = new SAB_Image_Filler( new SAB_Image_Scanner(), new SAB_Link_Scanner(), new SAB_Schema_Scanner(), new SAB_Heading_Checker() );

// Relevance: the "plumbing" image should surface first.
$ids = $filler->related_image_ids( $post, 2 );
check( 'related returns requested count', 2 === count( $ids ) );
check( 'related image prioritised by keyword', $plumb === $ids[0] );

// Block markup.
$block = $filler->build_image_block( $plumb, 'center', 'large' );
check( 'block is a wp:image block', false !== strpos( $block, '<!-- wp:image' ) );
check( 'block carries our class', false !== strpos( $block, 'sab-auto-image' ) );
check( 'block honours alignment', false !== strpos( $block, 'aligncenter' ) );

// Distribution: after every 2nd paragraph.
$out = $filler->insert_blocks( '<p>a</p><p>b</p><p>c</p><p>d</p>', array( '[IMG1]', '[IMG2]' ), 2 );
check( 'each image inserted once', 1 === substr_count( $out, '[IMG1]' ) && 1 === substr_count( $out, '[IMG2]' ) );
check( 'first image lands after 2nd paragraph', strpos( $out, '[IMG1]' ) > strpos( $out, '<p>b</p>' ) && strpos( $out, '[IMG1]' ) < strpos( $out, '<p>c</p>' ) );

// Fill + revert.
$before = get_post( $post )->post_content;
$n      = $filler->fill_post( $post, array( 'align' => 'center', 'size' => 'large', 'count' => 2, 'every' => 2 ) );
check( 'fill_post inserted 2 images', 2 === $n );
check( 'fill_post saved a backup', metadata_exists( 'post', $post, '_sab_imagefill_backup' ) );
check( 'content now has image blocks', false !== strpos( get_post( $post )->post_content, 'sab-auto-image' ) );
$filler->revert_post( $post );
check( 'revert restores original content', get_post( $post )->post_content === $before );
check( 'backup removed after revert', ! metadata_exists( 'post', $post, '_sab_imagefill_backup' ) );

// SEO score + AI configuration.
$score = $filler->seo_score( $post );
check( 'seo_score in 0-100', is_array( $score ) && $score['score'] >= 0 && $score['score'] <= 100 );
check( 'seo_score has breakdown', isset( $score['parts']['images'], $score['parts']['schema'] ) );
$s = (array) get_option( 'sab_settings' );
$s['ai_key'] = '';
update_option( 'sab_settings', $s );
SAB_Settings::flush_cache();
check( 'AI not configured without a key', ! SAB_AI::is_configured() );
$s['ai_endpoint'] = 'https://example.test/v1/chat/completions';
$s['ai_key']      = 'sk-test';
update_option( 'sab_settings', $s );
SAB_Settings::flush_cache();
check( 'AI configured after endpoint+key set', SAB_AI::is_configured() );

echo "\n===== $pass passed, $fail failed =====\n";
exit( $fail > 0 ? 1 : 0 );
