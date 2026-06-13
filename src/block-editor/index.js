/**
 * Block editor integration — adds a "Generate with AI" button to the core image
 * block's settings sidebar, right next to the native Alternative Text field.
 *
 * Pattern: hook the 'editor.BlockEdit' filter with a Higher-Order Component (HOC)
 * that wraps every block's edit UI. We only add our controls when the block is a
 * core/image, so it never touches other blocks.
 */

import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, Button, Spinner, Notice } from '@wordpress/components';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/**
 * The custom panel shown inside the image block's sidebar.
 *
 * @param {Object}   props
 * @param {Object}   props.attributes    The block's attributes (id, url, alt...).
 * @param {Function} props.setAttributes Updates the block's attributes.
 */
function AltTextGenerator( { attributes, setAttributes } ) {
	const [ loading, setLoading ] = useState( false );
	const [ error, setError ] = useState( '' );

	const { id, url } = attributes;

	const handleGenerate = async () => {
		setLoading( true );
		setError( '' );

		// Prefer the attachment id (server reads the local file — fast, reliable).
		// Fall back to the URL for external images that have no attachment id.
		const data = id ? { image_id: id } : { image_url: url };

		try {
			const res = await apiFetch( {
				path: '/insag/v1/generate',
				method: 'POST',
				data,
			} );
			setAttributes( { alt: res.alt_text } );
		} catch ( e ) {
			setError( e && e.message ? e.message : __( 'Generation failed.', 'internick-smart-alt-generator' ) );
		} finally {
			setLoading( false );
		}
	};

	return (
		<PanelBody title={ __( 'AI Alt Text', 'internick-smart-alt-generator' ) } initialOpen={ true }>
			<Button
				variant="primary"
				onClick={ handleGenerate }
				disabled={ loading || ( ! id && ! url ) }
				style={ { marginBottom: '8px' } }
			>
				{ loading ? (
					<>
						<Spinner />
						{ __( 'Generating…', 'internick-smart-alt-generator' ) }
					</>
				) : (
					__( '⚡ Generate with AI', 'internick-smart-alt-generator' )
				) }
			</Button>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }
		</PanelBody>
	);
}

/**
 * HOC: wraps each block's edit component. For a SELECTED core/image block it
 * appends our panel into the InspectorControls (block settings sidebar).
 *
 * The `isSelected` guard matters: InspectorControls only attach to the sidebar
 * for the active block, so rendering them only when selected avoids the panel
 * being mounted in a detached context where it never shows.
 */
const withAltTextGenerator = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		if ( props.name !== 'core/image' ) {
			return <BlockEdit { ...props } />;
		}

		return (
			<>
				<BlockEdit { ...props } />
				{ props.isSelected && (
					<InspectorControls group="content">
						<AltTextGenerator
							attributes={ props.attributes }
							setAttributes={ props.setAttributes }
						/>
					</InspectorControls>
				) }
			</>
		);
	};
}, 'withAltTextGenerator' );

addFilter(
	'editor.BlockEdit',
	'internick-smart-alt-generator/with-image-controls',
	withAltTextGenerator
);
