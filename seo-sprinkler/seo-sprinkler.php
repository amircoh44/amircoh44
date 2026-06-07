<?php
/**
 * Plugin Name:       SEO Sprinkler
 * Plugin URI:        https://github.com/amircoh44/amircoh44
 * Description:        An ADDITIONAL on-page SEO toolkit that works alongside Yoast, Rank Math or AIOSEO (never a replacement) and does the things they don't: enforce an image minimum, fill articles with related images, audit & fix internal/external links, generate a complete JSON-LD schema graph from a business profile, distribute CTAs/shortcodes by tag, clean AI "generative junk", and export the whole site for migration. Distilled from 30 years of hands-on SEO and website building by Amir Khan.
 * Version:           1.20.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            Amir Khan
 * Author URI:        https://github.com/amircoh44
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       seo-sprinkler
 * Domain Path:       /languages
 *
 * @package SeoSprinkler
 */

// Exit if accessed directly. Never allow direct access to plugin files.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * ---------------------------------------------------------------------------
 * Plugin constants
 * ---------------------------------------------------------------------------
 * We centralise version, paths and the option/menu identifiers here so they
 * can be reused safely across every class without magic strings.
 */
define( 'SPR_VERSION', '1.20.0' );
define( 'SPR_PLUGIN_FILE', __FILE__ );
define( 'SPR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );   // .../seo-sprinkler/
define( 'SPR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );    // https://.../seo-sprinkler/
define( 'SPR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Key used to store all plugin settings in a single wp_options row.
define( 'SPR_OPTION_KEY', 'spr_settings' );

// Post-meta key that caches how many images each post contains.
define( 'SPR_META_IMAGE_COUNT', '_spr_image_count' );

// Transient key that caches the generated internal-link index.
define( 'SPR_TRANSIENT_INDEX', 'spr_link_index' );

// Transient key that caches the parsed Yoast sitemap URLs.
define( 'SPR_TRANSIENT_SITEMAP', 'spr_sitemap_urls' );

/*
 * ---------------------------------------------------------------------------
 * Autoload the plugin classes.
 * ---------------------------------------------------------------------------
 * A tiny, explicit "loader" keeps things readable for newcomers: every class
 * lives in its own file named class-{slug}.php. We require them once here.
 */
require_once SPR_PLUGIN_DIR . 'includes/class-settings.php';
require_once SPR_PLUGIN_DIR . 'includes/class-edition.php';
require_once SPR_PLUGIN_DIR . 'includes/class-activity.php';
require_once SPR_PLUGIN_DIR . 'includes/class-gsc.php';
require_once SPR_PLUGIN_DIR . 'includes/class-license-client.php';
require_once SPR_PLUGIN_DIR . 'includes/class-ai.php';
require_once SPR_PLUGIN_DIR . 'includes/class-business-profile.php';
require_once SPR_PLUGIN_DIR . 'includes/class-image-scanner.php';
require_once SPR_PLUGIN_DIR . 'includes/class-schema-scanner.php';
require_once SPR_PLUGIN_DIR . 'includes/class-schema-generator.php';
require_once SPR_PLUGIN_DIR . 'includes/class-sitemap-parser.php';
require_once SPR_PLUGIN_DIR . 'includes/class-link-replacer.php';
require_once SPR_PLUGIN_DIR . 'includes/class-link-index.php';
require_once SPR_PLUGIN_DIR . 'includes/class-link-injector.php';
require_once SPR_PLUGIN_DIR . 'includes/class-link-applier.php';
require_once SPR_PLUGIN_DIR . 'includes/class-new-post-linker.php';
require_once SPR_PLUGIN_DIR . 'includes/class-syndication.php';
require_once SPR_PLUGIN_DIR . 'includes/class-link-scanner.php';
require_once SPR_PLUGIN_DIR . 'includes/class-injection-rules.php';
require_once SPR_PLUGIN_DIR . 'includes/class-content-distributor.php';
require_once SPR_PLUGIN_DIR . 'includes/class-content-cleaner.php';
require_once SPR_PLUGIN_DIR . 'includes/class-heading-checker.php';
require_once SPR_PLUGIN_DIR . 'includes/class-image-filler.php';
require_once SPR_PLUGIN_DIR . 'includes/class-seo-detector.php';
require_once SPR_PLUGIN_DIR . 'includes/class-exporter.php';
require_once SPR_PLUGIN_DIR . 'includes/class-ajax.php';
require_once SPR_PLUGIN_DIR . 'includes/class-plugin.php';

if ( is_admin() ) {
	require_once SPR_PLUGIN_DIR . 'admin/class-admin.php';
	require_once SPR_PLUGIN_DIR . 'admin/class-distribution-admin.php';
	require_once SPR_PLUGIN_DIR . 'admin/class-link-audit-admin.php';
	require_once SPR_PLUGIN_DIR . 'admin/class-cleaner-admin.php';
	require_once SPR_PLUGIN_DIR . 'admin/class-business-admin.php';
	require_once SPR_PLUGIN_DIR . 'admin/class-export-admin.php';
	require_once SPR_PLUGIN_DIR . 'admin/class-image-distribution-admin.php';
	require_once SPR_PLUGIN_DIR . 'admin/class-gsc-admin.php';
}

/**
 * Boot the plugin once all plugins are loaded.
 *
 * Using the `plugins_loaded` hook (rather than running at file include time)
 * guarantees WordPress core, and optional integrations such as Yoast SEO,
 * are available before we wire up our own hooks.
 *
 * @return SPR_Plugin
 */
function spr() {
	return SPR_Plugin::instance();
}
add_action( 'plugins_loaded', 'spr' );

/*
 * ---------------------------------------------------------------------------
 * Activation / Deactivation lifecycle.
 * ---------------------------------------------------------------------------
 */

/**
 * Runs on plugin activation.
 *
 * Seeds default settings (without overwriting any the user already saved) and
 * schedules a background index rebuild.
 */
function spr_activate() {
	SPR_Settings::install_defaults();

	// Build the link index on the next request rather than blocking activation.
	if ( ! wp_next_scheduled( 'spr_rebuild_index_event' ) ) {
		wp_schedule_single_event( time() + 30, 'spr_rebuild_index_event' );
	}

	// Daily refresh of the sitemap + index so links stay current.
	if ( ! wp_next_scheduled( 'spr_daily_refresh_event' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'spr_daily_refresh_event' );
	}
}
register_activation_hook( __FILE__, 'spr_activate' );

/**
 * Runs on plugin deactivation. Clears scheduled events and caches but keeps
 * user settings (those are removed only on uninstall).
 */
function spr_deactivate() {
	wp_clear_scheduled_hook( 'spr_rebuild_index_event' );
	wp_clear_scheduled_hook( 'spr_daily_refresh_event' );
	wp_clear_scheduled_hook( 'spr_gsc_daily_event' );

	delete_transient( SPR_TRANSIENT_INDEX );
	delete_transient( SPR_TRANSIENT_SITEMAP );
}
register_deactivation_hook( __FILE__, 'spr_deactivate' );
