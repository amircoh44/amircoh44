<?php
/**
 * Image scanning and minimum-image enforcement.
 *
 * Counts the images embedded inside each article's content (inline <img> tags,
 * Gutenberg image/gallery blocks and classic [gallery] shortcodes, plus the
 * featured image when enabled) and surfaces posts that fall below the minimum
 * configured in the admin panel.
 *
 * @package SeoArticleBooster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SAB_Image_Scanner
 */
class SAB_Image_Scanner {

	/**
	 * Wire up hooks.
	 */
	public function init() {
		// Keep the cached per-post image count fresh whenever content is saved.
		add_action( 'save_post', array( $this, 'on_save_post' ), 20, 3 );

		// Admin list-table column + editor warning.
		add_action( 'admin_init', array( $this, 'register_admin_columns' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_editor_notice' ) );
		add_action( 'post_submitbox_misc_actions', array( $this, 'render_publish_box_status' ) );
	}

	/* ---------------------------------------------------------------------
	 * Counting
	 * ------------------------------------------------------------------- */

	/**
	 * Count the images contained in a piece of post content.
	 *
	 * @param string $content Raw post_content.
	 * @return int
	 */
	public function count_images_in_content( $content ) {
		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return 0;
		}

		$count = 0;

		// 1. Inline <img> tags. Gutenberg image & gallery blocks also store a
		//    rendered <img> tag in post_content, so this covers them too.
		if ( preg_match_all( '/<img\b/i', $content, $m ) ) {
			$count += count( $m[0] );
		}

		// 2. Classic [gallery ids="1,2,3"] shortcodes store no <img> markup, so
		//    we count the referenced attachment IDs instead.
		if ( false !== strpos( $content, '[gallery' ) && preg_match_all( '/\[gallery\b[^\]]*\]/i', $content, $galleries ) ) {
			foreach ( $galleries[0] as $gallery ) {
				if ( preg_match( '/\bids\s*=\s*([\'"])(.*?)\1/i', $gallery, $ids_match ) ) {
					$ids    = array_filter( array_map( 'trim', explode( ',', $ids_match[2] ) ) );
					$count += count( $ids );
				}
			}
		}

		return $count;
	}

	/**
	 * Count the images for a given post, optionally adding the featured image.
	 *
	 * @param int|WP_Post $post Post ID or object.
	 * @return int
	 */
	public function count_for_post( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return 0;
		}

		$count = $this->count_images_in_content( $post->post_content );

		if ( SAB_Settings::get( 'count_featured_image' ) && has_post_thumbnail( $post ) ) {
			$count++;
		}

		return $count;
	}

	/**
	 * The configured minimum number of images.
	 *
	 * @return int
	 */
	public function get_minimum() {
		return (int) SAB_Settings::get( 'min_images', 0 );
	}

	/**
	 * Does this post meet (or exceed) the minimum?
	 *
	 * @param int|WP_Post $post Post.
	 * @return bool
	 */
	public function meets_minimum( $post ) {
		return $this->count_for_post( $post ) >= $this->get_minimum();
	}

	/* ---------------------------------------------------------------------
	 * Persisting the cached count
	 * ------------------------------------------------------------------- */

