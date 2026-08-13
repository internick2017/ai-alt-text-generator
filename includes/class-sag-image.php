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

class INSAG_Image {

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
     * Formats the vision API will not accept, but that we can still handle by
     * converting them to JPEG first. WordPress accepts AVIF uploads since 6.5,
     * and WP 7.1 can produce them client-side, so they do reach the library.
     */
    private function convertible_map() {
        return array(
            'avif' => 'image/avif',
        );
    }

    /**
     * Resolve a MIME type from a file path's extension. Pure — unit-testable.
     *
     * Only formats the vision API accepts directly are returned here; see
     * is_convertible_path() for the ones that need a conversion pass.
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
     * Whether this path is an image we can use only after converting it.
     * Pure — unit-testable.
     *
     * @param string $path File path or name.
     * @return bool
     */
    public function is_convertible_path( $path ) {
        $ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
        return isset( $this->convertible_map()[ $ext ] );
    }

    /**
     * Convert an image the vision API rejects (currently AVIF) into JPEG bytes,
     * using whatever image editor the server provides.
     *
     * Servers without an AVIF-capable GD/Imagick build return an error here, so
     * the caller can report something clearer than an opaque API rejection.
     *
     * @param string $path Absolute path to the source image.
     * @return string|WP_Error Raw JPEG bytes, or an error.
     */
    public function convert_to_jpeg_bytes( $path ) {
        $convert_error = new WP_Error(
            'insag_image_convert',
            __( 'This image format could not be converted for AI processing. Try uploading a JPEG, PNG or WebP.', 'internick-smart-alt-generator' )
        );

        if ( ! function_exists( 'wp_get_image_editor' ) ) {
            return $convert_error;
        }

        $editor = wp_get_image_editor( $path );
        if ( $editor instanceof WP_Error ) {
            return $convert_error;
        }

        $tmp = tempnam( sys_get_temp_dir(), 'insag' );
        if ( false === $tmp ) {
            return $convert_error;
        }

        $saved = $editor->save( $tmp, 'image/jpeg' );
        if ( $saved instanceof WP_Error ) {
            wp_delete_file( $tmp );
            return $convert_error;
        }

        // Some editors append their own extension and return the real path.
        $out   = ( is_array( $saved ) && ! empty( $saved['path'] ) ) ? $saved['path'] : $tmp;
        $bytes = file_get_contents( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions

        wp_delete_file( $tmp );
        if ( $out !== $tmp ) {
            wp_delete_file( $out );
        }

        if ( false === $bytes || '' === $bytes ) {
            return $convert_error;
        }

        return $bytes;
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
            return new WP_Error( 'insag_image_unreadable', __( 'Image file could not be read.', 'internick-smart-alt-generator' ) );
        }

        $mime        = $this->mime_from_path( $path );
        $convertible = ( '' === $mime ) && $this->is_convertible_path( $path );

        if ( '' === $mime && ! $convertible ) {
            return new WP_Error( 'insag_image_type', __( 'Unsupported image type.', 'internick-smart-alt-generator' ) );
        }

        $size = filesize( $path );
        if ( false !== $size && $size > self::MAX_BYTES ) {
            return new WP_Error( 'insag_image_too_large', __( 'Image is too large to process.', 'internick-smart-alt-generator' ) );
        }

        if ( $convertible ) {
            $converted = $this->convert_to_jpeg_bytes( $path );
            if ( $converted instanceof WP_Error ) {
                return $converted;
            }
            return $this->build_data_uri( $converted, 'image/jpeg' );
        }

        $bytes = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
        if ( false === $bytes ) {
            return new WP_Error( 'insag_image_unreadable', __( 'Image file could not be read.', 'internick-smart-alt-generator' ) );
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
            return new WP_Error( 'insag_image_fetch', __( 'Could not download the image.', 'internick-smart-alt-generator' ) );
        }

        $bytes = wp_remote_retrieve_body( $response );
        if ( empty( $bytes ) ) {
            return new WP_Error( 'insag_image_fetch', __( 'Downloaded image was empty.', 'internick-smart-alt-generator' ) );
        }

        $mime = $this->mime_from_path( $url );
        if ( '' === $mime ) {
            // Fall back to the Content-Type header if the URL has no clear extension.
            $mime = wp_remote_retrieve_header( $response, 'content-type' );
            $mime = $mime ? $mime : 'image/jpeg';
        }

        // The header can hand us something the vision API rejects (AVIF, for
        // one). Convert rather than sending it and getting an opaque failure.
        if ( ! in_array( $mime, array_values( $this->mime_map() ), true ) ) {
            $converted = $this->convert_downloaded_bytes( $bytes, $mime );
            if ( $converted instanceof WP_Error ) {
                return $converted;
            }
            return $this->build_data_uri( $converted, 'image/jpeg' );
        }

        return $this->build_data_uri( $bytes, $mime );
    }

    /**
     * Convert already-downloaded bytes to JPEG by staging them on disk first,
     * since image editors work on files rather than raw strings.
     *
     * @param string $bytes Raw image bytes.
     * @param string $mime  Reported MIME type, used to pick the temp extension.
     * @return string|WP_Error Raw JPEG bytes, or an error.
     */
    private function convert_downloaded_bytes( $bytes, $mime ) {
        $convert_error = new WP_Error(
            'insag_image_convert',
            __( 'This image format could not be converted for AI processing. Try uploading a JPEG, PNG or WebP.', 'internick-smart-alt-generator' )
        );

        $ext = array_search( $mime, $this->convertible_map(), true );
        if ( false === $ext ) {
            $ext = 'img';
        }

        $staged = tempnam( sys_get_temp_dir(), 'insag' );
        if ( false === $staged ) {
            return $convert_error;
        }

        // The editor picks its reader from the extension, so keep it.
        $source = $staged . '.' . $ext;
        if ( false === file_put_contents( $source, $bytes ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
            wp_delete_file( $staged );
            return $convert_error;
        }

        $converted = $this->convert_to_jpeg_bytes( $source );

        wp_delete_file( $staged );
        wp_delete_file( $source );

        return $converted;
    }
}
