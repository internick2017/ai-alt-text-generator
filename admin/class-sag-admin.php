<?php
/**
 * Admin — registers menu pages and enqueues assets.
 *
 * @package Smart_Alt_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class INSAG_Admin {

    /** Hooked to admin_menu. */
    public function register_menus() {
        add_options_page(
            __( 'AI Alt Text', 'internick-smart-alt-generator' ),
            __( 'AI Alt Text', 'internick-smart-alt-generator' ),
            'manage_options',
            'insag-settings',
            array( $this, 'render_settings_page' )
        );

        add_media_page(
            __( 'Bulk Alt Text', 'internick-smart-alt-generator' ),
            __( 'Bulk Alt Text', 'internick-smart-alt-generator' ),
            'upload_files',
            'insag-bulk',
            array( $this, 'render_bulk_page' )
        );

        add_media_page(
            __( 'Alt Text Audit', 'internick-smart-alt-generator' ),
            __( 'Alt Text Audit', 'internick-smart-alt-generator' ),
            'upload_files',
            'insag-audit',
            array( $this, 'render_audit_page' )
        );
    }

    /** Hooked to admin_enqueue_scripts. Loads the review-notice script and the React bundles for admin pages. */
    public function enqueue_assets( $hook ) {
        // Review notice buttons — only on plugin screens, only when the notice is due.
        if ( in_array( $hook, INSAG_Review_Notice::ALLOWED_SCREENS, true )
            && current_user_can( 'manage_options' )
            && INSAG_Review_Notice::is_due() ) {
            wp_enqueue_script(
                'insag-review-notice',
                INSAG_PLUGIN_URL . 'admin/js/sag-review-notice.js',
                array(),
                INSAG_VERSION,
                true
            );
            wp_localize_script( 'insag-review-notice', 'insagReviewData', array(
                'restBase'  => rest_url( 'insag/v1' ),
                'nonce'     => wp_create_nonce( 'wp_rest' ),
                'reviewUrl' => INSAG_Review_Notice::REVIEW_URL,
            ) );
        }

        // Settings page — React bundle.
        if ( 'settings_page_insag-settings' === $hook ) {
            $asset_file = INSAG_PLUGIN_DIR . 'build/admin-settings.asset.php';
            if ( ! file_exists( $asset_file ) ) {
                return;
            }
            $asset = require $asset_file;
            wp_enqueue_script(
                'insag-admin-settings',
                INSAG_PLUGIN_URL . 'build/admin-settings.js',
                $asset['dependencies'],
                $asset['version'],
                true
            );
            wp_localize_script( 'insag-admin-settings', 'insagSettingsData', array(
                'hasConnector' => 'wp_connector' === INSAG_AI_Provider::detect_backend(),
                'nonce'        => wp_create_nonce( 'wp_rest' ),
                'restBase'     => rest_url( 'insag/v1' ),
            ) );
            wp_set_script_translations(
                'insag-admin-settings',
                'internick-smart-alt-generator',
                INSAG_PLUGIN_DIR . 'languages'
            );
            return;
        }

        // Bulk page — React bundle.
        if ( 'media_page_insag-bulk' === $hook ) {
            $bulk_query = new WP_Query( INSAG_Media::missing_alt_query_args( 1 ) );

            $asset_file = INSAG_PLUGIN_DIR . 'build/admin-bulk.asset.php';
            if ( ! file_exists( $asset_file ) ) {
                return;
            }
            $asset = require $asset_file;
            wp_enqueue_script(
                'insag-admin-bulk',
                INSAG_PLUGIN_URL . 'build/admin-bulk.js',
                $asset['dependencies'],
                $asset['version'],
                true
            );
            wp_localize_script( 'insag-admin-bulk', 'insagBulkData', array(
                'total'    => (int) $bulk_query->found_posts,
                'nonce'    => wp_create_nonce( 'wp_rest' ),
                'restBase' => rest_url( 'insag/v1' ),
            ) );
            wp_set_script_translations(
                'insag-admin-bulk',
                'internick-smart-alt-generator',
                INSAG_PLUGIN_DIR . 'languages'
            );
            return;
        }

        // Audit page — React bundle.
        if ( 'media_page_insag-audit' === $hook ) {
            $asset_file = INSAG_PLUGIN_DIR . 'build/admin-audit.asset.php';
            if ( ! file_exists( $asset_file ) ) {
                return;
            }
            $asset = require $asset_file;
            wp_enqueue_script(
                'insag-admin-audit',
                INSAG_PLUGIN_URL . 'build/admin-audit.js',
                $asset['dependencies'],
                $asset['version'],
                true
            );
            wp_localize_script( 'insag-admin-audit', 'insagAuditData', array(
                'nonce'    => wp_create_nonce( 'wp_rest' ),
                'restBase' => rest_url( 'insag/v1' ),
            ) );
            wp_set_script_translations(
                'insag-admin-audit',
                'internick-smart-alt-generator',
                INSAG_PLUGIN_DIR . 'languages'
            );
            return;
        }

        // Classic media library button — unchanged.
        if ( in_array( $hook, array( 'post.php', 'post-new.php', 'upload.php' ), true ) ) {
            wp_enqueue_script( 'insag-media', INSAG_PLUGIN_URL . 'admin/js/sag-media.js', array( 'wp-api-fetch' ), INSAG_VERSION, true );
        }
    }

    /**
     * Hooked to enqueue_block_editor_assets. Loads the compiled React bundle
     * that adds the "Generate with AI" button to the image block.
     */
    public function enqueue_block_editor() {
        $asset_file = INSAG_PLUGIN_DIR . 'build/index.asset.php';
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
            'insag-block-editor',
            INSAG_PLUGIN_URL . 'build/index.js',
            $deps,
            $asset['version'] . '-' . INSAG_VERSION,
            false // Load in <head> so the filter runs before the editor renders blocks.
        );
        wp_set_script_translations(
            'insag-block-editor',
            'internick-smart-alt-generator',
            INSAG_PLUGIN_DIR . 'languages'
        );
    }

    public function render_settings_page() {
        require INSAG_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    public function render_bulk_page() {
        require INSAG_PLUGIN_DIR . 'admin/views/bulk-page.php';
    }

    public function render_audit_page() {
        require INSAG_PLUGIN_DIR . 'admin/views/audit-page.php';
    }
}
