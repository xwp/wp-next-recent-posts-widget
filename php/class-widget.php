<?php
/**
 * Widget class.
 *
 * @package NextRecentPostsWidget
 */

namespace NextRecentPostsWidget;

/**
 * Class WP_JS_Widget_Recent_Posts
 *
 * @package JSWidgets
 */
class Widget extends \WP_JS_Widget {

	/**
	 * ID Base.
	 *
	 * @var string
	 */
	public $id_base = 'next-recent-posts';

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Widget constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
		if ( ! isset( $this->name ) ) {
			$this->name = __( 'Next Recent Posts', 'next-recent-posts-widget' );
		}
		parent::__construct();
	}

	/**
	 * Get REST server.
	 *
	 * This is a workaround to ensure that a REST server can be performantly instantiated
	 * during a widget's rendering callback. The reason for the slowness is that object-
	 * cache addition is suspended during a widget's rendering in the customizer to
	 * help prevent a widget from possibly polluting a persistent object cache with
	 * previewed data. Instantiating a new REST server is extremely slow without object
	 * cache particularly due to the gathering of the post templates for the REST API schema.
	 *
	 * @return \WP_REST_Server REST Server.
	 */
	public function get_rest_server() {
		$suspended = null;
		if ( $this->is_preview() ) {
			$suspended = wp_suspend_cache_addition();
			wp_suspend_cache_addition( false );
		}
		$server = rest_get_server();
		if ( $this->is_preview() ) {
			wp_suspend_cache_addition( $suspended );
		}
		return $server;
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

		$is_customize_preview = is_customize_preview() && current_user_can( 'customize' );
		if ( $is_customize_preview ) {
			wp_scripts()->registered[ $handle ]->deps[] = 'customize-preview-widgets';
		}

		wp_enqueue_script( $handle );
		$data = array(
			'perPage' => get_option( 'posts_per_page' ),
			'idBase' => $this->id_base,
			'containerSelector' => '.widget.' . $this->widget_options['classname'],
			'defaultInstanceData' => $this->get_default_instance(),
			'renderTemplateId' => 'widget-view-' . $this->id_base,
			'isCustomizePreview' => $is_customize_preview,

		);
		wp_add_inline_script( $handle, sprintf( 'nextRecentPostsWidget.init( %s );', wp_json_encode( $data ) ) );

		wp_enqueue_style( 'next-recent-posts-widget-view' );
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
			'show_excerpt' => array(
				'description' => __( 'Whether the excerpt is shown.', 'next-recent-posts-widget' ),
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
		$title_rendered = html_entity_decode( $title_rendered, ENT_QUOTES, get_bloginfo( 'charset' ) );
		$title_rendered = convert_smilies( $title_rendered );

		/** This filter is documented in wp-includes/widgets/class-wp-widget-recent-posts.php */
		$query = new \WP_Query( apply_filters( 'widget_posts_args', array(
			'posts_per_page'      => $instance['number'],
			'no_found_rows'       => true,
			'post_status'         => 'publish',
			'ignore_sticky_posts' => true,
		) ) );

		$item = array_merge(
			$instance,
			array(
				'title' => array(
					'raw' => $instance['title'],
					'rendered' => $title_rendered,
				),
				'posts' => wp_list_pluck( $query->posts, 'ID' ),
			)
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
		foreach ( array( 'show_date', 'show_featured_image', 'show_author', 'show_excerpt' ) as $field ) {
			$instance[ $field ] = boolval( $instance[ $field ] );
		}
		return $instance;
	}

	/**
	 * Get the REST resource item for the widget.
	 *
	 * @param array $instance Instance data.
	 * @param int   $number   Widget number.
	 *
	 * @return array Item.
	 */
	public function get_rest_item( $instance, $number = null ) {
		if ( empty( $number ) ) {
			$number = $this->number;
		}

		/*
		 * Must be called first so that the rest_api_init action will have been done
		 * so that $this->rest_controller will be set.
		 */
		$wp_rest_server = $this->get_rest_server();

		$route = '/' . $this->rest_controller->get_namespace() . '/widgets/' . $this->rest_controller->get_rest_base() . '/' . $number;
		$request = new \WP_REST_Request( 'GET', $route );
		$request->set_query_params( array(
			'context' => 'view',
		) );
		$response = $this->rest_controller->prepare_item_for_response( $instance, $request, $number );

		/** This filter is documented in wp-includes/rest-api/class-wp-rest-server.php */
		$response = apply_filters( 'rest_post_dispatch', $response, $wp_rest_server, $request );

		// Embed the posts 2-levels deep.
		$embedded_posts = array();
		foreach ( $response->data['posts'] as $post_id ) {
			$post_request = new \WP_REST_Request( 'GET', "/wp/v2/posts/$post_id" );
			$post_request->set_query_params( array(
				'context' => 'view',
			) );
			$post_response = $wp_rest_server->dispatch( $post_request );

			/** This filter is documented in wp-includes/rest-api/class-wp-rest-server.php */
			$post_response = apply_filters( 'rest_post_dispatch', $post_response, $wp_rest_server, $post_request );

			if ( ! $post_response->is_error() ) {
				$post_response = $wp_rest_server->envelope_response( $post_response, true );
				$embedded_posts[] = $post_response->data['body'];
			}
		}
		$response->data['_embedded']['wp:post'] = $embedded_posts;
		$item = $response->data;
		return $item;

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
		$item = $this->get_rest_item( $instance, $this->number );

		$exported_args = $args;
		unset( $exported_args['before_widget'] );
		unset( $exported_args['after_widget'] );

		$args['before_widget'] = preg_replace(
			'/^(\s*<\w+\s+)/',
			sprintf( '$1 data-args="%s" data-item="%s"', esc_attr( wp_json_encode( $exported_args ) ), esc_attr( wp_json_encode( $item ) ) ),
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
				<input id="{{ data.element_id_base }}_show_excerpt" class="widefat" type="checkbox" name="show_excerpt">
				<label for="{{ data.element_id_base }}_show_excerpt"><?php esc_html_e( 'Show excerpt', 'next-recent-posts-widget' ) ?></label>
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
		$edit_link_template = admin_url( '/' ) . 'post.php?post=%d&action=edit';
		?>
		<script id="tmpl-widget-view-<?php echo esc_attr( $this->id_base ) ?>" type="text/template">
			<#
			var editPostLinkTpl = <?php echo wp_json_encode( $edit_link_template ); ?>;
			#>
			<# if ( data.title.rendered ) { #>
				{{{ data.before_title }}}
					{{ data.title.rendered }}
				{{{ data.after_title }}}
			<# } #>
			<ol>
				<# _.each( data.posts.slice( 0, data.number ), function( post ) { #>
					<li>
						<article itemscope itemtype="https://schema.org/BlogPosting">
							<h3>
								<a class="entry-title" href="{{ post.link }}">{{{ post.title.rendered }}}</a>
								<?php if ( current_user_can( 'edit_posts' ) && ( ! is_customize_preview() || class_exists( 'WP_Customize_Post_Setting' ) ) ) : ?>
									<a class="post-edit-link" href="{{ editPostLinkTpl.replace( '%d', post.id ) }}">
										<span class="screen-reader-text"><?php esc_html_e( 'Edit This', 'default' ) ?></span>
									</a>
								<?php endif; ?>
							</h3>
							<# if ( data.show_featured_image && _.isObject( post.featured_media ) ) { #>
								<img src="{{ post.featured_media.get( 'media_details' ).sizes.medium.source_url }}"
									width="{{ post.featured_media.get( 'media_details' ).sizes.medium.width / 2 }}"
									height="{{ post.featured_media.get( 'media_details' ).sizes.medium.height / 2 }}"
									alt="{{ post.featured_media.get( 'title' ).rendered }}">
							<# } #>
							<footer>
								<# if ( data.show_date ) { #>
									<time itemprop="dataPublished" datetime="{{ post.date }}">{{ post.date.toLocaleDateString() }}</time>
								<# } #>
								<# if ( data.show_date && data.show_author && _.isObject( post.author ) ) { #>
									|
								<# } #>
								<# if ( data.show_author && _.isObject( post.author ) ) { #>
									<a itemprop="author" href="{{ post.author.get( 'link' ) }}">{{ post.author.get( 'name' ) }}</a>
								<# } #>
							</footer>
							<# if ( data.show_excerpt && post.excerpt.rendered ) { #>
								<div class="excerpt" itemprop="description">
									{{{ post.excerpt.rendered }}}
								</div>
							<# } #>
						</article>
					</li>
				<# } ); #>
			</ol>
		</script>
		<?php
	}

	/**
	 * Renders a specific widget using the supplied sidebar arguments.
	 *
	 * @param \WP_Customize_Partial $partial Partial.
	 * @return array|false REST widget item or false on error`.
	 */
	public function render_partial( $partial ) {
		$id_data   = $partial->id_data();
		$widget_id = array_shift( $id_data['keys'] );
		$parsed_widget_id = $partial->component->manager->widgets->parse_widget_id( $widget_id );
		if ( is_wp_error( $parsed_widget_id ) ) {
			return false;
		}

		$instance = $partial->component->manager->get_setting( $partial->primary_setting )->value();
		$item = $this->get_rest_item( $instance, $parsed_widget_id['number'] );
		return $item;
	}
}
