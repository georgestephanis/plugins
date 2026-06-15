<?php
/**
 * Plugin Name: Go Dark
 * Plugin URI:  https://wordpress.org/plugins/go-dark/
 * Description: Enables websites to 'go dark' with a customizable message, premium design presets, and start/end times to protest various causes.
 * Author:      George Stephanis
 * Author URI:  https://georgestephanis.wordpress.com
 * Version: 1.1.0
 * Text Domain: go-dark
 *
 * @package go-dark
 */

if ( ! class_exists( 'go_dark' ) ) :

	/**
	 * Go Dark main plugin class.
	 */
	class go_dark {

		/**
		 * Bootstrap all hooks.
		 *
		 * @return void
		 */
		public static function go() {
			add_action( 'init', array( __CLASS__, 'init' ) );
		}

		/**
		 * Register hooks on init.
		 *
		 * @return void
		 */
		public static function init() {
			add_action( 'admin_menu', array( __CLASS__, 'add_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'admin_scripts' ) );
			if ( ! is_admin() && ! is_feed() ) {
				if ( isset( $_GET['go_dark'] ) || self::is_dark() ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					add_action( 'template_redirect', array( __CLASS__, 'show_page' ) );
				}
			}
		}

		/**
		 * Enqueue admin scripts and styles.
		 *
		 * @param string $hook The current admin page hook.
		 * @return void
		 */
		public static function admin_scripts( $hook ) {
			if ( 'toplevel_page_go-dark' !== $hook ) {
				return;
			}
			wp_enqueue_media();
		}

		/**
		 * Register the admin menu item.
		 *
		 * @return void
		 */
		public static function add_admin_menu() {
			add_menu_page(
				'Go Dark',
				'Go Dark',
				'manage_options',
				'go-dark',
				array( __CLASS__, 'page_go_dark' ),
				'dashicons-hidden'
			);
		}

		/**
		 * Render the admin settings page.
		 *
		 * @return void
		 */
		public static function page_go_dark() {
			if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
				self::catch_post();
				echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__( 'Settings saved.', 'go-dark' ) . '</strong></p></div>';
			}

			$status         = self::get_status();
			$start          = self::get_start();
			$end            = self::get_end();
			$title          = self::get_title();
			$text           = self::get_text();
			$theme          = self::get_theme();
			$accent_color   = self::get_accent_color();
			$custom_img     = self::get_custom_img_url();
			$show_countdown = self::get_show_countdown();
			$link_url       = self::get_link_url();
			$link_text      = self::get_link_text();
			?>
		<style>
			.go-dark-admin-wrap {
				max-width: 1100px;
				margin: 20px 20px 20px 0;
				font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
			}
			.go-dark-header {
				background: #0f172a;
				color: #fff;
				padding: 24px 30px;
				border-radius: 12px 12px 0 0;
				display: flex;
				justify-content: space-between;
				align-items: center;
				border-bottom: 4px solid var(--header-accent, #ff3333);
			}
			.go-dark-header h2 {
				margin: 0;
				color: #fff;
				font-size: 24px;
				font-weight: 700;
			}
			.go-dark-header .go-dark-status-pill {
				font-size: 12px;
				font-weight: 600;
				padding: 6px 14px;
				border-radius: 9999px;
				text-transform: uppercase;
				letter-spacing: 0.05em;
			}
			.go-dark-status-active { background: #ef4444; color: #fff; }
			.go-dark-status-scheduled { background: #3b82f6; color: #fff; }
			.go-dark-status-inactive { background: #4b5563; color: #fff; }

			.go-dark-admin-notice-dark {
				background: #fef2f2;
				border-left: 4px solid #ef4444;
				color: #991b1b;
				padding: 12px 20px;
				margin: 15px 0;
				border-radius: 4px;
				font-weight: 600;
			}

			.go-dark-admin-layout {
				display: grid;
				grid-template-columns: 1fr;
				gap: 20px;
				margin-top: 20px;
			}
			@media (min-width: 900px) {
				.go-dark-admin-layout {
					grid-template-columns: 7fr 3fr;
				}
			}

			.go-dark-card {
				background: #fff;
				border: 1px solid #e2e8f0;
				border-radius: 8px;
				box-shadow: 0 1px 3px rgba(0,0,0,0.02);
				padding: 24px;
				margin-bottom: 20px;
			}
			.go-dark-card-title {
				font-size: 16px;
				font-weight: 700;
				margin-top: 0;
				margin-bottom: 20px;
				padding-bottom: 12px;
				border-bottom: 1px solid #f1f5f9;
				color: #1e293b;
			}

			.go-dark-row {
				margin-bottom: 20px;
			}
			.go-dark-row label {
				display: block;
				font-weight: 600;
				margin-bottom: 8px;
				color: #334155;
			}
			.go-dark-row input[type="text"],
			.go-dark-row input[type="color"],
			.go-dark-row input[type="datetime-local"],
			.go-dark-row select {
				width: 100%;
				max-width: 100%;
				padding: 8px 12px;
				border: 1px solid #cbd5e1;
				border-radius: 6px;
				background: #fff;
				font-size: 14px;
				transition: border-color 0.15s, box-shadow 0.15s, opacity 0.15s;
			}
			.go-dark-row input[type="text"]:focus,
			.go-dark-row input[type="datetime-local"]:focus,
			.go-dark-row select:focus {
				border-color: #3b82f6;
				box-shadow: 0 0 0 1px #3b82f6;
				outline: 2px solid transparent;
			}
			.go-dark-row p.description {
				margin: 6px 0 0 0;
				color: #64748b;
				font-style: italic;
				font-size: 12px;
			}

			.go-dark-row-grid {
				display: grid;
				grid-template-columns: 1fr;
				gap: 15px;
			}
			@media (min-width: 600px) {
				.go-dark-row-grid {
					grid-template-columns: 1fr 1fr;
				}
			}

			.go-dark-submit-sidebar {
				position: sticky;
				top: 50px;
			}
			.go-dark-submit-sidebar .button-primary {
				width: 100%;
				padding: 10px 16px !important;
				height: auto !important;
				font-size: 15px !important;
				line-height: 1.2 !important;
				font-weight: 600 !important;
			}
			.go-dark-preview-link {
				display: block;
				margin-top: 15px;
				text-align: center;
				text-decoration: none;
				font-weight: 600;
				color: #2563eb;
			}
			.go-dark-preview-link:hover {
				color: #1d4ed8;
				text-decoration: underline;
			}

			.go-dark-preset-box {
				background: #f8fafc;
				padding: 16px;
				border-radius: 8px;
				border: 1px dashed #cbd5e1;
				margin-bottom: 24px;
			}
			.go-dark-preset-box p {
				margin: 0 0 10px 0;
				font-weight: 600;
				color: #475569;
			}
		</style>

		<div class="go-dark-admin-wrap" style="--header-accent: <?php echo esc_attr( $accent_color ); ?>;">
			<div class="go-dark-header">
				<h2>Go Dark</h2>
				<div>
					<?php if ( self::is_dark() ) : ?>
						<span class="go-dark-status-pill go-dark-status-active"><?php esc_html_e( 'Dark Mode Active', 'go-dark' ); ?></span>
					<?php else : ?>
						<span class="go-dark-status-pill go-dark-status-inactive"><?php esc_html_e( 'Offline / Inactive', 'go-dark' ); ?></span>
					<?php endif; ?>
				</div>
			</div>

			<?php if ( self::is_dark() ) : ?>
				<div class="go-dark-admin-notice-dark">
					<p><?php esc_html_e( 'Your website is currently Dark. Visitors will see the configured splash page and receive a 503 status code.', 'go-dark' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'go_dark_settings', 'go_dark_nonce' ); ?>

				<div class="go-dark-admin-layout">
					<!-- Main Settings Column -->
					<div class="go-dark-main-column">
						
						<!-- Preset Selection -->
						<div class="go-dark-preset-box">
							<p><span class="dashicons dashicons-forms" style="vertical-align: middle; margin-right: 5px;"></span><?php esc_html_e( 'Load Cause Preset Template', 'go-dark' ); ?></p>
							<select id="go_dark_preset" class="postform">
								<option value=""><?php esc_html_e( 'Select a template...', 'go-dark' ); ?></option>
								<option value="climate"><?php esc_html_e( 'Global Climate Strike', 'go-dark' ); ?></option>
								<option value="net_neutrality"><?php esc_html_e( 'Defend Net Neutrality', 'go-dark' ); ?></option>
								<option value="censorship"><?php esc_html_e( 'Stop Online Censorship', 'go-dark' ); ?></option>
								<option value="maintenance"><?php esc_html_e( 'Scheduled Maintenance / Blackout', 'go-dark' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Selecting a template will auto-fill the heading, description, color, image, and link options.', 'go-dark' ); ?></p>
						</div>

						<!-- Section: General Settings -->
						<div class="go-dark-card">
							<h3 class="go-dark-card-title"><?php esc_html_e( '1. Activation & Status', 'go-dark' ); ?></h3>
							
							<div class="go-dark-row">
								<label for="go_dark_status"><?php esc_html_e( 'Status Mode', 'go-dark' ); ?></label>
								<select name="go_dark_status" id="go_dark_status">
									<option value="inactive" <?php selected( $status, 'inactive' ); ?>><?php esc_html_e( 'Inactive (Site stays online)', 'go-dark' ); ?></option>
									<option value="scheduled" <?php selected( $status, 'scheduled' ); ?>><?php esc_html_e( 'Scheduled (Dark only during the window)', 'go-dark' ); ?></option>
									<option value="active" <?php selected( $status, 'active' ); ?>><?php esc_html_e( 'Forced Dark (Site goes dark immediately)', 'go-dark' ); ?></option>
								</select>
							</div>

							<div class="go-dark-row-grid">
								<div class="go-dark-row">
									<label for="go_dark_start"><?php esc_html_e( 'Start Window', 'go-dark' ); ?></label>
									<input type="datetime-local" step="1" name="go_dark_start" id="go_dark_start"
										value="<?php echo $start ? esc_attr( wp_date( 'Y-m-d\TH:i:s', $start ) ) : ''; ?>" />
								</div>
								<div class="go-dark-row">
									<label for="go_dark_end"><?php esc_html_e( 'End Window', 'go-dark' ); ?></label>
									<input type="datetime-local" step="1" name="go_dark_end" id="go_dark_end"
										value="<?php echo $end ? esc_attr( wp_date( 'Y-m-d\TH:i:s', $end ) ) : ''; ?>" />
								</div>
							</div>
							
							<p class="description">
								<?php
								printf(
									/* translators: %s: timezone identifier, e.g. "America/New_York" */
									esc_html__( 'All times use your site\'s configured timezone (%s).', 'go-dark' ),
									esc_html( wp_timezone_string() )
								);
								?>
								<strong><?php esc_html_e( 'Current Time:', 'go-dark' ); ?></strong> <?php echo esc_html( wp_date( 'Y/m/d H:i:s' ) ); ?>
							</p>
						</div>

						<!-- Section: Content -->
						<div class="go-dark-card">
							<h3 class="go-dark-card-title"><?php esc_html_e( '2. Protest Content & Info', 'go-dark' ); ?></h3>

							<div class="go-dark-row">
								<label for="go_dark_title"><?php esc_html_e( 'Splash Heading / Title', 'go-dark' ); ?></label>
								<input type="text" name="go_dark_title" id="go_dark_title" value="<?php echo esc_attr( $title ); ?>" />
							</div>

							<div class="go-dark-row">
								<label for="go_dark_text"><?php esc_html_e( 'Protest Description', 'go-dark' ); ?></label>
								<?php
								wp_editor(
									$text,
									'go_dark_text',
									array(
										'textarea_rows' => 6,
									)
								);
								?>
							</div>

							<div class="go-dark-row-grid">
								<div class="go-dark-row">
									<label for="go_dark_link_url"><?php esc_html_e( 'Learn More Link URL', 'go-dark' ); ?></label>
									<input type="text" name="go_dark_link_url" id="go_dark_link_url" value="<?php echo esc_url( $link_url ); ?>" placeholder="https://..." />
								</div>
								<div class="go-dark-row">
									<label for="go_dark_link_text"><?php esc_html_e( 'Link Button Label', 'go-dark' ); ?></label>
									<input type="text" name="go_dark_link_text" id="go_dark_link_text" value="<?php echo esc_attr( $link_text ); ?>" />
								</div>
							</div>
						</div>

						<!-- Section: Appearance -->
						<div class="go-dark-card">
							<h3 class="go-dark-card-title"><?php esc_html_e( '3. Theme & Styling', 'go-dark' ); ?></h3>

							<div class="go-dark-row-grid">
								<div class="go-dark-row">
									<label for="go_dark_theme"><?php esc_html_e( 'Splash Design Theme', 'go-dark' ); ?></label>
									<select name="go_dark_theme" id="go_dark_theme">
										<option value="minimalist" <?php selected( $theme, 'minimalist' ); ?>><?php esc_html_e( 'Minimalist Blackout (Modern Deep Dark)', 'go-dark' ); ?></option>
										<option value="glassmorphism" <?php selected( $theme, 'glassmorphism' ); ?>><?php esc_html_e( 'Glassmorphism Card (Vibrant Backdrop Blur)', 'go-dark' ); ?></option>
										<option value="classic" <?php selected( $theme, 'classic' ); ?>><?php esc_html_e( 'Classic Protest (Legacy Stencil & Wood Grain)', 'go-dark' ); ?></option>
									</select>
								</div>
								<div class="go-dark-row">
									<label for="go_dark_accent_color"><?php esc_html_e( 'Accent / Glow Color', 'go-dark' ); ?></label>
									<input type="color" name="go_dark_accent_color" id="go_dark_accent_color" value="<?php echo esc_attr( $accent_color ); ?>" style="height: 40px; padding: 2px;" />
								</div>
							</div>

							<div class="go-dark-row">
								<label for="go_dark_custom_img_url"><?php esc_html_e( 'Logo or Protest Image', 'go-dark' ); ?></label>
								<div style="display: flex; gap: 10px;">
									<input type="text" name="go_dark_custom_img_url" id="go_dark_custom_img_url" value="<?php echo esc_url( $custom_img ); ?>" placeholder="https://..." style="flex: 1;" />
									<button type="button" id="go_dark_upload_btn" class="button"><?php esc_html_e( 'Select / Upload Image', 'go-dark' ); ?></button>
								</div>
								<p class="description"><?php esc_html_e( 'Select or upload an image from your WordPress Media Library to display on the splash page.', 'go-dark' ); ?></p>
							</div>

							<div class="go-dark-row">
								<label for="go_dark_show_countdown"><?php esc_html_e( 'Display Countdown Timer?', 'go-dark' ); ?></label>
								<select name="go_dark_show_countdown" id="go_dark_show_countdown">
									<option value="yes" <?php selected( $show_countdown, 'yes' ); ?>><?php esc_html_e( 'Yes (Displays time left until end time)', 'go-dark' ); ?></option>
									<option value="no" <?php selected( $show_countdown, 'no' ); ?>><?php esc_html_e( 'No', 'go-dark' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'The countdown only displays if an end window is configured in the future.', 'go-dark' ); ?></p>
							</div>
						</div>

					</div>

					<!-- Sidebar Actions Column -->
					<div class="go-dark-sidebar-column">
						<div class="go-dark-submit-sidebar">
							<div class="go-dark-card">
								<h3 class="go-dark-card-title"><?php esc_html_e( 'Save Settings', 'go-dark' ); ?></h3>
								<p><?php esc_html_e( 'Ensure your dates and content are configured before saving.', 'go-dark' ); ?></p>
								<?php submit_button( __( 'Save Changes', 'go-dark' ), 'primary', 'submit', false ); ?>
								<a href="<?php echo esc_url( home_url( '/?go_dark' ) ); ?>" class="go-dark-preview-link" target="_blank">
									<span class="dashicons dashicons-visibility" style="vertical-align: middle; margin-right: 5px;"></span><?php esc_html_e( 'Preview Splash Page', 'go-dark' ); ?>
								</a>
							</div>
						</div>
					</div>
				</div>
			</form>
		</div>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var presetSelect = document.getElementById('go_dark_preset');
			if (!presetSelect) return;

			var presets = {
				climate: {
					title: "<?php echo esc_js( __( 'Global Climate Strike', 'go-dark' ) ); ?>",
					text: "<?php echo esc_js( __( 'This site has gone dark in solidarity with the Global Climate Strike. We are temporarily offline to draw attention to the climate emergency and demand immediate action from world leaders.', 'go-dark' ) ); ?>",
					link_url: "https://globalclimatestrike.net/",
					link_text: "<?php echo esc_js( __( 'Join the Strike', 'go-dark' ) ); ?>",
					accent_color: "#10b981",
					custom_img_url: ""
				},
				net_neutrality: {
					title: "<?php echo esc_js( __( 'Defend Net Neutrality', 'go-dark' ) ); ?>",
					text: "<?php echo esc_js( __( 'This site has gone dark to protest threats to Net Neutrality and online freedom. The open internet is essential for democracy, free expression, and innovation. Without net neutrality, ISPs can censor content, slow down websites, and charge extra fees.', 'go-dark' ) ); ?>",
					link_url: "https://www.battleforthenet.com/",
					link_text: "<?php echo esc_js( __( 'Fight for the Net', 'go-dark' ) ); ?>",
					accent_color: "#3b82f6",
					custom_img_url: ""
				},
				censorship: {
					title: "<?php echo esc_js( __( 'Stop Online Censorship', 'go-dark' ) ); ?>",
					text: "<?php echo esc_js( __( 'This site has gone dark to protest threats to internet freedom and digital rights. We must protect free expression online and stop censorship.', 'go-dark' ) ); ?>",
					link_url: "https://www.eff.org/",
					link_text: "<?php echo esc_js( __( 'Learn More', 'go-dark' ) ); ?>",
					accent_color: "#ff3333",
					custom_img_url: ""
				},
				maintenance: {
					title: "<?php echo esc_js( __( 'Scheduled Maintenance', 'go-dark' ) ); ?>",
					text: "<?php echo esc_js( __( 'We are currently conducting scheduled system maintenance to improve our site performance and reliability. We apologize for the inconvenience and will be back online shortly.', 'go-dark' ) ); ?>",
					link_url: "",
					link_text: "",
					accent_color: "#f59e0b",
					custom_img_url: ""
				}
			};

			presetSelect.addEventListener('change', function() {
				var key = this.value;
				if (!key || !presets[key]) return;

				if (confirm("<?php echo esc_js( __( 'Are you sure you want to load this preset? This will overwrite your current heading, description, link, accent color, and image settings.', 'go-dark' ) ); ?>")) {
					var data = presets[key];

					document.getElementById('go_dark_title').value = data.title;
					document.getElementById('go_dark_link_url').value = data.link_url;
					document.getElementById('go_dark_link_text').value = data.link_text;
					document.getElementById('go_dark_accent_color').value = data.accent_color;
					document.getElementById('go_dark_custom_img_url').value = data.custom_img_url;

					var textarea = document.getElementById('go_dark_text');
					if (textarea) {
						textarea.value = data.text;
					}
					if (typeof tinymce !== 'undefined') {
						var editor = tinymce.get('go_dark_text');
						if (editor && !editor.isHidden()) {
							editor.setContent(data.text);
						}
					}
				}
				this.value = '';
			});

			// Media Uploader
			var mediaUploader;
			var uploadBtn = document.getElementById('go_dark_upload_btn');
			if (uploadBtn) {
				uploadBtn.addEventListener('click', function(e) {
					e.preventDefault();
					if (mediaUploader) {
						mediaUploader.open();
						return;
					}
					mediaUploader = wp.media({
						title: '<?php echo esc_js( __( 'Select Image', 'go-dark' ) ); ?>',
						button: {
							text: '<?php echo esc_js( __( 'Use Image', 'go-dark' ) ); ?>'
						},
						multiple: false
					});
					mediaUploader.on('select', function() {
						var attachment = mediaUploader.state().get('selection').first().toJSON();
						document.getElementById('go_dark_custom_img_url').value = attachment.url;
					});
					mediaUploader.open();
				});
			}

			// Dynamic input enabling/disabling based on Status Mode
			var statusSelect = document.getElementById('go_dark_status');
			var startInput   = document.getElementById('go_dark_start');
			var endInput     = document.getElementById('go_dark_end');

			function updateInputStates() {
				if (!statusSelect || !startInput || !endInput) return;
				var val = statusSelect.value;
				if ('inactive' === val) {
					startInput.disabled = true;
					endInput.disabled   = true;
					startInput.style.opacity = '0.5';
					endInput.style.opacity   = '0.5';
				} else if ('active' === val) {
					startInput.disabled = true;
					endInput.disabled   = false;
					startInput.style.opacity = '0.5';
					endInput.style.opacity   = '1';
				} else { // scheduled
					startInput.disabled = false;
					endInput.disabled   = false;
					startInput.style.opacity = '1';
					endInput.style.opacity   = '1';
				}
			}

			if (statusSelect) {
				statusSelect.addEventListener('change', updateInputStates);
				updateInputStates();
			}
		});
		</script>
			<?php
		}

		/**
		 * Process the settings form POST.
		 *
		 * @return void
		 */
		public static function catch_post() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have sufficient permissions.', 'go-dark' ) );
			}

			if ( ! isset( $_POST['go_dark_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['go_dark_nonce'] ) ), 'go_dark_settings' ) ) {
				wp_die( esc_html__( 'Nonce verification failed.', 'go-dark' ) );
			}

			if ( isset( $_POST['go_dark_status'] ) ) {
				$status = sanitize_text_field( wp_unslash( $_POST['go_dark_status'] ) );
				if ( in_array( $status, array( 'inactive', 'scheduled', 'active' ), true ) ) {
					update_option( 'go_dark_status', $status );
				}
			}

			if ( isset( $_POST['go_dark_start'] ) ) {
				$tz  = wp_timezone();
				$val = sanitize_text_field( wp_unslash( $_POST['go_dark_start'] ) );
				if ( empty( $val ) ) {
					update_option( 'go_dark_start', time() );
				} else {
					$dt = date_create( $val, $tz );
					if ( $dt ) {
						update_option( 'go_dark_start', $dt->getTimestamp() );
					}
				}
			}

			if ( isset( $_POST['go_dark_end'] ) ) {
				$tz  = wp_timezone();
				$val = sanitize_text_field( wp_unslash( $_POST['go_dark_end'] ) );
				if ( empty( $val ) ) {
					update_option( 'go_dark_end', 0 );
				} else {
					$dt = date_create( $val, $tz );
					if ( $dt ) {
						update_option( 'go_dark_end', $dt->getTimestamp() );
					}
				}
			}

			if ( isset( $_POST['go_dark_title'] ) ) {
				update_option( 'go_dark_title', sanitize_text_field( wp_unslash( $_POST['go_dark_title'] ) ) );
			}

			if ( isset( $_POST['go_dark_text'] ) ) {
				update_option( 'go_dark_text', wp_kses( wp_unslash( $_POST['go_dark_text'] ), self::get_allowed_tags() ) );
			}

			if ( isset( $_POST['go_dark_theme'] ) ) {
				$theme = sanitize_text_field( wp_unslash( $_POST['go_dark_theme'] ) );
				if ( in_array( $theme, array( 'minimalist', 'glassmorphism', 'classic' ), true ) ) {
					update_option( 'go_dark_theme', $theme );
				}
			}

			if ( isset( $_POST['go_dark_accent_color'] ) ) {
				$color = sanitize_hex_color( wp_unslash( $_POST['go_dark_accent_color'] ) );
				if ( $color ) {
					update_option( 'go_dark_accent_color', $color );
				}
			}

			if ( isset( $_POST['go_dark_custom_img_url'] ) ) {
				update_option( 'go_dark_custom_img_url', esc_url_raw( wp_unslash( $_POST['go_dark_custom_img_url'] ) ) );
			}

			if ( isset( $_POST['go_dark_show_countdown'] ) ) {
				$countdown = sanitize_text_field( wp_unslash( $_POST['go_dark_show_countdown'] ) );
				if ( in_array( $countdown, array( 'yes', 'no' ), true ) ) {
					update_option( 'go_dark_show_countdown', $countdown );
				}
			}

			if ( isset( $_POST['go_dark_link_url'] ) ) {
				update_option( 'go_dark_link_url', esc_url_raw( wp_unslash( $_POST['go_dark_link_url'] ) ) );
			}

			if ( isset( $_POST['go_dark_link_text'] ) ) {
				update_option( 'go_dark_link_text', sanitize_text_field( wp_unslash( $_POST['go_dark_link_text'] ) ) );
			}
		}

		/**
		 * Output the go-dark splash page and exit.
		 *
		 * @return void
		 */
		public static function show_page() {
			$theme   = self::get_theme();
			$is_dark = self::is_dark();

			if ( ! headers_sent() ) {
				status_header( 503 );
				if ( $is_dark ) {
					$time_left = self::get_end() - time();
					if ( $time_left > 0 ) {
						header( 'Retry-After: ' . $time_left );
					}
				}
			}

			$font_url = '';
			if ( 'minimalist' === $theme ) {
				$font_url = 'https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;700&family=Inter:wght@400;600&display=swap';
			} elseif ( 'glassmorphism' === $theme ) {
				$font_url = 'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&family=Inter:wght@400;600&display=swap';
			} else {
				$font_url = 'https://fonts.googleapis.com/css2?family=Special+Elite&display=swap';
			}
			?>
		<!doctype html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php echo esc_html( self::get_title() ); ?></title>
			<link rel="preconnect" href="https://fonts.googleapis.com">
			<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
			<link href="<?php echo esc_url( $font_url ); ?>" rel="stylesheet" type="text/css"> <?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Custom standalone 503 page; wp_head() is not called. ?>
			<style>
				:root {
					--accent-color: <?php echo esc_html( self::get_accent_color() ); ?>;
					--accent-rgb: <?php echo esc_html( implode( ',', self::hex2rgb( self::get_accent_color() ) ) ); ?>;
				}
				* { margin:0; padding:0; box-sizing: border-box; }
				html, body { min-height: 100vh; width: 100%; overflow-x: hidden; }
				body {
					display: flex;
					align-items: center;
					justify-content: center;
					background-color: #09090b;
					color: #f4f4f5;
					line-height: 1.6;
				}

				/* THEME 1: Minimalist Blackout */
				.theme-minimalist {
					font-family: 'Inter', sans-serif;
					background-color: #09090b;
					background-image: radial-gradient(circle at center, rgba(var(--accent-rgb), 0.08) 0%, rgba(9, 9, 11, 0) 70%);
					width: 100%;
					min-height: 100vh;
					display: flex;
					flex-direction: column;
					justify-content: center;
					align-items: center;
					padding: 2rem;
					text-align: center;
					position: relative;
				}
				.theme-minimalist::before {
					content: '';
					position: absolute;
					top: 0; left: 0; right: 0; height: 4px;
					background: var(--accent-color);
					box-shadow: 0 2px 20px rgba(var(--accent-rgb), 0.6);
				}
				.theme-minimalist .content-container { max-width: 650px; margin: 0 auto; }
				.theme-minimalist h1 {
					font-family: 'Space Grotesk', sans-serif;
					font-size: 3rem;
					font-weight: 700;
					letter-spacing: -0.025em;
					line-height: 1.1;
					margin-bottom: 1.5rem;
					color: #ffffff;
				}
				.theme-minimalist .description { font-size: 1.125rem; color: #a1a1aa; margin-bottom: 2.5rem; }
				.theme-minimalist .description a {
					color: var(--accent-color);
					text-decoration: none;
					border-bottom: 1px dashed rgba(var(--accent-rgb), 0.6);
					transition: all 0.2s ease;
				}
				.theme-minimalist .description a:hover { color: #ffffff; border-bottom-color: #ffffff; }

				/* THEME 2: Glassmorphism Alert */
				.theme-glassmorphism {
					font-family: 'Inter', sans-serif;
					background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
					width: 100%;
					min-height: 100vh;
					display: flex;
					flex-direction: column;
					justify-content: center;
					align-items: center;
					padding: 2rem;
					position: relative;
					overflow: hidden;
				}
				.theme-glassmorphism::before,
				.theme-glassmorphism::after {
					content: '';
					position: absolute;
					border-radius: 50%;
					filter: blur(120px);
					opacity: 0.15;
					z-index: 0;
				}
				.theme-glassmorphism::before { width: 350px; height: 350px; top: 15%; left: 10%; background-color: var(--accent-color); }
				.theme-glassmorphism::after { width: 300px; height: 300px; bottom: 15%; right: 10%; background-color: #4f46e5; }
				.theme-glassmorphism .content-container {
					max-width: 600px;
					width: 100%;
					background: rgba(255, 255, 255, 0.02);
					border: 1px solid rgba(255, 255, 255, 0.08);
					backdrop-filter: blur(20px);
					-webkit-backdrop-filter: blur(20px);
					border-radius: 24px;
					padding: 3.5rem 2.5rem;
					box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6);
					text-align: center;
					z-index: 1;
				}
				.theme-glassmorphism h1 {
					font-family: 'Outfit', sans-serif;
					font-size: 2.75rem;
					font-weight: 800;
					line-height: 1.15;
					margin-bottom: 1.5rem;
					background: linear-gradient(180deg, #ffffff 0%, #cbd5e1 100%);
					-webkit-background-clip: text;
					-webkit-text-fill-color: transparent;
				}
				.theme-glassmorphism .description { font-size: 1.05rem; color: #94a3b8; margin-bottom: 2.5rem; }
				.theme-glassmorphism .description a { color: var(--accent-color); text-decoration: none; font-weight: 600; }
				.theme-glassmorphism .description a:hover { opacity: 0.8; }

				/* THEME 3: Classic Protest */
				.theme-classic {
					font-family: 'Special Elite', monospace;
					background: #111 url('<?php echo esc_url( plugins_url( 'wood.jpg', __FILE__ ) ); ?>') 50% 50% repeat;
					width: 100%;
					min-height: 100vh;
					display: flex;
					flex-direction: column;
					justify-content: center;
					align-items: center;
					padding: 2rem;
					text-align: center;
					text-shadow: 1px 1px 0 #222;
					color: #eee;
				}
				.theme-classic .content-container {
					max-width: 700px;
					margin: 0 auto;
					padding: 2.5rem 2rem;
					border: 4px double #444;
					background: rgba(0, 0, 0, 0.85);
					border-radius: 4px;
				}
				.theme-classic h1 { font-size: 2.5rem; margin-bottom: 1.5rem; text-transform: uppercase; color: var(--accent-color); }
				.theme-classic .description { font-size: 1.25rem; margin-bottom: 2rem; }
				.theme-classic .description a { color: #fff; text-decoration: underline; }
				.theme-classic .description a:hover { color: var(--accent-color); }

				/* Badges & Logos */
				.custom-img { max-width: 180px; max-height: 180px; width: auto; height: auto; margin-bottom: 1.5rem; border-radius: 12px; }

				/* Action Button */
				.btn-action {
					display: inline-block;
					background-color: var(--accent-color);
					color: #ffffff !important;
					text-decoration: none !important;
					padding: 0.875rem 2rem;
					border-radius: 9999px;
					font-weight: 600;
					letter-spacing: -0.01em;
					box-shadow: 0 4px 14px rgba(var(--accent-rgb), 0.4);
					transition: all 0.2s ease;
					border: none;
					margin-top: 1.5rem;
				}
				.btn-action:hover {
					transform: translateY(-2px);
					box-shadow: 0 6px 20px rgba(var(--accent-rgb), 0.6);
				}

				/* Countdown Timer */
				.countdown-wrapper { margin: 2rem 0; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; }
				.countdown-label { font-size: 0.875rem; text-transform: uppercase; letter-spacing: 0.1em; color: #71717a; }
				.theme-glassmorphism .countdown-label { color: #64748b; }
				.theme-classic .countdown-label { color: #aaa; font-size: 1rem; }
				
				.countdown-timer { display: flex; gap: 1rem; justify-content: center; }
				.time-part { display: flex; flex-direction: column; align-items: center; min-width: 4.5rem; }
				.theme-classic .time-part { min-width: 3.5rem; }
				
				.time-part .number { font-size: 2.5rem; font-weight: 700; color: #ffffff; line-height: 1; }
				.theme-minimalist .time-part .number { font-family: 'Space Grotesk', sans-serif; }
				.theme-glassmorphism .time-part .number {
					font-family: 'Outfit', sans-serif;
					background: linear-gradient(180deg, #ffffff 0%, #cbd5e1 100%);
					-webkit-background-clip: text;
					-webkit-text-fill-color: transparent;
				}
				.theme-classic .time-part .number { font-size: 2.2rem; color: var(--accent-color); }

				.time-part .label { font-size: 0.75rem; text-transform: uppercase; color: #a1a1aa; margin-top: 0.25rem; }
				.theme-glassmorphism .time-part .label { color: #94a3b8; }
				.theme-classic .time-part .label { color: #eee; font-size: 0.85rem; }

				@keyframes pulse-glow {
					0%, 100% { transform: scale(1); opacity: 0.95; }
					50% { transform: scale(1.03); opacity: 1; }
				}

				@media (max-width: 640px) {
					.theme-minimalist h1 { font-size: 2.25rem; }
					.theme-glassmorphism h1 { font-size: 2rem; }
					.theme-glassmorphism .content-container { padding: 2.5rem 1.5rem; }
					.time-part .number { font-size: 1.85rem; }
					.time-part { min-width: 3rem; }
				}
			</style>
		</head>
		<body>
			<?php
			$custom_img        = self::get_custom_img_url();
			$link_url          = self::get_link_url();
			$link_text         = self::get_link_text();
			$show_countdown    = self::get_show_countdown();
			$end_time          = self::get_end();
			$display_countdown = ( 'yes' === $show_countdown && $end_time > time() && $is_dark );
			?>
			<main class="theme-<?php echo esc_attr( $theme ); ?>">
				<div class="content-container">
					<?php
					if ( ! empty( $custom_img ) ) {
						echo '<img class="custom-img" src="' . esc_url( $custom_img ) . '" alt="' . esc_attr( self::get_title() ) . '" />';
					}
					?>
					
					<h1><?php echo esc_html( self::get_title() ); ?></h1>
					
					<div class="description">
						<?php echo wp_kses( wpautop( self::get_text() ), self::get_allowed_tags() ); ?>
					</div>
					
					<?php if ( $display_countdown ) : ?>
						<div class="countdown-wrapper">
							<div class="countdown-label"><?php esc_html_e( 'Estimated Time Remaining', 'go-dark' ); ?></div>
							<div id="countdown" class="countdown-timer" data-endtime="<?php echo esc_attr( $end_time ); ?>">
								<!-- Filled by JavaScript -->
							</div>
						</div>
					<?php endif; ?>
					
					<?php if ( ! empty( $link_url ) && ! empty( $link_text ) ) : ?>
						<a href="<?php echo esc_url( $link_url ); ?>" class="btn-action" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html( $link_text ); ?>
						</a>
					<?php endif; ?>
				</div>
			</main>

			<?php if ( $display_countdown ) : ?>
				<script>
				document.addEventListener('DOMContentLoaded', function() {
					var countdownEl = document.getElementById('countdown');
					if (!countdownEl) return;
					
					var endTime = parseInt(countdownEl.getAttribute('data-endtime'), 10) * 1000;
					
					function updateCountdown() {
						var now = new Date().getTime();
						var distance = endTime - now;
						
						if (distance < 0) {
							countdownEl.innerHTML = '<span style="font-size: 1.1rem; color: #a1a1aa;"><?php esc_html_e( 'Returning shortly...', 'go-dark' ); ?></span>';
							setTimeout(function() {
								window.location.reload();
							}, 5000);
							return;
						}
						
						var days = Math.floor(distance / (1000 * 60 * 60 * 24));
						var hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
						var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
						var seconds = Math.floor((distance % (1000 * 60)) / 1000);
						
						var html = '';
						if (days > 0) {
							html += '<div class="time-part"><span class="number">' + days + '</span><span class="label"><?php esc_html_e( 'days', 'go-dark' ); ?></span></div>';
						}
						html += '<div class="time-part"><span class="number">' + (hours < 10 ? '0' + hours : hours) + '</span><span class="label"><?php esc_html_e( 'hours', 'go-dark' ); ?></span></div>';
						html += '<div class="time-part"><span class="number">' + (minutes < 10 ? '0' + minutes : minutes) + '</span><span class="label"><?php esc_html_e( 'mins', 'go-dark' ); ?></span></div>';
						html += '<div class="time-part"><span class="number">' + (seconds < 10 ? '0' + seconds : seconds) + '</span><span class="label"><?php esc_html_e( 'secs', 'go-dark' ); ?></span></div>';
						
						countdownEl.innerHTML = html;
					}
					
					updateCountdown();
					setInterval(updateCountdown, 1000);
				});
				</script>
			<?php endif; ?>
		</body>
		</html>
			<?php
			exit;
		}

		/**
		 * Convert hex color to RGB array.
		 *
		 * @param string $hex Hex color code.
		 * @return array{int, int, int}
		 */
		public static function hex2rgb( $hex ) {
			$hex = str_replace( '#', '', $hex );
			if ( 3 === strlen( $hex ) ) {
				$r = hexdec( substr( $hex, 0, 1 ) . substr( $hex, 0, 1 ) );
				$g = hexdec( substr( $hex, 1, 1 ) . substr( $hex, 1, 1 ) );
				$b = hexdec( substr( $hex, 2, 1 ) . substr( $hex, 2, 1 ) );
			} else {
				$r = hexdec( substr( $hex, 0, 2 ) );
				$g = hexdec( substr( $hex, 2, 2 ) );
				$b = hexdec( substr( $hex, 4, 2 ) );
			}
			return array( (int) $r, (int) $g, (int) $b );
		}

		/**
		 * Return the wp_kses allowed-tags array including iframe.
		 *
		 * @return array<string, array<string, bool>>
		 */
		public static function get_allowed_tags() {
			$allowed           = wp_kses_allowed_html( 'post' );
			$allowed['iframe'] = array(
				'src'                   => true,
				'width'                 => true,
				'height'                => true,
				'frameborder'           => true,
				'webkitallowfullscreen' => true,
				'mozallowfullscreen'    => true,
				'allowfullscreen'       => true,
			);
			return $allowed;
		}

		/**
		 * Return the default splash page status.
		 *
		 * @return string
		 */
		public static function get_status() {
			return get_option( 'go_dark_status', 'inactive' );
		}

		/**
		 * Return the default splash page title.
		 *
		 * @return string
		 */
		public static function get_default_title() {
			return __( 'Site Temporarily Offline', 'go-dark' );
		}

		/**
		 * Return the default splash page text.
		 *
		 * @return string
		 */
		public static function get_default_text() {
			return sprintf(
				/* translators: 1: site name */
				__( '%1$s has gone dark to stand in protest.', 'go-dark' ),
				get_bloginfo( 'name' )
			);
		}

		/**
		 * Check whether the site is currently in the go-dark window.
		 *
		 * @return bool
		 */
		public static function is_dark() {
			$status = self::get_status();
			if ( 'inactive' === $status ) {
				return false;
			}

			$now = time();
			$end = self::get_end();

			if ( 'active' === $status ) {
				// Forced dark ends when the end window time has passed.
				if ( $end > 0 && $now >= $end ) {
					return false;
				}
				return true;
			}

			// Otherwise 'scheduled'.
			return ( $now >= self::get_start() && $now < $end );
		}

		/**
		 * Return the go-dark start timestamp (UTC).
		 *
		 * @return int
		 */
		public static function get_start() {
			return (int) get_option( 'go_dark_start', self::get_default_start() );
		}

		/**
		 * Return the go-dark end timestamp (UTC).
		 *
		 * @return int
		 */
		public static function get_end() {
			return (int) get_option( 'go_dark_end', self::get_default_end() );
		}

		/**
		 * Return the default go-dark start timestamp.
		 *
		 * @return int
		 */
		public static function get_default_start() {
			return time();
		}

		/**
		 * Return the default go-dark end timestamp.
		 *
		 * @return int
		 */
		public static function get_default_end() {
			return time() + 86400; // 24 hours.
		}

		/**
		 * Return the date/time format string for display.
		 *
		 * @return string
		 */
		public static function get_timedate_format() {
			return get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		}

		/**
		 * Return the splash page title.
		 *
		 * @return string
		 */
		public static function get_title() {
			return get_option( 'go_dark_title', self::get_default_title() );
		}

		/**
		 * Return the splash page description.
		 *
		 * @return string
		 */
		public static function get_text() {
			return get_option( 'go_dark_text', self::get_default_text() );
		}

		/**
		 * Return the design theme.
		 *
		 * @return string
		 */
		public static function get_theme() {
			return get_option( 'go_dark_theme', 'minimalist' );
		}

		/**
		 * Return the accent color.
		 *
		 * @return string
		 */
		public static function get_accent_color() {
			return get_option( 'go_dark_accent_color', '#ff3333' );
		}

		/**
		 * Return the custom logo/image URL.
		 *
		 * @return string
		 */
		public static function get_custom_img_url() {
			return get_option( 'go_dark_custom_img_url', '' );
		}

		/**
		 * Return whether to show the countdown timer.
		 *
		 * @return string
		 */
		public static function get_show_countdown() {
			return get_option( 'go_dark_show_countdown', 'yes' );
		}

		/**
		 * Return the action link URL.
		 *
		 * @return string
		 */
		public static function get_link_url() {
			return get_option( 'go_dark_link_url', '' );
		}

		/**
		 * Return the action link text.
		 *
		 * @return string
		 */
		public static function get_link_text() {
			return get_option( 'go_dark_link_text', __( 'Learn More', 'go-dark' ) );
		}
	}

	go_dark::go();

endif;
