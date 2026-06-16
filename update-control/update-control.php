<?php
/**
 * Plugin Name: Update Control
 * Plugin URI: http://github.com/chipbennett/update-control/
 * Description: Adds a manual toggle to the WordPress Admin Interface for managing auto-updates.
 * Author: George Stephanis, Chip Bennett
 * Version: 1.5.1
 * Author URI: http://chipbennett.net
 * Text Domain: update-control
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package StephanisUpdateControl
 */

namespace Stephanis\UpdateControl;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Update Control Class
 */
class Update_Control {

	/**
	 * Enqueue admin script and stylesheets for General settings page.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public static function enqueue_admin_scripts( $hook ) {
		if ( is_multisite() && ! is_main_site() ) {
			return;
		}

		if ( 'options-general.php' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'update-control-admin',
			plugins_url( 'update-control.js', __FILE__ ),
			array( 'jquery' ),
			'1.5.1',
			true
		);

		wp_enqueue_style(
			'update-control-admin',
			plugins_url( 'update-control.css', __FILE__ ),
			array(),
			'1.5.1'
		);
	}

	/**
	 * Register automatic updates core/plugins/themes filters based on saved options.
	 */
	public static function setup_upgrade_filters() {
		if ( is_multisite() && ! is_main_site() ) {
			return;
		}

		$options = self::get_options();

		// Do these at priority 1, so other folks can easily override it.

		if ( 'no' === $options['active'] ) {

			add_filter( 'automatic_updater_disabled', '__return_true', 1 );

		} else {

			if ( 'dev' === $options['core'] ) {
				add_filter( 'allow_dev_auto_core_updates', '__return_true', 1 );
				add_filter( 'allow_major_auto_core_updates', '__return_true', 1 );
				add_filter( 'allow_minor_auto_core_updates', '__return_true', 1 );
			} elseif ( 'major' === $options['core'] ) {
				add_filter( 'allow_dev_auto_core_updates', '__return_false', 1 );
				add_filter( 'allow_major_auto_core_updates', '__return_true', 1 );
				add_filter( 'allow_minor_auto_core_updates', '__return_true', 1 );
			} else {
				add_filter( 'allow_dev_auto_core_updates', '__return_false', 1 );
				add_filter( 'allow_major_auto_core_updates', '__return_false', 1 );
				add_filter( 'allow_minor_auto_core_updates', '__return_true', 1 );
			}

			if ( 'yes' === $options['plugin'] ) {
				add_filter( 'auto_update_plugin', '__return_true', 1 );
				add_filter( 'plugins_auto_update_enabled', '__return_false', 1 );
			} elseif ( 'no' === $options['plugin'] ) {
				add_filter( 'auto_update_plugin', '__return_false', 1 );
				add_filter( 'plugins_auto_update_enabled', '__return_false', 1 );
			}

			if ( 'yes' === $options['theme'] ) {
				add_filter( 'auto_update_theme', '__return_true', 1 );
				add_filter( 'themes_auto_update_enabled', '__return_false', 1 );
			} elseif ( 'no' === $options['theme'] ) {
				add_filter( 'auto_update_theme', '__return_false', 1 );
				add_filter( 'themes_auto_update_enabled', '__return_false', 1 );
			}

			if ( ! $options['translation'] ) {
				add_filter( 'auto_update_translation', '__return_false', 1 );
			}

			if ( $options['vcscheck'] ) {
				add_filter( 'automatic_updates_is_vcs_checkout', '__return_false', 1 );
			}

			if ( 'no' === $options['emailactive'] || ! ( $options['successemail'] || $options['failureemail'] || $options['criticalemail'] ) ) {
				add_filter( 'auto_core_update_send_email', '__return_false', 1 );
				add_filter( 'auto_plugin_update_send_email', '__return_false', 1 );
				add_filter( 'auto_theme_update_send_email', '__return_false', 1 );
			} else {
				add_filter( 'auto_core_update_send_email', array( __CLASS__, 'filter_email' ), 1, 2 );
				add_filter( 'auto_plugin_update_send_email', array( __CLASS__, 'filter_plugin_theme_email' ), 1, 2 );
				add_filter( 'auto_theme_update_send_email', array( __CLASS__, 'filter_plugin_theme_email' ), 1, 2 );
			}

			if ( $options['debugemail'] ) {
				add_filter( 'automatic_updates_send_debug_email', '__return_false', 1 );
			}

			if ( ! empty( $options['notification_email'] ) ) {
				add_filter( 'auto_core_update_email', array( __CLASS__, 'filter_email_recipient' ), 10, 1 );
				add_filter( 'auto_plugin_theme_update_email', array( __CLASS__, 'filter_email_recipient' ), 10, 1 );
			}
		}
	}

