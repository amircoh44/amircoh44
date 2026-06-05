<?php
/**
 * Plugin Name:       SEO Article Booster
 * Plugin URI:        https://github.com/amircoh44/amircoh44
 * Description:        Boost your SEO: enforce a minimum number of images per article, audit articles for Schema.org structured data, and build an internal-linking index from the Yoast SEO sitemap to link related content automatically. New posts/pages automatically receive inbound internal links from older related articles.
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            amircoh44
 * Author URI:        https://github.com/amircoh44
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       seo-article-booster
 * Domain Path:       /languages
 *
 * @package SeoArticleBooster
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
define( 'SAB_VERSION', '1.0.0' );
define( 'SAB_PLUGIN_FILE', __FILE__ );
define( 'SAB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );   // .../seo-article-booster/
define( 'SAB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );    // https://.../seo-article-booster/
define( 'SAB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Key used to store all plugin settings in a single wp_options row.
define( 'SAB_OPTION_KEY', 'sab_settings' );

// Post-meta key that caches how many images each post contains.
define( 'SAB_META_IMAGE_COUNT', '_sab_image_count' );

// Transient key that caches the generated internal-link index.
define( 'SAB_TRANSIENT_INDEX', 'sab_link_index' );

// Transient key that caches the parsed Yoast sitemap URLs.
define( 'SAB_TRANSIENT_SITEMAP', 'sab_sitemap_urls' );

/*
 * ---------------------------------------------------------------------------
 * Autoload the plugin classes.
 * ---------------------------------------------------------------------------
 * A tiny, explicit "loader" keeps things readable for newcomers: every class
 * lives in its own file named class-{slug}.php. We require them once here.
 */
require_once SAB_PLUGIN_DIR . 'includes/class-settings.php';
require_once SAB_PLUGIN_DIR . 'includes/class-image-scanner.php';
require_once SAB_PLUGIN_DIR . 'includes/class-schema-scanner.php';
require_once SAB_PLUGIN_DIR . 'includes/class-sitemap-parser.php';
require_once SAB_PLUGIN_DIR . 'includes/class-link-replacer.php';
require_once SAB_PLUGIN_DIR . 'includes/class-link-index.php';
require_once SAB_PLUGIN_DIR . 'includes/class-link-injector.php';
require_once SAB_PLUGIN_DIR . 'includes/class-link-applier.php';
require_once SAB_PLUGIN_DIR . 'includes/class-new-post-linker.php';
require_once SAB_PLUGIN_DIR . 'includes/class-ajax.php';
require_once SAB_PLUGIN_DIR . 'includes/class-plugin.php';

if ( is_admin() ) {
	require_once SAB_PLUGIN_DIR . 'admin/class-admin.php';
}

/**
 * Boot the plugin once all plugins are loaded.
 *
 * Using the `plugins_loaded` hook (rather than running at file include time)
 * guarantees WordPress core, and optional integrations such as Yoast SEO,
 * are available before we wire up our own hooks.
 *
 * @return SAB_Plugin
 */
function sab() {
	return SAB_Plugin::instance();
}
add_action( 'plugins_loaded', 'sab' );

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
function sab_activate() {
	SAB_Settings::install_defaults();

	// Build the link index on the next request rather than blocking activation.
	if ( ! wp_next_scheduled( 'sab_rebuild_index_event' ) ) {
		wp_schedule_single_event( time() + 30, 'sab_rebuild_index_event' );
	}

	// Daily refresh of the sitemap + index so links stay current.
	if ( ! wp_next_scheduled( 'sab_daily_refresh_event' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'sab_daily_refresh_event' );
	}
}
register_activation_hook( __FILE__, 'sab_activate' );

/**
 * Runs on plugin deactivation. Clears scheduled events and caches but keeps
 * user settings (those are removed only on uninstall).
 */
function sab_deactivate() {
	wp_clear_scheduled_hook( 'sab_rebuild_index_event' );
	wp_clear_scheduled_hook( 'sab_daily_refresh_event' );

	delete_transient( SAB_TRANSIENT_INDEX );
	delete_transient( SAB_TRANSIENT_SITEMAP );
}
register_deactivation_hook( __FILE__, 'sab_deactivate' );
