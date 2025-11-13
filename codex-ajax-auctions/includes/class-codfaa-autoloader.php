<?php
/**
 * Simple PSR-4 style autoloader for the Codex AJAX Auctions plugin.
 *
 * @package CodexAjaxAuctions
 */

namespace Codfaa;

defined( 'ABSPATH' ) || exit;

/**
 * Registers an autoloader for Codfaa namespaced classes.
 */
class Autoloader {

	/**
	 * Base namespace for the plugin.
	 *
	 * @var string
	 */
	private $namespace = 'Codfaa\\';

	/**
	 * Base directory for class files.
	 *
	 * @var string
	 */
	private $base_dir;

	/**
	 * Autoloader constructor.
	 */
	public function __construct() {
		$this->base_dir = trailingslashit( CODFAA_PLUGIN_DIR . 'includes' );
	}

	/**
	 * Register autoload callback.
	 */
	public function register() {
		spl_autoload_register( array( $this, 'autoload' ) );
	}

	/**
	 * Locate and require the class file.
	 *
	 * @param string $class Requested class.
	 */
	private function autoload( $class ) {
		if ( 0 !== strpos( $class, $this->namespace ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( $this->namespace ) );
		$relative_path  = str_replace( '\\', '/', strtolower( $relative_class ) );
		$file           = $this->base_dir . $relative_path . '.php';

		if ( file_exists( $file ) ) {
			require $file;
		}
	}
}

( new Autoloader() )->register();
