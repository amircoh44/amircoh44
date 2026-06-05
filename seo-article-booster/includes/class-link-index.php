<?php
/**
 * Builds the internal-linking index.
 *
 * For every published, sitemap-listed post/page we derive one or more "anchor
 * phrases" (the post title and/or its Yoast focus keyword(s)) and map them to
 * the post's permalink. The display filter and the apply tool then look for
 * those phrases inside other content.
 *
 * Each index entry is precompiled with the regex used to match it, so matching
 * across thousands of text nodes stays fast.
 *
 * @package SeoArticleBooster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SAB_Link_Index
 */
class SAB_Link_Index {

	/**
	 * Sitemap parser dependency.
	 *
	 * @var SAB_Sitemap_Parser
	 */
	protected $sitemap;

	/**
	 * Per-request memo of the built index.
	 *
	 * @var array|null
	 */
	protected $runtime_cache = null;

	/**
	 * Constructor.
	 *
	 * @param SAB_Sitemap_Parser $sitemap Sitemap parser.
	 */
	public function __construct( SAB_Sitemap_Parser $sitemap ) {
		$this->sitemap = $sitemap;
	}

	/**
	 * Return the index, building (and caching) it if necessary.
	 *
	 * @param bool $force Rebuild even if cached.
	 * @return array List of entries: array( post_id, url, phrase, key, regex ).
	 */
	public function get_index( $force = false ) {
		if ( ! $force && null !== $this->runtime_cache ) {
			return $this->runtime_cache;
		}

		if ( ! $force ) {
			$cached = get_transient( SAB_TRANSIENT_INDEX );
			if ( is_array( $cached ) ) {
				$this->runtime_cache = $this->compile( $cached );
				return $this->runtime_cache;
			}
		}

		$raw = $this->build();
		set_transient( SAB_TRANSIENT_INDEX, $raw, DAY_IN_SECONDS );

		$this->runtime_cache = $this->compile( $raw );
		return $this->runtime_cache;
	}

	/**
	 * Force a rebuild and refresh the cache. Returns the raw entry count.
	 *
	 * @return int Number of anchor entries indexed.
	 */
	public function rebuild() {
		$raw = $this->build();
		set_transient( SAB_TRANSIENT_INDEX, $raw, DAY_IN_SECONDS );
		$this->runtime_cache = $this->compile( $raw );
		return count( $raw );
	}

	/**
	 * Clear caches so the next read rebuilds.
	 */
	public function clear() {
		$this->runtime_cache = null;
		delete_transient( SAB_TRANSIENT_INDEX );
	}

