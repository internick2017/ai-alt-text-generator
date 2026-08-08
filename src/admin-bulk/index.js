import { createRoot, useState, useCallback, useRef } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const { total = 0 } = window.insagBulkData ?? {};

/** 4 stat boxes: total / completed / errors / remaining. */
function StatBar( { total, successes, errors } ) {
	const remaining = total - successes - errors;
	const stats = [
		{ label: __( 'Total', 'internick-smart-alt-generator' ), value: total, color: '#1d2327' },
		{ label: __( 'Completed', 'internick-smart-alt-generator' ), value: successes, color: '#00a32a' },
		{ label: __( 'Errors', 'internick-smart-alt-generator' ), value: errors, color: '#d63638' },
		{ label: __( 'Remaining', 'internick-smart-alt-generator' ), value: remaining, color: '#2271b1' },
	];
	return (
		<div style={ { display: 'flex', gap: '12px', marginBottom: '16px' } }>
			{ stats.map( ( s ) => (
				<div
					key={ s.label }
					style={ {
						background: '#fff',
						border: '1px solid #c3c4c7',
						borderRadius: '4px',
						padding: '12px 16px',
						flex: 1,
						textAlign: 'center',
					} }
				>
					<div style={ { fontSize: '26px', fontWeight: 700, color: s.color, lineHeight: 1 } }>
						{ s.value }
					</div>
					<div style={ { fontSize: '11px', color: '#646970', textTransform: 'uppercase', letterSpacing: '.3px', marginTop: '4px' } }>
						{ s.label }
					</div>
				</div>
			) ) }
		</div>
	);
}

/** Progress bar + percentage + N/total counter. Pure display component. */
function ProgressBar( { processed, total } ) {
	const pct = total > 0 ? Math.round( ( processed / total ) * 100 ) : 0;
	return (
		<div style={ { background: '#fff', border: '1px solid #c3c4c7', borderRadius: '4px', padding: '12px 16px', marginBottom: '12px' } }>
			<div style={ { background: '#e0e0e0', borderRadius: '4px', height: '10px', overflow: 'hidden' } }>
				<div
					style={ {
						background: '#2271b1',
						height: '100%',
						width: `${ pct }%`,
						borderRadius: '4px',
						transition: 'width .3s',
					} }
				/>
			</div>
			<div style={ { display: 'flex', justifyContent: 'space-between', marginTop: '6px' } }>
				<span style={ { fontSize: '12px', fontWeight: 600, color: '#2271b1' } }>{ pct }%</span>
				<span style={ { fontSize: '11px', color: '#646970' } }>{ processed } / { total }</span>
			</div>
		</div>
	);
}

/** Single button whose label changes based on current status. */
function BulkControls( { status, onStart, onPause, onResume } ) {
	if ( status === 'idle' ) {
		return (
			<Button variant="primary" onClick={ onStart }>
				{ __( 'Generate All', 'internick-smart-alt-generator' ) }
			</Button>
		);
	}
	if ( status === 'running' ) {
		return (
			<Button variant="secondary" onClick={ onPause }>
				{ __( '⏸ Pause', 'internick-smart-alt-generator' ) }
			</Button>
		);
	}
	if ( status === 'paused' ) {
		return (
			<Button variant="primary" onClick={ onResume }>
				{ __( '▶ Resume', 'internick-smart-alt-generator' ) }
			</Button>
		);
	}
	if ( status === 'error' ) {
		return (
			<Button variant="primary" onClick={ onResume }>
				{ __( 'Retry', 'internick-smart-alt-generator' ) }
			</Button>
		);
	}
	// done
	return (
		<Button variant="secondary" disabled>
			{ __( '✓ All done', 'internick-smart-alt-generator' ) }
		</Button>
	);
}

