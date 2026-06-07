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
			'render_content'     => true,      // Render Elementor / page-builder / block output.
			'clean_html'         => true,      // Also include a scrubbed, semantic-HTML mirror.
			'extract_media'      => true,      // Per-post content images + link connections.
			'include_menus'      => true,      // Navigation menus (connections).
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
			'menus'        => ! empty( $opts['include_menus'] ) ? $this->menus_section() : null,
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

		// Render the *real* content (Elementor / page builders / blocks), then a
		// clean semantic-HTML mirror and the content's images + link connections.
		if ( ! empty( $opts['render_content'] ) && 'attachment' !== $post->post_type ) {
			$rendered = $this->render_post_content( $post );
			$record['content_rendered'] = $rendered;
			$record['builder']          = $this->detect_builder( $post );
			if ( ! empty( $opts['clean_html'] ) ) {
				$record['content_clean'] = $this->clean_export_html( $rendered );
			}
			if ( ! empty( $opts['extract_media'] ) ) {
				$record['content_images'] = $this->extract_images( $rendered );
				$record['links']          = $this->extract_links( $rendered );
			}
		}

		return $record;
	}

	/* ---------------------------------------------------------------------
	 * Rendered content, clean mirror, and content connections
	 * ------------------------------------------------------------------- */

	/**
	 * Which builder a post uses (so the importer knows the source).
	 *
	 * @param WP_Post $post Post.
	 * @return string elementor|blocks|classic.
	 */
	protected function detect_builder( $post ) {
		if ( 'builder' === get_post_meta( $post->ID, '_elementor_edit_mode', true ) ) {
			return 'elementor';
		}
		if ( function_exists( 'has_blocks' ) && has_blocks( $post->post_content ) ) {
			return 'blocks';
		}
		return 'classic';
	}

	/**
	 * Render a post's true front-end content.
	 *
	 * Elementor stores its layout in meta (not post_content), so we ask Elementor
	 * to render it. Everything else (Gutenberg blocks, shortcodes, other builders
	 * that hook the_content) is realised by running the the_content filter.
	 *
	 * @param WP_Post $post Post.
	 * @return string
	 */
	protected function render_post_content( $post ) {
		// Elementor builder pages.
		if ( did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' ) && 'builder' === get_post_meta( $post->ID, '_elementor_edit_mode', true ) ) {
			try {
				$html = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $post->ID, false );
				if ( is_string( $html ) && '' !== trim( $html ) ) {
					return $html;
				}
			} catch ( \Throwable $e ) {
				// Fall through to the_content.
			}
		}

		// Blocks / shortcodes / other builders.
		$prev_post       = isset( $GLOBALS['post'] ) ? $GLOBALS['post'] : null;
		$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		setup_postdata( $post );
		try {
			$html = apply_filters( 'the_content', $post->post_content );
		} catch ( \Throwable $e ) {
			$html = $post->post_content;
		}
		wp_reset_postdata();
		$GLOBALS['post'] = $prev_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		return is_string( $html ) ? $html : '';
	}

	/**
	 * Scrub rendered HTML down to a clean, portable, semantic mirror: no scripts,
	 * styles, comments, or builder/WordPress classes, styles and data-attributes —
	 * just the content (headings, text, lists, tables, images, links, embeds).
	 *
	 * @param string $html Rendered HTML.
	 * @return string
	 */
	public function clean_export_html( $html ) {
		$html = (string) $html;
		if ( '' === trim( $html ) ) {
			return '';
		}
		if ( ! class_exists( 'DOMDocument' ) ) {
			return $this->clean_export_html_regex( $html );
		}

		// Attributes worth keeping, per tag — everything else is dropped.
		$keep = array(
			'a'      => array( 'href', 'title', 'target', 'rel' ),
			'img'    => array( 'src', 'alt', 'title', 'width', 'height' ),
			'iframe' => array( 'src', 'title', 'width', 'height', 'allow', 'allowfullscreen' ),
			'source' => array( 'src', 'srcset', 'type', 'media' ),
			'video'  => array( 'src', 'controls', 'width', 'height', 'poster' ),
			'audio'  => array( 'src', 'controls' ),
			'th'     => array( 'colspan', 'rowspan', 'scope' ),
			'td'     => array( 'colspan', 'rowspan' ),
			'ol'     => array( 'start', 'type' ),
			'time'   => array( 'datetime' ),
		);

		$prev = libxml_use_internal_errors( true );
		$doc  = new DOMDocument( '1.0', 'UTF-8' );
		$doc->loadHTML(
			'<?xml encoding="UTF-8"><div id="spr-root">' . $html . '</div>',
			LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
		);
		libxml_clear_errors();
		libxml_use_internal_errors( $prev );

		$xp = new DOMXPath( $doc );

		// Drop comments outright.
		foreach ( iterator_to_array( $xp->query( '//comment()' ) ) as $comment ) {
			if ( $comment->parentNode ) {
				$comment->parentNode->removeChild( $comment );
			}
		}
		// Drop non-content elements outright.
		foreach ( array( 'script', 'style', 'link', 'noscript', 'svg', 'button', 'input', 'form', 'select' ) as $tag ) {
			foreach ( iterator_to_array( $doc->getElementsByTagName( $tag ) ) as $node ) {
				if ( $node->parentNode ) {
					$node->parentNode->removeChild( $node );
				}
			}
		}
		// Strip every attribute that isn't whitelisted for its tag.
		foreach ( iterator_to_array( $xp->query( '//*' ) ) as $el ) {
			if ( ! $el->hasAttributes() ) {
				continue;
			}
			$allowed = isset( $keep[ strtolower( $el->nodeName ) ] ) ? $keep[ strtolower( $el->nodeName ) ] : array();
			$names   = array();
			foreach ( $el->attributes as $attr ) {
				$names[] = $attr->nodeName;
			}
			foreach ( $names as $name ) {
				if ( ! in_array( strtolower( $name ), $allowed, true ) ) {
					$el->removeAttribute( $name );
				}
			}
		}

		$root = $xp->query( '//*[@id="spr-root"]' )->item( 0 );
		if ( ! $root ) {
			return $this->clean_export_html_regex( $html );
		}
		$out = '';
		foreach ( $root->childNodes as $child ) {
			$out .= $doc->saveHTML( $child );
		}
		return trim( preg_replace( '/\n{3,}/', "\n\n", $out ) );
	}

	/**
	 * Regex fallback for clean_export_html() when DOMDocument is unavailable.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	protected function clean_export_html_regex( $html ) {
		$html = preg_replace( '#<(script|style|noscript|svg)\b[\s\S]*?</\1>#i', '', $html );
		$html = preg_replace( '/<!--[\s\S]*?-->/', '', $html );
		$html = preg_replace( '/\s(?:class|style|id|role|data-[\w-]+|on\w+|srcset|sizes|loading|decoding)\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $html );
		return trim( preg_replace( '/\n{3,}/', "\n\n", $html ) );
	}

	/**
	 * Pull one attribute value out of a tag string.
	 *
	 * @param string $tag  Tag markup.
	 * @param string $name Attribute.
	 * @return string
	 */
	protected function tag_attr( $tag, $name ) {
		if ( preg_match( '/\b' . preg_quote( $name, '/' ) . '\s*=\s*("|\')(.*?)\1/i', $tag, $m ) ) {
			return trim( html_entity_decode( $m[2], ENT_QUOTES ) );
		}
		return '';
	}

	/**
	 * Every image inside the content, with alt/caption/title and the resolved
	 * attachment id where the URL maps to one in this library.
	 *
	 * @param string $html Rendered HTML.
	 * @return array[]
	 */
	protected function extract_images( $html ) {
		$out = array();
		if ( '' === trim( (string) $html ) || ! preg_match_all( '/<img\b[^>]*>/i', $html, $imgs ) ) {
			return $out;
		}
		foreach ( $imgs[0] as $tag ) {
			$src = $this->tag_attr( $tag, 'src' );
			if ( '' === $src ) {
				continue;
			}
			$item = array(
				'src'    => $src,
				'alt'    => $this->tag_attr( $tag, 'alt' ),
				'title'  => $this->tag_attr( $tag, 'title' ),
				'width'  => $this->tag_attr( $tag, 'width' ),
				'height' => $this->tag_attr( $tag, 'height' ),
			);
			$aid = attachment_url_to_postid( preg_replace( '/-\d+x\d+(?=\.[A-Za-z0-9]+$)/', '', $src ) );
			if ( ! $aid ) {
				$aid = attachment_url_to_postid( $src );
			}
			if ( $aid ) {
				$item['attachment_id'] = (int) $aid;
				$item['caption']       = get_post_field( 'post_excerpt', $aid );
				$item['description']   = get_post_field( 'post_content', $aid );
				$stored_alt            = (string) get_post_meta( $aid, '_wp_attachment_image_alt', true );
				if ( '' === $item['alt'] && '' !== $stored_alt ) {
					$item['alt'] = $stored_alt;
				}
			}
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * Internal/external links in the content (the connection graph). Internal
	 * links resolve to a target post id where possible.
	 *
	 * @param string $html Rendered HTML.
	 * @return array{internal:array,external:array}
	 */
	protected function extract_links( $html ) {
		$internal = array();
		$external = array();
		if ( '' !== trim( (string) $html ) && preg_match_all( '/<a\b[^>]*href\s*=\s*("|\')(.*?)\1[^>]*>(.*?)<\/a>/is', $html, $links, PREG_SET_ORDER ) ) {
			foreach ( $links as $a ) {
				$url = trim( html_entity_decode( $a[2], ENT_QUOTES ) );
				if ( '' === $url || 0 === strpos( $url, '#' ) || 0 === stripos( $url, 'javascript:' ) || 0 === stripos( $url, 'mailto:' ) || 0 === stripos( $url, 'tel:' ) ) {
					continue;
				}
				$text = trim( wp_strip_all_tags( $a[3] ) );
				if ( $this->is_internal_url( $url ) ) {
					$tid        = url_to_postid( $url );
					$internal[] = array(
						'url'       => $url,
						'text'      => $text,
						'target_id' => $tid ? (int) $tid : null,
					);
				} else {
					$external[] = array(
						'url'  => $url,
						'text' => $text,
					);
				}
			}
		}
		return array(
			'internal' => $internal,
			'external' => $external,
		);
	}

	/**
	 * Is a URL internal to this site (same host, or relative)?
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	protected function is_internal_url( $url ) {
		$home = wp_parse_url( home_url() );
		$host = isset( $home['host'] ) ? strtolower( $home['host'] ) : '';
		$u    = wp_parse_url( $url );
		if ( empty( $u['host'] ) ) {
			return true; // Relative URL.
		}
		return strtolower( $u['host'] ) === $host;
	}

	/**
	 * Navigation menus + their items (site connections/structure).
	 *
	 * @return array[]
	 */
	protected function menus_section() {
		$out       = array();
		$locations = array_flip( (array) get_nav_menu_locations() );
		foreach ( wp_get_nav_menus() as $menu ) {
			$items = wp_get_nav_menu_items( $menu->term_id );
			$rows  = array();
			foreach ( (array) $items as $it ) {
				$rows[] = array(
					'id'        => (int) $it->ID,
					'title'     => $it->title,
					'url'       => $it->url,
					'parent'    => (int) $it->menu_item_parent,
					'order'     => (int) $it->menu_order,
					'type'      => $it->type,
					'object'    => $it->object,
					'object_id' => (int) $it->object_id,
				);
			}
			$out[] = array(
				'name'     => $menu->name,
				'slug'     => $menu->slug,
				'location' => isset( $locations[ $menu->term_id ] ) ? $locations[ $menu->term_id ] : null,
				'items'    => $rows,
			);
		}
		return $out;
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
