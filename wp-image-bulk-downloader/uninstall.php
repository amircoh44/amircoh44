<?php
/**
 * Cleans up plugin data when uninstalled.
 *
 * @package WP_Image_Bulk_Downloader
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$uploads = wp_get_upload_dir();
if ( empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) ) {
	$dir = trailingslashit( $uploads['basedir'] ) . 'wpibd-exports';
	if ( is_dir( $dir ) ) {
		$files = glob( $dir . '/*' );
		if ( is_array( $files ) ) {
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					@unlink( $file );
				}
			}
		}
		@rmdir( $dir );
	}
}

global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_wpibd_job_%' OR option_name LIKE '_transient_timeout_wpibd_job_%'" );
