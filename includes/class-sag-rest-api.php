<?php
/**
 * REST API controller for alt text generation.
 *
 * Route: POST /wp-json/insag/v1/generate
 *
 * @package Smart_Alt_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class INSAG_REST_API {

    const REST_NAMESPACE = 'insag/v1';

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
                        'insag_openai_api_key' => array(
                            'type'              => 'string',
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'insag_model'          => array(
                            'type'              => 'string',
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                            'validate_callback' => array( $this, 'validate_model' ),
                        ),
                        'insag_language'       => array(
                            'type'              => 'string',
                            'required'          => false,
                            'sanitize_callback' => 'sanitize_text_field',
                        ),
                        'insag_auto_generate'  => array(
                            'type'              => 'boolean',
                            'required'          => false,
                            'sanitize_callback' => array( 'INSAG_Settings', 'sanitize_checkbox' ),
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
                // Admins only: this exercises the stored API key and can incur billable requests.
                'permission_callback' => array( $this, 'check_admin' ),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/audit/scan',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'handle_audit_scan' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'args'                => array(
                    'page' => array(
                        'type'              => 'integer',
                        'required'          => false,
                        'sanitize_callback' => 'absint',
                    ),
                ),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/bulk/scan',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( $this, 'handle_bulk_scan' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'args'                => array(
                    'page' => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
                ),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/audit/dismiss',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'handle_audit_dismiss' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'args'                => array(
                    'image_id'  => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
                    'dismissed' => array( 'type' => 'boolean', 'required' => true ),
                ),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/audit/set-alt',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'handle_audit_set_alt' ),
                'permission_callback' => array( $this, 'check_permission' ),
                'args'                => array(
                    'image_id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
                    'alt'      => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ),
                ),
            )
        );

        register_rest_route(
            self::REST_NAMESPACE,
            '/review/dismiss',
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array( $this, 'handle_review_dismiss' ),
                // Admins only: the notice itself is only shown to admins.
                'permission_callback' => array( $this, 'check_admin' ),
                'args'                => array(
                    'action' => array(
                        'type'              => 'string',
                        'required'          => true,
                        'enum'              => array( 'later', 'forever', 'reviewed' ),
                        'sanitize_callback' => 'sanitize_text_field',
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
        $generator = $this->generator ?? new INSAG_Generator();

        $image_id  = $request->get_param( 'image_id' );
        $image_url = $request->get_param( 'image_url' );

        if ( $image_id ) {
            // upload_files alone is not enough: verify the user may edit THIS attachment.
            if ( ! current_user_can( 'edit_post', (int) $image_id ) ) {
                return new WP_Error(
                    'insag_forbidden',
                    __( 'You are not allowed to edit this attachment.', 'internick-smart-alt-generator' ),
                    array( 'status' => 403 )
                );
            }
            $alt         = $generator->generate_for_image( (int) $image_id );
            $saved       = true;
            $resolved_id = (int) $image_id;
        } elseif ( $image_url ) {
            $alt         = $generator->generate_for_url( $image_url );
            $saved       = false;
            $resolved_id = null;
        } else {
            return new WP_Error(
                'insag_missing_param',
                __( 'Provide image_id or image_url.', 'internick-smart-alt-generator' ),
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

    /**
     * Scan one page of image attachments (100 per page). On the first page it
     * (re)builds the global duplicate signature set and resets the running
     * summary; both live in short-lived transients so duplicate detection has a
     * whole-library view and the score survives across paged requests.
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    public function handle_audit_scan( $request ) {
        global $wpdb;
        $per_page = 100;
        $page     = max( 1, (int) $request->get_param( 'page' ) );

        if ( 1 === $page ) {
            // One query for every image's alt value -> duplicate signature set.
            $alts = $wpdb->get_col(
                "SELECT pm.meta_value
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->postmeta} pm
                   ON pm.post_id = p.ID AND pm.meta_key = '_wp_attachment_image_alt'
                 WHERE p.post_type = 'attachment' AND p.post_mime_type LIKE 'image/%'"
            );
            set_transient( 'insag_audit_dupes', INSAG_Audit::find_duplicates( (array) $alts ), 15 * MINUTE_IN_SECONDS );
            set_transient( 'insag_audit_summary', array(
                'counts'  => array_fill_keys( INSAG_Audit::flags(), 0 ),
                'healthy' => 0,
                'total'   => 0,
            ), 15 * MINUTE_IN_SECONDS );
        }

        $dupes   = get_transient( 'insag_audit_dupes' );
        $running = get_transient( 'insag_audit_summary' );
        if ( ! is_array( $dupes ) ) {
            $dupes = array();
        }
        if ( ! is_array( $running ) ) {
            $running = array( 'counts' => array_fill_keys( INSAG_Audit::flags(), 0 ), 'healthy' => 0, 'total' => 0 );
        }

        $query = new WP_Query( array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ) );

        $records = array();
        foreach ( $query->posts as $id ) {
            $id       = (int) $id;
            $has_meta = metadata_exists( 'post', $id, '_wp_attachment_image_alt' );
            $file     = get_attached_file( $id );
            $records[] = array(
                'id'        => $id,
                'alt'       => $has_meta ? (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) : null,
                'filename'  => $file ? wp_basename( $file ) : '',
                'dismissed' => (bool) get_post_meta( $id, '_insag_audit_dismissed', true ),
            );
        }

        $page_result = INSAG_Audit::summarize( $records, $dupes );

        foreach ( $page_result['counts'] as $flag => $n ) {
            $running['counts'][ $flag ] = ( $running['counts'][ $flag ] ?? 0 ) + $n;
        }
        $running['healthy'] += $page_result['healthy'];
        $running['total']   += count( $records );
        set_transient( 'insag_audit_summary', $running, 15 * MINUTE_IN_SECONDS );

        $items = array();
        foreach ( $page_result['items'] as $item ) {
            $item['thumb'] = wp_get_attachment_image_url( $item['id'], 'thumbnail' );
            $items[]       = $item;
        }

        return rest_ensure_response( array(
            'page'        => $page,
            'total_pages' => (int) $query->max_num_pages,
            'total'       => (int) $running['total'],
            'counts'      => $running['counts'],
            'score'       => INSAG_Audit::score( $running['healthy'], $running['total'] ),
            'items'       => $items,
        ) );
    }

    /**
     * One page (100 IDs) of images missing alt text, plus the library-wide total,
     * so the bulk UI can batch through arbitrarily large libraries.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function handle_bulk_scan( $request ) {
        $page  = max( 1, (int) $request->get_param( 'page' ) );
        $query = new WP_Query( INSAG_Media::missing_alt_query_args( $page ) );

        return rest_ensure_response( array(
            'page'        => $page,
            'total_pages' => (int) $query->max_num_pages,
            'total'       => (int) $query->found_posts,
            'ids'         => array_map( 'intval', $query->posts ),
        ) );
    }

    /**
     * Toggle the "dismissed" (reviewed / ignore) flag on an attachment.
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    public function handle_audit_dismiss( $request ) {
        $id = (int) $request->get_param( 'image_id' );
        if ( ! current_user_can( 'edit_post', $id ) ) {
            return new WP_Error(
                'insag_forbidden',
                __( 'You are not allowed to edit this attachment.', 'internick-smart-alt-generator' ),
                array( 'status' => 403 )
            );
        }
        $dismissed = (bool) $request->get_param( 'dismissed' );
        if ( $dismissed ) {
            update_post_meta( $id, '_insag_audit_dismissed', 1 );
        } else {
            delete_post_meta( $id, '_insag_audit_dismissed' );
        }
        delete_transient( 'insag_audit_summary' );
        return rest_ensure_response( array( 'image_id' => $id, 'dismissed' => $dismissed ) );
    }

    /**
     * Save manually edited alt text for an attachment.
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    public function handle_audit_set_alt( $request ) {
        $id = (int) $request->get_param( 'image_id' );
        if ( ! current_user_can( 'edit_post', $id ) ) {
            return new WP_Error(
                'insag_forbidden',
                __( 'You are not allowed to edit this attachment.', 'internick-smart-alt-generator' ),
                array( 'status' => 403 )
            );
        }
        $alt = sanitize_text_field( (string) $request->get_param( 'alt' ) );
        update_post_meta( $id, '_wp_attachment_image_alt', $alt );
        delete_transient( 'insag_audit_summary' );
        return rest_ensure_response( array( 'image_id' => $id, 'alt' => $alt ) );
    }

    /**
     * Record the user's response to the review notice.
     *
     * @param WP_REST_Request $request
     * @return array|WP_Error
     */
    public function handle_review_dismiss( $request ) {
        $action = (string) $request->get_param( 'action' );
        if ( ! INSAG_Review_Notice::apply_action( $action, time() ) ) {
            return new WP_Error(
                'insag_invalid_action',
                __( 'Unknown review action.', 'internick-smart-alt-generator' ),
                array( 'status' => 400 )
            );
        }
        return rest_ensure_response( array( 'state_action' => $action, 'ok' => true ) );
    }

    /** Only admins may read/write settings. */
    public function check_admin() {
        return current_user_can( 'manage_options' );
    }

    /** Validates model value — returns true or WP_Error. */
    public function validate_model( $value ) {
        $allowed = INSAG_Settings::allowed_models();
        if ( in_array( $value, $allowed, true ) ) {
            return true;
        }
        return new WP_Error(
            'insag_invalid_model',
            __( 'Invalid model.', 'internick-smart-alt-generator' ),
            array( 'status' => 400 )
        );
    }

    /** Returns all plugin options as JSON. */
    public function get_settings( $request ) {
        return rest_ensure_response( array(
            'insag_openai_api_key' => get_option( 'insag_openai_api_key', '' ),
            'insag_model'          => get_option( 'insag_model', 'gpt-4o-mini' ),
            'insag_language'       => get_option( 'insag_language', 'auto' ),
            'insag_auto_generate'  => (bool) get_option( 'insag_auto_generate', false ),
        ) );
    }

    /** Saves whichever option keys were provided in the request. */
    public function save_settings( $request ) {
        $fields = array( 'insag_openai_api_key', 'insag_model', 'insag_language', 'insag_auto_generate' );
        foreach ( $fields as $key ) {
            $value = $request->get_param( $key );
            if ( null !== $value ) {
                update_option( $key, $value );
            }
        }
        return rest_ensure_response( array( 'saved' => true ) );
    }

    /**
     * Tests the OpenAI API key with a minimal request (max_tokens: 1).
     * Does not save anything. Uses the currently stored API key.
     *
     * @return array|WP_Error
     */
    public function test_connection( $request ) {
        $api_key = get_option( 'insag_openai_api_key', '' );
        if ( empty( $api_key ) ) {
            return new WP_Error(
                'insag_no_api_key',
                __( 'OpenAI API key is not configured.', 'internick-smart-alt-generator' ),
                array( 'status' => 400 )
            );
        }

        $model    = get_option( 'insag_model', 'gpt-4o-mini' );
        $response = wp_remote_post(
            INSAG_OpenAI::ENDPOINT,
            array(
                'timeout' => 15,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ),
                'body'    => wp_json_encode( array(
                    'model'      => $model,
                    'max_tokens' => 1,
                    'messages'   => array(
                        array( 'role' => 'user', 'content' => 'Hi' ),
                    ),
                ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'insag_test_failed',
                $response->get_error_message(),
                array( 'status' => 502 )
            );
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset( $data['error']['message'] ) ) {
            return new WP_Error(
                'insag_test_failed',
                $data['error']['message'],
                array( 'status' => 400 )
            );
        }

        return rest_ensure_response( array( 'ok' => true, 'model' => $model ) );
    }
}
