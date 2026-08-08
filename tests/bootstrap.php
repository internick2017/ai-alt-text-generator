<?php
/**
 * PHPUnit bootstrap â€” loads Composer autoloader (for Brain Monkey),
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
        const EDITABLE  = 'POST, PUT, PATCH';
    }
}

// --- Minimal WP_Query stub: lets a test control posts/found_posts/max_num_pages ---
if ( ! class_exists( 'WP_Query' ) ) {
    class WP_Query {
        public $posts         = array();
        public $found_posts   = 0;
        public $max_num_pages = 0;
        public function __construct( $args = array() ) {
            global $insag_test_wp_query_result;
            if ( is_array( $insag_test_wp_query_result ) ) {
                $this->posts         = $insag_test_wp_query_result['posts'] ?? array();
                $this->found_posts   = $insag_test_wp_query_result['found_posts'] ?? 0;
                $this->max_num_pages = $insag_test_wp_query_result['max_num_pages'] ?? 0;
            }
        }
    }
}

// --- Load the plugin classes under test ---
$includes = __DIR__ . '/../includes/';
foreach ( array(
    'class-sag-image.php',
    'class-sag-openai.php',
    'class-sag-ai-provider.php',
    'class-sag-generator.php',
    'class-sag-audit.php',
    'class-sag-review-notice.php',
    'class-sag-rest-api.php',
    'class-sag-settings.php',
    'class-sag-media.php',
    'class-sag-plugin.php',
) as $file ) {
    if ( file_exists( $includes . $file ) ) {
        require_once $includes . $file;
    }
}
