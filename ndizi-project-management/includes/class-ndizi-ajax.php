<?php
/**
 * AJAX handlers for Ndizi Project Management.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ndizi_Ajax {

	/**
	 * Initialize AJAX hooks
	 */
	public static function init() {
		add_action( 'wp_ajax_ndizi_aggregate_invoice_time', array( __CLASS__, 'ajax_aggregate_invoice_time' ) );
		add_action( 'wp_ajax_ndizi_start_timer_action', array( __CLASS__, 'ajax_start_timer' ) );
		add_action( 'wp_ajax_ndizi_stop_timer_action', array( __CLASS__, 'ajax_stop_timer' ) );
		add_action( 'wp_ajax_ndizi_delete_log_action', array( __CLASS__, 'ajax_delete_log' ) );
		add_action( 'wp_ajax_ndizi_check_active_timer', array( __CLASS__, 'ajax_check_active_timer' ) );
		add_action( 'wp_ajax_ndizi_refresh_logs_table', array( __CLASS__, 'ajax_refresh_logs_table' ) );
	}

	/**
	 * AJAX logic to link time entries to an invoice
	 */
	public static function ajax_aggregate_invoice_time() {
		check_ajax_referer( 'ndizi-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'ndizi_manage_invoices' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ndizi-project-management' ) ) );
		}

		$invoice_id = isset( $_POST['invoice_id'] ) ? intval( $_POST['invoice_id'] ) : 0;
		$entry_ids  = isset( $_POST['entry_ids'] ) && is_array( $_POST['entry_ids'] ) ? array_map( 'intval', $_POST['entry_ids'] ) : array();
		$rate       = isset( $_POST['hourly_rate'] ) ? floatval( $_POST['hourly_rate'] ) : 0;

		if ( ! $invoice_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid Invoice ID.', 'ndizi-project-management' ) ) );
		}

		global $wpdb;
		$table_name = Ndizi_DB::get_table_name();

		// Calculate total duration
		$total_sec = 0;
		if ( ! empty( $entry_ids ) ) {
			$ids_placeholders = implode( ',', array_fill( 0, count( $entry_ids ), '%d' ) );
			// phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$total_sec = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT SUM(duration) FROM $table_name WHERE id IN ($ids_placeholders)",
					$entry_ids
				)
			);
		}

		$total_hours       = $total_sec ? ( $total_sec / 3600 ) : 0;
		$calculated_amount = round( $total_hours * $rate, 2 );

		wp_send_json_success(
			array(
				'hours'  => round( $total_hours, 2 ),
				'amount' => $calculated_amount,
			)
		);
	}

	public static function ajax_start_timer() {
		check_ajax_referer( 'ndizi-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'ndizi_log_time' ) && ! current_user_can( 'ndizi_manage_time' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ndizi-project-management' ) ) );
		}

		$project_id  = isset( $_POST['project_id'] ) ? intval( $_POST['project_id'] ) : 0;
		$task_id     = isset( $_POST['task_id'] ) ? intval( $_POST['task_id'] ) : 0;
		$description = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
		$billable    = isset( $_POST['billable'] ) ? intval( $_POST['billable'] ) : 1;

		if ( ! $project_id ) {
			wp_send_json_error( array( 'message' => __( 'Project ID is required.', 'ndizi-project-management' ) ) );
		}

		$timer_id = Ndizi_Time_Service::start_timer(
			get_current_user_id(),
			$project_id,
			array(
				'task_id'     => $task_id,
				'description' => $description,
				'billable'    => $billable,
			)
		);
		if ( is_wp_error( $timer_id ) ) {
			wp_send_json_error( array( 'message' => $timer_id->get_error_message() ) );
		}

		wp_send_json_success( array( 'timer_id' => $timer_id ) );
	}

	/**
	 * AJAX logic to stop a timer
	 */
	public static function ajax_stop_timer() {
		check_ajax_referer( 'ndizi-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'ndizi_log_time' ) && ! current_user_can( 'ndizi_manage_time' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ndizi-project-management' ) ) );
		}

		$stopped = Ndizi_Time_Service::stop_timer( get_current_user_id() );
		if ( is_wp_error( $stopped ) ) {
			wp_send_json_error( array( 'message' => $stopped->get_error_message() ) );
		}

		wp_send_json_success( array( 'timer' => $stopped ) );
	}

	/**
	 * AJAX logic to delete a log
	 */
	public static function ajax_delete_log() {
		check_ajax_referer( 'ndizi-admin-nonce', 'nonce' );

		$log_id = isset( $_POST['log_id'] ) ? intval( $_POST['log_id'] ) : 0;
		if ( ! $log_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid log ID.', 'ndizi-project-management' ) ) );
		}

		$log     = Ndizi_DB::get_time_entry( $log_id );
		$user_id = get_current_user_id();

		if ( ! $log ) {
			wp_send_json_error( array( 'message' => __( 'Log not found.', 'ndizi-project-management' ) ) );
		}

		// Authorization: own logs, or users who can manage all time.
		if ( intval( $log->user_id ) !== $user_id && ! Ndizi_Roles::current_user_can( 'ndizi_manage_time' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ndizi-project-management' ) ) );
		}

		if ( Ndizi_DB::is_date_locked( $log->start_time ) ) {
			wp_send_json_error( array( 'message' => __( 'Cannot delete log. The time entry is in a locked period.', 'ndizi-project-management' ) ) );
		}

		$deleted = Ndizi_DB::delete_time_entry( $log_id );

		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete log.', 'ndizi-project-management' ) ) );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX logic to check running timer
	 */
	public static function ajax_check_active_timer() {
		check_ajax_referer( 'ndizi-admin-nonce', 'nonce' );

		$user_id = get_current_user_id();
		$timer   = Ndizi_DB::get_active_timer( $user_id );

		if ( ! $timer ) {
			wp_send_json_success( array( 'active' => false ) );
		}

		// Add live duration
		$start_ts             = strtotime( $timer->start_time );
		$now_ts               = time();
		$timer->live_duration = max( 0, $now_ts - $start_ts );

		wp_send_json_success(
			array(
				'active' => true,
				'timer'  => $timer,
			)
		);
	}

	/**
	 * AJAX logic to refresh logs table html
	 */
	public static function ajax_refresh_logs_table() {
		check_ajax_referer( 'ndizi-admin-nonce', 'nonce' );

		if ( ! current_user_can( 'ndizi_log_time' ) && ! current_user_can( 'ndizi_manage_time' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ndizi-project-management' ) ) );
		}

		$project_id = isset( $_POST['project_id'] ) ? intval( $_POST['project_id'] ) : 0;
		if ( ! $project_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid project ID.', 'ndizi-project-management' ) ) );
		}

		$user_id = get_current_user_id();

		// Validate project access for non-managers
		if ( ! current_user_can( 'ndizi_manage_projects' ) && ! current_user_can( 'ndizi_manage_time' ) ) {
			$access = Ndizi_Time_Service::validate_time_project_access( $project_id, 0, $user_id );
			if ( is_wp_error( $access ) ) {
				wp_send_json_error( array( 'message' => $access->get_error_message() ) );
			}
		}

		$query_args = array(
			'project_id' => $project_id,
			'number'     => 15,
		);

		// Restrict non-managers to their own entries
		if ( ! current_user_can( 'ndizi_manage_time' ) ) {
			$query_args['user_id'] = $user_id;
		}

		$logs = Ndizi_DB::get_time_entries( $query_args );

		ob_start();
		if ( empty( $logs ) ) {
			echo '<tr class="no-items"><td colspan="7">' . esc_html__( 'No time logged yet on this project.', 'ndizi-project-management' ) . '</td></tr>';
		} else {
			foreach ( $logs as $log ) {
				$log_user = get_userdata( $log->user_id );
				$log_task = $log->task_id ? get_post( $log->task_id ) : null;
				?>
				<tr id="ndizi-log-row-<?php echo esc_attr( $log->id ); ?>">
					<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $log->start_time ) ) ); ?></td>
					<td><?php echo $log_user ? esc_html( $log_user->display_name ) : '-'; ?></td>
					<td><?php echo $log_task ? esc_html( $log_task->post_title ) : '<em>-</em>'; ?></td>
					<td><?php echo esc_html( $log->description ); ?></td>
					<td><strong><?php echo esc_html( round( $log->duration / 3600, 2 ) ); ?>h</strong></td>
					<td>
						<span class="dashicons <?php echo $log->billable ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
					</td>
					<td>
						<button type="button" class="button button-link ndizi-delete-log-btn" data-id="<?php echo esc_attr( $log->id ); ?>">
							<span class="dashicons dashicons-trash"></span>
						</button>
					</td>
				</tr>
				<?php
			}
		}
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}
}
