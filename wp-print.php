<?php
/**
 * Plugin Name: WP-Print
 * Plugin URI: https://lesterchan.net/portfolio/programming/php/
 * Description: Displays a printable version of your WordPress blog's post/page.
 * Version: 3.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Lester 'GaMerZ' Chan
 * Author URI: https://lesterchan.net
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-print
 * Domain Path: /languages
 *
 * @package WP-Print
 */

/*
	Copyright 2026  Lester Chan  (email : lesterchan@gmail.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

// Plugin version.
define( 'WP_PRINT_VERSION', '3.0.0' );

// Stored option schema version. Bump when a migration is needed.
define( 'WP_PRINT_DB_VERSION', '1' );

// Main plugin file, for resolving paths and URLs from the includes.
define( 'WP_PRINT_MAIN_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-print-options.php';
require_once __DIR__ . '/includes/class-print-content.php';
require_once __DIR__ . '/includes/class-print-template.php';
require_once __DIR__ . '/includes/class-print-link.php';
require_once __DIR__ . '/includes/class-print-core.php';
require_once __DIR__ . '/includes/template-tags.php';

if ( is_admin() ) {
	require_once __DIR__ . '/includes/class-print-admin.php';
}

Print_Core::get_instance();
