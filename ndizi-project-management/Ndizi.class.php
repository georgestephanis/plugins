<?php 
define( 'NDZ', 'ndizi-project-management' );
if( !class_exists( 'Ndizi' ) ) :
class Ndizi {
	
	var $version = "0.9.7.0";
	var $pagination = 15;
	
	var $page_slugs = Array();
	
	var $user_table;
	var $client_table;
	var $project_table;
	var $task_table;
	var $time_table;
	var $invoice_table;
	var $attachment_table;
	var $permission_table;
	
	var $user_fields = "
		u.ID				AS `user_id`,
		u.user_login		AS `user_login`,
		u.display_name		AS `user_display_name`";
	var $client_fields = "
		c.id				AS `client_id`,
		c.name				AS `client_name`,
		c.phone				AS `client_phone`,
		c.email				AS `client_email`,
		c.site				AS `client_site`,
		c.description		AS `client_description`,
		c.address			AS `client_address`,
		c.access_key		AS `client_access_key`,
		c.active			AS `client_active`,
		c.modified			AS `client_modified`";
	var $project_fields = "
		p.id				AS `project_id`,
		p.client_id			AS `project_client_id`,
		p.name				AS `project_name`,
		p.description		AS `project_description`,
		p.active			AS `project_active`,
		p.modified			AS `project_modified`";
	var $task_fields = "
		t.id				AS `task_id`,
		t.client_id			AS `task_client_id`,
		t.project_id		AS `task_project_id`,
		t.user_id			AS `task_user_id`,
		t.name				AS `task_name`,
		t.description		AS `task_description`,
		t.priority			AS `task_priority`,
		t.status			AS `task_status`,
		t.modified			AS `task_modified`";
	var $time_fields = "
		ti.id				AS `time_id`,
		ti.client_id		AS `time_client_id`,
		ti.project_id		AS `time_project_id`,
		ti.task_id			AS `time_task_id`,
		ti.user_id			AS `time_user_id`,
		ti.invoice_id		AS `time_invoice_id`,
		ti.description		AS `time_description`,
		ti.duration			AS `time_duration`,
		ti.date				AS `time_date`,
		ti.modified			AS `time_modified`";
	var $invoice_fields = "
		i.id				AS `invoice_id`,
		i.client_id			AS `invoice_client_id`,
		i.project_id		AS `invoice_project_id`,
		i.name				AS `invoice_name`,
		i.description		AS `invoice_description`,
		i.notes				AS `invoice_notes`,
		i.total				AS `invoice_total`,
		i.terms				AS `invoice_terms`,
		i.paid				AS `invoice_paid`,
		i.status			AS `invoice_status`,
		i.date				AS `invoice_date`,
		i.modified			AS `invoice_modified`";
	var $attachment_fields = "
		a.id				AS `attachment_id`,
		a.parent			AS `attachment_parent`,
		a.user_id			AS `attachment_user_id`,
		a.item_id			AS `attachment_item_id`,
		a.item_type			AS `attachment_item_type`,
		a.message			AS `attachment_message`,
		a.file				AS `attachment_file`,
		a.when				AS `attachment_when`,
		a.modified			AS `attachment_modified`";
	var $permission_fields = "
		pe.id				AS `permission_id`,
		pe.user_id			AS `permission_user_id`,
		pe.item_id			AS `permission_item_id`,
		pe.item_type		AS `permission_item_type`,
		pe.permissions		AS `permission_permissions`,
		pe.modified			AS `permission_modified`,";
	
	function __construct(){
		global $wpdb;
		session_start();
		
		$this->user_table			= $wpdb->users;
		$this->client_table 		= $wpdb->prefix . 'ndizi_clients';
		$this->project_table 		= $wpdb->prefix . 'ndizi_projects';
		$this->task_table 			= $wpdb->prefix . 'ndizi_todo';
		$this->time_table 			= $wpdb->prefix . 'ndizi_time';
		$this->invoice_table 		= $wpdb->prefix . 'ndizi_invoices';
		$this->attachment_table 	= $wpdb->prefix . 'ndizi_attachments';
		$this->permission_table 	= $wpdb->prefix . 'ndizi_permissions';
		
		if( get_option( 'Ndizi Version' ) !== $this->version ) {
			$this->install();
		}
		
		add_action( 'init', Array( &$this, 'on_init' ) );
	}
	function on_activate(){
		do_action( 'ndizi_on_activate' );
		$this->install();
	}
	function on_deactivate(){
		do_action( 'ndizi_on_deactivate' );
	}
	function install(){
		do_action( 'ndizi_on_install' );
		$this->make_tables();
		$this->upgrade_tables();
		update_option( 'Ndizi Version', $this->version );
	}
	function uninstall(){
		do_action( 'ndizi_on_uninstall' );
		global $wpdb;
		$wpdb->query( "DROP TABLE `$this->project_table`" );
		$wpdb->query( "DROP TABLE `$this->client_table`" );
		$wpdb->query( "DROP TABLE `$this->task_table`" );
		$wpdb->query( "DROP TABLE `$this->time_table`" );
		$wpdb->query( "DROP TABLE `$this->invoice_table`" );
		$wpdb->query( "DROP TABLE `$this->attachment_table`" );
		$wpdb->query( "DROP TABLE `$this->permission_table`" );
		delete_option( 'Ndizi Version' );
		delete_option( 'Ndizi Frontend Page' );
		delete_option( 'Ndizi Print Admin Header Form' );
	}
	function on_init(){
		do_action( 'ndizi_on_init' );
		load_plugin_textdomain( 'ndizi-project-management', NULL, 'ndizi-project-management/i18n/' );
		$this->catch_post();
		
		if( current_user_can('manage_options') 
			&& isset( $_REQUEST['action'], $_REQUEST['id'] ) 
			&& ( 'email_key' == $_REQUEST['action'] ) ){
			$this->email_key( $_REQUEST['id'] );
		}
		
		wp_register_script( 'jquery-ui-slider',		WP_PLUGIN_URL . '/ndizi-project-management/js/jquery.ui.slider.js',		Array('jquery','jquery-ui-core') );
		wp_register_script( 'jquery-ui-widget',		WP_PLUGIN_URL . '/ndizi-project-management/js/jquery.ui.widget.js',		Array('jquery','jquery-ui-core') );
		wp_register_script( 'jquery-ui-mouse',		WP_PLUGIN_URL . '/ndizi-project-management/js/jquery.ui.mouse.js',		Array('jquery','jquery-ui-core','jquery-ui-widget') );
		wp_register_script( 'jquery-ui-datepicker',	WP_PLUGIN_URL . '/ndizi-project-management/js/jquery.ui.datepicker.js',	Array('jquery','jquery-ui-core') );
		wp_register_script( 'jquery-ui-timepicker',	WP_PLUGIN_URL . '/ndizi-project-management/js/jquery.ui.timepicker.js',	Array('jquery','jquery-ui-core','jquery-ui-datepicker','jquery-ui-slider','jquery-ui-mouse','jquery-ui-widget') );
		wp_register_script(	'shadowbox',			WP_PLUGIN_URL . '/ndizi-project-management/js/shadowbox.js' );
		wp_register_style(	'shadowbox',			WP_PLUGIN_URL . '/ndizi-project-management/css/shadowbox.css' );

		add_action( 'admin_print_scripts',	Array( &$this, 'print_admin_scripts_global' ) 	);
		add_action( 'admin_print_styles',	Array( &$this, 'print_admin_styles_global' )	);
		add_action(	'admin_menu',			Array( &$this, 'on_admin_menu' )				);
		add_action(	'wp_dashboard_setup',	Array( &$this, 'on_dashboard_setup' )			);
		add_action( 'wp_print_scripts',		Array( &$this, 'print_frontend_scripts')		);
		add_action( 'wp_print_styles',		Array( &$this, 'print_frontend_styles')			);
	#	add_filter(	'in_admin_header',		Array( &$this, 'admin_header_form' ),	11		);
		add_filter(	'the_content',			Array( &$this, 'frontend_content' ),	11		);
		wp_register_sidebar_widget( 'frontend-time-entry', 'Ndizi Time Entry', array( &$this, 'frontend_time_entry_widget'	) );
		wp_register_widget_control( 'frontend-time-entry', 'Ndizi Time Entry', array( &$this, 'frontend_time_entry_control'	) );
		wp_register_sidebar_widget( 'frontend-client-login', 'Ndizi Client Login', array( &$this, 'frontend_client_login_widget'	) );
		wp_register_widget_control( 'frontend-client-login', 'Ndizi Client Login', array( &$this, 'frontend_client_login_control'	) );
	}
	function on_admin_menu(){
		do_action( 'ndizi_on_admin_menu' );
		if( current_user_can( 'manage_options' ) ){
			$this->page_slugs['options']  = add_menu_page( __('Ndizi Project Management',NDZ),    __('Ndizi',NDZ),            'manage_options', 'ndizi',          Array( &$this, 'options_page' ) );
			$this->page_slugs['options']  = add_submenu_page( 'ndizi', __('Ndizi Options',NDZ),   __('Ndizi Options',NDZ),    'manage_options', 'ndizi',          Array( &$this, 'options_page' ) );
			$this->page_slugs['clients']  =	add_submenu_page( 'ndizi', __('Manage Clients',NDZ),  __('Manage Clients',NDZ),   'manage_options', 'ndizi_clients',  Array( &$this, 'client_page' )  );
			$this->page_slugs['projects'] =	add_submenu_page( 'ndizi', __('Manage Projects',NDZ), __('Manage Projects',NDZ),  'manage_options', 'ndizi_projects', Array( &$this, 'project_page' ) );
			$this->page_slugs['tasks']    =	add_submenu_page( 'ndizi', __('Manage Tasks',NDZ),    __('Manage Tasks',NDZ),     'manage_options', 'ndizi_tasks',    Array( &$this, 'task_page' )    );
			$this->page_slugs['times']    =	add_submenu_page( 'ndizi', __('Manage Time',NDZ),     __('Manage Time',NDZ),      'manage_options', 'ndizi_time',     Array( &$this, 'time_page' )    );
			$this->page_slugs['invoices'] =	add_submenu_page( 'ndizi', __('Manage Invoices',NDZ), __('Manage Invoices',NDZ),  'manage_options', 'ndizi_invoices', Array( &$this, 'invoice_page' ) );
		} elseif( current_user_can( 'edit_posts' ) ){
			$this->page_slugs['times']    = add_menu_page( __('Ndizi Time Tracking',NDZ),         __('Ndizi',NDZ),            'edit_posts',     'ndizi_time',     Array( &$this, 'your_time_page' ) );
		}
		// Enable external plugins to add or remove pages, or even create new ones.
		$this->page_slugs = apply_filters( 'ndizi_filter_page_slugs', $this->page_slugs );
		foreach( $this->page_slugs as $key => $page ) {
			add_action( 'admin_print_scripts-'.$page, array( &$this, 'print_admin_scripts_ndizi' ) );
			add_action( 'admin_print_styles-'.$page, array( &$this, 'print_admin_styles_ndizi' ) );
			add_contextual_help( $page , $this->generate_help($key) );
		}
	}
	function on_dashboard_setup(){
		wp_add_dashboard_widget( 'ndizi_right_now', __('Ndizi Project Management',NDZ), Array( &$this, 'admin_dashboard_widget' ) );	
	}
	function print_admin_scripts_global(){
		wp_enqueue_script( 'jquery-ui-slider' );
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_script( 'jquery-ui-timepicker' );
		wp_enqueue_script( 'ndizi', WP_PLUGIN_URL . '/ndizi-project-management/js/ndizi.js', Array('jquery') );
	}
	function print_admin_scripts_ndizi(){
		wp_tiny_mce( TRUE, Array( "editor_selector" => "wysiwyg" ) );
	}
	function print_admin_styles_global(){
		?>
		<link rel="stylesheet" href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.7.2/themes/smoothness/jquery-ui.css" type="text/css" />
		<link rel="stylesheet" type="text/css" href="<?php echo WP_PLUGIN_URL; ?>/ndizi-project-management/css/ndizi-global.css" />
		<?php
	}
	function print_admin_styles_ndizi(){
		?>
		<link rel="stylesheet" type="text/css" href="<?php echo WP_PLUGIN_URL; ?>/ndizi-project-management/css/ndizi.css" />
		<?php
	}
	function print_frontend_scripts(){
		global $post;
		if( $post->ID == get_option( 'Ndizi Frontend Page' ) ): 
		//	wp_enqueue_script( 'shadowbox' );
		//	wp_enqueue_script( 'thickbox' );
		endif;
	}
	function print_frontend_styles(){
		global $post;
		if( $post->ID == get_option( 'Ndizi Frontend Page' ) ):
		//	wp_enqueue_style( 'shadowbox' );
			?>
			<link rel="stylesheet" type="text/css" href="<?php echo WP_PLUGIN_URL; ?>/ndizi-project-management/css/ndizi-frontend.css" />
			<?php
		endif;
	}
	function generate_help( $page ){
		$help = '<form method="post" action="'.$this->friendly_page_link( 'options', FALSE ).'">
					<fieldset>
						<input type="hidden" name="ndizi_action" value="bug_report" />
						<div class="form-field">
							<label for="frm_description"><strong>'.__('Your E-Mail',NDZ).'</strong></label>
							<input id="frm_description" type="text" name="email" value="'.$GLOBALS['user_email'].'">
						</div>
						<div class="form-field">
							<label for="frm_description"><strong>'.__('What seems to be the problem?',NDZ).'</strong></label>
							<textarea id="frm_description" name="description" rows="8"></textarea>
						</div>
						<p class="submit">
							<input type="submit" class="button" value="'.__('Submit Bug Report',NDZ).' &rarr;" />
						</p>
					</fieldset>
				</form>';
		return apply_filters( 'ndizi_generate_help', $help, $page );
	}
	function make_tables() {
		global $wpdb;
		if( $wpdb->get_var( "SHOW TABLES LIKE '$this->client_table'" )		!= $this->client_table )		{ $this->make_clients_table();		}
		if( $wpdb->get_var( "SHOW TABLES LIKE '$this->project_table'" )		!= $this->project_table )		{ $this->make_projects_table();		}
		if( $wpdb->get_var( "SHOW TABLES LIKE '$this->task_table'" )		!= $this->task_table )			{ $this->make_tasks_table();		}
		if( $wpdb->get_var( "SHOW TABLES LIKE '$this->time_table'" )		!= $this->time_table )			{ $this->make_time_table();			}
		if( $wpdb->get_var( "SHOW TABLES LIKE '$this->invoice_table'" )		!= $this->invoice_table )		{ $this->make_invoices_table();		}
		if( $wpdb->get_var( "SHOW TABLES LIKE '$this->attachment_table'" )	!= $this->attachment_table )	{ $this->make_attachments_table();	}
	//	if( $wpdb->get_var( "SHOW TABLES LIKE '$this->permission_table'" )	!= $this->permission_table )	{ $this->make_permissions_table();	}
		do_action( 'ndizi_make_tables' );
	}
	function upgrade_tables() {
		global $wpdb;
		if( $wpdb->get_var( "SHOW TABLES LIKE '$this->client_table'" )		== $this->client_table )		{ $this->upgrade_clients_table();		}
		if( $wpdb->get_var( "SHOW TABLES LIKE '$this->project_table'" )		== $this->project_table )		{ $this->upgrade_projects_table();		}
		if( $wpdb->get_var( "SHOW TABLES LIKE '$this->task_table'" )		== $this->task_table )			{ $this->upgrade_tasks_table();			}
		if( $wpdb->get_var( "SHOW TABLES LIKE '$this->time_table'" )		== $this->time_table )			{ $this->upgrade_time_table();			}
		if( $wpdb->get_var( "SHOW TABLES LIKE '$this->invoice_table'" )		== $this->invoice_table )		{ $this->upgrade_invoices_table();		}
		if( $wpdb->get_var( "SHOW TABLES LIKE '$this->attachment_table'" )	== $this->attachment_table )	{ $this->upgrade_attachments_table();	}
	//	if( $wpdb->get_var( "SHOW TABLES LIKE '$this->permission_table'" )	== $this->permission_table )	{ $this->upgrade_permissions_table();	}
		do_action( 'ndizi_upgrade_tables' );
	}

#########################################################

