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
		$priority = 20; // \WP_Customize_Widgets::customize_dynamic_partial_args() happens at priority 10.
		add_filter( 'customize_dynamic_partial_args', array( $this, 'filter_customize_dynamic_partial_args' ), $priority, 2 );
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

	/**
	 * Override the partial render_callback for widgets of this type.
	 *
	 * @global \WP_Customize_Manager $wp_customize Manager.
	 * @param false|array $partial_args The arguments to the WP_Customize_Partial constructor.
	 * @return array Partial args.
	 */
	public function filter_customize_dynamic_partial_args( $partial_args ) {
		global $wp_customize;
		if ( empty( $wp_customize ) ) {
			_doing_it_wrong( __FUNCTION__, esc_html__( 'Expected customizer to be instantiated.', 'next-recent-posts-widget' ), null );
			return $partial_args;
		}
		if ( empty( $wp_customize->widgets ) ) {
			return $partial_args; // The widgets component is not loaded.
		}
		if ( ! isset( $partial_args['render_callback'] ) || array( $wp_customize->widgets, 'render_widget_partial' ) !== $partial_args['render_callback'] ) {
			return $partial_args; // Partial is not for a widget.
		}

		$parsed_widget_id = $wp_customize->widgets->parse_widget_setting_id( current( $partial_args['settings'] ) );
		if ( is_wp_error( $parsed_widget_id ) ) {
			return $partial_args;
		}
		if ( $parsed_widget_id['id_base'] === $this->widget->id_base ) {
			$partial_args['render_callback'] = array( $this->widget, 'render_partial' );
		}

		return $partial_args;
	}
}
