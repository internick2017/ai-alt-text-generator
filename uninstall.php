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

delete_option( 'sag_openai_api_key' );
delete_option( 'sag_model' );
delete_option( 'sag_language' );
delete_option( 'sag_auto_generate' );
