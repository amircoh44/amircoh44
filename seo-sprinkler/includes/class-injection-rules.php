<?php
/**
 * Injection rule storage, sanitisation and targeting.
 *
 * A "rule" describes WHAT to inject (an Elementor/other shortcode, an image,
 * or custom HTML), WHICH articles to inject it into (by post type, tag,
 * category, keyword, or explicit include/exclude), and WHERE in the content to
 * place it (around sub-headings, around paragraphs, between paragraphs, ...).
 *
 * Rules are stored as an array under a single option so they are easy to
 * back up and inspect. This class owns the shape of a rule; the distributor
 * (SPR_Content_Distributor) consumes them.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_Injection_Rules
 */
class SPR_Injection_Rules {

	/**
	 * Option key holding the rules array.
	 */
	const OPTION = 'spr_injection_rules';

	/**
	 * Allowed placement modes.
	 *
	 * @var string[]
	 */
	public static $placements = array(
		'top',
		'bottom',
		'before_paragraph',
		'after_paragraph',
		'before_heading',
		'after_heading',
		'before_first_heading',
		'after_first_heading',
		'before_last_heading',
		'after_last_heading',
		'between_paragraphs',
		'every_n_paragraphs',
		'middle',
		'after_words',
	);

	/**
	 * Default values for a single rule.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'id'             => '',
			'title'          => '',
			'enabled'        => 1,

			// --- Targeting ---------------------------------------------.
			'post_types'     => array( 'post' ),
			'match_logic'    => 'any',            // any|all.
			'tag_slugs'      => '',               // Comma list of tag slugs/names.
			'cat_slugs'      => '',               // Comma list of category slugs/names.
			'keywords'       => '',               // Comma list, matched in title/content.
			'include_ids'    => '',               // Comma list of post IDs (always match).
			'exclude_ids'    => '',               // Comma list of post IDs (never match).

			// --- Payload -----------------------------------------------.
			'payload_type'   => 'shortcode',      // shortcode|image|html.
			'shortcode'      => '',
			'image_id'       => 0,
			'image_size'     => 'large',
			'image_align'    => 'center',         // none|left|center|right.
			'image_link'     => '',
			'image_alt'      => '',
			'html'           => '',

			// --- Placement ---------------------------------------------.
			'placement'      => 'after_paragraph',
			'position'       => 2,                // Nth paragraph/heading, N for every_n, or word count.
			'heading_levels' => array( 'h2', 'h3' ),
			'max_insertions' => 1,
			'min_paragraphs' => 1,                // Don't inject before this many paragraphs.
			'spacing'        => 2,                // every_n: paragraphs between insertions.

			// --- Options (modern campaign-style controls) --------------.
			'wrapper_class'  => '',
			'device'         => 'all',            // all|desktop|mobile.
			'dedupe'         => 1,                // Skip if the payload is already present.
			'start_date'     => '',               // YYYY-MM-DD (optional schedule).
			'end_date'       => '',
			'priority'       => 10,
		);
	}

	/* ---------------------------------------------------------------------
	 * CRUD
	 * ------------------------------------------------------------------- */

	/**
	 * Get all rules (defaults-merged), keyed by id.
	 *
	 * @return array<string,array>
	 */
	public function get_rules() {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$rules = array();
		foreach ( $stored as $id => $rule ) {
			$rule       = wp_parse_args( is_array( $rule ) ? $rule : array(), self::defaults() );
			$rule['id'] = $id;
			$rules[ $id ] = $rule;
		}
		return $rules;
	}

	/**
	 * Get a single rule or null.
	 *
	 * @param string $id Rule id.
	 * @return array|null
	 */
	public function get_rule( $id ) {
		$rules = $this->get_rules();
		return isset( $rules[ $id ] ) ? $rules[ $id ] : null;
	}

	/**
	 * Insert or update a rule. Returns the saved id.
	 *
	 * @param array $data Raw rule data (typically from $_POST).
	 * @return string Rule id.
	 */
	public function save_rule( $data ) {
		$rules = get_option( self::OPTION, array() );
		$rules = is_array( $rules ) ? $rules : array();

		$clean = $this->sanitize_rule( $data );
		$id    = $clean['id'];
		if ( '' === $id || ! isset( $rules[ $id ] ) ) {
			$id          = ( '' !== $id ) ? $id : $this->generate_id();
			$clean['id'] = $id;
		}

		$rules[ $id ] = $clean;
		update_option( self::OPTION, $rules );
		return $id;
	}

	/**
	 * Delete a rule.
	 *
	 * @param string $id Rule id.
	 * @return bool
	 */
	public function delete_rule( $id ) {
		$rules = get_option( self::OPTION, array() );
		if ( ! is_array( $rules ) || ! isset( $rules[ $id ] ) ) {
			return false;
		}
		unset( $rules[ $id ] );
		update_option( self::OPTION, $rules );
		return true;
	}

