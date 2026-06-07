<?php
/**
 * Content cleaner — strips "generative content trash" from HTML.
 *
 * AI-generated or pasted content often carries junk: markdown code fences left
 * wrapping the HTML, zero-width characters, empty paragraphs, Word/Office
 * cruft, stray <script>/<style>, runs of <br>, empty inline tags, and so on.
 * This cleaner removes the selected categories of junk.
 *
 * It is mostly regex-based (no DOM round-trip) so it only touches the junk and
 * leaves the rest of the content untouched. Gutenberg block delimiters
 * (`<!-- wp:... -->`) are always preserved.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_Content_Cleaner
 */
class SPR_Content_Cleaner {

	/** Post meta holding the pre-clean backup of post_content. */
	const META_BACKUP      = '_spr_cleaner_backup';
	const META_BACKUP_TIME = '_spr_cleaner_backup_time';

	/** Option holding the cleaning log. */
	const LOG_OPTION = 'spr_cleaner_log';

	/** Maximum number of log entries to retain. */
	const LOG_MAX = 500;

	/**
	 * Map of setting key => human label (also the canonical list of cleanups).
	 *
	 * @return array
	 */
	public static function cleanups() {
		return array(
			'clean_code_fences'     => __( 'Markdown code fences (``` … ```)', 'seo-sprinkler' ),
			'clean_zero_width'      => __( 'Zero-width & invisible characters', 'seo-sprinkler' ),
			'clean_empty_paragraphs' => __( 'Empty paragraphs', 'seo-sprinkler' ),
			'clean_word_cruft'      => __( 'Word/Office cruft (<o:p>, <font>, conditional comments)', 'seo-sprinkler' ),
			'clean_script_style'    => __( 'Stray <script> and <style> blocks', 'seo-sprinkler' ),
			'clean_multiple_br'     => __( 'Runs of three or more <br>', 'seo-sprinkler' ),
			'clean_empty_tags'      => __( 'Empty inline tags (<span></span>, …)', 'seo-sprinkler' ),
			'clean_html_comments'   => __( 'HTML comments (Gutenberg blocks preserved)', 'seo-sprinkler' ),
			'clean_inline_styles'   => __( 'Inline style="…" attributes (CSS)', 'seo-sprinkler' ),
			'clean_classes'         => __( 'class="…" attributes (CSS hooks — may affect layout/blocks)', 'seo-sprinkler' ),
		);
	}

	/**
	 * Hooks.
	 */
	public function init() {
		// Optional: clean content automatically as it is saved.
		add_filter( 'wp_insert_post_data', array( $this, 'on_insert_post_data' ), 20, 2 );
	}

	/* ---------------------------------------------------------------------
	 * Core cleaning
	 * ------------------------------------------------------------------- */

	/**
	 * Clean a content string using the enabled cleanups.
	 *
	 * @param string $html  Content.
	 * @param array  $force Optional explicit on/off map (overrides settings).
	 * @return array{0:string,1:bool,2:array} Cleaned HTML, changed flag, stats.
	 */
	public function clean( $html, $force = null ) {
		$original = (string) $html;
		$html     = $original;
		$stats    = array();

		if ( $this->on( 'clean_code_fences', $force ) ) {
			$html = $this->strip_code_fences( $html, $stats );
		}
		if ( $this->on( 'clean_zero_width', $force ) ) {
			$html = $this->strip_zero_width( $html, $stats );
		}
		if ( $this->on( 'clean_word_cruft', $force ) ) {
			$html = $this->strip_word_cruft( $html, $stats );
		}
		if ( $this->on( 'clean_script_style', $force ) ) {
			$html = $this->strip_script_style( $html, $stats );
		}
		if ( $this->on( 'clean_html_comments', $force ) ) {
			$html = $this->strip_comments( $html, $stats );
		}
		if ( $this->on( 'clean_inline_styles', $force ) ) {
			$html = $this->strip_inline_styles( $html, $stats );
		}
		if ( $this->on( 'clean_classes', $force ) ) {
			$html = $this->strip_classes( $html, $stats );
		}
		if ( $this->on( 'clean_empty_tags', $force ) ) {
			$html = $this->strip_empty_inline( $html, $stats );
		}
		if ( $this->on( 'clean_empty_paragraphs', $force ) ) {
			$html = $this->strip_empty_paragraphs( $html, $stats );
		}
		if ( $this->on( 'clean_multiple_br', $force ) ) {
			$html = $this->collapse_br( $html, $stats );
		}

		return array( $html, ( $html !== $original ), array_filter( $stats ) );
	}

