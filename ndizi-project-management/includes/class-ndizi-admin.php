<?php
/**
 * Admin interface coordinator — delegates to focused subclasses.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ndizi_Admin {

	public static function init() {
		Ndizi_Settings::init();
		Ndizi_Meta_Boxes::init();
		Ndizi_List_Tables::init();
		Ndizi_Ajax::init();
		Ndizi_Reports::init();
	}

	/**
	 * Initialize Gantt module hooks (called from module registry).
	 */
	public static function init_gantt() {
		Ndizi_Settings::init_gantt();
	}
}
