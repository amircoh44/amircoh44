<?php
/**
 * Business Profile helpers — Google opening-hours conversion + Maps URL parsing.
 * Pure/static logic, so no network. Exits non-zero on failure.
 *
 * @package SeoSprinkler\Tests
 */

$core = getenv( 'WP_CORE' );
if ( ! $core ) { fwrite( STDERR, "WP_CORE not set\n" ); exit( 1 ); }
$_SERVER['HTTP_HOST'] = 'localhost:8088';
$_SERVER['REQUEST_URI'] = '/';
require $core . '/wp-load.php';

// Admin classes aren't loaded in CLI (is_admin() is false), so require it here.
require_once SPR_PLUGIN_DIR . 'admin/class-business-admin.php';

$pass = 0; $fail = 0;
function check( $name, $cond ) {
	global $pass, $fail;
	if ( $cond ) { $pass++; echo "PASS  $name\n"; }
	else { $fail++; echo "FAIL  $name\n"; }
}

// hhmm(): "HHMM" -> "HH:MM".
check( 'hhmm 0800', '08:00' === SPR_Business_Admin::hhmm( '0800' ) );
check( 'hhmm 2000', '20:00' === SPR_Business_Admin::hhmm( '2000' ) );
check( 'hhmm pads 3 digits', '09:00' === SPR_Business_Admin::hhmm( '900' ) );
check( 'hhmm rejects junk', '' === SPR_Business_Admin::hhmm( '0' ) );

// periods_to_hours(): the screenshot case — Sun-Thu 8-8, Fri 8-1, Sat closed.
$periods = array();
foreach ( array( 0, 1, 2, 3, 4 ) as $d ) {
	$periods[] = array( 'open' => array( 'day' => $d, 'time' => '0800' ), 'close' => array( 'day' => $d, 'time' => '2000' ) );
}
$periods[] = array( 'open' => array( 'day' => 5, 'time' => '0800' ), 'close' => array( 'day' => 5, 'time' => '1300' ) );
$hours = SPR_Business_Admin::periods_to_hours( $periods );
check( 'hours has Monday 08:00-20:00', false !== strpos( $hours, 'Mo 08:00-20:00' ) );
check( 'hours has Friday 08:00-13:00', false !== strpos( $hours, 'Fr 08:00-13:00' ) );
check( 'hours has Sunday 08:00-20:00', false !== strpos( $hours, 'Su 08:00-20:00' ) );
check( 'hours omits closed Saturday', false === strpos( $hours, 'Sa ' ) );
check( 'hours is Monday-first', 0 === strpos( $hours, 'Mo ' ) );

// The plugin's schema parser accepts the generated lines.
update_option( 'spr_business_profile', array_merge( (array) get_option( 'spr_business_profile', array() ), array( 'opening_hours' => $hours ) ) );
SPR_Business_Profile::flush_cache();
$spec = SPR_Business_Profile::opening_hours_spec();
check( 'schema parser reads generated hours', is_array( $spec ) && count( $spec ) >= 6 );
check( 'spec carries opens/closes', isset( $spec[0]['opens'], $spec[0]['closes'], $spec[0]['dayOfWeek'] ) );

// 24/7 special case.
$always = SPR_Business_Admin::periods_to_hours( array( array( 'open' => array( 'day' => 0, 'time' => '0000' ) ) ) );
check( '24/7 maps to Mo-Su 00:00-23:59', 'Mo-Su 00:00-23:59' === $always );

// parse_google_url(): prefer the place pin (!3d/!4d) over the @ viewport.
$url   = 'https://www.google.com/maps/place/Adam+Chimney+Sweep/@39.73076,-107.524375,17z/data=!3m1!4b1!4m6!3m5!1s0x0:0x0!8m2!3d39.6601!4d-104.7892!16s%2Fg%2F1';
$coord = SPR_Business_Admin::parse_google_url( $url );
check( 'parse uses the place pin lat', 39.6601 === $coord['lat'] );
check( 'parse uses the place pin lng', -104.7892 === $coord['lng'] );
check( 'parse reads the name', isset( $coord['name'] ) && 'Adam Chimney Sweep' === $coord['name'] );

echo "\n===== $pass passed, $fail failed =====\n";
exit( $fail > 0 ? 1 : 0 );
