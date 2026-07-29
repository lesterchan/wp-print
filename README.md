# WP-Print
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: print, printer, wp-print  
Requires at least: 6.8  
Tested up to: 7.0  
Stable tag: 3.0.0  
Requires PHP: 8.2  
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Displays a printable version of your WordPress blog's post/page.

## Description

Once installed take the following steps to set it up:

1. WP-Print settings page is located in WP-Admin -> Settings -> Print
1. You Need To Re-Generate The Permalink (WP-Admin -> Settings -> Permalinks -> Save Changes)
1. Refer To Usage For Further Instructions

### Usage

1. Open `wp-content/themes/<YOUR THEME NAME>/index.php`. You should place it in single.php, post.php, page.php, etc also if they exist.
1. Find: `<?php while (have_posts()) : the_post(); ?>`
1. Add Anywhere Below It: `<?php if(function_exists('wp_print')) { print_link(); } ?>`

* The first value is the text for printing post.
* The second value is the text for printing page.
* Default: print_link('', '')
* Alternatively, you can set the text in 'WP-Admin -> Settings -> Print'.
* If you DO NOT want the print link to appear in every post/page, DO NOT use the code above. Just type in <strong>[print_link]</strong> into the selected post/page content and it will embed the print link into that post/page only.

### Development
[https://github.com/lesterchan/wp-print](https://github.com/lesterchan/wp-print "https://github.com/lesterchan/wp-print")

### Credits
* Plugin icon by [SimpleIcon](https://www.simpleicon.com) from [Flaticon](https://www.flaticon.com)

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks as my school allowance, I will really appreciate it. If not feel free to use it without any obligations.

## Screenshots

1. Admin Print Options
2. Print Post Link
3. Print Page

## Frequently Asked Questions

### How do I add this to my theme?

1. Open `wp-content/themes/<YOUR THEME NAME>/index.php`
      You may place it in single.php, post.php, page.php, etc also.
1. Find: `<?php while (have_posts()) : the_post(); ?>`
1. Add Anywhere Below It: `<?php if(function_exists('wp_print')) { print_link(); } ?>`

Simply add this code inside the loop ### where you want the print link to display:
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
* WP-Print will load 'print-css.css', 'print-posts.php' and 'print-comments.php' from your theme's directory if it exists.
* If it doesn't exists, it will just load the respective default file that comes with WP-Print.
* This will allow you to upgrade WP-Print without worrying about overwriting your printing styles or templates that you have created.

## Changelog
### 3.0.0
* NEW: Requires WordPress 6.0 and PHP 7.4 or newer.
* NEW: Rewritten as classes under `includes/`, following the Plugin Handbook's folder structure.
* NEW: The options screen now uses the WordPress Settings API. Its address changes from `options-general.php?page=wp-print/print-options.php` to `options-general.php?page=wp-print` — the Settings -> Print menu item is unaffected, and there is now a Settings link on the Plugins screen. Update any bookmark.
* NEW: No more jQuery. The settings screen and the print view use plain JavaScript, and every inline `onclick` attribute is gone.
* NEW: Print options are stored unslashed. An existing install is migrated once, automatically, on the first admin page load after upgrading.
* FIXED: Print options set before a key existed no longer produce "Undefined array key" warnings or a print link with no text and no icon.
* FIXED: The first link in a post was left unnumbered on themes that turn `wpautop` off.
* FIXED: The category list separator lost its space, and `print_categories( $before, $after )` wrapped the whole list instead of each category.
* FIXED: The print view emitted `lang=""` for locales without a region, such as `ca`.
* FIXED: Right-to-left detection in the comments template never triggered, and the print view raised a deprecation notice on every load.
* FIXED: Link URLs taken from post content are escaped when written back into the printable page.
* FIXED: Uninstalling on a multisite network larger than 100 sites left the options behind on every site past the hundredth, and would fail outright on WordPress 5.1 or newer.
* REMOVED: The `remove_image()`, `remove_video()` and `str_replace_one()` global functions, which were undocumented and used names other plugins could collide with.
* REMOVED: Support for the Polyglot plugin, which has not been available for over a decade.
* NOTE: Every documented template tag — `print_link()`, `print_content()`, `print_categories()`, `print_comments_content()`, `print_comments_number()`, `print_links()`, `print_can()` — keeps its name, arguments and behaviour, and `[print_link]` and `[donotprint]` are unchanged.
* NOTE: If you have copied `print-posts.php` or `print-comments.php` into your theme, your copy still works and still takes precedence. It will not pick up this release's template fixes until you merge them in; compare against the new files in the plugin folder.

### 2.58.3
* NEW: Bump to WordPress 7.0
* FIXED: Fixed XSS audit by Claude

### 2.58.2
* NEW: Use strong instead of b for bold
* FIXED: Improve RTL

### 2.58.1
* FIXED: Strip iframe tags as well.

### 2.58
* NEW: Ability to print thumbnail. Props @MatthieuMota.

### 2.57.2
* FIXED: Check both parent and child theme

### 2.57.1
* NEW: Use translate.wordpress.org to translate the plugin
* FIXED: Unable to update options

### 2.57
* FIXED: Notices

### 2.56
* NEW: Updated print HTML code. Props @Luanramos

### 2.55
* NEW: Bump to 4.1
* FIXED: get_the_category_list() optional secondary argument
* FIXED: Replace font with p

### 2.54
* NEW: Finally there is custom post type support. Props [nimmolo](https://andrewnimmo.org/ "nimmolo").
* NEW: Allow Multisite Network Activate
* NEW: Uses WordPress uninstall.php file to uninstall the plugin

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
