<?php
/**
 * Schema Audit view.
 *
 * Runs a batched check over the audited post types, fetching each article and
 * reporting any that are missing structured data (or the required schema type).
 *
 * @package SeoArticleBooster
 *
 * @var SAB_Schema_Scanner $schema
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$enabled  = (bool) SAB_Settings::get( 'enable_schema_check' );
$required = $schema->required_types();
$types    = (array) SAB_Settings::get( 'audit_post_types', array( 'post' ) );
?>
<div class="wrap sab-wrap">
	<h1><?php esc_html_e( 'Schema Audit', 'seo-article-booster' ); ?></h1>

	<?php if ( ! $enabled ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php esc_html_e( 'Schema checking is currently disabled.', 'seo-article-booster' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=sab-settings' ) ); ?>#sab_schema"><?php esc_html_e( 'Enable it in settings.', 'seo-article-booster' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<p>
		<?php
		if ( $required ) {
			printf(
				/* translators: 1: required schema types, 2: post types. */
				esc_html__( 'Checking that articles expose the schema type(s): %1$s. Post types: %2$s.', 'seo-article-booster' ),
				'<strong>' . esc_html( implode( ', ', $required ) ) . '</strong>',
				esc_html( implode( ', ', $types ) )
			);
		} else {
			printf(
				/* translators: %s: post types. */
				esc_html__( 'Checking that articles expose any Schema.org structured data. Post types: %s.', 'seo-article-booster' ),
				esc_html( implode( ', ', $types ) )
			);
		}
		?>
	</p>
	<p class="description">
		<?php esc_html_e( 'Each article is fetched over HTTP to inspect its rendered output, so a full scan may take a little while on large sites.', 'seo-article-booster' ); ?>
	</p>

	<p>
		<button type="button" class="button button-primary" id="sab-schema-start" <?php disabled( ! $enabled ); ?>>
			<span class="dashicons dashicons-search" style="margin-top:4px"></span>
			<?php esc_html_e( 'Run schema scan', 'seo-article-booster' ); ?>
		</button>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sab-settings' ) ); ?>#sab_schema"><?php esc_html_e( 'Schema settings', 'seo-article-booster' ); ?></a>
	</p>

	<div id="sab-schema-progress" class="sab-progress" style="display:none">
		<div class="sab-progress__bar"><span></span></div>
		<p class="sab-progress__label"></p>
	</div>

	<table class="widefat striped" id="sab-schema-table" style="display:none">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Article', 'seo-article-booster' ); ?></th>
				<th style="width:200px"><?php esc_html_e( 'Status', 'seo-article-booster' ); ?></th>
				<th style="width:180px"><?php esc_html_e( 'Actions', 'seo-article-booster' ); ?></th>
			</tr>
		</thead>
		<tbody></tbody>
	</table>

	<p id="sab-schema-empty" class="sab-empty" style="display:none"></p>

	<hr style="margin:28px 0">

	<h2><?php esc_html_e( 'Sitemap coverage', 'seo-article-booster' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Check every URL in your configured sitemap(s) — pages, services, home, tags, everything — for structured data. This keeps your schema in sync with what you actually publish.', 'seo-article-booster' ); ?></p>
	<p>
		<button type="button" class="button button-primary" id="sab-sitemap-schema-start">
			<span class="dashicons dashicons-networking" style="margin-top:4px"></span>
			<?php esc_html_e( 'Scan all sitemap URLs', 'seo-article-booster' ); ?>
		</button>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sab-settings' ) ); ?>#sab_sitemap"><?php esc_html_e( 'Sitemap settings', 'seo-article-booster' ); ?></a>
	</p>

	<div id="sab-sitemap-schema-progress" class="sab-progress" style="display:none">
		<div class="sab-progress__bar"><span></span></div>
		<p class="sab-progress__label"></p>
	</div>

	<table class="widefat striped" id="sab-sitemap-schema-table" style="display:none">
		<thead>
			<tr>
				<th><?php esc_html_e( 'URL', 'seo-article-booster' ); ?></th>
				<th style="width:240px"><?php esc_html_e( 'Schema found', 'seo-article-booster' ); ?></th>
			</tr>
		</thead>
		<tbody></tbody>
	</table>
	<p id="sab-sitemap-schema-empty" class="sab-empty" style="display:none"></p>
</div>
