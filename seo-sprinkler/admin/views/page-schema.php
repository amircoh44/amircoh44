<?php
/**
 * Schema Audit view.
 *
 * Runs a batched check over the audited post types, fetching each article and
 * reporting any that are missing structured data (or the required schema type).
 *
 * @package SeoSprinkler
 *
 * @var SPR_Schema_Scanner $schema
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$enabled  = (bool) SPR_Settings::get( 'enable_schema_check' );
$required = $schema->required_types();
$types    = (array) SPR_Settings::get( 'audit_post_types', array( 'post' ) );
?>
<div class="wrap spr-wrap">
	<h1><?php esc_html_e( 'Schema Audit', 'seo-sprinkler' ); ?></h1>

	<?php if ( ! $enabled ) : ?>
		<div class="notice notice-warning inline">
			<p>
				<?php esc_html_e( 'Schema checking is currently disabled.', 'seo-sprinkler' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=spr-settings' ) ); ?>#spr_schema"><?php esc_html_e( 'Enable it in settings.', 'seo-sprinkler' ); ?></a>
			</p>
		</div>
	<?php endif; ?>

	<p>
		<?php
		if ( $required ) {
			printf(
				/* translators: 1: required schema types, 2: post types. */
				esc_html__( 'Checking that articles expose the schema type(s): %1$s. Post types: %2$s.', 'seo-sprinkler' ),
				'<strong>' . esc_html( implode( ', ', $required ) ) . '</strong>',
				esc_html( implode( ', ', $types ) )
			);
		} else {
			printf(
				/* translators: %s: post types. */
				esc_html__( 'Checking that articles expose any Schema.org structured data. Post types: %s.', 'seo-sprinkler' ),
				esc_html( implode( ', ', $types ) )
			);
		}
		?>
	</p>
	<p class="description">
		<?php esc_html_e( 'Each article is fetched over HTTP to inspect its rendered output, so a full scan may take a little while on large sites.', 'seo-sprinkler' ); ?>
	</p>

	<p>
		<button type="button" class="button button-primary" id="spr-schema-start" <?php disabled( ! $enabled ); ?>>
			<span class="dashicons dashicons-search" style="margin-top:4px"></span>
			<?php esc_html_e( 'Run schema scan', 'seo-sprinkler' ); ?>
		</button>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=spr-settings' ) ); ?>#spr_schema"><?php esc_html_e( 'Schema settings', 'seo-sprinkler' ); ?></a>
	</p>

	<div id="spr-schema-progress" class="spr-progress" style="display:none">
		<div class="spr-progress__bar"><span></span></div>
		<p class="spr-progress__label"></p>
	</div>

	<table class="widefat striped" id="spr-schema-table" style="display:none">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Article', 'seo-sprinkler' ); ?></th>
				<th style="width:200px"><?php esc_html_e( 'Status', 'seo-sprinkler' ); ?></th>
				<th style="width:180px"><?php esc_html_e( 'Actions', 'seo-sprinkler' ); ?></th>
			</tr>
		</thead>
		<tbody></tbody>
	</table>

	<p id="spr-schema-empty" class="spr-empty" style="display:none"></p>

	<hr style="margin:28px 0">

	<h2><?php esc_html_e( 'Sitemap coverage', 'seo-sprinkler' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Check every URL in your configured sitemap(s) — pages, services, home, tags, everything — for structured data. This keeps your schema in sync with what you actually publish.', 'seo-sprinkler' ); ?></p>
	<p>
		<button type="button" class="button button-primary" id="spr-sitemap-schema-start">
			<span class="dashicons dashicons-networking" style="margin-top:4px"></span>
			<?php esc_html_e( 'Scan all sitemap URLs', 'seo-sprinkler' ); ?>
		</button>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=spr-settings' ) ); ?>#spr_sitemap"><?php esc_html_e( 'Sitemap settings', 'seo-sprinkler' ); ?></a>
	</p>

	<div id="spr-sitemap-schema-progress" class="spr-progress" style="display:none">
		<div class="spr-progress__bar"><span></span></div>
		<p class="spr-progress__label"></p>
	</div>

	<table class="widefat striped" id="spr-sitemap-schema-table" style="display:none">
		<thead>
			<tr>
				<th><?php esc_html_e( 'URL', 'seo-sprinkler' ); ?></th>
				<th style="width:240px"><?php esc_html_e( 'Schema found', 'seo-sprinkler' ); ?></th>
			</tr>
		</thead>
		<tbody></tbody>
	</table>
	<p id="spr-sitemap-schema-empty" class="spr-empty" style="display:none"></p>

	<div class="spr-next-step">
		<span class="dashicons dashicons-id"></span>
		<span><?php esc_html_e( 'Missing structured data? Fill in your Business Profile and SEO Sprinkler generates a complete JSON-LD schema graph for your whole site.', 'seo-sprinkler' ); ?></span>
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=spr-business' ) ); ?>"><?php esc_html_e( 'Go to Business Profile', 'seo-sprinkler' ); ?></a>
	</div>
</div>
