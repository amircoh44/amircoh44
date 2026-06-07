<?php
/**
 * "Image Distribution" admin screen.
 *
 * Finds every article below the image minimum, lets you select them, and fills
 * each one with related, randomised images from the media library — inserted
 * straight into the content with alt text (AI-written when AI is connected, a
 * clean fallback otherwise). Density is either a target number of images per
 * article, or one image per N words. Each fill is backed up (revert from the
 * post editor's SEO Sprinkler box).
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_Image_Distribution_Admin
 */
class SPR_Image_Distribution_Admin {

	const CAP   = 'manage_options';
	const PAGE  = 'spr-image-distribution';
	const NONCE = 'spr_imgdist';

	/** @var SPR_Image_Scanner */
	protected $images;
	/** @var SPR_Image_Filler */
	protected $filler;

	/** @var string */
	protected $screen = '';

	/**
	 * Constructor.
	 *
	 * @param SPR_Image_Scanner $images Image scanner.
	 * @param SPR_Image_Filler  $filler Image filler engine.
	 */
	public function __construct( $images, $filler ) {
		$this->images = $images;
		$this->filler = $filler;
	}

	/**
	 * Hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_spr_imgdist_scan', array( $this, 'ajax_scan' ) );
		add_action( 'wp_ajax_spr_imgdist_fill', array( $this, 'ajax_fill' ) );
	}

	/**
	 * Register the submenu.
	 */
	public function register_menu() {
		$this->screen = add_submenu_page(
			'spr-dashboard',
			__( 'Image Distribution', 'seo-sprinkler' ),
			__( 'Image Distribution', 'seo-sprinkler' ),
			self::CAP,
			self::PAGE,
			array( $this, 'render' ),
			11
		);
	}

	/**
	 * Enqueue assets on our screen.
	 *
	 * @param string $hook Screen hook.
	 */
	public function enqueue( $hook ) {
		if ( $hook !== $this->screen ) {
			return;
		}
		wp_enqueue_style( 'spr-admin', SPR_PLUGIN_URL . 'admin/css/admin.css', array(), SPR_VERSION );
		wp_enqueue_script( 'spr-imgdist', SPR_PLUGIN_URL . 'admin/js/image-distribution.js', array( 'jquery' ), SPR_VERSION, true );
		wp_localize_script(
			'spr-imgdist',
			'SPR_IMGDIST',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'i18n'    => array(
					'scanning'  => __( 'Finding articles below the minimum…', 'seo-sprinkler' ),
					'noneFound' => __( 'Great — every audited article already meets the image minimum.', 'seo-sprinkler' ),
					'filling'   => __( 'Filling', 'seo-sprinkler' ),
					'done'      => __( 'Done.', 'seo-sprinkler' ),
					'doneOne'   => __( 'added %d image(s)', 'seo-sprinkler' ),
					'skipNone'  => __( 'no suitable images in the library', 'seo-sprinkler' ),
					'skipEnough' => __( 'already had enough', 'seo-sprinkler' ),
					'error'     => __( 'Something went wrong.', 'seo-sprinkler' ),
					'pickSome'  => __( 'Select at least one article first.', 'seo-sprinkler' ),
					'confirm'   => __( 'Insert images into the selected articles? This updates the saved content (each change is backed up and can be reverted from the post editor).', 'seo-sprinkler' ),
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
		$min       = (int) $this->images->get_minimum();
		$ai_ready  = SPR_Edition::can( 'ai' ) && SPR_AI::is_configured();
		require SPR_PLUGIN_DIR . 'admin/views/page-image-distribution.php';
	}

	/* ---------------------------------------------------------------------
	 * AJAX
	 * ------------------------------------------------------------------- */

	/**
	 * Verify nonce + capability or die.
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
	 * Batch-scan for articles below the image minimum.
	 */
	public function ajax_scan() {
		$this->guard();
		$paged = isset( $_POST['paged'] ) ? max( 1, absint( wp_unslash( $_POST['paged'] ) ) ) : 1;
		wp_send_json_success( $this->images->scan_batch( $paged, 50 ) );
	}

	/**
	 * Fill one selected post. The client passes the IDs already used this run via
	 * `exclude[]`, and we return the IDs we used so it can keep spreading.
	 */
	public function ajax_fill() {
		$this->guard();

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit this post.', 'seo-sprinkler' ) ), 403 );
		}

		$exclude = isset( $_POST['exclude'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['exclude'] ) ) : array();

		$result = $this->filler->bulk_fill(
			$post_id,
			array(
				'align'     => isset( $_POST['align'] ) ? sanitize_key( wp_unslash( $_POST['align'] ) ) : 'center',
				'size'      => isset( $_POST['size'] ) ? sanitize_key( wp_unslash( $_POST['size'] ) ) : 'large',
				'mode'      => isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'per_article',
				'target'    => isset( $_POST['target'] ) ? absint( wp_unslash( $_POST['target'] ) ) : 0,
				'per_words' => isset( $_POST['per_words'] ) ? absint( wp_unslash( $_POST['per_words'] ) ) : 200,
				'alt_mode'  => ( isset( $_POST['alt_mode'] ) && 'ai' === $_POST['alt_mode'] ) ? 'ai' : 'auto',
				'exclude'   => $exclude,
			)
		);

		wp_send_json_success( $result );
	}
}
