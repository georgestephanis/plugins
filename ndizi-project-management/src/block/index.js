import { registerBlockType } from '@wordpress/blocks';
import {
	useBlockProps,
	InspectorControls,
	PanelColorSettings,
} from '@wordpress/block-editor';
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
	const { backgroundColor, textColor, buttonColor, linkColor } = attributes;

	const blockProps = useBlockProps( {
		style: {
			padding: '35px',
			background: backgroundColor,
			color: textColor,
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
				<PanelColorSettings
					title={ __( 'Portal Colors', 'ndizi' ) }
					initialOpen={ true }
					colorSettings={ [
						{
							value: backgroundColor,
							onChange: ( value ) =>
								setAttributes( {
									backgroundColor: value || '#f8fafc',
								} ),
							label: __( 'Background Color', 'ndizi' ),
						},
						{
							value: textColor,
							onChange: ( value ) =>
								setAttributes( {
									textColor: value || '#0f172a',
								} ),
							label: __( 'Text Color', 'ndizi' ),
						},
						{
							value: buttonColor,
							onChange: ( value ) =>
								setAttributes( {
									buttonColor: value || '#4f46e5',
								} ),
							label: __( 'Primary Button Color', 'ndizi' ),
						},
						{
							value: linkColor,
							onChange: ( value ) =>
								setAttributes( {
									linkColor: value || '#818cf8',
								} ),
							label: __( 'Link & Accent Color', 'ndizi' ),
						},
					] }
				/>
			</InspectorControls>
			<div { ...blockProps }>
				<h3
					style={ {
						margin: '0 0 10px 0',
						fontSize: '22px',
						fontWeight: 'bold',
						color: textColor,
					} }
				>
					{ __( 'Ndizi Client Portal', 'ndizi' ) }
				</h3>
				<p
					style={ {
						margin: '0 0 15px 0',
						fontSize: '14px',
						opacity: 0.9,
					} }
				>
					{ __(
						'This block renders the interactive client portal with custom brand styles.',
						'ndizi'
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
						style={ {
							background: buttonColor,
							color: '#ffffff',
							border: 'none',
							padding: '8px 18px',
							borderRadius: '6px',
							fontWeight: '600',
							cursor: 'default',
						} }
					>
						{ __( 'Action Button', 'ndizi' ) }
					</button>
					<span
						style={ {
							color: linkColor,
							textDecoration: 'underline',
							fontWeight: '600',
							cursor: 'default',
							fontSize: '14px',
						} }
					>
						{ __( 'Sample Link Accent', 'ndizi' ) }
					</span>
				</div>
			</div>
		</>
	);
};

registerBlockType( metadata, {
	edit: Edit,
	save() {
		return null;
	},
} );
