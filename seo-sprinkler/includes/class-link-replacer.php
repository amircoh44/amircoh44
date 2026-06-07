<?php
/**
 * The DOM-based text replacement engine.
 *
 * Given a chunk of HTML and a list of "anchor phrase => target URL" entries,
 * it walks the text nodes and wraps matching phrases in <a> tags. It is
 * deliberately conservative:
 *
 *   - Only text nodes are touched; HTML structure and attributes are preserved.
 *   - Matches inside <a>, <code>, <pre>, <script>, <style>, form controls and
 *     (optionally) headings are skipped, so we never nest links or rewrite code.
 *   - Whole-word, Unicode-aware matching avoids linking fragments of words.
 *   - Per-keyword and per-document caps prevent over-linking.
 *
 * Both the non-destructive display filter and the permanent "apply" tool use
 * this single engine, so their behaviour is always identical.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_Link_Replacer
 */
class SPR_Link_Replacer {

	/**
	 * CSS class added to every link we create, so links can later be styled,
	 * detected, or stripped during a revert.
	 */
	const LINK_CLASS = 'spr-internal-link';

	/**
	 * Tags whose text content must never be linked.
	 *
	 * @var string[]
	 */
	protected $skip_tags = array( 'a', 'code', 'pre', 'script', 'style', 'textarea', 'button', 'select', 'option', 'label' );

	/**
	 * Replace phrases in an HTML string.
	 *
	 * @param string $html    HTML fragment (e.g. post content).
	 * @param array  $entries Link index entries. Each: array( 'url','regex','key','post_id' ).
	 * @param array  $options {
	 *     @type int   $max_per_post    Hard cap of links to add. Default 5.
	 *     @type int   $max_per_keyword Max links per phrase key. Default 1.
	 *     @type bool  $skip_headings   Skip <h1>-<h6>. Default true.
	 *     @type bool  $new_tab         Add target="_blank". Default false.
	 *     @type bool  $nofollow        Add rel="nofollow". Default false.
	 * }
	 * @return array{0:string,1:int} Modified HTML and the number of links added.
	 */
	public function replace( $html, array $entries, array $options = array() ) {
		$options = wp_parse_args(
			$options,
			array(
				'max_per_post'    => 5,
				'max_per_keyword' => 1,
				'skip_headings'   => true,
				'new_tab'         => false,
				'nofollow'        => false,
			)
		);

		if ( empty( $entries ) || '' === trim( (string) $html ) || $options['max_per_post'] < 1 ) {
			return array( $html, 0 );
		}

		// Entries should already be sorted longest-phrase-first by the index, but
		// guarantee it here so more specific anchors win at the same position.
		usort(
			$entries,
			static function ( $a, $b ) {
				return strlen( $b['phrase'] ) <=> strlen( $a['phrase'] );
			}
		);

		$dom = $this->load_dom( $html );
		if ( ! $dom ) {
			return array( $html, 0 );
		}

		$body = $dom->getElementsByTagName( 'body' )->item( 0 );
		if ( ! $body ) {
			return array( $html, 0 );
		}

		// Shared mutable state across the whole document walk.
		$state = array(
			'added'         => 0,
			'per_keyword'   => array(), // key => count.
			'max_post'      => (int) $options['max_per_post'],
			'max_keyword'   => (int) $options['max_per_keyword'],
		);

		$skip_tags = $this->skip_tags;
		if ( $options['skip_headings'] ) {
			$skip_tags = array_merge( $skip_tags, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) );
		}

		$this->walk( $body, $entries, $options, $state, $skip_tags, $dom );

		if ( 0 === $state['added'] ) {
			return array( $html, 0 );
		}

