<?php
/**
 * Admin UI and settings management class.
 *
 * @package GS_Support_Feed
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * GS_Support_Admin_UI class.
 */
class GS_Support_Admin_UI {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_gs_sf_toggle_read', array( $this, 'ajax_toggle_read' ) );
	}

	/**
	 * Register Admin Menu under Tools.
	 */
	public function register_admin_menu(): void {
		add_management_page(
			__( 'GS Support Feed', 'gs-support-feed' ),
			__( 'Support Feed', 'gs-support-feed' ),
			'manage_options',
			'gs-support-feed',
			array( $this, 'render_admin_page' )
		);
	}

	/**
	 * Enqueue admin styles and scripts.
	 *
	 * @param string $hook Page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		if ( 'tools_page_gs-support-feed' !== $hook ) {
			return;
		}

		wp_enqueue_script( 'jquery' );
	}

	/**
	 * Handle form actions (saving settings, adding/removing plugins, profile import, manual sync).
	 */
	public function handle_actions(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_REQUEST['page'] ) || 'gs-support-feed' !== $_REQUEST['page'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_REQUEST['gs_sf_action'] ) ? sanitize_key( $_REQUEST['gs_sf_action'] ) : '';
		if ( empty( $action ) ) {
			return;
		}

		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ), 'gs_sf_admin_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed. Please refresh and try again.', 'gs-support-feed' ) );
		}

		$manager = gs_support_manager();

		switch ( $action ) {
			case 'save_settings':
				$new_settings = array(
					'sync_interval'    => isset( $_POST['sync_interval'] ) ? sanitize_key( $_POST['sync_interval'] ) : 'hourly',
					'enable_email'     => isset( $_POST['enable_email'] ) ? 1 : 0,
					'email_recipients' => isset( $_POST['email_recipients'] ) ? sanitize_text_field( wp_unslash( $_POST['email_recipients'] ) ) : '',
					'enable_webhook'   => isset( $_POST['enable_webhook'] ) ? 1 : 0,
					'webhook_url'      => isset( $_POST['webhook_url'] ) ? esc_url_raw( wp_unslash( $_POST['webhook_url'] ) ) : '',
					'max_stored_items' => isset( $_POST['max_stored_items'] ) ? absint( $_POST['max_stored_items'] ) : 500,
				);
				$manager->save_settings( $new_settings );
				wp_safe_redirect( admin_url( 'tools.php?page=gs-support-feed&tab=settings&updated=1' ) );
				exit;

			case 'add_plugin':
				$slug  = isset( $_POST['plugin_slug'] ) ? sanitize_title( wp_unslash( $_POST['plugin_slug'] ) ) : '';
				$label = isset( $_POST['plugin_label'] ) ? sanitize_text_field( wp_unslash( $_POST['plugin_label'] ) ) : '';
				$type  = isset( $_POST['item_type'] ) && 'theme' === $_POST['item_type'] ? 'theme' : 'plugin';
				if ( ! empty( $slug ) ) {
					$manager->add_monitored_item( $slug, $type, $label );
					// Trigger sync for newly added item.
					$manager->fetcher->sync_all();
				}
				wp_safe_redirect( admin_url( 'tools.php?page=gs-support-feed&tab=plugins&added=1' ) );
				exit;

			case 'remove_plugin':
				$slug = isset( $_GET['slug'] ) ? sanitize_text_field( wp_unslash( $_GET['slug'] ) ) : '';
				if ( ! empty( $slug ) ) {
					$manager->remove_monitored_plugin( $slug );
				}
				wp_safe_redirect( admin_url( 'tools.php?page=gs-support-feed&tab=plugins&removed=1' ) );
				exit;

			case 'import_profile':
				$profile_input  = isset( $_POST['profile_url'] ) ? sanitize_text_field( wp_unslash( $_POST['profile_url'] ) ) : '';
				$username       = $manager->extract_username_from_profile_url( $profile_input );
				$imported_count = 0;

				if ( ! empty( $username ) ) {
					$items = $manager->fetch_author_items_from_wporg( $username );
					foreach ( $items['plugins'] as $p ) {
						if ( $manager->add_monitored_item( $p['slug'], 'plugin', $p['label'] ) ) {
							++$imported_count;
						}
					}
					foreach ( $items['themes'] as $t ) {
						if ( $manager->add_monitored_item( $t['slug'], 'theme', $t['label'] ) ) {
							++$imported_count;
						}
					}
					if ( $imported_count > 0 ) {
						$manager->fetcher->sync_all();
					}
				}
				wp_safe_redirect( admin_url( 'tools.php?page=gs-support-feed&tab=plugins&profile_imported=' . $imported_count . '&user=' . rawurlencode( $username ) ) );
				exit;

			case 'import_installed':
				if ( ! function_exists( 'get_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				$installed = get_plugins();
				$count     = 0;
				foreach ( $installed as $plugin_file => $plugin_data ) {
					$parts = explode( '/', $plugin_file );
					if ( count( $parts ) > 1 ) {
						$slug = $parts[0];
						$name = $plugin_data['Name'];
						if ( $manager->add_monitored_item( $slug, 'plugin', $name ) ) {
							++$count;
						}
					}
				}
				$themes = wp_get_themes();
				foreach ( $themes as $theme_slug => $theme_obj ) {
					$name = $theme_obj->get( 'Name' );
					if ( $manager->add_monitored_item( $theme_slug, 'theme', $name ) ) {
						++$count;
					}
				}
				if ( $count > 0 ) {
					$manager->fetcher->sync_all();
				}
				wp_safe_redirect( admin_url( 'tools.php?page=gs-support-feed&tab=plugins&imported=' . $count ) );
				exit;

			case 'sync_now':
				$stats = $manager->run_cron_sync();
				wp_safe_redirect( admin_url( 'tools.php?page=gs-support-feed&tab=dashboard&synced=1&new=' . $stats['new_items_found'] ) );
				exit;

			case 'mark_all_read':
				$items = $manager->get_feed_items();
				foreach ( $items as &$item ) {
					$item['read'] = true;
				}
				unset( $item );
				$manager->save_feed_items( $items );
				wp_safe_redirect( admin_url( 'tools.php?page=gs-support-feed&tab=dashboard&marked_read=1' ) );
				exit;

			case 'bulk_items':
				$item_ids    = isset( $_POST['item_ids'] ) && is_array( $_POST['item_ids'] ) ? array_map( 'sanitize_key', $_POST['item_ids'] ) : array();
				$bulk_action = isset( $_POST['bulk_action_type'] ) ? sanitize_key( $_POST['bulk_action_type'] ) : '';

				if ( ! empty( $item_ids ) && ! empty( $bulk_action ) ) {
					$items = $manager->get_feed_items();
					foreach ( $item_ids as $id ) {
						if ( 'mark_read' === $bulk_action && isset( $items[ $id ] ) ) {
							$items[ $id ]['read'] = true;
						} elseif ( 'mark_unread' === $bulk_action && isset( $items[ $id ] ) ) {
							$items[ $id ]['read'] = false;
						} elseif ( 'delete' === $bulk_action && isset( $items[ $id ] ) ) {
							unset( $items[ $id ] );
						}
					}
					$manager->save_feed_items( $items );
				}
				wp_safe_redirect( admin_url( 'tools.php?page=gs-support-feed&tab=dashboard' ) );
				exit;
		}
	}

	/**
	 * Handle AJAX toggle read status.
	 */
	public function ajax_toggle_read(): void {
		check_ajax_referer( 'gs_sf_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Unauthorized' ) );
		}

		$item_id = isset( $_POST['item_id'] ) ? sanitize_key( $_POST['item_id'] ) : '';
		if ( empty( $item_id ) ) {
			wp_send_json_error( array( 'message' => 'Invalid item ID' ) );
		}

		$manager = gs_support_manager();
		$items   = $manager->get_feed_items();

		if ( isset( $items[ $item_id ] ) ) {
			$items[ $item_id ]['read'] = ! $items[ $item_id ]['read'];
			$manager->save_feed_items( $items );
			wp_send_json_success( array( 'read' => $items[ $item_id ]['read'] ) );
		}

		wp_send_json_error( array( 'message' => 'Item not found' ) );
	}

	/**
	 * Render full admin page view.
	 */
	public function render_admin_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'dashboard';
		$nonce      = wp_create_nonce( 'gs_sf_admin_nonce' );

		echo '<div class="wrap">';
		echo '<h1>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plugin-owned static SVG asset, no user input.
		echo $this->get_icon_svg();
		echo ' ' . esc_html__( 'GS Support Feed', 'gs-support-feed' ) . '</h1>';

		$this->render_notices();

		echo '<h2 class="nav-tab-wrapper">';
		echo '<a href="' . esc_url( admin_url( 'tools.php?page=gs-support-feed&tab=dashboard' ) ) . '" class="nav-tab ' . ( 'dashboard' === $active_tab ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Unified Support Feed', 'gs-support-feed' ) . '</a>';
		echo '<a href="' . esc_url( admin_url( 'tools.php?page=gs-support-feed&tab=plugins' ) ) . '" class="nav-tab ' . ( 'plugins' === $active_tab ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Monitored Plugins & Themes', 'gs-support-feed' ) . '</a>';
		echo '<a href="' . esc_url( admin_url( 'tools.php?page=gs-support-feed&tab=settings' ) ) . '" class="nav-tab ' . ( 'settings' === $active_tab ? 'nav-tab-active' : '' ) . '">' . esc_html__( 'Settings & Notifications', 'gs-support-feed' ) . '</a>';
		echo '</h2>';

		echo '<div class="tab-content" style="margin-top: 15px;">';
		if ( 'plugins' === $active_tab ) {
			$this->render_plugins_tab( $nonce );
		} elseif ( 'settings' === $active_tab ) {
			$this->render_settings_tab( $nonce );
		} else {
			$this->render_dashboard_tab( $nonce );
		}
		echo '</div>';

		echo '</div>';

		// Render inline CSS and JS for interactive admin UX.
		$this->render_inline_assets( $nonce );
	}

	/**
	 * Inline mono life-ring SVG used as the page-header icon.
	 *
	 * @return string Raw SVG markup (trusted, plugin-owned, no user input).
	 */
	private function get_icon_svg(): string {
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="32" height="32" fill="currentColor" role="img" aria-label="' . esc_attr__( 'Support', 'gs-support-feed' ) . '" style="vertical-align: middle;"><mask id="gs-sf-icon-mask"><rect x="0" y="0" width="200" height="200" fill="white"></rect><circle cx="100" cy="100" r="52" fill="black"></circle><path d="M160.1 181.3 L181.3 160.1 L39.9 18.7 L18.7 39.9 Z" fill="black"></path><path d="M18.7 160.1 L39.9 181.3 L181.3 39.9 L160.1 18.7 Z" fill="black"></path></mask><circle cx="100" cy="100" r="92" mask="url(#gs-sf-icon-mask)"></circle></svg>';
	}

	/**
	 * Render feedback notices.
	 */
	private function render_notices(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully.', 'gs-support-feed' ) . '</p></div>';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['added'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Item added to monitored list.', 'gs-support-feed' ) . '</p></div>';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['removed'] ) ) {
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'Item removed from monitored list.', 'gs-support-feed' ) . '</p></div>';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['imported'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$count = absint( $_GET['imported'] );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
				sprintf(
					/* translators: 1: Count of imported items */
					__( 'Imported %d plugin(s) and theme(s) from local site.', 'gs-support-feed' ),
					$count
				)
			) . '</p></div>';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['profile_imported'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$count = absint( $_GET['profile_imported'] );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$user = isset( $_GET['user'] ) ? sanitize_text_field( wp_unslash( $_GET['user'] ) ) : '';
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
				sprintf(
					/* translators: 1: Count of imported items, 2: WordPress.org username */
					__( 'Imported %1$d plugin(s) and theme(s) for WordPress.org user "%2$s".', 'gs-support-feed' ),
					$count,
					$user
				)
			) . '</p></div>';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['synced'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$new_count = isset( $_GET['new'] ) ? absint( $_GET['new'] ) : 0;
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
				sprintf(
					/* translators: 1: Count of new support items */
					__( 'Sync completed! Found %d new item(s).', 'gs-support-feed' ),
					$new_count
				)
			) . '</p></div>';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['marked_read'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'All items marked as read.', 'gs-support-feed' ) . '</p></div>';
		}
	}

	/**
	 * Render Dashboard (Unified Support Feed) Tab.
	 *
	 * @param string $nonce Nonce value.
	 */
	private function render_dashboard_tab( string $nonce ): void {
		$manager   = gs_support_manager();
		$monitored = $manager->get_monitored_plugins();
		$all_items = $manager->get_feed_items();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter_plugin = isset( $_GET['filter_plugin'] ) ? sanitize_text_field( wp_unslash( $_GET['filter_plugin'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter_status = isset( $_GET['filter_status'] ) ? sanitize_key( wp_unslash( $_GET['filter_status'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search_query = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';

		// Filter items.
		$filtered_items = array();
		$unread_count   = 0;

		foreach ( $all_items as $item ) {
			if ( empty( $item['read'] ) ) {
				++$unread_count;
			}

			if ( ! empty( $filter_plugin ) && $item['plugin_slug'] !== $filter_plugin && ( isset( $item['item_type'] ) ? $item['item_type'] . ':' . $item['plugin_slug'] : $item['plugin_slug'] ) !== $filter_plugin ) {
				continue;
			}
			if ( 'unread' === $filter_status && ! empty( $item['read'] ) ) {
				continue;
			}
			if ( 'read' === $filter_status && empty( $item['read'] ) ) {
				continue;
			}
			if ( ! empty( $search_query ) ) {
				$match_title  = false !== stripos( $item['title'], $search_query );
				$match_desc   = false !== stripos( $item['description'], $search_query );
				$match_author = false !== stripos( $item['author'], $search_query );
				if ( ! $match_title && ! $match_desc && ! $match_author ) {
					continue;
				}
			}

			$filtered_items[] = $item;
		}

		echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px;">';
		echo '<div>';
		echo '<strong style="font-size: 16px;">' . esc_html(
			sprintf(
				/* translators: 1: Total count of support topics */
				__( 'Total Topics: %d', 'gs-support-feed' ),
				count( $all_items )
			)
		) . '</strong> ';
		echo '<span style="margin: 0 10px; color: #ccc;">|</span> ';
		echo '<span class="badge-unread" style="background:#e74c3c; color:#fff; padding:3px 8px; border-radius:10px; font-weight:bold; font-size:12px;">' . esc_html(
			sprintf(
				/* translators: 1: Unread topic count */
				__( '%d Unread', 'gs-support-feed' ),
				$unread_count
			)
		) . '</span>';
		echo '</div>';

		echo '<div>';
		echo '<a href="' . esc_url( wp_nonce_url( admin_url( 'tools.php?page=gs-support-feed&gs_sf_action=sync_now' ), 'gs_sf_admin_nonce' ) ) . '" class="button button-primary"><span class="dashicons dashicons-update" style="margin-top:4px;"></span> ' . esc_html__( 'Sync All Feeds Now', 'gs-support-feed' ) . '</a> ';
		echo '<a href="' . esc_url( wp_nonce_url( admin_url( 'tools.php?page=gs-support-feed&gs_sf_action=mark_all_read' ), 'gs_sf_admin_nonce' ) ) . '" class="button button-secondary">' . esc_html__( 'Mark All as Read', 'gs-support-feed' ) . '</a>';
		echo '</div>';
		echo '</div>';

		// Filters & Search Bar.
		echo '<form method="get" action="' . esc_url( admin_url( 'tools.php' ) ) . '" style="margin-bottom: 15px; display:flex; gap:10px; align-items:center; background:#fff; padding:10px; border:1px solid #ccd0d4; border-radius:4px;">';
		echo '<input type="hidden" name="page" value="gs-support-feed" />';
		echo '<input type="hidden" name="tab" value="dashboard" />';

		echo '<select name="filter_plugin">';
		echo '<option value="">' . esc_html__( 'All Monitored Items', 'gs-support-feed' ) . '</option>';
		foreach ( $monitored as $key => $data ) {
			$item_slug = isset( $data['slug'] ) ? $data['slug'] : $key;
			$item_type = isset( $data['type'] ) ? $data['type'] : 'plugin';
			$item_val  = $item_type . ':' . $item_slug;
			echo '<option value="' . esc_attr( $item_val ) . '" ' . selected( $filter_plugin, $item_val, false ) . '>';
			echo esc_html( sprintf( '[%s] %s (%s)', strtoupper( $item_type ), $data['label'], $item_slug ) );
			echo '</option>';
		}
		echo '</select>';

		echo '<select name="filter_status">';
		echo '<option value="">' . esc_html__( 'All Statuses', 'gs-support-feed' ) . '</option>';
		echo '<option value="unread" ' . selected( $filter_status, 'unread', false ) . '>' . esc_html__( 'Unread Only', 'gs-support-feed' ) . '</option>';
		echo '<option value="read" ' . selected( $filter_status, 'read', false ) . '>' . esc_html__( 'Read Only', 'gs-support-feed' ) . '</option>';
		echo '</select>';

		echo '<input type="search" name="s" value="' . esc_attr( $search_query ) . '" placeholder="' . esc_attr__( 'Search topics or authors...', 'gs-support-feed' ) . '" style="width:250px;" />';
		echo '<input type="submit" class="button" value="' . esc_attr__( 'Filter', 'gs-support-feed' ) . '" />';
		if ( ! empty( $filter_plugin ) || ! empty( $filter_status ) || ! empty( $search_query ) ) {
			echo '<a href="' . esc_url( admin_url( 'tools.php?page=gs-support-feed&tab=dashboard' ) ) . '" class="button button-link">' . esc_html__( 'Clear Filters', 'gs-support-feed' ) . '</a>';
		}
		echo '</form>';

		// Bulk Action Form & Feed Items List.
		echo '<form method="post" action="' . esc_url( admin_url( 'tools.php' ) ) . '">';
		echo '<input type="hidden" name="page" value="gs-support-feed" />';
		echo '<input type="hidden" name="gs_sf_action" value="bulk_items" />';
		echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '" />';

		echo '<div class="tablenav top" style="margin-bottom:10px;">';
		echo '<div class="alignleft actions bulkactions">';
		echo '<select name="bulk_action_type">';
		echo '<option value="">' . esc_html__( 'Bulk Actions', 'gs-support-feed' ) . '</option>';
		echo '<option value="mark_read">' . esc_html__( 'Mark as Read', 'gs-support-feed' ) . '</option>';
		echo '<option value="mark_unread">' . esc_html__( 'Mark as Unread', 'gs-support-feed' ) . '</option>';
		echo '<option value="delete">' . esc_html__( 'Delete', 'gs-support-feed' ) . '</option>';
		echo '</select>';
		echo '<input type="submit" class="button action" value="' . esc_attr__( 'Apply', 'gs-support-feed' ) . '" />';
		echo '</div>';
		echo '</div>';

		if ( empty( $filtered_items ) ) {
			echo '<div class="notice notice-info"><p>' . esc_html__( 'No support topics found matching your criteria. Try adding plugins or themes to track or click "Sync All Feeds Now".', 'gs-support-feed' ) . '</p></div>';
		} else {
			echo '<table class="wp-list-table widefat fixed striped table-view-list">';
			echo '<thead>';
			echo '<tr>';
			echo '<td class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all-top" /></td>';
			echo '<th style="width: 100px;">' . esc_html__( 'Status', 'gs-support-feed' ) . '</th>';
			echo '<th style="width: 160px;">' . esc_html__( 'Item', 'gs-support-feed' ) . '</th>';
			echo '<th>' . esc_html__( 'Topic Title', 'gs-support-feed' ) . '</th>';
			echo '<th style="width: 140px;">' . esc_html__( 'Author', 'gs-support-feed' ) . '</th>';
			echo '<th style="width: 150px;">' . esc_html__( 'Date Posted', 'gs-support-feed' ) . '</th>';
			echo '<th style="width: 100px;">' . esc_html__( 'Action', 'gs-support-feed' ) . '</th>';
			echo '</tr>';
			echo '</thead>';
			echo '<tbody>';

			foreach ( $filtered_items as $item ) {
				$item_id      = esc_attr( $item['id'] );
				$is_read      = ! empty( $item['read'] );
				$row_style    = $is_read ? 'opacity: 0.7;' : 'font-weight: bold; background: #fff8e5;';
				$status_label = $is_read ? '<span style="color:#777;">' . esc_html__( 'Read', 'gs-support-feed' ) . '</span>' : '<span style="color:#e74c3c; font-weight:bold;">● ' . esc_html__( 'Unread', 'gs-support-feed' ) . '</span>';

				$item_type    = isset( $item['item_type'] ) ? $item['item_type'] : 'plugin';
				$item_key     = $item_type . ':' . $item['plugin_slug'];
				$plugin_label = isset( $monitored[ $item_key ] ) ? $monitored[ $item_key ]['label'] : ( isset( $monitored[ $item['plugin_slug'] ] ) ? $monitored[ $item['plugin_slug'] ]['label'] : $item['plugin_slug'] );

				$badge_bg = 'theme' === $item_type ? '#e8f5e9' : '#eef';
				$badge_fg = 'theme' === $item_type ? '#2e7d32' : '#1565c0';

				echo '<tr id="item-row-' . esc_attr( $item_id ) . '" style="' . esc_attr( $row_style ) . '">';
				echo '<th scope="row" class="check-column"><input type="checkbox" name="item_ids[]" value="' . esc_attr( $item_id ) . '" /></th>';
				echo '<td class="item-status-col">' . wp_kses_post( $status_label ) . '</td>';
				echo '<td>';
				echo '<span class="plugin-tag" style="background:' . esc_attr( $badge_bg ) . '; color:' . esc_attr( $badge_fg ) . '; padding:2px 6px; border-radius:3px; font-size:11px; margin-right:4px;">' . esc_html( strtoupper( $item_type ) ) . '</span> ';
				echo '<strong>' . esc_html( $plugin_label ) . '</strong>';
				echo '</td>';
				echo '<td>';
				echo '<a href="' . esc_url( $item['link'] ) . '" target="_blank" style="text-decoration:none;">' . esc_html( $item['title'] ) . '</a> <span class="dashicons dashicons-external" style="font-size:14px; width:14px; height:14px; vertical-align:middle; color:#666;"></span>';
				if ( ! empty( $item['description'] ) ) {
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-sanitized on input via SimplePie and wp_kses_post.
					echo '<div class="item-preview" style="font-weight:normal; font-size:12px; color:#555; margin-top:4px;">' . wp_trim_words( $item['description'], 25 ) . '</div>';
				}
				echo '</td>';
				echo '<td>' . esc_html( ! empty( $item['author'] ) ? $item['author'] : __( 'Anonymous', 'gs-support-feed' ) ) . '</td>';
				echo '<td>' . esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item['pub_date'] ) ) . '</td>';
				echo '<td>';
				echo '<button type="button" class="button button-small toggle-read-btn" data-id="' . esc_attr( $item_id ) . '">' . ( $is_read ? esc_html__( 'Mark Unread', 'gs-support-feed' ) : esc_html__( 'Mark Read', 'gs-support-feed' ) ) . '</button>';
				echo '</td>';
				echo '</tr>';
			}

			echo '</tbody>';
			echo '</table>';
		}

		echo '</form>';
	}

	/**
	 * Render Monitored Plugins & Themes Tab.
	 *
	 * @param string $nonce Nonce value.
	 */
	private function render_plugins_tab( string $nonce ): void {
		$manager   = gs_support_manager();
		$monitored = $manager->get_monitored_plugins();

		echo '<div style="display:flex; gap:20px; align-items:flex-start;">';

		// Monitored Items List Table.
		echo '<div style="flex:2;">';
		echo '<h2>' . esc_html__( 'Monitored Plugins & Themes List', 'gs-support-feed' ) . '</h2>';

		if ( empty( $monitored ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'You are not monitoring any plugins or themes yet. Add an item on the right, import a WordPress.org Profile, or import installed plugins.', 'gs-support-feed' ) . '</p></div>';
		} else {
			echo '<table class="wp-list-table widefat fixed striped">';
			echo '<thead>';
			echo '<tr>';
			echo '<th style="width: 80px;">' . esc_html__( 'Type', 'gs-support-feed' ) . '</th>';
			echo '<th>' . esc_html__( 'Name', 'gs-support-feed' ) . '</th>';
			echo '<th>' . esc_html__( 'WP.org Slug', 'gs-support-feed' ) . '</th>';
			echo '<th>' . esc_html__( 'Last Synced', 'gs-support-feed' ) . '</th>';
			echo '<th>' . esc_html__( 'Topics Cached', 'gs-support-feed' ) . '</th>';
			echo '<th>' . esc_html__( 'Actions', 'gs-support-feed' ) . '</th>';
			echo '</tr>';
			echo '</thead>';
			echo '<tbody>';

			foreach ( $monitored as $key => $data ) {
				$type          = isset( $data['type'] ) ? $data['type'] : 'plugin';
				$slug          = isset( $data['slug'] ) ? $data['slug'] : $key;
				$last_sync_str = ! empty( $data['last_sync'] ) ? date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $data['last_sync'] ) : __( 'Never', 'gs-support-feed' );
				$forum_url     = sprintf( 'https://wordpress.org/support/%s/%s/', $type, $slug );
				$remove_url    = wp_nonce_url( admin_url( 'tools.php?page=gs-support-feed&gs_sf_action=remove_plugin&slug=' . rawurlencode( $key ) ), 'gs_sf_admin_nonce' );

				$badge_bg = 'theme' === $type ? '#e8f5e9' : '#eef';
				$badge_fg = 'theme' === $type ? '#2e7d32' : '#1565c0';

				echo '<tr>';
				echo '<td><span style="background:' . esc_attr( $badge_bg ) . '; color:' . esc_attr( $badge_fg ) . '; padding:2px 6px; border-radius:3px; font-size:11px; font-weight:bold;">' . esc_html( strtoupper( $type ) ) . '</span></td>';
				echo '<td><strong>' . esc_html( $data['label'] ) . '</strong></td>';
				echo '<td><code>' . esc_html( $slug ) . '</code></td>';
				echo '<td>' . esc_html( $last_sync_str ) . '</td>';
				echo '<td>' . esc_html( isset( $data['item_count'] ) ? $data['item_count'] : 0 ) . '</td>';
				echo '<td>';
				echo '<a href="' . esc_url( $forum_url ) . '" target="_blank" class="button button-small"><span class="dashicons dashicons-external" style="margin-top:2px;"></span> ' . esc_html__( 'Forum', 'gs-support-feed' ) . '</a> ';
				echo '<a href="' . esc_url( $remove_url ) . '" class="button button-small button-link-delete" onclick="return confirm(\'' . esc_js( __( 'Are you sure you want to stop monitoring this item?', 'gs-support-feed' ) ) . '\');">' . esc_html__( 'Remove', 'gs-support-feed' ) . '</a>';
				echo '</td>';
				echo '</tr>';
			}

			echo '</tbody>';
			echo '</table>';
		}
		echo '</div>';

		// Sidebar Controls: Profile Import, Add Single Item & Import Local Installed.
		echo '<div style="flex:1; background:#fff; border:1px solid #ccd0d4; padding:15px; border-radius:4px;">';

		// 1. WordPress.org Profile Import.
		echo '<h3><span class="dashicons dashicons-admin-users" style="margin-top:3px;"></span> ' . esc_html__( 'Import from WordPress.org Profile', 'gs-support-feed' ) . '</h3>';
		echo '<p>' . esc_html__( 'Enter a WordPress.org profile URL or username to automatically populate plugins and themes published by that author.', 'gs-support-feed' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'tools.php' ) ) . '" id="gs-sf-profile-import-form">';
		echo '<input type="hidden" name="page" value="gs-support-feed" />';
		echo '<input type="hidden" name="gs_sf_action" value="import_profile" />';
		echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '" />';

		echo '<p><label><strong>' . esc_html__( 'Profile URL or Username:', 'gs-support-feed' ) . '</strong><br/>';
		echo '<input type="text" name="profile_url" required placeholder="https://profiles.wordpress.org/username/" class="widefat" /></label></p>';

		echo '<p style="display:flex; align-items:center; gap:8px;">';
		echo '<input type="submit" id="gs-sf-import-btn" class="button button-primary" value="' . esc_attr__( 'Import Profile Plugins & Themes', 'gs-support-feed' ) . '" />';
		echo '<span class="spinner" id="gs-sf-import-spinner" style="float:none; margin:0;"></span>';
		echo '</p>';
		echo '</form>';

		echo '<hr style="margin:20px 0; border:0; border-top:1px solid #eee;" />';

		// 2. Add Single Item.
		echo '<h3>' . esc_html__( 'Add Single Plugin or Theme', 'gs-support-feed' ) . '</h3>';
		echo '<form method="post" action="' . esc_url( admin_url( 'tools.php' ) ) . '">';
		echo '<input type="hidden" name="page" value="gs-support-feed" />';
		echo '<input type="hidden" name="gs_sf_action" value="add_plugin" />';
		echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '" />';

		echo '<p><label><strong>' . esc_html__( 'Type:', 'gs-support-feed' ) . '</strong><br/>';
		echo '<select name="item_type" class="widefat">';
		echo '<option value="plugin">' . esc_html__( 'Plugin', 'gs-support-feed' ) . '</option>';
		echo '<option value="theme">' . esc_html__( 'Theme', 'gs-support-feed' ) . '</option>';
		echo '</select></label></p>';

		echo '<p><label><strong>' . esc_html__( 'Slug:', 'gs-support-feed' ) . '</strong><br/>';
		echo '<input type="text" name="plugin_slug" required placeholder="e.g. woocommerce, twentytwentyfour" class="widefat" /></label><br/>';
		echo '<small style="color:#666;">' . esc_html__( 'The slug from wordpress.org/plugins/{slug}/ or /themes/{slug}/', 'gs-support-feed' ) . '</small></p>';

		echo '<p><label><strong>' . esc_html__( 'Custom Display Label (Optional):', 'gs-support-feed' ) . '</strong><br/>';
		echo '<input type="text" name="plugin_label" placeholder="e.g. WooCommerce Core" class="widefat" /></label></p>';

		echo '<p><input type="submit" class="button button-secondary" value="' . esc_attr__( 'Add Item', 'gs-support-feed' ) . '" /></p>';
		echo '</form>';

		echo '<hr style="margin:20px 0; border:0; border-top:1px solid #eee;" />';

		// 3. Auto-discover installed plugins.
		echo '<h3>' . esc_html__( 'Auto-Discover Installed Plugins', 'gs-support-feed' ) . '</h3>';
		echo '<p>' . esc_html__( 'Automatically add all WordPress.org-hosted plugins installed on this site.', 'gs-support-feed' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'tools.php' ) ) . '">';
		echo '<input type="hidden" name="page" value="gs-support-feed" />';
		echo '<input type="hidden" name="gs_sf_action" value="import_installed" />';
		echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '" />';
		echo '<input type="submit" class="button button-secondary" value="' . esc_attr__( 'Import Installed Plugins', 'gs-support-feed' ) . '" />';
		echo '</form>';

		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render Settings & Notifications Tab.
	 *
	 * @param string $nonce Nonce value.
	 */
	private function render_settings_tab( string $nonce ): void {
		$manager  = gs_support_manager();
		$settings = $manager->get_settings();

		echo '<form method="post" action="' . esc_url( admin_url( 'tools.php' ) ) . '" style="max-width: 800px; background:#fff; padding:20px; border:1px solid #ccd0d4; border-radius:4px;">';
		echo '<input type="hidden" name="page" value="gs-support-feed" />';
		echo '<input type="hidden" name="gs_sf_action" value="save_settings" />';
		echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $nonce ) . '" />';

		echo '<h2>' . esc_html__( 'Sync & Notification Settings', 'gs-support-feed' ) . '</h2>';

		echo '<table class="form-table">';
		echo '<tr>';
		echo '<th scope="row"><label for="sync_interval">' . esc_html__( 'Background Sync Frequency', 'gs-support-feed' ) . '</label></th>';
		echo '<td>';
		echo '<select name="sync_interval" id="sync_interval">';
		echo '<option value="hourly" ' . selected( $settings['sync_interval'], 'hourly', false ) . '>' . esc_html__( 'Hourly (Recommended)', 'gs-support-feed' ) . '</option>';
		echo '<option value="twicedaily" ' . selected( $settings['sync_interval'], 'twicedaily', false ) . '>' . esc_html__( 'Twice Daily', 'gs-support-feed' ) . '</option>';
		echo '<option value="daily" ' . selected( $settings['sync_interval'], 'daily', false ) . '>' . esc_html__( 'Daily', 'gs-support-feed' ) . '</option>';
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'How often WP-Cron checks for new topics across monitored plugin and theme feeds.', 'gs-support-feed' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="max_stored_items">' . esc_html__( 'Max Stored Feed Items', 'gs-support-feed' ) . '</label></th>';
		echo '<td>';
		echo '<input type="number" name="max_stored_items" id="max_stored_items" value="' . esc_attr( $settings['max_stored_items'] ) . '" min="50" max="2000" class="small-text" />';
		echo '<p class="description">' . esc_html__( 'Maximum number of support topics retained in cache storage.', 'gs-support-feed' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		echo '<tr><th colspan="2"><hr/><h3>' . esc_html__( 'Email Notifications', 'gs-support-feed' ) . '</h3></th></tr>';

		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Enable Email Alerts', 'gs-support-feed' ) . '</th>';
		echo '<td>';
		echo '<label><input type="checkbox" name="enable_email" value="1" ' . checked( $settings['enable_email'], 1, false ) . ' /> ' . esc_html__( 'Send email notification digest when new topics are flagged', 'gs-support-feed' ) . '</label>';
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="email_recipients">' . esc_html__( 'Email Recipients', 'gs-support-feed' ) . '</label></th>';
		echo '<td>';
		echo '<input type="text" name="email_recipients" id="email_recipients" value="' . esc_attr( $settings['email_recipients'] ) . '" class="large-text" />';
		echo '<p class="description">' . esc_html__( 'Comma-separated email addresses to receive new topic digests.', 'gs-support-feed' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		echo '<tr><th colspan="2"><hr/><h3>' . esc_html__( 'Webhook Notifications (Slack / Discord / Custom)', 'gs-support-feed' ) . '</h3></th></tr>';

		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Enable Webhook Alerts', 'gs-support-feed' ) . '</th>';
		echo '<td>';
		echo '<label><input type="checkbox" name="enable_webhook" value="1" ' . checked( $settings['enable_webhook'], 1, false ) . ' /> ' . esc_html__( 'Post JSON payload to Webhook URL when new topics are flagged', 'gs-support-feed' ) . '</label>';
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="webhook_url">' . esc_html__( 'Webhook Endpoint URL', 'gs-support-feed' ) . '</label></th>';
		echo '<td>';
		echo '<input type="url" name="webhook_url" id="webhook_url" value="' . esc_attr( $settings['webhook_url'] ) . '" placeholder="https://hooks.slack.com/services/..." class="large-text" />';
		echo '<p class="description">' . esc_html__( 'HTTPS URL receiving JSON HTTP POST payload of newly flagged topics.', 'gs-support-feed' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		echo '<tr><th colspan="2"><hr/><h3>' . esc_html__( 'Unified RSS & JSON Feed Export', 'gs-support-feed' ) . '</h3></th></tr>';

		$rss_url  = home_url( '/wp-json/gs-support-feed/v1/feed?format=rss' );
		$json_url = home_url( '/wp-json/gs-support-feed/v1/feed?format=json' );

		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'External RSS Feed URL', 'gs-support-feed' ) . '</th>';
		echo '<td>';
		echo '<code><a href="' . esc_url( $rss_url ) . '" target="_blank">' . esc_html( $rss_url ) . '</a></code>';
		echo '<p class="description">' . esc_html__( 'Subscribe to this URL in NetNewsWire, Feedly, Apple Mail, or any RSS reader to monitor all plugins and themes in one place!', 'gs-support-feed' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'External JSON Feed URL', 'gs-support-feed' ) . '</th>';
		echo '<td>';
		echo '<code><a href="' . esc_url( $json_url ) . '" target="_blank">' . esc_html( $json_url ) . '</a></code>';
		echo '</td>';
		echo '</tr>';

		echo '</table>';

		echo '<p class="submit"><input type="submit" class="button button-primary" value="' . esc_attr__( 'Save Settings', 'gs-support-feed' ) . '" /></p>';
		echo '</form>';
	}

	/**
	 * Render inline JS assets.
	 *
	 * @param string $nonce Nonce value.
	 */
	private function render_inline_assets( string $nonce ): void {
		?>
		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#cb-select-all-top').on('change', function() {
				$('input[name="item_ids[]"]').prop('checked', this.checked);
			});

			$('#gs-sf-profile-import-form').on('submit', function() {
				var form = $(this);
				var btn = form.find('#gs-sf-import-btn');
				var spinner = form.find('#gs-sf-import-spinner');
				spinner.addClass('is-active');
				setTimeout(function() {
					btn.addClass('disabled').attr('disabled', 'disabled');
				}, 10);
			});

			$('.toggle-read-btn').on('click', function(e) {
				e.preventDefault();
				var btn = $(this);
				var itemId = btn.data('id');
				var row = $('#item-row-' + itemId);

				btn.prop('disabled', true);

				$.post(ajaxurl, {
					action: 'gs_sf_toggle_read',
					item_id: itemId,
					nonce: '<?php echo esc_js( $nonce ); ?>'
				}, function(res) {
					btn.prop('disabled', false);
					if (res.success) {
						if (res.data.read) {
							row.css({ opacity: '0.7', fontWeight: 'normal', background: 'transparent' });
							row.find('.item-status-col').html('<span style="color:#777;"><?php echo esc_js( __( 'Read', 'gs-support-feed' ) ); ?></span>');
							btn.text('<?php echo esc_js( __( 'Mark Unread', 'gs-support-feed' ) ); ?>');
						} else {
							row.css({ opacity: '1.0', fontWeight: 'bold', background: '#fff8e5' });
							row.find('.item-status-col').html('<span style="color:#e74c3c; font-weight:bold;">● <?php echo esc_js( __( 'Unread', 'gs-support-feed' ) ); ?></span>');
							btn.text('<?php echo esc_js( __( 'Mark Read', 'gs-support-feed' ) ); ?>');
						}
					}
				});
			});
		});
		</script>
		<?php
	}
}
