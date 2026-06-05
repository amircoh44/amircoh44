<?php
/**
 * Settings view — renders the registered Settings API fields.
 *
 * @package SeoArticleBooster
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap sab-wrap">
	<h1><?php esc_html_e( 'SEO Article Booster — Settings', 'seo-article-booster' ); ?></h1>

	<form action="options.php" method="post">
		<?php
		settings_fields( SAB_Settings::GROUP );
		do_settings_sections( 'sab-settings' );
		submit_button();
		?>
	</form>
</div>
