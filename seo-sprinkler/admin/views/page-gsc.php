<?php
/**
 * Search Console view — one-click connect, index check, queue + submit.
 *
 * @package SeoSprinkler
 *
 * @var SPR_GSC $gsc
 * @var bool    $locked Whether the Expert feature is locked.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_url = admin_url( 'admin-post.php' );
$state    = $gsc->state();
?>
<div class="wrap spr-wrap spr-gsc">
	<h1><?php esc_html_e( 'Search Console & Indexing', 'seo-sprinkler' ); ?></h1>
	<p class="spr-intro"><?php esc_html_e( 'Connect Google Search Console to see which articles are indexed, then queue every unindexed URL and submit a safe number to Google each day. You will be told which URLs were newly indexed, dropped, or still not indexed.', 'seo-sprinkler' ); ?></p>

	<?php if ( $locked ) : ?>
		<div class="notice notice-info inline"><p>
			<span class="dashicons dashicons-star-filled"></span>
			<?php
			printf(
				/* translators: %s: edition label. */
				esc_html__( 'Search Console indexing is an %s feature. Upgrade to connect and submit URLs.', 'seo-sprinkler' ),
				esc_html( SPR_Edition::label( SPR_Edition::required_for( 'indexing' ) ) )
			);
			?>
		</p></div>
		<?php return; ?>
	<?php endif; ?>

	<?php
	// Flash notices from the OAuth round-trip.
	if ( isset( $_GET['connected'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Connected to Google Search Console.', 'seo-sprinkler' ) . '</p></div>';
	} elseif ( isset( $_GET['disconnected'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Disconnected.', 'seo-sprinkler' ) . '</p></div>';
	} elseif ( isset( $_GET['saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-success inline"><p>' . esc_html__( 'Saved.', 'seo-sprinkler' ) . '</p></div>';
	} elseif ( isset( $_GET['error'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Could not connect. Check your client credentials and the redirect URI, then try again.', 'seo-sprinkler' ) . '</p></div>';
	}
	?>

	<?php if ( $gsc->is_connected() ) : ?>

		<div class="spr-panel">
			<h2 class="spr-panel__h"><span class="dashicons dashicons-yes-alt"></span> <?php esc_html_e( 'Connected', 'seo-sprinkler' ); ?></h2>
			<div class="spr-statuslist">
				<div class="spr-statusrow">
					<span class="spr-statusrow__k"><?php esc_html_e( 'Google account', 'seo-sprinkler' ); ?></span>
					<span class="spr-statusrow__v"><?php echo esc_html( $gsc->account_email() ? $gsc->account_email() : __( '(connected)', 'seo-sprinkler' ) ); ?></span>
				</div>
				<div class="spr-statusrow">
					<span class="spr-statusrow__k"><?php esc_html_e( 'Property', 'seo-sprinkler' ); ?></span>
					<span class="spr-statusrow__v"><?php echo esc_html( $gsc->property() ); ?></span>
				</div>
				<div class="spr-statusrow">
					<span class="spr-statusrow__k"><?php esc_html_e( 'Daily submit cap', 'seo-sprinkler' ); ?></span>
					<span class="spr-statusrow__v"><?php echo (int) $gsc->daily_quota(); ?></span>
				</div>
				<div class="spr-statusrow">
					<span class="spr-statusrow__k"><?php esc_html_e( 'Currently queued', 'seo-sprinkler' ); ?></span>
					<span class="spr-statusrow__v"><span class="spr-badge spr-badge--neutral" id="spr-gsc-queued"><?php echo (int) count( $state['queue'] ); ?></span></span>
				</div>
			</div>
			<p>
				<form method="post" action="<?php echo esc_url( $post_url ); ?>" style="display:inline">
					<input type="hidden" name="action" value="spr_gsc_disconnect" />
					<?php wp_nonce_field( 'spr_gsc_disconnect' ); ?>
					<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Disconnect', 'seo-sprinkler' ); ?></button>
				</form>
			</p>
		</div>

		<div class="spr-panel">
			<h2 class="spr-panel__h"><span class="dashicons dashicons-search"></span> <?php esc_html_e( 'Check & submit', 'seo-sprinkler' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Check the index status of your published articles. Unindexed URLs are queued; submit up to your daily cap now, or let the daily schedule drain the queue automatically.', 'seo-sprinkler' ); ?></p>
			<p>
				<button type="button" class="button button-primary" id="spr-gsc-check"><span class="dashicons dashicons-search" style="margin-top:4px"></span> <?php esc_html_e( 'Check index status', 'seo-sprinkler' ); ?></button>
				<button type="button" class="button" id="spr-gsc-submit"><span class="dashicons dashicons-upload" style="margin-top:4px"></span> <?php esc_html_e( 'Submit next batch now', 'seo-sprinkler' ); ?></button>
			</p>

			<div id="spr-gsc-progress" class="spr-progress" style="display:none">
				<div class="spr-progress__bar"><span></span></div>
				<p class="spr-progress__label"></p>
			</div>

			<div id="spr-gsc-results" class="spr-scan-results" style="display:none"></div>

			<form method="post" action="<?php echo esc_url( $post_url ); ?>" style="margin-top:14px">
				<input type="hidden" name="action" value="spr_gsc_schedule" />
				<?php wp_nonce_field( 'spr_gsc_schedule' ); ?>
				<label><input type="checkbox" name="auto" value="1" <?php checked( $gsc->is_scheduled() ); ?> /> <?php esc_html_e( 'Automatically submit the next batch every day until the queue is empty', 'seo-sprinkler' ); ?></label>
				<button type="submit" class="button" style="margin-left:8px"><?php esc_html_e( 'Save schedule', 'seo-sprinkler' ); ?></button>
			</form>
		</div>

		<?php if ( ! empty( $state['log'] ) ) : ?>
			<div class="spr-panel">
				<h2 class="spr-panel__h"><span class="dashicons dashicons-backup"></span> <?php esc_html_e( 'Recent indexing activity', 'seo-sprinkler' ); ?></h2>
				<ul class="spr-timeline">
					<?php foreach ( array_slice( $state['log'], 0, 12 ) as $entry ) : ?>
						<li>
							<time><?php echo esc_html( sprintf( /* translators: %s: time diff */ __( '%s ago', 'seo-sprinkler' ), human_time_diff( (int) $entry['time'], time() ) ) ); ?></time>
							<span class="spr-tl-msg"><?php echo esc_html( $entry['message'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

	<?php else : ?>

		<div class="spr-panel">
			<h2 class="spr-panel__h"><span class="dashicons dashicons-google"></span> <?php esc_html_e( 'Connect', 'seo-sprinkler' ); ?></h2>

			<?php if ( $gsc->is_configured() ) : ?>
				<p><?php esc_html_e( 'Your Google client is set up. Connect your account in one click:', 'seo-sprinkler' ); ?></p>
				<form method="post" action="<?php echo esc_url( $post_url ); ?>">
					<input type="hidden" name="action" value="spr_gsc_connect" />
					<?php wp_nonce_field( 'spr_gsc_connect' ); ?>
					<button type="submit" class="button button-primary button-hero"><span class="dashicons dashicons-google" style="margin-top:8px"></span> <?php esc_html_e( 'Connect Google Search Console', 'seo-sprinkler' ); ?></button>
				</form>
				<hr />
			<?php endif; ?>

			<p class="description">
				<?php esc_html_e( 'One-time setup: in Google Cloud Console create an OAuth client (type: Web application), enable the “Google Search Console API” and the “Web Search Indexing API”, and add this exact redirect URI:', 'seo-sprinkler' ); ?>
			</p>
			<p><code><?php echo esc_html( $gsc->redirect_uri() ); ?></code></p>

			<form method="post" action="<?php echo esc_url( $post_url ); ?>">
				<input type="hidden" name="action" value="spr_gsc_save" />
				<?php wp_nonce_field( 'spr_gsc_save' ); ?>
				<?php $c = $gsc->config(); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="spr-gsc-client-id"><?php esc_html_e( 'OAuth client ID', 'seo-sprinkler' ); ?></label></th>
						<td><input type="text" id="spr-gsc-client-id" name="client_id" class="large-text" value="<?php echo esc_attr( isset( $c['client_id'] ) ? $c['client_id'] : '' ); ?>" placeholder="xxxxx.apps.googleusercontent.com" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="spr-gsc-client-secret"><?php esc_html_e( 'OAuth client secret', 'seo-sprinkler' ); ?></label></th>
						<td><input type="text" id="spr-gsc-client-secret" name="client_secret" class="large-text" value="<?php echo esc_attr( isset( $c['client_secret'] ) ? $c['client_secret'] : '' ); ?>" placeholder="GOCSPX-…" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="spr-gsc-property"><?php esc_html_e( 'Search Console property', 'seo-sprinkler' ); ?></label></th>
						<td>
							<input type="url" id="spr-gsc-property" name="property" class="large-text" value="<?php echo esc_attr( $gsc->property() ); ?>" />
							<p class="description"><?php esc_html_e( 'Use a URL-prefix property exactly as it appears in Search Console (e.g. https://www.example.com/). The connected Google account must be an owner of it.', 'seo-sprinkler' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save client credentials', 'seo-sprinkler' ) ); ?>
			</form>
		</div>

	<?php endif; ?>
</div>
