<?php
/**
 * Internal Links view.
 *
 * Sitemap status, index rebuild, a preview of indexed anchor phrases, and the
 * permanent bulk apply / revert tools.
 *
 * @package SeoArticleBooster
 *
 * @var SAB_Link_Index     $index
 * @var SAB_Sitemap_Parser $sitemap
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$entries     = $index->get_index();
$entry_count = count( $entries );
$preview     = array_slice( $entries, 0, 25 );
$sitemap_url = SAB_Settings::get_sitemap_url();
?>
<div class="wrap sab-wrap">
	<h1><?php esc_html_e( 'Internal Links', 'seo-article-booster' ); ?></h1>

	<div class="sab-panel">
		<h2><?php esc_html_e( 'Yoast sitemap', 'seo-article-booster' ); ?></h2>
		<p>
			<?php esc_html_e( 'Source:', 'seo-article-booster' ); ?>
			<a href="<?php echo esc_url( $sitemap_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $sitemap_url ); ?></a>
		</p>
		<p>
			<button type="button" class="button" id="sab-refresh-sitemap"><?php esc_html_e( 'Refresh sitemap', 'seo-article-booster' ); ?></button>
			<button type="button" class="button button-primary" id="sab-rebuild-index"><?php esc_html_e( 'Rebuild link index', 'seo-article-booster' ); ?></button>
			<span class="sab-inline-status" id="sab-index-status">
				<?php
				printf(
					/* translators: %d: number of indexed phrases. */
					esc_html__( '%d phrases currently indexed.', 'seo-article-booster' ),
					(int) $entry_count
				);
				?>
			</span>
		</p>
	</div>

	<div class="sab-panel">
		<h2><?php esc_html_e( 'Permanent linking', 'seo-article-booster' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'By default links are added on the fly when a page is viewed (nothing is stored). Use these tools to bake the links permanently into your post content, or to remove links the plugin previously added.', 'seo-article-booster' ); ?>
		</p>
		<p>
			<button type="button" class="button button-primary" id="sab-apply-links"><?php esc_html_e( 'Apply links to all content', 'seo-article-booster' ); ?></button>
			<button type="button" class="button button-secondary" id="sab-revert-links"><?php esc_html_e( 'Revert applied links', 'seo-article-booster' ); ?></button>
		</p>

		<div id="sab-apply-progress" class="sab-progress" style="display:none">
			<div class="sab-progress__bar"><span></span></div>
			<p class="sab-progress__label"></p>
		</div>
	</div>

	<div class="sab-panel">
		<h2><?php esc_html_e( 'Indexed phrases (preview)', 'seo-article-booster' ); ?></h2>
		<?php if ( empty( $preview ) ) : ?>
			<p class="sab-empty"><?php esc_html_e( 'No phrases indexed yet. Make sure Yoast is publishing a sitemap, then rebuild the index above.', 'seo-article-booster' ); ?></p>
		<?php else : ?>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Anchor phrase', 'seo-article-booster' ); ?></th>
						<th><?php esc_html_e( 'Links to', 'seo-article-booster' ); ?></th>
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
						esc_html__( '… and %d more.', 'seo-article-booster' ),
						(int) ( $entry_count - count( $preview ) )
					);
					?>
				</p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
