<?php
/**
 * Bulk generator page. Lists attachments missing alt text and lets the user
 * generate for all of them. Processing happens client-side via the REST API.
 *
 * @package AI_Alt_Text_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

// Query images with empty or missing alt text.
$aatg_query = new WP_Query(
    array(
        'post_type'      => 'attachment',
        'post_mime_type' => 'image',
        'post_status'    => 'inherit',
        'posts_per_page' => 100,
        'meta_query'     => array(
            'relation' => 'OR',
            array( 'key' => '_wp_attachment_image_alt', 'compare' => 'NOT EXISTS' ),
            array( 'key' => '_wp_attachment_image_alt', 'value' => '', 'compare' => '=' ),
        ),
    )
);

$aatg_ids = wp_list_pluck( $aatg_query->posts, 'ID' );
?>
<div class="wrap aatg-bulk">
    <h1><?php esc_html_e( 'Bulk Alt Text Generator', 'ai-alt-text-generator' ); ?></h1>

    <p>
        <?php
        printf(
            /* translators: %d is the number of images. */
            esc_html( _n( '%d image is missing alt text.', '%d images are missing alt text.', count( $aatg_ids ), 'ai-alt-text-generator' ) ),
            count( $aatg_ids )
        );
        ?>
    </p>

    <?php if ( ! empty( $aatg_ids ) ) : ?>
        <button type="button" class="button button-primary" id="aatg-start"
                data-ids="<?php echo esc_attr( wp_json_encode( $aatg_ids ) ); ?>">
            <?php esc_html_e( 'Generate All', 'ai-alt-text-generator' ); ?>
        </button>

        <div class="aatg-progress-wrap" style="display:none;">
            <div class="aatg-progress-bar"><span id="aatg-progress-fill"></span></div>
            <p id="aatg-progress-text"></p>
        </div>
        <ul id="aatg-log" class="aatg-log"></ul>
    <?php else : ?>
        <p><?php esc_html_e( 'All your images already have alt text. ', 'ai-alt-text-generator' ); ?></p>
    <?php endif; ?>
</div>
