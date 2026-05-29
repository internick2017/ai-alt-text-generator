<?php
/**
 * Uninstall — remove all plugin options. Runs on plugin deletion.
 *
 * @package AI_Alt_Text_Generator
 */

// If uninstall is not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    die;
}

delete_option( 'aatg_openai_api_key' );
delete_option( 'aatg_model' );
delete_option( 'aatg_language' );
delete_option( 'aatg_auto_generate' );
