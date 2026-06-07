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

			<p class="description"><?php esc_html_e( 'One-time setup — about three minutes. After this, connecting is a single click. (A literal zero-setup connect would require a Google-verified hosted app, which a self-hosted plugin can’t bundle.)', 'seo-sprinkler' ); ?></p>

			<p class="description">
				<span class="dashicons dashicons-info-outline" style="color:#2271b1"></span>
				<?php esc_html_e( 'Before you start: you need a free Google account, and that account must be an owner of your site in Search Console.', 'seo-sprinkler' ); ?>
			</p>

			<ol class="spr-steps">
				<li>
					<strong><?php esc_html_e( 'Turn on the two Google APIs', 'seo-sprinkler' ); ?></strong>
					<p class="description"><?php esc_html_e( 'Open each link and click “Enable” (pick or create a Google Cloud project if asked — any name is fine).', 'seo-sprinkler' ); ?></p>
					<p>
						<a class="button" target="_blank" rel="noopener" href="https://console.cloud.google.com/apis/library/searchconsole.googleapis.com"><span class="dashicons dashicons-external" style="margin-top:4px"></span> <?php esc_html_e( 'Enable Search Console API', 'seo-sprinkler' ); ?></a>
						<a class="button" target="_blank" rel="noopener" href="https://console.cloud.google.com/apis/library/indexing.googleapis.com"><span class="dashicons dashicons-external" style="margin-top:4px"></span> <?php esc_html_e( 'Enable Indexing API', 'seo-sprinkler' ); ?></a>
					</p>
				</li>
				<li>
					<strong><?php esc_html_e( 'Set up the consent screen + add yourself as a test user', 'seo-sprinkler' ); ?></strong>
					<p class="description"><?php esc_html_e( 'User type: External. Fill in an app name and your email. Then open “Audience” and add your own Google address under “Test users”. Skipping this is the #1 cause of an “access blocked / app not verified” error when you connect.', 'seo-sprinkler' ); ?></p>
					<p>
						<a class="button" target="_blank" rel="noopener" href="https://console.cloud.google.com/auth/overview"><span class="dashicons dashicons-external" style="margin-top:4px"></span> <?php esc_html_e( 'Open consent screen', 'seo-sprinkler' ); ?></a>
						<a class="button" target="_blank" rel="noopener" href="https://console.cloud.google.com/auth/audience"><span class="dashicons dashicons-external" style="margin-top:4px"></span> <?php esc_html_e( 'Add a test user', 'seo-sprinkler' ); ?></a>
					</p>
				</li>
				<li>
					<strong><?php esc_html_e( 'Create the OAuth client', 'seo-sprinkler' ); ?></strong>
					<p class="description">
						<?php esc_html_e( 'In the “Create credentials” wizard, choose:', 'seo-sprinkler' ); ?>
					</p>
					<ul class="spr-substeps">
						<li><?php echo wp_kses_post( __( 'Which API are you using? → <strong>Google Search Console API</strong>', 'seo-sprinkler' ) ); ?></li>
						<li><?php echo wp_kses_post( __( 'What data will you be accessing? → <strong>User data</strong> (not “Application data”), then <strong>Next</strong>', 'seo-sprinkler' ) ); ?></li>
						<li><?php echo wp_kses_post( __( 'Application type → <strong>Web application</strong>', 'seo-sprinkler' ) ); ?></li>
						<li><?php echo wp_kses_post( __( 'Under <strong>Authorised redirect URIs</strong>, add the URI below, then <strong>Create</strong>', 'seo-sprinkler' ) ); ?></li>
					</ul>
					<p class="spr-copy-row">
						<code class="spr-copy-uri"><?php echo esc_html( $gsc->redirect_uri() ); ?></code>
						<button type="button" class="button button-small spr-copy-btn" data-copy="<?php echo esc_attr( $gsc->redirect_uri() ); ?>"><?php esc_html_e( 'Copy', 'seo-sprinkler' ); ?></button>
						<span class="spr-copied" style="display:none"><?php esc_html_e( 'Copied!', 'seo-sprinkler' ); ?></span>
					</p>
					<p>
						<a class="button" target="_blank" rel="noopener" href="https://console.cloud.google.com/auth/clients/create"><span class="dashicons dashicons-external" style="margin-top:4px"></span> <?php esc_html_e( 'Open “Create OAuth client”', 'seo-sprinkler' ); ?></a>
					</p>
				</li>
				<li>
					<strong><?php esc_html_e( 'Copy the Client ID + secret into the fields below, Save, then click Connect.', 'seo-sprinkler' ); ?></strong>
				</li>
			</ol>

			<details class="spr-help">
				<summary><?php esc_html_e( 'Connect not working? Common fixes', 'seo-sprinkler' ); ?></summary>
				<ul class="spr-substeps">
					<li><?php esc_html_e( '“Access blocked / app isn’t verified” → add your Google address as a Test user (Step 2), or click “Continue (unsafe)” on your own app.', 'seo-sprinkler' ); ?></li>
					<li><?php esc_html_e( '“redirect_uri_mismatch” → the redirect URI in Google must match the one above exactly (including https and the trailing path).', 'seo-sprinkler' ); ?></li>
					<li><?php esc_html_e( 'No data after connecting → the Google account you used must be an owner of the Search Console property below.', 'seo-sprinkler' ); ?></li>
				</ul>
			</details>

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
