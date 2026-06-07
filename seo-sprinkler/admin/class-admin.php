<?php
/**
 * Admin UI: menu pages, settings fields and asset loading.
 *
 * Registers a top-level "SEO Sprinkler" menu with five screens:
 *   - Dashboard  : at-a-glance media, schema + linking status.
 *   - Image Audit: run a scan and list articles below the image minimum.
 *   - Schema Audit: list articles missing structured data.
 *   - Internal Links: sitemap status, index rebuild, bulk apply / revert.
 *   - Settings   : every configurable option.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_Admin
 */
class SPR_Admin {

	/**
	 * Capability required to view/use the admin pages.
	 */
	const CAP = 'manage_options';

	/**
	 * Services injected from the orchestrator.
	 *
	 * @var SPR_Image_Scanner
	 */
	protected $scanner;

	/**
	 * @var SPR_Schema_Scanner
	 */
	protected $schema;

	/**
	 * @var SPR_Link_Index
	 */
	protected $index;

	/**
	 * @var SPR_Link_Applier
	 */
	protected $applier;

	/**
	 * @var SPR_Sitemap_Parser
	 */
	protected $sitemap;

	/**
	 * Hook suffixes for our screens (used to scope asset loading).
	 *
	 * @var string[]
	 */
	protected $screens = array();

	/**
	 * Constructor.
	 *
	 * @param SPR_Image_Scanner  $scanner Image scanner.
	 * @param SPR_Schema_Scanner $schema  Schema scanner.
	 * @param SPR_Link_Index     $index   Index.
	 * @param SPR_Link_Applier   $applier Applier.
	 * @param SPR_Sitemap_Parser $sitemap Sitemap parser.
	 */
	public function __construct( SPR_Image_Scanner $scanner, SPR_Schema_Scanner $schema, SPR_Link_Index $index, SPR_Link_Applier $applier, SPR_Sitemap_Parser $sitemap ) {
		$this->scanner = $scanner;
		$this->schema  = $schema;
		$this->index   = $index;
		$this->applier = $applier;
		$this->sitemap = $sitemap;
	}

	/**
	 * Register admin hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings_fields' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/* ---------------------------------------------------------------------
	 * Menu
	 * ------------------------------------------------------------------- */

	/**
	 * Register the top-level menu and sub-pages.
	 */
	public function register_menu() {
		// A custom "boost" (rocket) SVG icon, coloured to match the admin menu.
		$svg  = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#a7aaad" d="M14.6 1.6c-2.6.3-4.8 1.6-6.6 3.6L6.7 6.8 4 6.3c-.4-.1-.8 0-1.1.3L1 8.6c-.4.4-.3 1 .2 1.2l2.4 1.1c-.1.4-.1.8 0 1.2l-.6.6c-.3.3-.3.9 0 1.2l1.5 1.5c.3.3.9.3 1.2 0l.6-.6c.4.1.8.1 1.2 0l1.1 2.4c.2.5.8.6 1.2.2l2-1.9c.3-.3.4-.7.3-1.1l-.5-2.7 1.6-1.3c2-1.8 3.3-4 3.6-6.6.1-.9.1-1.8 0-2.6-.9-.2-1.8-.2-2.6-.1zM13 7.5a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zM3.3 15.1c-.8.3-1.5 1.1-1.7 3.3 2.2-.2 3-.9 3.3-1.7.3-.8-.8-2-1.6-1.6z"/></svg>';
		$icon = 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- inline SVG icon.

		$this->screens[] = add_menu_page(
			__( 'SEO Sprinkler', 'seo-sprinkler' ),
			__( 'SEO Sprinkler', 'seo-sprinkler' ),
			self::CAP,
			'spr-dashboard',
			array( $this, 'render_dashboard' ),
			$icon,
			22 // Just below Pages, in the content area of the admin menu.
		);

		$this->screens[] = add_submenu_page(
			'spr-dashboard',
			__( 'Dashboard', 'seo-sprinkler' ),
			__( 'Dashboard', 'seo-sprinkler' ),
			self::CAP,
			'spr-dashboard',
			array( $this, 'render_dashboard' ),
			0
		);

		$this->screens[] = add_submenu_page(
			'spr-dashboard',
			__( 'Image Audit', 'seo-sprinkler' ),
			__( 'Image Audit', 'seo-sprinkler' ),
			self::CAP,
			'spr-images',
			array( $this, 'render_images' ),
			10
		);

		$this->screens[] = add_submenu_page(
			'spr-dashboard',
			__( 'Schema Audit', 'seo-sprinkler' ),
			__( 'Schema Audit', 'seo-sprinkler' ),
			self::CAP,
			'spr-schema',
			array( $this, 'render_schema' ),
			30
		);

		$this->screens[] = add_submenu_page(
			'spr-dashboard',
			__( 'Internal Links', 'seo-sprinkler' ),
			__( 'Internal Links', 'seo-sprinkler' ),
			self::CAP,
			'spr-links',
			array( $this, 'render_links' ),
			21
		);

		$this->screens[] = add_submenu_page(
			'spr-dashboard',
			__( 'Settings', 'seo-sprinkler' ),
			__( 'Settings', 'seo-sprinkler' ),
			self::CAP,
			'spr-settings',
			array( $this, 'render_settings' ),
			80
		);
	}

