<?php
/**
 * SEO plugin detector.
 *
 * Recognises the major SEO plugins and where they store their per-post data,
 * so the exporter can carry that data across to another platform — and so the
 * site owner can see/keep it if they stay on WordPress.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_SEO_Detector
 */
class SPR_SEO_Detector {

	/**
	 * Known SEO plugins and the post meta they use.
	 *
	 * `meta` maps a normalised field => the plugin's meta key. `extra_meta`
	 * lists additional keys worth exporting verbatim.
	 *
	 * @return array
	 */
	public static function known() {
		return array(
			'yoast'        => array(
				'name'       => 'Yoast SEO',
				'const'      => 'WPSEO_VERSION',
				'plugin'     => 'wordpress-seo/wp-seo.php',
				'meta'       => array(
					'title'          => '_yoast_wpseo_title',
					'description'    => '_yoast_wpseo_metadesc',
					'focus_keyword'  => '_yoast_wpseo_focuskw',
					'canonical'      => '_yoast_wpseo_canonical',
					'robots_noindex' => '_yoast_wpseo_meta-robots-noindex',
					'og_title'       => '_yoast_wpseo_opengraph-title',
					'og_description' => '_yoast_wpseo_opengraph-description',
					'og_image'       => '_yoast_wpseo_opengraph-image',
				),
				'extra_meta' => array( '_yoast_wpseo_focuskeywords', '_yoast_wpseo_primary_category', '_yoast_wpseo_twitter-title', '_yoast_wpseo_twitter-description' ),
			),
			'rankmath'     => array(
				'name'   => 'Rank Math',
				'const'  => 'RANK_MATH_VERSION',
				'plugin' => 'seo-by-rank-math/rank-math.php',
				'meta'   => array(
					'title'          => 'rank_math_title',
					'description'    => 'rank_math_description',
					'focus_keyword'  => 'rank_math_focus_keyword',
					'canonical'      => 'rank_math_canonical_url',
					'robots'         => 'rank_math_robots',
					'og_title'       => 'rank_math_facebook_title',
					'og_description' => 'rank_math_facebook_description',
				),
			),
			'aioseo'       => array(
				'name'   => 'All in One SEO',
				'const'  => 'AIOSEO_VERSION',
				'plugin' => 'all-in-one-seo-pack/all_in_one_seo_pack.php',
				'meta'   => array(
					'title'       => '_aioseo_title',
					'description' => '_aioseo_description',
				),
				'note'   => 'AIOSEO v4+ keeps most data in the wp_aioseo_posts table (not post meta).',
			),
			'seopress'     => array(
				'name'   => 'SEOPress',
				'const'  => 'SEOPRESS_VERSION',
				'plugin' => 'wp-seopress/seopress.php',
				'meta'   => array(
					'title'          => '_seopress_titles_title',
					'description'    => '_seopress_titles_desc',
					'focus_keyword'  => '_seopress_analysis_target_kw',
					'canonical'      => '_seopress_robots_canonical',
					'robots_noindex' => '_seopress_robots_index',
				),
			),
			'seoframework' => array(
				'name'   => 'The SEO Framework',
				'const'  => 'THE_SEO_FRAMEWORK_VERSION',
				'plugin' => 'autodescription/autodescription.php',
				'meta'   => array(
					'title'          => '_genesis_title',
					'description'    => '_genesis_description',
					'canonical'      => '_genesis_canonical_uri',
					'robots_noindex' => '_genesis_noindex',
				),
			),
		);
	}

	/**
	 * Which known SEO plugins are active on this site.
	 *
	 * @return array Subset of known(), keyed the same way.
	 */
	public static function active() {
		$active = array();
		foreach ( self::known() as $key => $info ) {
			$on = ( ! empty( $info['const'] ) && defined( $info['const'] ) );
			if ( ! $on && function_exists( 'is_plugin_active' ) && ! empty( $info['plugin'] ) ) {
				$on = is_plugin_active( $info['plugin'] );
			}
			if ( $on ) {
				$info['version']  = ( ! empty( $info['const'] ) && defined( $info['const'] ) ) ? constant( $info['const'] ) : '';
				$active[ $key ]   = $info;
			}
		}
		return $active;
	}

	/**
	 * All SEO meta keys to export (from active plugins, or all known if none).
	 *
	 * @return string[]
	 */
	public static function all_meta_keys() {
		$src = self::active();
		if ( empty( $src ) ) {
			$src = self::known();
		}
		$keys = array();
		foreach ( $src as $info ) {
			foreach ( (array) $info['meta'] as $meta_key ) {
				$keys[] = $meta_key;
			}
			foreach ( (array) ( isset( $info['extra_meta'] ) ? $info['extra_meta'] : array() ) as $meta_key ) {
				$keys[] = $meta_key;
			}
		}
		return array_values( array_unique( $keys ) );
	}

	/**
	 * Normalised SEO fields for a post, from the first active plugin that has data.
	 *
	 * @param int $post_id Post ID.
	 * @return array|null
	 */
	public static function get_post_seo( $post_id ) {
		foreach ( self::active() as $key => $info ) {
			$data = array( 'plugin' => $key );
			$has  = false;
			foreach ( (array) $info['meta'] as $field => $meta_key ) {
				$val = get_post_meta( $post_id, $meta_key, true );
				if ( '' !== $val && array() !== $val ) {
					$data[ $field ] = $val;
					$has            = true;
				}
			}
			if ( $has ) {
				return $data;
			}
		}
		return null;
	}

	/**
	 * A compact summary for the admin UI.
	 *
	 * @return array[] Each: key, name, version, note.
	 */
	public static function summary() {
		$out = array();
		foreach ( self::active() as $key => $info ) {
			$out[] = array(
				'key'     => $key,
				'name'    => $info['name'],
				'version' => isset( $info['version'] ) ? $info['version'] : '',
				'note'    => isset( $info['note'] ) ? $info['note'] : '',
			);
		}
		return $out;
	}
}
