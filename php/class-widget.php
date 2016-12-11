<?php
/**
 * Widget class.
 *
 * @package NextRecentPostsWidget
 */

// @todo Re-fetch posts from REST API to re-populate Backbone models when setting in Customize Posts changes.

namespace NextRecentPostsWidget;

/**
 * Class WP_JS_Widget_Recent_Posts
 *
 * @package JSWidgets
 */
class Widget extends \WP_JS_Widget {

	/**
	 * Version of widget.
	 *
	 * @var string
	 */
	public $version = '0.1';

	/**
	 * ID Base.
	 *
	 * @var string
	 */
	public $id_base = 'next_recent_posts';

	/**
	 * Widget constructor.
	 */
	public function __construct() {
		if ( ! isset( $this->name ) ) {
			$this->name = __( 'Next Recent Posts', 'next-recent-posts-widget' );
		}
		parent::__construct();

		add_action( 'wp_footer', array( $this, 'render_template' ) );

		// @todo This should be smarter.
		// @todo Can this return the rendered title as part of the response?
		add_filter( 'customize_posts_partial_schema', function( $schema ) {
			$schema['post_title']['fallback_refresh'] = false;
			return $schema;
		} );
	}
	/**
	 * Enqueue scripts needed for the controls.
	 */
	public function enqueue_control_scripts() {
		$handle = 'next-recent-posts-widget-control';
		wp_enqueue_script( $handle );
	}

	/**
	 * Enqueue scripts needed for the frontend.
	 */
	public function enqueue_frontend_scripts() {
		$handle = 'next-recent-posts-widget-view';

		$is_customize_preview = is_customize_preview();
		if ( $is_customize_preview ) {
			wp_scripts()->registered[ $handle ]->deps[] = 'customize-preview-widgets';
		}

		wp_enqueue_script( $handle );
		$data = array(
			'postsPerPage' => get_option( 'posts_per_page' ),
			'idBase' => $this->id_base,
			'containerSelector' => '.widget.' . $this->widget_options['classname'],
			'defaultInstanceData' => $this->get_default_instance(),
			'renderTemplateId' => 'widget-view-' . $this->id_base,
			'isCustomizePreview' => $is_customize_preview,
		);
		wp_add_inline_script( $handle, sprintf( 'nextRecentPostsWidget.init( %s );', wp_json_encode( $data ) ) );
	}

	/**
	 * Get instance schema properties.
	 *
	 * @return array Schema.
	 */
	public function get_item_schema() {
		$schema = array(
			'title' => array(
				'description' => __( 'The title for the widget.', 'next-recent-posts-widget' ),
				'type' => 'object',
				'context' => array( 'view', 'edit', 'embed' ),
				'properties' => array(
					'raw' => array(
						'description' => __( 'Title for the widget, as it exists in the database.', 'next-recent-posts-widget' ),
						'type' => 'string',
						'context' => array( 'edit' ),
						'default' => '',
						'arg_options' => array(
							'validate_callback' => array( $this, 'validate_title_field' ),
						),
					),
					'rendered' => array(
						'description' => __( 'HTML title for the widget, transformed for display.', 'next-recent-posts-widget' ),
						'type' => 'string',
						'context' => array( 'view', 'edit', 'embed' ),
						'default' => __( 'Recent Posts', 'next-recent-posts-widget' ),
						'readonly' => true,
					),
				),
			),
			'number' => array(
				'description' => __( 'The number of posts to display.', 'js-widgets' ),
				'type' => 'integer',
				'context' => array( 'view', 'edit', 'embed' ),
				'default' => 5,
				'minimum' => 1,
				'arg_options' => array(
					'validate_callback' => 'rest_validate_request_arg',
				),
			),
			'show_date' => array(
				'description' => __( 'Whether the date should be shown.', 'next-recent-posts-widget' ),
				'type' => 'boolean',
				'default' => false,
				'context' => array( 'view', 'edit', 'embed' ),
				'arg_options' => array(
					'validate_callback' => 'rest_validate_request_arg',
				),
			),
			'show_author' => array(
				'description' => __( 'Whether the author is shown.', 'next-recent-posts-widget' ),
				'type' => 'boolean',
				'default' => false,
				'context' => array( 'view', 'edit', 'embed' ),
				'arg_options' => array(
					'validate_callback' => 'rest_validate_request_arg',
				),
			),
			'show_featured_image' => array(
				'description' => __( 'Whether the featured image is shown.', 'next-recent-posts-widget' ),
				'type' => 'boolean',
				'default' => false,
				'context' => array( 'view', 'edit', 'embed' ),
				'arg_options' => array(
					'validate_callback' => 'rest_validate_request_arg',
				),
			),
			'posts' => array(
				'description' => __( 'The IDs for the collected posts.', 'next-recent-posts-widget' ),
				'type' => 'array',
				'items' => array(
					'type' => 'integer',
				),
				'context' => array( 'view', 'edit', 'embed' ),
				'default' => array(),
				'readonly' => true,
			),
		);
		return $schema;
	}

