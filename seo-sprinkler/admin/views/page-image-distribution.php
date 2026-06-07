<?php
/**
 * Image Distribution view — bulk-fill below-minimum articles with library images.
 *
 * @package SeoSprinkler
 *
 * @var int  $min      The image minimum.
 * @var bool $ai_ready Whether AI alt text is available.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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
				<?php esc_html_e( 'Find articles below the minimum', 'seo-sprinkler' ); ?>
			</button>
		</p>
	</div>

	<div id="spr-imgdist-progress" class="spr-progress" style="display:none">
		<div class="spr-progress__bar"><span></span></div>
		<p class="spr-progress__label"></p>
	</div>

	<div id="spr-imgdist-empty" class="spr-empty" style="display:none"></div>

	<div id="spr-imgdist-wrap" class="spr-panel" style="display:none">
		<p>
			<button type="button" class="button button-primary" id="spr-imgdist-fill">
				<span class="dashicons dashicons-format-image"></span>
				<?php esc_html_e( 'Fill selected with images', 'seo-sprinkler' ); ?>
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
			<tbody></tbody>
		</table>
	</div>
</div>
