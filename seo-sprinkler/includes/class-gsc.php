<?php
/**
 * Google Search Console integration.
 *
 * One-click connect (OAuth2 authorization-code flow, offline/refresh token),
 * URL Inspection (which articles are indexed) and the Indexing API (request
 * (re)indexing). Because Google's Indexing API is rate-limited, the plugin
 * queues every unindexed URL and submits a capped number per day via WP-Cron.
 *
 * Tokens and credentials live in a single autoload-off option. All network
 * calls funnel through request() and first fire the `spr_gsc_http` filter, so
 * the logic (classify/delta/queue) is unit-testable without hitting Google.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_GSC
 */
class SPR_GSC {

	/** Option storing credentials + tokens + property. */
	const OPTION = 'spr_gsc';

	/** Option storing index states, the submit queue and the run log. */
	const STATE = 'spr_gsc_state';

	/** Daily cron hook. */
	const CRON = 'spr_gsc_daily_event';

	/** OAuth scopes: read index status + request indexing. */
	const SCOPES = 'https://www.googleapis.com/auth/webmasters.readonly https://www.googleapis.com/auth/indexing';

	/**
	 * Hooks.
	 */
	public function init() {
		add_action( self::CRON, array( $this, 'run_daily' ) );
	}

	/* ---------------------------------------------------------------------
	 * Credentials + connection state
	 * ------------------------------------------------------------------- */

	/**
	 * The stored config.
	 *
	 * @return array
	 */
	public function config() {
		$c = get_option( self::OPTION, array() );
		return is_array( $c ) ? $c : array();
	}

	/**
	 * Persist a subset of config.
	 *
	 * @param array $patch Keys to merge in.
	 */
	protected function update( $patch ) {
		update_option( self::OPTION, array_merge( $this->config(), $patch ), false );
	}

	/**
	 * Save the OAuth client credentials + the verified property URL.
	 *
	 * @param string $client_id     OAuth client id.
	 * @param string $client_secret OAuth client secret.
	 * @param string $property      Search Console property (a URL-prefix property).
	 */
	public function save_credentials( $client_id, $client_secret, $property ) {
		$this->update(
			array(
				'client_id'     => trim( $client_id ),
				'client_secret' => trim( $client_secret ),
				'property'      => trim( $property ),
			)
		);
	}

	/** Has the OAuth client been entered? @return bool */
	public function is_configured() {
		$c = $this->config();
		return ! empty( $c['client_id'] ) && ! empty( $c['client_secret'] );
	}

	/** Is the account connected (we hold a refresh token)? @return bool */
	public function is_connected() {
		$c = $this->config();
		return ! empty( $c['refresh_token'] );
	}

	/** The Search Console property URL (defaults to the site home). @return string */
	public function property() {
		$c = $this->config();
		return ! empty( $c['property'] ) ? $c['property'] : home_url( '/' );
	}

	/** The connected Google account email, if known. @return string */
	public function account_email() {
		$c = $this->config();
		return isset( $c['email'] ) ? (string) $c['email'] : '';
	}

	/**
	 * The OAuth redirect URI the user must register in their Google client.
	 *
	 * @return string
	 */
	public function redirect_uri() {
		return admin_url( 'admin-post.php?action=spr_gsc_callback' );
	}

