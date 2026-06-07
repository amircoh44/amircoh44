<?php
/**
 * "Content Cleaner" admin screen.
 *
 * Scan for generative junk, clean it (permanently, with a per-post backup), and
 * review/undo every change from the log.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_Cleaner_Admin
 */
class SPR_Cleaner_Admin {

	const CAP   = 'manage_options';
	const PAGE  = 'spr-cleaner';
	const NONCE = 'spr_cleaner';

	/**
	 * Cleaner engine.
	 *
	 * @var SPR_Content_Cleaner
	 */
	protected $cleaner;

	/**
	 * Screen hook.
	 *
	 * @var string
	 */
	protected $screen = '';

	/**
	 * Constructor.
	 *
	 * @param SPR_Content_Cleaner $cleaner Cleaner engine.
	 */
	public function __construct( SPR_Content_Cleaner $cleaner ) {
		$this->cleaner = $cleaner;
	}

	/**
	 * Hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_spr_scan_junk', array( $this, 'ajax_scan' ) );
		add_action( 'wp_ajax_spr_clean_junk', array( $this, 'ajax_clean' ) );
		add_action( 'wp_ajax_spr_revert_clean', array( $this, 'ajax_revert' ) );
	}

	/**
	 * Register the submenu.
	 */
	public function register_menu() {
		$this->screen = add_submenu_page(
			'spr-dashboard',
			__( 'Content Cleaner', 'seo-sprinkler' ),
			__( 'Content Cleaner', 'seo-sprinkler' ),
			self::CAP,
			self::PAGE,
			array( $this, 'render' ),
			50
		);
	}

	/**
	 * Enqueue assets on our screen.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue( $hook ) {
		if ( $hook !== $this->screen ) {
			return;
		}
		wp_enqueue_style( 'spr-admin', SPR_PLUGIN_URL . 'admin/css/admin.css', array(), SPR_VERSION );
		wp_enqueue_script( 'spr-cleaner', SPR_PLUGIN_URL . 'admin/js/cleaner.js', array( 'jquery' ), SPR_VERSION, true );
		wp_localize_script(
			'spr-cleaner',
			'SPR_CLEAN',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'i18n'    => array(
					'scanning'      => __( 'Scanning…', 'seo-sprinkler' ),
					'cleaning'      => __( 'Cleaning…', 'seo-sprinkler' ),
					'done'          => __( 'Done.', 'seo-sprinkler' ),
					'error'         => __( 'Something went wrong.', 'seo-sprinkler' ),
					'reverted'      => __( 'Reverted.', 'seo-sprinkler' ),
					'confirmClean'  => __( 'This permanently rewrites your post content. A per-post backup is saved so you can revert. Continue?', 'seo-sprinkler' ),
					'confirmRevert' => __( 'Restore this post to its pre-clean content?', 'seo-sprinkler' ),
					'noIssues'      => __( 'No generative junk found in the scanned posts.', 'seo-sprinkler' ),
				),
			)
		);
	}

	/**
	 * Render the page.
	 */
	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'seo-sprinkler' ) );
		}
		$cleaner = $this->cleaner;
		require SPR_PLUGIN_DIR . 'admin/views/page-cleaner.php';
	}

	/* ---------------------------------------------------------------------
	 * AJAX
	 * ------------------------------------------------------------------- */

	/**
	 * Nonce + capability guard.
	 */
	protected function guard() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'seo-sprinkler' ) ), 403 );
		}
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'seo-sprinkler' ) ), 403 );
		}
	}

	/**
	 * Read the requested batch page.
	 *
	 * @return int
	 */
	protected function paged() {
		return isset( $_POST['paged'] ) ? max( 1, absint( wp_unslash( $_POST['paged'] ) ) ) : 1;
	}

	/**
	 * Scan (preview) for junk.
	 */
	public function ajax_scan() {
		$this->guard();
		wp_send_json_success( $this->cleaner->scan_batch( $this->paged(), 40 ) );
	}

	/**
	 * Clean (permanent, batched).
	 */
	public function ajax_clean() {
		$this->guard();
		if ( ! SPR_Edition::can( 'bulk_clean' ) ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: edition label. */
						__( 'Bulk cleaning is a %s feature. Scanning and reverting stay free.', 'seo-sprinkler' ),
						SPR_Edition::label( SPR_Edition::required_for( 'bulk_clean' ) )
					),
					'upgrade' => SPR_Edition::upgrade_url(),
				),
				402
			);
		}
		wp_send_json_success( $this->cleaner->clean_batch( $this->paged(), 20 ) );
	}

	/**
	 * Revert a single post's clean.
	 */
	public function ajax_revert() {
		$this->guard();
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit this post.', 'seo-sprinkler' ) ), 403 );
		}
		$result = $this->cleaner->revert_post( $post_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'post_id' => $post_id ) );
	}
}
