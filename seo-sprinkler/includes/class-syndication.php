<?php
/**
 * Syndication — push published articles to other platforms.
 *
 * On first publish, the post is POSTed as JSON to one or more outbound webhook
 * URLs. Point those at Zapier / Make / n8n / IFTTT, which have native actions
 * for Google Business Profile (GMB), Facebook, LinkedIn, X and more — so your
 * articles reach those platforms today without OAuth apps. (Native first-party
 * OAuth integrations are on the roadmap.)
 *
 * Expert feature.
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_Syndication
 */
class SPR_Syndication {

	/**
	 * Hooks.
	 */
	public function init() {
		add_action( 'transition_post_status', array( $this, 'on_transition' ), 20, 3 );
	}

	/**
	 * Dispatch when a post first becomes published.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 */
	public function on_transition( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}
		if ( ! SPR_Settings::get( 'syndicate_enabled' ) ) {
			return;
		}
		if ( ! SPR_Edition::can( 'syndication' ) ) {
			return; // Expert feature.
		}
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( ! in_array( $post->post_type, (array) SPR_Settings::get( 'syndicate_post_types', array( 'post' ) ), true ) ) {
			return;
		}
		$this->dispatch( $post );
	}

	/**
	 * Configured outbound webhook URLs.
	 *
	 * @return string[]
	 */
	public function webhooks() {
		$raw  = (string) SPR_Settings::get( 'syndicate_webhooks', '' );
		$urls = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$urls[] = $line;
			}
		}
		return array_values( array_unique( $urls ) );
	}

	/**
	 * Build the JSON payload describing a published post.
	 *
	 * @param int|WP_Post $post Post.
	 * @return array
	 */
	public function build_payload( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return array();
		}

		$excerpt = has_excerpt( $post ) ? $post->post_excerpt : wp_strip_all_tags( $post->post_content );
		$excerpt = wp_html_excerpt( trim( preg_replace( '/\s+/', ' ', $excerpt ) ), 300, '…' );

		$payload = array(
			'event' => 'post_published',
			'site'  => array(
				'name' => get_bloginfo( 'name' ),
				'url'  => home_url( '/' ),
			),
			'post'  => array(
				'id'        => $post->ID,
				'type'      => $post->post_type,
				'title'     => get_the_title( $post ),
				'url'       => get_permalink( $post ),
				'excerpt'   => $excerpt,
				'image'     => get_the_post_thumbnail_url( $post, 'full' ),
				'author'    => get_the_author_meta( 'display_name', $post->post_author ),
				'published' => $post->post_date_gmt,
				'tags'      => wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) ),
			),
		);

		/**
		 * Filter the syndication payload.
		 *
		 * @param array   $payload Payload.
		 * @param WP_Post $post    Post.
		 */
		return apply_filters( 'spr_syndication_payload', $payload, $post );
	}

	/**
	 * POST the payload to every configured webhook (non-blocking).
	 *
	 * @param int|WP_Post $post Post.
	 * @return int Number of webhooks notified.
	 */
	public function dispatch( $post ) {
		$urls = $this->webhooks();
		if ( empty( $urls ) ) {
			return 0;
		}
		$body = wp_json_encode( $this->build_payload( $post ) );
		$sent = 0;
		foreach ( $urls as $url ) {
			wp_remote_post(
				$url,
				array(
					'timeout'  => 15,
					'blocking' => false,
					'headers'  => array( 'Content-Type' => 'application/json' ),
					'body'     => $body,
				)
			);
			$sent++;
		}

		$post_obj = get_post( $post );
		/**
		 * Fires after a post has been syndicated.
		 *
		 * @param int      $post_id Post ID.
		 * @param string[] $urls    Webhook URLs notified.
		 */
		do_action( 'spr_syndicated', $post_obj ? $post_obj->ID : 0, $urls );

		return $sent;
	}
}
