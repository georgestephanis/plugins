<?php
/**
 * Time Entries List Table for Ndizi Project Management.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Ndizi_Time_Entries_List_Table extends WP_List_Table {

	/**
	 * Constructor
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'ndizi-time-entry',
				'plural'   => 'ndizi-time-entries',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Get the columns
	 */
	public function get_columns() {
		$columns = array(
			'cb'          => '<input type="checkbox" />',
			'project'     => __( 'Project', 'ndizi-project-management' ),
			'task'        => __( 'Task', 'ndizi-project-management' ),
			'user'        => __( 'User', 'ndizi-project-management' ),
			'description' => __( 'Description', 'ndizi-project-management' ),
			'start_time'  => __( 'Start Time', 'ndizi-project-management' ),
			'end_time'    => __( 'End Time', 'ndizi-project-management' ),
			'duration'    => __( 'Duration', 'ndizi-project-management' ),
			'billable'    => __( 'Billable', 'ndizi-project-management' ),
			'approved'    => __( 'Status', 'ndizi-project-management' ),
		);
		return $columns;
	}

	/**
	 * Get sortable columns
	 */
	public function get_sortable_columns() {
		return array(
			'start_time' => array( 'start_time', true ),
			'end_time'   => array( 'end_time', false ),
			'duration'   => array( 'duration', false ),
		);
	}

	/**
	 * Checkbox column
	 */
	public function column_cb( $item ) {
		return sprintf(
			'<input type="checkbox" name="entry_ids[]" value="%d" />',
			$item->id
		);
	}

	/**
	 * Default column rendering
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'project':
				$project = get_post( $item->project_id );
				if ( $project ) {
					return sprintf(
						'<a href="%s"><strong>%s</strong></a>',
						esc_url( get_edit_post_link( $item->project_id ) ),
						esc_html( $project->post_title )
					);
				}
				return '-';

			case 'task':
				if ( $item->task_id ) {
					$task = get_post( $item->task_id );
					if ( $task ) {
						return sprintf(
							'<a href="%s">%s</a>',
							esc_url( get_edit_post_link( $item->task_id ) ),
							esc_html( $task->post_title )
						);
					}
				}
				return '<em>' . esc_html__( 'General / None', 'ndizi-project-management' ) . '</em>';

			case 'user':
				$user = get_userdata( $item->user_id );
				return $user ? esc_html( $user->display_name ) : '-';

			case 'description':
				$description = ! empty( $item->description ) ? esc_html( $item->description ) : '<em>' . esc_html__( 'No description', 'ndizi-project-management' ) . '</em>';

				$actions = array();

				// Build action links based on permissions and approval status
				$can_manage = Ndizi_Roles::current_user_can( 'ndizi_manage_time' );
				$can_edit   = $can_manage || ( Ndizi_Roles::current_user_can( 'ndizi_log_time' ) && (int) $item->user_id === (int) get_current_user_id() );

				// Locked checks
				$is_locked = Ndizi_DB::is_date_locked( $item->start_time );

				if ( $can_edit && ! $is_locked && ! $item->approved ) {
					$actions['edit'] = sprintf(
						'<a href="%s">%s</a>',
						esc_url( admin_url( 'admin.php?page=ndizi-time-entries&action=edit&id=' . $item->id ) ),
						__( 'Edit', 'ndizi-project-management' )
					);

					$actions['delete'] = sprintf(
						'<a href="%s" onclick="return confirm(\'%s\');" style="color: #b32d2e;">%s</a>',
						esc_url( wp_nonce_url( admin_url( 'admin.php?page=ndizi-time-entries&action=delete&id=' . $item->id ), 'ndizi_delete_time_' . $item->id, 'nonce' ) ),
						esc_attr__( 'Are you sure you want to delete this time entry?', 'ndizi-project-management' ),
						__( 'Delete', 'ndizi-project-management' )
					);
				}

				if ( $can_manage ) {
					$nonce_action             = $item->approved ? 'unapprove' : 'approve';
					$actions[ $nonce_action ] = sprintf(
						'<a href="%s">%s</a>',
						esc_url( wp_nonce_url( admin_url( 'admin.php?page=ndizi-time-entries&action=' . $nonce_action . '&id=' . $item->id ), 'ndizi_' . $nonce_action . '_time_' . $item->id, 'nonce' ) ),
						$item->approved ? __( 'Unapprove', 'ndizi-project-management' ) : __( 'Approve', 'ndizi-project-management' )
					);
				}

				return sprintf( '%s %s', $description, $this->row_actions( $actions ) );

			case 'start_time':
				return $item->start_time ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item->start_time ) ) ) : '-';

			case 'end_time':
				return $item->end_time ? esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $item->end_time ) ) ) : '-';

			case 'duration':
				$h = floor( $item->duration / 3600 );
				$m = floor( ( $item->duration % 3600 ) / 60 );
				return sprintf( '%02dh %02dm', $h, $m );

			case 'billable':
				$billable_label = $item->billable ? __( 'Yes', 'ndizi-project-management' ) : __( 'No', 'ndizi-project-management' );
				$badge_class    = $item->billable ? 'ndizi-badge-active' : 'ndizi-badge-archived';
				return '<span class="ndizi-badge ' . esc_attr( $badge_class ) . '">' . esc_html( $billable_label ) . '</span>';

			case 'approved':
				$approved_label = $item->approved ? __( 'Approved', 'ndizi-project-management' ) : __( 'Pending', 'ndizi-project-management' );
				$badge_class    = $item->approved ? 'ndizi-badge-active' : 'ndizi-badge-pending';
				return '<span class="ndizi-badge ' . esc_attr( $badge_class ) . '">' . esc_html( $approved_label ) . '</span>';

			default:
				return '-';
		}
	}

	/**
	 * Prepare the items
	 */
	public function prepare_items() {
		$columns               = $this->get_columns();
		$hidden                = array();
		$sortable              = $this->get_sortable_columns();
		$this->_column_headers = array( $columns, $hidden, $sortable, 'description' );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		// Get query args
		$paged    = $this->get_pagenum();
		$per_page = $this->get_items_per_page( 'ndizi_time_entries_per_page', 20 );

		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'start_time';
		$order   = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'ASC' : 'DESC';

		$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$project  = isset( $_GET['project_id'] ) && '' !== $_GET['project_id'] ? intval( wp_unslash( $_GET['project_id'] ) ) : null;
		$user     = isset( $_GET['user_id'] ) && '' !== $_GET['user_id'] ? intval( wp_unslash( $_GET['user_id'] ) ) : null;
		$billable = isset( $_GET['billable_status'] ) && '' !== $_GET['billable_status'] ? ( 'yes' === sanitize_key( wp_unslash( $_GET['billable_status'] ) ) ? 1 : 0 ) : null;
		$approved = isset( $_GET['approved_status'] ) && '' !== $_GET['approved_status'] ? ( 'yes' === sanitize_key( wp_unslash( $_GET['approved_status'] ) ) ? 1 : 0 ) : null;
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Access control check
		if ( ! Ndizi_Roles::current_user_can( 'ndizi_manage_time' ) ) {
			// Enforce only seeing their own entries
			$user = get_current_user_id();
		}

		$query_args = array(
			'project_id' => $project,
			'user_id'    => $user,
			'billable'   => $billable,
			'approved'   => $approved,
			'search'     => $search,
			'orderby'    => $orderby,
			'order'      => $order,
			'number'     => $per_page,
			'offset'     => ( $paged - 1 ) * $per_page,
		);

		$this->items = Ndizi_DB::get_time_entries( $query_args );

		$total_items = Ndizi_DB::get_time_entries_count( $query_args );

		$this->set_pagination_args(
			array(
				'total_items' => $total_items,
				'per_page'    => $per_page,
				'total_pages' => ceil( $total_items / $per_page ),
			)
		);
	}

	/**
	 * Render bulk action buttons
	 */
	public function get_bulk_actions() {
		$actions = array();
		if ( Ndizi_Roles::current_user_can( 'ndizi_manage_time' ) ) {
			$actions['bulk-approve']   = __( 'Approve', 'ndizi-project-management' );
			$actions['bulk-unapprove'] = __( 'Unapprove', 'ndizi-project-management' );
		}

		// Anyone who can log time or edit their own can do bulk delete, but we will check permissions inline for deletion.
		$actions['bulk-delete'] = __( 'Delete', 'ndizi-project-management' );

		return $actions;
	}
}
