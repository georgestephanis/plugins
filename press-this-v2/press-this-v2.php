<?php
/*
Plugin Name: Press This v2
Plugin URI: http://wordpress.org/extend/plugins/press-this-v2/
Description: This is a rewrite of the Press This functionality from core.
Author: George Stephanis
Version: 0.1
Author URI: https://georgestephanis.wordpress.com
*/

add_filter( 'shortcut_link', 'v2_shortcut_link' );
function v2_shortcut_link( $orig ){
	$link = "javascript:
			var d=document,
				w=window,
				c=w.getSelection,
				k=d.getSelection,
				x=d.selection,
				s=(c?c():(k)?k():(x?x.createRange().text:0)),
				l=d.location,
				e=encodeURIComponent,
				metas=d.head.getElementsByTagName('meta'),
				imgs=d.body.getElementsByTagName('img'),
				r=new Image(),
				f=d.createElement('form'),
				fAdd=function(n,v){
					if(typeof(v)==='undefined')return;
					g=d.createElement('input');
					g.name=n;
					g.value=v;
					g.type='hidden';
					f.appendChild(g);
				};
			
			for(m in metas){
				q=metas[m];
				if(q.name){
					fAdd('_meta['+q.name+']',q.content);
				}else if(q.property){
					fAdd('_meta['+q.property+']',q.content);
				}
			}
			
			for(i in imgs){
				r.src=imgs[i].src;
				if(r.width>=50||r.height>=50){
					fAdd('_img[]',r.src);
				}
			}
			
			fAdd('_u',l.href);
			fAdd('_t',d.title);
			fAdd('_s',s);
			
			f.method='POST';
			f.action='" . plugins_url( 'press-this.php', __FILE__ ) . "?u='+e(l.href)+'&t='+e(d.title)+'&s='+e(s)+'&v=4';
			
			t=w.open('','t','toolbar=0,resizable=1,scrollbars=1,status=1,width=720,height=570');
			t.document.body.appendChild(f);
			t.document.forms[0].submit();
			void(0);";

	$link = str_replace( array( "\r", "\n", "\t" ), '', $link );
	
	return $link;
}
