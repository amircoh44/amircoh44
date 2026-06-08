<?php
/**
 * Per-article link analysis and inline editing.
 *
 * Enumerates every <a href> in a post's content, classifies each as internal
 * or external, exposes the counts (admin columns + audit screen) and the full
 * link list, and can update a single link's URL and anchor text from the back
 * end without disturbing the rest of the content.
 *
 * Updates use a targeted regex replacement of the Nth anchor (rather than a DOM
 * round-trip) so every other byte of the post content is preserved exactly.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_Link_Scanner
 */
class SPR_Link_Scanner {

	const META_INTERNAL = '_spr_links_internal';
	const META_EXTERNAL = '_spr_links_external';

	/**
	 * Matches each <a ...>...</a> in document order. Group 1 = attributes,
	 * group 2 = inner HTML. Anchors cannot nest, so the lazy body is safe.
	 */
	const ANCHOR_RE = '/<a\b([^>]*)>(.*?)<\/a>/is';

	/**
	 * Hooks.
	 */
	public function init() {
		add_action( 'save_post', array( $this, 'on_save_post' ), 25, 2 );
		add_action( 'admin_init', array( $this, 'register_columns' ) );
	}

	/* ---------------------------------------------------------------------
	 * Analysis
	 * ------------------------------------------------------------------- */

