<?php
/**
 * Migration exporter.
 *
 * Builds a single structured manifest of the whole site — every post type and
 * custom post type, all taxonomies/terms, the full media library with its
 * metadata (alt, caption, description, sizes), users, comments, general
 * settings and SEO metadata — ready to import into Python or another platform.
 *
 * Personal data (user logins/emails) is only included when the caller opts in;
 * the admin screen additionally requires an explicit authorisation checkbox.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_Exporter
 */
class SPR_Exporter {

	/**
	 * Default export options.
	 *
	 * @return array
	 */
	public static function default_options() {
		return array(
			'post_types'         => array(),   // Empty = all (non-attachment) public + private types.
			'include_media'      => true,      // Media library metadata.
			'include_taxonomies' => true,
			'include_settings'   => true,
			'include_seo'        => true,
			'include_users'      => false,     // PII.
			'include_emails'     => false,     // PII (requires include_users).
			'include_comments'   => false,
			'include_all_options' => false,    // Advanced/risky (may contain secrets).
		);
	}

	/**
	 * The security warning embedded in every export.
	 *
	 * @return string
	 */
	public static function security_notice() {
		return 'This export may contain sensitive and personal data (e.g. user names, email addresses, settings). Keep it in a safe place. Do NOT upload it to an unsecured or public server, and never commit it to a public repository such as GitHub.';
	}

	/**
	 * Build the full export manifest.
	 *
	 * @param array $opts Options (see default_options()).
	 * @return array
	 */
	public function build_manifest( $opts = array() ) {
		$opts = wp_parse_args( $opts, self::default_options() );

		$manifest = array(
			'_notice'      => self::security_notice(),
			'generator'    => 'SEO Sprinkler',
			'version'      => defined( 'SPR_VERSION' ) ? SPR_VERSION : '',
			'generated_at' => gmdate( 'c' ),
			'site'         => $this->site_section( $opts ),
			'seo'          => $opts['include_seo'] ? $this->seo_section() : null,
			'post_types'   => $this->post_types_section( $opts ),
			'taxonomies'   => $opts['include_taxonomies'] ? $this->taxonomies_section() : null,
			'posts'        => $this->posts_section( $opts ),
			'media'        => $opts['include_media'] ? $this->media_section() : null,
			'users'        => $opts['include_users'] ? $this->users_section( $opts ) : null,
			'comments'     => $opts['include_comments'] ? $this->comments_section() : null,
		);

		if ( $opts['include_settings'] ) {
			$manifest['settings'] = $this->settings_section( $opts );
		}

		// Drop null sections for a cleaner document.
		return array_filter( $manifest, static function ( $v ) {
			return null !== $v;
		} );
	}

	/* ---------------------------------------------------------------------
	 * Sections
	 * ------------------------------------------------------------------- */

	/**
	 * Site identity + general settings the user expects ("from the general").
	 *
	 * @param array $opts Options.
	 * @return array
	 */
	protected function site_section( $opts ) {
		$site = array(
			'name'                => get_bloginfo( 'name' ),
			'description'         => get_bloginfo( 'description' ),
			'url'                 => site_url(),
			'home'                => home_url(),
			'language'            => get_bloginfo( 'language' ),
			'charset'             => get_bloginfo( 'charset' ),
			'timezone'            => wp_timezone_string(),
			'permalink_structure' => get_option( 'permalink_structure' ),
			'active_theme'        => get_option( 'stylesheet' ),
		);
		if ( ! empty( $opts['include_emails'] ) ) {
			$site['admin_email'] = get_option( 'admin_email' );
		}
		return $site;
	}

	/**
	 * Curated general settings (plus all options when explicitly requested).
	 *
	 * @param array $opts Options.
	 * @return array
	 */
	protected function settings_section( $opts ) {
		$keys = array(
			'blogname', 'blogdescription', 'siteurl', 'home', 'start_of_week',
			'timezone_string', 'date_format', 'time_format', 'posts_per_page',
			'permalink_structure', 'template', 'stylesheet', 'WPLANG',
			'show_on_front', 'page_on_front', 'page_for_posts', 'default_category',
		);
		$settings = array();
		foreach ( $keys as $k ) {
			$settings[ $k ] = get_option( $k );
		}
		$settings['active_plugins'] = (array) get_option( 'active_plugins', array() );

		if ( ! empty( $opts['include_all_options'] ) ) {
			// Advanced: dump all autoloaded options. May contain sensitive data —
			// the admin opted in explicitly.
			$all = wp_load_alloptions();
			$settings['all_options'] = is_array( $all ) ? $all : array();
		}

		return $settings;
	}

