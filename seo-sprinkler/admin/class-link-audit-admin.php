<?php
/**
 * "Link Audit" admin screen.
 *
 * Lists every linkable post with its internal/external link counts, and a
 * per-post detail view listing all links with an inline mini-WYSIWYG editor to
 * change a link's text and URL and save it straight to the post content.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_Link_Audit_Admin
 */
class SPR_Link_Audit_Admin {

	const CAP   = 'manage_options';
	const PAGE  = 'spr-link-audit';
	const NONCE = 'spr_link_audit';

	/** Option that stores the last scan so the list survives reloads. */
	const SNAPSHOT = 'spr_link_audit_snapshot';

	/**
	 * Link scanner.
	 *
	 * @var SPR_Link_Scanner
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
	 * @param SPR_Link_Scanner $scanner Link scanner.
	 */
	public function __construct( SPR_Link_Scanner $scanner ) {
		$this->scanner = $scanner;
	}

	/**
	 * Hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_ajax_spr_scan_links', array( $this, 'ajax_scan' ) );
		add_action( 'wp_ajax_spr_save_link', array( $this, 'ajax_save' ) );
	}

	/**
	 * Register the submenu.
	 */
	public function register_menu() {
		$this->screen = add_submenu_page(
			'spr-dashboard',
			__( 'Link Audit', 'seo-sprinkler' ),
			__( 'Link Audit', 'seo-sprinkler' ),
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
		wp_enqueue_style( 'spr-admin', SPR_PLUGIN_URL . 'admin/css/admin.css', array(), SPR_VERSION );
		wp_enqueue_script( 'spr-link-audit', SPR_PLUGIN_URL . 'admin/js/link-audit.js', array( 'jquery' ), SPR_VERSION, true );
		wp_localize_script(
			'spr-link-audit',
			'SPR_LINKS',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( self::NONCE ),
				'maxChars' => SPR_Link_Scanner::MAX_ANCHOR_CHARS,
				'i18n'     => array(
					'scanning'    => __( 'Scanning…', 'seo-sprinkler' ),
					'done'        => __( 'Done.', 'seo-sprinkler' ),
					'justScanned' => __( 'Last scanned just now.', 'seo-sprinkler' ),
					'saving'   => __( 'Saving…', 'seo-sprinkler' ),
					'saved'    => __( 'Saved.', 'seo-sprinkler' ),
					'error'    => __( 'Something went wrong.', 'seo-sprinkler' ),
					'tooLong'  => __( 'Link text is too long.', 'seo-sprinkler' ),
					'editTitle' => __( 'Edit link', 'seo-sprinkler' ),
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
			wp_die( esc_html__( 'You do not have permission to access this page.', 'seo-sprinkler' ) );
		}

		$scanner = $this->scanner;
		$action  = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : 'list'; // phpcs:ignore WordPress.Security.NonceVerification
		$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

		$snapshot = $this->snapshot();

		require SPR_PLUGIN_DIR . 'admin/views/page-link-audit.php';
	}

	/* ---------------------------------------------------------------------
	 * AJAX
	 * ------------------------------------------------------------------- */

	/**
	 * Verify nonce + capability or die.
	 */
	protected function guard() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'seo-sprinkler' ) ), 403 );
		}
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'seo-sprinkler' ) ), 403 );
		}
	}

	/**
	 * The stored snapshot of the last scan: { rows: array, updated: int }.
	 *
	 * @return array
	 */
	public function snapshot() {
		$snap = get_option( self::SNAPSHOT, array() );
		if ( ! is_array( $snap ) ) {
			$snap = array();
		}
		return array(
			'rows'    => ( isset( $snap['rows'] ) && is_array( $snap['rows'] ) ) ? $snap['rows'] : array(),
			'updated' => isset( $snap['updated'] ) ? (int) $snap['updated'] : 0,
		);
	}

	/**
	 * Batch-scan posts for link counts, persisting the results so the list
	 * survives page reloads until the next scan. Page 1 starts a fresh snapshot;
	 * each batch appends to it; the final batch stamps the scan time.
	 */
	public function ajax_scan() {
		$this->guard();
		$paged = isset( $_POST['paged'] ) ? max( 1, absint( wp_unslash( $_POST['paged'] ) ) ) : 1;
		$batch = $this->scanner->scan_batch( $paged, 50 );

		$snap = ( 1 === $paged ) ? array( 'rows' => array(), 'updated' => 0 ) : $this->snapshot();
		$snap['rows'] = array_merge( $snap['rows'], $batch['rows'] );
		if ( ! empty( $batch['done'] ) ) {
			$snap['updated'] = time();
		}
		update_option( self::SNAPSHOT, $snap, false );

		wp_send_json_success( $batch );
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
			wp_send_json_error( array( 'message' => __( 'You cannot edit this post.', 'seo-sprinkler' ) ), 403 );
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
