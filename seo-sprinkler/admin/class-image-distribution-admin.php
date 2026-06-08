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

	/** Option that saves the last "below minimum" scan so it survives reloads. */
	const SNAPSHOT = 'spr_imgdist_snapshot';

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
		add_action( 'wp_ajax_spr_imgdist_propose', array( $this, 'ajax_propose' ) );
		add_action( 'wp_ajax_spr_imgdist_apply', array( $this, 'ajax_apply' ) );
		add_action( 'wp_ajax_spr_imgdist_remove', array( $this, 'ajax_remove' ) );
		add_action( 'wp_ajax_spr_imgdist_icons', array( $this, 'ajax_icons' ) );
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
					'skipBuilder' => __( 'page builder — skipped', 'seo-sprinkler' ),
					'error'     => __( 'Something went wrong.', 'seo-sprinkler' ),
					'pickSome'  => __( 'Select at least one article first.', 'seo-sprinkler' ),
					'confirm'   => __( 'Insert images into the selected articles? This updates the saved content (each change is backed up and can be reverted from the post editor).', 'seo-sprinkler' ),
					'reviewing' => __( 'Reviewing', 'seo-sprinkler' ),
					'approve'   => __( 'Approve & insert', 'seo-sprinkler' ),
					'skip'      => __( 'Skip', 'seo-sprinkler' ),
					'approveAll' => __( 'Approve all remaining', 'seo-sprinkler' ),
					'finish'    => __( 'Finish', 'seo-sprinkler' ),
					'inserted'  => __( 'Inserted', 'seo-sprinkler' ),
					'skipped'   => __( 'Skipped', 'seo-sprinkler' ),
					'altLabel'  => __( 'Alt text', 'seo-sprinkler' ),
					'capLabel'  => __( 'Caption', 'seo-sprinkler' ),
					'ttlLabel'  => __( 'Image title', 'seo-sprinkler' ),
					'descLabel' => __( 'Image description', 'seo-sprinkler' ),
					'reviewConfirm' => __( 'Review images one by one for the selected articles? Each insert updates the saved content (backed up, revertable).', 'seo-sprinkler' ),
					'removing'  => __( 'Removing', 'seo-sprinkler' ),
					'removed'   => __( 'removed', 'seo-sprinkler' ),
					'removeNone' => __( 'no inserted images', 'seo-sprinkler' ),
					'removeConfirm' => __( 'Remove the images SEO Sprinkler inserted from the selected articles? This rewrites the saved content (your other content is untouched).', 'seo-sprinkler' ),
					'iconsConfirm' => __( 'Sprinkle context-matched icons into the selected articles? Each icon is chosen to relate to the heading/paragraph it leads, scattered and left-aligned.', 'seo-sprinkler' ),
					'noIcons'   => __( 'no icon-sized images in the library', 'seo-sprinkler' ),
					'iconsNoPoints' => __( 'nowhere to place icons', 'seo-sprinkler' ),
					'sprinkling' => __( 'Sprinkling icons', 'seo-sprinkler' ),
					'alignCenter' => __( 'Middle', 'seo-sprinkler' ),
					'alignLeft' => __( 'Left', 'seo-sprinkler' ),
					'alignRight' => __( 'Right', 'seo-sprinkler' ),
					'reviewAllConfirm' => __( 'Build an editable preview of every proposed image for the selected articles?', 'seo-sprinkler' ),
					'gathering' => __( 'Preparing images…', 'seo-sprinkler' ),
					'insertingAll' => __( 'Inserting', 'seo-sprinkler' ),
					'raEmpty'   => __( 'Nothing to preview — the selected articles already meet the target, or no library images were found.', 'seo-sprinkler' ),
					'selected'  => __( 'to insert', 'seo-sprinkler' ),
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
		$snapshot  = $this->snapshot();
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
		$batch = $this->images->scan_batch( $paged, 50 );

		// Save the below-minimum list so it survives reloads (page 1 resets it).
		$snap         = ( 1 === $paged ) ? array( 'rows' => array(), 'updated' => 0 ) : $this->snapshot();
		$snap['rows'] = array_merge( $snap['rows'], $batch['deficient'] );
		if ( ! empty( $batch['done'] ) ) {
			$snap['updated'] = time();
			if ( class_exists( 'SPR_Activity' ) ) {
				SPR_Activity::log( 'image_scan', sprintf( /* translators: %d: count */ __( 'Image scan: %d article(s) below the minimum.', 'seo-sprinkler' ), count( $snap['rows'] ) ) );
			}
		}
		update_option( self::SNAPSHOT, $snap, false );

		wp_send_json_success( $batch );
	}

	/**
	 * Stored snapshot of the last below-minimum scan: { rows, updated }.
	 *
	 * @return array
	 */
	public function snapshot() {
		$snap = get_option( self::SNAPSHOT, array() );
		if ( ! is_array( $snap ) ) {
			$snap = array();
		}
		return array(
			'rows'    => ( isset( $snap['rows'] ) && is_array( $snap['rows'] ) ) ? $snap['rows'] : array(),
			'updated' => isset( $snap['updated'] ) ? (int) $snap['updated'] : 0,
		);
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
				'icons_only' => ! empty( $_POST['icons'] ),
				'format'    => isset( $_POST['format'] ) ? sanitize_key( wp_unslash( $_POST['format'] ) ) : 'auto',
			)
		);

		if ( class_exists( 'SPR_Activity' ) && ! empty( $result['inserted'] ) ) {
			SPR_Activity::log( 'image_fill', sprintf( /* translators: 1: count, 2: post title */ _n( 'Added %1$d image to "%2$s".', 'Added %1$d images to "%2$s".', (int) $result['inserted'], 'seo-sprinkler' ), (int) $result['inserted'], get_the_title( $post_id ) ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Propose images (with editable metadata) for one post in the reviewer.
	 */
	public function ajax_propose() {
		$this->guard();
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit this post.', 'seo-sprinkler' ) ), 403 );
		}
		$post    = get_post( $post_id );
		$mode    = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'per_article';
		$target  = isset( $_POST['target'] ) ? absint( wp_unslash( $_POST['target'] ) ) : 0;
		$perw    = isset( $_POST['per_words'] ) ? absint( wp_unslash( $_POST['per_words'] ) ) : 200;
		$use_ai  = isset( $_POST['alt_mode'] ) && 'ai' === $_POST['alt_mode'];
		$exclude = isset( $_POST['exclude'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['exclude'] ) ) : array();

		$need      = $this->filler->needed_for( $post, $mode, $target, $perw );
		$proposals = ( $need > 0 ) ? $this->filler->propose_for_post( $post_id, $need, $exclude, $use_ai ) : array();

		wp_send_json_success(
			array(
				'title'     => get_the_title( $post ),
				'edit_link' => get_edit_post_link( $post_id, 'raw' ),
				'need'      => $need,
				'proposals' => $proposals,
			)
		);
	}

	/**
	 * Insert one approved image (with edited alt / caption / title / description).
	 */
	public function ajax_apply() {
		$this->guard();
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		$att     = isset( $_POST['attachment_id'] ) ? absint( wp_unslash( $_POST['attachment_id'] ) ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit this post.', 'seo-sprinkler' ) ), 403 );
		}

		$res = $this->filler->apply_single(
			$post_id,
			$att,
			array(
				'align'       => isset( $_POST['align'] ) ? sanitize_key( wp_unslash( $_POST['align'] ) ) : 'center',
				'size'        => isset( $_POST['size'] ) ? sanitize_key( wp_unslash( $_POST['size'] ) ) : 'large',
				'alt'         => isset( $_POST['alt'] ) ? sanitize_text_field( wp_unslash( $_POST['alt'] ) ) : '',
				'caption'     => isset( $_POST['caption'] ) ? sanitize_text_field( wp_unslash( $_POST['caption'] ) ) : '',
				'title'       => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
				'description' => isset( $_POST['description'] ) ? wp_kses_post( wp_unslash( $_POST['description'] ) ) : '',
				'format'      => isset( $_POST['format'] ) ? sanitize_key( wp_unslash( $_POST['format'] ) ) : 'auto',
			)
		);

		if ( class_exists( 'SPR_Activity' ) && ! empty( $res['inserted'] ) ) {
			SPR_Activity::log( 'image_apply', sprintf( /* translators: %s: post title */ __( 'Approved 1 image into "%s".', 'seo-sprinkler' ), get_the_title( $post_id ) ) );
		}

		wp_send_json_success( $res );
	}

	/**
	 * Remove the images this tool inserted from one post.
	 */
	public function ajax_remove() {
		$this->guard();
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit this post.', 'seo-sprinkler' ) ), 403 );
		}

		$res = $this->filler->remove_inserted( $post_id );

		if ( class_exists( 'SPR_Activity' ) && ! empty( $res['removed'] ) ) {
			SPR_Activity::log(
				'image_remove',
				sprintf(
					/* translators: 1: count, 2: post title. */
					_n( 'Removed %1$d inserted image from "%2$s".', 'Removed %1$d inserted images from "%2$s".', (int) $res['removed'], 'seo-sprinkler' ),
					(int) $res['removed'],
					get_the_title( $post_id )
				)
			);
		}

		wp_send_json_success( $res );
	}

	/**
	 * Sprinkle context-matched icons into one post (placement chosen by the user).
	 */
	public function ajax_icons() {
		$this->guard();
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit this post.', 'seo-sprinkler' ) ), 403 );
		}
		$placement = isset( $_POST['placement'] ) ? sanitize_key( wp_unslash( $_POST['placement'] ) ) : 'headings';
		if ( ! in_array( $placement, array( 'headings', 'paragraphs', 'top' ), true ) ) {
			$placement = 'headings';
		}
		$exclude = isset( $_POST['exclude'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['exclude'] ) ) : array();

		$res = $this->filler->sprinkle_icons(
			$post_id,
			array(
				'placement' => $placement,
				'exclude'   => $exclude,
			)
		);

		if ( class_exists( 'SPR_Activity' ) && ! empty( $res['inserted'] ) ) {
			SPR_Activity::log(
				'image_icons',
				sprintf(
					/* translators: 1: count, 2: post title. */
					_n( 'Sprinkled %1$d icon into "%2$s".', 'Sprinkled %1$d icons into "%2$s".', (int) $res['inserted'], 'seo-sprinkler' ),
					(int) $res['inserted'],
					get_the_title( $post_id )
				)
			);
		}

		wp_send_json_success( $res );
	}
}
