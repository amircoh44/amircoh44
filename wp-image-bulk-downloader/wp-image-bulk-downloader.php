<?php
/**
 * Plugin Name:       Image Bulk Downloader
 * Plugin URI:        https://example.com/wp-image-bulk-downloader
 * Description:       One-click download of all WordPress media library images as a ZIP file. Choose to export images only, preserve upload folder paths, or include image metadata (alt text, caption, description, title).
 * Version:           1.0.0
 * Requires at least: 5.6
 * Requires PHP:      7.2
 * Author:            Image Bulk Downloader
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

define( 'WPIBD_VERSION', '1.0.0' );
define( 'WPIBD_PLUGIN_FILE', __FILE__ );
define( 'WPIBD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPIBD_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPIBD_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once WPIBD_PLUGIN_DIR . 'includes/class-wpibd-zip-builder.php';
require_once WPIBD_PLUGIN_DIR . 'includes/class-wpibd-image-collector.php';
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
