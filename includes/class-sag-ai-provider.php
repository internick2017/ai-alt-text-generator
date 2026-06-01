<?php
/**
 * AI Provider router.
 *
 * Chooses between WordPress 7.0's native AI Connectors (AiClient) and a
 * direct OpenAI client. The backend is injectable so both paths are testable;
 * passing null auto-detects based on WordPress capabilities.
 *
 * @package Smart_Alt_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class SAG_AI_Provider {

    /** @var string 'wp_connector'|'openai' */
    private $backend;

    /**
     * @param string|null $backend Force a backend, or null to auto-detect.
     */
    public function __construct( $backend = null ) {
        $this->backend = $backend ?? self::detect_backend();
    }

    /**
     * Auto-detect: prefer WP 7.0 native AI Connectors if available.
     * NOT unit-tested (class_exists cannot be mocked here) — verified manually.
     *
     * @return string
     */
    public static function detect_backend() {
        return ( class_exists( 'WordPress\AiClient\AiClient' ) && \WordPress\AiClient\AiClient::isConfigured() )
            ? 'wp_connector'
            : 'openai';
    }

    /**
     * Build the instruction prompt. Pure — fully unit-testable.
     *
     * @param string $language 'auto' or a language name.
     * @return string
     */
    public function build_prompt( $language ) {
        $lang = ( 'auto' === $language )
            ? __( 'Use the same language as the website.', 'smart-alt-generator' )
            : sprintf(
                /* translators: %s is a language name. */
                __( 'Write the alt text in %s.', 'smart-alt-generator' ),
                $language
            );

        return sprintf(
            /* translators: %s is the language instruction. */
            __( 'Generate a concise, descriptive alt text for this image. Maximum 125 characters. %s Return only the alt text, no quotes.', 'smart-alt-generator' ),
            $lang
        );
    }

    /**
     * Generate alt text for an image URL using the selected backend.
     *
     * @param string $image_url Public image URL.
     * @param string $language  Language preference.
     * @return string|WP_Error
     */
    public function generate( $image_url, $language = 'auto' ) {
        $prompt = $this->build_prompt( $language );

        if ( 'wp_connector' === $this->backend ) {
            return $this->generate_via_connector( $image_url, $prompt );
        }
        return $this->generate_via_openai( $image_url, $prompt );
    }

    /** WP 6.x path. */
    private function generate_via_openai( $image_url, $prompt ) {
        $client = new SAG_OpenAI();
        return $client->request( $image_url, $prompt );
    }

    /** WP 7.0+ path — uses the native AI Connectors (AiClient). */
    private function generate_via_connector( $image_url, $prompt ) {
        try {
            $result = \WordPress\AiClient\AiClient::prompt()
                ->withText( $prompt )
                ->withFile( $image_url )
                ->generateTextResult();
            return trim( $result->toText() );
        } catch ( \Exception $e ) {
            return new WP_Error( 'sag_connector_error', $e->getMessage() );
        }
    }
}
