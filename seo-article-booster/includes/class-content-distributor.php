<?php
/**
 * Content distribution ("Sprinkler") engine.
 *
 * At display time it finds the injection rules that match the current article
 * and places each rule's payload (a shortcode such as an Elementor template, an
 * image, or custom HTML) at the requested position inside the content — around
 * sub-headings, around paragraphs, in the gap between paragraphs, and so on.
 *
 * Injection happens on `the_content`, so your stored content is never modified:
 * the article "stays as is" and the sprinkled blocks are added on the fly.
 *
 * @package SeoArticleBooster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SAB_Content_Distributor
 */
class SAB_Content_Distributor {

	/**
	 * CSS class added to every injected wrapper.
	 */
	const WRAP_CLASS = 'sab-injection';

	/**
	 * Rules service.
	 *
	 * @var SAB_Injection_Rules
	 */
	protected $rules;

	/**
	 * Constructor.
	 *
	 * @param SAB_Injection_Rules $rules Rules service.
	 */
	public function __construct( SAB_Injection_Rules $rules ) {
		$this->rules = $rules;
	}

	/**
	 * Register hooks.
	 */
	public function init() {
		// After the internal-link injector (20) so links land first.
		add_filter( 'the_content', array( $this, 'distribute' ), 25 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_styles' ) );
	}

	/**
	 * Enqueue the small front-end stylesheet (alignment + device visibility).
	 */
	public function enqueue_public_styles() {
		if ( ! is_singular() ) {
			return;
		}
		wp_enqueue_style( 'sab-public', SAB_PLUGIN_URL . 'public/css/public.css', array(), SAB_VERSION );
	}

	/**
	 * Inject matching rules' payloads into the content.
	 *
	 * @param string $content Post content HTML.
	 * @return string
	 */
	public function distribute( $content ) {
		if ( ! $this->should_run() || '' === trim( (string) $content ) ) {
			return $content;
		}

		$post = get_post( get_the_ID() );
		if ( ! $post ) {
			return $content;
		}

		$rules = $this->rules->get_matching_rules( $post );
		if ( empty( $rules ) ) {
			return $content;
		}

		$dom = $this->load_dom( $content );
		if ( ! $dom ) {
			return $content;
		}
		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body ) {
			return $content;
		}

		$changed = false;
		foreach ( $rules as $rule ) {
			if ( ! empty( $rule['dedupe'] ) && $this->already_present( $content, $rule ) ) {
				continue;
			}

			$wrapper = $this->build_payload_node( $dom, $rule );
			if ( ! $wrapper ) {
				continue;
			}

			$ops = $this->resolve_insertions( $body, $rule );
			if ( empty( $ops ) ) {
				continue;
			}

			$ops = array_slice( $ops, 0, max( 1, (int) $rule['max_insertions'] ) );
			foreach ( $ops as $op ) {
				$node = $wrapper->cloneNode( true );
				$this->insert( $node, $op );
				$changed = true;
			}
		}

		return $changed ? $this->serialize_body( $dom, $body ) : $content;
	}

	/**
	 * Should the filter run for this request?
	 *
	 * @return bool
	 */
	protected function should_run() {
		if ( ! SAB_Settings::get( 'enable_distribution', 1 ) ) {
			return false;
		}
		if ( ! SAB_Edition::can( 'distribution' ) ) {
			return false; // Content distribution is a Pro feature.
		}
		if ( is_admin() || is_feed() ) {
			return false;
		}
		if ( ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return false;
		}
		/** Allow disabling distribution per request. */
		return (bool) apply_filters( 'sab_should_distribute', true );
	}

	/* ---------------------------------------------------------------------
	 * Payload building
	 * ------------------------------------------------------------------- */

	/**
	 * Render the inner HTML of a rule's payload.
	 *
	 * @param array $rule Rule.
	 * @return string
	 */
	protected function render_payload_inner( $rule ) {
		switch ( $rule['payload_type'] ) {
			case 'image':
				return $this->render_image( $rule );

			case 'html':
				// Allow shortcodes inside custom HTML too.
				return do_shortcode( (string) $rule['html'] );

			case 'shortcode':
			default:
				// Render the shortcode now, because the_content's own
				// do_shortcode (priority 11) has already run by priority 25.
				return do_shortcode( (string) $rule['shortcode'] );
		}
	}

