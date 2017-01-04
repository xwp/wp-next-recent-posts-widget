=== Next Recent Posts Widget ===
Contributors: westonruter, xwp
Requires at least: 4.7.0
Tested up to: 4.7.0
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Next-generation Recent Posts widget which fetches posts via the WP REST API and dynamically renders data with JavaScript templates.

== Description ==

Next-generation Recent Posts widget which fetches posts via the WP REST API, renders then into the widget via JavaScript templates, and provides a JS-driven customizer control via [JS Widgets](https://github.com/xwp/wp-js-widgets).

This plugin was developed to showcase the [Customize REST Resources](https://github.com/xwp/wp-customize-rest-resources) plugin.
Also try the widget with the [Customize Posts](https://github.com/xwp/wp-customize-posts) plugin installed (v0.8.5+) as you'll be able to edit posts in the customizer and see their changes apply live in the widget. Selective refresh is used not to re-render the widget but rather to fetch the `rendered` fields on the REST API resources used in the widget.
