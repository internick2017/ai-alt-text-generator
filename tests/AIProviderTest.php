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

    public function test_connector_returns_error_on_empty_response() {
        // An empty (or whitespace-only) generation must never be saved as alt
        // text: it would leave the image in the "missing alt" result set while
        // the caller counted it as a success.
        \WordPress\AiClient\AiClient::$text = "  \n ";

        $provider = new \INSAG_AI_Provider( 'wp_connector' );
        $result   = $provider->generate( 'https://x/cat.jpg', 'auto' );

        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'insag_empty_response', $result->get_error_code() );
    }

    public function test_connector_returns_trimmed_text_when_not_empty() {
        \WordPress\AiClient\AiClient::$text = "  A cat.\n";

        $provider = new \INSAG_AI_Provider( 'wp_connector' );

        $this->assertSame( 'A cat.', $provider->generate( 'https://x/cat.jpg', 'auto' ) );
    }
}