	/**
	 * Recompute and store the image count on save so admin list queries stay fast.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an update.
	 */
	public function on_save_post( $post_id, $post, $update = false ) {
		// Skip autosaves/revisions and unsupported post types.
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, (array) SAB_Settings::get( 'audit_post_types', array() ), true ) ) {
			return;
		}

		$count = $this->count_for_post( $post );
		update_post_meta( $post_id, SAB_META_IMAGE_COUNT, $count );
	}

	/**
	 * Read the cached count, recomputing on the fly if it has never been stored.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	public function get_cached_count( $post_id ) {
		$stored = get_post_meta( $post_id, SAB_META_IMAGE_COUNT, true );
		if ( '' === $stored ) {
			$count = $this->count_for_post( $post_id );
			update_post_meta( $post_id, SAB_META_IMAGE_COUNT, $count );
			return $count;
		}
		return (int) $stored;
	}

	/* ---------------------------------------------------------------------
	 * Bulk scanning (used by the admin audit page via AJAX)
	 * ------------------------------------------------------------------- */

	/**
	 * Scan a batch of posts and return any that fall below the minimum.
	 *
	 * @param int $paged    Page number (1-based).
	 * @param int $per_page Posts per batch.
	 * @return array {
	 *     @type array $deficient   List of deficient posts (id, title, count, edit_link, view_link).
	 *     @type int   $total       Total posts matching the audit query.
	 *     @type int   $scanned     Number scanned so far (through this page).
	 *     @type bool  $done        Whether scanning is complete.
	 *     @type int   $min         The configured minimum.
	 * }
	 */
	public function scan_batch( $paged = 1, $per_page = 50 ) {
		$post_types = (array) SAB_Settings::get( 'audit_post_types', array( 'post' ) );
		$min        = $this->get_minimum();

		$query = new WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				'posts_per_page'         => $per_page,
				'paged'                  => $paged,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'no_found_rows'          => false,
				'ignore_sticky_posts'    => true,
				'update_post_term_cache' => false,
			)
		);

		$deficient = array();
		foreach ( $query->posts as $post ) {
			$count = $this->count_for_post( $post );
			update_post_meta( $post->ID, SAB_META_IMAGE_COUNT, $count );

			if ( $count < $min ) {
				$deficient[] = array(
					'id'        => $post->ID,
					'title'     => get_the_title( $post ) ? get_the_title( $post ) : __( '(no title)', 'seo-article-booster' ),
					'count'     => $count,
					'edit_link' => get_edit_post_link( $post->ID, 'raw' ),
					'view_link' => get_permalink( $post->ID ),
				);
			}
		}

		$total   = (int) $query->found_posts;
		$scanned = min( $paged * $per_page, $total );

		return array(
			'deficient' => $deficient,
			'total'     => $total,
			'scanned'   => $scanned,
			'done'      => $scanned >= $total,
			'min'       => $min,
			'next_page' => $paged + 1,
		);
	}

	/* ---------------------------------------------------------------------
	 * Media library statistics
	 * ------------------------------------------------------------------- */

	/**
	 * Summarise the media library: total attachments and total images.
	 *
	 * @return array{total:int, images:int}
	 */
	public function get_media_library_stats() {
		$counts = (array) wp_count_attachments();
		$total  = 0;
		$images = 0;

		foreach ( $counts as $mime => $num ) {
			$num    = (int) $num;
			$total += $num;
			if ( 0 === strpos( (string) $mime, 'image/' ) ) {
				$images += $num;
			}
		}

		return array(
			'total'  => $total,
			'images' => $images,
		);
	}

	/* ---------------------------------------------------------------------
	 * Admin: list-table column + editor warnings
	 * ------------------------------------------------------------------- */

	/**
	 * Register an "Images" column for every audited post type's list table.
	 */
	public function register_admin_columns() {
		foreach ( (array) SAB_Settings::get( 'audit_post_types', array() ) as $type ) {
			add_filter( "manage_{$type}_posts_columns", array( $this, 'add_column' ) );
			add_action( "manage_{$type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
			add_filter( "manage_edit-{$type}_sortable_columns", array( $this, 'make_column_sortable' ) );
		}
		add_action( 'pre_get_posts', array( $this, 'handle_column_sorting' ) );
	}

	/**
	 * Append the Images column header.
	 *
	 * @param array $columns Existing columns.
	 * @return array
	 */
	public function add_column( $columns ) {
		$columns['sab_images'] = __( 'Images', 'seo-article-booster' );
		return $columns;
	}

	/**
	 * Render the per-row image count with a warning when below the minimum.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( $column, $post_id ) {
		if ( 'sab_images' !== $column ) {
			return;
		}

		$count = $this->get_cached_count( $post_id );
		$min   = $this->get_minimum();

		if ( $count < $min ) {
			printf(
				'<span class="sab-badge sab-badge--warn" title="%s"><span class="dashicons dashicons-warning"></span> %d / %d</span>',
				esc_attr__( 'Below the minimum number of images', 'seo-article-booster' ),
				(int) $count,
				(int) $min
			);
		} else {
			printf(
				'<span class="sab-badge sab-badge--ok"><span class="dashicons dashicons-yes"></span> %d</span>',
				(int) $count
			);
		}
	}

	/**
	 * Declare the column sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public function make_column_sortable( $columns ) {
		$columns['sab_images'] = 'sab_images';
		return $columns;
	}

	/**
	 * Translate the "sab_images" orderby into a meta-value sort.
	 *
	 * @param WP_Query $query Main query.
	 */
	public function handle_column_sorting( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( 'sab_images' !== $query->get( 'orderby' ) ) {
			return;
		}
		$query->set( 'meta_key', SAB_META_IMAGE_COUNT );
		$query->set( 'orderby', 'meta_value_num' );
	}

	/**
	 * Show an admin notice on the post editor when the current post is short on images.
	 */
	public function maybe_show_editor_notice() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->base ) {
			return;
		}
		if ( ! in_array( $screen->post_type, (array) SAB_Settings::get( 'audit_post_types', array() ), true ) ) {
			return;
		}

		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $post_id ) {
			return;
		}

		$count = $this->count_for_post( $post_id );
		$min   = $this->get_minimum();
		if ( $count >= $min ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'SEO Article Booster:', 'seo-article-booster' ),
			esc_html(
				sprintf(
					/* translators: 1: current image count, 2: required minimum. */
					__( 'This content currently has %1$d image(s) but the configured minimum is %2$d. Consider adding more images.', 'seo-article-booster' ),
					$count,
					$min
				)
			)
		);
	}

	/**
	 * Add a compact image-count line to the Publish meta box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_publish_box_status( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( ! in_array( $post->post_type, (array) SAB_Settings::get( 'audit_post_types', array() ), true ) ) {
			return;
		}

		$count = $this->count_for_post( $post );
		$min   = $this->get_minimum();
		$ok    = $count >= $min;

		printf(
			'<div class="misc-pub-section sab-pub-images"><span class="dashicons %s"></span> %s <strong>%d</strong> / %d</div>',
			esc_attr( $ok ? 'dashicons-yes-alt' : 'dashicons-warning' ),
			esc_html__( 'Images:', 'seo-article-booster' ),
			(int) $count,
			(int) $min
		);
	}
}
