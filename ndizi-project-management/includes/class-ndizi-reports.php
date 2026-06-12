<?php
/**
 * Reports page for Ndizi Project Management.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ndizi_Reports {

	/**
	 * Initialize reports hooks (page registered by Ndizi_Settings)
	 */
	public static function init() {}

	/**
	 * Render Reports Dashboard Page (gorgeous custom reports with filters and CSS charts)
	 */
	public static function render_reports_page() {
		if ( ! current_user_can( 'ndizi_view_reports' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'ndizi-project-management' ) );
		}

		// Handle Approval Actions
		if ( isset( $_POST['ndizi_approval_action'] ) && isset( $_POST['ndizi_time_entry_ids'] ) ) {
			check_admin_referer( 'ndizi_reports_approval', 'ndizi_approval_nonce' );

			$entry_ids       = array_map( 'absint', (array) $_POST['ndizi_time_entry_ids'] );
			$action          = sanitize_key( $_POST['ndizi_approval_action'] );
			$current_user_id = get_current_user_id();

			if ( ! empty( $entry_ids ) ) {
				foreach ( $entry_ids as $entry_id ) {
					if ( 'approve' === $action ) {
						Ndizi_DB::update_time_entry(
							$entry_id,
							array(
								'approved'    => 1,
								'approved_by' => $current_user_id,
							)
						);
					} elseif ( 'unapprove' === $action ) {
						Ndizi_DB::update_time_entry(
							$entry_id,
							array(
								'approved'    => 0,
								'approved_by' => 0,
							)
						);
					}
				}
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Timesheet statuses updated successfully!', 'ndizi-project-management' ) . '</p></div>';
			}
		}

		// Read-only, bookmarkable report filters from the query string; the page
		// itself is already gated by the ndizi_view_reports capability, and no
		// state is changed here, so a nonce is not appropriate.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$project_id = isset( $_GET['project_id'] ) ? intval( $_GET['project_id'] ) : 0;
		$user_id    = isset( $_GET['user_id'] ) ? intval( $_GET['user_id'] ) : 0;
		$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : gmdate( 'Y-m-01' ); // first day of month
		$end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : gmdate( 'Y-m-t' ); // last day of month
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Fetch projects and team members for dropdown filters
		$projects = get_posts(
			array(
				'post_type'      => 'ndizi_project',
				'posts_per_page' => -1,
			)
		);
		$users    = get_users( array( 'capability' => 'ndizi_log_time' ) );

		// Query aggregate data
		$project_totals = Ndizi_DB::get_time_totals(
			array(
				'project_id' => $project_id ? $project_id : null,
				'user_id'    => $user_id ? $user_id : null,
				'start_date' => $start_date,
				'end_date'   => $end_date,
				'groupby'    => 'project_id',
			)
		);

		$user_totals = Ndizi_DB::get_time_totals(
			array(
				'project_id' => $project_id ? $project_id : null,
				'user_id'    => $user_id ? $user_id : null,
				'start_date' => $start_date,
				'end_date'   => $end_date,
				'groupby'    => 'user_id',
			)
		);

		$overall_seconds          = 0;
		$overall_billable_seconds = 0;
		foreach ( $project_totals as $p_total ) {
			$overall_seconds          += $p_total->total_duration;
			$overall_billable_seconds += $p_total->billable_duration;
		}

		$overall_hours          = round( $overall_seconds / 3600, 2 );
		$overall_billable_hours = round( $overall_billable_seconds / 3600, 2 );
		$billable_percentage    = $overall_hours > 0 ? round( ( $overall_billable_hours / $overall_hours ) * 100 ) : 0;

		// Query detailed entries for profitability calculations
		$detailed_entries = Ndizi_DB::get_time_entries(
			array(
				'project_id' => $project_id ? $project_id : null,
				'user_id'    => $user_id ? $user_id : null,
				'start_date' => $start_date,
				'end_date'   => $end_date,
				'number'     => -1,
			)
		);

		$overall_revenue     = 0;
		$overall_cost        = 0;
		$project_margin_data = array();

		foreach ( $detailed_entries as $entry ) {
			// Resolve billing rate hierarchically: Task Override -> User Billing Rate -> Project Default Rate
			$entry_rate = '';
			if ( $entry->task_id ) {
				$entry_rate = get_post_meta( $entry->task_id, '_ndizi_task_hourly_rate', true );
			}
			if ( '' === $entry_rate && $entry->user_id ) {
				$entry_rate = get_user_meta( $entry->user_id, '_ndizi_user_billing_rate', true );
			}
			if ( '' === $entry_rate && $entry->project_id ) {
				$entry_rate = get_post_meta( $entry->project_id, '_ndizi_project_hourly_rate', true );
			}
			$entry_rate    = '' !== $entry_rate ? floatval( $entry_rate ) : 0.0;
			$entry_hours   = $entry->duration / 3600;
			$entry_revenue = $entry->billable ? ( $entry_hours * $entry_rate ) : 0;

			// Resolve salary rate (internal cost)
			$salary_rate = 0;
			if ( $entry->user_id ) {
				$salary_rate = get_user_meta( $entry->user_id, '_ndizi_user_salary_rate', true );
			}
			$salary_rate = floatval( $salary_rate );
			$entry_cost  = $entry_hours * $salary_rate;

			$overall_revenue += $entry_revenue;
			$overall_cost    += $entry_cost;

			// Group by project
			if ( ! isset( $project_margin_data[ $entry->project_id ] ) ) {
				$project_margin_data[ $entry->project_id ] = array(
					'hours'   => 0,
					'revenue' => 0,
					'cost'    => 0,
				);
			}
			$project_margin_data[ $entry->project_id ]['hours']   += $entry_hours;
			$project_margin_data[ $entry->project_id ]['revenue'] += $entry_revenue;
			$project_margin_data[ $entry->project_id ]['cost']    += $entry_cost;
		}

		$overall_margin     = $overall_revenue - $overall_cost;
		$overall_margin_pct = $overall_revenue > 0 ? round( ( $overall_margin / $overall_revenue ) * 100, 1 ) : 0;

		$unapproved_entries = array();
		$approved_entries   = array();
		foreach ( $detailed_entries as $entry ) {
			if ( $entry->approved ) {
				$approved_entries[] = $entry;
			} else {
				$unapproved_entries[] = $entry;
			}
		}
		?>
		<div class="wrap ndizi-reports-page">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Ndizi Time Reports', 'ndizi-project-management' ); ?></h1>
			<hr class="wp-header-end">

			<!-- Reports filter header -->
			<div class="ndizi-reports-filter-card">
				<form method="get" action="">
					<input type="hidden" name="post_type" value="ndizi_project">
					<input type="hidden" name="page" value="ndizi-reports">

					<div class="ndizi-filter-row">
						<div class="ndizi-filter-col">
							<label for="project_id"><?php esc_html_e( 'Project', 'ndizi-project-management' ); ?></label>
							<select name="project_id" id="project_id">
								<option value="0"><?php esc_html_e( 'All Projects', 'ndizi-project-management' ); ?></option>
								<?php foreach ( $projects as $proj ) : ?>
									<option value="<?php echo esc_attr( $proj->ID ); ?>" <?php selected( $project_id, $proj->ID ); ?>>
										<?php echo esc_html( $proj->post_title ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="ndizi-filter-col">
							<label for="user_id"><?php esc_html_e( 'Team Member', 'ndizi-project-management' ); ?></label>
							<select name="user_id" id="user_id">
								<option value="0"><?php esc_html_e( 'All Members', 'ndizi-project-management' ); ?></option>
								<?php foreach ( $users as $u ) : ?>
									<option value="<?php echo esc_attr( $u->ID ); ?>" <?php selected( $user_id, $u->ID ); ?>>
										<?php echo esc_html( $u->display_name ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="ndizi-filter-col">
							<label for="start_date"><?php esc_html_e( 'Start Date', 'ndizi-project-management' ); ?></label>
							<input type="date" name="start_date" id="start_date" value="<?php echo esc_attr( $start_date ); ?>">
						</div>

						<div class="ndizi-filter-col">
							<label for="end_date"><?php esc_html_e( 'End Date', 'ndizi-project-management' ); ?></label>
							<input type="date" name="end_date" id="end_date" value="<?php echo esc_attr( $end_date ); ?>">
						</div>

						<div class="ndizi-filter-col filter-actions">
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Filter Report', 'ndizi-project-management' ); ?></button>
							<?php
							$csv_export_url        = wp_nonce_url(
								add_query_arg( 'ndizi_export_report', 'csv' ),
								'ndizi_export_report_nonce'
							);
							$quickbooks_export_url = wp_nonce_url(
								add_query_arg( 'ndizi_export_report', 'quickbooks_csv' ),
								'ndizi_export_report_nonce'
							);
							?>
							<a href="<?php echo esc_url( $csv_export_url ); ?>" class="button button-secondary" style="background: #10b981 !important; border-color: #10b981 !important; color: #fff !important; line-height: 36px; min-height: 38px;"><?php esc_html_e( 'Export CSV', 'ndizi-project-management' ); ?></a>
							<a href="<?php echo esc_url( $quickbooks_export_url ); ?>" class="button button-secondary" style="background: #059669 !important; border-color: #059669 !important; color: #fff !important; line-height: 36px; min-height: 38px;"><?php esc_html_e( 'Export QuickBooks CSV', 'ndizi-project-management' ); ?></a>
							<a href="edit.php?post_type=ndizi_project&page=ndizi-reports" class="button button-secondary"><?php esc_html_e( 'Reset', 'ndizi-project-management' ); ?></a>
						</div>
					</div>
				</form>
			</div>

			<!-- KPI Cards -->
			<div class="ndizi-kpi-grid">
				<div class="ndizi-kpi-card">
					<span class="ndizi-kpi-title"><?php esc_html_e( 'Total Hours Tracked', 'ndizi-project-management' ); ?></span>
					<span class="ndizi-kpi-val"><?php echo esc_html( $overall_hours ); ?>h</span>
				</div>
				<div class="ndizi-kpi-card">
					<span class="ndizi-kpi-title"><?php esc_html_e( 'Billable Hours', 'ndizi-project-management' ); ?></span>
					<span class="ndizi-kpi-val ndizi-kpi-billable"><?php echo esc_html( $overall_billable_hours ); ?>h</span>
				</div>
				<div class="ndizi-kpi-card">
					<span class="ndizi-kpi-title"><?php esc_html_e( 'Billable Ratio', 'ndizi-project-management' ); ?></span>
					<span class="ndizi-kpi-val"><?php echo esc_html( $billable_percentage ); ?>%</span>
					<div class="ndizi-ratio-bar"><div class="ndizi-ratio-fill" style="width: <?php echo esc_attr( $billable_percentage ); ?>%"></div></div>
				</div>
				<div class="ndizi-kpi-card">
					<span class="ndizi-kpi-title"><?php esc_html_e( 'Total Revenue', 'ndizi-project-management' ); ?></span>
					<span class="ndizi-kpi-val" style="color: #10b981;">$<?php echo esc_html( number_format( $overall_revenue, 2 ) ); ?></span>
				</div>
				<div class="ndizi-kpi-card">
					<span class="ndizi-kpi-title"><?php esc_html_e( 'Net Margin', 'ndizi-project-management' ); ?></span>
					<span class="ndizi-kpi-val" style="color: #4f46e5;">$<?php echo esc_html( number_format( $overall_margin, 2 ) ); ?></span>
					<div style="font-size: 12px; color: #64748b; margin-top: 4px; font-weight: 500;"><?php echo esc_html( $overall_margin_pct ); ?>% <?php esc_html_e( 'margin', 'ndizi-project-management' ); ?></div>
				</div>
			</div>

			<!-- Graphical/Bar representation grids -->
			<div class="ndizi-charts-grid">
				<!-- Project Hours Chart -->
				<div class="ndizi-chart-card">
					<h3><?php esc_html_e( 'Hours by Project', 'ndizi-project-management' ); ?></h3>
					<?php if ( empty( $project_totals ) ) : ?>
						<p class="no-data-msg"><?php esc_html_e( 'No log data available for this range.', 'ndizi-project-management' ); ?></p>
						<?php
					else :
						// Find max total to scale widths relative to maximum
						$max_p_total = 1;
						foreach ( $project_totals as $t ) {
							if ( $t->total_duration > $max_p_total ) {
								$max_p_total = $t->total_duration;
							}
						}
						?>
						<div class="ndizi-custom-barchart">
							<?php
							foreach ( $project_totals as $t ) :
								$proj = get_post( $t->group_id );
								if ( ! $proj ) {
									continue;
								}
								$h       = round( $t->total_duration / 3600, 2 );
								$bh      = round( $t->billable_duration / 3600, 2 );
								$percent = round( ( $t->total_duration / $max_p_total ) * 100 );
								?>
								<div class="ndizi-barchart-row">
									<div class="ndizi-barchart-label">
										<a href="<?php echo esc_url( get_edit_post_link( $proj->ID ) ); ?>"><?php echo esc_html( $proj->post_title ); ?></a>
									</div>
									<div class="ndizi-barchart-container">
										<div class="ndizi-barchart-fill" style="width: <?php echo esc_attr( $percent ); ?>%;">
											<span class="ndizi-barchart-val"><?php echo esc_html( $h ); ?>h (<?php echo esc_html( $bh ); ?>h billable)</span>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<!-- Team Member Hours Chart -->
				<div class="ndizi-chart-card">
					<h3><?php esc_html_e( 'Hours by Team Member', 'ndizi-project-management' ); ?></h3>
					<?php if ( empty( $user_totals ) ) : ?>
						<p class="no-data-msg"><?php esc_html_e( 'No log data available for this range.', 'ndizi-project-management' ); ?></p>
						<?php
					else :
						$max_u_total = 1;
						foreach ( $user_totals as $t ) {
							if ( $t->total_duration > $max_u_total ) {
								$max_u_total = $t->total_duration;
							}
						}
						?>
						<div class="ndizi-custom-barchart">
							<?php
							foreach ( $user_totals as $t ) :
								$usr = get_userdata( $t->group_id );
								if ( ! $usr ) {
									continue;
								}
								$h       = round( $t->total_duration / 3600, 2 );
								$percent = round( ( $t->total_duration / $max_u_total ) * 100 );
								?>
								<div class="ndizi-barchart-row">
									<div class="ndizi-barchart-label"><?php echo esc_html( $usr->display_name ); ?></div>
									<div class="ndizi-barchart-container">
										<div class="ndizi-barchart-fill ndizi-fill-member" style="width: <?php echo esc_attr( $percent ); ?>%;">
											<span class="ndizi-barchart-val"><?php echo esc_html( $h ); ?>h</span>
										</div>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>

			<!-- Project Profitability & Margins Table -->
			<div class="ndizi-chart-card" style="margin-top: 25px;">
				<h3 style="margin-bottom: 20px; font-size: 16px; font-weight: 700; color: #1e293b;"><?php esc_html_e( 'Project Profitability & Margins', 'ndizi-project-management' ); ?></h3>
				<?php if ( empty( $project_margin_data ) ) : ?>
					<p class="no-data-msg"><?php esc_html_e( 'No log data available for this range.', 'ndizi-project-management' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped posts" style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: none;">
						<thead>
							<tr>
								<th style="font-weight: 700; padding: 12px;"><?php esc_html_e( 'Project', 'ndizi-project-management' ); ?></th>
								<th style="font-weight: 700; padding: 12px; text-align: right;"><?php esc_html_e( 'Budget', 'ndizi-project-management' ); ?></th>
								<th style="font-weight: 700; padding: 12px; text-align: right;"><?php esc_html_e( 'Tracked Hours', 'ndizi-project-management' ); ?></th>
								<th style="font-weight: 700; padding: 12px; text-align: right;"><?php esc_html_e( 'Billed Revenue', 'ndizi-project-management' ); ?></th>
								<th style="font-weight: 700; padding: 12px; text-align: right;"><?php esc_html_e( 'Internal Cost', 'ndizi-project-management' ); ?></th>
								<th style="font-weight: 700; padding: 12px; text-align: right;"><?php esc_html_e( 'Profit Margin', 'ndizi-project-management' ); ?></th>
								<th style="font-weight: 700; padding: 12px; text-align: right;"><?php esc_html_e( 'Margin %', 'ndizi-project-management' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $project_margin_data as $p_id => $data ) :
								$proj = get_post( $p_id );
								if ( ! $proj ) {
									continue;
								}
								$budget       = floatval( get_post_meta( $p_id, '_ndizi_project_budget', true ) );
								$p_margin     = $data['revenue'] - $data['cost'];
								$p_margin_pct = $data['revenue'] > 0 ? round( ( $p_margin / $data['revenue'] ) * 100, 1 ) : 0;
								?>
								<tr>
									<td style="padding: 12px; font-weight: 600;">
										<a href="<?php echo esc_url( get_edit_post_link( $proj->ID ) ); ?>"><?php echo esc_html( $proj->post_title ); ?></a>
									</td>
									<td style="padding: 12px; text-align: right;">
										<?php echo $budget ? '$' . esc_html( number_format( $budget, 2 ) ) : '<span style="color: #94a3b8;">-</span>'; ?>
									</td>
									<td style="padding: 12px; text-align: right; font-weight: 600;">
										<?php echo esc_html( round( $data['hours'], 2 ) ); ?>h
									</td>
									<td style="padding: 12px; text-align: right; color: #10b981; font-weight: 600;">
										$<?php echo esc_html( number_format( $data['revenue'], 2 ) ); ?>
									</td>
									<td style="padding: 12px; text-align: right; color: #475569;">
										$<?php echo esc_html( number_format( $data['cost'], 2 ) ); ?>
									</td>
									<td style="padding: 12px; text-align: right; font-weight: 600; color: <?php echo $p_margin >= 0 ? '#4f46e5' : '#ef4444'; ?>;">
										$<?php echo esc_html( number_format( $p_margin, 2 ) ); ?>
									</td>
									<td style="padding: 12px; text-align: right; font-weight: 600; color: <?php echo $p_margin >= 0 ? '#4f46e5' : '#ef4444'; ?>;">
										<?php echo esc_html( $p_margin_pct ); ?>%
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
				<?php endif; ?>
			</div>

			<!-- Team Member Utilization Table -->
			<div class="ndizi-chart-card" style="margin-top: 25px;">
				<h3 style="margin-bottom: 20px; font-size: 16px; font-weight: 700; color: #1e293b;"><?php esc_html_e( 'Team Member Utilization & Capacity', 'ndizi-project-management' ); ?></h3>
				<?php if ( empty( $user_totals ) ) : ?>
					<p class="no-data-msg"><?php esc_html_e( 'No log data available for this range.', 'ndizi-project-management' ); ?></p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped posts" style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: none;">
						<thead>
							<tr>
								<th style="font-weight: 700; padding: 12px;"><?php esc_html_e( 'Team Member', 'ndizi-project-management' ); ?></th>
								<th style="font-weight: 700; padding: 12px; text-align: right;"><?php esc_html_e( 'Total Tracked', 'ndizi-project-management' ); ?></th>
								<th style="font-weight: 700; padding: 12px; text-align: right;"><?php esc_html_e( 'Billable Hours', 'ndizi-project-management' ); ?></th>
								<th style="font-weight: 700; padding: 12px; text-align: right;"><?php esc_html_e( 'Non-Billable Hours', 'ndizi-project-management' ); ?></th>
								<th style="font-weight: 700; padding: 12px; text-align: right;"><?php esc_html_e( 'Utilization Rate', 'ndizi-project-management' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $user_totals as $t ) :
								$usr = get_userdata( $t->group_id );
								if ( ! $usr ) {
									continue;
								}
								$tot_h    = $t->total_duration / 3600;
								$bill_h   = $t->billable_duration / 3600;
								$nbill_h  = $tot_h - $bill_h;
								$util_pct = $tot_h > 0 ? round( ( $bill_h / $tot_h ) * 100 ) : 0;
								?>
								<tr>
									<td style="padding: 12px; font-weight: 600;"><?php echo esc_html( $usr->display_name ); ?></td>
									<td style="padding: 12px; text-align: right;"><?php echo esc_html( round( $tot_h, 2 ) ); ?>h</td>
									<td style="padding: 12px; text-align: right; color: #10b981; font-weight: 600;"><?php echo esc_html( round( $bill_h, 2 ) ); ?>h</td>
									<td style="padding: 12px; text-align: right; color: #64748b;"><?php echo esc_html( round( $nbill_h, 2 ) ); ?>h</td>
									<td style="padding: 12px; text-align: right; font-weight: 600;">
										<div style="display: flex; align-items: center; justify-content: flex-end; gap: 10px;">
											<span><?php echo esc_html( $util_pct ); ?>%</span>
											<div style="width: 100px; height: 8px; background-color: #e2e8f0; border-radius: 4px; overflow: hidden; display: inline-block; vertical-align: middle;">
												<div style="height: 100%; width: <?php echo esc_attr( $util_pct ); ?>%; background-color: #4f46e5; border-radius: 4px;"></div>
											</div>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<!-- Timesheet Approvals Card -->
			<div class="ndizi-chart-card" style="margin-top: 25px;">
				<h3 style="margin-bottom: 20px; font-size: 16px; font-weight: 700; color: #1e293b;"><?php esc_html_e( 'Timesheet Approvals', 'ndizi-project-management' ); ?></h3>

				<form method="post" action="">
					<?php wp_nonce_field( 'ndizi_reports_approval', 'ndizi_approval_nonce' ); ?>

					<h4 style="font-weight: 600; color: #475569; margin: 0 0 10px 0;"><?php esc_html_e( 'Pending Approval', 'ndizi-project-management' ); ?> (<?php echo count( $unapproved_entries ); ?>)</h4>
					<?php if ( empty( $unapproved_entries ) ) : ?>
						<p style="color: #64748b; font-style: italic; margin-bottom: 20px;"><?php esc_html_e( 'No time entries pending approval in this range.', 'ndizi-project-management' ); ?></p>
					<?php else : ?>
						<table class="wp-list-table widefat fixed striped posts" style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: none; margin-bottom: 15px;">
							<thead>
								<tr>
									<th style="width: 40px; padding: 12px; text-align: center;"><input type="checkbox" onclick="jQuery('.ndizi-unapproved-checkbox').prop('checked', this.checked);"></th>
									<th style="font-weight: 700; padding: 12px;"><?php esc_html_e( 'Team Member', 'ndizi-project-management' ); ?></th>
									<th style="font-weight: 700; padding: 12px;"><?php esc_html_e( 'Project & Task', 'ndizi-project-management' ); ?></th>
									<th style="font-weight: 700; padding: 12px;"><?php esc_html_e( 'Date & Time', 'ndizi-project-management' ); ?></th>
									<th style="font-weight: 700; padding: 12px;"><?php esc_html_e( 'Description', 'ndizi-project-management' ); ?></th>
									<th style="font-weight: 700; padding: 12px; text-align: right;"><?php esc_html_e( 'Duration', 'ndizi-project-management' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								foreach ( $unapproved_entries as $entry ) :
									$usr   = get_userdata( $entry->user_id );
									$proj  = get_post( $entry->project_id );
									$task  = $entry->task_id ? get_post( $entry->task_id ) : null;
									$dur_h = floor( $entry->duration / 3600 );
									$dur_m = floor( ( $entry->duration % 3600 ) / 60 );
									?>
									<tr>
										<td style="padding: 12px; text-align: center;"><input type="checkbox" class="ndizi-unapproved-checkbox" name="ndizi_time_entry_ids[]" value="<?php echo esc_attr( $entry->id ); ?>"></td>
										<td style="padding: 12px;"><?php echo esc_html( $usr ? $usr->display_name : 'Unknown' ); ?></td>
										<td style="padding: 12px;">
											<strong><?php echo esc_html( $proj ? $proj->post_title : 'Deleted Project' ); ?></strong>
											<?php
											if ( $task ) :
												?>
												<br><span style="color: #64748b; font-size: 11px;"><?php echo esc_html( $task->post_title ); ?></span><?php endif; ?>
										</td>
										<td style="padding: 12px;"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->start_time ) ) ); ?></td>
										<td style="padding: 12px;"><?php echo esc_html( $entry->description ); ?></td>
										<td style="padding: 12px; text-align: right; font-weight: 600;"><?php echo esc_html( sprintf( '%02dh %02dm', $dur_h, $dur_m ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<button type="submit" name="ndizi_approval_action" value="approve" class="button button-primary" style="margin-bottom: 25px;"><?php esc_html_e( 'Approve Selected Entries', 'ndizi-project-management' ); ?></button>
					<?php endif; ?>
				</form>

				<form method="post" action="">
					<?php wp_nonce_field( 'ndizi_reports_approval', 'ndizi_approval_nonce' ); ?>

					<h4 style="font-weight: 600; color: #475569; margin: 20px 0 10px 0;"><?php esc_html_e( 'Already Approved', 'ndizi-project-management' ); ?> (<?php echo count( $approved_entries ); ?>)</h4>
					<?php if ( empty( $approved_entries ) ) : ?>
						<p style="color: #64748b; font-style: italic;"><?php esc_html_e( 'No approved time entries in this range.', 'ndizi-project-management' ); ?></p>
					<?php else : ?>
						<table class="wp-list-table widefat fixed striped posts" style="border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: none; margin-bottom: 15px;">
							<thead>
								<tr>
									<th style="width: 40px; padding: 12px; text-align: center;"><input type="checkbox" onclick="jQuery('.ndizi-approved-checkbox').prop('checked', this.checked);"></th>
									<th style="font-weight: 700; padding: 12px;"><?php esc_html_e( 'Team Member', 'ndizi-project-management' ); ?></th>
									<th style="font-weight: 700; padding: 12px;"><?php esc_html_e( 'Project & Task', 'ndizi-project-management' ); ?></th>
									<th style="font-weight: 700; padding: 12px;"><?php esc_html_e( 'Date & Time', 'ndizi-project-management' ); ?></th>
									<th style="font-weight: 700; padding: 12px;"><?php esc_html_e( 'Description', 'ndizi-project-management' ); ?></th>
									<th style="font-weight: 700; padding: 12px; text-align: right;"><?php esc_html_e( 'Duration', 'ndizi-project-management' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php
								foreach ( $approved_entries as $entry ) :
									$usr   = get_userdata( $entry->user_id );
									$proj  = get_post( $entry->project_id );
									$task  = $entry->task_id ? get_post( $entry->task_id ) : null;
									$dur_h = floor( $entry->duration / 3600 );
									$dur_m = floor( ( $entry->duration % 3600 ) / 60 );
									?>
									<tr>
										<td style="padding: 12px; text-align: center;"><input type="checkbox" class="ndizi-approved-checkbox" name="ndizi_time_entry_ids[]" value="<?php echo esc_attr( $entry->id ); ?>"></td>
										<td style="padding: 12px;"><?php echo esc_html( $usr ? $usr->display_name : 'Unknown' ); ?></td>
										<td style="padding: 12px;">
											<strong><?php echo esc_html( $proj ? $proj->post_title : 'Deleted Project' ); ?></strong>
											<?php
											if ( $task ) :
												?>
												<br><span style="color: #64748b; font-size: 11px;"><?php echo esc_html( $task->post_title ); ?></span><?php endif; ?>
										</td>
										<td style="padding: 12px;"><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->start_time ) ) ); ?></td>
										<td style="padding: 12px;"><?php echo esc_html( $entry->description ); ?></td>
										<td style="padding: 12px; text-align: right; font-weight: 600;"><?php echo esc_html( sprintf( '%02dh %02dm', $dur_h, $dur_m ) ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<button type="submit" name="ndizi_approval_action" value="unapprove" class="button button-secondary" style="border-color: #ef4444 !important; color: #ef4444 !important;"><?php esc_html_e( 'Unapprove Selected Entries', 'ndizi-project-management' ); ?></button>
					<?php endif; ?>
				</form>
			</div>
		</div>
		<?php
	}
}
