<?php
namespace AATG\Tests;

use Brain\Monkey\Functions;

final class GeneratorTest extends TestCase {

    protected function set_up() {
        parent::set_up();
        Functions\when( '__' )->returnArg( 1 );
    }

    public function test_generate_for_image_saves_alt_text() {
        Functions\when( 'wp_get_attachment_url' )->justReturn( 'https://x/img.jpg' );
        Functions\when( 'get_option' )->justReturn( 'auto' );
        Functions\when( 'sanitize_text_field' )->returnArg( 1 );

        // Expect the alt text to be saved to the correct postmeta key.
        Functions\expect( 'update_post_meta' )
            ->once()
            ->with( 123, '_wp_attachment_image_alt', 'A red apple.' )
            ->andReturn( true );

        // Fake provider that returns a fixed string.
        $provider = new class {
            public function generate( $url, $lang ) { return 'A red apple.'; }
        };

        $generator = new \AATG_Generator( $provider );
        $result    = $generator->generate_for_image( 123 );

        $this->assertSame( 'A red apple.', $result );
    }

    public function test_generate_for_image_invalid_id_returns_error() {
        Functions\when( 'wp_get_attachment_url' )->justReturn( false );

        $provider  = new class { public function generate( $u, $l ) { return 'x'; } };
        $generator = new \AATG_Generator( $provider );
        $result    = $generator->generate_for_image( 999 );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'aatg_invalid_image', $result->get_error_code() );
    }
}
