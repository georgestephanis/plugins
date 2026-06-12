<?php
/**
 * Custom list table columns for Ndizi Project Management CPTs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ndizi_List_Tables {

	/**
	 * Initialize list table hooks
	 */
	public static function init() {
		add_filter( 'manage_ndizi_client_posts_columns', array( __CLASS__, 'add_client_columns' ) );
		add_action( 'manage_ndizi_client_posts_custom_column', array( __CLASS__, 'render_client_columns' ), 10, 2 );

		add_filter( 'manage_ndizi_project_posts_columns', array( __CLASS__, 'add_project_columns' ) );
		add_action( 'manage_ndizi_project_posts_custom_column', array( __CLASS__, 'render_project_columns' ), 10, 2 );

		add_filter( 'manage_ndizi_task_posts_columns', array( __CLASS__, 'add_task_columns' ) );
		add_action( 'manage_ndizi_task_posts_custom_column', array( __CLASS__, 'render_task_columns' ), 10, 2 );

		add_filter( 'manage_ndizi_invoice_posts_columns', array( __CLASS__, 'add_invoice_columns' ) );
		add_action( 'manage_ndizi_invoice_posts_custom_column', array( __CLASS__, 'render_invoice_columns' ), 10, 2 );

		add_filter( 'pre_get_posts', array( __CLASS__, 'restrict_posts_query' ) );
	}

	/**
	 * Restrict default wp-admin list query for Team Members
	 */
	public static function restrict_posts_query( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		// Check if we are viewing tasks in the admin list
		if ( 'ndizi_task' === $query->get( 'post_type' ) ) {
			// If Team Member (i.e. cannot manage tasks), restrict to assigned tasks.
			if ( ! Ndizi_Roles::current_user_can( 'ndizi_manage_tasks' ) ) {
				// Merge into any existing meta_query rather than overwriting it,
				// so we don't clobber core/other-plugin list filters.
				$meta_query = $query->get( 'meta_query' );
				if ( ! is_array( $meta_query ) ) {
					$meta_query = array();
				}
				$meta_query[] = array(
					'key'   => '_ndizi_assigned_user_id',
					'value' => get_current_user_id(),
				);
				$query->set( 'meta_query', $meta_query );
			}
		}
	}

	/**
	 * Add custom columns to Clients list
	 */
	public static function add_client_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			if ( 'date' === $key ) {
				$new_columns['projects_count'] = __( 'Projects', 'ndizi-project-management' );
				$new_columns['client_status']  = __( 'Status', 'ndizi-project-management' );
				$new_columns['client_key']     = __( 'Portal Key', 'ndizi-project-management' );
			}
			$new_columns[ $key ] = $title;
		}
		return $new_columns;
	}

	/**
	 * Render custom column contents for Clients list
	 */
	public static function render_client_columns( $column, $post_id ) {
		if ( 'projects_count' === $column ) {
			$projects = get_posts(
				array(
					'post_type'              => 'ndizi_project',
					'posts_per_page'         => -1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'meta_query'             => array(
						array(
							'key'   => '_ndizi_client_id',
							'value' => $post_id,
						),
					),
				)
			);
			echo count( $projects );
		} elseif ( 'client_status' === $column ) {
			$status       = get_post_meta( $post_id, '_ndizi_client_status', true );
			$status_label = ( 'archived' === $status ) ? __( 'Archived', 'ndizi-project-management' ) : __( 'Active', 'ndizi-project-management' );
			$status_class = ( 'archived' === $status ) ? 'ndizi-badge-archived' : 'ndizi-badge-active';
			echo '<span class="ndizi-badge ' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span>';
		} elseif ( 'client_key' === $column ) {
			$key = get_post_meta( $post_id, '_ndizi_client_auth_key', true );
			echo '<code>' . esc_html( $key ? $key : '-' ) . '</code>';
		}
	}

	/**
	 * Add custom columns to Projects list
	 */
	public static function add_project_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			if ( 'date' === $key ) {
				$new_columns['project_client'] = __( 'Client', 'ndizi-project-management' );
				$new_columns['project_status'] = __( 'Status', 'ndizi-project-management' );
				$new_columns['project_time']   = __( 'Time Tracked', 'ndizi-project-management' );
				$new_columns['project_budget'] = __( 'Budget', 'ndizi-project-management' );
			}
			$new_columns[ $key ] = $title;
		}
		return $new_columns;
	}

	/**
	 * Render custom column contents for Projects list
	 */
	public static function render_project_columns( $column, $post_id ) {
		if ( 'project_client' === $column ) {
			$client_id = get_post_meta( $post_id, '_ndizi_client_id', true );
			if ( $client_id ) {
				echo '<a href="' . esc_url( get_edit_post_link( $client_id ) ) . '">' . esc_html( get_the_title( $client_id ) ) . '</a>';
			} else {
				echo '-';
			}
		} elseif ( 'project_status' === $column ) {
			$status       = get_post_meta( $post_id, '_ndizi_project_status', true );
			$status_label = ( 'archived' === $status ) ? __( 'Archived', 'ndizi-project-management' ) : __( 'Active', 'ndizi-project-management' );
			$status_class = ( 'archived' === $status ) ? 'ndizi-badge-archived' : 'ndizi-badge-active';
			echo '<span class="ndizi-badge ' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span>';
		} elseif ( 'project_time' === $column ) {
			static $cached_totals = null;
			if ( null === $cached_totals ) {
				$cached_totals = array();
				global $posts;
				$project_ids = array();
				if ( is_array( $posts ) ) {
					foreach ( $posts as $p ) {
						if ( isset( $p->post_type ) && 'ndizi_project' === $p->post_type ) {
							$project_ids[] = (int) $p->ID;
						}
					}
				}
				if ( ! empty( $project_ids ) ) {
					global $wpdb;
					$table_name       = Ndizi_DB::get_table_name();
					$ids_placeholders = implode( ',', array_fill( 0, count( $project_ids ), '%d' ) );
					$results          = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT project_id, SUM(duration) as total_duration FROM $table_name WHERE project_id IN ($ids_placeholders) GROUP BY project_id", // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
							$project_ids
						)
					);
					if ( is_array( $results ) ) {
						foreach ( $results as $row ) {
							$cached_totals[ (int) $row->project_id ] = (int) $row->total_duration;
						}
					}
				}
			}

			if ( isset( $cached_totals[ $post_id ] ) ) {
				$sec = $cached_totals[ $post_id ];
			} else {
				$totals                    = Ndizi_DB::get_time_totals( array( 'project_id' => $post_id ) );
				$sec                       = ! empty( $totals ) ? $totals[0]->total_duration : 0;
				$cached_totals[ $post_id ] = $sec;
			}
			$hours = round( $sec / 3600, 2 );
			echo esc_html( $hours ) . 'h';
		} elseif ( 'project_budget' === $column ) {
			$budget = get_post_meta( $post_id, '_ndizi_project_budget', true );
			echo $budget ? '$' . esc_html( number_format( $budget, 2 ) ) : '-';
		}
	}

	/**
	 * Add custom columns to Tasks list
	 */
	public static function add_task_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			if ( 'date' === $key ) {
				$new_columns['task_project']  = __( 'Project', 'ndizi-project-management' );
				$new_columns['task_assignee'] = __( 'Assignee', 'ndizi-project-management' );
				$new_columns['task_status']   = __( 'Status', 'ndizi-project-management' );
				$new_columns['task_priority'] = __( 'Priority', 'ndizi-project-management' );
				$new_columns['task_due_date'] = __( 'Due Date', 'ndizi-project-management' );
			}
			$new_columns[ $key ] = $title;
		}
		return $new_columns;
	}

	/**
	 * Render custom column contents for Tasks list
	 */
	public static function render_task_columns( $column, $post_id ) {
		if ( 'task_project' === $column ) {
			$project_id = get_post_meta( $post_id, '_ndizi_project_id', true );
			if ( $project_id ) {
				echo '<a href="' . esc_url( get_edit_post_link( $project_id ) ) . '">' . esc_html( get_the_title( $project_id ) ) . '</a>';
			} else {
				echo '-';
			}
		} elseif ( 'task_assignee' === $column ) {
			$assignee_id = get_post_meta( $post_id, '_ndizi_assigned_user_id', true );
			if ( $assignee_id ) {
				$user = get_userdata( $assignee_id );
				echo $user ? esc_html( $user->display_name ) : '-';
			} else {
				echo '<em>' . esc_html__( 'Unassigned', 'ndizi-project-management' ) . '</em>';
			}
		} elseif ( 'task_status' === $column ) {
			$status = get_post_meta( $post_id, '_ndizi_task_status', true );
			$labels = array(
				'open'        => __( 'Open', 'ndizi-project-management' ),
				'in_progress' => __( 'In Progress', 'ndizi-project-management' ),
				'completed'   => __( 'Completed', 'ndizi-project-management' ),
				'cancelled'   => __( 'Cancelled', 'ndizi-project-management' ),
			);
			$label  = isset( $labels[ $status ] ) ? $labels[ $status ] : __( 'Open', 'ndizi-project-management' );
			echo '<span class="ndizi-badge ndizi-task-' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
		} elseif ( 'task_priority' === $column ) {
			$priority = get_post_meta( $post_id, '_ndizi_task_priority', true );
			$labels   = array(
				'low'    => __( 'Low', 'ndizi-project-management' ),
				'medium' => __( 'Medium', 'ndizi-project-management' ),
				'high'   => __( 'High', 'ndizi-project-management' ),
			);
			$label    = isset( $labels[ $priority ] ) ? $labels[ $priority ] : __( 'Medium', 'ndizi-project-management' );
			echo '<span class="ndizi-priority-' . esc_attr( $priority ) . '">' . esc_html( $label ) . '</span>';
		} elseif ( 'task_due_date' === $column ) {
			$due = get_post_meta( $post_id, '_ndizi_task_due_date', true );
			echo $due ? esc_html( $due ) : '-';
		}
	}

	/**
	 * Add custom columns to Invoices list
	 */
	public static function add_invoice_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			if ( 'date' === $key ) {
				$new_columns['invoice_project'] = __( 'Project', 'ndizi-project-management' );
				$new_columns['invoice_status']  = __( 'Status', 'ndizi-project-management' );
				$new_columns['invoice_amount']  = __( 'Amount', 'ndizi-project-management' );
				$new_columns['invoice_due']     = __( 'Due Date', 'ndizi-project-management' );
			}
			$new_columns[ $key ] = $title;
		}
		return $new_columns;
	}

	/**
	 * Render custom column contents for Invoices list
	 */
	public static function render_invoice_columns( $column, $post_id ) {
		if ( 'invoice_project' === $column ) {
			$project_id = get_post_meta( $post_id, '_ndizi_project_id', true );
			if ( $project_id ) {
				echo '<a href="' . esc_url( get_edit_post_link( $project_id ) ) . '">' . esc_html( get_the_title( $project_id ) ) . '</a>';
			} else {
				echo '-';
			}
		} elseif ( 'invoice_status' === $column ) {
			$status = get_post_meta( $post_id, '_ndizi_invoice_status', true );
			$labels = array(
				'draft' => __( 'Draft', 'ndizi-project-management' ),
				'sent'  => __( 'Sent', 'ndizi-project-management' ),
				'paid'  => __( 'Paid', 'ndizi-project-management' ),
				'void'  => __( 'Void', 'ndizi-project-management' ),
			);
			$label  = isset( $labels[ $status ] ) ? $labels[ $status ] : __( 'Draft', 'ndizi-project-management' );
			echo '<span class="ndizi-badge ndizi-invoice-' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
		} elseif ( 'invoice_amount' === $column ) {
			$amount = get_post_meta( $post_id, '_ndizi_invoice_amount', true );
			echo $amount ? '$' . esc_html( number_format( $amount, 2 ) ) : '-';
		} elseif ( 'invoice_due' === $column ) {
			$due = get_post_meta( $post_id, '_ndizi_invoice_due_date', true );
			echo $due ? esc_html( $due ) : '-';
		}
	}
}
