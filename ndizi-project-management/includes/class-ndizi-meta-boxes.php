<?php
/**
 * Meta boxes for Ndizi Project Management CPTs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ndizi_Meta_Boxes {

	/**
	 * Initialize meta box hooks
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta_boxes' ) );
	}

	/**
	 * Add Meta Boxes
	 */
	public static function add_meta_boxes() {
		// Client Meta Box
		add_meta_box( 'ndizi_client_details', __( 'Client Details', 'ndizi-project-management' ), array( __CLASS__, 'render_client_meta_box' ), 'ndizi_client', 'normal', 'high' );

		// Project Meta Box
		add_meta_box( 'ndizi_project_details', __( 'Project Details', 'ndizi-project-management' ), array( __CLASS__, 'render_project_meta_box' ), 'ndizi_project', 'normal', 'high' );
		add_meta_box( 'ndizi_project_time', __( 'Time Log / Tracker', 'ndizi-project-management' ), array( __CLASS__, 'render_project_time_meta_box' ), 'ndizi_project', 'normal', 'default' );

		// Task Meta Box
		add_meta_box( 'ndizi_task_details', __( 'Task Details', 'ndizi-project-management' ), array( __CLASS__, 'render_task_meta_box' ), 'ndizi_task', 'normal', 'high' );

		// Invoice Meta Box
		if ( Ndizi_Project_Management::is_module_active( 'invoicing' ) ) {
			add_meta_box( 'ndizi_invoice_details', __( 'Invoice Details', 'ndizi-project-management' ), array( __CLASS__, 'render_invoice_meta_box' ), 'ndizi_invoice', 'normal', 'high' );
		}

		// Contact Meta Box
		add_meta_box( 'ndizi_contact_details', __( 'Contact Details', 'ndizi-project-management' ), array( __CLASS__, 'render_contact_meta_box' ), 'ndizi_contact', 'normal', 'high' );

		// Time Off Meta Box
		if ( Ndizi_Project_Management::is_module_active( 'time_off' ) ) {
			add_meta_box( 'ndizi_time_off_details', __( 'Time Off Details', 'ndizi-project-management' ), array( __CLASS__, 'render_time_off_meta_box' ), 'ndizi_time_off', 'normal', 'high' );
		}
	}

	/**
	 * Render Client Meta Box
	 */
	public static function render_client_meta_box( $post ) {
		wp_nonce_field( 'ndizi_save_client', 'ndizi_client_nonce' );

		$website = get_post_meta( $post->ID, '_ndizi_client_website', true );
		$address = get_post_meta( $post->ID, '_ndizi_client_address', true );
		$key     = get_post_meta( $post->ID, '_ndizi_client_auth_key', true );
		$status  = get_post_meta( $post->ID, '_ndizi_client_status', true );

		$external_source = get_post_meta( $post->ID, '_ndizi_external_source', true );
		$external_id     = get_post_meta( $post->ID, '_ndizi_external_id', true );

		if ( empty( $key ) ) {
			$key = wp_generate_password( 16, false );
		}
		?>
		<table class="form-table ndizi-meta-table">
			<tr>
				<th><label for="ndizi_client_website"><?php esc_html_e( 'Website URL', 'ndizi-project-management' ); ?></label></th>
				<td><input type="url" name="ndizi_client_website" id="ndizi_client_website" value="<?php echo esc_url( $website ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="ndizi_client_address"><?php esc_html_e( 'Billing Address', 'ndizi-project-management' ); ?></label></th>
				<td><textarea name="ndizi_client_address" id="ndizi_client_address" class="large-text" rows="3"><?php echo esc_textarea( $address ); ?></textarea></td>
			</tr>
			<tr>
				<th><label for="ndizi_client_status"><?php esc_html_e( 'Status', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_client_status" id="ndizi_client_status">
						<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'ndizi-project-management' ); ?></option>
						<option value="archived" <?php selected( $status, 'archived' ); ?>><?php esc_html_e( 'Archived / Inactive', 'ndizi-project-management' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_client_auth_key"><?php esc_html_e( 'Portal Key', 'ndizi-project-management' ); ?></label></th>
				<td>
					<input type="text" name="ndizi_client_auth_key" id="ndizi_client_auth_key" value="<?php echo esc_attr( $key ); ?>" class="regular-text" readonly>
					<button type="button" class="button ndizi-regen-key-btn"><?php esc_html_e( 'Regenerate Key', 'ndizi-project-management' ); ?></button>
					<p class="description"><?php esc_html_e( 'Used for frontend access authentication.', 'ndizi-project-management' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_external_source"><?php esc_html_e( 'External Provenance', 'ndizi-project-management' ); ?></label></th>
				<td>
					<input type="text" name="ndizi_external_source" id="ndizi_external_source" value="<?php echo esc_attr( $external_source ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Source (e.g. freshbooks)', 'ndizi-project-management' ); ?>" style="width: 48%; margin-right: 2%;">
					<input type="text" name="ndizi_external_id" id="ndizi_external_id" value="<?php echo esc_attr( $external_id ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'External ID', 'ndizi-project-management' ); ?>" style="width: 48%;">
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render Project Meta Box
	 */
	public static function render_project_meta_box( $post ) {
		wp_nonce_field( 'ndizi_save_project', 'ndizi_project_nonce' );

		$client_id   = get_post_meta( $post->ID, '_ndizi_client_id', true );
		$start_date  = get_post_meta( $post->ID, '_ndizi_project_start_date', true );
		$end_date    = get_post_meta( $post->ID, '_ndizi_project_end_date', true );
		$budget      = get_post_meta( $post->ID, '_ndizi_project_budget', true );
		$hourly_rate = get_post_meta( $post->ID, '_ndizi_project_hourly_rate', true );
		$status      = get_post_meta( $post->ID, '_ndizi_project_status', true );

		$external_source = get_post_meta( $post->ID, '_ndizi_external_source', true );
		$external_id     = get_post_meta( $post->ID, '_ndizi_external_id', true );

		$clients = get_posts(
			array(
				'post_type'      => 'ndizi_client',
				'posts_per_page' => -1,
			)
		);
		?>
		<table class="form-table ndizi-meta-table">
			<tr>
				<th><label for="ndizi_client_id"><?php esc_html_e( 'Client', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_client_id" id="ndizi_client_id" required>
						<option value=""><?php esc_html_e( '-- Select Client --', 'ndizi-project-management' ); ?></option>
						<?php foreach ( $clients as $client ) : ?>
							<option value="<?php echo esc_attr( $client->ID ); ?>" <?php selected( $client_id, $client->ID ); ?>>
								<?php echo esc_html( $client->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_project_start_date"><?php esc_html_e( 'Start Date', 'ndizi-project-management' ); ?></label></th>
				<td><input type="date" name="ndizi_project_start_date" id="ndizi_project_start_date" value="<?php echo esc_attr( $start_date ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ndizi_project_end_date"><?php esc_html_e( 'End/Target Date', 'ndizi-project-management' ); ?></label></th>
				<td><input type="date" name="ndizi_project_end_date" id="ndizi_project_end_date" value="<?php echo esc_attr( $end_date ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ndizi_project_budget"><?php esc_html_e( 'Budget ($)', 'ndizi-project-management' ); ?></label></th>
				<td><input type="number" step="0.01" name="ndizi_project_budget" id="ndizi_project_budget" value="<?php echo esc_attr( $budget ); ?>" class="small-text"></td>
			</tr>
			<?php if ( Ndizi_Project_Management::is_module_active( 'invoicing' ) ) : ?>
			<tr>
				<th><label for="ndizi_project_hourly_rate"><?php esc_html_e( 'Default Hourly Rate ($/hour)', 'ndizi-project-management' ); ?></label></th>
				<td><input type="number" step="0.01" name="ndizi_project_hourly_rate" id="ndizi_project_hourly_rate" value="<?php echo esc_attr( $hourly_rate ); ?>" class="small-text"></td>
			</tr>
			<?php endif; ?>
			<tr>
				<th><label for="ndizi_project_status"><?php esc_html_e( 'Project Status', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_project_status" id="ndizi_project_status">
						<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Active', 'ndizi-project-management' ); ?></option>
						<option value="archived" <?php selected( $status, 'archived' ); ?>><?php esc_html_e( 'Archived', 'ndizi-project-management' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_external_source"><?php esc_html_e( 'External Provenance', 'ndizi-project-management' ); ?></label></th>
				<td>
					<input type="text" name="ndizi_external_source" id="ndizi_external_source" value="<?php echo esc_attr( $external_source ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Source (e.g. freshbooks)', 'ndizi-project-management' ); ?>" style="width: 48%; margin-right: 2%;">
					<input type="text" name="ndizi_external_id" id="ndizi_external_id" value="<?php echo esc_attr( $external_id ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'External ID', 'ndizi-project-management' ); ?>" style="width: 48%;">
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render Project Time Logs Meta Box
	 */
	public static function render_project_time_meta_box( $post ) {
		$user_id           = get_current_user_id();
		$active            = Ndizi_DB::get_active_timer( $user_id );
		$is_active_on_this = $active && ( intval( $active->project_id ) === $post->ID );

		// Load tasks for this project to log against
		$tasks = get_posts(
			array(
				'post_type'      => 'ndizi_task',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => '_ndizi_project_id',
						'value' => $post->ID,
					),
				),
			)
		);

		// Load historical logs for this project
		$logs = Ndizi_DB::get_time_entries(
			array(
				'project_id' => $post->ID,
				'number'     => 15,
			)
		);
		?>
		<div class="ndizi-tracker-wrapper">
			<!-- Timer controls -->
			<div class="ndizi-tracker-controls">
				<h4><?php esc_html_e( 'Live Time Tracker', 'ndizi-project-management' ); ?></h4>
				<div class="ndizi-timer-bar <?php echo $is_active_on_this ? 'ndizi-timer-running' : ''; ?>">
					<div class="ndizi-timer-fields">
						<select id="ndizi_tracker_task_id">
							<option value="0"><?php esc_html_e( '-- General --', 'ndizi-project-management' ); ?></option>
							<?php foreach ( $tasks as $task ) : ?>
								<option value="<?php echo esc_attr( $task->ID ); ?>">
									<?php echo esc_html( $task->post_title ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<input type="text" id="ndizi_tracker_desc" placeholder="<?php esc_attr_e( 'What are you working on?', 'ndizi-project-management' ); ?>" class="regular-text">
						<label class="ndizi-checkbox-label">
							<input type="checkbox" id="ndizi_tracker_billable" value="1" checked> <?php esc_html_e( 'Billable', 'ndizi-project-management' ); ?>
						</label>
					</div>

					<div class="ndizi-timer-action">
						<span class="ndizi-live-clock">00:00:00</span>
						<?php if ( $is_active_on_this ) : ?>
							<button type="button" class="button button-primary ndizi-btn-stop" data-project-id="<?php echo esc_attr( $post->ID ); ?>">
								<?php esc_html_e( 'Stop', 'ndizi-project-management' ); ?>
							</button>
						<?php else : ?>
							<button type="button" class="button button-primary ndizi-btn-start" data-project-id="<?php echo esc_attr( $post->ID ); ?>" <?php disabled( $active !== false ); ?>>
								<?php esc_html_e( 'Start Timer', 'ndizi-project-management' ); ?>
							</button>
						<?php endif; ?>
					</div>
				</div>
				<?php if ( $active && ! $is_active_on_this ) : ?>
					<p class="description error-message">
						<?php esc_html_e( 'You already have an active timer running on another project. Stop it first to track here.', 'ndizi-project-management' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<hr>

			<!-- Log List -->
			<div class="ndizi-tracker-logs">
				<h4><?php esc_html_e( 'Recent Time Logs', 'ndizi-project-management' ); ?></h4>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th class="column-date"><?php esc_html_e( 'Date', 'ndizi-project-management' ); ?></th>
							<th class="column-user"><?php esc_html_e( 'User', 'ndizi-project-management' ); ?></th>
							<th class="column-task"><?php esc_html_e( 'Task', 'ndizi-project-management' ); ?></th>
							<th class="column-desc"><?php esc_html_e( 'Description', 'ndizi-project-management' ); ?></th>
							<th class="column-duration"><?php esc_html_e( 'Duration', 'ndizi-project-management' ); ?></th>
							<th class="column-billable"><?php esc_html_e( 'Billable', 'ndizi-project-management' ); ?></th>
							<th class="column-actions"><?php esc_html_e( 'Action', 'ndizi-project-management' ); ?></th>
						</tr>
					</thead>
					<tbody id="ndizi_logs_table_body">
						<?php if ( empty( $logs ) ) : ?>
							<tr class="no-items"><td colspan="7"><?php esc_html_e( 'No time logged yet on this project.', 'ndizi-project-management' ); ?></td></tr>
						<?php else : ?>
							<?php
							foreach ( $logs as $log ) :
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
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Task Meta Box
	 */
	public static function render_task_meta_box( $post ) {
		wp_nonce_field( 'ndizi_save_task', 'ndizi_task_nonce' );

		$project_id       = get_post_meta( $post->ID, '_ndizi_project_id', true );
		$assignee_id      = get_post_meta( $post->ID, '_ndizi_assigned_user_id', true );
		$status           = get_post_meta( $post->ID, '_ndizi_task_status', true );
		$priority         = get_post_meta( $post->ID, '_ndizi_task_priority', true );
		$due_date         = get_post_meta( $post->ID, '_ndizi_task_due_date', true );
		$task_hourly_rate = get_post_meta( $post->ID, '_ndizi_task_hourly_rate', true );

		$projects = get_posts(
			array(
				'post_type'      => 'ndizi_project',
				'posts_per_page' => -1,
			)
		);

		$users = get_users(
			array(
				'capability' => 'ndizi_log_time',
			)
		);
		?>
		<table class="form-table ndizi-meta-table">
			<tr>
				<th><label for="ndizi_project_id"><?php esc_html_e( 'Project', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_project_id" id="ndizi_project_id" required>
						<option value=""><?php esc_html_e( '-- Select Project --', 'ndizi-project-management' ); ?></option>
						<?php foreach ( $projects as $project ) : ?>
							<option value="<?php echo esc_attr( $project->ID ); ?>" <?php selected( $project_id, $project->ID ); ?>>
								<?php echo esc_html( $project->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_assigned_user_id"><?php esc_html_e( 'Assigned To', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_assigned_user_id" id="ndizi_assigned_user_id">
						<option value="0"><?php esc_html_e( 'Unassigned', 'ndizi-project-management' ); ?></option>
						<?php foreach ( $users as $u ) : ?>
							<option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( $assignee_id, $u->ID ); ?>>
								<?php echo esc_html( $u->display_name ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_task_status"><?php esc_html_e( 'Task Status', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_task_status" id="ndizi_task_status">
						<option value="open" <?php selected( $status, 'open' ); ?>><?php esc_html_e( 'Open', 'ndizi-project-management' ); ?></option>
						<option value="in_progress" <?php selected( $status, 'in_progress' ); ?>><?php esc_html_e( 'In Progress', 'ndizi-project-management' ); ?></option>
						<option value="completed" <?php selected( $status, 'completed' ); ?>><?php esc_html_e( 'Completed', 'ndizi-project-management' ); ?></option>
						<option value="cancelled" <?php selected( $status, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'ndizi-project-management' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_task_priority"><?php esc_html_e( 'Priority', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_task_priority" id="ndizi_task_priority">
						<option value="low" <?php selected( $priority, 'low' ); ?>><?php esc_html_e( 'Low', 'ndizi-project-management' ); ?></option>
						<option value="medium" <?php selected( $priority, 'medium' ); ?>><?php esc_html_e( 'Medium', 'ndizi-project-management' ); ?></option>
						<option value="high" <?php selected( $priority, 'high' ); ?>><?php esc_html_e( 'High', 'ndizi-project-management' ); ?></option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_task_due_date"><?php esc_html_e( 'Due Date', 'ndizi-project-management' ); ?></label></th>
				<td><input type="date" name="ndizi_task_due_date" id="ndizi_task_due_date" value="<?php echo esc_attr( $due_date ); ?>"></td>
			</tr>
			<?php if ( Ndizi_Project_Management::is_module_active( 'invoicing' ) ) : ?>
			<tr>
				<th><label for="ndizi_task_hourly_rate"><?php esc_html_e( 'Hourly Rate Override ($/hour)', 'ndizi-project-management' ); ?></label></th>
				<td><input type="number" step="0.01" name="ndizi_task_hourly_rate" id="ndizi_task_hourly_rate" value="<?php echo esc_attr( $task_hourly_rate ); ?>" class="small-text"></td>
			</tr>
			<?php endif; ?>
		</table>
		<?php
	}

	/**
	 * Render Invoice Meta Box
	 */
	public static function render_invoice_meta_box( $post ) {
		wp_nonce_field( 'ndizi_save_invoice', 'ndizi_invoice_nonce' );

		$client_id       = get_post_meta( $post->ID, '_ndizi_client_id', true );
		$project_id      = get_post_meta( $post->ID, '_ndizi_project_id', true );
		$invoice_number  = get_post_meta( $post->ID, '_ndizi_invoice_number', true );
		$currency        = get_post_meta( $post->ID, '_ndizi_invoice_currency', true );
		$invoice_date    = get_post_meta( $post->ID, '_ndizi_invoice_date', true );
		$due_date        = get_post_meta( $post->ID, '_ndizi_invoice_due_date', true );
		$amount          = get_post_meta( $post->ID, '_ndizi_invoice_amount', true );
		$status          = get_post_meta( $post->ID, '_ndizi_invoice_status', true );
		$line_items      = get_post_meta( $post->ID, '_ndizi_invoice_line_items', true );
		$payments        = get_post_meta( $post->ID, '_ndizi_invoice_payments', true );
		$external_source = get_post_meta( $post->ID, '_ndizi_external_source', true );
		$external_id     = get_post_meta( $post->ID, '_ndizi_external_id', true );

		if ( ! $currency ) {
			$currency = get_option( 'ndizi_default_currency', 'USD' );
		}
		if ( ! is_array( $line_items ) ) {
			$line_items = array();
		}
		if ( ! is_array( $payments ) ) {
			$payments = array();
		}
		$total_paid = 0.0;
		foreach ( $payments as $payment ) {
			$total_paid += isset( $payment['amount'] ) ? floatval( $payment['amount'] ) : 0.0;
		}

		$clients = get_posts(
			array(
				'post_type'      => 'ndizi_client',
				'posts_per_page' => -1,
			)
		);

		$projects = get_posts(
			array(
				'post_type'      => 'ndizi_project',
				'posts_per_page' => -1,
			)
		);

		// Load billable time entries that belong to this project and either belong to THIS invoice OR have invoice_id = 0
		$time_entries = array();
		if ( $project_id ) {
			global $wpdb;
			$table_name = Ndizi_DB::get_table_name();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from $wpdb->prefix; ids are prepared; unbilled entries read from the custom table for the meta box.
			$time_entries = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE project_id = %d AND billable = 1 AND (invoice_id = 0 OR invoice_id = %d) ORDER BY start_time DESC", $project_id, $post->ID ) );
		}
		?>
		<table class="form-table ndizi-meta-table">
			<tr>
				<th><label for="ndizi_invoice_client_id"><?php esc_html_e( 'Client', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_client_id" id="ndizi_invoice_client_id">
						<option value=""><?php esc_html_e( '-- Select Client (Or infer from Project) --', 'ndizi-project-management' ); ?></option>
						<?php foreach ( $clients as $c ) : ?>
							<option value="<?php echo esc_attr( $c->ID ); ?>" <?php selected( $client_id, $c->ID ); ?>>
								<?php echo esc_html( $c->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Direct client association. Required if no project is selected.', 'ndizi-project-management' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_invoice_project_id"><?php esc_html_e( 'Project', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_project_id" id="ndizi_invoice_project_id">
						<option value=""><?php esc_html_e( '-- None / Standalone Client Invoice --', 'ndizi-project-management' ); ?></option>
						<?php foreach ( $projects as $project ) : ?>
							<?php $proj_rate = get_post_meta( $project->ID, '_ndizi_project_hourly_rate', true ); ?>
							<option value="<?php echo esc_attr( $project->ID ); ?>" <?php selected( $project_id, $project->ID ); ?> data-rate="<?php echo esc_attr( $proj_rate ); ?>">
								<?php echo esc_html( $project->post_title ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_invoice_number"><?php esc_html_e( 'Invoice Number', 'ndizi-project-management' ); ?></label></th>
				<td>
					<input type="text" name="ndizi_invoice_number" id="ndizi_invoice_number" value="<?php echo esc_attr( $invoice_number ); ?>" class="regular-text" placeholder="e.g. INV-1001">
					<p class="description"><?php esc_html_e( 'Unique identifier or external invoice number.', 'ndizi-project-management' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_invoice_currency"><?php esc_html_e( 'Currency', 'ndizi-project-management' ); ?></label></th>
				<td>
					<input type="text" name="ndizi_invoice_currency" id="ndizi_invoice_currency" value="<?php echo esc_attr( $currency ); ?>" class="small-text" maxlength="3" style="text-transform: uppercase;">
					<p class="description"><?php esc_html_e( '3-letter currency code (e.g. USD, EUR, GBP, CAD).', 'ndizi-project-management' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_invoice_date"><?php esc_html_e( 'Invoice Date', 'ndizi-project-management' ); ?></label></th>
				<td><input type="date" name="ndizi_invoice_date" id="ndizi_invoice_date" value="<?php echo esc_attr( $invoice_date ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ndizi_invoice_due_date"><?php esc_html_e( 'Due Date', 'ndizi-project-management' ); ?></label></th>
				<td><input type="date" name="ndizi_invoice_due_date" id="ndizi_invoice_due_date" value="<?php echo esc_attr( $due_date ); ?>"></td>
			</tr>
			<tr>
				<th><label for="ndizi_invoice_amount"><?php esc_html_e( 'Total Amount', 'ndizi-project-management' ); ?></label></th>
				<td>
					<input type="number" step="0.01" name="ndizi_invoice_amount" id="ndizi_invoice_amount" value="<?php echo esc_attr( $amount ); ?>" class="small-text">
					<p class="description"><?php esc_html_e( 'Total amount for this invoice. Can be calculated from structured line items or time entries.', 'ndizi-project-management' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_invoice_status"><?php esc_html_e( 'Status', 'ndizi-project-management' ); ?></label></th>
				<td>
					<select name="ndizi_invoice_status" id="ndizi_invoice_status">
						<option value="draft" <?php selected( $status, 'draft' ); ?>><?php esc_html_e( 'Draft', 'ndizi-project-management' ); ?></option>
						<option value="sent" <?php selected( $status, 'sent' ); ?>><?php esc_html_e( 'Sent', 'ndizi-project-management' ); ?></option>
						<option value="partial" <?php selected( $status, 'partial' ); ?>><?php esc_html_e( 'Partially Paid', 'ndizi-project-management' ); ?></option>
						<option value="paid" <?php selected( $status, 'paid' ); ?>><?php esc_html_e( 'Paid', 'ndizi-project-management' ); ?></option>
						<option value="void" <?php selected( $status, 'void' ); ?>><?php esc_html_e( 'Void', 'ndizi-project-management' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Status is updated automatically from recorded payments when they are added (unless Draft or Void).', 'ndizi-project-management' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Structured Line Items', 'ndizi-project-management' ); ?></label></th>
				<td>
					<table class="widefat striped" id="ndizi_line_items_table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Description', 'ndizi-project-management' ); ?></th>
								<th style="width: 80px;"><?php esc_html_e( 'Qty', 'ndizi-project-management' ); ?></th>
								<th style="width: 110px;"><?php esc_html_e( 'Unit Price', 'ndizi-project-management' ); ?></th>
								<th style="width: 110px;"><?php esc_html_e( 'Amount', 'ndizi-project-management' ); ?></th>
								<th style="width: 50px;"></th>
							</tr>
						</thead>
						<tbody id="ndizi_line_items_body">
							<?php if ( empty( $line_items ) ) : ?>
								<tr class="ndizi-line-item-row">
									<td><input type="text" name="ndizi_line_items_desc[]" value="" class="large-text"></td>
									<td><input type="number" step="0.01" name="ndizi_line_items_qty[]" value="1" class="small-text ndizi-li-qty"></td>
									<td><input type="number" step="0.01" name="ndizi_line_items_price[]" value="0.00" class="small-text ndizi-li-price"></td>
									<td><input type="number" step="0.01" name="ndizi_line_items_amount[]" value="0.00" class="small-text ndizi-li-amount" readonly></td>
									<td><button type="button" class="button button-secondary ndizi-remove-li-row">&times;</button></td>
								</tr>
							<?php else : ?>
								<?php foreach ( $line_items as $item ) : ?>
									<tr class="ndizi-line-item-row">
										<td><input type="text" name="ndizi_line_items_desc[]" value="<?php echo esc_attr( isset( $item['description'] ) ? $item['description'] : '' ); ?>" class="large-text"></td>
										<td><input type="number" step="0.01" name="ndizi_line_items_qty[]" value="<?php echo esc_attr( isset( $item['quantity'] ) ? $item['quantity'] : 1 ); ?>" class="small-text ndizi-li-qty"></td>
										<td><input type="number" step="0.01" name="ndizi_line_items_price[]" value="<?php echo esc_attr( isset( $item['unit_price'] ) ? $item['unit_price'] : 0.00 ); ?>" class="small-text ndizi-li-price"></td>
										<td><input type="number" step="0.01" name="ndizi_line_items_amount[]" value="<?php echo esc_attr( isset( $item['amount'] ) ? $item['amount'] : 0.00 ); ?>" class="small-text ndizi-li-amount" readonly></td>
										<td><button type="button" class="button button-secondary ndizi-remove-li-row">&times;</button></td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
					<div style="margin-top: 10px;">
						<button type="button" class="button" id="ndizi_add_line_item_btn"><?php esc_html_e( '+ Add Line Item', 'ndizi-project-management' ); ?></button>
						<button type="button" class="button" id="ndizi_calc_line_items_btn"><?php esc_html_e( 'Calculate & Apply Total', 'ndizi-project-management' ); ?></button>
					</div>
				</td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Payments', 'ndizi-project-management' ); ?></label></th>
				<td>
					<table class="widefat striped" id="ndizi_payments_table">
						<thead>
							<tr>
								<th style="width: 150px;"><?php esc_html_e( 'Date', 'ndizi-project-management' ); ?></th>
								<th style="width: 120px;"><?php esc_html_e( 'Amount', 'ndizi-project-management' ); ?></th>
								<th style="width: 140px;"><?php esc_html_e( 'Method', 'ndizi-project-management' ); ?></th>
								<th><?php esc_html_e( 'Note', 'ndizi-project-management' ); ?></th>
								<th style="width: 50px;"></th>
							</tr>
						</thead>
						<tbody id="ndizi_payments_body">
							<?php foreach ( $payments as $payment ) : ?>
								<tr class="ndizi-payment-row">
									<td><input type="date" name="ndizi_payments_date[]" value="<?php echo esc_attr( isset( $payment['date'] ) ? $payment['date'] : '' ); ?>"></td>
									<td><input type="number" step="0.01" name="ndizi_payments_amount[]" value="<?php echo esc_attr( isset( $payment['amount'] ) ? $payment['amount'] : '0.00' ); ?>" class="small-text ndizi-pay-amount"></td>
									<td><input type="text" name="ndizi_payments_method[]" value="<?php echo esc_attr( isset( $payment['method'] ) ? $payment['method'] : '' ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Bank transfer', 'ndizi-project-management' ); ?>"></td>
									<td><input type="text" name="ndizi_payments_note[]" value="<?php echo esc_attr( isset( $payment['note'] ) ? $payment['note'] : '' ); ?>" class="large-text"></td>
									<td><button type="button" class="button button-secondary ndizi-remove-pay-row">&times;</button></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
						<tfoot>
							<tr>
								<th style="text-align: right;"><?php esc_html_e( 'Total Paid', 'ndizi-project-management' ); ?></th>
								<th><span id="ndizi_payments_total"><?php echo esc_html( number_format( $total_paid, 2 ) ); ?></span></th>
								<th style="text-align: right;"><?php esc_html_e( 'Balance Due', 'ndizi-project-management' ); ?></th>
								<th colspan="2"><span id="ndizi_payments_balance"><?php echo esc_html( number_format( floatval( $amount ) - $total_paid, 2 ) ); ?></span></th>
							</tr>
						</tfoot>
					</table>
					<div style="margin-top: 10px;">
						<button type="button" class="button" id="ndizi_add_payment_btn"><?php esc_html_e( '+ Add Payment', 'ndizi-project-management' ); ?></button>
					</div>
				</td>
			</tr>
			<tr>
				<th><label for="ndizi_external_source"><?php esc_html_e( 'External Provenance', 'ndizi-project-management' ); ?></label></th>
				<td>
					<input type="text" name="ndizi_external_source" id="ndizi_external_source" value="<?php echo esc_attr( $external_source ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Source (e.g. freshbooks)', 'ndizi-project-management' ); ?>" style="width: 48%; margin-right: 2%;">
					<input type="text" name="ndizi_external_id" id="ndizi_external_id" value="<?php echo esc_attr( $external_id ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'External ID', 'ndizi-project-management' ); ?>" style="width: 48%;">
				</td>
			</tr>
			<?php if ( $project_id ) : ?>
				<tr>
					<th><?php esc_html_e( 'Aggregate Time Entries', 'ndizi-project-management' ); ?></th>
					<td>
						<div class="ndizi-invoice-time-picker">
							<p class="description"><?php esc_html_e( 'Select the billable time entries to include on this invoice:', 'ndizi-project-management' ); ?></p>
							<div class="ndizi-invoice-time-scroll">
								<?php if ( empty( $time_entries ) ) : ?>
									<p><em><?php esc_html_e( 'No uninvoiced billable time entries found for this project.', 'ndizi-project-management' ); ?></em></p>
								<?php else : ?>
									<table class="widefat striped">
										<thead>
											<tr>
												<th><input type="checkbox" id="ndizi_select_all_invoice_time"></th>
												<th><?php esc_html_e( 'Date', 'ndizi-project-management' ); ?></th>
												<th><?php esc_html_e( 'User', 'ndizi-project-management' ); ?></th>
												<th><?php esc_html_e( 'Description', 'ndizi-project-management' ); ?></th>
												<th><?php esc_html_e( 'Hours', 'ndizi-project-management' ); ?></th>
												<th><?php esc_html_e( 'Rate ($/h)', 'ndizi-project-management' ); ?></th>
												<th><?php esc_html_e( 'Subtotal ($)', 'ndizi-project-management' ); ?></th>
											</tr>
										</thead>
										<tbody>
											<?php
											foreach ( $time_entries as $entry ) :
												$entry_user = get_userdata( $entry->user_id );
												$is_linked  = ( intval( $entry->invoice_id ) === $post->ID );

												// Resolve the billing rate hierarchically: Task Override -> User Billing Rate -> Project Default Rate
												$resolved_rate = '';
												if ( $entry->task_id ) {
													$resolved_rate = get_post_meta( $entry->task_id, '_ndizi_task_hourly_rate', true );
												}
												if ( '' === $resolved_rate && $entry->user_id ) {
													$resolved_rate = get_user_meta( $entry->user_id, '_ndizi_user_billing_rate', true );
												}
												if ( '' === $resolved_rate && $entry->project_id ) {
													$resolved_rate = get_post_meta( $entry->project_id, '_ndizi_project_hourly_rate', true );
												}
												$resolved_rate = '' !== $resolved_rate ? floatval( $resolved_rate ) : 0.0;
												$subtotal      = round( ( $entry->duration / 3600 ) * $resolved_rate, 2 );
												?>
												<tr>
													<td>
														<input type="checkbox" name="ndizi_invoice_time_entries[]" value="<?php echo esc_attr( $entry->id ); ?>" <?php checked( $is_linked ); ?> class="ndizi-invoice-time-checkbox" data-duration="<?php echo esc_attr( $entry->duration ); ?>" data-rate="<?php echo esc_attr( $resolved_rate ); ?>">
													</td>
													<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $entry->start_time ) ) ); ?></td>
													<td><?php echo $entry_user ? esc_html( $entry_user->display_name ) : '-'; ?></td>
													<td><?php echo esc_html( $entry->description ); ?></td>
													<td><strong><?php echo esc_html( round( $entry->duration / 3600, 2 ) ); ?>h</strong></td>
													<td><?php echo $resolved_rate ? '$' . esc_html( number_format( $resolved_rate, 2 ) ) : '-'; ?></td>
													<td><?php echo $resolved_rate ? '$' . esc_html( number_format( $subtotal, 2 ) ) : '-'; ?></td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
									<div style="margin-top: 10px;">
										<?php $project_hourly_rate = get_post_meta( $project_id, '_ndizi_project_hourly_rate', true ); ?>
										<input type="number" id="ndizi_hourly_rate" placeholder="<?php esc_attr_e( 'Rate ($/hour)', 'ndizi-project-management' ); ?>" style="width: 100px;" value="<?php echo esc_attr( $project_hourly_rate ); ?>">
										<button type="button" class="button" id="ndizi_btn_calc_invoice"><?php esc_html_e( 'Calculate & Apply Amount', 'ndizi-project-management' ); ?></button>
									</div>
								<?php endif; ?>
							</div>
						</div>
					</td>
				</tr>
			<?php else : ?>
				<tr>
					<th><?php esc_html_e( 'Time Entries', 'ndizi-project-management' ); ?></th>
					<td><p class="description"><?php esc_html_e( 'Select a Project and save/update the invoice first to see eligible time entries.', 'ndizi-project-management' ); ?></p></td>
				</tr>
			<?php endif; ?>
		</table>
		<?php
	}

	/**
	 * Render Contact Meta Box
	 */
	public static function render_contact_meta_box( $post ) {
		wp_nonce_field( 'ndizi_save_contact', 'ndizi_contact_nonce' );

		$email = get_post_meta( $post->ID, '_ndizi_contact_email', true );
		$phone = get_post_meta( $post->ID, '_ndizi_contact_phone', true );
		$role  = get_post_meta( $post->ID, '_ndizi_contact_role', true );

		$assoc_clients = get_post_meta( $post->ID, '_ndizi_associated_clients', true );
		if ( ! is_array( $assoc_clients ) ) {
			$assoc_clients = array();
		}

		$clients = get_posts(
			array(
				'post_type'      => 'ndizi_client',
				'posts_per_page' => -1,
			)
		);
		?>
		<table class="form-table ndizi-meta-table">
			<tr>
				<th><label for="ndizi_contact_email"><?php esc_html_e( 'Email Address', 'ndizi-project-management' ); ?></label></th>
				<td><input type="email" name="ndizi_contact_email" id="ndizi_contact_email" value="<?php echo esc_attr( $email ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="ndizi_contact_phone"><?php esc_html_e( 'Phone Number', 'ndizi-project-management' ); ?></label></th>
				<td><input type="text" name="ndizi_contact_phone" id="ndizi_contact_phone" value="<?php echo esc_attr( $phone ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th><label for="ndizi_contact_role"><?php esc_html_e( 'Role / Title', 'ndizi-project-management' ); ?></label></th>
				<td><input type="text" name="ndizi_contact_role" id="ndizi_contact_role" value="<?php echo esc_attr( $role ); ?>" class="regular-text" placeholder="e.g. Project Manager, Billing Contact"></td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Associated Clients', 'ndizi-project-management' ); ?></label></th>
				<td>
					<div class="ndizi-checkbox-list" style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
						<?php foreach ( $clients as $client ) : ?>
							<label style="display: block; margin-bottom: 5px;">
								<input type="checkbox" name="ndizi_associated_clients[]" value="<?php echo esc_attr( $client->ID ); ?>" <?php checked( in_array( $client->ID, $assoc_clients, true ) ); ?>>
								<?php echo esc_html( $client->post_title ); ?>
							</label>
						<?php endforeach; ?>
					</div>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Render Time Off Meta Box
	 */
	public static function render_time_off_meta_box( $post ) {
		wp_nonce_field( 'ndizi_save_time_off', 'ndizi_time_off_nonce' );

		$start_date = get_post_meta( $post->ID, '_ndizi_time_off_start_date', true );
		$end_date   = get_post_meta( $post->ID, '_ndizi_time_off_end_date', true );
		$type       = get_post_meta( $post->ID, '_ndizi_time_off_type', true );
		$status     = get_post_meta( $post->ID, '_ndizi_time_off_status', true );
		$user_id    = get_post_meta( $post->ID, '_ndizi_time_off_user_id', true );

		if ( ! $status ) {
			$status = 'pending';
		}
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$users      = get_users( array( 'capability' => 'ndizi_log_time' ) );
		$is_manager = current_user_can( 'manage_options' ) || current_user_can( 'ndizi_view_reports' );
		?>
		<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; padding: 10px 0;">
			<div>
				<label for="ndizi_time_off_user_id" style="display: block; font-weight: 600; margin-bottom: 8px;"><?php esc_html_e( 'Team Member', 'ndizi-project-management' ); ?></label>
				<?php if ( $is_manager ) : ?>
					<select name="ndizi_time_off_user_id" id="ndizi_time_off_user_id" style="width: 100%;">
						<?php foreach ( $users as $u ) : ?>
							<option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( $user_id, $u->ID ); ?>><?php echo esc_html( $u->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
				<?php else : ?>
					<input type="hidden" name="ndizi_time_off_user_id" value="<?php echo esc_attr( $user_id ); ?>">
					<?php $readonly_user = get_userdata( $user_id ); ?>
					<input type="text" readonly value="<?php echo esc_attr( $readonly_user ? $readonly_user->display_name : __( 'Unknown User', 'ndizi-project-management' ) ); ?>" style="width: 100%; background: #f1f5f9;">
				<?php endif; ?>
			</div>

			<div>
				<label for="ndizi_time_off_start_date" style="display: block; font-weight: 600; margin-bottom: 8px;"><?php esc_html_e( 'Start Date', 'ndizi-project-management' ); ?></label>
				<input type="date" name="ndizi_time_off_start_date" id="ndizi_time_off_start_date" value="<?php echo esc_attr( $start_date ); ?>" style="width: 100%;" required>
			</div>

			<div>
				<label for="ndizi_time_off_end_date" style="display: block; font-weight: 600; margin-bottom: 8px;"><?php esc_html_e( 'End Date', 'ndizi-project-management' ); ?></label>
				<input type="date" name="ndizi_time_off_end_date" id="ndizi_time_off_end_date" value="<?php echo esc_attr( $end_date ); ?>" style="width: 100%;" required>
			</div>

			<div>
				<label for="ndizi_time_off_type" style="display: block; font-weight: 600; margin-bottom: 8px;"><?php esc_html_e( 'Type', 'ndizi-project-management' ); ?></label>
				<select name="ndizi_time_off_type" id="ndizi_time_off_type" style="width: 100%;" required>
					<option value="vacation" <?php selected( $type, 'vacation' ); ?>><?php esc_html_e( 'Vacation', 'ndizi-project-management' ); ?></option>
					<option value="sick_leave" <?php selected( $type, 'sick_leave' ); ?>><?php esc_html_e( 'Sick Leave', 'ndizi-project-management' ); ?></option>
					<option value="personal" <?php selected( $type, 'personal' ); ?>><?php esc_html_e( 'Personal Leave', 'ndizi-project-management' ); ?></option>
					<option value="other" <?php selected( $type, 'other' ); ?>><?php esc_html_e( 'Other', 'ndizi-project-management' ); ?></option>
				</select>
			</div>

			<div>
				<label for="ndizi_time_off_status" style="display: block; font-weight: 600; margin-bottom: 8px;"><?php esc_html_e( 'Status', 'ndizi-project-management' ); ?></label>
				<?php if ( $is_manager ) : ?>
					<select name="ndizi_time_off_status" id="ndizi_time_off_status" style="width: 100%; font-weight: bold;">
						<option value="pending" <?php selected( $status, 'pending' ); ?> style="color: #fbbf24; font-weight: bold;"><?php esc_html_e( 'Pending', 'ndizi-project-management' ); ?></option>
						<option value="approved" <?php selected( $status, 'approved' ); ?> style="color: #16a34a; font-weight: bold;"><?php esc_html_e( 'Approved', 'ndizi-project-management' ); ?></option>
						<option value="rejected" <?php selected( $status, 'rejected' ); ?> style="color: #dc2626; font-weight: bold;"><?php esc_html_e( 'Rejected', 'ndizi-project-management' ); ?></option>
					</select>
				<?php else : ?>
					<input type="hidden" name="ndizi_time_off_status" value="<?php echo esc_attr( $status ); ?>">
					<span style="font-weight: bold; display: inline-block; padding: 6px 12px; border-radius: 4px; border: 1px solid #e2e8f0; background: #f8fafc;
						color: <?php echo 'approved' === $status ? '#16a34a' : ( 'rejected' === $status ? '#dc2626' : '#d97706' ); ?>;">
						<?php echo esc_html( ucfirst( $status ) ); ?>
					</span>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Save Meta Box Submissions
	 */
	public static function save_meta_boxes( $post_id ) {
		// Avoid autosave, revision, and bulk edit saves.
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		// Authorization: the current user must be able to edit this specific post.
		// (Nonces below guard against CSRF; this guards against privilege escalation.)
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Verify the post type before each save block so a nonce from one post type
		// cannot write metadata onto a post of another type.
		$post_type = get_post_type( $post_id );

		// 1. Client Save
		if ( 'ndizi_client' === $post_type && isset( $_POST['ndizi_client_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ndizi_client_nonce'] ) ), 'ndizi_save_client' ) ) {
			if ( isset( $_POST['ndizi_client_website'] ) ) {
				update_post_meta( $post_id, '_ndizi_client_website', esc_url_raw( wp_unslash( $_POST['ndizi_client_website'] ) ) );
			}
			if ( isset( $_POST['ndizi_client_address'] ) ) {
				update_post_meta( $post_id, '_ndizi_client_address', sanitize_textarea_field( wp_unslash( $_POST['ndizi_client_address'] ) ) );
			}
			if ( isset( $_POST['ndizi_client_status'] ) ) {
				update_post_meta( $post_id, '_ndizi_client_status', sanitize_text_field( wp_unslash( $_POST['ndizi_client_status'] ) ) );
			}
			if ( isset( $_POST['ndizi_client_auth_key'] ) ) {
				update_post_meta( $post_id, '_ndizi_client_auth_key', sanitize_text_field( wp_unslash( $_POST['ndizi_client_auth_key'] ) ) );
			}
			if ( isset( $_POST['ndizi_external_source'] ) ) {
				update_post_meta( $post_id, '_ndizi_external_source', sanitize_text_field( wp_unslash( $_POST['ndizi_external_source'] ) ) );
			}
			if ( isset( $_POST['ndizi_external_id'] ) ) {
				update_post_meta( $post_id, '_ndizi_external_id', sanitize_text_field( wp_unslash( $_POST['ndizi_external_id'] ) ) );
			}
		}

		// 2. Project Save
		if ( 'ndizi_project' === $post_type && isset( $_POST['ndizi_project_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ndizi_project_nonce'] ) ), 'ndizi_save_project' ) ) {
			if ( isset( $_POST['ndizi_client_id'] ) ) {
				update_post_meta( $post_id, '_ndizi_client_id', intval( $_POST['ndizi_client_id'] ) );
			}
			if ( isset( $_POST['ndizi_project_start_date'] ) ) {
				update_post_meta( $post_id, '_ndizi_project_start_date', sanitize_text_field( wp_unslash( $_POST['ndizi_project_start_date'] ) ) );
			}
			if ( isset( $_POST['ndizi_project_end_date'] ) ) {
				update_post_meta( $post_id, '_ndizi_project_end_date', sanitize_text_field( wp_unslash( $_POST['ndizi_project_end_date'] ) ) );
			}
			if ( isset( $_POST['ndizi_project_budget'] ) ) {
				update_post_meta( $post_id, '_ndizi_project_budget', floatval( $_POST['ndizi_project_budget'] ) );
			}
			if ( isset( $_POST['ndizi_project_hourly_rate'] ) ) {
				update_post_meta( $post_id, '_ndizi_project_hourly_rate', max( 0.0, floatval( $_POST['ndizi_project_hourly_rate'] ) ) );
			}
			if ( isset( $_POST['ndizi_project_status'] ) ) {
				update_post_meta( $post_id, '_ndizi_project_status', sanitize_text_field( wp_unslash( $_POST['ndizi_project_status'] ) ) );
			}
			if ( isset( $_POST['ndizi_external_source'] ) ) {
				update_post_meta( $post_id, '_ndizi_external_source', sanitize_text_field( wp_unslash( $_POST['ndizi_external_source'] ) ) );
			}
			if ( isset( $_POST['ndizi_external_id'] ) ) {
				update_post_meta( $post_id, '_ndizi_external_id', sanitize_text_field( wp_unslash( $_POST['ndizi_external_id'] ) ) );
			}
		}

		// 3. Task Save
		if ( 'ndizi_task' === $post_type && isset( $_POST['ndizi_task_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ndizi_task_nonce'] ) ), 'ndizi_save_task' ) ) {
			if ( isset( $_POST['ndizi_project_id'] ) ) {
				update_post_meta( $post_id, '_ndizi_project_id', intval( $_POST['ndizi_project_id'] ) );
			}
			if ( isset( $_POST['ndizi_assigned_user_id'] ) ) {
				update_post_meta( $post_id, '_ndizi_assigned_user_id', intval( $_POST['ndizi_assigned_user_id'] ) );
			}
			if ( isset( $_POST['ndizi_task_status'] ) ) {
				update_post_meta( $post_id, '_ndizi_task_status', sanitize_text_field( wp_unslash( $_POST['ndizi_task_status'] ) ) );
			}
			if ( isset( $_POST['ndizi_task_priority'] ) ) {
				update_post_meta( $post_id, '_ndizi_task_priority', sanitize_text_field( wp_unslash( $_POST['ndizi_task_priority'] ) ) );
			}
			if ( isset( $_POST['ndizi_task_due_date'] ) ) {
				update_post_meta( $post_id, '_ndizi_task_due_date', sanitize_text_field( wp_unslash( $_POST['ndizi_task_due_date'] ) ) );
			}
			if ( isset( $_POST['ndizi_task_hourly_rate'] ) ) {
				update_post_meta( $post_id, '_ndizi_task_hourly_rate', max( 0.0, floatval( $_POST['ndizi_task_hourly_rate'] ) ) );
			}
		}

		// 4. Invoice Save
		if ( 'ndizi_invoice' === $post_type && isset( $_POST['ndizi_invoice_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ndizi_invoice_nonce'] ) ), 'ndizi_save_invoice' ) ) {
			if ( isset( $_POST['ndizi_client_id'] ) ) {
				update_post_meta( $post_id, '_ndizi_client_id', intval( $_POST['ndizi_client_id'] ) );
			}
			if ( isset( $_POST['ndizi_project_id'] ) ) {
				update_post_meta( $post_id, '_ndizi_project_id', intval( $_POST['ndizi_project_id'] ) );
			}
			if ( isset( $_POST['ndizi_invoice_number'] ) ) {
				update_post_meta( $post_id, '_ndizi_invoice_number', sanitize_text_field( wp_unslash( $_POST['ndizi_invoice_number'] ) ) );
			}
			if ( isset( $_POST['ndizi_invoice_currency'] ) ) {
				update_post_meta( $post_id, '_ndizi_invoice_currency', strtoupper( sanitize_text_field( wp_unslash( $_POST['ndizi_invoice_currency'] ) ) ) );
			}
			if ( isset( $_POST['ndizi_invoice_date'] ) ) {
				update_post_meta( $post_id, '_ndizi_invoice_date', sanitize_text_field( wp_unslash( $_POST['ndizi_invoice_date'] ) ) );
			}
			if ( isset( $_POST['ndizi_invoice_due_date'] ) ) {
				update_post_meta( $post_id, '_ndizi_invoice_due_date', sanitize_text_field( wp_unslash( $_POST['ndizi_invoice_due_date'] ) ) );
			}
			if ( isset( $_POST['ndizi_invoice_amount'] ) ) {
				update_post_meta( $post_id, '_ndizi_invoice_amount', floatval( $_POST['ndizi_invoice_amount'] ) );
			}
			if ( isset( $_POST['ndizi_invoice_status'] ) ) {
				update_post_meta( $post_id, '_ndizi_invoice_status', sanitize_text_field( wp_unslash( $_POST['ndizi_invoice_status'] ) ) );
			}
			if ( isset( $_POST['ndizi_external_source'] ) ) {
				update_post_meta( $post_id, '_ndizi_external_source', sanitize_text_field( wp_unslash( $_POST['ndizi_external_source'] ) ) );
			}
			if ( isset( $_POST['ndizi_external_id'] ) ) {
				update_post_meta( $post_id, '_ndizi_external_id', sanitize_text_field( wp_unslash( $_POST['ndizi_external_id'] ) ) );
			}

			// Process structured line items
			$descs  = isset( $_POST['ndizi_line_items_desc'] ) && is_array( $_POST['ndizi_line_items_desc'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['ndizi_line_items_desc'] ) ) : array();
			$qtys   = isset( $_POST['ndizi_line_items_qty'] ) && is_array( $_POST['ndizi_line_items_qty'] ) ? array_map( 'floatval', wp_unslash( $_POST['ndizi_line_items_qty'] ) ) : array();
			$prices = isset( $_POST['ndizi_line_items_price'] ) && is_array( $_POST['ndizi_line_items_price'] ) ? array_map( 'floatval', wp_unslash( $_POST['ndizi_line_items_price'] ) ) : array();

			$saved_items = array();
			foreach ( $descs as $idx => $desc ) {
				$qty   = isset( $qtys[ $idx ] ) ? floatval( $qtys[ $idx ] ) : 0.0;
				$price = isset( $prices[ $idx ] ) ? floatval( $prices[ $idx ] ) : 0.0;
				if ( '' === trim( $desc ) && 0.0 === $qty && 0.0 === $price ) {
					continue;
				}
				$amt           = round( $qty * $price, 2 );
				$saved_items[] = array(
					'description' => $desc,
					'quantity'    => $qty,
					'unit_price'  => $price,
					'amount'      => $amt,
				);
			}
			update_post_meta( $post_id, '_ndizi_invoice_line_items', $saved_items );

			// Process payment records.
			$pay_dates   = isset( $_POST['ndizi_payments_date'] ) && is_array( $_POST['ndizi_payments_date'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['ndizi_payments_date'] ) ) : array();
			$pay_amounts = isset( $_POST['ndizi_payments_amount'] ) && is_array( $_POST['ndizi_payments_amount'] ) ? array_map( 'floatval', wp_unslash( $_POST['ndizi_payments_amount'] ) ) : array();
			$pay_methods = isset( $_POST['ndizi_payments_method'] ) && is_array( $_POST['ndizi_payments_method'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['ndizi_payments_method'] ) ) : array();
			$pay_notes   = isset( $_POST['ndizi_payments_note'] ) && is_array( $_POST['ndizi_payments_note'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['ndizi_payments_note'] ) ) : array();

			$saved_payments = array();
			$total_paid     = 0.0;
			foreach ( $pay_amounts as $idx => $amt ) {
				$amt  = floatval( $amt );
				$date = isset( $pay_dates[ $idx ] ) ? $pay_dates[ $idx ] : '';
				if ( 0.0 === $amt && '' === trim( $date ) ) {
					continue;
				}
				$total_paid      += $amt;
				$saved_payments[] = array(
					'date'   => $date,
					'amount' => $amt,
					'method' => isset( $pay_methods[ $idx ] ) ? $pay_methods[ $idx ] : '',
					'note'   => isset( $pay_notes[ $idx ] ) ? $pay_notes[ $idx ] : '',
				);
			}
			update_post_meta( $post_id, '_ndizi_invoice_payments', $saved_payments );

			// Derive status from payments unless the invoice is a draft or voided.
			$current_status = get_post_meta( $post_id, '_ndizi_invoice_status', true );
			if ( 'draft' !== $current_status && 'void' !== $current_status ) {
				$invoice_total = floatval( get_post_meta( $post_id, '_ndizi_invoice_amount', true ) );
				if ( $invoice_total > 0 && $total_paid >= $invoice_total ) {
					$derived = 'paid';
				} elseif ( $total_paid > 0 ) {
					$derived = 'partial';
				} else {
					$derived = 'sent';
				}
				update_post_meta( $post_id, '_ndizi_invoice_status', $derived );
			}

			// Clear all existing time entries linked to this invoice first, then relink selected ones
			global $wpdb;
			$table_name = Ndizi_DB::get_table_name();
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Unlinking time entries from an invoice on the plugin's custom table.
			$wpdb->update(
				$table_name,
				array( 'invoice_id' => 0 ),
				array( 'invoice_id' => $post_id ),
				array( '%d' ),
				array( '%d' )
			);

			if ( isset( $_POST['ndizi_invoice_time_entries'] ) && is_array( $_POST['ndizi_invoice_time_entries'] ) ) {
				$selected_entry_ids = array_map( 'intval', wp_unslash( $_POST['ndizi_invoice_time_entries'] ) );
				if ( ! empty( $selected_entry_ids ) ) {
					// Relink all selected entries in a single bulk query rather than one query per entry.
					$placeholders = implode( ',', array_fill( 0, count( $selected_entry_ids ), '%d' ) );
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name from $wpdb->prefix; the IN() list is built from per-id %d placeholders and prepared against the selected ids.
					$wpdb->query( $wpdb->prepare( "UPDATE $table_name SET invoice_id = %d WHERE id IN ($placeholders)", array_merge( array( $post_id ), $selected_entry_ids ) ) );
				}
			}
		}

		// 5. Contact Save
		if ( 'ndizi_contact' === $post_type && isset( $_POST['ndizi_contact_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ndizi_contact_nonce'] ) ), 'ndizi_save_contact' ) ) {
			if ( isset( $_POST['ndizi_contact_email'] ) ) {
				update_post_meta( $post_id, '_ndizi_contact_email', sanitize_email( wp_unslash( $_POST['ndizi_contact_email'] ) ) );
			}
			if ( isset( $_POST['ndizi_contact_phone'] ) ) {
				update_post_meta( $post_id, '_ndizi_contact_phone', sanitize_text_field( wp_unslash( $_POST['ndizi_contact_phone'] ) ) );
			}
			if ( isset( $_POST['ndizi_contact_role'] ) ) {
				update_post_meta( $post_id, '_ndizi_contact_role', sanitize_text_field( wp_unslash( $_POST['ndizi_contact_role'] ) ) );
			}

			$clients_array = isset( $_POST['ndizi_associated_clients'] ) && is_array( $_POST['ndizi_associated_clients'] ) ? array_map( 'intval', $_POST['ndizi_associated_clients'] ) : array();
			update_post_meta( $post_id, '_ndizi_associated_clients', $clients_array );
		}

		// 6. Time Off Save
		if ( 'ndizi_time_off' === $post_type && isset( $_POST['ndizi_time_off_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ndizi_time_off_nonce'] ) ), 'ndizi_save_time_off' ) ) {
			if ( isset( $_POST['ndizi_time_off_user_id'] ) ) {
				if ( current_user_can( 'manage_options' ) || current_user_can( 'ndizi_view_reports' ) ) {
					update_post_meta( $post_id, '_ndizi_time_off_user_id', intval( $_POST['ndizi_time_off_user_id'] ) );
				} else {
					$existing_user = get_post_meta( $post_id, '_ndizi_time_off_user_id', true );
					if ( ! $existing_user ) {
						update_post_meta( $post_id, '_ndizi_time_off_user_id', get_current_user_id() );
					}
				}
			}
			if ( isset( $_POST['ndizi_time_off_start_date'] ) ) {
				update_post_meta( $post_id, '_ndizi_time_off_start_date', sanitize_text_field( wp_unslash( $_POST['ndizi_time_off_start_date'] ) ) );
			}
			if ( isset( $_POST['ndizi_time_off_end_date'] ) ) {
				update_post_meta( $post_id, '_ndizi_time_off_end_date', sanitize_text_field( wp_unslash( $_POST['ndizi_time_off_end_date'] ) ) );
			}
			if ( isset( $_POST['ndizi_time_off_type'] ) ) {
				update_post_meta( $post_id, '_ndizi_time_off_type', sanitize_text_field( wp_unslash( $_POST['ndizi_time_off_type'] ) ) );
			}
			if ( isset( $_POST['ndizi_time_off_status'] ) ) {
				if ( current_user_can( 'manage_options' ) || current_user_can( 'ndizi_view_reports' ) ) {
					update_post_meta( $post_id, '_ndizi_time_off_status', sanitize_text_field( wp_unslash( $_POST['ndizi_time_off_status'] ) ) );
				} else {
					$existing_status = get_post_meta( $post_id, '_ndizi_time_off_status', true );
					if ( ! $existing_status ) {
						update_post_meta( $post_id, '_ndizi_time_off_status', 'pending' );
					}
				}
			}
		}
	}
}
