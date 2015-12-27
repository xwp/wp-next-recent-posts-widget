<?php
/**
 * Instantiates the Next Recent Posts Widget plugin
 *
 * @package NextRecentPostsWidget
 */

namespace NextRecentPostsWidget;

global $next_recent_posts_widget_plugin;

require_once __DIR__ . '/php/class-plugin-base.php';
require_once __DIR__ . '/php/class-plugin.php';

$next_recent_posts_widget_plugin = new Plugin();

/**
 * Next Recent Posts Widget Plugin Instance
 *
 * @return Plugin
 */
function get_plugin_instance() {
	global $next_recent_posts_widget_plugin;
	return $next_recent_posts_widget_plugin;
}