	/**
	 * Render a widget instance for a REST API response.
	 *
	 * Map the instance data to the REST resource fields and add rendered fields.
	 * The Text widget stores the `content` field in `text` and `auto_paragraph` in `filter`.
	 *
	 * @inheritdoc
	 *
	 * @param array            $instance Raw database instance.
	 * @param \WP_REST_Request $request  REST request.
	 * @return array Widget item.
	 */
	public function prepare_item_for_response( $instance, $request ) {
		unset( $request );

		$schema = $this->get_item_schema();
		$instance = array_merge( $this->get_default_instance(), $instance );

		$title_rendered = $instance['title'] ? $instance['title'] : $schema['title']['properties']['rendered']['default'];
		/** This filter is documented in src/wp-includes/widgets/class-wp-widget-pages.php */
		$title_rendered = apply_filters( 'widget_title', $title_rendered, $instance, $this->id_base );

		$item = array(
			'title' => array(
				'raw' => $instance['title'],
				'rendered' => $title_rendered,
			),
			'posts' => $instance['posts'],
			'show_date' => $instance['show_date'],
			'show_featured_image' => $instance['show_featured_image'],
			'show_author' => $instance['show_author'],
		);

		return $item;
	}

	/**
	 * Prepare links for the response.
	 *
	 * @param \WP_REST_Response           $response   Response.
	 * @param \WP_REST_Request            $request    Request.
	 * @param \JS_Widgets_REST_Controller $controller Controller.
	 * @return array Links for the given post.
	 */
	public function get_rest_response_links( $response, $request, $controller ) {
		unset( $request, $controller );
		$links = array();

		$links['wp:post'] = array();
		foreach ( $response->data['posts'] as $post_id ) {
			$post = get_post( $post_id );
			if ( empty( $post ) ) {
				continue;
			}
			$obj = get_post_type_object( $post->post_type );
			if ( empty( $obj ) ) {
				continue;
			}

			$rest_base = ! empty( $obj->rest_base ) ? $obj->rest_base : $obj->name;
			$base = sprintf( '/wp/v2/%s', $rest_base );

			$links['wp:post'][] = array(
				'href'       => rest_url( trailingslashit( $base ) . $post_id ),
				'embeddable' => true,
				'post_type'  => $post->post_type,
			);
		}
		return $links;
	}

	/**
	 * Validate a title request argument based on details registered to the route.
	 *
	 * @param  mixed            $value   Value.
	 * @param  \WP_REST_Request $request Request.
	 * @param  string           $param   Param.
	 * @return \WP_Error|boolean
	 */
	public function validate_title_field( $value, $request, $param ) {
		$valid = rest_validate_request_arg( $value, $request, $param );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( $this->should_validate_strictly( $request ) ) {
			if ( preg_match( '#</?\w+.*?>#', $value ) ) {
				return new \WP_Error( 'rest_invalid_param', sprintf( __( '%s cannot contain markup', 'next-recent-posts-widget' ), $param ) );
			}
			if ( trim( $value ) !== $value ) {
				return new \WP_Error( 'rest_invalid_param', sprintf( __( '%s contains whitespace padding', 'next-recent-posts-widget' ), $param ) );
			}
			if ( preg_match( '/%[a-f0-9]{2}/i', $value ) ) {
				return new \WP_Error( 'rest_invalid_param', sprintf( __( '%s contains illegal characters (octets)', 'next-recent-posts-widget' ), $param ) );
			}
		}
		return true;
	}

	/**
	 * Sanitize instance data.
	 *
	 * @inheritdoc
	 *
	 * @param array $new_instance  New instance.
	 * @param array $old_instance  Old instance.
	 * @return array|null|\WP_Error Array instance if sanitization (and validation) passed. Returns `WP_Error` or `null` on failure.
	 */
	public function sanitize( $new_instance, $old_instance ) {
		unset( $old_instance );
		$instance = array_merge( $this->get_default_instance(), $new_instance );
		$instance['title'] = sanitize_text_field( $instance['title'] );
		foreach ( array( 'show_date', 'show_featured_image', 'show_author' ) as $field ) {
			$instance[ $field ] = boolval( $instance[ $field ] );
		}
		return $instance;
	}

