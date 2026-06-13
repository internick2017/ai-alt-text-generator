<?php
namespace SAG\Tests;

use Brain\Monkey\Functions;

final class AIProviderTest extends TestCase {

    protected function set_up() {
        parent::set_up();
        Functions\when( '__' )->returnArg( 1 );
    }

    public function test_build_prompt_auto_language() {
        $provider = new \INSAG_AI_Provider( 'openai' );
        $prompt   = $provider->build_prompt( 'auto' );

        $this->assertStringContainsString( '125 characters', $prompt );
        $this->assertStringContainsString( 'same language as the website', $prompt );
    }

    public function test_build_prompt_specific_language() {
        $provider = new \INSAG_AI_Provider( 'openai' );
        $prompt   = $provider->build_prompt( 'Spanish' );

        $this->assertStringContainsString( 'in Spanish', $prompt );
    }

    public function test_generate_uses_openai_backend_when_selected() {
        // Stub the option reads + HTTP the OpenAI client performs.
        Functions\when( 'get_option' )->justReturn( 'sk-test' );
        Functions\when( 'wp_remote_post' )->justReturn( array( 'body' => '{}' ) );
        Functions\when( 'is_wp_error' )->justReturn( false );
        Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
        Functions\when( 'wp_remote_retrieve_body' )->justReturn(
            json_encode( array( 'choices' => array( array( 'message' => array( 'content' => 'A cat.' ) ) ) ) )
        );

        $provider = new \INSAG_AI_Provider( 'openai' );
        $result   = $provider->generate( 'https://x/cat.jpg', 'auto' );

        $this->assertSame( 'A cat.', $result );
    }
}
