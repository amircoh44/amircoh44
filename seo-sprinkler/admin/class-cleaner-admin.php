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
		add_action( 'wp_ajax_spr_clean_one', array( $this, 'ajax_clean_one' ) );
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
					'confirmCleanSel' => __( 'Clean the selected articles? Unchecked articles are excluded. Each change is backed up and revertable.', 'seo-sprinkler' ),
					'confirmRevert' => __( 'Restore this post to its pre-clean content?', 'seo-sprinkler' ),
					'noIssues'      => __( 'No generative junk found in the scanned posts.', 'seo-sprinkler' ),
					'pickSome'      => __( 'Select at least one article first.', 'seo-sprinkler' ),
					'cleanedTag'    => __( 'Cleaned', 'seo-sprinkler' ),
					'cleanNone'     => __( 'Already clean', 'seo-sprinkler' ),
					'cleanLabel'    => __( 'Clean', 'seo-sprinkler' ),
					'selected'      => __( 'selected', 'seo-sprinkler' ),
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

		$paged = $this->paged();
		$batch = $this->cleaner->clean_batch( $paged, 20 );

		// Accumulate across the batched run and log one summary entry when finished.
		$acc            = ( 1 === $paged ) ? array() : (array) get_transient( 'spr_clean_run' );
		$acc['cleaned'] = (int) ( isset( $acc['cleaned'] ) ? $acc['cleaned'] : 0 ) + (int) $batch['cleaned'];
		$acc['removed'] = (int) ( isset( $acc['removed'] ) ? $acc['removed'] : 0 ) + (int) $batch['removed'];
		if ( ! empty( $batch['done'] ) ) {
			delete_transient( 'spr_clean_run' );
			if ( class_exists( 'SPR_Activity' ) && $acc['cleaned'] > 0 ) {
				SPR_Activity::log(
					'content_clean',
					sprintf(
						/* translators: 1: article count, 2: removed item count. */
						__( 'Content Cleaner: cleaned %1$d article(s), removed %2$d junk item(s).', 'seo-sprinkler' ),
						$acc['cleaned'],
						$acc['removed']
					)
				);
			}
		} else {
			set_transient( 'spr_clean_run', $acc, HOUR_IN_SECONDS );
		}

		wp_send_json_success( $batch );
	}

	/**
	 * Clean ONE post (used by per-row "Clean" and "Clean selected"), so the user
	 * can clean articles one by one and exclude any they don't want touched.
	 */
	public function ajax_clean_one() {
		$this->guard();
		if ( ! SPR_Edition::can( 'bulk_clean' ) ) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %s: edition label. */
						__( 'Cleaning is a %s feature. Scanning and reverting stay free.', 'seo-sprinkler' ),
						SPR_Edition::label( SPR_Edition::required_for( 'bulk_clean' ) )
					),
					'upgrade' => SPR_Edition::upgrade_url(),
				),
				402
			);
		}
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit this post.', 'seo-sprinkler' ) ), 403 );
		}

		$result  = $this->cleaner->clean_post( $post_id );
		$removed = ! empty( $result['changed'] ) ? array_sum( $result['stats'] ) : 0;

		if ( class_exists( 'SPR_Activity' ) && ! empty( $result['changed'] ) ) {
			SPR_Activity::log(
				'content_clean',
				sprintf(
					/* translators: 1: post title, 2: removed item count. */
					__( 'Cleaned "%1$s" (%2$d junk item(s)).', 'seo-sprinkler' ),
					get_the_title( $post_id ),
					(int) $removed
				)
			);
		}

		wp_send_json_success(
			array(
				'post_id' => $post_id,
				'changed' => ! empty( $result['changed'] ),
				'removed' => (int) $removed,
			)
		);
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
