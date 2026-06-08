<?php
/**
 * License client — turns an entered key into an edition by asking the
 * SEO Sprinkler license server (the companion Flask app).
 *
 * It answers the `spr_validate_license` filter that SPR_Edition::activate_key()
 * applies when a key is saved, and re-validates daily so revoked/expired keys
 * downgrade on their own. With no server configured (or a network error) the
 * current edition is preserved, so nothing breaks.
 *
 * The server URL comes from the SPR_LICENSE_SERVER constant if defined,
 * otherwise the "License server URL" setting.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_License_Client
 */
class SPR_License_Client {

	/**
	 * Register hooks.
	 */
	public function init() {
		add_filter( 'spr_validate_license', array( $this, 'resolve' ), 10, 2 );
		add_action( 'spr_daily_refresh_event', array( $this, 'revalidate' ) );
	}

	/**
	 * Configured license server base URL (no trailing slash), or '' if unset.
	 *
	 * @return string
	 */
	public function server_url() {
		if ( defined( 'SPR_LICENSE_SERVER' ) && SPR_LICENSE_SERVER ) {
			$url = SPR_LICENSE_SERVER;
		} else {
			$url = SPR_Settings::get( 'license_server_url', '' );
		}
		return $url ? untrailingslashit( (string) $url ) : '';
	}

	/**
	 * Filter callback: resolve an entered key into free|pro|expert.
	 *
	 * @param string $edition Current/fallback edition.
	 * @param string $key     Entered license key.
	 * @return string
	 */
	public function resolve( $edition, $key ) {
		$key    = trim( (string) $key );
		$server = $this->server_url();
		if ( '' === $key || '' === $server ) {
			return $edition;
		}
		$resp = $this->post( $server . '/api/v1/activate', $key );
		return $this->edition_from_response( $resp, $edition );
	}

	/**
	 * Daily cron: re-check the stored key so revocations/expiries take effect.
	 */
	public function revalidate() {
		$key    = trim( (string) SPR_Settings::get( 'license_key', '' ) );
		$server = $this->server_url();
		if ( '' === $key || '' === $server ) {
			return;
		}
		$resp    = $this->post( $server . '/api/v1/validate', $key );
		$edition = $this->edition_from_response( $resp, null );
		if ( null !== $edition ) {
			update_option( SPR_Edition::OPTION, $edition );
		}
	}

	/**
	 * POST a key + this site's identity to the license server.
	 *
	 * @param string $endpoint Full URL.
	 * @param string $key      License key.
	 * @return array|WP_Error
	 */
	protected function post( $endpoint, $key ) {
		return wp_remote_post(
			$endpoint,
			array(
				'timeout' => 15,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'key'       => $key,
						'site_url'  => home_url( '/' ),
						'site_name' => get_bloginfo( 'name' ),
					)
				),
			)
		);
	}

	/**
	 * Extract a valid edition from an HTTP response, else the fallback.
	 *
	 * @param array|WP_Error $resp     Response.
	 * @param string|null    $fallback Returned when the response is unusable.
	 * @return string|null
	 */
	protected function edition_from_response( $resp, $fallback ) {
		if ( is_wp_error( $resp ) ) {
			return $fallback;
		}
		$data = json_decode( wp_remote_retrieve_body( $resp ), true );
		if ( ! is_array( $data ) || ! isset( $data['edition'] ) ) {
			return $fallback;
		}
		$edition = $data['edition'];
		return in_array( $edition, array( 'free', 'pro', 'expert' ), true ) ? $edition : $fallback;
	}
}