/** Scrollable result log — most recent item at top. */
function LogList( { log, onClear } ) {
	if ( log.length === 0 ) {
		return null;
	}
	return (
		<div style={ { background: '#fff', border: '1px solid #c3c4c7', borderRadius: '4px', overflow: 'hidden' } }>
			<div style={ { padding: '8px 12px', borderBottom: '1px solid #f0f0f1', display: 'flex', justifyContent: 'space-between', alignItems: 'center' } }>
				<span style={ { fontSize: '11px', fontWeight: 600, color: '#646970', textTransform: 'uppercase', letterSpacing: '.3px' } }>
					{ __( 'Results', 'internick-smart-alt-generator' ) }
				</span>
				<button
					onClick={ onClear }
					style={ { fontSize: '11px', color: '#2271b1', background: 'none', border: 'none', cursor: 'pointer', padding: 0 } }
				>
					{ __( 'Clear log', 'internick-smart-alt-generator' ) }
				</button>
			</div>
			<div style={ { maxHeight: '240px', overflowY: 'auto' } }>
				{ log.map( ( item, i ) => (
					<div
						key={ i }
						style={ { display: 'flex', gap: '8px', padding: '6px 12px', borderBottom: '1px solid #f9f9f9', fontSize: '12px' } }
					>
						<span style={ { color: item.ok ? '#00a32a' : '#d63638', flexShrink: 0 } }>
							{ item.ok ? '✓' : '✗' }
						</span>
						<span style={ { flex: 1, color: item.ok ? '#1d2327' : '#d63638' } }>
							{ item.text }
						</span>
						<span style={ { color: '#c3c4c7', fontSize: '10px' } }>#{ item.id }</span>
					</div>
				) ) }
			</div>
		</div>
	);
}

