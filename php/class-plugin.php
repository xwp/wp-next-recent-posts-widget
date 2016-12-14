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
		add_filter( 'customize_render_partials_response', array( $this, 'amend_partials_response_with_rest_resources' ), 10, 3 );

		// @todo There should be some more sophisticated logic for determining whether fallback_refresh is done.
		add_filter( 'customize_posts_partial_schema', function( $schema ) {
			$schema['post_title']['fallback_refresh'] = false;
			$schema['post_excerpt']['fallback_refresh'] = false;
			return $schema;
		} );
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
	 * Add the REST resources for the customized posts to the partial rendering response.
	 *
	 * @param array $response {
	 *     Response.
	 *
	 *     @type array $contents Associative array mapping a partial ID its corresponding array of contents
	 *                           for the containers requested.
	 *     @type array $errors   List of errors triggered during rendering of partials, if `WP_DEBUG_DISPLAY`
	 *                           is enabled.
	 * }
	 * @param \WP_Customize_Selective_Refresh $selective_refresh Selective refresh component.
	 * @param array                           $partials Placements' context data for the partials rendered in the request.
	 *                                                  The array is keyed by partial ID, with each item being an array of
	 *                                                  the placements' context data.
	 * @return array Response.
	 */
	public function amend_partials_response_with_rest_resources( $response, $selective_refresh, $partials ) {

		// Abort if Customize Posts isn't even active.
		if ( ! class_exists( 'WP_Customize_Post_Setting' ) ) {
			return $response;
		}

		// Abort if the partial render request isn't for a post field partial.
		$requesting_post_field_partial = false;
		foreach ( array_keys( $partials ) as $partial_id ) {
			if ( $selective_refresh->get_partial( $partial_id ) instanceof \WP_Customize_Post_Field_Partial ) {
				$requesting_post_field_partial = true;
				break;
			}
		}
		if ( ! $requesting_post_field_partial ) {
			return $response;
		}

		// Gather the customized posts by type.
		$posts_by_type = array();
		foreach ( $selective_refresh->manager->settings() as $setting ) {
			if ( $setting instanceof \WP_Customize_Post_Setting ) {
				if ( ! isset( $posts_by_type[ $setting->post_type ] ) ) {
					$posts_by_type[ $setting->post_type ] = array();
				}
				$posts_by_type[ $setting->post_type ][] = get_post( $setting->post_id );
			}
		}

		// Short-circuit if there are no customized posts.
		if ( count( $posts_by_type ) === 0 ) {
			return $response;
		}

		// Amend partial render response with the rest resources for the given customized posts.
		$response['rest_post_resources'] = array();
		$wp_rest_server = rest_get_server();
		foreach ( $posts_by_type as $type => $posts ) {
			$post_type_object = get_post_type_object( $type );
			if ( ! $post_type_object || empty( $post_type_object->rest_base ) ) {
				continue;
			}

			$request = new \WP_REST_Request( 'GET', '/wp/v2/' . $post_type_object->rest_base );
			$request->set_query_params( array(
				'per_page' => 100,
				'include' => wp_list_pluck( $posts, 'ID' ),
			) );
			if ( current_user_can( $post_type_object->cap->edit_posts ) ) {
				$request->set_query_params( array(
					'context' => 'edit',
				) );
			}

			$rest_response = $wp_rest_server->dispatch( $request );
			if ( ! $rest_response->is_error() ) {

				/** This filter is documented in wp-includes/rest-api/class-wp-rest-server.php */
				$rest_response = apply_filters( 'rest_post_dispatch', rest_ensure_response( $rest_response ), $wp_rest_server, $request );

				foreach ( $wp_rest_server->response_to_data( $rest_response, true ) as $post_data ) {
					$response['rest_post_resources'][ $post_data['id'] ] = $post_data;
				}
			}
		}

		return $response;
	}
}
