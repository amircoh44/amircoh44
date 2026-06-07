<?php
/**
 * Settings view — renders the registered Settings API fields.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap spr-wrap">
	<h1><?php esc_html_e( 'SEO Sprinkler — Settings', 'seo-sprinkler' ); ?></h1>

	<form action="options.php" method="post">
		<?php
		settings_fields( SPR_Settings::GROUP );
		do_settings_sections( 'spr-settings' );
		submit_button();
		?>
	</form>
</div>
