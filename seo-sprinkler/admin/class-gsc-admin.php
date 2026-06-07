<?php
/**
 * "Search Console" admin screen.
 *
 * One-click connect to Google Search Console (OAuth), check which articles are
 * indexed, queue every unindexed URL, and submit a capped number per day to the
 * Indexing API. The admin is notified which URLs were added (newly indexed),
 * dropped, or remain not indexed.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_GSC_Admin
 */
class SPR_GSC_Admin {

	const CAP   = 'manage_options';
	const PAGE  = 'spr-gsc';
	const NONCE = 'spr_gsc';

	/** @var SPR_GSC */
	protected $gsc;

	/** @var string */
	protected $screen = '';

	/**
	 * Constructor.
	 *
	 * @param SPR_GSC $gsc GSC engine.
	 */
	public function __construct( SPR_GSC $gsc ) {
		$this->gsc = $gsc;
	}

	/**
	 * Hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 11 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_spr_gsc_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_spr_gsc_connect', array( $this, 'handle_connect' ) );
		add_action( 'admin_post_spr_gsc_callback', array( $this, 'handle_callback' ) );
		add_action( 'admin_post_spr_gsc_disconnect', array( $this, 'handle_disconnect' ) );
		add_action( 'admin_post_spr_gsc_schedule', array( $this, 'handle_schedule' ) );
		add_action( 'wp_ajax_spr_gsc_check', array( $this, 'ajax_check' ) );
		add_action( 'wp_ajax_spr_gsc_submit', array( $this, 'ajax_submit' ) );
	}

	/**
	 * Register the submenu (just above Settings).
	 */
	public function register_menu() {
		$this->screen = add_submenu_page(
			'spr-dashboard',
			__( 'Search Console', 'seo-sprinkler' ),
			__( 'Search Console', 'seo-sprinkler' ),
			self::CAP,
			self::PAGE,
			array( $this, 'render' ),
			75
		);
	}

