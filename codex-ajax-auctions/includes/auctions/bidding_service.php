<?php
/**
 * Handles AJAX bidding, live status, and prize claims for Codex auctions.
 *
 * @package CodexAjaxAuctions
 */

namespace Codfaa\Auctions;

defined( 'ABSPATH' ) || exit;

use WC_Product;
use WP_Error;

/**
 * Service responsible for processing user bids, exposing live status, and allowing winners to claim prizes.
 */
class Bidding_Service {

	const STATE_UPCOMING = 'upcoming';
	const STATE_LIVE     = 'live';
	const STATE_ENDED    = 'ended';

	public const CLAIM_PRICE = 1.00;
	private const READY_COUNTDOWN = 600; // 10 minutes.

	/**
	 * Register AJAX hooks.
	 */
	public function boot() {
		add_action( 'wp_ajax_codfaa_place_bid', array( $this, 'handle_bid' ) );
		add_action( 'wp_ajax_nopriv_codfaa_place_bid', array( $this, 'handle_bid_guest' ) );

		add_action( 'wp_ajax_codfaa_auction_status', array( $this, 'handle_status' ) );
		add_action( 'wp_ajax_nopriv_codfaa_auction_status', array( $this, 'handle_status' ) );

		add_action( 'wp_ajax_codfaa_claim_prize', array( $this, 'handle_claim' ) );
		add_action( 'wp_ajax_nopriv_codfaa_claim_prize', array( $this, 'handle_claim_guest' ) );

		add_action( 'woocommerce_before_calculate_totals', array( $this, 'maybe_enforce_cart_prices' ), 20 );
		add_action( 'codfaa_participant_registered', array( $this, 'maybe_schedule_ready_state' ) );
	}

