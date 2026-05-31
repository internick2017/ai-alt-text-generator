<?php
/**
 * Bulk generator page. Lists attachments missing alt text and lets the user
 * generate for all of them. Processing happens client-side via the REST API.
 *
 * @package Smart_Alt_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

// Query images with empty or missing alt text.
$sag_query = new WP_Query(
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

$sag_ids = wp_list_pluck( $sag_query->posts, 'ID' );
?>
<div class="wrap sag-bulk">
    <h1><?php esc_html_e( 'Bulk Alt Text Generator', 'smart-alt-generator' ); ?></h1>

    <p>
        <?php
        printf(
            /* translators: %d is the number of images. */
            esc_html( _n( '%d image is missing alt text.', '%d images are missing alt text.', count( $sag_ids ), 'smart-alt-generator' ) ),
            count( $sag_ids )
        );
        ?>
    </p>

    <?php if ( ! empty( $sag_ids ) ) : ?>
        <button type="button" class="button button-primary" id="sag-start"
                data-ids="<?php echo esc_attr( wp_json_encode( $sag_ids ) ); ?>">
            <?php esc_html_e( 'Generate All', 'smart-alt-generator' ); ?>
        </button>

        <div class="sag-progress-wrap" style="display:none;">
            <div class="sag-progress-bar"><span id="sag-progress-fill"></span></div>
            <p id="sag-progress-text"></p>
        </div>
        <ul id="sag-log" class="sag-log"></ul>
    <?php else : ?>
        <p><?php esc_html_e( 'All your images already have alt text. ', 'smart-alt-generator' ); ?></p>
    <?php endif; ?>
</div>
