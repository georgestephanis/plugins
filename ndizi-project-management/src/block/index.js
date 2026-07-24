import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import metadata from './block.json';

/**
 * Edit component for the block.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update attributes.
 * @return {Element} Element to render.
 */
const Edit = ( { attributes, setAttributes } ) => {
	const {
		enableTaskSubmission,
		enableTimeOff,
		showTasks,
		showInvoices,
		showDiscussion,
	} = attributes;

	const blockProps = useBlockProps( {
		style: {
			padding: '35px',
			borderRadius: '12px',
			textAlign: 'center',
			fontFamily: 'sans-serif',
			boxShadow: '0 4px 15px rgba(0,0,0,0.1)',
			border: '1px solid #cbd5e1',
		},
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __(
						'Dashboard Features',
						'ndizi-project-management'
					) }
					initialOpen={ true }
				>
					<ToggleControl
						label={ __(
							'Allow task submission',
							'ndizi-project-management'
						) }
						help={ __(
							'When off, clients can view their tasks but not submit new ones.',
							'ndizi-project-management'
						) }
						checked={ enableTaskSubmission }
						onChange={ ( value ) =>
							setAttributes( { enableTaskSubmission: value } )
						}
					/>
					<ToggleControl
						label={ __(
							'Allow out-of-office requests',
							'ndizi-project-management'
						) }
						help={ __(
							'Lets clients log upcoming out-of-office windows so your team can plan around them.',
							'ndizi-project-management'
						) }
						checked={ enableTimeOff }
						onChange={ ( value ) =>
							setAttributes( { enableTimeOff: value } )
						}
					/>
				</PanelBody>
				<PanelBody
					title={ __(
						'Dashboard Sections',
						'ndizi-project-management'
					) }
					initialOpen={ true }
				>
					<ToggleControl
						label={ __( 'Show tasks', 'ndizi-project-management' ) }
						checked={ showTasks }
						onChange={ ( value ) =>
							setAttributes( { showTasks: value } )
						}
					/>
					<ToggleControl
						label={ __(
							'Show invoices',
							'ndizi-project-management'
						) }
						checked={ showInvoices }
						onChange={ ( value ) =>
							setAttributes( { showInvoices: value } )
						}
					/>
					<ToggleControl
						label={ __(
							'Show discussion',
							'ndizi-project-management'
						) }
						checked={ showDiscussion }
						onChange={ ( value ) =>
							setAttributes( { showDiscussion: value } )
						}
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<h3
					style={ {
						margin: '0 0 10px 0',
						fontSize: '22px',
						fontWeight: 'bold',
					} }
				>
					{ __( 'Ndizi Client Portal', 'ndizi-project-management' ) }
				</h3>
				<p
					style={ {
						margin: '0 0 15px 0',
						fontSize: '14px',
						opacity: 0.9,
					} }
				>
					{ __(
						'This block renders the interactive client portal. Background, text, link, and button colors are set from the Styles panel.',
						'ndizi-project-management'
					) }
				</p>
				<div
					style={ {
						display: 'flex',
						alignItems: 'center',
						justifyContent: 'center',
						gap: '20px',
					} }
				>
					<button
						type="button"
						className="wp-element-button"
						style={ {
							padding: '8px 18px',
							borderRadius: '6px',
							fontWeight: '600',
							cursor: 'default',
						} }
					>
						{ __( 'Action Button', 'ndizi-project-management' ) }
					</button>
					<a
						href="#portal-preview"
						onClick={ ( event ) => event.preventDefault() }
						style={ { fontWeight: '600', fontSize: '14px' } }
					>
						{ __(
							'Sample Link Accent',
							'ndizi-project-management'
						) }
					</a>
				</div>
			</div>
		</>
	);
};

// Pre-Styles-panel version: flat hex color attributes instead of native
// color support. Migrates existing blocks so previously-picked colors carry
// over into the new style.color / style.elements shape.
const deprecated = [
	{
		attributes: {
			backgroundColor: { type: 'string', default: '#f8fafc' },
			textColor: { type: 'string', default: '#0f172a' },
			buttonColor: { type: 'string', default: '#3A1A4D' },
			linkColor: { type: 'string', default: '#7B4B9E' },
			enableTaskSubmission: { type: 'boolean', default: true },
			enableTimeOff: { type: 'boolean', default: false },
			showTasks: { type: 'boolean', default: true },
			showInvoices: { type: 'boolean', default: true },
			showDiscussion: { type: 'boolean', default: true },
		},
		save() {
			return null;
		},
		migrate( attributes ) {
			const {
				backgroundColor,
				textColor,
				buttonColor,
				linkColor,
				...rest
			} = attributes;
			return {
				...rest,
				style: {
					color: {
						background: backgroundColor,
						text: textColor,
					},
					elements: {
						link: { color: { text: linkColor } },
						button: { color: { background: buttonColor } },
					},
				},
			};
		},
	},
];

registerBlockType( metadata, {
	edit: Edit,
	save() {
		return null;
	},
	deprecated,
} );
