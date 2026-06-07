<?php
/**
 * Image Audit view.
 *
 * Runs a batched scan over the audited post types and lists every article that
 * has fewer images than the configured minimum.
 *
 * @package SeoSprinkler
 *
 * @var SPR_Image_Scanner $scanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$min   = $scanner->get_minimum();
$types = (array) SPR_Settings::get( 'audit_post_types', array( 'post' ) );
?>
<div class="wrap spr-wrap">
	<h1><?php esc_html_e( 'Image Audit', 'seo-sprinkler' ); ?></h1>
	<p>
		<?php
		printf(
			/* translators: 1: minimum images, 2: comma separated post types. */
			esc_html__( 'Scanning for articles with fewer than %1$d image(s). Auditing post types: %2$s.', 'seo-sprinkler' ),
			(int) $min,
			esc_html( implode( ', ', $types ) )
		);
		?>
	</p>

	<p>
		<button type="button" class="button button-primary" id="spr-scan-start">
			<span class="dashicons dashicons-search" style="margin-top:4px"></span>
			<?php esc_html_e( 'Run scan', 'seo-sprinkler' ); ?>
		</button>
		<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=spr-settings' ) ); ?>#spr_images"><?php esc_html_e( 'Change minimum', 'seo-sprinkler' ); ?></a>
	</p>

	<div id="spr-scan-progress" class="spr-progress" style="display:none">
		<div class="spr-progress__bar"><span></span></div>
		<p class="spr-progress__label"></p>
	</div>

	<table class="widefat striped" id="spr-deficient-table" style="display:none">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Article', 'seo-sprinkler' ); ?></th>
				<th style="width:120px"><?php esc_html_e( 'Images', 'seo-sprinkler' ); ?></th>
				<th style="width:180px"><?php esc_html_e( 'Actions', 'seo-sprinkler' ); ?></th>
			</tr>
		</thead>
		<tbody></tbody>
	</table>

	<p id="spr-scan-empty" class="spr-empty" style="display:none"></p>
</div>
