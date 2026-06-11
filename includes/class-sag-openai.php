<?php
/**
 * OpenAI API client. Used on WordPress < 7.0 (no native AI Connectors).
 *
 * @package Smart_Alt_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class SAG_OpenAI {

    const ENDPOINT = 'https://api.openai.com/v1/chat/completions';

    /**
     * Build the JSON request payload for the vision chat completion.
     * Pure function — no WordPress calls, fully unit-testable.
     *
     * @param string $image_url Public URL of the image.
     * @param string $prompt    Instruction text.
     * @param string $model     Model id, e.g. gpt-4o-mini.
     * @return array
     */
    public function build_request_body( $image_url, $prompt, $model ) {
        return array(
            'model'      => $model,
            'max_tokens' => 150,
            'messages'   => array(
                array(
                    'role'    => 'user',
                    'content' => array(
                        array( 'type' => 'text', 'text' => $prompt ),
                        array( 'type' => 'image_url', 'image_url' => array( 'url' => $image_url ) ),
                    ),
                ),
            ),
        );
    }

    /**
     * Parse the decoded OpenAI response into alt text or a WP_Error.
     * Pure function — fully unit-testable.
     *
     * @param array $data Decoded JSON response.
     * @return string|WP_Error
     */
    public function parse_response( $data ) {
        if ( isset( $data['error']['message'] ) ) {
            return new WP_Error( 'sag_openai_error', $data['error']['message'] );
        }
        $text = $data['choices'][0]['message']['content'] ?? '';
        if ( '' === $text ) {
            return new WP_Error( 'sag_empty_response', __( 'OpenAI returned an empty response.', 'internick-smart-alt-generator' ) );
        }
        return trim( $text );
    }

    /**
     * Perform the HTTP request to OpenAI. Uses wp_remote_post (WP standard,
     * never raw cURL). Reads the API key + model from options.
     *
     * @param string $image_url Public image URL.
     * @param string $prompt    Instruction text.
     * @return string|WP_Error
     */
    public function request( $image_url, $prompt ) {
        $api_key = get_option( 'sag_openai_api_key', '' );
        if ( empty( $api_key ) ) {
            return new WP_Error( 'sag_no_api_key', __( 'OpenAI API key is not configured.', 'internick-smart-alt-generator' ) );
        }
        $model = get_option( 'sag_model', 'gpt-4o-mini' );

        $response = wp_remote_post(
            self::ENDPOINT,
            array(
                'timeout' => 30,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( $this->build_request_body( $image_url, $prompt, $model ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        return $this->parse_response( is_array( $data ) ? $data : array() );
    }
}
