<?php
/**
 * Email notifications handler for Ndizi Project Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ndizi_Notifications {

	/**
	 * Initialize notification hooks
	 */
	public static function init() {
		add_action( 'ndizi_client_submitted_task', array( __CLASS__, 'notify_admin_on_client_task' ), 10, 3 );
		add_action( 'added_post_meta', array( __CLASS__, 'handle_added_post_meta' ), 10, 4 );
		add_action( 'updated_post_meta', array( __CLASS__, 'handle_updated_post_meta' ), 10, 4 );
	}

	/**
	 * Send email notification when a client submits a task
	 */
	public static function notify_admin_on_client_task( $task_id, $project_id, $client_id ) {
		$task    = get_post( $task_id );
		$project = get_post( $project_id );
		$client  = get_post( $client_id );

		if ( ! $task || ! $project || ! $client ) {
			return;
		}

		$to = get_option( 'admin_email' );
		/* translators: %s: client name */
		$subject = sprintf( __( '[Ndizi PM] New Task Request from %s', 'ndizi-project-management' ), $client->post_title );

		// Build a clean, styled HTML message body
		$message  = '<html><body>';
		$message .= '<div style="font-family: sans-serif; max-width: 600px; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;">';
		$message .= '<h2 style="color: #4f46e5; margin-top: 0;">New Task Submitted</h2>';
		$message .= sprintf( '<p><strong>Client:</strong> %s</p>', esc_html( $client->post_title ) );
		$message .= sprintf( '<p><strong>Project:</strong> %s</p>', esc_html( $project->post_title ) );
		$message .= '<hr style="border: 0; height: 1px; background: #e2e8f0; margin: 20px 0;">';
		$message .= sprintf( '<h3 style="margin-top: 0; color: #1e293b;">%s</h3>', esc_html( $task->post_title ) );
		$message .= '<div style="background: #f8fafc; padding: 15px; border-radius: 6px; font-size: 14px; line-height: 1.6; color: #475569;">';
		$message .= wpautop( esc_html( $task->post_content ) );
		$message .= '</div>';
		$message .= '<hr style="border: 0; height: 1px; background: #e2e8f0; margin: 20px 0;">';
		$message .= sprintf( '<p style="font-size: 13px; color: #64748b;"><a href="%s" style="color: #4f46e5; text-decoration: none; font-weight: bold;">Triage and Assign Task in WordPress Admin &rarr;</a></p>', esc_url( admin_url( 'post.php?post=' . $task_id . '&action=edit' ) ) );
		$message .= '</div>';
		$message .= '</body></html>';

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: Ndizi Project Management <' . get_option( 'admin_email' ) . '>',
		);

		// Allow other plugins to hook in or filter the notification parameters
		$to      = apply_filters( 'ndizi_task_notification_to', $to, $task_id, $project_id, $client_id );
		$subject = apply_filters( 'ndizi_task_notification_subject', $subject, $task_id, $project_id, $client_id );
		$message = apply_filters( 'ndizi_task_notification_message', $message, $task_id, $project_id, $client_id );
		$headers = apply_filters( 'ndizi_task_notification_headers', $headers, $task_id, $project_id, $client_id );

		wp_mail( $to, $subject, $message, $headers );
	}

	/**
	 * Handle metadata additions to trigger notifications
	 */
	public static function handle_added_post_meta( $mid, $object_id, $meta_key, $_meta_value ) {
		if ( 'ndizi_task' !== get_post_type( $object_id ) ) {
			return;
		}

		if ( '_ndizi_assigned_user_id' === $meta_key ) {
			$assignee_id = intval( $_meta_value );
			if ( $assignee_id > 0 ) {
				self::send_assignment_notification( $object_id, $assignee_id );
			}
		}
	}

	/**
	 * Handle metadata updates to trigger notifications
	 */
	public static function handle_updated_post_meta( $mid, $object_id, $meta_key, $_meta_value, $_meta_value_prev = '' ) {
		if ( 'ndizi_task' !== get_post_type( $object_id ) ) {
			return;
		}

		if ( '_ndizi_assigned_user_id' === $meta_key ) {
			$new_assignee = intval( $_meta_value );
			$old_assignee = intval( $_meta_value_prev );
			if ( $new_assignee > 0 && $new_assignee !== $old_assignee ) {
				self::send_assignment_notification( $object_id, $new_assignee );
			}
		} elseif ( '_ndizi_task_status' === $meta_key ) {
			$new_status  = sanitize_text_field( $_meta_value );
			$old_status  = sanitize_text_field( $_meta_value_prev );
			$assignee_id = intval( get_post_meta( $object_id, '_ndizi_assigned_user_id', true ) );
			if ( $assignee_id > 0 && ! empty( $old_status ) && $new_status !== $old_status ) {
				self::send_status_change_notification( $object_id, $assignee_id, $old_status, $new_status );
			}
		}
	}

	/**
	 * Send email notification when a task is assigned to a user
	 */
	public static function send_assignment_notification( $task_id, $user_id ) {
		$task_id = intval( $task_id );
		$user_id = intval( $user_id );

		if ( ! $task_id || ! $user_id ) {
			return;
		}

		$task = get_post( $task_id );
		if ( ! $task || 'ndizi_task' !== $task->post_type ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$project_id = get_post_meta( $task_id, '_ndizi_project_id', true );
		$project    = $project_id ? get_post( $project_id ) : null;
		$proj_title = $project ? $project->post_title : __( 'No Project', 'ndizi-project-management' );

		$to = $user->user_email;
		/* translators: %s: task title */
		$subject = sprintf( __( '[Ndizi PM] Task Assigned: %s', 'ndizi-project-management' ), $task->post_title );

		$message  = '<html><body>';
		$message .= '<div style="font-family: sans-serif; max-width: 600px; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;">';
		$message .= '<h2 style="color: #4f46e5; margin-top: 0;">New Task Assignment</h2>';
		/* translators: %s: user display name */
		$message .= sprintf( '<p>' . esc_html__( 'Hello %s,', 'ndizi-project-management' ) . '</p>', esc_html( $user->display_name ) );
		$message .= sprintf( '<p>' . esc_html__( 'You have been assigned to the following task:', 'ndizi-project-management' ) . '</p>' );
		$message .= '<hr style="border: 0; height: 1px; background: #e2e8f0; margin: 20px 0;">';
		$message .= sprintf( '<h3 style="margin-top: 0; color: #1e293b;">%s</h3>', esc_html( $task->post_title ) );
		$message .= sprintf( '<p><strong>Project:</strong> %s</p>', esc_html( $proj_title ) );
		if ( ! empty( $task->post_content ) ) {
			$message .= '<div style="background: #f8fafc; padding: 15px; border-radius: 6px; font-size: 14px; line-height: 1.6; color: #475569; margin-top: 10px;">';
			$message .= wpautop( esc_html( $task->post_content ) );
			$message .= '</div>';
		}
		$message .= '<hr style="border: 0; height: 1px; background: #e2e8f0; margin: 20px 0;">';
		$message .= sprintf( '<p style="font-size: 13px; color: #64748b;"><a href="%s" style="color: #4f46e5; text-decoration: none; font-weight: bold;">View Task details in WordPress &rarr;</a></p>', esc_url( admin_url( 'post.php?post=' . $task_id . '&action=edit' ) ) );
		$message .= '</div>';
		$message .= '</body></html>';

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: Ndizi Project Management <' . get_option( 'admin_email' ) . '>',
		);

		$to      = apply_filters( 'ndizi_task_assign_notification_to', $to, $task_id, $user_id );
		$subject = apply_filters( 'ndizi_task_assign_notification_subject', $subject, $task_id, $user_id );
		$message = apply_filters( 'ndizi_task_assign_notification_message', $message, $task_id, $user_id );
		$headers = apply_filters( 'ndizi_task_assign_notification_headers', $headers, $task_id, $user_id );

		wp_mail( $to, $subject, $message, $headers );
	}

	/**
	 * Send email notification when task status changes
	 */
	public static function send_status_change_notification( $task_id, $user_id, $old_status, $new_status ) {
		$task_id = intval( $task_id );
		$user_id = intval( $user_id );

		if ( ! $task_id || ! $user_id ) {
			return;
		}

		$task = get_post( $task_id );
		if ( ! $task || 'ndizi_task' !== $task->post_type ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$project_id = get_post_meta( $task_id, '_ndizi_project_id', true );
		$project    = $project_id ? get_post( $project_id ) : null;
		$proj_title = $project ? $project->post_title : __( 'No Project', 'ndizi-project-management' );

		$status_labels = array(
			'open'        => __( 'Open', 'ndizi-project-management' ),
			'in_progress' => __( 'In Progress', 'ndizi-project-management' ),
			'completed'   => __( 'Completed', 'ndizi-project-management' ),
			'cancelled'   => __( 'Cancelled', 'ndizi-project-management' ),
		);

		$old_status_label = isset( $status_labels[ $old_status ] ) ? $status_labels[ $old_status ] : $old_status;
		$new_status_label = isset( $status_labels[ $new_status ] ) ? $status_labels[ $new_status ] : $new_status;

		$to = $user->user_email;
		/* translators: %s: task title */
		$subject = sprintf( __( '[Ndizi PM] Task Status Updated: %s', 'ndizi-project-management' ), $task->post_title );

		$message  = '<html><body>';
		$message .= '<div style="font-family: sans-serif; max-width: 600px; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;">';
		$message .= '<h2 style="color: #4f46e5; margin-top: 0;">Task Status Updated</h2>';
		/* translators: %s: user display name */
		$message   .= sprintf( '<p>' . esc_html__( 'Hello %s,', 'ndizi-project-management' ) . '</p>', esc_html( $user->display_name ) );
		$status_msg = sprintf(
			/* translators: 1: old task status, 2: new task status */
			esc_html__( 'The status of your assigned task has been changed from %1$s to %2$s.', 'ndizi-project-management' ),
			'<strong>' . esc_html( $old_status_label ) . '</strong>',
			'<strong>' . esc_html( $new_status_label ) . '</strong>'
		);
		$message .= '<p>' . $status_msg . '</p>';
		$message .= '<hr style="border: 0; height: 1px; background: #e2e8f0; margin: 20px 0;">';
		$message .= sprintf( '<h3 style="margin-top: 0; color: #1e293b;">%s</h3>', esc_html( $task->post_title ) );
		$message .= sprintf( '<p><strong>Project:</strong> %s</p>', esc_html( $proj_title ) );
		$message .= '<hr style="border: 0; height: 1px; background: #e2e8f0; margin: 20px 0;">';
		$message .= sprintf( '<p style="font-size: 13px; color: #64748b;"><a href="%s" style="color: #4f46e5; text-decoration: none; font-weight: bold;">View Task details in WordPress &rarr;</a></p>', esc_url( admin_url( 'post.php?post=' . $task_id . '&action=edit' ) ) );
		$message .= '</div>';
		$message .= '</body></html>';

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: Ndizi Project Management <' . get_option( 'admin_email' ) . '>',
		);

		$to      = apply_filters( 'ndizi_task_status_notification_to', $to, $task_id, $user_id, $old_status, $new_status );
		$subject = apply_filters( 'ndizi_task_status_notification_subject', $subject, $task_id, $user_id, $old_status, $new_status );
		$message = apply_filters( 'ndizi_task_status_notification_message', $message, $task_id, $user_id, $old_status, $new_status );
		$headers = apply_filters( 'ndizi_task_status_notification_headers', $headers, $task_id, $user_id, $old_status, $new_status );

		wp_mail( $to, $subject, $message, $headers );
	}
}
