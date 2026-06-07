<?php
/**
 * Image filler tests — relevance, block markup, distribution, fill + revert.
 * Exits non-zero on failure.
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

/** Create a small icon-sized attachment (<=150px). */
function mkicon( $title, $alt ) {
	$id = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => $title, 'post_status' => 'inherit', 'post_type' => 'attachment' ), false, 0 );
	update_post_meta( $id, '_wp_attached_file', $title . '.png' );
	wp_update_attachment_metadata( $id, array( 'width' => 48, 'height' => 48, 'file' => $title . '.png', 'sizes' => array() ) );
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

$filler = new SPR_Image_Filler( new SPR_Image_Scanner(), new SPR_Link_Scanner(), new SPR_Schema_Scanner(), new SPR_Heading_Checker() );

// Relevance: the "plumbing" image should surface first.
$ids = $filler->related_image_ids( $post, 2 );
check( 'related returns requested count', 2 === count( $ids ) );
check( 'related image prioritised by keyword', $plumb === $ids[0] );

// Block markup.
$block = $filler->build_image_block( $plumb, 'center', 'large' );
check( 'block is a wp:image block', false !== strpos( $block, '<!-- wp:image' ) );
check( 'block carries our class', false !== strpos( $block, 'spr-auto-image' ) );
check( 'block honours alignment', false !== strpos( $block, 'aligncenter' ) );
check( 'img itself carries the align class', (bool) preg_match( '/<img[^>]*\bclass="[^"]*\baligncenter\b/', $block ) );
check( 'block markup is lean (no baked srcset)', false === strpos( $block, 'srcset' ) && false === strpos( $block, 'sizes=' ) );
check( 'img keeps the wp-image id for responsive render', false !== strpos( $block, 'wp-image-' . $plumb ) );
$left_block = $filler->build_image_block( $plumb, 'left', 'large' );
check( 'left img carries alignleft', (bool) preg_match( '/<img[^>]*\bclass="[^"]*\balignleft\b/', $left_block ) );

// Distribution: after every 2nd paragraph.
$out = $filler->insert_blocks( '<p>a</p><p>b</p><p>c</p><p>d</p>', array( '[IMG1]', '[IMG2]' ), 2 );
check( 'each image inserted once', 1 === substr_count( $out, '[IMG1]' ) && 1 === substr_count( $out, '[IMG2]' ) );
check( 'first image lands after 2nd paragraph', strpos( $out, '[IMG1]' ) > strpos( $out, '<p>b</p>' ) && strpos( $out, '[IMG1]' ) < strpos( $out, '<p>c</p>' ) );

// Fill + revert.
$before = get_post( $post )->post_content;
$n      = $filler->fill_post( $post, array( 'align' => 'center', 'size' => 'large', 'count' => 2, 'every' => 2 ) );
check( 'fill_post inserted 2 images', 2 === $n );
check( 'fill_post saved a backup', metadata_exists( 'post', $post, '_spr_imagefill_backup' ) );
check( 'content now has image blocks', false !== strpos( get_post( $post )->post_content, 'spr-auto-image' ) );
$filler->revert_post( $post );
check( 'revert restores original content', get_post( $post )->post_content === $before );
check( 'backup removed after revert', ! metadata_exists( 'post', $post, '_spr_imagefill_backup' ) );

// SEO score + AI configuration.
$score = $filler->seo_score( $post );
check( 'seo_score in 0-100', is_array( $score ) && $score['score'] >= 0 && $score['score'] <= 100 );
check( 'seo_score has breakdown', isset( $score['parts']['images'], $score['parts']['schema'] ) );
$s = (array) get_option( 'spr_settings' );
$s['ai_key'] = '';
update_option( 'spr_settings', $s );
SPR_Settings::flush_cache();
check( 'AI not configured without a key', ! SPR_AI::is_configured() );
$s['ai_endpoint'] = 'https://example.test/v1/chat/completions';
$s['ai_key']      = 'sk-test';
update_option( 'spr_settings', $s );
SPR_Settings::flush_cache();
check( 'AI configured after endpoint+key set', SPR_AI::is_configured() );

/* --- Bulk image distribution ------------------------------------------- */

// Custom alt is written onto the <img>.
$alt_block = $filler->build_image_block( $plumb, 'center', 'large', 'My custom alt' );
check( 'block honours custom alt', false !== strpos( $alt_block, 'alt="My custom alt"' ) );

