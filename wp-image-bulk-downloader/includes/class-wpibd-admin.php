<?php
/**
 * Admin UI — menu, settings page, asset enqueueing.
 *
 * @package WP_Image_Bulk_Downloader
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPIBD_Admin {

	const PAGE_SLUG = 'wpibd-export';

	public function register_menu() {
		add_media_page(
			__( 'Bulk Image Download', 'wp-image-bulk-downloader' ),
			__( 'Bulk Download', 'wp-image-bulk-downloader' ),
			'upload_files',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( $hook ) {
		if ( 'media_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wpibd-admin',
			WPIBD_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			WPIBD_VERSION
		);

		wp_enqueue_script(
			'wpibd-admin',
			WPIBD_PLUGIN_URL . 'admin/js/admin.js',
			array( 'jquery' ),
			WPIBD_VERSION,
			true
		);

		wp_localize_script(
			'wpibd-admin',
			'wpibdConfig',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wpibd_export_nonce' ),
				'i18n'    => array(
					'preparing'   => __( 'Preparing export…', 'wp-image-bulk-downloader' ),
					'processing'  => __( 'Processed %1$s of %2$s images', 'wp-image-bulk-downloader' ),
					'complete'    => __( 'Export complete. Your download should start automatically.', 'wp-image-bulk-downloader' ),
					'failed'      => __( '%s image(s) could not be added.', 'wp-image-bulk-downloader' ),
					'error'       => __( 'Something went wrong:', 'wp-image-bulk-downloader' ),
					'cancelled'   => __( 'Export cancelled.', 'wp-image-bulk-downloader' ),
					'startAgain'  => __( 'Start a new export', 'wp-image-bulk-downloader' ),
					'confirmLeave'=> __( 'An export is in progress. Leave anyway?', 'wp-image-bulk-downloader' ),
				),
			)
		);
	}

	public function render_page() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-image-bulk-downloader' ) );
		}

		$image_count = $this->get_image_count();
		?>
		<div class="wrap wpibd-wrap">
			<h1><?php esc_html_e( 'Bulk Image Download', 'wp-image-bulk-downloader' ); ?></h1>

			<p class="wpibd-intro">
				<?php
				printf(
					/* translators: %s: total image count. */
					esc_html__( 'Download every image in your media library (%s found) as a single ZIP archive. Pick an export mode and click the button — large libraries are processed in the background, so you can keep the tab open while it works.', 'wp-image-bulk-downloader' ),
					'<strong>' . esc_html( number_format_i18n( $image_count ) ) . '</strong>'
				);
				?>
			</p>

			<div class="wpibd-card">
				<h2><?php esc_html_e( 'Export options', 'wp-image-bulk-downloader' ); ?></h2>

				<fieldset class="wpibd-modes" aria-label="<?php esc_attr_e( 'Export mode', 'wp-image-bulk-downloader' ); ?>">
					<label class="wpibd-mode">
						<input type="radio" name="wpibd_mode" value="images_only" checked>
						<span class="wpibd-mode-title"><?php esc_html_e( 'Images only', 'wp-image-bulk-downloader' ); ?></span>
						<span class="wpibd-mode-desc"><?php esc_html_e( 'Flat ZIP containing every image file. Filenames are kept unique automatically.', 'wp-image-bulk-downloader' ); ?></span>
					</label>

					<label class="wpibd-mode">
						<input type="radio" name="wpibd_mode" value="with_paths">
						<span class="wpibd-mode-title"><?php esc_html_e( 'Images with upload folder paths', 'wp-image-bulk-downloader' ); ?></span>
						<span class="wpibd-mode-desc"><?php esc_html_e( 'Preserves the YYYY/MM/ folder structure of your uploads directory inside the ZIP.', 'wp-image-bulk-downloader' ); ?></span>
					</label>

					<label class="wpibd-mode">
						<input type="radio" name="wpibd_mode" value="with_metadata">
						<span class="wpibd-mode-title"><?php esc_html_e( 'Images + folder paths + metadata', 'wp-image-bulk-downloader' ); ?></span>
						<span class="wpibd-mode-desc"><?php esc_html_e( 'Everything above plus image-metadata.csv and image-metadata.json with title, alt text, caption, and description for every image.', 'wp-image-bulk-downloader' ); ?></span>
					</label>

					<label class="wpibd-mode wpibd-mode-astro">
						<input type="radio" name="wpibd_mode" value="astro_export">
						<span class="wpibd-mode-title"><?php esc_html_e( 'Full site export (Astro-ready)', 'wp-image-bulk-downloader' ); ?></span>
						<span class="wpibd-mode-desc"><?php esc_html_e( 'Everything above plus every post / page / custom post type as a markdown file with YAML frontmatter under src/content/, taxonomies + menus + authors + comments + Rank Math redirects under src/data/, images under public/images/, and a project-handover/ folder with spreadsheets (content, plugins, integrations, users, redirects), a redacted connections dossier (database, SMTP, payments, analytics), and a database inventory (schema + options).', 'wp-image-bulk-downloader' ); ?></span>
					</label>
				</fieldset>

				<div class="wpibd-extras">
					<label class="wpibd-extra">
						<input type="checkbox" id="wpibd-include-site-info" name="wpibd_include_site_info" value="1" checked>
						<span class="wpibd-extra-title"><?php esc_html_e( 'Also include site info file', 'wp-image-bulk-downloader' ); ?></span>
						<span class="wpibd-extra-desc">
							<?php esc_html_e( 'Adds site-info.json and site-info.txt to the ZIP with Google Search Console / Bing / Yandex / Pinterest / Facebook verification codes, Google Analytics (UA + GA4), GTM, Google Ads, Facebook Pixel, TikTok, LinkedIn, Hotjar, Microsoft Clarity IDs, SEO plugin settings (Yoast / Rank Math / AIOSEO / SEOPress), active theme, and the list of active plugins with versions.', 'wp-image-bulk-downloader' ); ?>
						</span>
					</label>
				</div>

				<div class="wpibd-actions">
					<button type="button" class="button button-primary button-hero" id="wpibd-start" <?php disabled( 0 === $image_count ); ?>>
						<?php esc_html_e( 'Download all images', 'wp-image-bulk-downloader' ); ?>
					</button>
					<button type="button" class="button" id="wpibd-cancel" hidden>
						<?php esc_html_e( 'Cancel', 'wp-image-bulk-downloader' ); ?>
					</button>
				</div>

				<div class="wpibd-progress" id="wpibd-progress" hidden>
					<div class="wpibd-progress-bar"><div class="wpibd-progress-fill" id="wpibd-progress-fill"></div></div>
					<p class="wpibd-progress-status" id="wpibd-progress-status" aria-live="polite"></p>
				</div>

				<div class="wpibd-notice" id="wpibd-notice" hidden></div>
			</div>

			<?php if ( ! class_exists( 'ZipArchive' ) ) : ?>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'The PHP ZipArchive extension is not available on this server, so exports cannot run. Please contact your host.', 'wp-image-bulk-downloader' ); ?></p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	private function get_image_count() {
		$counts = wp_count_attachments( 'image' );
		$total  = 0;
		if ( is_object( $counts ) ) {
			foreach ( get_object_vars( $counts ) as $count ) {
				$total += (int) $count;
			}
		}
		return $total;
	}
}
