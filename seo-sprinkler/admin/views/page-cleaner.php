<?php
/**
 * Content Cleaner view: scan, clean, and the reversible change log.
 *
 * @package SeoSprinkler
 *
 * @var SPR_Content_Cleaner $cleaner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$enabled = array();
foreach ( SPR_Content_Cleaner::cleanups() as $key => $label ) {
	if ( SPR_Settings::get( $key ) ) {
		$enabled[] = $label;
	}
}
$log = $cleaner->get_log();
?>
<div class="wrap spr-wrap">
	<h1><?php esc_html_e( 'Content Cleaner', 'seo-sprinkler' ); ?></h1>
	<p class="spr-intro"><?php esc_html_e( 'Remove “generative content trash” from your HTML — leaving clean markup with no CSS or inline styles, and without adding anything to your links.', 'seo-sprinkler' ); ?></p>

	<div class="notice notice-warning inline"><p>
		<strong><?php esc_html_e( 'Heads up:', 'seo-sprinkler' ); ?></strong>
		<?php esc_html_e( 'Cleaning permanently rewrites post content. A per-post backup is saved before each change, and every change is listed below with a Revert button. Still, taking a full backup first is recommended.', 'seo-sprinkler' ); ?>
	</p></div>

	<div class="spr-panel">
		<h2><?php esc_html_e( 'Active cleanups', 'seo-sprinkler' ); ?></h2>
		<?php if ( empty( $enabled ) ) : ?>
			<p class="spr-empty"><?php esc_html_e( 'No cleanups are enabled.', 'seo-sprinkler' ); ?> <a href="<?php echo esc_url( admin_url( 'admin.php?page=spr-settings#spr_cleaner' ) ); ?>"><?php esc_html_e( 'Enable some in settings.', 'seo-sprinkler' ); ?></a></p>
		<?php else : ?>
			<ul class="spr-checklist">
				<?php foreach ( $enabled as $label ) : ?>
					<li><span class="dashicons dashicons-yes"></span> <?php echo esc_html( $label ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=spr-settings#spr_cleaner' ) ); ?>"><?php esc_html_e( 'Change cleanups', 'seo-sprinkler' ); ?></a></p>
		<?php endif; ?>
	</div>

	<div class="spr-panel">
		<h2><?php esc_html_e( 'Scan & clean', 'seo-sprinkler' ); ?></h2>
		<p>
			<button type="button" class="button" id="spr-junk-scan"><?php esc_html_e( 'Scan (preview only)', 'seo-sprinkler' ); ?></button>
			<button type="button" class="button button-primary" id="spr-junk-clean"><?php esc_html_e( 'Clean all', 'seo-sprinkler' ); ?></button>
		</p>
		<div id="spr-junk-progress" class="spr-progress" style="display:none">
			<div class="spr-progress__bar"><span></span></div>
			<p class="spr-progress__label"></p>
		</div>
		<table class="widefat striped" id="spr-junk-results" style="display:none">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Article', 'seo-sprinkler' ); ?></th>
					<th><?php esc_html_e( 'Junk found', 'seo-sprinkler' ); ?></th>
				</tr>
			</thead>
			<tbody></tbody>
		</table>
		<p id="spr-junk-empty" class="spr-empty" style="display:none"></p>
	</div>

	<div class="spr-panel">
		<h2><?php esc_html_e( 'Change log', 'seo-sprinkler' ); ?></h2>
		<?php if ( empty( $log ) ) : ?>
			<p class="spr-empty"><?php esc_html_e( 'No changes yet. Cleaned posts will appear here so you can review and revert each one.', 'seo-sprinkler' ); ?></p>
		<?php else : ?>
			<table class="widefat striped" id="spr-clean-log">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Article', 'seo-sprinkler' ); ?></th>
						<th><?php esc_html_e( 'When', 'seo-sprinkler' ); ?></th>
						<th><?php esc_html_e( 'What was removed', 'seo-sprinkler' ); ?></th>
						<th><?php esc_html_e( 'Status', 'seo-sprinkler' ); ?></th>
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
							<td class="spr-log-status">
								<?php if ( ! empty( $entry['reverted'] ) ) : ?>
									<span class="spr-badge spr-badge--neutral"><?php esc_html_e( 'Reverted', 'seo-sprinkler' ); ?></span>
								<?php else : ?>
									<span class="spr-badge spr-badge--ok"><?php esc_html_e( 'Cleaned', 'seo-sprinkler' ); ?></span>
								<?php endif; ?>
							</td>
							<td class="spr-log-action">
								<?php if ( empty( $entry['reverted'] ) && ! empty( $entry['has_backup'] ) ) : ?>
									<button type="button" class="button button-small spr-revert" data-post="<?php echo esc_attr( $entry['post_id'] ); ?>"><?php esc_html_e( 'Revert', 'seo-sprinkler' ); ?></button>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
</div>
