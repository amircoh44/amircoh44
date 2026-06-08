<?php
/**
 * Edition / licensing gate tests (feature split + 25-page free grace).
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

// Control the free page limit deterministically.
$GLOBALS['spr_test_limit'] = -1; // -1 disables the grace (pure licence behaviour).
add_filter( 'spr_free_page_limit', function () { return $GLOBALS['spr_test_limit']; } );

/* ---- Licence behaviour (grace disabled) ---- */
$GLOBALS['spr_test_limit'] = -1;

update_option( 'spr_edition', 'free' );
check( 'free current', 'free' === SPR_Edition::current() );
check( 'free is not pro', ! SPR_Edition::is_pro() );
check( 'free: free feature allowed', SPR_Edition::can( 'image_audit' ) );
check( 'free over-limit: cannot auto-link', ! SPR_Edition::can( 'auto_linking' ) );
check( 'free over-limit: cannot schema output', ! SPR_Edition::can( 'schema_output' ) );
check( 'free over-limit: cannot AI', ! SPR_Edition::can( 'ai' ) );
check( 'free over-limit: cannot export', ! SPR_Edition::can( 'export' ) );

update_option( 'spr_edition', 'pro' );
check( 'pro is_pro', SPR_Edition::is_pro() );
check( 'pro can auto-link', SPR_Edition::can( 'auto_linking' ) );
check( 'pro can schema output', SPR_Edition::can( 'schema_output' ) );
check( 'pro can AI', SPR_Edition::can( 'ai' ) );
check( 'pro CANNOT export (Expert only)', ! SPR_Edition::can( 'export' ) );

update_option( 'spr_edition', 'expert' );
check( 'expert is_expert', SPR_Edition::is_expert() );
check( 'expert can export', SPR_Edition::can( 'export' ) );

/* ---- 25-page free grace (limit high => within limit) ---- */
$GLOBALS['spr_test_limit'] = 9999;
update_option( 'spr_edition', 'free' );
check( 'free within limit: within_free_limit() true', SPR_Edition::within_free_limit() );
check( 'free within limit: premium unlocked (grace)', SPR_Edition::can( 'auto_linking' ) );
check( 'free within limit: AI unlocked (grace)', SPR_Edition::can( 'ai' ) );
check( 'free within limit: export unlocked (grace)', SPR_Edition::can( 'export' ) );

$GLOBALS['spr_test_limit'] = -1;
check( 'free over limit: over_free_limit() true', SPR_Edition::over_free_limit() );
check( 'free over limit: premium re-locked', ! SPR_Edition::can( 'auto_linking' ) );

/* ---- Licence activation ---- */
update_option( 'spr_edition', 'expert' );
SPR_Edition::activate_key( '' );
check( 'empty key resolves to free', 'free' === get_option( 'spr_edition' ) );

add_filter( 'spr_validate_license', function ( $edition, $key ) {
	return ( 'PRO-123' === $key ) ? 'pro' : $edition;
}, 10, 2 );
SPR_Edition::activate_key( 'PRO-123' );
check( 'valid key resolves to pro via filter', 'pro' === SPR_Edition::current() );

check( 'export requires expert', 'expert' === SPR_Edition::required_for( 'export' ) );
check( 'label maps', 'Pro' === SPR_Edition::label( 'pro' ) );

/* ---- Support entitlement per tier ---- */
check( 'free support is community/docs', 'Community forum & documentation' === SPR_Edition::support_label( 'free' ) );
check( 'pro support is email', 'Email support' === SPR_Edition::support_label( 'pro' ) );
check( 'expert support is 24-hour priority email', false !== strpos( SPR_Edition::support_label( 'expert' ), '24-hour' ) );

update_option( 'spr_edition', 'free' );

echo "\n===== $pass passed, $fail failed =====\n";
exit( $fail > 0 ? 1 : 0 );