	/* ---------------------------------------------------------------------
	 * Assets
	 * ------------------------------------------------------------------- */

	/**
	 * Enqueue CSS/JS only on our own screens.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! in_array( $hook_suffix, $this->screens, true ) ) {
			return;
		}

		wp_enqueue_style(
			'spr-admin',
			SPR_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			SPR_VERSION
		);

		wp_enqueue_script(
			'spr-admin',
			SPR_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			SPR_VERSION,
			true
		);

		wp_localize_script(
			'spr-admin',
			'SPR',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( SPR_Ajax::NONCE ),
				'i18n'    => array(
					'scanning'       => __( 'Scanning…', 'seo-sprinkler' ),
					'schemaScanning' => __( 'Checking schema…', 'seo-sprinkler' ),
					'applying'       => __( 'Applying links…', 'seo-sprinkler' ),
					'reverting'      => __( 'Reverting links…', 'seo-sprinkler' ),
					'working'        => __( 'Working…', 'seo-sprinkler' ),
					'done'           => __( 'Done.', 'seo-sprinkler' ),
					'error'          => __( 'Something went wrong. Please try again.', 'seo-sprinkler' ),
					'confirmApply'   => __( 'This will permanently write internal links into your post content. You can revert later. Continue?', 'seo-sprinkler' ),
					'confirmRevert'  => __( 'This will remove every link this plugin previously added to your content. Continue?', 'seo-sprinkler' ),
					'noDeficient'    => __( 'Great — every scanned article meets the image minimum.', 'seo-sprinkler' ),
					/* translators: %d: number of flagged articles. */
					'jumpDistribute' => __( 'Found %d article(s) below the image minimum. Jump to Image Distribution to fill them with library images now?', 'seo-sprinkler' ),
					'noMissingSchema' => __( 'Great — every scanned article exposes the required structured data.', 'seo-sprinkler' ),
					'flagged'        => __( 'flagged', 'seo-sprinkler' ),
					'statusNone'     => __( 'No structured data', 'seo-sprinkler' ),
					'statusInsufficient' => __( 'Missing required type', 'seo-sprinkler' ),
					'statusError'    => __( 'Could not fetch', 'seo-sprinkler' ),
					'schemaOk'       => __( 'OK', 'seo-sprinkler' ),
					'noSitemapUrls'  => __( 'No URLs were found in the sitemap(s).', 'seo-sprinkler' ),
					'edit'           => __( 'Edit', 'seo-sprinkler' ),
					'view'           => __( 'View', 'seo-sprinkler' ),
					'testGoogle'     => __( 'Test on Google', 'seo-sprinkler' ),
				),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Settings API fields
	 * ------------------------------------------------------------------- */

	/**
	 * Register settings sections + fields. The actual option registration lives
	 * in SPR_Settings::register(); here we just describe the form.
	 */
	public function register_settings_fields() {
		// Sections.
		add_settings_section( 'spr_license', __( 'License &amp; edition', 'seo-sprinkler' ), array( $this, 'section_license' ), $this->page_for( 'spr_license' ) );
		$this->add_text_field( 'license_key', __( 'License key', 'seo-sprinkler' ), 'spr_license', __( 'Paste your Pro/Expert license key to unlock premium features.', 'seo-sprinkler' ) );
		$this->add_text_field( 'license_server_url', __( 'License server URL', 'seo-sprinkler' ), 'spr_license', __( 'Base URL of your SEO Sprinkler license server, e.g. https://license.example.com (or define SPR_LICENSE_SERVER). Leave blank if you activate keys another way.', 'seo-sprinkler' ) );

		add_settings_section( 'spr_ai', __( 'AI (bring your own API)', 'seo-sprinkler' ) . ' ' . SPR_Edition::pill( 'pro' ), array( $this, 'section_ai' ), $this->page_for( 'spr_ai' ) );
		$this->add_text_field( 'ai_endpoint', __( 'API endpoint', 'seo-sprinkler' ), 'spr_ai', __( 'Any OpenAI-compatible /chat/completions URL (OpenAI, OpenRouter, Azure, local LLM…).', 'seo-sprinkler' ), 'url', 'https://api.openai.com/v1/chat/completions' );
		$this->add_text_field( 'ai_key', __( 'API key', 'seo-sprinkler' ), 'spr_ai', __( 'Stored on your site only — never sent anywhere except your chosen endpoint.', 'seo-sprinkler' ) );
		$this->add_text_field( 'ai_model', __( 'Model', 'seo-sprinkler' ), 'spr_ai', '', 'text', 'gpt-4o-mini' );

		add_settings_section( 'spr_images', __( 'Image minimum', 'seo-sprinkler' ), array( $this, 'section_images' ), $this->page_for( 'spr_images' ) );
		add_settings_section( 'spr_schema', __( 'Schema (structured data)', 'seo-sprinkler' ), array( $this, 'section_schema' ), $this->page_for( 'spr_schema' ) );
		add_settings_section( 'spr_distribution', __( 'Content distribution (Sprinkler)', 'seo-sprinkler' ) . ' ' . SPR_Edition::pill( 'pro' ), array( $this, 'section_distribution' ), $this->page_for( 'spr_distribution' ) );
		add_settings_section( 'spr_cleaner', __( 'Content cleaner (generative junk)', 'seo-sprinkler' ) . ' ' . SPR_Edition::pill( 'pro' ), array( $this, 'section_cleaner' ), $this->page_for( 'spr_cleaner' ) );
		add_settings_section( 'spr_syndication', __( 'Syndication', 'seo-sprinkler' ) . ' ' . SPR_Edition::pill( 'expert' ), array( $this, 'section_syndication' ), $this->page_for( 'spr_syndication' ) );
		add_settings_section( 'spr_linking', __( 'Internal linking', 'seo-sprinkler' ) . ' ' . SPR_Edition::pill( 'pro' ), array( $this, 'section_linking' ), $this->page_for( 'spr_linking' ) );
		add_settings_section( 'spr_sitemap', __( 'Yoast sitemap', 'seo-sprinkler' ), array( $this, 'section_sitemap' ), $this->page_for( 'spr_sitemap' ) );
		add_settings_section( 'spr_newposts', __( 'New post awareness', 'seo-sprinkler' ), array( $this, 'section_newposts' ), $this->page_for( 'spr_newposts' ) );

		// Image fields.
		$this->add_number_field( 'min_images', __( 'Minimum images per article', 'seo-sprinkler' ), 'spr_images', __( 'Articles with fewer images than this are flagged in the audit and the editor.', 'seo-sprinkler' ) );
		$this->add_checkbox_field( 'count_featured_image', __( 'Count the featured image', 'seo-sprinkler' ), 'spr_images' );
		$this->add_post_types_field( 'audit_post_types', __( 'Audit these post types', 'seo-sprinkler' ), 'spr_images' );

		// Schema fields.
		$this->add_checkbox_field( 'enable_schema_check', __( 'Audit articles for structured data', 'seo-sprinkler' ), 'spr_schema' );
		$this->add_text_field( 'schema_required_types', __( 'Required schema types', 'seo-sprinkler' ), 'spr_schema', __( 'Optional comma-separated list, e.g. Article, BlogPosting. Leave blank to accept any structured data.', 'seo-sprinkler' ) );
		$this->add_checkbox_field( 'enable_schema_output', __( 'Output this plugin’s JSON-LD schema in <head>', 'seo-sprinkler' ), 'spr_schema' );
		$this->add_text_field( 'service_post_type', __( 'Service post type', 'seo-sprinkler' ), 'spr_schema', __( 'Post type mapped to Service schema (default: service).', 'seo-sprinkler' ), 'text', 'service' );

		// Content distribution fields.
		$this->add_checkbox_field( 'enable_distribution', __( 'Enable rule-based content distribution', 'seo-sprinkler' ), 'spr_distribution' );

		// Content cleaner fields (one checkbox per cleanup, plus auto-clean).
		if ( class_exists( 'SPR_Content_Cleaner' ) ) {
			foreach ( SPR_Content_Cleaner::cleanups() as $key => $label ) {
				$this->add_checkbox_field( $key, $label, 'spr_cleaner' );
			}
		}
		$this->add_checkbox_field( 'cleaner_autosave', __( 'Auto-clean content every time a post is saved', 'seo-sprinkler' ), 'spr_cleaner' );

		// Syndication fields.
		$this->add_checkbox_field( 'syndicate_enabled', __( 'Push newly published posts to webhooks', 'seo-sprinkler' ), 'spr_syndication' );
		$this->add_textarea_field( 'syndicate_webhooks', __( 'Webhook URLs', 'seo-sprinkler' ), 'spr_syndication', __( 'One URL per line. Point these at Zapier / Make / n8n / IFTTT, which post to Google Business Profile, Facebook, LinkedIn, X, etc.', 'seo-sprinkler' ) );
		$this->add_post_types_field( 'syndicate_post_types', __( 'Syndicate these post types', 'seo-sprinkler' ), 'spr_syndication' );

		// Linking fields.
		$this->add_checkbox_field( 'enable_auto_linking', __( 'Enable automatic internal linking (display-time)', 'seo-sprinkler' ), 'spr_linking' );
		$this->add_post_types_field( 'link_post_types', __( 'Link within these post types', 'seo-sprinkler' ), 'spr_linking' );
		$this->add_number_field( 'max_links_per_post', __( 'Max links added per article', 'seo-sprinkler' ), 'spr_linking' );
		$this->add_number_field( 'max_links_per_keyword', __( 'Max links per phrase (per article)', 'seo-sprinkler' ), 'spr_linking' );
		$this->add_number_field( 'min_keyword_length', __( 'Ignore phrases shorter than (characters)', 'seo-sprinkler' ), 'spr_linking' );
		$this->add_checkbox_field( 'use_title_as_keyword', __( 'Use post titles as linkable phrases', 'seo-sprinkler' ), 'spr_linking' );
		$this->add_checkbox_field( 'use_yoast_focus_kw', __( 'Use Yoast focus keyword(s) as phrases', 'seo-sprinkler' ), 'spr_linking' );
		$this->add_checkbox_field( 'skip_headings', __( 'Never link text inside headings', 'seo-sprinkler' ), 'spr_linking' );
		$this->add_checkbox_field( 'case_sensitive', __( 'Case-sensitive matching', 'seo-sprinkler' ), 'spr_linking' );
		$this->add_checkbox_field( 'open_new_tab', __( 'Open internal links in a new tab', 'seo-sprinkler' ), 'spr_linking' );
		$this->add_checkbox_field( 'nofollow', __( 'Add rel="nofollow" to internal links', 'seo-sprinkler' ), 'spr_linking' );

		// Sitemap fields.
		$this->add_text_field( 'sitemap_url', __( 'Primary sitemap URL', 'seo-sprinkler' ), 'spr_sitemap', sprintf( /* translators: %s: default sitemap URL. */ __( 'Leave blank to auto-detect %s', 'seo-sprinkler' ), '<code>' . esc_html( home_url( '/sitemap_index.xml' ) ) . '</code>' ), 'url', home_url( '/sitemap_index.xml' ) );
		$this->add_textarea_field( 'sitemap_urls', __( 'Additional sitemaps', 'seo-sprinkler' ), 'spr_sitemap', __( 'One sitemap URL per line. Each may be a sitemap index or a flat urlset. All are merged and de-duplicated.', 'seo-sprinkler' ) );
		$this->add_checkbox_field( 'restrict_to_sitemap', __( 'Only link to URLs present in the sitemap(s)', 'seo-sprinkler' ), 'spr_sitemap' );

		// New-post fields.
		$this->add_checkbox_field( 'auto_link_new_posts', __( 'React when new content is published', 'seo-sprinkler' ), 'spr_newposts' );
		$this->add_checkbox_field( 'auto_apply_inbound', __( 'Permanently add inbound links to older posts on publish', 'seo-sprinkler' ), 'spr_newposts' );
		$this->add_number_field( 'inbound_apply_limit', __( 'Max older posts to edit per new post', 'seo-sprinkler' ), 'spr_newposts' );
	}

	/**
	 * Settings tabs (slug => label), in nav order.
	 *
	 * @return array<string,string>
	 */
	public static function settings_tabs() {
		return array(
			'license'     => __( 'License', 'seo-sprinkler' ),
			'linking'     => __( 'Internal links', 'seo-sprinkler' ),
			'content'     => __( 'Images &amp; schema', 'seo-sprinkler' ),
			'business'    => __( 'Business profile', 'seo-sprinkler' ),
			'tools'       => __( 'Content tools', 'seo-sprinkler' ),
			'ai'          => __( 'AI', 'seo-sprinkler' ),
			'syndication' => __( 'Syndication', 'seo-sprinkler' ),
		);
	}

	/**
	 * Which tab a settings section belongs to.
	 *
	 * @param string $section Section id.
	 * @return string Tab slug.
	 */
	public function tab_for_section( $section ) {
		$map = array(
			'spr_license'      => 'license',
			'spr_linking'      => 'linking',
			'spr_sitemap'      => 'linking',
			'spr_newposts'     => 'linking',
			'spr_images'       => 'content',
			'spr_schema'       => 'content',
			'spr_distribution' => 'tools',
			'spr_cleaner'      => 'tools',
			'spr_ai'           => 'ai',
			'spr_syndication'  => 'syndication',
		);
		return isset( $map[ $section ] ) ? $map[ $section ] : 'license';
	}

	/**
	 * The settings-page slug a section (and its fields) render under — one per tab.
	 *
	 * @param string $section Section id.
	 * @return string
	 */
	public function page_for( $section ) {
		return 'spr-settings-' . $this->tab_for_section( $section );
	}

	/** Section intros — generous, plain-language explanations for each tab. */
	public function section_ai() {
		echo '<p>' . esc_html__( 'Bring your own AI provider — any OpenAI-compatible chat endpoint (OpenAI, OpenRouter, Azure OpenAI, or a local model). Your API key is stored only in your own database and is sent only to the endpoint you enter below — never to us. It powers the optional AI buttons in the editor, such as generating a meta description or an SEO title from your content. Leave the key blank to keep AI features off.', 'seo-sprinkler' ) . '</p>';
	}
	public function section_license() {
		echo '<p>' . esc_html__( 'SEO Sprinkler is free to use, with optional Pro and Expert tiers that add automation, AI, full JSON-LD schema output and whole-site migration. Everything is unlocked free while a site stays under the free page limit; beyond that, paste a key here to unlock premium features. On sites you own you can also activate with the SPR_EDITION constant or your own license server (below).', 'seo-sprinkler' ) . '</p>';
		printf(
			'<p>%s <strong>%s</strong>. <a href="%s" target="_blank" rel="noopener">%s</a></p>',
			esc_html__( 'Current edition:', 'seo-sprinkler' ),
			esc_html( SPR_Edition::label() ),
			esc_url( SPR_Edition::upgrade_url() ),
			esc_html__( 'Compare Free / Pro / Expert →', 'seo-sprinkler' )
		);
	}
	public function section_images() {
		echo '<p>' . esc_html__( 'Strong articles are not walls of text. Set the minimum number of images you expect per article and SEO Sprinkler flags anything that falls short — in the audit and right inside the editor. It counts inline images, gallery blocks and shortcodes, and (optionally) the featured image, so the number reflects what a reader actually sees.', 'seo-sprinkler' ) . '</p>';
	}
	public function section_schema() {
		echo '<p>' . esc_html__( 'Structured data (Schema.org) is how Google understands what each page is — an Article, a LocalBusiness, a Service, and so on. This audit fetches each published article and reports exactly which schema types it finds in the rendered HTML (JSON-LD or microdata), so you can spot pages that are missing it. The plugin can also output its own complete JSON-LD graph built from your Business Profile.', 'seo-sprinkler' ) . '</p>';

		// Warn about a schema clash if another SEO plugin is active and we also output schema.
		$others = class_exists( 'SPR_SEO_Detector' ) ? SPR_SEO_Detector::active() : array();
		if ( ! empty( $others ) && (int) SPR_Settings::get( 'enable_schema_output' ) ) {
			$names = array();
			foreach ( $others as $o ) {
				$names[] = $o['name'];
			}
			echo '<div class="notice notice-warning inline" style="margin:8px 0 0"><p>' . wp_kses_post(
				sprintf(
					/* translators: %s: detected SEO plugin name(s). */
					__( '<strong>Possible schema clash:</strong> %s is active and most likely outputs its own JSON-LD too. Two schema generators on one page can confuse search engines. Either turn off the other plugin\'s schema — <em>Rank Math → Titles &amp; Meta → (each type) → Schema</em>, <em>Yoast → Settings → Site features → Schema</em>, or <em>AIOSEO → Search Appearance</em> — or untick "Output this plugin\'s JSON-LD schema in &lt;head&gt;" below.', 'seo-sprinkler' ),
					esc_html( implode( ', ', $names ) )
				)
			) . '</p></div>';
		}
	}
	public function section_distribution() {
		printf(
			'<p>%s <a href="%s">%s</a></p>',
			esc_html__( 'Inject a shortcode (such as an Elementor template), an image, or custom HTML into the articles you choose — matched by tag, category or keyword, and placed exactly where you want around headings and paragraphs. Your stored content is never modified; the insert happens at display time, so you can change or remove it any time. Build and manage the rules on the dedicated screen:', 'seo-sprinkler' ),
			esc_url( admin_url( 'admin.php?page=' . SPR_Distribution_Admin::PAGE ) ),
			esc_html__( 'Content Distribution →', 'seo-sprinkler' )
		);
	}
	public function section_syndication() {
		echo '<p>' . esc_html__( 'Reach more people automatically: whenever you publish a new post, SEO Sprinkler can POST it to one or more webhook URLs. Point those at Zapier, Make, n8n or IFTTT to fan the post out to Google Business Profile, Facebook, LinkedIn, X and more — no manual reposting. Available on the Expert edition, and on any site within the free page limit.', 'seo-sprinkler' ) . '</p>';
	}
	public function section_cleaner() {
		printf(
			'<p>%s <a href="%s">%s</a></p>',
			esc_html__( 'Pasted or AI-generated content often carries invisible junk — markdown code fences, zero-width characters, empty paragraphs, Word/Office cruft and more. Choose what to strip; cleaning leaves plain, valid markup (no CSS, no inline styles) and never touches your links. Every change is logged with a per-post backup and a one-click revert. Run a scan or clean from:', 'seo-sprinkler' ),
			esc_url( admin_url( 'admin.php?page=' . ( class_exists( 'SPR_Cleaner_Admin' ) ? SPR_Cleaner_Admin::PAGE : 'spr-cleaner' ) ) ),
			esc_html__( 'Content Cleaner →', 'seo-sprinkler' )
		);
	}
	public function section_linking() {
		echo '<p>' . esc_html__( 'Internal links guide readers through your site and spread ranking signal between related pages. SEO Sprinkler finds phrases in your content — each post title and/or its Yoast focus keyword — and turns matches into links to the right page. By default this happens at display time, so nothing is written to your posts, and the caps below keep it tasteful (limit links per article and per phrase).', 'seo-sprinkler' ) . '</p>';
	}
	public function section_sitemap() {
		echo '<p>' . esc_html__( 'So links only ever point at real, indexable pages, SEO Sprinkler reads your XML sitemap and links only to URLs it lists. Add more sitemaps if your site has several — they are merged and de-duplicated. If no sitemap is found, the plugin falls back to all published posts and says so on the Dashboard.', 'seo-sprinkler' ) . '</p>';
	}
	public function section_newposts() {
		echo '<p>' . esc_html__( 'Fresh content should not sit unlinked. When you publish a post or page, SEO Sprinkler rebuilds its link index so older, related articles start linking to the new URL right away. Optionally it can bake those inbound links permanently into a limited number of older posts, giving a new page an immediate internal-link boost.', 'seo-sprinkler' ) . '</p>';
	}

	/* --- Field renderers ------------------------------------------------- */

	/**
	 * Render a number input field.
	 *
	 * @param string $key     Setting key.
	 * @param string $label   Field label.
	 * @param string $section Section id.
	 * @param string $help    Optional help text.
	 */
	protected function add_number_field( $key, $label, $section, $help = '' ) {
		add_settings_field(
			$key,
			esc_html( $label ),
			function () use ( $key, $help ) {
				printf(
					'<input type="number" min="0" name="%1$s[%2$s]" id="%2$s" value="%3$s" class="small-text" />',
					esc_attr( SPR_OPTION_KEY ),
					esc_attr( $key ),
					esc_attr( SPR_Settings::get( $key ) )
				);
				if ( $help ) {
					echo '<p class="description">' . esc_html( $help ) . '</p>';
				}
			},
			$this->page_for( $section ),
			$section,
			array( 'label_for' => $key )
		);
	}

	/**
	 * Render a checkbox field.
	 *
	 * @param string $key     Setting key.
	 * @param string $label   Field label.
	 * @param string $section Section id.
	 */
	protected function add_checkbox_field( $key, $label, $section ) {
		add_settings_field(
			$key,
			esc_html( $label ),
			function () use ( $key, $label ) {
				printf(
					'<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> %4$s</label>',
					esc_attr( SPR_OPTION_KEY ),
					esc_attr( $key ),
					checked( 1, (int) SPR_Settings::get( $key ), false ),
					esc_html( $label )
				);
			},
			$this->page_for( $section ),
			$section
		);
	}

	/**
	 * Render a text/url input field.
	 *
	 * @param string $key         Setting key.
	 * @param string $label       Field label.
	 * @param string $section     Section id.
	 * @param string $help        Optional help (may contain safe <code> HTML).
	 * @param string $type        Input type ('text' or 'url').
	 * @param string $placeholder Optional placeholder text.
	 */
	protected function add_textarea_field( $key, $label, $section, $help = '' ) {
		add_settings_field(
			$key,
			esc_html( $label ),
			function () use ( $key, $help ) {
				printf(
					'<textarea name="%1$s[%2$s]" id="%2$s" rows="4" class="large-text code">%3$s</textarea>',
					esc_attr( SPR_OPTION_KEY ),
					esc_attr( $key ),
					esc_textarea( SPR_Settings::get( $key ) )
				);
				if ( $help ) {
					echo '<p class="description">' . esc_html( $help ) . '</p>';
				}
			},
			$this->page_for( $section ),
			$section,
			array( 'label_for' => $key )
		);
	}

	/**
	 * Render a text/url input field.
	 *
	 * @param string $key         Setting key.
	 * @param string $label       Field label.
	 * @param string $section     Section id.
	 * @param string $help        Optional help (may contain safe <code> HTML).
	 * @param string $type        Input type ('text' or 'url').
	 * @param string $placeholder Optional placeholder.
	 */
	protected function add_text_field( $key, $label, $section, $help = '', $type = 'text', $placeholder = '' ) {
		add_settings_field(
			$key,
			esc_html( $label ),
			function () use ( $key, $help, $type, $placeholder ) {
				printf(
					'<input type="%1$s" name="%2$s[%3$s]" id="%3$s" value="%4$s" class="regular-text" placeholder="%5$s" />',
					esc_attr( 'url' === $type ? 'url' : 'text' ),
					esc_attr( SPR_OPTION_KEY ),
					esc_attr( $key ),
					esc_attr( SPR_Settings::get( $key ) ),
					esc_attr( $placeholder )
				);
				if ( $help ) {
					echo '<p class="description">' . wp_kses( $help, array( 'code' => array() ) ) . '</p>';
				}
			},
			$this->page_for( $section ),
			$section,
			array( 'label_for' => $key )
		);
	}

	/**
	 * Render a list of public post-type checkboxes.
	 *
	 * @param string $key     Setting key.
	 * @param string $label   Field label.
	 * @param string $section Section id.
	 */
	protected function add_post_types_field( $key, $label, $section ) {
		add_settings_field(
			$key,
			esc_html( $label ),
			function () use ( $key ) {
				$selected = (array) SPR_Settings::get( $key, array() );
				$types    = get_post_types( array( 'public' => true ), 'objects' );
				foreach ( $types as $type ) {
					if ( 'attachment' === $type->name ) {
						continue;
					}
					printf(
						'<label style="margin-right:1em;display:inline-block"><input type="checkbox" name="%1$s[%2$s][]" value="%3$s" %4$s /> %5$s</label>',
						esc_attr( SPR_OPTION_KEY ),
						esc_attr( $key ),
						esc_attr( $type->name ),
						checked( in_array( $type->name, $selected, true ), true, false ),
						esc_html( $type->labels->name )
					);
				}
			},
			$this->page_for( $section ),
			$section
		);
	}

	/* ---------------------------------------------------------------------
	 * Page renderers (delegate to view partials)
	 * ------------------------------------------------------------------- */

	/**
	 * Guard + include a view file with shared data in scope.
	 *
	 * @param string $view View filename (without extension).
	 */
	protected function view( $view ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'seo-sprinkler' ) );
		}

		// Variables made available to every view.
		$scanner = $this->scanner;
		$schema  = $this->schema;
		$index   = $this->index;
		$applier = $this->applier;
		$sitemap = $this->sitemap;

		require SPR_PLUGIN_DIR . 'admin/views/' . $view . '.php';
	}

	/** Render the dashboard page. */
	public function render_dashboard() {
		$this->view( 'page-dashboard' );
	}

	/** Render the image audit page. */
	public function render_images() {
		$this->view( 'page-images' );
	}

	/** Render the schema audit page. */
	public function render_schema() {
		$this->view( 'page-schema' );
	}

	/** Render the internal links page. */
	public function render_links() {
		$this->view( 'page-links' );
	}

	/** Render the settings page. */
	public function render_settings() {
		$this->view( 'page-settings' );
	}
}
