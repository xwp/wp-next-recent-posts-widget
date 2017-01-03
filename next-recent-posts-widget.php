<?php
/**
 * Plugin Name: Next Recent Posts Widget
 * Plugin URI: https://github.com/xwp/wp-next-recent-posts-widget
 * Description: Next-generation Recent Posts widget which fetches posts via the WP REST API, renders then into the widget via JavaScript templates, and provides a JS-driven Customizer control. It will continue fetching the any next new posts at regular intervals.
 * Version: 0.2.0
 * Author:  XWP
 * Author URI: https://xwp.co/
 * License: GPLv2+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: next-recent-posts-widget
 * Domain Path: /languages
 *
 * Copyright (c) 2015 XWP (https://xwp.co/)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2 or, at
 * your discretion, any later version, as published by the Free
 * Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * @package NextRecentPostsWidget
 */

if ( version_compare( phpversion(), '5.3', '>=' ) ) {
	require_once __DIR__ . '/instance.php';
} else {
	if ( defined( 'WP_CLI' ) ) {
		WP_CLI::warning( _next_recent_posts_widget_php_version_text() );
	} else {
		add_action( 'admin_notices', '_next_recent_posts_widget_php_version_error' );
	}
}

/**
 * Admin notice for incompatible versions of PHP.
 */
function _next_recent_posts_widget_php_version_error() {
	printf( '<div class="error"><p>%s</p></div>', esc_html( _next_recent_posts_widget_php_version_text() ) );
}

/**
 * String describing the minimum PHP version.
 *
 * @return string
 */
function _next_recent_posts_widget_php_version_text() {
	return __( 'Next Recent Posts Widget plugin error: Your version of PHP is too old to run this plugin. You must be running PHP 5.3 or higher.', 'next-recent-posts-widget' );
}
