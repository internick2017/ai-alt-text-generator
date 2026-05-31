<?php
/**
 * Admin — registers menu pages and enqueues assets.
 *
 * @package Smart_Alt_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class SAG_Admin {

    /** Hooked to admin_menu. */
    public function register_menus() {
        add_options_page(
            __( 'AI Alt Text', 'smart-alt-generator' ),
            __( 'AI Alt Text', 'smart-alt-generator' ),
            'manage_options',
            'sag-settings',
            array( $this, 'render_settings_page' )
        );

        add_media_page(
            __( 'Bulk Alt Text', 'smart-alt-generator' ),
            __( 'Bulk Alt Text', 'smart-alt-generator' ),
            'upload_files',
            'sag-bulk',
            array( $this, 'render_bulk_page' )
        );
    }

    /** Hooked to admin_enqueue_scripts. Loads assets only on the screens that need them. */
    public function enqueue_assets( $hook ) {
        // Bulk page assets.
        if ( 'media_page_sag-bulk' === $hook ) {
            wp_enqueue_style( 'sag-admin', SAG_PLUGIN_URL . 'admin/css/sag-admin.css', array(), SAG_VERSION );
            wp_enqueue_script( 'sag-bulk', SAG_PLUGIN_URL . 'admin/js/sag-bulk.js', array( 'wp-api-fetch' ), SAG_VERSION, true );
            return;
        }

        // Classic media button handler — load where the attachment panel appears.
        if ( in_array( $hook, array( 'post.php', 'post-new.php', 'upload.php' ), true ) ) {
            wp_enqueue_script( 'sag-media', SAG_PLUGIN_URL . 'admin/js/sag-media.js', array( 'wp-api-fetch' ), SAG_VERSION, true );
        }
    }

    /**
     * Hooked to enqueue_block_editor_assets. Loads the compiled React bundle
     * that adds the "Generate with AI" button to the image block.
     */
    public function enqueue_block_editor() {
        $asset_file = SAG_PLUGIN_DIR . 'build/index.asset.php';
        if ( ! file_exists( $asset_file ) ) {
            return; // Build not present (dev clone without npm build).
        }

        $asset = require $asset_file;

        // Filter out `react-jsx-runtime` on WordPress < 6.6 — the handle didn't
        // exist before 6.6, so WordPress would silently refuse to enqueue our
        // script if it's listed as a dependency and isn't registered yet.
        // `wp-element` (always present) already covers the JSX runtime for us.
        $deps = array_values(
            array_filter(
                $asset['dependencies'],
                static function ( $handle ) {
                    return $handle !== 'react-jsx-runtime' || wp_script_is( 'react-jsx-runtime', 'registered' );
                }
            )
        );

        // Ensure wp-blocks is a dependency so our editor.BlockEdit filter is
        // registered before the editor finishes mounting blocks.
        foreach ( array( 'wp-blocks', 'wp-edit-post' ) as $required ) {
            if ( ! in_array( $required, $deps, true ) && wp_script_is( $required, 'registered' ) ) {
                $deps[] = $required;
            }
        }

        wp_enqueue_script(
            'sag-block-editor',
            SAG_PLUGIN_URL . 'build/index.js',
            $deps,
            $asset['version'] . '-' . SAG_VERSION,
            false // Load in <head> so the filter runs before the editor renders blocks.
        );
    }

    public function render_settings_page() {
        require SAG_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    public function render_bulk_page() {
        require SAG_PLUGIN_DIR . 'admin/views/bulk-page.php';
    }
}