	function make_clients_table(){
		global $wpdb;
		$wpdb->query("CREATE TABLE IF NOT EXISTS `$this->client_table` (
						`id`			INT				NOT NULL	AUTO_INCREMENT	PRIMARY KEY			COMMENT 'Client ID',
						`name`			VARCHAR( 30 )	NOT NULL										COMMENT 'Client Name',
						`phone`			VARCHAR( 20 )	NOT NULL	DEFAULT ''							COMMENT 'Client Phone',
						`email`			VARCHAR( 50 )	NOT NULL	DEFAULT ''							COMMENT 'Client Email',
						`site`			VARCHAR( 100 )	NOT NULL	DEFAULT ''							COMMENT 'Client Site',
						`description`	TEXT			NOT NULL	DEFAULT ''							COMMENT 'Client Description',
						`address`		VARCHAR( 255 )	NOT NULL	DEFAULT ''							COMMENT 'Client Address',
						`access_key`	VARCHAR( 32 )	NOT NULL	DEFAULT ''							COMMENT 'Client Access Key',
						`active`		ENUM('y','n')	NOT NULL	DEFAULT 'y'							COMMENT 'Client Active',
						`modified`		TIMESTAMP		NOT NULL	DEFAULT CURRENT_TIMESTAMP
																	ON UPDATE CURRENT_TIMESTAMP			COMMENT 'Last Modified',
						UNIQUE KEY `access_key` (`access_key`)
					) ENGINE = MYISAM COMMENT = 'Ndizi Clients Table'");
	}
	function make_projects_table(){
		global $wpdb;
		$wpdb->query("CREATE TABLE IF NOT EXISTS `$this->project_table` (
						`id`			INT				NOT NULL	AUTO_INCREMENT	PRIMARY KEY			COMMENT 'Project ID',
						`client_id`		INT				NOT NULL	DEFAULT 0							COMMENT 'Client ID',
						`name`			VARCHAR( 30 )	NOT NULL										COMMENT 'Project Name',
						`description`	TEXT			NOT NULL	DEFAULT ''							COMMENT 'Project Description',
						`active`		ENUM('y','n')	NOT NULL	DEFAULT 'y'							COMMENT 'Project Active',
						`modified`		TIMESTAMP		NOT NULL	DEFAULT CURRENT_TIMESTAMP
																	ON UPDATE CURRENT_TIMESTAMP			COMMENT 'Last Modified'
					) ENGINE = MYISAM COMMENT = 'Ndizi Projects Table'");
	}
	function make_tasks_table(){
		global $wpdb;
		$wpdb->query("CREATE TABLE IF NOT EXISTS `$this->task_table` (
						`id`			INT				NOT NULL	AUTO_INCREMENT	PRIMARY KEY			COMMENT 'Task ID',
						`client_id`		INT				NOT NULL	DEFAULT 0							COMMENT 'Client ID',
						`project_id`	INT				NOT NULL	DEFAULT 0							COMMENT 'Project ID',
						`user_id`		INT				NOT NULL	DEFAULT 0							COMMENT 'User Assigned To',
						`name`			VARCHAR( 30 )	NOT NULL										COMMENT 'Task Name',
						`description`	TEXT			NOT NULL	DEFAULT ''							COMMENT 'Task Description',
						`priority`		INT( 2 )		NOT NULL	DEFAULT 0							COMMENT 'Task Priority',
						`status`		VARCHAR( 100 )	NOT NULL	DEFAULT ''							COMMENT 'Task Status',
						`modified`		TIMESTAMP		NOT NULL	DEFAULT CURRENT_TIMESTAMP
																	ON UPDATE CURRENT_TIMESTAMP			COMMENT 'Last Modified'
					) ENGINE = MYISAM COMMENT = 'Ndizi Task Table'");
	}
	function make_time_table(){
		global $wpdb;
		$wpdb->query("CREATE TABLE IF NOT EXISTS `$this->time_table` (
						`id`			INT				NOT NULL	AUTO_INCREMENT	PRIMARY KEY			COMMENT 'Time ID',
						`client_id`		INT				NOT NULL	DEFAULT 0							COMMENT 'Client ID',
						`project_id`	INT				NOT NULL	DEFAULT 0							COMMENT 'Project ID',
						`task_id`		INT				NOT NULL	DEFAULT 0							COMMENT 'Task ID',
						`user_id`		INT				NOT NULL	DEFAULT 0							COMMENT 'User ID',
						`invoice_id`	INT				NOT NULL	DEFAULT 0							COMMENT 'Invoice ID',
						`description`	TEXT			NOT NULL	DEFAULT ''							COMMENT 'Summary Of Actions',
						`duration`		TIME			NOT NULL	DEFAULT 0							COMMENT 'Time Duration',
						`date`			DATE			NOT NULL	DEFAULT 0							COMMENT 'Time Date',
						`modified`		TIMESTAMP		NOT NULL	DEFAULT CURRENT_TIMESTAMP
																	ON UPDATE CURRENT_TIMESTAMP			COMMENT 'Last Modified'
					) ENGINE = MYISAM COMMENT = 'Ndizi Time Table'");
	}
	function make_invoices_table(){
		global $wpdb;
		$wpdb->query("CREATE TABLE IF NOT EXISTS `$this->invoice_table` (
						`id`			INT				NOT NULL	AUTO_INCREMENT	PRIMARY KEY			COMMENT 'Invoice ID',
						`client_id`		INT				NOT NULL	DEFAULT 0							COMMENT 'Client ID',
						`project_id`	INT				NOT NULL	DEFAULT 0							COMMENT 'Project ID',
						`name`			VARCHAR( 30 )	NOT NULL										COMMENT 'Invoice Name',
						`description`	TEXT			NOT NULL	DEFAULT ''							COMMENT 'Invoice Description',
						`notes`			TEXT			NOT NULL	DEFAULT ''							COMMENT 'Invoice Internal Notes',
						`terms`			VARCHAR( 255 )	NOT NULL	DEFAULT ''							COMMENT 'Invoice Terms',
						`total`			DECIMAL( 9,2 )	NOT NULL	DEFAULT 0							COMMENT 'Invoice Amount Due',
						`paid`			DECIMAL( 9,2 )	NOT NULL	DEFAULT 0							COMMENT 'Invoice Amount Paid',
						`status`		INT( 2 )		NOT NULL	DEFAULT 0							COMMENT 'Invoice Status',
						`date`			DATE			NOT NULL	DEFAULT 0							COMMENT 'Invoice Date',
						`modified`		TIMESTAMP		NOT NULL	DEFAULT CURRENT_TIMESTAMP
																	ON UPDATE CURRENT_TIMESTAMP			COMMENT 'Last Modified'
					) ENGINE = MYISAM COMMENT = 'Ndizi Invoice Table'");
	}
	function make_attachments_table(){
		global $wpdb;
		$wpdb->query("CREATE TABLE IF NOT EXISTS `$this->attachment_table` (
						`id`			INT				NOT NULL 	AUTO_INCREMENT	PRIMARY KEY 		COMMENT 'Attachment ID',
						`parent`		INT				NOT NULL	DEFAULT 0							COMMENT 'Parent Attachment',
						`user_id`		INT				NOT NULL 	DEFAULT 0							COMMENT 'User ID or 0 for Client',
						`item_id`		INT				NOT NULL 	DEFAULT 0							COMMENT 'Attached to Item ID',
						`item_type`		VARCHAR( 30 )	NOT NULL 	DEFAULT ''							COMMENT 'Attached to Item Type (Client, Project, Task)',
						`message`		TEXT			NOT NULL 	DEFAULT ''							COMMENT 'Message',
						`file`			VARCHAR( 100 )	NOT NULL 	DEFAULT ''							COMMENT 'File Attachment',
						`when`			DATETIME		NOT NULL 	DEFAULT 0							COMMENT 'When Submitted',
						`modified`		TIMESTAMP		NOT NULL 	DEFAULT CURRENT_TIMESTAMP 
																	ON UPDATE CURRENT_TIMESTAMP 		COMMENT 'Last Modified'
					) ENGINE = MYISAM COMMENT = 'Ndizi Attachment Table'");
	}
	function make_permissions_table(){
		global $wpdb;
		$wpdb->query("CREATE TABLE IF NOT EXISTS `$this->permission_table` (
						`id`			INT				NOT NULL 	AUTO_INCREMENT	PRIMARY KEY 		COMMENT 'Permission ID',
						`user_id`		INT				NOT NULL	DEFAULT 0							COMMENT 'User ID or 0 for Client',
						`item_id`		INT				NOT NULL 	DEFAULT 0							COMMENT 'Permissions for Item ID',
						`item_type`		VARCHAR( 30 )	NOT NULL 	DEFAULT ''							COMMENT 'Permissions for Item Type',
						`permissions`	longtext		NOT NULL	DEFAULT ''							COMMENT 'What permissions are granted',
						`modified`		TIMESTAMP		NOT NULL 	DEFAULT CURRENT_TIMESTAMP 
																	ON UPDATE CURRENT_TIMESTAMP 		COMMENT 'Last Modified'
					) ENGINE = MYISAM COMMENT = 'Ndizi Permission Table'");
	}

#########################################################

	function upgrade_clients_table(){
		global $wpdb;
		// Since version 0.9.6.0
		if( !$this->table_has_column( $this->client_table, 'active' ) ){
			$wpdb->query( "ALTER TABLE `$this->client_table` ADD `active` ENUM('y','n') NOT NULL DEFAULT 'y' COMMENT 'Client Active' AFTER `access_key` " );
		}
		// Since version 0.9.6.8
		if( !$this->table_has_key( $this->client_table, 'access_key' ) ){
			$wpdb->query( "ALTER TABLE `$this->client_table` ADD UNIQUE ( `access_key` ) " );
		}
	}
	function upgrade_projects_table(){
		global $wpdb;
		// Since version 0.9.6.0
		if( !$this->table_has_column( $this->project_table, 'active' ) ){
			$wpdb->query( "ALTER TABLE `$this->project_table` ADD `active` ENUM('y','n') NOT NULL DEFAULT 'y' COMMENT 'Project Active' AFTER `description` " );
		}
	}
	function upgrade_tasks_table(){}
	function upgrade_time_table(){}
	function upgrade_invoices_table(){
		global $wpdb;
		// Since version 0.9.5.6
		if( !$this->table_has_column( $this->invoice_table, 'terms' ) ){
			$wpdb->query( "ALTER TABLE `$this->invoice_table` ADD `terms` VARCHAR( 255 ) NOT NULL DEFAULT '' COMMENT 'Invoice Terms' AFTER `notes` " );
		}
	}
	function upgrade_attachments_table(){}
	function upgrade_permissions_table(){}

#########################################################

	function _count( $table, $args = Array() ){
		$args = apply_filters( 'ndizi_count_'.$table, apply_filters( 'ndizi_count_things', $args ) );
		global $wpdb;
		$sql = "SELECT COUNT( `id` ) FROM `$table` ";
		if( count( $args ) ) {
			$sql .= " WHERE " . implode( " AND ", $this->_parse( $args ) );
		}
		return $wpdb->get_var( $sql );
	}
	function client_count(		$args = Array() ){	return $this->_count( $this->client_table.'` `c',		$args );	}
	function project_count(		$args = Array() ){	return $this->_count( $this->project_table.'` `p',		$args );	}
	function task_count(		$args = Array() ){	return $this->_count( $this->task_table.'` `t',			$args );	}
	function time_count(		$args = Array() ){	return $this->_count( $this->time_table.'` `ti',		$args );	}
	function invoice_count(		$args = Array() ){	return $this->_count( $this->invoice_table.'` `i',		$args );	}
	function attachment_count(	$args = Array() ){	return $this->_count( $this->attachment_table.'` `a',	$args );	}
	function permission_count(	$args = Array() ){	return $this->_count( $this->permission_table.'` `pe',	$args );	}

// ===========================

	function time_total( $args = Array() ){
		global $wpdb;
		$sql = "SELECT Sec_to_Time( Sum( Time_to_Sec( `duration` ) ) ) FROM $this->time_table";
		$conditions = $this->_parse( $args );
		if( count( $conditions ) ) {
			$sql .= " WHERE" . implode( " AND ", $conditions );
		}
		return ( $returnMe = $wpdb->get_var( $sql ) ) ? $returnMe : "0:00" ;
	}
	function invoice_total( $type = 'total', $args = Array() ){
		global $wpdb;
			$sql = "SELECT Sum( `total` ) FROM $this->invoice_table";
			$conditions = $this->_parse( $args );
			if( count( $conditions ) ) {
				$sql .= " WHERE" . implode( " AND ", $conditions );
			}
		$total = $wpdb->get_var( $sql );
			$sql = "SELECT Sum( `paid` ) FROM $this->invoice_table";
			$conditions = $this->_parse( $args );
			if( count( $conditions ) ) {
				$sql .= " WHERE" . implode( " AND ", $conditions );
			}
		$paid = $wpdb->get_var( $sql );
		switch( $type ){
			case 'total':		return $total;				break;
			case 'paid':		return $paid;				break;
			case 'balance':		return $total-$paid;		break;
			default:			return NULL;				break;
		}
	}

// ===========================
	
	function get_clients( $page = NULL, $args = Array() ){
		$args = apply_filters( 'ndizi_get_clients', apply_filters( 'ndizi_get_things', $args ) );
		global $wpdb;
		$sql = "SELECT			$this->client_fields
				FROM			$this->client_table c";
		if( count( $args ) ) {
			$sql .= " WHERE " . implode( " AND ", $this->_parse( $args ) );
		}
		$sql .= " ORDER BY c.id ";
		if( !is_null( $page ) ){
			$sql .= " LIMIT ".( ( intval( $page ) - 1 ) * $this->pagination )." , $this->pagination ";
		}
		return $wpdb->get_results( $sql, ARRAY_A );
	}
	function get_projects( $page = NULL, $args = Array() ){
		$args = apply_filters( 'ndizi_get_projects', apply_filters( 'ndizi_get_things', $args ) );
		global $wpdb;
		$sql = "SELECT			$this->project_fields,
								$this->client_fields
				FROM 			$this->project_table p 
					LEFT JOIN 	$this->client_table c 	ON p.client_id = c.id";
		if( count( $args ) ) {
			$sql .= " WHERE " . implode( " AND ", $this->_parse( $args ) );
		}
		$sql .= " ORDER BY c.id, p.id ";
		if( !is_null( $page ) ){
			$sql .= " LIMIT ".( ( intval( $page ) - 1 ) * $this->pagination )." , $this->pagination ";
		}
		return $wpdb->get_results( $sql, ARRAY_A );
	}
	function get_tasks( $page = NULL, $args = Array() ){
		$args = apply_filters( 'ndizi_get_tasks', apply_filters( 'ndizi_get_things', $args ) );
		global $wpdb;
		$sql = "SELECT			$this->task_fields,
								$this->user_fields,
								$this->project_fields,
								$this->client_fields
				FROM 			$this->task_table t 
					LEFT JOIN 	$this->user_table u 	ON t.user_id = u.ID
					LEFT JOIN 	$this->project_table p 	ON t.project_id = p.id
					LEFT JOIN 	$this->client_table c 	ON p.client_id = c.id";
		if( count( $args ) ) {
			$sql .= " WHERE " . implode( " AND ", $this->_parse( $args ) );
		}
		$sql .= " ORDER BY c.id, p.id, t.id ";
		if( !is_null( $page ) ){
			$sql .= " LIMIT ".( ( intval( $page ) - 1 ) * $this->pagination )." , $this->pagination ";
		}
		return $wpdb->get_results( $sql, ARRAY_A );
	}
	function get_times( $page = NULL, $args = Array() ){
		$args = apply_filters( 'ndizi_get_times', apply_filters( 'ndizi_get_things', $args ) );
		global $wpdb;
		$sql = "SELECT			$this->time_fields,
								$this->task_fields,
								$this->user_fields,
								$this->project_fields,
								$this->client_fields,
								$this->invoice_fields
				FROM 			$this->time_table ti
					LEFT JOIN 	$this->task_table t 	ON ti.task_id = t.id
					LEFT JOIN 	$this->user_table u 	ON ti.user_id = u.ID
					LEFT JOIN 	$this->project_table p 	ON ti.project_id = p.id
					LEFT JOIN 	$this->client_table c 	ON p.client_id = c.id
					LEFT JOIN 	$this->invoice_table i 	ON ti.invoice_id = i.id";
		if( count( $args ) ) {
			$sql .= " WHERE " . implode( " AND ", $this->_parse( $args ) );
		}
		$sql .= " ORDER BY ti.date DESC ";
		if( !is_null( $page ) ){
			$sql .= " LIMIT ".( ( intval( $page ) - 1 ) * $this->pagination )." , $this->pagination ";
		}
		return $wpdb->get_results( $sql, ARRAY_A );
	}
	function get_invoices( $page = NULL, $args = Array() ){
		$args = apply_filters( 'ndizi_get_invoices', apply_filters( 'ndizi_get_things', $args ) );
		global $wpdb;
		$sql = "SELECT			$this->invoice_fields,
								$this->project_fields,
								$this->client_fields
				FROM 			$this->invoice_table i
					LEFT JOIN 	$this->project_table p 	ON i.project_id = p.id
					LEFT JOIN 	$this->client_table c 	ON p.client_id = c.id";
		if( count( $args ) ) {
			$sql .= " WHERE " . implode( " AND ", $this->_parse( $args ) );
		}
		$sql .= " ORDER BY i.date DESC "; 
		if( !is_null( $page ) ){
			$sql .= " LIMIT ".( ( intval( $page ) - 1 ) * $this->pagination )." , $this->pagination ";
		}
		return $wpdb->get_results( $sql, ARRAY_A );
	}
	function get_attachments( $page = NULL, $args = Array() ){
		$args = apply_filters( 'ndizi_get_attachments', apply_filters( 'ndizi_get_things', $args ) );
		global $wpdb;
		$sql = "SELECT			$this->attachment_fields
				FROM			$this->attachment_table a";
		if( count( $args ) ) {
			$sql .= " WHERE " . implode( " AND ", $this->_parse( $args ) );
		}
		$sql .= " ORDER BY a.id ";
		if( !is_null( $page ) ){
			$sql .= " LIMIT ".( ( intval( $page ) - 1 ) * $this->pagination )." , $this->pagination ";
		}
		return $wpdb->get_results( $sql, ARRAY_A );
	}
	function get_permissions( $page = NULL, $args = Array() ){
		$args = apply_filters( 'ndizi_get_permissions', apply_filters( 'ndizi_get_things', $args ) );
		global $wpdb;
		$sql = "SELECT			$this->permission_fields
				FROM			$this->permission_table pe";
		if( count( $args ) ) {
			$sql .= " WHERE " . implode( " AND ", $this->_parse( $args ) );
		}
		$sql .= " ORDER BY pe.id ";
		if( !is_null( $page ) ){
			$sql .= " LIMIT ".( ( intval( $page ) - 1 ) * $this->pagination )." , $this->pagination ";
		}
		return $wpdb->get_results( $sql, ARRAY_A );
	}
	
// ===========================
	
	function get_client( $id ){
		global $wpdb;
		$sql = "SELECT			$this->client_fields
				FROM			$this->client_table c
				WHERE			c.id = " . intval( $id );
		return $wpdb->get_row( $sql, ARRAY_A );
	}
	function get_project( $id ){
		global $wpdb;
		$sql = "SELECT			$this->project_fields,
								$this->client_fields
				FROM 			$this->project_table p 
					LEFT JOIN 	$this->client_table c 	ON p.client_id = c.id
				WHERE			p.id = " . intval( $id );
		return $wpdb->get_row( $sql, ARRAY_A );
	}
	function get_task( $id ){
		global $wpdb;
		$sql = "SELECT			$this->task_fields,
								$this->user_fields,
								$this->project_fields,
								$this->client_fields
				FROM 			$this->task_table t 
					LEFT JOIN 	$this->user_table u 	ON t.user_id = u.ID
					LEFT JOIN 	$this->project_table p 	ON t.project_id = p.id
					LEFT JOIN 	$this->client_table c 	ON p.client_id = c.id
				WHERE			t.id = " . intval( $id );
		return $wpdb->get_row( $sql, ARRAY_A );
	}
	function get_time( $id ){
		global $wpdb;
		$sql = "SELECT			$this->time_fields,
								$this->task_fields,
								$this->user_fields,
								$this->project_fields,
								$this->client_fields,
								$this->invoice_fields
				FROM 			$this->time_table ti
					LEFT JOIN 	$this->task_table t 	ON ti.task_id = t.id
					LEFT JOIN 	$this->user_table u 	ON ti.user_id = u.ID
					LEFT JOIN 	$this->project_table p 	ON ti.project_id = p.id
					LEFT JOIN 	$this->client_table c 	ON p.client_id = c.id
					LEFT JOIN	$this->invoice_table i	ON ti.invoice_id = i.id
				WHERE			ti.id = " . intval( $id );
		return $wpdb->get_row( $sql, ARRAY_A );
	}
	function get_invoice( $id ){
		global $wpdb;
		$sql = "SELECT			$this->invoice_fields,
								$this->project_fields,
								$this->client_fields
				FROM 			$this->invoice_table i
					LEFT JOIN 	$this->project_table p 	ON i.project_id = p.id
					LEFT JOIN 	$this->client_table c 	ON p.client_id = c.id
				WHERE			i.id = " . intval( $id );
		return $wpdb->get_row( $sql, ARRAY_A );
	}
	function get_attachment( $id ){
		global $wpdb;
		$sql = "SELECT			$this->attachment_fields
				FROM 			$this->attachment_table a
				WHERE			a.id = " . intval( $id );
		return $wpdb->get_row( $sql, ARRAY_A );
	}
	function get_permission( $id ){
		global $wpdb;
		$sql = "SELECT			$this->permission_fields
				FROM 			$this->permission_table pe
				WHERE			pe.id = " . intval( $id );
		return $wpdb->get_row( $sql, ARRAY_A );
	}

// ===========================

