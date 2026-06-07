<?php
/**
 * Display-time internal linking.
 *
 * Hooks `the_content` and injects internal links on the fly when a singular
 * post/page is viewed. Nothing is written to the database, so this is fully
 * reversible: disable the plugin and the original content reappears untouched.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_Link_Injector
 */
class SPR_Link_Injector {

	/**
	 * Link index.
	 *
	 * @var SPR_Link_Index
	 */
	protected $index;

	/**
	 * Replacement engine.
	 *
	 * @var SPR_Link_Replacer
	 */
	protected $replacer;

	/**
	 * Constructor.
	 *
	 * @param SPR_Link_Index    $index    Link index.
	 * @param SPR_Link_Replacer $replacer Replacement engine.
	 */
	public function __construct( SPR_Link_Index $index, SPR_Link_Replacer $replacer ) {
		$this->index    = $index;
		$this->replacer = $replacer;
	}

	/**
	 * Register the content filter.
	 */
	public function init() {
		// Priority 20 keeps us after wpautop/shortcode rendering (priority 10/11).
		add_filter( 'the_content', array( $this, 'filter_content' ), 20 );
	}

	/**
	 * Inject links into the post content for front-end singular views.
	 *
	 * @param string $content Post content HTML.
	 * @return string
	 */
	public function filter_content( $content ) {
		if ( ! $this->should_run() ) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}

		// Exclude the current post so it cannot link to itself.
		$entries = $this->index->get_index_excluding( $post_id );
		if ( empty( $entries ) ) {
			return $content;
		}

		list( $linked, $added ) = $this->replacer->replace( $content, $entries, $this->replacer_options() );

		return $added > 0 ? $linked : $content;
	}

	/**
	 * Decide whether the filter should act on the current request.
	 *
	 * @return bool
	 */
	protected function should_run() {
		if ( ! SPR_Settings::get( 'enable_auto_linking' ) ) {
			return false;
		}
		if ( ! SPR_Edition::can( 'auto_linking' ) ) {
			return false; // Automatic internal linking is a Pro feature.
		}
		// Never touch admin screens, feeds or REST output.
		if ( is_admin() || is_feed() ) {
			return false;
		}
		// Only the main content of a singular, linkable post type, in the loop.
		$types = (array) SPR_Settings::get( 'link_post_types', array( 'post', 'page' ) );
		if ( ! is_singular( $types ) || ! in_the_loop() || ! is_main_query() ) {
			return false;
		}

		/**
		 * Allow themes/plugins to disable injection for a specific request.
		 *
		 * @param bool $run Whether to run.
		 */
		return (bool) apply_filters( 'spr_should_inject_links', true );
	}

	/**
	 * Build the options array for the replacer from settings.
	 *
	 * @return array
	 */
	protected function replacer_options() {
		return array(
			'max_per_post'    => (int) SPR_Settings::get( 'max_links_per_post', 5 ),
			'max_per_keyword' => (int) SPR_Settings::get( 'max_links_per_keyword', 1 ),
			'skip_headings'   => (bool) SPR_Settings::get( 'skip_headings', 1 ),
			'new_tab'         => (bool) SPR_Settings::get( 'open_new_tab', 0 ),
			'nofollow'        => (bool) SPR_Settings::get( 'nofollow', 0 ),
		);
	}
}