	/**
	 * SEO plugins detected + the meta keys carried in the export.
	 *
	 * @return array
	 */
	protected function seo_section() {
		return array(
			'active_plugins' => SPR_SEO_Detector::summary(),
			'meta_keys'      => SPR_SEO_Detector::all_meta_keys(),
		);
	}

	/**
	 * Registered post types (so the importer knows the shape of the data).
	 *
	 * @param array $opts Options.
	 * @return array[]
	 */
	protected function post_types_section( $opts ) {
		$out = array();
		foreach ( $this->target_post_types( $opts ) as $name ) {
			$obj = get_post_type_object( $name );
			if ( ! $obj ) {
				continue;
			}
			$out[] = array(
				'name'         => $name,
				'label'        => $obj->labels->name,
				'public'       => (bool) $obj->public,
				'hierarchical' => (bool) $obj->hierarchical,
				'taxonomies'   => get_object_taxonomies( $name ),
			);
		}
		return $out;
	}

	/**
	 * All terms in every public taxonomy.
	 *
	 * @return array<string,array>
	 */
	protected function taxonomies_section() {
		$out = array();
		foreach ( get_taxonomies( array(), 'names' ) as $tax ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $tax,
					'hide_empty' => false,
				)
			);
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			$out[ $tax ] = array();
			foreach ( $terms as $term ) {
				$out[ $tax ][] = array(
					'id'          => $term->term_id,
					'name'        => $term->name,
					'slug'        => $term->slug,
					'description' => $term->description,
					'parent'      => $term->parent,
					'count'       => $term->count,
					'meta'        => $this->flatten_meta( get_term_meta( $term->term_id ) ),
				);
			}
		}
		return $out;
	}

	/**
	 * Every post of every targeted type, with meta, terms, featured image, SEO.
	 *
	 * @param array $opts Options.
	 * @return array[]
	 */
	protected function posts_section( $opts ) {
		$out = array();
		$q   = new WP_Query(
			array(
				'post_type'           => $this->target_post_types( $opts ),
				'post_status'         => 'any',
				'posts_per_page'      => -1,
				'orderby'             => 'ID',
				'order'               => 'ASC',
				'ignore_sticky_posts' => true,
				'no_found_rows'       => true,
				'suppress_filters'    => true,
			)
		);
		foreach ( $q->posts as $post ) {
			$out[] = $this->build_post_record( $post, $opts );
		}
		return $out;
	}

	/**
	 * Build one post record.
	 *
	 * @param WP_Post $post Post.
	 * @param array   $opts Options.
	 * @return array
	 */
	protected function build_post_record( $post, $opts ) {
		$terms = array();
		foreach ( get_object_taxonomies( $post->post_type ) as $tax ) {
			$t = wp_get_object_terms( $post->ID, $tax, array( 'fields' => 'slugs' ) );
			if ( ! is_wp_error( $t ) && ! empty( $t ) ) {
				$terms[ $tax ] = $t;
			}
		}

		$thumb_id = get_post_thumbnail_id( $post->ID );

		$record = array(
			'id'         => $post->ID,
			'type'       => $post->post_type,
			'status'     => $post->post_status,
			'title'      => get_the_title( $post ),
			'slug'       => $post->post_name,
			'date'       => $post->post_date_gmt,
			'modified'   => $post->post_modified_gmt,
			'author'     => (int) $post->post_author,
			'parent'     => (int) $post->post_parent,
			'menu_order' => (int) $post->menu_order,
			'permalink'  => get_permalink( $post ),
			'excerpt'    => $post->post_excerpt,
			'content'    => $post->post_content,
			'terms'      => $terms,
			'meta'       => $this->flatten_meta( get_post_meta( $post->ID ) ),
			'featured_image' => $thumb_id ? array(
				'id'  => (int) $thumb_id,
				'url' => wp_get_attachment_url( $thumb_id ),
				'alt' => get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ),
			) : null,
		);

		if ( ! empty( $opts['include_seo'] ) ) {
			$seo = SPR_SEO_Detector::get_post_seo( $post->ID );
			if ( $seo ) {
				$record['seo'] = $seo;
			}
		}

		return $record;
	}

	/**
	 * The full media library with metadata (alt, caption, description, sizes).
	 *
	 * @return array[]
	 */
	protected function media_section() {
		$out = array();
		$q   = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);
		foreach ( $q->posts as $att ) {
			$meta = wp_get_attachment_metadata( $att->ID );
			$file = get_attached_file( $att->ID );
			$out[] = array(
				'id'          => $att->ID,
				'title'       => get_the_title( $att ),
				'filename'    => $file ? basename( $file ) : '',
				'url'         => wp_get_attachment_url( $att->ID ),
				'mime'        => $att->post_mime_type,
				'date'        => $att->post_date_gmt,
				'alt'         => get_post_meta( $att->ID, '_wp_attachment_image_alt', true ),
				'caption'     => $att->post_excerpt,
				'description' => $att->post_content,
				'width'       => isset( $meta['width'] ) ? $meta['width'] : null,
				'height'      => isset( $meta['height'] ) ? $meta['height'] : null,
				'sizes'       => isset( $meta['sizes'] ) ? $meta['sizes'] : array(),
				'filesize'    => ( $file && file_exists( $file ) ) ? filesize( $file ) : null,
			);
		}
		return $out;
	}

	/**
	 * Users — names always; logins/emails only when emails are opted in.
	 *
	 * @param array $opts Options.
	 * @return array[]
	 */
	protected function users_section( $opts ) {
		$out   = array();
		$users = get_users( array( 'fields' => 'all' ) );
		foreach ( $users as $user ) {
			$record = array(
				'id'           => $user->ID,
				'display_name' => $user->display_name,
				'roles'        => $user->roles,
				'registered'   => $user->user_registered,
				'url'          => $user->user_url,
				'description'  => get_user_meta( $user->ID, 'description', true ),
			);
			if ( ! empty( $opts['include_emails'] ) ) {
				$record['login'] = $user->user_login;
				$record['email'] = $user->user_email;
			}
			$out[] = $record;
		}
		return $out;
	}

	/**
	 * Comments.
	 *
	 * @return array[]
	 */
	protected function comments_section() {
		$out = array();
		foreach ( get_comments( array( 'status' => 'all' ) ) as $c ) {
			$out[] = array(
				'id'         => (int) $c->comment_ID,
				'post'       => (int) $c->comment_post_ID,
				'author'     => $c->comment_author,
				'date'       => $c->comment_date_gmt,
				'content'    => $c->comment_content,
				'approved'   => $c->comment_approved,
				'parent'     => (int) $c->comment_parent,
				'user_id'    => (int) $c->user_id,
			);
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Media files (for the ZIP export)
	 * ------------------------------------------------------------------- */

	/**
	 * Absolute paths of all media files, keyed by attachment ID.
	 *
	 * @return array<int,string>
	 */
	public function media_files() {
		$files = array();
		$q     = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		foreach ( $q->posts as $id ) {
			$file = get_attached_file( $id );
			if ( $file && file_exists( $file ) ) {
				$files[ (int) $id ] = $file;
			}
		}
		return $files;
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------- */

	/**
	 * Which post types to export.
	 *
	 * @param array $opts Options.
	 * @return string[]
	 */
	protected function target_post_types( $opts ) {
		if ( ! empty( $opts['post_types'] ) ) {
			return array_values( array_filter( array_map( 'sanitize_key', (array) $opts['post_types'] ), 'post_type_exists' ) );
		}
		$types = get_post_types( array(), 'names' );
		unset( $types['attachment'], $types['revision'], $types['nav_menu_item'], $types['custom_css'], $types['customize_changeset'], $types['oembed_cache'], $types['user_request'], $types['wp_block'], $types['wp_template'], $types['wp_template_part'], $types['wp_global_styles'], $types['wp_navigation'] );
		return array_values( $types );
	}

	/**
	 * Flatten get_*_meta() output (each key is an array of values) to scalars
	 * where there is a single value, keeping arrays where there are many.
	 *
	 * @param array $meta Raw meta map.
	 * @return array
	 */
	protected function flatten_meta( $meta ) {
		$out = array();
		foreach ( (array) $meta as $key => $values ) {
			// Skip transient editorial locks.
			if ( '_edit_lock' === $key || '_edit_last' === $key ) {
				continue;
			}
			$decoded = array();
			foreach ( (array) $values as $v ) {
				$maybe     = maybe_unserialize( $v );
				$decoded[] = $maybe;
			}
			$out[ $key ] = ( 1 === count( $decoded ) ) ? $decoded[0] : $decoded;
		}
		return $out;
	}
}
