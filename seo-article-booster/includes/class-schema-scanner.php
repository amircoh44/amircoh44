<?php
/**
 * Schema (structured-data) checking.
 *
 * SEO plugins such as Yoast output JSON-LD structured data in the page <head>
 * at render time — it is not stored in post_content. To verify it reliably we
 * fetch each article's rendered HTML and look for:
 *
 *   - <script type="application/ld+json"> blocks (preferred), decoding the JSON
 *     and collecting every @type (including those inside an @graph), and
 *   - Microdata fallbacks (itemtype="https://schema.org/...").
 *
 * Results are cached in post meta so repeat visits to the audit screen and the
 * editor don't trigger fresh HTTP requests.
 *
 * @package SeoArticleBooster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SAB_Schema_Scanner
 */
class SAB_Schema_Scanner {

	/**
	 * Post-meta keys for the cached result.
	 */
	const META_TYPES   = '_sab_schema_types';
	const META_CHECKED = '_sab_schema_checked';

	/**
	 * Optional sitemap parser (for the sitemap-coverage audit).
	 *
	 * @var SAB_Sitemap_Parser|null
	 */
	protected $sitemap = null;

	/**
	 * Constructor.
	 *
	 * @param SAB_Sitemap_Parser|null $sitemap Sitemap parser.
	 */
	public function __construct( $sitemap = null ) {
		$this->sitemap = $sitemap;
	}

	/**
	 * Wire up editor hooks.
	 */
	public function init() {
		add_action( 'post_submitbox_misc_actions', array( $this, 'render_publish_box_status' ) );
	}

	/* ---------------------------------------------------------------------
	 * Detection
	 * ------------------------------------------------------------------- */

