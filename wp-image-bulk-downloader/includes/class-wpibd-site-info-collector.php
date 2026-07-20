<?php
/**
 * Gathers site-wide information — verification codes, tracking IDs,
 * SEO plugin settings, active theme, and active plugins.
 *
 * Verification and tracking values are extracted from the live home
 * page HTML (via wp_remote_get) so we catch codes regardless of where
 * they were added — theme, snippet plugin, SEO plugin, or GTM.
 *
 * @package WP_Image_Bulk_Downloader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPIBD_Site_Info_Collector {

	private $home_html = null;

	public function collect() {
		return array(
			'exported_at'    => gmdate( 'c' ),
			'exported_by'    => $this->current_user_login(),
			'plugin_version' => defined( 'WPIBD_VERSION' ) ? WPIBD_VERSION : '',
			'site'           => $this->collect_site_basics(),
			'verifications'  => $this->collect_verifications(),
			'analytics'      => $this->collect_analytics(),
			'seo_plugins'    => $this->collect_seo_plugins(),
			'active_theme'   => $this->collect_active_theme(),
			'active_plugins' => $this->collect_active_plugins(),
		);
	}

	public function format_as_text( array $info ) {
		$lines   = array();
		$lines[] = '=== Site Info Export ===';
		$lines[] = 'Exported at   : ' . $info['exported_at'];
		$lines[] = 'Exported by   : ' . $info['exported_by'];
		$lines[] = 'Plugin version: ' . $info['plugin_version'];

		$lines[] = '';
		$lines[] = '--- Site basics ---';
		foreach ( $info['site'] as $k => $v ) {
			$lines[] = $this->pad( $k, 18 ) . ': ' . $this->stringify( $v );
		}

		$lines[] = '';
		$lines[] = '--- Verification codes (found in <head>) ---';
		if ( empty( $info['verifications'] ) ) {
			$lines[] = '(none detected)';
		} else {
			foreach ( $info['verifications'] as $key => $v ) {
				$label = $this->pad( $key, 28 );
				if ( is_array( $v ) && isset( $v['value'] ) ) {
					$lines[] = $label . ': ' . $v['value'] . '   [' . $v['meta_name'] . ']';
				} else {
					$lines[] = $label . ': ' . $this->stringify( $v );
				}
			}
		}

		$lines[] = '';
		$lines[] = '--- Analytics / tracking ---';
		if ( empty( $info['analytics'] ) ) {
			$lines[] = '(none detected)';
		} else {
			foreach ( $info['analytics'] as $key => $v ) {
				$lines[] = $this->pad( $key, 28 ) . ': ' . $this->stringify( $v );
			}
		}

		$lines[] = '';
		$lines[] = '--- SEO plugin settings ---';
		if ( empty( $info['seo_plugins'] ) ) {
			$lines[] = '(none detected)';
		} else {
			foreach ( $info['seo_plugins'] as $plugin => $data ) {
				$lines[] = '[' . $plugin . ']';
				foreach ( (array) $data as $k => $v ) {
					$lines[] = '  ' . $k . ': ' . $this->stringify( $v );
				}
			}
		}

		$lines[] = '';
		$lines[] = '--- Active theme ---';
		foreach ( $info['active_theme'] as $k => $v ) {
			$lines[] = $this->pad( $k, 12 ) . ': ' . $this->stringify( $v );
		}

		$lines[] = '';
		$lines[] = '--- Active plugins (' . count( $info['active_plugins'] ) . ') ---';
		foreach ( $info['active_plugins'] as $p ) {
			$lines[] = '- ' . $p['name'] . '  (' . $p['slug'] . ')  v' . $p['version'];
		}

		return implode( "\n", $lines ) . "\n";
	}

	private function collect_site_basics() {
		return array(
			'name'           => get_bloginfo( 'name' ),
			'description'    => get_bloginfo( 'description' ),
			'home_url'       => home_url(),
			'site_url'       => site_url(),
			'admin_email'    => get_option( 'admin_email' ),
			'timezone'       => function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : get_option( 'timezone_string' ),
			'language'       => get_locale(),
			'charset'        => get_bloginfo( 'charset' ),
			'wp_version'     => get_bloginfo( 'version' ),
			'php_version'    => PHP_VERSION,
			'is_multisite'   => is_multisite(),
			'permalink'      => get_option( 'permalink_structure' ),
			'blog_public'    => (int) get_option( 'blog_public' ),
			'date_format'    => get_option( 'date_format' ),
			'time_format'    => get_option( 'time_format' ),
			'start_of_week'  => (int) get_option( 'start_of_week' ),
		);
	}

	private function collect_verifications() {
		$html    = $this->get_home_html();
		$result  = array();

		$targets = array(
			'google_search_console'    => 'google-site-verification',
			'bing_webmaster'           => 'msvalidate.01',
			'yandex_webmaster'         => 'yandex-verification',
			'baidu_webmaster'          => 'baidu-site-verification',
			'pinterest_domain'         => 'p:domain_verify',
			'facebook_domain'          => 'facebook-domain-verification',
			'norton_safeweb'           => 'norton-safeweb-site-verification',
			'ahrefs_verification'      => 'ahrefs-site-verification',
			'semrush_verification'     => 'semrush-verification',
			'alexa_verification'       => 'alexaVerifyID',
			'shopify_verification'     => 'shopify-checkout-api-token',
		);

		foreach ( $targets as $key => $meta_name ) {
			$value = $this->extract_meta( $html, $meta_name );
			if ( null !== $value ) {
				$result[ $key ] = array(
					'meta_name' => $meta_name,
					'value'     => $value,
				);
			}
		}

		return $result;
	}

	private function collect_analytics() {
		$html   = $this->get_home_html();
		$result = array();

		if ( $html ) {
			$patterns = array(
				'google_gtag_ids'              => '/gtag\/js\?id=([A-Z0-9\-_]+)/i',
				'google_tag_manager_ids'       => '/(?:googletagmanager\.com\/gtm\.js\?id=|GTM-)([A-Z0-9]{5,})/i',
				'universal_analytics_ids'      => '/UA-\d{4,}-\d+/',
				'ga4_measurement_ids'          => '/G-[A-Z0-9]{6,}/',
				'google_ads_ids'               => '/AW-\d{4,}/',
				'google_dv360_ids'             => '/DC-\d{4,}/',
				'microsoft_uet_ids'            => '/bat\.js["\'\s].{0,80}?ti\s*[:=]\s*["\'](\d+)["\']/is',
				'microsoft_clarity_project_id' => '/clarity\.ms\/tag\/([a-z0-9]+)/i',
				'hotjar_site_id'               => '/hotjar[^{]*?hjid\s*[:=]\s*(\d+)/i',
				'facebook_pixel_ids'           => '/fbq\(\s*[\'"]init[\'"]\s*,\s*[\'"](\d+)[\'"]/i',
				'tiktok_pixel_ids'             => '/ttq\.load\(\s*[\'"]([A-Z0-9]+)[\'"]/i',
				'linkedin_partner_ids'         => '/_linkedin_partner_id\s*=\s*[\'"]?(\d+)[\'"]?/i',
				'pinterest_tag_ids'            => '/pintrk\(\s*[\'"]load[\'"]\s*,\s*[\'"](\d+)[\'"]/i',
			);

			foreach ( $patterns as $key => $regex ) {
				if ( preg_match_all( $regex, $html, $m ) ) {
					$values = ! empty( $m[1] ) ? $m[1] : $m[0];
					$values = array_values( array_unique( array_filter( array_map( 'trim', (array) $values ) ) ) );
					if ( ! empty( $values ) ) {
						$result[ $key ] = count( $values ) === 1 ? $values[0] : $values;
					}
				}
			}
		}

		$site_kit_sc = get_option( 'googlesitekit_search-console_settings' );
		if ( is_array( $site_kit_sc ) && ! empty( $site_kit_sc ) ) {
			$result['google_site_kit_search_console'] = $this->pluck( $site_kit_sc, array( 'propertyID', 'ownerID' ) );
		}

		$site_kit_ga = get_option( 'googlesitekit_analytics-4_settings' );
		if ( is_array( $site_kit_ga ) && ! empty( $site_kit_ga ) ) {
			$result['google_site_kit_analytics'] = $this->pluck( $site_kit_ga, array( 'measurementID', 'propertyID', 'accountID', 'webDataStreamID' ) );
		}

		$monsterinsights = get_option( 'monsterinsights_settings' );
		if ( is_array( $monsterinsights ) ) {
			$result['monsterinsights'] = $this->pluck( $monsterinsights, array( 'manual_ua_code', 'analytics_profile', 'measurement_protocol_secret' ) );
		}

		return $result;
	}

	private function collect_seo_plugins() {
		$result = array();

		$yoast = get_option( 'wpseo' );
		if ( is_array( $yoast ) ) {
			$result['yoast_seo'] = $this->pluck(
				$yoast,
				array(
					'googleverify', 'msverify', 'yandexverify', 'baiduverify', 'pinterestverify',
					'website_name', 'company_name', 'company_or_person', 'person_name',
					'company_logo', 'person_logo',
				)
			);
		}

		$rank_math = get_option( 'rank_math_options_general' );
		if ( is_array( $rank_math ) ) {
			$result['rank_math'] = $this->pluck(
				$rank_math,
				array(
					'console_google_verify', 'console_bing_verify', 'console_yandex_verify',
					'console_baidu_verify', 'console_pinterest_verify', 'console_norton_verify',
					'analytics_ga4_code', 'analytics_google_analytics_code',
				)
			);
		}

		$aioseo_raw = get_option( 'aioseo_options' );
		if ( ! empty( $aioseo_raw ) ) {
			$aioseo = is_string( $aioseo_raw ) ? json_decode( $aioseo_raw, true ) : $aioseo_raw;
			if ( is_array( $aioseo ) && isset( $aioseo['webmasterTools'] ) ) {
				$result['all_in_one_seo'] = $aioseo['webmasterTools'];
			}
		}

		$seopress_google = get_option( 'seopress_advanced_option_name' );
		if ( is_array( $seopress_google ) ) {
			$result['seopress'] = $this->pluck(
				$seopress_google,
				array(
					'seopress_advanced_google', 'seopress_advanced_google_verif',
					'seopress_advanced_bing_verif', 'seopress_advanced_pinterest_verif',
					'seopress_advanced_yandex_verif', 'seopress_advanced_baidu_verif',
				)
			);
		}

		return array_filter( $result );
	}

	private function collect_active_theme() {
		$theme = wp_get_theme();
		return array(
			'name'     => $theme->get( 'Name' ),
			'version'  => $theme->get( 'Version' ),
			'template' => $theme->get_template(),
			'author'   => wp_strip_all_tags( (string) $theme->get( 'Author' ) ),
			'text_domain' => $theme->get( 'TextDomain' ),
		);
	}

	private function collect_active_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$active = (array) get_option( 'active_plugins', array() );
		$all    = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$out    = array();
		foreach ( $active as $slug ) {
			$data  = isset( $all[ $slug ] ) ? $all[ $slug ] : array();
			$out[] = array(
				'slug'    => $slug,
				'name'    => isset( $data['Name'] ) ? $data['Name'] : '',
				'version' => isset( $data['Version'] ) ? $data['Version'] : '',
				'author'  => isset( $data['Author'] ) ? wp_strip_all_tags( (string) $data['Author'] ) : '',
			);
		}
		return $out;
	}

	private function get_home_html() {
		if ( null !== $this->home_html ) {
			return $this->home_html;
		}

		$response = wp_remote_get(
			home_url( '/' ),
			array(
				'timeout'     => 20,
				'redirection' => 3,
				'sslverify'   => false,
				'user-agent'  => 'WPImageBulkDownloader/' . ( defined( 'WPIBD_VERSION' ) ? WPIBD_VERSION : '1.0' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->home_html = '';
		} else {
			$this->home_html = (string) wp_remote_retrieve_body( $response );
		}

		return $this->home_html;
	}

	private function extract_meta( $html, $meta_name ) {
		if ( ! $html ) {
			return null;
		}
		$escaped  = preg_quote( $meta_name, '/' );
		$patterns = array(
			'/<meta[^>]+name=["\']' . $escaped . '["\'][^>]+content=["\']([^"\']+)["\']/i',
			'/<meta[^>]+content=["\']([^"\']+)["\'][^>]+name=["\']' . $escaped . '["\']/i',
		);
		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $html, $m ) ) {
				return trim( $m[1] );
			}
		}
		return null;
	}

	private function pluck( array $source, array $keys ) {
		$out = array();
		foreach ( $keys as $k ) {
			if ( isset( $source[ $k ] ) && '' !== $source[ $k ] && array() !== $source[ $k ] ) {
				$out[ $k ] = $source[ $k ];
			}
		}
		return $out;
	}

	private function current_user_login() {
		$user = wp_get_current_user();
		return $user && $user->exists() ? $user->user_login : '(unknown)';
	}

	private function pad( $str, $width ) {
		return str_pad( (string) $str, $width, ' ', STR_PAD_RIGHT );
	}

	private function stringify( $value ) {
		if ( is_bool( $value ) ) {
			return $value ? 'yes' : 'no';
		}
		if ( is_scalar( $value ) ) {
			return (string) $value;
		}
		return wp_json_encode( $value );
	}
}
