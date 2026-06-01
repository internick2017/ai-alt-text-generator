<?php
namespace SAG\Tests;

use Brain\Monkey\Functions;

final class TestEndpointTest extends TestCase {

    protected function set_up() {
        parent::set_up();
        Functions\when( '__' )->returnArg( 1 );
        Functions\when( 'rest_ensure_response' )->returnArg( 1 );
    }

    public function test_test_connection_returns_error_when_no_api_key() {
        Functions\when( 'get_option' )->justReturn( '' );

        $result = ( new \SAG_REST_API() )->test_connection( new \WP_REST_Request() );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'sag_no_api_key', $result->get_error_code() );
    }

    public function test_test_connection_returns_ok_on_success() {
        Functions\when( 'get_option' )->alias( function ( $key, $default = '' ) {
            return $key === 'sag_openai_api_key' ? 'sk-test' : 'gpt-4o-mini';
        } );
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
        Functions\when( 'wp_remote_post' )->justReturn( array( 'response' => array( 'code' => 200 ) ) );
        Functions\when( 'is_wp_error' )->justReturn( false );
        Functions\when( 'wp_remote_retrieve_body' )->justReturn(
            '{"choices":[{"message":{"content":"."}}]}'
        );

        $result = ( new \SAG_REST_API() )->test_connection( new \WP_REST_Request() );

        $this->assertTrue( $result['ok'] );
        $this->assertSame( 'gpt-4o-mini', $result['model'] );
    }

    public function test_test_connection_returns_error_on_openai_api_error() {
        Functions\when( 'get_option' )->alias( function ( $key, $default = '' ) {
            return $key === 'sag_openai_api_key' ? 'sk-bad' : 'gpt-4o-mini';
        } );
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
        Functions\when( 'wp_remote_post' )->justReturn( array( 'response' => array( 'code' => 401 ) ) );
        Functions\when( 'is_wp_error' )->justReturn( false );
        Functions\when( 'wp_remote_retrieve_body' )->justReturn(
            '{"error":{"message":"Incorrect API key provided."}}'
        );

        $result = ( new \SAG_REST_API() )->test_connection( new \WP_REST_Request() );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'sag_test_failed', $result->get_error_code() );
        $this->assertStringContainsString( 'Incorrect API key', $result->get_error_message() );
    }

    public function test_test_connection_returns_error_on_http_failure() {
        Functions\when( 'get_option' )->alias( function ( $key, $default = '' ) {
            return $key === 'sag_openai_api_key' ? 'sk-test' : 'gpt-4o-mini';
        } );
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
        $wp_error = new \WP_Error( 'http_request_failed', 'cURL error 6: Could not resolve host' );
        Functions\when( 'wp_remote_post' )->justReturn( $wp_error );
        Functions\when( 'is_wp_error' )->justReturn( true );

        $result = ( new \SAG_REST_API() )->test_connection( new \WP_REST_Request() );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'sag_test_failed', $result->get_error_code() );
    }
}
