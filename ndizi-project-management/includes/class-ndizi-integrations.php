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
			<strong><?php _e( 'Exports:', 'ndizi' ); ?></strong>
			<div style="margin-top: 5px;">
				<a href="<?php echo esc_url( $csv_url ); ?>" class="button button-secondary button-small"><?php _e( 'Export CSV', 'ndizi' ); ?></a>
				<a href="<?php echo esc_url( $json_url ); ?>" class="button button-secondary button-small"><?php _e( 'Export JSON', 'ndizi' ); ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * Intercept print invoice template request
	 */
	public static function handle_invoice_print_request() {
		if ( ! isset( $_GET['ndizi_print_invoice'] ) ) {
			return;
		}

		$invoice_id = intval( $_GET['ndizi_print_invoice'] );
		$invoice    = get_post( $invoice_id );

		if ( ! $invoice || 'ndizi_invoice' !== $invoice->post_type ) {
			wp_die( __( 'Invalid Invoice.', 'ndizi' ) );
		}

		// Perform authorization:
		// Either user has capabilities, or URL contains valid client auth key parameter
		$authorized = false;
		if ( current_user_can( 'ndizi_manage_invoices' ) || current_user_can( 'administrator' ) ) {
			$authorized = true;
		} else {
			// Check key match
			$project_id    = get_post_meta( $invoice_id, '_ndizi_project_id', true );
			$client_id     = get_post_meta( $project_id, '_ndizi_client_id', true );
			$client_key    = get_post_meta( $client_id, '_ndizi_client_auth_key', true );
			$request_token = isset( $_GET['ndizi_token'] ) ? sanitize_text_field( $_GET['ndizi_token'] ) : '';

			if ( ! empty( $client_key ) && hash_equals( $client_key, $request_token ) ) {
				$authorized = true;
			}
		}

		if ( ! $authorized ) {
			wp_die( __( 'You are not authorized to view this invoice.', 'ndizi' ) );
		}

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
			<title><?php echo esc_html( $invoice->post_title ); ?></title>
			<style>
				body {
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
					color: #1e293b;
					background: #fff;
					margin: 0;
					padding: 40px;
					line-height: 1.5;
				}
				.invoice-wrapper {
					max-width: 800px;
					margin: 0 auto;
				}
				.invoice-header {
					display: flex;
					justify-content: space-between;
					margin-bottom: 40px;
				}
				.invoice-logo {
					font-size: 28px;
					font-weight: 800;
					color: #4f46e5;
				}
				.invoice-title-col {
					text-align: right;
				}
				.invoice-title-col h1 {
					margin: 0 0 5px 0;
					font-size: 32px;
					font-weight: 900;
					color: #0f172a;
				}
				.invoice-status-badge {
					display: inline-block;
					padding: 5px 12px;
					border-radius: 4px;
					font-size: 11px;
					font-weight: 700;
					text-transform: uppercase;
					letter-spacing: 0.05em;
					background: #f1f5f9;
				}
				.invoice-status-paid {
					background: #d1fae5;
					color: #065f46;
				}
				.invoice-status-sent {
					background: #fef3c7;
					color: #92400e;
				}

				.invoice-details-grid {
					display: grid;
					grid-template-columns: 1fr 1fr;
					gap: 40px;
					margin-bottom: 45px;
					border-bottom: 1px solid #e2e8f0;
					padding-bottom: 30px;
				}
				.details-col h3 {
					font-size: 12px;
					text-transform: uppercase;
					color: #64748b;
					margin-bottom: 10px;
					letter-spacing: 0.05em;
				}
				.details-col p {
					margin: 0 0 5px 0;
					font-size: 14px;
				}

				.invoice-table {
					width: 100%;
					border-collapse: collapse;
					margin-bottom: 40px;
				}
				.invoice-table th {
					background: #f8fafc;
					border-bottom: 2px solid #cbd5e1;
					padding: 12px;
					text-align: left;
					font-size: 11px;
					text-transform: uppercase;
					color: #64748b;
				}
				.invoice-table td {
					border-bottom: 1px solid #e2e8f0;
					padding: 14px 12px;
					font-size: 14px;
				}
				.invoice-table tr:last-child td {
					border-bottom: 1px solid #cbd5e1;
				}

				.invoice-totals {
					display: flex;
					flex-direction: column;
					align-items: flex-end;
					gap: 10px;
					margin-bottom: 50px;
				}
				.totals-row {
					display: flex;
					width: 250px;
					justify-content: space-between;
					font-size: 14px;
				}
				.totals-row-grand {
					font-size: 20px;
					font-weight: 800;
					color: #0f172a;
					border-top: 2px solid #4f46e5;
					padding-top: 10px;
				}

				.invoice-notes {
					font-size: 12px;
					color: #64748b;
					border-top: 1px solid #e2e8f0;
					padding-top: 30px;
				}

				.print-controls {
					background: #f8fafc;
					padding: 15px;
					border-radius: 8px;
					margin-bottom: 30px;
					display: flex;
					justify-content: space-between;
					align-items: center;
					border: 1px solid #e2e8f0;
				}
				.print-btn {
					background: #4f46e5;
					color: #fff;
					padding: 10px 20px;
					border: none;
					border-radius: 6px;
					font-weight: 700;
					cursor: pointer;
				}
				.print-btn:hover {
					background: #4338ca;
				}

				@media print {
					body { padding: 0; }
					.print-controls { display: none; }
				}
			</style>
		</head>
		<body>
			<div class="invoice-wrapper">
				<!-- Print Toolbar -->
				<div class="print-controls">
					<span><?php _e( 'Print or save this invoice as a PDF file.', 'ndizi' ); ?></span>
					<button class="print-btn" onclick="window.print();"><?php _e( 'Print Invoice', 'ndizi' ); ?></button>
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
						<h3><?php _e( 'Billed To', 'ndizi' ); ?></h3>
						<p><strong><?php echo esc_html( $client->post_title ); ?></strong></p>
						<?php if ( get_post_meta( $client_id, '_ndizi_client_address', true ) ) : ?>
							<p><?php echo nl2br( esc_html( get_post_meta( $client_id, '_ndizi_client_address', true ) ) ); ?></p>
						<?php endif; ?>
						<?php if ( get_post_meta( $client_id, '_ndizi_client_website', true ) ) : ?>
							<p><?php echo esc_html( get_post_meta( $client_id, '_ndizi_client_website', true ) ); ?></p>
						<?php endif; ?>
					</div>
					<div class="details-col" style="text-align: right;">
						<h3><?php _e( 'Invoice Details', 'ndizi' ); ?></h3>
						<p><strong><?php _e( 'Date:', 'ndizi' ); ?></strong> <?php echo esc_html( $invoice_date ); ?></p>
						<p><strong><?php _e( 'Due Date:', 'ndizi' ); ?></strong> <?php echo esc_html( $due_date ); ?></p>
						<p><strong><?php _e( 'Project:', 'ndizi' ); ?></strong> <?php echo esc_html( $project->post_title ); ?></p>
					</div>
				</div>

				<!-- Table of billable logs -->
				<table class="invoice-table">
					<thead>
						<tr>
							<th style="width: 15%;"><?php _e( 'Date', 'ndizi' ); ?></th>
							<th style="width: 20%;"><?php _e( 'Team Member', 'ndizi' ); ?></th>
							<th style="width: 50%;"><?php _e( 'Description', 'ndizi' ); ?></th>
							<th style="width: 15%; text-align: right;"><?php _e( 'Hours', 'ndizi' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $time_entries ) ) : ?>
							<tr>
								<td colspan="4" style="text-align: center; color: #64748b;">
									<em><?php _e( 'No detailed time entries linked. Showing summary amount only.', 'ndizi' ); ?></em>
								</td>
							</tr>
						<?php else : ?>
							<?php
							foreach ( $time_entries as $entry ) :
								$user = get_userdata( $entry->user_id );
								?>
								<tr>
									<td><?php echo esc_html( gmdate( 'Y-m-d', strtotime( $entry->start_time ) ) ); ?></td>
									<td><?php echo $user ? esc_html( $user->display_name ) : '-'; ?></td>
									<td><?php echo esc_html( $entry->description ); ?></td>
									<td style="text-align: right;"><strong><?php echo esc_html( round( $entry->duration / 3600, 2 ) ); ?>h</strong></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<div class="invoice-totals">
					<div class="totals-row">
						<span><?php _e( 'Total Logged Hours:', 'ndizi' ); ?></span>
						<span><strong><?php echo esc_html( $total_hours ); ?>h</strong></span>
					</div>
					<div class="totals-row totals-row-grand">
						<span><?php _e( 'Total Due:', 'ndizi' ); ?></span>
						<span>$<?php echo esc_html( number_format( $amount, 2 ) ); ?></span>
					</div>
				</div>

				<?php if ( ! empty( $invoice->post_content ) ) : ?>
					<div class="invoice-notes">
						<strong><?php _e( 'Notes & Payment Terms:', 'ndizi' ); ?></strong>
						<?php echo wpautop( esc_html( $invoice->post_content ) ); ?>
					</div>
				<?php endif; ?>
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
			wp_die( __( 'Insufficient permissions.', 'ndizi' ) );
		}

		$invoice_id = intval( $_GET['invoice_id'] );
		$invoice    = get_post( $invoice_id );

		if ( ! $invoice || 'ndizi_invoice' !== $invoice->post_type ) {
			wp_die( __( 'Invalid Invoice.', 'ndizi' ) );
		}

		$format = sanitize_text_field( $_GET['ndizi_export_invoice'] );

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

			// Write invoice metadata headers
			fputcsv( $output, array( 'Invoice Export Summary' ) );
			fputcsv( $output, array( 'Invoice #', $export_data['invoice_number'] ) );
			fputcsv( $output, array( 'Client', $export_data['client_name'] ) );
			fputcsv( $output, array( 'Project', $export_data['project_name'] ) );
			fputcsv( $output, array( 'Invoice Date', $export_data['invoice_date'] ) );
			fputcsv( $output, array( 'Due Date', $export_data['due_date'] ) );
			fputcsv( $output, array( 'Total Amount', '$' . number_format( $export_data['amount'], 2 ) ) );
			fputcsv( $output, array( 'Status', $export_data['status'] ) );
			fputcsv( $output, array() ); // Empty spacer line

			// Write detailed time log line items
			fputcsv( $output, array( 'Line Items' ) );
			fputcsv( $output, array( 'Date', 'Team Member', 'Description', 'Hours' ) );

			foreach ( $export_data['line_items'] as $item ) {
				fputcsv(
					$output,
					array(
						$item['date'],
						$item['team_member'],
						$item['description'],
						$item['hours'],
					)
				);
			}

			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
			fclose( $output );
			exit;
		}

		wp_die( __( 'Invalid export format.', 'ndizi' ) );
	}
}