	/**
	 * Render an image payload as a <figure>.
	 *
	 * @param array $rule Rule.
	 * @return string
	 */
	protected function render_image( $rule ) {
		$id = (int) $rule['image_id'];
		if ( $id < 1 ) {
			return '';
		}

		$attr = array( 'class' => 'sab-injection-img' );
		if ( '' !== trim( (string) $rule['image_alt'] ) ) {
			$attr['alt'] = $rule['image_alt'];
		}

		$img = wp_get_attachment_image( $id, $rule['image_size'] ? $rule['image_size'] : 'large', false, $attr );
		if ( '' === $img ) {
			return '';
		}

		if ( '' !== trim( (string) $rule['image_link'] ) ) {
			$img = '<a href="' . esc_url( $rule['image_link'] ) . '">' . $img . '</a>';
		}

		$align = in_array( $rule['image_align'], array( 'left', 'center', 'right', 'none' ), true ) ? $rule['image_align'] : 'center';
		return '<figure class="align' . esc_attr( $align ) . '">' . $img . '</figure>';
	}

	/**
	 * Build the wrapper DOM node for a payload (or null if empty).
	 *
	 * @param DOMDocument $dom  Owner document.
	 * @param array       $rule Rule.
	 * @return DOMElement|null
	 */
	protected function build_payload_node( $dom, $rule ) {
		$inner = trim( (string) $this->render_payload_inner( $rule ) );
		if ( '' === $inner ) {
			return null;
		}

		$classes = array( self::WRAP_CLASS, self::WRAP_CLASS . '--' . sanitize_html_class( $rule['payload_type'] ) );
		if ( in_array( $rule['device'], array( 'desktop', 'mobile' ), true ) ) {
			$classes[] = self::WRAP_CLASS . '--' . $rule['device'] . '-only';
		}
		if ( '' !== $rule['wrapper_class'] ) {
			$classes[] = $rule['wrapper_class'];
		}

		$wrapper = $dom->createElement( 'div' );
		$wrapper->setAttribute( 'class', implode( ' ', $classes ) );
		$wrapper->setAttribute( 'data-sab-rule', (string) $rule['id'] );

		// Import the rendered inner HTML as real nodes.
		foreach ( $this->html_to_nodes( $dom, $inner ) as $node ) {
			$wrapper->appendChild( $node );
		}

		return $wrapper->hasChildNodes() ? $wrapper : null;
	}

	/* ---------------------------------------------------------------------
	 * Placement resolution
	 * ------------------------------------------------------------------- */

