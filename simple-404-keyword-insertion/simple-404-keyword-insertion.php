<?php
/*
Plugin Name: Simple 404 Keyword Insertion
Plugin URI:  http://wordpress.org/extend/plugins/simple-404-keyword-insertion/
Description: This builds a custom 404 page based off the request string.
Author:      George Stephanis
Author URI:  http://www.Stephanis.info/
Version:     1.0
*/

if( ! class_exists( 'simple_fourohfour_keyword_insertion' ) ):
class simple_fourohfour_keyword_insertion {

	function go(){
		add_filter( '404_template', array( __CLASS__, '_404_template' ) );
	}
	function _404_template( $x ){
		status_header( 200 );
		query_posts( 'pagename=404-page' );
		add_filter( 'wp_title', array( __CLASS__, 'get_keywords_caps' ), 90 );
		add_shortcode( '404-keywords', array( __CLASS__, 'get_keywords' ) );
		add_filter( 'the_title', 'do_shortcode' );
		return locate_template(array('page.php','index.php'));
	}
	function get_keywords_caps( $title = '' ){
		return self::get_keywords( true );
	}
	function get_keywords( $caps = false ){
		$k = ' '.trim( preg_replace( '/\W/', ' ', urldecode( $_SERVER['REQUEST_URI'] ) ) ).' ';
		if( $caps ){
			return ucwords( $k );
		}else{
			return $k;
		}
	}
	function activate(){
		global $wpdb;
		if( $post_id = (int) $wpdb->get_var("SELECT `ID` FROM `$wpdb->posts` WHERE `post_name` = '404-page'") ){
		
		}else{
			if( $current_user = wp_get_current_user() ){
				$post_id = wp_insert_post( array(
					'post_title' => '[404-keywords]',
					'post_content' => 'Here are the keywords: `[404-keywords]`.  Wow, isn&rsquo;t that neato?',
					'post_status' => 'publish',
					'post_author' => $current_user->ID,
					'post_type'=> 'page',
					'post_name' => '404-page',
				) );  
			}
		}
	}

}
simple_fourohfour_keyword_insertion::go();
register_activation_hook( __FILE__, array( 'simple_fourohfour_keyword_insertion', 'activate' ) );
endif;
