<?php
/**
 * Project handover exporter — writes CSV spreadsheets, a redacted
 * connections dossier, and a database inventory into the ZIP so a
 * developer receiving the archive can understand and rebuild the
 * WordPress site elsewhere (e.g. in Astro).
 *
 * @package WP_Image_Bulk_Downloader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPIBD_Project_Handover {

	private $redact_credentials;

	public function __construct( $redact_credentials = true ) {
		$this->redact_credentials = (bool) $redact_credentials;
	}

	public function write_all( ZipArchive $zip ) {
		$this->write_spreadsheets( $zip );
		$this->write_connections( $zip );
		$this->write_database_inventory( $zip );
		$this->write_readme( $zip );
	}

	private function write_spreadsheets( ZipArchive $zip ) {
		$zip->addFromString( 'project-handover/spreadsheets/project-overview.csv',   $this->csv( $this->project_overview() ) );
		$zip->addFromString( 'project-handover/spreadsheets/content-inventory.csv',  $this->csv( $this->content_inventory() ) );
		$zip->addFromString( 'project-handover/spreadsheets/plugin-inventory.csv',   $this->csv( $this->plugin_inventory() ) );
		$zip->addFromString( 'project-handover/spreadsheets/integration-inventory.csv', $this->csv( $this->integration_inventory() ) );
		$zip->addFromString( 'project-handover/spreadsheets/menu-inventory.csv',     $this->csv( $this->menu_inventory() ) );
		$zip->addFromString( 'project-handover/spreadsheets/taxonomy-inventory.csv', $this->csv( $this->taxonomy_inventory() ) );
		$zip->addFromString( 'project-handover/spreadsheets/redirect-inventory.csv', $this->csv( $this->redirect_inventory() ) );
		$zip->addFromString( 'project-handover/spreadsheets/user-inventory.csv',     $this->csv( $this->user_inventory() ) );
	}

	private function write_connections( ZipArchive $zip ) {
		$conn = $this->collect_connections();

		$json = wp_json_encode( $conn, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false !== $json ) {
			$zip->addFromString( 'project-handover/connections/connections.json', $json );
		}
		$zip->addFromString( 'project-handover/connections/connections.txt', $this->format_connections_text( $conn ) );

		$security_readme = <<<MD
# Connections dossier — security note

Credentials in this bundle are **redacted by default**. Every value marked
`***REDACTED***` was present on the source site but stripped from this
export. To retrieve the real values:

- **Database password** — look in the source site's `wp-config.php`
  (`DB_PASSWORD`) or your host's control panel.
- **API keys / secrets / SMTP passwords** — look in the source site's
  WordPress admin, or in `wp_options` (values are stored serialised).
- **Payment gateway keys** — in the source site's WooCommerce / gateway
  settings.

**Never share this bundle over unencrypted channels or public repos if
credentials are un-redacted.** The plugin author is not liable for leaked
secrets.
MD;
		$zip->addFromString( 'project-handover/connections/README-security.md', $security_readme );
	}

	private function write_database_inventory( ZipArchive $zip ) {
		global $wpdb;

		$tables = $wpdb->get_col( "SHOW TABLES" );
		if ( ! is_array( $tables ) ) {
			$tables = array();
		}

		$schema = array();
		foreach ( $tables as $table ) {
			$columns    = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}`", ARRAY_A );
			$row_count  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
			$schema[] = array(
				'table'      => $table,
				'row_count'  => $row_count,
				'columns'    => array_map(
					function ( $c ) {
						return array(
							'name'    => $c['Field'],
							'type'    => $c['Type'],
							'null'    => $c['Null'],
							'key'     => $c['Key'],
							'default' => $c['Default'],
							'extra'   => $c['Extra'],
						);
					},
					is_array( $columns ) ? $columns : array()
				),
			);
		}

		$json = wp_json_encode( $schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false !== $json ) {
			$zip->addFromString( 'project-handover/database/schema-overview.json', $json );
		}

		$options_inventory = $this->options_inventory();
		$json = wp_json_encode( $options_inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false !== $json ) {
			$zip->addFromString( 'project-handover/database/options-inventory.json', $json );
		}

		$zip->addFromString( 'project-handover/database/tables-summary.csv', $this->csv( $this->tables_summary_rows( $schema ) ) );
	}

	private function write_readme( ZipArchive $zip ) {
		$site = get_bloginfo( 'name' );
		$md   = <<<MD
# Project handover — {$site}

This folder is a snapshot of everything a developer needs to understand
and rebuild this WordPress site elsewhere.

## What's inside

### `spreadsheets/`
CSV files you can open in Excel, Google Sheets, or Numbers.

- `project-overview.csv` — one-page summary: site name, URL, WP + PHP
  versions, counts of posts / pages / media / users / plugins / menus /
  redirects.
- `content-inventory.csv` — every post, page, and public custom post
  type with ID, URL, status, author, date, word count, and featured
  image URL.
- `plugin-inventory.csv` — every active plugin with slug, version, and
  author.
- `integration-inventory.csv` — third-party integrations detected on the
  site (payment gateways, forms, analytics, SMTP, page builders).
- `menu-inventory.csv` — every nav menu item across every registered
  menu location.
- `taxonomy-inventory.csv` — every category, tag, and custom taxonomy
  term with post counts.
- `redirect-inventory.csv` — redirects managed by Rank Math (source →
  target → HTTP code).
- `user-inventory.csv` — every user with role, post count, and
  registration date (email is redacted unless you re-run the export
  with credentials).

### `connections/`
- `connections.json` — machine-readable connection dossier.
- `connections.txt` — same dossier, human-readable.
- `README-security.md` — credential handling notes.

Contents: database host / name / user, `WP_HOME` / `WP_SITEURL`, SMTP
settings (from common plugins), payment gateway keys, form service
integrations, active analytics services. **Secrets are redacted by
default.**

### `database/`
- `schema-overview.json` — every table in the database with its row
  count and column definitions.
- `options-inventory.json` — non-transient `wp_options` rows relevant to
  site configuration (site URL, SEO plugin settings, analytics IDs,
  active theme, active plugins list, permalink structure, etc.).
- `tables-summary.csv` — quick spreadsheet of tables and their row
  counts, sorted largest first.

This is **not** a full mysqldump. For a full DB backup use your host's
backup tools (WP Engine, Kinsta, etc. all provide one-click dumps) or
run `mysqldump` locally against the source database.

## Suggested handover checklist

1. Read `project-overview.csv` — you'll know the site's shape in a
   minute.
2. Skim `plugin-inventory.csv` and `integration-inventory.csv` — flag
   anything you'll need to re-implement in the new stack.
3. Open `content-inventory.csv` and decide which posts / pages need to
   migrate.
4. For each Astro migration, use `src/content/` and `src/data/` next to
   this folder — see the top-level `README.md`.
5. Ask the source-site owner for the credentials that were redacted from
   `connections/` before you switch DNS.
MD;
		$zip->addFromString( 'project-handover/README.md', $md );
	}

	private function project_overview() {
		global $wpdb;

		$counts_posts     = wp_count_posts( 'post' );
		$counts_pages     = wp_count_posts( 'page' );
		$counts_media     = wp_count_attachments( 'image' );
		$counts_users     = count_users();
		$counts_menus     = count( (array) wp_get_nav_menus() );
		$counts_redirects = 0;
		$rm_table         = $wpdb->prefix . 'rank_math_redirections';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rm_table ) ) ) {
			$counts_redirects = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$rm_table}" );
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$active = (array) get_option( 'active_plugins', array() );

		return array(
			array( 'field', 'value' ),
			array( 'Site name',              get_bloginfo( 'name' ) ),
			array( 'Site tagline',           get_bloginfo( 'description' ) ),
			array( 'Home URL',               home_url() ),
			array( 'Admin URL',              admin_url() ),
			array( 'Admin email',            get_option( 'admin_email' ) ),
			array( 'Language',               get_locale() ),
			array( 'Timezone',               function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : get_option( 'timezone_string' ) ),
			array( 'WordPress version',      get_bloginfo( 'version' ) ),
			array( 'PHP version',            PHP_VERSION ),
			array( 'MySQL version',          $wpdb->db_version() ),
			array( 'Active theme',           wp_get_theme()->get( 'Name' ) . ' ' . wp_get_theme()->get( 'Version' ) ),
			array( 'Multisite',              is_multisite() ? 'yes' : 'no' ),
			array( 'Search-engine visible',  ( '0' === (string) get_option( 'blog_public' ) ) ? 'no' : 'yes' ),
			array( 'Published posts',        (int) ( isset( $counts_posts->publish ) ? $counts_posts->publish : 0 ) ),
			array( 'Draft posts',            (int) ( isset( $counts_posts->draft ) ? $counts_posts->draft : 0 ) ),
			array( 'Published pages',        (int) ( isset( $counts_pages->publish ) ? $counts_pages->publish : 0 ) ),
			array( 'Media images',           array_sum( (array) $counts_media ) ),
			array( 'Registered users',       (int) ( isset( $counts_users['total_users'] ) ? $counts_users['total_users'] : 0 ) ),
			array( 'Active plugins',         count( $active ) ),
			array( 'Nav menus',              $counts_menus ),
			array( 'Rank Math redirects',    $counts_redirects ),
		);
	}

	private function content_inventory() {
		$rows       = array( array( 'id', 'post_type', 'title', 'slug', 'status', 'url', 'author', 'date', 'modified', 'word_count', 'featured_image_url', 'is_elementor' ) );
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		unset( $post_types['attachment'] );

		$query = new WP_Query(
			array(
				'post_type'      => array_values( $post_types ),
				'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
				'posts_per_page' => -1,
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);

		foreach ( $query->posts as $post ) {
			$author  = get_userdata( $post->post_author );
			$featured = get_post_thumbnail_id( $post->ID );
			$rows[] = array(
				$post->ID,
				$post->post_type,
				$post->post_title,
				$post->post_name,
				$post->post_status,
				get_permalink( $post ),
				$author ? $author->display_name : '',
				$post->post_date,
				$post->post_modified,
				str_word_count( wp_strip_all_tags( (string) $post->post_content ) ),
				$featured ? wp_get_attachment_url( $featured ) : '',
				get_post_meta( $post->ID, '_elementor_edit_mode', true ) ? 'yes' : 'no',
			);
		}
		return $rows;
	}

	private function plugin_inventory() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$rows   = array( array( 'slug', 'name', 'version', 'author', 'plugin_uri', 'network' ) );
		$active = (array) get_option( 'active_plugins', array() );
		$all    = get_plugins();
		foreach ( $active as $slug ) {
			$data   = isset( $all[ $slug ] ) ? $all[ $slug ] : array();
			$rows[] = array(
				$slug,
				isset( $data['Name'] ) ? $data['Name'] : '',
				isset( $data['Version'] ) ? $data['Version'] : '',
				isset( $data['Author'] ) ? wp_strip_all_tags( (string) $data['Author'] ) : '',
				isset( $data['PluginURI'] ) ? $data['PluginURI'] : '',
				isset( $data['Network'] ) && $data['Network'] ? 'yes' : 'no',
			);
		}
		return $rows;
	}

	private function integration_inventory() {
		$rows = array( array( 'category', 'service', 'detected_via', 'value_or_note' ) );

		$active = (array) get_option( 'active_plugins', array() );
		$active_map = array_flip( $active );

		$known_plugins = array(
			'seo'         => array(
				'wordpress-seo/wp-seo.php'                          => 'Yoast SEO',
				'seo-by-rank-math/rank-math.php'                    => 'Rank Math SEO',
				'all-in-one-seo-pack/all_in_one_seo_pack.php'       => 'All in One SEO',
				'wp-seopress/seopress.php'                          => 'SEOPress',
			),
			'analytics'   => array(
				'google-site-kit/google-site-kit.php'               => 'Google Site Kit',
				'google-analytics-for-wordpress/googleanalytics.php'=> 'MonsterInsights',
				'wp-analytify/wp-analytify.php'                     => 'Analytify',
			),
			'forms'       => array(
				'wpforms-lite/wpforms.php'                          => 'WPForms Lite',
				'wpforms/wpforms.php'                               => 'WPForms',
				'contact-form-7/wp-contact-form-7.php'              => 'Contact Form 7',
				'gravityforms/gravityforms.php'                     => 'Gravity Forms',
				'ninja-forms/ninja-forms.php'                       => 'Ninja Forms',
				'formidable/formidable.php'                         => 'Formidable Forms',
				'fluentform/fluentform.php'                         => 'Fluent Forms',
			),
			'ecommerce'   => array(
				'woocommerce/woocommerce.php'                       => 'WooCommerce',
				'easy-digital-downloads/easy-digital-downloads.php' => 'Easy Digital Downloads',
			),
			'page-builder' => array(
				'elementor/elementor.php'                           => 'Elementor',
				'elementor-pro/elementor-pro.php'                   => 'Elementor Pro',
				'beaver-builder-lite-version/fl-builder.php'        => 'Beaver Builder',
				'js_composer/js_composer.php'                       => 'WPBakery',
				'divi-builder/divi-builder.php'                     => 'Divi Builder',
				'oxygen/functions.php'                              => 'Oxygen',
				'bricks/functions.php'                              => 'Bricks',
			),
			'smtp'        => array(
				'wp-mail-smtp/wp_mail_smtp.php'                     => 'WP Mail SMTP',
				'post-smtp/postman-smtp.php'                        => 'Post SMTP',
				'fluent-smtp/fluent-smtp.php'                       => 'FluentSMTP',
			),
			'security'    => array(
				'wordfence/wordfence.php'                           => 'Wordfence',
				'sucuri-scanner/sucuri.php'                         => 'Sucuri Scanner',
			),
			'cache'       => array(
				'wp-super-cache/wp-cache.php'                       => 'WP Super Cache',
				'w3-total-cache/w3-total-cache.php'                 => 'W3 Total Cache',
				'wp-rocket/wp-rocket.php'                           => 'WP Rocket',
				'litespeed-cache/litespeed-cache.php'               => 'LiteSpeed Cache',
			),
			'backup'      => array(
				'updraftplus/updraftplus.php'                       => 'UpdraftPlus',
				'backwpup/backwpup.php'                             => 'BackWPup',
			),
			'membership'  => array(
				'memberpress/memberpress.php'                       => 'MemberPress',
				'paid-memberships-pro/paid-memberships-pro.php'     => 'Paid Memberships Pro',
			),
			'crm'         => array(
				'fluent-crm/fluent-crm.php'                         => 'FluentCRM',
				'groundhogg/groundhogg.php'                         => 'Groundhogg',
			),
		);

		foreach ( $known_plugins as $category => $plugins ) {
			foreach ( $plugins as $slug => $label ) {
				if ( isset( $active_map[ $slug ] ) ) {
					$rows[] = array( $category, $label, 'active plugin', $slug );
				}
			}
		}

		$smtp_options = array( 'wp_mail_smtp', 'postman_options', 'fluentmail-settings' );
		foreach ( $smtp_options as $opt ) {
			$v = get_option( $opt );
			if ( ! empty( $v ) ) {
				$host = '';
				if ( is_array( $v ) ) {
					foreach ( array( 'host', 'smtp_host', 'mailer' ) as $k ) {
						if ( ! empty( $v[ $k ] ) ) {
							$host = is_scalar( $v[ $k ] ) ? (string) $v[ $k ] : '';
							break;
						}
						if ( isset( $v['smtp'] ) && is_array( $v['smtp'] ) && ! empty( $v['smtp'][ $k ] ) ) {
							$host = (string) $v['smtp'][ $k ];
							break;
						}
					}
				}
				$rows[] = array( 'smtp', 'SMTP relay', 'option ' . $opt, $host );
			}
		}

		$stripe_key = get_option( 'woocommerce_stripe_settings' );
		if ( is_array( $stripe_key ) && ! empty( $stripe_key['enabled'] ) && 'yes' === $stripe_key['enabled'] ) {
			$rows[] = array( 'payment', 'Stripe (WooCommerce)', 'option woocommerce_stripe_settings', $this->redact( isset( $stripe_key['publishable_key'] ) ? $stripe_key['publishable_key'] : '' ) );
		}
		$paypal = get_option( 'woocommerce_ppcp-gateway_settings' );
		if ( is_array( $paypal ) && ! empty( $paypal['enabled'] ) && 'yes' === $paypal['enabled'] ) {
			$rows[] = array( 'payment', 'PayPal (WooCommerce)', 'option woocommerce_ppcp-gateway_settings', 'enabled' );
		}

		return $rows;
	}

	private function menu_inventory() {
		$rows  = array( array( 'menu', 'item_id', 'title', 'url', 'parent_id', 'order', 'type', 'object' ) );
		$menus = wp_get_nav_menus();
		if ( ! is_array( $menus ) ) {
			return $rows;
		}
		foreach ( $menus as $menu ) {
			$items = wp_get_nav_menu_items( $menu->term_id, array( 'update_post_term_cache' => false ) );
			if ( ! is_array( $items ) ) {
				continue;
			}
			foreach ( $items as $it ) {
				$rows[] = array(
					$menu->name,
					$it->ID,
					$it->title,
					$it->url,
					$it->menu_item_parent,
					$it->menu_order,
					$it->type,
					$it->object,
				);
			}
		}
		return $rows;
	}

	private function taxonomy_inventory() {
		$rows       = array( array( 'taxonomy', 'term_id', 'name', 'slug', 'parent', 'count' ) );
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		foreach ( $taxonomies as $tax ) {
			$terms = get_terms( array( 'taxonomy' => $tax->name, 'hide_empty' => false ) );
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			foreach ( $terms as $t ) {
				$rows[] = array( $tax->name, $t->term_id, $t->name, $t->slug, $t->parent, $t->count );
			}
		}
		return $rows;
	}

	private function redirect_inventory() {
		global $wpdb;
		$rows  = array( array( 'from', 'to', 'code', 'status', 'match_type' ) );
		$table = $wpdb->prefix . 'rank_math_redirections';
		if ( ! $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) {
			return $rows;
		}
		$results = $wpdb->get_results( "SELECT sources, url_to, header_code, status FROM {$table}", ARRAY_A );
		foreach ( (array) $results as $r ) {
			$sources = maybe_unserialize( $r['sources'] );
			if ( ! is_array( $sources ) ) {
				continue;
			}
			foreach ( $sources as $src ) {
				if ( empty( $src['pattern'] ) ) {
					continue;
				}
				$rows[] = array(
					$src['pattern'],
					$r['url_to'],
					$r['header_code'],
					$r['status'],
					isset( $src['comparison'] ) ? $src['comparison'] : 'exact',
				);
			}
		}
		return $rows;
	}

	private function user_inventory() {
		$rows  = array( array( 'id', 'login', 'display_name', 'roles', 'email', 'registered', 'post_count' ) );
		$users = get_users( array( 'fields' => array( 'ID', 'user_login', 'display_name', 'user_email', 'user_registered' ) ) );
		foreach ( $users as $u ) {
			$obj      = get_userdata( $u->ID );
			$roles    = $obj ? implode( '|', (array) $obj->roles ) : '';
			$rows[] = array(
				$u->ID,
				$u->user_login,
				$u->display_name,
				$roles,
				$this->redact( $u->user_email ),
				$u->user_registered,
				count_user_posts( $u->ID ),
			);
		}
		return $rows;
	}

	private function collect_connections() {
		global $wpdb;

		$out = array(
			'generated_at' => gmdate( 'c' ),
			'redacted'     => $this->redact_credentials,
		);

		$out['database'] = array(
			'host'    => defined( 'DB_HOST' ) ? DB_HOST : '',
			'name'    => defined( 'DB_NAME' ) ? DB_NAME : '',
			'user'    => defined( 'DB_USER' ) ? DB_USER : '',
			'password' => defined( 'DB_PASSWORD' ) ? $this->redact( DB_PASSWORD ) : '',
			'charset' => defined( 'DB_CHARSET' ) ? DB_CHARSET : '',
			'collate' => defined( 'DB_COLLATE' ) ? DB_COLLATE : '',
			'prefix'  => $wpdb->prefix,
		);

		$out['wordpress'] = array(
			'home'         => defined( 'WP_HOME' ) ? WP_HOME : home_url(),
			'site_url'     => defined( 'WP_SITEURL' ) ? WP_SITEURL : site_url(),
			'debug'        => defined( 'WP_DEBUG' ) && WP_DEBUG,
			'multisite'    => is_multisite(),
			'content_dir'  => defined( 'WP_CONTENT_DIR' ) ? WP_CONTENT_DIR : '',
			'plugin_dir'   => defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : '',
		);

		$smtp_sources = array(
			'WP Mail SMTP'   => 'wp_mail_smtp',
			'Post SMTP'      => 'postman_options',
			'FluentSMTP'     => 'fluentmail-settings',
			'Easy WP SMTP'   => 'swpsmtp_options',
		);
		$smtp_out = array();
		foreach ( $smtp_sources as $label => $opt ) {
			$v = get_option( $opt );
			if ( empty( $v ) ) {
				continue;
			}
			$smtp_out[ $label ] = $this->extract_smtp( $v );
		}
		$out['smtp'] = $smtp_out;

		$payments_out = array();
		$stripe = get_option( 'woocommerce_stripe_settings' );
		if ( is_array( $stripe ) ) {
			$payments_out['Stripe (WooCommerce)'] = array(
				'enabled'           => ! empty( $stripe['enabled'] ),
				'test_mode'         => ! empty( $stripe['testmode'] ),
				'publishable_key'   => $this->redact( isset( $stripe['publishable_key'] ) ? $stripe['publishable_key'] : '' ),
				'secret_key'        => $this->redact( isset( $stripe['secret_key'] ) ? $stripe['secret_key'] : '' ),
				'webhook_secret'    => $this->redact( isset( $stripe['webhook_secret'] ) ? $stripe['webhook_secret'] : '' ),
			);
		}
		$paypal = get_option( 'woocommerce_ppcp-gateway_settings' );
		if ( is_array( $paypal ) ) {
			$payments_out['PayPal (WooCommerce)'] = array(
				'enabled'    => ! empty( $paypal['enabled'] ),
				'sandbox'    => ! empty( $paypal['sandbox_on'] ),
				'client_id'  => $this->redact( isset( $paypal['client_id_sandbox'] ) ? $paypal['client_id_sandbox'] : ( isset( $paypal['client_id_production'] ) ? $paypal['client_id_production'] : '' ) ),
				'secret'     => $this->redact( isset( $paypal['client_secret_sandbox'] ) ? $paypal['client_secret_sandbox'] : ( isset( $paypal['client_secret_production'] ) ? $paypal['client_secret_production'] : '' ) ),
			);
		}
		$out['payments'] = $payments_out;

		$analytics_out = array();
		$site_kit_ga = get_option( 'googlesitekit_analytics-4_settings' );
		if ( is_array( $site_kit_ga ) ) {
			$analytics_out['Google Site Kit — Analytics 4'] = array(
				'measurement_id' => isset( $site_kit_ga['measurementID'] ) ? $site_kit_ga['measurementID'] : '',
				'property_id'    => isset( $site_kit_ga['propertyID'] ) ? $site_kit_ga['propertyID'] : '',
				'account_id'     => isset( $site_kit_ga['accountID'] ) ? $site_kit_ga['accountID'] : '',
			);
		}
		$site_kit_sc = get_option( 'googlesitekit_search-console_settings' );
		if ( is_array( $site_kit_sc ) ) {
			$analytics_out['Google Site Kit — Search Console'] = array(
				'property_id' => isset( $site_kit_sc['propertyID'] ) ? $site_kit_sc['propertyID'] : '',
			);
		}
		$out['analytics'] = $analytics_out;

		return $out;
	}

	private function extract_smtp( $option_value ) {
		if ( ! is_array( $option_value ) ) {
			return array();
		}
		$candidate = $option_value;
		if ( isset( $option_value['smtp'] ) && is_array( $option_value['smtp'] ) ) {
			$candidate = array_merge( $candidate, $option_value['smtp'] );
		}
		$fields = array( 'host', 'smtp_host', 'port', 'smtp_port', 'from_email', 'from_name', 'from', 'user', 'smtp_user', 'auth', 'encryption', 'smtp_encryption', 'mailer' );
		$out    = array();
		foreach ( $fields as $f ) {
			if ( isset( $candidate[ $f ] ) && '' !== $candidate[ $f ] ) {
				$out[ $f ] = is_scalar( $candidate[ $f ] ) ? (string) $candidate[ $f ] : wp_json_encode( $candidate[ $f ] );
			}
		}
		foreach ( array( 'password', 'smtp_pass', 'pass' ) as $pw ) {
			if ( ! empty( $candidate[ $pw ] ) ) {
				$out[ $pw ] = $this->redact( $candidate[ $pw ] );
				break;
			}
		}
		return $out;
	}

	private function options_inventory() {
		$keys = array(
			'siteurl', 'home', 'blogname', 'blogdescription', 'admin_email', 'template', 'stylesheet',
			'timezone_string', 'gmt_offset', 'date_format', 'time_format', 'start_of_week',
			'permalink_structure', 'category_base', 'tag_base', 'blog_public', 'default_role',
			'active_plugins', 'WPLANG',
			'show_on_front', 'page_on_front', 'page_for_posts', 'posts_per_page',
			'wpseo', 'rank_math_options_general', 'aioseo_options', 'seopress_advanced_option_name',
			'googlesitekit_active_modules', 'googlesitekit_search-console_settings', 'googlesitekit_analytics-4_settings',
			'woocommerce_currency', 'woocommerce_default_country', 'woocommerce_store_address',
		);
		$out = array();
		foreach ( $keys as $k ) {
			$v = get_option( $k, null );
			if ( null === $v || false === $v ) {
				continue;
			}
			$out[ $k ] = $v;
		}
		return $out;
	}

	private function tables_summary_rows( array $schema ) {
		$rows = array( array( 'table', 'row_count', 'column_count' ) );
		usort(
			$schema,
			function ( $a, $b ) {
				return $b['row_count'] - $a['row_count'];
			}
		);
		foreach ( $schema as $t ) {
			$rows[] = array( $t['table'], $t['row_count'], count( $t['columns'] ) );
		}
		return $rows;
	}

	private function format_connections_text( array $conn ) {
		$lines = array( '=== Connections dossier ===' );
		$lines[] = 'Generated: ' . $conn['generated_at'];
		$lines[] = 'Credentials redacted: ' . ( $conn['redacted'] ? 'yes' : 'no' );

		foreach ( array( 'database', 'wordpress', 'smtp', 'payments', 'analytics' ) as $section ) {
			if ( empty( $conn[ $section ] ) ) {
				continue;
			}
			$lines[] = '';
			$lines[] = '--- ' . strtoupper( $section ) . ' ---';
			$this->flatten_lines( $conn[ $section ], $lines, '' );
		}
		return implode( "\n", $lines ) . "\n";
	}

	private function flatten_lines( $data, array &$lines, $prefix ) {
		if ( is_array( $data ) ) {
			foreach ( $data as $k => $v ) {
				$new_prefix = '' === $prefix ? (string) $k : $prefix . '.' . $k;
				if ( is_array( $v ) ) {
					$lines[] = $new_prefix . ':';
					$this->flatten_lines( $v, $lines, $new_prefix );
				} else {
					$lines[] = '  ' . $new_prefix . ': ' . ( is_bool( $v ) ? ( $v ? 'yes' : 'no' ) : (string) $v );
				}
			}
		} else {
			$lines[] = '  ' . $prefix . ': ' . (string) $data;
		}
	}

	private function redact( $value ) {
		if ( '' === $value || null === $value ) {
			return '';
		}
		if ( ! $this->redact_credentials ) {
			return (string) $value;
		}
		return '***REDACTED***';
	}

	private function csv( array $rows ) {
		$fh = fopen( 'php://temp', 'w+' );
		foreach ( $rows as $row ) {
			$flat = array_map(
				function ( $v ) {
					if ( is_bool( $v ) ) {
						return $v ? 'yes' : 'no';
					}
					if ( is_scalar( $v ) || null === $v ) {
						return (string) $v;
					}
					return wp_json_encode( $v );
				},
				$row
			);
			fputcsv( $fh, $flat );
		}
		rewind( $fh );
		$csv = stream_get_contents( $fh );
		fclose( $fh );
		return $csv;
	}
}
