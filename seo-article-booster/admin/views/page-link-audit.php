<?php
/**
 * Link Audit view — list of posts with link counts, or a per-post link list.
 *
 * @package SeoArticleBooster
 *
 * @var SAB_Link_Scanner $scanner
 * @var string           $action  list|view
 * @var int              $post_id
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$list_url = admin_url( 'admin.php?page=' . SAB_Link_Audit_Admin::PAGE );
?>
<div class="wrap sab-wrap">

<?php if ( 'view' === $action && $post_id ) : ?>

	<?php
	$post   = get_post( $post_id );
	$links  = $post ? $scanner->analyze_post( $post ) : array();
	$counts = $post ? $scanner->count_for_post( $post ) : array(
		'internal' => 0,
		'external' => 0,
	);
	?>
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Links in:', 'seo-article-booster' ); ?> <?php echo esc_html( $post ? get_the_title( $post ) : __( '(missing post)', 'seo-article-booster' ) ); ?></h1>
	<a href="<?php echo esc_url( $list_url ); ?>" class="page-title-action"><?php esc_html_e( '← Back to list', 'seo-article-booster' ); ?></a>
	<?php if ( $post ) : ?>
		<a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>" class="page-title-action"><?php esc_html_e( 'Open in editor', 'seo-article-booster' ); ?></a>
	<?php endif; ?>
	<hr class="wp-header-end">

	<p id="sab-counts">
		<span class="sab-badge sab-int <?php echo $counts['internal'] > 0 ? 'sab-badge--ok' : 'sab-badge--warn'; ?>"><?php printf( /* translators: %d internal links */ esc_html__( 'Internal: %d', 'seo-article-booster' ), (int) $counts['internal'] ); ?></span>
		<span class="sab-badge sab-badge--neutral sab-ext"><?php printf( /* translators: %d external links */ esc_html__( 'External: %d', 'seo-article-booster' ), (int) $counts['external'] ); ?></span>
	</p>

	<?php if ( empty( $links ) ) : ?>
		<div class="sab-empty"><?php esc_html_e( 'This article contains no links.', 'seo-article-booster' ); ?></div>
	<?php else : ?>
		<table class="widefat striped" id="sab-links-table" data-post="<?php echo esc_attr( $post_id ); ?>">
			<thead>
				<tr>
					<th style="width:40px">#</th>
					<th style="width:90px"><?php esc_html_e( 'Type', 'seo-article-booster' ); ?></th>
					<th><?php esc_html_e( 'Anchor text', 'seo-article-booster' ); ?></th>
					<th><?php esc_html_e( 'URL', 'seo-article-booster' ); ?></th>
					<th style="width:90px"><?php esc_html_e( 'Edit', 'seo-article-booster' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $links as $link ) : ?>
					<tr data-index="<?php echo esc_attr( $link['index'] ); ?>">
						<td><?php echo (int) $link['index'] + 1; ?></td>
						<td>
							<?php if ( 'internal' === $link['type'] ) : ?>
								<span class="sab-badge sab-badge--ok"><?php esc_html_e( 'Internal', 'seo-article-booster' ); ?></span>
							<?php else : ?>
								<span class="sab-badge sab-badge--neutral"><?php esc_html_e( 'External', 'seo-article-booster' ); ?></span>
							<?php endif; ?>
						</td>
						<td class="sab-link-text">
							<?php
							echo $link['anchor_text'] // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
								? esc_html( $link['anchor_text'] )
								: '<em class="sab-muted">' . esc_html__( '(no text — e.g. an image link)', 'seo-article-booster' ) . '</em>';
							?>
						</td>
						<td class="sab-link-url"><a href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $link['url'] ); ?></a></td>
						<td>
							<button type="button" class="button button-small sab-edit-link"
								data-index="<?php echo esc_attr( $link['index'] ); ?>"
								data-url="<?php echo esc_attr( $link['url'] ); ?>"
								data-html="<?php echo esc_attr( $link['anchor_html'] ); ?>">
								<?php esc_html_e( 'Edit', 'seo-article-booster' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php // The reusable edit modal. ?>
	<div id="sab-link-modal" class="sab-modal" style="display:none" role="dialog" aria-modal="true" aria-labelledby="sab-modal-title">
		<div class="sab-modal__box">
			<h2 id="sab-modal-title"><?php esc_html_e( 'Edit link', 'seo-article-booster' ); ?></h2>

			<label class="sab-modal__label"><?php esc_html_e( 'Link text', 'seo-article-booster' ); ?></label>
			<div class="sab-wysiwyg-toolbar">
				<button type="button" data-cmd="bold" title="<?php esc_attr_e( 'Bold', 'seo-article-booster' ); ?>"><strong>B</strong></button>
				<button type="button" data-cmd="italic" title="<?php esc_attr_e( 'Italic', 'seo-article-booster' ); ?>"><em>I</em></button>
				<button type="button" data-cmd="removeFormat" title="<?php esc_attr_e( 'Clear formatting', 'seo-article-booster' ); ?>">&times;</button>
				<span class="sab-charcount"><span id="sab-charnow">0</span> / <?php echo (int) SAB_Link_Scanner::MAX_ANCHOR_CHARS; ?></span>
			</div>
			<div id="sab-wysiwyg" class="sab-wysiwyg" contenteditable="true"></div>

			<label class="sab-modal__label" for="sab-link-url-input"><?php esc_html_e( 'URL', 'seo-article-booster' ); ?></label>
			<input type="url" id="sab-link-url-input" class="widefat" />

			<p id="sab-modal-error" class="sab-modal__error" style="display:none"></p>

			<p class="sab-modal__actions">
				<button type="button" class="button button-primary" id="sab-link-save"><?php esc_html_e( 'Save', 'seo-article-booster' ); ?></button>
				<button type="button" class="button" id="sab-link-cancel"><?php esc_html_e( 'Cancel', 'seo-article-booster' ); ?></button>
			</p>
		</div>
		<div class="sab-modal__backdrop"></div>
	</div>

<?php else : ?>

	<h1><?php esc_html_e( 'Link Audit', 'seo-article-booster' ); ?></h1>
	<p class="sab-intro"><?php esc_html_e( 'See how many internal and external links each article has, so you can spot posts that need more (or fewer) links. Open any post to see and edit its links.', 'seo-article-booster' ); ?></p>

	<p>
		<button type="button" class="button button-primary" id="sab-links-scan">
			<span class="dashicons dashicons-search" style="margin-top:4px"></span>
			<?php esc_html_e( 'Scan all posts', 'seo-article-booster' ); ?>
		</button>
	</p>

	<div id="sab-links-progress" class="sab-progress" style="display:none">
		<div class="sab-progress__bar"><span></span></div>
		<p class="sab-progress__label"></p>
	</div>

	<table class="widefat striped" id="sab-links-results" style="display:none">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Article', 'seo-article-booster' ); ?></th>
				<th style="width:120px"><?php esc_html_e( 'Internal', 'seo-article-booster' ); ?></th>
				<th style="width:120px"><?php esc_html_e( 'External', 'seo-article-booster' ); ?></th>
				<th style="width:120px"><?php esc_html_e( 'Links', 'seo-article-booster' ); ?></th>
			</tr>
		</thead>
		<tbody></tbody>
	</table>

<?php endif; ?>
</div>
