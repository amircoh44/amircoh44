<?php
/**
 * Schema (JSON-LD) generator.
 *
 * Outputs a single connected @graph in <head> describing the business and the
 * current page, so every URL — home, pages, services, posts, archives/tags —
 * gets accurate structured data. Built from the Business Profile.
 *
 * Because the user opted to make this plugin the single source of schema, it
 * emits Organization/LocalBusiness + WebSite sitewide, plus a context node
 * (WebPage / Article / Service / CollectionPage / ProfilePage) and a
 * BreadcrumbList per request. Disable Yoast's schema to avoid duplication.
 *
 * @package SeoArticleBooster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SAB_Schema_Generator
 */
class SAB_Schema_Generator {

	/**
	 * Hooks.
	 */
	public function init() {
		add_action( 'wp_head', array( $this, 'output' ), 1 );
	}

	/**
	 * Print the JSON-LD graph.
	 */
	public function output() {
		if ( ! SAB_Settings::get( 'enable_schema_output' ) ) {
			return;
		}
		if ( is_admin() || is_feed() || is_embed() || is_404() ) {
			return;
		}

		$graph = $this->build_graph();
		if ( empty( $graph ) ) {
			return;
		}

		$data = array(
			'@context' => 'https://schema.org',
			'@graph'   => array_values( $graph ),
		);

		echo "\n<script type=\"application/ld+json\" class=\"sab-schema-graph\">"
			. wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
			. "</script>\n";
	}

	/**
	 * Assemble the full graph for the current request.
	 *
	 * @return array
	 */
	public function build_graph() {
		$graph = array();

		// Sitewide nodes.
		$org = $this->organization_node();
		if ( $org ) {
			$graph[] = $org;
			$logo = $this->logo_node();
			if ( $logo ) {
				$graph[] = $logo;
			}
		}
		$graph[] = $this->website_node();

		// Context node(s).
		$context = $this->context_nodes();
		foreach ( $context as $node ) {
			$graph[] = $node;
		}

		/**
		 * Filter the full schema graph before output.
		 *
		 * @param array $graph Graph nodes.
		 */
		return apply_filters( 'sab_schema_graph', $graph );
	}

	/* ---------------------------------------------------------------------
	 * Identifiers
	 * ------------------------------------------------------------------- */

	protected function org_id() {
		return home_url( '/' ) . '#organization';
	}
	protected function website_id() {
		return home_url( '/' ) . '#website';
	}
	protected function logo_id() {
		return home_url( '/' ) . '#logo';
	}

	/**
	 * Canonical URL for the current context.
	 *
	 * @return string
	 */
	protected function current_url() {
		if ( is_front_page() || is_home() ) {
			return home_url( '/' );
		}
		if ( is_singular() ) {
			$link = get_permalink( get_queried_object_id() );
			return $link ? $link : home_url( '/' );
		}
		$obj = get_queried_object();
		if ( $obj instanceof WP_Term ) {
			$link = get_term_link( $obj );
			return is_wp_error( $link ) ? home_url( '/' ) : $link;
		}
		if ( is_author() ) {
			return get_author_posts_url( get_queried_object_id() );
		}
		if ( is_post_type_archive() ) {
			$link = get_post_type_archive_link( get_post_type() );
			return $link ? $link : home_url( '/' );
		}
		return home_url( add_query_arg( array() ) );
	}

	/* ---------------------------------------------------------------------
	 * Sitewide nodes
	 * ------------------------------------------------------------------- */

