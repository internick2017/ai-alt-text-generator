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
}
