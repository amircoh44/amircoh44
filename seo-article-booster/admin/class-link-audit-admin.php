<?php
/**
 * "Link Audit" admin screen.
 *
 * Lists every linkable post with its internal/external link counts, and a
 * per-post detail view listing all links with an inline mini-WYSIWYG editor to
 * change a link's text and URL and save it straight to the post content.
 *
 * @package SeoArticleBooster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SAB_Link_Audit_Admin
 */
class SAB_Link_Audit_Admin {

	const CAP   = 'manage_options';
	const PAGE  = 'sab-link-audit';
	const NONCE = 'sab_link_audit';

	/**
	 * Link scanner.
	 *
	 * @var SAB_Link_Scanner
	 */
	protected $scanner;

	/**
	 * Screen hook suffix.
	 *
	 * @var string
	 */
	protected $screen = '';

	/**
	 * Constructor.
	 *
	 * @param SAB_Link_Scanner $scanner Link scanner.
	 */
	public function __construct( SAB_Link_Scanner $scanner ) {
		$this->scanner = $scanner;
	}

	/**
	 * Hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_sab_scan_links', array( $this, 'ajax_scan' ) );
		add_action( 'wp_ajax_sab_save_link', array( $this, 'ajax_save' ) );
	}

	/**
	 * Register the submenu.
	 */
	public function register_menu() {
		$this->screen = add_submenu_page(
			'sab-dashboard',
			__( 'Link Audit', 'seo-article-booster' ),
			__( 'Link Audit', 'seo-article-booster' ),
			self::CAP,
			self::PAGE,
			array( $this, 'render' )
		);
	}

	/**
	 * Enqueue assets on our screen.
	 *
	 * @param string $hook Screen hook.
	 */
	public function enqueue( $hook ) {
		if ( $hook !== $this->screen ) {
			return;
		}
		wp_enqueue_style( 'sab-admin', SAB_PLUGIN_URL . 'admin/css/admin.css', array(), SAB_VERSION );
		wp_enqueue_script( 'sab-link-audit', SAB_PLUGIN_URL . 'admin/js/link-audit.js', array( 'jquery' ), SAB_VERSION, true );
		wp_localize_script(
			'sab-link-audit',
			'SAB_LINKS',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::NONCE ),
				'maxChars' => SAB_Link_Scanner::MAX_ANCHOR_CHARS,
				'i18n'     => array(
					'scanning' => __( 'Scanning…', 'seo-article-booster' ),
					'done'     => __( 'Done.', 'seo-article-booster' ),
					'saving'   => __( 'Saving…', 'seo-article-booster' ),
					'saved'    => __( 'Saved.', 'seo-article-booster' ),
					'error'    => __( 'Something went wrong.', 'seo-article-booster' ),
					'tooLong'  => __( 'Link text is too long.', 'seo-article-booster' ),
					'editTitle' => __( 'Edit link', 'seo-article-booster' ),
				),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------- */

	/**
	 * Render the list or the per-post detail view.
	 */
	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'seo-article-booster' ) );
		}

		$scanner = $this->scanner;
		$action  = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

		require SAB_PLUGIN_DIR . 'admin/views/page-link-audit.php';
	}

	/* ---------------------------------------------------------------------
	 * AJAX
	 * ------------------------------------------------------------------- */

	/**
	 * Verify nonce + capability or die.
	 */
	protected function guard() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'seo-article-booster' ) ), 403 );
		}
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'seo-article-booster' ) ), 403 );
		}
	}

	/**
	 * Batch-scan posts for link counts.
	 */
	public function ajax_scan() {
		$this->guard();
		$paged = isset( $_POST['paged'] ) ? max( 1, absint( wp_unslash( $_POST['paged'] ) ) ) : 1;
		wp_send_json_success( $this->scanner->scan_batch( $paged, 50 ) );
	}

	/**
	 * Save an edited link.
	 */
	public function ajax_save() {
		$this->guard();

		$post_id = isset( $_POST['post_id'] ) ? absint( wp_unslash( $_POST['post_id'] ) ) : 0;
		$index   = isset( $_POST['index'] ) ? absint( wp_unslash( $_POST['index'] ) ) : 0;
		$url     = isset( $_POST['url'] ) ? wp_unslash( $_POST['url'] ) : ''; // Sanitised in update_link().
		$html    = isset( $_POST['html'] ) ? wp_unslash( $_POST['html'] ) : ''; // Sanitised (wp_kses) in update_link().

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit this post.', 'seo-article-booster' ) ), 403 );
		}

		$result = $this->scanner->update_link( $post_id, $index, $url, $html );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Return refreshed counts too.
		$result['counts'] = $this->scanner->count_for_post( $post_id );
		wp_send_json_success( $result );
	}
}