	/**
	 * Widget instance.
	 *
	 * @access public
	 *
	 * @param array $args     Display arguments including 'before_title', 'after_title',
	 *                        'before_widget', and 'after_widget'.
	 * @param array $instance The settings for the particular instance of the widget.
	 * @return void
	 */
	public function render( $args, $instance ) {
		$instance = array_merge( $this->get_default_instance(), $instance );

		/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
		$instance['title'] = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );

		$exported_args = $args;
		unset( $exported_args['before_widget'] );
		unset( $exported_args['after_widget'] );

		$data = array(
			'args' => $exported_args,
			'posts' => null,
		);

		$wp_rest_server = rest_get_server();
		$request = new \WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_query_params( array(
			'per_page' => $instance['number'],
		) );

		$response = $wp_rest_server->dispatch( $request );
		if ( ! $response->is_error() ) {

			/** This filter is documented in wp-includes/rest-api/class-wp-rest-server.php */
			$response = apply_filters( 'rest_post_dispatch', rest_ensure_response( $response ), $wp_rest_server, $request );

			$data['posts'] = $wp_rest_server->response_to_data( $response, true );
		}

		$data['instance'] = $instance;

		$args['before_widget'] = preg_replace(
			'/^(\s*<\w+\s+)/',
			sprintf( '$1 data-embedded="%s"', esc_attr( wp_json_encode( $data ) ) ),
			$args['before_widget'],
			1 // Limit.
		);

		echo $args['before_widget']; // WPCS: xss ok.
		echo $args['after_widget']; // WPCS: xss ok.
	}

	/**
	 * Get configuration data for the form.
	 *
	 * @return array
	 */
	public function get_form_args() {
		return array(
			'l10n' => array(
				'title_tags_invalid' => __( 'Tags will be stripped from the title.', 'next-recent-posts-widget' ),
			),
		);
	}

	/**
	 * Render JS Template.
	 */
	public function form_template() {
		?>
		<script id="tmpl-customize-widget-<?php echo esc_attr( $this->id_base ) ?>" type="text/template">
			<p>
				<label for="{{ data.element_id_base }}_title"><?php esc_html_e( 'Title:', 'next-recent-posts-widget' ) ?></label>
				<input id="{{ data.element_id_base }}_title" class="widefat" type="text" name="title">
			</p>
			<p>
				<label for="{{ data.element_id_base }}_number"><?php esc_html_e( 'Number', 'next-recent-posts-widget' ) ?></label>
				<input id="{{ data.element_id_base }}_number" name="number" type="number" min="1" max="<?php echo esc_attr( get_option( 'posts_per_page' ) ) ?>" size="3">
			</p>
			<p>
				<input id="{{ data.element_id_base }}_show_date" class="widefat" type="checkbox" name="show_date">
				<label for="{{ data.element_id_base }}_show_date"><?php esc_html_e( 'Show date', 'next-recent-posts-widget' ) ?></label>
			</p>
			<p>
				<input id="{{ data.element_id_base }}_show_author" class="widefat" type="checkbox" name="show_author">
				<label for="{{ data.element_id_base }}_show_author"><?php esc_html_e( 'Show author', 'next-recent-posts-widget' ) ?></label>
			</p>
			<p>
				<input id="{{ data.element_id_base }}_show_featured_image" class="widefat" type="checkbox" name="show_featured_image">
				<label for="{{ data.element_id_base }}_show_featured_image"><?php esc_html_e( 'Show featured image', 'next-recent-posts-widget' ) ?></label>
			</p>
		</script>
		<?php
	}

	/**
	 * Render (view) template
	 */
	public function render_template() {
		?>
		<script id="tmpl-widget-view-<?php echo esc_attr( $this->id_base ) ?>" type="text/template">
			<# if ( data.title ) { #>
				{{{ data.before_title }}}
					{{ data.title }}
				{{{ data.after_title }}}
			<# } #>
			<ol>
				<# _.each( data.posts.slice( 0, data.number ), function( post ) { #>
					<li>
						<a href="{{ post.link }}">{{{ post.title.rendered }}}</a>
						<# if ( data.show_date ) { #>
							(<time datetime="{{ post.date }}">{{ post.date.toLocaleDateString() }}</time>)
						<# } #>
						<# if ( data.show_author && _.isObject( post.author ) ) { #>
							{{ post.author.attributes.name }}
						<# } #>
					</li>
				<# } ); #>
			</ol>
		</script>
		<?php
	}
}
