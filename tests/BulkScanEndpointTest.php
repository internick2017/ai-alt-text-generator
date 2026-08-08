<?php
namespace SAG\Tests;

use Brain\Monkey\Functions;

final class BulkScanEndpointTest extends TestCase {

    protected function set_up() {
        parent::set_up();
        Functions\when( 'rest_ensure_response' )->returnArg( 1 );
    }

    protected function tear_down() {
        global $insag_test_wp_query_result;
        $insag_test_wp_query_result = null;
        parent::tear_down();
    }

    public function test_returns_page_and_metadata_and_ids() {
        global $insag_test_wp_query_result;
        $insag_test_wp_query_result = array(
            'posts'         => array( '10', '20', '30' ),
            'found_posts'   => 250,
            'max_num_pages' => 3,
        );

        $request = new \WP_REST_Request();
        $request->set_param( 'page', 2 );

        $result = ( new \INSAG_REST_API() )->handle_bulk_scan( $request );

        $this->assertSame( 2, $result['page'] );
        $this->assertSame( 3, $result['total_pages'] );
        $this->assertSame( 250, $result['total'] );
        $this->assertSame( array( 10, 20, 30 ), $result['ids'] );
        foreach ( $result['ids'] as $id ) {
            $this->assertIsInt( $id );
        }
    }

    public function test_out_of_range_page_returns_empty_ids_with_metadata() {
        global $insag_test_wp_query_result;
        $insag_test_wp_query_result = array(
            'posts'         => array(),
            'found_posts'   => 250,
            'max_num_pages' => 3,
        );

        $request = new \WP_REST_Request();
        $request->set_param( 'page', 99 );

        $result = ( new \INSAG_REST_API() )->handle_bulk_scan( $request );

        $this->assertSame( 99, $result['page'] );
        $this->assertSame( 3, $result['total_pages'] );
        $this->assertSame( 250, $result['total'] );
        $this->assertSame( array(), $result['ids'] );
    }

    public function test_page_defaults_to_one_when_missing() {
        global $insag_test_wp_query_result;
        $insag_test_wp_query_result = array(
            'posts'         => array( '1' ),
            'found_posts'   => 1,
            'max_num_pages' => 1,
        );

        $request = new \WP_REST_Request();

        $result = ( new \INSAG_REST_API() )->handle_bulk_scan( $request );

        $this->assertSame( 1, $result['page'] );
    }
}
