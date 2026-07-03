<?php
namespace SAG\Tests;

use Brain\Monkey\Functions;

final class ReviewEndpointTest extends TestCase {

    protected function set_up() {
        parent::set_up();
        Functions\when( '__' )->returnArg( 1 );
        Functions\when( 'rest_ensure_response' )->returnArg( 1 );
    }

    public function test_valid_action_is_applied_and_persisted() {
        Functions\expect( 'update_option' )
            ->once()->with( 'insag_review_state', 'dismissed', false )->andReturn( true );

        $request = new \WP_REST_Request();
        $request->set_param( 'action', 'forever' );

        $api      = new \INSAG_REST_API();
        $response = $api->handle_review_dismiss( $request );

        $this->assertSame( array( 'state_action' => 'forever', 'ok' => true ), $response );
    }

    public function test_unknown_action_returns_400_error() {
        Functions\expect( 'update_option' )->never();

        $request = new \WP_REST_Request();
        $request->set_param( 'action', 'nuke' );

        $api      = new \INSAG_REST_API();
        $response = $api->handle_review_dismiss( $request );

        $this->assertInstanceOf( \WP_Error::class, $response );
        $this->assertSame( 'insag_invalid_action', $response->get_error_code() );
    }

    public function test_route_is_registered_with_admin_permission() {
        $captured = array();
        Functions\when( 'register_rest_route' )->alias(
            function ( $ns, $route, $args ) use ( &$captured ) {
                $captured[ $route ] = $args;
            }
        );

        $api = new \INSAG_REST_API();
        $api->register_routes();

        $this->assertArrayHasKey( '/review/dismiss', $captured );
        $this->assertSame( 'check_admin', $captured['/review/dismiss']['permission_callback'][1] );
        $this->assertSame( array( 'later', 'forever', 'reviewed' ), $captured['/review/dismiss']['args']['action']['enum'] );
    }
}
