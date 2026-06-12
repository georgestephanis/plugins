<?php
/**
 * Google Calendar Integration handler for Ndizi Project Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ndizi_Calendar {

	/**
	 * Initialize hooks
	 */
	public static function init() {
		add_action( 'save_post_ndizi_task', array( __CLASS__, 'sync_task_to_google' ), 10, 2 );
		add_action( 'ndizi_timer_stopped', array( __CLASS__, 'sync_time_entry_to_google' ), 10, 1 );
		add_action( 'ndizi_time_logged', array( __CLASS__, 'sync_time_entry_to_google' ), 10, 1 );
		add_action( 'ndizi_time_entry_updated', array( __CLASS__, 'sync_time_entry_to_google' ), 10, 1 );
		add_action( 'ndizi_time_entry_deleted', array( __CLASS__, 'delete_time_entry_from_google' ), 10, 1 );
	}

	/**
	 * Get a valid access token for Google API
	 *
	 * @return string|false Access token or false on failure.
	 */
	private static function get_access_token() {
		$refresh_token = get_option( 'ndizi_google_refresh_token', '' );
		if ( ! $refresh_token ) {
			return false;
		}

		$access_token = get_option( 'ndizi_google_access_token', '' );
		$expiry       = (int) get_option( 'ndizi_google_token_expiry', 0 );

		if ( $access_token && $expiry > ( time() + 30 ) ) {
			return $access_token;
		}

		$client_id     = get_option( 'ndizi_google_client_id', '' );
		$client_secret = get_option( 'ndizi_google_client_secret', '' );

		if ( ! $client_id || ! $client_secret ) {
			return false;
		}

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'body' => array(
					'refresh_token' => $refresh_token,
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'grant_type'    => 'refresh_token',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( isset( $body['access_token'] ) ) {
			update_option( 'ndizi_google_access_token', $body['access_token'] );
			if ( isset( $body['expires_in'] ) ) {
				update_option( 'ndizi_google_token_expiry', time() + (int) $body['expires_in'] );
			}
			return $body['access_token'];
		}

		return false;
	}

	/**
	 * Sync a task to Google Calendar on save/update
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function sync_task_to_google( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || 'ndizi_task' !== $post->post_type ) {
			return;
		}

		$access_token = self::get_access_token();
		if ( ! $access_token ) {
			return;
		}

		$due_date = get_post_meta( $post_id, '_ndizi_task_due_date', true );
		$event_id = get_post_meta( $post_id, '_ndizi_gcal_event_id', true );

		if ( ! $due_date ) {
			// If task no longer has a due date but has a synced event, delete it.
			if ( $event_id ) {
				wp_remote_request(
					'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . $event_id,
					array(
						'method'  => 'DELETE',
						'headers' => array(
							'Authorization' => 'Bearer ' . $access_token,
						),
					)
				);
				delete_post_meta( $post_id, '_ndizi_gcal_event_id' );
			}
			return;
		}

		$start_date = $due_date;
		$end_date   = date( 'Y-m-d', strtotime( $due_date . ' +1 day' ) ); // Exclusive.

		$project_id    = get_post_meta( $post_id, '_ndizi_project_id', true );
		$project_title = $project_id ? get_the_title( $project_id ) : '';
		$status        = get_post_meta( $post_id, '_ndizi_task_status', true );

		$summary = sprintf( 'Task Due: %s [%s]', $post->post_title, $status );
		$desc    = '';
		if ( $project_title ) {
			$desc .= sprintf( "Project: %s\n", $project_title );
		}
		if ( ! empty( $post->post_content ) ) {
			$desc .= $post->post_content;
		}

		$body = array(
			'summary'     => $summary,
			'description' => $desc,
			'start'       => array(
				'date' => $start_date,
			),
			'end'         => array(
				'date' => $end_date,
			),
		);

		if ( $event_id ) {
			$url    = 'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . $event_id;
			$method = 'PUT';
		} else {
			$url    = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';
			$method = 'POST';
		}

		$response = wp_remote_request(
			$url,
			array(
				'method'  => $method,
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( ! is_wp_error( $response ) ) {
			$res_body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( isset( $res_body['id'] ) ) {
				update_post_meta( $post_id, '_ndizi_gcal_event_id', $res_body['id'] );
			}
		}
	}

	/**
	 * Sync a time entry to Google Calendar on stop/log/update
	 *
	 * @param int $entry_id Entry ID.
	 */
	public static function sync_time_entry_to_google( $entry_id ) {
		$access_token = self::get_access_token();
		if ( ! $access_token ) {
			return;
		}

		$entry = Ndizi_DB::get_time_entry( $entry_id );
		if ( ! $entry || ! $entry->end_time ) {
			return;
		}

		$event_id = get_option( 'ndizi_gcal_time_entry_' . $entry_id, '' );

		$project_title = get_the_title( $entry->project_id );
		$task_title    = $entry->task_id ? get_the_title( $entry->task_id ) : '';

		if ( ! empty( $entry->description ) ) {
			$summary = $entry->description;
		} else {
			$summary = $project_title;
			if ( $task_title ) {
				$summary .= ' - ' . $task_title;
			}
		}

		$summary = 'Tracked: ' . $summary;

		$desc = sprintf( "Project: %s\n", $project_title );
		if ( $task_title ) {
			$desc .= sprintf( "Task: %s\n", $task_title );
		}

		$h     = floor( $entry->duration / 3600 );
		$m     = floor( ( $entry->duration % 3600 ) / 60 );
		$desc .= sprintf( 'Duration: %02d:%02d', $h, $m );

		$start_iso = get_gmt_from_date( $entry->start_time, 'c' );
		$end_iso   = get_gmt_from_date( $entry->end_time, 'c' );

		$body = array(
			'summary'     => $summary,
			'description' => $desc,
			'start'       => array(
				'dateTime' => $start_iso,
			),
			'end'         => array(
				'dateTime' => $end_iso,
			),
		);

		if ( $event_id ) {
			$url    = 'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . $event_id;
			$method = 'PUT';
		} else {
			$url    = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';
			$method = 'POST';
		}

		$response = wp_remote_request(
			$url,
			array(
				'method'  => $method,
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( ! is_wp_error( $response ) ) {
			$res_body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( isset( $res_body['id'] ) ) {
				update_option( 'ndizi_gcal_time_entry_' . $entry_id, $res_body['id'] );
			}
		}
	}

	/**
	 * Delete a time entry from Google Calendar
	 *
	 * @param int $entry_id Entry ID.
	 */
	public static function delete_time_entry_from_google( $entry_id ) {
		$event_id = get_option( 'ndizi_gcal_time_entry_' . $entry_id, '' );
		if ( ! $event_id ) {
			return;
		}

		$access_token = self::get_access_token();
		if ( ! $access_token ) {
			return;
		}

		wp_remote_request(
			'https://www.googleapis.com/calendar/v3/calendars/primary/events/' . $event_id,
			array(
				'method'  => 'DELETE',
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
				),
			)
		);

		delete_option( 'ndizi_gcal_time_entry_' . $entry_id );
	}
}