/** Root component — manages the generation loop state machine. */
function BulkApp() {
	const [ status, setStatus ] = useState( 'idle' ); // idle | running | paused | error | done
	const [ successes, setSuccesses ] = useState( 0 );
	const [ errors, setErrors ] = useState( 0 );
	const [ processed, setProcessed ] = useState( 0 );
	const [ log, setLog ] = useState( [] );
	const pausedRef = useRef( false );
	const runningRef = useRef( false ); // guards against a second concurrent processQueue
	const attemptedRef = useRef( new Set() );
	const queueRef = useRef( [] ); // remaining IDs of the current batch
	const stuckRef = useRef( 0 ); // count of failed generations; they stay in the /bulk/scan result set as a prefix

	const addLog = useCallback( ( id, ok, text ) => {
		setLog( ( prev ) => [ { id, ok, text }, ...prev ] );
	}, [] );

	const processQueue = useCallback( async () => {
		if ( runningRef.current ) {
			return;
		}
		runningRef.current = true;
		try {
			while ( true ) {
				while ( queueRef.current.length > 0 ) {
					if ( pausedRef.current ) {
						return;
					}
					const id = queueRef.current.shift();
					attemptedRef.current.add( id );
					try {
						const res = await apiFetch( {
							path: '/insag/v1/generate',
							method: 'POST',
							data: { image_id: id },
						} );
						// An "empty success" would keep the image in the /bulk/scan
						// result set forever while the loop counted it as done,
						// desyncing the prefix count. Treat it as a failure.
						const altText = ( res?.alt_text || '' ).trim();
						if ( '' === altText ) {
							addLog( id, false, __( 'The AI returned an empty alt text.', 'internick-smart-alt-generator' ) );
							setErrors( ( n ) => n + 1 );
							stuckRef.current += 1;
						} else {
							addLog( id, true, altText );
							setSuccesses( ( s ) => s + 1 );
						}
					} catch ( e ) {
						addLog( id, false, e?.message || __( 'Generation failed.', 'internick-smart-alt-generator' ) );
						setErrors( ( n ) => n + 1 );
						stuckRef.current += 1;
					}
					setProcessed( ( p ) => p + 1 );
				}
				// Failed images keep missing alt text, so they never leave the /bulk/scan
				// result set — they pile up as a prefix (ID-ascending). The first
				// unattempted image therefore sits at 0-indexed position = stuckRef.current,
				// i.e. on page floor(stuckRef.current / 100) + 1.
				const page = Math.floor( stuckRef.current / 100 ) + 1;
				let scan;
				try {
					scan = await apiFetch( { path: `/insag/v1/bulk/scan?page=${ page }` } );
				} catch ( e ) {
					addLog( 0, false, __( 'Could not load the next batch. Check your connection and press Retry.', 'internick-smart-alt-generator' ) );
					setErrors( ( n ) => n + 1 );
					setStatus( 'error' );
					return;
				}
				const fresh = ( scan?.ids || [] ).filter( ( id ) => ! attemptedRef.current.has( id ) );
				if ( fresh.length === 0 ) {
					// Safety net: the prefix count can drift above the real prefix
					// (a /generate that succeeded server-side but failed client-side,
					// or a failed image deleted mid-run), which would park the page
					// window past the frontier and strand a block of images. Before
					// declaring completion, re-check page 1 once. attemptedRef filters
					// out everything already tried, so this can never ping-pong.
					if ( page > 1 ) {
						let rescan;
						try {
							rescan = await apiFetch( { path: '/insag/v1/bulk/scan?page=1' } );
						} catch ( e ) {
							addLog( 0, false, __( 'Could not load the next batch. Check your connection and press Retry.', 'internick-smart-alt-generator' ) );
							setErrors( ( n ) => n + 1 );
							setStatus( 'error' );
							return;
						}
						const recovered = ( rescan?.ids || [] ).filter( ( id ) => ! attemptedRef.current.has( id ) );
						if ( recovered.length > 0 ) {
							queueRef.current = recovered;
							continue;
						}
					}
					setStatus( 'done' );
					return;
				}
				queueRef.current = fresh;
			}
		} finally {
			runningRef.current = false;
		}
	}, [ addLog ] );

	const handleStart = useCallback( () => {
		if ( runningRef.current ) {
			return;
		}
		if (
			total > 100 &&
			// translators: %d: number of images that will be sent to the AI provider.
			! window.confirm( sprintf( __( 'This will generate alt text for %d images using your API key. Continue?', 'internick-smart-alt-generator' ), total ) )
		) {
			return;
		}
		pausedRef.current = false;
		attemptedRef.current = new Set();
		queueRef.current = [];
		stuckRef.current = 0;
		setSuccesses( 0 );
		setErrors( 0 );
		setProcessed( 0 );
		setStatus( 'running' );
		processQueue();
	}, [ processQueue ] );

	const handlePause = useCallback( () => {
		pausedRef.current = true;
		setStatus( 'paused' );
	}, [] );

	// Also used to Retry after a scan failure: resumes with attemptedRef/counters
	// intact so already-generated images are never regenerated/repaid for.
	const handleResume = useCallback( () => {
		if ( runningRef.current ) {
			return;
		}
		pausedRef.current = false;
		setStatus( 'running' );
		processQueue();
	}, [ processQueue ] );

	if ( total === 0 ) {
		return (
			<div style={ { paddingTop: '16px' } }>
				<h1>{ __( 'Bulk Alt Text Generator', 'internick-smart-alt-generator' ) }</h1>
				<p>{ __( 'All your images already have alt text.', 'internick-smart-alt-generator' ) }</p>
			</div>
		);
	}

	return (
		<div style={ { maxWidth: '760px', paddingTop: '16px' } }>
			<h1>{ __( 'Bulk Alt Text Generator', 'internick-smart-alt-generator' ) }</h1>
			<StatBar total={ total } successes={ successes } errors={ errors } />
			{ status !== 'idle' && <ProgressBar processed={ processed } total={ total } /> }
			<div style={ { marginBottom: '16px' } }>
				<BulkControls
					status={ status }
					onStart={ handleStart }
					onPause={ handlePause }
					onResume={ handleResume }
				/>
			</div>
			<LogList log={ log } onClear={ () => setLog( [] ) } />
		</div>
	);
}

const root = document.getElementById( 'insag-bulk-root' );
if ( root ) {
	createRoot( root ).render( <BulkApp /> );
}
