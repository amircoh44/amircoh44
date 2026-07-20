<?php
/**
 * Plugin Name:       Image Bulk Downloader
 * Plugin URI:        https://github.com/amircoh44/wp-image-bulk-downloader
 * Description:       One-click bulk download of your WordPress media library as a ZIP — or a full site export ready to migrate to Astro (posts, pages, custom post types, taxonomies, menus, authors, comments, redirects, plus a project-handover folder with spreadsheets, a redacted connections dossier, and a database inventory).
 * Version:           1.2.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            Amir Cohen
 * Author URI:        https://www.adamchimneysweep.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-image-bulk-downloader
 * Domain Path:       /languages
 *
 * @package WP_Image_Bulk_Downloader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPIBD_VERSION', '1.2.0' );
define( 'WPIBD_PLUGIN_FILE', __FILE__ );
define( 'WPIBD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPIBD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPIBD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once WPIBD_PLUGIN_DIR . 'includes/class-wpibd-zip-builder.php';
require_once WPIBD_PLUGIN_DIR . 'includes/class-wpibd-image-collector.php';
require_once WPIBD_PLUGIN_DIR . 'includes/class-wpibd-site-info-collector.php';
require_once WPIBD_PLUGIN_DIR . 'includes/class-wpibd-astro-exporter.php';
require_once WPIBD_PLUGIN_DIR . 'includes/class-wpibd-project-handover.php';
require_once WPIBD_PLUGIN_DIR . 'includes/class-wpibd-admin.php';
require_once WPIBD_PLUGIN_DIR . 'includes/class-wpibd-plugin.php';

function wpibd_load_textdomain() {
	load_plugin_textdomain(
		'wp-image-bulk-downloader',
		false,
		dirname( WPIBD_PLUGIN_BASENAME ) . '/languages'
	);
}
add_action( 'plugins_loaded', 'wpibd_load_textdomain' );

function wpibd_activate() {
	if ( ! class_exists( 'ZipArchive' ) ) {
		deactivate_plugins( WPIBD_PLUGIN_BASENAME );
		wp_die(
			esc_html__( 'Image Bulk Downloader requires the PHP ZipArchive extension. Please enable it on your server and try again.', 'wp-image-bulk-downloader' ),
			esc_html__( 'Plugin Activation Error', 'wp-image-bulk-downloader' ),
			array( 'back_link' => true )
		);
	}
}
register_activation_hook( __FILE__, 'wpibd_activate' );

function wpibd() {
	return WPIBD_Plugin::instance();
}
wpibd();