	/**
	 * Organization / LocalBusiness node from the business profile.
	 *
	 * @return array|null
	 */
	protected function organization_node() {
		if ( ! SAB_Business_Profile::is_complete() ) {
			// Fall back to a minimal Organization from site identity.
			$name = get_bloginfo( 'name' );
			if ( '' === $name ) {
				return null;
			}
			return array(
				'@type' => 'Organization',
				'@id'   => $this->org_id(),
				'name'  => $name,
				'url'   => home_url( '/' ),
			);
		}

		$type = SAB_Business_Profile::schema_type();

		$node = array(
			'@type' => $type,
			'@id'   => $this->org_id(),
			'name'  => SAB_Business_Profile::get( 'name' ),
			'url'   => SAB_Business_Profile::get( 'url' ) ? SAB_Business_Profile::get( 'url' ) : home_url( '/' ),
		);

		foreach ( array(
			'alternate_name' => 'alternateName',
			'legal_name'     => 'legalName',
			'description'    => 'description',
			'telephone'      => 'telephone',
			'email'          => 'email',
			'price_range'    => 'priceRange',
			'founding_date'  => 'foundingDate',
			'vat_id'         => 'vatID',
		) as $field => $prop ) {
			$val = trim( (string) SAB_Business_Profile::get( $field ) );
			if ( '' !== $val ) {
				$node[ $prop ] = $val;
			}
		}

		// Logo + image.
		if ( SAB_Business_Profile::get( 'logo_id' ) ) {
			$node['logo']  = array( '@id' => $this->logo_id() );
			$node['image'] = array( '@id' => $this->logo_id() );
		}

		// Address.
		$address = $this->address_node();
		if ( $address ) {
			$node['address'] = $address;
		}

		// Geo (LocalBusiness only really uses it).
		$lat = SAB_Business_Profile::get( 'latitude' );
		$lng = SAB_Business_Profile::get( 'longitude' );
		if ( '' !== $lat && '' !== $lng ) {
			$node['geo'] = array(
				'@type'     => 'GeoCoordinates',
				'latitude'  => (float) $lat,
				'longitude' => (float) $lng,
			);
		}

		// Opening hours.
		$hours = SAB_Business_Profile::opening_hours_spec();
		if ( ! empty( $hours ) ) {
			$node['openingHoursSpecification'] = $hours;
		}

		// Area served.
		$area = trim( (string) SAB_Business_Profile::get( 'area_served' ) );
		if ( '' !== $area ) {
			$node['areaServed'] = array_map( 'trim', explode( ',', $area ) );
		}

		// Founder.
		$founder = trim( (string) SAB_Business_Profile::get( 'founder' ) );
		if ( '' !== $founder ) {
			$node['founder'] = array(
				'@type' => 'Person',
				'name'  => $founder,
			);
		}

		// Social profiles.
		$same_as = SAB_Business_Profile::same_as();
		if ( ! empty( $same_as ) ) {
			$node['sameAs'] = $same_as;
		}

		// Contact point.
		$tel = trim( (string) SAB_Business_Profile::get( 'telephone' ) );
		if ( '' !== $tel ) {
			$node['contactPoint'] = array(
				'@type'       => 'ContactPoint',
				'telephone'   => $tel,
				'contactType' => SAB_Business_Profile::get( 'contact_type' ) ? SAB_Business_Profile::get( 'contact_type' ) : 'customer service',
			);
		}

		return $node;
	}

	/**
	 * PostalAddress node, or null if no address fields are set.
	 *
	 * @return array|null
	 */
	protected function address_node() {
		$map = array(
			'street'      => 'streetAddress',
			'locality'    => 'addressLocality',
			'region'      => 'addressRegion',
			'postal_code' => 'postalCode',
			'country'     => 'addressCountry',
		);
		$address = array( '@type' => 'PostalAddress' );
		$has     = false;
		foreach ( $map as $field => $prop ) {
			$val = trim( (string) SAB_Business_Profile::get( $field ) );
			if ( '' !== $val ) {
				$address[ $prop ] = $val;
				$has              = true;
			}
		}
		return $has ? $address : null;
	}

	/**
	 * Logo ImageObject node.
	 *
	 * @return array|null
	 */
	protected function logo_node() {
		$id = SAB_Business_Profile::get( 'logo_id' );
		if ( ! $id ) {
			return null;
		}
		$src = wp_get_attachment_image_url( $id, 'full' );
		if ( ! $src ) {
			return null;
		}
		list( , $w, $h ) = array_pad( (array) wp_get_attachment_image_src( $id, 'full' ), 3, null );
		$node = array(
			'@type'      => 'ImageObject',
			'@id'        => $this->logo_id(),
			'url'        => $src,
			'contentUrl' => $src,
		);
		if ( $w ) {
			$node['width'] = (int) $w;
		}
		if ( $h ) {
			$node['height'] = (int) $h;
		}
		return $node;
	}

