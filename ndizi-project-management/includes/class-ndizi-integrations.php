<?php
/**
 * Invoice integrations and exports handler for Ndizi Project Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ndizi_Integrations {

	/**
	 * Initialize integrations and export hooks
	 */
	public static function init() {
		add_action( 'template_redirect', array( __CLASS__, 'handle_invoice_print_request' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_invoice_export_requests' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_report_export_requests' ) );
		add_action( 'post_submitbox_misc_actions', array( __CLASS__, 'add_export_buttons_to_editor' ) );
	}

	/**
	 * Add Export buttons to the Invoice editor publish box
	 */
	public static function add_export_buttons_to_editor( $post ) {
		if ( 'ndizi_invoice' !== $post->post_type ) {
			return;
		}

		$csv_url  = wp_nonce_url( admin_url( 'admin.php?ndizi_export_invoice=csv&invoice_id=' . $post->ID ), 'ndizi_export_nonce' );
		$json_url = wp_nonce_url( admin_url( 'admin.php?ndizi_export_invoice=json&invoice_id=' . $post->ID ), 'ndizi_export_nonce' );
		?>
		<div class="misc-pub-section ndizi-export-section" style="border-top: 1px solid #eee; padding-top: 10px; margin-top: 10px;">
			<strong><?php esc_html_e( 'Exports:', 'ndizi-project-management' ); ?></strong>
			<div style="margin-top: 5px;">
				<a href="<?php echo esc_url( $csv_url ); ?>" class="button button-secondary button-small"><?php esc_html_e( 'Export CSV', 'ndizi-project-management' ); ?></a>
				<a href="<?php echo esc_url( $json_url ); ?>" class="button button-secondary button-small"><?php esc_html_e( 'Export JSON', 'ndizi-project-management' ); ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * Intercept print invoice template request
	 */
	public static function handle_invoice_print_request() {
		// This is a public, bookmarkable print view authorized below by capability
		// or by the client auth token (hash_equals), not by a nonce.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['ndizi_print_invoice'] ) ) {
			return;
		}

		$invoice_id = intval( $_GET['ndizi_print_invoice'] );
		$invoice    = get_post( $invoice_id );

		if ( ! $invoice || 'ndizi_invoice' !== $invoice->post_type ) {
			wp_die( esc_html__( 'Invalid Invoice.', 'ndizi-project-management' ) );
		}

		// Perform authorization:
		// Either user has capabilities, or URL contains valid client auth key parameter
		$authorized = false;
		if ( current_user_can( 'ndizi_manage_invoices' ) || current_user_can( 'manage_options' ) ) {
			$authorized = true;
		} else {
			// Check key match
			$project_id    = get_post_meta( $invoice_id, '_ndizi_project_id', true );
			$client_id     = get_post_meta( $project_id, '_ndizi_client_id', true );
			$client_key    = get_post_meta( $client_id, '_ndizi_client_auth_key', true );
			$request_token = isset( $_GET['ndizi_token'] ) ? sanitize_text_field( wp_unslash( $_GET['ndizi_token'] ) ) : '';

			if ( ! empty( $client_key ) && hash_equals( $client_key, $request_token ) ) {
				$authorized = true;
			}
		}

		if ( ! $authorized ) {
			wp_die( esc_html__( 'You are not authorized to view this invoice.', 'ndizi-project-management' ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Pull related details
		$project_id = get_post_meta( $invoice_id, '_ndizi_project_id', true );
		$project    = get_post( $project_id );
		$client_id  = get_post_meta( $project_id, '_ndizi_client_id', true );
		$client     = get_post( $client_id );

		$invoice_date = get_post_meta( $invoice_id, '_ndizi_invoice_date', true );
		$due_date     = get_post_meta( $invoice_id, '_ndizi_invoice_due_date', true );
		$amount       = get_post_meta( $invoice_id, '_ndizi_invoice_amount', true );
		$status       = get_post_meta( $invoice_id, '_ndizi_invoice_status', true );

		// Fetch linked time entries
		global $wpdb;
		$table_name   = Ndizi_DB::get_table_name();
		$time_entries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE invoice_id = %d ORDER BY start_time ASC",
				$invoice_id
			)
		);

		// Calculate total hours
		$total_seconds = 0;
		foreach ( $time_entries as $entry ) {
			$total_seconds += $entry->duration;
		}
		$total_hours = round( $total_seconds / 3600, 2 );

		// Output invoice layout HTML
		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=device-width">
			<title><?php echo esc_html( $invoice->post_title ); ?></title>
			<link rel="preconnect" href="https://fonts.googleapis.com">
			<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
			<?php // phpcs:disable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Standalone invoice print template, not a WordPress theme page ?>
			<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
			<style>
				body {
					font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
					color: #1e293b;
					background: #f8fafc;
					margin: 0;
					padding: 40px 20px;
					line-height: 1.5;
					-webkit-print-color-adjust: exact;
					print-color-adjust: exact;
				}
				.invoice-wrapper {
					max-width: 800px;
					margin: 0 auto;
					background: #fff;
					padding: 45px;
					border-radius: 12px;
					box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.05), 0 2px 4px -2px rgb(0 0 0 / 0.05);
					border: 1px solid #e2e8f0;
				}
				.invoice-header {
					display: flex;
					justify-content: space-between;
					align-items: flex-start;
					margin-bottom: 40px;
					border-bottom: 1px solid #f1f5f9;
					padding-bottom: 30px;
				}
				.invoice-logo {
					font-size: 26px;
					font-weight: 800;
					color: #4f46e5;
					letter-spacing: -0.03em;
				}
				.invoice-title-col {
					text-align: right;
				}
				.invoice-title-col h1 {
					margin: 0 0 8px 0;
					font-size: 28px;
					font-weight: 800;
					color: #0f172a;
					letter-spacing: -0.02em;
				}
				.invoice-status-badge {
					display: inline-block;
					padding: 6px 12px;
					border-radius: 6px;
					font-size: 11px;
					font-weight: 700;
					text-transform: uppercase;
					letter-spacing: 0.05em;
					background: #e2e8f0;
					color: #475569;
				}
				.invoice-status-paid {
					background: #d1fae5;
					color: #065f46;
				}
				.invoice-status-sent {
					background: #fef3c7;
					color: #92400e;
				}
				.invoice-status-draft {
					background: #f1f5f9;
					color: #475569;
				}
				.invoice-status-overdue {
					background: #fee2e2;
					color: #991b1b;
				}

				.invoice-details-grid {
					display: grid;
					grid-template-columns: 1fr 1fr;
					gap: 40px;
					margin-bottom: 40px;
				}
				.details-col h3 {
					font-size: 11px;
					font-weight: 700;
					text-transform: uppercase;
					color: #94a3b8;
					margin: 0 0 12px 0;
					letter-spacing: 0.05em;
				}
				.details-col p {
					margin: 0 0 6px 0;
					font-size: 14px;
					color: #334155;
				}

				.invoice-table {
					width: 100%;
					border-collapse: collapse;
					margin-bottom: 40px;
				}
				.invoice-table th {
					background: #f8fafc;
					border-bottom: 2px solid #e2e8f0;
					padding: 12px 14px;
					text-align: left;
					font-size: 11px;
					font-weight: 700;
					text-transform: uppercase;
					color: #64748b;
					letter-spacing: 0.05em;
				}
				.invoice-table td {
					border-bottom: 1px solid #f1f5f9;
					padding: 14px;
					font-size: 14px;
					color: #334155;
				}
				.invoice-table tbody tr:nth-child(even) {
					background-color: #fafbfd;
				}
				.invoice-table tr:last-child td {
					border-bottom: 1px solid #cbd5e1;
				}

				.invoice-totals {
					display: flex;
					flex-direction: column;
					align-items: flex-end;
					gap: 8px;
					margin-bottom: 40px;
				}
				.totals-row {
					display: flex;
					width: 280px;
					justify-content: space-between;
					font-size: 14px;
					color: #64748b;
					box-sizing: border-box;
				}
				.totals-row strong {
					color: #334155;
				}
				.totals-row-grand {
					font-size: 20px;
					font-weight: 800;
					color: #4f46e5;
					border-top: 2px solid #4f46e5;
					background: #f5f3ff;
					padding: 12px 16px;
					border-radius: 8px;
					margin-top: 6px;
				}
				.totals-row-grand span {
					color: #4f46e5;
				}
				.totals-row-grand strong {
					color: #4f46e5;
				}

				.invoice-notes {
					font-size: 13px;
					color: #64748b;
					border-top: 1px solid #f1f5f9;
					padding-top: 30px;
					margin-bottom: 30px;
				}
				.invoice-notes strong {
					display: block;
					color: #475569;
					margin-bottom: 8px;
				}

				.invoice-footer {
					text-align: center;
					font-size: 13px;
					color: #94a3b8;
					border-top: 1px solid #f1f5f9;
					padding-top: 30px;
					margin-top: 30px;
				}

				.print-controls {
					background: #f8fafc;
					padding: 15px 24px;
					border-radius: 10px;
					margin-bottom: 30px;
					display: flex;
					justify-content: space-between;
					align-items: center;
					border: 1px solid #e2e8f0;
				}
				.print-controls span {
					font-size: 14px;
					color: #64748b;
					font-weight: 500;
				}
				.print-btn {
					background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%);
					color: #fff;
					padding: 10px 24px;
					border: none;
					border-radius: 8px;
					font-weight: 600;
					font-size: 14px;
					cursor: pointer;
					box-shadow: 0 2px 4px rgb(79 70 229 / 0.2);
					transition: all 0.2s;
				}
				.print-btn:hover {
					transform: translateY(-1px);
					box-shadow: 0 4px 6px rgb(79 70 229 / 0.3);
				}

				@page {
					size: letter;
					margin: 15mm 20mm;
				}

				@media print {
					body {
						padding: 0;
						background: #fff;
						color: #000;
					}
					.invoice-wrapper {
						border: none;
						box-shadow: none;
						padding: 0;
						max-width: 100%;
						margin: 0;
					}
					.print-controls {
						display: none;
					}
					tr {
						page-break-inside: avoid;
					}
					thead {
						display: table-header-group;
					}
					.invoice-totals, .invoice-notes, .invoice-footer {
						page-break-inside: avoid;
					}
				}
			</style>
			<?php // phpcs:enable WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet ?>
		</head>
		<body>
			<div class="invoice-wrapper">
				<!-- Print Toolbar -->
				<div class="print-controls">
					<span><?php esc_html_e( 'Print or save this invoice as a PDF file.', 'ndizi-project-management' ); ?></span>
					<button class="print-btn" onclick="window.print();"><?php esc_html_e( 'Print Invoice', 'ndizi-project-management' ); ?></button>
				</div>

				<header class="invoice-header">
					<div class="invoice-logo">
						<?php echo esc_html( get_bloginfo( 'name' ) ); ?>
					</div>
					<div class="invoice-title-col">
						<h1><?php echo esc_html( $invoice->post_title ); ?></h1>
						<span class="invoice-status-badge invoice-status-<?php echo esc_attr( $status ); ?>">
							<?php echo esc_html( $status ); ?>
						</span>
					</div>
				</header>

				<div class="invoice-details-grid">
					<div class="details-col">
						<h3><?php esc_html_e( 'Billed To', 'ndizi-project-management' ); ?></h3>
						<p><strong><?php echo esc_html( $client->post_title ); ?></strong></p>
						<?php if ( get_post_meta( $client_id, '_ndizi_client_address', true ) ) : ?>
							<p><?php echo nl2br( esc_html( get_post_meta( $client_id, '_ndizi_client_address', true ) ) ); ?></p>
						<?php endif; ?>
						<?php if ( get_post_meta( $client_id, '_ndizi_client_website', true ) ) : ?>
							<p><?php echo esc_html( get_post_meta( $client_id, '_ndizi_client_website', true ) ); ?></p>
						<?php endif; ?>
					</div>
					<div class="details-col" style="text-align: right;">
						<h3><?php esc_html_e( 'Invoice Details', 'ndizi-project-management' ); ?></h3>
						<p><strong><?php esc_html_e( 'Date:', 'ndizi-project-management' ); ?></strong> <?php echo esc_html( $invoice_date ); ?></p>
						<p><strong><?php esc_html_e( 'Due Date:', 'ndizi-project-management' ); ?></strong> <?php echo esc_html( $due_date ); ?></p>
						<p><strong><?php esc_html_e( 'Project:', 'ndizi-project-management' ); ?></strong> <?php echo esc_html( $project->post_title ); ?></p>
					</div>
				</div>

				<!-- Table of billable logs -->
				<table class="invoice-table">
					<thead>
						<tr>
							<th style="width: 12%;"><?php esc_html_e( 'Date', 'ndizi-project-management' ); ?></th>
							<th style="width: 18%;"><?php esc_html_e( 'Team Member', 'ndizi-project-management' ); ?></th>
							<th style="width: 40%;"><?php esc_html_e( 'Description', 'ndizi-project-management' ); ?></th>
							<th style="width: 10%; text-align: right;"><?php esc_html_e( 'Hours', 'ndizi-project-management' ); ?></th>
							<th style="width: 10%; text-align: right;"><?php esc_html_e( 'Rate', 'ndizi-project-management' ); ?></th>
							<th style="width: 10%; text-align: right;"><?php esc_html_e( 'Subtotal', 'ndizi-project-management' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $time_entries ) ) : ?>
							<tr>
								<td colspan="6" style="text-align: center; color: #64748b;">
									<em><?php esc_html_e( 'No detailed time entries linked. Showing summary amount only.', 'ndizi-project-management' ); ?></em>
								</td>
							</tr>
						<?php else : ?>
							<?php
							foreach ( $time_entries as $entry ) :
								$user = get_userdata( $entry->user_id );

								// Resolve billing rate hierarchically: Task Override -> User Billing Rate -> Project Default Rate
								$entry_rate = 0;
								if ( $entry->task_id ) {
									$entry_rate = get_post_meta( $entry->task_id, '_ndizi_task_hourly_rate', true );
								}
								if ( ! $entry_rate && $entry->user_id ) {
									$entry_rate = get_user_meta( $entry->user_id, '_ndizi_user_billing_rate', true );
								}
								if ( ! $entry_rate && $entry->project_id ) {
									$entry_rate = get_post_meta( $entry->project_id, '_ndizi_project_hourly_rate', true );
								}
								$entry_rate     = floatval( $entry_rate );
								$entry_subtotal = round( ( $entry->duration / 3600 ) * $entry_rate, 2 );
								?>
								<tr>
									<td><?php echo esc_html( gmdate( 'Y-m-d', strtotime( $entry->start_time ) ) ); ?></td>
									<td><?php echo $user ? esc_html( $user->display_name ) : '-'; ?></td>
									<td><?php echo esc_html( $entry->description ); ?></td>
									<td style="text-align: right;"><strong><?php echo esc_html( round( $entry->duration / 3600, 2 ) ); ?>h</strong></td>
									<td style="text-align: right;"><?php echo $entry_rate ? '$' . esc_html( number_format( $entry_rate, 2 ) ) : '-'; ?></td>
									<td style="text-align: right;"><strong><?php echo $entry_rate ? '$' . esc_html( number_format( $entry_subtotal, 2 ) ) : '-'; ?></strong></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<div class="invoice-totals">
					<div class="totals-row">
						<span><?php esc_html_e( 'Total Logged Hours:', 'ndizi-project-management' ); ?></span>
						<span><strong><?php echo esc_html( $total_hours ); ?>h</strong></span>
					</div>
					<div class="totals-row totals-row-grand">
						<span><?php esc_html_e( 'Total Due:', 'ndizi-project-management' ); ?></span>
						<span><strong>$<?php echo esc_html( number_format( $amount, 2 ) ); ?></strong></span>
					</div>
				</div>

				<?php if ( ! empty( $invoice->post_content ) ) : ?>
					<div class="invoice-notes">
						<strong><?php esc_html_e( 'Notes & Payment Terms:', 'ndizi-project-management' ); ?></strong>
						<?php echo wp_kses_post( wpautop( esc_html( $invoice->post_content ) ) ); ?>
					</div>
				<?php endif; ?>

				<footer class="invoice-footer">
					<p><?php esc_html_e( 'Thank you for your business!', 'ndizi-project-management' ); ?></p>
					<p style="font-size: 10px; color: #cbd5e1; margin-top: 10px;"><?php esc_html_e( 'Generated by Ndizi Project Management', 'ndizi-project-management' ); ?></p>
				</footer>
			</div>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * Handle admin invoice CSV/JSON exports
	 */
	public static function handle_invoice_export_requests() {
		if ( ! isset( $_GET['ndizi_export_invoice'] ) || ! isset( $_GET['invoice_id'] ) ) {
			return;
		}

		check_admin_referer( 'ndizi_export_nonce' );

		if ( ! current_user_can( 'ndizi_manage_invoices' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'ndizi-project-management' ) );
		}

		$invoice_id = intval( $_GET['invoice_id'] );
		$invoice    = get_post( $invoice_id );

		if ( ! $invoice || 'ndizi_invoice' !== $invoice->post_type ) {
			wp_die( esc_html__( 'Invalid Invoice.', 'ndizi-project-management' ) );
		}

		$format = sanitize_text_field( wp_unslash( $_GET['ndizi_export_invoice'] ) );

		// Compile export array structure
		$project_id = get_post_meta( $invoice_id, '_ndizi_project_id', true );
		$project    = get_post( $project_id );
		$client_id  = get_post_meta( $project_id, '_ndizi_client_id', true );
		$client     = get_post( $client_id );

		$invoice_date = get_post_meta( $invoice_id, '_ndizi_invoice_date', true );
		$due_date     = get_post_meta( $invoice_id, '_ndizi_invoice_due_date', true );
		$amount       = get_post_meta( $invoice_id, '_ndizi_invoice_amount', true );
		$status       = get_post_meta( $invoice_id, '_ndizi_invoice_status', true );

		// Load logs
		global $wpdb;
		$table_name   = Ndizi_DB::get_table_name();
		$time_entries = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $table_name WHERE invoice_id = %d ORDER BY start_time ASC",
				$invoice_id
			)
		);

		$export_data = array(
			'invoice_id'     => $invoice_id,
			'invoice_number' => $invoice->post_title,
			'project_name'   => $project ? $project->post_title : '',
			'client_name'    => $client ? $client->post_title : '',
			'invoice_date'   => $invoice_date,
			'due_date'       => $due_date,
			'amount'         => $amount,
			'status'         => $status,
			'line_items'     => array(),
		);

		foreach ( $time_entries as $entry ) {
			$user                        = get_userdata( $entry->user_id );
			$export_data['line_items'][] = array(
				'date'        => gmdate( 'Y-m-d', strtotime( $entry->start_time ) ),
				'team_member' => $user ? $user->display_name : '',
				'description' => $entry->description,
				'hours'       => round( $entry->duration / 3600, 2 ),
			);
		}

		// Allow third party plugins to customize invoice dataset exports
		$export_data = apply_filters( 'ndizi_export_invoice_data', $export_data, $invoice_id );

		if ( 'json' === $format ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . sanitize_title( $invoice->post_title ) . '_export.json"' );
			echo wp_json_encode( $export_data, JSON_PRETTY_PRINT );
			exit;
		} elseif ( 'csv' === $format ) {
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="' . sanitize_title( $invoice->post_title ) . '_export.csv"' );

			$output = fopen( 'php://output', 'w' );

			// Write invoice metadata headers. User-controlled cells are run through
			// self::escape_csv_field() to neutralize spreadsheet formula injection.
			fputcsv( $output, array( 'Invoice Export Summary' ) );
			fputcsv( $output, array( 'Invoice #', self::escape_csv_field( $export_data['invoice_number'] ) ) );
			fputcsv( $output, array( 'Client', self::escape_csv_field( $export_data['client_name'] ) ) );
			fputcsv( $output, array( 'Project', self::escape_csv_field( $export_data['project_name'] ) ) );
			fputcsv( $output, array( 'Invoice Date', self::escape_csv_field( $export_data['invoice_date'] ) ) );
			fputcsv( $output, array( 'Due Date', self::escape_csv_field( $export_data['due_date'] ) ) );
			fputcsv( $output, array( 'Total Amount', '$' . number_format( $export_data['amount'], 2 ) ) );
			fputcsv( $output, array( 'Status', self::escape_csv_field( $export_data['status'] ) ) );
			fputcsv( $output, array() ); // Empty spacer line

			// Write detailed time log line items
			fputcsv( $output, array( 'Line Items' ) );
			fputcsv( $output, array( 'Date', 'Team Member', 'Description', 'Hours' ) );

			foreach ( $export_data['line_items'] as $item ) {
				fputcsv(
					$output,
					array(
						self::escape_csv_field( $item['date'] ),
						self::escape_csv_field( $item['team_member'] ),
						self::escape_csv_field( $item['description'] ),
						self::escape_csv_field( $item['hours'] ),
					)
				);
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
			fclose( $output );
			exit;
		}

		wp_die( esc_html__( 'Invalid export format.', 'ndizi-project-management' ) );
	}

	/**
	 * Handle admin filtered time report CSV exports
	 */
	public static function handle_report_export_requests() {
		if ( ! isset( $_GET['ndizi_export_report'] ) || 'csv' !== $_GET['ndizi_export_report'] ) {
			return;
		}

		check_admin_referer( 'ndizi_export_report_nonce' );

		if ( ! current_user_can( 'ndizi_view_reports' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'ndizi-project-management' ) );
		}

		$project_id = isset( $_GET['project_id'] ) ? intval( $_GET['project_id'] ) : 0;
		$user_id    = isset( $_GET['user_id'] ) ? intval( $_GET['user_id'] ) : 0;
		$start_date = isset( $_GET['start_date'] ) ? sanitize_text_field( wp_unslash( $_GET['start_date'] ) ) : '';
		$end_date   = isset( $_GET['end_date'] ) ? sanitize_text_field( wp_unslash( $_GET['end_date'] ) ) : '';

		// Query time entries matching filters
		$time_entries = Ndizi_DB::get_time_entries(
			array(
				'project_id' => $project_id ? $project_id : null,
				'user_id'    => $user_id ? $user_id : null,
				'start_date' => $start_date,
				'end_date'   => $end_date,
				'number'     => -1,
			)
		);

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="ndizi_time_report_' . gmdate( 'Y-m-d' ) . '.csv"' );

		$output = fopen( 'php://output', 'w' );

		// Write CSV headers. User-controlled cells are run through self::escape_csv_field()
		fputcsv( $output, array( 'Ndizi Time Report Export' ) );
		if ( $project_id ) {
			$proj = get_post( $project_id );
			fputcsv( $output, array( 'Project Filter', $proj ? self::escape_csv_field( $proj->post_title ) : '' ) );
		}
		if ( $user_id ) {
			$usr = get_userdata( $user_id );
			fputcsv( $output, array( 'Team Member Filter', $usr ? self::escape_csv_field( $usr->display_name ) : '' ) );
		}
		if ( $start_date ) {
			fputcsv( $output, array( 'Start Date', self::escape_csv_field( $start_date ) ) );
		}
		if ( $end_date ) {
			fputcsv( $output, array( 'End Date', self::escape_csv_field( $end_date ) ) );
		}
		fputcsv( $output, array() ); // Spacer row

		fputcsv(
			$output,
			array(
				'Date',
				'Project',
				'Task',
				'Team Member',
				'Description',
				'Hours',
				'Billable',
				'Billing Rate',
				'Revenue',
				'Salary Rate',
				'Internal Cost',
				'Margin',
			)
		);

		foreach ( $time_entries as $entry ) {
			$user = get_userdata( $entry->user_id );
			$proj = get_post( $entry->project_id );
			$task = $entry->task_id ? get_post( $entry->task_id ) : null;

			// Resolve billing rate
			$billing_rate = 0;
			if ( $entry->task_id ) {
				$billing_rate = get_post_meta( $entry->task_id, '_ndizi_task_hourly_rate', true );
			}
			if ( ! $billing_rate && $entry->user_id ) {
				$billing_rate = get_user_meta( $entry->user_id, '_ndizi_user_billing_rate', true );
			}
			if ( ! $billing_rate && $entry->project_id ) {
				$billing_rate = get_post_meta( $entry->project_id, '_ndizi_project_hourly_rate', true );
			}
			$billing_rate  = floatval( $billing_rate );
			$hours         = $entry->duration / 3600;
			$entry_revenue = $entry->billable ? ( $hours * $billing_rate ) : 0;

			// Resolve salary rate
			$salary_rate = 0;
			if ( $entry->user_id ) {
				$salary_rate = get_user_meta( $entry->user_id, '_ndizi_user_salary_rate', true );
			}
			$salary_rate = floatval( $salary_rate );
			$entry_cost  = $hours * $salary_rate;
			$margin      = $entry_revenue - $entry_cost;

			fputcsv(
				$output,
				array(
					self::escape_csv_field( gmdate( 'Y-m-d', strtotime( $entry->start_time ) ) ),
					self::escape_csv_field( $proj ? $proj->post_title : '' ),
					self::escape_csv_field( $task ? $task->post_title : '' ),
					self::escape_csv_field( $user ? $user->display_name : '' ),
					self::escape_csv_field( $entry->description ),
					self::escape_csv_field( round( $hours, 2 ) ),
					self::escape_csv_field( $entry->billable ? 'Yes' : 'No' ),
					self::escape_csv_field( '$' . number_format( $billing_rate, 2 ) ),
					self::escape_csv_field( '$' . number_format( $entry_revenue, 2 ) ),
					self::escape_csv_field( '$' . number_format( $salary_rate, 2 ) ),
					self::escape_csv_field( '$' . number_format( $entry_cost, 2 ) ),
					self::escape_csv_field( '$' . number_format( $margin, 2 ) ),
				)
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
		fclose( $output );
		exit;
	}

	/**
	 * Neutralize CSV/spreadsheet formula injection for a single field.
	 *
	 * Spreadsheet applications treat cells beginning with =, +, -, @, or a
	 * leading tab / carriage return as formulas. Prefixing such values with a
	 * single quote forces them to be treated as plain text.
	 *
	 * @param mixed $value The cell value to sanitize.
	 * @return string The safe cell value.
	 */
	private static function escape_csv_field( $value ) {
		$value = (string) $value;

		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			$value = "'" . $value;
		}

		return $value;
	}
}
