/* eslint-disable camelcase, no-alert */
// DataViews itself is provided by the shared `ndizi-dataviews` bundle
// (src/vendor/dataviews.js → build/vendor-dataviews.js), registered in PHP.
// webpack.config.js maps `@wordpress/dataviews/wp` to that bundle's global, and
// the .asset.php lists `ndizi-dataviews` as a dependency, so the DataViews code
// and its stylesheet load without being part of this script's build.
import { render, useState, useMemo } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
// Registers the `core` data store used below via select/dispatch and
// getEntityRecords. Importing it here makes wp-scripts add `wp-core-data` to
// time-entries.asset.php so the store is guaranteed to load on this standalone
// admin page (the block editor is not present to register it for us).
import '@wordpress/core-data';
import { DataViews } from '@wordpress/dataviews/wp';
import {
	Modal,
	Button,
	TextControl,
	SelectControl,
	CheckboxControl,
	TextareaControl,
	Notice,
	Spinner,
} from '@wordpress/components';
import { decodeEntities } from '@wordpress/html-entities';
import apiFetch from '@wordpress/api-fetch';

/* global ndizi_time_entries_admin */

const TimeEntriesApp = () => {
	const currentUserId = parseInt(
		ndizi_time_entries_admin.current_user_id,
		10
	);
	const canManage = !! ndizi_time_entries_admin.can_manage;
	const lockDateStr = ndizi_time_entries_admin.lock_date;

	// View state for DataViews. The shape follows the @wordpress/dataviews v16
	// API: visible columns live in `fields` (an ordered array of field ids) and
	// sorting lives in `sort: { field, direction }`. (Older releases used
	// `layout.fieldIds` and `sortBy`/`sortOrder`, which v16 ignores — leaving
	// every row's cells blank.)
	const [ view, setView ] = useState( {
		type: 'table',
		search: '',
		filters: [],
		page: 1,
		perPage: 20,
		sort: { field: 'start_time', direction: 'desc' },
		// Project+Task are merged into the `project_id` column and start/end times
		// into the `start_time` (Date) column, so `task_id`/`end_time` are not
		// listed as their own columns.
		fields: [
			'project_id',
			'user_id',
			'description',
			'start_time',
			'duration',
			'billable',
			'approved',
		],
		// Per-column sizing/alignment. The table is `width: 100%` with native
		// `table-layout: auto`, so capping every column except Description with a
		// `maxWidth` concentrates the leftover horizontal space on Description,
		// which is left uncapped (just a `minWidth`) so it auto-expands to fill
		// the row. Duration / Billable / Status are pinned narrow (equal width +
		// maxWidth). `align: 'start'` is required on the text columns because
		// DataViews right-aligns any field whose `type` is `integer` (which
		// project_id/user_id/billable/approved are, for filtering) by default.
		layout: {
			styles: {
				project_id: {
					align: 'start',
					minWidth: '180px',
					maxWidth: '300px',
				},
				user_id: {
					align: 'start',
					minWidth: '110px',
					maxWidth: '170px',
				},
				description: { align: 'start', minWidth: '240px' },
				start_time: {
					align: 'start',
					minWidth: '130px',
					maxWidth: '170px',
				},
				duration: { align: 'end', width: '90px', maxWidth: '90px' },
				billable: { align: 'center', width: '90px', maxWidth: '90px' },
				approved: {
					align: 'center',
					width: '110px',
					maxWidth: '110px',
				},
			},
		},
	} );

	// Construct query args from View state
	const queryArgs = useMemo( () => {
		const args = {
			page: view.page,
			per_page: view.perPage,
			orderby: view.sort?.field || 'start_time',
			order: view.sort?.direction || 'desc',
			search: view.search || '',
		};

		if ( view.filters ) {
			view.filters.forEach( ( filter ) => {
				if ( filter.field === 'project_id' ) {
					args.project_id = filter.value;
				} else if ( filter.field === 'user_id' ) {
					args.user_id = filter.value;
				} else if ( filter.field === 'billable' ) {
					args.billable = filter.value;
				} else if ( filter.field === 'approved' ) {
					args.approved = filter.value;
				}
			} );
		}

		return args;
	}, [ view ] );

	// Data Fetching via useSelect
	const { records, totalItems, hasResolved, projects, tasks, users } =
		useSelect(
			( select ) => {
				const selector = select( 'core' );
				return {
					records: selector.getEntityRecords(
						'ndizi',
						'time-entry',
						queryArgs
					),
					totalItems: selector.getEntityRecordsTotalItems(
						'ndizi',
						'time-entry',
						queryArgs
					),
					hasResolved: selector.hasFinishedResolution(
						'getEntityRecords',
						[ 'ndizi', 'time-entry', queryArgs ]
					),
					// Core REST controllers cap per_page at 100 and reject -1
					// outright (rest_invalid_param), which would leave the
					// dropdowns/filters empty. Request the maximum allowed page
					// instead. (If an install ever exceeds 100 projects/tasks/
					// users these lists would need real pagination.)
					projects: selector.getEntityRecords(
						'postType',
						'ndizi_project',
						{ per_page: 100 }
					),
					tasks: selector.getEntityRecords(
						'postType',
						'ndizi_task',
						{ per_page: 100 }
					),
					users: selector.getEntityRecords( 'root', 'user', {
						per_page: 100,
					} ),
				};
			},
			[ queryArgs ]
		);

	const { saveEntityRecord, deleteEntityRecord } = useDispatch( 'core' );

	// Form Modal State
	const [ isFormModalOpen, setIsFormModalOpen ] = useState( false );
	const [ editingEntry, setEditingEntry ] = useState( null );
	const [ formState, setFormState ] = useState( {
		projectId: '',
		taskId: '0',
		userId: currentUserId,
		description: '',
		durationHours: '',
		startTime: '',
		endTime: '',
		billable: true,
		approved: false,
	} );

	const [ actionNotice, setActionNotice ] = useState( null );
	const [ isSaving, setIsSaving ] = useState( false );

	// Helper to check if date is locked
	const isDateLocked = ( dateStr ) => {
		if ( ! lockDateStr || ! dateStr ) {
			return false;
		}
		// Lock the entire lock date, not just its midnight boundary, so an entry
		// recorded later on the lock day (e.g. 14:30) still counts as locked.
		// Mirrors Ndizi_DB::is_date_locked(), which compares against 23:59:59.
		const lockDate = new Date(
			( lockDateStr + ' 23:59:59' ).replace( /-/g, '/' )
		);
		const checkDate = new Date( dateStr.replace( /-/g, '/' ) );
		return checkDate <= lockDate;
	};

	// Dropdown Options
	const projectsOptions = useMemo( () => {
		if ( ! projects ) {
			return [];
		}
		return [
			{ label: 'Select Project', value: '' },
			...projects.map( ( p ) => ( {
				label: decodeEntities( p.title.rendered ),
				value: p.id,
			} ) ),
		];
	}, [ projects ] );

	const tasksOptions = useMemo( () => {
		if ( ! tasks ) {
			return [];
		}
		const filtered = tasks.filter(
			( t ) =>
				parseInt( t.meta?._ndizi_project_id, 10 ) ===
				parseInt( formState.projectId, 10 )
		);
		return [
			{ label: 'General / None', value: '0' },
			...filtered.map( ( t ) => ( {
				label: decodeEntities( t.title.rendered ),
				value: t.id,
			} ) ),
		];
	}, [ tasks, formState.projectId ] );

	const usersOptions = useMemo( () => {
		if ( ! users ) {
			return [];
		}
		return users.map( ( u ) => ( {
			label: u.name,
			value: u.id,
		} ) );
	}, [ users ] );

	// REST API Helpers. Accepts one or more entry IDs so the same path serves
	// both the single-row action and the bulk (multi-select) action.
	const setApproval = async ( ids, approved ) => {
		try {
			await Promise.all(
				ids.map( ( id ) =>
					apiFetch( {
						path: `/ndizi/v1/time/${ id }`,
						method: 'PUT',
						data: {
							approved: approved ? 1 : 0,
							approved_by: approved ? currentUserId : 0,
						},
					} )
				)
			);
			const count = ids.length;
			const noun = count === 1 ? 'entry' : 'entries';
			setActionNotice( {
				status: 'success',
				content: approved
					? `${ count } time ${ noun } approved.`
					: `${ count } time ${ noun } unapproved.`,
			} );
			// Force a refetch of the current page of records.
			window.wp.data
				.dispatch( 'core' )
				.invalidateResolution( 'getEntityRecords', [
					'ndizi',
					'time-entry',
					queryArgs,
				] );
		} catch ( err ) {
			setActionNotice( {
				status: 'error',
				content: err.message || 'Error updating approval status.',
			} );
		}
	};

	const deleteEntry = async ( id ) => {
		try {
			await deleteEntityRecord( 'ndizi', 'time-entry', id );
			setActionNotice( {
				status: 'success',
				content: 'Time entry deleted successfully.',
			} );
		} catch ( err ) {
			setActionNotice( {
				status: 'error',
				content: err.message || 'Error deleting time entry.',
			} );
		}
	};

	// Save handler for Add/Edit
	const handleSave = async ( e ) => {
		e.preventDefault();
		if ( ! formState.projectId ) {
			setActionNotice( {
				status: 'error',
				content: 'Project is required.',
			} );
			return;
		}

		setIsSaving( true );
		setActionNotice( null );

		const payload = {
			project_id: parseInt( formState.projectId, 10 ),
			task_id: parseInt( formState.taskId, 10 ) || 0,
			user_id: parseInt( formState.userId, 10 ) || currentUserId,
			description: formState.description,
			duration: Math.round(
				parseFloat( formState.durationHours || 0 ) * 3600
			),
			billable: formState.billable ? 1 : 0,
			start_time:
				formState.startTime ||
				new Date().toISOString().slice( 0, 19 ).replace( 'T', ' ' ),
			end_time: formState.endTime || '',
		};

		if ( editingEntry?.id ) {
			payload.id = editingEntry.id;
		}

		try {
			await saveEntityRecord( 'ndizi', 'time-entry', payload );
			setIsFormModalOpen( false );
			setActionNotice( {
				status: 'success',
				content: editingEntry?.id
					? 'Time entry updated successfully.'
					: 'Time entry added successfully.',
			} );
		} catch ( err ) {
			setActionNotice( {
				status: 'error',
				content: err.message || 'Error saving time entry.',
			} );
		} finally {
			setIsSaving( false );
		}
	};

	// Setup Open Add/Edit Modal
	const openAddModal = () => {
		setEditingEntry( null );
		setFormState( {
			projectId: '',
			taskId: '0',
			userId: currentUserId,
			description: '',
			durationHours: '',
			startTime: new Date()
				.toISOString()
				.slice( 0, 19 )
				.replace( 'T', ' ' ),
			endTime: '',
			billable: true,
			approved: false,
		} );
		setIsFormModalOpen( true );
	};

	const openEditModal = ( entry ) => {
		setEditingEntry( entry );
		setFormState( {
			projectId: entry.project_id.toString(),
			taskId: entry.task_id.toString(),
			userId: entry.user_id,
			description: entry.description,
			durationHours: ( entry.duration / 3600 ).toFixed( 2 ),
			startTime: entry.start_time,
			endTime: entry.end_time || '',
			billable: parseInt( entry.billable, 10 ) === 1,
			approved: parseInt( entry.approved, 10 ) === 1,
		} );
		setIsFormModalOpen( true );
	};

	// Actions for DataViews rows
	const actions = useMemo(
		() => {
			return [
				{
					id: 'edit',
					label: 'Edit',
					isPrimary: true,
					isEligible: ( item ) => {
						if ( parseInt( item.approved, 10 ) === 1 ) {
							return false;
						}
						if ( isDateLocked( item.start_time ) ) {
							return false;
						}
						return (
							canManage ||
							parseInt( item.user_id, 10 ) === currentUserId
						);
					},
					callback: ( items ) => openEditModal( items[ 0 ] ),
				},
				{
					id: 'delete',
					label: 'Delete',
					isDestructive: true,
					isEligible: ( item ) => {
						if ( parseInt( item.approved, 10 ) === 1 ) {
							return false;
						}
						if ( isDateLocked( item.start_time ) ) {
							return false;
						}
						return (
							canManage ||
							parseInt( item.user_id, 10 ) === currentUserId
						);
					},
					callback: ( items ) => {
						if (
							window.confirm(
								'Are you sure you want to delete this time entry?'
							)
						) {
							deleteEntry( items[ 0 ].id );
						}
					},
				},
				{
					id: 'approve',
					label: 'Approve',
					isPrimary: false,
					// supportsBulk enables the row-selection checkboxes and the
					// bulk-actions toolbar; the callback receives every selected
					// (eligible) item.
					supportsBulk: true,
					isEligible: ( item ) =>
						canManage && parseInt( item.approved, 10 ) !== 1,
					callback: ( items ) =>
						setApproval(
							items.map( ( item ) => item.id ),
							true
						),
				},
				{
					id: 'unapprove',
					label: 'Unapprove',
					isPrimary: false,
					supportsBulk: true,
					isEligible: ( item ) =>
						canManage && parseInt( item.approved, 10 ) === 1,
					callback: ( items ) =>
						setApproval(
							items.map( ( item ) => item.id ),
							false
						),
				},
			];
		},
		// queryArgs is included so the post-action cache invalidation in
		// setApproval targets the currently-viewed query, not a stale one.
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[ canManage, currentUserId, queryArgs ]
	);

	// Fields configuration for DataViews. In v16 a column's header text comes
	// from `label`, and filters are declared per-field via `elements` +
	// `filterBy` (there is no separate `filters` prop). `getValue` is supplied
	// where the rendered column id is not the raw value used for sorting or
	// filtering, so the filter chips and server query args stay correct.
	const fields = useMemo( () => {
		const projectElements = ( projects || [] ).map( ( p ) => ( {
			value: p.id,
			label: decodeEntities( p.title.rendered ),
		} ) );
		const userElements = ( users || [] ).map( ( u ) => ( {
			value: u.id,
			label: u.name,
		} ) );

		return [
			{
				id: 'project_id',
				label: 'Project / Task',
				type: 'integer',
				elements: projectElements,
				filterBy: { operators: [ 'is' ] },
				// Sortable: clicking groups by project, then task (the server
				// adds task_id as a secondary ORDER BY for this column).
				enableSorting: true,
				getValue: ( { item } ) => parseInt( item.project_id, 10 ),
				render: ( { item } ) => (
					<div>
						<strong>
							{ item.project_name ||
								`Project #${ item.project_id }` }
						</strong>
						<div style={ { color: '#64748b', fontSize: '0.9em' } }>
							{ item.task_name || <em>General / None</em> }
						</div>
					</div>
				),
			},
			{
				id: 'user_id',
				label: 'User',
				type: 'integer',
				elements: canManage ? userElements : undefined,
				filterBy: canManage ? { operators: [ 'is' ] } : false,
				getValue: ( { item } ) => parseInt( item.user_id, 10 ),
				render: ( { item } ) => (
					<span>{ item.user_name || `User #${ item.user_id }` }</span>
				),
			},
			{
				id: 'description',
				label: 'Description',
				filterBy: false,
				render: ( { item } ) => (
					<div
						style={ {
							whiteSpace: 'normal',
							wordBreak: 'break-word',
						} }
					>
						{ item.description || <em>No description</em> }
					</div>
				),
			},
			{
				id: 'start_time',
				label: 'Date',
				enableSorting: true,
				filterBy: false,
				render: ( { item } ) => {
					if ( ! item.start_time ) {
						return <span>-</span>;
					}
					const start = new Date(
						item.start_time.replace( /-/g, '/' )
					);
					const end = item.end_time
						? new Date( item.end_time.replace( /-/g, '/' ) )
						: null;
					const timeOpts = {
						hour: 'numeric',
						minute: '2-digit',
					};
					return (
						<div>
							<div>{ start.toLocaleDateString() }</div>
							<div
								style={ {
									color: '#64748b',
									fontSize: '0.9em',
								} }
							>
								{ start.toLocaleTimeString( [], timeOpts ) }
								{ end &&
									` – ${ end.toLocaleTimeString(
										[],
										timeOpts
									) }` }
							</div>
						</div>
					);
				},
			},
			{
				id: 'duration',
				label: 'Duration',
				enableSorting: true,
				filterBy: false,
				getValue: ( { item } ) => parseInt( item.duration, 10 ),
				render: ( { item } ) => {
					const h = Math.floor( item.duration / 3600 );
					const m = Math.floor( ( item.duration % 3600 ) / 60 );
					return (
						<span>
							{ h }h { m }m
						</span>
					);
				},
			},
			{
				id: 'billable',
				label: 'Billable',
				type: 'integer',
				elements: [
					{ value: 1, label: 'Billable' },
					{ value: 0, label: 'Non-Billable' },
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
							{ isBillable ? 'Yes' : 'No' }
						</span>
					);
				},
			},
			{
				id: 'approved',
				label: 'Status',
				type: 'integer',
				elements: [
					{ value: 1, label: 'Approved' },
					{ value: 0, label: 'Pending' },
				],
				filterBy: { operators: [ 'is' ] },
				getValue: ( { item } ) => parseInt( item.approved, 10 ),
				render: ( { item } ) => {
					const isApproved = parseInt( item.approved, 10 ) === 1;
					const badgeClass = isApproved
						? 'ndizi-badge-active'
						: 'ndizi-badge-pending';
					return (
						<span className={ `ndizi-badge ${ badgeClass }` }>
							{ isApproved ? 'Approved' : 'Pending' }
						</span>
					);
				},
			},
		];
	}, [ projects, users, canManage ] );

	return (
		<div
			className="ndizi-time-entries-react-app"
			style={ { margin: '20px 20px 0 0' } }
		>
			<div
				style={ {
					display: 'flex',
					justifyContent: 'space-between',
					alignItems: 'center',
					marginBottom: '20px',
				} }
			>
				<h1 className="wp-heading-inline" style={ { margin: 0 } }>
					Time Entries
				</h1>
				<Button isPrimary onClick={ openAddModal }>
					Add New
				</Button>
			</div>

			{ actionNotice && (
				<Notice
					status={ actionNotice.status }
					onDismiss={ () => setActionNotice( null ) }
					isDismissible
				>
					{ actionNotice.content }
				</Notice>
			) }

			{ ! hasResolved && (
				<div
					style={ {
						display: 'flex',
						justifyContent: 'center',
						padding: '50px 0',
					} }
				>
					<Spinner />
				</div>
			) }

			{ hasResolved && (
				<div
					style={ {
						background: '#fff',
						border: '1px solid #e2e8f0',
						borderRadius: '10px',
						padding: '15px',
					} }
				>
					<DataViews
						data={ records || [] }
						fields={ fields }
						actions={ actions }
						view={ view }
						onChangeView={ setView }
						getItemId={ ( item ) => String( item.id ) }
						isLoading={ ! hasResolved }
						paginationInfo={ {
							totalItems: totalItems || 0,
							totalPages: Math.ceil(
								( totalItems || 0 ) / ( view.perPage || 20 )
							),
						} }
						defaultLayouts={ { table: {} } }
					/>
				</div>
			) }

			{ isFormModalOpen && (
				<Modal
					title={
						editingEntry?.id
							? 'Edit Time Entry'
							: 'Add New Time Entry'
					}
					onRequestClose={ () => setIsFormModalOpen( false ) }
					style={ { maxWidth: '500px' } }
				>
					<form onSubmit={ handleSave }>
						<SelectControl
							label="Project"
							value={ formState.projectId }
							options={ projectsOptions }
							onChange={ ( val ) =>
								setFormState( {
									...formState,
									projectId: val,
									taskId: '0',
								} )
							}
							required
						/>

						<SelectControl
							label="Task (Optional)"
							value={ formState.taskId }
							options={ tasksOptions }
							onChange={ ( val ) =>
								setFormState( { ...formState, taskId: val } )
							}
						/>

						{ canManage && (
							<SelectControl
								label="User"
								value={ formState.userId }
								options={ usersOptions }
								onChange={ ( val ) =>
									setFormState( {
										...formState,
										userId: parseInt( val, 10 ),
									} )
								}
							/>
						) }

						<TextareaControl
							label="Description"
							value={ formState.description }
							onChange={ ( val ) =>
								setFormState( {
									...formState,
									description: val,
								} )
							}
							placeholder="What were you working on?"
						/>

						<TextControl
							label="Duration (Hours)"
							type="number"
							step="0.01"
							min="0.01"
							value={ formState.durationHours }
							onChange={ ( val ) =>
								setFormState( {
									...formState,
									durationHours: val,
								} )
							}
							required
							placeholder="e.g. 2.5"
						/>

						<TextControl
							label="Start Time (UTC)"
							value={ formState.startTime }
							onChange={ ( val ) =>
								setFormState( { ...formState, startTime: val } )
							}
							help="UTC format: YYYY-MM-DD HH:MM:SS. Defaults to current UTC time if empty."
						/>

						<TextControl
							label="End Time (UTC - Optional)"
							value={ formState.endTime }
							onChange={ ( val ) =>
								setFormState( { ...formState, endTime: val } )
							}
							help="Optional UTC format. If empty, the system will estimate it."
						/>

						<CheckboxControl
							label="Billable"
							checked={ formState.billable }
							onChange={ ( val ) =>
								setFormState( { ...formState, billable: val } )
							}
						/>

						<div
							style={ {
								display: 'flex',
								gap: '10px',
								justifyContent: 'flex-end',
								marginTop: '20px',
							} }
						>
							<Button
								isSecondary
								onClick={ () => setIsFormModalOpen( false ) }
							>
								Cancel
							</Button>
							<Button
								isPrimary
								type="submit"
								isBusy={ isSaving }
								disabled={ isSaving }
							>
								Save Entry
							</Button>
						</div>
					</form>
				</Modal>
			) }
		</div>
	);
};

// Mount the app when the DOM is fully loaded
window.addEventListener( 'DOMContentLoaded', () => {
	const container = document.getElementById( 'ndizi-time-entries-app' );
	if ( container ) {
		render( <TimeEntriesApp />, container );
	}
} );
