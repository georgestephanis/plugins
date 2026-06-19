<?php
/**
 * Template for the Ndizi standalone time-tracker PWA page.
 *
 * Expected variables (set by Ndizi_Standalone_Tracker::render_standalone_page()):
 *   $active_timer  object|null  Active timer row from Ndizi_DB, or null.
 *   $duration_sec  int          Seconds elapsed for the active timer (0 if none).
 *   $ticker_text   string       Pre-formatted HH:MM:SS string for the initial clock display.
 *   $prefilled_desc string      URL-param description pre-fill (sanitized).
 *
 * @package Ndizi_Project_Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Assets are registered/enqueued by Ndizi_Standalone_Tracker::enqueue_standalone_assets()
// and printed below via wp_print_styles()/wp_print_scripts(), since this standalone
// document does not run wp_head()/wp_footer().
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?php esc_html_e( 'Ndizi Time Tracker', 'ndizi-project-management' ); ?></title>
	<link rel="manifest" href="admin.php?ndizi-action=manifest">
	<?php if ( Ndizi_Project_Management::google_fonts_enabled() ) : ?>
		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<?php endif; ?>
	<?php
	$ndizi_standalone_styles = array( 'ndizi-standalone' );
	if ( Ndizi_Project_Management::google_fonts_enabled() ) {
		$ndizi_standalone_styles[] = 'ndizi-standalone-fonts';
	}
	wp_print_styles( $ndizi_standalone_styles );
	?>
</head>
<body class="<?php echo esc_attr( $active_timer ? 'timer-running' : '' ); ?>">

	<div class="app-container">

		<!-- PWA Install Prompt Banner -->
		<div class="install-banner" id="pwa-install-banner">
			<div class="install-text"><?php esc_html_e( 'Install Ndizi Time Tracker app for distraction-free access', 'ndizi-project-management' ); ?></div>
			<button class="install-btn" id="pwa-install-btn"><?php esc_html_e( 'Install', 'ndizi-project-management' ); ?></button>
		</div>

		<div class="glass-card">
			<!-- Header -->
			<div class="app-header">
				<div class="app-brand">
					<svg xmlns="http://www.w3.org/2000/svg" class="app-logo" viewBox="0 0 100 100">
						<g transform="rotate(-38 50 50)">
							<path d="M14 56 Q50 96 86 48 Q50 72 14 56 Z" fill="#F4B223"/>
							<circle cx="14" cy="56" r="3.6" fill="#FBE6A8"/>
							<circle cx="86" cy="48" r="3.6" fill="#FBE6A8"/>
						</g>
					</svg>
					<div class="app-title"><?php esc_html_e( 'Ndizi PM', 'ndizi-project-management' ); ?></div>
				</div>
				<div class="app-status"><?php esc_html_e( 'App Window', 'ndizi-project-management' ); ?></div>
			</div>
		</div>

		<!-- Running Tracker Panel -->
		<div class="glass-card active-timer-section" id="active-timer-view" style="<?php echo esc_attr( $active_timer ? '' : 'display: none;' ); ?>">
			<div class="active-panel">
				<div class="active-header">
					<div class="active-meta">
						<span class="badge badge-project" id="lbl-active-project">
							<?php echo $active_timer ? esc_html( get_the_title( $active_timer->project_id ) ) : ''; ?>
						</span>
						<span class="badge badge-task" id="lbl-active-task" style="<?php echo ( $active_timer && $active_timer->task_id ) ? '' : 'display: none;'; ?>">
							<?php echo ( $active_timer && $active_timer->task_id ) ? esc_html( get_the_title( $active_timer->task_id ) ) : ''; ?>
						</span>
					</div>
					<div class="active-desc" id="lbl-active-desc">
						<?php echo $active_timer ? esc_html( $active_timer->description ) : ''; ?>
					</div>
				</div>

				<div class="ticker-clock" id="timer-clock-display"><?php echo esc_html( $ticker_text ); ?></div>

				<button type="button" class="btn btn-danger" id="btn-stop-timer">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
					<?php esc_html_e( 'Stop Tracker', 'ndizi-project-management' ); ?>
				</button>
			</div>
		</div>

		<!-- Inactive Log Form Panel -->
		<div class="glass-card new-timer-section" id="new-timer-view" style="<?php echo esc_attr( $active_timer ? 'display: none;' : '' ); ?>">

			<!-- Tabs -->
			<div class="mode-tabs">
				<button type="button" class="tab-btn active" id="tab-timer" data-mode="timer">
					<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
					<?php esc_html_e( 'Timer', 'ndizi-project-management' ); ?>
				</button>
				<button type="button" class="tab-btn" id="tab-manual" data-mode="manual">
					<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 1 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
					<?php esc_html_e( 'Manual', 'ndizi-project-management' ); ?>
				</button>
			</div>

			<!-- Form Inputs -->
			<div class="form-group" style="margin-top: 14px;">
				<label class="form-label"><?php esc_html_e( 'Project', 'ndizi-project-management' ); ?></label>
				<select id="project-select" class="form-select">
					<option value=""><?php esc_html_e( 'Loading projects...', 'ndizi-project-management' ); ?></option>
				</select>
			</div>

			<div class="form-group" id="task-select-group" style="display: none;">
				<label class="form-label"><?php esc_html_e( 'Task', 'ndizi-project-management' ); ?></label>
				<select id="task-select" class="form-select">
					<option value="0"><?php esc_html_e( '-- General --', 'ndizi-project-management' ); ?></option>
				</select>
			</div>

			<div class="form-group">
				<label class="form-label"><?php esc_html_e( 'Activity Description', 'ndizi-project-management' ); ?></label>
				<input type="text" id="desc-input" class="form-input" placeholder="<?php esc_attr_e( 'What are you working on?', 'ndizi-project-management' ); ?>" value="<?php echo esc_attr( $prefilled_desc ); ?>" maxlength="255">
			</div>

			<div class="toggle-row">
				<span class="toggle-label-text"><?php esc_html_e( 'Billable Time', 'ndizi-project-management' ); ?></span>
				<label class="switch">
					<input type="checkbox" id="billable-check" value="1" checked>
					<span class="slider"></span>
				</label>
			</div>

			<!-- Timer Logger Mode Action -->
			<div id="panel-timer-mode">
				<button type="button" class="btn btn-primary" id="btn-start-timer" disabled>
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
					<?php esc_html_e( 'Start Timer', 'ndizi-project-management' ); ?>
				</button>
			</div>

			<!-- Manual Logger Mode Actions -->
			<div id="panel-manual-mode" class="manual-panel">
				<div class="duration-inputs">
					<div class="duration-col">
						<input type="number" id="manual-hours" min="0" placeholder="0">
						<span><?php esc_html_e( 'Hours', 'ndizi-project-management' ); ?></span>
					</div>
					<div class="duration-sep">:</div>
					<div class="duration-col">
						<input type="number" id="manual-minutes" min="0" max="59" placeholder="00">
						<span><?php esc_html_e( 'Min', 'ndizi-project-management' ); ?></span>
					</div>
				</div>
				<div class="manual-date-section">
					<input type="date" id="manual-date" class="manual-date-input" disabled>
					<button type="button" class="manual-date-change-btn" id="manual-date-change-btn"><?php esc_html_e( 'Change date', 'ndizi-project-management' ); ?></button>
				</div>
				<button type="button" class="btn btn-primary" style="margin-top: 8px;" id="btn-save-manual">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M9 16.17 4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
					<?php esc_html_e( 'Log Manual Entry', 'ndizi-project-management' ); ?>
				</button>
			</div>

		</div>

		<!-- Today's Logged List -->
		<div class="glass-card recent-section">
			<div class="section-title-row">
				<div class="section-title"><?php esc_html_e( 'Logged Today', 'ndizi-project-management' ); ?></div>
			</div>
			<div class="recent-list" id="recent-logs-list">
				<div class="empty-logs"><?php esc_html_e( 'No entries recorded today.', 'ndizi-project-management' ); ?></div>
			</div>
		</div>

		<!-- Footer branding -->
		<div class="app-footer">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=ndizi-pm' ) ); ?>" class="footer-link">
				&larr; <?php esc_html_e( 'Back to WordPress Dashboard', 'ndizi-project-management' ); ?>
			</a>
		</div>

	</div>

	<?php
	// Prints jQuery (a dependency), the wp_localize_script() ndizi_standalone
	// config object, and build/standalone.js — all registered/enqueued in
	// Ndizi_Standalone_Tracker::enqueue_standalone_assets().
	wp_print_scripts( array( 'ndizi-standalone' ) );
	?>
</body>
</html>
