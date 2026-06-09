<?php
/*
Plugin Name: Ndizi Project Management
Plugin URI: http://wordpress.org/extend/plugins/ndizi-project-management/
Description: Ndizi Project Management adds a complete project management system to WordPress.
Author: George Stephanis
Author URI: http://www.Stephanis.info/
Version: 0.9.7.0
*/

require_once( 'Ndizi.class.php' );

if( class_exists( 'Ndizi' ) ) {
	$ndizi = new Ndizi();
	register_activation_hook	(	__FILE__,				Array( &$ndizi, 'on_activate' )				);
	register_deactivation_hook	(	__FILE__,				Array( &$ndizi, 'on_deactivate' )			);
}

