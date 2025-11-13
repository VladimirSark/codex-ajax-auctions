<?php
/**
 * Auction shortcode renderer.
 *
 * @package CodexAjaxAuctions
 */

namespace Codfaa\Auctions;

defined( 'ABSPATH' ) || exit;

use WP_Post;
use WC_Product;

/**
 * Provides shortcode for rendering auction details.
 */
class Auction_Shortcode {

	/**
	 * Shortcode tag.
	 */
	private const SHORTCODE = 'codfaa_auction';

	/**
	 * Default pre-live countdown (seconds).
	 */
	private const READY_COUNTDOWN = 600;

	/**
	 * Register hooks.
	 */
	public function boot() {
		add_shortcode( self::SHORTCODE, array( $this, 'render_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue frontend assets when shortcode is used.
	 */
	public function enqueue_assets() {
		if ( ! is_singular() ) {
			return;
		}

		global $post;

		if ( ! $post instanceof WP_Post ) {
			return;
		}

		if ( has_shortcode( $post->post_content, self::SHORTCODE ) ) {
			wp_enqueue_style(
				'codfaa-auction-widget',
				CODFAA_PLUGIN_URL . 'public/css/auction-widget.css',
				array(),
				\Codex_Ajax_Auctions::VERSION
			);
			wp_enqueue_script( 'codfaa-auction-registration' );
		}
	}

	/**
	 * Render the auction shortcode output.
	 *
	 * @param array<string,mixed> $atts Shortcode attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			self::SHORTCODE
		);

		$auction_id = (int) $atts['id'];

		if ( ! $auction_id ) {
			return '';
		}

		$auction = get_post( $auction_id );

		if ( ! $auction || Auction_Post_Type::POST_TYPE !== $auction->post_type ) {
			return '';
		}

		$product_id      = (int) get_post_meta( $auction_id, Auction_Post_Type::META_PRODUCT_ID, true );
		$registration_id = (int) get_post_meta( $auction_id, Auction_Post_Type::META_REGISTRATION_ID, true );
		$bid_product_id  = (int) get_post_meta( $auction_id, Auction_Post_Type::META_BID_PRODUCT_ID, true );
		$required        = (int) get_post_meta( $auction_id, Auction_Post_Type::META_REQUIRED_PARTICIPANTS, true );
		$timer           = (int) get_post_meta( $auction_id, Auction_Post_Type::META_TIMER_SECONDS, true );

		$product           = $product_id ? wc_get_product( $product_id ) : null;
		$registration_prod = $registration_id ? wc_get_product( $registration_id ) : null;
		$bid_product       = $bid_product_id ? wc_get_product( $bid_product_id ) : null;

		$is_logged_in  = is_user_logged_in();
		$current_user = $is_logged_in ? get_current_user_id() : 0;
		$is_registered = $is_logged_in ? $this->user_is_registered( $auction_id, $current_user ) : false;
		$registration_pending = false;

		if ( $is_logged_in && ! $is_registered ) {
			$registration_pending = $this->user_has_pending_registration( $auction_id, $current_user );
		}

		$user_stats    = $is_logged_in ? $this->get_user_stats( $auction_id, $current_user ) : array(
			'bid_count'     => 0,
			'total_minor'   => 0,
			'total_display' => wc_price( 0 ),
		);

		$initial_status = $this->get_initial_status_payload( $auction_id, $current_user, $required, $timer, $is_registered );

		$participant_count        = isset( $initial_status['participants'] ) ? (int) $initial_status['participants'] : 0;
		$progress_percent         = isset( $initial_status['progressPercent'] ) ? (float) $initial_status['progressPercent'] : 0;
		$participant_label        = isset( $initial_status['participantLabel'] ) ? $initial_status['participantLabel'] : '';
		$initial_remaining        = isset( $initial_status['remaining'] ) ? (int) $initial_status['remaining'] : $timer;
		$initial_status_message   = isset( $initial_status['statusMessage'] ) ? $initial_status['statusMessage'] : '';
		$initial_status_variant   = isset( $initial_status['statusVariant'] ) ? $initial_status['statusVariant'] : 'muted';
		$last_bid_user            = isset( $initial_status['lastBidUser'] ) ? (int) $initial_status['lastBidUser'] : 0;
		$last_bid_display         = isset( $initial_status['lastBidderDisplay'] ) ? $initial_status['lastBidderDisplay'] : '';
		$ready                    = ! empty( $initial_status['ready'] );
		$ended                    = ! empty( $initial_status['ended'] );
		$current_state            = get_post_meta( $auction_id, '_codfaa_state', true );

		if ( ! $current_state ) {
			$current_state = $ended ? Bidding_Service::STATE_ENDED : Bidding_Service::STATE_UPCOMING;
		}

		if ( $registration_pending ) {
			$initial_status_message = __( 'Registration received. Awaiting admin confirmation.', 'codex-ajax-auctions' );
			$initial_status_variant = 'warning';
		}

		$ready_at_meta      = isset( $initial_status['readyTimestamp'] ) ? (int) $initial_status['readyTimestamp'] : (int) get_post_meta( $auction_id, '_codfaa_ready_at', true );
		$go_live_at_meta    = isset( $initial_status['goLiveTimestamp'] ) ? (int) $initial_status['goLiveTimestamp'] : (int) get_post_meta( $auction_id, '_codfaa_go_live_at', true );
		$prelive_remaining  = isset( $initial_status['preliveRemaining'] ) ? (int) $initial_status['preliveRemaining'] : 0;
		$default_ready_time = (int) apply_filters( 'codfaa_ready_countdown_seconds', self::READY_COUNTDOWN, $auction_id );
		$prelive_duration   = 0;

		if ( $ready_at_meta && $go_live_at_meta && $go_live_at_meta > $ready_at_meta ) {
			$prelive_duration = $go_live_at_meta - $ready_at_meta;
		} elseif ( $prelive_remaining > 0 ) {
			$prelive_duration = max( $prelive_remaining, $default_ready_time );
		} else {
			$prelive_duration = max( 0, $default_ready_time );
		}

		$winner_user        = (int) get_post_meta( $auction_id, '_codfaa_winner_user', true );
		$winner_total_minor = (int) get_post_meta( $auction_id, '_codfaa_winner_total_minor', true );
		$winner_bid_count   = (int) get_post_meta( $auction_id, '_codfaa_winner_bid_count', true );
		$winner_claimed     = 'yes' === get_post_meta( $auction_id, '_codfaa_winner_claimed', true );

		if ( $ended && ! $winner_user && $last_bid_user ) {
			$winner_user = $last_bid_user;
		}

		if ( $winner_user && 0 === $winner_total_minor ) {
			$totals             = $this->get_user_stats( $auction_id, $winner_user );
			$winner_total_minor = (int) $totals['total_minor'];
			$winner_bid_count   = (int) $totals['bid_count'];
		}

		$winner_total_display = wc_price( $winner_total_minor / 100 );
		$winner_total_plain   = html_entity_decode( wp_strip_all_tags( $winner_total_display ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		$claim_minor          = (int) round( Bidding_Service::CLAIM_PRICE * 100 );
		$combined_minor       = $winner_total_minor + $claim_minor;
		$combined_display     = wc_price( $combined_minor / 100 );
		$combined_plain       = html_entity_decode( wp_strip_all_tags( $combined_display ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		$user_is_winner       = $winner_user && $current_user && ( (int) $winner_user === (int) $current_user );

		if ( ! $ended ) {
			$winner_summary = '';
		} elseif ( $user_is_winner ) {
			$winner_summary = sprintf(
				__( 'You will pay %1$s total (%2$s in bid fees across %3$d bids).', 'codex-ajax-auctions' ),
				$combined_display,
				$winner_total_display,
				max( 1, $winner_bid_count )
			);
		} elseif ( $winner_user && $last_bid_display ) {
			$winner_summary = sprintf(
				__( 'Winner: %1$s (%2$s total).', 'codex-ajax-auctions' ),
				$last_bid_display,
				$combined_display
			);
		} else {
			$winner_summary = '';
		}

	$claim_label               = sprintf( __( 'Claim prize & pay %s', 'codex-ajax-auctions' ), $combined_plain );
	$winner_claim_total_display = $combined_display;
		$recent_bidders = $this->get_recent_bidders( $auction_id );

		$can_bid = ( Bidding_Service::STATE_LIVE === $current_state && $is_registered && ! $ended );

		$terms_content_raw = (string) get_option( Admin_Dashboard_Service::OPTION_TERMS_CONTENT, '' );
		$terms_content     = $terms_content_raw ? wpautop( wp_kses_post( $terms_content_raw ) ) : '';

	$context = array(
			'auction'                  => $auction,
			'product'                  => $product instanceof WC_Product ? $product : null,
			'product_link'             => $product instanceof WC_Product ? $product->get_permalink() : '',
			'registration'             => $registration_prod instanceof WC_Product ? $registration_prod : null,
			'bid_product'              => $bid_product instanceof WC_Product ? $bid_product : null,
			'is_registered'            => $is_registered,
			'is_logged_in'             => $is_logged_in,
			'registration_pending'     => $registration_pending,
			'user_bid_count'           => isset( $user_stats['bid_count'] ) ? (int) $user_stats['bid_count'] : 0,
			'user_total_minor'         => isset( $user_stats['total_minor'] ) ? (int) $user_stats['total_minor'] : 0,
			'user_total_display'       => isset( $user_stats['total_display'] ) ? $user_stats['total_display'] : wc_price( 0 ),
			'display_url'              => $this->get_display_url(),
			'can_bid'                  => $can_bid,
			'ready'                    => $ready,
			'ended'                    => $ended,
			'required'                 => $required,
			'timer'                    => $timer,
			'participant_count'        => $participant_count,
			'participant_label'        => $participant_label,
			'progress_percent'         => $progress_percent,
			'prelive_remaining'        => $prelive_remaining,
			'prelive_duration'         => $prelive_duration,
			'prelive_initial_formatted'=> $this->format_seconds( max( 0, $prelive_remaining ) ),
			'go_live_at'               => $go_live_at_meta,
			'initial_remaining'        => $initial_remaining,
			'initial_remaining_formatted' => $this->format_seconds( $initial_remaining ),
			'last_bid_user'            => $last_bid_user,
			'last_bid_display'         => $last_bid_display,
			'initial_status_message'   => $initial_status_message,
			'initial_status_variant'   => $initial_status_variant,
			'user_is_winner'           => $user_is_winner,
			'winner_total_minor'       => $winner_total_minor,
			'winner_total_display'     => $winner_total_display,
			'winner_bid_count'         => $winner_bid_count,
			'winner_claimed'           => $winner_claimed,
			'winner_summary'           => $winner_summary,
			'claim_label'              => $claim_label,
			'current_state'            => $current_state,
		'recent_bidders'           => $recent_bidders,
		'winner_claim_total_display' => $winner_claim_total_display,
			'terms_content'           => $terms_content,
		);

		ob_start();
		extract( $context ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		include CODFAA_PLUGIN_DIR . 'public/views/shortcode-auction.php';
		return ob_get_clean();
	}

	/**
	 * Build initial status payload for rendering.
	 *
	 * @param int  $auction_id     Auction ID.
	 * @param int  $user_id        Current user ID.
	 * @param int  $required       Required participants.
	 * @param int  $timer_seconds  Auction timer seconds.
	 * @param bool $is_registered  Whether current user is registered.
	 * @return array<string,mixed>
	 */
	private function get_initial_status_payload( $auction_id, $user_id, $required, $timer_seconds, $is_registered ) {
		$participants      = $this->get_participant_count( $auction_id );
		$progress_percent  = $required > 0 ? min( 100, ( $participants / max( 1, $required ) ) * 100 ) : ( $participants > 0 ? 100 : 0 );
		$participant_label = sprintf(
			__( 'Now Registered %s%%. Share this auction to reach 100%%', 'codex-ajax-auctions' ),
			number_format_i18n( (int) round( $progress_percent ) )
		);

		$last_bid_user = (int) get_post_meta( $auction_id, '_codfaa_last_bid_user', true );
		$last_bid_time = (int) get_post_meta( $auction_id, '_codfaa_last_bid_time', true );
		$ready_at      = (int) get_post_meta( $auction_id, '_codfaa_ready_at', true );
		$go_live_at    = (int) get_post_meta( $auction_id, '_codfaa_go_live_at', true );

		$server_time = current_time( 'timestamp', true );
		$prelive_remaining = 0;

		if ( $ready_at && $go_live_at && $server_time < $go_live_at ) {
			$prelive_remaining = max( 0, $go_live_at - $server_time );
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

		$ready = $required > 0 ? ( $participants >= $required ) : true;
		if ( $ready_at && $go_live_at ) {
			$ready = true;
		}
		$ended = $ready && $timer_seconds > 0 && $remaining <= 0 && $last_bid_user;

		$last_bidder_display = '';

		if ( $last_bid_user ) {
			$user = get_userdata( $last_bid_user );

			if ( $user ) {
				$last_bidder_display = $user->display_name ? $user->display_name : $user->user_login;
			} else {
				$last_bidder_display = sprintf( __( 'User #%d', 'codex-ajax-auctions' ), $last_bid_user );
			}

			$last_bidder_display = $this->mask_display_name( $last_bidder_display );
		}

		if ( $last_bid_user ) {
			if ( $user_id && (int) $last_bid_user === (int) $user_id ) {
				$status_message = __( 'Last bidder: You', 'codex-ajax-auctions' );
				$status_variant = 'success';
			} elseif ( $is_registered && $user_id ) {
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
			if ( $user_id && $last_bid_user === $user_id ) {
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

		return array(
			'participants'      => $participants,
			'progressPercent'   => $progress_percent,
			'participantLabel'  => $participant_label,
			'remaining'         => $remaining,
			'lastBidUser'       => $last_bid_user,
			'lastBidderDisplay' => $last_bidder_display,
			'ready'             => $ready,
			'ended'             => $ended,
			'statusMessage'     => $status_message,
			'statusVariant'     => $status_variant,
			'readyTimestamp'    => $ready_at,
			'goLiveTimestamp'   => $go_live_at,
			'preliveRemaining'  => $prelive_remaining,
		);
	}

	/**
	 * Mask a display name by revealing only the edges.
	 *
	 * @param string $name Raw display name.
	 * @return string
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
	 * Determine whether the user has a pending registration order awaiting confirmation.
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
	 * Count participants registered for an auction.
	 *
	 * @param int $auction_id Auction ID.
	 * @return int
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
	 * Get statistics for the current user within an auction.
	 *
	 * @param int $auction_id Auction ID.
	 * @param int $user_id    User ID.
	 * @return array{bid_count:int,total_minor:int,total_display:string}
	 */
	private function get_user_stats( $auction_id, $user_id ) {
		global $wpdb;

		$participant = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT total_reserved FROM {$wpdb->prefix}codfaa_auction_participants WHERE auction_id = %d AND user_id = %d AND status = 'active'",
				$auction_id,
				$user_id
			),
			ARRAY_A
		);

		$bid_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}codfaa_auction_bids WHERE auction_id = %d AND user_id = %d",
				$auction_id,
				$user_id
			)
		);

		$total_minor = $participant ? (int) $participant['total_reserved'] : 0;

		return array(
			'bid_count'     => $bid_count,
			'total_minor'   => $total_minor,
			'total_display' => wc_price( $total_minor / 100 ),
		);
	}

	/**
	 * Fetch recent bidders with totals for template display.
	 *
	 * @param int $auction_id Auction ID.
	 * @param int $limit      Number of bidders.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_recent_bidders( $auction_id, $limit = 5 ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id,
					SUM(reserved_amount) AS total_amount,
					COUNT(*) AS total_bids,
					MAX(created_at) AS last_bid_at
				FROM {$wpdb->prefix}codfaa_auction_bids
				WHERE auction_id = %d
				GROUP BY user_id
				ORDER BY last_bid_at DESC
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
			$user = get_userdata( (int) $row['user_id'] );
			$name = $user ? ( $user->display_name ? $user->display_name : $user->user_login ) : sprintf( __( 'User #%d', 'codex-ajax-auctions' ), $row['user_id'] );
			$name = $this->mask_display_name( $name );
			$total_minor = (int) $row['total_amount'];

			$results[] = array(
				'name'         => $name,
				'bidCount'     => (int) $row['total_bids'],
				'totalMinor'   => $total_minor,
				'totalDisplay' => wc_price( $total_minor / 100 ),
			);
		}

		return $results;
	}

	/**
	 * Format seconds as an mm:ss string.
	 *
	 * @param int $seconds Seconds remaining.
	 * @return string
	 */
	private function format_seconds( $seconds ) {
		$seconds  = max( 0, (int) $seconds );
		$minutes  = floor( $seconds / 60 );
		$remainder = $seconds % 60;

		return sprintf( '%02d:%02d', $minutes, $remainder );
	}

	/**
	 * Identify the page URL where the shortcode is rendered.
	 *
	 * @return string
	 */
	private function get_display_url() {
		global $post;

		if ( $post instanceof WP_Post ) {
			return get_permalink( $post );
		}

		return home_url( '/' );
	}

	/**
	 * Check if the given user has already registered for the auction.
	 *
	 * @param int $auction_id Auction ID.
	 * @param int $user_id    User ID.
	 * @return bool
	 */
	private function user_is_registered( $auction_id, $user_id ) {
		if ( ! $auction_id || ! $user_id ) {
			return false;
		}

		global $wpdb;

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}codfaa_auction_participants WHERE auction_id = %d AND user_id = %d AND status = 'active' LIMIT 1",
				$auction_id,
				$user_id
			)
		);

		return ! empty( $exists );
	}
}
