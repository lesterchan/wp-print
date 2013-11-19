# WP-Print
Contributors: GamerZ, aaroncampbell  
Donate link: http://lesterchan.net/site/donation/  
Tags: print, printer, wp-print  
Requires at least: 2.8  
Tested up to: 3.7  
Stable tag: trunk  

Displays a printable version of your WordPress blog's post/page.

## Description

Displays a printable version of your WordPress blog's post/page.

### Previous Versions
* [WP-Print 2.50 For WordPress 2.7.x And 2.8.x](http://downloads.wordpress.org/plugin/wp-print.2.50.zip "WP-Print 2.50 For WordPress 2.7.x And 2.8.x")
* [WP-Print 2.50 For WordPress 2.7.x And 2.8.x](http://downloads.wordpress.org/plugin/wp-print.2.50.zip "WP-Print 2.50 For WordPress 2.7.x And 2.8.x")
* [WP-Print 2.31 For WordPress 2.5.x And 2.6.x](http://downloads.wordpress.org/plugin/wp-print.2.31.zip "WP-Print 2.31 For WordPress 2.5.x And 2.6.x")
* [WP-Print 2.20 For WordPress 2.1.x, 2.2.x And 2.3.x](http://downloads.wordpress.org/plugin/wp-print.2.20.zip "WP-Print 2.20 For WordPress 2.1.x, 2.2.x And 2.3.x")
* [WP-Print 2.06 For WordPress 2.0.x](http://downloads.wordpress.org/plugin/wp-print.2.06.zip "WP-Print 2.06 For WordPress 2.0.x")
* [WP-Print 2.00 For WordPress 1.5.2](http://downloads.wordpress.org/plugin/wp-print.2.00.zip "WP-Print 2.00 For WordPress 1.5.2")

### Development
* [http://dev.wp-plugins.org/browser/wp-print/](http://dev.wp-plugins.org/browser/wp-print/ "http://dev.wp-plugins.org/browser/wp-print/")

### Translations
* [http://dev.wp-plugins.org/browser/wp-print/i18n/](http://dev.wp-plugins.org/browser/wp-print/i18n/ "http://dev.wp-plugins.org/browser/wp-print/i18n/")

### Support Forums
* [http://forums.lesterchan.net/index.php?board=18.0](http://forums.lesterchan.net/index.php?board=18.0 "http://forums.lesterchan.net/index.php?board=18.0")

### Credits
* Icons courtesy of [FamFamFam](http://www.famfamfam.com/)
* __ngetext() by [Anna Ozeritskaya](http://hweia.ru/)
* Right-to-left language support by [Kambiz R. Khojasteh](http://persian-programming.com/)
* Do Not Print idea by [Robert "Nilpo" Dunham](http://www.nilpo.com/)

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks as my school allowance, I will really appreciate it. If not feel free to use it without any obligations. Thank You. My Paypal account is lesterchan@gmail.com.

## Installation

You can either install it automatically from the WordPress admin, or do it manually:

1. Upload the whole `wp-print` directory into your plugins folder(`/wp-content/plugins/`)
1. Activate the plugin through the 'Plugins' menu in WordPress

Once installed take the following steps to set it up:

1. WP-Print settings page is located in WP-Admin -> Settings -> Print
1. You Need To Re-Generate The Permalink (WP-Admin -> Settings -> Permalinks -> Save Changes)
1. Refer To Usage For Further Instructions

### Usage

1. Open `wp-content/themes/<YOUR THEME NAME>/index.php`
      You may place it in single.php, post.php, page.php, etc also.
1. Find: `<?php while (have_posts()) : the_post(); ?>`
1. Add Anywhere Below It: `<?php if(function_exists('wp_print')) { print_link(); } ?>`

* The first value is the text for printing post.
* The second value is the text for printing page.
* Default: print_link('', '')
* Alternatively, you can set the text in 'WP-Admin -> Settings -> Print'.
* If you DO NOT want the print link to appear in every post/page, DO NOT use the code above. Just type in <strong>[print_link]</strong> into the selected post/page content and it will embed the print link into that post/page only.


## Screenshots

1. Admin Print Options
2. Print Post Link
3. Print Page link
4. Print Page

## Frequently Asked Questions

### How do I add this to my theme?

1. Open `wp-content/themes/<YOUR THEME NAME>/index.php`
      You may place it in single.php, post.php, page.php, etc also.
1. Find: `<?php while (have_posts()) : the_post(); ?>`
1. Add Anywhere Below It: `<?php if(function_exists('wp_print')) { print_link(); } ?>`

Simply add this code <strong>inside the loop ### where you want the print link to display:
<code>
if(function_exists('wp_print')) {
	print_link();
}
</code>

### If you do not want to print a portion of your post's content
<code>
[donotprint]Text within this tag will not be displayed when printing[/donotprint]
</code>
* The text within [donotprint][/donotprint] will not be displayed when you are viewing a printer friendly version of a post or page.
* However, it will still be displayed as normal on a normal post or page view.
* Do note that if you are using WP-Email, any text within [donotprint][/donotprint] will not be emailed as well.

### Custom Template
* WP-Print will load 'print-css.css', '<strong>print-posts.php' and 'print-comments.php' from your theme's directory if it exists.
* If it doesn't exists, it will just load the respective default file that comes with WP-Print.
* This will allow you to upgrade WP-Print without worrying about overwriting your printing styles or templates that you have created.


## Changelog

### 2.53
* FIXED: Use get_stylesheet_directory() instead of TEMPLATEPATH

### 2.52
* FIXED: Added nonce to Options. Credits to Charlie Eriksen via Secunia SVCRP.

### 2.51
* NEW: Support for links that start with "//"
* FIXED: Unable to load WP-Print on Password Protected posts

### 2.50
* NEW: Uses jQuery Framework
* NEW: [donotprint][/donotprint] ShortCode Will Not Be Displayed As Well When Using WP-Email (Refer To Usage Tab)
* NEW: Use _n() Instead Of __ngettext() And _n_noop() Instead Of __ngettext_noop()
* FIXED: Uses $_SERVER['PHP_SELF'] With plugin_basename(__FILE__) Instead Of Just $_SERVER['REQUEST_URI']
* FIXED: Nested ShortCode Issues

### 2.40
* NEW: Works For WordPress 2.7 Only
* NEW: Better Translation Using __ngetext() by Anna Ozeritskaya
* NEW: Right To Left Language Support by Kambiz R. Khojasteh
* NEW: Call print_textdomain() In print_init() by Kambiz R. Khojasteh
* NEW: Replace "text_direction" Option With $text_direction And language_attributes() by Kambiz R. Khojasteh
* NEW: Added "print-css-rtl.css" by Kambiz R. Khojasteh
* NEW: Page Title Is Now "Post Title -> Print" Instead Of "Print -> Post Title" by Kambiz R. Khojasteh
* NEW: Modified "print-css.css" To Hide "comments_controls" Element For Print  by Kambiz R. Khojasteh
* FIXED: Footnotes Referencing Is Now "link1 <sup>[1]</sup>" Instead Of "[1] link1" by Kambiz R. Khojasteh
* FIXED: Remove chunk_split() As Browser Will Try To Wrap Word Boundaries Based On Page Width by Kambiz R. Khojasteh
* FIXED: Remove [print_link] ShortCode In Print Pages

### 2.31
* NEW: Works For WordPress 2.6
* NEW: Added donotprint ShortCode. See Usage Tab
* NEW: WP-Print Will Load print-posts.php And print-comments.php Templates From Your Theme Directory First If The Exist
* FIXED: Replace &lt;center&gt; With &lt;div style="margin: 0px auto 0px auto;"&gt;

### 2.30
* NEW: Works For WordPress 2.5 Only
* NEW: WP-Print Will Load 'print-css.css' Inside Your Theme Directory If It Exists. If Not, It Will Just Load The Default 'print-css.css' By WP-Print
* NEW: Uses Shortcode API
* NEW: Added "Right To Left" And "Left To Right Text" Direction Option
* NEW: Option To Remove Videos From Post
* NEW: Duplicate Links Now Uses A Single Number And Printed Only Once By Constantinos Neophytou
* NEW: &lt;IMG&gt; And &lt;A&gt; Tag Now Matches Single Quotes By Constantinos Neophytou
* NEW: Uses /wp-print/ Folder Instead Of /print/
* NEW: Uses wp-print.php Instead Of print.php
* NEW: Changed wp-print.php To print-posts.php
* NEW: Changed wp-print-comments.php To print-comments.php
* NEW: Changed wp-print-css.css To print-css.css
* FIXED: Comment Type Not Translated

### 2.20
* NEW: Works For WordPress 2.3 Only
* NEW: Ability To Embed [print_link] Into Excerpt
* NEW: wp-print-css.css Now Controls The CSS Styles For The Printer Friendly Page
* NEW: Disclaimer/Copyright Text Option By Duane Craig
* NEW: Anchor Link To Comments By Reinventia
* NEW: Collapsable Comments By Reinventia
* NEW: Ability To Uninstall WP-Print
* FIXED: If There Is No Trailing Slash In Your Permalink, WP-Print Will Add It For You

### 2.11
* NEW: Putting [print_link] In Your Post/Page Content Will Display A Link To The Printable Post/Page
* FIXED: Worked With Polyglot Plugin, Fixed By zeridon
* FIXED: Wrong URL If Front Page Is A Static Page

### 2.10
* NEW: Added Fam Fam Fam's Printer Icon
* NEW: Works For WordPress 2.1 Only
* NEW: Localize WP-Print
* NEW: Ability To Configure The Text For Print Links Via 'WP-Admin -> Options -> Print'
* NEW: The Text For Print Links Can No Longer Be Pass To The Function print_link() or print_link_image()
* FIXED: MUltiple URL Type Fixed By Virgil
* FIXED: 'Click Here To Print' Will Be Hidden When Printing By Joe (Ttech)

### 2.06
* NEW: Used Default Date/Time Format Under WordPress Options
* NEW: Added robots: noindex To Printer Friendly Pages
* NEW: Added rel="nofollow" To All Links Generated By WP-Print
* FIXED: &lt;abbr&gt; Tag Mixed Up With &lt;a&gt;
* FIXED: PHP5 Compatibility Issue
* FIXED: Long URL Will Not Break Into More Than 1 Line

### 2.05
* NEW: Added Print Options In WP Administration Panel Under 'Options -> Print'
* NEW: Print Administration Panel And The Code That WP-Print Generated Is XHTML 1.0 Transitional
* FIXED: Comment's Content Formatting

### 2.04
* NEW: Able To Print Comments Together With Post Using $can_print_comments In wp-print.php
* NEW: Moved wp-print.php To Plugin Folder
* FIXED: Removed Link From Post Comment Count And Post Category

### 2.03
* NEW: Added Print Image With print_link_image()
* NEW: Automatically Break To Next Line If Link Contains More Than 100 Chars
* FIXED: Comment Numbers Showing In Password Protected Post

### 2.02
* FIXED: Able To View Password Protected Blog

### 2.01
* NEW: Compatible With WordPress 2.0
* NEW: Automatically Detect Whether You Are Using Nice Permalink
* NEW: Automated Permalink
* NEW: Now You Only Need To Insert 1 Line Into Your index.php Of Your Theme
* NEW: GPL License Added
* FIXED: Links Not Displaying Properly When Printing More Than 1 Post On A Single Page

### 2.00a
* NEW: Permlink For The Page Feature

### 2.00
* NEW: Print Out A Summary Of URLS In The Post At The Bottom Of The Page
