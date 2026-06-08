<?php
/**
 * Image filler + per-post "SEO Sprinkler" meta box.
 *
 * Adds a button to every post/page/custom-post editor that fills the content
 * with related (and randomised) images from the media library — choosing the
 * alignment (left/center/right) and size (thumbnail…full). Images are inserted
 * as Gutenberg image blocks (which also render fine in the classic editor) and
 * are distributed through the content so the article ends up "full" of visuals.
 *
 * The same meta box shows a live SEO checklist: image count vs the minimum,
 * internal/external link counts, schema status and the duplicate-H1 flag.
 *
 * Every fill backs up the original content first, so it can be reverted.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_Image_Filler
 */
class SPR_Image_Filler {

	const META_BACKUP = '_spr_imagefill_backup';
	const NONCE       = 'spr_metabox';
	const CSS_CLASS   = 'spr-auto-image';

	/** @var SPR_Image_Scanner */
	protected $images;
	/** @var SPR_Link_Scanner */
	protected $links;
	/** @var SPR_Schema_Scanner */
	protected $schema;
	/** @var SPR_Heading_Checker */
	protected $headings;

	/**
	 * Constructor.
	 *
	 * @param SPR_Image_Scanner   $images   Image scanner.
	 * @param SPR_Link_Scanner    $links    Link scanner.
	 * @param SPR_Schema_Scanner  $schema   Schema scanner.
	 * @param SPR_Heading_Checker $headings Heading checker.
	 */
	public function __construct( $images, $links, $schema, $headings ) {
		$this->images   = $images;
		$this->links    = $links;
		$this->schema   = $schema;
		$this->headings = $headings;
	}

