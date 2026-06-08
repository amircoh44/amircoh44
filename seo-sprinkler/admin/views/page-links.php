<?php
/**
 * Internal Links view.
 *
 * Sitemap status, index rebuild, a preview of indexed anchor phrases, and the
 * permanent bulk apply / revert tools.
 *
 * @package SeoSprinkler
 *
 * @var SPR_Link_Index     $index
 * @var SPR_Sitemap_Parser $sitemap
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$entries     = $index->get_index();
$entry_count = count( $entries );
$preview     = array_slice( $entries, 0, 25 );
$sitemap_url = SPR_Settings::get_sitemap_url();
?>
<div class="wrap spr-wrap">
	<h1><?php esc_html_e( 'Internal Links', 'seo-sprinkler' ); ?></h1>

	<div class="spr-panel">
		<h2><?php esc_html_e( 'Yoast sitemap', 'seo-sprinkler' ); ?></h2>
		<p>
			<?php esc_html_e( 'Source:', 'seo-sprinkler' ); ?>
			<a href="<?php echo esc_url( $sitemap_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $sitemap_url ); ?></a>
		</p>
		<p>
			<button type="button" class="button" id="spr-refresh-sitemap"><?php esc_html_e( 'Refresh sitemap', 'seo-sprinkler' ); ?></button>
			<button type="button" class="button button-primary" id="spr-rebuild-index"><?php esc_html_e( 'Rebuild link index', 'seo-sprinkler' ); ?></button>
			<span class="spr-inline-status" id="spr-index-status">
				<?php
				printf(
					/* translators: %d: number of indexed phrases. */
					esc_html__( '%d phrases currently indexed.', 'seo-sprinkler' ),
					(int) $entry_count
				);
				?>
			</span>
		</p>
	</div>

	<div class="spr-panel">
		<h2><?php esc_html_e( 'Permanent linking', 'seo-sprinkler' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'By default links are added on the fly when a page is viewed (nothing is stored). Use these tools to bake the links permanently into your post content, or to remove links the plugin previously added.', 'seo-sprinkler' ); ?>
		</p>
		<p>
			<button type="button" class="button button-primary" id="spr-apply-links"><?php esc_html_e( 'Apply links to all content', 'seo-sprinkler' ); ?></button>
			<button type="button" class="button button-secondary" id="spr-revert-links"><?php esc_html_e( 'Revert applied links', 'seo-sprinkler' ); ?></button>
		</p>

		<div id="spr-apply-progress" class="spr-progress" style="display:none">
			<div class="spr-progress__bar"><span></span></div>
			<p class="spr-progress__label"></p>
		</div>
	</div>

	<div class="spr-panel">
		<h2><?php esc_html_e( 'Indexed phrases (preview)', 'seo-sprinkler' ); ?></h2>
		<?php if ( empty( $preview ) ) : ?>
			<p class="spr-empty"><?php esc_html_e( 'No phrases indexed yet. Make sure Yoast is publishing a sitemap, then rebuild the index above.', 'seo-sprinkler' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Anchor phrase', 'seo-sprinkler' ); ?></th>
						<th><?php esc_html_e( 'Links to', 'seo-sprinkler' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $preview as $entry ) : ?>
						<tr>
							<td><code><?php echo esc_html( $entry['phrase'] ); ?></code></td>
							<td><a href="<?php echo esc_url( $entry['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_the_title( $entry['post_id'] ) ); ?></a></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( $entry_count > count( $preview ) ) : ?>
				<p class="description">
					<?php
					printf(
						/* translators: %d: number of additional phrases. */
						esc_html__( '… and %d more.', 'seo-sprinkler' ),
						(int) ( $entry_count - count( $preview ) )
					);
					?>
				</p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