	function _button_new( $type, $text ){
		do_action( 'ndizi_button_new', $type, $text );
		if( isset( $this->page_slugs[$type] ) ){
			return "\r\n\t<a href=\"".$this->friendly_page_link( $type, FALSE )."&amp;action=add\" class=\"button-primary\">$text &rarr;</a>";
		}
		return NULL;
	}
	function print_button_new_client(){		echo $this->_button_new( 'clients', 	__('New Client',NDZ) 		);	}
	function print_button_new_project(){	echo $this->_button_new( 'projects', 	__('New Project',NDZ) 		);	}
	function print_button_new_task(){		echo $this->_button_new( 'tasks', 		__('New Task',NDZ) 			);	}
	function print_button_new_time(){		echo $this->_button_new( 'times', 		__('New Time Report',NDZ) 	);	}
	function print_button_new_invoice(){	echo $this->_button_new( 'invoices', 	__('New Invoice',NDZ) 		);	}
	function print_button_new_attachment(){	echo $this->_button_new( 'attachments', __('New Attachment',NDZ) 	);	}
	function print_button_new_permission(){	echo $this->_button_new( 'permissions', __('New Permission',NDZ) 	);	}

// ===========================

	function _link_edit( $id, $type, $text ){
		do_action( 'ndizi_link_edit', $id, $type, $text );
		if( isset( $this->page_slugs[$type] ) ){
			return '<a class="edit-link" href="'.$this->friendly_page_link( $type, FALSE ).'&amp;action=edit&amp;id='.intval($id).'">'.$text.' &rarr;</a>';
		}
		return NULL;
	}
	function get_link_edit_client(		$id ){	return $this->_link_edit( $id, 'clients',		__('Edit Client',NDZ) 		);	}
	function get_link_edit_project(		$id ){	return $this->_link_edit( $id, 'projects',		__('Edit Project',NDZ) 		);	}
	function get_link_edit_task(		$id ){	return $this->_link_edit( $id, 'tasks',			__('Edit Task',NDZ) 		);	}
	function get_link_edit_time(		$id ){	return $this->_link_edit( $id, 'times',			__('Edit Time Report',NDZ) 	);	}
	function get_link_edit_invoice(		$id ){	return $this->_link_edit( $id, 'invoices',		__('Edit Invoice',NDZ) 		);	}
	function get_link_edit_attachment(	$id ){	return $this->_link_edit( $id, 'attachments',	__('Edit Attachment',NDZ) 	);	}
	function get_link_edit_permission(	$id ){	return $this->_link_edit( $id, 'permissions',	__('Edit Permission',NDZ) 	);	}
	
// ===========================

	function _link_delete( $id, $type, $text ){
		do_action( 'ndizi_link_delete', $id, $type, $text );
		if( isset( $this->page_slugs[$type] ) ){
			return '<a class="delete-link" href="'.$this->friendly_page_link( $type, FALSE ).'&amp;action=delete&amp;id='.intval($id).'">'.$text.' &rarr;</a>';
		}
		return NULL;
	}
	function get_link_delete_client(		$id ){	return $this->_link_delete( $id, 'clients',		__('Delete Client',NDZ) 		);	}
	function get_link_delete_project(		$id ){	return $this->_link_delete( $id, 'projects',	__('Delete Project',NDZ) 		);	}
	function get_link_delete_task(			$id ){	return $this->_link_delete( $id, 'tasks',		__('Delete Task',NDZ) 			);	}
	function get_link_delete_time(			$id ){	return $this->_link_delete( $id, 'times',		__('Delete Time Report',NDZ) 	);	}
	function get_link_delete_invoice(		$id ){	return $this->_link_delete( $id, 'invoices',	__('Delete Invoice',NDZ) 		);	}
	function get_link_delete_attachment(	$id ){	return $this->_link_delete( $id, 'attachments',	__('Delete Attachment',NDZ) 	);	}
	function get_link_delete_permission(	$id ){	return $this->_link_delete( $id, 'permissions',	__('Delete Permission',NDZ) 	);	}
	
// ===========================
	
	function _add( $table, $args = Array() ){
		$args = apply_filters( 'ndizi_add_thing', $args, $table );
		global $wpdb;
		$sql = "INSERT INTO `$table` ";
		if( count( $args ) ) {
			$sql .= " SET " . implode( " , ", $this->_parse( $args ) );
		}
		$wpdb->query( $sql );
		do_action( 'ndizi_added_thing', $table, $args, $wpdb->insert_id );
		return $wpdb->insert_id;
	}
	function add_client(		$args = Array() )	{	return $this->_add( $this->client_table,		$args );	}
	function add_project(		$args = Array() )	{	return $this->_add( $this->project_table,		$args );	}
	function add_task(			$args = Array() )	{	return $this->_add( $this->task_table,			$args );	}
	function add_time(			$args = Array() )	{	return $this->_add( $this->time_table,			$args );	}
	function add_invoice(		$args = Array() )	{	return $this->_add( $this->invoice_table,		$args );	}
	function add_attachment(	$args = Array() )	{	return $this->_add( $this->attachment_table,	$args );	}
	function add_permission(	$args = Array() )	{	return $this->_add( $this->permission_table,	$args );	}

// ===========================
	
	function _edit( $table, $id, $args = Array() ){
		$args = apply_filters( 'ndizi_edit_thing', $args, $table, $id );
		global $wpdb;
		$sql = "UPDATE `$table` ";
		if( count( $args ) ) { $sql .= " SET " . implode( " , ", $this->_parse( $args ) ); }
		$sql .= " WHERE `id` = '".intval( $id )."' LIMIT 1 ";
		return $wpdb->query( $sql );
	}
	function edit_client(		$id, $args = Array() )	{	return $this->_edit( $this->client_table,		$id, $args );	}
	function edit_project(		$id, $args = Array() )	{	return $this->_edit( $this->project_table,		$id, $args );	}
	function edit_task(			$id, $args = Array() )	{	return $this->_edit( $this->task_table,			$id, $args );	}
	function edit_time(			$id, $args = Array() )	{	return $this->_edit( $this->time_table,			$id, $args );	}
	function edit_invoice(		$id, $args = Array() )	{	return $this->_edit( $this->invoice_table,		$id, $args );	}
	function edit_attachment(	$id, $args = Array() )	{	return $this->_edit( $this->attachment_table,	$id, $args );	}
	function edit_permission(	$id, $args = Array() )	{	return $this->_edit( $this->permission_table,	$id, $args );	}
	
// ===========================

	function admin_save_client( $id = NULL ){
		$type = 'client';
		$params = Array(
			'name'			=>	'name',
			'phone'			=>	'phone',
			'email'			=>	'email',
			'site'			=>	'site',
			'description'	=>	'description',
			'address'		=>	'address',
			'access_key'	=>	'access_key',
			'active'		=>	'active',
		);
		$args = $this->_params_to_args( $params, $_POST );
		if( !$args ) return;
		if( is_null( $id ) ){
			return $this->{'add_'.$type}( $args );
		} else {
			return $this->{'edit_'.$type}( $id, $args );
		}
	}
	function admin_save_project( $id = NULL ){
		$type = 'project';
		$params = Array(
			'client_id'		=>	'client_id',
			'name'			=>	'name',
			'description'	=>	'description',
			'active'		=>	'active',
		);
		$args = $this->_params_to_args( $params, $_POST );
		if( !$args ) return;
		if( is_null( $id ) ){
			return $this->{'add_'.$type}( $args );
		} else {
			return $this->{'edit_'.$type}( $id, $args );
		}
	}
	function admin_save_task( $id = NULL ){
		$type = 'task';
		$params = Array(
			'client_id'		=>	'client_id',
			'project_id'	=>	'project_id',
			'user_id'		=>	'user_id',
			'name'			=>	'name',
			'description'	=>	'description',
			'priority'		=>	'priority',
			'status'		=>	'status',
		);
		$args = $this->_params_to_args( $params, $_POST );
		if( isset( $args['project_id'] ) && !isset( $args['client_id'] ) ){
			$args['client_id'] = $this->get_client_from_project( $args['project_id'] );
		}
		if( !$args ) return;
		if( is_null( $id ) ){
			return $this->{'add_'.$type}( $args );
		} else {
			return $this->{'edit_'.$type}( $id, $args );
		}
	}
	function admin_save_time( $id = NULL ){
		$type = 'time';
		$params = Array(
			'client_id'		=>	'client_id',
			'project_id'	=>	'project_id',
			'task_id'		=>	'task_id',
			'user_id'		=>	'user_id',
			'invoice_id'	=>	'invoice_id',
			'description'	=>	'description',
			'duration'		=>	'duration',
			'date'			=>	'date',
		);
		$args = $this->_params_to_args( $params, $_POST );
		if( isset( $args['project_id'] ) && !isset( $args['client_id'] ) ){
			$args['client_id'] = $this->get_client_from_project( $args['project_id'] );
		}
		if( !$args ) return;
		if( is_null( $id ) ){
			return $this->{'add_'.$type}( $args );
		} else {
			return $this->{'edit_'.$type}( $id, $args );
		}
	}
	function admin_save_invoice( $id = NULL ){
		$type = 'invoice';
		$params = Array(
			'client_id'		=>	'client_id',
			'project_id'	=>	'project_id',
			'name'			=>	'name',
			'description'	=>	'description',
			'notes'			=>	'notes',
			'terms'			=>	'terms',
			'total'			=>	'total',
			'paid'			=>	'paid',
			'status'		=>	'status',
			'date'			=>	'date',
		);
		$args = $this->_params_to_args( $params, $_POST );
		if( isset( $args['project_id'] ) && !isset( $args['client_id'] ) ){
			$args['client_id'] = $this->get_client_from_project( $args['project_id'] );
		}
		if( !$args ) return;
		if( is_null( $id ) ){
			return $this->{'add_'.$type}( $args );
		} else {
			return $this->{'edit_'.$type}( $id, $args );
		}
	}
	function admin_save_attachment( $id = NULL ){
		$type = 'attachment';
		$params = Array(
			'parent'		=>	'parent',
			'user_id'		=>	'user_id',
			'item_id'		=>	'item_id',
			'item_type'		=>	'item_type',
			'message'		=>	'message',
			'file'			=>	'file',
			'when'			=>	'when',
		);
		$args = $this->_params_to_args( $params, $_POST );
		if( !$args ) return;
		if( is_null( $id ) ){
			return $this->{'add_'.$type}( $args );
		} else {
			return $this->{'edit_'.$type}( $id, $args );
		}
	}
	function admin_save_permission( $id = NULL ){
		$type = 'permission';
		$params = Array(
			'user_id'		=>	'user_id',
			'item_id'		=>	'item_id',
			'item_type'		=>	'item_type',
			'permissions'	=>	'permissions',
		);
		$args = $this->_params_to_args( $params, $_POST );
		if( !$args ) return;
		if( is_null( $id ) ){
			return $this->{'add_'.$type}( $args );
		} else {
			return $this->{'edit_'.$type}( $id, $args );
		}
	}
	
// ===========================

	function delete_client( $id ){
		do_action( 'ndizi_delete_client', $id );
		global $wpdb;
		$sql = "DELETE FROM		`$this->client_table` 
				WHERE			`id` = '".intval( $id )."'
				LIMIT			1 ";
		$wpdb->query( $sql );
		$sql = "DELETE FROM		`$this->project_table` 
				WHERE			`client_id` = '".intval( $id )."' ";
		$wpdb->query( $sql );
		$sql = "DELETE FROM		`$this->task_table` 
				WHERE			`client_id` = '".intval( $id )."' ";
		$wpdb->query( $sql );
		$sql = "DELETE FROM		`$this->time_table` 
				WHERE			`client_id` = '".intval( $id )."' ";
		$wpdb->query( $sql );
	}
	function delete_project( $id ){
		do_action( 'ndizi_delete_project', $id );
		global $wpdb;
		$sql = "DELETE FROM		`$this->project_table` 
				WHERE			`id` = '".intval( $id )."'
				LIMIT			1 ";
		$wpdb->query( $sql );
		$sql = "DELETE FROM		`$this->task_table` 
				WHERE			`project_id` = '".intval( $id )."' ";
		$wpdb->query( $sql );
		$sql = "DELETE FROM		`$this->time_table` 
				WHERE			`project_id` = '".intval( $id )."' ";
		$wpdb->query( $sql );
	}
	function delete_task( $id ){
		do_action( 'ndizi_delete_task', $id );
		global $wpdb;
		$sql = "DELETE FROM		`$this->task_table` 
				WHERE			`id` = '".intval( $id )."'
				LIMIT			1 ";
		$wpdb->query( $sql );
		$sql = "DELETE FROM		`$this->time_table` 
				WHERE			`task_id` = '".intval( $id )."' ";
		$wpdb->query( $sql );
	}
	function delete_time( $id ){
		do_action( 'ndizi_delete_time', $id );
		global $wpdb;
		$sql = "DELETE FROM		`$this->time_table` 
				WHERE			`id` = '".intval( $id )."'
				LIMIT			1 ";
		$wpdb->query( $sql );
	}
	function delete_invoice( $id ){
		do_action( 'ndizi_delete_invoice', $id );
		global $wpdb;
		$sql = "DELETE FROM		`$this->invoice_table` 
				WHERE			`id` = '".intval( $id )."'
				LIMIT			1 ";
		$wpdb->query( $sql );
		// What to do about times assigned to this invoice?
	}
	function delete_attachment( $id ){
		do_action( 'ndizi_delete_attachment', $id );
		global $wpdb;
		$sql = "DELETE FROM		$this->attachment_table` 
				WHERE			`id` = '".intval( $id )."'
				LIMIT			1 ";
		$wpdb->query( $sql );
	}
	function delete_permission( $id ){
		do_action( 'ndizi_delete_permission', $id );
		global $wpdb;
		$sql = "DELETE FROM		`$this->permission_table` 
				WHERE			`id` = '".intval( $id )."'
				LIMIT			1 ";
		$wpdb->query( $sql );
	}
	
// ===========================
	
	function delete_clients( $ids ){
		if( is_array( $ids ) ){
			foreach( $ids as $id ){
				$this->delete_client( $id );
			}
		}
	}
	function delete_projects( $ids ){
		if( is_array( $ids ) ){
			foreach( $ids as $id ){
				$this->delete_project( $id );
			}
		}
	}
	function delete_tasks( $ids ){
		if( is_array( $ids ) ){
			foreach( $ids as $id ){
				$this->delete_task( $id );
			}
		}
	}
	function delete_times( $ids ){
		if( is_array( $ids ) ){
			foreach( $ids as $id ){
				$this->delete_time( $id );
			}
		}
	}
	function delete_invoices( $ids ){
		if( is_array( $ids ) ){
			foreach( $ids as $id ){
				$this->delete_invoice( $id );
			}
		}
	}
	function delete_attachments( $ids ){
		if( is_array( $ids ) ){
			foreach( $ids as $id ){
				$this->delete_attachment( $id );
			}
		}
	}
	function delete_permissions( $ids ){
		if( is_array( $ids ) ){
			foreach( $ids as $id ){
				$this->delete_permission( $id );
			}
		}
	}
	
// ===========================
	
