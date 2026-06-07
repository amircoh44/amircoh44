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
 * @package SeoSprinkler
 */

// Only run from the official WordPress uninstall flow.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// 1. Settings + distribution rules + cleaner log.
delete_option( 'spr_settings' );
delete_option( 'spr_injection_rules' );
delete_option( 'spr_cleaner_log' );
delete_option( 'spr_business_profile' );
delete_option( 'spr_edition' );

// 2. Cached transients.
delete_transient( 'spr_link_index' );
delete_transient( 'spr_sitemap_urls' );

// 3. Per-post metadata created by the plugin.
$meta_keys = array(
	'_spr_image_count',
	'_spr_links_applied',
	'_spr_inbound_sources',
	'_spr_schema_types',
	'_spr_schema_checked',
	'_spr_links_internal',
	'_spr_links_external',
	'_spr_cleaner_backup',
	'_spr_cleaner_backup_time',
	'_spr_ignore_h1',
	'_spr_imagefill_backup',
);
foreach ( $meta_keys as $meta_key ) {
	delete_post_meta_by_key( $meta_key );
}

// 4. Scheduled events.
wp_clear_scheduled_hook( 'spr_rebuild_index_event' );
wp_clear_scheduled_hook( 'spr_daily_refresh_event' );