	/**
	 * Work out where to insert, returning ops: array( node => DOMNode, pos => before|after ).
	 *
	 * @param DOMElement $body Body element.
	 * @param array      $rule Rule.
	 * @return array[]
	 */
	protected function resolve_insertions( $body, $rule ) {
		$blocks = $this->block_children( $body );
		if ( empty( $blocks ) ) {
			return array();
		}

		$paragraphs = array_values(
			array_filter(
				$blocks,
				static function ( $n ) {
					return 'p' === strtolower( $n->nodeName );
				}
			)
		);
		$levels   = array_map( 'strtolower', (array) $rule['heading_levels'] );
		$headings = array_values(
			array_filter(
				$blocks,
				static function ( $n ) use ( $levels ) {
					return in_array( strtolower( $n->nodeName ), $levels, true );
				}
			)
		);

		$pos = max( 1, (int) $rule['position'] );
		$min = max( 0, (int) $rule['min_paragraphs'] );
		$ops = array();

		switch ( $rule['placement'] ) {
			case 'top':
				$ops[] = array( 'node' => $blocks[0], 'pos' => 'before' );
				break;

			case 'bottom':
				$ops[] = array( 'node' => end( $blocks ), 'pos' => 'after' );
				break;

			case 'before_paragraph':
			case 'after_paragraph':
				$node = $this->nth( $paragraphs, $pos );
				if ( $node ) {
					$ops[] = array( 'node' => $node, 'pos' => ( 'before_paragraph' === $rule['placement'] ) ? 'before' : 'after' );
				}
				break;

			case 'before_heading':
			case 'after_heading':
				$node = $this->nth( $headings, $pos );
				if ( $node ) {
					$ops[] = array( 'node' => $node, 'pos' => ( 'before_heading' === $rule['placement'] ) ? 'before' : 'after' );
				}
				break;

			case 'before_first_heading':
			case 'after_first_heading':
				if ( ! empty( $headings ) ) {
					$ops[] = array( 'node' => $headings[0], 'pos' => ( 'before_first_heading' === $rule['placement'] ) ? 'before' : 'after' );
				}
				break;

			case 'before_last_heading':
			case 'after_last_heading':
				if ( ! empty( $headings ) ) {
					$ops[] = array( 'node' => end( $headings ), 'pos' => ( 'before_last_heading' === $rule['placement'] ) ? 'before' : 'after' );
				}
				break;

			case 'middle':
				$ops[] = array( 'node' => $blocks[ (int) floor( count( $blocks ) / 2 ) ], 'pos' => 'before' );
				break;

			case 'after_words':
				$node = $this->paragraph_after_words( $paragraphs, $pos );
				if ( $node ) {
					$ops[] = array( 'node' => $node, 'pos' => 'after' );
				}
				break;

			case 'every_n_paragraphs':
				$count = count( $paragraphs );
				for ( $i = $pos; $i <= $count; $i += $pos ) {
					if ( $i < $min ) {
						continue;
					}
					$ops[] = array( 'node' => $paragraphs[ $i - 1 ], 'pos' => 'after' );
				}
				break;

			case 'between_paragraphs':
				$ops[] = $this->between_paragraphs_op( $blocks, $paragraphs, $min );
				$ops   = array_filter( $ops );
				break;

			default:
				break;
		}

		return $ops;
	}

	/**
	 * Resolve the "between two paragraphs" placement, preferring an empty
	 * paragraph gap when one exists, otherwise inserting after an early paragraph.
	 *
	 * @param array $blocks     Top-level block nodes.
	 * @param array $paragraphs Paragraph nodes.
	 * @param int   $min        Minimum paragraphs before insertion.
	 * @return array|null
	 */
	protected function between_paragraphs_op( $blocks, $paragraphs, $min ) {
		// 1. Prefer an empty paragraph that sits between two other elements.
		foreach ( $blocks as $i => $node ) {
			if ( 'p' !== strtolower( $node->nodeName ) || ! $this->is_empty_paragraph( $node ) ) {
				continue;
			}
			$has_prev = $i > 0;
			$has_next = isset( $blocks[ $i + 1 ] );
			if ( $has_prev && $has_next ) {
				// Insert into the empty gap (before the blank paragraph).
				return array( 'node' => $node, 'pos' => 'before' );
			}
		}

		// 2. Otherwise drop it between the first eligible pair of paragraphs.
		if ( count( $paragraphs ) >= 2 ) {
			$index = max( 1, $min ); // After the Nth paragraph (1-based).
			$index = min( $index, count( $paragraphs ) - 1 );
			return array( 'node' => $paragraphs[ $index - 1 ], 'pos' => 'after' );
		}

		// 3. Only one paragraph: put it after that paragraph ("inside").
		if ( 1 === count( $paragraphs ) ) {
			return array( 'node' => $paragraphs[0], 'pos' => 'after' );
		}

		return null;
	}

	/**
	 * Return the Nth (1-based) node from a list, clamped to the last item.
	 *
	 * @param array $list  Nodes.
	 * @param int   $n     1-based index.
	 * @return DOMNode|null
	 */
	protected function nth( $list, $n ) {
		if ( empty( $list ) ) {
			return null;
		}
		$index = min( count( $list ), max( 1, $n ) ) - 1;
		return $list[ $index ];
	}

	/**
	 * Find the paragraph after which the cumulative word count passes a target.
	 *
	 * @param array $paragraphs Paragraph nodes.
	 * @param int   $words      Target word count.
	 * @return DOMNode|null
	 */
	protected function paragraph_after_words( $paragraphs, $words ) {
		$total = 0;
		foreach ( $paragraphs as $p ) {
			$total += str_word_count( wp_strip_all_tags( $p->textContent ) );
			if ( $total >= $words ) {
				return $p;
			}
		}
		return ! empty( $paragraphs ) ? end( $paragraphs ) : null;
	}