	/**
	 * Hooks.
	 */
	public function init() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_spr_fill_images', array( $this, 'ajax_fill' ) );
		add_action( 'wp_ajax_spr_remove_images', array( $this, 'ajax_remove' ) );
		add_action( 'wp_ajax_spr_ai_generate', array( $this, 'ajax_ai' ) );
	}

	/* ---------------------------------------------------------------------
	 * Per-post SEO score (free)
	 * ------------------------------------------------------------------- */

	/**
	 * Compute a 0-100 on-page SEO score from the plugin's own signals.
	 *
	 * @param int|WP_Post $post Post.
	 * @return array{score:int,parts:array,words:int}
	 */
	public function seo_score( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return array( 'score' => 0, 'parts' => array(), 'words' => 0 );
		}

		$min    = max( 1, $this->images->get_minimum() );
		$imgs   = $this->images->count_for_post( $post );
		$links  = $this->links->count_for_post( $post );
		$schema = $this->schema->get_cached_types( $post->ID );
		$h1     = $this->headings->count_h1( $post->post_content );
		$words  = str_word_count( wp_strip_all_tags( $post->post_content ) );

		$parts = array(
			'images'   => (int) round( 25 * min( 1, $imgs / $min ) ),
			'internal' => $links['internal'] > 0 ? 20 : 0,
			'external' => $links['external'] > 0 ? 10 : 0,
			'schema'   => ( null !== $schema && $this->schema->passes( (array) $schema ) ) ? 20 : 0,
			'h1'       => $h1 < 1 ? 10 : 0,
			'length'   => (int) round( 15 * min( 1, $words / 300 ) ),
		);

		return array(
			'score' => min( 100, array_sum( $parts ) ),
			'parts' => $parts,
			'words' => $words,
		);
	}

	/**
	 * Post types that get the SEO Sprinkler meta box (all public, editable types).
	 *
	 * @return string[]
	 */
	protected function post_types() {
		$types = get_post_types( array( 'public' => true ) );
		unset( $types['attachment'] );
		/**
		 * Filter the post types that show the SEO Sprinkler meta box.
		 *
		 * @param string[] $types Post types.
		 */
		return apply_filters( 'spr_metabox_post_types', array_values( $types ) );
	}

	/* ---------------------------------------------------------------------
	 * Meta box
	 * ------------------------------------------------------------------- */

	/**
	 * Register the meta box.
	 */
	public function add_meta_box() {
		add_meta_box(
			'spr_metabox',
			__( 'SEO Sprinkler', 'seo-sprinkler' ),
			array( $this, 'render_meta_box' ),
			$this->post_types(),
			'side',
			'high'
		);
	}

	/**
	 * Render the meta box (checklist + fill tool).
	 *
	 * @param WP_Post $post Post.
	 */
	public function render_meta_box( $post ) {
		$min      = $this->images->get_minimum();
		$imgs     = $this->images->count_for_post( $post );
		$links    = $this->links->count_for_post( $post );
		$schema   = $this->schema->get_cached_types( $post->ID );
		$h1       = $this->headings->count_h1( $post->post_content );
		$to_min   = max( 0, $min - $imgs );

		$row = function ( $ok, $label ) {
			printf(
				'<li><span class="spr-badge %s"><span class="dashicons %s"></span></span> %s</li>',
				$ok ? 'spr-badge--ok' : 'spr-badge--warn',
				esc_attr( $ok ? 'dashicons-yes' : 'dashicons-warning' ),
				wp_kses_post( $label )
			);
		};

		$score    = $this->seo_score( $post );
		$score_cls = $score['score'] >= 80 ? 'ok' : ( $score['score'] >= 50 ? 'neutral' : 'warn' );
		?>
		<div class="spr-score spr-score--<?php echo esc_attr( $score_cls ); ?>">
			<span class="spr-score__num"><?php echo (int) $score['score']; ?></span><span class="spr-score__max">/100</span>
			<span class="spr-score__label"><?php esc_html_e( 'SEO score', 'seo-sprinkler' ); ?></span>
		</div>

		<ul class="spr-checklist spr-metabox-list">
			<?php
			$row( $imgs >= $min, sprintf( /* translators: 1: count, 2: min */ esc_html__( 'Images: %1$d / %2$d', 'seo-sprinkler' ), $imgs, $min ) );
			$row( $links['internal'] > 0, sprintf( /* translators: %d count */ esc_html__( 'Internal links: %d', 'seo-sprinkler' ), $links['internal'] ) );
			$row( $links['external'] > 0, sprintf( /* translators: %d count */ esc_html__( 'External links: %d', 'seo-sprinkler' ), $links['external'] ) );
			$row( null !== $schema && $this->schema->passes( (array) $schema ), null === $schema ? esc_html__( 'Schema: not checked', 'seo-sprinkler' ) : esc_html__( 'Schema present', 'seo-sprinkler' ) );
			$row( $h1 < 1, sprintf( /* translators: %d count */ esc_html__( 'Content H1s: %d', 'seo-sprinkler' ), $h1 ) );
			?>
		</ul>

		<hr />
		<p><strong><?php esc_html_e( 'Fill with images', 'seo-sprinkler' ); ?></strong></p>
		<p class="description"><?php esc_html_e( 'Inserts related (randomised) images from your library throughout the content. Saves the post and reloads — save any draft edits first.', 'seo-sprinkler' ); ?></p>

		<p>
			<label><?php esc_html_e( 'Alignment', 'seo-sprinkler' ); ?>
				<select id="spr-fill-align">
					<option value="center"><?php esc_html_e( 'Middle', 'seo-sprinkler' ); ?></option>
					<option value="left"><?php esc_html_e( 'Left', 'seo-sprinkler' ); ?></option>
					<option value="right"><?php esc_html_e( 'Right', 'seo-sprinkler' ); ?></option>
				</select>
			</label>
		</p>
		<p>
			<label><?php esc_html_e( 'Size', 'seo-sprinkler' ); ?>
				<select id="spr-fill-size">
					<option value="large"><?php esc_html_e( 'Full size', 'seo-sprinkler' ); ?></option>
					<option value="full"><?php esc_html_e( 'Original', 'seo-sprinkler' ); ?></option>
					<option value="medium"><?php esc_html_e( 'Medium', 'seo-sprinkler' ); ?></option>
					<option value="thumbnail"><?php esc_html_e( 'Thumbnail', 'seo-sprinkler' ); ?></option>
				</select>
			</label>
		</p>
		<p>
			<label><?php esc_html_e( 'How many', 'seo-sprinkler' ); ?>
				<input type="number" id="spr-fill-count" min="1" max="30" value="<?php echo esc_attr( max( 1, $to_min ? $to_min : 3 ) ); ?>" class="small-text" />
			</label>
			<label style="margin-left:8px"><?php esc_html_e( 'Every', 'seo-sprinkler' ); ?>
				<input type="number" id="spr-fill-every" min="1" max="10" value="2" class="small-text" /> <?php esc_html_e( 'paragraphs', 'seo-sprinkler' ); ?>
			</label>
		</p>

		<p>
			<button type="button" class="button button-primary" id="spr-fill-go" data-post="<?php echo esc_attr( $post->ID ); ?>"><?php esc_html_e( 'Insert images', 'seo-sprinkler' ); ?></button>
			<button type="button" class="button" id="spr-fill-remove" data-post="<?php echo esc_attr( $post->ID ); ?>" <?php echo metadata_exists( 'post', $post->ID, self::META_BACKUP ) ? '' : 'style="display:none"'; ?>><?php esc_html_e( 'Undo last fill', 'seo-sprinkler' ); ?></button>
		</p>
		<p id="spr-fill-status" class="description"></p>

		<hr />
		<p><strong><?php esc_html_e( 'AI assist', 'seo-sprinkler' ); ?></strong></p>
		<?php if ( ! SPR_Edition::can( 'ai' ) ) : ?>
			<p class="description">
				<?php esc_html_e( 'AI actions are a Pro feature.', 'seo-sprinkler' ); ?>
				<a href="<?php echo esc_url( SPR_Edition::upgrade_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade', 'seo-sprinkler' ); ?></a>
			</p>
		<?php elseif ( ! SPR_AI::is_configured() ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %s: settings link. */
					esc_html__( 'Add your AI endpoint and key in %s.', 'seo-sprinkler' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=spr-settings#spr_ai' ) ) . '">' . esc_html__( 'Settings → AI', 'seo-sprinkler' ) . '</a>'
				);
				?>
			</p>
		<?php else : ?>
			<p>
				<button type="button" class="button spr-ai-go" data-post="<?php echo esc_attr( $post->ID ); ?>" data-kind="meta_description"><?php esc_html_e( 'Meta description', 'seo-sprinkler' ); ?></button>
				<button type="button" class="button spr-ai-go" data-post="<?php echo esc_attr( $post->ID ); ?>" data-kind="title"><?php esc_html_e( 'SEO title', 'seo-sprinkler' ); ?></button>
			</p>
			<textarea id="spr-ai-result" rows="3" class="widefat" readonly placeholder="<?php esc_attr_e( 'AI output appears here — copy it into your SEO plugin.', 'seo-sprinkler' ); ?>"></textarea>
		<?php endif; ?>
		<?php
		wp_nonce_field( self::NONCE, 'spr_metabox_nonce' );
	}

	/**
	 * AJAX: generate text with the configured AI provider (Pro).
	 */
	public function ajax_ai() {
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		$this->guard( $post_id );

		if ( ! SPR_Edition::can( 'ai' ) ) {
			wp_send_json_error( array( 'message' => __( 'AI actions are a Pro feature.', 'seo-sprinkler' ) ), 402 );
		}
		if ( ! SPR_AI::is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'Configure your AI endpoint and key in Settings → AI.', 'seo-sprinkler' ) ) );
		}

		$kind   = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : 'meta_description';
		$result = ( 'title' === $kind ) ? SPR_AI::generate_title( $post_id ) : SPR_AI::generate_meta_description( $post_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'text' => $result ) );
	}

	/**
	 * Enqueue assets on the post editor.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		wp_enqueue_style( 'spr-admin', SPR_PLUGIN_URL . 'admin/css/admin.css', array(), SPR_VERSION );
		wp_enqueue_script( 'spr-metabox', SPR_PLUGIN_URL . 'admin/js/sprinkler.js', array( 'jquery' ), SPR_VERSION, true );
		wp_localize_script(
			'spr-metabox',
			'SPR_METABOX',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'i18n'    => array(
					'working'      => __( 'Working…', 'seo-sprinkler' ),
					'inserted'     => __( 'Inserted %d image(s). Reloading…', 'seo-sprinkler' ),
					'removed'      => __( 'Reverted. Reloading…', 'seo-sprinkler' ),
					'none'         => __( 'No suitable images were found in the media library.', 'seo-sprinkler' ),
					'error'        => __( 'Something went wrong.', 'seo-sprinkler' ),
					'confirmFill'  => __( 'This updates the saved post content and reloads the editor. Save any unsaved changes first. Continue?', 'seo-sprinkler' ),
					'confirmUndo'  => __( 'Restore the content from before the last image fill?', 'seo-sprinkler' ),
				),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * AJAX
	 * ------------------------------------------------------------------- */

	/**
	 * Guard: nonce + edit_post capability.
	 *
	 * @param int $post_id Post ID.
	 */
	protected function guard( $post_id ) {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'seo-sprinkler' ) ), 403 );
		}
		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit this post.', 'seo-sprinkler' ) ), 403 );
		}
	}

	/**
	 * AJAX: fill a post with images.
	 */
	public function ajax_fill() {
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		$this->guard( $post_id );

		$args = array(
			'align' => isset( $_POST['align'] ) ? sanitize_key( wp_unslash( $_POST['align'] ) ) : 'center',
			'size'  => isset( $_POST['size'] ) ? sanitize_key( wp_unslash( $_POST['size'] ) ) : 'large',
			'count' => isset( $_POST['count'] ) ? max( 1, min( 30, absint( wp_unslash( $_POST['count'] ) ) ) ) : 3,
			'every' => isset( $_POST['every'] ) ? max( 1, min( 10, absint( wp_unslash( $_POST['every'] ) ) ) ) : 2,
		);

		$inserted = $this->fill_post( $post_id, $args );
		wp_send_json_success( array( 'inserted' => $inserted ) );
	}

	/**
	 * AJAX: undo the last fill.
	 */
	public function ajax_remove() {
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		$this->guard( $post_id );
		$this->revert_post( $post_id );
		wp_send_json_success();
	}

	/* ---------------------------------------------------------------------
	 * Filling engine
	 * ------------------------------------------------------------------- */

	/**
	 * Insert images into a post's content and save (with a backup).
	 *
	 * @param int   $post_id Post ID.
	 * @param array $args     align, size, count, every.
	 * @return int Number of images inserted.
	 */
	public function fill_post( $post_id, $args ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return 0;
		}
		$args = wp_parse_args(
			$args,
			array(
				'align' => 'center',
				'size'  => 'large',
				'count' => 3,
				'every' => 2,
			)
		);
		$align = in_array( $args['align'], array( 'left', 'center', 'right' ), true ) ? $args['align'] : 'center';
		$size  = in_array( $args['size'], array( 'thumbnail', 'medium', 'large', 'full' ), true ) ? $args['size'] : 'large';

		$ids = $this->related_image_ids( $post_id, (int) $args['count'] );
		if ( empty( $ids ) ) {
			return 0;
		}

		$use_blocks = $this->wants_blocks( $post );
		$blocks     = array();
		foreach ( $ids as $id ) {
			$block = $this->build_image_block( $id, $align, $size, '', '', $use_blocks );
			if ( '' !== $block ) {
				$blocks[] = $block;
			}
		}
		if ( empty( $blocks ) ) {
			return 0;
		}

		// Back up the original content so the fill is reversible.
		update_post_meta( $post_id, self::META_BACKUP, $post->post_content );

		$new = $this->insert_blocks( $post->post_content, $blocks, (int) $args['every'] );
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $new,
			)
		);

		return count( $blocks );
	}

	/**
	 * Restore content from before the last fill.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public function revert_post( $post_id ) {
		if ( metadata_exists( 'post', $post_id, self::META_BACKUP ) ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => get_post_meta( $post_id, self::META_BACKUP, true ),
				)
			);
			delete_post_meta( $post_id, self::META_BACKUP );
			return true;
		}

		// Fallback: strip our inserted image blocks.
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}
		$clean = $this->strip_filled( $post->post_content );
		if ( $clean !== $post->post_content ) {
			wp_update_post( array( 'ID' => $post_id, 'post_content' => $clean ) );
		}
		return true;
	}

	/**
	 * Bulk "Image Distribution" remove: take back out only the images THIS tool
	 * inserted (the spr-auto-image blocks/figures), save, refresh the cached count
	 * and drop the fill backup. Unlike revert_post() it never restores other edits.
	 *
	 * @param int $post_id Post ID.
	 * @return array{removed:int,count:int}
	 */
	public function remove_inserted( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'removed' => 0, 'count' => 0 );
		}
		$before = (int) $this->images->count_for_post( $post );
		$clean  = $this->strip_filled( $post->post_content );
		if ( $clean !== $post->post_content ) {
			wp_update_post( array( 'ID' => $post_id, 'post_content' => $clean ) );
			delete_post_meta( $post_id, self::META_BACKUP );
		}
		$count = (int) $this->images->count_for_post( get_post( $post_id ) );
		update_post_meta( $post_id, SPR_META_IMAGE_COUNT, $count );
		return array( 'removed' => max( 0, $before - $count ), 'count' => $count );
	}

	/* ---------------------------------------------------------------------
	 * Bulk image distribution
	 * ------------------------------------------------------------------- */

	/**
	 * Fill one post for the bulk "Image Distribution" tool.
	 *
	 * Density modes:
	 *  - per_article : ensure the post has at least `target` images (default: the
	 *                  image minimum); inserts the deficit only.
	 *  - per_words   : one image per `per_words` words; inserts the deficit.
	 *
	 * Alt text: 'ai' uses the configured AI (when available) to write contextual,
	 * SEO-friendly alt for each image; otherwise a clean fallback alt is built from
	 * the image and the post title. Pass `exclude` (IDs used earlier in the run) to
	 * spread across the whole library.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $args    align, size, mode, target, per_words, every, alt_mode, exclude.
	 * @return array { inserted:int, used:int[], skipped?:string }
	 */
	public function bulk_fill( $post_id, $args ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'inserted' => 0, 'used' => array(), 'skipped' => 'missing' );
		}
		$args = wp_parse_args(
			$args,
			array(
				'align'     => 'center',
				'size'      => 'large',
				'mode'      => 'per_article',
				'target'    => 0,
				'per_words' => 200,
				'every'     => 2,
				'alt_mode'  => 'auto',
				'exclude'   => array(),
				'icons_only' => false,
				'format'    => 'auto',
			)
		);
		$align      = in_array( $args['align'], array( 'left', 'center', 'right' ), true ) ? $args['align'] : 'center';
		$size       = in_array( $args['size'], array( 'thumbnail', 'medium', 'large', 'full' ), true ) ? $args['size'] : 'large';
		$use_blocks = $this->use_blocks_for( $args['format'], $post );

		$current = (int) $this->images->count_for_post( $post );

		if ( 'per_words' === $args['mode'] ) {
			$per    = max( 25, (int) $args['per_words'] );
			$words  = str_word_count( wp_strip_all_tags( $post->post_content ) );
			$target = (int) ceil( $words / $per );
		} else {
			$target = (int) $args['target'];
			if ( $target < 1 ) {
				$target = max( 1, (int) $this->images->get_minimum() );
			}
		}

		$need = min( 30, max( 0, $target - $current ) );
		if ( $need <= 0 ) {
			return array( 'inserted' => 0, 'used' => array(), 'skipped' => 'enough' );
		}

		$ids = $this->related_image_ids( $post_id, $need, (array) $args['exclude'], ! empty( $args['icons_only'] ) );
		if ( empty( $ids ) ) {
			return array( 'inserted' => 0, 'used' => array(), 'skipped' => 'no_images' );
		}

		$use_ai = ( 'ai' === $args['alt_mode'] ) && SPR_Edition::can( 'ai' ) && SPR_AI::is_configured();

		$blocks = array();
		$used   = array();
		foreach ( $ids as $id ) {
			$alt   = $use_ai ? $this->ai_alt( $post, $id ) : $this->auto_alt( $post, $id );
			$block = $this->build_image_block( $id, $align, $size, $alt, '', $use_blocks );
			if ( '' !== $block ) {
				$blocks[] = $block;
				$used[]   = (int) $id;
			}
		}
		if ( empty( $blocks ) ) {
			return array( 'inserted' => 0, 'used' => array(), 'skipped' => 'no_images' );
		}

		update_post_meta( $post_id, self::META_BACKUP, $post->post_content );
		// Scatter the images evenly across the article rather than clumping them.
		$new = $this->distribute_evenly( $post->post_content, $blocks );
		wp_update_post( array( 'ID' => $post_id, 'post_content' => $new ) );
		update_post_meta( $post_id, SPR_META_IMAGE_COUNT, $this->images->count_for_post( get_post( $post_id ) ) );

		return array( 'inserted' => count( $blocks ), 'used' => $used );
	}

	/**
	 * Fallback alt text: the attachment's stored alt, else built from the image
	 * file name and the post title.
	 *
	 * @param WP_Post $post          Post.
	 * @param int     $attachment_id Attachment ID.
	 * @return string
	 */
	public function auto_alt( $post, $attachment_id ) {
		$existing = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		if ( '' !== $existing ) {
			return $existing;
		}
		$file = get_attached_file( $attachment_id );
		$name = $file ? preg_replace( '/[-_]+/', ' ', pathinfo( $file, PATHINFO_FILENAME ) ) : '';
		$name = trim( preg_replace( '/\b\d{3,}\b/', '', (string) $name ) );
		$title = get_the_title( $post );
		$alt   = $name ? trim( $title . ' — ' . $name ) : $title;
		return trim( wp_strip_all_tags( $alt ) );
	}

	/**
	 * AI-written, context-aware alt text (falls back to auto_alt on any failure).
	 *
	 * @param WP_Post $post          Post.
	 * @param int     $attachment_id Attachment ID.
	 * @return string
	 */
	public function ai_alt( $post, $attachment_id ) {
		$file  = get_attached_file( $attachment_id );
		$fname = $file ? pathinfo( $file, PATHINFO_FILENAME ) : '';

		$system = 'You write concise, descriptive, SEO-friendly alt text for an image placed inside a web article. Reply with ONLY the alt text — no quotes, no prefix, maximum 125 characters.';
		$user   = sprintf(
			"Article title: %s\nArticle excerpt: %s\nImage file name: %s\nWrite alt text for what this image most likely shows in the article's context.",
			get_the_title( $post ),
			wp_trim_words( wp_strip_all_tags( $post->post_content ), 60, '' ),
			$fname
		);

		$out = SPR_AI::generate( $system, $user, 60 );
		if ( is_wp_error( $out ) || '' === trim( (string) $out ) ) {
			return $this->auto_alt( $post, $attachment_id );
		}
		$out = trim( wp_strip_all_tags( $out ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $out, 0, 125 ) : substr( $out, 0, 125 );
	}

	/* ---------------------------------------------------------------------
	 * Per-image review (approve alt / caption / title / description)
	 * ------------------------------------------------------------------- */

	/**
	 * How many images a post still needs for the given mode.
	 *
	 * @param WP_Post $post      Post.
	 * @param string  $mode      per_article|per_words.
	 * @param int     $target    Target images (per_article).
	 * @param int     $per_words One image per N words (per_words).
	 * @return int Deficit (0-30).
	 */
	public function needed_for( $post, $mode, $target, $per_words ) {
		$current = (int) $this->images->count_for_post( $post );
		if ( 'per_words' === $mode ) {
			$per   = max( 25, (int) $per_words );
			$words = str_word_count( wp_strip_all_tags( $post->post_content ) );
			$t     = (int) ceil( $words / $per );
		} else {
			$t = (int) $target;
			if ( $t < 1 ) {
				$t = max( 1, (int) $this->images->get_minimum() );
			}
		}
		return min( 30, max( 0, $t - $current ) );
	}

	/**
	 * Propose images for a post, each with suggested metadata the user can edit.
	 *
	 * @param int   $post_id Post ID.
	 * @param int   $count   How many to propose.
	 * @param int[] $exclude Attachment IDs already used this run.
	 * @param bool  $use_ai  Generate metadata with AI when available.
	 * @return array[] Each: id, thumb, alt, caption, title, description.
	 */
	public function propose_for_post( $post_id, $count, $exclude = array(), $use_ai = false ) {
		$post = get_post( $post_id );
		if ( ! $post || $count < 1 ) {
			return array();
		}
		$use_ai = $use_ai && SPR_Edition::can( 'ai' ) && SPR_AI::is_configured();
		$out    = array();
		foreach ( $this->related_image_ids( $post_id, $count, $exclude ) as $id ) {
			$meta  = $use_ai ? $this->ai_image_meta( $post, $id ) : $this->auto_image_meta( $post, $id );
			$out[] = array(
				'id'          => (int) $id,
				'thumb'       => wp_get_attachment_image_url( $id, 'thumbnail' ),
				'alt'         => $meta['alt'],
				'caption'     => $meta['caption'],
				'title'       => $meta['title'],
				'description' => $meta['description'],
			);
		}
		return $out;
	}

	/**
	 * Insert ONE approved image into a post (used by the reviewer). Updates the
	 * attachment's title/description/alt in the library, backs up the post once,
	 * and distributes the new block.
	 *
	 * @param int   $post_id       Post ID.
	 * @param int   $attachment_id Attachment ID.
	 * @param array $args          align, size, alt, caption, title, description, every.
	 * @return array { inserted:int, count:int }
	 */
	public function apply_single( $post_id, $attachment_id, $args ) {
		$post = get_post( $post_id );
		if ( ! $post || ! $attachment_id ) {
			return array( 'inserted' => 0, 'count' => 0 );
		}
		$args  = wp_parse_args(
			$args,
			array( 'align' => 'center', 'size' => 'large', 'alt' => '', 'caption' => '', 'title' => '', 'description' => '', 'every' => 2, 'format' => 'auto' )
		);
		$align = in_array( $args['align'], array( 'left', 'center', 'right' ), true ) ? $args['align'] : 'center';
		$size  = in_array( $args['size'], array( 'thumbnail', 'medium', 'large', 'full' ), true ) ? $args['size'] : 'large';

		$this->update_attachment_fields( $attachment_id, $args['title'], $args['description'], $args['alt'] );

		$block = $this->build_image_block( $attachment_id, $align, $size, $args['alt'], $args['caption'], $this->use_blocks_for( $args['format'], $post ) );
		if ( '' === $block ) {
			return array( 'inserted' => 0, 'count' => (int) $this->images->count_for_post( $post ) );
		}

		// Back up only once per post, so a multi-image review still reverts fully.
		if ( ! metadata_exists( 'post', $post_id, self::META_BACKUP ) ) {
			update_post_meta( $post_id, self::META_BACKUP, $post->post_content );
		}
		$new = $this->insert_blocks( $post->post_content, array( $block ), (int) $args['every'] );
		wp_update_post( array( 'ID' => $post_id, 'post_content' => $new ) );

		$count = (int) $this->images->count_for_post( get_post( $post_id ) );
		update_post_meta( $post_id, SPR_META_IMAGE_COUNT, $count );
		return array( 'inserted' => 1, 'count' => $count );
	}

	/**
	 * Update an attachment's library fields (only non-empty values are written).
	 *
	 * @param int    $id          Attachment ID.
	 * @param string $title       Title.
	 * @param string $description Description (post_content).
	 * @param string $alt         Alt text.
	 */
	public function update_attachment_fields( $id, $title, $description, $alt ) {
		$update = array();
		if ( '' !== trim( (string) $title ) ) {
			$update['post_title'] = sanitize_text_field( $title );
		}
		if ( '' !== trim( (string) $description ) ) {
			$update['post_content'] = wp_kses_post( $description );
		}
		if ( ! empty( $update ) ) {
			$update['ID'] = (int) $id;
			wp_update_post( $update );
		}
		if ( '' !== trim( (string) $alt ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		}
	}

	/**
	 * Suggested metadata without AI: existing alt/title, a caption from the title,
	 * description from the attachment, all derived from image + post.
	 *
	 * @param WP_Post $post Post.
	 * @param int     $id   Attachment ID.
	 * @return array{alt:string,caption:string,title:string,description:string}
	 */
	protected function auto_image_meta( $post, $id ) {
		$file  = get_attached_file( $id );
		$name  = $file ? trim( preg_replace( '/\b\d{3,}\b/', '', preg_replace( '/[-_]+/', ' ', pathinfo( $file, PATHINFO_FILENAME ) ) ) ) : '';
		$title = get_the_title( $id );
		return array(
			'alt'         => $this->auto_alt( $post, $id ),
			'caption'     => '',
			'title'       => $title ? $title : ( $name ? ucwords( $name ) : get_the_title( $post ) ),
			'description' => (string) get_post_field( 'post_content', $id ),
		);
	}

	/**
	 * Suggested metadata via AI (one call, four labelled lines). Falls back to
	 * auto_image_meta for any field the model leaves blank or on error.
	 *
	 * @param WP_Post $post Post.
	 * @param int     $id   Attachment ID.
	 * @return array{alt:string,caption:string,title:string,description:string}
	 */
	public function ai_image_meta( $post, $id ) {
		$auto  = $this->auto_image_meta( $post, $id );
		$file  = get_attached_file( $id );
		$fname = $file ? pathinfo( $file, PATHINFO_FILENAME ) : '';

		$system = 'You write image metadata for an image placed inside a web article. Reply with EXACTLY four lines, each prefixed "ALT:", "CAPTION:", "TITLE:", "DESCRIPTION:". ALT <= 125 chars; CAPTION one short sentence; TITLE a few words; DESCRIPTION one or two sentences. No other text.';
		$user   = sprintf(
			"Article title: %s\nArticle excerpt: %s\nImage file name: %s",
			get_the_title( $post ),
			wp_trim_words( wp_strip_all_tags( $post->post_content ), 50, '' ),
			$fname
		);

		$out = SPR_AI::generate( $system, $user, 200 );
		if ( is_wp_error( $out ) ) {
			return $auto;
		}
		$p = $this->parse_labeled( (string) $out );
		return array(
			'alt'         => '' !== $p['alt'] ? $p['alt'] : $auto['alt'],
			'caption'     => '' !== $p['caption'] ? $p['caption'] : $auto['caption'],
			'title'       => '' !== $p['title'] ? $p['title'] : $auto['title'],
			'description' => '' !== $p['description'] ? $p['description'] : $auto['description'],
		);
	}

	/**
	 * Parse "LABEL: value" lines into alt/caption/title/description.
	 *
	 * @param string $text AI output.
	 * @return array{alt:string,caption:string,title:string,description:string}
	 */
	protected function parse_labeled( $text ) {
		$res = array( 'alt' => '', 'caption' => '', 'title' => '', 'description' => '' );
		foreach ( array_keys( $res ) as $key ) {
			if ( preg_match( '/^\s*' . $key . '\s*:\s*(.+)$/im', $text, $m ) ) {
				$res[ $key ] = trim( wp_strip_all_tags( $m[1] ) );
			}
		}
		return $res;
	}

	/**
	 * Choose image attachment IDs related to the post, then randomised.
	 *
	 * Images whose title/alt/filename match the post's keywords are preferred;
	 * the remainder is filled with random library images. The order is shuffled
	 * so repeated fills vary.
	 *
	 * Selection order: keyword-matched (unused) → other unused → already-used.
	 * Passing $exclude (IDs used earlier in a bulk run) pushes those images to the
	 * back, so a run spreads across the whole library before any image repeats.
	 *
	 * @param int   $post_id    Post ID.
	 * @param int   $count      How many IDs to return.
	 * @param int[] $exclude    Attachment IDs to de-prioritise (used earlier this run).
	 * @param bool  $icons_only Restrict to icon-sized images (<= 150x150).
	 * @return int[]
	 */
	public function related_image_ids( $post_id, $count, $exclude = array(), $icons_only = false ) {
		$count = max( 0, (int) $count );
		if ( 0 === $count ) {
			return array();
		}

		$keywords = $this->post_keywords( $post_id );

		// Pull a wide, randomised slice of the library so variety is maximised.
		$pool = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'posts_per_page' => 1000,
				'fields'         => 'ids',
				'orderby'        => 'rand',
			)
		);
		if ( empty( $pool ) ) {
			return array();
		}

		if ( $icons_only ) {
			$pool = array_values( array_filter( $pool, array( $this, 'is_icon' ) ) );
			if ( empty( $pool ) ) {
				return array();
			}
		}

		$exclude = array_flip( array_map( 'intval', (array) $exclude ) );

		$related = array(); // keyword-matched, unused
		$rest    = array(); // other, unused
		$reuse   = array(); // already used this run (last resort)
		foreach ( $pool as $id ) {
			$id   = (int) $id;
			$file = get_attached_file( $id );
			$hay  = strtolower(
				get_the_title( $id ) . ' '
				. (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) . ' '
				. ( $file ? basename( $file ) : '' )
			);
			$matched = false;
			foreach ( $keywords as $kw ) {
				if ( '' !== $kw && false !== strpos( $hay, $kw ) ) {
					$matched = true;
					break;
				}
			}

			if ( isset( $exclude[ $id ] ) ) {
				$reuse[] = $id;
			} elseif ( $matched ) {
				$related[] = $id;
			} else {
				$rest[] = $id;
			}
		}

		shuffle( $related );
		shuffle( $rest );
		shuffle( $reuse );

		// Unused images first (matched, then any), repeats only if the library runs out.
		$ordered = array_merge( $related, $rest, $reuse );
		return array_slice( $ordered, 0, $count );
	}

	/**
	 * Keywords for relevance matching (title words + Yoast focus keyword).
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	protected function post_keywords( $post_id ) {
		$words = array();

		foreach ( preg_split( '/\s+/', strtolower( wp_strip_all_tags( get_the_title( $post_id ) ) ) ) as $w ) {
			$w = preg_replace( '/[^a-z0-9]/', '', $w );
			if ( strlen( $w ) >= 4 ) {
				$words[] = $w;
			}
		}

		$focus = strtolower( (string) get_post_meta( $post_id, '_yoast_wpseo_focuskw', true ) );
		if ( '' !== $focus ) {
			$words[] = $focus;
		}

		return array_values( array_unique( array_filter( $words ) ) );
	}

	/**
	 * Should a post get Gutenberg block markup? True when the block editor is
	 * used for it; false for the classic editor (where a block comment would be
	 * rewritten on save and lose its alignment).
	 *
	 * @param int|WP_Post $post Post.
	 * @return bool
	 */
	public function wants_blocks( $post ) {
		if ( function_exists( 'use_block_editor_for_post' ) ) {
			return (bool) use_block_editor_for_post( $post );
		}
		return true;
	}

	/**
	 * Resolve the requested output format to a "use blocks?" boolean.
	 *
	 * @param string      $format auto|classic|block.
	 * @param int|WP_Post $post   Post (for auto-detection).
	 * @return bool
	 */
	protected function use_blocks_for( $format, $post ) {
		if ( 'classic' === $format ) {
			return false;
		}
		if ( 'block' === $format ) {
			return true;
		}
		return $this->wants_blocks( $post );
	}

	/**
	 * Build a Gutenberg image block (renders in classic editor too).
	 *
	 * @param int    $id    Attachment ID.
	 * @param string $align left|center|right.
	 * @param string $size  thumbnail|medium|large|full.
	 * @param string $alt   Optional alt text to set on the <img> (overrides the stored alt).
	 * @return string
	 */
	public function build_image_block( $id, $align, $size, $alt = '', $caption = '', $blocks = true ) {
		$icon = $this->is_icon( $id );
		if ( $icon ) {
			// Small images behave like icons: lead a paragraph, float left, no caption,
			// shown at their natural size.
			$align   = 'left';
			$caption = '';
			$size    = 'full';
		}

		$img_class = 'align' . $align . ' size-' . $size . ' wp-image-' . (int) $id . ' ' . self::CSS_CLASS . ( $icon ? ' spr-auto-icon' : '' );

		// Classic editor: store a plain aligned <img> (and, with a caption, a figure
		// that carries the alignment class). The classic editor (TinyMCE) keeps the
		// alignment on the <img> when saving — a Gutenberg block comment would be
		// rewritten on save and lose its "center" alignment.
		if ( ! $blocks ) {
			$img = $this->image_tag( $id, $size, $img_class, $alt );
			if ( '' === $img ) {
				return '';
			}
			if ( '' !== trim( (string) $caption ) ) {
				return '<figure class="align' . $align . ' size-' . $size . ' ' . self::CSS_CLASS . '">' . $img . '<figcaption class="wp-element-caption">' . esc_html( $caption ) . '</figcaption></figure>';
			}
			return $img;
		}

		// Block editor: a lean wp:image block. The <img> still carries the alignment
		// class (classic markup themes style) and the wp-image-{id} class WordPress
		// uses to add responsive srcset at render — so the stored content stays lean.
		$img = $this->image_tag( $id, $size, $img_class, $alt );
		if ( '' === $img ) {
			return '';
		}

		$inner = $img;
		if ( '' !== trim( (string) $caption ) ) {
			$inner .= '<figcaption class="wp-element-caption">' . esc_html( $caption ) . '</figcaption>';
		}
		$figure_class = 'wp-block-image align' . $align . ' size-' . $size . ' ' . self::CSS_CLASS . ( $icon ? ' spr-auto-icon' : '' );
		$figure       = '<figure class="' . esc_attr( $figure_class ) . '">' . $inner . '</figure>';

		$attrs = wp_json_encode(
			array(
				'id'        => (int) $id,
				'sizeSlug'  => $size,
				'align'     => $align,
				'className' => self::CSS_CLASS,
			)
		);

		return '<!-- wp:image ' . $attrs . " -->\n" . $figure . "\n<!-- /wp:image -->";
	}

	/**
	 * Build one clean <img> tag for a size: just class, src, alt, width, height.
	 * No srcset/sizes are stored — WordPress adds those at render from the
	 * wp-image-{id} class, keeping the saved content lean.
	 *
	 * @param int    $id    Attachment ID.
	 * @param string $size  Image size.
	 * @param string $class Class attribute.
	 * @param string $alt   Alt text.
	 * @return string
	 */
	protected function image_tag( $id, $size, $class, $alt = '' ) {
		$src = wp_get_attachment_image_src( $id, $size );
		if ( ! $src || empty( $src[0] ) ) {
			return '';
		}
		return sprintf(
			'<img class="%s" src="%s" alt="%s" width="%d" height="%d" />',
			esc_attr( $class ),
			esc_url( $src[0] ),
			esc_attr( $alt ),
			(int) $src[1],
			(int) $src[2]
		);
	}

	/**
	 * Is an attachment icon-sized (<= 150x150)? Such images are placed inline at
	 * the start of a paragraph, left-aligned, with no caption.
	 *
	 * @param int $id Attachment ID.
	 * @return bool
	 */
	public function is_icon( $id ) {
		$meta = wp_get_attachment_metadata( (int) $id );
		$w    = isset( $meta['width'] ) ? (int) $meta['width'] : 0;
		$h    = isset( $meta['height'] ) ? (int) $meta['height'] : 0;
		if ( $w < 1 || $h < 1 ) {
			return false;
		}
		return $w <= 150 && $h <= 150;
	}

	/**
	 * Insert blocks spread *evenly* across the content's paragraph/heading
	 * boundaries, so images are scattered rather than clumped.
	 *
	 * @param string   $content Content.
	 * @param string[] $blocks  Block markup strings.
	 * @return string
	 */
	public function distribute_evenly( $content, $blocks ) {
		$blocks = array_values( array_filter( (array) $blocks ) );
		$n      = count( $blocks );
		if ( 0 === $n ) {
			return $content;
		}

		if ( false !== strpos( $content, '<!-- wp:' ) ) {
			$boundary = '/<!-- \/wp:(?:paragraph|heading|list|quote|table|image|gallery|columns|group|cover|embed) -->/';
		} else {
			// Classic/HTML: after any top-level block close, or a blank line. Avoids
			// </li>/</div> so we never break list/wrapper nesting.
			$boundary = '#</(?:p|h[1-6]|ul|ol|blockquote|table|figure|pre)>|\n[ \t]*\n#i';
		}

		if ( ! preg_match_all( $boundary, $content, $m, PREG_OFFSET_CAPTURE ) || count( $m[0] ) < 2 ) {
			// Too few seams to scatter — fall back to inserting every couple of
			// paragraph/sentence breaks rather than dumping everything at the end.
			return $this->insert_blocks( $content, $blocks, 2 );
		}

		// End offset of each boundary (where a block may be inserted after it).
		$positions = array();
		foreach ( $m[0] as $match ) {
			$positions[] = $match[1] + strlen( $match[0] );
		}
		$bn = count( $positions );

		// Map each block to an evenly spaced boundary, then insert back-to-front so
		// earlier offsets stay valid.
		$inserts = array();
		for ( $i = 0; $i < $n; $i++ ) {
			$bi              = (int) round( $bn * ( $i + 1 ) / ( $n + 1 ) );
			$bi              = min( $bn, max( 1, $bi ) ) - 1;
			$pos             = $positions[ $bi ];
			$inserts[ $pos ] = isset( $inserts[ $pos ] ) ? $inserts[ $pos ] : array();
			$inserts[ $pos ][] = $blocks[ $i ];
		}
		krsort( $inserts );
		foreach ( $inserts as $pos => $blks ) {
			$content = substr( $content, 0, $pos ) . "\n\n" . implode( "\n\n", $blks ) . "\n\n" . substr( $content, $pos );
		}
		return $content;
	}

	/**
	 * Insert block strings into content, distributed every Nth paragraph.
	 *
	 * @param string   $content Content.
	 * @param string[] $blocks  Block markup strings.
	 * @param int      $every   Insert after every Nth boundary.
	 * @return string
	 */
	public function insert_blocks( $content, $blocks, $every ) {
		if ( empty( $blocks ) ) {
			return $content;
		}
		$every = max( 1, (int) $every );

		if ( false !== strpos( $content, '<!-- wp:' ) ) {
			$boundary = '/<!-- \/wp:(?:paragraph|heading|list|quote) -->/';
		} elseif ( false !== stripos( $content, '</p>' ) ) {
			$boundary = '#</p>#i';
		} else {
			$boundary = '/\n\s*\n/';
		}

		$i     = 0;
		$used  = 0;
		$total = count( $blocks );

		$out = preg_replace_callback(
			$boundary,
			function ( $m ) use ( &$i, &$used, $blocks, $every, $total ) {
				$i++;
				if ( $used < $total && 0 === $i % $every ) {
					return $m[0] . "\n\n" . $blocks[ $used++ ] . "\n\n";
				}
				return $m[0];
			},
			$content
		);

		// Append any leftovers (e.g. very short content).
		while ( $used < $total ) {
			$out .= "\n\n" . $blocks[ $used++ ];
		}

		return $out;
	}

	/**
	 * Strip image blocks/figures previously inserted by this tool.
	 *
	 * @param string $content Content.
	 * @return string
	 */
	protected function strip_filled( $content ) {
		// Whole Gutenberg image blocks carrying our class.
		$content = preg_replace(
			'/<!-- wp:image [^>]*' . preg_quote( self::CSS_CLASS, '/' ) . '[^>]*-->.*?<!-- \/wp:image -->\s*/s',
			'',
			$content
		);
		// Bare figures carrying our class.
		$content = preg_replace(
			'#<figure[^>]*\b' . preg_quote( self::CSS_CLASS, '#' ) . '\b[^>]*>.*?</figure>\s*#is',
			'',
			$content
		);
		// Bare/classic inserted images and inline sprinkled icons (an <img> carrying
		// our class, not wrapped in a figure).
		$content = preg_replace(
			'#<img[^>]*\bspr-auto-image\b[^>]*>\s*#i',
			'',
			$content
		);
		return $content;
	}

	/* ---------------------------------------------------------------------
	 * Icon sprinkle — place small icons next to the section they relate to
	 * ------------------------------------------------------------------- */

	/**
	 * Sprinkle context-matched icons through a post. Icons are chosen so their
	 * alt / title / file name relates to the heading or paragraph they lead, are
	 * scattered (a different icon per spot) and float left at the start of the
	 * text. Placement: 'headings' (before each H2-H4), 'paragraphs' (start of each
	 * paragraph) or 'top' (one at the very top).
	 *
	 * @param int   $post_id Post ID.
	 * @param array $args    placement, max, exclude (icon IDs used earlier this run).
	 * @return array{inserted:int,used:int[],skipped?:string}
	 */
	public function sprinkle_icons( $post_id, $args ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array( 'inserted' => 0, 'used' => array(), 'skipped' => 'missing' );
		}
		$args = wp_parse_args(
			$args,
			array(
				'placement' => 'headings',
				'max'       => 20,
				'exclude'   => array(),
			)
		);
		$content = $post->post_content;

		$icons = $this->icon_pool();
		if ( empty( $icons ) ) {
			return array( 'inserted' => 0, 'used' => array(), 'skipped' => 'no_icons' );
		}
		$points = $this->icon_points( $content, $args['placement'] );
		if ( empty( $points ) ) {
			return array( 'inserted' => 0, 'used' => array(), 'skipped' => 'no_points' );
		}
		$points = array_slice( $points, 0, max( 1, (int) $args['max'] ) );

		$exclude = array_flip( array_map( 'intval', (array) $args['exclude'] ) );
		$used    = array();
		$inserts = array();
		foreach ( $points as $pt ) {
			$icon = $this->best_icon_for( $pt['context'], $icons, $exclude, $used );
			if ( ! $icon ) {
				continue;
			}
			$inserts[ $pt['offset'] ] = $this->build_icon_img( $icon['id'], $this->icon_alt( $icon, $pt['context'] ) );
			$used[]                   = (int) $icon['id'];
		}
		if ( empty( $inserts ) ) {
			return array( 'inserted' => 0, 'used' => array(), 'skipped' => 'no_match' );
		}

		// Insert back-to-front so earlier offsets stay valid.
		krsort( $inserts );
		foreach ( $inserts as $offset => $img ) {
			$content = substr( $content, 0, $offset ) . $img . substr( $content, $offset );
		}

		if ( ! metadata_exists( 'post', $post_id, self::META_BACKUP ) ) {
			update_post_meta( $post_id, self::META_BACKUP, $post->post_content );
		}
		wp_update_post( array( 'ID' => $post_id, 'post_content' => $content ) );
		update_post_meta( $post_id, SPR_META_IMAGE_COUNT, $this->images->count_for_post( get_post( $post_id ) ) );

		return array( 'inserted' => count( $inserts ), 'used' => $used );
	}

	/**
	 * All icon-sized attachments, each with its keyword haystack (alt/title/file).
	 *
	 * @return array[] Each: id, kw[], alt, title.
	 */
	public function icon_pool() {
		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'posts_per_page' => 2000,
				'fields'         => 'ids',
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		$out = array();
		foreach ( $ids as $id ) {
			$id = (int) $id;
			if ( ! $this->is_icon( $id ) ) {
				continue;
			}
			$file  = get_attached_file( $id );
			$alt   = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
			$title = get_the_title( $id );
			$hay   = strtolower( $title . ' ' . $alt . ' ' . ( $file ? basename( $file ) : '' ) );
			$words = array();
			foreach ( preg_split( '/[^a-z0-9]+/', $hay ) as $w ) {
				if ( strlen( $w ) >= 3 ) {
					$words[] = $w;
				}
			}
			$out[] = array(
				'id'    => $id,
				'kw'    => array_values( array_unique( $words ) ),
				'alt'   => $alt,
				'title' => $title,
			);
		}
		return $out;
	}

	/**
	 * Find icon insertion points + the text they should match.
	 *
	 * @param string $content   Content.
	 * @param string $placement headings|paragraphs|top.
	 * @return array[] Each: offset (int), context (string).
	 */
	protected function icon_points( $content, $placement ) {
		if ( 'top' === $placement ) {
			return array( array( 'offset' => 0, 'context' => wp_strip_all_tags( $content ) ) );
		}
		$pattern = ( 'paragraphs' === $placement ) ? '#<p\b[^>]*>#i' : '#<h[2-4]\b[^>]*>#i';
		$points  = array();
		if ( preg_match_all( $pattern, $content, $m, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $m[0] as $match ) {
				$open_end = $match[1] + strlen( $match[0] );
				$points[] = array(
					'offset'  => $open_end,
					'context' => wp_strip_all_tags( substr( $content, $open_end, 220 ) ),
				);
			}
		}
		return $points;
	}

	/**
	 * Pick the icon whose keywords best match the surrounding text. Prefers an
	 * unused icon (scatter/variety); falls back to a random unused, then reuse.
	 *
	 * @param string $context Nearby text.
	 * @param array  $icons   Icon pool.
	 * @param array  $exclude Flip-map of excluded IDs.
	 * @param int[]  $used    IDs already placed this run.
	 * @return array|null
	 */
	protected function best_icon_for( $context, $icons, $exclude, $used ) {
		$ctx = array();
		foreach ( preg_split( '/[^a-z0-9]+/', strtolower( wp_strip_all_tags( $context ) ) ) as $w ) {
			if ( strlen( $w ) >= 4 ) {
				$ctx[ $w ] = 1;
			}
		}
		$used_flip  = array_flip( array_map( 'intval', $used ) );
		$best       = null;
		$best_score = 0;
		$unused     = array();
		foreach ( $icons as $ic ) {
			if ( isset( $exclude[ $ic['id'] ] ) || isset( $used_flip[ $ic['id'] ] ) ) {
				continue;
			}
			$unused[] = $ic;
			$score    = 0;
			foreach ( $ic['kw'] as $w ) {
				if ( isset( $ctx[ $w ] ) ) {
					$score++;
				}
			}
			if ( $score > $best_score ) {
				$best_score = $score;
				$best       = $ic;
			}
		}
		if ( $best && $best_score > 0 ) {
			return $best; // Context match.
		}
		if ( ! empty( $unused ) ) {
			return $unused[ array_rand( $unused ) ]; // Scatter / variety.
		}
		$reusable = array_values(
			array_filter(
				$icons,
				static function ( $ic ) use ( $exclude ) {
					return ! isset( $exclude[ $ic['id'] ] );
				}
			)
		);
		return $reusable ? $reusable[ array_rand( $reusable ) ] : null;
	}

	/**
	 * Alt text for a sprinkled icon: its own alt, else derived from the context.
	 *
	 * @param array  $icon    Icon row.
	 * @param string $context Nearby text.
	 * @return string
	 */
	protected function icon_alt( $icon, $context ) {
		if ( ! empty( $icon['alt'] ) ) {
			return $icon['alt'];
		}
		$t = trim( wp_strip_all_tags( $context ) );
		$t = wp_trim_words( $t, 6, '' );
		if ( '' !== $t ) {
			return trim( $t ) . ' icon';
		}
		return $icon['title'] ? $icon['title'] : 'icon';
	}

	/**
	 * Build an inline icon <img> (left-aligned, no caption) to lead a heading or
	 * paragraph — like a section icon. Carries spr-auto-image + spr-auto-icon so
	 * "Remove inserted images" can take it back out.
	 *
	 * @param int    $id  Attachment ID.
	 * @param string $alt Alt text.
	 * @return string
	 */
	public function build_icon_img( $id, $alt = '' ) {
		return $this->image_tag( $id, 'full', 'alignleft size-full wp-image-' . (int) $id . ' ' . self::CSS_CLASS . ' spr-auto-icon', $alt );
	}
}
