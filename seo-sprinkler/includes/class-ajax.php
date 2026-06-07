<?php
/**
 * AJAX endpoints for the admin tools.
 *
 * Every handler verifies the shared nonce and the manage_options capability
 * before doing anything, then delegates to the relevant service class. Long
 * jobs are batched: the browser keeps calling back with the next page until
 * the response reports `done`.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_Ajax
 */
class SPR_Ajax {

	/**
	 * Nonce action/name shared by all plugin AJAX calls.
	 */
	const NONCE = 'spr_ajax';

	/**
	 * Image scanner.
	 *
	 * @var SPR_Image_Scanner
	 */
	protected $scanner;

	/**
	 * Schema scanner.
	 *
	 * @var SPR_Schema_Scanner
	 */
	protected $schema;

	/**
	 * Link index.
	 *
	 * @var SPR_Link_Index
	 */
	protected $index;

	/**
	 * Link applier.
	 *
	 * @var SPR_Link_Applier
	 */
	protected $applier;

	/**
	 * Sitemap parser.
	 *
	 * @var SPR_Sitemap_Parser
	 */
	protected $sitemap;

	/**
	 * Constructor.
	 *
	 * @param SPR_Image_Scanner  $scanner Image scanner.
	 * @param SPR_Schema_Scanner $schema  Schema scanner.
	 * @param SPR_Link_Index     $index   Index.
	 * @param SPR_Link_Applier   $applier Applier.
	 * @param SPR_Sitemap_Parser $sitemap Sitemap parser.
	 */
	public function __construct( SPR_Image_Scanner $scanner, SPR_Schema_Scanner $schema, SPR_Link_Index $index, SPR_Link_Applier $applier, SPR_Sitemap_Parser $sitemap ) {
		$this->scanner = $scanner;
		$this->schema  = $schema;
		$this->index   = $index;
		$this->applier = $applier;
		$this->sitemap = $sitemap;
	}

	/**
	 * Register all AJAX actions (admin only).
	 */
	public function init() {
		add_action( 'wp_ajax_spr_scan_images', array( $this, 'scan_images' ) );
		add_action( 'wp_ajax_spr_scan_schema', array( $this, 'scan_schema' ) );
		add_action( 'wp_ajax_spr_scan_sitemap_schema', array( $this, 'scan_sitemap_schema' ) );
		add_action( 'wp_ajax_spr_rebuild_index', array( $this, 'rebuild_index' ) );
		add_action( 'wp_ajax_spr_refresh_sitemap', array( $this, 'refresh_sitemap' ) );
		add_action( 'wp_ajax_spr_apply_links', array( $this, 'apply_links' ) );
		add_action( 'wp_ajax_spr_revert_links', array( $this, 'revert_links' ) );
	}

	/**
	 * Shared guard: verify nonce + capability, or die with a JSON error.
	 */
	protected function guard() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please reload the page.', 'seo-sprinkler' ) ), 403 );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'seo-sprinkler' ) ), 403 );
		}
	}

	/**
	 * Read the requested batch page from the request.
	 *
	 * @return int
	 */
	protected function paged() {
		return isset( $_POST['paged'] ) ? max( 1, absint( wp_unslash( $_POST['paged'] ) ) ) : 1;
	}

	/* ---------------------------------------------------------------------
	 * Handlers
	 * ------------------------------------------------------------------- */

	/**
	 * Scan a batch of posts for image deficiencies.
	 */
	public function scan_images() {
		$this->guard();
		wp_send_json_success( $this->scanner->scan_batch( $this->paged(), 50 ) );
	}

	/**
	 * Scan a batch of posts for missing/insufficient structured data.
	 *
	 * Uses a small batch size because each post triggers an HTTP request.
	 */
	public function scan_schema() {
		$this->guard();
		wp_send_json_success( $this->schema->scan_batch( $this->paged(), 10 ) );
	}

	/**
	 * Scan a batch of sitemap URLs for structured data (sitemap-synced audit).
	 */
	public function scan_sitemap_schema() {
		$this->guard();
		wp_send_json_success( $this->schema->scan_sitemap_batch( $this->paged(), 10 ) );
	}

	/**
	 * Rebuild the internal-link index in one shot.
	 */
	public function rebuild_index() {
		$this->guard();
		$count = $this->index->rebuild();
		wp_send_json_success(
			array(
				'count'   => $count,
				'message' => sprintf(
					/* translators: %d: number of anchor phrases indexed. */
					_n( 'Indexed %d anchor phrase.', 'Indexed %d anchor phrases.', $count, 'seo-sprinkler' ),
					$count
				),
			)
		);
	}

	/**
	 * Re-fetch the Yoast sitemap and report how many URLs were found.
	 */
	public function refresh_sitemap() {
		$this->guard();
		$urls  = $this->sitemap->get_all_urls( true );
		$error = $this->sitemap->get_last_error();

		if ( empty( $urls ) && $error ) {
			wp_send_json_error( array( 'message' => $error ) );
		}

		wp_send_json_success(
			array(
				'count'   => count( $urls ),
				'message' => sprintf(
					/* translators: %d: number of URLs found in the sitemap. */
					_n( 'Found %d URL in the Yoast sitemap.', 'Found %d URLs in the Yoast sitemap.', count( $urls ), 'seo-sprinkler' ),
					count( $urls )
				),
			)
		);
	}

	/**
	 * Permanently apply links to a batch of posts.
	 */
	public function apply_links() {
		$this->guard();
		$this->require_edition( 'bulk_apply' );
		wp_send_json_success( $this->applier->apply_batch( $this->paged(), 20 ) );
	}

	/**
	 * Strip permanently-applied links from a batch of posts.
	 */
	public function revert_links() {
		$this->guard();
		// Reverting is always allowed (safety), even on Free.
		wp_send_json_success( $this->applier->revert_batch( $this->paged(), 20 ) );
	}

	/**
	 * Send an upgrade error and stop if the current edition lacks a feature.
	 *
	 * @param string $feature Feature key.
	 */
	protected function require_edition( $feature ) {
		if ( ! SPR_Edition::can( $feature ) ) {
			wp_send_json_error(
				array(
					'message'  => sprintf(
						/* translators: %s: required edition label. */
						__( 'This is a %s feature. Please upgrade to use it.', 'seo-sprinkler' ),
						SPR_Edition::label( SPR_Edition::required_for( $feature ) )
					),
					'upgrade'  => SPR_Edition::upgrade_url(),
				),
				402
			);
		}
	}
}
