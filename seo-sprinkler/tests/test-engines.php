<?php
/**
 * Engine smoke tests — exercise the pure-logic engines on WP-loaded data.
 *
 * Run after bootstrap-wp.sh. Exits non-zero if any assertion fails.
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

$junk = "```html\n<p>Hello\u{200B} world</p>\n```\n"
	. '<p></p>'
	. '<p style="color:red" class="foo">Styled</p>'
	. '<h1>Rogue H1</h1>'
	. '<p>See <a href="https://example.com">ex</a> and <a href="/about">about</a>.</p>'
	. '<script>alert(1)</script>'
	. '<p>Many<br><br><br><br>brk</p>'
	. '<p>Empty <span></span> tag</p>'
	. '<!-- wp:paragraph --><p>keepblock</p><!-- /wp:paragraph -->'
	. '<!-- remove this comment -->'
	. '<img src="a.jpg"><img src="b.jpg">';

// Image counter.
$img = new SPR_Image_Scanner();
check( 'image count = 2', 2 === $img->count_images_in_content( $junk ) );

// H1 counter.
$h = new SPR_Heading_Checker();
check( 'h1 count = 1', 1 === $h->count_h1( $junk ) );

// Link scanner: enumerate + classify.
$ls    = new SPR_Link_Scanner();
$links = $ls->enumerate( $junk );
check( 'enumerate finds 2 links', 2 === count( $links ) );
$types = array_column( $links, 'type' );
check( '1 external + 1 internal', in_array( 'external', $types, true ) && in_array( 'internal', $types, true ) );

// Inline link edit on a real post.
$pid = wp_insert_post( array( 'post_title' => 'Edit Target', 'post_content' => $junk, 'post_status' => 'publish' ) );
$res = $ls->update_link( $pid, 0, '/changed', 'Changed <strong>text</strong>' );
$updated = get_post( $pid )->post_content;
check( 'update_link returns array', is_array( $res ) );
check( 'new href present', false !== strpos( $updated, '/changed' ) );
check( 'new anchor text present', false !== strpos( $updated, 'Changed <strong>text</strong>' ) );
check( 'rest of content preserved', false !== strpos( $updated, 'href="/about"' ) );

// Content cleaner — force every cleanup on.
$cleaner = new SPR_Content_Cleaner();
$force   = array();
foreach ( array_keys( SPR_Content_Cleaner::cleanups() ) as $k ) { $force[ $k ] = 1; }
list( $clean, $changed, $stats ) = $cleaner->clean( $junk, $force );
check( 'cleaner changed content', true === $changed );
check( 'no code fences', false === strpos( $clean, '```' ) );
check( 'no zero-width char', false === strpos( $clean, "\u{200B}" ) );
check( 'no <script>', false === stripos( $clean, '<script' ) );
check( 'no inline style=', false === stripos( $clean, 'style=' ) );
check( 'no class=', false === stripos( $clean, 'class=' ) );
check( 'no empty <p></p>', 0 === preg_match( '#<p>\s*</p>#', $clean ) );
check( 'no empty <span></span>', false === stripos( $clean, '<span></span>' ) );
check( '<=2 consecutive br', ! preg_match( '#(?:<br[^>]*>\s*){3,}#i', $clean ) );
check( 'Gutenberg block comment preserved', false !== strpos( $clean, 'wp:paragraph' ) );
check( 'plain comment removed', false === strpos( $clean, 'remove this comment' ) );
check( 'cleaner never touches links', false !== strpos( $clean, 'href="/about"' ) );

// Inline-style cleanup must keep functional table-layout styles (so cleaning a
// page like the chimney "Pricing" tables never strips borders/padding), while
// still removing inline styles on ordinary elements.
$tbl = '<p style="color:red">x</p>'
	. '<table style="width:100%"><thead><tr style="background:#eee">'
	. '<th style="padding:8px">A</th></tr></thead>'
	. '<tbody><tr><td style="padding:8px;border:1px solid #ddd">1</td></tr></tbody></table>';
list( $tclean, , $tstats ) = $cleaner->clean( $tbl, array( 'clean_inline_styles' => 1 ) );
check( 'table style= kept', false !== strpos( $tclean, '<table style="width:100%"' ) );
check( 'th/td style= kept', false !== strpos( $tclean, '<td style="padding:8px;border:1px solid #ddd"' ) );
check( 'non-table <p> style= removed', false === strpos( $tclean, 'color:red' ) );
check( 'only the <p> style counted', 1 === (int) $tstats['clean_inline_styles'] );

// Custom cleanup rules — a literal line and a /regex/ line.
$cs                         = (array) get_option( 'spr_settings' );
$cs['cleaner_custom_rules'] = "REMOVE_ME\n/\\[junk[^\\]]*\\]/i";
update_option( 'spr_settings', $cs );
SPR_Settings::flush_cache();
list( $ccclean, , $ccstats ) = $cleaner->clean( '<p>REMOVE_ME keep [junk a=1] end</p>', array( 'clean_custom' => 1 ) );
check( 'custom literal rule removed', false === strpos( $ccclean, 'REMOVE_ME' ) );
check( 'custom regex rule removed', false === strpos( $ccclean, '[junk' ) );
check( 'custom rules keep other text', false !== strpos( $ccclean, 'keep' ) && false !== strpos( $ccclean, 'end' ) );
check( 'custom rules counted', isset( $ccstats['clean_custom'] ) && $ccstats['clean_custom'] >= 2 );

// Cleaner backup + log + revert.
$pid2   = wp_insert_post( array( 'post_title' => 'Clean Target', 'post_content' => $junk, 'post_status' => 'publish' ) );
$before = get_post( $pid2 )->post_content;
$r      = $cleaner->clean_post( $pid2 );
check( 'clean_post reports changed', true === $r['changed'] );
check( 'backup meta saved', metadata_exists( 'post', $pid2, '_spr_cleaner_backup' ) );
$log = $cleaner->get_log();
check( 'log records the change', ! empty( array_filter( $log, function ( $e ) use ( $pid2 ) { return $e['post_id'] == $pid2; } ) ) );
$cleaner->revert_post( $pid2 );
check( 'revert restores original', get_post( $pid2 )->post_content === $before );
check( 'backup removed after revert', ! metadata_exists( 'post', $pid2, '_spr_cleaner_backup' ) );

// Link replacer (display-time injection engine).
$rep   = new SPR_Link_Replacer();
$entry = array(
	'post_id' => 123,
	'url'     => 'http://localhost:8088/wp/',
	'phrase'  => 'WordPress',
	'key'     => 'wordpress',
	'regex'   => '/(?<![\p{L}\p{N}_])(WordPress)(?![\p{L}\p{N}_])/u',
);
list( $html, $added ) = $rep->replace( '<p>I really love WordPress today.</p>', array( $entry ), array( 'max_per_post' => 5, 'max_per_keyword' => 1 ) );
check( 'replacer added 1 link', 1 === $added );
check( 'anchor wraps phrase', false !== strpos( $html, '>WordPress</a>' ) );
$noop = $rep->replace( '<p>nothing here</p>', array( $entry ) );
check( 'replacer skips when phrase absent', 0 === $noop[1] );

// Settings sanity.
check( 'default min_images = 3', 3 === (int) SPR_Settings::get( 'min_images' ) );
check( 'clean_inline_styles default ON', 1 === (int) SPR_Settings::get( 'clean_inline_styles' ) );
check( 'clean_classes default OFF', 0 === (int) SPR_Settings::get( 'clean_classes' ) );

echo "\n===== $pass passed, $fail failed =====\n";
exit( $fail > 0 ? 1 : 0 );
