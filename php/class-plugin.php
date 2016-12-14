<?php
/**
 * Bootstraps the Next Recent Posts Widget plugin.
 *
 * @package NextRecentPostsWidget
 */

namespace NextRecentPostsWidget;

/**
 * Main plugin bootstrap file.
 */
class Plugin extends Plugin_Base {

	/**
	 * Widget instance.
	 *
	 * @var Widget
	 */
	public $widget;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->plugin_file = dirname( __DIR__ ) . '/next-recent-posts-widget.php';
		parent::__construct();
		add_action( 'after_setup_theme', array( $this, 'init' ) );
	}

	/**
	 * Initiate the plugin resources.
	 *
	 * @action after_setup_theme
	 */
	public function init() {
		if ( ! defined( 'REST_API_VERSION' ) || ! class_exists( 'WP_REST_Posts_Controller' ) || ! apply_filters( 'rest_enabled', true ) || ! class_exists( 'WP_JS_Widget' ) ) {
			add_action( 'admin_notices', array( $this, 'show_missing_dependencies_notice' ) );
			return;
		}

		add_action( 'wp_default_scripts', array( $this, 'register_scripts' ), 11 );
		add_action( 'wp_default_styles', array( $this, 'register_styles' ), 11 );
		add_action( 'widgets_init', array( $this, 'register_widget' ) );
		add_action( 'wp_footer', array( $this, 'render_templates' ) );
	}

	/**
	 * Show error when REST API and JS Widgets plugins are not available.
	 *
	 * @action admin_notices
	 */
	public function show_missing_dependencies_notice() {
		?>
		<div class="error">
			<p><?php esc_html_e( 'The Next Recent Posts Widget plugin requires the JS Widgets plugin to be active as well as it requires the WordPress REST API to be available and enabled, including WordPress 4.7 or the REST API plugin.', 'next-recent-posts-widget' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Register scripts.
	 *
	 * @param \WP_Scripts $wp_scripts Instance of \WP_Scripts.
	 * @action wp_default_scripts
	 */
	public function register_scripts( \WP_Scripts $wp_scripts ) {
		$handle = 'next-recent-posts-widget-view';
		$src = $this->dir_url . '/js/widget-view.js';
		$deps = array( 'backbone', 'wp-api', 'wp-util' );
		$wp_scripts->add( $handle, $src, $deps, $this->version );

		$handle = 'next-recent-posts-widget-control';
		$src = $this->dir_url . '/js/widget-control.js';
		$deps = array( 'customize-js-widgets' );
		$wp_scripts->add( $handle, $src, $deps, $this->version );
	}

	/**
	 * Register styles.
	 *
	 * @param \WP_Styles $wp_styles Instance of \WP_Styles.
	 * @action wp_default_styles
	 */
	public function register_styles( \WP_Styles $wp_styles ) {
		$handle = 'next-recent-posts-widget-view';
		$src = $this->dir_url . '/css/widget-view.css';
		$deps = array( 'dashicons' );
		$wp_styles->add( $handle, $src, $deps, $this->version );
	}

	/**
	 * Register widget.
	 *
	 * @action widgets_init, 10
	 */
	public function register_widget() {
		$this->widget = new Widget( $this );
		register_widget( $this->widget );
	}

	/**
	 * Render templates.
	 *
	 * @global \WP_Widget_Factory $wp_widget_factory
	 */
	public function render_templates() {
		global $wp_widget_factory;
		if ( in_array( $this->widget, $wp_widget_factory->widgets, true ) ) {
			$this->widget->render_template();
		}
	}
}
