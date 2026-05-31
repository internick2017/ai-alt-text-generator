<?php
namespace SAG\Tests;

use Brain\Monkey\Functions;

final class ImageTest extends TestCase {

    protected function set_up() {
        parent::set_up();
        Functions\when( '__' )->returnArg( 1 );
    }

    public function test_mime_from_path_known_extensions() {
        $img = new \SAG_Image();
        $this->assertSame( 'image/jpeg', $img->mime_from_path( '/x/photo.jpg' ) );
        $this->assertSame( 'image/jpeg', $img->mime_from_path( '/x/photo.JPEG' ) );
        $this->assertSame( 'image/png', $img->mime_from_path( '/x/photo.png' ) );
        $this->assertSame( 'image/webp', $img->mime_from_path( '/x/photo.webp' ) );
    }

    public function test_mime_from_path_unknown_returns_empty() {
        $img = new \SAG_Image();
        $this->assertSame( '', $img->mime_from_path( '/x/file.txt' ) );
    }

    public function test_build_data_uri_format() {
        $img = new \SAG_Image();
        $uri = $img->build_data_uri( 'HELLO', 'image/png' );
        // base64 of "HELLO" is SEVMTE8=
        $this->assertSame( 'data:image/png;base64,SEVMTE8=', $uri );
    }

    public function test_path_to_data_uri_reads_real_file() {
        // Write a tiny temp file and confirm it gets encoded.
        $tmp = sys_get_temp_dir() . '/aatg-test-pixel.png';
        file_put_contents( $tmp, 'PNGDATA' );

        $img = new \SAG_Image();
        $uri = $img->path_to_data_uri( $tmp );

        $this->assertStringStartsWith( 'data:image/png;base64,', $uri );
        $this->assertStringContainsString( base64_encode( 'PNGDATA' ), $uri );

        unlink( $tmp );
    }

    public function test_path_to_data_uri_missing_file_returns_error() {
        $img    = new \SAG_Image();
        $result = $img->path_to_data_uri( '/nope/missing.png' );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'sag_image_unreadable', $result->get_error_code() );
    }

    public function test_path_to_data_uri_unsupported_type_returns_error() {
        $tmp = sys_get_temp_dir() . '/aatg-test-file.txt';
        file_put_contents( $tmp, 'hello' );

        $img    = new \SAG_Image();
        $result = $img->path_to_data_uri( $tmp );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'sag_image_type', $result->get_error_code() );

        unlink( $tmp );
    }
}
