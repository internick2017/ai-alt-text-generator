/**
 * Classic Media Library "Generate Alt Text" button handler.
 * Delegated click listener — works even when the attachment panel is rendered
 * dynamically inside the media modal.
 */
( function () {
	document.addEventListener( 'click', async function ( event ) {
		const btn = event.target.closest( '.aatg-generate-btn' );
		if ( ! btn ) {
			return;
		}
		event.preventDefault();

		const imageId = parseInt( btn.dataset.imageId, 10 );
		if ( ! imageId ) {
			return;
		}

		const original = btn.textContent;
		btn.disabled = true;
		btn.textContent = '…';

		try {
			const res = await wp.apiFetch( {
				path: '/ai-alt-text/v1/generate',
				method: 'POST',
				data: { image_id: imageId },
			} );

			// Update the alt text field in the same panel if present.
			const field = document.getElementById( 'attachments-' + imageId + '-alt' );
			if ( field ) {
				field.value = res.alt_text;
				field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
			btn.textContent = '✓';
		} catch ( e ) {
			btn.textContent = '✗';
			window.console.error( 'AATG:', e.message || e );
		} finally {
			setTimeout( function () {
				btn.disabled = false;
				btn.textContent = original;
			}, 1500 );
		}
	} );
} )();
