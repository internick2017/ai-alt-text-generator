/**
 * Review notice buttons. Vanilla JS, no build step.
 * Expects window.insagReviewData = { restBase, nonce, reviewUrl }.
 */
( function () {
    'use strict';

    document.addEventListener( 'DOMContentLoaded', function () {
        var notice = document.querySelector( '.insag-review-notice' );
        if ( ! notice || ! window.insagReviewData ) {
            return;
        }

        notice.addEventListener( 'click', function ( event ) {
            var el = event.target.closest( '[data-insag-review]' );
            if ( ! el ) {
                return;
            }
            var action = el.getAttribute( 'data-insag-review' );
            // "reviewed" is an <a target=_blank>: let the link open, just record.

            window.fetch( window.insagReviewData.restBase + '/review/dismiss', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.insagReviewData.nonce,
                },
                body: JSON.stringify( { action: action } ),
            } ).then( function ( response ) {
                if ( response.ok ) {
                    notice.remove();
                }
            } ).catch( function () {
                // Network failure: keep the notice so the user can try again.
            } );
        } );
    } );
} )();