	/**
	 * Filters whether to send update emails based on the update status type.
	 *
	 * @param bool   $should_send Whether to send the email.
	 * @param string $type        The email type (e.g. success, fail, critical).
	 * @return bool True if emails should be sent, false otherwise.
	 */
	public static function filter_email( $should_send, $type ) {
		$options = self::get_options();

		if ( 'success' === $type && ! $options['successemail'] ) {
			return false;
		}

		if ( 'fail' === $type && ! $options['failureemail'] ) {
			return false;
		}

		if ( 'critical' === $type && ! $options['criticalemail'] ) {
			return false;
		}
		return $should_send;
	}

	/**
	 * Filters whether to send plugin/theme update emails based on the update results.
	 *
	 * @param bool  $should_send    Whether to send the email.
	 * @param array $update_results Array of update results.
	 * @return bool True if emails should be sent, false otherwise.
	 */
	public static function filter_plugin_theme_email( $should_send, $update_results ) {
		$options = self::get_options();

		// If email notification is globally disabled, do not send.
		if ( 'no' === $options['emailactive'] ) {
			return false;
		}

		$has_success = false;
		$has_failure = false;

		if ( is_array( $update_results ) ) {
			foreach ( $update_results as $result_obj ) {
				if ( isset( $result_obj->result ) ) {
					if ( true === $result_obj->result ) {
						$has_success = true;
					} else {
						$has_failure = true;
					}
				}
			}
		}

		// If we only have successes, check if success emails are disabled.
		if ( $has_success && ! $has_failure && ! $options['successemail'] ) {
			return false;
		}

		// If we only have failures, check if failure and critical emails are disabled.
		if ( $has_failure && ! $has_success && ! $options['failureemail'] && ! $options['criticalemail'] ) {
			return false;
		}

		// If we have both successes and failures.
		if ( $has_success && $has_failure ) {
			// If both success emails and failure/critical emails are disabled, then don't send.
			if ( ! $options['successemail'] && ! $options['failureemail'] && ! $options['criticalemail'] ) {
				return false;
			}
		}

		return $should_send;
	}

	/**
	 * Retrieve saved plugin options combined with defaults.
	 *
	 * @return array The option array.
	 */
	public static function get_options() {
		$defaults = array(
			'active'             => 'yes',
			'core'               => 'minor',
			'plugin'             => 'core',
			'theme'              => 'core',
			'translation'        => true,
			'toggleadvanced'     => 'hide',
			'vcscheck'           => false,
			'emailactive'        => 'yes',
			'successemail'       => true,
			'failureemail'       => true,
			'criticalemail'      => true,
			'debugemail'         => false,
			'notification_email' => '',
		);
		$args     = get_option( 'update_control_options', array() );

		// Migrate old boolean values to the new string format.
		if ( isset( $args['plugin'] ) ) {
			if ( true === $args['plugin'] || '1' === $args['plugin'] || 'yes' === $args['plugin'] ) {
				$args['plugin'] = 'yes';
			} elseif ( false === $args['plugin'] || '0' === $args['plugin'] || '' === $args['plugin'] ) {
				$args['plugin'] = 'core';
			}
		}
		if ( isset( $args['theme'] ) ) {
			if ( true === $args['theme'] || '1' === $args['theme'] || 'yes' === $args['theme'] ) {
				$args['theme'] = 'yes';
			} elseif ( false === $args['theme'] || '0' === $args['theme'] || '' === $args['theme'] ) {
				$args['theme'] = 'core';
			}
		}

		return wp_parse_args( $args, $defaults );
	}