	/**
	 * Is a cleanup enabled (via explicit map or settings)?
	 *
	 * @param string     $key   Cleanup key.
	 * @param array|null $force Explicit map.
	 * @return bool
	 */
	protected function on( $key, $force ) {
		if ( is_array( $force ) ) {
			return ! empty( $force[ $key ] );
		}
		return (bool) SPR_Settings::get( $key );
	}

	/* ---- Individual cleanups ------------------------------------------- */

	/**
	 * Remove markdown code-fence delimiters that wrap HTML.
	 *
	 * Targets fences on their own line, or wrapped in a paragraph — not inline
	 * backticks, so legitimate inline code is left alone.
	 *
	 * @param string $html  HTML.
	 * @param array  $stats Stats (by reference).
	 * @return string
	 */
	protected function strip_code_fences( $html, &$stats ) {
		$total = 0;
		$html  = preg_replace( '/^[ \t]*(?:```|~~~)[a-zA-Z0-9]*[ \t]*\R?/m', '', $html, -1, $c1 );
		$html  = preg_replace( '#<p>\s*(?:```|~~~)[a-zA-Z0-9]*\s*</p>#i', '', $html, -1, $c2 );
		$total = (int) $c1 + (int) $c2;
		$stats['clean_code_fences'] = $total;
		return $html;
	}

	/**
	 * Remove zero-width / invisible characters and BOMs.
	 *
	 * @param string $html  HTML.
	 * @param array  $stats Stats.
	 * @return string
	 */
	protected function strip_zero_width( $html, &$stats ) {
		$html = preg_replace( '/[\x{200B}-\x{200D}\x{2060}\x{FEFF}]/u', '', $html, -1, $c );
		$stats['clean_zero_width'] = (int) $c;
		return $html;
	}

	/**
	 * Remove Word/Office cruft.
	 *
	 * @param string $html  HTML.
	 * @param array  $stats Stats.
	 * @return string
	 */
	protected function strip_word_cruft( $html, &$stats ) {
		$html = preg_replace( '#</?o:p[^>]*>#i', '', $html, -1, $a );          // <o:p>.
		$html = preg_replace( '#</?font[^>]*>#i', '', $html, -1, $b );         // <font> (keep inner text).
		$html = preg_replace( '/<!--\[if[\s\S]*?<!\[endif\]-->/i', '', $html, -1, $d ); // Conditional comments.
		$stats['clean_word_cruft'] = (int) $a + (int) $b + (int) $d;
		return $html;
	}

	/**
	 * Remove stray <script> and <style> blocks.
	 *
	 * @param string $html  HTML.
	 * @param array  $stats Stats.
	 * @return string
	 */
	protected function strip_script_style( $html, &$stats ) {
		$html = preg_replace( '#<script\b[\s\S]*?</script>#i', '', $html, -1, $a );
		$html = preg_replace( '#<style\b[\s\S]*?</style>#i', '', $html, -1, $b );
		$stats['clean_script_style'] = (int) $a + (int) $b;
		return $html;
	}

