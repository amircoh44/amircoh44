<?php
/**
 * Permanent ("baked in") internal linking.
 *
 * Unlike the display filter, this writes <a> tags directly into post_content
 * using the very same replacement engine. Every link it adds carries the
 * plugin's CSS class, so the matching "revert" routine can later strip them
 * cleanly and restore the original text.
 *
 * All operations run in batches behind nonce- and capability-protected AJAX so
 * large sites don't time out.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_Link_Applier
 */
class SPR_Link_Applier {

	/**
	 * Post meta flag marking content that currently holds applied links.
	 */
	const META_APPLIED = '_spr_links_applied';

	/**
	 * Link index.
	 *
	 * @var SPR_Link_Index
	 */
	protected $index;

	/**
	 * Replacement engine.
	 *
	 * @var SPR_Link_Replacer
	 */
	protected $replacer;

	/**
	 * Constructor.
	 *
	 * @param SPR_Link_Index    $index    Link index.
	 * @param SPR_Link_Replacer $replacer Replacement engine.
	 */
	public function __construct( SPR_Link_Index $index, SPR_Link_Replacer $replacer ) {
		$this->index    = $index;
		$this->replacer = $replacer;
	}

	/**
	 * Apply links permanently to a batch of posts.
	 *
	 * @param int $paged    Batch page (1-based).
	 * @param int $per_page Posts per batch.
	 * @return array Progress payload.
	 */
	public function apply_batch( $paged = 1, $per_page = 20 ) {
		$query   = $this->target_query( $paged, $per_page );
		$updated = 0;
		$links   = 0;

		foreach ( $query->posts as $post ) {
			$entries = $this->index->get_index_excluding( $post->ID );
			if ( empty( $entries ) ) {
				continue;
			}

			// Work on already-clean content: strip any prior plugin links first so
			// re-running is idempotent and never double-links.
			list( $clean ) = $this->replacer->strip_links( $post->post_content );

			list( $linked, $added ) = $this->replacer->replace( $clean, $entries, $this->options() );

			if ( $added > 0 && $linked !== $post->post_content ) {
				$this->save_content( $post->ID, $linked );
				update_post_meta( $post->ID, self::META_APPLIED, 1 );
				$updated++;
				$links += $added;
			}
		}

		return $this->progress( $query, $paged, $per_page, array(
			'updated' => $updated,
			'links'   => $links,
		) );
	}

	/**
	 * Revert (strip) plugin links from a batch of posts.
	 *
	 * @param int $paged    Batch page (1-based).
	 * @param int $per_page Posts per batch.
	 * @return array Progress payload.
	 */
	public function revert_batch( $paged = 1, $per_page = 20 ) {
		$query   = $this->target_query( $paged, $per_page );
		$updated = 0;
		$removed = 0;

		foreach ( $query->posts as $post ) {
			list( $clean, $count ) = $this->replacer->strip_links( $post->post_content );
			if ( $count > 0 && $clean !== $post->post_content ) {
				$this->save_content( $post->ID, $clean );
				delete_post_meta( $post->ID, self::META_APPLIED );
				$updated++;
				$removed += $count;
			}
		}

		return $this->progress( $query, $paged, $per_page, array(
			'updated' => $updated,
			'removed' => $removed,
		) );
	}

	/**
	 * Insert inbound links to a single target post inside up to N existing posts.
	 *
	 * Used by the "new post awareness" feature: when a post is published we want
	 * older, related articles to start pointing at it. Only the target's anchor
	 * phrases are considered, and only posts that actually contain them are edited.
	 *
	 * @param int $target_post_id The newly published post that should receive links.
	 * @param int $limit          Max number of existing posts to edit.
	 * @return array{edited:int,links:int,post_ids:int[]}
	 */
	public function apply_inbound_for_target( $target_post_id, $limit = 5 ) {
		$target_post_id = (int) $target_post_id;
		$entries        = $this->index->get_entries_for_target( $target_post_id );

		$result = array(
			'edited'   => 0,
			'links'    => 0,
			'post_ids' => array(),
		);

		if ( empty( $entries ) || $limit < 1 ) {
			return $result;
		}

		$link_types = (array) SPR_Settings::get( 'link_post_types', array( 'post', 'page' ) );

		$query = new WP_Query(
			array(
				'post_type'           => $link_types,
				'post_status'         => 'publish',
				'posts_per_page'      => -1,
				'post__not_in'        => array( $target_post_id ),
				'orderby'             => 'modified',
				'order'               => 'DESC',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
			)
		);

		foreach ( $query->posts as $post ) {
			if ( $result['edited'] >= $limit ) {
				break;
			}

			list( $clean )          = $this->replacer->strip_links( $post->post_content );
			list( $linked, $added ) = $this->replacer->replace( $clean, $entries, $this->options() );

			if ( $added > 0 && $linked !== $post->post_content ) {
				$this->save_content( $post->ID, $linked );
				update_post_meta( $post->ID, self::META_APPLIED, 1 );
				$result['edited']++;
				$result['links']    += $added;
				$result['post_ids'][] = $post->ID;
			}
		}

		return $result;
	}

	/* ---------------------------------------------------------------------
	 * Internals
	 * ------------------------------------------------------------------- */

	/**
	 * Replacer options from settings.
	 *
	 * @return array
	 */
	protected function options() {
		return array(
			'max_per_post'    => (int) SPR_Settings::get( 'max_links_per_post', 5 ),
			'max_per_keyword' => (int) SPR_Settings::get( 'max_links_per_keyword', 1 ),
			'skip_headings'   => (bool) SPR_Settings::get( 'skip_headings', 1 ),
			'new_tab'         => (bool) SPR_Settings::get( 'open_new_tab', 0 ),
			'nofollow'        => (bool) SPR_Settings::get( 'nofollow', 0 ),
		);
	}

	/**
	 * Build the batch query for all linkable posts.
	 *
	 * @param int $paged    Page.
	 * @param int $per_page Per page.
	 * @return WP_Query
	 */
	protected function target_query( $paged, $per_page ) {
		return new WP_Query(
			array(
				'post_type'           => (array) SPR_Settings::get( 'link_post_types', array( 'post', 'page' ) ),
				'post_status'         => 'publish',
				'posts_per_page'      => $per_page,
				'paged'               => $paged,
				'orderby'             => 'ID',
				'order'               => 'ASC',
				'ignore_sticky_posts' => true,
			)
		);
	}

	/**
	 * Persist new content for a post without firing our own save hooks twice.
	 *
	 * We update the DB directly-ish through wp_update_post but temporarily
	 * detach the new-post linker to avoid recursion.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $content New post_content.
	 */
	protected function save_content( $post_id, $content ) {
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $content,
			)
		);
	}

	/**
	 * Assemble a uniform progress payload for AJAX batching.
	 *
	 * @param WP_Query $query    Executed query.
	 * @param int      $paged    Current page.
	 * @param int      $per_page Per page.
	 * @param array    $extra    Extra counters to merge in.
	 * @return array
	 */
	protected function progress( $query, $paged, $per_page, $extra ) {
		$total   = (int) $query->found_posts;
		$scanned = min( $paged * $per_page, $total );

		return array_merge(
			array(
				'total'     => $total,
				'scanned'   => $scanned,
				'done'      => $scanned >= $total,
				'next_page' => $paged + 1,
			),
			$extra
		);
	}
}
