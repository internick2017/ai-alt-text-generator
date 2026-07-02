<?php
namespace SAG\Tests;

use Brain\Monkey\Functions;

final class AuditEndpointTest extends TestCase {

    protected function set_up() {
        parent::set_up();
        Functions\when( '__' )->returnArg( 1 );
        Functions\when( 'rest_ensure_response' )->returnArg( 1 );
        Functions\when( 'delete_transient' )->justReturn( true );
    }

    public function test_dismiss_denies_without_edit_post() {
        Functions\when( 'current_user_can' )->justReturn( false );
        $request = new \WP_REST_Request();
        $request->set_param( 'image_id', 5 );
        $request->set_param( 'dismissed', true );
        $result = ( new \INSAG_REST_API() )->handle_audit_dismiss( $request );
        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'insag_forbidden', $result->get_error_code() );
    }

    public function test_dismiss_sets_meta_when_allowed() {
        Functions\when( 'current_user_can' )->justReturn( true );
        $saved = array();
        Functions\when( 'update_post_meta' )->alias( function ( $id, $key, $val ) use ( &$saved ) {
            $saved[ $key ] = array( $id, $val );
        } );
        Functions\when( 'delete_post_meta' )->justReturn( true );

        $request = new \WP_REST_Request();
        $request->set_param( 'image_id', 5 );
        $request->set_param( 'dismissed', true );
        $result = ( new \INSAG_REST_API() )->handle_audit_dismiss( $request );

        $this->assertTrue( $result['dismissed'] );
        $this->assertSame( array( 5, 1 ), $saved['_insag_audit_dismissed'] );
    }

    public function test_set_alt_denies_without_edit_post() {
        Functions\when( 'current_user_can' )->justReturn( false );
        $request = new \WP_REST_Request();
        $request->set_param( 'image_id', 7 );
        $request->set_param( 'alt', 'hello' );
        $result = ( new \INSAG_REST_API() )->handle_audit_set_alt( $request );
        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'insag_forbidden', $result->get_error_code() );
    }

    public function test_set_alt_saves_sanitized_value() {
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'sanitize_text_field' )->returnArg( 1 );
        $saved = array();
        Functions\when( 'update_post_meta' )->alias( function ( $id, $key, $val ) use ( &$saved ) {
            $saved[ $key ] = array( $id, $val );
        } );

        $request = new \WP_REST_Request();
        $request->set_param( 'image_id', 7 );
        $request->set_param( 'alt', 'A cat on a sofa' );
        $result = ( new \INSAG_REST_API() )->handle_audit_set_alt( $request );

        $this->assertSame( 'A cat on a sofa', $result['alt'] );
        $this->assertSame( array( 7, 'A cat on a sofa' ), $saved['_wp_attachment_image_alt'] );
    }
}
