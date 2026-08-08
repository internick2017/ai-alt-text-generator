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
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const { hasConnector = false, connectorIds = [] } = window.insagSettingsData ?? {};

/** Capitalizes a provider id like 'anthropic' into a display name like 'Anthropic'. */
function providerLabel( id ) {
	return id.charAt( 0 ).toUpperCase() + id.slice( 1 );
}

/** WP 7.0+ notice — shown only when at least one AI Connector is configured. */
function ConnectorsNotice() {
	const names = connectorIds.map( providerLabel ).join( ', ' );
	const single = connectorIds.length === 1;
	return (
		<div className="notice notice-success inline" style={ { marginTop: 0 } }>
			<p>
				{ single
					? sprintf(
						/* translators: %s: the connected AI provider's name, e.g. "Anthropic". */
						__( 'Generating alt text via your WordPress AI Connector: %s.', 'internick-smart-alt-generator' ),
						names
					)
					: sprintf(
						/* translators: %s: comma-separated list of connected AI providers, e.g. "Anthropic, Google". */
						__(
							'Generating alt text via WordPress AI Connectors. Connected: %s — WordPress picks which one answers each request automatically; this plugin does not choose or know in advance.',
							'internick-smart-alt-generator'
						),
						names
					) }
			</p>
		</div>
	);
}

/** Shown when no AI Connector is active — explains the current backend and the alternative. */
function DirectApiNotice() {
	return (
		<div className="notice notice-info inline" style={ { marginTop: 0 } }>
			<p>
				{ __(
					'Currently generating alt text by calling OpenAI directly with the API key below.',
					'internick-smart-alt-generator'
				) }
				{ ' ' }
				{ __(
					'Running WordPress 7.0 or newer? You can connect a different AI provider (such as Anthropic or Google) in your site’s AI settings — once configured, this plugin switches to it automatically, no key or code change needed here.',
					'internick-smart-alt-generator'
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
			await apiFetch( { path: '/insag/v1/test' } );
			setStatus( 'ok' );
			setMessage( __( 'Connection successful', 'internick-smart-alt-generator' ) );
		} catch ( e ) {
			setStatus( 'error' );
			setMessage( e?.message || __( 'Connection failed', 'internick-smart-alt-generator' ) );
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
				{ __( 'Test Connection', 'internick-smart-alt-generator' ) }
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
				<strong>{ __( 'AI Provider', 'internick-smart-alt-generator' ) }</strong>
			</CardHeader>
			<CardBody>
				<TextControl
					label={ __( 'OpenAI API Key', 'internick-smart-alt-generator' ) }
					type="password"
					value={ settings.insag_openai_api_key }
					onChange={ ( v ) => onChange( 'insag_openai_api_key', v ) }
					help={
						<>
							{ __( "Don't have a key? ", 'internick-smart-alt-generator' ) }
							<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">
								{ __( 'Get one at platform.openai.com', 'internick-smart-alt-generator' ) }
							</a>
							<br />
							{ __( "Billed separately from ChatGPT Plus, as pay-per-use credits — ", 'internick-smart-alt-generator' ) }
							<a href="https://platform.openai.com/settings/organization/billing/overview" target="_blank" rel="noopener noreferrer">
								{ __( 'check your OpenAI credit balance', 'internick-smart-alt-generator' ) }
							</a>
						</>
					}
					autoComplete="off"
				/>
				<SelectControl
					label={ __( 'Model', 'internick-smart-alt-generator' ) }
					value={ settings.insag_model }
					options={ [
						{ label: 'gpt-4o-mini — Fastest, cheapest (recommended)', value: 'gpt-4o-mini' },
						{ label: 'gpt-4o — Highest quality', value: 'gpt-4o' },
					] }
					onChange={ ( v ) => onChange( 'insag_model', v ) }
				/>
				<TestConnectionButton apiKey={ settings.insag_openai_api_key } />
			</CardBody>
		</Card>
	);
}

/** Generation settings card — auto-generate toggle + language. */
function GenerationCard( { settings, onChange } ) {
	return (
		<Card style={ { marginBottom: '16px' } }>
			<CardHeader>
				<strong>{ __( 'Generation', 'internick-smart-alt-generator' ) }</strong>
			</CardHeader>
			<CardBody>
				<ToggleControl
					label={ __( 'Auto-generate on upload', 'internick-smart-alt-generator' ) }
					help={ __(
						'Automatically generate alt text when a new image is uploaded to the Media Library.',
						'internick-smart-alt-generator'
					) }
					checked={ settings.insag_auto_generate }
					onChange={ ( v ) => onChange( 'insag_auto_generate', v ) }
				/>
				<TextControl
					label={ __( 'Language', 'internick-smart-alt-generator' ) }
					value={ settings.insag_language }
					onChange={ ( v ) => onChange( 'insag_language', v ) }
					help={ __(
						'Use "auto" to match the site language, or enter a language name (e.g. "Spanish").',
						'internick-smart-alt-generator'
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
						✓ { __( 'Settings saved', 'internick-smart-alt-generator' ) }
					</span>
				) }
				{ saveStatus === 'error' && (
					<span style={ { color: '#d63638', marginRight: '12px' } }>
						✗ { __( 'Save failed. Please try again.', 'internick-smart-alt-generator' ) }
					</span>
				) }
				<Button
					variant="primary"
					onClick={ onSave }
					disabled={ saveStatus === 'saving' }
				>
					{ saveStatus === 'saving'
						? __( 'Saving…', 'internick-smart-alt-generator' )
						: __( 'Save Settings', 'internick-smart-alt-generator' ) }
				</Button>
			</CardFooter>
		</Card>
	);
}

const DEFAULT_SETTINGS = {
	insag_openai_api_key: '',
	insag_model: 'gpt-4o-mini',
	insag_language: 'auto',
	insag_auto_generate: false,
};

/** Root component — loads settings from REST, handles save. */
function SettingsApp() {
	const [ settings, setSettings ] = useState( DEFAULT_SETTINGS );
	const [ loading, setLoading ] = useState( true );
	const [ saveStatus, setSaveStatus ] = useState( null ); // null | saving | saved | error

	useEffect( () => {
		apiFetch( { path: '/insag/v1/settings' } )
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
				path: '/insag/v1/settings',
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
			<h1>{ __( 'Smart Alt Generator', 'internick-smart-alt-generator' ) }</h1>
			{ hasConnector ? <ConnectorsNotice /> : <DirectApiNotice /> }
			<ProviderCard settings={ settings } onChange={ handleChange } />
			<GenerationCard settings={ settings } onChange={ handleChange } />
			<SaveFooter onSave={ handleSave } saveStatus={ saveStatus } />
		</div>
	);
}

const root = document.getElementById( 'insag-settings-root' );
if ( root ) {
	createRoot( root ).render( <SettingsApp /> );
}