	/**
	 * Insert a node relative to a reference node.
	 *
	 * @param DOMNode $node New node.
	 * @param array   $op   array( node => ref, pos => before|after ).
	 */
	protected function insert( $node, $op ) {
		$ref    = $op['node'];
		$parent = $ref->parentNode;
		if ( ! $parent ) {
			return;
		}
		if ( 'before' === $op['pos'] ) {
			$parent->insertBefore( $node, $ref );
		} elseif ( $ref->nextSibling ) {
			$parent->insertBefore( $node, $ref->nextSibling );
		} else {
			$parent->appendChild( $node );
		}
	}

	/* ---------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------- */

	/**
	 * Element (block-level) children of the body.
	 *
	 * @param DOMElement $body Body.
	 * @return DOMElement[]
	 */
	protected function block_children( $body ) {
		$blocks = array();
		foreach ( $body->childNodes as $child ) {
			if ( XML_ELEMENT_NODE === $child->nodeType ) {
				$blocks[] = $child;
			}
		}
		return $blocks;
	}

	/**
	 * Is a <p> empty (blank or just &nbsp;)?
	 *
	 * @param DOMNode $node Node.
	 * @return bool
	 */
	protected function is_empty_paragraph( $node ) {
		$text = str_replace( "\xc2\xa0", '', (string) $node->textContent ); // Strip non-breaking spaces.
		return '' === trim( $text ) && ! $node->getElementsByTagName( 'img' )->length;
	}

	/**
	 * Is the payload already present in the content (dedupe)?
	 *
	 * @param string $content Raw content.
	 * @param array  $rule    Rule.
	 * @return bool
	 */
	protected function already_present( $content, $rule ) {
		if ( 'image' === $rule['payload_type'] && $rule['image_id'] ) {
			return false !== strpos( $content, 'wp-image-' . (int) $rule['image_id'] );
		}
		if ( 'shortcode' === $rule['payload_type'] && '' !== trim( (string) $rule['shortcode'] ) ) {
			// Compare the first shortcode tag, e.g. [elementor-template ...].
			if ( preg_match( '/\[([a-z0-9_-]+)/i', $rule['shortcode'], $m ) ) {
				return false !== strpos( $content, '[' . $m[1] );
			}
		}
		return false;
	}

	/**
	 * Convert an HTML string into imported DOM nodes belonging to $dom.
	 *
	 * @param DOMDocument $dom  Target document.
	 * @param string      $html HTML fragment.
	 * @return DOMNode[]
	 */
	protected function html_to_nodes( $dom, $html ) {
		$temp = $this->load_dom( $html );
		if ( ! $temp ) {
			return array();
		}
		$body = $temp->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body ) {
			return array();
		}

		$nodes = array();
		foreach ( $body->childNodes as $child ) {
			$nodes[] = $dom->importNode( $child, true );
		}
		return $nodes;
	}

	/**
	 * Load an HTML fragment into a DOMDocument (UTF-8 safe).
	 *
	 * @param string $html HTML.
	 * @return DOMDocument|false
	 */
	protected function load_dom( $html ) {
		if ( ! class_exists( 'DOMDocument' ) ) {
			return false;
		}
		$dom      = new DOMDocument( '1.0', 'UTF-8' );
		$previous = libxml_use_internal_errors( true );
		$wrapped  = '<?xml encoding="UTF-8">' . '<html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';
		$loaded   = $dom->loadHTML( $wrapped, LIBXML_HTML_NODEFDTD | LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		return $loaded ? $dom : false;
	}

	/**
	 * Serialize the inner HTML of <body>.
	 *
	 * @param DOMDocument $dom  Document.
	 * @param DOMElement  $body Body.
	 * @return string
	 */
	protected function serialize_body( $dom, $body ) {
		$html = '';
		foreach ( $body->childNodes as $child ) {
			$html .= $dom->saveHTML( $child );
		}
		return $html;
	}
}
