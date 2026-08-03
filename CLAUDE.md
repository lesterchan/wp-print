# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

WP-Print follows `_standards/STANDARDS.md` in the parent folder, which is the
contract for all nineteen plugins in the collection. Where this file and that
one disagree, that one wins.

## What it is

A `/print/` endpoint that renders any post, page or comment thread as a
standalone printable document, plus the link that gets a reader there. Two
templates a theme may override, a `[print_link]` shortcode, a `[donotprint]`
shortcode, and a settings screen under Settings with **Settings** and
**Templates** tabs.

## Data

`wp_print_options` (settings) and `wp_print_version` (markers, replacing
`print_db_version`). The migration folds in the released 2.58.3's `print_options`
— user-facing.

**Re-saving permalinks is required after upgrading.** The `/print/` endpoint is
unchanged but the rewrite rules are only written when that screen is saved.

## Traps

* **`includes/print-posts.php` and `includes/print-comments.php` keep those exact
  filenames because a theme overrides them by copying them.** §1 carves out this
  exception explicitly: renaming either to `class-*.php` or `screen-*.php` would
  break every theme that has ever overridden one.
* **`QUERY_VAR = 'print'` is deliberately unprefixed and must stay that way.**
  It is a public query var from the last SVN release, so the value is part of
  what shipped: prefixing it to `wp_print` would break every existing `?print=1`
  link and every theme that builds one. The *constant* carries the plugin
  prefix; the *value* it holds does not, and that asymmetry is the point. §2.3
  prefixes constant names, not the strings inside them.
* **Those templates deliberately do not call `wp_head()` or `wp_footer()`.** A
  print view that pulled in the theme's stylesheets and every other plugin's
  assets would not print cleanly. That is why the head prints assets by handle
  with `wp_print_styles( array( 'wp-print' ) )` / `wp_print_scripts()`, and why
  the script is a plain tag. Do not "fix" this.
* **Activation migrates *before* seeding defaults, and the order is a fixed
  bug.** `WP_Print::activate_site()` used to `add_option()` the defaults first;
  the migration lets an existing value win, so a site upgrading from 2.58.3 had
  its `print_options` row read, deleted and thrown away — the one case activation
  exists to handle. Pinned by `test_activation_runs_the_migration`. This was one
  of the five behavioural bugs the first PHPUnit sweep found.
* **`maybe_upgrade()` reads the legacy link settings before anything writes.**
  `print_style`, `post_text` and `page_text` are retired, and *every* write drops
  a retired key — including the sanitize callback, which `register_setting()`
  hangs on `sanitize_option_wp_print_options` and which therefore fires on every
  `update_option()` the migration makes during an admin request. Read first, or
  the template is synthesised from a row the values have already been removed
  from (commit `49861b9`).
* **Every migration write goes through `WP_Print_Options::write()`, never
  `update_option()` directly.** `update_option()` declines to write a value equal
  to the one `get_option()` would return, and `register_setting()` is passed a
  `default`, which answers `get_option()` with the shipped defaults for a row
  that does not exist. So the one install shape whose migration *result* is the
  defaults — never touched a link setting in twenty years, which is the commonest
  one — wrote no row at all, while the legacy row was deleted and the markers
  stamped complete. `write()` tells an absent row from a defaulted one by passing
  an explicit default to `get_option()` and `add_option()`s it.

  `test_the_migration_survives_its_own_sanitize_callback` covers the same admin
  path and passed throughout, because its fixture is *customised* and so differs
  from the defaults. **A fixture that differs from the defaults cannot see a
  defect that only shows when it does not.**
  `test_the_shipped_settings_survive_the_admin_path` is the one that fails, and
  it reads the raw row — through the registered default a row that was never
  written is indistinguishable from one holding the defaults.
* **Migration is gated on the stored markers, never on whether the old shape is
  still detectable.** Gating on detection re-migrates on every request.
* **A retired placeholder is left in the template, not blanked.** A template
  still carrying `%PRINT_TEXT%` renders it visibly on the page, so an install
  that needs editing says so rather than silently losing its words. Same
  reasoning keeps `print_link()`'s first two arguments in the signature, ignored.
* **`WP_Print_Content::allowed_html( 'password-form' )` is the only context that
  adds tags, and widening it globally would be a bug.** `post_content()` answers
  a locked post with `get_the_password_form()`, and
  `wp_kses_allowed_html( 'post' )` has never allowed `form` or `input` — so the
  prompt and the Password label printed and the field they point at was stripped,
  leaving the reader told to type a password into nothing. `print_content()`
  picks the context from `post_password_required()`, the same question
  `post_content()` asked, so the widened list only ever sees markup core built
  and never a stored post body. `test_a_form_in_a_post_body_is_not_printed_as_a_form`
  is what stops somebody widening it for convenience later. The context reaches
  the `wp_print_allowed_html` filter too.
