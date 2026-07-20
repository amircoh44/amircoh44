<?php
/**
 * Gathers image attachments and their metadata from the WordPress media library.
 *
 * @package WP_Image_Bulk_Downloader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPIBD_Image_Collector {

	public function get_all_image_ids() {
		$query = new WP_Query(
			array(
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'post_mime_type'         => 'image',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return $query->posts;
	}

	public function get_image_data( $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );
		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return null;
		}

		$post = get_post( $attachment_id );
		if ( ! $post ) {
			return null;
		}

		$uploads = wp_get_upload_dir();
		$relative_path = '';
		if ( ! empty( $uploads['basedir'] ) && 0 === strpos( $file_path, $uploads['basedir'] ) ) {
			$relative_path = ltrim( substr( $file_path, strlen( $uploads['basedir'] ) ), '/\\' );
		} else {
			$relative_path = basename( $file_path );
		}

		return array(
			'id'            => $attachment_id,
			'file_path'     => $file_path,
			'relative_path' => $relative_path,
			'filename'      => basename( $file_path ),
			'url'           => wp_get_attachment_url( $attachment_id ),
			'title'         => $post->post_title,
			'caption'       => $post->post_excerpt,
			'description'   => $post->post_content,
			'alt'           => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'mime_type'     => $post->post_mime_type,
			'date'          => $post->post_date,
		);
	}
}