	function print_clients_table( $page = 1, $args = Array() ){
		$type = 'client';
		do_action( 'ndizi_print_'.$type.'s_table', $page, $args );
		if( !$count = $this->{$type.'_count'}( $args ) ){
			echo "\r\n<h3>".sprintf(__('No %ss yet &hellip; why not add one?',NDZ),__($type,NDZ))."</h3>";
			$this->{'print_'.$type.'_form'}();
			return;
		}
		$page = intval( $page );
		$things = $this->{'get_'.$type.'s'}( $page, $args );
		$this->{'print_button_new_'.$type}();
		$this->print_pager( $page, $this->friendly_page_link( $type.'s', FALSE ).$this->_parse_for_url( $args ), $count );
		?>
		<table class="widefat <?php echo $type; ?>s" cellspacing="0">
			<thead>
				<tr><?php	$this->_th( 'cb',			'<input type="checkbox" />',	FALSE,	TRUE	);
							$this->_th( 'id',			__('ID',NDZ),					TRUE,	TRUE	);
							$this->_th( 'name',			__('Name',NDZ),					FALSE,	TRUE	);
							$this->_th( 'phone',		__('Phone',NDZ),				FALSE,	TRUE	);
							$this->_th( 'email',		__('Email',NDZ),				FALSE,	TRUE	);
				#			$this->_th( 'site',			__('Site',NDZ),					FALSE,	TRUE	);
				#			$this->_th( 'description',	__('Description',NDZ),			FALSE,	TRUE	);
				#			$this->_th( 'address',		__('Address',NDZ),				FALSE,	TRUE	);
				#			$this->_th( 'access-key',	__('Access Key',NDZ),			FALSE,	TRUE	);
							$this->_th( 'email-key',	__('Email Key',NDZ),			TRUE,	TRUE	);
							$this->_th( 'projects',		__('Projects',NDZ),				TRUE,	TRUE	);
							$this->_th( 'tasks',		__('Tasks',NDZ),				TRUE,	TRUE	);
							$this->_th( 'times',		__('Time',NDZ),					TRUE,	TRUE	);
							$this->_th( 'active',		__('Active',NDZ),				TRUE,	TRUE	);
							$this->_th( 'edit',			__('Edit',NDZ),					TRUE,	TRUE	);
							$this->_th( 'delete',		__('Delete',NDZ),				TRUE,	TRUE	);	?>
				</tr>
			</thead>
			<tfoot>
				<tr><?php	$this->_th( 'cb',			'<input type="checkbox" />',	FALSE	);
							$this->_th( 'id',			__('ID',NDZ),					TRUE	);
							$this->_th( 'name',			__('Name',NDZ),					FALSE	);
							$this->_th( 'phone',		__('Phone',NDZ),				FALSE	);
							$this->_th( 'email',		__('Email',NDZ),				FALSE	);
				#			$this->_th( 'site',			__('Site',NDZ),					FALSE	);
				#			$this->_th( 'description',	__('Description',NDZ),			FALSE	);
				#			$this->_th( 'address',		__('Address',NDZ),				FALSE	);
				#			$this->_th( 'access-key',	__('Access Key',NDZ),			FALSE	);
							$this->_th( 'email-key',	__('Email Key',NDZ),			TRUE	);
							$this->_th( 'projects',		__('Projects',NDZ),				TRUE	);
							$this->_th( 'tasks',		__('Tasks',NDZ),				TRUE	);
							$this->_th( 'times',		__('Time',NDZ),					TRUE	);
							$this->_th( 'active',		__('Active',NDZ),				TRUE	);
							$this->_th( 'edit',			__('Edit',NDZ),					TRUE	);
							$this->_th( 'delete',		__('Delete',NDZ),				TRUE	);	?>
				</tr>
			</tfoot>
			<tbody id="the-list" class="list:<?php echo $type; ?>s">
				<?php foreach( $things as $i => $client ): $id = $client['client_id']; $_args = array( 'client_id' => $id ); ?>
					<tr id="<?php echo $type; ?>-<?php echo $id; ?>" class="<?php if($i%2) echo 'alternate'; ?>">
						<?php	$this->_rth('client',		$id														);
								$this->_td( 'id',			$client['client_id'],							TRUE	);
								$this->_td( 'name',			$client['client_name'],							FALSE	);
								$this->_td( 'phone',		$client['client_phone'],						FALSE	);
								$this->_td( 'email',		$client['client_email'],						FALSE	);
				#				$this->_td( 'site',			$client['client_site'],							FALSE	);
				#				$this->_td( 'description',	$client['client_description'],					FALSE	);
				#				$this->_td( 'address',		$client['client_address'],						FALSE	);
				#				$this->_td( 'access-key',	$client['client_access_key'],					FALSE	);
								$this->_td( 'email-key',	$this->email_key_link( $id ),					TRUE 	);
								$this->_td( 'projects',		$this->project_count( $_args ),					TRUE	);
								$this->_td( 'tasks',		$this->task_count( $_args ),					TRUE	);
								$this->_td( 'times',		$this->time_total( $_args ),					TRUE	);
								$this->_td( 'active',		$this->_show_active( $client['client_active'] ),TRUE	);
								$this->_td( 'edit',			$this->{'get_link_edit_'.$type}( $id ),			TRUE	);
								$this->_td( 'delete',		$this->{'get_link_delete_'.$type}( $id ),		TRUE	);	?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$this->print_pager( $page, $this->friendly_page_link( $type.'s', FALSE ).$this->_parse_for_url( $args ), $count );
	}
	function print_projects_table( $page = 1, $args = Array() ){
		$type = 'project';
		do_action( 'ndizi_print_'.$type.'s_table', $page, $args );
		if( !$count = $this->{$type.'_count'}( $args ) ){
			echo "\r\n<h3>".sprintf(__('No %ss yet &hellip; why not add one?',NDZ),__($type,NDZ))."</h3>";
			$this->{'print_'.$type.'_form'}();
			return;
		}
		$page = intval( $page );
		$things = $this->{'get_'.$type.'s'}( $page, $args );
		$this->{'print_button_new_'.$type}();
		$this->print_pager( $page, $this->friendly_page_link( $type.'s', FALSE ).$this->_parse_for_url( $args ), $count );
		?>
		<table class="widefat <?php echo $type; ?>s" cellspacing="0">
			<thead>
				<tr><?php	$this->_th( 'cb',			'<input type="checkbox" />',	FALSE,	TRUE	);
							$this->_th( 'id',			__('ID',NDZ),					TRUE,	TRUE	);
							$this->_th( 'name',			__('Name',NDZ),					FALSE,	TRUE	);
							$this->_th( 'attachment',	__('Messages',NDZ),				TRUE,	TRUE	);
							$this->_th( 'client',		__('Client',NDZ),				FALSE,	TRUE	);
							$this->_th( 'tasks',		__('Tasks',NDZ),				TRUE,	TRUE	);
							$this->_th( 'times',		__('Time',NDZ),					TRUE,	TRUE	);
							$this->_th( 'active',		__('Active',NDZ),				TRUE,	TRUE	);
							$this->_th( 'edit',			__('Edit',NDZ),					TRUE,	TRUE	);
							$this->_th( 'delete',		__('Delete',NDZ),				TRUE,	TRUE	);	?>
				</tr>
			</thead>
			<tfoot>
				<tr><?php	$this->_th( 'cb',			'<input type="checkbox" />',	FALSE	);
							$this->_th( 'id',			__('ID',NDZ),					TRUE	);
							$this->_th( 'name',			__('Name',NDZ),					FALSE	);
							$this->_th( 'attachment',	__('Messages',NDZ),				TRUE	);
							$this->_th( 'client',		__('Client',NDZ),				FALSE	);
							$this->_th( 'tasks',		__('Tasks',NDZ),				TRUE	);
							$this->_th( 'times',		__('Time',NDZ),					TRUE	);
							$this->_th( 'active',		__('Active',NDZ),				TRUE	);
							$this->_th( 'edit',			__('Edit',NDZ),					TRUE	);
							$this->_th( 'delete',		__('Delete',NDZ),				TRUE	);	?>
				</tr>
			</tfoot>
			<tbody id="the-list" class="list:<?php echo $type; ?>s">
				<?php foreach( $things as $i => $project ): $id = $project['project_id']; $_args = array( 'project_id' => $id ); ?>
					<tr id="<?php echo $type; ?>-<?php echo $id; ?>" class="<?php if($i%2) echo 'alternate'; ?>">
						<?php	$this->_rth('project',		$id															);
								$this->_td( 'id',			$project['project_id'],								TRUE	);
								$this->_td( 'name',			$project['project_name'],							FALSE	);
								$this->_td( 'attachments',	$this->attachment_count(array('item_type'=>$type,'item_id'=>$id)),		TRUE	);
								$this->_td( 'client',		$project['client_name'],							FALSE	);
								$this->_td( 'tasks',		$this->task_count( $_args ),						TRUE	);
								$this->_td( 'times',		$this->time_total( $_args ),						TRUE	);
								$this->_td( 'active',		$this->_show_active( $project['project_active'] ),	TRUE	);
								$this->_td( 'edit',			$this->{'get_link_edit_'.$type}( $id ),				TRUE	);
								$this->_td( 'delete',		$this->{'get_link_delete_'.$type}( $id ),			TRUE	);	?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$this->print_pager( $page, $this->friendly_page_link( $type.'s', FALSE ).$this->_parse_for_url( $args ), $count );
	}
	function print_tasks_table( $page = 1, $args = Array() ){
		$type = 'task';
		do_action( 'ndizi_print_'.$type.'s_table', $page, $args );
		if( !$count = $this->{$type.'_count'}( $args ) ){
			echo "\r\n<h3>".sprintf(__('No %ss yet &hellip; why not add one?',NDZ),__($type,NDZ))."</h3>";
			$this->{'print_'.$type.'_form'}();
			return;
		}
		$page = intval( $page );
		$things = $this->{'get_'.$type.'s'}( $page, $args );
		$this->{'print_button_new_'.$type}();
		$this->print_pager( $page, $this->friendly_page_link( $type.'s', FALSE ).$this->_parse_for_url( $args ), $count );
		?>
		<table class="widefat <?php echo $type; ?>s" cellspacing="0">
			<thead>
				<tr><?php	$this->_th( 'cb',			'<input type="checkbox" />',	FALSE,	TRUE	);
							$this->_th( 'id',			__('ID',NDZ),					TRUE,	TRUE	);
							$this->_th( 'name',			__('Name',NDZ),					FALSE,	TRUE	);
							$this->_th( 'attachment',	__('Messages',NDZ),				TRUE,	TRUE	);
							$this->_th( 'priority',		__('Priority',NDZ),				TRUE,	TRUE	);
							$this->_th( 'status',		__('Status',NDZ),				FALSE,	TRUE	);
							$this->_th( 'client',		__('Client',NDZ),				FALSE,	TRUE	);
							$this->_th( 'project',		__('Project',NDZ),				FALSE,	TRUE	);
							$this->_th( 'user',			__('User',NDZ),					TRUE,	TRUE	);
							$this->_th( 'edit',			__('Edit',NDZ),					TRUE,	TRUE	);
							$this->_th( 'delete',		__('Delete',NDZ),				TRUE,	TRUE	); ?>
				</tr>
			</thead>
			<tfoot>
				<tr><?php	$this->_th( 'cb',			'<input type="checkbox" />',	FALSE	);
							$this->_th( 'id',			__('ID',NDZ),					TRUE	);
							$this->_th( 'name',			__('Name',NDZ),					FALSE	);
							$this->_th( 'attachment',	__('Messages',NDZ),				TRUE	);
							$this->_th( 'priority',		__('Priority',NDZ),				TRUE	);
							$this->_th( 'status',		__('Status',NDZ),				FALSE	);
							$this->_th( 'client',		__('Client',NDZ),				FALSE	);
							$this->_th( 'project',		__('Project',NDZ),				FALSE	);
							$this->_th( 'user',			__('User',NDZ),					TRUE	);
							$this->_th( 'edit',			__('Edit',NDZ),					TRUE	);
							$this->_th( 'delete',		__('Delete',NDZ),				TRUE	); ?>
				</tr>
			</tfoot>
			<tbody id="the-list" class="list:<?php echo $type; ?>s">
				<?php foreach( $things as $i => $task ): $id = $task['task_id']; ?>
					<tr id="<?php echo $type; ?>-<?php echo $id; ?>" class="<?php if($i%2) echo 'alternate'; ?>">
						<?php	$this->_rth('task',			$id																);
								$this->_td( 'id',			$time['task_id'],										TRUE	);
								$this->_td( 'name',			$task['task_name'],										FALSE	);
								$this->_td( 'attachment',	$this->attachment_count(array('item_type'=>$type,'item_id'=>$id)),		TRUE	);
								$this->_td( 'priority',		$this->priority_readable( $task['task_priority'] ),		TRUE	);
								$this->_td( 'status',		$this->status_readable( $task['task_status'] ),			FALSE	);
								$this->_td( 'client',		$task['client_name'],									FALSE	);
								$this->_td( 'project',		$task['project_name'],									FALSE	);
								$this->_td( 'user',			$task['user_display_name'],								TRUE	);
								$this->_td( 'edit',			$this->{'get_link_edit_'.$type}( $id ),					TRUE	);
								$this->_td( 'delete',		$this->{'get_link_delete_'.$type}( $id ),				TRUE	);	?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$this->print_pager( $page, $this->friendly_page_link( $type.'s', FALSE ).$this->_parse_for_url( $args ), $count );
	}
	function print_times_table( $page = 1, $args = Array() ){
		$type = 'time';
		do_action( 'ndizi_print_'.$type.'s_table', $page, $args );
		if( !$count = $this->{$type.'_count'}( $args ) ){
			echo "\r\n<h3>".sprintf(__('No %ss yet &hellip; why not add one?',NDZ),__($type,NDZ))."</h3>";
			$this->{'print_'.$type.'_form'}();
			return;
		}
		$page = intval( $page );
		$things = $this->{'get_'.$type.'s'}( $page, $args );
		$this->{'print_button_new_'.$type}();
		$this->print_pager( $page, $this->friendly_page_link( $type.'s', FALSE ).$this->_parse_for_url( $args ), $count );
		?>
		<table class="widefat <?php echo $type; ?>s" cellspacing="0">
			<thead>
				<tr><?php	$this->_th( 'cb',			'<input type="checkbox" />',	FALSE,	TRUE	);
							$this->_th( 'id',			__('ID',NDZ),					TRUE,	TRUE	);
							$this->_th( 'description',	__('Description',NDZ),			FALSE,	TRUE	);
							$this->_th( 'date',			__('Date',NDZ),					FALSE,	TRUE	);
							$this->_th( 'duration',		__('Duration',NDZ),				FALSE,	TRUE	);
							$this->_th( 'client',		__('Client',NDZ),				FALSE,	TRUE	);
							$this->_th( 'project',		__('Project',NDZ),				FALSE,	TRUE	);
							$this->_th( 'user',			__('User',NDZ),					FALSE,	TRUE	);
							$this->_th( 'edit',			__('Edit',NDZ),					TRUE,	TRUE	);
							$this->_th( 'delete',		__('Delete',NDZ),				TRUE,	TRUE	); ?>
				</tr>
			</thead>
			<tfoot>
				<tr><?php	$this->_th( 'cb',			'<input type="checkbox" />',	FALSE	);
							$this->_th( 'id',			__('ID',NDZ),					TRUE	);
							$this->_th( 'description',	__('Description',NDZ),			FALSE	);
							$this->_th( 'date',			__('Date',NDZ),					FALSE	);
							$this->_th( 'duration',		__('Duration',NDZ),				FALSE	);
							$this->_th( 'client',		__('Client',NDZ),				FALSE	);
							$this->_th( 'project',		__('Project',NDZ),				FALSE	);
							$this->_th( 'user',			__('User',NDZ),					FALSE	);
							$this->_th( 'edit',			__('Edit',NDZ),					TRUE	);
							$this->_th( 'delete',		__('Delete',NDZ),				TRUE	); ?>
				</tr>
			</tfoot>
			<tbody id="the-list" class="list:<?php echo $type; ?>s">
				<?php foreach( $things as $i => $time ): $id = $time['time_id']; ?>
					<tr id="<?php echo $type; ?>-<?php echo $id; ?>" class="<?php if($i%2) echo 'alternate'; ?>">
						<?php	$this->_rth('time',			$id													);
								$this->_td( 'id',			$time['time_id'],							TRUE	);
								$this->_td( 'description',	$time['time_description'],					FALSE	);
								$this->_td( 'date',			$time['time_date'],							FALSE	);
								$this->_td( 'duration',		$time['time_duration'],						FALSE	);
								$this->_td( 'client',		$time['client_name'],						FALSE	);
								$this->_td( 'project',		$time['project_name'],						FALSE	);
								$this->_td( 'user',			$time['user_display_name'],					FALSE	);
								$this->_td( 'edit',			$this->{'get_link_edit_'.$type}( $id ),		TRUE	);
								$this->_td( 'delete',		$this->{'get_link_delete_'.$type}( $id ),	TRUE	);	?>
						</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$this->print_pager( $page, $this->friendly_page_link( $type.'s', FALSE ).$this->_parse_for_url( $args ), $count );
	}
	function print_invoices_table( $page = 1, $args = Array() ){
		$type = 'invoice';
		do_action( 'ndizi_print_'.$type.'s_table', $page, $args );
		if( !$count = $this->{$type.'_count'}( $args ) ){
			echo "\r\n<h3>".sprintf(__('No %ss yet &hellip; why not add one?',NDZ),__($type,NDZ))."</h3>";
			$this->{'print_'.$type.'_form'}();
			return;
		}
		$page = intval( $page );
		$things = $this->{'get_'.$type.'s'}( $page, $args );
		$this->{'print_button_new_'.$type}();
		$this->print_pager( $page, $this->friendly_page_link( $type.'s', FALSE ).$this->_parse_for_url( $args ), $count );
		?>
		<table class="widefat <?php echo $type; ?>s" cellspacing="0">
			<thead>
				<tr><?php	$this->_th( 'cb',			'<input type="checkbox" />',	FALSE,	TRUE	);
							$this->_th( 'id',			__('ID',NDZ),					TRUE,	TRUE	);
							$this->_th( 'name',			__('Name',NDZ),					FALSE,	TRUE	);
							$this->_th( 'date',			__('Date',NDZ),					FALSE,	TRUE	);
							$this->_th( 'client',		__('Client',NDZ),				FALSE,	TRUE	);
							$this->_th( 'project',		__('Project',NDZ),				FALSE,	TRUE	);
							$this->_th( 'attachment',	__('Messages',NDZ),				TRUE,	TRUE	);
							$this->_th( 'time',			__('Time',NDZ),					FALSE,	TRUE	);
							$this->_th( 'status',		__('Status',NDZ),				TRUE,	TRUE	);
							$this->_th( 'total',		__('Total',NDZ),				TRUE,	TRUE	);
							$this->_th( 'paid',			__('Paid',NDZ),					TRUE,	TRUE	);
							$this->_th( 'balance',		__('Balance',NDZ),				TRUE,	TRUE	);
							$this->_th( 'edit',			__('Edit',NDZ),					TRUE,	TRUE	);
							$this->_th( 'delete',		__('Delete',NDZ),				TRUE,	TRUE	); ?>
				</tr>
			</thead>
			<tfoot>
				<tr><?php	$this->_th( 'cb',			'<input type="checkbox" />',	FALSE	);
							$this->_th( 'id',			__('ID',NDZ),					TRUE	);
							$this->_th( 'name',			__('Name',NDZ),					FALSE	);
							$this->_th( 'date',			__('Date',NDZ),					FALSE	);
							$this->_th( 'client',		__('Client',NDZ),				FALSE	);
							$this->_th( 'project',		__('Project',NDZ),				FALSE	);
							$this->_th( 'attachment',	__('Messages',NDZ),				TRUE	);
							$this->_th( 'time',			__('Time',NDZ),					FALSE	);
							$this->_th( 'status',		__('Status',NDZ),				TRUE	);
							$this->_th( 'total',		__('Total',NDZ),				TRUE	);
							$this->_th( 'paid',			__('Paid',NDZ),					TRUE	);
							$this->_th( 'balance',		__('Balance',NDZ),				TRUE	);
							$this->_th( 'edit',			__('Edit',NDZ),					TRUE	);
							$this->_th( 'delete',		__('Delete',NDZ),				TRUE	);	?>
				</tr>
			</tfoot>
			<tbody id="the-list" class="list:<?php echo $type; ?>s">
				<?php foreach( $things as $i => $invoice ): $id = $invoice['invoice_id']; ?>
					<tr id="<?php echo $type; ?>-<?php echo $id; ?>" class="<?php echo ($invoice['invoice_total']<=$invoice['invoice_paid'])?'paid-in-full':''; echo ($i%2?'':' alternate'); ?>">
						<?php	$this->_rth('invoice',		$id																							);
								$this->_td( 'id',			$invoice['invoice_id'],																	TRUE	);
								$this->_td( 'name',			$invoice['invoice_name'],																FALSE	);
								$this->_td( 'date',			$invoice['invoice_date'],																FALSE	);
								$this->_td( 'client',		$invoice['client_name'],																FALSE	);
								$this->_td( 'project',		$invoice['project_name'],																FALSE	);
								$this->_td( 'attachment',	$this->attachment_count(array('item_type'=>$type,'item_id'=>$id)),						TRUE	);
								$this->_td( 'time',			$this->time_total( array( 'invoice_id' => $id ) ),										FALSE	);
								$this->_td( 'status',		$this->invoice_status_readable( $invoice['invoice_status'] ),							TRUE	);
								$this->_td( 'total',		__('$',NDZ).number_format( $invoice['invoice_total'], 2 ),								TRUE	);
								$this->_td( 'paid',			__('$',NDZ).number_format( $invoice['invoice_paid'], 2 ),								TRUE	);
								$this->_td( 'balance',		__('$',NDZ).number_format( ($invoice['invoice_total']-$invoice['invoice_paid']), 2 ),	TRUE	);
								$this->_td( 'edit',			$this->{'get_link_edit_'.$type}( $id ),													TRUE	);
								$this->_td( 'delete',		$this->{'get_link_delete_'.$type}( $id ),												TRUE	);	?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$this->print_pager( $page, $this->friendly_page_link( $type.'s', FALSE ).$this->_parse_for_url( $args ), $count );
	}
	function print_attachments_table( $page = 1, $args = Array() ){
		$type = 'attachment';
		do_action( 'ndizi_print_'.$type.'s_table', $page, $args );
		if( !$count = $this->{$type.'_count'}( $args ) ){
			echo "\r\n<h3>".sprintf(__('No %ss yet &hellip; why not add one?',NDZ),__($type,NDZ))."</h3>";
			$this->{'print_'.$type.'_form'}();
			return;
		}
		$page = intval( $page );
		$things = $this->{'get_'.$type.'s'}( $page, $args );
		$this->{'print_button_new_'.$type}();
		$this->print_pager( $page, $this->friendly_page_link( $type.'s', FALSE ).$this->_parse_for_url( $args ), $count );
		?>
		<table class="widefat <?php echo $type; ?>s" cellspacing="0">
			<thead>
				<tr><?php	$this->_th( 'cb',			'<input type="checkbox" />',	FALSE,	TRUE	);
							$this->_th( 'id',			__('ID',NDZ),					TRUE,	TRUE	);
							$this->_th( 'edit',			__('Edit',NDZ),					TRUE,	TRUE	);
							$this->_th( 'delete',		__('Delete',NDZ),				TRUE,	TRUE	); ?>
				</tr>
			</thead>
			<tfoot>
				<tr><?php	$this->_th( 'cb',			'<input type="checkbox" />',	FALSE	);
							$this->_th( 'id',			__('ID',NDZ),					TRUE	);
							$this->_th( 'edit',			__('Edit',NDZ),					TRUE	);
							$this->_th( 'delete',		__('Delete',NDZ),				TRUE	); ?>
				</tr>
			</tfoot>
			<tbody id="the-list" class="list:<?php echo $type; ?>s">
				<?php foreach( $things as $i => $attachment ): $id = $attachment['attachment_id']; ?>
					<tr id="<?php echo $type; ?>-<?php echo $id; ?>" class="<?php if($i%2) echo 'alternate'; ?>">
						<?php	$this->_rth('attachment',	$id													);
								$this->_td( 'id',			$attachment['attachment_id'],				TRUE	);
								$this->_td( 'edit',			$this->{'get_link_edit_'.$type}( $id ),		TRUE	);
								$this->_td( 'delete',		$this->{'get_link_delete_'.$type}( $id ),	TRUE	);	?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$this->print_pager( $page, $this->friendly_page_link( $type.'s', FALSE ).$this->_parse_for_url( $args ), $count );
	}
	function print_permissions_table( $page = 1, $args = Array() ){
		$type = 'permission';
		do_action( 'ndizi_print_'.$type.'s_table', $page, $args );
		if( !$count = $this->{$type.'_count'}( $args ) ){
			echo "\r\n<h3>".sprintf(__('No %ss yet &hellip; why not add one?',NDZ),__($type,NDZ))."</h3>";
			$this->{'print_'.$type.'_form'}();
			return;
		}
		$page = intval( $page );
		$things = $this->{'get_'.$type.'s'}( $page, $args );
		$this->{'print_button_new_'.$type}();
		$this->print_pager( $page, $this->friendly_page_link( $type.'s', FALSE ).$this->_parse_for_url( $args ), $count );
		?>
		<table class="widefat <?php echo $type; ?>s" cellspacing="0">
			<thead>
				<tr><?php	$this->_th( 'cb',			'<input type="checkbox" />',	FALSE,	TRUE	);
							$this->_th( 'id',			__('ID',NDZ),					TRUE,	TRUE	);
							$this->_th( 'edit',			__('Edit',NDZ),					TRUE,	TRUE	);
							$this->_th( 'delete',		__('Delete',NDZ),				TRUE,	TRUE	); ?>
				</tr>
			</thead>
			<tfoot>
				<tr><?php	$this->_th( 'cb',			'<input type="checkbox" />',	FALSE	);
							$this->_th( 'id',			__('ID',NDZ),					TRUE	);
							$this->_th( 'edit',			__('Edit',NDZ),					TRUE	);
							$this->_th( 'delete',		__('Delete',NDZ),				TRUE	); ?>
				</tr>
			</tfoot>
			<tbody id="the-list" class="list:<?php echo $type; ?>s">
				<?php foreach( $things as $i => $permission ): $id = $permission['permission_id']; ?>
					<tr id="<?php echo $type; ?>-<?php echo $id; ?>" class="<?php if($i%2) echo 'alternate'; ?>">
						<?php	$this->_rth('permission',	$id													);
								$this->_td( 'id',			$permission['permission_id'],				TRUE	);
								$this->_td( 'edit',			$this->{'get_link_edit_'.$type}( $id ),		TRUE	);
								$this->_td( 'delete',		$this->{'get_link_delete_'.$type}( $id ),	TRUE	);	?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$this->print_pager( $page, $this->friendly_page_link( $type.'s', FALSE ).$this->_parse_for_url( $args ), $count );
	}
	
	
	function print_your_times_table( $page = 1, $args = Array() ){
		do_action( 'ndizi_print_your_times_table', $page, $args );
		$count = $this->time_count( $args );
		$page = intval( $page );
		$things = $this->get_times( $page, $args );
		$this->print_pager( $page, $this->friendly_page_link( 'times', FALSE ).$this->_parse_for_url( $args ), $count );
		?>
		<table class="widefat <?php echo $type; ?>s" cellspacing="0">
			<thead>
				<tr><?php	$this->_th( 'cb',			'<input type="checkbox" />',	FALSE,	TRUE	);
							$this->_th( 'id',			__('ID',NDZ),					TRUE,	TRUE	);
							$this->_th( 'description',	__('Description',NDZ),			FALSE,	TRUE	);
							$this->_th( 'date',			__('Date',NDZ),					FALSE,	TRUE	);
							$this->_th( 'duration',		__('Duration',NDZ),				FALSE,	TRUE	);
							$this->_th( 'client',		__('Client',NDZ),				FALSE,	TRUE	);
							$this->_th( 'project',		__('Project',NDZ),				FALSE,	TRUE	);
							$this->_th( 'edit',			__('Edit',NDZ),					TRUE,	TRUE	);
							$this->_th( 'delete',		__('Delete',NDZ),				TRUE,	TRUE	); ?>
				</tr>
			</thead>
			<tfoot>
				<tr><?php	$this->_th( 'cb',			'<input type="checkbox" />',	FALSE	);
							$this->_th( 'id',			__('ID',NDZ),					TRUE	);
							$this->_th( 'description',	__('Description',NDZ),			FALSE	);
							$this->_th( 'date',			__('Date',NDZ),					FALSE	);
							$this->_th( 'duration',		__('Duration',NDZ),				FALSE	);
							$this->_th( 'client',		__('Client',NDZ),				FALSE	);
							$this->_th( 'project',		__('Project',NDZ),				FALSE	);
							$this->_th( 'edit',			__('Edit',NDZ),					TRUE	);
							$this->_th( 'delete',		__('Delete',NDZ),				TRUE	); ?>
				</tr>
			</tfoot>
			<tbody id="the-list" class="list:<?php echo $type; ?>s">
				<form method="post">
					<tr id="new-time" class="alternate">
						<th scope="row"><input type="hidden" name="ndizi_action" value="add_time" /></th>
						<td><input type="hidden" name="user_id" value="<?php echo $this->get_curr_user_id(); ?>" /></td>
						<td><input type="text" name="description" placeholder="Description" /></td>
						<td><input class="ndizi-datepicker" type="text" name="date" value="<?php echo date('Y-m-d'); ?>" /></td>
						<td><input class="ndizi-timepicker" type="text" name="duration" value="0:00" /></td>
						<td colspan="2"><?php $this->print_projects_dropdown( NULL, 'project', array() ); ?></td>
						<td colspan="2"><input type="submit" class="button-primary" value="Add Time Entry &rarr;" /></td>
					</tr>
				</form>
				<?php foreach( $things as $i => $time ): $id = $time['time_id']; ?>
					<tr id="<?php echo $type; ?>-<?php echo $id; ?>" class="<?php if($i%2) echo 'alternate'; ?>">
						<?php	$this->_rth('time',			$id												);
								$this->_td( 'id',			$time['time_id'],						TRUE	);
								$this->_td( 'description',	$time['time_description'],				FALSE	);
								$this->_td( 'date',			$time['time_date'],						FALSE	);
								$this->_td( 'duration',		$time['time_duration'],					FALSE	);
								$this->_td( 'client',		$time['client_name'],					FALSE	);
								$this->_td( 'project',		$time['project_name'],					FALSE	);
								$this->_td( 'edit',			$this->get_link_edit_time( $id ),		TRUE	);
								$this->_td( 'delete',		$this->get_link_delete_time( $id ),		TRUE	);	?>
						</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$this->print_pager( $page, $this->friendly_page_link( 'times', FALSE ).$this->_parse_for_url( $args ), $count );
	}

// ===========================

