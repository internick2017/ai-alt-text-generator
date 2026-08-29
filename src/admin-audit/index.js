import { createRoot, useState, useCallback, useEffect } from '@wordpress/element';
import { Button, TextControl, Spinner } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const { restBase = '', nonce = '' } = window.insagAuditData ?? {};
if ( nonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( nonce ) );
}

const FLAG_LABELS = {
	missing: __( 'Missing', 'internick-smart-alt-generator' ),
	empty: __( 'Empty', 'internick-smart-alt-generator' ),
	duplicate: __( 'Duplicate', 'internick-smart-alt-generator' ),
	too_long: __( 'Too long', 'internick-smart-alt-generator' ),
	placeholder: __( 'Placeholder', 'internick-smart-alt-generator' ),
};
const FLAG_ORDER = [ 'missing', 'empty', 'duplicate', 'too_long', 'placeholder' ];

/** Big number + progress bar summarizing library alt-text health. */
function ScoreCard( { score, total } ) {
	const color = score >= 80 ? '#00a32a' : score >= 50 ? '#dba617' : '#d63638';
	return (
		<div style={ { background: '#fff', border: '1px solid #c3c4c7', borderRadius: '4px', padding: '16px 20px', marginBottom: '16px' } }>
			<div style={ { display: 'flex', alignItems: 'baseline', gap: '8px' } }>
				<span style={ { fontSize: '40px', fontWeight: 700, color, lineHeight: 1 } }>{ score }</span>
				<span style={ { fontSize: '16px', color: '#646970' } }>/ 100</span>
				<span style={ { marginLeft: 'auto', fontSize: '12px', color: '#646970' } }>
					{ total } { __( 'images', 'internick-smart-alt-generator' ) }
				</span>
			</div>
			<div style={ { background: '#e0e0e0', borderRadius: '4px', height: '8px', overflow: 'hidden', marginTop: '10px' } }>
				<div style={ { background: color, height: '100%', width: `${ score }%`, transition: 'width .3s' } } />
			</div>
		</div>
	);
}

/** Clickable per-check counters that filter the table. */
function Chips( { counts, active, onPick } ) {
	return (
		<div style={ { display: 'flex', gap: '8px', flexWrap: 'wrap', marginBottom: '16px' } }>
			{ FLAG_ORDER.map( ( flag ) => {
				const isActive = active === flag;
				return (
					<button
						key={ flag }
						onClick={ () => onPick( isActive ? null : flag ) }
						style={ {
							cursor: 'pointer',
							border: `1px solid ${ isActive ? '#2271b1' : '#c3c4c7' }`,
							background: isActive ? '#2271b1' : '#fff',
							color: isActive ? '#fff' : '#1d2327',
							borderRadius: '4px',
							padding: '6px 12px',
							fontSize: '13px',
						} }
					>
						{ FLAG_LABELS[ flag ] } <strong>{ counts[ flag ] ?? 0 }</strong>
					</button>
				);
			} ) }
		</div>
	);
}

