<?php
/**
 * Simple service container that boots plugin services.
 *
 * @package CodexAjaxAuctions
 */

namespace Codfaa\Bootstrap;

defined( 'ABSPATH' ) || exit;

/**
 * Basic container/registry for plugin services.
 */
class Container {

	/**
	 * Registered service class names.
	 *
	 * @var string[]
	 */
	private $services = array();

	/**
	 * Boot core services.
	 */
	public function boot() {
		$this->services = $this->get_service_classes();

		foreach ( $this->services as $class ) {
			if ( class_exists( $class ) ) {
				$instance = new $class();

				if ( method_exists( $instance, 'boot' ) ) {
					$instance->boot();
				}
			}
		}
	}

	/**
	 * Return list of service classes to bootstrap.
	 *
	 * @return string[]
	 */
	private function get_service_classes() {
		return array(
			'Codfaa\\Auctions\\Auction_Post_Type',
			'Codfaa\\Auctions\\Registration_Service',
			'Codfaa\\Auctions\\Bidding_Service',
			'Codfaa\\Auctions\\Auction_Shortcode',
			'Codfaa\\Auctions\\Admin_Dashboard_Service',
		);
	}
}