	function print_client_form( $id = NULL ){
		do_action( 'ndizi_pre_print_client_form', $id );
		$x = is_null( $id ) ? Array() : $this->get_client( intval( $id ) ) ;
		$what = is_null($id)?__('Add New Client',NDZ):__('Edit Client',NDZ);
		?>
		<h2 id="<?php echo is_null($id)?'add':'edit'; ?>-client"><?php echo $what; ?></h2>
		<form method="post" action="<?php $this->friendly_page_link( 'clients' ); ?>">
			<?php $this->_form_row( 'ndizi_action', NULL, (is_null($id)?'add':'edit').'_client', 'hidden' ); ?>
			<?php if( !is_null( $id ) ) $this->_form_row( 'client_id', NULL, $x['client_id'], 'hidden' ); ?>
			<table class="form-table">
			<tbody>
				<?php	$this->_form_row( 'name',			__('Client Name',NDZ),		$this->_val($x,'client_name') 						);
						$this->_form_row( 'phone',			__('Phone Number',NDZ),		$this->_val($x,'client_phone') 						);
						$this->_form_row( 'email',			__('Email Address',NDZ),	$this->_val($x,'client_email') 						);
						$this->_form_row( 'site',			__('Website',NDZ),			$this->_val($x,'client_site') 						);
						$this->_form_row( 'description',	__('Description',NDZ),		$this->_val($x,'client_description'),	'wysiwyg'	);
						$this->_form_row( 'address',		__('Address',NDZ),			$this->_val($x,'client_address'),		'textarea' 	);
						$this->_form_row( 'access_key',		__('Access Key',NDZ),		$this->_val($x,'client_access_key',md5(time()))		);
						$this->_form_row( 'active',			__('Active',NDZ),			$this->_val($x,'client_active','y'),	'yn' 		);
						$this->_form_row( 'submit',			NULL,						$what,									'submit'	); ?>
			</tbody>
			</table>
		</form>
		<?php
		do_action( 'ndizi_post_print_client_form', $id );
	}
	function print_project_form( $id = NULL ){
		do_action( 'ndizi_pre_print_project_form', $id );
		$x = is_null( $id ) ? Array() : $this->get_project( intval( $id ) ) ;
		$what = is_null($id)?__('Add New Project',NDZ):__('Edit Project',NDZ);
		?>
		<h2 id="<?php echo is_null($id)?'add':'edit'; ?>-project"><?php echo $what; ?></h2>
		<form method="post" action="<?php $this->friendly_page_link( 'projects' ); ?>">
			<?php $this->_form_row( 'ndizi_action', NULL, (is_null($id)?'add':'edit').'_project', 'hidden' ); ?>
			<?php if( !is_null( $id ) ) $this->_form_row( 'project_id', NULL, $x['project_id'], 'hidden' ); ?>
			<table class="form-table">
			<tbody>
				<?php	$this->_form_row( 'name',			__('Project Name',NDZ),			$this->_val($x,'project_name') 						);
						$this->_form_row( 'description',	__('Project Description',NDZ),	$this->_val($x,'project_description'),	'wysiwyg'	);
						$this->_form_row( 'client_id',		__('Client',NDZ),				$this->_val($x,'project_client_id'),	'clients'	);
						$this->_form_row( 'active',			__('Active',NDZ),				$this->_val($x,'project_active','y'),	'yn' 		);
						$this->_form_row( 'submit',			NULL,							$what,									'submit'	); ?>
			</tbody>
			</table>
		</form>
		<?php
		do_action( 'ndizi_post_print_project_form', $id );
	}
	function print_task_form( $id = NULL ){
		do_action( 'ndizi_pre_print_task_form', $id );
		$x = is_null( $id ) ? Array() : $this->get_task( intval( $id ) ) ;
		$what = is_null($id)?__('Add New Task',NDZ):__('Edit Task',NDZ);
		?>
		<h2 id="<?php echo is_null($id)?'add':'edit'; ?>-task"><?php echo $what; ?></h2>
		<form method="post" action="<?php $this->friendly_page_link( 'tasks' ); ?>">
			<?php $this->_form_row( 'ndizi_action', NULL, (is_null($id)?'add':'edit').'_task', 'hidden' ); ?>
			<?php if( !is_null( $id ) ) $this->_form_row( 'task_id', NULL, $x['task_id'], 'hidden' ); ?>
			<table class="form-table">
			<tbody>
				<?php	$this->_form_row( 'project_id',		__('Project',NDZ),				$this->_val($x,'project_id'), 			'projects'	);
						$this->_form_row( 'name',			__('Task Name',NDZ),			$this->_val($x,'task_name') 						);
						$this->_form_row( 'description',	__('Task Description',NDZ),		$this->_val($x,'task_description'),		'wysiwyg'	);
						$this->_form_row( 'priority',		__('Task Priority',NDZ),		$this->_val($x,'task_priority',3),		'priority'	);
						$this->_form_row( 'status',			__('Task Status',NDZ),			$this->_val($x,'task_status',1),		'status'	);
						$this->_form_row( 'user_id',		__('Assign to User',NDZ),		$this->_val($x,'task_user_id'),			'users'		);
						$this->_form_row( 'submit',			NULL,							$what,									'submit'	);	?>
			</tbody>
			</table>
		</form>
		<?php
		do_action( 'ndizi_post_print_task_form', $id );
	}
	function print_time_form( $id = NULL ){
		do_action( 'ndizi_pre_print_time_form', $id );
		$x = is_null( $id ) ? Array() : $this->get_time( intval( $id ) ) ;
		$what = is_null($id)?__('Add New Time Report',NDZ):__('Edit Time Report',NDZ);
		?>
		<h2 id="<?php echo is_null($id)?'add':'edit'; ?>-time"><?php echo $what; ?></h2>
		<form method="post" action="<?php $this->friendly_page_link( 'times' ); ?>">
			<?php $this->_form_row( 'ndizi_action', NULL, (is_null($id)?'add':'edit').'_time', 'hidden' ); ?>
			<?php if( !is_null( $id ) ) $this->_form_row( 'time_id', NULL, $x['time_id'], 'hidden' ); ?>
			<table class="form-table">
			<tbody>
				<?php	$this->_form_row( 'project_id',		__('Project',NDZ),			$this->_val($x,'project_id'),							'projects'	);
						$this->_form_row( 'date',			__('Date',NDZ),				$this->_val($x,'time_date',date("Y-m-d")),				'date'		);
						$this->_form_row( 'duration',		__('Duration',NDZ),			$this->_val($x,'time_duration','0:00')								);
						$this->_form_row( 'description',	__('Summary',NDZ),			$this->_val($x,'time_description'),						'wysiwyg'	);
if( current_user_can( 'manage_options' ) ):
						$this->_form_row( 'user_id',		__('User',NDZ),				$this->_val($x,'user_id'),								'users'		);
else:					$this->_form_row( 'user_id',		NULL,						$this->_val($x,'user_id',$this->get_curr_user_id()),	'hidden'	);
endif;					$this->_form_row( 'submit',			NULL,						$what,													'submit'	);	?>
			</tbody>
			</table>
		</form>
		<?php
		do_action( 'ndizi_post_print_time_form', $id );
	}
	function print_invoice_form( $id = NULL ){
		do_action( 'ndizi_pre_print_invoice_form', $id );
		$x = is_null( $id ) ? Array() : $this->get_invoice( intval( $id ) ) ;
		$what = is_null($id)?__('Add New Invoice',NDZ):__('Edit Invoice',NDZ);
		?>
		<h2 id="<?php echo is_null($id)?'add':'edit'; ?>-invoice"><?php echo $what; ?></h2>
		<form method="post" action="<?php $this->friendly_page_link( 'invoices' ); ?>">
			<?php $this->_form_row( 'ndizi_action', NULL, (is_null($id)?'add':'edit').'_invoice', 'hidden' ); ?>
			<?php if( !is_null( $id ) ) $this->_form_row( 'invoice_id', NULL, $x['invoice_id'], 'hidden' ); ?>
			<table class="form-table">
			<tbody>
				<?php	$this->_form_row( 'project_id',		__('Project',NDZ),					$this->_val($x,'project_id'),					'projects'			);
						$this->_form_row( 'date',			__('Invoice Date',NDZ),				$this->_val($x,'invoice_date',date("Y-m-d")),	'date'				);
						$this->_form_row( 'name',			__('Invoice Name',NDZ),				$this->_val($x,'invoice_name') 										);
						$this->_form_row( 'description',	__('Invoice Description',NDZ),		$this->_val($x,'invoice_description'),			'wysiwyg'			);
						$this->_form_row( 'notes',			__('Invoice Notes',NDZ),			$this->_val($x,'invoice_notes'),				'wysiwyg'			);
						$this->_form_row( 'terms',			__('Invoice Terms',NDZ),			$this->_val($x,'invoice_terms'),				'wysiwyg'			);
						$this->_form_row( 'total',			__('Invoice Total',NDZ),			$this->_val($x,'invoice_total','0.00') 								);
						$this->_form_row( 'paid',			__('Amount Paid',NDZ),				$this->_val($x,'invoice_paid','0.00') 								);
						$this->_form_row( 'status',			__('Invoice Status',NDZ),			$this->_val($x,'invoice_status',1),				'invoice_status'	);
						$this->_form_row( 'submit',			NULL,								$what,											'submit'			);	?>
			</tbody>
			</table>
		</form>
		<?php
		do_action( 'ndizi_post_print_invoice_form', $id );
	}
	function print_attachment_form( $id = NULL ){
		do_action( 'ndizi_pre_print_attachment_form', $id );
		$x = is_null( $id ) ? Array() : $this->get_attachment( intval( $id ) ) ;
		$what = is_null( $id ) ? __('Add New Attachment',NDZ) : __('Edit Attachment',NDZ) ;
		?>
		<h2 id="<?php echo is_null($id)?'add':'edit'; ?>-attachment"><?php echo $what; ?></h2>
		<form method="post" action="<?php $this->friendly_page_link( 'attachments' ); ?>">
			<?php $this->_form_row( 'ndizi_action', NULL, (is_null($id)?'add':'edit').'_attachment', 'hidden' ); ?>
			<?php if( !is_null( $id ) ) $this->_form_row( 'attachment_id', NULL, $x['attachment_id'], 'hidden' ); ?>
			<table class="form-table">
			<tbody>
				<?php	$this->_form_row( 'submit',			NULL,					$what,									'submit'	);	?>
			</tbody>
			</table>
		</form>
		<?php
		do_action( 'ndizi_post_print_attachment_form', $id );
	}
	function print_permission_form( $id = NULL ){
		do_action( 'ndizi_pre_print_permission_form', $id );
		$x = is_null( $id ) ? Array() : $this->get_permission( intval( $id ) ) ;
		$what = is_null($id)?__('Add New Permission',NDZ):__('Edit Permission',NDZ);
		?>
		<h2 id="<?php echo is_null($id)?'add':'edit'; ?>-permission"><?php echo $what; ?></h2>
		<form method="post" action="<?php $this->friendly_page_link( 'permissions' ); ?>">
			<?php $this->_form_row( 'ndizi_action', NULL, (is_null($id)?'add':'edit').'_permission', 'hidden' ); ?>
			<?php if( !is_null( $id ) ) $this->_form_row( 'permission_id', NULL, $x['permission_id'], 'hidden' ); ?>
			<table class="form-table">
			<tbody>
				<?php	$this->_form_row( 'submit',			NULL,					$what,									'submit'	);	?>
			</tbody>
			</table>
		</form>
		<?php
		do_action( 'ndizi_post_print_permission_form', $id );
	}

#########################################################

