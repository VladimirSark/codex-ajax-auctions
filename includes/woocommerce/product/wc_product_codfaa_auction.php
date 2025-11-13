<?php
/**
 * WooCommerce product class for Codex auctions.
 *
 * @package CodexAjaxAuctions
 */

namespace Codfaa\WooCommerce\Product;

defined( 'ABSPATH' ) || exit;

use Codfaa\WooCommerce\Auction_Product_Service;
use WC_Product_Simple;

/**
 * Provides WooCommerce product behavior for auction products.
 */
class WC_Product_Codfaa_Auction extends WC_Product_Simple {

	/**
	 * Constructor.
	 *
	 * @param mixed $product Product ID or object.
	 */
	public function __construct( $product = 0 ) {
		parent::__construct( $product );

		$this->set_props(
			array(
				'type' => Auction_Product_Service::PRODUCT_TYPE,
			)
		);
	}

	/**
	 * Return unique product type identifier.
	 *
	 * @return string
	 */
	public function get_type() {
		return Auction_Product_Service::PRODUCT_TYPE;
	}
}
