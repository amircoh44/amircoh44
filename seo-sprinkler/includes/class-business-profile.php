<?php
/**
 * Business profile storage — the "tell us about your business" questionnaire.
 *
 * Everything the schema generator needs to build a complete, accurate
 * Organization / LocalBusiness graph lives here in a single option.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_Business_Profile
 */
class SPR_Business_Profile {

	/** Option key. */
	const OPTION = 'spr_business_profile';

	/** Runtime cache. */
	protected static $cache = null;

	/**
	 * Field map: key => sanitiser type.
	 *
	 * @return array<string,string>
	 */
	public static function fields() {
		return array(
			// Identity.
			'business_type'     => 'key',    // Organization|LocalBusiness|Person.
			'local_subtype'     => 'text',   // e.g. Plumber, Locksmith, Restaurant.
			'name'              => 'text',
			'alternate_name'    => 'text',
			'legal_name'        => 'text',
			'description'       => 'textarea',
			'url'               => 'url',
			'logo_id'           => 'int',
			'image_id'          => 'int',

			// Contact.
			'email'             => 'email',
			'telephone'         => 'text',
			'contact_type'      => 'text',   // e.g. customer service.

			// Address.
			'street'            => 'text',
			'locality'          => 'text',   // City.
			'region'            => 'text',   // State/region.
			'postal_code'       => 'text',
			'country'           => 'text',   // ISO code or name.

			// Geo.
			'latitude'          => 'float',
			'longitude'         => 'float',

			// Business details.
			'price_range'       => 'text',   // e.g. $$.
			'opening_hours'     => 'textarea', // One rule per line: "Mo-Fr 09:00-17:00".
			'area_served'       => 'text',   // Comma list.
			'founder'           => 'text',
			'founding_date'     => 'text',   // YYYY or YYYY-MM-DD.
			'vat_id'            => 'text',

			// Integrations.
			'places_api_key'    => 'text',   // Optional Google Places API key for "Fill from Google".

			// Social profiles (sameAs).
			'facebook'          => 'url',
			'instagram'         => 'url',
			'twitter'           => 'url',
			'linkedin'          => 'url',
			'youtube'           => 'url',
			'tiktok'            => 'url',
			'pinterest'         => 'url',
			'extra_profiles'    => 'textarea', // One profile URL per line.
		);
	}

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		$d = array();
		foreach ( self::fields() as $key => $type ) {
			$d[ $key ] = ( 'int' === $type || 'float' === $type ) ? 0 : '';
		}
		$d['business_type'] = 'Organization';
		$d['url']           = home_url( '/' );
		return $d;
	}

	/**
	 * Get the full profile.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored      = get_option( self::OPTION, array() );
			$stored      = is_array( $stored ) ? $stored : array();
			self::$cache = wp_parse_args( $stored, self::defaults() );
		}
		return self::$cache;
	}

	/**
	 * Get one field.
	 *
	 * @param string $key     Field.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( $key, $default = '' ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Clear the runtime cache.
	 */
	public static function flush_cache() {
		self::$cache = null;
	}

	/**
	 * Persist a sanitised profile from raw input.
	 *
	 * @param array $input Raw input.
	 */
	public static function save( $input ) {
		update_option( self::OPTION, self::sanitize( $input ) );
		self::flush_cache();
	}

	/**
	 * Sanitise raw profile input by field type.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$clean = array();

		foreach ( self::fields() as $key => $type ) {
			$raw = isset( $input[ $key ] ) ? $input[ $key ] : '';
			switch ( $type ) {
				case 'int':
					$clean[ $key ] = absint( $raw );
					break;
				case 'float':
					$clean[ $key ] = ( '' === $raw ) ? '' : (float) $raw;
					break;
				case 'url':
					$clean[ $key ] = ( '' === trim( (string) $raw ) ) ? '' : esc_url_raw( trim( (string) $raw ) );
					break;
				case 'email':
					$clean[ $key ] = sanitize_email( (string) $raw );
					break;
				case 'textarea':
					$clean[ $key ] = sanitize_textarea_field( (string) $raw );
					break;
				case 'key':
					$clean[ $key ] = sanitize_text_field( (string) $raw );
					break;
				default:
					$clean[ $key ] = sanitize_text_field( (string) $raw );
			}
		}

		// Constrain business type.
		if ( ! in_array( $clean['business_type'], array( 'Organization', 'LocalBusiness', 'Person' ), true ) ) {
			$clean['business_type'] = 'Organization';
		}

		return $clean;
	}

	/* ---------------------------------------------------------------------
	 * Derived helpers used by the schema generator + audit
	 * ------------------------------------------------------------------- */

	/**
	 * Is the minimum needed for a valid Organization present?
	 *
	 * @return bool
	 */
	public static function is_complete() {
		return '' !== trim( (string) self::get( 'name' ) ) && '' !== trim( (string) self::get( 'url' ) );
	}

	/**
	 * Resolve the effective schema @type for the organisation node.
	 *
	 * @return string
	 */
	public static function schema_type() {
		$type = self::get( 'business_type', 'Organization' );
		if ( 'LocalBusiness' === $type ) {
			$sub = trim( (string) self::get( 'local_subtype' ) );
			if ( '' !== $sub ) {
				return preg_replace( '/[^A-Za-z]/', '', $sub ); // e.g. "Plumber".
			}
		}
		return $type;
	}

	/**
	 * Collect non-empty social-profile URLs (sameAs).
	 *
	 * @return string[]
	 */
	public static function same_as() {
		$urls = array();
		foreach ( array( 'facebook', 'instagram', 'twitter', 'linkedin', 'youtube', 'tiktok', 'pinterest' ) as $k ) {
			$v = trim( (string) self::get( $k ) );
			if ( '' !== $v ) {
				$urls[] = $v;
			}
		}
		$extra = trim( (string) self::get( 'extra_profiles' ) );
		if ( '' !== $extra ) {
			foreach ( preg_split( '/\r\n|\r|\n/', $extra ) as $line ) {
				$line = trim( $line );
				if ( '' !== $line ) {
					$urls[] = $line;
				}
			}
		}
		return array_values( array_unique( $urls ) );
	}

	/**
	 * Parse the opening-hours textarea into schema OpeningHoursSpecification.
	 *
	 * Accepts lines like "Mo-Fr 09:00-17:00" or "Saturday 10:00-14:00".
	 *
	 * @return array[]
	 */
	public static function opening_hours_spec() {
		$raw = trim( (string) self::get( 'opening_hours' ) );
		if ( '' === $raw ) {
			return array();
		}

		$days = array(
			'mo' => 'Monday', 'tu' => 'Tuesday', 'we' => 'Wednesday', 'th' => 'Thursday',
			'fr' => 'Friday', 'sa' => 'Saturday', 'su' => 'Sunday',
		);
		$order = array_values( $days );
		$specs = array();

		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( $line );
			if ( '' === $line || ! preg_match( '/^([A-Za-z]{2,9})(?:\s*-\s*([A-Za-z]{2,9}))?\s+(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/', $line, $m ) ) {
				continue;
			}
			$start = strtolower( substr( $m[1], 0, 2 ) );
			$end   = '' !== $m[2] ? strtolower( substr( $m[2], 0, 2 ) ) : $start;
			if ( ! isset( $days[ $start ], $days[ $end ] ) ) {
				continue;
			}
			$i = array_search( $days[ $start ], $order, true );
			$j = array_search( $days[ $end ], $order, true );
			$day_names = array();
			for ( $k = $i; ; $k = ( $k + 1 ) % 7 ) {
				$day_names[] = $order[ $k ];
				if ( $k === $j ) {
					break;
				}
			}
			$specs[] = array(
				'@type'     => 'OpeningHoursSpecification',
				'dayOfWeek' => $day_names,
				'opens'     => $m[3],
				'closes'    => $m[4],
			);
		}

		return $specs;
	}
}
