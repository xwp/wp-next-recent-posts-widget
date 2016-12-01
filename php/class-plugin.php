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

		$priority = 9; // Because WP_Customize_Widgets::register_settings() happens at after_setup_theme priority 10.
		add_action( 'after_setup_theme', array( $this, 'init' ), $priority );
	}

	/**
	 * Initiate the plugin resources.
	 *
	 * @action after_setup_theme
	 */
	public function init() {
		if ( ! defined( 'REST_API_VERSION' ) || ! class_exists( 'WP_REST_Posts_Controller' ) || ! apply_filters( 'rest_enabled', true ) ) {
			add_action( 'admin_notices', array( $this, 'show_missing_rest_api_admin_notice' ) );
			return;
		}

		$this->config = apply_filters( 'next_recent_posts_widget_plugin_config', $this->config, $this );

		add_action( 'wp_default_scripts', array( $this, 'register_scripts' ), 11 );
		add_action( 'wp_default_styles', array( $this, 'register_styles' ), 11 );
		add_action( 'widgets_init', array( $this, 'register_widget' ) );
		add_action( 'widgets_init', array( $this, 'export_widget_types' ), 90 );
	}

	/**
	 * Show error when REST API is not available.
	 *
	 * @action admin_notices
	 */
	public function show_missing_rest_api_admin_notice() {
		?>
		<div class="error">
			<p><?php esc_html_e( 'The Next Recent Posts Widget plugin requires the WordPress REST API to be available and enabled, including WordPress 4.7 or the REST API plugin.', 'next-recent-posts-widget' ); ?></p>
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

		$exports = array(
			'postsPerPage' => (int) get_option( 'posts_per_page' ),
		);
		$wp_scripts->add_data(
			$handle,
			'data',
			sprintf( 'var _nextRecentPostsWidgetExports = %s;', wp_json_encode( $exports ) )
		);
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
		$deps = array();
		$wp_styles->add( $handle, $src, $deps, $this->version );
	}

	/**
	 * Register widget.
	 *
	 * @action widgets_init, 10
	 */
	public function register_widget() {
		global $wp_widget_factory;
		$class_name = __NAMESPACE__ . '\\Widget';
		register_widget( $class_name );
		$this->widget = $wp_widget_factory->widgets[ $class_name ];
		$this->widget->plugin = $this;

		/*
		 * Note: Once register_widget() allows pre-instantiated widgets to be
		 * passed into register_widget(), this can be simplified to:
		 *
		 *   $this->widget = new Widget();
		 *   $this->widget->plugin = $this;
		 *   register_widget( $this->widget );
		 *
		 * See https://core.trac.wordpress.org/ticket/28216
		 */
	}

	/**
	 * Export the container selector and default instance data for the widget.
	 *
	 * @action widgets_init, 90
	 */
	public function export_widget_types() {
		$container_selector = '.' . $this->widget->widget_options['classname'];

		$handle = 'next-recent-posts-widget-view';
		$wp_scripts = wp_scripts();
		$data = $wp_scripts->get_data( $handle, 'data' );
		$data .= sprintf( '_nextRecentPostsWidgetExports.containerSelector = %s;', wp_json_encode( $container_selector ) );
		$data .= sprintf( '_nextRecentPostsWidgetExports.defaultInstanceData = %s;', wp_json_encode( $this->widget->get_default_instance() ) );
		$wp_scripts->add_data( $handle, 'data', $data );
	}

	/**
	 * Get instance of WP_REST_Server.
	 *
	 * @return \WP_REST_Server
	 */
	public function get_rest_server() {
		/**
		 * REST Server.
		 *
		 * @var \WP_REST_Server $wp_rest_server
		 */
		global $wp_rest_server;
		if ( empty( $wp_rest_server ) ) {
			/** This filter is documented in wp-includes/rest-api.php */
			$wp_rest_server_class = apply_filters( 'wp_rest_server_class', 'WP_REST_Server' );
			$wp_rest_server = new $wp_rest_server_class();

			/** This filter is documented in wp-includes/rest-api.php */
			do_action( 'rest_api_init', $wp_rest_server );
		}
		return $wp_rest_server;
	}
}
