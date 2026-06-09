<?php
/*
 * Plugin Name: The
 * Author: George Stephanis
 * Author URI: http://stephanis.info
 * Plugin Muses: WordCamp Philly (@WCPhilly), John Hawkins (@VegasGeek), Dre Armeda (@DreMeda), and Ben Metcalfe (@DotBen)
 * Description: This plugin adds a shortcode of [the], which replaces the shortcode with either `the`, `The`, `teh`, or `Teh` depending on whether or not the `caps` or `hipster` parameters are set.
 * Version: 1.1
 * License: GPLv2+
 */

function wcphilly_the_shortcode( $args ){
	$defaults = array(
		'hipster' => null,
		'caps'    => null,
	);
	$args = shortcode_atts( $defaults, $args, 'the' );

	$the = 'the';
	if ( $args['hipster'] ) {
		$the = 'teh';
	}
	if ( $args['caps'] ) {
		$the = ucwords( $the );
	}
	return $the;
}
add_shortcode( 'the', 'wcphilly_the_shortcode' );