	/**
	 * Remove HTML comments, but keep Gutenberg block delimiters (<!-- wp:... -->).
	 *
	 * @param string $html  HTML.
	 * @param array  $stats Stats.
	 * @return string
	 */
	protected function strip_comments( $html, &$stats ) {
		$count = 0;
		$html  = preg_replace_callback(
			'/<!--([\s\S]*?)-->/',
			function ( $m ) {
				$inner = ltrim( $m[1] );
				// Preserve Gutenberg block comments.
				if ( 0 === stripos( $inner, 'wp:' ) || 0 === stripos( $inner, '/wp:' ) ) {
					return $m[0];
				}
				return '';
			},
			$html,
			-1,
			$count
		);
		// preg_replace_callback counts ALL matches; we can't easily exclude kept
		// ones, so report a best-effort count of removed comments.
		$stats['clean_html_comments'] = (int) $count;
		return $html;
	}

	/**
	 * Strip inline style attributes.
	 *
	 * @param string $html  HTML.
	 * @param array  $stats Stats.
	 * @return string
	 */
	protected function strip_inline_styles( $html, &$stats ) {
		$html = preg_replace( '/\s*style\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $html, -1, $c );
		$stats['clean_inline_styles'] = (int) $c;
		return $html;
	}

	/**
	 * Strip class attributes (CSS hooks). Aggressive — may affect block/theme
	 * styling, so it is opt-in.
	 *
	 * @param string $html  HTML.
	 * @param array  $stats Stats.
	 * @return string
	 */
	protected function strip_classes( $html, &$stats ) {
		$html = preg_replace( '/\s*class\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $html, -1, $c );
		$stats['clean_classes'] = (int) $c;
		return $html;
	}

	/**
	 * Remove empty inline tags (possibly nested), e.g. <span></span>.
	 *
	 * @param string $html  HTML.
	 * @param array  $stats Stats.
	 * @return string
	 */
	protected function strip_empty_inline( $html, &$stats ) {
		$tags  = 'strong|em|b|i|span|u|small|mark|sub|sup';
		$total = 0;
		do {
			// Delimiter is ~ (not #) because the pattern contains "&#160;".
			$html = preg_replace(
				'~<(' . $tags . ')\b[^>]*>(?:\s|&nbsp;|&#160;|\x{00A0})*</\1>~iu',
				'',
				$html,
				-1,
				$c
			);
			$total += (int) $c;
		} while ( $c > 0 );
		$stats['clean_empty_tags'] = $total;
		return $html;
	}

	/**
	 * Remove empty paragraphs, including empty Gutenberg paragraph blocks.
	 *
	 * @param string $html  HTML.
	 * @param array  $stats Stats.
	 * @return string
	 */
	protected function strip_empty_paragraphs( $html, &$stats ) {
		// Empty Gutenberg paragraph block (comment wrapper + empty <p>).
		// Delimiter is ~ (not #) because the pattern contains "&#160;".
		$html = preg_replace(
			'~<!--\s*wp:paragraph\s*-->\s*<p[^>]*>(?:\s|&nbsp;|&#160;|\x{00A0})*</p>\s*<!--\s*/wp:paragraph\s*-->~iu',
			'',
			$html,
			-1,
			$a
		);
		// Plain empty paragraph.
		$html = preg_replace(
			'~<p[^>]*>(?:\s|&nbsp;|&#160;|\x{00A0})*</p>~iu',
			'',
			$html,
			-1,
			$b
		);
		$stats['clean_empty_paragraphs'] = (int) $a + (int) $b;
		return $html;
	}

	/**
	 * Collapse 3+ consecutive <br> to a maximum of two.
	 *
	 * @param string $html  HTML.
	 * @param array  $stats Stats.
	 * @return string
	 */
	protected function collapse_br( $html, &$stats ) {
		$html = preg_replace( '#(?:<br\s*/?>\s*){3,}#i', "<br />\n<br />\n", $html, -1, $c );
		$stats['clean_multiple_br'] = (int) $c;
		return $html;
	}

	/* ---------------------------------------------------------------------
	 * Per-post + batch operations
	 * ------------------------------------------------------------------- */

	/**
	 * Clean a single post and persist it, backing up the original first so the
	 * change can be reverted, and recording it in the log.
	 *
	 * @param int $post_id Post ID.
	 * @return array{changed:bool,stats:array}
	 */
	public function clean_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array(
				'changed' => false,
				'stats'   => array(),
			);
		}

		list( $clean, $changed, $stats ) = $this->clean( $post->post_content );

		if ( $changed ) {
			// Back up the original content so this clean is reversible.
			update_post_meta( $post_id, self::META_BACKUP, $post->post_content );
			update_post_meta( $post_id, self::META_BACKUP_TIME, time() );

			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $clean,
				)
			);

