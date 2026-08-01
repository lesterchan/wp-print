# WP-Print
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: print, printer, printable, print button, print link  
Requires at least: 6.8  
Tested up to: 7.0  
Stable tag: 3.0.0  
Requires PHP: 8.2  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Displays a printable version of your WordPress blog's post/page.

## Description

WP-Print adds a print link to your posts and pages. Following it gives the reader a clean printable document: your content, its comments if you want them, and nothing else — no theme, no sidebar, no navigation.

Every link in the printed article is numbered and its URL listed at the end, because a reader holding a sheet of paper cannot click anything.

Once installed take the following steps to set it up:

1. The settings screen is at WP-Admin -> Settings -> Print
1. Re-save your permalinks at WP-Admin -> Settings -> Permalinks, so the /print/ endpoint is registered
1. Refer to Usage below for the template tag

### Features

* A printable version of any post, page or custom post type, at `/your-post/print/`
* Every link footnoted with a number and listed at the end of the document
* Optional comments, images, embedded video and featured image
* `[donotprint]` for content that should never reach the printed page
* One stylesheet in both text directions, with a dark mode for reading before printing
* Templates and styles you can override from your theme

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Usage

Add the print link to your theme inside the loop, wherever you want it to appear:

```php
if ( function_exists( 'wp_print' ) ) {
	print_link();
}
```

`print_link( $post_text, $page_text, $display )` takes the link text for a post, the link text for a page, and whether to print or return. All three are optional; the two labels default to what you set on the settings screen.

If you would rather not have the link on every post, leave your theme alone and type `[print_link]` into the one post or page that needs it.

### Shortcodes

* `[print_link]` — the print link, in the post or page it is typed into.
* `[donotprint]Not for paper[/donotprint]` — the enclosed content is dropped from the printable version and left alone everywhere else. WP-Email honours the same tag, so text you exclude from print is excluded from email too.

### Template Tags

* `print_link( $post_text, $page_text, $display )` — the print link.
* `print_content( $display )` — the post content, prepared for printing.
* `print_comments_content( $display )` — the current comment's content.
* `print_categories( $before, $after )` — the categories, each wrapped in your markup.
* `print_comments_number()` — the comment count as a sentence.
* `print_links( $heading )` — the collected list of URLs.
* `print_can( $type )` — whether one of the options is on.
* `print_disclaimer()` — the disclaimer or copyright line.

### Custom Template

WP-Print loads `print-posts.php`, `print-comments.php` and `print-css.css` from your theme's directory if they exist, child theme first, then parent, then the copies bundled with the plugin. Copy one into your theme to restyle the printable page without losing the change on upgrade.

The bundled copies moved in 3.0.0 — the templates are in `includes/` and the stylesheet is `css/wp-print.css` — but the names your theme overrides them with are unchanged.

### Filters

* `wp_print_capability` — the capability the settings screen requires. Defaults to `manage_options`.
* `wp_print_allowed_html` — the tags the printed document may carry, in `wp_kses()` form. Add to it if a block on your site prints markup the default list does not cover.

## Frequently Asked Questions

### How do I add the print link to my theme?

Open `wp-content/themes/<YOUR THEME NAME>/index.php`, and single.php, page.php and any other template that runs the loop. Find `<?php while ( have_posts() ) : the_post(); ?>` and add this anywhere below it:

```php
if ( function_exists( 'wp_print' ) ) {
	print_link();
}
```

### The print link 404s

Re-save your permalinks at WP-Admin -> Settings -> Permalinks. WP-Print adds a `/print/` endpoint, and WordPress only writes the rewrite rules out when that screen is saved.

### How do I keep part of a post off the printed page?

Type this into the post:

```php
[donotprint]Text within this tag will not be displayed when printing[/donotprint]
```

The enclosed text is dropped from the printable version and shown as normal everywhere else.

### Can I change how the printed page looks?

Yes, two ways. Copy `print-css.css` into your theme to replace the stylesheet outright, or set any of the custom properties the bundled sheet uses — `--wp-print-font-family`, `--wp-print-font-size`, `--wp-print-line-height`, `--wp-print-text`, `--wp-print-background`, `--wp-print-rule` and `--wp-print-gap` — to retune it without forking the file.

### Is the print icon still an image I can choose?

No. The two bundled GIFs are one inline SVG that takes its colour from your theme and stays sharp at any size, so there is nothing left to choose between and the Print Icon setting is gone.

## Screenshots

1. Admin Print Options
2. Print Post Link
3. Print Page

## Changelog

