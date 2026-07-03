<?php
/**
 * Uninstall — remove all plugin options. Runs on plugin deletion.
 *
 * @package Smart_Alt_Generator
 */

// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    die;
}

delete_option( 'insag_openai_api_key' );
delete_option( 'insag_model' );
delete_option( 'insag_language' );
delete_option( 'insag_auto_generate' );
delete_option( 'insag_generation_count' );
delete_option( 'insag_review_state' );
delete_option( 'insag_review_snooze_until' );
