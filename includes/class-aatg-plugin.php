<?php
/**
 * Main plugin class — Singleton.
 *
 * Loads all dependency classes and registers WordPress hooks exactly once.
 *
 * @package AI_Alt_Text_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class AATG_Plugin {

    /** @var AATG_Plugin|null The single instance. */
    private static $instance = null;

    /** Private constructor prevents `new AATG_Plugin()` from outside. */
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
        if ( ! defined( 'AATG_PLUGIN_DIR' ) ) {
            return; // In unit tests the classes are already loaded by bootstrap.php.
        }
        require_once AATG_PLUGIN_DIR . 'includes/class-aatg-image.php';
        require_once AATG_PLUGIN_DIR . 'includes/class-aatg-openai.php';
        require_once AATG_PLUGIN_DIR . 'includes/class-aatg-ai-provider.php';
        require_once AATG_PLUGIN_DIR . 'includes/class-aatg-generator.php';
        require_once AATG_PLUGIN_DIR . 'includes/class-aatg-rest-api.php';
        require_once AATG_PLUGIN_DIR . 'includes/class-aatg-settings.php';
        require_once AATG_PLUGIN_DIR . 'includes/class-aatg-media.php';
        require_once AATG_PLUGIN_DIR . 'admin/class-aatg-admin.php';
    }

    /**
     * Wire up WordPress hooks for each subsystem.
     *
     * Each instantiation is guarded with class_exists() so the plugin works
     * during incremental development (some classes are added in later tasks).
     */
    private function register_hooks() {
        if ( class_exists( 'AATG_REST_API' ) ) {
            $rest = new AATG_REST_API();
            add_action( 'rest_api_init', array( $rest, 'register_routes' ) );
        }
        if ( class_exists( 'AATG_Settings' ) ) {
            $settings = new AATG_Settings();
            add_action( 'admin_init', array( $settings, 'register' ) );
        }
        if ( is_admin() && class_exists( 'AATG_Admin' ) ) {
            $admin = new AATG_Admin();
            add_action( 'admin_menu', array( $admin, 'register_menus' ) );
            add_action( 'admin_enqueue_scripts', array( $admin, 'enqueue_assets' ) );
            add_action( 'enqueue_block_editor_assets', array( $admin, 'enqueue_block_editor' ) );
        }
        if ( class_exists( 'AATG_Media' ) ) {
            $media = new AATG_Media();
            add_action( 'add_attachment', array( $media, 'maybe_auto_generate' ) );
            add_filter( 'attachment_fields_to_edit', array( $media, 'add_generate_button' ), 10, 2 );
        }
    }

    /** Runs on activation: set default options. */
    public static function activate() {
        add_option( 'aatg_model', 'gpt-4o-mini' );
        add_option( 'aatg_language', 'auto' );
        add_option( 'aatg_auto_generate', false );
    }

    /** Runs on deactivation. No persistent cleanup here (that's uninstall.php). */
    public static function deactivate() {
        // Intentionally empty for v1.
    }
}
