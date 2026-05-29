<?php
/**
 * Generator — orchestrates: resolve image URL -> call provider -> save alt text.
 *
 * @package AI_Alt_Text_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class AATG_Generator {

    /** @var object Anything with a generate( $url, $language ) method. */
    private $provider;

    /**
     * @param object|null $provider Inject a provider; defaults to AATG_AI_Provider.
     */
    public function __construct( $provider = null ) {
        $this->provider = $provider ?? new AATG_AI_Provider();
    }

    /**
     * Generate and SAVE alt text for an attachment ID.
     *
     * @param int $image_id Attachment post ID.
     * @return string|WP_Error The generated alt text, or error.
     */
    public function generate_for_image( $image_id ) {
        $image_url = wp_get_attachment_url( $image_id );
        if ( ! $image_url ) {
            return new WP_Error( 'aatg_invalid_image', __( 'Image not found.', 'ai-alt-text-generator' ) );
        }

        $alt = $this->generate_for_url( $image_url );
        if ( is_wp_error( $alt ) ) {
            return $alt;
        }

        update_post_meta( $image_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
        return $alt;
    }

    /**
     * Generate alt text for a raw URL WITHOUT saving (no post ID).
     *
     * @param string $image_url Public image URL.
     * @return string|WP_Error
     */
    public function generate_for_url( $image_url ) {
        $language = get_option( 'aatg_language', 'auto' );
        return $this->provider->generate( $image_url, $language );
    }
}
