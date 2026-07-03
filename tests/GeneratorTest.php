<?php
namespace SAG\Tests;

use Brain\Monkey\Functions;

final class GeneratorTest extends TestCase {

    protected function set_up() {
        parent::set_up();
        Functions\when( '__' )->returnArg( 1 );
    }

    public function test_generate_for_image_saves_alt_text() {
        Functions\when( 'get_attached_file' )->justReturn( '/var/uploads/img.jpg' );
        Functions\when( 'get_option' )->justReturn( 'auto' );
        Functions\when( 'sanitize_text_field' )->returnArg( 1 );

        // Expect the alt text saved to the correct postmeta key.
        Functions\expect( 'update_post_meta' )
            ->once()
            ->with( 123, '_wp_attachment_image_alt', 'A red apple.' )
            ->andReturn( true );

        // Expect the counter to be incremented.
        Functions\expect( 'update_option' )
            ->once()
            ->with( 'insag_generation_count', \Mockery::type( 'int' ), false )
            ->andReturn( true );

        // Fake image helper returns a data URI.
        $image = new class {
            public function path_to_data_uri( $p ) { return 'data:image/jpeg;base64,AAA'; }
        };
        // Fake provider records what it received and returns a fixed string.
        $provider = new class {
            public $received_image = null;
            public function generate( $img, $lang ) { $this->received_image = $img; return 'A red apple.'; }
        };

        $generator = new \INSAG_Generator( $provider, $image );
        $result    = $generator->generate_for_image( 123 );

        $this->assertSame( 'A red apple.', $result );
        // The provider must receive the data URI, NOT a URL.
        $this->assertStringStartsWith( 'data:image/jpeg;base64,', $provider->received_image );
    }

    public function test_generate_for_image_invalid_id_returns_error() {
        Functions\when( 'get_attached_file' )->justReturn( false );

        $image     = new class { public function path_to_data_uri( $p ) { return 'x'; } };
        $provider  = new class { public function generate( $i, $l ) { return 'x'; } };
        $generator = new \INSAG_Generator( $provider, $image );
        $result    = $generator->generate_for_image( 999 );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'insag_invalid_image', $result->get_error_code() );
    }

    public function test_generate_for_image_propagates_image_error() {
        Functions\when( 'get_attached_file' )->justReturn( '/var/uploads/file.txt' );

        // Image helper returns a WP_Error (e.g. unsupported type).
        $image = new class {
            public function path_to_data_uri( $p ) {
                return new \WP_Error( 'insag_image_type', 'Unsupported image type.' );
            }
        };
        $provider  = new class { public function generate( $i, $l ) { return 'should not run'; } };
        $generator = new \INSAG_Generator( $provider, $image );
        $result    = $generator->generate_for_image( 5 );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'insag_image_type', $result->get_error_code() );
    }

    public function test_generate_for_url_downloads_and_sends_data_uri() {
        Functions\when( 'get_option' )->justReturn( 'auto' );

        $image = new class {
            public function url_to_data_uri( $url ) { return 'data:image/png;base64,BBB'; }
        };
        $provider = new class {
            public $received_image = null;
            public function generate( $img, $lang ) { $this->received_image = $img; return 'A cat.'; }
        };

        $generator = new \INSAG_Generator( $provider, $image );
        $result    = $generator->generate_for_url( 'https://x/cat.png' );

        $this->assertSame( 'A cat.', $result );
        $this->assertStringStartsWith( 'data:image/png;base64,', $provider->received_image );
    }

    public function test_successful_generation_increments_review_counter() {
        Functions\when( 'get_attached_file' )->justReturn( '/var/uploads/img.jpg' );
        Functions\when( 'get_option' )->justReturn( 'auto' );
        Functions\when( 'sanitize_text_field' )->returnArg( 1 );
        Functions\when( 'update_post_meta' )->justReturn( true );

        // get_option is stubbed to 'auto' for everything, so the counter
        // increment becomes (int) 'auto' + 1 = 1. What matters: it fires once.
        Functions\expect( 'update_option' )
            ->once()
            ->with( 'insag_generation_count', \Mockery::type( 'int' ), false )
            ->andReturn( true );

        $image     = new class { public function path_to_data_uri( $p ) { return 'data:image/jpeg;base64,AAA'; } };
        $provider  = new class { public function generate( $i, $l ) { return 'A red apple.'; } };
        $generator = new \INSAG_Generator( $provider, $image );

        $this->assertSame( 'A red apple.', $generator->generate_for_image( 123 ) );
    }

    public function test_failed_generation_does_not_increment_counter() {
        Functions\when( 'get_attached_file' )->justReturn( '/var/uploads/img.jpg' );
        Functions\when( 'get_option' )->justReturn( 'auto' );
        Functions\expect( 'update_option' )->never();

        $image    = new class { public function path_to_data_uri( $p ) { return 'data:image/jpeg;base64,AAA'; } };
        $provider = new class {
            public function generate( $i, $l ) { return new \WP_Error( 'insag_api', 'boom' ); }
        };
        $generator = new \INSAG_Generator( $provider, $image );

        $this->assertInstanceOf( \WP_Error::class, $generator->generate_for_image( 123 ) );
    }
}