### 3.0.0
* BREAKING: Requires WordPress 6.8 and PHP 8.2, up from 6.0 and 7.4.
* BREAKING: The settings move from the `print_options` row to `wp_print_options`, and `print_db_version` is replaced by `wp_print_version`. Existing settings are migrated automatically on the first admin page load after upgrading.
* BREAKING: The `Print_Admin`, `Print_Content`, `Print_Core`, `Print_Link`, `Print_Options` and `Print_Template` classes are renamed `WP_Print_Admin`, `WP_Print_Content`, `WP_Print`, `WP_Print_Link`, `WP_Print_Options` and `WP_Print_Template`.
* BREAKING: The Print Icon setting and the two bundled GIFs are gone, replaced by one inline SVG. A custom link template using `%PRINT_ICON_URL%` is rewritten to `%PRINT_ICON%` automatically.
* BREAKING: `print-css-rtl.css` is deleted. The one stylesheet now serves both text directions.
* BREAKING: A theme's copy of `print-posts.php` needs `class="wp-print"` on its `<body>` tag to pick up the new stylesheet.
* NEW: The print icon is an inline SVG that inherits your theme's link colour and stays sharp on any screen.
* NEW: The printable page has a dark mode for reading it before printing, and prints as ink on white either way.
* NEW: The stylesheet is tunable through CSS custom properties rather than by forking it.
* NEW: `wp_print_capability` filters the capability the settings screen requires; `wp_print_allowed_html` filters the tags the printed document may carry.
* NEW: Rewritten as classes under `includes/`, following the Plugin Handbook's folder structure.
* NEW: The options screen now uses the WordPress Settings API. Its address changes from `options-general.php?page=wp-print/print-options.php` to `options-general.php?page=wp-print` — the Settings -> Print menu item is unaffected, and there is now a Settings link on the Plugins screen. Update any bookmark.
* NEW: No more jQuery. The settings screen and the print view use plain JavaScript, and every inline `onclick` attribute is gone.
* NEW: Print options are stored unslashed. An existing install is migrated once, automatically, on the first admin page load after upgrading.
* CHANGED: The printed document is filtered through `wp_kses()` on its way out, keeping every tag a post body plausibly contains plus the ones embeds arrive in. `wp_print_allowed_html` covers anything it misses.
* CHANGED: The print view no longer fetches a webfont from fonts.googleapis.com when the site is right-to-left.
* CHANGED: The third argument to `print_link()` is named `$display`, matching the other template tags. Only a caller passing it by name is affected.
* FIXED: Numbered and bulleted lists in a post kept their indentation but lost their markers on the printed page.
* FIXED: Print options set before a key existed no longer produce "Undefined array key" warnings or a print link with no text and no icon.
* FIXED: The first link in a post was left unnumbered on themes that turn `wpautop` off.
* FIXED: The category list separator lost its space, and `print_categories( $before, $after )` wrapped the whole list instead of each category.
* FIXED: The print view emitted `lang=""` for locales without a region, such as `ca`.
* FIXED: Right-to-left detection in the comments template never triggered, and the print view raised a deprecation notice on every load.
* FIXED: Link URLs taken from post content are escaped when written back into the printable page.
* FIXED: An icon-only print link had no accessible name; it now carries one.
* FIXED: Uninstalling on a multisite network larger than 100 sites left the options behind on every site past the hundredth, and would fail outright on WordPress 5.1 or newer.
* NOTE: The `remove_image()`, `remove_video()` and `str_replace_one()` global functions are gone. They were undocumented and used names other plugins could collide with.
* NOTE: Support for the Polyglot plugin is removed. It has not been available for over a decade.
* NOTE: Every documented template tag — `print_link()`, `print_content()`, `print_categories()`, `print_comments_content()`, `print_comments_number()`, `print_links()`, `print_can()` — keeps its name, arguments and behaviour, and `[print_link]` and `[donotprint]` are unchanged. So is the `/print/` URL of every printable page.

## Upgrade Notice

### 3.0.0

Requires WordPress 6.8 and PHP 8.2.

**Re-save your permalinks after updating**, at `WP-Admin -> Settings -> Permalinks -> Save Changes`. The `/print/` endpoint is unchanged, but the rewrite rules are only written out when that screen is saved.

**Settings migrate on the first admin page load.** `print_options` becomes `wp_print_options` and `print_db_version` becomes `wp_print_version`. Deleting the plugin removes all four rows.

**The print icon is no longer a choice.** The two bundled GIFs are one inline SVG that takes its colour from your theme, so the Print Icon setting is gone. A custom link template built around `%PRINT_ICON_URL%` is rewritten to `%PRINT_ICON%`, which inserts the glyph itself rather than a URL to an image.

**A right-to-left site stops calling out to Google.** The mirrored stylesheet pulled a webfont from `fonts.googleapis.com` on every print view, so every reader printing a page announced themselves to a third party. The sheet is gone, and the printable page uses the fonts already on the reader's device.

**If you copied `print-posts.php` into your theme, change its `<body>` tag to `<body class="wp-print">`.** Every rule in the new stylesheet is scoped to that class, so without it your printable page loads a stylesheet that matches nothing. Also: `print-css-rtl.css` no longer exists, the plugin's own templates moved to `includes/` and its stylesheet to `css/wp-print.css`, and the `<link>` and `<script>` tags in the head are printed by `wp_print_styles( array( 'wp-print' ) )` and `wp_print_scripts( array( 'wp-print' ) )`. Your copy still takes precedence; compare it against the new one to pick up this release's fixes.

**Classes are renamed.** `Print_Admin`, `Print_Content`, `Print_Core`, `Print_Link`, `Print_Options` and `Print_Template` become `WP_Print_Admin`, `WP_Print_Content`, `WP_Print`, `WP_Print_Link`, `WP_Print_Options` and `WP_Print_Template`. `Print_` was far too common a prefix to leave unclaimed in the global namespace.

Every documented template tag keeps its name, arguments and behaviour, `[print_link]` and `[donotprint]` are unchanged, and so is the `/print/` URL of every printable page. The one exception is the third argument to `print_link()`, renamed from `$echo` to `$display`, which matters only if you were passing it by name.
