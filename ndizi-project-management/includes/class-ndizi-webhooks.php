<?php
/**
 * Webhooks and Slack integration handler for Ndizi Project Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ndizi_Webhooks {

	/**
	 * Initialize webhook triggers
	 */
	public static function init() {
		// Time Entry Actions
		add_action( 'ndizi_timer_started', array( __CLASS__, 'timer_started' ), 10, 6 );
		add_action( 'ndizi_timer_stopped', array( __CLASS__, 'timer_stopped' ), 10, 3 );
		add_action( 'ndizi_time_logged', array( __CLASS__, 'time_logged' ), 10, 7 );
		add_action( 'ndizi_time_entry_updated', array( __CLASS__, 'time_entry_updated' ), 10, 2 );
		add_action( 'ndizi_time_entry_deleted', array( __CLASS__, 'time_entry_deleted' ), 10, 1 );

		// Custom Post Type Transitions (Tasks & Invoices)
		add_action( 'transition_post_status', array( __CLASS__, 'handle_post_status_transition' ), 10, 3 );

		// Canonical events fired by Ndizi_CPTs (always loaded)
		add_action( 'ndizi_task_assigned', array( __CLASS__, 'task_assigned' ), 10, 2 );
		add_action( 'ndizi_task_status_changed', array( __CLASS__, 'task_status_changed' ), 10, 3 );
		add_action( 'ndizi_invoice_status_changed', array( __CLASS__, 'invoice_status_changed' ), 10, 3 );
	}

	/**
	 * Dispatch webhook payload and Slack message
	 *
	 * @param string $event Event type identifier.
	 * @param array  $data Payload dataset.
	 * @param string $slack_message Formatted text alert for Slack.
	 */
	private static function dispatch( $event, $data, $slack_message = '' ) {
		$webhook_url       = get_option( 'ndizi_webhook_url' );
		$slack_webhook_url = get_option( 'ndizi_slack_webhook_url' );

		$payload = array(
			'event'     => $event,
			'timestamp' => time(),
			'data'      => $data,
		);

		// Trigger webhook POST request (non-blocking).
		// wp_http_validate_url() rejects loopback and private-range targets (SSRF guard).
		if ( ! empty( $webhook_url ) && wp_http_validate_url( $webhook_url ) ) {
			wp_remote_post(
				$webhook_url,
				array(
					'method'      => 'POST',
					'timeout'     => 5,
					'redirection' => 5,
					'httpversion' => '1.0',
					'blocking'    => false,
					'headers'     => array(
						'Content-Type' => 'application/json; charset=utf-8',
					),
					'body'        => wp_json_encode( $payload ),
				)
			);
		} elseif ( ! empty( $webhook_url ) ) {
			$parsed     = wp_parse_url( $webhook_url );
			$logged_url = ( isset( $parsed['scheme'] ) ? $parsed['scheme'] . '://' : '' ) . ( isset( $parsed['host'] ) ? $parsed['host'] : '' );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Ndizi: outbound webhook URL blocked by SSRF guard: ' . $logged_url );
		}

		// Trigger Slack webhook POST request (non-blocking).
		if ( ! empty( $slack_webhook_url ) && wp_http_validate_url( $slack_webhook_url ) && ! empty( $slack_message ) ) {
			wp_remote_post(
				$slack_webhook_url,
				array(
					'method'      => 'POST',
					'timeout'     => 5,
					'redirection' => 5,
					'httpversion' => '1.0',
					'blocking'    => false,
					'headers'     => array(
						'Content-Type' => 'application/json; charset=utf-8',
					),
					'body'        => wp_json_encode( array( 'text' => $slack_message ) ),
				)
			);
		} elseif ( ! empty( $slack_webhook_url ) && ! empty( $slack_message ) ) {
			$parsed     = wp_parse_url( $slack_webhook_url );
			$logged_url = ( isset( $parsed['scheme'] ) ? $parsed['scheme'] . '://' : '' ) . ( isset( $parsed['host'] ) ? $parsed['host'] : '' );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Ndizi: Slack webhook URL blocked by SSRF guard: ' . $logged_url );
		}
	}

	/**
	 * Handler for ndizi_timer_started
	 */
	public static function timer_started( $entry_id, $user_id, $project_id, $task_id, $description, $billable ) {
		$user = get_userdata( $user_id );
		$proj = get_post( $project_id );
		$task = $task_id ? get_post( $task_id ) : null;

		$user_name    = $user ? $user->display_name : __( 'Unknown User', 'ndizi-project-management' );
		$project_name = $proj ? $proj->post_title : __( 'No Project', 'ndizi-project-management' );
		$task_name    = $task ? $task->post_title : '';

		$data = array(
			'entry_id'     => $entry_id,
			'user_id'      => $user_id,
			'user_name'    => $user_name,
			'project_id'   => $project_id,
			'project_name' => $project_name,
			'task_id'      => $task_id,
			'task_name'    => $task_name,
			'description'  => $description,
			'billable'     => (bool) $billable,
		);

		$desc_text     = $description ? $description : '_' . __( 'No description', 'ndizi-project-management' ) . '_';
		$slack_message = sprintf(
			"⏱️ *Timer Started* by *%s* on *%s*%s\n*Description*: %s",
			$user_name,
			$project_name,
			$task_name ? " (Task: *{$task_name}*)" : '',
			$desc_text
		);

		self::dispatch( 'timer_started', $data, $slack_message );
	}

	/**
	 * Handler for ndizi_timer_stopped
	 */
	public static function timer_stopped( $entry_id, $user_id, $duration ) {
		$user      = get_userdata( $user_id );
		$user_name = $user ? $user->display_name : __( 'Unknown User', 'ndizi-project-management' );
		$entry     = Ndizi_DB::get_time_entry( $entry_id );

		$project_name = __( 'No Project', 'ndizi-project-management' );
		if ( $entry && $entry->project_id ) {
			$proj = get_post( $entry->project_id );
			if ( $proj ) {
				$project_name = $proj->post_title;
			}
		}

		$data = array(
			'entry_id'         => $entry_id,
			'user_id'          => $user_id,
			'user_name'        => $user_name,
			'duration_seconds' => $duration,
			'duration_hours'   => round( $duration / 3600, 2 ),
		);

		$slack_message = sprintf(
			'🛑 *Timer Stopped* by *%s* on *%s* (Duration: %s hours)',
			$user_name,
			$project_name,
			number_format( $duration / 3600, 2 )
		);

		self::dispatch( 'timer_stopped', $data, $slack_message );
	}

	/**
	 * Handler for ndizi_time_logged
	 */
	public static function time_logged( $entry_id, $user_id, $project_id, $task_id, $description, $duration, $billable ) {
		$user = get_userdata( $user_id );
		$proj = get_post( $project_id );
		$task = $task_id ? get_post( $task_id ) : null;

		$user_name    = $user ? $user->display_name : __( 'Unknown User', 'ndizi-project-management' );
		$project_name = $proj ? $proj->post_title : __( 'No Project', 'ndizi-project-management' );
		$task_name    = $task ? $task->post_title : '';

		$data = array(
			'entry_id'         => $entry_id,
			'user_id'          => $user_id,
			'user_name'        => $user_name,
			'project_id'       => $project_id,
			'project_name'     => $project_name,
			'task_id'          => $task_id,
			'task_name'        => $task_name,
			'description'      => $description,
			'duration_seconds' => $duration,
			'duration_hours'   => round( $duration / 3600, 2 ),
			'billable'         => (bool) $billable,
		);

		$desc_text     = $description ? $description : '_' . __( 'No description', 'ndizi-project-management' ) . '_';
		$slack_message = sprintf(
			"📝 *Time Logged* by *%s* on *%s*%s\n*Duration*: %s hours\n*Description*: %s",
			$user_name,
			$project_name,
			$task_name ? " (Task: *{$task_name}*)" : '',
			number_format( $duration / 3600, 2 ),
			$desc_text
		);

		self::dispatch( 'time_logged', $data, $slack_message );
	}

	/**
	 * Handler for ndizi_time_entry_updated
	 */
	public static function time_entry_updated( $id, $updated_data ) {
		$entry = Ndizi_DB::get_time_entry( $id );
		if ( ! $entry ) {
			return;
		}

		$user      = get_userdata( $entry->user_id );
		$user_name = $user ? $user->display_name : __( 'Unknown User', 'ndizi-project-management' );

		$data = array(
			'entry_id'       => $id,
			'user_name'      => $user_name,
			'updated_fields' => $updated_data,
		);

		$slack_message = sprintf(
			'🔄 *Time Entry Updated* (ID: %d) by *%s*',
			$id,
			$user_name
		);

		self::dispatch( 'time_entry_updated', $data, $slack_message );
	}

	/**
	 * Handler for ndizi_time_entry_deleted
	 */
	public static function time_entry_deleted( $id ) {
		$data = array(
			'entry_id' => $id,
		);

		$slack_message = sprintf(
			'🗑️ *Time Entry Deleted* (ID: %d)',
			$id
		);

		self::dispatch( 'time_entry_deleted', $data, $slack_message );
	}

	/**
	 * Handler for transition_post_status (Task and Invoice creation)
	 */
	public static function handle_post_status_transition( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		if ( 'ndizi_task' === $post->post_type ) {
			$project_id = get_post_meta( $post->ID, '_ndizi_project_id', true );
			$proj       = $project_id ? get_post( $project_id ) : null;
			$proj_title = $proj ? $proj->post_title : __( 'No Project', 'ndizi-project-management' );
			$edit_url   = admin_url( 'post.php?post=' . $post->ID . '&action=edit' );

			$data = array(
				'task_id'      => $post->ID,
				'task_title'   => $post->post_title,
				'project_id'   => $project_id,
				'project_name' => $proj_title,
			);

			$slack_message = sprintf(
				"🆕 *Task Created*: *%s* on *%s*\n*Link*: <%s|View Task>",
				$post->post_title,
				$proj_title,
				$edit_url
			);

			self::dispatch( 'task_created', $data, $slack_message );

		} elseif ( 'ndizi_invoice' === $post->post_type ) {
			$project_id = get_post_meta( $post->ID, '_ndizi_project_id', true );
			$proj       = $project_id ? get_post( $project_id ) : null;
			$proj_title = $proj ? $proj->post_title : __( 'No Project', 'ndizi-project-management' );
			$amount     = get_post_meta( $post->ID, '_ndizi_invoice_amount', true );
			$edit_url   = admin_url( 'post.php?post=' . $post->ID . '&action=edit' );

			$data = array(
				'invoice_id'    => $post->ID,
				'invoice_title' => $post->post_title,
				'project_id'    => $project_id,
				'project_name'  => $proj_title,
				'amount'        => $amount,
			);

			$slack_message = sprintf(
				"📄 *Invoice Created*: *%s* on *%s* (Amount: $%s)\n*Link*: <%s|View Invoice>",
				$post->post_title,
				$proj_title,
				number_format( floatval( $amount ), 2 ),
				$edit_url
			);

			self::dispatch( 'invoice_created', $data, $slack_message );
		}
	}

	/**
	 * Handler for canonical ndizi_task_assigned action
	 */
	public static function task_assigned( $task_id, $assignee_id ) {
		$assignee_id = intval( $assignee_id );
		if ( $assignee_id > 0 ) {
			$user      = get_userdata( $assignee_id );
			$user_name = $user ? $user->display_name : __( 'Unknown User', 'ndizi-project-management' );
			$task      = get_post( $task_id );
			$edit_url  = admin_url( 'post.php?post=' . $task_id . '&action=edit' );

			$data = array(
				'task_id'     => $task_id,
				'task_title'  => $task ? $task->post_title : '',
				'assigned_to' => $assignee_id,
				'user_name'   => $user_name,
			);

			$slack_message = sprintf(
				"👤 *Task Assigned*: *%s* is now assigned to *%s*\n*Link*: <%s|View Task>",
				$task ? $task->post_title : '',
				$user_name,
				$edit_url
			);

			self::dispatch( 'task_assigned', $data, $slack_message );
		}
	}

	/**
	 * Handler for canonical ndizi_task_status_changed action
	 */
	public static function task_status_changed( $task_id, $new_status_val, $old_status_val ) {
		$task          = get_post( $task_id );
		$status_labels = array(
			'open'        => __( 'Open', 'ndizi-project-management' ),
			'in_progress' => __( 'In Progress', 'ndizi-project-management' ),
			'completed'   => __( 'Completed', 'ndizi-project-management' ),
			'cancelled'   => __( 'Cancelled', 'ndizi-project-management' ),
		);
		$old_status    = isset( $status_labels[ $old_status_val ] ) ? $status_labels[ $old_status_val ] : $old_status_val;
		$new_status    = isset( $status_labels[ $new_status_val ] ) ? $status_labels[ $new_status_val ] : $new_status_val;
		$edit_url      = admin_url( 'post.php?post=' . $task_id . '&action=edit' );

		$data = array(
			'task_id'    => $task_id,
			'task_title' => $task ? $task->post_title : '',
			'old_status' => $old_status_val,
			'new_status' => $new_status_val,
		);

		$slack_message = sprintf(
			"🔄 *Task Status Updated*: *%s* status changed from *%s* to *%s*\n*Link*: <%s|View Task>",
			$task ? $task->post_title : '',
			$old_status ? $old_status : __( 'None', 'ndizi-project-management' ),
			$new_status,
			$edit_url
		);

		self::dispatch( 'task_status_updated', $data, $slack_message );
	}

	/**
	 * Handler for canonical ndizi_invoice_status_changed action
	 */
	public static function invoice_status_changed( $invoice_id, $new_status_val, $old_status_val ) {
		$invoice  = get_post( $invoice_id );
		$amount   = get_post_meta( $invoice_id, '_ndizi_invoice_amount', true );
		$edit_url = admin_url( 'post.php?post=' . $invoice_id . '&action=edit' );

		$data = array(
			'invoice_id' => $invoice_id,
			'invoice_no' => $invoice ? $invoice->post_title : '',
			'old_status' => $old_status_val,
			'new_status' => $new_status_val,
			'amount'     => $amount,
		);

		$slack_message = sprintf(
			"📄 *Invoice Status Updated*: *%s* status changed to *%s* (Amount: $%s)\n*Link*: <%s|View Invoice>",
			$invoice ? $invoice->post_title : '',
			strtoupper( $new_status_val ),
			number_format( floatval( $amount ), 2 ),
			$edit_url
		);

		self::dispatch( 'invoice_status_updated', $data, $slack_message );
	}
}
