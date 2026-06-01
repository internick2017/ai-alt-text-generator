import { createRoot, useState, useEffect, useCallback } from '@wordpress/element';
import {
	Card,
	CardHeader,
	CardBody,
	CardFooter,
	Button,
	TextControl,
	SelectControl,
	ToggleControl,
	Spinner,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const { hasConnector = false } = window.sagSettingsData ?? {};

/** WP 7.0+ notice — shown only when AI Connectors are active. */
function ConnectorsNotice() {
	return (
		<div className="notice notice-success inline" style={ { marginTop: 0 } }>
			<p>
				{ __(
					'WordPress AI Connectors detected. Using your configured AI provider.',
					'smart-alt-generator'
				) }
			</p>
		</div>
	);
}

/**
 * "⚡ Test Connection" button inside ProviderCard.
 * Resets when the API key changes.
 */
function TestConnectionButton( { apiKey } ) {
	const [ status, setStatus ] = useState( 'idle' ); // idle | testing | ok | error
	const [ message, setMessage ] = useState( '' );

	useEffect( () => {
		setStatus( 'idle' );
		setMessage( '' );
	}, [ apiKey ] );

	const handleTest = async () => {
		setStatus( 'testing' );
		try {
			await apiFetch( { path: '/smart-alt/v1/test' } );
			setStatus( 'ok' );
			setMessage( __( 'Connection successful', 'smart-alt-generator' ) );
		} catch ( e ) {
			setStatus( 'error' );
			setMessage( e?.message || __( 'Connection failed', 'smart-alt-generator' ) );
		}
	};

	return (
		<div style={ { display: 'flex', alignItems: 'center', gap: '12px', marginTop: '8px' } }>
			<Button
				variant="secondary"
				onClick={ handleTest }
				disabled={ status === 'testing' }
			>
				{ status === 'testing' ? <Spinner /> : '⚡ ' }
				{ __( 'Test Connection', 'smart-alt-generator' ) }
			</Button>
			{ status === 'ok' && (
				<span style={ { color: '#00a32a', fontSize: '13px' } }>
					✓ { message }
				</span>
			) }
			{ status === 'error' && (
				<span style={ { color: '#d63638', fontSize: '13px' } }>
					✗ { message }
				</span>
			) }
		</div>
	);
}

/** AI Provider card — hidden on WP 7.0+ when connectors handle everything. */
function ProviderCard( { settings, onChange } ) {
	if ( hasConnector ) {
		return null;
	}
	return (
		<Card style={ { marginBottom: '16px' } }>
			<CardHeader>
				<strong>{ __( 'AI Provider', 'smart-alt-generator' ) }</strong>
			</CardHeader>
			<CardBody>
				<TextControl
					label={ __( 'OpenAI API Key', 'smart-alt-generator' ) }
					type="password"
					value={ settings.sag_openai_api_key }
					onChange={ ( v ) => onChange( 'sag_openai_api_key', v ) }
					help={ __( 'Get your key at platform.openai.com', 'smart-alt-generator' ) }
					autoComplete="off"
				/>
				<SelectControl
					label={ __( 'Model', 'smart-alt-generator' ) }
					value={ settings.sag_model }
					options={ [
						{ label: 'gpt-4o-mini — Fastest, cheapest (recommended)', value: 'gpt-4o-mini' },
						{ label: 'gpt-4o — Highest quality', value: 'gpt-4o' },
					] }
					onChange={ ( v ) => onChange( 'sag_model', v ) }
				/>
				<TestConnectionButton apiKey={ settings.sag_openai_api_key } />
			</CardBody>
		</Card>
	);
}

/** Generation settings card — auto-generate toggle + language. */
function GenerationCard( { settings, onChange } ) {
	return (
		<Card style={ { marginBottom: '16px' } }>
			<CardHeader>
				<strong>{ __( 'Generation', 'smart-alt-generator' ) }</strong>
			</CardHeader>
			<CardBody>
				<ToggleControl
					label={ __( 'Auto-generate on upload', 'smart-alt-generator' ) }
					help={ __(
						'Automatically generate alt text when a new image is uploaded to the Media Library.',
						'smart-alt-generator'
					) }
					checked={ settings.sag_auto_generate }
					onChange={ ( v ) => onChange( 'sag_auto_generate', v ) }
				/>
				<TextControl
					label={ __( 'Language', 'smart-alt-generator' ) }
					value={ settings.sag_language }
					onChange={ ( v ) => onChange( 'sag_language', v ) }
					help={ __(
						'Use "auto" to match the site language, or enter a language name (e.g. "Spanish").',
						'smart-alt-generator'
					) }
				/>
			</CardBody>
		</Card>
	);
}

/** Bottom footer with Save button and inline success/error notice. */
function SaveFooter( { onSave, saveStatus } ) {
	return (
		<Card>
			<CardFooter justify="flex-end">
				{ saveStatus === 'saved' && (
					<span style={ { color: '#00a32a', marginRight: '12px' } }>
						✓ { __( 'Settings saved', 'smart-alt-generator' ) }
					</span>
				) }
				{ saveStatus === 'error' && (
					<span style={ { color: '#d63638', marginRight: '12px' } }>
						✗ { __( 'Save failed. Please try again.', 'smart-alt-generator' ) }
					</span>
				) }
				<Button
					variant="primary"
					onClick={ onSave }
					disabled={ saveStatus === 'saving' }
				>
					{ saveStatus === 'saving'
						? __( 'Saving…', 'smart-alt-generator' )
						: __( 'Save Settings', 'smart-alt-generator' ) }
				</Button>
			</CardFooter>
		</Card>
	);
}

const DEFAULT_SETTINGS = {
	sag_openai_api_key: '',
	sag_model: 'gpt-4o-mini',
	sag_language: 'auto',
	sag_auto_generate: false,
};

/** Root component — loads settings from REST, handles save. */
function SettingsApp() {
	const [ settings, setSettings ] = useState( DEFAULT_SETTINGS );
	const [ loading, setLoading ] = useState( true );
	const [ saveStatus, setSaveStatus ] = useState( null ); // null | saving | saved | error

	useEffect( () => {
		apiFetch( { path: '/smart-alt/v1/settings' } )
			.then( ( data ) => {
				setSettings( ( prev ) => ( { ...prev, ...data } ) );
				setLoading( false );
			} )
			.catch( () => setLoading( false ) );
	}, [] );

	const handleChange = useCallback( ( key, value ) => {
		setSettings( ( prev ) => ( { ...prev, [ key ]: value } ) );
		setSaveStatus( null );
	}, [] );

	const handleSave = async () => {
		setSaveStatus( 'saving' );
		try {
			await apiFetch( {
				path: '/smart-alt/v1/settings',
				method: 'POST',
				data: settings,
			} );
			setSaveStatus( 'saved' );
			setTimeout( () => setSaveStatus( null ), 3000 );
		} catch {
			setSaveStatus( 'error' );
		}
	};

	if ( loading ) {
		return <Spinner />;
	}

	return (
		<div style={ { maxWidth: '640px', paddingTop: '16px' } }>
			<h1>{ __( 'Smart Alt Generator', 'smart-alt-generator' ) }</h1>
			{ hasConnector && <ConnectorsNotice /> }
			<ProviderCard settings={ settings } onChange={ handleChange } />
			<GenerationCard settings={ settings } onChange={ handleChange } />
			<SaveFooter onSave={ handleSave } saveStatus={ saveStatus } />
		</div>
	);
}

const root = document.getElementById( 'sag-settings-root' );
if ( root ) {
	createRoot( root ).render( <SettingsApp /> );
}
