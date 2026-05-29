/**
 * Bulk alt text generator. Calls the REST API once per image, sequentially,
 * updating a progress bar. Uses wp.apiFetch (handles the nonce automatically).
 */
( function () {
	const startBtn = document.getElementById( 'aatg-start' );
	if ( ! startBtn ) {
		return;
	}

	startBtn.addEventListener( 'click', async function () {
		const ids = JSON.parse( startBtn.dataset.ids || '[]' );
		const total = ids.length;
		if ( ! total ) {
			return;
		}

		startBtn.disabled = true;
		document.querySelector( '.aatg-progress-wrap' ).style.display = 'block';

		const fill = document.getElementById( 'aatg-progress-fill' );
		const text = document.getElementById( 'aatg-progress-text' );
		const log = document.getElementById( 'aatg-log' );

		let done = 0;
		for ( const id of ids ) {
			try {
				const res = await wp.apiFetch( {
					path: '/ai-alt-text/v1/generate',
					method: 'POST',
					data: { image_id: id },
				} );
				addLog( log, '#' + id + ': ' + res.alt_text, true );
			} catch ( e ) {
				addLog( log, '#' + id + ': ' + ( e.message || 'error' ), false );
			}
			done++;
			const pct = Math.round( ( done / total ) * 100 );
			fill.style.width = pct + '%';
			text.textContent = done + ' / ' + total;
		}

		startBtn.disabled = false;
	} );

	function addLog( log, message, ok ) {
		const li = document.createElement( 'li' );
		li.textContent = ( ok ? '✓ ' : '✗ ' ) + message;
		li.className = ok ? 'aatg-ok' : 'aatg-err';
		log.appendChild( li );
	}
} )();