	/**
	 * Generate a unique rule id.
	 *
	 * @return string
	 */
	protected function generate_id() {
		return 'r_' . substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 10 );
	}

	/* ---------------------------------------------------------------------
	 * Sanitisation
	 * ------------------------------------------------------------------- */

	/**
	 * Validate + clean a rule submitted from the admin form.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_rule( $input ) {
		$d     = self::defaults();
		$input = is_array( $input ) ? $input : array();
		$out   = array();

		$out['id']      = isset( $input['id'] ) ? preg_replace( '/[^a-z0-9_]/', '', (string) $input['id'] ) : '';
		$out['title']   = isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : '';
		$out['enabled'] = empty( $input['enabled'] ) ? 0 : 1;

		// Targeting.
		$valid_types        = get_post_types( array( 'public' => true ) );
		$submitted_types    = isset( $input['post_types'] ) ? (array) $input['post_types'] : array();
		$out['post_types']  = array_values( array_intersect( array_map( 'sanitize_key', $submitted_types ), $valid_types ) );
		if ( empty( $out['post_types'] ) ) {
			$out['post_types'] = array( 'post' );
		}
		$out['match_logic'] = ( isset( $input['match_logic'] ) && 'all' === $input['match_logic'] ) ? 'all' : 'any';
		$out['tag_slugs']   = $this->clean_csv_text( isset( $input['tag_slugs'] ) ? $input['tag_slugs'] : '' );
		$out['cat_slugs']   = $this->clean_csv_text( isset( $input['cat_slugs'] ) ? $input['cat_slugs'] : '' );
		$out['keywords']    = $this->clean_csv_text( isset( $input['keywords'] ) ? $input['keywords'] : '' );
		$out['include_ids'] = $this->clean_id_csv( isset( $input['include_ids'] ) ? $input['include_ids'] : '' );
		$out['exclude_ids'] = $this->clean_id_csv( isset( $input['exclude_ids'] ) ? $input['exclude_ids'] : '' );

		// Payload.
		$type               = isset( $input['payload_type'] ) ? sanitize_key( $input['payload_type'] ) : 'shortcode';
		$out['payload_type'] = in_array( $type, array( 'shortcode', 'image', 'html' ), true ) ? $type : 'shortcode';
		// Shortcodes may legitimately contain quotes/brackets; keep as text (no tags).
		$out['shortcode']   = isset( $input['shortcode'] ) ? trim( wp_kses_post( wp_unslash( $input['shortcode'] ) ) ) : '';
		$out['image_id']    = isset( $input['image_id'] ) ? absint( $input['image_id'] ) : 0;
		$out['image_size']  = isset( $input['image_size'] ) ? sanitize_key( $input['image_size'] ) : 'large';
		$align              = isset( $input['image_align'] ) ? sanitize_key( $input['image_align'] ) : 'center';
		$out['image_align'] = in_array( $align, array( 'none', 'left', 'center', 'right' ), true ) ? $align : 'center';
		$out['image_link']  = isset( $input['image_link'] ) ? esc_url_raw( trim( (string) $input['image_link'] ) ) : '';
		$out['image_alt']   = isset( $input['image_alt'] ) ? sanitize_text_field( $input['image_alt'] ) : '';
		$out['html']        = isset( $input['html'] ) ? wp_kses_post( wp_unslash( $input['html'] ) ) : '';

		// Placement.
		$placement        = isset( $input['placement'] ) ? sanitize_key( $input['placement'] ) : 'after_paragraph';
		$out['placement'] = in_array( $placement, self::$placements, true ) ? $placement : 'after_paragraph';
		$out['position']  = isset( $input['position'] ) ? max( 1, (int) $input['position'] ) : 2;

		$levels                = isset( $input['heading_levels'] ) ? (array) $input['heading_levels'] : array();
		$levels                = array_values( array_intersect( array_map( 'sanitize_key', $levels ), array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) ) );
		$out['heading_levels'] = empty( $levels ) ? array( 'h2', 'h3' ) : $levels;

		$out['max_insertions'] = isset( $input['max_insertions'] ) ? min( 20, max( 1, (int) $input['max_insertions'] ) ) : 1;
		$out['min_paragraphs'] = isset( $input['min_paragraphs'] ) ? max( 0, (int) $input['min_paragraphs'] ) : 1;
		$out['spacing']        = isset( $input['spacing'] ) ? max( 1, (int) $input['spacing'] ) : 2;

		// Options.
		$out['wrapper_class'] = isset( $input['wrapper_class'] ) ? sanitize_html_class( str_replace( ' ', '-', (string) $input['wrapper_class'] ) ) : '';
		$device               = isset( $input['device'] ) ? sanitize_key( $input['device'] ) : 'all';
		$out['device']        = in_array( $device, array( 'all', 'desktop', 'mobile' ), true ) ? $device : 'all';
		$out['dedupe']        = empty( $input['dedupe'] ) ? 0 : 1;
		$out['start_date']    = $this->clean_date( isset( $input['start_date'] ) ? $input['start_date'] : '' );
		$out['end_date']      = $this->clean_date( isset( $input['end_date'] ) ? $input['end_date'] : '' );
		$out['priority']      = isset( $input['priority'] ) ? (int) $input['priority'] : 10;

		return wp_parse_args( $out, $d );
	}

	/**
	 * Normalise a comma-separated text list (trims, drops empties, de-dupes).
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function clean_csv_text( $value ) {
		$parts = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( (string) $value ) ) ) );
		return implode( ', ', array_unique( $parts ) );
	}

	/**
	 * Normalise a comma-separated list of post IDs.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function clean_id_csv( $value ) {
		$ids = array_filter( array_map( 'absint', explode( ',', (string) $value ) ) );
		return implode( ', ', array_unique( $ids ) );
	}

	/**
	 * Validate a YYYY-MM-DD date, or return ''.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function clean_date( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$d = DateTime::createFromFormat( 'Y-m-d', $value );
		return ( $d && $d->format( 'Y-m-d' ) === $value ) ? $value : '';
	}

	/* ---------------------------------------------------------------------
	 * Targeting (used by the distributor)
	 * ------------------------------------------------------------------- */

	/**
	 * Return the enabled rules that match a given post, ordered by priority.
	 *
	 * @param int|WP_Post $post Post.
	 * @return array[]
	 */
	public function get_matching_rules( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return array();
		}

		$now      = current_time( 'timestamp' );
		$matched  = array();

		foreach ( $this->get_rules() as $rule ) {
			if ( empty( $rule['enabled'] ) ) {
				continue;
			}
			if ( ! $this->in_schedule( $rule, $now ) ) {
				continue;
			}
			if ( ! in_array( $post->post_type, (array) $rule['post_types'], true ) ) {
				continue;
			}

			$exclude = $this->csv_to_ids( $rule['exclude_ids'] );
			if ( in_array( $post->ID, $exclude, true ) ) {
				continue;
			}

			// Explicit include wins immediately.
			$include = $this->csv_to_ids( $rule['include_ids'] );
			if ( in_array( $post->ID, $include, true ) ) {
				$matched[] = $rule;
				continue;
			}

			$conditions = array();
			$tag_ids    = $this->slugs_to_ids( $rule['tag_slugs'], 'post_tag' );
			$cat_ids    = $this->slugs_to_ids( $rule['cat_slugs'], 'category' );

			if ( ! empty( $tag_ids ) ) {
				$conditions[] = has_term( $tag_ids, 'post_tag', $post );
			}
			if ( ! empty( $cat_ids ) ) {
				$conditions[] = has_term( $cat_ids, 'category', $post );
			}
			if ( '' !== trim( $rule['keywords'] ) ) {
				$conditions[] = $this->matches_keywords( $post, $rule['keywords'] );
			}

			// No conditions = applies to all posts of the chosen types.
			if ( empty( $conditions ) ) {
				$matched[] = $rule;
				continue;
			}

			$ok = ( 'all' === $rule['match_logic'] )
				? ! in_array( false, $conditions, true )
				: in_array( true, $conditions, true );

			if ( $ok ) {
				$matched[] = $rule;
			}
		}

		usort(
			$matched,
			static function ( $a, $b ) {
				return (int) $a['priority'] <=> (int) $b['priority'];
			}
		);

		return $matched;
	}

	/**
	 * Is "now" within the rule's optional schedule window?
	 *
	 * @param array $rule Rule.
	 * @param int   $now  Timestamp.
	 * @return bool
	 */
	protected function in_schedule( $rule, $now ) {
		if ( ! empty( $rule['start_date'] ) ) {
			$start = strtotime( $rule['start_date'] . ' 00:00:00' );
			if ( $start && $now < $start ) {
				return false;
			}
		}
		if ( ! empty( $rule['end_date'] ) ) {
			$end = strtotime( $rule['end_date'] . ' 23:59:59' );
			if ( $end && $now > $end ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Does any keyword appear in the post title or content?
	 *
	 * @param WP_Post $post     Post.
	 * @param string  $keywords Comma list.
	 * @return bool
	 */
	protected function matches_keywords( $post, $keywords ) {
		$haystack = strtolower( $post->post_title . ' ' . wp_strip_all_tags( $post->post_content ) );
		foreach ( array_filter( array_map( 'trim', explode( ',', $keywords ) ) ) as $kw ) {
			if ( '' !== $kw && false !== strpos( $haystack, strtolower( $kw ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Convert a comma list of post IDs to an int array.
	 *
	 * @param string $csv CSV.
	 * @return int[]
	 */
	protected function csv_to_ids( $csv ) {
		return array_values( array_filter( array_map( 'absint', explode( ',', (string) $csv ) ) ) );
	}

	/**
	 * Resolve a comma list of term slugs/names to term IDs for a taxonomy.
	 *
	 * @param string $csv      Slugs or names.
	 * @param string $taxonomy Taxonomy.
	 * @return int[]
	 */
	public function slugs_to_ids( $csv, $taxonomy ) {
		$ids = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', (string) $csv ) ) ) as $token ) {
			$term = get_term_by( 'slug', sanitize_title( $token ), $taxonomy );
			if ( ! $term ) {
				$term = get_term_by( 'name', $token, $taxonomy );
			}
			if ( $term && ! is_wp_error( $term ) ) {
				$ids[] = (int) $term->term_id;
			}
		}
		return array_values( array_unique( $ids ) );
	}
}
