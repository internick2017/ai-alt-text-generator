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

    /** Register all REST routes. Hooked to rest_api_init. */
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

        register_rest_route(
            self::REST_NAMESPACE,
            '/settings',
            array(
                array(
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => array( $this, 'get_settings' ),
                    'permission_callback' => array( $this, 'check_admin' ),
                ),
                array(
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => array( $this, 'save_settings' ),
                    'permission_callback' => array( $this, 'check_admin' ),
                    'args'                => array(
                        'sag_openai_api_key' => array(
                            'type'              => 'string',
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'sag_model'          => array(
                            'type'              => 'string',
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                            'validate_callback' => array( $this, 'validate_model' ),
                        ),
                        'sag_language'       => array(
                            'type'              => 'string',
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'sag_auto_generate'  => array(
                            'type'              => 'boolean',
                            'required'          => false,
                            'sanitize_callback' => array( 'SAG_Settings', 'sanitize_checkbox' ),
                        ),
                    ),
                ),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/test',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'test_connection' ),
                'permission_callback' => array( $this, 'check_permission' ),
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

    /** Only admins may read/write settings. */
    public function check_admin() {
        return current_user_can( 'manage_options' );
    }

    /** Validates model value — returns true or WP_Error. */
    public function validate_model( $value ) {
        $allowed = SAG_Settings::allowed_models();
        if ( in_array( $value, $allowed, true ) ) {
            return true;
        }
        return new WP_Error(
            'sag_invalid_model',
            __( 'Invalid model.', 'smart-alt-generator' ),
            array( 'status' => 400 )
        );
    }

    /** Returns all plugin options as JSON. */
    public function get_settings( $request ) {
        return rest_ensure_response( array(
            'sag_openai_api_key' => get_option( 'sag_openai_api_key', '' ),
            'sag_model'          => get_option( 'sag_model', 'gpt-4o-mini' ),
            'sag_language'       => get_option( 'sag_language', 'auto' ),
            'sag_auto_generate'  => (bool) get_option( 'sag_auto_generate', false ),
        ) );
    }

    /** Saves whichever option keys were provided in the request. */
    public function save_settings( $request ) {
        $fields = array( 'sag_openai_api_key', 'sag_model', 'sag_language', 'sag_auto_generate' );
        foreach ( $fields as $key ) {
            $value = $request->get_param( $key );
            if ( null !== $value ) {
                update_option( $key, $value );
            }
        }
        return rest_ensure_response( array( 'saved' => true ) );
    }

    /**
     * Tests the OpenAI API connection. Stub — implemented in Phase 2 Task 2.
     *
     * @return WP_Error
     */
    public function test_connection( $request ) {
        return new WP_Error(
            'sag_not_implemented',
            __( 'Test endpoint not yet implemented.', 'smart-alt-generator' ),
            array( 'status' => 501 )
        );
    }
}
