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
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_Plugin
 */
final class SPR_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var SPR_Plugin|null
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
	 * @return SPR_Plugin
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
		$sitemap  = new SPR_Sitemap_Parser();
		$replacer = new SPR_Link_Replacer();
		$index    = new SPR_Link_Index( $sitemap );
		$scanner  = new SPR_Image_Scanner();
		$schema   = new SPR_Schema_Scanner( $sitemap );
		$schema_gen = new SPR_Schema_Generator();
		$injector = new SPR_Link_Injector( $index, $replacer );
		$applier  = new SPR_Link_Applier( $index, $replacer );
		$new_post = new SPR_New_Post_Linker( $index, $applier );
		$rules    = new SPR_Injection_Rules();
		$distrib  = new SPR_Content_Distributor( $rules );
		$link_scan = new SPR_Link_Scanner();
		$cleaner  = new SPR_Content_Cleaner();
		$heading  = new SPR_Heading_Checker();
		$filler   = new SPR_Image_Filler( $scanner, $link_scan, $schema, $heading );
		$syndication = new SPR_Syndication();
		$license  = new SPR_License_Client();
		$exporter = new SPR_Exporter();
		$gsc      = new SPR_GSC();
		$ajax     = new SPR_Ajax( $scanner, $schema, $index, $applier, $sitemap );

		$this->services = compact( 'sitemap', 'replacer', 'index', 'scanner', 'schema', 'schema_gen', 'injector', 'applier', 'new_post', 'rules', 'distrib', 'link_scan', 'cleaner', 'heading', 'filler', 'syndication', 'license', 'exporter', 'gsc', 'ajax' );

		// --- Register settings + i18n. ------------------------------------.
		add_action( 'admin_init', array( 'SPR_Settings', 'register' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// --- Front-end + content services. --------------------------------.
		$scanner->init();
		$schema->init();
		$schema_gen->init();
		$injector->init();
		$new_post->init();
		$syndication->init();
		$license->init();
		$distrib->init();
		$link_scan->init();
		$cleaner->init();
		$heading->init();
		$gsc->init();

		// --- Scheduled maintenance (see activation hook). -----------------.
		add_action( 'spr_rebuild_index_event', array( $index, 'rebuild' ) );
		add_action( 'spr_daily_refresh_event', array( $this, 'daily_refresh' ) );

		// --- Admin-only services. -----------------------------------------.
		if ( is_admin() ) {
			$ajax->init();
			$filler->init();

			if ( class_exists( 'SPR_Admin' ) ) {
				$admin = new SPR_Admin( $scanner, $schema, $index, $applier, $sitemap );
				$admin->init();
				$this->services['admin'] = $admin;
			}

			if ( class_exists( 'SPR_Distribution_Admin' ) ) {
				$dist_admin = new SPR_Distribution_Admin( $rules );
				$dist_admin->init();
				$this->services['dist_admin'] = $dist_admin;
			}

			if ( class_exists( 'SPR_Link_Audit_Admin' ) ) {
				$link_admin = new SPR_Link_Audit_Admin( $link_scan );
				$link_admin->init();
				$this->services['link_admin'] = $link_admin;
			}

			if ( class_exists( 'SPR_Cleaner_Admin' ) ) {
				$cleaner_admin = new SPR_Cleaner_Admin( $cleaner );
				$cleaner_admin->init();
				$this->services['cleaner_admin'] = $cleaner_admin;
			}

			if ( class_exists( 'SPR_Business_Admin' ) ) {
				$business_admin = new SPR_Business_Admin();
				$business_admin->init();
				$this->services['business_admin'] = $business_admin;
			}

			if ( class_exists( 'SPR_Export_Admin' ) ) {
				$export_admin = new SPR_Export_Admin( $exporter );
				$export_admin->init();
				$this->services['export_admin'] = $export_admin;
			}

			if ( class_exists( 'SPR_Image_Distribution_Admin' ) ) {
				$imgdist_admin = new SPR_Image_Distribution_Admin( $scanner, $filler );
				$imgdist_admin->init();
				$this->services['imgdist_admin'] = $imgdist_admin;
			}

			if ( class_exists( 'SPR_GSC_Admin' ) ) {
				$gsc_admin = new SPR_GSC_Admin( $gsc );
				$gsc_admin->init();
				$this->services['gsc_admin'] = $gsc_admin;
			}

			// Convenience "Settings" link on the Plugins screen.
			add_filter( 'plugin_action_links_' . SPR_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
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
			'seo-sprinkler',
			false,
			dirname( SPR_PLUGIN_BASENAME ) . '/languages'
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
			esc_url( admin_url( 'admin.php?page=spr-settings' ) ),
			esc_html__( 'Settings', 'seo-sprinkler' )
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