	function options_page(){
		do_action( 'ndizi_pre_options_page' );
		if( isset( $_POST['ndizi_action'] ) ){
			switch( $_POST['ndizi_action'] ){
				case 'save_ndizi_options':
					update_option( 'Ndizi Frontend Page', $this->_val( $_POST, 'frontend_page' ) );
				//	update_option( 'Ndizi Print Admin Header Form', $this->_val( $_POST, 'admin_header', 'NO' ) );
					break;
				case 'bug_report':
					$this->bug_report( $this->_val($_POST,'email'), $this->_val($_POST,'description') );
					break;
				default:
					break;
			}
		} ?>
		<div class="wrap">
			<div id="icon-edit" class="icon32">
				<br />
			</div>
			<h2><?php _e('Ndizi Project Management',NDZ); ?></h2>
			<br class="clear" />
			<div id="col-container">
				<div id="col-right">
					<div class="col-wrap metabox-holder">
						<div class="postbox">
							<h3><span><?php _e('About',NDZ); ?></span></h3>
							<div class="inside">
								<p><q><?php _e('Come mister tally man, tally me bananas &hellip;',NDZ); ?></q></p>
								<p><?php _e('Ndizi is Swahili for Banana.  Owing to Harry Belafonte&rsquo;s classic ballad, and a fondness for Potassium, we&rsquo;ve decided to name this project <strong>Ndizi Project Management</strong>.',NDZ); ?></p>
								<p><?php _e('It still is Beta software, so not <em>all</em> functionality actually works yet, and some of it may not be extactly <em>pretty</em>, but please shoot me some feed back if you catch anything glaring, or just want to speak your mind!  You can E-Mail me at ',NDZ); ?>George@Stephanis.info</p>
							</div>
						</div><!-- /postbox -->
						<div class="postbox">
							<h3><span><?php _e('Bug Reports',NDZ); ?></span></h3>
							<div class="inside">
								<?php if( isset( $_POST['ndizi_action'] ) && ( $_POST['ndizi_action'] == 'bug_report' ) ): ?>
									<p><?php _e('Thanks!  If you don&rsquo;t hear from me in the next 24 hours, shoot me an email directly &mdash;',NDZ); ?> George@Stephanis.info</p>
								<?php else: ?>
									<form method="post" action="<?php $this->friendly_page_link( 'options' ); ?>">
										<fieldset>
											<input type="hidden" name="ndizi_action" value="bug_report" />
											<div class="form-field">
												<label for="frm_description"><strong><?php _e('Your E-Mail',NDZ); ?></strong></label>
												<input id="frm_description" type="text" name="email" value="<?php echo $GLOBALS['user_email']; ?>">
											</div>
											<div class="form-field">
												<label for="frm_description"><strong><?php _e('What seems to be the problem?',NDZ); ?></strong></label>
												<textarea id="frm_description" name="description" rows="8"></textarea>
											</div>
											<p class="submit">
												<input type="submit" class="button" value="<?php _e('Submit Bug Report',NDZ); ?> &rarr;" />
											</p>
										</fieldset>
									</form>
								<?php endif; ?>
							</div>
						</div><!-- /postbox -->
					</div><!-- /col-wrap -->
				</div><!-- /col-right -->
				<div id="col-left">
					<div class="col-wrap">
						<div class="form-wrap">
							<h3><?php _e('Configure Options',NDZ); ?></h3>
							<form method="post" action="<?php $this->friendly_page_link( 'options' ); ?>">
								<fieldset>
								<input type="hidden" name="ndizi_action" value="save_ndizi_options" />
									<div class="form-field">
										<label for="ndizi-frontend-page"><strong><?php _e('Ndizi Front-End Page',NDZ); ?></strong></label>
										<?php wp_dropdown_pages( Array(
														'show_option_none'	=> ' ',
														'depth'         	=> 0,
														'selected'			=> get_option( 'Ndizi Frontend Page', NULL ),
														'name'          	=> 'frontend_page',
														'id'            	=> 'ndizi-frontend-page',
													) ); ?>
										<p><?php _e('What page should clients log in to?',NDZ); ?></p>
									</div>
				<!--				<div class="form-field">
										<label for="ndizi_admin_header"><strong><?php _e('Time Form in Admin Header?',NDZ); ?></strong></label>
										<label><input type="checkbox" name="admin_header" id="ndizi_admin_header" value="YES" style="width:auto;"<?php if('YES'==get_option('Ndizi Print Admin Header Form')) echo ' checked="checked" '; ?>/> <?php _e('Yes'); ?></label>
										<p><?php _e('Do you want to display a handy little form up top?',NDZ); ?></p>
									</div>
				-->					<p class="submit">
										<input type="submit" class="button-primary" value="<?php _e('Save Options',NDZ); ?> &rarr;" />
									</p>
								</fieldset>
							</form>
						</div>
					</div>
				</div><!-- /col-left -->
			</div><!-- /col-container -->
		</div><!-- /wrap -->
		<?php
		do_action( 'ndizi_post_options_page' );
	}
	function _type_page( $type, $title, $icon_id = 'icon-tools' ){
		do_action( 'ndizi_pre_'.$type.'_page' );
		if( isset( $_POST['ndizi_action'] ) ){
			switch( $_POST['ndizi_action'] ){
				case "add_$type":
				case "edit_$type":
					$this->{'admin_save_'.$type}( $this->_val( $_POST, $type.'_id' ) );
					break;
			}
		}
		?>
		<div class="wrap">
			<div id="<?php echo $icon_id; ?>" class="icon32"><br /></div>
			<h2><?php echo $title; ?></h2>
			<br class="clear" />
			<?php
				if( isset( $_GET['action'] ) ) {
					switch( $_GET['action'] ) {
						case 'edit':
							$this->{'print_'.$type.'_form'}( $this->_val( $_GET, 'id' ) );
							break;
						case 'add':
							$this->{'print_'.$type.'_form'}();
							break;
						case 'delete':
							$this->{'delete_'.$type.''}( $this->_val( $_GET, 'id' ) );
						default:
							$this->{'print_'.$type.'s_table'}( $this->_val($_GET,'paged',1) );
							break;
					}
				} else {
					$this->{'print_'.$type.'s_table'}( $this->_val($_GET,'paged',1) );
				}
			?>
			<br class="clear" />
		</div><!-- /wrap -->
		<?php
		do_action( 'ndizi_post_'.$type.'_page' );
	}
	function client_page(){		$this->_type_page( 'client',		__('Ndizi Clients',NDZ),		'icon-users'	);	}
	function project_page(){	$this->_type_page( 'project',		__('Ndizi Projects',NDZ),		'icon-themes'	);	}
	function task_page(){		$this->_type_page( 'task',			__('Ndizi Tasks',NDZ),			'icon-edit'		);	}
	function time_page(){		$this->_type_page( 'time',			__('Ndizi Times',NDZ),			'icon-tools'	);	}
	function invoice_page(){	$this->_type_page( 'invoice',		__('Ndizi Invoices',NDZ),		'icon-tools'	);	}
	function attachment_page(){	$this->_type_page( 'attachment',	__('Ndizi Attachments',NDZ),	'icon-tools'	);	}
	function permission_page(){	$this->_type_page( 'permission',	__('Ndizi Permissions',NDZ),	'icon-tools'	);	}

	function your_time_page(){
		do_action( 'ndizi_pre_your_time_page' );
		if( isset( $_POST['ndizi_action'] ) ){
			switch( $_POST['ndizi_action'] ){
				case "add_time":
				case "edit_time":
					$this->admin_save_time( $this->_val( $_POST, 'time_id' ) );
					break;
			}
		}
		?>
		<div class="wrap">
			<div id="icon-tools" class="icon32"><br /></div>
			<h2><?php _e('Your Time Entries',NDZ); ?></h2>
			<br class="clear" />
			<?php
				if( isset( $_GET['action'] ) ) {
					switch( $_GET['action'] ) {
						case 'edit':
							$this->print_time_form( $this->_val( $_GET, 'id' ) );
							break;
						case 'add':
							$this->print_time_form();
							break;
						case 'delete':
							$this->delete_time( $this->_val( $_GET, 'id' ) );
						default:
							$this->print_your_times_table( $this->_val($_GET,'paged',1), array( 'ti_user_id' => $this->get_curr_user_id() ) );
							break;
					}
				} else {
					$this->print_your_times_table( $this->_val($_GET,'paged',1), array( 'ti_user_id' => $this->get_curr_user_id() ) );
				}
			?>
			<br class="clear" />
		</div><!-- /wrap -->
		<?php
		do_action( 'ndizi_post_your_time_page' );
	}
#########################################################

	function friendly_page_slug( $slug_id, $display = TRUE ){
		if( isset( $this->page_slugs[ $slug_id ] ) ){
			$array = explode( '_page_', $this->page_slugs[ $slug_id ] );
			if( $display ) { echo $array[ 1 ]; }
			return $array[ 1 ];
		}
		elseif( $slug_id == 'top_level' ){
			if( $display ) { echo 'ndizi'; }
			return 'ndizi';
		}
	}
	function friendly_page_link( $slug_id, $display = TRUE ){
		$value = get_bloginfo('wpurl') . '/wp-admin/admin.php?page=' . $this->friendly_page_slug( $slug_id, FALSE );
		if( $display ) { echo $value; }
		return $value;
	}
	
#########################################################

	function catch_post(){
		do_action( 'ndizi_catch_post' );
		if( isset( $_POST['ndizi_action'] ) ){
			if( current_user_can('manage_options') ){
				
			}
			if( isset( $_SESSION['ndizi_client_id'] ) ) {
				if( 'frontend_add_task' == $_POST['ndizi_action'] ){	$this->frontend_add_task();					}
			}
			if( 'client_login' == $_POST['ndizi_action'] ){				$this->do_login( $_POST['access_key'] );	}
			if( 'client_logout' == $_POST['ndizi_action'] ){			$this->do_logout();							}
		}
	}

	
################################################
# Dropdowns and Options                        #
################################################
	
	function print_clients_dropdown( $selected_id = NULL, $name = 'client_id', $args = Array() ){
		$args = apply_filters( 'ndizi_print_clients_dropdown', $args );
		$clients = $this->get_clients( NULL, $args );
		?>
		<select id="frm_<?php echo $name; ?>" name="<?php echo $name; ?>">
			<option value="0">( <?php _e('none',NDZ); ?> )</option>
			<?php foreach( $clients as $client ): ?>
			<option value="<?php echo $client['client_id']; ?>"<?php echo ($client['client_id']==$selected_id?' selected="selected"':''); ?>><?php echo $client['client_name']; ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}
	function print_projects_dropdown( $selected_id = NULL, $name = 'project_id', $args = Array() ){
		$args = apply_filters( 'ndizi_print_projects_dropdown', $args );
		$projects = $this->get_projects( NULL, $args );
		$client = $projects[0]['client_name'];
		?>
		<select id="frm_<?php echo $name; ?>" name="<?php echo $name; ?>">
			<option value="0">( <?php _e('none',NDZ); ?> )</option>
			<optgroup label="<?php echo $client; ?>">
			<?php foreach( $projects as $project ): ?>
				<?php if( $project['client_name'] != $client ): ?>
					</optgroup>
					<optgroup label="<?php echo $client = $project['client_name']; ?>">
				<?php endif; ?>
				<option value="<?php echo $project['project_id']; ?>"<?php if($project['project_id']==$selected_id) echo ' selected="selected"'; ?>><?php echo $project['project_name']; ?></option>
			<?php endforeach; ?>
			</optgroup>
		</select>
		<?php
	}
	function print_tasks_dropdown( $selected_id = NULL, $name = 'task_id', $args = Array() ){
		$args = apply_filters( 'ndizi_print_tasks_dropdown', $args );
		$tasks = $this->get_tasks( NULL, $args );
		$client = $tasks[0]['client_name'];
		$project = $tasks[0]['project_name'];
		?>
		<select id="frm_task_id" name="task_id">
			<option value="0">( <?php _e('none',NDZ); ?> )</option>
			<optgroup label="<?php echo $client; ?>">
				<optgroup label="<?php echo $project; ?>">
				<?php foreach( $tasks as $task ): ?>
					<?php if( $task['project_name'] != $project ): ?>
						</optgroup>
						<?php if( $task['client_name'] != $client ): ?>
							</optgroup>
							<optgroup label="<?php echo $client = $task['client_name']; ?>">
						<?php endif; ?>
						<optgroup label="<?php echo $project = $task['project_name']; ?>">
					<?php endif; ?>
				<option value="<?php echo $task['task_id']; ?>"<?php if($task['task_id']==$selected_id) echo ' selected="selected"'; ?>><?php echo $task['task_name']; ?> (<?php echo $this->priority_readable( $task['task_priority'] ); ?>)</option>
				<?php endforeach; ?>
				</optgroup>
			</optgroup>
		</select>
		<?php
	}
	
// ===========================
	
	function priority_options(){
		return apply_filters( 'ndizi_priority_options', Array( 
			5 => __('Critical',NDZ), 
			4 => __('High',NDZ), 
			3 => __('Normal',NDZ), 
			2 => __('Low',NDZ), 
			1 => __('Trivial',NDZ), 
		) );
	}
	function priority_readable( $i ){
		$priorities = $this->priority_options();
		return $priorities[ intval($i) ];
	}
	function print_priority_options_dropdown( $selected = 3, $name = "priority" ){
		$options = $this->priority_options();
		if( ! isset( $options[ intval($selected) ] ) ){
			$selected = 3;
		}
		?>
		<select id="frm_<?php echo $name; ?>" name="<?php echo $name; ?>">
		<?php foreach( $options as $value => $text ): ?>
			<option value="<?php echo $value; ?>"<?php echo ($selected==$value?' selected="selected"':''); ?>><?php echo $text; ?></option>
		<?php endforeach; ?>
		</select>
		<?php
	}
	
// ===========================
	
	function status_options(){
		return apply_filters( 'ndizi_status_options', Array( 
			7 => __('Finished',NDZ), 
			6 => __('Waiting on Approval',NDZ), 
			5 => __('Waiting on Client Response',NDZ), 
			4 => __('Work In Progress',NDZ), 
			3 => __('On The To-Do List',NDZ), 
			2 => __('On The Back Burner',NDZ), 
			1 => __('Not Yet Begun',NDZ) 
		) );
	}
	function status_readable( $i ){
		$statuses = $this->status_options();
		return $statuses[ intval($i) ];
	}
	function print_status_options_dropdown( $selected = 1, $name = "status" ){
		$statuses = $this->status_options();
		if( !isset( $statuses[ intval($selected) ] ) ){
			$selected = 1;
		}
		?>
		<select id="frm_<?php echo $name; ?>" name="<?php echo $name; ?>">
		<?php foreach( $statuses as $value => $text ): ?>
			<option value="<?php echo $value; ?>"<?php echo ($selected==$value?' selected="selected"':''); ?>><?php echo $text; ?></option>
		<?php endforeach; ?>
		</select>
		<?php
	}
	
// ===========================
	
	function invoice_status_options(){
		return apply_filters( 'ndizi_invoice_status_options', Array( 
			5 => __('Paid',NDZ), 
			4 => __('Overdue',NDZ), 
			3 => __('Partial',NDZ), 
			2 => __('Submitted',NDZ), 
			1 => __('Draft',NDZ) 
		) );
	}
	function invoice_status_readable( $i ){
		$invoice_statuses = $this->invoice_status_options();
		return $invoice_statuses[ intval($i) ];
	}
	function print_invoice_status_options_dropdown( $selected = 1, $name = "status" ){
		$invoice_statuses = $this->invoice_status_options();
		if( !isset( $invoice_statuses[ intval($selected) ] ) ){
			$selected = 1;
		}
		?>
		<select id="frm_<?php echo $name; ?>" name="<?php echo $name; ?>">
		<?php foreach( $invoice_statuses as $value => $text ): ?>
			<option value="<?php echo $value; ?>"<?php echo ($selected==$value?' selected="selected"':''); ?>><?php echo $text; ?></option>
		<?php endforeach; ?>
		</select>
		<?php
	}
	
// ===========================
	
	function permission_options(){
		return apply_filters( 'ndizi_permission_options', Array( 
			0 => __('None',NDZ),
			1 => __('View',NDZ),
			3 => __('Enter Time',NDZ),
			5 => __('Manage',NDZ),
			7 => __('Manage (with Invoices)',NDZ),
			9 => __('Full',NDZ) 
		) );
	}
	function permission_readable( $i ){
		$permissions = $this->permission_options();
		return $permissions[ intval($i) ];
	}
	function print_permission_options_dropdown( $selected = 1, $name = "permission" ){
		$permissions = $this->permission_options();
		if( !isset( $permissions[ intval($selected) ] ) ){
			$selected = 0;
		}
		?>
		<select id="frm_<?php echo $name; ?>" name="<?php echo $name; ?>">
		<?php foreach( $permissions as $value => $text ): ?>
			<option value="<?php echo $value; ?>"<?php echo ($selected==$value?' selected="selected"':''); ?>><?php echo $text; ?></option>
		<?php endforeach; ?>
		</select>
		<?php
	}
	
################################################
# Front-end Display Functions                  #
################################################

	function do_login( $access_key ){
		global $wpdb;
		$client_id = $wpdb->get_var( "SELECT `id` FROM $this->client_table WHERE `access_key` = '" . $wpdb->escape( $access_key ) . "' " );
		if( !is_null( $client_id ) ) {
			$_SESSION['ndizi_client_id'] = $client_id;
		}
	}
	function do_logout(){
		$_SESSION['ndizi_client_id'] = NULL;
		unset( $_SESSION['ndizi_client_id'] );
	}
	function print_login_form(){
		?>
			<form action="<?php echo get_permalink( get_option( 'Ndizi Frontend Page' ) ); ?>" method="POST">
				<input type="hidden" name="ndizi_action" value="client_login" />
				<label for="frm_access_key"><?php _e('Access Key',NDZ); ?>: 
					<input id="frm_access_key" type="text" name="access_key" />
				</label>
				<input type="submit" value="<?php _e('Log-In',NDZ); ?> &rarr;" />
			</form>
		<?php
	}
	function print_logout_form(){
		?>
			<form action="<?php echo get_permalink( get_option( 'Ndizi Frontend Page' ) ); ?>" method="POST">
				<input type="hidden" name="ndizi_action" value="client_logout" />
				<input type="submit" value="<?php _e('Log-Out',NDZ); ?> &rarr;" />
			</form>
		<?php 
	}

// ===========================