	/**
	 * Forget tokens (keeps the client credentials so reconnect is one click).
	 */
	public function disconnect() {
		$this->update(
			array(
				'access_token'  => '',
				'refresh_token' => '',
				'token_expires' => 0,
				'email'         => '',
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * OAuth
	 * ------------------------------------------------------------------- */

	/**
	 * Build the Google consent URL for the one-click connect.
	 *
	 * @param string $state CSRF state token.
	 * @return string
	 */
	public function auth_url( $state ) {
		$c = $this->config();
		return add_query_arg(
			array(
				'client_id'              => isset( $c['client_id'] ) ? $c['client_id'] : '',
				'redirect_uri'           => $this->redirect_uri(),
				'response_type'          => 'code',
				'scope'                  => self::SCOPES,
				'access_type'            => 'offline',
				'prompt'                 => 'consent',
				'include_granted_scopes' => 'true',
				'state'                  => $state,
			),
			'https://accounts.google.com/o/oauth2/v2/auth'
		);
	}

	/**
	 * Exchange an authorization code for tokens and store them.
	 *
	 * @param string $code Authorization code from the callback.
	 * @return true|WP_Error
	 */
	public function exchange_code( $code ) {
		$c    = $this->config();
		$resp = $this->request(
			'POST',
			'https://oauth2.googleapis.com/token',
			array(
				'body' => array(
					'code'          => $code,
					'client_id'     => isset( $c['client_id'] ) ? $c['client_id'] : '',
					'client_secret' => isset( $c['client_secret'] ) ? $c['client_secret'] : '',
					'redirect_uri'  => $this->redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		if ( empty( $resp['body']['access_token'] ) ) {
			return new WP_Error( 'spr_gsc_token', __( 'Google did not return an access token.', 'seo-sprinkler' ) );
		}
		$patch = array(
			'access_token'  => $resp['body']['access_token'],
			'token_expires' => time() + (int) ( isset( $resp['body']['expires_in'] ) ? $resp['body']['expires_in'] : 3500 ),
		);
		if ( ! empty( $resp['body']['refresh_token'] ) ) {
			$patch['refresh_token'] = $resp['body']['refresh_token'];
		}
		$this->update( $patch );
		$this->fetch_account_email();
		return true;
	}

	/**
	 * A valid access token, refreshing it when expired.
	 *
	 * @return string|WP_Error
	 */
	public function access_token() {
		$c = $this->config();
		if ( ! empty( $c['access_token'] ) && isset( $c['token_expires'] ) && $c['token_expires'] > time() + 60 ) {
			return $c['access_token'];
		}
		if ( empty( $c['refresh_token'] ) ) {
			return new WP_Error( 'spr_gsc_disconnected', __( 'Not connected to Google Search Console.', 'seo-sprinkler' ) );
		}
		$resp = $this->request(
			'POST',
			'https://oauth2.googleapis.com/token',
			array(
				'body' => array(
					'client_id'     => $c['client_id'],
					'client_secret' => $c['client_secret'],
					'refresh_token' => $c['refresh_token'],
					'grant_type'    => 'refresh_token',
				),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		if ( empty( $resp['body']['access_token'] ) ) {
			return new WP_Error( 'spr_gsc_refresh', __( 'Could not refresh the Google token. Reconnect.', 'seo-sprinkler' ) );
		}
		$this->update(
			array(
				'access_token'  => $resp['body']['access_token'],
				'token_expires' => time() + (int) ( isset( $resp['body']['expires_in'] ) ? $resp['body']['expires_in'] : 3500 ),
			)
		);
		return $resp['body']['access_token'];
	}

	/**
	 * Look up + store the connected account's email (best effort).
	 */
	protected function fetch_account_email() {
		$token = $this->access_token();
		if ( is_wp_error( $token ) ) {
			return;
		}
		$resp = $this->request(
			'GET',
			'https://www.googleapis.com/oauth2/v2/userinfo',
			array( 'headers' => array( 'Authorization' => 'Bearer ' . $token ) )
		);
		if ( ! is_wp_error( $resp ) && ! empty( $resp['body']['email'] ) ) {
			$this->update( array( 'email' => sanitize_email( $resp['body']['email'] ) ) );
		}
	}

	/* ---------------------------------------------------------------------
	 * URL Inspection + Indexing API
	 * ------------------------------------------------------------------- */

	/**
	 * Inspect one URL's index status via the URL Inspection API.
	 *
	 * @param string $url URL to inspect.
	 * @return array|WP_Error { state, coverage, verdict }.
	 */
	public function inspect_url( $url ) {
		$token = $this->access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$resp = $this->request(
			'POST',
			'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect',
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
				'json'    => array(
					'inspectionUrl' => $url,
					'siteUrl'       => $this->property(),
				),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$idx      = isset( $resp['body']['inspectionResult']['indexStatusResult'] ) ? $resp['body']['inspectionResult']['indexStatusResult'] : array();
		$coverage = isset( $idx['coverageState'] ) ? (string) $idx['coverageState'] : '';
		$verdict  = isset( $idx['verdict'] ) ? (string) $idx['verdict'] : '';
		return array(
			'state'    => self::classify( $coverage, $verdict ),
			'coverage' => $coverage,
			'verdict'  => $verdict,
		);
	}

	/**
	 * Map Google's coverageState/verdict to a simple state.
	 *
	 * @param string $coverage coverageState text.
	 * @param string $verdict  PASS|NEUTRAL|FAIL.
	 * @return string indexed|not_indexed|unknown.
	 */
	public static function classify( $coverage, $verdict = '' ) {
		$c = strtolower( (string) $coverage );
		if ( '' === $c ) {
			return ( 'PASS' === $verdict ) ? 'indexed' : 'unknown';
		}
		if ( false !== strpos( $c, 'not indexed' ) || false !== strpos( $c, 'unknown' ) || false !== strpos( $c, 'excluded' ) || false !== strpos( $c, 'redirect' ) || false !== strpos( $c, 'not found' ) || false !== strpos( $c, 'error' ) || false !== strpos( $c, 'blocked' ) ) {
			return 'not_indexed';
		}
		if ( false !== strpos( $c, 'indexed' ) ) {
			return 'indexed';
		}
		return 'not_indexed';
	}

	/**
	 * Ask Google to (re)crawl/index a URL via the Indexing API.
	 *
	 * @param string $url URL.
	 * @return true|WP_Error
	 */
	public function request_index( $url ) {
		$token = $this->access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$resp = $this->request(
			'POST',
			'https://indexing.googleapis.com/v3/urlNotifications:publish',
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
				'json'    => array(
					'url'  => $url,
					'type' => 'URL_UPDATED',
				),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		if ( isset( $resp['code'] ) && (int) $resp['code'] >= 400 ) {
			$msg = isset( $resp['body']['error']['message'] ) ? $resp['body']['error']['message'] : __( 'Indexing API rejected the request.', 'seo-sprinkler' );
			return new WP_Error( 'spr_gsc_index', $msg );
		}
		return true;
	}

	/* ---------------------------------------------------------------------
	 * State, deltas + queue
	 * ------------------------------------------------------------------- */

	/**
	 * Stored state: { states:{url:state}, queue:[url], submitted:{url:ts}, daily_date, daily_count, log:[] }.
	 *
	 * @return array
	 */
	public function state() {
		$s = get_option( self::STATE, array() );
		$s = is_array( $s ) ? $s : array();
		return wp_parse_args(
			$s,
			array(
				'states'      => array(),
				'queue'       => array(),
				'submitted'   => array(),
				'daily_date'  => '',
				'daily_count' => 0,
				'log'         => array(),
				'updated'     => 0,
			)
		);
	}

	/**
	 * Save state.
	 *
	 * @param array $s State.
	 */
	protected function save_state( $s ) {
		update_option( self::STATE, $s, false );
	}

	/**
	 * Compute which URLs were newly added (indexed), dropped, or are not indexed.
	 *
	 * @param array<string,string> $prev Previous url=>state.
	 * @param array<string,string> $curr Current url=>state.
	 * @return array{added:string[],dropped:string[],not_indexed:string[]}
	 */
	public static function compute_delta( $prev, $curr ) {
		$added       = array();
		$dropped     = array();
		$not_indexed = array();
		foreach ( $curr as $url => $state ) {
			$was = isset( $prev[ $url ] ) ? $prev[ $url ] : '';
			if ( 'indexed' === $state && 'indexed' !== $was ) {
				$added[] = $url;
			}
			if ( 'indexed' !== $state ) {
				$not_indexed[] = $url;
				if ( 'indexed' === $was ) {
					$dropped[] = $url;
				}
			}
		}
		return array(
			'added'       => $added,
			'dropped'     => $dropped,
			'not_indexed' => $not_indexed,
		);
	}

	/**
	 * Default daily submit cap (Google rate-limits the Indexing API).
	 *
	 * @return int
	 */
	public function daily_quota() {
		/**
		 * Filter the per-day Indexing API submit cap.
		 *
		 * @param int $quota Default 10.
		 */
		return max( 1, (int) apply_filters( 'spr_gsc_daily_quota', 10 ) );
	}

	/**
	 * Submit up to $limit queued URLs to the Indexing API, honouring the daily
	 * cap. Returns the URLs submitted and any errors.
	 *
	 * @param int $limit Max to submit this run (also capped by remaining daily quota).
	 * @return array{submitted:string[],errors:array,remaining:int}
	 */
	public function process_queue( $limit = 0 ) {
		$s     = $this->state();
		$today = gmdate( 'Y-m-d' );
		if ( $s['daily_date'] !== $today ) {
			$s['daily_date']  = $today;
			$s['daily_count'] = 0;
		}
		$quota     = $this->daily_quota();
		$remaining = max( 0, $quota - (int) $s['daily_count'] );
		$limit     = $limit > 0 ? min( $limit, $remaining ) : $remaining;

		$submitted = array();
		$errors    = array();
		while ( $limit > 0 && ! empty( $s['queue'] ) ) {
			$url    = array_shift( $s['queue'] );
			$result = $this->request_index( $url );
			if ( is_wp_error( $result ) ) {
				$errors[ $url ] = $result->get_error_message();
				// Re-queue at the back so a transient error doesn't lose the URL,
				// unless it's an auth error (then stop to avoid burning the run).
				if ( in_array( $result->get_error_code(), array( 'spr_gsc_disconnected', 'spr_gsc_refresh' ), true ) ) {
					array_unshift( $s['queue'], $url );
					break;
				}
				$s['queue'][] = $url;
			} else {
				$submitted[]            = $url;
				$s['submitted'][ $url ] = time();
				$s['daily_count']++;
				$limit--;
			}
		}

		$s['updated'] = time();
		$this->save_state( $s );

		return array(
			'submitted' => $submitted,
			'errors'    => $errors,
			'remaining' => count( $s['queue'] ),
		);
	}

	/**
	 * Candidate URLs to inspect (published articles of the audited post types).
	 *
	 * @param int $max Max URLs.
	 * @return array<int,string> id => url.
	 */
	public function candidate_urls( $max = 200 ) {
		$types = (array) SPR_Settings::get( 'audit_post_types', array( 'post' ) );
		$types = array_merge( $types, (array) SPR_Settings::get( 'link_post_types', array( 'post', 'page' ) ) );
		$types = array_values( array_unique( array_filter( $types ) ) );
		$ids   = get_posts(
			array(
				'post_type'      => $types,
				'post_status'    => 'publish',
				'posts_per_page' => max( 1, (int) $max ),
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$out = array();
		foreach ( $ids as $id ) {
			$out[ (int) $id ] = get_permalink( $id );
		}
		return $out;
	}

	/**
	 * Append an entry to the run log (kept short).
	 *
	 * @param string $message Summary.
	 */
	public function log( $message ) {
		$s = $this->state();
		array_unshift(
			$s['log'],
			array(
				'time'    => time(),
				'message' => wp_strip_all_tags( (string) $message ),
			)
		);
		$s['log'] = array_slice( $s['log'], 0, 30 );
		$this->save_state( $s );
		if ( class_exists( 'SPR_Activity' ) ) {
			SPR_Activity::log( 'gsc', $message );
		}
	}

	/* ---------------------------------------------------------------------
	 * Scheduling + notifications
	 * ------------------------------------------------------------------- */

	/** Schedule the daily submit cron. */
	public function schedule() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON );
		}
		$this->update( array( 'auto' => 1 ) );
	}

	/** Cancel the daily submit cron. */
	public function unschedule() {
		wp_clear_scheduled_hook( self::CRON );
		$this->update( array( 'auto' => 0 ) );
	}

	/** Is the daily auto-submit on? @return bool */
	public function is_scheduled() {
		$c = $this->config();
		return ! empty( $c['auto'] );
	}

	/**
	 * Daily cron: submit the next batch and notify the admin.
	 */
	public function run_daily() {
		if ( ! $this->is_connected() ) {
			return;
		}
		$res = $this->process_queue();
		if ( ! empty( $res['submitted'] ) ) {
			$this->log(
				sprintf(
					/* translators: 1: submitted count, 2: remaining count. */
					__( 'Indexing: submitted %1$d URL(s); %2$d still queued.', 'seo-sprinkler' ),
					count( $res['submitted'] ),
					(int) $res['remaining']
				)
			);
			$this->notify(
				__( 'SEO Sprinkler: URLs submitted for indexing', 'seo-sprinkler' ),
				sprintf(
					/* translators: 1: submitted count, 2: remaining. */
					__( "Submitted %1\$d URL(s) to Google for indexing today. %2\$d remain queued.\n\n%3\$s", 'seo-sprinkler' ),
					count( $res['submitted'] ),
					(int) $res['remaining'],
					implode( "\n", $res['submitted'] )
				)
			);
		}
	}

	/**
	 * Email the site admin (best effort).
	 *
	 * @param string $subject Subject.
	 * @param string $body    Body.
	 */
	public function notify( $subject, $body ) {
		$to = get_option( 'admin_email' );
		if ( $to ) {
			wp_mail( $to, $subject, $body );
		}
	}

	/* ---------------------------------------------------------------------
	 * HTTP (mockable)
	 * ------------------------------------------------------------------- */

	/**
	 * Perform an HTTP request, normalised to { code, body(array) }.
	 *
	 * Fires `spr_gsc_http` first so tests (or a custom transport) can short-circuit
	 * the network: return an array { code, body } from the filter to mock.
	 *
	 * @param string $method GET|POST.
	 * @param string $url    Endpoint.
	 * @param array  $args   { body:array(form), json:array, headers:array }.
	 * @return array|WP_Error
	 */
	protected function request( $method, $url, $args = array() ) {
		$mock = apply_filters( 'spr_gsc_http', null, $method, $url, $args );
		if ( is_array( $mock ) ) {
			return wp_parse_args( $mock, array( 'code' => 200, 'body' => array() ) );
		}

		$req = array(
			'method'  => $method,
			'timeout' => 20,
			'headers' => isset( $args['headers'] ) ? (array) $args['headers'] : array(),
		);
		if ( isset( $args['json'] ) ) {
			$req['headers']['Content-Type'] = 'application/json';
			$req['body']                    = wp_json_encode( $args['json'] );
		} elseif ( isset( $args['body'] ) ) {
			$req['body'] = $args['body'];
		}

		$resp = wp_remote_request( $url, $req );
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		$body = json_decode( wp_remote_retrieve_body( $resp ), true );
		return array(
			'code' => (int) wp_remote_retrieve_response_code( $resp ),
			'body' => is_array( $body ) ? $body : array(),
		);
	}
}
