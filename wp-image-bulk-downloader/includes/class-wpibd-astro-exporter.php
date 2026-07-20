<?php
/**
 * Astro migration exporter — packages WordPress content into an
 * Astro-friendly folder structure.
 *
 * Output layout inside the ZIP:
 *
 *   src/content/posts/<slug>.md
 *   src/content/pages/<slug>.md
 *   src/content/<custom-post-type>/<slug>.md
 *   src/data/site.json
 *   src/data/authors.json
 *   src/data/taxonomies.json
 *   src/data/menus.json
 *   src/data/comments.json
 *   src/data/redirects.json
 *   public/images/YYYY/MM/<file>       (images written by the ZIP builder)
 *   README.md
 *
 * @package WP_Image_Bulk_Downloader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPIBD_Astro_Exporter {

	private $uploads_baseurl;

	public function __construct() {
		$uploads               = wp_get_upload_dir();
		$this->uploads_baseurl = isset( $uploads['baseurl'] ) ? $uploads['baseurl'] : '';
	}

	public function write_all( ZipArchive $zip ) {
		$this->write_content( $zip );
		$this->write_data( $zip );
		$this->write_readme( $zip );
	}

	private function write_content( ZipArchive $zip ) {
		$post_types = $this->get_exportable_post_types();

		foreach ( $post_types as $post_type ) {
			$posts = get_posts(
				array(
					'post_type'        => $post_type,
					'post_status'      => array( 'publish', 'draft', 'private', 'pending', 'future' ),
					'numberposts'      => -1,
					'suppress_filters' => true,
					'orderby'          => 'date',
					'order'            => 'DESC',
				)
			);

			$used_slugs = array();
			foreach ( $posts as $post ) {
				$slug  = $this->unique_slug( $post, $used_slugs );
				$entry = 'src/content/' . $post_type . '/' . $slug . '.md';
				$body  = $this->build_post_markdown( $post );
				$zip->addFromString( $entry, $body );
			}
		}
	}

	private function write_data( ZipArchive $zip ) {
		$data_files = array(
			'src/data/site.json'       => $this->collect_site(),
			'src/data/authors.json'    => $this->collect_authors(),
			'src/data/taxonomies.json' => $this->collect_taxonomies(),
			'src/data/menus.json'      => $this->collect_menus(),
			'src/data/comments.json'   => $this->collect_comments(),
			'src/data/redirects.json'  => $this->collect_redirects(),
		);

		foreach ( $data_files as $entry => $data ) {
			$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
			if ( false !== $json ) {
				$zip->addFromString( $entry, $json );
			}
		}
	}

	private function write_readme( ZipArchive $zip ) {
		$site  = get_bloginfo( 'name' );
		$stamp = gmdate( 'Y-m-d H:i:s' ) . ' UTC';
		$body  = <<<MD
# Astro migration export — {$site}

Exported {$stamp} by the Image Bulk Downloader plugin.

## Layout

- `src/content/posts/<slug>.md` — blog posts (YAML frontmatter + HTML body)
- `src/content/pages/<slug>.md` — pages
- `src/content/<post-type>/<slug>.md` — custom post types
- `src/data/*.json` — site config, authors, taxonomies, menus, comments, redirects
- `public/images/YYYY/MM/…` — media library images, keyed the same way WordPress stored them
- `image-metadata.json` / `image-metadata.csv` — per-image title / alt / caption / description
- `site-info.json` / `site-info.txt` — verification codes, tracking IDs, SEO plugin settings

## Body format

Each post's body is the original WordPress HTML with image URLs rewritten from
`https://your-site.com/wp-content/uploads/…` to `/images/…` so they line up with
`public/images/` in your Astro project. Astro's `<Content />` renders HTML inside
markdown files as-is; convert to real markdown later if you prefer.

## Suggested Astro content collection

```ts
// src/content/config.ts
import { defineCollection, z } from 'astro:content';

const posts = defineCollection({
  type: 'content',
  schema: z.object({
    title: z.string(),
    slug: z.string(),
    date: z.coerce.date(),
    updated: z.coerce.date().optional(),
    excerpt: z.string().optional(),
    author: z.string().optional(),
    featured_image: z.string().optional(),
    categories: z.array(z.string()).default([]),
    tags: z.array(z.string()).default([]),
    seo: z.object({
      title: z.string().optional(),
      description: z.string().optional(),
      canonical: z.string().url().optional(),
      og_image: z.string().optional(),
      focus_keyword: z.string().optional(),
    }).partial().optional(),
    draft: z.boolean().default(false),
  }),
});

export const collections = { posts, pages: posts };
```

## Notes

- Elementor / page-builder posts store their layout as shortcodes / JSON in
  `post_content`. Those are exported verbatim and will need to be rebuilt in
  Astro.
- Redirects come from Rank Math's `rank_math_redirections` table when the
  Redirections module is enabled.
MD;

		$zip->addFromString( 'README.md', $body );
	}

	private function build_post_markdown( WP_Post $post ) {
		$categories  = wp_get_post_terms( $post->ID, 'category', array( 'fields' => 'names' ) );
		$tags        = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'names' ) );
		$author      = get_userdata( $post->post_author );
		$author_name = $author ? $author->display_name : '';
		$featured    = get_post_thumbnail_id( $post->ID );
		$featured_rel = $featured ? $this->attachment_relative_url( $featured ) : '';

		$frontmatter = array(
			'title'          => $post->post_title,
			'slug'           => $post->post_name,
			'date'           => mysql2date( 'c', $post->post_date_gmt ?: $post->post_date ),
			'updated'        => mysql2date( 'c', $post->post_modified_gmt ?: $post->post_modified ),
			'status'         => $post->post_status,
			'author'         => $author_name,
			'author_id'      => (int) $post->post_author,
			'excerpt'        => $post->post_excerpt,
			'featured_image' => $featured_rel,
			'categories'     => is_array( $categories ) ? array_values( $categories ) : array(),
			'tags'           => is_array( $tags ) ? array_values( $tags ) : array(),
			'seo'            => $this->collect_post_seo( $post ),
			'wp' => array(
				'id'          => $post->ID,
				'guid'        => $post->guid,
				'permalink'   => get_permalink( $post ),
				'menu_order'  => (int) $post->menu_order,
				'parent'      => (int) $post->post_parent,
				'comment_status' => $post->comment_status,
				'template'    => get_page_template_slug( $post->ID ),
				'is_elementor' => (bool) get_post_meta( $post->ID, '_elementor_edit_mode', true ),
			),
			'draft'          => 'publish' !== $post->post_status,
		);

		$body = $this->rewrite_image_urls( (string) $post->post_content );

		return $this->encode_yaml_frontmatter( $frontmatter ) . "\n" . $body . "\n";
	}

	private function collect_post_seo( WP_Post $post ) {
		$seo = array();

		$rank_math_map = array(
			'title'         => 'rank_math_title',
			'description'   => 'rank_math_description',
			'canonical'     => 'rank_math_canonical_url',
			'focus_keyword' => 'rank_math_focus_keyword',
			'og_title'      => 'rank_math_facebook_title',
			'og_description'=> 'rank_math_facebook_description',
			'og_image'      => 'rank_math_facebook_image',
			'twitter_title' => 'rank_math_twitter_title',
			'twitter_image' => 'rank_math_twitter_image',
			'robots'        => 'rank_math_robots',
			'schema_type'   => 'rank_math_rich_snippet',
		);
		foreach ( $rank_math_map as $key => $meta_key ) {
			$val = get_post_meta( $post->ID, $meta_key, true );
			if ( '' !== $val && null !== $val ) {
				$seo[ $key ] = is_array( $val ) ? $val : (string) $val;
			}
		}

		if ( empty( $seo ) ) {
			$yoast_map = array(
				'title'          => '_yoast_wpseo_title',
				'description'    => '_yoast_wpseo_metadesc',
				'canonical'      => '_yoast_wpseo_canonical',
				'focus_keyword'  => '_yoast_wpseo_focuskw',
				'og_title'       => '_yoast_wpseo_opengraph-title',
				'og_description' => '_yoast_wpseo_opengraph-description',
				'og_image'       => '_yoast_wpseo_opengraph-image',
				'twitter_title'  => '_yoast_wpseo_twitter-title',
				'twitter_image'  => '_yoast_wpseo_twitter-image',
			);
			foreach ( $yoast_map as $key => $meta_key ) {
				$val = get_post_meta( $post->ID, $meta_key, true );
				if ( '' !== $val && null !== $val ) {
					$seo[ $key ] = (string) $val;
				}
			}
		}

		return (object) $seo;
	}

	private function collect_site() {
		return array(
			'name'         => get_bloginfo( 'name' ),
			'description'  => get_bloginfo( 'description' ),
			'home_url'     => home_url(),
			'site_url'     => site_url(),
			'admin_email'  => get_option( 'admin_email' ),
			'language'     => get_locale(),
			'timezone'     => function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : get_option( 'timezone_string' ),
			'permalink'    => get_option( 'permalink_structure' ),
			'front_page_on_front' => 'page' === get_option( 'show_on_front' ),
			'front_page_id'       => (int) get_option( 'page_on_front' ),
			'posts_page_id'       => (int) get_option( 'page_for_posts' ),
			'posts_per_page'      => (int) get_option( 'posts_per_page' ),
			'date_format'         => get_option( 'date_format' ),
			'time_format'         => get_option( 'time_format' ),
		);
	}

	private function collect_authors() {
		$user_ids = get_users(
			array(
				'fields'    => 'ID',
				'has_published_posts' => true,
			)
		);
		$out = array();
		foreach ( $user_ids as $id ) {
			$u = get_userdata( $id );
			if ( ! $u ) {
				continue;
			}
			$out[] = array(
				'id'           => (int) $u->ID,
				'login'        => $u->user_login,
				'display_name' => $u->display_name,
				'first_name'   => (string) get_user_meta( $u->ID, 'first_name', true ),
				'last_name'    => (string) get_user_meta( $u->ID, 'last_name', true ),
				'description'  => (string) get_user_meta( $u->ID, 'description', true ),
				'url'          => $u->user_url,
				'roles'        => (array) $u->roles,
			);
		}
		return $out;
	}

	private function collect_taxonomies() {
		$out = array();
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		foreach ( $taxonomies as $tax ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $tax->name,
					'hide_empty' => false,
				)
			);
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			$out[ $tax->name ] = array(
				'label' => $tax->label,
				'terms' => array_map(
					function ( $t ) {
						return array(
							'id'          => (int) $t->term_id,
							'name'        => $t->name,
							'slug'        => $t->slug,
							'parent'      => (int) $t->parent,
							'description' => (string) $t->description,
							'count'       => (int) $t->count,
						);
					},
					$terms
				),
			);
		}
		return $out;
	}

	private function collect_menus() {
		$out    = array();
		$menus  = wp_get_nav_menus();
		if ( ! is_array( $menus ) ) {
			return $out;
		}
		foreach ( $menus as $menu ) {
			$items = wp_get_nav_menu_items( $menu->term_id, array( 'update_post_term_cache' => false ) );
			if ( ! is_array( $items ) ) {
				$items = array();
			}
			$out[ $menu->slug ] = array(
				'id'    => (int) $menu->term_id,
				'name'  => $menu->name,
				'items' => array_map(
					function ( $it ) {
						return array(
							'id'         => (int) $it->ID,
							'title'      => $it->title,
							'url'        => $it->url,
							'parent'     => (int) $it->menu_item_parent,
							'order'      => (int) $it->menu_order,
							'type'       => $it->type,
							'object'     => $it->object,
							'object_id'  => (int) $it->object_id,
							'target'     => $it->target,
							'classes'    => is_array( $it->classes ) ? array_values( array_filter( $it->classes ) ) : array(),
							'xfn'        => $it->xfn,
							'description'=> $it->description,
						);
					},
					$items
				),
			);
		}
		return $out;
	}

	private function collect_comments() {
		$out      = array();
		$comments = get_comments(
			array(
				'status' => 'approve',
				'type'   => 'comment',
				'number' => 5000,
			)
		);
		foreach ( $comments as $c ) {
			$post_id = (int) $c->comment_post_ID;
			if ( ! isset( $out[ $post_id ] ) ) {
				$out[ $post_id ] = array();
			}
			$out[ $post_id ][] = array(
				'id'      => (int) $c->comment_ID,
				'parent'  => (int) $c->comment_parent,
				'author'  => $c->comment_author,
				'email'   => $c->comment_author_email,
				'url'     => $c->comment_author_url,
				'date'    => mysql2date( 'c', $c->comment_date_gmt ?: $c->comment_date ),
				'content' => $c->comment_content,
			);
		}
		return $out;
	}

	private function collect_redirects() {
		global $wpdb;
		$table = $wpdb->prefix . 'rank_math_redirections';
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $exists ) {
			return array();
		}
		$rows = $wpdb->get_results( "SELECT sources, url_to, header_code, status FROM {$table}", ARRAY_A );
		$out  = array();
		foreach ( (array) $rows as $r ) {
			$sources = maybe_unserialize( $r['sources'] );
			if ( ! is_array( $sources ) ) {
				continue;
			}
			foreach ( $sources as $src ) {
				if ( empty( $src['pattern'] ) ) {
					continue;
				}
				$out[] = array(
					'from'        => $src['pattern'],
					'to'          => $r['url_to'],
					'code'        => (int) $r['header_code'],
					'status'      => $r['status'],
					'match_type'  => isset( $src['comparison'] ) ? $src['comparison'] : 'exact',
				);
			}
		}
		return $out;
	}

	public function get_exportable_post_types() {
		$types = get_post_types( array( 'public' => true ), 'names' );
		unset( $types['attachment'] );
		return array_values( $types );
	}

	public function rewrite_image_urls( $html ) {
		if ( ! $this->uploads_baseurl || ! $html ) {
			return $html;
		}
		$escaped = $this->uploads_baseurl;
		$html    = str_replace( $escaped, '/images', $html );
		$http    = preg_replace( '#^https://#i', 'http://', $escaped );
		if ( $http && $http !== $escaped ) {
			$html = str_replace( $http, '/images', $html );
		}
		return $html;
	}

	private function attachment_relative_url( $attachment_id ) {
		$url = wp_get_attachment_url( $attachment_id );
		if ( ! $url || ! $this->uploads_baseurl ) {
			return $url;
		}
		if ( 0 === strpos( $url, $this->uploads_baseurl ) ) {
			return '/images' . substr( $url, strlen( $this->uploads_baseurl ) );
		}
		return $url;
	}

	private function unique_slug( WP_Post $post, array &$used ) {
		$base = $post->post_name;
		if ( '' === $base ) {
			$base = sanitize_title( $post->post_title );
		}
		if ( '' === $base ) {
			$base = 'post-' . $post->ID;
		}
		$slug = $base;
		$i    = 2;
		while ( isset( $used[ $slug ] ) ) {
			$slug = $base . '-' . $i;
			++$i;
		}
		$used[ $slug ] = true;
		return $slug;
	}

	private function encode_yaml_frontmatter( array $data ) {
		$lines = array( '---' );
		foreach ( $data as $key => $value ) {
			$lines[] = $this->yaml_line( $key, $value, 0 );
		}
		$lines[] = '---';
		return implode( "\n", $lines );
	}

	private function yaml_line( $key, $value, $indent ) {
		$pad = str_repeat( '  ', $indent );

		if ( is_array( $value ) ) {
			if ( empty( $value ) ) {
				return $pad . $key . ': []';
			}
			if ( $this->is_list( $value ) ) {
				if ( $this->all_scalar( $value ) ) {
					$parts = array_map( array( $this, 'yaml_scalar' ), $value );
					return $pad . $key . ': [' . implode( ', ', $parts ) . ']';
				}
				$out = $pad . $key . ':';
				foreach ( $value as $item ) {
					if ( is_array( $item ) ) {
						$out .= "\n" . $pad . '  -';
						foreach ( $item as $k2 => $v2 ) {
							$out .= "\n" . $this->yaml_line( $k2, $v2, $indent + 2 );
						}
					} else {
						$out .= "\n" . $pad . '  - ' . $this->yaml_scalar( $item );
					}
				}
				return $out;
			}
			$out = $pad . $key . ':';
			foreach ( $value as $k2 => $v2 ) {
				$out .= "\n" . $this->yaml_line( $k2, $v2, $indent + 1 );
			}
			return $out;
		}

		if ( is_object( $value ) ) {
			return $this->yaml_line( $key, (array) $value, $indent );
		}

		return $pad . $key . ': ' . $this->yaml_scalar( $value );
	}

	private function yaml_scalar( $value ) {
		if ( null === $value || '' === $value ) {
			return '""';
		}
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( is_int( $value ) || is_float( $value ) ) {
			return (string) $value;
		}
		$s = (string) $value;
		$s = str_replace( array( '\\', '"' ), array( '\\\\', '\\"' ), $s );
		$s = str_replace( array( "\r\n", "\r", "\n" ), array( '\n', '\n', '\n' ), $s );
		return '"' . $s . '"';
	}

	private function is_list( array $arr ) {
		if ( empty( $arr ) ) {
			return true;
		}
		return array_keys( $arr ) === range( 0, count( $arr ) - 1 );
	}

	private function all_scalar( array $arr ) {
		foreach ( $arr as $v ) {
			if ( ! is_scalar( $v ) && null !== $v ) {
				return false;
			}
		}
		return true;
	}
}
