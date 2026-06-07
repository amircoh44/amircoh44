<?php
/**
 * Export / Migrate view.
 *
 * @package SeoArticleBooster
 *
 * @var SAB_Exporter $exporter
 * @var bool         $zip_ready  Whether ZipArchive is available.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$seo_plugins = SAB_SEO_Detector::summary();

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
<div class="wrap sab-wrap">
	<h1><?php esc_html_e( 'Export / Migrate', 'seo-article-booster' ); ?></h1>

	<?php if ( ! empty( $locked ) ) : ?>
		<div class="notice notice-info inline">
			<p>
				<span class="dashicons dashicons-star-filled"></span>
				<?php
				printf(
					/* translators: %s: edition label. */
					esc_html__( 'Export / Migrate is an %s feature. Below is a preview of what it does — upgrade to download.', 'seo-article-booster' ),
					esc_html( SAB_Edition::label( SAB_Edition::required_for( 'export' ) ) )
				);
				?>
				<a class="button button-primary" style="margin-left:8px" href="<?php echo esc_url( SAB_Edition::upgrade_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade', 'seo-article-booster' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<p class="sab-intro"><?php esc_html_e( 'Download your whole site as structured JSON — all post types and custom post types, taxonomies, the full media library with alt/caption/description, settings and SEO metadata — ready to import into Python or another platform. Optionally bundle the actual media files as a ZIP.', 'seo-article-booster' ); ?></p>

	<?php if ( ! empty( $seo_plugins ) ) : ?>
		<div class="sab-panel">
			<h2><?php esc_html_e( 'Detected SEO plugins', 'seo-article-booster' ); ?></h2>
			<ul class="sab-checklist">
				<?php foreach ( $seo_plugins as $p ) : ?>
					<li><span class="dashicons dashicons-yes"></span> <strong><?php echo esc_html( $p['name'] ); ?></strong> <?php echo $p['version'] ? esc_html( $p['version'] ) : ''; ?>
						<?php if ( $p['note'] ) : ?><br /><span class="description"><?php echo esc_html( $p['note'] ); ?></span><?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<p class="description"><?php esc_html_e( 'Their per-post SEO fields (titles, descriptions, focus keywords, canonical, robots, social) are carried in the export — and remain editable in WordPress if you decide to stay.', 'seo-article-booster' ); ?></p>
		</div>
	<?php else : ?>
		<div class="sab-panel"><p class="description"><?php esc_html_e( 'No known SEO plugin detected. Post titles, content and meta are still exported in full.', 'seo-article-booster' ); ?></p></div>
	<?php endif; ?>

	<form id="sab-export-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
		<input type="hidden" name="action" value="sab_export_json" />
		<?php wp_nonce_field( SAB_Export_Admin::NONCE, 'sab_export_nonce' ); ?>

		<div class="sab-panel">
			<h2><?php esc_html_e( 'What to include', 'seo-article-booster' ); ?></h2>
			<?php
			$check( 'include_media', __( 'Media library metadata (alt, caption, description, sizes, URLs)', 'seo-article-booster' ), true );
			$check( 'include_taxonomies', __( 'All taxonomies &amp; terms (categories, tags, custom)', 'seo-article-booster' ), true );
			$check( 'include_seo', __( 'SEO metadata from detected plugins', 'seo-article-booster' ), true );
			$check( 'include_settings', __( 'General settings', 'seo-article-booster' ), true );
			$check( 'include_comments', __( 'Comments', 'seo-article-booster' ), false );
			?>
			<hr />
			<p><strong><?php esc_html_e( 'Post types', 'seo-article-booster' ); ?></strong></p>
			<?php foreach ( $types as $t ) : ?>
				<label style="margin-right:1em;display:inline-block"><input type="checkbox" name="post_types[]" value="<?php echo esc_attr( $t->name ); ?>" checked /> <?php echo esc_html( $t->labels->name ); ?> <code><?php echo esc_html( $t->name ); ?></code></label>
			<?php endforeach; ?>
		</div>

		<div class="sab-panel">
			<h2><?php esc_html_e( 'Personal data', 'seo-article-booster' ); ?></h2>
			<div class="notice notice-warning inline"><p><?php esc_html_e( 'The options below include personal data. Only export data you are authorised to handle. Keep the downloaded file in a safe place — do not upload it to an unsecured or public server, and never commit it to a public repository such as GitHub.', 'seo-article-booster' ); ?></p></div>
			<?php
			$check( 'include_users', __( 'Users (names, roles, registration date)', 'seo-article-booster' ), false );
			$check( 'include_emails', __( 'Include user logins &amp; <strong>email addresses</strong> + admin email', 'seo-article-booster' ), false );
			$check( 'include_all_options', __( 'All site options (advanced — may contain API keys/secrets)', 'seo-article-booster' ), false );
			?>
			<p style="margin-top:10px">
				<label><input type="checkbox" id="sab-authorize" name="authorize" value="1" /> <strong><?php esc_html_e( 'I am authorised to export this data, including any personal data it contains.', 'seo-article-booster' ); ?></strong></label>
			</p>
		</div>

		<div class="sab-panel">
			<h2><?php esc_html_e( 'Download', 'seo-article-booster' ); ?></h2>

			<div class="notice notice-error inline" id="sab-export-warning" style="display:none">
				<p>
					<span class="dashicons dashicons-shield-alt"></span>
					<strong><?php esc_html_e( 'This export will contain sensitive / personal data.', 'seo-article-booster' ); ?></strong>
					<?php esc_html_e( 'Store the file somewhere private. Do NOT upload it to an unsecured or public server, and never commit it to a public repository such as GitHub.', 'seo-article-booster' ); ?>
				</p>
			</div>

			<p>
				<button type="submit" class="button button-primary" <?php disabled( ! empty( $locked ) ); ?>><?php esc_html_e( 'Download JSON', 'seo-article-booster' ); ?></button>
				<?php if ( $zip_ready ) : ?>
					<button type="button" class="button" id="sab-export-zip" <?php disabled( ! empty( $locked ) ); ?>><?php esc_html_e( 'Build &amp; download media ZIP', 'seo-article-booster' ); ?></button>
				<?php else : ?>
					<span class="description"><?php esc_html_e( 'Media ZIP unavailable — the server is missing the PHP zip extension. Use JSON; media URLs are included so a script can fetch the files.', 'seo-article-booster' ); ?></span>
				<?php endif; ?>
			</p>
			<div id="sab-export-progress" class="sab-progress" style="display:none">
				<div class="sab-progress__bar"><span></span></div>
				<p class="sab-progress__label"></p>
			</div>
		</div>
	</form>
</div>
