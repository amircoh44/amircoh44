<?php
/**
 * Main plugin orchestrator.
 *
 * A single entry point (a classic singleton) that instantiates every service,
 * injects their dependencies, and registers their hooks. Keeping the wiring in
 * one place makes the data flow easy to follow:
 *
 *   sitemap  -> index -> { injector (display), applier (permanent) }
 *   scanner  -> admin image audit + minimum enforcement
 *   new-post -> rebuilds index / applies inbound links on publish
 *
 * @package SeoArticleBooster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SAB_Plugin
 */
final class SAB_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var SAB_Plugin|null
	 */
	protected static $instance = null;

	/**
	 * Service container.
	 *
	 * @var array<string,object>
	 */
	protected $services = array();

	/**
	 * Get (and lazily create) the single instance.
	 *
	 * @return SAB_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Private constructor — use instance().
	 */
	private function __construct() {}

	/**
	 * Instantiate services and register hooks.
	 */
	protected function boot() {
		// --- Build the object graph (dependencies first). -----------------.
		$sitemap  = new SAB_Sitemap_Parser();
		$replacer = new SAB_Link_Replacer();
		$index    = new SAB_Link_Index( $sitemap );
		$scanner  = new SAB_Image_Scanner();
		$schema   = new SAB_Schema_Scanner();
		$injector = new SAB_Link_Injector( $index, $replacer );
		$applier  = new SAB_Link_Applier( $index, $replacer );
		$new_post = new SAB_New_Post_Linker( $index, $applier );
		$rules    = new SAB_Injection_Rules();
		$distrib  = new SAB_Content_Distributor( $rules );
		$ajax     = new SAB_Ajax( $scanner, $schema, $index, $applier, $sitemap );

		$this->services = compact( 'sitemap', 'replacer', 'index', 'scanner', 'schema', 'injector', 'applier', 'new_post', 'rules', 'distrib', 'ajax' );

		// --- Register settings + i18n. ------------------------------------.
		add_action( 'admin_init', array( 'SAB_Settings', 'register' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// --- Front-end + content services. --------------------------------.
		$scanner->init();
		$schema->init();
		$injector->init();
		$new_post->init();
		$distrib->init();

		// --- Scheduled maintenance (see activation hook). -----------------.
		add_action( 'sab_rebuild_index_event', array( $index, 'rebuild' ) );
		add_action( 'sab_daily_refresh_event', array( $this, 'daily_refresh' ) );

		// --- Admin-only services. -----------------------------------------.
		if ( is_admin() ) {
			$ajax->init();

			if ( class_exists( 'SAB_Admin' ) ) {
				$admin = new SAB_Admin( $scanner, $schema, $index, $applier, $sitemap );
				$admin->init();
				$this->services['admin'] = $admin;
			}

			if ( class_exists( 'SAB_Distribution_Admin' ) ) {
				$dist_admin = new SAB_Distribution_Admin( $rules );
				$dist_admin->init();
				$this->services['dist_admin'] = $dist_admin;
			}

			// Convenience "Settings" link on the Plugins screen.
			add_filter( 'plugin_action_links_' . SAB_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
		}
	}

	/**
	 * Daily cron: refresh sitemap cache then rebuild the index.
	 */
	public function daily_refresh() {
		$this->services['sitemap']->get_all_urls( true );
		$this->services['index']->rebuild();
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'seo-article-booster',
			false,
			dirname( SAB_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Add a "Settings" link under the plugin name on the Plugins page.
	 *
	 * @param string[] $links Existing action links.
	 * @return string[]
	 */
	public function plugin_action_links( $links ) {
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=sab-settings' ) ),
			esc_html__( 'Settings', 'seo-article-booster' )
		);
		array_unshift( $links, $settings );
		return $links;
	}

	/**
	 * Fetch a service instance by key (e.g. 'index', 'scanner').
	 *
	 * @param string $key Service key.
	 * @return object|null
	 */
	public function service( $key ) {
		return isset( $this->services[ $key ] ) ? $this->services[ $key ] : null;
	}
}
