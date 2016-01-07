<?php
/**
 * Widget class.
 *
 * @package NextRecentPostsWidget
 */

namespace NextRecentPostsWidget;

/**
 * Widget
 *
 * @todo Abstract this into a Customize_Widget for re-use.
 */
class Widget extends \WP_Widget {

	/**
	 * Plugin instance.
	 *
	 * @see \NextRecentPostsWidget\Plugin::register_widget()
	 *
	 * @var Plugin
	 */
	public $plugin;

	/**
	 * Root ID for all widgets of this type.
	 *
	 * @access public
	 * @var mixed|string
	 */
	public $id_base = 'next_recent_posts';

	/**
	 * Option array passed to {@see wp_register_sidebar_widget()}.
	 *
	 * @access public
	 * @var array
	 */
	public $widget_options = array();

	/**
	 * Option array passed to {@see wp_register_widget_control()}.
	 *
	 * @access public
	 * @var array
	 */
	public $control_options = array();

	/**
	 * Constructor.
	 *
	 * @param string $id_base         Optional Base ID for the widget, lowercase and unique. If left empty,
	 *                                a portion of the widget's class name will be used Has to be unique.
	 * @param string $name            Name for the widget displayed on the configuration page.
	 * @param array  $widget_options  Optional. Widget options. See wp_register_sidebar_widget() for information
	 *                                on accepted arguments. Default empty array.
	 * @param array  $control_options Optional. Widget control options. See wp_register_widget_control() for
	 *                                information on accepted arguments. Default empty array.
	 */
	public function __construct( $id_base = null, $name = null, $widget_options = null, $control_options = null ) {
		if ( is_null( $id_base ) ) {
			$id_base = $this->id_base;
		}
		if ( is_null( $name ) ) {
			$name = __( 'Next Recent Posts', 'next-recent-posts-widget' );
		}
		if ( empty( $this->widget_options ) ) {
			$this->widget_options = array(
				'description' => __( 'Dynamic (JS-driven), self-updating list of recent posts.' ),
			);
		}
		if ( is_null( $widget_options ) ) {
			$widget_options = $this->widget_options;
		}
		if ( is_null( $control_options ) ) {
			$control_options = $this->control_options;
		}
		parent::__construct( $id_base, $name, $widget_options, $control_options );

		wp_enqueue_script( 'next-recent-posts-widget-view' );
		wp_enqueue_style( 'next-recent-posts-widget-view' );
		add_action( 'wp_footer', array( $this, 'print_templates' ) );
	}

	/**
	 * Widget instance.
	 *
	 * @access public
	 *
	 * @param array $args     Display arguments including 'before_title', 'after_title',
	 *                        'before_widget', and 'after_widget'.
	 * @param array $instance The settings for the particular instance of the widget.
	 */
	public function widget( $args, $instance ) {
		$instance = array_merge( $this->get_default_instance(), $instance );

		$exported_args = $args;
		unset( $exported_args['before_widget'] );
		unset( $exported_args['after_widget'] );

		$data = array(
			'args' => $exported_args,
			'posts' => null,
		);

		$wp_rest_server = $this->plugin->get_rest_server();
		$request = new \WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_query_params( array(
			'filter' => array( 'posts_per_page' => $instance['number'] ),
		) );

		$response = $wp_rest_server->dispatch( $request );
		if ( ! $response->is_error() ) {
			$data['posts'] = $wp_rest_server->response_to_data( $response, true );
			$instance['has_more'] = ( $instance['number'] < $response->headers['X-WP-Total'] );
		}

		$data['instance'] = $instance;

		echo $args['before_widget']; // WPCS: xss ok.
		echo '<script type="application/json">';
		echo wp_json_encode( $data );
		echo '</script>';
		echo $args['after_widget']; // WPCS: xss ok.
	}

	/**
	 * Widget form.
	 *
	 * @param array $instance Instance data. Unused.
	 * @access public
	 */
	public function form( $instance ) {
		unset( $instance );
		if ( 'widgets' === get_current_screen()->base ) {
			?>
			<p>
				<?php
				echo wp_kses_post( sprintf(
					__( 'This widget can only be edited in the <a href="%s">Customizer</a>.', 'next-recent-posts-widget' ),
					add_query_arg( array( 'autofocus[panel]' => 'widgets' ), wp_customize_url() )
				) );
				?>
			</p>
			<?php
			return 'noform';
		}
	}

	/**
	 * Get the default instance data.
	 *
	 * @return array
	 */
	public function get_default_instance() {
		return array(
			'title' => '',
			'number' => 5,
			'show_date' => true,
			'show_author' => true,
		);
	}

	/**
	 * Updates a particular instance of a widget.
	 *
	 * This function should check that `$new_instance` is set correctly. The newly-calculated
	 * value of `$instance` should be returned. If false is returned, the instance won't be
	 * saved/updated.
	 *
	 * @access public
	 *
	 * @param array $new_instance New settings for this instance as input by the user via
	 *                            WP_Widget::form().
	 * @param array $old_instance Old settings for this instance.
	 * @return array Settings to save or bool false to cancel saving.
	 */
	public function update( $new_instance, $old_instance ) {
		unset( $old_instance );
		$new_instance = array_merge(
			$this->get_default_instance(),
			$new_instance
		);
		$new_instance['title'] = sanitize_text_field( $new_instance['title'] );
		$new_instance['number'] = min( 100, max( 1, intval( $new_instance['number'] ) ) );
		$new_instance['show_date'] = boolval( $new_instance['show_date'] );
		return $new_instance;
	}

	/**
	 * Print JS templates.
	 *
	 * @action wp_footer
	 */
	public function print_templates() {
		?>
		<script id="tmpl-next-recent-posts-widget" type="text/template">
			<# if ( data.title ) { #>
				{{{ data.before_title }}}
					{{ data.title }}
				{{{ data.after_title }}}
			<# } #>
			<ol>
				<# _.each( data.posts, function( post ) { #>
					<li>
						<a href="{{ post.link }}">{{{ post.title.rendered }}}</a>
						<# if ( data.show_date ) { #>
							(<time datetime="{{ post.date }}">{{ post.date.toLocaleDateString() }}</time>)
						<# } #>
						<# if ( data.show_author ) { #>
							{{ post.author.attributes.name }}
						<# } #>
					</li>
				<# } ); #>
			</ol>
			<# if ( data.has_more ) { #>
				<p>
					<button class="load-more" type="button">More</button>
					<span class="spinner"></span>
				</p>
			<# } #>
		</script>
		<?php
	}
}
