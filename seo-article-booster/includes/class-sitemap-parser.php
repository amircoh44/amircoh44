<?php
/**
 * Yoast SEO sitemap fetching and parsing.
 *
 * Yoast publishes a sitemap *index* (e.g. /sitemap_index.xml) that points at a
 * set of child sitemaps (post-sitemap.xml, page-sitemap.xml, ...). This class
 * downloads the index, follows each child and returns the full list of <loc>
 * URLs. Results are cached in a transient to avoid repeated HTTP requests.
 *
 * @package SeoArticleBooster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SAB_Sitemap_Parser
 */
class SAB_Sitemap_Parser {

	/**
	 * How long to cache parsed sitemap URLs.
	 */
	const CACHE_TTL = DAY_IN_SECONDS;

	/**
	 * The most recent error message, for surfacing in the admin UI.
	 *
	 * @var string
	 */
	protected $last_error = '';

	/**
	 * Get every URL listed across the Yoast sitemap, using the cache when available.
	 *
	 * @param bool $force Bypass the cache and re-fetch.
	 * @return string[] List of absolute URLs.
	 */
	public function get_all_urls( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( SAB_TRANSIENT_SITEMAP );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$urls = $this->fetch_all_urls();

		// Cache even an empty array (briefly) so a broken sitemap doesn't hammer
		// the server on every page load.
		set_transient( SAB_TRANSIENT_SITEMAP, $urls, empty( $urls ) ? MINUTE_IN_SECONDS * 15 : self::CACHE_TTL );

		return $urls;
	}

	/**
	 * Perform the actual fetch + parse (no caching).
	 *
	 * @return string[]
	 */
	public function fetch_all_urls() {
		$this->last_error = '';
		$urls             = array();

		// Read every configured sitemap (each may be an index or a flat urlset).
		foreach ( SAB_Settings::get_sitemap_urls() as $sitemap_url ) {
			$urls = array_merge( $urls, $this->fetch_one( $sitemap_url ) );
		}

		// De-duplicate while preserving order.
		return array_values( array_unique( $urls ) );
	}

	/**
	 * Fetch + parse a single sitemap URL (index or urlset) into a list of URLs.
	 *
	 * @param string $url Sitemap URL.
	 * @return string[]
	 */
	protected function fetch_one( $url ) {
		$xml = $this->fetch( $url );
		if ( false === $xml ) {
			return array();
		}

		$root = $this->load_xml( $xml );
		if ( ! $root ) {
			$this->last_error = __( 'A sitemap could not be parsed as valid XML.', 'seo-article-booster' );
			return array();
		}

		$urls = array();

		// A <sitemapindex> lists child sitemaps; a <urlset> lists actual pages.
		if ( 'sitemapindex' === $root->getName() ) {
			foreach ( $root->sitemap as $sitemap ) {
				$child_url = trim( (string) $sitemap->loc );
				if ( '' === $child_url ) {
					continue;
				}
				$child_xml = $this->fetch( $child_url );
				if ( false === $child_xml ) {
					continue;
				}
				$child_root = $this->load_xml( $child_xml );
				if ( $child_root && 'urlset' === $child_root->getName() ) {
					$urls = array_merge( $urls, $this->extract_locs( $child_root ) );
				}
			}
		} elseif ( 'urlset' === $root->getName() ) {
			// Some setups expose a single flat sitemap.
			$urls = $this->extract_locs( $root );
		} else {
			$this->last_error = __( 'Unrecognised sitemap format (expected sitemapindex or urlset).', 'seo-article-booster' );
		}

		return $urls;
	}

	/**
	 * Pull the <loc> values out of a <urlset>.
	 *
	 * @param SimpleXMLElement $urlset Parsed urlset element.
	 * @return string[]
	 */
	protected function extract_locs( $urlset ) {
		$locs = array();
		foreach ( $urlset->url as $url ) {
			$loc = trim( (string) $url->loc );
			if ( '' !== $loc ) {
				$locs[] = $loc;
			}
		}
		return $locs;
	}

	/**
	 * Fetch a URL over HTTP, returning the body or false on failure.
	 *
	 * @param string $url URL to GET.
	 * @return string|false
	 */
	protected function fetch( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 15,
				'user-agent' => 'SeoArticleBooster/' . SAB_VERSION . '; ' . home_url( '/' ),
				// Allow self-signed/local certs on dev installs to still resolve.
				'sslverify'  => apply_filters( 'sab_sitemap_sslverify', true ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->last_error = sprintf(
				/* translators: %s: HTTP error message. */
				__( 'Could not fetch the sitemap: %s', 'seo-article-booster' ),
				$response->get_error_message()
			);
			return false;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$this->last_error = sprintf(
				/* translators: %d: HTTP status code. */
				__( 'The sitemap request returned HTTP status %d.', 'seo-article-booster' ),
				$code
			);
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( '' === trim( (string) $body ) ) {
			$this->last_error = __( 'The sitemap response was empty.', 'seo-article-booster' );
			return false;
		}

		return $body;
	}

	/**
	 * Safely parse an XML string into a SimpleXMLElement.
	 *
	 * libxml internal error handling is toggled so malformed feeds fail quietly
	 * rather than emitting warnings. XXE is mitigated because we never resolve
	 * external entities (the default for simplexml_load_string without options).
	 *
	 * @param string $xml Raw XML.
	 * @return SimpleXMLElement|false
	 */
	protected function load_xml( $xml ) {
		// Strip an XSL stylesheet processing instruction Yoast adds; harmless but tidy.
		$previous = libxml_use_internal_errors( true );

		$element = simplexml_load_string( $xml, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET );

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return $element ? $element : false;
	}

	/**
	 * Expose the most recent error (if any) for admin display.
	 *
	 * @return string
	 */
	public function get_last_error() {
		return $this->last_error;
	}

	/**
	 * Normalise a URL for comparison: lowercase host, strip scheme, www and a
	 * trailing slash. This lets us match permalinks against sitemap entries
	 * even when http/https or trailing-slash settings differ.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function normalize_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		$parts = wp_parse_url( $url );
		if ( false === $parts || empty( $parts['host'] ) ) {
			return rtrim( $url, '/' );
		}

		$host = strtolower( $parts['host'] );
		$host = preg_replace( '/^www\./', '', $host );
		$path = isset( $parts['path'] ) ? $parts['path'] : '/';
		$path = rtrim( $path, '/' );

		return $host . $path;
	}
}
