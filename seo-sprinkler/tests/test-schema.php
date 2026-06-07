<?php
/**
 * Schema generator + business profile + multi-sitemap tests.
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
/** Find a graph node by a predicate. */
function find_node( $graph, $cb ) {
	foreach ( $graph as $n ) { if ( $cb( $n ) ) { return $n; } }
	return null;
}

// A service custom post type.
register_post_type( 'service', array( 'public' => true, 'label' => 'Services', 'has_archive' => true ) );

// Fill in a rich business profile.
update_option(
	'spr_business_profile',
	array(
		'business_type' => 'LocalBusiness',
		'local_subtype' => 'Plumber',
		'name'          => 'Acme Plumbing',
		'url'           => home_url( '/' ),
		'telephone'     => '+1-555-100',
		'email'         => 'hi@acme.test',
		'street'        => '1 Main St',
		'locality'      => 'Springfield',
		'region'        => 'IL',
		'postal_code'   => '62701',
		'country'       => 'US',
		'price_range'   => '$$',
		'opening_hours' => "Mo-Fr 09:00-17:00\nSa 10:00-14:00",
		'area_served'   => 'Springfield, Chatham',
		'facebook'      => 'https://facebook.com/acme',
		'instagram'     => 'https://instagram.com/acme',
	)
);
SPR_Business_Profile::flush_cache();

// Profile helpers.
check( 'schema_type resolves LocalBusiness subtype', 'Plumber' === SPR_Business_Profile::schema_type() );
check( 'same_as collects social URLs', count( SPR_Business_Profile::same_as() ) === 2 );
check( 'opening hours parse to 2 specs', count( SPR_Business_Profile::opening_hours_spec() ) === 2 );
check( 'profile is complete', SPR_Business_Profile::is_complete() );

$gen = new SPR_Schema_Generator();

// --- Article context ---
$post = wp_insert_post( array( 'post_title' => 'How to fix a leak', 'post_content' => '<p>Steps.</p>', 'post_status' => 'publish' ) );
$GLOBALS['wp_the_query'] = new WP_Query( array( 'p' => $post ) );
$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];
while ( $GLOBALS['wp_query']->have_posts() ) { $GLOBALS['wp_query']->the_post(); }
$graph = $gen->build_graph();
wp_reset_query();

$org = find_node( $graph, function ( $n ) { return isset( $n['@id'] ) && '#organization' === substr( $n['@id'], -13 ); } );
check( 'Organization node present', null !== $org );
check( 'Org @type = Plumber', $org && 'Plumber' === $org['@type'] );
check( 'Org name set', $org && 'Acme Plumbing' === $org['name'] );
check( 'Org has PostalAddress', $org && isset( $org['address']['streetAddress'] ) );
check( 'Org has openingHoursSpecification', $org && ! empty( $org['openingHoursSpecification'] ) );
check( 'Org has sameAs', $org && ! empty( $org['sameAs'] ) );
check( 'WebSite node present', null !== find_node( $graph, function ( $n ) { return 'WebSite' === $n['@type']; } ) );
check( 'WebSite has SearchAction', (bool) find_node( $graph, function ( $n ) { return 'WebSite' === $n['@type'] && ! empty( $n['potentialAction'] ); } ) );
check( 'WebPage node present', null !== find_node( $graph, function ( $n ) { return isset( $n['@type'] ) && 'WebPage' === $n['@type']; } ) );
$article = find_node( $graph, function ( $n ) { return 'Article' === $n['@type']; } );
check( 'Article node present', null !== $article );
check( 'Article headline matches', $article && 'How to fix a leak' === $article['headline'] );
check( 'Article publisher refs org', $article && isset( $article['publisher']['@id'] ) && '#organization' === substr( $article['publisher']['@id'], -13 ) );
check( 'BreadcrumbList present', null !== find_node( $graph, function ( $n ) { return 'BreadcrumbList' === $n['@type']; } ) );

// --- Service context ---
$svc = wp_insert_post( array( 'post_title' => 'Drain Cleaning', 'post_content' => '<p>We clean drains.</p>', 'post_status' => 'publish', 'post_type' => 'service' ) );
$GLOBALS['wp_the_query'] = new WP_Query( array( 'p' => $svc, 'post_type' => 'service' ) );
$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];
while ( $GLOBALS['wp_query']->have_posts() ) { $GLOBALS['wp_query']->the_post(); }
$graph2 = $gen->build_graph();
wp_reset_query();

$service = find_node( $graph2, function ( $n ) { return 'Service' === $n['@type']; } );
check( 'Service node present for service CPT', null !== $service );
check( 'Service name matches', $service && 'Drain Cleaning' === $service['name'] );
check( 'Service provider refs org', $service && isset( $service['provider']['@id'] ) && '#organization' === substr( $service['provider']['@id'], -13 ) );

// --- Multiple sitemaps setting ---
$s = (array) get_option( 'spr_settings' );
$s['sitemap_urls'] = "https://example.com/a.xml\nhttps://example.com/b.xml";
$s['sitemap_url']  = 'https://example.com/primary.xml';
update_option( 'spr_settings', $s );
SPR_Settings::flush_cache();
$urls = SPR_Settings::get_sitemap_urls();
check( 'get_sitemap_urls merges all three', count( $urls ) === 3 );

echo "\n===== $pass passed, $fail failed =====\n";
exit( $fail > 0 ? 1 : 0 );
