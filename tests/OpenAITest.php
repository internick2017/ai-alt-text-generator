<?php
namespace SAG\Tests;

use Brain\Monkey\Functions;

final class OpenAITest extends TestCase {

    protected function set_up() {
        parent::set_up();
        Functions\when( '__' )->returnArg( 1 );
    }

    public function test_build_request_body_includes_model_and_image() {
        $client = new \SAG_OpenAI();
        $body   = $client->build_request_body( 'https://x/img.jpg', 'Describe this.', 'gpt-4o-mini' );

        $this->assertSame( 'gpt-4o-mini', $body['model'] );
        $this->assertSame( 'Describe this.', $body['messages'][0]['content'][0]['text'] );
        $this->assertSame( 'https://x/img.jpg', $body['messages'][0]['content'][1]['image_url']['url'] );
    }

    public function test_parse_response_returns_text_on_success() {
        $client = new \SAG_OpenAI();
        $data   = array( 'choices' => array( array( 'message' => array( 'content' => 'A red apple.' ) ) ) );

        $this->assertSame( 'A red apple.', $client->parse_response( $data ) );
    }

    public function test_parse_response_returns_wp_error_on_api_error() {
        $client = new \SAG_OpenAI();
        $data   = array( 'error' => array( 'message' => 'Invalid API key' ) );

        $result = $client->parse_response( $data );
        $this->assertInstanceOf( \WP_Error::class, $result );
        $this->assertSame( 'Invalid API key', $result->get_error_message() );
    }
}
