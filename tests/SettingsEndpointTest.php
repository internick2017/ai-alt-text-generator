<?php
namespace SAG\Tests;

use Brain\Monkey\Functions;

final class SettingsEndpointTest extends TestCase {

    protected function set_up() {
        parent::set_up();
        Functions\when( '__' )->returnArg( 1 );
        Functions\when( 'rest_ensure_response' )->returnArg( 1 );
    }

    public function test_get_settings_returns_all_options() {
        Functions\when( 'get_option' )->alias( function ( $key, $default = false ) {
            return [
                'insag_openai_api_key' => 'sk-test',
                'insag_model'          => 'gpt-4o-mini',
                'insag_language'       => 'auto',
                'insag_auto_generate'  => false,
            ][ $key ] ?? $default;
        } );

        $api      = new \INSAG_REST_API();
        $response = $api->get_settings( new \WP_REST_Request() );

        $this->assertSame( 'sk-test', $response['insag_openai_api_key'] );
        $this->assertSame( 'gpt-4o-mini', $response['insag_model'] );
        $this->assertSame( 'auto', $response['insag_language'] );
        $this->assertFalse( $response['insag_auto_generate'] );
    }

    public function test_save_settings_persists_provided_fields() {
        $saved = [];
        Functions\when( 'update_option' )->alias( function ( $key, $value ) use ( &$saved ) {
            $saved[ $key ] = $value;
            return true;
        } );

        $api     = new \INSAG_REST_API();
        $request = new \WP_REST_Request();
        $request->set_param( 'insag_model', 'gpt-4o' );
        $request->set_param( 'insag_language', 'Spanish' );

        $response = $api->save_settings( $request );

        $this->assertTrue( $response['saved'] );
        $this->assertSame( 'gpt-4o', $saved['insag_model'] );
        $this->assertSame( 'Spanish', $saved['insag_language'] );
    }

    public function test_save_settings_skips_null_params() {
        $saved = [];
        Functions\when( 'update_option' )->alias( function ( $key, $value ) use ( &$saved ) {
            $saved[ $key ] = $value;
            return true;
        } );

        $request = new \WP_REST_Request();
        $request->set_param( 'insag_language', 'French' );
        ( new \INSAG_REST_API() )->save_settings( $request );

        $this->assertArrayHasKey( 'insag_language', $saved );
        $this->assertArrayNotHasKey( 'insag_model', $saved );
        $this->assertArrayNotHasKey( 'insag_openai_api_key', $saved );
    }

    public function test_validate_model_accepts_known_models() {
        $api = new \INSAG_REST_API();
        $this->assertTrue( $api->validate_model( 'gpt-4o-mini' ) );
        $this->assertTrue( $api->validate_model( 'gpt-4o' ) );
    }

    public function test_validate_model_rejects_unknown_model() {
        $result = ( new \INSAG_REST_API() )->validate_model( 'gpt-5-ultra' );
        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'insag_invalid_model', $result->get_error_code() );
    }

    public function test_check_admin_delegates_to_current_user_can() {
        $api = new \INSAG_REST_API();
        Functions\when( 'current_user_can' )->justReturn( true );
        $this->assertTrue( $api->check_admin() );
        Functions\when( 'current_user_can' )->justReturn( false );
        $this->assertFalse( $api->check_admin() );
    }
}
