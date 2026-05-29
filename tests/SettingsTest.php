<?php
namespace AATG\Tests;

final class SettingsTest extends TestCase {

    public function test_sanitize_model_allows_known_models() {
        $settings = new \AATG_Settings();
        $this->assertSame( 'gpt-4o-mini', $settings->sanitize_model( 'gpt-4o-mini' ) );
        $this->assertSame( 'gpt-4o', $settings->sanitize_model( 'gpt-4o' ) );
    }

    public function test_sanitize_model_falls_back_on_unknown() {
        $settings = new \AATG_Settings();
        $this->assertSame( 'gpt-4o-mini', $settings->sanitize_model( 'evil-model' ) );
    }

    public function test_sanitize_checkbox() {
        $settings = new \AATG_Settings();
        $this->assertTrue( $settings->sanitize_checkbox( '1' ) );
        $this->assertFalse( $settings->sanitize_checkbox( null ) );
    }
}
