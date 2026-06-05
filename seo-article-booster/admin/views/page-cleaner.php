<?php
/**
 * Content Cleaner view: scan, clean, and the reversible change log.
 *
 * @package SeoArticleBooster
 *
 * @var SAB_Content_Cleaner $cleaner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$enabled = array();
foreach ( SAB_Content_Cleaner::cleanups() as $key => $label ) {
	if ( SAB_Settings::get( $key ) ) {
		$enabled[] = $label;
	}
}
$log = $cleaner->get_log();
?>
<div class="wrap sab-wrap">
	<h1><?php esc_html_e( 'Content Cleaner', 'seo-article-booster' ); ?></h1>
	<p class="sab-intro"><?php esc_html_e( 'Remove “generative content trash” from your HTML — leaving clean markup with no CSS or inline styles, and without adding anything to your links.', 'seo-article-booster' ); ?></p>

	<div class="notice notice-warning inline"><p>
		<strong><?php esc_html_e( 'Heads up:', 'seo-article-booster' ); ?></strong>
		<?php esc_html_e( 'Cleaning permanently rewrites post content. A per-post backup is saved before each change, and every change is listed below with a Revert button. Still, taking a full backup first is recommended.', 'seo-article-booster' ); ?>
	</p></div>

	<div class="sab-panel">
		<h2><?php esc_html_e( 'Active cleanups', 'seo-article-booster' ); ?></h2>
		<?php if ( empty( $enabled ) ) : ?>
			<p class="sab-empty"><?php esc_html_e( 'No cleanups are enabled.', 'seo-article-booster' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=sab-settings#sab_cleaner' ) ); ?>"><?php esc_html_e( 'Enable some in settings.', 'seo-article-booster' ); ?></a></p>
		<?php else : ?>
			<ul class="sab-checklist">
				<?php foreach ( $enabled as $label ) : ?>
					<li><span class="dashicons dashicons-yes"></span> <?php echo esc_html( $label ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=sab-settings#sab_cleaner' ) ); ?>"><?php esc_html_e( 'Change cleanups', 'seo-article-booster' ); ?></a></p>
		<?php endif; ?>
	</div>

	<div class="sab-panel">
		<h2><?php esc_html_e( 'Scan & clean', 'seo-article-booster' ); ?></h2>
		<p>
			<button type="button" class="button" id="sab-junk-scan"><?php esc_html_e( 'Scan (preview only)', 'seo-article-booster' ); ?></button>
			<button type="button" class="button button-primary" id="sab-junk-clean"><?php esc_html_e( 'Clean all', 'seo-article-booster' ); ?></button>
		</p>
		<div id="sab-junk-progress" class="sab-progress" style="display:none">
			<div class="sab-progress__bar"><span></span></div>
			<p class="sab-progress__label"></p>
		</div>
		<table class="widefat striped" id="sab-junk-results" style="display:none">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Article', 'seo-article-booster' ); ?></th>
					<th><?php esc_html_e( 'Junk found', 'seo-article-booster' ); ?></th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>
		<p id="sab-junk-empty" class="sab-empty" style="display:none"></p>
	</div>

	<div class="sab-panel">
		<h2><?php esc_html_e( 'Change log', 'seo-article-booster' ); ?></h2>
		<?php if ( empty( $log ) ) : ?>
			<p class="sab-empty"><?php esc_html_e( 'No changes yet. Cleaned posts will appear here so you can review and revert each one.', 'seo-article-booster' ); ?></p>
		<?php else : ?>
			<table class="widefat striped" id="sab-clean-log">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Article', 'seo-article-booster' ); ?></th>
						<th><?php esc_html_e( 'When', 'seo-article-booster' ); ?></th>
						<th><?php esc_html_e( 'What was removed', 'seo-article-booster' ); ?></th>
						<th><?php esc_html_e( 'Status', 'seo-article-booster' ); ?></th>
						<th style="width:90px"></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $log as $entry ) : ?>
						<tr data-post="<?php echo esc_attr( $entry['post_id'] ); ?>">
							<td>
								<?php if ( ! empty( $entry['edit_link'] ) ) : ?>
									<a href="<?php echo esc_url( $entry['edit_link'] ); ?>"><?php echo esc_html( $entry['title'] ? $entry['title'] : '#' . $entry['post_id'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $entry['title'] ? $entry['title'] : '#' . $entry['post_id'] ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( wp_date( 'Y-m-d H:i', (int) $entry['time'] ) ); ?></td>
							<td><?php echo esc_html( $entry['summary'] ? $entry['summary'] : '—' ); ?></td>
							<td class="sab-log-status">
								<?php if ( ! empty( $entry['reverted'] ) ) : ?>
									<span class="sab-badge sab-badge--neutral"><?php esc_html_e( 'Reverted', 'seo-article-booster' ); ?></span>
								<?php else : ?>
									<span class="sab-badge sab-badge--ok"><?php esc_html_e( 'Cleaned', 'seo-article-booster' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="sab-log-action">
								<?php if ( empty( $entry['reverted'] ) && ! empty( $entry['has_backup'] ) ) : ?>
									<button type="button" class="button button-small sab-revert" data-post="<?php echo esc_attr( $entry['post_id'] ); ?>"><?php esc_html_e( 'Revert', 'seo-article-booster' ); ?></button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