/** One problem row: thumbnail, badges, current alt, and the three actions. */
function Row( { item, onDone } ) {
	const [ alt, setAlt ] = useState( item.alt );
	const [ busy, setBusy ] = useState( false );
	const [ note, setNote ] = useState( '' );

	const generate = useCallback( async () => {
		setBusy( true );
		setNote( '' );
		try {
			const res = await apiFetch( { path: '/insag/v1/generate', method: 'POST', data: { image_id: item.id } } );
			setAlt( res.alt_text );
			setNote( __( 'Generated ✓', 'internick-smart-alt-generator' ) );
			onDone( item.id );
		} catch ( e ) {
			setNote( e?.message || __( 'Failed', 'internick-smart-alt-generator' ) );
		}
		setBusy( false );
	}, [ item.id, onDone ] );

	const save = useCallback( async () => {
		setBusy( true );
		setNote( '' );
		try {
			await apiFetch( { path: '/insag/v1/audit/set-alt', method: 'POST', data: { image_id: item.id, alt } } );
			setNote( __( 'Saved ✓', 'internick-smart-alt-generator' ) );
			onDone( item.id );
		} catch ( e ) {
			setNote( e?.message || __( 'Failed', 'internick-smart-alt-generator' ) );
		}
		setBusy( false );
	}, [ item.id, alt, onDone ] );

	const dismiss = useCallback( async () => {
		setBusy( true );
		try {
			await apiFetch( { path: '/insag/v1/audit/dismiss', method: 'POST', data: { image_id: item.id, dismissed: true } } );
			onDone( item.id );
		} catch ( e ) {
			setNote( e?.message || __( 'Failed', 'internick-smart-alt-generator' ) );
			setBusy( false );
		}
	}, [ item.id, onDone ] );

	return (
		<div style={ { display: 'flex', gap: '12px', padding: '12px', borderBottom: '1px solid #f0f0f1', alignItems: 'flex-start' } }>
			<img
				src={ item.thumb }
				alt=""
				width={ 60 }
				height={ 60 }
				style={ { objectFit: 'cover', borderRadius: '4px', flexShrink: 0, background: '#f0f0f1' } }
			/>
			<div style={ { flex: 1 } }>
				<div style={ { display: 'flex', gap: '4px', marginBottom: '6px', flexWrap: 'wrap' } }>
					{ item.flags.map( ( f ) => (
						<span key={ f } style={ { fontSize: '11px', background: '#fcf0f1', color: '#d63638', border: '1px solid #f0c3c5', borderRadius: '3px', padding: '1px 6px' } }>
							{ FLAG_LABELS[ f ] }
						</span>
					) ) }
					<span style={ { marginLeft: 'auto', fontSize: '10px', color: '#c3c4c7' } }>#{ item.id }</span>
				</div>
				<TextControl
					value={ alt }
					onChange={ setAlt }
					placeholder={ __( 'Alt text…', 'internick-smart-alt-generator' ) }
					__nextHasNoMarginBottom
				/>
				<div style={ { display: 'flex', gap: '8px', alignItems: 'center', marginTop: '6px' } }>
					<Button variant="primary" onClick={ generate } disabled={ busy } isSmall>
						{ __( 'Generate with AI', 'internick-smart-alt-generator' ) }
					</Button>
					<Button variant="secondary" onClick={ save } disabled={ busy } isSmall>
						{ __( 'Save', 'internick-smart-alt-generator' ) }
					</Button>
					<Button variant="tertiary" onClick={ dismiss } disabled={ busy } isSmall>
						{ __( 'Ignore', 'internick-smart-alt-generator' ) }
					</Button>
					{ busy && <Spinner /> }
					{ note && <span style={ { fontSize: '12px', color: '#646970' } }>{ note }</span> }
				</div>
			</div>
		</div>
	);
}