	/**
	 * WebSite node with a sitelinks SearchAction.
	 *
	 * @return array
	 */
	protected function website_node() {
		return array(
			'@type'           => 'WebSite',
			'@id'             => $this->website_id(),
			'url'             => home_url( '/' ),
			'name'            => get_bloginfo( 'name' ),
			'description'     => get_bloginfo( 'description' ),
			'publisher'       => array( '@id' => $this->org_id() ),
			'inLanguage'      => get_bloginfo( 'language' ),
			'potentialAction' => array(
				array(
					'@type'       => 'SearchAction',
					'target'      => array(
						'@type'       => 'EntryPoint',
						'urlTemplate' => home_url( '/?s={search_term_string}' ),
					),
					'query-input' => 'required name=search_term_string',
				),
			),
		);
	}

	/* ---------------------------------------------------------------------
	 * Context nodes
	 * ------------------------------------------------------------------- */

	/**
	 * Build the WebPage + primary entity + breadcrumb for the current context.
	 *
	 * @return array[]
	 */
	protected function context_nodes() {
		$url       = $this->current_url();
		$webpage_id = $url . '#webpage';

		$webpage = array(
			'@type'      => $this->webpage_type(),
			'@id'        => $webpage_id,
			'url'        => $url,
			'name'       => wp_get_document_title(),
			'isPartOf'   => array( '@id' => $this->website_id() ),
			'inLanguage' => get_bloginfo( 'language' ),
		);

		// Front page is "about" the organisation.
		if ( is_front_page() ) {
			$webpage['about'] = array( '@id' => $this->org_id() );
		}

		$nodes = array();

		// Breadcrumb.
		$crumbs = $this->breadcrumb_node( $url );
		if ( $crumbs ) {
			$webpage['breadcrumb'] = array( '@id' => $crumbs['@id'] );
			$nodes[]               = $crumbs;
		}

		// Singular: dates, primary image, and a primary entity.
		if ( is_singular() ) {
			$post_id = get_queried_object_id();

			$webpage['datePublished'] = get_post_time( 'c', true, $post_id );
			$webpage['dateModified']  = get_post_modified_time( 'c', true, $post_id );

			$img = get_the_post_thumbnail_url( $post_id, 'full' );
			if ( $img ) {
				$webpage['primaryImageOfPage'] = array( 'url' => $img );
			}

			$primary = $this->primary_entity_node( $post_id, $webpage_id, $url );
			if ( $primary ) {
				$webpage['mainEntity'] = array( '@id' => $primary['@id'] );
				$nodes[]               = $primary;
			}
		}

		// Prepend the webpage node.
		array_unshift( $nodes, $webpage );
		return $nodes;
	}

	/**
	 * The WebPage @type for the current context.
	 *
	 * @return string
	 */
	protected function webpage_type() {
		if ( is_front_page() ) {
			return 'WebPage';
		}
		if ( is_category() || is_tag() || is_tax() || is_post_type_archive() || is_date() ) {
			return 'CollectionPage';
		}
		if ( is_search() ) {
			return 'SearchResultsPage';
		}
		if ( is_author() ) {
			return 'ProfilePage';
		}
		return 'WebPage';
	}

