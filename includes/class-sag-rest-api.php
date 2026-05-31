<?php
/**
 * REST API controller for alt text generation.
 *
 * Route: POST /wp-json/smart-alt/v1/generate
 *
 * @package Smart_Alt_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class SAG_REST_API {

    const REST_NAMESPACE = 'smart-alt/v1';

    /** @var object Generator with generate_for_image()/generate_for_url(). */
    private $generator;

    public function __construct( $generator = null ) {
        $this->generator = $generator; // Lazily created in handle_generate if null.
    }

    /** Register the REST route. Hooked to rest_api_init. */
    public function register_routes() {
        register_rest_route(
            self::REST_NAMESPACE,
            '/generate',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'handle_generate' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'args'                => array(
                    'image_id'  => array(
                        'type'              => 'integer',
                        'required'          => false,
                        'sanitize_callback' => 'absint',
                    ),
                    'image_url' => array(
                        'type'              => 'string',
                        'required'          => false,
                        'sanitize_callback' => 'esc_url_raw',
                    ),
                ),
            )
        );
    }

    /** Only users who can upload files may generate. */
    public function check_permission() {
        return current_user_can( 'upload_files' );
    }

    /**
     * Handle the request. With image_id -> save; with image_url -> return only.
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    public function handle_generate( $request ) {
        $generator = $this->generator ?? new SAG_Generator();

        $image_id  = $request->get_param( 'image_id' );
        $image_url = $request->get_param( 'image_url' );

        if ( $image_id ) {
            $alt         = $generator->generate_for_image( (int) $image_id );
            $saved       = true;
            $resolved_id = (int) $image_id;
        } elseif ( $image_url ) {
            $alt         = $generator->generate_for_url( $image_url );
            $saved       = false;
            $resolved_id = null;
        } else {
            return new WP_Error(
                'sag_missing_param',
                __( 'Provide image_id or image_url.', 'smart-alt-generator' ),
                array( 'status' => 400 )
            );
        }

        if ( is_wp_error( $alt ) ) {
            return $alt;
        }

        return rest_ensure_response(
            array(
                'alt_text' => $alt,
                'image_id' => $resolved_id,
                'saved'    => $saved,
            )
        );
    }
}