	/**
	 * Enqueue assets on our screen.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue( $hook ) {
		if ( $hook !== $this->screen ) {
			return;
		}
		wp_enqueue_style( 'spr-admin', SPR_PLUGIN_URL . 'admin/css/admin.css', array(), SPR_VERSION );
		wp_enqueue_script( 'spr-gsc', SPR_PLUGIN_URL . 'admin/js/gsc.js', array( 'jquery' ), SPR_VERSION, true );
		wp_localize_script(
			'spr-gsc',
			'SPR_GSC',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE ),
				'i18n'    => array(
					'checking'  => __( 'Checking index status…', 'seo-sprinkler' ),
					'submitting' => __( 'Submitting to Google…', 'seo-sprinkler' ),
					'done'      => __( 'Done.', 'seo-sprinkler' ),
					'error'     => __( 'Something went wrong.', 'seo-sprinkler' ),
				),
			)
		);
	}

	/**
	 * Render.
	 */
	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'seo-sprinkler' ) );
		}
		$gsc    = $this->gsc;
		$locked = ! SPR_Edition::can( 'indexing' );
		require SPR_PLUGIN_DIR . 'admin/views/page-gsc.php';
	}

	/* ---------------------------------------------------------------------
	 * Guards
	 * ------------------------------------------------------------------- */

	/**
	 * Guard an admin-post action: capability + edition + nonce.
	 *
	 * @param string $nonce_action Nonce action.
	 */
	protected function guard_post( $nonce_action ) {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'Not allowed.', 'seo-sprinkler' ), 403 );
		}
		if ( ! SPR_Edition::can( 'indexing' ) ) {
			wp_die( esc_html__( 'The Search Console integration is an Expert feature.', 'seo-sprinkler' ), 402 );
		}
		check_admin_referer( $nonce_action );
	}

	/**
	 * Redirect back to our screen with a flag.
	 *
	 * @param string $flag   Query flag.
	 * @param string $value  Value.
	 */
	protected function redirect_back( $flag, $value = '1' ) {
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE, $flag => $value ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * admin-post handlers
	 * ------------------------------------------------------------------- */

	/**
	 * Save the OAuth client credentials + property.
	 */
	public function handle_save() {
		$this->guard_post( 'spr_gsc_save' );
		$this->gsc->save_credentials(
			isset( $_POST['client_id'] ) ? sanitize_text_field( wp_unslash( $_POST['client_id'] ) ) : '',
			isset( $_POST['client_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['client_secret'] ) ) : '',
			isset( $_POST['property'] ) ? esc_url_raw( wp_unslash( $_POST['property'] ) ) : ''
		);
		$this->redirect_back( 'saved' );
	}

	/**
	 * Begin the OAuth flow (one-click connect).
	 */
	public function handle_connect() {
		$this->guard_post( 'spr_gsc_connect' );
		if ( ! $this->gsc->is_configured() ) {
			$this->redirect_back( 'error', 'noclient' );
		}
		$state = wp_generate_password( 24, false );
		set_transient( 'spr_gsc_state_' . get_current_user_id(), $state, 15 * MINUTE_IN_SECONDS );
		wp_redirect( $this->gsc->auth_url( $state ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- external OAuth endpoint.
		exit;
	}

	/**
	 * OAuth callback: verify state, exchange the code.
	 */
	public function handle_callback() {
		if ( ! current_user_can( self::CAP ) || ! SPR_Edition::can( 'indexing' ) ) {
			wp_die( esc_html__( 'Not allowed.', 'seo-sprinkler' ), 403 );
		}
		$state    = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		$expected = get_transient( 'spr_gsc_state_' . get_current_user_id() );
		delete_transient( 'spr_gsc_state_' . get_current_user_id() );
		if ( ! $state || $state !== $expected ) {
			$this->redirect_back( 'error', 'state' );
		}
		if ( isset( $_GET['error'] ) ) {
			$this->redirect_back( 'error', 'denied' );
		}
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		if ( ! $code ) {
			$this->redirect_back( 'error', 'nocode' );
		}
		$res = $this->gsc->exchange_code( $code );
		if ( is_wp_error( $res ) ) {
			$this->redirect_back( 'error', 'token' );
		}
		$this->gsc->log( __( 'Connected to Google Search Console.', 'seo-sprinkler' ) );
		$this->redirect_back( 'connected' );
	}

	/**
	 * Disconnect.
	 */
	public function handle_disconnect() {
		$this->guard_post( 'spr_gsc_disconnect' );
		$this->gsc->unschedule();
		$this->gsc->disconnect();
		$this->redirect_back( 'disconnected' );
	}

	/**
	 * Toggle the daily auto-submit schedule.
	 */
	public function handle_schedule() {
		$this->guard_post( 'spr_gsc_schedule' );
		if ( ! empty( $_POST['auto'] ) ) {
			$this->gsc->schedule();
		} else {
			$this->gsc->unschedule();
		}
		$this->redirect_back( 'saved' );
	}

	/* ---------------------------------------------------------------------
	 * AJAX
	 * ------------------------------------------------------------------- */

	/**
	 * AJAX guard.
	 */
	protected function ajax_guard() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'seo-sprinkler' ) ), 403 );
		}
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'seo-sprinkler' ) ), 403 );
		}
		if ( ! SPR_Edition::can( 'indexing' ) ) {
			wp_send_json_error( array( 'message' => __( 'The Search Console integration is an Expert feature.', 'seo-sprinkler' ) ), 402 );
		}
		if ( ! $this->gsc->is_connected() ) {
			wp_send_json_error( array( 'message' => __( 'Connect to Google Search Console first.', 'seo-sprinkler' ) ), 400 );
		}
	}

	/**
	 * AJAX: inspect a batch of candidate URLs; on completion compute the deltas,
	 * rebuild the submit queue and notify.
	 */
	public function ajax_check() {
		$this->ajax_guard();
		$paged    = isset( $_POST['paged'] ) ? max( 1, absint( wp_unslash( $_POST['paged'] ) ) ) : 1;
		$per      = 8;
		$run_key  = 'spr_gsc_run_' . get_current_user_id();

		if ( 1 === $paged ) {
			$urls = array_values( $this->gsc->candidate_urls( 200 ) );
			$run  = array( 'urls' => $urls, 'states' => array() );
		} else {
			$run = get_transient( $run_key );
			if ( ! is_array( $run ) ) {
				wp_send_json_error( array( 'message' => __( 'Session expired — start again.', 'seo-sprinkler' ) ) );
			}
		}

		$total = count( $run['urls'] );
		$start = ( $paged - 1 ) * $per;
		$slice = array_slice( $run['urls'], $start, $per );
		foreach ( $slice as $url ) {
			$res                      = $this->gsc->inspect_url( $url );
			$run['states'][ $url ]    = is_wp_error( $res ) ? 'unknown' : $res['state'];
		}
		$scanned = min( $start + $per, $total );
		$done    = $scanned >= $total;

		if ( $done ) {
			delete_transient( $run_key );
			$prev  = $this->gsc->state();
			$delta = SPR_GSC::compute_delta( $prev['states'], $run['states'] );

			// Merge the new states + (re)build the queue from everything not indexed.
			$prev['states'] = array_merge( $prev['states'], $run['states'] );
			$queue          = array();
			foreach ( $delta['not_indexed'] as $u ) {
				if ( ! isset( $prev['submitted'][ $u ] ) || ( time() - (int) $prev['submitted'][ $u ] ) > DAY_IN_SECONDS ) {
					$queue[] = $u;
				}
			}
			$prev['queue']   = array_values( array_unique( $queue ) );
			$prev['updated'] = time();
			$this->gsc->log(
				sprintf(
					/* translators: 1: not-indexed, 2: added, 3: dropped. */
					__( 'Index check: %1$d not indexed, %2$d newly indexed, %3$d dropped.', 'seo-sprinkler' ),
					count( $delta['not_indexed'] ),
					count( $delta['added'] ),
					count( $delta['dropped'] )
				)
			);
			// Persist merged states+queue (log() re-read state, so write after).
			update_option( SPR_GSC::STATE, $prev, false );

			if ( ! empty( $delta['added'] ) || ! empty( $delta['dropped'] ) ) {
				$this->gsc->notify(
					__( 'SEO Sprinkler: index status changed', 'seo-sprinkler' ),
					sprintf(
						/* translators: 1: added list, 2: dropped list. */
						__( "Newly indexed:\n%1\$s\n\nDropped from the index:\n%2\$s", 'seo-sprinkler' ),
						$delta['added'] ? implode( "\n", $delta['added'] ) : '—',
						$delta['dropped'] ? implode( "\n", $delta['dropped'] ) : '—'
					)
				);
			}

			wp_send_json_success(
				array(
					'done'        => true,
					'scanned'     => $scanned,
					'total'       => $total,
					'added'       => $delta['added'],
					'dropped'     => $delta['dropped'],
					'not_indexed' => $delta['not_indexed'],
					'queued'      => count( $prev['queue'] ),
					'quota'       => $this->gsc->daily_quota(),
				)
			);
		}

		set_transient( $run_key, $run, HOUR_IN_SECONDS );
		wp_send_json_success(
			array(
				'done'      => false,
				'scanned'   => $scanned,
				'total'     => $total,
				'next_page' => $paged + 1,
			)
		);
	}

	/**
	 * AJAX: submit the next daily batch now.
	 */
	public function ajax_submit() {
		$this->ajax_guard();
		$res = $this->gsc->process_queue();
		if ( ! empty( $res['submitted'] ) ) {
			$this->gsc->log(
				sprintf(
					/* translators: %d: submitted count. */
					__( 'Indexing: submitted %d URL(s) now.', 'seo-sprinkler' ),
					count( $res['submitted'] )
				)
			);
		}
		wp_send_json_success( $res );
	}
}
