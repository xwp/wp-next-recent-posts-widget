<?php
/**
 * Test_Next_Recent_Posts_Widget
 *
 * @package NextRecentPostsWidget
 */

namespace NextRecentPostsWidget;

/**
 * Class Test_Next_Recent_Posts_Widget
 *
 * @package NextRecentPostsWidget
 */
class Test_Next_Recent_Posts_Widget extends \WP_UnitTestCase {

	/**
	 * Test _next_recent_posts_widget_php_version_error().
	 *
	 * @see _next_recent_posts_widget_php_version_error()
	 */
	public function test_next_recent_posts_widget_php_version_error() {
		ob_start();
		_next_recent_posts_widget_php_version_error();
		$buffer = ob_get_clean();
		$this->assertContains( '<div class="error">', $buffer );
	}

	/**
	 * Test _next_recent_posts_widget_php_version_text().
	 *
	 * @see _next_recent_posts_widget_php_version_text()
	 */
	public function test_next_recent_posts_widget_php_version_text() {
		$this->assertContains( 'Next Recent Posts Widget plugin error:', _next_recent_posts_widget_php_version_text() );
	}
}
