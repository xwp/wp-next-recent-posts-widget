<?php
/**
 * Class Plugin_Base
 *
 * @package NextRecentPostsWidget
 */

namespace NextRecentPostsWidget;

/**
 * Class Plugin_Base
 *
 * @package NextRecentPostsWidget
 */
abstract class Plugin_Base {

	/**
	 * Plugin file.
	 *
	 * Given a plugin slug 'foo', this should get set to .../wp-content/plugins/foo/foo.php
	 * This file contains the metadata for the plugin.
	 *
	 * @var string
	 */
	public $plugin_file;

	/**
	 * Plugin version.
	 *
	 * @var bool|string
	 */
	public $version = false;

	/**
	 * Plugin config.
	 *
	 * @var array
	 */
	public $config = array();

	/**
	 * Plugin slug.
	 *
	 * @var string
	 */
	public $slug;

	/**
	 * Plugin directory path.
	 *
	 * @var string
	 */
	public $dir_path;

	/**
	 * Plugin directory URL.
	 *
	 * @var string
	 */
	public $dir_url;

	/**
	 * Directory in plugin containing autoloaded classes.
	 *
	 * @var string
	 */
	protected $autoload_class_dir = 'php';

	/**
	 * Plugin_Base constructor.
	 *
	 * @throws Exception If the $this->plugin_file is not set.
	 */
	public function __construct() {
		if ( empty( $this->plugin_file ) ) {
			require_once __DIR__ . '/class-exception.php';
			throw new Exception( 'plugin_file not set' );
		}
		if ( preg_match( '/Version:\s*(\d\S*)/', file_get_contents( $this->plugin_file ), $matches ) ) {
			$this->version = $matches[1];
		}
		$this->dir_path = plugin_dir_path( $this->plugin_file );
		$this->dir_url = plugin_dir_url( $this->plugin_file );
		$this->slug = basename( $this->dir_url );
		spl_autoload_register( array( $this, 'autoload' ) );
	}

	/**
	 * Get reflection object for this class.
	 *
	 * @return \ReflectionObject
	 */
	public function get_object_reflection() {
		static $reflection;
		if ( empty( $reflection ) ) {
			$reflection = new \ReflectionObject( $this );
		}
		return $reflection;
	}

	/**
	 * Autoload matches cache.
	 *
	 * @var array
	 */
	protected $autoload_matches_cache = array();

	/**
	 * Autoload for classes that are in the same namespace as $this.
	 *
	 * @param string $class Class name.
	 * @return void
	 */
	public function autoload( $class ) {
		if ( ! isset( $this->autoload_matches_cache[ $class ] ) ) {
			if ( ! preg_match( '/^(?P<namespace>.+)\\\\(?P<class>[^\\\\]+)$/', $class, $matches ) ) {
				$matches = false;
			}
			$this->autoload_matches_cache[ $class ] = $matches;
		} else {
			$matches = $this->autoload_matches_cache[ $class ];
		}
		if ( empty( $matches ) ) {
			return;
		}
		if ( $this->get_object_reflection()->getNamespaceName() !== $matches['namespace'] ) {
			return;
		}
		$class_name = $matches['class'];

		$class_path = \trailingslashit( $this->dir_path );
		if ( $this->autoload_class_dir ) {
			$class_path .= \trailingslashit( $this->autoload_class_dir );
		}
		$class_path .= sprintf( 'class-%s.php', strtolower( str_replace( '_', '-', $class_name ) ) );
		if ( is_readable( $class_path ) ) {
			require_once $class_path;
		}
	}

	/**
	 * Return whether we're on WordPress.com VIP production.
	 *
	 * @return bool
	 */
	public function is_wpcom_vip_prod() {
		return ( defined( '\WPCOM_IS_VIP_ENV' ) && \WPCOM_IS_VIP_ENV );
	}

	/**
	 * Call trigger_error() if not on VIP production.
	 *
	 * @param string $message Warning message.
	 * @param int    $code    Warning code.
	 */
	public function trigger_warning( $message, $code = \E_USER_WARNING ) {
		if ( ! $this->is_wpcom_vip_prod() ) {
			trigger_error( esc_html( get_class( $this ) . ': ' . $message ), $code );
		}
	}
}
