<?php
/**
 * WP-CLI command handler for Ndizi Project Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ndizi_CLI {

	/**
	 * Register CLI commands
	 */
	public static function init() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'ndizi time', 'Ndizi_CLI' );
		}
	}

	/**
	 * Start a running timer for a project.
	 *
	 * ## OPTIONS
	 *
	 * --project=<project_id_or_title>
	 * : The ID or title of the project to track time against.
	 *
	 * [--task=<task_id_or_title>]
	 * : The ID or title of the task to track time against.
	 *
	 * [--desc=<description>]
	 * : Description of the work.
	 *
	 * [--user=<user_id_or_login>]
	 * : The user logging the time. Defaults to the current user (or first administrator
	 *   when running non-interactively). Logging time as another user requires
	 *   ndizi_manage_time capability.
	 *
	 * [--billable=<billable>]
	 * : Whether the time is billable (1 or 0). Defaults to 1.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ndizi time start --project=123 --desc="Debugging CLI"
	 *     wp ndizi time start --project="Website Redesign" --task="Setup WP" --billable=1
	 */
	public function start( $args, $assoc_args ) {
		$project_string    = isset( $assoc_args['project'] ) ? sanitize_text_field( $assoc_args['project'] ) : '';
		$task_string       = isset( $assoc_args['task'] ) ? sanitize_text_field( $assoc_args['task'] ) : '';
		$description       = isset( $assoc_args['desc'] ) ? sanitize_text_field( $assoc_args['desc'] ) : '';
		$user_string       = isset( $assoc_args['user'] ) ? sanitize_text_field( $assoc_args['user'] ) : '';
		$explicit_user_arg = isset( $assoc_args['user'] );
		$billable          = ! isset( $assoc_args['billable'] ) || (int) $assoc_args['billable'] !== 0;

		$user_id = $this->get_user_id( $user_string );
		if ( ! $user_id ) {
			WP_CLI::error( 'Invalid user specified.' );
		}

		if ( $explicit_user_arg && (int) $user_id !== (int) get_current_user_id() ) {
			if ( ! current_user_can( 'ndizi_manage_time' ) ) {
				WP_CLI::error( 'You need the ndizi_manage_time capability to log time on behalf of another user.' );
			}
		}

		$project_id = $this->get_project_id( $project_string );
		if ( ! $project_id ) {
			WP_CLI::error( sprintf( 'Project "%s" not found.', $project_string ) );
		}

		$task_id = 0;
		if ( ! empty( $task_string ) ) {
			$task_id = $this->get_task_id( $task_string, $project_id );
			if ( ! $task_id ) {
				WP_CLI::error( sprintf( 'Task "%s" not found for project.', $task_string ) );
			}
		}

		// Check if active timer is running.
		$active = Ndizi_DB::get_active_timer( $user_id );
		if ( $active ) {
			WP_CLI::line( 'Stopping already active timer first...' );
		}

		$timer_id = Ndizi_DB::start_timer( $user_id, $project_id, $task_id, $description, $billable );
		if ( $timer_id ) {
			WP_CLI::success( sprintf( 'Timer started for project ID %d (Timer Entry ID: %d).', $project_id, $timer_id ) );
		} else {
			WP_CLI::error( 'Failed to start timer.' );
		}
	}

	/**
	 * Stop a running timer.
	 *
	 * ## OPTIONS
	 *
	 * [--user=<user_id_or_login>]
	 * : The user to stop the timer for. Stopping another user's timer requires
	 *   ndizi_manage_time capability.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ndizi time stop
	 *     wp ndizi time stop --user=admin
	 */
	public function stop( $args, $assoc_args ) {
		$user_string       = isset( $assoc_args['user'] ) ? sanitize_text_field( $assoc_args['user'] ) : '';
		$explicit_user_arg = isset( $assoc_args['user'] );

		$user_id = $this->get_user_id( $user_string );
		if ( ! $user_id ) {
			WP_CLI::error( 'Invalid user specified.' );
		}

		if ( $explicit_user_arg && (int) $user_id !== (int) get_current_user_id() ) {
			if ( ! current_user_can( 'ndizi_manage_time' ) ) {
				WP_CLI::error( 'You need the ndizi_manage_time capability to stop another user\'s timer.' );
			}
		}

		$active = Ndizi_DB::get_active_timer( $user_id );
		if ( ! $active ) {
			WP_CLI::warning( 'No active timer running for this user.' );
			return;
		}

		$stopped = Ndizi_DB::stop_timer( $user_id );
		if ( $stopped ) {
			$hours = round( $stopped->duration / 3600, 2 );
			WP_CLI::success( sprintf( 'Timer stopped. Total duration: %s hours (%d seconds).', $hours, $stopped->duration ) );
		} else {
			WP_CLI::error( 'Failed to stop timer.' );
		}
	}

	/**
	 * Show status of the current running timer.
	 *
	 * ## OPTIONS
	 *
	 * [--user=<user_id_or_login>]
	 * : The user to check the timer status for.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ndizi time status
	 *     wp ndizi time status --user=admin
	 */
	public function status( $args, $assoc_args ) {
		$user_string = isset( $assoc_args['user'] ) ? sanitize_text_field( $assoc_args['user'] ) : '';

		$user_id = $this->get_user_id( $user_string );
		if ( ! $user_id ) {
			WP_CLI::error( 'Invalid user specified.' );
		}

		$active = Ndizi_DB::get_active_timer( $user_id );
		if ( ! $active ) {
			WP_CLI::line( 'No active timer running.' );
			return;
		}

		$project    = get_post( $active->project_id );
		$proj_title = $project ? $project->post_title : 'Unknown';
		$task_title = '-';
		if ( $active->task_id ) {
			$task       = get_post( $active->task_id );
			$task_title = $task ? $task->post_title : 'Unknown';
		}

		$now_ts   = time();
		$start_ts = strtotime( $active->start_time );
		$duration = max( 0, $now_ts - $start_ts );
		$hours    = round( $duration / 3600, 2 );

		WP_CLI::line( 'Active Timer Details:' );
		WP_CLI::line( sprintf( '  Project:     %s (ID: %d)', $proj_title, $active->project_id ) );
		WP_CLI::line( sprintf( '  Task:        %s (ID: %d)', $task_title, $active->task_id ) );
		WP_CLI::line( sprintf( '  Description: %s', $active->description ) );
		WP_CLI::line( sprintf( '  Started At:  %s', $active->start_time ) );
		WP_CLI::line( sprintf( '  Duration:    %s hours (%d seconds)', $hours, $duration ) );
		WP_CLI::line( sprintf( '  Billable:    %s', $active->billable ? 'Yes' : 'No' ) );
	}

	/**
	 * Helper: Resolve user ID from ID, login, or email
	 */
	private function get_user_id( $user_string ) {
		if ( empty( $user_string ) ) {
			$current_user_id = get_current_user_id();
			if ( $current_user_id ) {
				return $current_user_id;
			}
			$users = get_users(
				array(
					'role'   => 'administrator',
					'number' => 1,
				)
			);
			if ( ! empty( $users ) ) {
				return $users[0]->ID;
			}
			return 1;
		}

		if ( is_numeric( $user_string ) ) {
			return intval( $user_string );
		}

		$user = get_user_by( 'login', $user_string );
		if ( $user ) {
			return $user->ID;
		}

		$user = get_user_by( 'email', $user_string );
		if ( $user ) {
			return $user->ID;
		}

		return 0;
	}

	/**
	 * Helper: Resolve project ID from ID or Title
	 */
	private function get_project_id( $project_string ) {
		if ( is_numeric( $project_string ) ) {
			$project = get_post( intval( $project_string ) );
			if ( $project && 'ndizi_project' === $project->post_type ) {
				return $project->ID;
			}
		}

		global $wpdb;
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'ndizi_project' AND post_status != 'trash' LIMIT 1",
				$project_string
			)
		);

		return $id ? intval( $id ) : 0;
	}

	/**
	 * Helper: Resolve task ID from ID or Title under a Project
	 */
	private function get_task_id( $task_string, $project_id ) {
		if ( is_numeric( $task_string ) ) {
			$task = get_post( intval( $task_string ) );
			if ( $task && 'ndizi_task' === $task->post_type ) {
				return $task->ID;
			}
		}

		global $wpdb;
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				 WHERE p.post_title = %s
				   AND p.post_type = 'ndizi_task'
				   AND p.post_status != 'trash'
				   AND pm.meta_key = '_ndizi_project_id'
				   AND pm.meta_value = %d
				 LIMIT 1",
				$task_string,
				$project_id
			)
		);

		return $id ? intval( $id ) : 0;
	}
}
