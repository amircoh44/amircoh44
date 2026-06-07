<?php
/**
 * Edition / licensing gate tests (feature split + 25-page free grace).
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

// Control the free page limit deterministically.
$GLOBALS['sab_test_limit'] = -1; // -1 disables the grace (pure licence behaviour).
add_filter( 'sab_free_page_limit', function () { return $GLOBALS['sab_test_limit']; } );

/* ---- Licence behaviour (grace disabled) ---- */
$GLOBALS['sab_test_limit'] = -1;

update_option( 'sab_edition', 'free' );
check( 'free current', 'free' === SAB_Edition::current() );
check( 'free is not pro', ! SAB_Edition::is_pro() );
check( 'free: free feature allowed', SAB_Edition::can( 'image_audit' ) );
check( 'free over-limit: cannot auto-link', ! SAB_Edition::can( 'auto_linking' ) );
check( 'free over-limit: cannot schema output', ! SAB_Edition::can( 'schema_output' ) );
check( 'free over-limit: cannot AI', ! SAB_Edition::can( 'ai' ) );
check( 'free over-limit: cannot export', ! SAB_Edition::can( 'export' ) );

update_option( 'sab_edition', 'pro' );
check( 'pro is_pro', SAB_Edition::is_pro() );
check( 'pro can auto-link', SAB_Edition::can( 'auto_linking' ) );
check( 'pro can schema output', SAB_Edition::can( 'schema_output' ) );
check( 'pro can AI', SAB_Edition::can( 'ai' ) );
check( 'pro CANNOT export (Expert only)', ! SAB_Edition::can( 'export' ) );

update_option( 'sab_edition', 'expert' );
check( 'expert is_expert', SAB_Edition::is_expert() );
check( 'expert can export', SAB_Edition::can( 'export' ) );

/* ---- 25-page free grace (limit high => within limit) ---- */
$GLOBALS['sab_test_limit'] = 9999;
update_option( 'sab_edition', 'free' );
check( 'free within limit: within_free_limit() true', SAB_Edition::within_free_limit() );
check( 'free within limit: premium unlocked (grace)', SAB_Edition::can( 'auto_linking' ) );
check( 'free within limit: AI unlocked (grace)', SAB_Edition::can( 'ai' ) );
check( 'free within limit: export unlocked (grace)', SAB_Edition::can( 'export' ) );

$GLOBALS['sab_test_limit'] = -1;
check( 'free over limit: over_free_limit() true', SAB_Edition::over_free_limit() );
check( 'free over limit: premium re-locked', ! SAB_Edition::can( 'auto_linking' ) );

/* ---- Licence activation ---- */
update_option( 'sab_edition', 'expert' );
SAB_Edition::activate_key( '' );
check( 'empty key resolves to free', 'free' === get_option( 'sab_edition' ) );

add_filter( 'sab_validate_license', function ( $edition, $key ) {
	return ( 'PRO-123' === $key ) ? 'pro' : $edition;
}, 10, 2 );
SAB_Edition::activate_key( 'PRO-123' );
check( 'valid key resolves to pro via filter', 'pro' === SAB_Edition::current() );

check( 'export requires expert', 'expert' === SAB_Edition::required_for( 'export' ) );
check( 'label maps', 'Pro' === SAB_Edition::label( 'pro' ) );

update_option( 'sab_edition', 'free' );

echo "\n===== $pass passed, $fail failed =====\n";
exit( $fail > 0 ? 1 : 0 );
