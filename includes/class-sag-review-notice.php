<?php
/**
 * Review notice — asks happy users for a WordPress.org review.
 *
 * Pure decision logic lives here (unit-testable); rendering and enqueue
 * hooks are wired from INSAG_Plugin / INSAG_Admin.
 *
 * @package Smart_Alt_Generator
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

class INSAG_Review_Notice {

    const THRESHOLD      = 10;
    const SNOOZE_SECONDS = 2592000; // 30 days.

    const STATE_SNOOZED   = 'snoozed';
    const STATE_DISMISSED = 'dismissed';
    const STATE_REVIEWED  = 'reviewed';

    const OPTION_COUNT  = 'insag_generation_count';
    const OPTION_STATE  = 'insag_review_state';
    const OPTION_SNOOZE = 'insag_review_snooze_until';

    const REVIEW_URL = 'https://wordpress.org/support/plugin/internick-smart-alt-generator/reviews/#new-post';

    /** Admin-page hooks where the notice may render. */
    const ALLOWED_SCREENS = array(
        'settings_page_insag-settings',
        'media_page_insag-bulk',
        'media_page_insag-audit',
    );

    /**
     * Pure display rule. All state comes in as arguments.
     *
     * @param int    $count        Successful generations so far.
     * @param string $state        '', 'snoozed', 'dismissed' or 'reviewed'.
     * @param int    $snooze_until Unix timestamp; only meaningful when snoozed.
     * @param int    $now          Current Unix timestamp.
     * @return bool
     */
    public static function should_show( $count, $state, $snooze_until, $now ) {
        if ( $count < self::THRESHOLD ) {
            return false;
        }
        if ( in_array( $state, array( self::STATE_DISMISSED, self::STATE_REVIEWED ), true ) ) {
            return false;
        }
        if ( self::STATE_SNOOZED === $state && $now <= (int) $snooze_until ) {
            return false;
        }
        return true;
    }

    /** Increment the successful-generation counter (autoload off: admin-only data). */
    public static function record_generation() {
        $count = (int) get_option( self::OPTION_COUNT, 0 );
        update_option( self::OPTION_COUNT, $count + 1, false );
    }

    /**
     * Persist a user response to the notice.
     *
     * @param string $action 'later' | 'forever' | 'reviewed'.
     * @param int    $now    Current Unix timestamp.
     * @return bool Whether the action was recognized.
     */
    public static function apply_action( $action, $now ) {
        switch ( $action ) {
            case 'later':
                update_option( self::OPTION_STATE, self::STATE_SNOOZED, false );
                update_option( self::OPTION_SNOOZE, $now + self::SNOOZE_SECONDS, false );
                return true;
            case 'forever':
                update_option( self::OPTION_STATE, self::STATE_DISMISSED, false );
                return true;
            case 'reviewed':
                update_option( self::OPTION_STATE, self::STATE_REVIEWED, false );
                return true;
        }
        return false;
    }

    /** Convenience wrapper reading live options. Used by render/enqueue guards. */
    public static function is_due() {
        return self::should_show(
            (int) get_option( self::OPTION_COUNT, 0 ),
            (string) get_option( self::OPTION_STATE, '' ),
            (int) get_option( self::OPTION_SNOOZE, 0 ),
            time()
        );
    }
}
