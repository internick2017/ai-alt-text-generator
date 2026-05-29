<?php
namespace AATG\Tests;

use Brain\Monkey\Functions;

final class RestApiTest extends TestCase {

    protected function set_up() {
        parent::set_up();
        Functions\when( '__' )->returnArg( 1 );
        Functions\when( 'rest_ensure_response' )->returnArg( 1 );
    }

    public function test_permission_requires_upload_files_cap() {
        $api = new \AATG_REST_API();

        Functions\when( 'current_user_can' )->justReturn( true );
        $this->assertTrue( $api->check_permission() );

        Functions\when( 'current_user_can' )->justReturn( false );
        $this->assertFalse( $api->check_permission() );
    }

    public function test_handle_generate_with_image_id_saves_and_returns() {
        // Fake generator injected via constructor.
        $fake = new class {
            public function generate_for_image( $id ) { return 'Alt for ' . $id; }
            public function generate_for_url( $url ) { return 'Alt for url'; }
        };
        $api = new \AATG_REST_API( $fake );

        $request = new \WP_REST_Request();
        $request->set_param( 'image_id', 55 );

        $response = $api->handle_generate( $request );
        $this->assertSame( 'Alt for 55', $response['alt_text'] );
        $this->assertSame( 55, $response['image_id'] );
        $this->assertTrue( $response['saved'] );
    }

    public function test_handle_generate_with_url_only_does_not_save() {
        $fake = new class {
            public function generate_for_image( $id ) { return 'x'; }
            public function generate_for_url( $url ) { return 'Alt for url'; }
        };
        $api = new \AATG_REST_API( $fake );

        $request = new \WP_REST_Request();
        $request->set_param( 'image_url', 'https://x/y.jpg' );

        $response = $api->handle_generate( $request );
        $this->assertSame( 'Alt for url', $response['alt_text'] );
        $this->assertNull( $response['image_id'] );
        $this->assertFalse( $response['saved'] );
    }
}
