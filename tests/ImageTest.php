<?php
namespace SAG\Tests;

use Brain\Monkey\Functions;

final class ImageTest extends TestCase {

    protected function set_up() {
        parent::set_up();
        Functions\when( '__' )->returnArg( 1 );
    }

    public function test_mime_from_path_known_extensions() {
        $img = new \INSAG_Image();
        $this->assertSame( 'image/jpeg', $img->mime_from_path( '/x/photo.jpg' ) );
        $this->assertSame( 'image/jpeg', $img->mime_from_path( '/x/photo.JPEG' ) );
        $this->assertSame( 'image/png', $img->mime_from_path( '/x/photo.png' ) );
        $this->assertSame( 'image/webp', $img->mime_from_path( '/x/photo.webp' ) );
    }

    public function test_mime_from_path_unknown_returns_empty() {
        $img = new \INSAG_Image();
        $this->assertSame( '', $img->mime_from_path( '/x/file.txt' ) );
    }

    public function test_build_data_uri_format() {
        $img = new \INSAG_Image();
        $uri = $img->build_data_uri( 'HELLO', 'image/png' );
        // base64 of "HELLO" is SEVMTE8=
        $this->assertSame( 'data:image/png;base64,SEVMTE8=', $uri );
    }

    public function test_path_to_data_uri_reads_real_file() {
        // Write a tiny temp file and confirm it gets encoded.
        $tmp = sys_get_temp_dir() . '/sag-test-pixel.png';
        file_put_contents( $tmp, 'PNGDATA' );

        $img = new \INSAG_Image();
        $uri = $img->path_to_data_uri( $tmp );

        $this->assertStringStartsWith( 'data:image/png;base64,', $uri );
        $this->assertStringContainsString( base64_encode( 'PNGDATA' ), $uri );

        unlink( $tmp );
    }

    public function test_path_to_data_uri_missing_file_returns_error() {
        $img    = new \INSAG_Image();
        $result = $img->path_to_data_uri( '/nope/missing.png' );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'insag_image_unreadable', $result->get_error_code() );
    }

    public function test_path_to_data_uri_unsupported_type_returns_error() {
        $tmp = sys_get_temp_dir() . '/sag-test-file.txt';
        file_put_contents( $tmp, 'hello' );

        $img    = new \INSAG_Image();
        $result = $img->path_to_data_uri( $tmp );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'insag_image_type', $result->get_error_code() );

        unlink( $tmp );
    }

    /**
     * AVIF must NOT be reported as directly sendable: the vision API accepts
     * only PNG, JPEG, WebP and GIF, so AVIF has to go through conversion.
     */
    public function test_mime_from_path_avif_is_not_directly_sendable() {
        $img = new \INSAG_Image();
        $this->assertSame( '', $img->mime_from_path( '/x/photo.avif' ) );
    }

    public function test_is_convertible_path_only_for_avif() {
        $img = new \INSAG_Image();
        $this->assertTrue( $img->is_convertible_path( '/x/photo.avif' ) );
        $this->assertTrue( $img->is_convertible_path( '/x/PHOTO.AVIF' ) );
        // Already sendable formats are not "convertible" — they need no work.
        $this->assertFalse( $img->is_convertible_path( '/x/photo.png' ) );
        // Non-images stay unsupported.
        $this->assertFalse( $img->is_convertible_path( '/x/file.txt' ) );
    }

    public function test_path_to_data_uri_converts_avif_to_jpeg() {
        $tmp = sys_get_temp_dir() . '/sag-test-image.avif';
        file_put_contents( $tmp, 'AVIFDATA' );

        // Fake editor: writes JPEG bytes wherever it is asked to save.
        Functions\when( 'wp_get_image_editor' )->justReturn( new FakeImageEditor( 'JPEGBYTES' ) );

        $img    = new \INSAG_Image();
        $result = $img->path_to_data_uri( $tmp );

        $this->assertIsString( $result );
        $this->assertStringStartsWith( 'data:image/jpeg;base64,', $result );
        $this->assertStringContainsString( base64_encode( 'JPEGBYTES' ), $result );

        unlink( $tmp );
    }

    public function test_path_to_data_uri_avif_returns_error_when_editor_cannot_handle_it() {
        $tmp = sys_get_temp_dir() . '/sag-test-image2.avif';
        file_put_contents( $tmp, 'AVIFDATA' );

        // Server has no AVIF-capable image editor.
        Functions\when( 'wp_get_image_editor' )->justReturn( new \WP_Error( 'no_editor', 'nope' ) );

        $img    = new \INSAG_Image();
        $result = $img->path_to_data_uri( $tmp );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'insag_image_convert', $result->get_error_code() );

        unlink( $tmp );
    }
}

/**
 * Stands in for WP_Image_Editor: records the requested mime and writes
 * fixed bytes to the destination path.
 */
final class FakeImageEditor {
    private $bytes;
    public function __construct( $bytes ) {
        $this->bytes = $bytes;
    }
    public function save( $path, $mime = null ) {
        file_put_contents( $path, $this->bytes );
        return array(
            'path'      => $path,
            'mime-type' => $mime,
        );
    }
}
