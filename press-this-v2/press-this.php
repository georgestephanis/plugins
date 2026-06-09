<?php
/**
 * Press This Display and Handler.
 *
 * @package WordPress
 * @subpackage Press_This
 */

define('IFRAME_REQUEST' , true);

/** WordPress Administration Bootstrap */
$admin_php_path = realpath( dirname(__FILE__).'/../../../wp-admin/admin.php' );
require_once( $admin_php_path );

header( 'Content-Type: ' . get_option( 'html_type' ) . '; charset=' . get_option( 'blog_charset' ) );

if ( ! current_user_can( 'edit_posts' ) )
	wp_die( __( 'Cheatin&#8217; uh?' ) );

require_once( './includes/press-this.php' );

define( 'PRESS_THIS_POST_TYPE', 'post' );

// For submitted posts.
if ( isset( $_REQUEST['action'] ) && 'post' == $_REQUEST['action'] ) {
	check_admin_referer( 'press-this' );
	$posted = $post_ID = press_it( PRESS_THIS_POST_TYPE );
} else {
	$post = get_default_post_to_edit( PRESS_THIS_POST_TYPE, true );
	$post_ID = $post->ID;
}

// Set Variables
$title 	= isset( $_GET['t'] ) ? trim( strip_tags( html_entity_decode( stripslashes( $_GET['t'] ), ENT_QUOTES ) ) ) : '';
$url 	= isset( $_GET['u'] ) ? esc_url( $_GET['u'] ) : '';
$image 	= isset( $_GET['i'] ) ? $_GET['i'] : '';

$selection = '';
if ( ! empty( $_GET['s'] ) ) {
	$selection = str_replace( '&apos;', "'", stripslashes($_GET['s'] ) );
	$selection = trim( htmlspecialchars( html_entity_decode( $selection, ENT_QUOTES ) ) );
}

if ( ! empty( $selection ) ) {
	$selection = preg_replace( '/(\r?\n|\r)/', '</p><p>', $selection );
	$selection = '<p>' . str_replace( '<p></p>', '', $selection ) . '</p>';
}


if ( ! empty( $_REQUEST['ajax'] ) )
	press_this_ajax( $_REQUEST['ajax'] );

_wp_admin_html_begin();
?>
<title><?php _e('Press This') ?></title>
<script>
addLoadEvent = function(func){if(typeof jQuery!="undefined")jQuery(document).ready(func);else if(typeof wpOnload!='function'){wpOnload=func;}else{var oldonload=wpOnload;wpOnload=function(){oldonload();func();}}};
var userSettings = {
		'url': '<?php echo SITECOOKIEPATH; ?>',
		'uid': '<?php if ( ! isset($current_user) ) $current_user = wp_get_current_user(); echo $current_user->ID; ?>',
		'time':'<?php echo time() ?>'
	},
	ajaxurl = '<?php echo admin_url( 'admin-ajax.php', 'relative' ); ?>',
	pagenow = 'press-this',
	typenow = '<?php echo PRESS_THIS_POST_TYPE; ?>',
//	adminpage = '',
	thousandsSeparator = '<?php echo addslashes( $wp_locale->number_format['thousands_sep'] ); ?>',
	decimalPoint = '<?php echo addslashes( $wp_locale->number_format['decimal_point'] ); ?>',
	isRtl = <?php echo (int) is_rtl(); ?>,
	photostorage = false;
</script>

<?php
	wp_enqueue_style( 'colors' );
	wp_enqueue_script( 'post' );
	do_action( 'admin_enqueue_scripts', 'press-this.php' );
	do_action( 'admin_print_styles-press-this.php' );
	do_action( 'admin_print_styles' );
	do_action( 'admin_print_scripts-press-this.php' );
	do_action( 'admin_print_scripts' );
	do_action( 'admin_head-press-this.php' ); 
	do_action( 'admin_head' );
?>