	/**
	 * Handle bid placements for logged-in users.
	 */
	public function handle_bid() {
		check_ajax_referer( Registration_Service::NONCE_ACTION, 'nonce' );

		if ( ! is_user_logged_in() ) {
			$this->send_error_response(
				__( 'Please log in before placing a bid.', 'codex-ajax-auctions' ),
				wp_login_url( wp_get_referer() ? wp_get_referer() : home_url() ),
				401
			);
		}

		$auction_id = isset( $_POST['auction_id'] ) ? absint( $_POST['auction_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $auction_id || Auction_Post_Type::POST_TYPE !== get_post_type( $auction_id ) ) {
			$this->send_error_response( __( 'Invalid auction.', 'codex-ajax-auctions' ) );
		}

		$user_id = get_current_user_id();

		if ( ! $this->user_is_registered( $auction_id, $user_id ) ) {
			if ( $this->user_has_pending_registration( $auction_id, $user_id ) ) {
				$this->send_error_response( __( 'Your registration is awaiting admin confirmation. Please check back soon.', 'codex-ajax-auctions' ) );
			}

			$this->send_error_response( __( 'Please register for this auction before bidding.', 'codex-ajax-auctions' ) );
		}

		$state = $this->get_auction_state( $auction_id );

		if ( self::STATE_LIVE !== $state ) {
			if ( self::STATE_UPCOMING === $state && $this->is_ready( $auction_id ) ) {
				$this->set_auction_state( $auction_id, self::STATE_LIVE );
			} else {
				$this->send_error_response( __( 'This auction is not currently live.', 'codex-ajax-auctions' ) );
			}
		}

		$bid_product_id = (int) get_post_meta( $auction_id, Auction_Post_Type::META_BID_PRODUCT_ID, true );

		if ( ! $bid_product_id ) {
			$this->send_error_response( __( 'Bid fee product is not configured for this auction.', 'codex-ajax-auctions' ) );
		}

		$product = wc_get_product( $bid_product_id );

		if ( ! $product instanceof WC_Product ) {
			$this->send_error_response( __( 'Bid fee product could not be loaded.', 'codex-ajax-auctions' ) );
		}

		$bid_amount_display = (float) wc_get_price_to_display( $product );
		$bid_amount_minor   = (int) round( $bid_amount_display * 100 );

		if ( $bid_amount_minor <= 0 ) {
			$this->send_error_response( __( 'Bid fee must be greater than zero.', 'codex-ajax-auctions' ) );
		}

		$last_bid_user = (int) get_post_meta( $auction_id, '_codfaa_last_bid_user', true );

		if ( $last_bid_user && $last_bid_user === $user_id ) {
			$payload = $this->build_status_payload( $auction_id, $user_id );

			if ( is_wp_error( $payload ) ) {
				$this->send_error_response( $payload->get_error_message() );
			}

			$payload['message'] = __( 'You are already the highest bidder.', 'codex-ajax-auctions' );
			wp_send_json_success( $payload );
		}

		$record = $this->record_bid( $auction_id, $user_id, $bid_amount_minor );

		if ( is_wp_error( $record ) ) {
			$this->send_error_response( $record->get_error_message() );
		}

		$payload = $this->build_status_payload( $auction_id, $user_id );

		if ( is_wp_error( $payload ) ) {
			$this->send_error_response( $payload->get_error_message() );
		}

		$payload['message'] = __( 'Your bid has been recorded!', 'codex-ajax-auctions' );

		wp_send_json_success( $payload );
	}

	/**
	 * Return the latest auction status.
	 */
	public function handle_status() {
		$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $nonce || ! wp_verify_nonce( $nonce, Registration_Service::NONCE_ACTION ) ) {
			$this->send_error_response( __( 'Invalid request token.', 'codex-ajax-auctions' ), '', 403 );
		}

		$auction_id = isset( $_POST['auction_id'] ) ? absint( $_POST['auction_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $auction_id || Auction_Post_Type::POST_TYPE !== get_post_type( $auction_id ) ) {
			$this->send_error_response( __( 'Invalid auction.', 'codex-ajax-auctions' ), '', 404 );
		}

		$payload = $this->build_status_payload( $auction_id, get_current_user_id() );

		if ( is_wp_error( $payload ) ) {
			$this->send_error_response( $payload->get_error_message() );
		}

		wp_send_json_success( $payload );
	}

	/**
	 * Claim the prize and add products to the winner's cart.
	 */
	public function handle_claim() {
		check_ajax_referer( Registration_Service::NONCE_ACTION, 'nonce' );

		if ( ! is_user_logged_in() ) {
			$this->send_error_response(
				__( 'Please log in to claim the prize.', 'codex-ajax-auctions' ),
				wp_login_url( wp_get_referer() ? wp_get_referer() : home_url() ),
				401
			);
		}

		$auction_id = isset( $_POST['auction_id'] ) ? absint( $_POST['auction_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $auction_id || Auction_Post_Type::POST_TYPE !== get_post_type( $auction_id ) ) {
			$this->send_error_response( __( 'Invalid auction.', 'codex-ajax-auctions' ) );
		}

		$user_id = get_current_user_id();

		$status = $this->build_status_payload( $auction_id, $user_id );

		if ( is_wp_error( $status ) ) {
			$this->send_error_response( $status->get_error_message() );
		}

		$state = isset( $status['state'] ) ? $status['state'] : $this->get_auction_state( $auction_id );
		$ended = ! empty( $status['ended'] ) || self::STATE_ENDED === $state;

		if ( $ended && self::STATE_ENDED !== $state ) {
			$this->set_auction_state( $auction_id, self::STATE_ENDED );
			$state = self::STATE_ENDED;
		}

		if ( ! $ended ) {
			$this->send_error_response( __( 'The auction has not ended yet.', 'codex-ajax-auctions' ) );
		}

		$winner_user = (int) get_post_meta( $auction_id, '_codfaa_winner_user', true );

		if ( ! $winner_user ) {
			$last_bid_user = (int) get_post_meta( $auction_id, '_codfaa_last_bid_user', true );
			if ( $last_bid_user ) {
				$this->maybe_finalize_auction( $auction_id, $last_bid_user );
				$winner_user = (int) get_post_meta( $auction_id, '_codfaa_winner_user', true );
			}
		}

		if ( $winner_user !== $user_id ) {
			$this->send_error_response( __( 'Only the winning bidder can claim this prize.', 'codex-ajax-auctions' ), '', 403 );
		}

		if ( 'yes' === get_post_meta( $auction_id, '_codfaa_winner_claimed', true ) ) {
			$this->send_error_response( __( 'You have already claimed this prize.', 'codex-ajax-auctions' ) );
		}

		$prize_product_id = (int) get_post_meta( $auction_id, Auction_Post_Type::META_PRODUCT_ID, true );
		$bid_product_id   = (int) get_post_meta( $auction_id, Auction_Post_Type::META_BID_PRODUCT_ID, true );

		if ( ! $prize_product_id ) {
			$this->send_error_response( __( 'Prize product is not configured for this auction.', 'codex-ajax-auctions' ) );
		}

		$totals = $this->get_user_totals( $auction_id, $user_id );
		$winner_total_minor   = (int) $totals['total_minor'];
		$winner_total_display   = wc_price( $winner_total_minor / 100 );
		$winner_total_plain     = html_entity_decode( wp_strip_all_tags( $winner_total_display ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		$winner_bid_count       = (int) $totals['bid_count'];
		$claim_minor            = (int) round( self::CLAIM_PRICE * 100 );
		$combined_minor         = $winner_total_minor + $claim_minor;
		$combined_display       = wc_price( $combined_minor / 100 );
		$combined_plain         = html_entity_decode( wp_strip_all_tags( $combined_display ), ENT_QUOTES, get_bloginfo( 'charset' ) );

		if ( ! function_exists( 'wc_load_cart' ) ) {
			$this->send_error_response( __( 'WooCommerce cart is unavailable.', 'codex-ajax-auctions' ) );
		}

		wc_load_cart();

		if ( null === WC()->cart ) {
			$this->send_error_response( __( 'Unable to load your cart.', 'codex-ajax-auctions' ) );
		}

		$added_prize = WC()->cart->add_to_cart(
			$prize_product_id,
			1,
			0,
			array(),
			array(
				'codfaa_auction_id' => $auction_id,
				'codfaa_prize_claim' => 1,
			)
		);

		if ( ! $added_prize ) {
			$this->send_error_response( __( 'Unable to add the prize product to your cart. Please try again.', 'codex-ajax-auctions' ) );
		}

		if ( $bid_product_id && $winner_bid_count > 0 ) {
			$bid_added = WC()->cart->add_to_cart(
				$bid_product_id,
				$winner_bid_count,
				0,
				array(),
				array(
					'codfaa_auction_id' => $auction_id,
					'codfaa_bid_fee'    => 1,
				)
			);

			if ( ! $bid_added ) {
				$this->send_error_response( __( 'Unable to add bid fees to your cart. Please try again.', 'codex-ajax-auctions' ) );
			}
		}

		update_post_meta( $auction_id, '_codfaa_winner_claimed', 'yes' );
		update_post_meta( $auction_id, '_codfaa_winner_claimed_at', current_time( 'mysql' ) );

		wp_send_json_success(
			array(
				'winnerClaimed' => true,
				'redirect'      => wc_get_checkout_url(),
				'claimLabel'    => sprintf( __( 'Claim prize & pay %s', 'codex-ajax-auctions' ), $combined_plain ),
				'winnerSummary' => array(
					'summary' => sprintf( __( 'You will pay %1$s total (%2$s in bid fees across %3$d bids).', 'codex-ajax-auctions' ), $combined_display, $winner_total_display, max( 1, $winner_bid_count ) ),
					'variant' => 'win',
				),
				'message'       => sprintf( __( 'Prize added to your cart. You will pay %s.', 'codex-ajax-auctions' ), $combined_plain ),
			)
		);
	}

	/**
	 * Guests must log in before claiming the prize.
	 */
	public function handle_claim_guest() {
		$this->send_error_response(
			__( 'Please log in to claim the prize.', 'codex-ajax-auctions' ),
			wp_login_url( wp_get_referer() ? wp_get_referer() : home_url() ),
			401
		);
	}

	/**
	 * Reject bids from guests.
	 */
	public function handle_bid_guest() {
		$this->send_error_response(
			__( 'Please log in before placing a bid.', 'codex-ajax-auctions' ),
			wp_login_url( wp_get_referer() ? wp_get_referer() : home_url() ),
			401
		);
	}

	/**
	 * Record the bid and update participant totals/meta.
	 */
	private function record_bid( $auction_id, $user_id, $amount ) {
		global $wpdb;

		$participant = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, total_reserved FROM {$wpdb->prefix}codfaa_auction_participants WHERE auction_id = %d AND user_id = %d AND status = 'active'",
				$auction_id,
				$user_id
			),
			ARRAY_A
		);

		if ( ! $participant ) {
			return new WP_Error( 'codfaa_not_registered', __( 'You must register before bidding.', 'codex-ajax-auctions' ) );
		}

		$bid_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}codfaa_auction_bids WHERE auction_id = %d AND user_id = %d",
				$auction_id,
				$user_id
			)
		);

