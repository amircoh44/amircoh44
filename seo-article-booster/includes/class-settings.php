<?php
/**
 * Settings storage, defaults, sanitisation and registration.
 *
 * All plugin options live in a single serialized array under SAB_OPTION_KEY.
 * This class is the only place that knows the shape of that array, so the rest
 * of the plugin can simply call SAB_Settings::get( 'min_images' ).
 *
 * @package SeoArticleBooster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SAB_Settings
 */
class SAB_Settings {

	/**
	 * Option group / page slug used by the WordPress Settings API.
	 */
	const GROUP = 'sab_settings_group';

	/**
	 * Runtime cache of the merged settings array.
	 *
	 * @var array|null
	 */
	protected static $cache = null;

	/**
	 * Return the default value for every supported setting.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			// --- Image minimum auditing ---------------------------------.
			'min_images'             => 3,      // Minimum images required per article.
			'count_featured_image'   => 1,      // Count the featured image toward the total.
			'audit_post_types'       => array( 'post' ), // Post types to audit for images + schema.

			// --- Schema (structured data) ------------------------------.
			'enable_schema_check'    => 1,      // Audit articles for JSON-LD/microdata.
			'schema_required_types'  => '',     // Comma list, e.g. "Article,BlogPosting" (blank = any).

			// --- Content distribution (Sprinkler) ----------------------.
			'enable_distribution'    => 1,      // Master switch for rule-based content injection.

			// --- Internal linking --------------------------------------.
			'enable_auto_linking'    => 1,      // Master switch for display-time linking.
			'link_post_types'        => array( 'post', 'page' ), // Where links may be injected.
			'max_links_per_post'     => 5,      // Hard cap of injected links per page view.
			'max_links_per_keyword'  => 1,      // Times a single phrase may be linked per post.
			'min_keyword_length'     => 4,      // Ignore anchor phrases shorter than this.
			'case_sensitive'         => 0,      // Match phrases case-sensitively?
			'open_new_tab'           => 0,      // Add target="_blank" to injected links.
			'nofollow'               => 0,      // Add rel="nofollow" to injected links.
			'use_title_as_keyword'   => 1,      // Use each post title as a linkable phrase.
			'use_yoast_focus_kw'     => 1,      // Use Yoast focus keyword(s) as phrases.
			'skip_headings'          => 1,      // Never inject links inside <h1>-<h6>.

			// --- Yoast sitemap -----------------------------------------.
			'sitemap_url'            => '',     // Blank = auto-detect /sitemap_index.xml.
			'restrict_to_sitemap'    => 1,      // Only link to URLs present in the sitemap.

			// --- New post awareness ------------------------------------.
			'auto_link_new_posts'    => 1,             // React to newly published content.
			'auto_apply_inbound'     => 0,             // Permanently write inbound links on publish.
			'inbound_apply_limit'    => 5,             // Max existing posts to edit per new post.
		);
	}

	/**
	 * Booleans we must cast so checkbox values behave predictably.
	 *
	 * @var string[]
	 */
	protected static $booleans = array(
		'count_featured_image',
		'enable_schema_check',
		'enable_distribution',
		'enable_auto_linking',
		'case_sensitive',
		'open_new_tab',
		'nofollow',
		'use_title_as_keyword',
		'use_yoast_focus_kw',
		'skip_headings',
		'restrict_to_sitemap',
		'auto_link_new_posts',
		'auto_apply_inbound',
	);

	/**
	 * Integer fields with sane min/max bounds.
	 *
	 * @var array<string,array<int,int>> field => array( min, max ).
	 */
	protected static $integers = array(
		'min_images'            => array( 0, 100 ),
		'max_links_per_post'    => array( 0, 100 ),
		'max_links_per_keyword' => array( 1, 20 ),
		'min_keyword_length'    => array( 2, 50 ),
		'inbound_apply_limit'   => array( 0, 100 ),
	);

	/**
	 * Seed defaults on activation without clobbering existing values.
	 */
	public static function install_defaults() {
		$existing = get_option( SAB_OPTION_KEY, array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}
		update_option( SAB_OPTION_KEY, wp_parse_args( $existing, self::defaults() ) );
	}

	/**
	 * Fetch the full, defaults-merged settings array.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored      = get_option( SAB_OPTION_KEY, array() );
			$stored      = is_array( $stored ) ? $stored : array();
			self::$cache = wp_parse_args( $stored, self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * Fetch a single setting.
	 *
	 * @param string $key     Setting name.
	 * @param mixed  $default Fallback if the key is unknown.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		return $default;
	}

	/**
	 * Clear the in-memory cache (call after saving).
	 */
	public static function flush_cache() {
		self::$cache = null;
	}

	/**
	 * Register the option with the Settings API so update_option works through
	 * options.php with full sanitisation.
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			SAB_OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Sanitise the entire settings array coming from the admin form.
	 *
	 * Every field is validated against its expected type so that hand-crafted
	 * POST requests cannot inject unexpected data.
	 *
	 * @param mixed $input Raw submitted value.
	 * @return array Clean settings.
	 */
	public static function sanitize( $input ) {
		$defaults = self::defaults();
		$clean    = array();
		$input    = is_array( $input ) ? $input : array();

		foreach ( $defaults as $key => $default_value ) {

			// Booleans: present + truthy checkbox => 1, otherwise 0.
			if ( in_array( $key, self::$booleans, true ) ) {
				$clean[ $key ] = ( ! empty( $input[ $key ] ) ) ? 1 : 0;
				continue;
			}

			// Bounded integers.
			if ( isset( self::$integers[ $key ] ) ) {
				list( $min, $max ) = self::$integers[ $key ];
				$value             = isset( $input[ $key ] ) ? (int) $input[ $key ] : (int) $default_value;
				$clean[ $key ]     = max( $min, min( $max, $value ) );
				continue;
			}

			// Post-type lists: keep only registered, public post types.
			if ( in_array( $key, array( 'audit_post_types', 'link_post_types' ), true ) ) {
				$submitted        = isset( $input[ $key ] ) ? (array) $input[ $key ] : array();
				$valid_types      = get_post_types( array( 'public' => true ) );
				$clean[ $key ]    = array_values( array_intersect( array_map( 'sanitize_key', $submitted ), $valid_types ) );
				if ( empty( $clean[ $key ] ) ) {
					$clean[ $key ] = $default_value; // Never leave it empty.
				}
				continue;
			}

			// Sitemap URL.
			if ( 'sitemap_url' === $key ) {
				$raw           = isset( $input[ $key ] ) ? trim( (string) $input[ $key ] ) : '';
				$clean[ $key ] = ( '' === $raw ) ? '' : esc_url_raw( $raw );
				continue;
			}

			// Fallback: treat as plain text.
			$clean[ $key ] = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : $default_value;
		}

		// Saving settings can change which posts are linkable; drop the caches.
		delete_transient( SAB_TRANSIENT_INDEX );
		delete_transient( SAB_TRANSIENT_SITEMAP );
		self::flush_cache();

		return $clean;
	}

	/**
	 * Resolve the Yoast sitemap index URL, honouring the manual override.
	 *
	 * Yoast SEO publishes its index at /sitemap_index.xml by default.
	 *
	 * @return string
	 */
	public static function get_sitemap_url() {
		$override = trim( (string) self::get( 'sitemap_url', '' ) );
		if ( '' !== $override ) {
			return $override;
		}
		return home_url( '/sitemap_index.xml' );
	}
}
