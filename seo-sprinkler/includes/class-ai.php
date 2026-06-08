<?php
/**
 * AI client (bring-your-own API).
 *
 * Provider-agnostic, OpenAI-compatible chat client. The user pastes their own
 * endpoint URL + API key + model in Settings, so it works with OpenAI,
 * OpenRouter, Azure OpenAI, Together, or a self-hosted/local LLM that speaks the
 * /chat/completions shape. The plugin never ships or proxies any key.
 *
 * Used by Pro features such as "generate meta description". Kept tiny and
 * dependency-free (wp_remote_post).
 *
 * @package SeoSprinkler
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SPR_AI
 */
class SPR_AI {

	/**
	 * Is an endpoint + key configured?
	 *
	 * @return bool
	 */
	public static function is_configured() {
		return '' !== trim( (string) SPR_Settings::get( 'ai_endpoint' ) )
			&& '' !== trim( (string) SPR_Settings::get( 'ai_key' ) );
	}

	/**
	 * Run a chat completion.
	 *
	 * @param string $system     System instruction.
	 * @param string $user       User content.
	 * @param int    $max_tokens Max tokens.
	 * @return string|WP_Error The model's text reply.
	 */
	public static function generate( $system, $user, $max_tokens = 220 ) {
		if ( ! self::is_configured() ) {
			return new WP_Error( 'spr_ai_unconfigured', __( 'Add your AI endpoint and API key in Settings → AI first.', 'seo-sprinkler' ) );
		}

		$endpoint = trim( (string) SPR_Settings::get( 'ai_endpoint' ) );
		$model    = trim( (string) SPR_Settings::get( 'ai_model' ) );
		$model    = '' !== $model ? $model : 'gpt-4o-mini';

		$body = array(
			'model'       => $model,
			'messages'    => array(
				array( 'role' => 'system', 'content' => $system ),
				array( 'role' => 'user', 'content' => $user ),
			),
			'max_tokens'  => (int) $max_tokens,
			'temperature' => 0.7,
		);

		/**
		 * Filter the request body sent to the AI provider.
		 *
		 * @param array $body Request body.
		 */
		$body = apply_filters( 'spr_ai_request_body', $body );

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . SPR_Settings::get( 'ai_key' ),
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = isset( $data['error']['message'] ) ? $data['error']['message'] : sprintf( /* translators: %d: HTTP status. */ __( 'AI request failed (HTTP %d).', 'seo-sprinkler' ), $code );
			return new WP_Error( 'spr_ai_http', $message );
		}

		// OpenAI-compatible shape.
		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			return trim( (string) $data['choices'][0]['message']['content'] );
		}
		// Some providers return choices[0].text.
		if ( isset( $data['choices'][0]['text'] ) ) {
			return trim( (string) $data['choices'][0]['text'] );
		}

		return new WP_Error( 'spr_ai_parse', __( 'Unexpected response from the AI provider.', 'seo-sprinkler' ) );
	}

	/**
	 * A plain-text snippet of a post for prompting.
	 *
	 * @param int $post_id Post ID.
	 * @param int $chars   Max characters.
	 * @return string
	 */
	protected static function post_text( $post_id, $chars = 1500 ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		$text = wp_strip_all_tags( $post->post_content );
		$text = trim( preg_replace( '/\s+/', ' ', $text ) );
		return get_the_title( $post_id ) . "\n\n" . mb_substr( $text, 0, $chars );
	}

	/**
	 * Generate an SEO meta description for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string|WP_Error
	 */
	public static function generate_meta_description( $post_id ) {
		return self::generate(
			'You are an expert SEO copywriter. Write a single compelling meta description of at most 155 characters for the article below. Use active voice, include the main topic, and add a gentle call to action. Reply with ONLY the description text — no quotes, no labels.',
			self::post_text( $post_id ),
			120
		);
	}

	/**
	 * Generate an SEO title for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return string|WP_Error
	 */
	public static function generate_title( $post_id ) {
		return self::generate(
			'You are an expert SEO copywriter. Write a single click-worthy SEO title of at most 60 characters for the article below. Reply with ONLY the title.',
			self::post_text( $post_id ),
			40
		);
	}
}
