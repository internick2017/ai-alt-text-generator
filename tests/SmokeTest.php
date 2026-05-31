<?php
namespace SAG\Tests;

use Brain\Monkey\Functions;

final class SmokeTest extends TestCase {
    public function test_brain_monkey_mocks_wp_functions() {
        Functions\when( 'get_option' )->justReturn( 'gpt-4o-mini' );
        $this->assertSame( 'gpt-4o-mini', get_option( 'sag_model' ) );
    }
}
