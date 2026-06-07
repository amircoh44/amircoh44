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

		$blocks = array();
		foreach ( $ids as $id ) {
			$block = $this->build_image_block( $id, $align, $size );
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
	 * Choose image attachment IDs related to the post, then randomised.
	 *
	 * Images whose title/alt/filename match the post's keywords are preferred;
	 * the remainder is filled with random library images. The order is shuffled
	 * so repeated fills vary.
	 *
	 * @param int $post_id Post ID.
	 * @param int $count   How many IDs to return.
	 * @return int[]
	 */
	public function related_image_ids( $post_id, $count ) {
		$count = max( 0, (int) $count );
		if ( 0 === $count ) {
			return array();
		}

		$keywords = $this->post_keywords( $post_id );

		$pool = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'posts_per_page' => 300,
				'fields'         => 'ids',
				'orderby'        => 'rand',
			)
		);
		if ( empty( $pool ) ) {
			return array();
		}

		$related = array();
		$rest    = array();
		foreach ( $pool as $id ) {
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
			if ( $matched ) {
				$related[] = $id;
			} else {
				$rest[] = $id;
			}
		}

		shuffle( $related );
		shuffle( $rest );

		$ordered = array_merge( $related, $rest );
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
	 * Build a Gutenberg image block (renders in classic editor too).
	 *
	 * @param int    $id    Attachment ID.
	 * @param string $align left|center|right.
	 * @param string $size  thumbnail|medium|large|full.
	 * @return string
	 */
	public function build_image_block( $id, $align, $size ) {
		$img = wp_get_attachment_image( $id, $size, false, array( 'class' => 'wp-image-' . (int) $id ) );
		if ( ! $img ) {
			return '';
		}

		$figure_class = 'wp-block-image align' . $align . ' size-' . $size . ' ' . self::CSS_CLASS;
		$figure       = '<figure class="' . esc_attr( $figure_class ) . '">' . $img . '</figure>';

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
		return $content;
	}
}
