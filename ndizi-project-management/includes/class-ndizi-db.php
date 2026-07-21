<?php
/**
 * Database operations handler for Ndizi Project Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ndizi_DB {

	/**
	 * Get the table name with WordPress prefix
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'ndizi_time_entries';
	}

	/**
	 * Create or upgrade the custom database table
	 */
	public static function create_table() {
		global $wpdb;
		$table_name      = self::get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
  id bigint(20) NOT NULL AUTO_INCREMENT,
  client_id bigint(20) DEFAULT 0,
  project_id bigint(20) DEFAULT 0,
  task_id bigint(20) DEFAULT 0,
  user_id bigint(20) NOT NULL,
  description text NOT NULL,
  start_time datetime NOT NULL,
  end_time datetime DEFAULT NULL,
  duration int(11) DEFAULT 0,
  billable tinyint(1) DEFAULT 1,
  invoice_id bigint(20) DEFAULT 0,
  approved tinyint(1) NOT NULL DEFAULT 0,
  approved_by bigint(20) NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY client_id (client_id),
  KEY project_id (project_id),
  KEY task_id (task_id),
  KEY user_id (user_id),
  KEY invoice_id (invoice_id)
) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get active running timer for a user
	 */
	public static function get_active_timer( $user_id ) {
		global $wpdb;
		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name derives from $wpdb->prefix and cannot be a placeholder; value is prepared; live timer lookup must not be cached.
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE user_id = %d AND end_time IS NULL ORDER BY start_time DESC LIMIT 1", $user_id ) );
	}

	/**
	 * Start a running timer
	 */
	public static function start_timer( $user_id, $project_id, $args = array() ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$defaults = array(
			'client_id'   => 0,
			'task_id'     => 0,
			'description' => '',
			'billable'    => 1,
		);
		$args     = wp_parse_args( $args, $defaults );

		$client_id = intval( $args['client_id'] );
		if ( ! $client_id && $project_id > 0 ) {
			$client_id = intval( get_post_meta( $project_id, '_ndizi_client_id', true ) );
		}

		if ( self::is_date_locked( current_time( 'mysql', true ) ) ) {
			return false;
		}

		// Stop any existing active timers for this user first
		self::stop_timer( $user_id );

		$now = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Writing to the plugin's custom time-entries table.
		$result = $wpdb->insert(
			$table_name,
			array(
				'client_id'   => $client_id,
				'project_id'  => $project_id,
				'task_id'     => intval( $args['task_id'] ),
				'user_id'     => $user_id,
				'description' => sanitize_text_field( $args['description'] ),
				'start_time'  => $now,
				'end_time'    => null,
				'duration'    => 0,
				'billable'    => $args['billable'] ? 1 : 0,
				'invoice_id'  => 0,
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		if ( $result ) {
			$insert_id = $wpdb->insert_id;
			do_action( 'ndizi_timer_started', $insert_id, $user_id, $project_id, $args );
			return $insert_id;
		}

		return false;
	}

	/**
	 * Stop active running timer for a user
	 */
	public static function stop_timer( $user_id ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$active_timer = self::get_active_timer( $user_id );
		if ( ! $active_timer ) {
			return false;
		}

		if ( self::is_date_locked( $active_timer->start_time ) ) {
			return false;
		}

		$now_mysql = current_time( 'mysql', true );
		$now_ts    = strtotime( $now_mysql );
		$start_ts  = strtotime( $active_timer->start_time );
		$duration  = max( 0, $now_ts - $start_ts );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Updating the plugin's custom time-entries table.
		$result = $wpdb->update(
			$table_name,
			array(
				'end_time'   => $now_mysql,
				'duration'   => $duration,
				'updated_at' => $now_mysql,
			),
			array( 'id' => $active_timer->id ),
			array( '%s', '%d', '%s' ),
			array( '%d' )
		);

		if ( $result !== false ) {
			do_action( 'ndizi_timer_stopped', $active_timer->id, $user_id, $duration );
			return self::get_time_entry( $active_timer->id );
		}

		return false;
	}

	/**
	 * Log a manual time entry with explicit duration
	 */
	public static function log_time_manual( $user_id, $project_id, $args = array() ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$defaults = array(
			'client_id'   => 0,
			'task_id'     => 0,
			'description' => '',
			'duration'    => 0,
			'billable'    => 1,
			'start_time'  => '',
			'end_time'    => '',
		);
		$args     = wp_parse_args( $args, $defaults );

		$client_id = intval( $args['client_id'] );
		if ( ! $client_id && $project_id > 0 ) {
			$client_id = intval( get_post_meta( $project_id, '_ndizi_client_id', true ) );
		}

		// All timestamps stored in UTC.
		$now_ts = time();
		$now    = gmdate( 'Y-m-d H:i:s', $now_ts );

		// If start/end times aren't provided, estimate them based on duration
		$start_time = $args['start_time'];
		$end_time   = $args['end_time'];
		$duration   = $args['duration'];

		if ( empty( $start_time ) && empty( $end_time ) ) {
			$duration_sec = intval( $duration );
			$start_time   = gmdate( 'Y-m-d H:i:s', $now_ts - $duration_sec );
			$end_time     = $now;
		} elseif ( empty( $start_time ) ) {
			$duration_sec = intval( $duration );
			$end_ts       = strtotime( $end_time );
			$start_time   = gmdate( 'Y-m-d H:i:s', $end_ts - $duration_sec );
		} elseif ( empty( $end_time ) ) {
			$duration_sec = intval( $duration );
			$start_ts     = strtotime( $start_time );
			$end_time     = gmdate( 'Y-m-d H:i:s', $start_ts + $duration_sec );
		} else {
			// Both are provided, make sure duration matches the difference
			$duration = strtotime( $end_time ) - strtotime( $start_time );
		}

		if ( self::is_date_locked( $start_time ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Writing to the plugin's custom time-entries table.
		$result = $wpdb->insert(
			$table_name,
			array(
				'client_id'   => $client_id,
				'project_id'  => $project_id,
				'task_id'     => intval( $args['task_id'] ),
				'user_id'     => $user_id,
				'description' => sanitize_text_field( $args['description'] ),
				'start_time'  => $start_time,
				'end_time'    => $end_time,
				'duration'    => max( 0, intval( $duration ) ),
				'billable'    => $args['billable'] ? 1 : 0,
				'invoice_id'  => 0,
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		if ( $result ) {
			$insert_id          = $wpdb->insert_id;
			$args['start_time'] = $start_time;
			$args['end_time']   = $end_time;
			$args['duration']   = max( 0, intval( $duration ) );
			do_action( 'ndizi_time_logged', $insert_id, $user_id, $project_id, $args );
			return $insert_id;
		}

		return false;
	}

	/**
	 * Update an existing time entry
	 */
	public static function update_time_entry( $id, $data ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$existing = self::get_time_entry( $id );
		if ( ! $existing ) {
			return false;
		}

		$updating_other_fields = false;
		foreach ( array_keys( $data ) as $key ) {
			if ( 'approved' !== $key && 'approved_by' !== $key ) {
				$updating_other_fields = true;
				break;
			}
		}

		if ( $updating_other_fields ) {
			// Block non-approval edits on approved entries or entries in a locked period.
			if ( $existing->approved ) {
				return false;
			}
			if ( self::is_date_locked( $existing->start_time ) ) {
				return false;
			}
			// Also reject if the caller is trying to move the entry into a locked period.
			if ( isset( $data['start_time'] ) && self::is_date_locked( $data['start_time'] ) ) {
				return false;
			}
		}

		$update_data = array();
		$formats     = array();

		$allowed_keys = array(
			'client_id'   => '%d',
			'project_id'  => '%d',
			'task_id'     => '%d',
			'description' => '%s',
			'start_time'  => '%s',
			'end_time'    => '%s',
			'duration'    => '%d',
			'billable'    => '%d',
			'invoice_id'  => '%d',
			'approved'    => '%d',
			'approved_by' => '%d',
		);

		foreach ( $allowed_keys as $key => $format ) {
			if ( isset( $data[ $key ] ) ) {
				$update_data[ $key ] = ( '%d' === $format ) ? intval( $data[ $key ] ) : $data[ $key ];
				$formats[]           = $format;
			}
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		// Update duration if start/end times changed and duration wasn't explicitly passed
		if ( isset( $update_data['start_time'] ) && isset( $update_data['end_time'] ) && ! isset( $update_data['duration'] ) ) {
			$update_data['duration'] = max( 0, strtotime( $update_data['end_time'] ) - strtotime( $update_data['start_time'] ) );
			$formats[]               = '%d';
		}

		$update_data['updated_at'] = current_time( 'mysql', true );
		$formats[]                 = '%s';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Updating the plugin's custom time-entries table.
		$result = $wpdb->update(
			$table_name,
			$update_data,
			array( 'id' => $id ),
			$formats,
			array( '%d' )
		);

		if ( $result !== false ) {
			do_action( 'ndizi_time_entry_updated', $id, $update_data );
			return true;
		}

		return false;
	}

	/**
	 * Get a single time entry
	 */
	public static function get_time_entry( $id ) {
		global $wpdb;
		$table_name = self::get_table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name derives from $wpdb->prefix and cannot be a placeholder; id is prepared; row read directly from the custom table.
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $id ) );
	}

	/**
	 * Delete a time entry
	 */
	public static function delete_time_entry( $id ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$existing = self::get_time_entry( $id );
		if ( ! $existing ) {
			return false;
		}

		if ( self::is_date_locked( $existing->start_time ) || $existing->approved ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Deleting from the plugin's custom time-entries table.
		$result = $wpdb->delete(
			$table_name,
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( $result ) {
			do_action( 'ndizi_time_entry_deleted', $id );
		}

		return $result;
	}

	/**
	 * Query time entries with filters
	 */
	public static function get_time_entries( $args = array() ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$defaults = array(
			'client_id'  => null,
			'project_id' => null,
			'task_id'    => null,
			'user_id'    => null,
			'invoice_id' => null,
			'billable'   => null,
			'start_date' => null,
			'end_date'   => null,
			'approved'   => null,
			'search'     => null,
			'orderby'    => 'start_time',
			'order'      => 'DESC',
			'number'     => 50,
			'offset'     => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where      = array( '1=1' );
		$query_args = array();

		if ( null !== $args['client_id'] ) {
			$where[]      = 'client_id = %d';
			$query_args[] = intval( $args['client_id'] );
		}

		if ( null !== $args['project_id'] ) {
			$where[]      = 'project_id = %d';
			$query_args[] = intval( $args['project_id'] );
		}

		if ( null !== $args['task_id'] ) {
			$where[]      = 'task_id = %d';
			$query_args[] = intval( $args['task_id'] );
		}

		if ( null !== $args['user_id'] ) {
			$where[]      = 'user_id = %d';
			$query_args[] = intval( $args['user_id'] );
		}

		if ( null !== $args['invoice_id'] ) {
			$where[]      = 'invoice_id = %d';
			$query_args[] = intval( $args['invoice_id'] );
		}

		if ( null !== $args['billable'] ) {
			$where[]      = 'billable = %d';
			$query_args[] = $args['billable'] ? 1 : 0;
		}

		if ( null !== $args['approved'] ) {
			$where[]      = 'approved = %d';
			$query_args[] = $args['approved'] ? 1 : 0;
		}

		if ( ! empty( $args['start_date'] ) ) {
			$where[]      = 'start_time >= %s';
			$query_args[] = $args['start_date'] . ' 00:00:00';
		}

		if ( ! empty( $args['end_date'] ) ) {
			$where[]      = 'start_time <= %s';
			$query_args[] = $args['end_date'] . ' 23:59:59';
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]      = 'description LIKE %s';
			$query_args[] = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
		}

		$where_str = implode( ' AND ', $where );

		$allowed_orderby = array( 'id', 'client_id', 'project_id', 'task_id', 'user_id', 'start_time', 'end_time', 'duration', 'billable' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'start_time';
		$order           = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		// The Time Entries admin screen merges project and task into one column;
		// sorting it groups by project first, then task as a secondary key.
		$order_clause = ( 'project_id' === $orderby )
			? "project_id $order, task_id $order"
			: "$orderby $order";

		$sql = "SELECT * FROM $table_name WHERE $where_str ORDER BY $order_clause";

		if ( $args['number'] > 0 ) {
			$sql         .= ' LIMIT %d OFFSET %d';
			$query_args[] = intval( $args['number'] );
			$query_args[] = intval( $args['offset'] );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is assembled from prepared placeholders and an allowlisted ORDER BY; custom-table reporting read.
		if ( ! empty( $query_args ) ) {
			return $wpdb->get_results( $wpdb->prepare( $sql, $query_args ) );
		}

		return $wpdb->get_results( $sql );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * Get total count of time entries matching filters
	 */
	public static function get_time_entries_count( $args = array() ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$defaults = array(
			'client_id'  => null,
			'project_id' => null,
			'task_id'    => null,
			'user_id'    => null,
			'invoice_id' => null,
			'billable'   => null,
			'start_date' => null,
			'end_date'   => null,
			'approved'   => null,
			'search'     => null,
		);

		$args = wp_parse_args( $args, $defaults );

		$where      = array( '1=1' );
		$query_args = array();

		if ( null !== $args['client_id'] ) {
			$where[]      = 'client_id = %d';
			$query_args[] = intval( $args['client_id'] );
		}

		if ( null !== $args['project_id'] ) {
			$where[]      = 'project_id = %d';
			$query_args[] = intval( $args['project_id'] );
		}

		if ( null !== $args['task_id'] ) {
			$where[]      = 'task_id = %d';
			$query_args[] = intval( $args['task_id'] );
		}

		if ( null !== $args['user_id'] ) {
			$where[]      = 'user_id = %d';
			$query_args[] = intval( $args['user_id'] );
		}

		if ( null !== $args['invoice_id'] ) {
			$where[]      = 'invoice_id = %d';
			$query_args[] = intval( $args['invoice_id'] );
		}

		if ( null !== $args['billable'] ) {
			$where[]      = 'billable = %d';
			$query_args[] = $args['billable'] ? 1 : 0;
		}

		if ( null !== $args['approved'] ) {
			$where[]      = 'approved = %d';
			$query_args[] = $args['approved'] ? 1 : 0;
		}

		if ( ! empty( $args['start_date'] ) ) {
			$where[]      = 'start_time >= %s';
			$query_args[] = $args['start_date'] . ' 00:00:00';
		}

		if ( ! empty( $args['end_date'] ) ) {
			$where[]      = 'start_time <= %s';
			$query_args[] = $args['end_date'] . ' 23:59:59';
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]      = 'description LIKE %s';
			$query_args[] = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
		}

		$where_str = implode( ' AND ', $where );
		$sql       = "SELECT COUNT(*) FROM $table_name WHERE $where_str";

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is assembled from prepared placeholders over a custom table; count read for reporting.
		if ( ! empty( $query_args ) ) {
			return intval( $wpdb->get_var( $wpdb->prepare( $sql, $query_args ) ) );
		}

		return intval( $wpdb->get_var( $sql ) );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * Get aggregated totals for time entries (e.g. for reports)
	 */
	public static function get_time_totals( $args = array() ) {
		global $wpdb;
		$table_name = self::get_table_name();

		$defaults = array(
			'client_id'  => null,
			'project_id' => null,
			'user_id'    => null,
			'invoice_id' => null,
			'start_date' => null,
			'end_date'   => null,
			'groupby'    => 'project_id', // can be client_id, project_id, user_id, task_id, or day
		);

		$args = wp_parse_args( $args, $defaults );

		$where      = array( '1=1' );
		$query_args = array();

		if ( null !== $args['client_id'] ) {
			$where[]      = 'client_id = %d';
			$query_args[] = intval( $args['client_id'] );
		}

		if ( null !== $args['project_id'] ) {
			$where[]      = 'project_id = %d';
			$query_args[] = intval( $args['project_id'] );
		}

		if ( null !== $args['invoice_id'] ) {
			$where[]      = 'invoice_id = %d';
			$query_args[] = intval( $args['invoice_id'] );
		}

		if ( null !== $args['user_id'] ) {
			$where[]      = 'user_id = %d';
			$query_args[] = intval( $args['user_id'] );
		}

		if ( ! empty( $args['start_date'] ) ) {
			$where[]      = 'start_time >= %s';
			$query_args[] = $args['start_date'] . ' 00:00:00';
		}

		if ( ! empty( $args['end_date'] ) ) {
			$where[]      = 'start_time <= %s';
			$query_args[] = $args['end_date'] . ' 23:59:59';
		}

		$where_str = implode( ' AND ', $where );

		switch ( $args['groupby'] ) {
			case 'client_id':
				$select  = 'client_id as group_id, SUM(duration) as total_duration, SUM(CASE WHEN billable = 1 THEN duration ELSE 0 END) as billable_duration';
				$groupby = 'client_id';
				break;
			case 'user_id':
				$select  = 'user_id as group_id, SUM(duration) as total_duration, SUM(CASE WHEN billable = 1 THEN duration ELSE 0 END) as billable_duration';
				$groupby = 'user_id';
				break;
			case 'task_id':
				$select  = 'task_id as group_id, SUM(duration) as total_duration, SUM(CASE WHEN billable = 1 THEN duration ELSE 0 END) as billable_duration';
				$groupby = 'task_id';
				break;
			case 'day':
				$select  = 'DATE(start_time) as group_id, SUM(duration) as total_duration, SUM(CASE WHEN billable = 1 THEN duration ELSE 0 END) as billable_duration';
				$groupby = 'DATE(start_time)';
				break;
			case 'project_id':
			default:
				$select  = 'project_id as group_id, SUM(duration) as total_duration, SUM(CASE WHEN billable = 1 THEN duration ELSE 0 END) as billable_duration';
				$groupby = 'project_id';
				break;
		}

		$sql = "SELECT $select FROM $table_name WHERE $where_str GROUP BY $groupby";

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $sql is assembled from prepared placeholders and an allowlisted SELECT/GROUP BY; custom-table reporting aggregate.
		if ( ! empty( $query_args ) ) {
			return $wpdb->get_results( $wpdb->prepare( $sql, $query_args ) );
		}

		return $wpdb->get_results( $sql );
		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * Check if a date string falls on or before the lock date option.
	 */
	public static function is_date_locked( $date_string ) {
		$lock_date = get_option( 'ndizi_lock_date' );
		if ( empty( $lock_date ) ) {
			return false;
		}

		$lock_time  = strtotime( $lock_date . ' 23:59:59' );
		$check_time = strtotime( $date_string );

		if ( false === $lock_time || false === $check_time ) {
			return false;
		}

		return $check_time <= $lock_time;
	}
}
