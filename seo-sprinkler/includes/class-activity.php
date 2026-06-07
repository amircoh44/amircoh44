<?php
/**
 * Lightweight activity log.
 *
 * Records plugin actions (scans, image fills, cleans, …) so the Dashboard can
 * show a recent-history feed. Kept as the latest N entries in a single
 * autoload-disabled option — no custom table, no growth unbounded.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_Activity
 */
class SPR_Activity {

	const OPTION = 'spr_activity_log';
	const MAX    = 60;

	/**
	 * Record an event.
	 *
	 * @param string $type    Short type key (e.g. image_fill, image_scan, clean).
	 * @param string $message Human-readable summary.
	 * @param array  $meta    Optional numeric/string metadata.
	 */
	public static function log( $type, $message, $meta = array() ) {
		$entries = get_option( self::OPTION, array() );
		if ( ! is_array( $entries ) ) {
			$entries = array();
		}
		array_unshift(
			$entries,
			array(
				'type'    => sanitize_key( $type ),
				'message' => wp_strip_all_tags( (string) $message ),
				'meta'    => is_array( $meta ) ? $meta : array(),
				'time'    => time(),
				'user'    => get_current_user_id(),
			)
		);
		update_option( self::OPTION, array_slice( $entries, 0, self::MAX ), false );
	}

	/**
	 * The most recent entries (newest first).
	 *
	 * @param int $limit Max entries.
	 * @return array[]
	 */
	public static function recent( $limit = 15 ) {
		$entries = get_option( self::OPTION, array() );
		if ( ! is_array( $entries ) ) {
			return array();
		}
		return array_slice( $entries, 0, max( 1, (int) $limit ) );
	}

	/**
	 * Forget all logged activity.
	 */
	public static function clear() {
		delete_option( self::OPTION );
	}
}
