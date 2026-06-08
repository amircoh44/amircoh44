<?php
/**
 * Export / Migrate view.
 *
 * @package SeoSprinkler
 *
 * @var SPR_Exporter $exporter
 * @var bool         $zip_ready  Whether ZipArchive is available.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$seo_plugins = SPR_SEO_Detector::summary();

$types = get_post_types( array(), 'objects' );
unset( $types['attachment'], $types['revision'], $types['nav_menu_item'], $types['custom_css'], $types['customize_changeset'], $types['oembed_cache'], $types['user_request'], $types['wp_block'], $types['wp_template'], $types['wp_template_part'], $types['wp_global_styles'], $types['wp_navigation'] );

$check = function ( $name, $label, $checked = true, $class = '' ) {
	printf(
		'<label class="%4$s"><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label><br />',
		esc_attr( $name ),
		checked( $checked, true, false ),
		wp_kses_post( $label ),
		esc_attr( $class )
	);
};
?>
<div class="wrap spr-wrap">
	<h1><?php esc_html_e( 'Export / Migrate', 'seo-sprinkler' ); ?></h1>

	<?php if ( ! empty( $locked ) ) : ?>
		<div class="notice notice-info inline">
			<p>
				<span class="dashicons dashicons-star-filled"></span>
				<?php
				printf(
					/* translators: %s: edition label. */
					esc_html__( 'Export / Migrate is an %s feature. Below is a preview of what it does — upgrade to download.', 'seo-sprinkler' ),
					esc_html( SPR_Edition::label( SPR_Edition::required_for( 'export' ) ) )
				);
				?>
				<a class="button button-primary" style="margin-left:8px" href="<?php echo esc_url( SPR_Edition::upgrade_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade', 'seo-sprinkler' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<p class="spr-intro"><?php esc_html_e( 'Download your whole site as structured JSON — all post types and custom post types, taxonomies, the full media library with alt/caption/description, settings and SEO metadata — ready to import into Python or another platform. Optionally bundle the actual media files as a ZIP.', 'seo-sprinkler' ); ?></p>

	<?php if ( ! empty( $seo_plugins ) ) : ?>
		<div class="spr-panel">
			<h2><?php esc_html_e( 'Detected SEO plugins', 'seo-sprinkler' ); ?></h2>
			<ul class="spr-checklist">
				<?php foreach ( $seo_plugins as $p ) : ?>
					<li><span class="dashicons dashicons-yes"></span> <strong><?php echo esc_html( $p['name'] ); ?></strong> <?php echo $p['version'] ? esc_html( $p['version'] ) : ''; ?>
						<?php if ( $p['note'] ) : ?><br /><span class="description"><?php echo esc_html( $p['note'] ); ?></span><?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="description"><?php esc_html_e( 'Their per-post SEO fields (titles, descriptions, focus keywords, canonical, robots, social) are carried in the export — and remain editable in WordPress if you decide to stay.', 'seo-sprinkler' ); ?></p>
		</div>
	<?php else : ?>
		<div class="spr-panel"><p class="description"><?php esc_html_e( 'No known SEO plugin detected. Post titles, content and meta are still exported in full.', 'seo-sprinkler' ); ?></p></div>
	<?php endif; ?>

	<form id="spr-export-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" id="spr-export-action" value="spr_export_json" />
		<?php wp_nonce_field( SPR_Export_Admin::NONCE, 'spr_export_nonce' ); ?>

		<div class="spr-panel">
			<h2><?php esc_html_e( 'What to include', 'seo-sprinkler' ); ?></h2>
			<?php
			$check( 'include_media', __( 'Media library metadata (alt, caption, description, sizes, URLs)', 'seo-sprinkler' ), true );
			$check( 'include_taxonomies', __( 'All taxonomies &amp; terms (categories, tags, custom)', 'seo-sprinkler' ), true );
			$check( 'include_seo', __( 'SEO metadata from detected plugins', 'seo-sprinkler' ), true );
			$check( 'include_settings', __( 'General settings', 'seo-sprinkler' ), true );
			$check( 'include_comments', __( 'Comments', 'seo-sprinkler' ), false );
			?>
			<hr />
			<p><strong><?php esc_html_e( 'Post types', 'seo-sprinkler' ); ?></strong></p>
			<?php foreach ( $types as $t ) : ?>
				<label style="margin-right:1em;display:inline-block"><input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $t->name ); ?>" checked /> <?php echo esc_html( $t->labels->name ); ?> <code><?php echo esc_html( $t->name ); ?></code></label>
			<?php endforeach; ?>
		</div>

		<div class="spr-panel">
			<h2 class="spr-panel__h"><span class="dashicons dashicons-admin-page"></span> <?php esc_html_e( 'Content mirror', 'seo-sprinkler' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Capture the real, rendered content of every page — including Elementor and other page builders, whose layout lives outside the post content — so the export is a complete, portable mirror you can rebuild anywhere.', 'seo-sprinkler' ); ?></p>
			<?php
			$check( 'render_content', __( 'Render full page content (Elementor, page builders, blocks &amp; shortcodes)', 'seo-sprinkler' ), true );
			$check( 'clean_html', __( 'Add a clean, semantic-HTML mirror — no scripts, styles, classes or builder/WordPress markup', 'seo-sprinkler' ), true );
			$check( 'extract_media', __( 'Extract each page&#8217;s content images and internal/external links (connections)', 'seo-sprinkler' ), true );
			$check( 'include_menus', __( 'Navigation menus &amp; their items', 'seo-sprinkler' ), true );
			?>
			<p class="description"><?php esc_html_e( 'Rendering every page can take a little longer on large sites.', 'seo-sprinkler' ); ?></p>
		</div>

		<div class="spr-panel">
			<h2><?php esc_html_e( 'Personal data', 'seo-sprinkler' ); ?></h2>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'The options below include personal data. Only export data you are authorised to handle. Keep the downloaded file in a safe place — do not upload it to an unsecured or public server, and never commit it to a public repository such as GitHub.', 'seo-sprinkler' ); ?></p></div>
			<?php
			$check( 'include_users', __( 'Users (names, roles, registration date)', 'seo-sprinkler' ), false );
			$check( 'include_emails', __( 'Include user logins &amp; <strong>email addresses</strong> + admin email', 'seo-sprinkler' ), false );
			$check( 'include_all_options', __( 'All site options (advanced — may contain API keys/secrets)', 'seo-sprinkler' ), false );
			?>
			<p style="margin-top:10px">
				<label><input type="checkbox" id="spr-authorize" name="authorize" value="1" /> <strong><?php esc_html_e( 'I am authorised to export this data, including any personal data it contains.', 'seo-sprinkler' ); ?></strong></label>
			</p>
		</div>

		<div class="spr-panel">
			<h2><?php esc_html_e( 'Download', 'seo-sprinkler' ); ?></h2>

			<div class="notice notice-error inline" id="spr-export-warning" style="display:none">
				<p>
					<span class="dashicons dashicons-shield-alt"></span>
					<strong><?php esc_html_e( 'This export will contain sensitive / personal data.', 'seo-sprinkler' ); ?></strong>
					<?php esc_html_e( 'Store the file somewhere private. Do NOT upload it to an unsecured or public server, and never commit it to a public repository such as GitHub.', 'seo-sprinkler' ); ?>
				</p>
			</div>

			<p>
				<button type="submit" class="button button-primary" id="spr-export-json-btn" <?php disabled( ! empty( $locked ) ); ?>><?php esc_html_e( 'Download JSON (single file)', 'seo-sprinkler' ); ?></button>
				<label style="margin-left:10px"><input type="checkbox" name="compress" value="1" /> <?php esc_html_e( 'Compress (.gz)', 'seo-sprinkler' ); ?></label>
				<?php if ( $zip_ready ) : ?>
					<button type="submit" class="button button-primary" id="spr-export-files-btn" <?php disabled( ! empty( $locked ) ); ?>><?php esc_html_e( 'Download content files (ZIP)', 'seo-sprinkler' ); ?></button>
					<button type="button" class="button" id="spr-export-zip" <?php disabled( ! empty( $locked ) ); ?>><?php esc_html_e( 'Build &amp; download media ZIP', 'seo-sprinkler' ); ?></button>
				<?php else : ?>
					<span class="description"><?php esc_html_e( 'Media ZIP unavailable — the server is missing the PHP zip extension. Use JSON; media URLs are included so a script can fetch the files.', 'seo-sprinkler' ); ?></span>
				<?php endif; ?>
			</p>
			<p class="description"><?php esc_html_e( '“Download content files (ZIP)” splits the export into many small files — one JSON (plus a clean .html) per article, chunked media metadata and separate sections — so nothing is one huge file to open. “Download JSON” is the all-in-one document; the media ZIP adds the actual media files under media/files/.', 'seo-sprinkler' ); ?></p>
			<p class="description"><?php esc_html_e( 'Antivirus blocking the download? The file is your own content — scanners can mis-flag the HTML/JavaScript inside your posts. Tick "Compress (.gz)" (extract it with 7-Zip/WinRAR), or choose "Keep" in your browser and allow the item under Windows Security → Virus &amp; threat protection → Protection history.', 'seo-sprinkler' ); ?></p>
			<div id="spr-export-progress" class="spr-progress" style="display:none">
				<div class="spr-progress__bar"><span></span></div>
				<p class="spr-progress__label"></p>
			</div>
		</div>
	</form>
</div>
