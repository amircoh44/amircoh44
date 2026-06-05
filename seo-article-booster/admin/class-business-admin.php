<?php
/**
 * "Business Profile" admin screen — the questionnaire that powers schema.
 *
 * The profile option is registered with the Settings API so saving, the nonce
 * and the "Settings saved" notice are all handled by options.php.
 *
 * @package SeoArticleBooster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SAB_Business_Admin
 */
class SAB_Business_Admin {

	const CAP   = 'manage_options';
	const PAGE  = 'sab-business';
	const GROUP = 'sab_business_group';

	/**
	 * Screen hook.
	 *
	 * @var string
	 */
	protected $screen = '';

	/**
	 * Hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Register the submenu.
	 */
	public function register_menu() {
		$this->screen = add_submenu_page(
			'sab-dashboard',
			__( 'Business Profile', 'seo-article-booster' ),
			__( 'Business Profile', 'seo-article-booster' ),
			self::CAP,
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Register the profile option with sanitisation.
	 */
	public function register_setting() {
		register_setting(
			self::GROUP,
			SAB_Business_Profile::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'SAB_Business_Profile', 'sanitize' ),
				'default'           => SAB_Business_Profile::defaults(),
			)
		);
	}

	/**
	 * Enqueue the media uploader + assets on our screen.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue( $hook ) {
		if ( $hook !== $this->screen ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'sab-admin', SAB_PLUGIN_URL . 'admin/css/admin.css', array(), SAB_VERSION );
		wp_enqueue_script( 'sab-business', SAB_PLUGIN_URL . 'admin/js/business.js', array( 'jquery' ), SAB_VERSION, true );
		wp_localize_script(
			'sab-business',
			'SAB_BIZ',
			array(
				'choose' => __( 'Select image', 'seo-article-booster' ),
				'use'    => __( 'Use this image', 'seo-article-booster' ),
			)
		);
	}

	/**
	 * Render the page.
	 */
	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'seo-article-booster' ) );
		}
		require SAB_PLUGIN_DIR . 'admin/views/page-business.php';
	}
}
