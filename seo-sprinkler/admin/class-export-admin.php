<?php
/**
 * "Export / Migrate" admin screen.
 *
 * Lets the site owner download the whole site as JSON (for import into Python
 * or another platform) and, optionally, a ZIP of the actual media files. Any
 * export that includes personal data (user logins/emails) requires an explicit
 * authorisation checkbox in addition to the export capability and a nonce.
 *
 * The JSON is streamed (never written to a public location). The media ZIP is
 * built in the system temp dir and streamed for download, then deleted.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_Export_Admin
 */
class SPR_Export_Admin {

	const CAP   = 'export';
	const PAGE  = 'spr-export';
	const NONCE = 'spr_export';

	/**
	 * Exporter.
	 *
	 * @var SPR_Exporter
	 */
	protected $exporter;

	/**
	 * Screen hook.
	 *
	 * @var string
	 */
	protected $screen = '';

	/**
	 * Constructor.
	 *
	 * @param SPR_Exporter $exporter Exporter.
	 */
	public function __construct( SPR_Exporter $exporter ) {
		$this->exporter = $exporter;
	}

	/**
	 * Hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_spr_export_json', array( $this, 'download_json' ) );
		add_action( 'admin_post_spr_export_zip_download', array( $this, 'download_zip' ) );
		add_action( 'wp_ajax_spr_export_zip_start', array( $this, 'ajax_zip_start' ) );
		add_action( 'wp_ajax_spr_export_zip_batch', array( $this, 'ajax_zip_batch' ) );
	}

	/**
	 * Register the submenu.
	 */
	public function register_menu() {
		$this->screen = add_submenu_page(
			'spr-dashboard',
			__( 'Export / Migrate', 'seo-sprinkler' ),
			__( 'Export / Migrate', 'seo-sprinkler' ),
			self::CAP,
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Enqueue assets.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue( $hook ) {
		if ( $hook !== $this->screen ) {
			return;
		}
		wp_enqueue_style( 'spr-admin', SPR_PLUGIN_URL . 'admin/css/admin.css', array(), SPR_VERSION );
		wp_enqueue_script( 'spr-export', SPR_PLUGIN_URL . 'admin/js/export.js', array( 'jquery' ), SPR_VERSION, true );
		wp_localize_script(
			'spr-export',
			'SPR_EXPORT',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'downloadUrl' => admin_url( 'admin-post.php' ),
				'nonce'       => wp_create_nonce( self::NONCE ),
				'i18n'        => array(
					'preparing' => __( 'Preparing…', 'seo-sprinkler' ),
					'adding'    => __( 'Adding media…', 'seo-sprinkler' ),
					'ready'     => __( 'ZIP ready — downloading…', 'seo-sprinkler' ),
					'error'     => __( 'Something went wrong.', 'seo-sprinkler' ),
					'needAuth'  => __( 'Please tick the authorisation box first.', 'seo-sprinkler' ),
				),
			)
		);
	}

	/**
	 * Render the page.
	 */
	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to export this site.', 'seo-sprinkler' ) );
		}
		$exporter  = $this->exporter;
		$zip_ready = class_exists( 'ZipArchive' );
		$locked    = ! SPR_Edition::can( 'export' ); // Export is an Expert feature.
		require SPR_PLUGIN_DIR . 'admin/views/page-export.php';
	}

	/* ---------------------------------------------------------------------
	 * Option parsing + guards
	 * ------------------------------------------------------------------- */

	/**
	 * Read + validate export options from the request.
	 *
	 * @param array $src Request array ($_POST or $_GET, already unslashed by caller).
	 * @return array
	 */
	protected function read_options( $src ) {
		$bool = function ( $key ) use ( $src ) {
			return ! empty( $src[ $key ] );
		};

		$post_types = array();
		if ( ! empty( $src['post_types'] ) ) {
			$post_types = array_map( 'sanitize_key', (array) $src['post_types'] );
		}

		$opts = array(
			'post_types'          => $post_types,
			'include_media'       => $bool( 'include_media' ),
			'include_taxonomies'  => $bool( 'include_taxonomies' ),
			'include_settings'    => $bool( 'include_settings' ),
			'include_seo'         => $bool( 'include_seo' ),
			'include_users'       => $bool( 'include_users' ),
			'include_emails'      => $bool( 'include_emails' ),
			'include_comments'    => $bool( 'include_comments' ),
			'include_all_options' => $bool( 'include_all_options' ),
		);

		// Emails imply users.
		if ( $opts['include_emails'] ) {
			$opts['include_users'] = true;
		}

		return $opts;
	}

	/**
	 * True when the request includes personal data.
	 *
	 * @param array $opts Options.
	 * @return bool
	 */
	protected function has_pii( $opts ) {
		return ! empty( $opts['include_users'] ) || ! empty( $opts['include_emails'] ) || ! empty( $opts['include_all_options'] );
	}

	/**
	 * Shared guard for downloads: capability + nonce + PII authorisation.
	 *
	 * @param array  $src         Request data (unslashed).
	 * @param string $nonce_field Nonce field name.
	 * @param string $nonce_value Expected nonce value/action.
	 * @return array Parsed options (dies on failure).
	 */
	protected function guard_request( $src, $nonce_field, $nonce_value ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You are not allowed to export this site.', 'seo-sprinkler' ), 403 );
		}
		if ( ! SPR_Edition::can( 'export' ) ) {
			wp_die( esc_html__( 'The Export / Migrate tool is an Expert feature. Please upgrade to use it.', 'seo-sprinkler' ), 402 );
		}
		if ( empty( $src[ $nonce_field ] ) || ! wp_verify_nonce( $src[ $nonce_field ], $nonce_value ) ) {
			wp_die( esc_html__( 'Security check failed. Please reload and try again.', 'seo-sprinkler' ), 403 );
		}
		$opts = $this->read_options( $src );
		if ( $this->has_pii( $opts ) && empty( $src['authorize'] ) ) {
			wp_die( esc_html__( 'This export includes personal data. You must confirm you are authorised to export it.', 'seo-sprinkler' ), 403 );
		}
		return $opts;
	}

	/* ---------------------------------------------------------------------
	 * JSON download (streamed)
	 * ------------------------------------------------------------------- */

	/**
	 * Stream the manifest as a JSON download.
	 */
	public function download_json() {
		$src  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification -- verified in guard.
		$opts = $this->guard_request( $src, 'spr_export_nonce', self::NONCE );

		$json = wp_json_encode( $this->exporter->build_manifest( $opts ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		// Optional gzip: compressed bytes don't match the script/HTML text
		// heuristics that make some antivirus tools quarantine a content export.
		$compress = ! empty( $src['compress'] ) && function_exists( 'gzencode' );

		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		if ( $compress ) {
			$body = gzencode( $json, 6 );
			header( 'Content-Type: application/gzip' );
			header( 'Content-Disposition: attachment; filename="' . $this->filename( 'json.gz' ) . '"' );
			header( 'Content-Length: ' . strlen( $body ) );
			echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- gzip download body.
		} else {
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . $this->filename( 'json' ) . '"' );
			header( 'Content-Length: ' . strlen( $json ) );
			echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download body.
		}
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Media ZIP (batched build + streamed download)
	 * ------------------------------------------------------------------- */

	/**
	 * AJAX guard for the ZIP builders.
	 *
	 * @return array Options.
	 */
	protected function ajax_guard() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'seo-sprinkler' ) ), 403 );
		}
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'seo-sprinkler' ) ), 403 );
		}
		if ( ! SPR_Edition::can( 'export' ) ) {
			wp_send_json_error( array( 'message' => __( 'The Export / Migrate tool is an Expert feature.', 'seo-sprinkler' ), 'upgrade' => SPR_Edition::upgrade_url() ), 402 );
		}
		if ( ! class_exists( 'ZipArchive' ) ) {
			wp_send_json_error( array( 'message' => __( 'ZipArchive (PHP zip extension) is not available on this server.', 'seo-sprinkler' ) ) );
		}
		$opts = $this->read_options( wp_unslash( $_POST ) );
		if ( $this->has_pii( $opts ) && empty( $_POST['authorize'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Confirm you are authorised to export personal data first.', 'seo-sprinkler' ) ), 403 );
		}
		return $opts;
	}

	/**
	 * AJAX: start a ZIP — write the manifest, list the media files.
	 */
	public function ajax_zip_start() {
		$opts  = $this->ajax_guard();
		$token = md5( uniqid( (string) wp_rand(), true ) );
		$path  = trailingslashit( get_temp_dir() ) . 'spr-export-' . $token . '.zip';

		$zip = new ZipArchive();
		if ( true !== $zip->open( $path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not create the ZIP file.', 'seo-sprinkler' ) ) );
		}
		$json = wp_json_encode( $this->exporter->build_manifest( $opts ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$zip->addFromString( 'manifest.json', $json );
		$zip->addFromString( 'SECURITY-README.txt', SPR_Exporter::security_notice() . "\n" );
		$zip->close();

		$ids = array_keys( $this->exporter->media_files() );

		set_transient(
			'spr_export_' . $token,
			array(
				'path'  => $path,
				'ids'   => $ids,
				'index' => 0,
			),
			HOUR_IN_SECONDS
		);

		wp_send_json_success(
			array(
				'token' => $token,
				'total' => count( $ids ),
			)
		);
	}

	/**
	 * AJAX: add a batch of media files to the ZIP.
	 */
	public function ajax_zip_batch() {
		$this->ajax_guard();

		$token = isset( $_POST['token'] ) ? preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_POST['token'] ) ) : '';
		$state = $token ? get_transient( 'spr_export_' . $token ) : false;
		if ( ! is_array( $state ) ) {
			wp_send_json_error( array( 'message' => __( 'Export session expired. Please start again.', 'seo-sprinkler' ) ) );
		}

		$per_batch = 20;
		$zip       = new ZipArchive();
		if ( true !== $zip->open( $state['path'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not open the ZIP file.', 'seo-sprinkler' ) ) );
		}

		$ids   = $state['ids'];
		$start = (int) $state['index'];
		$end   = min( $start + $per_batch, count( $ids ) );
		for ( $i = $start; $i < $end; $i++ ) {
			$id   = (int) $ids[ $i ];
			$file = get_attached_file( $id );
			if ( $file && file_exists( $file ) ) {
				$zip->addFile( $file, 'media/' . $id . '-' . basename( $file ) );
			}
		}
		$zip->close();

		$state['index'] = $end;
		set_transient( 'spr_export_' . $token, $state, HOUR_IN_SECONDS );

		$done = $end >= count( $ids );
		wp_send_json_success(
			array(
				'scanned' => $end,
				'total'   => count( $ids ),
				'done'    => $done,
				'token'   => $token,
			)
		);
	}

	/**
	 * Stream the finished ZIP, then delete it.
	 */
	public function download_zip() {
		if ( ! current_user_can( self::CAP ) || ! SPR_Edition::can( 'export' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'seo-sprinkler' ), 403 );
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			wp_die( esc_html__( 'Security check failed.', 'seo-sprinkler' ), 403 );
		}
		$token = isset( $_GET['token'] ) ? preg_replace( '/[^a-f0-9]/', '', (string) wp_unslash( $_GET['token'] ) ) : '';
		$state = $token ? get_transient( 'spr_export_' . $token ) : false;
		if ( ! is_array( $state ) || empty( $state['path'] ) || ! file_exists( $state['path'] ) ) {
			wp_die( esc_html__( 'Export not found or expired.', 'seo-sprinkler' ), 404 );
		}

		$path = $state['path'];
		nocache_headers();
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $this->filename( 'zip' ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		wp_delete_file( $path );
		delete_transient( 'spr_export_' . $token );
		exit;
	}

	/**
	 * Build a download filename.
	 *
	 * @param string $ext Extension.
	 * @return string
	 */
	protected function filename( $ext ) {
		$slug = sanitize_title( get_bloginfo( 'name' ) );
		$slug = $slug ? $slug : 'site';
		return 'spr-export-' . $slug . '-' . gmdate( 'Ymd-His' ) . '.' . $ext;
	}
}
