/* eslint-disable camelcase */
// Client-facing, read-only time log for the Ndizi client portal.
//
// v1 loads *all* of the authenticated client's time entries as a single JSON
// blob (localized as `window.ndizi_portal_time_log` by Ndizi_Portal), and lets
// DataViews filter/sort/paginate them entirely in-memory via
// `filterSortAndPaginate`. No REST round-trips, so no portal-token REST auth
// surface is needed.
//
// DataViews itself is provided by the shared `ndizi-dataviews` bundle
// (src/vendor/dataviews.js → build/vendor-dataviews.js), registered in PHP.
// webpack.config.js maps `@wordpress/dataviews/wp` to that bundle's global, and
// this script's .asset.php lists `ndizi-dataviews` as a dependency, so the
// DataViews code and its stylesheet load without being part of this build.
import { render, useMemo, useState } from '@wordpress/element';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews/wp';
import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';

/* global ndizi_portal_time_log */

const TimeLogApp = () => {
	const entries = useMemo(
		() =>
			Array.isArray( ndizi_portal_time_log.entries )
				? ndizi_portal_time_log.entries
				: [],
		[]
	);

	const projectElements = useMemo(
		() =>
			( ndizi_portal_time_log.projects || [] ).map( ( p ) => ( {
				value: parseInt( p.value, 10 ),
				label: decodeEntities( p.label ),
			} ) ),
		[]
	);

	// Default to showing unbilled time when the client has any, so the log opens
	// on "what still needs invoicing" rather than the full history.
	const hasUnbilled = useMemo(
		() => entries.some( ( e ) => parseInt( e.invoiced, 10 ) === 0 ),
		[ entries ]
	);

	// View state follows the @wordpress/dataviews v16 shape: visible columns are
	// an ordered `fields` array of ids and sorting lives in `sort`.
	const [ view, setView ] = useState( {
		type: 'table',
		search: '',
		filters: hasUnbilled
			? [ { field: 'invoiced', operator: 'is', value: 0 } ]
			: [],
		page: 1,
		perPage: 20,
		sort: { field: 'start_time', direction: 'desc' },
		fields: [
			'start_time',
			'project_id',
			'description',
			'duration',
			'billable',
			'invoiced',
		],
		layout: {
			styles: {
				project_id: {
					align: 'start',
					minWidth: '180px',
					maxWidth: '300px',
				},
				description: { align: 'start', minWidth: '240px' },
				start_time: {
					align: 'start',
					minWidth: '130px',
					maxWidth: '170px',
				},
				duration: { align: 'end', width: '90px', maxWidth: '90px' },
				billable: {
					align: 'center',
					width: '110px',
					maxWidth: '110px',
				},
				invoiced: {
					align: 'center',
					width: '110px',
					maxWidth: '110px',
				},
			},
		},
	} );

	const fields = useMemo(
		() => [
			{
				id: 'start_time',
				label: __( 'Date', 'ndizi-project-management' ),
				enableSorting: true,
				filterBy: false,
				render: ( { item } ) => {
					if ( ! item.start_time ) {
						return <span>-</span>;
					}
					// Stored datetimes are MySQL UTC strings; parse as UTC so
					// toLocale*() renders the correct instant in the viewer's zone.
					const parseUTC = ( mysql ) =>
						new Date( mysql.replace( ' ', 'T' ) + 'Z' );
					const start = parseUTC( item.start_time );
					return (
						<div>
							<div>{ start.toLocaleDateString() }</div>
							<div
								style={ {
									color: '#64748b',
									fontSize: '0.9em',
								} }
							>
								{ start.toLocaleTimeString( [], {
									hour: 'numeric',
									minute: '2-digit',
								} ) }
							</div>
						</div>
					);
				},
			},
			{
				id: 'project_id',
				label: __( 'Project / Task', 'ndizi-project-management' ),
				type: 'integer',
				elements: projectElements,
				filterBy: { operators: [ 'is' ] },
				enableSorting: false,
				getValue: ( { item } ) => parseInt( item.project_id, 10 ),
				render: ( { item } ) => (
					<div>
						<strong>
							{ item.project_name ||
								( item.project_id
									? `#${ item.project_id }`
									: __( '—', 'ndizi-project-management' ) ) }
						</strong>
						{ item.task_name && (
							<div
								style={ {
									color: '#64748b',
									fontSize: '0.9em',
								} }
							>
								{ item.task_name }
							</div>
						) }
					</div>
				),
			},
			{
				id: 'description',
				label: __( 'Description', 'ndizi-project-management' ),
				filterBy: false,
				enableSorting: false,
				render: ( { item } ) => (
					<div
						style={ {
							whiteSpace: 'normal',
							wordBreak: 'break-word',
						} }
					>
						{ item.description || (
							<em>
								{ __(
									'No description',
									'ndizi-project-management'
								) }
							</em>
						) }
					</div>
				),
			},
			{
				id: 'duration',
				label: __( 'Duration', 'ndizi-project-management' ),
				enableSorting: true,
				filterBy: false,
				getValue: ( { item } ) => parseInt( item.duration, 10 ),
				render: ( { item } ) => {
					const secs = parseInt( item.duration, 10 ) || 0;
					const h = Math.floor( secs / 3600 );
					const m = Math.floor( ( secs % 3600 ) / 60 );
					return (
						<span>
							{ h }h { m }m
						</span>
					);
				},
			},
			{
				id: 'billable',
				label: __( 'Billable', 'ndizi-project-management' ),
				type: 'integer',
				elements: [
					{
						value: 1,
						label: __( 'Billable', 'ndizi-project-management' ),
					},
					{
						value: 0,
						label: __( 'Non-Billable', 'ndizi-project-management' ),
					},
				],
				filterBy: { operators: [ 'is' ] },
				getValue: ( { item } ) => parseInt( item.billable, 10 ),
				render: ( { item } ) => {
					const isBillable = parseInt( item.billable, 10 ) === 1;
					const badgeClass = isBillable
						? 'ndizi-badge-active'
						: 'ndizi-badge-archived';
					return (
						<span className={ `ndizi-badge ${ badgeClass }` }>
							{ isBillable
								? __( 'Yes', 'ndizi-project-management' )
								: __( 'No', 'ndizi-project-management' ) }
						</span>
					);
				},
			},
			{
				id: 'invoiced',
				label: __( 'Status', 'ndizi-project-management' ),
				type: 'integer',
				elements: [
					{
						value: 1,
						label: __( 'Billed', 'ndizi-project-management' ),
					},
					{
						value: 0,
						label: __( 'Unbilled', 'ndizi-project-management' ),
					},
				],
				filterBy: { operators: [ 'is' ] },
				getValue: ( { item } ) => parseInt( item.invoiced, 10 ),
				render: ( { item } ) => {
					const isInvoiced = parseInt( item.invoiced, 10 ) === 1;
					const badgeClass = isInvoiced
						? 'ndizi-badge-active'
						: 'ndizi-badge-pending';
					return (
						<span className={ `ndizi-badge ${ badgeClass }` }>
							{ isInvoiced
								? __( 'Billed', 'ndizi-project-management' )
								: __( 'Unbilled', 'ndizi-project-management' ) }
						</span>
					);
				},
			},
		],
		[ projectElements ]
	);

	// All filtering, sorting, and pagination happen client-side against the blob.
	const { data: shownData, paginationInfo } = useMemo(
		() => filterSortAndPaginate( entries, view, fields ),
		[ entries, view, fields ]
	);

	if ( ! entries.length ) {
		return (
			<p className="no-items-desc">
				{ __(
					'No time has been logged yet.',
					'ndizi-project-management'
				) }
			</p>
		);
	}

	return (
		<DataViews
			data={ shownData }
			fields={ fields }
			view={ view }
			onChangeView={ setView }
			getItemId={ ( item ) => String( item.id ) }
			paginationInfo={ paginationInfo }
			defaultLayouts={ { table: {} } }
		/>
	);
};

document.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'ndizi-portal-time-log-root' );
	if ( container ) {
		render( <TimeLogApp />, container );
	}
} );
