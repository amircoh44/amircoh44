<?php
/**
 * "Content Cleaner" admin screen.
 *
 * Scan for generative junk, clean it (permanently, with a per-post backup), and
 * review/undo every change from the log.
 *
 * @package SeoArticleBooster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SAB_Cleaner_Admin
 */
class SAB_Cleaner_Admin {

	const CAP   = 'manage_options';
	const PAGE  = 'sab-cleaner';
	const NONCE = 'sab_cleaner';

	/**
	 * Cleaner engine.
	 *
	 * @var SAB_Content_Cleaner
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
	 * @param SAB_Content_Cleaner $cleaner Cleaner engine.
	 */
	public function __construct( SAB_Content_Cleaner $cleaner ) {
		$this->cleaner = $cleaner;
	}

	/**
	 * Hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_sab_scan_junk', array( $this, 'ajax_scan' ) );
		add_action( 'wp_ajax_sab_clean_junk', array( $this, 'ajax_clean' ) );
		add_action( 'wp_ajax_sab_revert_clean', array( $this, 'ajax_revert' ) );
	}

	/**
	 * Register the submenu.
	 */
	public function register_menu() {
		$this->screen = add_submenu_page(
			'sab-dashboard',
			__( 'Content Cleaner', 'seo-article-booster' ),
			__( 'Content Cleaner', 'seo-article-booster' ),
			self::CAP,
			self::PAGE,
			array( $this, 'render' )
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
		wp_enqueue_style( 'sab-admin', SAB_PLUGIN_URL . 'admin/css/admin.css', array(), SAB_VERSION );
		wp_enqueue_script( 'sab-cleaner', SAB_PLUGIN_URL . 'admin/js/cleaner.js', array( 'jquery' ), SAB_VERSION, true );
		wp_localize_script(
			'sab-cleaner',
			'SAB_CLEAN',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'i18n'    => array(
					'scanning'      => __( 'Scanning…', 'seo-article-booster' ),
					'cleaning'      => __( 'Cleaning…', 'seo-article-booster' ),
					'done'          => __( 'Done.', 'seo-article-booster' ),
					'error'         => __( 'Something went wrong.', 'seo-article-booster' ),
					'reverted'      => __( 'Reverted.', 'seo-article-booster' ),
					'confirmClean'  => __( 'This permanently rewrites your post content. A per-post backup is saved so you can revert. Continue?', 'seo-article-booster' ),
					'confirmRevert' => __( 'Restore this post to its pre-clean content?', 'seo-article-booster' ),
					'noIssues'      => __( 'No generative junk found in the scanned posts.', 'seo-article-booster' ),
				),
			)
		);
	}

	/**
	 * Render the page.
	 */
	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'seo-article-booster' ) );
		}
		$cleaner = $this->cleaner;
		require SAB_PLUGIN_DIR . 'admin/views/page-cleaner.php';
	}

	/* ---------------------------------------------------------------------
	 * AJAX
	 * ------------------------------------------------------------------- */

	/**
	 * Nonce + capability guard.
	 */
	protected function guard() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'seo-article-booster' ) ), 403 );
		}
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'seo-article-booster' ) ), 403 );
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
		wp_send_json_success( $this->cleaner->clean_batch( $this->paged(), 20 ) );
	}

	/**
	 * Revert a single post's clean.
	 */
	public function ajax_revert() {
		$this->guard();
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit this post.', 'seo-article-booster' ) ), 403 );
		}
		$result = $this->cleaner->revert_post( $post_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'post_id' => $post_id ) );
	}
}
