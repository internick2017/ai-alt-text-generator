<?php
/**
 * Generator — orchestrates: resolve image -> data URI -> call provider -> save.
 *
 * Images are converted to base64 data URIs so the AI provider never needs to
 * fetch them from our server (works on localhost, behind auth, on firewalls).
 *
 * @package Smart_Alt_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class SAG_Generator {

    /** @var object Anything with a generate( $image, $language ) method. */
    private $provider;

    /** @var object SAG_Image (or compatible) for data-URI conversion. */
    private $image;

    /**
     * @param object|null $provider Inject a provider; defaults to SAG_AI_Provider.
     * @param object|null $image    Inject an image helper; defaults to SAG_Image.
     */
    public function __construct( $provider = null, $image = null ) {
        $this->provider = $provider ?? new SAG_AI_Provider();
        $this->image    = $image ?? new SAG_Image();
    }

    /**
     * Generate and SAVE alt text for an attachment ID.
     * Reads the LOCAL file (fast, no HTTP) and sends it as a data URI.
     *
     * @param int $image_id Attachment post ID.
     * @return string|WP_Error The generated alt text, or error.
     */
    public function generate_for_image( $image_id ) {
        $path = get_attached_file( $image_id );
        if ( ! $path ) {
            return new WP_Error( 'sag_invalid_image', __( 'Image not found.', 'smart-alt-generator' ) );
        }

        $data_uri = $this->image->path_to_data_uri( $path );
        if ( is_wp_error( $data_uri ) ) {
            return $data_uri;
        }

        $alt = $this->run_provider( $data_uri );
        if ( is_wp_error( $alt ) ) {
            return $alt;
        }

        update_post_meta( $image_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
        return $alt;
    }

    /**
     * Generate alt text for a raw URL WITHOUT saving (no post ID).
     * Downloads the image and sends it as a data URI.
     *
     * @param string $image_url Public image URL.
     * @return string|WP_Error
     */
    public function generate_for_url( $image_url ) {
        $data_uri = $this->image->url_to_data_uri( $image_url );
        if ( is_wp_error( $data_uri ) ) {
            return $data_uri;
        }
        return $this->run_provider( $data_uri );
    }

    /**
     * Call the AI provider with a ready-to-send image (data URI or URL).
     *
     * @param string $image Data URI or URL.
     * @return string|WP_Error
     */
    private function run_provider( $image ) {
        $language = get_option( 'sag_language', 'auto' );
        return $this->provider->generate( $image, $language );
    }
}
