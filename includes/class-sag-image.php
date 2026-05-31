<?php
/**
 * Image helper — converts images to base64 data URIs.
 *
 * Sending a data URI (instead of a public URL) means the AI provider never has
 * to fetch the image from our server. This works on localhost, behind auth, or
 * on firewalled sites where the image URL is not publicly reachable.
 *
 * @package Smart_Alt_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class SAG_Image {

    /** Max bytes to encode (OpenAI vision limit is ~20MB; stay safe at 18MB). */
    const MAX_BYTES = 18874368; // 18 * 1024 * 1024

    /** Map of file extension => MIME type. */
    private function mime_map() {
        return array(
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'gif'  => 'image/gif',
        );
    }

    /**
     * Resolve a MIME type from a file path's extension. Pure — unit-testable.
     *
     * @param string $path File path or name.
     * @return string MIME type, or '' if unsupported.
     */
    public function mime_from_path( $path ) {
        $ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
        $map = $this->mime_map();
        return $map[ $ext ] ?? '';
    }

    /**
     * Build a data URI from raw bytes + MIME type. Pure — unit-testable.
     *
     * @param string $bytes Raw file contents.
     * @param string $mime  MIME type.
     * @return string
     */
    public function build_data_uri( $bytes, $mime ) {
        return 'data:' . $mime . ';base64,' . base64_encode( $bytes );
    }

    /**
     * Read a LOCAL file and return a base64 data URI.
     * Preferred path — no HTTP, works behind auth.
     *
     * @param string $path Absolute file path.
     * @return string|WP_Error
     */
    public function path_to_data_uri( $path ) {
        if ( ! is_string( $path ) || ! is_readable( $path ) ) {
            return new WP_Error( 'sag_image_unreadable', __( 'Image file could not be read.', 'smart-alt-generator' ) );
        }

        $mime = $this->mime_from_path( $path );
        if ( '' === $mime ) {
            return new WP_Error( 'sag_image_type', __( 'Unsupported image type.', 'smart-alt-generator' ) );
        }

        $size = filesize( $path );
        if ( false !== $size && $size > self::MAX_BYTES ) {
            return new WP_Error( 'sag_image_too_large', __( 'Image is too large to process.', 'smart-alt-generator' ) );
        }

        $bytes = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        if ( false === $bytes ) {
            return new WP_Error( 'sag_image_unreadable', __( 'Image file could not be read.', 'smart-alt-generator' ) );
        }

        return $this->build_data_uri( $bytes, $mime );
    }

    /**
     * Download a remote URL and return a base64 data URI.
     * Fallback for when only a URL is available (REST image_url mode).
     *
     * @param string $url Public image URL.
     * @return string|WP_Error
     */
    public function url_to_data_uri( $url ) {
        $response = wp_remote_get( $url, array( 'timeout' => 30 ) );
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== (int) $code ) {
            return new WP_Error( 'sag_image_fetch', __( 'Could not download the image.', 'smart-alt-generator' ) );
        }

        $bytes = wp_remote_retrieve_body( $response );
        if ( empty( $bytes ) ) {
            return new WP_Error( 'sag_image_fetch', __( 'Downloaded image was empty.', 'smart-alt-generator' ) );
        }

        $mime = $this->mime_from_path( $url );
        if ( '' === $mime ) {
            // Fall back to the Content-Type header if the URL has no clear extension.
            $mime = wp_remote_retrieve_header( $response, 'content-type' );
            $mime = $mime ? $mime : 'image/jpeg';
        }

        return $this->build_data_uri( $bytes, $mime );
    }
}
