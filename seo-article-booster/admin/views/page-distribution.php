<?php
/**
 * Content Distribution admin view (list + add/edit form).
 *
 * @package SeoArticleBooster
 *
 * @var SAB_Injection_Rules $rules
 * @var string              $action  list|edit|new
 * @var array|null          $editing The rule being edited (defaults for "new")
 * @var string              $notice  Optional notice key
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$admin_post = admin_url( 'admin-post.php' );
$page_url   = admin_url( 'admin.php?page=' . SAB_Distribution_Admin::PAGE );

/** Human-readable placement labels. */
$placement_labels = array(
	'top'                  => __( 'Top of content', 'seo-article-booster' ),
	'bottom'               => __( 'Bottom of content', 'seo-article-booster' ),
	'before_paragraph'     => __( 'Before paragraph #N', 'seo-article-booster' ),
	'after_paragraph'      => __( 'After paragraph #N', 'seo-article-booster' ),
	'before_heading'       => __( 'Before sub-heading #N', 'seo-article-booster' ),
	'after_heading'        => __( 'After sub-heading #N', 'seo-article-booster' ),
	'before_first_heading' => __( 'Before the first sub-heading', 'seo-article-booster' ),
	'after_first_heading'  => __( 'After the first sub-heading', 'seo-article-booster' ),
	'before_last_heading'  => __( 'Before the last sub-heading', 'seo-article-booster' ),
	'after_last_heading'   => __( 'After the last sub-heading', 'seo-article-booster' ),
	'between_paragraphs'   => __( 'Between two paragraphs (use empty gap if present)', 'seo-article-booster' ),
	'every_n_paragraphs'   => __( 'After every N paragraphs', 'seo-article-booster' ),
	'middle'               => __( 'Middle of content', 'seo-article-booster' ),
	'after_words'          => __( 'After N words', 'seo-article-booster' ),
);
?>
<div class="wrap sab-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Content Distribution', 'seo-article-booster' ); ?></h1>
	<?php if ( 'list' === $action ) : ?>
		<a href="<?php echo esc_url( add_query_arg( 'action', 'new', $page_url ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add rule', 'seo-article-booster' ); ?></a>
	<?php endif; ?>
	<hr class="wp-header-end">

	<?php if ( $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p>
			<?php
			$messages = array(
				'saved'   => __( 'Rule saved.', 'seo-article-booster' ),
				'deleted' => __( 'Rule deleted.', 'seo-article-booster' ),
				'toggled' => __( 'Rule updated.', 'seo-article-booster' ),
			);
			echo esc_html( isset( $messages[ $notice ] ) ? $messages[ $notice ] : __( 'Done.', 'seo-article-booster' ) );
			?>
		</p></div>
	<?php endif; ?>

	<p class="sab-intro">
		<?php esc_html_e( 'Distribute a shortcode (e.g. an Elementor template), an image, or custom HTML into articles that match a tag, category or keyword — placed exactly where you want around your headings and paragraphs. Your stored content is never changed; blocks are added on the fly.', 'seo-article-booster' ); ?>
	</p>

	<?php
	if ( 'list' === $action ) {
		require __DIR__ . '/partial-distribution-list.php';
	} else {
		require __DIR__ . '/partial-distribution-form.php';
	}
	?>
</div>
