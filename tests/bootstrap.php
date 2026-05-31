<?php
/**
 * PHPUnit bootstrap — loads Composer autoloader (for Brain Monkey),
 * defines minimal WordPress stubs, and loads the plugin's classes.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Load the shared test base class explicitly (test files are discovered by
// PHPUnit, but the base class they extend must be available first).
require_once __DIR__ . '/TestCase.php';

// --- Minimal WordPress constant stubs ---
if ( ! defined( 'WPINC' ) ) {
    define( 'WPINC', 'wp-includes' );
}
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

// --- Minimal WP_Error stub (mimics the real class surface we use) ---
if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        public $errors = array();
        public $error_data = array();
        public function __construct( $code = '', $message = '', $data = '' ) {
            if ( '' !== $code ) {
                $this->errors[ $code ][] = $message;
                if ( '' !== $data ) {
                    $this->error_data[ $code ] = $data;
                }
            }
        }
        public function get_error_message() {
            $codes = array_keys( $this->errors );
            return $codes ? $this->errors[ $codes[0] ][0] : '';
        }
        public function get_error_code() {
            $codes = array_keys( $this->errors );
            return $codes ? $codes[0] : '';
        }
    }
}

// --- WP_REST classes are only type hints in our code; stub them minimally ---
if ( ! class_exists( 'WP_REST_Request' ) ) {
    class WP_REST_Request {
        private $params = array();
        public function set_param( $key, $value ) { $this->params[ $key ] = $value; }
        public function get_param( $key ) { return $this->params[ $key ] ?? null; }
    }
}
if ( ! class_exists( 'WP_REST_Server' ) ) {
    class WP_REST_Server {
        const READABLE  = 'GET';
        const CREATABLE = 'POST';
    }
}

// --- Load the plugin classes under test ---
$includes = __DIR__ . '/../includes/';
foreach ( array(
    'class-sag-image.php',
    'class-sag-openai.php',
    'class-sag-ai-provider.php',
    'class-sag-generator.php',
    'class-sag-rest-api.php',
    'class-sag-settings.php',
    'class-sag-media.php',
    'class-sag-plugin.php',
) as $file ) {
    if ( file_exists( $includes . $file ) ) {
        require_once $includes . $file;
    }
}