* **A locked post is guarded twice, and both are wanted.**
  `includes/print-comments.php` opens with `post_password_required()` and
  `WP_Print_Template::render()` also hangs `hide_protected_comments()` on
  `comments_array`. Core's `comments_template()` makes no password check — the
  convention is that the theme's comments template makes it — and this plugin
  replaces the theme's, so for the plugin's whole life a locked post withheld its
  body, said "Comments Hidden" over the count, and printed the whole thread
  underneath. The guard belongs in the template because that is the copy a theme
  takes; the filter exists because a theme's copy taken before the guard existed
  would leak forever otherwise. The same reasoning applies to any future fix in
  either overridable file: ask whether a stale copy can still do the damage.
* **The link template meets kses inside `WP_Print_Link::render()`, not at the
  call sites.** It used to leave that to the caller the way `get_the_title()`
  leaves it to `the_title()`: `print_link()` filtered on the way out and the
  `[print_link]` shortcode returned `render()` straight to the shortcode engine,
  which puts it in `the_content` untouched. One stored template was therefore
  inert through the tag a theme calls and live through the shortcode the readme
  documents — and the shortcode is the route every documented install uses. The
  glyph, the URL and the post-type label are substituted first and filtered with
  everything else, so the returned markup is escaped for every caller including
  a theme that takes `print_link( '', '', false )` and echoes it.
  `test_a_hostile_template_is_inert_through_the_tag_and_the_shortcode` pins both
  routes to the same string; wp-useronline's `format_count()` had the identical
  shape and carries the same fix.
* **`WP_Print_Link::allowed_html()` is a closed list, not `wp_kses_post()`**,
  because the post list has never allowed `svg` and would strip the printer glyph
  the template substitutes in. `WP_Print_Content::allowed_html()` is the kses
  post list *plus* the four embed tags (the Print Videos option exists to keep
  them) *plus* the inline SVG.
* **`$GLOBALS['links_text']` stays a global.** It has been the accumulator a
  print template reads since the plugin's first release. The footnote numbering
  in `WP_Print_Content` is shared between the post body and its comments, and a
  repeated URL reuses its first number.
* **The `WP-PrintIcon` class survives the GIF→SVG change** — themes have styled
  it by that name for twenty years.
* **`print-css-rtl.css` is gone, and deleting it was a privacy fix as well as
  §5.1 compliance.** The mirrored sheet pulled a webfont from
  `fonts.googleapis.com` on every print view, so every reader printing a page
  announced themselves to a third party.
* **A theme's copied `print-posts.php` needs `<body class="wp-print">`.** Every
  rule in the new stylesheet is scoped to that class.
* `$print_language` splits on the dash **only when there is one**:
  `get_bloginfo( 'language' )` returns `ca` as well as `en-US`, and
  `substr( $s, 0, false )` is `''`, which emitted `lang=""`.
* **`unfiltered_html` is super-admins-only under multisite** (§7.2.2). Tests that
  depend on it must grant it rather than the gate being weakened (commit
  `95fe5ee`).

## Tests

wp-print produced 19 of the first sweep's errors from a single cause —
`rtrim(): Passing null`, reached from `includes/print-posts.php:118` through
core's `comments_template()`, because `WP_UnitTestCase_Base::tear_down()` nulls
`$wp_stylesheet_path`. The fix is `wp_set_template_globals()` in `set_up()`, not
a change to the plugin. §7.2.1 has the full account; wp-commentnavi shares it.

**`tests/test-metadata.php::test_the_plugin_root_holds_no_loose_files` is the
plugin-root rule** and only wp-print carries it. It must exempt `*.config.js` —
`playwright.config.js` has to live in the root because Playwright resolves every
path relative to itself. §7.2.1 records this as a class of problem: a metadata
rule written before e2e existed will fire on e2e scaffolding; widen the rule
rather than moving the file.

`tests/e2e/` (5 specs, 52 tests) is among the twelve suites
`_standards/RESUME.md` lists as never run to green.

**`printview.spec.js`'s password test quotes core's wording, and core has since
changed it.** WordPress 7.0's `get_the_password_form()` reads *"This content is
password-protected. To view it, please enter the password below."* — hyphenated,
and not the sentence either the spec's comment or the sweep entry quotes. So
`toContainText( 'password protected' )` cannot match, and that assertion sits
directly after the comment-leak one that was the real bug. Both plugin bugs that
test covers are fixed; if it is still red, read the failing line before
concluding anything about the plugin. `.wp-env.json` pins `core: null`, which is
whatever WordPress is current, so the wording is not stable and asserting on it
was always going to age.

## Pending, not started

`_standards/RESUME.md` task #17 renames the settings page and its identically
named "Print Options" section; task #18 is the wp-print/wp-email link-settings
collapse, most of which has already landed here (commit `49861b9`) — check
before redoing it.
