<?php
/**
 * Business Profile view — the questionnaire used to build schema.
 *
 * @package SeoArticleBooster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$opt = SAB_Business_Profile::OPTION;
$g   = function ( $key ) {
	return SAB_Business_Profile::get( $key );
};

/**
 * Render a labelled text/url/email input.
 */
$field = function ( $key, $label, $type = 'text', $placeholder = '' ) use ( $opt, $g ) {
	printf(
		'<tr><th scope="row"><label for="sab-%1$s">%2$s</label></th><td><input type="%3$s" id="sab-%1$s" name="%4$s[%1$s]" value="%5$s" class="regular-text" placeholder="%6$s" /></td></tr>',
		esc_attr( $key ),
		esc_html( $label ),
		esc_attr( $type ),
		esc_attr( $opt ),
		esc_attr( $g( $key ) ),
		esc_attr( $placeholder )
	);
};

/**
 * Render a labelled textarea.
 */
$textarea = function ( $key, $label, $rows, $placeholder = '' ) use ( $opt, $g ) {
	printf(
		'<tr><th scope="row"><label for="sab-%1$s">%2$s</label></th><td><textarea id="sab-%1$s" name="%4$s[%1$s]" rows="%5$d" class="large-text code" placeholder="%6$s">%3$s</textarea></td></tr>',
		esc_attr( $key ),
		esc_html( $label ),
		esc_textarea( $g( $key ) ),
		esc_attr( $opt ),
		(int) $rows,
		esc_attr( $placeholder )
	);
};
?>
<div class="wrap sab-wrap">
	<h1><?php esc_html_e( 'Business Profile', 'seo-article-booster' ); ?></h1>
	<p class="sab-intro"><?php esc_html_e( 'Tell us about your business. These details power the structured data (schema) the plugin outputs on every page — Organization / LocalBusiness, plus the right context schema for your home page, pages, services, posts and archives.', 'seo-article-booster' ); ?></p>

	<?php if ( ! SAB_Business_Profile::is_complete() ) : ?>
		<div class="notice notice-info inline"><p><?php esc_html_e( 'At minimum, fill in your business Name and URL — the rest makes your schema richer and more accurate.', 'seo-article-booster' ); ?></p></div>
	<?php endif; ?>

	<form action="options.php" method="post">
		<?php settings_fields( SAB_Business_Admin::GROUP ); ?>

		<h2 class="title"><?php esc_html_e( 'Identity', 'seo-article-booster' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="sab-business_type"><?php esc_html_e( 'Business type', 'seo-article-booster' ); ?></label></th>
				<td>
					<select id="sab-business_type" name="<?php echo esc_attr( $opt ); ?>[business_type]">
						<?php
						foreach ( array(
							'Organization'  => __( 'Organization (default)', 'seo-article-booster' ),
							'LocalBusiness' => __( 'Local business (has a physical location)', 'seo-article-booster' ),
							'Person'        => __( 'Person / sole proprietor', 'seo-article-booster' ),
						) as $val => $label ) {
							printf(
								'<option value="%s" %s>%s</option>',
								esc_attr( $val ),
								selected( $g( 'business_type' ), $val, false ),
								esc_html( $label )
							);
						}
						?>
					</select>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="sab-local_subtype"><?php esc_html_e( 'Local business type', 'seo-article-booster' ); ?></label></th>
				<td>
					<input type="text" id="sab-local_subtype" name="<?php echo esc_attr( $opt ); ?>[local_subtype]" value="<?php echo esc_attr( $g( 'local_subtype' ) ); ?>" class="regular-text" list="sab-lb-types" placeholder="<?php esc_attr_e( 'e.g. Plumber, Locksmith, Restaurant', 'seo-article-booster' ); ?>" />
					<datalist id="sab-lb-types">
						<?php
						foreach ( array( 'Plumber', 'Locksmith', 'Electrician', 'HVACBusiness', 'RoofingContractor', 'Restaurant', 'Dentist', 'Attorney', 'AutoRepair', 'RealEstateAgent', 'BeautySalon', 'Store' ) as $t ) {
							echo '<option value="' . esc_attr( $t ) . '"></option>';
						}
						?>
					</datalist>
					<p class="description"><?php esc_html_e( 'Only used when type is "Local business". Use a schema.org LocalBusiness subtype.', 'seo-article-booster' ); ?></p>
				</td>
			</tr>
			<?php
			$field( 'name', __( 'Business name', 'seo-article-booster' ) );
			$field( 'alternate_name', __( 'Alternate name', 'seo-article-booster' ) );
			$field( 'legal_name', __( 'Legal name', 'seo-article-booster' ) );
			$field( 'url', __( 'Website URL', 'seo-article-booster' ), 'url', home_url( '/' ) );
			$textarea( 'description', __( 'Short description', 'seo-article-booster' ), 3 );
			?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Logo', 'seo-article-booster' ); ?></th>
				<td><?php sab_business_media_field( 'logo_id', $g( 'logo_id' ), $opt ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Primary image', 'seo-article-booster' ); ?></th>
				<td><?php sab_business_media_field( 'image_id', $g( 'image_id' ), $opt ); ?></td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Contact', 'seo-article-booster' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			$field( 'email', __( 'Email', 'seo-article-booster' ), 'email' );
			$field( 'telephone', __( 'Telephone', 'seo-article-booster' ) );
			$field( 'contact_type', __( 'Contact type', 'seo-article-booster' ), 'text', __( 'customer service', 'seo-article-booster' ) );
			?>
		</table>

		<h2 class="title"><?php esc_html_e( 'Address', 'seo-article-booster' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			$field( 'street', __( 'Street address', 'seo-article-booster' ) );
			$field( 'locality', __( 'City / locality', 'seo-article-booster' ) );
			$field( 'region', __( 'State / region', 'seo-article-booster' ) );
			$field( 'postal_code', __( 'Postal code', 'seo-article-booster' ) );
			$field( 'country', __( 'Country', 'seo-article-booster' ), 'text', 'US' );
			$field( 'latitude', __( 'Latitude', 'seo-article-booster' ) );
			$field( 'longitude', __( 'Longitude', 'seo-article-booster' ) );
			?>
		</table>

		<h2 class="title"><?php esc_html_e( 'Hours, pricing & service area', 'seo-article-booster' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			$field( 'price_range', __( 'Price range', 'seo-article-booster' ), 'text', '$$' );
			$textarea( 'opening_hours', __( 'Opening hours', 'seo-article-booster' ), 4, "Mo-Fr 09:00-17:00\nSa 10:00-14:00" );
			?>
			<tr><td colspan="2" class="description" style="padding-top:0"><?php esc_html_e( 'One rule per line, e.g. "Mo-Fr 09:00-17:00".', 'seo-article-booster' ); ?></td></tr>
			<?php
			$field( 'area_served', __( 'Areas served', 'seo-article-booster' ), 'text', __( 'New York, Brooklyn, Queens', 'seo-article-booster' ) );
			$field( 'founder', __( 'Founder', 'seo-article-booster' ) );
			$field( 'founding_date', __( 'Founding date', 'seo-article-booster' ), 'text', 'YYYY or YYYY-MM-DD' );
			$field( 'vat_id', __( 'VAT / Tax ID', 'seo-article-booster' ) );
			?>
		</table>

		<h2 class="title"><?php esc_html_e( 'Social profiles (sameAs)', 'seo-article-booster' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			$field( 'facebook', 'Facebook', 'url' );
			$field( 'instagram', 'Instagram', 'url' );
			$field( 'twitter', 'X / Twitter', 'url' );
			$field( 'linkedin', 'LinkedIn', 'url' );
			$field( 'youtube', 'YouTube', 'url' );
			$field( 'tiktok', 'TikTok', 'url' );
			$field( 'pinterest', 'Pinterest', 'url' );
			$textarea( 'extra_profiles', __( 'Other profiles', 'seo-article-booster' ), 3, "https://...\nhttps://..." );
			?>
		</table>

		<?php submit_button( __( 'Save business profile', 'seo-article-booster' ) ); ?>
	</form>
</div>
<?php
/**
 * Render a media-picker field (hidden id input + preview + buttons).
 *
 * @param string $key   Field key.
 * @param int    $value Attachment ID.
 * @param string $opt   Option name.
 */
function sab_business_media_field( $key, $value, $opt ) {
	$value = (int) $value;
	$src   = $value ? wp_get_attachment_image_url( $value, 'medium' ) : '';
	?>
	<div class="sab-media" data-key="<?php echo esc_attr( $key ); ?>">
		<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="sab-media-id" />
		<div class="sab-media-preview"><?php if ( $src ) : ?><img src="<?php echo esc_url( $src ); ?>" alt="" style="max-width:160px;height:auto" /><?php endif; ?></div>
		<button type="button" class="button sab-media-choose"><?php esc_html_e( 'Select image', 'seo-article-booster' ); ?></button>
		<button type="button" class="button-link sab-media-clear" <?php echo $value ? '' : 'style="display:none"'; ?>><?php esc_html_e( 'Remove', 'seo-article-booster' ); ?></button>
	</div>
	<?php
}
