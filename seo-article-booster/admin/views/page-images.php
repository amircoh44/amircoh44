<?php
/**
 * Image Audit view.
 *
 * Runs a batched scan over the audited post types and lists every article that
 * has fewer images than the configured minimum.
 *
 * @package SeoArticleBooster
 *
 * @var SAB_Image_Scanner $scanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$min   = $scanner->get_minimum();
$types = (array) SAB_Settings::get( 'audit_post_types', array( 'post' ) );
?>
<div class="wrap sab-wrap">
	<h1><?php esc_html_e( 'Image Audit', 'seo-article-booster' ); ?></h1>
	<p>
		<?php
		printf(
			/* translators: 1: minimum images, 2: comma separated post types. */
			esc_html__( 'Scanning for articles with fewer than %1$d image(s). Auditing post types: %2$s.', 'seo-article-booster' ),
			(int) $min,
			esc_html( implode( ', ', $types ) )
		);
		?>
	</p>

	<p>
		<button type="button" class="button button-primary" id="sab-scan-start">
			<span class="dashicons dashicons-search" style="margin-top:4px"></span>
			<?php esc_html_e( 'Run scan', 'seo-article-booster' ); ?>
		</button>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sab-settings' ) ); ?>#sab_images"><?php esc_html_e( 'Change minimum', 'seo-article-booster' ); ?></a>
	</p>

	<div id="sab-scan-progress" class="sab-progress" style="display:none">
		<div class="sab-progress__bar"><span></span></div>
		<p class="sab-progress__label"></p>
	</div>

	<table class="widefat striped" id="sab-deficient-table" style="display:none">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Article', 'seo-article-booster' ); ?></th>
				<th style="width:120px"><?php esc_html_e( 'Images', 'seo-article-booster' ); ?></th>
				<th style="width:180px"><?php esc_html_e( 'Actions', 'seo-article-booster' ); ?></th>
			</tr>
		</thead>
		<tbody></tbody>
	</table>

	<p id="sab-scan-empty" class="sab-empty" style="display:none"></p>
</div>
