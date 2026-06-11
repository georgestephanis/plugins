<?php
/**
 * Frontend Client Portal handler for Ndizi Project Management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ndizi_Portal {

	/**
	 * Initialize portal hooks
	 */
	public static function init() {
		add_shortcode( 'ndizi_client_portal', array( __CLASS__, 'render_portal_shortcode' ) );
		self::handle_portal_actions();
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_ndizi_load_task_discussion', array( __CLASS__, 'ajax_load_task_discussion' ) );
		add_action( 'wp_ajax_nopriv_ndizi_load_task_discussion', array( __CLASS__, 'ajax_load_task_discussion' ) );
	}

	/**
	 * Enqueue frontend CSS and JS
	 */
	public static function enqueue_assets() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'ndizi_client_portal' ) ) {
			wp_enqueue_style( 'ndizi-portal-style', NDIZI_PLUGIN_URL . 'build/portal.css', array(), NDIZI_VERSION );
			wp_enqueue_script( 'ndizi-portal-script', NDIZI_PLUGIN_URL . 'build/portal.js', array( 'jquery' ), NDIZI_VERSION, true );

			wp_localize_script(
				'ndizi-portal-script',
				'ndizi_portal',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
				)
			);
		}
	}

	/**
	 * Retrieve client ID associated with active auth session
	 */
	public static function get_authenticated_client_id() {
		// 1. Check URL token (highest priority, sets cookie)
		if ( isset( $_GET['ndizi_token'] ) ) {
			$token     = sanitize_text_field( $_GET['ndizi_token'] );
			$client_id = self::get_client_id_by_token( $token );
			if ( $client_id ) {
				return $client_id;
			}
		}

		// 2. Check cookie token
		if ( isset( $_COOKIE['ndizi_client_token'] ) ) {
			$token = sanitize_text_field( $_COOKIE['ndizi_client_token'] );
			return self::get_client_id_by_token( $token );
		}

		// 3. Fallback: Check if current WP user has a client linked (using meta)
		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
			$clients = get_posts(
				array(
					'post_type'      => 'ndizi_client',
					'posts_per_page' => 1,
					'meta_query'     => array(
						array(
							'key'   => '_ndizi_client_wp_user_id',
							'value' => $user_id,
						),
					),
				)
			);
			if ( ! empty( $clients ) ) {
				return $clients[0]->ID;
			}
		}

		return false;
	}

	/**
	 * Helper: Query client by auth key
	 */
	private static function get_client_id_by_token( $token ) {
		if ( empty( $token ) ) {
			return false;
		}

		$clients = get_posts(
			array(
				'post_type'      => 'ndizi_client',
				'posts_per_page' => 1,
				'meta_query'     => array(
					array(
						'key'   => '_ndizi_client_auth_key',
						'value' => $token,
					),
				),
			)
		);

		return ! empty( $clients ) ? $clients[0]->ID : false;
	}

	/**
	 * Handle Portal Actions (Login, Submit Task, Submit Message/Comment, Uploads)
	 */
	public static function handle_portal_actions() {
		// 1. Token validation from URL
		if ( isset( $_GET['ndizi_token'] ) ) {
			$token     = sanitize_text_field( $_GET['ndizi_token'] );
			$client_id = self::get_client_id_by_token( $token );
			if ( $client_id ) {
				// Set cookie for 30 days
				setcookie( 'ndizi_client_token', $token, time() + ( 30 * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN, is_ssl() );
				// Clean URL parameters
				$redirect_url = remove_query_arg( 'ndizi_token' );
				wp_safe_redirect( $redirect_url );
				exit;
			}
		}

		// 2. Manual Key Login Submit
		if ( isset( $_POST['ndizi_portal_login'] ) && isset( $_POST['ndizi_portal_key'] ) ) {
			$token     = sanitize_text_field( $_POST['ndizi_portal_key'] );
			$client_id = self::get_client_id_by_token( $token );
			if ( $client_id ) {
				setcookie( 'ndizi_client_token', $token, time() + ( 30 * DAY_IN_SECONDS ), COOKIEPATH, COOKIE_DOMAIN, is_ssl() );
				wp_safe_redirect( get_permalink() );
				exit;
			} else {
				wp_safe_redirect( add_query_arg( 'ndizi_auth_error', '1', get_permalink() ) );
				exit;
			}
		}

		// 3. Logout
		if ( isset( $_GET['ndizi_logout'] ) ) {
			setcookie( 'ndizi_client_token', '', time() - YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl() );
			wp_safe_redirect( remove_query_arg( 'ndizi_logout', get_permalink() ) );
			exit;
		}

		// 4. Verify client context for writes
		$client_id = self::get_authenticated_client_id();
		if ( ! $client_id ) {
			return;
		}

		// 5. Submit new Task
		if ( isset( $_POST['ndizi_submit_task_portal'] ) && isset( $_POST['ndizi_task_project_id'] ) ) {
			check_admin_referer( 'ndizi_portal_submit_task', '_wpnonce' );

			$project_id = intval( $_POST['ndizi_task_project_id'] );

			// Double-check project ownership
			$project_client = get_post_meta( $project_id, '_ndizi_client_id', true );
			if ( intval( $project_client ) !== $client_id ) {
				wp_die( __( 'Unauthorized project selection.', 'ndizi' ) );
			}

			$title   = sanitize_text_field( $_POST['ndizi_task_title'] );
			$details = sanitize_textarea_field( $_POST['ndizi_task_details'] );

			if ( ! empty( $title ) ) {
				$task_id = wp_insert_post(
					array(
						'post_title'   => $title,
						'post_content' => $details,
						'post_status'  => 'publish',
						'post_type'    => 'ndizi_task',
					)
				);

				if ( $task_id ) {
					update_post_meta( $task_id, '_ndizi_project_id', $project_id );
					update_post_meta( $task_id, '_ndizi_task_status', 'open' );
					update_post_meta( $task_id, '_ndizi_task_priority', 'medium' );
					update_post_meta( $task_id, '_ndizi_assigned_user_id', 0 ); // Unassigned initially

					// Trigger email notifications
					do_action( 'ndizi_client_submitted_task', $task_id, $project_id, $client_id );

					wp_safe_redirect( add_query_arg( 'ndizi_task_success', '1', get_permalink() ) );
					exit;
				}
			}
		}

		// 6. Submit a discussion Message (Comment)
		if ( isset( $_POST['ndizi_submit_portal_comment'] ) && isset( $_POST['ndizi_comment_post_id'] ) ) {
			check_admin_referer( 'ndizi_portal_submit_comment', '_wpnonce' );

			$post_id = intval( $_POST['ndizi_comment_post_id'] );
			$content = sanitize_textarea_field( $_POST['ndizi_comment_content'] );

			// Validate project context
			$post_type = get_post_type( $post_id );
			if ( 'ndizi_project' === $post_type ) {
				$project_client = get_post_meta( $post_id, '_ndizi_client_id', true );
				if ( intval( $project_client ) !== $client_id ) {
					wp_die( __( 'Unauthorized access.', 'ndizi' ) );
				}
			} elseif ( 'ndizi_task' === $post_type ) {
				$project_id     = get_post_meta( $post_id, '_ndizi_project_id', true );
				$project_client = get_post_meta( $project_id, '_ndizi_client_id', true );
				if ( intval( $project_client ) !== $client_id ) {
					wp_die( __( 'Unauthorized access.', 'ndizi' ) );
				}
			} else {
				wp_die( __( 'Invalid discussion target.', 'ndizi' ) );
			}

			if ( ! empty( $content ) ) {
				// Bypass WP restrictions for anonymous comments by setting author parameters
				$comment_author       = get_the_title( $client_id ) . ' (' . __( 'Client', 'ndizi' ) . ')';
				$comment_author_email = get_post_meta( $client_id, '_ndizi_client_website', true ) ?: 'client@portal.local';

				$comment_id = wp_insert_comment(
					array(
						'comment_post_ID'      => $post_id,
						'comment_content'      => $content,
						'comment_author'       => $comment_author,
						'comment_author_email' => $comment_author_email,
						'comment_type'         => 'comment',
						'comment_approved'     => 1, // Auto approve client comments in their secure portal
						'user_id'              => is_user_logged_in() ? get_current_user_id() : 0,
					)
				);

				if ( $comment_id ) {
					// Handle file attachments
					if ( ! empty( $_FILES['ndizi_attachments'] ) ) {
						require_once ABSPATH . 'wp-admin/includes/image.php';
						require_once ABSPATH . 'wp-admin/includes/file.php';
						require_once ABSPATH . 'wp-admin/includes/media.php';

						$files          = $_FILES['ndizi_attachments'];
						$attachment_ids = array();

						// Loop through multiple files
						if ( is_array( $files['name'] ) ) {
							foreach ( $files['name'] as $key => $value ) {
								if ( $files['name'][ $key ] ) {
									$file = array(
										'name'     => $files['name'][ $key ],
										'type'     => $files['type'][ $key ],
										'tmp_name' => $files['tmp_name'][ $key ],
										'error'    => $files['error'][ $key ],
										'size'     => $files['size'][ $key ],
									);

									$_FILES['ndizi_temp_upload'] = $file;
									// Upload and associate with project/task post
									$attach_id = media_handle_upload( 'ndizi_temp_upload', $post_id );
									if ( ! is_wp_error( $attach_id ) ) {
										$attachment_ids[] = $attach_id;
									}
								}
							}
						}

						if ( ! empty( $attachment_ids ) ) {
							update_comment_meta( $comment_id, '_ndizi_comment_attachment_ids', $attachment_ids );
						}
					}

					wp_safe_redirect( get_permalink() );
					exit;
				}
			}
		}
	}

	/**
	 * Render portal main shortcode
	 */
	public static function render_portal_shortcode() {
		$client_id = self::get_authenticated_client_id();

		ob_start();
		?>
		<div class="ndizi-portal-container">
			<?php if ( ! $client_id ) : ?>
				<?php self::render_login_view(); ?>
			<?php else : ?>
				<?php self::render_dashboard_view( $client_id ); ?>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Login view
	 */
	private static function render_login_view() {
		$error = isset( $_GET['ndizi_auth_error'] ) ? true : false;
		?>
		<div class="ndizi-portal-login-card">
			<div class="ndizi-portal-logo-glow"></div>
			<h2><?php _e( 'Client Portal Access', 'ndizi' ); ?></h2>
			<p><?php _e( 'Enter your secure Client Portal Key to review your projects, invoices, and tasks.', 'ndizi' ); ?></p>

			<?php if ( $error ) : ?>
				<div class="ndizi-portal-alert alert-error">
					<?php _e( 'Invalid portal key. Please double-check and try again.', 'ndizi' ); ?>
				</div>
			<?php endif; ?>

			<form method="post" action="">
				<div class="ndizi-form-group">
					<input type="password" name="ndizi_portal_key" placeholder="<?php esc_attr_e( 'Enter Portal Key (e.g. ndizi_...)', 'ndizi' ); ?>" required>
				</div>
				<button type="submit" name="ndizi_portal_login" class="ndizi-portal-btn"><?php _e( 'Authenticate', 'ndizi' ); ?></button>
			</form>
		</div>
		<?php
	}

	/**
	 * Dashboard view
	 */
	private static function render_dashboard_view( $client_id ) {
		$client   = get_post( $client_id );
		$projects = get_posts(
			array(
				'post_type'      => 'ndizi_project',
				'posts_per_page' => -1,
				'meta_query'     => array(
					array(
						'key'   => '_ndizi_client_id',
						'value' => $client_id,
					),
				),
			)
		);

		$task_success = isset( $_GET['ndizi_task_success'] ) ? true : false;
		?>
		<header class="ndizi-portal-header">
			<div>
				<h1><?php echo esc_html( $client->post_title ); ?></h1>
				<p class="subtitle"><?php _e( 'Welcome to your project command center.', 'ndizi' ); ?></p>
			</div>
			<div>
				<a href="<?php echo esc_url( add_query_arg( 'ndizi_logout', '1', get_permalink() ) ); ?>" class="ndizi-portal-btn-secondary"><?php _e( 'Sign Out', 'ndizi' ); ?></a>
			</div>
		</header>

		<?php if ( $task_success ) : ?>
			<div class="ndizi-portal-alert alert-success">
				<?php _e( 'Task successfully submitted! Our team has been notified and will review it shortly.', 'ndizi' ); ?>
			</div>
		<?php endif; ?>

		<div class="ndizi-portal-layout">
			<!-- Main projects column -->
			<div class="ndizi-portal-main">
				<h2><?php _e( 'Your Projects', 'ndizi' ); ?></h2>
				<?php if ( empty( $projects ) ) : ?>
					<div class="ndizi-portal-card no-items">
						<p><?php _e( 'You do not have any projects assigned yet.', 'ndizi' ); ?></p>
					</div>
				<?php else : ?>
					<div class="ndizi-portal-accordion-grid">
						<?php
						foreach ( $projects as $project ) :
							// Aggregate time only (Harvest style)
							$time_totals = Ndizi_DB::get_time_totals( array( 'project_id' => $project->ID ) );
							$total_sec   = ! empty( $time_totals ) ? $time_totals[0]->total_duration : 0;
							$total_hours = round( $total_sec / 3600, 1 );

							// Fetch Project Invoices
							$invoices = get_posts(
								array(
									'post_type'      => 'ndizi_invoice',
									'posts_per_page' => -1,
									'meta_query'     => array(
										array(
											'key'   => '_ndizi_project_id',
											'value' => $project->ID,
										),
									),
								)
							);

							// Fetch Tasks
							$tasks = get_posts(
								array(
									'post_type'      => 'ndizi_task',
									'posts_per_page' => -1,
									'meta_query'     => array(
										array(
											'key'   => '_ndizi_project_id',
											'value' => $project->ID,
										),
									),
								)
							);
							?>
							<div class="ndizi-portal-card ndizi-project-card" data-project-id="<?php echo esc_attr( $project->ID ); ?>">
								<div class="ndizi-project-card-header">
									<div>
										<h3><?php echo esc_html( $project->post_title ); ?></h3>
										<div class="ndizi-project-summary-meta">
											<span class="ndizi-meta-hours"><strong><?php echo esc_html( $total_hours ); ?></strong> <?php _e( 'hours tracked', 'ndizi' ); ?></span>
											<span class="ndizi-meta-divider">&bull;</span>
											<span class="ndizi-meta-tasks"><?php echo count( $tasks ); ?> <?php _e( 'tasks', 'ndizi' ); ?></span>
										</div>
									</div>
									<button type="button" class="ndizi-accordion-toggle-btn">
										<span class="dashicons dashicons-arrow-down-alt2"></span>
									</button>
								</div>

								<div class="ndizi-project-card-content" style="display: none;">
									<div class="ndizi-project-details-text">
										<?php echo wpautop( esc_html( $project->post_content ) ); ?>
									</div>

									<hr class="ndizi-card-divider">

									<!-- Project Task Checklist -->
									<div class="ndizi-portal-tasks-section">
										<h4><?php _e( 'Tasks & Status', 'ndizi' ); ?></h4>
										<?php if ( empty( $tasks ) ) : ?>
											<p class="no-items-desc"><?php _e( 'No tasks registered for this project.', 'ndizi' ); ?></p>
										<?php else : ?>
											<ul class="ndizi-portal-task-list">
												<?php
												foreach ( $tasks as $task ) :
													$status   = get_post_meta( $task->ID, '_ndizi_task_status', true );
													$priority = get_post_meta( $task->ID, '_ndizi_task_priority', true );
													$due      = get_post_meta( $task->ID, '_ndizi_task_due_date', true );
													?>
													<li class="ndizi-portal-task-item">
														<div class="ndizi-task-details-col">
															<span class="ndizi-task-title"><?php echo esc_html( $task->post_title ); ?></span>
															<?php if ( $due ) : ?>
																<span class="ndizi-task-due"><?php _e( 'Due:', 'ndizi' ); ?> <?php echo esc_html( $due ); ?></span>
															<?php endif; ?>
														</div>
														<div class="ndizi-task-badges-col">
															<span class="ndizi-badge ndizi-task-<?php echo esc_attr( $status ); ?>"><?php echo esc_html( $status ); ?></span>
															<!-- Task Discussion Dialog trigger button -->
															<button type="button" class="ndizi-btn-comment-dialog" data-post-id="<?php echo esc_attr( $task->ID ); ?>" data-title="<?php echo esc_attr( $task->post_title ); ?>">
																<span class="dashicons dashicons-admin-comments"></span>
															</button>
														</div>
													</li>
												<?php endforeach; ?>
											</ul>
										<?php endif; ?>
									</div>

									<hr class="ndizi-card-divider">

									<!-- Invoices List -->
									<div class="ndizi-portal-invoices-section">
										<h4><?php _e( 'Project Invoices', 'ndizi' ); ?></h4>
										<?php if ( empty( $invoices ) ) : ?>
											<p class="no-items-desc"><?php _e( 'No invoices generated for this project.', 'ndizi' ); ?></p>
										<?php else : ?>
											<div class="ndizi-portal-table-wrapper">
												<table class="ndizi-portal-table">
													<thead>
														<tr>
															<th><?php _e( 'Invoice #', 'ndizi' ); ?></th>
															<th><?php _e( 'Date', 'ndizi' ); ?></th>
															<th><?php _e( 'Due Date', 'ndizi' ); ?></th>
															<th><?php _e( 'Amount', 'ndizi' ); ?></th>
															<th><?php _e( 'Status', 'ndizi' ); ?></th>
															<th><?php _e( 'Actions', 'ndizi' ); ?></th>
														</tr>
													</thead>
													<tbody>
														<?php
														foreach ( $invoices as $inv ) :
															$inv_status = get_post_meta( $inv->ID, '_ndizi_invoice_status', true );
															$inv_amount = get_post_meta( $inv->ID, '_ndizi_invoice_amount', true );
															$inv_date   = get_post_meta( $inv->ID, '_ndizi_invoice_date', true );
															$inv_due    = get_post_meta( $inv->ID, '_ndizi_invoice_due_date', true );
															?>
															<tr>
																<td><strong><?php echo esc_html( $inv->post_title ); ?></strong></td>
																<td><?php echo esc_html( $inv_date ); ?></td>
																<td><?php echo esc_html( $inv_due ); ?></td>
																<td><?php echo $inv_amount ? '$' . esc_html( number_format( $inv_amount, 2 ) ) : '-'; ?></td>
																<td><span class="ndizi-badge ndizi-invoice-<?php echo esc_attr( $inv_status ); ?>"><?php echo esc_html( $inv_status ); ?></span></td>
																<td>
																	<!-- Printable invoice URL linking to portal printing action -->
																	<a href="
																	<?php
																	echo esc_url(
																		add_query_arg(
																			array(
																				'ndizi_print_invoice' => $inv->ID,
																				'ndizi_token' => get_post_meta( $client_id, '_ndizi_client_auth_key', true ),
																			),
																			home_url()
																		)
																	);
																	?>
																				" target="_blank" class="ndizi-portal-btn-table">
																		<span class="dashicons dashicons-printer"></span> <?php _e( 'Print/PDF', 'ndizi' ); ?>
																	</a>
																</td>
															</tr>
														<?php endforeach; ?>
													</tbody>
												</table>
											</div>
										<?php endif; ?>
									</div>

									<hr class="ndizi-card-divider">

									<!-- Project Discussion / Messages -->
									<div class="ndizi-portal-discussion-section">
										<h4><?php _e( 'Project Discussion & Attachments', 'ndizi' ); ?></h4>
										<?php self::render_discussion_thread( $project->ID ); ?>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- Sidebar columns for submitting new tasks -->
			<div class="ndizi-portal-sidebar">
				<div class="ndizi-portal-card ndizi-sidebar-form-card">
					<h3><?php _e( 'Submit a Request / Task', 'ndizi' ); ?></h3>
					<p class="desc"><?php _e( 'Submit a new request. It will appear on our dashboards for immediate triage and user assignment.', 'ndizi' ); ?></p>

					<form method="post" action="">
						<?php wp_nonce_field( 'ndizi_portal_submit_task' ); ?>
						<div class="ndizi-form-group">
							<label for="ndizi_task_project_id"><?php _e( 'Select Project', 'ndizi' ); ?></label>
							<select name="ndizi_task_project_id" id="ndizi_task_project_id" required>
								<option value=""><?php _e( '-- Select Project --', 'ndizi' ); ?></option>
								<?php foreach ( $projects as $project ) : ?>
									<option value="<?php echo esc_attr( $project->ID ); ?>"><?php echo esc_html( $project->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="ndizi-form-group">
							<label for="ndizi_task_title"><?php _e( 'Task Summary', 'ndizi' ); ?></label>
							<input type="text" name="ndizi_task_title" id="ndizi_task_title" placeholder="<?php esc_attr_e( 'e.g. Design homepage hero layout', 'ndizi' ); ?>" required>
						</div>

						<div class="ndizi-form-group">
							<label for="ndizi_task_details"><?php _e( 'Requirements / Details', 'ndizi' ); ?></label>
							<textarea name="ndizi_task_details" id="ndizi_task_details" rows="5" placeholder="<?php esc_attr_e( 'Describe requirements, links, details...', 'ndizi' ); ?>" required></textarea>
						</div>

						<button type="submit" name="ndizi_submit_task_portal" class="ndizi-portal-btn"><?php _e( 'Submit Task', 'ndizi' ); ?></button>
					</form>
				</div>
			</div>
		</div>

		<!-- Dialog Modal overlay for Task Discussions -->
		<div id="ndizi_task_comment_modal" class="ndizi-portal-modal" style="display: none;">
			<div class="ndizi-portal-modal-content">
				<div class="ndizi-portal-modal-header">
					<h3 id="ndizi_modal_task_title">Task Discussion</h3>
					<button type="button" class="ndizi-portal-modal-close-btn">&times;</button>
				</div>
				<div class="ndizi-portal-modal-body" id="ndizi_modal_discussion_container">
					<!-- Populated by JS ajax or page triggers -->
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render discussion thread comments for a specific post (project or task)
	 */
	public static function render_discussion_thread( $post_id ) {
		$comments = get_comments(
			array(
				'post_id' => $post_id,
				'status'  => 'approve',
				'order'   => 'ASC',
			)
		);
		?>
		<div class="ndizi-discussion-thread">
			<?php if ( empty( $comments ) ) : ?>
				<p class="no-items-desc"><?php _e( 'No messages posted yet. Start the conversation below.', 'ndizi' ); ?></p>
			<?php else : ?>
				<ul class="ndizi-comments-list">
					<?php
					foreach ( $comments as $comment ) :
						$attach_ids = get_comment_meta( $comment->comment_ID, '_ndizi_comment_attachment_ids', true );
						?>
						<li class="ndizi-comment-item">
							<div class="ndizi-comment-meta">
								<strong class="ndizi-comment-author"><?php echo esc_html( $comment->comment_author ); ?></strong>
								<span class="ndizi-comment-date"><?php echo esc_html( comment_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), false, false, $comment ) ); ?></span>
							</div>
							<div class="ndizi-comment-text">
								<?php echo wpautop( esc_html( $comment->comment_content ) ); ?>
							</div>

							<!-- Display attachments if any -->
							<?php if ( ! empty( $attach_ids ) && is_array( $attach_ids ) ) : ?>
								<div class="ndizi-comment-attachments">
									<strong><?php _e( 'Attached Files:', 'ndizi' ); ?></strong>
									<ul>
										<?php
										foreach ( $attach_ids as $att_id ) :
											$att_url  = wp_get_attachment_url( $att_id );
											$att_name = basename( get_attached_file( $att_id ) );
											?>
											<li>
												<span class="dashicons dashicons-paperclip"></span>
												<a href="<?php echo esc_url( $att_url ); ?>" target="_blank"><?php echo esc_html( $att_name ); ?></a>
											</li>
										<?php endforeach; ?>
									</ul>
								</div>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<!-- Submit comment form -->
			<form method="post" action="" enctype="multipart/form-data" class="ndizi-comment-form">
				<?php wp_nonce_field( 'ndizi_portal_submit_comment' ); ?>
				<input type="hidden" name="ndizi_comment_post_id" value="<?php echo esc_attr( $post_id ); ?>">

				<div class="ndizi-form-group">
					<textarea name="ndizi_comment_content" rows="3" placeholder="<?php esc_attr_e( 'Write a message...', 'ndizi' ); ?>" required></textarea>
				</div>

				<div class="ndizi-form-row">
					<div class="ndizi-form-group flex-grow">
						<label class="ndizi-file-upload-label">
							<span class="dashicons dashicons-upload"></span> <?php _e( 'Attach files', 'ndizi' ); ?>
							<input type="file" name="ndizi_attachments[]" multiple style="display: none;" onchange="jQuery(this).parent().find('.file-count-label').text(this.files.length + ' file(s) selected');">
							<span class="file-count-label" style="font-size: 11px; color: #94a3b8; font-weight: normal; margin-left: 5px;"></span>
						</label>
					</div>
					<div>
						<button type="submit" name="ndizi_submit_portal_comment" class="ndizi-portal-btn-sm"><?php _e( 'Send Message', 'ndizi' ); ?></button>
					</div>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * AJAX logic to load a task discussion thread in portal
	 */
	public static function ajax_load_task_discussion() {
		$client_id = self::get_authenticated_client_id();
		if ( ! $client_id ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'ndizi' ) ) );
		}

		$task_id = isset( $_POST['task_id'] ) ? intval( $_POST['task_id'] ) : 0;
		if ( ! $task_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid task ID.', 'ndizi' ) ) );
		}

		// Verify task belongs to a project belonging to this client
		$project_id     = get_post_meta( $task_id, '_ndizi_project_id', true );
		$project_client = get_post_meta( $project_id, '_ndizi_client_id', true );
		if ( intval( $project_client ) !== $client_id ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access to task.', 'ndizi' ) ) );
		}

		ob_start();
		self::render_discussion_thread( $task_id );
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}
}
