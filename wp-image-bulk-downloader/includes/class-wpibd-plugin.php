<?php
/**
 * Main plugin class — bootstraps admin UI and AJAX endpoints.
 *
 * @package WP_Image_Bulk_Downloader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPIBD_Plugin {

	private static $instance = null;

	private $admin;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->admin = new WPIBD_Admin();

		add_action( 'admin_menu', array( $this->admin, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this->admin, 'enqueue_assets' ) );

		add_action( 'wp_ajax_wpibd_start_export', array( $this, 'ajax_start_export' ) );
		add_action( 'wp_ajax_wpibd_export_chunk', array( $this, 'ajax_export_chunk' ) );
		add_action( 'wp_ajax_wpibd_download_zip', array( $this, 'ajax_download_zip' ) );
		add_action( 'wp_ajax_wpibd_cancel_export', array( $this, 'ajax_cancel_export' ) );
	}

	private function verify_request() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to export images.', 'wp-image-bulk-downloader' ) ),
				403
			);
		}
		check_ajax_referer( 'wpibd_export_nonce', 'nonce' );
	}

	public function ajax_start_export() {
		$this->verify_request();

		$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'images_only';
		if ( ! in_array( $mode, array( 'images_only', 'with_paths', 'with_metadata', 'astro_export' ), true ) ) {
			$mode = 'images_only';
		}

		$include_site_info = isset( $_POST['include_site_info'] ) && '1' === (string) $_POST['include_site_info'];

		$collector      = new WPIBD_Image_Collector();
		$attachment_ids = $collector->get_all_image_ids();

		if ( empty( $attachment_ids ) ) {
			wp_send_json_error(
				array( 'message' => __( 'No images found in the media library.', 'wp-image-bulk-downloader' ) )
			);
		}

		$zip_builder = new WPIBD_Zip_Builder();
		$job         = $zip_builder->create_job( $attachment_ids, $mode, $include_site_info );

		if ( is_wp_error( $job ) ) {
			wp_send_json_error( array( 'message' => $job->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'job_id'     => $job['job_id'],
				'total'      => $job['total'],
				'chunk_size' => $job['chunk_size'],
				'mode'       => $mode,
			)
		);
	}

	public function ajax_export_chunk() {
		$this->verify_request();

		$job_id = isset( $_POST['job_id'] ) ? sanitize_key( wp_unslash( $_POST['job_id'] ) ) : '';
		$offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;

		if ( empty( $job_id ) ) {
			wp_send_json_error( array( 'message' => __( 'Missing export job.', 'wp-image-bulk-downloader' ) ) );
		}

		$zip_builder = new WPIBD_Zip_Builder();
		$result      = $zip_builder->process_chunk( $job_id, $offset );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	public function ajax_download_zip() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You do not have permission to download this file.', 'wp-image-bulk-downloader' ), '', array( 'response' => 403 ) );
		}

		$nonce = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'wpibd_download_nonce' ) ) {
			wp_die( esc_html__( 'Invalid security token.', 'wp-image-bulk-downloader' ), '', array( 'response' => 403 ) );
		}

		$job_id = isset( $_GET['job_id'] ) ? sanitize_key( wp_unslash( $_GET['job_id'] ) ) : '';
		if ( empty( $job_id ) ) {
			wp_die( esc_html__( 'Missing export job.', 'wp-image-bulk-downloader' ), '', array( 'response' => 400 ) );
		}

		$zip_builder = new WPIBD_Zip_Builder();
		$zip_builder->stream_zip( $job_id );
	}

	public function ajax_cancel_export() {
		$this->verify_request();

		$job_id = isset( $_POST['job_id'] ) ? sanitize_key( wp_unslash( $_POST['job_id'] ) ) : '';
		if ( ! empty( $job_id ) ) {
			$zip_builder = new WPIBD_Zip_Builder();
			$zip_builder->cleanup_job( $job_id );
		}

		wp_send_json_success();
	}
}