<script>
	var wpActiveEditor = 'content';

	function insert_plain_editor(text) {
		if ( typeof(QTags) != 'undefined' )
			QTags.insertContent(text);
	}
	function set_editor(text) {
		if ( '' == text || '<p></p>' == text )
			text = '<p><br /></p>';

		if ( tinyMCE.activeEditor )
			tinyMCE.execCommand('mceSetContent', false, text);
	}
	function insert_editor(text) {
		if ( '' != text && tinyMCE.activeEditor && ! tinyMCE.activeEditor.isHidden()) {
			tinyMCE.execCommand('mceInsertContent', false, '<p>' + decodeURI(tinymce.DOM.decode(text)) + '</p>', {format : 'raw'});
		} else {
			insert_plain_editor(decodeURI(text));
		}
	}
	function append_editor(text) {
		if ( '' != text && tinyMCE.activeEditor && ! tinyMCE.activeEditor.isHidden()) {
			tinyMCE.execCommand('mceSetContent', false, tinyMCE.activeEditor.getContent({format : 'raw'}) + '<p>' + text + '</p>');
		} else {
			insert_plain_editor(text);
		}
	}

	function show( tab_name ) {
		jQuery('#extra-fields').html('');
		switch( tab_name ) {
			case 'video' :
				jQuery('#extra-fields').load('<?php echo esc_url( $_SERVER['PHP_SELF'] ); ?>', { ajax: 'video', s: '<?php echo esc_attr( $selection ); ?>'}, function() {
					<?php
					$content = '';
					if ( preg_match( '/youtube\.com\/watch/i', $url) ) {
						list( $domain, $video_id ) = explode( 'v=', $url );
						$video_id = esc_attr( $video_id );
						$content = '<object width="425" height="350"><param name="movie" value="http://www.youtube.com/v/' . $video_id . '"></param><param name="wmode" value="transparent"></param><embed src="http://www.youtube.com/v/' . $video_id . '" type="application/x-shockwave-flash" wmode="transparent" width="425" height="350"></embed></object>';

					} elseif ( preg_match( '/vimeo\.com\/[0-9]+/i', $url ) ) {
						list( $domain, $video_id ) = explode( '.com/', $url );
						$video_id = esc_attr( $video_id );
						$content = '<object width="400" height="225"><param name="allowfullscreen" value="true" /><param name="allowscriptaccess" value="always" /><param name="movie" value="http://www.vimeo.com/moogaloop.swf?clip_id=' . $video_id . '&amp;server=www.vimeo.com&amp;show_title=1&amp;show_byline=1&amp;show_portrait=0&amp;color=&amp;fullscreen=1" />	<embed src="http://www.vimeo.com/moogaloop.swf?clip_id=' . $video_id . '&amp;server=www.vimeo.com&amp;show_title=1&amp;show_byline=1&amp;show_portrait=0&amp;color=&amp;fullscreen=1" type="application/x-shockwave-flash" allowfullscreen="true" allowscriptaccess="always" width="400" height="225"></embed></object>';

						if ( trim( $selection ) == '' )
							$selection = '<p><a href="http://www.vimeo.com/' . $video_id . '?pg=embed&sec=' . $video_id . '">' . $title . '</a> on <a href="http://vimeo.com?pg=embed&sec=' . $video_id . '">Vimeo</a></p>';

					} elseif ( strpos( $selection, '<object' ) !== false ) {
						$content = $selection;
					}
					?>
					jQuery('#embed-code').prepend('<?php echo htmlentities($content); ?>');
				});
				jQuery('#extra-fields').show();
				return false;
				break;
			case 'photo' :
				function setup_photo_actions() {
					jQuery('.close').click(function() {
						jQuery('#extra-fields').hide();
						jQuery('#extra-fields').html('');
					});
					jQuery('.refresh').click(function() {
						photostorage = false;
						show('photo');
					});
					jQuery('#photo-add-url').click(function(){
						var form = jQuery('#photo-add-url-div').clone();
						jQuery('#img_container').empty().append( form.show() );
					});
					jQuery('#waiting').hide();
					jQuery('#extra-fields').show();
				}

				jQuery('#waiting').show();
				if(photostorage == false) {
					jQuery.ajax({
						type: "GET",
						cache : false,
						url: "<?php echo esc_url($_SERVER['PHP_SELF']); ?>",
						data: "ajax=photo_js&u=<?php echo urlencode($url)?>",
						dataType : "script",
						success : function(data) {
							eval(data);
							photostorage = jQuery('#extra-fields').html();
							setup_photo_actions();
						}
					});
				} else {
					jQuery('#extra-fields').html(photostorage);
					setup_photo_actions();
				}
				return false;
				break;
		}
	}
	jQuery(document).ready(function($) {
		//resize screen
		window.resizeTo(720,580);
		// set button actions
		jQuery('#photo_button').click(function() { show('photo'); return false; });
		jQuery('#video_button').click(function() { show('video'); return false; });
		// auto select
		<?php if ( preg_match( '/youtube\.com\/watch/i', $url ) ): ?>
			show('video');
		<?php elseif ( preg_match( '/vimeo\.com\/[0-9]+/i', $url ) ): ?>
			show('video');
		<?php elseif ( preg_match( '/flickr\.com/i', $url ) ): ?>
			show('photo');
		<?php endif; ?>
		jQuery('#title').unbind();
		jQuery('#publish, #save').click(function() { jQuery('#saving').css('display', 'inline'); });

		$('#tagsdiv-post_tag, #categorydiv').children('h3, .handlediv').click(function(){
			$(this).siblings('.inside').toggle();
		});
	});
