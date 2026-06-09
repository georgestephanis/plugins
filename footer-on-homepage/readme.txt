=== Footer On Homepage ===
Contributors: georgestephanis
Donate link: http://www.charitywater.org/donate/
Tags: SEO, Copy on Homepage, TodaysGrowthConsultant
Requires at least: 2.7
Tested up to: 3.3
Stable tag: 1.0.1

Footer On Homepage lets you add some copy to your homepage footer, visible with a single click.

== Description ==

This (relatively simple) plugin gives site owners an opportunity to stuff some SEO-friendly text into the homepage (and only the homepage) of their site. It will initially display only as a link, but when the visitor clicks the link, the full specified text displays right there in the footer for the user to see.

It is also a great example of how to use wp_editor() [new in 3.3] and still be backwards-compatible.

The admin side, as of v1.0.1, has a text box for you to specify your own CSS stylings of the footer div.  So go ahead and make it look however you like!

== Installation ==

1. Install via the WordPress Plugins Repository
1. Add your copy via Appearance > Homepage Footer
1. (optional) Add styling to the #footer_on_homepage and #footer_on_homepage_wrap elements in the admin page.

== Changelog ==

= 1.0.1 =
* Fixed glitch where I used wp_header action rather than wp_head.
* Added box for custom CSS styling.

= 1.0 =
* Not Applicable, initial public release.