	/**
	 * Build the raw (uncompiled, cacheable) index from the database + sitemap.
	 *
	 * @return array List of array( post_id, url, phrase, key ).
	 */
	protected function build() {
		$post_types = (array) SAB_Settings::get( 'link_post_types', array( 'post', 'page' ) );
		$min_len    = (int) SAB_Settings::get( 'min_keyword_length', 4 );
		$restrict   = (bool) SAB_Settings::get( 'restrict_to_sitemap', 1 );

		// Map of normalised sitemap URLs for fast lookup.
		$sitemap_lookup = array();
		if ( $restrict ) {
			foreach ( $this->sitemap->get_all_urls() as $url ) {
				$sitemap_lookup[ SAB_Sitemap_Parser::normalize_url( $url ) ] = true;
			}
			// If the sitemap could not be read, fall back to "no restriction" so
			// the linker still works (and the admin UI warns about it separately).
			if ( empty( $sitemap_lookup ) ) {
				$restrict = false;
			}
		}

		$query = new WP_Query(
			array(
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		$entries  = array();
		$seen_key = array(); // Avoid duplicate phrases pointing at different posts.

		foreach ( $query->posts as $post_id ) {
			$url = get_permalink( $post_id );
			if ( ! $url ) {
				continue;
			}

			if ( $restrict && ! isset( $sitemap_lookup[ SAB_Sitemap_Parser::normalize_url( $url ) ] ) ) {
				continue; // Not present in the Yoast sitemap.
			}

			foreach ( $this->phrases_for_post( $post_id ) as $phrase ) {
				$phrase = trim( $phrase );
				$key    = function_exists( 'mb_strtolower' ) ? mb_strtolower( $phrase ) : strtolower( $phrase );

				if ( $this->phrase_length( $phrase ) < $min_len ) {
					continue;
				}
				// First post to claim a phrase keeps it (prevents ambiguous targets).
				if ( isset( $seen_key[ $key ] ) ) {
					continue;
				}
				$seen_key[ $key ] = true;

				$entries[] = array(
					'post_id' => (int) $post_id,
					'url'     => $url,
					'phrase'  => $phrase,
					'key'     => $key,
				);
			}
		}

		// Longest phrases first so specific anchors beat generic ones.
		usort(
			$entries,
			function ( $a, $b ) {
				return $this->phrase_length( $b['phrase'] ) <=> $this->phrase_length( $a['phrase'] );
			}
		);

		/**
		 * Filter the raw link index before it is cached.
		 *
		 * @param array $entries Raw entries.
		 */
		return apply_filters( 'sab_link_index', $entries );
	}

	/**
	 * Gather candidate anchor phrases for a post (title + Yoast keywords).
	 *
	 * @param int $post_id Post ID.
	 * @return string[]
	 */
	protected function phrases_for_post( $post_id ) {
		$phrases = array();

		if ( SAB_Settings::get( 'use_title_as_keyword' ) ) {
			$title = get_the_title( $post_id );
			if ( $title ) {
				$phrases[] = wp_strip_all_tags( $title );
			}
		}

		if ( SAB_Settings::get( 'use_yoast_focus_kw' ) ) {
			// Primary Yoast focus keyword.
			$focus = get_post_meta( $post_id, '_yoast_wpseo_focuskw', true );
			if ( $focus ) {
				$phrases[] = $focus;
			}

			// Additional keywords (Yoast Premium) are stored as JSON.
			$extra_json = get_post_meta( $post_id, '_yoast_wpseo_focuskeywords', true );
			if ( $extra_json ) {
				$decoded = json_decode( $extra_json, true );
				if ( is_array( $decoded ) ) {
					foreach ( $decoded as $row ) {
						if ( is_array( $row ) && ! empty( $row['keyword'] ) ) {
							$phrases[] = $row['keyword'];
						}
					}
				}
			}
		}

		/**
		 * Filter the anchor phrases for a single post.
		 *
		 * @param string[] $phrases Phrases.
		 * @param int      $post_id Post ID.
		 */
		return array_unique( apply_filters( 'sab_post_phrases', $phrases, $post_id ) );
	}

	/**
	 * Attach a compiled regex to each entry. Done outside the cache because a
	 * compiled pattern is just a string anyway and keeps the cache portable.
	 *
	 * @param array $raw Raw entries.
	 * @return array Compiled entries.
	 */
	protected function compile( $raw ) {
		$case_flag = SAB_Settings::get( 'case_sensitive' ) ? '' : 'i';

		$compiled = array();
		foreach ( $raw as $entry ) {
			$quoted = preg_quote( $entry['phrase'], '/' );
			// Unicode-aware whole-word boundaries: no letter/number/underscore on
			// either side of the phrase. Capture group 1 is the phrase itself.
			$entry['regex'] = '/(?<![\p{L}\p{N}_])(' . $quoted . ')(?![\p{L}\p{N}_])/u' . $case_flag;
			$compiled[]     = $entry;
		}
		return $compiled;
	}

	/**
	 * Multibyte-safe length of a phrase.
	 *
	 * @param string $phrase Phrase.
	 * @return int
	 */
	protected function phrase_length( $phrase ) {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $phrase ) : strlen( $phrase );
	}

	/**
	 * Return only the entries that do NOT belong to the given post (so a post
	 * never links to itself) — used by the display filter and apply tool.
	 *
	 * @param int $exclude_post_id Post to exclude.
	 * @return array
	 */
	public function get_index_excluding( $exclude_post_id ) {
		$exclude_post_id = (int) $exclude_post_id;
		$out             = array();
		foreach ( $this->get_index() as $entry ) {
			if ( $entry['post_id'] !== $exclude_post_id ) {
				$out[] = $entry;
			}
		}
		return $out;
	}

	/**
	 * Return only the entries that point at a single target post — used by the
	 * "inbound links to a new post" feature.
	 *
	 * @param int $target_post_id Target post.
	 * @return array
	 */
	public function get_entries_for_target( $target_post_id ) {
		$target_post_id = (int) $target_post_id;
		$out            = array();
		foreach ( $this->get_index() as $entry ) {
			if ( $entry['post_id'] === $target_post_id ) {
				$out[] = $entry;
			}
		}
		return $out;
	}
}
