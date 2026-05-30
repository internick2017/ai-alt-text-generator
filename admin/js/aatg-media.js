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
		showMessage( btn, '', '' ); // clear any previous message

		try {
			const res = await wp.apiFetch( {
				path: '/ai-alt-text/v1/generate',
				method: 'POST',
				data: { image_id: imageId },
			} );

			// Update the alt text field — IDs differ by context:
			// - upload.php grid modal:  attachment-details-two-column-alt-text
			// - Insert Media modal:     attachment-details-alt-text
			// - Classic/form fallback:  attachments-{id}-alt
			const field =
				document.getElementById( 'attachment-details-two-column-alt-text' ) ||
				document.getElementById( 'attachment-details-alt-text' ) ||
				document.getElementById( 'attachments-' + imageId + '-alt' );
			if ( field ) {
				field.value = res.alt_text;
				field.dispatchEvent( new Event( 'input', { bubbles: true } ) );
				field.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			}
			showMessage( btn, '✓ ' + res.alt_text, 'ok' );
		} catch ( e ) {
			// Surface the real error message (e.g. OpenAI quota) to the user.
			const msg = ( e && e.message ) ? e.message : 'Generation failed.';
			showMessage( btn, '⚠ ' + msg, 'err' );
		} finally {
			btn.disabled = false;
			btn.textContent = original;
		}
	} );

	/**
	 * Show a status message in a div right after the button.
	 * type: 'ok' (green) | 'err' (red) | '' (clear).
	 */
	function showMessage( btn, text, type ) {
		let box = btn.parentNode.querySelector( '.aatg-message' );
		if ( ! text ) {
			if ( box ) {
				box.remove();
			}
			return;
		}
		if ( ! box ) {
			box = document.createElement( 'p' );
			box.className = 'aatg-message';
			box.style.margin = '6px 0 0';
			box.style.fontSize = '12px';
			box.style.lineHeight = '1.4';
			btn.parentNode.appendChild( box );
		}
		box.textContent = text;
		box.style.color = type === 'err' ? '#cf222e' : '#1a7f37';
	}
} )();
