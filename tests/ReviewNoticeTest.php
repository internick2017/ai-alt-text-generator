<?php
namespace SAG\Tests;

use Brain\Monkey\Functions;

final class ReviewNoticeTest extends TestCase {

    // ---- should_show(): pure, no WP calls ----

    public function test_hidden_below_threshold() {
        $this->assertFalse( \INSAG_Review_Notice::should_show( 9, '', 0, 1000 ) );
    }

    public function test_shown_at_threshold_with_no_state() {
        $this->assertTrue( \INSAG_Review_Notice::should_show( 10, '', 0, 1000 ) );
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
        $this->assertTrue( \INSAG_Review_Notice::should_show( 10, 'garbage', 0, 1000 ) );
    }

    // ---- record_generation(): increments the counter option ----

    public function test_record_generation_increments_count() {
        Functions\when( 'get_option' )->justReturn( 4 );
        Functions\expect( 'update_option' )
            ->once()
            ->with( 'insag_generation_count', 5, false )
            ->andReturn( true );

        \INSAG_Review_Notice::record_generation();
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
}
