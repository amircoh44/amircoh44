<?php
/**
 * Edition / licensing gate tests. Exits non-zero on failure.
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

// --- Free ---
update_option( 'sab_edition', 'free' );
check( 'default/free current', 'free' === SAB_Edition::current() );
check( 'free is not pro', ! SAB_Edition::is_pro() );
check( 'free can use a free feature (unknown key)', SAB_Edition::can( 'image_audit' ) );
check( 'free cannot auto-link', ! SAB_Edition::can( 'auto_linking' ) );
check( 'free cannot output schema', ! SAB_Edition::can( 'schema_output' ) );
check( 'free cannot bulk apply', ! SAB_Edition::can( 'bulk_apply' ) );
check( 'free cannot export', ! SAB_Edition::can( 'export' ) );

// --- Pro ---
update_option( 'sab_edition', 'pro' );
check( 'pro is_pro', SAB_Edition::is_pro() );
check( 'pro is not expert', ! SAB_Edition::is_expert() );
check( 'pro can auto-link', SAB_Edition::can( 'auto_linking' ) );
check( 'pro can output schema', SAB_Edition::can( 'schema_output' ) );
check( 'pro can bulk apply', SAB_Edition::can( 'bulk_apply' ) );
check( 'pro can distribute', SAB_Edition::can( 'distribution' ) );
check( 'pro CANNOT export (Expert only)', ! SAB_Edition::can( 'export' ) );

// --- Expert ---
update_option( 'sab_edition', 'expert' );
check( 'expert is_expert', SAB_Edition::is_expert() );
check( 'expert can export', SAB_Edition::can( 'export' ) );
check( 'expert can everything pro', SAB_Edition::can( 'distribution' ) && SAB_Edition::can( 'schema_output' ) );

// --- License activation (no provider hooked => stays free) ---
update_option( 'sab_edition', 'expert' );
SAB_Edition::activate_key( '' );
check( 'empty key resolves to free', 'free' === get_option( 'sab_edition' ) );

// A provider/integration can grant an edition via the filter.
add_filter( 'sab_validate_license', function ( $edition, $key ) {
	return ( 'PRO-123' === $key ) ? 'pro' : $edition;
}, 10, 2 );
SAB_Edition::activate_key( 'PRO-123' );
check( 'valid key resolves to pro via filter', 'pro' === SAB_Edition::current() );

// required_for + label.
check( 'export requires expert', 'expert' === SAB_Edition::required_for( 'export' ) );
check( 'label maps', 'Pro' === SAB_Edition::label( 'pro' ) );

// reset
update_option( 'sab_edition', 'free' );

echo "\n===== $pass passed, $fail failed =====\n";
exit( $fail > 0 ? 1 : 0 );