	function frontend_content( $content ){
		global $post;
		if( $post->ID == get_option( 'Ndizi Frontend Page' ) ){
			do_action( 'ndizi_pre_frontend_content' );
			ob_start();
			echo '<div id="NDIZI-CONTENT-WRAPPER">';
			if( isset( $_SESSION['ndizi_client_id'] ) ):
				if( isset( $_GET['invoice'] ) ){
					$invoice = $this->get_invoice( intval( $_GET['invoice'] ) );
					if( $invoice['client_id'] == $_SESSION['ndizi_client_id'] ){
						$this->print_frontend_display_invoice( $invoice['invoice_id'] );
					} else {
						echo "\r\n\t<h2>".__('Error: You are not authorized to view this invoice.',NDZ)."</h2>";
					}
				} else {
					$this->print_logout_form();
					$this->print_frontend_client_table( $_SESSION['ndizi_client_id'] );
					$this->print_frontend_task_form( $_SESSION['ndizi_client_id'] );
				}
			else:
				$this->print_login_form();
			endif;
	//		echo '<script type="text/javascript"> Shadowbox.init(); </script> ';
			echo '</div><!-- /NDIZI-CONTENT-WRAPPER -->';
			$ndizi_content = ob_get_contents();
			ob_end_clean();
			do_action( 'ndizi_post_frontend_content' );
			$ndizi_content = apply_filters( 'ndizi_frontend_content', $ndizi_content );
			return (isset( $_SESSION['ndizi_client_id'])?NULL:$content).$ndizi_content;
		}
		return $content;
	}
	function print_frontend_client_table( $client_id ){
		$client = $this->get_client( $client_id );
		?>
		<table class="client-info">
			<tr><th scope="row"><?php _e('Name',NDZ); ?></th>
				<td><?php echo $client['client_name']; ?></td></tr>
			<tr><th scope="row"><?php _e('Address',NDZ); ?></th>
				<td><?php echo nl2br( $client['client_address'] ); ?></td></tr>
			<tr><th scope="row"><?php _e('Phone',NDZ); ?></th>
				<td><?php echo $client['client_phone']; ?></td></tr>
			<tr><th scope="row"><?php _e('Site',NDZ); ?></th>
				<td><?php echo $client['client_site']; ?></td></tr>
			<tr><th scope="row"><?php _e('E-mail',NDZ); ?></th>
				<td><?php echo $client['client_email']; ?></td></tr>
		</table>
		<?php
		$this->print_frontend_invoice_table( $client['client_id'] );
		if( count( $projects = $this->get_projects( NULL, array( 'client_id' => $client_id ) ) ) ){
			echo "<h2 class=\"\">".__('Projects',NDZ)."</h2>";
			foreach( $projects as $project ){
				$this->print_frontend_project_table( $project['project_id'] );
			}
		}
	}
	function print_frontend_project_table( $project_id ){
		$project = $this->get_project( $project_id );
		$tasks = $this->get_tasks( NULL, array( 'project_id' => $project_id ) );
		?>
		<div class="ndizi-project" id="project-<?php echo $project_id; ?>">
			<h3><?php echo $project['project_name']; ?></h3>
			<table class="project-info">
				<tr><th scope="row"><?php _e('Description',NDZ); ?></th>
					<td><?php echo nl2br( trim( $project['project_description'] ) ); ?></td></tr>
				<tr><th scope="row"><?php _e('Time Spent',NDZ); ?></th>
					<td><?php echo $this->time_total( array('project_id'=>$project_id) ); ?></td></tr>
			</table>
			
			<?php if( count( $tasks ) ): ?>
				<h3><?php _e('Tasks for',NDZ); ?> <?php echo $project['project_name']; ?></h3>
				<table class="widefat tasks" cellspacing="0">
					<thead>
						<tr>
							<?php	$this->_th( 'name',		__('Name',NDZ),		FALSE	);
									$this->_th( 'priority',	__('Priority',NDZ),	FALSE	);
									$this->_th( 'status',	__('Status',NDZ),	FALSE	);
									$this->_th( 'user',		__('User',NDZ),		FALSE	);	?>
						</tr>
					</thead>
					<tfoot>
						<tr>
							<?php	$this->_th( 'name',		__('Name',NDZ),		FALSE	);
									$this->_th( 'priority',	__('Priority',NDZ),	FALSE	);
									$this->_th( 'status',	__('Status',NDZ),	FALSE	);
									$this->_th( 'user',		__('User',NDZ),		FALSE	);	?>
						</tr>
					</tfoot>
					<tbody class="list:tasks">
						<?php foreach( $tasks as $i => $task ): ?>
							<tr id="task-<?php echo $task['task_id']; ?>"<?php echo ($i%2?'':' class="alternate"'); ?>>
								<td class="name column-name"><?php echo $task['task_name']; ?></td>
								<td class="priority column-priority"><?php echo $this->priority_readable( $task['task_priority'] ); ?></td>
								<td class="status column-status"><?php echo $this->status_readable( $task['task_status'] ); ?></td>
								<td class="user column-user"><?php echo $task['user_display_name']; ?></td>
							</tr>
							<tr class="description <?php echo ($i%2?'':' alternate'); ?>">
								<th scope="col"><?php /* _e('Description',NDZ); */ ?></th>
								<td colspan="3" class="description column-description"><?php echo $task['task_description']; ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php $this->print_attachments_list( Array( 'project_id' => $project_id ) );  ?>
		</div>
		<?php
	}
	function print_frontend_invoice_table( $client_id ){
		$invoices = array();
		foreach( $this->get_invoices( NULL, array( 'i_client_id' => $client_id ) ) as $i ){
			if( $i['invoice_status'] > 1 ){
				$invoices[] = $i;
			}
		}
		if( !count( $invoices ) ){
			echo "\r\n<h3>".__('No invoices yet.',NDZ)."</h3>";
			return;
		}
		?>
		<h2 class="invoices"><?php _e('Invoices',NDZ); ?></h2>
		<table class="widefat invoices" cellspacing="0">
			<thead>
				<tr>
					<?php	$this->_th( 'id',		__('ID',NDZ),		FALSE	);
							$this->_th( 'date',		__('Date',NDZ),		FALSE	);
							$this->_th( 'project',	__('Project',NDZ),	FALSE	);
							$this->_th( 'status',	__('Status',NDZ),	FALSE	);
							$this->_th( 'due',		__('Due',NDZ),		FALSE	);
							$this->_th( 'view',		__('View',NDZ),		FALSE	);	?>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<?php	$this->_th( 'id',		__('ID',NDZ),		FALSE	);
							$this->_th( 'date',		__('Date',NDZ),		FALSE	);
							$this->_th( 'project',	__('Project',NDZ),	FALSE	);
							$this->_th( 'status',	__('Status',NDZ),	FALSE	);
							$this->_th( 'due',		__('Due',NDZ),		FALSE	);
							$this->_th( 'view',		__('View',NDZ),		FALSE	);	?>
				</tr>
			</tfoot>
			<tbody id="the-list" class="list:invoices">
				<?php foreach( $invoices as $i => $invoice ): ?>
					<tr id="invoice-<?php echo $invoice['invoice_id']; ?>" class="<?php echo ($invoice['invoice_total']<=$invoice['invoice_paid'])?'paid-in-full':''; echo ($i%2?'':' alternate'); ?>">
						<?php	$this->_td( 'id',		$invoice['invoice_id'],																FALSE	);
								$this->_td( 'date',		$invoice['invoice_date'],															FALSE	);
								$this->_td( 'project',	$invoice['project_name'],															FALSE	);
								$this->_td( 'status',	$this->invoice_status_readable( $invoice['invoice_status'] ),						FALSE	);
								$this->_td( 'due',		__('$',NDZ).number_format( ($invoice['invoice_total']-$invoice['invoice_paid']), 2 ),	FALSE	);
								$this->_td( 'view',		'<a class="view-link" href="'.$this->append_get_variable( get_permalink( get_option( 'Ndizi Frontend Page' ) ), 'invoice='.$invoice['invoice_id'] ).'">'.__('View',NDZ).' &rarr;</a>',	FALSE	);	?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}
	function print_attachments_list( $args ){
		$attachments = $this->get_attachments( $args );
		if( count( $attachments ) ): ?>
			<h4><?php _e('Attachments',NDZ); ?></h4>
			<ul class="attachments">
				<?php foreach( $attachments as $key => $att ): ?>
					<li id="attachment-<?php echo $att['attachment_id']; ?>"></li>
				<?php endforeach; ?>
			</ul>
		<?php endif;
	}
	function print_frontend_display_invoice( $invoice_id ){
		$invoice = $this->get_invoice( $invoice_id );
		$times = $this->get_times( NULL, array( 'ti_invoice_id' => $invoice_id ) );
		?>
		<div id="NDIZI-INVOICE">
		<h1><?php _e('INVOICE',NDZ); ?></h1>
		<h2 class="invoice-name"><?php echo $invoice['invoice_name']; ?></h1>
		<table class="invoice-details">
			<tr><th scope="row"><?php _e('Invoice #',NDZ); ?></th>
				<td><?php echo $invoice['invoice_id']; ?></td></tr>
			<tr><th scope="row"><?php _e('Submitted On',NDZ); ?></th>
				<td><?php echo $invoice['invoice_date']; ?></td></tr>
		</table>

		<h3 class="submitted-to"><?php _e('Submitted To',NDZ); ?></p>
		<table class="client-info">
			<tr><th scope="row"><?php _e('Name',NDZ); ?></th>
				<td><?php echo $invoice['client_name']; ?></td></tr>
			<tr><th scope="row"><?php _e('Address',NDZ); ?></th>
				<td><?php echo nl2br( $invoice['client_address'] ); ?></td></tr>
			<tr><th scope="row"><?php _e('Phone',NDZ); ?></th>
				<td><?php echo $invoice['client_phone']; ?></td></tr>
			<tr><th scope="row"><?php _e('Site',NDZ); ?></th>
				<td><?php echo $invoice['client_site']; ?></td></tr>
			<tr><th scope="row"><?php _e('E-mail',NDZ); ?></th>
				<td><?php echo $invoice['client_email']; ?></td></tr>
		</table>
		<?php /* Local Company Info to go here! */ ?>

		<?php if( strlen( trim( $invoice['invoice_description'] ) ) ): ?>
			<h3><?php _e('Description',NDZ); ?></h3>
			<blockquote class="invoice-description">
				<p><?php echo $invoice['invoice_description']; ?></p>
			</blockquote>
		<?php endif; ?>
		
		<?php if( count( $times ) ): ?>
			<table class="ndizi-invoice-times widefat" id="ndizi-invoice-<?php echo $invoice['invoice_id']; ?>-times">
				<thead>
					<tr><?php	$this->_th( 'id',			__('ID',NDZ),			FALSE	);
								$this->_th( 'user',			__('User',NDZ),			FALSE	);
								$this->_th( 'date',			__('Date',NDZ),			FALSE	);
								$this->_th( 'description',	__('Description',NDZ),	FALSE	);
								$this->_th( 'duration',		__('Duration',NDZ),		FALSE	);	?>
					</tr>
				</thead>
				<tfoot>
					<tr><?php	$this->_th( 'id',			NULL,				FALSE	);
								$this->_th( 'user',			NULL,				FALSE	);
								$this->_th( 'date',			NULL,				FALSE	);
								$this->_th( 'description',	__('Total:',NDZ),	FALSE	);
								$this->_th( 'duration',		$this->time_total( array( 'ti_invoice_id' => $invoice_id ) ),	FALSE	);	?>
					</tr>			
				</tfoot>
				<tbody>
					<?php foreach( $times as $r => $t ): ?>
						<tr id="invoice-<?php echo $invoice['invoice_id']; ?>-time-<?php echo $t['time_id']; ?>"<?php echo ($r%2?'':' class="alternate"'); ?>>
							<?php	$this->_td(	'id',			$t['time_id']			);
									$this->_td(	'user',			$t['user_display_name']	);
									$this->_td(	'date',			$t['time_date']			);
									$this->_td(	'description',	$t['time_description']	);
									$this->_td(	'duration',		$t['time_duration']		);	?>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		
		<h3><?php _e('Total'); ?></h3>
		<table class="ndizi-invoice-totals widefat" id="ndizi-invoice-<?php echo $invoice['invoice_id']; ?>-totals">
			<thead>
				<tr><?php	$this->_th( 'total',		__('Total',NDZ),	FALSE	);
							$this->_th( 'paid',			__('Paid',NDZ),		FALSE	);
							$this->_th( 'balance',		__('Balance',NDZ),	FALSE	);	?>
				</tr>
			</thead>
			<tbody>
				<tr id="invoice-<?php echo $invoice['invoice_id']; ?>-totals">
					<?php	$this->_td(	'total',		__('$',NDZ).number_format( $invoice['invoice_total'],							2 )	);
							$this->_td(	'paid',			__('$',NDZ).number_format( $invoice['invoice_paid'],							2 )	);
							$this->_td(	'balance',		__('$',NDZ).number_format( $invoice['invoice_total']-$invoice['invoice_paid'],	2 )	);	?>
				</tr>
			</tbody>
		</table>
		
		<?php if( strlen( trim( $invoice['invoice_terms'] ) ) ): ?>
			<h3><?php _e('Terms',NDZ); ?></h3>
			<blockquote class="invoice-terms">
				<p><?php echo $invoice['invoice_terms']; ?></p>
			</blockquote>
		<?php endif; ?>
		
		</div><!-- /NDIZI-INVOICE -->
		<?php
	}
	function print_frontend_task_form( $client_id ){
		$x = Array();
		$what = __('Add New Task',NDZ);
		?>
		<h2 id="add-task"><?php echo $what; ?></h2>
		<form method="post" action="<?php echo get_permalink( get_option( 'Ndizi Frontend Page' ) ); ?>#add-task">
			<?php $this->_form_row( 'ndizi_action', NULL, 'frontend_add_task', 'hidden' ); ?>
			<table class="form-table">
			<tbody>
				<tr><th scope="row"><label for="frm_project_id"><?php _e('Project',NDZ); ?></label></th>
					<td><?php $this->print_projects_dropdown( NULL, 'project_id', array( 'client_id' => (int) $client_id ) ); ?></td></tr>
				<?php	$this->_form_row( 'task_name',		__('Name',NDZ),			NULL 				);
						$this->_form_row( 'description',	__('Description',NDZ),	NULL,	'wysiwyg'	);
						$this->_form_row( 'priority',		__('Priority',NDZ),		3,		'priority'	);
						$this->_form_row( 'submit',			NULL,					$what,	'submit'	);	?>
			</tbody>
			</table>
		</form>
		<?php
	}
	function print_frontend_attachment_form( $type, $id, $parent = 0 ){
		?>
		<form method="post" action="<?php echo get_permalink( get_option( 'Ndizi Frontend Page' ) ); ?>#add-task">
			<?php $this->_form_row( 'ndizi_action', NULL, 'frontend_add_attachment', 'hidden' ); ?>
			<table class="form-table">
			<tbody>
				<?php	$this->_form_row( 'ndizi_action',	NULL,				'frontend_add_attachment',	'hidden'	);
						$this->_form_row( 'parent',			NULL,				$parent,					'hidden'	);
						$this->_form_row( 'item_id',		NULL,				$id, 						'hidden'	);
						$this->_form_row( 'item_type',		NULL,				$type, 						'hidden'	);
						$this->_form_row( 'message',		__('Message',NDZ),	NULL,						'wysiwyg'	);
						$this->_form_row( 'file',			__('File',NDZ),		NULL,						'file'		);
						$this->_form_row( 'submit',			NULL,				__('Submit',NDZ),			'submit'	);	?>
			</tbody>
			</table>
		</form>
		<?php
	}
	function frontend_add_task(){
		$params = Array(
			'client_id'		=>	'client_id',
			'project_id'	=>	'project_id',
			'user_id'		=>	'user_id',
			'task_name'		=>	'name',
			'description'	=>	'description',
			'priority'		=>	'priority',
		);
		$args = $this->_params_to_args( $params, $_POST );
		if( isset( $args['project_id'] ) && !isset( $args['client_id'] ) ){
			$args['client_id'] = $this->get_client_from_project( $args['project_id'] );
		}
		if( !$args ) return;
		return $this->add_task( $args );
	}
	function frontend_add_attachment(){
		$params = Array(
			'parent'		=>	'parent',
			'id'			=>	'item_id',
			'type'			=>	'item_type',
			'message'		=>	'message',
		);
		$args = $this->_params_to_args( $params, $_POST );
		$args['when'] = date("Y-m-d H:i:s");
		$args['user_id'] = isset( $_SESSION['ndizi_client_id'] ) ? 0 : $this->get_curr_user_id() ;
		if( isset( $_FILES['file'] ) ){
			$args['file'] = $this->_process_file( $_FILES['file']['tmp_name'] );
		}
		return $this->add_attachment( $args );
	}

################################################
# Other Admin Display Functions                #
################################################

