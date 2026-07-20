<?php
/**
 * Builds the export ZIP archive in chunks so large libraries don't hit PHP limits.
 *
 * @package WP_Image_Bulk_Downloader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPIBD_Zip_Builder {

	const CHUNK_SIZE         = 20;
	const JOB_TRANSIENT_TTL  = HOUR_IN_SECONDS;
	const JOB_DIR_NAME       = 'wpibd-exports';

	public function create_job( array $attachment_ids, $mode, $include_site_info = false, $download_as_gz = false ) {
		$dir = $this->get_export_dir();
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		try {
			$job_id = bin2hex( random_bytes( 8 ) );
		} catch ( Exception $e ) {
			$job_id = strtolower( wp_generate_password( 16, false, false ) );
		}
		$zip_path = trailingslashit( $dir ) . 'images-' . $job_id . '.zip';

		if ( ! is_writable( $dir ) ) {
			return new WP_Error( 'wpibd_dir_not_writable', __( 'Export directory is not writable.', 'wp-image-bulk-downloader' ) );
		}

		$job = array(
			'job_id'            => $job_id,
			'mode'              => $mode,
			'include_site_info' => (bool) $include_site_info,
			'download_as_gz'    => (bool) $download_as_gz,
			'zip_path'          => $zip_path,
			'attachment_ids'    => array_values( array_map( 'absint', $attachment_ids ) ),
			'total'             => count( $attachment_ids ),
			'processed'         => 0,
			'failed'            => array(),
			'metadata_rows'     => array(),
			'used_paths'        => array(),
			'created_at'        => time(),
			'chunk_size'        => self::CHUNK_SIZE,
		);

		$this->save_job( $job );

		return $job;
	}

	public function process_chunk( $job_id, $offset ) {
		$job = $this->get_job( $job_id );
		if ( ! $job ) {
			return new WP_Error( 'wpibd_job_missing', __( 'Export job not found or expired.', 'wp-image-bulk-downloader' ) );
		}

		$zip = new ZipArchive();
		$open_result = $zip->open( $job['zip_path'], ZipArchive::CREATE );
		if ( true !== $open_result ) {
			return new WP_Error(
				'wpibd_zip_open',
				sprintf(
					/* translators: %s: ZipArchive error code. */
					__( 'Could not open ZIP archive (code %s).', 'wp-image-bulk-downloader' ),
					$open_result
				)
			);
		}

		$collector = new WPIBD_Image_Collector();
		$ids       = array_slice( $job['attachment_ids'], $offset, self::CHUNK_SIZE );

		foreach ( $ids as $attachment_id ) {
			$data = $collector->get_image_data( $attachment_id );
			if ( ! $data ) {
				$job['failed'][] = $attachment_id;
				++$job['processed'];
				continue;
			}

			$entry_path = $this->resolve_entry_path( $data, $job );
			if ( ! $zip->addFile( $data['file_path'], $entry_path ) ) {
				$job['failed'][] = $attachment_id;
				++$job['processed'];
				continue;
			}

			if ( 'with_metadata' === $job['mode'] || 'astro_export' === $job['mode'] ) {
				$job['metadata_rows'][] = array(
					'id'          => $data['id'],
					'file'        => $entry_path,
					'url'         => $data['url'],
					'title'       => $data['title'],
					'alt'         => $data['alt'],
					'caption'     => $data['caption'],
					'description' => $data['description'],
					'mime_type'   => $data['mime_type'],
					'date'        => $data['date'],
				);
			}

			++$job['processed'];
		}

		$zip->close();

		$next_offset = $offset + count( $ids );
		$done        = $next_offset >= $job['total'];

		if ( $done && ( 'with_metadata' === $job['mode'] || 'astro_export' === $job['mode'] ) && ! empty( $job['metadata_rows'] ) ) {
			$this->append_metadata_files( $job );
		}

		if ( $done && ! empty( $job['include_site_info'] ) ) {
			$this->append_site_info_files( $job );
		}

		if ( $done && 'astro_export' === $job['mode'] ) {
			$this->append_astro_export( $job );
		}

		$this->save_job( $job );

		$response = array(
			'job_id'    => $job['job_id'],
			'processed' => $job['processed'],
			'total'     => $job['total'],
			'failed'    => count( $job['failed'] ),
			'done'      => $done,
			'next'      => $done ? null : $next_offset,
		);

		if ( $done ) {
			$download_args = array(
				'action' => 'wpibd_download_zip',
				'job_id' => $job['job_id'],
				'nonce'  => wp_create_nonce( 'wpibd_download_nonce' ),
			);
			if ( ! empty( $job['download_as_gz'] ) ) {
				$download_args['format'] = 'gz';
			}
			$response['download_url'] = add_query_arg( $download_args, admin_url( 'admin-ajax.php' ) );
			$response['filename']     = 'wp-images-' . gmdate( 'Y-m-d-His' ) . '.zip' . ( ! empty( $job['download_as_gz'] ) ? '.gz' : '' );
		}

		return $response;
	}

	public function stream_zip( $job_id, $format = 'zip' ) {
		$job = $this->get_job( $job_id );
		if ( ! $job || ! file_exists( $job['zip_path'] ) ) {
			wp_die( esc_html__( 'Export archive is missing or expired.', 'wp-image-bulk-downloader' ), '', array( 'response' => 404 ) );
		}

		$as_gz = ( 'gz' === $format ) || ! empty( $job['download_as_gz'] );

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );

		if ( $as_gz ) {
			$gz_path = $this->build_gz_copy( $job['zip_path'] );
			if ( ! $gz_path || ! file_exists( $gz_path ) ) {
				wp_die( esc_html__( 'Could not build gzip download.', 'wp-image-bulk-downloader' ), '', array( 'response' => 500 ) );
			}
			$filename = 'wp-images-' . gmdate( 'Y-m-d-His' ) . '.zip.gz';
			header( 'Content-Type: application/gzip' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Content-Length: ' . filesize( $gz_path ) );
			readfile( $gz_path );
			@unlink( $gz_path );
		} else {
			$filename = 'wp-images-' . gmdate( 'Y-m-d-His' ) . '.zip';
			header( 'Content-Type: application/zip' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Content-Length: ' . filesize( $job['zip_path'] ) );
			readfile( $job['zip_path'] );
		}

		$this->cleanup_job( $job_id );
		exit;
	}

	private function build_gz_copy( $zip_path ) {
		if ( ! function_exists( 'gzopen' ) ) {
			return null;
		}

		$gz_path = $zip_path . '.gz';
		$in      = @fopen( $zip_path, 'rb' );
		if ( ! $in ) {
			return null;
		}
		$out = @gzopen( $gz_path, 'wb1' );
		if ( ! $out ) {
			fclose( $in );
			return null;
		}

		while ( ! feof( $in ) ) {
			$buf = fread( $in, 65536 );
			if ( false === $buf || '' === $buf ) {
				break;
			}
			gzwrite( $out, $buf );
		}

		fclose( $in );
		gzclose( $out );

		return $gz_path;
	}

	public function cleanup_job( $job_id ) {
		$job = $this->get_job( $job_id );
		if ( $job && ! empty( $job['zip_path'] ) && file_exists( $job['zip_path'] ) ) {
			@unlink( $job['zip_path'] );
		}
		delete_transient( $this->job_key( $job_id ) );
	}

	private function resolve_entry_path( array $data, array &$job ) {
		switch ( $job['mode'] ) {
			case 'astro_export':
				$entry = 'public/images/' . $data['relative_path'];
				break;
			case 'with_paths':
			case 'with_metadata':
				$entry = $data['relative_path'];
				break;
			case 'images_only':
			default:
				$entry = $data['filename'];
				break;
		}

		$entry = $this->sanitize_zip_path( $entry );
		$entry = $this->unique_entry_path( $entry, $job['used_paths'] );

		$job['used_paths'][ strtolower( $entry ) ] = true;

		return $entry;
	}

	private function sanitize_zip_path( $path ) {
		$path = str_replace( '\\', '/', $path );
		$parts = array();
		foreach ( explode( '/', $path ) as $segment ) {
			if ( '' === $segment || '.' === $segment || '..' === $segment ) {
				continue;
			}
			$parts[] = sanitize_file_name( $segment );
		}
		if ( empty( $parts ) ) {
			$parts[] = 'image';
		}
		return implode( '/', $parts );
	}

	private function unique_entry_path( $entry, array $used ) {
		if ( ! isset( $used[ strtolower( $entry ) ] ) ) {
			return $entry;
		}

		$dir       = dirname( $entry );
		$dir       = ( '.' === $dir ) ? '' : $dir . '/';
		$basename  = basename( $entry );
		$extension = '';
		if ( false !== strpos( $basename, '.' ) ) {
			$extension = '.' . pathinfo( $basename, PATHINFO_EXTENSION );
			$basename  = pathinfo( $basename, PATHINFO_FILENAME );
		}

		$i = 1;
		do {
			$candidate = $dir . $basename . '-' . $i . $extension;
			++$i;
		} while ( isset( $used[ strtolower( $candidate ) ] ) );

		return $candidate;
	}

	private function append_metadata_files( array $job ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $job['zip_path'] ) ) {
			return;
		}

		$csv = $this->build_csv( $job['metadata_rows'] );
		$zip->addFromString( 'image-metadata.csv', $csv );

		$json = wp_json_encode( $job['metadata_rows'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false !== $json ) {
			$zip->addFromString( 'image-metadata.json', $json );
		}

		$zip->close();
	}

	private function append_astro_export( array $job ) {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $job['zip_path'] ) ) {
			return;
		}

		if ( class_exists( 'WPIBD_Astro_Exporter' ) ) {
			$astro = new WPIBD_Astro_Exporter();
			$astro->write_all( $zip );
		}

		if ( class_exists( 'WPIBD_Project_Handover' ) ) {
			$handover = new WPIBD_Project_Handover( true );
			$handover->write_all( $zip );
		}

		$zip->close();
	}

	private function append_site_info_files( array $job ) {
		if ( ! class_exists( 'WPIBD_Site_Info_Collector' ) ) {
			return;
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $job['zip_path'] ) ) {
			return;
		}

		$collector = new WPIBD_Site_Info_Collector();
		$info      = $collector->collect();

		$json = wp_json_encode( $info, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false !== $json ) {
			$zip->addFromString( 'site-info.json', $json );
		}

		$zip->addFromString( 'site-info.txt', $collector->format_as_text( $info ) );

		$zip->close();
	}

	private function build_csv( array $rows ) {
		$columns = array( 'id', 'file', 'url', 'title', 'alt', 'caption', 'description', 'mime_type', 'date' );
		$fh      = fopen( 'php://temp', 'w+' );
		fputcsv( $fh, $columns );
		foreach ( $rows as $row ) {
			$line = array();
			foreach ( $columns as $col ) {
				$line[] = isset( $row[ $col ] ) ? (string) $row[ $col ] : '';
			}
			fputcsv( $fh, $line );
		}
		rewind( $fh );
		$csv = stream_get_contents( $fh );
		fclose( $fh );
		return $csv;
	}

	private function get_export_dir() {
		$uploads = wp_get_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'wpibd_uploads_unavailable', $uploads['error'] );
		}

		$dir = trailingslashit( $uploads['basedir'] ) . self::JOB_DIR_NAME;
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'wpibd_dir_create', __( 'Could not create export directory.', 'wp-image-bulk-downloader' ) );
		}

		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			@file_put_contents( $htaccess, "Require all denied\nDeny from all\n" );
		}
		$index = $dir . '/index.html';
		if ( ! file_exists( $index ) ) {
			@file_put_contents( $index, '' );
		}

		return $dir;
	}

	private function job_key( $job_id ) {
		return 'wpibd_job_' . $job_id;
	}

	private function save_job( array $job ) {
		set_transient( $this->job_key( $job['job_id'] ), $job, self::JOB_TRANSIENT_TTL );
	}

	private function get_job( $job_id ) {
		$job = get_transient( $this->job_key( $job_id ) );
		return is_array( $job ) ? $job : null;
	}
}
