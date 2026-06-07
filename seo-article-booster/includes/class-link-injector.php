<?php
/**
 * Display-time internal linking.
 *
 * Hooks `the_content` and injects internal links on the fly when a singular
 * post/page is viewed. Nothing is written to the database, so this is fully
 * reversible: disable the plugin and the original content reappears untouched.
 *
 * @package SeoArticleBooster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SAB_Link_Injector
 */
class SAB_Link_Injector {

	/**
	 * Link index.
	 *
	 * @var SAB_Link_Index
	 */
	protected $index;

	/**
	 * Replacement engine.
	 *
	 * @var SAB_Link_Replacer
	 */
	protected $replacer;

	/**
	 * Constructor.
	 *
	 * @param SAB_Link_Index    $index    Link index.
	 * @param SAB_Link_Replacer $replacer Replacement engine.
	 */
	public function __construct( SAB_Link_Index $index, SAB_Link_Replacer $replacer ) {
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
		if ( ! SAB_Settings::get( 'enable_auto_linking' ) ) {
			return false;
		}
		if ( ! SAB_Edition::can( 'auto_linking' ) ) {
			return false; // Automatic internal linking is a Pro feature.
		}
		// Never touch admin screens, feeds or REST output.
		if ( is_admin() || is_feed() ) {
			return false;
		}
		// Only the main content of a singular, linkable post type, in the loop.
		$types = (array) SAB_Settings::get( 'link_post_types', array( 'post', 'page' ) );
		if ( ! is_singular( $types ) || ! in_the_loop() || ! is_main_query() ) {
			return false;
		}

		/**
		 * Allow themes/plugins to disable injection for a specific request.
		 *
		 * @param bool $run Whether to run.
		 */
		return (bool) apply_filters( 'sab_should_inject_links', true );
	}

	/**
	 * Build the options array for the replacer from settings.
	 *
	 * @return array
	 */
	protected function replacer_options() {
		return array(
			'max_per_post'    => (int) SAB_Settings::get( 'max_links_per_post', 5 ),
			'max_per_keyword' => (int) SAB_Settings::get( 'max_links_per_keyword', 1 ),
			'skip_headings'   => (bool) SAB_Settings::get( 'skip_headings', 1 ),
			'new_tab'         => (bool) SAB_Settings::get( 'open_new_tab', 0 ),
			'nofollow'        => (bool) SAB_Settings::get( 'nofollow', 0 ),
		);
	}
}
