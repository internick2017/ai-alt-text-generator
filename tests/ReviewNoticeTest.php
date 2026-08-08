<?php
namespace SAG\Tests;

use Brain\Monkey\Functions;

final class ReviewNoticeTest extends TestCase {

    // ---- should_show(): pure, no WP calls ----

    public function test_hidden_below_threshold() {
        $this->assertFalse( \INSAG_Review_Notice::should_show( 4, '', 0, 1000 ) );
    }

    public function test_shown_at_threshold_with_no_state() {
        $this->assertTrue( \INSAG_Review_Notice::should_show( 5, '', 0, 1000 ) );
    }

    public function test_hidden_when_dismissed() {
        $this->assertFalse( \INSAG_Review_Notice::should_show( 50, 'dismissed', 0, 1000 ) );
    }

    public function test_hidden_when_reviewed() {
        $this->assertFalse( \INSAG_Review_Notice::should_show( 50, 'reviewed', 0, 1000 ) );
    }

    public function test_hidden_while_snooze_active() {
        $this->assertFalse( \INSAG_Review_Notice::should_show( 50, 'snoozed', 2000, 1999 ) );
    }

    public function test_shown_after_snooze_expires() {
        $this->assertTrue( \INSAG_Review_Notice::should_show( 50, 'snoozed', 2000, 2001 ) );
    }

    public function test_unknown_state_treated_as_never_answered() {
        $this->assertTrue( \INSAG_Review_Notice::should_show( 5, 'garbage', 0, 1000 ) );
    }

    // ---- record_generation(): increments the counter option ----

    public function test_record_generation_increments_count() {
        Functions\when( 'get_option' )->justReturn( 4 );
        Functions\expect( 'update_option' )
            ->once()
            ->with( 'insag_generation_count', 5, false )
            ->andReturn( true );

        \INSAG_Review_Notice::record_generation();
        $this->addToAssertionCount( 1 );
    }

    // ---- apply_action(): persists the user's choice ----

    public function test_apply_action_later_snoozes_30_days() {
        Functions\expect( 'update_option' )
            ->once()->with( 'insag_review_state', 'snoozed', false )->andReturn( true );
        Functions\expect( 'update_option' )
            ->once()->with( 'insag_review_snooze_until', 1000 + 2592000, false )->andReturn( true );

        $this->assertTrue( \INSAG_Review_Notice::apply_action( 'later', 1000 ) );
    }

    public function test_apply_action_forever_dismisses() {
        Functions\expect( 'update_option' )
            ->once()->with( 'insag_review_state', 'dismissed', false )->andReturn( true );

        $this->assertTrue( \INSAG_Review_Notice::apply_action( 'forever', 1000 ) );
    }

    public function test_apply_action_reviewed_marks_reviewed() {
        Functions\expect( 'update_option' )
            ->once()->with( 'insag_review_state', 'reviewed', false )->andReturn( true );

        $this->assertTrue( \INSAG_Review_Notice::apply_action( 'reviewed', 1000 ) );
    }

    public function test_apply_action_rejects_unknown_action() {
        Functions\expect( 'update_option' )->never();
        $this->assertFalse( \INSAG_Review_Notice::apply_action( 'nuke', 1000 ) );
    }

    // ---- maybe_render(): guards ----

    private function fake_screen( $id ) {
        Functions\when( 'get_current_screen' )->justReturn( (object) array( 'id' => $id ) );
    }

    public function test_render_skips_wrong_screen() {
        $this->fake_screen( 'dashboard' );
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( 'get_option' )->justReturn( 99 );

        ob_start();
        \INSAG_Review_Notice::maybe_render();
        $this->assertSame( '', ob_get_clean() );
    }

    public function test_render_skips_non_admin_user() {
        $this->fake_screen( 'settings_page_insag-settings' );
        Functions\when( 'current_user_can' )->justReturn( false );
        Functions\when( 'get_option' )->justReturn( 99 );

        ob_start();
        \INSAG_Review_Notice::maybe_render();
        $this->assertSame( '', ob_get_clean() );
    }

    public function test_render_outputs_three_actions_when_due() {
        $this->fake_screen( 'media_page_insag-audit' );
        Functions\when( 'current_user_can' )->justReturn( true );
        Functions\when( '__' )->returnArg( 1 );
        Functions\when( 'esc_html__' )->returnArg( 1 );
        Functions\when( 'esc_html' )->returnArg( 1 );
        Functions\when( 'esc_url' )->returnArg( 1 );
        // get_option: count over threshold, state empty, snooze 0.
        Functions\when( 'get_option' )->alias(
            function ( $key, $default = false ) {
                return 'insag_generation_count' === $key ? 42 : $default;
            }
        );

        ob_start();
        \INSAG_Review_Notice::maybe_render();
        $html = ob_get_clean();

        $this->assertStringContainsString( 'insag-review-notice', $html );
        $this->assertStringContainsString( 'data-insag-review="reviewed"', $html );
        $this->assertStringContainsString( 'data-insag-review="later"', $html );
        $this->assertStringContainsString( 'data-insag-review="forever"', $html );
        $this->assertStringContainsString( 'wordpress.org/support/plugin/internick-smart-alt-generator/reviews', $html );
    }
}
