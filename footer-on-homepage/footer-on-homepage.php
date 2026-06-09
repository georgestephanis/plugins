<?php
/*
Plugin Name: Footer on Homepage
Plugin URI:  http://wordpress.org/extend/plugins/footer-on-homepage/
Description: Provides a space in the administrative area to add SEO-Driven Copy to *only* your homepage.  It will, by default, display a link that (when clicked) displays the entered copy.
Author:      George Stephanis
Author URI:  http://www.Stephanis.info/
Version:     1.0.1
*/

if( ! class_exists( 'footer_on_homepage' ) ):
class footer_on_homepage {

	function go(){
		add_action( 'init', array( __CLASS__, 'init' ) );
	}
	function init(){
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
		add_action( 'wp_head', array( __CLASS__, 'wp_head' ) );
		add_action( 'wp_footer', array( __CLASS__, 'wp_footer' ) );
	}
	function admin_menu(){
		add_theme_page( 'Homepage Footer', 'Homepage Footer', 'edit_posts', 'homepage-footer', array( __CLASS__, 'page_homepage_footer' ) );
	}
	function page_homepage_footer(){
		if( $_POST ) self::catch_post();
		?>
		<div class="wrap">
			<div id="icon-edit-pages" class="icon32"><br /></div>
			<h2>Homepage Footer</h2>
			<br class="clear" />			
			<form method="post">
				<table class="form-table">
					<tr><th scope="row"><label for="<?php echo __CLASS__; ?>_more">&ldquo;More Info&rdquo; Link Text:</label></th>
						<td><input type="text" id="<?php echo __CLASS__; ?>_more" name="<?php echo __CLASS__; ?>_more" class="widefat" value="<?php echo wptexturize(stripslashes(get_option(__CLASS__.'_more','More Info &raquo;'))); ?>" />
					<tr><th scope="row"><label for="<?php echo preg_replace('[^a-z]','',__CLASS__); ?>">SEO Footer Text on Homepage:</label></th>
						<td>
							<?php if( function_exists( 'wp_editor' ) ): ?>
								<?php wp_editor( wptexturize(stripslashes(get_option(__CLASS__))), preg_replace('[^a-z]','',__CLASS__),  array( 'media_buttons' => false, 'textarea_name' => __CLASS__ ) ); ?>
							<?php else: ?>
								<textarea name="<?php echo __CLASS__; ?>" id="<?php echo preg_replace('[^a-z]','',__CLASS__); ?>" rows="10" cols="50" class="widefat"><?php echo wptexturize(stripslashes(get_option(__CLASS__))); ?></textarea>
							<?php endif; ?>
						</td></tr>
					<tr><th scope="row"><label for="<?php echo __CLASS__; ?>_css">Additional CSS Styling:</label></th>
						<td><textarea id="<?php echo __CLASS__; ?>_css" name="<?php echo __CLASS__; ?>_css" class="widefat" rows="4"><?php echo stripslashes(get_option(__CLASS__.'_css',"#".__CLASS__."-wrapper { } /* The wrapper around everything */\r\n#".__CLASS__." { } /* The actual content's wrapper */\r\n")); ?></textarea>
				</table>
				<br />
				<input type="submit" class="button-primary" value="Save &rarr;" />
			</form>
		</div>
		<?php 
	}
	function catch_post(){
		if( isset( $_POST[__CLASS__] ) ){
			update_option(__CLASS__,$_POST[__CLASS__]);
		}
		if( isset( $_POST[__CLASS__.'_more'] ) ){
			update_option(__CLASS__.'_more',$_POST[__CLASS__.'_more']);
		}
		if( isset( $_POST[__CLASS__.'_css'] ) ){
			update_option(__CLASS__.'_css',$_POST[__CLASS__.'_css']);
		}
	}
	function wp_head(){
		if( !is_front_page() ) return;
		?>
		<!-- Start <?php echo __CLASS__; ?> styles -->
		<style>
			<?php echo stripslashes( get_option(__CLASS__.'_css') ); ?>
			.<?php echo __CLASS__; ?>-hidden {display:none;}
		</style>
		<!-- End <?php echo __CLASS__; ?> styles -->
		<?php
	}
	function wp_footer(){
		if( !is_front_page() ) return;
		?>
		<div id="<?php echo __CLASS__; ?>-wrapper">
			<a href="javascript:;" onclick="document.getElementById('<?php echo __CLASS__; ?>').className='';this.className='<?php echo __CLASS__; ?>-hidden';return false;"><?php echo wptexturize(stripslashes(get_option(__CLASS__.'_more','More Info &raquo;'))); ?></a>
			<div id="<?php echo __CLASS__; ?>" class="<?php echo __CLASS__; ?>-hidden">
				<?php echo wpautop(wptexturize(stripslashes(get_option(__CLASS__)))); ?>
			</div><!-- /<?php echo __CLASS__; ?> -->
		</div><!-- /<?php echo __CLASS__; ?>-wrapper -->
		<?php
	}

}
footer_on_homepage::go();
endif;
