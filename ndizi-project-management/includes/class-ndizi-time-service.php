<?php
/**
 * Shared service/validation layer for all time-entry write operations.
 *
 * All four write paths (REST, Admin AJAX, Abilities, CLI) are thin adapters
 * over the three methods here.  Business rules (project-assignment check,
 * date-lock guard, duration validation) live in exactly one place.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ndizi_Time_Service {

	/**
	 * Verify the given user is allowed to track time against a project/task.
	 *
	 * @param int $project_id Project post ID.
	 * @param int $task_id    Task post ID (0 = no specific task).
	 * @param int $user_id    User ID.
	 * @return true|WP_Error True on success, WP_Error describing the problem.
	 */
	public static function validate_time_project_access( $project_id, $task_id, $user_id ) {
		if ( ! $project_id || 'ndizi_project' !== get_post_type( $project_id ) ) {
			return new WP_Error( 'invalid_project', __( 'Invalid project ID.', 'ndizi-project-management' ) );
		}

		if ( 'active' !== get_post_meta( $project_id, '_ndizi_project_status', true ) ) {
			return new WP_Error( 'project_not_active', __( 'Cannot track time on an inactive project.', 'ndizi-project-management' ) );
		}

		if ( ! Ndizi_Roles::current_user_can( 'ndizi_manage_projects' ) ) {
			$user_tasks = get_posts(
				array(
					'post_type'      => 'ndizi_task',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_query'     => array(
						array(
							'key'   => '_ndizi_project_id',
							'value' => $project_id,
						),
						array(
							'key'   => '_ndizi_assigned_user_id',
							'value' => $user_id,
						),
					),
				)
			);
			if ( empty( $user_tasks ) ) {
				return new WP_Error( 'project_not_assigned', __( 'You must be assigned to tasks in this project to track time.', 'ndizi-project-management' ) );
			}
		}

		if ( $task_id ) {
			if ( 'ndizi_task' !== get_post_type( $task_id ) || (int) get_post_meta( $task_id, '_ndizi_project_id', true ) !== $project_id ) {
				return new WP_Error( 'invalid_task', __( 'Invalid task ID or task does not belong to the specified project.', 'ndizi-project-management' ) );
			}

			if ( ! Ndizi_Roles::current_user_can( 'ndizi_manage_tasks' ) ) {
				$assigned_user = (int) get_post_meta( $task_id, '_ndizi_assigned_user_id', true );
				if ( $assigned_user !== $user_id ) {
					return new WP_Error( 'task_not_assigned', __( 'You are not assigned to this task.', 'ndizi-project-management' ) );
				}
			}
		}

		return true;
	}

	/**
	 * Start a timer for a user.
	 *
	 * Validates project/task access and the date-lock guard before inserting.
	 *
	 * @param int    $user_id     User logging the time.
	 * @param int    $project_id  Project post ID.
	 * @param int    $task_id     Task post ID (0 = general).
	 * @param string $description Work description.
	 * @param int    $billable    1 = billable, 0 = non-billable.
	 * @return int|WP_Error New timer entry ID on success, WP_Error on failure.
	 */
	public static function start_timer( $user_id, $project_id, $task_id, $description, $billable ) {
		$access = self::validate_time_project_access( $project_id, $task_id, $user_id );
		if ( is_wp_error( $access ) ) {
			return $access;
		}

		if ( Ndizi_DB::is_date_locked( current_time( 'mysql', true ) ) ) {
			return new WP_Error( 'date_locked', __( 'Cannot start timer. The current date is locked.', 'ndizi-project-management' ) );
		}

		$timer_id = Ndizi_DB::start_timer( $user_id, $project_id, $task_id, $description, $billable );
		if ( ! $timer_id ) {
			return new WP_Error( 'db_error', __( 'Failed to start timer.', 'ndizi-project-management' ) );
		}

		return $timer_id;
	}

	/**
	 * Stop the active timer for a user.
	 *
	 * @param int $user_id User whose timer should be stopped.
	 * @return object|WP_Error Stopped timer row on success, WP_Error on failure.
	 */
	public static function stop_timer( $user_id ) {
		$active = Ndizi_DB::get_active_timer( $user_id );

		if ( ! $active ) {
			return new WP_Error( 'no_active_timer', __( 'No active timer found.', 'ndizi-project-management' ) );
		}

		if ( Ndizi_DB::is_date_locked( $active->start_time ) ) {
			return new WP_Error( 'date_locked', __( 'Cannot stop timer. The timer start time falls in a locked period.', 'ndizi-project-management' ) );
		}

		$stopped = Ndizi_DB::stop_timer( $user_id );
		if ( ! $stopped ) {
			return new WP_Error( 'db_error', __( 'Failed to stop timer.', 'ndizi-project-management' ) );
		}

		return $stopped;
	}

	/**
	 * Log a manual time entry.
	 *
	 * Validates project/task access, duration, and the date-lock guard.
	 *
	 * @param int    $user_id     User logging the time.
	 * @param int    $project_id  Project post ID.
	 * @param int    $task_id     Task post ID (0 = general).
	 * @param string $description Work description.
	 * @param int    $duration    Duration in seconds (must be > 0).
	 * @param int    $billable    1 = billable, 0 = non-billable.
	 * @param string $start_time  UTC datetime string; defaults to now.
	 * @return int|WP_Error New entry ID on success, WP_Error on failure.
	 */
	public static function log_time_manual( $user_id, $project_id, $task_id, $description, $duration, $billable, $start_time = '' ) {
		$access = self::validate_time_project_access( $project_id, $task_id, $user_id );
		if ( is_wp_error( $access ) ) {
			return $access;
		}

		if ( $duration <= 0 ) {
			return new WP_Error( 'invalid_duration', __( 'Duration must be greater than zero.', 'ndizi-project-management' ) );
		}

		$check_time = empty( $start_time ) ? current_time( 'mysql', true ) : $start_time;
		if ( Ndizi_DB::is_date_locked( $check_time ) ) {
			return new WP_Error( 'date_locked', __( 'Cannot log time. The target start date is locked.', 'ndizi-project-management' ) );
		}

		$entry_id = Ndizi_DB::log_time_manual( $user_id, $project_id, $task_id, $description, $duration, $billable, $start_time );
		if ( ! $entry_id ) {
			return new WP_Error( 'db_error', __( 'Failed to log time.', 'ndizi-project-management' ) );
		}

		return $entry_id;
	}
}