		$inserted = $wpdb->insert(
			$wpdb->prefix . 'codfaa_auction_bids',
			array(
				'auction_id'      => $auction_id,
				'user_id'         => $user_id,
				'bid_number'      => $bid_count + 1,
				'reserved_amount' => $amount,
				'created_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%d', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'codfaa_bid_insert_failed', __( 'Unable to record your bid. Please try again.', 'codex-ajax-auctions' ) );
		}

		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}codfaa_auction_participants SET total_reserved = total_reserved + %d WHERE auction_id = %d AND user_id = %d AND status = 'active'",
				$amount,
				$auction_id,
				$user_id
			)
		);

		if ( false === $updated ) {
			return new WP_Error( 'codfaa_participant_update_failed', __( 'Unable to update your bid totals.', 'codex-ajax-auctions' ) );
		}

		$current_timestamp = current_time( 'timestamp', true );

		update_post_meta( $auction_id, '_codfaa_last_bid_time', $current_timestamp );
		update_post_meta( $auction_id, '_codfaa_last_bid_user', $user_id );
		update_post_meta( $auction_id, '_codfaa_last_bid_amount', $amount );

		$total_minor = (int) $participant['total_reserved'] + $amount;

		return array(
			'bid_count'   => $bid_count + 1,
			'total_minor' => $total_minor,
		);
	}

	/**
	 * Build a comprehensive status payload for an auction.
	 */
	private function build_status_payload( $auction_id, $user_id = 0 ) {
		global $wpdb;

		$auction = get_post( $auction_id );

		if ( ! $auction || Auction_Post_Type::POST_TYPE !== $auction->post_type ) {
			return new WP_Error( 'codfaa_invalid_auction', __( 'Unable to locate this auction.', 'codex-ajax-auctions' ) );
		}

		$user_registered = $user_id ? $this->user_is_registered( $auction_id, $user_id ) : false;
		$registration_pending = ( $user_id && ! $user_registered ) ? $this->user_has_pending_registration( $auction_id, $user_id ) : false;

		$state        = $this->get_auction_state( $auction_id );
		$participants = $this->get_participant_count( $auction_id );
		$required     = $this->get_required_participants( $auction_id );
		$ready        = $this->is_ready( $auction_id );

		$timer_seconds = (int) get_post_meta( $auction_id, Auction_Post_Type::META_TIMER_SECONDS, true );
		$last_bid_user = (int) get_post_meta( $auction_id, '_codfaa_last_bid_user', true );
		$last_bid_time = (int) get_post_meta( $auction_id, '_codfaa_last_bid_time', true );
		$ready_at      = (int) get_post_meta( $auction_id, '_codfaa_ready_at', true );
		$go_live_at    = (int) get_post_meta( $auction_id, '_codfaa_go_live_at', true );

		$server_time = current_time( 'timestamp', true );

		$prelive_remaining = 0;

		if ( $ready_at && $go_live_at ) {
			if ( self::STATE_UPCOMING === $state && $server_time >= $go_live_at ) {
				$this->set_auction_state( $auction_id, self::STATE_LIVE );
				$state = self::STATE_LIVE;
			} elseif ( $server_time < $go_live_at ) {
				$prelive_remaining = max( 0, $go_live_at - $server_time );
			}
		}

		$default_ready_time = (int) apply_filters( 'codfaa_ready_countdown_seconds', self::READY_COUNTDOWN, $auction_id );
		$prelive_duration  = 0;

		if ( $ready_at && $go_live_at && $go_live_at > $ready_at ) {
			$prelive_duration = $go_live_at - $ready_at;
		} elseif ( $prelive_remaining > 0 ) {
			$prelive_duration = max( $prelive_remaining, $default_ready_time );
		} else {
			$prelive_duration = max( 0, $default_ready_time );
		}

		if ( $timer_seconds > 0 ) {
			if ( $last_bid_time ) {
				$elapsed   = max( 0, $server_time - $last_bid_time );
				$remaining = max( 0, $timer_seconds - $elapsed );
			} else {
				$remaining = $timer_seconds;
			}
		} else {
			$remaining = 0;
		}

		$ended = ( self::STATE_ENDED === $state );

		if ( ! $ended && self::STATE_LIVE === $state && $timer_seconds > 0 && $remaining <= 0 && $last_bid_user ) {
			$ended = true;
			$this->set_auction_state( $auction_id, self::STATE_ENDED );
			$state = self::STATE_ENDED;
			$this->maybe_finalize_auction( $auction_id, $last_bid_user );
		}

		if ( $ended ) {
			$remaining = 0;
		}

		$winner_user        = (int) get_post_meta( $auction_id, '_codfaa_winner_user', true );
		$winner_total_minor = (int) get_post_meta( $auction_id, '_codfaa_winner_total_minor', true );
		$winner_bid_count   = (int) get_post_meta( $auction_id, '_codfaa_winner_bid_count', true );
		$winner_claimed     = 'yes' === get_post_meta( $auction_id, '_codfaa_winner_claimed', true );

		if ( $ended && ! $winner_user && $last_bid_user ) {
			$winner_user = $last_bid_user;
			$this->maybe_finalize_auction( $auction_id, $winner_user );
			$winner_total_minor = (int) get_post_meta( $auction_id, '_codfaa_winner_total_minor', true );
			$winner_bid_count   = (int) get_post_meta( $auction_id, '_codfaa_winner_bid_count', true );
		}

		$progress_percent = $required > 0 ? min( 100, ( $participants / max( 1, $required ) ) * 100 ) : ( $participants > 0 ? 100 : 0 );
		$participant_label = sprintf(
			__( 'Now Registered %s%%. Share this auction to reach 100%%', 'codex-ajax-auctions' ),
			number_format_i18n( (int) round( $progress_percent ) )
		);

		$last_bidder_display = $this->get_masked_user_label( $last_bid_user );

		$user_totals       = $user_id ? $this->get_user_totals( $auction_id, $user_id ) : array( 'total_minor' => 0, 'bid_count' => 0 );
		$user_bid_count    = (int) $user_totals['bid_count'];
		$user_total_minor  = (int) $user_totals['total_minor'];
		$user_total_display = wc_price( $user_total_minor / 100 );

		$winner_total_display = wc_price( $winner_total_minor / 100 );
		$winner_total_plain   = html_entity_decode( wp_strip_all_tags( $winner_total_display ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		$claim_minor          = (int) round( self::CLAIM_PRICE * 100 );
		$combined_minor       = $winner_total_minor + $claim_minor;
		$combined_display     = wc_price( $combined_minor / 100 );
		$combined_plain       = html_entity_decode( wp_strip_all_tags( $combined_display ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		$user_is_winner       = $winner_user && $user_id && ( (int) $winner_user === (int) $user_id );

		if ( ! $ended && self::STATE_LIVE !== $state && $prelive_remaining > 0 ) {
			$status_message = sprintf( __( 'Auction starts in %s.', 'codex-ajax-auctions' ), $this->format_countdown( $prelive_remaining ) );
			$status_variant = 'info';
		} elseif ( $last_bid_user ) {
			if ( $user_id && (int) $last_bid_user === (int) $user_id ) {
				$status_message = __( 'Last bidder: You', 'codex-ajax-auctions' );
				$status_variant = 'success';
			} elseif ( $user_registered && $user_id ) {
				$status_message = $last_bidder_display
					? sprintf( __( 'Last bidder: %s', 'codex-ajax-auctions' ), $last_bidder_display )
					: __( 'Last bidder: —', 'codex-ajax-auctions' );
				$status_variant = 'warning';
			} else {
				$status_message = $last_bidder_display
					? sprintf( __( 'Last bidder: %s', 'codex-ajax-auctions' ), $last_bidder_display )
					: __( 'Last bidder: —', 'codex-ajax-auctions' );
				$status_variant = 'info';
			}
		} else {
			if ( $required > 0 && ! $ready ) {
				$status_message = sprintf(
					__( 'Waiting for participants (%s%% full).', 'codex-ajax-auctions' ),
					number_format_i18n( (int) round( $progress_percent ) )
				);
				$status_variant = 'muted';
			} else {
				$status_message = __( 'No bids yet. Be the first to bid!', 'codex-ajax-auctions' );
				$status_variant = 'muted';
			}
		}

		if ( $ended ) {
			if ( $user_is_winner ) {
				$status_message = __( 'Auction ended. You won!', 'codex-ajax-auctions' );
				$status_variant = 'success';
			} elseif ( $last_bidder_display ) {
				$status_message = sprintf( __( 'Auction ended. Winner: %s', 'codex-ajax-auctions' ), $last_bidder_display );
				$status_variant = 'info';
			} else {
				$status_message = __( 'Auction ended.', 'codex-ajax-auctions' );
				$status_variant = 'info';
			}
		}

		if ( $registration_pending && ! $user_registered && ! $ended ) {
			$status_message = __( 'Registration received. Awaiting admin confirmation.', 'codex-ajax-auctions' );
			$status_variant = 'warning';
		}

		$winner_summary_text = '';

		if ( $winner_user ) {
			if ( $user_is_winner ) {
				$winner_summary_text = sprintf(
					__( 'You will pay %1$s total (%2$s in bid fees across %3$d bids).', 'codex-ajax-auctions' ),
					$combined_display,
					$winner_total_display,
					max( 1, $winner_bid_count )
				);
			} elseif ( $last_bidder_display ) {
				$winner_summary_text = sprintf(
					__( 'Winner: %1$s (%2$s total).', 'codex-ajax-auctions' ),
					$last_bidder_display,
					$combined_display
				);
			}
		}

		$winner_summary = $winner_summary_text ? array(
			'summary' => $winner_summary_text,
			'variant' => $user_is_winner ? 'win' : 'lost',
		) : array();

		return array(
			'participants'        => $participants,
			'required'            => $required,
			'progressPercent'     => $progress_percent,
			'participantLabel'    => $participant_label,
			'ready'               => $ready,
			'readyTimestamp'      => $ready_at,
			'goLiveTimestamp'     => $go_live_at,
			'preliveRemaining'    => $prelive_remaining,
			'preliveDuration'    => $prelive_duration,
			'userRegistered'      => $user_registered,
			'registrationPending' => $registration_pending,
			'userRegistered'      => $user_registered,
			'state'               => $ended ? self::STATE_ENDED : $state,
			'timerSeconds'        => $timer_seconds,
			'remaining'           => $remaining,
			'lastBidUser'         => $last_bid_user,
			'lastBidderDisplay'   => $last_bidder_display,
			'userBidCount'        => $user_bid_count,
			'userTotalMinor'      => $user_total_minor,
			'userTotalDisplay'    => $user_total_display,
			'canBid'              => ( self::STATE_LIVE === $state && ! $ended && $user_registered ),
			'statusMessage'       => $status_message,
			'statusVariant'       => $status_variant,
			'ended'               => $ended,
			'winnerUserId'        => $winner_user,
			'userIsWinner'        => $user_is_winner,
			'winnerTotalMinor'    => $winner_total_minor,
			'winnerTotalDisplay'  => $winner_total_display,
			'winnerBidCount'      => $winner_bid_count,
			'winnerClaimed'       => $winner_claimed,
			'winnerSummary'       => $winner_summary,
			'claimLabel'          => sprintf( __( 'Claim prize & pay %s', 'codex-ajax-auctions' ), $combined_plain ),
			'recentBidders'       => $this->get_recent_bidders( $auction_id ),
		);
	}

	/**
	 * Finalize auction metadata when a winner is confirmed.
	 */
	private function maybe_finalize_auction( $auction_id, $winner_user ) {
		$existing_winner = (int) get_post_meta( $auction_id, '_codfaa_winner_user', true );

		if ( $existing_winner ) {
			return;
		}

		$totals = $this->get_user_totals( $auction_id, $winner_user );

		update_post_meta( $auction_id, '_codfaa_winner_user', $winner_user );
		update_post_meta( $auction_id, '_codfaa_winner_total_minor', (int) $totals['total_minor'] );
		update_post_meta( $auction_id, '_codfaa_winner_bid_count', (int) $totals['bid_count'] );
		update_post_meta( $auction_id, '_codfaa_winner_recorded_at', current_time( 'mysql' ) );
		update_post_meta( $auction_id, '_codfaa_state', self::STATE_ENDED );
	}

	/**
	 * Retrieve total reserved amount and bid count for a user.
	 */
	private function get_user_totals( $auction_id, $user_id ) {
		global $wpdb;

		$total_minor = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT total_reserved FROM {$wpdb->prefix}codfaa_auction_participants WHERE auction_id = %d AND user_id = %d",
				$auction_id,
				$user_id
			)
		);

		$bid_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}codfaa_auction_bids WHERE auction_id = %d AND user_id = %d",
				$auction_id,
				$user_id
			)
		);

		return array(
			'total_minor' => $total_minor,
			'bid_count'   => $bid_count,
		);
	}

	/**
	 * Determine if the current user is registered.
	 */
	private function user_is_registered( $auction_id, $user_id ) {
		if ( ! $auction_id || ! $user_id ) {
			return false;
		}

		global $wpdb;

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}codfaa_auction_participants WHERE auction_id = %d AND user_id = %d LIMIT 1",
				$auction_id,
				$user_id
			)
		);

		return ! empty( $exists );
	}

	/**
	 * Count registered participants for an auction.
	 */
	private function get_participant_count( $auction_id ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}codfaa_auction_participants WHERE auction_id = %d AND status = 'active'",
				$auction_id
			)
		);
	}

	/**
	 * Fetch the required participants for an auction.
	 */
	private function get_required_participants( $auction_id ) {
		return (int) get_post_meta( $auction_id, Auction_Post_Type::META_REQUIRED_PARTICIPANTS, true );
	}

	/**
	 * Determine if the auction has met participation requirements.
	 */
	private function is_ready( $auction_id ) {
		$required     = $this->get_required_participants( $auction_id );
		$participants = $this->get_participant_count( $auction_id );

		return $required > 0 ? ( $participants >= $required ) : true;
	}

	/**
	 * Retrieve the persisted auction state.
	 */
	private function get_auction_state( $auction_id ) {
		$state = get_post_meta( $auction_id, '_codfaa_state', true );

		if ( ! $state ) {
			$winner_user = (int) get_post_meta( $auction_id, '_codfaa_winner_user', true );

			$state = $winner_user ? self::STATE_ENDED : self::STATE_UPCOMING;
			$this->set_auction_state( $auction_id, $state );
		}

		return $state;
	}

	/**
	 * Persist the auction state meta.
	 */
	private function set_auction_state( $auction_id, $state ) {
		update_post_meta( $auction_id, '_codfaa_state', $state );
	}

	/**
	 * Check whether the user has a pending registration order awaiting admin confirmation.
	 *
	 * @param int $auction_id Auction ID.
	 * @param int $user_id    User ID.
	 * @return bool
	 */
	private function user_has_pending_registration( $auction_id, $user_id ) {
		if ( ! $auction_id || ! $user_id ) {
			return false;
		}

		$meta_key      = '_codfaa_pending_registration_' . absint( $auction_id );
		$pending_order = (int) get_user_meta( $user_id, $meta_key, true );

		if ( $pending_order ) {
			$order = wc_get_order( $pending_order );

			if ( $order instanceof WC_Order && in_array( $order->get_status(), array( 'pending', 'on-hold' ), true ) ) {
				return true;
			}

			delete_user_meta( $user_id, $meta_key );
		}

		$orders = array();

		if ( function_exists( 'wc_get_orders' ) ) {
			$orders = wc_get_orders(
				array(
					'customer_id' => $user_id,
					'limit'       => 1,
					'return'      => 'ids',
					'status'      => array( 'pending', 'on-hold' ),
					'type'        => 'shop_order',
					'meta_key'    => '_codfaa_return_auction',
					'meta_value'  => $auction_id,
				)
			);
		}

		if ( ! empty( $orders ) ) {
			return true;
		}

		global $wpdb;

		$sql = <<<SQL
SELECT oi.order_id
FROM {$wpdb->prefix}woocommerce_order_items AS oi
INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS oim
	ON oi.order_item_id = oim.order_item_id
INNER JOIN {$wpdb->prefix}posts AS orders
	ON oi.order_id = orders.ID
INNER JOIN {$wpdb->prefix}postmeta AS customer
	ON orders.ID = customer.post_id AND customer.meta_key = '_customer_user'
WHERE oim.meta_key = '_codfaa_auction_id'
	AND oim.meta_value = %d
	AND customer.meta_value = %d
	AND orders.post_status IN ( 'wc-pending', 'wc-on-hold', 'pending', 'on-hold' )
LIMIT 1
SQL;

		$order_id = $wpdb->get_var(
			$wpdb->prepare(
				$sql,
				$auction_id,
				$user_id
			)
		);

		return ! empty( $order_id );
	}

	/**
	 * Return recent bidders with totals.
	 *
	 * @param int $auction_id Auction ID.
	 * @param int $limit      Number of bidders.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_recent_bidders( $auction_id, $limit = 5 ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, reserved_amount, created_at
				FROM {$wpdb->prefix}codfaa_auction_bids
				WHERE auction_id = %d
				ORDER BY created_at DESC
				LIMIT %d",
				$auction_id,
				absint( $limit )
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$results = array();

		foreach ( $rows as $row ) {
			$user_id      = (int) $row['user_id'];
			$amount_minor = (int) $row['reserved_amount'];
			$time_utc     = ! empty( $row['created_at'] ) ? mysql2date( 'c', $row['created_at'], true ) : '';
			$time_display = ! empty( $row['created_at'] ) ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['created_at'], true ) : '';

			$results[] = array(
				'name'         => $this->get_masked_user_label( $user_id ),
				'totalDisplay' => wc_price( $amount_minor / 100 ),
				'timestamp'    => $time_display,
				'timestampRaw' => $time_utc,
			);
		}

		return $results;
	}

	/**
	 * Return an anonymized label for a specific user.
	 */
	private function get_masked_user_label( $user_id ) {
		if ( ! $user_id ) {
			return __( 'Bidder', 'codex-ajax-auctions' );
		}

		$user = get_userdata( $user_id );
		$name = '';

		if ( $user ) {
			$name = $user->display_name ? $user->display_name : $user->user_login;
		} else {
			$name = sprintf( __( 'User #%d', 'codex-ajax-auctions' ), $user_id );
		}

		return $this->mask_display_name( $name );
	}

	/**
	 * Mask a name by keeping its edges visible.
	 */
	private function mask_display_name( $name ) {
		$name = trim( (string) $name );

		if ( '' === $name ) {
			return __( 'Bidder', 'codex-ajax-auctions' );
		}

		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $name ) : strlen( $name );

		if ( $length <= 2 ) {
			$first = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1 ) : substr( $name, 0, 1 );
			$first = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $first ) : strtoupper( $first );
			return $first . '****';
		}

		$first = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1 ) : substr( $name, 0, 1 );
		$tail_length = min( 2, $length - 1 );
		$last  = function_exists( 'mb_substr' ) ? mb_substr( $name, -$tail_length ) : substr( $name, -$tail_length );
		$first = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $first ) : strtoupper( $first );
		$last  = function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $last ) : strtoupper( $last );

		return sprintf( '%s****%s', $first, $last );
	}


	/**
	 * Send JSON error response and halt execution.
	 */
	private function send_error_response( $message, $redirect = '', $status = 400 ) {
		$data = array( 'message' => $message );

		if ( $redirect ) {
			$data['redirect'] = $redirect;
		}

		wp_send_json_error( $data, $status );
	}

	/**
	 * When participant threshold is met, start countdown and notify users.
	 *
	 * @param int $auction_id Auction ID.
	 */
	public function maybe_schedule_ready_state( $auction_id ) {
		$auction_id = absint( $auction_id );

		if ( ! $auction_id ) {
			return;
		}

		$required = $this->get_required_participants( $auction_id );

		if ( $required <= 0 ) {
			return;
		}

		$participants = $this->get_participant_count( $auction_id );

		if ( $participants < $required ) {
			return;
		}

		$ready_at = (int) get_post_meta( $auction_id, '_codfaa_ready_at', true );

		if ( $ready_at ) {
			return;
		}

		$ready_at    = current_time( 'timestamp', true );
		$countdown   = (int) apply_filters( 'codfaa_ready_countdown_seconds', self::READY_COUNTDOWN, $auction_id );
		$countdown   = max( 60, $countdown );
		$go_live_at  = $ready_at + $countdown;

		update_post_meta( $auction_id, '_codfaa_ready_at', $ready_at );
		update_post_meta( $auction_id, '_codfaa_go_live_at', $go_live_at );
		delete_post_meta( $auction_id, '_codfaa_ready_notified' );

		$this->notify_ready_participants( $auction_id, $go_live_at );
	}

	/**
	 * Email registered participants when countdown begins.
	 */
	private function notify_ready_participants( $auction_id, $go_live_at ) {
		if ( 'yes' === get_post_meta( $auction_id, '_codfaa_ready_notified', true ) ) {
			return;
		}

		$user_ids = $this->get_participant_user_ids( $auction_id );

		if ( empty( $user_ids ) ) {
			return;
		}

		$title      = get_the_title( $auction_id );
		$permalink  = get_permalink( $auction_id );
		$human_time = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $go_live_at );
		$subject    = sprintf( __( 'Auction "%s" starts soon', 'codex-ajax-auctions' ), $title );
		$minutes    = max( 1, floor( ( $go_live_at - current_time( 'timestamp', true ) ) / 60 ) );
		$message    = sprintf( __( "The auction \"%1\$s\" will start in about %2\$d minutes (at %3\$s).\nVisit: %4\$s", 'codex-ajax-auctions' ), $title, $minutes, $human_time, $permalink );
		$headers    = array( 'Content-Type: text/plain; charset=UTF-8' );

		foreach ( $user_ids as $user_id ) {
			$user = get_userdata( $user_id );

			if ( ! $user || ! $user->user_email ) {
				continue;
			}

			wp_mail( $user->user_email, $subject, $message, $headers );
		}

		update_post_meta( $auction_id, '_codfaa_ready_notified', 'yes' );
	}

	/**
	 * Fetch participant user IDs for an auction.
	 */
	private function get_participant_user_ids( $auction_id ) {
		global $wpdb;

		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT user_id FROM {$wpdb->prefix}codfaa_auction_participants WHERE auction_id = %d AND status = 'active'",
				$auction_id
			)
		);
	}

	/**
	 * Format seconds into mm:ss string.
	 */
	private function format_countdown( $seconds ) {
		$seconds = max( 0, (int) $seconds );
		$minutes = floor( $seconds / 60 );
		$remainder = $seconds % 60;

		return sprintf( '%02d:%02d', $minutes, $remainder );
	}

	/**
	 * Ensure prize pricing stays at the claim rate while bid fees remain.
	 *
	 * @param \WC_Cart $cart Cart instance.
	 */
	public function maybe_enforce_cart_prices( $cart ) {
		if ( ! is_a( $cart, '\\WC_Cart' ) || $cart->is_empty() ) {
			return;
		}

		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		$auctions_with_fees = array();

		foreach ( $cart->get_cart() as $item ) {
			if ( empty( $item['codfaa_bid_fee'] ) || empty( $item['codfaa_auction_id'] ) ) {
				continue;
			}

			$auction_id = absint( $item['codfaa_auction_id'] );

			if ( $auction_id ) {
				$auctions_with_fees[ $auction_id ] = true;
			}
		}

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( empty( $cart_item['codfaa_prize_claim'] ) || empty( $cart_item['codfaa_auction_id'] ) ) {
				if ( isset( $cart_item['codfaa_original_price'] ) && isset( $cart->cart_contents[ $cart_item_key ] ) ) {
					$product = $cart_item['data'];

					if ( $product instanceof \WC_Product ) {
						$product->set_price( $cart_item['codfaa_original_price'] );
					}

					unset( $cart->cart_contents[ $cart_item_key ]['codfaa_original_price'] );
				}
				continue;
			}

			$auction_id = absint( $cart_item['codfaa_auction_id'] );
			$product    = $cart_item['data'];

			if ( ! $auction_id || ! $product instanceof \WC_Product ) {
				continue;
			}

			if ( ! empty( $auctions_with_fees[ $auction_id ] ) ) {
				if ( ! isset( $cart->cart_contents[ $cart_item_key ]['codfaa_original_price'] ) ) {
					$cart->cart_contents[ $cart_item_key ]['codfaa_original_price'] = (float) $product->get_price();
				}

				$product->set_price( self::CLAIM_PRICE );
			} elseif ( isset( $cart->cart_contents[ $cart_item_key ]['codfaa_original_price'] ) ) {
				$product->set_price( $cart->cart_contents[ $cart_item_key ]['codfaa_original_price'] );
				unset( $cart->cart_contents[ $cart_item_key ]['codfaa_original_price'] );
			}
		}
	}
}
