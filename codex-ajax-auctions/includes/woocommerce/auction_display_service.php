<?php
/**
 * Adjust front-end product experience for Codex auctions.
 *
 * @package CodexAjaxAuctions
 */

namespace Codfaa\WooCommerce;

defined( 'ABSPATH' ) || exit;

use WC_Product;

/**
 * Handles front-end rendering adjustments for auction products.
 */
class Auction_Display_Service {

	/**
	 * Boot display hooks.
	 */
	public function boot() {
		add_action( 'wp', array( $this, 'setup_single_product_hooks' ) );
	}

	/**
	 * If current product is an auction, adjust summary hooks.
	 */
	public function setup_single_product_hooks() {
		if ( ! is_singular( 'product' ) ) {
			return;
		}

		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		if ( Auction_Product_Service::PRODUCT_TYPE !== $product->get_type() ) {
			return;
		}

		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );

		add_action( 'woocommerce_single_product_summary', array( $this, 'render_auction_details' ), 25 );
	}

	/**
	 * Output auction data within the single product summary.
	 */
	public function render_auction_details() {
		global $product;

		if ( ! $product instanceof WC_Product ) {
			return;
		}

		$auction_data = $this->get_auction_meta( $product->get_id() );

		echo '<div class="codfaa-auction-summary">';
		echo '<h3>' . esc_html__( 'Auction Details', 'codex-ajax-auctions' ) . '</h3>';
		echo '<ul class="codfaa-auction-meta">';

		printf(
			'<li><strong>%s:</strong> %s</li>',
			esc_html__( 'Registration Fee', 'codex-ajax-auctions' ),
			esc_html( $auction_data['registration_fee'] )
		);
		printf(
			'<li><strong>%s:</strong> %s</li>',
			esc_html__( 'Bid Cost', 'codex-ajax-auctions' ),
			esc_html( $auction_data['bid_cost'] )
		);
		printf(
			'<li><strong>%s:</strong> %s</li>',
			esc_html__( 'Required Participants', 'codex-ajax-auctions' ),
			esc_html( $auction_data['required_participants'] )
		);
		printf(
			'<li><strong>%s:</strong> %s</li>',
			esc_html__( 'Countdown Timer', 'codex-ajax-auctions' ),
			esc_html( $auction_data['auction_timer'] )
		);

		if ( '' !== $auction_data['buy_now_price'] ) {
			printf(
				'<li><strong>%s:</strong> %s</li>',
				esc_html__( 'Buy It Now Price', 'codex-ajax-auctions' ),
				esc_html( $auction_data['buy_now_price'] )
			);
		}

		echo '</ul>';
		echo '<p class="codfaa-auction-cta">' . esc_html__( 'Login to register and start bidding once the auction is live.', 'codex-ajax-auctions' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Fetch auction meta for display.
	 *
	 * @param int $product_id Product identifier.
	 * @return array<string,string>
	 */
	private function get_auction_meta( $product_id ) {
		$registration_fee      = get_post_meta( $product_id, '_codfaa_registration_fee', true );
		$bid_cost              = get_post_meta( $product_id, '_codfaa_bid_cost', true );
		$required_participants = get_post_meta( $product_id, '_codfaa_required_participants', true );
		$auction_timer         = get_post_meta( $product_id, '_codfaa_auction_timer', true );
		$buy_now_price         = get_post_meta( $product_id, '_codfaa_buy_now_price', true );

		return array(
			'registration_fee'      => $registration_fee ? sprintf( esc_html__( '%d credits', 'codex-ajax-auctions' ), (int) $registration_fee ) : esc_html__( 'Not set', 'codex-ajax-auctions' ),
			'bid_cost'              => $bid_cost ? sprintf( esc_html__( '%d credits', 'codex-ajax-auctions' ), (int) $bid_cost ) : esc_html__( 'Not set', 'codex-ajax-auctions' ),
			'required_participants' => $required_participants ? (int) $required_participants : esc_html__( 'Not set', 'codex-ajax-auctions' ),
			'auction_timer'         => $auction_timer ? sprintf( esc_html__( '%d seconds', 'codex-ajax-auctions' ), (int) $auction_timer ) : esc_html__( 'Not set', 'codex-ajax-auctions' ),
			'buy_now_price'         => '' !== $buy_now_price ? wc_price( $buy_now_price ) : '',
		);
	}
}
