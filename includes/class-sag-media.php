<?php
/**
 * Media hooks — auto-generate on upload + classic Media Library button.
 *
 * @package Smart_Alt_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class INSAG_Media {

    /** @var object Generator with generate_for_image(). */
    private $generator;

    public function __construct( $generator = null ) {
        $this->generator = $generator;
    }

    /**
     * Hooked to add_attachment. Generates alt text if auto-generate is on
     * and the attachment is an image.
     *
     * @param int $attachment_id
     */
    public function maybe_auto_generate( $attachment_id ) {
        if ( ! get_option( 'insag_auto_generate', false ) ) {
            return;
        }
        if ( ! wp_attachment_is_image( $attachment_id ) ) {
            return;
        }
        $generator = $this->generator ?? new INSAG_Generator();
        $generator->generate_for_image( $attachment_id );
    }

    /**
     * Hooked to attachment_fields_to_edit. Adds a "Generate Alt Text" button
     * to the classic attachment edit panel.
     *
     * @param array   $form_fields
     * @param WP_Post $post
     * @return array
     */
    public function add_generate_button( $form_fields, $post ) {
        $button = sprintf(
            '<button type="button" class="button insag-generate-btn" data-image-id="%d">%s</button>',
            esc_attr( $post->ID ),
            esc_html__( 'Generate Alt Text', 'internick-smart-alt-generator' )
        );
        $form_fields['insag_generate'] = array(
            'label' => __( 'AI Alt Text', 'internick-smart-alt-generator' ),
            'input' => 'html',
            'html'  => $button,
        );
        return $form_fields;
    }

    /**
     * WP_Query args for one page (100 items) of images missing alt text.
     * Shared by the bulk admin page and the /bulk/scan REST endpoint.
     *
     * @param int $page 1-based page number.
     * @return array
     */
    public static function missing_alt_query_args( $page ) {
        return array(
            'post_type'      => 'attachment',
            'post_mime_type' => 'image',
            'post_status'    => 'inherit',
            'posts_per_page' => 100,
            'paged'          => max( 1, (int) $page ),
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'fields'         => 'ids',
            'no_found_rows'  => false,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Intentional and unavoidable: finding attachments missing alt text requires a meta_query. Bounded to 100 results per page.
            'meta_query'     => array(
                'relation' => 'OR',
                array( 'key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS' ),
                array( 'key' => '_wp_attachment_image_alt', 'value' => '', 'compare' => '=' ),
            ),
        );
    }
}
