<?php
/**
 * Content Distribution — rules list table.
 *
 * @package SeoArticleBooster
 *
 * @var SAB_Injection_Rules $rules
 * @var string              $page_url
 * @var array               $placement_labels
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$all = $rules->get_rules();
?>
<?php if ( empty( $all ) ) : ?>
	<div class="sab-empty">
		<?php esc_html_e( 'No distribution rules yet. Click “Add rule” to create your first one — for example, inject a locksmith Elementor template before the first sub-heading on every post tagged “automotive”.', 'seo-article-booster' ); ?>
	</div>
<?php else : ?>
	<table class="widefat striped">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Rule', 'seo-article-booster' ); ?></th>
				<th><?php esc_html_e( 'Targets', 'seo-article-booster' ); ?></th>
				<th><?php esc_html_e( 'Payload', 'seo-article-booster' ); ?></th>
				<th><?php esc_html_e( 'Placement', 'seo-article-booster' ); ?></th>
				<th><?php esc_html_e( 'Status', 'seo-article-booster' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'seo-article-booster' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach ( $all as $rule ) :
				$edit_url   = add_query_arg(
					array(
						'action' => 'edit',
						'id'     => $rule['id'],
					),
					$page_url
				);
				$delete_url = wp_nonce_url(
					add_query_arg(
						array(
							'action' => 'sab_delete_rule',
							'id'     => $rule['id'],
						),
						admin_url( 'admin-post.php' )
					),
					'sab_delete_rule_' . $rule['id']
				);
				$toggle_url = wp_nonce_url(
					add_query_arg(
						array(
							'action' => 'sab_toggle_rule',
							'id'     => $rule['id'],
						),
						admin_url( 'admin-post.php' )
					),
					'sab_toggle_rule_' . $rule['id']
				);

				// Build target/payload summaries.
				$targets = array();
				if ( '' !== trim( $rule['tag_slugs'] ) ) {
					$targets[] = sprintf( /* translators: %s: tags */ __( 'tags: %s', 'seo-article-booster' ), $rule['tag_slugs'] );
				}
				if ( '' !== trim( $rule['cat_slugs'] ) ) {
					$targets[] = sprintf( /* translators: %s: categories */ __( 'cats: %s', 'seo-article-booster' ), $rule['cat_slugs'] );
				}
				if ( '' !== trim( $rule['keywords'] ) ) {
					$targets[] = sprintf( /* translators: %s: keywords */ __( 'kw: %s', 'seo-article-booster' ), $rule['keywords'] );
				}
				if ( empty( $targets ) ) {
					$targets[] = __( 'all posts of the chosen types', 'seo-article-booster' );
				}

				$payload_summary = ucfirst( $rule['payload_type'] );
				if ( 'image' === $rule['payload_type'] && $rule['image_id'] ) {
					$payload_summary .= ' #' . (int) $rule['image_id'];
				}
				?>
				<tr>
					<td>
						<strong><a href="<?php echo esc_url( $edit_url ); ?>"><?php echo esc_html( $rule['title'] ? $rule['title'] : __( '(untitled rule)', 'seo-article-booster' ) ); ?></a></strong>
						<div class="row-actions"><span class="sab-muted"><?php echo esc_html( $rule['id'] ); ?></span></div>
					</td>
					<td>
						<?php echo esc_html( implode( ' · ', $targets ) ); ?>
						<div class="sab-muted"><?php echo esc_html( implode( ', ', (array) $rule['post_types'] ) ); ?> · <?php echo esc_html( 'all' === $rule['match_logic'] ? __( 'match all', 'seo-article-booster' ) : __( 'match any', 'seo-article-booster' ) ); ?></div>
					</td>
					<td><?php echo esc_html( $payload_summary ); ?></td>
					<td>
						<?php echo esc_html( isset( $placement_labels[ $rule['placement'] ] ) ? $placement_labels[ $rule['placement'] ] : $rule['placement'] ); ?>
						<div class="sab-muted">
							<?php
							printf(
								/* translators: 1: position, 2: max insertions */
								esc_html__( 'N=%1$d · max %2$d', 'seo-article-booster' ),
								(int) $rule['position'],
								(int) $rule['max_insertions']
							);
							?>
						</div>
					</td>
					<td>
						<?php if ( ! empty( $rule['enabled'] ) ) : ?>
							<span class="sab-badge sab-badge--ok"><span class="dashicons dashicons-yes"></span> <?php esc_html_e( 'Active', 'seo-article-booster' ); ?></span>
						<?php else : ?>
							<span class="sab-badge sab-badge--neutral"><?php esc_html_e( 'Paused', 'seo-article-booster' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<a class="button button-small" href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'seo-article-booster' ); ?></a>
						<a class="button button-small" href="<?php echo esc_url( $toggle_url ); ?>"><?php echo empty( $rule['enabled'] ) ? esc_html__( 'Enable', 'seo-article-booster' ) : esc_html__( 'Pause', 'seo-article-booster' ); ?></a>
						<a class="button button-small button-link-delete" href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this rule?', 'seo-article-booster' ) ); ?>');"><?php esc_html_e( 'Delete', 'seo-article-booster' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php endif; ?>