	/**
	 * Retrieve a single option value by key.
	 *
	 * @param string $key Option key.
	 * @return mixed Option value or null.
	 */
	public static function get_option( $key ) {
		$options = self::get_options();
		if ( isset( $options[ $key ] ) ) {
			return $options[ $key ];
		}
		return null;
	}

	/**
	 * Register plugin settings option group and add settings fields.
	 */
	public static function register_settings() {
		if ( is_multisite() && ! is_main_site() ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		add_settings_section(
			'update-control',
			esc_html__( 'Automatic Updates', 'update-control' ),
			array( __CLASS__, 'update_control_settings_section' ),
			'general'
		);

		if ( defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED ) {
			return;
		}

		add_settings_field(
			'update_control_active',
			sprintf( '<label for="update_control_active">%1$s</label>', esc_html__( 'Automatic Updates Enabled?', 'update-control' ) ),
			array( __CLASS__, 'update_control_active_cb' ),
			'general',
			'update-control'
		);

		add_settings_field(
			'update_control_core',
			esc_html__( 'Automatic Core Update Level?', 'update-control' ),
			array( __CLASS__, 'update_control_core_cb' ),
			'general',
			'update-control'
		);

		add_settings_field(
			'update_control_plugin',
			esc_html__( 'Automatic Plugin Updates', 'update-control' ),
			array( __CLASS__, 'update_control_plugin_cb' ),
			'general',
			'update-control'
		);

		add_settings_field(
			'update_control_theme',
			esc_html__( 'Automatic Theme Updates', 'update-control' ),
			array( __CLASS__, 'update_control_theme_cb' ),
			'general',
			'update-control'
		);

		add_settings_field(
			'update_control_translation',
			sprintf( '<label for="update_control_translation">%1$s</label>', esc_html__( 'Permit Automatic Translation Updates?', 'update-control' ) ),
			array( __CLASS__, 'update_control_translation_cb' ),
			'general',
			'update-control'
		);

		add_settings_field(
			'update_control_toggleadvanced',
			esc_html__( 'Advanced Settings', 'update-control' ),
			array( __CLASS__, 'update_control_toggleadvanced_cb' ),
			'general',
			'update-control'
		);

		add_settings_field(
			'update_control_vcscheck',
			sprintf( '<label for="update_control_vcscheck">%1$s</label>', esc_html__( 'Enable updates for VCS installations?', 'update-control' ) ),
			array( __CLASS__, 'update_control_vcscheck_cb' ),
			'general',
			'update-control'
		);

		add_settings_field(
			'update_control_email_active',
			sprintf( '<label for="update_control_email_active">%1$s</label>', esc_html__( 'Update Emails Enabled?', 'update-control' ) ),
			array( __CLASS__, 'update_control_email_active_cb' ),
			'general',
			'update-control'
		);

		add_settings_field(
			'update_control_email_recipient',
			sprintf( '<label for="update_control_email_recipient">%1$s</label>', esc_html__( 'Notification Email Address', 'update-control' ) ),
			array( __CLASS__, 'update_control_email_recipient_cb' ),
			'general',
			'update-control'
		);

		add_settings_field(
			'update_control_email_success',
			sprintf( '<label for="update_control_email_success">%1$s</label>', esc_html__( 'Send Emails for Successful Updates?', 'update-control' ) ),
			array( __CLASS__, 'update_control_email_success_cb' ),
			'general',
			'update-control'
		);

		add_settings_field(
			'update_control_email_failure',
			sprintf( '<label for="update_control_email_failure">%1$s</label>', esc_html__( 'Send Emails for Failed Updates?', 'update-control' ) ),
			array( __CLASS__, 'update_control_email_failure_cb' ),
			'general',
			'update-control'
		);

		add_settings_field(
			'update_control_email_critical',
			sprintf( '<label for="update_control_email_critical">%1$s</label>', esc_html__( 'Send Emails for Critically Failed Updates?', 'update-control' ) ),
			array( __CLASS__, 'update_control_email_critical_cb' ),
			'general',
			'update-control'
		);

		$wp_version = $GLOBALS['wp_version'];
		if ( false !== strpos( $wp_version, '-' ) ) {
			add_settings_field(
				'update_control_email_debug',
				sprintf( '<label for="update_control_email_debug">%1$s</label>', esc_html__( 'Disable Debug Emails for Development Versions?', 'update-control' ) ),
				array( __CLASS__, 'update_control_email_debug_cb' ),
				'general',
				'update-control'
			);
		}

		register_setting( 'general', 'update_control_options', array( __CLASS__, 'sanitize_options' ) );
	}

	/**
	 * Output descriptions and instructions for the settings section.
	 */
	public static function update_control_settings_section() {
		if ( defined( 'AUTOMATIC_UPDATER_DISABLED' ) && AUTOMATIC_UPDATER_DISABLED ) : ?>
			<p id="update-control-settings-section">
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: PHP constant name */
						__( 'You have the %s constant set. Automatic updates are disabled.', 'update-control' ),
						'<code>AUTOMATIC_UPDATER_DISABLED</code>'
					),
					array( 'code' => array() )
				);
				?>
			</p>
		<?php else : ?>
			<p id="update-control-settings-section">
				<?php esc_html_e( 'This section lets you specify what areas of your WordPress install will be permitted to auto-update.', 'update-control' ); ?>
			</p>
			<?php
		endif;
	}

	/**
	 * Output markup for 'Automatic Updates Enabled?' field.
	 */
	public static function update_control_active_cb() {
		?>
		<select id="update_control_active" name="update_control_options[active]">
			<option <?php selected( 'yes' === self::get_option( 'active' ) ); ?> value="yes"><?php esc_html_e( 'Yes', 'update-control' ); ?></option>
			<option <?php selected( 'no' === self::get_option( 'active' ) ); ?> value="no"><?php esc_html_e( 'No', 'update-control' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Output markup for 'Automatic Core Update Level?' field.
	 */
	public static function update_control_core_cb() {
		$val = self::get_option( 'core' );
		?>
		<fieldset>
			<legend class="screen-reader-text"><span><?php esc_html_e( 'Automatic Core Update Level?', 'update-control' ); ?></span></legend>
			<label><input type="radio" class="update_control_dependency" id="update_control_core_minor" name="update_control_options[core]" value="minor" <?php checked( 'minor' === $val ); ?> /> <?php esc_html_e( 'Minor Updates', 'update-control' ); ?></label><br />
			<label><input type="radio" class="update_control_dependency" id="update_control_core_major" name="update_control_options[core]" value="major" <?php checked( 'major' === $val ); ?> /> <?php esc_html_e( 'Major Updates', 'update-control' ); ?></label><br />
			<label><input type="radio" class="update_control_dependency" id="update_control_core_dev" name="update_control_options[core]" value="dev" <?php checked( 'dev' === $val ); ?> /> <?php esc_html_e( 'Development Updates', 'update-control' ); ?></label>
		</fieldset>
		<?php
	}

	/**
	 * Output markup for 'Automatic Plugin Updates' field.
	 */
	public static function update_control_plugin_cb() {
		$val = self::get_option( 'plugin' );
		?>
		<fieldset>
			<legend class="screen-reader-text"><span><?php esc_html_e( 'Automatic Plugin Updates', 'update-control' ); ?></span></legend>
			<label><input type="radio" class="update_control_dependency" id="update_control_plugin_yes" name="update_control_options[plugin]" value="yes" <?php checked( 'yes' === $val ); ?> /> <?php esc_html_e( 'Auto-update all plugins', 'update-control' ); ?></label><br />
			<label><input type="radio" class="update_control_dependency" id="update_control_plugin_no" name="update_control_options[plugin]" value="no" <?php checked( 'no' === $val ); ?> /> <?php esc_html_e( 'Disable all plugin auto-updates', 'update-control' ); ?></label><br />
			<label><input type="radio" class="update_control_dependency" id="update_control_plugin_core" name="update_control_options[plugin]" value="core" <?php checked( 'core' === $val ); ?> /> <?php esc_html_e( 'Choose individually (WordPress default)', 'update-control' ); ?></label>
		</fieldset>
		<?php
	}

	/**
	 * Output markup for 'Automatic Theme Updates' field.
	 */
	public static function update_control_theme_cb() {
		$val = self::get_option( 'theme' );
		?>
		<fieldset>
			<legend class="screen-reader-text"><span><?php esc_html_e( 'Automatic Theme Updates', 'update-control' ); ?></span></legend>
			<label><input type="radio" class="update_control_dependency" id="update_control_theme_yes" name="update_control_options[theme]" value="yes" <?php checked( 'yes' === $val ); ?> /> <?php esc_html_e( 'Auto-update all themes', 'update-control' ); ?></label><br />
			<label><input type="radio" class="update_control_dependency" id="update_control_theme_no" name="update_control_options[theme]" value="no" <?php checked( 'no' === $val ); ?> /> <?php esc_html_e( 'Disable all theme auto-updates', 'update-control' ); ?></label><br />
			<label><input type="radio" class="update_control_dependency" id="update_control_theme_core" name="update_control_options[theme]" value="core" <?php checked( 'core' === $val ); ?> /> <?php esc_html_e( 'Choose individually (WordPress default)', 'update-control' ); ?></label>
		</fieldset>
		<?php
	}

	/**
	 * Output markup for 'Permit Automatic Translation Updates?' field.
	 */
	public static function update_control_translation_cb() {
		?>
		<input type="checkbox" class="update_control_dependency" id="update_control_translation" name="update_control_options[translation]" <?php checked( self::get_option( 'translation' ) ); ?> />
		<?php
	}

	/**
	 * Output markup for 'Advanced Settings' field.
	 */
	public static function update_control_toggleadvanced_cb() {
		$options = self::get_options();
		$is_open = 'show' === $options['toggleadvanced'];
		?>
		<details id="update_control_toggleadvanced_details" class="update_control_dependency"<?php echo $is_open ? ' open' : ''; ?>>
			<summary style="cursor: pointer; font-weight: 600; outline: none; user-select: none;">
				<span class="open-text"><?php esc_html_e( 'Hide settings', 'update-control' ); ?></span>
				<span class="closed-text"><?php esc_html_e( 'Show settings', 'update-control' ); ?></span>
			</summary>
		</details>
		<input type="hidden" id="update_control_toggleadvanced" name="update_control_options[toggleadvanced]" value="<?php echo esc_attr( $options['toggleadvanced'] ); ?>" />
		<?php
	}

	/**
	 * Output markup for 'Enable updates for VCS installations?' field.
	 */
	public static function update_control_vcscheck_cb() {
		$is_vcs = self::is_vcs_checkout();
		?>
		<input type="checkbox" class="update_control_advanced" id="update_control_vcscheck" name="update_control_options[vcscheck]" <?php checked( self::get_option( 'vcscheck' ) ); ?> />
		<p class="description">
			<?php
			if ( null === $is_vcs ) {
				esc_html_e( 'Unable to determine whether this site is under version control.', 'update-control' );
			} elseif ( $is_vcs ) {
				esc_html_e( 'This site appears to be a version control checkout, so WordPress would normally block automatic updates. Enable this to allow them anyway.', 'update-control' );
			} else {
				esc_html_e( 'This site does not appear to be a version control checkout, so this setting has no effect here.', 'update-control' );
			}
			?>
		</p>
		<?php
		$vcs_checkouts = self::get_vcs_checkouts();
		if ( ! empty( $vcs_checkouts['plugins'] ) || ! empty( $vcs_checkouts['themes'] ) ) {
			?>
			<p class="description">
				<?php esc_html_e( 'The following are also version control checkouts and would be blocked individually:', 'update-control' ); ?>
				<?php
				if ( ! empty( $vcs_checkouts['plugins'] ) ) {
					printf(
						'<br />%1$s %2$s',
						esc_html__( 'Plugins:', 'update-control' ),
						esc_html( implode( ', ', $vcs_checkouts['plugins'] ) )
					);
				}
				if ( ! empty( $vcs_checkouts['themes'] ) ) {
					printf(
						'<br />%1$s %2$s',
						esc_html__( 'Themes:', 'update-control' ),
						esc_html( implode( ', ', $vcs_checkouts['themes'] ) )
					);
				}
				?>
			</p>
			<?php
		}
	}

	/**
	 * Scan installed plugins and themes for version control checkouts.
	 *
	 * WordPress blocks automatic updates for any plugin or theme whose directory
	 * contains version control metadata, independent of whether the core install
	 * is itself a checkout.
	 *
	 * @return array {
	 *     @type string[] $plugins Names of plugin directories under version control.
	 *     @type string[] $themes  Names of themes under version control.
	 * }
	 */
	private static function get_vcs_checkouts() {
		$vcs_dirs = array( '.svn', '.git', '.hg', '.bzr' );
		$found    = array(
			'plugins' => array(),
			'themes'  => array(),
		);

		if ( defined( 'WP_PLUGIN_DIR' ) && is_dir( WP_PLUGIN_DIR ) ) {
			foreach ( (array) glob( WP_PLUGIN_DIR . '/*', GLOB_ONLYDIR ) as $plugin_dir ) {
				foreach ( $vcs_dirs as $vcs ) {
					if ( is_dir( $plugin_dir . '/' . $vcs ) ) {
						$found['plugins'][] = basename( $plugin_dir );
						break;
					}
				}
			}
		}

		foreach ( wp_get_themes() as $theme ) {
			$stylesheet_dir = $theme->get_stylesheet_directory();
			foreach ( $vcs_dirs as $vcs ) {
				if ( is_dir( $stylesheet_dir . '/' . $vcs ) ) {
					$found['themes'][] = $theme->get( 'Name' );
					break;
				}
			}
		}

		return $found;
	}

	/**
	 * Determine whether the current site is a version control checkout.
	 *
	 * Mirrors the detection WordPress core uses to decide whether to block
	 * automatic updates on VCS installs.
	 *
	 * @return bool|null True/false if detectable, null if it cannot be determined.
	 */
	private static function is_vcs_checkout() {
		if ( ! class_exists( 'WP_Automatic_Updater' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-automatic-updater.php';
		}

		if ( ! class_exists( 'WP_Automatic_Updater' ) ) {
			return null;
		}

		$updater = new \WP_Automatic_Updater();

		return (bool) $updater->is_vcs_checkout( ABSPATH );
	}

	/**
	 * Output markup for 'Update Emails Enabled?' field.
	 */
	public static function update_control_email_active_cb() {
		?>
		<select class="update_control_advanced" id="update_control_email_active" name="update_control_options[emailactive]">
			<option <?php selected( 'yes' === self::get_option( 'emailactive' ) ); ?> value="yes"><?php esc_html_e( 'Yes', 'update-control' ); ?></option>
			<option <?php selected( 'no' === self::get_option( 'emailactive' ) ); ?> value="no"><?php esc_html_e( 'No', 'update-control' ); ?></option>
		</select>
		<?php
	}

	/**
	 * Output markup for 'Send Emails for Successful Updates?' field.
	 */
	public static function update_control_email_success_cb() {
		?>
		<input type="checkbox" class="update_control_email_dependency update_control_advanced" id="update_control_email_success" name="update_control_options[successemail]" <?php checked( self::get_option( 'successemail' ) ); ?> />
		<?php
	}

	/**
	 * Output markup for 'Send Emails for Failed Updates?' field.
	 */
	public static function update_control_email_failure_cb() {
		?>
		<input type="checkbox" class="update_control_email_dependency update_control_advanced" id="update_control_email_failure" name="update_control_options[failureemail]" <?php checked( self::get_option( 'failureemail' ) ); ?> />
		<?php
	}

	/**
	 * Output markup for 'Send Emails for Critically Failed Updates?' field.
	 */
	public static function update_control_email_critical_cb() {
		?>
		<input type="checkbox" class="update_control_email_dependency update_control_advanced" id="update_control_email_critical" name="update_control_options[criticalemail]" <?php checked( self::get_option( 'criticalemail' ) ); ?> />
		<?php
	}

	/**
	 * Output markup for 'Disable Debug Emails for Development Versions?' field.
	 */
	public static function update_control_email_debug_cb() {
		?>
		<input type="checkbox" class="update_control_advanced" id="update_control_email_debug" name="update_control_options[debugemail]" <?php checked( self::get_option( 'debugemail' ) ); ?> />
		<?php
	}

	/**
	 * Output markup for 'Notification Email Address' field.
	 */
	public static function update_control_email_recipient_cb() {
		?>
		<?php
		$default_recipient = get_option( 'admin_email' );
		if ( $default_recipient ) {
			$recipient_placeholder = sprintf(
				/* translators: %s: the site's admin email address. */
				__( 'Leave empty to use %s', 'update-control' ),
				$default_recipient
			);
		} else {
			$recipient_placeholder = __( 'Leave empty for default admin email', 'update-control' );
		}
		?>
		<input type="email" class="update_control_email_dependency update_control_advanced regular-text" id="update_control_email_recipient" name="update_control_options[notification_email]" value="<?php echo esc_attr( self::get_option( 'notification_email' ) ); ?>" placeholder="<?php echo esc_attr( $recipient_placeholder ); ?>" />
		<?php
	}

	/**
	 * Overrides the recipient email address for auto-update notifications.
	 *
	 * @param array $email Email arguments.
	 * @return array Modified email arguments.
	 */
	public static function filter_email_recipient( $email ) {
		$options = self::get_options();
		if ( ! empty( $options['notification_email'] ) && is_email( $options['notification_email'] ) ) {
			$email['to'] = $options['notification_email'];
		}
		return $email;
	}

	/**
	 * Sanitize plugin options on save.
	 *
	 * @param array $options Raw options sent by option page.
	 * @return array Sanitized options.
	 */
	public static function sanitize_options( $options ) {
		$options = wp_parse_args( (array) $options, self::get_options() );

		$options['active']             = ( in_array( $options['active'], array( 'yes', 'no' ), true ) ? $options['active'] : 'yes' );
		$options['core']               = ( in_array( $options['core'], array( 'minor', 'major', 'dev' ), true ) ? $options['core'] : 'minor' );
		$options['plugin']             = ( in_array( $options['plugin'], array( 'yes', 'no', 'core' ), true ) ? $options['plugin'] : 'core' );
		$options['theme']              = ( in_array( $options['theme'], array( 'yes', 'no', 'core' ), true ) ? $options['theme'] : 'core' );
		$options['translation']        = ! empty( $options['translation'] );
		$options['toggleadvanced']     = ( isset( $options['toggleadvanced'] ) && in_array( $options['toggleadvanced'], array( 'show', 'hide' ), true ) ? $options['toggleadvanced'] : 'hide' );
		$options['vcscheck']           = ! empty( $options['vcscheck'] );
		$options['emailactive']        = ( in_array( $options['emailactive'], array( 'yes', 'no' ), true ) ? $options['emailactive'] : 'yes' );
		$options['successemail']       = ! empty( $options['successemail'] );
		$options['failureemail']       = ! empty( $options['failureemail'] );
		$options['criticalemail']      = ! empty( $options['criticalemail'] );
		$options['debugemail']         = ! empty( $options['debugemail'] );
		$options['notification_email'] = ! empty( $options['notification_email'] ) ? sanitize_email( $options['notification_email'] ) : '';

		return $options;
	}
}
add_action( 'admin_init', array( __NAMESPACE__ . '\Update_Control', 'register_settings' ) );
add_action( 'init', array( __NAMESPACE__ . '\Update_Control', 'setup_upgrade_filters' ) );
add_action( 'admin_enqueue_scripts', array( __NAMESPACE__ . '\Update_Control', 'enqueue_admin_scripts' ) );