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

	<?php
	$edition  = SAB_Edition::current();
	$is_pro   = SAB_Edition::is_pro();
	$is_exp   = SAB_Edition::is_expert();
	$content  = SAB_Edition::content_count();
	$limit    = SAB_Edition::free_limit();
	$within   = SAB_Edition::within_free_limit();
	?>
	<div class="sab-panel sab-edition sab-edition--<?php echo esc_attr( $edition ); ?>">
		<h2>
			<?php
			printf(
				/* translators: %s: edition label. */
				esc_html__( 'Edition: %s', 'seo-article-booster' ),
				esc_html( SAB_Edition::label() )
			);
			?>
		</h2>

		<?php if ( ! $is_pro ) : ?>
			<p>
				<?php
				printf(
					/* translators: 1: content count, 2: free limit. */
					esc_html__( 'Free usage: %1$d / %2$d pages.', 'seo-article-booster' ),
					(int) $content,
					(int) $limit
				);
				?>
				<?php if ( $within ) : ?>
					<span class="sab-badge sab-badge--ok"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Everything is unlocked free at your size.', 'seo-article-booster' ); ?></span>
				<?php else : ?>
					<span class="sab-badge sab-badge--warn"><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Over the free limit — premium features are locked.', 'seo-article-booster' ); ?></span>
				<?php endif; ?>
			</p>
			<p>
				<?php
				printf(
					/* translators: %d: free limit. */
					esc_html__( 'Everything is free up to %d pages. Beyond that, automatic linking, bulk tools, content distribution, AI and JSON-LD schema output need Pro; the Export / Migrate tool needs Expert.', 'seo-article-booster' ),
					(int) $limit
				);
				?>
			</p>
			<p><a class="button button-primary" href="<?php echo esc_url( SAB_Edition::upgrade_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade to Pro / Expert', 'seo-article-booster' ); ?></a></p>
		<?php elseif ( ! $is_exp ) : ?>
			<p class="description"><?php printf( /* translators: %d: content count. */ esc_html__( 'Published content items: %d', 'seo-article-booster' ), (int) $content ); ?></p>
			<p><?php esc_html_e( 'Pro unlocks unlimited automation, bulk tools, AI and schema output. Upgrade to Expert for the Export / Migrate tool and multisite.', 'seo-article-booster' ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( SAB_Edition::upgrade_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade to Expert', 'seo-article-booster' ); ?></a></p>
		<?php else : ?>
			<p><span class="sab-badge sab-badge--ok"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'All features unlocked. Thank you!', 'seo-article-booster' ); ?></span></p>
		<?php endif; ?>
	</div>

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
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sab-export' ) ); ?>"><?php esc_html_e( 'Export / Migrate', 'seo-article-booster' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sab-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'seo-article-booster' ); ?></a>
		</p>
	</div>
</div>
