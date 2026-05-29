<?php
/**
 * Admin — registers menu pages and enqueues assets.
 *
 * @package AI_Alt_Text_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class AATG_Admin {

    /** Hooked to admin_menu. */
    public function register_menus() {
        add_options_page(
            __( 'AI Alt Text', 'ai-alt-text-generator' ),
            __( 'AI Alt Text', 'ai-alt-text-generator' ),
            'manage_options',
            'aatg-settings',
            array( $this, 'render_settings_page' )
        );

        add_media_page(
            __( 'Bulk Alt Text', 'ai-alt-text-generator' ),
            __( 'Bulk Alt Text', 'ai-alt-text-generator' ),
            'upload_files',
            'aatg-bulk',
            array( $this, 'render_bulk_page' )
        );
    }

    /** Hooked to admin_enqueue_scripts. Loads assets only on the screens that need them. */
    public function enqueue_assets( $hook ) {
        // Bulk page assets.
        if ( 'media_page_aatg-bulk' === $hook ) {
            wp_enqueue_style( 'aatg-admin', AATG_PLUGIN_URL . 'admin/css/aatg-admin.css', array(), AATG_VERSION );
            wp_enqueue_script( 'aatg-bulk', AATG_PLUGIN_URL . 'admin/js/aatg-bulk.js', array( 'wp-api-fetch' ), AATG_VERSION, true );
            return;
        }

        // Classic media button handler — load where the attachment panel appears.
        if ( in_array( $hook, array( 'post.php', 'post-new.php', 'upload.php' ), true ) ) {
            wp_enqueue_script( 'aatg-media', AATG_PLUGIN_URL . 'admin/js/aatg-media.js', array( 'wp-api-fetch' ), AATG_VERSION, true );
        }
    }

    public function render_settings_page() {
        require AATG_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    public function render_bulk_page() {
        require AATG_PLUGIN_DIR . 'admin/views/bulk-page.php';
    }
}
