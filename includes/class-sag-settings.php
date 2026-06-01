<?php
/**
 * Settings — registers options via the WordPress Settings API.
 *
 * @package Smart_Alt_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class SAG_Settings {

    const GROUP = 'sag_settings';

    /** Allowed model ids. */
    public static function allowed_models() {
        return array( 'gpt-4o-mini', 'gpt-4o' );
    }

    /** Hooked to admin_init. */
    public function register() {
        register_setting( self::GROUP, 'sag_openai_api_key', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
        register_setting( self::GROUP, 'sag_model', array( 'sanitize_callback' => array( $this, 'sanitize_model' ), 'default' => 'gpt-4o-mini' ) );
        register_setting( self::GROUP, 'sag_language', array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'auto' ) );
        register_setting( self::GROUP, 'sag_auto_generate', array( 'sanitize_callback' => array( $this, 'sanitize_checkbox' ), 'default' => false ) );
    }

    /** Only allow known model ids; fall back to the cheap default. */
    public function sanitize_model( $value ) {
        return in_array( $value, self::allowed_models(), true ) ? $value : 'gpt-4o-mini';
    }

    /** Normalize a checkbox to a real bool. */
    public static function sanitize_checkbox( $value ) {
        return ! empty( $value );
    }
}
