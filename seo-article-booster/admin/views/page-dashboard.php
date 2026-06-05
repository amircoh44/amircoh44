<?php
/**
 * Dashboard view.
 *
 * @package SeoArticleBooster
 *
 * @var SAB_Image_Scanner  $scanner
 * @var SAB_Link_Index     $index
 * @var SAB_Link_Applier   $applier
 * @var SAB_Sitemap_Parser $sitemap
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$media       = $scanner->get_media_library_stats();
$min_images  = $scanner->get_minimum();
$sitemap_url = SAB_Settings::get_sitemap_url();
$index_count = count( $index->get_index() );
$linking_on  = (bool) SAB_Settings::get( 'enable_auto_linking' );
$schema_on   = (bool) SAB_Settings::get( 'enable_schema_check' );
$distrib_on  = (bool) SAB_Settings::get( 'enable_distribution' );
$rule_count  = class_exists( 'SAB_Injection_Rules' ) ? count( ( new SAB_Injection_Rules() )->get_rules() ) : 0;
?>
<div class="wrap sab-wrap">
	<h1><?php esc_html_e( 'SEO Article Booster', 'seo-article-booster' ); ?></h1>
	<p class="sab-intro">
		<?php esc_html_e( 'Boost your articles: enforce a minimum number of images, verify structured-data (schema) output, and automatically interlink related content using your Yoast SEO sitemap.', 'seo-article-booster' ); ?>
	</p>

	<div class="sab-cards">
		<div class="sab-card">
			<span class="dashicons dashicons-format-image"></span>
			<h2><?php echo esc_html( number_format_i18n( $media['images'] ) ); ?></h2>
			<p><?php esc_html_e( 'Images in the media library', 'seo-article-booster' ); ?></p>
		</div>
		<div class="sab-card">
			<span class="dashicons dashicons-admin-media"></span>
			<h2><?php echo esc_html( number_format_i18n( $media['total'] ) ); ?></h2>
			<p><?php esc_html_e( 'Total attachments', 'seo-article-booster' ); ?></p>
		</div>
		<div class="sab-card">
			<span class="dashicons dashicons-warning"></span>
			<h2><?php echo esc_html( number_format_i18n( $min_images ) ); ?></h2>
			<p><?php esc_html_e( 'Minimum images per article', 'seo-article-booster' ); ?></p>
		</div>
		<div class="sab-card">
			<span class="dashicons dashicons-admin-links"></span>
			<h2><?php echo esc_html( number_format_i18n( $index_count ) ); ?></h2>
			<p><?php esc_html_e( 'Linkable anchor phrases', 'seo-article-booster' ); ?></p>
		</div>
	</div>

	<div class="sab-panel">
		<h2><?php esc_html_e( 'Status', 'seo-article-booster' ); ?></h2>
		<table class="widefat striped sab-status">
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Automatic internal linking', 'seo-article-booster' ); ?></td>
					<td>
						<?php if ( $linking_on ) : ?>
							<span class="sab-badge sab-badge--ok"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Enabled', 'seo-article-booster' ); ?></span>
						<?php else : ?>
							<span class="sab-badge sab-badge--warn"><?php esc_html_e( 'Disabled', 'seo-article-booster' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Schema (structured data) check', 'seo-article-booster' ); ?></td>
					<td>
						<?php if ( $schema_on ) : ?>
							<span class="sab-badge sab-badge--ok"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Enabled', 'seo-article-booster' ); ?></span>
						<?php else : ?>
							<span class="sab-badge sab-badge--warn"><?php esc_html_e( 'Disabled', 'seo-article-booster' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Content distribution (Sprinkler)', 'seo-article-booster' ); ?></td>
					<td>
						<?php if ( $distrib_on ) : ?>
							<span class="sab-badge sab-badge--ok"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Enabled', 'seo-article-booster' ); ?></span>
						<?php else : ?>
							<span class="sab-badge sab-badge--warn"><?php esc_html_e( 'Disabled', 'seo-article-booster' ); ?></span>
						<?php endif; ?>
						<span class="sab-muted">
							<?php
							printf(
								/* translators: %d: number of distribution rules. */
								esc_html( _n( '%d rule', '%d rules', $rule_count, 'seo-article-booster' ) ),
								(int) $rule_count
							);
							?>
						</span>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Yoast sitemap', 'seo-article-booster' ); ?></td>
					<td><a href="<?php echo esc_url( $sitemap_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $sitemap_url ); ?></a></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Yoast SEO active', 'seo-article-booster' ); ?></td>
					<td>
						<?php if ( defined( 'WPSEO_VERSION' ) ) : ?>
							<span class="sab-badge sab-badge--ok"><span class="dashicons dashicons-yes"></span> <?php echo esc_html( WPSEO_VERSION ); ?></span>
						<?php else : ?>
							<span class="sab-badge sab-badge--warn"><?php esc_html_e( 'Not detected — the plugin falls back to all published posts.', 'seo-article-booster' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="sab-actions">
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=sab-business' ) ); ?>"><?php esc_html_e( 'Business profile', 'seo-article-booster' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sab-images' ) ); ?>"><?php esc_html_e( 'Run image audit', 'seo-article-booster' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sab-schema' ) ); ?>"><?php esc_html_e( 'Run schema audit', 'seo-article-booster' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sab-links' ) ); ?>"><?php esc_html_e( 'Manage internal links', 'seo-article-booster' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sab-link-audit' ) ); ?>"><?php esc_html_e( 'Link audit', 'seo-article-booster' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sab-distribution' ) ); ?>"><?php esc_html_e( 'Content distribution', 'seo-article-booster' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sab-cleaner' ) ); ?>"><?php esc_html_e( 'Content cleaner', 'seo-article-booster' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sab-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'seo-article-booster' ); ?></a>
		</p>
	</div>
</div>