	function email_key_link( $client_id ){
		return '<a class="mail-link" href="'.$this->friendly_page_link( 'clients', FALSE ).'&amp;paged='.$this->_val($_GET,'paged',1).'&amp;action=email_key&amp;id='.intval($client_id).'">'.__('Email Key').' &rarr;</a>';
	}
	function email_key( $client_id ){
		if( $login_link = get_permalink( get_option( 'Ndizi Frontend Page' ) ) ){
			$client = $this->get_client( $client_id );
			extract( $client );
			
			$to = $client_email;
			
			$message = __('Hello, ').$client_name."\r\n\r\n"
					.__('You can log into our Project Management System at the following url:')."\r\n\r\n"
					.$login_link."\r\n\r\n"
					.__('When prompted, please enter your access key:')."\r\n\r\n"
					.$client_access_key."\r\n\r\n"
					.__('and you should be able to access our client area.');
	
			$subject = __('Client Access Key - ').get_bloginfo('name');
	
			if( wp_mail( $to, $subject, $message ) ){
				add_action( 'admin_notices', array( $this, 'confirm_email_sent' ) );
			}else{
				add_action( 'admin_notices', array( $this, 'confirm_email_not_sent' ) );
			}
		}else{
			add_action( 'admin_notices', array( $this, 'confirm_no_page_set_up' ) );
		}
	}
	function confirm_email_sent(){
		?>
		<div class="updated">
			<p>Email Notification Sent.</p>
		</div>
		<?php 
	}
	function confirm_email_not_sent(){
		?>
		<div class="updated">
			<p><strong>ERROR:</strong> Email Notification Not Sent.</p>
		</div>
		<?php 
	}
	function confirm_no_page_set_up(){
		?>
		<div class="updated">
			<p><strong>ERROR:</strong> You have not yet set up a front-end page for clients to use.</p>
		</div>
		<?php 
	}
	function admin_header_form(){
		if( current_user_can( 'edit_posts' ) ):
			if( get_option( 'Ndizi Print Admin Header Form' ) == 'YES' ):
			?>
			<form method="post" action="<?php $this->friendly_page_link( 'times' ); ?>" class="alignright" style="margin:11px 5px 0;color:#777;">
				| 
				<input type="hidden" name="ndizi_action" value="add_time" />
				<input type="hidden" name="user_id" value="<?php echo $this->get_curr_user_id(); ?>" />
				<input size="12" type="text" name="date" class="ndizi-datepicker" value="<?php echo date("Y-m-d"); ?>" />
				<input size="5"  type="text" onblur="this.value=(this.value=='')?'00:00':this.value;" onfocus="this.value=(this.value=='00:00')?'':this.value;" name="duration" value="00:00" />
				<?php $this->print_projects_dropdown( NULL, NULL, NULL, 'Project' ); ?>
				<input type="submit" class="button" value="<?php _e('Enter Time',NDZ); ?> &raquo;" />
			</form>
		    <?php
			//	<input size="10" type="text" onblur="this.value=(this.value=='')?'Summary':this.value;" onfocus="this.value=(this.value=='Summary')?'':this.value;" name="description" value="Summary" />
			endif;
		endif;
	}
	function admin_dashboard_widget(){
		if( current_user_can( 'edit_posts' ) ):
			$u = $this->get_curr_user_id();
			if( current_user_can( 'manage_options' ) ):
				?>
				<div class="table table_general">
					<p class="sub"><?php bloginfo('name'); ?> has:</p>
					<table>
						<tbody>
							<tr class="first"><td class="b"><a href="<?php $this->friendly_page_link('clients'); ?>"><?php echo $this->client_count(); ?></a></td>
								<td class="t"><a href="<?php $this->friendly_page_link('clients'); ?>"><?php echo _n('Client','Clients',$this->client_count(),NDZ); ?></a></td></tr>
							<tr><td class="b"><a href="<?php $this->friendly_page_link('projects'); ?>"><?php echo $this->project_count(); ?></a></td>
								<td class="t"><a href="<?php $this->friendly_page_link('projects'); ?>"><?php echo _n('Project','Projects',$this->project_count(),NDZ); ?></a></td></tr>
							<tr><td class="b"><a href="<?php $this->friendly_page_link('tasks'); ?>"><?php echo $this->task_count(); ?></a></td>
								<td class="t"><a href="<?php $this->friendly_page_link('tasks'); ?>"><?php echo _n('Task','Tasks',$this->task_count(),NDZ); ?></a></td></tr>
							<tr><td class="b"><a href="<?php $this->friendly_page_link('times'); ?>"><?php echo $this->time_count(); ?></a></td>
								<td class="t"><a href="<?php $this->friendly_page_link('times'); ?>"><?php echo _n('Time Report','Time Reports',$this->time_count(),NDZ); ?></a></td></tr>
							<tr><td class="b"><a href="<?php $this->friendly_page_link('times'); ?>"><?php echo $this->time_total(); ?></a></td>
								<td class="t"><a href="<?php $this->friendly_page_link('times'); ?>"><?php _e('Hours Logged',NDZ); ?></a></td></tr>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
			<div class="table table_user">
				<p class="sub">You currently have:</p>
				<table>
					<tbody>
						<tr class="first"><td class="b"><a href="<?php $this->friendly_page_link('tasks'); ?>"><?php echo $this->task_count(array('user_id'=>$u)); ?></a></td>
							<td class="t"><a href="<?php $this->friendly_page_link('tasks'); ?>"><?php echo _n('Task Assigned','Tasks Assigned',$this->task_count(array('user_id'=>$u)),NDZ); ?></a></td></tr>
						<tr><td class="b"><a href="<?php $this->friendly_page_link('tasks'); ?>"><?php echo $this->time_count(array('user_id'=>$u)); ?></a></td>
							<td class="t"><a href="<?php $this->friendly_page_link('tasks'); ?>"><?php echo _n('Time Report','Time Reports',$this->time_count(array('user_id'=>$u)),NDZ); ?></a></td></tr>
						<tr><td class="b"><a href="<?php $this->friendly_page_link('times'); ?>"><?php echo $this->time_total(array('user_id'=>$u)); ?></a></td>
							<td class="t"><a href="<?php $this->friendly_page_link('times'); ?>"><?php _e('Hours Logged',NDZ); ?></a></td></tr>
					</tbody>
				</table>
			</div>
			<div class="versions" style="clear:both;">
				<p><?php _e('This is',NDZ); ?> <span class="b"><a href="http://wordpress.org/extend/plugins/ndizi-project-management/"><?php _e('Ndizi Project Management',NDZ); ?></a></span> <?php _e('version',NDZ); ?> <?php echo $this->version; ?></p>
			</div>
			<?php
		endif;
	}
	function frontend_time_entry_control(){
		if (isset($_POST['ndizi_w_title']))	update_option( 'ndizi_w_title', attribute_escape( $_POST['ndizi_w_title'] ) );
		?>
		<p><label>
			<strong><?php _e('Widget Title',NDZ); ?>:</strong><br />
			<input class="widefat" type="text" name="ndizi_w_title" value="<?php echo get_option( 'ndizi_w_title' ); ?>" />
		</label></p>
		<p><?php _e('What do you want the title above the time form to be?',NDZ); ?></p>
		<?php
	}
	function frontend_time_entry_widget( $args ){
		if( current_user_can( 'edit_posts' ) ):
			echo $args['before_widget'];
			echo $args['before_title'] . get_option( 'ndizi_w_title' ) . $args['after_title'];
			?>
			<form method="post" action="<?php bloginfo('wpurl'); ?>/wp-admin/admin.php?page=ndizi_time">
				<input type="hidden" name="ndizi_action" value="add_time" />
				<input type="hidden" name="user_id" value="<?php echo $this->get_curr_user_id(); ?>" />
				<ul style="list-style-type:none;">
					<li><label><strong><?php _e('Date',NDZ); ?></strong><br />
						<input type="text" name="date" value="<?php echo date("Y-m-d"); ?>" class="ndizi-datepicker" />
					</label></li>
					<li><label><strong><?php _e('Summary',NDZ); ?></strong><br />
						<input type="text" name="description" onblur="this.value=(this.value=='')?'Summary':this.value;" onfocus="this.value=(this.value=='Summary')?'':this.value;" />
					</label></li>
					<li><label><strong><?php _e('Duration',NDZ); ?></strong><br />
						<input type="text"  name="duration" value="00:00" onblur="this.value=(this.value=='')?'00:00':this.value;" onfocus="this.value=(this.value=='00:00')?'':this.value;" />
					</label></li>
					<li><label><strong><?php _e('Project',NDZ); ?></strong><br />
						<?php $this->print_projects_dropdown( NULL, 'project_id' ); ?>
					</label></li>
					<li><label>
						<input type="submit" class="button" value="<?php _e('Enter Time',NDZ); ?> &raquo;" />
					</label></li>
				</ul>
			</form>
		    <?php
		    echo $args['after_widget'];
		endif;
	}
	function frontend_client_login_control(){
		if (isset($_POST['ndizi_client_login_title']))	update_option( 'ndizi_client_login_title', attribute_escape( $_POST['ndizi_client_login_title'] ) );
		?>
		<p><label>
			<strong><?php _e('Widget Title',NDZ); ?>:</strong><br />
			<input class="widefat" type="text" name="ndizi_client_login_title" value="<?php echo get_option( 'ndizi_client_login_title' ); ?>" />
		</label></p>
		<p><?php _e('What do you want the title above the client login form to be?',NDZ); ?></p>
		<?php
	}
	function frontend_client_login_widget( $args ){
		echo $args['before_widget'];
		echo $args['before_title'] . get_option( 'ndizi_client_login_title' ) . $args['after_title'];
		if( isset( $_SESSION['ndizi_client_id'] ) ){
			$this->print_logout_form();
		} else {
			$this->print_login_form();
		}
	    echo $args['after_widget'];
	}

################################################
# General Utility Functions                    #
################################################

	function print_pager( $page, $link, $qty_items ){
		$page = intval( $page );
		$qty_items = intval( $qty_items );
		$qty_pages = ceil( $qty_items / $this->pagination );
		$first_num = ( ( $page - 1 ) * $this->pagination ) + 1;
		$last_num = ( $qty_items > ( $page * $this->pagination ) ) ? $page * $this->pagination : $qty_items ;
		$ellipses = FALSE;
		?>
		<div class="tablenav">
		<div class="tablenav-pages">
		<?php if( $qty_pages > 1 ): ?>
			<span class="displaying-num"><?php printf(__('Displaying %1$s&mdash;%2$s of %3$s',NDZ),$first_num,$last_num,$qty_items); ?></span>
			<?php if( $page > 1 ): ?>
				<a class="prev page-numbers" href="<?php echo $link; ?>&amp;paged=<?php echo $page-1; ?>">&laquo;</a>
			<?php endif;
			for( $i = 1; $i <= $qty_pages; $i++ ) {
				if( ( $i == 1 ) || ( abs( $i - $page ) < 2 ) || ( $i == $qty_pages ) ) {
					if( $i != $page ): ?>
						<a class="page-numbers" href="<?php echo $link; ?>&amp;paged=<?php echo $i; ?>"><?php echo $i; ?></a>
					<?php else: ?>
						<span class="page-numbers current"><?php echo $i; ?></span>
					<?php endif;
					$ellipses = FALSE;
				} else {
					if( $ellipses == FALSE ) { ?>
						<span class="page-numbers dots">...</span>
						<?php $ellipses = TRUE;
					}
				}
			}
			if( $page < $qty_pages ): ?>
				<a class="next page-numbers" href="<?php $link; ?>&amp;paged=<?php echo $page+1; ?>">&raquo;</a>
			<?php endif; ?>
		<?php else: ?>
			<span class="displaying-num"><?php printf(__('Displaying all %s results',NDZ),$qty_items); ?></span>
		<?php endif; ?>
		</div><!-- /tablenav-pages -->
		</div><!-- /tablenav -->
		<?php 
	}
	function _th( $name, $label, $num = FALSE, $thead = FALSE ){
		?>	<th scope="col"<?php if( $thead ) echo " id=\"$name\""; ?> class="manage-column column-<?php echo $name.($num?' num':''); ?>"><?php echo $label; ?></th>
		<?php
	}
	function _rth( $name, $label ){
		?>	<th scope="row" class="cb check-column num"><input type="checkbox" name="<?php echo $name; ?>[]" value="<?php echo $value ?>" /></th>
		<?php
	}
	function _td( $name, $content, $num = FALSE ){
		?>	<td class="<?php echo $name; ?> column-<?php echo $name.($num?' num':''); ?>"><?php echo $content; ?></td>
		<?php
	}
	function _form_row( $name, $label, $value = NULL, $type = 'text' ){
		if( 'text' == $type ):
			?>	<tr><th scope="row"><label for="frm_<?php echo $name; ?>"><?php echo $label; ?></label></th>
					<td><input class="widefat" type="text" id="frm_<?php echo $name; ?>" name="<?php echo $name; ?>" value="<?php echo $value; ?>" /></td></tr>
			<?php
		elseif( 'date' == $type ):
			?>	<tr><th scope="row"><label for="frm_<?php echo $name; ?>"><?php echo $label; ?></label></th>
					<td><input class="ndizi-datepicker" type="text" id="frm_<?php echo $name; ?>" name="<?php echo $name; ?>" value="<?php echo $value; ?>" /></td></tr>
			<?php
		elseif( 'wysiwyg' == $type ):
			?>	<tr><th scope="row"><label for="frm_<?php echo $name; ?>"><?php echo $label; ?></label></th>
					<td><div class="postbox"><textarea id="frm_<?php echo $name; ?>" name="<?php echo $name; ?>" class="wysiwyg"><?php echo $value; ?></textarea></div></td></tr>
			<?php
		elseif( 'yn' == $type ):
			?>	<tr><th scope="row"><?php echo $label; ?></th>
					<td><label><input type="radio" name="<?php echo $name; ?>" value="y" <?php if('y'==$value) echo 'checked="checked" '; ?>/> Yes</label>
						<label><input type="radio" name="<?php echo $name; ?>" value="n" <?php if('n'==$value) echo 'checked="checked" '; ?>/> No</label></td></tr>
			<?php
		elseif( 'clients' == $type ):
			?>	<tr><th scope="row"><label for="frm_<?php echo $name; ?>"><?php echo $label; ?></label></th>
					<td><?php $this->print_clients_dropdown( $value, $name ); ?></td></tr>
			<?php
		elseif( 'projects' == $type ):
			?>	<tr><th scope="row"><label for="frm_<?php echo $name; ?>"><?php echo $label; ?></label></th>
					<td><?php $this->print_projects_dropdown( $value, $name ); ?></td></tr>
			<?php
		elseif( 'tasks' == $type ):
			?>	<tr><th scope="row"><label for="frm_<?php echo $name; ?>"><?php echo $label; ?></label></th>
					<td><?php $this->print_tasks_dropdown( $value, $name ); ?></td></tr>
			<?php
		elseif( 'users' == $type ):
			?>	<tr><th scope="row"><label for="frm_<?php echo $name; ?>"><?php echo $label; ?></label></th>
					<td><?php wp_dropdown_users( Array(	'show_option_none' => '( none )',
														'echo'             => 1,
														'selected'         => (int) $value,
														'name'             => $name,
														'id'               => 'frm_'.$name ) ) ?></td></tr>
			<?php
		elseif( 'priority' == $type ):
			?>	<tr><th scope="row"><label for="frm_<?php echo $name; ?>"><?php echo $label; ?></label></th>
					<td><?php $this->print_priority_options_dropdown( $value, $name ); ?></td></tr>
			<?php
		elseif( 'status' == $type ):
			?>	<tr><th scope="row"><label for="frm_<?php echo $name; ?>"><?php echo $label; ?></label></th>
					<td><?php $this->print_status_options_dropdown( $value, $name ); ?></td></tr>
			<?php
		elseif( 'invoice_status' == $type ):
			?>	<tr><th scope="row"><label for="frm_<?php echo $name; ?>"><?php echo $label; ?></label></th>
					<td><?php $this->print_invoice_status_options_dropdown( $value, $name ); ?></td></tr>
			<?php
		elseif( 'permissions' == $type ):
			?>	<tr><th scope="row"><label for="frm_<?php echo $name; ?>"><?php echo $label; ?></label></th>
					<td><?php $this->print_permissions_options_dropdown( $value, $name ); ?></td></tr>
			<?php
		elseif( 'hidden' == $type ):
			?>	<input type="hidden" name="<?php echo $name; ?>" value="<?php echo $value; ?>" />
			<?php
		elseif( 'image' == $type ):
			?>	<input type="hidden" name="action" value="wp_handle_upload" />
				<tr><th scope="row"><label for="frm_<?php echo $name; ?>"><?php echo $label; ?></label></th>
					<td><?php if( strlen( $value ) ): ?><strong>Current:</strong> <img src="<?php echo $value; ?>" /><br />
						<?php endif; ?><input type="file" id="frm_<?php echo $name; ?>" name="<?php echo $name; ?>" /></td></tr>
			<?php
		elseif( 'submit' == $type ):
			?>	<tr><th scope="row"></th>
					<td><input type="submit" class="button button-primary" value="<?php echo $value; ?> &rarr;" /></td></tr>
			<?php
		endif;

		// Add an action for people to add and catch other fields.
		do_action( 'ndizi_form_field_maker', array( 'name' => $name, 'label' => $label, 'value' => $value, 'type' => $type ) );

		return;
	}
	function _show_active( $active ){
		switch( $active ){
			case 'y':
				return '<span class="item-active">'.__('Active',NDZ).'</span>';
			default:
				return '<span class="item-inactive">'.__('Inactive',NDZ).'</span>';
		}
	}
	function get_client_from_project( $project_id ){
		if( !$project_id ){
			return 0;
		}
		global $wpdb;
		return $wpdb->get_var( "SELECT `client_id` FROM $this->project_table WHERE `id` = '".intval( $project_id )."'" );
	}
	function get_curr_user_id(){
		$user = wp_get_current_user();
		return $user->ID;
	}
	function _val( $array, $index, $otherwise = NULL ){
		if( isset( $array[$index] ) ) {
			return $array[$index];
		} else {
			return $otherwise;
		}
	}
	function table_has_column( $table, $column ){
		global $wpdb;
		return (boolean) count( $wpdb->get_results( "SHOW COLUMNS FROM `".$wpdb->escape( $table )."` LIKE '".$wpdb->escape( $column )."'", ARRAY_A ) );
	}
	function table_has_key( $table, $key ){
		global $wpdb;
		return (boolean) count( $wpdb->get_results( "SHOW KEYS FROM `".$wpdb->escape( $table )."` LIKE '".$wpdb->escape( $key )."'", ARRAY_A ) );
	}
	function append_get_variable( $url, $get = '' ){
		if( FALSE === strpos( $url, '?' ) ){
			return $url.'?'.$get;
		} else {
			return $url.'&amp;'.$get;
		}
	}
	function _parse( $args = Array() ){
		global $wpdb;
		$args = apply_filters( 'ndizi_pre_parse_args', $args );
		$params = Array();
		if( is_array( $args ) ){
			foreach( $args as $key => $value ){
				if( substr( $key, 1, 1) == '_' ){
					$key = substr( $key, 0, 1 ).'`.`'.substr( $key, 2 );
				}
				if( substr( $key, 2, 1) == '_' ){
					$key = substr( $key, 0, 2 ).'`.`'.substr( $key, 3 );
				}
				$params[] = " `$key` = '".$wpdb->escape( $value )."' ";
			}
		}
		return apply_filters( 'ndizi_post_parse_params', $params );
	}
	function _parse_for_url( $args = Array() ){
		$returnMe = '';
		$args = apply_filters( 'ndizi_pre_parse_for_url', $args );
		if( is_array( $args ) ){
			foreach( $args as $k => $v ){
				$returnMe .= "&amp;{$k}={$v}";
			}
		}
		return apply_filters( 'ndizi_parsed_for_url', $returnMe );
	}
	function _params_to_args( $params, $src ){
		$returnMe = Array();
		if( is_array( $params ) ){
			foreach( $params as $k => $v ){
				if( isset( $src[$k] ) ){
					$returnMe[$v] = $src[$k];
				}
			}
		}
		return $returnMe;
	}
	function _process_file( $loc ){
		if( file_exists( $loc ) ){
			$rand = rand( 100000, 999999 );
			$filename = basename( $loc );
			$u = wp_upload_dir();
			if( move_uploaded_file( $loc, $u['path']."/".$rand.".".$filename ) ){
				return $u['subdir']."/".$rand.".".$filename;
			}
		}
		return FALSE;
	}
	function bug_report( $from, $message ){
		global $wpdb;
		$tables = $wpdb->get_col( "SHOW TABLES" );
		$ndizi_tables = $wpdb->get_col( "SHOW TABLES LIKE '%ndizi%'" );
		$table_creates = Array();
		foreach( $ndizi_tables as $table ){
			$row = $wpdb->get_row( "SHOW CREATE TABLE $table ", ARRAY_A );
			if( $row ){
				$table_creates[] = implode( ": \r\n", $row );
			}
		}

		$message .= " \r\n"
					."From: ".$from." \r\n"
					."Ndizi Version: ".$this->version." \r\n"
					."WordPress Version: ".get_bloginfo( 'version' )." \r\n"
					."PHP Version: ".phpversion()." \r\n"
					."Operating System: ".$_SERVER['SERVER_SIGNATURE']." \r\n"
					."Current Folder: ".dirname(__FILE__)." \r\n"
					."WP Tables: ".implode( ', ', $tables )." \r\n"
					."--- \r\n"
					.implode( "\r\n--- \r\n", $table_creates )." \r\n";

		wp_mail('George@Stephanis.info','Bug Report from Ndizi Project Management',$message,"Reply-To: $from\r\n");
	}
}
endif;

