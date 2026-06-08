<?php
/**
 * "Business Profile" admin screen — the questionnaire that powers schema.
 *
 * The profile option is registered with the Settings API so saving, the nonce
 * and the "Settings saved" notice are all handled by options.php.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_Business_Admin
 */
class SPR_Business_Admin {

	const CAP   = 'manage_options';
	const PAGE  = 'spr-business';
	const GROUP = 'spr_business_group';

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
		add_action( 'wp_ajax_spr_geocode', array( $this, 'ajax_geocode' ) );
		add_action( 'wp_ajax_spr_gmb_lookup', array( $this, 'ajax_gmb' ) );
	}

	/**
	 * Register the submenu.
	 */
	public function register_menu() {
		$this->screen = add_submenu_page(
			'spr-dashboard',
			__( 'Business Profile', 'seo-sprinkler' ),
			__( 'Business Profile', 'seo-sprinkler' ),
			self::CAP,
			self::PAGE,
			array( $this, 'render' ),
			70
		);
	}

	/**
	 * Register the profile option with sanitisation.
	 */
	public function register_setting() {
		register_setting(
			self::GROUP,
			SPR_Business_Profile::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'SPR_Business_Profile', 'sanitize' ),
				'default'           => SPR_Business_Profile::defaults(),
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
		wp_enqueue_style( 'spr-admin', SPR_PLUGIN_URL . 'admin/css/admin.css', array(), SPR_VERSION );
		wp_enqueue_script( 'spr-business', SPR_PLUGIN_URL . 'admin/js/business.js', array( 'jquery' ), SPR_VERSION, true );
		$logo_id  = (int) get_theme_mod( 'custom_logo' );
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
		wp_localize_script(
			'spr-business',
			'SPR_BIZ',
			array(
				'choose'       => __( 'Select image', 'seo-sprinkler' ),
				'use'          => __( 'Use this image', 'seo-sprinkler' ),
				'ajax'         => admin_url( 'admin-ajax.php' ),
				'geocodeNonce' => wp_create_nonce( 'spr_geocode' ),
				'site'         => array(
					'name'        => get_bloginfo( 'name' ),
					'url'         => home_url( '/' ),
					'description' => get_bloginfo( 'description' ),
					'email'       => get_option( 'admin_email' ),
					'logoId'      => $logo_id,
					'logoUrl'     => $logo_url ? $logo_url : '',
				),
				'i18n'         => array(
					'filled'  => __( 'Filled from this site — review and Save.', 'seo-sprinkler' ),
					'looking' => __( 'Looking up…', 'seo-sprinkler' ),
					'matched' => __( 'Matched:', 'seo-sprinkler' ),
					'geoFail' => __( 'Could not find coordinates for that address.', 'seo-sprinkler' ),
					'gmbNeed' => __( 'Paste your Google Maps link first.', 'seo-sprinkler' ),
					'gmbOk'   => __( 'Filled from Google — review and Save.', 'seo-sprinkler' ),
					'gmbFail' => __( 'Could not read that Google link.', 'seo-sprinkler' ),
				),
			)
		);
	}

	/**
	 * Render the page.
	 */
	public function render() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'seo-sprinkler' ) );
		}
		require SPR_PLUGIN_DIR . 'admin/views/page-business.php';
	}

	/**
	 * AJAX: turn the entered address into latitude/longitude.
	 */
	public function ajax_geocode() {
		if ( ! check_ajax_referer( 'spr_geocode', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'seo-sprinkler' ) ), 403 );
		}
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'seo-sprinkler' ) ), 403 );
		}
		$parts = array();
		foreach ( array( 'street', 'locality', 'region', 'postal_code', 'country' ) as $k ) {
			$v = isset( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : '';
			if ( '' !== $v ) {
				$parts[] = $v;
			}
		}
		if ( empty( $parts ) ) {
			wp_send_json_error( array( 'message' => __( 'Enter an address (city + country) first.', 'seo-sprinkler' ) ) );
		}
		$result = $this->geocode( implode( ', ', $parts ) );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( $result );
	}

	/**
	 * AJAX: read a Google Maps / Business link and fill the profile.
	 *
	 * The business name + coordinates come straight from the link (free). When a
	 * Google Places API key is supplied, we additionally fetch the phone, address
	 * and website from the Places API.
	 */
	public function ajax_gmb() {
		if ( ! check_ajax_referer( 'spr_geocode', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'seo-sprinkler' ) ), 403 );
		}
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'Not allowed.', 'seo-sprinkler' ) ), 403 );
		}
		$url = isset( $_POST['url'] ) ? esc_url_raw( wp_unslash( $_POST['url'] ) ) : '';
		$key = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		if ( '' === $url ) {
			wp_send_json_error( array( 'message' => __( 'Paste your Google Maps link first.', 'seo-sprinkler' ) ) );
		}

		// Resolve short links (maps.app.goo.gl / goo.gl) to the full Maps URL.
		if ( preg_match( '#(goo\.gl|app\.goo\.gl|maps\.app\.goo\.gl)#i', $url ) ) {
			$url = $this->resolve_url( $url );
		}

		$data = self::parse_google_url( $url );

		if ( '' !== $key ) {
			$details = $this->places_details( $data, $key );
			if ( is_array( $details ) ) {
				$data = array_merge( $data, $details );
			}
		}

		// No address yet (e.g. no Places key)? Derive it from the coordinates for
		// free via OpenStreetMap, so the address still transfers.
		if ( empty( $data['street'] ) && empty( $data['locality'] ) && isset( $data['lat'], $data['lng'] ) ) {
			$rev = $this->reverse_geocode( $data['lat'], $data['lng'] );
			if ( is_array( $rev ) ) {
				$data = array_merge( $rev, $data ); // Keep name/coords/Places values; add the address.
			}
		}

		if ( empty( $data ) || ( empty( $data['lat'] ) && empty( $data['name'] ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Could not read that link. Make sure it is a Google Maps place link.', 'seo-sprinkler' ) ) );
		}

		wp_send_json_success( $data );
	}

	/**
	 * Follow redirects to the final URL (for shortened Google links).
	 *
	 * @param string $url  URL.
	 * @param int    $hops Max redirects.
	 * @return string
	 */
	protected function resolve_url( $url, $hops = 5 ) {
		for ( $i = 0; $i < $hops; $i++ ) {
			$resp = wp_remote_head(
				$url,
				array(
					'redirection' => 0,
					'timeout'     => 10,
					'headers'     => array( 'User-Agent' => 'SEO Sprinkler/' . SPR_VERSION . ' (' . home_url( '/' ) . ')' ),
				)
			);
			if ( is_wp_error( $resp ) ) {
				break;
			}
			$code = (int) wp_remote_retrieve_response_code( $resp );
			$loc  = wp_remote_retrieve_header( $resp, 'location' );
			if ( $code >= 300 && $code < 400 && $loc ) {
				$url = $loc;
				continue;
			}
			break;
		}
		return $url;
	}

	/**
	 * Pull the place name + coordinates out of a Google Maps URL.
	 *
	 * @param string $url Maps URL.
	 * @return array { name?, lat?, lng? }
	 */
	public static function parse_google_url( $url ) {
		$out = array();
		// Prefer the actual place pin (!3d<lat>!4d<lng>); the @lat,lng in the URL is
		// only the map viewport and is often miles from the business.
		if ( preg_match( '/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/', $url, $m ) ) {
			$out['lat'] = round( (float) $m[1], 6 );
			$out['lng'] = round( (float) $m[2], 6 );
		} elseif ( preg_match( '/@(-?\d+\.\d+),(-?\d+\.\d+)/', $url, $m ) ) {
			$out['lat'] = round( (float) $m[1], 6 );
			$out['lng'] = round( (float) $m[2], 6 );
		}
		if ( preg_match( '#/place/([^/@]+)#', $url, $m ) ) {
			$name = trim( str_replace( '+', ' ', rawurldecode( $m[1] ) ) );
			if ( '' !== $name ) {
				$out['name'] = $name;
			}
		}
		return $out;
	}

	/**
	 * Reverse-geocode coordinates to a street address via OpenStreetMap (free,
	 * no API key). Lets the Google-link lookup fill the address even without a
	 * Places API key.
	 *
	 * @param float $lat Latitude.
	 * @param float $lng Longitude.
	 * @return array|null { street, locality, region, postal_code, country }.
	 */
	protected function reverse_geocode( $lat, $lng ) {
		$resp = wp_remote_get(
			add_query_arg(
				array(
					'lat'            => $lat,
					'lon'            => $lng,
					'format'         => 'jsonv2',
					'addressdetails' => 1,
					'email'          => (string) get_option( 'admin_email' ),
				),
				'https://nominatim.openstreetmap.org/reverse'
			),
			array(
				'timeout' => 15,
				'headers' => array(
					'User-Agent' => 'SEO Sprinkler/' . SPR_VERSION . ' (' . home_url( '/' ) . ')',
					'Accept'     => 'application/json',
				),
			)
		);
		if ( is_wp_error( $resp ) || 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
			return null;
		}
		$d = json_decode( wp_remote_retrieve_body( $resp ), true );
		$a = isset( $d['address'] ) && is_array( $d['address'] ) ? $d['address'] : null;
		if ( ! $a ) {
			return null;
		}
		$street = trim( ( isset( $a['house_number'] ) ? $a['house_number'] . ' ' : '' ) . ( isset( $a['road'] ) ? $a['road'] : '' ) );
		$loc    = '';
		foreach ( array( 'city', 'town', 'village', 'hamlet', 'suburb' ) as $k ) {
			if ( ! empty( $a[ $k ] ) ) {
				$loc = $a[ $k ];
				break;
			}
		}
		$out = array();
		if ( '' !== $street ) {
			$out['street'] = $street;
		}
		if ( '' !== $loc ) {
			$out['locality'] = $loc;
		}
		if ( ! empty( $a['state'] ) ) {
			$out['region'] = $a['state'];
		}
		if ( ! empty( $a['postcode'] ) ) {
			$out['postal_code'] = $a['postcode'];
		}
		if ( ! empty( $a['country_code'] ) ) {
			$out['country'] = strtoupper( $a['country_code'] );
		}
		return $out;
	}

	/**
	 * Fetch full details from the Google Places API (needs an API key).
	 *
	 * @param array  $known Already-known data (name/lat/lng) used to find the place.
	 * @param string $key   Google Places API key.
	 * @return array|null Filled fields, or null on failure.
	 */
	protected function places_details( $known, $key ) {
		$query = isset( $known['name'] ) ? $known['name'] : '';
		if ( '' === $query ) {
			return null;
		}

		$find_args = array(
			'input'     => $query,
			'inputtype' => 'textquery',
			'fields'    => 'place_id',
			'key'       => $key,
		);
		if ( isset( $known['lat'], $known['lng'] ) ) {
			$find_args['locationbias'] = 'point:' . $known['lat'] . ',' . $known['lng'];
		}
		$find = wp_remote_get(
			add_query_arg( $find_args, 'https://maps.googleapis.com/maps/api/place/findplacefromtext/json' ),
			array( 'timeout' => 15 )
		);
		if ( is_wp_error( $find ) ) {
			return null;
		}
		$fbody = json_decode( wp_remote_retrieve_body( $find ), true );
		$pid   = isset( $fbody['candidates'][0]['place_id'] ) ? $fbody['candidates'][0]['place_id'] : '';
		if ( '' === $pid ) {
			return null;
		}

		$det = wp_remote_get(
			add_query_arg(
				array(
					'place_id' => $pid,
					'fields'   => 'name,formatted_phone_number,international_phone_number,website,address_components,geometry,opening_hours',
					'key'      => $key,
				),
				'https://maps.googleapis.com/maps/api/place/details/json'
			),
			array( 'timeout' => 15 )
		);
		if ( is_wp_error( $det ) ) {
			return null;
		}
		$dbody = json_decode( wp_remote_retrieve_body( $det ), true );
		$r     = isset( $dbody['result'] ) && is_array( $dbody['result'] ) ? $dbody['result'] : null;
		if ( ! $r ) {
			return null;
		}

		$out = array();
		if ( ! empty( $r['name'] ) ) {
			$out['name'] = $r['name'];
		}
		if ( ! empty( $r['formatted_phone_number'] ) ) {
			$out['telephone'] = $r['formatted_phone_number'];
		} elseif ( ! empty( $r['international_phone_number'] ) ) {
			$out['telephone'] = $r['international_phone_number'];
		}
		if ( ! empty( $r['website'] ) ) {
			$out['website'] = $r['website'];
		}
		if ( ! empty( $r['geometry']['location']['lat'] ) ) {
			$out['lat'] = round( (float) $r['geometry']['location']['lat'], 6 );
			$out['lng'] = round( (float) $r['geometry']['location']['lng'], 6 );
		}
		if ( ! empty( $r['address_components'] ) && is_array( $r['address_components'] ) ) {
			$pick = function ( $types ) use ( $r ) {
				foreach ( $r['address_components'] as $c ) {
					foreach ( (array) $types as $t ) {
						if ( in_array( $t, (array) $c['types'], true ) ) {
							return $c;
						}
					}
				}
				return null;
			};
			$num   = $pick( 'street_number' );
			$route = $pick( 'route' );
			$street = trim( ( $num ? $num['long_name'] . ' ' : '' ) . ( $route ? $route['long_name'] : '' ) );
			if ( '' !== $street ) {
				$out['street'] = $street;
			}
			$loc = $pick( array( 'locality', 'postal_town' ) );
			if ( $loc ) {
				$out['locality'] = $loc['long_name'];
			}
			$reg = $pick( 'administrative_area_level_1' );
			if ( $reg ) {
				$out['region'] = $reg['short_name'];
			}
			$pc = $pick( 'postal_code' );
			if ( $pc ) {
				$out['postal_code'] = $pc['long_name'];
			}
			$co = $pick( 'country' );
			if ( $co ) {
				$out['country'] = $co['short_name'];
			}
		}
		if ( ! empty( $r['opening_hours']['periods'] ) ) {
			$hours = self::periods_to_hours( $r['opening_hours']['periods'] );
			if ( '' !== $hours ) {
				$out['opening_hours'] = $hours;
			}
		}
		return $out;
	}

	/**
	 * Convert a Google Places `opening_hours.periods` array into the plugin's
	 * opening-hours format (one rule per line, e.g. "Mo 08:00-20:00"), which the
	 * schema generator turns into OpeningHoursSpecification. Uses the structured
	 * `periods` (24h, locale-free) rather than the localized weekday_text.
	 *
	 * @param array $periods Google periods.
	 * @return string
	 */
	public static function periods_to_hours( $periods ) {
		if ( ! is_array( $periods ) || empty( $periods ) ) {
			return '';
		}
		$codes = array( 0 => 'Su', 1 => 'Mo', 2 => 'Tu', 3 => 'We', 4 => 'Th', 5 => 'Fr', 6 => 'Sa' );

		// Open 24/7: a single period with open time 0000 and no close.
		if ( 1 === count( $periods ) && empty( $periods[0]['close'] ) && isset( $periods[0]['open']['time'] ) && '0000' === (string) $periods[0]['open']['time'] ) {
			return 'Mo-Su 00:00-23:59';
		}

		$by_day = array();
		foreach ( $periods as $p ) {
			if ( ! isset( $p['open']['day'], $p['open']['time'] ) ) {
				continue;
			}
			$open  = self::hhmm( $p['open']['time'] );
			$close = isset( $p['close']['time'] ) ? self::hhmm( $p['close']['time'] ) : '23:59';
			if ( '' === $open ) {
				continue;
			}
			$by_day[ (int) $p['open']['day'] ][] = $open . '-' . $close;
		}

		$lines = array();
		foreach ( array( 1, 2, 3, 4, 5, 6, 0 ) as $d ) { // Monday-first for readability.
			if ( empty( $by_day[ $d ] ) ) {
				continue;
			}
			foreach ( $by_day[ $d ] as $range ) {
				$lines[] = $codes[ $d ] . ' ' . $range;
			}
		}
		return implode( "\n", $lines );
	}

	/**
	 * Normalise a Google "HHMM" time to "HH:MM".
	 *
	 * @param string $t Time.
	 * @return string
	 */
	public static function hhmm( $t ) {
		$t = preg_replace( '/\D/', '', (string) $t );
		if ( strlen( $t ) < 3 ) {
			return '';
		}
		$t = str_pad( $t, 4, '0', STR_PAD_LEFT );
		return substr( $t, 0, 2 ) . ':' . substr( $t, 2, 2 );
	}

	/**
	 * Geocode a free-form address to coordinates via OpenStreetMap Nominatim
	 * (free, no API key). Cached for a week. Override with the `spr_geocode`
	 * filter to plug in a different provider (e.g. Google).
	 *
	 * @param string $address Free-form address.
	 * @return array|WP_Error { lat, lon, display } on success.
	 */
	public function geocode( $address ) {
		$address = trim( (string) $address );
		if ( '' === $address ) {
			return new WP_Error( 'spr_geocode_empty', __( 'No address provided.', 'seo-sprinkler' ) );
		}

		$pre = apply_filters( 'spr_geocode', null, $address );
		if ( is_array( $pre ) ) {
			return $pre;
		}

		$cache_key = 'spr_geo_' . md5( $address );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		// add_query_arg() URL-encodes values for us — pass them raw (no pre-encoding).
		$url = add_query_arg(
			array(
				'q'      => $address,
				'format' => 'jsonv2',
				'limit'  => 1,
				'email'  => (string) get_option( 'admin_email' ),
			),
			'https://nominatim.openstreetmap.org/search'
		);

		$resp = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					// Nominatim usage policy requires an identifying User-Agent.
					'User-Agent' => 'SEO Sprinkler/' . SPR_VERSION . ' (' . home_url( '/' ) . ')',
					'Accept'     => 'application/json',
				),
			)
		);
		if ( is_wp_error( $resp ) ) {
			return $resp;
		}
		if ( 200 !== (int) wp_remote_retrieve_response_code( $resp ) ) {
			return new WP_Error( 'spr_geocode_http', __( 'The geocoding service did not respond. Try again shortly.', 'seo-sprinkler' ) );
		}
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( empty( $data[0]['lat'] ) || empty( $data[0]['lon'] ) ) {
			return new WP_Error( 'spr_geocode_none', __( 'No match found for that address.', 'seo-sprinkler' ) );
		}

		$out = array(
			'lat'     => round( (float) $data[0]['lat'], 6 ),
			'lon'     => round( (float) $data[0]['lon'], 6 ),
			'display' => isset( $data[0]['display_name'] ) ? (string) $data[0]['display_name'] : '',
		);
		set_transient( $cache_key, $out, WEEK_IN_SECONDS );
		return $out;
	}
}
