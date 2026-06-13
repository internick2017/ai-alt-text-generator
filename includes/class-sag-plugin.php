<?php
/**
 * Main plugin class â€” Singleton.
 *
 * Loads all dependency classes and registers WordPress hooks exactly once.
 *
 * @package Smart_Alt_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class INSAG_Plugin {

    /** @var INSAG_Plugin|null The single instance. */
    private static $instance = null;

    /** Private constructor prevents `new INSAG_Plugin()` from outside. */
    private function __construct() {
        $this->load_dependencies();
        $this->register_hooks();
    }

    /** Single access point. Creates the instance on first call. */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /** Load class files. Guarded so unit tests (which preload them) don't double-require. */
    private function load_dependencies() {
        if ( ! defined( 'INSAG_PLUGIN_DIR' ) ) {
            return; // In unit tests the classes are already loaded by bootstrap.php.
        }
        require_once INSAG_PLUGIN_DIR . 'includes/class-sag-image.php';
        require_once INSAG_PLUGIN_DIR . 'includes/class-sag-openai.php';
        require_once INSAG_PLUGIN_DIR . 'includes/class-sag-ai-provider.php';
        require_once INSAG_PLUGIN_DIR . 'includes/class-sag-generator.php';
        require_once INSAG_PLUGIN_DIR . 'includes/class-sag-rest-api.php';
        require_once INSAG_PLUGIN_DIR . 'includes/class-sag-settings.php';
        require_once INSAG_PLUGIN_DIR . 'includes/class-sag-media.php';
        require_once INSAG_PLUGIN_DIR . 'admin/class-sag-admin.php';
    }

    /**
     * Wire up WordPress hooks for each subsystem.
     *
     * Each instantiation is guarded with class_exists() so the plugin works
     * during incremental development (some classes are added in later tasks).
     */
    private function register_hooks() {
        if ( class_exists( 'INSAG_REST_API' ) ) {
            $rest = new INSAG_REST_API();
            add_action( 'rest_api_init', array( $rest, 'register_routes' ) );
        }
        if ( class_exists( 'INSAG_Settings' ) ) {
            $settings = new INSAG_Settings();
            add_action( 'admin_init', array( $settings, 'register' ) );
        }
        if ( is_admin() && class_exists( 'INSAG_Admin' ) ) {
            $admin = new INSAG_Admin();
            add_action( 'admin_menu', array( $admin, 'register_menus' ) );
            add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_assets' ) );
            add_action( 'enqueue_block_editor_assets', array( $admin, 'enqueue_block_editor' ) );
        }
        if ( class_exists( 'INSAG_Media' ) ) {
            $media = new INSAG_Media();
            add_action( 'add_attachment', array( $media, 'maybe_auto_generate' ) );
            add_filter( 'attachment_fields_to_edit', array( $media, 'add_generate_button' ), 10, 2 );
        }
    }

    /** Runs on activation: set default options. */
    public static function activate() {
        add_option( 'insag_model', 'gpt-4o-mini' );
        add_option( 'insag_language', 'auto' );
        add_option( 'insag_auto_generate', false );
    }

    /** Runs on deactivation. No persistent cleanup here (that's uninstall.php). */
    public static function deactivate() {
        // Intentionally empty for v1.
    }
}