	/**
	 * Return every link in a post's content with classification + edit data.
	 *
	 * @param int|WP_Post $post Post.
	 * @return array[] Each: index, url, anchor_html, anchor_text, type, is_empty_anchor.
	 */
	public function analyze_post( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return array();
		}
		return $this->enumerate( $post->post_content );
	}

	/**
	 * Parse the anchors out of a content string.
	 *
	 * @param string $content Raw post content.
	 * @return array[]
	 */
	public function enumerate( $content ) {
		$links = array();
		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return $links;
		}

		if ( ! preg_match_all( self::ANCHOR_RE, $content, $matches, PREG_SET_ORDER ) ) {
			return $links;
		}

		$index = 0;
		foreach ( $matches as $m ) {
			$attrs = $m[1];
			$inner = $m[2];
			$href  = $this->attr_value( $attrs, 'href' );

			// Skip named anchors / placeholders without a real href.
			if ( null === $href || '' === trim( $href ) ) {
				continue;
			}

			$text = trim( wp_strip_all_tags( $inner ) );

			$links[] = array(
				'index'           => $index,
				'url'             => $href,
				'anchor_html'     => $inner,
				'anchor_text'     => $text,
				'type'            => $this->is_internal( $href ) ? 'internal' : 'external',
				'is_empty_anchor' => ( '' === $text ),
			);
			$index++;
		}

		return $links;
	}

	/**
	 * Count internal/external links for a post.
	 *
	 * @param int|WP_Post $post Post.
	 * @return array{internal:int,external:int,total:int}
	 */
	public function count_for_post( $post ) {
		$internal = 0;
		$external = 0;
		foreach ( $this->analyze_post( $post ) as $link ) {
			if ( 'internal' === $link['type'] ) {
				$internal++;
			} else {
				$external++;
			}
		}
		return array(
			'internal' => $internal,
			'external' => $external,
			'total'    => $internal + $external,
		);
	}

	/**
	 * Decide whether a URL points inside this site.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public function is_internal( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return true;
		}
		// Fragment-only or root-relative links are internal.
		if ( '#' === $url[0] || '/' === $url[0] ) {
			return true;
		}
		// mailto:, tel:, javascript: etc. are treated as external/outbound.
		if ( preg_match( '#^[a-z][a-z0-9+.\-]*:#i', $url ) && ! preg_match( '#^https?://#i', $url ) ) {
			return false;
		}

		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! $host ) {
			return true; // No host => relative => internal.
		}
		$host = strtolower( preg_replace( '/^www\./', '', $host ) );
		$site = strtolower( preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );

		return $host === $site;
	}

	/* ---------------------------------------------------------------------
	 * Editing a single link
	 * ------------------------------------------------------------------- */

	/**
	 * Maximum number of (text) characters allowed in an edited anchor.
	 */
	const MAX_ANCHOR_CHARS = 300;

	/**
	 * Update the Nth link in a post: set its href and replace its anchor HTML.
	 *
	 * @param int    $post_id Post ID.
	 * @param int    $index   0-based anchor index.
	 * @param string $url     New URL.
	 * @param string $html    New anchor inner HTML (will be sanitised).
	 * @return array|WP_Error Updated link data on success.
	 */
	public function update_link( $post_id, $index, $url, $html ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return new WP_Error( 'spr_no_post', __( 'Post not found.', 'seo-sprinkler' ) );
		}

		$inner = $this->sanitize_anchor_html( $html );
		if ( mb_strlen( trim( wp_strip_all_tags( $inner ) ) ) > self::MAX_ANCHOR_CHARS ) {
			return new WP_Error(
				'spr_too_long',
				sprintf(
					/* translators: %d: maximum characters. */
					__( 'The link text must be %d characters or fewer.', 'seo-sprinkler' ),
					self::MAX_ANCHOR_CHARS
				)
			);
		}
		if ( '' === trim( wp_strip_all_tags( $inner ) ) ) {
			return new WP_Error( 'spr_empty', __( 'The link text cannot be empty.', 'seo-sprinkler' ) );
		}

		$clean_url = esc_url_raw( trim( (string) $url ) );
		$found     = false;
		$counter   = 0;

		$new_content = preg_replace_callback(
			self::ANCHOR_RE,
			function ( $m ) use ( &$counter, &$found, $index, $clean_url, $inner ) {
				// Only count anchors that actually have an href (matches enumerate()).
				if ( null === $this->attr_value( $m[1], 'href' ) || '' === trim( (string) $this->attr_value( $m[1], 'href' ) ) ) {
					return $m[0];
				}
				$current = $counter++;
				if ( $current !== $index ) {
					return $m[0];
				}
				$found = true;
				return '<a' . $this->set_href_attr( $m[1], $clean_url ) . '>' . $inner . '</a>';
			},
			$post->post_content
		);

		if ( ! $found || null === $new_content ) {
			return new WP_Error( 'spr_not_found', __( 'That link could not be located (the content may have changed). Please reload.', 'seo-sprinkler' ) );
		}

		if ( $new_content !== $post->post_content ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $new_content,
				)
			);
		}

		return array(
			'index'       => $index,
			'url'         => $clean_url,
			'anchor_html' => $inner,
			'anchor_text' => trim( wp_strip_all_tags( $inner ) ),
			'type'        => $this->is_internal( $clean_url ) ? 'internal' : 'external',
		);
	}

	/**
	 * Allowed inline tags inside an anchor.
	 *
	 * @return array
	 */
	protected function allowed_anchor_tags() {
		return array(
			'strong' => array(),
			'em'     => array(),
			'b'      => array(),
			'i'      => array(),
			'span'   => array(),
			'br'     => array(),
		);
	}

	/**
	 * Sanitise anchor inner HTML to a safe inline subset (no nested links).
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	protected function sanitize_anchor_html( $html ) {
		return trim( wp_kses( (string) $html, $this->allowed_anchor_tags() ) );
	}

	/* ---------------------------------------------------------------------
	 * Attribute helpers
	 * ------------------------------------------------------------------- */

	/**
	 * Read an attribute value out of a raw attribute string, or null.
	 *
	 * @param string $attrs Attribute string.
	 * @param string $name  Attribute name.
	 * @return string|null
	 */
	protected function attr_value( $attrs, $name ) {
		if ( preg_match( '/\b' . preg_quote( $name, '/' ) . '\s*=\s*("([^"]*)"|\'([^\']*)\'|(\S+))/i', $attrs, $m ) ) {
			if ( isset( $m[2] ) && '' !== $m[2] ) {
				return $m[2];
			}
			if ( isset( $m[3] ) && '' !== $m[3] ) {
				return $m[3];
			}
			return isset( $m[4] ) ? $m[4] : '';
		}
		return null;
	}

	/**
	 * Replace (or add) the href in an attribute string, preserving the rest
	 * (class, rel, target, ...). Uses a callback to avoid backreference issues.
	 *
	 * @param string $attrs Attribute string.
	 * @param string $url   New URL (already escaped).
	 * @return string
	 */
	protected function set_href_attr( $attrs, $url ) {
		$count = 0;
		$out   = preg_replace_callback(
			'/\bhref\s*=\s*("[^"]*"|\'[^\']*\'|\S+)/i',
			function () use ( $url ) {
				return 'href="' . esc_url( $url ) . '"';
			},
			$attrs,
			1,
			$count
		);
		if ( 0 === $count ) {
			$out = ' href="' . esc_url( $url ) . '"' . $attrs;
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Cached counts + bulk scan
	 * ------------------------------------------------------------------- */

	/**
	 * Recompute and store link counts on save.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public function on_save_post( $post_id, $post ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, (array) SPR_Settings::get( 'link_post_types', array( 'post', 'page' ) ), true ) ) {
			return;
		}
		$counts = $this->count_for_post( $post );
		update_post_meta( $post_id, self::META_INTERNAL, $counts['internal'] );
		update_post_meta( $post_id, self::META_EXTERNAL, $counts['external'] );
	}

	/**
	 * Read cached counts, computing them once if missing.
	 *
	 * @param int $post_id Post ID.
	 * @return array{internal:int,external:int,total:int}
	 */
	public function get_cached_counts( $post_id ) {
		$internal = get_post_meta( $post_id, self::META_INTERNAL, true );
		if ( '' === $internal ) {
			$counts = $this->count_for_post( $post_id );
			update_post_meta( $post_id, self::META_INTERNAL, $counts['internal'] );
			update_post_meta( $post_id, self::META_EXTERNAL, $counts['external'] );
			return $counts;
		}
		$external = (int) get_post_meta( $post_id, self::META_EXTERNAL, true );
		return array(
			'internal' => (int) $internal,
			'external' => $external,
			'total'    => (int) $internal + $external,
		);
	}

	/**
	 * Scan a batch of posts and return their link counts.
	 *
	 * @param int $paged    Batch page.
	 * @param int $per_page Per page.
	 * @return array
	 */
	public function scan_batch( $paged = 1, $per_page = 50 ) {
		$query = new WP_Query(
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

		$rows = array();
		foreach ( $query->posts as $post ) {
			$counts = $this->count_for_post( $post );
			update_post_meta( $post->ID, self::META_INTERNAL, $counts['internal'] );
			update_post_meta( $post->ID, self::META_EXTERNAL, $counts['external'] );

			$rows[] = array(
				'id'        => $post->ID,
				'title'     => get_the_title( $post ) ? get_the_title( $post ) : __( '(no title)', 'seo-sprinkler' ),
				'internal'  => $counts['internal'],
				'external'  => $counts['external'],
				'view_link' => add_query_arg(
					array(
						'page'   => 'spr-link-audit',
						'action' => 'view',
						'post'   => $post->ID,
					),
					admin_url( 'admin.php' )
				),
			);
		}

		$total   = (int) $query->found_posts;
		$scanned = min( $paged * $per_page, $total );

		return array(
			'rows'      => $rows,
			'total'     => $total,
			'scanned'   => $scanned,
			'done'      => $scanned >= $total,
			'next_page' => $paged + 1,
		);
	}

	/* ---------------------------------------------------------------------
	 * Admin columns
	 * ------------------------------------------------------------------- */

	/**
	 * Register an internal/external links column for linkable post types.
	 */
	public function register_columns() {
		foreach ( (array) SPR_Settings::get( 'link_post_types', array( 'post', 'page' ) ) as $type ) {
			add_filter( "manage_{$type}_posts_columns", array( $this, 'add_column' ) );
			add_action( "manage_{$type}_posts_custom_column", array( $this, 'render_column' ), 10, 2 );
		}
	}

	/**
	 * Add the "Links" column header.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function add_column( $columns ) {
		$columns['spr_links'] = __( 'Links (int / ext)', 'seo-sprinkler' );
		return $columns;
	}

	/**
	 * Render the per-row link counts.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public function render_column( $column, $post_id ) {
		if ( 'spr_links' !== $column ) {
			return;
		}
		$counts = $this->get_cached_counts( $post_id );
		$view   = add_query_arg(
			array(
				'page'   => 'spr-link-audit',
				'action' => 'view',
				'post'   => $post_id,
			),
			admin_url( 'admin.php' )
		);

		$internal_badge = sprintf(
			'<span class="spr-badge %s" title="%s">%d</span>',
			$counts['internal'] > 0 ? 'spr-badge--ok' : 'spr-badge--warn',
			esc_attr__( 'Internal links', 'seo-sprinkler' ),
			(int) $counts['internal']
		);
		$external_badge = sprintf(
			'<span class="spr-badge spr-badge--neutral" title="%s">%d</span>',
			esc_attr__( 'External links', 'seo-sprinkler' ),
			(int) $counts['external']
		);

		printf(
			'%s %s <a href="%s" class="spr-muted">%s</a>',
			$internal_badge, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with escaped values above.
			$external_badge, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			esc_url( $view ),
			esc_html__( 'view', 'seo-sprinkler' )
		);
	}
}