// auto_alt: stored alt wins; otherwise it is built from the post title + file name.
$noalt = mkimg( 'no-alt-image', '' );
check( 'auto_alt uses stored alt', 'plumbing leak repair' === $filler->auto_alt( get_post( $post ), $plumb ) );
check( 'auto_alt falls back to title', false !== stripos( $filler->auto_alt( get_post( $post ), $noalt ), 'Plumbing tips' ) );

// Variety: an already-used image is pushed to the back so others are picked.
$one = $filler->related_image_ids( $post, 1, array( $plumb ) );
check( 'related skips an excluded image when alternatives exist', 1 === count( $one ) && $plumb !== $one[0] );

// bulk_fill brings a 0-image post up to the target and reports the IDs used.
$bp  = wp_insert_post( array( 'post_title' => 'Needs images', 'post_content' => '<p>a</p><p>b</p><p>c</p><p>d</p>', 'post_status' => 'publish' ) );
$res = $filler->bulk_fill( $bp, array( 'mode' => 'per_article', 'target' => 2, 'every' => 2, 'alt_mode' => 'auto' ) );
check( 'bulk_fill inserts up to the target', isset( $res['inserted'] ) && 2 === $res['inserted'] );
check( 'bulk_fill returns the used image ids', ! empty( $res['used'] ) );
check( 'bulk_fill wrote image blocks', false !== strpos( get_post( $bp )->post_content, 'spr-auto-image' ) );

// And skips a post that already meets the target.
$res2 = $filler->bulk_fill( $bp, array( 'mode' => 'per_article', 'target' => 2 ) );
check( 'bulk_fill skips when already enough', 0 === $res2['inserted'] && 'enough' === $res2['skipped'] );

// per_words mode derives the target from the word count (400 words / 200 = 2).
$lp   = wp_insert_post( array( 'post_title' => 'Long one', 'post_content' => '<p>' . str_repeat( 'word ', 400 ) . '</p>', 'post_status' => 'publish' ) );
$res3 = $filler->bulk_fill( $lp, array( 'mode' => 'per_words', 'per_words' => 200, 'alt_mode' => 'auto' ) );
check( 'per_words inserts ceil(words / N)', isset( $res3['inserted'] ) && $res3['inserted'] >= 2 );

/* --- Per-image reviewer (approve alt / caption / title / description) ---- */

// build_image_block renders an editable caption when one is supplied.
$cap_block = $filler->build_image_block( $plumb, 'center', 'large', 'Alt here', 'A nice caption' );
check( 'block renders a figcaption', false !== strpos( $cap_block, '<figcaption' ) );
check( 'block shows the caption text', false !== strpos( $cap_block, 'A nice caption' ) );

// needed_for mirrors the bulk deficit logic.
$rp = wp_insert_post( array( 'post_title' => 'Review me', 'post_content' => '<p>x</p><p>y</p><p>z</p>', 'post_status' => 'publish' ) );
check( 'needed_for per_article = target', 3 === $filler->needed_for( get_post( $rp ), 'per_article', 3, 200 ) );
check( 'needed_for falls back to the minimum', $filler->needed_for( get_post( $rp ), 'per_article', 0, 200 ) >= 1 );

// propose_for_post returns editable metadata rows (auto, no AI).
$props = $filler->propose_for_post( $rp, 2, array(), false );
check( 'propose returns the requested count', 2 === count( $props ) );
check( 'proposal exposes editable fields', isset( $props[0]['id'], $props[0]['alt'], $props[0]['caption'], $props[0]['title'], $props[0]['description'] ) );

// apply_single inserts ONE reviewed image and writes the attachment fields.
$rimg = (int) $props[0]['id'];
$ap   = $filler->apply_single(
	$rp,
	$rimg,
	array( 'align' => 'center', 'size' => 'large', 'alt' => 'Reviewed alt', 'caption' => 'Reviewed caption', 'title' => 'Reviewed title', 'description' => 'Reviewed description', 'every' => 2 )
);
check( 'apply_single inserts one image', isset( $ap['inserted'] ) && 1 === $ap['inserted'] );
check( 'apply_single backed up the post', metadata_exists( 'post', $rp, '_spr_imagefill_backup' ) );
$rp_html = get_post( $rp )->post_content;
check( 'applied block carries reviewed alt', false !== strpos( $rp_html, 'Reviewed alt' ) );
check( 'applied block carries reviewed caption', false !== strpos( $rp_html, 'Reviewed caption' ) );
check( 'apply_single wrote the attachment title', 'Reviewed title' === get_the_title( $rimg ) );
check( 'apply_single wrote the attachment alt', 'Reviewed alt' === get_post_meta( $rimg, '_wp_attachment_image_alt', true ) );
check( 'apply_single reports the new count', isset( $ap['count'] ) && $ap['count'] >= 1 );

