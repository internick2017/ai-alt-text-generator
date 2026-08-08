<?php
/**
 * Minimal stand-in for WordPress 7.0's native AI Connectors SDK, so the
 * INSAG_AI_Provider "wp_connector" path can be unit-tested without the SDK.
 *
 * Only the fluent surface the plugin actually uses is modelled:
 *   AiClient::prompt()->withText()->withFile()->generateTextResult()->toText()
 *
 * Tests control the fake generation by setting AiClient::$text.
 */

namespace WordPress\AiClient;

if ( ! class_exists( __NAMESPACE__ . '\AiClient' ) ) {

    class AiClientTextResult {
        private $text;
        public function __construct( $text ) {
            $this->text = $text;
        }
        public function toText() {
            return $this->text;
        }
    }

    class AiClientPrompt {
        public function withText( $text ) {
            return $this;
        }
        public function withFile( $url ) {
            return $this;
        }
        public function generateTextResult() {
            return new AiClientTextResult( AiClient::$text );
        }
    }

    class AiClient {
        /** @var string Text the fake model returns on the next generation. */
        public static $text = '';

        public static function prompt() {
            return new AiClientPrompt();
        }
    }
}
