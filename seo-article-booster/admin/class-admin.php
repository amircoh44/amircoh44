<?php
/**
 * Admin UI: menu pages, settings fields and asset loading.
 *
 * Registers a top-level "SEO Article Booster" menu with five screens:
 *   - Dashboard  : at-a-glance media, schema + linking status.
 *   - Image Audit: run a scan and list articles below the image minimum.
 *   - Schema Audit: list articles missing structured data.
 *   - Internal Links: sitemap status, index rebuild, bulk apply / revert.
 *   - Settings   : every configurable option.
 *
 * @package SeoArticleBooster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SAB_Admin
 */
class SAB_Admin {

	/**
	 * Capability required to view/use the admin pages.
	 */
	const CAP = 'manage_options';

	/**
	 * Services injected from the orchestrator.
	 *
	 * @var SAB_Image_Scanner
	 */
	protected $scanner;

	/**
	 * @var SAB_Schema_Scanner
	 */
	protected $schema;

	/**
	 * @var SAB_Link_Index
	 */
	protected $index;

	/**
	 * @var SAB_Link_Applier
	 */
	protected $applier;

	/**
	 * @var SAB_Sitemap_Parser
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
	 * @param SAB_Image_Scanner  $scanner Image scanner.
	 * @param SAB_Schema_Scanner $schema  Schema scanner.
	 * @param SAB_Link_Index     $index   Index.
	 * @param SAB_Link_Applier   $applier Applier.
	 * @param SAB_Sitemap_Parser $sitemap Sitemap parser.
	 */
	public function __construct( SAB_Image_Scanner $scanner, SAB_Schema_Scanner $schema, SAB_Link_Index $index, SAB_Link_Applier $applier, SAB_Sitemap_Parser $sitemap ) {
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
		$this->screens[] = add_menu_page(
			__( 'SEO Article Booster', 'seo-article-booster' ),
			__( 'SEO Booster', 'seo-article-booster' ),
			self::CAP,
			'sab-dashboard',
			array( $this, 'render_dashboard' ),
			'dashicons-admin-links',
			58
		);

		$this->screens[] = add_submenu_page(
			'sab-dashboard',
			__( 'Dashboard', 'seo-article-booster' ),
			__( 'Dashboard', 'seo-article-booster' ),
			self::CAP,
			'sab-dashboard',
			array( $this, 'render_dashboard' )
		);

		$this->screens[] = add_submenu_page(
			'sab-dashboard',
			__( 'Image Audit', 'seo-article-booster' ),
			__( 'Image Audit', 'seo-article-booster' ),
			self::CAP,
			'sab-images',
			array( $this, 'render_images' )
		);

		$this->screens[] = add_submenu_page(
			'sab-dashboard',
			__( 'Schema Audit', 'seo-article-booster' ),
			__( 'Schema Audit', 'seo-article-booster' ),
			self::CAP,
			'sab-schema',
			array( $this, 'render_schema' )
		);

		$this->screens[] = add_submenu_page(
			'sab-dashboard',
			__( 'Internal Links', 'seo-article-booster' ),
			__( 'Internal Links', 'seo-article-booster' ),
			self::CAP,
			'sab-links',
			array( $this, 'render_links' )
		);

		$this->screens[] = add_submenu_page(
			'sab-dashboard',
			__( 'Settings', 'seo-article-booster' ),
			__( 'Settings', 'seo-article-booster' ),
			self::CAP,
			'sab-settings',
			array( $this, 'render_settings' )
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
			'sab-admin',
			SAB_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			SAB_VERSION
		);

		wp_enqueue_script(
			'sab-admin',
			SAB_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			SAB_VERSION,
			true
		);

		wp_localize_script(
			'sab-admin',
			'SAB',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( SAB_Ajax::NONCE ),
				'i18n'    => array(
					'scanning'       => __( 'Scanning…', 'seo-article-booster' ),
					'schemaScanning' => __( 'Checking schema…', 'seo-article-booster' ),
					'applying'       => __( 'Applying links…', 'seo-article-booster' ),
					'reverting'      => __( 'Reverting links…', 'seo-article-booster' ),
					'working'        => __( 'Working…', 'seo-article-booster' ),
					'done'           => __( 'Done.', 'seo-article-booster' ),
					'error'          => __( 'Something went wrong. Please try again.', 'seo-article-booster' ),
					'confirmApply'   => __( 'This will permanently write internal links into your post content. You can revert later. Continue?', 'seo-article-booster' ),
					'confirmRevert'  => __( 'This will remove every link this plugin previously added to your content. Continue?', 'seo-article-booster' ),
					'noDeficient'    => __( 'Great — every scanned article meets the image minimum.', 'seo-article-booster' ),
					'noMissingSchema' => __( 'Great — every scanned article exposes the required structured data.', 'seo-article-booster' ),
					'flagged'        => __( 'flagged', 'seo-article-booster' ),
					'statusNone'     => __( 'No structured data', 'seo-article-booster' ),
					'statusInsufficient' => __( 'Missing required type', 'seo-article-booster' ),
					'statusError'    => __( 'Could not fetch', 'seo-article-booster' ),
					'edit'           => __( 'Edit', 'seo-article-booster' ),
					'view'           => __( 'View', 'seo-article-booster' ),
				),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Settings API fields
	 * ------------------------------------------------------------------- */

	/**
	 * Register settings sections + fields. The actual option registration lives
	 * in SAB_Settings::register(); here we just describe the form.
	 */
	public function register_settings_fields() {
		// Sections.
		add_settings_section( 'sab_images', __( 'Image minimum', 'seo-article-booster' ), array( $this, 'section_images' ), 'sab-settings' );
		add_settings_section( 'sab_schema', __( 'Schema (structured data)', 'seo-article-booster' ), array( $this, 'section_schema' ), 'sab-settings' );
		add_settings_section( 'sab_linking', __( 'Internal linking', 'seo-article-booster' ), array( $this, 'section_linking' ), 'sab-settings' );
		add_settings_section( 'sab_sitemap', __( 'Yoast sitemap', 'seo-article-booster' ), array( $this, 'section_sitemap' ), 'sab-settings' );
		add_settings_section( 'sab_newposts', __( 'New post awareness', 'seo-article-booster' ), array( $this, 'section_newposts' ), 'sab-settings' );

		// Image fields.
		$this->add_number_field( 'min_images', __( 'Minimum images per article', 'seo-article-booster' ), 'sab_images', __( 'Articles with fewer images than this are flagged in the audit and the editor.', 'seo-article-booster' ) );
		$this->add_checkbox_field( 'count_featured_image', __( 'Count the featured image', 'seo-article-booster' ), 'sab_images' );
		$this->add_post_types_field( 'audit_post_types', __( 'Audit these post types', 'seo-article-booster' ), 'sab_images' );

		// Schema fields.
		$this->add_checkbox_field( 'enable_schema_check', __( 'Audit articles for structured data', 'seo-article-booster' ), 'sab_schema' );
		$this->add_text_field( 'schema_required_types', __( 'Required schema types', 'seo-article-booster' ), 'sab_schema', __( 'Optional comma-separated list, e.g. Article, BlogPosting. Leave blank to accept any structured data.', 'seo-article-booster' ) );

		// Linking fields.
		$this->add_checkbox_field( 'enable_auto_linking', __( 'Enable automatic internal linking (display-time)', 'seo-article-booster' ), 'sab_linking' );
		$this->add_post_types_field( 'link_post_types', __( 'Link within these post types', 'seo-article-booster' ), 'sab_linking' );
		$this->add_number_field( 'max_links_per_post', __( 'Max links added per article', 'seo-article-booster' ), 'sab_linking' );
		$this->add_number_field( 'max_links_per_keyword', __( 'Max links per phrase (per article)', 'seo-article-booster' ), 'sab_linking' );
		$this->add_number_field( 'min_keyword_length', __( 'Ignore phrases shorter than (characters)', 'seo-article-booster' ), 'sab_linking' );
		$this->add_checkbox_field( 'use_title_as_keyword', __( 'Use post titles as linkable phrases', 'seo-article-booster' ), 'sab_linking' );
		$this->add_checkbox_field( 'use_yoast_focus_kw', __( 'Use Yoast focus keyword(s) as phrases', 'seo-article-booster' ), 'sab_linking' );
		$this->add_checkbox_field( 'skip_headings', __( 'Never link text inside headings', 'seo-article-booster' ), 'sab_linking' );
		$this->add_checkbox_field( 'case_sensitive', __( 'Case-sensitive matching', 'seo-article-booster' ), 'sab_linking' );
		$this->add_checkbox_field( 'open_new_tab', __( 'Open internal links in a new tab', 'seo-article-booster' ), 'sab_linking' );
		$this->add_checkbox_field( 'nofollow', __( 'Add rel="nofollow" to internal links', 'seo-article-booster' ), 'sab_linking' );

		// Sitemap fields.
		$this->add_text_field( 'sitemap_url', __( 'Sitemap index URL', 'seo-article-booster' ), 'sab_sitemap', sprintf( /* translators: %s: default sitemap URL. */ __( 'Leave blank to auto-detect %s', 'seo-article-booster' ), '<code>' . esc_html( home_url( '/sitemap_index.xml' ) ) . '</code>' ), 'url', home_url( '/sitemap_index.xml' ) );
		$this->add_checkbox_field( 'restrict_to_sitemap', __( 'Only link to URLs present in the sitemap', 'seo-article-booster' ), 'sab_sitemap' );

		// New-post fields.
		$this->add_checkbox_field( 'auto_link_new_posts', __( 'React when new content is published', 'seo-article-booster' ), 'sab_newposts' );
		$this->add_checkbox_field( 'auto_apply_inbound', __( 'Permanently add inbound links to older posts on publish', 'seo-article-booster' ), 'sab_newposts' );
		$this->add_number_field( 'inbound_apply_limit', __( 'Max older posts to edit per new post', 'seo-article-booster' ), 'sab_newposts' );
	}

	/** Section intros. */
	public function section_images() {
		echo '<p>' . esc_html__( 'Define how many images an article should contain. The plugin counts inline images, gallery blocks/shortcodes and (optionally) the featured image.', 'seo-article-booster' ) . '</p>';
	}
	public function section_schema() {
		echo '<p>' . esc_html__( 'Verify that your articles output Schema.org structured data (JSON-LD or microdata). The audit fetches each article and reports the schema types found.', 'seo-article-booster' ) . '</p>';
	}
	public function section_linking() {
		echo '<p>' . esc_html__( 'Control how related articles are linked. Matching uses each post’s title and/or Yoast focus keyword as the anchor phrase.', 'seo-article-booster' ) . '</p>';
	}
	public function section_sitemap() {
		echo '<p>' . esc_html__( 'The linkable URL set is taken from the Yoast SEO sitemap so only indexed, public pages are linked.', 'seo-article-booster' ) . '</p>';
	}
	public function section_newposts() {
		echo '<p>' . esc_html__( 'When a post or page is published, the link index is rebuilt so older related articles immediately link to the new URL. You can also bake those inbound links permanently into a limited number of older posts.', 'seo-article-booster' ) . '</p>';
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
			$label,
			function () use ( $key, $help ) {
				printf(
					'<input type="number" min="0" name="%1$s[%2$s]" id="%2$s" value="%3$s" class="small-text" />',
					esc_attr( SAB_OPTION_KEY ),
					esc_attr( $key ),
					esc_attr( SAB_Settings::get( $key ) )
				);
				if ( $help ) {
					echo '<p class="description">' . esc_html( $help ) . '</p>';
				}
			},
			'sab-settings',
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
			$label,
			function () use ( $key, $label ) {
				printf(
					'<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> %4$s</label>',
					esc_attr( SAB_OPTION_KEY ),
					esc_attr( $key ),
					checked( 1, (int) SAB_Settings::get( $key ), false ),
					esc_html( $label )
				);
			},
			'sab-settings',
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
	protected function add_text_field( $key, $label, $section, $help = '', $type = 'text', $placeholder = '' ) {
		add_settings_field(
			$key,
			$label,
			function () use ( $key, $help, $type, $placeholder ) {
				printf(
					'<input type="%1$s" name="%2$s[%3$s]" id="%3$s" value="%4$s" class="regular-text" placeholder="%5$s" />',
					esc_attr( 'url' === $type ? 'url' : 'text' ),
					esc_attr( SAB_OPTION_KEY ),
					esc_attr( $key ),
					esc_attr( SAB_Settings::get( $key ) ),
					esc_attr( $placeholder )
				);
				if ( $help ) {
					echo '<p class="description">' . wp_kses( $help, array( 'code' => array() ) ) . '</p>';
				}
			},
			'sab-settings',
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
			$label,
			function () use ( $key ) {
				$selected = (array) SAB_Settings::get( $key, array() );
				$types    = get_post_types( array( 'public' => true ), 'objects' );
				foreach ( $types as $type ) {
					if ( 'attachment' === $type->name ) {
						continue;
					}
					printf(
						'<label style="margin-right:1em;display:inline-block"><input type="checkbox" name="%1$s[%2$s][]" value="%3$s" %4$s /> %5$s</label>',
						esc_attr( SAB_OPTION_KEY ),
						esc_attr( $key ),
						esc_attr( $type->name ),
						checked( in_array( $type->name, $selected, true ), true, false ),
						esc_html( $type->labels->name )
					);
				}
			},
			'sab-settings',
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
			wp_die( esc_html__( 'You do not have permission to access this page.', 'seo-article-booster' ) );
		}

		// Variables made available to every view.
		$scanner = $this->scanner;
		$schema  = $this->schema;
		$index   = $this->index;
		$applier = $this->applier;
		$sitemap = $this->sitemap;

		require SAB_PLUGIN_DIR . 'admin/views/' . $view . '.php';
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
