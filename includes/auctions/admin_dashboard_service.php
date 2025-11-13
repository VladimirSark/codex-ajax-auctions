<?php
/**
 * Admin dashboard for monitoring Codex auctions.
 *
 * @package CodexAjaxAuctions
 */

namespace Codfaa\Auctions;

defined( 'ABSPATH' ) || exit;

/**
 * Provides wp-admin dashboards and management tools for auctions.
 */
class Admin_Dashboard_Service {

	private const MENU_SLUG_STATS    = 'codfaa-auction-dashboard';
	private const MENU_SLUG_UPCOMING = 'codfaa-auction-upcoming';
	private const MENU_SLUG_LIVE     = 'codfaa-auction-live';
	private const MENU_SLUG_ENDED    = 'codfaa-auction-ended';
	private const MENU_SLUG_SETTINGS = 'codfaa-auction-settings';

	public const OPTION_TERMS_CONTENT = 'codfaa_terms_content';

	/**
	 * Boot admin hooks.
	 */
	public function boot() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_codfaa_start_auction', array( $this, 'handle_start' ) );
		add_action( 'admin_post_codfaa_end_auction', array( $this, 'handle_end' ) );
		add_action( 'admin_post_codfaa_restart_auction', array( $this, 'handle_restart' ) );
		add_action( 'admin_post_codfaa_reset_claim', array( $this, 'handle_reset_claim' ) );
		add_action( 'admin_post_codfaa_remove_participant', array( $this, 'handle_remove_participant' ) );
		add_action( 'admin_post_codfaa_email_participant', array( $this, 'handle_email_participant' ) );
		add_action( 'admin_notices', array( $this, 'render_notice' ) );
	}

	/**
	 * Register submenu pages for the auction post type.
	 */
	public function register_menu() {
		$parent = 'edit.php?post_type=' . Auction_Post_Type::POST_TYPE;

		add_submenu_page(
			$parent,
			__( 'Auction Statistics', 'codex-ajax-auctions' ),
			__( 'Statistics', 'codex-ajax-auctions' ),
			'manage_woocommerce',
			self::MENU_SLUG_STATS,
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			$parent,
			__( 'Upcoming Auctions', 'codex-ajax-auctions' ),
			__( 'Upcoming Auctions', 'codex-ajax-auctions' ),
			'manage_woocommerce',
			self::MENU_SLUG_UPCOMING,
			array( $this, 'render_upcoming_page' )
		);

		add_submenu_page(
			$parent,
			__( 'Live Auctions', 'codex-ajax-auctions' ),
			__( 'Live Auctions', 'codex-ajax-auctions' ),
			'manage_woocommerce',
			self::MENU_SLUG_LIVE,
			array( $this, 'render_live_page' )
		);

		add_submenu_page(
			$parent,
			__( 'Ended Auctions', 'codex-ajax-auctions' ),
			__( 'Ended Auctions', 'codex-ajax-auctions' ),
			'manage_woocommerce',
			self::MENU_SLUG_ENDED,
			array( $this, 'render_ended_page' )
		);

		add_submenu_page(
			$parent,
			__( 'Auction Settings', 'codex-ajax-auctions' ),
			__( 'Settings', 'codex-ajax-auctions' ),
			'manage_woocommerce',
			self::MENU_SLUG_SETTINGS,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin-only assets for dashboard pages.
	 *
	 * @param string $hook Current admin hook suffix.
	 */
	public function enqueue_assets( $hook ) {
		if ( 'edit.php' !== $hook ) {
			return;
		}

		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! in_array( $page, $this->get_menu_slugs(), true ) ) {
			return;
		}

		wp_enqueue_style(
			'codfaa-admin-dashboard',
			CODFAA_PLUGIN_URL . 'admin/css/dashboard.css',
			array(),
			\Codex_Ajax_Auctions::VERSION
		);
	}

	/**
	 * Handle manual start action.
	 */
	public function handle_start() {
		$auction_id = isset( $_POST['auction_id'] ) ? absint( $_POST['auction_id'] ) : 0;
		$nonce      = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! $auction_id || ! wp_verify_nonce( $nonce, 'codfaa_start_auction_' . $auction_id ) ) {
			wp_die( __( 'Invalid request.', 'codex-ajax-auctions' ) );
		}

		if ( ! current_user_can( 'edit_post', $auction_id ) ) {
			wp_die( __( 'You do not have permission to modify this auction.', 'codex-ajax-auctions' ) );
		}

		update_post_meta( $auction_id, '_codfaa_state', Bidding_Service::STATE_LIVE );
		update_post_meta( $auction_id, '_codfaa_started_at', current_time( 'mysql' ) );

		$redirect = $this->build_page_url(
			self::MENU_SLUG_STATS,
			array(
				'codfaa_notice' => 'auction_started',
				'codfaa_target' => $auction_id,
			)
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle manual end action.
	 */
	public function handle_end() {
		$auction_id = isset( $_POST['auction_id'] ) ? absint( $_POST['auction_id'] ) : 0;
		$nonce      = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! $auction_id || ! wp_verify_nonce( $nonce, 'codfaa_end_auction_' . $auction_id ) ) {
			wp_die( __( 'Invalid request.', 'codex-ajax-auctions' ) );
		}

		if ( ! current_user_can( 'edit_post', $auction_id ) ) {
			wp_die( __( 'You do not have permission to modify this auction.', 'codex-ajax-auctions' ) );
		}

		$last_bid_user = (int) get_post_meta( $auction_id, '_codfaa_last_bid_user', true );

		if ( $last_bid_user ) {
			$this->finalize_winner( $auction_id, $last_bid_user );
		}

		update_post_meta( $auction_id, '_codfaa_state', Bidding_Service::STATE_ENDED );
		update_post_meta( $auction_id, '_codfaa_ended_at', current_time( 'mysql' ) );

		$redirect = $this->build_page_url(
			self::MENU_SLUG_STATS,
			array(
				'codfaa_notice' => 'auction_ended',
				'codfaa_target' => $auction_id,
			)
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle restarting an ended auction.
	 */
	public function handle_restart() {
		$auction_id = isset( $_POST['auction_id'] ) ? absint( $_POST['auction_id'] ) : 0;
		$nonce      = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! $auction_id || ! wp_verify_nonce( $nonce, 'codfaa_restart_auction_' . $auction_id ) ) {
			wp_die( __( 'Invalid request.', 'codex-ajax-auctions' ) );
		}

		if ( ! current_user_can( 'edit_post', $auction_id ) ) {
			wp_die( __( 'You do not have permission to modify this auction.', 'codex-ajax-auctions' ) );
		}

		$this->restart_auction( $auction_id );
		$this->log_admin_event( $auction_id, 'restart' );

		$redirect = $this->build_page_url(
			self::MENU_SLUG_STATS,
			array(
				'codfaa_notice' => 'auction_restarted',
				'codfaa_target' => $auction_id,
			)
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle resetting a winner claim.
	 */
	public function handle_reset_claim() {
		$auction_id = isset( $_POST['auction_id'] ) ? absint( $_POST['auction_id'] ) : 0;
		$nonce      = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! $auction_id || ! wp_verify_nonce( $nonce, 'codfaa_reset_claim_' . $auction_id ) ) {
			wp_die( __( 'Invalid request.', 'codex-ajax-auctions' ) );
		}

		if ( ! current_user_can( 'edit_post', $auction_id ) ) {
			wp_die( __( 'You do not have permission to modify this auction.', 'codex-ajax-auctions' ) );
		}

		update_post_meta( $auction_id, '_codfaa_winner_claimed', 'no' );
		delete_post_meta( $auction_id, '_codfaa_winner_claimed_at' );

		$redirect = $this->build_page_url(
			self::MENU_SLUG_STATS,
			array(
				'codfaa_notice' => 'claim_reset',
				'codfaa_target' => $auction_id,
			)
		);

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Remove a participant from an upcoming auction.
	 */
	public function handle_remove_participant() {
		$auction_id = isset( $_POST['auction_id'] ) ? absint( $_POST['auction_id'] ) : 0;
		$user_id    = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$nonce      = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		$redirect   = isset( $_POST['redirect'] ) ? $this->sanitize_redirect_url( wp_unslash( $_POST['redirect'] ), $this->build_page_url( self::MENU_SLUG_UPCOMING ) ) : $this->build_page_url( self::MENU_SLUG_UPCOMING );

		if ( ! $auction_id || ! $user_id || ! wp_verify_nonce( $nonce, 'codfaa_remove_participant_' . $auction_id . '_' . $user_id ) ) {
			wp_die( __( 'Invalid request.', 'codex-ajax-auctions' ) );
		}

		if ( ! current_user_can( 'edit_post', $auction_id ) ) {
			wp_die( __( 'You do not have permission to modify this auction.', 'codex-ajax-auctions' ) );
		}

		if ( ! $this->can_remove_participants( $auction_id ) ) {
			$redirect = add_query_arg( 'codfaa_notice', 'participant_remove_locked', $redirect );
			wp_safe_redirect( $redirect );
			exit;
		}

		global $wpdb;

		$reason  = isset( $_POST['reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ) : '';
		$updated = $wpdb->update(
			$wpdb->prefix . 'codfaa_auction_participants',
			array(
				'status'         => 'removed',
				'removed_by'     => get_current_user_id(),
				'removed_at'     => current_time( 'mysql' ),
				'removed_reason' => $reason,
			),
			array(
				'auction_id' => $auction_id,
				'user_id'    => $user_id,
				'status'     => 'active',
			),
			array( '%s', '%d', '%s', '%s' ),
			array( '%d', '%d', '%s' )
		);
		if ( $updated ) {
			$this->log_admin_event( $auction_id, 'participant_removed', $user_id, $reason );
			do_action( 'codfaa_participant_removed', $auction_id, $user_id );
			$notice = 'participant_removed';
		} else {
			$notice = 'participant_remove_failed';
		}

		$redirect = add_query_arg( 'codfaa_notice', $notice, $redirect );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Email a participant directly from the admin page.
	 */
	public function handle_email_participant() {
		$auction_id = isset( $_POST['auction_id'] ) ? absint( $_POST['auction_id'] ) : 0;
		$user_id    = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
		$nonce      = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		$redirect   = isset( $_POST['redirect'] ) ? $this->sanitize_redirect_url( wp_unslash( $_POST['redirect'] ), $this->build_page_url( self::MENU_SLUG_UPCOMING ) ) : $this->build_page_url( self::MENU_SLUG_UPCOMING );
		$subject    = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
		$message    = isset( $_POST['message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';

		if ( ! $auction_id || ! $user_id || ! wp_verify_nonce( $nonce, 'codfaa_email_participant_' . $auction_id . '_' . $user_id ) ) {
			wp_die( __( 'Invalid request.', 'codex-ajax-auctions' ) );
		}

		if ( ! current_user_can( 'edit_post', $auction_id ) ) {
			wp_die( __( 'You do not have permission to modify this auction.', 'codex-ajax-auctions' ) );
		}

		$contact = $this->get_participant_contact( $auction_id, $user_id );

		if ( ! $contact || empty( $contact['email'] ) ) {
			$redirect = add_query_arg( 'codfaa_notice', 'participant_email_failed', $redirect );
			wp_safe_redirect( $redirect );
			exit;
		}

		if ( '' === trim( $subject ) || '' === trim( $message ) ) {
			$redirect = add_query_arg( 'codfaa_notice', 'participant_email_failed', $redirect );
			wp_safe_redirect( $redirect );
			exit;
		}

		$sent = wp_mail( $contact['email'], $subject, $message, array( 'Content-Type: text/plain; charset=UTF-8' ) );

		if ( $sent ) {
			$this->log_admin_event( $auction_id, 'participant_email', $user_id, $subject );
			do_action( 'codfaa_participant_emailed', $auction_id, $user_id );
			$notice = 'participant_email_sent';
		} else {
			$notice = 'participant_email_failed';
		}

		$redirect = add_query_arg( 'codfaa_notice', $notice, $redirect );
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Render statistics overview page.
	 */
	public function render_dashboard() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'You do not have permission to view this page.', 'codex-ajax-auctions' ) );
		}

		$groups = $this->get_grouped_rows();

		include CODFAA_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Render upcoming-auctions management page.
	 */
	public function render_upcoming_page() {
		$this->render_state_page(
			Bidding_Service::STATE_UPCOMING,
			self::MENU_SLUG_UPCOMING,
			__( 'Upcoming Auctions', 'codex-ajax-auctions' ),
			__( 'Review each auction, manage registrations, or contact participants before the timer starts.', 'codex-ajax-auctions' )
		);
	}

	/**
	 * Render live-auctions page.
	 */
	public function render_live_page() {
		$this->render_state_page(
			Bidding_Service::STATE_LIVE,
			self::MENU_SLUG_LIVE,
			__( 'Live Auctions', 'codex-ajax-auctions' ),
			__( 'Monitor active bidding, recent logs, and participant stats for auctions that are in progress.', 'codex-ajax-auctions' )
		);
	}

	/**
	 * Render ended-auctions page.
	 */
	public function render_ended_page() {
		$this->render_state_page(
			Bidding_Service::STATE_ENDED,
			self::MENU_SLUG_ENDED,
			__( 'Ended Auctions', 'codex-ajax-auctions' ),
			__( 'Inspect winners, claim states, and post-auction history.', 'codex-ajax-auctions' )
		);
	}

	/**
	 * Render auction settings page.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'You do not have permission to view this page.', 'codex-ajax-auctions' ) );
		}

		if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['codfaa_settings_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			check_admin_referer( 'codfaa_save_settings' );

			$terms_content = isset( $_POST['codfaa_terms_content'] ) ? wp_kses_post( wp_unslash( $_POST['codfaa_terms_content'] ) ) : '';

			update_option( self::OPTION_TERMS_CONTENT, $terms_content );

			$redirect = $this->build_page_url(
				self::MENU_SLUG_SETTINGS,
				array(
					'codfaa_notice' => 'settings_saved',
				)
			);

			wp_safe_redirect( $redirect );
			exit;
		}

		$current_url = $this->build_page_url( self::MENU_SLUG_SETTINGS );
		$terms_content = (string) get_option( self::OPTION_TERMS_CONTENT, '' );

		include CODFAA_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Shared renderer for state-specific management pages.
	 *
	 * @param string $state     Auction state constant.
	 * @param string $page_slug Menu slug.
	 * @param string $title     Page heading.
	 * @param string $blurb     Intro text.
	 */
	private function render_state_page( $state, $page_slug, $title, $blurb ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'You do not have permission to view this page.', 'codex-ajax-auctions' ) );
		}

		$rows        = $this->get_state_rows( $state );
		$current_url = $this->build_page_url( $page_slug );
		$selected_id = isset( $_GET['auction'] ) ? absint( $_GET['auction'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected    = null;

		if ( $selected_id ) {
			$selected_row = null;

			foreach ( $rows as $row ) {
				if ( (int) $row['id'] === $selected_id ) {
					$selected_row = $row;
					break;
				}
			}

			if ( $selected_row ) {
				$selected = $this->get_management_context( $selected_id );
			} else {
				$selected_id = 0;
			}
		}

		$email_recipient = null;
		$email_user_id   = $selected ? ( isset( $_GET['email_user'] ) ? absint( $_GET['email_user'] ) : 0 ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $selected && $email_user_id ) {
			$email_recipient = $this->get_participant_contact( $selected['id'], $email_user_id );

			if ( empty( $email_recipient ) ) {
				$email_user_id   = 0;
				$email_recipient = null;
			}
		}

		$page_title    = $title;
		$description   = $blurb;
		$page_slug_var = $page_slug;
		$state_key     = $state;

		include CODFAA_PLUGIN_DIR . 'admin/views/state-page.php';
	}

	/**
	 * Output admin notice messages.
	 */
	public function render_notice() {
		if ( empty( $_GET['codfaa_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$notice = sanitize_text_field( wp_unslash( $_GET['codfaa_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$class = 'success';

		switch ( $notice ) {
			case 'auction_started':
				$message = __( 'Auction marked as live.', 'codex-ajax-auctions' );
				break;
			case 'auction_ended':
				$message = __( 'Auction ended successfully.', 'codex-ajax-auctions' );
				break;
			case 'claim_reset':
				$message = __( 'Winner claim has been reset.', 'codex-ajax-auctions' );
				break;
			case 'participant_removed':
				$message = __( 'Participant removed from the auction.', 'codex-ajax-auctions' );
				break;
			case 'participant_remove_failed':
				$message = __( 'Unable to remove the participant. They may already be removed.', 'codex-ajax-auctions' );
				$class   = 'error';
				break;
			case 'participant_remove_locked':
				$message = __( 'Countdown already started; participants can no longer be removed.', 'codex-ajax-auctions' );
				$class   = 'warning';
				break;
			case 'participant_email_sent':
				$message = __( 'Email sent to participant.', 'codex-ajax-auctions' );
				break;
			case 'participant_email_failed':
				$message = __( 'Unable to send email to participant.', 'codex-ajax-auctions' );
				$class   = 'error';
				break;
		case 'settings_saved':
			$message = __( 'Settings saved.', 'codex-ajax-auctions' );
			break;
		case 'auction_restarted':
			$message = __( 'Auction reset to upcoming state.', 'codex-ajax-auctions' );
			break;
			default:
				$message = '';
		}

		if ( $message ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
		}
	}

	/**
	 * Build grouped statistics for all auctions.
	 *
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	private function get_grouped_rows() {
		$auctions = get_posts(
			array(
				'post_type'      => Auction_Post_Type::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$groups = array(
			Bidding_Service::STATE_UPCOMING => array(),
			Bidding_Service::STATE_LIVE     => array(),
			Bidding_Service::STATE_ENDED    => array(),
		);

		foreach ( $auctions as $auction ) {
			$state = $this->get_auction_state( $auction->ID );

			if ( ! isset( $groups[ $state ] ) ) {
				$groups[ $state ] = array();
			}

			$groups[ $state ][] = $this->prepare_row( $auction );
		}

		return $groups;
	}

	/**
	 * Return rows limited to a single state.
	 *
	 * @param string $state Auction state.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_state_rows( $state ) {
		$groups = $this->get_grouped_rows();

		return isset( $groups[ $state ] ) ? $groups[ $state ] : array();
	}

	/**
	 * Prepare a dashboard row of data.
	 *
	 * @param \WP_Post $auction Auction object.
	 * @return array<string,mixed>
	 */
	private function prepare_row( \WP_Post $auction ) {
		$auction_id = $auction->ID;

		$product_id      = (int) get_post_meta( $auction_id, Auction_Post_Type::META_PRODUCT_ID, true );
		$registration_id  = (int) get_post_meta( $auction_id, Auction_Post_Type::META_REGISTRATION_ID, true );
		$last_bid_user    = (int) get_post_meta( $auction_id, '_codfaa_last_bid_user', true );
		$last_bidder      = $last_bid_user ? $this->get_user_display( $last_bid_user ) : __( '—', 'codex-ajax-auctions' );
		$participants     = $this->get_participant_count( $auction_id );
		$required         = $this->get_required_participants( $auction_id );
		$state            = $this->get_auction_state( $auction_id );
		$winner_user      = (int) get_post_meta( $auction_id, '_codfaa_winner_user', true );
		$winner_total     = (int) get_post_meta( $auction_id, '_codfaa_winner_total_minor', true );
		$winner_claimed   = 'yes' === get_post_meta( $auction_id, '_codfaa_winner_claimed', true );
		$last_bid_totals  = $last_bid_user ? $this->get_user_totals( $auction_id, $last_bid_user ) : array( 'total_minor' => 0, 'bid_count' => 0 );
		$registration_fee = $registration_id ? $this->get_product_price_minor( $registration_id ) : 0;
		$registration_sum = $registration_fee * max( 0, $participants );
		$bid_total_minor  = $this->get_total_bid_fees( $auction_id );

		return array(
			'id'                        => $auction_id,
			'title'                     => get_the_title( $auction ),
			'edit_link'                 => get_edit_post_link( $auction_id ),
			'product'                   => $product_id ? get_the_title( $product_id ) : __( '—', 'codex-ajax-auctions' ),
			'registration'              => $registration_id ? get_the_title( $registration_id ) : __( '—', 'codex-ajax-auctions' ),
			'participants'              => $participants,
			'required'                  => $required,
			'last_bidder'               => $last_bidder,
			'last_bid_total'            => $last_bid_totals['total_minor'],
			'last_bid_total_display'    => $last_bid_totals['total_minor'] ? wc_price( $last_bid_totals['total_minor'] / 100 ) : '—',
			'winner_user'               => $winner_user,
			'winner_display'            => $winner_user ? $this->get_user_display( $winner_user ) : __( '—', 'codex-ajax-auctions' ),
			'winner_total_minor'        => $winner_total,
			'winner_total_display'      => $winner_total ? wc_price( $winner_total / 100 ) : '—',
			'winner_claimed'            => $winner_claimed,
			'state'                     => $state,
			'registration_total_minor'  => $registration_sum,
			'registration_total_display'=> $registration_sum ? wc_price( $registration_sum / 100 ) : '—',
			'bid_total_minor'           => $bid_total_minor,
			'bid_total_display'         => $bid_total_minor ? wc_price( $bid_total_minor / 100 ) : '—',
		);
	}

	/**
	 * Get auction state (ensuring meta default).
	 *
	 * @param int $auction_id Auction ID.
	 * @return string
	 */
	private function get_auction_state( $auction_id ) {
		$state = get_post_meta( $auction_id, '_codfaa_state', true );

		if ( ! $state ) {
			$winner_user = (int) get_post_meta( $auction_id, '_codfaa_winner_user', true );
			$state       = $winner_user ? Bidding_Service::STATE_ENDED : Bidding_Service::STATE_UPCOMING;
			update_post_meta( $auction_id, '_codfaa_state', $state );
		}

		return $state;
	}

	/**
	 * Finalize the winner metadata for an auction.
	 *
	 * @param int $auction_id Auction ID.
	 * @param int $winner_user Winner user ID.
	 */
	private function finalize_winner( $auction_id, $winner_user ) {
		$totals = $this->get_user_totals( $auction_id, $winner_user );

		update_post_meta( $auction_id, '_codfaa_winner_user', $winner_user );
		update_post_meta( $auction_id, '_codfaa_winner_total_minor', (int) $totals['total_minor'] );
		update_post_meta( $auction_id, '_codfaa_winner_bid_count', (int) $totals['bid_count'] );
	}

	/**
	 * Reset runtime data so an ended auction can start over.
	 *
	 * @param int $auction_id Auction ID.
	 */
	private function restart_auction( $auction_id ) {
		$meta_keys = array(
			'_codfaa_started_at',
			'_codfaa_ended_at',
			'_codfaa_last_bid_user',
			'_codfaa_last_bid_time',
			'_codfaa_last_bid_amount',
			'_codfaa_ready_at',
			'_codfaa_go_live_at',
			'_codfaa_ready_notified',
			'_codfaa_winner_user',
			'_codfaa_winner_total_minor',
			'_codfaa_winner_bid_count',
			'_codfaa_winner_recorded_at',
			'_codfaa_winner_claimed',
			'_codfaa_winner_claimed_at',
		);

		foreach ( $meta_keys as $meta_key ) {
			delete_post_meta( $auction_id, $meta_key );
		}

		update_post_meta( $auction_id, '_codfaa_state', Bidding_Service::STATE_UPCOMING );

		global $wpdb;

		$wpdb->delete( $wpdb->prefix . 'codfaa_auction_bids', array( 'auction_id' => $auction_id ), array( '%d' ) );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}codfaa_auction_participants SET total_reserved = 0 WHERE auction_id = %d", $auction_id ) );
	}

	/**
	 * Fetch totals for a specific user.
	 *
	 * @param int $auction_id Auction ID.
	 * @param int $user_id    User ID.
	 * @return array<string,int>
	 */
	private function get_user_totals( $auction_id, $user_id ) {
		global $wpdb;

		$total_minor = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT total_reserved FROM {$wpdb->prefix}codfaa_auction_participants WHERE auction_id = %d AND user_id = %d AND status = 'active'",
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
	 * Sum bid fees reserved by active participants.
	 *
	 * @param int $auction_id Auction ID.
	 * @return int
	 */
	private function get_total_bid_fees( $auction_id ) {
		global $wpdb;

		$sum = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(total_reserved) FROM {$wpdb->prefix}codfaa_auction_participants WHERE auction_id = %d AND status = 'active'",
				$auction_id
			)
		);

		return max( 0, $sum );
	}

	/**
	 * Determine how many participants currently count toward the requirement.
	 *
	 * @param int    $auction_id Auction ID.
	 * @param string $status     Status filter (active|removed|all).
	 * @return int
	 */
	private function get_participant_count( $auction_id, $status = 'active' ) {
		global $wpdb;

		$sql    = "SELECT COUNT(*) FROM {$wpdb->prefix}codfaa_auction_participants WHERE auction_id = %d";
		$params = array( $auction_id );

		if ( 'all' !== $status ) {
			$sql     .= ' AND status = %s';
			$params[] = $status;
		}

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, ...$params ) );
	}

	/**
	 * Helper to fetch participation requirement.
	 *
	 * @param int $auction_id Auction ID.
	 * @return int
	 */
	private function get_required_participants( $auction_id ) {
		return (int) get_post_meta( $auction_id, Auction_Post_Type::META_REQUIRED_PARTICIPANTS, true );
	}

	/**
	 * Retrieve product price in minor units.
	 *
	 * @param int $product_id Product ID.
	 * @return int
	 */
	private function get_product_price_minor( $product_id ) {
		if ( ! $product_id ) {
			return 0;
		}

		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return 0;
		}

		$display_price = (float) wc_get_price_to_display( $product );

		return (int) round( $display_price * 100 );
	}

	/**
	 * Determine if participants can still be removed for an auction.
	 *
	 * @param int $auction_id Auction ID.
	 * @return bool
	 */
	private function can_remove_participants( $auction_id ) {
		$state    = $this->get_auction_state( $auction_id );
		$ready_at = (int) get_post_meta( $auction_id, '_codfaa_ready_at', true );

		return Bidding_Service::STATE_UPCOMING === $state && empty( $ready_at );
	}

	/**
	 * Fetch participants with administrative metadata.
	 *
	 * @param int $auction_id Auction ID.
	 * @return array<int,array<string,mixed>>
	 */
	private function get_participant_rows( $auction_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, order_id, registered_at, total_reserved, status, removed_by, removed_at, removed_reason
				FROM {$wpdb->prefix}codfaa_auction_participants
				WHERE auction_id = %d
				ORDER BY registered_at ASC",
				$auction_id
			),
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return array();
		}

		$allow_removal = $this->can_remove_participants( $auction_id );
		$data          = array();

		foreach ( $rows as $row ) {
			$user_id = (int) $row['user_id'];
			$user    = get_userdata( $user_id );
			$name    = $user ? ( $user->display_name ? $user->display_name : $user->user_login ) : sprintf( __( 'User #%d', 'codex-ajax-auctions' ), $user_id );
			$email   = $user && $user->user_email ? $user->user_email : '';

			$status       = $row['status'] ? $row['status'] : 'active';
			$status_label = 'removed' === $status ? __( 'Removed', 'codex-ajax-auctions' ) : __( 'Active', 'codex-ajax-auctions' );
			$registered   = $row['registered_at'] ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['registered_at'] ) : '—';
			$removed_at   = $row['removed_at'] ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['removed_at'] ) : '';
			$removed_by   = $row['removed_by'] ? $this->get_user_display( (int) $row['removed_by'] ) : '';
			$order_link   = $row['order_id'] ? get_edit_post_link( (int) $row['order_id'] ) : '';
			$order_label  = $row['order_id'] ? sprintf( '#%d', (int) $row['order_id'] ) : '—';

			$data[] = array(
				'user_id'        => $user_id,
				'name'           => $name,
				'email'          => $email,
				'status'         => $status,
				'status_label'   => $status_label,
				'registered_at'  => $registered,
				'order_id'       => (int) $row['order_id'],
				'order_label'    => $order_label,
				'order_link'     => $order_link,
				'total_display'  => wc_price( (int) $row['total_reserved'] / 100 ),
				'can_remove'     => ( 'active' === $status && $allow_removal ),
				'removed_at'     => $removed_at,
				'removed_by'     => $removed_by,
				'removed_reason' => $row['removed_reason'],
			);
		}

		return $data;
	}

	/**
	 * Fetch recent bids for detail pages.
	 *
	 * @param int $auction_id Auction ID.
	 * @return array<int,array<string,string>>
	 */
	private function get_bids_view( $auction_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'codfaa_auction_bids';

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, reserved_amount, created_at FROM {$table} WHERE auction_id = %d ORDER BY created_at DESC LIMIT 100",
				$auction_id
			),
			ARRAY_A
		);

		$data = array();

		foreach ( $rows as $row ) {
			$data[] = array(
				'user'       => $this->get_user_display( (int) $row['user_id'] ),
				'amount'     => wc_price( (int) $row['reserved_amount'] / 100 ),
				'created_at' => $row['created_at'] ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['created_at'] ) : '—',
			);
		}

		return $data;
	}

	/**
	 * Retrieve admin log entries stored in post meta.
	 *
	 * @param int $auction_id Auction ID.
	 * @return array<int,array<string,string>>
	 */
	private function get_admin_logs( $auction_id ) {
		$entries = get_post_meta( $auction_id, '_codfaa_admin_log' );

		if ( empty( $entries ) ) {
			return array();
		}

		$logs = array();

		foreach ( $entries as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}

			$raw_time = isset( $entry['timestamp'] ) ? $entry['timestamp'] : '';

			$logs[] = array(
				'type'          => isset( $entry['type'] ) ? $entry['type'] : 'note',
				'actor'         => isset( $entry['actor'] ) ? $this->get_user_display( (int) $entry['actor'] ) : '',
				'target'        => isset( $entry['target'] ) && $entry['target'] ? $this->get_user_display( (int) $entry['target'] ) : '',
				'context'       => isset( $entry['context'] ) ? $entry['context'] : '',
				'timestamp'     => $raw_time ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $raw_time ) : '',
				'timestamp_raw' => $raw_time,
			);
		}

		usort(
			$logs,
			function ( $a, $b ) {
				return strcmp( (string) $b['timestamp_raw'], (string) $a['timestamp_raw'] );
			}
		);

		return $logs;
	}

	/**
	 * Compile management context for detailed view.
	 *
	 * @param int $auction_id Auction ID.
	 * @return array<string,mixed>|null
	 */
	private function get_management_context( $auction_id ) {
		$auction = get_post( $auction_id );

		if ( ! $auction || Auction_Post_Type::POST_TYPE !== $auction->post_type ) {
			return null;
		}

		$product_id     = (int) get_post_meta( $auction_id, Auction_Post_Type::META_PRODUCT_ID, true );
		$registration_id = (int) get_post_meta( $auction_id, Auction_Post_Type::META_REGISTRATION_ID, true );
		$state           = $this->get_auction_state( $auction_id );
		$participants    = $this->get_participant_count( $auction_id );
		$removed         = $this->get_participant_count( $auction_id, 'removed' );
		$required        = $this->get_required_participants( $auction_id );
		$ready_at        = (int) get_post_meta( $auction_id, '_codfaa_ready_at', true );
		$go_live_at      = (int) get_post_meta( $auction_id, '_codfaa_go_live_at', true );
		$winner_claimed  = 'yes' === get_post_meta( $auction_id, '_codfaa_winner_claimed', true );
		$counts          = array(
			'active'   => $participants,
			'removed'  => $removed,
			'required' => $required,
		);

		return array(
			'id'           => $auction_id,
			'title'        => get_the_title( $auction ),
			'permalink'    => get_permalink( $auction ),
			'state'        => $state,
			'counts'       => $counts,
			'winner_claimed' => $winner_claimed,
			'product'      => array(
				'id'        => $product_id,
				'label'     => $product_id ? get_the_title( $product_id ) : __( '—', 'codex-ajax-auctions' ),
				'edit_link' => $product_id ? get_edit_post_link( $product_id ) : '',
			),
			'registration' => array(
				'id'        => $registration_id,
				'label'     => $registration_id ? get_the_title( $registration_id ) : __( '—', 'codex-ajax-auctions' ),
				'edit_link' => $registration_id ? get_edit_post_link( $registration_id ) : '',
			),
			'allow_removal'=> $this->can_remove_participants( $auction_id ),
			'participants' => $this->get_participant_rows( $auction_id ),
			'bids'         => $this->get_bids_view( $auction_id ),
			'logs'         => $this->get_admin_logs( $auction_id ),
			'ready_at'     => $ready_at,
			'go_live_at'   => $go_live_at,
		);
	}

	/**
	 * Persist an admin action log entry.
	 *
	 * @param int    $auction_id Auction ID.
	 * @param string $type       Action type.
	 * @param int    $target     Target user ID.
	 * @param string $context    Optional context string.
	 */
	private function log_admin_event( $auction_id, $type, $target = 0, $context = '' ) {
		$entry = array(
			'type'      => sanitize_key( $type ),
			'actor'     => get_current_user_id(),
			'target'    => absint( $target ),
			'context'   => sanitize_textarea_field( $context ),
			'timestamp' => current_time( 'mysql' ),
		);

		add_post_meta( $auction_id, '_codfaa_admin_log', $entry );
	}

	/**
	 * Fetch participant contact data for emailing.
	 *
	 * @param int $auction_id Auction ID.
	 * @param int $user_id    User ID.
	 * @return array<string,string|int>|null
	 */
	private function get_participant_contact( $auction_id, $user_id ) {
		global $wpdb;

		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}codfaa_auction_participants WHERE auction_id = %d AND user_id = %d LIMIT 1",
				$auction_id,
				$user_id
			)
		);

		if ( ! $exists ) {
			return null;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return null;
		}

		return array(
			'id'    => $user_id,
			'name'  => $user->display_name ? $user->display_name : $user->user_login,
			'email' => $user->user_email,
		);
	}

	/**
	 * Helper for redirect sanitization.
	 *
	 * @param string $url      Raw URL.
	 * @param string $fallback Fallback URL.
	 * @return string
	 */
	private function sanitize_redirect_url( $url, $fallback ) {
		$validated = wp_validate_redirect( $url, false );

		if ( $validated ) {
			return $validated;
		}

		return $fallback;
	}

	/**
	 * Build an admin URL for a given submenu page.
	 *
	 * @param string               $slug Menu slug.
	 * @param array<string,string> $args Additional query args.
	 * @return string
	 */
	private function build_page_url( $slug, $args = array() ) {
		$base_args = array(
			'post_type' => Auction_Post_Type::POST_TYPE,
			'page'      => $slug,
		);

		return add_query_arg( array_merge( $base_args, $args ), admin_url( 'edit.php' ) );
	}

	/**
	 * List valid submenu slugs.
	 *
	 * @return array<int,string>
	 */
	private function get_menu_slugs() {
		return array(
			self::MENU_SLUG_STATS,
			self::MENU_SLUG_UPCOMING,
			self::MENU_SLUG_LIVE,
			self::MENU_SLUG_ENDED,
			self::MENU_SLUG_SETTINGS,
		);
	}

	/**
	 * Helper to get a user's display name.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function get_user_display( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return sprintf( __( 'User #%d', 'codex-ajax-auctions' ), $user_id );
		}

		return $user->display_name ? $user->display_name : $user->user_login;
	}
}
