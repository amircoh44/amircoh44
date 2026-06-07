<?php
/**
 * "New post awareness."
 *
 * When a post or page is published for the first time, this:
 *
 *   1. Rebuilds the link index so the new URL becomes immediately linkable —
 *      meaning the display-time filter starts adding inbound links to the new
 *      post across the site right away (non-destructive).
 *
 *   2. Optionally (a setting) permanently writes inbound links pointing at the
 *      new post into a limited number of older, related articles.
 *
 * @package SeoArticleBooster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SAB_New_Post_Linker
 */
class SAB_New_Post_Linker {

	/**
	 * Link index.
	 *
	 * @var SAB_Link_Index
	 */
	protected $index;

	/**
	 * Applier (for permanent inbound links).
	 *
	 * @var SAB_Link_Applier
	 */
	protected $applier;

	/**
	 * Re-entrancy guard so applier-driven updates don't loop back here.
	 *
	 * @var bool
	 */
	protected $is_processing = false;

	/**
	 * Constructor.
	 *
	 * @param SAB_Link_Index   $index   Link index.
	 * @param SAB_Link_Applier $applier Applier.
	 */
	public function __construct( SAB_Link_Index $index, SAB_Link_Applier $applier ) {
		$this->index   = $index;
		$this->applier = $applier;
	}

	/**
	 * Hook into publish transitions and content edits.
	 */
	public function init() {
		// Fires on every status change; we filter for "becoming published".
		add_action( 'transition_post_status', array( $this, 'on_transition' ), 10, 3 );

		// Editing existing content can change its title/keywords, so refresh the
		// index (debounced via the transient) whenever linkable content is saved.
		add_action( 'save_post', array( $this, 'on_save' ), 30, 2 );
	}

	/**
	 * React to a post becoming published.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Previous status.
	 * @param WP_Post $post       The post.
	 */
	public function on_transition( $new_status, $old_status, $post ) {
		if ( $this->is_processing ) {
			return;
		}
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return; // Only the moment it first becomes public.
		}
		if ( ! $this->is_linkable( $post ) ) {
			return;
		}
		if ( ! SAB_Settings::get( 'auto_link_new_posts' ) ) {
			return;
		}

		// (1) Make the new URL linkable everywhere via the display filter.
		$this->index->rebuild();

		// (2) Optionally bake permanent inbound links into older related posts (Pro).
		if ( SAB_Settings::get( 'auto_apply_inbound' ) && SAB_Edition::can( 'auto_inbound' ) ) {
			$limit = (int) SAB_Settings::get( 'inbound_apply_limit', 5 );

			$this->is_processing = true; // Prevent recursion via wp_update_post.
			$result              = $this->applier->apply_inbound_for_target( $post->ID, $limit );
			$this->is_processing = false;

			// Record what we did, useful for support/debugging and the admin log.
			if ( ! empty( $result['post_ids'] ) ) {
				update_post_meta( $post->ID, '_sab_inbound_sources', $result['post_ids'] );
			}

			/**
			 * Fires after inbound links to a new post have been applied.
			 *
			 * @param int   $post_id The new post.
			 * @param array $result  { edited, links, post_ids }.
			 */
			do_action( 'sab_inbound_links_applied', $post->ID, $result );
		}
	}

	/**
	 * Invalidate the index when linkable content is saved so titles/keywords
	 * stay in sync. The index is lazily rebuilt on the next read.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public function on_save( $post_id, $post ) {
		if ( $this->is_processing ) {
			return;
		}
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! $this->is_linkable( $post ) ) {
			return;
		}
		// Just drop the cache; rebuilding on every keystroke would be wasteful.
		$this->index->clear();
	}

	/**
	 * Is this post one of the linkable post types?
	 *
	 * @param WP_Post $post Post.
	 * @return bool
	 */
	protected function is_linkable( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return false;
		}
		return in_array( $post->post_type, (array) SAB_Settings::get( 'link_post_types', array( 'post', 'page' ) ), true );
	}
}