	/**
	 * Fetch a post's rendered HTML and extract the structured-data @type list.
	 *
	 * @param int|WP_Post $post Post.
	 * @return array{checked:bool,types:string[],error:string} Result. `checked`
	 *               is false when the page could not be fetched.
	 */
	public function detect_for_post( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return array(
				'checked' => false,
				'types'   => array(),
				'error'   => __( 'Post not found.', 'seo-article-booster' ),
			);
		}
		return $this->detect_for_url( get_permalink( $post ) );
	}

	/**
	 * Fetch any URL's rendered HTML and extract its structured-data @type list.
	 *
	 * @param string $url URL.
	 * @return array{checked:bool,types:string[],error:string}
	 */
	public function detect_for_url( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 20,
				'user-agent' => 'SeoArticleBooster/' . SAB_VERSION . '; ' . home_url( '/' ),
				'sslverify'  => apply_filters( 'sab_schema_sslverify', true ),
				// Identify ourselves so caching layers can be bypassed if needed.
				'headers'    => array( 'X-SAB-Schema-Check' => '1' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'checked' => false,
				'types'   => array(),
				'error'   => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return array(
				'checked' => false,
				'types'   => array(),
				'error'   => sprintf( /* translators: %d: HTTP status. */ __( 'HTTP %d', 'seo-article-booster' ), $code ),
			);
		}

		return array(
			'checked' => true,
			'types'   => $this->extract_types( (string) wp_remote_retrieve_body( $response ) ),
			'error'   => '',
		);
	}

	/**
	 * Extract Schema.org @type values from an HTML document.
	 *
	 * @param string $html Rendered HTML.
	 * @return string[] Unique list of detected types.
	 */
	public function extract_types( $html ) {
		$types = array();

		if ( '' === trim( $html ) ) {
			return $types;
		}

		// 1. JSON-LD blocks.
		if ( preg_match_all( '#<script[^>]*type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $matches ) ) {
			foreach ( $matches[1] as $json ) {
				$data = json_decode( trim( $json ), true );
				if ( null === $data ) {
					continue;
				}
				$this->collect_types( $data, $types );
			}
		}

		// 2. Microdata fallback (itemtype="https://schema.org/Article").
		if ( preg_match_all( '#itemtype=["\']https?://schema\.org/([A-Za-z]+)["\']#i', $html, $micro ) ) {
			foreach ( $micro[1] as $type ) {
				$types[] = $type;
			}
		}

		return array_values( array_unique( array_filter( $types ) ) );
	}

	/**
	 * Recursively walk decoded JSON-LD collecting @type values.
	 *
	 * @param mixed    $node  Decoded JSON node.
	 * @param string[] $types Accumulator (by reference).
	 */
	protected function collect_types( $node, &$types ) {
		if ( ! is_array( $node ) ) {
			return;
		}

		if ( isset( $node['@type'] ) ) {
			foreach ( (array) $node['@type'] as $type ) {
				if ( is_string( $type ) ) {
					$types[] = $type;
				}
			}
		}

		// Descend into @graph and any nested arrays/objects.
		foreach ( $node as $value ) {
			if ( is_array( $value ) ) {
				$this->collect_types( $value, $types );
			}
		}
	}

	/* ---------------------------------------------------------------------
	 * Pass/fail logic
	 * ------------------------------------------------------------------- */

	/**
	 * The list of required schema types (empty = any structured data passes).
	 *
	 * @return string[]
	 */
	public function required_types() {
		$raw = (string) SAB_Settings::get( 'schema_required_types', '' );
		if ( '' === trim( $raw ) ) {
			return array();
		}
		$parts = array_map( 'trim', explode( ',', $raw ) );
		return array_values( array_filter( $parts ) );
	}

	/**
	 * Decide whether a detected type list satisfies the requirements.
	 *
	 * @param string[] $types Detected types.
	 * @return bool
	 */
	public function passes( $types ) {
		$types = array_map( 'strtolower', (array) $types );

		$required = $this->required_types();
		if ( empty( $required ) ) {
			// Any structured data at all is enough.
			return ! empty( $types );
		}

		foreach ( $required as $req ) {
			if ( in_array( strtolower( $req ), $types, true ) ) {
				return true;
			}
		}
		return false;
	}

	/* ---------------------------------------------------------------------
	 * Caching
	 * ------------------------------------------------------------------- */

	/**
	 * Get the cached detected types for a post (or null if never checked).
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	public function get_cached_types( $post_id ) {
		$checked = get_post_meta( $post_id, self::META_CHECKED, true );
		if ( '' === $checked ) {
			return null;
		}
		$types = get_post_meta( $post_id, self::META_TYPES, true );
		return is_array( $types ) ? $types : array();
	}

	/**
	 * Persist a detection result.
	 *
	 * @param int      $post_id Post ID.
	 * @param string[] $types   Detected types.
	 */
	protected function store( $post_id, $types ) {
		update_post_meta( $post_id, self::META_TYPES, array_values( $types ) );
		update_post_meta( $post_id, self::META_CHECKED, time() );
	}

	/* ---------------------------------------------------------------------
	 * Bulk scanning (AJAX)
	 * ------------------------------------------------------------------- */

	/**
	 * Scan a batch of URLs taken from the configured sitemap(s).
	 *
	 * This "syncs" the schema audit with the sitemaps: every public URL the
	 * sitemaps advertise is fetched and checked for structured data.
	 *
	 * @param int $paged    Batch page.
	 * @param int $per_page URLs per batch.
	 * @return array Progress payload incl. `rows`.
	 */
	public function scan_sitemap_batch( $paged = 1, $per_page = 10 ) {
		$all = $this->sitemap ? $this->sitemap->get_all_urls() : array();
		$all = array_values( $all );

		$total  = count( $all );
		$offset = ( $paged - 1 ) * $per_page;
		$slice  = array_slice( $all, $offset, $per_page );
		$rows   = array();

		foreach ( $slice as $url ) {
			$result = $this->detect_for_url( $url );
			$passes = $result['checked'] && $this->passes( $result['types'] );

			$rows[] = array(
				'url'    => $url,
				'types'  => $result['types'],
				'status' => ! $result['checked'] ? 'error' : ( $passes ? 'ok' : ( empty( $result['types'] ) ? 'none' : 'insufficient' ) ),
				'note'   => $result['error'],
			);
		}

		$scanned = min( $offset + $per_page, $total );

		return array(
			'rows'      => $rows,
			'total'     => $total,
			'scanned'   => $scanned,
			'done'      => $scanned >= $total,
			'next_page' => $paged + 1,
			'required'  => $this->required_types(),
		);
	}

	/**
	 * Scan a batch of posts for missing/insufficient schema.
	 *
	 * Batches are small because each post requires its own HTTP request.
	 *
	 * @param int $paged    Batch page.
	 * @param int $per_page Posts per batch.
	 * @return array Progress payload incl. a `missing` list.
	 */
	public function scan_batch( $paged = 1, $per_page = 10 ) {
		$post_types = (array) SAB_Settings::get( 'audit_post_types', array( 'post' ) );

		$query = new WP_Query(
			array(
				'post_type'           => $post_types,
				'post_status'         => 'publish',
				'posts_per_page'      => $per_page,
				'paged'               => $paged,
				'orderby'             => 'ID',
				'order'               => 'ASC',
				'ignore_sticky_posts' => true,
			)
		);

		$missing = array();

		foreach ( $query->posts as $post ) {
			$result = $this->detect_for_post( $post );

			// Could not fetch — skip caching but report as "unknown".
			if ( ! $result['checked'] ) {
				$missing[] = array(
					'id'        => $post->ID,
					'title'     => get_the_title( $post ),
					'types'     => array(),
					'status'    => 'error',
					'note'      => $result['error'],
					'edit_link' => get_edit_post_link( $post->ID, 'raw' ),
					'view_link' => get_permalink( $post->ID ),
				);
				continue;
			}

			$this->store( $post->ID, $result['types'] );

			if ( ! $this->passes( $result['types'] ) ) {
				$missing[] = array(
					'id'        => $post->ID,
					'title'     => get_the_title( $post ),
					'types'     => $result['types'],
					'status'    => empty( $result['types'] ) ? 'none' : 'insufficient',
					'note'      => '',
					'edit_link' => get_edit_post_link( $post->ID, 'raw' ),
					'view_link' => get_permalink( $post->ID ),
				);
			}
		}

		$total   = (int) $query->found_posts;
		$scanned = min( $paged * $per_page, $total );

		return array(
			'missing'   => $missing,
			'total'     => $total,
			'scanned'   => $scanned,
			'done'      => $scanned >= $total,
			'next_page' => $paged + 1,
			'required'  => $this->required_types(),
		);
	}

	/* ---------------------------------------------------------------------
	 * Editor integration
	 * ------------------------------------------------------------------- */

	/**
	 * Show the last-known schema status in the Publish meta box.
	 *
	 * @param WP_Post $post Current post.
	 */
	public function render_publish_box_status( $post ) {
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( ! SAB_Settings::get( 'enable_schema_check' ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, (array) SAB_Settings::get( 'audit_post_types', array() ), true ) ) {
			return;
		}

		$types = $this->get_cached_types( $post->ID );

		if ( null === $types ) {
			printf(
				'<div class="misc-pub-section sab-pub-schema"><span class="dashicons dashicons-editor-help"></span> %s</div>',
				esc_html__( 'Schema: not yet checked', 'seo-article-booster' )
			);
			return;
		}

		$ok = $this->passes( $types );
		printf(
			'<div class="misc-pub-section sab-pub-schema"><span class="dashicons %s"></span> %s <strong>%s</strong></div>',
			esc_attr( $ok ? 'dashicons-yes-alt' : 'dashicons-warning' ),
			esc_html__( 'Schema:', 'seo-article-booster' ),
			esc_html( empty( $types ) ? __( 'none found', 'seo-article-booster' ) : implode( ', ', $types ) )
		);
	}
}
