<?php
/**
 * Uninstall routine.
 *
 * Runs only when the plugin is deleted from the Plugins screen. Removes the
 * plugin's settings, caches, scheduled events and per-post metadata.
 *
 * Note: links that were *permanently applied* to post content are intentionally
 * left in place (they are valid links and part of your content). Use the
 * "Revert applied links" tool before deleting the plugin if you want them gone.
 *
 * @package SeoArticleBooster
 */

// Only run from the official WordPress uninstall flow.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// 1. Settings + distribution rules.
delete_option( 'sab_settings' );
delete_option( 'sab_injection_rules' );

// 2. Cached transients.
delete_transient( 'sab_link_index' );
delete_transient( 'sab_sitemap_urls' );

// 3. Per-post metadata created by the plugin.
$meta_keys = array(
	'_sab_image_count',
	'_sab_links_applied',
	'_sab_inbound_sources',
	'_sab_schema_types',
	'_sab_schema_checked',
	'_sab_links_internal',
	'_sab_links_external',
);
foreach ( $meta_keys as $meta_key ) {
	delete_post_meta_by_key( $meta_key );
}

// 4. Scheduled events.
wp_clear_scheduled_hook( 'sab_rebuild_index_event' );
wp_clear_scheduled_hook( 'sab_daily_refresh_event' );
