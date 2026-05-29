<?php
namespace AATG\Tests;

use Brain\Monkey\Functions;

final class MediaTest extends TestCase {

    protected function set_up() {
        parent::set_up();
        Functions\when( '__' )->returnArg( 1 );
    }

    public function test_auto_generate_skips_when_option_off() {
        Functions\when( 'get_option' )->justReturn( false ); // auto_generate off
        Functions\when( 'wp_attachment_is_image' )->justReturn( true );

        $fake = new class {
            public $called = false;
            public function generate_for_image( $id ) { $this->called = true; return 'x'; }
        };
        $media = new \AATG_Media( $fake );
        $media->maybe_auto_generate( 10 );

        $this->assertFalse( $fake->called, 'Should not generate when auto_generate is off' );
    }

    public function test_auto_generate_runs_when_option_on_and_is_image() {
        Functions\when( 'get_option' )->justReturn( true ); // auto_generate on
        Functions\when( 'wp_attachment_is_image' )->justReturn( true );

        $fake = new class {
            public $called_with = null;
            public function generate_for_image( $id ) { $this->called_with = $id; return 'x'; }
        };
        $media = new \AATG_Media( $fake );
        $media->maybe_auto_generate( 42 );

        $this->assertSame( 42, $fake->called_with );
    }
}