			$this->log_clean( $post_id, $stats );
		}

		return array(
			'changed' => $changed,
			'stats'   => $stats,
		);
	}

	/**
	 * Restore a post's pre-clean content from its backup.
	 *
	 * @param int $post_id Post ID.
	 * @return true|WP_Error
	 */
	public function revert_post( $post_id ) {
		if ( ! metadata_exists( 'post', $post_id, self::META_BACKUP ) ) {
			return new WP_Error( 'spr_no_backup', __( 'No backup is available for this post.', 'seo-sprinkler' ) );
		}

		$backup = get_post_meta( $post_id, self::META_BACKUP, true );
		wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => $backup,
			)
		);

		delete_post_meta( $post_id, self::META_BACKUP );
		delete_post_meta( $post_id, self::META_BACKUP_TIME );
		$this->mark_reverted( $post_id );

		return true;
	}

	/* ---------------------------------------------------------------------
	 * Change log
	 * ------------------------------------------------------------------- */

	/**
	 * Record a cleaning operation in the log.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $stats   What was removed.
	 */
	protected function log_clean( $post_id, $stats ) {
		$log = $this->get_log_raw();

		$log[ $post_id ] = array(
			'post_id'    => (int) $post_id,
			'title'      => get_the_title( $post_id ),
			'time'       => time(),
			'stats'      => $stats,
			'summary'    => $this->describe_stats( $stats ),
			'removed'    => array_sum( $stats ),
			'reverted'   => false,
			'has_backup' => true,
			'edit_link'  => get_edit_post_link( $post_id, 'raw' ),
		);

		// Keep only the most recent entries.
		if ( count( $log ) > self::LOG_MAX ) {
			uasort(
				$log,
				static function ( $a, $b ) {
					return $b['time'] <=> $a['time'];
				}
			);
			$log = array_slice( $log, 0, self::LOG_MAX, true );
		}

		update_option( self::LOG_OPTION, $log, false );
	}

	/**
	 * Mark a log entry as reverted.
	 *
	 * @param int $post_id Post ID.
	 */
	protected function mark_reverted( $post_id ) {
		$log = $this->get_log_raw();
		if ( isset( $log[ $post_id ] ) ) {
			$log[ $post_id ]['reverted']      = true;
			$log[ $post_id ]['has_backup']    = false;
			$log[ $post_id ]['reverted_time'] = time();
			update_option( self::LOG_OPTION, $log, false );
		}
	}

	/**
	 * Raw log array (keyed by post id).
	 *
	 * @return array
	 */
	protected function get_log_raw() {
		$log = get_option( self::LOG_OPTION, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Get the log entries, newest first.
	 *
	 * @return array[]
	 */
	public function get_log() {
		$log = array_values( $this->get_log_raw() );
		usort(
			$log,
			static function ( $a, $b ) {
				return $b['time'] <=> $a['time'];
			}
		);
		return $log;
	}

	/**
	 * Clear the log (does not touch post backups).
	 */
	public function clear_log() {
		delete_option( self::LOG_OPTION );
	}

	/**
	 * Build a WP_Query for the cleaner's post types.
	 *
	 * @param int $paged    Page.
	 * @param int $per_page Per page.
	 * @return WP_Query
	 */
	protected function query( $paged, $per_page ) {
		return new WP_Query(
			array(
				'post_type'           => (array) SPR_Settings::get( 'link_post_types', array( 'post', 'page' ) ),
				'post_status'         => array( 'publish', 'draft', 'pending', 'private' ),
				'posts_per_page'      => $per_page,
				'paged'               => $paged,
				'orderby'             => 'ID',
				'order'               => 'ASC',
				'ignore_sticky_posts' => true,
			)
		);
	}

	/**
	 * Scan a batch of posts for junk (without modifying them).
	 *
	 * @param int $paged    Page.
	 * @param int $per_page Per page.
	 * @return array
	 */
	public function scan_batch( $paged = 1, $per_page = 40 ) {
		$query = $this->query( $paged, $per_page );
		$rows  = array();

		foreach ( $query->posts as $post ) {
			list( , $changed, $stats ) = $this->clean( $post->post_content );
			if ( $changed ) {
				$rows[] = array(
					'id'        => $post->ID,
					'title'     => get_the_title( $post ) ? get_the_title( $post ) : __( '(no title)', 'seo-sprinkler' ),
					'issues'    => $this->describe_stats( $stats ),
					'edit_link' => get_edit_post_link( $post->ID, 'raw' ),
				);
			}
		}

		return $this->progress( $query, $paged, $per_page, array( 'rows' => $rows ) );
	}

	/**
	 * Clean a batch of posts (permanent).
	 *
	 * @param int $paged    Page.
	 * @param int $per_page Per page.
	 * @return array
	 */
	public function clean_batch( $paged = 1, $per_page = 20 ) {
		$query   = $this->query( $paged, $per_page );
		$cleaned = 0;
		$removed = 0;

		foreach ( $query->posts as $post ) {
			$result = $this->clean_post( $post->ID );
			if ( $result['changed'] ) {
				$cleaned++;
				$removed += array_sum( $result['stats'] );
			}
		}

		return $this->progress(
			$query,
			$paged,
			$per_page,
			array(
				'cleaned' => $cleaned,
				'removed' => $removed,
			)
		);
	}

	/**
	 * Turn a stats array into human-readable issue labels.
	 *
	 * @param array $stats Stats.
	 * @return string
	 */
	protected function describe_stats( $stats ) {
		$labels = self::cleanups();
		$out    = array();
		foreach ( $stats as $key => $count ) {
			if ( $count > 0 && isset( $labels[ $key ] ) ) {
				$out[] = $count . '× ' . $labels[ $key ];
			}
		}
		return implode( '; ', $out );
	}

	/**
	 * Uniform batch progress payload.
	 *
	 * @param WP_Query $query    Query.
	 * @param int      $paged    Page.
	 * @param int      $per_page Per page.
	 * @param array    $extra    Extra data.
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

	/* ---------------------------------------------------------------------
	 * Auto-clean on save
	 * ------------------------------------------------------------------- */

	/**
	 * Clean content as it is inserted/updated, when auto-clean is enabled.
	 *
	 * @param array $data    Slashed post data being saved.
	 * @param array $postarr Raw post array.
	 * @return array
	 */
	public function on_insert_post_data( $data, $postarr ) {
		if ( ! SPR_Settings::get( 'cleaner_autosave' ) ) {
			return $data;
		}
		if ( ! SPR_Edition::can( 'autosave_clean' ) ) {
			return $data; // Auto-clean-on-save is a Pro feature.
		}
		if ( in_array( $data['post_status'], array( 'inherit', 'auto-draft', 'trash' ), true ) ) {
			return $data;
		}
		if ( ! in_array( $data['post_type'], (array) SPR_Settings::get( 'link_post_types', array( 'post', 'page' ) ), true ) ) {
			return $data;
		}

		// $data is slashed; unslash before cleaning and re-slash after.
		list( $clean, $changed ) = $this->clean( wp_unslash( $data['post_content'] ) );
		if ( $changed ) {
			$data['post_content'] = wp_slash( $clean );
		}
		return $data;
	}
}