// remove_inserted takes our images back out (and only ours).
$rm = $filler->remove_inserted( $rp );
check( 'remove_inserted reports removed >= 1', isset( $rm['removed'] ) && $rm['removed'] >= 1 );
check( 'remove_inserted stripped our blocks', false === strpos( get_post( $rp )->post_content, 'spr-auto-image' ) );

/* --- Icon images (<=150px) + even scatter ------------------------------- */

$icon = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'service-icon', 'post_status' => 'inherit', 'post_type' => 'attachment' ), false, 0 );
update_post_meta( $icon, '_wp_attached_file', 'service-icon.png' );
wp_update_attachment_metadata( $icon, array( 'width' => 50, 'height' => 50, 'file' => 'service-icon.png', 'sizes' => array() ) );

check( 'is_icon true for 50x50', true === $filler->is_icon( $icon ) );
check( 'is_icon false for 600x400', false === $filler->is_icon( $plumb ) );

// An icon is forced left, no caption, with the icon class — even if asked otherwise.
$iblock = $filler->build_image_block( $icon, 'center', 'large', 'Icon alt', 'Ignored caption' );
check( 'icon block is left-aligned', false !== strpos( $iblock, 'alignleft' ) );
check( 'icon block carries the icon class', false !== strpos( $iblock, 'spr-auto-icon' ) );
check( 'icon block drops the caption', false === strpos( $iblock, 'figcaption' ) );

// distribute_evenly spreads blocks across the content (no clumping).
$content = '<p>a</p><p>b</p><p>c</p><p>d</p><p>e</p><p>f</p>';
$spread  = $filler->distribute_evenly( $content, array( '[I1]', '[I2]', '[I3]' ) );
check( 'distribute inserts every block once', 1 === substr_count( $spread, '[I1]' ) && 1 === substr_count( $spread, '[I2]' ) && 1 === substr_count( $spread, '[I3]' ) );
check( 'distribute keeps document order', strpos( $spread, '[I1]' ) < strpos( $spread, '[I2]' ) && strpos( $spread, '[I2]' ) < strpos( $spread, '[I3]' ) );
check( 'distribute does not clump at the end', strpos( $spread, '[I1]' ) < strpos( $spread, '<p>f</p>' ) );

/* --- Icon sprinkle: placement + context matching + scatter -------------- */

$ic_chim = mkicon( 'chimney-repair-icon', 'chimney repair' );
$ic_fire = mkicon( 'fireplace-cleaning-icon', 'fireplace cleaning' );

$svc = wp_insert_post( array(
	'post_title'   => 'Our Services',
	'post_content' => '<h2>Chimney Repair</h2><p>' . str_repeat( 'chimney repair ', 20 ) . '</p><h2>Fireplace Cleaning</h2><p>' . str_repeat( 'fireplace cleaning ', 20 ) . '</p>',
	'post_status'  => 'publish',
) );

// build_icon_img is an inline, left-aligned icon (no figure/caption).
$iconimg = $filler->build_icon_img( $ic_chim, 'Chimney repair' );
check( 'build_icon_img is left-aligned', false !== strpos( $iconimg, 'alignleft' ) );
check( 'build_icon_img carries the icon class', false !== strpos( $iconimg, 'spr-auto-icon' ) );
check( 'build_icon_img has no figcaption', false === strpos( $iconimg, 'figcaption' ) );

$si = $filler->sprinkle_icons( $svc, array( 'placement' => 'headings' ) );
check( 'sprinkle inserts an icon per heading', isset( $si['inserted'] ) && $si['inserted'] >= 2 );
$html = get_post( $svc )->post_content;
check( 'sprinkle added inline icons', false !== strpos( $html, 'spr-auto-icon' ) );
// Context match: the chimney icon lands in the chimney section (before the fireplace heading).
$p_chim = strpos( $html, 'wp-image-' . $ic_chim );
$p_fire = strpos( $html, 'Fireplace Cleaning' );
check( 'chimney icon matched to its section', false !== $p_chim && false !== $p_fire && $p_chim < $p_fire );
// Scatter: a different icon per heading.
check( 'icons are scattered (no repeat)', count( array_unique( $si['used'] ) ) === count( $si['used'] ) );

// Removal also strips sprinkled icons.
$filler->remove_inserted( $svc );
check( 'remove strips sprinkled icons', false === strpos( get_post( $svc )->post_content, 'spr-auto-icon' ) );

echo "\n===== $pass passed, $fail failed =====\n";
exit( $fail > 0 ? 1 : 0 );
