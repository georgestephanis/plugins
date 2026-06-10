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
		$subject = sprintf( __( '[Ndizi PM] New Task Request from %s', 'ndizi' ), $client->post_title );

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
}
