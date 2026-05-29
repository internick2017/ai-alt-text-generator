<?php
namespace AATG\Tests;

use Brain\Monkey\Functions;

final class PluginTest extends TestCase {

    public function test_get_instance_returns_same_object() {
        // AATG_Plugin constructor calls load_dependencies() + register_hooks();
        // register_hooks() calls add_action()/add_filter()/is_admin(), so stub them.
        Functions\when( 'add_action' )->justReturn( true );
        Functions\when( 'add_filter' )->justReturn( true );
        Functions\when( 'is_admin' )->justReturn( false );
        Functions\when( 'plugin_dir_path' )->justReturn( '/tmp/' );
        Functions\when( 'plugin_dir_url' )->justReturn( 'http://x/' );

        $a = \AATG_Plugin::get_instance();
        $b = \AATG_Plugin::get_instance();

        $this->assertSame( $a, $b, 'Singleton must return the same instance' );
    }
}