</script>
</head>
<?php
$admin_body_class = ( is_rtl() ) ? 'rtl' : '';
$admin_body_class .= ' locale-' . sanitize_html_class( strtolower( str_replace( '_', '-', get_locale() ) ) );
?>
<body class="press-this wp-admin <?php echo $admin_body_class; ?>">
<form action="press-this.php?action=post" method="post">
<div id="poststuff" class="metabox-holder">
	<div id="side-sortables" class="press-this-sidebar">
		<div class="sleeve">
			<?php wp_nonce_field('press-this') ?>
			<input type="hidden" name="post_type" id="post_type" value="text"/>
			<input type="hidden" name="autosave" id="autosave" />
			<input type="hidden" id="original_post_status" name="original_post_status" value="draft" />
			<input type="hidden" id="prev_status" name="prev_status" value="draft" />
			<input type="hidden" id="post_id" name="post_id" value="<?php echo (int) $post_ID; ?>" />

			<!-- This div holds the photo metadata -->
			<div class="photolist"></div>

			<div id="submitdiv" class="postbox">
				<div class="handlediv" title="<?php esc_attr_e( 'Click to toggle' ); ?>"><br /></div>
				<h3 class="hndle"><?php _e('Press This') ?></h3>
				<div class="inside">
					<p id="publishing-actions">
					<?php
						submit_button( __( 'Save Draft' ), 'button', 'draft', false, array( 'id' => 'save' ) );
						if ( current_user_can('publish_posts') ) {
							submit_button( __( 'Publish' ), 'primary', 'publish', false );
						} else {
							echo '<br /><br />';
							submit_button( __( 'Submit for Review' ), 'primary', 'review', false );
						} ?>
						<img src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" id="saving" style="display:none;" />
					</p>
					<?php if ( current_theme_supports( 'post-formats' ) && post_type_supports( 'post', 'post-formats' ) ) :
							$post_formats = get_theme_support( 'post-formats' );
							if ( is_array( $post_formats[0] ) ) :
								$default_format = get_option( 'default_post_format', '0' );
						?>
					<p>
						<label for="post_format"><?php _e( 'Post Format:' ); ?>
						<select name="post_format" id="post_format">
							<option value="0"><?php _ex( 'Standard', 'Post format' ); ?></option>
						<?php foreach ( $post_formats[0] as $format ): ?>
							<option<?php selected( $default_format, $format ); ?> value="<?php echo esc_attr( $format ); ?>"> <?php echo esc_html( get_post_format_string( $format ) ); ?></option>
						<?php endforeach; ?>
						</select></label>
					</p>
					<?php endif; endif; ?>
				</div>
			</div>

			<?php $tax = get_taxonomy( 'category' ); ?>
			<div id="categorydiv" class="postbox">
				<div class="handlediv" title="<?php esc_attr_e( 'Click to toggle' ); ?>"><br /></div>
				<h3 class="hndle"><?php _e('Categories') ?></h3>
				<div class="inside">
				<div id="taxonomy-category" class="categorydiv">

					<ul id="category-tabs" class="category-tabs">
						<li class="tabs"><a href="#category-all" tabindex="3"><?php echo $tax->labels->all_items; ?></a></li>
						<li class="hide-if-no-js"><a href="#category-pop" tabindex="3"><?php _e( 'Most Used' ); ?></a></li>
					</ul>

					<div id="category-pop" class="tabs-panel" style="display: none;">
						<ul id="categorychecklist-pop" class="categorychecklist form-no-clear" >
							<?php $popular_ids = wp_popular_terms_checklist( 'category' ); ?>
						</ul>
					</div>

					<div id="category-all" class="tabs-panel">
						<ul id="categorychecklist" class="list:category categorychecklist form-no-clear">
							<?php wp_terms_checklist($post_ID, array( 'taxonomy' => 'category', 'popular_cats' => $popular_ids ) ) ?>
						</ul>
					</div>

					<?php if ( !current_user_can($tax->cap->assign_terms) ) : ?>
					<p><em><?php _e('You cannot modify this Taxonomy.'); ?></em></p>
					<?php endif; ?>
					<?php if ( current_user_can($tax->cap->edit_terms) ) : ?>
						<div id="category-adder" class="wp-hidden-children">
							<h4>
								<a id="category-add-toggle" href="#category-add" class="hide-if-no-js" tabindex="3">
									<?php printf( __( '+ %s' ), $tax->labels->add_new_item ); ?>
								</a>
							</h4>
							<p id="category-add" class="category-add wp-hidden-child">
								<label class="screen-reader-text" for="newcategory"><?php echo $tax->labels->add_new_item; ?></label>
								<input type="text" name="newcategory" id="newcategory" class="form-required form-input-tip" value="<?php echo esc_attr( $tax->labels->new_item_name ); ?>" tabindex="3" aria-required="true"/>
								<label class="screen-reader-text" for="newcategory_parent">
									<?php echo $tax->labels->parent_item_colon; ?>
								</label>
								<?php wp_dropdown_categories( array( 'taxonomy' => 'category', 'hide_empty' => 0, 'name' => 'newcategory_parent', 'orderby' => 'name', 'hierarchical' => 1, 'show_option_none' => '&mdash; ' . $tax->labels->parent_item . ' &mdash;', 'tab_index' => 3 ) ); ?>
								<input type="button" id="category-add-submit" class="add:categorychecklist:category-add button category-add-submit" value="<?php echo esc_attr( $tax->labels->add_new_item ); ?>" tabindex="3" />
								<?php wp_nonce_field( 'add-category', '_ajax_nonce-add-category', false ); ?>
								<span id="category-ajax-response"></span>
							</p>
						</div>
					<?php endif; ?>
				</div>
				</div>
			</div>

			<div id="tagsdiv-post_tag" class="postbox">
				<div class="handlediv" title="<?php esc_attr_e( 'Click to toggle' ); ?>"><br /></div>
				<h3><span><?php _e('Tags'); ?></span></h3>
				<div class="inside">
					<div class="tagsdiv" id="post_tag">
						<div class="jaxtag">
							<label class="screen-reader-text" for="newtag"><?php _e('Tags'); ?></label>
							<input type="hidden" name="tax_input[post_tag]" class="the-tags" id="tax-input[post_tag]" value="" />
							<div class="ajaxtag">
								<input type="text" name="newtag[post_tag]" class="newtag form-input-tip" size="16" autocomplete="off" value="" />
								<input type="button" class="button tagadd" value="<?php esc_attr_e('Add'); ?>" tabindex="3" />
							</div>
						</div>
						<div class="tagchecklist"></div>
					</div>
					<p class="tagcloud-link"><a href="#titlediv" class="tagcloud-link" id="link-post_tag"><?php _e('Choose from the most used tags'); ?></a></p>
				</div>
			</div>
		</div>
	</div>
	<div class="posting">

		<div id="wphead">
			<img id="header-logo" src="<?php echo esc_url( includes_url( 'images/blank.gif' ) ); ?>" alt="" width="16" height="16" />
			<h1 id="site-heading">
				<a href="<?php echo get_option('home'); ?>/" target="_blank">
					<span id="site-title"><?php bloginfo('name'); ?></span>
				</a>
			</h1>
		</div>

		<?php
		if ( isset($posted) && intval($posted) ) {
			$post_ID = intval($posted); ?>
			<div id="message" class="updated">
			<p><strong><?php _e('Your post has been saved.'); ?></strong>
			<a onclick="window.opener.location.replace(this.href); window.close();" href="<?php echo get_permalink($post_ID); ?>"><?php _e('View post'); ?></a>
			| <a href="<?php echo get_edit_post_link( $post_ID ); ?>" onclick="window.opener.location.replace(this.href); window.close();"><?php _e('Edit Post'); ?></a>
			| <a href="javascript:;" onclick="window.close();"><?php _e('Close Window'); ?></a></p>
			</div>
		<?php } ?>

		<div id="titlediv">
			<div class="titlewrap">
				<input name="title" id="title" class="text" value="<?php echo esc_attr($title);?>"/>
			</div>
		</div>

		<div id="waiting" style="display: none"><img src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" height="16" width="16" alt="" /> <?php _e( 'Loading&hellip;' ); ?></div>

		<div id="extra-fields" style="display: none"></div>

		<div class="postdivrich">
		<?php

			$editor_settings = array(
				'teeny' => true,
				'textarea_rows' => '15'
			);

			$content = empty( $post ) ? '' : $post->post_content;

			if ( $selection )
				$content .= $selection;

			if ( $url ) {
				$content .= '<p>';

				if ( $selection )
					$content .= __('via ');

				$content .= sprintf( "<a href='%s'>%s</a>.</p>", esc_url( $url ), esc_html( $title ) );
			}

			remove_action( 'media_buttons', 'media_buttons' );
			add_action( 'media_buttons', 'press_this_media_buttons' );

			wp_editor( $content, 'content', $editor_settings );

		?>
		</div><!-- /postdivrich -->
	</div><!-- /posting -->
</div><!-- /poststuff -->
</form>
<?php
do_action('admin_footer');
do_action('admin_print_footer_scripts');
?>
<script>if(typeof wpOnload=='function')wpOnload();</script>
</body>
</html>
