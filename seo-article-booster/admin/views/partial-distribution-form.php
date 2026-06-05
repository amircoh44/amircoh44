<?php
/**
 * Content Distribution — add/edit rule form.
 *
 * @package SeoArticleBooster
 *
 * @var SAB_Injection_Rules $rules
 * @var array               $editing The rule (defaults for "new")
 * @var string              $action  new|edit
 * @var string              $page_url
 * @var array               $placement_labels
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$r           = $editing;
$post_types  = get_post_types( array( 'public' => true ), 'objects' );
$image_sizes = array_merge( get_intermediate_image_sizes(), array( 'full' ) );
$img         = $r['image_id'] ? wp_get_attachment_image( (int) $r['image_id'], 'thumbnail', false, array( 'style' => 'max-width:120px;height:auto;' ) ) : '';
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="sab-rule-form">
	<?php wp_nonce_field( 'sab_save_rule' ); ?>
	<input type="hidden" name="action" value="sab_save_rule" />
	<input type="hidden" name="rule[id]" value="<?php echo esc_attr( $r['id'] ); ?>" />

	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="sab-title"><?php esc_html_e( 'Rule name', 'seo-article-booster' ); ?></label></th>
			<td>
				<input name="rule[title]" id="sab-title" type="text" class="regular-text" value="<?php echo esc_attr( $r['title'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. Locksmith CTA on automotive posts', 'seo-article-booster' ); ?>" />
				<label style="margin-left:1em"><input type="checkbox" name="rule[enabled]" value="1" <?php checked( 1, (int) $r['enabled'] ); ?> /> <?php esc_html_e( 'Active', 'seo-article-booster' ); ?></label>
			</td>
		</tr>
	</table>

	<h2 class="title"><?php esc_html_e( 'Targeting', 'seo-article-booster' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Post types', 'seo-article-booster' ); ?></th>
			<td>
				<?php
				foreach ( $post_types as $pt ) :
					if ( 'attachment' === $pt->name ) {
						continue;
					}
					?>
					<label style="margin-right:1em"><input type="checkbox" name="rule[post_types][]" value="<?php echo esc_attr( $pt->name ); ?>" <?php checked( in_array( $pt->name, (array) $r['post_types'], true ) ); ?> /> <?php echo esc_html( $pt->labels->name ); ?></label>
				<?php endforeach; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="sab-match"><?php esc_html_e( 'Match logic', 'seo-article-booster' ); ?></label></th>
			<td>
				<select name="rule[match_logic]" id="sab-match">
					<option value="any" <?php selected( 'any', $r['match_logic'] ); ?>><?php esc_html_e( 'Match ANY condition below', 'seo-article-booster' ); ?></option>
					<option value="all" <?php selected( 'all', $r['match_logic'] ); ?>><?php esc_html_e( 'Match ALL conditions below', 'seo-article-booster' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Leave all conditions blank to target every post of the chosen types.', 'seo-article-booster' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="sab-tags"><?php esc_html_e( 'Tags', 'seo-article-booster' ); ?></label></th>
			<td><input name="rule[tag_slugs]" id="sab-tags" type="text" class="regular-text" value="<?php echo esc_attr( $r['tag_slugs'] ); ?>" placeholder="automotive, car-key" />
				<p class="description"><?php esc_html_e( 'Comma-separated tag slugs or names.', 'seo-article-booster' ); ?></p></td>
		</tr>
		<tr>
			<th scope="row"><label for="sab-cats"><?php esc_html_e( 'Categories', 'seo-article-booster' ); ?></label></th>
			<td><input name="rule[cat_slugs]" id="sab-cats" type="text" class="regular-text" value="<?php echo esc_attr( $r['cat_slugs'] ); ?>" placeholder="services, emergency" /></td>
		</tr>
		<tr>
			<th scope="row"><label for="sab-keywords"><?php esc_html_e( 'Keywords', 'seo-article-booster' ); ?></label></th>
			<td><input name="rule[keywords]" id="sab-keywords" type="text" class="regular-text" value="<?php echo esc_attr( $r['keywords'] ); ?>" placeholder="locksmith, lockout" />
				<p class="description"><?php esc_html_e( 'Matched in the post title or content.', 'seo-article-booster' ); ?></p></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Include / exclude IDs', 'seo-article-booster' ); ?></th>
			<td>
				<input name="rule[include_ids]" type="text" class="regular-text" value="<?php echo esc_attr( $r['include_ids'] ); ?>" placeholder="<?php esc_attr_e( 'Always include: 12, 34', 'seo-article-booster' ); ?>" /><br>
				<input name="rule[exclude_ids]" type="text" class="regular-text" style="margin-top:6px" value="<?php echo esc_attr( $r['exclude_ids'] ); ?>" placeholder="<?php esc_attr_e( 'Never include: 56, 78', 'seo-article-booster' ); ?>" />
			</td>
		</tr>
	</table>

	<h2 class="title"><?php esc_html_e( 'Payload', 'seo-article-booster' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="sab-payload-type"><?php esc_html_e( 'What to inject', 'seo-article-booster' ); ?></label></th>
			<td>
				<select name="rule[payload_type]" id="sab-payload-type" class="sab-payload-type">
					<option value="shortcode" <?php selected( 'shortcode', $r['payload_type'] ); ?>><?php esc_html_e( 'Shortcode (e.g. Elementor template)', 'seo-article-booster' ); ?></option>
					<option value="image" <?php selected( 'image', $r['payload_type'] ); ?>><?php esc_html_e( 'Image', 'seo-article-booster' ); ?></option>
					<option value="html" <?php selected( 'html', $r['payload_type'] ); ?>><?php esc_html_e( 'Custom HTML', 'seo-article-booster' ); ?></option>
				</select>
			</td>
		</tr>
		<tr class="sab-when sab-when-shortcode">
			<th scope="row"><label for="sab-shortcode"><?php esc_html_e( 'Shortcode', 'seo-article-booster' ); ?></label></th>
			<td>
				<textarea name="rule[shortcode]" id="sab-shortcode" rows="2" class="large-text code" placeholder='[elementor-template id="123"]'><?php echo esc_textarea( $r['shortcode'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'Paste your Elementor (or any) shortcode. It is rendered before insertion.', 'seo-article-booster' ); ?></p>
			</td>
		</tr>
		<tr class="sab-when sab-when-image">
			<th scope="row"><?php esc_html_e( 'Image', 'seo-article-booster' ); ?></th>
			<td>
				<input type="hidden" name="rule[image_id]" id="sab-image-id" value="<?php echo esc_attr( (int) $r['image_id'] ); ?>" />
				<div id="sab-image-preview" style="margin-bottom:6px"><?php echo wp_kses_post( $img ); ?></div>
				<button type="button" class="button" id="sab-choose-image"><?php esc_html_e( 'Choose image', 'seo-article-booster' ); ?></button>
				<button type="button" class="button" id="sab-remove-image" <?php disabled( ! $r['image_id'] ); ?>><?php esc_html_e( 'Remove', 'seo-article-booster' ); ?></button>
				<p>
					<label><?php esc_html_e( 'Size', 'seo-article-booster' ); ?>
						<select name="rule[image_size]">
							<?php foreach ( $image_sizes as $size ) : ?>
								<option value="<?php echo esc_attr( $size ); ?>" <?php selected( $size, $r['image_size'] ); ?>><?php echo esc_html( $size ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<label style="margin-left:1em"><?php esc_html_e( 'Align', 'seo-article-booster' ); ?>
						<select name="rule[image_align]">
							<?php foreach ( array( 'none', 'left', 'center', 'right' ) as $al ) : ?>
								<option value="<?php echo esc_attr( $al ); ?>" <?php selected( $al, $r['image_align'] ); ?>><?php echo esc_html( $al ); ?></option>
							<?php endforeach; ?>
						</select>
					</label>
				</p>
				<p>
					<input name="rule[image_link]" type="url" class="regular-text" value="<?php echo esc_attr( $r['image_link'] ); ?>" placeholder="<?php esc_attr_e( 'Optional link URL', 'seo-article-booster' ); ?>" /><br>
					<input name="rule[image_alt]" type="text" class="regular-text" style="margin-top:6px" value="<?php echo esc_attr( $r['image_alt'] ); ?>" placeholder="<?php esc_attr_e( 'Optional alt text override', 'seo-article-booster' ); ?>" />
				</p>
			</td>
		</tr>
		<tr class="sab-when sab-when-html">
			<th scope="row"><label for="sab-html"><?php esc_html_e( 'HTML', 'seo-article-booster' ); ?></label></th>
			<td><textarea name="rule[html]" id="sab-html" rows="4" class="large-text code"><?php echo esc_textarea( $r['html'] ); ?></textarea></td>
		</tr>
	</table>

	<h2 class="title"><?php esc_html_e( 'Placement', 'seo-article-booster' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="sab-placement"><?php esc_html_e( 'Where', 'seo-article-booster' ); ?></label></th>
			<td>
				<select name="rule[placement]" id="sab-placement">
					<?php foreach ( $placement_labels as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $key, $r['placement'] ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="sab-position"><?php esc_html_e( 'N (position / every-N / word count)', 'seo-article-booster' ); ?></label></th>
			<td><input name="rule[position]" id="sab-position" type="number" min="1" class="small-text" value="<?php echo esc_attr( (int) $r['position'] ); ?>" /></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Count these as sub-headings', 'seo-article-booster' ); ?></th>
			<td>
				<?php foreach ( array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) as $h ) : ?>
					<label style="margin-right:.75em"><input type="checkbox" name="rule[heading_levels][]" value="<?php echo esc_attr( $h ); ?>" <?php checked( in_array( $h, (array) $r['heading_levels'], true ) ); ?> /> <?php echo esc_html( strtoupper( $h ) ); ?></label>
				<?php endforeach; ?>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Limits', 'seo-article-booster' ); ?></th>
			<td>
				<label><?php esc_html_e( 'Max insertions', 'seo-article-booster' ); ?> <input name="rule[max_insertions]" type="number" min="1" max="20" class="small-text" value="<?php echo esc_attr( (int) $r['max_insertions'] ); ?>" /></label>
				<label style="margin-left:1em"><?php esc_html_e( 'Min paragraphs before', 'seo-article-booster' ); ?> <input name="rule[min_paragraphs]" type="number" min="0" class="small-text" value="<?php echo esc_attr( (int) $r['min_paragraphs'] ); ?>" /></label>
				<label style="margin-left:1em"><?php esc_html_e( 'Spacing (every-N)', 'seo-article-booster' ); ?> <input name="rule[spacing]" type="number" min="1" class="small-text" value="<?php echo esc_attr( (int) $r['spacing'] ); ?>" /></label>
			</td>
		</tr>
	</table>

	<h2 class="title"><?php esc_html_e( 'Options', 'seo-article-booster' ); ?></h2>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="sab-device"><?php esc_html_e( 'Devices', 'seo-article-booster' ); ?></label></th>
			<td>
				<select name="rule[device]" id="sab-device">
					<option value="all" <?php selected( 'all', $r['device'] ); ?>><?php esc_html_e( 'All devices', 'seo-article-booster' ); ?></option>
					<option value="desktop" <?php selected( 'desktop', $r['device'] ); ?>><?php esc_html_e( 'Desktop only', 'seo-article-booster' ); ?></option>
					<option value="mobile" <?php selected( 'mobile', $r['device'] ); ?>><?php esc_html_e( 'Mobile only', 'seo-article-booster' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="sab-wrapper"><?php esc_html_e( 'Wrapper CSS class', 'seo-article-booster' ); ?></label></th>
			<td><input name="rule[wrapper_class]" id="sab-wrapper" type="text" class="regular-text" value="<?php echo esc_attr( $r['wrapper_class'] ); ?>" placeholder="my-cta-box" /></td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Schedule', 'seo-article-booster' ); ?></th>
			<td>
				<label><?php esc_html_e( 'Start', 'seo-article-booster' ); ?> <input name="rule[start_date]" type="date" value="<?php echo esc_attr( $r['start_date'] ); ?>" /></label>
				<label style="margin-left:1em"><?php esc_html_e( 'End', 'seo-article-booster' ); ?> <input name="rule[end_date]" type="date" value="<?php echo esc_attr( $r['end_date'] ); ?>" /></label>
				<p class="description"><?php esc_html_e( 'Optional. Leave blank to run indefinitely.', 'seo-article-booster' ); ?></p>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Behaviour', 'seo-article-booster' ); ?></th>
			<td>
				<label><input type="checkbox" name="rule[dedupe]" value="1" <?php checked( 1, (int) $r['dedupe'] ); ?> /> <?php esc_html_e( 'Skip if the payload already appears in the content', 'seo-article-booster' ); ?></label><br>
				<label style="margin-top:6px;display:inline-block"><?php esc_html_e( 'Priority', 'seo-article-booster' ); ?> <input name="rule[priority]" type="number" class="small-text" value="<?php echo esc_attr( (int) $r['priority'] ); ?>" /> <span class="description"><?php esc_html_e( '(lower runs first)', 'seo-article-booster' ); ?></span></label>
			</td>
		</tr>
	</table>

	<p class="submit">
		<button type="submit" class="button button-primary"><?php esc_html_e( 'Save rule', 'seo-article-booster' ); ?></button>
		<a class="button" href="<?php echo esc_url( $page_url ); ?>"><?php esc_html_e( 'Cancel', 'seo-article-booster' ); ?></a>
	</p>
</form>
