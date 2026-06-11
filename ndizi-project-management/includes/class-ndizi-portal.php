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
		self::register_portal_block();
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'wp_ajax_ndizi_load_task_discussion', array( __CLASS__, 'ajax_load_task_discussion' ) );
		add_action( 'wp_ajax_nopriv_ndizi_load_task_discussion', array( __CLASS__, 'ajax_load_task_discussion' ) );
	}

	/**
	 * Enqueue frontend CSS and JS
	 */
	public static function enqueue_assets() {
		global $post;
		if ( is_a( $post, 'WP_Post' ) ) {
			$has_shortcode = has_shortcode( $post->post_content, 'ndizi_client_portal' );
			$has_block     = has_block( 'ndizi/client-portal', $post->post_content );

			if ( $has_shortcode || $has_block ) {
				// Make sure style is registered
				if ( ! wp_style_is( 'ndizi-portal-style', 'registered' ) ) {
					wp_register_style(
						'ndizi-portal-style',
						NDIZI_PLUGIN_URL . 'build/portal.css',
						array(),
						NDIZI_VERSION
					);
				}
				// Make sure script is registered
				if ( ! wp_script_is( 'ndizi-portal-script', 'registered' ) ) {
					wp_register_script(
						'ndizi-portal-script',
						NDIZI_PLUGIN_URL . 'build/portal.js',
						array( 'jquery' ),
						NDIZI_VERSION,
						true
					);
					wp_localize_script(
						'ndizi-portal-script',
						'ndizi_portal',
						array(
							'ajax_url' => admin_url( 'admin-ajax.php' ),
						)
					);
				}

				wp_enqueue_style( 'ndizi-portal-style' );
				wp_enqueue_script( 'ndizi-portal-script' );
			}
		}
	}

	/**
	 * Register the Gutenberg Client Portal block
	 */
	public static function register_portal_block() {
		// Register the block editor script
		$asset_file   = NDIZI_PLUGIN_DIR . 'build/block.asset.php';
		$dependencies = array( 'wp-blocks', 'wp-element', 'wp-i18n' );
		$version      = NDIZI_VERSION;

		if ( file_exists( $asset_file ) ) {
			$assets       = include $asset_file;
			$dependencies = isset( $assets['dependencies'] ) ? $assets['dependencies'] : $dependencies;
			$version      = isset( $assets['version'] ) ? $assets['version'] : $version;
		}

		wp_register_script(
			'ndizi-block-script',
			NDIZI_PLUGIN_URL . 'build/block.js',
			$dependencies,
			$version,
			true
		);

		// Register frontend script and style
		wp_register_style(
			'ndizi-portal-style',
			NDIZI_PLUGIN_URL . 'build/portal.css',
			array(),
			NDIZI_VERSION
		);

		wp_register_script(
			'ndizi-portal-script',
			NDIZI_PLUGIN_URL . 'build/portal.js',
			array( 'jquery' ),
			NDIZI_VERSION,
			true
		);

		wp_localize_script(
			'ndizi-portal-script',
			'ndizi_portal',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
			)
		);

		register_block_type(
			'ndizi/client-portal',
			array(
				'editor_script'   => 'ndizi-block-script',
				'script'          => 'ndizi-portal-script',
				'style'           => 'ndizi-portal-style',
				'attributes'      => array(
					'backgroundColor' => array(
						'type'    => 'string',
						'default' => '#f8fafc',
					),
					'textColor'       => array(
						'type'    => 'string',
						'default' => '#0f172a',
					),
					'buttonColor'     => array(
						'type'    => 'string',
						'default' => '#4f46e5',
					),
					'linkColor'       => array(
						'type'    => 'string',
						'default' => '#818cf8',
					),
				),
				'render_callback' => array( __CLASS__, 'render_portal_block_html' ),
			)
		);
	}

	/**
	 * Render portal main block HTML
	 *
	 * @param array $attributes Block attributes.
	 */
	public static function render_portal_block_html( $attributes ) {
		// Explicitly enqueue localized assets as a fallback
		wp_enqueue_style( 'ndizi-portal-style' );
		wp_enqueue_script( 'ndizi-portal-script' );

		// Parse colors from attributes (with fallbacks)
		$bg_color     = isset( $attributes['backgroundColor'] ) ? $attributes['backgroundColor'] : '#f8fafc';
		$text_color   = isset( $attributes['textColor'] ) ? $attributes['textColor'] : '#0f172a';
		$button_color = isset( $attributes['buttonColor'] ) ? $attributes['buttonColor'] : '#4f46e5';
		$link_color   = isset( $attributes['linkColor'] ) ? $attributes['linkColor'] : '#818cf8';

		// Generate a scoped unique ID for wrapper
		$wrapper_id = 'ndizi-portal-' . wp_rand( 1000, 9999 );

		// Check if chosen background is a light color
		$is_light = self::is_color_light( $bg_color );

		// Configure secondary design colors and border widths/opacities dynamically based on luminance
		$card_bg      = $is_light ? '#ffffff' : 'rgba(30, 41, 59, 0.7)';
		$border_color = $is_light ? 'rgba(0, 0, 0, 0.08)' : 'rgba(255, 255, 255, 0.08)';
		$text_muted   = $is_light ? '#475569' : '#94a3b8';
		$input_bg     = $is_light ? '#ffffff' : 'rgba(15, 23, 42, 0.6)';
		$input_border = $is_light ? 'rgba(0, 0, 0, 0.15)' : 'rgba(255, 255, 255, 0.1)';
		$item_bg      = $is_light ? 'rgba(0, 0, 0, 0.02)' : 'rgba(15, 23, 42, 0.4)';
		$item_border  = $is_light ? 'rgba(0, 0, 0, 0.06)' : 'rgba(255, 255, 255, 0.06)';

		// Build scoped styles
		$inline_css  = "<style>\n";
		$inline_css .= "#{$wrapper_id} {\n";
		$inline_css .= '  --ndizi-bg-main: ' . esc_attr( $bg_color ) . " !important;\n";
		$inline_css .= '  --ndizi-bg-card: ' . esc_attr( $card_bg ) . " !important;\n";
		$inline_css .= '  --ndizi-border-color: ' . esc_attr( $border_color ) . " !important;\n";
		$inline_css .= '  --ndizi-text-main: ' . esc_attr( $text_color ) . " !important;\n";
		$inline_css .= '  --ndizi-text-muted: ' . esc_attr( $text_muted ) . " !important;\n";
		$inline_css .= '  --ndizi-primary: ' . esc_attr( $button_color ) . " !important;\n";
		$inline_css .= '  --ndizi-primary-hover: ' . esc_attr( $button_color ) . " !important;\n";
		$inline_css .= "}\n";

		// Override outer portal container
		$inline_css .= "#{$wrapper_id} .ndizi-portal-container {\n";
		$inline_css .= '  background-color: ' . esc_attr( $bg_color ) . " !important;\n";
		$inline_css .= '  color: ' . esc_attr( $text_color ) . " !important;\n";
		$inline_css .= "}\n";

		// Fix company name / header h1 invisibility by overriding transparency
		$inline_css .= "#{$wrapper_id} .ndizi-portal-header h1 {\n";
		$inline_css .= "  background: none !important;\n";
		$inline_css .= '  -webkit-text-fill-color: ' . esc_attr( $text_color ) . " !important;\n";
		$inline_css .= '  color: ' . esc_attr( $text_color ) . " !important;\n";
		$inline_css .= "}\n";

		// Apply card background and borders
		$inline_css .= "#{$wrapper_id} .ndizi-portal-login-card,\n";
		$inline_css .= "#{$wrapper_id} .ndizi-portal-card,\n";
		$inline_css .= "#{$wrapper_id} .ndizi-project-card,\n";
		$inline_css .= "#{$wrapper_id} .ndizi-portal-modal-content {\n";
		$inline_css .= '  background-color: ' . esc_attr( $card_bg ) . " !important;\n";
		$inline_css .= '  border-color: ' . esc_attr( $border_color ) . " !important;\n";
		$inline_css .= "}\n";

		// Subtitles and headings
		$inline_css .= "#{$wrapper_id} h2,\n";
		$inline_css .= "#{$wrapper_id} h3,\n";
		$inline_css .= "#{$wrapper_id} h4,\n";
		$inline_css .= "#{$wrapper_id} label {\n";
		$inline_css .= '  color: ' . esc_attr( $text_color ) . " !important;\n";
		$inline_css .= "}\n";

		// Fix completed/in_progress task titles being hardcoded to white (causing low contrast in light mode)
		$inline_css .= "#{$wrapper_id} .ndizi-task-title {\n";
		$inline_css .= '  color: ' . esc_attr( $text_color ) . " !important;\n";
		$inline_css .= "}\n";

		// Fix project description details having low contrast
		$inline_css .= "#{$wrapper_id} .ndizi-project-details-text {\n";
		$inline_css .= '  color: ' . esc_attr( $text_muted ) . " !important;\n";
		$inline_css .= "}\n";

		// Form controls background, border, text
		$inline_css .= "#{$wrapper_id} input[type=text],\n";
		$inline_css .= "#{$wrapper_id} input[type=password],\n";
		$inline_css .= "#{$wrapper_id} select,\n";
		$inline_css .= "#{$wrapper_id} textarea {\n";
		$inline_css .= '  background-color: ' . esc_attr( $input_bg ) . " !important;\n";
		$inline_css .= '  border-color: ' . esc_attr( $input_border ) . " !important;\n";
		$inline_css .= '  color: ' . esc_attr( $text_color ) . " !important;\n";
		$inline_css .= "}\n";

		// Buttons and primary accents (solid backgrounds)
		$inline_css .= "#{$wrapper_id} .ndizi-portal-btn,\n";
		$inline_css .= "#{$wrapper_id} .ndizi-btn-comment-dialog,\n";
		$inline_css .= "#{$wrapper_id} .ndizi-portal-btn-sm,\n";
		$inline_css .= "#{$wrapper_id} .ndizi-portal-tabs a.ndizi-active-tab {\n";
		$inline_css .= '  background-color: ' . esc_attr( $button_color ) . " !important;\n";
		$inline_css .= '  border-color: ' . esc_attr( $button_color ) . " !important;\n";
		$inline_css .= "  color: #ffffff !important;\n";
		$inline_css .= "}\n";

		// Links and text highlights
		$inline_css .= "#{$wrapper_id} a {\n";
		$inline_css .= '  color: ' . esc_attr( $link_color ) . ";\n";
		$inline_css .= "}\n";
		$inline_css .= "#{$wrapper_id} a:hover {\n";
		$inline_css .= '  color: ' . esc_attr( $link_color ) . " !important;\n";
		$inline_css .= "  opacity: 0.85;\n";
		$inline_css .= "}\n";

		// Accordion card header text highlights
		$inline_css .= "#{$wrapper_id} .ndizi-project-card-header:hover h3,\n";
		$inline_css .= "#{$wrapper_id} .ndizi-meta-hours strong {\n";
		$inline_css .= '  color: ' . esc_attr( $link_color ) . " !important;\n";
		$inline_css .= "}\n";

		// Tasks and comment items
		$inline_css .= "#{$wrapper_id} .ndizi-portal-task-item,\n";
		$inline_css .= "#{$wrapper_id} .ndizi-comment-item {\n";
		$inline_css .= '  background-color: ' . esc_attr( $item_bg ) . " !important;\n";
		$inline_css .= '  border-color: ' . esc_attr( $item_border ) . " !important;\n";
		$inline_css .= "}\n";

		// Secondary buttons (Sign Out, File uploads)
		$inline_css .= "#{$wrapper_id} .ndizi-portal-btn-secondary,\n";
		$inline_css .= "#{$wrapper_id} .ndizi-file-upload-label {\n";
		if ( $is_light ) {
			$inline_css .= "  background-color: #ffffff !important;\n";
			$inline_css .= "  border-color: rgba(0, 0, 0, 0.15) !important;\n";
			$inline_css .= '  color: ' . esc_attr( $text_color ) . " !important;\n";
		} else {
			$inline_css .= "  background-color: transparent !important;\n";
			$inline_css .= "  border-color: rgba(255, 255, 255, 0.15) !important;\n";
			$inline_css .= '  color: ' . esc_attr( $text_color ) . " !important;\n";
		}
		$inline_css .= "}\n";

		// Muted texts
		$inline_css .= "#{$wrapper_id} .subtitle,\n";
		$inline_css .= "#{$wrapper_id} .desc,\n";
		$inline_css .= "#{$wrapper_id} .ndizi-task-due,\n";
		$inline_css .= "#{$wrapper_id} .ndizi-project-summary-meta,\n";
		$inline_css .= "#{$wrapper_id} .ndizi-comment-date,\n";
		$inline_css .= "#{$wrapper_id} .no-items {\n";
		$inline_css .= '  color: ' . esc_attr( $text_muted ) . " !important;\n";
		$inline_css .= "}\n";

		$inline_css .= "</style>\n";

		$portal_content = self::render_portal_shortcode();

		return $inline_css . '<div id="' . esc_attr( $wrapper_id ) . '" class="ndizi-custom-branded-portal">' . $portal_content . '</div>';
	}

	/**
	 * Check if a hex color is light or dark based on relative luminance
	 *
	 * @param string $hex Hex color string.
	 * @return bool True if light, false if dark.
	 */
	private static function is_color_light( $hex ) {
		$hex = str_replace( '#', '', $hex );
		if ( 3 === strlen( $hex ) ) {
			$r = hexdec( substr( $hex, 0, 1 ) . substr( $hex, 0, 1 ) );
			$g = hexdec( substr( $hex, 1, 1 ) . substr( $hex, 1, 1 ) );
			$b = hexdec( substr( $hex, 2, 1 ) . substr( $hex, 2, 1 ) );
		} elseif ( 6 === strlen( $hex ) ) {
			$r = hexdec( substr( $hex, 0, 2 ) );
			$g = hexdec( substr( $hex, 2, 2 ) );
			$b = hexdec( substr( $hex, 4, 2 ) );
		} else {
			return false;
		}
		// Calculate relative luminance
		$luminance = ( 0.2126 * $r + 0.7152 * $g + 0.0722 * $b ) / 255;
		return $luminance > 0.5;
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
			$token = sanitize_text_field( wp_unslash( $_COOKIE['ndizi_client_token'] ) );
			return self::get_client_id_by_token( $token );
		}

		return false;
	}

	/**
	 * Set (or clear) the portal token cookie with hardened flags.
	 *
	 * The cookie is HttpOnly (not readable by JavaScript), Secure on HTTPS, and
	 * SameSite=Lax to limit cross-site sending. Uses the options-array signature
	 * of setcookie() available since PHP 7.3.
	 *
	 * @param string $token   Token value to store ('' to clear).
	 * @param int    $expires Expiry timestamp.
	 */
	private static function set_token_cookie( $token, $expires ) {
		setcookie(
			'ndizi_client_token',
			$token,
			array(
				'expires'  => $expires,
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	/**
	 * Helper: Query client by auth key
	 *
	 * @param string $token Portal auth key supplied by the visitor.
	 * @return int|false Client post ID, or false if not found.
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
				// Set cookie for 30 days.
				self::set_token_cookie( $token, time() + ( 30 * DAY_IN_SECONDS ) );
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
				self::set_token_cookie( $token, time() + ( 30 * DAY_IN_SECONDS ) );
				wp_safe_redirect( get_permalink() );
				exit;
			} else {
				wp_safe_redirect( add_query_arg( 'ndizi_auth_error', '1', get_permalink() ) );
				exit;
			}
		}

		// 3. Logout
		if ( isset( $_GET['ndizi_logout'] ) ) {
			self::set_token_cookie( '', time() - YEAR_IN_SECONDS );
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
				wp_die( __( 'Unauthorized project selection.', 'ndizi-project-management' ) );
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
					wp_die( __( 'Unauthorized access.', 'ndizi-project-management' ) );
				}
			} elseif ( 'ndizi_task' === $post_type ) {
				$project_id     = get_post_meta( $post_id, '_ndizi_project_id', true );
				$project_client = get_post_meta( $project_id, '_ndizi_client_id', true );
				if ( intval( $project_client ) !== $client_id ) {
					wp_die( __( 'Unauthorized access.', 'ndizi-project-management' ) );
				}
			} else {
				wp_die( __( 'Invalid discussion target.', 'ndizi-project-management' ) );
			}

			if ( ! empty( $content ) ) {
				// Portal clients are not WP users, so synthesize valid author fields.
				// Use a per-client address at the site's own domain (a URL is not a
				// valid email, which the previous fallback incorrectly used).
				$comment_author = get_the_title( $client_id ) . ' (' . __( 'Client', 'ndizi-project-management' ) . ')';
				$site_host      = wp_parse_url( home_url(), PHP_URL_HOST );
				$site_host      = $site_host ? $site_host : 'localhost';
				$comment_author_email = 'client-' . absint( $client_id ) . '@' . $site_host;

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
			<h2><?php _e( 'Client Portal Access', 'ndizi-project-management' ); ?></h2>
			<p><?php _e( 'Enter your secure Client Portal Key to review your projects, invoices, and tasks.', 'ndizi-project-management' ); ?></p>

			<?php if ( $error ) : ?>
				<div class="ndizi-portal-alert alert-error">
					<?php _e( 'Invalid portal key. Please double-check and try again.', 'ndizi-project-management' ); ?>
				</div>
			<?php endif; ?>

			<form method="post" action="">
				<div class="ndizi-form-group">
					<input type="password" name="ndizi_portal_key" placeholder="<?php esc_attr_e( 'Enter Portal Key (e.g. ndizi_...)', 'ndizi-project-management' ); ?>" required>
				</div>
				<button type="submit" name="ndizi_portal_login" class="ndizi-portal-btn"><?php _e( 'Authenticate', 'ndizi-project-management' ); ?></button>
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
				<p class="subtitle"><?php _e( 'Welcome to your project command center.', 'ndizi-project-management' ); ?></p>
			</div>
			<div>
				<a href="<?php echo esc_url( add_query_arg( 'ndizi_logout', '1', get_permalink() ) ); ?>" class="ndizi-portal-btn-secondary"><?php _e( 'Sign Out', 'ndizi-project-management' ); ?></a>
			</div>
		</header>

		<?php if ( $task_success ) : ?>
			<div class="ndizi-portal-alert alert-success">
				<?php _e( 'Task successfully submitted! Our team has been notified and will review it shortly.', 'ndizi-project-management' ); ?>
			</div>
		<?php endif; ?>

		<div class="ndizi-portal-layout">
			<!-- Main projects column -->
			<div class="ndizi-portal-main">
				<h2><?php _e( 'Your Projects', 'ndizi-project-management' ); ?></h2>
				<?php if ( empty( $projects ) ) : ?>
					<div class="ndizi-portal-card no-items">
						<p><?php _e( 'You do not have any projects assigned yet.', 'ndizi-project-management' ); ?></p>
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
											<span class="ndizi-meta-hours"><strong><?php echo esc_html( $total_hours ); ?></strong> <?php _e( 'hours tracked', 'ndizi-project-management' ); ?></span>
											<span class="ndizi-meta-divider">&bull;</span>
											<span class="ndizi-meta-tasks"><?php echo count( $tasks ); ?> <?php _e( 'tasks', 'ndizi-project-management' ); ?></span>
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
										<h4><?php _e( 'Tasks & Status', 'ndizi-project-management' ); ?></h4>
										<?php if ( empty( $tasks ) ) : ?>
											<p class="no-items-desc"><?php _e( 'No tasks registered for this project.', 'ndizi-project-management' ); ?></p>
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
																<span class="ndizi-task-due"><?php _e( 'Due:', 'ndizi-project-management' ); ?> <?php echo esc_html( $due ); ?></span>
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
										<h4><?php _e( 'Project Invoices', 'ndizi-project-management' ); ?></h4>
										<?php if ( empty( $invoices ) ) : ?>
											<p class="no-items-desc"><?php _e( 'No invoices generated for this project.', 'ndizi-project-management' ); ?></p>
										<?php else : ?>
											<div class="ndizi-portal-table-wrapper">
												<table class="ndizi-portal-table">
													<thead>
														<tr>
															<th><?php _e( 'Invoice #', 'ndizi-project-management' ); ?></th>
															<th><?php _e( 'Date', 'ndizi-project-management' ); ?></th>
															<th><?php _e( 'Due Date', 'ndizi-project-management' ); ?></th>
															<th><?php _e( 'Amount', 'ndizi-project-management' ); ?></th>
															<th><?php _e( 'Status', 'ndizi-project-management' ); ?></th>
															<th><?php _e( 'Actions', 'ndizi-project-management' ); ?></th>
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
																		<span class="dashicons dashicons-printer"></span> <?php _e( 'Print/PDF', 'ndizi-project-management' ); ?>
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
										<h4><?php _e( 'Project Discussion & Attachments', 'ndizi-project-management' ); ?></h4>
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
					<h3><?php _e( 'Submit a Request / Task', 'ndizi-project-management' ); ?></h3>
					<p class="desc"><?php _e( 'Submit a new request. It will appear on our dashboards for immediate triage and user assignment.', 'ndizi-project-management' ); ?></p>

					<form method="post" action="">
						<?php wp_nonce_field( 'ndizi_portal_submit_task' ); ?>
						<div class="ndizi-form-group">
							<label for="ndizi_task_project_id"><?php _e( 'Select Project', 'ndizi-project-management' ); ?></label>
							<select name="ndizi_task_project_id" id="ndizi_task_project_id" required>
								<option value=""><?php _e( '-- Select Project --', 'ndizi-project-management' ); ?></option>
								<?php foreach ( $projects as $project ) : ?>
									<option value="<?php echo esc_attr( $project->ID ); ?>"><?php echo esc_html( $project->post_title ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="ndizi-form-group">
							<label for="ndizi_task_title"><?php _e( 'Task Summary', 'ndizi-project-management' ); ?></label>
							<input type="text" name="ndizi_task_title" id="ndizi_task_title" placeholder="<?php esc_attr_e( 'e.g. Design homepage hero layout', 'ndizi-project-management' ); ?>" required>
						</div>

						<div class="ndizi-form-group">
							<label for="ndizi_task_details"><?php _e( 'Requirements / Details', 'ndizi-project-management' ); ?></label>
							<textarea name="ndizi_task_details" id="ndizi_task_details" rows="5" placeholder="<?php esc_attr_e( 'Describe requirements, links, details...', 'ndizi-project-management' ); ?>" required></textarea>
						</div>

						<button type="submit" name="ndizi_submit_task_portal" class="ndizi-portal-btn"><?php _e( 'Submit Task', 'ndizi-project-management' ); ?></button>
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
				<p class="no-items-desc"><?php _e( 'No messages posted yet. Start the conversation below.', 'ndizi-project-management' ); ?></p>
			<?php else : ?>
				<ul class="ndizi-comments-list">
					<?php
					foreach ( $comments as $comment ) :
						$attach_ids = get_comment_meta( $comment->comment_ID, '_ndizi_comment_attachment_ids', true );
						?>
						<li class="ndizi-comment-item">
							<div class="ndizi-comment-meta">
								<strong class="ndizi-comment-author"><?php echo esc_html( $comment->comment_author ); ?></strong>
								<span class="ndizi-comment-date"><?php echo esc_html( get_comment_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $comment ) ); ?></span>
							</div>
							<div class="ndizi-comment-text">
								<?php echo wpautop( esc_html( $comment->comment_content ) ); ?>
							</div>

							<!-- Display attachments if any -->
							<?php if ( ! empty( $attach_ids ) && is_array( $attach_ids ) ) : ?>
								<div class="ndizi-comment-attachments">
									<strong><?php _e( 'Attached Files:', 'ndizi-project-management' ); ?></strong>
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
					<textarea name="ndizi_comment_content" rows="3" placeholder="<?php esc_attr_e( 'Write a message...', 'ndizi-project-management' ); ?>" required></textarea>
				</div>

				<div class="ndizi-form-row">
					<div class="ndizi-form-group flex-grow">
						<label class="ndizi-file-upload-label">
							<span class="dashicons dashicons-upload"></span> <?php _e( 'Attach files', 'ndizi-project-management' ); ?>
							<input type="file" name="ndizi_attachments[]" multiple style="display: none;" onchange="jQuery(this).parent().find('.file-count-label').text(this.files.length + ' file(s) selected');">
							<span class="file-count-label" style="font-size: 11px; color: #94a3b8; font-weight: normal; margin-left: 5px;"></span>
						</label>
					</div>
					<div>
						<button type="submit" name="ndizi_submit_portal_comment" class="ndizi-portal-btn-sm"><?php _e( 'Send Message', 'ndizi-project-management' ); ?></button>
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
			wp_send_json_error( array( 'message' => __( 'Unauthorized access.', 'ndizi-project-management' ) ) );
		}

		$task_id = isset( $_POST['task_id'] ) ? intval( $_POST['task_id'] ) : 0;
		if ( ! $task_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid task ID.', 'ndizi-project-management' ) ) );
		}

		// Verify task belongs to a project belonging to this client
		$project_id     = get_post_meta( $task_id, '_ndizi_project_id', true );
		$project_client = get_post_meta( $project_id, '_ndizi_client_id', true );
		if ( intval( $project_client ) !== $client_id ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized access to task.', 'ndizi-project-management' ) ) );
		}

		ob_start();
		self::render_discussion_thread( $task_id );
		$html = ob_get_clean();

		wp_send_json_success( array( 'html' => $html ) );
	}
}
