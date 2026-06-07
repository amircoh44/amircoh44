<?php
/**
 * Business Profile view — the questionnaire used to build schema.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$opt = SPR_Business_Profile::OPTION;
$g   = function ( $key ) {
	return SPR_Business_Profile::get( $key );
};

/**
 * Render a labelled text/url/email input.
 */
$field = function ( $key, $label, $type = 'text', $placeholder = '' ) use ( $opt, $g ) {
	printf(
		'<tr><th scope="row"><label for="spr-%1$s">%2$s</label></th><td><input type="%3$s" id="spr-%1$s" name="%4$s[%1$s]" value="%5$s" class="regular-text" placeholder="%6$s" /></td></tr>',
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
		'<tr><th scope="row"><label for="spr-%1$s">%2$s</label></th><td><textarea id="spr-%1$s" name="%4$s[%1$s]" rows="%5$d" class="large-text code" placeholder="%6$s">%3$s</textarea></td></tr>',
		esc_attr( $key ),
		esc_html( $label ),
		esc_textarea( $g( $key ) ),
		esc_attr( $opt ),
		(int) $rows,
		esc_attr( $placeholder )
	);
};
?>
<div class="wrap spr-wrap">
	<h1><?php esc_html_e( 'Business Profile', 'seo-sprinkler' ); ?></h1>
	<p class="spr-intro"><?php esc_html_e( 'Tell us about your business. These details power the structured data (schema) the plugin outputs on every page — Organization / LocalBusiness, plus the right context schema for your home page, pages, services, posts and archives.', 'seo-sprinkler' ); ?></p>

	<?php if ( ! SPR_Business_Profile::is_complete() ) : ?>
		<div class="notice notice-info inline"><p><?php esc_html_e( 'At minimum, fill in your business Name and URL — the rest makes your schema richer and more accurate.', 'seo-sprinkler' ); ?></p></div>
	<?php endif; ?>

	<form action="options.php" method="post">
		<?php settings_fields( SPR_Business_Admin::GROUP ); ?>

			<p class="spr-autofill-bar">
				<button type="button" class="button" id="spr-autofill"><?php esc_html_e( 'Auto-fill from this site', 'seo-sprinkler' ); ?></button>
				<span id="spr-autofill-status" class="description" style="margin-left:8px"></span>
			</p>
			<p class="description" style="margin-top:-4px"><?php esc_html_e( 'Pulls your business Name, Website URL, description and logo from WordPress — only fills fields that are still blank.', 'seo-sprinkler' ); ?></p>

		<h2 class="title"><?php esc_html_e( 'Identity', 'seo-sprinkler' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="spr-business_type"><?php esc_html_e( 'Business type', 'seo-sprinkler' ); ?></label></th>
				<td>
					<select id="spr-business_type" name="<?php echo esc_attr( $opt ); ?>[business_type]">
						<?php
						foreach ( array(
							'Organization'  => __( 'Organization (default)', 'seo-sprinkler' ),
							'LocalBusiness' => __( 'Local business (has a physical location)', 'seo-sprinkler' ),
							'Person'        => __( 'Person / sole proprietor', 'seo-sprinkler' ),
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
				<th scope="row"><label for="spr-local_subtype"><?php esc_html_e( 'Local business type', 'seo-sprinkler' ); ?></label></th>
				<td>
					<input type="text" id="spr-local_subtype" name="<?php echo esc_attr( $opt ); ?>[local_subtype]" value="<?php echo esc_attr( $g( 'local_subtype' ) ); ?>" class="regular-text" list="spr-lb-types" placeholder="<?php esc_attr_e( 'e.g. Plumber, Locksmith, Restaurant', 'seo-sprinkler' ); ?>" />
					<datalist id="spr-lb-types">
						<?php
						foreach ( array( 'Plumber', 'Locksmith', 'Electrician', 'HVACBusiness', 'RoofingContractor', 'Restaurant', 'Dentist', 'Attorney', 'AutoRepair', 'RealEstateAgent', 'BeautySalon', 'Store' ) as $t ) {
							echo '<option value="' . esc_attr( $t ) . '"></option>';
						}
						?>
					</datalist>
					<p class="description"><?php esc_html_e( 'Only used when type is "Local business". Use a schema.org LocalBusiness subtype.', 'seo-sprinkler' ); ?></p>
				</td>
			</tr>
			<?php
			$field( 'name', __( 'Business name', 'seo-sprinkler' ) );
			$field( 'alternate_name', __( 'Alternate name', 'seo-sprinkler' ) );
			$field( 'legal_name', __( 'Legal name', 'seo-sprinkler' ) );
			$field( 'url', __( 'Website URL', 'seo-sprinkler' ), 'url', home_url( '/' ) );
			$textarea( 'description', __( 'Short description', 'seo-sprinkler' ), 3 );
			?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Logo', 'seo-sprinkler' ); ?></th>
				<td><?php spr_business_media_field( 'logo_id', $g( 'logo_id' ), $opt ); ?></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Primary image', 'seo-sprinkler' ); ?></th>
				<td><?php spr_business_media_field( 'image_id', $g( 'image_id' ), $opt ); ?></td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Contact', 'seo-sprinkler' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			$field( 'email', __( 'Email', 'seo-sprinkler' ), 'email' );
			$field( 'telephone', __( 'Telephone', 'seo-sprinkler' ) );
			$field( 'contact_type', __( 'Contact type', 'seo-sprinkler' ), 'text', __( 'customer service', 'seo-sprinkler' ) );
			?>
		</table>

		<h2 class="title"><?php esc_html_e( 'Address', 'seo-sprinkler' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			$field( 'street', __( 'Street address', 'seo-sprinkler' ) );
			$field( 'locality', __( 'City / locality', 'seo-sprinkler' ) );
			$field( 'region', __( 'State / region', 'seo-sprinkler' ) );
			$field( 'postal_code', __( 'Postal code', 'seo-sprinkler' ) );
			$field( 'country', __( 'Country', 'seo-sprinkler' ), 'text', 'US' );
			$field( 'latitude', __( 'Latitude', 'seo-sprinkler' ) );
			$field( 'longitude', __( 'Longitude', 'seo-sprinkler' ) );
			?>
			<tr>
				<th scope="row"></th>
				<td>
					<button type="button" class="button" id="spr-geocode"><?php esc_html_e( 'Look up coordinates from address', 'seo-sprinkler' ); ?></button>
					<span id="spr-geocode-status" class="description" style="margin-left:8px"></span>
					<p class="description"><?php esc_html_e( 'Fills latitude & longitude from the address above using OpenStreetMap — free, no API key needed.', 'seo-sprinkler' ); ?></p>
				</td>
			</tr>
		</table>

		<h2 class="title"><?php esc_html_e( 'Hours, pricing & service area', 'seo-sprinkler' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			$field( 'price_range', __( 'Price range', 'seo-sprinkler' ), 'text', '$$' );
			$textarea( 'opening_hours', __( 'Opening hours', 'seo-sprinkler' ), 4, "Mo-Fr 09:00-17:00\nSa 10:00-14:00" );
			?>
			<tr><td colspan="2" class="description" style="padding-top:0"><?php esc_html_e( 'One rule per line, e.g. "Mo-Fr 09:00-17:00".', 'seo-sprinkler' ); ?></td></tr>
			<?php
			$field( 'area_served', __( 'Areas served', 'seo-sprinkler' ), 'text', __( 'New York, Brooklyn, Queens', 'seo-sprinkler' ) );
			$field( 'founder', __( 'Founder', 'seo-sprinkler' ) );
			$field( 'founding_date', __( 'Founding date', 'seo-sprinkler' ), 'text', 'YYYY or YYYY-MM-DD' );
			$field( 'vat_id', __( 'VAT / Tax ID', 'seo-sprinkler' ) );
			?>
		</table>

		<h2 class="title"><?php esc_html_e( 'Social profiles (sameAs)', 'seo-sprinkler' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			$field( 'facebook', 'Facebook', 'url' );
			$field( 'instagram', 'Instagram', 'url' );
			$field( 'twitter', 'X / Twitter', 'url' );
			$field( 'linkedin', 'LinkedIn', 'url' );
			$field( 'youtube', 'YouTube', 'url' );
			$field( 'tiktok', 'TikTok', 'url' );
			$field( 'pinterest', 'Pinterest', 'url' );
			$textarea( 'extra_profiles', __( 'Other profiles', 'seo-sprinkler' ), 3, "https://...\nhttps://..." );
			?>
		</table>

		<?php submit_button( __( 'Save business profile', 'seo-sprinkler' ) ); ?>
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
function spr_business_media_field( $key, $value, $opt ) {
	$value = (int) $value;
	$src   = $value ? wp_get_attachment_image_url( $value, 'medium' ) : '';
	?>
	<div class="spr-media" data-key="<?php echo esc_attr( $key ); ?>">
		<input type="hidden" name="<?php echo esc_attr( $opt ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" class="spr-media-id" />
		<div class="spr-media-preview"><?php if ( $src ) : ?><img src="<?php echo esc_url( $src ); ?>" alt="" style="max-width:160px;height:auto" /><?php endif; ?></div>
		<button type="button" class="button spr-media-choose"><?php esc_html_e( 'Select image', 'seo-sprinkler' ); ?></button>
		<button type="button" class="button-link spr-media-clear" <?php echo $value ? '' : 'style="display:none"'; ?>><?php esc_html_e( 'Remove', 'seo-sprinkler' ); ?></button>
	</div>
	<?php
}
