<?php
/**
 * Plugin Name: Codex AJAX Auctions
 * Plugin URI:  https://codex.example.com/plugins/codex-ajax-auctions
 * Description: Credits-based auction toolkit for WooCommerce with AJAX-driven bidding and registration flows.
 * Version:     0.1.0
 * Author:      Codex AI
 * Author URI:  https://codex.example.com
 * Text Domain: codex-ajax-auctions
 * Domain Path: /languages
 *
 * @package CodexAjaxAuctions
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'CODFAA_PLUGIN_FILE' ) ) {
	define( 'CODFAA_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'CODFAA_PLUGIN_DIR' ) ) {
	define( 'CODFAA_PLUGIN_DIR', plugin_dir_path( CODFAA_PLUGIN_FILE ) );
}

if ( ! defined( 'CODFAA_PLUGIN_URL' ) ) {
	define( 'CODFAA_PLUGIN_URL', plugin_dir_url( CODFAA_PLUGIN_FILE ) );
}

/**
 * Main plugin bootstrap.
 */
final class Codex_Ajax_Auctions {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '0.1.0';

	/**
	 * Singleton instance.
	 *
	 * @var Codex_Ajax_Auctions|null
	 */
	private static $instance = null;

	/**
	 * Retrieve singleton instance.
	 *
	 * @return Codex_Ajax_Auctions
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Codex_Ajax_Auctions constructor.
	 */
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Load required files.
	 */
	private function includes() {
		require_once CODFAA_PLUGIN_DIR . 'includes/class-codfaa-autoloader.php';
	}

	/**
	 * Register WordPress hooks.
	 */
	private function init_hooks() {
		register_activation_hook( CODFAA_PLUGIN_FILE, array( 'Codfaa\\Setup\\Installer', 'activate' ) );
		register_deactivation_hook( CODFAA_PLUGIN_FILE, array( 'Codfaa\\Setup\\Installer', 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
	}

	/**
	 * Initialize features once WordPress and dependencies are loaded.
	 */
	public function on_plugins_loaded() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return;
		}

		$container = new Codfaa\Bootstrap\Container();
		$container->boot();
	}

	/**
	 * Admin notice for missing WooCommerce.
	 */
	public function woocommerce_missing_notice() {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			esc_html__( 'Codex AJAX Auctions requires WooCommerce to be active.', 'codex-ajax-auctions' )
		);
	}
}

Codex_Ajax_Auctions::instance();
