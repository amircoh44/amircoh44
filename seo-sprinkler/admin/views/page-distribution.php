<?php
/**
 * Content Distribution admin view (list + add/edit form).
 *
 * @package SeoSprinkler
 *
 * @var SPR_Injection_Rules $rules
 * @var string              $action  list|edit|new
 * @var array|null          $editing The rule being edited (defaults for "new")
 * @var string              $notice  Optional notice key
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$admin_post = admin_url( 'admin-post.php' );
$page_url   = admin_url( 'admin.php?page=' . SPR_Distribution_Admin::PAGE );

/** Human-readable placement labels. */
$placement_labels = array(
	'top'                  => __( 'Top of content', 'seo-sprinkler' ),
	'bottom'               => __( 'Bottom of content', 'seo-sprinkler' ),
	'before_paragraph'     => __( 'Before paragraph #N', 'seo-sprinkler' ),
	'after_paragraph'      => __( 'After paragraph #N', 'seo-sprinkler' ),
	'before_heading'       => __( 'Before sub-heading #N', 'seo-sprinkler' ),
	'after_heading'        => __( 'After sub-heading #N', 'seo-sprinkler' ),
	'before_first_heading' => __( 'Before the first sub-heading', 'seo-sprinkler' ),
	'after_first_heading'  => __( 'After the first sub-heading', 'seo-sprinkler' ),
	'before_last_heading'  => __( 'Before the last sub-heading', 'seo-sprinkler' ),
	'after_last_heading'   => __( 'After the last sub-heading', 'seo-sprinkler' ),
	'between_paragraphs'   => __( 'Between two paragraphs (use empty gap if present)', 'seo-sprinkler' ),
	'every_n_paragraphs'   => __( 'After every N paragraphs', 'seo-sprinkler' ),
	'middle'               => __( 'Middle of content', 'seo-sprinkler' ),
	'after_words'          => __( 'After N words', 'seo-sprinkler' ),
);
?>
<div class="wrap spr-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Content Distribution', 'seo-sprinkler' ); ?></h1>
	<?php if ( 'list' === $action ) : ?>
		<a href="<?php echo esc_url( add_query_arg( 'action', 'new', $page_url ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add rule', 'seo-sprinkler' ); ?></a>
	<?php endif; ?>
	<hr class="wp-header-end">

	<?php if ( $notice ) : ?>
		<div class="notice notice-success is-dismissible"><p>
			<?php
			$messages = array(
				'saved'   => __( 'Rule saved.', 'seo-sprinkler' ),
				'deleted' => __( 'Rule deleted.', 'seo-sprinkler' ),
				'toggled' => __( 'Rule updated.', 'seo-sprinkler' ),
			);
			echo esc_html( isset( $messages[ $notice ] ) ? $messages[ $notice ] : __( 'Done.', 'seo-sprinkler' ) );
			?>
		</p></div>
	<?php endif; ?>

	<p class="spr-intro">
		<?php esc_html_e( 'Distribute a shortcode (e.g. an Elementor template), an image, or custom HTML into articles that match a tag, category or keyword — placed exactly where you want around your headings and paragraphs. Your stored content is never changed; blocks are added on the fly.', 'seo-sprinkler' ); ?>
	</p>

	<?php
	if ( 'list' === $action ) {
		require __DIR__ . '/partial-distribution-list.php';
	} else {
		require __DIR__ . '/partial-distribution-form.php';
	}
	?>
</div>
