<?php
/**
 * Duplicate-H1 checker.
 *
 * Themes normally render the post title as the page's single <h1>. If the post
 * content *also* contains an <h1>, that usually creates a duplicate-H1 SEO
 * problem — so we warn the editor. Because some layouts intentionally hide the
 * theme H1 and use a different one inside the content, the warning can be
 * ignored per post.
 *
 * @package SeoArticleBooster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SAB_Heading_Checker
 */
class SAB_Heading_Checker {

	/** Post meta flag: this post's H1 warning is dismissed. */
	const META_IGNORE = '_sab_ignore_h1';

	/** AJAX nonce. */
	const NONCE = 'sab_h1';

	/**
	 * Hooks.
	 */
	public function init() {
		add_action( 'admin_notices', array( $this, 'maybe_notice' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'post_submitbox_misc_actions', array( $this, 'publish_box' ) );
		add_action( 'wp_ajax_sab_ignore_h1', array( $this, 'ajax_ignore' ) );
	}

	/**
	 * Count <h1> tags in a content string.
	 *
	 * @param string $content Content.
	 * @return int
	 */
	public function count_h1( $content ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return 0;
		}
		return (int) preg_match_all( '/<h1\b/i', $content );
	}

	/**
	 * Is the warning relevant for this post type?
	 *
	 * @param string $post_type Post type.
	 * @return bool
	 */
	protected function applies_to( $post_type ) {
		return in_array( $post_type, (array) SAB_Settings::get( 'link_post_types', array( 'post', 'page' ) ), true );
	}

	/**
	 * Is a post flagged (has a content H1 and the warning is not ignored)?
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	public function is_flagged( $post ) {
		if ( ! $post instanceof WP_Post || ! $this->applies_to( $post->post_type ) ) {
			return false;
		}
		if ( get_post_meta( $post->ID, self::META_IGNORE, true ) ) {
			return false;
		}
		return $this->count_h1( $post->post_content ) >= 1;
	}

	/**
	 * Current edit-screen post, or null.
	 *
	 * @return WP_Post|null
	 */
	protected function current_post() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'post' !== $screen->base ) {
			return null;
		}
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		return $post_id ? get_post( $post_id ) : null;
	}

	/**
	 * Render the editor warning when appropriate.
	 */
	public function maybe_notice() {
		$post = $this->current_post();
		if ( ! $post || ! $this->is_flagged( $post ) ) {
			return;
		}

		$count = $this->count_h1( $post->post_content );
		?>
		<div class="notice notice-warning sab-h1-notice" data-post="<?php echo esc_attr( $post->ID ); ?>">
			<p>
				<strong><?php esc_html_e( 'SEO Article Booster:', 'seo-article-booster' ); ?></strong>
				<?php
				printf(
					/* translators: %d: number of H1 tags found in content. */
					esc_html( _n( 'This content contains %d <h1> heading. Themes usually output the post title as the page’s only H1, so this can create a duplicate-H1 SEO issue.', 'This content contains %d <h1> headings. Themes usually output the post title as the page’s only H1, so these can create duplicate-H1 SEO issues.', $count, 'seo-article-booster' ) ),
					(int) $count
				);
				?>
				<?php esc_html_e( 'If this is intentional (for example, you hide the theme H1 and use your own), you can ignore it.', 'seo-article-booster' ); ?>
			</p>
			<p>
				<button type="button" class="button button-small sab-h1-ignore"><?php esc_html_e( 'Ignore for this post', 'seo-article-booster' ); ?></button>
			</p>
		</div>
		<?php
	}

	/**
	 * Show the H1 status (and ignore state) in the Publish box.
	 *
	 * @param WP_Post $post Post.
	 */
	public function publish_box( $post ) {
		if ( ! $post instanceof WP_Post || ! $this->applies_to( $post->post_type ) ) {
			return;
		}
		$count   = $this->count_h1( $post->post_content );
		$ignored = (bool) get_post_meta( $post->ID, self::META_IGNORE, true );
		if ( $count < 1 ) {
			return;
		}
		?>
		<div class="misc-pub-section sab-pub-h1" data-post="<?php echo esc_attr( $post->ID ); ?>">
			<span class="dashicons <?php echo $ignored ? 'dashicons-hidden' : 'dashicons-warning'; ?>"></span>
			<?php
			printf(
				/* translators: %d: number of content H1 tags. */
				esc_html__( 'Content H1: %d', 'seo-article-booster' ),
				(int) $count
			);
			?>
			<?php if ( $ignored ) : ?>
				— <a href="#" class="sab-h1-restore"><?php esc_html_e( 'show warning', 'seo-article-booster' ); ?></a>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Enqueue the tiny notice script on the post editor.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue( $hook ) {
		if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			return;
		}
		wp_enqueue_script( 'sab-h1', SAB_PLUGIN_URL . 'admin/js/h1-notice.js', array( 'jquery' ), SAB_VERSION, true );
		wp_localize_script(
			'sab-h1',
			'SAB_H1',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'ignored' => __( 'Warning ignored for this post.', 'seo-article-booster' ),
			)
		);
	}

	/**
	 * AJAX: set or clear the per-post ignore flag.
	 */
	public function ajax_ignore() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'seo-article-booster' ) ), 403 );
		}
		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		$ignore  = isset( $_POST['ignore'] ) ? (int) $_POST['ignore'] : 1;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit this post.', 'seo-article-booster' ) ), 403 );
		}

		if ( $ignore ) {
			update_post_meta( $post_id, self::META_IGNORE, 1 );
		} else {
			delete_post_meta( $post_id, self::META_IGNORE );
		}
		wp_send_json_success( array( 'ignored' => (bool) $ignore ) );
	}
}
