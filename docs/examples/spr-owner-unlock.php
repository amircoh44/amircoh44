<?php
/**
 * Plugin Name: SEO Sprinkler — Owner Unlock (this site)
 * Description: Runs SEO Sprinkler at full (Expert) edition on a site you OWN, via the
 *              public `spr_edition` filter. Transparent owner override — no key, no
 *              server, nothing hidden. Put this only on installs you control; never
 *              ship an edition override inside the plugin you distribute to others.
 *
 * Install: copy to  wp-content/mu-plugins/spr-owner-unlock.php  (auto-loads; no activation)
 * Remove:  delete the file to revert to normal (key/Free) behaviour.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'spr_edition', function () {
	return 'expert';   // 'pro' or 'expert'
} );
