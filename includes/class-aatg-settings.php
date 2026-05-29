<?php
/**
 * Settings — registers options via the WordPress Settings API.
 *
 * @package AI_Alt_Text_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class AATG_Settings {

    const GROUP = 'aatg_settings';

    /** Allowed model ids. */
    private function allowed_models() {
        return array( 'gpt-4o-mini', 'gpt-4o' );
    }

    /** Hooked to admin_init. */
    public function register() {
        register_setting( self::GROUP, 'aatg_openai_api_key', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
        register_setting( self::GROUP, 'aatg_model', array( 'sanitize_callback' => array( $this, 'sanitize_model' ), 'default' => 'gpt-4o-mini' ) );
        register_setting( self::GROUP, 'aatg_language', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'auto' ) );
        register_setting( self::GROUP, 'aatg_auto_generate', array( 'sanitize_callback' => array( $this, 'sanitize_checkbox' ), 'default' => false ) );
    }

    /** Only allow known model ids; fall back to the cheap default. */
    public function sanitize_model( $value ) {
        return in_array( $value, $this->allowed_models(), true ) ? $value : 'gpt-4o-mini';
    }

    /** Normalize a checkbox to a real bool. */
    public function sanitize_checkbox( $value ) {
        return ! empty( $value );
    }
}
