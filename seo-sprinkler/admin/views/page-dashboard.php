<?php
/**
 * Dashboard view.
 *
 * @package SeoSprinkler
 *
 * @var SPR_Image_Scanner  $scanner
 * @var SPR_Link_Index     $index
 * @var SPR_Link_Applier   $applier
 * @var SPR_Sitemap_Parser $sitemap
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$media       = $scanner->get_media_library_stats();
$min_images  = $scanner->get_minimum();
$sitemap_url = SPR_Settings::get_sitemap_url();
$index_count = count( $index->get_index() );
$linking_on  = (bool) SPR_Settings::get( 'enable_auto_linking' );
$schema_on   = (bool) SPR_Settings::get( 'enable_schema_check' );
$distrib_on  = (bool) SPR_Settings::get( 'enable_distribution' );
$rule_count  = class_exists( 'SPR_Injection_Rules' ) ? count( ( new SPR_Injection_Rules() )->get_rules() ) : 0;
?>
<div class="wrap spr-wrap">
	<h1><?php esc_html_e( 'SEO Sprinkler', 'seo-sprinkler' ); ?></h1>
	<p class="spr-intro">
		<?php esc_html_e( "Boost your articles: enforce a minimum number of images, verify structured-data (schema) output, and automatically interlink related content using your SEO plugin's sitemap (Yoast, Rank Math, AIOSEO and more).", 'seo-sprinkler' ); ?>
	</p>

	<?php
	$edition  = SPR_Edition::current();
	$is_pro   = SPR_Edition::is_pro();
	$is_exp   = SPR_Edition::is_expert();
	$content  = SPR_Edition::content_count();
	$limit    = SPR_Edition::free_limit();
	$within   = SPR_Edition::within_free_limit();
	?>
	<div class="spr-panel spr-edition spr-edition--<?php echo esc_attr( $edition ); ?>">
		<h2>
			<?php
			printf(
				/* translators: %s: edition label. */
				esc_html__( 'Edition: %s', 'seo-sprinkler' ),
				esc_html( SPR_Edition::label() )
			);
			?>
		</h2>

		<p class="spr-support">
			<?php
			printf(
				/* translators: %s: support entitlement for the current edition. */
				esc_html__( 'Support: %s', 'seo-sprinkler' ),
				esc_html( SPR_Edition::support_label() )
			);
			?>
		</p>

		<?php if ( ! $is_pro ) : ?>
			<p>
				<?php
				printf(
					/* translators: 1: content count, 2: free limit. */
					esc_html__( 'Free usage: %1$d / %2$d pages.', 'seo-sprinkler' ),
					(int) $content,
					(int) $limit
				);
				?>
				<?php if ( $within ) : ?>
					<span class="spr-badge spr-badge--ok"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Everything is unlocked free at your size.', 'seo-sprinkler' ); ?></span>
				<?php else : ?>
					<span class="spr-badge spr-badge--warn"><span class="dashicons dashicons-warning"></span> <?php esc_html_e( 'Over the free limit — premium features are locked.', 'seo-sprinkler' ); ?></span>
				<?php endif; ?>
			</p>
			<p>
				<?php
				printf(
					/* translators: %d: free limit. */
					esc_html__( 'Everything is free up to %d pages. Beyond that, automatic linking, bulk tools, content distribution, AI and JSON-LD schema output need Pro; the Export / Migrate tool needs Expert.', 'seo-sprinkler' ),
					(int) $limit
				);
				?>
			</p>
			<p><a class="button button-primary" href="<?php echo esc_url( SPR_Edition::upgrade_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade to Pro / Expert', 'seo-sprinkler' ); ?></a></p>
		<?php elseif ( ! $is_exp ) : ?>
			<p class="description"><?php printf( /* translators: %d: content count. */ esc_html__( 'Published content items: %d', 'seo-sprinkler' ), (int) $content ); ?></p>
			<p><?php esc_html_e( 'Pro unlocks unlimited automation, bulk tools, AI and schema output. Upgrade to Expert for the Export / Migrate tool and multisite.', 'seo-sprinkler' ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( SPR_Edition::upgrade_url() ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Upgrade to Expert', 'seo-sprinkler' ); ?></a></p>
		<?php else : ?>
			<p><span class="spr-badge spr-badge--ok"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'All features unlocked. Thank you!', 'seo-sprinkler' ); ?></span></p>
		<?php endif; ?>
	</div>

	<div class="spr-cards">
		<div class="spr-card">
			<span class="dashicons dashicons-format-image"></span>
			<h2><?php echo esc_html( number_format_i18n( $media['images'] ) ); ?></h2>
			<p><?php esc_html_e( 'Images in the media library', 'seo-sprinkler' ); ?></p>
		</div>
		<div class="spr-card">
			<span class="dashicons dashicons-admin-media"></span>
			<h2><?php echo esc_html( number_format_i18n( $media['total'] ) ); ?></h2>
			<p><?php esc_html_e( 'Total attachments', 'seo-sprinkler' ); ?></p>
		</div>
		<div class="spr-card">
			<span class="dashicons dashicons-warning"></span>
			<h2><?php echo esc_html( number_format_i18n( $min_images ) ); ?></h2>
			<p><?php esc_html_e( 'Minimum images per article', 'seo-sprinkler' ); ?></p>
		</div>
		<div class="spr-card">
			<span class="dashicons dashicons-admin-links"></span>
			<h2><?php echo esc_html( number_format_i18n( $index_count ) ); ?></h2>
			<p><?php esc_html_e( 'Linkable anchor phrases', 'seo-sprinkler' ); ?></p>
		</div>
	</div>

	<div class="spr-panel">
		<h2><?php esc_html_e( 'Status', 'seo-sprinkler' ); ?></h2>
		<table class="widefat striped spr-status">
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Automatic internal linking', 'seo-sprinkler' ); ?></td>
					<td>
						<?php if ( $linking_on ) : ?>
							<span class="spr-badge spr-badge--ok"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Enabled', 'seo-sprinkler' ); ?></span>
						<?php else : ?>
							<span class="spr-badge spr-badge--warn"><?php esc_html_e( 'Disabled', 'seo-sprinkler' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Schema (structured data) check', 'seo-sprinkler' ); ?></td>
					<td>
						<?php if ( $schema_on ) : ?>
							<span class="spr-badge spr-badge--ok"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Enabled', 'seo-sprinkler' ); ?></span>
						<?php else : ?>
							<span class="spr-badge spr-badge--warn"><?php esc_html_e( 'Disabled', 'seo-sprinkler' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Content distribution (Sprinkler)', 'seo-sprinkler' ); ?></td>
					<td>
						<?php if ( $distrib_on ) : ?>
							<span class="spr-badge spr-badge--ok"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Enabled', 'seo-sprinkler' ); ?></span>
						<?php else : ?>
							<span class="spr-badge spr-badge--warn"><?php esc_html_e( 'Disabled', 'seo-sprinkler' ); ?></span>
						<?php endif; ?>
						<span class="spr-muted">
							<?php
							printf(
								/* translators: %d: number of distribution rules. */
								esc_html( _n( '%d rule', '%d rules', $rule_count, 'seo-sprinkler' ) ),
								(int) $rule_count
							);
							?>
						</span>
					</td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'SEO sitemap', 'seo-sprinkler' ); ?></td>
					<td><a href="<?php echo esc_url( $sitemap_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $sitemap_url ); ?></a></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'SEO plugin detected', 'seo-sprinkler' ); ?></td>
					<td>
						<?php
						$spr_seo = class_exists( 'SPR_SEO_Detector' ) ? SPR_SEO_Detector::active() : array();
						if ( ! empty( $spr_seo ) ) :
							$spr_names = array();
							foreach ( $spr_seo as $spr_p ) {
								$spr_names[] = $spr_p['name'] . ( ! empty( $spr_p['version'] ) ? ' ' . $spr_p['version'] : '' );
							}
							?>
							<span class="spr-badge spr-badge--ok"><span class="dashicons dashicons-yes"></span> <?php echo esc_html( implode( ', ', $spr_names ) ); ?></span>
						<?php else : ?>
							<span class="spr-badge spr-badge--warn"><?php esc_html_e( 'None detected — falling back to all published posts and post titles.', 'seo-sprinkler' ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>

		<p class="spr-actions">
			<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=spr-business' ) ); ?>"><?php esc_html_e( 'Business profile', 'seo-sprinkler' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=spr-images' ) ); ?>"><?php esc_html_e( 'Run image audit', 'seo-sprinkler' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=spr-schema' ) ); ?>"><?php esc_html_e( 'Run schema audit', 'seo-sprinkler' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=spr-links' ) ); ?>"><?php esc_html_e( 'Manage internal links', 'seo-sprinkler' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=spr-link-audit' ) ); ?>"><?php esc_html_e( 'Link audit', 'seo-sprinkler' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=spr-distribution' ) ); ?>"><?php esc_html_e( 'Content distribution', 'seo-sprinkler' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=spr-cleaner' ) ); ?>"><?php esc_html_e( 'Content cleaner', 'seo-sprinkler' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=spr-export' ) ); ?>"><?php esc_html_e( 'Export / Migrate', 'seo-sprinkler' ); ?></a>
			<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=spr-settings' ) ); ?>"><?php esc_html_e( 'Settings', 'seo-sprinkler' ); ?></a>
		</p>
	</div>

	<div class="spr-panel">
		<h2 class="title"><?php esc_html_e( 'Recent activity', 'seo-sprinkler' ); ?></h2>
		<?php $spr_acts = class_exists( 'SPR_Activity' ) ? SPR_Activity::recent( 12 ) : array(); ?>
		<?php if ( empty( $spr_acts ) ) : ?>
			<p class="description"><?php esc_html_e( 'No activity yet — run an audit or fill images and it will appear here.', 'seo-sprinkler' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<tbody>
				<?php foreach ( $spr_acts as $spr_a ) : ?>
					<tr>
						<td style="width:150px" class="spr-muted"><?php echo esc_html( sprintf( /* translators: %s: human time diff */ __( '%s ago', 'seo-sprinkler' ), human_time_diff( (int) $spr_a['time'], time() ) ) ); ?></td>
						<td><?php echo esc_html( $spr_a['message'] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