	/**
	 * Build the primary entity for a singular view (Article / Service / etc.).
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $webpage_id WebPage @id.
	 * @param string $url        Canonical URL.
	 * @return array|null
	 */
	protected function primary_entity_node( $post_id, $webpage_id, $url ) {
		$post_type = get_post_type( $post_id );
		$service   = (string) SAB_Settings::get( 'service_post_type', 'service' );

		// Service custom post type.
		if ( $post_type === $service ) {
			$node = array(
				'@type'       => 'Service',
				'@id'         => $url . '#service',
				'name'        => get_the_title( $post_id ),
				'url'         => $url,
				'description' => $this->excerpt( $post_id ),
				'provider'    => array( '@id' => $this->org_id() ),
				'serviceType' => get_the_title( $post_id ),
			);
			$area = trim( (string) SAB_Business_Profile::get( 'area_served' ) );
			if ( '' !== $area ) {
				$node['areaServed'] = array_map( 'trim', explode( ',', $area ) );
			}
			return $node;
		}

		// Article-like post types (default: posts).
		$article_types = (array) apply_filters( 'sab_article_post_types', array( 'post' ) );
		if ( in_array( $post_type, $article_types, true ) ) {
			$node = array(
				'@type'            => 'Article',
				'@id'              => $url . '#article',
				'isPartOf'         => array( '@id' => $webpage_id ),
				'mainEntityOfPage' => array( '@id' => $webpage_id ),
				'headline'         => get_the_title( $post_id ),
				'description'      => $this->excerpt( $post_id ),
				'datePublished'    => get_post_time( 'c', true, $post_id ),
				'dateModified'     => get_post_modified_time( 'c', true, $post_id ),
				'author'           => $this->author_node( $post_id ),
				'publisher'        => array( '@id' => $this->org_id() ),
				'inLanguage'       => get_bloginfo( 'language' ),
			);
			$img = get_the_post_thumbnail_url( $post_id, 'full' );
			if ( $img ) {
				$node['image'] = $img;
			}
			$cats = get_the_category( $post_id );
			if ( ! empty( $cats ) && ! is_wp_error( $cats ) ) {
				$node['articleSection'] = wp_list_pluck( $cats, 'name' );
			}
			return $node;
		}

		// Pages and other singulars are adequately described by the WebPage node.
		return null;
	}

	/**
	 * Author Person node.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	protected function author_node( $post_id ) {
		$author_id = (int) get_post_field( 'post_author', $post_id );
		return array(
			'@type' => 'Person',
			'name'  => get_the_author_meta( 'display_name', $author_id ),
			'url'   => get_author_posts_url( $author_id ),
		);
	}

	/**
	 * A short plain-text description for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	protected function excerpt( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		$text = has_excerpt( $post_id ) ? $post->post_excerpt : wp_strip_all_tags( $post->post_content );
		$text = trim( preg_replace( '/\s+/', ' ', $text ) );
		return wp_html_excerpt( $text, 300, '…' );
	}

	/**
	 * BreadcrumbList node for the current context.
	 *
	 * @param string $url Current URL.
	 * @return array|null
	 */
	protected function breadcrumb_node( $url ) {
		$items = array();
		$items[] = array( 'name' => __( 'Home', 'seo-article-booster' ), 'url' => home_url( '/' ) );

		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			// Page ancestors.
			foreach ( array_reverse( get_post_ancestors( $post_id ) ) as $anc ) {
				$items[] = array( 'name' => get_the_title( $anc ), 'url' => get_permalink( $anc ) );
			}
			$items[] = array( 'name' => get_the_title( $post_id ), 'url' => $url );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term    = get_queried_object();
			$items[] = array( 'name' => $term->name, 'url' => $url );
		} elseif ( is_post_type_archive() ) {
			$items[] = array( 'name' => post_type_archive_title( '', false ), 'url' => $url );
		} elseif ( is_author() ) {
			$items[] = array( 'name' => get_the_author_meta( 'display_name', get_queried_object_id() ), 'url' => $url );
		} elseif ( is_search() ) {
			$items[] = array( 'name' => get_search_query(), 'url' => $url );
		} elseif ( is_front_page() ) {
			// Home only — a single-item breadcrumb is not useful.
			return null;
		}

		if ( count( $items ) < 2 ) {
			return null;
		}

		$list = array();
		foreach ( $items as $i => $item ) {
			$list[] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $item['name'],
				'item'     => $item['url'],
			);
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $url . '#breadcrumb',
			'itemListElement' => $list,
		);
	}
}
