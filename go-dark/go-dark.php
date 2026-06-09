<?php
/*
Plugin Name: Go Dark
Plugin URI:  http://wordpress.org/extend/plugins/go-dark/
Description: This plugin enables websites to 'go dark' on January 18th to protest SOPA/PIPA and Internet Censorship
Author:      George Stephanis
Author URI:  https://georgestephanis.wordpress.com
Version:     1.0.7
*/

if( ! class_exists( 'go_dark' ) ):
class go_dark {

	function go(){
		add_action( 'init', array( __CLASS__, 'init' ) );
	}
	function init(){
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		if( ! is_admin() && ! is_feed() ){
			if( isset( $_GET['go_dark'] ) || self::is_dark() ){
				add_action( 'template_redirect', array( __CLASS__, 'show_page' ), 99 );
			}
		}
	}
	function admin_menu(){
		add_menu_page( 'Go Dark', 'Go Dark', 'manage_options', 'go-dark', array( __CLASS__, 'page_go_dark' ) );
	}
	function page_go_dark(){
		if( $_POST ) self::catch_post();
		?>
		<div class="wrap">
			<div id="icon-edit-pages" class="icon32"><br /></div>
			<h2>Go Dark <a href="<?php bloginfo('wpurl'); ?>?go_dark" target="_blank" class="add-new-h2">Preview Display</a></h2>
			<br class="clear" />
			<?php if( self::is_dark() ): ?>
				<p><strong>Your website is currently 'dark'.</strong></p>
			<?php endif; ?>
			<form method="post">
				<?php wp_nonce_field(__CLASS__.'_action',__CLASS__.'_nonce'); ?>
				<table class="form-table">
					<tr>
						<th scope="row">Current Time</th>
						<td><?php echo date('Y/m/d H:i:s',current_time('timestamp')); ?>
							<br /><em>All times are local to your website&rsquo;s configured timezone ( GMT<?php echo get_option('gmt_offset'); ?> )</em></td>
					</tr>
					<tr>
						<th scope="row">
							<label for="<?php echo __CLASS__; ?>_start">Start</label>
						</th>
						<td>
							<input class="widefat" type="text" name="<?php echo __CLASS__; ?>_start" id="<?php echo __CLASS__; ?>_start" value="<?php echo date('Y/m/d H:i:s',self::get_start()); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="<?php echo __CLASS__; ?>_end">End</label>
						</th>
						<td>
							<input class="widefat" type="text" name="<?php echo __CLASS__; ?>_end" id="<?php echo __CLASS__; ?>_end" value="<?php echo date('Y/m/d H:i:s',self::get_end()); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">Image</th>
						<td>
							<label style="display:block; float:left; vertical-align:top;">
								<input style="display:block; float:left; margin:5px 5px 5px 10px;" type="radio" name="<?php echo __CLASS__; ?>_img" value="none" <?php checked('none',get_option(__CLASS__.'_img')); ?> />
								( none )
							</label>
							<label style="display:block; float:left; vertical-align:top;">
								<input style="display:block; float:left; margin:5px 5px 5px 10px;" type="radio" name="<?php echo __CLASS__; ?>_img" value="sign" <?php checked('sign',get_option(__CLASS__.'_img')); ?> />
								<img src="<?php echo plugins_url('blocked.png',__FILE__); ?>" width="100" style="background:#111; margin-top:5px;" />
							</label>
							<label style="display:block; float:left; vertical-align:top;">
								<input style="display:block; float:left; margin:5px 5px 5px 10px;" type="radio" name="<?php echo __CLASS__; ?>_img" value="seal" <?php checked('seal',get_option(__CLASS__.'_img')); ?> />
								<img src="<?php echo plugins_url('seal.png',__FILE__); ?>" width="100" style="background:#111; margin-top:5px;" />
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="<?php echo preg_replace('[^a-z]','',__CLASS__); ?>">Explanation Text on Homepage:</label>
							<hr />
							<p>Some useful resources for you to consider:</p>
							<ul>
								<li><a href="http://sopastrike.com/" target="_blank">http://sopastrike.com/</a></li>
								<li><a href="http://demandprogress.org/" target="_blank">http://demandprogress.org/</a></li>
								<li><a href="http://www.digitaltrends.com/opinion/sopa-sponsor-rep-lamar-smith-to-sopa-opponents-you-dont-matter/" target="_blank">http://www.digitaltrends.com/opinion/sopa-sponsor-rep-lamar-smith-to-sopa-opponents-you-dont-matter/</a></li>
								<li><a href="http://vimeo.com/31100268" target="_blank">Vimeo Video</a></li>
							</ul>
						</th>
						<td>
							<?php if( function_exists( 'wp_editor' ) ): ?>
								<?php wp_editor( wptexturize(stripslashes(get_option(__CLASS__,self::get_default_text()))), preg_replace('[^a-z]','',__CLASS__),  array( 'media_buttons' => false, 'textarea_name' => __CLASS__ ) ); ?>
							<?php else: ?>
								<textarea name="<?php echo __CLASS__; ?>" id="<?php echo preg_replace('[^a-z]','',__CLASS__); ?>" rows="10" cols="50" class="widefat"><?php echo wptexturize(stripslashes(get_option(__CLASS__,self::get_default_text()))); ?></textarea>
							<?php endif; ?>
						</td>
					</tr>
				</table>
				<br />
				<?php if( function_exists( 'submit_button' ) ): ?>
					<?php submit_button(); ?>
				<?php else: ?>
					<p class="submit"><input type="submit" name="submit" id="submit" class="button-primary" value="Save Changes"  /></p>
				<?php endif; ?>
			</form>
		</div>
		<?php 
	}
	function catch_post(){
		if( !wp_verify_nonce($_REQUEST[__CLASS__.'_nonce'],__CLASS__.'_action') ){
			return;
		}
	
		if( isset( $_POST[__CLASS__.'_start'] ) ){
			update_option(__CLASS__.'_start',strtotime($_POST[__CLASS__.'_start']));
		}
		if( isset( $_POST[__CLASS__.'_end'] ) ){
			update_option(__CLASS__.'_end',strtotime($_POST[__CLASS__.'_end']));
		}
		if( isset( $_POST[__CLASS__.'_img'] ) ){
			update_option(__CLASS__.'_img',$_POST[__CLASS__.'_img']);
		}
		if( isset( $_POST[__CLASS__] ) ){
			update_option(__CLASS__,$_POST[__CLASS__]);
		}
	}
	function show_page(){
		$font = 'Covered By Your Grace';
		if( ! headers_sent() ){
			header('HTTP/1.1 503 Service Temporarily Unavailable');
			header('Status: 503 Service Temporarily Unavailable');

			$time_left = self::get_end() - current_time('timestamp');
			if( ( $time_left > 0 ) && ( self::get_start() < current_time('timestamp') ) ){
				header('Retry-After: '.$time_left); // in seconds
			}
		}
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<title><?php echo strip_tags(wptexturize(stripslashes(get_option(__CLASS__,self::get_default_text())))); ?></title>
	<link href="http://fonts.googleapis.com/css?family=<?php echo str_replace(' ','+',$font); ?>" rel="stylesheet" />
	<style>
		body,html {
			margin:0;
			padding:0;
			color:#aaa;
			font-family:'<?php echo $font; ?>',Helvetica,sans-serif;
			font-size:20px;
			letter-spacing:1px;
			min-height:100%;
			width:100%;
			text-shadow:1px 1px 1px #222;
		}
		html {
			background:#111 url('<?php echo plugins_url('wood.jpg',__FILE__); ?>') 50% 50% repeat;
		}
		body {
			background:rgba(0,0,0,.7);
		}
		a {color:#ccc; text-decoration:none;}
		a:hover {text-decoration:underline;}
		#blocked {padding:50px 25%; text-align:center;}
	</style>
</head>
<body>
	<div id="blocked">
		<?php echo self::get_img(); ?>
		<?php echo wpautop(wptexturize(stripslashes(get_option(__CLASS__,self::get_default_text())))); ?>
	</div>
	<?php wp_footer(); ?>
</body>
</html>
		<?php
		exit;
	}
	function is_dark(){
		if( ( self::get_start() < current_time('timestamp') ) && ( current_time('timestamp') < self::get_end() ) ){
			return true;
		}
		return false;
	}
	function get_img(){
		switch( get_option(__CLASS__.'_img') ){
			case 'sign':
				return '<img src="' . plugins_url('blocked.png',__FILE__) . '" width="281" height="248" alt="BLOCKED - Censorship in Progress" />';
			case 'seal':
				return '<img src="' . plugins_url('seal.png',__FILE__) . '" width="222" height="223" alt="Censorship of this Website and its Content are (not) Authorized By RIAA/MPAA" />';
			default:
				return '';
		}
	}
	function get_default_text(){
		return get_bloginfo('name').' has gone dark from '
			.date(self::get_timedate_format(),self::get_start())
			.' until '
			.date(self::get_timedate_format(),self::get_end())
			.' to protest SOPA/PIPA and Internet Censorship.'
			."\r\n\r\n"
			.'<iframe src="http://player.vimeo.com/video/31100268?byline=0&#038;portrait=0" width="400" height="225" frameborder="0" webkitAllowFullScreen mozallowfullscreen allowFullScreen></iframe>'
			."\r\n\r\n"
			.'<a href="http://sopastrike.com/">Learn More Here &raquo;</a>';
	}
	function get_start(){
		return get_option( __CLASS__.'_start', self::get_default_start() );
	}
	function get_default_start(){
		return strtotime( '2012/01/18 8:00' );
	}
	function get_end(){
		return get_option( __CLASS__.'_end', self::get_default_end() );
	}
	function get_default_end(){
		return strtotime( '2012/01/18 20:00' );
	}
	function get_timedate_format(){
		return get_option('date_format').' \a\t '.get_option('time_format');
	}

}
go_dark::go();
endif;