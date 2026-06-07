<?php
/**
 * Image Distribution view — bulk-fill below-minimum articles with library images.
 *
 * @package SeoSprinkler
 *
 * @var int   $min      The image minimum.
 * @var bool  $ai_ready Whether AI alt text is available.
 * @var array $snapshot Saved scan: { rows:array, updated:int }.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$saved_rows = ( isset( $snapshot['rows'] ) && is_array( $snapshot['rows'] ) ) ? $snapshot['rows'] : array();
$updated    = isset( $snapshot['updated'] ) ? (int) $snapshot['updated'] : 0;
$has_saved  = ! empty( $saved_rows );
?>
<div class="wrap spr-wrap spr-imgdist">
	<h1><?php esc_html_e( 'Image Distribution', 'seo-sprinkler' ); ?></h1>
	<p class="spr-intro"><?php esc_html_e( 'Find every article below your image minimum and fill it with related, randomised images from your media library — inserted straight into the content with alt text. Each fill is backed up and can be reverted from the post editor.', 'seo-sprinkler' ); ?></p>

	<div class="spr-panel">
		<h2 class="title"><?php esc_html_e( 'How to fill', 'seo-sprinkler' ); ?></h2>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'How many images', 'seo-sprinkler' ); ?></th>
				<td>
					<label style="display:block;margin-bottom:6px">
						<input type="radio" name="spr-mode" value="per_article" checked />
						<?php esc_html_e( 'Bring each article up to', 'seo-sprinkler' ); ?>
						<input type="number" id="spr-target" min="1" max="30" value="<?php echo esc_attr( max( 1, $min ) ); ?>" class="small-text" />
						<?php esc_html_e( 'images', 'seo-sprinkler' ); ?>
						<span class="description">(<?php esc_html_e( 'defaults to your image minimum', 'seo-sprinkler' ); ?>)</span>
					</label>
					<label style="display:block">
						<input type="radio" name="spr-mode" value="per_words" />
						<?php esc_html_e( 'One image per', 'seo-sprinkler' ); ?>
						<input type="number" id="spr-per-words" min="50" max="2000" step="25" value="200" class="small-text" />
						<?php esc_html_e( 'words', 'seo-sprinkler' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Alignment', 'seo-sprinkler' ); ?></th>
				<td>
					<select id="spr-align">
						<option value="center"><?php esc_html_e( 'Middle', 'seo-sprinkler' ); ?></option>
						<option value="left"><?php esc_html_e( 'Left', 'seo-sprinkler' ); ?></option>
						<option value="right"><?php esc_html_e( 'Right', 'seo-sprinkler' ); ?></option>
					</select>
					<select id="spr-size" style="margin-left:8px">
						<option value="large"><?php esc_html_e( 'Full size', 'seo-sprinkler' ); ?></option>
						<option value="full"><?php esc_html_e( 'Original', 'seo-sprinkler' ); ?></option>
						<option value="medium"><?php esc_html_e( 'Medium', 'seo-sprinkler' ); ?></option>
						<option value="thumbnail"><?php esc_html_e( 'Thumbnail', 'seo-sprinkler' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Alt text', 'seo-sprinkler' ); ?></th>
				<td>
					<?php if ( $ai_ready ) : ?>
						<label style="margin-right:14px"><input type="radio" name="spr-alt" value="ai" checked /> <?php esc_html_e( 'AI-written (context-aware)', 'seo-sprinkler' ); ?></label>
						<label><input type="radio" name="spr-alt" value="auto" /> <?php esc_html_e( 'Basic (from image + title)', 'seo-sprinkler' ); ?></label>
					<?php else : ?>
						<input type="hidden" id="spr-alt-fixed" value="auto" />
						<span class="description">
							<?php
							printf(
								/* translators: %s: settings link. */
								esc_html__( 'Using basic alt text (image + post title). Connect AI in %s for context-aware alt.', 'seo-sprinkler' ),
								'<a href="' . esc_url( admin_url( 'admin.php?page=spr-settings#ai' ) ) . '">' . esc_html__( 'Settings → AI', 'seo-sprinkler' ) . '</a>'
							);
							?>
						</span>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<p>
			<button type="button" class="button button-primary" id="spr-imgdist-scan">
				<span class="dashicons dashicons-search"></span>
				<?php echo $has_saved ? esc_html__( 'Re-scan all articles', 'seo-sprinkler' ) : esc_html__( 'Find articles below the minimum', 'seo-sprinkler' ); ?>
			</button>
		</p>
	</div>

	<div id="spr-imgdist-progress" class="spr-progress" style="display:none">
		<div class="spr-progress__bar"><span></span></div>
		<p class="spr-progress__label"></p>
	</div>

	<div id="spr-imgdist-empty" class="spr-empty" style="display:none"></div>

	<div id="spr-imgdist-wrap" class="spr-panel"<?php echo $has_saved ? '' : ' style="display:none"'; ?>>
		<?php if ( $updated ) : ?>
			<p class="spr-saved-note" id="spr-imgdist-saved">
				<span class="dashicons dashicons-backup"></span>
				<?php
				printf(
					/* translators: %s: human-readable time difference. */
					esc_html__( 'Showing your saved scan from %s ago. Re-scan any time to refresh.', 'seo-sprinkler' ),
					esc_html( human_time_diff( $updated, time() ) )
				);
				?>
			</p>
		<?php endif; ?>
		<p>
			<button type="button" class="button button-primary" id="spr-imgdist-fill">
				<span class="dashicons dashicons-format-image"></span>
				<?php esc_html_e( 'Fill selected automatically', 'seo-sprinkler' ); ?>
			</button>
			<button type="button" class="button" id="spr-imgdist-review">
				<span class="dashicons dashicons-visibility"></span>
				<?php esc_html_e( 'Review each image first', 'seo-sprinkler' ); ?>
			</button>
			<button type="button" class="button" id="spr-imgdist-review-all">
				<span class="dashicons dashicons-list-view"></span>
				<?php esc_html_e( 'Preview & edit all', 'seo-sprinkler' ); ?>
			</button>
			<button type="button" class="button button-link-delete" id="spr-imgdist-remove">
				<span class="dashicons dashicons-trash"></span>
				<?php esc_html_e( 'Remove inserted images', 'seo-sprinkler' ); ?>
			</button>
			<span id="spr-imgdist-selcount" class="description" style="margin-left:8px"></span>
		</p>
		<table class="widefat striped" id="spr-imgdist-table">
			<thead>
				<tr>
					<th style="width:34px"><input type="checkbox" id="spr-imgdist-all" checked /></th>
					<th><?php esc_html_e( 'Article', 'seo-sprinkler' ); ?></th>
					<th style="width:120px"><?php esc_html_e( 'Images now', 'seo-sprinkler' ); ?></th>
					<th style="width:220px"><?php esc_html_e( 'Result', 'seo-sprinkler' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $saved_rows as $row ) : ?>
					<tr data-id="<?php echo esc_attr( $row['id'] ); ?>">
						<td><input type="checkbox" class="spr-imgdist-cb" checked /></td>
						<td>
							<?php if ( ! empty( $row['edit_link'] ) ) : ?>
								<a href="<?php echo esc_url( $row['edit_link'] ); ?>" target="_blank"><?php echo esc_html( $row['title'] ); ?></a>
							<?php else : ?>
								<span><?php echo esc_html( $row['title'] ); ?></span>
							<?php endif; ?>
						</td>
						<td><span class="spr-badge spr-badge--warn"><?php echo (int) $row['count']; ?></span></td>
						<td class="spr-imgdist-result">—</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>

	<?php /* Preview & edit ALL proposed images before inserting. */ ?>
	<div id="spr-review-all" class="spr-panel" style="display:none">
		<h2 class="spr-panel__h"><span class="dashicons dashicons-list-view"></span> <?php esc_html_e( 'Preview & edit images before inserting', 'seo-sprinkler' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Edit any alt text, caption, image title or description. Tick “Skip” to drop an image, then insert them all at once. Images use the alignment and size you chose above.', 'seo-sprinkler' ); ?></p>
		<div id="spr-review-all-list"></div>
		<p id="spr-review-all-empty" class="spr-empty" style="display:none"></p>
		<p class="spr-ra-foot">
			<button type="button" class="button button-primary" id="spr-review-all-insert"><?php esc_html_e( 'Insert all approved images', 'seo-sprinkler' ); ?></button>
			<button type="button" class="button" id="spr-review-all-cancel"><?php esc_html_e( 'Cancel', 'seo-sprinkler' ); ?></button>
			<span id="spr-review-all-count" class="description" style="margin-left:6px"></span>
		</p>
	</div>

	<?php /* Per-image reviewer modal (Approve / Skip / Approve-all). */ ?>
	<div id="spr-review" class="spr-modal" style="display:none" aria-hidden="true">
		<div class="spr-modal__backdrop"></div>
		<div class="spr-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="spr-review-heading">
			<div class="spr-modal__head">
				<h2 id="spr-review-heading" class="spr-modal__title"></h2>
				<button type="button" class="spr-modal__close" id="spr-review-close" aria-label="<?php esc_attr_e( 'Close', 'seo-sprinkler' ); ?>">&times;</button>
			</div>
			<p class="spr-modal__progress" id="spr-review-progress"></p>
			<div class="spr-modal__body spr-review__body">
				<div class="spr-review__thumb">
					<img id="spr-review-thumb" src="" alt="" />
				</div>
				<div class="spr-review__fields">
					<p>
						<label for="spr-review-alt"><strong class="spr-review-lbl-alt"></strong></label>
						<input type="text" id="spr-review-alt" class="widefat" />
					</p>
					<p>
						<label for="spr-review-caption"><strong class="spr-review-lbl-cap"></strong></label>
						<input type="text" id="spr-review-caption" class="widefat" />
					</p>
					<p>
						<label for="spr-review-title"><strong class="spr-review-lbl-ttl"></strong></label>
						<input type="text" id="spr-review-title" class="widefat" />
					</p>
					<p>
						<label for="spr-review-desc"><strong class="spr-review-lbl-desc"></strong></label>
						<textarea id="spr-review-desc" rows="3" class="widefat"></textarea>
					</p>
				</div>
			</div>
			<div class="spr-modal__foot">
				<button type="button" class="button button-primary" id="spr-review-approve"></button>
				<button type="button" class="button" id="spr-review-skip"></button>
				<button type="button" class="button" id="spr-review-approve-all"></button>
				<span class="spinner spr-review__spin"></span>
				<button type="button" class="button button-link" id="spr-review-finish" style="margin-left:auto"></button>
			</div>
		</div>
	</div>
</div>