		return array( $this->serialize_body( $dom, $body ), $state['added'] );
	}

	/**
	 * Recursively walk child nodes, replacing inside eligible text nodes.
	 *
	 * We snapshot the child list first because we mutate the tree as we go.
	 *
	 * @param DOMNode     $node      Current element.
	 * @param array       $entries   Index entries.
	 * @param array       $options   Options.
	 * @param array       $state     Mutable counters (by reference).
	 * @param string[]    $skip_tags Tags to skip.
	 * @param DOMDocument $dom       Owner document.
	 */
	protected function walk( $node, $entries, $options, &$state, $skip_tags, $dom ) {
		if ( $state['added'] >= $state['max_post'] ) {
			return;
		}

		// Iterate over a static copy of the children.
		$children = array();
		foreach ( $node->childNodes as $child ) {
			$children[] = $child;
		}

		foreach ( $children as $child ) {
			if ( $state['added'] >= $state['max_post'] ) {
				return;
			}

			if ( XML_TEXT_NODE === $child->nodeType ) {
				$this->process_text_node( $child, $entries, $options, $state, $dom );
				continue;
			}

			if ( XML_ELEMENT_NODE === $child->nodeType ) {
				$tag = strtolower( $child->nodeName );

				// Never descend into skipped elements (e.g. existing links).
				if ( in_array( $tag, $skip_tags, true ) ) {
					continue;
				}

				$this->walk( $child, $entries, $options, $state, $skip_tags, $dom );
			}
		}
	}

	/**
	 * Replace matches within a single text node, splitting it into text + <a>.
	 *
	 * @param DOMText     $text_node Text node to process.
	 * @param array       $entries   Index entries.
	 * @param array       $options   Options.
	 * @param array       $state     Mutable counters (by reference).
	 * @param DOMDocument $dom       Owner document.
	 */
	protected function process_text_node( $text_node, $entries, $options, &$state, $dom ) {
		$text = $text_node->nodeValue;
		if ( '' === trim( $text ) ) {
			return;
		}

		$replacement = array(); // Ordered list of DOMNode to replace this text node with.
		$offset      = 0;
		$length      = strlen( $text );

		while ( $offset < $length && $state['added'] < $state['max_post'] ) {
			$best = $this->find_next_match( $text, $offset, $entries, $state );
			if ( null === $best ) {
				break;
			}

			// Text preceding the match.
			if ( $best['pos'] > $offset ) {
				$replacement[] = $dom->createTextNode( substr( $text, $offset, $best['pos'] - $offset ) );
			}

			// The link itself.
			$replacement[] = $this->build_anchor( $dom, $best['entry'], $best['match'], $options );

			// Update counters.
			$state['added']++;
			$key                          = $best['entry']['key'];
			$state['per_keyword'][ $key ] = ( isset( $state['per_keyword'][ $key ] ) ? $state['per_keyword'][ $key ] : 0 ) + 1;

			$offset = $best['pos'] + strlen( $best['match'] );
		}

		// Nothing matched: leave the node untouched.
		if ( empty( $replacement ) ) {
			return;
		}

		// Trailing text after the last match.
		if ( $offset < $length ) {
			$replacement[] = $dom->createTextNode( substr( $text, $offset ) );
		}

		// Swap the original text node for our sequence of nodes.
		$parent = $text_node->parentNode;
		foreach ( $replacement as $new_node ) {
			$parent->insertBefore( $new_node, $text_node );
		}
		$parent->removeChild( $text_node );
	}

	/**
	 * Find the earliest eligible phrase match in $text at or after $offset.
	 *
	 * "Earliest" wins; on a tie the longest phrase wins (entries are pre-sorted).
	 * Phrases that have hit their per-keyword cap are skipped.
	 *
	 * @param string $text    Subject text.
	 * @param int    $offset  Byte offset to search from.
	 * @param array  $entries Index entries.
	 * @param array  $state   Counters.
	 * @return array|null { pos:int, match:string, entry:array } or null.
	 */
	protected function find_next_match( $text, $offset, $entries, $state ) {
		$best = null;

		foreach ( $entries as $entry ) {
			$key = $entry['key'];
			if ( isset( $state['per_keyword'][ $key ] ) && $state['per_keyword'][ $key ] >= $state['max_keyword'] ) {
				continue;
			}

			if ( preg_match( $entry['regex'], $text, $m, PREG_OFFSET_CAPTURE, $offset ) ) {
				$pos        = $m[1][1]; // Byte offset of capture group 1.
				$match_text = $m[1][0];

				if (
					null === $best
					|| $pos < $best['pos']
					|| ( $pos === $best['pos'] && strlen( $match_text ) > strlen( $best['match'] ) )
				) {
					$best = array(
						'pos'   => $pos,
						'match' => $match_text,
						'entry' => $entry,
					);
				}
			}
		}

		return $best;
	}

	/**
	 * Build an <a> element for a matched phrase.
	 *
	 * @param DOMDocument $dom        Owner document.
	 * @param array       $entry      Index entry.
	 * @param string      $match_text The exact text that matched (preserves case).
	 * @param array       $options    Options.
	 * @return DOMElement
	 */
	protected function build_anchor( $dom, $entry, $match_text, $options ) {
		$a = $dom->createElement( 'a' );
		$a->setAttribute( 'href', $entry['url'] );
		$a->setAttribute( 'class', self::LINK_CLASS );
		$a->setAttribute( 'data-spr-target', (string) $entry['post_id'] );

		$rel = array();
		if ( ! empty( $options['nofollow'] ) ) {
			$rel[] = 'nofollow';
		}
		if ( ! empty( $options['new_tab'] ) ) {
			$a->setAttribute( 'target', '_blank' );
			$rel[] = 'noopener';
		}
		if ( $rel ) {
			$a->setAttribute( 'rel', implode( ' ', array_unique( $rel ) ) );
		}

		// appendChild on a text node performs the necessary entity encoding.
		$a->appendChild( $dom->createTextNode( $match_text ) );

		return $a;
	}

	/* ---------------------------------------------------------------------
	 * DOM helpers
	 * ------------------------------------------------------------------- */

	/**
	 * Load an HTML fragment into a DOMDocument with reliable UTF-8 handling.
	 *
	 * @param string $html HTML fragment.
	 * @return DOMDocument|false
	 */
	protected function load_dom( $html ) {
		if ( ! class_exists( 'DOMDocument' ) ) {
			return false;
		}

		$dom      = new DOMDocument( '1.0', 'UTF-8' );
		$previous = libxml_use_internal_errors( true );

		// The XML encoding hint forces libxml to treat the bytes as UTF-8.
		// Wrapping in <html><body> gives us a stable node to serialise from.
		$wrapped = '<?xml encoding="UTF-8">' . '<html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';

		$loaded = $dom->loadHTML( $wrapped, LIBXML_HTML_NODEFDTD | LIBXML_NONET );

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return $loaded ? $dom : false;
	}

	/**
	 * Serialise the inner HTML of the <body> back to a string.
	 *
	 * @param DOMDocument $dom  Document.
	 * @param DOMElement  $body Body element.
	 * @return string
	 */
	protected function serialize_body( $dom, $body ) {
		$html = '';
		foreach ( $body->childNodes as $child ) {
			$html .= $dom->saveHTML( $child );
		}
		return $html;
	}

	/* ---------------------------------------------------------------------
	 * Reverting permanently-applied links
	 * ------------------------------------------------------------------- */

	/**
	 * Remove links previously inserted by this plugin from an HTML string,
	 * keeping the original anchor text. Identified by our LINK_CLASS.
	 *
	 * @param string $html HTML containing plugin links.
	 * @return array{0:string,1:int} Cleaned HTML and number of links removed.
	 */
	public function strip_links( $html ) {
		if ( false === strpos( $html, self::LINK_CLASS ) ) {
			return array( $html, 0 );
		}

		$dom = $this->load_dom( $html );
		if ( ! $dom ) {
			return array( $html, 0 );
		}

		$body  = $dom->getElementsByTagName( 'body' )->item( 0 );
		$xpath = new DOMXPath( $dom );
		// Match <a> whose class attribute contains our token.
		$nodes = $xpath->query( "//a[contains(concat(' ', normalize-space(@class), ' '), ' " . self::LINK_CLASS . " ')]" );

		$removed = 0;
		if ( $nodes ) {
			foreach ( $nodes as $anchor ) {
				$parent = $anchor->parentNode;
				// Replace the <a> with its child nodes (the anchor text).
				while ( $anchor->firstChild ) {
					$parent->insertBefore( $anchor->firstChild, $anchor );
				}
				$parent->removeChild( $anchor );
				$removed++;
			}
		}

		if ( 0 === $removed ) {
			return array( $html, 0 );
		}

		return array( $this->serialize_body( $dom, $body ), $removed );
	}
}
