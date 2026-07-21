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

		add_filter( 'manage_ndizi_contact_posts_columns', array( __CLASS__, 'add_contact_columns' ) );
		add_action( 'manage_ndizi_contact_posts_custom_column', array( __CLASS__, 'render_contact_columns' ), 10, 2 );

		add_filter( 'manage_ndizi_time_off_posts_columns', array( __CLASS__, 'add_time_off_columns' ) );
		add_action( 'manage_ndizi_time_off_posts_custom_column', array( __CLASS__, 'render_time_off_columns' ), 10, 2 );

		add_filter( 'default_hidden_columns', array( __CLASS__, 'set_default_hidden_columns' ), 10, 2 );

		add_filter( 'pre_get_posts', array( __CLASS__, 'restrict_posts_query' ) );

		add_filter( 'post_row_actions', array( __CLASS__, 'add_client_copy_link_action' ), 10, 2 );

		add_action( 'restrict_manage_posts', array( __CLASS__, 'render_list_filters' ), 10, 2 );
	}

	/**
	 * Get project IDs belonging to a client, cached per request.
	 */
	public static function get_client_project_ids( $client_id ) {
		static $cache = array();
		$client_id    = (int) $client_id;
		if ( isset( $cache[ $client_id ] ) ) {
			return $cache[ $client_id ];
		}

		$project_ids = get_posts(
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
						'value' => $client_id,
					),
				),
			)
		);

		$cache[ $client_id ] = $project_ids;
		return $project_ids;
	}

	/**
	 * Get invoice IDs belonging to a client directly, or via one of the client's projects, cached per request.
	 */
	public static function get_client_invoice_ids( $client_id ) {
		static $cache = array();
		$client_id    = (int) $client_id;
		if ( isset( $cache[ $client_id ] ) ) {
			return $cache[ $client_id ];
		}

		$project_ids = self::get_client_project_ids( $client_id );

		$meta_query   = array( 'relation' => 'OR' );
		$meta_query[] = array(
			'key'   => '_ndizi_client_id',
			'value' => $client_id,
		);
		if ( ! empty( $project_ids ) ) {
			$meta_query[] = array(
				'key'     => '_ndizi_project_id',
				'value'   => $project_ids,
				'compare' => 'IN',
			);
		}

		$invoice_ids = get_posts(
			array(
				'post_type'              => 'ndizi_invoice',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => true,
				'update_post_term_cache' => false,
				'meta_query'             => $meta_query,
			)
		);

		$cache[ $client_id ] = $invoice_ids;
		return $invoice_ids;
	}

	/**
	 * Render list-table filter dropdowns (Clients status, Projects/Tasks/Invoices client, Tasks project).
	 */
	public static function render_list_filters( $post_type, $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		switch ( $post_type ) {
			case 'ndizi_client':
				self::render_client_status_filter();
				break;
			case 'ndizi_project':
			case 'ndizi_invoice':
				self::render_client_filter_dropdown();
				break;
			case 'ndizi_task':
				self::render_project_filter_dropdown();
				self::render_client_filter_dropdown();
				break;
		}
	}

	/**
	 * Render the Clients list status filter dropdown.
	 */
	private static function render_client_status_filter() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off distinct-value lookup to populate an admin filter dropdown.
		$known_statuses = $wpdb->get_col( "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_ndizi_client_status'" );
		$other_statuses = array_diff( array_unique( array_filter( (array) $known_statuses ) ), array( 'active', 'archived' ) );
		sort( $other_statuses );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-table filter, not a form submission.
		$selected = isset( $_GET['ndizi_filter_status'] ) ? sanitize_key( wp_unslash( $_GET['ndizi_filter_status'] ) ) : 'active';

		echo '<select name="ndizi_filter_status">';
		printf( '<option value="active" %s>%s</option>', selected( $selected, 'active', false ), esc_html__( 'Active', 'ndizi-project-management' ) );
		printf( '<option value="archived" %s>%s</option>', selected( $selected, 'archived', false ), esc_html__( 'Archived', 'ndizi-project-management' ) );
		foreach ( $other_statuses as $status ) {
			printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $status ), selected( $selected, $status, false ), esc_html( ucwords( str_replace( array( '_', '-' ), ' ', $status ) ) ) );
		}
		printf( '<option value="all" %s>%s</option>', selected( $selected, 'all', false ), esc_html__( 'All', 'ndizi-project-management' ) );
		echo '</select>';
	}

	/**
	 * Render a Client filter dropdown, reused on Projects, Tasks, and Invoices lists.
	 */
	private static function render_client_filter_dropdown() {
		$clients = get_posts(
			array(
				'post_type'              => 'ndizi_client',
				'posts_per_page'         => -1,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		if ( empty( $clients ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-table filter, not a form submission.
		$selected = isset( $_GET['ndizi_filter_client'] ) ? absint( $_GET['ndizi_filter_client'] ) : 0;

		echo '<select name="ndizi_filter_client">';
		printf( '<option value="0">%s</option>', esc_html__( 'All Clients', 'ndizi-project-management' ) );
		foreach ( $clients as $client ) {
			printf( '<option value="%1$d" %2$s>%3$s</option>', absint( $client->ID ), selected( $selected, $client->ID, false ), esc_html( $client->post_title ) );
		}
		echo '</select>';
	}

	/**
	 * Render the Tasks list Project filter dropdown.
	 */
	private static function render_project_filter_dropdown() {
		$projects = get_posts(
			array(
				'post_type'              => 'ndizi_project',
				'posts_per_page'         => -1,
				'orderby'                => 'title',
				'order'                  => 'ASC',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);
		if ( empty( $projects ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-table filter, not a form submission.
		$selected = isset( $_GET['ndizi_filter_project'] ) ? absint( $_GET['ndizi_filter_project'] ) : 0;

		echo '<select name="ndizi_filter_project">';
		printf( '<option value="0">%s</option>', esc_html__( 'All Projects', 'ndizi-project-management' ) );
		foreach ( $projects as $project ) {
			printf( '<option value="%1$d" %2$s>%3$s</option>', absint( $project->ID ), selected( $selected, $project->ID, false ), esc_html( $project->post_title ) );
		}
		echo '</select>';
	}

	/**
	 * Add a "Copy Portal Link" row action to the Clients list table.
	 */
	public static function add_client_copy_link_action( $actions, $post ) {
		if ( 'ndizi_client' !== $post->post_type ) {
			return $actions;
		}

		if ( ! Ndizi_Project_Management::is_module_active( 'portal' ) || ! class_exists( 'Ndizi_Portal' ) ) {
			return $actions;
		}

		$link = Ndizi_Portal::get_client_portal_link( $post->ID );
		if ( ! $link ) {
			return $actions;
		}

		$actions['ndizi_copy_link'] = sprintf(
			'<a href="#" class="ndizi-copy-portal-link" data-url="%s">%s</a>',
			esc_url( $link ),
			esc_html__( 'Copy Portal Link', 'ndizi-project-management' )
		);

		return $actions;
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

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-table filter, not a form submission.
			$project_filter = isset( $_GET['ndizi_filter_project'] ) ? absint( $_GET['ndizi_filter_project'] ) : 0;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-table filter, not a form submission.
			$client_filter = isset( $_GET['ndizi_filter_client'] ) ? absint( $_GET['ndizi_filter_client'] ) : 0;

			if ( $project_filter || $client_filter ) {
				$meta_query = $query->get( 'meta_query' );
				if ( ! is_array( $meta_query ) ) {
					$meta_query = array();
				}
				if ( $project_filter ) {
					$meta_query[] = array(
						'key'   => '_ndizi_project_id',
						'value' => $project_filter,
					);
				}
				if ( $client_filter ) {
					$project_ids  = self::get_client_project_ids( $client_filter );
					$meta_query[] = array(
						'key'     => '_ndizi_project_id',
						'value'   => ! empty( $project_ids ) ? $project_ids : array( 0 ),
						'compare' => 'IN',
					);
				}
				$query->set( 'meta_query', $meta_query );
			}
		}

		if ( 'ndizi_project' === $query->get( 'post_type' ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-table filter, not a form submission.
			$client_filter = isset( $_GET['ndizi_filter_client'] ) ? absint( $_GET['ndizi_filter_client'] ) : 0;
			if ( $client_filter ) {
				$meta_query   = $query->get( 'meta_query' );
				$meta_query   = is_array( $meta_query ) ? $meta_query : array();
				$meta_query[] = array(
					'key'   => '_ndizi_client_id',
					'value' => $client_filter,
				);
				$query->set( 'meta_query', $meta_query );
			}
		}

		if ( 'ndizi_invoice' === $query->get( 'post_type' ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-table filter, not a form submission.
			$client_filter = isset( $_GET['ndizi_filter_client'] ) ? absint( $_GET['ndizi_filter_client'] ) : 0;
			if ( $client_filter ) {
				$invoice_ids = self::get_client_invoice_ids( $client_filter );
				$query->set( 'post__in', ! empty( $invoice_ids ) ? $invoice_ids : array( 0 ) );
			}

			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-table filter, not a form submission.
			$status_filter = isset( $_GET['ndizi_filter_invoice_status'] ) ? sanitize_key( wp_unslash( $_GET['ndizi_filter_invoice_status'] ) ) : '';
			if ( 'outstanding' === $status_filter ) {
				$meta_query   = $query->get( 'meta_query' );
				$meta_query   = is_array( $meta_query ) ? $meta_query : array();
				$meta_query[] = array(
					'key'     => '_ndizi_invoice_status',
					'value'   => array( 'sent', 'partial' ),
					'compare' => 'IN',
				);
				$query->set( 'meta_query', $meta_query );
			}
		}

		if ( 'ndizi_client' === $query->get( 'post_type' ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list-table filter, not a form submission.
			$status_filter = isset( $_GET['ndizi_filter_status'] ) ? sanitize_key( wp_unslash( $_GET['ndizi_filter_status'] ) ) : 'active';
			if ( 'all' !== $status_filter ) {
				$meta_query = $query->get( 'meta_query' );
				if ( ! is_array( $meta_query ) ) {
					$meta_query = array();
				}
				if ( 'active' === $status_filter ) {
					$meta_query[] = array(
						'relation' => 'OR',
						array(
							'key'     => '_ndizi_client_status',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => '_ndizi_client_status',
							'value'   => 'archived',
							'compare' => '!=',
						),
					);
				} else {
					$meta_query[] = array(
						'key'   => '_ndizi_client_status',
						'value' => $status_filter,
					);
				}
				$query->set( 'meta_query', $meta_query );
			}
		}
	}

	/**
	 * Add custom columns to Clients list
	 */
	public static function add_client_columns( $columns ) {
		$new_columns    = array();
		$invoicing_live = Ndizi_Project_Management::is_module_active( 'invoicing' );
		foreach ( $columns as $key => $title ) {
			if ( 'date' === $key ) {
				if ( $invoicing_live ) {
					$new_columns['invoices_count'] = __( 'Invoices', 'ndizi-project-management' );
					$new_columns['outstanding']    = __( 'Outstanding', 'ndizi-project-management' );
				}
				$new_columns['unbilled_time']  = __( 'Unbilled Time', 'ndizi-project-management' );
				$new_columns['projects_count'] = __( 'Projects', 'ndizi-project-management' );
				$new_columns['client_status']  = __( 'Status', 'ndizi-project-management' );
				$new_columns['client_key']     = __( 'Portal Key', 'ndizi-project-management' );
				$new_columns['client_website'] = __( 'Website', 'ndizi-project-management' );
				$new_columns['client_address'] = __( 'Address', 'ndizi-project-management' );
				if ( $invoicing_live ) {
					$new_columns['last_invoice_date'] = __( 'Last Invoice', 'ndizi-project-management' );
				}
				$new_columns['last_activity'] = __( 'Last Activity', 'ndizi-project-management' );
			}
			$new_columns[ $key ] = $title;
		}
		return $new_columns;
	}

	/**
	 * Render custom column contents for Clients list
	 */
	public static function render_client_columns( $column, $post_id ) {
		if ( 'invoices_count' === $column ) {
			$invoice_ids = self::get_client_invoice_ids( $post_id );
			$count       = count( $invoice_ids );
			if ( $count ) {
				printf(
					'<a href="%s">%d</a>',
					esc_url(
						add_query_arg(
							array(
								'post_type'           => 'ndizi_invoice',
								'ndizi_filter_client' => $post_id,
							),
							admin_url( 'edit.php' )
						)
					),
					absint( $count )
				);
			} else {
				echo '0';
			}
		} elseif ( 'outstanding' === $column ) {
			$invoice_ids = self::get_client_invoice_ids( $post_id );
			$outstanding = 0.0;
			$currency    = '';
			foreach ( $invoice_ids as $invoice_id ) {
				$status = get_post_meta( $invoice_id, '_ndizi_invoice_status', true );
				if ( ! in_array( $status, array( 'sent', 'partial' ), true ) ) {
					continue;
				}
				$outstanding += Ndizi_Invoicing::get_invoice_balance( $invoice_id );
				if ( ! $currency ) {
					$currency = get_post_meta( $invoice_id, '_ndizi_invoice_currency', true );
				}
			}
			if ( $outstanding > 0 ) {
				$currency = $currency ? strtoupper( $currency ) : strtoupper( get_option( 'ndizi_default_currency', 'USD' ) );
				printf(
					'<a href="%s">%s</a>',
					esc_url(
						add_query_arg(
							array(
								'post_type'           => 'ndizi_invoice',
								'ndizi_filter_client' => $post_id,
								'ndizi_filter_invoice_status' => 'outstanding',
							),
							admin_url( 'edit.php' )
						)
					),
					esc_html( $currency . ' ' . number_format( $outstanding, 2 ) )
				);
			} else {
				echo '-';
			}
		} elseif ( 'unbilled_time' === $column ) {
			$totals = Ndizi_DB::get_time_totals(
				array(
					'client_id'  => $post_id,
					'invoice_id' => 0,
					'groupby'    => 'client_id',
				)
			);
			$hours  = ! empty( $totals ) ? round( (float) $totals[0]->billable_duration / HOUR_IN_SECONDS, 2 ) : 0;
			printf(
				'<a href="%s">%sh</a>',
				esc_url(
					add_query_arg(
						array(
							'page'      => 'ndizi-time-entries',
							'client_id' => $post_id,
							'invoiced'  => 'no',
						),
						admin_url( 'admin.php' )
					)
				),
				esc_html( $hours )
			);
		} elseif ( 'projects_count' === $column ) {
			$project_ids = self::get_client_project_ids( $post_id );
			$count       = count( $project_ids );
			if ( $count ) {
				printf(
					'<a href="%s">%d</a>',
					esc_url(
						add_query_arg(
							array(
								'post_type'           => 'ndizi_project',
								'ndizi_filter_client' => $post_id,
							),
							admin_url( 'edit.php' )
						)
					),
					absint( $count )
				);
			} else {
				echo '0';
			}
		} elseif ( 'last_invoice_date' === $column ) {
			$invoice_ids = self::get_client_invoice_ids( $post_id );
			$latest      = '';
			foreach ( $invoice_ids as $invoice_id ) {
				$date = get_post_meta( $invoice_id, '_ndizi_invoice_date', true );
				if ( $date && ( ! $latest || strtotime( $date ) > strtotime( $latest ) ) ) {
					$latest = $date;
				}
			}
			echo $latest ? esc_html( $latest ) : '-';
		} elseif ( 'last_activity' === $column ) {
			global $wpdb;
			$table_name = Ndizi_DB::get_table_name();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table name comes from Ndizi_DB, not user input; client id is prepared.
			$last = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(start_time) FROM {$table_name} WHERE client_id = %d", $post_id ) );
			echo $last ? esc_html( $last ) : '-';
		} elseif ( 'client_status' === $column ) {
			$status       = get_post_meta( $post_id, '_ndizi_client_status', true );
			$status_label = ( 'archived' === $status ) ? __( 'Archived', 'ndizi-project-management' ) : __( 'Active', 'ndizi-project-management' );
			$status_class = ( 'archived' === $status ) ? 'ndizi-badge-archived' : 'ndizi-badge-active';
			echo '<span class="ndizi-badge ' . esc_attr( $status_class ) . '">' . esc_html( $status_label ) . '</span>';
		} elseif ( 'client_key' === $column ) {
			$key = get_post_meta( $post_id, '_ndizi_client_auth_key', true );
			echo '<code>' . esc_html( $key ? $key : '-' ) . '</code>';
		} elseif ( 'client_website' === $column ) {
			$website = get_post_meta( $post_id, '_ndizi_client_website', true );
			if ( $website ) {
				echo '<a href="' . esc_url( $website ) . '" target="_blank" rel="noopener">' . esc_html( $website ) . '</a>';
			} else {
				echo '-';
			}
		} elseif ( 'client_address' === $column ) {
			$address = get_post_meta( $post_id, '_ndizi_client_address', true );
			echo $address ? esc_html( $address ) : '-';
		}
	}

	/**
	 * Add custom columns to Projects list
	 */
	public static function add_project_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			if ( 'date' === $key ) {
				$new_columns['project_client']      = __( 'Client', 'ndizi-project-management' );
				$new_columns['project_status']      = __( 'Status', 'ndizi-project-management' );
				$new_columns['project_time']        = __( 'Time Tracked', 'ndizi-project-management' );
				$new_columns['project_budget']      = __( 'Budget', 'ndizi-project-management' );
				$new_columns['project_start_date']  = __( 'Start Date', 'ndizi-project-management' );
				$new_columns['project_end_date']    = __( 'End Date', 'ndizi-project-management' );
				$new_columns['project_hourly_rate'] = __( 'Hourly Rate', 'ndizi-project-management' );
				$new_columns['open_tasks']          = __( 'Open Tasks', 'ndizi-project-management' );
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
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from $wpdb->prefix; the IN() list is built from per-id %d placeholders and prepared against $project_ids.
					$results = $wpdb->get_results( $wpdb->prepare( "SELECT project_id, SUM(duration) as total_duration FROM $table_name WHERE project_id IN ($ids_placeholders) GROUP BY project_id", $project_ids ) );
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
		} elseif ( 'project_start_date' === $column ) {
			$start = get_post_meta( $post_id, '_ndizi_project_start_date', true );
			echo $start ? esc_html( $start ) : '-';
		} elseif ( 'project_end_date' === $column ) {
			$end = get_post_meta( $post_id, '_ndizi_project_end_date', true );
			echo $end ? esc_html( $end ) : '-';
		} elseif ( 'project_hourly_rate' === $column ) {
			$rate = get_post_meta( $post_id, '_ndizi_project_hourly_rate', true );
			echo $rate ? '$' . esc_html( number_format( $rate, 2 ) ) : '-';
		} elseif ( 'open_tasks' === $column ) {
			$open_task_ids = get_posts(
				array(
					'post_type'              => 'ndizi_task',
					'posts_per_page'         => -1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'meta_query'             => array(
						array(
							'key'   => '_ndizi_project_id',
							'value' => $post_id,
						),
						array(
							'key'     => '_ndizi_task_status',
							'value'   => array( 'completed', 'cancelled' ),
							'compare' => 'NOT IN',
						),
					),
				)
			);
			$count         = count( $open_task_ids );
			if ( $count ) {
				printf(
					'<a href="%s">%d</a>',
					esc_url(
						add_query_arg(
							array(
								'post_type'            => 'ndizi_task',
								'ndizi_filter_project' => $post_id,
							),
							admin_url( 'edit.php' )
						)
					),
					absint( $count )
				);
			} else {
				echo '0';
			}
		}
	}

	/**
	 * Add custom columns to Tasks list
	 */
	public static function add_task_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			if ( 'date' === $key ) {
				$new_columns['task_project']     = __( 'Project', 'ndizi-project-management' );
				$new_columns['task_assignee']    = __( 'Assignee', 'ndizi-project-management' );
				$new_columns['task_status']      = __( 'Status', 'ndizi-project-management' );
				$new_columns['task_priority']    = __( 'Priority', 'ndizi-project-management' );
				$new_columns['task_due_date']    = __( 'Due Date', 'ndizi-project-management' );
				$new_columns['task_hourly_rate'] = __( 'Hourly Rate', 'ndizi-project-management' );
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
		} elseif ( 'task_hourly_rate' === $column ) {
			$rate = get_post_meta( $post_id, '_ndizi_task_hourly_rate', true );
			echo $rate ? '$' . esc_html( number_format( $rate, 2 ) ) : '-';
		}
	}

	/**
	 * Add custom columns to Invoices list
	 */
	public static function add_invoice_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			if ( 'date' === $key ) {
				$new_columns['invoice_num']     = __( 'Invoice #', 'ndizi-project-management' );
				$new_columns['invoice_client']  = __( 'Client', 'ndizi-project-management' );
				$new_columns['invoice_project'] = __( 'Project', 'ndizi-project-management' );
				$new_columns['invoice_status']  = __( 'Status', 'ndizi-project-management' );
				$new_columns['invoice_amount']  = __( 'Amount', 'ndizi-project-management' );
				$new_columns['invoice_due']     = __( 'Due Date', 'ndizi-project-management' );
				$new_columns['invoice_date']    = __( 'Invoice Date', 'ndizi-project-management' );
				$new_columns['days_overdue']    = __( 'Days Overdue', 'ndizi-project-management' );
			}
			$new_columns[ $key ] = $title;
		}
		return $new_columns;
	}

	/**
	 * Render custom column contents for Invoices list
	 */
	public static function render_invoice_columns( $column, $post_id ) {
		if ( 'invoice_num' === $column ) {
			$num = get_post_meta( $post_id, '_ndizi_invoice_number', true );
			echo $num ? esc_html( $num ) : '-';
		} elseif ( 'invoice_client' === $column ) {
			$client_id = get_post_meta( $post_id, '_ndizi_client_id', true );
			if ( ! $client_id ) {
				$project_id = get_post_meta( $post_id, '_ndizi_project_id', true );
				$client_id  = $project_id ? get_post_meta( $project_id, '_ndizi_client_id', true ) : 0;
			}
			if ( $client_id ) {
				echo '<a href="' . esc_url( get_edit_post_link( $client_id ) ) . '">' . esc_html( get_the_title( $client_id ) ) . '</a>';
			} else {
				echo '-';
			}
		} elseif ( 'invoice_project' === $column ) {
			$project_id = get_post_meta( $post_id, '_ndizi_project_id', true );
			if ( $project_id ) {
				echo '<a href="' . esc_url( get_edit_post_link( $project_id ) ) . '">' . esc_html( get_the_title( $project_id ) ) . '</a>';
			} else {
				echo '-';
			}
		} elseif ( 'invoice_status' === $column ) {
			$status = get_post_meta( $post_id, '_ndizi_invoice_status', true );
			$labels = array(
				'draft'   => __( 'Draft', 'ndizi-project-management' ),
				'sent'    => __( 'Sent', 'ndizi-project-management' ),
				'partial' => __( 'Partially Paid', 'ndizi-project-management' ),
				'paid'    => __( 'Paid', 'ndizi-project-management' ),
				'void'    => __( 'Void', 'ndizi-project-management' ),
			);
			$label  = isset( $labels[ $status ] ) ? $labels[ $status ] : __( 'Draft', 'ndizi-project-management' );
			echo '<span class="ndizi-badge ndizi-invoice-' . esc_attr( $status ) . '">' . esc_html( $label ) . '</span>';
		} elseif ( 'invoice_amount' === $column ) {
			$amount   = get_post_meta( $post_id, '_ndizi_invoice_amount', true );
			$currency = get_post_meta( $post_id, '_ndizi_invoice_currency', true );
			if ( ! $currency ) {
				$currency = get_option( 'ndizi_default_currency', 'USD' );
			}
			$currency = strtoupper( $currency );
			echo $amount ? esc_html( $currency . ' ' . number_format( $amount, 2 ) ) : '-';
			$balance = Ndizi_Invoicing::get_invoice_balance( $post_id );
			if ( $balance > 0 && floatval( $amount ) > 0 && $balance < floatval( $amount ) ) {
				echo '<br><small style="color:#b45309;">' . esc_html(
					sprintf(
					/* translators: %s: formatted outstanding balance */
						__( 'Balance: %s', 'ndizi-project-management' ),
						$currency . ' ' . number_format( $balance, 2 )
					)
				) . '</small>';
			}
		} elseif ( 'invoice_due' === $column ) {
			$due = get_post_meta( $post_id, '_ndizi_invoice_due_date', true );
			echo $due ? esc_html( $due ) : '-';
		} elseif ( 'invoice_date' === $column ) {
			$date = get_post_meta( $post_id, '_ndizi_invoice_date', true );
			echo $date ? esc_html( $date ) : '-';
		} elseif ( 'days_overdue' === $column ) {
			$status = get_post_meta( $post_id, '_ndizi_invoice_status', true );
			$due    = get_post_meta( $post_id, '_ndizi_invoice_due_date', true );
			$days   = ( $due && in_array( $status, array( 'sent', 'partial' ), true ) ) ? (int) floor( ( time() - strtotime( $due ) ) / DAY_IN_SECONDS ) : 0;
			if ( $days > 0 ) {
				echo '<span style="color:#b45309;font-weight:600;">' . esc_html( $days ) . '</span>';
			} else {
				echo '-';
			}
		}
	}

	/**
	 * Add custom columns to Contacts list
	 */
	public static function add_contact_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			if ( 'title' === $key ) {
				$new_columns['contact_avatar'] = __( 'Photo', 'ndizi-project-management' );
			}
			if ( 'date' === $key ) {
				$new_columns['contact_email']   = __( 'Email', 'ndizi-project-management' );
				$new_columns['contact_phone']   = __( 'Phone', 'ndizi-project-management' );
				$new_columns['contact_role']    = __( 'Role', 'ndizi-project-management' );
				$new_columns['contact_clients'] = __( 'Associated Clients', 'ndizi-project-management' );
			}
			$new_columns[ $key ] = $title;
		}
		return $new_columns;
	}

	/**
	 * Render custom column contents for Contacts list
	 */
	public static function render_contact_columns( $column, $post_id ) {
		if ( 'contact_avatar' === $column ) {
			$style = 'width:40px;height:40px;object-fit:cover;border-radius:4px;';
			if ( has_post_thumbnail( $post_id ) ) {
				echo get_the_post_thumbnail( $post_id, 'thumbnail', array( 'style' => $style ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_the_post_thumbnail() escapes its own output.
			} else {
				$email = get_post_meta( $post_id, '_ndizi_contact_email', true );
				echo get_avatar( $email, 40, '', '', array( 'style' => $style ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_avatar() escapes its own output.
			}
		} elseif ( 'contact_email' === $column ) {
			$email = get_post_meta( $post_id, '_ndizi_contact_email', true );
			if ( $email ) {
				echo '<a href="' . esc_url( 'mailto:' . $email ) . '">' . esc_html( $email ) . '</a>';
			} else {
				echo '-';
			}
		} elseif ( 'contact_phone' === $column ) {
			$phone = get_post_meta( $post_id, '_ndizi_contact_phone', true );
			echo $phone ? esc_html( $phone ) : '-';
		} elseif ( 'contact_role' === $column ) {
			$role = get_post_meta( $post_id, '_ndizi_contact_role', true );
			echo $role ? esc_html( $role ) : '-';
		} elseif ( 'contact_clients' === $column ) {
			$clients = get_post_meta( $post_id, '_ndizi_associated_clients', true );
			if ( is_array( $clients ) && ! empty( $clients ) ) {
				$client_links = array();
				foreach ( $clients as $client_id ) {
					$client_links[] = sprintf(
						'<a href="%1$s">%2$s</a>',
						esc_url( get_edit_post_link( $client_id ) ),
						esc_html( get_the_title( $client_id ) )
					);
				}
				echo wp_kses_post( implode( ', ', $client_links ) );
			} else {
				echo '-';
			}
		}
	}

	/**
	 * Add custom columns to Time Off Requests list
	 */
	public static function add_time_off_columns( $columns ) {
		$new_columns = array();
		foreach ( $columns as $key => $title ) {
			if ( 'date' === $key ) {
				$new_columns['time_off_client']     = __( 'Client', 'ndizi-project-management' );
				$new_columns['time_off_start_date'] = __( 'Start Date', 'ndizi-project-management' );
				$new_columns['time_off_end_date']   = __( 'End Date', 'ndizi-project-management' );
				$new_columns['time_off_type']       = __( 'Type', 'ndizi-project-management' );
				$new_columns['time_off_status']     = __( 'Status', 'ndizi-project-management' );
			}
			$new_columns[ $key ] = $title;
		}
		return $new_columns;
	}

	/**
	 * Render custom column contents for Time Off Requests list
	 */
	public static function render_time_off_columns( $column, $post_id ) {
		if ( 'time_off_client' === $column ) {
			$client_id = get_post_meta( $post_id, '_ndizi_time_off_client_id', true );
			if ( $client_id ) {
				echo '<a href="' . esc_url( get_edit_post_link( $client_id ) ) . '">' . esc_html( get_the_title( $client_id ) ) . '</a>';
			} else {
				echo '-';
			}
		} elseif ( 'time_off_start_date' === $column ) {
			$start = get_post_meta( $post_id, '_ndizi_time_off_start_date', true );
			echo $start ? esc_html( $start ) : '-';
		} elseif ( 'time_off_end_date' === $column ) {
			$end = get_post_meta( $post_id, '_ndizi_time_off_end_date', true );
			echo $end ? esc_html( $end ) : '-';
		} elseif ( 'time_off_type' === $column ) {
			$type   = get_post_meta( $post_id, '_ndizi_time_off_type', true );
			$labels = array(
				'vacation'   => __( 'Vacation', 'ndizi-project-management' ),
				'sick_leave' => __( 'Sick Leave', 'ndizi-project-management' ),
				'personal'   => __( 'Personal', 'ndizi-project-management' ),
				'other'      => __( 'Other', 'ndizi-project-management' ),
			);
			$label  = isset( $labels[ $type ] ) ? $labels[ $type ] : ucfirst( $type );
			echo esc_html( $label );
		} elseif ( 'time_off_status' === $column ) {
			$status = get_post_meta( $post_id, '_ndizi_time_off_status', true );
			$labels = array(
				'pending'  => __( 'Pending', 'ndizi-project-management' ),
				'approved' => __( 'Approved', 'ndizi-project-management' ),
				'denied'   => __( 'Denied', 'ndizi-project-management' ),
			);
			$label  = isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( $status );

			$badge_class = 'ndizi-badge-pending';
			if ( 'approved' === $status ) {
				$badge_class = 'ndizi-badge-active';
			} elseif ( 'denied' === $status ) {
				$badge_class = 'ndizi-badge-denied';
			}

			echo '<span class="ndizi-badge ' . esc_attr( $badge_class ) . '">' . esc_html( $label ) . '</span>';
		}
	}

	/**
	 * Set default hidden columns for Screen Options
	 */
	public static function set_default_hidden_columns( $hidden, $screen ) {
		if ( empty( $screen->post_type ) ) {
			return $hidden;
		}

		switch ( $screen->post_type ) {
			case 'ndizi_client':
				$hidden[] = 'client_website';
				$hidden[] = 'client_address';
				$hidden[] = 'last_invoice_date';
				$hidden[] = 'last_activity';
				break;
			case 'ndizi_project':
				$hidden[] = 'project_start_date';
				$hidden[] = 'project_end_date';
				$hidden[] = 'project_hourly_rate';
				$hidden[] = 'open_tasks';
				break;
			case 'ndizi_task':
				$hidden[] = 'task_hourly_rate';
				break;
			case 'ndizi_invoice':
				$hidden[] = 'invoice_date';
				$hidden[] = 'days_overdue';
				break;
			case 'ndizi_contact':
				$hidden[] = 'contact_phone';
				break;
		}

		return array_unique( $hidden );
	}
}