/** Root: runs the paginated scan on mount, renders score, chips, and the table. */
function AuditApp() {
	const [ status, setStatus ] = useState( 'idle' ); // idle | scanning | done
	const [ progress, setProgress ] = useState( { page: 0, totalPages: 0 } );
	const [ score, setScore ] = useState( 100 );
	const [ total, setTotal ] = useState( 0 );
	const [ counts, setCounts ] = useState( {} );
	const [ items, setItems ] = useState( [] );
	const [ filter, setFilter ] = useState( null );
	const [ resolved, setResolved ] = useState( () => new Set() );
	const [ bulkBusy, setBulkBusy ] = useState( false );
	const [ bulkProgress, setBulkProgress ] = useState( { done: 0, total: 0 } );

	const runScan = useCallback( async () => {
		setStatus( 'scanning' );
		setItems( [] );
		setResolved( new Set() );
		let page = 1;
		let totalPages = 1;
		const collected = [];
		do {
			const res = await apiFetch( { path: `/insag/v1/audit/scan?page=${ page }` } );
			totalPages = res.total_pages || 1;
			setProgress( { page, totalPages } );
			setScore( res.score );
			setTotal( res.total );
			setCounts( res.counts );
			collected.push( ...res.items );
			setItems( [ ...collected ] );
			page++;
		} while ( page <= totalPages );
		setStatus( 'done' );
	}, [] );

	useEffect( () => {
		runScan();
	}, [ runScan ] );

	const markResolved = useCallback( ( id ) => {
		setResolved( ( prev ) => new Set( prev ).add( id ) );
	}, [] );

	const visible = items.filter(
		( it ) => ! resolved.has( it.id ) && ( ! filter || it.flags.includes( filter ) )
	);

	// Generate AI alt text for every currently-shown (filtered) image, one at a
	// time with progress. Reuses the existing /generate endpoint; each call is a
	// paid OpenAI request, so we confirm the count first.
	const generateAllShown = useCallback( async () => {
		const targets = visible.slice();
		if ( targets.length === 0 ) {
			return;
		}
		// eslint-disable-next-line no-alert
		if ( ! window.confirm(
			sprintf(
				// translators: %d: number of images that will be sent to the AI.
				__( 'Generate AI alt text for %d image(s)? Each one is a paid OpenAI request.', 'internick-smart-alt-generator' ),
				targets.length
			)
		) ) {
			return;
		}
		setBulkBusy( true );
		setBulkProgress( { done: 0, total: targets.length } );
		for ( let i = 0; i < targets.length; i++ ) {
			try {
				await apiFetch( { path: '/insag/v1/generate', method: 'POST', data: { image_id: targets[ i ].id } } );
				markResolved( targets[ i ].id );
			} catch ( e ) {
				// Leave the image in the list on failure so it stays visible.
			}
			setBulkProgress( { done: i + 1, total: targets.length } );
		}
		setBulkBusy( false );
	}, [ visible, markResolved ] );

	return (
		<div style={ { maxWidth: '820px', paddingTop: '16px' } }>
			<div style={ { display: 'flex', alignItems: 'center', gap: '12px' } }>
				<h1 style={ { margin: 0 } }>{ __( 'Alt Text Audit', 'internick-smart-alt-generator' ) }</h1>
				<div style={ { marginLeft: 'auto', display: 'flex', gap: '8px' } }>
					<Button
						variant="primary"
						onClick={ generateAllShown }
						disabled={ bulkBusy || status === 'scanning' || visible.length === 0 }
					>
						{ bulkBusy
							? sprintf(
								// translators: %1$d: images already generated. %2$d: total images in this run.
								__( 'Generating %1$d/%2$d…', 'internick-smart-alt-generator' ),
								bulkProgress.done,
								bulkProgress.total
							)
							: sprintf(
								// translators: %d: number of images currently listed on screen.
								__( 'Generate all shown (%d)', 'internick-smart-alt-generator' ),
								visible.length
							) }
					</Button>
					<Button variant="secondary" onClick={ runScan } disabled={ status === 'scanning' || bulkBusy }>
						{ __( 'Re-scan', 'internick-smart-alt-generator' ) }
					</Button>
				</div>
			</div>

			{ status === 'scanning' && (
				<p style={ { color: '#646970' } }>
					<Spinner /> { __( 'Scanning…', 'internick-smart-alt-generator' ) } { progress.page }/{ progress.totalPages }
				</p>
			) }

			<ScoreCard score={ score } total={ total } />
			<Chips counts={ counts } active={ filter } onPick={ setFilter } />

			<div style={ { background: '#fff', border: '1px solid #c3c4c7', borderRadius: '4px', overflow: 'hidden' } }>
				{ visible.length === 0 ? (
					<p style={ { padding: '20px', textAlign: 'center', color: '#646970' } }>
						{ status === 'done'
							? __( 'No problems here. Nice work!', 'internick-smart-alt-generator' )
							: __( 'Loading…', 'internick-smart-alt-generator' ) }
					</p>
				) : (
					visible.map( ( it ) => <Row key={ it.id } item={ it } onDone={ markResolved } /> )
				) }
			</div>
		</div>
	);
}

const root = document.getElementById( 'insag-audit-root' );
if ( root ) {
	createRoot( root ).render( <AuditApp /> );
}
