<?php
/**
 * Link Audit view — list of posts with link counts, or a per-post link list.
 *
 * @package SeoSprinkler
 *
 * @var SPR_Link_Scanner $scanner
 * @var string           $action  list|view
 * @var int              $post_id
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$list_url = admin_url( 'admin.php?page=' . SPR_Link_Audit_Admin::PAGE );
?>
<div class="wrap spr-wrap">

<?php if ( 'view' === $action && $post_id ) : ?>

	<?php
	$post   = get_post( $post_id );
	$links  = $post ? $scanner->analyze_post( $post ) : array();
	$counts = $post ? $scanner->count_for_post( $post ) : array(
		'internal' => 0,
		'external' => 0,
	);
	?>
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Links in:', 'seo-sprinkler' ); ?> <?php echo esc_html( $post ? get_the_title( $post ) : __( '(missing post)', 'seo-sprinkler' ) ); ?></h1>
	<a href="<?php echo esc_url( $list_url ); ?>" class="page-title-action"><?php esc_html_e( '← Back to list', 'seo-sprinkler' ); ?></a>
	<?php if ( $post ) : ?>
		<a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>" class="page-title-action"><?php esc_html_e( 'Open in editor', 'seo-sprinkler' ); ?></a>
	<?php endif; ?>
	<hr class="wp-header-end">

	<p id="spr-counts">
		<span class="spr-badge spr-int <?php echo $counts['internal'] > 0 ? 'spr-badge--ok' : 'spr-badge--warn'; ?>"><?php printf( /* translators: %d internal links */ esc_html__( 'Internal: %d', 'seo-sprinkler' ), (int) $counts['internal'] ); ?></span>
		<span class="spr-badge spr-badge--neutral spr-ext"><?php printf( /* translators: %d external links */ esc_html__( 'External: %d', 'seo-sprinkler' ), (int) $counts['external'] ); ?></span>
	</p>

	<?php if ( empty( $links ) ) : ?>
		<div class="spr-empty"><?php esc_html_e( 'This article contains no links.', 'seo-sprinkler' ); ?></div>
	<?php else : ?>
		<table class="widefat striped" id="spr-links-table" data-post="<?php echo esc_attr( $post_id ); ?>">
			<thead>
				<tr>
					<th style="width:40px">#</th>
					<th style="width:90px"><?php esc_html_e( 'Type', 'seo-sprinkler' ); ?></th>
					<th><?php esc_html_e( 'Anchor text', 'seo-sprinkler' ); ?></th>
					<th><?php esc_html_e( 'URL', 'seo-sprinkler' ); ?></th>
					<th style="width:90px"><?php esc_html_e( 'Edit', 'seo-sprinkler' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $links as $link ) : ?>
					<tr data-index="<?php echo esc_attr( $link['index'] ); ?>">
						<td><?php echo (int) $link['index'] + 1; ?></td>
						<td>
							<?php if ( 'internal' === $link['type'] ) : ?>
								<span class="spr-badge spr-badge--ok"><?php esc_html_e( 'Internal', 'seo-sprinkler' ); ?></span>
							<?php else : ?>
								<span class="spr-badge spr-badge--neutral"><?php esc_html_e( 'External', 'seo-sprinkler' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="spr-link-text">
							<?php
							echo $link['anchor_text'] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								? esc_html( $link['anchor_text'] )
								: '<em class="spr-muted">' . esc_html__( '(no text — e.g. an image link)', 'seo-sprinkler' ) . '</em>';
							?>
						</td>
						<td class="spr-link-url"><a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $link['url'] ); ?></a></td>
						<td>
							<button type="button" class="button button-small spr-edit-link"
								data-index="<?php echo esc_attr( $link['index'] ); ?>"
								data-url="<?php echo esc_attr( $link['url'] ); ?>"
								data-html="<?php echo esc_attr( $link['anchor_html'] ); ?>">
								<?php esc_html_e( 'Edit', 'seo-sprinkler' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php // The reusable edit modal. ?>
	<div id="spr-link-modal" class="spr-modal" style="display:none" role="dialog" aria-modal="true" aria-labelledby="spr-modal-title">
		<div class="spr-modal__box">
			<h2 id="spr-modal-title"><?php esc_html_e( 'Edit link', 'seo-sprinkler' ); ?></h2>

			<label class="spr-modal__label"><?php esc_html_e( 'Link text', 'seo-sprinkler' ); ?></label>
			<div class="spr-wysiwyg-toolbar">
				<button type="button" data-cmd="bold" title="<?php esc_attr_e( 'Bold', 'seo-sprinkler' ); ?>"><strong>B</strong></button>
				<button type="button" data-cmd="italic" title="<?php esc_attr_e( 'Italic', 'seo-sprinkler' ); ?>"><em>I</em></button>
				<button type="button" data-cmd="removeFormat" title="<?php esc_attr_e( 'Clear formatting', 'seo-sprinkler' ); ?>">&times;</button>
				<span class="spr-charcount"><span id="spr-charnow">0</span> / <?php echo (int) SPR_Link_Scanner::MAX_ANCHOR_CHARS; ?></span>
			</div>
			<div id="spr-wysiwyg" class="spr-wysiwyg" contenteditable="true"></div>

			<label class="spr-modal__label" for="spr-link-url-input"><?php esc_html_e( 'URL', 'seo-sprinkler' ); ?></label>
			<input type="url" id="spr-link-url-input" class="widefat" />

			<p id="spr-modal-error" class="spr-modal__error" style="display:none"></p>

			<p class="spr-modal__actions">
				<button type="button" class="button button-primary" id="spr-link-save"><?php esc_html_e( 'Save', 'seo-sprinkler' ); ?></button>
				<button type="button" class="button" id="spr-link-cancel"><?php esc_html_e( 'Cancel', 'seo-sprinkler' ); ?></button>
			</p>
		</div>
		<div class="spr-modal__backdrop"></div>
	</div>

<?php else : ?>

	<h1><?php esc_html_e( 'Link Audit', 'seo-sprinkler' ); ?></h1>
	<p class="spr-intro"><?php esc_html_e( 'See how many internal and external links each article has, so you can spot posts that need more (or fewer) links. Open any post to see and edit its links.', 'seo-sprinkler' ); ?></p>

	<p>
		<button type="button" class="button button-primary" id="spr-links-scan">
			<span class="dashicons dashicons-search" style="margin-top:4px"></span>
			<?php esc_html_e( 'Scan all posts', 'seo-sprinkler' ); ?>
		</button>
	</p>

	<div id="spr-links-progress" class="spr-progress" style="display:none">
		<div class="spr-progress__bar"><span></span></div>
		<p class="spr-progress__label"></p>
	</div>

	<table class="widefat striped" id="spr-links-results" style="display:none">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Article', 'seo-sprinkler' ); ?></th>
				<th style="width:120px"><?php esc_html_e( 'Internal', 'seo-sprinkler' ); ?></th>
				<th style="width:120px"><?php esc_html_e( 'External', 'seo-sprinkler' ); ?></th>
				<th style="width:120px"><?php esc_html_e( 'Links', 'seo-sprinkler' ); ?></th>
			</tr>
		</thead>
		<tbody></tbody>
	</table>

<?php endif; ?>
</div>
