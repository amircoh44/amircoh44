<?php
/**
 * Admin screen for Content Distribution ("Sprinkler") rules.
 *
 * Provides a list of rules and an add/edit form, and handles create / update /
 * delete / toggle via nonce- and capability-protected admin-post actions.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_Distribution_Admin
 */
class SPR_Distribution_Admin {

	const CAP  = 'manage_options';
	const PAGE = 'spr-distribution';

	/**
	 * Rules service.
	 *
	 * @var SPR_Injection_Rules
	 */
	protected $rules;

	/**
	 * Our screen hook suffix.
	 *
	 * @var string
	 */
	protected $screen = '';

	/**
	 * Constructor.
	 *
	 * @param SPR_Injection_Rules $rules Rules service.
	 */
	public function __construct( SPR_Injection_Rules $rules ) {
		$this->rules = $rules;
	}

	/**
	 * Hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_spr_save_rule', array( $this, 'handle_save' ) );
		add_action( 'admin_post_spr_delete_rule', array( $this, 'handle_delete' ) );
		add_action( 'admin_post_spr_toggle_rule', array( $this, 'handle_toggle' ) );
	}

	/**
	 * Register the submenu under the SEO Sprinkler parent.
	 */
	public function register_menu() {
		$this->screen = add_submenu_page(
			'spr-dashboard',
			__( 'Content Distribution', 'seo-sprinkler' ),
			__( 'Content Distribution', 'seo-sprinkler' ),
			self::CAP,
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Enqueue assets on our screen only.
	 *
	 * @param string $hook Current screen hook.
	 */
	public function enqueue( $hook ) {
		if ( $hook !== $this->screen ) {
			return;
		}
		wp_enqueue_style( 'spr-admin', SPR_PLUGIN_URL . 'admin/css/admin.css', array(), SPR_VERSION );
		wp_enqueue_media(); // For the image picker.
		wp_enqueue_script( 'spr-distribution', SPR_PLUGIN_URL . 'admin/js/distribution.js', array( 'jquery' ), SPR_VERSION, true );
		wp_localize_script(
			'spr-distribution',
			'SPR_DIST',
			array(
				'frameTitle' => __( 'Select an image to distribute', 'seo-sprinkler' ),
				'useImage'   => __( 'Use this image', 'seo-sprinkler' ),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------- */

	/**
	 * Render the list or the edit form.
	 */
	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'seo-sprinkler' ) );
		}

		$rules  = $this->rules;
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification
		$notice = isset( $_GET['spr_notice'] ) ? sanitize_key( wp_unslash( $_GET['spr_notice'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		$editing = null;
		if ( 'edit' === $action ) {
			$id      = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
			$editing = $this->rules->get_rule( $id );
		}
		if ( 'new' === $action || ( 'edit' === $action && ! $editing ) ) {
			$editing = SPR_Injection_Rules::defaults();
			$action  = 'new';
		}

		require SPR_PLUGIN_DIR . 'admin/views/page-distribution.php';
	}

	/* ---------------------------------------------------------------------
	 * Form handlers
	 * ------------------------------------------------------------------- */

	/**
	 * Create or update a rule.
	 */
	public function handle_save() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'seo-sprinkler' ) );
		}
		check_admin_referer( 'spr_save_rule' );

		$data = isset( $_POST['rule'] ) ? (array) wp_unslash( $_POST['rule'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitized in save_rule().
		$id   = $this->rules->save_rule( $data );

		$this->redirect( array( 'spr_notice' => 'saved', 'action' => 'edit', 'id' => $id ) );
	}

	/**
	 * Delete a rule.
	 */
	public function handle_delete() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'seo-sprinkler' ) );
		}
		$id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		check_admin_referer( 'spr_delete_rule_' . $id );

		$this->rules->delete_rule( $id );
		$this->redirect( array( 'spr_notice' => 'deleted' ) );
	}

	/**
	 * Toggle a rule's enabled state.
	 */
	public function handle_toggle() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'seo-sprinkler' ) );
		}
		$id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		check_admin_referer( 'spr_toggle_rule_' . $id );

		$rule = $this->rules->get_rule( $id );
		if ( $rule ) {
			$rule['enabled'] = empty( $rule['enabled'] ) ? 1 : 0;
			$this->rules->save_rule( $rule );
		}
		$this->redirect( array( 'spr_notice' => 'toggled' ) );
	}

	/**
	 * Redirect back to our admin page with query args, then exit.
	 *
	 * @param array $args Extra query args.
	 */
	protected function redirect( $args ) {
		$url = add_query_arg( array_merge( array( 'page' => self::PAGE ), $args ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}
}
